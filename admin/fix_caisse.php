<?php
/**
 * Script de vérification et migration des données de caisse
 */

require_once __DIR__ . '/../security.php';
global $pdo;

echo "=== VÉRIFICATION ÉTAT DE LA BASE ===\n\n";

// 1. Vérifier quelles tables existent
$tables_exist = [];
foreach (['journal_caisse', 'caisse_journal'] as $table) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        $tables_exist[$table] = true;
        echo "✓ Table $table existe\n";
    } catch (Exception $e) {
        $tables_exist[$table] = false;
        echo "✗ Table $table n'existe pas\n";
    }
}

echo "\n";

// 2. Compter les données
foreach ($tables_exist as $table => $exists) {
    if ($exists) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $stmt->fetch()['cnt'];
            echo "→ $table: $count lignes\n";
        } catch (Exception $e) {
            echo "✗ Erreur $table: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== MIGRATION ===\n\n";

// 3. Si caisse_journal existe et a des données, les migrer
if ($tables_exist['caisse_journal']) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM caisse_journal");
        $count = $stmt->fetch()['cnt'];
        
        if ($count > 0) {
            echo "Migration de $count lignes de caisse_journal → journal_caisse...\n\n";
            
            // Vérifier que journal_caisse existe, sinon la créer
            if (!$tables_exist['journal_caisse']) {
                echo "Création de journal_caisse...\n";
                $pdo->exec("
                    CREATE TABLE journal_caisse (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        date_operation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        date_ecriture DATETIME DEFAULT NULL,
                        nature_operation VARCHAR(100) DEFAULT NULL,
                        type_operation ENUM('VENTE','ACHAT','ENCAISSEMENT','DECAISSEMENT','AUTRE') DEFAULT 'AUTRE',
                        sens ENUM('RECETTE','DEPENSE','ENTREE','SORTIE') NOT NULL,
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
                        INDEX idx_date_operation (date_operation),
                        INDEX idx_vente_id (vente_id),
                        INDEX idx_achat_id (achat_id),
                        INDEX idx_sens (sens)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                echo "✓ journal_caisse créée\n";
            }
            
            // Migrer les données
            $sql = "
                INSERT IGNORE INTO journal_caisse 
                (date_operation, date_ecriture, sens, montant, source_type, source_id, commentaire, utilisateur_id, type_operation)
                SELECT 
                    COALESCE(cj.date_ecriture, NOW()) as date_operation,
                    cj.date_ecriture,
                    CASE 
                        WHEN cj.sens = 'ENTREE' THEN 'RECETTE'
                        WHEN cj.sens = 'SORTIE' THEN 'DEPENSE'
                        ELSE cj.sens
                    END AS sens,
                    cj.montant,
                    cj.source_type,
                    cj.source_id,
                    cj.commentaire,
                    cj.utilisateur_id,
                    CASE
                        WHEN cj.source_type = 'VENTE' THEN 'VENTE'
                        WHEN cj.source_type = 'ACHAT' THEN 'ACHAT'
                        ELSE 'AUTRE'
                    END
                FROM caisse_journal cj
                WHERE NOT EXISTS (
                    SELECT 1 FROM journal_caisse jc 
                    WHERE jc.source_type = cj.source_type AND jc.source_id = cj.source_id
                )
            ";
            
            $pdo->exec($sql);
            echo "✓ Migration effectuée\n";
            
            // Vérifier le résultat
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM journal_caisse");
            $new_count = $stmt->fetch()['cnt'];
            echo "✓ journal_caisse contient maintenant $new_count lignes\n";
        }
    } catch (Exception $e) {
        echo "✗ Erreur migration: " . $e->getMessage() . "\n";
    }
}

echo "\n=== VÉRIFICATION FINALE ===\n\n";

// 4. Vérifier CA aujourd'hui
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        SELECT 
            SUM(CASE WHEN sens IN ("RECETTE", "ENTREE") THEN montant ELSE 0 END) as total,
            COUNT(*) as count
        FROM journal_caisse 
        WHERE DATE(date_operation) = ? 
          AND est_annule = 0
    ');
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "CA AUJOURD'HUI ($today):\n";
    echo "  Écritures: " . ($result['count'] ?? 0) . "\n";
    echo "  Total: " . ($result['total'] ?? 0) . " F\n";
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n✓ Migration terminée!\n";
?>
