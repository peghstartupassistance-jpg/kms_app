-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 05:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kms_gestion`
--

-- --------------------------------------------------------

--
-- Table structure for table `bons_livraison`
--

CREATE TABLE `bons_livraison` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL,
  `date_bl` date NOT NULL,
  `vente_id` int(10) UNSIGNED DEFAULT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `transport_assure_par` varchar(150) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `signe_client` tinyint(1) NOT NULL DEFAULT 0,
  `magasinier_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bons_livraison_lignes`
--

CREATE TABLE `bons_livraison_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `bon_livraison_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `canaux_vente`
--

CREATE TABLE `canaux_vente` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `libelle` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canaux_vente`
--

INSERT INTO `canaux_vente` (`id`, `code`, `libelle`) VALUES
(1, 'SHOWROOM', 'Vente showroom'),
(2, 'TERRAIN', 'Vente terrain'),
(3, 'DIGITAL', 'Vente digital / en ligne'),
(4, 'HOTEL', 'Vente liée à l’’hôtel'),
(5, 'FORMATION', 'Vente liée aux formations');

-- --------------------------------------------------------

--
-- Table structure for table `chambres`
--

CREATE TABLE `chambres` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `tarif_nuite` decimal(15,2) NOT NULL DEFAULT 0.00,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chambres`
--

INSERT INTO `chambres` (`id`, `code`, `description`, `tarif_nuite`, `actif`) VALUES
(1, 'CH-101', 'Chambre standard lit double', 20000.00, 1),
(2, 'APP-201', 'Appartement meublé 2 pièces', 35000.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `type_client_id` int(10) UNSIGNED NOT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `statut` enum('PROSPECT','CLIENT','APPRENANT','HOTE') NOT NULL DEFAULT 'PROSPECT',
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `nom`, `type_client_id`, `telephone`, `email`, `adresse`, `source`, `statut`, `date_creation`) VALUES
(1, 'Client Showroom Test', 1, '+237650000001', 'client.showroom@test.local', 'Douala', 'Showroom', 'CLIENT', '2025-11-17 17:25:20'),
(2, 'Client Terrain Test', 2, '+237650000002', 'client.terrain@test.local', 'Bonabéri', 'Terrain', 'PROSPECT', '2025-11-17 17:25:20'),
(3, 'Client Digital Test', 3, '+237650000003', 'client.digital@test.local', 'Yaoundé', 'Facebook', 'CLIENT', '2025-11-17 17:25:20'),
(4, 'Client Hôtel Test', 4, '+237650000004', 'client.hotel@test.local', 'Douala', 'Réservation directe', 'HOTE', '2025-11-17 17:25:20'),
(5, 'Apprenant Formation', 5, '+237650000005', 'apprenant@test.local', 'Bafoussam', 'WhatsApp', 'APPRENANT', '2025-11-17 17:25:20');

-- --------------------------------------------------------

--
-- Table structure for table `connexions_utilisateur`
--

CREATE TABLE `connexions_utilisateur` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `date_connexion` datetime NOT NULL DEFAULT current_timestamp(),
  `adresse_ip` varchar(100) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `succes` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `connexions_utilisateur`
--

INSERT INTO `connexions_utilisateur` (`id`, `utilisateur_id`, `date_connexion`, `adresse_ip`, `user_agent`, `succes`) VALUES
(1, 1, '2025-11-17 17:37:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1);

-- --------------------------------------------------------

--
-- Table structure for table `devis`
--

CREATE TABLE `devis` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL,
  `date_devis` date NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `canal_vente_id` int(10) UNSIGNED NOT NULL,
  `statut` enum('EN_ATTENTE','ACCEPTE','REFUSE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE',
  `date_relance` date DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `montant_total_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_total_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remise_global` decimal(15,2) NOT NULL DEFAULT 0.00,
  `conditions` text DEFAULT NULL,
  `commentaires` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devis_lignes`
--

CREATE TABLE `devis_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `devis_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ligne_ht` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `familles_produits`
--

CREATE TABLE `familles_produits` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `familles_produits`
--

INSERT INTO `familles_produits` (`id`, `nom`) VALUES
(1, 'Meubles & aménagements intérieurs'),
(2, 'Accessoires & quincaillerie de menuiserie'),
(3, 'Machines & équipements de menuiserie'),
(4, 'Panneaux & matériaux d’’agencement');

-- --------------------------------------------------------

--
-- Table structure for table `formations`
--

CREATE TABLE `formations` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `tarif_total` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `formations`
--

INSERT INTO `formations` (`id`, `nom`, `description`, `tarif_total`) VALUES
(1, 'Menuiserie moderne', 'Formation pratique en menuiserie et agencement', 150000.00),
(2, 'Agencement intérieur', 'Techniques d’agencement et décoration intérieure', 180000.00);

-- --------------------------------------------------------

--
-- Table structure for table `fournisseurs`
--

CREATE TABLE `fournisseurs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `contact` varchar(150) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `adresse` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fournisseurs`
--

INSERT INTO `fournisseurs` (`id`, `nom`, `contact`, `telephone`, `email`, `adresse`) VALUES
(1, 'Fournisseur Général KMS', 'Service commercial', '+237600000001', 'fournisseur@kms.local', 'Douala'),
(2, 'Import Matériaux Pro', 'Responsable achat', '+237600000002', 'imports@kms.local', 'Douala - Zone industrielle');

-- --------------------------------------------------------

--
-- Table structure for table `inscriptions_formation`
--

CREATE TABLE `inscriptions_formation` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_inscription` date NOT NULL,
  `apprenant_nom` varchar(150) NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `formation_id` int(10) UNSIGNED NOT NULL,
  `montant_paye` decimal(15,2) NOT NULL DEFAULT 0.00,
  `solde_du` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_caisse`
--

CREATE TABLE `journal_caisse` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_operation` date NOT NULL,
  `numero_piece` varchar(50) NOT NULL,
  `nature_operation` text NOT NULL,
  `sens` enum('RECETTE','DEPENSE') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `mode_paiement_id` int(10) UNSIGNED NOT NULL,
  `vente_id` int(10) UNSIGNED DEFAULT NULL,
  `reservation_id` int(10) UNSIGNED DEFAULT NULL,
  `inscription_formation_id` int(10) UNSIGNED DEFAULT NULL,
  `responsable_encaissement_id` int(10) UNSIGNED NOT NULL,
  `observations` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modes_paiement`
--

CREATE TABLE `modes_paiement` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `libelle` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `modes_paiement`
--

INSERT INTO `modes_paiement` (`id`, `code`, `libelle`) VALUES
(1, 'CASH', 'Espèces'),
(2, 'VIREMENT', 'Virement bancaire'),
(3, 'MOBILE_MONEY', 'Mobile Money'),
(4, 'CHEQUE', 'Chèque');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `description`) VALUES
(1, 'PRODUITS_LIRE', 'Consulter le catalogue produits et les stocks'),
(2, 'PRODUITS_CREER', 'Créer de nouveaux produits'),
(3, 'PRODUITS_MODIFIER', 'Modifier les produits existants'),
(4, 'PRODUITS_SUPPRIMER', 'Supprimer des produits'),
(5, 'CLIENTS_LIRE', 'Consulter les clients / prospects'),
(6, 'CLIENTS_CREER', 'Créer ou modifier des clients'),
(7, 'DEVIS_LIRE', 'Lister et consulter les devis'),
(8, 'DEVIS_CREER', 'Créer des devis'),
(9, 'DEVIS_MODIFIER', 'Modifier le statut ou le contenu des devis'),
(10, 'VENTES_LIRE', 'Consulter les ventes et bons de livraison'),
(11, 'VENTES_CREER', 'Créer des ventes'),
(12, 'VENTES_VALIDER', 'Valider des ventes / livraisons'),
(13, 'CAISSE_LIRE', 'Consulter le journal de caisse'),
(14, 'CAISSE_ECRIRE', 'Enregistrer des opérations de caisse'),
(15, 'PROMOTIONS_GERER', 'Créer et gérer les promotions'),
(16, 'HOTEL_GERER', 'Gérer les réservations hôtel et upsell'),
(17, 'FORMATION_GERER', 'Gérer les formations et inscriptions'),
(18, 'REPORTING_LIRE', 'Accéder aux tableaux de bord et reporting');

-- --------------------------------------------------------

--
-- Table structure for table `produits`
--

CREATE TABLE `produits` (
  `id` int(10) UNSIGNED NOT NULL,
  `code_produit` varchar(100) NOT NULL,
  `famille_id` int(10) UNSIGNED NOT NULL,
  `sous_categorie_id` int(10) UNSIGNED DEFAULT NULL,
  `designation` varchar(255) NOT NULL,
  `caracteristiques` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fournisseur_id` int(10) UNSIGNED DEFAULT NULL,
  `localisation` varchar(150) DEFAULT NULL,
  `prix_achat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `prix_vente` decimal(15,2) NOT NULL DEFAULT 0.00,
  `stock_actuel` int(11) NOT NULL DEFAULT 0,
  `seuil_alerte` int(11) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `produits`
--

INSERT INTO `produits` (`id`, `code_produit`, `famille_id`, `sous_categorie_id`, `designation`, `caracteristiques`, `description`, `fournisseur_id`, `localisation`, `prix_achat`, `prix_vente`, `stock_actuel`, `seuil_alerte`, `image_path`, `actif`, `date_creation`, `date_modification`) VALUES
(1, 'MEU-CH-001', 1, 1, 'Lit 2 places avec chevets', 'Dimensions 160x200', 'Lit moderne pour chambre parentale', 1, 'Showroom Douala', 120000.00, 180000.00, 5, 1, NULL, 1, '2025-11-17 17:25:20', NULL),
(2, 'MEU-SAL-001', 1, 2, 'Salon 5 places', 'Structure bois, mousse haute densité', 'Salon complet 3+1+1', 1, 'Showroom Douala', 200000.00, 280000.00, 3, 1, NULL, 1, '2025-11-17 17:25:20', NULL),
(3, 'ACC-VIS-001', 2, 3, 'Lot de visserie menuiserie', 'Assortiment vis bois', 'Accessoires pour montage de meubles', 2, 'Magasin PK12', 15000.00, 25000.00, 50, 10, NULL, 1, '2025-11-17 17:25:20', NULL),
(4, 'PAN-MEL-001', 4, 5, 'Panneau mélaminé blanc 18mm', '2,75m x 1,83m', 'Panneau pour agencement intérieur', 2, 'Magasin PK12', 25000.00, 38000.00, 30, 5, NULL, 1, '2025-11-17 17:25:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `pourcentage_remise` decimal(5,2) DEFAULT NULL,
  `montant_remise` decimal(15,2) DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotion_produit`
--

CREATE TABLE `promotion_produit` (
  `promotion_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prospections_terrain`
--

CREATE TABLE `prospections_terrain` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_prospection` date NOT NULL,
  `prospect_nom` varchar(150) NOT NULL,
  `secteur` varchar(150) NOT NULL,
  `besoin_identifie` text NOT NULL,
  `action_menee` text NOT NULL,
  `resultat` text NOT NULL,
  `prochaine_etape` text DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `commercial_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prospects_formation`
--

CREATE TABLE `prospects_formation` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_prospect` date NOT NULL,
  `nom_prospect` varchar(150) NOT NULL,
  `contact` varchar(100) NOT NULL,
  `source` varchar(100) DEFAULT NULL,
  `statut_actuel` varchar(100) NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rendezvous_terrain`
--

CREATE TABLE `rendezvous_terrain` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_rdv` date NOT NULL,
  `heure_rdv` time NOT NULL,
  `client_prospect_nom` varchar(150) NOT NULL,
  `lieu` varchar(150) NOT NULL,
  `objectif` text NOT NULL,
  `statut` enum('PLANIFIE','CONFIRME','ANNULE','HONORE') NOT NULL DEFAULT 'PLANIFIE',
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `commercial_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations_hotel`
--

CREATE TABLE `reservations_hotel` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_reservation` date NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `chambre_id` int(10) UNSIGNED NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `nb_nuits` int(11) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `statut` enum('EN_COURS','TERMINEE','ANNULEE') NOT NULL DEFAULT 'EN_COURS',
  `mode_paiement_id` int(10) UNSIGNED DEFAULT NULL,
  `concierge_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `retours_litiges`
--

CREATE TABLE `retours_litiges` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_retour` date NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `vente_id` int(10) UNSIGNED DEFAULT NULL,
  `motif` text NOT NULL,
  `responsable_suivi_id` int(10) UNSIGNED NOT NULL,
  `statut_traitement` enum('EN_COURS','RESOLU','ABANDONNE') NOT NULL DEFAULT 'EN_COURS',
  `solution` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `code`, `nom`, `description`) VALUES
(1, 'ADMIN', 'Administrateur', 'Accès complet à toute l’’application'),
(2, 'SHOWROOM', 'Commercial Showroom', 'Gestion des visiteurs, devis et ventes en showroom'),
(3, 'TERRAIN', 'Commercial Terrain', 'Prospection terrain, devis et ventes terrain'),
(4, 'MAGASINIER', 'Magasinier', 'Gestion des stocks, livraisons, ruptures'),
(5, 'CAISSIER', 'Caissier', 'Journal de caisse et encaissements'),
(6, 'DIRECTION', 'Direction', 'Consultation des reportings et indicateurs globaux');

-- --------------------------------------------------------

--
-- Table structure for table `role_permission`
--

CREATE TABLE `role_permission` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permission`
--

INSERT INTO `role_permission` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(2, 1),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(3, 1),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 10),
(3, 11),
(4, 1),
(4, 3),
(4, 10),
(5, 10),
(5, 13),
(5, 14),
(5, 18),
(6, 1),
(6, 5),
(6, 7),
(6, 10),
(6, 13),
(6, 16),
(6, 17),
(6, 18);

-- --------------------------------------------------------

--
-- Table structure for table `ruptures_stock`
--

CREATE TABLE `ruptures_stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_rapport` date NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `seuil_alerte` int(11) NOT NULL,
  `stock_actuel` int(11) NOT NULL,
  `impact_commercial` text NOT NULL,
  `action_proposee` text NOT NULL,
  `magasinier_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `satisfaction_clients`
--

CREATE TABLE `satisfaction_clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_satisfaction` date NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `nom_client` varchar(150) NOT NULL,
  `service_utilise` enum('SHOWROOM','HOTEL','FORMATION','TERRAIN','DIGITAL') NOT NULL,
  `note` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sous_categories_produits`
--

CREATE TABLE `sous_categories_produits` (
  `id` int(10) UNSIGNED NOT NULL,
  `famille_id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sous_categories_produits`
--

INSERT INTO `sous_categories_produits` (`id`, `famille_id`, `nom`) VALUES
(1, 1, 'Chambres à coucher'),
(2, 1, 'Salons'),
(3, 2, 'Quincaillerie standard'),
(4, 3, 'Machines de découpe'),
(5, 4, 'Panneaux mélaminés');

-- --------------------------------------------------------

--
-- Table structure for table `stocks_mouvements`
--

CREATE TABLE `stocks_mouvements` (
  `id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `date_mouvement` datetime NOT NULL DEFAULT current_timestamp(),
  `type_mouvement` enum('ENTREE','SORTIE','AJUSTEMENT') NOT NULL,
  `quantite` int(11) NOT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stocks_mouvements`
--

INSERT INTO `stocks_mouvements` (`id`, `produit_id`, `date_mouvement`, `type_mouvement`, `quantite`, `source_type`, `source_id`, `commentaire`, `utilisateur_id`) VALUES
(1, 1, '2025-11-17 17:25:20', 'ENTREE', 5, 'INVENTAIRE', NULL, 'Stock initial lit 2 places', 1),
(2, 2, '2025-11-17 17:25:20', 'ENTREE', 3, 'INVENTAIRE', NULL, 'Stock initial salon', 1),
(3, 3, '2025-11-17 17:25:20', 'ENTREE', 50, 'INVENTAIRE', NULL, 'Stock initial visserie', 1),
(4, 4, '2025-11-17 17:25:20', 'ENTREE', 30, 'INVENTAIRE', NULL, 'Stock initial panneaux', 1);

-- --------------------------------------------------------

--
-- Table structure for table `types_client`
--

CREATE TABLE `types_client` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `libelle` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `types_client`
--

INSERT INTO `types_client` (`id`, `code`, `libelle`) VALUES
(1, 'SHOWROOM', 'Client / prospect showroom'),
(2, 'TERRAIN', 'Client / prospect terrain'),
(3, 'DIGITAL', 'Client issu du digital (réseaux sociaux, site, CRM)'),
(4, 'HOTEL', 'Client hébergement / hôtel'),
(5, 'FORMATION', 'Apprenant / client formation');

-- --------------------------------------------------------

--
-- Table structure for table `upsell_hotel`
--

CREATE TABLE `upsell_hotel` (
  `id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `service_additionnel` varchar(150) NOT NULL,
  `montant` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(10) UNSIGNED NOT NULL,
  `login` varchar(100) NOT NULL,
  `mot_de_passe_hash` varchar(255) NOT NULL,
  `nom_complet` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_derniere_connexion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `login`, `mot_de_passe_hash`, `nom_complet`, `email`, `telephone`, `actif`, `date_creation`, `date_derniere_connexion`) VALUES
(1, 'admin', '$2b$10$j6YYUX.QLOxOoBn9eB4rJu8/ye4/NOEXPvRjcYhUY4mBiaZZFUrTi', 'Administrateur KMS', 'admin@kms.local', NULL, 1, '2025-11-17 17:19:55', '2025-11-17 17:37:35');

-- --------------------------------------------------------

--
-- Table structure for table `utilisateur_role`
--

CREATE TABLE `utilisateur_role` (
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `utilisateur_role`
--

INSERT INTO `utilisateur_role` (`utilisateur_id`, `role_id`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `ventes`
--

CREATE TABLE `ventes` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL,
  `date_vente` date NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `canal_vente_id` int(10) UNSIGNED NOT NULL,
  `devis_id` int(10) UNSIGNED DEFAULT NULL,
  `statut` enum('EN_ATTENTE_LIVRAISON','LIVREE','ANNULEE','PARTIELLEMENT_LIVREE') NOT NULL DEFAULT 'EN_ATTENTE_LIVRAISON',
  `montant_total_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_total_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `commentaires` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ventes_lignes`
--

CREATE TABLE `ventes_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `vente_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(15,2) NOT NULL,
  `remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ligne_ht` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visiteurs_hotel`
--

CREATE TABLE `visiteurs_hotel` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_visite` date NOT NULL,
  `nom_visiteur` varchar(150) NOT NULL,
  `motif` text NOT NULL,
  `service_solicite` varchar(150) NOT NULL,
  `orientation` varchar(150) DEFAULT NULL,
  `concierge_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visiteurs_showroom`
--

CREATE TABLE `visiteurs_showroom` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_visite` date NOT NULL,
  `client_nom` varchar(150) NOT NULL,
  `contact` varchar(100) NOT NULL,
  `produit_interet` text DEFAULT NULL,
  `orientation` varchar(100) DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bons_livraison`
--
ALTER TABLE `bons_livraison`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `fk_bl_vente` (`vente_id`),
  ADD KEY `fk_bl_client` (`client_id`),
  ADD KEY `fk_bl_magasinier` (`magasinier_id`),
  ADD KEY `idx_bl_date` (`date_bl`);

--
-- Indexes for table `bons_livraison_lignes`
--
ALTER TABLE `bons_livraison_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bl_lignes_bl` (`bon_livraison_id`),
  ADD KEY `fk_bl_lignes_produit` (`produit_id`);

--
-- Indexes for table `canaux_vente`
--
ALTER TABLE `canaux_vente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `chambres`
--
ALTER TABLE `chambres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_clients_type` (`type_client_id`),
  ADD KEY `idx_clients_nom` (`nom`);

--
-- Indexes for table `connexions_utilisateur`
--
ALTER TABLE `connexions_utilisateur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_connexions_utilisateur_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_connexions_utilisateur_date` (`date_connexion`);

--
-- Indexes for table `devis`
--
ALTER TABLE `devis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `fk_devis_client` (`client_id`),
  ADD KEY `fk_devis_canal` (`canal_vente_id`),
  ADD KEY `fk_devis_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_devis_date` (`date_devis`),
  ADD KEY `idx_devis_statut` (`statut`);

--
-- Indexes for table `devis_lignes`
--
ALTER TABLE `devis_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_devis_lignes_devis` (`devis_id`),
  ADD KEY `fk_devis_lignes_produit` (`produit_id`);

--
-- Indexes for table `familles_produits`
--
ALTER TABLE `familles_produits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inscriptions_formation`
--
ALTER TABLE `inscriptions_formation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inscription_client` (`client_id`),
  ADD KEY `fk_inscription_formation` (`formation_id`),
  ADD KEY `idx_inscription_date` (`date_inscription`);

--
-- Indexes for table `journal_caisse`
--
ALTER TABLE `journal_caisse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_caisse_mode_paiement` (`mode_paiement_id`),
  ADD KEY `fk_caisse_vente` (`vente_id`),
  ADD KEY `fk_caisse_reservation` (`reservation_id`),
  ADD KEY `fk_caisse_inscription` (`inscription_formation_id`),
  ADD KEY `fk_caisse_responsable` (`responsable_encaissement_id`),
  ADD KEY `idx_caisse_date` (`date_operation`);

--
-- Indexes for table `modes_paiement`
--
ALTER TABLE `modes_paiement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_produit` (`code_produit`),
  ADD KEY `fk_produits_famille` (`famille_id`),
  ADD KEY `fk_produits_sous_categorie` (`sous_categorie_id`),
  ADD KEY `fk_produits_fournisseur` (`fournisseur_id`),
  ADD KEY `idx_produits_designation` (`designation`),
  ADD KEY `idx_produits_code` (`code_produit`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promotion_produit`
--
ALTER TABLE `promotion_produit`
  ADD PRIMARY KEY (`promotion_id`,`produit_id`),
  ADD KEY `fk_promo_produit_produit` (`produit_id`);

--
-- Indexes for table `prospections_terrain`
--
ALTER TABLE `prospections_terrain`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prospections_client` (`client_id`),
  ADD KEY `fk_prospections_commercial` (`commercial_id`),
  ADD KEY `idx_prospections_date` (`date_prospection`);

--
-- Indexes for table `prospects_formation`
--
ALTER TABLE `prospects_formation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_prospect_formation_client` (`client_id`),
  ADD KEY `fk_prospect_formation_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_prospect_formation_date` (`date_prospect`);

--
-- Indexes for table `rendezvous_terrain`
--
ALTER TABLE `rendezvous_terrain`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rdv_client` (`client_id`),
  ADD KEY `fk_rdv_commercial` (`commercial_id`),
  ADD KEY `idx_rdv_date` (`date_rdv`);

--
-- Indexes for table `reservations_hotel`
--
ALTER TABLE `reservations_hotel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reservation_client` (`client_id`),
  ADD KEY `fk_reservation_chambre` (`chambre_id`),
  ADD KEY `fk_reservation_mode_paiement` (`mode_paiement_id`),
  ADD KEY `fk_reservation_concierge` (`concierge_id`),
  ADD KEY `idx_reservation_dates` (`date_debut`,`date_fin`);

--
-- Indexes for table `retours_litiges`
--
ALTER TABLE `retours_litiges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_litiges_client` (`client_id`),
  ADD KEY `fk_litiges_produit` (`produit_id`),
  ADD KEY `fk_litiges_vente` (`vente_id`),
  ADD KEY `fk_litiges_responsable` (`responsable_suivi_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permission_permission` (`permission_id`);

--
-- Indexes for table `ruptures_stock`
--
ALTER TABLE `ruptures_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ruptures_produit` (`produit_id`),
  ADD KEY `fk_ruptures_magasinier` (`magasinier_id`),
  ADD KEY `idx_ruptures_date` (`date_rapport`);

--
-- Indexes for table `satisfaction_clients`
--
ALTER TABLE `satisfaction_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_satisfaction_client` (`client_id`),
  ADD KEY `fk_satisfaction_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_satisfaction_date` (`date_satisfaction`);

--
-- Indexes for table `sous_categories_produits`
--
ALTER TABLE `sous_categories_produits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sous_categories_famille` (`famille_id`);

--
-- Indexes for table `stocks_mouvements`
--
ALTER TABLE `stocks_mouvements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mouvements_produit` (`produit_id`),
  ADD KEY `fk_mouvements_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_mouvements_date` (`date_mouvement`);

--
-- Indexes for table `types_client`
--
ALTER TABLE `types_client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `upsell_hotel`
--
ALTER TABLE `upsell_hotel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_upsell_reservation` (`reservation_id`);

--
-- Indexes for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- Indexes for table `utilisateur_role`
--
ALTER TABLE `utilisateur_role`
  ADD PRIMARY KEY (`utilisateur_id`,`role_id`),
  ADD KEY `fk_utilisateur_role_role` (`role_id`);

--
-- Indexes for table `ventes`
--
ALTER TABLE `ventes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `fk_ventes_client` (`client_id`),
  ADD KEY `fk_ventes_canal` (`canal_vente_id`),
  ADD KEY `fk_ventes_devis` (`devis_id`),
  ADD KEY `fk_ventes_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_ventes_date` (`date_vente`),
  ADD KEY `idx_ventes_statut` (`statut`);

--
-- Indexes for table `ventes_lignes`
--
ALTER TABLE `ventes_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ventes_lignes_vente` (`vente_id`),
  ADD KEY `fk_ventes_lignes_produit` (`produit_id`);

--
-- Indexes for table `visiteurs_hotel`
--
ALTER TABLE `visiteurs_hotel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_visiteurs_hotel_concierge` (`concierge_id`),
  ADD KEY `idx_visiteurs_hotel_date` (`date_visite`);

--
-- Indexes for table `visiteurs_showroom`
--
ALTER TABLE `visiteurs_showroom`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_visiteurs_client` (`client_id`),
  ADD KEY `fk_visiteurs_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_visiteurs_date` (`date_visite`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bons_livraison`
--
ALTER TABLE `bons_livraison`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bons_livraison_lignes`
--
ALTER TABLE `bons_livraison_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `canaux_vente`
--
ALTER TABLE `canaux_vente`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chambres`
--
ALTER TABLE `chambres`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `connexions_utilisateur`
--
ALTER TABLE `connexions_utilisateur`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `devis`
--
ALTER TABLE `devis`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devis_lignes`
--
ALTER TABLE `devis_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `familles_produits`
--
ALTER TABLE `familles_produits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fournisseurs`
--
ALTER TABLE `fournisseurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inscriptions_formation`
--
ALTER TABLE `inscriptions_formation`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_caisse`
--
ALTER TABLE `journal_caisse`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modes_paiement`
--
ALTER TABLE `modes_paiement`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `produits`
--
ALTER TABLE `produits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prospections_terrain`
--
ALTER TABLE `prospections_terrain`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prospects_formation`
--
ALTER TABLE `prospects_formation`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rendezvous_terrain`
--
ALTER TABLE `rendezvous_terrain`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations_hotel`
--
ALTER TABLE `reservations_hotel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `retours_litiges`
--
ALTER TABLE `retours_litiges`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ruptures_stock`
--
ALTER TABLE `ruptures_stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `satisfaction_clients`
--
ALTER TABLE `satisfaction_clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sous_categories_produits`
--
ALTER TABLE `sous_categories_produits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stocks_mouvements`
--
ALTER TABLE `stocks_mouvements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `types_client`
--
ALTER TABLE `types_client`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `upsell_hotel`
--
ALTER TABLE `upsell_hotel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ventes_lignes`
--
ALTER TABLE `ventes_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visiteurs_hotel`
--
ALTER TABLE `visiteurs_hotel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visiteurs_showroom`
--
ALTER TABLE `visiteurs_showroom`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bons_livraison`
--
ALTER TABLE `bons_livraison`
  ADD CONSTRAINT `fk_bl_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bl_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bl_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `bons_livraison_lignes`
--
ALTER TABLE `bons_livraison_lignes`
  ADD CONSTRAINT `fk_bl_lignes_bl` FOREIGN KEY (`bon_livraison_id`) REFERENCES `bons_livraison` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bl_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_type` FOREIGN KEY (`type_client_id`) REFERENCES `types_client` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `connexions_utilisateur`
--
ALTER TABLE `connexions_utilisateur`
  ADD CONSTRAINT `fk_connexions_utilisateur_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `devis`
--
ALTER TABLE `devis`
  ADD CONSTRAINT `fk_devis_canal` FOREIGN KEY (`canal_vente_id`) REFERENCES `canaux_vente` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devis_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devis_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `devis_lignes`
--
ALTER TABLE `devis_lignes`
  ADD CONSTRAINT `fk_devis_lignes_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_devis_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `inscriptions_formation`
--
ALTER TABLE `inscriptions_formation`
  ADD CONSTRAINT `fk_inscription_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscription_formation` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `journal_caisse`
--
ALTER TABLE `journal_caisse`
  ADD CONSTRAINT `fk_caisse_inscription` FOREIGN KEY (`inscription_formation_id`) REFERENCES `inscriptions_formation` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caisse_mode_paiement` FOREIGN KEY (`mode_paiement_id`) REFERENCES `modes_paiement` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caisse_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations_hotel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caisse_responsable` FOREIGN KEY (`responsable_encaissement_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_caisse_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `fk_produits_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles_produits` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produits_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produits_sous_categorie` FOREIGN KEY (`sous_categorie_id`) REFERENCES `sous_categories_produits` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `promotion_produit`
--
ALTER TABLE `promotion_produit`
  ADD CONSTRAINT `fk_promo_produit_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_promo_produit_promo` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prospections_terrain`
--
ALTER TABLE `prospections_terrain`
  ADD CONSTRAINT `fk_prospections_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prospections_commercial` FOREIGN KEY (`commercial_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `prospects_formation`
--
ALTER TABLE `prospects_formation`
  ADD CONSTRAINT `fk_prospect_formation_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prospect_formation_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `rendezvous_terrain`
--
ALTER TABLE `rendezvous_terrain`
  ADD CONSTRAINT `fk_rdv_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rdv_commercial` FOREIGN KEY (`commercial_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `reservations_hotel`
--
ALTER TABLE `reservations_hotel`
  ADD CONSTRAINT `fk_reservation_chambre` FOREIGN KEY (`chambre_id`) REFERENCES `chambres` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservation_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservation_concierge` FOREIGN KEY (`concierge_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservation_mode_paiement` FOREIGN KEY (`mode_paiement_id`) REFERENCES `modes_paiement` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `retours_litiges`
--
ALTER TABLE `retours_litiges`
  ADD CONSTRAINT `fk_litiges_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_litiges_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_litiges_responsable` FOREIGN KEY (`responsable_suivi_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_litiges_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD CONSTRAINT `fk_role_permission_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_role_permission_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ruptures_stock`
--
ALTER TABLE `ruptures_stock`
  ADD CONSTRAINT `fk_ruptures_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruptures_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `satisfaction_clients`
--
ALTER TABLE `satisfaction_clients`
  ADD CONSTRAINT `fk_satisfaction_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_satisfaction_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `sous_categories_produits`
--
ALTER TABLE `sous_categories_produits`
  ADD CONSTRAINT `fk_sous_categories_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles_produits` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `stocks_mouvements`
--
ALTER TABLE `stocks_mouvements`
  ADD CONSTRAINT `fk_mouvements_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvements_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `upsell_hotel`
--
ALTER TABLE `upsell_hotel`
  ADD CONSTRAINT `fk_upsell_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations_hotel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `utilisateur_role`
--
ALTER TABLE `utilisateur_role`
  ADD CONSTRAINT `fk_utilisateur_role_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utilisateur_role_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ventes`
--
ALTER TABLE `ventes`
  ADD CONSTRAINT `fk_ventes_canal` FOREIGN KEY (`canal_vente_id`) REFERENCES `canaux_vente` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventes_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventes_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `ventes_lignes`
--
ALTER TABLE `ventes_lignes`
  ADD CONSTRAINT `fk_ventes_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventes_lignes_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `visiteurs_hotel`
--
ALTER TABLE `visiteurs_hotel`
  ADD CONSTRAINT `fk_visiteurs_hotel_concierge` FOREIGN KEY (`concierge_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `visiteurs_showroom`
--
ALTER TABLE `visiteurs_showroom`
  ADD CONSTRAINT `fk_visiteurs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_visiteurs_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- ==========================================================
--  PERMISSION : gestion des utilisateurs (ADMIN uniquement)
-- ==========================================================

INSERT INTO permissions (code, description) VALUES
 ('UTILISATEURS_GERER', 'Créer, modifier et désactiver les utilisateurs internes');

-- Associer la permission UTILISATEURS_GERER au rôle ADMIN
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
  ON p.code = 'UTILISATEURS_GERER'
WHERE r.code = 'ADMIN';
