-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 09, 2026 at 12:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.4.27

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
  `session_epoch` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_password_change` datetime DEFAULT NULL,
  `backup_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_accounts`
--

INSERT INTO `tbl_accounts` (`id`, `fullName`, `email`, `password`, `role`, `created_at`, `last_login`, `dormant_until`, `session_epoch`, `last_password_change`, `backup_email`) VALUES
(1, 'Redg Admin', 'main@admin.edu', '$2y$10$dLNCtd5IGqTMf7VUHFYyEOPI00YWyd9h9n4uj6dTptlcKwFNcw57e', 'Super Admin', '2026-01-01 00:00:00', '2026-11-09 07:32:53', NULL, 0, NULL, NULL),
(2, 'Chico Ramos', 'chico@admin.edu', '$2y$10$w.G7jIE6r6iSsVyn/T/G2OAhoqpyxgu4kkWKbHIhOky/CJk94xLGC', 'Admin', '2026-10-02 11:05:03', '2026-11-08 15:00:44', NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_admin_activity`
--

CREATE TABLE `tbl_admin_activity` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `category` varchar(20) NOT NULL,
  `action` varchar(30) DEFAULT NULL,
  `summary` varchar(255) NOT NULL,
  `ref_type` varchar(12) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `ref` varchar(40) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_admin_activity`
--

INSERT INTO `tbl_admin_activity` (`id`, `admin_id`, `admin_name`, `category`, `action`, `summary`, `ref_type`, `ref_id`, `ref`, `created_at`) VALUES
(1, 1, 'Redg Admin', 'request', 'request_returned', 'Marked AC Remote as returned (Sandy Napiza)', 'request', 17, 'arb:1', '2026-06-04 13:47:37'),
(2, 1, 'Redg Admin', 'request', 'request_returned', 'Marked AC Remote as returned (Sandy Napiza)', 'request', 18, 'arb:2', '2026-06-06 21:43:09'),
(3, 1, 'Redg Admin', 'request', 'request_returned', 'Marked AC Remote as returned (Frederick Rosales)', 'request', 19, 'arb:4', '2026-06-06 21:59:40'),
(4, 1, 'Redg Admin', 'request', 'request_returned', 'Marked AC Remote as returned (Frederick Rosales)', 'request', 20, 'arb:6', '2026-06-06 22:02:11'),
(5, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-10-18 11:17:49'),
(6, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-10-18 16:08:18'),
(7, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-10-22 15:45:40'),
(8, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 5 inserted, 0 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 17:25:52'),
(9, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for Aaron Atienza', NULL, NULL, NULL, '2026-10-22 17:50:48'),
(10, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for ALonica ebo', NULL, NULL, NULL, '2026-10-22 18:03:53'),
(11, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 92 inserted, 0 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 19:40:14'),
(12, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 19 inserted, 73 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 19:44:29'),
(13, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 92 inserted, 0 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 19:54:15'),
(14, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 0 inserted, 92 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 19:56:33'),
(15, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 92 inserted, 0 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 19:58:06'),
(16, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-10-22 20:24:22'),
(17, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 92 inserted, 0 updated, 0 skipped', NULL, NULL, NULL, '2026-10-22 20:26:14'),
(18, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-10-24 17:33:45'),
(19, 1, 'Redg Admin', 'request', 'request_returned', 'Marked Double A Batter as returned (Edmundo Dela Cruz)', 'request', 25, NULL, '2026-10-24 17:35:45'),
(20, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-06 07:40:24'),
(21, 1, 'Redg Admin', 'faculty', NULL, 'Deleted faculty account of Stephen Perez', NULL, NULL, NULL, '2026-11-06 07:41:11'),
(22, 1, 'Redg Admin', 'faculty', NULL, 'Deleted faculty account of Michael Anjelo Miguel', NULL, NULL, NULL, '2026-11-06 07:41:17'),
(23, 1, 'Redg Admin', 'faculty', NULL, 'Deleted faculty account of Indaleen Quinsayas', NULL, NULL, NULL, '2026-11-06 07:41:23'),
(24, 1, 'Redg Admin', 'faculty', NULL, 'Deleted faculty account of Edmundo Dela Cruz', NULL, NULL, NULL, '2026-11-06 07:41:27'),
(25, 1, 'Redg Admin', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-06 07:44:00'),
(26, 1, 'Redg Admin', 'faculty', NULL, 'Uploaded faculty registry: 92 inserted, 0 updated, 0 skipped (file: Faculty Identification Records - Sheet1.pdf, replaced the previous file)', NULL, NULL, NULL, '2026-11-06 07:57:30'),
(27, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for Michael Anjelo Miguel', NULL, NULL, NULL, '2026-11-06 07:58:19'),
(28, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for Edmundo Dela Cruz', NULL, NULL, NULL, '2026-11-06 07:59:19'),
(29, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for Indaleen Quinsayas', NULL, NULL, NULL, '2026-11-06 07:59:50'),
(30, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for Aaron Atienza', NULL, NULL, NULL, '2026-11-06 08:00:20'),
(31, 1, 'Redg Admin', 'faculty', NULL, 'Created faculty account for Stephen Perez', NULL, NULL, NULL, '2026-11-06 08:06:16'),
(32, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-06 08:08:18'),
(33, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-08 14:48:06'),
(34, 1, 'Redg Admin', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-08 14:50:24'),
(35, 2, 'Chico Ramos', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-08 15:00:44'),
(36, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: Test Raspberry Pi Kit', NULL, NULL, NULL, '2026-11-08 16:34:38'),
(37, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-08 16:38:15'),
(38, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: Ostin', NULL, NULL, NULL, '2026-11-08 16:48:06'),
(39, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: Ostin', NULL, NULL, NULL, '2026-11-08 16:48:11'),
(40, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: Helloo', NULL, NULL, NULL, '2026-11-08 16:48:26'),
(41, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: hotdog', NULL, NULL, NULL, '2026-11-08 17:00:06'),
(42, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: amoy pwet', NULL, NULL, NULL, '2026-11-08 17:15:36'),
(43, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-08 18:38:59'),
(44, 1, 'Redg Admin', 'equipment', NULL, 'Archived equipment: test item', NULL, NULL, NULL, '2026-11-08 19:31:09'),
(45, 1, 'Redg Admin', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-08 22:06:14'),
(46, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-09 07:32:52'),
(47, 1, 'Redg Admin', 'request', NULL, 'Deleted the entire On Loan list (20 records)', NULL, NULL, NULL, '2026-11-09 10:38:49'),
(48, 1, 'Redg Admin', 'request', 'request_claimed', 'Confirmed claim of Adaptor by Edmundo Dela Cruz', 'request', 27, NULL, '2026-11-09 10:48:22'),
(49, 1, 'Redg Admin', 'request', 'request_returned', 'Marked Adaptor as returned (Edmundo Dela Cruz)', 'request', 27, NULL, '2026-11-09 10:57:07'),
(50, 1, 'Redg Admin', 'request', 'request_claimed', 'Confirmed claim of Ammeter (15A) by Edmundo Dela Cruz', 'request', 54, NULL, '2026-11-09 16:03:17'),
(51, 1, 'Redg Admin', 'request', 'request_claimed', 'Confirmed claim of Ammeter (20A) by Edmundo Dela Cruz', 'request', 55, NULL, '2026-11-09 16:03:17'),
(52, 1, 'Redg Admin', 'request', 'request_claimed', 'Confirmed claim of Ammeter (30A) by Edmundo Dela Cruz', 'request', 56, NULL, '2026-11-09 16:03:17'),
(53, 1, 'Redg Admin', 'request', 'request_returned', 'Returned Ammeter (15A) (Good) — Edmundo Dela Cruz', 'request', 54, NULL, '2026-11-09 16:21:11'),
(54, 1, 'Redg Admin', 'request', 'request_returned', 'Returned Ammeter (20A) (Good) — Edmundo Dela Cruz', 'request', 55, NULL, '2026-11-09 16:21:11'),
(55, 1, 'Redg Admin', 'request', 'request_returned', 'Returned Ammeter (30A) (Good) — Edmundo Dela Cruz', 'request', 56, NULL, '2026-11-09 16:21:11'),
(56, 1, 'Redg Admin', 'request', NULL, 'Deleted the entire On Loan list (7 records)', NULL, NULL, NULL, '2026-11-09 18:19:43');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_ai_chat_log`
--

CREATE TABLE `tbl_ai_chat_log` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_ai_chat_log`
--

INSERT INTO `tbl_ai_chat_log` (`id`, `faculty_id`, `sent_at`) VALUES
(142, 'NOTSET-275A970376E5', '2026-10-24 17:28:30'),
(143, 'NOTSET-275A970376E5', '2026-10-24 17:28:52'),
(144, 'NOTSET-275A970376E5', '2026-10-25 09:26:43'),
(145, 'NOTSET-275A970376E5', '2026-10-25 09:27:01'),
(146, 'NOTSET-275A970376E5', '2026-10-25 09:38:32'),
(147, 'NOTSET-275A970376E5', '2026-10-25 09:56:00'),
(148, 'NOTSET-275A970376E5', '2026-10-25 09:57:47'),
(149, 'NOTSET-275A970376E5', '2026-10-25 10:27:26'),
(150, 'NOTSET-275A970376E5', '2026-10-25 10:42:34');

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
(4, 'role_priority_faculty', '2', '2026-06-04 05:42:29'),
(5, 'role_priority_student', '1', '2026-06-04 05:42:29'),
(6, 'rule_overdue_block_enabled', '0', '2026-11-09 10:20:53'),
(7, 'rule_duplicate_block_enabled', '0', '2026-11-09 10:20:53'),
(8, 'rule_missing_doc_block_enabled', '0', '2026-11-09 10:20:53'),
(10, 'max_borrow_days', '1', '2026-11-09 10:20:53'),
(11, 'max_items_per_borrower', '3', '2026-11-09 10:20:53'),
(12, 'low_stock_threshold', '2', '2026-11-09 10:20:53'),
(13, 'auto_approve_timeout_minutes', '1', '2026-11-09 10:20:53');

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
  `override_by_id` int(11) DEFAULT NULL,
  `override_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_arbitration_log`
--

INSERT INTO `tbl_arbitration_log` (`id`, `request_id`, `borrower_id`, `borrower_name`, `equipment_name`, `decision`, `rule_applied`, `reason`, `override_by`, `override_by_id`, `override_reason`, `created_at`) VALUES
(13, 27, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'Returned', 'qr_return', 'Auto-approved: no admin action within the buffer time.', 'Redg Admin', 1, NULL, '2026-11-09 10:57:07'),
(42, 55, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (20A)', 'Returned', 'qr_return', 'Equipment returned via QR scan — condition: Good', 'Redg Admin', 1, NULL, '2026-11-09 16:21:11'),
(43, 54, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (15A)', 'Returned', 'qr_return', 'Equipment returned via QR scan — condition: Good', 'Redg Admin', 1, NULL, '2026-11-09 16:21:10'),
(44, 56, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (30A)', 'Returned', 'qr_return', 'Equipment returned via QR scan — condition: Good', 'Redg Admin', 1, NULL, '2026-11-09 16:21:11'),
(51, 57, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, '2026-11-09 17:02:36'),
(52, 59, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (0-5A)', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, '2026-11-09 17:02:36'),
(53, 58, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (0-100mA)', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, '2026-11-09 17:02:36'),
(54, 60, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Acer System Unit', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, '2026-11-09 19:09:25'),
(55, 62, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Active Speakers', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, '2026-11-09 19:09:25'),
(56, 61, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, '2026-11-09 19:09:25');

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
(2, 1, 'main-building-b', 'Building B (New)', 'North Wing', 4, 'assets/images/faculty/pup-main-building-b-image.jpg', 'business', 'Modern laboratories, smart classrooms, and collaborative study spaces.', 2, '2026-10-01 13:05:16'),
(3, 2, 'cite-main', 'Building C (CITE)', 'Main Block', 4, 'assets/images/faculty/pup-cite-image.jpg', 'engineering', 'Technical laboratories, computer labs, and specialized engineering facilities spread across 4 floors.', 1, '2026-10-01 13:05:16');

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
-- Table structure for table `tbl_equipment_categories`
--

CREATE TABLE `tbl_equipment_categories` (
  `category_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_equipment_categories`
--

INSERT INTO `tbl_equipment_categories` (`category_id`, `campus_id`, `name`, `sort_order`, `created_at`) VALUES
(1, 1, 'Electronics and Accessories', 1, '2026-11-08 14:26:35'),
(2, 1, 'Audio/Visual', 2, '2026-11-08 14:26:35'),
(3, 1, 'Power', 3, '2026-11-08 14:26:35'),
(4, 1, 'Others', 99, '2026-11-08 14:26:35'),
(5, 2, 'Audio/Visual', 1, '2026-11-08 14:26:35'),
(6, 2, 'Cables & Connectors', 2, '2026-11-08 14:26:35'),
(8, 2, 'Lab Equipment', 4, '2026-11-08 14:26:35'),
(9, 2, 'Networking', 5, '2026-11-08 14:26:35'),
(10, 2, 'Power', 6, '2026-11-08 14:26:35'),
(11, 2, 'Test & Measurement', 7, '2026-11-08 14:26:35'),
(12, 2, 'Tools', 8, '2026-11-08 14:26:35'),
(13, 2, 'Others', 99, '2026-11-08 14:26:35'),
(14, 2, 'Robotics', 0, '2026-11-08 15:02:51'),
(15, 2, 'Computer Laboratories', 0, '2026-11-08 16:22:55'),
(16, 2, 'Dispensing Room', 0, '2026-11-08 16:22:56'),
(17, 2, 'Chem Lab', 0, '2026-11-08 16:22:58'),
(18, 2, 'Audio-Visual Equipment', 0, '2026-11-08 16:22:58');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_admin_notice`
--

CREATE TABLE `tbl_faculty_admin_notice` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_faculty_admin_notice`
--

INSERT INTO `tbl_faculty_admin_notice` (`id`, `faculty_id`, `request_id`, `admin_id`, `admin_name`, `title`, `message`, `created_at`) VALUES
(1, 'NOTSET-275A970376E5', 25, 0, 'PUPSync System', 'Item Code Used: Double A Batter', 'sandy (2023-00004bn-0) borrowed 1 unit of Double A Batter using your item code today. Return expected by 19:30. Room/Location: 308.', '2026-10-24 17:31:00');

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

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_item_codes`
--

CREATE TABLE `tbl_faculty_item_codes` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(255) NOT NULL,
  `faculty_name` varchar(255) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_id` int(11) NOT NULL,
  `code` varchar(16) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_by_name` varchar(255) DEFAULT NULL,
  `used_by_id` varchar(50) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_registry`
--

CREATE TABLE `tbl_faculty_registry` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `is_newly_hired` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_faculty_registry`
--

INSERT INTO `tbl_faculty_registry` (`id`, `faculty_id`, `full_name`, `department`, `is_newly_hired`, `uploaded_at`, `uploaded_by`) VALUES
(558, 'FA0006BN2024', 'Aaron A. Atienza', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(559, 'FA0003BN2021', 'Abigail T. Verterra', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(560, NULL, 'Aleli Joy A. Prestado', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(561, 'FA0006BN2026', 'Alonica Mae M. Ebo', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(562, NULL, 'Andrea Mhe Abarca', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(563, 'FA0012ST2009', 'Angelita E. Nocon', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(564, 'FA0004BN2022', 'Annabelle V. Molina', 'BEED', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(565, 'FA0001SP2014', 'Antonio D. Noel', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(566, NULL, 'April L. Villaverde', 'BSEDEN', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(567, 'FA0021BN2011', 'Araceli C. Amoranto', 'BSEDSS', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(568, 'FA0143MN2016', 'Archie C. Arevalo', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(569, NULL, 'Ariel S. Ponce', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(570, 'FA0005BN2026', 'Arvin M. Nuñez', 'BEED', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(571, 'FA0005SP2012', 'Benjamin A. Abarquez Jr.', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(572, 'FA0010BN2026', 'Benjamin Hernandez', 'BSIT', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(573, 'FA0053SP2012', 'Bonifacio Umaclap', 'BSBAHRM', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(574, NULL, 'Carissa C. Morano', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(575, 'FA0002BN2022', 'Carmen L. Yupangco', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(576, NULL, 'Celine N. Ocfemia', 'BSCPE', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(577, NULL, 'Charisse A. Nethercott', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(578, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'BSEDEN', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(579, 'FA0005BN2019', 'Dennis R. Estocado', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(580, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'DIT', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(581, NULL, 'Edward Francis Gabor', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(582, 'FA0003BN2024', 'Elena de Torres Teh', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(583, 'FA0011BN2025', 'Elvira R. Yambot', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(584, 'FA0007BN2023', 'Federick C. Macarubbo', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(585, NULL, 'Francesca T. Chavenia', 'BSEDEN', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(586, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(587, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'DCPET', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(588, NULL, 'Gian Carlo C. Villagracia', 'BSBAHRM', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(589, 'FA0006BN2025', 'Gio M. Marcos', 'BSCPE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(590, 'FA0001BN2020', 'Glorina Divina P. Orozco', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(591, 'FA0002BN2026', 'Gracel Eliza Ching Greta', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(592, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(593, 'FA0014BN2022', 'Hardly D. Abellano', 'BSCPE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(594, 'FA0003ST2012', 'Herdino Cañeja', 'BSCPE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(595, 'FA0002BN2016', 'Indaleen P. Quinsayas', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(596, 'FA0008TG2012', 'Israel G. Ortega', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(597, 'FA0004BN2026', 'Jan Martin Resureccion', 'BEED', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(598, 'FA0002BN2023', 'Jayson M. Capistrano', 'BSIT', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(599, NULL, 'Jayvee John Louie de Guzman', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(600, 'FA0003BN2018', 'Jean J. Tolentino', 'BSIE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(601, 'FA0003BN2026', 'Jexter Magsino', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(602, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(603, 'FA0015BN2023', 'Jobert D. Cadiz', 'DCPET', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(604, NULL, 'John O. Conanan', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(605, 'FA0011BN2023', 'John Rafael D. Risos', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(606, 'FA0011BN2021', 'John Robby O. Robiños', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(607, 'FA0012BN2022', 'Jose Cardama Delgado', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(608, 'FA0021MN2009', 'Josefina B. Macarubbo', 'BSBAHRM', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(609, 'FA0783MN2008', 'Juan C. Birion', 'BSPSY', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(610, 'FA0002BN2020', 'Kris Rommel R. Ramos', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(611, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'BSCPE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(612, 'FA0001BN2024', 'Lawrence Lucis Bucayu', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(613, 'FA0010BN2025', 'BSPSY', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(614, NULL, 'Luke Garcia', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(615, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'BSIT', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(616, 'FA0002BN2012', 'Manolo David B. Rivera', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(617, NULL, 'Marco M. Badion', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(618, 'FA0004TG2009', 'Margarita T. Sevilla', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(619, 'FA0003BN2022', 'Marichu L. Camposano', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(620, 'FA0010BN2023', 'Mark Anthony P. Lagumen', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(621, 'FA0008BN2025', 'Marte D. Nipas', 'DIT', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(622, NULL, 'Mary Anne Legarda', '', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(623, NULL, 'Mathew Ives L. Yupangco', 'BSPSY', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(624, 'FA0054SP2012', 'Maura Umaclap', 'BEED', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(625, 'FA0004BN2018', 'May L. Borjal', 'BSEDSS', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(626, 'FA0015SP2021', 'Mc Joben R. Reyes', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(627, 'FA0038MN2017', 'McLougin Mislan', 'BSCPE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(628, 'FA0004BN2025', 'Melanie Matilla Lalim', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(629, 'FA0010BN2022', 'Melanny E. Rivera', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(630, 'FA0005BN2016', 'Michael Anjelo O. Miguel', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(631, NULL, 'Muhammad A. Lambas', 'DCPET', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(632, 'FA0012BN2024', 'Nancy M. Cabaluna', 'BSEDSS', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(633, 'FA0047SP2012', 'Nelissa B. Perey', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(634, 'FA0009BN2026', 'Neribeth Charm G. Agripa', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(635, 'FA0001BN2025', 'Nestleson H. Alagon', 'BSCPE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(636, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'BSIE', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(637, 'FA0001BN2011', 'Noemi Apostol', 'DIT', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(638, 'FA0001PQ2019', 'Paul John J. Capote', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(639, 'FA0008BN2026', 'Paul Vincent A. Vierneza', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(640, NULL, 'Rafael Lloyd Domingo', 'BSIT', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(641, 'FA0060MN2012', 'DCPET', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(642, 'FA0001BN2026', 'Rafael M. Bungualan', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(643, 'FA0002TG2012', 'Reginald G. Raquiza', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(644, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(645, 'FA0005BN2023', 'Rod Phillip M. Corro', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(646, NULL, 'Ronnel P. Virtucio', 'DCPET', 1, '2026-11-06 07:57:30', 'Redg Admin'),
(647, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(648, 'FA0011BN2026', 'Stephen Mathew C. Perez', '', 0, '2026-11-06 07:57:30', 'Redg Admin'),
(649, 'FA0002BN2019', 'Widonna B. Cuenca', 'BSEDEN', 0, '2026-11-06 07:57:30', 'Redg Admin');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_registry_files`
--

CREATE TABLE `tbl_faculty_registry_files` (
  `id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(64) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `sha256` char(64) NOT NULL,
  `import_mode` enum('merge','replace') NOT NULL DEFAULT 'merge',
  `row_count` int(11) DEFAULT NULL,
  `inserted_count` int(11) DEFAULT NULL,
  `updated_count` int(11) DEFAULT NULL,
  `skipped_count` int(11) DEFAULT NULL,
  `status` enum('uploaded','imported') NOT NULL DEFAULT 'uploaded',
  `uploaded_by` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `imported_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_faculty_registry_files`
--

INSERT INTO `tbl_faculty_registry_files` (`id`, `original_name`, `stored_name`, `mime_type`, `file_size`, `sha256`, `import_mode`, `row_count`, `inserted_count`, `updated_count`, `skipped_count`, `status`, `uploaded_by`, `uploaded_at`, `imported_at`) VALUES
(1, 'Faculty Identification Records - Sheet1.pdf', 'c7d47e11d20e940331e7ac9e502e3345.pdf', 'application/pdf', 65772, '7d697dbb0c0c55e296ba28ca4768bd4b443d16e890b58fad486f376a187cb2d7', 'merge', 92, 92, 0, 0, 'imported', 'Redg Admin', '2026-11-06 07:57:29', '2026-11-06 07:57:30');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory`
--

CREATE TABLE `tbl_inventory` (
  `item_id` int(11) NOT NULL,
  `campus_id` int(11) NOT NULL DEFAULT 1,
  `synced_from_sheet` tinyint(1) NOT NULL DEFAULT 0,
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

INSERT INTO `tbl_inventory` (`item_id`, `campus_id`, `synced_from_sheet`, `item_name`, `category`, `quantity`, `condition`, `description`, `image_path`, `created_at`, `is_archived`, `is_high_value`) VALUES
(8, 1, 0, 'HDMI Cable', 'Electronics and Accessories', 5, 'Good', NULL, 'uploads/1768426958_item_hdmicable.webp', '2026-01-15 05:42:38', 0, 0),
(9, 1, 0, 'AC Remote', 'Electronics and Accessories', 4, 'Good', NULL, 'uploads/1768427004_item_remoteAc.jpg', '2026-01-15 05:43:24', 0, 0),
(10, 1, 0, 'Extension', 'Electronics and Accessories', 6, 'Good', NULL, 'uploads/1768427033_item_extension.webp', '2026-01-15 05:43:53', 0, 0),
(30, 1, 0, 'Table', 'Others', 4, 'Good', NULL, 'uploads/1790901156_item_table2.jpg', '2026-10-02 08:32:36', 0, 0),
(31, 1, 0, 'Projector', 'Audio/Visual', 3, 'Good', NULL, 'uploads/1790901270_images.jpg', '2026-10-02 08:34:30', 0, 0),
(32, 1, 0, 'Double A Batter', 'Power', 16, 'Fair', NULL, 'uploads/1790910199_batteryAA.jpg', '2026-10-02 11:03:19', 0, 0),
(33, 2, 1, 'Unterrupted Power Supply', 'Computer Laboratories', 30, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(34, 2, 1, 'Automatic Voltage Regulator', 'Computer Laboratories', 30, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(35, 2, 1, 'Data Comm Cabinet', 'Computer Laboratories', 1, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(36, 2, 1, 'D-Link Switch/Hub', 'Computer Laboratories', 1, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(37, 2, 1, 'Network Switch', 'Computer Laboratories', 2, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(38, 2, 1, 'Router', 'Computer Laboratories', 1, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(39, 2, 1, 'System Unit', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(40, 2, 1, 'Keyboard', 'Computer Laboratories', 30, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(41, 2, 1, 'Optical Mouse', 'Computer Laboratories', 30, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(42, 2, 1, 'PC Monitor', 'Computer Laboratories', 30, 'Good', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(43, 2, 1, 'Printer', 'Computer Laboratories', 1, 'For Repair', NULL, NULL, '2026-11-08 16:22:55', 0, 0),
(44, 2, 1, 'Globe Ammeter (-500 to 500 uA)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(45, 2, 1, 'Ammeter (100/500 mA)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(46, 2, 1, 'Globe Ammeter (1/5 A)', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(47, 2, 1, 'Ammeter (5A)', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(48, 2, 1, 'Camsco Ammeter (5A)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(49, 2, 1, 'Ammeter (15A)', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(50, 2, 1, 'Ammeter (20A)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(51, 2, 1, 'Ammeter (30A)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(52, 2, 1, 'Nikko Denki Ammeter (30A)', 'Dispensing Room', 20, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(53, 2, 1, 'EDM Voltmeter (3V)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(54, 2, 1, 'Voltmeter (10V)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(55, 2, 1, 'Voltmeter (15V)', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(56, 2, 1, 'Unix Voltmeter (15V)', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(57, 2, 1, 'Voltmeter (3/15V)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(58, 2, 1, 'Nikko Denki Voltmeter (30V)', 'Dispensing Room', 9, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(59, 2, 1, 'CAmsco Voltmeter (150V)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(60, 2, 1, 'COS Power Factor Meter (5V)', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(61, 2, 1, 'Risesun COS Power Factor Meter (5V)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(62, 2, 1, 'Nikko Denki COS Power Factor Meter (5V)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(63, 2, 1, 'Transformer DC Moving Coil', 'Dispensing Room', 8, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(64, 2, 1, 'Transformer Module 03', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(65, 2, 1, 'Transformer Module 07', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(66, 2, 1, 'Transformer Current', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(67, 2, 1, 'Black Potentiometer', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(68, 2, 1, 'White Potentiometer', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(69, 2, 1, 'Regulating Transformer 250', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(70, 2, 1, 'Nice-Power DC Power Supply', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(71, 2, 1, 'Lodestar - DC Power Supply', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(72, 2, 1, 'Wattmeter (500w)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(73, 2, 1, 'Magnetic Contractor', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(74, 2, 1, 'Resistance Box', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(75, 2, 1, 'Uni-T Digital Multimeter', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(76, 2, 1, 'Sanwa Multitester', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(77, 2, 1, 'Lenovo System Unit', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(78, 2, 1, 'Novus System Unit', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(79, 2, 1, 'Acer System Unit', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(80, 2, 1, 'NewTech System Unit', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(81, 2, 1, 'Lenovo Monitor', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(82, 2, 1, 'BenQ Monitor', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(83, 2, 1, 'Sassin Power Supply', 'Dispensing Room', 9, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(84, 2, 1, 'Harrys Power Supply', 'Dispensing Room', 10, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(85, 2, 1, 'Wheeler Power Supply', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(86, 2, 1, 'Air Compressor', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(87, 2, 1, 'D-Link Dongle', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(88, 2, 1, 'Dummy Dome Camera', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(89, 2, 1, 'Wheeler Digital Traing System', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(90, 2, 1, 'Coaxial Cable Stripper', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(91, 2, 1, 'Wire Stripper', 'Dispensing Room', 6, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(92, 2, 1, 'Crimping Tool', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(93, 2, 1, 'Pack of Crystal Heads', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(94, 2, 1, 'Roll of Lan Cable', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(95, 2, 1, 'D-Link 24-Port Switch', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(96, 2, 1, 'D-Link 16-Port Switch', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(97, 2, 1, 'D-Link 8-Port Switch', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(98, 2, 1, 'Network Cable Tester', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(99, 2, 1, 'Amron Prpgrammable Controller', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(100, 2, 1, 'Atwood Machine', 'Dispensing Room', 12, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(101, 2, 1, 'Wheeler 20MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(102, 2, 1, 'Instek 100MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(103, 2, 1, 'Raffles 100MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(104, 2, 1, 'Leader 40MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(105, 2, 1, 'Wheeler Signal Generator', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(106, 2, 1, 'Function Generator', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(107, 2, 1, 'Incline Plane', 'Dispensing Room', 9, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(108, 2, 1, 'Frictionless Dynamic Cart', 'Dispensing Room', 6, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(109, 2, 1, 'Friction Board with Pully', 'Dispensing Room', 9, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(110, 2, 1, 'Freefall Apparatus', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(111, 2, 1, 'Centripetal Force Apparatus', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(112, 2, 1, 'Dynamic Cart', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(113, 2, 1, 'Friction Block', 'Dispensing Room', 6, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(114, 2, 1, 'Winch Roller Fairlead', 'Dispensing Room', 11, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(115, 2, 1, 'Ballistic Pendulums', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(116, 2, 1, 'Demonstration Balance', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(117, 2, 1, 'Force Table', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(118, 2, 1, 'Slinky Coil 3x4', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(119, 2, 1, 'Cage Style Slotted Mass Hanger', 'Dispensing Room', 11, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(120, 2, 1, 'Graduated Cylinder (100ml)', 'Chem Lab', 10, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(121, 2, 1, 'Beaker (100ml)', 'Chem Lab', 4, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(122, 2, 1, 'Resistor Assortment Kit', 'Dispensing Room', 15, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(123, 2, 1, 'Freefall Apparatus Accessories', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(124, 2, 1, 'Electric Calorimeter', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(125, 2, 1, 'Esal Barometer', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(126, 2, 1, 'Box of Syringe with Needle', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(127, 2, 1, 'Hook Style Slotted Mass Hanger', 'Dispensing Room', 13, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(128, 2, 1, 'Field Mapping Set', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(129, 2, 1, 'Double Meam Balance', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(130, 2, 1, 'Triple Beam Balance', 'Dispensing Room', 10, 'Fair', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(131, 2, 1, 'Spalding Stopwatch', 'Dispensing Room', 10, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(132, 2, 1, 'Kadio Stopwatch', 'Dispensing Room', 9, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(133, 2, 1, 'Glass Plate for Friction Board', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(134, 2, 1, 'Soldering Iron', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(135, 2, 1, 'Desoldering Pump', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(136, 2, 1, 'Erlenmeyer Flask (250ml)', 'Chem Lab', 12, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(137, 2, 1, 'Erlenmeyer Flask (500ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(138, 2, 1, 'Erlenmeyer Flask (1000ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(139, 2, 1, 'Red Fire Extinguisher', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(140, 2, 1, 'Retort Stand', 'Dispensing Room', 6, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(141, 2, 1, 'Physical Optics Experiment Set', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(142, 2, 1, 'Artec Robo Creator Set DX', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(143, 2, 1, 'Wadfow Bench Vice', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(144, 2, 1, 'Adaptor', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:56', 0, 0),
(145, 2, 1, 'Raspberry Pi Camera', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(146, 2, 1, 'Digital Scale', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(147, 2, 1, 'Test Line ad Alligator Clip Set', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(148, 2, 1, 'Box of Potentiometer', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(149, 2, 1, 'Ohms Law Apparatus', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(150, 2, 1, 'Metal File Set', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(151, 2, 1, 'Digital Level', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(152, 2, 1, 'Tatnad Soldering Iron', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(153, 2, 1, 'Spring Balancer', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(154, 2, 1, 'Needle File', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(155, 2, 1, 'White Hard Hat', 'Dispensing Room', 10, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(156, 2, 1, 'Microscope', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(157, 2, 1, 'Kuaiqu DC Power Supply', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(158, 2, 1, 'Portable Monitor', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(159, 2, 1, 'Wadfow Claw Hammer', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(160, 2, 1, 'Wadfow Ball Pein Hammer', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(161, 2, 1, 'Beaker (250ml)', 'Chem Lab', 8, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(162, 2, 1, 'Beaker (500ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(163, 2, 1, 'Beaker (1000ml)', 'Chem Lab', 10, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(164, 2, 1, 'Glass Funnel (50mm)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(165, 2, 1, 'Glass Funnel (60mm)', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(166, 2, 1, 'Glass Funnel (75mm)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(167, 2, 1, 'Glass Funnel (100mm)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(168, 2, 1, 'Burette', 'Dispensing Room', 1, 'Fair', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(169, 2, 1, 'Optical Bench', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(170, 2, 1, 'Galvanometer', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(171, 2, 1, 'Magnifying Glass', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(172, 2, 1, 'Compass', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(173, 2, 1, 'Combination Wrench', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(174, 2, 1, 'Crucible (50ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(175, 2, 1, 'Crucible (30ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(176, 2, 1, 'Indicator Paper', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(177, 2, 1, 'Horse Shoe Magnet', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(178, 2, 1, 'Voltmeter (0-500v)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(179, 2, 1, 'Squeeze Bottle (250ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(180, 2, 1, 'Squeeze Bottle (500ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(181, 2, 1, 'Wadfow Machinist Hammer', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(182, 2, 1, 'Crimper Plier', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(183, 2, 1, 'VE terminals', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(184, 2, 1, 'Tap and Die Set', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(185, 2, 1, 'Prism', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(186, 2, 1, 'Ammeter (0-100mA)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(187, 2, 1, 'Test Tube Rack', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(188, 2, 1, 'L-Square', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(189, 2, 1, 'Saw', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(190, 2, 1, 'Metal Saw', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(191, 2, 1, 'Ammeter (0-5A)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(192, 2, 1, 'Gas Burner', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(193, 2, 1, 'Voltmeter (0-20v)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(194, 2, 1, 'Convex Lens', 'Dispensing Room', 12, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(195, 2, 1, 'Meter Stick', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(196, 2, 1, 'Multimeter', 'Dispensing Room', 7, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(197, 2, 1, 'Safety Goggles', 'Dispensing Room', 15, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(198, 2, 1, 'Face Shield', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(199, 2, 1, 'Dropper Pipette (10ml)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(200, 2, 1, 'Raspi Camera', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(201, 2, 1, 'Thermometer', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(202, 2, 1, 'Inculating Loops', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(203, 2, 1, 'Watch Glass (Large)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(204, 2, 1, 'Watch Glass (Medium)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(205, 2, 1, 'Watch Glass (Small)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:57', 0, 0),
(206, 2, 1, 'Test Tube Holder', 'Dispensing Room', 10, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(207, 2, 1, 'Test Tube Brush', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(208, 2, 1, 'Crucible Tong', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(209, 2, 1, 'Tuning Fork', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(210, 2, 1, 'ESP32', 'Dispensing Room', 4, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(211, 2, 1, 'Multi-Function Meter', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(212, 2, 1, 'Relay (8in)', 'Dispensing Room', 5, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(213, 2, 1, 'Vernier Caliper', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(214, 2, 1, 'Mini Guage', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(215, 2, 1, 'Precision Screwdriver', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(216, 2, 1, 'Wrench (150x19)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(217, 2, 1, 'Wrench (200x24)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(218, 2, 1, 'Wrench (250x30)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(219, 2, 1, 'Wrench (300x36)', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(220, 2, 1, 'Long Nose', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(221, 2, 1, 'Pliers', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(222, 2, 1, 'Cutting Pliers', 'Dispensing Room', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(223, 2, 1, 'Graduated Cylinder (10ml)', 'Chem Lab', 4, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(224, 2, 1, 'Graduated Cylinder (25ml)', 'Chem Lab', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(225, 2, 1, 'Graduated Cylinder (50ml)', 'Chem Lab', 8, 'Fair', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(226, 2, 1, 'Erlenmeyer Flask (100ml)', 'Chem Lab', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(227, 2, 1, 'Erlenmeyer Flask (125ml)', 'Chem Lab', 11, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(228, 2, 1, 'Filtering Flask', 'Chem Lab', 5, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(229, 2, 1, 'Separatory Funnel (100ml)', 'Chem Lab', 1, 'Fair', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(230, 2, 1, 'Separatory Funnel (250ml)', 'Chem Lab', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(231, 2, 1, 'Separatory Funnel (500ml)', 'Chem Lab', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(232, 2, 1, 'Florence Flask (250ml)', 'Chem Lab', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(233, 2, 1, 'Florence Flask (500ml)', 'Chem Lab', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(234, 2, 1, 'Beaker (50ml)', 'Chem Lab', 5, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(235, 2, 1, 'Beaker (150ml)', 'Chem Lab', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(236, 2, 1, 'Beaker (400ml)', 'Chem Lab', 7, 'Fair', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(237, 2, 1, 'Alcohol Lamp', 'Chem Lab', 0, 'For Repair', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(238, 2, 1, 'Pipette', 'Chem Lab', 10, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(239, 2, 1, 'Hydrometer', 'Chem Lab', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(240, 2, 1, 'Watch Glass', 'Chem Lab', 9, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(241, 2, 1, 'Glass Thermometer', 'Chem Lab', 10, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(242, 2, 1, 'Delivery Tube', 'Chem Lab', 3, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(243, 2, 1, 'Reagent Bottle', 'Chem Lab', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(244, 2, 1, 'Petri Dish (100mm)', 'Chem Lab', 10, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(245, 2, 1, 'Double Beam Balance', 'Chem Lab', 7, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(246, 2, 1, 'Fume Hood', 'Chem Lab', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(247, 2, 1, 'Smart TV', 'Audio-Visual Equipment', 17, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(248, 2, 1, 'Smart TV (Touch Screen)', 'Audio-Visual Equipment', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(249, 2, 1, 'LCD Projector', 'Audio-Visual Equipment', 6, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(250, 2, 1, 'Analog Audio Mixer', 'Audio-Visual Equipment', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(251, 2, 1, 'Active Speakers', 'Audio-Visual Equipment', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(252, 2, 1, 'Passive Speakers with Power Amplifier', 'Audio-Visual Equipment', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(253, 2, 1, 'Power Amplifier', 'Audio-Visual Equipment', 1, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(254, 2, 1, 'Wireless Microphone', 'Audio-Visual Equipment', 2, 'Good', NULL, NULL, '2026-11-08 16:22:58', 0, 0),
(255, 2, 0, 'Test Raspberry Pi Kit', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:31:32', 1, 0),
(256, 2, 0, 'Helloo', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:41:05', 1, 0),
(257, 2, 0, 'Ostin', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:44:58', 1, 0),
(258, 2, 0, 'hotdog', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 16:56:57', 1, 0),
(259, 2, 0, 'amoy pwet', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 17:14:20', 1, 0),
(260, 2, 1, 'test item', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 19:29:47', 1, 0),
(261, 2, 1, 'ambaho ni anes', 'Dispensing Room', 2, 'Good', NULL, NULL, '2026-11-08 21:25:09', 1, 0),
(262, 2, 1, 'hello', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-08 22:06:47', 1, 0),
(263, 2, 1, 'hotdog', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 08:34:09', 1, 0),
(264, 2, 1, 'testitem', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-09 09:01:03', 1, 0),
(265, 2, 1, 'HOTDOG', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 09:04:04', 1, 0),
(266, 2, 1, 'HOTDOG', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 09:14:36', 1, 0),
(267, 2, 1, 'HOTDOG', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 09:32:19', 1, 0),
(268, 2, 1, 'pizza', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 09:35:17', 1, 0),
(269, 2, 1, 'HOTDOG', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 09:52:52', 1, 0),
(270, 2, 1, 'pizza', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 09:52:52', 1, 0),
(271, 2, 1, 'hotdog', 'Dispensing Room', 0, 'For Repair', NULL, NULL, '2026-11-09 12:58:29', 1, 0),
(272, 2, 1, 'hotdog', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 13:04:40', 1, 0);

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
(1, '::1', 0, '2026-11-06 08:06:36', NULL),
(19, '127.0.0.1', 1, '2026-11-09 15:36:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_item_code_attempts`
--

CREATE TABLE `tbl_item_code_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `fail_count` smallint(5) NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL DEFAULT current_timestamp(),
  `locked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(19, 'juandelacruz@pup.edu.ph', '127.0.0.1', 0, 1, '2026-10-18 10:18:53', '2026-10-18 10:13:53', 0),
(22, 'main@admin.edu', '127.0.0.1', 0, 0, NULL, '2026-11-09 07:32:53', 1),
(23, 'stephenperez@pupsync.edu', '127.0.0.1', 1, 0, NULL, '2026-11-09 10:23:31', 0),
(24, 'stephen.perez@pupsync.edu', '127.0.0.1', 1, 0, NULL, '2026-11-09 10:23:41', 0),
(25, 'edmundo.delacruz@pupsync.edu', '127.0.0.1', 0, 0, NULL, '2026-11-09 10:24:15', 1),
(26, 'edmundo.delacruz@gmail.com', '127.0.0.1', 1, 0, NULL, '2026-11-09 15:36:32', 0);

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
-- Table structure for table `tbl_remember_tokens`
--

CREATE TABLE `tbl_remember_tokens` (
  `selector` char(32) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `account_type` enum('admin','faculty') NOT NULL,
  `account_email` varchar(255) NOT NULL,
  `credential_hash` char(64) NOT NULL,
  `auth_time` bigint(20) UNSIGNED NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `claim_token` varchar(64) DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Waiting',
  `request_date` datetime DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `arbitration_rule` varchar(50) DEFAULT NULL,
  `submitted_by_name` varchar(255) DEFAULT NULL,
  `submitted_by_id` varchar(50) DEFAULT NULL,
  `submitted_as` varchar(20) DEFAULT NULL,
  `batch_id` char(36) DEFAULT NULL,
  `bundle_claim_token` varchar(64) DEFAULT NULL,
  `borrow_time` time DEFAULT NULL,
  `return_time` time DEFAULT NULL,
  `borrow_qty` int(11) NOT NULL DEFAULT 1,
  `room_id` int(11) DEFAULT NULL,
  `booking_mode` varchar(10) DEFAULT NULL,
  `stock_applied` tinyint(1) NOT NULL DEFAULT 1,
  `extension_count` int(11) NOT NULL DEFAULT 0,
  `original_return_date` date DEFAULT NULL,
  `original_return_time` time DEFAULT NULL,
  `extension_late_seconds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `return_condition` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_requests`
--

INSERT INTO `tbl_requests` (`id`, `faculty_name`, `faculty_id`, `equipment_name`, `instructor`, `room`, `borrow_date`, `return_date`, `return_token`, `claim_token`, `returned_at`, `claimed_at`, `status`, `request_date`, `reason`, `document_path`, `arbitration_rule`, `submitted_by_name`, `submitted_by_id`, `submitted_as`, `batch_id`, `bundle_claim_token`, `borrow_time`, `return_time`, `borrow_qty`, `room_id`, `booking_mode`, `stock_applied`, `extension_count`, `original_return_date`, `original_return_time`, `extension_late_seconds`, `return_condition`) VALUES
(2, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'elaine', 'B403', '2026-01-15', '2026-01-15', NULL, NULL, NULL, NULL, 'Declined', '2026-01-15 11:08:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 0, NULL, NULL, 0, NULL),
(8, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'sir noy', 'B304', '2026-02-23', '2026-02-25', NULL, NULL, NULL, NULL, 'Declined', '2026-02-22 17:29:16', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 0, NULL, NULL, 0, NULL),
(9, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'ma\'am JJ', 'A901', '2026-03-01', '2026-03-09', NULL, NULL, NULL, NULL, 'Declined', '2026-02-22 17:31:37', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 0, NULL, NULL, 0, NULL),
(10, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Sir ajon', 'B207', '2026-02-25', '2026-02-26', NULL, NULL, NULL, NULL, 'Declined', '2026-02-22 17:38:07', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 0, NULL, NULL, 0, NULL),
(12, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'joyce', 'b203', '2026-02-23', '2026-02-24', NULL, NULL, NULL, NULL, 'Declined', '2026-02-22 17:52:27', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 0, NULL, NULL, 0, NULL),
(14, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'sir redg', 'b201', '2026-02-24', '2026-02-25', NULL, NULL, NULL, NULL, 'Declined', '2026-02-23 17:46:05', 'Request expired – borrow date has already passed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, 1, 0, NULL, NULL, 0, NULL),
(60, 'Edmundo Dela Cruz', 'FA0008BN2023', 'Acer System Unit', 'Edmundo Dela Cruz', 'Room 210Building C (CITE) · 2nd Floor', '2026-11-09', '2026-11-09', 'd19e8412005db2493e4a37a97f34cfc4229da9dcc63ba10225c9dd774291bcc8', NULL, NULL, NULL, 'Approved', '2026-11-09 19:08:21', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', 'f46b09c1-cc5d-48ee-a6dd-dd85865092fc', '53a4512b54a2477f43f49dafd6af1cb689e8a9eff81d66cab707eba1bafa7448', '19:10:00', '20:10:00', 1, 76, '0', 0, 0, NULL, NULL, 0, NULL),
(61, 'Edmundo Dela Cruz', 'FA0008BN2023', 'Adaptor', 'Edmundo Dela Cruz', 'Room 210Building C (CITE) · 2nd Floor', '2026-11-09', '2026-11-09', '08642274936732edc1881f6e95c8c4261497ca9f624687e60b0d2bfb290b65b7', NULL, NULL, NULL, 'Approved', '2026-11-09 19:08:22', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', 'f46b09c1-cc5d-48ee-a6dd-dd85865092fc', '53a4512b54a2477f43f49dafd6af1cb689e8a9eff81d66cab707eba1bafa7448', '19:10:00', '20:10:00', 2, 76, '0', 0, 0, NULL, NULL, 0, NULL),
(62, 'Edmundo Dela Cruz', 'FA0008BN2023', 'Active Speakers', 'Edmundo Dela Cruz', 'Room 210Building C (CITE) · 2nd Floor', '2026-11-09', '2026-11-09', 'd7097b36635d38b0cfd62128244af34530c16baeea07d2c62457e7576e37536c', NULL, NULL, NULL, 'Approved', '2026-11-09 19:08:22', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', 'f46b09c1-cc5d-48ee-a6dd-dd85865092fc', '53a4512b54a2477f43f49dafd6af1cb689e8a9eff81d66cab707eba1bafa7448', '19:10:00', '20:10:00', 2, 76, '0', 0, 0, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_request_events`
--

CREATE TABLE `tbl_request_events` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `borrower_id` varchar(50) NOT NULL,
  `borrower_name` varchar(100) NOT NULL DEFAULT '',
  `equipment_name` varchar(100) NOT NULL DEFAULT '',
  `event` varchar(30) NOT NULL,
  `detail` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_request_events`
--

INSERT INTO `tbl_request_events` (`id`, `request_id`, `borrower_id`, `borrower_name`, `equipment_name`, `event`, `detail`, `created_at`) VALUES
(1, 37, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Active Speakers', 'extended', 'Until Nov 9, 4:05 PM (+3 hours, extension 1 of 2). Was overdue by 9 minutes; originally due Nov 9, 12:52 PM.', '2026-11-09 13:01:18'),
(2, 35, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Acer System Unit', 'extended', 'Until Nov 9, 6:05 PM (+5 hours, extension 1 of 2). Was overdue by 9 minutes; originally due Nov 9, 12:52 PM.', '2026-11-09 13:01:24'),
(3, 36, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'extended', 'Until Nov 9, 2:05 PM (+1 hour, extension 1 of 2). Was overdue by 9 minutes; originally due Nov 9, 12:52 PM.', '2026-11-09 13:01:27'),
(4, 57, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'extended', 'Until Nov 9, 8:20 PM (+2 hours, extension 1 of 2). Was overdue by 14 minutes; originally due Nov 9, 6:01 PM.', '2026-11-09 18:15:41');

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
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_rooms`
--

INSERT INTO `tbl_rooms` (`room_id`, `building_id`, `room_name`, `floor_number`, `floor_label`, `seating_capacity`, `amenities`, `status`, `is_archived`, `is_locked`, `sort_order`, `created_at`) VALUES
(1, 1, 'Admin Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 1, '2026-10-01 13:05:16'),
(2, 1, 'Registration Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 2, '2026-10-01 13:05:16'),
(3, 1, 'OSAS Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 3, '2026-10-01 13:05:16'),
(4, 1, 'Office 1', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 4, '2026-10-01 13:05:16'),
(5, 1, 'Clinic', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 5, '2026-10-01 13:05:16'),
(6, 1, 'Staff Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 6, '2026-10-01 13:05:16'),
(7, 1, 'Room 201', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(8, 1, 'Room 202', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16'),
(9, 1, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(10, 1, 'Room 204', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(11, 1, 'Room 205', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(12, 1, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(13, 1, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16'),
(14, 1, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(15, 1, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(16, 1, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(17, 1, 'Org Room', 4, '4th Floor', NULL, NULL, 'Not Bookable', 0, 0, 1, '2026-10-01 13:05:16'),
(18, 1, 'CSC Room', 4, '4th Floor', NULL, NULL, 'Not Bookable', 0, 0, 2, '2026-10-01 13:05:16'),
(19, 1, 'AVR 2', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(20, 1, 'Computer Laboratory 1', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(21, 1, 'Computer Laboratory 2', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(22, 1, 'Chemistry Laboratory', 5, '5th Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(23, 2, 'Library', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 1, '2026-10-01 13:05:16'),
(24, 2, 'Directors Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 2, '2026-10-01 13:05:16'),
(25, 2, 'Faculty Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 3, '2026-10-01 13:05:16'),
(26, 2, 'DO Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 4, '2026-10-01 13:05:16'),
(27, 2, 'Guidance Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 5, '2026-10-01 13:05:16'),
(28, 2, 'Research Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(29, 2, 'Room 202', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16'),
(30, 2, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(31, 2, 'Room 204', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(32, 2, 'Room 205', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(33, 2, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(34, 2, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16'),
(35, 2, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(36, 2, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(37, 2, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(38, 2, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(39, 2, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16'),
(40, 2, 'AVR 1', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(41, 2, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(51, 3, 'Prayer Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 1, '2026-10-01 13:05:16'),
(52, 3, 'Audiovisual Room', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16'),
(53, 3, 'Testing Area', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 3, '2026-10-01 13:05:16'),
(54, 3, 'Student Organization Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 4, '2026-10-01 13:05:16'),
(55, 3, 'Clinic Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 5, '2026-10-01 13:05:16'),
(56, 3, 'Industrial Engineering Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 6, '2026-10-01 13:05:16'),
(57, 3, 'Director\'s Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 7, '2026-10-01 13:05:16'),
(58, 3, 'Basketball Court', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 8, '2026-10-01 13:05:16'),
(59, 3, 'Room 103', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 9, '2026-10-01 13:05:16'),
(60, 3, 'Room 105', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-10-01 13:05:16'),
(61, 3, 'Room 106', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 11, '2026-10-01 13:05:16'),
(62, 3, 'Room 116', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 12, '2026-10-01 13:05:16'),
(63, 3, 'Room 118', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 13, '2026-10-01 13:05:16'),
(64, 3, 'Room 119', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 14, '2026-10-01 13:05:16'),
(65, 3, 'Admin Office', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 0, 0, 1, '2026-10-01 13:05:16'),
(66, 3, 'Faculty Lounge', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 0, 0, 2, '2026-10-01 13:05:16'),
(67, 3, 'AutoCAD & Multimedia Laboratory', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(68, 3, 'Computer Laboratory 1', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(69, 3, 'Computer Laboratory 2', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(70, 3, 'Computer Laboratory 3', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-10-01 13:05:16'),
(71, 3, 'Ergonomics Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-10-01 13:05:16'),
(72, 3, 'Digital Laboratory Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-10-01 13:05:16'),
(73, 3, 'Dispensing Room', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 0, 0, 9, '2026-10-01 13:05:16'),
(74, 3, 'Microprocessing Laboratory Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-10-01 13:05:16'),
(75, 3, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 11, '2026-10-01 13:05:16'),
(76, 3, 'Room 210', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 12, '2026-10-01 13:05:16'),
(77, 3, 'Room 212', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 13, '2026-10-01 13:05:16'),
(78, 3, 'Room 218', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 14, '2026-10-01 13:05:16'),
(79, 3, 'Library Room', 3, '3rd Floor', NULL, NULL, 'Not Bookable', 0, 0, 1, '2026-10-01 13:05:16'),
(80, 3, 'Library Extension Room', 3, '3rd Floor', NULL, NULL, 'Not Bookable', 0, 0, 2, '2026-10-01 13:05:16'),
(81, 3, 'Physics Room', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(82, 3, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(83, 3, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(84, 3, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-10-01 13:05:16'),
(85, 3, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-10-01 13:05:16'),
(86, 3, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-10-01 13:05:16'),
(87, 3, 'Room 307', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 9, '2026-10-01 13:05:16'),
(88, 3, 'Room 308', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-10-01 13:05:16'),
(89, 3, 'Room 309', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 11, '2026-10-01 13:05:16'),
(90, 3, 'Room 310', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 12, '2026-10-01 13:05:16'),
(91, 3, 'Chemistry Laboratory Room', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16'),
(92, 3, 'Student Lounge', 4, '4th Floor', NULL, NULL, 'Not Bookable', 0, 0, 2, '2026-10-01 13:05:16'),
(93, 3, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16'),
(94, 3, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16'),
(95, 3, 'Room 403', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16'),
(96, 3, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-10-01 13:05:16'),
(97, 3, 'Room 406', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-10-01 13:05:16'),
(98, 3, 'Room 415', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-10-01 13:05:16');

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
(9, 9, 7, 'Room 201', 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-06 03:00:53'),
(10, 10, 88, 'Room 308', 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-18 14:16:49'),
(11, 11, 76, 'Room 210', 'NOTSET-B9DEB33EB03E', 'Indaleen Quinsayas', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-18 16:12:04'),
(12, 12, 76, 'Room 210', 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-19 08:52:51'),
(13, 13, 76, 'Room 210', 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'Approved', 'rule_1_fifo', 'Room reservation approved — no time conflict.', '2026-10-19 10:13:49'),
(14, 14, 88, 'Room 308', 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'Approved', 'rule_1_fifo', 'Facility reservation approved — no time conflict.', '2026-10-25 09:57:21');

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
(1, 8, '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'personal', NULL, NULL, 'Lab session', 50, '2026-10-02', '09:30:00', '12:30:00', NULL, 'uploads/room_documents/20261002_081929_2026-00001-BN-0_7d5d175a.pdf', 'Approved', 'Room reservation approved — no time conflict.', '2026-10-02 08:19:29', NULL),
(2, 7, '2026-00001-BN-0', 'Michael Anjelo O. Miguel', 'personal', NULL, NULL, 'Lecture', 40, '2026-10-03', '08:30:00', '10:30:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-02 08:21:22', NULL),
(3, 67, '2026-00002-BN-0', 'Stephen Perez', 'personal', NULL, NULL, 'Lab session', 32767, '2026-10-03', '17:00:00', '20:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-03 16:43:55', NULL),
(4, 67, '2026-00002-BN-0', 'Stephen Perez', 'personal', NULL, NULL, 'Meeting', 30, '2026-10-03', '17:00:00', '18:00:00', NULL, NULL, 'Declined', 'This time slot is already reserved by a higher-priority request.', '2026-10-03 16:44:38', NULL),
(5, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-06', '08:00:00', '11:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-05 20:06:40', NULL),
(6, 71, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-08', '11:00:00', '14:00:00', NULL, NULL, 'Cancelled', 'Room reservation approved — no time conflict.', '2026-10-05 20:11:38', '2026-10-06 02:56:10'),
(7, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lab session', 1, '2026-10-06', '07:00:00', '12:00:00', NULL, NULL, 'Declined', 'This time slot is already reserved by a higher-priority request.', '2026-10-06 02:58:43', NULL),
(8, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lab session', 1, '2026-10-06', '10:30:00', '15:30:00', NULL, NULL, 'Declined', 'This time slot is already reserved by a higher-priority request.', '2026-10-06 03:00:22', NULL),
(9, 7, 'NOTSET-408F953BDD2D', 'Michael Anjelo Miguel', 'personal', NULL, NULL, 'Lab session', 1, '2026-10-06', '11:00:00', '16:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-06 03:00:53', NULL),
(10, 88, 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'personal', NULL, NULL, 'Meeting', 1, '2026-10-19', '14:00:00', '17:00:00', NULL, NULL, 'Cancelled', 'Room reservation approved — no time conflict.', '2026-10-18 14:16:49', '2026-10-18 14:17:32'),
(11, 76, 'NOTSET-B9DEB33EB03E', 'Indaleen Quinsayas', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-29', '08:00:00', '11:30:00', NULL, NULL, 'Cancelled', 'Room reservation approved — no time conflict.', '2026-10-18 16:12:04', '2026-10-18 18:13:42'),
(12, 76, 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-19', '10:00:00', '12:00:00', NULL, NULL, 'Cancelled', 'Room reservation approved — no time conflict.', '2026-10-19 08:52:50', '2026-10-19 08:53:21'),
(13, 76, 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-19', '14:00:00', '17:00:00', NULL, NULL, 'Approved', 'Room reservation approved — no time conflict.', '2026-10-19 10:13:48', NULL),
(14, 88, 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-26', '14:00:00', '17:00:00', NULL, NULL, 'Cancelled', 'Facility reservation approved — no time conflict.', '2026-10-25 09:57:21', '2026-10-25 10:43:50');

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
-- Table structure for table `tbl_sync_config`
--

CREATE TABLE `tbl_sync_config` (
  `config_key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_sync_config`
--

INSERT INTO `tbl_sync_config` (`config_key`, `value`, `updated_at`) VALUES
('gsheet_last_modified', '2026-11-09T05:10:03.032Z', '2026-11-09 13:10:08'),
('gsheet_last_sync', '2026-11-09 11:23:47', '2026-11-09 18:23:47'),
('gsheet_sync_enabled', '0', '2026-11-08 16:09:50'),
('gsheet_url', 'https://docs.google.com/spreadsheets/d/1sYHp_bfG3WkDvw4kEaFRGim2_2JHeiqz/edit?gid=1608238740', '2026-11-08 16:22:43');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sync_pending_deletions`
--

CREATE TABLE `tbl_sync_pending_deletions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `campus_id` int(11) NOT NULL DEFAULT 1,
  `detected_at` datetime DEFAULT current_timestamp()
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
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`fullname`, `faculty_id`, `email`, `backup_email`, `password`, `last_password_change`, `dob`, `gender`, `nationality`, `profile_picture`, `department`, `faculty_rank`, `phone`, `present_address`, `permanent_address`, `landline`, `emergency_name`, `emergency_relationship`, `emergency_phone`, `role`) VALUES
('Indaleen Quinsayas', 'FA0002BN2016', 'indaleen.quinsayas@pupsync.edu', NULL, '$2y$10$oUbLCC9dtNYPFxcvkvcbtOrmsdae1iiw1IGenLcfrWZo5CSyvlf1C', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Michael Anjelo Miguel', 'FA0005BN2016', 'michael.miguel@pupsync.edu', NULL, '$2y$10$cDg6QolF.l2mGo3v43go3e/fpglvD0WfWTxv3ifoAJ95wtEH4FGpC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Aaron Atienza', 'FA0006BN2024', 'aaron.atienza@pupsync.edu', NULL, '$2y$10$jhe4/CMvTgDYHbnB1XKaC.6NVO.di7gcuIk8cyQ8osmDIV.9uNs1.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Edmundo Dela Cruz', 'FA0008BN2023', 'edmundo.delacruz@pupsync.edu', NULL, '$2y$10$XdcZ7CBLZ6CvgSQtQ.oh0.S9VH91QWzR08IJLQexx4SMvgR5Bzl3S', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Stephen Perez', 'FA0011BN2026', 'stephen.perez@pupsync.edu', NULL, '$2y$10$YjDFkqM4Ap0nQguCXFhcAOcUmLbEiEVLxE18CItSQEiBolMyVTunK', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty');

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
-- Indexes for table `tbl_admin_activity`
--
ALTER TABLE `tbl_admin_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_time` (`admin_id`,`created_at`),
  ADD KEY `idx_time` (`created_at`),
  ADD KEY `idx_ref` (`ref`);

--
-- Indexes for table `tbl_ai_chat_log`
--
ALTER TABLE `tbl_ai_chat_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_faculty_sent` (`faculty_id`,`sent_at`);

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
-- Indexes for table `tbl_equipment_categories`
--
ALTER TABLE `tbl_equipment_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_campus_category` (`campus_id`,`name`),
  ADD KEY `fk_cat_campus` (`campus_id`);

--
-- Indexes for table `tbl_faculty_admin_notice`
--
ALTER TABLE `tbl_faculty_admin_notice`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_faculty_time` (`faculty_id`,`created_at`),
  ADD KEY `idx_request` (`request_id`);

--
-- Indexes for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_fc_faculty` (`faculty_id`),
  ADD KEY `idx_fc_code` (`code`);

--
-- Indexes for table `tbl_faculty_item_codes`
--
ALTER TABLE `tbl_faculty_item_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_code` (`code`),
  ADD UNIQUE KEY `uq_faculty_item` (`faculty_id`,`item_id`);

--
-- Indexes for table `tbl_faculty_notif_state`
--
ALTER TABLE `tbl_faculty_notif_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_faculty_notif` (`faculty_id`,`notif_key`);

--
-- Indexes for table `tbl_faculty_registry`
--
ALTER TABLE `tbl_faculty_registry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_registry_full_name` (`full_name`),
  ADD UNIQUE KEY `uq_registry_faculty_id` (`faculty_id`);

--
-- Indexes for table `tbl_faculty_registry_files`
--
ALTER TABLE `tbl_faculty_registry_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_registry_file_stored` (`stored_name`),
  ADD KEY `idx_registry_file_uploaded` (`uploaded_at`);

--
-- Indexes for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_inventory_campus` (`campus_id`);

--
-- Indexes for table `tbl_ip_login_attempts`
--
ALTER TABLE `tbl_ip_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ip` (`ip_address`),
  ADD KEY `idx_ip_locked` (`locked_until`);

--
-- Indexes for table `tbl_item_code_attempts`
--
ALTER TABLE `tbl_item_code_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ip` (`ip_address`);

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
-- Indexes for table `tbl_remember_tokens`
--
ALTER TABLE `tbl_remember_tokens`
  ADD PRIMARY KEY (`selector`),
  ADD KEY `idx_remember_expiry` (`expires_at`),
  ADD KEY `idx_remember_account` (`account_type`,`account_email`);

--
-- Indexes for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_token` (`return_token`),
  ADD UNIQUE KEY `claim_token` (`claim_token`),
  ADD KEY `idx_return_token` (`return_token`),
  ADD KEY `idx_claim_token` (`claim_token`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_bundle_claim_token` (`bundle_claim_token`);

--
-- Indexes for table `tbl_request_events`
--
ALTER TABLE `tbl_request_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request` (`request_id`),
  ADD KEY `idx_time` (`created_at`);

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
-- Indexes for table `tbl_sync_config`
--
ALTER TABLE `tbl_sync_config`
  ADD PRIMARY KEY (`config_key`);

--
-- Indexes for table `tbl_sync_pending_deletions`
--
ALTER TABLE `tbl_sync_pending_deletions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pending_item` (`item_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_admin_activity`
--
ALTER TABLE `tbl_admin_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `tbl_ai_chat_log`
--
ALTER TABLE `tbl_ai_chat_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `tbl_arbitration_config`
--
ALTER TABLE `tbl_arbitration_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tbl_arbitration_log`
--
ALTER TABLE `tbl_arbitration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

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
-- AUTO_INCREMENT for table `tbl_equipment_categories`
--
ALTER TABLE `tbl_equipment_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `tbl_faculty_admin_notice`
--
ALTER TABLE `tbl_faculty_admin_notice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_faculty_item_codes`
--
ALTER TABLE `tbl_faculty_item_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_faculty_notif_state`
--
ALTER TABLE `tbl_faculty_notif_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_faculty_registry`
--
ALTER TABLE `tbl_faculty_registry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=650;

--
-- AUTO_INCREMENT for table `tbl_faculty_registry_files`
--
ALTER TABLE `tbl_faculty_registry_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=273;

--
-- AUTO_INCREMENT for table `tbl_ip_login_attempts`
--
ALTER TABLE `tbl_ip_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tbl_item_code_attempts`
--
ALTER TABLE `tbl_item_code_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_login_attempts`
--
ALTER TABLE `tbl_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tbl_notif_state`
--
ALTER TABLE `tbl_notif_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `tbl_request_events`
--
ALTER TABLE `tbl_request_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `tbl_room_arbitration_log`
--
ALTER TABLE `tbl_room_arbitration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_room_issues`
--
ALTER TABLE `tbl_room_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_room_waitlist`
--
ALTER TABLE `tbl_room_waitlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_sync_pending_deletions`
--
ALTER TABLE `tbl_sync_pending_deletions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_buildings`
--
ALTER TABLE `tbl_buildings`
  ADD CONSTRAINT `fk_buildings_campus` FOREIGN KEY (`campus_id`) REFERENCES `tbl_campuses` (`campus_id`);

--
-- Constraints for table `tbl_equipment_categories`
--
ALTER TABLE `tbl_equipment_categories`
  ADD CONSTRAINT `fk_cat_campus` FOREIGN KEY (`campus_id`) REFERENCES `tbl_campuses` (`campus_id`);

--
-- Constraints for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  ADD CONSTRAINT `fk_inventory_campus` FOREIGN KEY (`campus_id`) REFERENCES `tbl_campuses` (`campus_id`);

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
-- Constraints for table `tbl_sync_pending_deletions`
--
ALTER TABLE `tbl_sync_pending_deletions`
  ADD CONSTRAINT `fk_pending_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_inventory` (`item_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
