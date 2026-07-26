-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2026 at 10:18 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

DROP DATABASE IF EXISTS lending_db;
CREATE DATABASE IF NOT EXISTS lending_db;
USE `lending_db`;


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
  `fullName` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(16) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_accounts`
--

INSERT INTO `tbl_accounts` (`fullName`, `email`, `password`, `last_login`) VALUES
('Redg Admin', 'main@admin.edu', 'admin123', '2026-06-08 10:15:03');

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
(6, 20, '2023-00251-BN-0', 'Frederick Rosales', 'AC Remote', 'Returned', 'qr_return', 'Request approved via FIFO priority scoring.', 'Redg Admin', NULL, '2026-06-06 22:02:11');

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
(3, '2023-00251-BN-0', 'Frederick Rosales', 'nk5-m6x-w2k', 1, 'Kiloman', '2023-00250-BN-0', '2026-06-06 22:07:38', '2026-06-06 22:09:27'),
(4, '2023-00004-BN-0', 'Sandy Napiza', 'wtw-zc3-ad4', 0, NULL, NULL, '2026-06-08 11:26:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inventory`
--

CREATE TABLE `tbl_inventory` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `is_high_value` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_inventory`
--

INSERT INTO `tbl_inventory` (`item_id`, `item_name`, `category`, `quantity`, `image_path`, `created_at`, `is_archived`, `is_high_value`) VALUES
(8, 'HDMI Cable', 'Electronics and Accessories', 4, 'uploads/1768426958_item_hdmicable.webp', '2026-01-15 05:42:38', 0, 0),
(9, 'AC Remote', 'Electronics and Accessories', 1, 'uploads/1768427004_item_remoteAc.jpg', '2026-01-15 05:43:24', 0, 0),
(10, 'Extension', 'Electronics and Accessories', 6, 'uploads/1768427033_item_extension.webp', '2026-01-15 05:43:53', 0, 0),
(11, 'Projector', 'Electronics and Accessories', 1, 'uploads/1768427059_item_projector.webp', '2026-01-15 05:44:19', 0, 0);

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
  `batch_id` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_requests`
--

INSERT INTO `tbl_requests` (`id`, `faculty_name`, `faculty_id`, `equipment_name`, `instructor`, `room`, `borrow_date`, `return_date`, `return_token`, `returned_at`, `status`, `request_date`, `reason`, `document_path`, `arbitration_rule`, `submitted_by_name`, `submitted_by_id`) VALUES
(1, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'Sir Migs', 'A305', '2026-01-15', '2026-01-16', NULL, NULL, 'Overdue', '2026-01-15 11:04:23', NULL, NULL, NULL, NULL, NULL),
(2, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'elaine', 'B403', '2026-01-15', '2026-01-15', NULL, NULL, 'Declined', '2026-01-15 11:08:29', NULL, NULL, NULL, NULL, NULL),
(3, 'Frederick Rosales', '2023-00251-BN-0', 'Extension', 'Sir Migs', 'B203', '2026-01-23', '2026-01-24', NULL, NULL, 'Returned', '2026-01-15 12:28:40', NULL, NULL, NULL, NULL, NULL),
(4, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'Ma\'am Donna', 'E031', '2026-02-05', '2026-02-12', NULL, NULL, 'Returned', '2026-01-15 12:30:25', NULL, NULL, NULL, NULL, NULL),
(5, 'John Jr.', '2030-00071-BN-0', 'AC Remote', 'Sir Migs', 'B203', '2026-01-15', '2026-01-16', NULL, NULL, 'Overdue', '2026-01-15 13:51:29', NULL, NULL, NULL, NULL, NULL),
(6, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'Ma\'am Donna', 'B205', '2026-02-19', '2026-02-22', NULL, NULL, 'Returned', '2026-02-18 00:27:57', NULL, NULL, NULL, NULL, NULL),
(7, 'Aiello Gabriel B. Lastrella', '2023-00294-BN-0', 'HDMI Cable', 'Sir Migs', 'Room A304', '2026-02-20', '2026-02-20', NULL, NULL, 'Overdue', '2026-02-19 15:07:14', NULL, NULL, NULL, NULL, NULL),
(8, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'sir noy', 'B304', '2026-02-23', '2026-02-25', NULL, NULL, 'Declined', '2026-02-22 17:29:16', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL),
(9, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'ma\'am JJ', 'A901', '2026-03-01', '2026-03-09', NULL, NULL, 'Declined', '2026-02-22 17:31:37', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL),
(10, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Sir ajon', 'B207', '2026-02-25', '2026-02-26', NULL, NULL, 'Declined', '2026-02-22 17:38:07', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL),
(11, 'Derick Ramsey', '2023-00651-BN-0', 'AC Remote', 'jojo', 'b703', '2026-03-12', '2026-03-21', NULL, NULL, 'Returned', '2026-02-22 17:38:52', NULL, NULL, NULL, NULL, NULL),
(12, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'joyce', 'b203', '2026-02-23', '2026-02-24', NULL, NULL, 'Declined', '2026-02-22 17:52:27', 'Out of stock – maximum approved requests reached', NULL, NULL, NULL, NULL),
(13, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'noy', 'j012', '2026-03-12', '2026-03-13', NULL, NULL, 'Overdue', '2026-02-22 17:53:09', NULL, NULL, NULL, NULL, NULL),
(14, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'sir redg', 'b201', '2026-02-24', '2026-02-25', NULL, NULL, 'Declined', '2026-02-23 17:46:05', 'Request expired – borrow date has already passed', NULL, NULL, NULL, NULL),
(15, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'sir aaron', 'B301', '2026-03-13', '2026-03-20', NULL, NULL, 'Returned', '2026-03-12 11:45:02', NULL, NULL, NULL, NULL, NULL),
(16, 'Frederick Rosales', '2023-00251-BN-0', 'Extension', 'Sir Migs', 'B205', '2026-03-13', '2026-03-14', NULL, NULL, 'Returned', '2026-03-12 13:05:13', NULL, NULL, NULL, NULL, NULL),
(17, 'Sandy Napiza', '2023-00004-BN-0', 'AC Remote', 'Sandy Napiza', '210', '2026-06-04', '2026-06-05', NULL, '2026-06-04 13:47:37', 'Returned', '2026-06-04 13:46:58', NULL, NULL, NULL, 'sandy', '2023-00004-BN-0'),
(18, 'Sandy Napiza', '2023-00004-BN-0', 'AC Remote', 'Sandy Napiza', '278', '2026-06-06', '2026-06-06', NULL, '2026-06-06 21:43:09', 'Returned', '2026-06-06 21:37:09', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL),
(19, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Frederick Rosales', '201', '2026-06-18', '2026-06-19', NULL, '2026-06-06 21:59:40', 'Returned', '2026-06-06 21:55:17', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL),
(20, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Frederick Rosales', '204', '2026-06-24', '2026-06-25', NULL, '2026-06-06 22:02:11', 'Returned', '2026-06-06 22:00:39', 'Request approved via FIFO priority scoring.', NULL, 'rule_1_fifo', NULL, NULL),
(21, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Frederick Rosales', 'B203', '2026-06-12', '2026-06-19', '476ea34754a96f645c20b907493adf7e6e8b0b585651a30ce7a4a99877b399b7', NULL, 'Approved', '2026-06-06 22:09:27', NULL, NULL, NULL, 'Kiloman', '2023-00250-BN-0');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_reservations`
--

CREATE TABLE `tbl_room_reservations` (
  `id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `room_name` varchar(150) NOT NULL,
  `purpose` varchar(200) NOT NULL,
  `instructor` varchar(100) NOT NULL,
  `attendees` int(11) DEFAULT 1,
  `reservation_date` date NOT NULL,
  `time_slot` varchar(60) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Waiting','Approved','Declined','Cancelled') DEFAULT 'Waiting',
  `reason` varchar(255) DEFAULT NULL,
  `request_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_organizations`
--

CREATE TABLE `tbl_organizations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_organizations`
--

INSERT INTO `tbl_organizations` (`id`, `name`) VALUES
(1, 'IBITS'),
(2, 'YES'),
(3, 'ACES');

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

INSERT INTO `tbl_users` (`fullname`, `faculty_id`, `email`, `backup_email`, `password`, `last_password_change`, `dob`, `gender`, `nationality`, `profile_picture`, `department`, `faculty_rank`, `phone`, `present_address`, `permanent_address`, `landline`, `emergency_name`, `emergency_relationship`, `emergency_phone`, `role`) VALUES
('Sandy Napiza', '2023-00004-BN-0', 'napiza.sandy.lsei@gmail.com', NULL, '$2y$10$LkK0vynd6.4zdgxJmlqJVOhjVAg7ZTm8uE8S1L/se4ihE1YOUHEWe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Philip San Jose', '2023-00111-BN-0', 'philip@gmail.com', NULL, '$2y$10$9R40gACxJd27H2pxjk1tD.wo4Gsrl3dhxTAIK82rRwYouxNu/FJKu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Mendoza', '2023-00230-BN-0', 'elainejoyamendoza@iskolarngbayan.pup.edu', NULL, '$2y$10$6DLhVRPsBCHxBPqpeuenc.GJDhp1pq3aiW9RDnS.FH2Nn/k/jDyUq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Frederick Rosales', '2023-00251-BN-0', 'iamfrederickr@gmail.com', 'frederick@gmail.com', '$2y$10$haZe66NIfJD5N5SEqNNTm.j9kYKYa/sJgcB7mSDBWqClftRV49okW', '2026-03-12 19:08:17', '2003-06-21', 'Male', 'Filipino', '2023-00251-BN-0_1773344427.JPG', 'BSIT', '3rd Year', '639662668443', '', '', '', NULL, NULL, NULL, 'Regular Faculty'),
('Aiello Gabriel B. Lastrella', '2023-00294-BN-0', 'aiello.gabbb@gmail.com', NULL, '$2y$10$5rIqe5mYody6ITpFmVzAHudnh3UIghf14/B.w.26v/Uo5AcXZFaOu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('Derick Ramsey', '2023-00651-BN-0', 'derick@gmail.com', NULL, '$2y$10$STa.6os34BGuOndBPJR5/OSurigCNj299HS.Nl/aEYuFNIhulz0La', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
('John Jr.', '2030-00071-BN-0', 'johnjohn1234@gmail.com', NULL, '$2y$10$Z4WaQGuntPYSnblonEtdKu5WMx9SPxHE5YUUAcd/8tPy6wsuVzuMS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
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
-- Indexes for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_fc_faculty` (`faculty_id`),
  ADD KEY `idx_fc_code` (`code`);

--
-- Indexes for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_token` (`return_token`),
  ADD KEY `idx_return_token` (`return_token`);

--
-- Indexes for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_organizations`
--
ALTER TABLE `tbl_organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_org_name` (`name`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `fk_users_org` FOREIGN KEY (`organization_id`) REFERENCES `tbl_organizations` (`id`) ON DELETE RESTRICT;

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_arbitration_config`
--
ALTER TABLE `tbl_arbitration_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_arbitration_log`
--
ALTER TABLE `tbl_arbitration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_organizations`
--
ALTER TABLE `tbl_organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

-- ── CREATE-FACULTY-ACCOUNT MIGRATION ──────────────────────────────────────
-- Run this block once on any existing lending_db that was created before
-- this feature was added. A full re-import of lending_db.sql also works.

-- 1. New table: tbl_organizations
CREATE TABLE IF NOT EXISTS `tbl_organizations` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `tbl_organizations` (`name`) VALUES
  ('IBITS'),
  ('YES'),
  ('ACES');

-- Columns `is_org_adviser`, `organization_id`, and FK `fk_users_org` are now
-- defined directly in CREATE TABLE tbl_users above; no ALTER TABLE needed here.

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- DUAL BORROWING MODE MIGRATION
-- Run this block on any existing lending_db to add the dual-borrowing-mode
-- columns. The block is fully idempotent: running it a second time on a DB
-- that already has the columns produces no error and leaves existing data
-- unchanged. Uses a stored-procedure guard (MariaDB 10.4 compatible).

-- ── 1a. allow_org_borrowing on tbl_users (AFTER is_org_adviser) ──────────
DROP PROCEDURE IF EXISTS `_dbm_add_allow_org_borrowing`;
DELIMITER $$
CREATE PROCEDURE `_dbm_add_allow_org_borrowing`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = 'tbl_users'
          AND  COLUMN_NAME  = 'allow_org_borrowing'
    ) THEN
        ALTER TABLE `tbl_users`
            ADD COLUMN `allow_org_borrowing` TINYINT(1) NOT NULL DEFAULT 0
            AFTER `is_org_adviser`;
    END IF;
END$$
DELIMITER ;
CALL `_dbm_add_allow_org_borrowing`();
DROP PROCEDURE IF EXISTS `_dbm_add_allow_org_borrowing`;

-- ── 1b. Backfill existing Organisation Advisers ──────────────────────────
UPDATE `tbl_users`
SET    `allow_org_borrowing` = 1
WHERE  `role` = 'Organization Adviser';

-- ── 2a. submitted_as on tbl_requests (AFTER submitted_by_id) ─────────────
DROP PROCEDURE IF EXISTS `_dbm_add_submitted_as`;
DELIMITER $$
CREATE PROCEDURE `_dbm_add_submitted_as`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = 'tbl_requests'
          AND  COLUMN_NAME  = 'submitted_as'
    ) THEN
        ALTER TABLE `tbl_requests`
            ADD COLUMN `submitted_as` VARCHAR(20) DEFAULT NULL
            AFTER `submitted_by_id`;
    END IF;
END$$
DELIMITER ;
CALL `_dbm_add_submitted_as`();
DROP PROCEDURE IF EXISTS `_dbm_add_submitted_as`;

-- ── 2b. batch_id on tbl_requests (AFTER submitted_as) ────────────────────
DROP PROCEDURE IF EXISTS `_dbm_add_batch_id`;
DELIMITER $$
CREATE PROCEDURE `_dbm_add_batch_id`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = 'tbl_requests'
          AND  COLUMN_NAME  = 'batch_id'
    ) THEN
        ALTER TABLE `tbl_requests`
            ADD COLUMN `batch_id` CHAR(36) DEFAULT NULL
            AFTER `submitted_as`;
    END IF;
END$$
DELIMITER ;
CALL `_dbm_add_batch_id`();
DROP PROCEDURE IF EXISTS `_dbm_add_batch_id`;


-- ═══════════════════════════════════════════════════════════════════════════
-- ROOM REGISTRY MIGRATION  (Phase 1)
-- Tables: tbl_campuses, tbl_buildings, tbl_rooms
-- Adds room_id FK column to existing tbl_room_reservations.
-- Fully idempotent — safe to run on an existing lending_db.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── tbl_campuses ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tbl_campuses` (
  `campus_id`   int(11)      NOT NULL AUTO_INCREMENT,
  `campus_key`  varchar(50)  NOT NULL,
  `campus_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at`  datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`campus_id`),
  UNIQUE KEY `uq_campus_key` (`campus_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── tbl_buildings ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tbl_buildings` (
  `building_id`  int(11)      NOT NULL AUTO_INCREMENT,
  `campus_id`    int(11)      NOT NULL,
  `building_key` varchar(50)  NOT NULL,
  `name`         varchar(100) NOT NULL,
  `wing`         varchar(100) DEFAULT NULL,
  `floor_count`  tinyint(3)   NOT NULL DEFAULT 1,
  `image_path`   varchar(255) DEFAULT NULL,
  `icon`         varchar(50)  NOT NULL DEFAULT 'domain',
  `description`  varchar(255) DEFAULT NULL,
  `sort_order`   tinyint(3)   NOT NULL DEFAULT 0,
  `created_at`   datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`building_id`),
  UNIQUE KEY `uq_building_key` (`building_key`),
  CONSTRAINT `fk_buildings_campus`
    FOREIGN KEY (`campus_id`) REFERENCES `tbl_campuses` (`campus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── tbl_rooms ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tbl_rooms` (
  `room_id`          int(11)      NOT NULL AUTO_INCREMENT,
  `building_id`      int(11)      NOT NULL,
  `room_name`        varchar(100) NOT NULL,
  `floor_number`     tinyint(3)   NOT NULL DEFAULT 1,
  `floor_label`      varchar(50)  DEFAULT NULL,
  `seating_capacity` smallint(5)  DEFAULT NULL,
  `amenities`        varchar(500) DEFAULT NULL,
  `status`           enum('Available','Maintenance','Not Bookable')
                                  NOT NULL DEFAULT 'Available',
  `is_archived`      tinyint(1)   NOT NULL DEFAULT 0,
  `sort_order`       smallint(5)  NOT NULL DEFAULT 0,
  `created_at`       datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`room_id`),
  CONSTRAINT `fk_rooms_building`
    FOREIGN KEY (`building_id`) REFERENCES `tbl_buildings` (`building_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Add room_id FK to tbl_room_reservations (idempotent) ─────────────────
DROP PROCEDURE IF EXISTS `_rrm_add_room_id_col`;
DELIMITER $$
CREATE PROCEDURE `_rrm_add_room_id_col`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'tbl_room_reservations'
          AND COLUMN_NAME  = 'room_id'
    ) THEN
        ALTER TABLE `tbl_room_reservations`
            ADD COLUMN `room_id` int(11) DEFAULT NULL AFTER `id`,
            ADD CONSTRAINT `fk_reservations_room`
                FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`);
    END IF;
END$$
DELIMITER ;
CALL `_rrm_add_room_id_col`();
DROP PROCEDURE IF EXISTS `_rrm_add_room_id_col`;

-- ── SEED DATA ─────────────────────────────────────────────────────────────

-- Campuses
INSERT IGNORE INTO `tbl_campuses` (`campus_id`, `campus_key`, `campus_name`, `description`) VALUES
(1, 'main', 'PUP MAIN', 'Manage academic buildings, administrative offices, and central university facilities.'),
(2, 'cite', 'PUP CITE', 'Manage technical laboratories, engineering workshops, and specialized equipment facilities.');

-- Buildings
INSERT IGNORE INTO `tbl_buildings`
    (`building_id`, `campus_id`, `building_key`, `name`, `wing`, `floor_count`, `image_path`, `icon`, `description`, `sort_order`)
VALUES
(1, 1, 'main-building-a', 'Building A (Old)', 'South Wing', 5,
 'assets/images/faculty/pup-main-building-a-image.jpg', 'domain',
 'Administrative offices, lecture halls, organization rooms, and specialized laboratories spread across 5 floors.', 1),
(2, 1, 'main-building-b', 'Building B (New)', 'North Wing', 6,
 'assets/images/faculty/pup-main-building-b-image.jpg', 'business',
 'Modern laboratories, smart classrooms, and collaborative study spaces.', 2),
(3, 2, 'cite-main', 'PUP CITE Building', 'Main Block', 4,
 'assets/images/faculty/pup-cite-image.jpg', 'engineering',
 'Technical laboratories, computer labs, and specialized engineering facilities spread across 4 floors.', 1);

-- ── Building A (Old) — PUP MAIN  (building_id = 1) ───────────────────────
INSERT IGNORE INTO `tbl_rooms`
    (`building_id`, `room_name`, `floor_number`, `floor_label`, `status`, `sort_order`)
VALUES
-- 1st Floor
(1, 'Admin Office',         1, '1st Floor', 'Not Bookable', 1),
(1, 'Registration Office',  1, '1st Floor', 'Not Bookable', 2),
(1, 'OSAS Office',          1, '1st Floor', 'Not Bookable', 3),
(1, 'Office 1',             1, '1st Floor', 'Not Bookable', 4),
(1, 'Clinic',               1, '1st Floor', 'Not Bookable', 5),
(1, 'Staff Room',           1, '1st Floor', 'Not Bookable', 6),
-- 2nd Floor
(1, 'Room 201', 2, '2nd Floor', 'Available', 1),
(1, 'Room 202', 2, '2nd Floor', 'Available', 2),
(1, 'Room 203', 2, '2nd Floor', 'Available', 3),
(1, 'Room 204', 2, '2nd Floor', 'Available', 4),
(1, 'Room 205', 2, '2nd Floor', 'Available', 5),
-- 3rd Floor
(1, 'Room 301', 3, '3rd Floor', 'Available', 1),
(1, 'Room 302', 3, '3rd Floor', 'Available', 2),
(1, 'Room 303', 3, '3rd Floor', 'Available', 3),
(1, 'Room 304', 3, '3rd Floor', 'Available', 4),
(1, 'Room 305', 3, '3rd Floor', 'Available', 5),
-- 4th Floor
(1, 'Org Room',              4, '4th Floor', 'Not Bookable', 1),
(1, 'CSC Room',              4, '4th Floor', 'Not Bookable', 2),
(1, 'AVR 2',                 4, '4th Floor', 'Available',    3),
(1, 'Computer Laboratory 1', 4, '4th Floor', 'Available',    4),
(1, 'Computer Laboratory 2', 4, '4th Floor', 'Available',    5),
-- 5th Floor
(1, 'Chemistry Laboratory', 5, '5th Floor', 'Available', 1);

-- ── Building B (New) — PUP MAIN  (building_id = 2) ───────────────────────
INSERT IGNORE INTO `tbl_rooms`
    (`building_id`, `room_name`, `floor_number`, `floor_label`, `status`, `sort_order`)
VALUES
-- 1st Floor
(2, 'Library',          1, '1st Floor', 'Not Bookable', 1),
(2, 'Directors Office', 1, '1st Floor', 'Not Bookable', 2),
(2, 'Faculty Room',     1, '1st Floor', 'Not Bookable', 3),
(2, 'DO Office',        1, '1st Floor', 'Not Bookable', 4),
(2, 'Guidance Office',  1, '1st Floor', 'Not Bookable', 5),
-- 2nd Floor
(2, 'Research Room', 2, '2nd Floor', 'Available', 1),
(2, 'Room 202',      2, '2nd Floor', 'Available', 2),
(2, 'Room 203',      2, '2nd Floor', 'Available', 3),
(2, 'Room 204',      2, '2nd Floor', 'Available', 4),
(2, 'Room 205',      2, '2nd Floor', 'Available', 5),
-- 3rd Floor
(2, 'Room 301', 3, '3rd Floor', 'Available', 1),
(2, 'Room 302', 3, '3rd Floor', 'Available', 2),
(2, 'Room 303', 3, '3rd Floor', 'Available', 3),
(2, 'Room 304', 3, '3rd Floor', 'Available', 4),
(2, 'Room 305', 3, '3rd Floor', 'Available', 5),
-- 4th Floor
(2, 'Room 401', 4, '4th Floor', 'Available', 1),
(2, 'Room 402', 4, '4th Floor', 'Available', 2),
(2, 'AVR 1',    4, '4th Floor', 'Available', 3),
(2, 'Room 405', 4, '4th Floor', 'Available', 4),
-- 5th Floor
(2, 'Room 501', 5, '5th Floor', 'Available', 1),
(2, 'Room 502', 5, '5th Floor', 'Available', 2),
(2, 'Room 503', 5, '5th Floor', 'Available', 3),
(2, 'Room 504', 5, '5th Floor', 'Available', 4),
(2, 'Room 505', 5, '5th Floor', 'Available', 5),
(2, 'Room 506', 5, '5th Floor', 'Available', 6),
(2, 'Room 507', 5, '5th Floor', 'Available', 7),
(2, 'Room 508', 5, '5th Floor', 'Available', 8),
(2, 'Room 509', 5, '5th Floor', 'Available', 9),
-- 6th Floor
(2, 'Room 601', 6, '6th Floor', 'Available', 1),
(2, 'Room 602', 6, '6th Floor', 'Available', 2),
(2, 'Room 603', 6, '6th Floor', 'Available', 3),
(2, 'Room 604', 6, '6th Floor', 'Available', 4),
(2, 'Room 605', 6, '6th Floor', 'Available', 5),
(2, 'Room 606', 6, '6th Floor', 'Available', 6),
(2, 'Room 607', 6, '6th Floor', 'Available', 7),
(2, 'Room 608', 6, '6th Floor', 'Available', 8),
(2, 'Room 609', 6, '6th Floor', 'Available', 9),
(2, 'Room 610', 6, '6th Floor', 'Available', 10),
(2, 'Room 611', 6, '6th Floor', 'Available', 11),
(2, 'Room 612', 6, '6th Floor', 'Available', 12),
(2, 'Room 613', 6, '6th Floor', 'Available', 13),
(2, 'Room 614', 6, '6th Floor', 'Available', 14),
(2, 'Room 615', 6, '6th Floor', 'Available', 15),
(2, 'Room 616', 6, '6th Floor', 'Available', 16),
(2, 'Room 617', 6, '6th Floor', 'Available', 17),
(2, 'Room 618', 6, '6th Floor', 'Available', 18),
(2, 'Room 619', 6, '6th Floor', 'Available', 19),
(2, 'Room 620', 6, '6th Floor', 'Available', 20);

-- ── PUP CITE Building  (building_id = 3) ─────────────────────────────────
INSERT IGNORE INTO `tbl_rooms`
    (`building_id`, `room_name`, `floor_number`, `floor_label`, `status`, `sort_order`)
VALUES
-- 1st Floor
(3, 'Prayer Room',                  1, '1st Floor', 'Not Bookable', 1),
(3, 'Audiovisual Room',             1, '1st Floor', 'Available',    2),
(3, 'Testing Area',                 1, '1st Floor', 'Not Bookable', 3),
(3, 'Student Organization Room',    1, '1st Floor', 'Not Bookable', 4),
(3, 'Clinic Room',                  1, '1st Floor', 'Not Bookable', 5),
(3, 'Industrial Engineering Room',  1, '1st Floor', 'Not Bookable', 6),
(3, 'Director''s Office',           1, '1st Floor', 'Not Bookable', 7),
(3, 'Basketball Court',             1, '1st Floor', 'Not Bookable', 8),
(3, 'Room 103',                     1, '1st Floor', 'Available',    9),
(3, 'Room 105',                     1, '1st Floor', 'Available',    10),
(3, 'Room 106',                     1, '1st Floor', 'Available',    11),
(3, 'Room 116',                     1, '1st Floor', 'Available',    12),
(3, 'Room 118',                     1, '1st Floor', 'Available',    13),
(3, 'Room 119',                     1, '1st Floor', 'Available',    14),
-- 2nd Floor
(3, 'Admin Office',                   2, '2nd Floor', 'Not Bookable', 1),
(3, 'Faculty Lounge',                 2, '2nd Floor', 'Not Bookable', 2),
(3, 'AutoCAD & Multimedia Laboratory',2, '2nd Floor', 'Available',    3),
(3, 'Computer Laboratory 1',          2, '2nd Floor', 'Available',    4),
(3, 'Computer Laboratory 2',          2, '2nd Floor', 'Available',    5),
(3, 'Computer Laboratory 3',          2, '2nd Floor', 'Available',    6),
(3, 'Ergonomics Room',                2, '2nd Floor', 'Available',    7),
(3, 'Digital Laboratory Room',        2, '2nd Floor', 'Available',    8),
(3, 'Dispensing Room',                2, '2nd Floor', 'Not Bookable', 9),
(3, 'Microprocessing Laboratory Room',2, '2nd Floor', 'Available',    10),
(3, 'Room 203',                       2, '2nd Floor', 'Available',    11),
(3, 'Room 210',                       2, '2nd Floor', 'Available',    12),
(3, 'Room 212',                       2, '2nd Floor', 'Available',    13),
(3, 'Room 218',                       2, '2nd Floor', 'Available',    14),
-- 3rd Floor
(3, 'Library Room',           3, '3rd Floor', 'Not Bookable', 1),
(3, 'Library Extension Room', 3, '3rd Floor', 'Not Bookable', 2),
(3, 'Physics Room',           3, '3rd Floor', 'Available',    3),
(3, 'Room 301',               3, '3rd Floor', 'Available',    4),
(3, 'Room 302',               3, '3rd Floor', 'Available',    5),
(3, 'Room 303',               3, '3rd Floor', 'Available',    6),
(3, 'Room 304',               3, '3rd Floor', 'Available',    7),
(3, 'Room 305',               3, '3rd Floor', 'Available',    8),
(3, 'Room 307',               3, '3rd Floor', 'Available',    9),
(3, 'Room 308',               3, '3rd Floor', 'Available',    10),
(3, 'Room 309',               3, '3rd Floor', 'Available',    11),
(3, 'Room 310',               3, '3rd Floor', 'Available',    12),
-- 4th Floor
(3, 'Chemistry Laboratory Room', 4, '4th Floor', 'Available',    1),
(3, 'Student Lounge',            4, '4th Floor', 'Not Bookable', 2),
(3, 'Room 401',                  4, '4th Floor', 'Available',    3),
(3, 'Room 402',                  4, '4th Floor', 'Available',    4),
(3, 'Room 403',                  4, '4th Floor', 'Available',    5),
(3, 'Room 405',                  4, '4th Floor', 'Available',    6),
(3, 'Room 406',                  4, '4th Floor', 'Available',    7),
(3, 'Room 415',                  4, '4th Floor', 'Available',    8);


-- ═══════════════════════════════════════════════════════════════════════════
-- ROOM RESERVATION PHASE 2 MIGRATION
-- Replaces the placeholder tbl_room_reservations with a schema that supports:
--   - room_id FK, start_time/end_time (TIME), submitted_as, document_path
--   - Approved/Declined only (no Waiting/Pending)
-- Adds tbl_room_arbitration_log for engine audit trail.
-- Fully idempotent — safe to run on an existing lending_db.
-- ═══════════════════════════════════════════════════════════════════════════

-- Drop placeholder table (no live data in Phase 1)
DROP TABLE IF EXISTS `tbl_room_reservations`;

-- ── tbl_room_reservations (Phase 2 schema) ────────────────────────────────
CREATE TABLE `tbl_room_reservations` (
  `id`                int(11)       NOT NULL AUTO_INCREMENT,
  `room_id`           int(11)       NOT NULL,
  `faculty_id`        varchar(50)   NOT NULL,
  `faculty_name`      varchar(100)  NOT NULL,
  `submitted_as`      varchar(20)   NOT NULL DEFAULT 'personal',
  `submitted_by_name` varchar(100)  DEFAULT NULL,
  `submitted_by_id`   varchar(50)   DEFAULT NULL,
  `purpose`           varchar(200)  NOT NULL,
  `attendees`         smallint(5)   NOT NULL DEFAULT 1,
  `reservation_date`  date          NOT NULL,
  `start_time`        time          NOT NULL,
  `end_time`          time          NOT NULL,
  `notes`             text          DEFAULT NULL,
  `document_path`     varchar(255)  DEFAULT NULL,
  `status`            enum('Approved','Declined') NOT NULL DEFAULT 'Approved',
  `reason`            varchar(255)  DEFAULT NULL,
  `request_date`      datetime      DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_room_date` (`room_id`, `reservation_date`),
  CONSTRAINT `fk_rr_room`
    FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── tbl_room_arbitration_log ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tbl_room_arbitration_log` (
  `id`              int(11)       NOT NULL AUTO_INCREMENT,
  `reservation_id`  int(11)       NOT NULL,
  `room_id`         int(11)       NOT NULL,
  `room_name`       varchar(100)  NOT NULL,
  `borrower_id`     varchar(50)   NOT NULL,
  `borrower_name`   varchar(100)  NOT NULL,
  `decision`        varchar(20)   NOT NULL,
  `rule_applied`    varchar(60)   NOT NULL,
  `reason`          varchar(255)  DEFAULT NULL,
  `created_at`      datetime      DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reservation_id` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- ROOM RESERVATION PHASE 3 MIGRATION
-- Adds: Cancelled status to tbl_room_reservations,
--       tbl_room_waitlist, tbl_room_issues
-- Fully idempotent — safe to run on an existing lending_db.
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. Extend tbl_room_reservations status enum to include 'Cancelled'
DROP PROCEDURE IF EXISTS `_p3_extend_status_enum`;
DELIMITER $$
CREATE PROCEDURE `_p3_extend_status_enum`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = 'tbl_room_reservations'
          AND  COLUMN_NAME  = 'status'
          AND  COLUMN_TYPE  LIKE '%Cancelled%'
    ) THEN
        ALTER TABLE `tbl_room_reservations`
            MODIFY `status` enum('Approved','Declined','Cancelled')
                            NOT NULL DEFAULT 'Approved';
    END IF;
END$$
DELIMITER ;
CALL `_p3_extend_status_enum`();
DROP PROCEDURE IF EXISTS `_p3_extend_status_enum`;

-- 2. Add cancelled_at column (idempotent)
DROP PROCEDURE IF EXISTS `_p3_add_cancelled_at`;
DELIMITER $$
CREATE PROCEDURE `_p3_add_cancelled_at`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = 'tbl_room_reservations'
          AND  COLUMN_NAME  = 'cancelled_at'
    ) THEN
        ALTER TABLE `tbl_room_reservations`
            ADD COLUMN `cancelled_at` datetime DEFAULT NULL
            AFTER `request_date`;
    END IF;
END$$
DELIMITER ;
CALL `_p3_add_cancelled_at`();
DROP PROCEDURE IF EXISTS `_p3_add_cancelled_at`;

-- 3. tbl_room_waitlist
CREATE TABLE IF NOT EXISTS `tbl_room_waitlist` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `room_id`          int(11)      NOT NULL,
  `reservation_date` date         NOT NULL,
  `start_time`       time         NOT NULL,
  `end_time`         time         NOT NULL,
  `faculty_id`       varchar(50)  NOT NULL,
  `faculty_name`     varchar(100) NOT NULL,
  `faculty_email`    varchar(255) NOT NULL,
  `created_at`       datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_waitlist_faculty_slot`
    (`room_id`, `reservation_date`, `start_time`, `end_time`, `faculty_id`),
  KEY `idx_waitlist_slot`
    (`room_id`, `reservation_date`, `start_time`, `end_time`),
  CONSTRAINT `fk_wl_room`
    FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. tbl_room_issues
CREATE TABLE IF NOT EXISTS `tbl_room_issues` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `room_id`          int(11)      NOT NULL,
  `reported_by_id`   varchar(50)  NOT NULL,
  `reported_by_name` varchar(100) NOT NULL,
  `description`      text         NOT NULL,
  `status`           enum('Open','Resolved','Dismissed')
                                  NOT NULL DEFAULT 'Open',
  `admin_notes`      text         DEFAULT NULL,
  `created_at`       datetime     DEFAULT current_timestamp(),
  `resolved_at`      datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_issues_room`   (`room_id`),
  KEY `idx_issues_status` (`status`),
  CONSTRAINT `fk_issue_room`
    FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
