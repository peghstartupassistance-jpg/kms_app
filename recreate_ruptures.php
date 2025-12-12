<?php
require_once __DIR__ . '/db/db.php';

try {
    // Supprimer les contraintes qui pourraient bloquer
    echo "Suppression des contraintes existantes...\n";
    
    $constraints = [
        'fk_ruptures_produit',
        'fk_ruptures_magasinier'
    ];
    
    foreach ($constraints as $constraint) {
        try {
            $pdo->exec("ALTER TABLE ruptures_signalees DROP FOREIGN KEY $constraint");
            echo "  ✓ Contrainte $constraint supprimée\n";
        } catch (PDOException $e) {
            // Ignorer si n'existe pas
        }
    }
    
    // Tenter de supprimer la table si elle existe (partiellement)
    try {
        $pdo->exec("DROP TABLE IF EXISTS ruptures_signalees");
        echo "  ✓ Table ruptures_signalees supprimée\n";
    } catch (PDOException $e) {
        echo "  ⚠ " . $e->getMessage() . "\n";
    }
    
    // Recréer proprement
    echo "\nCréation de la table ruptures_signalees...\n";
    
    $sql = "CREATE TABLE `ruptures_signalees` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date_signalement` DATE NOT NULL,
  `produit_id` INT(10) UNSIGNED NOT NULL,
  `seuil_alerte` DECIMAL(15,3) NOT NULL,
  `stock_actuel` DECIMAL(15,3) NOT NULL,
  `impact_commercial` TEXT DEFAULT NULL COMMENT 'Ventes perdues, clients mécontents, etc.',
  `action_proposee` TEXT DEFAULT NULL COMMENT 'Réappro urgent, promotion, produit alternatif',
  `magasinier_id` INT(10) UNSIGNED NOT NULL,
  `statut_traitement` ENUM('SIGNALE','EN_COURS','RESOLU','ABANDONNE') DEFAULT 'SIGNALE',
  `date_resolution` DATETIME DEFAULT NULL,
  `commentaire_resolution` TEXT DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_ruptures_date` (`date_signalement`),
  INDEX `idx_ruptures_produit` (`produit_id`),
  INDEX `idx_ruptures_statut` (`statut_traitement`),

  CONSTRAINT `fk_ruptures_sig_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ruptures_sig_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertes ruptures stock (magasin → marketing)';";

    $pdo->exec($sql);
    echo "✅ Table créée avec succès!\n";
    
    // Vérification
    $stmt = $pdo->query("DESCRIBE ruptures_signalees");
    echo "\n=== STRUCTURE CRÉÉE ===\n";
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ✓ {$col['Field']}\n";
    }
    
    echo "\n✅ Table ruptures_signalees opérationnelle!\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
