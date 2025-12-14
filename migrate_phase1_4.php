<?php
/**
 * Migration Phase 1.4 - Création table caisses_clotures
 * Pour stocker les clôtures quotidiennes de caisse
 */

require_once __DIR__ . '/security.php';
global $pdo;

echo "=== MIGRATION PHASE 1.4 - CAISSES CLOTURES ===\n\n";

try {
    // Vérifier si la table existe déjà
    $stmt = $pdo->query("SHOW TABLES LIKE 'caisses_clotures'");
    if ($stmt->fetch()) {
        echo "⚠️ Table caisses_clotures existe déjà.\n";
    } else {
        // Créer la table
        $sql = "
            CREATE TABLE caisses_clotures (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                date_cloture DATE NOT NULL,
                
                -- Montants calculés
                total_recettes DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_depenses DECIMAL(15,2) NOT NULL DEFAULT 0,
                solde_calcule DECIMAL(15,2) NOT NULL DEFAULT 0,
                
                -- Montants déclarés par le caissier
                montant_especes_declare DECIMAL(15,2) DEFAULT 0,
                montant_cheques_declare DECIMAL(15,2) DEFAULT 0,
                montant_virements_declare DECIMAL(15,2) DEFAULT 0,
                montant_mobile_declare DECIMAL(15,2) DEFAULT 0,
                total_declare DECIMAL(15,2) NOT NULL DEFAULT 0,
                
                -- Écart
                ecart DECIMAL(15,2) NOT NULL DEFAULT 0,
                justification_ecart TEXT,
                
                -- Statistiques
                nb_operations INT DEFAULT 0,
                nb_ventes INT DEFAULT 0,
                nb_annulations INT DEFAULT 0,
                
                -- Métadonnées
                statut ENUM('BROUILLON','VALIDE','ANNULE') DEFAULT 'BROUILLON',
                caissier_id INT UNSIGNED,
                validateur_id INT UNSIGNED,
                date_validation DATETIME,
                observations TEXT,
                date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                -- Contraintes
                UNIQUE KEY unique_date (date_cloture),
                INDEX idx_statut (statut),
                INDEX idx_caissier (caissier_id),
                FOREIGN KEY (caissier_id) REFERENCES utilisateurs(id),
                FOREIGN KEY (validateur_id) REFERENCES utilisateurs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        echo "✅ Table caisses_clotures créée avec succès!\n";
    }
    
    // Afficher structure
    echo "\n=== STRUCTURE TABLE ===\n";
    $stmt = $pdo->query('DESCRIBE caisses_clotures');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    echo "\n✅ Migration Phase 1.4 terminée!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
