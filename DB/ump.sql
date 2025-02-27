-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 27, 2025 at 08:42 PM
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
-- Database: `ump`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_logs`
--

CREATE TABLE `access_logs` (
  `id` int(255) NOT NULL DEFAULT 0,
  `account_id` varchar(255) NOT NULL,
  `ip_address` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `region` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `latitude` varchar(255) NOT NULL,
  `longitude` varchar(255) NOT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_logs`
--

INSERT INTO `access_logs` (`id`, `account_id`, `ip_address`, `country`, `region`, `city`, `latitude`, `longitude`, `time`) VALUES
(1, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-01 13:05:42'),
(2, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-01 14:39:43'),
(3, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-01 14:55:20'),
(4, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-04 06:56:37'),
(5, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-04 08:17:16'),
(6, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-04 12:33:47'),
(7, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-04 13:06:50'),
(8, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-04 14:25:38'),
(9, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-04 14:30:17'),
(10, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-05 08:07:13'),
(11, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-05 08:44:21'),
(12, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-05 12:49:41'),
(13, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-05 14:09:16'),
(14, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-05 14:46:58'),
(15, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 08:54:28'),
(16, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 09:17:54'),
(17, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 09:47:28'),
(18, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 09:52:43'),
(19, 'SFRACC02932', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 10:59:53'),
(20, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 11:00:22'),
(21, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 12:05:41'),
(22, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-06 14:01:10'),
(23, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-08 08:27:51'),
(24, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-08 11:48:24'),
(25, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-08 12:50:09'),
(26, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-08 14:08:45'),
(27, 'SFRACC15263', '102.22.190.123', 'Rwanda', 'Kigali City', 'Kigali', '-1.9705786', '30.1044288', '2024-03-08 15:36:00');

-- --------------------------------------------------------

--
-- Table structure for table `account_holders`
--

CREATE TABLE `account_holders` (
  `id` int(255) NOT NULL,
  `account_id` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `privilege` varchar(255) NOT NULL,
  `last_access` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `profile` varchar(255) NOT NULL,
  `forgot_code` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_holders`
--

INSERT INTO `account_holders` (`id`, `account_id`, `firstname`, `lastname`, `email`, `username`, `password`, `privilege`, `last_access`, `status`, `profile`, `forgot_code`) VALUES
(1, 'SFRACC02932', 'William', 'CAMPBELL', 'sample@mail.com', 'sample@mail.com', '$2y$10$.Gii6ipfWj0Rv4mxztAsYOwlmqFZyhX9WWsghdVFJ7nj2fgeTxr3W', 'DOS', 'online', 'active', 'face1.png', 'used'),
(2, 'SFRACC15263', 'Anthony', 'CAMPBELL', 'teacher@mail.com', 'teacher@mail.com', '$2y$10$gdPO7UWrXirg4jvf49kCYOw./3b3Hu6FNv7K1pt4pk6NpECU.F4Oe', 'teacher', 'online', 'active', 'face1.png', '227237'),
(3, 'SFRACC15432', 'Moses', 'THEDDY', 'teacher2@mail.com', 'student@mail.com', '$2y$10$gdPO7UWrXirg4jvf49kCYOw./3b3Hu6FNv7K1pt4pk6NpECU.F4Oe', 'teacher', 'online', 'active', 'face2.jpg', '434324'),
(4, 'SFRACC43442', 'Ange', 'SYMPHONY', 'library@mail.com', 'library@mail.com', '$2y$10$gdPO7UWrXirg4jvf49kCYOw./3b3Hu6FNv7K1pt4pk6NpECU.F4Oe', 'librarian', 'online', 'active', 'face2.jpg', 'used');

-- --------------------------------------------------------

--
-- Table structure for table `data`
--

CREATE TABLE `data` (
  `id` int(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `date` varchar(255) NOT NULL,
  `opening_stock` varchar(255) NOT NULL,
  `recieved_day` varchar(255) NOT NULL,
  `recieved_night` varchar(255) NOT NULL,
  `recieved_total` varchar(255) NOT NULL,
  `grv_day` varchar(255) NOT NULL,
  `grv_night` varchar(255) NOT NULL,
  `issued` varchar(255) NOT NULL,
  `giv` varchar(255) NOT NULL,
  `closed_stock` varchar(255) NOT NULL,
  `mtd` varchar(255) NOT NULL,
  `recorded_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modified` varchar(255) NOT NULL DEFAULT 'None'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `data`
--

INSERT INTO `data` (`id`, `code`, `date`, `opening_stock`, `recieved_day`, `recieved_night`, `recieved_total`, `grv_day`, `grv_night`, `issued`, `giv`, `closed_stock`, `mtd`, `recorded_on`, `modified`) VALUES
(1, 'UMP62413', '2024-03-26', '1000', '435', '34', '469', 'CGD002', 'CGD002', '34', 'CGD773', '1435', '1479', '2024-03-26 19:41:25', 'None'),
(4, 'UMP48157', '2024-03-27', '12344', '12', '32', '44', 'CDG022', 'CDG022', '123', '4234', '12265', '44', '2024-03-26 19:41:25', 'None'),
(8, 'UMP50926', '2024-03-27', '12323', '12', '32', '44', 'CDG022', 'CDG022', '122', 'DCV098', '12245', '88', '2024-03-27 21:43:11', 'None'),
(9, 'UMP44937', '2024-04-05', '12245', '10', '32', '42', 'CDG022', 'CDG022', '322', 'CDF2432', '11965', '130', '2024-04-14 12:09:49', 'None'),
(10, 'UMP31951', '2024-04-08', '11965', '10', '2', '12', 'CDG022', 'CDG022', '23', 'CDF2432', '11954', '142', '2024-04-09 10:59:28', 'None'),
(11, 'UMP17901', '2024-04-09', '11954', '4', '6', '10', 'CDG022', 'CDG022', '12', 'CHD753', '11952', '152', '2024-04-09 11:00:15', 'None'),
(12, 'UMP8452', '2024-04-10', '11952', '12', '2', '14', 'CDG022', 'CDG022', '21', 'HGv992', '11945', '166', '2024-04-10 12:41:39', '2024-04-10'),
(13, 'UMP48445', '2024-04-14', '11945', '12', '2', '14', 'CDG022', 'CDG022', '453', 'FVD444', '11506', '180', '2024-04-14 12:17:28', 'None'),
(14, 'UMP82063', '2024-08-12', '11506', '12', '5', '17', 'CDG022', 'CDG022', '30', '77767', '11493', '197', '2024-08-12 11:20:07', 'None');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `opening_stock` varchar(255) NOT NULL,
  `mtd` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `password`, `opening_stock`, `mtd`) VALUES
(1, '$2y$10$.Gii6ipfWj0Rv4mxztAsYOwlmqFZyhX9WWsghdVFJ7nj2fgeTxr3W', '100', '1000');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_holders`
--
ALTER TABLE `account_holders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `data`
--
ALTER TABLE `data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_holders`
--
ALTER TABLE `account_holders`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `data`
--
ALTER TABLE `data`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
