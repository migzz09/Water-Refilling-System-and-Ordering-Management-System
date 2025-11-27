-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 27, 2025 at 01:02 AM
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
-- Database: `wrsoms`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `otp_expires` timestamp NOT NULL DEFAULT (current_timestamp() + interval 10 minute),
  `is_verified` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `profile_photo` varchar(255) DEFAULT NULL,
  `deletion_token` varchar(255) DEFAULT NULL,
  `deletion_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`account_id`, `customer_id`, `username`, `password`, `password_changed_at`, `otp`, `otp_expires`, `is_verified`, `is_admin`, `profile_photo`, `deletion_token`, `deletion_expires`) VALUES
(3, NULL, 'admin', 'admin123', NULL, NULL, '2025-11-03 16:55:21', 1, 1, NULL, NULL, NULL),
(4, 1, 'user1', '$2y$10$fUon80zNJHH53avIRTiP9.8/pSlWk3yYkESn7dWrUVqNrKwTlvvZe', NULL, NULL, '2025-11-26 22:34:16', 1, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `admin_management_view`
-- (See below for the actual view)
--
CREATE TABLE `admin_management_view` (
`reference_id` varchar(6)
,`order_date` timestamp
,`delivery_date` date
,`total_amount` decimal(10,2)
,`order_status` varchar(50)
,`order_status_id` int(11)
,`payment_status_id` int(11)
,`payment_status` varchar(50)
,`delivery_status_id` int(11)
,`delivery_status` varchar(50)
,`batch_id` int(11)
,`vehicle` varchar(100)
,`vehicle_type` enum('Tricycle','Car')
,`batch_status` varchar(50)
,`customer_name` varchar(152)
,`customer_contact` char(11)
,`street` varchar(150)
,`barangay` varchar(50)
,`city` varchar(100)
,`province` varchar(100)
,`quantity` int(11)
,`container_type` varchar(50)
,`container_price` decimal(10,2)
,`subtotal` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `archived_orders`
--

CREATE TABLE `archived_orders` (
  `archive_id` int(11) NOT NULL,
  `reference_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Complete order snapshot with all details',
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `archived_orders`
--

INSERT INTO `archived_orders` (`archive_id`, `reference_id`, `user_id`, `delivery_date`, `total_amount`, `order_data`, `archived_at`, `archived_by`) VALUES
(1, '536580', 1, '2025-11-26', 30.00, '{\"reference_id\":\"536580\",\"order_date\":\"2025-11-27 07:08:03\",\"payment_method\":\"GCash\",\"order_status\":\"Completed\",\"delivery_status\":\"Completed\",\"customer\":{\"name\":\"JUAN MIGUEL FAUSTINO\",\"contact\":\"09663085901\"},\"delivery\":{\"address\":\"MAKATI HOMES 204-B MILKWEED ST. TAGUIG CITY, San Miguel, Taguig, Metro Manila\",\"pickup_time\":\"2025-11-26 07:00:00\",\"delivery_time\":\"2025-11-27 07:19:08\"},\"batch\":{\"batch_id\":1,\"batch_number\":1,\"vehicle_type\":\"Tricycle\"},\"items\":[{\"quantity\":1,\"subtotal\":\"30.00\",\"container_type\":\"Slim Container\",\"water_type_name\":\"Alkaline Water\",\"order_type_name\":\"Refill\"}]}', '2025-11-26 23:19:38', 2);

-- --------------------------------------------------------

--
-- Table structure for table `archive_log`
--

CREATE TABLE `archive_log` (
  `log_id` int(11) NOT NULL,
  `archive_type` enum('manual','automatic','end_of_day') NOT NULL,
  `orders_archived` int(11) DEFAULT 0,
  `archived_by` int(11) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_filter` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for archive operations';

--
-- Dumping data for table `archive_log`
--

INSERT INTO `archive_log` (`log_id`, `archive_type`, `orders_archived`, `archived_by`, `archived_at`, `date_filter`, `notes`) VALUES
(1, 'manual', 1, 2, '2025-11-26 23:19:38', '2025-11-27', 'Archived 1 completed orders for 2025-11-27');

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `batch_id` int(11) NOT NULL,
  `batch_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `batch_status_id` int(11) DEFAULT NULL,
  `vehicle` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `vehicle_type` enum('Tricycle','Car') NOT NULL,
  `batch_number` int(11) NOT NULL DEFAULT 1,
  `pickup_time` time DEFAULT NULL,
  `delivery_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`batch_id`, `batch_date`, `batch_status_id`, `vehicle`, `notes`, `vehicle_type`, `batch_number`, `pickup_time`, `delivery_time`) VALUES
(1, '2025-11-25 16:00:00', 3, 'Tricycle #922', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(2, '2025-11-25 16:00:00', 3, 'Tricycle #478', 'Auto-created batch', 'Tricycle', 2, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `batch_status`
--

CREATE TABLE `batch_status` (
  `batch_status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
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
  `id` int(11) NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time NOT NULL DEFAULT '08:00:00',
  `close_time` time NOT NULL DEFAULT '18:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_hours`
--

INSERT INTO `business_hours` (`id`, `day_of_week`, `is_open`, `open_time`, `close_time`, `created_at`, `updated_at`) VALUES
(1, 'Monday', 1, '10:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-25 17:55:29'),
(2, 'Tuesday', 1, '10:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-25 17:55:29'),
(3, 'Wednesday', 1, '01:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-26 16:33:19'),
(4, 'Thursday', 1, '00:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-26 16:33:19'),
(5, 'Friday', 1, '10:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-25 17:55:29'),
(6, 'Saturday', 1, '10:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-25 17:55:29'),
(7, 'Sunday', 1, '10:00:00', '17:00:00', '2025-11-13 05:40:59', '2025-11-25 17:55:29');

-- --------------------------------------------------------

--
-- Table structure for table `checkouts`
--

CREATE TABLE `checkouts` (
  `checkout_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checkouts`
--

INSERT INTO `checkouts` (`checkout_id`, `customer_id`, `created_at`, `notes`) VALUES
(1, 1, '2025-11-26 23:08:03', '| PayMongo Ref: REF-1764198461-7865'),
(2, 1, '2025-11-26 23:24:50', '| PayMongo Ref: REF-1764199473-6149'),
(3, 1, '2025-11-26 23:25:37', '');

-- --------------------------------------------------------

--
-- Table structure for table `containers`
--

CREATE TABLE `containers` (
  `container_id` int(11) NOT NULL,
  `container_type` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `purchase_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `containers`
--

INSERT INTO `containers` (`container_id`, `container_type`, `price`, `photo`, `is_visible`, `purchase_price`) VALUES
(1, 'Slim Container', 30.00, 'container_692780edf08863.46553914.jpg', 1, 250.00),
(2, 'Round Container', 40.00, 'container_692781011db154.62489142.jpg', 1, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `customer_contact` char(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `street` varchar(150) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `account_id`, `first_name`, `middle_name`, `last_name`, `customer_contact`, `email`, `street`, `barangay`, `city`, `province`, `date_created`) VALUES
(1, 4, 'JUAN MIGUEL', NULL, 'FAUSTINO', '09663085901', 'jfaustino.a12345404@umak.edu.ph', 'MAKATI HOMES 204-B MILKWEED ST. TAGUIG CITY', 'San Miguel', 'Taguig', 'Metro Manila', '2025-11-26 22:33:48');

-- --------------------------------------------------------

--
-- Table structure for table `customer_feedback`
--

CREATE TABLE `customer_feedback` (
  `feedback_id` int(11) NOT NULL,
  `reference_id` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `feedback_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `customer_sales_summary` (
`customer_id` int(11)
,`customer_name` varchar(101)
,`customer_contact` char(11)
,`email` varchar(100)
,`total_orders` bigint(21)
,`lifetime_value` decimal(32,2)
,`average_order_value` decimal(14,6)
,`first_order_date` timestamp
,`last_order_date` timestamp
,`days_since_last_order` int(7)
,`completed_orders` bigint(21)
,`failed_orders` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `cutoff_time_setting`
--

CREATE TABLE `cutoff_time_setting` (
  `id` int(11) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `cutoff_time` time NOT NULL DEFAULT '16:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cutoff_time_setting`
--

INSERT INTO `cutoff_time_setting` (`id`, `is_enabled`, `cutoff_time`, `created_at`, `updated_at`) VALUES
(1, 0, '16:00:00', '2025-11-13 05:48:04', '2025-11-26 08:18:53');

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_sales_summary` (
`sale_date` date
,`day_name` varchar(9)
,`total_orders` bigint(21)
,`completed_orders` bigint(21)
,`failed_orders` bigint(21)
,`cancelled_orders` bigint(21)
,`gross_sales` decimal(32,2)
,`net_sales` decimal(32,2)
,`completed_sales` decimal(32,2)
,`average_order_value` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `delivery_status_id` int(11) DEFAULT NULL,
  `delivery_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  `delivery_type` enum('pickup','delivery') NOT NULL DEFAULT 'delivery',
  `actual_time` timestamp NULL DEFAULT NULL,
  `scheduled_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `batch_id`, `delivery_status_id`, `delivery_date`, `notes`, `delivery_type`, `actual_time`, `scheduled_time`) VALUES
(1, 1, 3, '2025-11-25 16:00:00', 'Auto-created pickup for order 536580', 'pickup', '2025-11-26 23:19:08', '2025-11-25 23:00:00'),
(2, 1, 3, '2025-11-25 16:00:00', 'Auto-created delivery for order 536580', 'delivery', '2025-11-26 23:19:37', '2025-11-26 02:00:00'),
(3, 2, 3, '2025-11-25 16:00:00', 'Auto-created pickup for order 565481', 'pickup', '2025-11-26 23:25:57', '2025-11-25 23:00:00'),
(4, 2, 3, '2025-11-25 16:00:00', 'Auto-created delivery for order 565481', 'delivery', '2025-11-26 23:26:36', '2025-11-26 02:00:00'),
(5, 2, 3, '2025-11-25 16:00:00', 'Auto-created pickup for order 387452', 'pickup', '2025-11-26 23:25:57', '2025-11-25 23:00:00'),
(6, 2, 3, '2025-11-25 16:00:00', 'Auto-created delivery for order 387452', 'delivery', '2025-11-26 23:26:36', '2025-11-26 02:00:00');

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
  `delivery_status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
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
-- Table structure for table `feedback_category`
--

CREATE TABLE `feedback_category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  `container_id` int(11) NOT NULL,
  `container_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`container_id`, `container_type`, `stock`, `last_updated`) VALUES
(1, 'Slim Container', 90, '2025-11-26 22:36:29'),
(2, 'Round Container', 90, '2025-11-26 22:36:49');

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `monthly_sales_summary` (
`sale_year` int(4)
,`sale_month` int(2)
,`month_name` varchar(9)
,`total_orders` bigint(21)
,`completed_orders` bigint(21)
,`gross_sales` decimal(32,2)
,`net_sales` decimal(32,2)
,`completed_sales` decimal(32,2)
,`average_order_value` decimal(14,6)
,`unique_customers` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `reference_id` varchar(32) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `reference_id`, `notification_type`, `is_read`, `created_at`) VALUES
(1, 4, 'Your order #536580 has been placed successfully! Total: ₱30.00', '536580', 'order_placed', 1, '2025-11-26 23:08:03'),
(2, 4, 'Pickup for order #536580 has started and is on its way.', '536580', 'delivery_status', 0, '2025-11-26 23:19:06'),
(3, 4, 'Pickup for order #536580 has been completed successfully.', '536580', 'delivery_status', 0, '2025-11-26 23:19:08'),
(4, 4, 'Delivery for order #536580 has started and is on its way.', '536580', 'delivery_status', 0, '2025-11-26 23:19:19'),
(5, 4, 'Your order with tracking number #536580 has been marked as failed. Please contact support.', '536580', 'order_status', 0, '2025-11-26 23:19:34'),
(6, 4, 'Your order #536580 has been failed', '536580', 'order_status', 0, '2025-11-26 23:19:34'),
(7, 4, 'Delivery for order #536580 has been completed successfully.', '536580', 'delivery_status', 0, '2025-11-26 23:19:37'),
(8, 4, 'Your order #565481 has been placed successfully! Total: ₱40.00', '565481', 'order_placed', 0, '2025-11-26 23:24:50'),
(9, 4, 'Your order #387452 has been placed successfully! Total: ₱40.00', '387452', 'order_placed', 0, '2025-11-26 23:25:37'),
(10, 4, 'Pickup for order #387452 has started and is on its way.', '387452', 'delivery_status', 0, '2025-11-26 23:25:56'),
(12, 4, 'Pickup for order #387452 has been completed successfully.', '387452', 'delivery_status', 0, '2025-11-26 23:25:57'),
(14, 4, 'Delivery for order #387452 has started and is on its way.', '387452', 'delivery_status', 0, '2025-11-26 23:26:15'),
(16, 4, 'Payment for order #387452 has been confirmed and processed successfully.', '387452', 'payment_status', 0, '2025-11-26 23:26:24'),
(17, 4, 'Your order #387452 has been delivered', '387452', 'order_status', 0, '2025-11-26 23:26:24'),
(18, 4, 'Your order #565481 has been delivered', '565481', 'order_status', 0, '2025-11-26 23:26:34'),
(19, 4, 'Delivery for order #387452 has been completed successfully.', '387452', 'delivery_status', 0, '2025-11-26 23:26:36');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `reference_id` varchar(6) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `checkout_id` int(11) DEFAULT NULL,
  `order_type_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_date` date DEFAULT NULL,
  `order_status_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_personnel_name` varchar(255) DEFAULT NULL,
  `payment_collected_amount` decimal(10,2) DEFAULT NULL,
  `failed_reason` text DEFAULT NULL,
  `delivery_completed_at` datetime DEFAULT NULL,
  `delivery_failed_at` datetime DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount applied to order',
  `notes` text DEFAULT NULL COMMENT 'Order notes or special instructions',
  `completed_at` datetime DEFAULT NULL COMMENT 'When order was fully completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`reference_id`, `customer_id`, `checkout_id`, `order_type_id`, `batch_id`, `order_date`, `delivery_date`, `order_status_id`, `total_amount`, `delivery_personnel_name`, `payment_collected_amount`, `failed_reason`, `delivery_completed_at`, `delivery_failed_at`, `discount_amount`, `notes`, `completed_at`) VALUES
('387452', 1, 3, 1, 2, '2025-11-26 23:25:37', '2025-11-26', 3, 40.00, 'Romer Abujen', NULL, NULL, '2025-11-27 07:26:24', NULL, 0.00, NULL, NULL),
('536580', 1, 1, 1, 1, '2025-11-26 23:08:03', '2025-11-26', 3, 30.00, 'Romer Abujen', NULL, 'Customer didn\'t pick up', NULL, '2025-11-27 07:19:34', 0.00, NULL, NULL),
('565481', 1, 2, 1, 2, '2025-11-26 23:24:50', '2025-11-26', 3, 40.00, 'Romer Abujen', NULL, NULL, '2025-11-27 07:26:34', NULL, 0.00, NULL, NULL);

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
  `order_detail_id` int(11) NOT NULL,
  `reference_id` varchar(6) NOT NULL,
  `batch_number` int(11) NOT NULL DEFAULT 1,
  `container_id` int(11) DEFAULT NULL,
  `water_type_id` int(11) DEFAULT NULL,
  `order_type_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_detail_id`, `reference_id`, `batch_number`, `container_id`, `water_type_id`, `order_type_id`, `quantity`, `subtotal`) VALUES
(1, '536580', 1, 1, 2, 1, 1, 30.00),
(2, '565481', 2, 2, 1, 1, 1, 40.00),
(3, '387452', 2, 2, 1, 1, 1, 40.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
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
  `order_type_id` int(11) NOT NULL,
  `type_name` enum('Refill','Purchase New Container/s') NOT NULL
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
  `payment_id` int(11) NOT NULL,
  `reference_id` varchar(6) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `payment_status_id` int(11) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount_paid` decimal(10,2) NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `reference_id`, `payment_method_id`, `payment_status_id`, `payment_date`, `amount_paid`, `transaction_reference`) VALUES
(1, '536580', 2, 1, '2025-11-26 23:08:03', 30.00, NULL),
(2, '565481', 2, 1, '2025-11-26 23:24:50', 40.00, NULL),
(3, '387452', 1, 2, '2025-11-26 23:26:24', 40.00, NULL);

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
  `payment_method_id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`payment_method_id`, `method_name`) VALUES
(1, 'COD'),
(2, 'GCash');

-- --------------------------------------------------------

--
-- Stand-in structure for view `payment_method_sales`
-- (See below for the actual view)
--
CREATE TABLE `payment_method_sales` (
`payment_method_name` varchar(50)
,`total_transactions` bigint(21)
,`total_amount` decimal(32,2)
,`average_transaction` decimal(14,6)
,`successful_payments` bigint(21)
,`failed_payments` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `payment_status`
--

CREATE TABLE `payment_status` (
  `payment_status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
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
-- Stand-in structure for view `product_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `product_sales_summary` (
`water_type_id` int(11)
,`water_type_name` varchar(50)
,`container_type_name` varchar(50)
,`total_orders` bigint(21)
,`total_quantity_sold` decimal(32,0)
,`total_revenue` decimal(32,2)
,`average_price` decimal(14,6)
,`last_sold_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `sales_summary_view`
-- (See below for the actual view)
--
CREATE TABLE `sales_summary_view` (
`reference_id` varchar(6)
,`order_date` timestamp
,`delivery_date` date
,`completed_at` datetime
,`sale_date` date
,`sale_year` int(4)
,`sale_month` int(2)
,`sale_day` int(2)
,`day_name` varchar(9)
,`customer_id` int(11)
,`customer_name` varchar(101)
,`customer_contact` char(11)
,`total_amount` decimal(10,2)
,`net_amount` decimal(10,2)
,`payment_method_name` varchar(50)
,`payment_status_name` varchar(50)
,`payment_date` timestamp
,`amount_paid` decimal(10,2)
,`order_status_name` varchar(50)
,`sale_status` varchar(9)
,`delivery_personnel_name` varchar(255)
,`batch_number` int(11)
,`vehicle_type` enum('Tricycle','Car')
,`items_count` bigint(21)
,`total_quantity` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `k` varchar(100) NOT NULL,
  `v` varchar(255) DEFAULT NULL
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
  `staff_id` int(11) NOT NULL,
  `staff_user` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `staff_password` varchar(255) NOT NULL,
  `staff_role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_user`, `first_name`, `last_name`, `staff_password`, `staff_role`) VALUES
(1, 'kurt', 'Kurt', 'Luzano', '$2y$10$4imOzXe/hBHRjEcqtfT6hOAKaze4y0ZqL6ALEBvRWjef4H1ot/FkC', 'Sales Manager'),
(2, 'romer', 'Romer', 'Abujen', '$2y$10$XJY1UlnyV22wry18nZuskOIypP0U6Hbxe3DEcP3IdQ41ydUPM..pK', 'Driver'),
(3, 'marc', 'Marc', 'Magno', '$2y$10$BTX2BZU.gh9R4UdNVlrBauC0d4DjqbfT8YeU1eg2/EOdgsiUgjyKG', 'Customer Manager');

-- --------------------------------------------------------

--
-- Table structure for table `water_types`
--

CREATE TABLE `water_types` (
  `water_type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_management_view`  AS SELECT `o`.`reference_id` AS `reference_id`, `o`.`order_date` AS `order_date`, `o`.`delivery_date` AS `delivery_date`, `o`.`total_amount` AS `total_amount`, `os`.`status_name` AS `order_status`, `os`.`status_id` AS `order_status_id`, `p`.`payment_status_id` AS `payment_status_id`, `ps`.`status_name` AS `payment_status`, `d`.`delivery_status_id` AS `delivery_status_id`, `ds`.`status_name` AS `delivery_status`, `b`.`batch_id` AS `batch_id`, `b`.`vehicle` AS `vehicle`, `b`.`vehicle_type` AS `vehicle_type`, `bs`.`status_name` AS `batch_status`, concat(`c`.`first_name`,' ',coalesce(`c`.`middle_name`,''),' ',`c`.`last_name`) AS `customer_name`, `c`.`customer_contact` AS `customer_contact`, `c`.`street` AS `street`, `c`.`barangay` AS `barangay`, `c`.`city` AS `city`, `c`.`province` AS `province`, `od`.`quantity` AS `quantity`, `cont`.`container_type` AS `container_type`, `cont`.`price` AS `container_price`, `od`.`subtotal` AS `subtotal` FROM ((((((((((`orders` `o` left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) left join `payments` `p` on(`o`.`reference_id` = `p`.`reference_id`)) left join `payment_status` `ps` on(`p`.`payment_status_id` = `ps`.`payment_status_id`)) left join `batches` `b` on(`o`.`batch_id` = `b`.`batch_id`)) left join `deliveries` `d` on(`b`.`batch_id` = `d`.`batch_id`)) left join `delivery_status` `ds` on(`d`.`delivery_status_id` = `ds`.`delivery_status_id`)) left join `batch_status` `bs` on(`b`.`batch_status_id` = `bs`.`batch_status_id`)) left join `customers` `c` on(`o`.`customer_id` = `c`.`customer_id`)) left join `order_details` `od` on(`o`.`reference_id` = `od`.`reference_id`)) left join `containers` `cont` on(`od`.`container_id` = `cont`.`container_id`)) GROUP BY `o`.`reference_id`, `o`.`order_date`, `o`.`delivery_date`, `o`.`total_amount`, `os`.`status_name`, `os`.`status_id`, `p`.`payment_status_id`, `ps`.`status_name`, `d`.`delivery_status_id`, `ds`.`status_name`, `b`.`batch_id`, `b`.`vehicle`, `b`.`vehicle_type`, `bs`.`status_name`, `c`.`customer_id`, `od`.`order_detail_id`, `cont`.`container_id` ORDER BY `o`.`order_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `customer_sales_summary`
--
DROP TABLE IF EXISTS `customer_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_sales_summary`  AS SELECT `c`.`customer_id` AS `customer_id`, concat(`c`.`first_name`,' ',`c`.`last_name`) AS `customer_name`, `c`.`customer_contact` AS `customer_contact`, `c`.`email` AS `email`, count(distinct `o`.`reference_id`) AS `total_orders`, sum(`o`.`total_amount`) AS `lifetime_value`, avg(`o`.`total_amount`) AS `average_order_value`, min(`o`.`order_date`) AS `first_order_date`, max(`o`.`order_date`) AS `last_order_date`, to_days(current_timestamp()) - to_days(max(`o`.`order_date`)) AS `days_since_last_order`, count(distinct case when `os`.`status_name` = 'Completed' then `o`.`reference_id` end) AS `completed_orders`, count(distinct case when `os`.`status_name` = 'Failed' then `o`.`reference_id` end) AS `failed_orders` FROM ((`customers` `c` left join `orders` `o` on(`c`.`customer_id` = `o`.`customer_id`)) left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) GROUP BY `c`.`customer_id`, `c`.`first_name`, `c`.`last_name`, `c`.`customer_contact`, `c`.`email` ORDER BY sum(`o`.`total_amount`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `daily_sales_summary`
--
DROP TABLE IF EXISTS `daily_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales_summary`  AS SELECT cast(`o`.`order_date` as date) AS `sale_date`, dayname(`o`.`order_date`) AS `day_name`, count(distinct `o`.`reference_id`) AS `total_orders`, count(distinct case when `os`.`status_name` = 'Completed' then `o`.`reference_id` end) AS `completed_orders`, count(distinct case when `os`.`status_name` = 'Failed' then `o`.`reference_id` end) AS `failed_orders`, count(distinct case when `os`.`status_name` = 'Cancelled' then `o`.`reference_id` end) AS `cancelled_orders`, sum(`o`.`total_amount`) AS `gross_sales`, sum(`o`.`total_amount`) AS `net_sales`, sum(case when `os`.`status_name` = 'Completed' then `o`.`total_amount` else 0 end) AS `completed_sales`, avg(`o`.`total_amount`) AS `average_order_value` FROM (`orders` `o` left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) GROUP BY cast(`o`.`order_date` as date) ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_sales_summary`
--
DROP TABLE IF EXISTS `monthly_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_sales_summary`  AS SELECT year(`o`.`order_date`) AS `sale_year`, month(`o`.`order_date`) AS `sale_month`, monthname(`o`.`order_date`) AS `month_name`, count(distinct `o`.`reference_id`) AS `total_orders`, count(distinct case when `os`.`status_name` = 'Completed' then `o`.`reference_id` end) AS `completed_orders`, sum(`o`.`total_amount`) AS `gross_sales`, sum(`o`.`total_amount`) AS `net_sales`, sum(case when `os`.`status_name` = 'Completed' then `o`.`total_amount` else 0 end) AS `completed_sales`, avg(`o`.`total_amount`) AS `average_order_value`, count(distinct `o`.`customer_id`) AS `unique_customers` FROM (`orders` `o` left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) GROUP BY year(`o`.`order_date`), month(`o`.`order_date`), monthname(`o`.`order_date`) ORDER BY year(`o`.`order_date`) DESC, month(`o`.`order_date`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `payment_method_sales`
--
DROP TABLE IF EXISTS `payment_method_sales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `payment_method_sales`  AS SELECT `pm`.`method_name` AS `payment_method_name`, count(distinct `p`.`reference_id`) AS `total_transactions`, sum(`p`.`amount_paid`) AS `total_amount`, avg(`p`.`amount_paid`) AS `average_transaction`, count(distinct case when `ps`.`status_name` = 'Paid' then `p`.`reference_id` end) AS `successful_payments`, count(distinct case when `ps`.`status_name` = 'Failed' then `p`.`reference_id` end) AS `failed_payments` FROM ((`payments` `p` join `payment_methods` `pm` on(`p`.`payment_method_id` = `pm`.`payment_method_id`)) left join `payment_status` `ps` on(`p`.`payment_status_id` = `ps`.`payment_status_id`)) GROUP BY `pm`.`method_name` ORDER BY sum(`p`.`amount_paid`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `product_sales_summary`
--
DROP TABLE IF EXISTS `product_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_sales_summary`  AS SELECT `wt`.`water_type_id` AS `water_type_id`, `wt`.`type_name` AS `water_type_name`, `c`.`container_type` AS `container_type_name`, count(distinct `od`.`reference_id`) AS `total_orders`, sum(`od`.`quantity`) AS `total_quantity_sold`, sum(`od`.`subtotal`) AS `total_revenue`, avg(`c`.`price`) AS `average_price`, cast(max(`o`.`order_date`) as date) AS `last_sold_date` FROM ((((`order_details` `od` join `water_types` `wt` on(`od`.`water_type_id` = `wt`.`water_type_id`)) join `containers` `c` on(`od`.`container_id` = `c`.`container_id`)) join `orders` `o` on(`od`.`reference_id` = `o`.`reference_id`)) join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) WHERE `os`.`status_name` = 'Completed' GROUP BY `wt`.`water_type_id`, `wt`.`type_name`, `c`.`container_type` ORDER BY sum(`od`.`subtotal`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `sales_summary_view`
--
DROP TABLE IF EXISTS `sales_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sales_summary_view`  AS SELECT `o`.`reference_id` AS `reference_id`, `o`.`order_date` AS `order_date`, `o`.`delivery_date` AS `delivery_date`, `o`.`completed_at` AS `completed_at`, cast(`o`.`order_date` as date) AS `sale_date`, year(`o`.`order_date`) AS `sale_year`, month(`o`.`order_date`) AS `sale_month`, dayofmonth(`o`.`order_date`) AS `sale_day`, dayname(`o`.`order_date`) AS `day_name`, `c`.`customer_id` AS `customer_id`, concat(`c`.`first_name`,' ',`c`.`last_name`) AS `customer_name`, `c`.`customer_contact` AS `customer_contact`, `o`.`total_amount` AS `total_amount`, `o`.`total_amount` AS `net_amount`, `pm`.`method_name` AS `payment_method_name`, `ps`.`status_name` AS `payment_status_name`, `p`.`payment_date` AS `payment_date`, `p`.`amount_paid` AS `amount_paid`, `os`.`status_name` AS `order_status_name`, CASE WHEN `os`.`status_name` = 'Completed' THEN 'Completed' WHEN `os`.`status_name` = 'Failed' THEN 'Failed' WHEN `os`.`status_name` = 'Cancelled' THEN 'Cancelled' ELSE 'Pending' END AS `sale_status`, `o`.`delivery_personnel_name` AS `delivery_personnel_name`, `b`.`batch_number` AS `batch_number`, `b`.`vehicle_type` AS `vehicle_type`, (select count(0) from `order_details` `od` where `od`.`reference_id` = `o`.`reference_id`) AS `items_count`, (select sum(`od`.`quantity`) from `order_details` `od` where `od`.`reference_id` = `o`.`reference_id`) AS `total_quantity` FROM ((((((`orders` `o` left join `customers` `c` on(`o`.`customer_id` = `c`.`customer_id`)) left join `payments` `p` on(`o`.`reference_id` = `p`.`reference_id`)) left join `payment_methods` `pm` on(`p`.`payment_method_id` = `pm`.`payment_method_id`)) left join `payment_status` `ps` on(`p`.`payment_status_id` = `ps`.`payment_status_id`)) left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) left join `batches` `b` on(`o`.`batch_id` = `b`.`batch_id`)) ;

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
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `archived_orders`
--
ALTER TABLE `archived_orders`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `archive_log`
--
ALTER TABLE `archive_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `batch_status`
--
ALTER TABLE `batch_status`
  MODIFY `batch_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `checkouts`
--
ALTER TABLE `checkouts`
  MODIFY `checkout_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `containers`
--
ALTER TABLE `containers`
  MODIFY `container_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cutoff_time_setting`
--
ALTER TABLE `cutoff_time_setting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `delivery_status`
--
ALTER TABLE `delivery_status`
  MODIFY `delivery_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback_category`
--
ALTER TABLE `feedback_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_types`
--
ALTER TABLE `order_types`
  MODIFY `order_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `payment_method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_status`
--
ALTER TABLE `payment_status`
  MODIFY `payment_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `water_types`
--
ALTER TABLE `water_types`
  MODIFY `water_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
