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
  `is_paid` tinyint(1) DEFAULT '0',
  `pay_on_delivery` tinyint(1) DEFAULT '0',
  `is_cod_paid` tinyint(1) DEFAULT '0',
  `is_cod_issued` tinyint(1) DEFAULT '0',
  `is_refunded` tinyint(1) DEFAULT '0',
  `payment_method` varchar(20) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `is_return` tinyint(1) DEFAULT '0',
  `pickup_point` varchar(100) DEFAULT NULL,
  `shelf` int(11) DEFAULT NULL,
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
  CONSTRAINT `parcel_codes_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for parcel_followers
-- ----------------------------
CREATE TABLE IF NOT EXISTS `parcel_followers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `parcel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_parcel` (`user_id`,`parcel_id`),
  CONSTRAINT `pf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pf_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for money_transfers
-- ----------------------------
CREATE TABLE IF NOT EXISTS `money_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_code` varchar(20) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','paid','issued','refunded') DEFAULT 'pending',
  `payment_method` varchar(20) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_code` (`transfer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for shifts
-- ----------------------------
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `opened_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` timestamp NULL DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for transactions
-- ----------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
