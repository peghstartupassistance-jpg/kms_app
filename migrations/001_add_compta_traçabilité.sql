-- Migration : Ajouter traçabilité et libellé aux pièces comptables
-- Exécutez ce script pour ajouter les colonnes manquantes

ALTER TABLE `compta_pieces`
ADD COLUMN `libelle` varchar(255) DEFAULT NULL AFTER `numero_piece`,
ADD COLUMN `utilisateur_id` int(10) UNSIGNED DEFAULT NULL AFTER `observations`,
ADD COLUMN `validee_par_id` int(10) UNSIGNED DEFAULT NULL AFTER `est_validee`,
ADD COLUMN `date_validation` datetime DEFAULT NULL AFTER `validee_par_id`;

-- Ajouter des indices pour performance (sans UNIQUE pour éviter les doublons)
ALTER TABLE `compta_pieces`
ADD KEY `idx_validee_par` (`validee_par_id`),
ADD KEY `idx_date_validation` (`date_validation`),
ADD KEY `idx_numero_piece` (`numero_piece`),
ADD KEY `idx_utilisateur` (`utilisateur_id`);

-- Ajouter des contraintes de clés étrangères
ALTER TABLE `compta_pieces`
ADD CONSTRAINT `fk_compta_pieces_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_compta_pieces_validee_par` FOREIGN KEY (`validee_par_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

-- ⚠️ NOTE: Les doublons numero_piece existent. À nettoyer manuellement:
-- SELECT numero_piece, COUNT(*) FROM compta_pieces GROUP BY numero_piece HAVING COUNT(*) > 1;

-- Vérification : voir le schéma mis à jour
DESCRIBE `compta_pieces`;
