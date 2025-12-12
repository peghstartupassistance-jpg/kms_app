-- Import du Plan Comptable SYSCOHADA Complet
-- Basé sur le référentiel OHADA (Organisation pour l'Harmonisation en Afrique du Droit des Affaires)

-- Nettoyer les anciens comptes (optionnel - commentez si vous voulez garder les existants)
-- TRUNCATE TABLE compta_comptes;

-- ==============================================================================
-- CLASSE 1 : COMPTES DE RESSOURCES DURABLES (CAPITAUX PROPRES ET DETTES FINANCIÈRES)
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('10', 'Capital', '1', 'PASSIF', 1),
('11', 'Réserves', '1', 'PASSIF', 1),
('12', 'Report à nouveau', '1', 'PASSIF', 1),
('13', 'Résultat net de l''exercice', '1', 'PASSIF', 1),
('14', 'Subventions d''investissement', '1', 'PASSIF', 1),
('15', 'Provisions réglementées', '1', 'PASSIF', 1),
('16', 'Emprunts et dettes assimilées', '1', 'PASSIF', 1),
('17', 'Dettes de location-acquisition', '1', 'PASSIF', 1),
('18', 'Dettes liées à des participations', '1', 'PASSIF', 1),
('19', 'Provisions financières pour risques et charges', '1', 'PASSIF', 1);

-- ==============================================================================
-- CLASSE 2 : COMPTES D'ACTIF IMMOBILISÉ
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('20', 'Charges immobilisées', '2', 'ACTIF', 1),
('21', 'Immobilisations incorporelles', '2', 'ACTIF', 1),
('22', 'Terrains', '2', 'ACTIF', 1),
('23', 'Bâtiments, installations techniques et agencements', '2', 'ACTIF', 1),
('24', 'Matériel, mobilier et actifs biologiques', '2', 'ACTIF', 1),
('25', 'Avances et acomptes versés sur immobilisations', '2', 'ACTIF', 1),
('26', 'Titres de participation', '2', 'ACTIF', 1),
('27', 'Autres immobilisations financières', '2', 'ACTIF', 1),
('28', 'Amortissements', '2', 'ACTIF', 1),
('29', 'Provisions pour dépréciation des immobilisations', '2', 'ACTIF', 1);

-- ==============================================================================
-- CLASSE 3 : COMPTES DE STOCKS
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('31', 'Marchandises', '3', 'ACTIF', 1),
('32', 'Matières premières et fournitures liées', '3', 'ACTIF', 1),
('33', 'Autres approvisionnements', '3', 'ACTIF', 1),
('34', 'Produits en cours', '3', 'ACTIF', 1),
('35', 'Services en cours', '3', 'ACTIF', 1),
('36', 'Produits finis', '3', 'ACTIF', 1),
('37', 'Produits intermédiaires et résiduels', '3', 'ACTIF', 1),
('38', 'Stocks en cours de route, en consignation ou en dépôt', '3', 'ACTIF', 1),
('39', 'Dépréciations des stocks', '3', 'ACTIF', 1);

-- ==============================================================================
-- CLASSE 4 : COMPTES DE TIERS
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('40', 'Fournisseurs et comptes rattachés', '4', 'PASSIF', 1),
('41', 'Clients et comptes rattachés', '4', 'ACTIF', 1),
('42', 'Personnel', '4', 'PASSIF', 1),
('43', 'Organismes sociaux', '4', 'PASSIF', 1),
('44', 'État et collectivités publiques', '4', 'MIXTE', 1),
('45', 'Organismes internationaux', '4', 'MIXTE', 1),
('46', 'Associés et groupe', '4', 'MIXTE', 1),
('47', 'Débiteurs et créditeurs divers', '4', 'MIXTE', 1),
('48', 'Créances et dettes hors activités ordinaires', '4', 'MIXTE', 1),
('49', 'Dépréciations et risques provisionnés', '4', 'MIXTE', 1);

-- ==============================================================================
-- CLASSE 5 : COMPTES DE TRÉSORERIE
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('50', 'Titres de placement', '5', 'ACTIF', 1),
('51', 'Valeurs à encaisser', '5', 'ACTIF', 1),
('52', 'Banques, établissements financiers et assimilés', '5', 'ACTIF', 1),
('53', 'Établissements financiers et assimilés', '5', 'ACTIF', 1),
('54', 'Instruments de trésorerie', '5', 'ACTIF', 1),
('56', 'Crédits de trésorerie', '5', 'PASSIF', 1),
('57', 'Caisse', '5', 'ACTIF', 1),
('58', 'Régies d''avances, accréditifs et virements internes', '5', 'ACTIF', 1),
('59', 'Dépréciations et risques provisionnés', '5', 'ACTIF', 1);

-- ==============================================================================
-- CLASSE 6 : COMPTES DE CHARGES DES ACTIVITÉS ORDINAIRES
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('60', 'Achats et variations de stocks', '6', 'CHARGE', 1),
('61', 'Transports', '6', 'CHARGE', 1),
('62', 'Services extérieurs A', '6', 'CHARGE', 1),
('63', 'Autres services extérieurs B', '6', 'CHARGE', 1),
('64', 'Impôts et taxes', '6', 'CHARGE', 1),
('65', 'Autres charges', '6', 'CHARGE', 1),
('66', 'Charges de personnel', '6', 'CHARGE', 1),
('67', 'Frais financiers et charges assimilées', '6', 'CHARGE', 1),
('68', 'Dotations aux amortissements', '6', 'CHARGE', 1),
('69', 'Dotations aux provisions', '6', 'CHARGE', 1);

-- ==============================================================================
-- CLASSE 7 : COMPTES DE PRODUITS DES ACTIVITÉS ORDINAIRES
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('70', 'Ventes', '7', 'PRODUIT', 1),
('71', 'Subventions d''exploitation', '7', 'PRODUIT', 1),
('72', 'Production immobilisée', '7', 'PRODUIT', 1),
('73', 'Variations des stocks de biens et de services produits', '7', 'PRODUIT', 1),
('75', 'Autres produits', '7', 'PRODUIT', 1),
('77', 'Revenus financiers et produits assimilés', '7', 'PRODUIT', 1),
('78', 'Transferts de charges', '7', 'PRODUIT', 1),
('79', 'Reprises de provisions', '7', 'PRODUIT', 1);

-- ==============================================================================
-- CLASSE 8 : COMPTES DES AUTRES CHARGES ET DES AUTRES PRODUITS
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('81', 'Valeurs comptables des cessions d''immobilisations', '8', 'CHARGE', 1),
('82', 'Produits des cessions d''immobilisations', '8', 'PRODUIT', 1),
('83', 'Charges hors activités ordinaires', '8', 'CHARGE', 1),
('84', 'Produits hors activités ordinaires', '8', 'PRODUIT', 1),
('85', 'Dotations hors activités ordinaires', '8', 'CHARGE', 1),
('86', 'Reprises hors activités ordinaires', '8', 'PRODUIT', 1),
('87', 'Participations des travailleurs', '8', 'CHARGE', 1),
('88', 'Subventions d''équilibre', '8', 'PRODUIT', 1),
('89', 'Impôts sur le résultat', '8', 'CHARGE', 1);

-- ==============================================================================
-- CLASSE 9 : COMPTES DES ENGAGEMENTS HORS BILAN ET COMPTES DE LA COMPTABILITÉ ANALYTIQUE
-- ==============================================================================

INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, est_actif) VALUES
('90', 'Engagements donnés ou reçus', '9', 'ANALYTIQUE', 1),
('91', 'Contrepartie des engagements', '9', 'ANALYTIQUE', 1),
('92', 'Comptes réfléchis du bilan', '9', 'ANALYTIQUE', 1),
('93', 'Comptes réfléchis de gestion', '9', 'ANALYTIQUE', 1),
('94', 'Comptes de stocks', '9', 'ANALYTIQUE', 1),
('95', 'Comptes de coûts', '9', 'ANALYTIQUE', 1),
('96', 'Comptes d''écarts sur coûts', '9', 'ANALYTIQUE', 1),
('97', 'Comptes de résultats analytiques', '9', 'ANALYTIQUE', 1),
('98', 'Comptes de liaisons internes', '9', 'ANALYTIQUE', 1),
('99', 'Comptes de l''activité', '9', 'ANALYTIQUE', 1);

-- ==============================================================================
-- COMPTES DÉTAILLÉS PRINCIPAUX (Subdivision courante)
-- ==============================================================================

-- Sous-comptes Classe 4 (Tiers)
INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, compte_parent_id, est_actif) VALUES
('401', 'Fournisseurs - dettes en compte', '4', 'PASSIF', (SELECT id FROM compta_comptes WHERE numero_compte = '40' LIMIT 1), 1),
('411', 'Clients', '4', 'ACTIF', (SELECT id FROM compta_comptes WHERE numero_compte = '41' LIMIT 1), 1),
('421', 'Personnel - Rémunérations dues', '4', 'PASSIF', (SELECT id FROM compta_comptes WHERE numero_compte = '42' LIMIT 1), 1),
('431', 'Sécurité sociale', '4', 'PASSIF', (SELECT id FROM compta_comptes WHERE numero_compte = '43' LIMIT 1), 1),
('441', 'État - Impôts sur les bénéfices', '4', 'PASSIF', (SELECT id FROM compta_comptes WHERE numero_compte = '44' LIMIT 1), 1),
('443', 'TVA facturée', '4', 'PASSIF', (SELECT id FROM compta_comptes WHERE numero_compte = '44' LIMIT 1), 1),
('445', 'TVA récupérable', '4', 'ACTIF', (SELECT id FROM compta_comptes WHERE numero_compte = '44' LIMIT 1), 1),
('471', 'Débiteurs divers', '4', 'ACTIF', (SELECT id FROM compta_comptes WHERE numero_compte = '47' LIMIT 1), 1),
('472', 'Créditeurs divers', '4', 'PASSIF', (SELECT id FROM compta_comptes WHERE numero_compte = '47' LIMIT 1), 1);

-- Sous-comptes Classe 5 (Trésorerie)
INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, compte_parent_id, est_actif) VALUES
('521', 'Banques locales', '5', 'ACTIF', (SELECT id FROM compta_comptes WHERE numero_compte = '52' LIMIT 1), 1),
('531', 'Chèques postaux', '5', 'ACTIF', (SELECT id FROM compta_comptes WHERE numero_compte = '53' LIMIT 1), 1),
('571', 'Caisse siège social', '5', 'ACTIF', (SELECT id FROM compta_comptes WHERE numero_compte = '57' LIMIT 1), 1);

-- Sous-comptes Classe 6 (Charges)
INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, compte_parent_id, est_actif) VALUES
('601', 'Achats de marchandises', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '60' LIMIT 1), 1),
('602', 'Achats de matières premières', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '60' LIMIT 1), 1),
('604', 'Achats stockés de matières et fournitures consommables', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '60' LIMIT 1), 1),
('605', 'Autres achats', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '60' LIMIT 1), 1),
('607', 'Achats de marchandises', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '60' LIMIT 1), 1),
('611', 'Transports sur achats', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '61' LIMIT 1), 1),
('612', 'Transports sur ventes', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '61' LIMIT 1), 1),
('622', 'Locations et charges locatives', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '62' LIMIT 1), 1),
('624', 'Entretien, réparations et maintenance', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '62' LIMIT 1), 1),
('626', 'Études, recherches et documentation', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '62' LIMIT 1), 1),
('631', 'Frais bancaires', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '63' LIMIT 1), 1),
('661', 'Appointements, salaires et commissions', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '66' LIMIT 1), 1),
('663', 'Indemnités forfaitaires versées au personnel', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '66' LIMIT 1), 1),
('664', 'Charges sociales', '6', 'CHARGE', (SELECT id FROM compta_comptes WHERE numero_compte = '66' LIMIT 1), 1);

-- Sous-comptes Classe 7 (Produits)
INSERT INTO compta_comptes (numero_compte, libelle, classe, type_compte, compte_parent_id, est_actif) VALUES
('701', 'Ventes de produits finis', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1),
('702', 'Ventes de produits intermédiaires', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1),
('703', 'Ventes de produits résiduels', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1),
('704', 'Travaux', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1),
('705', 'Études', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1),
('706', 'Prestations de services', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1),
('707', 'Ventes de marchandises', '7', 'PRODUIT', (SELECT id FROM compta_comptes WHERE numero_compte = '70' LIMIT 1), 1);

-- Message de fin
SELECT 'Plan comptable SYSCOHADA importé avec succès !' as message;
SELECT COUNT(*) as nb_comptes FROM compta_comptes;
