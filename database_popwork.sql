-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 30, 2026 at 02:49 AM
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
-- Database: `popwork`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Pending','Shortlisted','Accepted','Rejected') DEFAULT 'Pending',
  `total_work_hours` decimal(6,2) DEFAULT 0.00,
  `job_completed` tinyint(1) DEFAULT 0,
  `completion_date` datetime DEFAULT NULL,
  `employer_notes` text DEFAULT NULL,
  `payment_status` enum('pending','processing','paid','disputed') DEFAULT 'pending',
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `last_clock_in` datetime DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `job_id`, `user_id`, `status`, `total_work_hours`, `job_completed`, `completion_date`, `employer_notes`, `payment_status`, `payment_amount`, `payment_date`, `payment_method`, `last_clock_in`, `applied_at`, `created_at`) VALUES
(30, 36, 15, 'Accepted', 1.97, 1, '2026-01-10 16:57:22', '', 'paid', 92.50, '2026-01-11 13:22:50', 'cash', '2026-01-20 21:18:29', '2026-01-10 08:40:46', '2026-01-10 16:40:46'),
(31, 37, 15, 'Accepted', 199.92, 1, '2026-01-11 13:21:53', '', 'paid', 41.00, '2026-01-11 13:25:49', 'cash', '2026-01-20 21:17:20', '2026-01-11 04:29:46', '2026-01-11 12:29:46'),
(38, 44, 15, 'Accepted', 2.06, 1, '2026-01-15 14:25:22', '', 'paid', 19.30, '2026-01-15 15:33:03', 'bank_transfer', '2026-01-20 21:19:49', '2026-01-15 04:04:22', '2026-01-15 12:04:22'),
(39, 44, 26, 'Rejected', 0.05, 0, NULL, NULL, 'pending', NULL, NULL, NULL, '2026-01-15 15:23:40', '2026-01-15 07:19:52', '2026-01-15 15:19:52'),
(40, 45, 15, 'Accepted', 0.02, 1, '2026-01-21 13:07:14', '', 'paid', 1.00, '2026-01-21 13:07:26', 'cash', '2026-01-21 13:04:40', '2026-01-21 05:03:35', '2026-01-21 13:03:35'),
(41, 45, 26, 'Accepted', 1.87, 1, '2026-01-21 16:11:24', NULL, 'pending', 93.50, NULL, NULL, '2026-01-21 14:10:58', '2026-01-21 06:10:06', '2026-01-21 14:10:06'),
(42, 46, 15, 'Shortlisted', 0.00, 0, NULL, NULL, 'pending', NULL, NULL, NULL, '2026-01-21 16:31:33', '2026-01-21 08:30:34', '2026-01-21 16:30:34');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `clock_in_time` datetime NOT NULL,
  `clock_out_time` datetime DEFAULT NULL,
  `clock_in_location` varchar(255) DEFAULT NULL,
  `clock_out_location` varchar(255) DEFAULT NULL,
  `clock_in_latitude` decimal(10,8) DEFAULT NULL,
  `clock_in_longitude` decimal(11,8) DEFAULT NULL,
  `clock_out_latitude` decimal(10,8) DEFAULT NULL,
  `clock_out_longitude` decimal(11,8) DEFAULT NULL,
  `clock_out_distance` decimal(8,2) DEFAULT NULL COMMENT 'Distance from job location at clock out (meters)',
  `clock_out_verified` tinyint(1) DEFAULT 0 COMMENT 'Whether location was verified at clock out',
  `distance_from_job_location` decimal(8,2) DEFAULT NULL COMMENT 'Distance in meters',
  `location_verified` tinyint(1) DEFAULT 0,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('clocked_in','clocked_out','approved','disputed') DEFAULT 'clocked_in',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `application_id`, `user_id`, `job_id`, `employer_id`, `clock_in_time`, `clock_out_time`, `clock_in_location`, `clock_out_location`, `clock_in_latitude`, `clock_in_longitude`, `clock_out_latitude`, `clock_out_longitude`, `clock_out_distance`, `clock_out_verified`, `distance_from_job_location`, `location_verified`, `total_hours`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(9, 30, 15, 36, 23, '2026-01-10 16:41:46', '2026-01-10 16:56:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.25, NULL, 'clocked_out', '2026-01-10 08:41:46', '2026-01-10 08:56:26'),
(10, 30, 15, 36, 23, '2026-01-11 11:43:55', '2026-01-11 13:20:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1.60, NULL, 'clocked_out', '2026-01-11 03:43:55', '2026-01-11 05:20:34'),
(12, 31, 15, 37, 23, '2026-01-11 12:30:48', '2026-01-11 13:20:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.82, NULL, 'clocked_out', '2026-01-11 04:30:48', '2026-01-11 05:20:31'),
(13, 31, 15, 37, 23, '2026-01-11 14:52:03', '2026-01-19 21:58:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 199.10, NULL, 'clocked_out', '2026-01-11 06:52:03', '2026-01-19 13:58:15'),
(18, 38, 15, 44, 23, '2026-01-15 12:05:33', '2026-01-15 14:02:05', NULL, NULL, 6.04623417, 116.13021027, NULL, NULL, NULL, 0, 6.58, 1, 1.93, NULL, 'clocked_out', '2026-01-15 04:05:33', '2026-01-15 06:02:05'),
(19, 39, 26, 44, 23, '2026-01-15 15:23:40', '2026-01-15 15:26:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.05, NULL, 'clocked_out', '2026-01-15 07:23:40', '2026-01-15 07:26:42'),
(20, 38, 15, 44, 23, '2026-01-19 21:49:18', '2026-01-19 21:58:17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.13, NULL, 'clocked_out', '2026-01-19 13:49:18', '2026-01-19 13:58:17'),
(23, 30, 15, 36, 23, '2026-01-19 21:50:17', '2026-01-19 21:58:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.12, NULL, 'clocked_out', '2026-01-19 13:50:17', '2026-01-19 13:58:03'),
(24, 38, 15, 44, 23, '2026-01-20 21:11:26', '2026-01-20 21:11:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.00, NULL, 'clocked_out', '2026-01-20 13:11:26', '2026-01-20 13:11:31'),
(25, 30, 15, 36, 23, '2026-01-20 21:11:47', '2026-01-20 21:11:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.00, NULL, 'clocked_out', '2026-01-20 13:11:47', '2026-01-20 13:11:51'),
(26, 38, 15, 44, 23, '2026-01-20 21:17:06', '2026-01-20 21:17:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.00, NULL, 'clocked_out', '2026-01-20 13:17:06', '2026-01-20 13:17:18'),
(27, 31, 15, 37, 23, '2026-01-20 21:17:20', '2026-01-20 21:17:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.00, NULL, 'clocked_out', '2026-01-20 13:17:20', '2026-01-20 13:17:23'),
(28, 30, 15, 36, 23, '2026-01-20 21:18:29', '2026-01-20 21:18:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.00, NULL, 'clocked_out', '2026-01-20 13:18:29', '2026-01-20 13:18:31'),
(29, 38, 15, 44, 23, '2026-01-20 21:19:49', '2026-01-20 21:19:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.00, NULL, 'clocked_out', '2026-01-20 13:19:49', '2026-01-20 13:19:53'),
(30, 40, 15, 45, 23, '2026-01-21 13:04:40', '2026-01-21 13:06:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 0.02, NULL, 'clocked_out', '2026-01-21 05:04:40', '2026-01-21 05:06:38'),
(31, 41, 26, 45, 23, '2026-01-21 14:10:58', '2026-01-21 16:03:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, 1.87, NULL, 'clocked_out', '2026-01-21 06:10:58', '2026-01-21 08:03:34'),
(32, 42, 15, 46, 23, '2026-01-21 16:31:33', NULL, NULL, NULL, 6.01854045, 116.12036933, NULL, NULL, NULL, 0, 5.82, 1, NULL, NULL, 'clocked_in', '2026-01-21 08:31:33', '2026-01-21 08:31:33');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL COMMENT 'User giving the feedback',
  `reviewee_id` int(11) NOT NULL COMMENT 'User receiving the feedback',
  `rating` tinyint(1) NOT NULL COMMENT 'Overall rating 1-5',
  `comment` text DEFAULT NULL,
  `work_quality` tinyint(1) DEFAULT NULL COMMENT '1-5: Quality of work done',
  `reliability` tinyint(1) DEFAULT NULL COMMENT '1-5: Showed up on time, completed tasks',
  `professionalism` tinyint(1) DEFAULT NULL COMMENT '1-5: Professional behavior',
  `job_accuracy` tinyint(1) DEFAULT NULL COMMENT '1-5: Job matched description',
  `payment_timeliness` tinyint(1) DEFAULT NULL COMMENT '1-5: Paid on time',
  `communication` tinyint(1) DEFAULT NULL COMMENT '1-5: Clear communication',
  `would_work_again` tinyint(1) DEFAULT 1 COMMENT '1=Yes, 0=No',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `application_id`, `reviewer_id`, `reviewee_id`, `rating`, `comment`, `work_quality`, `reliability`, `professionalism`, `job_accuracy`, `payment_timeliness`, `communication`, `would_work_again`, `created_at`, `updated_at`) VALUES
(1, 30, 23, 15, 5, 'heyya', 5, 5, 5, NULL, NULL, NULL, 1, '2026-01-10 09:06:32', '2026-01-10 09:06:32'),
(3, 30, 15, 23, 4, 'heyya', NULL, NULL, NULL, 5, 5, 5, 1, '2026-01-10 09:07:49', '2026-01-10 09:07:49');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `category` enum('Gig','Part-Time','Full-Time') DEFAULT 'Gig',
  `description` text DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `pay_rate` decimal(10,2) DEFAULT NULL,
  `pay_period` varchar(20) DEFAULT NULL,
  `job_duration` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `experience_level` enum('Entry','Mid','Senior','Any') DEFAULT 'Any',
  `required_documents` text DEFAULT NULL,
  `languages_required` varchar(200) DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `schedule` varchar(255) DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `positions_available` int(11) DEFAULT 1,
  `is_urgent` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `auto_expire_days` int(11) DEFAULT 30,
  `status` enum('available','upcoming','completed','cancelled','expired') DEFAULT 'available',
  `job_status` enum('available','in_progress','completed','cancelled') DEFAULT 'available',
  `completion_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `require_onsite_attendance` tinyint(1) DEFAULT 0 COMMENT 'Requires worker to be at job location',
  `job_latitude` decimal(10,8) DEFAULT NULL COMMENT 'Job site latitude',
  `job_longitude` decimal(11,8) DEFAULT NULL COMMENT 'Job site longitude',
  `geofence_radius` int(11) DEFAULT 100 COMMENT 'Allowed radius in meters (default 100m)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`job_id`, `employer_id`, `title`, `category`, `description`, `location`, `latitude`, `longitude`, `pay_rate`, `pay_period`, `job_duration`, `start_date`, `end_date`, `experience_level`, `required_documents`, `languages_required`, `responsibilities`, `skills`, `schedule`, `benefits`, `positions_available`, `is_urgent`, `expires_at`, `auto_expire_days`, `status`, `job_status`, `completion_notes`, `created_at`, `updated_at`, `require_onsite_attendance`, `job_latitude`, `job_longitude`, `geofence_radius`) VALUES
(36, 23, 'Security Guard', 'Part-Time', '📌 Position: Security Guard\r\n\r\n🎯 KEY RESPONSIBILITIES:\r\n• Monitor premises for security threats\r\n• Control access to buildings\r\n• Conduct regular patrols\r\n• Report suspicious activities\r\n• Handle emergencies\r\n• Maintain security logs\r\n\r\n✅ REQUIRED SKILLS:\r\n• Vigilance\r\n• Physical fitness\r\n• Communication\r\n• First aid knowledge\r\n• Problem solving\r\n• Reliability\r\n\r\n📧 Interested candidates, please apply now!', 'Beaufort', 6.028681, 116.129241, 50.00, 'Per Day', '1 day', '2026-01-30', '2026-01-31', 'Any', 'Resume,Cover Letter', '', '[\"Monitor premises for security threats\",\"Control access to buildings\",\"Conduct regular patrols\",\"Report suspicious activities\",\"Handle emergencies\",\"Maintain security logs\"]', '[\"Vigilance\",\"Physical fitness\",\"Communication\",\"First aid knowledge\",\"Problem solving\",\"Reliability\"]', 'Flexible hours', 'Free meals', 1, 0, '2026-02-09 00:00:00', 30, 'available', 'available', NULL, '2026-01-10 07:24:24', '2026-01-10 07:34:45', 0, NULL, NULL, 100),
(37, 23, 'Cashier', 'Full-Time', '📌 Position: Cashier\r\n📋 Job Type: Full Time\r\n\r\n🎯 KEY RESPONSIBILITIES:\r\n• Handle cash and card transactions\r\n• Process customer purchases accurately\r\n• Provide excellent customer service\r\n• Maintain cleanliness of checkout area\r\n• Balance cash register at end of shift\r\n• Answer customer inquiries\r\n\r\n✅ REQUIRED SKILLS:\r\n• Cash handling\r\n• Customer service\r\n• Attention to detail\r\n• Basic math skills\r\n• POS system operation\r\n• Communication skills\r\n\r\n📧 Interested candidates, please apply now!', 'Beaufort', 5.893858, 116.081170, 50.00, 'Per Hour', '1 day', '2026-01-12', '2026-01-13', 'Senior', 'Resume', 'English', '[\"Handle cash and card transactions\",\"Process customer purchases accurately\",\"Provide excellent customer service\",\"Maintain cleanliness of checkout area\",\"Balance cash register at end of shift\",\"Answer customer inquiries\"]', '[\"Cash handling\",\"Customer service\",\"Attention to detail\",\"Basic math skills\",\"POS system operation\",\"Communication skills\"]', 'Flexible hours', 'Free meals', 1, 0, '2026-01-18 12:28:41', 7, 'available', 'available', NULL, '2026-01-11 04:28:41', '2026-01-11 04:28:41', 0, NULL, NULL, 100),
(44, 23, 'Event Crew', 'Gig', 'Job Details\r\n- Set up and dismantle event equipment\r\n- Assist with crowd management\r\n- Coordinate with event organizers\r\n- Ensure safety protocols are followed\r\n- Handle registration and guest assistance\r\n- Manage event logistics and materials\r\n\r\nRequired Skills\r\n- Physical stamina\r\n- Teamwork\r\n- Communication skills\r\n- Time management\r\n- Problem solving\r\n- Customer service', 'Kota Kinabalu', 6.046295, 116.130173, 10.00, 'Per Hour', '1 day', '2026-01-30', '2026-01-31', 'Any', 'Resume,Portfolio', 'English', '[\"Set up and dismantle event equipment\",\"Assist with crowd management\",\"Coordinate with event organizers\",\"Ensure safety protocols are followed\",\"Handle registration and guest assistance\",\"Manage event logistics and materials\"]', '[\"Physical stamina\",\"Teamwork\",\"Communication skills\",\"Time management\",\"Problem solving\",\"Customer service\"]', 'Flexible hours', 'Free meals', 1, 0, '2026-01-22 00:00:00', 7, 'available', 'available', NULL, '2026-01-15 04:01:12', '2026-01-15 06:09:33', 0, NULL, NULL, 100),
(45, 23, 'Kitchen Helper', 'Gig', '📌 Position: Kitchen Helper\r\n🎯 KEY RESPONSIBILITIES:\r\n• Assist chefs with food preparation\r\n• Maintain kitchen cleanliness\r\n• Wash dishes and utensils\r\n• Stock ingredients and supplies\r\n• Follow food safety standards\r\n• Support cooking operations\r\n\r\n✅ REQUIRED SKILLS:\r\n• Food safety knowledge\r\n• Physical stamina\r\n• Teamwork\r\n• Attention to detail\r\n• Time management\r\n• Reliability\r\n\r\n📧 Interested candidates, please apply now!', 'Kota Kinabalu', NULL, NULL, 50.00, 'Per Project', '1 day', '2026-01-30', '2026-01-31', 'Any', 'Resume', '', '[\"Assist chefs with food preparation\",\"Maintain kitchen cleanliness\",\"Wash dishes and utensils\",\"Stock ingredients and supplies\",\"Follow food safety standards\",\"Support cooking operations\"]', '[\"Food safety knowledge\",\"Physical stamina\",\"Teamwork\",\"Attention to detail\",\"Time management\",\"Reliability\"]', 'Flexible hours', 'Free meals', 1, 0, '2026-01-28 13:03:18', 7, 'available', 'available', NULL, '2026-01-21 05:03:18', '2026-01-21 05:03:18', 0, NULL, NULL, 100),
(46, 23, 'Delivery Rider', 'Gig', '📌 Position: Delivery Rider\r\n🎯 KEY RESPONSIBILITIES:\r\n• Deliver orders to customers on time\r\n• Handle deliveries safely\r\n• Maintain delivery vehicle\r\n• Collect payments (if required)\r\n• Navigate using GPS\r\n• Provide courteous customer service\r\n\r\n✅ REQUIRED SKILLS:\r\n• Driving/Riding license\r\n• Navigation skills\r\n• Time management\r\n• Physical fitness\r\n• Customer service\r\n• Safety awareness\r\n\r\n📧 Interested candidates, please apply now!', 'Kota Kinabalu', NULL, NULL, 10.00, 'Per Hour', 'Permanent', '2026-01-23', NULL, 'Any', 'Resume', '', '[\"Deliver orders to customers on time\",\"Handle deliveries safely\",\"Maintain delivery vehicle\",\"Collect payments (if required)\",\"Navigate using GPS\",\"Provide courteous customer service\"]', '[\"Driving/Riding license\",\"Navigation skills\",\"Time management\",\"Physical fitness\",\"Customer service\",\"Safety awareness\"]', 'Flexible hours', '', 1, 0, '2026-01-28 16:30:24', 7, 'available', 'available', NULL, '2026-01-21 08:30:24', '2026-01-21 08:30:24', 1, 6.01854403, 116.12042186, 320);

-- --------------------------------------------------------

--
-- Table structure for table `job_completion_log`
--

CREATE TABLE `job_completion_log` (
  `log_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `total_hours` decimal(10,2) DEFAULT 0.00,
  `payment_amount` decimal(10,2) DEFAULT 0.00,
  `completed_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_completion_log`
--

INSERT INTO `job_completion_log` (`log_id`, `application_id`, `job_id`, `worker_id`, `employer_id`, `total_hours`, `payment_amount`, `completed_at`, `notes`) VALUES
(2, 38, 44, 15, 23, 1.93, 19.30, '2026-01-15 14:25:22', NULL),
(5, 40, 45, 15, 23, 0.02, 1.00, '2026-01-21 13:07:14', NULL),
(6, 41, 45, 26, 23, 1.87, 93.50, '2026-01-21 16:11:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_templates`
--

CREATE TABLE `job_templates` (
  `template_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `pay_period` varchar(20) DEFAULT NULL,
  `job_duration` varchar(50) DEFAULT NULL,
  `experience_level` enum('Entry','Mid','Senior','Any') DEFAULT 'Any',
  `required_documents` text DEFAULT NULL,
  `languages_required` varchar(200) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_templates`
--

INSERT INTO `job_templates` (`template_id`, `employer_id`, `template_name`, `title`, `category`, `description`, `responsibilities`, `skills`, `schedule`, `benefits`, `pay_period`, `job_duration`, `experience_level`, `required_documents`, `languages_required`, `is_default`, `created_at`, `updated_at`) VALUES
(2, 16, 'Cashier Template', 'Cashier', 'Gig', '📌 Position: Cashier\r\n📋 Job Type: 🎯 Gig (One-time task)\r\n💰 Compensation: RM 50.00 Per Project\r\n👥 Positions Available: 3\r\n🎓 Experience Level: Senior Level\r\n🔥 URGENT HIRING!\r\n\r\n🎯 KEY RESPONSIBILITIES:\r\n• Handle cash and card transactions\r\n• Process customer purchases accurately\r\n• Provide excellent customer service\r\n• Maintain cleanliness of checkout area\r\n• Balance cash register at end of shift\r\n• Answer customer inquiries\r\n• communication\r\n\r\n✅ REQUIRED SKILLS:\r\n• Cash handling\r\n• Customer service\r\n• Attention to detail\r\n• Basic math skills\r\n• POS system operation\r\n\r\n⏰ WORK SCHEDULE:\r\n• Flexible hours\r\n\r\n🎁 ADDITIONAL BENEFITS:\r\n• Transportation allowance\r\n\r\n📄 REQUIRED DOCUMENTS:\r\n• Resume\r\n• ID Card\r\n\r\n🗣️ LANGUAGE REQUIREMENTS:\r\n• Malay\r\n\r\n📧 Interested candidates, please apply now!', '?? Handle cash and card transactions\r, ?? Process customer purchases accurately\r, ?? Provide excellent customer service\r, ?? Maintain cleanliness of checkout area\r, ?? Balance cash register at end of shift\r, ?? Answer customer inquiries\r, ?? communication', '?? Cash handling\r, ?? Customer service\r, ?? Attention to detail\r, ?? Basic math skills\r, ?? POS system operation', '?? Flexible hours', '?? Transportation allowance', 'Per Project', 'Permanent', 'Senior', 'Resume,ID Card', 'Malay', 0, '2025-11-19 03:25:24', '2025-11-19 03:25:24');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`attempt_id`, `email`, `ip_address`, `attempt_time`) VALUES
(4, 'admin01@gmail.com', '::1', '2025-11-10 14:58:31'),
(5, 'nurrashidah.alias@gmail.com', '::1', '2025-11-10 15:02:01');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `id` int(11) GENERATED ALWAYS AS (`message_id`) VIRTUAL,
  `conversation_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `type` enum('text','image','audio','file') NOT NULL DEFAULT 'text',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `job_id` int(11) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `conversation_context` varchar(50) DEFAULT 'job_application' COMMENT 'job_application, job_inquiry, general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `conversation_id`, `sender_id`, `receiver_id`, `message`, `edited`, `edited_at`, `type`, `sent_at`, `job_id`, `timestamp`, `is_read`, `conversation_context`) VALUES
(3, NULL, 15, 16, 'kenapa ko reject ako', 0, NULL, 'text', '2025-11-15 06:26:43', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(4, NULL, 15, 16, 'dffpfp', 0, NULL, 'text', '2025-11-18 15:04:55', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(5, NULL, 15, 16, 's', 0, NULL, 'text', '2025-11-18 15:12:14', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(8, NULL, 15, 16, 'q', 0, NULL, 'text', '2025-11-18 15:28:50', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(9, NULL, 15, 16, 'ds', 0, NULL, 'text', '2025-11-18 15:28:54', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(10, NULL, 15, 16, 'heloo', 0, NULL, 'text', '2025-11-18 15:30:48', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(11, NULL, 15, 16, 'hello', 0, NULL, 'text', '2025-11-18 15:49:12', NULL, '2026-01-05 13:57:51', 1, 'job_application'),
(12, NULL, 16, 15, 'uploads/chat/file_1767705029_695d09c51fd3b.jpeg', 0, NULL, 'image', '2026-01-06 13:10:29', NULL, '2026-01-06 21:10:29', 1, 'job_application'),
(34, NULL, 15, 16, 'hello, saya nak jadi cleaner', 0, NULL, 'text', '2026-01-06 17:52:44', NULL, '2026-01-07 01:52:44', 1, 'job_application'),
(35, NULL, 15, 16, 'saya nak cashier', 0, NULL, 'text', '2026-01-06 17:53:51', NULL, '2026-01-07 01:53:51', 1, 'job_application'),
(36, NULL, 15, 23, 'hye', 0, NULL, 'text', '2026-01-06 18:41:16', NULL, '2026-01-07 02:41:16', 1, 'general'),
(37, NULL, 15, 16, 'hello', 0, NULL, 'text', '2026-01-06 19:04:11', NULL, '2026-01-07 03:04:11', 1, 'general'),
(38, NULL, 15, 23, 'ee', 0, NULL, 'text', '2026-01-06 19:05:49', NULL, '2026-01-07 03:05:49', 1, 'job_application'),
(40, NULL, 15, 23, 'hello', 0, NULL, 'text', '2026-01-06 19:13:58', NULL, '2026-01-07 03:13:58', 1, 'job_application'),
(55, NULL, 16, 15, 'hello', 0, NULL, 'text', '2026-01-07 23:19:19', NULL, '2026-01-08 07:19:19', 1, 'job_application'),
(56, NULL, 16, 15, 'jk\'', 0, NULL, 'text', '2026-01-07 23:21:09', NULL, '2026-01-08 07:21:09', 1, 'job_application'),
(57, NULL, 16, 15, 'jnkl', 0, NULL, 'text', '2026-01-07 23:21:14', NULL, '2026-01-08 07:21:14', 1, 'job_application'),
(71, NULL, 15, 16, 'hello', 0, NULL, 'text', '2026-01-08 02:43:14', NULL, '2026-01-08 10:43:14', 1, 'job_application'),
(72, NULL, 15, 16, 'hello', 0, NULL, 'text', '2026-01-08 03:04:59', NULL, '2026-01-08 11:04:59', 1, 'job_application'),
(73, NULL, 15, 23, 'hgello', 0, NULL, 'text', '2026-01-08 03:18:06', NULL, '2026-01-08 11:18:06', 1, 'job_application'),
(74, NULL, 15, 23, 'ced', 0, NULL, 'text', '2026-01-08 03:18:37', NULL, '2026-01-08 11:18:37', 1, 'job_application'),
(75, NULL, 23, 15, 'dfdw', 0, NULL, 'text', '2026-01-08 03:25:13', NULL, '2026-01-08 11:25:13', 1, 'job_application'),
(104, NULL, 16, 15, 'hello', 0, NULL, 'text', '2026-01-08 10:43:52', NULL, '2026-01-08 18:43:52', 1, 'job_application'),
(109, NULL, 16, 15, 'hello', 0, NULL, 'text', '2026-01-08 23:18:56', NULL, '2026-01-09 07:18:56', 1, 'job_application'),
(113, NULL, 15, 16, 'uploads/chat/6963360e7343e_1768109582.webm', 0, NULL, 'audio', '2026-01-11 05:33:02', NULL, '2026-01-11 13:33:02', 1, 'job_application'),
(114, NULL, 15, 16, 'uploads/chat/6963be9b28267_1768144539.webm', 0, NULL, 'audio', '2026-01-11 15:15:39', NULL, '2026-01-11 23:15:39', 1, 'job_application'),
(115, NULL, 15, 16, 'hello', 0, NULL, 'text', '2026-01-11 15:28:05', NULL, '2026-01-11 23:28:05', 1, 'job_application'),
(121, NULL, 15, 16, 'uploads/chat/6963d57a2db6d_1768150394.png', 0, NULL, 'image', '2026-01-11 16:53:14', NULL, '2026-01-12 00:53:14', 1, 'job_application'),
(122, NULL, 15, 16, 'uploads/chat/6963d59297a63_1768150418.jpeg', 0, NULL, 'image', '2026-01-11 16:53:38', NULL, '2026-01-12 00:53:38', 1, 'job_application'),
(123, NULL, 23, 26, 'helloooo', 0, NULL, 'text', '2026-01-15 07:33:35', 44, '2026-01-15 15:33:35', 1, 'job_application'),
(124, NULL, 26, 23, 'uploads/chat/697c0a9592d24_1769736853.jpeg', 0, NULL, 'image', '2026-01-30 01:34:13', 44, '2026-01-30 09:34:13', 0, 'job_application');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `days_worked` int(11) DEFAULT NULL,
  `payment_method` enum('Cash','Bank Transfer','E-Wallet','Cheque','Online Banking') NOT NULL,
  `payment_status` enum('Pending','Processing','Completed','Failed','Disputed') DEFAULT 'Pending',
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `work_start_date` date NOT NULL,
  `work_end_date` date NOT NULL,
  `payment_due_date` date NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `employer_notes` text DEFAULT NULL,
  `worker_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_records`
--

CREATE TABLE `payment_records` (
  `payment_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Worker ID',
  `employer_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `total_hours` decimal(6,2) NOT NULL,
  `hourly_rate` decimal(8,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `bonus_amount` decimal(10,2) DEFAULT 0.00,
  `deduction_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','approved','processing','paid','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT 'Employer user_id who approved',
  `approved_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_records`
--

INSERT INTO `payment_records` (`payment_id`, `application_id`, `user_id`, `employer_id`, `job_id`, `total_hours`, `hourly_rate`, `total_amount`, `bonus_amount`, `deduction_amount`, `final_amount`, `payment_status`, `payment_method`, `payment_reference`, `payment_date`, `approved_by`, `approved_date`, `notes`, `created_at`, `updated_at`) VALUES
(5, 30, 15, 23, 36, 1.85, 50.00, 92.50, 0.00, 0.00, 92.50, 'paid', 'cash', '', '2026-01-11 13:22:50', 23, '2026-01-11 13:22:50', '', '2026-01-11 05:22:50', '2026-01-11 05:22:50'),
(6, 31, 15, 23, 37, 0.82, 50.00, 41.00, 0.00, 0.00, 41.00, 'paid', 'cash', '', '2026-01-11 13:25:49', 23, '2026-01-11 13:25:49', '', '2026-01-11 05:25:49', '2026-01-11 05:25:49'),
(8, 38, 15, 23, 44, 1.93, 10.00, 19.30, 0.00, 0.00, 19.30, 'paid', 'bank_transfer', '', '2026-01-15 15:33:03', 23, '2026-01-15 15:33:03', '', '2026-01-15 07:33:03', '2026-01-15 07:33:03'),
(11, 40, 15, 23, 45, 0.02, 50.00, 1.00, 0.00, 0.00, 1.00, 'paid', 'cash', '', '2026-01-21 13:07:26', 23, '2026-01-21 13:07:26', '', '2026-01-21 05:07:26', '2026-01-21 05:07:26');

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `rating` float DEFAULT 0,
  `photo_url` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `experience` varchar(50) DEFAULT NULL,
  `resume_url` varchar(255) DEFAULT NULL,
  `university` varchar(255) DEFAULT NULL,
  `is_student` tinyint(1) DEFAULT 0,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `major` varchar(255) DEFAULT NULL,
  `edu_duration` varchar(100) DEFAULT NULL,
  `avg_rating` decimal(2,1) DEFAULT 0.0 COMMENT 'Average rating from all feedback',
  `total_reviews` int(11) DEFAULT 0 COMMENT 'Total number of reviews received',
  `rating_breakdown` text DEFAULT NULL COMMENT 'JSON object storing rating breakdown',
  `last_rating_update` timestamp NULL DEFAULT NULL COMMENT 'Last time ratings were recalculated',
  `total_ratings` int(11) DEFAULT 0 COMMENT 'Total ratings received'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`profile_id`, `user_id`, `name`, `profile_picture`, `phone`, `address`, `bio`, `rating`, `photo_url`, `skills`, `experience`, `resume_url`, `university`, `is_student`, `date_of_birth`, `gender`, `district`, `location`, `major`, `edu_duration`, `avg_rating`, `total_reviews`, `rating_breakdown`, `last_rating_update`, `total_ratings`) VALUES
(1, 15, 'Nur Rashidah binti Alias', NULL, '010-7611076', 'NO.34 KKAKF, Kinabatangan', 'Motivated and eager to learn, I am seeking opportunities to gain practical experience in the hospitality industry. I am a reliable team player with strong communication skills and a positive attitude.', 0, 'uploads/photos/profile_15_1767715108.jpg', 'Teamwork, Time Management', 'entry', 'uploads/resumes/resume_15_1768141903.pdf', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 5.0, 0, NULL, NULL, 2),
(2, 16, 'Yayasan Sukarelawan Siswa', NULL, '010-7611076', 'kota marudu, Kota Marudu', 'BIG VOLUNTEERING COMPANY', 0, 'uploads/photos/profile_16_1767727463.png', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 3.0, 0, NULL, NULL, 1),
(8, 17, 'Elly Rachel Marcus', NULL, '010-7611076', 'KKAKF\r\n', 'I am the developer of this website\r\n', 0, 'uploads/profile_17_695b345f0a75e.jpg', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'Kota Kinabalu', NULL, NULL, NULL, 0.0, 0, NULL, NULL, 0),
(9, 23, 'Petronas Sabah', NULL, '011-26317815', 'Menara 1, Menara Berkembar Petronas, Pusat Bandaraya Kuala Lumpur, 50088 Kuala Lumpur, Malaysia', 'Petronas Sabah is a dynamic company in the agriculture sector, based in Ranau. We are committed to providing quality services and creating a positive work environment. We value our employees and offer opportunities for growth and professional development. Join our team and be part of our success story!', 0, 'uploads/photos/profile_23_1767705241.png', NULL, NULL, NULL, NULL, 0, '2010-12-24', 'female', 'Ranau', NULL, NULL, NULL, 4.0, 0, NULL, NULL, 1),
(13, 26, 'Zarra Soffea', NULL, '010-7611076', '76, Lorong Taman Teluk Villa 1\r\n89500 Penampang, Sabah\r\n\r\n', 'Hi! I\'m Zarra, currently studying at Universiti Malaysia Sabah (UMS), based in Kota Kinabalu. I\'m a motivated student seeking flexible part-time opportunities to gain practical work experience while managing my academic commitments. I\'m eager to start my career and learn from experienced professionals. I\'m a reliable team player with strong communication skills and a positive attitude, ready to contribute to your team\'s success.', 0, 'uploads/photos/profile_26_1769736642.jpeg', 'Communication, Teamwork', 'junior', 'uploads/resumes/resume_26_1769736768.pdf', 'Universiti Malaysia Sabah (UMS)', 1, '2002-07-08', 'female', 'Kota Kinabalu', NULL, 'Network Engineering', '2021-2025', 0.0, 0, NULL, NULL, 0),
(14, 27, 'Aniq Zamzamy', NULL, '017-6394119', NULL, 'Hi! I\'m Aniq, currently studying at Universiti Malaysia Pahang (UMP), based in Kinabatangan. I\'m a motivated student seeking flexible part-time opportunities to gain practical work experience while managing my academic commitments. I\'m eager to start my career and learn from experienced professionals. I\'m a reliable team player with strong communication skills and a positive attitude, ready to contribute to your team\'s success.', 0, NULL, NULL, 'entry', NULL, 'Universiti Malaysia Pahang (UMP)', 1, '2001-11-09', 'male', 'Kinabatangan', NULL, NULL, NULL, 0.0, 0, NULL, NULL, 0),
(15, 28, 'SYLIO', NULL, '', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'Not Set', NULL, NULL, NULL, 0.0, 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('worker','employer','admin') NOT NULL DEFAULT 'worker',
  `status` enum('active','blocked','suspended') NOT NULL DEFAULT 'active',
  `last_activity` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `role`, `status`, `last_activity`, `created_at`) VALUES
(15, NULL, 'nurrashidah.alias@gmail.com', '$2y$10$wymSqKyOmAOG4psnXDBbzeBw3/6773vVqDANbZ0jYIyZyKe.B/kN2', 'worker', 'active', '2026-01-29 14:37:49', '2025-11-10 17:24:53'),
(16, NULL, 'sylioanderfrisca@gmail.com', '$2y$10$vtSQCm1128b6BEFevJtY5OV6SwRmWNRiQVlVRf8eNzB8vf4iIdGwG', 'employer', 'active', '2026-01-11 17:05:14', '2025-11-11 00:00:49'),
(17, NULL, 'admin01@gmail.com', '$2y$10$Kh.LbsGQt/u7cY5UGh3X1eV0daSDL6wX7x0pAh.tBI2JXZ/C/7iqm', 'admin', 'active', NULL, '2025-11-15 12:37:28'),
(23, NULL, 'petronassbh@gmail.com', '$2y$10$LyrPKlbEI35s3r6N8Fzk7eXf1BlCYMrCBpk85YSPPbIx1rybZjFvy', 'employer', 'active', '2026-01-21 09:11:57', '2026-01-06 13:14:01'),
(26, NULL, 'zarrasoffea@gmail.com', '$2y$10$DnvE/UD.LGyLhj.JzwEkL.kPnr4oaBthLTxhKSbEGNZ88T5uoAZLu', 'worker', 'active', '2026-01-30 01:34:08', '2026-01-15 06:38:56'),
(27, NULL, 'aniqzamzamy@gmail.com', '$2y$10$sVMEi/va6JHyRXHUq3Z9Q.daVL09bPDV9B4K6PfotDbcQe1JpKqCq', 'worker', 'active', NULL, '2026-01-21 12:42:34'),
(28, NULL, 'admin02@gmail.com', '$2y$10$zq98lDCTZ2ZUbsp/9AqqtemrgBN2XP9WQ7EG9y/yTj9HtgQpQk3zu', 'admin', 'active', NULL, '2026-01-21 13:28:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `idx_applications_job` (`job_id`),
  ADD KEY `idx_applications_worker` (`user_id`),
  ADD KEY `idx_applications_status` (`status`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_employer_id` (`employer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_clock_in_time` (`clock_in_time`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD UNIQUE KEY `unique_conv` (`job_id`,`employer_id`,`worker_id`),
  ADD KEY `employer_id` (`employer_id`),
  ADD KEY `worker_id` (`worker_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `unique_feedback` (`application_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `reviewee_id` (`reviewee_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_is_urgent` (`is_urgent`),
  ADD KEY `idx_experience_level` (`experience_level`),
  ADD KEY `idx_jobs_employer` (`employer_id`),
  ADD KEY `idx_jobs_status` (`status`);

--
-- Indexes for table `job_completion_log`
--
ALTER TABLE `job_completion_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_application` (`application_id`),
  ADD KEY `idx_job` (`job_id`),
  ADD KEY `idx_worker` (`worker_id`),
  ADD KEY `idx_employer` (`employer_id`),
  ADD KEY `idx_completed_at` (`completed_at`);

--
-- Indexes for table `job_templates`
--
ALTER TABLE `job_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD KEY `idx_employer` (`employer_id`),
  ADD KEY `idx_is_default` (`is_default`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_email_ip` (`email`,`ip_address`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `idx_messages_job_sender_receiver` (`job_id`,`sender_id`,`receiver_id`),
  ADD KEY `idx_messages_timestamp` (`timestamp`),
  ADD KEY `idx_messages_type` (`type`),
  ADD KEY `idx_job_conversations` (`job_id`,`sender_id`,`receiver_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `employer_id` (`employer_id`),
  ADD KEY `worker_id` (`worker_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_employer_id` (`employer_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD KEY `idx_profiles_user_id` (`user_id`),
  ADD KEY `idx_profiles_is_student` (`is_student`),
  ADD KEY `idx_profiles_district` (`district`),
  ADD KEY `idx_avg_rating` (`avg_rating`),
  ADD KEY `idx_rating` (`avg_rating`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `job_completion_log`
--
ALTER TABLE `job_completion_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `job_templates`
--
ALTER TABLE `job_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_records`
--
ALTER TABLE `payment_records`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `profiles`
--
ALTER TABLE `profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`worker_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_reviewee` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_completion_log`
--
ALTER TABLE `job_completion_log`
  ADD CONSTRAINT `job_completion_log_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_completion_log_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_completion_log_ibfk_3` FOREIGN KEY (`worker_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_completion_log_ibfk_4` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_templates`
--
ALTER TABLE `job_templates`
  ADD CONSTRAINT `job_templates_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_4` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`worker_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD CONSTRAINT `payment_records_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_records_ibfk_3` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_records_ibfk_4` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `profiles`
--
ALTER TABLE `profiles`
  ADD CONSTRAINT `profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
