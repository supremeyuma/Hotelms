-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 09, 2026 at 09:37 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u776774917_MooreLife`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounting_periods`
--

CREATE TABLE `accounting_periods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL COMMENT 'Event type',
  `ip_address` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Request details, old/new values' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `metadata`, `created_at`, `updated_at`) VALUES
(1, 2, 'order_created', '127.0.0.1', '{\"note\": \"Seeded audit log #1\"}', '2025-12-05 22:01:34', '2025-12-12 00:01:34'),
(2, 4, 'image_uploaded', '127.0.0.1', '{\"note\": \"Seeded audit log #2\"}', '2025-12-05 23:01:34', '2025-12-12 00:01:34'),
(3, 5, 'maintenance_ticket_created', '127.0.0.1', '{\"note\": \"Seeded audit log #3\"}', '2025-12-07 18:01:34', '2025-12-12 00:01:34'),
(4, 6, 'inventory_stock_added', '127.0.0.1', '{\"note\": \"Seeded audit log #4\"}', '2025-12-07 11:01:34', '2025-12-12 00:01:34'),
(5, 7, 'booking_created', '127.0.0.1', '{\"note\": \"Seeded audit log #5\"}', '2025-12-07 15:01:34', '2025-12-12 00:01:34'),
(6, 8, 'order_created', '127.0.0.1', '{\"note\": \"Seeded audit log #6\"}', '2025-12-11 13:01:34', '2025-12-12 00:01:34'),
(7, 9, 'image_uploaded', '127.0.0.1', '{\"note\": \"Seeded audit log #7\"}', '2025-12-11 16:01:34', '2025-12-12 00:01:34'),
(8, 10, 'maintenance_ticket_created', '127.0.0.1', '{\"note\": \"Seeded audit log #8\"}', '2025-12-10 19:01:34', '2025-12-12 00:01:34'),
(9, 11, 'inventory_stock_added', '127.0.0.1', '{\"note\": \"Seeded audit log #9\"}', '2025-12-10 00:01:34', '2025-12-12 00:01:34'),
(10, 12, 'booking_created', '127.0.0.1', '{\"note\": \"Seeded audit log #10\"}', '2025-12-07 17:01:34', '2025-12-12 00:01:34'),
(11, 13, 'order_created', '127.0.0.1', '{\"note\": \"Seeded audit log #11\"}', '2025-12-08 10:01:34', '2025-12-12 00:01:34'),
(12, 14, 'image_uploaded', '127.0.0.1', '{\"note\": \"Seeded audit log #12\"}', '2025-12-11 03:01:34', '2025-12-12 00:01:34'),
(13, 15, 'maintenance_ticket_created', '127.0.0.1', '{\"note\": \"Seeded audit log #13\"}', '2025-12-06 15:01:34', '2025-12-12 00:01:34'),
(14, 16, 'inventory_stock_added', '127.0.0.1', '{\"note\": \"Seeded audit log #14\"}', '2025-12-04 14:01:34', '2025-12-12 00:01:34'),
(15, 17, 'booking_created', '127.0.0.1', '{\"note\": \"Seeded audit log #15\"}', '2025-12-06 21:01:34', '2025-12-12 00:01:34'),
(16, 18, 'order_created', '127.0.0.1', '{\"note\": \"Seeded audit log #16\"}', '2025-12-06 18:01:34', '2025-12-12 00:01:34'),
(17, 19, 'image_uploaded', '127.0.0.1', '{\"note\": \"Seeded audit log #17\"}', '2025-12-07 05:01:34', '2025-12-12 00:01:34'),
(18, 20, 'maintenance_ticket_created', '127.0.0.1', '{\"note\": \"Seeded audit log #18\"}', '2025-12-11 18:01:34', '2025-12-12 00:01:34'),
(19, 21, 'inventory_stock_added', '127.0.0.1', '{\"note\": \"Seeded audit log #19\"}', '2025-12-08 03:01:34', '2025-12-12 00:01:34'),
(20, 22, 'booking_created', '127.0.0.1', '{\"note\": \"Seeded audit log #20\"}', '2025-12-09 05:01:34', '2025-12-12 00:01:34'),
(21, 23, 'order_created', '127.0.0.1', '{\"note\": \"Seeded audit log #21\"}', '2025-12-08 17:01:34', '2025-12-12 00:01:34'),
(22, 3, 'image_uploaded', '127.0.0.1', '{\"note\": \"Seeded audit log #22\"}', '2025-12-04 20:01:34', '2025-12-12 00:01:34'),
(23, 1, 'maintenance_ticket_created', '127.0.0.1', '{\"note\": \"Seeded audit log #23\"}', '2025-12-08 09:01:34', '2025-12-12 00:01:34'),
(24, 2, 'inventory_stock_added', '127.0.0.1', '{\"note\": \"Seeded audit log #24\"}', '2025-12-04 11:01:34', '2025-12-12 00:01:34'),
(25, 4, 'booking_created', '127.0.0.1', '{\"note\": \"Seeded audit log #25\"}', '2025-12-05 03:01:34', '2025-12-12 00:01:34'),
(26, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:49:57', '2025-12-13 13:49:57'),
(27, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:51:04', '2025-12-13 13:51:04'),
(28, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:51:40', '2025-12-13 13:51:40'),
(29, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:52:28', '2025-12-13 13:52:28'),
(30, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:53:51', '2025-12-13 13:53:51'),
(31, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:53:54', '2025-12-13 13:53:54'),
(32, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:54:05', '2025-12-13 13:54:05'),
(33, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:54:13', '2025-12-13 13:54:13'),
(34, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:57:09', '2025-12-13 13:57:09'),
(35, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:58:46', '2025-12-13 13:58:46'),
(36, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:59:02', '2025-12-13 13:59:02'),
(37, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 13:59:52', '2025-12-13 13:59:52'),
(38, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 2}, \"status\": \"available\", \"room_number\": \"119\", \"room_type_id\": 4}}', '2025-12-13 14:00:37', '2025-12-13 14:00:37'),
(39, 2, 'room_created', '127.0.0.1', '{\"data\": {\"meta\": null, \"status\": \"occupied\", \"property_id\": \"1\", \"room_number\": \"125\", \"room_type_id\": \"1\"}}', '2025-12-13 14:11:49', '2025-12-13 14:11:49'),
(40, 2, 'room_created', '127.0.0.1', '{\"data\": {\"meta\": null, \"status\": \"maintenance\", \"property_id\": \"1\", \"room_number\": \"126\", \"room_type_id\": \"3\"}}', '2025-12-13 14:12:33', '2025-12-13 14:12:33'),
(41, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": [], \"status\": \"available\", \"room_number\": \"102\", \"room_type_id\": 2}}', '2025-12-13 14:37:39', '2025-12-13 14:37:39'),
(42, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": [], \"status\": \"maintenance\", \"room_number\": \"126\", \"room_type_id\": 3}}', '2025-12-13 14:37:48', '2025-12-13 14:37:48'),
(43, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": [], \"status\": \"maintenance\", \"room_number\": \"126\", \"room_type_id\": 3}}', '2025-12-13 14:38:08', '2025-12-13 14:38:08'),
(44, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 4}, \"status\": \"available\", \"room_number\": \"120\", \"room_type_id\": 5}}', '2025-12-13 14:51:47', '2025-12-13 14:51:47'),
(45, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": 5}, \"status\": \"available\", \"room_number\": \"120\", \"room_type_id\": 5}}', '2025-12-13 14:52:11', '2025-12-13 14:52:11'),
(46, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Chinonso Okafor\", \"role\": \"MD\", \"email\": \"ceo@email.com\", \"phone\": null, \"action_code\": null}}', '2025-12-13 15:18:25', '2025-12-13 15:18:25'),
(47, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Chinonso Okafor\", \"role\": \"MD\", \"email\": \"ceo@email.com\", \"phone\": null, \"action_code\": \"1234\"}}', '2025-12-13 15:19:42', '2025-12-13 15:19:42'),
(48, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Chinonso Okafor\", \"role\": \"MD\", \"email\": \"ceo@email.com\", \"phone\": null, \"action_code\": \"1234\"}}', '2025-12-13 15:19:50', '2025-12-13 15:19:50'),
(49, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Chinonso Okafor\", \"role\": \"MD\", \"email\": \"ceo@email.com\", \"phone\": null, \"action_code\": \"1234\"}}', '2025-12-13 15:19:59', '2025-12-13 15:19:59'),
(50, 2, 'staff_note_added', '127.0.0.1', '{\"staff_id\": 1}', '2025-12-13 15:23:45', '2025-12-13 15:23:45'),
(51, 2, 'staff_deleted', '127.0.0.1', '[]', '2025-12-14 00:08:44', '2025-12-14 00:08:44'),
(52, 2, 'staff_deleted', '127.0.0.1', '[]', '2025-12-14 00:09:08', '2025-12-14 00:09:08'),
(53, 2, 'staff_deleted', '127.0.0.1', '[]', '2025-12-14 00:14:20', '2025-12-14 00:14:20'),
(54, 2, 'staff_deleted', '127.0.0.1', '[]', '2025-12-14 00:14:33', '2025-12-14 00:14:33'),
(55, 2, 'staff_deleted', '127.0.0.1', '[]', '2025-12-14 00:17:12', '2025-12-14 00:17:12'),
(56, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Daniel Mensah\", \"role\": null, \"email\": \"inventory@email.com\", \"phone\": \"12345678\", \"action_code\": \"234ABC\"}}', '2025-12-14 00:39:30', '2025-12-14 00:39:30'),
(57, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Daniel Mensah\", \"role\": \"Staff\", \"email\": \"inventory@email.com\", \"phone\": \"12345678\", \"action_code\": \"123abc\"}}', '2025-12-14 00:39:55', '2025-12-14 00:39:55'),
(58, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Daniel Mensah\", \"role\": \"Inventory Manager\", \"email\": \"inventory@email.com\", \"phone\": null, \"action_code\": null}}', '2025-12-14 00:41:48', '2025-12-14 00:41:48'),
(59, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Daniel Mensah\", \"role\": \"Staff\", \"email\": \"inventory@email.com\", \"phone\": null, \"action_code\": null}}', '2025-12-14 00:43:31', '2025-12-14 00:43:31'),
(60, 2, 'staff_note_added', '127.0.0.1', '{\"staff_id\": 3}', '2025-12-14 01:09:58', '2025-12-14 01:09:58'),
(61, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"images\": [{}], \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 02:31:52', '2025-12-14 02:31:52'),
(62, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"images\": [{}, {}, {}], \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 02:37:51', '2025-12-14 02:37:51'),
(63, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\", \"remove_images\": [\"4\"]}}', '2025-12-14 03:04:53', '2025-12-14 03:04:53'),
(64, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:05:01', '2025-12-14 03:05:01'),
(65, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:05:08', '2025-12-14 03:05:08'),
(66, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:05:17', '2025-12-14 03:05:17'),
(67, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:16:21', '2025-12-14 03:16:21'),
(68, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:19:33', '2025-12-14 03:19:33'),
(69, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:19:39', '2025-12-14 03:19:39'),
(70, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\", \"primary_image_id\": \"20\"}}', '2025-12-14 03:24:10', '2025-12-14 03:24:10'),
(71, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\"}}', '2025-12-14 03:25:06', '2025-12-14 03:25:06'),
(72, 2, 'room_updated', '127.0.0.1', '{\"data\": {\"meta\": {\"floor\": \"1\"}, \"status\": \"available\", \"room_number\": \"101\", \"room_type_id\": \"1\", \"primary_image_id\": \"22\"}}', '2025-12-14 03:25:56', '2025-12-14 03:25:56'),
(73, 2, 'inventory_used', '127.0.0.1', '{\"change\": 2}', '2025-12-18 15:34:57', '2025-12-18 15:34:57'),
(74, 2, 'settings_updated', '127.0.0.1', '{\"data\": {\"logo\": null, \"banner\": null, \"site_name\": \"TariMoore Hotels\", \"contact_email\": \"info@tarimoorehotels.com\", \"contact_phone\": \"1122334455\", \"room_service_menu\": null}}', '2025-12-18 20:39:35', '2025-12-18 20:39:35'),
(75, 2, 'settings_updated', '127.0.0.1', '{\"data\": {\"logo\": null, \"banner\": null, \"site_name\": \"TariMoore Hotels\", \"contact_email\": \"info@tarimoorehotels.com\", \"contact_phone\": \"1122334455\", \"room_service_menu\": null}}', '2025-12-18 20:39:49', '2025-12-18 20:39:49'),
(76, 2, 'settings_updated', '127.0.0.1', '{\"data\": {\"logo\": null, \"banner\": null, \"site_name\": \"TariMoore Hotels\", \"contact_email\": \"info@tarimoorehotels.com\", \"contact_phone\": \"1122334455\", \"room_service_menu\": null}}', '2025-12-18 20:39:51', '2025-12-18 20:39:51'),
(77, 2, 'report_viewed', '127.0.0.1', '[]', '2025-12-19 14:27:47', '2025-12-19 14:27:47'),
(78, 2, 'report_viewed', '127.0.0.1', '[]', '2025-12-19 14:28:38', '2025-12-19 14:28:38'),
(79, 2, 'report_viewed', '127.0.0.1', '[]', '2025-12-19 14:30:49', '2025-12-19 14:30:49'),
(80, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Grace Adebayo\", \"role\": \"Staff\", \"email\": \"grace.ad@email.com\", \"phone\": \"+234801000000\", \"action_code\": null}}', '2025-12-20 12:45:31', '2025-12-20 12:45:31'),
(81, NULL, 'booking_created', '127.0.0.1', '{\"payload\": {\"adults\": 1, \"check_in\": \"2025-12-23\", \"children\": 0, \"quantity\": 1, \"check_out\": \"2025-12-24\", \"guest_email\": \"guest1@hotel.com\", \"guest_phone\": \"12345678\", \"total_price\": 0, \"room_type_id\": 1, \"special_requests\": \"Hot towels\"}}', '2025-12-21 05:03:37', '2025-12-21 05:03:37'),
(82, NULL, 'booking_created', '127.0.0.1', '{\"payload\": {\"adults\": 1, \"check_in\": \"2025-12-23\", \"children\": 0, \"quantity\": 1, \"check_out\": \"2025-12-24\", \"guest_email\": \"guest1@hotel.com\", \"guest_phone\": \"12345678\", \"total_price\": 0, \"room_type_id\": 1, \"special_requests\": \"Hot towels\"}}', '2025-12-21 05:04:03', '2025-12-21 05:04:03'),
(83, NULL, 'booking_created', '127.0.0.1', '{\"payload\": {\"adults\": 1, \"check_in\": \"2025-12-24\", \"children\": 0, \"quantity\": 1, \"check_out\": \"2025-12-28\", \"guest_email\": \"guest2@email.com\", \"guest_phone\": \"12222222\", \"total_price\": 0, \"room_type_id\": 5, \"special_requests\": \"Hot Bath\"}}', '2025-12-21 05:10:46', '2025-12-21 05:10:46'),
(84, NULL, 'booking_created', '127.0.0.1', '{\"payload\": {\"adults\": 1, \"check_in\": \"2025-12-22\", \"children\": 0, \"quantity\": 1, \"check_out\": \"2025-12-24\", \"guest_name\": \"Guest Three\", \"guest_email\": \"guest3@gmail.com\", \"guest_phone\": \"33333333\", \"room_type_id\": 1, \"total_amount\": 0, \"special_requests\": \"Ice bath\"}}', '2025-12-21 05:38:52', '2025-12-21 05:38:52'),
(85, NULL, 'booking_created', '127.0.0.1', '{\"payload\": {\"adults\": 1, \"check_in\": \"2025-12-23\", \"children\": 0, \"quantity\": 1, \"check_out\": \"2025-12-24\", \"guest_name\": \"GUEST fOUR\", \"guest_email\": \"guest@emails.com\", \"guest_phone\": \"1111111\", \"room_type_id\": 1, \"total_amount\": 35000, \"special_requests\": null}}', '2025-12-21 05:44:54', '2025-12-21 05:44:54'),
(86, NULL, 'booking_created', '127.0.0.1', '{\"payload\": {\"adults\": 1, \"check_in\": \"2025-12-24\", \"children\": 0, \"quantity\": 1, \"check_out\": \"2025-12-26\", \"guest_name\": \"Guest Five\", \"guest_email\": \"guest@email.com\", \"guest_phone\": \"55555\", \"nightly_rate\": \"35000.00\", \"room_type_id\": 1, \"total_amount\": 70000, \"special_requests\": null}}', '2025-12-23 14:55:04', '2025-12-23 14:55:04'),
(87, NULL, 'booking_created', '127.0.0.1', '{\"rooms\": [\"101\", \"106\"]}', '2025-12-23 15:23:02', '2025-12-23 15:23:02'),
(88, NULL, 'booking_created', '127.0.0.1', '{\"rooms\": [\"111\", \"116\", \"121\"]}', '2025-12-23 15:24:38', '2025-12-23 15:24:38'),
(89, NULL, 'booking_created', '127.0.0.1', '{\"rooms\": [\"125\", \"101\", \"111\"]}', '2025-12-23 15:25:34', '2025-12-23 15:25:34'),
(90, NULL, 'booking_created', '127.0.0.1', '{\"rooms\": [\"121\"]}', '2025-12-23 15:46:21', '2025-12-23 15:46:21'),
(91, 2, 'report_viewed', '127.0.0.1', '[]', '2025-12-23 15:58:06', '2025-12-23 15:58:06'),
(92, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Bolaji Ojo\", \"role\": \"frontend\", \"email\": \"bolaji.oj@email.com\", \"phone\": \"+234801000002\", \"action_code\": null}}', '2025-12-23 22:22:34', '2025-12-23 22:22:34'),
(93, 6, 'booking_extended', '127.0.0.1', '{\"by\": null, \"new_check_out\": \"2025-12-27\", \"old_check_out\": \"2025-12-26T00:00:00.000000Z\"}', '2025-12-25 01:09:51', '2025-12-25 01:09:51'),
(94, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-27T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:37.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}, \"before\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-27T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest\", \"updated_at\": \"2025-12-23T16:24:38.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}}', '2025-12-25 12:08:37', '2025-12-25 12:08:37'),
(95, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}, \"before\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-27T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:37.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}}', '2025-12-25 12:08:45', '2025-12-25 12:08:45'),
(96, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}, \"before\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}}', '2025-12-25 12:09:01', '2025-12-25 12:09:01'),
(97, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}, \"before\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}}', '2025-12-25 12:09:17', '2025-12-25 12:09:17'),
(98, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}, \"before\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}}', '2025-12-25 12:09:50', '2025-12-25 12:09:50'),
(99, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"rooms\": [{\"id\": 11, \"meta\": {\"floor\": 2}, \"pivot\": {\"room_id\": 11, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"111\", \"room_type_id\": 1}, {\"id\": 16, \"meta\": {\"floor\": 2}, \"pivot\": {\"room_id\": 16, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"116\", \"room_type_id\": 1}, {\"id\": 21, \"meta\": {\"floor\": 3}, \"pivot\": {\"room_id\": 21, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"121\", \"room_type_id\": 1}], \"adults\": 1, \"guests\": 1, \"status\": \"active\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:26:49.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}, \"before\": {\"id\": 20, \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:08:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null}}', '2025-12-25 12:26:49', '2025-12-25 12:26:49'),
(100, NULL, 'booking_created', '127.0.0.1', '{\"rooms\": [\"111\"]}', '2025-12-25 12:42:27', '2025-12-25 12:42:27'),
(101, 6, 'booking_checked_in', '127.0.0.1', '{\"by\": null, \"rooms\": [\"116\"]}', '2025-12-25 23:11:59', '2025-12-25 23:11:59'),
(102, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 19, \"rooms\": [{\"id\": 1, \"meta\": {\"floor\": \"1\"}, \"pivot\": {\"room_id\": 1, \"booking_id\": 19, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-14T03:31:52.000000Z\", \"property_id\": 1, \"room_number\": \"101\", \"room_type_id\": 1}, {\"id\": 6, \"meta\": {\"floor\": 1}, \"pivot\": {\"room_id\": 6, \"booking_id\": 19, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"106\", \"room_type_id\": 1}], \"adults\": 1, \"guests\": 1, \"status\": \"confirmed\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"2\", \"check_out\": \"2025-12-27T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:23:02.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:08:02\", \"guest_name\": \"Guest One\", \"updated_at\": \"2025-12-26T00:53:36.000000Z\", \"guest_email\": \"guest@email.com\", \"guest_phone\": \"1111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC1E6E9D07\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"140000.00\", \"special_requests\": \"Hot Towels\"}, \"before\": {\"id\": 19, \"rooms\": [{\"id\": 1, \"meta\": {\"floor\": \"1\"}, \"pivot\": {\"room_id\": 1, \"booking_id\": 19, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-14T03:31:52.000000Z\", \"property_id\": 1, \"room_number\": \"101\", \"room_type_id\": 1}, {\"id\": 6, \"meta\": {\"floor\": 1}, \"pivot\": {\"room_id\": 6, \"booking_id\": 19, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"106\", \"room_type_id\": 1}], \"adults\": 1, \"guests\": 1, \"status\": \"pending_payment\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"2\", \"check_out\": \"2025-12-27T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:23:02.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:08:02\", \"guest_name\": \"Guest One\", \"updated_at\": \"2025-12-25T02:09:51.000000Z\", \"guest_email\": \"guest@email.com\", \"guest_phone\": \"1111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC1E6E9D07\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"140000.00\", \"special_requests\": \"Hot Towels\"}}', '2025-12-25 23:53:36', '2025-12-25 23:53:36'),
(103, 6, 'booking_checked_in', '127.0.0.1', '{\"by\": 6, \"rooms\": [\"101\"]}', '2025-12-26 01:00:38', '2025-12-26 01:00:38'),
(104, 6, 'booking_checked_in', '127.0.0.1', '{\"by\": 6, \"rooms\": [\"106\"]}', '2025-12-26 01:00:54', '2025-12-26 01:00:54'),
(105, 2, 'staff_created', '127.0.0.1', '{\"role\": \"laundry\"}', '2025-12-28 02:13:16', '2025-12-28 02:13:16'),
(106, 2, 'staff_deleted', '127.0.0.1', '[]', '2025-12-28 02:20:22', '2025-12-28 02:20:22'),
(107, 2, 'staff_created', '127.0.0.1', '{\"role\": \"laundry\"}', '2025-12-28 02:52:15', '2025-12-28 02:52:15'),
(108, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Laundry\", \"role\": \"laundry\", \"email\": \"laundry@email.com\", \"phone\": \"12345678\", \"action_code\": null}}', '2025-12-28 03:02:17', '2025-12-28 03:02:17'),
(109, 2, 'staff_updated', '127.0.0.1', '{\"data\": {\"name\": \"Laundry\", \"role\": \"laundry\", \"email\": \"laundry@email.com\", \"phone\": \"12345678\", \"password\": \"11111111\", \"action_code\": null}}', '2025-12-28 03:03:36', '2025-12-28 03:03:36'),
(110, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 22, \"rooms\": [{\"id\": 47, \"meta\": {\"floor\": 3}, \"pivot\": {\"room_id\": 47, \"booking_id\": 22, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-20T14:19:41.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-20T14:19:41.000000Z\", \"property_id\": 1, \"room_number\": \"121\", \"room_type_id\": 1}], \"adults\": 2, \"guests\": 1, \"status\": \"active\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"1\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:46:21.000000Z\", \"deleted_at\": null, \"expires_at\": null, \"guest_name\": \"guest two\", \"updated_at\": \"2025-12-28T04:39:35.000000Z\", \"guest_email\": \"guest@email.com\", \"guest_phone\": \"1111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC75D39F46\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"70000.00\", \"special_requests\": \"Hot towels\", \"checked_in_rooms_count\": 0}, \"before\": {\"id\": 22, \"rooms\": [{\"id\": 47, \"meta\": {\"floor\": 3}, \"pivot\": {\"room_id\": 47, \"booking_id\": 22, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-20T14:19:41.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-20T14:19:41.000000Z\", \"property_id\": 1, \"room_number\": \"121\", \"room_type_id\": 1}], \"adults\": 2, \"guests\": 1, \"status\": \"active\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"1\", \"check_out\": \"2025-12-26T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:46:21.000000Z\", \"deleted_at\": null, \"expires_at\": null, \"guest_name\": \"guest two\", \"updated_at\": \"2025-12-23T16:46:34.000000Z\", \"guest_email\": \"guest@email.com\", \"guest_phone\": \"1111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC75D39F46\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"70000.00\", \"special_requests\": \"Hot towels\", \"checked_in_rooms_count\": 0}}', '2025-12-28 03:39:35', '2025-12-28 03:39:35'),
(111, 6, 'booking_updated', '127.0.0.1', '{\"after\": {\"id\": 20, \"rooms\": [{\"id\": 11, \"meta\": {\"floor\": 2}, \"pivot\": {\"room_id\": 11, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"111\", \"room_type_id\": 1}, {\"id\": 16, \"meta\": {\"floor\": 2}, \"pivot\": {\"room_id\": 16, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"116\", \"room_type_id\": 1}, {\"id\": 21, \"meta\": {\"floor\": 3}, \"pivot\": {\"room_id\": 21, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"121\", \"room_type_id\": 1}], \"adults\": 1, \"guests\": 1, \"status\": \"active\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-28T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-28T04:39:45.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null, \"checked_in_rooms_count\": 0}, \"before\": {\"id\": 20, \"rooms\": [{\"id\": 11, \"meta\": {\"floor\": 2}, \"pivot\": {\"room_id\": 11, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"111\", \"room_type_id\": 1}, {\"id\": 16, \"meta\": {\"floor\": 2}, \"pivot\": {\"room_id\": 16, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"116\", \"room_type_id\": 1}, {\"id\": 21, \"meta\": {\"floor\": 3}, \"pivot\": {\"room_id\": 21, \"booking_id\": 20, \"created_at\": null, \"updated_at\": null, \"checked_in_at\": null, \"rate_override\": null, \"checked_out_at\": null, \"booking_rooms.status\": \"active\"}, \"status\": \"available\", \"created_at\": \"2025-12-12T01:01:26.000000Z\", \"deleted_at\": null, \"updated_at\": \"2025-12-12T01:01:26.000000Z\", \"property_id\": 1, \"room_number\": \"121\", \"room_type_id\": 1}], \"adults\": 1, \"guests\": 1, \"status\": \"active\", \"details\": null, \"room_id\": null, \"user_id\": null, \"check_in\": \"2025-12-24T00:00:00.000000Z\", \"children\": 0, \"quantity\": \"3\", \"check_out\": \"2025-12-27T00:00:00.000000Z\", \"created_at\": \"2025-12-23T16:24:38.000000Z\", \"deleted_at\": null, \"expires_at\": \"2025-12-23 17:09:38\", \"guest_name\": \"Guest Status test\", \"updated_at\": \"2025-12-25T13:26:49.000000Z\", \"guest_email\": \"guet@email.com\", \"guest_phone\": \"11111\", \"property_id\": 1, \"booking_code\": \"BKG-694AC24618D85\", \"nightly_rate\": \"0.00\", \"room_type_id\": 1, \"total_amount\": \"315000.00\", \"special_requests\": null, \"checked_in_rooms_count\": 0}}', '2025-12-28 03:39:45', '2025-12-28 03:39:45'),
(112, 6, 'booking_created', '127.0.0.1', '{\"quantity\": 1}', '2025-12-28 03:45:39', '2025-12-28 03:45:39'),
(113, 6, 'booking_confirmed', '127.0.0.1', '[]', '2025-12-28 03:45:41', '2025-12-28 03:45:41'),
(114, 6, 'booking_checked_in', '127.0.0.1', '{\"by\": 6, \"rooms\": [\"102\"]}', '2025-12-28 03:46:12', '2025-12-28 03:46:12'),
(115, NULL, 'booking_created', '105.113.8.212', '{\"quantity\":1}', '2025-12-30 18:49:35', '2025-12-30 18:49:35'),
(116, NULL, 'booking_confirmed', '105.113.8.212', '[]', '2025-12-30 18:49:37', '2025-12-30 18:49:37'),
(117, 2, 'staff_created', '105.113.41.219', '{\"role\":\"frontdesk\"}', '2026-01-01 04:05:34', '2026-01-01 04:05:34'),
(118, 2, 'staff_created', '105.113.41.219', '{\"role\":\"staff\"}', '2026-01-01 04:06:11', '2026-01-01 04:06:11'),
(119, 2, 'staff_created', '105.113.41.219', '{\"role\":\"bar\"}', '2026-01-01 04:08:16', '2026-01-01 04:08:16'),
(120, 2, 'staff_created', '105.113.41.219', '{\"role\":\"kitchen\"}', '2026-01-01 04:08:48', '2026-01-01 04:08:48'),
(121, NULL, 'booking_created', '105.112.101.44', '{\"quantity\":1}', '2026-01-02 09:56:52', '2026-01-02 09:56:52'),
(122, NULL, 'booking_confirmed', '105.112.101.44', '[]', '2026-01-02 09:57:04', '2026-01-02 09:57:04'),
(123, NULL, 'booking_created', '105.112.213.141', '{\"quantity\":1}', '2026-01-02 23:10:33', '2026-01-02 23:10:33'),
(124, NULL, 'booking_created', '105.112.213.141', '{\"quantity\":1}', '2026-01-03 04:54:54', '2026-01-03 04:54:54'),
(125, NULL, 'booking_confirmed', '105.112.213.141', '[]', '2026-01-03 04:55:01', '2026-01-03 04:55:01'),
(126, NULL, 'booking_created', '105.112.179.97', '{\"quantity\":1}', '2026-01-04 08:49:25', '2026-01-04 08:49:25'),
(127, NULL, 'booking_confirmed', '105.112.179.97', '[]', '2026-01-04 08:49:30', '2026-01-04 08:49:30'),
(128, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"1\",\"name\":\"Major AB Lawal\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:04:05', '2026-01-09 16:04:05'),
(129, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"1\",\"name\":\"Col. U. Adamu\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:04:37', '2026-01-09 16:04:37'),
(130, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"1\",\"name\":\"SGT Komolafe\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:05:16', '2026-01-09 16:05:16'),
(131, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"2\",\"name\":\"SGT Olabiyi\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:05:46', '2026-01-09 16:05:46'),
(132, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"2\",\"name\":\"Caleb\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:06:00', '2026-01-09 16:06:00'),
(133, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"2\",\"name\":\"LCPL Oyedele Solese\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:06:25', '2026-01-09 16:06:25'),
(134, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"2\",\"name\":\"Richard\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:06:46', '2026-01-09 16:06:46'),
(135, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"2\",\"name\":\"Gold\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:07:12', '2026-01-09 16:07:12'),
(136, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"PTE Ahmed\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:07:29', '2026-01-09 16:07:29'),
(137, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"PTE Adeosun\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:07:50', '2026-01-09 16:07:50'),
(138, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"SSGT Shehu\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:08:09', '2026-01-09 16:08:09'),
(139, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"CAPT. Yau\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:08:40', '2026-01-09 16:08:40'),
(140, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"PTE Deyon\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:09:06', '2026-01-09 16:09:06'),
(141, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"SGT Effiong Asuquo\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:09:30', '2026-01-09 16:09:30'),
(142, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"CPL U.L. Ndifreke\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:09:59', '2026-01-09 16:09:59'),
(143, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"3\",\"name\":\"Engr. Emeka Nnedu\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:10:30', '2026-01-09 16:10:30'),
(144, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"4\",\"name\":\"Barrister C. Okparaolu\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:11:33', '2026-01-09 16:11:33'),
(145, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"4\",\"name\":\"PTE Mohammed\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:12:02', '2026-01-09 16:12:02'),
(146, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"4\",\"name\":\"Isah\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:12:24', '2026-01-09 16:12:24'),
(147, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"4\",\"name\":\"Engr. Temibere Tobore\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:12:58', '2026-01-09 16:12:58'),
(148, 2, 'room_created', '102.88.115.90', '{\"data\":{\"property_id\":\"1\",\"room_type_id\":\"4\",\"name\":\"Inua Eyet Ikot\",\"status\":\"available\",\"meta\":null}}', '2026-01-09 16:13:21', '2026-01-09 16:13:21'),
(149, 2, 'settings_updated', '105.113.12.201', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":{},\"banner\":null,\"room_service_menu\":null}}', '2026-01-21 09:05:27', '2026-01-21 09:05:27'),
(150, 2, 'settings_updated', '105.113.12.201', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":{},\"banner\":null,\"room_service_menu\":null}}', '2026-01-21 09:41:30', '2026-01-21 09:41:30'),
(151, 2, 'settings_updated', '105.113.12.201', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":{},\"banner\":null,\"room_service_menu\":null}}', '2026-01-21 09:42:49', '2026-01-21 09:42:49'),
(152, 2, 'settings_updated', '105.113.12.201', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":{},\"banner\":null,\"room_service_menu\":null}}', '2026-01-21 09:46:56', '2026-01-21 09:46:56'),
(153, 2, 'settings_updated', '105.113.12.201', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":{},\"banner\":null,\"room_service_menu\":null}}', '2026-01-21 09:50:33', '2026-01-21 09:50:33'),
(154, NULL, 'booking_created', '102.90.118.111', '{\"quantity\":1}', '2026-01-22 12:29:27', '2026-01-22 12:29:27'),
(155, NULL, 'booking_confirmed', '102.90.118.111', '[]', '2026-01-22 12:29:38', '2026-01-22 12:29:38'),
(156, NULL, 'booking_created', '102.90.101.21', '{\"quantity\":1}', '2026-01-22 12:40:24', '2026-01-22 12:40:24'),
(157, NULL, 'booking_confirmed', '102.90.101.21', '[]', '2026-01-22 12:40:33', '2026-01-22 12:40:33'),
(158, 2, 'settings_updated', '102.90.99.206', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":null,\"banner\":null,\"room_service_menu\":null,\"site_whatsapp\":\"+2347083095684\"}}', '2026-01-31 14:55:30', '2026-01-31 14:55:30'),
(159, 2, 'settings_updated', '102.90.99.206', '{\"data\":{\"site_name\":\"Moore Life Beach Resort\",\"contact_email\":\"info@tarimoorehotels.com\",\"contact_phone\":\"1122334455\",\"logo\":null,\"banner\":null,\"room_service_menu\":null,\"site_whatsapp\":\"+2347083095684\"}}', '2026-01-31 14:55:33', '2026-01-31 14:55:33'),
(160, NULL, 'booking_created', '2605:59c0:efc:1908:4057:e11b:78bc:9c0f', '{\"quantity\":1}', '2026-02-04 14:43:32', '2026-02-04 14:43:32'),
(161, NULL, 'booking_created', '105.127.7.37', '{\"quantity\":1}', '2026-02-04 19:42:50', '2026-02-04 19:42:50'),
(162, NULL, 'booking_created', '105.127.7.37', '{\"quantity\":1}', '2026-02-04 19:54:32', '2026-02-04 19:54:32'),
(163, NULL, 'booking_created', '105.127.7.37', '{\"quantity\":1}', '2026-02-04 20:28:32', '2026-02-04 20:28:32'),
(164, 26, 'booking_created', '105.127.7.37', '{\"quantity\":1}', '2026-02-04 21:19:26', '2026-02-04 21:19:26'),
(165, 26, 'booking_updated', '2605:59c1:19e4:8b08:8e2:a8f2:30f3:e38a', '{\"before\":{\"id\":31,\"property_id\":1,\"room_id\":null,\"nightly_rate\":\"0.00\",\"guest_name\":\"Ttt\",\"user_id\":null,\"booking_code\":\"BKG-69721AB880607\",\"check_in\":\"2026-01-21T23:00:00.000000Z\",\"check_out\":\"2026-01-23T23:00:00.000000Z\",\"guests\":1,\"total_amount\":\"300000.00\",\"status\":\"confirmed\",\"payment_method\":\"offline\",\"payment_status\":\"pending\",\"details\":null,\"created_at\":\"2026-01-22T11:40:24.000000Z\",\"updated_at\":\"2026-01-22T11:40:33.000000Z\",\"deleted_at\":null,\"expires_at\":null,\"adults\":1,\"children\":0,\"special_requests\":null,\"guest_email\":\"ttrr@fhgd.com\",\"guest_phone\":\"9878999\",\"room_type_id\":1,\"quantity\":\"1\",\"checked_in_rooms_count\":0,\"rooms\":[]},\"after\":{\"id\":31,\"property_id\":1,\"room_id\":null,\"nightly_rate\":\"0.00\",\"guest_name\":\"Ttt\",\"user_id\":null,\"booking_code\":\"BKG-69721AB880607\",\"check_in\":\"2026-01-20T23:00:00.000000Z\",\"check_out\":\"2026-04-29T23:00:00.000000Z\",\"guests\":1,\"total_amount\":\"300000.00\",\"status\":\"confirmed\",\"payment_method\":\"offline\",\"payment_status\":\"pending\",\"details\":null,\"created_at\":\"2026-01-22T11:40:24.000000Z\",\"updated_at\":\"2026-03-23T23:35:24.000000Z\",\"deleted_at\":null,\"expires_at\":null,\"adults\":1,\"children\":0,\"special_requests\":null,\"guest_email\":\"ttrr@fhgd.com\",\"guest_phone\":\"9878999\",\"room_type_id\":1,\"quantity\":\"1\",\"checked_in_rooms_count\":0,\"rooms\":[]}}', '2026-03-24 00:35:24', '2026-03-24 00:35:24');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip_address`, `metadata`, `created_at`, `updated_at`) VALUES
(166, 26, 'booking_updated', '2605:59c1:19e4:8b08:8e2:a8f2:30f3:e38a', '{\"before\":{\"id\":31,\"property_id\":1,\"room_id\":null,\"nightly_rate\":\"0.00\",\"guest_name\":\"Ttt\",\"user_id\":null,\"booking_code\":\"BKG-69721AB880607\",\"check_in\":\"2026-01-20T23:00:00.000000Z\",\"check_out\":\"2026-04-29T23:00:00.000000Z\",\"guests\":1,\"total_amount\":\"300000.00\",\"status\":\"confirmed\",\"payment_method\":\"offline\",\"payment_status\":\"pending\",\"details\":null,\"created_at\":\"2026-01-22T11:40:24.000000Z\",\"updated_at\":\"2026-03-23T23:35:24.000000Z\",\"deleted_at\":null,\"expires_at\":null,\"adults\":1,\"children\":0,\"special_requests\":null,\"guest_email\":\"ttrr@fhgd.com\",\"guest_phone\":\"9878999\",\"room_type_id\":1,\"quantity\":\"1\",\"checked_in_rooms_count\":0,\"rooms\":[]},\"after\":{\"id\":31,\"property_id\":1,\"room_id\":null,\"nightly_rate\":\"0.00\",\"guest_name\":\"John Doe\",\"user_id\":null,\"booking_code\":\"BKG-69721AB880607\",\"check_in\":\"2026-01-19T23:00:00.000000Z\",\"check_out\":\"2026-04-28T23:00:00.000000Z\",\"guests\":1,\"total_amount\":\"300000.00\",\"status\":\"confirmed\",\"payment_method\":\"offline\",\"payment_status\":\"pending\",\"details\":null,\"created_at\":\"2026-01-22T11:40:24.000000Z\",\"updated_at\":\"2026-03-23T23:35:49.000000Z\",\"deleted_at\":null,\"expires_at\":null,\"adults\":1,\"children\":0,\"special_requests\":null,\"guest_email\":\"ttrr@fhgd.com\",\"guest_phone\":\"9878999\",\"room_type_id\":1,\"quantity\":\"1\",\"checked_in_rooms_count\":0,\"rooms\":[]}}', '2026-03-24 00:35:49', '2026-03-24 00:35:49'),
(167, 26, 'booking_checked_in', '2605:59c1:19e4:8b08:8e2:a8f2:30f3:e38a', '{\"rooms\":[\"Major AB Lawal\"],\"by\":26}', '2026-03-24 00:36:38', '2026-03-24 00:36:38'),
(168, NULL, 'booking_created', '102.90.100.189', '{\"quantity\":1}', '2026-03-25 09:34:21', '2026-03-25 09:34:21'),
(169, NULL, 'booking_created', '102.88.113.38', '{\"quantity\":1}', '2026-04-06 15:27:49', '2026-04-06 15:27:49'),
(170, NULL, 'booking_confirmed', '102.88.113.38', '[]', '2026-04-06 15:28:07', '2026-04-06 15:28:07'),
(171, NULL, 'booking_created', '197.211.57.3', '{\"quantity\":1}', '2026-04-06 21:29:00', '2026-04-06 21:29:00');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nightly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `guest_name` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `booking_code` varchar(255) NOT NULL COMMENT 'Public booking reference',
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `guests` int(11) NOT NULL DEFAULT 1,
  `total_amount` decimal(12,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending payment',
  `payment_method` varchar(255) NOT NULL DEFAULT 'offline',
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata' CHECK (json_valid(`details`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `adults` int(11) DEFAULT NULL,
  `children` int(11) DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `guest_email` varchar(255) DEFAULT NULL,
  `guest_phone` varchar(255) DEFAULT NULL,
  `room_type_id` int(11) DEFAULT NULL,
  `quantity` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `property_id`, `room_id`, `nightly_rate`, `guest_name`, `user_id`, `booking_code`, `check_in`, `check_out`, `guests`, `total_amount`, `status`, `payment_method`, `payment_status`, `details`, `created_at`, `updated_at`, `deleted_at`, `expires_at`, `adults`, `children`, `special_requests`, `guest_email`, `guest_phone`, `room_type_id`, `quantity`) VALUES
(30, 1, NULL, 0.00, 'Ms. Rachel', NULL, 'BKG-6972182721BCB', '2026-01-26', '2026-01-31', 1, 750000.00, 'confirmed', 'offline', 'pending', NULL, '2026-01-22 12:29:27', '2026-01-22 12:29:38', NULL, NULL, 1, 0, NULL, 'rachelandrew827@gmail.com', '09032492212', 1, '1'),
(31, 1, NULL, 0.00, 'John Doe', NULL, 'BKG-69721AB880607', '2026-01-20', '2026-04-29', 1, 300000.00, 'active', 'offline', 'pending', NULL, '2026-01-22 12:40:24', '2026-03-24 00:35:49', NULL, NULL, 1, 0, NULL, 'ttrr@fhgd.com', '9878999', 1, '1'),
(32, 1, NULL, 0.00, 'John og', NULL, 'BKG-69834D04436B3', '2026-02-04', '2026-02-09', 1, 358750.00, 'pending_payment', 'offline', 'pending', NULL, '2026-02-04 14:43:32', '2026-02-04 14:43:32', NULL, '2026-02-04 15:28:32', 1, 0, NULL, 'john@gmail.com', '08135239405', 4, '1'),
(33, 1, NULL, 0.00, 'webhook', NULL, 'BKG-6983932A186F4', '2026-02-04', '2026-02-24', 1, 2050000.00, 'pending_payment', 'offline', 'pending', NULL, '2026-02-04 19:42:50', '2026-02-04 19:42:50', NULL, '2026-02-04 20:27:50', 1, 0, NULL, 'wb@hk.com', '11111111', 2, '1'),
(34, 1, NULL, 0.00, 'wbhk2', NULL, 'BKG-698395E87CA5C', '2026-02-04', '2026-02-23', 1, 1947500.00, 'pending_payment', 'offline', 'pending', NULL, '2026-02-04 19:54:32', '2026-02-04 19:54:32', NULL, '2026-02-04 20:39:32', 1, 0, NULL, 'wb@hk.com', '11111111', 2, '1'),
(35, 1, NULL, 0.00, 'wbhk3', NULL, 'BKG-69839DE0D4C85', '2026-02-04', '2026-02-24', 1, 2050000.00, 'pending_payment', 'offline', 'pending', NULL, '2026-02-04 20:28:32', '2026-02-04 20:28:32', NULL, '2026-02-04 21:13:32', 2, 0, NULL, 'wb@hk.com', '11111111', 2, '1'),
(36, 1, NULL, 0.00, 'tapi', NULL, 'BKG-6983A9CEA9D30', '2026-02-04', '2026-02-20', 1, 1148000.00, 'pending_payment', 'offline', 'pending', NULL, '2026-02-04 21:19:26', '2026-02-04 21:19:26', NULL, '2026-02-04 22:04:26', 1, 0, NULL, 'tapi@tap.com', '11111111', 4, '1'),
(37, 1, NULL, 0.00, 'Caleb Chukwumati', NULL, 'BKG-69C39E0D82B16', '2026-03-31', '2026-04-04', 1, 287000.00, 'pending_payment', 'offline', 'pending', NULL, '2026-03-25 09:34:21', '2026-03-25 09:34:21', NULL, '2026-03-25 10:19:21', 1, 0, NULL, 'calebchukwumati@gmail.com', '07039758756', 4, '1'),
(38, 1, NULL, 0.00, 'Solace Aanuoluwapor', NULL, 'BKG-69D3C2E58BF91', '2026-04-15', '2026-04-16', 1, 71750.00, 'confirmed', 'offline', 'pending', NULL, '2026-04-06 15:27:49', '2026-04-06 15:28:07', NULL, NULL, 1, 0, NULL, 'aanuoluwapo97@gmail.com', '08116233391', 4, '1'),
(39, 1, NULL, 0.00, 'cassius akpan', NULL, 'BKG-69D4178C4DA9F', '2026-04-08', '2026-04-10', 1, 164000.00, 'pending_payment', 'offline', 'pending', NULL, '2026-04-06 21:29:00', '2026-04-06 21:29:00', NULL, '2026-04-06 22:14:00', 2, 0, NULL, 'ediomoakpan@12345', '09051732325', 3, '1');

-- --------------------------------------------------------

--
-- Table structure for table `booking_rooms`
--

CREATE TABLE `booking_rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rate_override` decimal(12,2) DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_out_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_rooms`
--

INSERT INTO `booking_rooms` (`id`, `booking_id`, `room_id`, `created_at`, `updated_at`, `rate_override`, `checked_in_at`, `checked_out_at`, `status`) VALUES
(15, 31, 51, '2026-03-24 00:36:38', '2026-03-24 00:36:38', NULL, '2026-03-24 00:36:38', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `charges`
--

CREATE TABLE `charges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `payment_mode` enum('prepaid','pay_on_delivery','postpaid') NOT NULL DEFAULT 'postpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'manual',
  `charge_date` date DEFAULT NULL,
  `billable_type` varchar(255) DEFAULT NULL,
  `billable_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_inventory_templates`
--

CREATE TABLE `cleaning_inventory_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_type_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cleaning_logs`
--

CREATE TABLE `cleaning_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contents`
--

CREATE TABLE `contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `type` enum('text','html','image') NOT NULL DEFAULT 'text',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contents`
--

INSERT INTO `contents` (`id`, `key`, `value`, `type`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'site.name', 'MooreLife Hotels and Beach Resort', 'text', 2, '2026-01-09 16:14:48', '2026-01-21 10:04:26'),
(2, 'home.hero.title', 'A Private Beachfront Escape', 'text', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(3, 'home.hero.subtitle', 'Luxury, privacy and unforgettable moments by the ocean.', 'text', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(4, 'home.hero.image', '/storage/content/B5rVNlg8fT182u2un9t70n4xsjPrB52rh3E23DnF.jpg', 'image', 2, '2026-01-09 16:14:48', '2026-01-14 18:50:55'),
(5, 'home.experience.text', 'Nestled along pristine shores, our resort blends modern comfort with coastal serenity. From golden sunsets to vibrant nightlife, every moment is designed to elevate your stay.', 'text', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(6, 'amenities.content', '\r\n                <ul>\r\n                    <li>Private beach access</li>\r\n                    <li>Luxury swimming pool</li>\r\n                    <li>Club & night lounge</li>\r\n                    <li>Restaurant & cocktail bar</li>\r\n                    <li>24/7 front desk service</li>\r\n                    <li>Daily housekeeping</li>\r\n                    <li>Laundry & dry-cleaning</li>\r\n                    <li>High-speed Wi-Fi</li>\r\n                    <li>Secure on-site parking</li>\r\n                </ul>\r\n            ', 'html', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(7, 'club.description', '<p><strong>Our club and lounge redefine nightlife by the sea. Featuring curated music, premium cocktails and an exclusive atmosphere, it is the heartbeat of the resort after sunset.</strong></p><p><strong>Dress code applies. Open weekends and selected weekdays.</strong></p>', 'html', 2, '2026-01-09 16:14:48', '2026-01-19 17:33:57'),
(8, 'policies.content', '<h3><strong>Check-in &amp; Check-out</strong></h3><p>Check-in from 2:00 PM. Check-out by 12:00 PM.</p><h3><strong>Cancellation Policy</strong></h3><p>Cancellations made 48 hours before arrival are fully refundable.</p><h3><strong>Payments</strong></h3><p>All bookings must be guaranteed. Outstanding bills must be cleared before checkout.</p><h3><strong>Guest Conduct</strong></h3><p>Guests are expected to respect other guests and hotel property.</p><h3><strong>Club Rules</strong></h3><p>Management reserves the right to refuse entry.</p>', 'html', 2, '2026-01-09 16:14:48', '2026-01-19 17:41:16'),
(9, 'footer.about', 'A luxury beachfront resort offering comfort, nightlife and exceptional hospitality.', 'text', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(10, 'footer.contact', 'Phone: +234 XXX XXX XXXX | Email: reservations@azuresands.com', 'text', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(11, 'footer.location', 'Beachfront Road, Coastal City, Nigeria', 'text', NULL, '2026-01-09 16:14:48', '2026-01-09 16:14:48'),
(12, 'gallery.bar.description', 'Crafted cocktails and panoramic horizons. Our rooftop bar is a sanctuary for the senses.', 'text', NULL, NULL, NULL),
(13, 'gallery.club.description', 'Where the pulse of the ocean meets the rhythm of the night.', 'text', NULL, NULL, NULL),
(14, 'gallery.pool.description', 'Mirror-still waters designed to blur the line between the sky and the sea.', 'text', NULL, NULL, NULL),
(15, 'gallery.beach.description', 'Pristine white sands and the gentle lull of the Atlantic at your doorstep.', 'text', NULL, NULL, NULL),
(16, 'gallery.resort.description', 'Exploring the grand architecture and hidden corners of MooreLife.', 'text', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Admin', NULL, NULL),
(2, 'Front Desk', NULL, NULL),
(3, 'Security', NULL, NULL),
(4, 'Cleaning', NULL, NULL),
(5, 'Kitchen', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `venue` varchar(255) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `ticket_sales_start` datetime DEFAULT NULL,
  `ticket_sales_end` datetime DEFAULT NULL,
  `max_tickets_per_person` int(11) NOT NULL DEFAULT 10,
  `has_table_reservations` tinyint(1) NOT NULL DEFAULT 0,
  `table_capacity` int(11) DEFAULT NULL,
  `table_price` decimal(8,2) DEFAULT NULL,
  `promotional_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`promotional_content`)),
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `start_time`, `end_time`, `image`, `is_active`, `created_at`, `updated_at`, `is_featured`, `venue`, `capacity`, `start_datetime`, `end_datetime`, `ticket_sales_start`, `ticket_sales_end`, `max_tickets_per_person`, `has_table_reservations`, `table_capacity`, `table_price`, `promotional_content`, `status`, `deleted_at`) VALUES
(1, 'Valentine Mask Party', 'Mask Party. No Mask, No Entry.\r\nMoore Life Rooftop Club presents our first special dress up party as celebration to the VALENTINE’S DAY.\r\n\r\nEntry Access: MASK \r\n\r\nReachout to us to purchase your club MASK.\r\n\r\nSEE YOU THERE ON VALENTINES DAY💌', NULL, NULL, 'events/GAiaipft7mzKsI95otI5Vojav0hfrRNM6ew3mt4W.jpg', 1, '2026-01-31 16:59:04', '2026-02-01 11:34:19', 1, 'Rootop CLub & Bar - MooreLife Beach Hotel & Resort, Ibeno', 500, '2026-02-14 20:00:00', '2026-02-15 11:00:00', '2026-01-31 15:49:00', '2026-02-14 19:00:00', 5, 1, NULL, NULL, NULL, 'draft', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_promotional_media`
--

CREATE TABLE `event_promotional_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_main_image` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_promotional_media`
--

INSERT INTO `event_promotional_media` (`id`, `event_id`, `media_type`, `media_url`, `title`, `description`, `sort_order`, `is_main_image`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'image', 'events/promotional/1Zw1ZJgMJufGGN2ig2lJlubCmvONTInf3DdyIJS4.jpg', NULL, NULL, 0, 0, 1, '2026-02-01 11:34:19', '2026-02-01 11:34:19');

-- --------------------------------------------------------

--
-- Table structure for table `event_table_reservations`
--

CREATE TABLE `event_table_reservations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `guest_name` varchar(255) NOT NULL,
  `guest_email` varchar(255) NOT NULL,
  `guest_phone` varchar(255) DEFAULT NULL,
  `table_number` varchar(255) NOT NULL DEFAULT 'TBD',
  `number_of_guests` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'online',
  `payment_reference` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `status` enum('pending','confirmed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_provider` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) NOT NULL,
  `special_requests` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `base_amount` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `service_charge_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_table_types`
--

CREATE TABLE `event_table_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `capacity` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_table_types`
--

INSERT INTO `event_table_types` (`id`, `event_id`, `name`, `description`, `price`, `capacity`, `created_at`, `updated_at`) VALUES
(1, 1, 'Table for 10', NULL, 1000000.00, 10, '2026-01-31 16:59:04', '2026-01-31 16:59:04'),
(2, 1, 'Table for 6', NULL, 500000.00, 6, '2026-01-31 16:59:04', '2026-01-31 16:59:04'),
(3, 1, 'Table for 4', NULL, 250000.00, 4, '2026-01-31 16:59:04', '2026-01-31 16:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `event_tickets`
--

CREATE TABLE `event_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `ticket_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `guest_name` varchar(255) NOT NULL,
  `guest_email` varchar(255) NOT NULL,
  `guest_phone` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(255) NOT NULL DEFAULT 'online',
  `payment_reference` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `payment_provider` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `refund_requested_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `base_amount` decimal(10,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `service_charge_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_tickets`
--

INSERT INTO `event_tickets` (`id`, `event_id`, `ticket_type_id`, `guest_name`, `guest_email`, `guest_phone`, `quantity`, `amount`, `payment_method`, `payment_reference`, `payment_status`, `status`, `payment_provider`, `qr_code`, `checked_in_at`, `refund_requested_at`, `refunded_at`, `notes`, `created_at`, `updated_at`, `deleted_at`, `base_amount`, `vat_amount`, `service_charge_amount`) VALUES
(1, 1, 1, 'Guest Test', 'test@mail.com', NULL, 1, 2500.00, 'online', NULL, 'pending', 'pending', NULL, 'EVT-FHNNQCVP-1769908073', NULL, NULL, NULL, NULL, '2026-02-01 02:07:53', '2026-02-01 02:07:53', NULL, NULL, NULL, NULL),
(2, 1, 1, 'Supreme', 'admin@mooreliferesort.com', NULL, 1, 2500.00, 'online', NULL, 'pending', 'pending', NULL, 'EVT-BWTQ3DPC-1769942130', NULL, NULL, NULL, NULL, '2026-02-01 11:35:30', '2026-02-01 11:35:30', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_ticket_types`
--

CREATE TABLE `event_ticket_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `quantity_sold` int(11) NOT NULL DEFAULT 0,
  `max_per_person` int(11) NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sales_start` datetime DEFAULT NULL,
  `sales_end` datetime DEFAULT NULL,
  `color_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_ticket_types`
--

INSERT INTO `event_ticket_types` (`id`, `event_id`, `name`, `description`, `price`, `quantity_available`, `quantity_sold`, `max_per_person`, `is_active`, `sales_start`, `sales_end`, `color_code`, `created_at`, `updated_at`) VALUES
(1, 1, 'Basic', NULL, 2500.00, 200, 2, 5, 1, NULL, NULL, '#3B82F6', '2026-01-31 16:59:04', '2026-02-01 11:35:30'),
(2, 1, 'Premium', NULL, 5000.00, 100, 0, 5, 1, NULL, NULL, '#3B82F6', '2026-01-31 16:59:04', '2026-01-31 16:59:04'),
(3, 1, 'VIP', NULL, 20000.00, 100, 0, 5, 1, NULL, NULL, '#3B82F6', '2026-01-31 16:59:04', '2026-01-31 16:59:04');

-- --------------------------------------------------------

--
-- Table structure for table `galleries`
--

CREATE TABLE `galleries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `galleries`
--

INSERT INTO `galleries` (`id`, `category`, `image_path`, `caption`, `order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'beach', 'gallery/oYfKpeeHf9MKA6tFugC4L843RF9H5SsIFL7Nkv8u.jpg', 'beach sand', 0, 1, '2026-01-09 16:14:48', '2026-01-15 20:30:41'),
(2, 'beach', 'public/gallery/beach-2.jpg', NULL, 2, 1, '2026-01-09 16:14:48', '2026-01-15 20:04:41'),
(3, 'resort', 'gallery/MJwJ5G1wxckKldLB57iitvH6eavxy1guTddenCy8.jpg', 'Beach Resort', 4, 1, '2026-01-09 16:14:48', '2026-01-15 20:04:41'),
(4, 'resort', 'public/gallery/resort-2.jpg', NULL, 6, 1, '2026-01-09 16:14:48', '2026-01-15 20:04:41'),
(5, 'club', 'gallery/LB6anGCdoumlgbClxc5jHXJguiwnq6JqR6Io8LKa.jpg', NULL, 8, 1, '2026-01-09 16:14:48', '2026-01-19 16:20:03'),
(6, 'club', 'public/gallery/club-2.jpg', NULL, 10, 1, '2026-01-09 16:14:48', '2026-01-15 20:04:41'),
(7, 'rooftop bar', 'public/gallery/lounge-1.jpg', NULL, 12, 1, '2026-01-09 16:14:48', '2026-01-15 20:04:41'),
(8, 'rooftop bar', 'public/gallery/lounge-2.jpg', NULL, 14, 1, '2026-01-09 16:14:48', '2026-01-15 20:04:41'),
(9, 'beach', 'gallery/W85BT3ryVzc9eny1pjrymzaKDs9gJi3IjHIfC3UD.png', NULL, 1, 1, '2026-01-09 18:19:23', '2026-01-19 15:24:23'),
(10, 'beach', 'public/gallery/beach-2.jpg', NULL, 3, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41'),
(11, 'resort', 'public/gallery/resort-1.jpg', NULL, 5, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41'),
(12, 'resort', 'public/gallery/resort-2.jpg', NULL, 7, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41'),
(13, 'club', 'public/gallery/club-1.jpg', NULL, 9, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41'),
(14, 'club', 'public/gallery/club-2.jpg', NULL, 11, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41'),
(15, 'rooftop bar', 'public/gallery/lounge-1.jpg', NULL, 13, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41'),
(16, 'rooftop bar', 'public/gallery/lounge-2.jpg', NULL, 15, 1, '2026-01-09 18:19:23', '2026-01-15 20:04:41');

-- --------------------------------------------------------

--
-- Table structure for table `guest_requests`
--

CREATE TABLE `guest_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `requestable_type` varchar(255) DEFAULT NULL,
  `requestable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `imageable_type` varchar(255) NOT NULL,
  `imageable_id` bigint(20) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL COMMENT 'File path',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `alt` varchar(255) DEFAULT NULL COMMENT 'Alt text',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dimension, size' CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `images`
--

INSERT INTO `images` (`id`, `imageable_type`, `imageable_id`, `path`, `is_primary`, `alt`, `meta`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'App\\Models\\Property', 1, 'images/property/lobby.jpg', 0, 'Lobby', '{\"h\": 900, \"w\": 1600}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(2, 'App\\Models\\Property', 1, 'images/property/pool.jpg', 0, 'Pool', '{\"h\": 900, \"w\": 1600}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(3, 'App\\Models\\Property', 1, 'images/property/restaurant.jpg', 0, 'Restaurant', '{\"h\": 900, \"w\": 1600}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(4, 'App\\Models\\Room', 1, 'images/rooms/room_1.jpg', 0, 'Room 1 photo', '{\"order\": 1}', '2025-12-12 00:01:33', '2025-12-14 03:04:53', '2025-12-14 03:04:53'),
(5, 'App\\Models\\Room', 2, 'images/rooms/room_2.jpg', 0, 'Room 2 photo', '{\"order\": 2}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(6, 'App\\Models\\Room', 3, 'images/rooms/room_3.jpg', 0, 'Room 3 photo', '{\"order\": 3}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(7, 'App\\Models\\Room', 3, 'images/rooms/room_3_2.jpg', 0, 'Room 3 second photo', '{\"order\": 4}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(8, 'App\\Models\\Room', 4, 'images/rooms/room_4.jpg', 0, 'Room 4 photo', '{\"order\": 4}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(9, 'App\\Models\\Room', 5, 'images/rooms/room_5.jpg', 0, 'Room 5 photo', '{\"order\": 5}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(10, 'App\\Models\\Room', 6, 'images/rooms/room_6.jpg', 0, 'Room 6 photo', '{\"order\": 6}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(11, 'App\\Models\\Room', 6, 'images/rooms/room_6_2.jpg', 0, 'Room 6 second photo', '{\"order\": 7}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(12, 'App\\Models\\Room', 7, 'images/rooms/room_7.jpg', 0, 'Room 7 photo', '{\"order\": 7}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(13, 'App\\Models\\Room', 8, 'images/rooms/room_8.jpg', 0, 'Room 8 photo', '{\"order\": 8}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(14, 'App\\Models\\Room', 9, 'images/rooms/room_9.jpg', 0, 'Room 9 photo', '{\"order\": 9}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(15, 'App\\Models\\Room', 9, 'images/rooms/room_9_2.jpg', 0, 'Room 9 second photo', '{\"order\": 10}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(16, 'App\\Models\\Room', 10, 'images/rooms/room_10.jpg', 0, 'Room 10 photo', '{\"order\": 10}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(17, 'App\\Models\\Property', 1, 'images/property/spa.jpg', 0, 'Spa', '[]', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(18, 'App\\Models\\Property', 1, 'images/property/gym.jpg', 0, 'Gym', '[]', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(19, 'App\\Models\\Room', 1, 'rooms/z2w7NbdleCIniAeLvL5p5jlYMjkD6EoOlL67IuVe.jpg', 0, NULL, NULL, '2025-12-14 02:31:52', '2025-12-14 03:25:56', NULL),
(20, 'App\\Models\\Room', 1, 'rooms/PYs8gbg7f4xa1sDxMDwIlv9sLDpVw6YyykTMXFem.jpg', 0, NULL, NULL, '2025-12-14 02:37:51', '2025-12-14 03:25:56', NULL),
(21, 'App\\Models\\Room', 1, 'rooms/PihnUxMpLnWRio3Ch1EfAJo9yWErSF3d6IPwSnTv.jpg', 0, NULL, NULL, '2025-12-14 02:37:51', '2025-12-14 03:25:56', NULL),
(22, 'App\\Models\\Room', 1, 'rooms/N2lGw6js12A1SgqceX0qxDqVpwM7aRb1DCo8v005.jpg', 1, NULL, NULL, '2025-12-14 02:37:51', '2025-12-14 03:25:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(255) DEFAULT NULL COMMENT 'kg, pcs, carton',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `name`, `sku`, `quantity`, `unit`, `meta`, `created_at`, `updated_at`, `deleted_at`, `low_stock_threshold`) VALUES
(1, 'Bath Towel', 'TOWEL-001', 200, 'pcs', '{\"reorder\": 50}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL, 10),
(2, 'Shampoo Bottle', 'SHAM-001', 500, 'pcs', '{\"reorder\": 100}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL, 10),
(3, 'Soap Bar', 'SOAP-001', 600, 'pcs', '{\"reorder\": 150}', '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL, 10),
(4, 'Laundry Detergent', 'LD-001', 99, 'kg', '[]', '2025-12-12 00:01:33', '2025-12-18 20:24:55', NULL, 10),
(5, 'Coffee Beans', 'COF-001', 9, 'kg', '[]', '2025-12-12 00:01:33', '2025-12-18 15:06:38', NULL, 10);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_locations`
--

CREATE TABLE `inventory_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inventory_item_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED DEFAULT NULL,
  `change` int(11) NOT NULL COMMENT 'Positive or negative quantity',
  `type` varchar(255) NOT NULL COMMENT 'addition, removal, adjustment',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `inventory_item_id`, `staff_id`, `change`, `type`, `meta`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 200, 'addition', '{\"source\": \"initial_seed\"}', '2025-12-12 00:01:33', '2025-12-12 00:01:33'),
(2, 4, 2, -2, 'usage', '{\"after\": 97, \"before\": 99, \"reason\": \"Laundry\", \"department_id\": null}', '2025-12-18 15:34:57', '2025-12-18 15:34:57'),
(3, 4, 2, 2, 'undo_usage', NULL, '2025-12-18 20:24:55', '2025-12-18 20:24:55');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inventory_item_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_location_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('in','out','transfer','adjustment') NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stocks`
--

CREATE TABLE `inventory_stocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `inventory_item_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_location_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('in','out','transfer','adjustment') NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entry_date` date NOT NULL,
  `reference_type` varchar(255) NOT NULL,
  `reference_id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_lines`
--

CREATE TABLE `journal_lines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `journal_entry_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laundry_items`
--

CREATE TABLE `laundry_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `laundry_items`
--

INSERT INTO `laundry_items` (`id`, `name`, `price`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Shirt', 500.00, 'non-white tops', '2025-12-28 03:23:41', '2025-12-28 03:23:41'),
(2, 'Suits', 2000.00, 'Suits', '2025-12-28 03:24:31', '2025-12-28 03:24:31'),
(3, 'Trousers', 700.00, 'Male Trousers', '2025-12-28 03:24:55', '2025-12-28 03:24:55');

-- --------------------------------------------------------

--
-- Table structure for table `laundry_orders`
--

CREATE TABLE `laundry_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_code` varchar(255) NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laundry_order_images`
--

CREATE TABLE `laundry_order_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `laundry_order_id` bigint(20) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laundry_order_items`
--

CREATE TABLE `laundry_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `laundry_order_id` bigint(20) UNSIGNED NOT NULL,
  `laundry_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laundry_status_histories`
--

CREATE TABLE `laundry_status_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `laundry_order_id` bigint(20) UNSIGNED NOT NULL,
  `from_status` varchar(255) DEFAULT NULL,
  `to_status` varchar(255) NOT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_tickets`
--

CREATE TABLE `maintenance_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('kitchen','bar','both') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `prep_time_minutes` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_inventory_recipes`
--

CREATE TABLE `menu_inventory_recipes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `menu_item_id` bigint(20) UNSIGNED NOT NULL,
  `inventory_item_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` decimal(8,2) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `menu_category_id` bigint(20) UNSIGNED NOT NULL,
  `menu_subcategory_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `prep_time_minutes` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `service_area` enum('kitchen','bar') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_item_images`
--

CREATE TABLE `menu_item_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `menu_item_id` bigint(20) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_subcategories`
--

CREATE TABLE `menu_subcategories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `menu_category_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `prep_time_minutes` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2025_12_07_184713_create_roles_table', 1),
(2, '2025_12_07_184812_create_user_table', 1),
(3, '2025_12_07_184859_create_staff_profiles_table', 1),
(4, '2025_12_07_185228_create_properties_table', 1),
(5, '2025_12_07_192432_create_room_types_table', 1),
(6, '2025_12_07_192432_create_rooms_table', 1),
(7, '2025_12_08_092439_create_bookings_table', 1),
(8, '2025_12_08_092440_create_inventory_items_table', 1),
(9, '2025_12_08_092441_create_inventory_logs_table', 1),
(10, '2025_12_08_092442_create_maintenance_tickets_table', 1),
(11, '2025_12_08_092443_create_orders_table', 1),
(12, '2025_12_08_092444_create_order_items_table', 1),
(13, '2025_12_08_092445_create_order_events_table', 1),
(14, '2025_12_08_092446_create_payments_table', 1),
(15, '2025_12_08_092447_create_settings_table', 1),
(16, '2025_12_08_092448_create_audit_logs_table', 1),
(17, '2025_12_08_092449_create_images_table', 1),
(18, '2025_12_13_025421_add_is_primary_to_images_table', 2),
(19, '2025_12_13_042133_remove_role_id_from_users_table', 3),
(20, '2025_12_13_042658_create_permission_tables', 4),
(21, '2025_12_13_160214_create_staff_notes', 5),
(22, '2025_12_13_162950_create_staff_threads_table', 6),
(23, '2025_12_13_163059_create_staff_threads_messages_table', 6),
(24, '2025_12_14_011938_add_suspended_at_to_users_table', 7),
(25, '2025_12_18_144536_add_low_stock_threshold_column_to_inventory_table', 8),
(26, '2025_12_18_164230_create_departments_table', 9),
(27, '2025_12_18_223325_add_nightly_rates_to_bookings_table', 10),
(28, '2025_12_20_141206_add_department_id_to_users_table', 11),
(29, '2025_12_20_151338_create_room_cleanings_table', 12),
(30, '2025_12_21_025532_create_cleaning_logs_table', 13),
(31, '2025_12_21_034313_add_columns_to_bookings_table', 14),
(32, '2025_12_23_161415_create_booking_rooms_table', 15),
(33, '2025_12_23_181728_create_room_access_tokens_table', 16),
(34, '2025_12_23_184052_create_service_requests_table', 17),
(35, '2025_12_23_184201_create_maintenance_tickets_table', 17),
(36, '2025_12_23_184220_create_charges_table', 17),
(37, '2025_12_23_184235_create_payments_table', 18),
(38, '2025_12_23_232705_create_guest_requests_table', 19),
(39, '2025_12_24_192845_create_room_payments_table', 20),
(40, '2025_12_24_201929_add_type_column_to_charges_table', 21),
(41, '2025_12_24_201930_add_type_column_to_charges_table', 22),
(42, '2025_12_24_204326_add_rate_override_to_booking_rooms_table', 23),
(43, '2025_12_24_204946_add_charge_date_column_to_charges_table', 24),
(44, '2025_12_25_000349_add_checked_in_timestamp_column_to_booking_rooms_table', 25),
(45, '2025_12_27_124824_create_laundry_items_table', 26),
(46, '2025_12_27_124905_create_laundry_orders_table', 26),
(47, '2025_12_27_124953_create_laundry_order_items_table', 26),
(48, '2025_12_27_125023_create_laundry_order_images_table', 26),
(49, '2025_12_27_125102_create_laundry_status_histories_table', 26),
(50, '2025_12_28_032826_rename_action_code_hash_column', 27),
(51, '2025_12_29_131732_add_requestable_tables_to_guest_requests_table', 28),
(52, '2025_12_30_002130_create_receipts_table', 29),
(53, '2025_12_30_002425_add_reference_to_payments_table', 29),
(54, '2026_01_01_120000_create_menu_categories_table', 30),
(55, '2026_01_01_120001_create_menu_subcategories_table', 30),
(56, '2026_01_01_120002_create_menu_items_table', 30),
(57, '2026_01_01_120003_update_orders_table_add_service_area_and_status', 30),
(58, '2026_01_01_045852_create_menu_item_images_table', 31),
(59, '2026_01_02_090000_add_is_active_to_menu_categories', 31),
(60, '2026_01_02_090001_add_is_active_to_menu_subcategories', 31),
(61, '2026_01_02_090002_add_is_active_to_menu_items', 31),
(62, '2026_01_02_090004_add_prep_time_to_menu_categories', 31),
(63, '2026_01_02_090005_add_prep_time_to_menu_subcategories', 31),
(64, '2026_01_03_034218_make_user_id_nullable_on_room_cleanings_table', 31),
(65, '2026_01_03_041321_remove_enum_from_status_columnin_room_cleanings_table', 31),
(66, '2026_01_05_132429_add_room_id_and_notes_to_orders_table', 32),
(67, '2026_01_06_160450_add_cancelable_until_and_completed_at_columns_to_orders_table', 33),
(68, '2026_01_06_161919_add_notes_to_order_items_table', 33),
(69, '2026_01_06_184410_change_orders_status_to_string', 33),
(70, '2026_01_08_150852_add_name_column_to_rooms_table', 33),
(71, '2026_01_08_163319_remove_room_number_from_rooms_table', 34),
(72, '2026_01_08_164656_add_flutterwave_fields_to_payments_table', 34),
(73, '2026_01_08_183215_add_status_and_payment_moode_columns_to_charges_table', 34),
(74, '2026_01_09_004614_add_order_id_column_to_charges_table', 34),
(75, '2026_01_09_004649_add_order_id_column_to_charges_table', 34),
(76, '2026_01_09_121156_make_charges_billable_polymorphic', 34),
(77, '2026_01_09_162226_create_contents_table', 35),
(78, '2026_01_09_162243_create_galleries_table', 35),
(79, '2026_01_09_191406_create_events_table', 35),
(80, '2026_01_22_162239_create_inventory_locations_table', 36),
(81, '2026_01_22_162332_create_inventory_stocks_table', 36),
(82, '2026_01_22_162355_create_inventory_movements_table', 36),
(83, '2026_01_22_171614_fix_inventory_movements_table', 36),
(84, '2026_01_26_113347_swap_inventory_table_names', 36),
(85, '2026_01_26_133001_create_cleaning_inventory_templates', 36),
(86, '2026_01_26_145118_create_menu_inventory_recipes', 36),
(87, '2026_01_28_123816_create_accounts_table', 36),
(88, '2026_01_28_123907_create_journal_entries_table', 36),
(89, '2026_01_28_123938_create_journal_lines_table', 36),
(90, '2026_01_30_151605_create_accounting_periods_table', 36),
(91, '2026_01_30_162706_create_event_ticket_types_table', 36),
(92, '2026_01_30_163206_create_event_tickets_table_2026', 37),
(93, '2026_01_30_170000_create_event_table_reservations_table', 37),
(94, '2026_01_30_170100_create_payment_points_table', 37),
(95, '2026_01_30_224419_add_missing_columns_to_events_table', 37),
(96, '2026_01_30_224635_add_deleted_at_to_events_table', 37),
(97, '2026_01_31_000000_add_soft_deletes_to_event_tickets_table', 37),
(98, '2026_01_31_000001_add_flutterwave_payment_fields', 37),
(99, '2026_01_31_000001_add_soft_deletes_to_event_table_reservations_table', 37),
(100, '2026_01_31_100000_create_event_promotional_media_table', 37),
(101, '2026_01_31_120000_create_event_table_types_table', 37),
(102, '2026_01_31_200000_add_is_active_to_event_promotional_media_table', 37),
(103, '2026_01_31_155529_remove_event_date_column_from_events_table', 38),
(104, '2026_02_01_000000_add_tax_breakdown_to_event_payments', 39),
(105, '2026_02_01_122931_rename_amount_paid_to_amount_on_payments_table', 39),
(106, '2026_02_01_122932_rename_amount_paid_to_amount_on_payments_table.', 39),
(107, '2026_02_02_000000_add_provider_support_to_payments_table', 40),
(108, '2026_02_04_000000_add_idempotency_key_to_payments_table', 41);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(5, 'App\\Models\\User', 1),
(4, 'App\\Models\\User', 2),
(2, 'App\\Models\\User', 3),
(2, 'App\\Models\\User', 4),
(6, 'App\\Models\\User', 6),
(7, 'App\\Models\\User', 24),
(7, 'App\\Models\\User', 25),
(6, 'App\\Models\\User', 26),
(10, 'App\\Models\\User', 27),
(9, 'App\\Models\\User', 28),
(8, 'App\\Models\\User', 29);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED DEFAULT NULL,
  `room_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_code` varchar(255) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(255) NOT NULL DEFAULT 'postpaid',
  `payment_status` varchar(255) NOT NULL DEFAULT 'not_required',
  `payment_reference` varchar(255) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `service_area` enum('kitchen','bar') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cancelable_until` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_events`
--

CREATE TABLE `order_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event` varchar(255) NOT NULL COMMENT 'Event type: created, updated, delivered',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional event details' CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `item_name` varchar(255) NOT NULL COMMENT 'Name of item ordered',
  `qty` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'NGN',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `provider` varchar(255) NOT NULL DEFAULT 'flutterwave',
  `payment_type` varchar(255) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `flutterwave_tx_id` varchar(255) DEFAULT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `raw_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_response`)),
  `paid_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `flutterwave_tx_ref` varchar(255) DEFAULT NULL,
  `flutterwave_refund_id` varchar(255) DEFAULT NULL,
  `idempotency_key` varchar(255) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_points`
--

CREATE TABLE `payment_points` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_spent` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `type` enum('earned','redeemed') NOT NULL DEFAULT 'earned',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'manage_dashboard', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(2, 'view_inventory', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(3, 'manage_inventory', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(4, 'view_orders', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(5, 'manage_orders', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(6, 'manage_users', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(7, 'view_reports', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(8, 'manage_reports', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'Name of property/hotel',
  `location` varchar(255) DEFAULT NULL COMMENT 'Address or city',
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON list of amenities' CHECK (json_valid(`amenities`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `name`, `location`, `amenities`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Moore Life Beach Resort', 'Ibeno Beach, Akwa I bom', '[\"wifi\", \"pool\", \"spa\", \"restaurant\", \"gym\"]', '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `total_charges` decimal(12,2) NOT NULL,
  `total_payments` decimal(12,2) NOT NULL,
  `balance` decimal(12,2) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'guest', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(2, 'staff', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(3, 'inventory manager', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(4, 'manager', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(5, 'md', 'web', '2025-12-13 03:32:35', '2025-12-13 03:32:35'),
(6, 'frontdesk', 'web', NULL, NULL),
(7, 'laundry', 'web', NULL, NULL),
(8, 'kitchen', 'web', NULL, NULL),
(9, 'bar', 'web', NULL, NULL),
(10, 'clean', 'web', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(2, 1),
(2, 2),
(4, 2),
(2, 3),
(3, 3),
(1, 4),
(2, 4),
(4, 4),
(7, 4),
(1, 5),
(3, 5),
(6, 5),
(8, 5),
(4, 6);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `room_type_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'available' COMMENT 'available, occupied, maintenance',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Custom attributes per room' CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `floor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `code`, `property_id`, `room_type_id`, `status`, `meta`, `created_at`, `updated_at`, `deleted_at`, `display_name`, `slug`, `floor`) VALUES
(51, 'Major AB Lawal', NULL, 1, 1, 'occupied', NULL, '2026-01-09 16:04:05', '2026-03-24 00:36:38', NULL, NULL, NULL, NULL),
(52, 'Col. U. Adamu', NULL, 1, 1, 'available', NULL, '2026-01-09 16:04:37', '2026-01-09 16:04:37', NULL, NULL, NULL, NULL),
(53, 'SGT Komolafe', NULL, 1, 1, 'available', NULL, '2026-01-09 16:05:16', '2026-01-09 16:05:16', NULL, NULL, NULL, NULL),
(54, 'SGT Olabiyi', NULL, 1, 2, 'available', NULL, '2026-01-09 16:05:46', '2026-01-09 16:05:46', NULL, NULL, NULL, NULL),
(55, 'Caleb', NULL, 1, 2, 'available', NULL, '2026-01-09 16:06:00', '2026-01-09 16:06:00', NULL, NULL, NULL, NULL),
(56, 'LCPL Oyedele Solese', NULL, 1, 2, 'available', NULL, '2026-01-09 16:06:25', '2026-01-09 16:06:25', NULL, NULL, NULL, NULL),
(57, 'Richard', NULL, 1, 2, 'available', NULL, '2026-01-09 16:06:46', '2026-01-09 16:06:46', NULL, NULL, NULL, NULL),
(58, 'Gold', NULL, 1, 2, 'available', NULL, '2026-01-09 16:07:12', '2026-01-09 16:07:12', NULL, NULL, NULL, NULL),
(59, 'PTE Ahmed', NULL, 1, 3, 'available', NULL, '2026-01-09 16:07:29', '2026-01-09 16:07:29', NULL, NULL, NULL, NULL),
(60, 'PTE Adeosun', NULL, 1, 3, 'available', NULL, '2026-01-09 16:07:50', '2026-01-09 16:07:50', NULL, NULL, NULL, NULL),
(61, 'SSGT Shehu', NULL, 1, 3, 'available', NULL, '2026-01-09 16:08:09', '2026-01-09 16:08:09', NULL, NULL, NULL, NULL),
(62, 'CAPT. Yau', NULL, 1, 3, 'available', NULL, '2026-01-09 16:08:40', '2026-01-09 16:08:40', NULL, NULL, NULL, NULL),
(63, 'PTE Deyon', NULL, 1, 3, 'available', NULL, '2026-01-09 16:09:06', '2026-01-09 16:09:06', NULL, NULL, NULL, NULL),
(64, 'SGT Effiong Asuquo', NULL, 1, 3, 'available', NULL, '2026-01-09 16:09:30', '2026-01-09 16:09:30', NULL, NULL, NULL, NULL),
(65, 'CPL U.L. Ndifreke', NULL, 1, 3, 'available', NULL, '2026-01-09 16:09:59', '2026-01-09 16:09:59', NULL, NULL, NULL, NULL),
(66, 'Engr. Emeka Nnedu', NULL, 1, 3, 'available', NULL, '2026-01-09 16:10:30', '2026-01-09 16:10:30', NULL, NULL, NULL, NULL),
(67, 'Barrister C. Okparaolu', NULL, 1, 4, 'available', NULL, '2026-01-09 16:11:33', '2026-01-09 16:11:33', NULL, NULL, NULL, NULL),
(68, 'PTE Mohammed', NULL, 1, 4, 'available', NULL, '2026-01-09 16:12:02', '2026-01-09 16:12:02', NULL, NULL, NULL, NULL),
(69, 'Isah', NULL, 1, 4, 'available', NULL, '2026-01-09 16:12:24', '2026-01-09 16:12:24', NULL, NULL, NULL, NULL),
(70, 'Engr. Temibere Tobore', NULL, 1, 4, 'available', NULL, '2026-01-09 16:12:58', '2026-01-09 16:12:58', NULL, NULL, NULL, NULL),
(71, 'Inua Eyet Ikot', NULL, 1, 4, 'available', NULL, '2026-01-09 16:13:21', '2026-01-09 16:13:21', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `room_access_tokens`
--

CREATE TABLE `room_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_access_tokens`
--

INSERT INTO `room_access_tokens` (`id`, `booking_id`, `room_id`, `token`, `expires_at`, `created_at`, `updated_at`) VALUES
(16, 31, 51, 'a039fe6d7db7dd6ef1be1e398df45d0c05cc83ac0dd7773fcf8b7ff5abcc1419', '2026-04-29 01:00:00', '2026-03-24 00:36:38', '2026-03-24 00:36:38');

-- --------------------------------------------------------

--
-- Table structure for table `room_cleanings`
--

CREATE TABLE `room_cleanings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `cleaned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_payments`
--

CREATE TABLE `room_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(255) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_types`
--

CREATE TABLE `room_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'Room type name',
  `max_occupancy` int(11) NOT NULL DEFAULT 1,
  `base_price` decimal(10,2) NOT NULL COMMENT 'Default price',
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON features: wifi, tv, etc' CHECK (json_valid(`features`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_types`
--

INSERT INTO `room_types` (`id`, `property_id`, `title`, `max_occupancy`, `base_price`, `features`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Sea View', 5, 150000.00, NULL, NULL, NULL, NULL),
(2, 1, 'Land View', 4, 100000.00, NULL, NULL, NULL, NULL),
(3, 1, 'Premium', 3, 80000.00, NULL, NULL, NULL, NULL),
(4, 1, 'Standard', 2, 70000.00, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `meta`, `created_at`, `updated_at`) VALUES
(1, 'hotel_name', 'Moore Life Beach Resort', NULL, '2025-12-20 13:19:41', '2025-12-20 13:19:41'),
(2, 'hotel_email', 'info@mooreliferesort.com', NULL, '2025-12-20 13:19:41', '2025-12-20 13:19:41'),
(3, 'hotel_phone', '+2348090000000', NULL, '2025-12-20 13:19:41', '2025-12-20 13:19:41'),
(4, 'hotel_address', 'Ibeno Beach, Akwa Ibom', NULL, '2025-12-20 13:19:41', '2025-12-20 13:19:41'),
(5, 'map_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!...', NULL, '2025-12-20 13:19:41', '2025-12-20 13:19:41'),
(6, 'page_about', 'Moore Life Beach Resort is a Luxury Beach Side Resort', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33'),
(7, 'page_terms', 'Terms and conditions apply.', NULL, '2025-12-12 00:01:34', '2025-12-12 00:01:34'),
(8, 'page_privacy', 'We value your privacy.', NULL, '2025-12-12 00:01:34', '2025-12-12 00:01:34'),
(9, 'page_contact_info', 'Reach us at +2348090000000 or info@lagosharborhotel.com', NULL, '2025-12-12 00:01:34', '2025-12-12 00:01:34'),
(10, 'site_name', 'Moore Life Beach Resort', NULL, '2025-12-18 20:36:49', '2025-12-18 20:36:49'),
(11, 'contact_email', 'info@tarimoorehotels.com', NULL, '2025-12-18 20:36:49', '2025-12-18 20:36:49'),
(12, 'contact_phone', '1122334455', NULL, '2025-12-18 20:36:49', '2025-12-18 20:36:49'),
(13, 'logo', 'settings/1oKHlZ7TWLOGGuvBjENzXqDS0k9haZBoqoj2w4le.png', NULL, '2026-01-21 09:05:27', '2026-01-21 09:50:33'),
(14, 'site_whatsapp', '+2347083095684', NULL, NULL, '2026-01-31 14:55:30');

-- --------------------------------------------------------

--
-- Table structure for table `staff_notes`
--

CREATE TABLE `staff_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('query','commendation') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_notes`
--

INSERT INTO `staff_notes` (`id`, `staff_id`, `admin_id`, `type`, `message`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'query', 'tHIS IS THE MD', '2025-12-13 15:23:45', '2025-12-13 15:23:45'),
(2, 3, 2, 'query', 'c  nc bdvad', '2025-12-14 01:09:57', '2025-12-14 01:09:57');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `position` varchar(255) DEFAULT NULL COMMENT 'Job title (e.g. receptionist)',
  `phone` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Custom JSON attributes' CHECK (json_valid(`meta`)),
  `action_code` varchar(255) DEFAULT NULL COMMENT 'Hashed staff action approval code',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`id`, `user_id`, `position`, `phone`, `meta`, `action_code`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 4, 'Staff', '+234801000000', '{\"shift\": \"morning\"}', NULL, '2025-12-12 00:01:32', '2025-12-12 00:01:32', NULL),
(2, 5, 'Staff', '+234801000001', '{\"shift\": \"evening\"}', NULL, '2025-12-12 00:01:32', '2025-12-12 00:01:32', NULL),
(3, 6, 'Staff', '+234801000002', '{\"shift\": \"evening\"}', NULL, '2025-12-12 00:01:32', '2025-12-12 00:01:32', NULL),
(4, 7, 'Staff', '+234801000003', '{\"shift\": \"morning\"}', NULL, '2025-12-12 00:01:32', '2025-12-12 00:01:32', NULL),
(5, 8, 'Staff', '+234801000004', '{\"shift\": \"evening\"}', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(6, 9, 'Staff', '+234801000005', '{\"shift\": \"evening\"}', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(7, 10, 'Staff', '+234801000006', '{\"shift\": \"morning\"}', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(8, 11, 'Staff', '+234801000007', '{\"shift\": \"evening\"}', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(9, 12, 'Staff', '+234801000008', '{\"shift\": \"evening\"}', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(10, 13, 'Staff', '+234801000009', '{\"shift\": \"morning\"}', NULL, '2025-12-12 00:01:33', '2025-12-12 00:01:33', NULL),
(11, 1, NULL, NULL, NULL, NULL, '2025-12-13 15:18:25', '2025-12-13 15:19:58', NULL),
(12, 3, NULL, '12345678', NULL, NULL, '2025-12-14 00:39:30', '2025-12-14 00:39:55', NULL),
(13, 24, NULL, '1111', NULL, NULL, '2025-12-28 02:13:16', '2025-12-28 02:13:16', NULL),
(14, 25, NULL, '12345678', NULL, 'eyJpdiI6InFwSmNoWXcxWmlYaHVKOEkyNEFDOUE9PSIsInZhbHVlIjoiRUppMFdQZldZSXF4aGRPODdKb2FLdz09IiwibWFjIjoiNWNiYmIyMDNlZDU2MWU2NWM2M2YwMjRhMWE2M2JiYjkyMGFkYjAwMzU5Nzk0MTcxZTFiNGM5MTI4MTYyYmE0NSIsInRhZyI6IiJ9', '2025-12-28 02:52:15', '2025-12-28 02:52:15', NULL),
(15, 26, NULL, '1234', NULL, 'eyJpdiI6ImNMbnlHT3ZyczE5MytmdEFUaTRvcVE9PSIsInZhbHVlIjoiaWFFOWM3cDdFSlRLRDZaLzI5bkt1UT09IiwibWFjIjoiNGFiMjY2N2E4YmIzODVmOTRiZTFkN2NlYjdjNDg0YTY5ODc3YWUyZWI3MTc4ZjE3NWFhYzlkMjUwNmYwMGNjNCIsInRhZyI6IiJ9', '2026-01-01 04:05:34', '2026-01-01 04:05:34', NULL),
(16, 27, NULL, '1234', NULL, 'eyJpdiI6Ikwwa1grdWVCUGpXaExQdU00SHlNY0E9PSIsInZhbHVlIjoibHh3Uno5VGwzWHA0dlBoUElwVFpnZz09IiwibWFjIjoiMTI0YjI3YTQzZDI4ODI2ZWZjYWI2ZGE3ZjgyNWZmZTVmMzQ0ZGJmYWZlZWEzMDRjYTAwMzYzNTY4Zjg0MWVjNSIsInRhZyI6IiJ9', '2026-01-01 04:06:11', '2026-01-01 04:06:11', NULL),
(17, 28, NULL, '1234', NULL, 'eyJpdiI6IjlGVm5ab2Z5aXA2Yy9xS3R0VVRMWHc9PSIsInZhbHVlIjoiOGpyUkxUaXdXdmgvTFhlQ1dZZGl0Zz09IiwibWFjIjoiNGZiMTc2ZTgwZTFiYWExZDJiOWY0ZjMwMGI3YjQ2YjMwMzEwMjE0YTY2NDMxNjNiZGRjZjBiNWEwNTcyMjZhOCIsInRhZyI6IiJ9', '2026-01-01 04:08:16', '2026-01-01 04:08:16', NULL),
(18, 29, NULL, '1234', NULL, 'eyJpdiI6InJhMnQ1cVNERFQxUW1FTE15cEhrZ3c9PSIsInZhbHVlIjoiL3hId0tVeDBZVzV6RnhEZ3Q0ZHRkUT09IiwibWFjIjoiNDBjODk1ZDc0MTY2NzFhZTM3MzU4YmNiMWMzMDBiYzI4N2Q0YWEwMzFkZmI5YTczMzRkMTgxMDMxNmFmYjk4MiIsInRhZyI6IiJ9', '2026-01-01 04:08:48', '2026-01-01 04:08:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_threads`
--

CREATE TABLE `staff_threads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` enum('query','commendation') NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_threads`
--

INSERT INTO `staff_threads` (`id`, `staff_id`, `admin_id`, `type`, `title`, `created_at`, `updated_at`) VALUES
(1, 5, 2, 'query', 'Test Title', '2025-12-13 16:47:53', '2025-12-13 16:47:53'),
(2, 5, 2, 'commendation', 'Test 2', '2025-12-13 23:54:06', '2025-12-13 23:54:06'),
(3, 5, 2, 'query', 'Title', '2025-12-14 00:07:49', '2025-12-14 00:07:49');

-- --------------------------------------------------------

--
-- Table structure for table `staff_thread_messages`
--

CREATE TABLE `staff_thread_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_thread_messages`
--

INSERT INTO `staff_thread_messages` (`id`, `thread_id`, `sender_id`, `message`, `attachments`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Test Message', '[{}]', '2025-12-13 16:47:53', '2025-12-13 16:47:53'),
(2, 1, 2, 'test', NULL, '2025-12-13 23:47:14', '2025-12-13 23:47:14'),
(3, 1, 2, 'test message 3', '[\"staff_threads/BxtK4ICvgbMo0kHES9HR6ggFPvd7tws4cxETd4Y2.png\"]', '2025-12-13 23:53:23', '2025-12-13 23:53:23'),
(4, 2, 2, 'Test Image 1', '[{}]', '2025-12-13 23:54:06', '2025-12-13 23:54:06'),
(5, 3, 2, 'Test Image', '[\"staff_threads/rvEcZzo0w1JwWKWZSx2EvDMqetw9pawX89WpE0Bo.jpg\"]', '2025-12-14 00:07:49', '2025-12-14 00:07:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL COMMENT 'Public user identifier',
  `name` varchar(255) NOT NULL COMMENT 'Full name',
  `email` varchar(255) NOT NULL COMMENT 'User email address',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `uuid`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `suspended_at`, `created_at`, `updated_at`, `deleted_at`, `department_id`) VALUES
(1, '8d87d3c0-d054-4fbd-85b0-286633aa05c5', 'Chinonso Okafor', 'ceo@email.com', NULL, '$2y$12$KTPKI34xj1RQ5gY4jcDdFOW68h5VHO042HO9IMPTtiXs/L4BLXWBu', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, 1),
(2, '1810a061-3efb-452c-ac97-203fb515bfdf', 'Aisha Bello', 'manager@email.com', NULL, '$2y$12$oqh/tYL5Wz/KzYJFM34taO/I.HTDM9fZQuCdp29d3.YdgqXI5fIBi', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, 1),
(3, 'c6639973-70b5-4b18-983a-4e822fa2b703', 'Daniel Mensah', 'inventory@email.com', NULL, '$2y$12$J3a.YNA4aMXQE27yL.qi5ercHjNut07JyQJ24DCFyba/o8kaMYWX6', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(4, '9ae7e8e5-80a2-4129-ab7f-300932cdb0c1', 'Grace Adebayo', 'grace.ad@email.com', NULL, '$2y$12$wi6CuvHBwSulxfbQVcPUBeofrlbaxGIH2Zy0dnRF70tfpJNQOY/8a', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, 4),
(5, '2cadf201-35d6-464d-8161-e2340e474436', 'Emeka Nwosu', 'emeka.nw@email.com', NULL, '$2y$12$y3WHhFpm311TjaRoiGg79OZUkJk5zjFasDwyC1dew4oDzQSHsFJTW', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(6, '3bbd1f43-ca64-46f4-adeb-a046ad3c2844', 'Bolaji Ojo', 'bolaji.oj@email.com', NULL, '$2y$12$VeQmUogAYVqFa3WChtEeieLhjXJt./DsWHXoOTRnZm9n05cwH9AKS', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(7, '8ce53df2-3934-4375-a32a-306d18ca169d', 'Sadiq Ibrahim', 'sadiq.ib@email.com', NULL, '$2y$12$nKR01EPO661R9v4i.QcN9edNt/ir86CVlbBD/4hD.nvaaMMJTqtUO', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(8, '991c5a18-f205-427e-a562-31b2f6e94c70', 'Ruth Eze', 'ruth.ez@email.com', NULL, '$2y$12$gNZRFHgzjjxWeJtBuUHbB.hEAvsLULkQFz6oEUJNuEttWSh9kgL8u', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(9, '2940bce3-ac50-479e-bb57-1ede28a14874', 'Tunde Oladipo', 'tunde.ol@email.com', NULL, '$2y$12$PPmqK9pGlseTKXQKY0ccNe8iAOfyvYG7EzwVVzhT9GayjXiG1pvNW', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(10, 'b762af5e-ceef-41f0-b853-867c4f4359d9', 'Mercy Okoye', 'mercy.ok@email.com', NULL, '$2y$12$jwI5qWMCM7FoI8gZrsNJrernwhE9yh6IQjK5L4nDaTYPnEYmaNe52', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(11, '8da146d5-95c7-480a-9f14-9dee2d238986', 'John Doe', 'john.doe@email.com', NULL, '$2y$12$vDxnakOJ4mtU2O371Z5oiuTywC61PGZkyvTQ3I0wHdTtej6GoVb3G', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(12, '7cc40086-6001-4e15-be57-666092caa355', 'Femi Johnson', 'femi.jo@email.com', NULL, '$2y$12$DWCVo9Zp6mHwEcmWjmcpsu4PRE3gemrXLF7mD2Oispp8saWp0Haka', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(13, 'dc5bdc81-db2b-4d6c-912b-66514316ce7d', 'Amina Yusuf', 'amina.yu@email.com', NULL, '$2y$12$HfcdneBpaTdl34PEmXuj3OXYR4CCELbGw17oki7F30rrYSpy.fxVm', NULL, NULL, '2025-12-12 00:01:26', '2025-12-14 00:14:20', '2025-12-14 00:14:20', NULL),
(14, '67ea10cd-e26c-4345-91e6-42ceb8a743c1', 'Adebimpe Oladunni', 'bimpe.guest@example.com', NULL, '$2y$12$DvK6iGVKvXUD7ZToF0SWeOjetMfdWLSrShexDBphqHbU8YXVR7SRi', NULL, NULL, '2025-12-12 00:01:26', '2025-12-14 00:14:33', '2025-12-14 00:14:33', NULL),
(15, '02bc063f-8cb3-4bd4-b096-d89a12d4e431', 'Michael Smith', 'michael.smith@example.com', NULL, '$2y$12$WHsVW19e9la92jLh.hI6oOHeVQUPMdip.N2aISjqBA6Qq6m6AMS7u', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(16, '4edffb07-9ad0-4aa6-9857-d930677d6e44', 'Ngozi Chukwu', 'ngozi.c@example.com', NULL, '$2y$12$ogxx2GJ5O9IK.5BhkN4lGeeCRcf1sk98YZp5Sd3Pw1oeHD1pTVqVm', NULL, NULL, '2025-12-12 00:01:26', '2025-12-14 00:09:08', '2025-12-14 00:09:08', NULL),
(17, '10a8112e-7bd7-4aa7-878d-7e8bd23e42e8', 'Samantha Brown', 'samantha.b@example.com', NULL, '$2y$12$Qxk3VCpMenKGDYqh2yX5Nes5kEwYMkrKo0qFWdSo8Mq3QIdlU.Xia', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(18, '300f3b1b-501d-4b01-a966-4a98a758f2ad', 'Olufemi Adeyemi', 'femi.a@example.com', NULL, '$2y$12$4Z0cvFlBPCZzZCHfu6KiKeHg3sZyK8oBlEIwPyHKN7D6Kw0WDukUu', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(19, 'abc772a5-4fda-4979-9334-344f6cec3b99', 'Chen Wei', 'chen.wei@example.com', NULL, '$2y$12$S6dXnGb7wXZMmpgCO0TfdeED/MsThbsIoTJeeAeQuDq4ng4Sf9lDq', NULL, NULL, '2025-12-12 00:01:26', '2025-12-14 00:08:44', '2025-12-14 00:08:44', NULL),
(20, '0ac9646c-afc6-495c-b655-f6707a340ee6', 'Maria Rodriguez', 'maria.rodriguez@example.com', NULL, '$2y$12$DWtaPiBRGLXypF1wneIRwusL3z1cGb66bBld4GrZ4Pk6RFNS1Ypvq', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(21, 'a70cdd7a-4d62-44db-85cf-fb6ea65a191e', 'Kwame Nkrumah', 'kwame.n@example.com', NULL, '$2y$12$NgQUiVvlFVDxuEb52XS1r.0ohmREfTUFCLdiDu4/iUILVt6Wl/qVm', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(22, '43907f13-1e95-42b4-bd29-fa1684d70949', 'Lilian Okon', 'lilian.ok@example.com', NULL, '$2y$12$1WU3Fq4rLxgu7jKifm5preRyPfNjcFUgRhPTSM5AqtnQEe/K2Fyxy', NULL, NULL, '2025-12-12 00:01:26', '2025-12-12 00:01:26', NULL, NULL),
(23, '7e64f0e7-0081-4c3b-8f38-1433fea45714', 'Peter Obi', 'peter.ob@example.com', NULL, '$2y$12$odtGRbBCQleUwdgPko3ueed/ay8CK0EA4Z2vwR1pLOR/LCAQP1Dye', NULL, NULL, '2025-12-12 00:01:26', '2025-12-14 00:17:12', '2025-12-14 00:17:12', NULL),
(24, '53a32ae0-a0cb-4b71-af0f-576ff08a2e6d', 'Laundry', 'laundr@email.com', NULL, '$2y$12$i/zJRxEmDarTl/txrPI17e.wjln2D5dxZvMsrsFLE0M4WCnDBye12', NULL, NULL, '2025-12-28 02:13:16', '2025-12-28 02:20:22', '2025-12-28 02:20:22', NULL),
(25, 'f7b65799-2e24-4308-a4db-cb314930a0ed', 'Laundry', 'laundry@email.com', NULL, '$2y$12$vjj5WScTbCvilI09QUYZ4eq4OiHhhjnL2ahmuq52jjvGIGAGgGxW6', NULL, NULL, '2025-12-28 02:52:15', '2025-12-28 03:03:36', NULL, NULL),
(26, 'b41ec566-1fc4-4260-a6c5-3197b52ab173', 'frontdesk staff', 'front@email.com', NULL, '$2y$12$8vN2igIWqwNVUdwTL3gEB.HxtwkQ9tka/yqfkbIFwBjyrXFW5gzJe', 'QxLN89iA1QMnD03Vj8zlhRpMgrEH0bfoWfOZqr3AnlRBya3oK4aLoENK1GFY', NULL, '2026-01-01 04:05:34', '2026-01-01 04:05:34', NULL, NULL),
(27, 'a8961dfa-fa32-45e3-a29d-5a7bbbb1363a', 'Cleaner', 'clean@email.com', NULL, '$2y$12$89fWt.2GG9418nDrRA3ccON9jusmAHygHGRiRWnY0YzFH.bAomjnq', NULL, NULL, '2026-01-01 04:06:11', '2026-01-01 04:06:11', NULL, NULL),
(28, '82e56a5e-33ef-4fa1-b3ab-aaa52cc67906', 'Bar Staff', 'bar@email.com', NULL, '$2y$12$kqk4QZnaq8Sj7tssGXA/8.xuyluSZ9HKmNnfwXLsZIZjWWO3P/6ta', NULL, NULL, '2026-01-01 04:08:16', '2026-01-01 04:08:16', NULL, NULL),
(29, '0158d498-9478-4cf7-84e7-cd5deaf71f77', 'kitchen staff', 'kitchen@email.com', NULL, '$2y$12$WGLs7fkY1pSLCHlZnbloVu/GLpFn9AyajkrUYxUKmxeqm1jr77f2G', NULL, NULL, '2026-01-01 04:08:48', '2026-01-01 04:08:48', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounting_periods`
--
ALTER TABLE `accounting_periods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accounts_code_unique` (`code`),
  ADD KEY `accounts_parent_id_foreign` (`parent_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_user_id_foreign` (`user_id`),
  ADD KEY `audit_logs_action_index` (`action`),
  ADD KEY `audit_logs_ip_address_index` (`ip_address`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bookings_booking_code_unique` (`booking_code`),
  ADD KEY `bookings_property_id_foreign` (`property_id`),
  ADD KEY `bookings_user_id_foreign` (`user_id`),
  ADD KEY `bookings_check_in_index` (`check_in`),
  ADD KEY `bookings_check_out_index` (`check_out`),
  ADD KEY `bookings_total_amount_index` (`total_amount`),
  ADD KEY `bookings_status_index` (`status`);

--
-- Indexes for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_rooms_booking_id_room_id_unique` (`booking_id`,`room_id`),
  ADD KEY `booking_rooms_room_id_foreign` (`room_id`);

--
-- Indexes for table `charges`
--
ALTER TABLE `charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `charges_booking_id_foreign` (`booking_id`),
  ADD KEY `charges_room_id_foreign` (`room_id`),
  ADD KEY `charges_charge_date_index` (`charge_date`),
  ADD KEY `charges_billable_type_billable_id_index` (`billable_type`,`billable_id`);

--
-- Indexes for table `cleaning_inventory_templates`
--
ALTER TABLE `cleaning_inventory_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clean_tpl_room_item_unique` (`room_type_id`,`inventory_item_id`),
  ADD KEY `cleaning_inventory_templates_inventory_item_id_foreign` (`inventory_item_id`);

--
-- Indexes for table `cleaning_logs`
--
ALTER TABLE `cleaning_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cleaning_logs_room_id_foreign` (`room_id`),
  ADD KEY `cleaning_logs_user_id_foreign` (`user_id`);

--
-- Indexes for table `contents`
--
ALTER TABLE `contents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contents_key_unique` (`key`),
  ADD KEY `contents_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `events_is_featured_index` (`is_featured`),
  ADD KEY `events_is_active_index` (`is_active`),
  ADD KEY `events_status_index` (`status`),
  ADD KEY `events_start_datetime_index` (`start_datetime`);

--
-- Indexes for table `event_promotional_media`
--
ALTER TABLE `event_promotional_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_promotional_media_event_id_media_type_index` (`event_id`,`media_type`),
  ADD KEY `event_promotional_media_event_id_is_main_image_index` (`event_id`,`is_main_image`);

--
-- Indexes for table `event_table_reservations`
--
ALTER TABLE `event_table_reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_table_reservations_qr_code_unique` (`qr_code`),
  ADD KEY `event_table_reservations_event_id_status_index` (`event_id`,`status`),
  ADD KEY `event_table_reservations_payment_status_index` (`payment_status`),
  ADD KEY `event_table_reservations_qr_code_index` (`qr_code`);

--
-- Indexes for table `event_table_types`
--
ALTER TABLE `event_table_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_table_types_event_id_foreign` (`event_id`);

--
-- Indexes for table `event_tickets`
--
ALTER TABLE `event_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_tickets_qr_code_unique` (`qr_code`),
  ADD KEY `event_tickets_ticket_type_id_foreign` (`ticket_type_id`),
  ADD KEY `event_tickets_event_id_status_index` (`event_id`,`status`),
  ADD KEY `event_tickets_payment_status_index` (`payment_status`);

--
-- Indexes for table `event_ticket_types`
--
ALTER TABLE `event_ticket_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_ticket_types_event_id_is_active_index` (`event_id`,`is_active`);

--
-- Indexes for table `galleries`
--
ALTER TABLE `galleries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guest_requests`
--
ALTER TABLE `guest_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guest_requests_booking_id_foreign` (`booking_id`),
  ADD KEY `guest_requests_room_id_foreign` (`room_id`),
  ADD KEY `guest_requests_requestable_index` (`requestable_type`,`requestable_id`),
  ADD KEY `guest_requests_type_status_index` (`type`,`status`),
  ADD KEY `guest_requests_type_index` (`type`),
  ADD KEY `guest_requests_status_index` (`status`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `images_imageable_type_imageable_id_index` (`imageable_type`,`imageable_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_items_sku_unique` (`sku`),
  ADD KEY `inventory_items_name_index` (`name`);

--
-- Indexes for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_logs_inventory_item_id_foreign` (`inventory_item_id`),
  ADD KEY `inventory_logs_staff_id_foreign` (`staff_id`),
  ADD KEY `inventory_logs_type_index` (`type`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_stocks_inventory_item_id_foreign` (`inventory_item_id`),
  ADD KEY `inventory_stocks_inventory_location_id_foreign` (`inventory_location_id`),
  ADD KEY `inventory_stocks_staff_id_foreign` (`staff_id`);

--
-- Indexes for table `inventory_stocks`
--
ALTER TABLE `inventory_stocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_movements_inventory_item_id_foreign` (`inventory_item_id`),
  ADD KEY `inventory_movements_inventory_location_id_foreign` (`inventory_location_id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_entries_created_by_foreign` (`created_by`),
  ADD KEY `journal_entries_reference_type_reference_id_index` (`reference_type`,`reference_id`);

--
-- Indexes for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_lines_journal_entry_id_foreign` (`journal_entry_id`),
  ADD KEY `journal_lines_account_id_index` (`account_id`);

--
-- Indexes for table `laundry_items`
--
ALTER TABLE `laundry_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `laundry_orders`
--
ALTER TABLE `laundry_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `laundry_orders_order_code_unique` (`order_code`),
  ADD KEY `laundry_orders_room_id_foreign` (`room_id`),
  ADD KEY `laundry_orders_status_created_at_index` (`status`,`created_at`);

--
-- Indexes for table `laundry_order_images`
--
ALTER TABLE `laundry_order_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `laundry_order_images_laundry_order_id_foreign` (`laundry_order_id`);

--
-- Indexes for table `laundry_order_items`
--
ALTER TABLE `laundry_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `laundry_order_items_laundry_order_id_foreign` (`laundry_order_id`),
  ADD KEY `laundry_order_items_laundry_item_id_foreign` (`laundry_item_id`);

--
-- Indexes for table `laundry_status_histories`
--
ALTER TABLE `laundry_status_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `laundry_status_histories_laundry_order_id_foreign` (`laundry_order_id`),
  ADD KEY `laundry_status_histories_changed_by_foreign` (`changed_by`);

--
-- Indexes for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_tickets_booking_id_foreign` (`booking_id`),
  ADD KEY `maintenance_tickets_room_id_foreign` (`room_id`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_inventory_recipes`
--
ALTER TABLE `menu_inventory_recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menu_inventory_recipes_menu_item_id_inventory_item_id_unique` (`menu_item_id`,`inventory_item_id`),
  ADD KEY `menu_inventory_recipes_inventory_item_id_foreign` (`inventory_item_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_items_menu_category_id_foreign` (`menu_category_id`),
  ADD KEY `menu_items_menu_subcategory_id_foreign` (`menu_subcategory_id`);

--
-- Indexes for table `menu_item_images`
--
ALTER TABLE `menu_item_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_item_images_menu_item_id_foreign` (`menu_item_id`);

--
-- Indexes for table `menu_subcategories`
--
ALTER TABLE `menu_subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_subcategories_menu_category_id_foreign` (`menu_category_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_code_unique` (`order_code`),
  ADD KEY `orders_booking_id_foreign` (`booking_id`),
  ADD KEY `orders_user_id_foreign` (`user_id`),
  ADD KEY `orders_total_index` (`total`),
  ADD KEY `orders_status_index` (`status`),
  ADD KEY `orders_room_id_foreign` (`room_id`);

--
-- Indexes for table `order_events`
--
ALTER TABLE `order_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_events_order_id_foreign` (`order_id`),
  ADD KEY `order_events_staff_id_foreign` (`staff_id`),
  ADD KEY `order_events_event_index` (`event`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_foreign` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `payments_booking_id_foreign` (`booking_id`),
  ADD KEY `payments_room_id_foreign` (`room_id`),
  ADD KEY `payments_reference_index` (`reference`),
  ADD KEY `payments_flutterwave_tx_ref_index` (`flutterwave_tx_ref`),
  ADD KEY `payments_provider_index` (`provider`),
  ADD KEY `payments_external_reference_index` (`external_reference`),
  ADD KEY `payments_payment_type_index` (`payment_type`);

--
-- Indexes for table `payment_points`
--
ALTER TABLE `payment_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_points_payment_id_foreign` (`payment_id`),
  ADD KEY `payment_points_customer_email_type_index` (`customer_email`,`type`),
  ADD KEY `payment_points_created_at_index` (`created_at`),
  ADD KEY `payment_points_customer_email_index` (`customer_email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `properties_name_index` (`name`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receipts_booking_id_foreign` (`booking_id`),
  ADD KEY `receipts_room_id_foreign` (`room_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rooms_property_id_foreign` (`property_id`),
  ADD KEY `rooms_room_type_id_foreign` (`room_type_id`),
  ADD KEY `rooms_status_index` (`status`);

--
-- Indexes for table `room_access_tokens`
--
ALTER TABLE `room_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_access_tokens_token_unique` (`token`),
  ADD KEY `room_access_tokens_booking_id_foreign` (`booking_id`),
  ADD KEY `room_access_tokens_room_id_foreign` (`room_id`);

--
-- Indexes for table `room_cleanings`
--
ALTER TABLE `room_cleanings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_cleanings_room_id_foreign` (`room_id`),
  ADD KEY `room_cleanings_staff_id_foreign` (`staff_id`);

--
-- Indexes for table `room_payments`
--
ALTER TABLE `room_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_payments_booking_id_foreign` (`booking_id`),
  ADD KEY `room_payments_room_id_foreign` (`room_id`);

--
-- Indexes for table `room_types`
--
ALTER TABLE `room_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_types_property_id_foreign` (`property_id`),
  ADD KEY `room_types_title_index` (`title`),
  ADD KEY `room_types_base_price_index` (`base_price`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_requests_booking_id_foreign` (`booking_id`),
  ADD KEY `service_requests_room_id_foreign` (`room_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`);

--
-- Indexes for table `staff_notes`
--
ALTER TABLE `staff_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_notes_staff_id_foreign` (`staff_id`),
  ADD KEY `staff_notes_admin_id_foreign` (`admin_id`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_profiles_user_id_foreign` (`user_id`),
  ADD KEY `staff_profiles_phone_index` (`phone`);

--
-- Indexes for table `staff_threads`
--
ALTER TABLE `staff_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_threads_staff_id_foreign` (`staff_id`),
  ADD KEY `staff_threads_admin_id_foreign` (`admin_id`);

--
-- Indexes for table `staff_thread_messages`
--
ALTER TABLE `staff_thread_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_thread_messages_thread_id_foreign` (`thread_id`),
  ADD KEY `staff_thread_messages_sender_id_foreign` (`sender_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_uuid_unique` (`uuid`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_name_index` (`name`),
  ADD KEY `users_department_id_foreign` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounting_periods`
--
ALTER TABLE `accounting_periods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `charges`
--
ALTER TABLE `charges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cleaning_inventory_templates`
--
ALTER TABLE `cleaning_inventory_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cleaning_logs`
--
ALTER TABLE `cleaning_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contents`
--
ALTER TABLE `contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `event_promotional_media`
--
ALTER TABLE `event_promotional_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_table_reservations`
--
ALTER TABLE `event_table_reservations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_table_types`
--
ALTER TABLE `event_table_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_tickets`
--
ALTER TABLE `event_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_ticket_types`
--
ALTER TABLE `event_ticket_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `galleries`
--
ALTER TABLE `galleries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `guest_requests`
--
ALTER TABLE `guest_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_stocks`
--
ALTER TABLE `inventory_stocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_lines`
--
ALTER TABLE `journal_lines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `laundry_items`
--
ALTER TABLE `laundry_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `laundry_orders`
--
ALTER TABLE `laundry_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `laundry_order_images`
--
ALTER TABLE `laundry_order_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `laundry_order_items`
--
ALTER TABLE `laundry_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `laundry_status_histories`
--
ALTER TABLE `laundry_status_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_inventory_recipes`
--
ALTER TABLE `menu_inventory_recipes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_item_images`
--
ALTER TABLE `menu_item_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_subcategories`
--
ALTER TABLE `menu_subcategories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `order_events`
--
ALTER TABLE `order_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_points`
--
ALTER TABLE `payment_points`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `room_access_tokens`
--
ALTER TABLE `room_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `room_cleanings`
--
ALTER TABLE `room_cleanings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `room_payments`
--
ALTER TABLE `room_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `room_types`
--
ALTER TABLE `room_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `staff_notes`
--
ALTER TABLE `staff_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `staff_threads`
--
ALTER TABLE `staff_threads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_thread_messages`
--
ALTER TABLE `staff_thread_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_rooms`
--
ALTER TABLE `booking_rooms`
  ADD CONSTRAINT `booking_rooms_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_rooms_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `charges`
--
ALTER TABLE `charges`
  ADD CONSTRAINT `charges_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `charges_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cleaning_inventory_templates`
--
ALTER TABLE `cleaning_inventory_templates`
  ADD CONSTRAINT `cleaning_inventory_templates_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cleaning_inventory_templates_room_type_id_foreign` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cleaning_logs`
--
ALTER TABLE `cleaning_logs`
  ADD CONSTRAINT `cleaning_logs_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cleaning_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contents`
--
ALTER TABLE `contents`
  ADD CONSTRAINT `contents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_promotional_media`
--
ALTER TABLE `event_promotional_media`
  ADD CONSTRAINT `event_promotional_media_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_table_reservations`
--
ALTER TABLE `event_table_reservations`
  ADD CONSTRAINT `event_table_reservations_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_table_types`
--
ALTER TABLE `event_table_types`
  ADD CONSTRAINT `event_table_types_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_tickets`
--
ALTER TABLE `event_tickets`
  ADD CONSTRAINT `event_tickets_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_tickets_ticket_type_id_foreign` FOREIGN KEY (`ticket_type_id`) REFERENCES `event_ticket_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_ticket_types`
--
ALTER TABLE `event_ticket_types`
  ADD CONSTRAINT `event_ticket_types_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guest_requests`
--
ALTER TABLE `guest_requests`
  ADD CONSTRAINT `guest_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guest_requests_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_logs_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `inventory_stocks_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stocks_inventory_location_id_foreign` FOREIGN KEY (`inventory_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stocks_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_stocks`
--
ALTER TABLE `inventory_stocks`
  ADD CONSTRAINT `inventory_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_movements_inventory_location_id_foreign` FOREIGN KEY (`inventory_location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD CONSTRAINT `journal_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `journal_lines_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laundry_orders`
--
ALTER TABLE `laundry_orders`
  ADD CONSTRAINT `laundry_orders_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laundry_order_images`
--
ALTER TABLE `laundry_order_images`
  ADD CONSTRAINT `laundry_order_images_laundry_order_id_foreign` FOREIGN KEY (`laundry_order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laundry_order_items`
--
ALTER TABLE `laundry_order_items`
  ADD CONSTRAINT `laundry_order_items_laundry_item_id_foreign` FOREIGN KEY (`laundry_item_id`) REFERENCES `laundry_items` (`id`),
  ADD CONSTRAINT `laundry_order_items_laundry_order_id_foreign` FOREIGN KEY (`laundry_order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laundry_status_histories`
--
ALTER TABLE `laundry_status_histories`
  ADD CONSTRAINT `laundry_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `laundry_status_histories_laundry_order_id_foreign` FOREIGN KEY (`laundry_order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_tickets`
--
ALTER TABLE `maintenance_tickets`
  ADD CONSTRAINT `maintenance_tickets_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_tickets_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_inventory_recipes`
--
ALTER TABLE `menu_inventory_recipes`
  ADD CONSTRAINT `menu_inventory_recipes_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_inventory_recipes_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_menu_category_id_foreign` FOREIGN KEY (`menu_category_id`) REFERENCES `menu_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_items_menu_subcategory_id_foreign` FOREIGN KEY (`menu_subcategory_id`) REFERENCES `menu_subcategories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `menu_item_images`
--
ALTER TABLE `menu_item_images`
  ADD CONSTRAINT `menu_item_images_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_subcategories`
--
ALTER TABLE `menu_subcategories`
  ADD CONSTRAINT `menu_subcategories_menu_category_id_foreign` FOREIGN KEY (`menu_category_id`) REFERENCES `menu_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_events`
--
ALTER TABLE `order_events`
  ADD CONSTRAINT `order_events_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_events_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_points`
--
ALTER TABLE `payment_points`
  ADD CONSTRAINT `payment_points_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `receipts_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rooms_room_type_id_foreign` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_access_tokens`
--
ALTER TABLE `room_access_tokens`
  ADD CONSTRAINT `room_access_tokens_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_access_tokens_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_cleanings`
--
ALTER TABLE `room_cleanings`
  ADD CONSTRAINT `room_cleanings_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_cleanings_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_payments`
--
ALTER TABLE `room_payments`
  ADD CONSTRAINT `room_payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `room_payments_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `room_types`
--
ALTER TABLE `room_types`
  ADD CONSTRAINT `room_types_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_requests_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_notes`
--
ALTER TABLE `staff_notes`
  ADD CONSTRAINT `staff_notes_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_notes_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `staff_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_threads`
--
ALTER TABLE `staff_threads`
  ADD CONSTRAINT `staff_threads_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `staff_threads_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_thread_messages`
--
ALTER TABLE `staff_thread_messages`
  ADD CONSTRAINT `staff_thread_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_thread_messages_thread_id_foreign` FOREIGN KEY (`thread_id`) REFERENCES `staff_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
