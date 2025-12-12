<?php
require __DIR__ . '/db/db.php';

echo "📋 Création du schéma comptable\n";
echo "==============================\n\n";

$queries = [
    // Tables
    "CREATE TABLE IF NOT EXISTS compta_exercices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        annee INT NOT NULL UNIQUE,
        date_ouverture DATE NOT NULL,
        date_cloture DATE,
        est_clos TINYINT(1) DEFAULT 0,
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS compta_journaux (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(10) NOT NULL UNIQUE,
        libelle VARCHAR(100) NOT NULL,
        type ENUM('VENTE', 'ACHAT', 'TRESORERIE', 'OPERATION_DIVERSE', 'PAIE') DEFAULT 'OPERATION_DIVERSE',
        compte_contre_partie INT UNSIGNED,
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS compta_comptes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        numero_compte VARCHAR(20) NOT NULL UNIQUE,
        libelle VARCHAR(150) NOT NULL,
        classe CHAR(1) NOT NULL,
        est_analytique TINYINT(1) DEFAULT 0,
        compte_parent_id INT UNSIGNED,
        type_compte ENUM('ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT') DEFAULT 'ACTIF',
        nature ENUM('CREANCE', 'DETTE', 'STOCK', 'IMMOBILISATION', 'TRESORERIE', 'VENTE', 'CHARGE_VARIABLE', 'CHARGE_FIXE', 'AUTRE') DEFAULT 'AUTRE',
        est_actif TINYINT(1) DEFAULT 1,
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (compte_parent_id) REFERENCES compta_comptes(id),
        KEY idx_numero (numero_compte),
        KEY idx_classe (classe),
        KEY idx_nature (nature)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "ALTER TABLE compta_journaux ADD FOREIGN KEY (compte_contre_partie) REFERENCES compta_comptes(id)",
    
    "CREATE TABLE IF NOT EXISTS compta_pieces (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        exercice_id INT UNSIGNED NOT NULL,
        journal_id INT UNSIGNED NOT NULL,
        numero_piece VARCHAR(50) NOT NULL,
        date_piece DATE NOT NULL,
        reference_type VARCHAR(50),
        reference_id INT UNSIGNED,
        tiers_client_id INT UNSIGNED,
        tiers_fournisseur_id INT UNSIGNED,
        observations TEXT,
        est_validee TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (exercice_id) REFERENCES compta_exercices(id),
        FOREIGN KEY (journal_id) REFERENCES compta_journaux(id),
        FOREIGN KEY (tiers_client_id) REFERENCES clients(id),
        FOREIGN KEY (tiers_fournisseur_id) REFERENCES fournisseurs(id),
        UNIQUE KEY uk_piece (exercice_id, journal_id, numero_piece),
        KEY idx_date (date_piece),
        KEY idx_ref (reference_type, reference_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS compta_ecritures (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        piece_id INT UNSIGNED NOT NULL,
        compte_id INT UNSIGNED NOT NULL,
        libelle_ecriture VARCHAR(200),
        debit DECIMAL(15, 2) DEFAULT 0,
        credit DECIMAL(15, 2) DEFAULT 0,
        tiers_client_id INT UNSIGNED,
        tiers_fournisseur_id INT UNSIGNED,
        centre_analytique_id INT UNSIGNED,
        ordre_ligne INT,
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (piece_id) REFERENCES compta_pieces(id) ON DELETE CASCADE,
        FOREIGN KEY (compte_id) REFERENCES compta_comptes(id),
        FOREIGN KEY (tiers_client_id) REFERENCES clients(id),
        FOREIGN KEY (tiers_fournisseur_id) REFERENCES fournisseurs(id),
        KEY idx_compte (compte_id),
        KEY idx_piece (piece_id),
        KEY idx_debit_credit (debit, credit)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS compta_mapping_operations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_type VARCHAR(50) NOT NULL,
        code_operation VARCHAR(50) NOT NULL,
        journal_id INT UNSIGNED NOT NULL,
        compte_debit_id INT UNSIGNED,
        compte_credit_id INT UNSIGNED,
        description TEXT,
        actif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (journal_id) REFERENCES compta_journaux(id),
        FOREIGN KEY (compte_debit_id) REFERENCES compta_comptes(id),
        FOREIGN KEY (compte_credit_id) REFERENCES compta_comptes(id),
        UNIQUE KEY uk_mapping (source_type, code_operation),
        KEY idx_source (source_type, code_operation)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS compta_operations_trace (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_type VARCHAR(50) NOT NULL,
        source_id INT UNSIGNED NOT NULL,
        piece_id INT UNSIGNED,
        status ENUM('success', 'error', 'en_attente') DEFAULT 'en_attente',
        messages TEXT,
        executed_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (piece_id) REFERENCES compta_pieces(id),
        UNIQUE KEY uk_trace (source_type, source_id),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // ALTER TABLE journal_caisse - avec gestion d'erreur
    "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS client_id INT UNSIGNED AFTER nature_operation",
    "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS fournisseur_id INT UNSIGNED AFTER client_id",
    
    // INSERT DATA
    "INSERT INTO compta_exercices (annee, date_ouverture, est_clos, observations) VALUES (2024, '2024-01-01', 0, 'Exercice 2024'), (2025, '2025-01-01', 0, 'Exercice 2025') ON DUPLICATE KEY UPDATE annee=annee",
    
    "INSERT INTO compta_journaux (code, libelle, type) VALUES ('VE', 'Ventes', 'VENTE'), ('AC', 'Achats', 'ACHAT'), ('TR', 'Tresorerie', 'TRESORERIE'), ('OD', 'Operations Diverses', 'OPERATION_DIVERSE'), ('PA', 'Paie', 'PAIE') ON DUPLICATE KEY UPDATE code=code",
    
    "INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, nature) VALUES ('1', 'Immobilisations', '1', 'ACTIF', 'IMMOBILISATION'), ('2', 'Stocks', '2', 'ACTIF', 'STOCK'), ('3', 'Tiers', '3', 'PASSIF', 'AUTRE'), ('4', 'Capitaux', '4', 'PASSIF', 'AUTRE'), ('5', 'Resultats', '5', 'PASSIF', 'AUTRE'), ('6', 'Charges', '6', 'CHARGE', 'CHARGE_VARIABLE'), ('7', 'Produits', '7', 'PRODUIT', 'VENTE'), ('8', 'Speciaux', '8', 'ACTIF', 'AUTRE') ON DUPLICATE KEY UPDATE numero_compte=numero_compte"
];

$success = 0;
$error = 0;

foreach ($queries as $i => $query) {
    try {
        $pdo->exec($query);
        $success++;
        $type = '';
        if (stripos($query, 'CREATE TABLE') === 0) {
            preg_match('/CREATE TABLE.*?(\w+)\s*\(/', $query, $m);
            $type = ' [' . ($m[1] ?? 'table') . ']';
        } elseif (stripos($query, 'ALTER TABLE') === 0) {
            preg_match('/ALTER TABLE\s+(\w+)/', $query, $m);
            $type = ' [' . ($m[1] ?? 'alter') . ']';
        } elseif (stripos($query, 'INSERT') === 0) {
            preg_match('/INSERT INTO\s+(\w+)/', $query, $m);
            $type = ' [' . ($m[1] ?? 'insert') . ']';
        }
        echo "✓ Requête " . ($i+1) . " OK$type\n";
    } catch (Exception $e) {
        $error++;
        echo "✗ Requête " . ($i+1) . " ERREUR: " . $e->getMessage() . "\n";
    }
}

echo "\n==============================\n";
echo "✓ Succès : $success\n";
echo "✗ Erreurs : $error\n";

// Vérifier les tables créées
try {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='kms_gestion' AND TABLE_NAME LIKE 'compta_%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $table_count = $result['nb'] ?? 0;
    
    echo "\n📊 Tables comptables créées : $table_count\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'compta_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}

echo "\n✅ Migration terminée !\n";
?>
