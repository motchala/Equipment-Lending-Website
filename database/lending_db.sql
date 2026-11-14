-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2026 at 12:04 PM
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
  `admin_no` tinyint(3) UNSIGNED DEFAULT NULL,
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

INSERT INTO `tbl_accounts` (`id`, `fullName`, `email`, `password`, `role`, `admin_no`, `created_at`, `last_login`, `dormant_until`, `session_epoch`, `last_password_change`, `backup_email`) VALUES
(1, 'Redg Abelida', 'main@admin.edu', '$2y$10$dLNCtd5IGqTMf7VUHFYyEOPI00YWyd9h9n4uj6dTptlcKwFNcw57e', 'Super Admin', 1, '2026-01-01 00:00:00', '2026-11-14 17:07:46', NULL, 0, NULL, ''),
(2, 'Chico Ramos', 'chico@admin.edu', '$2y$10$w.G7jIE6r6iSsVyn/T/G2OAhoqpyxgu4kkWKbHIhOky/CJk94xLGC', 'Admin', 2, '2026-10-02 11:05:03', '2026-11-09 23:25:05', NULL, 0, NULL, NULL),
(3, 'Jell Alagon', 'jell@admin.edu', '$2y$10$Q2ZyDvxioT548b/LuPKdEe7XYwf3bh3t//5dN/mX2bNIKEQ3buhf6', 'Admin', 3, '2026-11-10 06:01:18', NULL, NULL, 0, NULL, NULL);

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
(56, 1, 'Redg Admin', 'request', NULL, 'Deleted the entire On Loan list (7 records)', NULL, NULL, NULL, '2026-11-09 18:19:43'),
(57, 1, 'Redg Admin', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-09 21:25:05'),
(58, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-09 23:24:44'),
(59, 2, 'Chico Ramos', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-09 23:25:05'),
(60, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-10 02:18:04'),
(61, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Registration Office', NULL, NULL, NULL, '2026-11-10 04:37:36'),
(62, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Registration Office', NULL, NULL, NULL, '2026-11-10 04:37:41'),
(63, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Registration Office', NULL, NULL, NULL, '2026-11-10 04:37:58'),
(64, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Registration Office', NULL, NULL, NULL, '2026-11-10 04:38:04'),
(65, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Registration Office', NULL, NULL, NULL, '2026-11-10 04:48:33'),
(66, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Admin Office', NULL, NULL, NULL, '2026-11-10 04:48:39'),
(67, 1, 'Redg Admin', 'room', NULL, 'Archived facility: OSAS Office', NULL, NULL, NULL, '2026-11-10 04:48:44'),
(68, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Clinic', NULL, NULL, NULL, '2026-11-10 04:48:56'),
(69, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Staff Room', NULL, NULL, NULL, '2026-11-10 04:48:59'),
(70, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Office 1', NULL, NULL, NULL, '2026-11-10 04:49:09'),
(71, 1, 'Redg Admin', 'room', NULL, 'Added facility: Audio Visual Room 1', NULL, NULL, NULL, '2026-11-10 04:50:01'),
(72, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Audio Visual Room 1', NULL, NULL, NULL, '2026-11-10 04:50:12'),
(73, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Audio Visual Room 1', NULL, NULL, NULL, '2026-11-10 04:50:20'),
(74, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Org Room', NULL, NULL, NULL, '2026-11-10 05:11:22'),
(75, 1, 'Redg Admin', 'room', NULL, 'Archived facility: CSC Room', NULL, NULL, NULL, '2026-11-10 05:11:25'),
(76, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Library', NULL, NULL, NULL, '2026-11-10 05:11:31'),
(77, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Directors Office', NULL, NULL, NULL, '2026-11-10 05:11:34'),
(78, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Faculty Room', NULL, NULL, NULL, '2026-11-10 05:11:36'),
(79, 1, 'Redg Admin', 'room', NULL, 'Archived facility: DO Office', NULL, NULL, NULL, '2026-11-10 05:11:39'),
(80, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Guidance Office', NULL, NULL, NULL, '2026-11-10 05:11:44'),
(81, 1, 'Redg Admin', 'room', NULL, 'Updated facility: Audio Visual Room 2', NULL, NULL, NULL, '2026-11-10 05:11:59'),
(82, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Prayer Room', NULL, NULL, NULL, '2026-11-10 05:12:16'),
(83, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Industrial Engineering Room', NULL, NULL, NULL, '2026-11-10 05:12:19'),
(84, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Director\'s Office', NULL, NULL, NULL, '2026-11-10 05:12:23'),
(85, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Testing Area', NULL, NULL, NULL, '2026-11-10 05:12:26'),
(86, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Student Organization Room', NULL, NULL, NULL, '2026-11-10 05:12:30'),
(87, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Clinic Room', NULL, NULL, NULL, '2026-11-10 05:12:33'),
(88, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Basketball Court', NULL, NULL, NULL, '2026-11-10 05:12:40'),
(89, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Admin Office', NULL, NULL, NULL, '2026-11-10 05:12:44'),
(90, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Faculty Lounge', NULL, NULL, NULL, '2026-11-10 05:12:48'),
(91, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Dispensing Room', NULL, NULL, NULL, '2026-11-10 05:12:52'),
(92, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Library Room', NULL, NULL, NULL, '2026-11-10 05:12:59'),
(93, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Library Extension Room', NULL, NULL, NULL, '2026-11-10 05:13:02'),
(94, 1, 'Redg Admin', 'room', NULL, 'Archived facility: Student Lounge', NULL, NULL, NULL, '2026-11-10 05:13:09'),
(95, 1, 'Redg Admin', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-10 05:37:45'),
(96, 1, 'Redg Admin', 'account', NULL, 'Created admin account for Jell Alagon (Admin 3)', NULL, NULL, NULL, '2026-11-10 06:01:19'),
(97, 1, 'Redg Admin', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-10 14:39:55'),
(98, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-10 22:10:46'),
(99, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-10 22:12:10'),
(100, 1, 'Redg Abelida', 'faculty', NULL, 'Created faculty account for Archie Arevalo', NULL, NULL, NULL, '2026-11-10 22:13:00'),
(101, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-11 07:56:04'),
(102, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-11 07:56:20'),
(103, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-11 13:53:45'),
(104, 1, 'Redg Abelida', 'rooms', NULL, 'Imported rooms assigned: 723 weekly class meetings for 91 faculty in 45 rooms (file: PUPSync-FullScheduleList.xlsx)', NULL, NULL, NULL, '2026-11-11 13:54:30'),
(105, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-11 15:41:42'),
(106, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Active Speakers (Edmundo Dela Cruz)', 'request', 62, NULL, '2026-11-11 16:10:13'),
(107, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Adaptor (Edmundo Dela Cruz)', 'request', 61, NULL, '2026-11-11 16:10:26'),
(108, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Acer System Unit (Edmundo Dela Cruz)', 'request', 60, NULL, '2026-11-11 16:10:35'),
(109, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-11 16:14:14'),
(110, 1, 'Redg Abelida', 'equipment', NULL, 'Updated equipment: Alcohol Lamp', NULL, NULL, NULL, '2026-11-11 16:18:29'),
(111, 1, 'Redg Abelida', 'room', NULL, 'Updated facility: Room 103', NULL, NULL, NULL, '2026-11-11 16:30:46'),
(112, 1, 'Redg Abelida', 'room', NULL, 'Updated facility: Room 119', NULL, NULL, NULL, '2026-11-11 16:30:51'),
(113, 1, 'Redg Abelida', 'room', NULL, 'Updated facility: Room 119', NULL, NULL, NULL, '2026-11-11 16:32:18'),
(114, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-11 16:33:48'),
(115, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-11 19:38:46'),
(116, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-12 02:41:44'),
(117, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-12 02:43:52'),
(118, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-12 07:45:23'),
(119, 1, 'Redg Abelida', 'rooms', NULL, 'Imported rooms assigned: 723 weekly class meetings for 91 faculty in 45 rooms (file: PUPSync-FullScheduleList.xlsx, replaced the previous file)', NULL, NULL, NULL, '2026-11-12 07:45:49'),
(120, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-12 08:49:29'),
(121, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-12 08:49:55'),
(122, 1, 'Redg Abelida', 'request', 'overdue_notice_sent', 'Sent overdue return notice for Active Speakers to Stephen Perez', 'request', 67, NULL, '2026-11-12 17:14:10'),
(123, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Router (Michael Anjelo Miguel)', 'request', 65, NULL, '2026-11-12 17:14:30'),
(124, 1, 'Redg Abelida', 'request', 'overdue_notice_sent', 'Sent overdue return notice for Active Speakers to Stephen Perez', 'request', 66, NULL, '2026-11-12 17:14:47'),
(125, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Active Speakers (Michael Anjelo Miguel)', 'request', 63, NULL, '2026-11-12 17:14:52'),
(126, 1, 'Redg Abelida', 'request', 'overdue_notice_sent', 'Sent overdue return notice for Unterrupted Power Supply to Michael Anjelo Miguel', 'request', 64, NULL, '2026-11-12 18:07:04'),
(127, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Unterrupted Power Supply (Michael Anjelo Miguel)', 'request', 64, NULL, '2026-11-12 18:27:39'),
(128, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-12 23:29:53'),
(129, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-13 04:03:07'),
(130, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-13 05:13:37'),
(131, 1, 'Redg Abelida', 'request', 'overdue_notice_sent', 'Sent overdue return notice for Active Speakers to Stephen Perez', 'request', 67, NULL, '2026-11-13 05:52:00'),
(132, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Active Speakers (Stephen Perez)', 'request', 67, NULL, '2026-11-13 05:52:37'),
(133, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Active Speakers (Stephen Perez)', 'request', 66, NULL, '2026-11-13 05:52:40'),
(134, 1, 'Redg Abelida', 'request', 'request_claimed', 'Recorded manual claim: Active Speakers → Stephen Perez (1 unit(s))', 'request', 68, NULL, '2026-11-13 05:58:09'),
(135, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Active Speakers (Stephen Perez)', 'request', 68, NULL, '2026-11-13 06:03:36'),
(136, 1, 'Redg Abelida', 'request', 'request_claimed', 'Recorded manual claim: Unterrupted Power Supply → Stephen Perez (3 unit(s))', 'request', 69, NULL, '2026-11-13 06:33:03'),
(137, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Unterrupted Power Supply (Stephen Perez)', 'request', 69, NULL, '2026-11-13 06:33:15'),
(138, 1, 'Redg Abelida', 'request', 'request_claimed', 'Recorded manual claim: Unterrupted Power Supply → Stephen Perez (2 unit(s))', 'request', 70, NULL, '2026-11-13 06:35:23'),
(139, 1, 'Redg Abelida', 'request', 'request_returned', 'Recorded manual return: Unterrupted Power Supply (Stephen Perez)', 'request', 70, NULL, '2026-11-13 06:35:32'),
(140, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-13 07:44:08'),
(141, 1, 'Redg Abelida', 'settings', NULL, 'Unlocked the Imports tab', NULL, NULL, NULL, '2026-11-14 01:53:02'),
(142, 1, 'Redg Abelida', 'faculty', NULL, 'Created faculty account for Arvin Nuñez (8 weekly class meetings added to their schedule)', NULL, NULL, NULL, '2026-11-14 01:53:53'),
(143, 1, 'Redg Abelida', 'login', NULL, 'Signed in', NULL, NULL, NULL, '2026-11-14 17:07:46'),
(144, 1, 'Redg Abelida', 'faculty', NULL, 'Deleted faculty account of Arvin Nuñez', NULL, NULL, NULL, '2026-11-14 17:08:09'),
(145, 1, 'Redg Abelida', 'faculty', NULL, 'Created faculty account for Arvin Nuñez (8 weekly class meetings added to their schedule)', NULL, NULL, NULL, '2026-11-14 17:08:53');

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

INSERT INTO `tbl_arbitration_log` (`id`, `request_id`, `borrower_id`, `borrower_name`, `equipment_name`, `decision`, `rule_applied`, `reason`, `override_by`, `override_by_id`, `override_reason`, `admin_notes`, `created_at`) VALUES
(13, 27, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'Returned', 'qr_return', 'Auto-approved: no admin action within the buffer time.', 'Redg Admin', 1, NULL, NULL, '2026-11-09 10:57:07'),
(42, 55, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (20A)', 'Returned', 'qr_return', 'Equipment returned via QR scan — condition: Good', 'Redg Admin', 1, NULL, NULL, '2026-11-09 16:21:11'),
(43, 54, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (15A)', 'Returned', 'qr_return', 'Equipment returned via QR scan — condition: Good', 'Redg Admin', 1, NULL, NULL, '2026-11-09 16:21:10'),
(44, 56, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (30A)', 'Returned', 'qr_return', 'Equipment returned via QR scan — condition: Good', 'Redg Admin', 1, NULL, NULL, '2026-11-09 16:21:11'),
(51, 57, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, NULL, '2026-11-09 17:02:36'),
(52, 59, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (0-5A)', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, NULL, '2026-11-09 17:02:36'),
(53, 58, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Ammeter (0-100mA)', 'Approved', 'auto_approve_timeout', 'Auto-approved: no admin action within the buffer time.', NULL, NULL, NULL, NULL, '2026-11-09 17:02:36'),
(54, 60, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Acer System Unit', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-11 16:10:35'),
(55, 62, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Active Speakers', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-11 16:10:13'),
(56, 61, 'FA0008BN2023', 'Edmundo Dela Cruz', 'Adaptor', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-11 16:10:26'),
(57, 63, 'FA0005BN2016', 'Michael Anjelo Miguel', 'Active Speakers', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-12 17:14:52'),
(58, 64, 'FA0005BN2016', 'Michael Anjelo Miguel', 'Unterrupted Power Supply', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-12 18:27:39'),
(59, 65, 'FA0005BN2016', 'Michael Anjelo Miguel', 'Router', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-12 17:14:30'),
(60, 66, 'FA0011BN2026', 'Stephen Perez', 'Active Speakers', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-13 05:52:40'),
(61, 67, 'FA0011BN2026', 'Stephen Perez', 'Active Speakers', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-13 05:52:37'),
(70, 68, 'FA0011BN2026', 'Stephen Perez', 'Active Speakers', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-13 06:03:35'),
(73, 69, 'FA0011BN2026', 'Stephen Perez', 'Unterrupted Power Supply', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-13 06:33:15'),
(76, 70, 'FA0011BN2026', 'Stephen Perez', 'Unterrupted Power Supply', 'Returned', 'manual_return', 'Equipment returned manually by an administrator.', 'Redg Abelida', 1, 'Condition: Good', NULL, '2026-11-13 06:35:32');

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
-- Table structure for table `tbl_class_schedule`
--

CREATE TABLE `tbl_class_schedule` (
  `id` int(10) UNSIGNED NOT NULL,
  `import_id` int(11) DEFAULT NULL,
  `faculty_id` varchar(50) DEFAULT NULL,
  `faculty_name` varchar(255) DEFAULT NULL,
  `name_key` varchar(255) DEFAULT NULL,
  `employment` varchar(30) DEFAULT NULL,
  `account_faculty_id` varchar(255) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `room_code` varchar(20) NOT NULL,
  `subject_code` varchar(40) NOT NULL DEFAULT '',
  `subject_name` varchar(255) NOT NULL DEFAULT '',
  `section` varchar(60) NOT NULL DEFAULT '',
  `component` varchar(20) NOT NULL DEFAULT 'Lecture',
  `group_label` varchar(40) DEFAULT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_tba` tinyint(1) NOT NULL DEFAULT 0,
  `term_label` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_class_schedule`
--

INSERT INTO `tbl_class_schedule` (`id`, `import_id`, `faculty_id`, `faculty_name`, `name_key`, `employment`, `account_faculty_id`, `room_id`, `room_code`, `subject_code`, `subject_name`, `section`, `component`, `group_label`, `day_of_week`, `start_time`, `end_time`, `is_tba`, `term_label`, `created_at`) VALUES
(724, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 100, 'A402', 'ELEC CPE-E1', 'CPE Elective 1', 'BSCPE 3-1', 'Laboratory', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(725, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 100, 'A402', 'ELEC CPE-E1', 'CPE Elective 1', 'BSCPE 3-1', 'Lecture', NULL, 2, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(726, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 101, 'A403', 'ELEC CPE-E1', 'CPE Elective 1', 'BSCPE 3-2', 'Laboratory', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(727, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 101, 'A403', 'ELEC CPE-E1', 'CPE Elective 1', 'BSCPE 3-2', 'Lecture', NULL, 4, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(728, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 102, 'C101', 'COMP 017', 'Multimedia', 'BSIT 3-2', 'Laboratory', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(729, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 102, 'C101', 'COMP 017', 'Multimedia', 'BSIT 3-2', 'Lecture', NULL, 5, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(730, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 59, 'C103', 'ITEC 102', 'Basic Computer Hardware Servicing', 'DIT 1-1', 'Laboratory', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(731, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 87, 'C307', 'ITEC 102', 'Basic Computer Hardware Servicing', 'DIT 1-1', 'Lecture', NULL, 5, '15:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(732, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 89, 'C309', 'COMP 017', 'Multimedia', 'DIT 3-1', 'Lecture', NULL, 6, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(733, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 90, 'C310', 'COMP 017', 'Multimedia', 'DIT 3-2', 'Lecture', NULL, 2, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(734, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 103, 'CL207', 'COMP 017', 'Multimedia', 'DIT 3-1', 'Laboratory', 'Grp A', 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(735, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 103, 'CL207', 'COMP 017', 'Multimedia', 'DIT 3-2', 'Laboratory', 'Grp A', 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(736, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 104, 'CL209', 'COMP 017', 'Multimedia', 'DIT 3-1', 'Laboratory', 'Grp B', 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(737, 2, 'FA0006BN2024', 'Aaron A. Atienza', 'aaronaatienza', 'Permanent', 'FA0006BN2024', 104, 'CL209', 'COMP 017', 'Multimedia', 'DIT 3-2', 'Laboratory', 'Grp B', 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(738, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 8, 'A202', 'ELED 203', 'Teaching Social Studies in the Elementary Grades (Philippine History and Government)', 'BEED 2-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(739, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 8, 'A202', 'ELED 203', 'Teaching Social Studies in the Elementary Grades (Philippine History and Government)', 'BEED 2-2', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(740, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 9, 'A203', 'ELED 314', 'Teaching English in the Elementary Grades (Language Arts)', 'BEED 3-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(741, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 9, 'A203', 'ELED 314', 'Teaching English in the Elementary Grades (Language Arts)', 'BEED 3-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(742, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 10, 'A204', 'EDUC 021', 'Integrative Course in Education', 'BEED 4-1', 'Lecture', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(743, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 10, 'A204', 'EDUC 021', 'Integrative Course in Education', 'BEED 4-2', 'Lecture', NULL, 4, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(744, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 105, 'A404', 'SEEN 102', 'Language, Culture and Society', 'BSEDEN 1-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(745, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 106, 'A405', 'SEEN 102', 'Language, Culture and Society', 'BSEDEN 1-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(746, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 30, 'B203', 'EDUC 021', 'Integrative Course in Education', 'BSEDEN 4-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(747, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 30, 'B203', 'EDUC 021', 'Integrative Course in Education', 'BSEDEN 4-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(748, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 34, 'B302', 'EDUC 021', 'Integrative Course in Education', 'BSEDSS 4-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(749, 2, 'FA0003BN2021', 'Abigail T. Verterra', 'abigailtverterra', 'Permanent', NULL, 35, 'B303', 'EDUC 021', 'Integrative Course in Education', 'BSEDSS 4-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(750, 2, NULL, 'Aleli Joy A. Prestado', 'alelijoyaprestado', 'Part-Time', NULL, 9, 'A203', 'BUMA 012', 'Strategic Management', 'BSBA-HRM 3-1', 'Lecture', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(751, 2, NULL, 'Aleli Joy A. Prestado', 'alelijoyaprestado', 'Part-Time', NULL, 14, 'A303', 'HRMA 401', 'Strategic Human Resource Management', 'BSBA-HRM 4-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(752, 2, NULL, 'Aleli Joy A. Prestado', 'alelijoyaprestado', 'Part-Time', NULL, 107, 'B403', 'HRMA 401', 'Strategic Human Resource Management', 'BSBA-HRM 4-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(753, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 38, 'B401', 'INEN 403', 'Project Feasibility 1', 'BSIE 4-1', 'Laboratory', NULL, 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(754, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 39, 'B402', 'INEN 403', 'Project Feasibility 1', 'BSIE 4-2', 'Laboratory', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(755, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 41, 'B405', 'COMP 006', 'Data Structures and Algorithms', 'BSIT 2-3', 'Lecture', NULL, 5, '19:00:00', '21:00:00', 0, NULL, '2026-11-12 07:45:48'),
(756, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 102, 'C101', 'ELEC IT-E1', 'IT Elective 1', 'BSIT 3-1', 'Lecture', NULL, 3, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(757, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 102, 'C101', 'ELEC IT-E1', 'IT Elective 1', 'BSIT 3-2', 'Laboratory', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(758, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 102, 'C101', 'ELEC IT-E1', 'IT Elective 1', 'BSIT 3-2', 'Lecture', NULL, 5, '15:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(759, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 60, 'C105', 'ELEC IT-E1', 'IT Elective 1', 'BSIT 3-1', 'Laboratory', NULL, 4, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(760, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 103, 'CL207', 'COMP 006', 'Data Structures and Algorithms', 'BSIT 2-3', 'Laboratory', 'Grp A', 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(761, 2, 'FA0006BN2026', 'Alonica Mae M. Ebo', 'alonicamaemebo', 'Part-Time', NULL, 104, 'CL209', 'COMP 006', 'Data Structures and Algorithms', 'BSIT 2-3', 'Laboratory', 'Grp B', 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(762, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 10, 'A204', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSEDEN 1-1', 'Lecture', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(763, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 14, 'A303', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSEDSS 1-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(764, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 15, 'A304', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSEDEN 1-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(765, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 15, 'A304', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSPSY 2-1', 'Lecture', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(766, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 31, 'B204', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSIE 2-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(767, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 36, 'B304', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSIE 2-1', 'Lecture', NULL, 4, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(768, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 59, 'C103', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSBA-HRM 1-1', 'Lecture', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(769, 2, NULL, 'Andrea Mhe Abarca', 'andreamheabarca', 'Part-Time', NULL, 108, 'C206', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSEDEN 1-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(770, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 7, 'A201', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BEED 1-1', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(771, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 12, 'A301', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSBA-HRM 1-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(772, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 16, 'A305', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSCPE 1-3', 'Lecture', NULL, 1, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(773, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 33, 'B301', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSCPE 1-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(774, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 33, 'B301', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSIE 2-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(775, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 37, 'B305', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSIE 2-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(776, 2, 'FA0012ST2009', 'Angelita E. Nocon', 'angelitaenocon', 'Part-Time', NULL, 41, 'B405', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSIT 2-2', 'Lecture', NULL, 3, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(777, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 16, 'A305', 'EDUC 018', 'Foundation of Special and Inclusive Education', 'BEED 3-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(778, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 105, 'A404', 'EDUC 018', 'Foundation of Special and Inclusive Education', 'BEED 3-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(779, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 109, 'B201', 'EDUC 012', 'The Teaching Profession', 'BSEDEN 2-2', 'Lecture', NULL, 1, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(780, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 29, 'B202', 'EDUC 018', 'Foundation of Special and Inclusive Education', 'BSEDEN 3-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(781, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 33, 'B301', 'EDUC 018', 'Foundation of Special and Inclusive Education', 'BSEDSS 3-2', 'Lecture', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(782, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 34, 'B302', 'EDUC 018', 'Foundation of Special and Inclusive Education', 'BSEDSS 3-1', 'Lecture', NULL, 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(783, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 37, 'B305', 'EDUC 018', 'Foundation of Special and Inclusive Education', 'BSEDEN 3-2', 'Lecture', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(784, 2, 'FA0004BN2022', 'Annabelle V. Molina', 'annabellevmolina', 'Part-Time', NULL, 110, 'B404', 'EDUC 012', 'The Teaching Profession', 'BSEDSS 2-2', 'Lecture', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(785, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 13, 'A302', 'COMP 008', 'Data Communications and Networking', 'DIT 2-2', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(786, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 102, 'C101', 'COMP 016', 'Web Development', 'BSIT 3-2', 'Lecture', NULL, 6, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(787, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 59, 'C103', 'COMP 023', 'Social and Professional Issues in Computing', 'BSIT 4-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(788, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 111, 'C208', 'COMP 027', 'Mobile Application Development (SMP PLUS)', 'DIT 3-1', 'Lecture', NULL, 2, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(789, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 88, 'C308', 'COMP 023', 'Social and Professional Issues in Computing', 'DIT 2-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(790, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 89, 'C309', 'COMP 008', 'Data Communications and Networking', 'DIT 2-2', 'Lecture', NULL, 5, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(791, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 89, 'C309', 'COMP 027', 'Mobile Application Development (SMP PLUS)', 'DIT 3-1', 'Laboratory', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(792, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 103, 'CL207', 'COMP 016', 'Web Development', 'BSIT 3-2', 'Laboratory', 'Grp B', 2, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(793, 2, 'FA0001SP2014', 'Antonio D. Noel', 'antoniodnoel', 'Part-Time', NULL, 104, 'CL209', 'COMP 016', 'Web Development', 'BSIT 3-2', 'Laboratory', 'Grp A', 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(794, 2, NULL, 'April L. Villaverde', 'aprillvillaverde', 'Part-Time', NULL, 100, 'A402', 'CWTS 001', 'Civic Welfare Training Service 1', 'BSEDEN 1-2', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(795, 2, NULL, 'April L. Villaverde', 'aprillvillaverde', 'Part-Time', NULL, 34, 'B302', 'NSTP 001', 'National Service Training Program 1', 'BSIT 1-2', 'Lecture', NULL, 4, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(796, 2, NULL, 'April L. Villaverde', 'aprillvillaverde', 'Part-Time', NULL, 34, 'B302', 'CWTS 001', 'Civic Welfare Training Service 1', 'DIT 1-2', 'Lecture', NULL, 2, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(797, 2, NULL, 'April L. Villaverde', 'aprillvillaverde', 'Part-Time', NULL, 35, 'B303', 'NSTP 001', 'National Service Training Program 1', 'BSIT 1-3', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(798, 2, NULL, 'April L. Villaverde', 'aprillvillaverde', 'Part-Time', NULL, 36, 'B304', 'CWTS 001', 'Civic Welfare Training Service 1', 'BSEDSS 1-2', 'Lecture', NULL, 3, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(799, 2, 'FA0021BN2011', 'Araceli C. Amoranto', 'aracelicamoranto', 'Part-Time', NULL, 106, 'A405', 'EDUC 022', 'Field Study 1: Observations of Teaching-Learning in Actual School Environment', 'BEED 4-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(800, 2, 'FA0021BN2011', 'Araceli C. Amoranto', 'aracelicamoranto', 'Part-Time', NULL, 30, 'B203', 'SOST 316', 'Comparative Government and Politics', 'BSEDSS 3-2', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(801, 2, 'FA0021BN2011', 'Araceli C. Amoranto', 'aracelicamoranto', 'Part-Time', NULL, 31, 'B204', 'EDUC 016', 'Assessment in Learning 1', 'BSEDSS 3-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(802, 2, 'FA0021BN2011', 'Araceli C. Amoranto', 'aracelicamoranto', 'Part-Time', NULL, 31, 'B204', 'SOST 423', 'Property and Resources Management for Education', 'BSEDSS 4-2', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(803, 2, 'FA0021BN2011', 'Araceli C. Amoranto', 'aracelicamoranto', 'Part-Time', NULL, 33, 'B301', 'SOST 316', 'Comparative Government and Politics', 'BSEDSS 3-1', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(804, 2, 'FA0021BN2011', 'Araceli C. Amoranto', 'aracelicamoranto', 'Part-Time', NULL, 59, 'C103', 'SOST 423', 'Property and Resources Management for Education', 'BSEDSS 4-1', 'Lecture', NULL, 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(805, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 7, 'A201', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSCPE 2-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(806, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 14, 'A303', 'GEED 028', 'Reading Visual Arts', 'BSCPE 4-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(807, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 16, 'A305', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSCPE 2-3', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(808, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 105, 'A404', 'GEED 028', 'Reading Visual Arts', 'BSCPE 4-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(809, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 37, 'B305', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSIE 3-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(810, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 37, 'B305', 'GEED 008', 'Ethics/Etika', 'BSIE 3-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(811, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 38, 'B401', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSIE 3-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(812, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 110, 'B404', 'GEED 028', 'Reading Visual Arts', 'BSIT 2-1', 'Lecture', NULL, 6, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(813, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 41, 'B405', 'GEED 028', 'Reading Visual Arts', 'BSIT 2-3', 'Lecture', NULL, 1, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(814, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 102, 'C101', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSIT 3-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(815, 2, 'FA0143MN2016', 'Archie C. Arevalo', 'archiecarevalo', 'Permanent', 'FA0143MN2016', 112, 'C107', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSPSY 2-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(816, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 9, 'A203', 'COMP 018', 'Database Administration', 'DIT 3-1', 'Laboratory', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(817, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 100, 'A402', 'CMPE 302', 'Introduction Hardware Descriptive Language (HDL)', 'BSCPE 3-1', 'Laboratory', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(818, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 102, 'C101', 'COMP 018', 'Database Administration', 'BSIT 3-2', 'Laboratory', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(819, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 102, 'C101', 'COMP 018', 'Database Administration', 'BSIT 3-2', 'Lecture', NULL, 5, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(820, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 108, 'C206', 'CMPE 311', 'Microprocessors', 'DCPET 3-1', 'Lecture', NULL, 5, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(821, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 113, 'C306', 'CMPE 311', 'Microprocessors', 'DCPET 3-1', 'Laboratory', NULL, 4, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(822, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 89, 'C309', 'COMP 018', 'Database Administration', 'DIT 3-1', 'Lecture', NULL, 3, '09:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(823, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 90, 'C310', 'COMP 018', 'Database Administration', 'DIT 3-2', 'Laboratory', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(824, 2, NULL, 'Ariel S. Ponce', 'arielsponce', 'Part-Time', NULL, 90, 'C310', 'COMP 018', 'Database Administration', 'DIT 3-2', 'Lecture', NULL, 1, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(825, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 14, 'A303', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BEED 1-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(826, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 114, 'A401', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSCPE 2-2', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(827, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 31, 'B204', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSEDSS 1-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(828, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 31, 'B204', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSPSY 2-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(829, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 32, 'B205', 'SOST 214', 'Socio-Cultural Anthropology', 'BSEDSS 2-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(830, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 32, 'B205', 'SOST 214', 'Socio-Cultural Anthropology', 'BSEDSS 2-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(831, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 38, 'B401', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSIE 3-2', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(832, 2, 'FA0005BN2026', 'Arvin M. Nuñez', 'arvinmnuez', 'Part-Time', 'FA0005BN2026', 112, 'C107', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSPSY 2-2', 'Lecture', NULL, 1, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(833, 2, 'FA0005SP2012', 'Benjamin A. Abarquez Jr.', 'benjaminaabarquezjr', 'Part-Time', NULL, 107, 'B403', 'ACCO 014', 'Principles of Accounting', 'BSIT 1-2', 'Lecture', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(834, 2, 'FA0005SP2012', 'Benjamin A. Abarquez Jr.', 'benjaminaabarquezjr', 'Part-Time', NULL, 107, 'B403', 'ACCO 014', 'Principles of Accounting', 'BSIT 1-3', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(835, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 7, 'A201', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BEED 1-2', 'Lecture', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(836, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 12, 'A301', 'GEED 008', 'Ethics/Etika', 'BSBA-HRM 2-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(837, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 12, 'A301', 'GEED 008', 'Ethics/Etika', 'BSBA-HRM 2-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(838, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 105, 'A404', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSEDEN 1-1', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(839, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 106, 'A405', 'PSYC 302', 'Filipino Psychology/Sikolohiyang Pilipino', 'BSPSY 3-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(840, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 30, 'B203', 'GEED 008', 'Ethics/Etika', 'BSIE 3-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(841, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 41, 'B405', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSIT 2-3', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(842, 2, 'FA0010BN2026', 'Benjamin Hernandez', 'benjaminhernandez', 'Part-Time', NULL, 112, 'C107', 'PSYC 202', 'Cognitive Psychology', 'BSPSY 2-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(843, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 10, 'A204', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'Lecture', NULL, 1, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(844, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 16, 'A305', 'CMPE 101', 'Computer Engineering as a Discipline', 'BSCPE 1-3', 'Lecture', NULL, 3, '07:00:00', '08:00:00', 0, NULL, '2026-11-12 07:45:48'),
(845, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 114, 'A401', 'CMPE 306', 'Fundamentals of Mixed Signals and Sensors', 'BSCPE 3-1', 'Lecture', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(846, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 101, 'A403', 'CMPE 302', 'Introduction Hardware Descriptive Language (HDL)', 'BSCPE 3-2', 'Laboratory', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(847, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 105, 'A404', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'Laboratory', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(848, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 105, 'A404', 'CMPE 405', 'Digital Signal Processing', 'BSCPE 4-2', 'Lecture', NULL, 4, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(849, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 106, 'A405', 'CMPE 303', 'Computer Engineering Drafting and Design', 'BSCPE 3-2', 'Laboratory', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(850, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 77, 'C212', 'CMPE 101', 'Computer Engineering as a Discipline', 'DCPET 2-1', 'Lecture', NULL, 4, '07:00:00', '08:00:00', 0, NULL, '2026-11-12 07:45:48'),
(851, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 77, 'C212', 'CPET 201', '2D Animation', 'DCPET 2-1', 'Laboratory', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(852, 2, NULL, 'Carissa C. Morano', 'carissacmorano', 'Part-Time', NULL, 77, 'C212', 'CPET 201', '2D Animation', 'DCPET 2-1', 'Laboratory', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(853, 2, 'FA0002BN2022', 'Carmen L. Yupangco', 'carmenlyupangco', 'Part-Time', NULL, 34, 'B302', 'PSYC 305', 'Field Methods in Psychology', 'BSPSY 3-2', 'Lecture', NULL, 6, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(854, 2, 'FA0002BN2022', 'Carmen L. Yupangco', 'carmenlyupangco', 'Part-Time', NULL, 108, 'C206', 'PSYC 305', 'Field Methods in Psychology', 'BSPSY 3-2', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(855, 2, 'FA0002BN2022', 'Carmen L. Yupangco', 'carmenlyupangco', 'Part-Time', NULL, 111, 'C208', 'PSYC 305', 'Field Methods in Psychology', 'BSPSY 3-1', 'Lecture', NULL, 6, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(856, 2, 'FA0002BN2022', 'Carmen L. Yupangco', 'carmenlyupangco', 'Part-Time', NULL, 111, 'C208', 'PSYC 402', 'Practicum in Psychology 1 (Industrial Setting)', 'BSPSY 4-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(857, 2, 'FA0002BN2022', 'Carmen L. Yupangco', 'carmenlyupangco', 'Part-Time', NULL, 76, 'C210', 'PSYC 305', 'Field Methods in Psychology', 'BSPSY 3-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(858, 2, 'FA0002BN2022', 'Carmen L. Yupangco', 'carmenlyupangco', 'Part-Time', NULL, 76, 'C210', 'PSYC 402', 'Practicum in Psychology 1 (Industrial Setting)', 'BSPSY 4-2', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(859, 2, NULL, 'Celine N. Ocfemia', 'celinenocfemia', 'Part-Time', NULL, 31, 'B204', 'CWTS 001', 'Civic Welfare Training Service 1', 'BSEDSS 1-1', 'Lecture', NULL, 4, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(860, 2, NULL, 'Celine N. Ocfemia', 'celinenocfemia', 'Part-Time', NULL, 36, 'B304', 'NSTP 001', 'National Service Training Program 1', 'BSIE 1-2', 'Lecture', NULL, 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(861, 2, NULL, 'Celine N. Ocfemia', 'celinenocfemia', 'Part-Time', NULL, 39, 'B402', 'NSTP 001', 'National Service Training Program 1', 'BSIT 1-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(862, 2, NULL, 'Celine N. Ocfemia', 'celinenocfemia', 'Part-Time', NULL, 59, 'C103', 'CWTS 001', 'Civic Welfare Training Service 1', 'DCPET 1-2', 'Lecture', NULL, 2, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(863, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 39, 'B402', 'COMP 001', 'Introduction to Computing', 'BSIT 1-1', 'Lecture', NULL, 2, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(864, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 87, 'C307', 'COMP 001', 'Introduction to Computing', 'DIT 1-1', 'Lecture', NULL, 5, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(865, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 88, 'C308', 'COMP 001', 'Introduction to Computing', 'DIT 1-2', 'Lecture', NULL, 4, '15:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(866, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 103, 'CL207', 'COMP 001', 'Introduction to Computing', 'DIT 1-1', 'Laboratory', 'Grp A', 1, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(867, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'BSIT 1-1', 'Laboratory', 'Grp B', 4, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(868, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'BSIT 1-1', 'Laboratory', 'Grp A', 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(869, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'DIT 1-1', 'Laboratory', 'Grp B', 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(870, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'DIT 1-2', 'Laboratory', 'Grp A', 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(871, 2, NULL, 'Charisse A. Nethercott', 'charisseanethercott', 'Part-Time', NULL, 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'DIT 1-2', 'Laboratory', 'Grp B', 3, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(872, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 8, 'A202', 'EDUC 011', 'The Child and Adolescent Learner and Learning Principles', 'BEED 2-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(873, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 8, 'A202', 'EDUC 011', 'The Child and Adolescent Learner and Learning Principles', 'BEED 2-2', 'Lecture', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(874, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 9, 'A203', 'ELED 320', 'Technology for Teaching in Elementary Grades', 'BEED 3-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(875, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 13, 'A302', 'EDUC 016', 'Assessment in Learning 1', 'BEED 3-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(876, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 109, 'B201', 'EDUC 011', 'The Child and Adolescent Learner and Learning Principles', 'BSEDEN 2-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(877, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 109, 'B201', 'EDUC 011', 'The Child and Adolescent Learner and Learning Principles', 'BSEDEN 2-2', 'Lecture', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(878, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 29, 'B202', 'SEEN 311', 'Language Education Research', 'BSEDEN 3-1', 'Lecture', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(879, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 32, 'B205', 'EDUC 011', 'The Child and Adolescent Learner and Learning Principles', 'BSEDSS 2-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(880, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 32, 'B205', 'EDUC 011', 'The Child and Adolescent Learner and Learning Principles', 'BSEDSS 2-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(881, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 33, 'B301', 'EDUC 016', 'Assessment in Learning 1', 'BSEDSS 3-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(882, 2, 'FA0161MN2010', 'Cheryl Joyce D. Jurado', 'cheryljoycedjurado', 'Permanent', NULL, 102, 'C101', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSIT 3-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(883, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 8, 'A202', 'ELEC BEED-GEE1', 'Gen. Ed. Elective 1', 'BEED 2-2', 'Lecture', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(884, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 106, 'A405', 'GEED 017', 'Philippine Indigenous Communities', 'BSEDEN 1-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(885, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 32, 'B205', 'ELEC SS-GE3', 'Gen. Ed. Elective 3', 'BSEDSS 2-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(886, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 32, 'B205', 'ELEC SS-GE3', 'Gen. Ed. Elective 3', 'BSEDSS 2-2', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(887, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 35, 'B303', 'SOST 101', 'Foundation of Social Studies', 'BSEDSS 1-2', 'Lecture', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(888, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 37, 'B305', 'SOST 315', 'Comparative Economic Planning', 'BSEDSS 3-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(889, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 41, 'B405', 'SOST 101', 'Foundation of Social Studies', 'BSEDSS 1-1', 'Lecture', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(890, 2, 'FA0005BN2019', 'Dennis R. Estocado', 'dennisrestocado', 'Part-Time', NULL, 90, 'C310', 'SOST 315', 'Comparative Economic Planning', 'BSEDSS 3-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(891, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 7, 'A201', 'COMP 006', 'Data Structures and Algorithms', 'DIT 2-1', 'Lecture', NULL, 6, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(892, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 33, 'B301', 'COMP 006', 'Data Structures and Algorithms', 'BSIT 2-2', 'Lecture', NULL, 1, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(893, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 89, 'C309', 'COMP 006', 'Data Structures and Algorithms', 'DIT 2-2', 'Lecture', NULL, 4, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(894, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 103, 'CL207', 'COMP 006', 'Data Structures and Algorithms', 'BSIT 2-2', 'Laboratory', 'Grp B', 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(895, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 103, 'CL207', 'COMP 006', 'Data Structures and Algorithms', 'DIT 2-1', 'Laboratory', 'Grp B', 2, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(896, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 103, 'CL207', 'COMP 006', 'Data Structures and Algorithms', 'DIT 2-1', 'Laboratory', 'Grp A', 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(897, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 103, 'CL207', 'COMP 006', 'Data Structures and Algorithms', 'DIT 2-2', 'Laboratory', 'Grp A', 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(898, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 103, 'CL207', 'COMP 006', 'Data Structures and Algorithms', 'DIT 2-2', 'Laboratory', 'Grp B', 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(899, 2, 'FA0008BN2023', 'Edmundo S. Dela Cruz', 'edmundosdelacruz', 'Part-Time', 'FA0008BN2023', 104, 'CL209', 'COMP 006', 'Data Structures and Algorithms', 'BSIT 2-2', 'Laboratory', 'Grp A', 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(900, 2, NULL, 'Edward Francis Gabor', 'edwardfrancisgabor', 'Part-Time', NULL, 8, 'A202', 'ELED 209', 'Edukasyon Pantahanan at Pangkabuhayan', 'BEED 2-2', 'Lecture', NULL, 3, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(901, 2, NULL, 'Edward Francis Gabor', 'edwardfrancisgabor', 'Part-Time', NULL, 9, 'A203', 'ELED 318', 'Research In Elementary Education', 'BEED 3-1', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(902, 2, NULL, 'Edward Francis Gabor', 'edwardfrancisgabor', 'Part-Time', NULL, 12, 'A301', 'ELED 318', 'Research In Elementary Education', 'BEED 3-2', 'Lecture', NULL, 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(903, 2, NULL, 'Edward Francis Gabor', 'edwardfrancisgabor', 'Part-Time', NULL, 13, 'A302', 'ELED 207', 'Teaching Math in the Primary Grades', 'BEED 2-2', 'Lecture', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(904, 2, NULL, 'Edward Francis Gabor', 'edwardfrancisgabor', 'Part-Time', NULL, 13, 'A302', 'EDUC 023', 'Field Study 2: Participation and Teaching Assistanship', 'BSEDEN 4-2', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(905, 2, NULL, 'Edward Francis Gabor', 'edwardfrancisgabor', 'Part-Time', NULL, 90, 'C310', 'EDUC 022', 'Field Study 1: Observations of Teaching-Learning in Actual School Environment', 'BSEDSS 4-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(906, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 9, 'A203', 'EDUC 014', 'Building and Enhancing New Literacies Across the Curriculum', 'BEED 3-2', 'Lecture', NULL, 5, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(907, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 31, 'B204', 'EDUC 014', 'Building and Enhancing New Literacies Across the Curriculum', 'BSEDEN 3-2', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(908, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 32, 'B205', 'EDUC 014', 'Building and Enhancing New Literacies Across the Curriculum', 'BSEDEN 3-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(909, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 107, 'B403', 'EDUC 014', 'Building and Enhancing New Literacies Across the Curriculum', 'BSEDSS 3-2', 'Lecture', NULL, 4, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(910, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 41, 'B405', 'EDUC 014', 'Building and Enhancing New Literacies Across the Curriculum', 'BSEDSS 3-1', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(911, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 112, 'C107', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSPSY 2-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(912, 2, 'FA0003BN2024', 'Elena de Torres Teh', 'elenadetorresteh', 'Part-Time', NULL, 89, 'C309', 'EDUC 014', 'Building and Enhancing New Literacies Across the Curriculum', 'BEED 3-1', 'Lecture', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(913, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 8, 'A202', 'ELED 209', 'Edukasyon Pantahanan at Pangkabuhayan', 'BEED 2-1', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(914, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 9, 'A203', 'ELED 317', 'Good Manners and Right Conduct (Edukasyon sa Pagpapakatao)', 'BEED 3-1', 'Lecture', NULL, 5, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(915, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 9, 'A203', 'EDUC 016', 'Assessment in Learning 1', 'BEED 3-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(916, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 10, 'A204', 'HRMA 402', 'Strategic Innovation and Design Thinking', 'BSBA-HRM 4-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(917, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 15, 'A304', 'HRMA 402', 'Strategic Innovation and Design Thinking', 'BSBA-HRM 4-2', 'Lecture', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(918, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 29, 'B202', 'SEEN 322', 'Technology for Teaching and Learning 2 (Technology in Language Education)', 'BSEDEN 3-1', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(919, 2, 'FA0011BN2025', 'Elvira R. Yambot', 'elviraryambot', 'Part-Time', NULL, 33, 'B301', 'EDUC 016', 'Assessment in Learning 1', 'BSEDEN 3-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(920, 2, 'FA0007BN2023', 'Federick C. Macarubbo', 'federickcmacarubbo', 'Part-Time', NULL, 7, 'A201', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BEED 1-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(921, 2, 'FA0007BN2023', 'Federick C. Macarubbo', 'federickcmacarubbo', 'Part-Time', NULL, 7, 'A201', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BEED 1-2', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(922, 2, 'FA0007BN2023', 'Federick C. Macarubbo', 'federickcmacarubbo', 'Part-Time', NULL, 14, 'A303', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSBA-HRM 1-1', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(923, 2, 'FA0007BN2023', 'Federick C. Macarubbo', 'federickcmacarubbo', 'Part-Time', NULL, 106, 'A405', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSEDEN 1-2', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(924, 2, 'FA0007BN2023', 'Federick C. Macarubbo', 'federickcmacarubbo', 'Part-Time', NULL, 38, 'B401', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSEDSS 1-1', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(925, 2, 'FA0007BN2023', 'Federick C. Macarubbo', 'federickcmacarubbo', 'Part-Time', NULL, 39, 'B402', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSIE 2-2', 'Lecture', NULL, 3, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(926, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 10, 'A204', 'EDUC 022', 'Field Study 1: Observations of Teaching-Learning in Actual School Environment', 'BEED 4-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(927, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 10, 'A204', 'ENGL 011', 'Business Communication', 'BSBA-HRM 2-2', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(928, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 12, 'A301', 'ENGL 011', 'Business Communication', 'BSBA-HRM 2-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(929, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 14, 'A303', 'SEEN 317', 'Survey of English and American Literature', 'BSEDEN 3-1', 'Lecture', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(930, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 29, 'B202', 'SEEN 317', 'Survey of English and American Literature', 'BSEDEN 3-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(931, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 33, 'B301', 'SEEN 311', 'Language Education Research', 'BSEDEN 3-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(932, 2, NULL, 'Francesca T. Chavenia', 'francescatchavenia', 'Part-Time', NULL, 34, 'B302', 'SEEN 423', 'Remedial Instruction', 'BSEDEN 4-1', 'Lecture', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48');
INSERT INTO `tbl_class_schedule` (`id`, `import_id`, `faculty_id`, `faculty_name`, `name_key`, `employment`, `account_faculty_id`, `room_id`, `room_code`, `subject_code`, `subject_name`, `section`, `component`, `group_label`, `day_of_week`, `start_time`, `end_time`, `is_tba`, `term_label`, `created_at`) VALUES
(933, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 8, 'A202', 'ELEC BEED-GEE1', 'Gen. Ed. Elective 1', 'BEED 2-1', 'Lecture', NULL, 6, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(934, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 8, 'A202', 'ELEC BEED-GEE2', 'Gen. Ed. Elective 2', 'BEED 2-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(935, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 11, 'A205', 'GEED 003', 'The Contemporary World/Ang Kasalukuyang Daigdig', 'BSBA-HRM 1-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(936, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 14, 'A303', 'GEED 020', 'Politics, Governance and Citizenship', 'BSCPE 2-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(937, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 114, 'A401', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSCPE 2-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(938, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 114, 'A401', 'GEED 020', 'Politics, Governance and Citizenship', 'BSCPE 2-2', 'Lecture', NULL, 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(939, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 106, 'A405', 'GEED 020', 'Politics, Governance and Citizenship', 'BSCPE 2-3', 'Lecture', NULL, 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(940, 2, 'FA0009BN2023', 'Gatsby Aparejado Valdemoro', 'gatsbyaparejadovaldemoro', 'Part-Time', NULL, 112, 'C107', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSPSY 2-1', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(941, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 11, 'A205', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(942, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 100, 'A402', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'Laboratory', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(943, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 100, 'A402', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(944, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 101, 'A403', 'CMPE 305', 'Data and Digital Communications', 'BSCPE 3-2', 'Laboratory', NULL, 1, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(945, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 111, 'C208', 'CMPE 305', 'Data and Digital Communications', 'DCPET 3-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(946, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 76, 'C210', 'CMPE 105', 'Computer Hardware Fundamentals', 'DCPET 1-1', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(947, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 76, 'C210', 'CMPE 105', 'Computer Hardware Fundamentals', 'DCPET 1-1', 'Laboratory', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(948, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 77, 'C212', 'CMPE 105', 'Computer Hardware Fundamentals', 'DCPET 1-2', 'Laboratory', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(949, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 77, 'C212', 'CMPE 105', 'Computer Hardware Fundamentals', 'DCPET 1-2', 'Laboratory', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(950, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 113, 'C306', 'CMPE 305', 'Data and Digital Communications', 'DCPET 3-1', 'Laboratory', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(951, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 87, 'C307', 'CMPE 305', 'Data and Digital Communications', 'DCPET 3-2', 'Laboratory', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(952, 2, 'FA0013BN2011', 'Gerino P. Abelida Jr.', 'gerinopabelidajr', 'Permanent', NULL, 87, 'C307', 'CMPE 305', 'Data and Digital Communications', 'DCPET 3-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(953, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 12, 'A301', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSBA-HRM 2-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(954, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 114, 'A401', 'GEED 006', 'Art Appreciation/Pagpapahalaga sa Sining', 'BSCPE 2-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(955, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 101, 'A403', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSBA-HRM 2-1', 'Lecture', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(956, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 106, 'A405', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSCPE 2-3', 'Lecture', NULL, 6, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(957, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 30, 'B203', 'SOST 322', 'Technology for Teaching and Learning 2', 'BSEDSS 3-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(958, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 33, 'B301', 'SOST 322', 'Technology for Teaching and Learning 2', 'BSEDSS 3-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(959, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 36, 'B304', 'GEED 037', 'Life and Works of Rizal/Buhay at Mga Gawa ni Rizal', 'BSIE 2-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(960, 2, NULL, 'Gian Carlo C. Villagracia', 'giancarlocvillagracia', 'Part-Time', NULL, 41, 'B405', 'GEED 028', 'Reading Visual Arts', 'BSIT 2-2', 'Lecture', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(961, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 14, 'A303', 'INTE 402', 'Capstone Project 2', 'BSIT 4-1', 'Lecture', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(962, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 29, 'B202', 'INEN 407', 'Seminars, Industry Inspection and Observation', 'BSIE 4-1', 'Laboratory', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(963, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 31, 'B204', 'INEN 101', 'Introduction to Industrial Engineering', 'BSIE 1-1', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(964, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 34, 'B302', 'ENSC 025', 'Environmental Science and Engineering', 'BSIE 3-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(965, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 37, 'B305', 'INEN 301', 'Operations Research 1', 'BSIE 3-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(966, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 38, 'B401', 'INEN 407', 'Seminars, Industry Inspection and Observation', 'BSIE 4-1', 'Laboratory', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(967, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 39, 'B402', 'INEN 401', 'IE Capstone Project 1', 'BSIE 4-2', 'Laboratory', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(968, 2, 'FA0006BN2025', 'Gio M. Marcos', 'giommarcos', 'Part-Time', NULL, 59, 'C103', 'INTE 402', 'Capstone Project 2', 'BSIT 4-2', 'Lecture', NULL, 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(969, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 10, 'A204', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(970, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 10, 'A204', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSCPE 1-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(971, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 30, 'B203', 'CHEM 015', 'Chemistry for Engineers', 'BSIE 1-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(972, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 35, 'B303', 'CHEM 015', 'Chemistry for Engineers', 'BSIE 1-1', 'Laboratory', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(973, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 77, 'C212', 'CHEM 015', 'Chemistry for Engineers', 'DCPET 1-2', 'Laboratory', NULL, 3, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(974, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 77, 'C212', 'CHEM 015', 'Chemistry for Engineers', 'DCPET 1-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(975, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 103, 'CL207', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-2', 'Laboratory', 'Grp B', 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(976, 2, 'FA0001BN2020', 'Glorina Divina P. Orozco', 'glorinadivinaporozco', 'Part-Time', NULL, 103, 'CL207', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-2', 'Laboratory', 'Grp A', 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(977, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 101, 'A403', 'CMPE 402', 'Computer Project Management', 'BSCPE 4-1', 'Lecture', NULL, 2, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(978, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 34, 'B302', 'CMPE 011', 'Computer Fundamentals and Programming', 'BSIE 1-1', 'Laboratory', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(979, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 35, 'B303', 'CMPE 011', 'Computer Fundamentals and Programming', 'BSIE 1-1', 'Laboratory', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(980, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 59, 'C103', 'INTE 403', 'Systems Administration and Maintenance', 'BSIT 4-1', 'Laboratory', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(981, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 59, 'C103', 'INTE 403', 'Systems Administration and Maintenance', 'BSIT 4-2', 'Laboratory', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(982, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 59, 'C103', 'INTE 403', 'Systems Administration and Maintenance', 'BSIT 4-2', 'Lecture', NULL, 6, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(983, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 111, 'C208', 'INTE 401', 'Information Assurance and Security 2', 'BSIT 4-1', 'Lecture', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(984, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 111, 'C208', 'INTE 403', 'Systems Administration and Maintenance', 'BSIT 4-1', 'Lecture', NULL, 1, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(985, 2, 'FA0002BN2026', 'Gracel Eliza Ching Greta', 'gracelelizachinggreta', 'Part-Time', NULL, 111, 'C208', 'CMPE 308', 'CPE Laws and Professional Practice', 'DCPET 3-2', 'Lecture', NULL, 4, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(986, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 16, 'A305', 'MATH 101', 'Calculus 1', 'BSCPE 1-3', 'Lecture', NULL, 4, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(987, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 36, 'B304', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSIE 1-2', 'Lecture', NULL, 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(988, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 36, 'B304', 'ENSC 018', 'Engineering Mechanics', 'BSIE 2-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(989, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 37, 'B305', 'ENSC 018', 'Engineering Mechanics', 'BSIE 2-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(990, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 37, 'B305', 'MEEN 316', 'Thermodynamics', 'BSIE 3-1', 'Lecture', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(991, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 38, 'B401', 'MEEN 316', 'Thermodynamics', 'BSIE 3-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(992, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 60, 'C105', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSPSY 1-1', 'Lecture', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(993, 2, 'FA0004BN2016', 'Hannah Ledda B. Ferrer', 'hannahleddabferrer', 'Part-Time', NULL, 88, 'C308', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'DIT 1-2', 'Lecture', NULL, 1, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(994, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 11, 'A205', 'HRMA 101', 'Service Culture', 'BSBA-HRM 1-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(995, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 14, 'A303', 'HRMA 101', 'Service Culture', 'BSBA-HRM 1-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(996, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 106, 'A405', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSEDEN 1-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(997, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 107, 'B403', 'PSYC 303', 'Strategic Human Resource Development', 'BSPSY 3-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(998, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 110, 'B404', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSIT 2-1', 'Lecture', NULL, 4, '18:00:00', '21:00:00', 0, NULL, '2026-11-12 07:45:48'),
(999, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 108, 'C206', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSPSY 1-2', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1000, 2, 'FA0014BN2022', 'Hardly D. Abellano', 'hardlydabellano', 'Part-Time', NULL, 108, 'C206', 'PSYC 303', 'Strategic Human Resource Development', 'BSPSY 3-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1001, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 15, 'A304', 'MATH 101', 'Calculus 1', 'BSCPE 1-1', 'Lecture', NULL, 5, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1002, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 16, 'A305', 'MATH 101', 'Calculus 1', 'BSCPE 1-2', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1003, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 30, 'B203', 'MATH 101', 'Calculus 1', 'BSIE 1-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1004, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 36, 'B304', 'MATH 101', 'Calculus 1', 'BSIE 1-2', 'Lecture', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1005, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 38, 'B401', 'ELEN 015', 'Elementary Electrical Engineering', 'BSIE 4-1', 'Lecture', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1006, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 39, 'B402', 'ELEN 015', 'Elementary Electrical Engineering', 'BSIE 4-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1007, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 76, 'C210', 'MATH 101', 'Calculus 1', 'DCPET 1-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1008, 2, 'FA0003ST2012', 'Herdino Cañeja', 'herdinocaeja', 'Part-Time', NULL, 77, 'C212', 'MATH 101', 'Calculus 1', 'DCPET 1-2', 'Lecture', NULL, 5, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1009, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 11, 'A205', 'CMPE 402', 'Computer Project Management', 'BSCPE 4-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1010, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 16, 'A305', 'CMPE 101', 'Computer Engineering as a Discipline', 'BSCPE 1-2', 'Lecture', NULL, 6, '07:00:00', '08:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1011, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 59, 'C103', 'INTE 202', 'Integrative Programming and Technologies 1', 'DIT 2-2', 'Lecture', NULL, 6, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1012, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 111, 'C208', 'ITEC 301', 'Advance Programming', 'DIT 3-1', 'Laboratory', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1013, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 88, 'C308', 'INTE 202', 'Integrative Programming and Technologies 1', 'DIT 2-1', 'Laboratory', NULL, 2, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1014, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 88, 'C308', 'INTE 202', 'Integrative Programming and Technologies 1', 'DIT 2-1', 'Lecture', NULL, 3, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1015, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 89, 'C309', 'INTE 202', 'Integrative Programming and Technologies 1', 'DIT 2-2', 'Laboratory', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1016, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 89, 'C309', 'ITEC 301', 'Advance Programming', 'DIT 3-1', 'Lecture', NULL, 6, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1017, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 90, 'C310', 'ITEC 301', 'Advance Programming', 'DIT 3-2', 'Laboratory', NULL, 4, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1018, 2, 'FA0002BN2016', 'Indaleen P. Quinsayas', 'indaleenpquinsayas', 'Part-Time', 'FA0002BN2016', 90, 'C310', 'ITEC 301', 'Advance Programming', 'DIT 3-2', 'Lecture', NULL, 3, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1019, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 37, 'B305', 'PSYC 104', 'Psychological Statistics', 'BSPSY 1-1', 'Laboratory', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1020, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 60, 'C105', 'PSYC 104', 'Psychological Statistics', 'BSPSY 1-1', 'Laboratory', NULL, 6, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1021, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 60, 'C105', 'PSYC 104', 'Psychological Statistics', 'BSPSY 1-1', 'Lecture', NULL, 2, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1022, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 60, 'C105', 'PSYC 104', 'Psychological Statistics', 'BSPSY 1-2', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1023, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 60, 'C105', 'PSYC 104', 'Psychological Statistics', 'BSPSY 1-2', 'Laboratory', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1024, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 60, 'C105', 'PSYC 104', 'Psychological Statistics', 'BSPSY 1-2', 'Lecture', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1025, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 108, 'C206', 'PSYC 302', 'Filipino Psychology/Sikolohiyang Pilipino', 'BSPSY 3-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1026, 2, 'FA0008TG2012', 'Israel G. Ortega', 'israelgortega', 'Part-Time', NULL, 111, 'C208', 'PSYC 401', 'Research in Psychology 2', 'BSPSY 4-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1027, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 13, 'A302', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSEDEN 1-2', 'Lecture', NULL, 5, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1028, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 14, 'A303', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSBA-HRM 1-1', 'Lecture', NULL, 4, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1029, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 15, 'A304', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSCPE 1-1', 'Lecture', NULL, 6, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1030, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 16, 'A305', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSCPE 1-3', 'Lecture', NULL, 2, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1031, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 105, 'A404', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSEDEN 1-1', 'Lecture', NULL, 3, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1032, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 31, 'B204', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSEDSS 1-1', 'Lecture', NULL, 6, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1033, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 32, 'B205', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSEDSS 2-1', 'Lecture', NULL, 6, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1034, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 41, 'B405', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSPSY 1-1', 'Lecture', NULL, 5, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1035, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 76, 'C210', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'DCPET 1-1', 'Lecture', NULL, 1, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1036, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 87, 'C307', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'DIT 1-1', 'Lecture', NULL, 2, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1037, 2, 'FA0004BN2026', 'Jan Martin Resureccion', 'janmartinresureccion', 'Part-Time', NULL, 88, 'C308', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'DIT 1-2', 'Lecture', NULL, 4, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1038, 2, 'FA0002BN2023', 'Jayson M. Capistrano', 'jaysonmcapistrano', 'Part-Time', NULL, 12, 'A301', 'BUMA 011', 'Operations Management (TQM)', 'BSBA-HRM 2-2', 'Lecture', NULL, 5, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1039, 2, 'FA0002BN2023', 'Jayson M. Capistrano', 'jaysonmcapistrano', 'Part-Time', NULL, 39, 'B402', 'ACCO 014', 'Principles of Accounting', 'BSIT 1-1', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1040, 2, NULL, 'Jayvee John Louie de Guzman', 'jayveejohnlouiedeguzman', 'Part-Time', NULL, 13, 'A302', 'BUMA 015', 'Business Research', 'BSBA-HRM 3-1', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1041, 2, NULL, 'Jayvee John Louie de Guzman', 'jayveejohnlouiedeguzman', 'Part-Time', NULL, 13, 'A302', 'BUMA 015', 'Business Research', 'BSBA-HRM 3-2', 'Lecture', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1042, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 7, 'A201', 'ELED 116', 'Content and Pedagogy for the Mother-Tongue', 'BEED 1-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1043, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 7, 'A201', 'ELED 116', 'Content and Pedagogy for the Mother-Tongue', 'BEED 1-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1044, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 9, 'A203', 'ELED 317', 'Good Manners and Right Conduct (Edukasyon sa Pagpapakatao)', 'BEED 3-2', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1045, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 30, 'B203', 'EDUC 022', 'Field Study 1: Observations of Teaching-Learning in Actual School Environment', 'BSEDEN 4-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1046, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 30, 'B203', 'EDUC 023', 'Field Study 2: Participation and Teaching Assistanship', 'BSEDEN 4-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1047, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 34, 'B302', 'EDUC 022', 'Field Study 1: Observations of Teaching-Learning in Actual School Environment', 'BSEDSS 4-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1048, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 34, 'B302', 'EDUC 023', 'Field Study 2: Participation and Teaching Assistanship', 'BSEDSS 4-1', 'Lecture', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1049, 2, 'FA0003BN2018', 'Jean J. Tolentino', 'jeanjtolentino', 'Part-Time', NULL, 87, 'C307', 'EDUC 023', 'Field Study 2: Participation and Teaching Assistanship', 'BEED 4-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1050, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 7, 'A201', 'ELED 105', 'Pagtuturo ng Filipino sa Elementarya (I) - Estruktura at Gamit ng Wikang Filipino', 'BEED 1-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1051, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 7, 'A201', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BEED 1-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1052, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 7, 'A201', 'GEED 035', 'Panitikang Filipino', 'BEED 1-2', 'Lecture', NULL, 3, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1053, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 11, 'A205', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSBA-HRM 1-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1054, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 13, 'A302', 'ELED 105', 'Pagtuturo ng Filipino sa Elementarya (I) - Estruktura at Gamit ng Wikang Filipino', 'BEED 1-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1055, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 30, 'B203', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSEDSS 1-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1056, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 32, 'B205', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSEDSS 1-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1057, 2, 'FA0003BN2026', 'Jexter Magsino', 'jextermagsino', 'Part-Time', NULL, 112, 'C107', 'GEED 002', 'Readings in Philippine History/Mga Babasahin Hinggil sa Kasaysayan ng Pilipinas', 'BSIE 3-1', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1058, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 110, 'B404', 'COMP 007', 'Operating Systems', 'BSIT 2-1', 'Laboratory', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1059, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 110, 'B404', 'COMP 007', 'Operating Systems', 'BSIT 2-1', 'Lecture', NULL, 4, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1060, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 41, 'B405', 'COMP 007', 'Operating Systems', 'BSIT 2-2', 'Laboratory', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1061, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 41, 'B405', 'COMP 007', 'Operating Systems', 'BSIT 2-2', 'Lecture', NULL, 6, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1062, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 41, 'B405', 'COMP 007', 'Operating Systems', 'BSIT 2-3', 'Laboratory', NULL, 6, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1063, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 41, 'B405', 'COMP 007', 'Operating Systems', 'BSIT 2-3', 'Lecture', NULL, 1, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1064, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 87, 'C307', 'CMPE 311', 'Microprocessors', 'DCPET 3-2', 'Laboratory', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1065, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 87, 'C307', 'CMPE 311', 'Microprocessors', 'DCPET 3-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1066, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 87, 'C307', 'COMP 002', 'Computer Programming 1', 'DIT 1-1', 'Laboratory', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1067, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 87, 'C307', 'COMP 002', 'Computer Programming 1', 'DIT 1-1', 'Lecture', NULL, 6, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1068, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 88, 'C308', 'COMP 002', 'Computer Programming 1', 'DIT 1-2', 'Laboratory', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1069, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 88, 'C308', 'COMP 002', 'Computer Programming 1', 'DIT 1-2', 'Lecture', NULL, 5, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1070, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 88, 'C308', 'COMP 007', 'Operating Systems', 'DIT 2-1', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1071, 2, 'FA0009BN2022', 'Jhun Jhun B. Maravilla', 'jhunjhunbmaravilla', 'Permanent', NULL, 88, 'C308', 'COMP 007', 'Operating Systems', 'DIT 2-1', 'Lecture', NULL, 1, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1072, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 7, 'A201', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-2', 'Laboratory', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1073, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 15, 'A304', 'CMPE 101', 'Computer Engineering as a Discipline', 'BSCPE 1-1', 'Lecture', NULL, 2, '07:00:00', '08:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1074, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 15, 'A304', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-1', 'Laboratory', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1075, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 15, 'A304', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-1', 'Laboratory', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1076, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 16, 'A305', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-2', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1077, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 101, 'A403', 'ELEC CPE-E3', 'CPE Elective 3', 'BSCPE 4-1', 'Laboratory', NULL, 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1078, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 101, 'A403', 'ELEC CPE-E3', 'CPE Elective 3', 'BSCPE 4-1', 'Lecture', NULL, 6, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1079, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 105, 'A404', 'ELEC CPE-E3', 'CPE Elective 3', 'BSCPE 4-2', 'Lecture', NULL, 3, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1080, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 106, 'A405', 'ELEC CPE-E3', 'CPE Elective 3', 'BSCPE 4-2', 'Laboratory', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1081, 2, 'FA0015BN2023', 'Jobert D. Cadiz', 'jobertdcadiz', 'Part-Time', NULL, 108, 'C206', 'CMPE 101', 'Computer Engineering as a Discipline', 'DCPET 2-2', 'Lecture', NULL, 1, '11:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1082, 2, NULL, 'John O. Conanan', 'johnoconanan', 'Part-Time', NULL, 10, 'A204', 'HRMA 404', 'Labor Relations and Negotiations', 'BSBA-HRM 4-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1083, 2, NULL, 'John O. Conanan', 'johnoconanan', 'Part-Time', NULL, 12, 'A301', 'BUMA 011', 'Operations Management (TQM)', 'BSBA-HRM 2-1', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1084, 2, NULL, 'John O. Conanan', 'johnoconanan', 'Part-Time', NULL, 13, 'A302', 'BUMA 018', 'Fundamentals of Business Analytics', 'BSBA-HRM 3-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1085, 2, NULL, 'John O. Conanan', 'johnoconanan', 'Part-Time', NULL, 16, 'A305', 'BUMA 018', 'Fundamentals of Business Analytics', 'BSBA-HRM 3-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1086, 2, NULL, 'John O. Conanan', 'johnoconanan', 'Part-Time', NULL, 100, 'A402', 'HRMA 404', 'Labor Relations and Negotiations', 'BSBA-HRM 4-2', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1087, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 29, 'B202', 'PSYC 304', 'Abnormal Psychology', 'BSPSY 3-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1088, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 60, 'C105', 'PSYC 101', 'Introduction to Psychology', 'BSPSY 1-1', 'Lecture', NULL, 5, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1089, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 60, 'C105', 'PSYC 101', 'Introduction to Psychology', 'BSPSY 1-2', 'Lecture', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1090, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 108, 'C206', 'PSYC 301', 'Social Psychology', 'BSPSY 3-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1091, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 108, 'C206', 'PSYC 301', 'Social Psychology', 'BSPSY 3-2', 'Lecture', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1092, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 108, 'C206', 'PSYC 304', 'Abnormal Psychology', 'BSPSY 3-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1093, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 108, 'C206', 'ELEC PSYC-E2', 'Psychology Elective 2', 'BSPSY 4-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1094, 2, 'FA0011BN2023', 'John Rafael D. Risos', 'johnrafaeldrisos', 'Part-Time', NULL, 76, 'C210', 'ELEC PSYC-E2', 'Psychology Elective 2', 'BSPSY 4-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1095, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 15, 'A304', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSCPE 1-1', 'Lecture', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1096, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 114, 'A401', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSCPE 1-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1097, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 35, 'B303', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSIE 1-1', 'Lecture', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1098, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 35, 'B303', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSIT 1-2', 'Lecture', NULL, 1, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1099, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 39, 'B402', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSIT 1-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1100, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 60, 'C105', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSPSY 1-2', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1101, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 87, 'C307', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSBA-HRM 3-2', 'Lecture', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1102, 2, 'FA0011BN2021', 'John Robby O. Robiños', 'johnrobbyorobios', 'Part-Time', NULL, 87, 'C307', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'DIT 1-1', 'Lecture', NULL, 6, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1103, 2, 'FA0012BN2022', 'Jose Cardama Delgado', 'josecardamadelgado', 'Part-Time', NULL, 8, 'A202', 'ELED 207', 'Teaching Math in the Primary Grades', 'BEED 2-1', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1104, 2, 'FA0012BN2022', 'Jose Cardama Delgado', 'josecardamadelgado', 'Part-Time', NULL, 30, 'B203', 'EDUC 022', 'Field Study 1: Observations of Teaching-Learning in Actual School Environment', 'BSEDEN 4-2', 'Lecture', NULL, 1, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1105, 2, 'FA0012BN2022', 'Jose Cardama Delgado', 'josecardamadelgado', 'Part-Time', NULL, 35, 'B303', 'EDUC 023', 'Field Study 2: Participation and Teaching Assistanship', 'BEED 4-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1106, 2, 'FA0012BN2022', 'Jose Cardama Delgado', 'josecardamadelgado', 'Part-Time', NULL, 36, 'B304', 'SEEN 424', 'Creative Writing', 'BSEDEN 4-1', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1107, 2, 'FA0012BN2022', 'Jose Cardama Delgado', 'josecardamadelgado', 'Part-Time', NULL, 37, 'B305', 'SEEN 322', 'Technology for Teaching and Learning 2 (Technology in Language Education)', 'BSEDEN 3-2', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1108, 2, 'FA0012BN2022', 'Jose Cardama Delgado', 'josecardamadelgado', 'Part-Time', NULL, 38, 'B401', 'SEEN 424', 'Creative Writing', 'BSEDEN 4-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1109, 2, 'FA0021MN2009', 'Josefina B. Macarubbo', 'josefinabmacarubbo', 'Part-Time', NULL, 11, 'A205', 'BUMA 013', 'Good Governance and Social Responsibility', 'BSBA-HRM 1-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1110, 2, 'FA0021MN2009', 'Josefina B. Macarubbo', 'josefinabmacarubbo', 'Part-Time', NULL, 11, 'A205', 'BUMA 013', 'Good Governance and Social Responsibility', 'BSBA-HRM 1-2', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1111, 2, 'FA0021MN2009', 'Josefina B. Macarubbo', 'josefinabmacarubbo', 'Part-Time', NULL, 32, 'B205', 'SOST 207', 'Microeconomics', 'BSEDSS 2-1', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1112, 2, 'FA0021MN2009', 'Josefina B. Macarubbo', 'josefinabmacarubbo', 'Part-Time', NULL, 36, 'B304', 'ECON 011', 'Principles of Economics', 'BSIE 2-1', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1113, 2, 'FA0021MN2009', 'Josefina B. Macarubbo', 'josefinabmacarubbo', 'Part-Time', NULL, 37, 'B305', 'ECON 011', 'Principles of Economics', 'BSIE 2-2', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1114, 2, 'FA0021MN2009', 'Josefina B. Macarubbo', 'josefinabmacarubbo', 'Part-Time', NULL, 107, 'B403', 'SOST 207', 'Microeconomics', 'BSEDSS 2-2', 'Lecture', NULL, 6, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1115, 2, 'FA0783MN2008', 'Juan C. Birion', 'juancbirion', 'Part-Time', NULL, 11, 'A205', 'PSYC 202', 'Cognitive Psychology', 'BSPSY 2-1', 'Lecture', NULL, 4, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1116, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 114, 'A401', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-2', 'Laboratory', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1117, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 114, 'A401', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'BSCPE 2-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1118, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 77, 'C212', 'ECEN 011', 'Fundamentals of Electronic Circuits', 'DCPET 2-1', 'Laboratory', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1119, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 77, 'C212', 'ECEN 011', 'Fundamentals of Electronic Circuits', 'DCPET 2-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1120, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 113, 'C306', 'ECEN 011', 'Fundamentals of Electronic Circuits', 'DCPET 2-2', 'Laboratory', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1121, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 113, 'C306', 'ECEN 011', 'Fundamentals of Electronic Circuits', 'DCPET 2-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1122, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 113, 'C306', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'DCPET 2-2', 'Laboratory', NULL, 5, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1123, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 113, 'C306', 'ELEN 012', 'Fundamentals of Electrical Circuits', 'DCPET 2-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1124, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 113, 'C306', 'CPET 301', 'CpET Project Development 1', 'DCPET 3-1', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1125, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 113, 'C306', 'CPET 301', 'CpET Project Development 1', 'DCPET 3-1', 'Laboratory', NULL, 6, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1126, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 87, 'C307', 'CPET 301', 'CpET Project Development 1', 'DCPET 3-2', 'Laboratory', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1127, 2, 'FA0002BN2020', 'Kris Rommel R. Ramos', 'krisrommelrramos', 'Permanent', NULL, 87, 'C307', 'CPET 301', 'CpET Project Development 1', 'DCPET 3-2', 'Laboratory', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1128, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 16, 'A305', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-3', 'Laboratory', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1129, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 16, 'A305', 'CMPE 102', 'Programming Logic and Design', 'BSCPE 1-3', 'Laboratory', NULL, 5, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1130, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 100, 'A402', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-1', 'Laboratory', NULL, 3, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1131, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 100, 'A402', 'CMPE 304', 'Logic Circuits and Design', 'BSCPE 3-1', 'Lecture', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1132, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 111, 'C208', 'CMPE 102', 'Programming Logic and Design', 'DCPET 1-2', 'Laboratory', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1133, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 76, 'C210', 'CMPE 102', 'Programming Logic and Design', 'DCPET 1-1', 'Laboratory', NULL, 4, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1134, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 76, 'C210', 'CMPE 102', 'Programming Logic and Design', 'DCPET 1-1', 'Laboratory', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1135, 2, 'FA0007BN2026', 'Kurt Kurt Leinard D. Balbuena', 'kurtkurtleinarddbalbuena', 'Part-Time', NULL, 77, 'C212', 'CMPE 102', 'Programming Logic and Design', 'DCPET 1-2', 'Laboratory', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1136, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 106, 'A405', 'GEED 017', 'Philippine Indigenous Communities', 'BSEDEN 1-2', 'Lecture', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1137, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 29, 'B202', 'SOST 205', 'Geography 2: Human Geography', 'BSEDSS 2-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1138, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 31, 'B204', 'ELEC SS-GE1', 'Gen. Ed. Elective 1', 'BSEDSS 1-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48');
INSERT INTO `tbl_class_schedule` (`id`, `import_id`, `faculty_id`, `faculty_name`, `name_key`, `employment`, `account_faculty_id`, `room_id`, `room_code`, `subject_code`, `subject_name`, `section`, `component`, `group_label`, `day_of_week`, `start_time`, `end_time`, `is_tba`, `term_label`, `created_at`) VALUES
(1139, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 32, 'B205', 'SOST 209', 'World History 1', 'BSEDSS 2-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1140, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 32, 'B205', 'SOST 205', 'Geography 2: Human Geography', 'BSEDSS 2-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1141, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 32, 'B205', 'SOST 209', 'World History 1', 'BSEDSS 2-2', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1142, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 33, 'B301', 'SOST 318', 'Teaching Approaches in Secondary School - Social Studies', 'BSEDSS 3-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1143, 2, 'FA0001BN2024', 'Lawrence Lucis Bucayu', 'lawrencelucisbucayu', 'Part-Time', NULL, 33, 'B301', 'SOST 318', 'Teaching Approaches in Secondary School - Social Studies', 'BSEDSS 3-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1144, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 13, 'A302', 'CMPE-PC1', 'CPE Professional Course 2', 'DCPET 2-1', 'Lecture', NULL, 5, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1145, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 105, 'A404', 'CMPE 404', 'CPE Practice and Design 1', 'BSCPE 4-2', 'Laboratory', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1146, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 105, 'A404', 'CMPE 404', 'CPE Practice and Design 1', 'BSCPE 4-2', 'Lecture', NULL, 4, '07:00:00', '08:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1147, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 59, 'C103', 'CMPE-PC1', 'CPE Professional Course 2', 'DCPET 2-2', 'Laboratory', NULL, 4, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1148, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 108, 'C206', 'CMPE-PC3', 'CPE Professional Course 3', 'DCPET 3-1', 'Laboratory', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1149, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 76, 'C210', 'CPET 102', 'Web Technology and Programming', 'DCPET 1-1', 'Laboratory', NULL, 5, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1150, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 76, 'C210', 'CPET 102', 'Web Technology and Programming', 'DCPET 1-1', 'Lecture', NULL, 2, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1151, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 77, 'C212', 'CMPE-PC1', 'CPE Professional Course 2', 'DCPET 2-1', 'Laboratory', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1152, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 113, 'C306', 'CMPE-PC1', 'CPE Professional Course 2', 'DCPET 2-2', 'Lecture', NULL, 6, '15:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1153, 2, 'FA0010BN2025', 'Lois Eunice Jemima Lapira Lopez', 'loiseunicejemimalapiralopez', 'Part-Time', NULL, 113, 'C306', 'CMPE-PC3', 'CPE Professional Course 3', 'DCPET 3-1', 'Lecture', NULL, 6, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1154, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 15, 'A304', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSEDEN 1-1', 'Lecture', NULL, 2, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1155, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 114, 'A401', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSCPE 2-2', 'Lecture', NULL, 1, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1156, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 34, 'B302', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSIE 1-2', 'Lecture', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1157, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 107, 'B403', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSIT 1-2', 'Lecture', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1158, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 107, 'B403', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSIT 1-3', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1159, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 77, 'C212', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'DCPET 2-1', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1160, 2, NULL, 'Luke Garcia', 'lukegarcia', 'Part-Time', NULL, 113, 'C306', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'DCPET 2-2', 'Lecture', NULL, 6, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1161, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 8, 'A202', 'ELEC BEED-GEE2', 'Gen. Ed. Elective 2', 'BEED 2-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1162, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 12, 'A301', 'ELEC HRM- E1', 'Elective 1', 'BSBA-HRM 2-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1163, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 12, 'A301', 'ELEC HRM- E1', 'Elective 1', 'BSBA-HRM 2-2', 'Lecture', NULL, 2, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1164, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 31, 'B204', 'ELEC SS-GE1', 'Gen. Ed. Elective 1', 'BSEDSS 1-1', 'Lecture', NULL, 4, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1165, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 107, 'B403', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'BSIT 1-2', 'Lecture', NULL, 5, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1166, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 107, 'B403', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'BSIT 1-3', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1167, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 59, 'C103', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'DIT 1-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1168, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 60, 'C105', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'BSPSY 1-1', 'Lecture', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1169, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 60, 'C105', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'BSPSY 1-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1170, 2, 'FA0008MN2009', 'Ma. Gemalyn S. Austria', 'magemalynsaustria', 'Permanent', NULL, 87, 'C307', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'DIT 1-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1171, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 12, 'A301', 'BUMA 014', 'International Business and Trade', 'BSBA-HRM 2-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1172, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 12, 'A301', 'BUMA 014', 'International Business and Trade', 'BSBA-HRM 2-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1173, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 13, 'A302', 'ELEC HRM-E2', 'Elective 2', 'BSBA-HRM 3-1', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1174, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 13, 'A302', 'ELEC HRM-E2', 'Elective 2', 'BSBA-HRM 3-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1175, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 14, 'A303', 'ELEC HRM-34', 'Elective 4', 'BSBA-HRM 4-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1176, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 14, 'A303', 'HRMA 403', 'Special Topics in Human Resource Management', 'BSBA-HRM 4-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1177, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 14, 'A303', 'HRMA 405', 'Introduction to Knowledge Management', 'BSBA-HRM 4-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1178, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 15, 'A304', 'ELEC HRM-34', 'Elective 4', 'BSBA-HRM 4-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1179, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 15, 'A304', 'HRMA 403', 'Special Topics in Human Resource Management', 'BSBA-HRM 4-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1180, 2, 'FA0002BN2012', 'Manolo David B. Rivera', 'manolodavidbrivera', 'Permanent', NULL, 15, 'A304', 'HRMA 405', 'Introduction to Knowledge Management', 'BSBA-HRM 4-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1181, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 11, 'A205', 'NSTP 001', 'National Service Training Program 1', 'BSBA-HRM 1-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1182, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 11, 'A205', 'NSTP 001', 'National Service Training Program 1', 'BSBA-HRM 1-2', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1183, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 15, 'A304', 'NSTP 001', 'National Service Training Program 1', 'BSCPE 1-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1184, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 16, 'A305', 'NSTP 001', 'National Service Training Program 1', 'BSCPE 1-2', 'Lecture', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1185, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 16, 'A305', 'NSTP 001', 'National Service Training Program 1', 'BSCPE 1-3', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1186, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 29, 'B202', 'NSTP 001', 'National Service Training Program 1', 'BSPSY 1-2', 'Lecture', NULL, 5, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1187, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 32, 'B205', 'NSTP 001', 'National Service Training Program 1', 'BSPSY 1-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1188, 2, NULL, 'Marco M. Badion', 'marcombadion', 'Part-Time', NULL, 35, 'B303', 'NSTP 001', 'National Service Training Program 1', 'BSIE 1-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1189, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 110, 'B404', 'ELEC IT-FE1', 'BSIT Free Elective 1', 'BSIT 2-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1190, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 41, 'B405', 'ELEC IT-FE1', 'BSIT Free Elective 1', 'BSIT 2-3', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1191, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 102, 'C101', 'COMP 017', 'Multimedia', 'BSIT 3-1', 'Laboratory', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1192, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 102, 'C101', 'COMP 017', 'Multimedia', 'BSIT 3-1', 'Lecture', NULL, 1, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1193, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 108, 'C206', 'INTE 201', 'Programming 3 (Structured Programming)', 'DIT 2-2', 'Lecture', NULL, 1, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1194, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 88, 'C308', 'ITEC 102', 'Basic Computer Hardware Servicing', 'DIT 1-2', 'Laboratory', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1195, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 88, 'C308', 'ITEC 102', 'Basic Computer Hardware Servicing', 'DIT 1-2', 'Lecture', NULL, 4, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1196, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 88, 'C308', 'INTE 201', 'Programming 3 (Structured Programming)', 'DIT 2-1', 'Laboratory', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1197, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 88, 'C308', 'INTE 201', 'Programming 3 (Structured Programming)', 'DIT 2-1', 'Lecture', NULL, 1, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1198, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 89, 'C309', 'COMP 007', 'Operating Systems', 'DIT 2-2', 'Laboratory', NULL, 2, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1199, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 89, 'C309', 'COMP 007', 'Operating Systems', 'DIT 2-2', 'Lecture', NULL, 5, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1200, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 89, 'C309', 'INTE 201', 'Programming 3 (Structured Programming)', 'DIT 2-2', 'Laboratory', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1201, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 90, 'C310', 'COMP 027', 'Mobile Application Development (SMP PLUS)', 'DIT 3-2', 'Laboratory', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1202, 2, 'FA0004TG2009', 'Margarita T. Sevilla', 'margaritatsevilla', 'Permanent', NULL, 90, 'C310', 'COMP 027', 'Mobile Application Development (SMP PLUS)', 'DIT 3-2', 'Lecture', NULL, 1, '09:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1203, 2, 'FA0003BN2022', 'Marichu L. Camposano', 'marichulcamposano', 'Part-Time', NULL, 114, 'A401', 'BUMA 012', 'Strategic Management', 'BSBA-HRM 3-2', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1204, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 100, 'A402', 'ENGL 013', 'Technical Report Writing', 'BSCPE 3-1', 'Lecture', NULL, 2, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1205, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 101, 'A403', 'ENGL 013', 'Technical Report Writing', 'BSCPE 3-2', 'Lecture', NULL, 4, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1206, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 109, 'B201', 'SEEN 208', 'Teaching and Assessment of the Macro Skills', 'BSEDEN 2-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1207, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 109, 'B201', 'SEEN 209', 'Teaching and Assessment of Grammar', 'BSEDEN 2-1', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1208, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 109, 'B201', 'SEEN 209', 'Teaching and Assessment of Grammar', 'BSEDEN 2-2', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1209, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 29, 'B202', 'SEEN 320', 'Technical Writing', 'BSEDEN 3-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1210, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 34, 'B302', 'SEEN 208', 'Teaching and Assessment of the Macro Skills', 'BSEDEN 2-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1211, 2, 'FA0010BN2023', 'Mark Anthony P. Lagumen', 'markanthonyplagumen', 'Part-Time', NULL, 107, 'B403', 'SEEN 320', 'Technical Writing', 'BSEDEN 3-1', 'Lecture', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1212, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 114, 'A401', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-2', 'Laboratory', NULL, 1, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1213, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 105, 'A404', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-2', 'Laboratory', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1214, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 105, 'A404', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-2', 'Laboratory', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1215, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 110, 'B404', 'COMP 008', 'Data Communications and Networking', 'BSIT 2-1', 'Laboratory', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1216, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 110, 'B404', 'COMP 008', 'Data Communications and Networking', 'BSIT 2-1', 'Lecture', NULL, 5, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1217, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 41, 'B405', 'COMP 008', 'Data Communications and Networking', 'BSIT 2-2', 'Laboratory', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1218, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 41, 'B405', 'COMP 008', 'Data Communications and Networking', 'BSIT 2-2', 'Lecture', NULL, 3, '15:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1219, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 41, 'B405', 'COMP 008', 'Data Communications and Networking', 'BSIT 2-3', 'Laboratory', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1220, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 41, 'B405', 'COMP 008', 'Data Communications and Networking', 'BSIT 2-3', 'Lecture', NULL, 5, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1221, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 113, 'C306', 'CPET 302', 'Database Management System 2', 'DCPET 3-1', 'Laboratory', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1222, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 113, 'C306', 'CPET 302', 'Database Management System 2', 'DCPET 3-1', 'Laboratory', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1223, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 103, 'CL207', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-2', 'Laboratory', 'Grp B', 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1224, 2, 'FA0008BN2025', 'Marte D. Nipas', 'martednipas', 'Permanent', NULL, 104, 'CL209', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-2', 'Laboratory', 'Grp A', 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1225, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 101, 'A403', 'CMPE 403', 'Computer Architecture and Organization', 'BSCPE 4-1', 'Laboratory', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1226, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 101, 'A403', 'CMPE 403', 'Computer Architecture and Organization', 'BSCPE 4-1', 'Lecture', NULL, 3, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1227, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 105, 'A404', 'CMPE 403', 'Computer Architecture and Organization', 'BSCPE 4-1', 'Lecture', NULL, 6, '09:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1228, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 105, 'A404', 'CMPE 403', 'Computer Architecture and Organization', 'BSCPE 4-2', 'Laboratory', NULL, 2, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1229, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 105, 'A404', 'CMPE 403', 'Computer Architecture and Organization', 'BSCPE 4-2', 'Lecture', NULL, 3, '12:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1230, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 105, 'A404', 'CMPE 403', 'Computer Architecture and Organization', 'BSCPE 4-2', 'Lecture', NULL, 6, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1231, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 89, 'C309', 'COMP 025', 'Project Management', 'DIT 3-1', 'Laboratory', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1232, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 89, 'C309', 'COMP 025', 'Project Management', 'DIT 3-1', 'Lecture', NULL, 1, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1233, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 90, 'C310', 'COMP 025', 'Project Management', 'DIT 3-2', 'Laboratory', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1234, 2, NULL, 'Mary Anne Legarda', 'maryannelegarda', 'Part-Time', NULL, 90, 'C310', 'COMP 025', 'Project Management', 'DIT 3-2', 'Lecture', NULL, 1, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1235, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 11, 'A205', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSBA-HRM 1-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1236, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 16, 'A305', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSCPE 1-2', 'Lecture', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1237, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 31, 'B204', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSEDSS 1-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1238, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 39, 'B402', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSPSY 1-1', 'Lecture', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1239, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 110, 'B404', 'GEED 001', 'Understanding the Self/Pag-unawa sa Sarili', 'BSEDSS 1-1', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1240, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 112, 'C107', 'PSYC 201', 'Theories of Personality', 'BSPSY 2-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1241, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 112, 'C107', 'PSYC 201', 'Theories of Personality', 'BSPSY 2-2', 'Lecture', NULL, 1, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1242, 2, NULL, 'Mathew Ives L. Yupangco', 'mathewiveslyupangco', 'Part-Time', NULL, 111, 'C208', 'PSYC 401', 'Research in Psychology 2', 'BSPSY 4-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1243, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 8, 'A202', 'EDUC 012', 'The Teaching Profession', 'BEED 2-1', 'Lecture', NULL, 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1244, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 8, 'A202', 'EDUC 012', 'The Teaching Profession', 'BEED 2-2', 'Lecture', NULL, 6, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1245, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 11, 'A205', 'SEEN 423', 'Remedial Instruction', 'BSEDEN 4-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1246, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 30, 'B203', 'EDUC 012', 'The Teaching Profession', 'BSEDEN 2-1', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1247, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 33, 'B301', 'EDUC 012', 'The Teaching Profession', 'BSEDSS 2-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1248, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 35, 'B303', 'SOST 424', 'Basic of School Management and Administration', 'BSEDSS 4-2', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1249, 2, 'FA0054SP2012', 'Maura Umaclap', 'mauraumaclap', 'Part-Time', NULL, 90, 'C310', 'SOST 424', 'Basic of School Management and Administration', 'BSEDSS 4-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1250, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 109, 'B201', 'ELEC IT-FE1', 'BSIT Free Elective 1', 'BSIT 2-2', 'Lecture', NULL, 5, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1251, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 110, 'B404', 'INTE 201', 'Programming 3 (Structured Programming)', 'BSIT 2-1', 'Laboratory', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1252, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 110, 'B404', 'INTE 201', 'Programming 3 (Structured Programming)', 'BSIT 2-1', 'Lecture', NULL, 4, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1253, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 59, 'C103', 'COMP 023', 'Social and Professional Issues in Computing', 'BSIT 4-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1254, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 59, 'C103', 'ITEC 101', 'Keyboarding and Documents Processing with Laboratory', 'DIT 1-1', 'Laboratory', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1255, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 87, 'C307', 'ITEC 101', 'Keyboarding and Documents Processing with Laboratory', 'DIT 1-1', 'Lecture', NULL, 5, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1256, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 88, 'C308', 'ITEC 101', 'Keyboarding and Documents Processing with Laboratory', 'DIT 1-2', 'Laboratory', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1257, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 88, 'C308', 'ITEC 101', 'Keyboarding and Documents Processing with Laboratory', 'DIT 1-2', 'Lecture', NULL, 5, '09:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1258, 2, 'FA0004BN2018', 'May L. Borjal', 'maylborjal', 'Part-Time', NULL, 89, 'C309', 'COMP 023', 'Social and Professional Issues in Computing', 'DIT 2-2', 'Lecture', NULL, 2, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1259, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 34, 'B302', 'COMP 001', 'Introduction to Computing', 'BSIT 1-3', 'Lecture', NULL, 6, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1260, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 41, 'B405', 'INTE 201', 'Programming 3 (Structured Programming)', 'BSIT 2-2', 'Laboratory', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1261, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 41, 'B405', 'INTE 201', 'Programming 3 (Structured Programming)', 'BSIT 2-2', 'Lecture', NULL, 3, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1262, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 102, 'C101', 'COMP 018', 'Database Administration', 'BSIT 3-1', 'Laboratory', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1263, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 102, 'C101', 'COMP 018', 'Database Administration', 'BSIT 3-1', 'Lecture', NULL, 2, '19:00:00', '21:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1264, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 108, 'C206', 'INTE 401', 'Information Assurance and Security 2', 'BSIT 4-2', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1265, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 90, 'C310', 'INTE 351', 'Systems Analysis and Design', 'DIT 3-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1266, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 103, 'CL207', 'COMP 001', 'Introduction to Computing', 'BSIT 1-3', 'Laboratory', 'Grp B', 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1267, 2, 'FA0015SP2021', 'Mc Joben R. Reyes', 'mcjobenrreyes', 'Part-Time', NULL, 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'BSIT 1-3', 'Laboratory', 'Grp A', 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1268, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 9, 'A203', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSCPE 2-1', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1269, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 12, 'A301', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSBA-HRM 2-1', 'Lecture', NULL, 1, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1270, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 100, 'A402', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSCPE 2-3', 'Lecture', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1271, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 106, 'A405', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSEDEN 1-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1272, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 35, 'B303', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSIE 1-1', 'Lecture', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1273, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 110, 'B404', 'GEED 033', 'Pagsasalin sa Kontekstong Filipino', 'BSPSY 2-1', 'Lecture', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1274, 2, 'FA0038MN2017', 'McLougin Mislan', 'mclouginmislan', 'Part-Time', NULL, 88, 'C308', 'GEED 033', 'Pagsasalin sa Kontekstong Filipino', 'BSPSY 2-2', 'Lecture', NULL, 2, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1275, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 16, 'A305', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSCPE 1-3', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1276, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 114, 'A401', 'MATH 209', 'Differential Equations', 'BSCPE 2-2', 'Lecture', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1277, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 100, 'A402', 'MATH 209', 'Differential Equations', 'BSCPE 2-3', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1278, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 106, 'A405', 'MATH 209', 'Differential Equations', 'BSCPE 2-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1279, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 34, 'B302', 'MATH 209', 'Differential Equations', 'BSIE 2-2', 'Lecture', NULL, 5, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1280, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 36, 'B304', 'MATH 209', 'Differential Equations', 'BSIE 2-1', 'Lecture', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1281, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 107, 'B403', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSIT 1-3', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1282, 2, 'FA0004BN2025', 'Melanie Matilla Lalim', 'melaniematillalalim', 'Part-Time', NULL, 102, 'C101', 'GEED 004', 'Mathematics in the Modern World/Matematika sa Makabagong Daigdig', 'BSBA-HRM 3-1', 'Lecture', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1283, 2, 'FA0010BN2022', 'Melanny E. Rivera', 'melannyerivera', 'Part-Time', NULL, 13, 'A302', 'ACCO 013', 'Taxation (Income Taxation)', 'BSBA-HRM 3-1', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1284, 2, 'FA0010BN2022', 'Melanny E. Rivera', 'melannyerivera', 'Part-Time', NULL, 59, 'C103', 'ACCO 013', 'Taxation (Income Taxation)', 'BSBA-HRM 3-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1285, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 101, 'A403', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-1', 'Laboratory', NULL, 3, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1286, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 101, 'A403', 'CMPE 401', 'Database Management Systems', 'BSCPE 4-1', 'Laboratory', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1287, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 39, 'B402', 'COMP 002', 'Computer Programming 1', 'BSIT 1-1', 'Laboratory', NULL, 2, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1288, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 39, 'B402', 'COMP 002', 'Computer Programming 1', 'BSIT 1-1', 'Lecture', NULL, 5, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1289, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 107, 'B403', 'COMP 001', 'Introduction to Computing', 'BSIT 1-2', 'Lecture', NULL, 2, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1290, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 107, 'B403', 'COMP 002', 'Computer Programming 1', 'BSIT 1-2', 'Laboratory', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1291, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 107, 'B403', 'COMP 002', 'Computer Programming 1', 'BSIT 1-2', 'Lecture', NULL, 3, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1292, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 102, 'C101', 'COMP 015', 'Fundamentals of Research', 'BSIT 3-1', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1293, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 102, 'C101', 'COMP 015', 'Fundamentals of Research', 'BSIT 3-2', 'Lecture', NULL, 4, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1294, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 89, 'C309', 'COMP 015', 'Fundamentals of Research', 'DIT 3-1', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1295, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 90, 'C310', 'COMP 015', 'Fundamentals of Research', 'DIT 3-2', 'Lecture', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1296, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 103, 'CL207', 'COMP 001', 'Introduction to Computing', 'BSIT 1-2', 'Laboratory', 'Grp A', 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1297, 2, 'FA0005BN2016', 'Michael Anjelo O. Miguel', 'michaelanjeloomiguel', 'Permanent', 'FA0005BN2016', 104, 'CL209', 'COMP 001', 'Introduction to Computing', 'BSIT 1-2', 'Laboratory', 'Grp B', 4, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1298, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 109, 'B201', 'SEEN 213', 'Children and Adolescent Literature', 'BSEDEN 2-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1299, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 29, 'B202', 'EDUC 016', 'Assessment in Learning 1', 'BSEDEN 3-1', 'Lecture', NULL, 6, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1300, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 29, 'B202', 'SEEN 318', 'Contemporary, Popular, and Emergent Literature', 'BSEDEN 3-1', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1301, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 29, 'B202', 'SEEN 318', 'Contemporary, Popular, and Emergent Literature', 'BSEDEN 3-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1302, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 34, 'B302', 'SEEN 213', 'Children and Adolescent Literature', 'BSEDEN 2-2', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1303, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 35, 'B303', 'EDUC 023', 'Field Study 2: Participation and Teaching Assistanship', 'BSEDSS 4-2', 'Lecture', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1304, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 113, 'C306', 'ENGL 012', 'Technical Communication', 'DCPET 3-1', 'Lecture', NULL, 1, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1305, 2, NULL, 'Muhammad A. Lambas', 'muhammadalambas', 'Part-Time', NULL, 87, 'C307', 'ENGL 012', 'Technical Communication', 'DCPET 3-2', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1306, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 11, 'A205', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BEED 1-2', 'Lecture', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1307, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 13, 'A302', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BEED 1-1', 'Lecture', NULL, 2, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1308, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 109, 'B201', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSEDEN 2-1', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1309, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 110, 'B404', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSEDEN 2-2', 'Lecture', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1310, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 111, 'C208', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSIT 4-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1311, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 111, 'C208', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSIT 4-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1312, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 76, 'C210', 'CHEM 015', 'Chemistry for Engineers', 'DCPET 1-1', 'Laboratory', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1313, 2, 'FA0012BN2024', 'Nancy M. Cabaluna', 'nancymcabaluna', 'Part-Time', NULL, 76, 'C210', 'CHEM 015', 'Chemistry for Engineers', 'DCPET 1-1', 'Lecture', NULL, 1, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1314, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 13, 'A302', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSCPE 1-1', 'Lecture', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1315, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 16, 'A305', 'GEED 007', 'Science, Technology and Society/Agham, Teknolohiya, at Lipunan', 'BSCPE 1-3', 'Lecture', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1316, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 112, 'C107', 'CHEM 017', 'General Chemistry', 'BSPSY 2-1', 'Laboratory', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1317, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 112, 'C107', 'CHEM 017', 'General Chemistry', 'BSPSY 2-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1318, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 112, 'C107', 'CHEM 017', 'General Chemistry', 'BSPSY 2-2', 'Laboratory', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1319, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 112, 'C107', 'CHEM 017', 'General Chemistry', 'BSPSY 2-2', 'Laboratory', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1320, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 112, 'C107', 'CHEM 017', 'General Chemistry', 'BSPSY 2-2', 'Lecture', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1321, 2, 'FA0047SP2012', 'Nelissa B. Perey', 'nelissabperey', 'Part-Time', NULL, 111, 'C208', 'CHEM 017', 'General Chemistry', 'BSPSY 2-1', 'Laboratory', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1322, 2, 'FA0009BN2026', 'Neribeth Charm G. Agripa', 'neribethcharmgagripa', 'Part-Time', NULL, 7, 'A201', 'LAW 015', 'Business Law (Obligations and Contracts)', 'BSBA-HRM 3-2', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1323, 2, 'FA0009BN2026', 'Neribeth Charm G. Agripa', 'neribethcharmgagripa', 'Part-Time', NULL, 101, 'A403', 'LAW 015', 'Business Law (Obligations and Contracts)', 'BSBA-HRM 3-1', 'Lecture', NULL, 1, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1324, 2, 'FA0009BN2026', 'Neribeth Charm G. Agripa', 'neribethcharmgagripa', 'Part-Time', NULL, 32, 'B205', 'SOST 217', 'Law-Related Studies', 'BSEDSS 2-1', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1325, 2, 'FA0009BN2026', 'Neribeth Charm G. Agripa', 'neribethcharmgagripa', 'Part-Time', NULL, 110, 'B404', 'SOST 217', 'Law-Related Studies', 'BSEDSS 2-2', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1326, 2, 'FA0001BN2025', 'Nestleson H. Alagon', 'nestlesonhalagon', 'Part-Time', NULL, 105, 'A404', 'CWTS 001', 'Civic Welfare Training Service 1', 'BSEDEN 1-1', 'Lecture', NULL, 1, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1327, 2, 'FA0001BN2025', 'Nestleson H. Alagon', 'nestlesonhalagon', 'Part-Time', NULL, 76, 'C210', 'CWTS 001', 'Civic Welfare Training Service 1', 'DCPET 1-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1328, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 8, 'A202', 'ELED 102', 'Teaching Science in the Elementary Grades (Physics, Earth and Space Science)', 'BEED 2-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1329, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 9, 'A203', 'ELED 311', 'Teaching Music in the Elementary Grades', 'BEED 3-1', 'Lecture', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1330, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 10, 'A204', 'ELED 102', 'Teaching Science in the Elementary Grades (Physics, Earth and Space Science)', 'BEED 2-2', 'Lecture', NULL, 4, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1331, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 30, 'B203', 'SOST 311', 'Research in Social Studies', 'BSEDSS 3-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1332, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 33, 'B301', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSEDSS 2-2', 'Lecture', NULL, 6, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1333, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 33, 'B301', 'SOST 311', 'Research in Social Studies', 'BSEDSS 3-1', 'Lecture', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1334, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 41, 'B405', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSIT 2-2', 'Lecture', NULL, 2, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1335, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 88, 'C308', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'DIT 2-1', 'Lecture', NULL, 3, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1336, 2, 'FA0007BN2025', 'Nikki Crystel A. Elic', 'nikkicrystelaelic', 'Part-Time', NULL, 89, 'C309', 'ELED 311', 'Teaching Music in the Elementary Grades', 'BEED 3-2', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1337, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 7, 'A201', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BEED 1-1', 'Lecture', NULL, 5, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1338, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 7, 'A201', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BEED 1-2', 'Lecture', NULL, 3, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1339, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 8, 'A202', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BEED 2-2', 'Lecture', NULL, 3, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1340, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 12, 'A301', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSBA-HRM 2-1', 'Lecture', NULL, 1, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1341, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 12, 'A301', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSBA-HRM 2-2', 'Lecture', NULL, 5, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1342, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 114, 'A401', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSCPE 2-2', 'Lecture', NULL, 4, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1343, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 100, 'A402', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSCPE 2-3', 'Lecture', NULL, 2, '09:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1344, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 31, 'B204', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSIE 2-2', 'Lecture', NULL, 3, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1345, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 36, 'B304', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSIE 1-2', 'Lecture', NULL, 6, '09:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1346, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 36, 'B304', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSIE 2-1', 'Lecture', NULL, 4, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1347, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 107, 'B403', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSIT 1-2', 'Lecture', NULL, 5, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48');
INSERT INTO `tbl_class_schedule` (`id`, `import_id`, `faculty_id`, `faculty_name`, `name_key`, `employment`, `account_faculty_id`, `room_id`, `room_code`, `subject_code`, `subject_name`, `section`, `component`, `group_label`, `day_of_week`, `start_time`, `end_time`, `is_tba`, `term_label`, `created_at`) VALUES
(1348, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 107, 'B403', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSIT 1-3', 'Lecture', NULL, 2, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1349, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 110, 'B404', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSIT 2-1', 'Lecture', NULL, 6, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1350, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 60, 'C105', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSPSY 1-2', 'Lecture', NULL, 2, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1351, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 112, 'C107', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSPSY 2-1', 'Lecture', NULL, 6, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1352, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 112, 'C107', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSPSY 2-2', 'Lecture', NULL, 1, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1353, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 108, 'C206', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'DIT 2-2', 'Lecture', NULL, 1, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1354, 2, 'FA0001BN2011', 'Noemi Apostol', 'noemiapostol', 'Permanent', NULL, 113, 'C306', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'DCPET 2-2', 'Lecture', NULL, 4, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1355, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 13, 'A302', 'CMPE 404', 'CPE Practice and Design 1', 'BSCPE 4-1', 'Laboratory', NULL, 2, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1356, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 114, 'A401', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-1', 'Laboratory', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1357, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 101, 'A403', 'CMPE 404', 'CPE Practice and Design 1', 'BSCPE 4-1', 'Lecture', NULL, 1, '07:00:00', '08:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1358, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 77, 'C212', 'CMPE 104', 'Discrete Mathematics', 'DCPET 2-1', 'Lecture', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1359, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 113, 'C306', 'CMPE 104', 'Discrete Mathematics', 'DCPET 2-2', 'Lecture', NULL, 5, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1360, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 87, 'C307', 'CMPE-PC3', 'CPE Professional Course 3', 'DCPET 3-2', 'Laboratory', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1361, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 87, 'C307', 'CMPE-PC3', 'CPE Professional Course 3', 'DCPET 3-2', 'Lecture', NULL, 5, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1362, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 104, 'CL209', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-1', 'Laboratory', 'Grp A', 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1363, 2, 'FA0001PQ2019', 'Paul John J. Capote', 'pauljohnjcapote', 'Part-Time', NULL, 104, 'CL209', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-1', 'Laboratory', 'Grp B', 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1364, 2, 'FA0008BN2026', 'Paul Vincent A. Vierneza', 'paulvincentavierneza', 'Part-Time', NULL, 12, 'A301', 'CWTS 001', 'Civic Welfare Training Service 1', 'BEED 1-1', 'Lecture', NULL, 5, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1365, 2, 'FA0008BN2026', 'Paul Vincent A. Vierneza', 'paulvincentavierneza', 'Part-Time', NULL, 60, 'C105', 'CWTS 001', 'Civic Welfare Training Service 1', 'DIT 1-1', 'Lecture', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1366, 2, NULL, 'Rafael Lloyd Domingo', 'rafaellloyddomingo', 'Part-Time', NULL, 10, 'A204', 'GEED 035', 'Panitikang Filipino', 'BEED 1-1', 'Lecture', NULL, 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1367, 2, NULL, 'Rafael Lloyd Domingo', 'rafaellloyddomingo', 'Part-Time', NULL, 12, 'A301', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSBA-HRM 2-2', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1368, 2, NULL, 'Rafael Lloyd Domingo', 'rafaellloyddomingo', 'Part-Time', NULL, 14, 'A303', 'GEED 035', 'Panitikang Filipino', 'BSBA-HRM 3-1', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1369, 2, NULL, 'Rafael Lloyd Domingo', 'rafaellloyddomingo', 'Part-Time', NULL, 14, 'A303', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSIT 1-1', 'Lecture', NULL, 3, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1370, 2, NULL, 'Rafael Lloyd Domingo', 'rafaellloyddomingo', 'Part-Time', NULL, 106, 'A405', 'GEED 035', 'Panitikang Filipino', 'BSBA-HRM 3-2', 'Lecture', NULL, 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1371, 2, NULL, 'Rafael Lloyd Domingo', 'rafaellloyddomingo', 'Part-Time', NULL, 31, 'B204', 'GEED 032', 'Filipinolohiya at Pambansang Kaunlaran', 'BSEDSS 1-2', 'Lecture', NULL, 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1372, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 9, 'A203', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-3', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1373, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 15, 'A304', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-1', 'Lecture', NULL, 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1374, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 109, 'B201', 'CHEM 015', 'Chemistry for Engineers', 'BSIE 1-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1375, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 36, 'B304', 'CHEM 015', 'Chemistry for Engineers', 'BSIE 1-2', 'Laboratory', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1376, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 103, 'CL207', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-1', 'Laboratory', 'Grp A', 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1377, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 103, 'CL207', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-3', 'Laboratory', 'Grp A', 5, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1378, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 103, 'CL207', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-3', 'Laboratory', 'Grp B', 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1379, 2, 'FA0001BN2026', 'Rafael M. Bungualan', 'rafaelmbungualan', 'Part-Time', NULL, 104, 'CL209', 'CHEM 015', 'Chemistry for Engineers', 'BSCPE 1-1', 'Laboratory', 'Grp B', 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1380, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 9, 'A203', 'ELED 320', 'Technology for Teaching in Elementary Grades', 'BEED 3-2', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1381, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 10, 'A204', 'SEEN 101', 'Introduction to Linguistics', 'BSEDEN 1-1', 'Lecture', NULL, 3, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1382, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 13, 'A302', 'SEEN 101', 'Introduction to Linguistics', 'BSEDEN 1-2', 'Lecture', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1383, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 109, 'B201', 'SEEN 206', 'Language Learning Material Development', 'BSEDEN 2-1', 'Lecture', NULL, 2, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1384, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 109, 'B201', 'SEEN 205', 'Language Programs and Policies in Multilingual Societies', 'BSEDEN 2-2', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1385, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 109, 'B201', 'SEEN 206', 'Language Learning Material Development', 'BSEDEN 2-2', 'Lecture', NULL, 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1386, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 39, 'B402', 'GEED 005', 'Purposive Communication/Malayuning Komunikasyon', 'BSIT 1-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1387, 2, 'FA0060MN2012', 'Raiza Francesca Buenaventura Culili', 'raizafrancescabuenaventuraculili', 'Part-Time', NULL, 111, 'C208', 'SEEN 205', 'Language Programs and Policies in Multilingual Societies', 'BSEDEN 2-1', 'Lecture', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1388, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 101, 'A403', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-2', 'Lecture', NULL, 3, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1389, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 37, 'B305', 'INEN 303', 'Quality Management Systems', 'BSIE 3-1', 'Lecture', NULL, 2, '10:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1390, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 59, 'C103', 'ELEC IT-E4', 'IT Elective 4', 'BSIT 4-2', 'Lecture', NULL, 5, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1391, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 60, 'C105', 'ELEC IT-E4', 'IT Elective 4', 'BSIT 4-1', 'Lecture', NULL, 4, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1392, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 113, 'C306', 'CMPE 308', 'CPE Laws and Professional Practice', 'DCPET 3-1', 'Lecture', NULL, 3, '11:00:00', '13:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1393, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 103, 'CL207', 'ELEC IT-E4', 'IT Elective 4', 'BSIT 4-2', 'Laboratory', 'Grp A', 4, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1394, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 104, 'CL209', 'ELEC IT-E4', 'IT Elective 4', 'BSIT 4-1', 'Laboratory', 'Grp B', 1, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1395, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 104, 'CL209', 'ELEC IT-E4', 'IT Elective 4', 'BSIT 4-1', 'Laboratory', 'Grp A', 5, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1396, 2, 'FA0002TG2012', 'Reginald G. Raquiza', 'reginaldgraquiza', 'Part-Time', NULL, 104, 'CL209', 'ELEC IT-E4', 'IT Elective 4', 'BSIT 4-2', 'Laboratory', 'Grp B', 6, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1397, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 29, 'B202', 'INEN 405', 'Information Systems', 'BSIE 4-2', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1398, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 31, 'B204', 'INEN 203', 'Industrial Organization and Management', 'BSIE 2-2', 'Lecture', NULL, 6, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1399, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 35, 'B303', 'INEN 101', 'Introduction to Industrial Engineering', 'BSIE 1-2', 'Lecture', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1400, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 36, 'B304', 'INEN 203', 'Industrial Organization and Management', 'BSIE 2-1', 'Lecture', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1401, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 37, 'B305', 'INEN 201', 'Statistical Analysis for Industrial Engineering 2', 'BSIE 2-2', 'Lecture', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1402, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 38, 'B401', 'INEN 405', 'Information Systems', 'BSIE 4-1', 'Lecture', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1403, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 39, 'B402', 'INEN 407', 'Seminars, Industry Inspection and Observation', 'BSIE 4-2', 'Laboratory', NULL, 4, '15:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1404, 2, 'FA0013BN2022', 'Rochelle Hanna C. Nopueto', 'rochellehannacnopueto', 'Part-Time', NULL, 39, 'B402', 'INEN 407', 'Seminars, Industry Inspection and Observation', 'BSIE 4-2', 'Laboratory', NULL, 5, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1405, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 100, 'A402', 'ENSC 301', 'Engineering Economics', 'BSCPE 3-1', 'Lecture', NULL, 4, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1406, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 36, 'B304', 'INEN 201', 'Statistical Analysis for Industrial Engineering 2', 'BSIE 2-1', 'Lecture', NULL, 6, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1407, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 37, 'B305', 'ENSC 025', 'Environmental Science and Engineering', 'BSIE 3-1', 'Lecture', NULL, 3, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1408, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 38, 'B401', 'INEN 301', 'Operations Research 1', 'BSIE 3-2', 'Lecture', NULL, 2, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1409, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 38, 'B401', 'INEN 303', 'Quality Management Systems', 'BSIE 3-2', 'Lecture', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1410, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 38, 'B401', 'ELEC IE-E2', 'IE Elective 2', 'BSIE 4-1', 'Lecture', NULL, 1, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1411, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 38, 'B401', 'INEN 401', 'IE Capstone Project 1', 'BSIE 4-1', 'Laboratory', NULL, 2, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1412, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 39, 'B402', 'ELEC IE-E2', 'IE Elective 2', 'BSIE 4-2', 'Lecture', NULL, 1, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1413, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 76, 'C210', 'ENSC 013', 'Engineering Drawing', 'DCPET 1-1', 'Laboratory', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1414, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 76, 'C210', 'ENSC 013', 'Engineering Drawing', 'DCPET 1-1', 'Laboratory', NULL, 5, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1415, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 77, 'C212', 'ENSC 013', 'Engineering Drawing', 'DCPET 1-2', 'Laboratory', NULL, 5, '14:00:00', '17:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1416, 2, 'FA0005BN2023', 'Rod Phillip M. Corro', 'rodphillipmcorro', 'Permanent', NULL, 77, 'C212', 'ENSC 013', 'Engineering Drawing', 'DCPET 1-2', 'Laboratory', NULL, 6, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1417, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 100, 'A402', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-3', 'Laboratory', NULL, 4, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1418, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 105, 'A404', 'CMPE 201', 'Data Structures and Algorithms', 'BSCPE 2-3', 'Laboratory', NULL, 1, '07:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1419, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 36, 'B304', 'CMPE 011', 'Computer Fundamentals and Programming', 'BSIE 1-2', 'Laboratory', NULL, 3, '13:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1420, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 36, 'B304', 'CMPE 011', 'Computer Fundamentals and Programming', 'BSIE 1-2', 'Laboratory', NULL, 5, '08:00:00', '11:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1421, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 77, 'C212', 'CMPE 201', 'Data Structures and Algorithms', 'DCPET 2-1', 'Laboratory', NULL, 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1422, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 77, 'C212', 'CMPE 201', 'Data Structures and Algorithms', 'DCPET 2-1', 'Laboratory', NULL, 5, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1423, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 113, 'C306', 'CMPE 201', 'Data Structures and Algorithms', 'DCPET 2-2', 'Laboratory', NULL, 3, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1424, 2, NULL, 'Ronnel P. Virtucio', 'ronnelpvirtucio', 'Part-Time', NULL, 89, 'C309', 'CMPE 201', 'Data Structures and Algorithms', 'DCPET 2-2', 'Laboratory', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1425, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 7, 'A201', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSBA-HRM 1-2', 'Lecture', NULL, 5, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1426, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 8, 'A202', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BEED 2-1', 'Lecture', NULL, 1, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1427, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 10, 'A204', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSCPE 1-2', 'Lecture', NULL, 1, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1428, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 114, 'A401', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSCPE 2-1', 'Lecture', NULL, 3, '14:00:00', '16:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1429, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 109, 'B201', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSEDEN 2-1', 'Lecture', NULL, 5, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1430, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 109, 'B201', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSEDEN 2-2', 'Lecture', NULL, 2, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1431, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 31, 'B204', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSEDSS 1-2', 'Lecture', NULL, 3, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1432, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 35, 'B303', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSIE 1-1', 'Lecture', NULL, 4, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1433, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 37, 'B305', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'BSIT 2-3', 'Lecture', NULL, 3, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1434, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 39, 'B402', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'BSIT 1-1', 'Lecture', NULL, 4, '13:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1435, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 77, 'C212', 'PATHFIT 1', 'Physical Activity Towards Health and Fitness 1', 'DCPET 1-2', 'Lecture', NULL, 2, '08:00:00', '10:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1436, 2, 'FA0002BN2025', 'Sarah Ethel T. Jasmin', 'sarahetheltjasmin', 'Part-Time', NULL, 77, 'C212', 'PATHFIT 3', 'Physical Activity Towards Health and Fitness 3', 'DCPET 2-1', 'Lecture', NULL, 6, '17:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1437, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 102, 'C101', 'COMP 016', 'Web Development', 'BSIT 3-1', 'Lecture', NULL, 5, '10:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1438, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 102, 'C101', 'INTE 301', 'Systems Integration and Architecture 1', 'BSIT 3-1', 'Lecture', NULL, 1, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1439, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 102, 'C101', 'INTE 301', 'Systems Integration and Architecture 1', 'BSIT 3-2', 'Lecture', NULL, 4, '17:00:00', '20:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1440, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 89, 'C309', 'COMP 019', 'Applications Development and Emerging Technologies', 'DIT 3-1', 'Lecture', NULL, 3, '07:00:00', '09:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1441, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 90, 'C310', 'COMP 019', 'Applications Development and Emerging Technologies', 'DIT 3-1', 'Laboratory', NULL, 6, '09:00:00', '12:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1442, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 90, 'C310', 'COMP 019', 'Applications Development and Emerging Technologies', 'DIT 3-2', 'Laboratory', NULL, 3, '11:00:00', '14:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1443, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 90, 'C310', 'COMP 019', 'Applications Development and Emerging Technologies', 'DIT 3-2', 'Lecture', NULL, 5, '16:00:00', '18:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1444, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 103, 'CL207', 'COMP 016', 'Web Development', 'BSIT 3-1', 'Laboratory', 'Grp B', 6, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1445, 2, 'FA0011BN2026', 'Stephen Mathew C. Perez', 'stephenmathewcperez', 'Part-Time', 'FA0011BN2026', 104, 'CL209', 'COMP 016', 'Web Development', 'BSIT 3-1', 'Laboratory', 'Grp A', 2, '16:00:00', '19:00:00', 0, NULL, '2026-11-12 07:45:48'),
(1446, 2, 'FA0002BN2019', 'Widonna B. Cuenca', 'widonnabcuenca', 'Permanent', NULL, 105, 'A404', 'CWTS 001', 'Civic Welfare Training Service 1', 'BEED 1-2', 'Lecture', NULL, 5, '12:00:00', '15:00:00', 0, NULL, '2026-11-12 07:45:48');

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
(1, 'NOTSET-275A970376E5', 25, 0, 'PUPSync System', 'Item Code Used: Double A Batter', 'sandy (2023-00004bn-0) borrowed 1 unit of Double A Batter using your item code today. Return expected by 19:30. Room/Location: 308.', '2026-10-24 17:31:00'),
(2, 'FA0011BN2026', 67, 1, 'Redg Abelida', 'Immediate Return Required: Active Speakers', 'This is an official overdue-return notice from PUPSync Admin. Active Speakers was due on November 10, 2026 and is now 2 days overdue. Please return this equipment to the Admin Office immediately. Your borrowing access remains blocked until the return is recorded.', '2026-11-12 17:14:10'),
(3, 'FA0011BN2026', 66, 1, 'Redg Abelida', 'Immediate Return Required: Active Speakers', 'This is an official overdue-return notice from PUPSync Admin. Active Speakers was due on November 11, 2026 and is now 1 day overdue. Please return this equipment to the Admin Office immediately. Your borrowing access remains blocked until the return is recorded.', '2026-11-12 17:14:47'),
(4, 'FA0005BN2016', 64, 1, 'Redg Abelida', 'Immediate Return Required: Unterrupted Power Supply', 'This is an official overdue-return notice from PUPSync Admin. Unterrupted Power Supply was due on November 10, 2026 and is now 2 days overdue. Please return this equipment to the Admin Office immediately. Your borrowing access remains blocked until the return is recorded.', '2026-11-12 18:07:04'),
(5, 'FA0011BN2026', 67, 1, 'Redg Abelida', 'Immediate Return Required: Active Speakers', 'This is an official overdue-return notice from PUPSync Admin. Active Speakers was due on November 10, 2026 and is now 3 days overdue. Please return this equipment to the Admin Office immediately. Your borrowing access remains blocked until the return is recorded.', '2026-11-13 05:52:00');

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
(13, 'FA0005BN2016', 'Michael Anjelo Miguel', '9e3-n6c-xb2', 1, 'Frederick Rosales', '', '2026-11-09 22:54:26', '2026-11-10 02:01:39'),
(15, 'FA0143MN2016', 'Archie Arevalo', 'nua-jcp-npm', 0, NULL, NULL, '2026-11-10 22:45:08', NULL);

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

--
-- Dumping data for table `tbl_faculty_item_codes`
--

INSERT INTO `tbl_faculty_item_codes` (`id`, `faculty_id`, `faculty_name`, `item_name`, `item_id`, `code`, `is_used`, `used_by_name`, `used_by_id`, `used_at`, `created_at`) VALUES
(26, 'FA0143MN2016', 'Archie Arevalo', 'Beaker (1000ml)', 163, 'Lgz86rvRAfEb', 0, NULL, NULL, NULL, '2026-11-10 23:11:12'),
(27, 'FA0143MN2016', 'Archie Arevalo', 'Beaker (250ml)', 161, 'p6cCLWdpYbPs', 0, NULL, NULL, NULL, '2026-11-10 23:11:24'),
(28, 'FA0005BN2016', 'Michael Anjelo Miguel', 'Active Speakers', 251, 'FqANGSEHB5Lk', 0, NULL, NULL, NULL, '2026-11-11 08:58:25'),
(29, 'FA0005BN2016', 'Michael Anjelo Miguel', 'AC Remote', 9, 'd5qnH6BqHkNJ', 0, NULL, NULL, NULL, '2026-11-12 16:25:16'),
(30, 'FA0005BN2016', 'Michael Anjelo Miguel', 'Acer System Unit', 79, '3V6MGFx44REx', 0, NULL, NULL, NULL, '2026-11-12 16:25:27');

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
(8, 'FA0011BN2026', 'reqpending-67', 1, 0, '2026-11-10 15:30:16'),
(9, 'FA0011BN2026', 'adminnotice-3', 1, 0, '2026-11-13 05:51:29'),
(10, 'FA0011BN2026', 'adminnotice-5', 1, 0, '2026-11-13 05:52:10');

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
(33, 2, 1, 'Unterrupted Power Supply', 'Computer Laboratories', 30, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(34, 2, 1, 'Automatic Voltage Regulator', 'Computer Laboratories', 30, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(35, 2, 1, 'Data Comm Cabinet', 'Computer Laboratories', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(36, 2, 1, 'D-Link Switch/Hub', 'Computer Laboratories', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(37, 2, 1, 'Network Switch', 'Computer Laboratories', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(38, 2, 1, 'Router', 'Computer Laboratories', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(39, 2, 1, 'System Unit', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(40, 2, 1, 'Keyboard', 'Computer Laboratories', 30, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(41, 2, 1, 'Optical Mouse', 'Computer Laboratories', 30, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(42, 2, 1, 'PC Monitor', 'Computer Laboratories', 30, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(43, 2, 1, 'Printer', 'Computer Laboratories', 1, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:55', 0, 0),
(44, 2, 1, 'Globe Ammeter (-500 to 500 uA)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(45, 2, 1, 'Ammeter (100/500 mA)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(46, 2, 1, 'Globe Ammeter (1/5 A)', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(47, 2, 1, 'Ammeter (5A)', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(48, 2, 1, 'Camsco Ammeter (5A)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(49, 2, 1, 'Ammeter (15A)', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(50, 2, 1, 'Ammeter (20A)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(51, 2, 1, 'Ammeter (30A)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(52, 2, 1, 'Nikko Denki Ammeter (30A)', 'Dispensing Room', 20, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(53, 2, 1, 'EDM Voltmeter (3V)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(54, 2, 1, 'Voltmeter (10V)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(55, 2, 1, 'Voltmeter (15V)', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(56, 2, 1, 'Unix Voltmeter (15V)', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(57, 2, 1, 'Voltmeter (3/15V)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(58, 2, 1, 'Nikko Denki Voltmeter (30V)', 'Dispensing Room', 9, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(59, 2, 1, 'CAmsco Voltmeter (150V)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(60, 2, 1, 'COS Power Factor Meter (5V)', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(61, 2, 1, 'Risesun COS Power Factor Meter (5V)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(62, 2, 1, 'Nikko Denki COS Power Factor Meter (5V)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(63, 2, 1, 'Transformer DC Moving Coil', 'Dispensing Room', 8, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(64, 2, 1, 'Transformer Module 03', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(65, 2, 1, 'Transformer Module 07', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(66, 2, 1, 'Transformer Current', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(67, 2, 1, 'Black Potentiometer', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(68, 2, 1, 'White Potentiometer', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(69, 2, 1, 'Regulating Transformer 250', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(70, 2, 1, 'Nice-Power DC Power Supply', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(71, 2, 1, 'Lodestar - DC Power Supply', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(72, 2, 1, 'Wattmeter (500w)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(73, 2, 1, 'Magnetic Contractor', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(74, 2, 1, 'Resistance Box', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(75, 2, 1, 'Uni-T Digital Multimeter', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(76, 2, 1, 'Sanwa Multitester', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(77, 2, 1, 'Lenovo System Unit', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(78, 2, 1, 'Novus System Unit', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(79, 2, 1, 'Acer System Unit', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(80, 2, 1, 'NewTech System Unit', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(81, 2, 1, 'Lenovo Monitor', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(82, 2, 1, 'BenQ Monitor', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(83, 2, 1, 'Sassin Power Supply', 'Dispensing Room', 9, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(84, 2, 1, 'Harrys Power Supply', 'Dispensing Room', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(85, 2, 1, 'Wheeler Power Supply', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(86, 2, 1, 'Air Compressor', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(87, 2, 1, 'D-Link Dongle', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(88, 2, 1, 'Dummy Dome Camera', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(89, 2, 1, 'Wheeler Digital Traing System', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(90, 2, 1, 'Coaxial Cable Stripper', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(91, 2, 1, 'Wire Stripper', 'Dispensing Room', 6, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(92, 2, 1, 'Crimping Tool', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(93, 2, 1, 'Pack of Crystal Heads', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(94, 2, 1, 'Roll of Lan Cable', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(95, 2, 1, 'D-Link 24-Port Switch', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(96, 2, 1, 'D-Link 16-Port Switch', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(97, 2, 1, 'D-Link 8-Port Switch', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(98, 2, 1, 'Network Cable Tester', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(99, 2, 1, 'Amron Prpgrammable Controller', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(100, 2, 1, 'Atwood Machine', 'Dispensing Room', 12, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(101, 2, 1, 'Wheeler 20MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(102, 2, 1, 'Instek 100MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(103, 2, 1, 'Raffles 100MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(104, 2, 1, 'Leader 40MHz Oscilloscope', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(105, 2, 1, 'Wheeler Signal Generator', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(106, 2, 1, 'Function Generator', 'Dispensing Room', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(107, 2, 1, 'Incline Plane', 'Dispensing Room', 9, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(108, 2, 1, 'Frictionless Dynamic Cart', 'Dispensing Room', 6, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(109, 2, 1, 'Friction Board with Pully', 'Dispensing Room', 9, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(110, 2, 1, 'Freefall Apparatus', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(111, 2, 1, 'Centripetal Force Apparatus', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(112, 2, 1, 'Dynamic Cart', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(113, 2, 1, 'Friction Block', 'Dispensing Room', 6, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(114, 2, 1, 'Winch Roller Fairlead', 'Dispensing Room', 11, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(115, 2, 1, 'Ballistic Pendulums', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(116, 2, 1, 'Demonstration Balance', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(117, 2, 1, 'Force Table', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(118, 2, 1, 'Slinky Coil 3x4', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(119, 2, 1, 'Cage Style Slotted Mass Hanger', 'Dispensing Room', 11, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(120, 2, 1, 'Graduated Cylinder (100ml)', 'Chem Lab', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(121, 2, 1, 'Beaker (100ml)', 'Chem Lab', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(122, 2, 1, 'Resistor Assortment Kit', 'Dispensing Room', 15, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(123, 2, 1, 'Freefall Apparatus Accessories', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(124, 2, 1, 'Electric Calorimeter', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(125, 2, 1, 'Esal Barometer', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(126, 2, 1, 'Box of Syringe with Needle', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(127, 2, 1, 'Hook Style Slotted Mass Hanger', 'Dispensing Room', 13, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(128, 2, 1, 'Field Mapping Set', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(129, 2, 1, 'Double Meam Balance', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(130, 2, 1, 'Triple Beam Balance', 'Chem Lab', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(131, 2, 1, 'Spalding Stopwatch', 'Dispensing Room', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(132, 2, 1, 'Kadio Stopwatch', 'Dispensing Room', 9, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(133, 2, 1, 'Glass Plate for Friction Board', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(134, 2, 1, 'Soldering Iron', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(135, 2, 1, 'Desoldering Pump', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(136, 2, 1, 'Erlenmeyer Flask (250ml)', 'Chem Lab', 12, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(137, 2, 1, 'Erlenmeyer Flask (500ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(138, 2, 1, 'Erlenmeyer Flask (1000ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(139, 2, 1, 'Red Fire Extinguisher', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(140, 2, 1, 'Retort Stand', 'Dispensing Room', 6, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(141, 2, 1, 'Physical Optics Experiment Set', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(142, 2, 1, 'Artec Robo Creator Set DX', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(143, 2, 1, 'Wadfow Bench Vice', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(144, 2, 1, 'Adaptor', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:56', 0, 0),
(145, 2, 1, 'Raspberry Pi Camera', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(146, 2, 1, 'Digital Scale', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(147, 2, 1, 'Test Line ad Alligator Clip Set', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(148, 2, 1, 'Box of Potentiometer', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(149, 2, 1, 'Ohms Law Apparatus', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(150, 2, 1, 'Metal File Set', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(151, 2, 1, 'Digital Level', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(152, 2, 1, 'Tatnad Soldering Iron', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(153, 2, 1, 'Spring Balancer', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(154, 2, 1, 'Needle File', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(155, 2, 1, 'White Hard Hat', 'Dispensing Room', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(156, 2, 1, 'Microscope', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(157, 2, 1, 'Kuaiqu DC Power Supply', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(158, 2, 1, 'Portable Monitor', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(159, 2, 1, 'Wadfow Claw Hammer', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(160, 2, 1, 'Wadfow Ball Pein Hammer', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(161, 2, 1, 'Beaker (250ml)', 'Chem Lab', 8, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(162, 2, 1, 'Beaker (500ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(163, 2, 1, 'Beaker (1000ml)', 'Chem Lab', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(164, 2, 1, 'Glass Funnel (50mm)', 'Chem Lab', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(165, 2, 1, 'Glass Funnel (60mm)', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(166, 2, 1, 'Glass Funnel (75mm)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(167, 2, 1, 'Glass Funnel (100mm)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(168, 2, 1, 'Burette', 'Dispensing Room', 1, 'Fair', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(169, 2, 1, 'Optical Bench', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(170, 2, 1, 'Galvanometer', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(171, 2, 1, 'Magnifying Glass', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(172, 2, 1, 'Compass', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(173, 2, 1, 'Combination Wrench', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(174, 2, 1, 'Crucible (50ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(175, 2, 1, 'Crucible (30ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(176, 2, 1, 'Indicator Paper', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(177, 2, 1, 'Horse Shoe Magnet', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(178, 2, 1, 'Voltmeter (0-500v)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(179, 2, 1, 'Squeeze Bottle (250ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(180, 2, 1, 'Squeeze Bottle (500ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(181, 2, 1, 'Wadfow Machinist Hammer', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(182, 2, 1, 'Crimper Plier', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(183, 2, 1, 'VE terminals', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(184, 2, 1, 'Tap and Die Set', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(185, 2, 1, 'Prism', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(186, 2, 1, 'Ammeter (0-100mA)', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(187, 2, 1, 'Test Tube Rack', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(188, 2, 1, 'L-Square', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(189, 2, 1, 'Saw', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(190, 2, 1, 'Metal Saw', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(191, 2, 1, 'Ammeter (0-5A)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(192, 2, 1, 'Gas Burner', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(193, 2, 1, 'Voltmeter (0-20v)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(194, 2, 1, 'Convex Lens', 'Dispensing Room', 12, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(195, 2, 1, 'Meter Stick', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(196, 2, 1, 'Multimeter', 'Dispensing Room', 7, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(197, 2, 1, 'Safety Goggles', 'Dispensing Room', 15, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(198, 2, 1, 'Face Shield', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(199, 2, 1, 'Dropper Pipette (10ml)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(200, 2, 1, 'Raspi Camera', 'Dispensing Room', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(201, 2, 1, 'Thermometer', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(202, 2, 1, 'Inculating Loops', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(203, 2, 1, 'Watch Glass (Large)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(204, 2, 1, 'Watch Glass (Medium)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(205, 2, 1, 'Watch Glass (Small)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:57', 0, 0),
(206, 2, 1, 'Test Tube Holder', 'Dispensing Room', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(207, 2, 1, 'Test Tube Brush', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(208, 2, 1, 'Crucible Tong', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(209, 2, 1, 'Tuning Fork', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(210, 2, 1, 'ESP32', 'Dispensing Room', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(211, 2, 1, 'Multi-Function Meter', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(212, 2, 1, 'Relay (8in)', 'Dispensing Room', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(213, 2, 1, 'Vernier Caliper', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(214, 2, 1, 'Mini Guage', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(215, 2, 1, 'Precision Screwdriver', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(216, 2, 1, 'Wrench (150x19)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(217, 2, 1, 'Wrench (200x24)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(218, 2, 1, 'Wrench (250x30)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(219, 2, 1, 'Wrench (300x36)', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(220, 2, 1, 'Long Nose', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(221, 2, 1, 'Pliers', 'Dispensing Room', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(222, 2, 1, 'Cutting Pliers', 'Dispensing Room', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(223, 2, 1, 'Graduated Cylinder (10ml)', 'Chem Lab', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(224, 2, 1, 'Graduated Cylinder (25ml)', 'Chem Lab', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(225, 2, 1, 'Graduated Cylinder (50ml)', 'Chem Lab', 8, 'Fair', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(226, 2, 1, 'Erlenmeyer Flask (100ml)', 'Chem Lab', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(227, 2, 1, 'Erlenmeyer Flask (125ml)', 'Chem Lab', 11, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(228, 2, 1, 'Filtering Flask', 'Chem Lab', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(229, 2, 1, 'Separatory Funnel (100ml)', 'Chem Lab', 1, 'Fair', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(230, 2, 1, 'Separatory Funnel (250ml)', 'Chem Lab', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(231, 2, 1, 'Separatory Funnel (500ml)', 'Chem Lab', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(232, 2, 1, 'Florence Flask (250ml)', 'Chem Lab', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(233, 2, 1, 'Florence Flask (500ml)', 'Chem Lab', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(234, 2, 1, 'Beaker (50ml)', 'Chem Lab', 5, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(235, 2, 1, 'Beaker (150ml)', 'Chem Lab', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(236, 2, 1, 'Beaker (400ml)', 'Chem Lab', 7, 'Fair', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(237, 1, 1, 'Alcohol Lamp', 'Audio/Visual', 2, 'For Repair', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 1, 0),
(238, 2, 1, 'Pipette', 'Chem Lab', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(239, 2, 1, 'Hydrometer', 'Chem Lab', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(240, 2, 1, 'Watch Glass', 'Chem Lab', 9, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(241, 2, 1, 'Glass Thermometer', 'Chem Lab', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(242, 2, 1, 'Delivery Tube', 'Chem Lab', 3, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(243, 2, 1, 'Reagent Bottle', 'Chem Lab', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(244, 2, 1, 'Petri Dish (100mm)', 'Chem Lab', 10, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(245, 2, 1, 'Double Beam Balance', 'Chem Lab', 7, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(246, 2, 1, 'Fume Hood', 'Chem Lab', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(247, 2, 1, 'Smart TV', 'Audio-Visual Equipment', 17, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(248, 2, 1, 'Smart TV (Touch Screen)', 'Audio-Visual Equipment', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(249, 2, 1, 'LCD Projector', 'Audio-Visual Equipment', 6, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(250, 2, 1, 'Analog Audio Mixer', 'Audio-Visual Equipment', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(251, 2, 1, 'Active Speakers', 'Audio-Visual Equipment', 4, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(252, 2, 1, 'Passive Speakers with Power Amplifier', 'Audio-Visual Equipment', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(253, 2, 1, 'Power Amplifier', 'Audio-Visual Equipment', 1, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
(254, 2, 1, 'Wireless Microphone', 'Audio-Visual Equipment', 2, 'Good', NULL, 'uploads/default.png', '2026-11-08 16:22:58', 0, 0),
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
(272, 2, 1, 'hotdog', 'Dispensing Room', 1, 'Good', NULL, NULL, '2026-11-09 13:04:40', 1, 0),
(273, 2, 1, 'Alcohol Lamp', 'Chem Lab', 0, 'For Repair', NULL, 'uploads/default.png', '2026-11-14 18:42:13', 0, 0);

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
(1, '::1', 2, '2026-11-14 17:07:39', NULL),
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
(26, 'edmundo.delacruz@gmail.com', '127.0.0.1', 1, 0, NULL, '2026-11-09 15:36:32', 0),
(27, 'main@admin.edu', '::1', 0, 0, NULL, '2026-11-10 02:18:05', 1),
(28, 'michael.miguel@pupsync.edu', '::1', 0, 0, NULL, '2026-11-12 07:43:26', 1),
(29, 'arvin.nuez@pupsync.edu', '::1', 2, 0, NULL, '2026-11-14 17:08:29', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notif_prefs`
--

CREATE TABLE `tbl_notif_prefs` (
  `id` int(11) NOT NULL,
  `audience` varchar(10) NOT NULL,
  `user_key` varchar(64) NOT NULL,
  `pref_key` varchar(32) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_notif_prefs`
--

INSERT INTO `tbl_notif_prefs` (`id`, `audience`, `user_key`, `pref_key`, `enabled`, `updated_at`) VALUES
(1, 'faculty', 'FA0011BN2026', 'all', 1, '2026-11-13 08:56:16');

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
(60, 'Edmundo Dela Cruz', 'FA0008BN2023', 'Acer System Unit', 'Edmundo Dela Cruz', 'Room 210Building C (CITE) · 2nd Floor', '2026-11-09', '2026-11-09', NULL, NULL, '2026-11-11 16:10:35', NULL, 'Returned', '2026-11-09 19:08:21', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', 'f46b09c1-cc5d-48ee-a6dd-dd85865092fc', '53a4512b54a2477f43f49dafd6af1cb689e8a9eff81d66cab707eba1bafa7448', '19:10:00', '20:10:00', 1, 76, '0', 0, 0, NULL, NULL, 0, NULL),
(61, 'Edmundo Dela Cruz', 'FA0008BN2023', 'Adaptor', 'Edmundo Dela Cruz', 'Room 210Building C (CITE) · 2nd Floor', '2026-11-09', '2026-11-09', NULL, NULL, '2026-11-11 16:10:26', NULL, 'Returned', '2026-11-09 19:08:22', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', 'f46b09c1-cc5d-48ee-a6dd-dd85865092fc', '53a4512b54a2477f43f49dafd6af1cb689e8a9eff81d66cab707eba1bafa7448', '19:10:00', '20:10:00', 2, 76, '0', 0, 0, NULL, NULL, 0, NULL),
(62, 'Edmundo Dela Cruz', 'FA0008BN2023', 'Active Speakers', 'Edmundo Dela Cruz', 'Room 210Building C (CITE) · 2nd Floor', '2026-11-09', '2026-11-09', NULL, NULL, '2026-11-11 16:10:13', NULL, 'Returned', '2026-11-09 19:08:22', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', 'f46b09c1-cc5d-48ee-a6dd-dd85865092fc', '53a4512b54a2477f43f49dafd6af1cb689e8a9eff81d66cab707eba1bafa7448', '19:10:00', '20:10:00', 2, 76, '0', 0, 0, NULL, NULL, 0, NULL),
(63, 'Michael Anjelo Miguel', 'FA0005BN2016', 'Active Speakers', 'Michael Anjelo Miguel', 'Room 205 · Building A (Old) · 2nd Floor', '2026-11-11', '2026-11-11', NULL, 'b150c281e41c1e07f6bc499dc59df089d36748a131b196afdd8012890822b60a', '2026-11-12 17:14:52', NULL, 'Returned', '2026-11-10 00:36:01', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', 'Napiza, Sandy', '', 'personal', NULL, NULL, '07:00:00', '09:00:00', 1, 11, 'future', 1, 0, NULL, NULL, 0, NULL),
(64, 'Michael Anjelo Miguel', 'FA0005BN2016', 'Unterrupted Power Supply', 'Michael Anjelo Miguel', 'Room 203', '2026-11-10', '2026-11-10', NULL, '27a1178a554ef46246a325a44d28bf61c4ec0824bce89a2e057c84e799d98445', '2026-11-12 18:27:39', NULL, 'Returned', '2026-11-10 02:01:36', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', 'Frederick Rosales', '', 'student', NULL, NULL, '07:00:00', '08:00:00', 1, 30, 'today', 0, 0, NULL, NULL, 0, NULL),
(65, 'Michael Anjelo Miguel', 'FA0005BN2016', 'Router', 'Michael Anjelo Miguel', 'Room 203', '2026-11-10', '2026-11-10', NULL, '92dce4fbd4bda5a060f63161ec8ad1d9ad64bf4bcd6e7685f97d5f3407ecc3f1', '2026-11-12 17:14:30', NULL, 'Returned', '2026-11-10 02:01:39', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', 'Frederick Rosales', '', 'student', NULL, NULL, '07:00:00', '08:00:00', 1, 30, 'today', 0, 0, NULL, NULL, 0, NULL),
(66, 'Stephen Perez', 'FA0011BN2026', 'Active Speakers', 'Stephen Perez', 'Audio Visual Room 2 · Building A (Old) · 1st Floor', '2026-11-11', '2026-11-11', NULL, '341a723fe4194caa6f47847514e8689a6213abcf40ef0d5e6360a49feae1b722', '2026-11-13 05:52:40', NULL, 'Returned', '2026-11-10 15:27:09', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', NULL, NULL, '16:00:00', '19:00:00', 2, 99, 'future', 1, 0, NULL, NULL, 0, NULL),
(67, 'Stephen Perez', 'FA0011BN2026', 'Active Speakers', 'Stephen Perez', 'Room 203Building A (Old) · 2nd Floor', '2026-11-10', '2026-11-10', NULL, NULL, '2026-11-13 05:52:37', NULL, 'Returned', '2026-11-10 15:29:13', 'Auto-approved: no admin action within the buffer time.', NULL, 'auto_approve_timeout', '', '', 'personal', '9c3fa87e-ba96-4960-aaf8-d3fc071d6372', '5a193f9913356a6330038f68408348967bf7e92ae106d2fe779017325e7bec9a', '16:00:00', '19:00:00', 1, 9, '0', 0, 0, NULL, NULL, 0, NULL),
(68, 'Stephen Perez', 'FA0011BN2026', 'Active Speakers', 'Stephen Perez', 'Outside', '2026-11-13', '2026-11-13', NULL, NULL, '2026-11-13 06:03:35', '2026-11-13 05:58:09', 'Returned', '2026-11-13 05:53:58', 'Approved: the equipment is free for the requested schedule.', NULL, 'rule_1_fifo', '', '', 'personal', NULL, NULL, '07:00:00', '09:00:00', 1, NULL, 'today', 1, 0, NULL, NULL, 0, NULL),
(69, 'Stephen Perez', 'FA0011BN2026', 'Unterrupted Power Supply', 'Stephen Perez', 'Room 203 · Building B (New) · 2nd Floor', '2026-11-13', '2026-11-13', NULL, NULL, '2026-11-13 06:33:15', '2026-11-13 06:33:03', 'Returned', '2026-11-13 06:32:31', 'Approved: the equipment is free for the requested schedule.', NULL, 'rule_1_fifo', '', '', 'personal', NULL, NULL, '07:00:00', '08:00:00', 3, 30, 'today', 1, 0, NULL, NULL, 0, NULL),
(70, 'Stephen Perez', 'FA0011BN2026', 'Unterrupted Power Supply', 'Stephen Perez', 'Audio Visual Room 2 · Building A (Old) · 1st Floor', '2026-11-13', '2026-11-13', NULL, NULL, '2026-11-13 06:35:32', '2026-11-13 06:35:23', 'Returned', '2026-11-13 06:34:52', 'Approved: the equipment is free for the requested schedule.', NULL, 'rule_1_fifo', '', '', 'personal', NULL, NULL, '07:00:00', '08:00:00', 2, 99, 'today', 1, 0, NULL, NULL, 0, NULL);

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
  `created_at` datetime DEFAULT current_timestamp(),
  `room_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_rooms`
--

INSERT INTO `tbl_rooms` (`room_id`, `building_id`, `room_name`, `floor_number`, `floor_label`, `seating_capacity`, `amenities`, `status`, `is_archived`, `is_locked`, `sort_order`, `created_at`, `room_code`) VALUES
(1, 1, 'Admin Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 1, '2026-10-01 13:05:16', NULL),
(2, 1, 'Registration Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 2, '2026-10-01 13:05:16', NULL),
(3, 1, 'OSAS Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 3, '2026-10-01 13:05:16', NULL),
(4, 1, 'Office 1', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 4, '2026-10-01 13:05:16', NULL),
(5, 1, 'Clinic', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 5, '2026-10-01 13:05:16', NULL),
(6, 1, 'Staff Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 6, '2026-10-01 13:05:16', NULL),
(7, 1, 'Room 201', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', 'A201'),
(8, 1, 'Room 202', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16', 'A202'),
(9, 1, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', 'A203'),
(10, 1, 'Room 204', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', 'A204'),
(11, 1, 'Room 205', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', 'A205'),
(12, 1, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', 'A301'),
(13, 1, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16', 'A302'),
(14, 1, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', 'A303'),
(15, 1, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', 'A304'),
(16, 1, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', 'A305'),
(17, 1, 'Org Room', 4, '4th Floor', NULL, NULL, 'Not Bookable', 1, 0, 1, '2026-10-01 13:05:16', NULL),
(18, 1, 'CSC Room', 4, '4th Floor', NULL, NULL, 'Not Bookable', 1, 0, 2, '2026-10-01 13:05:16', NULL),
(19, 1, 'AVR 2', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', NULL),
(20, 1, 'Computer Laboratory 1', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', NULL),
(21, 1, 'Computer Laboratory 2', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', NULL),
(22, 1, 'Chemistry Laboratory', 5, '5th Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', NULL),
(23, 2, 'Library', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 1, '2026-10-01 13:05:16', NULL),
(24, 2, 'Directors Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 2, '2026-10-01 13:05:16', NULL),
(25, 2, 'Faculty Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 3, '2026-10-01 13:05:16', NULL),
(26, 2, 'DO Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 4, '2026-10-01 13:05:16', NULL),
(27, 2, 'Guidance Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 5, '2026-10-01 13:05:16', NULL),
(28, 2, 'Research Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', NULL),
(29, 2, 'Room 202', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16', 'B202'),
(30, 2, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', 'B203'),
(31, 2, 'Room 204', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', 'B204'),
(32, 2, 'Room 205', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', 'B205'),
(33, 2, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', 'B301'),
(34, 2, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16', 'B302'),
(35, 2, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', 'B303'),
(36, 2, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', 'B304'),
(37, 2, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', 'B305'),
(38, 2, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', 'B401'),
(39, 2, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16', 'B402'),
(40, 2, 'AVR 1', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', NULL),
(41, 2, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', 'B405'),
(51, 3, 'Prayer Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 1, '2026-10-01 13:05:16', NULL),
(52, 3, 'Audiovisual Room', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 2, '2026-10-01 13:05:16', NULL),
(53, 3, 'Testing Area', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 3, '2026-10-01 13:05:16', NULL),
(54, 3, 'Student Organization Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 4, '2026-10-01 13:05:16', NULL),
(55, 3, 'Clinic Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 5, '2026-10-01 13:05:16', NULL),
(56, 3, 'Industrial Engineering Room', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 6, '2026-10-01 13:05:16', NULL),
(57, 3, 'Director\'s Office', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 7, '2026-10-01 13:05:16', NULL),
(58, 3, 'Basketball Court', 1, '1st Floor', NULL, NULL, 'Not Bookable', 1, 0, 8, '2026-10-01 13:05:16', NULL),
(59, 3, 'Room 103', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 9, '2026-10-01 13:05:16', 'C103'),
(60, 3, 'Room 105', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-10-01 13:05:16', 'C105'),
(61, 3, 'Room 106', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 11, '2026-10-01 13:05:16', NULL),
(62, 3, 'Room 116', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 12, '2026-10-01 13:05:16', NULL),
(63, 3, 'Room 118', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 13, '2026-10-01 13:05:16', NULL),
(64, 3, 'Room 119', 1, '1st Floor', NULL, NULL, 'Not Bookable', 0, 0, 14, '2026-10-01 13:05:16', NULL),
(65, 3, 'Admin Office', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 1, 0, 1, '2026-10-01 13:05:16', NULL),
(66, 3, 'Faculty Lounge', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 1, 0, 2, '2026-10-01 13:05:16', NULL),
(67, 3, 'AutoCAD & Multimedia Laboratory', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', NULL),
(68, 3, 'Computer Laboratory 1', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', NULL),
(69, 3, 'Computer Laboratory 2', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', NULL),
(70, 3, 'Computer Laboratory 3', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-10-01 13:05:16', NULL),
(71, 3, 'Ergonomics Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-10-01 13:05:16', NULL),
(72, 3, 'Digital Laboratory Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-10-01 13:05:16', NULL),
(73, 3, 'Dispensing Room', 2, '2nd Floor', NULL, NULL, 'Not Bookable', 1, 0, 9, '2026-10-01 13:05:16', NULL),
(74, 3, 'Microprocessing Laboratory Room', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-10-01 13:05:16', NULL),
(75, 3, 'Room 203', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 11, '2026-10-01 13:05:16', NULL),
(76, 3, 'Room 210', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 12, '2026-10-01 13:05:16', 'C210'),
(77, 3, 'Room 212', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 13, '2026-10-01 13:05:16', 'C212'),
(78, 3, 'Room 218', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 14, '2026-10-01 13:05:16', NULL),
(79, 3, 'Library Room', 3, '3rd Floor', NULL, NULL, 'Not Bookable', 1, 0, 1, '2026-10-01 13:05:16', NULL),
(80, 3, 'Library Extension Room', 3, '3rd Floor', NULL, NULL, 'Not Bookable', 1, 0, 2, '2026-10-01 13:05:16', NULL),
(81, 3, 'Physics Room', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', NULL),
(82, 3, 'Room 301', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', NULL),
(83, 3, 'Room 302', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', NULL),
(84, 3, 'Room 303', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-10-01 13:05:16', NULL),
(85, 3, 'Room 304', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-10-01 13:05:16', NULL),
(86, 3, 'Room 305', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-10-01 13:05:16', NULL),
(87, 3, 'Room 307', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 9, '2026-10-01 13:05:16', 'C307'),
(88, 3, 'Room 308', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-10-01 13:05:16', 'C308'),
(89, 3, 'Room 309', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 11, '2026-10-01 13:05:16', 'C309'),
(90, 3, 'Room 310', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 12, '2026-10-01 13:05:16', 'C310'),
(91, 3, 'Chemistry Laboratory Room', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 1, '2026-10-01 13:05:16', NULL),
(92, 3, 'Student Lounge', 4, '4th Floor', NULL, NULL, 'Not Bookable', 1, 0, 2, '2026-10-01 13:05:16', NULL),
(93, 3, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 3, '2026-10-01 13:05:16', NULL),
(94, 3, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 4, '2026-10-01 13:05:16', NULL),
(95, 3, 'Room 403', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-10-01 13:05:16', NULL),
(96, 3, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-10-01 13:05:16', NULL),
(97, 3, 'Room 406', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-10-01 13:05:16', NULL),
(98, 3, 'Room 415', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-10-01 13:05:16', NULL),
(99, 1, 'Audio Visual Room 2', 1, '1st Floor', NULL, '[\"WiFi\",\"A\\/C\",\"Whiteboard\",\"Smart TV\"]', 'Available', 0, 0, 0, '2026-11-10 04:50:01', NULL),
(100, 1, 'Room 402', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-11-11 13:54:29', 'A402'),
(101, 1, 'Room 403', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 7, '2026-11-11 13:54:29', 'A403'),
(102, 3, 'Room 101', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 15, '2026-11-11 13:54:29', 'C101'),
(103, 3, 'Computer Lab 207', 2, '2nd Floor', NULL, 'Computer Lab', 'Available', 0, 0, 15, '2026-11-11 13:54:29', 'CL207'),
(104, 3, 'Computer Lab 209', 2, '2nd Floor', NULL, 'Computer Lab', 'Available', 0, 0, 16, '2026-11-11 13:54:29', 'CL209'),
(105, 1, 'Room 404', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 8, '2026-11-11 13:54:29', 'A404'),
(106, 1, 'Room 405', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 9, '2026-11-11 13:54:29', 'A405'),
(107, 2, 'Room 403', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 5, '2026-11-11 13:54:29', 'B403'),
(108, 3, 'Room 206', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 17, '2026-11-11 13:54:29', 'C206'),
(109, 2, 'Room 201', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-11-11 13:54:29', 'B201'),
(110, 2, 'Room 404', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 6, '2026-11-11 13:54:29', 'B404'),
(111, 3, 'Room 208', 2, '2nd Floor', NULL, NULL, 'Available', 0, 0, 18, '2026-11-11 13:54:29', 'C208'),
(112, 3, 'Room 107', 1, '1st Floor', NULL, NULL, 'Available', 0, 0, 16, '2026-11-11 13:54:29', 'C107'),
(113, 3, 'Room 306', 3, '3rd Floor', NULL, NULL, 'Available', 0, 0, 13, '2026-11-11 13:54:29', 'C306'),
(114, 1, 'Room 401', 4, '4th Floor', NULL, NULL, 'Available', 0, 0, 10, '2026-11-11 13:54:29', 'A401');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rooms_import_files`
--

CREATE TABLE `tbl_rooms_import_files` (
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
-- Dumping data for table `tbl_rooms_import_files`
--

INSERT INTO `tbl_rooms_import_files` (`id`, `original_name`, `stored_name`, `mime_type`, `file_size`, `sha256`, `import_mode`, `row_count`, `inserted_count`, `updated_count`, `skipped_count`, `status`, `uploaded_by`, `uploaded_at`, `imported_at`) VALUES
(2, 'PUPSync-FullScheduleList.xlsx', '2942d9781bc3ec31cf9fb40b185c5f86.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 48503, 'c566dd3679c7ee5d207ec12bf877eca14215ace8eb1ded31869874248ffb2fb2', 'merge', 696, 723, 0, 0, 'imported', 'Redg Abelida', '2026-11-12 07:45:48', '2026-11-12 07:45:49');

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
(14, 14, 88, 'Room 308', 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'Approved', 'rule_1_fifo', 'Facility reservation approved — no time conflict.', '2026-10-25 09:57:21'),
(15, 15, 9, 'Room 203', 'FA0011BN2026', 'Stephen Perez', 'Approved', 'rule_1_fifo', 'Facility reservation approved — no time conflict.', '2026-11-10 15:28:39');

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
(14, 88, 'NOTSET-275A970376E5', 'Edmundo Dela Cruz', 'personal', NULL, NULL, 'Lecture', 1, '2026-10-26', '14:00:00', '17:00:00', NULL, NULL, 'Cancelled', 'Facility reservation approved — no time conflict.', '2026-10-25 09:57:21', '2026-10-25 10:43:50'),
(15, 9, 'FA0011BN2026', 'Stephen Perez', 'personal', NULL, NULL, 'Lecture', 1, '2026-11-10', '16:00:00', '19:00:00', NULL, NULL, 'Approved', 'Facility reservation approved — no time conflict.', '2026-11-10 15:28:39', NULL);

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
-- Table structure for table `tbl_service_survey_comments`
--

CREATE TABLE `tbl_service_survey_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `comment_text` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `period_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_service_survey_comments`
--

INSERT INTO `tbl_service_survey_comments` (`id`, `comment_text`, `created_at`, `period_id`) VALUES
(1, 'oks lang', '2026-11-13 06:23:23', NULL),
(2, 'oksoks lang', '2026-11-13 06:30:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_survey_completions`
--

CREATE TABLE `tbl_service_survey_completions` (
  `source_type` varchar(16) NOT NULL,
  `transaction_key` varchar(64) NOT NULL,
  `answered` tinyint(1) NOT NULL DEFAULT 0,
  `consumed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_service_survey_completions`
--

INSERT INTO `tbl_service_survey_completions` (`source_type`, `transaction_key`, `answered`, `consumed_at`) VALUES
('equipment', 'batch:9c3fa87e-ba96-4960-aaf8-d3fc071d6372', 1, '2026-11-13 06:30:18'),
('equipment', 'request:66', 1, '2026-11-13 06:23:58'),
('equipment', 'request:68', 1, '2026-11-13 06:23:23'),
('equipment', 'request:69', 1, '2026-11-13 06:33:22'),
('equipment', 'request:70', 1, '2026-11-13 06:35:53'),
('room', '15', 0, '2026-11-13 06:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_survey_config`
--

CREATE TABLE `tbl_service_survey_config` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `comment_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `semester_start` date DEFAULT NULL,
  `semester_end` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_service_survey_config`
--

INSERT INTO `tbl_service_survey_config` (`id`, `enabled`, `comment_enabled`, `updated_at`, `semester_start`, `semester_end`) VALUES
(1, 1, 1, '2026-11-13 07:10:45', '2026-09-01', '2027-01-08');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_survey_periods`
--

CREATE TABLE `tbl_service_survey_periods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `semester_start` date NOT NULL,
  `semester_end` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `response_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_score` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_service_survey_periods`
--

INSERT INTO `tbl_service_survey_periods` (`id`, `semester_start`, `semester_end`, `period_start`, `period_end`, `response_count`, `rating_count`, `total_score`, `created_at`) VALUES
(1, '2026-09-01', '2027-01-08', '2026-09-01', '2026-09-14', 0, 0, 0, '2026-11-13 07:10:45'),
(2, '2026-09-01', '2027-01-08', '2026-09-15', '2026-09-28', 0, 0, 0, '2026-11-13 07:10:45'),
(3, '2026-09-01', '2027-01-08', '2026-09-29', '2026-10-12', 0, 0, 0, '2026-11-13 07:10:45'),
(4, '2026-09-01', '2027-01-08', '2026-10-13', '2026-10-26', 0, 0, 0, '2026-11-13 07:10:45'),
(5, '2026-09-01', '2027-01-08', '2026-10-27', '2026-11-09', 0, 0, 0, '2026-11-13 07:10:45'),
(6, '2026-09-01', '2027-01-08', '2026-11-10', '2026-11-23', 0, 0, 0, '2026-11-13 07:10:45'),
(7, '2026-09-01', '2027-01-08', '2026-11-24', '2026-12-07', 0, 0, 0, '2026-11-13 07:10:45'),
(8, '2026-09-01', '2027-01-08', '2026-12-08', '2026-12-21', 0, 0, 0, '2026-11-13 07:10:45'),
(9, '2026-09-01', '2027-01-08', '2026-12-22', '2027-01-04', 0, 0, 0, '2026-11-13 07:10:45'),
(10, '2026-09-01', '2027-01-08', '2027-01-05', '2027-01-08', 0, 0, 0, '2026-11-13 07:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_survey_period_questions`
--

CREATE TABLE `tbl_service_survey_period_questions` (
  `period_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `question_version` char(64) NOT NULL,
  `question_text` varchar(160) NOT NULL,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `responses` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_score` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `rating_1` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_2` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_3` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_4` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_5` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_survey_questions`
--

CREATE TABLE `tbl_service_survey_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_text` varchar(160) NOT NULL,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `responses` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_score` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_1` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_2` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_3` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_4` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rating_5` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_service_survey_questions`
--

INSERT INTO `tbl_service_survey_questions` (`id`, `question_text`, `sort_order`, `active`, `responses`, `total_score`, `rating_1`, `rating_2`, `rating_3`, `rating_4`, `rating_5`) VALUES
(1, 'How satisfied are you with the service you received?', 1, 1, 5, 18, 1, 1, 0, 0, 3),
(2, 'How helpful and respectful was the staff who assisted you?', 2, 0, 0, 0, 0, 0, 0, 0, 0),
(3, 'How clear was the information about your transaction?', 2, 1, 5, 19, 0, 0, 1, 4, 0),
(4, 'How satisfied are you with the time it took to complete your transaction?', 4, 0, 0, 0, 0, 0, 0, 0, 0),
(5, 'How likely are you to use this service again?', 3, 1, 5, 20, 0, 0, 2, 1, 2);

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
('gsheet_last_sync', '2026-11-14 12:04:12', '2026-11-14 19:04:12'),
('gsheet_sync_enabled', '0', '2026-11-08 16:09:50'),
('gsheet_url', 'https://docs.google.com/spreadsheets/d/1Dw6HiLwXKIS8hZI0VVFOh4awDzPi4KEy/edit?gid=1579991349#gid=1579991349', '2026-11-11 15:48:41');

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
  `profile_picture` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'Regular Faculty',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`fullname`, `faculty_id`, `email`, `backup_email`, `password`, `last_password_change`, `profile_picture`, `department`, `role`, `must_change_password`) VALUES
('Indaleen Quinsayas', 'FA0002BN2016', 'indaleen.quinsayas@pupsync.edu', NULL, '$2y$10$oUbLCC9dtNYPFxcvkvcbtOrmsdae1iiw1IGenLcfrWZo5CSyvlf1C', NULL, NULL, NULL, 'Regular Faculty', 0),
('Michael Anjelo Miguel', 'FA0005BN2016', 'michael.miguel@pupsync.edu', NULL, '$2y$10$cDg6QolF.l2mGo3v43go3e/fpglvD0WfWTxv3ifoAJ95wtEH4FGpC', NULL, NULL, NULL, 'Regular Faculty', 0),
('Arvin Nuñez', 'FA0005BN2026', 'arvin.nunez@pupsync.edu', NULL, '$2y$10$sU.v4llfJcWRZcx8QvFSYuOx3lJJH51TrkJ1TLNB/ID0IXCZaPmPO', '2026-11-14 17:09:34', NULL, 'Department of Information Technology/Computing', 'Regular Faculty', 0),
('Aaron Atienza', 'FA0006BN2024', 'aaron.atienza@pupsync.edu', NULL, '$2y$10$jhe4/CMvTgDYHbnB1XKaC.6NVO.di7gcuIk8cyQ8osmDIV.9uNs1.', NULL, NULL, NULL, 'Regular Faculty', 0),
('Edmundo Dela Cruz', 'FA0008BN2023', 'edmundo.delacruz@pupsync.edu', NULL, '$2y$10$XdcZ7CBLZ6CvgSQtQ.oh0.S9VH91QWzR08IJLQexx4SMvgR5Bzl3S', NULL, NULL, NULL, 'Regular Faculty', 0),
('Stephen Perez', 'FA0011BN2026', 'stephen.perez@pupsync.edu', NULL, '$2y$10$YjDFkqM4Ap0nQguCXFhcAOcUmLbEiEVLxE18CItSQEiBolMyVTunK', NULL, NULL, NULL, 'Regular Faculty', 0),
('Archie Arevalo', 'FA0143MN2016', 'archie.arevalo@pupsync.edu', 'archiearevalo@gmail.com', '$2y$10$uvhw5DCm0BaZaRQkO9nR2ezH.IsY7uXqGUqcWALfSpDf3eSobffcq', '2026-11-10 22:25:09', NULL, 'Department of General Education & Social Sciences', 'Regular Faculty', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_admin_no` (`admin_no`);

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
-- Indexes for table `tbl_class_schedule`
--
ALTER TABLE `tbl_class_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cs_room_day` (`room_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_cs_account` (`account_faculty_id`,`day_of_week`),
  ADD KEY `idx_cs_name` (`name_key`);

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
-- Indexes for table `tbl_notif_prefs`
--
ALTER TABLE `tbl_notif_prefs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notif_pref` (`audience`,`user_key`,`pref_key`);

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
  ADD KEY `fk_rooms_building` (`building_id`),
  ADD KEY `idx_rooms_code` (`room_code`);

--
-- Indexes for table `tbl_rooms_import_files`
--
ALTER TABLE `tbl_rooms_import_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rooms_import_stored` (`stored_name`),
  ADD KEY `idx_rooms_import_uploaded` (`uploaded_at`);

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
-- Indexes for table `tbl_service_survey_comments`
--
ALTER TABLE `tbl_service_survey_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_survey_comment_date` (`created_at`),
  ADD KEY `idx_service_survey_comment_period` (`period_id`);

--
-- Indexes for table `tbl_service_survey_completions`
--
ALTER TABLE `tbl_service_survey_completions`
  ADD PRIMARY KEY (`source_type`,`transaction_key`),
  ADD KEY `idx_service_survey_answered` (`answered`);

--
-- Indexes for table `tbl_service_survey_config`
--
ALTER TABLE `tbl_service_survey_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_service_survey_periods`
--
ALTER TABLE `tbl_service_survey_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_service_survey_period` (`semester_start`,`semester_end`,`period_start`),
  ADD KEY `idx_service_survey_semester` (`semester_start`,`semester_end`);

--
-- Indexes for table `tbl_service_survey_period_questions`
--
ALTER TABLE `tbl_service_survey_period_questions`
  ADD PRIMARY KEY (`period_id`,`question_id`,`question_version`),
  ADD KEY `idx_service_survey_period_question` (`question_id`);

--
-- Indexes for table `tbl_service_survey_questions`
--
ALTER TABLE `tbl_service_survey_questions`
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_admin_activity`
--
ALTER TABLE `tbl_admin_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

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
-- AUTO_INCREMENT for table `tbl_class_schedule`
--
ALTER TABLE `tbl_class_schedule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1447;

--
-- AUTO_INCREMENT for table `tbl_equipment_categories`
--
ALTER TABLE `tbl_equipment_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=942;

--
-- AUTO_INCREMENT for table `tbl_faculty_admin_notice`
--
ALTER TABLE `tbl_faculty_admin_notice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_faculty_codes`
--
ALTER TABLE `tbl_faculty_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_faculty_item_codes`
--
ALTER TABLE `tbl_faculty_item_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_faculty_notif_state`
--
ALTER TABLE `tbl_faculty_notif_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=274;

--
-- AUTO_INCREMENT for table `tbl_ip_login_attempts`
--
ALTER TABLE `tbl_ip_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tbl_item_code_attempts`
--
ALTER TABLE `tbl_item_code_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_login_attempts`
--
ALTER TABLE `tbl_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_notif_prefs`
--
ALTER TABLE `tbl_notif_prefs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_notif_state`
--
ALTER TABLE `tbl_notif_state`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `tbl_request_events`
--
ALTER TABLE `tbl_request_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `tbl_rooms_import_files`
--
ALTER TABLE `tbl_rooms_import_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_room_arbitration_log`
--
ALTER TABLE `tbl_room_arbitration_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_room_issues`
--
ALTER TABLE `tbl_room_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_room_reservations`
--
ALTER TABLE `tbl_room_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_room_waitlist`
--
ALTER TABLE `tbl_room_waitlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_service_survey_comments`
--
ALTER TABLE `tbl_service_survey_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_service_survey_periods`
--
ALTER TABLE `tbl_service_survey_periods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tbl_service_survey_questions`
--
ALTER TABLE `tbl_service_survey_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
