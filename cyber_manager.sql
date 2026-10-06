-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 06 oct. 2026 à 15:23
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `cyber_manager`
--

-- --------------------------------------------------------

--
-- Structure de la table `appareils_wifi`
--

CREATE TABLE `appareils_wifi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `adresse_mac` varchar(17) NOT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `host_name` varchar(255) DEFAULT NULL,
  `nom_affichage` varchar(100) DEFAULT NULL,
  `type_appareil` enum('telephone','ordinateur','tablette','inconnu') DEFAULT NULL,
  `fabricant` varchar(255) DEFAULT NULL,
  `modele` varchar(255) DEFAULT NULL,
  `systeme` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `premiere_connexion` timestamp NULL DEFAULT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `appareils_wifi`
--

INSERT INTO `appareils_wifi` (`id`, `adresse_mac`, `adresse_ip`, `host_name`, `nom_affichage`, `type_appareil`, `fabricant`, `modele`, `systeme`, `user_agent`, `premiere_connexion`, `derniere_connexion`, `created_at`, `updated_at`) VALUES
(1, '3C:7A:F0:2E:82:BA', '128.0.1.213', 'android-1339b7ea284b0002', 'Itel A16 Plus', 'inconnu', NULL, NULL, NULL, NULL, '2026-09-28 15:49:52', '2026-10-06 11:23:44', '2026-09-28 15:49:52', '2026-10-06 11:23:44'),
(2, '7C:03:AB:31:6D:67', '128.0.1.203', 'RedmiNote7Pro-RedmiN', 'RedmiNote7Pro RedmiN', 'inconnu', NULL, NULL, NULL, NULL, '2026-10-05 14:15:02', '2026-10-06 07:27:02', '2026-10-05 14:15:02', '2026-10-06 07:27:02');

-- --------------------------------------------------------

--
-- Structure de la table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-hotspot_sync_lock', 'b:1;', 1791293038),
('laravel-cache-mikrotik.statut', 'a:5:{s:8:\"connecte\";b:1;s:8:\"identity\";s:6:\"INSIDE\";s:7:\"version\";s:15:\"7.21.3 (stable)\";s:6:\"erreur\";N;s:9:\"verifie_a\";s:25:\"2026-10-06T13:23:37+00:00\";}', 1791293037);

-- --------------------------------------------------------

--
-- Structure de la table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cyber_sessions`
--

CREATE TABLE `cyber_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `poste_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tarif_id` bigint(20) UNSIGNED DEFAULT NULL,
  `voucher_id` bigint(20) UNSIGNED DEFAULT NULL,
  `mikrotik_active_id` varchar(255) DEFAULT NULL,
  `hotspot_username` varchar(255) DEFAULT NULL,
  `client_mac` varchar(255) DEFAULT NULL,
  `client_ip` varchar(255) DEFAULT NULL,
  `ssid` varchar(64) DEFAULT NULL,
  `appareil_wifi_id` bigint(20) UNSIGNED DEFAULT NULL,
  `derniere_sync_at` timestamp NULL DEFAULT NULL,
  `type_session` varchar(255) NOT NULL,
  `date_heure_debut` datetime DEFAULT NULL,
  `date_heure_fin_prevue` datetime DEFAULT NULL,
  `date_heure_fin_reelle` datetime DEFAULT NULL,
  `date_heure_suspension` datetime DEFAULT NULL,
  `date_heure_reprise` datetime DEFAULT NULL,
  `duree_suspension` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `duree_prevue` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `duree_consommee` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `temps_restant` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_initial` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_recharge` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_consomme` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_restant` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `montant_a_reverser` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `etat` varchar(255) NOT NULL DEFAULT 'en_attente',
  `motif_fin` varchar(255) DEFAULT NULL,
  `volume_entree` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `volume_sortie` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `volume_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cyber_sessions`
--

INSERT INTO `cyber_sessions` (`id`, `poste_id`, `tarif_id`, `voucher_id`, `mikrotik_active_id`, `hotspot_username`, `client_mac`, `client_ip`, `ssid`, `appareil_wifi_id`, `derniere_sync_at`, `type_session`, `date_heure_debut`, `date_heure_fin_prevue`, `date_heure_fin_reelle`, `date_heure_suspension`, `date_heure_reprise`, `duree_suspension`, `duree_prevue`, `duree_consommee`, `temps_restant`, `montant_initial`, `montant_recharge`, `montant_total`, `montant_consomme`, `montant_restant`, `montant_a_reverser`, `description`, `etat`, `motif_fin`, `volume_entree`, `volume_sortie`, `volume_total`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 22:02:34', '2026-09-21 22:27:34', '2026-09-21 22:25:39', NULL, NULL, 0, 25, 23, 2, 500, 0, 500, 460, 40, 40, NULL, 'terminee', 'arret_client', 0, 0, 0, '2026-09-21 20:02:34', '2026-09-21 20:25:39'),
(2, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 22:32:47', '2026-09-21 22:35:17', '2026-09-21 22:40:21', NULL, NULL, 0, 50, 50, 0, 500, 500, 1000, 1000, 0, 0, 'Test recharge', 'expiree', 'expiration', 0, 0, 0, '2026-09-21 20:32:47', '2026-09-21 20:40:21'),
(3, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 22:43:24', '2026-09-21 23:16:10', NULL, NULL, NULL, 0, 25, 8, 17, 500, 0, 500, 160, 340, 0, 'Test suspension', 'suspendue', NULL, 0, 0, 0, '2026-09-21 20:51:10', '2026-09-21 20:51:57'),
(4, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 22:48:30', '2026-09-21 23:28:31', '2026-09-21 23:14:55', '2026-09-21 23:01:37', '2026-09-21 23:12:31', 654, 25, 15, 10, 500, 0, 500, 300, 200, 200, 'Test suspension reprise', 'terminee', 'fin_test', 0, 0, 0, '2026-09-21 21:08:09', '2026-09-21 21:14:55'),
(5, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 23:07:48', '2026-09-21 23:57:48', '2026-09-21 23:22:29', NULL, NULL, 0, 50, 14, 36, 500, 500, 1000, 280, 720, 720, 'Test recharge', 'terminee', 'fin_test', 0, 0, 0, '2026-09-21 21:15:31', '2026-09-21 21:22:29'),
(6, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 23:15:35', '2026-09-21 23:24:52', '2026-09-21 23:25:58', NULL, NULL, 0, 50, 50, 0, 500, 500, 1000, 1000, 0, 0, 'Test recharge autonome', 'expiree', 'expiration', 0, 0, 0, '2026-09-21 21:23:18', '2026-09-21 21:25:58'),
(7, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 23:29:58', '2026-09-22 00:19:58', '2026-09-21 23:37:23', NULL, NULL, 0, 50, 7, 43, 500, 500, 1000, 140, 860, 860, 'Test historique recharge', 'terminee', 'fin_test', 0, 0, 0, '2026-09-21 21:34:43', '2026-09-21 21:37:23'),
(8, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-21 23:46:49', '2026-09-22 00:11:49', '2026-09-22 14:31:11', NULL, NULL, 0, 25, 25, 0, 500, 0, 500, 500, 0, 0, 'Session client', 'expiree', 'expiration', 0, 0, 0, '2026-09-21 21:46:49', '2026-09-22 12:31:11'),
(9, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-22 15:39:06', '2026-09-22 15:38:13', '2026-09-22 15:39:38', NULL, NULL, 0, 25, 25, 0, 500, 0, 500, 500, 0, 0, 'Test expiration automatique', 'expiree', 'expiration', 0, 0, 0, '2026-09-22 13:39:06', '2026-09-22 13:39:38'),
(10, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ethernet', '2026-09-22 15:46:51', '2026-09-22 15:45:58', '2026-09-22 15:47:01', NULL, NULL, 0, 25, 25, 0, 500, 0, 500, 500, 0, 0, 'Test planificateur automatique', 'expiree', 'expiration', 0, 0, 0, '2026-09-22 13:46:51', '2026-09-22 13:47:01'),
(13, NULL, NULL, NULL, '*D5010080', 'RLLL9HZV', '3C:7A:F0:2E:82:BA', '128.0.1.213', NULL, NULL, '2026-09-28 14:26:02', 'wifi', '2026-09-28 16:23:30', '2026-09-28 16:38:30', '2026-09-28 16:26:02', NULL, NULL, 0, 15, 2, 13, 300, 0, 300, 40, 260, 0, NULL, 'terminee', 'deconnexion_client', 84939, 31972, 116911, '2026-09-28 14:23:51', '2026-09-28 14:26:02'),
(14, NULL, NULL, NULL, '*D5010080', 'P1S0RTH9', '3C:7A:F0:2E:82:BA', '128.0.1.213', NULL, NULL, '2026-09-28 14:48:39', 'wifi', '2026-09-28 16:40:15', '2026-09-28 16:55:15', '2026-09-28 16:48:39', NULL, NULL, 0, 15, 8, 7, 300, 0, 300, 160, 140, 0, NULL, 'terminee', 'deconnexion_client', 936784, 449376, 1386160, '2026-09-28 14:41:02', '2026-09-28 14:48:39'),
(15, NULL, NULL, NULL, '*D5010080', 'P1S0RTH9', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-09-28 15:53:02', 'wifi', '2026-09-28 17:49:38', '2026-09-28 18:04:38', '2026-09-28 17:53:02', NULL, NULL, 0, 15, 3, 12, 300, 0, 300, 60, 240, 0, NULL, 'terminee', 'deconnexion_client', 419526, 187406, 606932, '2026-09-28 15:49:53', '2026-09-28 15:53:02'),
(16, NULL, NULL, NULL, '*D5010080', 'WBPVI8TD', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-02 07:57:02', 'wifi', '2026-10-02 09:50:57', '2026-10-02 10:05:57', '2026-10-02 13:03:10', NULL, NULL, 0, 15, 15, 0, 300, 0, 300, 300, 0, 0, NULL, 'expiree', 'expiration', 10120388, 483894, 10604282, '2026-10-02 07:51:04', '2026-10-02 11:03:10'),
(17, NULL, NULL, NULL, '*D5010080', 'cgcc', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-02 14:21:19', 'wifi', '2026-10-02 16:12:43', NULL, '2026-10-02 16:21:19', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 467853, 467729, 935582, '2026-10-02 14:13:06', '2026-10-02 14:21:19'),
(18, NULL, NULL, NULL, '*D5010080', 'ncby', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-02 14:53:03', 'wifi', '2026-10-02 16:38:43', NULL, '2026-10-02 16:53:03', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 811406, 977455, 1788861, '2026-10-02 14:39:03', '2026-10-02 14:53:03'),
(19, NULL, NULL, NULL, '*D5010080', 'nyxf', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-05 13:35:02', 'wifi', '2026-10-05 15:12:45', NULL, '2026-10-05 15:35:02', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 264838, 177067, 441905, '2026-10-05 13:13:10', '2026-10-05 13:35:02'),
(20, NULL, NULL, NULL, '*CB010080', 'nyxf', '7C:03:AB:31:6D:67', '128.0.1.203', 'hotspot1', 2, '2026-10-05 14:25:03', 'wifi', '2026-10-05 16:14:38', NULL, '2026-10-05 16:25:03', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 3238747, 700377, 3939124, '2026-10-05 14:15:02', '2026-10-05 14:25:03'),
(21, NULL, NULL, NULL, '*CB010080', 'nyxf', '7C:03:AB:31:6D:67', '128.0.1.203', 'hotspot1', 2, '2026-10-05 14:51:05', 'wifi', '2026-10-05 16:42:43', NULL, '2026-10-05 16:51:05', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 58124714, 2132370, 60257084, '2026-10-05 14:43:04', '2026-10-05 14:51:05'),
(22, NULL, NULL, NULL, '*CB010080', 'nyxf', '7C:03:AB:31:6D:67', '128.0.1.203', 'hotspot1', 2, '2026-10-05 14:56:05', 'wifi', '2026-10-05 16:51:09', NULL, '2026-10-05 16:56:05', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 10429532, 694753, 11124285, '2026-10-05 14:52:03', '2026-10-05 14:56:05'),
(23, NULL, NULL, NULL, '*CB010080', 'nyxf', '7C:03:AB:31:6D:67', '128.0.1.203', 'hotspot1', 2, '2026-10-06 05:33:59', 'wifi', '2026-10-06 07:26:42', NULL, '2026-10-06 07:33:59', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 44950970, 804483, 45755453, '2026-10-06 05:27:03', '2026-10-06 05:33:59'),
(24, NULL, NULL, NULL, '*D5010080', 'issj', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-06 05:38:04', 'wifi', '2026-10-06 07:29:01', NULL, '2026-10-06 07:38:04', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 580915, 222480, 803395, '2026-10-06 05:29:11', '2026-10-06 05:38:04'),
(25, NULL, NULL, NULL, '*D5010080', 'issj', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-06 05:57:03', 'wifi', '2026-10-06 07:55:52', NULL, '2026-10-06 07:57:03', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 47402, 34303, 81705, '2026-10-06 05:56:03', '2026-10-06 05:57:03'),
(26, NULL, NULL, NULL, '*D5010080', 'mahefa', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-06 06:03:03', 'wifi', '2026-10-06 07:58:53', NULL, '2026-10-06 08:03:03', NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 80062, 68246, 148308, '2026-10-06 05:59:03', '2026-10-06 06:03:03'),
(27, NULL, NULL, NULL, '*CB010080', 'saux', '7C:03:AB:31:6D:67', '128.0.1.203', 'hotspot1', 2, '2026-10-06 07:27:10', 'wifi', '2026-10-06 09:23:01', NULL, '2026-10-06 09:27:10', NULL, NULL, 0, 0, 4, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 4872497, 558340, 5430837, '2026-10-06 07:23:02', '2026-10-06 07:27:10'),
(28, NULL, NULL, 102, '*D5010080', 'vdbw', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-06 08:08:45', 'wifi', '2026-10-06 10:05:31', NULL, '2026-10-06 10:08:45', NULL, NULL, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, NULL, 'terminee', 'deconnexion_client', 180431, 178188, 358619, '2026-10-06 08:05:43', '2026-10-06 08:08:45'),
(29, NULL, NULL, 102, '*D5010080', 'vdbw', '3C:7A:F0:2E:82:BA', '128.0.1.213', 'hotspot1', 1, '2026-10-06 11:23:44', 'wifi', '2026-10-06 13:16:13', NULL, NULL, NULL, NULL, 0, 0, 7, 0, 0, 0, 0, 0, 0, 0, NULL, 'en_cours', NULL, 87765264, 1486601, 89251865, '2026-10-06 11:17:23', '2026-10-06 11:23:44');

-- --------------------------------------------------------

--
-- Structure de la table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `lot_vouchers`
--

CREATE TABLE `lot_vouchers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `quantite_prevue` int(10) UNSIGNED NOT NULL,
  `montant_unitaire` int(10) UNSIGNED NOT NULL,
  `duree_unitaire` int(10) UNSIGNED NOT NULL,
  `quantite_generee` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `source` varchar(255) NOT NULL DEFAULT 'cyber_manager',
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `lot_vouchers`
--

INSERT INTO `lot_vouchers` (`id`, `nom`, `quantite_prevue`, `montant_unitaire`, `duree_unitaire`, `quantite_generee`, `source`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Test 300 Ar', 3, 300, 15, 3, 'cyber_manager', NULL, '2026-09-25 07:39:29', '2026-09-25 07:39:30'),
(2, 'Pool auto 02/10/2026 09:40', 40, 300, 15, 40, 'cyber_manager', 'Réapprovisionnement automatique (seuil 10, taille 40)', '2026-10-02 07:40:13', '2026-10-02 07:40:16'),
(3, 'Génération du 02/10/2026 16:08', 20, 0, 0, 20, 'cyber_manager', NULL, '2026-10-02 14:08:14', '2026-10-02 14:08:14'),
(4, 'Génération du 02/10/2026 16:08', 40, 0, 0, 40, 'cyber_manager', NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:28'),
(5, 'Génération du 06/10/2026 08:03', 1, 0, 0, 1, 'cyber_manager', NULL, '2026-10-06 06:03:13', '2026-10-06 06:03:14'),
(6, 'Génération du 06/10/2026 09:21', 1, 0, 0, 1, 'cyber_manager', NULL, '2026-10-06 07:21:19', '2026-10-06 07:21:20');

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_21_195542_create_postes_table', 2),
(5, '2026_09_21_214819_create_sessions_table', 3),
(6, '2026_09_21_224351_add_suspension_fields_to_cyber_sessions_table', 4),
(7, '2026_09_21_225723_add_duree_suspension_to_cyber_sessions_table', 5),
(8, '2026_09_21_232745_create_recharges_table', 6),
(9, '2026_09_22_150003_add_agent_url_to_postes_table', 7),
(10, '2026_09_25_083407_create_restitutions_table', 8),
(11, '2026_09_25_090141_create_tarifs_table', 9),
(12, '2026_09_25_090317_add_tarif_id_to_cyber_sessions_table', 10),
(13, '2026_09_25_090551_add_details_to_tarifs_table', 11),
(14, '2026_09_25_093109_create_lot_vouchers_table', 12),
(15, '2026_09_25_093115_create_vouchers_table', 12),
(16, '2026_09_25_093259_add_voucher_id_to_cyber_sessions_table', 12),
(17, '2026_09_28_150000_add_mikrotik_sync_to_vouchers_table', 13),
(18, '2026_09_28_200000_add_wifi_fields_to_sessions_table', 14),
(19, '2026_09_28_210000_create_appareils_wifi_table', 15),
(20, '2026_09_28_210100_extend_vouchers_for_wifi', 15),
(21, '2026_09_28_210200_add_ssid_and_appareil_to_cyber_sessions', 15),
(22, '2026_09_29_100000_add_nom_to_vouchers_table', 16),
(23, '2026_09_30_090000_simplify_vouchers_states', 17),
(24, '2026_10_03_090000_add_mikrotik_fields_to_vouchers', 18),
(25, '2026_10_03_090100_add_nom_affichage_to_appareils_wifi', 18),
(26, '2026_10_03_090200_create_parametres_table', 18);

-- --------------------------------------------------------

--
-- Structure de la table `parametres`
--

CREATE TABLE `parametres` (
  `cle` varchar(100) NOT NULL,
  `valeur` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `parametres`
--

INSERT INTO `parametres` (`cle`, `valeur`, `created_at`, `updated_at`) VALUES
('hotspot_pool_mode', 'auto', '2026-10-06 05:42:24', '2026-10-06 05:42:31');

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `postes`
--

CREATE TABLE `postes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom_poste` varchar(255) NOT NULL,
  `nom_windows` varchar(255) DEFAULT NULL,
  `adresse_mac` varchar(255) DEFAULT NULL,
  `adresse_ip` varchar(255) DEFAULT NULL,
  `type_connexion` varchar(255) NOT NULL DEFAULT 'ethernet',
  `etat` varchar(255) NOT NULL DEFAULT 'disponible',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `derniere_communication` timestamp NULL DEFAULT NULL,
  `agent_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `postes`
--

INSERT INTO `postes` (`id`, `nom_poste`, `nom_windows`, `adresse_mac`, `adresse_ip`, `type_connexion`, `etat`, `actif`, `derniere_communication`, `agent_url`, `created_at`, `updated_at`) VALUES
(1, 'POSTE1', NULL, NULL, '128.1.0.152', 'ethernet', 'disponible', 1, NULL, NULL, '2026-09-21 18:02:19', '2026-09-25 06:47:44'),
(2, 'POSTE2', NULL, NULL, '128.1.0.150', 'ethernet', 'disponible', 1, NULL, NULL, '2026-09-21 18:02:19', '2026-09-21 18:02:19'),
(3, 'POSTE3', NULL, NULL, NULL, 'ethernet', 'disponible', 1, NULL, NULL, '2026-09-21 18:02:19', '2026-09-21 18:02:19'),
(4, 'POSTE7', NULL, 'C0-3F-D5-5E-EE-B7', '128.1.0.165', 'ethernet', 'disponible', 1, '2026-09-25 07:42:32', 'http://127.0.0.1:5005', '2026-09-21 18:02:19', '2026-09-25 07:42:32');

-- --------------------------------------------------------

--
-- Structure de la table `recharges`
--

CREATE TABLE `recharges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `date_heure` datetime NOT NULL,
  `montant` int(10) UNSIGNED NOT NULL,
  `duree_ajoutee` int(10) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `recharges`
--

INSERT INTO `recharges` (`id`, `session_id`, `date_heure`, `montant`, `duree_ajoutee`, `description`, `created_at`, `updated_at`) VALUES
(1, 7, '2026-09-21 23:35:09', 500, 25, 'Recharge test 500 Ar', '2026-09-21 21:35:09', '2026-09-21 21:35:09');

-- --------------------------------------------------------

--
-- Structure de la table `restitutions`
--

CREATE TABLE `restitutions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `date_heure` datetime NOT NULL,
  `montant` int(10) UNSIGNED NOT NULL,
  `motif` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('ZhqeoQxIaSpvfjxWBHLfdUzBWU0Qr7LhRGVpWdwf', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiMldTbGU3ajlrUU1BV1lYVXJKR25aQUlJRWk5VnpvNUlmZHAxeGNFWSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozNDoiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2hvdHNwb3QvZXRhdCI7czo1OiJyb3V0ZSI7czoxMjoiaG90c3BvdC5ldGF0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo3OiJob3RzcG90IjthOjA6e319', 1791293024);

-- --------------------------------------------------------

--
-- Structure de la table `tarifs`
--

CREATE TABLE `tarifs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `montant_par_minute` int(10) UNSIGNED NOT NULL DEFAULT 20,
  `montant_minimum` int(10) UNSIGNED NOT NULL DEFAULT 300,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `personnalise` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tarifs`
--

INSERT INTO `tarifs` (`id`, `nom`, `created_at`, `updated_at`, `montant_par_minute`, `montant_minimum`, `actif`, `personnalise`, `description`) VALUES
(1, 'Tarif standard', '2026-09-25 07:06:39', '2026-09-25 07:06:39', 20, 300, 1, 0, 'Tarif standard du cybercafé : 20 Ar par minute.');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Administrateur', 'admin@cyber-manager.local', NULL, '$2y$12$/.1B1EfxYzBeaYobzHuhV.y6uMooc/fJC4rEYgeAi29LlsM/Si.Ri', 'yKhALkFGWN7WKUld2My7FAWQp5sFp2Bf7RtXRyyNCU5f8MT5rfNo8MbEKMX7', '2026-09-21 17:52:25', '2026-09-21 17:52:25');

-- --------------------------------------------------------

--
-- Structure de la table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lot_voucher_id` bigint(20) UNSIGNED DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `montant` int(10) UNSIGNED NOT NULL,
  `duree` int(10) UNSIGNED NOT NULL,
  `etat` enum('disponible','en_cours','utilise') NOT NULL DEFAULT 'disponible',
  `source` varchar(255) NOT NULL DEFAULT 'cyber_manager',
  `type_voucher` enum('temporaire','permanent') NOT NULL DEFAULT 'temporaire',
  `protege` tinyint(1) NOT NULL DEFAULT 0,
  `serveur` varchar(64) DEFAULT NULL,
  `profil` varchar(64) DEFAULT NULL,
  `limite_data_mo` int(10) UNSIGNED DEFAULT NULL,
  `appareil_wifi_id` bigint(20) UNSIGNED DEFAULT NULL,
  `mikrotik_id` varchar(255) DEFAULT NULL,
  `date_heure_creation` datetime NOT NULL,
  `date_heure_utilisation` datetime DEFAULT NULL,
  `date_heure_expiration` datetime DEFAULT NULL,
  `derniere_synchronisation` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `mikrotik_synced_at` timestamp NULL DEFAULT NULL,
  `mikrotik_sync_error` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `vouchers`
--

INSERT INTO `vouchers` (`id`, `lot_voucher_id`, `username`, `nom`, `password`, `code`, `montant`, `duree`, `etat`, `source`, `type_voucher`, `protege`, `serveur`, `profil`, `limite_data_mo`, `appareil_wifi_id`, `mikrotik_id`, `date_heure_creation`, `date_heure_utilisation`, `date_heure_expiration`, `derniere_synchronisation`, `description`, `created_at`, `updated_at`, `mikrotik_synced_at`, `mikrotik_sync_error`) VALUES
(76, 4, 'temd', NULL, '1565', 'temd', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*7C', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:30', '2026-10-02 14:08:30', NULL),
(77, 4, 'khpo', NULL, '3311', 'khpo', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*7D', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:30', '2026-10-02 14:08:30', NULL),
(78, 4, 'biry', NULL, '5056', 'biry', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*7E', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:30', '2026-10-02 14:08:30', NULL),
(79, 4, 'wjla', NULL, '6027', 'wjla', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*7F', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:30', '2026-10-02 14:08:30', NULL),
(80, 4, 'csas', NULL, '9944', 'csas', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*80', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:31', '2026-10-02 14:08:31', NULL),
(81, 4, 'qzrs', NULL, '0641', 'qzrs', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*81', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:31', '2026-10-02 14:08:31', NULL),
(82, 4, 'ffmr', NULL, '5366', 'ffmr', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*82', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:31', '2026-10-02 14:08:31', NULL),
(83, 4, 'fnxq', NULL, '6819', 'fnxq', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*83', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:31', '2026-10-02 14:08:31', NULL),
(84, 4, 'vqye', NULL, '8001', 'vqye', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*84', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:31', '2026-10-02 14:08:31', NULL),
(85, 4, 'rdyk', NULL, '0315', 'rdyk', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*85', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:31', '2026-10-02 14:08:31', NULL),
(86, 4, 'yhwi', NULL, '9685', 'yhwi', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*86', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(87, 4, 'bmlk', NULL, '6300', 'bmlk', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*87', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(88, 4, 'qemp', NULL, '6687', 'qemp', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*88', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(89, 4, 'lgoh', NULL, '2381', 'lgoh', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*89', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(90, 4, 'nmqx', NULL, '9360', 'nmqx', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*8A', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(91, 4, 'sjch', NULL, '3759', 'sjch', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*8B', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(92, 4, 'uxop', NULL, '0420', 'uxop', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*8C', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(93, 4, 'emtv', NULL, '0517', 'emtv', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*8D', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:32', '2026-10-02 14:08:32', NULL),
(94, 4, 'dxrd', NULL, '9966', 'dxrd', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*8E', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:33', '2026-10-02 14:08:33', NULL),
(95, 4, 'mcgp', NULL, '2217', 'mcgp', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*8F', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:33', '2026-10-02 14:08:33', NULL),
(96, 4, 'xjmx', NULL, '4140', 'xjmx', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*90', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:33', '2026-10-02 14:08:33', NULL),
(97, 4, 'tzwc', NULL, '3586', 'tzwc', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*91', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:33', '2026-10-02 14:08:33', NULL),
(98, 4, 'hcql', NULL, '0375', 'hcql', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*92', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:33', '2026-10-02 14:08:33', NULL),
(99, 4, 'rscj', NULL, '9273', 'rscj', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*93', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:34', '2026-10-02 14:08:34', NULL),
(100, 4, 'vwms', NULL, '9236', 'vwms', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*94', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:34', '2026-10-02 14:08:34', NULL),
(101, 4, 'kldt', NULL, '0821', 'kldt', 0, 0, 'disponible', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, NULL, '*95', '2026-10-02 16:08:28', NULL, NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-02 14:08:34', '2026-10-02 14:08:34', NULL),
(102, 4, 'vdbw', NULL, '1182', 'vdbw', 0, 0, 'en_cours', 'cyber_manager', 'temporaire', 0, NULL, NULL, NULL, 1, '*96', '2026-10-02 16:08:28', '2026-10-06 10:05:43', NULL, NULL, NULL, '2026-10-02 14:08:28', '2026-10-06 11:17:24', '2026-10-02 14:08:34', NULL),
(107, NULL, 'admin', NULL, 'admin123', 'admin', 0, 0, 'disponible', 'manuel', 'temporaire', 1, NULL, NULL, NULL, NULL, '*1', '2026-10-02 16:19:50', NULL, NULL, NULL, 'Mot de passe de serveur', '2026-10-02 14:19:50', '2026-10-06 07:28:03', '2026-10-02 14:19:50', NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `appareils_wifi`
--
ALTER TABLE `appareils_wifi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appareils_wifi_adresse_mac_unique` (`adresse_mac`);

--
-- Index pour la table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Index pour la table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Index pour la table `cyber_sessions`
--
ALTER TABLE `cyber_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cyber_sessions_poste_id_foreign` (`poste_id`),
  ADD KEY `cyber_sessions_tarif_id_foreign` (`tarif_id`),
  ADD KEY `cyber_sessions_voucher_id_foreign` (`voucher_id`),
  ADD KEY `cyber_sessions_mikrotik_active_id_index` (`mikrotik_active_id`),
  ADD KEY `cyber_sessions_hotspot_username_index` (`hotspot_username`),
  ADD KEY `cyber_sessions_appareil_wifi_id_foreign` (`appareil_wifi_id`);

--
-- Index pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Index pour la table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Index pour la table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `lot_vouchers`
--
ALTER TABLE `lot_vouchers`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `parametres`
--
ALTER TABLE `parametres`
  ADD PRIMARY KEY (`cle`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Index pour la table `postes`
--
ALTER TABLE `postes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `recharges`
--
ALTER TABLE `recharges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recharges_session_id_foreign` (`session_id`);

--
-- Index pour la table `restitutions`
--
ALTER TABLE `restitutions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `restitutions_session_id_foreign` (`session_id`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Index pour la table `tarifs`
--
ALTER TABLE `tarifs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Index pour la table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vouchers_username_unique` (`username`),
  ADD UNIQUE KEY `vouchers_code_unique` (`code`),
  ADD KEY `vouchers_lot_voucher_id_foreign` (`lot_voucher_id`),
  ADD KEY `vouchers_etat_index` (`etat`),
  ADD KEY `vouchers_username_index` (`username`),
  ADD KEY `vouchers_source_index` (`source`),
  ADD KEY `vouchers_appareil_wifi_id_foreign` (`appareil_wifi_id`),
  ADD KEY `vouchers_type_etat_index` (`type_voucher`,`etat`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `appareils_wifi`
--
ALTER TABLE `appareils_wifi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `cyber_sessions`
--
ALTER TABLE `cyber_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `lot_vouchers`
--
ALTER TABLE `lot_vouchers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `postes`
--
ALTER TABLE `postes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `recharges`
--
ALTER TABLE `recharges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `restitutions`
--
ALTER TABLE `restitutions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `tarifs`
--
ALTER TABLE `tarifs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `cyber_sessions`
--
ALTER TABLE `cyber_sessions`
  ADD CONSTRAINT `cyber_sessions_appareil_wifi_id_foreign` FOREIGN KEY (`appareil_wifi_id`) REFERENCES `appareils_wifi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cyber_sessions_poste_id_foreign` FOREIGN KEY (`poste_id`) REFERENCES `postes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cyber_sessions_tarif_id_foreign` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cyber_sessions_voucher_id_foreign` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `recharges`
--
ALTER TABLE `recharges`
  ADD CONSTRAINT `recharges_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `cyber_sessions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `restitutions`
--
ALTER TABLE `restitutions`
  ADD CONSTRAINT `restitutions_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `cyber_sessions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `vouchers_appareil_wifi_id_foreign` FOREIGN KEY (`appareil_wifi_id`) REFERENCES `appareils_wifi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vouchers_lot_voucher_id_foreign` FOREIGN KEY (`lot_voucher_id`) REFERENCES `lot_vouchers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
