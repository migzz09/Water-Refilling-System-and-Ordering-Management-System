-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2025 at 05:51 AM
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
(1, 1, 'user1', '$2y$10$CGQbbPlFZKz5g/3tA8dLP.klKT0B8eaYOi5VygHCbV80psw3XgVoO', '2025-11-03 19:43:20', NULL, '2025-11-03 06:56:31', 1, 0, NULL, NULL, NULL),
(2, 2, 'user2', '$2y$10$0ODklyfOXkVEB3w6wd9rlu/fC0mF7835Hljr7zk8dMkV/.l6.Rygi', '2025-11-03 19:43:20', NULL, '2025-11-03 11:40:04', 1, 0, 'profile_2_1762176603.jpg', NULL, NULL),
(3, NULL, 'admin', 'admin123', NULL, NULL, '2025-11-03 16:55:21', 1, 1, NULL, NULL, NULL);

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
,`container_type` enum('Round','Slim')
,`container_price` decimal(10,2)
,`subtotal` decimal(10,2)
,`assigned_employees` mediumtext
);

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
(1, '2025-11-03 16:00:00', 3, 'Tricycle #112', 'Auto-created batch', 'Tricycle', 1, NULL, NULL),
(2, '2025-11-03 16:00:00', 3, 'Car #188', 'Auto-created batch', 'Car', 1, NULL, NULL),
(3, '2025-11-05 16:00:00', 3, 'Car #289', 'Auto-created batch', 'Car', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `batch_employees`
--

CREATE TABLE `batch_employees` (
  `batch_employee_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'Dispatched'),
(3, 'Failed'),
(1, 'Pending');

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
(1, 1, '2025-11-03 08:06:35', ''),
(2, 1, '2025-11-03 08:35:42', ''),
(3, 2, '2025-11-03 09:23:52', ''),
(4, 2, '2025-11-03 15:03:20', ''),
(5, 2, '2025-11-03 15:06:51', ''),
(6, 2, '2025-11-05 04:09:11', ''),
(7, 2, '2025-11-05 04:09:48', '');

-- --------------------------------------------------------

--
-- Table structure for table `containers`
--

CREATE TABLE `containers` (
  `container_id` int(11) NOT NULL,
  `container_type` enum('Round','Slim') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `containers`
--

INSERT INTO `containers` (`container_id`, `container_type`, `price`) VALUES
(1, 'Round', 40.00),
(2, 'Slim', 30.00);

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
(1, NULL, 'user1', NULL, 'user1', '09663085901', 'migzzuwu@gmail.com', 'Milkweed', 'Rizal', 'Taguig', 'Metro Manila', '2025-11-03 06:56:15'),
(2, NULL, 'user2', NULL, 'user2', '09663085905', 'jfaustino.a12345404@umak.edu.ph', 'Milkweed', 'Paco', 'Manila', 'Metro Manila', '2025-11-03 09:22:23');

-- --------------------------------------------------------

--
-- Table structure for table `customer_feedback`
--

CREATE TABLE `customer_feedback` (
  `feedback_id` int(11) NOT NULL,
  `reference_id` varchar(6) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_text` text DEFAULT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_feedback`
--

INSERT INTO `customer_feedback` (`feedback_id`, `reference_id`, `customer_id`, `rating`, `feedback_text`, `feedback_date`) VALUES
(2, NULL, 2, 3, 'Category: website\nSubject: hu\n\nhello', '2025-11-03 16:22:38');

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
(1, 1, 3, '2025-11-03 16:00:00', 'Auto-created pickup for order 895189', 'pickup', '2025-11-03 08:35:54', '2025-11-03 23:00:00'),
(2, 1, 3, '2025-11-03 16:00:00', 'Auto-created delivery for order 895189', 'delivery', '2025-11-03 08:36:00', '2025-11-04 02:00:00'),
(3, 1, 3, '2025-11-03 16:00:00', 'Auto-created pickup for order 949866', 'pickup', '2025-11-03 08:35:54', '2025-11-03 23:00:00'),
(4, 1, 3, '2025-11-03 16:00:00', 'Auto-created delivery for order 949866', 'delivery', '2025-11-03 08:36:00', '2025-11-04 02:00:00'),
(5, 2, 3, '2025-11-03 16:00:00', 'Auto-created pickup for order 440479', 'pickup', '2025-11-03 09:25:56', '2025-11-03 23:00:00'),
(6, 2, 3, '2025-11-03 16:00:00', 'Auto-created delivery for order 440479', 'delivery', '2025-11-03 09:26:13', '2025-11-04 02:00:00'),
(7, 2, 1, '2025-11-03 16:00:00', 'Auto-created pickup for order 525768', 'pickup', NULL, '2025-11-03 23:00:00'),
(8, 2, 1, '2025-11-03 16:00:00', 'Auto-created delivery for order 525768', 'delivery', NULL, '2025-11-04 02:00:00'),
(9, 2, 1, '2025-11-03 16:00:00', 'Auto-created pickup for order 364409', 'pickup', NULL, '2025-11-03 23:00:00'),
(10, 2, 1, '2025-11-03 16:00:00', 'Auto-created delivery for order 364409', 'delivery', NULL, '2025-11-04 02:00:00'),
(11, 3, 3, '2025-11-05 16:00:00', 'Auto-created pickup for order 376012', 'pickup', '2025-11-05 04:30:31', '2025-11-05 23:00:00'),
(12, 3, 3, '2025-11-05 16:00:00', 'Auto-created delivery for order 376012', 'delivery', '2025-11-05 04:30:37', '2025-11-06 02:00:00'),
(13, 3, 3, '2025-11-05 16:00:00', 'Auto-created pickup for order 949158', 'pickup', '2025-11-05 04:30:31', '2025-11-05 23:00:00'),
(14, 3, 3, '2025-11-05 16:00:00', 'Auto-created delivery for order 949158', 'delivery', '2025-11-05 04:30:37', '2025-11-06 02:00:00');

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
(3, 'Delivered'),
(2, 'Dispatched'),
(4, 'Failed'),
(1, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `employee_contact` char(11) NOT NULL,
  `role` varchar(50) DEFAULT NULL CHECK (`role` in ('Driver','Assistant',NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `container_id` int(11) NOT NULL,
  `container_type` varchar(50) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0 CHECK (`stock` >= 0),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`container_id`, `container_type`, `stock`, `last_updated`) VALUES
(1, 'Round', 90, '2025-11-05 04:18:54'),
(2, 'Slim', 90, '2025-11-05 04:18:59');

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
  `total_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`reference_id`, `customer_id`, `checkout_id`, `order_type_id`, `batch_id`, `order_date`, `delivery_date`, `order_status_id`, `total_amount`) VALUES
('364409', 2, 5, 1, 2, '2025-11-03 15:06:51', '2025-11-04', 1, 70.00),
('376012', 2, 6, 1, 3, '2025-11-05 04:09:11', '2025-11-06', 3, 250.00),
('440479', 2, 3, 1, 2, '2025-11-03 09:23:52', '2025-11-04', 3, 200.00),
('525768', 2, 4, 1, 2, '2025-11-03 15:03:20', '2025-11-04', 1, 70.00),
('895189', 1, 1, 1, 1, '2025-11-03 08:06:35', '2025-11-04', 3, 40.00),
('949158', 2, 7, 1, 3, '2025-11-05 04:09:48', '2025-11-06', 3, 570.00),
('949866', 1, 2, 1, 1, '2025-11-03 08:35:42', '2025-11-04', 3, 80.00);

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
(1, '895189', 1, 1, 2, 1, 1, 40.00),
(2, '949866', 1, 1, 2, 1, 2, 80.00),
(3, '440479', 1, 1, 1, 1, 5, 200.00),
(4, '525768', 1, 1, 1, 1, 1, 40.00),
(5, '525768', 1, 2, 3, 1, 1, 30.00),
(6, '364409', 1, 1, 1, 1, 1, 40.00),
(7, '364409', 1, 2, 3, 1, 1, 30.00),
(8, '376012', 1, 1, 2, 2, 1, 250.00),
(9, '949158', 1, 1, 3, 1, 1, 40.00),
(10, '949158', 1, 1, 1, 2, 1, 250.00),
(11, '949158', 1, 2, 3, 2, 1, 250.00),
(12, '949158', 1, 2, 1, 1, 1, 30.00);

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
(3, 'Delivered'),
(2, 'Dispatched'),
(4, 'Failed'),
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
(1, '895189', 1, 1, '2025-11-03 08:06:35', 40.00, NULL),
(2, '949866', 1, 1, '2025-11-03 08:35:42', 80.00, NULL),
(3, '440479', 1, 1, '2025-11-03 09:23:52', 200.00, NULL),
(4, '525768', 1, 1, '2025-11-03 15:03:20', 70.00, NULL),
(5, '364409', 1, 1, '2025-11-03 15:06:51', 70.00, NULL),
(6, '376012', 1, 1, '2025-11-05 04:09:11', 250.00, NULL),
(7, '949158', 1, 1, '2025-11-05 04:09:48', 570.00, NULL);

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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_management_view`  AS SELECT `o`.`reference_id` AS `reference_id`, `o`.`order_date` AS `order_date`, `o`.`delivery_date` AS `delivery_date`, `o`.`total_amount` AS `total_amount`, `os`.`status_name` AS `order_status`, `os`.`status_id` AS `order_status_id`, `p`.`payment_status_id` AS `payment_status_id`, `ps`.`status_name` AS `payment_status`, `d`.`delivery_status_id` AS `delivery_status_id`, `ds`.`status_name` AS `delivery_status`, `b`.`batch_id` AS `batch_id`, `b`.`vehicle` AS `vehicle`, `b`.`vehicle_type` AS `vehicle_type`, `bs`.`status_name` AS `batch_status`, concat(`c`.`first_name`,' ',coalesce(`c`.`middle_name`,''),' ',`c`.`last_name`) AS `customer_name`, `c`.`customer_contact` AS `customer_contact`, `c`.`street` AS `street`, `c`.`barangay` AS `barangay`, `c`.`city` AS `city`, `c`.`province` AS `province`, `od`.`quantity` AS `quantity`, `cont`.`container_type` AS `container_type`, `cont`.`price` AS `container_price`, `od`.`subtotal` AS `subtotal`, group_concat(concat(`e`.`first_name`,' ',`e`.`last_name`,' (',coalesce(`e`.`role`,'N/A'),')') separator ', ') AS `assigned_employees` FROM ((((((((((((`orders` `o` left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) left join `payments` `p` on(`o`.`reference_id` = `p`.`reference_id`)) left join `payment_status` `ps` on(`p`.`payment_status_id` = `ps`.`payment_status_id`)) left join `batches` `b` on(`o`.`batch_id` = `b`.`batch_id`)) left join `deliveries` `d` on(`b`.`batch_id` = `d`.`batch_id`)) left join `delivery_status` `ds` on(`d`.`delivery_status_id` = `ds`.`delivery_status_id`)) left join `batch_status` `bs` on(`b`.`batch_status_id` = `bs`.`batch_status_id`)) left join `customers` `c` on(`o`.`customer_id` = `c`.`customer_id`)) left join `order_details` `od` on(`o`.`reference_id` = `od`.`reference_id`)) left join `containers` `cont` on(`od`.`container_id` = `cont`.`container_id`)) left join `batch_employees` `be` on(`b`.`batch_id` = `be`.`batch_id`)) left join `employees` `e` on(`be`.`employee_id` = `e`.`employee_id`)) GROUP BY `o`.`reference_id`, `o`.`order_date`, `o`.`delivery_date`, `o`.`total_amount`, `os`.`status_name`, `os`.`status_id`, `p`.`payment_status_id`, `ps`.`status_name`, `d`.`delivery_status_id`, `ds`.`status_name`, `b`.`batch_id`, `b`.`vehicle`, `b`.`vehicle_type`, `bs`.`status_name`, `c`.`customer_id`, `od`.`order_detail_id`, `cont`.`container_id` ORDER BY `o`.`order_date` DESC ;

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
  ADD KEY `customer_id` (`customer_id`);

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
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`container_id`);

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
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batch_employees`
--
ALTER TABLE `batch_employees`
  MODIFY `batch_employee_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_status`
--
ALTER TABLE `batch_status`
  MODIFY `batch_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `checkouts`
--
ALTER TABLE `checkouts`
  MODIFY `checkout_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `containers`
--
ALTER TABLE `containers`
  MODIFY `container_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `delivery_status`
--
ALTER TABLE `delivery_status`
  MODIFY `delivery_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- AUTO_INCREMENT for table `water_types`
--
ALTER TABLE `water_types`
  MODIFY `water_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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

--
-- Constraints for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD CONSTRAINT `customer_feedback_ibfk_1` FOREIGN KEY (`reference_id`) REFERENCES `orders` (`reference_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`delivery_status_id`) REFERENCES `delivery_status` (`delivery_status_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`container_id`) REFERENCES `containers` (`container_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`order_type_id`) REFERENCES `order_types` (`order_type_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`order_status_id`) REFERENCES `order_status` (`status_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_checkout` FOREIGN KEY (`checkout_id`) REFERENCES `checkouts` (`checkout_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`reference_id`) REFERENCES `orders` (`reference_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`container_id`) REFERENCES `containers` (`container_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_3` FOREIGN KEY (`water_type_id`) REFERENCES `water_types` (`water_type_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_details_ibfk_4` FOREIGN KEY (`order_type_id`) REFERENCES `order_types` (`order_type_id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`reference_id`) REFERENCES `orders` (`reference_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`payment_method_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`payment_status_id`) REFERENCES `payment_status` (`payment_status_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
