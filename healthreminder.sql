-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2025 at 05:19 AM
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
-- Database: `healthreminder`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `user_id`, `title`, `date`, `time`, `created_at`) VALUES
(2, 1, 'Makan Ayam', '2025-05-21', '23:27:00', '2025-05-21 22:25:17'),
(4, 1, 'Cabut gigi', '2025-05-22', '04:58:00', '2025-05-22 01:55:54');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `post_id` int(11) NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `reminder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `remind_date` date NOT NULL,
  `remind_time` time DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sms_enabled` tinyint(1) DEFAULT 0 COMMENT 'Apakah SMS diaktifkan untuk pengingat ini',
  `sms_phone` varchar(20) DEFAULT NULL COMMENT 'Nomor telepon tujuan SMS',
  `sms_sent` tinyint(1) DEFAULT 0 COMMENT 'Status apakah SMS sudah terkirim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reminders`
--

INSERT INTO `reminders` (`reminder_id`, `user_id`, `title`, `description`, `remind_date`, `remind_time`, `is_done`, `created_at`, `sms_enabled`, `sms_phone`, `sms_sent`) VALUES
(2, 1, 'Minum Obat', 'Obat Paracetamol', '2025-05-22', '09:50:00', 0, '2025-05-22 01:31:43', 1, '08236548490', 0);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `pdf_filename` varchar(255) DEFAULT NULL,
  `date_filter` varchar(50) DEFAULT 'all',
  `generated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `user_id`, `type`, `data`, `pdf_filename`, `date_filter`, `generated_at`) VALUES
(4, 1, 'appointments', '[{\"Judul\":\"Makan Ayam\",\"Tanggal\":\"2025-05-21\",\"Waktu\":\"23:27:00\"},{\"Judul\":\"Cabut gigi\",\"Tanggal\":\"2025-05-22\",\"Waktu\":\"04:58:00\"}]', 'appointments_3_days_2025-05-22_01-36-59.pdf', '3_days', '2025-05-22 06:36:59');

-- --------------------------------------------------------

--
-- Table structure for table `todos`
--

CREATE TABLE `todos` (
  `todo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task` varchar(255) NOT NULL,
  `due_date` date DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `todos`
--

INSERT INTO `todos` (`todo_id`, `user_id`, `task`, `due_date`, `is_done`, `created_at`) VALUES
(2, 1, 'Lari Pagi', '2025-05-22', 1, '2025-05-22 01:21:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `phone_number`, `email`, `profile_image`, `created_at`) VALUES
(1, 'Sally', '$2y$10$nC.Tg2Tx1Jx16m4kyCVk2.fRuRnrY/q7INvsVhBTSs/q8op2Yx0Xi', '+628123456789', 'Sally@gmail.com', 'uploads/profiles/1_1747876493.png', '2025-05-21 17:00:56');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme` enum('light','dark') NOT NULL DEFAULT 'light',
  `language` enum('id','en') NOT NULL DEFAULT 'id',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`setting_id`, `user_id`, `theme`, `language`, `updated_at`) VALUES
(1, 1, 'light', 'id', '2025-05-22 08:15:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointments_user` (`user_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `fk_blog_author` (`author_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `fk_reminders_user` (`user_id`),
  ADD KEY `idx_reminders_sms_check` (`user_id`,`remind_date`,`is_done`,`sms_sent`),
  ADD KEY `idx_reminders_sms_enabled` (`sms_enabled`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_reports_user` (`user_id`);

--
-- Indexes for table `todos`
--
ALTER TABLE `todos`
  ADD PRIMARY KEY (`todo_id`),
  ADD KEY `fk_todos_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `phone_number` (`phone_number`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `uk_user_settings` (`user_id`),
  ADD KEY `fk_settings_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `todos`
--
ALTER TABLE `todos`
  MODIFY `todo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `fk_blog_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `fk_reminders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `todos`
--
ALTER TABLE `todos`
  ADD CONSTRAINT `fk_todos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
