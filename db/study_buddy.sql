-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 10:29 AM
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
-- Database: `study_mentorship_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'user, session, match, etc',
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'bulk_unverify', 'user', NULL, '{\"user_ids\":[\"138\"]}', '::1', '2025-10-09 09:16:50');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','alert') DEFAULT 'info',
  `target_audience` enum('all','students','mentors','peers') DEFAULT 'all',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `type`, `target_audience`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'ERROR', 'asdasd', 'warning', 'students', 1, 1, '2025-10-09 10:04:20', '2025-10-09 10:04:23'),
(3, 'ERROR', 'asdasd', 'warning', 'all', 1, 1, '2025-10-09 10:04:41', '2025-10-09 10:04:41'),
(4, 'ERROR', 'Hello guys', 'warning', 'all', 1, 1, '2025-10-16 14:34:28', '2025-10-16 14:34:28'),
(5, 'ERROR', 'lezzgaw gais', 'warning', 'all', 1, 1, '2025-10-16 14:38:16', '2025-10-16 14:38:16'),
(6, 'FREE FOR ALL', 'nyek', 'warning', 'all', 1, 1, '2025-10-17 03:16:10', '2025-10-17 03:16:10'),
(7, 'error occured', 'onke', 'alert', 'all', 1, 1, '2025-10-17 03:19:41', '2025-10-17 03:19:41'),
(8, 'working', 'working', 'alert', 'all', 1, 1, '2025-10-17 03:30:58', '2025-10-17 03:30:58');

-- --------------------------------------------------------

--
-- Table structure for table `commission_payments`
--

CREATE TABLE `commission_payments` (
  `id` int(11) NOT NULL,
  `session_payment_id` int(11) DEFAULT NULL,
  `mentor_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `commission_percentage` decimal(5,2) DEFAULT 10.00,
  `payment_status` enum('awaiting_payment','proof_submitted','verified','rejected','overdue') DEFAULT 'awaiting_payment',
  `proof_of_payment` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `payment_deadline` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `session_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mentor_gcash_number` varchar(20) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_payments`
--

INSERT INTO `commission_payments` (`id`, `session_payment_id`, `mentor_id`, `session_id`, `commission_amount`, `commission_percentage`, `payment_status`, `proof_of_payment`, `payment_method`, `payment_date`, `verified_by`, `verified_at`, `rejection_reason`, `payment_deadline`, `created_at`, `updated_at`, `session_amount`, `mentor_gcash_number`, `reference_number`) VALUES
(1, 1, 148, NULL, 10.00, 10.00, 'awaiting_payment', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-13 12:05:16', '2025-10-11 04:05:16', '2025-10-11 04:05:16', 0.00, NULL, NULL),
(7, NULL, 171, 64, 15.00, 10.00, 'verified', NULL, NULL, '2025-10-13 12:36:57', 1, '2025-10-13 12:40:09', NULL, '0000-00-00 00:00:00', '2025-10-13 04:11:50', '2025-10-13 04:40:09', 150.00, '09493297963', '4323234'),
(8, NULL, 171, 65, 15.00, 10.00, 'verified', NULL, NULL, '2025-10-13 13:14:36', 1, '2025-10-13 13:16:05', 'no referrence', '0000-00-00 00:00:00', '2025-10-13 05:08:10', '2025-10-13 05:16:05', 150.00, '09298645389', '1234123537865'),
(9, NULL, 171, 66, 15.00, 10.00, '', NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '2025-10-14 02:18:07', '2025-10-14 02:18:07', 150.00, NULL, NULL),
(10, NULL, 173, 69, 20.00, 10.00, '', NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '2025-10-15 12:49:15', '2025-10-15 12:49:15', 200.00, NULL, NULL),
(11, NULL, 147, 70, 20.00, 10.00, '', NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '2025-10-15 13:05:51', '2025-10-15 13:05:51', 200.00, NULL, NULL),
(12, NULL, 147, 71, 20.00, 10.00, '', NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '2025-10-15 13:09:30', '2025-10-15 13:09:30', 200.00, NULL, NULL),
(13, NULL, 166, 72, 15.00, 10.00, '', NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '2025-10-15 13:19:28', '2025-10-15 13:19:28', 150.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_settings`
--

CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` varchar(255) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL DEFAULT 'StudyConnect',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `variables` text DEFAULT NULL COMMENT 'JSON array of available variables',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_name`, `subject`, `body`, `variables`, `created_at`, `updated_at`) VALUES
(1, 'welcome_email', 'Welcome to {{platform_name}}!', 'Hi {{user_name}},\n\nWelcome to {{platform_name}}! We\'re excited to have you join our community.\n\nGet started by completing your profile and finding your first study partner.\n\nBest regards,\nThe {{platform_name}} Team', '[\"user_name\", \"platform_name\"]', '2025-10-09 09:13:07', '2025-10-09 09:13:07'),
(2, 'session_reminder', 'Session Reminder: {{session_subject}}', 'Hi {{user_name}},\n\nThis is a reminder that you have a session scheduled:\n\nSubject: {{session_subject}}\nDate: {{session_date}}\nTime: {{session_time}}\nWith: {{partner_name}}\n\nBest regards,\nThe {{platform_name}} Team', '[\"user_name\", \"session_subject\", \"session_date\", \"session_time\", \"partner_name\", \"platform_name\"]', '2025-10-09 09:13:07', '2025-10-09 09:13:07'),
(3, 'match_accepted', 'Your match request was accepted!', 'Hi {{user_name}},\n\n{{partner_name}} has accepted your match request! You can now start messaging and schedule study sessions.\n\nBest regards,\nThe {{platform_name}} Team', '[\"user_name\", \"partner_name\", \"platform_name\"]', '2025-10-09 09:13:07', '2025-10-09 09:13:07');

-- --------------------------------------------------------

--
-- Table structure for table `footer_settings`
--

CREATE TABLE `footer_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `footer_settings`
--

INSERT INTO `footer_settings` (`id`, `setting_key`, `setting_value`, `updated_at`, `updated_by`) VALUES
(1, 'platform_name', 'StudyBuddy', '2025-10-05 13:44:58', 1),
(2, 'platform_tagline', 'Empowering students worldwide through peer-to-peer learning and meaningful academic connections.', '2025-10-05 13:44:58', 1),
(3, 'facebook_url', 'https://www.facebook.com/share/1C41hVgJq7/', '2025-10-05 13:44:58', 1),
(4, 'twitter_url', '', '2025-10-05 13:44:58', 1),
(5, 'instagram_url', '', '2025-10-05 13:44:58', 1),
(6, 'linkedin_url', '', '2025-10-05 13:44:58', 1),
(7, 'contact_email', 'support@studyconnect.com', '2025-10-05 13:44:58', 1),
(8, 'contact_phone', '09493297963', '2025-10-05 13:44:58', 1),
(9, 'show_social_links', '1', '2025-10-05 13:44:58', 1),
(10, 'copyright_text', 'StudyBuddy. All rights reserved.', '2025-10-05 13:44:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `status` enum('pending','accepted','rejected','completed') DEFAULT 'pending',
  `match_score` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `requester_role` enum('student','mentor','peer') DEFAULT 'student',
  `responder_role` enum('student','mentor','peer') DEFAULT 'mentor',
  `teaching_user_id` int(11) DEFAULT NULL,
  `learning_user_id` int(11) DEFAULT NULL,
  `requester_id` int(11) NOT NULL DEFAULT 0,
  `helper_id` int(11) NOT NULL DEFAULT 0,
  `match_type` enum('peer_tutoring','traditional_mentoring') DEFAULT 'peer_tutoring'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `student_id`, `mentor_id`, `subject`, `status`, `match_score`, `created_at`, `updated_at`, `requester_role`, `responder_role`, `teaching_user_id`, `learning_user_id`, `requester_id`, `helper_id`, `match_type`) VALUES
(1, 4, 3, 'English', 'accepted', NULL, '2025-09-19 14:25:00', '2025-09-20 11:46:08', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(2, 5, 4, 'History', 'pending', 9.99, '2025-09-19 15:14:18', '2025-09-19 15:14:18', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(3, 6, 5, 'History', 'pending', 9.99, '2025-09-20 11:59:43', '2025-09-20 11:59:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(4, 6, 7, 'History', 'accepted', 9.99, '2025-09-20 12:00:00', '2025-09-20 12:00:41', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(5, 9, 4, 'Filipino', 'pending', 9.99, '2025-09-21 13:09:52', '2025-09-21 13:09:52', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(6, 8, 4, 'Filipino', 'accepted', 9.99, '2025-09-21 13:19:45', '2025-09-21 13:34:05', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(7, 8, 6, 'Filipino', 'accepted', 9.99, '2025-09-21 13:24:53', '2025-09-21 14:10:54', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(8, 3, 10, 'English', 'pending', 9.99, '2025-09-21 13:42:41', '2025-09-21 13:42:41', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(9, 11, 9, 'English', 'pending', 9.99, '2025-09-21 13:46:43', '2025-09-21 13:46:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(10, 12, 6, 'Science', 'pending', 9.99, '2025-09-21 13:47:42', '2025-09-21 13:47:42', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(11, 8, 11, 'Filipino', 'rejected', 9.99, '2025-09-21 14:10:14', '2025-09-21 14:10:53', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(12, 13, 6, 'History', 'pending', 9.99, '2025-09-21 14:11:51', '2025-09-21 14:11:51', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(13, 14, 6, 'English', 'accepted', 9.99, '2025-09-21 14:16:02', '2025-09-21 14:16:18', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(14, 16, 8, 'Filipino', 'accepted', 9.99, '2025-09-21 14:40:34', '2025-09-21 17:44:06', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(15, 8, 11, 'Filipino', 'accepted', 9.99, '2025-09-21 17:47:19', '2025-09-21 17:47:41', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(16, 8, 3, 'Filipino', 'accepted', 9.99, '2025-09-21 17:49:25', '2025-09-21 17:49:48', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(17, 8, 18, 'Filipino', 'pending', 9.99, '2025-09-21 19:15:44', '2025-09-21 19:15:44', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(18, 8, 18, 'Filipino', 'pending', 9.99, '2025-09-21 19:19:56', '2025-09-21 19:19:56', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(19, 8, 18, 'Filipino', 'pending', 9.99, '2025-09-21 19:20:15', '2025-09-21 19:20:15', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(20, 8, 18, 'Filipino', 'accepted', 9.99, '2025-09-21 19:20:24', '2025-09-22 03:52:58', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(22, 4, 14, 'Filipino', 'accepted', 9.99, '2025-09-22 03:19:11', '2025-09-24 05:42:39', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(24, 4, 20, 'English', 'accepted', 9.99, '2025-09-22 05:19:08', '2025-09-22 05:19:50', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(28, 3, 15, 'English', 'accepted', 9.99, '2025-09-23 10:52:10', '2025-09-23 10:52:15', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(29, 3, 33, '', 'pending', 9.99, '2025-09-23 11:07:41', '2025-09-23 11:07:41', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(30, 3, 14, '', 'pending', 9.99, '2025-09-23 11:08:00', '2025-09-23 11:08:00', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(31, 3, 5, '', 'accepted', 9.99, '2025-09-23 11:09:21', '2025-09-23 11:09:31', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(32, 40, 10, 'History', 'pending', 9.99, '2025-09-24 05:41:47', '2025-09-24 05:41:47', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(33, 4, 40, 'English', 'pending', 9.99, '2025-09-24 05:42:52', '2025-09-24 05:42:52', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(34, 40, 5, 'English', 'pending', 9.99, '2025-09-24 05:44:00', '2025-09-24 05:44:00', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(35, 7, 40, 'History', 'pending', 9.99, '2025-09-24 05:45:08', '2025-09-24 05:45:08', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(36, 40, 15, 'English', 'accepted', 9.99, '2025-09-24 05:48:27', '2025-09-24 05:49:11', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(37, 43, 40, 'Geography', 'accepted', 9.99, '2025-09-24 07:12:48', '2025-09-24 07:13:06', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(38, 45, 44, 'Geography', 'accepted', 9.99, '2025-09-24 07:35:06', '2025-09-25 01:28:31', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(39, 46, 4, 'Filipino', 'accepted', 9.99, '2025-09-24 07:48:21', '2025-09-28 18:39:15', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(40, 50, 44, 'History', 'accepted', 9.99, '2025-09-24 09:09:28', '2025-09-25 01:21:44', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(41, 51, 44, 'Programming', 'accepted', 9.99, '2025-09-24 09:28:57', '2025-09-25 01:21:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(42, 40, 42, 'History', 'accepted', 9.99, '2025-09-25 01:11:35', '2025-09-25 06:10:06', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(43, 40, 13, 'English', 'rejected', 9.99, '2025-09-25 01:11:49', '2025-09-30 05:36:37', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(44, 40, 37, 'English', 'pending', 9.99, '2025-09-25 01:12:24', '2025-09-25 01:12:24', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(45, 40, 33, 'English', 'pending', 9.99, '2025-09-25 01:13:26', '2025-09-25 01:13:26', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(46, 40, 33, 'English', 'pending', 9.99, '2025-09-25 01:17:13', '2025-09-25 01:17:13', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(47, 40, 44, 'History', 'accepted', 9.99, '2025-09-25 01:17:25', '2025-09-25 01:21:39', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(48, 40, 6, 'English', 'pending', 9.99, '2025-09-25 01:17:31', '2025-09-25 01:17:31', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(49, 44, 12, 'Science', 'pending', 9.99, '2025-09-25 01:28:44', '2025-09-25 01:28:44', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(50, 44, 12, 'Science', 'pending', 9.99, '2025-09-25 01:32:10', '2025-09-25 01:32:10', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(51, 44, 42, 'Science', 'pending', 9.99, '2025-09-25 01:32:16', '2025-09-25 01:32:16', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(52, 44, 13, 'History', 'pending', 9.99, '2025-09-25 01:32:34', '2025-09-25 01:32:34', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(53, 56, 55, 'Geography', 'pending', 9.99, '2025-09-25 06:42:55', '2025-09-25 06:42:55', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(54, 56, 55, 'Geography', 'rejected', 9.99, '2025-09-25 06:47:24', '2025-09-26 05:41:28', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(55, 56, 54, 'Geography', 'rejected', 9.99, '2025-09-25 06:47:33', '2025-09-25 12:42:47', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(56, 56, 43, 'Geography', 'accepted', 9.99, '2025-09-25 06:47:39', '2025-09-25 12:42:44', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(57, 64, 53, 'History', 'accepted', 9.99, '2025-09-25 12:29:53', '2025-09-25 12:31:54', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(58, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:31', '2025-09-26 06:01:31', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(59, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:33', '2025-09-26 06:01:33', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(60, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:35', '2025-09-26 06:01:35', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(61, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:37', '2025-09-26 06:01:37', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(62, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:39', '2025-09-26 06:01:39', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(63, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:41', '2025-09-26 06:01:41', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(64, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:43', '2025-09-26 06:01:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(65, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:45', '2025-09-26 06:01:45', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(66, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:48', '2025-09-26 06:01:48', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(67, 40, 63, 'English', 'pending', 9.99, '2025-09-26 06:01:50', '2025-09-26 06:01:50', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(68, 65, 64, 'Programming - C++', 'pending', 9.99, '2025-09-26 12:15:38', '2025-09-26 12:15:38', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(69, 66, 64, 'Programming - C++', 'pending', 9.99, '2025-09-26 12:33:39', '2025-09-26 12:33:39', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(70, 68, 67, 'C++', 'pending', 9.99, '2025-09-27 11:11:19', '2025-09-27 11:11:19', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(71, 68, 67, 'C++', 'pending', 9.99, '2025-09-27 11:11:43', '2025-09-27 11:11:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(72, 40, 62, 'History', 'pending', 9.99, '2025-09-28 13:14:37', '2025-09-28 13:14:37', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(73, 40, 53, 'History', 'pending', 9.99, '2025-09-28 13:25:31', '2025-09-28 13:25:31', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(74, 40, 54, 'History', 'pending', 9.99, '2025-09-28 14:21:37', '2025-09-28 14:21:37', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(75, 77, 74, 'C++', 'pending', 9.99, '2025-09-30 05:15:27', '2025-09-30 05:15:27', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(76, 77, 74, 'C++', 'pending', 9.99, '2025-09-30 05:22:32', '2025-09-30 05:22:32', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(77, 82, 83, 'Web Development', 'accepted', 9.99, '2025-09-30 13:31:05', '2025-09-30 13:31:39', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(78, 94, 93, 'HTML/CSS', 'accepted', 9.99, '2025-10-01 11:41:30', '2025-10-01 11:41:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(79, 113, 79, 'C++', 'accepted', 9.99, '2025-10-02 15:09:19', '2025-10-02 15:25:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(80, 113, 92, 'C++', 'pending', 9.99, '2025-10-02 15:35:23', '2025-10-02 15:35:23', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(81, 119, 117, 'Algebra', 'pending', 9.99, '2025-10-03 03:24:27', '2025-10-03 03:24:27', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(82, 119, 117, 'Algebra', 'pending', 9.99, '2025-10-03 03:25:20', '2025-10-03 03:25:20', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(83, 119, 83, 'Algebra', 'accepted', 9.99, '2025-10-03 03:25:25', '2025-10-04 12:41:22', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(84, 122, 102, 'Calculus', 'rejected', 9.99, '2025-10-03 12:16:41', '2025-10-03 12:20:44', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(85, 102, 93, 'Calculus', 'rejected', 9.99, '2025-10-03 12:19:25', '2025-10-03 12:20:11', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(86, 93, 91, 'Algebra', 'accepted', 9.99, '2025-10-03 12:23:54', '2025-10-03 12:29:41', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(87, 123, 91, 'Web Development', 'accepted', 9.99, '2025-10-03 12:35:08', '2025-10-03 12:35:22', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(88, 125, 79, 'Mathematics - Calculus', 'accepted', 9.99, '2025-10-04 03:45:30', '2025-10-04 03:48:16', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(89, 79, 76, 'C++', 'pending', 9.99, '2025-10-04 06:02:39', '2025-10-04 06:02:39', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(90, 78, 119, 'Programming - C++', 'accepted', 9.99, '2025-10-04 12:25:45', '2025-10-04 12:26:02', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(91, 127, 113, 'Mathematics - Calculus', 'pending', 9.99, '2025-10-05 06:03:14', '2025-10-05 06:03:14', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(92, 40, 50, 'History', 'pending', 9.99, '2025-10-05 10:01:09', '2025-10-05 10:01:09', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(93, 40, 50, 'History', 'pending', 9.99, '2025-10-05 10:01:51', '2025-10-05 10:01:51', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(94, 82, 132, 'Mathematics - Trigonometry', 'accepted', 9.99, '2025-10-05 10:57:42', '2025-10-05 10:57:54', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(95, 136, 134, 'Trigonometry', 'pending', 9.99, '2025-10-06 16:06:30', '2025-10-06 16:06:30', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(96, 139, 91, 'Algebra', 'accepted', 9.99, '2025-10-07 10:10:51', '2025-10-07 10:11:10', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(97, 140, 139, 'Algebra', 'accepted', 9.99, '2025-10-07 10:40:29', '2025-10-07 10:40:47', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(98, 142, 141, 'Trigonometry', 'accepted', 9.99, '2025-10-08 07:28:16', '2025-10-08 07:28:29', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(99, 162, 161, 'C++', 'accepted', 9.99, '2025-10-09 03:14:29', '2025-10-09 03:15:22', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(100, 147, 148, 'Abnormal Psychology', 'accepted', 9.99, '2025-10-11 03:25:19', '2025-10-11 03:25:33', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(101, 168, 166, 'C++', 'accepted', 9.99, '2025-10-12 03:51:15', '2025-10-12 03:51:36', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(102, 169, 166, 'C++', 'accepted', 9.99, '2025-10-12 04:38:59', '2025-10-12 04:39:43', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(103, 148, 171, 'Psychology - Abnormal Psychology', 'accepted', 9.99, '2025-10-13 04:04:05', '2025-10-13 04:04:19', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(104, 3, 42, 'English', 'pending', 9.99, '2025-10-14 04:45:52', '2025-10-14 04:45:52', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(105, 42, 6, 'History', 'pending', 9.99, '2025-10-14 04:46:53', '2025-10-14 04:46:53', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(106, 138, 137, 'Trigonometry', 'pending', 9.99, '2025-10-14 04:54:23', '2025-10-14 04:54:23', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(107, 137, 143, 'Trigonometry', 'pending', 9.99, '2025-10-14 04:56:27', '2025-10-14 04:56:27', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(108, 165, 88, 'Social Theory', 'pending', 9.99, '2025-10-14 04:59:55', '2025-10-14 04:59:55', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(109, 155, 117, 'Programming - C++', 'pending', 9.99, '2025-10-14 05:01:57', '2025-10-14 05:01:57', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(110, 147, 88, 'Abnormal Psychology', 'pending', 9.99, '2025-10-14 05:13:29', '2025-10-14 05:13:29', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(111, 170, 83, 'C++', 'pending', 9.99, '2025-10-14 05:14:25', '2025-10-14 05:14:25', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(112, 174, 147, 'Abnormal Psychology', 'accepted', 9.99, '2025-10-15 12:39:18', '2025-10-15 12:40:15', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(113, 147, 173, 'Abnormal Psychology', 'accepted', 9.99, '2025-10-15 12:42:36', '2025-10-15 12:43:04', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(114, 175, 147, 'Abnormal Psychology', 'accepted', 9.99, '2025-10-15 12:55:07', '2025-10-15 12:55:50', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(115, 176, 166, 'C++', 'accepted', 9.99, '2025-10-15 13:17:15', '2025-10-15 13:17:45', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(116, 40, 117, 'Programming - C++', 'accepted', 9.99, '2025-10-16 14:31:38', '2025-10-16 14:33:14', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(117, 40, 64, 'Programming - C++', 'pending', 9.99, '2025-10-16 14:40:09', '2025-10-16 14:40:09', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(118, 40, 155, 'Programming - C++', 'pending', 9.99, '2025-10-16 14:40:33', '2025-10-16 14:40:33', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring'),
(119, 40, 78, 'Programming - C++', 'pending', 9.99, '2025-10-17 03:20:37', '2025-10-17 03:20:37', 'student', 'mentor', NULL, NULL, 0, 0, 'peer_tutoring');

-- --------------------------------------------------------

--
-- Table structure for table `match_notifications`
--

CREATE TABLE `match_notifications` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `type` enum('request','response') NOT NULL,
  `status` enum('pending','delivered','seen') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  `seen_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `match_id`, `sender_id`, `message`, `is_read`, `created_at`) VALUES
(1, 13, 14, 'wtf', 1, '2025-09-21 14:16:38'),
(2, 13, 6, 'hello', 1, '2025-09-21 14:16:50'),
(3, 13, 14, 'asdasdas', 0, '2025-09-21 14:18:48'),
(4, 13, 14, 'asdasda', 0, '2025-09-21 14:18:56'),
(5, 13, 14, 'asdasd', 0, '2025-09-21 14:23:10'),
(6, 13, 14, 'asdasd', 0, '2025-09-21 14:26:11'),
(7, 13, 14, 'asdasd', 0, '2025-09-21 14:26:38'),
(8, 13, 14, 'hoy', 0, '2025-09-21 14:27:09'),
(9, 7, 8, 'bobo', 1, '2025-09-21 14:27:39'),
(10, 7, 8, 'dasdas', 1, '2025-09-21 14:32:21'),
(11, 7, 8, 'dasdas', 1, '2025-09-21 14:32:26'),
(12, 7, 8, 'dasdas', 1, '2025-09-21 14:32:32'),
(13, 7, 8, 'dasdas', 1, '2025-09-21 14:32:34'),
(14, 7, 8, 'asdasd', 1, '2025-09-21 14:32:41'),
(15, 7, 8, 'asdas', 1, '2025-09-21 14:33:30'),
(16, 7, 8, 'asdasd', 1, '2025-09-21 14:33:31'),
(17, 7, 8, 'asdasdasd', 1, '2025-09-21 14:33:33'),
(18, 7, 8, 'asdsa', 1, '2025-09-21 14:34:03'),
(19, 7, 8, 'asdsa', 1, '2025-09-21 14:34:06'),
(20, 7, 8, 'asdasd', 1, '2025-09-21 14:35:18'),
(21, 7, 8, 'asds', 1, '2025-09-21 14:35:19'),
(22, 7, 8, 'haha', 1, '2025-09-21 14:35:32'),
(23, 7, 6, 'gege', 1, '2025-09-21 14:35:48'),
(24, 7, 8, 'hehe', 0, '2025-09-21 17:26:52'),
(25, 7, 8, 'lala', 0, '2025-09-21 17:26:53'),
(26, 7, 8, 'anlal niy', 0, '2025-09-21 17:26:55'),
(27, 7, 8, 'sakit mo haha', 0, '2025-09-21 17:26:59'),
(28, 7, 8, 'sakit sakit mo', 0, '2025-09-21 17:27:02'),
(29, 7, 8, 'panalo ka naman 5600', 0, '2025-09-21 17:27:05'),
(30, 16, 8, 'lala di ko magaw', 1, '2025-09-21 17:52:24'),
(31, 16, 8, 'omai na', 1, '2025-09-21 17:52:26'),
(32, 15, 8, 'hays', 0, '2025-09-21 17:52:35'),
(33, 14, 8, 'galing', 0, '2025-09-21 17:52:44'),
(34, 24, 20, 'do it', 0, '2025-09-22 05:19:57'),
(35, 16, 3, 'nyesk', 0, '2025-09-23 10:55:51'),
(36, 4, 7, 'hoy', 0, '2025-09-24 05:44:54'),
(37, 36, 15, 'heyy', 1, '2025-09-24 05:49:15'),
(38, 37, 40, 'wow are u the real sigma', 0, '2025-09-24 07:14:04'),
(39, 31, 5, 'nyeks', 0, '2025-09-25 01:46:54'),
(40, 57, 64, 'test', 0, '2025-09-25 12:34:17'),
(41, 57, 64, 'a', 0, '2025-09-25 12:34:33'),
(42, 37, 40, '\'\'\'\'', 0, '2025-09-26 05:46:21'),
(43, 42, 40, 'not good', 0, '2025-09-27 09:35:05'),
(44, 42, 40, 'hasy\\', 0, '2025-09-30 05:35:51'),
(45, 77, 82, 'ure fucking good', 0, '2025-09-30 13:35:41'),
(46, 78, 94, 'hello', 1, '2025-10-01 11:41:55'),
(47, 78, 93, 'hello', 1, '2025-10-01 11:43:54'),
(48, 78, 94, 'hello', 1, '2025-10-01 11:43:55'),
(49, 78, 94, 'hello', 1, '2025-10-01 11:43:58'),
(50, 78, 93, 'hello', 1, '2025-10-01 11:44:08'),
(51, 78, 94, 'hi', 1, '2025-10-01 11:44:15'),
(52, 78, 93, '', 1, '2025-10-01 12:41:09'),
(53, 78, 94, 'hello', 1, '2025-10-01 12:41:17'),
(54, 78, 94, 'hello', 1, '2025-10-01 12:41:33'),
(55, 78, 94, 'hello', 1, '2025-10-01 12:43:35'),
(56, 78, 94, 'wth', 1, '2025-10-01 12:43:38'),
(57, 88, 79, 'dfsfsf', 0, '2025-10-04 03:49:21'),
(58, 88, 79, 'fsfdsfs', 0, '2025-10-04 03:49:31'),
(59, 88, 79, 'bobo', 0, '2025-10-04 06:01:25'),
(60, 88, 79, 'ka klarens', 0, '2025-10-04 06:01:27'),
(61, 88, 79, 'asdasd', 0, '2025-10-04 06:01:31'),
(62, 88, 79, 'asdasdas', 0, '2025-10-04 06:01:32'),
(63, 88, 79, 'asdasd', 0, '2025-10-04 06:01:33'),
(64, 88, 79, 'ftyftyt', 0, '2025-10-04 06:01:35'),
(65, 90, 119, 'wtf??', 1, '2025-10-04 12:28:23'),
(66, 90, 119, 'wtf??', 1, '2025-10-04 12:28:26'),
(67, 90, 119, 'wtf?', 1, '2025-10-04 12:28:29'),
(68, 90, 119, 'wtf', 1, '2025-10-04 12:28:38'),
(69, 94, 82, 'uo', 1, '2025-10-05 10:58:01'),
(70, 94, 132, 'dinga', 1, '2025-10-05 10:58:06'),
(71, 94, 82, 'uwo', 1, '2025-10-05 10:58:14'),
(72, 42, 40, 'lala', 0, '2025-10-06 12:46:43'),
(73, 42, 40, 'lala', 0, '2025-10-06 12:46:44'),
(74, 42, 40, 'lala', 0, '2025-10-06 12:46:45'),
(75, 42, 40, 'lala', 0, '2025-10-06 12:46:46'),
(76, 42, 40, 'lala', 0, '2025-10-06 12:46:46'),
(77, 101, 166, 'bwakanang bullshit', 0, '2025-10-12 04:17:58'),
(78, 100, 147, 'lala', 0, '2025-10-15 12:55:59'),
(79, 113, 147, 'terenso', 0, '2025-10-15 13:23:56'),
(80, 42, 40, 'asdasd', 0, '2025-10-16 13:34:50'),
(81, 42, 40, 'asdasd', 0, '2025-10-16 13:34:51'),
(82, 42, 40, 'asdasd', 0, '2025-10-16 13:34:51'),
(83, 42, 40, 'asdasd', 0, '2025-10-16 13:34:52'),
(84, 42, 40, 'asdas', 0, '2025-10-16 13:34:53'),
(85, 42, 40, 'asda', 0, '2025-10-16 13:34:59'),
(86, 42, 40, 'ha?', 0, '2025-10-16 13:36:44'),
(87, 42, 40, 'ha?', 0, '2025-10-16 13:36:58'),
(88, 37, 40, 'tunay na kaya', 0, '2025-10-16 13:38:35'),
(89, 37, 40, 'lala', 0, '2025-10-16 13:39:07'),
(90, 37, 40, 'awit', 0, '2025-10-16 13:40:10'),
(91, 37, 40, 'awit', 0, '2025-10-16 13:40:59'),
(92, 37, 40, 'lala', 0, '2025-10-16 13:43:40'),
(93, 37, 40, 'lala', 0, '2025-10-16 13:43:47'),
(94, 37, 40, 'lala', 0, '2025-10-16 13:45:03'),
(95, 37, 40, 'ayaw agad mag send', 0, '2025-10-16 13:45:08'),
(96, 37, 40, 'tunay ba ga', 0, '2025-10-16 13:51:29'),
(97, 37, 40, 'ayaw pa rin', 0, '2025-10-16 13:51:38'),
(98, 37, 40, 'await', 0, '2025-10-16 13:51:47'),
(99, 37, 40, 'awit', 0, '2025-10-16 13:53:17'),
(100, 37, 40, 'awit', 0, '2025-10-16 13:59:40'),
(101, 37, 40, 'awit', 0, '2025-10-16 13:59:44'),
(102, 37, 40, 'anona', 0, '2025-10-16 14:00:11'),
(103, 36, 40, 'sige ganire nalang', 0, '2025-10-16 14:07:54'),
(104, 36, 40, 'diba', 0, '2025-10-16 14:07:57'),
(105, 36, 40, 'tunay na?', 0, '2025-10-16 14:09:52'),
(106, 36, 40, 'nays', 0, '2025-10-16 14:09:59'),
(107, 36, 40, 'ha', 0, '2025-10-16 14:10:02'),
(108, 36, 40, 'pleaseseasesae', 0, '2025-10-16 14:12:11'),
(109, 36, 40, 'lala', 0, '2025-10-16 14:16:31'),
(110, 113, 147, 'awit', 0, '2025-10-17 11:36:12'),
(111, 113, 147, 'awit', 0, '2025-10-17 11:43:58'),
(112, 113, 147, 'asuhdaksuhdasukhdas', 0, '2025-10-17 11:44:00'),
(113, 113, 147, 'awit', 0, '2025-10-17 23:32:45');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('match_request','match_accepted','match_rejected','session_reminder','message') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `data`, `is_read`, `created_at`) VALUES
(1, 10, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"32\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-24 05:41:48'),
(2, 40, 'match_request', 'New Match Request', 'You have a match request from kai cata for English', '{\"match_id\":\"33\",\"student_id\":4,\"student_name\":\"kai cata\",\"subject\":\"English\",\"message\":\"\"}', 1, '2025-09-24 05:42:52'),
(3, 5, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"34\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-24 05:44:00'),
(4, 40, 'match_request', 'New Match Request', 'You have a match request from karrell catapang for History', '{\"match_id\":\"35\",\"student_id\":7,\"student_name\":\"karrell catapang\",\"subject\":\"History\",\"message\":\"\"}', 1, '2025-09-24 05:45:08'),
(5, 15, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"36\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-24 05:48:27'),
(6, 40, 'match_request', 'New Match Request', 'You have a match request from karltzy karl for Geography', '{\"match_id\":\"37\",\"student_id\":43,\"student_name\":\"karltzy karl\",\"subject\":\"Geography\",\"message\":\"\"}', 1, '2025-09-24 07:12:48'),
(7, 44, 'match_request', 'New Match Request', 'You have a match request from kelra as for Geography', '{\"match_id\":\"38\",\"student_id\":45,\"student_name\":\"kelra as\",\"subject\":\"Geography\",\"message\":\"\"}', 0, '2025-09-24 07:35:06'),
(8, 4, 'match_request', 'New Match Request', 'You have a match request from chaknu c for Filipino', '{\"match_id\":\"39\",\"student_id\":46,\"student_name\":\"chaknu c\",\"subject\":\"Filipino\",\"message\":\"\"}', 0, '2025-09-24 07:48:21'),
(9, 44, 'match_request', 'New Match Request', 'You have a match request from demonkite asd for History', '{\"match_id\":\"40\",\"student_id\":50,\"student_name\":\"demonkite asd\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-24 09:09:28'),
(10, 44, 'match_request', 'New Match Request', 'You have a match request from Karrell Balahadia for Programming', '{\"match_id\":\"41\",\"student_id\":51,\"student_name\":\"Karrell Balahadia\",\"subject\":\"Programming\",\"message\":\"\"}', 0, '2025-09-24 09:28:57'),
(11, 42, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"42\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-25 01:11:35'),
(12, 13, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"43\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-25 01:11:49'),
(13, 37, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"44\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-25 01:12:24'),
(14, 33, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"45\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-25 01:13:26'),
(15, 33, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"46\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-25 01:17:13'),
(16, 44, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"47\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-25 01:17:25'),
(17, 6, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"48\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-25 01:17:31'),
(18, 12, 'match_request', 'New Match Request', 'You have a match request from oheb oh for Science', '{\"match_id\":\"49\",\"student_id\":44,\"student_name\":\"oheb oh\",\"subject\":\"Science\",\"message\":\"\"}', 0, '2025-09-25 01:28:44'),
(19, 12, 'match_request', 'New Match Request', 'You have a match request from oheb oh for Science', '{\"match_id\":\"50\",\"student_id\":44,\"student_name\":\"oheb oh\",\"subject\":\"Science\",\"message\":\"\"}', 0, '2025-09-25 01:32:10'),
(20, 42, 'match_request', 'New Match Request', 'You have a match request from oheb oh for Science', '{\"match_id\":\"51\",\"student_id\":44,\"student_name\":\"oheb oh\",\"subject\":\"Science\",\"message\":\"\"}', 0, '2025-09-25 01:32:16'),
(21, 13, 'match_request', 'New Match Request', 'You have a match request from oheb oh for History', '{\"match_id\":\"52\",\"student_id\":44,\"student_name\":\"oheb oh\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-25 01:32:34'),
(22, 55, 'match_request', 'New Match Request', 'You have a match request from iza catapang for Geography', '{\"match_id\":\"53\",\"student_id\":56,\"student_name\":\"iza catapang\",\"subject\":\"Geography\",\"message\":\"\"}', 0, '2025-09-25 06:42:55'),
(23, 55, 'match_request', 'New Match Request', 'You have a match request from iza catapang for Geography', '{\"match_id\":\"54\",\"student_id\":56,\"student_name\":\"iza catapang\",\"subject\":\"Geography\",\"message\":\"\"}', 0, '2025-09-25 06:47:24'),
(24, 54, 'match_request', 'New Match Request', 'You have a match request from iza catapang for Geography', '{\"match_id\":\"55\",\"student_id\":56,\"student_name\":\"iza catapang\",\"subject\":\"Geography\",\"message\":\"\"}', 0, '2025-09-25 06:47:33'),
(25, 43, 'match_request', 'New Match Request', 'You have a match request from iza catapang for Geography', '{\"match_id\":\"56\",\"student_id\":56,\"student_name\":\"iza catapang\",\"subject\":\"Geography\",\"message\":\"\"}', 0, '2025-09-25 06:47:39'),
(26, 53, 'match_request', 'New Match Request', 'You have a match request from test2 test22 for History', '{\"match_id\":\"57\",\"student_id\":64,\"student_name\":\"test2 test22\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-25 12:29:54'),
(27, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"58\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:31'),
(28, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"59\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:33'),
(29, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"60\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:35'),
(30, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"61\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:37'),
(31, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"62\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:39'),
(32, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"63\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:41'),
(33, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"64\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:43'),
(34, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"65\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:46'),
(35, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"66\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:48'),
(36, 63, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for English', '{\"match_id\":\"67\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-09-26 06:01:50'),
(37, 64, 'match_request', 'New Match Request', 'You have a match request from fhukerat fhu for Programming - C++', '{\"match_id\":\"68\",\"student_id\":65,\"student_name\":\"fhukerat fhu\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 0, '2025-09-26 12:15:38'),
(38, 64, 'match_request', 'New Match Request', 'You have a match request from xcv xcv for Programming - C++', '{\"match_id\":\"69\",\"student_id\":66,\"student_name\":\"xcv xcv\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 0, '2025-09-26 12:33:39'),
(39, 67, 'match_request', 'New Match Request', 'You have a match request from marc noe for C++', '{\"match_id\":\"70\",\"student_id\":68,\"student_name\":\"marc noe\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-09-27 11:11:19'),
(40, 67, 'match_request', 'New Match Request', 'You have a match request from marc noe for C++', '{\"match_id\":\"71\",\"student_id\":68,\"student_name\":\"marc noe\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-09-27 11:11:43'),
(41, 62, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"72\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"a\"}', 0, '2025-09-28 13:14:37'),
(42, 53, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"73\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-28 13:25:31'),
(43, 54, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"74\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-09-28 14:21:37'),
(44, 74, 'match_request', 'New Match Request', 'You have a match request from ivan nash for C++', '{\"match_id\":\"75\",\"student_id\":77,\"student_name\":\"ivan nash\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-09-30 05:15:27'),
(45, 74, 'match_request', 'New Match Request', 'You have a match request from ivan nash for C++', '{\"match_id\":\"76\",\"student_id\":77,\"student_name\":\"ivan nash\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-09-30 05:22:32'),
(46, 83, 'match_request', 'New Match Request', 'You have a match request from kai Rie for Web Development', '{\"match_id\":\"77\",\"student_id\":82,\"student_name\":\"kai Rie\",\"subject\":\"Web Development\",\"message\":\"\"}', 0, '2025-09-30 13:31:05'),
(47, 93, 'match_request', 'New Match Request', 'You have a match request from sor mima for HTML/CSS', '{\"match_id\":\"78\",\"student_id\":94,\"student_name\":\"sor mima\",\"subject\":\"HTML\\/CSS\",\"message\":\"\"}', 0, '2025-10-01 11:41:30'),
(48, 79, 'match_request', 'New Match Request', 'You have a match request from nig ga for C++', '{\"match_id\":\"79\",\"student_id\":113,\"student_name\":\"nig ga\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-02 15:09:19'),
(49, 92, 'match_request', 'New Match Request', 'You have a match request from nig ga for C++', '{\"match_id\":\"80\",\"student_id\":113,\"student_name\":\"nig ga\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-02 15:35:23'),
(50, 117, 'match_request', 'New Match Request', 'You have a match request from klowi princess for Algebra', '{\"match_id\":\"81\",\"student_id\":119,\"student_name\":\"klowi princess\",\"subject\":\"Algebra\",\"message\":\"\"}', 1, '2025-10-03 03:24:27'),
(51, 117, 'match_request', 'New Match Request', 'You have a match request from klowi princess for Algebra', '{\"match_id\":\"82\",\"student_id\":119,\"student_name\":\"klowi princess\",\"subject\":\"Algebra\",\"message\":\"\"}', 1, '2025-10-03 03:25:20'),
(52, 83, 'match_request', 'New Match Request', 'You have a match request from klowi princess for Algebra', '{\"match_id\":\"83\",\"student_id\":119,\"student_name\":\"klowi princess\",\"subject\":\"Algebra\",\"message\":\"\"}', 0, '2025-10-03 03:25:25'),
(53, 102, 'match_request', 'New Match Request', 'You have a match request from black jack for Calculus', '{\"match_id\":\"84\",\"student_id\":122,\"student_name\":\"black jack\",\"subject\":\"Calculus\",\"message\":\"\"}', 0, '2025-10-03 12:16:41'),
(54, 93, 'match_request', 'New Match Request', 'You have a match request from jinggoy estrada for Calculus', '{\"match_id\":\"85\",\"student_id\":102,\"student_name\":\"jinggoy estrada\",\"subject\":\"Calculus\",\"message\":\"\"}', 0, '2025-10-03 12:19:25'),
(55, 91, 'match_request', 'New Match Request', 'You have a match request from mima sor for Algebra', '{\"match_id\":\"86\",\"student_id\":93,\"student_name\":\"mima sor\",\"subject\":\"Algebra\",\"message\":\"\"}', 0, '2025-10-03 12:23:54'),
(56, 91, 'match_request', 'New Match Request', 'You have a match request from bbm b for Web Development', '{\"match_id\":\"87\",\"student_id\":123,\"student_name\":\"bbm b\",\"subject\":\"Web Development\",\"message\":\"\"}', 0, '2025-10-03 12:35:08'),
(57, 79, 'match_request', 'New Match Request', 'You have a match request from Clarence Meneses for Mathematics - Calculus', '{\"match_id\":\"88\",\"student_id\":125,\"student_name\":\"Clarence Meneses\",\"subject\":\"Mathematics - Calculus\",\"message\":\"asdasdasda\"}', 0, '2025-10-04 03:45:30'),
(58, 76, 'match_request', 'New Match Request', 'You have a match request from Lloyd Emman for C++', '{\"match_id\":\"89\",\"student_id\":79,\"student_name\":\"Lloyd Emman\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-04 06:02:39'),
(59, 119, 'match_request', 'New Match Request', 'You have a match request from Lloyd emman for Programming - C++', '{\"match_id\":\"90\",\"student_id\":78,\"student_name\":\"Lloyd emman\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 0, '2025-10-04 12:25:45'),
(60, 113, 'match_request', 'New Match Request', 'You have a match request from medi balahadia for Mathematics - Calculus', '{\"match_id\":\"91\",\"student_id\":127,\"student_name\":\"medi balahadia\",\"subject\":\"Mathematics - Calculus\",\"message\":\"\"}', 0, '2025-10-05 06:03:14'),
(61, 50, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"92\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-10-05 10:01:09'),
(62, 50, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for History', '{\"match_id\":\"93\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-10-05 10:01:51'),
(63, 132, 'match_request', 'New Match Request', 'You have a match request from kai Rie for Mathematics - Trigonometry', '{\"match_id\":\"94\",\"student_id\":82,\"student_name\":\"kai Rie\",\"subject\":\"Mathematics - Trigonometry\",\"message\":\"\"}', 0, '2025-10-05 10:57:42'),
(64, 134, 'match_request', 'New Match Request', 'You have a match request from andreoi sevi for Trigonometry', '{\"match_id\":\"95\",\"student_id\":136,\"student_name\":\"andreoi sevi\",\"subject\":\"Trigonometry\",\"message\":\"\"}', 0, '2025-10-06 16:06:31'),
(65, 91, 'match_request', 'New Match Request', 'You have a match request from mema mema for Algebra', '{\"match_id\":\"96\",\"student_id\":139,\"student_name\":\"mema mema\",\"subject\":\"Algebra\",\"message\":\"\"}', 0, '2025-10-07 10:10:51'),
(66, 139, 'match_request', 'New Match Request', 'You have a match request from iraaaa subarashi for Algebra', '{\"match_id\":\"97\",\"student_id\":140,\"student_name\":\"iraaaa subarashi\",\"subject\":\"Algebra\",\"message\":\"\"}', 0, '2025-10-07 10:40:29'),
(67, 141, 'match_request', 'New Match Request', 'You have a match request from ednalyn balahadia for Trigonometry', '{\"match_id\":\"98\",\"student_id\":142,\"student_name\":\"ednalyn balahadia\",\"subject\":\"Trigonometry\",\"message\":\"\"}', 0, '2025-10-08 07:28:16'),
(68, 161, 'match_request', 'New Match Request', 'You have a match request from axel mondagol for C++', '{\"match_id\":\"99\",\"student_id\":162,\"student_name\":\"axel mondagol\",\"subject\":\"C++\",\"message\":\"huhuhu\"}', 0, '2025-10-09 03:14:29'),
(69, 148, 'match_request', 'New Match Request', 'You have a match request from john paul for Abnormal Psychology', '{\"match_id\":\"100\",\"student_id\":147,\"student_name\":\"john paul\",\"subject\":\"Abnormal Psychology\",\"message\":\"\"}', 0, '2025-10-11 03:25:19'),
(70, 166, 'match_request', 'New Match Request', 'You have a match request from saber man for C++', '{\"match_id\":\"101\",\"student_id\":168,\"student_name\":\"saber man\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-12 03:51:15'),
(71, 166, 'match_request', 'New Match Request', 'You have a match request from yve yve for C++', '{\"match_id\":\"102\",\"student_id\":169,\"student_name\":\"yve yve\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-12 04:39:00'),
(72, 171, 'match_request', 'New Match Request', 'You have a match request from eric balahadia for Psychology - Abnormal Psychology', '{\"match_id\":\"103\",\"student_id\":148,\"student_name\":\"eric balahadia\",\"subject\":\"Psychology - Abnormal Psychology\",\"message\":\"\"}', 0, '2025-10-13 04:04:05'),
(73, 42, 'match_request', 'New Match Request', 'You have a match request from niks bala for English', '{\"match_id\":\"104\",\"student_id\":3,\"student_name\":\"niks bala\",\"subject\":\"English\",\"message\":\"\"}', 0, '2025-10-14 04:45:52'),
(74, 6, 'match_request', 'New Match Request', 'You have a match request from ira kai for History', '{\"match_id\":\"105\",\"student_id\":42,\"student_name\":\"ira kai\",\"subject\":\"History\",\"message\":\"\"}', 0, '2025-10-14 04:46:53'),
(75, 137, 'match_request', 'New Match Request', 'You have a match request from kristelle catapang for Trigonometry', '{\"match_id\":\"106\",\"student_id\":138,\"student_name\":\"kristelle catapang\",\"subject\":\"Trigonometry\",\"message\":\"\"}', 0, '2025-10-14 04:54:23'),
(76, 143, 'match_request', 'New Match Request', 'You have a match request from nikkos virrey for Trigonometry', '{\"match_id\":\"107\",\"student_id\":137,\"student_name\":\"nikkos virrey\",\"subject\":\"Trigonometry\",\"message\":\"\"}', 0, '2025-10-14 04:56:27'),
(77, 88, 'match_request', 'New Match Request', 'You have a match request from ednaa balaha for Social Theory', '{\"match_id\":\"108\",\"student_id\":165,\"student_name\":\"ednaa balaha\",\"subject\":\"Social Theory\",\"message\":\"\"}', 0, '2025-10-14 04:59:55'),
(78, 117, 'match_request', 'New Match Request', 'You have a match request from janel balahadia for Programming - C++', '{\"match_id\":\"109\",\"student_id\":155,\"student_name\":\"janel balahadia\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 1, '2025-10-14 05:01:57'),
(79, 88, 'match_request', 'New Match Request', 'You have a match request from john paul for Abnormal Psychology', '{\"match_id\":\"110\",\"student_id\":147,\"student_name\":\"john paul\",\"subject\":\"Abnormal Psychology\",\"message\":\"\"}', 0, '2025-10-14 05:13:29'),
(80, 83, 'match_request', 'New Match Request', 'You have a match request from lily catapang for C++', '{\"match_id\":\"111\",\"student_id\":170,\"student_name\":\"lily catapang\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-14 05:14:25'),
(81, 147, 'match_request', 'New Match Request', 'You have a match request from joshua tayag for Abnormal Psychology', '{\"match_id\":\"112\",\"student_id\":174,\"student_name\":\"joshua tayag\",\"subject\":\"Abnormal Psychology\",\"message\":\"\"}', 1, '2025-10-15 12:39:18'),
(82, 173, 'match_request', 'New Match Request', 'You have a match request from john paul for Abnormal Psychology', '{\"match_id\":\"113\",\"student_id\":147,\"student_name\":\"john paul\",\"subject\":\"Abnormal Psychology\",\"message\":\"\"}', 0, '2025-10-15 12:42:36'),
(83, 147, 'match_request', 'New Match Request', 'You have a match request from jamiel bryan for Abnormal Psychology', '{\"match_id\":\"114\",\"student_id\":175,\"student_name\":\"jamiel bryan\",\"subject\":\"Abnormal Psychology\",\"message\":\"\"}', 1, '2025-10-15 12:55:07'),
(84, 166, 'match_request', 'New Match Request', 'You have a match request from renzo formanes for C++', '{\"match_id\":\"115\",\"student_id\":176,\"student_name\":\"renzo formanes\",\"subject\":\"C++\",\"message\":\"\"}', 0, '2025-10-15 13:17:15'),
(85, 117, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for Programming - C++', '{\"match_id\":\"116\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 1, '2025-10-16 14:31:38'),
(86, 64, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for Programming - C++', '{\"match_id\":\"117\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 0, '2025-10-16 14:40:09'),
(87, 155, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for Programming - C++', '{\"match_id\":\"118\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 1, '2025-10-16 14:40:33'),
(88, 78, 'match_request', 'New Match Request', 'You have a match request from ira subarashi for Programming - C++', '{\"match_id\":\"119\",\"student_id\":40,\"student_name\":\"ira subarashi\",\"subject\":\"Programming - C++\",\"message\":\"\"}', 1, '2025-10-17 03:20:37'),
(89, 155, 'match_request', 'New Match Request', 'You have a match request from per perputs for Social Psychology', '{\"match_id\":\"120\",\"student_id\":180,\"student_name\":\"per perputs\",\"subject\":\"Social Psychology\",\"message\":\"\"}', 1, '2025-10-17 03:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `email`, `otp_code`, `expires_at`, `is_used`, `created_at`) VALUES
(12, 'emmanvirrey41@gmail.com', '751948', '2025-10-07 00:08:45', 1, '2025-10-06 15:58:45'),
(13, 'emmanvirrey36@gmail.com', '191050', '2025-10-07 00:10:16', 0, '2025-10-06 16:00:16'),
(16, '22-69937@g.batstate-u.edu.ph', '647383', '2025-10-07 00:14:04', 1, '2025-10-06 16:04:04'),
(17, 'nikko@email.com', '367228', '2025-10-07 00:46:33', 0, '2025-10-06 16:36:33'),
(30, 'iraaasubarashi@gmail.com', '690492', '2025-10-07 02:02:33', 0, '2025-10-06 17:52:33'),
(32, 'balahadianikko2020@gmail.com', '272784', '2025-10-07 02:05:53', 1, '2025-10-06 17:55:53'),
(35, 'balahadianikko2015@gmail.com', '964716', '2025-10-07 02:09:52', 1, '2025-10-06 17:59:52'),
(37, 'irasubarashi@email.com', '767472', '2025-10-07 18:09:20', 0, '2025-10-07 09:59:20'),
(38, 'capilaapril@gmail.com', '094618', '2025-10-07 18:18:18', 1, '2025-10-07 10:08:18'),
(39, 'jfgymmanagement@gmail.com', '944022', '2025-10-07 18:49:28', 1, '2025-10-07 10:39:28'),
(40, 'georgecatapang76@gmail.com', '374034', '2025-10-07 21:17:10', 1, '2025-10-07 13:07:10'),
(41, '22-69954@g.batstate-u.edu.ph', '041884', '2025-10-08 15:34:42', 1, '2025-10-08 07:24:42'),
(42, 'perrybalahadia21@gmail.com', '756807', '2025-10-08 15:50:36', 1, '2025-10-08 07:40:36'),
(44, 'mabel12345678910@email.com', '287988', '2025-10-08 16:40:29', 0, '2025-10-08 08:30:29'),
(46, 'masdaskdj@email.com', '634705', '2025-10-08 16:55:00', 1, '2025-10-08 08:45:00'),
(47, 'maricarsadsdq@email.com', '233350', '2025-10-08 17:08:25', 1, '2025-10-08 08:58:25'),
(48, 'patrisk@email.com', '799895', '2025-10-08 17:13:01', 1, '2025-10-08 09:03:01'),
(74, 'cedr@email.com', '152091', '2025-10-09 17:00:17', 1, '2025-10-09 08:50:17'),
(82, 'jorge@email.com', '481907', '2025-10-13 12:04:49', 1, '2025-10-13 03:54:49'),
(89, 'AAA@email.com', '996420', '2025-10-17 20:37:08', 1, '2025-10-17 12:27:08'),
(90, 'qwertys@email.com', '701483', '2025-10-18 09:58:03', 1, '2025-10-18 01:48:03');

-- --------------------------------------------------------

--
-- Table structure for table `payment_restrictions`
--

CREATE TABLE `payment_restrictions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `restriction_type` enum('booking_restricted','hosting_restricted','both_restricted') NOT NULL,
  `reason` text NOT NULL,
  `related_payment_id` int(11) DEFAULT NULL,
  `payment_type` enum('session_payment','commission_payment') DEFAULT NULL,
  `restricted_at` datetime DEFAULT current_timestamp(),
  `lifted_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_settings`
--

INSERT INTO `payment_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'student_payment_deadline_days', '3', 'Number of days students have to submit payment proof after session completion', '2025-10-11 03:20:36'),
(2, 'mentor_commission_deadline_days', '2', 'Number of days mentors have to submit commission payment after student payment verification', '2025-10-11 03:20:36'),
(3, 'default_commission_percentage', '10.00', 'Default commission percentage for platform fee', '2025-10-11 03:20:36'),
(4, 'payment_methods', 'GCash,PayMaya,Bank Transfer,Cash', 'Accepted payment methods (comma-separated)', '2025-10-11 03:20:36'),
(5, 'enable_payment_restrictions', '1', 'Enable automatic account restrictions for overdue payments (1=enabled, 0=disabled)', '2025-10-11 03:20:36');

-- --------------------------------------------------------

--
-- Table structure for table `referral_codes`
--

CREATE TABLE `referral_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `max_uses` int(11) DEFAULT 10,
  `current_uses` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_codes`
--

INSERT INTO `referral_codes` (`id`, `code`, `created_by`, `max_uses`, `current_uses`, `expires_at`, `is_active`, `created_at`) VALUES
(1, 'MENTOR0008', 8, 10, 3, '2025-09-28 12:38:40', 1, '2025-09-21 12:38:40'),
(2, 'MENTORE21728', 8, 1, 1, '2025-09-28 13:08:14', 1, '2025-09-21 13:08:14'),
(3, 'MENTORB14814', 8, 1, 1, '2025-09-28 14:37:31', 1, '2025-09-21 14:37:31'),
(4, 'MENTORD8A550', 15, 1, 1, '2025-09-28 14:38:53', 1, '2025-09-21 14:38:53'),
(5, 'MENTOR9F405C', 86, 1, 0, '2025-10-31 03:46:50', 1, '2025-10-01 03:46:50'),
(6, 'MENTORE1F754', 105, 10, 5, '2025-10-09 12:46:06', 1, '2025-10-02 12:46:06'),
(7, 'MENTOR308CDD', 122, 1, 1, '2025-10-10 12:54:43', 1, '2025-10-03 12:54:43'),
(8, 'MENTOR0AE5EE', 147, 5, 5, '2025-10-15 09:36:48', 1, '2025-10-08 09:36:48'),
(9, 'PEERA8B2F4', 155, 25, 1, '2025-11-07 10:15:54', 1, '2025-10-08 10:15:54'),
(10, 'MENTORD3A160', 147, 50, 16, '2025-11-07 10:20:45', 1, '2025-10-08 10:20:45');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `category` enum('abuse','technical','suggestion','spam') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','critical','resolved') DEFAULT 'pending',
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_attended` tinyint(1) DEFAULT NULL,
  `mentor_attended` tinyint(1) DEFAULT NULL,
  `student_rating` int(11) DEFAULT NULL CHECK (`student_rating` >= 1 and `student_rating` <= 5),
  `mentor_rating` int(11) DEFAULT NULL CHECK (`mentor_rating` >= 1 and `mentor_rating` <= 5),
  `student_feedback` text DEFAULT NULL,
  `mentor_feedback` text DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `session_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Agreed session fee',
  `payment_required` tinyint(1) DEFAULT 1 COMMENT 'Whether payment is required for this session',
  `terms_accepted` tinyint(1) DEFAULT 0,
  `terms_accepted_at` datetime DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `match_id`, `session_date`, `start_time`, `end_time`, `updated_at`, `status`, `location`, `notes`, `created_at`, `student_attended`, `mentor_attended`, `student_rating`, `mentor_rating`, `student_feedback`, `mentor_feedback`, `cancellation_reason`, `cancelled_by`, `cancelled_at`, `session_rate`, `payment_required`, `terms_accepted`, `terms_accepted_at`, `payment_amount`) VALUES
(1, 1, '2025-09-20', '14:00:00', '16:00:00', NULL, 'completed', 'Zoom', 'asdas', '2025-09-20 11:47:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(2, 1, '2025-09-20', '20:00:00', '21:00:00', NULL, 'completed', 'Zoom', 'asd', '2025-09-20 11:47:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(3, 13, '2025-09-21', '23:00:00', '23:30:00', NULL, 'scheduled', 'asd', 'asd', '2025-09-21 14:46:46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(4, 14, '2025-09-22', '10:00:00', '11:00:00', NULL, 'completed', 'zoom', 'asdas', '2025-09-21 17:46:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(5, 15, '2025-09-22', '01:00:00', '02:00:00', NULL, 'completed', 'Zoom', 'asdasd', '2025-09-21 17:48:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(6, 14, '2025-09-22', '14:00:00', '16:00:00', NULL, 'completed', '', '', '2025-09-21 21:03:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(7, 14, '2025-09-23', '14:00:00', '16:00:00', '2025-09-26 13:45:05', 'cancelled', '', '', '2025-09-21 21:03:30', NULL, NULL, NULL, NULL, NULL, NULL, 'No reason provided', NULL, NULL, 0.00, 1, 0, NULL, NULL),
(8, 36, '2025-09-24', '13:00:00', '14:00:00', NULL, 'completed', 'Zoom', 'asdas', '2025-09-24 05:49:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(9, 28, '2025-09-24', '16:00:00', '17:00:00', NULL, 'cancelled', 'Zoom', 'asd', '2025-09-24 05:50:45', NULL, NULL, NULL, NULL, NULL, NULL, 'No reason provided', NULL, NULL, 0.00, 1, 0, NULL, NULL),
(10, 57, '2025-09-26', '14:00:00', '16:00:00', '2025-09-26 13:45:00', 'cancelled', 'Zoomasdasdasda', '', '2025-09-25 12:33:21', NULL, NULL, NULL, NULL, NULL, NULL, 'No reason provided', NULL, NULL, 0.00, 1, 0, NULL, NULL),
(11, 42, '2025-09-26', '14:00:00', '16:00:00', NULL, 'completed', 'Tanauan, Calabarzon (Region IV-A)', 'kj', '2025-09-26 06:32:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(12, 42, '2025-09-27', '19:00:00', '20:00:00', NULL, 'completed', 'Zoom', 'asdas', '2025-09-27 11:27:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(13, 37, '2025-09-28', '14:00:00', '16:00:00', NULL, 'cancelled', 'Zoom', 'asd', '2025-09-27 11:28:30', NULL, NULL, NULL, NULL, NULL, NULL, 'Student unavailable', 40, '2025-09-27 11:29:57', 0.00, 1, 0, NULL, NULL),
(14, 42, '2025-09-27', '22:00:00', '23:00:00', NULL, 'cancelled', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', '2025-09-27 12:20:09', NULL, NULL, NULL, NULL, NULL, NULL, 'Personal reasons', 40, '2025-09-27 12:20:52', 0.00, 1, 0, NULL, NULL),
(15, 77, '2025-09-30', '21:00:00', '22:00:00', NULL, 'completed', 'Zoom', 'asdas', '2025-09-30 13:31:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(16, 78, '2025-10-01', '19:00:00', '20:00:00', NULL, 'completed', 'Manila', 'asd', '2025-10-01 11:42:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(17, 79, '2025-10-02', '00:00:00', '13:00:00', NULL, 'completed', 'Zoom', 'asdas', '2025-10-02 15:36:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(18, 86, '2025-10-04', '12:00:00', '14:00:00', NULL, 'scheduled', 'Zoom', 'asdasd', '2025-10-03 12:30:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(19, 87, '2025-10-03', '20:00:00', '21:00:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-03 12:35:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(20, 90, '2025-10-04', '14:00:00', '16:00:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-04 12:26:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(21, 90, '2025-10-05', '14:00:00', '16:00:00', NULL, 'cancelled', 'Zoom', '', '2025-10-04 12:38:36', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 119, '2025-10-04 13:26:57', 0.00, 1, 0, NULL, NULL),
(22, 83, '2025-10-05', '14:00:00', '16:00:00', NULL, 'cancelled', 'Zoom', 'asd', '2025-10-04 12:41:39', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 119, '2025-10-04 13:33:04', 0.00, 1, 0, NULL, NULL),
(23, 83, '2025-10-04', '21:14:00', '22:00:00', NULL, 'scheduled', 'Zoom', 'asd', '2025-10-04 12:44:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(24, 77, '2025-10-04', '15:00:00', '16:00:00', NULL, 'scheduled', 'Zoom', 'ads', '2025-10-04 13:05:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(25, 77, '2025-10-04', '22:00:00', '23:00:00', NULL, 'scheduled', 'Zoom', 'asd', '2025-10-04 13:05:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(26, 42, '2025-10-05', '17:00:00', '18:00:00', NULL, 'completed', 'Zoom', 'asds', '2025-10-05 08:59:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(27, 42, '2025-10-05', '17:02:00', '18:02:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-05 09:01:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(28, 42, '2025-10-05', '19:00:00', '20:00:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-05 10:48:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(29, 77, '2025-10-05', '20:00:00', '21:00:00', NULL, 'scheduled', 'Zoom', 'asda', '2025-10-05 11:01:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(30, 94, '2025-10-05', '21:00:00', '22:00:00', NULL, 'cancelled', 'Tanauan, Calabarzon (Region IV-A)', 'asd', '2025-10-05 11:02:14', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 132, '2025-10-05 11:09:47', 0.00, 1, 0, NULL, NULL),
(31, 96, '2025-10-07', '18:42:00', '19:42:00', NULL, 'cancelled', 'Zoom', 'adas', '2025-10-07 10:11:43', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 139, '2025-10-07 10:19:52', 0.00, 1, 0, NULL, NULL),
(32, 96, '2025-10-07', '18:51:00', '19:51:00', NULL, 'cancelled', 'Zoom', 'asd', '2025-10-07 10:20:23', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 139, '2025-10-07 10:33:12', 0.00, 1, 0, NULL, NULL),
(33, 96, '2025-10-07', '19:55:00', '20:55:00', NULL, 'cancelled', 'Zoom', 'asd', '2025-10-07 10:33:37', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 139, '2025-10-07 10:55:16', 0.00, 1, 0, NULL, NULL),
(34, 97, '2025-10-07', '21:00:00', '22:00:00', NULL, 'cancelled', 'Zoom', 'asdas', '2025-10-07 10:41:36', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 139, '2025-10-07 10:55:19', 0.00, 1, 0, NULL, NULL),
(35, 97, '2025-10-07', '19:00:00', '20:00:00', NULL, 'completed', 'Zoom', 'bring what u want', '2025-10-07 10:55:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(36, 97, '2025-10-07', '19:11:00', '20:11:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-07 11:08:39', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(37, 97, '2025-10-07', '19:17:00', '20:17:00', NULL, 'completed', 'Zoom', 'asdas', '2025-10-07 11:16:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(38, 97, '2025-10-07', '19:19:00', '20:19:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-07 11:18:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(39, 97, '2025-10-07', '19:30:00', '20:30:00', NULL, 'scheduled', 'Zoom', 'asd', '2025-10-07 11:20:29', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(40, 97, '2025-10-07', '20:30:00', '21:30:00', NULL, 'scheduled', 'Zoom', 'as', '2025-10-07 12:28:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(41, 97, '2025-10-08', '15:30:00', '16:30:00', NULL, 'scheduled', 'Zoom', 'asd', '2025-10-08 07:18:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(42, 98, '2025-10-08', '16:00:00', '17:00:00', NULL, 'scheduled', 'Zoom', 'asd', '2025-10-08 07:28:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(43, 99, '2025-10-09', '14:00:00', '15:00:00', NULL, 'scheduled', 'Zoom', 'mjkh', '2025-10-09 03:15:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(44, 37, '2025-10-10', '16:00:00', '17:00:00', NULL, 'completed', 'asd', 'asd', '2025-10-10 05:23:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(45, 100, '2025-10-11', '11:30:00', '12:01:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-11 03:25:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(46, 100, '2025-10-11', '11:36:00', '12:36:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-11 03:35:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(47, 100, '2025-10-11', '11:40:00', '12:40:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-11 03:38:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(48, 100, '2025-10-11', '11:46:00', '12:01:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-11 03:44:41', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(49, 100, '2025-10-11', '14:00:00', '15:00:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-11 03:59:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(50, 100, '2025-10-11', '13:00:00', '14:00:00', NULL, 'completed', 'Tanauan, Calabarzon (Region IV-A)', 'asd', '2025-10-11 04:06:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 0, NULL, NULL),
(51, 101, '2025-10-12', '14:00:00', '15:00:00', NULL, 'cancelled', 'asd', 'asd', '2025-10-12 04:16:49', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 166, '2025-10-12 04:17:08', 0.00, 1, 1, '2025-10-12 12:16:49', 0.00),
(52, 101, '2025-10-12', '12:19:00', '13:19:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-12 04:17:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-12 12:17:21', 0.00),
(56, 101, '2025-10-12', '12:38:00', '13:38:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-12 04:36:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-12 12:36:54', 0.00),
(57, 102, '2025-10-12', '12:50:00', '13:50:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-12 04:49:19', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-12 12:49:19', 0.00),
(58, 101, '2025-10-12', '13:00:00', '14:00:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-12 04:59:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-12 12:59:15', 0.00),
(59, 100, '2025-10-13', '11:45:00', '12:45:00', NULL, 'completed', 'asd', 'asd', '2025-10-13 03:44:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-13 11:44:42', 0.00),
(60, 100, '2025-10-13', '11:47:00', '12:47:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-13 03:46:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-13 11:46:20', 0.00),
(61, 103, '2025-10-13', '12:05:00', '13:05:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-13 04:04:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-13 12:04:45', 0.00),
(62, 103, '2025-10-13', '12:08:00', '13:08:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-13 04:07:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-13 12:07:23', 0.00),
(64, 103, '2025-10-13', '12:11:00', '13:11:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-13 04:09:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-13 12:09:36', 0.00),
(65, 103, '2025-10-13', '13:08:00', '14:08:00', NULL, 'completed', 'Zoomasd', '', '2025-10-13 05:07:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-13 13:07:16', 0.00),
(66, 103, '2025-10-14', '10:18:00', '11:18:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-14 02:17:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-14 10:17:51', 0.00),
(67, 112, '2025-10-15', '20:41:00', '21:41:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-15 12:40:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-15 20:40:26', 0.00),
(68, 113, '2025-10-15', '20:44:00', '21:44:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-15 12:43:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-15 20:43:54', 0.00),
(69, 113, '2025-10-15', '20:50:00', '21:50:00', NULL, 'completed', 'Zoomasdas', 'asd', '2025-10-15 12:49:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-15 20:49:15', 200.00),
(70, 114, '2025-10-15', '20:56:00', '21:56:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-15 12:56:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-15 20:56:18', 0.00),
(71, 114, '2025-10-15', '21:07:00', '22:07:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-15 13:06:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-15 21:06:22', 0.00),
(72, 115, '2025-10-15', '21:19:00', '22:19:00', NULL, 'completed', 'Zoom', 'asd', '2025-10-15 13:18:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 1, 1, '2025-10-15 21:18:24', 0.00),
(73, 116, '2025-10-18', '02:00:00', '03:00:00', NULL, 'cancelled', 'Zoom', 'asd', '2025-10-17 17:53:37', NULL, NULL, NULL, NULL, NULL, NULL, 'Cancelled by user', 40, '2025-10-17 17:58:10', 0.00, 1, 1, '2025-10-18 01:53:37', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `session_payments`
--

CREATE TABLE `session_payments` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('awaiting_payment','proof_submitted','verified','rejected','overdue') DEFAULT 'awaiting_payment',
  `proof_of_payment` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `payment_deadline` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_payments`
--

INSERT INTO `session_payments` (`id`, `session_id`, `student_id`, `mentor_id`, `amount`, `payment_status`, `proof_of_payment`, `payment_method`, `payment_date`, `verified_by`, `verified_at`, `rejection_reason`, `payment_deadline`, `created_at`, `updated_at`) VALUES
(1, 47, 147, 148, 100.00, 'verified', '../uploads/payments/payment_47_1760155445.jpg', 'GCash', '2025-10-11 00:00:00', 1, '2025-10-11 12:05:16', NULL, '2025-10-14 12:04:05', '2025-10-11 04:04:05', '2025-10-11 04:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `session_ratings`
--

CREATE TABLE `session_ratings` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `rater_id` int(11) NOT NULL,
  `rated_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_ratings`
--

INSERT INTO `session_ratings` (`id`, `session_id`, `rater_id`, `rated_id`, `rating`, `feedback`, `created_at`) VALUES
(1, 2, 3, 4, 4, 'ure good', '2025-09-21 16:04:33'),
(2, 1, 3, 4, 5, 'asda', '2025-09-21 16:04:51'),
(3, 4, 8, 16, 5, '', '2025-09-21 17:47:08'),
(4, 5, 8, 11, 5, '', '2025-09-21 17:49:15'),
(5, 6, 8, 16, 5, 'quality boss', '2025-09-22 09:59:43'),
(6, 8, 15, 40, 4, 'goods', '2025-09-24 05:52:24'),
(7, 8, 40, 15, 5, 'nasy', '2025-09-25 06:27:33'),
(8, 11, 40, 42, 2, '', '2025-09-27 09:34:51'),
(9, 15, 82, 83, 5, 'ure good as fuck', '2025-09-30 13:32:39'),
(10, 15, 83, 82, 5, 'ure good ha', '2025-09-30 13:33:28'),
(11, 16, 94, 93, 4, 'ure good', '2025-10-01 11:43:16'),
(12, 16, 93, 94, 4, 'ure good', '2025-10-01 11:43:39'),
(13, 12, 40, 42, 5, 'sh', '2025-10-03 02:07:47'),
(14, 19, 91, 123, 5, 'ure good', '2025-10-03 12:39:01'),
(15, 19, 123, 91, 4, 'asdas', '2025-10-03 12:39:11'),
(16, 35, 139, 140, 5, '', '2025-10-07 11:05:38'),
(17, 44, 40, 43, 5, '', '2025-10-11 03:21:12'),
(18, 45, 148, 147, 5, 'wadw', '2025-10-11 03:30:30'),
(19, 46, 148, 147, 5, '', '2025-10-11 03:37:17'),
(20, 52, 166, 168, 5, 'sht', '2025-10-12 04:21:02'),
(21, 52, 168, 166, 5, 'asd', '2025-10-12 04:21:38'),
(22, 56, 166, 168, 5, 'asd', '2025-10-12 04:39:31'),
(23, 57, 169, 166, 5, 'asd', '2025-10-12 04:57:50'),
(24, 57, 166, 169, 5, 'asd', '2025-10-12 04:58:14'),
(25, 58, 166, 168, 5, 'asd', '2025-10-12 05:01:48'),
(26, 67, 147, 174, 5, '', '2025-10-15 12:42:19'),
(27, 68, 173, 147, 5, '', '2025-10-15 12:48:57');

-- --------------------------------------------------------

--
-- Table structure for table `session_reminders`
--

CREATE TABLE `session_reminders` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reminder_type` enum('24_hours','1_hour','30_minutes') NOT NULL,
  `reminder_time` datetime NOT NULL,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_reminders`
--

INSERT INTO `session_reminders` (`id`, `session_id`, `user_id`, `reminder_type`, `reminder_time`, `is_sent`, `sent_at`, `created_at`) VALUES
(1, 20, 119, '30_minutes', '2025-10-04 13:30:00', 0, NULL, '2025-10-04 12:26:35'),
(2, 21, 119, '30_minutes', '2025-10-05 13:30:00', 0, NULL, '2025-10-04 12:38:36'),
(3, 22, 83, '30_minutes', '2025-10-05 13:30:00', 0, NULL, '2025-10-04 12:41:39'),
(4, 23, 119, '30_minutes', '2025-10-04 20:44:00', 1, '2025-10-08 07:21:20', '2025-10-04 12:44:26'),
(5, 24, 83, '24_hours', '2025-10-03 15:00:00', 1, '2025-10-08 07:20:58', '2025-10-04 13:05:06'),
(6, 24, 83, '1_hour', '2025-10-04 14:00:00', 1, '2025-10-08 07:21:06', '2025-10-04 13:05:06'),
(7, 25, 83, '24_hours', '2025-10-03 22:00:00', 1, '2025-10-08 07:21:02', '2025-10-04 13:05:58'),
(8, 25, 83, '1_hour', '2025-10-04 21:00:00', 1, '2025-10-08 07:21:24', '2025-10-04 13:05:58'),
(9, 26, 40, '24_hours', '2025-10-04 17:00:00', 0, NULL, '2025-10-05 08:59:36'),
(10, 26, 40, '1_hour', '2025-10-05 16:00:00', 0, NULL, '2025-10-05 08:59:36'),
(11, 27, 40, '24_hours', '2025-10-04 17:02:00', 1, '2025-10-08 07:21:09', '2025-10-05 09:01:08'),
(12, 27, 40, '1_hour', '2025-10-05 16:02:00', 1, '2025-10-08 07:21:28', '2025-10-05 09:01:08'),
(13, 28, 40, '24_hours', '2025-10-04 19:00:00', 1, '2025-10-08 07:21:13', '2025-10-05 10:48:01'),
(14, 28, 40, '1_hour', '2025-10-05 18:00:00', 1, '2025-10-08 07:21:32', '2025-10-05 10:48:01'),
(15, 29, 82, '24_hours', '2025-10-04 20:00:00', 1, '2025-10-08 07:21:16', '2025-10-05 11:01:10'),
(16, 29, 82, '1_hour', '2025-10-05 19:00:00', 1, '2025-10-08 07:21:35', '2025-10-05 11:01:10'),
(17, 30, 132, '24_hours', '2025-10-04 21:00:00', 0, NULL, '2025-10-05 11:02:14'),
(18, 30, 132, '1_hour', '2025-10-05 20:00:00', 0, NULL, '2025-10-05 11:02:14'),
(19, 31, 91, '24_hours', '2025-10-06 18:42:00', 0, NULL, '2025-10-07 10:11:43'),
(20, 31, 91, '1_hour', '2025-10-07 17:42:00', 0, NULL, '2025-10-07 10:11:43'),
(21, 32, 91, '24_hours', '2025-10-06 18:51:00', 0, NULL, '2025-10-07 10:20:23'),
(22, 32, 91, '1_hour', '2025-10-07 17:51:00', 0, NULL, '2025-10-07 10:20:23'),
(27, 35, 139, '24_hours', '2025-10-06 19:00:00', 0, NULL, '2025-10-07 10:55:35'),
(28, 35, 139, '1_hour', '2025-10-07 18:00:00', 0, NULL, '2025-10-07 10:55:35'),
(29, 36, 139, '24_hours', '2025-10-06 19:11:00', 0, NULL, '2025-10-07 11:08:39'),
(30, 36, 139, '1_hour', '2025-10-07 18:11:00', 0, NULL, '2025-10-07 11:08:39'),
(31, 37, 139, '24_hours', '2025-10-06 19:17:00', 0, NULL, '2025-10-07 11:16:22'),
(32, 37, 139, '1_hour', '2025-10-07 18:17:00', 0, NULL, '2025-10-07 11:16:22'),
(33, 38, 139, '24_hours', '2025-10-06 19:19:00', 0, NULL, '2025-10-07 11:18:22'),
(34, 38, 139, '1_hour', '2025-10-07 18:19:00', 0, NULL, '2025-10-07 11:18:22'),
(35, 39, 139, '24_hours', '2025-10-06 19:30:00', 1, '2025-10-08 07:21:39', '2025-10-07 11:20:29'),
(36, 39, 139, '1_hour', '2025-10-07 18:30:00', 1, '2025-10-08 07:21:51', '2025-10-07 11:20:29'),
(37, 40, 139, '24_hours', '2025-10-06 20:30:00', 1, '2025-10-08 07:21:43', '2025-10-07 12:28:37'),
(38, 40, 139, '1_hour', '2025-10-07 19:30:00', 1, '2025-10-08 07:21:55', '2025-10-07 12:28:37'),
(39, 41, 139, '24_hours', '2025-10-07 15:30:00', 1, '2025-10-08 07:21:48', '2025-10-08 07:18:45'),
(40, 41, 139, '1_hour', '2025-10-08 14:30:00', 1, '2025-10-08 07:21:59', '2025-10-08 07:18:45'),
(41, 42, 141, '24_hours', '2025-10-07 16:00:00', 1, '2025-10-08 07:32:32', '2025-10-08 07:28:47'),
(42, 42, 141, '1_hour', '2025-10-08 15:00:00', 1, '2025-10-08 07:32:35', '2025-10-08 07:28:47'),
(43, 43, 161, '24_hours', '2025-10-08 14:00:00', 0, NULL, '2025-10-09 03:15:53'),
(44, 43, 161, '1_hour', '2025-10-09 13:00:00', 0, NULL, '2025-10-09 03:15:53'),
(45, 44, 40, '24_hours', '2025-10-09 16:00:00', 0, NULL, '2025-10-10 05:23:12'),
(46, 44, 40, '1_hour', '2025-10-10 15:00:00', 0, NULL, '2025-10-10 05:23:12'),
(47, 45, 148, '24_hours', '2025-10-10 23:00:00', 0, NULL, '2025-10-11 03:25:44'),
(48, 45, 148, '1_hour', '2025-10-11 22:00:00', 0, NULL, '2025-10-11 03:25:44'),
(49, 46, 148, '24_hours', '2025-10-10 11:36:00', 0, NULL, '2025-10-11 03:35:03'),
(50, 46, 148, '1_hour', '2025-10-11 10:36:00', 0, NULL, '2025-10-11 03:35:03'),
(51, 47, 148, '24_hours', '2025-10-10 11:40:00', 0, NULL, '2025-10-11 03:38:52'),
(52, 47, 148, '1_hour', '2025-10-11 10:40:00', 0, NULL, '2025-10-11 03:38:52'),
(53, 48, 147, '24_hours', '2025-10-10 23:45:00', 0, NULL, '2025-10-11 03:44:41'),
(54, 48, 147, '1_hour', '2025-10-11 22:45:00', 0, NULL, '2025-10-11 03:44:41'),
(55, 49, 147, '24_hours', '2025-10-10 14:00:00', 0, NULL, '2025-10-11 03:59:53'),
(56, 49, 147, '1_hour', '2025-10-11 13:00:00', 0, NULL, '2025-10-11 03:59:53'),
(57, 50, 148, '24_hours', '2025-10-10 13:00:00', 0, NULL, '2025-10-11 04:06:37'),
(58, 50, 148, '1_hour', '2025-10-11 12:00:00', 0, NULL, '2025-10-11 04:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `system_metrics`
--

CREATE TABLE `system_metrics` (
  `id` int(11) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_metrics`
--

INSERT INTO `system_metrics` (`id`, `metric_name`, `metric_value`, `metric_date`, `created_at`) VALUES
(1, 'daily_active_users', 45.00, '2025-09-19', '2025-09-19 13:56:04'),
(2, 'session_completion_rate', 87.50, '2025-09-19', '2025-09-19 13:56:04'),
(3, 'average_session_rating', 4.20, '2025-09-19', '2025-09-19 13:56:04'),
(4, 'new_registrations', 12.00, '2025-09-19', '2025-09-19 13:56:04');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'platform_name', 'Study Mentorship Platform', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(2, 'platform_tagline', 'Connect, Learn, Succeed Together hello', '2025-10-09 10:03:16', '2025-10-09 11:45:26'),
(3, 'support_email', 'support@studyplatform.com', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(4, 'maintenance_mode', '0', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(5, 'allow_registrations', '1', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(6, 'require_email_verification', '1', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(7, 'require_mentor_verification', '1', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(8, 'max_matches_per_user', '10', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(9, 'session_duration_default', '180', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(10, 'enable_messaging', '1', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(11, 'enable_video_sessions', '1', '2025-10-09 10:03:16', '2025-10-09 10:03:16'),
(34, 'admin_gcash_number', '09123456789', '2025-10-12 04:06:21', '2025-10-12 04:06:21'),
(35, 'commission_percentage', '10', '2025-10-12 04:06:21', '2025-10-12 04:06:21'),
(36, 'coa_terms_url', '/terms-and-conditions.php', '2025-10-12 04:06:21', '2025-10-12 04:06:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','mentor','peer','admin') NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `grade_level` varchar(20) DEFAULT NULL,
  `strand` varchar(50) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `learning_goals` text DEFAULT NULL,
  `preferred_learning_style` varchar(100) DEFAULT NULL,
  `teaching_style` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'Auto-set to TRUE for peers (verified via referral code). Mentors require document verification.',
  `matchmaking_enabled` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_accuracy` int(11) DEFAULT NULL,
  `location_type` enum('manual','geocoded') DEFAULT 'manual',
  `max_distance_km` int(11) DEFAULT 25,
  `hourly_rate` decimal(10,2) DEFAULT NULL COMMENT 'Hourly rate for mentors/peers (in local currency)',
  `suspension_until` timestamp NULL DEFAULT NULL,
  `is_banned` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `grade_level`, `strand`, `course`, `location`, `bio`, `learning_goals`, `preferred_learning_style`, `teaching_style`, `profile_picture`, `is_verified`, `matchmaking_enabled`, `is_active`, `created_at`, `updated_at`, `latitude`, `longitude`, `location_accuracy`, `location_type`, `max_distance_km`, `hourly_rate`, `suspension_until`, `is_banned`) VALUES
(1, 'admin', 'admin@studyplatform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2025-09-19 13:54:35', '2025-09-19 13:54:35', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(3, 'asd', 'asd@yahoo.com', '$2y$10$GWK385vNL2Ym8RBHaOo5kePoPj2GpYoh18jRNc2KugEmA6kVxaAfK', 'peer', 'niks', 'bala', 'Grade 9', '', 'IT', 'Tanauan City, Batangas', 'asdas', 'Learning goals: asdas', 'Visual', NULL, NULL, 1, 1, 1, '2025-09-19 14:21:30', '2025-10-10 04:56:40', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(4, 'kai', 'kai@yahoo.com', '$2y$10$6Hl437zVI6Z26NF6bc0ILOmIZ1U1Pom0Jj2TnqbH8sj66v5gZXoQm', 'student', 'kai', 'cata', 'Grade 11', '', 'BSIT', 'asdasd', 'asdas', 'Learning goals: asdas', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-19 14:24:26', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(5, 'kairell', 'karrell@email.com', '$2y$10$fuXtEz8w0Jgem4S9t1Ilqua5meDgMzAgQ3Zh3Kg1.2ijr8dhlYYd6', 'mentor', 'Karrell', 'Catapang', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'asdasd', NULL, NULL, 'Teaching approach: asdasd', NULL, 0, 1, 1, '2025-09-19 15:12:56', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(6, 'nikkos', 'nikkos@yahoo.com', '$2y$10$.UiTP1.78f5.BTDoXXpbue6FuYNX/bpwVGCOT1843lFQe6k5H.6eW', 'student', 'nikko', 'Balahadia', '4th Year College', '', 'BSIT', 'Tanauan City, Batangas', 'asdas', 'Learning goals: asdas', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-20 11:49:33', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(7, 'irakarrell', 'irakarrell@email.com', '$2y$10$D55HOuaIgCpuVpFRmVS/eeV7D8n6RkBqkREAauTLojZWNSzB2IIkK', 'mentor', 'karrell', 'catapang', '4th Year College', '', 'BSIT', 'Zoom', 'asdas', NULL, NULL, 'Teaching approach: asdas', NULL, 0, 1, 1, '2025-09-20 11:51:10', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(8, 'tumie', 'tumie@email.com', '$2y$10$nOOrBY86.jOGZDNKzdNxGeo6VLQTma3waTMyhMpDAewWR7evLfoDC', 'mentor', 'tumeee', 'balahadia', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'asdas', NULL, NULL, 'Teaching approach: asdas', 'uploads/profiles/profile_8_1758481495.jpg', 1, 1, 1, '2025-09-20 12:13:34', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(9, 'emman', 'emman@email.com', '$2y$10$3TdsKF/2pj0SoXib7AOyCORRDzDa7.CXolGnz15Ad.2Moa6n7nu.6', 'mentor', 'emman', 'virrey', '4th Year College', '', 'IT', 'Zoom', 'wdfwdf', NULL, NULL, 'Teaching approach: wdfwdf', NULL, 0, 1, 1, '2025-09-21 13:09:15', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(10, 'kiki', 'kiki@email.com', '$2y$10$NUp50602YrBZj7Gm4gh1ueLSwBxaHn7ocDtvMxSRqp8hVl4JdUsLi', 'mentor', 'kiki', 'kiki', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'kjhasd', NULL, NULL, 'Teaching approach: kjhasd', NULL, 1, 1, 1, '2025-09-21 13:16:01', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(11, 'noe', 'noee@yahoo.com', '$2y$10$itdM1eWWLA6LEZ7juJL2ceJ7vmKZ6ncdwYPx.bvu.qbe/XGpZgJp.', 'student', 'noe', 'ubana', '4th Year College', '', 'IT', 'Nasugbu, Calabarzon (Region IV-A), Philippines (the)', 'jhg', 'Learning goals: jhg', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-21 13:46:11', '2025-09-27 10:41:34', 14.07300000, 120.62950000, 26953, 'manual', 25, NULL, NULL, 0),
(12, 'Lloyd', 'Lloyd@email.com', '$2y$10$Fkfo5zYqPqmcOYMw56KmJ./p.hgoSu3vMBAQx.oNq85wjRvcmXaO.', 'mentor', 'Lloyd', 'virrey', '2nd Year College', '', 'IT', 'Tanauan City, Batangas', 'tgdf', NULL, NULL, 'Teaching approach: tgdf', NULL, 0, 1, 1, '2025-09-21 13:47:22', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(13, 'ivan', 'ivan@email.com', '$2y$10$qz8u.4SrcOONbVINzR6R6OQhaWnz9Ctdlu2veniwIihMMDc.oztuK', 'mentor', 'ivan', 'tenorio', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'asdas', NULL, NULL, 'Teaching approach: asdas', NULL, 0, 1, 1, '2025-09-21 14:11:29', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(14, 'john', 'john@email.com', '$2y$10$rK5WukkC4Q7KhvG23H4oJ.J5oAXYpYMXHVMhUFi8TcspEC5YkuoSe', 'mentor', 'john', 'balahadia', '4th Year College', '', 'BS Information Technology', 'Manila', 'asdas', NULL, NULL, 'Teaching approach: asdas', NULL, 0, 1, 1, '2025-09-21 14:15:25', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(15, 'admin@studyplatform.com', 'nashivan@email.com', '$2y$10$GxYx/3hypQ7v/PRoKjTJ4uchYzdgPd2cjPzQ1K/W7vtwxgjlC//H6', 'mentor', 'nash', 'ivan', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'asdasd', NULL, NULL, 'Teaching approach: asdasd', NULL, 1, 1, 1, '2025-09-21 14:37:59', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(16, 'emman lloyd', 'emmanlloyd@email.com', '$2y$10$1OrLViqEFWgwFMS8DPlt5ujoa5RJ6aFSQoH6RCK/i9dEUe7JSiy6m', 'student', 'emmanlloyd', 'virrey', 'Grade 10', '', 'BSIT', 'Tanauan City, Batangas', 'asd', 'Learning goals: asd', 'Visual', NULL, NULL, 1, 1, 1, '2025-09-21 14:39:55', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(17, 'irakai', 'irakai@email.com', '$2y$10$nQXC40cS8EKLl/79rH8mc.fQc.i6Wq4Q1meSnujOJVZ3xv8YsM85K', 'mentor', 'ira', 'catapang', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Teaching approach: Not specified', NULL, 0, 1, 1, '2025-09-21 15:02:26', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(18, 'nik', 'nik@email.com', '$2y$10$2KuJj868euZ8jSQzo6sM1OxOfVrgTGFt1rlgtGImGppfjiwzwJo/S', 'student', 'nik', 'balahadia', NULL, NULL, NULL, NULL, NULL, 'Learning goals: Not specified', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-21 15:03:59', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(19, 'kais', 'kais@yahoo.com', '$2y$10$Mph7HQGaTDu139.64hVm0eO721d93D4zE5.ViSRbJ6AxCCGUKbFgy', 'mentor', 'kais', 'catapang', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Teaching approach: Not specified', NULL, 0, 1, 1, '2025-09-21 15:07:43', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(20, 'niks', 'niks@yahoo.com', '$2y$10$9INBEKWZZ3C5uOwWpxhMTuTIPOudYoMGNDOqZ8fJf/E.6n/jWN5S.', 'mentor', 'nikko', 'balahadia', '4th Year College', '', 'BS Information Technology', 'Manila', 'asd', NULL, NULL, 'Teaching approach: asd', NULL, 0, 1, 1, '2025-09-21 18:57:22', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(26, 'ivanog', 'ivanog@yahoo.com', '$2y$10$CVSuc7MyrlB6biisI97XQeAweNeZEejESjaDKRlprSPJVzDJ2rVIy', 'student', 'ivan', 'tenorio', NULL, NULL, NULL, NULL, NULL, 'Learning goals: Not specified', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-22 05:22:26', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(27, 'em', 'em@email.com', '$2y$10$8UHdMmMFAA4nht2O5w99He.2ZcioO2lL1XTuhB9dCL0GGaBlrn7p6', 'student', 'em', 'ems', NULL, NULL, NULL, NULL, NULL, 'Learning goals: Not specified', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-22 14:45:57', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(32, 'ni', 'ni@email.com', '$2y$10$3QOLGFQFk76E5HSD1z0GL.qlElt33bMb9t1/2OHjzmyYboJzHM5iC', 'student', 'ni', 'nik', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'ASD', 'DASD', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-23 08:02:54', '2025-09-23 08:03:35', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(33, 'n', 'n@email.com', '$2y$10$TmijesdPEFkPnfI6DaLKJeIO/Nfh1gxg2tXO64zWUDPKyIlBFJRY6', 'mentor', 'n', 'n', NULL, NULL, NULL, 'Tanauan City, Batangas', 'asd', NULL, NULL, 'asd', NULL, 0, 1, 1, '2025-09-23 08:04:17', '2025-09-23 08:04:56', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(35, 'asdasdasd', 'asdasdasd@email.com', '$2y$10$PtIS2qotNm51iDQAqA6qb.PQERD.ZSqs2hULGLUqcOPnysikiJzDu', 'student', 'asdasd', 'asdasd', '2nd Year College', '', 'IT', 'Tanauan City, Batangas', 'asd', 'asdasd', 'Auditory', NULL, NULL, 0, 1, 1, '2025-09-23 08:09:25', '2025-09-23 08:12:13', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(37, 'kairi', 'kairi@email.com', '$2y$10$NMm0Uqh/oOwRJ/coFTVYj.N0O2evosumY/Rk9xbk6XKaJE0KoJyWW', 'student', 'Karrell', 'balahadia', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'asda', 'asda', '', NULL, NULL, 0, 1, 1, '2025-09-23 12:29:30', '2025-09-23 12:29:54', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(38, 'medy', 'medy@yahoo.com', '$2y$10$e48cQY1cOgXWWh6UNVP0uuBwIssRDCNOO0HAmreIkJAoDPm5d0rFW', 'student', 'medy', 'balahadia', NULL, NULL, NULL, NULL, NULL, 'Learning goals: Not specified', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-24 02:42:59', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(39, 'kai.sha', 'kaisha@gmail.com', '$2y$10$1k8WiHUFmgKLgH1j0dPX8eVkinbJurRSsUFQNj.gO9M9hZpHo.v3i', 'student', 'kai', 'sha', NULL, NULL, NULL, NULL, NULL, 'Learning goals: Not specified', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-24 04:02:14', '2025-09-24 04:16:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(40, 'irasub', 'irasubarashi@email.com', '$2y$10$LxPQtuwX6iHbndRZx1yV8eCahJGMNxh0hjnRGQ/2P3JW4aM3bGu9C', 'peer', 'ira', 'subarashi', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A), Philippines', 'awit', 'asdas', 'Visual', 'asdas', NULL, 1, 1, 1, '2025-09-24 04:16:54', '2025-10-17 17:58:56', 14.06830600, 121.13125600, 212, 'manual', 25, NULL, NULL, 0),
(41, 'iramae', 'iramae@email.com', '$2y$10$bnZ2pVDC6H4Txmd081a5UOImJBCUYrQUucDT62ba7eNSWjoVqZfES', 'student', 'ira', 'mae', '3rd Year College', '', 'IT', 'Tanauan City, Batangas', 'asdas', 'sadsas', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-24 04:20:13', '2025-09-24 04:20:33', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(42, 'irakai1', 'irakai1@email.com', '$2y$10$duHC1cLyqmASbH1SAlR73.pZVfLWLIlaokdhb1RCnk6s..s766Ev6', 'peer', 'ira', 'kai', '3rd Year College', '', 'IT', 'Tanauan City, Batangas', 'asdasd', 'asdasd', 'Auditory', 'asda', NULL, 1, 1, 1, '2025-09-24 05:53:40', '2025-10-16 14:19:50', NULL, NULL, NULL, 'manual', 25, NULL, '2025-10-23 14:19:05', 0),
(43, 'karl', 'karl@yahoo.com', '$2y$10$mGwaUzIOxo1ZhDyMM6vPduAfyn2pWfABVobUBGndIe.pcwMwKamHK', 'student', 'karltzy', 'karl', '4th Year College', '', 'IT', 'Tanauan City, Batangas', 'azxca', 'asasd', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-24 07:01:11', '2025-09-24 07:03:56', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(44, 'oheb', 'oheb@yahoo.com', '$2y$10$Kc6fsdTW5ZV5XN9YLWcYBuIUZ4M682ijiDA7zmeaATGAh36XWe9aW', 'peer', 'oheb', 'oh', '2nd Year College', '', 'IT', 'Tanauan City', 'kjb', 'asd', 'Auditory', 'asdasd', NULL, 1, 1, 1, '2025-09-24 07:23:50', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(45, 'kelra', 'kelra@yahoo.com', '$2y$10$hFeah3YTd5dSS3AbRM3AtOQXVcUdpl/XaXmtDUuwJf6y7lNmeIgK6', 'student', 'kelra', 'as', '2nd Year College', 'GAS', '', 'Batangas', 'asd', 'asdfa', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-24 07:27:54', '2025-09-24 07:28:48', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(46, 'chaks', 'chaknu@email.com', '$2y$10$106hqAArt9ANb6l3Ao0qWO7r6Qj45LbInVqDUmkWvTkN4NgNEfxo2', 'mentor', 'chaknu', 'c', NULL, NULL, NULL, 'Davao', 'kh', NULL, NULL, 'asdas', NULL, 0, 1, 1, '2025-09-24 07:43:25', '2025-09-24 07:45:56', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(47, 'jaypee', 'jp@yahoo.com', '$2y$10$3.Iujzuy2yB8BBVnBn.88.WyRRcGL.EsIta0gQJ3CLVJj8KTm3qtC', 'student', 'jaypee', 'j', '2nd Year College', '', 'IT', 'Batangas City', 'asdasd', 'asda', '', NULL, NULL, 0, 1, 1, '2025-09-24 08:03:17', '2025-09-24 08:04:20', NULL, NULL, NULL, 'manual', 15, NULL, NULL, 0),
(48, 'sanji', 'sanji@email.com', '$2y$10$j2nBTcNfqlAW.ceRkrNY5.ltc00r4synSE3qQF8nbmjwP/lm8hHum', 'mentor', 'sanji', 'san', NULL, NULL, NULL, 'Batangas City', 'asasd', NULL, NULL, 'asdadsd', NULL, 0, 1, 1, '2025-09-24 08:10:53', '2025-09-24 08:11:28', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(49, 'zxc', 'zxc@email.com', '$2y$10$8hJN5TJkh3Wq7j6R7eKDOezfs4xRs.n0XnCQLTmTG5.yItzZ8KLGG', 'peer', 'zxc', 'zxc', '3rd Year College', 'ABM', '', 'asd', 'asdsa', 'asd', 'Visual', 'asasd', NULL, 1, 1, 1, '2025-09-24 08:21:51', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(50, 'demonkite', 'demonkite@email.com', '$2y$10$84qsYxLw.KsqBZPozD1DfOTylgn2FkpeV2T6IgKZ1H3bGah9xBaJG', 'mentor', 'demonkite', 'asd', NULL, NULL, NULL, 'Batangas City', 'asdas', NULL, NULL, 'asdas', 'uploads/profiles/profile_50_1758704895.jpg', 0, 1, 1, '2025-09-24 09:07:42', '2025-09-24 09:08:15', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(51, 'ka', 'rrell@yahoo.com', '$2y$10$LRUir3/Z0egGCrpLRYU93uSoM.2PPg9ih4JJioeODE7L/iohcOv2q', 'peer', 'Karrell', 'Balahadia', '2nd Year College', '', 'IT', 'Batangas City', 'ASDASD', 'ASDAS', 'Auditory', 'ASDASDASD', NULL, 1, 1, 1, '2025-09-24 09:21:00', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(52, 'jaica', 'jaica@email.com', '$2y$10$dpJElY1MWbSicPIqWYYTdewN5teXYBuFNOLV9FOklszOgBjtsR3Iq', 'peer', 'jaica', 'b', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2025-09-25 03:41:41', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(53, 'jandrex', 'janjan@email.com', '$2y$10$/qkrgE2IaKazzDuzZk75Fe6HTRa4uF.FJruFA64UE4D0dryeC3XpW', 'mentor', 'jandrex', 'b', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asdas', NULL, 0, 1, 1, '2025-09-25 03:43:14', '2025-09-25 05:34:34', 14.06820309, 121.13119654, 98, 'manual', 25, NULL, NULL, 0),
(54, 'klowii', 'klowi@email.com', '$2y$10$ZyYBpCfgqihfVlW72YN.i.AcPppJuAQhq2IRW1PzKBfxVC8EcO/9G', 'peer', 'klowi', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdasd', 'Visual', 'asd', NULL, 1, 1, 1, '2025-09-25 06:05:56', '2025-10-03 01:44:09', 14.06820309, 121.13119654, 98, 'manual', 25, NULL, NULL, 0),
(55, 'iya', 'iya@yahoo.com', '$2y$10$8YaaoBN.Y3gZMaGR/Uy52upWJoBD1yOgN0cqYWUBMupZ1f4DtrkbO', 'student', 'iya', 'catapang', 'Grade 11', 'ABM', '', 'Calamba, CALABARZON (Region IV-A)', 'asdas', 'asdas', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-25 06:31:04', '2025-09-25 06:38:25', 14.06830600, 121.13125600, 212, 'manual', 25, NULL, NULL, 0),
(56, 'iza', 'iza@email.com', '$2y$10$mxyg2Bgq35XrMe6za.YQZ.y11Xc9a5.QRRWaRxFo67h5Mi2VresyC', 'mentor', 'iza', 'catapang', NULL, NULL, NULL, 'Santa Rosa, CALABARZON (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 0, 1, 1, '2025-09-25 06:39:23', '2025-09-25 06:40:06', 14.06816029, 121.13115053, 128, 'manual', 25, NULL, NULL, 0),
(57, 'ian', 'ian@email.com', '$2y$10$ljtYW7vpWIMuPsZi.4M7ju/7CXIlZrplKTSaq1bnniY.UDcaFY0fq', 'student', 'ian', 'catapang', '4th Year College', '', 'asdas', 'Tanauan, Calabarzon (Region IV-A)', 'asd', 'asdd', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-25 08:15:22', '2025-09-25 08:16:27', 14.06816790, 121.13116743, 89, 'manual', 25, NULL, NULL, 0),
(58, 'iraman', 'iraman@email.com', '$2y$10$SZWinv2/4NPuMhTe89Emh.JZea2.H13LL0s5he5QKd0cFRuMHMb9W', 'mentor', 'ira', 'kairi', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'asdasdas', NULL, 0, 1, 1, '2025-09-25 08:17:14', '2025-09-25 08:21:29', 14.06815259, 121.13115293, 105, 'manual', 25, NULL, NULL, 0),
(59, 'popo', 'popo@email.com', '$2y$10$c65jReLSspvrtUklCJCWbev.BA3JWsZpdXgLj.UL57sFne5vfzmha', 'peer', 'popo', 'popo', '3rd Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdas', 'Visual', 'asdas', NULL, 1, 1, 1, '2025-09-25 08:27:00', '2025-09-25 12:40:49', 14.06816790, 121.13116743, 89, 'manual', 25, NULL, NULL, 0),
(60, 'pj', 'pj@email.com', '$2y$10$NaTCCxgdfzOK5tFUdaW/4.NXGAvJRTVGZJ.qAAvnE7oPf7rK7M5IG', 'student', 'pj', 'pj', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asd', 'asda', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-25 08:37:40', '2025-09-25 08:38:03', 14.06816790, 121.13116743, 89, 'manual', 25, NULL, NULL, 0),
(61, 'keisha', 'keisha@email.com', '$2y$10$0/xK4VTwxKLR4R/SR3/zWua0OHjncr.AWHgME6akrcP9yDvgDYMQu', 'mentor', 'keisha', 'b', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasdasdasd', NULL, NULL, 'asdas', NULL, 0, 1, 1, '2025-09-25 08:42:47', '2025-09-25 08:43:21', 14.06820817, 121.13119783, 128, 'manual', 25, NULL, NULL, 0),
(62, 'betong', 'betong@email.com', '$2y$10$vw5YsNJTAbCZEf2vPankFeWOfh5HahSX0EsUwW6FKMw7cLeRzKzlC', 'student', 'betong', 'as', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasdasd', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-25 08:58:01', '2025-09-25 08:58:24', 14.06816790, 121.13116743, 89, 'manual', 25, NULL, NULL, 0),
(63, 'asdasdasdasdasd', 'asdasdasdasdasd@email.com', '$2y$10$qzVStg9ICUcnRzqJDcNONu26iEXe/.POemmvfo/GX9.Ms8ifcf4Dq', 'mentor', 'rens', 'asd', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-09-25 10:10:13', '2025-09-25 10:10:13', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(64, 'test', 'asd@s.c', '$2y$10$lRfdnFKzO/uQSTk5lMnE2etMUWsLuqh/I9ePsSGAvBTnex2S2n.Fe', 'student', 'test2', 'test22', 'Grade 12', 'TVL', '', 'Tanauan, Calabarzon (Region IV-A)', 'testasd', 'asd', '', NULL, NULL, 0, 1, 1, '2025-09-25 12:16:59', '2025-09-25 12:37:16', 14.07025064, 121.13032621, 159, 'manual', 25, NULL, NULL, 0),
(65, 'fhukerat', 'fhukerat@email.com', '$2y$10$djURtCyoQy5dwzdW4QSmYuF6SXUkm6dLMBZH60xKjtCERdbqusUBK', 'mentor', 'fhukerat', 'fhu', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'sdasd', NULL, NULL, 'asdsad', NULL, 0, 1, 1, '2025-09-26 12:13:24', '2025-09-26 12:13:59', 14.06820309, 121.13119654, 98, 'manual', 25, NULL, NULL, 0),
(66, 'cvb', 'cvb@email.com', '$2y$10$ePgahpTXCYyO/5EfN4QMVuwUikWUHIvgKa9FjFNWlo9RQ2WlpX40e', 'mentor', 'xcv', 'xcv', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asdad', NULL, 0, 1, 1, '2025-09-26 12:17:03', '2025-09-26 12:19:21', 14.06843307, 121.13103870, 77, 'manual', 25, NULL, NULL, 0),
(67, 'bimbi', 'bimbi@email.com', '$2y$10$0OCqB2brYPljND6k7B8xnuWuwhJomPFA4Tjr9p0Y4fdKukGBm4Bu6', 'peer', 'bimbi', 'bim', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asda', 'Visual', 'asdasd', 'uploads/profiles/profile_67_1758891186.jpg', 1, 1, 1, '2025-09-26 12:52:10', '2025-10-03 01:44:09', 14.06820817, 121.13119783, 128, 'manual', 25, NULL, NULL, 0),
(68, 'marcnoe', 'marcnoe@email.com', '$2y$10$YG1BsYgdckSsnH4UKzfraOu6cPPDMA.rQK229eOhHZ9LmpXCfA2J.', 'peer', 'marc', 'noe', '4th Year College', '', 'IT', 'Nasugbu, Calabarzon (Region IV-A)', 'asdasd', 'asdasd', 'Visual', 'asdasd', NULL, 1, 1, 1, '2025-09-27 10:20:59', '2025-10-03 01:44:09', 14.07300000, 120.62950000, 26953, 'manual', 25, NULL, NULL, 0),
(69, 'nikiminaj', 'nikiminaj@email.com', '$2y$10$CqkHI8wXvylA7nhd8NHiwu189iO8g23hqnCwpsR/2zIeQGPfX14Eq', 'mentor', 'nikiiiii', 'minaj', '4th Year College', '', 'IT', 'Nasugbu, Calabarzon (Region IV-A), Philippines', 'asdasd', NULL, NULL, 'asdas', NULL, 0, 1, 1, '2025-09-27 10:27:50', '2025-09-27 10:31:09', 14.07300000, 120.62950000, 26953, 'manual', 25, NULL, NULL, 0),
(70, 'princess', 'princess@email.com', '$2y$10$BNuUoGC0qw5usBawtKYs1ex9nSb6RfjMZm2UwTvYyKv41iAMBA.Ja', 'peer', 'Princess', 'Klowi', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasda', 'asdasd', 'Auditory', 'asdasd', NULL, 1, 1, 1, '2025-09-27 11:47:01', '2025-09-27 11:47:48', 14.06864500, 121.13101000, 22, 'manual', 25, NULL, NULL, 0),
(71, 'opo', 'opo@yahoo.com', '$2y$10$iv8CRqTIJdUocqamaFVrceqtGrG6x.EoH29/vptVw0b0gHHgTsIUS', 'peer', 'opo', 'opo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2025-09-27 11:52:19', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(72, 'ghj', 'ghj@email.com', '$2y$10$UqmqHfz2zjg4llonKyt6.uPaRbdwM5poFogPX974Gark0V31b72VW', 'peer', 'ghj', 'ghj', '4th Year College', '', 'IT', 'Nasugbu, Calabarzon (Region IV-A)', 'asdasdas', 'asd', 'Visual', 'asdasd', NULL, 1, 1, 1, '2025-09-27 11:58:20', '2025-10-03 01:44:09', 14.07300000, 120.62950000, 26953, 'manual', 25, NULL, NULL, 0),
(73, 'kuyaman', 'kuyaman@email.com', '$2y$10$o.Wui0H/RXaFEYxjUC28BeaPM4Er6tysKYhKdFpakkMBL6oD/.t3C', 'student', 'kuya', 'emman', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'dasdasd', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-28 15:20:50', '2025-09-28 15:22:40', 14.09833080, 121.06582044, 132, 'manual', 25, NULL, NULL, 0),
(74, 'nashivan', 'nashy@email.com', '$2y$10$Vm9dYX0uP85Kg/RE/j5Z9edNol.ah6hN9FCY6ub3H1TxjK9nhff8a', 'peer', 'nash', 'birthday', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'aasdasd', 'asdasd', 'Visual', 'asdasda', NULL, 1, 1, 1, '2025-09-28 16:03:47', '2025-10-03 01:44:09', 14.09832122, 121.06582301, 151, 'manual', 25, NULL, NULL, 0),
(75, 'ghhg', 'hgh@email.com', '$2y$10$NSUtl8uOC0kNKXv0SQAZHODUoghXXPYOLN2JZO2JTJsUfSX.LINJW', 'peer', 'hghg', 'hgh', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'adasd', 'kjhk', 'Visual', 'asdas', NULL, 1, 1, 1, '2025-09-28 18:42:16', '2025-10-03 01:44:09', 14.09831730, 121.06581860, 139, 'manual', 25, NULL, NULL, 0),
(76, 'noemarc', 'noemarc@email.com', '$2y$10$yhqHSBK14YqDYm9UZS.Pp.znazQKHpgRIjCInnYofzv/MGeURzSBK', 'peer', 'noe', 'marc', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'SASDASD', 'asdsad', 'Visual', 'asdasd', NULL, 1, 1, 1, '2025-09-29 00:54:11', '2025-10-03 01:44:09', 14.09834366, 121.06581903, 142, 'manual', 25, NULL, NULL, 0),
(77, 'ivannash', 'ivannash@email.com', '$2y$10$.NQZVwZw4FgFr1Ah4gXVIeQuPMRKWB0f9l3QQ4bKuUXQ1JLV1S7O.', 'student', 'ivan', 'nash', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdas', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-29 01:18:30', '2025-09-29 01:19:01', 14.09834057, 121.06582129, 137, 'manual', 25, NULL, NULL, 0),
(78, 'Lloydemman', 'Lloydemman@email.com', '$2y$10$EUegh12JnGieyXLx2jMcnuHyOkrLqKM37GAOQjCYVKvOAZq02oeT2', 'student', 'Lloyd', 'emman', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-09-29 02:02:43', '2025-09-29 02:02:43', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(79, 'Lloydemmancruz', 'Lloydemmancruz@email.com', '$2y$10$/.CNOWZTV3rkjbkMLX5d6ugL.DAVckQcUNVFxH8JS/v6FKlJTCj2y', 'peer', 'Lloyd', 'Emman', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasda', 'asdas', 'Visual', 'asdas', 'uploads/profiles/profile_79_1759417991.jpg', 1, 1, 1, '2025-09-29 02:03:31', '2025-10-03 01:44:09', 14.09831122, 121.06581527, 131, 'manual', 25, NULL, NULL, 0),
(80, 'nikkomari', 'nikkomari@email.com', '$2y$10$K/Aq4jIhhbg/Kr.xczdHgeMynnQQCnYpv0vzVJfmlUAPX7Dsyu8Ty', 'mentor', 'Nikko', 'mari', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasda', NULL, NULL, 'asdadsa', NULL, 0, 1, 1, '2025-09-29 02:08:08', '2025-09-29 02:08:37', 14.09830981, 121.06581411, 120, 'manual', 25, NULL, NULL, 0),
(81, 'nikolai', 'nikolai@email.com', '$2y$10$jdUg2YEyoPz9LVk7loaqpu9uXuHEKHL6IYx78cxyyWGVczfDithq.', 'peer', '.nikolai', 'niko', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'hello', 'lihjl', 'Visual', 'asdasd', NULL, 1, 1, 1, '2025-09-29 04:24:52', '2025-10-03 01:44:09', 14.09832255, 121.06582273, 144, 'manual', 25, NULL, NULL, 0),
(82, 'kaiRie', 'kaiRie@gmail.com', '$2y$10$w0.v88YBpL0N9t/Gv/5afezzjQYEetMtv.ACNiYXE.Z6Q9e.hR5gm', 'peer', 'kai', 'Rie', '1st Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'nana main', 'solving math', 'Visual', 'kjb', 'uploads/profiles/profile_82_1759204526.jpg', 1, 1, 1, '2025-09-30 03:50:02', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(83, 'karrellira', 'karrellir@email.com', '$2y$10$MUpxjTsTdXUCJftTpr9U6upxaeeFR0G7AqHI6tyGTgkMC9CDEM/be', 'peer', 'karrell', 'ira', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdasd', 'Visual', 'asdas', NULL, 1, 1, 1, '2025-09-30 05:37:25', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(84, 'karrellxnikko', 'kanik@email.com', '$2y$10$PBi.crl.AzXyUSXVG3z.NuequZ9ehaljTBjKdCW/8Q1e0rZ8tGNKO', 'peer', 'karreelll', 'nikko', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', ',jbnkj', 'Visual', 'asdas', NULL, 1, 1, 1, '2025-09-30 05:46:00', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(85, 'nikmakino', 'nikmakino@email.com', '$2y$10$lHr.lxiwf6vmCRtgvI8h.ONJF/q9jmnszLcENf5swVj8IlhRQSJfO', 'mentor', 'nik', 'makino', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'sdasd', NULL, 0, 1, 1, '2025-09-30 05:50:21', '2025-09-30 05:51:39', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(86, '123', '123@email.com', '$2y$10$gZ84ceoKCyQm80VmnMqnb.oyinTBCs6gXr/7pil7QW0GLwnLHNHOi', 'mentor', '123', '123', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asdas', NULL, 1, 1, 1, '2025-09-30 05:52:15', '2025-10-01 03:45:39', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(87, 'nikkokarrell', 'nika@email.com', '$2y$10$jAGakwHt2H6A6tpP.cabbudpxqw4Rixb7RXxyeY3movPsmf3utF5a', 'peer', 'nikko', 'karrell', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'ajsghdjash', 'asdas', 'Visual', 'asdasd', NULL, 1, 1, 1, '2025-09-30 05:58:32', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(88, 'JAlfonso', 'JayAlfonso@gmail.com', '$2y$10$Crd0vG3lyTdaHSW2NohWB.aqn1BHS90Jbku4RZVDxdJJ7H3QRTyGm', 'peer', 'Jay', 'Alfonso', '4th Year College', '', 'BS Psychology', 'Tanauan, Calabarzon (Region IV-A)', 'walang himala', 'to learn other things that im not good at and teach those who are willing to learn', 'Auditory', 'i can teach effectively by conversing and showing visuals', NULL, 1, 1, 1, '2025-09-30 06:03:56', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(89, 'nikk', 'nikminaji@email.com', '$2y$10$AeNRqyv3x.pfXuVJsuDDze5b2wVq1WDdPD5Zv9ydTRj/gCfNvpp1e', 'student', 'nik', 'minaji', 'Grade 12', 'STEM', '', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdas', 'Visual', NULL, NULL, 0, 1, 1, '2025-09-30 06:27:12', '2025-09-30 06:27:49', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(90, 'peers', 'peer@email.com', '$2y$10$yoczWFgZZJ.FCr7kpOvAJelaafUd7o7hBT8RHZXyU6zKmHodxgQW6', 'peer', 'peers', 'mentonr', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asdad', 'Visual', 'asdasda', NULL, 1, 1, 1, '2025-09-30 10:43:58', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(91, 'princessklowu', 'princessklowie@email.com', '$2y$10$fnA7VBH1rUxdjzJq.sLd4uAPvCwMpcZJq/.ihfYMUfyXGM.mlk8OG', 'peer', 'princess', 'klowi', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asdasd', 'Visual', 'asds', NULL, 1, 1, 1, '2025-09-30 10:47:32', '2025-10-03 01:44:09', 14.08000000, 121.15000000, 50000, 'manual', 25, NULL, NULL, 0),
(92, '456', '456@email.com', '$2y$10$VN9/w1nfKGWuMBwNcfljGOJLo5oGH57AZh9lY9/FRK8d/6rKNTcPq', 'peer', '456', '123', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdsa', 'asd', 'Visual', 'asdasd', NULL, 1, 1, 1, '2025-09-30 10:53:56', '2025-10-03 01:44:09', 14.06838400, 121.13111900, 72, 'manual', 25, NULL, NULL, 0),
(93, 'mimasor', 'mimasor@email.com', '$2y$10$TsM1Eq1Wtlc5bxeHdYya1ulqpo6XXNCOZcAoZ0MUmUYNJVcmF7YpK', 'peer', 'mima', 'sor', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'i am nikko', 'i want to be a good man', 'Visual', 'i am good at u', 'uploads/profiles/profile_93_1759318769.jpg', 1, 1, 1, '2025-10-01 11:37:37', '2025-10-03 01:44:09', 14.06820428, 121.13090060, 187, 'manual', 25, NULL, NULL, 0),
(94, 'sormima', 'sormima@email.com', '$2y$10$SUAWKyrIFjqOejiN6VOWte4h5inv8h4FP6Rj7WWv7Chy7pYe7jkFC', 'student', 'sor', 'mima', '3rd Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'i am mimasor', 'good knowledge', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-01 11:40:14', '2025-10-01 11:40:56', 14.06820428, 121.13090060, 187, 'manual', 25, NULL, NULL, 0),
(95, 'rob', 'rob@email.com', '$2y$10$3x6kG710uwv2dO3wizIH6.0rvAhBeYwjgBjfgoMzA7A3TNPqEnlVq', 'peer', 'rob', 'daniel', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', 'asd', NULL, 1, 1, 1, '2025-10-01 11:57:01', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(96, 'michael', 'michael@email.com', '$2y$10$pftB.39GfwJiP0TPVNTbG.QpRDNkzmRlahLCvzk5NJEi2t7WSFrXm', 'peer', 'michael', 'pangilinan', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'ASD', 'ASD', 'Visual', 'ASDASD', NULL, 1, 1, 1, '2025-10-01 12:02:55', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(97, 'juan', 'juancarlos@email.com', '$2y$10$COGMTOlnPFLeZiZXi2hqV.OrB/12042Es7XQFIfEsgNOydEAfwZue', 'peer', 'juan', 'carlos', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'sad', 'sda', 'Visual', 'asd', NULL, 1, 1, 1, '2025-10-01 12:16:27', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(98, 'this', 'this@email.com', '$2y$10$cnk6D/gnoHRCq7ZPyTg0rOYLm1Yb59PqT39DZcpitXjBq9iXbYtwe', 'peer', 'this', 'band', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'ASD', 'sdf', 'Visual', 'ASD', NULL, 1, 1, 1, '2025-10-01 12:23:51', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(99, 'rivermaya', 'rivermaya@email.com', '$2y$10$kSNz24TvQ3irrBeBEujr1.Rc3ZOJDUphpI7RrEBJXyqLNTj52PZQi', 'student', 'river', 'maya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-01 12:49:52', '2025-10-01 12:49:52', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(100, 'noeman', 'noeman@email.com', '$2y$10$b9d1lL0BAQWwBM7LxsYXzeM.GjiZKHIKJRhJLHW.NXelDj0C/FByG', 'student', 'noe', 'marc', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-02 12:17:30', '2025-10-02 12:17:53', 14.06812293, 121.13102179, 129, 'manual', 25, NULL, NULL, 0),
(101, 'karrellgirly', 'karrellgirly@email.com', '$2y$10$.tg8Cfl.95MAODSCt8Z0o.DXk1SaeF8i31a1hS30fW4iwUnox9HeG', 'student', 'karrell', 'catapang', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'gv', 'asdas', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-02 12:19:53', '2025-10-02 12:21:39', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(102, 'jinggoy', 'jinggoy@email.com', '$2y$10$zZVVodtvpED94YRqk2nHVOQhtlmrEtOb6A4akQLSHpD/mgjl8SILu', 'student', 'jinggoy', 'estrada', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-02 12:27:03', '2025-10-02 12:29:38', 14.06808926, 121.13110395, 159, 'manual', 25, NULL, NULL, 0),
(103, 'clarenceman', 'clarenceman@email.com', '$2y$10$5XDIgHSGyuKp49XqVZ8uROpwDW.K4vwxAT6MvG5JDYSeZc5RnCG6K', 'mentor', 'clarence', 'man', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asdas', NULL, 0, 1, 1, '2025-10-02 12:40:19', '2025-10-02 12:40:48', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(104, 'nikimakino', 'nikimakino@email.com', '$2y$10$AoiquA6bp7m1vCPo/mDcyO8YWbu7NKcAKqylVi310LfhaTRfyvAOS', 'student', 'nik', 'makino', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-02 12:42:53', '2025-10-02 12:43:15', 14.06818870, 121.13113177, 159, 'manual', 25, NULL, NULL, 0),
(105, 'edna', 'ednaly@email.com', '$2y$10$/8J/uVjA6l70VgR1LLWfqecrfOqoZ0TYbSGAXW8KJgbDoUg.GcpDm', 'mentor', 'edna', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-02 12:44:18', '2025-10-02 12:45:43', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(106, 'gerry', 'gerry@email.com', '$2y$10$SS5LzMCOtx7KETD0QOnvies1g4Pk7xJnI6OrAyUF1t972.hchosCC', 'mentor', 'gerry', 'boy', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-02 12:52:48', '2025-10-02 12:52:48', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(107, '098', '098@email.com', '$2y$10$Ex7YthZxOR8yg/v/YZ8Fgujw.BTx5XuJmS9CXezUGXJ2jkN1JeRCS', 'mentor', '098', '098', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-02 13:10:13', '2025-10-02 13:10:13', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(108, 'ASDASDASDASD', 'ASDASD@YAHOO.COM', '$2y$10$EKCYVAvCQpU2tGPaFt6I4eprrQtVR7ip4QNx8aUq2rj5G04eoDTYK', 'student', 'jhb', 'GFC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-02 13:10:52', '2025-10-02 13:10:52', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(109, 'CVBM', 'CVBM@EMAIL.COM', '$2y$10$QgnsKV2VISig.Nn2z3njReOaNZ6tAkf5/kZDZsZs5bWo6/a2WmBym', 'peer', 'CVB', 'CVB', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2025-10-02 13:11:35', '2025-10-03 01:44:09', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(110, 'jaja', 'jaja@email.com', '$2y$10$rtkLNlCBLERIwtgjfjO0ienjSVZY9DUVa145vZOMDpSMPbESDUpO.', 'mentor', 'jaja', 'balahadai', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-02 13:24:12', '2025-10-02 13:24:12', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(111, 'bebe', 'bebe@email.com', '$2y$10$e5KGehEfwCbXSYwXgDOVH.Y23x4hM4068IGznY2p4Y9uy2GkH8Nu.', 'mentor', 'be', 'be', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-02 13:55:33', '2025-10-02 13:55:33', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(112, 'nikmi', 'nikmi@email.com', '$2y$10$w6b/7kIRVjw.fcX3Mc3c.ut9N/mMjDG2srF1UVEwCBXcP3WXgu5e2', 'student', 'nik', 'mi', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asd', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-02 14:19:38', '2025-10-02 14:20:00', 14.06812293, 121.13102179, 129, 'manual', 25, NULL, NULL, 0),
(113, 'nigga', 'nigga@email.com', '$2y$10$AaUIPVkRML03jUxORKd30.aGN7NkKAC3sDBNJ/SAKVbz1u3TbuYeq', 'peer', 'nig', 'ga', '3rd Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', 'kjj', 'uploads/profiles/profile_113_1759417739.jpg', 1, 1, 1, '2025-10-02 14:29:30', '2025-10-03 01:44:09', 14.06818355, 121.13103875, 129, 'manual', 25, NULL, NULL, 0),
(114, 'niggman', 'niggman@email.com', '$2y$10$ntrYTEtfouyEDemL58BX4OPkRyQwMEARz3.PTr.u.Yujt2A1eF/7a', 'mentor', 'nigga', 'man', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-02 14:33:35', '2025-10-02 14:34:05', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(115, 'niggy', 'niggy@email.com', '$2y$10$SF2VbZPyOCQzMUIOtV7nCOjerM0chzGaDnoiWbi58rt5QXfcLXuZy', 'mentor', 'niggy', 'nig', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-02 14:39:03', '2025-10-02 14:39:51', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(116, 'renfer', 'renfer@email.com', '$2y$10$3lOi1yyEdH1z4GsZN0lYU.78W7QMvtxIhjBVArZrjIY2BdMlJEZdy', 'mentor', 'renfer', 'man', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 0, 1, 1, '2025-10-02 14:54:22', '2025-10-02 14:57:27', 14.06812293, 121.13102179, 129, 'manual', 25, NULL, NULL, 0),
(117, 'nigs', 'nigs@email.com', '$2y$10$Obk5YWz21juZai4Ei0VjzO8UlTzCOcUJdt7DuXPxdUJ3eJAMmBbY6', 'student', 'am', 'nigga', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasda', 'asd', 'Visual', NULL, 'uploads/profiles/profile_117_1759418273.jpg', 0, 1, 1, '2025-10-02 15:16:51', '2025-10-02 15:17:53', 14.06813868, 121.13106725, 108, 'manual', 25, NULL, NULL, 0),
(118, 'h', 'wth@EMAIL.COM', '$2y$10$x6hVBc9oDuf.Ahh0PL6Am.8YplzSsUaNK.BPBfq1kNs41Errwrj3e', 'mentor', 'w', 't', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-02 15:31:17', '2025-10-02 15:31:17', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(119, 'klowiprincess', 'klowiprincess@email.com', '$2y$10$LOKV5YFV1lNIyYaG3WS5Runnb/GM.PYkX0YrL/PwOWJPg3BiHmsGC', 'mentor', 'klowi', 'princess', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 0, 1, 1, '2025-10-03 02:37:48', '2025-10-03 02:38:10', 14.06818870, 121.13113177, 159, 'manual', 25, NULL, NULL, 0),
(120, 'christ', 'christ@email.com', '$2y$10$hDZ98eyy6LfvyT0YQElO3e0toWP2MxseXzgZkZ5ecsD.hnm11I0le', 'student', 'christ', 'mas', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', NULL, 'uploads/profiles/profile_120_1759459925.jpg', 0, 1, 1, '2025-10-03 02:51:41', '2025-10-03 02:52:05', 14.06841450, 121.13095250, 500, 'manual', 25, NULL, NULL, 0),
(121, 'Henry', 'Henry@email.com', '$2y$10$zqx1knpSIYvT7QnxjQwyMe58TaBe80g55clY1HPxPcp1eKg.fQB6u', 'student', 'henry', 'alcantara', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'fsdfsdf', 'sdfsdf', 'Visual', NULL, 'uploads/profiles/profile_121_1759493514.jpg', 0, 1, 1, '2025-10-03 12:11:16', '2025-10-03 12:11:54', 14.06820428, 121.13090060, 187, 'manual', 25, NULL, NULL, 0),
(122, 'Black', 'Blackjack@email.com', '$2y$10$dBM2jlpuglBjIir0se57hOXpgGFqOeLcKK0VvHMro2NpLVXC9unPi', 'mentor', 'black', 'jack', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'asdas', 'uploads/profiles/profile_122_1759493584.jpg', 1, 1, 1, '2025-10-03 12:12:20', '2025-10-03 12:53:26', 14.06820428, 121.13090060, 187, 'manual', 25, NULL, NULL, 0),
(123, 'bbm', 'bbm@email.com', '$2y$10$jU67cTGC1Dd6wh3rZuC0MeTFkD1AnzNf5p9Qd9fxms.Eve/enouyW', 'student', 'bbm', 'b', '3rd Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'sadsd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-03 12:33:19', '2025-10-03 12:34:53', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(124, 'jandel', 'jandel@email.com', '$2y$10$TZSRXGoPS0VrSvL8HMMHtemxNiIRUklQoC8umAxq9yDd/LTWTlbuy', 'mentor', 'jandel', 'blabla', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'sda', NULL, 0, 1, 1, '2025-10-03 12:42:50', '2025-10-03 12:43:50', 14.06841450, 121.13095250, 500, 'manual', 25, NULL, NULL, 0),
(125, 'clarence', 'clarence@yahoo.com', '$2y$10$yfoPMszO.wNh34AUlxdQcuN2j.ds7cLSi9MtEbp/QUm8t/qOlm.rG', 'student', 'Clarence', 'Meneses', 'Grade 7', 'STEM', 'IT', 'Gonzales', 'pakyu jaja', 'asdasda', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-04 03:43:08', '2025-10-04 03:44:05', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(126, 'keith', 'ubanamarcnoev@gmail.com', '$2y$10$RB3pjWXpcvL.ZYTdIW0J4e8URYZ.1mSEv3hTnwZlxGjjRa8ACf3oK', 'student', 'keith', 'k', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-04 09:53:10', '2025-10-04 09:53:10', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(127, 'medi', 'Balahadianikko2020@gmail.com', '$2y$10$hDkNovYFBwr.LFc8yuV8k.PjTybvjYoUD6o0GU8PxF0qVR.IbslrS', 'mentor', 'medi', 'balahadia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2025-10-04 12:07:57', '2025-10-05 05:58:46', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(128, 'iraku', 'irak@email.com', '$2y$10$y/CAFwdtDGPUuMS31OOiU.PoMAcegtI3vUx0aI8/rn.eFr3gZaTcC', 'mentor', 'irak', 'cat', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'asd', NULL, 0, 1, 1, '2025-10-05 06:20:45', '2025-10-05 06:21:17', 14.06818870, 121.13113177, 159, 'manual', 25, NULL, NULL, 0),
(129, 'tyson', 'tyson@email.com', '$2y$10$g3CEqyuLuz99ca5U1gfEDOa.PLZrik9VtAbeNyWdj6KrLBzEvDuA6', 'student', 'tyson', 'police', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asd', 'uigi', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-05 10:10:10', '2025-10-05 10:11:21', 14.06834660, 121.13125110, 20, 'manual', 25, NULL, NULL, 0),
(130, 'tysoni', 'tysonn@email.com', '$2y$10$ZfCwLvnOfAxerXcAzeU/7.Jl4raQfkcoXxq2DlaAiqi8IYkH1yDt.', 'mentor', 'tysonman', 'ty', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-05 10:12:14', '2025-10-05 10:14:10', 14.06834610, 121.13125700, 29, 'manual', 25, NULL, NULL, 0),
(131, 'adam', 'adam@email.com', '$2y$10$4UcVdbqW9yG8Rxg7cyNixOx9XnmycJ8M6NuJunEst3MwIHYxXYa5u', 'mentor', 'adam', 'balahadia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-05 10:50:56', '2025-10-05 10:50:56', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(132, 'noah', 'noahelizalde@email.com', '$2y$10$RGIihcRXzAH99zQYYugwiuJY0wpSnAXTgAiuh9d6Sd8tlPaKwE61e', 'student', 'noah', 'elizalde', '4th Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'i gave u to someone special', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-05 10:51:44', '2025-10-05 10:52:27', 14.06841450, 121.13095250, 500, 'manual', 25, NULL, NULL, 0),
(133, 'kachow', 'Mcqueen@gmail.com', '$2y$10$pwmiIxMeIec02qmu.4zeVePV9Z627z.P/vZf9rc25u7SqE5OV0qD.', 'student', 'Lightning', 'Mcqueen', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, '2025-10-05 12:26:49', '2025-10-05 12:26:49', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(134, 'angela', 'iraaasubarashi@gmail.com', '$2y$10$hjOMhqGnvoBPZ/dxtbF5SuSL958eFujIkHzZz2RK8t0PFBkxsEDre', 'mentor', 'angela', 'vil', NULL, NULL, NULL, 'San Pablo, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asdas', 'uploads/profiles/profile_134_1759764949.jpg', 0, 1, 1, '2025-10-06 15:34:59', '2025-10-06 15:35:49', 14.06422390, 121.32326050, 101724, 'manual', 25, NULL, NULL, 0),
(135, 'emmanoy', 'emmanvirrey41@gmail.com', '$2y$10$Nxgib6EIuaOduELRMUhpf.nMJ47zFCr8HoIXuxhFxIy2UIFA9/7v2', 'student', 'emmanoy', 'man', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, '2025-10-06 15:42:07', '2025-10-06 15:42:07', NULL, NULL, NULL, 'manual', 25, NULL, NULL, 0),
(136, 'andrei', '22-69937@g.batstate-u.edu.ph', '$2y$10$4I5Bj4ZFPFnlu20yZ/s6GOgLApeEBGEbzDtpXVkzhFYu25b8rzwOq', 'student', 'andreoi', 'sevi', '2nd Year College', '', 'BSIT', 'San Pablo, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', NULL, NULL, 1, 1, 1, '2025-10-06 16:05:50', '2025-10-06 16:06:13', 14.06422390, 121.32326050, 101724, 'manual', 25, NULL, NULL, 0),
(137, 'nikkoss', 'nikkoss@email.com', '$2y$10$WDTe40dmhHy00obp7QamPurxKnL2QJAfd9efZWEabS6rKB1Y.kxYq', 'student', 'nikkos', 'virrey', '2nd Year College', '', 'BS Information Technology', 'Tanauan, Calabarzon (Region IV-A)', 'asdasdasd', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-06 16:11:53', '2025-10-06 16:12:24', 14.09832664, 121.06582754, 132, 'manual', 25, NULL, NULL, 0),
(138, 'kristelle', 'kristelle@email.com', '$2y$10$7uZc1srP/NZuooY2hHxc2.TLRK8p5kgoA/M28VPsMjT88dwxxV/hq', 'mentor', 'kristelle', 'catapang', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-06 16:14:39', '2025-10-14 04:18:32', 14.09835715, 121.06584433, 121, 'manual', 25, NULL, NULL, 0),
(139, 'mema', 'capilaapril@gmail.com', '$2y$10$pX6N/KK3rNChXk4MiE99/upHXwQbswwb2XsxD0z/DCfCAKMT/lS8q', 'student', 'mema', 'mema', '4th Year College', '', 'BSIT', 'Dasmarinas, Calabarzon (Region IV-A)', 'asd', 'asda', 'Visual', NULL, NULL, 1, 1, 1, '2025-10-07 10:09:01', '2025-10-07 10:09:37', 14.35238400, 120.97617920, 63087, 'manual', 25, NULL, NULL, 0),
(140, 'iramani', 'jfgymmanagement@gmail.com', '$2y$10$/G6C6aNorIPDpl2tS4c9Ku8bQV1re66U6hyCb9WOjFtq7awb4RzFW', 'mentor', 'iraaaa', 'subarashi', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asdad', NULL, 1, 1, 1, '2025-10-07 10:39:47', '2025-10-07 10:40:06', 14.06835290, 121.13124710, 20, 'manual', 25, NULL, NULL, 0),
(141, 'george', 'georgecatapang76@gmail.com', '$2y$10$bIl9uCAy2RC/HDgbBtSA2uFrYAMCzs33yvH5Zupc6MPbGO.fjqfxm', 'student', 'george', 'catapang', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'qwd', 'Visual', NULL, NULL, 1, 1, 1, '2025-10-07 13:09:03', '2025-10-07 13:09:23', 14.06835180, 121.13124710, 12, 'manual', 25, NULL, NULL, 0),
(142, 'ednalyn', '22-69954@g.batstate-u.edu.ph', '$2y$10$ePyUx4grEDqhORdSg2vlveGuPHiQMC1PDkk/HzDJrJNijNB.lxDd.', 'mentor', 'ednalyn', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 07:26:59', '2025-10-08 07:27:19', 14.06837120, 121.13094243, 128, 'manual', 25, NULL, NULL, 0),
(143, 'Adangot', 'perrybalahadia21@gmail.com', '$2y$10$Tc0E5x8PA89g6OtLlWyufOLO9YCr.Kq.29Wsd/ESBnheULI4geXm6', 'mentor', 'Adam', 'Balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 07:41:43', '2025-10-08 07:42:03', 14.06837267, 121.13094361, 128, 'manual', 25, NULL, NULL, 0),
(144, 'mabel', 'masdaskdj@email.com', '$2y$10$qlF9D4Rc7nR/c24YJObD5OuUH/oFecl98PZP0TUEKP/eShPZh7YdK', 'mentor', 'mabel', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 08:45:23', '2025-10-08 08:45:47', 14.06837267, 121.13094361, 128, 'manual', 25, NULL, NULL, 0),
(145, 'maricar', 'maricarsadsdq@email.com', '$2y$10$uq8jSQa77PLZaJ6K3KpnsO3bCvg/wo8ABFmxFOlJ1Mu2eQ9byhd2K', 'mentor', 'maricar', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasdas', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 08:58:43', '2025-10-08 09:00:07', 14.06837267, 121.13094361, 128, 'manual', 25, NULL, NULL, 0),
(146, 'patrickkkkk', 'patrisk@email.com', '$2y$10$pYm7UTDfLeDK1U38XQyV2ORcrXen/Y.Yb9l9CuyyaUdCNKtqBv.km', 'mentor', 'patrick', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasdad', NULL, NULL, 'asdas', NULL, 1, 1, 1, '2025-10-08 09:03:16', '2025-10-08 09:03:33', 14.06837120, 121.13094243, 128, 'manual', 25, NULL, NULL, 0),
(147, 'JP', 'JP@email.com', '$2y$10$vyKtUsRXL38ge60k7WREIu63NZyFRdg4nea6mRvRliUzqDiQXxoni', 'mentor', 'john', 'paul', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asd', 'uploads/profiles/profile_147_1760747551.jpg', 1, 1, 1, '2025-10-08 09:26:34', '2025-10-18 00:32:31', 14.06830099, 121.13092562, 105, 'manual', 25, 200.00, NULL, 0),
(148, 'eric', 'eric@email.com', '$2y$10$vRQj6TmnxIjzYm79oUIuwO7w7twqAUAOd8Nv0I.dgi8vulMoEb1mO', 'student', 'eric', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', NULL, NULL, 1, 1, 1, '2025-10-08 09:32:21', '2025-10-08 09:32:46', 14.06830099, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(149, 'erika', 'erika@email.com', '$2y$10$SWSh7UM6nltzacM.2O5SquPIg.O8V/kJOSWr5VRQ9LD3XPK7/pCyK', 'mentor', 'erika', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasda', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 09:37:54', '2025-10-08 09:38:16', 14.06837120, 121.13094243, 128, 'manual', 25, NULL, NULL, 0),
(150, 'malou', 'malou@email.com', '$2y$10$hEiXbP2F1p9jY2PZym8Q0eLzrzsaQaoFzem0uc4955tvXFN3MnYHa', 'peer', 'malou', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asda', 'Visual', 'asda', NULL, 0, 1, 1, '2025-10-08 09:40:43', '2025-10-08 09:42:00', 14.06830099, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(151, 'tage', 'tage@email.com', '$2y$10$o7y/C5hXM.uGqhonVSdiCuVOn9QOUsXTRHB2nZa7oF3m4rSQKDeJm', 'student', 'tage', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asda', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-08 09:44:57', '2025-10-08 09:45:17', 14.06831508, 121.13092814, 111, 'manual', 25, NULL, NULL, 0),
(152, 'ambok', 'ambok@email.com', '$2y$10$jt9Veqg24JAi8KzMi7a8zO6cQMRI7qw.Ht2lcm7ftnTfRnI.Ippky', 'peer', 'ambok', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Auditory', 'asdas', NULL, 0, 1, 1, '2025-10-08 09:47:00', '2025-10-08 09:49:05', 14.06830099, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(153, 'emar', 'emar@email.com', '$2y$10$w0OdZKJP5OPV/h1lFvjZX.vpP..8Er60n/xewpbQcQaNvTUHkXZ5.', 'peer', 'emar', 'balahadia', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'sad', 'Visual', 'asdas', NULL, 0, 1, 1, '2025-10-08 09:57:23', '2025-10-08 09:58:49', 14.06836709, 121.13094361, 128, 'manual', 25, NULL, NULL, 0),
(154, 'mariel', 'mariel@email.com', '$2y$10$FeHm3bsvLTfAieBjYt1hNu44caTmfiwGKq.8uaRBE4gOKOEWAnFrS', 'mentor', 'mariele', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 10:00:31', '2025-10-08 10:00:52', 14.06837120, 121.13094243, 128, 'manual', 25, NULL, NULL, 0),
(155, 'janel', 'janel@email.com', '$2y$10$924TNQP0Ly5HRo8vqLbk6OLMnJjQr8OMvOyMkPlsAEUlbPDs.6RBe', 'peer', 'janel', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Auditory', 'asd', NULL, 1, 1, 1, '2025-10-08 10:12:39', '2025-10-08 10:15:36', 14.06864500, 121.13101000, 212, 'manual', 25, NULL, NULL, 0),
(156, 'jaicaman', 'jaicaman@email.com', '$2y$10$zweqj/NGE/tkUnaIohZuJuZq4Pk8PTUQX4nLhLcef037DzaNNajR.', 'mentor', 'jaica', 'balahadia', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-08 10:17:08', '2025-10-08 10:17:28', 14.06829748, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(157, 'gerryman', 'gerryb@email.com', '$2y$10$e.bSbYUOHKILXLbCPRF7u.536qkAmtugdGCOqcCsrhhGOGPc98kRy', 'peer', 'gerry', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd123', 'asd', 'Auditory', 'asdas', NULL, 0, 1, 1, '2025-10-08 10:19:53', '2025-10-08 10:21:25', 14.06829748, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(158, 'minda', 'minda@email.com', '$2y$10$VKgyDRuIR4GNfDGE6yKA5ueozvrXWc2zUdO6UtINS/pt5HR4dtsJ2', 'peer', 'minda', 'balahadai', '4th Year College', '', 'it', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Reading/Writing', 'asd', NULL, 0, 1, 1, '2025-10-08 10:22:34', '2025-10-08 10:32:47', 14.06860418, 121.13103830, 187, 'manual', 25, NULL, NULL, 0);
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `grade_level`, `strand`, `course`, `location`, `bio`, `learning_goals`, `preferred_learning_style`, `teaching_style`, `profile_picture`, `is_verified`, `matchmaking_enabled`, `is_active`, `created_at`, `updated_at`, `latitude`, `longitude`, `location_accuracy`, `location_type`, `max_distance_km`, `hourly_rate`, `suspension_until`, `is_banned`) VALUES
(159, 'macaisa', 'jmacaisa2@gmail.com', '$2y$10$bJthkt5mFIcnZG75nzaNy.KLpWiayRp7YSYB9g7ikqH4x6fy9iHee', 'mentor', 'macaisa', 'JM', NULL, NULL, NULL, 'Malvar, Calabarzon (Region IV-A)', 'asdas', NULL, NULL, 'asdasd', NULL, 1, 1, 1, '2025-10-09 02:55:12', '2025-10-09 02:58:18', 14.04410166, 121.15851010, 149, 'manual', 25, NULL, NULL, 0),
(160, 'iraker', 'iraker@email.com', '$2y$10$dJJkcuZz7ZjfdvQVhYNoTe83EndSWLlcdd9Cu3yYUKRNHx1d8VceK', 'peer', 'irak', 'irake', '3rd Year College', '', 'BSIT', 'Malvar, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', 'asdasd', NULL, 0, 1, 1, '2025-10-09 02:57:40', '2025-10-14 04:02:57', 14.04420303, 121.15847054, 109, 'manual', 25, 200.00, NULL, 0),
(161, 'patrick15', 'patrickdelmundo704@gmail.com', '$2y$10$BWHNvQKQisnONV9XairRF.lGT1UtqpPFSoNBXobr5FBCLjLEir2fK', 'peer', 'Patrick', 'Del Mundo', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'heheasdas', 'idk', 'Visual', 'ASSAD', NULL, 0, 1, 1, '2025-10-09 03:05:38', '2025-10-09 08:46:28', 14.06837120, 121.13094243, 128, 'manual', 25, NULL, NULL, 0),
(162, 'ax', 'ax@gmail.com', '$2y$10$iT8/ldu719.2ugF5CRMwWO1TbtKDR9hlGCvSW04Gk9Xmuiv1F4Eda', 'mentor', 'axel', 'mondagol', NULL, NULL, NULL, 'Malvar, Calabarzon (Region IV-A)', 'malupet', NULL, NULL, 'Maangas', NULL, 1, 1, 1, '2025-10-09 03:09:19', '2025-10-09 03:13:33', 14.04421569, 121.15844792, 112, 'manual', 25, NULL, NULL, 0),
(163, 'cedr', 'cedr@email.com', '$2y$10$3EWd5XwVITa6ljXFTkLYDO4fnCfObtRcXq5LiLagqhNiodBioSiVm', 'peer', 'cedr', 'balahadia', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasdasd', 'asdasd', 'Auditory', 'asdsa', NULL, 0, 1, 1, '2025-10-09 08:51:42', '2025-10-09 08:52:47', 14.06864500, 121.13101000, 212, 'manual', 25, NULL, NULL, 0),
(164, 'tumi', 'tumi@email.com', '$2y$10$xuMy6LxalkXtpGfVkADHW.zpOHAtDKJRbc.kEcioq/.IDTqeJ.2ya', 'peer', 'tumi', 'bala', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'fghfg', 'fgh', 'Visual', 'asd', NULL, 0, 1, 1, '2025-10-09 09:02:25', '2025-10-09 09:03:18', 14.06829748, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(165, 'ednaml', 'ednaml@email.com', '$2y$10$EAA1Np7IbZ2K3R3.WfqiEemwSo0PJcSR.LrJbPsQ9FEtinA.8.wmS', 'peer', 'ednaa', 'balaha', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'asd', 'Visual', 'asd', NULL, 1, 1, 1, '2025-10-09 09:06:12', '2025-10-09 10:06:30', 14.06826615, 121.13091789, 94, 'manual', 25, NULL, NULL, 0),
(166, 'nana', 'nana@email.com', '$2y$10$TCAm7CuYgUA39UC8ApZ34O8bdotO.NolSyqEF4SwG.t1ZdbWv0QVW', 'mentor', 'nana', 'na', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'asd', NULL, 1, 1, 1, '2025-10-12 03:27:34', '2025-10-12 03:29:30', 14.06826615, 121.13091789, 94, 'manual', 25, 150.00, NULL, 0),
(167, 'balentina', 'valentina@email.com', '$2y$10$znVz5HaseI7IzvWY9nDkreS1L22QJhknUZIy3hbfcDcmrZ1C59D9y', 'peer', 'valentina', 'man', '1st Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Kinesthetic', 'a', NULL, 0, 1, 1, '2025-10-12 03:37:47', '2025-10-14 02:41:41', 14.06864500, 121.13101000, 212, 'manual', 25, 200.00, NULL, 0),
(168, 'saber', 'saber@email.com', '$2y$10$NkjgiMWafsyNEebU6Tvslu5GRtH1miWEDagss67/K/XbJqMiE.Nb.', 'peer', 'saber', 'man', 'Grade 11', 'TVL', '', 'Tanauan, Calabarzon (Region IV-A)', 'asd', 'asd', 'Kinesthetic', 'asd', NULL, 0, 1, 1, '2025-10-12 03:39:50', '2025-10-14 02:34:52', 14.06829748, 121.13092562, 105, 'manual', 25, 250.00, NULL, 0),
(169, 'yve', 'yve@email.com', '$2y$10$36I2js6EuIgzGf1IMjIctO76xvvTzrBfoLuorD/xaCYOzgbYhid1m', 'peer', 'yve', 'yve', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasdasd', 'asd', 'Auditory', 'asdasd', NULL, 0, 1, 1, '2025-10-12 04:38:23', '2025-10-14 02:33:25', 14.06830099, 121.13092562, 105, 'manual', 25, 250.00, NULL, 0),
(170, 'lily', 'lily@email.com', '$2y$10$PmS1.ZpEB8xvDw47S7Pej.ikdQx1Tc71jN429gK8YeP72cxL1GL.q', 'mentor', 'lily', 'catapang', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asdasdsadasd', NULL, NULL, 'asdasd', NULL, 1, 1, 1, '2025-10-13 03:48:49', '2025-10-13 03:49:21', 14.06830099, 121.13092562, 105, 'manual', 25, 150.00, NULL, 0),
(171, 'jorge', 'jorge@email.com', '$2y$10$dgQq62irqP5CHHh7xlbIiOXkFBAdwtKD9bKdCzKCKwxbpRXUIW0rm', 'mentor', 'george', 'catapang', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', NULL, NULL, 'asd', 'uploads/profiles/profile_171_1760408588.jpg', 1, 1, 1, '2025-10-13 03:55:30', '2025-10-14 03:32:01', 14.06864500, 121.13101000, 212, 'manual', 25, 250.00, NULL, 0),
(172, 'layka', 'layla@email.com', '$2y$10$uKW5Z28z5qegiV0auRu1aepV/.NORWh2tb6JsmAadW0kZ.Wpq20dG', 'mentor', 'layla', 'asdasd', NULL, NULL, NULL, 'Tanauan, Calabarzon (Region IV-A)', 'asd', NULL, NULL, 'ASDASD', 'uploads/profiles/profile_172_1760409169.jpg', 0, 1, 1, '2025-10-14 02:31:22', '2025-10-14 02:32:49', 14.06853751, 121.13099558, 149, 'manual', 25, 200.00, NULL, 0),
(173, 'terrence', 'terrence@email.com', '$2y$10$QvQ.vw1wGcn8e3Ip6HFMP.zn2Im/FKjAhojJDMBPyYq1JaMSxlZY2', 'peer', 'terrence', 'formanes', '3rd Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdas', 'Visual', 'asdas', 'uploads/profiles/profile_173_1760531690.jpg', 0, 1, 1, '2025-10-15 12:33:39', '2025-10-15 13:38:24', 14.06995600, 121.13005100, 213, 'manual', 25, NULL, NULL, 0),
(174, 'joshua', 'joshuatayag@email.com', '$2y$10$GZPhKk7z6eHIKOkMEnGNAuMWhnXiuOxapdJm9SfTXBG6tyCl3k1xm', 'student', 'joshua', 'tayag', '3rd Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Auditory', NULL, 'uploads/profiles/profile_174_1760531948.jpg', 0, 1, 1, '2025-10-15 12:38:23', '2025-10-15 12:39:08', 14.07031787, 121.13072106, 108, 'manual', 25, NULL, NULL, 0),
(175, 'jamiel', 'jamiel@email.com', '$2y$10$JQ1yaM7yDHyCnGeNG0qzpuUxz3e44YH7H76yqN44v.kuwKv4n3cm2', 'student', 'jamiel', 'bryan', '3rd Year College', '', 'phsyc', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'sAD', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-15 12:54:01', '2025-10-15 12:54:31', 14.07016547, 121.13012542, 141, 'manual', 25, NULL, NULL, 0),
(176, 'renzo', 'renzo@email.com', '$2y$10$1UBc0ApK5X5oq6Epd2ECj.jz4zIlaI4d.fsjKD8RF3R3rwSUz0VGi', 'student', 'renzo', 'formanes', '2nd Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asdas', 'Visual', NULL, NULL, 1, 1, 1, '2025-10-15 13:16:08', '2025-10-18 05:49:44', 14.07040545, 121.13067199, 99, 'manual', 25, NULL, NULL, 0),
(177, 'earl', 'earl@email.com', '$2y$10$PXoyAaSeQ/0Jwmc97W0WZ.1Gy0CkcW.CejIeEkHlYzoEMQL/gkRiO', 'student', 'earl', 'dipa', '2nd Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdsa', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-16 13:02:58', '2025-10-16 13:03:46', 14.06864500, 121.13101000, 212, 'manual', 25, NULL, NULL, 0),
(178, 'karrelly', 'karrelly@email.com', '$2y$10$99Ej71.fkVUO2iyj6PYGA.6DUuWR63cCVt5z5Cdz6nLWqk83X9ET.', 'student', 'Karrelly', 'asd', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdas', 'assd', 'Kinesthetic', NULL, NULL, 0, 1, 1, '2025-10-16 13:24:11', '2025-10-16 13:24:51', 14.06830099, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(179, 'email', 'email@email.com', '$2y$10$lTT7Kx5Tn7aDTd5evArjh.RZMz/73M5jPsx7LLod/CEi6/LZMoJY2', 'student', 'email', 'email', '4th Year College', '', 'IT', 'Tanauan, Calabarzon (Region IV-A)', 'asd', 'asda', 'Auditory', NULL, NULL, 0, 1, 1, '2025-10-17 03:44:13', '2025-10-17 03:44:38', 14.06829748, 121.13092562, 105, 'manual', 25, NULL, NULL, 0),
(181, 'AAA', 'AAA@email.com', '$2y$10$5n4oD.QeEYho8gbApIVuE.KKJKMqKtgoVnBkZO6ymxpa/9HqUAnDu', 'student', 'AAA', 'aaa', '2nd Year College', '', 'IT', 'Batangas City', 'asdas', 'asd', 'Kinesthetic', NULL, NULL, 1, 1, 1, '2025-10-17 12:28:05', '2025-10-17 18:04:08', 14.09248730, 121.05842661, 159, 'manual', 25, NULL, NULL, 0),
(182, 'qwertys', 'qwertys@email.com', '$2y$10$zIpCmEBCuKv2aUYFIURMruW/W9e6L5WbDZEWsh0io746TguuTLe.u', 'student', 'qwertys', 'qwerty', '4th Year College', '', 'BSIT', 'Tanauan, Calabarzon (Region IV-A)', 'asdasd', 'asd', 'Visual', NULL, NULL, 0, 1, 1, '2025-10-18 01:50:34', '2025-10-18 01:50:57', 14.09282983, 121.05794505, 159, 'manual', 25, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-19 13:59:04'),
(2, NULL, 'logout', '{\"timestamp\":\"2025-09-19 22:21:11\"}', '::1', '2025-09-19 14:21:11'),
(3, 3, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-19 14:21:30'),
(4, 3, 'logout', '{\"timestamp\":\"2025-09-19 22:24:07\"}', '::1', '2025-09-19 14:24:07'),
(5, 4, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-19 14:24:26'),
(6, 4, 'logout', '{\"timestamp\":\"2025-09-19 22:25:04\"}', '::1', '2025-09-19 14:25:04'),
(7, 3, 'login', '{\"success\":true}', '::1', '2025-09-19 14:25:09'),
(8, 3, 'logout', '{\"timestamp\":\"2025-09-19 23:12:38\"}', '::1', '2025-09-19 15:12:38'),
(9, 5, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-19 15:12:56'),
(10, 5, 'match_request', '{\"match_id\":\"2\",\"mentor_id\":4,\"subject\":\"History\"}', '::1', '2025-09-19 15:14:18'),
(11, 5, 'logout', '{\"timestamp\":\"2025-09-20 19:45:34\"}', '::1', '2025-09-20 11:45:34'),
(12, 4, 'login', '{\"success\":true}', '::1', '2025-09-20 11:46:00'),
(13, 4, 'match_response', '{\"match_id\":1,\"response\":\"accepted\"}', '::1', '2025-09-20 11:46:08'),
(14, 4, 'logout', '{\"timestamp\":\"2025-09-20 19:46:26\"}', '::1', '2025-09-20 11:46:26'),
(15, 3, 'login', '{\"success\":true}', '::1', '2025-09-20 11:46:32'),
(16, 3, 'session_scheduled', '{\"match_id\":1,\"date\":\"2025-09-20\"}', '::1', '2025-09-20 11:47:06'),
(17, 3, 'session_scheduled', '{\"match_id\":1,\"date\":\"2025-09-20\"}', '::1', '2025-09-20 11:47:36'),
(18, 3, 'logout', '{\"timestamp\":\"2025-09-20 19:49:05\"}', '::1', '2025-09-20 11:49:05'),
(19, 6, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-20 11:49:33'),
(20, 6, 'logout', '{\"timestamp\":\"2025-09-20 19:50:52\"}', '::1', '2025-09-20 11:50:52'),
(21, 7, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-20 11:51:10'),
(22, 7, 'logout', '{\"timestamp\":\"2025-09-20 19:58:59\"}', '::1', '2025-09-20 11:58:59'),
(23, 6, 'login', '{\"success\":true}', '::1', '2025-09-20 11:59:05'),
(24, 6, 'match_request', '{\"match_id\":\"3\",\"mentor_id\":5,\"subject\":\"History\"}', '::1', '2025-09-20 11:59:43'),
(25, 6, 'match_request', '{\"match_id\":\"4\",\"mentor_id\":7,\"subject\":\"History\"}', '::1', '2025-09-20 12:00:00'),
(26, 6, 'logout', '{\"timestamp\":\"2025-09-20 20:00:18\"}', '::1', '2025-09-20 12:00:18'),
(27, 7, 'login', '{\"success\":true}', '::1', '2025-09-20 12:00:34'),
(28, 7, 'match_response', '{\"match_id\":4,\"response\":\"accepted\"}', '::1', '2025-09-20 12:00:41'),
(29, 7, 'logout', '{\"timestamp\":\"2025-09-20 20:13:13\"}', '::1', '2025-09-20 12:13:13'),
(30, 8, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-20 12:13:34'),
(31, 8, 'logout', '{\"timestamp\":\"2025-09-20 20:14:22\"}', '::1', '2025-09-20 12:14:22'),
(32, 1, 'login', '{\"success\":true}', '::1', '2025-09-20 12:14:27'),
(33, 1, 'admin_verify_user', '{\"verified_user_id\":8}', '::1', '2025-09-20 12:14:46'),
(34, 1, 'admin_verify_user', '{\"verified_user_id\":8}', '::1', '2025-09-20 12:14:52'),
(35, 1, 'logout', '{\"timestamp\":\"2025-09-20 20:16:36\"}', '::1', '2025-09-20 12:16:36'),
(36, 8, 'login', '{\"success\":true}', '::1', '2025-09-20 12:16:43'),
(37, 8, 'logout', '{\"timestamp\":\"2025-09-20 20:41:25\"}', '::1', '2025-09-20 12:41:25'),
(38, 1, 'login', '{\"success\":true}', '::1', '2025-09-20 12:41:28'),
(39, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 12:26:17'),
(40, 1, 'logout', '{\"timestamp\":\"2025-09-21 20:26:41\"}', '::1', '2025-09-21 12:26:41'),
(41, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 12:27:23'),
(42, 8, 'logout', '{\"timestamp\":\"2025-09-21 20:28:25\"}', '::1', '2025-09-21 12:28:25'),
(43, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 12:28:36'),
(44, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 12:33:59'),
(45, 1, 'logout', '{\"timestamp\":\"2025-09-21 20:39:38\"}', '::1', '2025-09-21 12:39:38'),
(46, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 12:39:46'),
(47, 8, 'login', '{\"success\":true}', '192.168.1.23', '2025-09-21 13:06:07'),
(48, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:07:58'),
(49, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:08:23\"}', '::1', '2025-09-21 13:08:23'),
(50, 9, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 13:09:15'),
(51, 9, 'match_request', '{\"match_id\":\"5\",\"mentor_id\":4,\"subject\":\"Filipino\"}', '::1', '2025-09-21 13:09:52'),
(52, 9, 'logout', '{\"timestamp\":\"2025-09-21 21:14:21\"}', '::1', '2025-09-21 13:14:21'),
(53, 4, 'login', '{\"success\":true}', '::1', '2025-09-21 13:14:25'),
(54, 4, 'logout', '{\"timestamp\":\"2025-09-21 21:14:47\"}', '::1', '2025-09-21 13:14:47'),
(55, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 13:14:50'),
(56, 1, 'logout', '{\"timestamp\":\"2025-09-21 21:15:16\"}', '::1', '2025-09-21 13:15:16'),
(57, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:15:24'),
(58, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:15:35\"}', '::1', '2025-09-21 13:15:35'),
(59, 10, 'register', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORE21728\",\"referral_code_id\":2,\"referred_by\":8}', '::1', '2025-09-21 13:16:01'),
(60, 10, 'logout', '{\"timestamp\":\"2025-09-21 21:17:45\"}', '::1', '2025-09-21 13:17:45'),
(61, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 13:17:49'),
(62, 1, 'logout', '{\"timestamp\":\"2025-09-21 21:19:29\"}', '::1', '2025-09-21 13:19:29'),
(63, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:19:34'),
(64, 8, 'match_request', '{\"match_id\":\"6\",\"mentor_id\":4,\"subject\":\"Filipino\"}', '::1', '2025-09-21 13:19:45'),
(65, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:19:51\"}', '::1', '2025-09-21 13:19:51'),
(66, 4, 'login', '{\"success\":true}', '::1', '2025-09-21 13:19:56'),
(67, 4, 'logout', '{\"timestamp\":\"2025-09-21 21:23:30\"}', '::1', '2025-09-21 13:23:30'),
(68, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:23:35'),
(69, 8, 'match_request', '{\"match_id\":\"7\",\"mentor_id\":6,\"subject\":\"Filipino\"}', '::1', '2025-09-21 13:24:53'),
(70, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:25:51\"}', '::1', '2025-09-21 13:25:51'),
(71, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 13:25:55'),
(72, 1, 'logout', '{\"timestamp\":\"2025-09-21 21:33:36\"}', '::1', '2025-09-21 13:33:36'),
(73, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:33:41'),
(74, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:33:49\"}', '::1', '2025-09-21 13:33:49'),
(75, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 13:33:52'),
(76, 1, 'logout', '{\"timestamp\":\"2025-09-21 21:34:08\"}', '::1', '2025-09-21 13:34:08'),
(77, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:34:14'),
(78, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:34:37\"}', '::1', '2025-09-21 13:34:37'),
(79, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 13:34:41'),
(80, 1, 'logout', '{\"timestamp\":\"2025-09-21 21:37:16\"}', '::1', '2025-09-21 13:37:16'),
(81, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:37:22'),
(82, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:42:14\"}', '::1', '2025-09-21 13:42:14'),
(83, 3, 'login', '{\"success\":true}', '::1', '2025-09-21 13:42:34'),
(84, 3, 'match_request', '{\"match_id\":\"8\",\"mentor_id\":10,\"subject\":\"English\"}', '::1', '2025-09-21 13:42:41'),
(85, 3, 'logout', '{\"timestamp\":\"2025-09-21 21:43:20\"}', '::1', '2025-09-21 13:43:20'),
(86, 10, 'login', '{\"success\":true}', '::1', '2025-09-21 13:43:25'),
(87, 10, 'logout', '{\"timestamp\":\"2025-09-21 21:43:33\"}', '::1', '2025-09-21 13:43:33'),
(88, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 13:43:42'),
(89, 8, 'logout', '{\"timestamp\":\"2025-09-21 21:44:47\"}', '::1', '2025-09-21 13:44:47'),
(90, 10, 'login', '{\"success\":true}', '::1', '2025-09-21 13:44:53'),
(91, 10, 'logout', '{\"timestamp\":\"2025-09-21 21:45:14\"}', '::1', '2025-09-21 13:45:14'),
(92, 11, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-21 13:46:11'),
(93, 11, 'match_request', '{\"match_id\":\"9\",\"mentor_id\":9,\"subject\":\"English\"}', '::1', '2025-09-21 13:46:43'),
(94, 11, 'logout', '{\"timestamp\":\"2025-09-21 21:46:51\"}', '::1', '2025-09-21 13:46:51'),
(95, 12, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 13:47:22'),
(96, 12, 'match_request', '{\"match_id\":\"10\",\"mentor_id\":6,\"subject\":\"Science\"}', '::1', '2025-09-21 13:47:42'),
(97, 12, 'logout', '{\"timestamp\":\"2025-09-21 21:48:04\"}', '::1', '2025-09-21 13:48:04'),
(98, 9, 'login', '{\"success\":true}', '::1', '2025-09-21 13:48:17'),
(99, 9, 'logout', '{\"timestamp\":\"2025-09-21 21:51:17\"}', '::1', '2025-09-21 13:51:17'),
(100, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 13:51:20'),
(101, 1, 'logout', '{\"timestamp\":\"2025-09-21 21:52:05\"}', '::1', '2025-09-21 13:52:05'),
(102, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 14:10:03'),
(103, 8, 'match_request', '{\"match_id\":\"11\",\"mentor_id\":11,\"subject\":\"Filipino\"}', '::1', '2025-09-21 14:10:14'),
(104, 8, 'match_response', '{\"match_id\":11,\"response\":\"rejected\"}', '::1', '2025-09-21 14:10:53'),
(105, 8, 'match_response', '{\"match_id\":7,\"response\":\"accepted\"}', '::1', '2025-09-21 14:10:54'),
(106, 8, 'logout', '{\"timestamp\":\"2025-09-21 22:11:00\"}', '::1', '2025-09-21 14:11:00'),
(107, 11, 'login', '{\"success\":true}', '::1', '2025-09-21 14:11:06'),
(108, 11, 'logout', '{\"timestamp\":\"2025-09-21 22:11:13\"}', '::1', '2025-09-21 14:11:13'),
(109, 13, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 14:11:29'),
(110, 13, 'match_request', '{\"match_id\":\"12\",\"mentor_id\":6,\"subject\":\"History\"}', '::1', '2025-09-21 14:11:51'),
(111, 13, 'logout', '{\"timestamp\":\"2025-09-21 22:12:00\"}', '::1', '2025-09-21 14:12:00'),
(112, 13, 'login', '{\"success\":true}', '::1', '2025-09-21 14:12:09'),
(113, 13, 'logout', '{\"timestamp\":\"2025-09-21 22:12:13\"}', '::1', '2025-09-21 14:12:13'),
(114, 6, 'login', '{\"success\":true}', '::1', '2025-09-21 14:12:26'),
(115, 14, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 14:15:25'),
(116, 14, 'match_request', '{\"match_id\":\"13\",\"mentor_id\":6,\"subject\":\"English\"}', '::1', '2025-09-21 14:16:02'),
(117, 6, 'match_response', '{\"match_id\":13,\"response\":\"accepted\"}', '::1', '2025-09-21 14:16:18'),
(118, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:16:38'),
(119, 6, 'message_sent', '{\"match_id\":13,\"partner_id\":14}', '::1', '2025-09-21 14:16:50'),
(120, 6, 'logout', '{\"timestamp\":\"2025-09-21 22:17:19\"}', '::1', '2025-09-21 14:17:19'),
(121, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 14:17:22'),
(122, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:18:48'),
(123, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:18:56'),
(124, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:23:10'),
(125, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:26:11'),
(126, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:26:38'),
(127, 14, 'message_sent', '{\"match_id\":13,\"partner_id\":6}', '::1', '2025-09-21 14:27:09'),
(128, 1, 'logout', '{\"timestamp\":\"2025-09-21 22:27:22\"}', '::1', '2025-09-21 14:27:22'),
(129, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 14:27:28'),
(130, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:27:39'),
(131, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:32:21'),
(132, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:32:26'),
(133, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:32:32'),
(134, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:32:34'),
(135, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:32:41'),
(136, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:33:30'),
(137, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:33:31'),
(138, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:33:33'),
(139, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:34:03'),
(140, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:34:06'),
(141, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:35:18'),
(142, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:35:19'),
(143, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 14:35:32'),
(144, 8, 'logout', '{\"timestamp\":\"2025-09-21 22:35:35\"}', '::1', '2025-09-21 14:35:35'),
(145, 6, 'login', '{\"success\":true}', '::1', '2025-09-21 14:35:39'),
(146, 6, 'message_sent', '{\"match_id\":7,\"partner_id\":8}', '::1', '2025-09-21 14:35:48'),
(147, 6, 'logout', '{\"timestamp\":\"2025-09-21 22:35:51\"}', '::1', '2025-09-21 14:35:51'),
(148, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 14:35:56'),
(149, 1, 'logout', '{\"timestamp\":\"2025-09-21 22:37:00\"}', '::1', '2025-09-21 14:37:00'),
(150, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 14:37:07'),
(151, 8, 'logout', '{\"timestamp\":\"2025-09-21 22:37:36\"}', '::1', '2025-09-21 14:37:36'),
(152, 15, 'register', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORB14814\",\"referral_code_id\":3,\"referred_by\":8}', '::1', '2025-09-21 14:37:59'),
(153, 15, 'logout', '{\"timestamp\":\"2025-09-21 22:38:33\"}', '::1', '2025-09-21 14:38:33'),
(154, 15, 'login', '{\"success\":true}', '::1', '2025-09-21 14:38:45'),
(155, 15, 'logout', '{\"timestamp\":\"2025-09-21 22:38:56\"}', '::1', '2025-09-21 14:38:56'),
(156, 16, 'register', '{\"role\":\"student\",\"referral_used\":true,\"referral_code\":\"MENTORD8A550\",\"referral_code_id\":4,\"referred_by\":15}', '::1', '2025-09-21 14:39:55'),
(157, 16, 'match_request', '{\"match_id\":\"14\",\"mentor_id\":8,\"subject\":\"Filipino\"}', '::1', '2025-09-21 14:40:34'),
(158, 16, 'logout', '{\"timestamp\":\"2025-09-21 22:45:54\"}', '::1', '2025-09-21 14:45:54'),
(159, 6, 'login', '{\"success\":true}', '::1', '2025-09-21 14:46:00'),
(160, 6, 'session_scheduled', '{\"match_id\":13,\"date\":\"2025-09-21\"}', '::1', '2025-09-21 14:46:46'),
(161, 6, 'logout', '{\"timestamp\":\"2025-09-21 22:50:03\"}', '::1', '2025-09-21 14:50:03'),
(162, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 14:50:06'),
(163, 1, 'logout', '{\"timestamp\":\"2025-09-21 23:01:45\"}', '::1', '2025-09-21 15:01:45'),
(164, 17, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 15:02:26'),
(165, 17, 'logout', '{\"timestamp\":\"2025-09-21 23:03:46\"}', '::1', '2025-09-21 15:03:46'),
(166, 18, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-21 15:03:59'),
(167, 18, 'logout', '{\"timestamp\":\"2025-09-21 23:06:36\"}', '::1', '2025-09-21 15:06:36'),
(168, 19, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 15:07:43'),
(169, 19, 'logout', '{\"timestamp\":\"2025-09-21 23:10:14\"}', '::1', '2025-09-21 15:10:14'),
(170, 19, 'login', '{\"success\":true}', '::1', '2025-09-21 15:10:20'),
(171, 19, 'logout', '{\"timestamp\":\"2025-09-21 23:25:26\"}', '::1', '2025-09-21 15:25:26'),
(172, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 15:25:33'),
(173, 8, 'logout', '{\"timestamp\":\"2025-09-21 23:33:32\"}', '::1', '2025-09-21 15:33:32'),
(174, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 15:33:36'),
(175, 1, 'logout', '{\"timestamp\":\"2025-09-21 23:36:12\"}', '::1', '2025-09-21 15:36:12'),
(176, 6, 'login', '{\"success\":true}', '::1', '2025-09-21 15:36:19'),
(177, 6, 'logout', '{\"timestamp\":\"2025-09-21 23:36:27\"}', '::1', '2025-09-21 15:36:27'),
(178, 3, 'login', '{\"success\":true}', '::1', '2025-09-21 15:36:55'),
(179, 3, 'session_completed', '{\"session_id\":2}', '::1', '2025-09-21 16:04:06'),
(180, 3, 'session_rated', '{\"session_id\":2,\"rating\":4}', '::1', '2025-09-21 16:04:33'),
(181, 3, 'session_completed', '{\"session_id\":1}', '::1', '2025-09-21 16:04:42'),
(182, 3, 'session_rated', '{\"session_id\":1,\"rating\":5}', '::1', '2025-09-21 16:04:51'),
(183, 3, 'logout', '{\"timestamp\":\"2025-09-22 00:05:09\"}', '::1', '2025-09-21 16:05:09'),
(184, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 16:05:12'),
(185, 1, 'logout', '{\"timestamp\":\"2025-09-22 00:09:41\"}', '::1', '2025-09-21 16:09:41'),
(186, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 16:09:48'),
(187, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 17:26:52'),
(188, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 17:26:53'),
(189, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 17:26:55'),
(190, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 17:26:59'),
(191, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 17:27:02'),
(192, 8, 'message_sent', '{\"match_id\":7,\"partner_id\":6}', '::1', '2025-09-21 17:27:05'),
(193, 8, 'logout', '{\"timestamp\":\"2025-09-22 01:34:10\"}', '::1', '2025-09-21 17:34:10'),
(194, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 17:34:12'),
(195, 1, 'logout', '{\"timestamp\":\"2025-09-22 01:43:51\"}', '::1', '2025-09-21 17:43:51'),
(196, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 17:43:57'),
(197, 8, 'match_response', '{\"match_id\":14,\"response\":\"accepted\"}', '::1', '2025-09-21 17:44:06'),
(198, 8, 'session_scheduled', '{\"match_id\":14,\"date\":\"2025-09-22\"}', '::1', '2025-09-21 17:46:42'),
(199, 8, 'session_completed', '{\"session_id\":4}', '::1', '2025-09-21 17:46:59'),
(200, 8, 'session_rated', '{\"session_id\":4,\"rating\":5}', '::1', '2025-09-21 17:47:08'),
(201, 8, 'match_request', '{\"match_id\":\"15\",\"mentor_id\":11,\"subject\":\"Filipino\"}', '::1', '2025-09-21 17:47:19'),
(202, 8, 'match_response', '{\"match_id\":15,\"response\":\"accepted\"}', '::1', '2025-09-21 17:47:41'),
(203, 8, 'session_scheduled', '{\"match_id\":15,\"date\":\"2025-09-22\"}', '::1', '2025-09-21 17:48:08'),
(204, 8, 'session_completed', '{\"session_id\":5}', '::1', '2025-09-21 17:49:08'),
(205, 8, 'session_rated', '{\"session_id\":5,\"rating\":5}', '::1', '2025-09-21 17:49:15'),
(206, 8, 'match_request', '{\"match_id\":\"16\",\"mentor_id\":3,\"subject\":\"Filipino\"}', '::1', '2025-09-21 17:49:25'),
(207, 8, 'match_response', '{\"match_id\":16,\"response\":\"accepted\"}', '::1', '2025-09-21 17:49:48'),
(208, 8, 'message_sent', '{\"match_id\":16,\"partner_id\":3}', '::1', '2025-09-21 17:52:24'),
(209, 8, 'message_sent', '{\"match_id\":16,\"partner_id\":3}', '::1', '2025-09-21 17:52:26'),
(210, 8, 'message_sent', '{\"match_id\":15,\"partner_id\":11}', '::1', '2025-09-21 17:52:35'),
(211, 8, 'message_sent', '{\"match_id\":14,\"partner_id\":16}', '::1', '2025-09-21 17:52:44'),
(212, 8, 'logout', '{\"timestamp\":\"2025-09-22 02:01:03\"}', '::1', '2025-09-21 18:01:03'),
(213, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 18:01:05'),
(214, 1, 'logout', '{\"timestamp\":\"2025-09-22 02:55:47\"}', '::1', '2025-09-21 18:55:47'),
(215, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 18:55:55'),
(216, 1, 'logout', '{\"timestamp\":\"2025-09-22 02:56:59\"}', '::1', '2025-09-21 18:56:59'),
(217, 20, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-21 18:57:22'),
(218, 8, 'login', '{\"success\":true}', '::1', '2025-09-21 19:03:45'),
(219, 8, 'match_request', '{\"match_id\":\"17\",\"mentor_id\":18,\"subject\":\"Filipino\"}', '::1', '2025-09-21 19:15:44'),
(220, 8, 'match_request', '{\"match_id\":\"18\",\"mentor_id\":18,\"subject\":\"Filipino\"}', '::1', '2025-09-21 19:19:56'),
(221, 8, 'match_request', '{\"match_id\":\"19\",\"mentor_id\":18,\"subject\":\"Filipino\"}', '::1', '2025-09-21 19:20:15'),
(222, 8, 'match_request', '{\"match_id\":\"20\",\"mentor_id\":18,\"subject\":\"Filipino\"}', '::1', '2025-09-21 19:20:24'),
(223, 8, 'session_scheduled', '{\"match_id\":14,\"date\":\"2025-09-22\"}', '::1', '2025-09-21 21:03:06'),
(224, 8, 'session_scheduled', '{\"match_id\":14,\"date\":\"2025-09-23\"}', '::1', '2025-09-21 21:03:30'),
(225, 8, 'logout', '{\"timestamp\":\"2025-09-22 05:08:18\"}', '::1', '2025-09-21 21:08:18'),
(226, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-21 21:15:34'),
(227, NULL, 'logout', '{\"timestamp\":\"2025-09-22 05:44:24\"}', '::1', '2025-09-21 21:44:24'),
(228, 1, 'login', '{\"success\":true}', '::1', '2025-09-21 21:44:26'),
(229, 1, 'logout', '{\"timestamp\":\"2025-09-22 10:23:00\"}', '::1', '2025-09-22 02:23:00'),
(230, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 02:23:06'),
(231, 1, 'logout', '{\"timestamp\":\"2025-09-22 10:24:45\"}', '::1', '2025-09-22 02:24:45'),
(232, NULL, 'login', '{\"success\":true}', '::1', '2025-09-22 02:25:09'),
(233, NULL, 'logout', '{\"timestamp\":\"2025-09-22 10:28:05\"}', '::1', '2025-09-22 02:28:05'),
(234, 18, 'login', '{\"success\":true}', '::1', '2025-09-22 02:28:18'),
(235, 18, 'logout', '{\"timestamp\":\"2025-09-22 10:28:31\"}', '::1', '2025-09-22 02:28:31'),
(236, NULL, 'login_failed', '{\"reason\":\"wrong_password\"}', '::1', '2025-09-22 02:28:39'),
(237, NULL, 'login', '{\"success\":true}', '::1', '2025-09-22 02:28:44'),
(238, NULL, 'logout', '{\"timestamp\":\"2025-09-22 10:28:59\"}', '::1', '2025-09-22 02:28:59'),
(239, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":true,\"referral_code\":\"MENTOR0008\",\"referral_code_id\":1,\"referred_by\":8}', '::1', '2025-09-22 02:30:31'),
(240, NULL, 'logout', '{\"timestamp\":\"2025-09-22 10:45:19\"}', '::1', '2025-09-22 02:45:19'),
(241, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 02:45:29'),
(242, 1, 'logout', '{\"timestamp\":\"2025-09-22 10:46:01\"}', '::1', '2025-09-22 02:46:01'),
(243, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-22 02:46:32'),
(244, NULL, 'logout', '{\"timestamp\":\"2025-09-22 10:47:01\"}', '::1', '2025-09-22 02:47:01'),
(245, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 03:02:11'),
(246, 1, 'logout', '{\"timestamp\":\"2025-09-22 11:02:39\"}', '::1', '2025-09-22 03:02:39'),
(247, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":true,\"referral_code\":\"MENTOR0008\",\"referral_code_id\":1,\"referred_by\":8}', '::1', '2025-09-22 03:03:11'),
(248, NULL, 'logout', '{\"timestamp\":\"2025-09-22 11:03:35\"}', '::1', '2025-09-22 03:03:35'),
(249, 3, 'login', '{\"success\":true}', '::1', '2025-09-22 03:03:50'),
(250, 3, 'logout', '{\"timestamp\":\"2025-09-22 11:04:33\"}', '::1', '2025-09-22 03:04:33'),
(251, NULL, 'login', '{\"success\":true}', '::1', '2025-09-22 03:04:40'),
(252, NULL, 'logout', '{\"timestamp\":\"2025-09-22 11:15:49\"}', '::1', '2025-09-22 03:15:49'),
(253, 7, 'login', '{\"success\":true}', '::1', '2025-09-22 03:16:20'),
(254, 7, 'logout', '{\"timestamp\":\"2025-09-22 11:16:38\"}', '::1', '2025-09-22 03:16:38'),
(255, NULL, 'login', '{\"success\":true}', '::1', '2025-09-22 03:16:44'),
(256, NULL, 'match_request', '{\"match_id\":\"21\",\"mentor_id\":4,\"subject\":\"Filipino\"}', '::1', '2025-09-22 03:17:49'),
(257, NULL, 'logout', '{\"timestamp\":\"2025-09-22 11:18:16\"}', '::1', '2025-09-22 03:18:16'),
(258, 4, 'login', '{\"success\":true}', '::1', '2025-09-22 03:18:23'),
(259, 4, 'match_request', '{\"match_id\":\"22\",\"mentor_id\":14,\"subject\":\"Filipino\"}', '::1', '2025-09-22 03:19:12'),
(260, 4, 'logout', '{\"timestamp\":\"2025-09-22 11:20:26\"}', '::1', '2025-09-22 03:20:26'),
(261, NULL, 'login', '{\"success\":true}', '::1', '2025-09-22 03:20:52'),
(262, NULL, 'logout', '{\"timestamp\":\"2025-09-22 11:21:26\"}', '::1', '2025-09-22 03:21:26'),
(263, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 03:21:49'),
(264, 1, 'logout', '{\"timestamp\":\"2025-09-22 11:23:53\"}', '::1', '2025-09-22 03:23:53'),
(265, 8, 'login', '{\"success\":true}', '::1', '2025-09-22 03:24:01'),
(266, 8, 'logout', '{\"timestamp\":\"2025-09-22 11:32:46\"}', '::1', '2025-09-22 03:32:46'),
(267, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-22 03:33:05'),
(268, NULL, 'match_request', '{\"match_id\":\"23\",\"mentor_id\":11,\"subject\":\"Filipino\"}', '::1', '2025-09-22 03:33:41'),
(269, NULL, 'logout', '{\"timestamp\":\"2025-09-22 11:38:54\"}', '::1', '2025-09-22 03:38:54'),
(270, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 03:38:56'),
(271, 1, 'logout', '{\"timestamp\":\"2025-09-22 11:48:04\"}', '::1', '2025-09-22 03:48:04'),
(272, 8, 'login', '{\"success\":true}', '::1', '2025-09-22 03:48:12'),
(273, 8, 'match_response', '{\"match_id\":20,\"response\":\"accepted\"}', '::1', '2025-09-22 03:52:58'),
(274, 4, 'login', '{\"success\":true}', '::1', '2025-09-22 05:18:54'),
(275, 4, 'match_request', '{\"match_id\":\"24\",\"mentor_id\":20,\"subject\":\"English\"}', '::1', '2025-09-22 05:19:08'),
(276, 4, 'logout', '{\"timestamp\":\"2025-09-22 13:19:38\"}', '::1', '2025-09-22 05:19:38'),
(277, 20, 'login', '{\"success\":true}', '::1', '2025-09-22 05:19:42'),
(278, 20, 'match_response', '{\"match_id\":24,\"response\":\"accepted\"}', '::1', '2025-09-22 05:19:50'),
(279, 20, 'message_sent', '{\"match_id\":24,\"partner_id\":4}', '::1', '2025-09-22 05:19:57'),
(280, 20, 'logout', '{\"timestamp\":\"2025-09-22 13:21:09\"}', '::1', '2025-09-22 05:21:09'),
(281, 4, 'login', '{\"success\":true}', '::1', '2025-09-22 05:21:14'),
(282, 4, 'logout', '{\"timestamp\":\"2025-09-22 13:21:43\"}', '::1', '2025-09-22 05:21:43'),
(283, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 05:21:45'),
(284, 1, 'logout', '{\"timestamp\":\"2025-09-22 13:21:56\"}', '::1', '2025-09-22 05:21:56'),
(285, 26, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-22 05:22:26'),
(286, 26, 'logout', '{\"timestamp\":\"2025-09-22 13:39:15\"}', '::1', '2025-09-22 05:39:15'),
(287, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 05:39:19'),
(288, 1, 'logout', '{\"timestamp\":\"2025-09-22 13:45:04\"}', '::1', '2025-09-22 05:45:04'),
(289, 8, 'login', '{\"success\":true}', '::1', '2025-09-22 05:45:46'),
(290, 8, 'logout', '{\"timestamp\":\"2025-09-22 14:13:46\"}', '::1', '2025-09-22 06:13:46'),
(291, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 06:19:49'),
(292, 1, 'logout', '{\"timestamp\":\"2025-09-22 14:38:36\"}', '::1', '2025-09-22 06:38:36'),
(293, 4, 'login', '{\"success\":true}', '::1', '2025-09-22 06:38:46'),
(294, 4, 'logout', '{\"timestamp\":\"2025-09-22 14:47:38\"}', '::1', '2025-09-22 06:47:38'),
(295, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 09:37:28'),
(296, 1, 'logout', '{\"timestamp\":\"2025-09-22 17:37:43\"}', '::1', '2025-09-22 09:37:43'),
(297, 8, 'login', '{\"success\":true}', '::1', '2025-09-22 09:38:10'),
(298, 8, 'logout', '{\"timestamp\":\"2025-09-22 17:42:35\"}', '::1', '2025-09-22 09:42:35'),
(299, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 09:42:37'),
(300, 1, 'admin_deactivate_user', '{\"deactivated_user_id\":26}', '::1', '2025-09-22 09:57:41'),
(301, 1, 'admin_activate_user', '{\"activated_user_id\":26}', '::1', '2025-09-22 09:57:53'),
(302, 1, 'logout', '{\"timestamp\":\"2025-09-22 17:59:12\"}', '::1', '2025-09-22 09:59:12'),
(303, 8, 'login', '{\"success\":true}', '::1', '2025-09-22 09:59:19'),
(304, 8, 'session_completed', '{\"session_id\":6}', '::1', '2025-09-22 09:59:30'),
(305, 8, 'session_rated', '{\"session_id\":6,\"rating\":5}', '::1', '2025-09-22 09:59:43'),
(306, 8, 'logout', '{\"timestamp\":\"2025-09-22 18:00:06\"}', '::1', '2025-09-22 10:00:06'),
(307, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 10:00:08'),
(308, 1, 'logout', '{\"timestamp\":\"2025-09-22 18:52:05\"}', '::1', '2025-09-22 10:52:05'),
(309, 1, 'login', '{\"success\":true}', '::1', '2025-09-22 11:04:08'),
(310, 8, 'login', '{\"success\":true}', '::1', '2025-09-22 14:36:48'),
(311, 8, 'logout', '{\"timestamp\":\"2025-09-22 22:45:45\"}', '::1', '2025-09-22 14:45:45'),
(312, 27, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-22 14:45:57'),
(313, 27, 'logout', '{\"timestamp\":\"2025-09-23 15:33:44\"}', '::1', '2025-09-23 07:33:44'),
(314, NULL, 'register', '{\"role\":\"peer_tutor\",\"referral_used\":false}', '::1', '2025-09-23 07:34:06'),
(315, NULL, 'logout', '{\"timestamp\":\"2025-09-23 15:34:22\"}', '::1', '2025-09-23 07:34:22'),
(316, NULL, 'register', '{\"role\":\"peer_tutor\",\"referral_used\":false}', '::1', '2025-09-23 07:36:19'),
(317, NULL, 'logout', '{\"timestamp\":\"2025-09-23 15:38:28\"}', '::1', '2025-09-23 07:38:28'),
(318, NULL, 'login', '{\"success\":true}', '::1', '2025-09-23 07:38:39'),
(319, NULL, 'logout', '{\"timestamp\":\"2025-09-23 15:42:02\"}', '::1', '2025-09-23 07:42:02'),
(320, NULL, 'register', '{\"role\":\"peer_tutor\",\"referral_used\":false}', '::1', '2025-09-23 07:42:17'),
(321, NULL, 'logout', '{\"timestamp\":\"2025-09-23 15:43:38\"}', '::1', '2025-09-23 07:43:38'),
(322, 1, 'login', '{\"success\":true}', '::1', '2025-09-23 07:43:42'),
(323, 1, 'logout', '{\"timestamp\":\"2025-09-23 15:43:52\"}', '::1', '2025-09-23 07:43:52'),
(324, 32, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-23 08:02:54'),
(325, 32, 'logout', '{\"timestamp\":\"2025-09-23 16:03:40\"}', '::1', '2025-09-23 08:03:40'),
(326, 32, 'login', '{\"success\":true}', '::1', '2025-09-23 08:03:45'),
(327, 32, 'logout', '{\"timestamp\":\"2025-09-23 16:04:03\"}', '::1', '2025-09-23 08:04:03'),
(328, 33, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-23 08:04:17'),
(329, 33, 'logout', '{\"timestamp\":\"2025-09-23 16:08:35\"}', '::1', '2025-09-23 08:08:35'),
(330, NULL, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-23 08:08:48'),
(331, NULL, 'logout', '{\"timestamp\":\"2025-09-23 16:09:15\"}', '::1', '2025-09-23 08:09:15'),
(332, 35, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-23 08:09:25'),
(333, 35, 'logout', '{\"timestamp\":\"2025-09-23 16:30:14\"}', '::1', '2025-09-23 08:30:14'),
(334, NULL, 'register', '{\"role\":\"peer_tutor\",\"referral_used\":false}', '::1', '2025-09-23 08:30:29'),
(335, NULL, 'logout', '{\"timestamp\":\"2025-09-23 16:31:52\"}', '::1', '2025-09-23 08:31:52'),
(336, NULL, 'login', '{\"success\":true}', '::1', '2025-09-23 08:32:02'),
(337, NULL, 'logout', '{\"timestamp\":\"2025-09-23 18:19:58\"}', '::1', '2025-09-23 10:19:58'),
(338, 1, 'login', '{\"success\":true}', '::1', '2025-09-23 10:20:01'),
(339, 1, 'logout', '{\"timestamp\":\"2025-09-23 18:30:06\"}', '::1', '2025-09-23 10:30:06'),
(340, 8, 'login', '{\"success\":true}', '::1', '2025-09-23 10:30:11'),
(341, 8, 'logout', '{\"timestamp\":\"2025-09-23 18:35:18\"}', '::1', '2025-09-23 10:35:18'),
(342, NULL, 'login', '{\"success\":true}', '::1', '2025-09-23 10:35:37'),
(343, NULL, 'match_request', '{\"match_id\":\"25\",\"mentor_id\":6,\"subject\":\"English\"}', '::1', '2025-09-23 10:50:43'),
(344, NULL, 'match_request', '{\"match_id\":\"26\",\"mentor_id\":4,\"subject\":\"English\"}', '::1', '2025-09-23 10:50:52'),
(345, NULL, 'match_request', '{\"match_id\":\"27\",\"mentor_id\":11,\"subject\":\"English\"}', '::1', '2025-09-23 10:51:12'),
(346, NULL, 'logout', '{\"timestamp\":\"2025-09-23 18:51:53\"}', '::1', '2025-09-23 10:51:53'),
(347, 3, 'login', '{\"success\":true}', '::1', '2025-09-23 10:51:58'),
(348, 3, 'match_request', '{\"match_id\":\"28\",\"mentor_id\":15,\"subject\":\"English\"}', '::1', '2025-09-23 10:52:10'),
(349, 3, 'match_response', '{\"match_id\":28,\"response\":\"accepted\"}', '::1', '2025-09-23 10:52:15'),
(350, 3, 'message_sent', '{\"match_id\":16,\"partner_id\":8}', '::1', '2025-09-23 10:55:51'),
(351, 3, 'match_request', '{\"match_id\":\"29\",\"mentor_id\":33,\"subject\":\"\"}', '::1', '2025-09-23 11:07:41'),
(352, 3, 'match_request', '{\"match_id\":\"30\",\"mentor_id\":14,\"subject\":\"\"}', '::1', '2025-09-23 11:08:00'),
(353, 3, 'match_request', '{\"match_id\":\"31\",\"mentor_id\":5,\"subject\":\"\"}', '::1', '2025-09-23 11:09:21'),
(354, 3, 'match_response', '{\"match_id\":31,\"response\":\"accepted\"}', '::1', '2025-09-23 11:09:31'),
(355, 3, 'logout', '{\"timestamp\":\"2025-09-23 20:10:21\"}', '::1', '2025-09-23 12:10:21'),
(356, 1, 'login', '{\"success\":true}', '::1', '2025-09-23 12:10:27'),
(357, 1, 'logout', '{\"timestamp\":\"2025-09-23 20:28:57\"}', '::1', '2025-09-23 12:28:57'),
(358, 37, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-23 12:29:30'),
(359, 38, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-24 02:42:59'),
(360, 38, 'logout', '{\"timestamp\":\"2025-09-24 11:59:09\"}', '::1', '2025-09-24 03:59:09'),
(361, 39, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-24 04:02:14'),
(362, 39, 'logout', '{\"timestamp\":\"2025-09-24 12:16:27\"}', '::1', '2025-09-24 04:16:27'),
(363, 40, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-24 04:16:54'),
(364, 40, 'logout', '{\"timestamp\":\"2025-09-24 12:19:57\"}', '::1', '2025-09-24 04:19:57'),
(365, 41, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-24 04:20:13'),
(366, 41, 'logout', '{\"timestamp\":\"2025-09-24 12:44:11\"}', '::1', '2025-09-24 04:44:11'),
(367, 40, 'login', '{\"success\":true}', '::1', '2025-09-24 04:44:18'),
(368, 40, 'logout', '{\"timestamp\":\"2025-09-24 13:18:03\"}', '::1', '2025-09-24 05:18:03'),
(369, 1, 'login', '{\"success\":true}', '::1', '2025-09-24 05:18:09'),
(370, 1, 'logout', '{\"timestamp\":\"2025-09-24 13:41:31\"}', '::1', '2025-09-24 05:41:31'),
(371, 40, 'login', '{\"success\":true}', '::1', '2025-09-24 05:41:38'),
(372, 40, 'match_request', '{\"match_id\":\"32\",\"mentor_id\":10,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-24 05:41:48'),
(373, 40, 'match_request_pending', '{\"match_id\":\"32\",\"mentor_id\":10,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 05:41:48'),
(374, 40, 'logout', '{\"timestamp\":\"2025-09-24 13:42:14\"}', '::1', '2025-09-24 05:42:14'),
(375, 4, 'login', '{\"success\":true}', '::1', '2025-09-24 05:42:33'),
(376, 4, 'match_response', '{\"match_id\":22,\"response\":\"accepted\"}', '::1', '2025-09-24 05:42:39'),
(377, 4, 'match_request', '{\"match_id\":\"33\",\"mentor_id\":40,\"subject\":\"English\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-09-24 05:42:52'),
(378, 4, 'match_request_pending', '{\"match_id\":\"33\",\"mentor_id\":40,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 05:42:52'),
(379, 4, 'logout', '{\"timestamp\":\"2025-09-24 13:42:58\"}', '::1', '2025-09-24 05:42:58'),
(380, 40, 'login', '{\"success\":true}', '::1', '2025-09-24 05:43:08'),
(381, 40, 'match_request', '{\"match_id\":\"34\",\"mentor_id\":5,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-24 05:44:00'),
(382, 40, 'match_request_pending', '{\"match_id\":\"34\",\"mentor_id\":5,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 05:44:00'),
(383, 40, 'logout', '{\"timestamp\":\"2025-09-24 13:44:16\"}', '::1', '2025-09-24 05:44:16'),
(384, 7, 'login', '{\"success\":true}', '::1', '2025-09-24 05:44:34'),
(385, 7, 'message_sent', '{\"match_id\":4,\"partner_id\":6}', '::1', '2025-09-24 05:44:54'),
(386, 7, 'match_request', '{\"match_id\":\"35\",\"mentor_id\":40,\"subject\":\"History\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-09-24 05:45:08'),
(387, 7, 'match_request_pending', '{\"match_id\":\"35\",\"mentor_id\":40,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 05:45:08'),
(388, 7, 'logout', '{\"timestamp\":\"2025-09-24 13:45:15\"}', '::1', '2025-09-24 05:45:15'),
(389, 40, 'login', '{\"success\":true}', '::1', '2025-09-24 05:45:20'),
(390, 40, 'match_request', '{\"match_id\":\"36\",\"mentor_id\":15,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-24 05:48:27'),
(391, 40, 'match_request_pending', '{\"match_id\":\"36\",\"mentor_id\":15,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 05:48:27'),
(392, 40, 'logout', '{\"timestamp\":\"2025-09-24 13:48:39\"}', '::1', '2025-09-24 05:48:39'),
(393, 40, 'login', '{\"success\":true}', '::1', '2025-09-24 05:48:45'),
(394, 40, 'logout', '{\"timestamp\":\"2025-09-24 13:48:47\"}', '::1', '2025-09-24 05:48:47'),
(395, 15, 'login', '{\"success\":true}', '::1', '2025-09-24 05:48:53'),
(396, 15, 'match_response', '{\"match_id\":36,\"response\":\"accepted\"}', '::1', '2025-09-24 05:49:11'),
(397, 15, 'message_sent', '{\"match_id\":36,\"partner_id\":40}', '::1', '2025-09-24 05:49:15'),
(398, 15, 'session_scheduled', '{\"match_id\":36,\"date\":\"2025-09-24\"}', '::1', '2025-09-24 05:49:30'),
(399, 15, 'session_scheduled', '{\"match_id\":28,\"date\":\"2025-09-24\"}', '::1', '2025-09-24 05:50:45'),
(400, 15, 'session_cancelled', '{\"session_id\":9}', '::1', '2025-09-24 05:51:22'),
(401, 15, 'session_completed', '{\"session_id\":8}', '::1', '2025-09-24 05:52:06'),
(402, 15, 'session_rated', '{\"session_id\":8,\"rating\":4}', '::1', '2025-09-24 05:52:24'),
(403, 15, 'logout', '{\"timestamp\":\"2025-09-24 13:52:37\"}', '::1', '2025-09-24 05:52:37'),
(404, 1, 'login', '{\"success\":true}', '::1', '2025-09-24 05:52:38'),
(405, 1, 'logout', '{\"timestamp\":\"2025-09-24 13:53:02\"}', '::1', '2025-09-24 05:53:02'),
(406, 42, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-24 05:53:40'),
(407, 42, 'logout', '{\"timestamp\":\"2025-09-24 13:59:21\"}', '::1', '2025-09-24 05:59:21'),
(408, 1, 'login', '{\"success\":true}', '::1', '2025-09-24 05:59:25'),
(409, 1, 'logout', '{\"timestamp\":\"2025-09-24 14:04:41\"}', '::1', '2025-09-24 06:04:41'),
(410, 1, 'login', '{\"success\":true}', '::1', '2025-09-24 06:06:33'),
(411, 1, 'logout', '{\"timestamp\":\"2025-09-24 15:00:50\"}', '::1', '2025-09-24 07:00:50'),
(412, 43, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-24 07:01:11'),
(413, 43, 'match_request', '{\"match_id\":\"37\",\"mentor_id\":40,\"subject\":\"Geography\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-09-24 07:12:48'),
(414, 43, 'match_request_pending', '{\"match_id\":\"37\",\"mentor_id\":40,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 07:12:48'),
(415, 43, 'logout', '{\"timestamp\":\"2025-09-24 15:12:50\"}', '::1', '2025-09-24 07:12:50'),
(416, 40, 'login', '{\"success\":true}', '::1', '2025-09-24 07:12:59'),
(417, 40, 'match_response', '{\"match_id\":37,\"response\":\"accepted\"}', '::1', '2025-09-24 07:13:06'),
(418, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-09-24 07:14:04'),
(419, 40, 'logout', '{\"timestamp\":\"2025-09-24 15:23:14\"}', '::1', '2025-09-24 07:23:14'),
(420, 44, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-24 07:23:50'),
(421, 44, 'logout', '{\"timestamp\":\"2025-09-24 15:27:37\"}', '::1', '2025-09-24 07:27:37'),
(422, 45, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-24 07:27:54'),
(423, 45, 'match_request', '{\"match_id\":\"38\",\"mentor_id\":44,\"subject\":\"Geography\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-09-24 07:35:06'),
(424, 45, 'match_request_pending', '{\"match_id\":\"38\",\"mentor_id\":44,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 07:35:06'),
(425, 45, 'logout', '{\"timestamp\":\"2025-09-24 15:43:06\"}', '::1', '2025-09-24 07:43:06'),
(426, 46, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-24 07:43:25'),
(427, 46, 'match_request', '{\"match_id\":\"39\",\"mentor_id\":4,\"subject\":\"Filipino\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-09-24 07:48:21'),
(428, 46, 'match_request_pending', '{\"match_id\":\"39\",\"mentor_id\":4,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 07:48:21'),
(429, 46, 'logout', '{\"timestamp\":\"2025-09-24 16:02:54\"}', '::1', '2025-09-24 08:02:54'),
(430, 47, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-24 08:03:17'),
(431, 47, 'logout', '{\"timestamp\":\"2025-09-24 16:10:31\"}', '::1', '2025-09-24 08:10:31'),
(432, 48, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-24 08:10:53'),
(433, 48, 'logout', '{\"timestamp\":\"2025-09-24 16:21:30\"}', '::1', '2025-09-24 08:21:30'),
(434, 49, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-24 08:21:51'),
(435, 49, 'logout', '{\"timestamp\":\"2025-09-24 16:46:19\"}', '::1', '2025-09-24 08:46:19'),
(436, 1, 'login', '{\"success\":true}', '::1', '2025-09-24 08:46:21'),
(437, 1, 'logout', '{\"timestamp\":\"2025-09-24 17:01:21\"}', '::1', '2025-09-24 09:01:21'),
(438, 50, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-24 09:07:42'),
(439, 50, 'match_request', '{\"match_id\":\"40\",\"mentor_id\":44,\"subject\":\"History\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-09-24 09:09:28'),
(440, 50, 'match_request_pending', '{\"match_id\":\"40\",\"mentor_id\":44,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 09:09:28'),
(441, 50, 'logout', '{\"timestamp\":\"2025-09-24 17:10:50\"}', '::1', '2025-09-24 09:10:50'),
(442, 51, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-24 09:21:00'),
(443, 51, 'match_request', '{\"match_id\":\"41\",\"mentor_id\":44,\"subject\":\"Programming\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-24 09:28:57'),
(444, 51, 'match_request_pending', '{\"match_id\":\"41\",\"mentor_id\":44,\"delivery_method\":\"pending\"}', '::1', '2025-09-24 09:28:57'),
(445, 51, 'logout', '{\"timestamp\":\"2025-09-24 17:29:02\"}', '::1', '2025-09-24 09:29:02'),
(446, 44, 'login', '{\"success\":true}', '::1', '2025-09-24 09:29:07'),
(447, 44, 'logout', '{\"timestamp\":\"2025-09-24 17:33:10\"}', '::1', '2025-09-24 09:33:10'),
(448, 1, 'login', '{\"success\":true}', '::1', '2025-09-24 09:33:14'),
(449, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 00:48:21'),
(450, 1, 'logout', '{\"timestamp\":\"2025-09-25 08:49:19\"}', '::1', '2025-09-25 00:49:19'),
(451, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 00:49:57'),
(452, 40, 'match_request', '{\"match_id\":\"42\",\"mentor_id\":42,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-25 01:11:35'),
(453, 40, 'match_request_pending', '{\"match_id\":\"42\",\"mentor_id\":42,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:11:35'),
(454, 40, 'match_request', '{\"match_id\":\"43\",\"mentor_id\":13,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 01:11:49'),
(455, 40, 'match_request_pending', '{\"match_id\":\"43\",\"mentor_id\":13,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:11:49'),
(456, 40, 'match_request', '{\"match_id\":\"44\",\"mentor_id\":37,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-09-25 01:12:24'),
(457, 40, 'match_request_pending', '{\"match_id\":\"44\",\"mentor_id\":37,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:12:24'),
(458, 40, 'match_request', '{\"match_id\":\"45\",\"mentor_id\":33,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 01:13:26'),
(459, 40, 'match_request_pending', '{\"match_id\":\"45\",\"mentor_id\":33,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:13:26'),
(460, 40, 'match_request', '{\"match_id\":\"46\",\"mentor_id\":33,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 01:17:13'),
(461, 40, 'match_request_pending', '{\"match_id\":\"46\",\"mentor_id\":33,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:17:13'),
(462, 40, 'match_request', '{\"match_id\":\"47\",\"mentor_id\":44,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-25 01:17:25'),
(463, 40, 'match_request_pending', '{\"match_id\":\"47\",\"mentor_id\":44,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:17:25'),
(464, 40, 'match_request', '{\"match_id\":\"48\",\"mentor_id\":6,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-09-25 01:17:31'),
(465, 40, 'match_request_pending', '{\"match_id\":\"48\",\"mentor_id\":6,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:17:31'),
(466, 40, 'logout', '{\"timestamp\":\"2025-09-25 09:19:31\"}', '::1', '2025-09-25 01:19:31'),
(467, 44, 'login', '{\"success\":true}', '::1', '2025-09-25 01:19:37'),
(468, 44, 'match_response', '{\"match_id\":47,\"response\":\"accepted\"}', '::1', '2025-09-25 01:21:39'),
(469, 44, 'match_response', '{\"match_id\":41,\"response\":\"accepted\"}', '::1', '2025-09-25 01:21:43'),
(470, 44, 'match_response', '{\"match_id\":40,\"response\":\"accepted\"}', '::1', '2025-09-25 01:21:44'),
(471, 44, 'match_response', '{\"match_id\":38,\"response\":\"accepted\"}', '::1', '2025-09-25 01:21:45'),
(472, 44, 'match_response', '{\"match_id\":38,\"response\":\"accepted\"}', '::1', '2025-09-25 01:28:31'),
(473, 44, 'match_request', '{\"match_id\":\"49\",\"mentor_id\":12,\"subject\":\"Science\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 01:28:44'),
(474, 44, 'match_request_pending', '{\"match_id\":\"49\",\"mentor_id\":12,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:28:44'),
(475, 44, 'match_request', '{\"match_id\":\"50\",\"mentor_id\":12,\"subject\":\"Science\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 01:32:10'),
(476, 44, 'match_request_pending', '{\"match_id\":\"50\",\"mentor_id\":12,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:32:10'),
(477, 44, 'match_request', '{\"match_id\":\"51\",\"mentor_id\":42,\"subject\":\"Science\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-25 01:32:16'),
(478, 44, 'match_request_pending', '{\"match_id\":\"51\",\"mentor_id\":42,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:32:16'),
(479, 44, 'match_request', '{\"match_id\":\"52\",\"mentor_id\":13,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 01:32:34'),
(480, 44, 'match_request_pending', '{\"match_id\":\"52\",\"mentor_id\":13,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 01:32:34'),
(481, 44, 'logout', '{\"timestamp\":\"2025-09-25 09:32:41\"}', '::1', '2025-09-25 01:32:41'),
(482, 13, 'login', '{\"success\":true}', '::1', '2025-09-25 01:32:46'),
(483, 13, 'logout', '{\"timestamp\":\"2025-09-25 09:35:24\"}', '::1', '2025-09-25 01:35:24'),
(484, 4, 'login', '{\"success\":true}', '::1', '2025-09-25 01:35:42'),
(485, 4, 'logout', '{\"timestamp\":\"2025-09-25 09:38:07\"}', '::1', '2025-09-25 01:38:07'),
(486, 5, 'login', '{\"success\":true}', '::1', '2025-09-25 01:38:13'),
(487, 5, 'message_sent', '{\"match_id\":31,\"partner_id\":3}', '::1', '2025-09-25 01:46:54'),
(488, 5, 'logout', '{\"timestamp\":\"2025-09-25 09:53:49\"}', '::1', '2025-09-25 01:53:49'),
(489, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 01:53:55'),
(490, 40, 'logout', '{\"timestamp\":\"2025-09-25 11:41:10\"}', '::1', '2025-09-25 03:41:10'),
(491, 52, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-25 03:41:41'),
(492, 52, 'logout', '{\"timestamp\":\"2025-09-25 11:42:56\"}', '::1', '2025-09-25 03:42:56'),
(493, 53, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-25 03:43:14'),
(494, 53, 'logout', '{\"timestamp\":\"2025-09-25 13:36:17\"}', '::1', '2025-09-25 05:36:17'),
(495, 38, 'login', '{\"success\":true}', '::1', '2025-09-25 05:36:23'),
(496, 38, 'logout', '{\"timestamp\":\"2025-09-25 14:04:49\"}', '::1', '2025-09-25 06:04:49'),
(497, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 06:05:00'),
(498, 40, 'logout', '{\"timestamp\":\"2025-09-25 14:05:32\"}', '::1', '2025-09-25 06:05:32'),
(499, 54, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-25 06:05:56'),
(500, 54, 'logout', '{\"timestamp\":\"2025-09-25 14:08:23\"}', '::1', '2025-09-25 06:08:23'),
(501, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 06:08:29'),
(502, 1, 'logout', '{\"timestamp\":\"2025-09-25 14:20:54\"}', '::1', '2025-09-25 06:20:54'),
(503, 17, 'login', '{\"success\":true}', '::1', '2025-09-25 06:21:38'),
(504, 17, 'logout', '{\"timestamp\":\"2025-09-25 14:27:10\"}', '::1', '2025-09-25 06:27:10'),
(505, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 06:27:15'),
(506, 40, 'session_rated', '{\"session_id\":8,\"rating\":5}', '::1', '2025-09-25 06:27:33'),
(507, 40, 'logout', '{\"timestamp\":\"2025-09-25 14:28:31\"}', '::1', '2025-09-25 06:28:31'),
(508, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 06:28:35'),
(509, 1, 'logout', '{\"timestamp\":\"2025-09-25 14:28:49\"}', '::1', '2025-09-25 06:28:49'),
(510, 6, 'login', '{\"success\":true}', '::1', '2025-09-25 06:28:56'),
(511, 6, 'logout', '{\"timestamp\":\"2025-09-25 14:30:47\"}', '::1', '2025-09-25 06:30:47'),
(512, 55, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-25 06:31:04'),
(513, 1, 'login', '{\"success\":true}', '192.168.254.107', '2025-09-25 06:33:53'),
(514, 55, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":2,\"coordinates\":{\"lat\":14.068306,\"lng\":121.131256}}', '::1', '2025-09-25 06:38:25'),
(515, 55, 'logout', '{\"timestamp\":\"2025-09-25 14:39:09\"}', '::1', '2025-09-25 06:39:09'),
(516, 56, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-25 06:39:23'),
(517, 56, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":2,\"coordinates\":{\"lat\":14.068160286695282,\"lng\":121.13115053412018}}', '::1', '2025-09-25 06:40:06'),
(518, 56, 'match_request', '{\"match_id\":\"53\",\"mentor_id\":55,\"subject\":\"Geography\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-09-25 06:42:55'),
(519, 56, 'match_request_pending', '{\"match_id\":\"53\",\"mentor_id\":55,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 06:42:55'),
(520, 56, 'match_request', '{\"match_id\":\"54\",\"mentor_id\":55,\"subject\":\"Geography\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-09-25 06:47:24');
INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(521, 56, 'match_request_pending', '{\"match_id\":\"54\",\"mentor_id\":55,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 06:47:24'),
(522, 56, 'match_request', '{\"match_id\":\"55\",\"mentor_id\":54,\"subject\":\"Geography\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-09-25 06:47:33'),
(523, 56, 'match_request_pending', '{\"match_id\":\"55\",\"mentor_id\":54,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 06:47:33'),
(524, 56, 'match_request', '{\"match_id\":\"56\",\"mentor_id\":43,\"subject\":\"Geography\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-09-25 06:47:39'),
(525, 56, 'match_request_pending', '{\"match_id\":\"56\",\"mentor_id\":43,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 06:47:39'),
(526, 56, 'logout', '{\"timestamp\":\"2025-09-25 14:47:44\"}', '::1', '2025-09-25 06:47:44'),
(527, 43, 'login', '{\"success\":true}', '::1', '2025-09-25 06:47:54'),
(528, 43, 'logout', '{\"timestamp\":\"2025-09-25 15:56:08\"}', '::1', '2025-09-25 07:56:08'),
(529, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 08:05:49'),
(530, 40, 'logout', '{\"timestamp\":\"2025-09-25 16:15:06\"}', '::1', '2025-09-25 08:15:06'),
(531, 57, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-25 08:15:22'),
(532, 57, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":3,\"coordinates\":{\"lat\":14.068167902633109,\"lng\":121.13116743172867}}', '::1', '2025-09-25 08:16:27'),
(533, 57, 'logout', '{\"timestamp\":\"2025-09-25 16:16:52\"}', '::1', '2025-09-25 08:16:52'),
(534, 58, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-25 08:17:14'),
(535, 58, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":3,\"coordinates\":{\"lat\":14.068152593538795,\"lng\":121.13115292714247}}', '::1', '2025-09-25 08:21:29'),
(536, 58, 'logout', '{\"timestamp\":\"2025-09-25 16:26:43\"}', '::1', '2025-09-25 08:26:43'),
(537, 59, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-25 08:27:00'),
(538, 59, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":4,\"coordinates\":{\"lat\":14.068167902633109,\"lng\":121.13116743172867}}', '::1', '2025-09-25 08:29:35'),
(539, 59, 'logout', '{\"timestamp\":\"2025-09-25 16:34:51\"}', '::1', '2025-09-25 08:34:51'),
(540, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 08:35:00'),
(541, 1, 'logout', '{\"timestamp\":\"2025-09-25 16:37:09\"}', '::1', '2025-09-25 08:37:09'),
(542, 59, 'login', '{\"success\":true}', '::1', '2025-09-25 08:37:16'),
(543, 59, 'logout', '{\"timestamp\":\"2025-09-25 16:37:24\"}', '::1', '2025-09-25 08:37:24'),
(544, 60, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-25 08:37:40'),
(545, 60, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068167902633109,\"lng\":121.13116743172867}}', '::1', '2025-09-25 08:38:03'),
(546, 60, 'logout', '{\"timestamp\":\"2025-09-25 16:42:25\"}', '::1', '2025-09-25 08:42:25'),
(547, 61, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-25 08:42:47'),
(548, 61, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068208172317597,\"lng\":121.13119783218885}}', '::1', '2025-09-25 08:43:21'),
(549, 61, 'logout', '{\"timestamp\":\"2025-09-25 16:57:46\"}', '::1', '2025-09-25 08:57:46'),
(550, 62, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-25 08:58:01'),
(551, 62, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068167902633109,\"lng\":121.13116743172867}}', '::1', '2025-09-25 08:58:25'),
(552, 62, 'logout', '{\"timestamp\":\"2025-09-25 18:08:33\"}', '::1', '2025-09-25 10:08:33'),
(553, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 10:08:53'),
(554, 1, 'logout', '{\"timestamp\":\"2025-09-25 18:09:38\"}', '::1', '2025-09-25 10:09:38'),
(555, 63, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-25 10:10:13'),
(556, 63, 'logout', '{\"timestamp\":\"2025-09-25 18:11:15\"}', '::1', '2025-09-25 10:11:15'),
(557, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 10:11:22'),
(558, 40, 'logout', '{\"timestamp\":\"2025-09-25 20:13:38\"}', '::1', '2025-09-25 12:13:38'),
(559, 64, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-25 12:16:59'),
(560, 64, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.07025064208647,\"lng\":121.13032621093689}}', '::1', '2025-09-25 12:21:12'),
(561, 64, 'logout', '{\"timestamp\":\"2025-09-25 20:27:45\"}', '::1', '2025-09-25 12:27:45'),
(562, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 12:27:49'),
(563, 1, 'logout', '{\"timestamp\":\"2025-09-25 20:27:57\"}', '::1', '2025-09-25 12:27:57'),
(564, 40, 'login', '{\"success\":true}', '::1', '2025-09-25 12:28:09'),
(565, 40, 'logout', '{\"timestamp\":\"2025-09-25 20:28:42\"}', '::1', '2025-09-25 12:28:42'),
(566, 64, 'login', '{\"success\":true}', '::1', '2025-09-25 12:28:57'),
(567, 64, 'match_request', '{\"match_id\":\"57\",\"mentor_id\":53,\"subject\":\"History\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-25 12:29:54'),
(568, 64, 'match_request_pending', '{\"match_id\":\"57\",\"mentor_id\":53,\"delivery_method\":\"pending\"}', '::1', '2025-09-25 12:29:54'),
(569, 64, 'logout', '{\"timestamp\":\"2025-09-25 20:31:38\"}', '::1', '2025-09-25 12:31:38'),
(570, 53, 'login', '{\"success\":true}', '::1', '2025-09-25 12:31:51'),
(571, 53, 'match_response', '{\"match_id\":57,\"response\":\"accepted\"}', '::1', '2025-09-25 12:31:54'),
(572, 53, 'logout', '{\"timestamp\":\"2025-09-25 20:31:55\"}', '::1', '2025-09-25 12:31:55'),
(573, 64, 'login', '{\"success\":true}', '::1', '2025-09-25 12:32:04'),
(574, 64, 'session_scheduled', '{\"match_id\":57,\"date\":\"2025-09-26\"}', '::1', '2025-09-25 12:33:21'),
(575, 64, 'message_sent', '{\"match_id\":57,\"partner_id\":53}', '::1', '2025-09-25 12:34:17'),
(576, 64, 'message_sent', '{\"match_id\":57,\"partner_id\":53}', '::1', '2025-09-25 12:34:33'),
(577, 64, 'logout', '{\"timestamp\":\"2025-09-25 20:36:45\"}', '::1', '2025-09-25 12:36:45'),
(578, 1, 'login', '{\"success\":true}', '::1', '2025-09-25 12:36:48'),
(579, 1, 'admin_verify_user', '{\"verified_user_id\":64}', '::1', '2025-09-25 12:37:13'),
(580, 1, 'admin_unverify_user', '{\"unverified_user_id\":64}', '::1', '2025-09-25 12:37:16'),
(581, 1, 'admin_verify_user', '{\"verified_user_id\":59}', '::1', '2025-09-25 12:40:49'),
(582, 1, 'logout', '{\"timestamp\":\"2025-09-25 20:46:10\"}', '::1', '2025-09-25 12:46:10'),
(583, 1, 'login', '{\"success\":true}', '::1', '2025-09-26 05:41:09'),
(584, 1, 'logout', '{\"timestamp\":\"2025-09-26 13:45:42\"}', '::1', '2025-09-26 05:45:42'),
(585, 40, 'login', '{\"success\":true}', '::1', '2025-09-26 05:45:49'),
(586, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-09-26 05:46:21'),
(587, 40, 'logout', '{\"timestamp\":\"2025-09-26 13:47:31\"}', '::1', '2025-09-26 05:47:31'),
(588, 40, 'login', '{\"success\":true}', '::1', '2025-09-26 05:49:59'),
(589, 40, 'match_request', '{\"match_id\":\"58\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:31'),
(590, 40, 'match_request_pending', '{\"match_id\":\"58\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:31'),
(591, 40, 'match_request', '{\"match_id\":\"59\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:33'),
(592, 40, 'match_request_pending', '{\"match_id\":\"59\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:33'),
(593, 40, 'match_request', '{\"match_id\":\"60\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:35'),
(594, 40, 'match_request_pending', '{\"match_id\":\"60\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:35'),
(595, 40, 'match_request', '{\"match_id\":\"61\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:37'),
(596, 40, 'match_request_pending', '{\"match_id\":\"61\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:37'),
(597, 40, 'match_request', '{\"match_id\":\"62\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:39'),
(598, 40, 'match_request_pending', '{\"match_id\":\"62\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:39'),
(599, 40, 'match_request', '{\"match_id\":\"63\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:41'),
(600, 40, 'match_request_pending', '{\"match_id\":\"63\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:41'),
(601, 40, 'match_request', '{\"match_id\":\"64\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:43'),
(602, 40, 'match_request_pending', '{\"match_id\":\"64\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:43'),
(603, 40, 'match_request', '{\"match_id\":\"65\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:46'),
(604, 40, 'match_request_pending', '{\"match_id\":\"65\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:46'),
(605, 40, 'match_request', '{\"match_id\":\"66\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:48'),
(606, 40, 'match_request_pending', '{\"match_id\":\"66\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:48'),
(607, 40, 'match_request', '{\"match_id\":\"67\",\"mentor_id\":63,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-26 06:01:50'),
(608, 40, 'match_request_pending', '{\"match_id\":\"67\",\"mentor_id\":63,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 06:01:50'),
(609, 40, 'session_scheduled', '{\"match_id\":42,\"date\":\"2025-09-26\"}', '::1', '2025-09-26 06:32:28'),
(610, 40, 'logout', '{\"timestamp\":\"2025-09-26 14:33:50\"}', '::1', '2025-09-26 06:33:50'),
(611, 1, 'login', '{\"success\":true}', '::1', '2025-09-26 06:33:52'),
(612, 1, 'logout', '{\"timestamp\":\"2025-09-26 14:37:14\"}', '::1', '2025-09-26 06:37:14'),
(613, 40, 'login', '{\"success\":true}', '::1', '2025-09-26 06:37:22'),
(614, 40, 'logout', '{\"timestamp\":\"2025-09-26 14:49:10\"}', '::1', '2025-09-26 06:49:10'),
(615, 65, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-26 12:13:24'),
(616, 65, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068203087577736,\"lng\":121.13119654320438}}', '::1', '2025-09-26 12:13:59'),
(617, 65, 'match_request', '{\"match_id\":\"68\",\"mentor_id\":64,\"subject\":\"Programming - C++\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-09-26 12:15:38'),
(618, 65, 'match_request_pending', '{\"match_id\":\"68\",\"mentor_id\":64,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 12:15:38'),
(619, 65, 'logout', '{\"timestamp\":\"2025-09-26 20:16:27\"}', '::1', '2025-09-26 12:16:27'),
(620, 66, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-26 12:17:03'),
(621, 66, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068433069666831,\"lng\":121.13103870452913}}', '::1', '2025-09-26 12:19:22'),
(622, 66, 'match_request', '{\"match_id\":\"69\",\"mentor_id\":64,\"subject\":\"Programming - C++\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-09-26 12:33:39'),
(623, 66, 'match_request_pending', '{\"match_id\":\"69\",\"mentor_id\":64,\"delivery_method\":\"pending\"}', '::1', '2025-09-26 12:33:39'),
(624, 66, 'logout', '{\"timestamp\":\"2025-09-26 20:51:45\"}', '::1', '2025-09-26 12:51:45'),
(625, 67, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-26 12:52:10'),
(626, 67, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068208172317597,\"lng\":121.13119783218885}}', '::1', '2025-09-26 12:53:06'),
(627, 1, 'login', '{\"success\":true}', '::1', '2025-09-27 09:33:31'),
(628, 1, 'logout', '{\"timestamp\":\"2025-09-27 17:33:40\"}', '::1', '2025-09-27 09:33:40'),
(629, 1, 'login', '{\"success\":true}', '::1', '2025-09-27 09:33:42'),
(630, 1, 'logout', '{\"timestamp\":\"2025-09-27 17:33:50\"}', '::1', '2025-09-27 09:33:50'),
(631, 40, 'login', '{\"success\":true}', '::1', '2025-09-27 09:33:57'),
(632, 40, 'session_completed', '{\"session_id\":11}', '::1', '2025-09-27 09:34:43'),
(633, 40, 'session_rated', '{\"session_id\":11,\"rating\":2}', '::1', '2025-09-27 09:34:51'),
(634, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-09-27 09:35:05'),
(635, 40, 'logout', '{\"timestamp\":\"2025-09-27 18:20:36\"}', '::1', '2025-09-27 10:20:36'),
(636, 68, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-27 10:20:59'),
(637, 68, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.073,\"lng\":120.6295}}', '::1', '2025-09-27 10:21:57'),
(638, 68, 'logout', '{\"timestamp\":\"2025-09-27 18:27:24\"}', '::1', '2025-09-27 10:27:24'),
(639, 69, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-27 10:27:50'),
(640, 69, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.073,\"lng\":120.6295}}', '::1', '2025-09-27 10:29:03'),
(641, 69, 'logout', '{\"timestamp\":\"2025-09-27 18:40:04\"}', '::1', '2025-09-27 10:40:04'),
(642, 11, 'login', '{\"success\":true}', '::1', '2025-09-27 10:40:11'),
(643, 11, 'logout', '{\"timestamp\":\"2025-09-27 18:41:55\"}', '::1', '2025-09-27 10:41:55'),
(644, 40, 'login', '{\"success\":true}', '::1', '2025-09-27 10:42:06'),
(645, 40, 'logout', '{\"timestamp\":\"2025-09-27 18:42:41\"}', '::1', '2025-09-27 10:42:41'),
(646, 68, 'login', '{\"success\":true}', '::1', '2025-09-27 10:42:49'),
(647, 68, 'match_request', '{\"match_id\":\"70\",\"mentor_id\":67,\"subject\":\"C++\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-27 11:11:19'),
(648, 68, 'match_request_pending', '{\"match_id\":\"70\",\"mentor_id\":67,\"delivery_method\":\"pending\"}', '::1', '2025-09-27 11:11:19'),
(649, 68, 'match_request', '{\"match_id\":\"71\",\"mentor_id\":67,\"subject\":\"C++\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-27 11:11:43'),
(650, 68, 'match_request_pending', '{\"match_id\":\"71\",\"mentor_id\":67,\"delivery_method\":\"pending\"}', '::1', '2025-09-27 11:11:43'),
(651, 68, 'logout', '{\"timestamp\":\"2025-09-27 19:13:17\"}', '::1', '2025-09-27 11:13:17'),
(652, 1, 'login', '{\"success\":true}', '::1', '2025-09-27 11:13:21'),
(653, 1, 'logout', '{\"timestamp\":\"2025-09-27 19:24:40\"}', '::1', '2025-09-27 11:24:40'),
(654, 40, 'login', '{\"success\":true}', '::1', '2025-09-27 11:24:48'),
(655, 40, 'session_scheduled', '{\"match_id\":42,\"date\":\"2025-09-27\"}', '::1', '2025-09-27 11:27:25'),
(656, 40, 'session_scheduled', '{\"match_id\":37,\"date\":\"2025-09-28\"}', '::1', '2025-09-27 11:28:30'),
(657, 40, 'session_cancelled', '{\"session_id\":13,\"reason\":\"Student unavailable\"}', '::1', '2025-09-27 11:29:57'),
(658, 40, 'logout', '{\"timestamp\":\"2025-09-27 19:30:03\"}', '::1', '2025-09-27 11:30:03'),
(659, 1, 'login', '{\"success\":true}', '::1', '2025-09-27 11:30:07'),
(660, 1, 'logout', '{\"timestamp\":\"2025-09-27 19:46:31\"}', '::1', '2025-09-27 11:46:31'),
(661, 70, 'register', '{\"role\":\"peer\",\"referral_used\":true,\"referral_code\":\"MENTOR0008\",\"referral_code_id\":1,\"referred_by\":8}', '::1', '2025-09-27 11:47:01'),
(662, 70, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-09-27 11:47:49'),
(663, 70, 'logout', '{\"timestamp\":\"2025-09-27 19:52:02\"}', '::1', '2025-09-27 11:52:02'),
(664, 71, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-27 11:52:19'),
(665, 71, 'logout', '{\"timestamp\":\"2025-09-27 19:54:13\"}', '::1', '2025-09-27 11:54:13'),
(666, 1, 'login', '{\"success\":true}', '::1', '2025-09-27 11:54:15'),
(667, 1, 'logout', '{\"timestamp\":\"2025-09-27 19:57:31\"}', '::1', '2025-09-27 11:57:31'),
(668, 40, 'login', '{\"success\":true}', '::1', '2025-09-27 11:57:37'),
(669, 40, 'logout', '{\"timestamp\":\"2025-09-27 19:58:01\"}', '::1', '2025-09-27 11:58:01'),
(670, 72, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-27 11:58:20'),
(671, 72, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.073,\"lng\":120.6295}}', '::1', '2025-09-27 11:59:03'),
(672, 72, 'logout', '{\"timestamp\":\"2025-09-27 20:19:30\"}', '::1', '2025-09-27 12:19:30'),
(673, 40, 'login', '{\"success\":true}', '::1', '2025-09-27 12:19:36'),
(674, 40, 'session_scheduled', '{\"match_id\":42,\"date\":\"2025-09-27\"}', '::1', '2025-09-27 12:20:09'),
(675, 40, 'session_cancelled', '{\"session_id\":14,\"reason\":\"Personal reasons\"}', '::1', '2025-09-27 12:20:52'),
(676, 40, 'logout', '{\"timestamp\":\"2025-09-27 20:21:44\"}', '::1', '2025-09-27 12:21:44'),
(677, 1, 'login', '{\"success\":true}', '::1', '2025-09-27 12:21:47'),
(678, 1, 'logout', '{\"timestamp\":\"2025-09-28 20:59:34\"}', '::1', '2025-09-28 12:59:34'),
(679, 40, 'login', '{\"success\":true}', '::1', '2025-09-28 12:59:55'),
(680, 40, 'match_request', '{\"match_id\":\"72\",\"mentor_id\":62,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-09-28 13:14:37'),
(681, 40, 'match_request_pending', '{\"match_id\":\"72\",\"mentor_id\":62,\"delivery_method\":\"pending\"}', '::1', '2025-09-28 13:14:37'),
(682, 40, 'match_request', '{\"match_id\":\"73\",\"mentor_id\":53,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-09-28 13:25:31'),
(683, 40, 'match_request_pending', '{\"match_id\":\"73\",\"mentor_id\":53,\"delivery_method\":\"pending\"}', '::1', '2025-09-28 13:25:31'),
(684, 40, 'match_request', '{\"match_id\":\"74\",\"mentor_id\":54,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-09-28 14:21:37'),
(685, 40, 'match_request_pending', '{\"match_id\":\"74\",\"mentor_id\":54,\"delivery_method\":\"pending\"}', '::1', '2025-09-28 14:21:37'),
(686, 40, 'logout', '{\"timestamp\":\"2025-09-28 23:19:02\"}', '::1', '2025-09-28 15:19:02'),
(687, 73, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-28 15:20:50'),
(688, 73, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098330796970876,\"lng\":121.06582044299203}}', '::1', '2025-09-28 15:22:40'),
(689, 73, 'logout', '{\"timestamp\":\"2025-09-28 23:23:29\"}', '::1', '2025-09-28 15:23:29'),
(690, NULL, 'login', '{\"success\":true}', '::1', '2025-09-28 15:23:41'),
(691, NULL, 'logout', '{\"timestamp\":\"2025-09-28 23:23:51\"}', '::1', '2025-09-28 15:23:51'),
(692, 69, 'login', '{\"success\":true}', '::1', '2025-09-28 15:24:01'),
(693, 69, 'logout', '{\"timestamp\":\"2025-09-28 23:26:13\"}', '::1', '2025-09-28 15:26:13'),
(694, 40, 'login', '{\"success\":true}', '::1', '2025-09-28 15:26:19'),
(695, 40, 'logout', '{\"timestamp\":\"2025-09-29 00:03:15\"}', '::1', '2025-09-28 16:03:15'),
(696, 74, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-28 16:03:47'),
(697, 74, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098321222165964,\"lng\":121.06582301161265}}', '::1', '2025-09-28 16:04:28'),
(698, 74, 'logout', '{\"timestamp\":\"2025-09-29 02:02:29\"}', '::1', '2025-09-28 18:02:29'),
(699, 1, 'login', '{\"success\":true}', '::1', '2025-09-28 18:02:32'),
(700, 1, 'logout', '{\"timestamp\":\"2025-09-29 02:05:32\"}', '::1', '2025-09-28 18:05:32'),
(701, 40, 'login', '{\"success\":true}', '::1', '2025-09-28 18:05:42'),
(702, 40, 'logout', '{\"timestamp\":\"2025-09-29 02:36:46\"}', '::1', '2025-09-28 18:36:46'),
(703, 4, 'login', '{\"success\":true}', '::1', '2025-09-28 18:36:55'),
(704, 4, 'match_response', '{\"match_id\":39,\"response\":\"accepted\"}', '::1', '2025-09-28 18:39:15'),
(705, 4, 'match_response', '{\"match_id\":26,\"response\":\"rejected\"}', '::1', '2025-09-28 18:39:18'),
(706, 4, 'logout', '{\"timestamp\":\"2025-09-29 02:39:53\"}', '::1', '2025-09-28 18:39:53'),
(707, 40, 'login', '{\"success\":true}', '::1', '2025-09-28 18:39:57'),
(708, 40, 'logout', '{\"timestamp\":\"2025-09-29 02:40:09\"}', '::1', '2025-09-28 18:40:09'),
(709, 11, 'login', '{\"success\":true}', '::1', '2025-09-28 18:40:21'),
(710, 11, 'logout', '{\"timestamp\":\"2025-09-29 02:40:59\"}', '::1', '2025-09-28 18:40:59'),
(711, 20, 'login', '{\"success\":true}', '::1', '2025-09-28 18:41:06'),
(712, 20, 'logout', '{\"timestamp\":\"2025-09-29 02:41:54\"}', '::1', '2025-09-28 18:41:54'),
(713, 75, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-28 18:42:16'),
(714, 75, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098317295132443,\"lng\":121.06581859752846}}', '::1', '2025-09-28 18:43:08'),
(715, 75, 'logout', '{\"timestamp\":\"2025-09-29 08:16:20\"}', '::1', '2025-09-29 00:16:20'),
(716, 11, 'login', '{\"success\":true}', '::1', '2025-09-29 00:16:32'),
(717, 11, 'logout', '{\"timestamp\":\"2025-09-29 08:16:34\"}', '::1', '2025-09-29 00:16:34'),
(718, 9, 'login', '{\"success\":true}', '::1', '2025-09-29 00:16:51'),
(719, 9, 'logout', '{\"timestamp\":\"2025-09-29 08:51:15\"}', '::1', '2025-09-29 00:51:15'),
(720, 4, 'login', '{\"success\":true}', '::1', '2025-09-29 00:51:26'),
(721, 4, 'logout', '{\"timestamp\":\"2025-09-29 08:52:16\"}', '::1', '2025-09-29 00:52:16'),
(722, 40, 'login', '{\"success\":true}', '::1', '2025-09-29 00:52:22'),
(723, 40, 'logout', '{\"timestamp\":\"2025-09-29 08:53:35\"}', '::1', '2025-09-29 00:53:35'),
(724, 76, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-29 00:54:11'),
(725, 76, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098343659624476,\"lng\":121.0658190325749}}', '::1', '2025-09-29 00:55:22'),
(726, 76, 'logout', '{\"timestamp\":\"2025-09-29 09:18:12\"}', '::1', '2025-09-29 01:18:12'),
(727, 77, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-29 01:18:30'),
(728, 77, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0983405697924,\"lng\":121.06582128954567}}', '::1', '2025-09-29 01:19:01'),
(729, 77, 'logout', '{\"timestamp\":\"2025-09-29 09:56:25\"}', '::1', '2025-09-29 01:56:25'),
(730, 77, 'login', '{\"success\":true}', '::1', '2025-09-29 01:56:36'),
(731, 77, 'logout', '{\"timestamp\":\"2025-09-29 09:56:38\"}', '::1', '2025-09-29 01:56:38'),
(732, 76, 'login', '{\"success\":true}', '::1', '2025-09-29 01:56:43'),
(733, 76, 'logout', '{\"timestamp\":\"2025-09-29 09:57:21\"}', '::1', '2025-09-29 01:57:21'),
(734, 4, 'login', '{\"success\":true}', '::1', '2025-09-29 01:57:26'),
(735, 4, 'logout', '{\"timestamp\":\"2025-09-29 09:57:59\"}', '::1', '2025-09-29 01:57:59'),
(736, 1, 'login', '{\"success\":true}', '::1', '2025-09-29 01:58:01'),
(737, 1, 'logout', '{\"timestamp\":\"2025-09-29 09:58:16\"}', '::1', '2025-09-29 01:58:16'),
(738, 4, 'login', '{\"success\":true}', '::1', '2025-09-29 01:58:23'),
(739, 4, 'logout', '{\"timestamp\":\"2025-09-29 10:01:24\"}', '::1', '2025-09-29 02:01:24'),
(740, 40, 'login', '{\"success\":true}', '::1', '2025-09-29 02:01:32'),
(741, 40, 'logout', '{\"timestamp\":\"2025-09-29 10:02:11\"}', '::1', '2025-09-29 02:02:11'),
(742, 78, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-29 02:02:43'),
(743, 78, 'logout', '{\"timestamp\":\"2025-09-29 10:02:59\"}', '::1', '2025-09-29 02:02:59'),
(744, 79, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-29 02:03:31'),
(745, 79, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098311220609421,\"lng\":121.06581526697094}}', '::1', '2025-09-29 02:05:21'),
(746, 79, 'logout', '{\"timestamp\":\"2025-09-29 10:07:04\"}', '::1', '2025-09-29 02:07:04'),
(747, 77, 'login', '{\"success\":true}', '::1', '2025-09-29 02:07:21'),
(748, 77, 'logout', '{\"timestamp\":\"2025-09-29 10:07:26\"}', '::1', '2025-09-29 02:07:26'),
(749, 76, 'login', '{\"success\":true}', '::1', '2025-09-29 02:07:34'),
(750, 76, 'logout', '{\"timestamp\":\"2025-09-29 10:07:51\"}', '::1', '2025-09-29 02:07:51'),
(751, 80, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-29 02:08:08'),
(752, 80, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098309809007361,\"lng\":121.06581411322345}}', '::1', '2025-09-29 02:08:37'),
(753, 80, 'logout', '{\"timestamp\":\"2025-09-29 10:17:30\"}', '::1', '2025-09-29 02:17:30'),
(754, 76, 'login', '{\"success\":true}', '::1', '2025-09-29 02:17:42'),
(755, 81, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-29 04:24:52'),
(756, 81, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098322554276725,\"lng\":121.0658227272591}}', '::1', '2025-09-29 04:25:54'),
(757, 40, 'login', '{\"success\":true}', '::1', '2025-09-30 03:26:19'),
(758, 40, 'logout', '{\"timestamp\":\"2025-09-30 11:26:31\"}', '::1', '2025-09-30 03:26:31'),
(759, 76, 'login', '{\"success\":true}', '::1', '2025-09-30 03:26:39'),
(760, 76, 'logout', '{\"timestamp\":\"2025-09-30 11:32:04\"}', '::1', '2025-09-30 03:32:04'),
(761, 1, 'login', '{\"success\":true}', '::1', '2025-09-30 03:32:06'),
(762, 1, 'logout', '{\"timestamp\":\"2025-09-30 11:47:33\"}', '::1', '2025-09-30 03:47:33'),
(763, 82, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-30 03:50:02'),
(764, 82, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 03:55:26'),
(765, 82, 'logout', '{\"timestamp\":\"2025-09-30 12:58:33\"}', '::1', '2025-09-30 04:58:33'),
(766, 1, 'login', '{\"success\":true}', '::1', '2025-09-30 04:58:42'),
(767, 1, 'logout', '{\"timestamp\":\"2025-09-30 13:04:04\"}', '::1', '2025-09-30 05:04:04'),
(768, 78, 'login', '{\"success\":true}', '::1', '2025-09-30 05:04:27'),
(769, 78, 'logout', '{\"timestamp\":\"2025-09-30 13:14:50\"}', '::1', '2025-09-30 05:14:50'),
(770, 77, 'login', '{\"success\":true}', '::1', '2025-09-30 05:15:03'),
(771, 77, 'match_request', '{\"match_id\":\"75\",\"mentor_id\":74,\"subject\":\"C++\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-09-30 05:15:27'),
(772, 77, 'match_request_pending', '{\"match_id\":\"75\",\"mentor_id\":74,\"delivery_method\":\"pending\"}', '::1', '2025-09-30 05:15:27'),
(773, 77, 'match_request', '{\"match_id\":\"76\",\"mentor_id\":74,\"subject\":\"C++\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-09-30 05:22:32'),
(774, 77, 'match_request_pending', '{\"match_id\":\"76\",\"mentor_id\":74,\"delivery_method\":\"pending\"}', '::1', '2025-09-30 05:22:32'),
(775, 77, 'logout', '{\"timestamp\":\"2025-09-30 13:24:49\"}', '::1', '2025-09-30 05:24:49'),
(776, 68, 'login', '{\"success\":true}', '::1', '2025-09-30 05:25:24'),
(777, 68, 'logout', '{\"timestamp\":\"2025-09-30 13:27:14\"}', '::1', '2025-09-30 05:27:14'),
(778, 40, 'login', '{\"success\":true}', '::1', '2025-09-30 05:27:21'),
(779, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-09-30 05:35:51'),
(780, 40, 'logout', '{\"timestamp\":\"2025-09-30 13:36:03\"}', '::1', '2025-09-30 05:36:03'),
(781, 1, 'login', '{\"success\":true}', '::1', '2025-09-30 05:36:08'),
(782, 1, 'logout', '{\"timestamp\":\"2025-09-30 13:37:02\"}', '::1', '2025-09-30 05:37:02'),
(783, 83, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 05:37:25'),
(784, 83, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 05:39:43'),
(785, 83, 'logout', '{\"timestamp\":\"2025-09-30 13:41:40\"}', '::1', '2025-09-30 05:41:40'),
(786, 76, 'login', '{\"success\":true}', '::1', '2025-09-30 05:41:47'),
(787, 76, 'logout', '{\"timestamp\":\"2025-09-30 13:41:56\"}', '::1', '2025-09-30 05:41:56'),
(788, 83, 'login', '{\"success\":true}', '::1', '2025-09-30 05:42:05'),
(789, 83, 'logout', '{\"timestamp\":\"2025-09-30 13:45:33\"}', '::1', '2025-09-30 05:45:33'),
(790, 84, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 05:46:00'),
(791, 84, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 05:48:54'),
(792, 84, 'logout', '{\"timestamp\":\"2025-09-30 13:49:40\"}', '::1', '2025-09-30 05:49:40'),
(793, 85, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-30 05:50:21'),
(794, 85, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 05:51:39'),
(795, 85, 'logout', '{\"timestamp\":\"2025-09-30 13:51:58\"}', '::1', '2025-09-30 05:51:58'),
(796, 86, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-09-30 05:52:15'),
(797, 86, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 05:52:34'),
(798, 86, 'logout', '{\"timestamp\":\"2025-09-30 13:53:01\"}', '::1', '2025-09-30 05:53:01'),
(799, 87, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 05:58:32'),
(800, 87, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 06:00:54'),
(801, 87, 'logout', '{\"timestamp\":\"2025-09-30 14:02:32\"}', '::1', '2025-09-30 06:02:32'),
(802, 88, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 06:03:56'),
(803, 88, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 06:11:59'),
(804, 88, 'logout', '{\"timestamp\":\"2025-09-30 14:25:27\"}', '::1', '2025-09-30 06:25:27'),
(805, 83, 'login', '{\"success\":true}', '::1', '2025-09-30 06:26:00'),
(806, 83, 'logout', '{\"timestamp\":\"2025-09-30 14:26:46\"}', '::1', '2025-09-30 06:26:46'),
(807, 89, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-09-30 06:27:12'),
(808, 89, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 06:27:49'),
(809, 89, 'logout', '{\"timestamp\":\"2025-09-30 18:36:10\"}', '::1', '2025-09-30 10:36:10'),
(810, 90, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 10:43:58'),
(811, 90, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 10:45:10'),
(812, 90, 'logout', '{\"timestamp\":\"2025-09-30 18:47:02\"}', '::1', '2025-09-30 10:47:02'),
(813, 91, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 10:47:32'),
(814, 91, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.08,\"lng\":121.15}}', '::1', '2025-09-30 10:49:03'),
(815, 91, 'logout', '{\"timestamp\":\"2025-09-30 18:53:32\"}', '::1', '2025-09-30 10:53:32'),
(816, 92, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-09-30 10:53:56'),
(817, 92, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068384,\"lng\":121.131119}}', '::1', '2025-09-30 10:54:50'),
(818, 92, 'logout', '{\"timestamp\":\"2025-09-30 18:56:22\"}', '::1', '2025-09-30 10:56:22'),
(819, 1, 'login', '{\"success\":true}', '::1', '2025-09-30 10:57:25'),
(820, 1, 'logout', '{\"timestamp\":\"2025-09-30 18:57:34\"}', '::1', '2025-09-30 10:57:34'),
(821, 86, 'login', '{\"success\":true}', '::1', '2025-09-30 10:57:40'),
(822, 86, 'logout', '{\"timestamp\":\"2025-09-30 21:17:40\"}', '::1', '2025-09-30 13:17:40'),
(823, 83, 'login', '{\"success\":true}', '::1', '2025-09-30 13:17:49'),
(824, 83, 'logout', '{\"timestamp\":\"2025-09-30 21:30:23\"}', '::1', '2025-09-30 13:30:23'),
(825, 82, 'login', '{\"success\":true}', '::1', '2025-09-30 13:30:31'),
(826, 82, 'match_request', '{\"match_id\":\"77\",\"mentor_id\":83,\"subject\":\"Web Development\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-09-30 13:31:05'),
(827, 82, 'match_request_pending', '{\"match_id\":\"77\",\"mentor_id\":83,\"delivery_method\":\"pending\"}', '::1', '2025-09-30 13:31:05'),
(828, 82, 'logout', '{\"timestamp\":\"2025-09-30 21:31:15\"}', '::1', '2025-09-30 13:31:15'),
(829, 83, 'login', '{\"success\":true}', '::1', '2025-09-30 13:31:23'),
(830, 83, 'match_response', '{\"match_id\":77,\"response\":\"accepted\"}', '::1', '2025-09-30 13:31:39'),
(831, 83, 'session_scheduled', '{\"match_id\":77,\"date\":\"2025-09-30\"}', '::1', '2025-09-30 13:31:56'),
(832, 83, 'logout', '{\"timestamp\":\"2025-09-30 21:32:05\"}', '::1', '2025-09-30 13:32:05'),
(833, 82, 'login', '{\"success\":true}', '::1', '2025-09-30 13:32:19'),
(834, 82, 'session_completed', '{\"session_id\":15}', '::1', '2025-09-30 13:32:28'),
(835, 82, 'session_rated', '{\"session_id\":15,\"rating\":5}', '::1', '2025-09-30 13:32:39'),
(836, 82, 'logout', '{\"timestamp\":\"2025-09-30 21:33:00\"}', '::1', '2025-09-30 13:33:00'),
(837, 83, 'login', '{\"success\":true}', '::1', '2025-09-30 13:33:11'),
(838, 83, 'session_rated', '{\"session_id\":15,\"rating\":5}', '::1', '2025-09-30 13:33:28'),
(839, 83, 'logout', '{\"timestamp\":\"2025-09-30 21:34:28\"}', '::1', '2025-09-30 13:34:28'),
(840, 82, 'login', '{\"success\":true}', '::1', '2025-09-30 13:34:57'),
(841, 82, 'message_sent', '{\"match_id\":77,\"partner_id\":83}', '::1', '2025-09-30 13:35:41'),
(842, 82, 'logout', '{\"timestamp\":\"2025-09-30 21:41:16\"}', '::1', '2025-09-30 13:41:16'),
(843, 1, 'login', '{\"success\":true}', '::1', '2025-09-30 13:41:19'),
(844, 1, 'logout', '{\"timestamp\":\"2025-09-30 21:43:20\"}', '::1', '2025-09-30 13:43:20'),
(845, 82, 'login', '{\"success\":true}', '::1', '2025-09-30 13:51:35'),
(846, 82, 'upgrade_to_peer', '{\"from_role\":\"student\",\"to_role\":\"peer\"}', '::1', '2025-09-30 13:52:33'),
(847, 82, 'logout', '{\"timestamp\":\"2025-10-01 11:29:35\"}', '::1', '2025-10-01 03:29:35'),
(848, 1, 'login', '{\"success\":true}', '::1', '2025-10-01 03:30:12'),
(849, 1, 'logout', '{\"timestamp\":\"2025-10-01 11:43:15\"}', '::1', '2025-10-01 03:43:15'),
(850, 76, 'login', '{\"success\":true}', '::1', '2025-10-01 03:44:48'),
(851, 77, 'login', '{\"success\":true}', '::1', '2025-10-01 03:45:12'),
(852, 77, 'logout', '{\"timestamp\":\"2025-10-01 11:45:18\"}', '::1', '2025-10-01 03:45:18'),
(853, 1, 'login', '{\"success\":true}', '::1', '2025-10-01 03:45:22'),
(854, 1, 'admin_verify_user', '{\"verified_user_id\":86}', '::1', '2025-10-01 03:45:39'),
(855, 1, 'logout', '{\"timestamp\":\"2025-10-01 11:45:44\"}', '::1', '2025-10-01 03:45:44'),
(856, 86, 'login', '{\"success\":true}', '::1', '2025-10-01 03:45:53'),
(857, 86, 'logout', '{\"timestamp\":\"2025-10-01 19:18:56\"}', '::1', '2025-10-01 11:18:56'),
(858, 1, 'login', '{\"success\":true}', '::1', '2025-10-01 11:19:43'),
(859, 1, 'logout', '{\"timestamp\":\"2025-10-01 19:20:17\"}', '::1', '2025-10-01 11:20:17'),
(860, 68, 'login', '{\"success\":true}', '::1', '2025-10-01 11:20:24'),
(861, 68, 'logout', '{\"timestamp\":\"2025-10-01 19:21:58\"}', '::1', '2025-10-01 11:21:58'),
(862, 1, 'login', '{\"success\":true}', '::1', '2025-10-01 11:22:00'),
(863, 1, 'logout', '{\"timestamp\":\"2025-10-01 19:26:02\"}', '::1', '2025-10-01 11:26:02'),
(864, 68, 'login', '{\"success\":true}', '::1', '2025-10-01 11:26:07'),
(865, 68, 'logout', '{\"timestamp\":\"2025-10-01 19:27:07\"}', '::1', '2025-10-01 11:27:07'),
(866, 40, 'login', '{\"success\":true}', '::1', '2025-10-01 11:27:30'),
(867, 93, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-10-01 11:37:37'),
(868, 93, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068204279224206,\"lng\":121.1309006044947}}', '::1', '2025-10-01 11:39:29'),
(869, 40, 'logout', '{\"timestamp\":\"2025-10-01 19:39:54\"}', '::1', '2025-10-01 11:39:54'),
(870, 94, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-01 11:40:14'),
(871, 94, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068204279224206,\"lng\":121.1309006044947}}', '::1', '2025-10-01 11:40:56'),
(872, 94, 'match_request', '{\"match_id\":\"78\",\"mentor_id\":93,\"subject\":\"HTML\\/CSS\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-10-01 11:41:30'),
(873, 94, 'match_request_pending', '{\"match_id\":\"78\",\"mentor_id\":93,\"delivery_method\":\"pending\"}', '::1', '2025-10-01 11:41:30'),
(874, 93, 'match_response', '{\"match_id\":78,\"response\":\"accepted\"}', '::1', '2025-10-01 11:41:43'),
(875, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 11:41:55'),
(876, 93, 'session_scheduled', '{\"match_id\":78,\"date\":\"2025-10-01\"}', '::1', '2025-10-01 11:42:41'),
(877, 94, 'session_completed', '{\"session_id\":16}', '::1', '2025-10-01 11:43:05'),
(878, 94, 'session_rated', '{\"session_id\":16,\"rating\":4}', '::1', '2025-10-01 11:43:16'),
(879, 93, 'session_rated', '{\"session_id\":16,\"rating\":4}', '::1', '2025-10-01 11:43:39'),
(880, 93, 'message_sent', '{\"match_id\":78,\"partner_id\":94}', '::1', '2025-10-01 11:43:54'),
(881, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 11:43:55'),
(882, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 11:43:58'),
(883, 93, 'message_sent', '{\"match_id\":78,\"partner_id\":94}', '::1', '2025-10-01 11:44:08'),
(884, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 11:44:15'),
(885, 93, 'logout', '{\"timestamp\":\"2025-10-01 19:56:34\"}', '::1', '2025-10-01 11:56:34'),
(886, 95, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-10-01 11:57:01'),
(887, 95, 'logout', '{\"timestamp\":\"2025-10-01 20:02:29\"}', '::1', '2025-10-01 12:02:29'),
(888, 96, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-10-01 12:02:55'),
(889, 96, 'logout', '{\"timestamp\":\"2025-10-01 20:16:09\"}', '::1', '2025-10-01 12:16:09'),
(890, 97, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-10-01 12:16:27'),
(891, 94, 'logout', '{\"timestamp\":\"2025-10-01 20:17:51\"}', '::1', '2025-10-01 12:17:51'),
(892, 93, 'login', '{\"success\":true}', '::1', '2025-10-01 12:17:59'),
(893, 93, 'logout', '{\"timestamp\":\"2025-10-01 20:19:42\"}', '::1', '2025-10-01 12:19:42'),
(894, 95, 'login', '{\"success\":true}', '::1', '2025-10-01 12:19:48'),
(895, 97, 'logout', '{\"timestamp\":\"2025-10-01 20:23:05\"}', '::1', '2025-10-01 12:23:05'),
(896, 98, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-10-01 12:23:51'),
(897, 98, 'logout', '{\"timestamp\":\"2025-10-01 20:25:42\"}', '::1', '2025-10-01 12:25:42'),
(898, 93, 'login', '{\"success\":true}', '::1', '2025-10-01 12:25:47'),
(899, 95, 'logout', '{\"timestamp\":\"2025-10-01 20:40:39\"}', '::1', '2025-10-01 12:40:39'),
(900, 94, 'login', '{\"success\":true}', '::1', '2025-10-01 12:40:44'),
(901, 93, 'message_sent', '{\"match_id\":78,\"partner_id\":94}', '::1', '2025-10-01 12:41:09'),
(902, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 12:41:17'),
(903, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 12:41:33'),
(904, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 12:43:35'),
(905, 94, 'message_sent', '{\"match_id\":78,\"partner_id\":93}', '::1', '2025-10-01 12:43:38'),
(906, 94, 'logout', '{\"timestamp\":\"2025-10-01 20:49:24\"}', '::1', '2025-10-01 12:49:24'),
(907, 99, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-01 12:49:52'),
(908, 99, 'logout', '{\"timestamp\":\"2025-10-01 20:58:32\"}', '::1', '2025-10-01 12:58:32'),
(909, 1, 'login', '{\"success\":true}', '::1', '2025-10-01 12:58:35'),
(910, 1, 'logout', '{\"timestamp\":\"2025-10-01 21:01:21\"}', '::1', '2025-10-01 13:01:21'),
(911, 40, 'login', '{\"success\":true}', '::1', '2025-10-01 13:09:50'),
(912, 68, 'login', '{\"success\":true}', '::1', '2025-10-02 11:54:56'),
(913, 68, 'logout', '{\"timestamp\":\"2025-10-02 20:16:50\"}', '::1', '2025-10-02 12:16:50'),
(914, 100, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 12:17:30'),
(915, 100, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068122925861006,\"lng\":121.13102178733715}}', '::1', '2025-10-02 12:17:53'),
(916, 100, 'logout', '{\"timestamp\":\"2025-10-02 20:19:22\"}', '::1', '2025-10-02 12:19:22'),
(917, 101, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 12:19:53'),
(918, 101, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068089255952923,\"lng\":121.13110394603594}}', '::1', '2025-10-02 12:20:25'),
(919, 101, 'logout', '{\"timestamp\":\"2025-10-02 20:26:37\"}', '::1', '2025-10-02 12:26:37'),
(920, 102, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 12:27:03'),
(921, 102, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068089255952923,\"lng\":121.13110394603594}}', '::1', '2025-10-02 12:27:43'),
(922, 102, 'peer_application', '{\"status\":\"submitted\"}', '::1', '2025-10-02 12:28:06'),
(923, 102, 'logout', '{\"timestamp\":\"2025-10-02 20:28:48\"}', '::1', '2025-10-02 12:28:48'),
(924, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 12:28:51'),
(925, 1, 'admin_verify_user', '{\"verified_user_id\":102}', '::1', '2025-10-02 12:29:06'),
(926, 1, 'logout', '{\"timestamp\":\"2025-10-02 20:29:08\"}', '::1', '2025-10-02 12:29:08'),
(927, 102, 'login', '{\"success\":true}', '::1', '2025-10-02 12:29:13'),
(928, 102, 'logout', '{\"timestamp\":\"2025-10-02 20:29:31\"}', '::1', '2025-10-02 12:29:31'),
(929, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 12:29:34'),
(930, 1, 'admin_unverify_user', '{\"unverified_user_id\":102}', '::1', '2025-10-02 12:29:38'),
(931, 1, 'logout', '{\"timestamp\":\"2025-10-02 20:33:51\"}', '::1', '2025-10-02 12:33:51'),
(932, 102, 'login', '{\"success\":true}', '::1', '2025-10-02 12:34:00'),
(933, 102, 'logout', '{\"timestamp\":\"2025-10-02 20:39:53\"}', '::1', '2025-10-02 12:39:53'),
(934, 103, 'register', '{\"role\":\"mentor\"}', '::1', '2025-10-02 12:40:19'),
(935, 103, 'logout', '{\"timestamp\":\"2025-10-02 20:42:27\"}', '::1', '2025-10-02 12:42:27'),
(936, 104, 'register', '{\"role\":\"student\"}', '::1', '2025-10-02 12:42:53'),
(937, 104, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068188699434359,\"lng\":121.13113177196423}}', '::1', '2025-10-02 12:43:15'),
(938, 104, 'logout', '{\"timestamp\":\"2025-10-02 20:43:52\"}', '::1', '2025-10-02 12:43:52'),
(939, 105, 'register', '{\"role\":\"mentor\"}', '::1', '2025-10-02 12:44:18'),
(940, 105, 'logout', '{\"timestamp\":\"2025-10-02 20:45:28\"}', '::1', '2025-10-02 12:45:28'),
(941, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 12:45:29'),
(942, 1, 'admin_verify_user', '{\"verified_user_id\":105}', '::1', '2025-10-02 12:45:43'),
(943, 1, 'logout', '{\"timestamp\":\"2025-10-02 20:45:44\"}', '::1', '2025-10-02 12:45:44'),
(944, 105, 'login', '{\"success\":true}', '::1', '2025-10-02 12:45:50'),
(945, 105, 'logout', '{\"timestamp\":\"2025-10-02 20:46:13\"}', '::1', '2025-10-02 12:46:13'),
(946, 104, 'login', '{\"success\":true}', '::1', '2025-10-02 12:46:21'),
(947, 104, 'peer_application', '{\"status\":\"submitted\",\"referral_code\":\"MENTORE1F754\",\"mentor_id\":105}', '::1', '2025-10-02 12:46:33'),
(948, 104, 'logout', '{\"timestamp\":\"2025-10-02 20:48:08\"}', '::1', '2025-10-02 12:48:08'),
(949, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 12:48:09'),
(950, 1, 'logout', '{\"timestamp\":\"2025-10-02 20:52:26\"}', '::1', '2025-10-02 12:52:26'),
(951, 106, 'register', '{\"role\":\"mentor\"}', '::1', '2025-10-02 12:52:48'),
(952, 106, 'logout', '{\"timestamp\":\"2025-10-02 21:01:13\"}', '::1', '2025-10-02 13:01:13'),
(953, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 13:01:15'),
(954, 1, 'logout', '{\"timestamp\":\"2025-10-02 21:09:27\"}', '::1', '2025-10-02 13:09:27'),
(955, 107, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 13:10:13'),
(956, 107, 'logout', '{\"timestamp\":\"2025-10-02 21:10:23\"}', '::1', '2025-10-02 13:10:23'),
(957, 108, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 13:10:52'),
(958, 108, 'logout', '{\"timestamp\":\"2025-10-02 21:11:00\"}', '::1', '2025-10-02 13:11:00'),
(959, 109, 'register', '{\"role\":\"peer\",\"referral_used\":false}', '::1', '2025-10-02 13:11:35'),
(960, 109, 'logout', '{\"timestamp\":\"2025-10-02 21:11:45\"}', '::1', '2025-10-02 13:11:45'),
(961, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 13:16:12'),
(962, 1, 'logout', '{\"timestamp\":\"2025-10-02 21:18:41\"}', '::1', '2025-10-02 13:18:41'),
(963, 40, 'login', '{\"success\":true}', '::1', '2025-10-02 13:20:38'),
(964, 40, 'logout', '{\"timestamp\":\"2025-10-02 21:22:45\"}', '::1', '2025-10-02 13:22:45'),
(965, 110, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 13:24:12'),
(966, 110, 'logout', '{\"timestamp\":\"2025-10-02 21:26:54\"}', '::1', '2025-10-02 13:26:54'),
(967, 111, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 13:55:33'),
(968, 111, 'logout', '{\"timestamp\":\"2025-10-02 22:04:00\"}', '::1', '2025-10-02 14:04:00'),
(969, 112, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 14:19:38'),
(970, 112, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068122925861006,\"lng\":121.13102178733715}}', '::1', '2025-10-02 14:20:01'),
(971, 112, 'logout', '{\"timestamp\":\"2025-10-02 22:29:10\"}', '::1', '2025-10-02 14:29:10'),
(972, 113, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 14:29:30'),
(973, 113, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068183546339663,\"lng\":121.13103874994816}}', '::1', '2025-10-02 14:29:52'),
(974, 113, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06818355,\"lng\":121.13103875}}', '::1', '2025-10-02 14:30:32'),
(975, 113, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06818355,\"lng\":121.13103875}}', '::1', '2025-10-02 14:30:48'),
(976, 113, 'logout', '{\"timestamp\":\"2025-10-02 22:31:09\"}', '::1', '2025-10-02 14:31:09'),
(977, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 14:31:11'),
(978, 1, 'logout', '{\"timestamp\":\"2025-10-02 22:31:28\"}', '::1', '2025-10-02 14:31:28'),
(979, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 14:31:33'),
(980, 113, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORE1F754\",\"referral_code_id\":6,\"referred_by\":105}', '::1', '2025-10-02 14:31:41'),
(981, 113, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06818355,\"lng\":121.13103875}}', '::1', '2025-10-02 14:32:22'),
(982, 113, 'logout', '{\"timestamp\":\"2025-10-02 22:33:13\"}', '::1', '2025-10-02 14:33:13'),
(983, 114, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 14:33:35'),
(984, 114, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORE1F754\",\"referral_code_id\":6,\"referred_by\":105}', '::1', '2025-10-02 14:34:05'),
(985, 114, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORE1F754\",\"referral_code_id\":6,\"referred_by\":105}', '::1', '2025-10-02 14:36:45'),
(986, 114, 'logout', '{\"timestamp\":\"2025-10-02 22:37:08\"}', '::1', '2025-10-02 14:37:08'),
(987, 114, 'login', '{\"success\":true}', '::1', '2025-10-02 14:37:36'),
(988, 114, 'logout', '{\"timestamp\":\"2025-10-02 22:38:45\"}', '::1', '2025-10-02 14:38:45'),
(989, 115, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 14:39:03'),
(990, 115, 'logout', '{\"timestamp\":\"2025-10-02 22:39:44\"}', '::1', '2025-10-02 14:39:44'),
(991, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 14:39:45'),
(992, 1, 'admin_verify_user', '{\"verified_user_id\":115}', '::1', '2025-10-02 14:39:51'),
(993, 1, 'logout', '{\"timestamp\":\"2025-10-02 22:39:53\"}', '::1', '2025-10-02 14:39:53'),
(994, 115, 'login', '{\"success\":true}', '::1', '2025-10-02 14:40:02');
INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(995, 115, 'logout', '{\"timestamp\":\"2025-10-02 22:41:33\"}', '::1', '2025-10-02 14:41:33'),
(996, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 14:46:01'),
(997, 113, 'logout', '{\"timestamp\":\"2025-10-02 22:53:56\"}', '::1', '2025-10-02 14:53:56'),
(998, 116, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 14:54:22'),
(999, 116, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068122925861006,\"lng\":121.13102178733715}}', '::1', '2025-10-02 14:57:27'),
(1000, 116, 'logout', '{\"timestamp\":\"2025-10-02 22:59:19\"}', '::1', '2025-10-02 14:59:19'),
(1001, 115, 'login', '{\"success\":true}', '::1', '2025-10-02 14:59:26'),
(1002, 115, 'logout', '{\"timestamp\":\"2025-10-02 22:59:28\"}', '::1', '2025-10-02 14:59:28'),
(1003, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 14:59:37'),
(1004, 113, 'match_request', '{\"match_id\":\"79\",\"mentor_id\":79,\"subject\":\"C++\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-02 15:09:19'),
(1005, 113, 'match_request_pending', '{\"match_id\":\"79\",\"mentor_id\":79,\"delivery_method\":\"pending\"}', '::1', '2025-10-02 15:09:19'),
(1006, 113, 'logout', '{\"timestamp\":\"2025-10-02 23:09:21\"}', '::1', '2025-10-02 15:09:21'),
(1007, 78, 'login', '{\"success\":true}', '::1', '2025-10-02 15:09:27'),
(1008, 78, 'logout', '{\"timestamp\":\"2025-10-02 23:09:55\"}', '::1', '2025-10-02 15:09:55'),
(1009, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 15:10:04'),
(1010, 113, 'logout', '{\"timestamp\":\"2025-10-02 23:10:19\"}', '::1', '2025-10-02 15:10:19'),
(1011, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 15:10:21'),
(1012, 1, 'logout', '{\"timestamp\":\"2025-10-02 23:10:53\"}', '::1', '2025-10-02 15:10:53'),
(1013, 79, 'login', '{\"success\":true}', '::1', '2025-10-02 15:10:57'),
(1014, 79, 'logout', '{\"timestamp\":\"2025-10-02 23:13:17\"}', '::1', '2025-10-02 15:13:17'),
(1015, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 15:13:22'),
(1016, 113, 'logout', '{\"timestamp\":\"2025-10-02 23:16:29\"}', '::1', '2025-10-02 15:16:29'),
(1017, 117, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-02 15:16:51'),
(1018, 117, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068138677469678,\"lng\":121.13106725173257}}', '::1', '2025-10-02 15:17:53'),
(1019, 117, 'logout', '{\"timestamp\":\"2025-10-02 23:19:33\"}', '::1', '2025-10-02 15:19:33'),
(1020, 79, 'login', '{\"success\":true}', '::1', '2025-10-02 15:19:40'),
(1021, 79, 'logout', '{\"timestamp\":\"2025-10-02 23:19:57\"}', '::1', '2025-10-02 15:19:57'),
(1022, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 15:20:04'),
(1023, 113, 'logout', '{\"timestamp\":\"2025-10-02 23:20:39\"}', '::1', '2025-10-02 15:20:39'),
(1024, 79, 'login', '{\"success\":true}', '::1', '2025-10-02 15:20:45'),
(1025, 79, 'match_response', '{\"match_id\":79,\"response\":\"accepted\"}', '::1', '2025-10-02 15:25:43'),
(1026, 79, 'logout', '{\"timestamp\":\"2025-10-02 23:25:46\"}', '::1', '2025-10-02 15:25:46'),
(1027, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 15:25:51'),
(1028, 113, 'logout', '{\"timestamp\":\"2025-10-02 23:31:02\"}', '::1', '2025-10-02 15:31:02'),
(1029, 118, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-02 15:31:17'),
(1030, 118, 'logout', '{\"timestamp\":\"2025-10-02 23:34:35\"}', '::1', '2025-10-02 15:34:35'),
(1031, 113, 'login', '{\"success\":true}', '::1', '2025-10-02 15:34:40'),
(1032, 113, 'match_request', '{\"match_id\":\"80\",\"mentor_id\":92,\"subject\":\"C++\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-02 15:35:23'),
(1033, 113, 'match_request_pending', '{\"match_id\":\"80\",\"mentor_id\":92,\"delivery_method\":\"pending\"}', '::1', '2025-10-02 15:35:23'),
(1034, 113, 'session_scheduled', '{\"match_id\":79,\"date\":\"2025-10-02\"}', '::1', '2025-10-02 15:36:38'),
(1035, 113, 'session_completed', '{\"session_id\":17}', '::1', '2025-10-02 15:37:01'),
(1036, 113, 'logout', '{\"timestamp\":\"2025-10-02 23:40:04\"}', '::1', '2025-10-02 15:40:04'),
(1037, 1, 'login', '{\"success\":true}', '::1', '2025-10-02 15:40:06'),
(1038, 1, 'logout', '{\"timestamp\":\"2025-10-03 08:02:52\"}', '::1', '2025-10-03 00:02:52'),
(1039, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 00:12:28'),
(1040, 1, 'logout', '{\"timestamp\":\"2025-10-03 08:17:35\"}', '::1', '2025-10-03 00:17:35'),
(1041, 40, 'login', '{\"success\":true}', '::1', '2025-10-03 01:02:46'),
(1042, 40, 'logout', '{\"timestamp\":\"2025-10-03 09:29:07\"}', '::1', '2025-10-03 01:29:07'),
(1043, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 01:29:12'),
(1044, 1, 'logout', '{\"timestamp\":\"2025-10-03 09:29:51\"}', '::1', '2025-10-03 01:29:51'),
(1045, 40, 'login', '{\"success\":true}', '::1', '2025-10-03 01:29:56'),
(1046, 40, 'session_completed', '{\"session_id\":12}', '::1', '2025-10-03 02:07:37'),
(1047, 40, 'session_rated', '{\"session_id\":12,\"rating\":5}', '::1', '2025-10-03 02:07:47'),
(1048, 40, 'logout', '{\"timestamp\":\"2025-10-03 10:37:26\"}', '::1', '2025-10-03 02:37:26'),
(1049, 119, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-03 02:37:48'),
(1050, 119, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068188699434359,\"lng\":121.13113177196423}}', '::1', '2025-10-03 02:38:10'),
(1051, 119, 'logout', '{\"timestamp\":\"2025-10-03 10:51:25\"}', '::1', '2025-10-03 02:51:25'),
(1052, 120, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-03 02:51:41'),
(1053, 120, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0684145,\"lng\":121.1309525}}', '::1', '2025-10-03 02:52:05'),
(1054, 120, 'logout', '{\"timestamp\":\"2025-10-03 11:05:45\"}', '::1', '2025-10-03 03:05:45'),
(1055, 68, 'login', '{\"success\":true}', '::1', '2025-10-03 03:05:52'),
(1056, 68, 'logout', '{\"timestamp\":\"2025-10-03 11:06:09\"}', '::1', '2025-10-03 03:06:09'),
(1057, 70, 'login', '{\"success\":true}', '::1', '2025-10-03 03:06:15'),
(1058, 70, 'logout', '{\"timestamp\":\"2025-10-03 11:06:25\"}', '::1', '2025-10-03 03:06:25'),
(1059, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 03:06:43'),
(1060, 1, 'logout', '{\"timestamp\":\"2025-10-03 11:06:51\"}', '::1', '2025-10-03 03:06:51'),
(1061, 119, 'login', '{\"success\":true}', '::1', '2025-10-03 03:06:56'),
(1062, 119, 'match_request', '{\"match_id\":\"81\",\"mentor_id\":117,\"subject\":\"Algebra\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-03 03:24:27'),
(1063, 119, 'match_request_pending', '{\"match_id\":\"81\",\"mentor_id\":117,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 03:24:27'),
(1064, 119, 'match_request', '{\"match_id\":\"82\",\"mentor_id\":117,\"subject\":\"Algebra\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-03 03:25:20'),
(1065, 119, 'match_request_pending', '{\"match_id\":\"82\",\"mentor_id\":117,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 03:25:20'),
(1066, 119, 'match_request', '{\"match_id\":\"83\",\"mentor_id\":83,\"subject\":\"Algebra\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-10-03 03:25:25'),
(1067, 119, 'match_request_pending', '{\"match_id\":\"83\",\"mentor_id\":83,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 03:25:25'),
(1068, 119, 'logout', '{\"timestamp\":\"2025-10-03 11:35:46\"}', '::1', '2025-10-03 03:35:46'),
(1069, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 03:45:06'),
(1070, 1, 'logout', '{\"timestamp\":\"2025-10-03 20:01:55\"}', '::1', '2025-10-03 12:01:55'),
(1071, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 12:03:41'),
(1072, 1, 'logout', '{\"timestamp\":\"2025-10-03 20:09:40\"}', '::1', '2025-10-03 12:09:40'),
(1073, 121, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-03 12:11:16'),
(1074, 121, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068204279224206,\"lng\":121.1309006044947}}', '::1', '2025-10-03 12:11:54'),
(1075, 122, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-03 12:12:20'),
(1076, 122, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068204279224206,\"lng\":121.1309006044947}}', '::1', '2025-10-03 12:13:04'),
(1077, 122, 'match_request', '{\"match_id\":\"84\",\"mentor_id\":102,\"subject\":\"Calculus\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-03 12:16:41'),
(1078, 122, 'match_request_pending', '{\"match_id\":\"84\",\"mentor_id\":102,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 12:16:41'),
(1079, 122, 'logout', '{\"timestamp\":\"2025-10-03 20:17:05\"}', '::1', '2025-10-03 12:17:05'),
(1080, 102, 'login', '{\"success\":true}', '::1', '2025-10-03 12:17:12'),
(1081, 102, 'match_request', '{\"match_id\":\"85\",\"mentor_id\":93,\"subject\":\"Calculus\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-10-03 12:19:25'),
(1082, 102, 'match_request_pending', '{\"match_id\":\"85\",\"mentor_id\":93,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 12:19:25'),
(1083, 102, 'logout', '{\"timestamp\":\"2025-10-03 20:19:26\"}', '::1', '2025-10-03 12:19:26'),
(1084, 102, 'login', '{\"success\":true}', '::1', '2025-10-03 12:19:35'),
(1085, 121, 'logout', '{\"timestamp\":\"2025-10-03 20:19:41\"}', '::1', '2025-10-03 12:19:41'),
(1086, 93, 'login', '{\"success\":true}', '::1', '2025-10-03 12:19:46'),
(1087, 93, 'match_response', '{\"match_id\":85,\"response\":\"rejected\"}', '::1', '2025-10-03 12:20:11'),
(1088, 102, 'match_response', '{\"match_id\":84,\"response\":\"rejected\"}', '::1', '2025-10-03 12:20:44'),
(1089, 93, 'match_request', '{\"match_id\":\"86\",\"mentor_id\":91,\"subject\":\"Algebra\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-03 12:23:54'),
(1090, 93, 'match_request_pending', '{\"match_id\":\"86\",\"mentor_id\":91,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 12:23:54'),
(1091, 93, 'logout', '{\"timestamp\":\"2025-10-03 20:23:55\"}', '::1', '2025-10-03 12:23:55'),
(1092, 102, 'logout', '{\"timestamp\":\"2025-10-03 20:23:58\"}', '::1', '2025-10-03 12:23:58'),
(1093, 91, 'login', '{\"success\":true}', '::1', '2025-10-03 12:24:04'),
(1094, 91, 'match_response', '{\"match_id\":86,\"response\":\"accepted\"}', '::1', '2025-10-03 12:29:41'),
(1095, 93, 'login', '{\"success\":true}', '::1', '2025-10-03 12:29:49'),
(1096, 93, 'session_scheduled', '{\"match_id\":86,\"date\":\"2025-10-04\"}', '::1', '2025-10-03 12:30:56'),
(1097, 93, 'logout', '{\"timestamp\":\"2025-10-03 20:33:00\"}', '::1', '2025-10-03 12:33:00'),
(1098, 123, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-03 12:33:19'),
(1099, 123, 'match_request', '{\"match_id\":\"87\",\"mentor_id\":91,\"subject\":\"Web Development\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-10-03 12:35:08'),
(1100, 123, 'match_request_pending', '{\"match_id\":\"87\",\"mentor_id\":91,\"delivery_method\":\"pending\"}', '::1', '2025-10-03 12:35:08'),
(1101, 91, 'match_response', '{\"match_id\":87,\"response\":\"accepted\"}', '::1', '2025-10-03 12:35:22'),
(1102, 91, 'session_scheduled', '{\"match_id\":87,\"date\":\"2025-10-04\"}', '::1', '2025-10-03 12:35:48'),
(1103, 123, 'session_completed', '{\"session_id\":19}', '::1', '2025-10-03 12:38:26'),
(1104, 91, 'session_rated', '{\"session_id\":19,\"rating\":5}', '::1', '2025-10-03 12:39:01'),
(1105, 123, 'session_rated', '{\"session_id\":19,\"rating\":4}', '::1', '2025-10-03 12:39:11'),
(1106, 91, 'logout', '{\"timestamp\":\"2025-10-03 20:39:43\"}', '::1', '2025-10-03 12:39:43'),
(1107, 124, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-03 12:42:50'),
(1108, 124, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0684145,\"lng\":121.1309525}}', '::1', '2025-10-03 12:43:50'),
(1109, 124, 'logout', '{\"timestamp\":\"2025-10-03 20:52:39\"}', '::1', '2025-10-03 12:52:39'),
(1110, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 12:52:41'),
(1111, 1, 'admin_verify_user', '{\"verified_user_id\":122}', '::1', '2025-10-03 12:53:26'),
(1112, 1, 'logout', '{\"timestamp\":\"2025-10-03 20:53:30\"}', '::1', '2025-10-03 12:53:30'),
(1113, 122, 'login', '{\"success\":true}', '::1', '2025-10-03 12:53:37'),
(1114, 122, 'logout', '{\"timestamp\":\"2025-10-03 20:55:09\"}', '::1', '2025-10-03 12:55:09'),
(1115, 1, 'login', '{\"success\":true}', '::1', '2025-10-03 12:55:11'),
(1116, 1, 'logout', '{\"timestamp\":\"2025-10-03 21:11:27\"}', '::1', '2025-10-03 13:11:27'),
(1117, 1, 'login', '{\"success\":true}', '::1', '2025-10-04 03:40:40'),
(1118, 1, 'logout', '{\"timestamp\":\"2025-10-04 11:41:48\"}', '::1', '2025-10-04 03:41:48'),
(1119, 125, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-04 03:43:08'),
(1120, 125, 'match_request', '{\"match_id\":\"88\",\"mentor_id\":79,\"subject\":\"Mathematics - Calculus\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-10-04 03:45:30'),
(1121, 125, 'match_request_pending', '{\"match_id\":\"88\",\"mentor_id\":79,\"delivery_method\":\"pending\"}', '::1', '2025-10-04 03:45:30'),
(1122, 125, 'logout', '{\"timestamp\":\"2025-10-04 11:46:37\"}', '::1', '2025-10-04 03:46:37'),
(1123, 78, 'login', '{\"success\":true}', '::1', '2025-10-04 03:46:54'),
(1124, 78, 'logout', '{\"timestamp\":\"2025-10-04 11:47:10\"}', '::1', '2025-10-04 03:47:10'),
(1125, 78, 'login', '{\"success\":true}', '::1', '2025-10-04 03:47:24'),
(1126, 78, 'logout', '{\"timestamp\":\"2025-10-04 11:47:28\"}', '::1', '2025-10-04 03:47:28'),
(1127, 79, 'login', '{\"success\":true}', '::1', '2025-10-04 03:47:37'),
(1128, 79, 'match_response', '{\"match_id\":88,\"response\":\"accepted\"}', '::1', '2025-10-04 03:48:16'),
(1129, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 03:49:21'),
(1130, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 03:49:31'),
(1131, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 06:01:25'),
(1132, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 06:01:27'),
(1133, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 06:01:31'),
(1134, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 06:01:32'),
(1135, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 06:01:33'),
(1136, 79, 'message_sent', '{\"match_id\":88,\"partner_id\":125}', '::1', '2025-10-04 06:01:35'),
(1137, 79, 'match_request', '{\"match_id\":\"89\",\"mentor_id\":76,\"subject\":\"C++\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-04 06:02:39'),
(1138, 79, 'match_request_pending', '{\"match_id\":\"89\",\"mentor_id\":76,\"delivery_method\":\"pending\"}', '::1', '2025-10-04 06:02:39'),
(1139, 79, 'logout', '{\"timestamp\":\"2025-10-04 16:42:16\"}', '::1', '2025-10-04 08:42:16'),
(1140, 1, 'login', '{\"success\":true}', '::1', '2025-10-04 09:01:13'),
(1141, 1, 'logout', '{\"timestamp\":\"2025-10-04 17:01:31\"}', '::1', '2025-10-04 09:01:31'),
(1142, 1, 'login', '{\"success\":true}', '::1', '2025-10-04 09:01:33'),
(1143, 1, 'logout', '{\"timestamp\":\"2025-10-04 17:01:54\"}', '::1', '2025-10-04 09:01:54'),
(1144, 126, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-04 09:53:10'),
(1145, 126, 'logout', '{\"timestamp\":\"2025-10-04 20:07:26\"}', '::1', '2025-10-04 12:07:26'),
(1146, 127, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-04 12:07:57'),
(1147, 127, 'logout', '{\"timestamp\":\"2025-10-04 20:24:57\"}', '::1', '2025-10-04 12:24:57'),
(1148, 78, 'login', '{\"success\":true}', '::1', '2025-10-04 12:25:38'),
(1149, 78, 'match_request', '{\"match_id\":\"90\",\"mentor_id\":119,\"subject\":\"Programming - C++\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-04 12:25:45'),
(1150, 78, 'match_request_pending', '{\"match_id\":\"90\",\"mentor_id\":119,\"delivery_method\":\"pending\"}', '::1', '2025-10-04 12:25:45'),
(1151, 78, 'logout', '{\"timestamp\":\"2025-10-04 20:25:49\"}', '::1', '2025-10-04 12:25:49'),
(1152, 119, 'login', '{\"success\":true}', '::1', '2025-10-04 12:25:56'),
(1153, 119, 'match_response', '{\"match_id\":90,\"response\":\"accepted\"}', '::1', '2025-10-04 12:26:02'),
(1154, 119, 'session_scheduled', '{\"match_id\":90,\"date\":\"2025-10-04\"}', '::1', '2025-10-04 12:26:35'),
(1155, 119, 'message_sent', '{\"match_id\":90,\"partner_id\":78}', '::1', '2025-10-04 12:28:23'),
(1156, 119, 'message_sent', '{\"match_id\":90,\"partner_id\":78}', '::1', '2025-10-04 12:28:26'),
(1157, 119, 'message_sent', '{\"match_id\":90,\"partner_id\":78}', '::1', '2025-10-04 12:28:29'),
(1158, 119, 'message_sent', '{\"match_id\":90,\"partner_id\":78}', '::1', '2025-10-04 12:28:38'),
(1159, 119, 'session_completed', '{\"session_id\":20}', '::1', '2025-10-04 12:30:30'),
(1160, 119, 'session_scheduled', '{\"match_id\":90,\"date\":\"2025-10-05\"}', '::1', '2025-10-04 12:38:36'),
(1161, 119, 'logout', '{\"timestamp\":\"2025-10-04 20:41:10\"}', '::1', '2025-10-04 12:41:10'),
(1162, 83, 'login', '{\"success\":true}', '::1', '2025-10-04 12:41:17'),
(1163, 83, 'match_response', '{\"match_id\":83,\"response\":\"accepted\"}', '::1', '2025-10-04 12:41:22'),
(1164, 83, 'session_scheduled', '{\"match_id\":83,\"date\":\"2025-10-05\"}', '::1', '2025-10-04 12:41:39'),
(1165, 83, 'logout', '{\"timestamp\":\"2025-10-04 20:41:55\"}', '::1', '2025-10-04 12:41:55'),
(1166, 119, 'login', '{\"success\":true}', '::1', '2025-10-04 12:42:00'),
(1167, 119, 'session_scheduled', '{\"match_id\":83,\"date\":\"2025-10-04\"}', '::1', '2025-10-04 12:44:26'),
(1168, 119, 'logout', '{\"timestamp\":\"2025-10-04 20:46:32\"}', '::1', '2025-10-04 12:46:32'),
(1169, 83, 'login', '{\"success\":true}', '::1', '2025-10-04 12:46:39'),
(1170, 83, 'session_scheduled', '{\"match_id\":77,\"date\":\"2025-10-04\"}', '::1', '2025-10-04 13:05:06'),
(1171, 83, 'session_scheduled', '{\"match_id\":77,\"date\":\"2025-10-04\"}', '::1', '2025-10-04 13:05:58'),
(1172, 83, 'logout', '{\"timestamp\":\"2025-10-04 21:07:10\"}', '::1', '2025-10-04 13:07:10'),
(1173, 119, 'login', '{\"success\":true}', '::1', '2025-10-04 13:07:22'),
(1174, 119, 'session_cancelled', '{\"session_id\":21,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-04 13:26:57'),
(1175, 119, 'session_cancelled', '{\"session_id\":22,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-04 13:33:04'),
(1176, 119, 'logout', '{\"timestamp\":\"2025-10-05 13:56:36\"}', '::1', '2025-10-05 05:56:36'),
(1177, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 05:56:40'),
(1178, 1, 'logout', '{\"timestamp\":\"2025-10-05 13:57:41\"}', '::1', '2025-10-05 05:57:41'),
(1179, 38, 'login', '{\"success\":true}', '::1', '2025-10-05 05:57:46'),
(1180, 38, 'logout', '{\"timestamp\":\"2025-10-05 13:57:50\"}', '::1', '2025-10-05 05:57:50'),
(1181, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 05:58:04'),
(1182, 1, 'logout', '{\"timestamp\":\"2025-10-05 13:58:14\"}', '::1', '2025-10-05 05:58:14'),
(1183, 127, 'login', '{\"success\":true}', '::1', '2025-10-05 05:58:27'),
(1184, 127, 'logout', '{\"timestamp\":\"2025-10-05 13:58:39\"}', '::1', '2025-10-05 05:58:39'),
(1185, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 05:58:41'),
(1186, 1, 'admin_verify_user', '{\"verified_user_id\":127}', '::1', '2025-10-05 05:58:46'),
(1187, 1, 'logout', '{\"timestamp\":\"2025-10-05 13:58:47\"}', '::1', '2025-10-05 05:58:47'),
(1188, 127, 'login', '{\"success\":true}', '::1', '2025-10-05 05:58:53'),
(1189, 127, 'match_request', '{\"match_id\":\"91\",\"mentor_id\":113,\"subject\":\"Mathematics - Calculus\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-10-05 06:03:14'),
(1190, 127, 'match_request_pending', '{\"match_id\":\"91\",\"mentor_id\":113,\"delivery_method\":\"pending\"}', '::1', '2025-10-05 06:03:14'),
(1191, 127, 'logout', '{\"timestamp\":\"2025-10-05 14:03:24\"}', '::1', '2025-10-05 06:03:24'),
(1192, 113, 'login', '{\"success\":true}', '::1', '2025-10-05 06:03:31'),
(1193, 113, 'logout', '{\"timestamp\":\"2025-10-05 14:19:26\"}', '::1', '2025-10-05 06:19:26'),
(1194, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 06:19:28'),
(1195, 1, 'logout', '{\"timestamp\":\"2025-10-05 14:19:31\"}', '::1', '2025-10-05 06:19:31'),
(1196, 128, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-05 06:20:45'),
(1197, 128, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068188699434359,\"lng\":121.13113177196423}}', '::1', '2025-10-05 06:21:17'),
(1198, 40, 'login', '{\"success\":true}', '::1', '2025-10-05 08:59:05'),
(1199, 40, 'session_scheduled', '{\"match_id\":42,\"date\":\"2025-10-05\"}', '::1', '2025-10-05 08:59:36'),
(1200, 40, 'session_completed', '{\"session_id\":26}', '::1', '2025-10-05 09:00:49'),
(1201, 40, 'session_scheduled', '{\"match_id\":42,\"date\":\"2025-10-05\"}', '::1', '2025-10-05 09:01:08'),
(1202, 40, 'match_request', '{\"match_id\":\"92\",\"mentor_id\":50,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-05 10:01:09'),
(1203, 40, 'match_request_pending', '{\"match_id\":\"92\",\"mentor_id\":50,\"delivery_method\":\"pending\"}', '::1', '2025-10-05 10:01:09'),
(1204, 40, 'match_request', '{\"match_id\":\"93\",\"mentor_id\":50,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-05 10:01:51'),
(1205, 40, 'match_request_pending', '{\"match_id\":\"93\",\"mentor_id\":50,\"delivery_method\":\"pending\"}', '::1', '2025-10-05 10:01:51'),
(1206, 40, 'logout', '{\"timestamp\":\"2025-10-05 18:09:56\"}', '::1', '2025-10-05 10:09:56'),
(1207, 129, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-05 10:10:10'),
(1208, 129, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683466,\"lng\":121.1312511}}', '::1', '2025-10-05 10:11:21'),
(1209, 129, 'logout', '{\"timestamp\":\"2025-10-05 18:11:36\"}', '::1', '2025-10-05 10:11:36'),
(1210, 130, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-05 10:12:14'),
(1211, 130, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683461,\"lng\":121.131257}}', '::1', '2025-10-05 10:12:49'),
(1212, 130, 'logout', '{\"timestamp\":\"2025-10-05 18:13:32\"}', '::1', '2025-10-05 10:13:32'),
(1213, 40, 'login', '{\"success\":true}', '::1', '2025-10-05 10:13:46'),
(1214, 40, 'logout', '{\"timestamp\":\"2025-10-05 18:14:04\"}', '::1', '2025-10-05 10:14:04'),
(1215, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 10:14:05'),
(1216, 1, 'admin_verify_user', '{\"verified_user_id\":130}', '::1', '2025-10-05 10:14:10'),
(1217, 1, 'logout', '{\"timestamp\":\"2025-10-05 18:14:13\"}', '::1', '2025-10-05 10:14:13'),
(1218, 130, 'login', '{\"success\":true}', '::1', '2025-10-05 10:14:22'),
(1219, 130, 'logout', '{\"timestamp\":\"2025-10-05 18:31:11\"}', '::1', '2025-10-05 10:31:11'),
(1220, 87, 'login', '{\"success\":true}', '::1', '2025-10-05 10:31:25'),
(1221, 87, 'logout', '{\"timestamp\":\"2025-10-05 18:47:13\"}', '::1', '2025-10-05 10:47:13'),
(1222, 40, 'login', '{\"success\":true}', '::1', '2025-10-05 10:47:22'),
(1223, 40, 'session_scheduled', '{\"match_id\":42,\"date\":\"2025-10-05\"}', '::1', '2025-10-05 10:48:01'),
(1224, 40, 'logout', '{\"timestamp\":\"2025-10-05 18:49:01\"}', '::1', '2025-10-05 10:49:01'),
(1225, 82, 'login', '{\"success\":true}', '::1', '2025-10-05 10:49:15'),
(1226, 131, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-05 10:50:56'),
(1227, 131, 'logout', '{\"timestamp\":\"2025-10-05 18:51:04\"}', '::1', '2025-10-05 10:51:04'),
(1228, 132, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-05 10:51:44'),
(1229, 132, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0684145,\"lng\":121.1309525}}', '::1', '2025-10-05 10:52:27'),
(1230, 82, 'match_request', '{\"match_id\":\"94\",\"mentor_id\":132,\"subject\":\"Mathematics - Trigonometry\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-10-05 10:57:42'),
(1231, 82, 'match_request_pending', '{\"match_id\":\"94\",\"mentor_id\":132,\"delivery_method\":\"pending\"}', '::1', '2025-10-05 10:57:42'),
(1232, 132, 'match_response', '{\"match_id\":94,\"response\":\"accepted\"}', '::1', '2025-10-05 10:57:54'),
(1233, 82, 'message_sent', '{\"match_id\":94,\"partner_id\":132}', '::1', '2025-10-05 10:58:01'),
(1234, 132, 'message_sent', '{\"match_id\":94,\"partner_id\":82}', '::1', '2025-10-05 10:58:06'),
(1235, 82, 'message_sent', '{\"match_id\":94,\"partner_id\":132}', '::1', '2025-10-05 10:58:14'),
(1236, 82, 'session_scheduled', '{\"match_id\":77,\"date\":\"2025-10-05\"}', '::1', '2025-10-05 11:01:10'),
(1237, 132, 'session_scheduled', '{\"match_id\":94,\"date\":\"2025-10-05\"}', '::1', '2025-10-05 11:02:14'),
(1238, 132, 'session_cancelled', '{\"session_id\":30,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-05 11:09:47'),
(1239, 133, 'register', '{\"role\":\"student\",\"referral_used\":false}', '192.168.254.124', '2025-10-05 12:26:49'),
(1240, 82, 'logout', '{\"timestamp\":\"2025-10-05 20:50:30\"}', '127.0.0.1', '2025-10-05 12:50:30'),
(1241, 40, 'login', '{\"success\":true}', '192.168.254.100', '2025-10-05 12:50:53'),
(1242, 40, 'login', '{\"success\":true}', '127.0.0.1', '2025-10-05 12:54:16'),
(1243, 40, 'logout', '{\"timestamp\":\"2025-10-05 20:55:02\"}', '127.0.0.1', '2025-10-05 12:55:02'),
(1244, 1, 'login', '{\"success\":true}', '127.0.0.1', '2025-10-05 12:55:05'),
(1245, 1, 'logout', '{\"timestamp\":\"2025-10-05 21:45:05\"}', '::1', '2025-10-05 13:45:05'),
(1246, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 13:47:36'),
(1247, 1, 'logout', '{\"timestamp\":\"2025-10-05 21:57:23\"}', '::1', '2025-10-05 13:57:23'),
(1248, 1, 'login', '{\"success\":true}', '::1', '2025-10-05 14:00:25'),
(1249, 1, 'login', '{\"success\":true}', '::1', '2025-10-06 04:14:55'),
(1250, 1, 'logout', '{\"timestamp\":\"2025-10-06 12:23:07\"}', '::1', '2025-10-06 04:23:07'),
(1251, 1, 'login', '{\"success\":true}', '::1', '2025-10-06 04:23:56'),
(1252, 1, 'logout', '{\"timestamp\":\"2025-10-06 20:12:54\"}', '::1', '2025-10-06 12:12:54'),
(1253, 1, 'login', '{\"success\":true}', '::1', '2025-10-06 12:13:08'),
(1254, 1, 'logout', '{\"timestamp\":\"2025-10-06 20:27:22\"}', '::1', '2025-10-06 12:27:22'),
(1255, 40, 'login', '{\"success\":true}', '::1', '2025-10-06 12:27:58'),
(1256, 40, 'logout', '{\"timestamp\":\"2025-10-06 20:28:12\"}', '::1', '2025-10-06 12:28:12'),
(1257, 40, 'login', '{\"success\":true}', '::1', '2025-10-06 12:46:12'),
(1258, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-06 12:46:43'),
(1259, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-06 12:46:44'),
(1260, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-06 12:46:45'),
(1261, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-06 12:46:46'),
(1262, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-06 12:46:46'),
(1263, 40, 'logout', '{\"timestamp\":\"2025-10-06 20:59:50\"}', '::1', '2025-10-06 12:59:50'),
(1264, 40, 'login', '{\"success\":true}', '::1', '2025-10-06 13:00:34'),
(1265, 40, 'logout', '{\"timestamp\":\"2025-10-06 21:00:57\"}', '::1', '2025-10-06 13:00:57'),
(1266, 1, 'login', '{\"success\":true}', '::1', '2025-10-06 13:00:59'),
(1267, 1, 'logout', '{\"timestamp\":\"2025-10-06 21:03:07\"}', '::1', '2025-10-06 13:03:07'),
(1268, 40, 'login', '{\"success\":true}', '::1', '2025-10-06 13:03:36'),
(1269, 40, 'logout', '{\"timestamp\":\"2025-10-06 21:58:50\"}', '::1', '2025-10-06 13:58:50'),
(1270, 127, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 15:23:11'),
(1271, 127, 'logout', '{\"timestamp\":\"2025-10-06 23:23:35\"}', '::1', '2025-10-06 15:23:35'),
(1272, 127, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 15:30:59'),
(1273, 127, 'logout', '{\"timestamp\":\"2025-10-06 23:31:03\"}', '::1', '2025-10-06 15:31:03'),
(1274, 134, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-06 15:34:59'),
(1275, 134, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0642239,\"lng\":121.3232605}}', '::1', '2025-10-06 15:35:49'),
(1276, 134, 'logout', '{\"timestamp\":\"2025-10-06 23:36:11\"}', '::1', '2025-10-06 15:36:11'),
(1277, 134, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 15:36:58'),
(1278, 134, 'logout', '{\"timestamp\":\"2025-10-06 23:37:05\"}', '::1', '2025-10-06 15:37:05'),
(1279, 127, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 15:38:11'),
(1280, 127, 'logout', '{\"timestamp\":\"2025-10-06 23:38:23\"}', '::1', '2025-10-06 15:38:23'),
(1281, 135, 'register_otp', '{\"method\":\"otp\",\"role\":\"student\"}', '::1', '2025-10-06 15:42:07'),
(1282, 135, 'logout', '{\"timestamp\":\"2025-10-06 23:42:33\"}', '::1', '2025-10-06 15:42:33'),
(1283, 135, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 15:59:03'),
(1284, 135, 'logout', '{\"timestamp\":\"2025-10-06 23:59:17\"}', '::1', '2025-10-06 15:59:17'),
(1285, 136, 'register_otp', '{\"method\":\"otp\",\"role\":\"student\"}', '::1', '2025-10-06 16:05:50'),
(1286, 136, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0642239,\"lng\":121.3232605}}', '::1', '2025-10-06 16:06:13'),
(1287, 136, 'match_request', '{\"match_id\":\"95\",\"mentor_id\":134,\"subject\":\"Trigonometry\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-06 16:06:31'),
(1288, 136, 'match_request_pending', '{\"match_id\":\"95\",\"mentor_id\":134,\"delivery_method\":\"pending\"}', '::1', '2025-10-06 16:06:31'),
(1289, 136, 'logout', '{\"timestamp\":\"2025-10-07 00:06:32\"}', '::1', '2025-10-06 16:06:32'),
(1290, 1, 'login', '{\"success\":true}', '::1', '2025-10-06 16:06:41'),
(1291, 1, 'logout', '{\"timestamp\":\"2025-10-07 00:06:49\"}', '::1', '2025-10-06 16:06:49'),
(1292, 134, 'login', '{\"success\":true}', '::1', '2025-10-06 16:06:54'),
(1293, 134, 'logout', '{\"timestamp\":\"2025-10-07 00:08:09\"}', '::1', '2025-10-06 16:08:09'),
(1294, 1, 'login', '{\"success\":true}', '::1', '2025-10-06 16:09:28'),
(1295, 137, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-06 16:11:53'),
(1296, 137, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.09832664361692,\"lng\":121.06582753569089}}', '::1', '2025-10-06 16:12:24'),
(1297, 137, 'logout', '{\"timestamp\":\"2025-10-07 00:12:49\"}', '::1', '2025-10-06 16:12:49'),
(1298, 138, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-06 16:14:39'),
(1299, 138, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.098357148166519,\"lng\":121.06584433124084}}', '::1', '2025-10-06 16:15:07'),
(1300, 1, 'logout', '{\"timestamp\":\"2025-10-07 00:27:20\"}', '::1', '2025-10-06 16:27:20'),
(1301, 134, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 17:52:17'),
(1302, 134, 'logout', '{\"timestamp\":\"2025-10-07 01:52:19\"}', '::1', '2025-10-06 17:52:19'),
(1303, 127, 'login_otp', '{\"method\":\"otp\"}', '::1', '2025-10-06 17:56:08'),
(1304, 127, 'logout', '{\"timestamp\":\"2025-10-07 02:00:25\"}', '::1', '2025-10-06 18:00:25'),
(1305, 139, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-07 10:09:01'),
(1306, 139, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.352384,\"lng\":120.9761792}}', '::1', '2025-10-07 10:09:37'),
(1307, 139, 'match_request', '{\"match_id\":\"96\",\"mentor_id\":91,\"subject\":\"Algebra\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-10-07 10:10:51'),
(1308, 139, 'match_request_pending', '{\"match_id\":\"96\",\"mentor_id\":91,\"delivery_method\":\"pending\"}', '::1', '2025-10-07 10:10:51'),
(1309, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:10:52\"}', '::1', '2025-10-07 10:10:52'),
(1310, 91, 'login', '{\"success\":true}', '::1', '2025-10-07 10:11:01'),
(1311, 91, 'match_response', '{\"match_id\":96,\"response\":\"accepted\"}', '::1', '2025-10-07 10:11:10'),
(1312, 91, 'session_scheduled', '{\"match_id\":96,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 10:11:43'),
(1313, 91, 'logout', '{\"timestamp\":\"2025-10-07 18:11:51\"}', '::1', '2025-10-07 10:11:51'),
(1314, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 10:11:59'),
(1315, 139, 'session_cancelled', '{\"session_id\":31,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-07 10:19:52'),
(1316, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:19:54\"}', '::1', '2025-10-07 10:19:54'),
(1317, 91, 'login', '{\"success\":true}', '::1', '2025-10-07 10:19:59'),
(1318, 91, 'session_scheduled', '{\"match_id\":96,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 10:20:23'),
(1319, 91, 'logout', '{\"timestamp\":\"2025-10-07 18:20:28\"}', '::1', '2025-10-07 10:20:28'),
(1320, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 10:20:34'),
(1321, 139, 'session_cancelled', '{\"session_id\":32,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-07 10:33:12'),
(1322, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:33:14\"}', '::1', '2025-10-07 10:33:14'),
(1323, 91, 'login', '{\"success\":true}', '::1', '2025-10-07 10:33:22'),
(1324, 91, 'session_scheduled', '{\"match_id\":96,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 10:33:37'),
(1325, 91, 'logout', '{\"timestamp\":\"2025-10-07 18:33:41\"}', '::1', '2025-10-07 10:33:41'),
(1326, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 10:33:54'),
(1327, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:37:37\"}', '::1', '2025-10-07 10:37:37'),
(1328, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 10:37:48'),
(1329, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:37:50\"}', '::1', '2025-10-07 10:37:50'),
(1330, 140, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-07 10:39:47'),
(1331, 140, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683529,\"lng\":121.1312471}}', '::1', '2025-10-07 10:40:06'),
(1332, 140, 'match_request', '{\"match_id\":\"97\",\"mentor_id\":139,\"subject\":\"Algebra\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-07 10:40:29'),
(1333, 140, 'match_request_pending', '{\"match_id\":\"97\",\"mentor_id\":139,\"delivery_method\":\"pending\"}', '::1', '2025-10-07 10:40:29'),
(1334, 140, 'logout', '{\"timestamp\":\"2025-10-07 18:40:30\"}', '::1', '2025-10-07 10:40:30'),
(1335, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 10:40:43'),
(1336, 139, 'match_response', '{\"match_id\":97,\"response\":\"accepted\"}', '::1', '2025-10-07 10:40:47'),
(1337, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 10:41:36'),
(1338, 139, 'session_cancelled', '{\"session_id\":33,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-07 10:55:16'),
(1339, 139, 'session_cancelled', '{\"session_id\":34,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-07 10:55:19'),
(1340, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 10:55:35'),
(1341, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:55:39\"}', '::1', '2025-10-07 10:55:39'),
(1342, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 10:55:55'),
(1343, 139, 'logout', '{\"timestamp\":\"2025-10-07 18:56:05\"}', '::1', '2025-10-07 10:56:05'),
(1344, 139, 'login', '{\"success\":true}', '::1', '2025-10-07 11:05:20'),
(1345, 139, 'session_completed', '{\"session_id\":35}', '::1', '2025-10-07 11:05:30'),
(1346, 139, 'session_rated', '{\"session_id\":35,\"rating\":5}', '::1', '2025-10-07 11:05:38'),
(1347, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 11:08:39'),
(1348, 139, 'session_completed', '{\"session_id\":36}', '::1', '2025-10-07 11:16:07'),
(1349, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 11:16:22'),
(1350, 139, 'session_completed', '{\"session_id\":37}', '::1', '2025-10-07 11:18:06'),
(1351, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 11:18:22'),
(1352, 139, 'session_completed', '{\"session_id\":38}', '::1', '2025-10-07 11:20:15'),
(1353, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 11:20:29'),
(1354, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-07\"}', '::1', '2025-10-07 12:28:37'),
(1355, 139, 'logout', '{\"timestamp\":\"2025-10-07 20:30:36\"}', '::1', '2025-10-07 12:30:36'),
(1356, 1, 'login', '{\"success\":true}', '::1', '2025-10-07 12:30:38'),
(1357, 1, 'logout', '{\"timestamp\":\"2025-10-07 20:30:48\"}', '::1', '2025-10-07 12:30:48'),
(1358, 138, 'login', '{\"success\":true}', '::1', '2025-10-07 12:30:54'),
(1359, 138, 'logout', '{\"timestamp\":\"2025-10-07 20:50:46\"}', '::1', '2025-10-07 12:50:46'),
(1360, 1, 'login', '{\"success\":true}', '::1', '2025-10-07 12:50:57'),
(1361, 1, 'logout', '{\"timestamp\":\"2025-10-07 20:51:11\"}', '::1', '2025-10-07 12:51:11'),
(1362, 141, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-07 13:09:03'),
(1363, 141, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683518,\"lng\":121.1312471}}', '::1', '2025-10-07 13:09:23'),
(1364, 141, 'logout', '{\"timestamp\":\"2025-10-07 21:10:22\"}', '::1', '2025-10-07 13:10:22'),
(1365, 1, 'login', '{\"success\":true}', '::1', '2025-10-07 13:10:29'),
(1366, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 07:11:00'),
(1367, 1, 'logout', '{\"timestamp\":\"2025-10-08 15:11:40\"}', '::1', '2025-10-08 07:11:40'),
(1368, 139, 'login', '{\"success\":true}', '::1', '2025-10-08 07:11:46'),
(1369, 139, 'logout', '{\"timestamp\":\"2025-10-08 15:14:39\"}', '::1', '2025-10-08 07:14:39'),
(1370, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 07:14:41'),
(1371, 1, 'logout', '{\"timestamp\":\"2025-10-08 15:17:49\"}', '::1', '2025-10-08 07:17:49'),
(1372, 139, 'login', '{\"success\":true}', '::1', '2025-10-08 07:17:54'),
(1373, 139, 'session_scheduled', '{\"match_id\":97,\"date\":\"2025-10-08\"}', '::1', '2025-10-08 07:18:45'),
(1374, 83, 'reminder_sent', '{\"session_id\":24,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:20:58'),
(1375, 83, 'reminder_sent', '{\"session_id\":25,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:02'),
(1376, 83, 'reminder_sent', '{\"session_id\":24,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:06'),
(1377, 40, 'reminder_sent', '{\"session_id\":27,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:09'),
(1378, 40, 'reminder_sent', '{\"session_id\":28,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:13'),
(1379, 82, 'reminder_sent', '{\"session_id\":29,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:16'),
(1380, 119, 'reminder_sent', '{\"session_id\":23,\"type\":\"30_minutes\"}', 'cron', '2025-10-08 07:21:20'),
(1381, 83, 'reminder_sent', '{\"session_id\":25,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:24'),
(1382, 40, 'reminder_sent', '{\"session_id\":27,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:28'),
(1383, 40, 'reminder_sent', '{\"session_id\":28,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:32'),
(1384, 82, 'reminder_sent', '{\"session_id\":29,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:35'),
(1385, 139, 'reminder_sent', '{\"session_id\":39,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:39'),
(1386, 139, 'reminder_sent', '{\"session_id\":40,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:43'),
(1387, 139, 'reminder_sent', '{\"session_id\":41,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:21:48'),
(1388, 139, 'reminder_sent', '{\"session_id\":39,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:51'),
(1389, 139, 'reminder_sent', '{\"session_id\":40,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:55'),
(1390, 139, 'reminder_sent', '{\"session_id\":41,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:21:59'),
(1391, 139, 'logout', '{\"timestamp\":\"2025-10-08 15:23:53\"}', '::1', '2025-10-08 07:23:53'),
(1392, 142, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 07:26:59'),
(1393, 142, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683712,\"lng\":121.13094243133048}}', '::1', '2025-10-08 07:27:19'),
(1394, 142, 'match_request', '{\"match_id\":\"98\",\"mentor_id\":141,\"subject\":\"Trigonometry\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-08 07:28:16'),
(1395, 142, 'match_request_pending', '{\"match_id\":\"98\",\"mentor_id\":141,\"delivery_method\":\"pending\"}', '::1', '2025-10-08 07:28:16'),
(1396, 142, 'logout', '{\"timestamp\":\"2025-10-08 15:28:18\"}', '::1', '2025-10-08 07:28:18'),
(1397, 141, 'login', '{\"success\":true}', '::1', '2025-10-08 07:28:23'),
(1398, 141, 'match_response', '{\"match_id\":98,\"response\":\"accepted\"}', '::1', '2025-10-08 07:28:29'),
(1399, 141, 'session_scheduled', '{\"match_id\":98,\"date\":\"2025-10-08\"}', '::1', '2025-10-08 07:28:47'),
(1400, 141, 'reminder_sent', '{\"session_id\":42,\"type\":\"24_hours\"}', 'cron', '2025-10-08 07:32:32'),
(1401, 141, 'reminder_sent', '{\"session_id\":42,\"type\":\"1_hour\"}', 'cron', '2025-10-08 07:32:35'),
(1402, 141, 'logout', '{\"timestamp\":\"2025-10-08 15:34:28\"}', '::1', '2025-10-08 07:34:28'),
(1403, 143, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 07:41:43'),
(1404, 143, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068372668884122,\"lng\":121.13094360643778}}', '::1', '2025-10-08 07:42:03'),
(1405, 143, 'logout', '{\"timestamp\":\"2025-10-08 15:57:38\"}', '::1', '2025-10-08 07:57:38'),
(1406, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 07:57:41'),
(1407, 1, 'logout', '{\"timestamp\":\"2025-10-08 15:59:38\"}', '::1', '2025-10-08 07:59:38'),
(1408, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 08:04:31'),
(1409, 1, 'logout', '{\"timestamp\":\"2025-10-08 16:12:06\"}', '::1', '2025-10-08 08:12:06'),
(1410, 143, 'login', '{\"success\":true}', '::1', '2025-10-08 08:15:40'),
(1411, 143, 'logout', '{\"timestamp\":\"2025-10-08 16:29:33\"}', '::1', '2025-10-08 08:29:33'),
(1412, 144, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 08:45:23'),
(1413, 144, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068372668884122,\"lng\":121.13094360643778}}', '::1', '2025-10-08 08:45:47'),
(1414, 144, 'logout', '{\"timestamp\":\"2025-10-08 16:48:37\"}', '::1', '2025-10-08 08:48:37'),
(1415, 145, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 08:58:43'),
(1416, 145, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068372668884122,\"lng\":121.13094360643778}}', '::1', '2025-10-08 09:00:07'),
(1417, 145, 'logout', '{\"timestamp\":\"2025-10-08 17:02:42\"}', '::1', '2025-10-08 09:02:42'),
(1418, 146, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 09:03:16'),
(1419, 146, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683712,\"lng\":121.13094243133048}}', '::1', '2025-10-08 09:03:33'),
(1420, 146, 'logout', '{\"timestamp\":\"2025-10-08 17:25:57\"}', '::1', '2025-10-08 09:25:57'),
(1421, 147, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 09:26:34'),
(1422, 147, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-08 09:26:59'),
(1423, 147, 'logout', '{\"timestamp\":\"2025-10-08 17:31:51\"}', '::1', '2025-10-08 09:31:51'),
(1424, 148, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 09:32:21'),
(1425, 148, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-08 09:32:46'),
(1426, 148, 'logout', '{\"timestamp\":\"2025-10-08 17:35:25\"}', '::1', '2025-10-08 09:35:25'),
(1427, 147, 'login', '{\"success\":true}', '::1', '2025-10-08 09:35:33'),
(1428, 147, 'logout', '{\"timestamp\":\"2025-10-08 17:35:50\"}', '::1', '2025-10-08 09:35:50'),
(1429, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 09:35:52'),
(1430, 1, 'admin_verify_user', '{\"verified_user_id\":147}', '::1', '2025-10-08 09:36:16'),
(1431, 1, 'logout', '{\"timestamp\":\"2025-10-08 17:36:24\"}', '::1', '2025-10-08 09:36:24'),
(1432, 147, 'login', '{\"success\":true}', '::1', '2025-10-08 09:36:29'),
(1433, 147, 'logout', '{\"timestamp\":\"2025-10-08 17:37:00\"}', '::1', '2025-10-08 09:37:00'),
(1434, 149, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 09:37:54'),
(1435, 149, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTOR0AE5EE\",\"referral_code_id\":8,\"referred_by\":147}', '::1', '2025-10-08 09:38:16'),
(1436, 149, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683712,\"lng\":121.13094243133048}}', '::1', '2025-10-08 09:38:16'),
(1437, 149, 'logout', '{\"timestamp\":\"2025-10-08 17:38:37\"}', '::1', '2025-10-08 09:38:37'),
(1438, 148, 'login', '{\"success\":true}', '::1', '2025-10-08 09:39:43'),
(1439, 148, 'logout', '{\"timestamp\":\"2025-10-08 17:39:47\"}', '::1', '2025-10-08 09:39:47'),
(1440, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 09:39:50'),
(1441, 1, 'logout', '{\"timestamp\":\"2025-10-08 17:39:52\"}', '::1', '2025-10-08 09:39:52'),
(1442, 150, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 09:40:43'),
(1443, 150, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-08 09:41:00'),
(1444, 150, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTOR0AE5EE\",\"referral_code_id\":8,\"referred_by\":147}', '::1', '2025-10-08 09:41:29'),
(1445, 150, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06830099,\"lng\":121.13092562}}', '::1', '2025-10-08 09:42:01'),
(1446, 150, 'logout', '{\"timestamp\":\"2025-10-08 17:44:09\"}', '::1', '2025-10-08 09:44:09'),
(1447, 151, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 09:44:57'),
(1448, 151, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068315075964753,\"lng\":121.13092814296569}}', '::1', '2025-10-08 09:45:17'),
(1449, 151, 'logout', '{\"timestamp\":\"2025-10-08 17:46:08\"}', '::1', '2025-10-08 09:46:08'),
(1450, 152, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 09:47:00'),
(1451, 152, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-08 09:47:18'),
(1452, 152, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTOR0AE5EE\",\"referral_code_id\":8,\"referred_by\":147}', '::1', '2025-10-08 09:48:02'),
(1453, 152, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06830099,\"lng\":121.13092562}}', '::1', '2025-10-08 09:49:05'),
(1454, 152, 'logout', '{\"timestamp\":\"2025-10-08 17:56:44\"}', '::1', '2025-10-08 09:56:44'),
(1455, 153, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 09:57:23'),
(1456, 153, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068367087124464,\"lng\":121.13094360643778}}', '::1', '2025-10-08 09:57:43'),
(1457, 153, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTOR0AE5EE\",\"referral_code_id\":8,\"referred_by\":147}', '::1', '2025-10-08 09:58:23'),
(1458, 153, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06836709,\"lng\":121.13094361}}', '::1', '2025-10-08 09:58:49'),
(1459, 153, 'logout', '{\"timestamp\":\"2025-10-08 17:59:09\"}', '::1', '2025-10-08 09:59:09'),
(1460, 154, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 10:00:31'),
(1461, 154, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTOR0AE5EE\",\"referral_code_id\":8,\"referred_by\":147}', '::1', '2025-10-08 10:00:52'),
(1462, 154, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683712,\"lng\":121.13094243133048}}', '::1', '2025-10-08 10:00:52'),
(1463, 154, 'logout', '{\"timestamp\":\"2025-10-08 18:11:14\"}', '::1', '2025-10-08 10:11:14'),
(1464, 155, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 10:12:39'),
(1465, 155, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-08 10:13:00');
INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1466, 155, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTOR308CDD\",\"referral_code_id\":7,\"referred_by\":122}', '::1', '2025-10-08 10:14:07'),
(1467, 155, 'referral_code_used', '{\"role\":\"peer\",\"referral_used\":true,\"referral_code\":\"MENTORE1F754\",\"referral_code_id\":6,\"referred_by\":105}', '::1', '2025-10-08 10:15:36'),
(1468, 155, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-08 10:15:36'),
(1469, 155, 'logout', '{\"timestamp\":\"2025-10-08 18:16:09\"}', '::1', '2025-10-08 10:16:09'),
(1470, 156, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-08 10:17:08'),
(1471, 156, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"PEERA8B2F4\",\"referral_code_id\":9,\"referred_by\":155}', '::1', '2025-10-08 10:17:28'),
(1472, 156, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068297476074616,\"lng\":121.13092561719382}}', '::1', '2025-10-08 10:17:28'),
(1473, 156, 'logout', '{\"timestamp\":\"2025-10-08 18:17:55\"}', '::1', '2025-10-08 10:17:55'),
(1474, 155, 'login', '{\"success\":true}', '::1', '2025-10-08 10:18:40'),
(1475, 155, 'logout', '{\"timestamp\":\"2025-10-08 18:18:48\"}', '::1', '2025-10-08 10:18:48'),
(1476, 148, 'login', '{\"success\":true}', '::1', '2025-10-08 10:18:55'),
(1477, 148, 'logout', '{\"timestamp\":\"2025-10-08 18:19:05\"}', '::1', '2025-10-08 10:19:05'),
(1478, 147, 'login', '{\"success\":true}', '::1', '2025-10-08 10:19:14'),
(1479, 147, 'logout', '{\"timestamp\":\"2025-10-08 18:19:18\"}', '::1', '2025-10-08 10:19:18'),
(1480, 157, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 10:19:53'),
(1481, 157, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068297476074616,\"lng\":121.13092561719382}}', '::1', '2025-10-08 10:20:12'),
(1482, 157, 'logout', '{\"timestamp\":\"2025-10-08 18:20:31\"}', '::1', '2025-10-08 10:20:31'),
(1483, 147, 'login', '{\"success\":true}', '::1', '2025-10-08 10:20:35'),
(1484, 147, 'logout', '{\"timestamp\":\"2025-10-08 18:20:52\"}', '::1', '2025-10-08 10:20:52'),
(1485, 157, 'login', '{\"success\":true}', '::1', '2025-10-08 10:20:57'),
(1486, 157, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-08 10:21:03'),
(1487, 157, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06829748,\"lng\":121.13092562}}', '::1', '2025-10-08 10:21:25'),
(1488, 157, 'logout', '{\"timestamp\":\"2025-10-08 18:22:05\"}', '::1', '2025-10-08 10:22:05'),
(1489, 158, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-08 10:22:34'),
(1490, 158, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068604179259388,\"lng\":121.13103829756355}}', '::1', '2025-10-08 10:22:52'),
(1491, 158, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-08 10:32:28'),
(1492, 158, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06860418,\"lng\":121.1310383}}', '::1', '2025-10-08 10:32:47'),
(1493, 158, 'logout', '{\"timestamp\":\"2025-10-08 18:34:18\"}', '::1', '2025-10-08 10:34:18'),
(1494, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 10:46:28'),
(1495, 1, 'logout', '{\"timestamp\":\"2025-10-08 18:55:00\"}', '::1', '2025-10-08 10:55:00'),
(1496, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 11:49:27'),
(1497, 1, 'logout', '{\"timestamp\":\"2025-10-08 19:49:29\"}', '::1', '2025-10-08 11:49:29'),
(1498, 147, 'login', '{\"success\":true}', '::1', '2025-10-08 11:49:50'),
(1499, 147, 'logout', '{\"timestamp\":\"2025-10-08 19:50:27\"}', '::1', '2025-10-08 11:50:27'),
(1500, 158, 'login', '{\"success\":true}', '::1', '2025-10-08 11:50:35'),
(1501, 158, 'logout', '{\"timestamp\":\"2025-10-08 19:51:00\"}', '::1', '2025-10-08 11:51:00'),
(1502, 1, 'login', '{\"success\":true}', '::1', '2025-10-08 11:51:47'),
(1503, 1, 'logout', '{\"timestamp\":\"2025-10-09 07:21:11\"}', '::1', '2025-10-08 23:21:11'),
(1504, 159, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-09 02:55:12'),
(1505, 159, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.044101664713287,\"lng\":121.15851009794815}}', '::1', '2025-10-09 02:55:39'),
(1506, 159, 'logout', '{\"timestamp\":\"2025-10-09 10:56:07\"}', '::1', '2025-10-09 02:56:07'),
(1507, 160, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-09 02:57:40'),
(1508, 160, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.044203025455182,\"lng\":121.158470544745}}', '::1', '2025-10-09 02:58:03'),
(1509, 160, 'logout', '{\"timestamp\":\"2025-10-09 10:58:11\"}', '::1', '2025-10-09 02:58:11'),
(1510, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 02:58:12'),
(1511, 1, 'admin_verify_user', '{\"verified_user_id\":159}', '::1', '2025-10-09 02:58:18'),
(1512, 1, 'logout', '{\"timestamp\":\"2025-10-09 10:58:20\"}', '::1', '2025-10-09 02:58:20'),
(1513, 159, 'login', '{\"success\":true}', '::1', '2025-10-09 02:58:32'),
(1514, 159, 'logout', '{\"timestamp\":\"2025-10-09 10:59:47\"}', '::1', '2025-10-09 02:59:47'),
(1515, 160, 'login', '{\"success\":true}', '::1', '2025-10-09 02:59:55'),
(1516, 160, 'logout', '{\"timestamp\":\"2025-10-09 11:01:36\"}', '::1', '2025-10-09 03:01:36'),
(1517, 159, 'login', '{\"success\":true}', '::1', '2025-10-09 03:01:45'),
(1518, 159, 'logout', '{\"timestamp\":\"2025-10-09 11:02:33\"}', '::1', '2025-10-09 03:02:33'),
(1519, 160, 'login', '{\"success\":true}', '::1', '2025-10-09 03:02:53'),
(1520, 160, 'logout', '{\"timestamp\":\"2025-10-09 11:03:39\"}', '::1', '2025-10-09 03:03:39'),
(1521, 161, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-09 03:05:38'),
(1522, 161, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0442354437837,\"lng\":121.15846673349219}}', '::1', '2025-10-09 03:06:59'),
(1523, 161, 'logout', '{\"timestamp\":\"2025-10-09 11:08:31\"}', '::1', '2025-10-09 03:08:31'),
(1524, 162, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-09 03:09:19'),
(1525, 162, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.04421569235025,\"lng\":121.15844791994716}}', '::1', '2025-10-09 03:12:49'),
(1526, 162, 'logout', '{\"timestamp\":\"2025-10-09 11:13:18\"}', '::1', '2025-10-09 03:13:18'),
(1527, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 03:13:24'),
(1528, 1, 'admin_verify_user', '{\"verified_user_id\":162}', '::1', '2025-10-09 03:13:33'),
(1529, 1, 'logout', '{\"timestamp\":\"2025-10-09 11:13:37\"}', '::1', '2025-10-09 03:13:37'),
(1530, 162, 'login', '{\"success\":true}', '::1', '2025-10-09 03:13:53'),
(1531, 162, 'match_request', '{\"match_id\":\"99\",\"mentor_id\":161,\"subject\":\"C++\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-09 03:14:29'),
(1532, 162, 'match_request_pending', '{\"match_id\":\"99\",\"mentor_id\":161,\"delivery_method\":\"pending\"}', '::1', '2025-10-09 03:14:29'),
(1533, 162, 'logout', '{\"timestamp\":\"2025-10-09 11:14:53\"}', '::1', '2025-10-09 03:14:53'),
(1534, 161, 'login', '{\"success\":true}', '::1', '2025-10-09 03:15:11'),
(1535, 161, 'match_response', '{\"match_id\":99,\"response\":\"accepted\"}', '::1', '2025-10-09 03:15:22'),
(1536, 161, 'session_scheduled', '{\"match_id\":99,\"date\":\"2025-10-09\"}', '::1', '2025-10-09 03:15:53'),
(1537, 161, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-09 08:45:59'),
(1538, 161, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.0683712,\"lng\":121.13094243133048}}', '::1', '2025-10-09 08:46:28'),
(1539, 161, 'logout', '{\"timestamp\":\"2025-10-09 16:50:02\"}', '::1', '2025-10-09 08:50:02'),
(1540, 163, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-09 08:51:42'),
(1541, 163, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-09 08:52:04'),
(1542, 163, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-09 08:52:26'),
(1543, 163, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-09 08:52:47'),
(1544, 163, 'logout', '{\"timestamp\":\"2025-10-09 17:01:49\"}', '::1', '2025-10-09 09:01:49'),
(1545, 164, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-09 09:02:25'),
(1546, 164, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068297476074616,\"lng\":121.13092561719382}}', '::1', '2025-10-09 09:02:41'),
(1547, 164, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-09 09:02:56'),
(1548, 164, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06829748,\"lng\":121.13092562}}', '::1', '2025-10-09 09:03:18'),
(1549, 164, 'logout', '{\"timestamp\":\"2025-10-09 17:05:39\"}', '::1', '2025-10-09 09:05:39'),
(1550, 165, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-09 09:06:12'),
(1551, 165, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06826614788871,\"lng\":121.13091788693764}}', '::1', '2025-10-09 09:06:28'),
(1552, 165, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-09 09:06:46'),
(1553, 165, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06826615,\"lng\":121.13091789}}', '::1', '2025-10-09 09:07:08'),
(1554, 165, 'logout', '{\"timestamp\":\"2025-10-09 17:14:23\"}', '::1', '2025-10-09 09:14:23'),
(1555, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 09:14:25'),
(1556, 1, 'logout', '{\"timestamp\":\"2025-10-09 17:18:55\"}', '::1', '2025-10-09 09:18:55'),
(1557, 3, 'login', '{\"success\":true}', '::1', '2025-10-09 09:19:23'),
(1558, 3, 'logout', '{\"timestamp\":\"2025-10-09 17:20:48\"}', '::1', '2025-10-09 09:20:48'),
(1559, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 09:20:49'),
(1560, 1, 'logout', '{\"timestamp\":\"2025-10-09 17:22:44\"}', '::1', '2025-10-09 09:22:44'),
(1561, 3, 'login', '{\"success\":true}', '::1', '2025-10-09 09:22:49'),
(1562, 3, 'logout', '{\"timestamp\":\"2025-10-09 17:22:58\"}', '::1', '2025-10-09 09:22:58'),
(1563, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 09:23:01'),
(1564, 1, 'logout', '{\"timestamp\":\"2025-10-09 17:30:42\"}', '::1', '2025-10-09 09:30:42'),
(1565, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 09:30:45'),
(1566, 1, 'admin_deactivate_user', '{\"deactivated_user_id\":165}', '::1', '2025-10-09 10:06:10'),
(1567, 1, 'admin_verify_user', '{\"verified_user_id\":165}', '::1', '2025-10-09 10:06:17'),
(1568, 1, 'admin_activate_user', '{\"activated_user_id\":165}', '::1', '2025-10-09 10:06:30'),
(1569, 1, 'logout', '{\"timestamp\":\"2025-10-09 18:14:23\"}', '::1', '2025-10-09 10:14:23'),
(1570, 165, 'login', '{\"success\":true}', '::1', '2025-10-09 10:14:30'),
(1571, 165, 'logout', '{\"timestamp\":\"2025-10-09 18:14:39\"}', '::1', '2025-10-09 10:14:39'),
(1572, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 10:14:42'),
(1573, 1, 'logout', '{\"timestamp\":\"2025-10-09 18:15:03\"}', '::1', '2025-10-09 10:15:03'),
(1574, 138, 'login', '{\"success\":true}', '::1', '2025-10-09 10:15:07'),
(1575, 138, 'logout', '{\"timestamp\":\"2025-10-09 18:15:59\"}', '::1', '2025-10-09 10:15:59'),
(1576, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 10:16:01'),
(1577, 1, 'logout', '{\"timestamp\":\"2025-10-09 19:45:31\"}', '::1', '2025-10-09 11:45:31'),
(1578, 3, 'login', '{\"success\":true}', '::1', '2025-10-09 11:45:43'),
(1579, 3, 'logout', '{\"timestamp\":\"2025-10-09 19:50:15\"}', '::1', '2025-10-09 11:50:15'),
(1580, 1, 'login', '{\"success\":true}', '::1', '2025-10-09 11:50:17'),
(1581, 1, 'logout', '{\"timestamp\":\"2025-10-09 19:54:51\"}', '::1', '2025-10-09 11:54:51'),
(1582, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 00:20:46'),
(1583, 1, 'admin_delete_user', '{\"deleted_user_id\":2}', '::1', '2025-10-10 01:27:12'),
(1584, 1, 'admin_delete_user', '{\"deleted_user_id\":21}', '::1', '2025-10-10 01:27:14'),
(1585, 1, 'admin_delete_user', '{\"deleted_user_id\":22}', '::1', '2025-10-10 01:27:17'),
(1586, 1, 'admin_delete_user', '{\"deleted_user_id\":23}', '::1', '2025-10-10 01:27:19'),
(1587, 1, 'admin_delete_user', '{\"deleted_user_id\":24}', '::1', '2025-10-10 01:27:22'),
(1588, 1, 'admin_delete_user', '{\"deleted_user_id\":25}', '::1', '2025-10-10 01:27:24'),
(1589, 1, 'admin_delete_user', '{\"deleted_user_id\":28}', '::1', '2025-10-10 01:27:26'),
(1590, 1, 'admin_delete_user', '{\"deleted_user_id\":29}', '::1', '2025-10-10 01:27:28'),
(1591, 1, 'admin_delete_user', '{\"deleted_user_id\":30}', '::1', '2025-10-10 01:27:30'),
(1592, 1, 'admin_delete_user', '{\"deleted_user_id\":31}', '::1', '2025-10-10 01:27:33'),
(1593, 1, 'admin_delete_user', '{\"deleted_user_id\":34}', '::1', '2025-10-10 01:27:35'),
(1594, 1, 'admin_delete_user', '{\"deleted_user_id\":36}', '::1', '2025-10-10 01:27:37'),
(1595, 1, 'logout', '{\"timestamp\":\"2025-10-10 12:50:21\"}', '::1', '2025-10-10 04:50:21'),
(1596, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 04:50:25'),
(1597, 1, 'logout', '{\"timestamp\":\"2025-10-10 12:50:47\"}', '::1', '2025-10-10 04:50:47'),
(1598, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 04:50:54'),
(1599, 1, 'logout', '{\"timestamp\":\"2025-10-10 12:51:02\"}', '::1', '2025-10-10 04:51:02'),
(1600, 3, 'login', '{\"success\":true}', '::1', '2025-10-10 04:51:08'),
(1601, 3, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-10 04:56:40'),
(1602, 3, 'logout', '{\"timestamp\":\"2025-10-10 12:59:26\"}', '::1', '2025-10-10 04:59:26'),
(1603, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 04:59:28'),
(1604, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:14:35\"}', '::1', '2025-10-10 05:14:35'),
(1605, 3, 'login', '{\"success\":true}', '::1', '2025-10-10 05:15:03'),
(1606, 3, 'logout', '{\"timestamp\":\"2025-10-10 13:15:28\"}', '::1', '2025-10-10 05:15:28'),
(1607, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 05:15:31'),
(1608, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:16:14\"}', '::1', '2025-10-10 05:16:14'),
(1609, 40, 'login', '{\"success\":true}', '::1', '2025-10-10 05:16:20'),
(1610, 40, 'session_completed', '{\"session_id\":28}', '::1', '2025-10-10 05:16:29'),
(1611, 40, 'logout', '{\"timestamp\":\"2025-10-10 13:17:50\"}', '::1', '2025-10-10 05:17:50'),
(1612, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 05:17:52'),
(1613, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:22:55\"}', '::1', '2025-10-10 05:22:55'),
(1614, 40, 'login', '{\"success\":true}', '::1', '2025-10-10 05:23:01'),
(1615, 40, 'session_scheduled', '{\"match_id\":37,\"date\":\"2025-10-10\"}', '::1', '2025-10-10 05:23:12'),
(1616, 40, 'logout', '{\"timestamp\":\"2025-10-10 13:23:26\"}', '::1', '2025-10-10 05:23:26'),
(1617, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 05:23:28'),
(1618, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:23:42\"}', '::1', '2025-10-10 05:23:42'),
(1619, 40, 'login', '{\"success\":true}', '::1', '2025-10-10 05:24:00'),
(1620, 40, 'logout', '{\"timestamp\":\"2025-10-10 13:24:17\"}', '::1', '2025-10-10 05:24:17'),
(1621, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 05:24:20'),
(1622, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:24:47\"}', '::1', '2025-10-10 05:24:47'),
(1623, 40, 'login', '{\"success\":true}', '::1', '2025-10-10 05:24:54'),
(1624, 40, 'logout', '{\"timestamp\":\"2025-10-10 13:25:23\"}', '::1', '2025-10-10 05:25:23'),
(1625, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 05:25:25'),
(1626, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:25:50\"}', '::1', '2025-10-10 05:25:50'),
(1627, 40, 'login', '{\"success\":true}', '::1', '2025-10-10 05:25:55'),
(1628, 40, 'logout', '{\"timestamp\":\"2025-10-10 13:26:26\"}', '::1', '2025-10-10 05:26:26'),
(1629, 1, 'login', '{\"success\":true}', '::1', '2025-10-10 05:26:28'),
(1630, 1, 'logout', '{\"timestamp\":\"2025-10-10 13:26:42\"}', '::1', '2025-10-10 05:26:42'),
(1631, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 01:36:59'),
(1632, 1, 'logout', '{\"timestamp\":\"2025-10-11 09:52:38\"}', '::1', '2025-10-11 01:52:38'),
(1633, 40, 'login', '{\"success\":true}', '::1', '2025-10-11 01:55:37'),
(1634, 40, 'logout', '{\"timestamp\":\"2025-10-11 10:38:32\"}', '::1', '2025-10-11 02:38:32'),
(1635, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 02:38:35'),
(1636, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:01:06\"}', '::1', '2025-10-11 03:01:06'),
(1637, 40, 'login', '{\"success\":true}', '::1', '2025-10-11 03:01:27'),
(1638, 40, 'session_completed', '{\"session_id\":44}', '::1', '2025-10-11 03:21:06'),
(1639, 40, 'session_rated', '{\"session_id\":44,\"rating\":5}', '::1', '2025-10-11 03:21:12'),
(1640, 40, 'logout', '{\"timestamp\":\"2025-10-11 11:21:42\"}', '::1', '2025-10-11 03:21:42'),
(1641, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:21:44'),
(1642, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:24:17\"}', '::1', '2025-10-11 03:24:17'),
(1643, 40, 'login', '{\"success\":true}', '::1', '2025-10-11 03:24:26'),
(1644, 40, 'logout', '{\"timestamp\":\"2025-10-11 11:24:45\"}', '::1', '2025-10-11 03:24:45'),
(1645, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:24:50'),
(1646, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:24:59\"}', '::1', '2025-10-11 03:24:59'),
(1647, 147, 'login', '{\"success\":true}', '::1', '2025-10-11 03:25:04'),
(1648, 147, 'match_request', '{\"match_id\":\"100\",\"mentor_id\":148,\"subject\":\"Abnormal Psychology\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-11 03:25:19'),
(1649, 147, 'match_request_pending', '{\"match_id\":\"100\",\"mentor_id\":148,\"delivery_method\":\"pending\"}', '::1', '2025-10-11 03:25:19'),
(1650, 147, 'logout', '{\"timestamp\":\"2025-10-11 11:25:24\"}', '::1', '2025-10-11 03:25:24'),
(1651, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 03:25:29'),
(1652, 148, 'match_response', '{\"match_id\":100,\"response\":\"accepted\"}', '::1', '2025-10-11 03:25:33'),
(1653, 148, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-11\"}', '::1', '2025-10-11 03:25:44'),
(1654, 148, 'logout', '{\"timestamp\":\"2025-10-11 11:28:22\"}', '::1', '2025-10-11 03:28:22'),
(1655, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:28:24'),
(1656, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:29:18\"}', '::1', '2025-10-11 03:29:18'),
(1657, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 03:29:24'),
(1658, 148, 'session_completed', '{\"session_id\":45}', '::1', '2025-10-11 03:30:23'),
(1659, 148, 'session_rated', '{\"session_id\":45,\"rating\":5}', '::1', '2025-10-11 03:30:30'),
(1660, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:33:35'),
(1661, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:34:17\"}', '::1', '2025-10-11 03:34:17'),
(1662, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 03:34:22'),
(1663, 148, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-11\"}', '::1', '2025-10-11 03:35:03'),
(1664, 148, 'session_completed', '{\"session_id\":46}', '::1', '2025-10-11 03:37:10'),
(1665, 148, 'session_rated', '{\"session_id\":46,\"rating\":5}', '::1', '2025-10-11 03:37:17'),
(1666, 148, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-11\"}', '::1', '2025-10-11 03:38:52'),
(1667, 148, 'logout', '{\"timestamp\":\"2025-10-11 11:40:26\"}', '::1', '2025-10-11 03:40:26'),
(1668, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:40:28'),
(1669, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:43:28\"}', '::1', '2025-10-11 03:43:28'),
(1670, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:43:31'),
(1671, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:43:36\"}', '::1', '2025-10-11 03:43:36'),
(1672, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 03:43:41'),
(1673, 148, 'session_completed', '{\"session_id\":47}', '::1', '2025-10-11 03:43:51'),
(1674, 148, 'logout', '{\"timestamp\":\"2025-10-11 11:43:59\"}', '::1', '2025-10-11 03:43:59'),
(1675, 147, 'login', '{\"success\":true}', '::1', '2025-10-11 03:44:03'),
(1676, 147, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-11\"}', '::1', '2025-10-11 03:44:41'),
(1677, 147, 'session_completed', '{\"session_id\":48}', '::1', '2025-10-11 03:46:33'),
(1678, 147, 'logout', '{\"timestamp\":\"2025-10-11 11:46:42\"}', '::1', '2025-10-11 03:46:42'),
(1679, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 03:46:47'),
(1680, 148, 'logout', '{\"timestamp\":\"2025-10-11 11:47:00\"}', '::1', '2025-10-11 03:47:00'),
(1681, 147, 'login', '{\"success\":true}', '::1', '2025-10-11 03:47:49'),
(1682, 147, 'logout', '{\"timestamp\":\"2025-10-11 11:48:43\"}', '::1', '2025-10-11 03:48:43'),
(1683, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 03:48:45'),
(1684, 1, 'logout', '{\"timestamp\":\"2025-10-11 11:53:50\"}', '::1', '2025-10-11 03:53:50'),
(1685, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 03:53:55'),
(1686, 148, 'logout', '{\"timestamp\":\"2025-10-11 11:55:42\"}', '::1', '2025-10-11 03:55:42'),
(1687, 147, 'login', '{\"success\":true}', '::1', '2025-10-11 03:55:47'),
(1688, 147, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-11\"}', '::1', '2025-10-11 03:59:53'),
(1689, 147, 'logout', '{\"timestamp\":\"2025-10-11 12:04:07\"}', '::1', '2025-10-11 04:04:07'),
(1690, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 04:04:10'),
(1691, 1, 'logout', '{\"timestamp\":\"2025-10-11 12:05:30\"}', '::1', '2025-10-11 04:05:30'),
(1692, 147, 'login', '{\"success\":true}', '::1', '2025-10-11 04:05:36'),
(1693, 147, 'logout', '{\"timestamp\":\"2025-10-11 12:05:57\"}', '::1', '2025-10-11 04:05:57'),
(1694, 148, 'login', '{\"success\":true}', '::1', '2025-10-11 04:06:06'),
(1695, 148, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-11\"}', '::1', '2025-10-11 04:06:37'),
(1696, 148, 'logout', '{\"timestamp\":\"2025-10-11 12:07:22\"}', '::1', '2025-10-11 04:07:22'),
(1697, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 04:07:24'),
(1698, 1, 'logout', '{\"timestamp\":\"2025-10-11 12:26:41\"}', '::1', '2025-10-11 04:26:41'),
(1699, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 04:28:30'),
(1700, 1, 'logout', '{\"timestamp\":\"2025-10-11 12:30:29\"}', '::1', '2025-10-11 04:30:29'),
(1701, 1, 'login', '{\"success\":true}', '::1', '2025-10-11 04:30:31'),
(1702, 1, 'logout', '{\"timestamp\":\"2025-10-12 11:14:55\"}', '::1', '2025-10-12 03:14:55'),
(1703, 40, 'login', '{\"success\":true}', '::1', '2025-10-12 03:15:46'),
(1704, 40, 'logout', '{\"timestamp\":\"2025-10-12 11:25:28\"}', '::1', '2025-10-12 03:25:28'),
(1705, 166, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-12 03:27:34'),
(1706, 166, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-12 03:29:30'),
(1707, 166, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06826614788871,\"lng\":121.13091788693764}}', '::1', '2025-10-12 03:29:30'),
(1708, 166, 'logout', '{\"timestamp\":\"2025-10-12 11:31:57\"}', '::1', '2025-10-12 03:31:57'),
(1709, 3, 'login', '{\"success\":true}', '::1', '2025-10-12 03:32:01'),
(1710, 3, 'logout', '{\"timestamp\":\"2025-10-12 11:32:02\"}', '::1', '2025-10-12 03:32:02'),
(1711, 165, 'login', '{\"success\":true}', '::1', '2025-10-12 03:32:19'),
(1712, 165, 'logout', '{\"timestamp\":\"2025-10-12 11:32:21\"}', '::1', '2025-10-12 03:32:21'),
(1713, 147, 'login', '{\"success\":true}', '::1', '2025-10-12 03:32:25'),
(1714, 147, 'logout', '{\"timestamp\":\"2025-10-12 11:32:29\"}', '::1', '2025-10-12 03:32:29'),
(1715, 167, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-12 03:37:47'),
(1716, 167, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-12 03:38:03'),
(1717, 167, 'logout', '{\"timestamp\":\"2025-10-12 11:38:45\"}', '::1', '2025-10-12 03:38:45'),
(1718, 168, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-12 03:39:50'),
(1719, 168, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068297476074616,\"lng\":121.13092561719382}}', '::1', '2025-10-12 03:40:12'),
(1720, 168, 'logout', '{\"timestamp\":\"2025-10-12 11:49:14\"}', '::1', '2025-10-12 03:49:14'),
(1721, 40, 'login', '{\"success\":true}', '::1', '2025-10-12 03:49:18'),
(1722, 40, 'logout', '{\"timestamp\":\"2025-10-12 11:50:56\"}', '::1', '2025-10-12 03:50:56'),
(1723, 168, 'login', '{\"success\":true}', '::1', '2025-10-12 03:51:08'),
(1724, 168, 'match_request', '{\"match_id\":\"101\",\"mentor_id\":166,\"subject\":\"C++\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-12 03:51:15'),
(1725, 168, 'match_request_pending', '{\"match_id\":\"101\",\"mentor_id\":166,\"delivery_method\":\"pending\"}', '::1', '2025-10-12 03:51:15'),
(1726, 168, 'logout', '{\"timestamp\":\"2025-10-12 11:51:20\"}', '::1', '2025-10-12 03:51:20'),
(1727, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 03:51:24'),
(1728, 166, 'match_response', '{\"match_id\":101,\"response\":\"accepted\"}', '::1', '2025-10-12 03:51:36'),
(1729, 166, 'logout', '{\"timestamp\":\"2025-10-12 11:51:46\"}', '::1', '2025-10-12 03:51:46'),
(1730, 168, 'login', '{\"success\":true}', '::1', '2025-10-12 03:51:51'),
(1731, 168, 'logout', '{\"timestamp\":\"2025-10-12 11:53:14\"}', '::1', '2025-10-12 03:53:14'),
(1732, 168, 'login', '{\"success\":true}', '::1', '2025-10-12 03:53:19'),
(1733, 168, 'logout', '{\"timestamp\":\"2025-10-12 11:53:21\"}', '::1', '2025-10-12 03:53:21'),
(1734, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 03:53:26'),
(1735, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:07:14\"}', '::1', '2025-10-12 04:07:14'),
(1736, 1, 'login', '{\"success\":true}', '::1', '2025-10-12 04:07:16'),
(1737, 1, 'logout', '{\"timestamp\":\"2025-10-12 12:07:50\"}', '::1', '2025-10-12 04:07:50'),
(1738, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:07:55'),
(1739, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:14:30\"}', '::1', '2025-10-12 04:14:30'),
(1740, 1, 'login', '{\"success\":true}', '::1', '2025-10-12 04:14:32'),
(1741, 1, 'logout', '{\"timestamp\":\"2025-10-12 12:16:29\"}', '::1', '2025-10-12 04:16:29'),
(1742, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:16:37'),
(1743, 166, 'session_scheduled', '{\"match_id\":101,\"date\":\"2025-10-12\"}', '::1', '2025-10-12 04:16:49'),
(1744, 166, 'session_cancelled', '{\"session_id\":51,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-12 04:17:08'),
(1745, 166, 'session_scheduled', '{\"match_id\":101,\"date\":\"2025-10-12\"}', '::1', '2025-10-12 04:17:21'),
(1746, 166, 'message_sent', '{\"match_id\":101,\"partner_id\":168}', '::1', '2025-10-12 04:17:58'),
(1747, 166, 'session_completed', '{\"session_id\":52}', '::1', '2025-10-12 04:20:52'),
(1748, 166, 'session_rated', '{\"session_id\":52,\"rating\":5}', '::1', '2025-10-12 04:21:02'),
(1749, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:21:23\"}', '::1', '2025-10-12 04:21:23'),
(1750, 168, 'login', '{\"success\":true}', '::1', '2025-10-12 04:21:28'),
(1751, 168, 'session_rated', '{\"session_id\":52,\"rating\":5}', '::1', '2025-10-12 04:21:38'),
(1752, 168, 'logout', '{\"timestamp\":\"2025-10-12 12:21:51\"}', '::1', '2025-10-12 04:21:51'),
(1753, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:21:56'),
(1754, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:25:14\"}', '::1', '2025-10-12 04:25:14'),
(1755, 1, 'login', '{\"success\":true}', '::1', '2025-10-12 04:25:16'),
(1756, 1, 'logout', '{\"timestamp\":\"2025-10-12 12:26:46\"}', '::1', '2025-10-12 04:26:46'),
(1757, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:26:55'),
(1758, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:28:03\"}', '::1', '2025-10-12 04:28:03'),
(1759, 1, 'login', '{\"success\":true}', '::1', '2025-10-12 04:28:05'),
(1760, 1, 'logout', '{\"timestamp\":\"2025-10-12 12:33:09\"}', '::1', '2025-10-12 04:33:09'),
(1761, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:33:19'),
(1762, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:35:21\"}', '::1', '2025-10-12 04:35:21'),
(1763, 1, 'login', '{\"success\":true}', '::1', '2025-10-12 04:35:23'),
(1764, 1, 'logout', '{\"timestamp\":\"2025-10-12 12:35:34\"}', '::1', '2025-10-12 04:35:34'),
(1765, 168, 'login', '{\"success\":true}', '::1', '2025-10-12 04:35:43'),
(1766, 168, 'logout', '{\"timestamp\":\"2025-10-12 12:36:36\"}', '::1', '2025-10-12 04:36:36'),
(1767, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:36:40'),
(1768, 166, 'session_scheduled', '{\"match_id\":101,\"date\":\"2025-10-12\"}', '::1', '2025-10-12 04:36:54'),
(1769, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:37:13\"}', '::1', '2025-10-12 04:37:13'),
(1770, 168, 'login', '{\"success\":true}', '::1', '2025-10-12 04:37:18'),
(1771, 168, 'logout', '{\"timestamp\":\"2025-10-12 12:37:50\"}', '::1', '2025-10-12 04:37:50'),
(1772, 169, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-12 04:38:23'),
(1773, 169, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-12 04:38:41'),
(1774, 169, 'match_request', '{\"match_id\":\"102\",\"mentor_id\":166,\"subject\":\"C++\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-12 04:38:59'),
(1775, 169, 'match_request_pending', '{\"match_id\":\"102\",\"mentor_id\":166,\"delivery_method\":\"pending\"}', '::1', '2025-10-12 04:39:00'),
(1776, 169, 'logout', '{\"timestamp\":\"2025-10-12 12:39:10\"}', '::1', '2025-10-12 04:39:10'),
(1777, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:39:16'),
(1778, 166, 'session_completed', '{\"session_id\":56}', '::1', '2025-10-12 04:39:24'),
(1779, 166, 'session_rated', '{\"session_id\":56,\"rating\":5}', '::1', '2025-10-12 04:39:31'),
(1780, 166, 'match_response', '{\"match_id\":102,\"response\":\"accepted\"}', '::1', '2025-10-12 04:39:43'),
(1781, 166, 'session_scheduled', '{\"match_id\":102,\"date\":\"2025-10-12\"}', '::1', '2025-10-12 04:49:19'),
(1782, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:50:46\"}', '::1', '2025-10-12 04:50:46'),
(1783, 1, 'login', '{\"success\":true}', '::1', '2025-10-12 04:50:48'),
(1784, 1, 'logout', '{\"timestamp\":\"2025-10-12 12:51:27\"}', '::1', '2025-10-12 04:51:27'),
(1785, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:51:32'),
(1786, 166, 'logout', '{\"timestamp\":\"2025-10-12 12:51:53\"}', '::1', '2025-10-12 04:51:53'),
(1787, 169, 'login', '{\"success\":true}', '::1', '2025-10-12 04:51:57'),
(1788, 169, 'session_completed', '{\"session_id\":57}', '::1', '2025-10-12 04:57:41'),
(1789, 169, 'session_rated', '{\"session_id\":57,\"rating\":5}', '::1', '2025-10-12 04:57:50'),
(1790, 169, 'logout', '{\"timestamp\":\"2025-10-12 12:57:58\"}', '::1', '2025-10-12 04:57:58'),
(1791, 166, 'login', '{\"success\":true}', '::1', '2025-10-12 04:58:06'),
(1792, 166, 'session_rated', '{\"session_id\":57,\"rating\":5}', '::1', '2025-10-12 04:58:14'),
(1793, 166, 'session_scheduled', '{\"match_id\":101,\"date\":\"2025-10-12\"}', '::1', '2025-10-12 04:59:15'),
(1794, 166, 'session_completed', '{\"session_id\":58}', '::1', '2025-10-12 05:00:46'),
(1795, 166, 'session_rated', '{\"session_id\":58,\"rating\":5}', '::1', '2025-10-12 05:01:48'),
(1796, 166, 'logout', '{\"timestamp\":\"2025-10-12 13:01:59\"}', '::1', '2025-10-12 05:01:59'),
(1797, 147, 'login', '{\"success\":true}', '::1', '2025-10-13 03:34:25'),
(1798, 147, 'session_completed', '{\"session_id\":49}', '::1', '2025-10-13 03:36:28'),
(1799, 147, 'logout', '{\"timestamp\":\"2025-10-13 11:39:10\"}', '::1', '2025-10-13 03:39:10'),
(1800, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 03:39:33'),
(1801, 1, 'logout', '{\"timestamp\":\"2025-10-13 11:41:25\"}', '::1', '2025-10-13 03:41:25'),
(1802, 147, 'login', '{\"success\":true}', '::1', '2025-10-13 03:41:41'),
(1803, 147, 'session_completed', '{\"session_id\":50}', '::1', '2025-10-13 03:42:18'),
(1804, 147, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-13\"}', '::1', '2025-10-13 03:44:42'),
(1805, 147, 'logout', '{\"timestamp\":\"2025-10-13 11:44:55\"}', '::1', '2025-10-13 03:44:55'),
(1806, 147, 'login', '{\"success\":true}', '::1', '2025-10-13 03:45:03'),
(1807, 147, 'logout', '{\"timestamp\":\"2025-10-13 11:45:11\"}', '::1', '2025-10-13 03:45:11'),
(1808, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 03:45:14'),
(1809, 148, 'session_completed', '{\"session_id\":59}', '::1', '2025-10-13 03:45:27'),
(1810, 148, 'logout', '{\"timestamp\":\"2025-10-13 11:45:42\"}', '::1', '2025-10-13 03:45:42'),
(1811, 147, 'login', '{\"success\":true}', '::1', '2025-10-13 03:45:47'),
(1812, 147, 'session_scheduled', '{\"match_id\":100,\"date\":\"2025-10-13\"}', '::1', '2025-10-13 03:46:20'),
(1813, 147, 'logout', '{\"timestamp\":\"2025-10-13 11:46:54\"}', '::1', '2025-10-13 03:46:54'),
(1814, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 03:46:59'),
(1815, 148, 'session_completed', '{\"session_id\":60}', '::1', '2025-10-13 03:47:05'),
(1816, 148, 'logout', '{\"timestamp\":\"2025-10-13 11:47:21\"}', '::1', '2025-10-13 03:47:21'),
(1817, 170, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-13 03:48:49'),
(1818, 170, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-13 03:49:21'),
(1819, 170, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-13 03:49:21'),
(1820, 170, 'logout', '{\"timestamp\":\"2025-10-13 11:54:26\"}', '::1', '2025-10-13 03:54:26'),
(1821, 171, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-13 03:55:30'),
(1822, 171, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-13 03:55:51'),
(1823, 171, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-13 03:55:51'),
(1824, 171, 'referral_code_used', '{\"role\":\"mentor\",\"referral_used\":true,\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147}', '::1', '2025-10-13 04:00:09'),
(1825, 171, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-13 04:00:10'),
(1826, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:00:19\"}', '::1', '2025-10-13 04:00:19'),
(1827, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:00:22'),
(1828, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:01:12\"}', '::1', '2025-10-13 04:01:12'),
(1829, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:01:18'),
(1830, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:01:26\"}', '::1', '2025-10-13 04:01:26'),
(1831, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:01:32'),
(1832, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:01:38\"}', '::1', '2025-10-13 04:01:38'),
(1833, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:01:42'),
(1834, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:02:16\"}', '::1', '2025-10-13 04:02:16'),
(1835, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:02:20'),
(1836, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:03:32\"}', '::1', '2025-10-13 04:03:32'),
(1837, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:03:37'),
(1838, 148, 'match_request', '{\"match_id\":\"103\",\"mentor_id\":171,\"subject\":\"Psychology - Abnormal Psychology\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-13 04:04:05'),
(1839, 148, 'match_request_pending', '{\"match_id\":\"103\",\"mentor_id\":171,\"delivery_method\":\"pending\"}', '::1', '2025-10-13 04:04:05'),
(1840, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:04:06\"}', '::1', '2025-10-13 04:04:06'),
(1841, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:04:14'),
(1842, 171, 'match_response', '{\"match_id\":103,\"response\":\"accepted\"}', '::1', '2025-10-13 04:04:19'),
(1843, 171, 'session_scheduled', '{\"match_id\":103,\"date\":\"2025-10-13\"}', '::1', '2025-10-13 04:04:45'),
(1844, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:05:13\"}', '::1', '2025-10-13 04:05:13'),
(1845, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:05:17'),
(1846, 148, 'session_completed', '{\"session_id\":61}', '::1', '2025-10-13 04:05:22'),
(1847, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:06:56\"}', '::1', '2025-10-13 04:06:56'),
(1848, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:07:01'),
(1849, 171, 'session_scheduled', '{\"match_id\":103,\"date\":\"2025-10-13\"}', '::1', '2025-10-13 04:07:23'),
(1850, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:07:34\"}', '::1', '2025-10-13 04:07:34'),
(1851, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:07:38'),
(1852, 148, 'session_completed', '{\"session_id\":62}', '::1', '2025-10-13 04:08:05'),
(1853, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:08:24\"}', '::1', '2025-10-13 04:08:24'),
(1854, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:08:26'),
(1855, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:08:52\"}', '::1', '2025-10-13 04:08:52'),
(1856, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:08:58'),
(1857, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:09:19\"}', '::1', '2025-10-13 04:09:19'),
(1858, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:09:23'),
(1859, 171, 'session_scheduled', '{\"match_id\":103,\"date\":\"2025-10-13\"}', '::1', '2025-10-13 04:09:36'),
(1860, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:10:34\"}', '::1', '2025-10-13 04:10:34'),
(1861, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:10:37'),
(1862, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:11:00\"}', '::1', '2025-10-13 04:11:00'),
(1863, 148, 'login', '{\"success\":true}', '::1', '2025-10-13 04:11:44'),
(1864, 148, 'session_completed', '{\"session_id\":64}', '::1', '2025-10-13 04:11:50'),
(1865, 148, 'logout', '{\"timestamp\":\"2025-10-13 12:11:55\"}', '::1', '2025-10-13 04:11:55'),
(1866, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:11:56'),
(1867, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:12:11\"}', '::1', '2025-10-13 04:12:11'),
(1868, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:12:17'),
(1869, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:14:41\"}', '::1', '2025-10-13 04:14:41'),
(1870, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:14:43'),
(1871, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:15:40\"}', '::1', '2025-10-13 04:15:40'),
(1872, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:15:45'),
(1873, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:27:11\"}', '::1', '2025-10-13 04:27:11'),
(1874, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:27:12'),
(1875, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:30:36\"}', '::1', '2025-10-13 04:30:36'),
(1876, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:30:41'),
(1877, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:32:57\"}', '::1', '2025-10-13 04:32:57'),
(1878, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:32:59'),
(1879, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:34:28\"}', '::1', '2025-10-13 04:34:28'),
(1880, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:34:32'),
(1881, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:37:03\"}', '::1', '2025-10-13 04:37:03'),
(1882, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:37:06'),
(1883, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:39:09\"}', '::1', '2025-10-13 04:39:09'),
(1884, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:39:14'),
(1885, 171, 'logout', '{\"timestamp\":\"2025-10-13 12:39:53\"}', '::1', '2025-10-13 04:39:53'),
(1886, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 04:39:55'),
(1887, 1, 'logout', '{\"timestamp\":\"2025-10-13 12:40:10\"}', '::1', '2025-10-13 04:40:10'),
(1888, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 04:40:15'),
(1889, 171, 'session_scheduled', '{\"match_id\":103,\"date\":\"2025-10-13\"}', '::1', '2025-10-13 05:07:16'),
(1890, 171, 'session_completed', '{\"session_id\":65}', '::1', '2025-10-13 05:08:10'),
(1891, 171, 'logout', '{\"timestamp\":\"2025-10-13 13:08:56\"}', '::1', '2025-10-13 05:08:56'),
(1892, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 05:08:58'),
(1893, 1, 'logout', '{\"timestamp\":\"2025-10-13 13:10:05\"}', '::1', '2025-10-13 05:10:05'),
(1894, 147, 'login', '{\"success\":true}', '::1', '2025-10-13 05:10:09'),
(1895, 147, 'logout', '{\"timestamp\":\"2025-10-13 13:13:51\"}', '::1', '2025-10-13 05:13:51'),
(1896, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 05:13:56'),
(1897, 171, 'logout', '{\"timestamp\":\"2025-10-13 13:14:51\"}', '::1', '2025-10-13 05:14:51'),
(1898, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 05:14:53'),
(1899, 1, 'logout', '{\"timestamp\":\"2025-10-13 13:15:24\"}', '::1', '2025-10-13 05:15:24'),
(1900, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 05:15:29'),
(1901, 171, 'logout', '{\"timestamp\":\"2025-10-13 13:15:53\"}', '::1', '2025-10-13 05:15:53'),
(1902, 1, 'login', '{\"success\":true}', '::1', '2025-10-13 05:15:55'),
(1903, 1, 'logout', '{\"timestamp\":\"2025-10-13 13:16:07\"}', '::1', '2025-10-13 05:16:07'),
(1904, 171, 'login', '{\"success\":true}', '::1', '2025-10-13 05:16:14'),
(1905, 147, 'login', '{\"success\":true}', '127.0.0.1', '2025-10-14 02:05:09'),
(1906, 147, 'logout', '{\"timestamp\":\"2025-10-14 10:05:17\"}', '127.0.0.1', '2025-10-14 02:05:17'),
(1907, 171, 'login', '{\"success\":true}', '127.0.0.1', '2025-10-14 02:05:22'),
(1908, 171, 'logout', '{\"timestamp\":\"2025-10-14 10:15:04\"}', '::1', '2025-10-14 02:15:04'),
(1909, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:15:06'),
(1910, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:17:15\"}', '::1', '2025-10-14 02:17:15'),
(1911, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 02:17:21'),
(1912, 171, 'session_scheduled', '{\"match_id\":103,\"date\":\"2025-10-14\"}', '::1', '2025-10-14 02:17:51'),
(1913, 171, 'session_completed', '{\"session_id\":66}', '::1', '2025-10-14 02:18:07'),
(1914, 171, 'logout', '{\"timestamp\":\"2025-10-14 10:18:21\"}', '::1', '2025-10-14 02:18:21'),
(1915, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:18:23'),
(1916, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:20:50\"}', '::1', '2025-10-14 02:20:50'),
(1917, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 02:22:32'),
(1918, 171, 'logout', '{\"timestamp\":\"2025-10-14 10:23:53\"}', '::1', '2025-10-14 02:23:53'),
(1919, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:24:00'),
(1920, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:24:28\"}', '::1', '2025-10-14 02:24:28'),
(1921, 165, 'login', '{\"success\":true}', '::1', '2025-10-14 02:24:34'),
(1922, 165, 'logout', '{\"timestamp\":\"2025-10-14 10:26:30\"}', '::1', '2025-10-14 02:26:30'),
(1923, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:26:35'),
(1924, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:26:42\"}', '::1', '2025-10-14 02:26:42'),
(1925, 169, 'login', '{\"success\":true}', '::1', '2025-10-14 02:26:47'),
(1926, 169, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-14 02:29:12'),
(1927, 169, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06830099,\"lng\":121.13092562}}', '::1', '2025-10-14 02:29:51'),
(1928, 169, 'logout', '{\"timestamp\":\"2025-10-14 10:30:24\"}', '::1', '2025-10-14 02:30:24'),
(1929, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:30:27'),
(1930, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:30:31\"}', '::1', '2025-10-14 02:30:31'),
(1931, 172, 'register', '{\"role\":\"mentor\",\"referral_used\":false}', '::1', '2025-10-14 02:31:22'),
(1932, 172, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068537507500798,\"lng\":121.13099558027449}}', '::1', '2025-10-14 02:32:49'),
(1933, 172, 'logout', '{\"timestamp\":\"2025-10-14 10:33:10\"}', '::1', '2025-10-14 02:33:10'),
(1934, 169, 'login', '{\"success\":true}', '::1', '2025-10-14 02:33:14'),
(1935, 169, 'logout', '{\"timestamp\":\"2025-10-14 10:33:42\"}', '::1', '2025-10-14 02:33:42'),
(1936, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:33:44'),
(1937, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:33:50\"}', '::1', '2025-10-14 02:33:50'),
(1938, 168, 'login', '{\"success\":true}', '::1', '2025-10-14 02:33:57'),
(1939, 168, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-14 02:34:25'),
(1940, 168, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.06829748,\"lng\":121.13092562}}', '::1', '2025-10-14 02:34:52'),
(1941, 168, 'logout', '{\"timestamp\":\"2025-10-14 10:35:33\"}', '::1', '2025-10-14 02:35:33'),
(1942, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 02:35:36'),
(1943, 1, 'logout', '{\"timestamp\":\"2025-10-14 10:35:46\"}', '::1', '2025-10-14 02:35:46'),
(1944, 167, 'login', '{\"success\":true}', '::1', '2025-10-14 02:35:55'),
(1945, 167, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"referred_by_name\":\"john paul\",\"auto_verified\":true,\"verified_at\":\"2025-10-14 10:40:52\"}', '::1', '2025-10-14 02:40:52'),
(1946, 167, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-14 02:41:21'),
(1947, 167, 'logout', '{\"timestamp\":\"2025-10-14 11:27:07\"}', '::1', '2025-10-14 03:27:07'),
(1948, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 03:27:09'),
(1949, 1, 'logout', '{\"timestamp\":\"2025-10-14 11:31:41\"}', '::1', '2025-10-14 03:31:41'),
(1950, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 03:31:47'),
(1951, 171, 'logout', '{\"timestamp\":\"2025-10-14 11:53:26\"}', '::1', '2025-10-14 03:53:26'),
(1952, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 03:53:28'),
(1953, 1, 'logout', '{\"timestamp\":\"2025-10-14 11:53:55\"}', '::1', '2025-10-14 03:53:55'),
(1954, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 03:54:00'),
(1955, 171, 'logout', '{\"timestamp\":\"2025-10-14 11:57:09\"}', '::1', '2025-10-14 03:57:09'),
(1956, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 03:57:11'),
(1957, 1, 'admin_review_document', '{\"document_id\":8,\"status\":\"approved\",\"user_id\":138}', '::1', '2025-10-14 03:57:21'),
(1958, 1, 'admin_review_document', '{\"document_id\":8,\"status\":\"rejected\",\"user_id\":138}', '::1', '2025-10-14 03:57:29'),
(1959, 1, 'admin_review_document', '{\"document_id\":8,\"status\":\"approved\",\"user_id\":138}', '::1', '2025-10-14 03:57:35'),
(1960, 1, 'logout', '{\"timestamp\":\"2025-10-14 11:57:44\"}', '::1', '2025-10-14 03:57:44'),
(1961, 138, 'login', '{\"success\":true}', '::1', '2025-10-14 03:57:53'),
(1962, 138, 'logout', '{\"timestamp\":\"2025-10-14 12:00:21\"}', '::1', '2025-10-14 04:00:21'),
(1963, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:00:22'),
(1964, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:00:37\"}', '::1', '2025-10-14 04:00:37'),
(1965, 160, 'login', '{\"success\":true}', '::1', '2025-10-14 04:00:43'),
(1966, 160, 'logout', '{\"timestamp\":\"2025-10-14 12:00:54\"}', '::1', '2025-10-14 04:00:54'),
(1967, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:00:56'),
(1968, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:01:00\"}', '::1', '2025-10-14 04:01:00'),
(1969, 169, 'login', '{\"success\":true}', '::1', '2025-10-14 04:01:05'),
(1970, 169, 'logout', '{\"timestamp\":\"2025-10-14 12:02:04\"}', '::1', '2025-10-14 04:02:04'),
(1971, 160, 'login', '{\"success\":true}', '::1', '2025-10-14 04:02:10'),
(1972, 160, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-14 04:02:17'),
(1973, 160, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.04420303,\"lng\":121.15847054}}', '::1', '2025-10-14 04:02:47');
INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1974, 160, 'logout', '{\"timestamp\":\"2025-10-14 12:18:21\"}', '::1', '2025-10-14 04:18:21'),
(1975, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:18:25'),
(1976, 1, 'admin_review_document', '{\"document_id\":9,\"status\":\"approved\",\"user_id\":138}', '::1', '2025-10-14 04:18:32'),
(1977, 1, 'admin_review_document', '{\"document_id\":2,\"status\":\"approved\",\"user_id\":65}', '::1', '2025-10-14 04:18:53'),
(1978, 1, 'admin_review_document', '{\"document_id\":5,\"status\":\"approved\",\"user_id\":119}', '::1', '2025-10-14 04:19:10'),
(1979, 1, 'admin_review_document', '{\"document_id\":9,\"status\":\"rejected\",\"user_id\":138}', '::1', '2025-10-14 04:27:44'),
(1980, 1, 'admin_review_document', '{\"document_id\":9,\"status\":\"approved\",\"user_id\":138}', '::1', '2025-10-14 04:27:59'),
(1981, 1, 'admin_review_document', '{\"document_id\":9,\"status\":\"rejected\",\"user_id\":138}', '::1', '2025-10-14 04:28:05'),
(1982, 1, 'admin_review_document', '{\"document_id\":9,\"status\":\"approved\",\"user_id\":138}', '::1', '2025-10-14 04:28:15'),
(1983, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:28:22\"}', '::1', '2025-10-14 04:28:22'),
(1984, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:28:27'),
(1985, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:28:34\"}', '::1', '2025-10-14 04:28:34'),
(1986, 119, 'login', '{\"success\":true}', '::1', '2025-10-14 04:28:39'),
(1987, 119, 'logout', '{\"timestamp\":\"2025-10-14 12:29:31\"}', '::1', '2025-10-14 04:29:31'),
(1988, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:29:33'),
(1989, 1, 'admin_review_document', '{\"document_id\":10,\"status\":\"rejected\",\"user_id\":119}', '::1', '2025-10-14 04:29:39'),
(1990, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:29:52\"}', '::1', '2025-10-14 04:29:52'),
(1991, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 04:37:03'),
(1992, 171, 'logout', '{\"timestamp\":\"2025-10-14 12:37:38\"}', '::1', '2025-10-14 04:37:38'),
(1993, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:37:45'),
(1994, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:37:52\"}', '::1', '2025-10-14 04:37:52'),
(1995, 3, 'login', '{\"success\":true}', '::1', '2025-10-14 04:37:55'),
(1996, 3, 'match_request', '{\"match_id\":\"104\",\"mentor_id\":42,\"subject\":\"English\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-14 04:45:52'),
(1997, 3, 'match_request_pending', '{\"match_id\":\"104\",\"mentor_id\":42,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 04:45:52'),
(1998, 3, 'logout', '{\"timestamp\":\"2025-10-14 12:45:59\"}', '::1', '2025-10-14 04:45:59'),
(1999, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:46:01'),
(2000, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:46:09\"}', '::1', '2025-10-14 04:46:09'),
(2001, 42, 'login', '{\"success\":true}', '::1', '2025-10-14 04:46:14'),
(2002, 42, 'match_request', '{\"match_id\":\"105\",\"mentor_id\":6,\"subject\":\"History\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-10-14 04:46:53'),
(2003, 42, 'match_request_pending', '{\"match_id\":\"105\",\"mentor_id\":6,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 04:46:53'),
(2004, 42, 'logout', '{\"timestamp\":\"2025-10-14 12:46:54\"}', '::1', '2025-10-14 04:46:54'),
(2005, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:46:55'),
(2006, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:47:10\"}', '::1', '2025-10-14 04:47:10'),
(2007, 6, 'login', '{\"success\":true}', '::1', '2025-10-14 04:47:13'),
(2008, 6, 'logout', '{\"timestamp\":\"2025-10-14 12:53:45\"}', '::1', '2025-10-14 04:53:45'),
(2009, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 04:53:50'),
(2010, 171, 'logout', '{\"timestamp\":\"2025-10-14 12:53:56\"}', '::1', '2025-10-14 04:53:56'),
(2011, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:54:03'),
(2012, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:54:10\"}', '::1', '2025-10-14 04:54:10'),
(2013, 138, 'login', '{\"success\":true}', '::1', '2025-10-14 04:54:16'),
(2014, 138, 'match_request', '{\"match_id\":\"106\",\"mentor_id\":137,\"subject\":\"Trigonometry\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-14 04:54:23'),
(2015, 138, 'match_request_pending', '{\"match_id\":\"106\",\"mentor_id\":137,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 04:54:23'),
(2016, 138, 'logout', '{\"timestamp\":\"2025-10-14 12:54:25\"}', '::1', '2025-10-14 04:54:25'),
(2017, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:54:27'),
(2018, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:54:54\"}', '::1', '2025-10-14 04:54:54'),
(2019, 137, 'login', '{\"success\":true}', '::1', '2025-10-14 04:54:57'),
(2020, 137, 'logout', '{\"timestamp\":\"2025-10-14 12:55:08\"}', '::1', '2025-10-14 04:55:08'),
(2021, 138, 'login', '{\"success\":true}', '::1', '2025-10-14 04:55:13'),
(2022, 138, 'logout', '{\"timestamp\":\"2025-10-14 12:55:28\"}', '::1', '2025-10-14 04:55:28'),
(2023, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:55:42'),
(2024, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:55:59\"}', '::1', '2025-10-14 04:55:59'),
(2025, 137, 'login', '{\"success\":true}', '::1', '2025-10-14 04:56:02'),
(2026, 137, 'match_request', '{\"match_id\":\"107\",\"mentor_id\":143,\"subject\":\"Trigonometry\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-14 04:56:27'),
(2027, 137, 'match_request_pending', '{\"match_id\":\"107\",\"mentor_id\":143,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 04:56:27'),
(2028, 137, 'logout', '{\"timestamp\":\"2025-10-14 12:56:28\"}', '::1', '2025-10-14 04:56:28'),
(2029, 143, 'login', '{\"success\":true}', '::1', '2025-10-14 04:56:35'),
(2030, 143, 'logout', '{\"timestamp\":\"2025-10-14 12:59:15\"}', '::1', '2025-10-14 04:59:15'),
(2031, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:59:17'),
(2032, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:59:25\"}', '::1', '2025-10-14 04:59:25'),
(2033, 169, 'login', '{\"success\":true}', '::1', '2025-10-14 04:59:29'),
(2034, 169, 'logout', '{\"timestamp\":\"2025-10-14 12:59:34\"}', '::1', '2025-10-14 04:59:34'),
(2035, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 04:59:36'),
(2036, 1, 'logout', '{\"timestamp\":\"2025-10-14 12:59:43\"}', '::1', '2025-10-14 04:59:43'),
(2037, 165, 'login', '{\"success\":true}', '::1', '2025-10-14 04:59:48'),
(2038, 165, 'match_request', '{\"match_id\":\"108\",\"mentor_id\":88,\"subject\":\"Social Theory\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-14 04:59:55'),
(2039, 165, 'match_request_pending', '{\"match_id\":\"108\",\"mentor_id\":88,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 04:59:55'),
(2040, 165, 'logout', '{\"timestamp\":\"2025-10-14 13:00:04\"}', '::1', '2025-10-14 05:00:04'),
(2041, 88, 'login_failed', '{\"reason\":\"wrong_password\"}', '::1', '2025-10-14 05:00:08'),
(2042, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 05:00:13'),
(2043, 1, 'logout', '{\"timestamp\":\"2025-10-14 13:00:40\"}', '::1', '2025-10-14 05:00:40'),
(2044, 88, 'login_failed', '{\"reason\":\"wrong_password\"}', '::1', '2025-10-14 05:00:51'),
(2045, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 05:00:55'),
(2046, 1, 'logout', '{\"timestamp\":\"2025-10-14 13:01:13\"}', '::1', '2025-10-14 05:01:13'),
(2047, 155, 'login', '{\"success\":true}', '::1', '2025-10-14 05:01:17'),
(2048, 155, 'match_request', '{\"match_id\":\"109\",\"mentor_id\":117,\"subject\":\"Programming - C++\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-10-14 05:01:57'),
(2049, 155, 'match_request_pending', '{\"match_id\":\"109\",\"mentor_id\":117,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 05:01:57'),
(2050, 155, 'logout', '{\"timestamp\":\"2025-10-14 13:02:04\"}', '::1', '2025-10-14 05:02:04'),
(2051, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 05:02:06'),
(2052, 1, 'logout', '{\"timestamp\":\"2025-10-14 13:02:14\"}', '::1', '2025-10-14 05:02:14'),
(2053, 117, 'login', '{\"success\":true}', '::1', '2025-10-14 05:02:17'),
(2054, 117, 'logout', '{\"timestamp\":\"2025-10-14 13:13:15\"}', '::1', '2025-10-14 05:13:15'),
(2055, 147, 'login', '{\"success\":true}', '::1', '2025-10-14 05:13:20'),
(2056, 147, 'match_request', '{\"match_id\":\"110\",\"mentor_id\":88,\"subject\":\"Abnormal Psychology\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-10-14 05:13:29'),
(2057, 147, 'match_request_pending', '{\"match_id\":\"110\",\"mentor_id\":88,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 05:13:29'),
(2058, 147, 'logout', '{\"timestamp\":\"2025-10-14 13:13:36\"}', '::1', '2025-10-14 05:13:36'),
(2059, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 05:13:38'),
(2060, 1, 'logout', '{\"timestamp\":\"2025-10-14 13:13:47\"}', '::1', '2025-10-14 05:13:47'),
(2061, 171, 'login', '{\"success\":true}', '::1', '2025-10-14 05:13:53'),
(2062, 171, 'logout', '{\"timestamp\":\"2025-10-14 13:14:00\"}', '::1', '2025-10-14 05:14:00'),
(2063, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 05:14:05'),
(2064, 1, 'logout', '{\"timestamp\":\"2025-10-14 13:14:09\"}', '::1', '2025-10-14 05:14:09'),
(2065, 170, 'login', '{\"success\":true}', '::1', '2025-10-14 05:14:14'),
(2066, 170, 'match_request', '{\"match_id\":\"111\",\"mentor_id\":83,\"subject\":\"C++\",\"student_role\":\"mentor\",\"mentor_role\":\"peer\"}', '::1', '2025-10-14 05:14:25'),
(2067, 170, 'match_request_pending', '{\"match_id\":\"111\",\"mentor_id\":83,\"delivery_method\":\"pending\"}', '::1', '2025-10-14 05:14:25'),
(2068, 170, 'logout', '{\"timestamp\":\"2025-10-14 13:14:26\"}', '::1', '2025-10-14 05:14:26'),
(2069, 1, 'login', '{\"success\":true}', '::1', '2025-10-14 05:14:28'),
(2070, 1, 'logout', '{\"timestamp\":\"2025-10-14 13:14:38\"}', '::1', '2025-10-14 05:14:38'),
(2071, 83, 'login', '{\"success\":true}', '::1', '2025-10-14 05:14:40'),
(2072, 83, 'logout', '{\"timestamp\":\"2025-10-14 13:14:50\"}', '::1', '2025-10-14 05:14:50'),
(2073, 170, 'login', '{\"success\":true}', '::1', '2025-10-14 05:14:55'),
(2074, 1, 'login', '{\"success\":true}', '::1', '2025-10-15 11:59:52'),
(2075, 1, 'logout', '{\"timestamp\":\"2025-10-15 20:33:00\"}', '::1', '2025-10-15 12:33:00'),
(2076, 173, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-15 12:33:39'),
(2077, 173, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.069956,\"lng\":121.130051}}', '::1', '2025-10-15 12:34:50'),
(2078, 173, 'logout', '{\"timestamp\":\"2025-10-15 20:37:45\"}', '::1', '2025-10-15 12:37:45'),
(2079, 174, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-15 12:38:23'),
(2080, 174, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.070317872352884,\"lng\":121.13072106351179}}', '::1', '2025-10-15 12:39:08'),
(2081, 174, 'match_request', '{\"match_id\":\"112\",\"mentor_id\":147,\"subject\":\"Abnormal Psychology\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-15 12:39:18'),
(2082, 174, 'match_request_pending', '{\"match_id\":\"112\",\"mentor_id\":147,\"delivery_method\":\"pending\"}', '::1', '2025-10-15 12:39:18'),
(2083, 174, 'match_response', '{\"match_id\":112,\"response\":\"accepted\"}', '::1', '2025-10-15 12:40:15'),
(2084, 174, 'session_scheduled', '{\"match_id\":112,\"date\":\"2025-10-15\"}', '::1', '2025-10-15 12:40:26'),
(2085, 174, 'session_completed', '{\"session_id\":67}', '::1', '2025-10-15 12:41:19'),
(2086, 174, 'logout', '{\"timestamp\":\"2025-10-15 20:41:42\"}', '::1', '2025-10-15 12:41:42'),
(2087, 1, 'login', '{\"success\":true}', '::1', '2025-10-15 12:41:50'),
(2088, 1, 'logout', '{\"timestamp\":\"2025-10-15 20:41:59\"}', '::1', '2025-10-15 12:41:59'),
(2089, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 12:42:03'),
(2090, 147, 'session_rated', '{\"session_id\":67,\"rating\":5}', '::1', '2025-10-15 12:42:19'),
(2091, 147, 'match_request', '{\"match_id\":\"113\",\"mentor_id\":173,\"subject\":\"Abnormal Psychology\",\"student_role\":\"mentor\",\"mentor_role\":\"student\"}', '::1', '2025-10-15 12:42:36'),
(2092, 147, 'match_request_pending', '{\"match_id\":\"113\",\"mentor_id\":173,\"delivery_method\":\"pending\"}', '::1', '2025-10-15 12:42:36'),
(2093, 147, 'logout', '{\"timestamp\":\"2025-10-15 20:42:38\"}', '::1', '2025-10-15 12:42:38'),
(2094, 173, 'login', '{\"success\":true}', '::1', '2025-10-15 12:42:43'),
(2095, 173, 'logout', '{\"timestamp\":\"2025-10-15 20:42:55\"}', '::1', '2025-10-15 12:42:55'),
(2096, 1, 'login', '{\"success\":true}', '::1', '2025-10-15 12:42:57'),
(2097, 1, 'logout', '{\"timestamp\":\"2025-10-15 20:43:05\"}', '::1', '2025-10-15 12:43:05'),
(2098, 173, 'login', '{\"success\":true}', '::1', '2025-10-15 12:43:14'),
(2099, 173, 'logout', '{\"timestamp\":\"2025-10-15 20:43:26\"}', '::1', '2025-10-15 12:43:26'),
(2100, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 12:43:34'),
(2101, 147, 'session_scheduled', '{\"match_id\":113,\"date\":\"2025-10-15\"}', '::1', '2025-10-15 12:43:54'),
(2102, 147, 'session_completed', '{\"session_id\":68}', '::1', '2025-10-15 12:48:10'),
(2103, 147, 'logout', '{\"timestamp\":\"2025-10-15 20:48:46\"}', '::1', '2025-10-15 12:48:46'),
(2104, 173, 'login', '{\"success\":true}', '::1', '2025-10-15 12:48:51'),
(2105, 173, 'session_rated', '{\"session_id\":68,\"rating\":5}', '::1', '2025-10-15 12:48:57'),
(2106, 173, 'session_scheduled', '{\"match_id\":113,\"date\":\"2025-10-15\"}', '::1', '2025-10-15 12:49:15'),
(2107, 173, 'session_completed', '{\"session_id\":69}', '::1', '2025-10-15 12:51:18'),
(2108, 173, 'logout', '{\"timestamp\":\"2025-10-15 20:51:26\"}', '::1', '2025-10-15 12:51:26'),
(2109, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 12:51:30'),
(2110, 147, 'logout', '{\"timestamp\":\"2025-10-15 20:52:59\"}', '::1', '2025-10-15 12:52:59'),
(2111, 175, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-15 12:54:01'),
(2112, 175, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.070165465370398,\"lng\":121.13012542156862}}', '::1', '2025-10-15 12:54:31'),
(2113, 175, 'match_request', '{\"match_id\":\"114\",\"mentor_id\":147,\"subject\":\"Abnormal Psychology\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-15 12:55:07'),
(2114, 175, 'match_request_pending', '{\"match_id\":\"114\",\"mentor_id\":147,\"delivery_method\":\"pending\"}', '::1', '2025-10-15 12:55:07'),
(2115, 175, 'logout', '{\"timestamp\":\"2025-10-15 20:55:10\"}', '::1', '2025-10-15 12:55:10'),
(2116, 175, 'login', '{\"success\":true}', '::1', '2025-10-15 12:55:33'),
(2117, 175, 'logout', '{\"timestamp\":\"2025-10-15 20:55:42\"}', '::1', '2025-10-15 12:55:42'),
(2118, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 12:55:46'),
(2119, 147, 'match_response', '{\"match_id\":114,\"response\":\"accepted\"}', '::1', '2025-10-15 12:55:50'),
(2120, 147, 'message_sent', '{\"match_id\":100,\"partner_id\":148}', '::1', '2025-10-15 12:55:59'),
(2121, 147, 'session_scheduled', '{\"match_id\":114,\"date\":\"2025-10-15\"}', '::1', '2025-10-15 12:56:18'),
(2122, 147, 'session_completed', '{\"session_id\":70}', '::1', '2025-10-15 13:05:51'),
(2123, 147, 'logout', '{\"timestamp\":\"2025-10-15 21:06:00\"}', '::1', '2025-10-15 13:06:00'),
(2124, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 13:06:06'),
(2125, 147, 'session_scheduled', '{\"match_id\":114,\"date\":\"2025-10-15\"}', '::1', '2025-10-15 13:06:22'),
(2126, 147, 'logout', '{\"timestamp\":\"2025-10-15 21:09:18\"}', '::1', '2025-10-15 13:09:18'),
(2127, 175, 'login', '{\"success\":true}', '::1', '2025-10-15 13:09:24'),
(2128, 175, 'session_completed', '{\"session_id\":71}', '::1', '2025-10-15 13:09:30'),
(2129, 175, 'logout', '{\"timestamp\":\"2025-10-15 21:09:35\"}', '::1', '2025-10-15 13:09:35'),
(2130, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 13:09:41'),
(2131, 147, 'logout', '{\"timestamp\":\"2025-10-15 21:14:35\"}', '::1', '2025-10-15 13:14:35'),
(2132, 176, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-15 13:16:08'),
(2133, 176, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.070405447247264,\"lng\":121.13067199213505}}', '::1', '2025-10-15 13:16:31'),
(2134, 176, 'match_request', '{\"match_id\":\"115\",\"mentor_id\":166,\"subject\":\"C++\",\"student_role\":\"student\",\"mentor_role\":\"mentor\"}', '::1', '2025-10-15 13:17:15'),
(2135, 176, 'match_request_pending', '{\"match_id\":\"115\",\"mentor_id\":166,\"delivery_method\":\"pending\"}', '::1', '2025-10-15 13:17:15'),
(2136, 176, 'logout', '{\"timestamp\":\"2025-10-15 21:17:21\"}', '::1', '2025-10-15 13:17:21'),
(2137, 166, 'login', '{\"success\":true}', '::1', '2025-10-15 13:17:25'),
(2138, 166, 'match_response', '{\"match_id\":115,\"response\":\"accepted\"}', '::1', '2025-10-15 13:17:45'),
(2139, 166, 'session_scheduled', '{\"match_id\":115,\"date\":\"2025-10-15\"}', '::1', '2025-10-15 13:18:24'),
(2140, 166, 'session_completed', '{\"session_id\":72}', '::1', '2025-10-15 13:19:28'),
(2141, 166, 'logout', '{\"timestamp\":\"2025-10-15 21:19:35\"}', '::1', '2025-10-15 13:19:35'),
(2142, 1, 'login', '{\"success\":true}', '::1', '2025-10-15 13:19:36'),
(2143, 1, 'logout', '{\"timestamp\":\"2025-10-15 21:22:10\"}', '::1', '2025-10-15 13:22:10'),
(2144, 147, 'login', '{\"success\":true}', '::1', '2025-10-15 13:22:18'),
(2145, 147, 'message_sent', '{\"match_id\":113,\"partner_id\":173}', '::1', '2025-10-15 13:23:56'),
(2146, 147, 'logout', '{\"timestamp\":\"2025-10-15 21:37:37\"}', '::1', '2025-10-15 13:37:37'),
(2147, 173, 'login', '{\"success\":true}', '::1', '2025-10-15 13:37:43'),
(2148, 173, 'upgrade_to_peer', '{\"previous_role\":\"student\",\"new_role\":\"peer\",\"referral_code\":\"MENTORD3A160\",\"referral_code_id\":10,\"referred_by\":147,\"auto_verified\":true}', '::1', '2025-10-15 13:37:54'),
(2149, 173, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.069956,\"lng\":121.130051}}', '::1', '2025-10-15 13:38:24'),
(2150, 173, 'logout', '{\"timestamp\":\"2025-10-15 22:28:36\"}', '::1', '2025-10-15 14:28:36'),
(2151, 1, 'login', '{\"success\":true}', '::1', '2025-10-15 14:47:22'),
(2152, 1, 'logout', '{\"timestamp\":\"2025-10-16 21:01:55\"}', '::1', '2025-10-16 13:01:55'),
(2153, 177, 'register', '{\"role\":\"student\",\"referral_used\":false,\"age\":22}', '::1', '2025-10-16 13:02:58'),
(2154, 177, 'age_verification_upload', '{\"filename\":\"age_proof_177_1760619805.jpg\"}', '::1', '2025-10-16 13:03:25'),
(2155, 177, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-16 13:03:46'),
(2156, 177, 'age_verification_upload', '{\"filename\":\"age_proof_177_1760619850.jpg\"}', '::1', '2025-10-16 13:04:10'),
(2157, 177, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068645,\"lng\":121.13101}}', '::1', '2025-10-16 13:04:20'),
(2158, 177, 'logout', '{\"timestamp\":\"2025-10-16 21:11:04\"}', '::1', '2025-10-16 13:11:04'),
(2159, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 13:11:07'),
(2160, 1, 'logout', '{\"timestamp\":\"2025-10-16 21:16:47\"}', '::1', '2025-10-16 13:16:47'),
(2161, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 13:21:36'),
(2162, 1, 'logout', '{\"timestamp\":\"2025-10-16 21:22:12\"}', '::1', '2025-10-16 13:22:12'),
(2163, 178, 'register', '{\"role\":\"student\",\"referral_used\":false,\"age\":18}', '::1', '2025-10-16 13:24:11'),
(2164, 178, 'age_verification_upload', '{\"filename\":\"age_proof_178_1760621065.jpg\"}', '::1', '2025-10-16 13:24:25'),
(2165, 178, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-16 13:24:51'),
(2166, 178, 'logout', '{\"timestamp\":\"2025-10-16 21:24:57\"}', '::1', '2025-10-16 13:24:57'),
(2167, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 13:24:59'),
(2168, 1, 'logout', '{\"timestamp\":\"2025-10-16 21:25:28\"}', '::1', '2025-10-16 13:25:28'),
(2169, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 13:30:43'),
(2170, 1, 'logout', '{\"timestamp\":\"2025-10-16 21:34:19\"}', '::1', '2025-10-16 13:34:19'),
(2171, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 13:34:25'),
(2172, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:34:50'),
(2173, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:34:51'),
(2174, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:34:51'),
(2175, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:34:52'),
(2176, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:34:53'),
(2177, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:34:59'),
(2178, 40, 'user_reported', '{\"reported_user_id\":42,\"reason\":\"inappropriate\"}', '::1', '2025-10-16 13:35:40'),
(2179, 40, 'user_reported', '{\"reported_user_id\":42,\"reason\":\"inappropriate\"}', '::1', '2025-10-16 13:35:51'),
(2180, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:36:44'),
(2181, 40, 'user_reported', '{\"reported_user_id\":42,\"reason\":\"inappropriate\"}', '::1', '2025-10-16 13:36:55'),
(2182, 40, 'message_sent', '{\"match_id\":42,\"partner_id\":42}', '::1', '2025-10-16 13:36:58'),
(2183, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:38:35'),
(2184, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:39:07'),
(2185, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:40:10'),
(2186, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:40:59'),
(2187, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:43:40'),
(2188, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:43:47'),
(2189, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:45:03'),
(2190, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:45:08'),
(2191, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:51:29'),
(2192, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:51:38'),
(2193, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:51:47'),
(2194, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:53:17'),
(2195, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:59:40'),
(2196, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 13:59:44'),
(2197, 40, 'message_sent', '{\"match_id\":37,\"partner_id\":43}', '::1', '2025-10-16 14:00:11'),
(2198, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:07:54'),
(2199, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:07:57'),
(2200, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:09:52'),
(2201, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:09:59'),
(2202, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:10:02'),
(2203, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:12:11'),
(2204, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:12:48\"}', '::1', '2025-10-16 14:12:48'),
(2205, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:16:28'),
(2206, 40, 'message_sent', '{\"match_id\":36,\"partner_id\":15}', '::1', '2025-10-16 14:16:31'),
(2207, 40, 'user_reported', '{\"reported_user_id\":15,\"reason\":\"no_show\"}', '::1', '2025-10-16 14:16:50'),
(2208, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:16:51\"}', '::1', '2025-10-16 14:16:51'),
(2209, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:16:54'),
(2210, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:19:08\"}', '::1', '2025-10-16 14:19:08'),
(2211, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:19:23'),
(2212, 1, 'admin_activate_user', '{\"activated_user_id\":42}', '::1', '2025-10-16 14:19:50'),
(2213, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:21:09\"}', '::1', '2025-10-16 14:21:09'),
(2214, 42, 'login', '{\"success\":true}', '::1', '2025-10-16 14:21:16'),
(2215, 42, 'logout', '{\"timestamp\":\"2025-10-16 22:22:39\"}', '::1', '2025-10-16 14:22:39'),
(2216, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:22:41'),
(2217, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:29:19\"}', '::1', '2025-10-16 14:29:19'),
(2218, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:29:24'),
(2219, 40, 'match_request', '{\"match_id\":\"116\",\"mentor_id\":117,\"subject\":\"Programming - C++\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-10-16 14:31:38'),
(2220, 40, 'match_request_pending', '{\"match_id\":\"116\",\"mentor_id\":117,\"delivery_method\":\"pending\"}', '::1', '2025-10-16 14:31:38'),
(2221, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:31:43\"}', '::1', '2025-10-16 14:31:43'),
(2222, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:31:45'),
(2223, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:31:55\"}', '::1', '2025-10-16 14:31:55'),
(2224, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:32:02'),
(2225, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:32:06\"}', '::1', '2025-10-16 14:32:06'),
(2226, 117, 'login', '{\"success\":true}', '::1', '2025-10-16 14:32:12'),
(2227, 117, 'logout', '{\"timestamp\":\"2025-10-16 22:33:09\"}', '::1', '2025-10-16 14:33:09'),
(2228, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:33:11'),
(2229, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:33:16\"}', '::1', '2025-10-16 14:33:16'),
(2230, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:33:19'),
(2231, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:33:20\"}', '::1', '2025-10-16 14:33:20'),
(2232, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:33:26'),
(2233, 40, 'session_completed', '{\"session_id\":27}', '::1', '2025-10-16 14:33:43'),
(2234, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:34:09\"}', '::1', '2025-10-16 14:34:09'),
(2235, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:34:12'),
(2236, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:34:30\"}', '::1', '2025-10-16 14:34:30'),
(2237, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:34:36'),
(2238, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:34:40\"}', '::1', '2025-10-16 14:34:40'),
(2239, 117, 'login', '{\"success\":true}', '::1', '2025-10-16 14:34:45'),
(2240, 117, 'logout', '{\"timestamp\":\"2025-10-16 22:37:59\"}', '::1', '2025-10-16 14:37:59'),
(2241, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:38:00'),
(2242, 1, 'logout', '{\"timestamp\":\"2025-10-16 22:39:55\"}', '::1', '2025-10-16 14:39:55'),
(2243, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:40:01'),
(2244, 40, 'match_request', '{\"match_id\":\"117\",\"mentor_id\":64,\"subject\":\"Programming - C++\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-10-16 14:40:09'),
(2245, 40, 'match_request_pending', '{\"match_id\":\"117\",\"mentor_id\":64,\"delivery_method\":\"pending\"}', '::1', '2025-10-16 14:40:09'),
(2246, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:40:11\"}', '::1', '2025-10-16 14:40:11'),
(2247, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:40:24'),
(2248, 40, 'match_request', '{\"match_id\":\"118\",\"mentor_id\":155,\"subject\":\"Programming - C++\",\"student_role\":\"peer\",\"mentor_role\":\"peer\"}', '::1', '2025-10-16 14:40:33'),
(2249, 40, 'match_request_pending', '{\"match_id\":\"118\",\"mentor_id\":155,\"delivery_method\":\"pending\"}', '::1', '2025-10-16 14:40:33'),
(2250, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:40:35\"}', '::1', '2025-10-16 14:40:35'),
(2251, 155, 'login', '{\"success\":true}', '::1', '2025-10-16 14:40:40'),
(2252, 155, 'logout', '{\"timestamp\":\"2025-10-16 22:41:08\"}', '::1', '2025-10-16 14:41:08'),
(2253, 117, 'login', '{\"success\":true}', '::1', '2025-10-16 14:41:14'),
(2254, 117, 'logout', '{\"timestamp\":\"2025-10-16 22:41:23\"}', '::1', '2025-10-16 14:41:23'),
(2255, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:41:31'),
(2256, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:41:41\"}', '::1', '2025-10-16 14:41:41'),
(2257, 40, 'login', '{\"success\":true}', '::1', '2025-10-16 14:43:16'),
(2258, 40, 'logout', '{\"timestamp\":\"2025-10-16 22:44:25\"}', '::1', '2025-10-16 14:44:25'),
(2259, 1, 'login', '{\"success\":true}', '::1', '2025-10-16 14:44:27'),
(2260, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:17:18\"}', '::1', '2025-10-17 03:17:18'),
(2261, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:17:21'),
(2262, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:17:23\"}', '::1', '2025-10-17 03:17:23'),
(2263, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 03:17:31'),
(2264, 40, 'logout', '{\"timestamp\":\"2025-10-17 11:19:21\"}', '::1', '2025-10-17 03:19:21'),
(2265, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:19:23'),
(2266, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:20:01\"}', '::1', '2025-10-17 03:20:01'),
(2267, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 03:20:08'),
(2268, 40, 'match_request', '{\"match_id\":\"119\",\"mentor_id\":78,\"subject\":\"Programming - C++\",\"student_role\":\"peer\",\"mentor_role\":\"student\"}', '::1', '2025-10-17 03:20:37'),
(2269, 40, 'match_request_pending', '{\"match_id\":\"119\",\"mentor_id\":78,\"delivery_method\":\"pending\"}', '::1', '2025-10-17 03:20:37'),
(2270, 40, 'logout', '{\"timestamp\":\"2025-10-17 11:20:41\"}', '::1', '2025-10-17 03:20:41'),
(2271, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:20:43'),
(2272, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:20:46\"}', '::1', '2025-10-17 03:20:46'),
(2273, 78, 'login', '{\"success\":true}', '::1', '2025-10-17 03:20:55'),
(2274, 78, 'user_reported', '{\"reported_user_id\":119,\"reason\":\"no_show\"}', '::1', '2025-10-17 03:22:06'),
(2275, 78, 'logout', '{\"timestamp\":\"2025-10-17 11:22:10\"}', '::1', '2025-10-17 03:22:10'),
(2276, 78, 'login', '{\"success\":true}', '::1', '2025-10-17 03:22:12'),
(2277, 78, 'logout', '{\"timestamp\":\"2025-10-17 11:22:14\"}', '::1', '2025-10-17 03:22:14'),
(2278, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:22:20'),
(2279, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:24:42\"}', '::1', '2025-10-17 03:24:42'),
(2280, 78, 'login', '{\"success\":true}', '::1', '2025-10-17 03:24:49'),
(2281, 78, 'logout', '{\"timestamp\":\"2025-10-17 11:25:11\"}', '::1', '2025-10-17 03:25:11'),
(2282, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 03:25:18'),
(2283, 147, 'logout', '{\"timestamp\":\"2025-10-17 11:25:51\"}', '::1', '2025-10-17 03:25:51'),
(2284, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:26:04'),
(2285, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:26:58\"}', '::1', '2025-10-17 03:26:58'),
(2286, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 03:27:05'),
(2287, 40, 'logout', '{\"timestamp\":\"2025-10-17 11:27:09\"}', '::1', '2025-10-17 03:27:09'),
(2288, 91, 'login', '{\"success\":true}', '::1', '2025-10-17 03:27:13'),
(2289, 91, 'logout', '{\"timestamp\":\"2025-10-17 11:28:44\"}', '::1', '2025-10-17 03:28:44'),
(2290, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:28:45'),
(2291, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:28:58\"}', '::1', '2025-10-17 03:28:58'),
(2292, 17, 'login', '{\"success\":true}', '::1', '2025-10-17 03:29:04'),
(2293, 17, 'logout', '{\"timestamp\":\"2025-10-17 11:29:09\"}', '::1', '2025-10-17 03:29:09'),
(2294, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:29:11'),
(2295, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:29:50\"}', '::1', '2025-10-17 03:29:50'),
(2296, 42, 'login', '{\"success\":true}', '::1', '2025-10-17 03:29:55'),
(2297, 42, 'logout', '{\"timestamp\":\"2025-10-17 11:30:43\"}', '::1', '2025-10-17 03:30:43'),
(2298, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:30:45'),
(2299, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:31:00\"}', '::1', '2025-10-17 03:31:00'),
(2300, 42, 'login', '{\"success\":true}', '::1', '2025-10-17 03:31:09'),
(2301, 42, 'logout', '{\"timestamp\":\"2025-10-17 11:31:13\"}', '::1', '2025-10-17 03:31:13'),
(2302, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:31:26'),
(2303, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:43:35\"}', '::1', '2025-10-17 03:43:35'),
(2304, 179, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-17 03:44:13'),
(2305, 179, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068297476074616,\"lng\":121.13092561719382}}', '::1', '2025-10-17 03:44:38'),
(2306, 179, 'logout', '{\"timestamp\":\"2025-10-17 11:44:53\"}', '::1', '2025-10-17 03:44:53'),
(2307, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 03:44:55'),
(2308, 1, 'logout', '{\"timestamp\":\"2025-10-17 11:51:27\"}', '::1', '2025-10-17 03:51:27'),
(2309, NULL, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-17 03:52:37'),
(2310, NULL, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.068300992024872,\"lng\":121.13092561719382}}', '::1', '2025-10-17 03:52:54'),
(2311, NULL, 'match_request', '{\"match_id\":\"120\",\"mentor_id\":155,\"subject\":\"Social Psychology\",\"student_role\":\"student\",\"mentor_role\":\"peer\"}', '::1', '2025-10-17 03:53:07'),
(2312, NULL, 'match_request_pending', '{\"match_id\":\"120\",\"mentor_id\":155,\"delivery_method\":\"pending\"}', '::1', '2025-10-17 03:53:07'),
(2313, NULL, 'logout', '{\"timestamp\":\"2025-10-17 11:53:10\"}', '::1', '2025-10-17 03:53:10'),
(2314, 155, 'login', '{\"success\":true}', '::1', '2025-10-17 03:53:15'),
(2315, 155, 'match_response', '{\"match_id\":120,\"response\":\"accepted\"}', '::1', '2025-10-17 03:53:29'),
(2316, 155, 'logout', '{\"timestamp\":\"2025-10-17 11:53:30\"}', '::1', '2025-10-17 03:53:30'),
(2317, NULL, 'login', '{\"success\":true}', '::1', '2025-10-17 03:53:36'),
(2318, NULL, 'logout', '{\"timestamp\":\"2025-10-17 19:25:20\"}', '::1', '2025-10-17 11:25:20'),
(2319, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 11:25:26'),
(2320, 40, 'logout', '{\"timestamp\":\"2025-10-17 19:25:27\"}', '::1', '2025-10-17 11:25:27'),
(2321, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 11:25:30'),
(2322, 1, 'logout', '{\"timestamp\":\"2025-10-17 19:25:40\"}', '::1', '2025-10-17 11:25:40'),
(2323, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 11:25:46'),
(2324, 147, 'logout', '{\"timestamp\":\"2025-10-17 19:26:43\"}', '::1', '2025-10-17 11:26:43'),
(2325, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 11:36:02'),
(2326, 147, 'message_sent', '{\"match_id\":113,\"partner_id\":173}', '::1', '2025-10-17 11:36:12'),
(2327, 147, 'message_sent', '{\"match_id\":113,\"partner_id\":173}', '::1', '2025-10-17 11:43:58'),
(2328, 147, 'message_sent', '{\"match_id\":113,\"partner_id\":173}', '::1', '2025-10-17 11:44:00'),
(2329, 147, 'logout', '{\"timestamp\":\"2025-10-17 20:01:44\"}', '::1', '2025-10-17 12:01:44'),
(2330, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 12:03:38'),
(2331, 1, 'logout', '{\"timestamp\":\"2025-10-17 20:07:13\"}', '::1', '2025-10-17 12:07:13'),
(2332, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 12:10:12'),
(2333, 1, 'logout', '{\"timestamp\":\"2025-10-17 20:26:31\"}', '::1', '2025-10-17 12:26:31'),
(2334, 181, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-17 12:28:05'),
(2335, 181, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.09248729577593,\"lng\":121.05842661125808}}', '::1', '2025-10-17 12:28:47'),
(2336, 181, 'logout', '{\"timestamp\":\"2025-10-17 20:36:11\"}', '::1', '2025-10-17 12:36:11'),
(2337, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 12:46:59'),
(2338, 1, 'logout', '{\"timestamp\":\"2025-10-17 20:47:51\"}', '::1', '2025-10-17 12:47:51'),
(2339, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 12:52:29'),
(2340, 40, 'logout', '{\"timestamp\":\"2025-10-17 20:52:50\"}', '::1', '2025-10-17 12:52:50'),
(2341, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 12:59:23'),
(2342, 40, 'logout', '{\"timestamp\":\"2025-10-17 21:00:56\"}', '::1', '2025-10-17 13:00:56'),
(2343, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 13:01:03'),
(2344, 147, 'logout', '{\"timestamp\":\"2025-10-17 21:01:19\"}', '::1', '2025-10-17 13:01:19'),
(2345, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 13:01:23'),
(2346, 1, 'logout', '{\"timestamp\":\"2025-10-17 21:40:30\"}', '::1', '2025-10-17 13:40:30'),
(2347, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 13:40:36'),
(2348, 147, 'logout', '{\"timestamp\":\"2025-10-17 21:40:52\"}', '::1', '2025-10-17 13:40:52'),
(2349, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 13:40:54'),
(2350, 1, 'logout', '{\"timestamp\":\"2025-10-17 22:03:59\"}', '::1', '2025-10-17 14:03:59'),
(2351, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 14:04:05'),
(2352, 147, 'logout', '{\"timestamp\":\"2025-10-17 22:09:57\"}', '::1', '2025-10-17 14:09:57'),
(2353, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 14:09:59'),
(2354, 1, 'logout', '{\"timestamp\":\"2025-10-17 22:11:51\"}', '::1', '2025-10-17 14:11:51'),
(2355, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 14:12:06'),
(2356, 147, 'user_reported', '{\"reported_user_id\":173,\"reason\":\"fake_profile\"}', '::1', '2025-10-17 14:12:30'),
(2357, 147, 'logout', '{\"timestamp\":\"2025-10-17 22:12:33\"}', '::1', '2025-10-17 14:12:33'),
(2358, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 14:12:35'),
(2359, 1, 'logout', '{\"timestamp\":\"2025-10-17 22:41:06\"}', '::1', '2025-10-17 14:41:06'),
(2360, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 15:27:39'),
(2361, 1, 'logout', '{\"timestamp\":\"2025-10-18 00:06:21\"}', '::1', '2025-10-17 16:06:21'),
(2362, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 16:06:44'),
(2363, 1, 'logout', '{\"timestamp\":\"2025-10-18 01:07:23\"}', '::1', '2025-10-17 17:07:23'),
(2364, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 17:07:29'),
(2365, 40, 'session_scheduled', '{\"match_id\":116,\"date\":\"2025-10-18\"}', '::1', '2025-10-17 17:53:37'),
(2366, 40, 'session_cancelled', '{\"session_id\":73,\"reason\":\"Cancelled by user\",\"admin_cancel\":false}', '::1', '2025-10-17 17:58:10'),
(2367, 40, 'logout', '{\"timestamp\":\"2025-10-18 01:59:29\"}', '::1', '2025-10-17 17:59:29'),
(2368, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 17:59:31'),
(2369, 1, 'admin_deactivate_user', '{\"deactivated_user_id\":181}', '::1', '2025-10-17 18:03:38'),
(2370, 1, 'admin_activate_user', '{\"activated_user_id\":181}', '::1', '2025-10-17 18:03:48'),
(2371, 1, 'admin_verify_user', '{\"verified_user_id\":181}', '::1', '2025-10-17 18:04:08'),
(2372, 1, 'admin_verify_user', '{\"verified_user_id\":181}', '::1', '2025-10-17 18:05:26'),
(2373, 1, 'admin_verify_user', '{\"verified_user_id\":181}', '::1', '2025-10-17 18:06:14'),
(2374, 1, 'admin_delete_user', '{\"deleted_user_id\":180}', '::1', '2025-10-17 18:06:22'),
(2375, 1, 'logout', '{\"timestamp\":\"2025-10-18 02:12:13\"}', '::1', '2025-10-17 18:12:13'),
(2376, 40, 'login', '{\"success\":true}', '::1', '2025-10-17 18:12:18'),
(2377, 40, 'logout', '{\"timestamp\":\"2025-10-18 02:12:58\"}', '::1', '2025-10-17 18:12:58'),
(2378, 1, 'login', '{\"success\":true}', '::1', '2025-10-17 18:16:48'),
(2379, 1, 'logout', '{\"timestamp\":\"2025-10-18 05:59:43\"}', '::1', '2025-10-17 21:59:43'),
(2380, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 21:59:49'),
(2381, 147, 'logout', '{\"timestamp\":\"2025-10-18 06:38:17\"}', '::1', '2025-10-17 22:38:17'),
(2382, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 22:38:23'),
(2383, 147, 'logout', '{\"timestamp\":\"2025-10-18 07:01:24\"}', '::1', '2025-10-17 23:01:24'),
(2384, 147, 'login_failed', '{\"reason\":\"wrong_password\"}', '::1', '2025-10-17 23:01:29'),
(2385, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 23:01:33'),
(2386, 147, 'message_sent', '{\"match_id\":113,\"partner_id\":173}', '::1', '2025-10-17 23:32:45'),
(2387, 147, 'logout', '{\"timestamp\":\"2025-10-18 07:40:10\"}', '::1', '2025-10-17 23:40:10'),
(2388, 147, 'login_failed', '{\"reason\":\"wrong_password\"}', '::1', '2025-10-17 23:40:19'),
(2389, 147, 'login', '{\"success\":true}', '::1', '2025-10-17 23:40:24'),
(2390, 147, 'logout', '{\"timestamp\":\"2025-10-18 09:47:37\"}', '::1', '2025-10-18 01:47:37'),
(2391, 182, 'register', '{\"role\":\"student\",\"referral_used\":false}', '::1', '2025-10-18 01:50:34'),
(2392, 182, 'auto_location_match', '{\"auto_match\":true,\"location_based\":true,\"matches_found\":5,\"coordinates\":{\"lat\":14.092829826554372,\"lng\":121.05794505033762}}', '::1', '2025-10-18 01:50:57'),
(2393, 182, 'logout', '{\"timestamp\":\"2025-10-18 11:15:47\"}', '::1', '2025-10-18 03:15:47'),
(2394, 1, 'login', '{\"success\":true}', '::1', '2025-10-18 03:15:50'),
(2395, 1, 'logout', '{\"timestamp\":\"2025-10-18 13:24:27\"}', '::1', '2025-10-18 05:24:27'),
(2396, 1, 'login', '{\"success\":true}', '::1', '2025-10-18 05:31:45'),
(2397, 1, 'admin_review_document', '{\"document_id\":7,\"status\":\"approved\",\"user_id\":138}', '::1', '2025-10-18 05:34:17'),
(2398, 1, 'admin_verify_user', '{\"verified_user_id\":176}', '::1', '2025-10-18 05:49:44'),
(2399, 1, 'logout', '{\"timestamp\":\"2025-10-18 15:44:49\"}', '::1', '2025-10-18 07:44:49'),
(2400, 147, 'login_failed', '{\"reason\":\"wrong_password\"}', '::1', '2025-10-18 07:45:00'),
(2401, 147, 'login', '{\"success\":true}', '::1', '2025-10-18 07:45:05');

-- --------------------------------------------------------

--
-- Table structure for table `user_availability`
--

CREATE TABLE `user_availability` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_availability`
--

INSERT INTO `user_availability` (`id`, `user_id`, `day_of_week`, `start_time`, `end_time`, `is_active`) VALUES
(1, 6, 'saturday', '21:00:00', '22:00:00', 1),
(2, 61, 'monday', '11:11:00', '11:11:00', 1),
(3, 65, 'friday', '11:11:00', '12:12:00', 1),
(4, 9, 'monday', '11:11:00', '12:12:00', 1),
(5, 9, 'tuesday', '11:11:00', '12:12:00', 1),
(6, 9, 'wednesday', '11:11:00', '12:12:00', 1),
(7, 9, 'thursday', '11:11:00', '12:12:00', 1),
(8, 9, 'friday', '11:11:00', '12:12:00', 1),
(9, 9, 'saturday', '11:11:00', '12:12:00', 1),
(10, 9, 'sunday', '11:11:00', '12:12:00', 1),
(11, 87, 'monday', '11:11:00', '12:12:00', 1),
(12, 82, 'monday', '11:11:00', '12:12:00', 1),
(13, 130, 'monday', '11:11:00', '12:12:00', 1),
(14, 134, 'friday', '11:11:00', '12:12:00', 1),
(15, 138, 'tuesday', '11:11:00', '12:12:00', 1),
(16, 140, 'monday', '11:11:00', '12:12:00', 1),
(17, 142, 'monday', '11:11:00', '12:12:00', 1),
(18, 143, 'thursday', '11:11:00', '12:12:00', 1),
(19, 144, 'thursday', '11:11:00', '12:12:00', 1),
(20, 145, 'monday', '11:11:00', '12:12:00', 1),
(21, 146, 'wednesday', '11:11:00', '12:12:00', 1),
(22, 147, 'sunday', '11:11:00', '12:12:00', 1),
(23, 149, 'sunday', '11:11:00', '12:12:00', 1),
(24, 150, 'monday', '11:11:00', '12:12:00', 1),
(25, 152, 'tuesday', '11:11:00', '12:12:00', 1),
(26, 153, 'monday', '11:11:00', '12:12:00', 1),
(27, 154, 'tuesday', '23:11:00', '12:12:00', 1),
(28, 155, 'wednesday', '23:11:00', '12:12:00', 1),
(29, 156, 'wednesday', '11:11:00', '12:12:00', 1),
(30, 157, 'wednesday', '11:11:00', '12:12:00', 1),
(31, 158, 'sunday', '11:11:00', '12:12:00', 1),
(32, 159, 'monday', '11:11:00', '12:12:00', 1),
(33, 162, 'thursday', '14:11:00', '04:11:00', 1),
(34, 161, 'thursday', '11:11:00', '12:12:00', 1),
(35, 163, 'wednesday', '11:11:00', '12:12:00', 1),
(36, 164, 'thursday', '11:11:00', '12:12:00', 1),
(37, 165, 'wednesday', '11:11:00', '12:12:00', 1),
(38, 166, 'wednesday', '11:11:00', '12:12:00', 1),
(39, 170, 'tuesday', '11:11:00', '12:12:00', 1),
(41, 171, 'wednesday', '11:11:00', '12:12:00', 1),
(42, 169, 'tuesday', '11:11:00', '12:12:00', 1),
(43, 172, 'wednesday', '11:11:00', '12:12:00', 1),
(44, 168, 'wednesday', '11:11:00', '12:12:00', 1),
(45, 167, 'monday', '23:41:00', '13:41:00', 1),
(46, 160, 'tuesday', '11:11:00', '12:12:00', 1),
(47, 173, 'wednesday', '11:11:00', '12:12:00', 1),
(48, 40, 'monday', '11:11:00', '12:12:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_bans`
--

CREATE TABLE `user_bans` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `banned_by` int(11) NOT NULL,
  `reason` text NOT NULL,
  `ban_type` enum('temporary','permanent') DEFAULT 'temporary',
  `ban_duration_days` int(11) DEFAULT 7,
  `banned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `type`, `message`, `is_read`, `created_at`) VALUES
(1, 42, 'warning', 'You have received a warning from the admin regarding your behavior. Please review our community guidelines.', 0, '2025-10-16 14:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `user_online_status`
--

CREATE TABLE `user_online_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_online_status`
--

INSERT INTO `user_online_status` (`id`, `user_id`, `is_online`, `last_activity`, `session_id`) VALUES
(1, 1, 0, '2025-09-20 11:25:13', NULL),
(2, 3, 0, '2025-09-20 11:25:13', NULL),
(3, 4, 0, '2025-09-20 11:25:13', NULL),
(4, 5, 0, '2025-09-20 11:25:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_rejections`
--

CREATE TABLE `user_rejections` (
  `id` int(11) NOT NULL,
  `rejector_id` int(11) NOT NULL,
  `rejected_id` int(11) NOT NULL,
  `rejected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp GENERATED ALWAYS AS (`rejected_at` + interval 7 day) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_rejections`
--

INSERT INTO `user_rejections` (`id`, `rejector_id`, `rejected_id`, `rejected_at`) VALUES
(2, 40, 11, '2025-10-05 10:02:13'),
(3, 40, 11, '2025-10-05 10:02:15'),
(4, 40, 11, '2025-10-05 10:02:17'),
(5, 40, 11, '2025-10-05 10:02:19'),
(6, 40, 11, '2025-10-05 10:02:21'),
(7, 40, 11, '2025-10-05 10:02:24'),
(8, 40, 11, '2025-10-05 10:02:27'),
(9, 40, 11, '2025-10-05 10:02:30'),
(10, 40, 11, '2025-10-05 10:02:33'),
(11, 40, 11, '2025-10-05 10:02:36'),
(12, 40, 11, '2025-10-05 10:02:39'),
(13, 40, 11, '2025-10-05 10:02:42'),
(14, 40, 11, '2025-10-05 10:02:44'),
(15, 40, 11, '2025-10-05 10:02:47'),
(16, 40, 11, '2025-10-05 10:02:50'),
(17, 40, 11, '2025-10-05 10:02:53'),
(18, 40, 11, '2025-10-05 10:02:55'),
(1, 40, 13, '2025-10-05 09:58:35'),
(45, 82, 68, '2025-10-05 11:25:18'),
(43, 82, 75, '2025-10-05 11:25:10'),
(42, 82, 76, '2025-10-05 11:25:09'),
(44, 82, 81, '2025-10-05 11:25:12'),
(37, 82, 84, '2025-10-05 11:24:59'),
(34, 82, 87, '2025-10-05 11:24:52'),
(38, 82, 90, '2025-10-05 11:25:01'),
(35, 82, 91, '2025-10-05 11:24:54'),
(39, 82, 92, '2025-10-05 11:25:05'),
(36, 82, 93, '2025-10-05 11:24:57'),
(46, 82, 95, '2025-10-05 11:25:20'),
(48, 82, 97, '2025-10-05 11:25:23'),
(47, 82, 98, '2025-10-05 11:25:21'),
(40, 82, 122, '2025-10-05 11:25:06'),
(41, 82, 128, '2025-10-05 11:25:07'),
(29, 87, 73, '2025-10-05 10:38:28'),
(24, 87, 82, '2025-10-05 10:38:22'),
(21, 87, 83, '2025-10-05 10:33:28'),
(22, 87, 84, '2025-10-05 10:38:19'),
(26, 87, 90, '2025-10-05 10:38:24'),
(23, 87, 91, '2025-10-05 10:38:20'),
(25, 87, 93, '2025-10-05 10:38:23'),
(28, 87, 94, '2025-10-05 10:38:27'),
(27, 87, 95, '2025-10-05 10:38:25'),
(30, 87, 123, '2025-10-05 10:38:29'),
(31, 87, 123, '2025-10-05 10:38:31'),
(32, 87, 123, '2025-10-05 10:38:33'),
(33, 87, 123, '2025-10-05 10:38:36'),
(19, 129, 70, '2025-10-05 10:11:30'),
(20, 129, 79, '2025-10-05 10:11:33'),
(49, 139, 83, '2025-10-07 10:09:57'),
(51, 140, 83, '2025-10-07 10:40:16'),
(55, 140, 84, '2025-10-07 10:40:23'),
(50, 140, 87, '2025-10-07 10:40:14'),
(52, 140, 91, '2025-10-07 10:40:18'),
(54, 140, 93, '2025-10-07 10:40:22'),
(53, 140, 117, '2025-10-07 10:40:20'),
(57, 143, 137, '2025-10-08 07:42:43'),
(56, 143, 141, '2025-10-08 07:42:34'),
(87, 155, 64, '2025-10-14 05:02:00'),
(88, 155, 78, '2025-10-14 05:02:02'),
(58, 159, 120, '2025-10-09 02:59:13'),
(59, 159, 120, '2025-10-09 02:59:31'),
(60, 162, 83, '2025-10-09 03:14:19'),
(61, 162, 83, '2025-10-09 03:14:46'),
(62, 167, 91, '2025-10-12 03:38:14'),
(63, 167, 113, '2025-10-12 03:38:17'),
(66, 169, 70, '2025-10-12 04:38:53'),
(67, 169, 79, '2025-10-12 04:38:55'),
(65, 169, 83, '2025-10-12 04:38:50'),
(68, 169, 84, '2025-10-12 04:38:57'),
(64, 169, 91, '2025-10-12 04:38:49'),
(83, 171, 67, '2025-10-13 04:03:07'),
(78, 171, 70, '2025-10-13 04:02:55'),
(77, 171, 73, '2025-10-13 04:02:51'),
(86, 171, 75, '2025-10-13 04:03:13'),
(84, 171, 76, '2025-10-13 04:03:09'),
(75, 171, 77, '2025-10-13 04:02:45'),
(79, 171, 79, '2025-10-13 04:02:57'),
(85, 171, 81, '2025-10-13 04:03:11'),
(71, 171, 83, '2025-10-13 04:02:33'),
(82, 171, 84, '2025-10-13 04:03:04'),
(74, 171, 89, '2025-10-13 04:02:43'),
(81, 171, 90, '2025-10-13 04:03:01'),
(73, 171, 91, '2025-10-13 04:02:40'),
(80, 171, 92, '2025-10-13 04:03:00'),
(76, 171, 113, '2025-10-13 04:02:48'),
(72, 171, 167, '2025-10-13 04:02:38'),
(69, 171, 168, '2025-10-13 04:02:28'),
(70, 171, 169, '2025-10-13 04:02:30'),
(90, 173, 88, '2025-10-15 12:35:05'),
(89, 173, 147, '2025-10-15 12:35:04'),
(91, 174, 88, '2025-10-15 12:39:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_reminder_preferences`
--

CREATE TABLE `user_reminder_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `enable_24h_reminder` tinyint(1) DEFAULT 1,
  `enable_1h_reminder` tinyint(1) DEFAULT 1,
  `enable_30m_reminder` tinyint(1) DEFAULT 0,
  `reminder_method` enum('email','both') DEFAULT 'email',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_reminder_preferences`
--

INSERT INTO `user_reminder_preferences` (`id`, `user_id`, `enable_24h_reminder`, `enable_1h_reminder`, `enable_30m_reminder`, `reminder_method`, `created_at`, `updated_at`) VALUES
(1, 127, 1, 1, 0, 'email', '2025-10-04 12:22:31', '2025-10-04 12:22:31'),
(2, 119, 1, 1, 0, 'email', '2025-10-04 12:26:04', '2025-10-04 12:26:04'),
(3, 83, 1, 1, 0, 'email', '2025-10-04 12:41:25', '2025-10-04 12:41:25'),
(4, 113, 1, 1, 0, 'email', '2025-10-05 06:11:08', '2025-10-05 06:11:08'),
(5, 40, 1, 1, 0, 'email', '2025-10-05 08:59:17', '2025-10-05 08:59:17'),
(6, 82, 1, 1, 0, 'email', '2025-10-05 10:49:26', '2025-10-05 10:49:26'),
(7, 132, 1, 1, 0, 'email', '2025-10-05 11:00:03', '2025-10-05 11:00:03'),
(8, 91, 1, 1, 0, 'email', '2025-10-07 10:11:16', '2025-10-07 10:11:16'),
(9, 139, 1, 1, 0, 'email', '2025-10-07 10:13:55', '2025-10-07 10:13:55'),
(10, 142, 1, 1, 0, 'email', '2025-10-08 07:28:09', '2025-10-08 07:28:09'),
(11, 141, 1, 1, 0, 'email', '2025-10-08 07:28:34', '2025-10-08 07:28:34'),
(12, 147, 1, 1, 0, 'email', '2025-10-08 11:50:08', '2025-10-08 11:50:08'),
(13, 161, 1, 1, 0, 'email', '2025-10-09 03:07:43', '2025-10-09 03:07:43'),
(14, 148, 1, 1, 0, 'email', '2025-10-11 03:25:36', '2025-10-11 03:25:36'),
(15, 168, 1, 1, 0, 'email', '2025-10-12 03:49:12', '2025-10-12 03:49:12'),
(16, 166, 1, 1, 0, 'email', '2025-10-12 03:51:38', '2025-10-12 03:51:38'),
(17, 171, 1, 1, 0, 'email', '2025-10-13 04:04:25', '2025-10-13 04:04:25'),
(18, 3, 1, 1, 0, 'email', '2025-10-14 04:38:46', '2025-10-14 04:38:46'),
(19, 138, 1, 1, 0, 'email', '2025-10-14 04:54:19', '2025-10-14 04:54:19'),
(20, 173, 1, 1, 0, 'email', '2025-10-15 12:37:17', '2025-10-15 12:37:17'),
(21, 174, 1, 1, 0, 'email', '2025-10-15 12:40:17', '2025-10-15 12:40:17');

-- --------------------------------------------------------

--
-- Table structure for table `user_reports`
--

CREATE TABLE `user_reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_notes` text DEFAULT NULL,
  `action_taken` varchar(50) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_reports`
--

INSERT INTO `user_reports` (`id`, `reporter_id`, `reported_id`, `reason`, `description`, `status`, `created_at`, `admin_notes`, `action_taken`, `reviewed_at`, `reviewed_by`, `resolved_at`, `resolved_by`) VALUES
(1, 40, 1, 'spam', 'awit', 'reviewed', '2025-10-10 05:24:12', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 40, 7, 'bug', 'asd', 'resolved', '2025-10-10 05:25:22', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 40, 42, 'inappropriate', 'asd', 'resolved', '2025-10-16 13:35:40', '', 'warned', NULL, NULL, '2025-10-17 03:28:55', 1),
(4, 40, 42, 'inappropriate', 'asd', 'resolved', '2025-10-16 13:35:51', '', 'warned', NULL, NULL, '2025-10-16 14:21:07', 1),
(5, 40, 42, 'inappropriate', 'asd', 'resolved', '2025-10-16 13:36:55', '', 'suspended', NULL, NULL, '2025-10-16 14:19:05', 1),
(6, 40, 15, 'no_show', 'awit', 'resolved', '2025-10-16 14:16:50', '', NULL, NULL, NULL, '2025-10-16 14:18:42', 1),
(7, 78, 119, 'no_show', 'awit', 'resolved', '2025-10-17 03:22:06', '', 'warned', NULL, NULL, '2025-10-17 03:26:17', 1),
(8, 147, 173, 'fake_profile', 'nuyon', 'pending', '2025-10-17 14:12:30', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_subjects`
--

CREATE TABLE `user_subjects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `main_subject` varchar(100) DEFAULT NULL,
  `subtopic` varchar(100) DEFAULT NULL,
  `proficiency_level` enum('beginner','intermediate','advanced','expert') NOT NULL,
  `subject_type` enum('learning','teaching') NOT NULL DEFAULT 'learning'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_subjects`
--

INSERT INTO `user_subjects` (`id`, `user_id`, `subject_name`, `main_subject`, `subtopic`, `proficiency_level`, `subject_type`) VALUES
(2, 3, 'English', NULL, NULL, 'advanced', 'learning'),
(3, 4, 'English', NULL, NULL, 'beginner', 'learning'),
(4, 4, 'Filipino', NULL, NULL, 'intermediate', 'learning'),
(5, 5, 'History', NULL, NULL, 'beginner', 'teaching'),
(6, 6, 'History', NULL, NULL, 'beginner', 'learning'),
(7, 6, 'Geography', NULL, NULL, 'beginner', 'learning'),
(8, 7, 'History', NULL, NULL, 'intermediate', 'teaching'),
(9, 8, 'Filipino', NULL, NULL, 'expert', 'teaching'),
(10, 9, 'Filipino', NULL, NULL, 'beginner', 'teaching'),
(11, 10, 'History', NULL, NULL, 'intermediate', 'teaching'),
(12, 11, 'English', NULL, NULL, 'beginner', 'learning'),
(13, 11, 'Filipino', NULL, NULL, 'intermediate', 'learning'),
(14, 12, 'Science', NULL, NULL, 'advanced', 'teaching'),
(15, 13, 'History', NULL, NULL, 'expert', 'teaching'),
(16, 14, 'English', NULL, NULL, 'intermediate', 'teaching'),
(17, 15, 'English', NULL, NULL, 'beginner', 'teaching'),
(18, 16, 'Filipino', NULL, NULL, 'advanced', 'learning'),
(19, 20, 'Filipino', NULL, NULL, 'intermediate', 'teaching'),
(26, 27, 'Chemistry', NULL, NULL, 'beginner', 'learning'),
(30, 32, 'Mathematics', NULL, NULL, 'beginner', 'learning'),
(31, 33, 'English', NULL, NULL, 'intermediate', 'teaching'),
(32, 35, 'Geography', NULL, NULL, 'intermediate', 'learning'),
(37, 41, 'Geography', NULL, NULL, 'beginner', 'learning'),
(38, 42, 'English', NULL, NULL, 'beginner', 'learning'),
(39, 42, 'History', NULL, NULL, 'expert', 'teaching'),
(40, 43, 'Geography', NULL, NULL, 'advanced', 'learning'),
(41, 44, 'Science', NULL, NULL, 'beginner', 'learning'),
(42, 44, 'History', NULL, NULL, 'expert', 'teaching'),
(43, 45, 'Geography', NULL, NULL, 'beginner', 'learning'),
(44, 46, 'Filipino', NULL, NULL, 'advanced', 'teaching'),
(45, 47, 'Geography', NULL, NULL, 'intermediate', 'learning'),
(46, 48, 'Science', NULL, NULL, 'advanced', 'teaching'),
(47, 49, 'Filipino', NULL, NULL, 'intermediate', 'learning'),
(48, 49, 'Chemistry', NULL, NULL, 'expert', 'teaching'),
(49, 50, 'History', NULL, NULL, 'intermediate', 'teaching'),
(50, 51, 'Geography', NULL, NULL, 'intermediate', 'learning'),
(51, 51, 'Programming', NULL, NULL, 'expert', 'teaching'),
(52, 53, 'History', NULL, NULL, 'beginner', 'teaching'),
(53, 54, 'English', NULL, NULL, 'advanced', 'teaching'),
(54, 54, 'Geography', NULL, NULL, 'expert', 'teaching'),
(55, 55, 'Geography', NULL, NULL, 'advanced', 'learning'),
(57, 56, 'Geography', NULL, NULL, 'advanced', 'teaching'),
(58, 61, 'Programming - Java', 'Programming', 'Java', 'beginner', 'teaching'),
(59, 61, 'Geography - Physical Geography', 'Geography', 'Physical Geography', 'beginner', 'teaching'),
(60, 64, 'Psychology - Developmental Psychology', 'Psychology', 'Developmental Psychology', 'intermediate', 'learning'),
(61, 64, 'Computer Science - Database Systems', 'Computer Science', 'Database Systems', 'advanced', 'learning'),
(62, 64, 'Programming - C++', 'Programming', 'C++', 'intermediate', 'learning'),
(63, 64, 'History - World History', 'History', 'World History', 'beginner', 'learning'),
(64, 64, 'History', 'History', NULL, 'intermediate', 'learning'),
(65, 40, 'Sociology', 'Sociology', NULL, 'beginner', 'learning'),
(66, 65, 'Programming - C++', 'Programming', 'C++', 'beginner', 'teaching'),
(67, 66, 'General Psychology', 'Psychology', 'General Psychology', 'intermediate', 'teaching'),
(68, 66, 'Programming - C++', 'Programming', 'C++', 'beginner', 'teaching'),
(70, 66, 'History - World History', 'History', 'World History', 'beginner', 'teaching'),
(71, 67, 'World History', 'History', 'World History', 'beginner', 'learning'),
(72, 67, 'C++', 'Programming', 'C++', 'intermediate', 'learning'),
(73, 68, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(74, 68, 'Calculus', 'Mathematics', 'Calculus', 'intermediate', 'learning'),
(75, 69, 'C++', 'Programming', 'C++', 'expert', 'teaching'),
(76, 70, 'C++', 'Programming', 'C++', 'intermediate', 'learning'),
(77, 70, 'Grammar', 'English', 'Grammar', 'intermediate', 'learning'),
(78, 72, 'Statistics', 'Mathematics', 'Statistics', 'beginner', 'learning'),
(79, 72, 'Python', 'Programming', 'Python', 'advanced', 'teaching'),
(80, 73, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(81, 73, 'Grammar', 'English', 'Grammar', 'beginner', 'learning'),
(82, 73, 'Web Development', 'Computer Science', 'Web Development', 'beginner', 'learning'),
(83, 74, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(84, 74, 'Grammar', 'English', 'Grammar', 'beginner', 'teaching'),
(85, 75, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(86, 75, 'Calculus', 'Mathematics', 'Calculus', 'advanced', 'teaching'),
(87, 76, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(88, 76, 'Calculus', 'Mathematics', 'Calculus', 'advanced', 'learning'),
(89, 76, 'World History', 'History', 'World History', 'beginner', 'learning'),
(90, 76, 'Grammar', 'English', 'Grammar', 'intermediate', 'teaching'),
(91, 77, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(92, 77, 'Mathematics - Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(93, 79, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(94, 79, 'Grammar', 'English', 'Grammar', 'intermediate', 'teaching'),
(95, 79, 'Mathematics - Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(96, 80, 'C++', 'Programming', 'C++', 'intermediate', 'teaching'),
(97, 81, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(98, 81, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(99, 81, 'Pagbasa at Pag-unawa', 'Filipino', 'Pagbasa at Pag-unawa', 'intermediate', 'teaching'),
(100, 82, 'Web Development', 'Computer Science', 'Web Development', 'beginner', 'learning'),
(101, 78, 'Programming - C++', 'Programming', 'C++', 'beginner', 'learning'),
(102, 68, 'Computer Science - Web Development', 'Computer Science', 'Web Development', 'intermediate', 'learning'),
(103, 83, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(104, 83, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(105, 83, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(106, 83, 'Web Development', 'Computer Science', 'Web Development', 'intermediate', 'teaching'),
(107, 83, 'World History', 'History', 'World History', 'intermediate', 'teaching'),
(108, 83, 'Gramatika', 'Filipino', 'Gramatika', 'intermediate', 'teaching'),
(109, 84, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(110, 84, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(111, 84, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(112, 84, 'Web Development', 'Computer Science', 'Web Development', 'intermediate', 'teaching'),
(113, 84, 'World History', 'History', 'World History', 'intermediate', 'teaching'),
(114, 84, 'Gramatika', 'Filipino', 'Gramatika', 'intermediate', 'teaching'),
(115, 85, 'JavaScript', 'Programming', 'JavaScript', 'intermediate', 'teaching'),
(116, 86, 'JavaScript', 'Programming', 'JavaScript', 'advanced', 'teaching'),
(117, 87, 'JavaScript', 'Programming', 'JavaScript', 'beginner', 'learning'),
(118, 87, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(119, 87, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(120, 87, 'Web Development', 'Computer Science', 'Web Development', 'advanced', 'learning'),
(121, 87, 'World History', 'History', 'World History', 'intermediate', 'learning'),
(122, 87, 'Gramatika', 'Filipino', 'Gramatika', 'advanced', 'learning'),
(123, 88, 'Social Theory', 'Sociology', 'Social Theory', 'beginner', 'learning'),
(124, 88, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'advanced', 'teaching'),
(125, 89, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(126, 89, 'World History', 'History', 'World History', 'beginner', 'learning'),
(127, 90, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(128, 90, 'Web Development', 'Computer Science', 'Web Development', 'intermediate', 'teaching'),
(129, 91, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(130, 91, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(131, 91, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(132, 91, 'Web Development', 'Computer Science', 'Web Development', 'intermediate', 'teaching'),
(133, 91, 'World History', 'History', 'World History', 'intermediate', 'teaching'),
(134, 92, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(135, 92, 'Calculus', 'Mathematics', 'Calculus', 'intermediate', 'teaching'),
(136, 82, 'Calculus', 'Mathematics', 'Calculus', 'intermediate', 'learning'),
(137, 93, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(138, 93, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(139, 93, 'Web Development', 'Computer Science', 'Web Development', 'intermediate', 'teaching'),
(140, 93, 'HTML/CSS', 'Programming', 'HTML/CSS', 'intermediate', 'teaching'),
(141, 94, 'HTML/CSS', 'Programming', 'HTML/CSS', 'beginner', 'learning'),
(142, 94, 'Web Development', 'Computer Science', 'Web Development', 'beginner', 'learning'),
(143, 95, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(144, 95, 'HTML/CSS', 'Programming', 'HTML/CSS', 'intermediate', 'teaching'),
(145, 95, 'Web Development', 'Computer Science', 'Web Development', 'intermediate', 'teaching'),
(146, 96, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(147, 96, 'HTML/CSS', 'Programming', 'HTML/CSS', 'intermediate', 'teaching'),
(148, 97, 'Cell Biology', 'Biology', 'Cell Biology', 'beginner', 'learning'),
(149, 97, 'HTML/CSS', 'Programming', 'HTML/CSS', 'expert', 'teaching'),
(150, 97, 'Web Development', 'Computer Science', 'Web Development', 'expert', 'teaching'),
(151, 98, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(152, 98, 'C++', 'Programming', 'C++', 'expert', 'teaching'),
(153, 98, 'Web Development', 'Computer Science', 'Web Development', 'expert', 'teaching'),
(154, 100, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(157, 101, 'Algebra', '', 'Algebra', 'intermediate', 'learning'),
(158, 102, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(159, 103, 'Algebra', 'Mathematics', 'Algebra', 'intermediate', 'learning'),
(160, 104, 'Geometry', 'Mathematics', 'Geometry', 'beginner', 'learning'),
(161, 105, 'HTML/CSS', 'Programming', 'HTML/CSS', 'advanced', 'learning'),
(162, 112, 'Human Geography', 'Geography', 'Human Geography', 'beginner', 'learning'),
(166, 113, 'Panitikan', 'Filipino', 'Panitikan', 'beginner', 'learning'),
(167, 113, 'C++', 'Programming', 'C++', 'advanced', 'learning'),
(169, 114, 'C++', 'Programming', 'C++', 'expert', 'learning'),
(170, 115, 'Financial Accounting', 'Accounting', 'Financial Accounting', 'intermediate', 'learning'),
(171, 116, 'Family Studies', 'Sociology', 'Family Studies', 'advanced', 'learning'),
(172, 113, 'Mathematics - Calculus', 'Mathematics', 'Calculus', 'expert', 'learning'),
(173, 117, 'Algebra', 'Mathematics', 'Algebra', 'intermediate', 'learning'),
(174, 117, 'Programming - C++', 'Programming', 'C++', 'beginner', 'learning'),
(175, 119, 'Algebra', 'Mathematics', 'Algebra', 'intermediate', 'learning'),
(176, 119, 'Programming - C++', 'Programming', 'C++', 'intermediate', 'learning'),
(177, 120, 'Logic', 'Philosophy', 'Logic', 'beginner', 'learning'),
(178, 121, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(179, 122, 'Calculus', 'Mathematics', 'Calculus', 'intermediate', 'learning'),
(180, 123, 'Web Development', 'Computer Science', 'Web Development', 'beginner', 'learning'),
(181, 123, 'World History', 'History', 'World History', 'beginner', 'learning'),
(182, 123, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(183, 123, 'Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(184, 123, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(185, 124, 'Gramatika', 'Filipino', 'Gramatika', 'intermediate', 'learning'),
(187, 125, 'Mathematics - Calculus', 'Mathematics', 'Calculus', 'beginner', 'learning'),
(188, 127, 'Mathematics - Algebra', 'Mathematics', 'Algebra', 'expert', 'learning'),
(189, 127, 'Mathematics - Calculus', 'Mathematics', 'Calculus', 'expert', 'learning'),
(190, 128, 'Calculus', 'Mathematics', 'Calculus', 'intermediate', 'learning'),
(191, 129, 'Grammar', 'English', 'Grammar', 'beginner', 'learning'),
(192, 130, 'Algebra', 'Mathematics', 'Algebra', 'intermediate', 'learning'),
(193, 82, 'English - Creative Writing', 'English', 'Creative Writing', 'advanced', 'learning'),
(194, 132, 'Creative Writing', 'English', 'Creative Writing', 'intermediate', 'learning'),
(195, 82, 'Mathematics - Trigonometry', 'Mathematics', 'Trigonometry', 'advanced', 'learning'),
(196, 132, 'Mathematics - Trigonometry', 'Mathematics', 'Trigonometry', 'intermediate', 'learning'),
(197, 134, 'Trigonometry', 'Mathematics', 'Trigonometry', 'intermediate', 'learning'),
(198, 136, 'Trigonometry', 'Mathematics', 'Trigonometry', 'beginner', 'learning'),
(199, 137, 'Trigonometry', 'Mathematics', 'Trigonometry', 'beginner', 'learning'),
(200, 138, 'Trigonometry', 'Mathematics', 'Trigonometry', 'advanced', 'learning'),
(201, 139, 'Algebra', 'Mathematics', 'Algebra', 'beginner', 'learning'),
(202, 140, 'Algebra', 'Mathematics', 'Algebra', 'intermediate', 'learning'),
(203, 141, 'Trigonometry', 'Mathematics', 'Trigonometry', 'beginner', 'learning'),
(204, 142, 'Trigonometry', 'Mathematics', 'Trigonometry', 'intermediate', 'learning'),
(205, 143, 'Trigonometry', 'Mathematics', 'Trigonometry', 'advanced', 'learning'),
(206, 144, 'Metaphysics', 'Philosophy', 'Metaphysics', 'intermediate', 'learning'),
(207, 145, 'Social Psychology', 'Psychology', 'Social Psychology', 'intermediate', 'learning'),
(208, 146, 'Ethics', 'Philosophy', 'Ethics', 'intermediate', 'learning'),
(209, 147, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'intermediate', 'learning'),
(211, 149, 'Family Studies', 'Sociology', 'Family Studies', 'advanced', 'learning'),
(213, 150, 'Programming Fundamentals', 'Computer Science', 'Programming Fundamentals', 'beginner', 'learning'),
(214, 150, 'Social Theory', 'Sociology', 'Social Theory', 'intermediate', 'learning'),
(215, 151, 'Social Theory', 'Sociology', 'Social Theory', 'beginner', 'learning'),
(217, 152, 'Metaphysics', 'Philosophy', 'Metaphysics', 'advanced', 'learning'),
(218, 152, 'Political Philosophy', 'Philosophy', 'Political Philosophy', 'intermediate', 'learning'),
(220, 153, 'Financial Accounting', 'Accounting', 'Financial Accounting', 'beginner', 'learning'),
(221, 153, 'Piano', 'Music', 'Piano', 'intermediate', 'learning'),
(222, 154, 'Composition', 'Music', 'Composition', 'intermediate', 'learning'),
(224, 155, 'Social Psychology', 'Psychology', 'Social Psychology', 'beginner', 'learning'),
(225, 155, 'Social Psychology', 'Psychology', 'Social Psychology', 'advanced', 'learning'),
(226, 156, 'Metaphysics', 'Philosophy', 'Metaphysics', 'advanced', 'learning'),
(228, 157, 'Social Theory', 'Sociology', 'Social Theory', 'intermediate', 'learning'),
(229, 157, 'Sculpture', 'Art', 'Sculpture', 'intermediate', 'learning'),
(231, 158, 'Microeconomics', 'Economics', 'Microeconomics', 'intermediate', 'learning'),
(232, 158, 'Logic', 'Philosophy', 'Logic', 'intermediate', 'learning'),
(235, 159, 'Biology - Zoology', 'Biology', 'Zoology', 'expert', 'learning'),
(237, 162, 'C++', 'Programming', 'C++', 'advanced', 'learning'),
(238, 161, 'Financial Accounting', 'Accounting', 'Financial Accounting', 'advanced', 'learning'),
(239, 161, 'Botany', 'Biology', 'Botany', 'expert', 'learning'),
(241, 163, 'Ethics', 'Philosophy', 'Ethics', 'advanced', 'learning'),
(242, 163, 'Painting', 'Art', 'Painting', 'advanced', 'learning'),
(244, 164, 'Cost Accounting', 'Accounting', 'Cost Accounting', 'intermediate', 'learning'),
(245, 164, 'Metaphysics', 'Philosophy', 'Metaphysics', 'expert', 'learning'),
(247, 165, 'Painting', 'Art', 'Painting', 'intermediate', 'learning'),
(248, 165, 'Social Theory', 'Sociology', 'Social Theory', 'expert', 'learning'),
(249, 166, 'C++', 'Programming', 'C++', 'advanced', 'learning'),
(253, 170, 'C++', 'Programming', 'C++', 'advanced', 'learning'),
(257, 171, 'Psychology - Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'advanced', 'learning'),
(258, 148, 'Psychology - Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'advanced', 'learning'),
(259, 169, 'Physical Geography', 'Geography', 'Physical Geography', 'advanced', 'learning'),
(260, 169, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'intermediate', 'learning'),
(261, 172, 'Development Economics', 'Economics', 'Development Economics', 'advanced', 'learning'),
(262, 168, 'Web Development', 'Computer Science', 'Web Development', 'beginner', 'learning'),
(263, 168, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'advanced', 'learning'),
(264, 167, 'Management Accounting', 'Accounting', 'Management Accounting', 'intermediate', 'learning'),
(265, 167, 'Developmental Psychology', 'Psychology', 'Developmental Psychology', 'advanced', 'learning'),
(266, 160, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'intermediate', 'learning'),
(267, 160, 'Cognitive Psychology', 'Psychology', 'Cognitive Psychology', 'intermediate', 'learning'),
(268, 155, 'Programming - C++', 'Programming', 'C++', 'expert', 'learning'),
(270, 174, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'intermediate', 'learning'),
(271, 174, 'Programming - C++', 'Programming', 'C++', 'expert', 'learning'),
(272, 175, 'Abnormal Psychology', 'Psychology', 'Abnormal Psychology', 'beginner', 'learning'),
(273, 176, 'C++', 'Programming', 'C++', 'beginner', 'learning'),
(274, 173, 'Pagbasa at Pag-unawa', 'Filipino', 'Pagbasa at Pag-unawa', 'intermediate', 'learning'),
(275, 173, 'JavaScript', 'Programming', 'JavaScript', 'advanced', 'learning'),
(277, 177, 'Philippine Geography', 'Geography', 'Philippine Geography', 'beginner', 'learning'),
(278, 178, 'Developmental Psychology', 'Psychology', 'Developmental Psychology', 'beginner', 'learning'),
(279, 40, 'Programming - C++', 'Programming', 'C++', 'advanced', 'learning'),
(280, 179, 'Logic', 'Philosophy', 'Logic', 'intermediate', 'learning'),
(282, 181, 'Cultural Sociology', 'Sociology', 'Cultural Sociology', 'beginner', 'learning'),
(283, 181, 'Programming - C++', 'Programming', 'C++', 'expert', 'learning'),
(284, 182, 'Digital Art', 'Art', 'Digital Art', 'advanced', 'learning');

-- --------------------------------------------------------

--
-- Table structure for table `user_verification_documents`
--

CREATE TABLE `user_verification_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('id','student_id','diploma','transcript','professional_cert','expertise_proof','other') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `uploaded_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_verification_documents`
--

INSERT INTO `user_verification_documents` (`id`, `user_id`, `document_type`, `filename`, `original_filename`, `description`, `status`, `uploaded_by`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `review_notes`, `review_date`, `created_at`, `updated_at`) VALUES
(1, 8, 'student_id', 'verification_8_student_id_1758370459.png', 'Screenshot 2025-09-20 193557.png', '', 'pending', 8, NULL, NULL, NULL, NULL, NULL, '2025-09-20 12:14:19', '2025-09-20 12:14:19'),
(2, 65, 'student_id', 'verification_65_student_id_1758888919.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'asd', 'approved', 65, 1, '2025-10-14 04:18:53', NULL, NULL, NULL, '2025-09-26 12:15:19', '2025-10-14 04:18:53'),
(3, 105, 'id', 'verification_105_id_1759409116.png', 'screencapture-localhost-study-mentorship-platform-profile-index-php-2025-10-01-21_10_15.png', 'asd', 'pending', 105, NULL, NULL, NULL, NULL, NULL, '2025-10-02 12:45:16', '2025-10-02 12:45:16'),
(4, 115, 'id', 'verification_115_id_1759415981.jpg', '841bb16d-38b1-4890-a7ae-4c834109f6f5.jpg', 'aw', 'pending', 115, NULL, NULL, NULL, NULL, NULL, '2025-10-02 14:39:41', '2025-10-02 14:39:41'),
(5, 119, 'id', 'verification_119_id_1759643795.jpg', 'WIN_20241007_19_13_15_Pro.jpg', '', 'approved', 119, 1, '2025-10-14 04:19:10', NULL, NULL, NULL, '2025-10-05 05:56:35', '2025-10-14 04:19:10'),
(6, 147, 'student_id', 'verification_147_student_id_1759916147.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'asd', 'pending', 147, NULL, NULL, NULL, NULL, NULL, '2025-10-08 09:35:47', '2025-10-08 09:35:47'),
(7, 138, 'id', 'verification_138_id_1760004923.jpg', 'WIN_20241008_08_08_08_Pro.jpg', 'asd', 'approved', 138, 1, '2025-10-18 05:34:17', NULL, NULL, NULL, '2025-10-09 10:15:23', '2025-10-18 05:34:17'),
(8, 138, 'student_id', 'verification_138_student_id_1760004930.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'asd', 'approved', 138, 1, '2025-10-14 03:57:35', NULL, NULL, NULL, '2025-10-09 10:15:30', '2025-10-14 03:57:35'),
(9, 138, 'professional_cert', 'verification_138_professional_cert_1760004939.jpg', 'WIN_20241008_08_07_45_Pro.jpg', 'asd', 'approved', 138, 1, '2025-10-14 04:28:15', NULL, NULL, NULL, '2025-10-09 10:15:39', '2025-10-14 04:28:15'),
(10, 119, 'student_id', 'verification_119_student_id_1760416168.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'asd', 'rejected', 119, 1, '2025-10-14 04:29:39', 'asdas', NULL, NULL, '2025-10-14 04:29:28', '2025-10-14 04:29:39'),
(11, 177, '', 'age_proof_177_1760619805.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'Age verification document - WIN_20241007_19_13_15_Pro.jpg', 'pending', 177, NULL, NULL, NULL, NULL, NULL, '2025-10-16 13:03:25', '2025-10-16 13:03:25'),
(12, 177, '', 'age_proof_177_1760619850.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'Age verification document - WIN_20241007_19_13_15_Pro.jpg', 'pending', 177, NULL, NULL, NULL, NULL, NULL, '2025-10-16 13:04:10', '2025-10-16 13:04:10'),
(13, 178, '', 'age_proof_178_1760621065.jpg', 'WIN_20241007_19_13_15_Pro.jpg', 'Age verification document - WIN_20241007_19_13_15_Pro.jpg', 'pending', 178, NULL, NULL, NULL, NULL, NULL, '2025-10-16 13:24:25', '2025-10-16 13:24:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_target` (`target_audience`);

--
-- Indexes for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_payment_id` (`session_payment_id`),
  ADD KEY `mentor_id` (`mentor_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_commission_status` (`payment_status`),
  ADD KEY `idx_commission_deadline` (`payment_deadline`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `email_settings`
--
ALTER TABLE `email_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_name` (`template_name`);

--
-- Indexes for table `footer_settings`
--
ALTER TABLE `footer_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `mentor_id` (`mentor_id`),
  ADD KEY `idx_matches_teaching_user` (`teaching_user_id`),
  ADD KEY `idx_matches_learning_user` (`learning_user_id`),
  ADD KEY `idx_matches_requester` (`requester_id`),
  ADD KEY `idx_matches_helper` (`helper_id`);

--
-- Indexes for table `match_notifications`
--
ALTER TABLE `match_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_messages_match_created` (`match_id`,`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_otp_code` (`otp_code`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `payment_restrictions`
--
ALTER TABLE `payment_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_restrictions` (`user_id`,`is_active`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_referral_code` (`code`),
  ADD KEY `idx_referral_created_by` (`created_by`),
  ADD KEY `idx_referral_active` (`is_active`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_user_id` (`reported_user_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cancelled_by` (`cancelled_by`),
  ADD KEY `idx_user_datetime` (`match_id`,`session_date`,`start_time`,`end_time`);

--
-- Indexes for table `session_payments`
--
ALTER TABLE `session_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `mentor_id` (`mentor_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_payment_deadline` (`payment_deadline`);

--
-- Indexes for table `session_ratings`
--
ALTER TABLE `session_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `rater_id` (`rater_id`),
  ADD KEY `rated_id` (`rated_id`);

--
-- Indexes for table `session_reminders`
--
ALTER TABLE `session_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reminder_time` (`reminder_time`,`is_sent`);

--
-- Indexes for table `system_metrics`
--
ALTER TABLE `system_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_metric_date` (`metric_name`,`metric_date`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_location_coords` (`latitude`,`longitude`),
  ADD KEY `idx_users_location` (`location`),
  ADD KEY `idx_users_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_hourly_rate` (`hourly_rate`);

--
-- Indexes for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_availability`
--
ALTER TABLE `user_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_bans`
--
ALTER TABLE `user_bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banned_by` (`banned_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_online_status`
--
ALTER TABLE `user_online_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `user_rejections`
--
ALTER TABLE `user_rejections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rejection` (`rejector_id`,`rejected_id`,`rejected_at`),
  ADD KEY `rejected_id` (`rejected_id`),
  ADD KEY `idx_rejector_expires` (`rejector_id`,`expires_at`);

--
-- Indexes for table `user_reminder_preferences`
--
ALTER TABLE `user_reminder_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user_reports`
--
ALTER TABLE `user_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_id` (`reported_id`),
  ADD KEY `fk_reviewed_by` (`reviewed_by`),
  ADD KEY `fk_resolved_by` (`resolved_by`);

--
-- Indexes for table `user_subjects`
--
ALTER TABLE `user_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_subjects_main` (`main_subject`),
  ADD KEY `idx_user_subjects_subtopic` (`subtopic`),
  ADD KEY `idx_user_subjects_type` (`user_id`,`subject_type`);

--
-- Indexes for table `user_verification_documents`
--
ALTER TABLE `user_verification_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_user_verification_status` (`user_id`,`status`),
  ADD KEY `idx_verification_pending` (`status`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `commission_payments`
--
ALTER TABLE `commission_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `email_settings`
--
ALTER TABLE `email_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `footer_settings`
--
ALTER TABLE `footer_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `match_notifications`
--
ALTER TABLE `match_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `payment_restrictions`
--
ALTER TABLE `payment_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `referral_codes`
--
ALTER TABLE `referral_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `session_payments`
--
ALTER TABLE `session_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `session_ratings`
--
ALTER TABLE `session_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `session_reminders`
--
ALTER TABLE `session_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `system_metrics`
--
ALTER TABLE `system_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2402;

--
-- AUTO_INCREMENT for table `user_availability`
--
ALTER TABLE `user_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `user_bans`
--
ALTER TABLE `user_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_online_status`
--
ALTER TABLE `user_online_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_rejections`
--
ALTER TABLE `user_rejections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `user_reminder_preferences`
--
ALTER TABLE `user_reminder_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_reports`
--
ALTER TABLE `user_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_subjects`
--
ALTER TABLE `user_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=285;

--
-- AUTO_INCREMENT for table `user_verification_documents`
--
ALTER TABLE `user_verification_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD CONSTRAINT `commission_payments_ibfk_2` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_payments_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `commission_payments_ibfk_4` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `footer_settings`
--
ALTER TABLE `footer_settings`
  ADD CONSTRAINT `footer_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `match_notifications`
--
ALTER TABLE `match_notifications`
  ADD CONSTRAINT `match_notifications_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_notifications_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_notifications_ibfk_3` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_restrictions`
--
ALTER TABLE `payment_restrictions`
  ADD CONSTRAINT `payment_restrictions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD CONSTRAINT `referral_codes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_ibfk_2` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `session_payments`
--
ALTER TABLE `session_payments`
  ADD CONSTRAINT `session_payments_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_payments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_payments_ibfk_3` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_payments_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `session_ratings`
--
ALTER TABLE `session_ratings`
  ADD CONSTRAINT `session_ratings_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_ratings_ibfk_2` FOREIGN KEY (`rater_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_ratings_ibfk_3` FOREIGN KEY (`rated_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `session_reminders`
--
ALTER TABLE `session_reminders`
  ADD CONSTRAINT `session_reminders_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_reminders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_availability`
--
ALTER TABLE `user_availability`
  ADD CONSTRAINT `user_availability_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_bans`
--
ALTER TABLE `user_bans`
  ADD CONSTRAINT `user_bans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_bans_ibfk_2` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_online_status`
--
ALTER TABLE `user_online_status`
  ADD CONSTRAINT `user_online_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_rejections`
--
ALTER TABLE `user_rejections`
  ADD CONSTRAINT `user_rejections_ibfk_1` FOREIGN KEY (`rejector_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_rejections_ibfk_2` FOREIGN KEY (`rejected_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_reminder_preferences`
--
ALTER TABLE `user_reminder_preferences`
  ADD CONSTRAINT `user_reminder_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_reports`
--
ALTER TABLE `user_reports`
  ADD CONSTRAINT `fk_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_reports_ibfk_2` FOREIGN KEY (`reported_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_subjects`
--
ALTER TABLE `user_subjects`
  ADD CONSTRAINT `user_subjects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_verification_documents`
--
ALTER TABLE `user_verification_documents`
  ADD CONSTRAINT `user_verification_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_verification_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_verification_documents_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
