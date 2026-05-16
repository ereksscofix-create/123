-- EHPST Database Schema
-- Generate this on your MySQL server

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` enum('sender','recipient','worker') DEFAULT 'recipient',
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for parcels
-- ----------------------------
CREATE TABLE IF NOT EXISTS `parcels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_code` varchar(20) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `sender_address` text DEFAULT NULL,
  `address` text NOT NULL,
  `weight` decimal(10,3) DEFAULT '0.000',
  `cost` decimal(10,2) DEFAULT '0.00',
  `tariff` varchar(10) DEFAULT 'ST',
  `cod` decimal(10,2) DEFAULT '0.00',
  `declared_value` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `track_code` (`track_code`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  CONSTRAINT `parcels_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parcels_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for parcel_status
-- ----------------------------
CREATE TABLE IF NOT EXISTS `parcel_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parcel_id` int(11) NOT NULL,
  `status_text` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parcel_id` (`parcel_id`),
  CONSTRAINT `parcel_status_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for notifications
-- ----------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(20) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for parcel_codes
-- ----------------------------
CREATE TABLE IF NOT EXISTS `parcel_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parcel_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `secret_code` varchar(20) DEFAULT NULL,
  `code_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parcel_id` (`parcel_id`),
  -- Убрана уникальность для parcel_id, чтобы можно было генерировать несколько кодов для одной посылки
  CONSTRAINT `parcel_codes_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Если у вас уже есть таблица и возникает ошибка Duplicate entry, выполните:
-- ALTER TABLE parcel_codes DROP INDEX parcel_id;
-- ALTER TABLE parcel_codes ADD INDEX parcel_id (parcel_id);

SET FOREIGN_KEY_CHECKS = 1;
