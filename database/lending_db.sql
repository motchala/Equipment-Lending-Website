-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2026 at 05:18 AM
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
-- Database: `lending_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_accounts`
--

CREATE TABLE `tbl_accounts` (
  `id` int(11) NOT NULL,
  `fullName` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Super Admin','Admin') NOT NULL DEFAULT 'Admin',
  `created_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `dormant_until` bigint(20) UNSIGNED DEFAULT NULL,
  `session_epoch` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_accounts`
--

INSERT INTO `tbl_accounts` (`id`, `fullName`, `email`, `password`, `role`, `created_at`, `last_login`, `dormant_until`, `session_epoch`) VALUES
(1, 'Redg Admin', 'main@admin.edu', '$2y$10$dLNCtd5IGqTMf7VUHFYyEOPI00YWyd9h9n4uj6dTptlcKwFNcw57e', 'Super Admin', '2026-01-01 00:00:00', '2026-10-06 09:43:14', NULL, 0),
(2, 'Chico Ramos', 'chico@admin.edu', '$2y$10$w.G7jIE6r6iSsVyn/T/G2OAhoqpyxgu4kkWKbHIhOky/CJk94xLGC', 'Admin', '2026-10-02 11:05:03', '2026-10-04 05:50:19', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_arbitration_config`
--

CREATE TABLE `tbl_arbitration_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_arbitration_config`
--

INSERT INTO `tbl_arbitration_config` (`id`, `config_key`, `config_value`, `updated_at`) VALUES
(1, 'tie_break_window_seconds', '5', '2026-06-04 05:42:29'),
(2, 'role_priority_director', '4', '2026-06-04 05:42:29'),
(3, 'role_priority_adviser', '3', '2026-06-04 05:42:29'),
(4, 'role_priority_faculty', '2', '2026-06-04 05:42:29'),
(5, 'role_priority_student', '1', '2026-06-04 05:42:29'),
(6, 'rule_overdue_block_enabled', '1', '2026-06-04 05:42:29'),
(7, 'rule_duplicate_block_enabled', '1', '2026-06-04 05:42:29'),
(8, 'rule_missing_doc_block_enabled', '1', '2026-06-04 05:42:29');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_arbitration_log`
--

CREATE TABLE `tbl_arbitration_log` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `borrower_id` varchar(50) NOT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `decision` varchar(20) NOT NULL,
  `rule_applied` varchar(50) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `override_by` varchar(255) DEFAULT NULL,
  `override_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_arbitration_log`
--

INSERT INTO `tbl_arbitration_log` (`id`, `request_id`, `borrower_id`, `borrower_name`, `equipment_name`, `decision`, `rule_applied`, `reason`, `override_by`, `override_reason`, `created_at`) VALUES
(1, 17, 'Sandy Napiza', 'Sandy Napiza', 'AC Remote', 'Returned', 'qr_return', 'Equipment returned via QR scan', 'Redg Admin', 'QR Token Return', '2026-06-04 13:47:37'),
(2, 18, '2023-00004-BN-0', 'Sandy Napiza', 'AC Remote', 'Returned', 'qr_return', 'Request approved via FIFO priority scoring.', 'Redg Admin', NULL, '2026-06-06 21:43:09'),
(4, 19, '2023-00251-BN-0', 'Frederick Rosales', 'AC Remote', 'Returned', 'qr_return', 'Request approved via FIFO priority scoring.', 'Redg Admin', NULL, '2026-06-06 21:59:40'),
(6, 20, '2023-00251-BN-0', 'Frederick Rosales', 'AC Remote', 'Returned', 'qr_return', 'Request approved via FIFO priority scoring.', 'Redg Admin', NULL, '2026-06-06 22:02:11'),
(8, 22, '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'AC Remote', 'Approved', 'rule_1_fifo', 'Request approved via FIFO priority scoring.', NULL, NULL, '2026-10-02 08:23:50'),
(9, 23, '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'HDMI Cable', 'Approved', 'rule_1_fifo', 'Request approved via FIFO priority scoring.', NULL, NULL, '2026-10-02 08:25:12'),
(10, 24, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Double A Batter', 'Approved', 'rule_1_fifo', 'Request approved via FIFO priority scoring.', NULL, NULL, '2026-10-05 20:04:28');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_buildings`
--

CREATE TABLE `tbl_buildings` (
  `building_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL,
  `building_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `wing` varchar(100) DEFAULT NULL,
  `floor_count` tinyint(3) NOT NULL DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'domain',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` tinyint(3) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_buildings`
--

INSERT INTO `tbl_buildings` (`building_id`, `campus_id`, `building_key`, `name`, `wing`, `floor_count`, `image_path`, `icon`, `description`, `sort_order`, `created_at`) VALUES
(1, 1, 'main-building-a', 'Building A (Old)', 'South Wing', 5, 'assets/images/faculty/pup-main-building-a-image.jpg', 'domain', 'Administrative offices, lecture halls, organization rooms, and specialized laboratories spread across 5 floors.', 1, '2026-10-01 13:05:16'),
(2, 1, 'main-building-b', 'Building B (New)', 'North Wing', 5, 'assets/images/faculty/pup-main-building-b-image.jpg', 'business', 'Modern laboratories, smart classrooms, and collaborative study spaces.', 2, '2026-10-01 13:05:16'),
(3, 2, 'cite-main', 'PUP CITE Building', 'Main Block', 4, 'assets/images/faculty/pup-cite-image.jpg', 'engineering', 'Technical laboratories, computer labs, and specialized engineering facilities spread across 4 floors.', 1, '2026-10-01 13:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_campuses`
--

CREATE TABLE `tbl_campuses` (
  `campus_id` int(11) NOT NULL,
  `campus_key` varchar(50) NOT NULL,
  `campus_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_campuses`
--

INSERT INTO `tbl_campuses` (`campus_id`, `campus_key`, `campus_name`, `description`, `created_at`) VALUES
(1, 'main', 'PUP MAIN', 'Manage academic buildings, administrative offices, and central university facilities.', '2026-10-01 13:05:16'),
(2, 'cite', 'PUP CITE', 'Manage technical laboratories, engineering workshops, and specialized equipment facilities.', '2026-10-01 13:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_codes`
--

CREATE TABLE `tbl_faculty_codes` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(255) NOT NULL,
  `faculty_name` varchar(255) NOT NULL,
  `code` varchar(15) NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_by_name` varchar(255) DEFAULT NULL,
  `used_by_id` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_faculty_codes`
--

INSERT INTO `tbl_faculty_codes` (`id`, `faculty_id`, `faculty_name`, `code`, `is_used`, `used_by_name`, `used_by_id`, `created_at`, `used_at`) VALUES
(1, '2023-00004-BN-0', 'Sandy Napiza', '7v4-48t-8u9', 1, 'sandy', '2023-00004-BN-0', '2026-06-04 13:46:31', '2026-06-04 13:46:58'),
(3, '2023-00251-BN-0', 'Frederick Rosales', 'nk5-m6x-w2k', 1, 'Kiloman', '2023-00250-BN-0', '2026-06-06 22:07:38', '2026-06-06 22:09:27');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_notif_state`
--

CREATE TABLE `tbl_faculty_notif_state` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `notif_key` varchar(64) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_faculty_notif_state`
--

INSERT INTO `tbl_faculty_notif_state` (`id`, `faculty_id`, `notif_key`, `is_read`, `is_deleted`, `updated_at`) VALUES
(7, 'NOTSET-F15E038389ED', 'sysnotice-1', 1, 0, '2026-10-06 09:25:29');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory`
--

CREATE TABLE `tbl_inventory` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `condition` varchar(20) NOT NULL DEFAULT 'Good',
  `description` varchar(500) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `is_high_value` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_inventory`
--

INSERT INTO `tbl_inventory` (`item_id`, `item_name`, `category`, `quantity`, `condition`, `description`, `image_path`, `created_at`, `is_archived`, `is_high_value`) VALUES
(8, 'HDMI Cable', 'Electronics and Accessories', 3, 'Good', NULL, 'uploads/1768426958_item_hdmicable.webp', '2026-01-15 05:42:38', 0, 0),
(9, 'AC Remote', 'Electronics and Accessories', 0, 'Good', NULL, 'uploads/1768427004_item_remoteAc.jpg', '2026-01-15 05:43:24', 0, 0),
(10, 'Extension', 'Electronics and Accessories', 6, 'Good', NULL, 'uploads/1768427033_item_extension.webp', '2026-01-15 05:43:53', 0, 0),
(30, 'Table', 'Others', 4, 'Good', NULL, 'uploads/1790901156_item_table2.jpg', '2026-10-02 08:32:36', 0, 0),
(31, 'Projector', 'Audio/Visual', 2, 'Good', NULL, 'uploads/1790901270_images.jpg', '2026-10-02 08:34:30', 0, 0),
(32, 'Double A Batter', 'Power', 15, 'Fair', NULL, 'uploads/1790910199_batteryAA.jpg', '2026-10-02 11:03:19', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ip_login_attempts`
--

CREATE TABLE `tbl_ip_login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `fail_count` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL DEFAULT current_timestamp(),
  `locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_ip_login_attempts`
--

INSERT INTO `tbl_ip_login_attempts` (`id`, `ip_address`, `fail_count`, `window_start`, `locked_until`) VALUES
(1, '::1', 0, '2026-10-06 11:08:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_attempts`
--

CREATE TABLE `tbl_login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `lockout_level` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_attempt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `login_ok` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_login_attempts`
--

INSERT INTO `tbl_login_attempts` (`id`, `identifier`, `ip_address`, `attempt_count`, `lockout_level`, `locked_until`, `last_attempt`, `login_ok`) VALUES
(1, 'stephenperez@gmail.com', '::1', 2, 0, NULL, '2026-10-05 20:13:15', 0),
(2, 'chico@admin.edu', '::1', 0, 0, NULL, '2026-10-02 13:12:58', 1),
(3, 'mendza@gmil.com', '::1', 2, 0, NULL, '2026-10-02 13:11:52', 0),
(5, 'mendoza@gmail.com', '::1', 2, 0, NULL, '2026-10-02 13:16:50', 0),
(7, 'iamfrederickr@gmail.com', '::1', 0, 0, NULL, '2026-10-03 22:50:16', 1),
(8, 'iamfrederick@gmail.com', '::1', 1, 0, NULL, '2026-10-03 11:04:52', 0),
(9, 'dawdad@gmail.com', '::1', 0, 1, '2026-10-03 22:54:44', '2026-10-03 22:49:44', 0),
(12, 'dawda@gmail.com', '::1', 1, 0, NULL, '2026-10-03 22:49:58', 0),
(14, 'stephenperez@pupsync.edu', '::1', 0, 0, NULL, '2026-10-06 08:19:45', 1),
(18, 'main@admin.edu', '::1', 0, 0, NULL, '2026-10-06 03:05:35', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notif_state`
--

CREATE TABLE `tbl_notif_state` (
  `id` int(11) NOT NULL,
  `notif_key` varchar(64) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_notif_state`
--

INSERT INTO `tbl_notif_state` (`id`, `notif_key`, `is_read`, `is_deleted`, `updated_at`) VALUES
(1, 'overdue-21', 1, 0, '2026-10-03 11:48:37'),
(2, 'overdue-13', 1, 0, '2026-10-03 11:48:39');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_organizations`
--

CREATE TABLE `tbl_organizations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `category` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_organizations`
--

INSERT INTO `tbl_organizations` (`id`, `name`, `created_at`, `category`) VALUES
(1, 'IBITS', '2026-10-01 13:05:15', 'Academic'),
(2, 'YES', '2026-10-01 13:05:15', 'Academic'),
(3, 'ACES', '2026-10-01 13:05:15', 'Academic'),
(5, 'HRSS', '2026-10-06 04:16:02', 'Academic'),
(6, 'PIIE', '2026-10-06 04:16:02', 'Academic'),
(7, 'SMS', '2026-10-06 04:16:02', 'Academic'),
(8, 'LENS', '2026-10-06 04:16:02', 'Non-academic'),
(9, 'AWS', '2026-10-06 04:16:02', 'Non-academic'),
(10, 'NXTGEN', '2026-10-06 04:16:02', 'Non-academic'),
(11, 'CSC', '2026-10-06 04:16:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_requests`
--

CREATE TABLE `tbl_requests` (
  `id` int(11) NOT NULL,
  `faculty_name` varchar(255) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `instructor` varchar(255) NOT NULL,
  `room` varchar(100) NOT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date NOT NULL,
  `return_token` varchar(64) DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Waiting',
  `request_date` datetime DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `arbitration_rule` varchar(50) DEFAULT NULL,
  `submitted_by_name` varchar(255) DEFAULT NULL,
  `submitted_by_id` varchar(50) DEFAULT NULL,
  `submitted_as` varchar(20) DEFAULT NULL,
  `batch_id` char(36) DEFAULT NULL,
  `borrow_time` time DEFAULT NULL,
  `return_time` time DEFAULT NULL,
  `borrow_qty` int(11) NOT NULL DEFAULT 1,
  `room_id` int(11) DEFAULT NULL,
  `booking_mode` varchar(10) DEFAULT NULL,
  `stock_applied` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_requests`
--

INSERT INTO `tbl_requests` (`id`, `faculty_name`, `faculty_id`, `equipment_name`, `instructor`, `room`, `borrow_date`, `return_date`, `return_token`, `returned_at`, `status`, `request_date`, `reason`, `document_path`, `arbitration_rule`, `submitted_by_name`, `submitted_by_id`, `submitted_as`, `batch_id`, `borrow_time`, `return_time`, `borrow_qty`, `room_id`, `booking_mode`, `stock_applied`) VALUES
(1, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'Sir Migs', 'A305', '2026-01-15', '2026-01-16', NULL, NULL, 'Overdue', '2026-01-15 11:04:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(2, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'elaine', 'B403', '2026-01-15', '2026-01-15', NULL, NULL, 'Declined', '2026-01-15 11:08:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(3, 'Frederick Rosales', '2023-00251-BN-0', 'Extension', 'Sir Migs', 'B203', '2026-01-23', '2026-01-24', NULL, NULL, 'Returned', '2026-01-15 12:28:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(4, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'Ma\'am Donna', 'E031', '2026-02-05', '2026-02-12', NULL, NULL, 'Returned', '2026-01-15 12:30:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(5, 'John Jr.', '2030-00071-BN-0', 'AC Remote', 'Sir Migs', 'B203', '2026-01-15', '2026-01-16', NULL, NULL, 'Overdue', '2026-01-15 13:51:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(6, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'Ma\'am Donna', 'B205', '2026-02-19', '2026-02-22', NULL, NULL, 'Returned', '2026-02-18 00:27:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(7, 'Aiello Gabriel B. Lastrella', '2023-00294-BN-0', 'HDMI Cable', 'Sir Migs', 'Room A304', '2026-02-20', '2026-02-20', NULL, NULL, 'Overdue', '2026-02-19 15:07:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(8, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'sir noy', 'B304', '2026-02-23', '2026-02-25', NULL, NULL, 'Declined', '2026-02-22 17:29:16', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(9, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'ma\'am JJ', 'A901', '2026-03-01', '2026-03-09', NULL, NULL, 'Declined', '2026-02-22 17:31:37', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(10, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Sir ajon', 'B207', '2026-02-25', '2026-02-26', NULL, NULL, 'Declined', '2026-02-22 17:38:07', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(11, 'Derick Ramsey', '2023-00651-BN-0', 'AC Remote', 'jojo', 'b703', '2026-03-12', '2026-03-21', NULL, NULL, 'Returned', '2026-02-22 17:38:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(12, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'joyce', 'b203', '2026-02-23', '2026-02-24', NULL, NULL, 'Declined', '2026-02-22 17:52:27', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(13, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'noy', 'j012', '2026-03-12', '2026-03-13', NULL, NULL, 'Overdue', '2026-02-22 17:53:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(14, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'sir redg', 'b201', '2026-02-24', '2026-02-25', NULL, NULL, 'Declined', '2026-02-23 17:46:05', 'Request expired – borrow date has already passed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(15, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'sir aaron', 'B301', '2026-03-13', '2026-03-20', NULL, NULL, 'Returned', '2026-03-12 11:45:02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(16, 'Frederick Rosales', '2023-00251-BN-0', 'Extension', 'Sir Migs', 'B205', '2026-03-13', '2026-03-14', NULL, NULL, 'Returned', '2026-03-12 13:05:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(17, 'Sandy Napiza', '2023-00004-BN-0', 'AC Remote', 'Sandy Napiza', '210', '2026-06-04', '2026-06-05', NULL, '2026-06-04 13:47:37', 'Returned', '2026-06-04 13:46:58', NULL, NULL, NULL, 'sandy', '2023-00004-BN-0', NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(18, 'Sandy Napiza', '2023-00004-BN-0', 'AC Remote', 'Sandy Napiza', '278', '2026-06-06', '2026-06-06', NULL, '2026-06-06 21:43:09', 'Returned', '2026-06-06 21:37:09', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(19, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Frederick Rosales', '201', '2026-06-18', '2026-06-19', NULL, '2026-06-06 21:59:40', 'Returned', '2026-06-06 21:55:17', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(20, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Frederick Rosales', '204', '2026-06-24', '2026-06-25', NULL, '2026-06-06 22:02:11', 'Returned', '2026-06-06 22:00:39', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(21, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Frederick Rosales', 'B203', '2026-06-12', '2026-06-19', '476ea34754a96f645c20b907493adf7e6e8b0b585651a30ce7a4a99877b399b7', NULL, 'Overdue', '2026-06-06 22:09:27', NULL, NULL, NULL, 'Kiloman', '2023-00250-BN-0', NULL, NULL, NULL, NULL, 1, NULL, NULL, 1),
(22, 'Michael Anjelo O. Miguel', '2026-00001-BN-0', 'AC Remote', 'Michael Anjelo O. Miguel', 'lab301', '2026-10-03', '2026-10-05', 'd4152e5b1e9d887aa60295d1e80ee84374364b232fc08b1e5cc51fd5edcda8a5', NULL, 'Overdue', '2026-10-02 08:23:50', 'Request approved via FIFO priority scoring.', 'uploads/request_letters/1790900630_2026-00001-BN-0_ROSALES_IBITS.pdf', 'rule_1_fifo', NULL, NULL, 'personal', NULL, NULL, NULL, 1, NULL, NULL, 1),
(23, 'Michael Anjelo O. Miguel', '2026-00001-BN-0', 'HDMI Cable', 'Michael Anjelo O. Miguel', 'lab201', '2026-10-02', '2026-10-03', '0c0fe459f07c0dc4c07f660b3863d2a8f942ce124f942052981f2161093f93c4', NULL, 'Overdue', '2026-10-02 08:25:12', 'Request approved via FIFO priority scoring.', 'uploads/request_letters/1790900712_2026-00001-BN-0_TEAM_ANG_LOGO.png', 'rule_1_fifo', NULL, NULL, 'personal', NULL, NULL, NULL, 1, NULL, NULL, 1),
(24, 'Michael Anjelo Miguel', 'NOTSET-408F953BDD2D', 'Double A Batter', 'Michael Anjelo Miguel', 'Room301', '2026-10-05', '2026-10-05', '1453570d553cd201afbff1731afec6a7e3bf5455a6104febf6667d1913a2c05d', NULL, 'Overdue', '2026-10-05 20:04:28', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL, 'personal', NULL, NULL, NULL, 1, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rooms`
--

CREATE TABLE `tbl_rooms` (
  `room_id` int(11) NOT NULL,
  `building_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `floor_number` tinyint(3) NOT NULL DEFAULT 1,
  `floor_label` varchar(50) DEFAULT NULL,
  `seating_capacity` smallint(5) DEFAULT NULL,
  `amenities` varchar(500) DEFAULT NULL,
  `status` enum('Available','Maintenance','Not Bookable') NOT NULL DEFAULT 'Available',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_rooms`
--

INSERT INTO `tbl_rooms` (`room_id`, `building_id`, `room_name`, `floor_number`, `floor_label`, `seating_capacity`, `amenities`, `status`, `is_archived`, `sort_order`, `created_at`) VALUES
(1, 1, 'Admin Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 1, '2026-10-01 13:05:16'),
(2, 1, 'Registration Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 2, '2026-10-01 13:05:16'),
(3, 1, 'OSAS Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 3, '2026-10-01 13:05:16'),
(4, 1, 'Office 1', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 4, '2026-10-01 13:05:16'),
(5, 1, 'Clinic', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 5, '2026-10-01 13:05:16'),
(6, 1, 'Staff Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 6, '2026-10-01 13:05:16'),
(7, 1, 'Room 201', 2, '2nd Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(8, 1, 'Room 202', 2, '2nd Floor', NULL, NULL, 'Available', 0, 2, '2026-10-01 13:05:16'),
(9, 1, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(10, 1, 'Room 204', 2, '2nd Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(11, 1, 'Room 205', 2, '2nd Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(12, 1, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(13, 1, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 2, '2026-10-01 13:05:16'),
(14, 1, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(15, 1, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(16, 1, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(17, 1, 'Org Room', 4, '4th Floor', NULL, NULL, 'Not Bookable', 0, 1, '2026-10-01 13:05:16'),
(18, 1, 'CSC Room', 4, '4th Floor', NULL, NULL, 'Not Bookable', 0, 2, '2026-10-01 13:05:16'),
(19, 1, 'AVR 2', 4, '4th Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(20, 1, 'Computer Laboratory 1', 4, '4th Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(21, 1, 'Computer Laboratory 2', 4, '4th Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(22, 1, 'Chemistry Laboratory', 5, '5th Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(23, 2, 'Library', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 1, '2026-10-01 13:05:16'),
(24, 2, 'Directors Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 2, '2026-10-01 13:05:16'),
(25, 2, 'Faculty Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 3, '2026-10-01 13:05:16'),
(26, 2, 'DO Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 4, '2026-10-01 13:05:16'),
(27, 2, 'Guidance Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 5, '2026-10-01 13:05:16'),
(28, 2, 'Research Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(29, 2, 'Room 202', 2, '2nd Floor', NULL, NULL, 'Available', 0, 2, '2026-10-01 13:05:16'),
(30, 2, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(31, 2, 'Room 204', 2, '2nd Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(32, 2, 'Room 205', 2, '2nd Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(33, 2, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(34, 2, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 2, '2026-10-01 13:05:16'),
(35, 2, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(36, 2, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(37, 2, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(38, 2, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(39, 2, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 2, '2026-10-01 13:05:16'),
(40, 2, 'AVR 1', 4, '4th Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(41, 2, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(42, 2, 'Room 501', 5, '5th Floor', NULL, NULL, 'Available', 1, 1, '2026-10-01 13:05:16'),
(43, 2, 'Room 502', 5, '5th Floor', NULL, NULL, 'Available', 1, 2, '2026-10-01 13:05:16'),
(44, 2, 'Room 503', 5, '5th Floor', NULL, NULL, 'Available', 1, 3, '2026-10-01 13:05:16'),
(45, 2, 'Room 504', 5, '5th Floor', NULL, NULL, 'Available', 1, 4, '2026-10-01 13:05:16'),
(46, 2, 'Room 505', 5, '5th Floor', NULL, NULL, 'Available', 1, 5, '2026-10-01 13:05:16'),
(47, 2, 'Room 506', 5, '5th Floor', NULL, NULL, 'Available', 1, 6, '2026-10-01 13:05:16'),
(48, 2, 'Room 507', 5, '5th Floor', NULL, NULL, 'Available', 1, 7, '2026-10-01 13:05:16'),
(49, 2, 'Room 508', 5, '5th Floor', NULL, NULL, 'Available', 1, 8, '2026-10-01 13:05:16'),
(50, 2, 'Room 509', 5, '5th Floor', NULL, NULL, 'Available', 1, 9, '2026-10-01 13:05:16'),
(51, 3, 'Prayer Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 1, '2026-10-01 13:05:16'),
(52, 3, 'Audiovisual Room', 1, '1st Floor', NULL, NULL, 'Available', 0, 2, '2026-10-01 13:05:16'),
(53, 3, 'Testing Area', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 3, '2026-10-01 13:05:16'),
(54, 3, 'Student Organization Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 4, '2026-10-01 13:05:16'),
(55, 3, 'Clinic Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 5, '2026-10-01 13:05:16'),
(56, 3, 'Industrial Engineering Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 6, '2026-10-01 13:05:16'),
(57, 3, 'Director\'s Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 7, '2026-10-01 13:05:16'),
(58, 3, 'Basketball Court', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 8, '2026-10-01 13:05:16'),
(59, 3, 'Room 103', 1, '1st Floor', NULL, NULL, 'Available', 0, 9, '2026-10-01 13:05:16'),
(60, 3, 'Room 105', 1, '1st Floor', NULL, NULL, 'Available', 0, 10, '2026-10-01 13:05:16'),
(61, 3, 'Room 106', 1, '1st Floor', NULL, NULL, 'Available', 0, 11, '2026-10-01 13:05:16'),
(62, 3, 'Room 116', 1, '1st Floor', NULL, NULL, 'Available', 0, 12, '2026-10-01 13:05:16'),
(63, 3, 'Room 118', 1, '1st Floor', NULL, NULL, 'Available', 0, 13, '2026-10-01 13:05:16'),
(64, 3, 'Room 119', 1, '1st Floor', NULL, NULL, 'Available', 0, 14, '2026-10-01 13:05:16'),
(65, 3, 'Admin Office', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 0, 1, '2026-10-01 13:05:16'),
(66, 3, 'Faculty Lounge', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 0, 2, '2026-10-01 13:05:16'),
(67, 3, 'AutoCAD & Multimedia Laboratory', 2, '2nd Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(68, 3, 'Computer Laboratory 1', 2, '2nd Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(69, 3, 'Computer Laboratory 2', 2, '2nd Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(70, 3, 'Computer Laboratory 3', 2, '2nd Floor', NULL, NULL, 'Available', 0, 6, '2026-10-01 13:05:16'),
(71, 3, 'Ergonomics Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 7, '2026-10-01 13:05:16'),
(72, 3, 'Digital Laboratory Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 8, '2026-10-01 13:05:16'),
(73, 3, 'Dispensing Room', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 0, 9, '2026-10-01 13:05:16'),
(74, 3, 'Microprocessing Laboratory Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 10, '2026-10-01 13:05:16'),
(75, 3, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 11, '2026-10-01 13:05:16'),
(76, 3, 'Room 210', 2, '2nd Floor', NULL, NULL, 'Available', 0, 12, '2026-10-01 13:05:16'),
(77, 3, 'Room 212', 2, '2nd Floor', NULL, NULL, 'Available', 0, 13, '2026-10-01 13:05:16'),
(78, 3, 'Room 218', 2, '2nd Floor', NULL, NULL, 'Available', 0, 14, '2026-10-01 13:05:16'),
(79, 3, 'Library Room', 3, '3rd Floor', NULL, NULL, 'Not Bookable', 0, 1, '2026-10-01 13:05:16'),
(80, 3, 'Library Extension Room', 3, '3rd Floor', NULL, NULL, 'Not Bookable', 0, 2, '2026-10-01 13:05:16'),
(81, 3, 'Physics Room', 3, '3rd Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(82, 3, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(83, 3, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(84, 3, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 6, '2026-10-01 13:05:16'),
(85, 3, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 7, '2026-10-01 13:05:16'),
(86, 3, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 8, '2026-10-01 13:05:16'),
(87, 3, 'Room 307', 3, '3rd Floor', NULL, NULL, 'Available', 0, 9, '2026-10-01 13:05:16'),
(88, 3, 'Room 308', 3, '3rd Floor', NULL, NULL, 'Available', 0, 10, '2026-10-01 13:05:16'),
(89, 3, 'Room 309', 3, '3rd Floor', NULL, NULL, 'Available', 0, 11, '2026-10-01 13:05:16'),
(90, 3, 'Room 310', 3, '3rd Floor', NULL, NULL, 'Available', 0, 12, '2026-10-01 13:05:16'),
(91, 3, 'Chemistry Laboratory Room', 4, '4th Floor', NULL, NULL, 'Available', 0, 1, '2026-10-01 13:05:16'),
(92, 3, 'Student Lounge', 4, '4th Floor', NULL, NULL, 'Not Bookable', 0, 2, '2026-10-01 13:05:16'),
(93, 3, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 3, '2026-10-01 13:05:16'),
(94, 3, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 4, '2026-10-01 13:05:16'),
(95, 3, 'Room 403', 4, '4th Floor', NULL, NULL, 'Available', 0, 5, '2026-10-01 13:05:16'),
(96, 3, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 6, '2026-10-01 13:05:16'),
(97, 3, 'Room 406', 4, '4th Floor', NULL, NULL, 'Available', 0, 7, '2026-10-01 13:05:16'),
(98, 3, 'Room 415', 4, '4th Floor', NULL, NULL, 'Available', 0, 8, '2026-10-01 13:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_arbitration_log`
--

CREATE TABLE `tbl_room_arbitration_log` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `borrower_id` varchar(50) NOT NULL,
  `borrower_name` varchar(100) NOT NULL,
  `decision` varchar(20) NOT NULL,
  `rule_applied` varchar(60) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_room_arbitration_log`
--

INSERT INTO `tbl_room_arbitration_log` (`id`, `reservation_id`, `room_id`, `room_name`, `borrower_id`, `borrower_name`, `decision`, `rule_applied`, `reason`, `created_at`) VALUES
(1, 1, 8, 'Room 202', '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-02 08:19:29'),
(2, 2, 7, 'Room 201', '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-02 08:21:22'),
(3, 3, 67, 'AutoCAD & Multimedia Laboratory', '2026-00002-BN-0', 'Stephen Perez', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-03 16:43:55'),
(4, 4, 67, 'AutoCAD & Multimedia Laboratory', '2026-00002-BN-0', 'Stephen Perez', 'Declined', 'rule_1_fifo', 'This time slot is already reserved by a higher-priority request.', '2026-10-03 16:44:38'),
(5, 5, 7, 'Room 201', 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-05 20:06:40'),
(6, 6, 71, 'Ergonomics Room', 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-05 20:11:38'),
(7, 7, 7, 'Room 201', 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Declined', 'rule_1_fifo', 'This time slot is already reserved by a higher-priority request.', '2026-10-06 02:58:43'),
(8, 8, 7, 'Room 201', 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Declined', 'rule_1_fifo', 'This time slot is already reserved by a higher-priority request.', '2026-10-06 03:00:22'),
(9, 9, 7, 'Room 201', 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-06 03:00:53');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_issues`
--

CREATE TABLE `tbl_room_issues` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `reported_by_id` varchar(50) NOT NULL,
  `reported_by_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Open','Resolved','Dismissed') NOT NULL DEFAULT 'Open',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_reservations`
--

CREATE TABLE `tbl_room_reservations` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `submitted_as` varchar(20) NOT NULL DEFAULT 'personal',
  `submitted_by_name` varchar(100) DEFAULT NULL,
  `submitted_by_id` varchar(50) DEFAULT NULL,
  `purpose` varchar(200) NOT NULL,
  `attendees` smallint(5) NOT NULL DEFAULT 1,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `notes` text DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('Approved','Declined','Cancelled') NOT NULL DEFAULT 'Approved',
  `reason` varchar(255) DEFAULT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_room_reservations`
--

INSERT INTO `tbl_room_reservations` (`id`, `room_id`, `faculty_id`, `faculty_name`, `submitted_as`, `submitted_by_name`, `submitted_by_id`, `purpose`, `attendees`, `reservation_date`, `start_time`, `end_time`, `notes`, `document_path`, `status`, `reason`, `request_date`, `cancelled_at`) VALUES
(1, 8, '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'adviser', NULL, NULL, 'Lab session', 50, '2026-10-02', '09:30:00', '12:30:00', NULL, 'uploads/room_documents/20261002_081929_2026-00001-BN-0_7d5d175a.pdf', 'Approved', 'Room reservation approved — no time conflict.', '2026-10-02 08:19:29', NULL),
(2, 7, '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'personal', NULL, NULL, 'Lecture', 40, '2026-10-03', '08:30:00', '10:30:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-02 08:21:22', NULL),
(3, 67, '2026-00002-BN-0', 'Stephen Perez', 'personal', NULL, NULL, 'Lab session', 32767, '2026-10-03', '17:00:00', '20:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-03 16:43:55', NULL),
(4, 67, '2026-00002-BN-0', 'Stephen Perez', 'personal', NULL, NULL, 'Meeting', 30, '2026-10-03', '17:00:00', '18:00:00', NULL, NULL, 'Declined', 'This time slot is already reserved by a higher-priority request.', '2026-10-03 16:44:38', NULL),
(5, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-06', '08:00:00', '11:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-05 20:06:40', NULL),
(6, 71, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-08', '11:00:00', '14:00:00', NULL, NULL, 'Cancelled', 'Room reservation approved — no time conflict.', '2026-10-05 20:11:38', '2026-10-06 02:56:10'),
(7, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lab session', 1, '2026-10-06', '07:00:00', '12:00:00', NULL, NULL, 'Declined', 'This time slot is already reserved by a higher-priority request.', '2026-10-06 02:58:43', NULL),
(8, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lab session', 1, '2026-10-06', '10:30:00', '15:30:00', NULL, NULL, 'Declined', 'This time slot is already reserved by a higher-priority request.', '2026-10-06 03:00:22', NULL),
(9, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lab session', 1, '2026-10-06', '11:00:00', '16:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-06 03:00:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_waitlist`
--

CREATE TABLE `tbl_room_waitlist` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `faculty_email` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `fullname` varchar(255) NOT NULL,
  `faculty_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `backup_email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `last_password_change` datetime DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `faculty_rank` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `landline` varchar(20) DEFAULT NULL,
  `emergency_name` varchar(120) DEFAULT NULL,
  `emergency_relationship` varchar(50) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'Regular Faculty',
  `is_org_adviser` tinyint(1) DEFAULT 0,
  `organization_id` int(11) DEFAULT NULL,
  `allow_org_borrowing` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`fullname`, `faculty_id`, `email`, `backup_email`, `password`, `last_password_change`, `dob`, `gender`, `nationality`, `profile_picture`, `department`, `faculty_rank`, `phone`, `present_address`, `permanent_address`, `landline`, `emergency_name`, `emergency_relationship`, `emergency_phone`, `role`, `is_org_adviser`, `organization_id`, `allow_org_borrowing`) VALUES
('Edmundo Dela Cruz', 'NOTSET-275A970376E5', 'edmundodelacruz@pupsync.edu', NULL, '$2y$10$C4byb8lLTyY/5YbcfLjzV.1rCSK6.rx93EzyG6LGrP7IAkyt1oGWO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty', 0, NULL, 0),
('Michael Anjelo Miguel', 'NOTSET-408F953BDD2D', 'michaelanjelomiguel@pupsync.edu', NULL, '$2y$10$fNYBe9j/ocdqv1rC2sizmu9Vn6GKjolJxXn0AbFhl0qU.wmH63W.y', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Organization Adviser', 1, 1, 1),
('Indaleen Quinsayas', 'NOTSET-B9DEB33EB03E', 'indaleenquinsayas@pupsync.edu', NULL, '$2y$10$xTwnSJMIhXQHi6hJT5MUOunMIMwBA5wsxep3Gnx5ER2krqel9aAOu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty', 0, NULL, 0),
('Stephen Perez', 'NOTSET-F15E038389ED', 'stephenperez@pupsync.edu', NULL, '$2y$10$r9wvt4o7CHvR2LXMjOK/oeNSGPT1gvjs8ochesyL5S2unNRf/kUYi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty', 0, NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tbl_arbitration_config`
--
ALTER TABLE `tbl_arbitration_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `tbl_arbitration_log`
--
ALTER TABLE `tbl_arbitration_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_request_id` (`request_id`);

--
-- Indexes for table `tbl_buildings`
--
ALTER TABLE `tbl_buildings`
  ADD PRIMARY KEY (`building_id`),
  ADD UNIQUE KEY `uq_building_key` (`building_key`),
  ADD KEY `fk_buildings_campus` (`campus_id`);

--
-- Indexes for table `tbl_campuses`
--
ALTER TABLE `tbl_campuses`
  ADD PRIMARY KEY (`campus_id`),
  ADD UNIQUE KEY `uq_campus_key` (`campus_key`);

--
-- Indexes for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_fc_faculty` (`faculty_id`),
  ADD KEY `idx_fc_code` (`code`);

--
-- Indexes for table `tbl_faculty_notif_state`
--
ALTER TABLE `tbl_faculty_notif_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_faculty_notif` (`faculty_id`,`notif_key`);

--
-- Indexes for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `tbl_ip_login_attempts`
--
ALTER TABLE `tbl_ip_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ip` (`ip_address`),
  ADD KEY `idx_ip_locked` (`locked_until`);

--
-- Indexes for table `tbl_login_attempts`
--
ALTER TABLE `tbl_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ident_ip` (`identifier`(191),`ip_address`),
  ADD KEY `idx_locked_until` (`locked_until`),
  ADD KEY `idx_last_attempt` (`last_attempt`);

--
-- Indexes for table `tbl_notif_state`
--
ALTER TABLE `tbl_notif_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notif_key` (`notif_key`);

--
-- Indexes for table `tbl_organizations`
--
ALTER TABLE `tbl_organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_name` (`name`);

--
-- Indexes for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_token` (`return_token`),
  ADD KEY `idx_return_token` (`return_token`);

--
-- Indexes for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `fk_rooms_building` (`building_id`);

--
-- Indexes for table `tbl_room_arbitration_log`
--
ALTER TABLE `tbl_room_arbitration_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation_id` (`reservation_id`);

--
-- Indexes for table `tbl_room_issues`
--
ALTER TABLE `tbl_room_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_issues_room` (`room_id`),
  ADD KEY `idx_issues_status` (`status`);

--
-- Indexes for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_date` (`room_id`,`reservation_date`);

--
-- Indexes for table `tbl_room_waitlist`
--
ALTER TABLE `tbl_room_waitlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_waitlist_faculty_slot` (`room_id`,`reservation_date`,`start_time`,`end_time`,`faculty_id`),
  ADD KEY `idx_waitlist_slot` (`room_id`,`reservation_date`,`start_time`,`end_time`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_org` (`organization_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_arbitration_config`
--
ALTER TABLE `tbl_arbitration_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_arbitration_log`
--
ALTER TABLE `tbl_arbitration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_buildings`
--
ALTER TABLE `tbl_buildings`
  MODIFY `building_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_campuses`
--
ALTER TABLE `tbl_campuses`
  MODIFY `campus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_faculty_notif_state`
--
ALTER TABLE `tbl_faculty_notif_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tbl_ip_login_attempts`
--
ALTER TABLE `tbl_ip_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_login_attempts`
--
ALTER TABLE `tbl_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tbl_notif_state`
--
ALTER TABLE `tbl_notif_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_organizations`
--
ALTER TABLE `tbl_organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `tbl_room_arbitration_log`
--
ALTER TABLE `tbl_room_arbitration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_room_issues`
--
ALTER TABLE `tbl_room_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_room_waitlist`
--
ALTER TABLE `tbl_room_waitlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_buildings`
--
ALTER TABLE `tbl_buildings`
  ADD CONSTRAINT `fk_buildings_campus` FOREIGN KEY (`campus_id`) REFERENCES `tbl_campuses` (`campus_id`);

--
-- Constraints for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  ADD CONSTRAINT `fk_rooms_building` FOREIGN KEY (`building_id`) REFERENCES `tbl_buildings` (`building_id`);

--
-- Constraints for table `tbl_room_issues`
--
ALTER TABLE `tbl_room_issues`
  ADD CONSTRAINT `fk_issue_room` FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`);

--
-- Constraints for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  ADD CONSTRAINT `fk_rr_room` FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`);

--
-- Constraints for table `tbl_room_waitlist`
--
ALTER TABLE `tbl_room_waitlist`
  ADD CONSTRAINT `fk_wl_room` FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`);

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `fk_users_org` FOREIGN KEY (`organization_id`) REFERENCES `tbl_organizations` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
