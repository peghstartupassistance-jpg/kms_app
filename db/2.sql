-- =================================================================
--  PARTIE 2 : Catalogue produits, clients, terrain, ventes, hôtel,
--             formation, caisse, satisfaction
-- =================================================================

-- -----------------------------------------------------
--  TABLE: sous_categories_produits
-- -----------------------------------------------------
CREATE TABLE sous_categories_produits (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    famille_id  INT UNSIGNED NOT NULL,
    nom         VARCHAR(100) NOT NULL,
    CONSTRAINT fk_sous_categories_famille
        FOREIGN KEY (famille_id) REFERENCES familles_produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: fournisseurs
-- -----------------------------------------------------
CREATE TABLE fournisseurs (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom      VARCHAR(150) NOT NULL,
    contact  VARCHAR(150) NULL,
    telephone VARCHAR(50) NULL,
    email    VARCHAR(150) NULL,
    adresse  TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: produits
-- -----------------------------------------------------
CREATE TABLE produits (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_produit     VARCHAR(100) NOT NULL UNIQUE,
    famille_id       INT UNSIGNED NOT NULL,
    sous_categorie_id INT UNSIGNED NULL,
    designation      VARCHAR(255) NOT NULL,
    caracteristiques TEXT NULL,
    description      TEXT NULL,
    fournisseur_id   INT UNSIGNED NULL,
    localisation     VARCHAR(150) NULL,
    prix_achat       DECIMAL(15,2) NOT NULL DEFAULT 0,
    prix_vente       DECIMAL(15,2) NOT NULL DEFAULT 0,
    stock_actuel     INT NOT NULL DEFAULT 0,
    seuil_alerte     INT NOT NULL DEFAULT 0,
    image_path       VARCHAR(255) NULL,
    actif            TINYINT(1) NOT NULL DEFAULT 1,
    date_creation    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NULL,
    CONSTRAINT fk_produits_famille
        FOREIGN KEY (famille_id) REFERENCES familles_produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_produits_sous_categorie
        FOREIGN KEY (sous_categorie_id) REFERENCES sous_categories_produits(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_produits_fournisseur
        FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_produits_designation (designation),
    INDEX idx_produits_code (code_produit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: stocks_mouvements
-- -----------------------------------------------------
CREATE TABLE stocks_mouvements (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produit_id     INT UNSIGNED NOT NULL,
    date_mouvement DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    type_mouvement ENUM('ENTREE','SORTIE','AJUSTEMENT') NOT NULL,
    quantite       INT NOT NULL,
    source_type    VARCHAR(50) NULL,   -- VENTE, ACHAT, INVENTAIRE, LITIGE, etc.
    source_id      INT NULL,
    commentaire    TEXT NULL,
    utilisateur_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_mouvements_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mouvements_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_mouvements_date (date_mouvement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: clients
-- -----------------------------------------------------
CREATE TABLE clients (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom            VARCHAR(150) NOT NULL,
    type_client_id INT UNSIGNED NOT NULL,
    telephone      VARCHAR(50) NULL,
    email          VARCHAR(150) NULL,
    adresse        TEXT NULL,
    source         VARCHAR(100) NULL, -- Facebook, WhatsApp, Terrain, Showroom...
    statut         ENUM('PROSPECT','CLIENT','APPRENANT','HOTE') NOT NULL DEFAULT 'PROSPECT',
    date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_type
        FOREIGN KEY (type_client_id) REFERENCES types_client(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_clients_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: visiteurs_showroom
-- -----------------------------------------------------
CREATE TABLE visiteurs_showroom (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_visite   DATE NOT NULL,
    client_nom    VARCHAR(150) NOT NULL,
    contact       VARCHAR(100) NOT NULL,
    produit_interet TEXT NULL,
    orientation   VARCHAR(100) NULL,  -- devis, hôtel, formation…
    client_id     INT UNSIGNED NULL,
    utilisateur_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_visiteurs_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_visiteurs_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_visiteurs_date (date_visite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: prospections_terrain
-- -----------------------------------------------------
CREATE TABLE prospections_terrain (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_prospection DATE NOT NULL,
    prospect_nom    VARCHAR(150) NOT NULL,
    secteur         VARCHAR(150) NOT NULL,
    besoin_identifie TEXT NOT NULL,
    action_menee    TEXT NOT NULL,
    resultat        TEXT NOT NULL,
    prochaine_etape TEXT NULL,
    client_id       INT UNSIGNED NULL,
    commercial_id   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_prospections_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_prospections_commercial
        FOREIGN KEY (commercial_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_prospections_date (date_prospection)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: rendezvous_terrain
-- -----------------------------------------------------
CREATE TABLE rendezvous_terrain (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_rdv           DATE NOT NULL,
    heure_rdv          TIME NOT NULL,
    client_prospect_nom VARCHAR(150) NOT NULL,
    lieu               VARCHAR(150) NOT NULL,
    objectif           TEXT NOT NULL,
    statut             ENUM('PLANIFIE','CONFIRME','ANNULE','HONORE') NOT NULL DEFAULT 'PLANIFIE',
    client_id          INT UNSIGNED NULL,
    commercial_id      INT UNSIGNED NOT NULL,
    CONSTRAINT fk_rdv_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rdv_commercial
        FOREIGN KEY (commercial_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_rdv_date (date_rdv)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: devis
-- -----------------------------------------------------
CREATE TABLE devis (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero           VARCHAR(50) NOT NULL UNIQUE,
    date_devis       DATE NOT NULL,
    client_id        INT UNSIGNED NOT NULL,
    canal_vente_id   INT UNSIGNED NOT NULL,
    statut           ENUM('EN_ATTENTE','ACCEPTE','REFUSE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE',
    date_relance     DATE NULL,
    utilisateur_id   INT UNSIGNED NOT NULL,
    montant_total_ht DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant_total_ttc DECIMAL(15,2) NOT NULL DEFAULT 0,
    remise_global    DECIMAL(15,2) NOT NULL DEFAULT 0,
    conditions       TEXT NULL,
    commentaires     TEXT NULL,
    CONSTRAINT fk_devis_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_devis_canal
        FOREIGN KEY (canal_vente_id) REFERENCES canaux_vente(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_devis_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_devis_date (date_devis),
    INDEX idx_devis_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: devis_lignes
-- -----------------------------------------------------
CREATE TABLE devis_lignes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    devis_id        INT UNSIGNED NOT NULL,
    produit_id      INT UNSIGNED NOT NULL,
    quantite        INT NOT NULL,
    prix_unitaire   DECIMAL(15,2) NOT NULL,
    remise          DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant_ligne_ht DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_devis_lignes_devis
        FOREIGN KEY (devis_id) REFERENCES devis(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_devis_lignes_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: ventes
-- -----------------------------------------------------
CREATE TABLE ventes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero           VARCHAR(50) NOT NULL UNIQUE,
    date_vente       DATE NOT NULL,
    client_id        INT UNSIGNED NOT NULL,
    canal_vente_id   INT UNSIGNED NOT NULL,
    devis_id         INT UNSIGNED NULL,
    statut           ENUM('EN_ATTENTE_LIVRAISON','LIVREE','ANNULEE','PARTIELLEMENT_LIVREE')
                     NOT NULL DEFAULT 'EN_ATTENTE_LIVRAISON',
    montant_total_ht DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant_total_ttc DECIMAL(15,2) NOT NULL DEFAULT 0,
    utilisateur_id   INT UNSIGNED NOT NULL,
    commentaires     TEXT NULL,
    CONSTRAINT fk_ventes_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ventes_canal
        FOREIGN KEY (canal_vente_id) REFERENCES canaux_vente(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ventes_devis
        FOREIGN KEY (devis_id) REFERENCES devis(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ventes_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_ventes_date (date_vente),
    INDEX idx_ventes_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: ventes_lignes
-- -----------------------------------------------------
CREATE TABLE ventes_lignes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vente_id        INT UNSIGNED NOT NULL,
    produit_id      INT UNSIGNED NOT NULL,
    quantite        INT NOT NULL,
    prix_unitaire   DECIMAL(15,2) NOT NULL,
    remise          DECIMAL(15,2) NOT NULL DEFAULT 0,
    montant_ligne_ht DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_ventes_lignes_vente
        FOREIGN KEY (vente_id) REFERENCES ventes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ventes_lignes_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: bons_livraison
-- -----------------------------------------------------
CREATE TABLE bons_livraison (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero            VARCHAR(50) NOT NULL UNIQUE,
    date_bl           DATE NOT NULL,
    vente_id          INT UNSIGNED NULL,
    client_id         INT UNSIGNED NOT NULL,
    transport_assure_par VARCHAR(150) NULL,
    observations      TEXT NULL,
    signe_client      TINYINT(1) NOT NULL DEFAULT 0,
    magasinier_id     INT UNSIGNED NOT NULL,
    CONSTRAINT fk_bl_vente
        FOREIGN KEY (vente_id) REFERENCES ventes(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_bl_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_bl_magasinier
        FOREIGN KEY (magasinier_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_bl_date (date_bl)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: bons_livraison_lignes
-- -----------------------------------------------------
CREATE TABLE bons_livraison_lignes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bon_livraison_id INT UNSIGNED NOT NULL,
    produit_id       INT UNSIGNED NOT NULL,
    quantite         INT NOT NULL,
    CONSTRAINT fk_bl_lignes_bl
        FOREIGN KEY (bon_livraison_id) REFERENCES bons_livraison(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bl_lignes_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: retours_litiges
-- -----------------------------------------------------
CREATE TABLE retours_litiges (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_retour          DATE NOT NULL,
    client_id            INT UNSIGNED NOT NULL,
    produit_id           INT UNSIGNED NOT NULL,
    vente_id             INT UNSIGNED NULL,
    motif                TEXT NOT NULL,
    responsable_suivi_id INT UNSIGNED NOT NULL,
    statut_traitement    ENUM('EN_COURS','RESOLU','ABANDONNE') NOT NULL DEFAULT 'EN_COURS',
    solution             TEXT NULL,
    CONSTRAINT fk_litiges_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_litiges_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_litiges_vente
        FOREIGN KEY (vente_id) REFERENCES ventes(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_litiges_responsable
        FOREIGN KEY (responsable_suivi_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: ruptures_stock
-- -----------------------------------------------------
CREATE TABLE ruptures_stock (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_rapport    DATE NOT NULL,
    produit_id      INT UNSIGNED NOT NULL,
    seuil_alerte    INT NOT NULL,
    stock_actuel    INT NOT NULL,
    impact_commercial TEXT NOT NULL,
    action_proposee TEXT NOT NULL,
    magasinier_id   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_ruptures_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ruptures_magasinier
        FOREIGN KEY (magasinier_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_ruptures_date (date_rapport)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: modes_paiement
-- -----------------------------------------------------
CREATE TABLE modes_paiement (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(50)  NOT NULL UNIQUE, -- CASH, VIREMENT, MOBILE_MONEY, CHEQUE
    libelle VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: chambres (Hôtel)
-- -----------------------------------------------------
CREATE TABLE chambres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    tarif_nuite DECIMAL(15,2) NOT NULL DEFAULT 0,
    actif       TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: formations
-- -----------------------------------------------------
CREATE TABLE formations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    tarif_total DECIMAL(15,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: reservations_hotel
-- -----------------------------------------------------
CREATE TABLE reservations_hotel (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_reservation DATE NOT NULL,
    client_id        INT UNSIGNED NOT NULL,
    chambre_id       INT UNSIGNED NOT NULL,
    date_debut       DATE NOT NULL,
    date_fin         DATE NOT NULL,
    nb_nuits         INT NOT NULL,
    montant_total    DECIMAL(15,2) NOT NULL DEFAULT 0,
    statut           ENUM('EN_COURS','TERMINEE','ANNULEE') NOT NULL DEFAULT 'EN_COURS',
    mode_paiement_id INT UNSIGNED NULL,
    concierge_id     INT UNSIGNED NOT NULL,
    CONSTRAINT fk_reservation_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservation_chambre
        FOREIGN KEY (chambre_id) REFERENCES chambres(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reservation_mode_paiement
        FOREIGN KEY (mode_paiement_id) REFERENCES modes_paiement(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reservation_concierge
        FOREIGN KEY (concierge_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_reservation_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: upsell_hotel
-- -----------------------------------------------------
CREATE TABLE upsell_hotel (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id  INT UNSIGNED NOT NULL,
    service_additionnel VARCHAR(150) NOT NULL,
    montant         DECIMAL(15,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_upsell_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations_hotel(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: visiteurs_hotel
-- -----------------------------------------------------
CREATE TABLE visiteurs_hotel (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_visite   DATE NOT NULL,
    nom_visiteur  VARCHAR(150) NOT NULL,
    motif         TEXT NOT NULL,
    service_solicite VARCHAR(150) NOT NULL,
    orientation   VARCHAR(150) NULL,
    concierge_id  INT UNSIGNED NOT NULL,
    CONSTRAINT fk_visiteurs_hotel_concierge
        FOREIGN KEY (concierge_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_visiteurs_hotel_date (date_visite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: prospects_formation
-- -----------------------------------------------------
CREATE TABLE prospects_formation (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_prospect  DATE NOT NULL,
    nom_prospect   VARCHAR(150) NOT NULL,
    contact        VARCHAR(100) NOT NULL,
    source         VARCHAR(100) NULL,
    statut_actuel  VARCHAR(100) NOT NULL,
    client_id      INT UNSIGNED NULL,
    utilisateur_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_prospect_formation_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_prospect_formation_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_prospect_formation_date (date_prospect)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: inscriptions_formation
-- -----------------------------------------------------
CREATE TABLE inscriptions_formation (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_inscription DATE NOT NULL,
    apprenant_nom   VARCHAR(150) NOT NULL,
    client_id       INT UNSIGNED NULL,
    formation_id    INT UNSIGNED NOT NULL,
    montant_paye    DECIMAL(15,2) NOT NULL DEFAULT 0,
    solde_du        DECIMAL(15,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_inscription_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inscription_formation
        FOREIGN KEY (formation_id) REFERENCES formations(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_inscription_date (date_inscription)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: journal_caisse
-- -----------------------------------------------------
CREATE TABLE journal_caisse (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_operation           DATE NOT NULL,
    numero_piece             VARCHAR(50) NOT NULL,
    nature_operation         TEXT NOT NULL,
    sens                     ENUM('RECETTE','DEPENSE') NOT NULL,
    montant                  DECIMAL(15,2) NOT NULL,
    mode_paiement_id         INT UNSIGNED NOT NULL,
    vente_id                 INT UNSIGNED NULL,
    reservation_id           INT UNSIGNED NULL,
    inscription_formation_id INT UNSIGNED NULL,
    responsable_encaissement_id INT UNSIGNED NOT NULL,
    observations             TEXT NULL,
    CONSTRAINT fk_caisse_mode_paiement
        FOREIGN KEY (mode_paiement_id) REFERENCES modes_paiement(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_caisse_vente
        FOREIGN KEY (vente_id) REFERENCES ventes(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_caisse_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations_hotel(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_caisse_inscription
        FOREIGN KEY (inscription_formation_id) REFERENCES inscriptions_formation(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_caisse_responsable
        FOREIGN KEY (responsable_encaissement_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_caisse_date (date_operation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: promotions
-- -----------------------------------------------------
CREATE TABLE promotions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(150) NOT NULL,
    description       TEXT NULL,
    pourcentage_remise DECIMAL(5,2) NULL,
    montant_remise    DECIMAL(15,2) NULL,
    date_debut        DATE NOT NULL,
    date_fin          DATE NOT NULL,
    actif             TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: promotion_produit (N-N)
-- -----------------------------------------------------
CREATE TABLE promotion_produit (
    promotion_id INT UNSIGNED NOT NULL,
    produit_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (promotion_id, produit_id),
    CONSTRAINT fk_promo_produit_promo
        FOREIGN KEY (promotion_id) REFERENCES promotions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_promo_produit_produit
        FOREIGN KEY (produit_id) REFERENCES produits(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: satisfaction_clients
-- -----------------------------------------------------
CREATE TABLE satisfaction_clients (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_satisfaction DATE NOT NULL,
    client_id      INT UNSIGNED NULL,
    nom_client     VARCHAR(150) NOT NULL,
    service_utilise ENUM('SHOWROOM','HOTEL','FORMATION','TERRAIN','DIGITAL') NOT NULL,
    note           INT NOT NULL, -- 1 à 5
    commentaire    TEXT NULL,
    utilisateur_id INT UNSIGNED NOT NULL,
    CONSTRAINT fk_satisfaction_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_satisfaction_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_satisfaction_date (date_satisfaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
--  SEED COMPLÉMENTAIRE
-- =================================================================

-- Modes de paiement
INSERT INTO modes_paiement (code, libelle) VALUES
 ('CASH',         'Espèces'),
 ('VIREMENT',     'Virement bancaire'),
 ('MOBILE_MONEY', 'Mobile Money'),
 ('CHEQUE',       'Chèque');

-- Fournisseurs de base
INSERT INTO fournisseurs (nom, contact, telephone, email, adresse) VALUES
 ('Fournisseur Général KMS', 'Service commercial', '+237600000001', 'fournisseur@kms.local', 'Douala'),
 ('Import Matériaux Pro',    'Responsable achat',  '+237600000002', 'imports@kms.local',     'Douala - Zone industrielle');

-- Sous-catégories exemples (en supposant les familles 1..4)
INSERT INTO sous_categories_produits (famille_id, nom) VALUES
 (1, 'Chambres à coucher'),
 (1, 'Salons'),
 (2, 'Quincaillerie standard'),
 (3, 'Machines de découpe'),
 (4, 'Panneaux mélaminés');

-- Produits de test
INSERT INTO produits (code_produit, famille_id, sous_categorie_id, designation,
                      caracteristiques, description, fournisseur_id,
                      localisation, prix_achat, prix_vente,
                      stock_actuel, seuil_alerte)
VALUES
 ('MEU-CH-001', 1, 1, 'Lit 2 places avec chevets',
  'Dimensions 160x200', 'Lit moderne pour chambre parentale', 1,
  'Showroom Douala', 120000, 180000, 5, 1),

 ('MEU-SAL-001', 1, 2, 'Salon 5 places',
  'Structure bois, mousse haute densité', 'Salon complet 3+1+1', 1,
  'Showroom Douala', 200000, 280000, 3, 1),

 ('ACC-VIS-001', 2, 3, 'Lot de visserie menuiserie',
  'Assortiment vis bois', 'Accessoires pour montage de meubles', 2,
  'Magasin PK12', 15000, 25000, 50, 10),

 ('PAN-MEL-001', 4, 5, 'Panneau mélaminé blanc 18mm',
  '2,75m x 1,83m', 'Panneau pour agencement intérieur', 2,
  'Magasin PK12', 25000, 38000, 30, 5);

-- Mouvement de stock initial (exemple) lié à l’utilisateur admin (id=1)
INSERT INTO stocks_mouvements (produit_id, type_mouvement, quantite, source_type, source_id, commentaire, utilisateur_id)
VALUES
 (1, 'ENTREE', 5,  'INVENTAIRE', NULL, 'Stock initial lit 2 places', 1),
 (2, 'ENTREE', 3,  'INVENTAIRE', NULL, 'Stock initial salon', 1),
 (3, 'ENTREE', 50, 'INVENTAIRE', NULL, 'Stock initial visserie', 1),
 (4, 'ENTREE', 30, 'INVENTAIRE', NULL, 'Stock initial panneaux', 1);

-- Clients de test (en supposant l’ordre d’insertion des types_client)
-- 1: SHOWROOM, 2: TERRAIN, 3: DIGITAL, 4: HOTEL, 5: FORMATION
INSERT INTO clients (nom, type_client_id, telephone, email, adresse, source, statut)
VALUES
 ('Client Showroom Test', 1, '+237650000001', 'client.showroom@test.local', 'Douala', 'Showroom', 'CLIENT'),
 ('Client Terrain Test',  2, '+237650000002', 'client.terrain@test.local',  'Bonabéri', 'Terrain', 'PROSPECT'),
 ('Client Digital Test',  3, '+237650000003', 'client.digital@test.local',  'Yaoundé', 'Facebook', 'CLIENT'),
 ('Client Hôtel Test',    4, '+237650000004', 'client.hotel@test.local',    'Douala', 'Réservation directe', 'HOTE'),
 ('Apprenant Formation',  5, '+237650000005', 'apprenant@test.local',       'Bafoussam', 'WhatsApp', 'APPRENANT');

-- Chambres test
INSERT INTO chambres (code, description, tarif_nuite, actif) VALUES
 ('CH-101', 'Chambre standard lit double', 20000, 1),
 ('APP-201','Appartement meublé 2 pièces', 35000, 1);

-- Formations test
INSERT INTO formations (nom, description, tarif_total) VALUES
 ('Menuiserie moderne', 'Formation pratique en menuiserie et agencement', 150000),
 ('Agencement intérieur', 'Techniques d’agencement et décoration intérieure', 180000);
