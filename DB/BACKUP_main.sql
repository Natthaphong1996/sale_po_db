/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `sale_po_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `sale_po_db`;

CREATE TABLE IF NOT EXISTS `customer_list` (
  `cus_id` int(11) NOT NULL AUTO_INCREMENT,
  `cus_name` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`cus_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `po_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `prod_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `po_list` (`po_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `po_item_history` (
  `hist_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `changed_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`changed_data`)),
  `changed_by` varchar(100) NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`hist_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `po_item_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `po_items` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `po_list` (
  `po_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_no` char(50) NOT NULL,
  `cus_id` int(11) NOT NULL,
  `po_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_no` (`po_no`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `po_pdf` (
  `po_pdf_id` int(11) NOT NULL,
  `po_no` varchar(50) NOT NULL,
  `path_file` varchar(512) NOT NULL,
  `create_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`po_pdf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `product_list` (
  `prod_id` int(11) NOT NULL AUTO_INCREMENT,
  `prod_code` varchar(50) DEFAULT NULL,
  `customer_code` varchar(50) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `prod_desc` varchar(255) DEFAULT NULL,
  `thickness` int(11) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `length` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `date_create` datetime DEFAULT current_timestamp(),
  `price` double NOT NULL,
  `date_price` datetime NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`prod_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `product_price` (
  `price_id` int(11) NOT NULL AUTO_INCREMENT,
  `price_value` double DEFAULT NULL,
  `prod_id` int(11) DEFAULT NULL,
  `date_update` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`price_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `product_price_history` (
  `price_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `price_id` int(11) DEFAULT NULL,
  `change_from` double DEFAULT NULL,
  `change_to` double DEFAULT NULL,
  `change_date` datetime DEFAULT current_timestamp(),
  `user_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`price_log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prod_type_list` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `prod_user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `thainame` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
