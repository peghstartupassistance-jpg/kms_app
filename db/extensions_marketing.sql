-- =====================================================================
-- EXTENSIONS MARKETING - KMS GESTION
-- Création des tables manquantes selon le document d'organisation
-- Date: 11 décembre 2025
-- =====================================================================

USE kms_gestion;

-- =====================================================================
-- 1. MODULE DIGITAL - Gestion des leads digitaux
-- =====================================================================

CREATE TABLE IF NOT EXISTS `leads_digital` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date_lead` DATE NOT NULL,
  `nom_prospect` VARCHAR(150) NOT NULL,
  `telephone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `source` ENUM('FACEBOOK','INSTAGRAM','WHATSAPP','SITE_WEB','TIKTOK','LINKEDIN','AUTRE') NOT NULL DEFAULT 'FACEBOOK',
  `message_initial` TEXT DEFAULT NULL,
  `produit_interet` VARCHAR(255) DEFAULT NULL,
  `statut` ENUM('NOUVEAU','CONTACTE','QUALIFIE','DEVIS_ENVOYE','CONVERTI','PERDU') NOT NULL DEFAULT 'NOUVEAU',
  `score_prospect` INT DEFAULT 0 COMMENT 'Score 0-100 selon intérêt/qualité',
  `date_dernier_contact` DATETIME DEFAULT NULL,
  `prochaine_action` VARCHAR(255) DEFAULT NULL,
  `date_prochaine_action` DATE DEFAULT NULL,
  `client_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'Rempli après conversion',
  `utilisateur_responsable_id` INT(10) UNSIGNED DEFAULT NULL,
  `campagne` VARCHAR(150) DEFAULT NULL COMMENT 'Nom de la campagne publicitaire',
  `cout_acquisition` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Coût pub si applicable',
  `observations` TEXT DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_conversion` DATETIME DEFAULT NULL,
  
  INDEX `idx_leads_source` (`source`),
  INDEX `idx_leads_statut` (`statut`),
  INDEX `idx_leads_date` (`date_lead`),
  INDEX `idx_leads_prochaine_action` (`date_prochaine_action`),
  
  CONSTRAINT `fk_leads_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leads_utilisateur` FOREIGN KEY (`utilisateur_responsable_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Leads digitaux (Facebook, Instagram, WhatsApp, etc.)';

-- =====================================================================
-- 2. REGISTRES LIAISON MARKETING-MAGASIN
-- =====================================================================

-- 2.1 Ordres de préparation (demandes marketing → magasin)
CREATE TABLE IF NOT EXISTS `ordres_preparation` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `numero_ordre` VARCHAR(50) NOT NULL UNIQUE,
  `date_ordre` DATE NOT NULL,
  `vente_id` INT(10) UNSIGNED DEFAULT NULL,
  `devis_id` INT(10) UNSIGNED DEFAULT NULL,
  `client_id` INT(10) UNSIGNED NOT NULL,
  `type_commande` ENUM('VENTE_SHOWROOM','VENTE_TERRAIN','VENTE_DIGITAL','RESERVATION_HOTEL','AUTRE') DEFAULT 'VENTE_SHOWROOM',
  `commercial_responsable_id` INT(10) UNSIGNED NOT NULL,
  `statut` ENUM('EN_ATTENTE','EN_PREPARATION','PRET','LIVRE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE',
  `date_preparation_demandee` DATE DEFAULT NULL,
  `priorite` ENUM('NORMALE','URGENTE','TRES_URGENTE') DEFAULT 'NORMALE',
  `observations` TEXT DEFAULT NULL,
  `signature_resp_marketing` TINYINT(1) DEFAULT 0 COMMENT 'Validation RESP MARKETING',
  `date_signature_marketing` DATETIME DEFAULT NULL,
  `magasinier_id` INT(10) UNSIGNED DEFAULT NULL,
  `date_preparation_effectuee` DATETIME DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_ordres_date` (`date_ordre`),
  INDEX `idx_ordres_statut` (`statut`),
  INDEX `idx_ordres_commercial` (`commercial_responsable_id`),
  
  CONSTRAINT `fk_ordres_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ordres_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ordres_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ordres_commercial` FOREIGN KEY (`commercial_responsable_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ordres_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordres de préparation (liaison marketing-magasin)';

-- 2.2 Lignes des ordres de préparation
CREATE TABLE IF NOT EXISTS `ordres_preparation_lignes` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ordre_preparation_id` INT(10) UNSIGNED NOT NULL,
  `produit_id` INT(10) UNSIGNED NOT NULL,
  `quantite_demandee` DECIMAL(15,3) NOT NULL,
  `quantite_preparee` DECIMAL(15,3) DEFAULT 0.000,
  `observations` VARCHAR(255) DEFAULT NULL,
  
  CONSTRAINT `fk_ordres_lignes_ordre` FOREIGN KEY (`ordre_preparation_id`) REFERENCES `ordres_preparation`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ordres_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 Ruptures signalées (magasin → marketing)
CREATE TABLE IF NOT EXISTS `ruptures_signalees` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertes ruptures stock (magasin → marketing)';

-- 2.4 Retours et litiges clients
CREATE TABLE IF NOT EXISTS `retours_litiges` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `numero_litige` VARCHAR(50) NOT NULL UNIQUE,
  `date_retour` DATE NOT NULL,
  `client_id` INT(10) UNSIGNED NOT NULL,
  `vente_id` INT(10) UNSIGNED DEFAULT NULL,
  `produit_id` INT(10) UNSIGNED DEFAULT NULL,
  `quantite_retournee` DECIMAL(15,3) DEFAULT 1.000,
  `type_probleme` ENUM('DEFAUT_PRODUIT','LIVRAISON_NON_CONFORME','RETARD_LIVRAISON','ERREUR_COMMANDE','INSATISFACTION_CLIENT','AUTRE') NOT NULL,
  `motif_detaille` TEXT NOT NULL,
  `responsable_suivi_id` INT(10) UNSIGNED NOT NULL,
  `statut_traitement` ENUM('EN_COURS','RESOLU','REMPLACEMENT_EFFECTUE','REMBOURSEMENT_EFFECTUE','ABANDONNE') NOT NULL DEFAULT 'EN_COURS',
  `solution_apportee` TEXT DEFAULT NULL,
  `montant_rembourse` DECIMAL(15,2) DEFAULT 0.00,
  `produit_remplace` TINYINT(1) DEFAULT 0,
  `date_resolution` DATETIME DEFAULT NULL,
  `satisfaction_client_finale` INT DEFAULT NULL COMMENT 'Note 1-5 après résolution',
  `observations` TEXT DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_litiges_date` (`date_retour`),
  INDEX `idx_litiges_client` (`client_id`),
  INDEX `idx_litiges_statut` (`statut_traitement`),
  INDEX `idx_litiges_type` (`type_probleme`),
  
  CONSTRAINT `fk_litiges_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_litiges_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_litiges_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_litiges_responsable` FOREIGN KEY (`responsable_suivi_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Retours et litiges clients';

-- =====================================================================
-- 3. GESTION DES RELANCES (Devis & Prospects)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `relances_devis` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `devis_id` INT(10) UNSIGNED NOT NULL,
  `date_relance` DATE NOT NULL,
  `type_relance` ENUM('APPEL','EMAIL','WHATSAPP','SMS','VISITE') NOT NULL DEFAULT 'APPEL',
  `utilisateur_id` INT(10) UNSIGNED NOT NULL,
  `resultat` ENUM('SANS_REPONSE','CLIENT_INTERESSE','DEVIS_ACCEPTE','DEVIS_REFUSE','A_RAPPELER') DEFAULT 'SANS_REPONSE',
  `notes` TEXT DEFAULT NULL,
  `prochaine_relance` DATE DEFAULT NULL,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_relances_devis` (`devis_id`),
  INDEX `idx_relances_date` (`date_relance`),
  INDEX `idx_relances_prochaine` (`prochaine_relance`),
  
  CONSTRAINT `fk_relances_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_relances_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des relances devis';

-- =====================================================================
-- 4. TRACKING PIPELINE & CONVERSIONS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `conversions_pipeline` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT(10) UNSIGNED NOT NULL,
  `canal` ENUM('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION') NOT NULL,
  `etape_initiale` VARCHAR(50) NOT NULL COMMENT 'VISITEUR, LEAD, PROSPECT, etc.',
  `date_etape_initiale` DATETIME NOT NULL,
  `etape_actuelle` VARCHAR(50) NOT NULL,
  `date_etape_actuelle` DATETIME NOT NULL,
  `devis_id` INT(10) UNSIGNED DEFAULT NULL,
  `vente_id` INT(10) UNSIGNED DEFAULT NULL,
  `montant_devis` DECIMAL(15,2) DEFAULT 0.00,
  `montant_vente` DECIMAL(15,2) DEFAULT 0.00,
  `duree_conversion_jours` INT DEFAULT NULL COMMENT 'Nombre de jours visiteur→vente',
  `nb_relances` INT DEFAULT 0,
  `commercial_responsable_id` INT(10) UNSIGNED DEFAULT NULL,
  `observations` TEXT DEFAULT NULL,
  
  INDEX `idx_conversions_client` (`client_id`),
  INDEX `idx_conversions_canal` (`canal`),
  INDEX `idx_conversions_etape` (`etape_actuelle`),
  INDEX `idx_conversions_date` (`date_etape_actuelle`),
  
  CONSTRAINT `fk_conversions_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conversions_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_conversions_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_conversions_commercial` FOREIGN KEY (`commercial_responsable_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suivi pipeline et conversions multi-canal';

-- =====================================================================
-- 5. OBJECTIFS COMMERCIAUX
-- =====================================================================

CREATE TABLE IF NOT EXISTS `objectifs_commerciaux` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `periode` ENUM('JOURNALIER','HEBDOMADAIRE','MENSUEL','TRIMESTRIEL','ANNUEL') NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `utilisateur_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = objectif équipe/global',
  `canal` ENUM('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION','GLOBAL') DEFAULT 'GLOBAL',
  `type_objectif` ENUM('CA','NOMBRE_VENTES','NOMBRE_DEVIS','NOMBRE_PROSPECTS','TAUX_CONVERSION','TICKET_MOYEN') NOT NULL,
  `valeur_cible` DECIMAL(15,2) NOT NULL,
  `valeur_realisee` DECIMAL(15,2) DEFAULT 0.00,
  `taux_realisation` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Pourcentage atteint',
  `observations` TEXT DEFAULT NULL,
  
  INDEX `idx_objectifs_periode` (`periode`, `date_debut`),
  INDEX `idx_objectifs_utilisateur` (`utilisateur_id`),
  INDEX `idx_objectifs_canal` (`canal`),
  
  CONSTRAINT `fk_objectifs_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Objectifs commerciaux (quotidiens, hebdo, mensuels)';

-- =====================================================================
-- 6. KPIs QUOTIDIENS (Cache performances pour dashboards)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `kpis_quotidiens` (
  `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date_kpi` DATE NOT NULL,
  `canal` ENUM('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION','GLOBAL') NOT NULL,
  `nb_visiteurs` INT DEFAULT 0,
  `nb_leads` INT DEFAULT 0,
  `nb_prospects` INT DEFAULT 0,
  `nb_devis` INT DEFAULT 0,
  `nb_ventes` INT DEFAULT 0,
  `ca_ht` DECIMAL(15,2) DEFAULT 0.00,
  `ca_ttc` DECIMAL(15,2) DEFAULT 0.00,
  `ticket_moyen` DECIMAL(15,2) DEFAULT 0.00,
  `taux_conversion_visite_devis` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'En %',
  `taux_conversion_devis_vente` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'En %',
  `nb_relances_effectuees` INT DEFAULT 0,
  `nb_livraisons` INT DEFAULT 0,
  `nb_litiges` INT DEFAULT 0,
  `satisfaction_moyenne` DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Note moyenne 1-5',
  `date_calcul` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  UNIQUE KEY `unique_date_canal` (`date_kpi`, `canal`),
  INDEX `idx_kpis_date` (`date_kpi`),
  INDEX `idx_kpis_canal` (`canal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='KPIs quotidiens consolidés (cache dashboards)';

-- =====================================================================
-- 7. AMÉLIORATIONS TABLES EXISTANTES
-- =====================================================================

-- Ajouter champs manquants à visiteurs_showroom si nécessaire
ALTER TABLE `visiteurs_showroom`
  ADD COLUMN IF NOT EXISTS `source_visite` VARCHAR(100) DEFAULT NULL COMMENT 'Publicité, bouche-à-oreille, réseaux sociaux, etc.' AFTER `orientation`,
  ADD COLUMN IF NOT EXISTS `converti_en_devis` TINYINT(1) DEFAULT 0 AFTER `source_visite`,
  ADD COLUMN IF NOT EXISTS `devis_id` INT(10) UNSIGNED DEFAULT NULL AFTER `converti_en_devis`,
  ADD COLUMN IF NOT EXISTS `converti_en_vente` TINYINT(1) DEFAULT 0 AFTER `devis_id`,
  ADD COLUMN IF NOT EXISTS `vente_id` INT(10) UNSIGNED DEFAULT NULL AFTER `converti_en_vente`;

-- Ajouter contraintes FK si colonnes ajoutées
-- ALTER TABLE `visiteurs_showroom` ADD CONSTRAINT `fk_visiteurs_showroom_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `visiteurs_showroom` ADD CONSTRAINT `fk_visiteurs_showroom_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes`(`id`) ON DELETE SET NULL;

-- Ajouter champs manquants à prospections_terrain
ALTER TABLE `prospections_terrain`
  ADD COLUMN IF NOT EXISTS `score_prospect` INT DEFAULT 0 COMMENT 'Score 0-100' AFTER `prochaine_etape`,
  ADD COLUMN IF NOT EXISTS `converti_en_devis` TINYINT(1) DEFAULT 0 AFTER `score_prospect`,
  ADD COLUMN IF NOT EXISTS `devis_id` INT(10) UNSIGNED DEFAULT NULL AFTER `converti_en_devis`,
  ADD COLUMN IF NOT EXISTS `converti_en_vente` TINYINT(1) DEFAULT 0 AFTER `devis_id`,
  ADD COLUMN IF NOT EXISTS `vente_id` INT(10) UNSIGNED DEFAULT NULL AFTER `converti_en_vente`;

-- =====================================================================
-- 8. VUES SQL POUR RAPPORTS CONSOLIDÉS
-- =====================================================================

-- Vue : Ventes livrées vs encaissées
CREATE OR REPLACE VIEW `v_ventes_livraison_encaissement` AS
SELECT 
  v.id AS vente_id,
  v.numero AS numero_vente,
  v.date_vente,
  c.nom AS client,
  v.montant_total_ttc,
  v.statut AS statut_vente,
  bl.id AS bl_id,
  bl.numero AS numero_bl,
  bl.date_bl,
  bl.signe_client,
  COALESCE(SUM(jc.montant), 0) AS montant_encaisse,
  (v.montant_total_ttc - COALESCE(SUM(jc.montant), 0)) AS solde_du,
  CASE 
    WHEN COALESCE(SUM(jc.montant), 0) = 0 THEN 'NON_ENCAISSE'
    WHEN COALESCE(SUM(jc.montant), 0) < v.montant_total_ttc THEN 'PARTIELLEMENT_ENCAISSE'
    WHEN COALESCE(SUM(jc.montant), 0) >= v.montant_total_ttc THEN 'TOTALEMENT_ENCAISSE'
  END AS statut_encaissement
FROM ventes v
LEFT JOIN clients c ON v.client_id = c.id
LEFT JOIN bons_livraison bl ON bl.vente_id = v.id
LEFT JOIN journal_caisse jc ON jc.vente_id = v.id AND jc.sens = 'RECETTE'
GROUP BY v.id, bl.id;

-- Vue : Pipeline commercial consolidé
CREATE OR REPLACE VIEW `v_pipeline_commercial` AS
SELECT 
  'SHOWROOM' AS canal,
  'VISITEUR' AS etape,
  COUNT(*) AS nombre,
  DATE(vs.date_visite) AS date_etape
FROM visiteurs_showroom vs
WHERE vs.converti_en_devis = 0
GROUP BY DATE(vs.date_visite)

UNION ALL

SELECT 
  'TERRAIN' AS canal,
  'PROSPECTION' AS etape,
  COUNT(*) AS nombre,
  DATE(pt.date_prospection) AS date_etape
FROM prospections_terrain pt
WHERE pt.converti_en_devis = 0
GROUP BY DATE(pt.date_prospection)

UNION ALL

SELECT 
  'DIGITAL' AS canal,
  statut AS etape,
  COUNT(*) AS nombre,
  DATE(ld.date_lead) AS date_etape
FROM leads_digital ld
GROUP BY statut, DATE(ld.date_lead)

UNION ALL

SELECT 
  cv.code AS canal,
  'DEVIS' AS etape,
  COUNT(*) AS nombre,
  DATE(d.date_devis) AS date_etape
FROM devis d
LEFT JOIN canaux_vente cv ON d.canal_vente_id = cv.id
WHERE d.statut != 'ACCEPTE'
GROUP BY cv.code, DATE(d.date_devis)

UNION ALL

SELECT 
  cv.code AS canal,
  'VENTE' AS etape,
  COUNT(*) AS nombre,
  DATE(v.date_vente) AS date_etape
FROM ventes v
LEFT JOIN canaux_vente cv ON v.canal_vente_id = cv.id
GROUP BY cv.code, DATE(v.date_vente);

-- =====================================================================
-- 9. DONNÉES DE TEST (optionnel)
-- =====================================================================

-- Insérer quelques leads digitaux de test
INSERT INTO `leads_digital` (date_lead, nom_prospect, telephone, source, produit_interet, statut, utilisateur_responsable_id) VALUES
  (CURDATE(), 'Jean Dupont', '+237699123456', 'FACEBOOK', 'Table à manger', 'NOUVEAU', 1),
  (DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Marie Kamga', '+237678987654', 'WHATSAPP', 'Chambre à coucher', 'CONTACTE', 1),
  (DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Paul Ngono', '+237655445566', 'INSTAGRAM', 'Panneaux mélaminés', 'QUALIFIE', 1);

-- Insérer un objectif mensuel global
INSERT INTO `objectifs_commerciaux` (periode, date_debut, date_fin, canal, type_objectif, valeur_cible) VALUES
  ('MENSUEL', DATE_FORMAT(CURDATE(), '%Y-%m-01'), LAST_DAY(CURDATE()), 'GLOBAL', 'CA', 10000000.00);

-- =====================================================================
-- FIN DU SCRIPT
-- =====================================================================

-- Messages de confirmation
SELECT 'EXTENSIONS MARKETING INSTALLÉES AVEC SUCCÈS !' AS statut;
SELECT '✅ Tables créées: leads_digital, ordres_preparation, ruptures_signalees, retours_litiges, relances_devis, conversions_pipeline, objectifs_commerciaux, kpis_quotidiens' AS info;
SELECT '✅ Vues créées: v_ventes_livraison_encaissement, v_pipeline_commercial' AS info;
