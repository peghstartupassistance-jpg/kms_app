-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 12, 2025 at 07:16 PM
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
-- Table structure for table `achats`
--

CREATE TABLE `achats` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL,
  `date_achat` date NOT NULL,
  `fournisseur_nom` varchar(255) DEFAULT NULL,
  `fournisseur_contact` varchar(255) DEFAULT NULL,
  `montant_total_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_total_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `statut` varchar(30) NOT NULL DEFAULT 'EN_COURS',
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `commentaires` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `achats`
--

INSERT INTO `achats` (`id`, `numero`, `date_achat`, `fournisseur_nom`, `fournisseur_contact`, `montant_total_ht`, `montant_total_ttc`, `statut`, `utilisateur_id`, `commentaires`, `date_creation`) VALUES
(1, 'ACH-20251121-162559', '2025-11-21', 'China.com', '+235555555', 9000.00, 9000.00, 'EN_COURS', 1, NULL, '2025-11-21 16:25:59'),
(2, 'AC-20251126-170544', '2025-11-26', 'SORA', '+235555556', 1250000.00, 1250000.00, 'VALIDE', 1, NULL, '2025-11-26 17:05:44'),
(3, 'AC-20251202-154014', '2025-12-02', 'SORA', '+235555556', 1250000.00, 1250000.00, 'EN_COURS', 1, NULL, '2025-12-02 15:40:14');

-- --------------------------------------------------------

--
-- Table structure for table `achats_lignes`
--

CREATE TABLE `achats_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `achat_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite` decimal(15,3) NOT NULL DEFAULT 0.000,
  `prix_unitaire` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remise` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_ligne_ht` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `achats_lignes`
--

INSERT INTO `achats_lignes` (`id`, `achat_id`, `produit_id`, `quantite`, `prix_unitaire`, `remise`, `montant_ligne_ht`) VALUES
(7, 1, 1, 5.000, 2000.00, 1000.00, 9000.00),
(8, 2, 2, 25.000, 50000.00, 0.00, 1250000.00),
(9, 3, 3, 25.000, 50000.00, 0.00, 1250000.00);

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

--
-- Dumping data for table `bons_livraison`
--

INSERT INTO `bons_livraison` (`id`, `numero`, `date_bl`, `vente_id`, `client_id`, `transport_assure_par`, `observations`, `signe_client`, `magasinier_id`) VALUES
(1, 'BL-20251118-123629', '2025-11-18', 2, 2, NULL, NULL, 1, 1),
(2, 'BL-20251118-123739', '2025-11-18', 1, 3, NULL, NULL, 0, 1),
(3, 'BL-20251118-140008', '2025-11-18', 3, 5, NULL, NULL, 0, 1),
(4, 'BL-20251118-151854', '2025-11-18', 4, 5, NULL, NULL, 0, 1),
(5, 'BL-20251120-122339', '2025-11-20', 16, 2, NULL, NULL, 0, 1),
(6, 'BL-20251121-112346', '2025-11-21', 17, 6, NULL, NULL, 1, 1);

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

--
-- Dumping data for table `bons_livraison_lignes`
--

INSERT INTO `bons_livraison_lignes` (`id`, `bon_livraison_id`, `produit_id`, `quantite`) VALUES
(1, 1, 4, 2),
(2, 2, 2, 1),
(3, 3, 3, 2),
(4, 4, 3, 2),
(5, 5, 4, 1),
(6, 6, 1, 4),
(7, 6, 4, 15);

-- --------------------------------------------------------

--
-- Table structure for table `caisse_journal`
--

CREATE TABLE `caisse_journal` (
  `id` int(11) NOT NULL,
  `date_ecriture` datetime NOT NULL,
  `sens` enum('ENTREE','SORTIE') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `caisse_journal`
--

INSERT INTO `caisse_journal` (`id`, `date_ecriture`, `sens`, `montant`, `source_type`, `source_id`, `commentaire`, `utilisateur_id`) VALUES
(1, '2025-12-11 13:40:06', 'ENTREE', 89437.50, 'VENTE', 20, 'Vente ', 1),
(2, '2025-12-12 15:11:25', 'ENTREE', 1001700.00, 'VENTE', 21, 'Vente V-20251212-151125', 1);

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
(1, 'Client Showroom Test', 1, '+237650000001', 'client.showroom@test.local', 'Douala', 'Showroom', 'CLIENT', '2025-11-18 11:00:22'),
(2, 'Client Terrain Test', 2, '+237650000002', 'client.terrain@test.local', 'Bonabéri', 'Terrain', 'PROSPECT', '2025-11-18 11:00:22'),
(3, 'Client Digital Test', 3, '+237650000003', 'client.digital@test.local', 'Yaoundé', 'Facebook', 'CLIENT', '2025-11-18 11:00:22'),
(4, 'Client Hôtel Test', 4, '+237650000004', 'client.hotel@test.local', 'Douala', 'Réservation directe', 'HOTE', '2025-11-18 11:00:22'),
(5, 'Apprenant Formation', 5, '+237650000005', 'apprenant@test.local', 'Bafoussam', 'WhatsApp', 'APPRENANT', '2025-11-18 11:00:22'),
(6, 'romy', 5, '695657613', 'cm@kennemulti-services.com', NULL, 'facebook', 'PROSPECT', '2025-11-20 09:02:31');

-- --------------------------------------------------------

--
-- Table structure for table `compta_comptes`
--

CREATE TABLE `compta_comptes` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_compte` varchar(20) NOT NULL,
  `libelle` varchar(150) NOT NULL,
  `classe` char(1) NOT NULL,
  `est_analytique` tinyint(1) DEFAULT 0,
  `compte_parent_id` int(10) UNSIGNED DEFAULT NULL,
  `type_compte` enum('ACTIF','PASSIF','CHARGE','PRODUIT','MIXTE','ANALYTIQUE') DEFAULT 'ACTIF',
  `nature` enum('CREANCE','DETTE','STOCK','IMMOBILISATION','TRESORERIE','VENTE','CHARGE_VARIABLE','CHARGE_FIXE','AUTRE') DEFAULT 'AUTRE',
  `est_actif` tinyint(1) DEFAULT 1,
  `observations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_comptes`
--

INSERT INTO `compta_comptes` (`id`, `numero_compte`, `libelle`, `classe`, `est_analytique`, `compte_parent_id`, `type_compte`, `nature`, `est_actif`, `observations`, `created_at`, `updated_at`) VALUES
(1, '1', 'Immobilisations', '1', 0, NULL, 'ACTIF', 'IMMOBILISATION', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(2, '2', 'Stocks', '2', 0, NULL, 'ACTIF', 'STOCK', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(3, '3', 'Tiers', '3', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(4, '4', 'Capitaux', '4', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(5, '5', 'Resultats', '5', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(6, '6', 'Charges', '6', 0, NULL, 'CHARGE', 'CHARGE_VARIABLE', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(7, '7', 'Produits', '7', 0, NULL, 'PRODUIT', 'VENTE', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(8, '8', 'Speciaux', '8', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(9, '411', 'Clients', '4', 0, NULL, 'ACTIF', 'CREANCE', 1, NULL, '2025-12-10 15:28:25', '2025-12-11 16:15:37'),
(10, '707', 'Ventes de marchandises', '7', 0, NULL, 'PRODUIT', 'VENTE', 1, NULL, '2025-12-10 15:28:25', '2025-12-10 15:28:25'),
(11, '401', 'Fournisseurs', '4', 0, NULL, 'PASSIF', 'DETTE', 1, NULL, '2025-12-10 15:46:34', '2025-12-11 16:15:37'),
(12, '607', 'Achats de marchandises', '6', 0, NULL, 'CHARGE', 'CHARGE_VARIABLE', 1, NULL, '2025-12-10 15:46:34', '2025-12-10 15:46:34'),
(15, '110', 'Réserves', '1', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(16, '150', 'Provisions', '1', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(17, '200', 'Amortissements', '1', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(18, '301', 'Matières premières', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(19, '512', 'Banque', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:14:24'),
(20, '571', 'Caisse siège social', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 15:45:09'),
(21, '601', 'Achats de matières premières', '6', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(22, '608', 'Frais de transport', '6', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(23, '622', 'Rémunérations du personnel', '6', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(24, '631', 'Impôts et taxes', '6', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(25, '701', 'Ventes de produits finis', '7', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(26, '708', 'Revenus annexes', '7', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(27, '10', 'Capital', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(28, '11', 'Réserves', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(29, '12', 'Report à nouveau', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(30, '13', 'Résultat net de l\'exercice', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(31, '14', 'Subventions d\'investissement', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(32, '15', 'Provisions réglementées', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(33, '16', 'Emprunts et dettes assimilées', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(34, '17', 'Dettes de location-acquisition', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(35, '18', 'Dettes liées à des participations', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(36, '19', 'Provisions financières pour risques et charges', '1', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(37, '20', 'Charges immobilisées', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(38, '21', 'Immobilisations incorporelles', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(39, '22', 'Terrains', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(40, '23', 'Bâtiments, installations techniques et agencements', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(41, '24', 'Matériel, mobilier et actifs biologiques', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(42, '25', 'Avances et acomptes versés sur immobilisations', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(43, '26', 'Titres de participation', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(44, '27', 'Autres immobilisations financières', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(45, '28', 'Amortissements', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(46, '29', 'Provisions pour dépréciation des immobilisations', '2', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(47, '31', 'Marchandises', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(48, '32', 'Matières premières et fournitures liées', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(49, '33', 'Autres approvisionnements', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(50, '34', 'Produits en cours', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(51, '35', 'Services en cours', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(52, '36', 'Produits finis', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(53, '37', 'Produits intermédiaires et résiduels', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(54, '38', 'Stocks en cours de route, en consignation ou en dépôt', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(55, '39', 'Dépréciations des stocks', '3', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(56, '40', 'Fournisseurs et comptes rattachés', '4', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(57, '41', 'Clients et comptes rattachés', '4', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(58, '42', 'Personnel', '4', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(59, '43', 'Organismes sociaux', '4', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(60, '44', 'État et collectivités publiques', '4', 0, NULL, 'MIXTE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(61, '45', 'Organismes internationaux', '4', 0, NULL, 'MIXTE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(62, '46', 'Associés et groupe', '4', 0, NULL, 'MIXTE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(63, '47', 'Débiteurs et créditeurs divers', '4', 0, NULL, 'MIXTE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(64, '48', 'Créances et dettes hors activités ordinaires', '4', 0, NULL, 'MIXTE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(65, '49', 'Dépréciations et risques provisionnés', '4', 0, NULL, 'MIXTE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(66, '50', 'Titres de placement', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(67, '51', 'Valeurs à encaisser', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(68, '52', 'Banques, établissements financiers et assimilés', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(69, '53', 'Établissements financiers et assimilés', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(70, '54', 'Instruments de trésorerie', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(71, '56', 'Crédits de trésorerie', '5', 0, NULL, 'PASSIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(72, '57', 'Caisse', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(73, '58', 'Régies d\'avances, accréditifs et virements internes', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(74, '59', 'Dépréciations et risques provisionnés', '5', 0, NULL, 'ACTIF', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(75, '60', 'Achats et variations de stocks', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(76, '61', 'Transports', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(77, '62', 'Services extérieurs A', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(78, '63', 'Autres services extérieurs B', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(79, '64', 'Impôts et taxes', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(80, '65', 'Autres charges', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(81, '66', 'Charges de personnel', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(82, '67', 'Frais financiers et charges assimilées', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(83, '68', 'Dotations aux amortissements', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(84, '69', 'Dotations aux provisions', '6', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(85, '70', 'Ventes', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(86, '71', 'Subventions d\'exploitation', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(87, '72', 'Production immobilisée', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(88, '73', 'Variations des stocks de biens et de services produits', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(89, '75', 'Autres produits', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(90, '77', 'Revenus financiers et produits assimilés', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(91, '78', 'Transferts de charges', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(92, '79', 'Reprises de provisions', '7', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(93, '81', 'Valeurs comptables des cessions d\'immobilisations', '8', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(94, '82', 'Produits des cessions d\'immobilisations', '8', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(95, '83', 'Charges hors activités ordinaires', '8', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(96, '84', 'Produits hors activités ordinaires', '8', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(97, '85', 'Dotations hors activités ordinaires', '8', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(98, '86', 'Reprises hors activités ordinaires', '8', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(99, '87', 'Participations des travailleurs', '8', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(100, '88', 'Subventions d\'équilibre', '8', 0, NULL, 'PRODUIT', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(101, '89', 'Impôts sur le résultat', '8', 0, NULL, 'CHARGE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(102, '90', 'Engagements donnés ou reçus', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(103, '91', 'Contrepartie des engagements', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(104, '92', 'Comptes réfléchis du bilan', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(105, '93', 'Comptes réfléchis de gestion', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(106, '94', 'Comptes de stocks', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(107, '95', 'Comptes de coûts', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(108, '96', 'Comptes d\'écarts sur coûts', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(109, '97', 'Comptes de résultats analytiques', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(110, '98', 'Comptes de liaisons internes', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56'),
(111, '99', 'Comptes de l\'activité', '9', 0, NULL, 'ANALYTIQUE', 'AUTRE', 1, NULL, '2025-12-11 15:26:56', '2025-12-11 15:26:56');

-- --------------------------------------------------------

--
-- Table structure for table `compta_ecritures`
--

CREATE TABLE `compta_ecritures` (
  `id` int(10) UNSIGNED NOT NULL,
  `piece_id` int(10) UNSIGNED NOT NULL,
  `compte_id` int(10) UNSIGNED NOT NULL,
  `libelle_ecriture` varchar(200) DEFAULT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `tiers_client_id` int(10) UNSIGNED DEFAULT NULL,
  `tiers_fournisseur_id` int(10) UNSIGNED DEFAULT NULL,
  `centre_analytique_id` int(10) UNSIGNED DEFAULT NULL,
  `ordre_ligne` int(11) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_ecritures`
--

INSERT INTO `compta_ecritures` (`id`, `piece_id`, `compte_id`, `libelle_ecriture`, `debit`, `credit`, `tiers_client_id`, `tiers_fournisseur_id`, `centre_analytique_id`, `ordre_ligne`, `observations`, `created_at`) VALUES
(1, 1, 9, 'Client facture V-20251118-114131', 238500.00, 0.00, 3, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(2, 1, 10, 'Vente produits facture V-20251118-114131', 0.00, 238500.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(3, 2, 9, 'Client facture V-20251118-122137', 1788742.85, 0.00, 2, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(4, 2, 10, 'Vente produits facture V-20251118-122137', 0.00, 1788742.85, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(5, 3, 9, 'Client facture V-20251118-135949', 50000.00, 0.00, 5, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(6, 3, 10, 'Vente produits facture V-20251118-135949', 0.00, 50000.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(7, 4, 9, 'Client facture V-20251118-151825', 50000.00, 0.00, 5, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(8, 4, 10, 'Vente produits facture V-20251118-151825', 0.00, 50000.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(9, 5, 9, 'Client facture V-20251120-122303', 38000.00, 0.00, 2, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(10, 5, 10, 'Vente produits facture V-20251120-122303', 0.00, 38000.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(11, 6, 9, 'Client facture V-20251121-112325', 1568137.50, 0.00, 6, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(12, 6, 10, 'Vente produits facture V-20251121-112325', 0.00, 1568137.50, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(13, 7, 9, 'Client facture V-20251126-154749', 429300.00, 0.00, 6, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(14, 7, 10, 'Vente produits facture V-20251126-154749', 0.00, 429300.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(15, 8, 9, 'Client facture V-20251126-170324', 89437.50, 0.00, 2, NULL, NULL, 1, NULL, '2025-12-10 15:32:08'),
(16, 8, 10, 'Vente produits facture V-20251126-170324', 0.00, 89437.50, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:32:08'),
(17, 9, 12, 'Achat articles facture ACH-20251121-162559', 9000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-10 15:49:48'),
(18, 9, 11, 'Fournisseur facture ACH-20251121-162559', 0.00, 9000.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:49:48'),
(19, 10, 12, 'Achat articles facture AC-20251126-170544', 1250000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-10 15:49:48'),
(20, 10, 11, 'Fournisseur facture AC-20251126-170544', 0.00, 1250000.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:49:48'),
(21, 11, 12, 'Achat articles facture AC-20251202-154014', 1250000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-10 15:49:48'),
(22, 11, 11, 'Fournisseur facture AC-20251202-154014', 0.00, 1250000.00, NULL, NULL, NULL, 2, NULL, '2025-12-10 15:49:48'),
(23, 12, 2, 'Stock initial valorisé', 9485000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:08:27'),
(24, 12, 28, 'Stock initial valorisé', 0.00, 9485000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:08:27'),
(25, 13, 9, 'Vente mobilier décoration', 3500000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:03'),
(26, 13, 25, 'Vente mobilier décoration', 0.00, 3500000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:03'),
(27, 14, 9, 'Vente accessoires', 2100000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:03'),
(28, 14, 25, 'Vente accessoires', 0.00, 2100000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:03'),
(29, 15, 9, 'Vente panneaux', 1850000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(30, 15, 25, 'Vente panneaux', 0.00, 1850000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(31, 16, 12, 'Achat matières premières', 1500000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(32, 16, 11, 'Achat matières premières', 0.00, 1500000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(33, 17, 12, 'Achat accessoires', 900000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(34, 17, 11, 'Achat accessoires', 0.00, 900000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(35, 18, 19, 'Paiement fournisseurs', 2509000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(36, 18, 11, 'Paiement fournisseurs', 0.00, 2509000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(37, 19, 19, 'Encaissement clients', 3000000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(38, 19, 9, 'Encaissement clients', 0.00, 3000000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(39, 20, 23, 'Salaires décembre', 450000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(40, 20, 20, 'Salaires décembre', 0.00, 450000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(41, 21, 23, 'Frais de transport', 150000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:04'),
(42, 21, 20, 'Frais de transport', 0.00, 150000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:04'),
(45, 23, 19, 'Encaissement partiel clients', 2000000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:46'),
(46, 23, 9, 'Encaissement partiel clients', 0.00, 2000000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:46'),
(47, 24, 19, 'Encaissement clients', 1500000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:10:46'),
(48, 24, 9, 'Encaissement clients', 0.00, 1500000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:10:46'),
(51, 22, 19, 'Capital social apporté', 10000000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:11:30'),
(52, 22, 27, 'Capital social apporté', 0.00, 10000000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:11:30'),
(53, 25, 19, 'Solde initial banque', 2000000.00, 0.00, NULL, NULL, NULL, 1, NULL, '2025-12-11 05:11:30'),
(54, 25, 28, 'Solde initial banque', 0.00, 2000000.00, NULL, NULL, NULL, 2, NULL, '2025-12-11 05:11:30'),
(55, 26, 9, 'Client facture V-20251126-170324', 89437.50, 0.00, 2, NULL, NULL, 1, NULL, '2025-12-11 12:40:06'),
(56, 26, 10, 'Vente produits facture V-20251126-170324', 0.00, 89437.50, NULL, NULL, NULL, 2, NULL, '2025-12-11 12:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `compta_exercices`
--

CREATE TABLE `compta_exercices` (
  `id` int(10) UNSIGNED NOT NULL,
  `annee` int(11) NOT NULL,
  `date_ouverture` date NOT NULL,
  `date_cloture` date DEFAULT NULL,
  `est_clos` tinyint(1) DEFAULT 0,
  `observations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_exercices`
--

INSERT INTO `compta_exercices` (`id`, `annee`, `date_ouverture`, `date_cloture`, `est_clos`, `observations`, `created_at`, `updated_at`) VALUES
(1, 2024, '2024-01-01', NULL, 0, 'Exercice 2024', '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(2, 2025, '2025-01-01', NULL, 0, 'Exercice 2025', '2025-12-10 13:32:46', '2025-12-10 13:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `compta_journaux`
--

CREATE TABLE `compta_journaux` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `type` enum('VENTE','ACHAT','TRESORERIE','OPERATION_DIVERSE','PAIE') DEFAULT 'OPERATION_DIVERSE',
  `compte_contre_partie` int(10) UNSIGNED DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_journaux`
--

INSERT INTO `compta_journaux` (`id`, `code`, `libelle`, `type`, `compte_contre_partie`, `observations`, `created_at`, `updated_at`) VALUES
(1, 'VE', 'Ventes', 'VENTE', NULL, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(2, 'AC', 'Achats', 'ACHAT', NULL, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(3, 'TR', 'Tresorerie', 'TRESORERIE', NULL, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(4, 'OD', 'Operations Diverses', 'OPERATION_DIVERSE', NULL, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46'),
(5, 'PA', 'Paie', 'PAIE', NULL, NULL, '2025-12-10 13:32:46', '2025-12-10 13:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `compta_mapping_operations`
--

CREATE TABLE `compta_mapping_operations` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `code_operation` varchar(50) NOT NULL,
  `journal_id` int(10) UNSIGNED NOT NULL,
  `compte_debit_id` int(10) UNSIGNED DEFAULT NULL,
  `compte_credit_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_mapping_operations`
--

INSERT INTO `compta_mapping_operations` (`id`, `source_type`, `code_operation`, `journal_id`, `compte_debit_id`, `compte_credit_id`, `description`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'VENTE', 'VENTE_PRODUITS', 1, 9, 10, 'Ecritures vente standard', 1, '2025-12-10 15:31:08', '2025-12-10 15:31:08'),
(2, 'ACHAT', 'ACHAT_STOCK', 2, 12, 11, 'Ecritures achat standard', 1, '2025-12-10 15:46:34', '2025-12-10 15:46:34');

-- --------------------------------------------------------

--
-- Table structure for table `compta_operations_trace`
--

CREATE TABLE `compta_operations_trace` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `source_id` int(10) UNSIGNED NOT NULL,
  `piece_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('success','error','en_attente') DEFAULT 'en_attente',
  `messages` text DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_operations_trace`
--

INSERT INTO `compta_operations_trace` (`id`, `source_type`, `source_id`, `piece_id`, `status`, `messages`, `executed_at`, `created_at`) VALUES
(1, 'VENTE', 1, 1, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(2, 'VENTE', 2, 2, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(3, 'VENTE', 3, 3, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(4, 'VENTE', 4, 4, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(5, 'VENTE', 16, 5, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(6, 'VENTE', 17, 6, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(7, 'VENTE', 19, 7, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(8, 'VENTE', 20, 26, 'success', 'Mapping VENTE/VENTE_PRODUITS non configuré', NULL, '2025-12-10 15:24:17'),
(17, 'ACHAT', 1, 9, 'success', NULL, NULL, '2025-12-10 15:49:48'),
(18, 'ACHAT', 2, 10, 'success', NULL, NULL, '2025-12-10 15:49:48'),
(19, 'ACHAT', 3, 11, 'success', NULL, NULL, '2025-12-10 15:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `compta_pieces`
--

CREATE TABLE `compta_pieces` (
  `id` int(10) UNSIGNED NOT NULL,
  `exercice_id` int(10) UNSIGNED NOT NULL,
  `journal_id` int(10) UNSIGNED NOT NULL,
  `numero_piece` varchar(50) NOT NULL,
  `date_piece` date NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `tiers_client_id` int(10) UNSIGNED DEFAULT NULL,
  `tiers_fournisseur_id` int(10) UNSIGNED DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `est_validee` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compta_pieces`
--

INSERT INTO `compta_pieces` (`id`, `exercice_id`, `journal_id`, `numero_piece`, `date_piece`, `reference_type`, `reference_id`, `tiers_client_id`, `tiers_fournisseur_id`, `observations`, `est_validee`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'XX-2025-00001', '2025-11-18', 'VENTE', 1, 3, NULL, 'Facture vente n° V-20251118-114131', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:14'),
(2, 2, 1, 'VE-2025-00002', '2025-11-18', 'VENTE', 2, 2, NULL, 'Facture vente n° V-20251118-122137', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:14'),
(3, 2, 1, 'VE-2025-00003', '2025-11-18', 'VENTE', 3, 5, NULL, 'Facture vente n° V-20251118-135949', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:15'),
(4, 2, 1, 'VE-2025-00004', '2025-11-18', 'VENTE', 4, 5, NULL, 'Facture vente n° V-20251118-151825', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:15'),
(5, 2, 1, 'VE-2025-00005', '2025-11-20', 'VENTE', 16, 2, NULL, 'Facture vente n° V-20251120-122303', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:15'),
(6, 2, 1, 'VE-2025-00006', '2025-11-21', 'VENTE', 17, 6, NULL, 'Facture vente n° V-20251121-112325', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:16'),
(7, 2, 1, 'VE-2025-00007', '2025-11-26', 'VENTE', 19, 6, NULL, 'Facture vente n° V-20251126-154749', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:16'),
(8, 2, 1, 'VE-2025-00008', '2025-11-26', 'VENTE', 20, 2, NULL, 'Facture vente n° V-20251126-170324', 1, '2025-12-10 15:32:08', '2025-12-11 04:58:16'),
(9, 2, 2, 'XX-2025-00001', '2025-11-21', 'ACHAT', 1, NULL, NULL, 'Facture achat n° ACH-20251121-162559', 1, '2025-12-10 15:49:48', '2025-12-11 04:58:16'),
(10, 2, 2, 'AC-2025-00002', '2025-11-26', 'ACHAT', 2, NULL, NULL, 'Facture achat n° AC-20251126-170544', 1, '2025-12-10 15:49:48', '2025-12-11 04:58:16'),
(11, 2, 2, 'AC-2025-00003', '2025-12-02', 'ACHAT', 3, NULL, NULL, 'Facture achat n° AC-20251202-154014', 1, '2025-12-10 15:49:48', '2025-12-11 04:58:16'),
(12, 2, 4, 'INV-2025-00001', '2025-12-11', NULL, NULL, NULL, NULL, 'Stock initial valorisé', 1, '2025-12-11 05:07:51', '2025-12-11 05:08:59'),
(13, 2, 1, 'VE-2025-00009', '2025-12-05', NULL, NULL, NULL, NULL, 'Vente mobilier décoration', 1, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(14, 2, 1, 'VE-2025-00010', '2025-12-06', NULL, NULL, NULL, NULL, 'Vente accessoires', 1, '2025-12-11 05:10:03', '2025-12-11 05:10:03'),
(15, 2, 1, 'VE-2025-00011', '2025-12-07', NULL, NULL, NULL, NULL, 'Vente panneaux', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(16, 2, 2, 'AC-2025-00004', '2025-12-03', NULL, NULL, NULL, NULL, 'Achat matières premières', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(17, 2, 2, 'AC-2025-00005', '2025-12-04', NULL, NULL, NULL, NULL, 'Achat accessoires', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(18, 2, 3, 'TR-2025-00001', '2025-12-05', NULL, NULL, NULL, NULL, 'Paiement fournisseurs', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(19, 2, 3, 'TR-2025-00002', '2025-12-08', NULL, NULL, NULL, NULL, 'Encaissement clients', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(20, 2, 4, 'CH-2025-00001', '2025-12-06', NULL, NULL, NULL, NULL, 'Salaires décembre', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(21, 2, 4, 'CH-2025-00002', '2025-12-08', NULL, NULL, NULL, NULL, 'Frais de transport', 1, '2025-12-11 05:10:04', '2025-12-11 05:10:04'),
(22, 2, 4, 'CAP-2025-00001', '2025-01-01', NULL, NULL, NULL, NULL, 'Capital social initial', 1, '2025-12-11 05:10:46', '2025-12-11 05:10:46'),
(23, 2, 3, 'TR-2025-00003', '2025-12-09', NULL, NULL, NULL, NULL, 'Encaissement partiel clients', 1, '2025-12-11 05:10:46', '2025-12-11 05:10:46'),
(24, 2, 3, 'TR-2025-00004', '2025-12-10', NULL, NULL, NULL, NULL, 'Encaissement clients', 1, '2025-12-11 05:10:46', '2025-12-11 05:10:46'),
(25, 2, 3, 'BNQ-2025-00001', '2025-01-01', NULL, NULL, NULL, NULL, 'Solde initial banque', 1, '2025-12-11 05:10:46', '2025-12-11 05:10:46'),
(26, 2, 1, 'VE-2025-00012', '2025-11-26', 'VENTE', 20, 2, NULL, 'Facture vente n° V-20251126-170324', 0, '2025-12-11 12:40:06', '2025-12-11 12:40:06');

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
(0, 1, '2025-12-10 23:12:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(1, 1, '2025-11-18 11:14:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(2, 1, '2025-11-18 11:30:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(3, 1, '2025-11-18 11:30:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(4, 1, '2025-11-18 11:47:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(5, 1, '2025-11-18 12:08:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(6, 1, '2025-11-18 14:28:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(7, 1, '2025-11-18 14:59:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(8, 1, '2025-11-18 15:16:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(9, 1, '2025-11-19 09:43:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(10, 1, '2025-11-19 10:07:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(11, 1, '2025-11-20 09:17:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(12, 1, '2025-11-20 11:07:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(13, 1, '2025-11-21 11:09:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(14, 1, '2025-11-21 13:10:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(15, 1, '2025-11-21 15:31:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(16, 1, '2025-11-26 14:35:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(17, 1, '2025-11-27 09:21:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 0),
(18, 1, '2025-11-27 09:21:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(19, 1, '2025-12-02 15:31:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(20, 1, '2025-12-02 15:44:39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 1),
(21, 1, '2025-12-06 12:30:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(22, 1, '2025-12-09 10:45:31', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 1),
(23, 1, '2025-12-09 15:56:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 0),
(24, 1, '2025-12-09 15:56:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(25, 1, '2025-12-10 10:51:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(26, 1, '2025-12-10 14:44:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(27, 1, '2025-12-10 14:53:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(28, 1, '2025-12-10 15:26:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(29, 1, '2025-12-11 09:54:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(30, 1, '2025-12-11 11:33:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(31, 1, '2025-12-11 11:48:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(32, 1, '2025-12-11 12:49:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(33, 1, '2025-12-11 12:51:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(34, 1, '2025-12-12 15:03:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1),
(35, 1, '2025-12-12 15:26:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', 1);

-- --------------------------------------------------------

--
-- Table structure for table `conversions_pipeline`
--

CREATE TABLE `conversions_pipeline` (
  `id` int(10) UNSIGNED NOT NULL,
  `source_type` enum('SHOWROOM','TERRAIN','DIGITAL') NOT NULL,
  `source_id` int(10) UNSIGNED NOT NULL COMMENT 'ID visiteur/prospection/lead',
  `client_id` int(10) UNSIGNED NOT NULL,
  `date_conversion` datetime NOT NULL DEFAULT current_timestamp(),
  `canal_vente_id` int(10) UNSIGNED DEFAULT NULL,
  `devis_id` int(10) UNSIGNED DEFAULT NULL,
  `vente_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `est_converti` tinyint(1) NOT NULL DEFAULT 0,
  `date_relance` date DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `montant_total_ht` decimal(15,2) NOT NULL DEFAULT 0.00,
  `montant_total_ttc` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remise_global` decimal(15,2) NOT NULL DEFAULT 0.00,
  `conditions` text DEFAULT NULL,
  `commentaires` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `devis`
--

INSERT INTO `devis` (`id`, `numero`, `date_devis`, `client_id`, `canal_vente_id`, `statut`, `est_converti`, `date_relance`, `utilisateur_id`, `montant_total_ht`, `montant_total_ttc`, `remise_global`, `conditions`, `commentaires`) VALUES
(1, 'DV-20251118-135909', '2025-11-18', 5, 3, 'ACCEPTE', 1, NULL, 1, 50000.00, 50000.00, 0.00, NULL, NULL),
(2, 'DV-20251120-105327', '2025-11-20', 2, 3, 'ACCEPTE', 1, NULL, 1, 38000.00, 38000.00, 0.00, NULL, NULL),
(3, 'DV-20251120-120352', '2025-11-20', 5, 3, 'ACCEPTE', 1, '2025-11-24', 1, 75995.00, 75995.00, 0.00, NULL, NULL),
(4, 'DV-20251120-122248', '2025-11-20', 2, 3, 'ACCEPTE', 1, NULL, 1, 38000.00, 38000.00, 0.00, NULL, NULL),
(5, 'DV-20251121-112258', '2025-11-21', 6, 1, 'ACCEPTE', 1, '2025-11-27', 1, 1290000.00, 1290000.00, 0.00, NULL, NULL);

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

--
-- Dumping data for table `devis_lignes`
--

INSERT INTO `devis_lignes` (`id`, `devis_id`, `produit_id`, `quantite`, `prix_unitaire`, `remise`, `montant_ligne_ht`) VALUES
(3, 1, 3, 2, 25000.00, 0.00, 50000.00),
(6, 2, 4, 1, 38000.00, 0.00, 38000.00),
(9, 3, 4, 1, 38000.00, 5.00, 37995.00),
(10, 3, 4, 1, 38000.00, 0.00, 38000.00),
(12, 4, 4, 1, 38000.00, 0.00, 38000.00),
(15, 5, 1, 4, 180000.00, 0.00, 720000.00),
(16, 5, 4, 15, 38000.00, 0.00, 570000.00);

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

--
-- Dumping data for table `inscriptions_formation`
--

INSERT INTO `inscriptions_formation` (`id`, `date_inscription`, `apprenant_nom`, `client_id`, `formation_id`, `montant_paye`, `solde_du`) VALUES
(1, '2025-11-19', 'Martial', NULL, 2, 50000.00, 130000.00),
(2, '2025-11-19', 'Tendop', 3, 2, 150000.00, 30000.00),
(3, '2025-11-20', 'Nkolo', NULL, 1, 80000.00, 70000.00);

-- --------------------------------------------------------

--
-- Table structure for table `journal_caisse`
--

CREATE TABLE `journal_caisse` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_operation` date NOT NULL,
  `numero_piece` varchar(50) NOT NULL,
  `nature_operation` text NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `fournisseur_id` int(10) UNSIGNED DEFAULT NULL,
  `sens` enum('RECETTE','DEPENSE') NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `mode_paiement_id` int(10) UNSIGNED NOT NULL,
  `vente_id` int(10) UNSIGNED DEFAULT NULL,
  `reservation_id` int(10) UNSIGNED DEFAULT NULL,
  `inscription_formation_id` int(10) UNSIGNED DEFAULT NULL,
  `responsable_encaissement_id` int(10) UNSIGNED NOT NULL,
  `observations` text DEFAULT NULL,
  `est_annule` tinyint(1) NOT NULL DEFAULT 0,
  `date_annulation` datetime DEFAULT NULL,
  `annule_par_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `journal_caisse`
--

INSERT INTO `journal_caisse` (`id`, `date_operation`, `numero_piece`, `nature_operation`, `client_id`, `fournisseur_id`, `sens`, `montant`, `mode_paiement_id`, `vente_id`, `reservation_id`, `inscription_formation_id`, `responsable_encaissement_id`, `observations`, `est_annule`, `date_annulation`, `annule_par_id`) VALUES
(1, '2025-11-18', 'RES-1', 'Encaissement réservation hôtel', NULL, NULL, 'RECETTE', 35000.00, 4, NULL, 1, NULL, 1, '', 0, NULL, NULL),
(2, '2025-11-18', '011', 'règlement fournissuer', NULL, NULL, 'RECETTE', 10000.00, 1, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL),
(3, '2025-11-19', 'INSCR-1', 'Encaissement inscription formation', NULL, NULL, 'RECETTE', 50000.00, 3, NULL, NULL, 1, 1, NULL, 0, NULL, NULL),
(4, '2025-11-20', '5', 'règlement fournissuer', NULL, NULL, 'RECETTE', 10000.00, 4, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL),
(5, '2025-11-20', '', 'sorepco', NULL, NULL, 'RECETTE', 100000.00, 4, NULL, NULL, NULL, 1, 'recouvrement', 1, '2025-11-20 18:53:38', 1),
(6, '2025-11-20', '', 'versement mupeci', NULL, NULL, 'RECETTE', 1000000.00, 4, NULL, NULL, NULL, 1, 'recouvrement', 0, NULL, NULL),
(7, '2025-11-21', '', 'recouvrement sorepco', NULL, NULL, 'RECETTE', 150000.00, 4, NULL, NULL, NULL, 1, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `kpis_quotidiens`
--

CREATE TABLE `kpis_quotidiens` (
  `id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `canal` enum('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION','GLOBAL') NOT NULL,
  `nb_visiteurs` int(11) DEFAULT 0,
  `nb_leads` int(11) DEFAULT 0,
  `nb_devis` int(11) DEFAULT 0,
  `nb_ventes` int(11) DEFAULT 0,
  `ca_realise` decimal(15,2) DEFAULT 0.00,
  `date_maj` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads_digital`
--

CREATE TABLE `leads_digital` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_lead` date NOT NULL,
  `nom_prospect` varchar(150) NOT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `source` enum('FACEBOOK','INSTAGRAM','WHATSAPP','SITE_WEB','TIKTOK','LINKEDIN','AUTRE') NOT NULL DEFAULT 'FACEBOOK',
  `message_initial` text DEFAULT NULL,
  `produit_interet` varchar(255) DEFAULT NULL,
  `statut` enum('NOUVEAU','CONTACTE','QUALIFIE','DEVIS_ENVOYE','CONVERTI','PERDU') NOT NULL DEFAULT 'NOUVEAU',
  `score_prospect` int(11) DEFAULT 0 COMMENT 'Score 0-100 selon intérêt/qualité',
  `date_dernier_contact` datetime DEFAULT NULL,
  `prochaine_action` varchar(255) DEFAULT NULL,
  `date_prochaine_action` date DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Rempli après conversion',
  `utilisateur_responsable_id` int(10) UNSIGNED DEFAULT NULL,
  `campagne` varchar(150) DEFAULT NULL COMMENT 'Nom de la campagne publicitaire',
  `cout_acquisition` decimal(15,2) DEFAULT 0.00 COMMENT 'Coût pub si applicable',
  `observations` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `date_conversion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Leads digitaux (Facebook, Instagram, WhatsApp, etc.)';

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
-- Table structure for table `mouvements_stock_backup_20251209_161710`
--

CREATE TABLE `mouvements_stock_backup_20251209_161710` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_mouvement` date NOT NULL,
  `type_mouvement` enum('ENTREE','SORTIE','CORRECTION') NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(11) NOT NULL,
  `source_module` varchar(50) DEFAULT NULL,
  `source_id` int(10) UNSIGNED DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `commentaire` varchar(255) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mouvements_stock_backup_20251209_161710`
--

INSERT INTO `mouvements_stock_backup_20251209_161710` (`id`, `date_mouvement`, `type_mouvement`, `produit_id`, `quantite`, `source_module`, `source_id`, `utilisateur_id`, `commentaire`, `date_creation`) VALUES
(1, '2025-11-21', 'ENTREE', 1, 22, 'ACHAT', 55222, NULL, NULL, '2025-11-21 12:50:59'),
(3, '2025-11-26', 'SORTIE', 3, 3, 'VENTE', 20, NULL, 'Sortie suite à la vente V-20251126-170324', '2025-11-26 17:04:15'),
(4, '2025-11-26', 'ENTREE', 2, 25, 'ACHAT', 2, NULL, 'Entrée suite à l’achat AC-20251126-170544', '2025-11-26 17:05:44'),
(5, '2025-12-02', 'ENTREE', 3, 25, 'ACHAT', 3, NULL, 'Entrée suite à l’achat AC-20251202-154014', '2025-12-02 15:40:14');

-- --------------------------------------------------------

--
-- Table structure for table `objectifs_commerciaux`
--

CREATE TABLE `objectifs_commerciaux` (
  `id` int(10) UNSIGNED NOT NULL,
  `annee` int(11) NOT NULL,
  `mois` int(11) DEFAULT NULL COMMENT 'NULL = objectif annuel',
  `canal` enum('SHOWROOM','TERRAIN','DIGITAL','HOTEL','FORMATION','GLOBAL') NOT NULL DEFAULT 'GLOBAL',
  `objectif_ca` decimal(15,2) NOT NULL DEFAULT 0.00,
  `objectif_nb_ventes` int(11) DEFAULT NULL,
  `objectif_nb_leads` int(11) DEFAULT NULL,
  `realise_ca` decimal(15,2) DEFAULT 0.00,
  `realise_nb_ventes` int(11) DEFAULT 0,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ordres_preparation`
--

CREATE TABLE `ordres_preparation` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_ordre` varchar(50) NOT NULL,
  `date_ordre` date NOT NULL,
  `vente_id` int(10) UNSIGNED DEFAULT NULL,
  `devis_id` int(10) UNSIGNED DEFAULT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `type_commande` enum('VENTE_SHOWROOM','VENTE_TERRAIN','VENTE_DIGITAL','RESERVATION_HOTEL','AUTRE') DEFAULT 'VENTE_SHOWROOM',
  `commercial_responsable_id` int(10) UNSIGNED NOT NULL,
  `statut` enum('EN_ATTENTE','EN_PREPARATION','PRET','LIVRE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE',
  `date_preparation_demandee` date DEFAULT NULL,
  `priorite` enum('NORMALE','URGENTE','TRES_URGENTE') DEFAULT 'NORMALE',
  `observations` text DEFAULT NULL,
  `signature_resp_marketing` tinyint(1) DEFAULT 0 COMMENT 'Validation RESP MARKETING',
  `date_signature_marketing` datetime DEFAULT NULL,
  `magasinier_id` int(10) UNSIGNED DEFAULT NULL,
  `date_preparation_effectuee` datetime DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordres de préparation (liaison marketing-magasin)';

--
-- Dumping data for table `ordres_preparation`
--

INSERT INTO `ordres_preparation` (`id`, `numero_ordre`, `date_ordre`, `vente_id`, `devis_id`, `client_id`, `type_commande`, `commercial_responsable_id`, `statut`, `date_preparation_demandee`, `priorite`, `observations`, `signature_resp_marketing`, `date_signature_marketing`, `magasinier_id`, `date_preparation_effectuee`, `date_creation`) VALUES
(4, 'OP-20251211-0001', '2025-12-11', 18, NULL, 6, 'VENTE_SHOWROOM', 1, 'EN_ATTENTE', '2025-12-13', 'NORMALE', '', 0, NULL, NULL, NULL, '2025-12-11 13:08:40'),
(5, 'OP-20251212-0001', '2025-12-12', 13, NULL, 2, 'VENTE_SHOWROOM', 1, 'EN_ATTENTE', '2025-12-13', 'NORMALE', '', 0, NULL, NULL, NULL, '2025-12-12 15:06:08');

-- --------------------------------------------------------

--
-- Table structure for table `ordres_preparation_lignes`
--

CREATE TABLE `ordres_preparation_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `ordre_preparation_id` int(10) UNSIGNED NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `quantite_demandee` decimal(15,3) NOT NULL,
  `quantite_preparee` decimal(15,3) DEFAULT 0.000,
  `observations` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(18, 'REPORTING_LIRE', 'Accéder aux tableaux de bord et reporting'),
(19, 'SATISFACTION_GERER', 'Gérer les enquêtes de satisfaction client'),
(20, 'ACHATS_GERER', 'Gérer les achats et approvisionnements'),
(21, 'COMPTABILITE_LIRE', 'Consulter le module comptabilité'),
(22, 'COMPTABILITE_ECRIRE', 'Enregistrer des écritures comptables'),
(23, 'UTILISATEURS_GERER', NULL);

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
(1, 'MEU-CH-001', 1, 1, 'Lit 2 places avec chevets', 'Dimensions 160x200', 'Lit moderne pour chambre parentale', 1, 'Showroom Douala', 120000.00, 180000.00, 21, 2, '/assets/img/produits/MEU-CH-001.png', 1, '2025-11-18 11:00:22', '2025-12-02 15:58:23'),
(2, 'MEU-SAL-001', 1, 2, 'Salon 5 places', 'Structure bois, mousse haute densité', 'Salon complet 3+1+1', 1, 'Showroom Douala', 200000.00, 280000.00, 27, 1, NULL, 1, '2025-11-18 11:00:22', NULL),
(3, 'ACC-VIS-001', 2, 3, 'Lot de visserie menuiserie', 'Assortiment vis bois', 'Accessoires pour montage de meubles', 2, 'Magasin PK12', 15000.00, 25000.00, 68, 10, NULL, 1, '2025-11-18 11:00:22', NULL),
(4, 'PAN-MEL-001', 4, 5, 'Panneau mélaminé blanc 18mm', '2,75m x 1,83m', 'Panneau pour agencement intérieur', 2, 'Magasin PK12', 25000.00, 38000.00, 12, 5, NULL, 1, '2025-11-18 11:00:22', NULL),
(15, 'PAN-FOR-001', 2, 3, 'Panneau formica blanc 18mm', NULL, NULL, 2, 'Magasin PK12', 500.00, 2000.00, 10, 2, '/assets/img/produits/PAN-FOR-001.png', 1, '2025-12-06 12:32:22', NULL),
(16, 'PAN-MDF', 4, 5, 'Panneau MDFblanc 18mm', NULL, NULL, 2, 'Magasin PK12', 10000.00, 500000.00, 24, 2, '/kms_app/assets/img/produits/PAN-MDF.jpg', 1, '2025-12-10 11:45:00', NULL),
(17, 'TEST-PRD-001', 1, NULL, 'Produit test automatisÃ©', NULL, NULL, NULL, NULL, 0.00, 1500.00, 3, 0, NULL, 0, '2025-12-10 13:09:46', NULL);

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
  `heure_prospection` time DEFAULT NULL,
  `prospect_nom` varchar(150) NOT NULL,
  `secteur` varchar(150) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `adresse_gps` varchar(500) DEFAULT NULL,
  `besoin_identifie` text NOT NULL,
  `action_menee` text NOT NULL,
  `resultat` text NOT NULL,
  `prochaine_etape` text DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `commercial_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `prospections_terrain`
--

INSERT INTO `prospections_terrain` (`id`, `date_prospection`, `heure_prospection`, `prospect_nom`, `secteur`, `latitude`, `longitude`, `adresse_gps`, `besoin_identifie`, `action_menee`, `resultat`, `prochaine_etape`, `client_id`, `commercial_id`) VALUES
(1, '2025-12-11', '12:26:30', 'MR Yves', 'Pindo', 4.05880337, 9.78497912, 'Pindo, Douala III, Communauté urbaine de Douala, Wouri, Région du Littoral, Cameroun', 'Deligneuse', 'Prospection et remise de la fiche produit', 'Intéressé - À recontacter', 'relancer', NULL, 1),
(2, '2025-12-12', '15:17:00', 'Zoboo', 'Ndogmbe', 4.04000000, 9.75000000, 'Ndogmbe, Douala III, Communauté urbaine de Douala, Wouri, Littoral, Cameroon', 'machines de ménuiserie', 'prospection et prise de rendez-vous au centre commercial', 'À rappeler plus tard', 'Relancer dans une semaine', NULL, 1),
(3, '2025-12-12', '15:24:38', 'Kossi', 'Non renseigné', 4.04000000, 9.75000000, NULL, 'efezfe', 'fezfzeefd', 'Devis demandé', 'zerfzfze', NULL, 1);

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

--
-- Dumping data for table `prospects_formation`
--

INSERT INTO `prospects_formation` (`id`, `date_prospect`, `nom_prospect`, `contact`, `source`, `statut_actuel`, `client_id`, `utilisateur_id`) VALUES
(1, '2025-11-01', 'Anicet Mballa', '655585502', 'facebook', 'En cours', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `relances_devis`
--

CREATE TABLE `relances_devis` (
  `id` int(10) UNSIGNED NOT NULL,
  `devis_id` int(10) UNSIGNED NOT NULL,
  `date_relance` date NOT NULL,
  `type_relance` enum('TELEPHONE','EMAIL','SMS','WHATSAPP','VISITE') NOT NULL DEFAULT 'TELEPHONE',
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `commentaires` text DEFAULT NULL,
  `prochaine_action` varchar(255) DEFAULT NULL,
  `date_prochaine_action` date DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
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

--
-- Dumping data for table `reservations_hotel`
--

INSERT INTO `reservations_hotel` (`id`, `date_reservation`, `client_id`, `chambre_id`, `date_debut`, `date_fin`, `nb_nuits`, `montant_total`, `statut`, `mode_paiement_id`, `concierge_id`) VALUES
(1, '2025-11-18', 5, 2, '2025-11-18', '2025-11-18', 1, 35000.00, 'EN_COURS', 4, 1),
(2, '2025-11-18', 4, 2, '2025-11-18', '2025-11-20', 2, 70000.00, 'EN_COURS', NULL, 1);

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
  `type_probleme` enum('DEFAUT_PRODUIT','ERREUR_LIVRAISON','INSATISFACTION_CLIENT','AUTRE') DEFAULT 'AUTRE',
  `responsable_suivi_id` int(10) UNSIGNED NOT NULL,
  `statut_traitement` enum('EN_COURS','RESOLU','ABANDONNE') NOT NULL DEFAULT 'EN_COURS',
  `solution` text DEFAULT NULL,
  `montant_rembourse` decimal(15,2) DEFAULT 0.00,
  `montant_avoir` decimal(15,2) DEFAULT 0.00,
  `date_resolution` datetime DEFAULT NULL
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
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(2, 1),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 19),
(3, 1),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 10),
(3, 11),
(3, 19),
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
(6, 18),
(6, 19),
(6, 23);

-- --------------------------------------------------------

--
-- Table structure for table `ruptures_signalees`
--

CREATE TABLE `ruptures_signalees` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_signalement` date NOT NULL,
  `produit_id` int(10) UNSIGNED NOT NULL,
  `seuil_alerte` decimal(15,3) NOT NULL,
  `stock_actuel` decimal(15,3) NOT NULL,
  `impact_commercial` text DEFAULT NULL COMMENT 'Ventes perdues, clients mécontents, etc.',
  `action_proposee` text DEFAULT NULL COMMENT 'Réappro urgent, promotion, produit alternatif',
  `magasinier_id` int(10) UNSIGNED NOT NULL,
  `statut_traitement` enum('SIGNALE','EN_COURS','RESOLU','ABANDONNE') DEFAULT 'SIGNALE',
  `date_resolution` datetime DEFAULT NULL,
  `commentaire_resolution` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertes ruptures stock (magasin → marketing)';

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

--
-- Dumping data for table `satisfaction_clients`
--

INSERT INTO `satisfaction_clients` (`id`, `date_satisfaction`, `client_id`, `nom_client`, `service_utilise`, `note`, `commentaire`, `utilisateur_id`) VALUES
(1, '2025-11-19', NULL, 'apprenant', 'FORMATION', 4, '', 1),
(2, '2025-11-20', 4, 'Client Hôtel Test', 'FORMATION', 2, 'grincheux et deçu', 1);

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
(1, 1, '2025-11-18 11:00:22', 'ENTREE', 5, 'INVENTAIRE', NULL, 'Stock initial lit 2 places', 1),
(2, 2, '2025-11-18 11:00:22', 'ENTREE', 3, 'INVENTAIRE', NULL, 'Stock initial salon', 1),
(3, 3, '2025-11-18 11:00:22', 'ENTREE', 50, 'INVENTAIRE', NULL, 'Stock initial visserie', 1),
(4, 4, '2025-11-18 11:00:22', 'ENTREE', 30, 'INVENTAIRE', NULL, 'Stock initial panneaux', 1),
(5, 4, '2025-11-18 12:36:29', 'SORTIE', 2, 'VENTE', 2, 'Sortie via BL BL-20251118-123629', 1),
(6, 2, '2025-11-18 12:37:39', 'SORTIE', 1, 'VENTE', 1, 'Sortie via BL BL-20251118-123739', 1),
(7, 3, '2025-11-18 14:00:08', 'SORTIE', 2, 'VENTE', 3, 'Sortie via BL BL-20251118-140008', 1),
(8, 3, '2025-11-18 15:18:54', 'SORTIE', 2, 'VENTE', 4, 'Sortie via BL BL-20251118-151854', 1),
(9, 4, '2025-11-20 12:23:39', 'SORTIE', 1, 'VENTE', 16, 'Sortie via BL BL-20251120-122339', 1),
(10, 1, '2025-11-21 11:23:46', 'SORTIE', 4, 'VENTE', 17, 'Sortie via BL BL-20251121-112346', 1),
(11, 4, '2025-11-21 11:23:46', 'SORTIE', 15, 'VENTE', 17, 'Sortie via BL BL-20251121-112346', 1),
(12, 1, '2025-12-06 12:30:51', 'ENTREE', 2, 'AJUSTEMENT', NULL, 'Ajustement manuel depuis fiche produit', 1),
(13, 1, '2025-12-06 12:31:08', 'SORTIE', 2, 'AJUSTEMENT', NULL, 'Ajustement manuel depuis fiche produit', 1),
(14, 15, '2025-12-06 12:32:22', 'ENTREE', 10, 'INVENTAIRE', NULL, 'Stock initial à la création du produit', 1),
(15, 1, '2025-11-21 12:50:59', 'ENTREE', 22, 'ACHAT', 55222, NULL, 1),
(17, 2, '2025-11-26 17:05:44', 'ENTREE', 25, 'ACHAT', 2, 'Entrée suite à l’achat AC-20251126-170544', 1),
(18, 3, '2025-12-02 15:40:14', 'ENTREE', 25, 'ACHAT', 3, 'Entrée suite à l’achat AC-20251202-154014', 1),
(19, 1, '2025-12-10 11:43:34', 'SORTIE', 1, 'AJUSTEMENT', NULL, 'Ajustement manuel depuis fiche produit', 1),
(20, 1, '2025-12-10 11:43:43', 'SORTIE', 1, 'AJUSTEMENT', NULL, 'Ajustement manuel depuis fiche produit', 1),
(21, 16, '2025-12-10 11:45:00', 'ENTREE', 25, 'INVENTAIRE', NULL, 'Stock initial à la création du produit', 1),
(22, 16, '2025-12-10 11:45:31', 'SORTIE', 1, 'AJUSTEMENT', NULL, 'Ajustement manuel depuis fiche produit', 1),
(27, 17, '2025-12-10 13:09:46', 'ENTREE', 5, 'INVENTAIRE', NULL, 'Stock initial à la création du produit', 1),
(28, 17, '2025-12-10 13:09:46', 'SORTIE', 2, 'AJUSTEMENT', NULL, 'Ajustement manuel depuis fiche produit', 1),
(29, 3, '2025-11-26 00:00:00', 'SORTIE', 3, 'VENTE', 20, 'Sortie suite à la vente V-20251126-170324', 1);

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
(1, 'admin', '$2b$10$j6YYUX.QLOxOoBn9eB4rJu8/ye4/NOEXPvRjcYhUY4mBiaZZFUrTi', 'Administrateur KMS', 'admin@kms.local', NULL, 1, '2025-11-18 10:59:28', '2025-12-12 15:26:26'),
(2, 'admin2', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Administrateur Système', 'admin2@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(3, 'showroom1', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Marie Kouadio', 'marie.kouadio@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(4, 'showroom2', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Yao Kouassi', 'yao.kouassi@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(5, 'terrain1', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Konan Yao', 'konan.yao@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(6, 'terrain2', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Aya N\'Guessan', 'aya.nguessan@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(7, 'magasin1', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Ibrahim Traoré', 'ibrahim.traore@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(8, 'magasin2', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Moussa Diallo', 'moussa.diallo@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(9, 'caisse1', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Aminata Koné', 'aminata.kone@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(10, 'caisse2', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Fatou Camara', 'fatou.camara@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(11, 'direction1', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Directeur Général', 'dg@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(12, 'direction2', '$2y$10$G6sGiMHX75v9PYTAqIZCPObMQV.3InGlXpNGyrKWKK/gM8iln0Tfu', 'Directeur Adjoint', 'da@kms.local', NULL, 1, '2025-12-11 11:56:20', NULL),
(13, 'Tatiana', '$2y$10$PI9HMfk.ET49yrr31htsKOHMhnZSNaITlwbcbcL5lJawUzejgOm7a', 'Naoussi Tatiana', 'naoussitatiana@gmail.com', '695657613', 1, '2025-12-11 12:07:02', NULL),
(14, 'Gislaine', '$2y$10$WwVYPLCm6FFKjE/CY4QLh.sN1gc3y2J3KsgHoLGh9u33r/b72mHKW', 'Gislaine', NULL, NULL, 1, '2025-12-11 12:09:27', NULL);

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
(1, 1),
(2, 1),
(3, 2),
(4, 2),
(5, 3),
(6, 3),
(7, 4),
(8, 4),
(9, 5),
(10, 5),
(11, 6),
(12, 6),
(13, 2),
(13, 3),
(13, 4);

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

--
-- Dumping data for table `ventes`
--

INSERT INTO `ventes` (`id`, `numero`, `date_vente`, `client_id`, `canal_vente_id`, `devis_id`, `statut`, `montant_total_ht`, `montant_total_ttc`, `utilisateur_id`, `commentaires`) VALUES
(1, 'V-20251118-114131', '2025-11-18', 3, 3, NULL, 'LIVREE', 200000.00, 238500.00, 1, NULL),
(2, 'V-20251118-122137', '2025-11-18', 2, 2, NULL, 'LIVREE', 1499994.00, 1788742.85, 1, NULL),
(3, 'V-20251118-135949', '2025-11-18', 5, 3, 1, 'LIVREE', 50000.00, 50000.00, 1, 'Vente issue du devis DV-20251118-135909'),
(4, 'V-20251118-151825', '2025-11-18', 5, 3, 1, 'LIVREE', 50000.00, 50000.00, 1, 'Vente issue du devis DV-20251118-135909'),
(5, 'V-20251118-155819', '2025-11-18', 5, 3, NULL, 'EN_ATTENTE_LIVRAISON', 50000.00, 59625.00, 1, NULL),
(6, 'V-20251120-105356', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(7, 'V-20251120-112512', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(8, 'V-20251120-112647', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(9, 'V-20251120-112839', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(10, 'V-20251120-112933', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(11, 'V-20251120-112955', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(12, 'V-20251120-113037', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(13, 'V-20251120-114844', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(14, 'V-20251120-115447', '2025-11-20', 2, 3, 2, 'EN_ATTENTE_LIVRAISON', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-105327'),
(15, 'V-20251120-120452', '2025-11-20', 5, 3, 3, 'EN_ATTENTE_LIVRAISON', 75995.00, 75995.00, 1, 'Vente issue du devis DV-20251120-120352'),
(16, 'V-20251120-122303', '2025-11-20', 2, 3, 4, 'LIVREE', 38000.00, 38000.00, 1, 'Vente issue du devis DV-20251120-122248'),
(17, 'V-20251121-112325', '2025-11-21', 6, 1, 5, 'LIVREE', 1315000.00, 1568137.50, 1, 'Vente issue du devis DV-20251121-112258'),
(18, 'V-20251126-154658', '2025-11-26', 6, 1, NULL, 'EN_ATTENTE_LIVRAISON', 180000.00, 214650.00, 1, NULL),
(19, 'V-20251126-154749', '2025-11-26', 6, 1, NULL, 'LIVREE', 360000.00, 429300.00, 1, NULL),
(20, 'V-20251126-170324', '2025-11-26', 2, 4, NULL, 'LIVREE', 75000.00, 89437.50, 1, NULL),
(21, 'V-20251212-151125', '2025-12-12', 4, 1, NULL, 'EN_ATTENTE_LIVRAISON', 840000.00, 1001700.00, 1, NULL);

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

--
-- Dumping data for table `ventes_lignes`
--

INSERT INTO `ventes_lignes` (`id`, `vente_id`, `produit_id`, `quantite`, `prix_unitaire`, `remise`, `montant_ligne_ht`) VALUES
(2, 1, 2, 1, 200000.00, 0.00, 200000.00),
(4, 2, 4, 2, 750000.00, 6.00, 1499994.00),
(5, 3, 3, 2, 25000.00, 0.00, 50000.00),
(6, 4, 3, 2, 25000.00, 0.00, 50000.00),
(7, 5, 3, 2, 25000.00, 0.00, 50000.00),
(8, 6, 4, 1, 38000.00, 0.00, 38000.00),
(9, 7, 4, 1, 38000.00, 0.00, 38000.00),
(10, 8, 4, 1, 38000.00, 0.00, 38000.00),
(11, 9, 4, 1, 38000.00, 0.00, 38000.00),
(12, 10, 4, 1, 38000.00, 0.00, 38000.00),
(13, 11, 4, 1, 38000.00, 0.00, 38000.00),
(14, 12, 4, 1, 38000.00, 0.00, 38000.00),
(15, 13, 4, 1, 38000.00, 0.00, 38000.00),
(16, 14, 4, 1, 38000.00, 0.00, 38000.00),
(17, 15, 4, 1, 38000.00, 5.00, 37995.00),
(18, 15, 4, 1, 38000.00, 0.00, 38000.00),
(19, 16, 4, 1, 38000.00, 0.00, 38000.00),
(25, 17, 1, 4, 180000.00, 0.00, 720000.00),
(26, 17, 4, 15, 38000.00, 0.00, 570000.00),
(27, 17, 3, 5, 5000.00, 0.00, 25000.00),
(28, 18, 1, 1, 180000.00, 0.00, 180000.00),
(31, 19, 1, 2, 180000.00, 0.00, 360000.00),
(34, 20, 3, 3, 25000.00, 0.00, 75000.00),
(35, 21, 2, 3, 280000.00, 0.00, 840000.00);

-- --------------------------------------------------------

--
-- Table structure for table `visiteurs_hotel`
--

CREATE TABLE `visiteurs_hotel` (
  `id` int(10) UNSIGNED NOT NULL,
  `date_visite` date NOT NULL,
  `nom_visiteur` varchar(150) NOT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
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

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pipeline_commercial`
-- (See below for the actual view)
--
CREATE TABLE `v_pipeline_commercial` (
`canal` varchar(8)
,`source_id` int(10) unsigned
,`prospect_nom` varchar(150)
,`date_entree` date
,`converti_en_devis` int(1)
,`converti_en_vente` int(1)
,`statut_pipeline` enum('NOUVEAU','CONTACTE','QUALIFIE','DEVIS_ENVOYE','CONVERTI','PERDU')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_ventes_livraison_encaissement`
-- (See below for the actual view)
--
CREATE TABLE `v_ventes_livraison_encaissement` (
`id` int(10) unsigned
,`numero` varchar(50)
,`date_vente` date
,`montant_total_ttc` decimal(15,2)
,`statut_vente` enum('EN_ATTENTE_LIVRAISON','LIVREE','ANNULEE','PARTIELLEMENT_LIVREE')
,`statut_livraison` varchar(9)
,`montant_encaisse` decimal(37,2)
,`solde_du` decimal(38,2)
);

-- --------------------------------------------------------

--
-- Structure for view `v_pipeline_commercial`
--
DROP TABLE IF EXISTS `v_pipeline_commercial`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pipeline_commercial`  AS SELECT 'SHOWROOM' AS `canal`, `vs`.`id` AS `source_id`, `vs`.`client_nom` AS `prospect_nom`, `vs`.`date_visite` AS `date_entree`, 0 AS `converti_en_devis`, 0 AS `converti_en_vente`, NULL AS `statut_pipeline` FROM `visiteurs_showroom` AS `vs`union all select 'TERRAIN' AS `canal`,`pt`.`id` AS `source_id`,`pt`.`prospect_nom` AS `prospect_nom`,`pt`.`date_prospection` AS `date_entree`,0 AS `converti_en_devis`,0 AS `converti_en_vente`,NULL AS `statut_pipeline` from `prospections_terrain` `pt` union all select 'DIGITAL' AS `canal`,`ld`.`id` AS `source_id`,`ld`.`nom_prospect` AS `prospect_nom`,`ld`.`date_lead` AS `date_entree`,`ld`.`statut` in ('DEVIS_ENVOYE','CONVERTI') AS `converti_en_devis`,`ld`.`statut` = 'CONVERTI' AS `converti_en_vente`,`ld`.`statut` AS `statut_pipeline` from `leads_digital` `ld`  ;

-- --------------------------------------------------------

--
-- Structure for view `v_ventes_livraison_encaissement`
--
DROP TABLE IF EXISTS `v_ventes_livraison_encaissement`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_ventes_livraison_encaissement`  AS SELECT `v`.`id` AS `id`, `v`.`numero` AS `numero`, `v`.`date_vente` AS `date_vente`, `v`.`montant_total_ttc` AS `montant_total_ttc`, `v`.`statut` AS `statut_vente`, CASE WHEN exists(select 1 from `bons_livraison` `bl` where `bl`.`vente_id` = `v`.`id` AND `bl`.`signe_client` = 1 limit 1) THEN 'LIVRE' ELSE 'NON_LIVRE' END AS `statut_livraison`, coalesce((select sum(`jc`.`montant`) from `journal_caisse` `jc` where `jc`.`vente_id` = `v`.`id`),0) AS `montant_encaisse`, `v`.`montant_total_ttc`- coalesce((select sum(`jc`.`montant`) from `journal_caisse` `jc` where `jc`.`vente_id` = `v`.`id`),0) AS `solde_du` FROM `ventes` AS `v` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achats`
--
ALTER TABLE `achats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_achats_utilisateur` (`utilisateur_id`);

--
-- Indexes for table `achats_lignes`
--
ALTER TABLE `achats_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_achats_lignes_achat` (`achat_id`),
  ADD KEY `fk_achats_lignes_produit` (`produit_id`);

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
-- Indexes for table `caisse_journal`
--
ALTER TABLE `caisse_journal`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `compta_comptes`
--
ALTER TABLE `compta_comptes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_compte` (`numero_compte`),
  ADD KEY `compte_parent_id` (`compte_parent_id`),
  ADD KEY `idx_numero` (`numero_compte`),
  ADD KEY `idx_classe` (`classe`),
  ADD KEY `idx_nature` (`nature`);

--
-- Indexes for table `compta_ecritures`
--
ALTER TABLE `compta_ecritures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiers_client_id` (`tiers_client_id`),
  ADD KEY `tiers_fournisseur_id` (`tiers_fournisseur_id`),
  ADD KEY `idx_compte` (`compte_id`),
  ADD KEY `idx_piece` (`piece_id`),
  ADD KEY `idx_debit_credit` (`debit`,`credit`);

--
-- Indexes for table `compta_exercices`
--
ALTER TABLE `compta_exercices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `annee` (`annee`);

--
-- Indexes for table `compta_journaux`
--
ALTER TABLE `compta_journaux`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `compte_contre_partie` (`compte_contre_partie`);

--
-- Indexes for table `compta_mapping_operations`
--
ALTER TABLE `compta_mapping_operations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mapping` (`source_type`,`code_operation`),
  ADD KEY `journal_id` (`journal_id`),
  ADD KEY `compte_debit_id` (`compte_debit_id`),
  ADD KEY `compte_credit_id` (`compte_credit_id`),
  ADD KEY `idx_source` (`source_type`,`code_operation`);

--
-- Indexes for table `compta_operations_trace`
--
ALTER TABLE `compta_operations_trace`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_trace` (`source_type`,`source_id`),
  ADD KEY `piece_id` (`piece_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `compta_pieces`
--
ALTER TABLE `compta_pieces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_piece` (`exercice_id`,`journal_id`,`numero_piece`),
  ADD KEY `journal_id` (`journal_id`),
  ADD KEY `tiers_client_id` (`tiers_client_id`),
  ADD KEY `tiers_fournisseur_id` (`tiers_fournisseur_id`),
  ADD KEY `idx_date` (`date_piece`),
  ADD KEY `idx_ref` (`reference_type`,`reference_id`);

--
-- Indexes for table `connexions_utilisateur`
--
ALTER TABLE `connexions_utilisateur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_connexions_utilisateur_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_connexions_utilisateur_date` (`date_connexion`);

--
-- Indexes for table `conversions_pipeline`
--
ALTER TABLE `conversions_pipeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversions_source` (`source_type`,`source_id`),
  ADD KEY `idx_conversions_client` (`client_id`),
  ADD KEY `idx_conversions_date` (`date_conversion`),
  ADD KEY `fk_conversions_canal` (`canal_vente_id`),
  ADD KEY `fk_conversions_devis` (`devis_id`),
  ADD KEY `fk_conversions_vente` (`vente_id`);

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
  ADD KEY `idx_caisse_date` (`date_operation`),
  ADD KEY `fk_journal_caisse_annule_par` (`annule_par_id`);

--
-- Indexes for table `kpis_quotidiens`
--
ALTER TABLE `kpis_quotidiens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_kpis_unique` (`date`,`canal`),
  ADD KEY `idx_kpis_date` (`date`);

--
-- Indexes for table `leads_digital`
--
ALTER TABLE `leads_digital`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leads_source` (`source`),
  ADD KEY `idx_leads_statut` (`statut`),
  ADD KEY `idx_leads_date` (`date_lead`),
  ADD KEY `idx_leads_prochaine_action` (`date_prochaine_action`),
  ADD KEY `fk_leads_client` (`client_id`),
  ADD KEY `fk_leads_utilisateur` (`utilisateur_responsable_id`);

--
-- Indexes for table `modes_paiement`
--
ALTER TABLE `modes_paiement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `mouvements_stock_backup_20251209_161710`
--
ALTER TABLE `mouvements_stock_backup_20251209_161710`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mouvements_stock_produit` (`produit_id`),
  ADD KEY `idx_mouvements_stock_utilisateur` (`utilisateur_id`);

--
-- Indexes for table `objectifs_commerciaux`
--
ALTER TABLE `objectifs_commerciaux`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_objectifs_unique` (`annee`,`mois`,`canal`),
  ADD KEY `idx_objectifs_periode` (`annee`,`mois`);

--
-- Indexes for table `ordres_preparation`
--
ALTER TABLE `ordres_preparation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_ordre` (`numero_ordre`),
  ADD KEY `idx_ordres_date` (`date_ordre`),
  ADD KEY `idx_ordres_statut` (`statut`),
  ADD KEY `idx_ordres_commercial` (`commercial_responsable_id`),
  ADD KEY `fk_ordres_vente` (`vente_id`),
  ADD KEY `fk_ordres_devis` (`devis_id`),
  ADD KEY `fk_ordres_client` (`client_id`),
  ADD KEY `fk_ordres_magasinier` (`magasinier_id`);

--
-- Indexes for table `ordres_preparation_lignes`
--
ALTER TABLE `ordres_preparation_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ordres_lignes_ordre` (`ordre_preparation_id`),
  ADD KEY `fk_ordres_lignes_produit` (`produit_id`);

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
-- Indexes for table `relances_devis`
--
ALTER TABLE `relances_devis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_relances_devis` (`devis_id`),
  ADD KEY `idx_relances_date` (`date_relance`),
  ADD KEY `fk_relances_utilisateur` (`utilisateur_id`);

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
-- Indexes for table `ruptures_signalees`
--
ALTER TABLE `ruptures_signalees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ruptures_date` (`date_signalement`),
  ADD KEY `idx_ruptures_produit` (`produit_id`),
  ADD KEY `idx_ruptures_statut` (`statut_traitement`),
  ADD KEY `fk_ruptures_sig_magasinier` (`magasinier_id`);

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
-- AUTO_INCREMENT for table `achats`
--
ALTER TABLE `achats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `achats_lignes`
--
ALTER TABLE `achats_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `bons_livraison`
--
ALTER TABLE `bons_livraison`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bons_livraison_lignes`
--
ALTER TABLE `bons_livraison_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `caisse_journal`
--
ALTER TABLE `caisse_journal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `compta_comptes`
--
ALTER TABLE `compta_comptes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `compta_ecritures`
--
ALTER TABLE `compta_ecritures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `compta_exercices`
--
ALTER TABLE `compta_exercices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `compta_journaux`
--
ALTER TABLE `compta_journaux`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `compta_mapping_operations`
--
ALTER TABLE `compta_mapping_operations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `compta_operations_trace`
--
ALTER TABLE `compta_operations_trace`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `compta_pieces`
--
ALTER TABLE `compta_pieces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `connexions_utilisateur`
--
ALTER TABLE `connexions_utilisateur`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `conversions_pipeline`
--
ALTER TABLE `conversions_pipeline`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devis`
--
ALTER TABLE `devis`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `devis_lignes`
--
ALTER TABLE `devis_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `journal_caisse`
--
ALTER TABLE `journal_caisse`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `kpis_quotidiens`
--
ALTER TABLE `kpis_quotidiens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads_digital`
--
ALTER TABLE `leads_digital`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `modes_paiement`
--
ALTER TABLE `modes_paiement`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `mouvements_stock_backup_20251209_161710`
--
ALTER TABLE `mouvements_stock_backup_20251209_161710`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `objectifs_commerciaux`
--
ALTER TABLE `objectifs_commerciaux`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ordres_preparation`
--
ALTER TABLE `ordres_preparation`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ordres_preparation_lignes`
--
ALTER TABLE `ordres_preparation_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `produits`
--
ALTER TABLE `produits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prospections_terrain`
--
ALTER TABLE `prospections_terrain`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `prospects_formation`
--
ALTER TABLE `prospects_formation`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `relances_devis`
--
ALTER TABLE `relances_devis`
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT for table `ruptures_signalees`
--
ALTER TABLE `ruptures_signalees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ruptures_stock`
--
ALTER TABLE `ruptures_stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `satisfaction_clients`
--
ALTER TABLE `satisfaction_clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sous_categories_produits`
--
ALTER TABLE `sous_categories_produits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stocks_mouvements`
--
ALTER TABLE `stocks_mouvements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `ventes_lignes`
--
ALTER TABLE `ventes_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

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
-- Constraints for table `achats`
--
ALTER TABLE `achats`
  ADD CONSTRAINT `fk_achats_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `achats_lignes`
--
ALTER TABLE `achats_lignes`
  ADD CONSTRAINT `fk_achats_lignes_achat` FOREIGN KEY (`achat_id`) REFERENCES `achats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_achats_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE;

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
-- Constraints for table `compta_comptes`
--
ALTER TABLE `compta_comptes`
  ADD CONSTRAINT `compta_comptes_ibfk_1` FOREIGN KEY (`compte_parent_id`) REFERENCES `compta_comptes` (`id`);

--
-- Constraints for table `compta_ecritures`
--
ALTER TABLE `compta_ecritures`
  ADD CONSTRAINT `compta_ecritures_ibfk_1` FOREIGN KEY (`piece_id`) REFERENCES `compta_pieces` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `compta_ecritures_ibfk_2` FOREIGN KEY (`compte_id`) REFERENCES `compta_comptes` (`id`),
  ADD CONSTRAINT `compta_ecritures_ibfk_3` FOREIGN KEY (`tiers_client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `compta_ecritures_ibfk_4` FOREIGN KEY (`tiers_fournisseur_id`) REFERENCES `fournisseurs` (`id`);

--
-- Constraints for table `compta_journaux`
--
ALTER TABLE `compta_journaux`
  ADD CONSTRAINT `compta_journaux_ibfk_1` FOREIGN KEY (`compte_contre_partie`) REFERENCES `compta_comptes` (`id`);

--
-- Constraints for table `compta_mapping_operations`
--
ALTER TABLE `compta_mapping_operations`
  ADD CONSTRAINT `compta_mapping_operations_ibfk_1` FOREIGN KEY (`journal_id`) REFERENCES `compta_journaux` (`id`),
  ADD CONSTRAINT `compta_mapping_operations_ibfk_2` FOREIGN KEY (`compte_debit_id`) REFERENCES `compta_comptes` (`id`),
  ADD CONSTRAINT `compta_mapping_operations_ibfk_3` FOREIGN KEY (`compte_credit_id`) REFERENCES `compta_comptes` (`id`);

--
-- Constraints for table `compta_operations_trace`
--
ALTER TABLE `compta_operations_trace`
  ADD CONSTRAINT `compta_operations_trace_ibfk_1` FOREIGN KEY (`piece_id`) REFERENCES `compta_pieces` (`id`);

--
-- Constraints for table `compta_pieces`
--
ALTER TABLE `compta_pieces`
  ADD CONSTRAINT `compta_pieces_ibfk_1` FOREIGN KEY (`exercice_id`) REFERENCES `compta_exercices` (`id`),
  ADD CONSTRAINT `compta_pieces_ibfk_2` FOREIGN KEY (`journal_id`) REFERENCES `compta_journaux` (`id`),
  ADD CONSTRAINT `compta_pieces_ibfk_3` FOREIGN KEY (`tiers_client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `compta_pieces_ibfk_4` FOREIGN KEY (`tiers_fournisseur_id`) REFERENCES `fournisseurs` (`id`);

--
-- Constraints for table `connexions_utilisateur`
--
ALTER TABLE `connexions_utilisateur`
  ADD CONSTRAINT `fk_connexions_utilisateur_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `conversions_pipeline`
--
ALTER TABLE `conversions_pipeline`
  ADD CONSTRAINT `fk_conversions_canal` FOREIGN KEY (`canal_vente_id`) REFERENCES `canaux_vente` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversions_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversions_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversions_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_caisse_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_journal_caisse_annule_par` FOREIGN KEY (`annule_par_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leads_digital`
--
ALTER TABLE `leads_digital`
  ADD CONSTRAINT `fk_leads_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_leads_utilisateur` FOREIGN KEY (`utilisateur_responsable_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `mouvements_stock_backup_20251209_161710`
--
ALTER TABLE `mouvements_stock_backup_20251209_161710`
  ADD CONSTRAINT `fk_mouvements_stock_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mouvements_stock_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `ordres_preparation`
--
ALTER TABLE `ordres_preparation`
  ADD CONSTRAINT `fk_ordres_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordres_commercial` FOREIGN KEY (`commercial_responsable_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordres_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordres_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordres_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ordres_preparation_lignes`
--
ALTER TABLE `ordres_preparation_lignes`
  ADD CONSTRAINT `fk_ordres_lignes_ordre` FOREIGN KEY (`ordre_preparation_id`) REFERENCES `ordres_preparation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordres_lignes_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Constraints for table `relances_devis`
--
ALTER TABLE `relances_devis`
  ADD CONSTRAINT `fk_relances_devis` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relances_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Constraints for table `ruptures_signalees`
--
ALTER TABLE `ruptures_signalees`
  ADD CONSTRAINT `fk_ruptures_sig_magasinier` FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruptures_sig_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
