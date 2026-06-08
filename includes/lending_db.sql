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
  `submitted_by_id` varchar(50) DEFAULT NULL
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
  `role` varchar(50) DEFAULT 'Regular Faculty'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`fullname`, `faculty_id`, `email`, `backup_email`, `password`, `last_password_change`, `dob`, `gender`, `nationality`, `profile_picture`, `department`, `faculty_rank`, `phone`, `present_address`, `permanent_address`, `landline`, `emergency_name`, `emergency_relationship`, `emergency_phone`, `role`) VALUES
('Sandy Napiza', '2023-00004-BN-0', 'napizasandy@gmail.com', NULL, '$2y$10$LkK0vynd6.4zdgxJmlqJVOhjVAg7ZTm8uE8S1L/se4ihE1YOUHEWe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Regular Faculty'),
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
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `email` (`email`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
