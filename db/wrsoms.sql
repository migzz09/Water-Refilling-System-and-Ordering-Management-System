-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 09, 2025 at 02:23 AM
-- Server version: 8.0.42
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wrsoms`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `otp` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `otp_expires` timestamp NOT NULL DEFAULT ((now() + interval 10 minute)),
  `is_verified` tinyint(1) DEFAULT '0',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `profile_photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deletion_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deletion_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`account_id`, `customer_id`, `username`, `password`, `password_changed_at`, `otp`, `otp_expires`, `is_verified`, `is_admin`, `profile_photo`, `deletion_token`, `deletion_expires`) VALUES
(3, NULL, 'admin', 'admin123', NULL, NULL, '2025-11-03 16:55:21', 1, 1, NULL, NULL, NULL),
(13, 1, 'user1', '$2y$10$iQeloeYqNvP1YW2P2M5pSu6jG3wXMp2mI8ghXxILxKySnz45wPYQ.', NULL, NULL, '2025-11-15 06:34:17', 1, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `admin_management_view`
-- (See below for the actual view)
--
CREATE TABLE `admin_management_view` (
`assigned_employees` text
,`barangay` varchar(50)
,`batch_id` int
,`batch_status` varchar(50)
,`city` varchar(100)
,`container_price` decimal(10,2)
,`container_type` varchar(50)
,`customer_contact` char(11)
,`customer_name` varchar(152)
,`delivery_date` date
,`delivery_status` varchar(50)
,`delivery_status_id` int
,`order_date` timestamp
,`order_status` varchar(50)
,`order_status_id` int
,`payment_status` varchar(50)
,`payment_status_id` int
,`province` varchar(100)
,`quantity` int
,`reference_id` varchar(6)
,`street` varchar(150)
,`subtotal` decimal(10,2)
,`total_amount` decimal(10,2)
,`vehicle` varchar(100)
,`vehicle_type` enum('Tricycle','Car')
);

-- --------------------------------------------------------

--
-- Table structure for table `archived_orders`
--

CREATE TABLE `archived_orders` (
  `archive_id` int NOT NULL,
  `reference_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `delivery_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Complete order snapshot with all details',
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archive_log`
--

CREATE TABLE `archive_log` (
  `log_id` int NOT NULL,
  `archive_type` enum('manual','automatic','end_of_day') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orders_archived` int DEFAULT '0',
  `archived_by` int DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_filter` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for archive operations';

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `batch_id` int NOT NULL,
  `batch_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `batch_status_id` int DEFAULT NULL,
  `vehicle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vehicle_type` enum('Tricycle','Car') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `batch_number` int NOT NULL DEFAULT '1',
  `pickup_time` time DEFAULT NULL,
  `delivery_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`batch_id`, `batch_date`, `batch_status_id`, `vehicle`, `notes`, `vehicle_type`, `batch_number`, `pickup_time`, `delivery_time`) VALUES
(1, '2025-11-15 16:00:00', 3, 'Tricycle #162', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(2, '2025-11-14 16:00:00', 3, 'Tricycle #178', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(3, '2025-11-14 16:00:00', 2, 'Tricycle #725', 'Auto-created batch', 'Tricycle', 2, NULL, NULL),
(4, '2025-11-24 16:00:00', 2, 'Tricycle #169', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(5, '2025-11-24 16:00:00', 2, 'Tricycle #332', 'Auto-created batch', 'Tricycle', 2, NULL, NULL),
(6, '2025-11-24 16:00:00', 2, 'Tricycle #605', 'Auto-created batch', 'Tricycle', 3, NULL, NULL),
(7, '2025-11-26 16:00:00', 2, 'Tricycle #411', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(8, '2025-11-26 16:00:00', 2, 'Tricycle #941', 'Auto-created batch', 'Tricycle', 2, NULL, NULL),
(9, '2025-12-06 16:00:00', 2, 'Tricycle #571', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(10, '2025-12-07 16:00:00', 3, 'Tricycle #601', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(11, '2025-12-07 16:00:00', 3, 'Tricycle #200', 'Auto-created batch', 'Tricycle', 2, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `batch_employees`
--

CREATE TABLE `batch_employees` (
  `batch_employee_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batch_status`
--

CREATE TABLE `batch_status` (
  `batch_status_id` int NOT NULL,
  `status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_status`
--

INSERT INTO `batch_status` (`batch_status_id`, `status_name`) VALUES
(3, 'Completed'),
(4, 'Failed'),
(2, 'In Progress'),
(1, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `business_hours`
--

CREATE TABLE `business_hours` (
  `id` int NOT NULL,
  `day_of_week` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT '1',
  `open_time` time NOT NULL DEFAULT '08:00:00',
  `close_time` time NOT NULL DEFAULT '18:00:00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_hours`
--

INSERT INTO `business_hours` (`id`, `day_of_week`, `is_open`, `open_time`, `close_time`, `created_at`, `updated_at`) VALUES
(1, 'Monday', 1, '03:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-12-07 22:16:33'),
(2, 'Tuesday', 1, '03:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-12-08 22:29:30'),
(3, 'Wednesday', 1, '03:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-25 20:42:57'),
(4, 'Thursday', 1, '03:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-12-08 22:29:31'),
(5, 'Friday', 1, '03:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-12-08 22:29:32'),
(6, 'Saturday', 1, '03:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-12-08 22:29:37'),
(7, 'Sunday', 0, '09:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-13 05:40:59');

-- --------------------------------------------------------

--
-- Table structure for table `checkouts`
--

CREATE TABLE `checkouts` (
  `checkout_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checkouts`
--

INSERT INTO `checkouts` (`checkout_id`, `customer_id`, `created_at`, `notes`) VALUES
(1, 1, '2025-11-15 06:34:35', ''),
(2, 1, '2025-11-15 07:46:50', ''),
(3, 1, '2025-11-15 07:56:51', ''),
(4, 1, '2025-11-25 01:17:56', ''),
(5, 1, '2025-11-25 20:43:58', ''),
(6, 1, '2025-11-25 23:54:16', ''),
(7, 1, '2025-11-27 00:23:36', ''),
(8, 1, '2025-11-27 00:24:42', ''),
(9, 1, '2025-11-27 00:50:40', ''),
(10, 1, '2025-11-27 01:19:10', ''),
(11, 1, '2025-12-07 22:17:07', ''),
(12, 1, '2025-12-07 22:17:53', ''),
(13, 1, '2025-12-07 22:18:07', ''),
(14, 1, '2025-12-08 22:37:32', ''),
(15, 1, '2025-12-08 22:38:20', ''),
(16, 1, '2025-12-08 22:38:39', ''),
(17, 1, '2025-12-08 22:46:37', ''),
(18, 1, '2025-12-08 22:47:46', '');

-- --------------------------------------------------------

--
-- Table structure for table `containers`
--

CREATE TABLE `containers` (
  `container_id` int NOT NULL,
  `container_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `purchase_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `containers`
--

INSERT INTO `containers` (`container_id`, `container_type`, `price`, `photo`, `is_visible`, `purchase_price`) VALUES
(1, 'Slim Container', 30.00, 'container_6916a0e1457d26.77028816.jpg', 1, 250.00),
(2, 'Round Container', 40.00, 'container_6916a103e32bf1.53719413.jpg', 1, 250.00),
(3, 'Small Slim Container', 25.00, 'container_6916a1219a4d72.88603141.jpg', 1, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int NOT NULL,
  `account_id` int DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `customer_contact` char(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `street` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `barangay` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `province` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `account_id`, `first_name`, `middle_name`, `last_name`, `customer_contact`, `email`, `street`, `barangay`, `city`, `province`, `date_created`) VALUES
(1, 13, 'user1', NULL, 'user1', '09663085902', 'jfaustino.a12345404@umak.edu.ph', 'Milkweed', 'Rizal', 'Taguig', 'Metro Manila', '2025-11-15 06:34:02');

-- --------------------------------------------------------

--
-- Table structure for table `customer_feedback`
--

CREATE TABLE `customer_feedback` (
  `feedback_id` int NOT NULL,
  `reference_id` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `feedback_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `feedback_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cutoff_time_setting`
--

CREATE TABLE `cutoff_time_setting` (
  `id` int NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `cutoff_time` time NOT NULL DEFAULT '16:00:00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cutoff_time_setting`
--

INSERT INTO `cutoff_time_setting` (`id`, `is_enabled`, `cutoff_time`, `created_at`, `updated_at`) VALUES
(1, 1, '16:00:00', '2025-11-13 05:48:04', '2025-11-14 05:27:36');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int NOT NULL,
  `batch_id` int DEFAULT NULL,
  `delivery_status_id` int DEFAULT NULL,
  `delivery_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery_type` enum('pickup','delivery') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'delivery',
  `actual_time` timestamp NULL DEFAULT NULL,
  `scheduled_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `batch_id`, `delivery_status_id`, `delivery_date`, `notes`, `delivery_type`, `actual_time`, `scheduled_time`) VALUES
(1, 1, 3, '2025-11-15 16:00:00', 'Auto-created pickup for order 301175', 'pickup', '2025-11-15 06:34:54', '2025-11-15 23:00:00'),
(2, 1, 3, '2025-11-15 16:00:00', 'Auto-created delivery for order 301175', 'delivery', '2025-11-15 06:35:09', '2025-11-16 02:00:00'),
(3, 2, 3, '2025-11-14 16:00:00', 'Auto-created pickup for order 651780', 'pickup', '2025-11-15 07:49:44', '2025-11-14 23:00:00'),
(4, 2, 3, '2025-11-14 16:00:00', 'Auto-created delivery for order 651780', 'delivery', '2025-11-15 07:53:24', '2025-11-15 02:00:00'),
(5, 3, 3, '2025-11-14 16:00:00', 'Auto-created pickup for order 445133', 'pickup', '2025-11-15 08:45:48', '2025-11-14 23:00:00'),
(6, 3, 2, '2025-11-14 16:00:00', 'Auto-created delivery for order 445133', 'delivery', NULL, '2025-11-15 02:00:00'),
(7, 4, 3, '2025-11-24 16:00:00', 'Auto-created pickup for order 204083', 'pickup', '2025-11-25 01:18:36', '2025-11-24 23:00:00'),
(8, 4, 2, '2025-11-24 16:00:00', 'Auto-created delivery for order 204083', 'delivery', NULL, '2025-11-25 02:00:00'),
(9, 5, 3, '2025-11-24 16:00:00', 'Auto-created pickup for order 037914', 'pickup', '2025-11-25 20:45:41', '2025-11-24 23:00:00'),
(10, 5, 3, '2025-11-24 16:00:00', 'Auto-created delivery for order 037914', 'delivery', '2025-11-25 23:51:08', '2025-11-25 02:00:00'),
(11, 6, 3, '2025-11-24 16:00:00', 'Auto-created pickup for order 522495', 'pickup', '2025-11-26 00:04:21', '2025-11-24 23:00:00'),
(12, 6, 3, '2025-11-24 16:00:00', 'Auto-created delivery for order 522495', 'delivery', '2025-11-26 00:04:35', '2025-11-25 02:00:00'),
(13, 7, 3, '2025-11-26 16:00:00', 'Auto-created pickup for order 800512', 'pickup', '2025-11-27 00:40:02', '2025-11-26 23:00:00'),
(14, 7, 3, '2025-11-26 16:00:00', 'Auto-created delivery for order 800512', 'delivery', '2025-11-27 00:40:24', '2025-11-27 02:00:00'),
(15, 7, 3, '2025-11-26 16:00:00', 'Auto-created pickup for order 945429', 'pickup', '2025-11-27 00:40:02', '2025-11-26 23:00:00'),
(16, 7, 3, '2025-11-26 16:00:00', 'Auto-created delivery for order 945429', 'delivery', '2025-11-27 00:40:24', '2025-11-27 02:00:00'),
(17, 8, 3, '2025-11-26 16:00:00', 'Auto-created pickup for order 507794', 'pickup', '2025-11-27 01:31:15', '2025-11-26 23:00:00'),
(18, 8, 3, '2025-11-26 16:00:00', 'Auto-created delivery for order 507794', 'delivery', '2025-11-27 01:31:34', '2025-11-27 02:00:00'),
(19, 8, 3, '2025-11-26 16:00:00', 'Auto-created pickup for order 108088', 'pickup', '2025-11-27 01:31:15', '2025-11-26 23:00:00'),
(20, 8, 3, '2025-11-26 16:00:00', 'Auto-created delivery for order 108088', 'delivery', '2025-11-27 01:31:34', '2025-11-27 02:00:00'),
(21, 9, 3, '2025-12-06 16:00:00', 'Auto-created pickup for order 239617', 'pickup', '2025-12-07 22:22:36', '2025-12-06 23:00:00'),
(22, 9, 3, '2025-12-06 16:00:00', 'Auto-created delivery for order 239617', 'delivery', '2025-12-07 22:23:01', '2025-12-07 02:00:00'),
(23, 9, 3, '2025-12-06 16:00:00', 'Auto-created pickup for order 242167', 'pickup', '2025-12-07 22:22:36', '2025-12-06 23:00:00'),
(24, 9, 3, '2025-12-06 16:00:00', 'Auto-created delivery for order 242167', 'delivery', '2025-12-07 22:23:01', '2025-12-07 02:00:00'),
(25, 9, 3, '2025-12-06 16:00:00', 'Auto-created pickup for order 001602', 'pickup', '2025-12-07 22:22:36', '2025-12-06 23:00:00'),
(26, 9, 3, '2025-12-06 16:00:00', 'Auto-created delivery for order 001602', 'delivery', '2025-12-07 22:23:01', '2025-12-07 02:00:00'),
(27, 10, 3, '2025-12-07 16:00:00', 'Auto-created pickup for order 489456', 'pickup', '2025-12-08 22:39:24', '2025-12-07 23:00:00'),
(28, 10, 3, '2025-12-07 16:00:00', 'Auto-created delivery for order 489456', 'delivery', '2025-12-08 22:46:58', '2025-12-08 02:00:00'),
(29, 10, 3, '2025-12-07 16:00:00', 'Auto-created pickup for order 860181', 'pickup', '2025-12-08 22:39:24', '2025-12-07 23:00:00'),
(30, 10, 3, '2025-12-07 16:00:00', 'Auto-created delivery for order 860181', 'delivery', '2025-12-08 22:46:58', '2025-12-08 02:00:00'),
(31, 10, 3, '2025-12-07 16:00:00', 'Auto-created pickup for order 891238', 'pickup', '2025-12-08 22:39:24', '2025-12-07 23:00:00'),
(32, 10, 3, '2025-12-07 16:00:00', 'Auto-created delivery for order 891238', 'delivery', '2025-12-08 22:46:58', '2025-12-08 02:00:00'),
(33, 11, 3, '2025-12-07 16:00:00', 'Auto-created pickup for order 560095', 'pickup', '2025-12-08 22:48:04', '2025-12-07 23:00:00'),
(34, 11, 3, '2025-12-07 16:00:00', 'Auto-created delivery for order 560095', 'delivery', '2025-12-08 22:49:13', '2025-12-08 02:00:00'),
(35, 11, 3, '2025-12-07 16:00:00', 'Auto-created pickup for order 819540', 'pickup', '2025-12-08 22:48:04', '2025-12-07 23:00:00'),
(36, 11, 3, '2025-12-07 16:00:00', 'Auto-created delivery for order 819540', 'delivery', '2025-12-08 22:49:13', '2025-12-08 02:00:00');

--
-- Triggers `deliveries`
--
DELIMITER $$
CREATE TRIGGER `delivery_status_notification` AFTER UPDATE ON `deliveries` FOR EACH ROW BEGIN
  DECLARE v_user INT;
  DECLARE v_msg VARCHAR(255);
  DECLARE v_ref VARCHAR(32);

  IF NEW.delivery_status_id <> OLD.delivery_status_id THEN
    -- notify only for important transitions (e.g. dispatched=2, delivered=3)
    IF NEW.delivery_status_id IN (2,3) THEN
      SELECT o.reference_id, a.account_id INTO v_ref, v_user
        FROM orders o
        JOIN accounts a ON a.customer_id = o.customer_id
        WHERE o.batch_id = NEW.batch_id
        LIMIT 1;
      IF v_user IS NOT NULL THEN
        SET v_msg = CONCAT(
          CASE WHEN NEW.delivery_type = 'pickup' THEN 'Pickup for order #' ELSE 'Delivery for order #' END,
          v_ref,
          CASE
            WHEN NEW.delivery_status_id = 2 THEN ' has started and is on its way.'
            WHEN NEW.delivery_status_id = 3 THEN ' has been completed successfully.'
            ELSE CONCAT(' updated to delivery status id ', NEW.delivery_status_id, '.')
          END
        );
        INSERT IGNORE INTO notifications (user_id, message, reference_id, notification_type)
          VALUES (v_user, v_msg, v_ref, 'delivery_status');
      END IF;
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_status`
--

CREATE TABLE `delivery_status` (
  `delivery_status_id` int NOT NULL,
  `status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_status`
--

INSERT INTO `delivery_status` (`delivery_status_id`, `status_name`) VALUES
(3, 'Completed'),
(4, 'Failed'),
(2, 'In Progress'),
(1, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int NOT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `employee_contact` char(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `middle_name`, `last_name`, `employee_contact`, `role`) VALUES
(1, 'Kurt Christian', NULL, 'Luzano', '09171112222', 'Driver'),
(2, 'Justine Ace Nino', NULL, 'San Jose', '09173334444', 'Assistant'),
(3, 'Romer John', NULL, 'Abujen', '000009', 'Driver'),
(4, 'Jehiel', NULL, 'Atole', '000000', 'Assistant'),
(5, 'sean martin', 'aban', 'pante', '09867687654', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedback_category`
--

CREATE TABLE `feedback_category` (
  `category_id` int NOT NULL,
  `category_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_category`
--

INSERT INTO `feedback_category` (`category_id`, `category_name`, `created_at`) VALUES
(1, 'Product', '2025-11-11 13:32:27'),
(2, 'Service', '2025-11-11 13:32:27'),
(3, 'Delivery', '2025-11-11 13:32:27'),
(4, 'Website', '2025-11-11 13:32:27'),
(5, 'Other', '2025-11-11 13:32:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `container_id` int NOT NULL,
  `container_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`container_id`, `container_type`, `stock`, `last_updated`) VALUES
(1, 'Slim Container', 83, '2025-12-08 22:47:46'),
(2, 'Round Container', 87, '2025-12-08 22:38:20'),
(3, 'Small Slim Container', 85, '2025-12-08 22:46:37');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int NOT NULL,
  `user_id` int NOT NULL,
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reference_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `reference_id`, `notification_type`, `is_read`, `created_at`) VALUES
(1, 13, 'Your order #301175 has been placed successfully! Total: ₱30.00', '301175', 'order_placed', 1, '2025-11-15 06:34:35'),
(2, 13, 'Pickup for order #301175 has started and is on its way.', '301175', 'delivery_status', 1, '2025-11-15 06:34:46'),
(3, 13, 'Pickup for order #301175 has been completed successfully.', '301175', 'delivery_status', 1, '2025-11-15 06:34:54'),
(4, 13, 'Delivery for order #301175 has started and is on its way.', '301175', 'delivery_status', 1, '2025-11-15 06:35:07'),
(5, 13, 'Delivery for order #301175 has been completed successfully.', '301175', 'delivery_status', 1, '2025-11-15 06:35:09'),
(6, 13, 'Payment for order #301175 has been confirmed and processed successfully.', '301175', 'payment_status', 1, '2025-11-15 06:35:09'),
(7, 13, 'Your order #651780 has been placed successfully! Total: ₱230.00', '651780', 'order_placed', 1, '2025-11-15 07:46:50'),
(8, 13, 'Pickup for order #651780 has started and is on its way.', '651780', 'delivery_status', 1, '2025-11-15 07:47:45'),
(9, 13, 'Pickup for order #651780 has been completed successfully.', '651780', 'delivery_status', 1, '2025-11-15 07:49:44'),
(10, 13, 'Delivery for order #651780 has started and is on its way.', '651780', 'delivery_status', 1, '2025-11-15 07:49:54'),
(11, 13, 'Delivery for order #651780 has been completed successfully.', '651780', 'delivery_status', 1, '2025-11-15 07:53:24'),
(12, 13, 'Payment for order #651780 has been confirmed and processed successfully.', '651780', 'payment_status', 1, '2025-11-15 07:53:24'),
(13, 13, 'Your order #445133 has been placed successfully! Total: ₱690.00', '445133', 'order_placed', 1, '2025-11-15 07:56:51'),
(14, 13, 'Pickup for order #445133 has started and is on its way.', '445133', 'delivery_status', 1, '2025-11-15 08:45:46'),
(15, 13, 'Pickup for order #445133 has been completed successfully.', '445133', 'delivery_status', 1, '2025-11-15 08:45:48'),
(16, 13, 'Delivery for order #445133 has started and is on its way.', '445133', 'delivery_status', 1, '2025-11-15 08:45:50'),
(17, 13, 'Your order #204083 has been placed successfully! Total: ₱30.00', '204083', 'order_placed', 1, '2025-11-25 01:17:56'),
(18, 13, 'Pickup for order #204083 has started and is on its way.', '204083', 'delivery_status', 1, '2025-11-25 01:18:27'),
(19, 13, 'Pickup for order #204083 has been completed successfully.', '204083', 'delivery_status', 1, '2025-11-25 01:18:34'),
(20, 13, 'Delivery for order #204083 has started and is on its way.', '204083', 'delivery_status', 1, '2025-11-25 01:18:38'),
(21, 13, 'Your order #037914 has been placed successfully! Total: ₱30.00', '037914', 'order_placed', 1, '2025-11-25 20:43:58'),
(22, 13, 'Pickup for order #037914 has started and is on its way.', '037914', 'delivery_status', 1, '2025-11-25 20:45:37'),
(23, 13, 'Pickup for order #037914 has been completed successfully.', '037914', 'delivery_status', 1, '2025-11-25 20:45:41'),
(24, 13, 'Delivery for order #037914 has started and is on its way.', '037914', 'delivery_status', 1, '2025-11-25 20:45:47'),
(25, 13, 'Payment for order #037914 has been confirmed and processed successfully.', '037914', 'payment_status', 1, '2025-11-25 23:51:04'),
(26, 13, 'Delivery for order #037914 has been completed successfully.', '037914', 'delivery_status', 1, '2025-11-25 23:51:08'),
(27, 13, 'Your order #037914 has been delivered', '037914', 'order_status', 1, '2025-11-25 23:51:11'),
(28, 13, 'Your order #522495 has been placed successfully! Total: ₱30.00', '522495', 'order_placed', 1, '2025-11-25 23:54:17'),
(29, 13, 'Pickup for order #522495 has started and is on its way.', '522495', 'delivery_status', 1, '2025-11-26 00:04:17'),
(30, 13, 'Pickup for order #522495 has been completed successfully.', '522495', 'delivery_status', 1, '2025-11-26 00:04:21'),
(31, 13, 'Delivery for order #522495 has started and is on its way.', '522495', 'delivery_status', 1, '2025-11-26 00:04:24'),
(32, 13, 'Payment for order #522495 has been confirmed and processed successfully.', '522495', 'payment_status', 1, '2025-11-26 00:04:35'),
(33, 13, 'Delivery for order #522495 has been completed successfully.', '522495', 'delivery_status', 1, '2025-11-26 00:04:35'),
(34, 13, 'Your order #522495 has been delivered', '522495', 'order_status', 1, '2025-11-26 00:04:36'),
(35, 13, 'Your order #800512 has been placed successfully! Total: ₱30.00', '800512', 'order_placed', 1, '2025-11-27 00:23:38'),
(36, 13, 'Your order #945429 has been placed successfully! Total: ₱30.00', '945429', 'order_placed', 1, '2025-11-27 00:24:43'),
(37, 13, 'Pickup for order #800512 has started and is on its way.', '800512', 'delivery_status', 1, '2025-11-27 00:39:50'),
(39, 13, 'Pickup for order #800512 has been completed successfully.', '800512', 'delivery_status', 1, '2025-11-27 00:40:02'),
(41, 13, 'Delivery for order #800512 has started and is on its way.', '800512', 'delivery_status', 1, '2025-11-27 00:40:07'),
(43, 13, 'Payment for order #945429 has been confirmed and processed successfully.', '945429', 'payment_status', 1, '2025-11-27 00:40:23'),
(44, 13, 'Delivery for order #800512 has been completed successfully.', '800512', 'delivery_status', 1, '2025-11-27 00:40:24'),
(46, 13, 'Your order #945429 has been delivered', '945429', 'order_status', 1, '2025-11-27 00:40:26'),
(47, 13, 'Your order #507794 has been placed successfully! Total: ₱30.00', '507794', 'order_placed', 1, '2025-11-27 00:50:40'),
(48, 13, 'Your order #108088 has been placed successfully! Total: ₱30.00', '108088', 'order_placed', 1, '2025-11-27 01:19:11'),
(49, 13, 'Pickup for order #108088 has started and is on its way.', '108088', 'delivery_status', 1, '2025-11-27 01:30:47'),
(51, 13, 'Pickup for order #108088 has been completed successfully.', '108088', 'delivery_status', 1, '2025-11-27 01:31:15'),
(53, 13, 'Delivery for order #108088 has started and is on its way.', '108088', 'delivery_status', 1, '2025-11-27 01:31:22'),
(55, 13, 'Payment for order #108088 has been confirmed and processed successfully.', '108088', 'payment_status', 1, '2025-11-27 01:31:34'),
(56, 13, 'Delivery for order #108088 has been completed successfully.', '108088', 'delivery_status', 1, '2025-11-27 01:31:34'),
(58, 13, 'Your order #108088 has been delivered', '108088', 'order_status', 1, '2025-11-27 01:31:35'),
(59, 13, 'Your order #239617 has been placed successfully! Total: ₱250.00', '239617', 'order_placed', 1, '2025-12-07 22:17:07'),
(60, 13, 'Your order #242167 has been placed successfully! Total: ₱250.00', '242167', 'order_placed', 1, '2025-12-07 22:17:53'),
(61, 13, 'Your order #001602 has been placed successfully! Total: ₱100.00', '001602', 'order_placed', 1, '2025-12-07 22:18:07'),
(65, 13, 'Pickup for order #001602 has started and is on its way.', '001602', 'delivery_status', 1, '2025-12-07 22:21:41'),
(68, 13, 'Your order #REF_ID pickup has started.', '001602', 'pickup_started', 1, '2025-12-07 22:21:41'),
(69, 13, 'Your order #REF_ID pickup has started.', '239617', 'pickup_started', 1, '2025-12-07 22:21:42'),
(70, 13, 'Your order #REF_ID pickup has started.', '242167', 'pickup_started', 1, '2025-12-07 22:21:42'),
(71, 13, 'Pickup for order #001602 has been completed successfully.', '001602', 'delivery_status', 1, '2025-12-07 22:22:36'),
(74, 13, 'Your order #REF_ID has been picked up and is on the way.', '001602', 'pickup_completed', 1, '2025-12-07 22:22:37'),
(75, 13, 'Your order #REF_ID has been picked up and is on the way.', '239617', 'pickup_completed', 1, '2025-12-07 22:22:37'),
(76, 13, 'Your order #REF_ID has been picked up and is on the way.', '242167', 'pickup_completed', 1, '2025-12-07 22:22:37'),
(77, 13, 'Delivery for order #001602 has started and is on its way.', '001602', 'delivery_status', 1, '2025-12-07 22:22:42'),
(80, 13, 'Your order #REF_ID is out for delivery.', '001602', 'out_for_delivery', 1, '2025-12-07 22:22:44'),
(81, 13, 'Your order #REF_ID is out for delivery.', '239617', 'out_for_delivery', 1, '2025-12-07 22:22:44'),
(82, 13, 'Your order #REF_ID is out for delivery.', '242167', 'out_for_delivery', 1, '2025-12-07 22:22:44'),
(83, 13, 'Payment for order #001602 has been confirmed and processed successfully.', '001602', 'payment_status', 1, '2025-12-07 22:23:01'),
(84, 13, 'Delivery for order #001602 has been completed successfully.', '001602', 'delivery_status', 1, '2025-12-07 22:23:01'),
(87, 13, 'Your order #001602 has been delivered', '001602', 'order_status', 1, '2025-12-07 22:23:01'),
(88, 13, 'Your order #489456 has been placed successfully! Total: ₱250.00', '489456', 'order_placed', 1, '2025-12-08 22:37:35'),
(89, 13, 'Your order #860181 has been placed successfully! Total: ₱250.00', '860181', 'order_placed', 1, '2025-12-08 22:38:21'),
(90, 13, 'Your order #891238 has been placed successfully! Total: ₱100.00', '891238', 'order_placed', 1, '2025-12-08 22:38:40'),
(91, 13, 'Pickup for order #489456 has started and is on its way.', '489456', 'delivery_status', 1, '2025-12-08 22:39:12'),
(94, 13, 'Your order #REF_ID pickup has started.', '489456', 'pickup_started', 1, '2025-12-08 22:39:15'),
(95, 13, 'Your order #REF_ID pickup has started.', '860181', 'pickup_started', 1, '2025-12-08 22:39:16'),
(96, 13, 'Your order #REF_ID pickup has started.', '891238', 'pickup_started', 1, '2025-12-08 22:39:16'),
(97, 13, 'Pickup for order #489456 has been completed successfully.', '489456', 'delivery_status', 1, '2025-12-08 22:39:24'),
(100, 13, 'Your order #REF_ID has been picked up and is on the way.', '489456', 'pickup_completed', 1, '2025-12-08 22:39:25'),
(101, 13, 'Your order #REF_ID has been picked up and is on the way.', '860181', 'pickup_completed', 1, '2025-12-08 22:39:25'),
(102, 13, 'Your order #REF_ID has been picked up and is on the way.', '891238', 'pickup_completed', 1, '2025-12-08 22:39:25'),
(103, 13, 'Delivery for order #489456 has started and is on its way.', '489456', 'delivery_status', 1, '2025-12-08 22:39:30'),
(106, 13, 'Your order #REF_ID is out for delivery.', '489456', 'out_for_delivery', 1, '2025-12-08 22:39:31'),
(107, 13, 'Your order #REF_ID is out for delivery.', '860181', 'out_for_delivery', 1, '2025-12-08 22:39:31'),
(108, 13, 'Your order #REF_ID is out for delivery.', '891238', 'out_for_delivery', 1, '2025-12-08 22:39:31'),
(109, 13, 'Payment for order #891238 has been confirmed and processed successfully.', '891238', 'payment_status', 1, '2025-12-08 22:39:50'),
(110, 13, 'Your order #891238 has been delivered', '891238', 'order_status', 1, '2025-12-08 22:39:50'),
(111, 13, 'Your order with tracking number #860181 has been marked as failed. Please contact support.', '860181', 'order_status', 1, '2025-12-08 22:40:02'),
(112, 13, 'Your order #860181 has been failed', '860181', 'order_status', 1, '2025-12-08 22:40:02'),
(113, 13, 'Your order with tracking number #489456 has been marked as failed. Please contact support.', '489456', 'order_status', 1, '2025-12-08 22:45:42'),
(114, 13, 'Your order #489456 has been failed', '489456', 'order_status', 1, '2025-12-08 22:45:46'),
(115, 13, 'Your order #560095 has been placed successfully! Total: ₱100.00', '560095', 'order_placed', 1, '2025-12-08 22:46:38'),
(116, 13, 'Delivery for order #489456 has been completed successfully.', '489456', 'delivery_status', 1, '2025-12-08 22:46:58'),
(119, 13, 'Payment for order #489456 has been confirmed and processed successfully.', '489456', 'payment_status', 1, '2025-12-08 22:46:58'),
(120, 13, 'Payment for order #860181 has been confirmed and processed successfully.', '860181', 'payment_status', 1, '2025-12-08 22:46:58'),
(121, 13, 'Your order #819540 has been placed successfully! Total: ₱250.00', '819540', 'order_placed', 1, '2025-12-08 22:47:46'),
(122, 13, 'Pickup for order #560095 has started and is on its way.', '560095', 'delivery_status', 1, '2025-12-08 22:48:00'),
(124, 13, 'Your order #REF_ID pickup has started.', '560095', 'pickup_started', 1, '2025-12-08 22:48:00'),
(125, 13, 'Your order #REF_ID pickup has started.', '819540', 'pickup_started', 1, '2025-12-08 22:48:00'),
(126, 13, 'Pickup for order #560095 has been completed successfully.', '560095', 'delivery_status', 1, '2025-12-08 22:48:04'),
(128, 13, 'Your order #REF_ID has been picked up and is on the way.', '560095', 'pickup_completed', 1, '2025-12-08 22:48:04'),
(129, 13, 'Your order #REF_ID has been picked up and is on the way.', '819540', 'pickup_completed', 1, '2025-12-08 22:48:04'),
(130, 13, 'Delivery for order #560095 has started and is on its way.', '560095', 'delivery_status', 1, '2025-12-08 22:48:06'),
(132, 13, 'Your order #REF_ID is out for delivery.', '560095', 'out_for_delivery', 1, '2025-12-08 22:48:07'),
(133, 13, 'Your order #REF_ID is out for delivery.', '819540', 'out_for_delivery', 1, '2025-12-08 22:48:07'),
(134, 13, 'Payment for order #819540 has been confirmed and processed successfully.', '819540', 'payment_status', 1, '2025-12-08 22:48:16'),
(135, 13, 'Your order #819540 has been delivered', '819540', 'order_status', 1, '2025-12-08 22:48:17'),
(136, 13, 'Your order with tracking number #560095 has been marked as failed. Please contact support.', '560095', 'order_status', 1, '2025-12-08 22:48:50'),
(137, 13, 'Your order #560095 has been failed', '560095', 'order_status', 1, '2025-12-08 22:48:50'),
(138, 13, 'Delivery for order #560095 has been completed successfully.', '560095', 'delivery_status', 1, '2025-12-08 22:49:13'),
(140, 13, 'Payment for order #560095 has been confirmed and processed successfully.', '560095', 'payment_status', 1, '2025-12-08 22:49:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `reference_id` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `customer_id` int DEFAULT NULL,
  `checkout_id` int DEFAULT NULL,
  `order_type_id` int DEFAULT NULL,
  `batch_id` int DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivery_date` date DEFAULT NULL,
  `order_status_id` int DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_personnel_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_collected_amount` decimal(10,2) DEFAULT NULL,
  `failed_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `delivery_completed_at` datetime DEFAULT NULL,
  `delivery_failed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`reference_id`, `customer_id`, `checkout_id`, `order_type_id`, `batch_id`, `order_date`, `delivery_date`, `order_status_id`, `total_amount`, `delivery_personnel_name`, `payment_collected_amount`, `failed_reason`, `delivery_completed_at`, `delivery_failed_at`) VALUES
('001602', 1, 13, 1, 9, '2025-12-07 22:18:07', '2025-12-07', 3, 100.00, 'admin', NULL, NULL, '2025-12-08 06:23:01', NULL),
('037914', 1, 5, 1, 5, '2025-11-25 20:43:58', '2025-11-25', 3, 30.00, 'admin', NULL, NULL, '2025-11-26 07:51:08', NULL),
('108088', 1, 10, 1, 8, '2025-11-27 01:19:10', '2025-11-27', 3, 30.00, 'admin', NULL, NULL, '2025-11-27 09:31:34', NULL),
('204083', 1, 4, 1, 4, '2025-11-25 01:17:56', '2025-11-25', 2, 30.00, NULL, NULL, NULL, NULL, NULL),
('239617', 1, 11, 1, 9, '2025-12-07 22:17:07', '2025-12-07', 2, 250.00, NULL, NULL, NULL, NULL, NULL),
('242167', 1, 12, 1, 9, '2025-12-07 22:17:53', '2025-12-07', 2, 250.00, NULL, NULL, NULL, NULL, NULL),
('301175', 1, 1, 1, 1, '2025-11-15 06:34:35', '2025-11-16', 3, 30.00, NULL, NULL, NULL, NULL, NULL),
('445133', 1, 3, 1, 3, '2025-11-15 07:56:51', '2025-11-15', 2, 690.00, NULL, NULL, NULL, NULL, NULL),
('489456', 1, 14, 1, 10, '2025-12-08 22:37:33', '2025-12-08', 3, 250.00, 'admin', NULL, 'wala daw pera', NULL, '2025-12-09 06:45:42'),
('507794', 1, 9, 1, 8, '2025-11-27 00:50:40', '2025-11-27', 2, 30.00, NULL, NULL, NULL, NULL, NULL),
('522495', 1, 6, 1, 6, '2025-11-25 23:54:17', '2025-11-25', 3, 30.00, 'admin', NULL, NULL, '2025-11-26 08:04:35', NULL),
('560095', 1, 17, 1, 11, '2025-12-08 22:46:37', '2025-12-08', 3, 100.00, 'admin', NULL, 'walang tao', NULL, '2025-12-09 06:48:50'),
('651780', 1, 2, 1, 2, '2025-11-15 07:46:50', '2025-11-15', 3, 230.00, NULL, NULL, NULL, NULL, NULL),
('800512', 1, 7, 1, 7, '2025-11-27 00:23:36', '2025-11-27', 2, 30.00, NULL, NULL, NULL, NULL, NULL),
('819540', 1, 18, 1, 11, '2025-12-08 22:47:46', '2025-12-08', 3, 250.00, 'admin', NULL, NULL, '2025-12-09 06:48:16', NULL),
('860181', 1, 15, 1, 10, '2025-12-08 22:38:20', '2025-12-08', 3, 250.00, 'admin', NULL, 'walang tao', NULL, '2025-12-09 06:40:02'),
('891238', 1, 16, 1, 10, '2025-12-08 22:38:39', '2025-12-08', 3, 100.00, 'admin', NULL, NULL, '2025-12-09 06:39:50', NULL),
('945429', 1, 8, 1, 7, '2025-11-27 00:24:42', '2025-11-27', 3, 30.00, 'admin', NULL, NULL, '2025-11-27 08:40:23', NULL);

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `order_status_notification` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
  DECLARE v_user INT;
  DECLARE v_msg VARCHAR(255);
  DECLARE v_ref VARCHAR(32);

  IF NEW.order_status_id <> OLD.order_status_id THEN
    -- avoid duplicates caused by delivery flow: skip if delivery already handling (batch check)
    IF NOT (NEW.order_status_id IN (2,3) AND EXISTS (
      SELECT 1 FROM deliveries d WHERE d.batch_id = NEW.batch_id AND d.delivery_status_id IN (2,3)
    )) THEN
      SELECT a.account_id INTO v_user
        FROM accounts a
        WHERE a.customer_id = NEW.customer_id
        LIMIT 1;
      SET v_ref = NEW.reference_id;
      IF v_user IS NOT NULL THEN
        SET v_msg = CONCAT('Your order with tracking number #', v_ref, ' has been ',
          CASE
            WHEN NEW.order_status_id = 1 THEN 'received and is being processed'
            WHEN NEW.order_status_id = 2 THEN 'dispatched and is on its way'
            WHEN NEW.order_status_id = 3 THEN 'successfully delivered'
            WHEN NEW.order_status_id = 4 THEN 'marked as failed. Please contact support'
            ELSE CONCAT('updated to status id ', NEW.order_status_id)
          END, '.');
        INSERT IGNORE INTO notifications (user_id, message, reference_id, notification_type)
          VALUES (v_user, v_msg, v_ref, 'order_status');
      END IF;
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `order_detail_id` int NOT NULL,
  `reference_id` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `batch_number` int NOT NULL DEFAULT '1',
  `container_id` int DEFAULT NULL,
  `water_type_id` int DEFAULT NULL,
  `order_type_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_detail_id`, `reference_id`, `batch_number`, `container_id`, `water_type_id`, `order_type_id`, `quantity`, `subtotal`) VALUES
(1, '301175', 1, 1, 1, 1, 1, 30.00),
(2, '651780', 1, 1, 3, 2, 1, 230.00),
(3, '445133', 2, 1, 1, 2, 3, 690.00),
(4, '204083', 1, 1, 1, 1, 1, 30.00),
(5, '037914', 2, 1, 1, 1, 1, 30.00),
(6, '522495', 3, 1, 1, 1, 1, 30.00),
(7, '800512', 1, 1, 1, 1, 1, 30.00),
(8, '945429', 1, 1, 1, 1, 1, 30.00),
(9, '507794', 2, 1, 1, 1, 1, 30.00),
(10, '108088', 2, 1, 1, 1, 1, 30.00),
(11, '239617', 1, 1, 1, 2, 1, 250.00),
(12, '242167', 1, 2, 1, 2, 1, 250.00),
(13, '001602', 1, 3, 1, 2, 1, 100.00),
(14, '489456', 1, 1, 1, 2, 1, 250.00),
(15, '860181', 1, 2, 1, 2, 1, 250.00),
(16, '891238', 1, 3, 1, 2, 1, 100.00),
(17, '560095', 2, 3, 1, 2, 1, 100.00),
(18, '819540', 2, 1, 1, 2, 1, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `status_id` int NOT NULL,
  `status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status`
--

INSERT INTO `order_status` (`status_id`, `status_name`) VALUES
(3, 'Completed'),
(4, 'Failed'),
(2, 'In Progress'),
(1, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `order_types`
--

CREATE TABLE `order_types` (
  `order_type_id` int NOT NULL,
  `type_name` enum('Refill','Purchase New Container/s') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_types`
--

INSERT INTO `order_types` (`order_type_id`, `type_name`) VALUES
(1, 'Refill'),
(2, 'Purchase New Container/s');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int NOT NULL,
  `reference_id` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `payment_method_id` int DEFAULT NULL,
  `payment_status_id` int DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `amount_paid` decimal(10,2) NOT NULL,
  `transaction_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `reference_id`, `payment_method_id`, `payment_status_id`, `payment_date`, `amount_paid`, `transaction_reference`) VALUES
(1, '301175', 1, 2, '2025-11-15 06:34:35', 30.00, NULL),
(2, '651780', 1, 2, '2025-11-15 07:46:50', 230.00, NULL),
(3, '445133', 1, 1, '2025-11-15 07:56:51', 690.00, NULL),
(4, '204083', 1, 1, '2025-11-25 01:17:56', 30.00, NULL),
(5, '037914', 1, 2, '2025-11-25 23:51:04', 30.00, NULL),
(6, '522495', 1, 2, '2025-11-26 00:04:35', 30.00, NULL),
(7, '800512', 1, 1, '2025-11-27 00:23:38', 30.00, NULL),
(8, '945429', 1, 2, '2025-11-27 00:40:23', 30.00, NULL),
(9, '507794', 1, 1, '2025-11-27 00:50:40', 30.00, NULL),
(10, '108088', 1, 2, '2025-11-27 01:31:34', 30.00, NULL),
(11, '239617', 1, 1, '2025-12-07 22:17:07', 250.00, NULL),
(12, '242167', 1, 1, '2025-12-07 22:17:53', 250.00, NULL),
(13, '001602', 1, 2, '2025-12-07 22:23:01', 100.00, NULL),
(14, '489456', 1, 2, '2025-12-08 22:37:34', 250.00, NULL),
(15, '860181', 1, 2, '2025-12-08 22:38:21', 250.00, NULL),
(16, '891238', 1, 2, '2025-12-08 22:39:50', 100.00, NULL),
(17, '560095', 1, 2, '2025-12-08 22:46:37', 100.00, NULL),
(18, '819540', 1, 2, '2025-12-08 22:48:16', 250.00, NULL);

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `payment_status_notification` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
  DECLARE v_user INT;
  DECLARE v_msg VARCHAR(255);
  DECLARE v_ref VARCHAR(32);

  IF NEW.payment_status_id <> OLD.payment_status_id THEN
    SELECT a.account_id INTO v_user
      FROM orders o
      JOIN accounts a ON a.customer_id = o.customer_id
      WHERE o.reference_id = NEW.reference_id
      LIMIT 1;
    SET v_ref = NEW.reference_id;
    IF v_user IS NOT NULL THEN
      SET v_msg = CONCAT('Payment for order #', v_ref, ' has been ',
        CASE
          WHEN NEW.payment_status_id = 1 THEN 'received and is being verified'
          WHEN NEW.payment_status_id = 2 THEN 'confirmed and processed successfully'
          WHEN NEW.payment_status_id = 3 THEN 'declined. Please update your payment information'
          ELSE CONCAT('updated to payment status id ', NEW.payment_status_id)
        END, '.');
      INSERT IGNORE INTO notifications (user_id, message, reference_id, notification_type)
        VALUES (v_user, v_msg, v_ref, 'payment_status');
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `payment_method_id` int NOT NULL,
  `method_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`payment_method_id`, `method_name`) VALUES
(1, 'COD'),
(2, 'GCash');

-- --------------------------------------------------------

--
-- Table structure for table `payment_status`
--

CREATE TABLE `payment_status` (
  `payment_status_id` int NOT NULL,
  `status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_status`
--

INSERT INTO `payment_status` (`payment_status_id`, `status_name`) VALUES
(3, 'Failed'),
(2, 'Paid'),
(1, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `k` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `v` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`k`, `v`) VALUES
('purchase_new_price', '230');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int NOT NULL,
  `staff_user` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `staff_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `staff_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_user`, `first_name`, `last_name`, `staff_password`, `staff_role`) VALUES
(2, 'cusman1', NULL, NULL, '123', 'customer manager'),
(3, 'salman1', NULL, NULL, '123', 'sales manager'),
(4, 'rider1', 'rider', 'rider', '$2y$10$c/JKgTsbMFF6Yhgx0zqApuGRity84Nr2PNSyeroTVg7LbhzB0CC9O', 'Driver');

-- --------------------------------------------------------

--
-- Table structure for table `water_types`
--

CREATE TABLE `water_types` (
  `water_type_id` int NOT NULL,
  `type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_types`
--

INSERT INTO `water_types` (`water_type_id`, `type_name`, `description`) VALUES
(1, 'Purified Water', 'Clean and safe drinking water through advanced filtration'),
(2, 'Alkaline Water', 'pH-balanced water for better hydration'),
(3, 'Mineral Water', 'Naturally enriched with essential minerals');

-- --------------------------------------------------------

--
-- Structure for view `admin_management_view`
--
DROP TABLE IF EXISTS `admin_management_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_management_view`  AS SELECT `o`.`reference_id` AS `reference_id`, `o`.`order_date` AS `order_date`, `o`.`delivery_date` AS `delivery_date`, `o`.`total_amount` AS `total_amount`, `os`.`status_name` AS `order_status`, `os`.`status_id` AS `order_status_id`, `p`.`payment_status_id` AS `payment_status_id`, `ps`.`status_name` AS `payment_status`, `d`.`delivery_status_id` AS `delivery_status_id`, `ds`.`status_name` AS `delivery_status`, `b`.`batch_id` AS `batch_id`, `b`.`vehicle` AS `vehicle`, `b`.`vehicle_type` AS `vehicle_type`, `bs`.`status_name` AS `batch_status`, concat(`c`.`first_name`,' ',coalesce(`c`.`middle_name`,''),' ',`c`.`last_name`) AS `customer_name`, `c`.`customer_contact` AS `customer_contact`, `c`.`street` AS `street`, `c`.`barangay` AS `barangay`, `c`.`city` AS `city`, `c`.`province` AS `province`, `od`.`quantity` AS `quantity`, `cont`.`container_type` AS `container_type`, `cont`.`price` AS `container_price`, `od`.`subtotal` AS `subtotal`, group_concat(concat(`e`.`first_name`,' ',`e`.`last_name`,' (',coalesce(`e`.`role`,'N/A'),')') separator ', ') AS `assigned_employees` FROM ((((((((((((`orders` `o` left join `order_status` `os` on((`o`.`order_status_id` = `os`.`status_id`))) left join `payments` `p` on((`o`.`reference_id` = `p`.`reference_id`))) left join `payment_status` `ps` on((`p`.`payment_status_id` = `ps`.`payment_status_id`))) left join `batches` `b` on((`o`.`batch_id` = `b`.`batch_id`))) left join `deliveries` `d` on((`b`.`batch_id` = `d`.`batch_id`))) left join `delivery_status` `ds` on((`d`.`delivery_status_id` = `ds`.`delivery_status_id`))) left join `batch_status` `bs` on((`b`.`batch_status_id` = `bs`.`batch_status_id`))) left join `customers` `c` on((`o`.`customer_id` = `c`.`customer_id`))) left join `order_details` `od` on((`o`.`reference_id` = `od`.`reference_id`))) left join `containers` `cont` on((`od`.`container_id` = `cont`.`container_id`))) left join `batch_employees` `be` on((`b`.`batch_id` = `be`.`batch_id`))) left join `employees` `e` on((`be`.`employee_id` = `e`.`employee_id`))) GROUP BY `o`.`reference_id`, `o`.`order_date`, `o`.`delivery_date`, `o`.`total_amount`, `os`.`status_name`, `os`.`status_id`, `p`.`payment_status_id`, `ps`.`status_name`, `d`.`delivery_status_id`, `ds`.`status_name`, `b`.`batch_id`, `b`.`vehicle`, `b`.`vehicle_type`, `bs`.`status_name`, `c`.`customer_id`, `od`.`order_detail_id`, `cont`.`container_id` ORDER BY `o`.`order_date` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `archived_orders`
--
ALTER TABLE `archived_orders`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_reference` (`reference_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `idx_archived_at` (`archived_at`);

--
-- Indexes for table `archive_log`
--
ALTER TABLE `archive_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_archive_date` (`archived_at`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `batch_status_id` (`batch_status_id`);

--
-- Indexes for table `batch_employees`
--
ALTER TABLE `batch_employees`
  ADD PRIMARY KEY (`batch_employee_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `batch_status`
--
ALTER TABLE `batch_status`
  ADD PRIMARY KEY (`batch_status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `business_hours`
--
ALTER TABLE `business_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_day` (`day_of_week`);

--
-- Indexes for table `checkouts`
--
ALTER TABLE `checkouts`
  ADD PRIMARY KEY (`checkout_id`),
  ADD KEY `idx_checkouts_customer_id` (`customer_id`);

--
-- Indexes for table `containers`
--
ALTER TABLE `containers`
  ADD PRIMARY KEY (`container_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `unique_customer_contact` (`customer_contact`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_feedback_category` (`category_id`);

--
-- Indexes for table `cutoff_time_setting`
--
ALTER TABLE `cutoff_time_setting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `delivery_status_id` (`delivery_status_id`);

--
-- Indexes for table `delivery_status`
--
ALTER TABLE `delivery_status`
  ADD PRIMARY KEY (`delivery_status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `feedback_category`
--
ALTER TABLE `feedback_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `unique_category` (`category_name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`container_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD UNIQUE KEY `uniq_notification` (`user_id`,`reference_id`,`notification_type`,`message`(191)),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reference_id` (`reference_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`reference_id`),
  ADD UNIQUE KEY `unique_reference_id` (`reference_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_type_id` (`order_type_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `order_status_id` (`order_status_id`),
  ADD KEY `idx_orders_checkout_id` (`checkout_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_detail_id`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `container_id` (`container_id`),
  ADD KEY `order_details_ibfk_3` (`water_type_id`),
  ADD KEY `order_details_ibfk_4` (`order_type_id`);

--
-- Indexes for table `order_status`
--
ALTER TABLE `order_status`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `order_types`
--
ALTER TABLE `order_types`
  ADD PRIMARY KEY (`order_type_id`),
  ADD UNIQUE KEY `unique_type_name` (`type_name`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `payment_status_id` (`payment_status_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`payment_method_id`),
  ADD UNIQUE KEY `method_name` (`method_name`);

--
-- Indexes for table `payment_status`
--
ALTER TABLE `payment_status`
  ADD PRIMARY KEY (`payment_status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`);

--
-- Indexes for table `water_types`
--
ALTER TABLE `water_types`
  ADD PRIMARY KEY (`water_type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `archived_orders`
--
ALTER TABLE `archived_orders`
  MODIFY `archive_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archive_log`
--
ALTER TABLE `archive_log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `batch_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `batch_employees`
--
ALTER TABLE `batch_employees`
  MODIFY `batch_employee_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_status`
--
ALTER TABLE `batch_status`
  MODIFY `batch_status_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT for table `checkouts`
--
ALTER TABLE `checkouts`
  MODIFY `checkout_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `containers`
--
ALTER TABLE `containers`
  MODIFY `container_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `feedback_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cutoff_time_setting`
--
ALTER TABLE `cutoff_time_setting`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `delivery_status`
--
ALTER TABLE `delivery_status`
  MODIFY `delivery_status_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback_category`
--
ALTER TABLE `feedback_category`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `status_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_types`
--
ALTER TABLE `order_types`
  MODIFY `order_type_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `payment_method_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_status`
--
ALTER TABLE `payment_status`
  MODIFY `payment_status_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `water_types`
--
ALTER TABLE `water_types`
  MODIFY `water_type_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`batch_status_id`) REFERENCES `batch_status` (`batch_status_id`) ON DELETE SET NULL;

--
-- Constraints for table `batch_employees`
--
ALTER TABLE `batch_employees`
  ADD CONSTRAINT `batch_employees_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_employees_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
