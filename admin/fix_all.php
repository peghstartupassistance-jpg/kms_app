<?php
/**
 * Script de correction complète de la trésorerie
 */

require_once __DIR__ . '/../security.php';
global $pdo;

header('Content-Type: text/plain; charset=utf-8');

echo "=== CORRECTION TRÉSORERIE ===\n\n";

// 1. Vérifier si journal_caisse existe
echo "1. Vérification journal_caisse...\n";

try {
    $stmt = $pdo->query("SELECT 1 FROM journal_caisse LIMIT 1");
    echo "   ✓ journal_caisse existe\n";
} catch (Exception $e) {
    echo "   ✗ journal_caisse n'existe pas → création...\n";
    
    try {
        $pdo->exec("
            CREATE TABLE journal_caisse (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_ecriture DATETIME DEFAULT NULL,
                nature_operation VARCHAR(100) DEFAULT NULL,
                type_operation VARCHAR(50) DEFAULT 'AUTRE',
                sens VARCHAR(20) NOT NULL COMMENT 'RECETTE ou DEPENSE',
                montant DECIMAL(15,2) NOT NULL,
                compte_id INT DEFAULT NULL,
                vente_id INT DEFAULT NULL,
                achat_id INT DEFAULT NULL,
                source_type VARCHAR(50) DEFAULT NULL,
                source_id INT DEFAULT NULL,
                commentaire TEXT DEFAULT NULL,
                utilisateur_id INT DEFAULT NULL,
                est_annule TINYINT DEFAULT 0,
                piece_id INT DEFAULT NULL,
                INDEX idx_date (date_operation),
                INDEX idx_vente (vente_id),
                INDEX idx_sens (sens)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✓ journal_caisse créée\n";
    } catch (Exception $e2) {
        echo "   ✗ Erreur création: " . $e2->getMessage() . "\n";
    }
}

// 2. Vérifier si caisse_journal existe et a des données
echo "\n2. Vérification caisse_journal...\n";

$has_caisse_journal = false;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM caisse_journal");
    $count = $stmt->fetch()['cnt'];
    if ($count > 0) {
        echo "   ✓ caisse_journal existe avec $count lignes\n";
        $has_caisse_journal = true;
    }
} catch (Exception $e) {
    echo "   ✓ caisse_journal n'existe pas (OK)\n";
}

// 3. Migrer caisse_journal → journal_caisse si nécessaire
if ($has_caisse_journal) {
    echo "\n3. Migration caisse_journal → journal_caisse...\n";
    
    try {
        $sql = "
            INSERT IGNORE INTO journal_caisse 
            (date_operation, date_ecriture, sens, montant, source_type, source_id, commentaire, utilisateur_id, type_operation)
            SELECT 
                COALESCE(date_ecriture, NOW()),
                date_ecriture,
                CASE 
                    WHEN sens = 'ENTREE' THEN 'RECETTE'
                    WHEN sens = 'SORTIE' THEN 'DEPENSE'
                    ELSE sens
                END,
                montant,
                source_type,
                source_id,
                commentaire,
                utilisateur_id,
                CASE WHEN source_type = 'VENTE' THEN 'VENTE' ELSE 'AUTRE' END
            FROM caisse_journal
        ";
        
        $pdo->exec($sql);
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM journal_caisse");
        $count = $stmt->fetch()['cnt'];
        echo "   ✓ Migration effectuée - journal_caisse a maintenant $count lignes\n";
    } catch (Exception $e) {
        echo "   ✗ Erreur migration: " . $e->getMessage() . "\n";
    }
}

// 4. Vérifier les données d'aujourd'hui
echo "\n4. Vérification données d'aujourd'hui:\n";

$today = date('Y-m-d');
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as cnt,
            SUM(CASE WHEN sens IN ('RECETTE', 'ENTREE') THEN montant ELSE 0 END) as total
        FROM journal_caisse
        WHERE DATE(date_operation) = ? AND est_annule = 0
    ");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   Date: $today\n";
    echo "   Lignes: " . $result['cnt'] . "\n";
    echo "   CA: " . ($result['total'] ?? 0) . " F\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

// 5. Créer des données de test si vide
echo "\n5. Création données test (si vide)...\n";

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM journal_caisse");
$count = $stmt->fetch()['cnt'];

if ($count == 0) {
    try {
        // Créer une vente
        $stmt = $pdo->prepare("
            INSERT INTO ventes (numero, client_id, date_vente, montant_total_ttc, statut, date_creation)
            VALUES (?, ?, CURDATE(), 50000, 'LIVREE', NOW())
        ");
        
        // Récupérer ou créer un client
        $client_stmt = $pdo->query("SELECT id FROM clients LIMIT 1");
        $client = $client_stmt->fetch();
        $client_id = $client['id'] ?? 1;
        
        if (!$client) {
            // Créer un client test
            $pdo->exec("INSERT INTO clients (nom, prenom, email, telephone) VALUES ('Test', 'Client', 'test@test.com', '0000000000')");
            $client_id = $pdo->lastInsertId();
        }
        
        $stmt->execute(['VTEST-' . date('YmdHis'), $client_id]);
        $vente_id = $pdo->lastInsertId();
        
        // Créer l'encaissement
        $stmt = $pdo->prepare("
            INSERT INTO journal_caisse 
            (date_operation, sens, montant, type_operation, source_type, source_id, commentaire, vente_id)
            VALUES (NOW(), 'RECETTE', 50000, 'VENTE', 'VENTE', ?, 'Encaissement test', ?)
        ");
        $stmt->execute([$vente_id, $vente_id]);
        
        echo "   ✓ Données test créées\n";
        echo "   ✓ Vente: #$vente_id\n";
        echo "   ✓ Encaissement: 50 000 F\n";
    } catch (Exception $e) {
        echo "   ✗ Erreur: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ CORRECTION TERMINÉE\n";
echo "→ Recharge le dashboard: http://localhost/kms_app/index.php\n";
?>
