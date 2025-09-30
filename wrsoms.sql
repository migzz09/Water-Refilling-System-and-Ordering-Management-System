-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2025 at 06:13 PM
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

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`batch_id`, `batch_date`, `batch_status_id`, `vehicle`, `notes`, `vehicle_type`) VALUES
(1, '2025-09-30 09:27:56', 2, 'Tricycle #717', 'Auto-created batch for 2025-09-30', 'Tricycle'),
(2, '2025-09-30 09:29:59', 1, 'Tricycle #935', 'Auto-created batch for 2025-09-30', 'Tricycle'),
(3, '2025-09-30 09:30:55', 1, 'Tricycle #888', 'Auto-created batch for 2025-09-30', 'Tricycle'),
(4, '2025-09-30 09:32:13', 1, 'Car #676', 'Auto-created batch for 2025-09-30', 'Car'),
(5, '2025-09-30 11:07:13', 1, 'Car #511', 'Auto-created batch for 2025-09-30', 'Car');

--
-- Dumping data for table `batch_status`
--

INSERT INTO `batch_status` (`batch_status_id`, `status_name`) VALUES
(3, 'Delivered'),
(2, 'Dispatched'),
(4, 'Failed'),
(1, 'Pending');

--
-- Dumping data for table `containers`
--

INSERT INTO `containers` (`container_id`, `container_type`, `price`) VALUES
(1, 'Round', 40.00),
(2, 'Rectangular', 30.00),
(3, 'Round', 60.00);

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `first_name`, `middle_name`, `last_name`, `customer_contact`, `street`, `barangay`, `city`, `province`, `date_created`) VALUES
(20, 'rere', 'rerere', 'ererer', '09274665124', 'street', 'Ligid-Tipas', 'Taguig', 'Metro Manila', '2025-09-30 09:27:56'),
(21, 'ikaw', 'si', 'customer', '11111111111', 'pp', 'San Miguel', 'Taguig', 'Metro Manila', '2025-09-30 09:29:59'),
(22, 'test', 'test', 'test', '22222222222', 'EQWE', 'San Miguel', 'Taguig', 'Metro Manila', '2025-09-30 09:30:55'),
(23, 'hgfh', 'VBN', 'EW', '09274772514', 'EQWE', 'Commonwealth', 'Quezon City', 'Metro Manila', '2025-09-30 09:32:12'),
(24, 'dasmdasd', 'fgdgd', 'gfdgfdg', '09499837463', 'EQWE', 'Batasan Hills', 'Quezon City', 'Metro Manila', '2025-09-30 11:05:35'),
(25, 'asdfsadfa', 'EQWE', 'asdf', '09274665120', 'EQWE', 'Bagong Pag-asa', 'Quezon City', 'Metro Manila', '2025-09-30 11:07:13'),
(26, 'gfdg', 'dfggdfg', 'EW', '09877665643', 'EQWE', 'Pinagsama', 'Taguig', 'Metro Manila', '2025-09-30 11:09:42'),
(27, 'EQWE', 'dfsf', 'faustino', '09274772515', 'EQWE', 'Bagong Pag-asa', 'Quezon City', 'Metro Manila', '2025-09-30 11:10:56'),
(28, 'elison', 'bom', 'bonita', '09274772522', 'EQWE', 'North Signal Village', 'Taguig', 'Metro Manila', '2025-09-30 11:17:06'),
(29, 'dasdasdasda', 'asdasdasdsa', 'asdasdasd', '09274665125', 'ghgf', 'Binondo', 'Manila', 'Metro Manila', '2025-09-30 11:24:08'),
(30, 'hgfh', 'wasdaw', 'faustino', '09274665177', 'EQWE', 'Calzada', 'Taguig', 'Metro Manila', '2025-09-30 11:29:10'),
(31, 'retuoiertu', 'ertert', 'terter', '09274665333', 'pp', 'Pinagsama', 'Taguig', 'Metro Manila', '2025-09-30 11:42:11');

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `batch_id`, `delivery_status_id`, `delivery_date`, `notes`) VALUES
(3, 2, 1, '2025-09-29 16:00:00', 'Delivery for Tricycle batch #Tricycle #222 on 2025-09-30'),
(4, 1, 4, '2025-09-30 11:36:28', 'Auto-created for order update');

--
-- Dumping data for table `delivery_status`
--

INSERT INTO `delivery_status` (`delivery_status_id`, `status_name`) VALUES
(3, 'Delivered'),
(2, 'Dispatched'),
(4, 'Failed'),
(1, 'Pending');

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `middle_name`, `last_name`, `employee_contact`, `role`) VALUES
(1, 'Kurt Christian', NULL, 'Luzano', '09171112222', 'Driver'),
(2, 'Justine Ace Nino', NULL, 'San Jose', '09173334444', 'Assistant'),
(3, 'Romer John', NULL, 'Abujen', '000009', 'Driver'),
(4, 'Jehiel', NULL, 'Atole', '000000', 'Assistant'),
(5, 'sean martin', 'aban', 'pante', '09867687654', NULL);

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`reference_id`, `customer_id`, `order_type_id`, `batch_id`, `order_date`, `delivery_date`, `order_status_id`, `total_amount`) VALUES
('050715', 21, 3, 2, '2025-09-30 09:29:59', '2025-09-30', 1, 30.00),
('14159', 20, 1, 1, '2025-09-30 09:29:23', '2025-09-30', 1, 30.00),
('162531', 26, 1, 2, '2025-09-30 11:09:42', '2025-09-30', 1, 40.00),
('162679', 31, 1, 1, '2025-09-30 11:42:11', '2025-09-30', 2, 40.00),
('274617', 22, 2, 3, '2025-09-30 09:30:55', '2025-09-30', 1, 150.00),
('28853', 24, 1, 4, '2025-09-30 11:05:35', '2025-09-30', 1, 40.00),
('300326', 25, 1, 1, '2025-09-30 11:27:45', '2025-09-30', 1, 40.00),
('321150', 20, 2, 1, '2025-09-30 09:27:56', '2025-09-30', 1, 160.00),
('351900', 27, 1, 5, '2025-09-30 11:10:57', '2025-09-30', 1, 80.00),
('629270', 30, 1, 1, '2025-09-30 11:29:10', '2025-09-30', 1, 160.00),
('682023', 23, 2, 4, '2025-09-30 09:32:13', '2025-09-30', 1, 320.00),
('72275', 28, 1, 2, '2025-09-30 11:17:06', '2025-09-30', 1, 40.00),
('723807', 25, 2, 5, '2025-09-30 11:07:13', '2025-09-30', 1, 120.00),
('74970', 29, 2, 5, '2025-09-30 11:24:08', '2025-09-30', 1, 90.00);

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_detail_id`, `reference_id`, `container_id`, `quantity`, `subtotal`) VALUES
(31, '321150', 1, 4, 160.00),
(32, '14159', 2, 1, 30.00),
(33, '050715', 2, 1, 30.00),
(34, '274617', 2, 5, 150.00),
(35, '682023', 1, 8, 320.00),
(36, '28853', 1, 1, 40.00),
(37, '723807', 1, 3, 120.00),
(38, '162531', 1, 1, 40.00),
(39, '351900', 1, 2, 80.00),
(40, '72275', 1, 1, 40.00),
(41, '74970', 2, 3, 90.00),
(42, '300326', 1, 1, 40.00),
(43, '629270', 1, 4, 160.00),
(44, '162679', 1, 1, 40.00);

--
-- Dumping data for table `order_status`
--

INSERT INTO `order_status` (`status_id`, `status_name`) VALUES
(3, 'Delivered'),
(2, 'Dispatched'),
(4, 'Failed'),
(1, 'Pending');

--
-- Dumping data for table `order_types`
--

INSERT INTO `order_types` (`order_type_id`, `type_name`) VALUES
(1, 'Refill'),
(2, 'Purchase New Container/s'),
(3, 'Both');

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `reference_id`, `payment_method_id`, `payment_status_id`, `payment_date`, `amount_paid`, `transaction_reference`) VALUES
(4, '629270', 1, 3, '2025-09-30 11:43:53', 160.00, NULL),
(5, '162679', 1, 2, '2025-09-30 15:53:50', 40.00, NULL);

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`payment_method_id`, `method_name`) VALUES
(1, 'COD'),
(2, 'GCash');

--
-- Dumping data for table `payment_status`
--

INSERT INTO `payment_status` (`payment_status_id`, `status_name`) VALUES
(3, 'Failed'),
(2, 'Paid'),
(1, 'Pending');

-- --------------------------------------------------------

--
-- Structure for view `admin_management_view`
--
DROP TABLE IF EXISTS `admin_management_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_management_view`  AS SELECT `o`.`reference_id` AS `reference_id`, `o`.`order_date` AS `order_date`, `o`.`delivery_date` AS `delivery_date`, `o`.`total_amount` AS `total_amount`, `os`.`status_name` AS `order_status`, `os`.`status_id` AS `order_status_id`, `p`.`payment_status_id` AS `payment_status_id`, `ps`.`status_name` AS `payment_status`, `d`.`delivery_status_id` AS `delivery_status_id`, `ds`.`status_name` AS `delivery_status`, `b`.`batch_id` AS `batch_id`, `b`.`vehicle` AS `vehicle`, `b`.`vehicle_type` AS `vehicle_type`, `bs`.`status_name` AS `batch_status`, concat(`c`.`first_name`,' ',coalesce(`c`.`middle_name`,''),' ',`c`.`last_name`) AS `customer_name`, `c`.`customer_contact` AS `customer_contact`, `c`.`street` AS `street`, `c`.`barangay` AS `barangay`, `c`.`city` AS `city`, `c`.`province` AS `province`, `od`.`quantity` AS `quantity`, `cont`.`container_type` AS `container_type`, `cont`.`price` AS `container_price`, `od`.`subtotal` AS `subtotal`, group_concat(concat(`e`.`first_name`,' ',`e`.`last_name`,' (',coalesce(`e`.`role`,'N/A'),')') separator ', ') AS `assigned_employees` FROM ((((((((((((`orders` `o` left join `order_status` `os` on(`o`.`order_status_id` = `os`.`status_id`)) left join `payments` `p` on(`o`.`reference_id` = `p`.`reference_id`)) left join `payment_status` `ps` on(`p`.`payment_status_id` = `ps`.`payment_status_id`)) left join `batches` `b` on(`o`.`batch_id` = `b`.`batch_id`)) left join `deliveries` `d` on(`b`.`batch_id` = `d`.`batch_id`)) left join `delivery_status` `ds` on(`d`.`delivery_status_id` = `ds`.`delivery_status_id`)) left join `batch_status` `bs` on(`b`.`batch_status_id` = `bs`.`batch_status_id`)) left join `customers` `c` on(`o`.`customer_id` = `c`.`customer_id`)) left join `order_details` `od` on(`o`.`reference_id` = `od`.`reference_id`)) left join `containers` `cont` on(`od`.`container_id` = `cont`.`container_id`)) left join `batch_employees` `be` on(`b`.`batch_id` = `be`.`batch_id`)) left join `employees` `e` on(`be`.`employee_id` = `e`.`employee_id`)) GROUP BY `o`.`reference_id`, `o`.`order_date`, `o`.`delivery_date`, `o`.`total_amount`, `os`.`status_name`, `os`.`status_id`, `p`.`payment_status_id`, `ps`.`status_name`, `d`.`delivery_status_id`, `ds`.`status_name`, `b`.`batch_id`, `b`.`vehicle`, `b`.`vehicle_type`, `bs`.`status_name`, `c`.`customer_id`, `od`.`order_detail_id`, `cont`.`container_id` ORDER BY `o`.`order_date` DESC ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
