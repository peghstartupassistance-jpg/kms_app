<?php
// audit_stock.php - Diagnostic complet du stock

require_once __DIR__ . '/db/db.php';

echo "<h2>🔍 AUDIT COMPLET DU STOCK</h2>";
echo "<hr>";

// ============================================
// 1. Comparer les deux tables de mouvements
// ============================================
echo "<h3>1️⃣ État des deux tables de mouvements</h3>";
// helper: existe-t-on une table ?
function tableExists(PDO $pdo, string $name): bool {
    // Utiliser information_schema.tables pour éviter les limitations
    // de certaines versions/driver PDO avec "SHOW TABLES LIKE ?".
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':t' => $name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

$hasMouvementsStock = tableExists($pdo, 'mouvements_stock');
$hasStocksMouvements = tableExists($pdo, 'stocks_mouvements');

$cnt1 = $hasMouvementsStock ? (int)$pdo->query("SELECT COUNT(*) as cnt FROM mouvements_stock")->fetch()['cnt'] : 0;
$cnt2 = $hasStocksMouvements ? (int)$pdo->query("SELECT COUNT(*) as cnt FROM stocks_mouvements")->fetch()['cnt'] : 0;

echo "<p>mouvements_stock : <strong>" . ($hasMouvementsStock ? "$cnt1 lignes" : "introuvable") . "</strong></p>";
echo "<p>stocks_mouvements : <strong>" . ($hasStocksMouvements ? "$cnt2 lignes" : "introuvable") . "</strong></p>";

// chercher un backup éventuel
$backupTable = null;
$stmt = $pdo->query("SHOW TABLES LIKE 'mouvements_stock_backup_%'");
$b = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!empty($b)) {
    // choisir le plus récent (nom contient la date)
    rsort($b);
    $backupTable = $b[0];
    echo "<p>Backup trouvé : <strong>" . htmlspecialchars($backupTable) . "</strong></p>";
}

// ============================================
// 2. Pour chaque produit, comparer le stock
// ============================================
echo "<h3>2️⃣ Cohérence stock par produit</h3>";

$stmt = $pdo->query("SELECT id, code_produit, stock_actuel FROM produits ORDER BY id");
$produits = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Code</th><th>stock_actuel (BD)</th><th>Théorique stocks_mouvements</th><th>Théorique mouvements_stock</th><th>Statut</th></tr>";

foreach ($produits as $p) {
    $id = $p['id'];
    $code = $p['code_produit'];
    $stock_bd = $p['stock_actuel'];
    
    // Calcul stock théorique depuis stocks_mouvements
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN type_mouvement = 'ENTREE' THEN quantite
                        WHEN type_mouvement = 'SORTIE' THEN -quantite
                        WHEN type_mouvement = 'AJUSTEMENT' THEN quantite
                        ELSE 0
                    END
                ),
                0
            ) AS qte
        FROM stocks_mouvements
        WHERE produit_id = :pid
    ");
    $stmt->execute([':pid' => $id]);
    $stock_theo1 = $stmt->fetch()['qte'];
    
    // Calcul stock théorique depuis mouvements_stock
    $stock_theo2 = 'N/A';
    if ($hasMouvementsStock) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(
                        CASE
                            WHEN type_mouvement = 'ENTREE' THEN quantite
                            WHEN type_mouvement = 'SORTIE' THEN -quantite
                            WHEN type_mouvement = 'CORRECTION' THEN quantite
                            ELSE 0
                        END
                    ),
                    0
                ) AS qte
            FROM mouvements_stock
            WHERE produit_id = :pid
        ");
        $stmt->execute([':pid' => $id]);
        $stock_theo2 = $stmt->fetch()['qte'];
    } elseif (!empty($backupTable)) {
        // essayer depuis la table backup
        $sql = "SELECT COALESCE(SUM(CASE WHEN type_mouvement='ENTREE' THEN quantite WHEN type_mouvement='SORTIE' THEN -quantite WHEN type_mouvement='CORRECTION' THEN quantite ELSE 0 END),0) AS qte FROM `" . str_replace('`','', $backupTable) . "` WHERE produit_id = :pid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pid' => $id]);
        $stock_theo2 = $stmt->fetch()['qte'];
    }
    
    // Vérifier la cohérence
    $ok = ($stock_bd == $stock_theo1);
    $status = $ok ? '✓ OK' : '✗ INCOHÉRENT';
    
    echo "<tr>";
    echo "<td>$id</td>";
    echo "<td>$code</td>";
    echo "<td><strong>$stock_bd</strong></td>";
    echo "<td>$stock_theo1</td>";
    echo "<td>$stock_theo2</td>";
    echo "<td><strong style='color: " . ($ok ? 'green' : 'red') . "'>$status</strong></td>";
    echo "</tr>";
}

echo "</table>";

// ============================================
// 3. Détail des mouvements par produit
// ============================================
echo "<h3>3️⃣ Détail des mouvements (stocks_mouvements)</h3>";

$stmt = $pdo->query("
    SELECT 
        p.id, p.code_produit, p.stock_actuel,
        sm.type_mouvement, sm.quantite, sm.source_type, sm.commentaire
    FROM produits p
    LEFT JOIN stocks_mouvements sm ON p.id = sm.produit_id
    ORDER BY p.id, sm.date_mouvement DESC
");
$rows = $stmt->fetchAll();

foreach ($rows as $row) {
    if ($row['type_mouvement'] === null) {
        echo "<p><strong>Produit {$row['code_produit']} (ID={$row['id']})</strong> : <em>Aucun mouvement</em></p>";
    } else {
        echo "<p><strong>Produit {$row['code_produit']}</strong> - {$row['type_mouvement']} {$row['quantite']} ({$row['source_type']}) : {$row['commentaire']}</p>";
    }
}

// ============================================
// 4. Détail des mouvements mouvements_stock
// ============================================
echo "<h3>4️⃣ Détail des mouvements (mouvements_stock)</h3>";

if ($hasMouvementsStock) {
    $stmt = $pdo->query("
        SELECT 
            p.id, p.code_produit,
            ms.type_mouvement, ms.quantite, ms.source_module, ms.commentaire
        FROM produits p
        LEFT JOIN mouvements_stock ms ON p.id = ms.produit_id
        ORDER BY p.id, ms.date_mouvement DESC
    ");
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if ($row['type_mouvement'] === null) {
            echo "<p><strong>Produit {$row['code_produit']} (ID={$row['id']})</strong> : <em>Aucun mouvement</em></p>";
        } else {
            echo "<p><strong>Produit {$row['code_produit']}</strong> - {$row['type_mouvement']} {$row['quantite']} ({$row['source_module']}) : {$row['commentaire']}</p>";
        }
    }

} elseif (!empty($backupTable)) {
    echo "<p>La table <strong>mouvements_stock</strong> est absente — affichage depuis backup <strong>" . htmlspecialchars($backupTable) . "</strong> :</p>";
    $sql = "
        SELECT 
            p.id, p.code_produit,
            ms.type_mouvement, ms.quantite, ms.source_module, ms.commentaire
        FROM produits p
        LEFT JOIN `" . str_replace('`','', $backupTable) . "` ms ON p.id = ms.produit_id
        ORDER BY p.id, ms.date_mouvement DESC
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        if ($row['type_mouvement'] === null) {
            echo "<p><strong>Produit {$row['code_produit']} (ID={$row['id']})</strong> : <em>Aucun mouvement</em></p>";
        } else {
            echo "<p><strong>Produit {$row['code_produit']}</strong> - {$row['type_mouvement']} {$row['quantite']} ({$row['source_module']}) : {$row['commentaire']}</p>";
        }
    }
} else {
    echo "<p>Table <strong>mouvements_stock</strong> introuvable et aucun backup détecté.</p>";
}

echo "<hr>";
echo "<p><strong>✅ Audit terminé. Utilise les informations ci-dessus pour la migration.</strong></p>";
?>
