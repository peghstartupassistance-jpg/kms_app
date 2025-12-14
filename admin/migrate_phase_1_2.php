<?php
/**
 * Phase 1.2 - Caisse Schema Unification Migration
 * 
 * Strategy: Consolidate to journal_caisse (standard accounting table)
 * - Migrate caisse_journal data to journal_caisse
 * - Ensure all functions write to journal_caisse
 * - Update all reads to journal_caisse
 * - Drop caisse_journal after migration
 * 
 * This script must be run ONCE to unify the schema.
 */

require_once __DIR__ . '/security.php';
exigerConnexion();
exigerPermission('ADMIN_SYSTEME');

global $pdo;

$migration_steps = [];

try {
    // STEP 1: Ensure journal_caisse has all necessary columns
    $migration_steps[] = [
        'name' => 'Ensure journal_caisse structure',
        'status' => 'in_progress'
    ];

    $sql_ensure = "
        CREATE TABLE IF NOT EXISTS journal_caisse (
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
            INDEX idx_sens (sens),
            CONSTRAINT fk_journal_caisse_ventes FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($sql_ensure);
    $migration_steps[count($migration_steps)-1]['status'] = 'completed';

    // STEP 2: Add missing columns if they don't exist
    $migration_steps[] = [
        'name' => 'Add missing columns to journal_caisse',
        'status' => 'in_progress'
    ];

    $columns_to_add = [
        "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS date_ecriture DATETIME DEFAULT NULL",
        "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS vente_id INT DEFAULT NULL",
        "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS achat_id INT DEFAULT NULL",
        "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS est_annule TINYINT DEFAULT 0",
        "ALTER TABLE journal_caisse ADD COLUMN IF NOT EXISTS piece_id INT DEFAULT NULL",
    ];

    foreach ($columns_to_add as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Column may already exist
            error_log('[MIGRATION 1.2] ' . $e->getMessage());
        }
    }
    $migration_steps[count($migration_steps)-1]['status'] = 'completed';

    // STEP 3: Migrate caisse_journal data to journal_caisse (if caisse_journal exists)
    $migration_steps[] = [
        'name' => 'Migrate caisse_journal → journal_caisse',
        'status' => 'in_progress'
    ];

    try {
        $stmt = $pdo->query("DESCRIBE caisse_journal");
        $stmt->execute();
        
        // caisse_journal exists, so migrate
        $sql_migrate = "
            INSERT IGNORE INTO journal_caisse 
            (date_operation, date_ecriture, sens, montant, source_type, source_id, commentaire, utilisateur_id, type_operation)
            SELECT 
                COALESCE(date_ecriture, NOW()),
                date_ecriture,
                CASE 
                    WHEN sens = 'ENTREE' THEN 'RECETTE'
                    WHEN sens = 'SORTIE' THEN 'DEPENSE'
                    ELSE sens
                END AS sens,
                montant,
                source_type,
                source_id,
                commentaire,
                utilisateur_id,
                'AUTRE'
            FROM caisse_journal
            WHERE id NOT IN (
                SELECT source_id FROM journal_caisse WHERE source_type = 'caisse_journal'
            )
        ";
        
        $pdo->exec($sql_migrate);
        $migration_steps[count($migration_steps)-1]['status'] = 'completed';
        $migration_steps[count($migration_steps)-1]['message'] = 'Data migrated successfully';
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Table 'kms_gestion.caisse_journal' doesn't exist") !== false) {
            $migration_steps[count($migration_steps)-1]['status'] = 'skipped';
            $migration_steps[count($migration_steps)-1]['message'] = 'caisse_journal does not exist (already migrated?)';
        } else {
            throw $e;
        }
    }

    // STEP 4: Backup and drop caisse_journal
    $migration_steps[] = [
        'name' => 'Backup and drop caisse_journal',
        'status' => 'in_progress'
    ];

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS caisse_journal_backup AS SELECT * FROM caisse_journal");
        $pdo->exec("DROP TABLE IF EXISTS caisse_journal");
        $migration_steps[count($migration_steps)-1]['status'] = 'completed';
    } catch (Exception $e) {
        $migration_steps[count($migration_steps)-1]['status'] = 'skipped';
        $migration_steps[count($migration_steps)-1]['message'] = 'caisse_journal already dropped';
    }

    // STEP 5: Update lib/caisse.php to write to journal_caisse
    $migration_steps[] = [
        'name' => 'Note: lib/caisse.php must be updated to use journal_caisse',
        'status' => 'pending_code_update',
        'details' => 'Run Phase 1.2.2 to update function calls'
    ];

} catch (Exception $e) {
    $migration_steps[count($migration_steps)-1]['status'] = 'failed';
    $migration_steps[count($migration_steps)-1]['error'] = $e->getMessage();
}

// Output results
header('Content-Type: application/json');
echo json_encode([
    'overall_status' => (count(array_filter($migration_steps, fn($s) => $s['status'] === 'failed')) === 0) ? 'SUCCESS' : 'PARTIAL',
    'steps' => $migration_steps,
    'next_steps' => [
        '1. Review migration results above',
        '2. Run Phase 1.2.2 to update lib/caisse.php',
        '3. Update all read queries to use journal_caisse instead of caisse_journal',
        '4. Run health check to verify consistency'
    ]
], JSON_PRETTY_PRINT);
