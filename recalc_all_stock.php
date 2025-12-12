<?php
// recalc_all_stock.php
// Recalcule produits.stock_actuel à partir de stocks_mouvements
require_once __DIR__ . '/db/db.php';

echo "<h2>Recalcule global : produits.stock_actuel depuis stocks_mouvements</h2>";

try {
    $pdo->beginTransaction();

    // Récupérer toutes les quantités calculées depuis stocks_mouvements
    $sql = "
        SELECT produit_id, COALESCE(SUM(
            CASE
                WHEN type_mouvement = 'ENTREE' THEN quantite
                WHEN type_mouvement = 'SORTIE' THEN -quantite
                WHEN type_mouvement = 'AJUSTEMENT' THEN quantite
                ELSE 0
            END
        ),0) AS qte
        FROM stocks_mouvements
        GROUP BY produit_id
    ";
    $stmt = $pdo->query($sql);
    $calc = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $calc[(int)$row['produit_id']] = (int)$row['qte'];
    }

    // Récupérer tous les produits
    $stmt = $pdo->query("SELECT id, code_produit, stock_actuel FROM produits ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $diffs = [];
    foreach ($rows as $p) {
        $id = (int)$p['id'];
        $old = (int)$p['stock_actuel'];
        $new = $calc[$id] ?? 0;
        if ($old !== $new) {
            $u = $pdo->prepare("UPDATE produits SET stock_actuel = :stock WHERE id = :id");
            $u->execute(['stock' => $new, 'id' => $id]);
            $updated++;
            $diffs[] = [
                'id' => $id,
                'code' => $p['code_produit'],
                'old' => $old,
                'new' => $new,
            ];
        }
    }

    $pdo->commit();

    echo "<p>Mise à jour effectuée : <strong>$updated</strong> produits modifiés.</p>";

    if ($updated > 0) {
        echo "<h3>Détails des changements</h3>";
        echo "<table border=1 cellpadding=6><tr><th>ID</th><th>Code</th><th>Ancien</th><th>Nouveau</th></tr>";
        foreach ($diffs as $d) {
            echo "<tr><td>{$d['id']}</td><td>" . htmlspecialchars($d['code']) . "</td><td>{$d['old']}</td><td>{$d['new']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Aucune différence détectée — tout est déjà cohérent.</p>";
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<p style='color:red;'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p>Après vérification, supprime ou protège ce script.</p>";
?>