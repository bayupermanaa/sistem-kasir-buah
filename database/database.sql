-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for sistem_kasir_buah
CREATE DATABASE IF NOT EXISTS `sistem_kasir_buah` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `sistem_kasir_buah`;

-- Dumping structure for table sistem_kasir_buah.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.categories: ~4 rows (approximately)
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(9sistem_kasir_buahcategories, 'Buah Lokal', 'buah-lokal', NULL, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(10, 'Buah Import', 'buah-import', NULL, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(11, 'Buah Potong', 'buah-potong', NULL, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(12, 'Paket Hampers', 'paket-hampers', NULL, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL);

-- Dumping structure for table sistem_kasir_buah.failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.failed_jobs: ~0 rows (approximately)

-- Dumping structure for table sistem_kasir_buah.migrations
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.migrations: ~10 rows (approximately)
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(1, '2014_10_11_000000_create_outlets_table', 1),
	(2, '2014_10_12_000000_create_users_table', 1),
	(3, '2014_10_12_100000_create_password_reset_tokens_table', 1),
	(4, '2019_08_19_000000_create_failed_jobs_table', 1),
	(5, '2019_12_14_000001_create_personal_access_tokens_table', 1),
	(6, '2026_07_21_000001_create_categories_table', 1),
	(7, '2026_07_21_000002_create_units_table', 1),
	(8, '2026_07_21_000003_create_products_table', 1),
	(9, '2026_07_21_000004_create_outlet_product_stocks_table', 1),
	(10, '2026_07_21_000005_create_stock_histories_table', 1);

-- Dumping structure for table sistem_kasir_buah.outlets
CREATE TABLE IF NOT EXISTS `outlets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.outlets: ~1 rows (approximately)
INSERT INTO `outlets` (`id`, `name`, `address`, `phone`, `created_at`, `updated_at`) VALUES
	(10, 'Toko Buah Teras Fatimah', NULL, NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28');

-- Dumping structure for table sistem_kasir_buah.outlet_product_stocks
CREATE TABLE IF NOT EXISTS `outlet_product_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `minimum_stock` decimal(15,3) NOT NULL DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outlet_product_stocks_outlet_id_product_id_unique` (`outlet_id`,`product_id`),
  KEY `outlet_product_stocks_product_id_foreign` (`product_id`),
  CONSTRAINT `outlet_product_stocks_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `outlet_product_stocks_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.outlet_product_stocks: ~8 rows (approximately)
INSERT INTO `outlet_product_stocks` (`id`, `outlet_id`, `product_id`, `quantity`, `minimum_stock`, `created_at`, `updated_at`) VALUES
	(6, 10, 7, 24.500, 5.000, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(7, 10, 8, 12.000, 4.000, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(8, 10, 9, 30.000, 6.000, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(9, 10, 10, 8.000, 3.000, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(10, 10, 11, 15.750, 4.500, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(11, 10, 12, 6.250, 2.000, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(12, 10, 13, 9.000, 2.000, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(13, 10, 14, 0.500, 2.500, '2026-07-22 08:04:28', '2026-07-22 08:08:50');

-- Dumping structure for table sistem_kasir_buah.password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.password_reset_tokens: ~0 rows (approximately)

-- Dumping structure for table sistem_kasir_buah.personal_access_tokens
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.personal_access_tokens: ~0 rows (approximately)

-- Dumping structure for table sistem_kasir_buah.products
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_code_unique` (`code`),
  KEY `products_category_id_foreign` (`category_id`),
  KEY `products_unit_id_foreign` (`unit_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.products: ~8 rows (approximately)
INSERT INTO `products` (`id`, `category_id`, `unit_id`, `code`, `name`, `description`, `image`, `purchase_price`, `selling_price`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(7, 10, 9, 'BRG-0001', 'Pisang Cavendish', NULL, NULL, 18000.00, 25000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(8, 10, 9, 'BRG-0002', 'Apel Fuji', NULL, NULL, 32000.00, 42000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(9, 9, 9, 'BRG-0003', 'Jeruk Pontianak', NULL, NULL, 14000.00, 22000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(10, 9, 9, 'BRG-0004', 'Semangka Merah', NULL, NULL, 12000.00, 18000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(11, 10, 9, 'BRG-0005', 'Melon Premium', NULL, NULL, 17000.00, 26000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(12, 11, 10, 'BRG-0006', 'Potongan Buah Segar', NULL, NULL, 10000.00, 15000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(13, 12, 11, 'BRG-0007', 'Paket Hampers Kecil', NULL, NULL, 50000.00, 75000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(14, 9, 9, 'BRG-0008', 'Nanas Madu', NULL, NULL, 11000.00, 17000.00, 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL);

-- Dumping structure for table sistem_kasir_buah.stock_histories
CREATE TABLE IF NOT EXISTS `stock_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_before` decimal(15,3) NOT NULL,
  `quantity_change` decimal(15,3) NOT NULL,
  `quantity_after` decimal(15,3) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_histories_outlet_id_foreign` (`outlet_id`),
  KEY `stock_histories_product_id_foreign` (`product_id`),
  KEY `stock_histories_user_id_foreign` (`user_id`),
  CONSTRAINT `stock_histories_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_histories_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.stock_histories: ~9 rows (approximately)
INSERT INTO `stock_histories` (`id`, `outlet_id`, `product_id`, `user_id`, `reference_type`, `reference_id`, `type`, `quantity_before`, `quantity_change`, `quantity_after`, `reason`, `notes`, `created_at`, `updated_at`) VALUES
	(1, 10, 7, 33, NULL, NULL, 'initial_stock', 0.000, 24.500, 24.500, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(2, 10, 8, 33, NULL, NULL, 'initial_stock', 0.000, 12.000, 12.000, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(3, 10, 9, 33, NULL, NULL, 'initial_stock', 0.000, 30.000, 30.000, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(4, 10, 10, 33, NULL, NULL, 'initial_stock', 0.000, 8.000, 8.000, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(5, 10, 11, 33, NULL, NULL, 'initial_stock', 0.000, 15.750, 15.750, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(6, 10, 12, 33, NULL, NULL, 'initial_stock', 0.000, 6.250, 6.250, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(7, 10, 13, 33, NULL, NULL, 'initial_stock', 0.000, 9.000, 9.000, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(8, 10, 14, 33, NULL, NULL, 'initial_stock', 0.000, 3.500, 3.500, 'Stok awal seed', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(9, 10, 14, 34, NULL, NULL, 'processed', 3.500, -3.000, 0.500, 'paket', 'test', '2026-07-22 08:08:50', '2026-07-22 08:08:50');

-- Dumping structure for table sistem_kasir_buah.units
CREATE TABLE IF NOT EXISTS `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_name_unique` (`name`),
  UNIQUE KEY `units_symbol_unique` (`symbol`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.units: ~3 rows (approximately)
INSERT INTO `units` (`id`, `name`, `symbol`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
	(9, 'Kilogram', 'kg', 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(10, 'Gram', 'g', 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL),
	(11, 'Pieces', 'pcs', 1, '2026-07-22 08:04:28', '2026-07-22 08:04:28', NULL);

-- Dumping structure for table sistem_kasir_buah.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cashier',
  `outlet_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_outlet_id_foreign` (`outlet_id`),
  CONSTRAINT `users_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table sistem_kasir_buah.users: ~2 rows (approximately)
INSERT INTO `users` (`id`, `name`, `username`, `email`, `role`, `outlet_id`, `is_active`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
	(33, 'Owner', 'owner', 'owner@terasfatimah.test', 'owner', NULL, 1, NULL, '$2y$12$ZWDRcZnw0.wgHmK2JlCGtOGGvrcgMJIxTZmq0h1bgwJmNur188Ylm', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28'),
	(34, 'Cashier', 'cashier', 'cashier@terasfatimah.test', 'cashier', 10, 1, NULL, '$2y$12$esmbXU2VK9RQeDRtSF.MzeUjmTRaA8YkAcTAkxam1P6lRAhBEqRJO', NULL, '2026-07-22 08:04:28', '2026-07-22 08:04:28');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
