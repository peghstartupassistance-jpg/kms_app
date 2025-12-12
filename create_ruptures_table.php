<?php
require_once __DIR__ . '/db/db.php';

$sql = "CREATE TABLE IF NOT EXISTS `ruptures_signalees` (
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

  CONSTRAINT `fk_ruptures_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ruptures_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertes ruptures stock (magasin → marketing)';";

try {
    $pdo->exec($sql);
    echo "✅ Table ruptures_signalees créée avec succès!\n";
    
    // Vérification
    $stmt = $pdo->query("DESCRIBE ruptures_signalees");
    echo "\nStructure créée:\n";
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ✓ {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
