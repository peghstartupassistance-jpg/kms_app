-- ------------------------------------------------------------------
--  KENNE MULTI-SERVICES (KMS)
--  Schéma base de données - Partie 1
--  Socle : utilisateurs, rôles, permissions, clients, canaux, familles
-- ------------------------------------------------------------------

-- (Optionnel) création de la base
CREATE DATABASE IF NOT EXISTS kms_gestion
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE kms_gestion;

SET NAMES utf8mb4;
SET sql_mode = 'STRICT_ALL_TABLES';

-- -----------------------------------------------------
--  TABLE: roles
-- -----------------------------------------------------
DROP TABLE IF EXISTS role_permission;
DROP TABLE IF EXISTS utilisateur_role;
DROP TABLE IF EXISTS connexions_utilisateur;
DROP TABLE IF EXISTS utilisateurs;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS types_client;
DROP TABLE IF EXISTS canaux_vente;
DROP TABLE IF EXISTS familles_produits;

CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50)  NOT NULL UNIQUE,
    nom         VARCHAR(100) NOT NULL,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: permissions
-- -----------------------------------------------------
CREATE TABLE permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: utilisateurs
-- -----------------------------------------------------
CREATE TABLE utilisateurs (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login                  VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe_hash      VARCHAR(255) NOT NULL,
    nom_complet            VARCHAR(150) NOT NULL,
    email                  VARCHAR(150) NULL,
    telephone              VARCHAR(50)  NULL,
    actif                  TINYINT(1)   NOT NULL DEFAULT 1,
    date_creation          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_derniere_connexion DATETIME    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: utilisateur_role (N-N)
-- -----------------------------------------------------
CREATE TABLE utilisateur_role (
    utilisateur_id INT UNSIGNED NOT NULL,
    role_id        INT UNSIGNED NOT NULL,
    PRIMARY KEY (utilisateur_id, role_id),
    CONSTRAINT fk_utilisateur_role_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_utilisateur_role_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: role_permission (N-N)
-- -----------------------------------------------------
CREATE TABLE role_permission (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permission_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_role_permission_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: connexions_utilisateur (journal connexions)
-- -----------------------------------------------------
CREATE TABLE connexions_utilisateur (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    date_connexion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    adresse_ip     VARCHAR(100) NOT NULL,
    user_agent     VARCHAR(255) NOT NULL,
    succes         TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_connexions_utilisateur_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_connexions_utilisateur_date (date_connexion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: types_client
-- -----------------------------------------------------
CREATE TABLE types_client (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(50)  NOT NULL UNIQUE, -- SHOWROOM, TERRAIN, DIGITAL, HOTEL, FORMATION
    libelle VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: canaux_vente
-- -----------------------------------------------------
CREATE TABLE canaux_vente (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(50)  NOT NULL UNIQUE, -- SHOWROOM, TERRAIN, DIGITAL, HOTEL, FORMATION
    libelle VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
--  TABLE: familles_produits
-- -----------------------------------------------------
CREATE TABLE familles_produits (
    id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
--  SEED INITIAL
-- =================================================================

-- -----------------------------------------------------
--  INSERT ROLES
-- -----------------------------------------------------
INSERT INTO roles (code, nom, description) VALUES
 ('ADMIN',       'Administrateur',        'Accès complet à toute l’’application'),
 ('SHOWROOM',    'Commercial Showroom',   'Gestion des visiteurs, devis et ventes en showroom'),
 ('TERRAIN',     'Commercial Terrain',    'Prospection terrain, devis et ventes terrain'),
 ('MAGASINIER',  'Magasinier',           'Gestion des stocks, livraisons, ruptures'),
 ('CAISSIER',    'Caissier',             'Journal de caisse et encaissements'),
 ('DIRECTION',   'Direction',            'Consultation des reportings et indicateurs globaux');

-- -----------------------------------------------------
--  INSERT PERMISSIONS
--  (codes utilisés dans les menus et modules principaux)
-- -----------------------------------------------------
INSERT INTO permissions (code, description) VALUES
 ('PRODUITS_LIRE',      'Consulter le catalogue produits et les stocks'),
 ('PRODUITS_CREER',     'Créer de nouveaux produits'),
 ('PRODUITS_MODIFIER',  'Modifier les produits existants'),
 ('PRODUITS_SUPPRIMER', 'Supprimer des produits'),

 ('CLIENTS_LIRE',       'Consulter les clients / prospects'),
 ('CLIENTS_CREER',      'Créer ou modifier des clients'),

 ('DEVIS_LIRE',         'Lister et consulter les devis'),
 ('DEVIS_CREER',        'Créer des devis'),
 ('DEVIS_MODIFIER',     'Modifier le statut ou le contenu des devis'),

 ('VENTES_LIRE',        'Consulter les ventes et bons de livraison'),
 ('VENTES_CREER',       'Créer des ventes'),
 ('VENTES_VALIDER',     'Valider des ventes / livraisons'),

 ('CAISSE_LIRE',        'Consulter le journal de caisse'),
 ('CAISSE_ECRIRE',      'Enregistrer des opérations de caisse'),

 ('PROMOTIONS_GERER',   'Créer et gérer les promotions'),

 ('HOTEL_GERER',        'Gérer les réservations hôtel et upsell'),
 ('FORMATION_GERER',    'Gérer les formations et inscriptions'),

 ('REPORTING_LIRE',     'Accéder aux tableaux de bord et reporting');

-- -----------------------------------------------------
--  ASSOCIATION ROLES ↔ PERMISSIONS
-- -----------------------------------------------------

-- ADMIN : toutes les permissions
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'ADMIN';

-- SHOWROOM : clients, devis, ventes, lecture produits
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p
  ON p.code IN ('CLIENTS_LIRE','CLIENTS_CREER',
                'DEVIS_LIRE','DEVIS_CREER','DEVIS_MODIFIER',
                'VENTES_LIRE','VENTES_CREER',
                'PRODUITS_LIRE')
WHERE r.code = 'SHOWROOM';

-- TERRAIN : similaire showroom, orienté terrain
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p
  ON p.code IN ('CLIENTS_LIRE','CLIENTS_CREER',
                'DEVIS_LIRE','DEVIS_CREER',
                'VENTES_LIRE','VENTES_CREER',
                'PRODUITS_LIRE')
WHERE r.code = 'TERRAIN';

-- MAGASINIER : lecture produits + modif stock, ventes lire
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p
  ON p.code IN ('PRODUITS_LIRE','PRODUITS_MODIFIER',
                'VENTES_LIRE')
WHERE r.code = 'MAGASINIER';

-- CAISSIER : caisse + ventes lire + reporting basique
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p
  ON p.code IN ('CAISSE_LIRE','CAISSE_ECRIRE',
                'VENTES_LIRE',
                'REPORTING_LIRE')
WHERE r.code = 'CAISSIER';

-- DIRECTION : lecture globale orientée reporting
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p
  ON p.code IN ('PRODUITS_LIRE',
                'CLIENTS_LIRE',
                'DEVIS_LIRE',
                'VENTES_LIRE',
                'CAISSE_LIRE',
                'HOTEL_GERER',
                'FORMATION_GERER',
                'REPORTING_LIRE')
WHERE r.code = 'DIRECTION';

-- -----------------------------------------------------
--  UTILISATEUR ADMIN PAR DÉFAUT
--  login : admin
--  mot de passe : admin123
-- -----------------------------------------------------
INSERT INTO utilisateurs (login, mot_de_passe_hash, nom_complet, email, telephone, actif)
VALUES (
    'admin',
    '$2b$10$j6YYUX.QLOxOoBn9eB4rJu8/ye4/NOEXPvRjcYhUY4mBiaZZFUrTi',
    'Administrateur KMS',
    'admin@kms.local',
    NULL,
    1
);

-- Association de l’admin au rôle ADMIN
INSERT INTO utilisateur_role (utilisateur_id, role_id)
SELECT u.id, r.id
FROM utilisateurs u, roles r
WHERE u.login = 'admin' AND r.code = 'ADMIN';

-- -----------------------------------------------------
--  SEED TYPES CLIENT
-- -----------------------------------------------------
INSERT INTO types_client (code, libelle) VALUES
 ('SHOWROOM',  'Client / prospect showroom'),
 ('TERRAIN',   'Client / prospect terrain'),
 ('DIGITAL',   'Client issu du digital (réseaux sociaux, site, CRM)'),
 ('HOTEL',     'Client hébergement / hôtel'),
 ('FORMATION', 'Apprenant / client formation');

-- -----------------------------------------------------
--  SEED CANAUX VENTE
-- -----------------------------------------------------
INSERT INTO canaux_vente (code, libelle) VALUES
 ('SHOWROOM',  'Vente showroom'),
 ('TERRAIN',   'Vente terrain'),
 ('DIGITAL',   'Vente digital / en ligne'),
 ('HOTEL',     'Vente liée à l’’hôtel'),
 ('FORMATION', 'Vente liée aux formations');

-- -----------------------------------------------------
--  SEED FAMILLES PRODUITS
-- -----------------------------------------------------
INSERT INTO familles_produits (nom) VALUES
 ('Meubles & aménagements intérieurs'),
 ('Accessoires & quincaillerie de menuiserie'),
 ('Machines & équipements de menuiserie'),
 ('Panneaux & matériaux d’’agencement');
