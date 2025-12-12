-- Script SQL complémentaire - Tables manquantes uniquement
-- À exécuter si les tables n'existent pas déjà

USE kms_gestion;

-- Table relances_devis (si n'existe pas)
CREATE TABLE IF NOT EXISTS `relances_devis` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `devis_id` INT(10) UNSIGNED NOT NULL,
  `date_relance` DATE NOT NULL,
  `type_relance` ENUM('TELEPHONE','EMAIL','SMS','WHATSAPP','VISITE') NOT NULL DEFAULT 'TELEPHONE',
  `utilisateur_id` INT(10) UNSIGNED NOT NULL,
  `commentaires` TEXT DEFAULT NULL,
  `prochaine_action` VARCHAR(255) DEFAULT NULL,
  `date_prochaine_action` DATE DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_relances_devis` (`devis_id`),
  INDEX `idx_relances_date` (`date_relance`),
  
  CONSTRAINT `fk_relances_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_relances_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table conversions_pipeline
CREATE TABLE IF NOT EXISTS `conversions_pipeline` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `source_type` ENUM('SHOWROOM','TERRAIN','DIGITAL') NOT NULL,
  `source_id` INT(10) UNSIGNED NOT NULL COMMENT 'ID visiteur/prospection/lead',
  `client_id` INT(10) UNSIGNED NOT NULL,
  `date_conversion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `canal_vente_id` INT(10) UNSIGNED DEFAULT NULL,
  `devis_id` INT(10) UNSIGNED DEFAULT NULL,
  `vente_id` INT(10) UNSIGNED DEFAULT NULL,
  
  INDEX `idx_conversions_source` (`source_type`, `source_id`),
  INDEX `idx_conversions_client` (`client_id`),
  INDEX `idx_conversions_date` (`date_conversion`),
  
  CONSTRAINT `fk_conversions_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conversions_canal` FOREIGN KEY (`canal_vente_id`) REFERENCES `canaux_vente`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_conversions_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_conversions_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table objectifs_commerciaux
CREATE TABLE IF NOT EXISTS `objectifs_commerciaux` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `annee` INT NOT NULL,
  `mois` INT DEFAULT NULL COMMENT 'NULL = objectif annuel',
  `canal` ENUM('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION','GLOBAL') NOT NULL DEFAULT 'GLOBAL',
  `objectif_ca` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `objectif_nb_ventes` INT DEFAULT NULL,
  `objectif_nb_leads` INT DEFAULT NULL,
  `realise_ca` DECIMAL(15,2) DEFAULT 0.00,
  `realise_nb_ventes` INT DEFAULT 0,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  UNIQUE KEY `idx_objectifs_unique` (`annee`, `mois`, `canal`),
  INDEX `idx_objectifs_periode` (`annee`, `mois`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table kpis_quotidiens
CREATE TABLE IF NOT EXISTS `kpis_quotidiens` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL,
  `canal` ENUM('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION','GLOBAL') NOT NULL,
  `nb_visiteurs` INT DEFAULT 0,
  `nb_leads` INT DEFAULT 0,
  `nb_devis` INT DEFAULT 0,
  `nb_ventes` INT DEFAULT 0,
  `ca_realise` DECIMAL(15,2) DEFAULT 0.00,
  `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY `idx_kpis_unique` (`date`, `canal`),
  INDEX `idx_kpis_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vues
CREATE OR REPLACE VIEW `v_pipeline_commercial` AS
SELECT 
    'SHOWROOM' as canal,
    vs.id as source_id,
    vs.client_nom as prospect_nom,
    vs.date_visite as date_entree,
    0 as converti_en_devis,
    0 as converti_en_vente,
    NULL as statut_pipeline
FROM visiteurs_showroom vs
UNION ALL
SELECT 
    'TERRAIN' as canal,
    pt.id as source_id,
    pt.prospect_nom as prospect_nom,
    pt.date_prospection as date_entree,
    0 as converti_en_devis,
    0 as converti_en_vente,
    NULL as statut_pipeline
FROM prospections_terrain pt
UNION ALL
SELECT 
    'DIGITAL' as canal,
    ld.id as source_id,
    ld.nom_prospect as prospect_nom,
    ld.date_lead as date_entree,
    (ld.statut IN ('DEVIS_ENVOYE', 'CONVERTI')) as converti_en_devis,
    (ld.statut = 'CONVERTI') as converti_en_vente,
    ld.statut as statut_pipeline
FROM leads_digital ld;

CREATE OR REPLACE VIEW `v_ventes_livraison_encaissement` AS
SELECT 
    v.id,
    v.numero,
    v.date_vente,
    v.montant_total_ttc,
    v.statut as statut_vente,
    CASE 
        WHEN EXISTS(SELECT 1 FROM bons_livraison bl WHERE bl.vente_id = v.id AND bl.signe_client = 1) 
        THEN 'LIVRE' 
        ELSE 'NON_LIVRE' 
    END as statut_livraison,
    COALESCE((SELECT SUM(montant) FROM journal_caisse jc WHERE jc.vente_id = v.id), 0) as montant_encaisse,
    (v.montant_total_ttc - COALESCE((SELECT SUM(montant) FROM journal_caisse jc WHERE jc.vente_id = v.id), 0)) as solde_du
FROM ventes v;
