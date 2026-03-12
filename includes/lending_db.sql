-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2026 at 09:08 PM
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
  `fullName` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(16) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_accounts`
--

INSERT INTO `tbl_accounts` (`fullName`, `email`, `password`, `last_login`) VALUES
('Redg Admin', 'main@admin.edu', 'admin123', '2026-03-12 22:33:35');

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
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_inventory`
--

INSERT INTO `tbl_inventory` (`item_id`, `item_name`, `category`, `quantity`, `image_path`, `created_at`, `is_archived`) VALUES
(8, 'HDMI Cable', 'Electronics and Accessories', 3, 'uploads/1768426958_item_hdmicable.webp', '2026-01-15 05:42:38', 0),
(9, 'AC Remote', 'Electronics and Accessories', 1, 'uploads/1768427004_item_remoteAc.jpg', '2026-01-15 05:43:24', 0),
(10, 'Extension', 'Electronics and Accessories', 5, 'uploads/1768427033_item_extension.webp', '2026-01-15 05:43:53', 0),
(11, 'Projector', 'Electronics and Accessories', 0, 'uploads/1768427059_item_projector.webp', '2026-01-15 05:44:19', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_requests`
--

CREATE TABLE `tbl_requests` (
  `id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `instructor` varchar(255) NOT NULL,
  `room` varchar(100) NOT NULL,
  `borrow_date` date NOT NULL,
  `return_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'Waiting',
  `request_date` datetime DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_requests`
--

INSERT INTO `tbl_requests` (`id`, `student_name`, `student_id`, `equipment_name`, `instructor`, `room`, `borrow_date`, `return_date`, `status`, `request_date`, `reason`) VALUES
(1, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'Sir Migs', 'A305', '2026-01-15', '2026-01-16', 'Overdue', '2026-01-15 11:04:23', NULL),
(2, 'Mendoza', '2023-00230-BN-0', 'AC Remote', 'elaine', 'B403', '2026-01-15', '2026-01-15', 'Declined', '2026-01-15 11:08:29', NULL),
(3, 'Frederick Rosales', '2023-00251-BN-0', 'Extension', 'Sir Migs', 'B203', '2026-01-23', '2026-01-24', 'Overdue', '2026-01-15 12:28:40', NULL),
(4, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'Ma\'am Donna', 'E031', '2026-02-05', '2026-02-12', 'Overdue', '2026-01-15 12:30:25', NULL),
(5, 'John Jr.', '2030-00071-BN-0', 'AC Remote', 'Sir Migs', 'B203', '2026-01-15', '2026-01-16', 'Overdue', '2026-01-15 13:51:29', NULL),
(6, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'Ma\'am Donna', 'B205', '2026-02-19', '2026-02-22', 'Overdue', '2026-02-18 00:27:57', NULL),
(7, 'Aiello Gabriel B. Lastrella', '2023-00294-BN-0', 'HDMI Cable', 'Sir Migs', 'Room A304', '2026-02-20', '2026-02-20', 'Overdue', '2026-02-19 15:07:14', NULL),
(8, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'sir noy', 'B304', '2026-02-23', '2026-02-25', 'Declined', '2026-02-22 17:29:16', 'Out of stock – maximum approved requests reached'),
(9, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'ma\'am JJ', 'A901', '2026-03-01', '2026-03-09', 'Declined', '2026-02-22 17:31:37', 'Out of stock – maximum approved requests reached'),
(10, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'Sir ajon', 'B207', '2026-02-25', '2026-02-26', 'Declined', '2026-02-22 17:38:07', 'Out of stock – maximum approved requests reached'),
(11, 'Derick Ramsey', '2023-00651-BN-0', 'AC Remote', 'jojo', 'b703', '2026-03-12', '2026-03-21', 'Returned', '2026-02-22 17:38:52', NULL),
(12, 'Frederick Rosales', '2023-00251-BN-0', 'Projector', 'joyce', 'b203', '2026-02-23', '2026-02-24', 'Declined', '2026-02-22 17:52:27', 'Out of stock – maximum approved requests reached'),
(13, 'Derick Ramsey', '2023-00651-BN-0', 'Projector', 'noy', 'j012', '2026-03-12', '2026-03-13', 'Approved', '2026-02-22 17:53:09', NULL),
(14, 'Frederick Rosales', '2023-00251-BN-0', 'HDMI Cable', 'sir redg', 'b201', '2026-02-24', '2026-02-25', 'Declined', '2026-02-23 17:46:05', 'Request expired – borrow date has already passed'),
(15, 'Frederick Rosales', '2023-00251-BN-0', 'AC Remote', 'sir aaron', 'B301', '2026-03-13', '2026-03-20', 'Approved', '2026-03-12 11:45:02', NULL),
(16, 'Frederick Rosales', '2023-00251-BN-0', 'Extension', 'Sir Migs', 'B205', '2026-03-13', '2026-03-14', 'Returned', '2026-03-12 13:05:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_reservations`
--

CREATE TABLE `tbl_room_reservations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
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
  `student_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `backup_email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `last_password_change` datetime DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `program` varchar(50) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `landline` varchar(20) DEFAULT NULL,
  `emergency_name` varchar(120) DEFAULT NULL,
  `emergency_relationship` varchar(50) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`fullname`, `student_id`, `email`, `backup_email`, `password`, `last_password_change`, `dob`, `gender`, `nationality`, `profile_picture`, `program`, `year_level`, `phone`, `present_address`, `permanent_address`, `landline`, `emergency_name`, `emergency_relationship`, `emergency_phone`) VALUES
('Sandy Napiza', '2023-00004-BN-0', 'napizasandy@gmail.com', NULL, '$2y$10$LkK0vynd6.4zdgxJmlqJVOhjVAg7ZTm8uE8S1L/se4ihE1YOUHEWe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('Philip San Jose', '2023-00111-BN-0', 'philip@gmail.com', NULL, '$2y$10$9R40gACxJd27H2pxjk1tD.wo4Gsrl3dhxTAIK82rRwYouxNu/FJKu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('Mendoza', '2023-00230-BN-0', 'elainejoyamendoza@iskolarngbayan.pup.edu', NULL, '$2y$10$6DLhVRPsBCHxBPqpeuenc.GJDhp1pq3aiW9RDnS.FH2Nn/k/jDyUq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('Frederick Rosales', '2023-00251-BN-0', 'iamfrederickr@gmail.com', 'frederick@gmail.com', '$2y$10$haZe66NIfJD5N5SEqNNTm.j9kYKYa/sJgcB7mSDBWqClftRV49okW', '2026-03-12 19:08:17', '2003-06-21', 'Male', 'Filipino', '2023-00251-BN-0_1773344427.JPG', 'BSIT', '3rd Year', '639662668443', '', '', '', NULL, NULL, NULL),
('Aiello Gabriel B. Lastrella', '2023-00294-BN-0', 'aiello.gabbb@gmail.com', NULL, '$2y$10$5rIqe5mYody6ITpFmVzAHudnh3UIghf14/B.w.26v/Uo5AcXZFaOu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('Derick Ramsey', '2023-00651-BN-0', 'derick@gmail.com', NULL, '$2y$10$STa.6os34BGuOndBPJR5/OSurigCNj299HS.Nl/aEYuFNIhulz0La', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('John Jr.', '2030-00071-BN-0', 'johnjohn1234@gmail.com', NULL, '$2y$10$Z4WaQGuntPYSnblonEtdKu5WMx9SPxHE5YUUAcd/8tPy6wsuVzuMS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_inventory`
--
ALTER TABLE `tbl_inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
