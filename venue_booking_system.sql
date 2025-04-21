-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 03, 2025 at 12:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `venue_booking_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `BookingID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `VenueID` int(11) DEFAULT NULL,
  `SlotID` int(11) DEFAULT NULL,
  `BookingDate` date NOT NULL,
  `Duration` int(11) NOT NULL,
  `Status` enum('Pending','Confirmed','Canceled') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`BookingID`, `UserID`, `VenueID`, `SlotID`, `BookingDate`, `Duration`, `Status`) VALUES
(5, 9, 2, 244, '2025-03-30', 1, 'Pending'),
(6, 9, 2, 581, '2025-03-30', 1, 'Confirmed'),
(7, 5, 2, 472, '2025-03-30', 1, 'Confirmed'),
(8, 5, 2, 249, '2025-03-30', 1, 'Canceled'),
(9, 5, 1, 30, '2025-03-30', 1, 'Confirmed'),
(10, 5, 1, 31, '2025-03-30', 1, 'Confirmed'),
(11, 9, 1, 94, '2025-04-03', 1, 'Pending'),
(12, 9, 1, 95, '2025-04-03', 1, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `Date` datetime DEFAULT current_timestamp(),
  `NotificationTime` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsRead` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`NotificationID`, `UserID`, `Title`, `Message`, `Date`, `NotificationTime`, `IsRead`, `CreatedAt`) VALUES
(1, 2, '', 'Таны хүсэлт амжилттай илгээгдсэн.', '2024-11-30 16:20:36', '2024-11-30 01:20:36', 1, '2024-11-30 08:20:36'),
(2, 2, 'Хамтрах хүсэлт батлагдсан', 'Таны хамтрах хүсэлт зөвшөөрөгдлөө. Та менежерийн эрхтэй боллоо.', '2024-11-30 16:27:49', '2024-11-30 08:27:49', 0, '2024-11-30 08:27:49'),
(3, 2, 'Танхим нэмэх хүсэлт батлагдсан', 'Таны танхим нэмэх хүсэлт зөвшөөрөгдлөө.', '2024-11-30 16:30:45', '2024-11-30 08:30:45', 0, '2024-11-30 08:30:45'),
(4, 2, 'Танхим нэмэх хүсэлт батлагдсан', 'Таны танхим нэмэх хүсэлт зөвшөөрөгдлөө.', '2024-11-30 16:36:03', '2024-11-30 08:36:03', 0, '2024-11-30 08:36:03'),
(5, 2, 'Танхим нэмэх хүсэлт батлагдсан', 'Таны танхим нэмэх хүсэлт зөвшөөрөгдлөө.', '2024-11-30 16:38:05', '2024-11-30 08:38:05', 0, '2024-11-30 08:38:05'),
(6, 9, '', 'Таны хүсэлт амжилттай илгээгдсэн.', '2025-04-02 03:39:12', '2025-04-01 13:39:12', 0, '2025-04-01 19:39:12'),
(7, 9, '', 'Таны хүсэлт амжилттай илгээгдсэн.', '2025-04-02 04:35:58', '2025-04-01 14:35:58', 0, '2025-04-01 20:35:58'),
(8, 9, '', 'Таны хүсэлт амжилттай устгагдлаа.', '2025-04-02 04:36:38', '2025-04-01 20:36:38', 0, '2025-04-01 20:36:38'),
(9, 9, '', 'Таны хүсэлт амжилттай устгагдлаа.', '2025-04-02 04:36:39', '2025-04-01 20:36:39', 0, '2025-04-01 20:36:39'),
(10, 9, 'Захиалга #11', 'Таны a1 танхимын захиалга Pending төлөвтэй байна. \r\n            Захиалгын дэлгэрэнгүй: Thursday, 21:00:00-22:00:00, \r\n            Үнэ: 50,000 ₮', '2025-04-03 18:05:34', '2025-04-03 10:05:34', 0, '2025-04-03 10:05:34'),
(11, 9, 'Захиалга #12', 'Таны a1 танхимын захиалга Pending төлөвтэй байна. \r\n            Захиалгын дэлгэрэнгүй: Thursday, 22:00:00-23:00:00, \r\n            Үнэ: 50,000 ₮', '2025-04-03 18:05:34', '2025-04-03 10:05:34', 0, '2025-04-03 10:05:34');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `PaymentID` int(11) NOT NULL,
  `BookingID` int(11) DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `PaymentDate` date NOT NULL,
  `Status` enum('Pending','Paid','Refunded','Partially Refunded','Failed','Canceled') NOT NULL DEFAULT 'Pending',
  `BankName` enum('Khan Bank','State Bank','Golomt Bank','TDB Bank','Xac Bank','Capitron Bank','National Investment Bank') NOT NULL,
  `AccountNumber` varchar(50) DEFAULT NULL,
  `TransactionID` varchar(100) DEFAULT NULL,
  `RefundAmount` decimal(10,2) DEFAULT 0.00,
  `RefundDate` datetime DEFAULT NULL,
  `RefundReason` text DEFAULT NULL,
  `AdminNotes` text DEFAULT NULL,
  `LastUpdated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`PaymentID`, `BookingID`, `Amount`, `PaymentDate`, `Status`, `BankName`, `AccountNumber`, `TransactionID`, `RefundAmount`, `RefundDate`, `RefundReason`, `AdminNotes`, `LastUpdated`) VALUES
(1, 11, 50000.00, '2025-04-03', 'Pending', 'Khan Bank', NULL, NULL, 0.00, NULL, NULL, NULL, '2025-04-03 18:05:34'),
(2, 12, 50000.00, '2025-04-03', 'Pending', 'Khan Bank', NULL, NULL, 0.00, NULL, NULL, NULL, '2025-04-03 18:05:34');

-- --------------------------------------------------------

--
-- Table structure for table `promotionrequest`
--

CREATE TABLE `promotionrequest` (
  `RequestID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `PhoneNumber` varchar(20) NOT NULL,
  `PhoneNumber2` varchar(20) DEFAULT NULL,
  `VenueName` varchar(100) NOT NULL,
  `VenueLocation` varchar(255) NOT NULL,
  `VenuePrice` decimal(10,2) NOT NULL,
  `TimeSlots` text NOT NULL,
  `Description` text DEFAULT NULL,
  `Images` text DEFAULT NULL,
  `RequestStatus` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `RequestDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promotionrequest`
--

INSERT INTO `promotionrequest` (`RequestID`, `UserID`, `Name`, `Email`, `PhoneNumber`, `PhoneNumber2`, `VenueName`, `VenueLocation`, `VenuePrice`, `TimeSlots`, `Description`, `Images`, `RequestStatus`, `RequestDate`, `CreatedAt`) VALUES
(1, 2, 'adf', 'e@gmail.com', '13245678', '25786', 'gtret', 're', 123465.00, '1245', 'ter', '', 'Approved', '2024-11-30 08:20:36', '2025-04-01 19:44:16'),
(2, 9, 'fas', 'user@example.com', '89988989', '89898989', 'jfg', 'jfghj', 4000.00, 'jyj', 'rgsdf', 'uploads/img_67ec40e006eb96.40728447.png', 'Pending', '2025-04-01 19:39:12', '2025-04-01 19:44:16');

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `RoleID` int(11) NOT NULL,
  `RoleName` enum('SuperAdmin','Admin','Manager','VenueStaff','Accountant','ModeratorCustomerSupport','User') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`RoleID`, `RoleName`) VALUES
(1, 'SuperAdmin'),
(2, 'Admin'),
(3, 'Manager'),
(4, 'User'),
(5, 'VenueStaff'),
(6, 'Accountant');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `UserID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `DateOfBirth` date NOT NULL,
  `RoleID` int(11) DEFAULT NULL,
  `Status` enum('Active','Banned') DEFAULT 'Active',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ManagerID` int(11) DEFAULT NULL,
  `VenueID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`UserID`, `Name`, `Phone`, `Email`, `Password`, `DateOfBirth`, `RoleID`, `Status`, `CreatedAt`, `ManagerID`, `VenueID`) VALUES
(5, 'aagii', '88998899', 'altangerel78427@gmail.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '2004-10-21', 4, 'Active', '2025-03-29 11:25:27', NULL, NULL),
(6, 'Super Admin', '', 'superadmin@example.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '0000-00-00', 1, 'Active', '2025-03-29 12:03:56', NULL, NULL),
(7, 'Admin User', '', 'admin@example.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '0000-00-00', 2, 'Active', '2025-03-29 12:03:56', NULL, NULL),
(8, 'Manager User', '', 'manager@example.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '0000-00-00', 3, 'Active', '2025-03-29 12:03:56', NULL, NULL),
(9, 'Regular User', '88998899', 'user@example.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '0000-00-00', 4, 'Active', '2025-03-29 12:03:56', NULL, NULL),
(10, 'Venue Staff', '', 'venuestaff@example.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '0000-00-00', 5, 'Active', '2025-03-29 12:03:56', NULL, NULL),
(11, 'Accountant User', '', 'accountant@example.com', '$2y$10$KYAYT7oC3zjyLBVW7tjS3uJsJ.VjiKD3lshI1JPTi5MBHuFgnU/L2', '0000-00-00', 6, 'Active', '2025-03-29 12:03:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `venue`
--

CREATE TABLE `venue` (
  `VenueID` int(11) NOT NULL,
  `ManagerID` int(11) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Location` varchar(255) NOT NULL,
  `SportType` enum('Хөлбөмбөг','Сагсанбөмбөг','Волейбол','Ширээний теннис','Бадминтон','Талбайн теннис','Гольф','Бүжиг','Иога','Билльярд') NOT NULL,
  `HourlyPrice` decimal(10,2) NOT NULL,
  `Description` text DEFAULT NULL,
  `MapLocation` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venue`
--

INSERT INTO `venue` (`VenueID`, `ManagerID`, `Name`, `Location`, `SportType`, `HourlyPrice`, `Description`, `MapLocation`) VALUES
(1, 2, 'a1', 'b', 'Сагсанбөмбөг', 50000.00, 'safd', 'adsf'),
(2, 8, 'sport zaal ordon 1', 'ub mongolia', '', 6005.00, 'bla bla bala', ''),
(3, 8, 'zaal 2', 'fas', 'Хөлбөмбөг', 2200.00, 'adsf', 'asdf'),
(4, 8, 'test 3', 'adf', '', 122.00, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `venueimages`
--

CREATE TABLE `venueimages` (
  `ImageID` int(11) NOT NULL,
  `VenueID` int(11) DEFAULT NULL,
  `ImagePath` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venueimages`
--

INSERT INTO `venueimages` (`ImageID`, `VenueID`, `ImagePath`) VALUES
(4, 1, 'uploads/venue_images/1.jpg'),
(5, 1, 'uploads/venue_images/2.jpg'),
(6, 1, 'uploads/venue_images/3.jpg'),
(7, 1, 'uploads/venue_images/4.jpg'),
(8, 2, 'uploads/venue_images/Arena-Jaragua-2022.jpg'),
(11, 3, 'uploads/venue_images/3_67ea6014d1895.jpg'),
(12, 4, 'uploads/venue_images/4_67ea601ca5509.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `venuesports`
--

CREATE TABLE `venuesports` (
  `ID` int(11) NOT NULL,
  `VenueID` int(11) DEFAULT NULL,
  `SportType` enum('Хөлбөмбөг','Сагсанбөмбөг','Волейбол','Ширээний теннис','Бадминтон','Талбайн теннис','Гольф','Бүжиг','Иога','Билльярд') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venuesports`
--

INSERT INTO `venuesports` (`ID`, `VenueID`, `SportType`) VALUES
(1, 1, 'Сагсанбөмбөг'),
(2, 1, 'Волейбол'),
(3, 1, 'Талбайн теннис'),
(36, 2, 'Хөлбөмбөг'),
(37, 2, 'Сагсанбөмбөг'),
(38, 2, 'Волейбол'),
(39, 2, 'Ширээний теннис'),
(40, 2, 'Бадминтон'),
(41, 2, 'Талбайн теннис'),
(42, 2, 'Гольф'),
(43, 2, 'Бүжиг'),
(44, 2, 'Иога'),
(45, 2, 'Билльярд'),
(25, 3, 'Хөлбөмбөг'),
(26, 4, 'Сагсанбөмбөг'),
(27, 4, 'Талбайн теннис');

-- --------------------------------------------------------

--
-- Table structure for table `venuestaffassignment`
--

CREATE TABLE `venuestaffassignment` (
  `AssignmentID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `VenueID` int(11) NOT NULL,
  `ManagerID` int(11) NOT NULL,
  `Role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venuestaffassignment`
--

INSERT INTO `venuestaffassignment` (`AssignmentID`, `UserID`, `VenueID`, `ManagerID`, `Role`) VALUES
(9, 9, 2, 8, 'VenueStaff'),
(10, 9, 2, 8, 'Accountant'),
(11, 9, 3, 8, 'VenueStaff'),
(12, 9, 3, 8, 'Accountant'),
(14, 9, 4, 8, 'VenueStaff');

-- --------------------------------------------------------

--
-- Table structure for table `venuetimeslot`
--

CREATE TABLE `venuetimeslot` (
  `SlotID` int(11) NOT NULL,
  `VenueID` int(11) DEFAULT NULL,
  `Week` int(11) NOT NULL,
  `DayOfWeek` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Status` enum('Available','Booked') DEFAULT 'Available',
  `Price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venuetimeslot`
--

INSERT INTO `venuetimeslot` (`SlotID`, `VenueID`, `Week`, `DayOfWeek`, `StartTime`, `EndTime`, `Status`, `Price`) VALUES
(1, 1, 1, 'Saturday', '08:00:00', '09:00:00', 'Available', 50000.00),
(2, 1, 1, 'Saturday', '09:00:00', '10:00:00', 'Available', 50000.00),
(3, 1, 1, 'Saturday', '10:00:00', '11:00:00', 'Available', 50000.00),
(4, 1, 1, 'Saturday', '11:00:00', '12:00:00', 'Available', 50000.00),
(5, 1, 1, 'Saturday', '12:00:00', '13:00:00', 'Available', 50000.00),
(6, 1, 1, 'Saturday', '13:00:00', '14:00:00', 'Available', 50000.00),
(7, 1, 1, 'Saturday', '14:00:00', '15:00:00', 'Available', 50000.00),
(8, 1, 1, 'Saturday', '15:00:00', '16:00:00', 'Available', 50000.00),
(9, 1, 1, 'Saturday', '16:00:00', '17:00:00', 'Available', 50000.00),
(10, 1, 1, 'Saturday', '17:00:00', '18:00:00', 'Available', 50000.00),
(11, 1, 1, 'Saturday', '18:00:00', '19:00:00', 'Available', 50000.00),
(12, 1, 1, 'Saturday', '19:00:00', '20:00:00', 'Booked', 50000.00),
(13, 1, 1, 'Saturday', '20:00:00', '21:00:00', 'Booked', 50000.00),
(14, 1, 1, 'Saturday', '21:00:00', '22:00:00', 'Available', 50000.00),
(15, 1, 1, 'Saturday', '22:00:00', '23:00:00', 'Available', 50000.00),
(16, 1, 1, 'Saturday', '23:00:00', '00:00:00', 'Available', 50000.00),
(17, 1, 1, 'Sunday', '08:00:00', '09:00:00', 'Available', 50000.00),
(18, 1, 1, 'Sunday', '09:00:00', '10:00:00', 'Available', 50000.00),
(19, 1, 1, 'Sunday', '10:00:00', '11:00:00', 'Available', 50000.00),
(20, 1, 1, 'Sunday', '11:00:00', '12:00:00', 'Available', 50000.00),
(21, 1, 1, 'Sunday', '12:00:00', '13:00:00', 'Available', 50000.00),
(22, 1, 1, 'Sunday', '13:00:00', '14:00:00', 'Available', 50000.00),
(23, 1, 1, 'Sunday', '14:00:00', '15:00:00', 'Available', 50000.00),
(24, 1, 1, 'Sunday', '15:00:00', '16:00:00', 'Available', 50000.00),
(25, 1, 1, 'Sunday', '16:00:00', '17:00:00', 'Available', 50000.00),
(26, 1, 1, 'Sunday', '17:00:00', '18:00:00', 'Available', 50000.00),
(27, 1, 1, 'Sunday', '18:00:00', '19:00:00', 'Available', 50000.00),
(28, 1, 1, 'Sunday', '19:00:00', '20:00:00', 'Available', 50000.00),
(29, 1, 1, 'Sunday', '20:00:00', '21:00:00', 'Available', 50000.00),
(30, 1, 1, 'Sunday', '21:00:00', '22:00:00', 'Booked', 50000.00),
(31, 1, 1, 'Sunday', '22:00:00', '23:00:00', 'Booked', 50000.00),
(32, 1, 1, 'Sunday', '23:00:00', '00:00:00', 'Available', 50000.00),
(33, 1, 1, 'Monday', '08:00:00', '09:00:00', 'Available', 50000.00),
(34, 1, 1, 'Monday', '09:00:00', '10:00:00', 'Available', 50000.00),
(35, 1, 1, 'Monday', '10:00:00', '11:00:00', 'Available', 50000.00),
(36, 1, 1, 'Monday', '11:00:00', '12:00:00', 'Available', 50000.00),
(37, 1, 1, 'Monday', '12:00:00', '13:00:00', 'Available', 50000.00),
(38, 1, 1, 'Monday', '13:00:00', '14:00:00', 'Available', 50000.00),
(39, 1, 1, 'Monday', '14:00:00', '15:00:00', 'Available', 50000.00),
(40, 1, 1, 'Monday', '15:00:00', '16:00:00', 'Available', 50000.00),
(41, 1, 1, 'Monday', '16:00:00', '17:00:00', 'Available', 50000.00),
(42, 1, 1, 'Monday', '17:00:00', '18:00:00', 'Available', 50000.00),
(43, 1, 1, 'Monday', '18:00:00', '19:00:00', 'Available', 50000.00),
(44, 1, 1, 'Monday', '19:00:00', '20:00:00', 'Available', 50000.00),
(45, 1, 1, 'Monday', '20:00:00', '21:00:00', 'Available', 50000.00),
(46, 1, 1, 'Monday', '21:00:00', '22:00:00', 'Available', 50000.00),
(47, 1, 1, 'Monday', '22:00:00', '23:00:00', 'Available', 50000.00),
(48, 1, 1, 'Monday', '23:00:00', '00:00:00', 'Available', 50000.00),
(49, 1, 1, 'Tuesday', '08:00:00', '09:00:00', 'Available', 50000.00),
(50, 1, 1, 'Tuesday', '09:00:00', '10:00:00', 'Available', 50000.00),
(51, 1, 1, 'Tuesday', '10:00:00', '11:00:00', 'Available', 50000.00),
(52, 1, 1, 'Tuesday', '11:00:00', '12:00:00', 'Available', 50000.00),
(53, 1, 1, 'Tuesday', '12:00:00', '13:00:00', 'Available', 50000.00),
(54, 1, 1, 'Tuesday', '13:00:00', '14:00:00', 'Available', 50000.00),
(55, 1, 1, 'Tuesday', '14:00:00', '15:00:00', 'Available', 50000.00),
(56, 1, 1, 'Tuesday', '15:00:00', '16:00:00', 'Available', 50000.00),
(57, 1, 1, 'Tuesday', '16:00:00', '17:00:00', 'Available', 50000.00),
(58, 1, 1, 'Tuesday', '17:00:00', '18:00:00', 'Available', 50000.00),
(59, 1, 1, 'Tuesday', '18:00:00', '19:00:00', 'Available', 50000.00),
(60, 1, 1, 'Tuesday', '19:00:00', '20:00:00', 'Available', 50000.00),
(61, 1, 1, 'Tuesday', '20:00:00', '21:00:00', 'Available', 50000.00),
(62, 1, 1, 'Tuesday', '21:00:00', '22:00:00', 'Available', 50000.00),
(63, 1, 1, 'Tuesday', '22:00:00', '23:00:00', 'Available', 50000.00),
(64, 1, 1, 'Tuesday', '23:00:00', '00:00:00', 'Available', 50000.00),
(65, 1, 1, 'Wednesday', '08:00:00', '09:00:00', 'Available', 50000.00),
(66, 1, 1, 'Wednesday', '09:00:00', '10:00:00', 'Available', 50000.00),
(67, 1, 1, 'Wednesday', '10:00:00', '11:00:00', 'Available', 50000.00),
(68, 1, 1, 'Wednesday', '11:00:00', '12:00:00', 'Available', 50000.00),
(69, 1, 1, 'Wednesday', '12:00:00', '13:00:00', 'Available', 50000.00),
(70, 1, 1, 'Wednesday', '13:00:00', '14:00:00', 'Available', 50000.00),
(71, 1, 1, 'Wednesday', '14:00:00', '15:00:00', 'Available', 50000.00),
(72, 1, 1, 'Wednesday', '15:00:00', '16:00:00', 'Available', 50000.00),
(73, 1, 1, 'Wednesday', '16:00:00', '17:00:00', 'Available', 50000.00),
(74, 1, 1, 'Wednesday', '17:00:00', '18:00:00', 'Available', 50000.00),
(75, 1, 1, 'Wednesday', '18:00:00', '19:00:00', 'Available', 50000.00),
(76, 1, 1, 'Wednesday', '19:00:00', '20:00:00', 'Available', 50000.00),
(77, 1, 1, 'Wednesday', '20:00:00', '21:00:00', 'Available', 50000.00),
(78, 1, 1, 'Wednesday', '21:00:00', '22:00:00', 'Available', 50000.00),
(79, 1, 1, 'Wednesday', '22:00:00', '23:00:00', 'Available', 50000.00),
(80, 1, 1, 'Wednesday', '23:00:00', '00:00:00', 'Available', 50000.00),
(81, 1, 1, 'Thursday', '08:00:00', '09:00:00', 'Available', 50000.00),
(82, 1, 1, 'Thursday', '09:00:00', '10:00:00', 'Available', 50000.00),
(83, 1, 1, 'Thursday', '10:00:00', '11:00:00', 'Available', 50000.00),
(84, 1, 1, 'Thursday', '11:00:00', '12:00:00', 'Available', 50000.00),
(85, 1, 1, 'Thursday', '12:00:00', '13:00:00', 'Available', 50000.00),
(86, 1, 1, 'Thursday', '13:00:00', '14:00:00', 'Available', 50000.00),
(87, 1, 1, 'Thursday', '14:00:00', '15:00:00', 'Available', 50000.00),
(88, 1, 1, 'Thursday', '15:00:00', '16:00:00', 'Available', 50000.00),
(89, 1, 1, 'Thursday', '16:00:00', '17:00:00', 'Available', 50000.00),
(90, 1, 1, 'Thursday', '17:00:00', '18:00:00', 'Available', 50000.00),
(91, 1, 1, 'Thursday', '18:00:00', '19:00:00', 'Available', 50000.00),
(92, 1, 1, 'Thursday', '19:00:00', '20:00:00', 'Available', 50000.00),
(93, 1, 1, 'Thursday', '20:00:00', '21:00:00', 'Available', 50000.00),
(94, 1, 1, 'Thursday', '21:00:00', '22:00:00', 'Booked', 50000.00),
(95, 1, 1, 'Thursday', '22:00:00', '23:00:00', 'Booked', 50000.00),
(96, 1, 1, 'Thursday', '23:00:00', '00:00:00', 'Available', 50000.00),
(97, 1, 1, 'Friday', '08:00:00', '09:00:00', 'Available', 50000.00),
(98, 1, 1, 'Friday', '09:00:00', '10:00:00', 'Available', 50000.00),
(99, 1, 1, 'Friday', '10:00:00', '11:00:00', 'Available', 50000.00),
(100, 1, 1, 'Friday', '11:00:00', '12:00:00', 'Available', 50000.00),
(101, 1, 1, 'Friday', '12:00:00', '13:00:00', 'Available', 50000.00),
(102, 1, 1, 'Friday', '13:00:00', '14:00:00', 'Available', 50000.00),
(103, 1, 1, 'Friday', '14:00:00', '15:00:00', 'Available', 50000.00),
(104, 1, 1, 'Friday', '15:00:00', '16:00:00', 'Available', 50000.00),
(105, 1, 1, 'Friday', '16:00:00', '17:00:00', 'Available', 50000.00),
(106, 1, 1, 'Friday', '17:00:00', '18:00:00', 'Available', 50000.00),
(107, 1, 1, 'Friday', '18:00:00', '19:00:00', 'Available', 50000.00),
(108, 1, 1, 'Friday', '19:00:00', '20:00:00', 'Available', 50000.00),
(109, 1, 1, 'Friday', '20:00:00', '21:00:00', 'Available', 50000.00),
(110, 1, 1, 'Friday', '21:00:00', '22:00:00', 'Available', 50000.00),
(111, 1, 1, 'Friday', '22:00:00', '23:00:00', 'Available', 50000.00),
(112, 1, 1, 'Friday', '23:00:00', '00:00:00', 'Available', 50000.00),
(113, 2, 1, 'Saturday', '08:00:00', '09:00:00', 'Available', 6005.00),
(114, 2, 1, 'Saturday', '09:00:00', '10:00:00', 'Available', 6005.00),
(115, 2, 1, 'Saturday', '10:00:00', '11:00:00', 'Available', 6005.00),
(116, 2, 1, 'Saturday', '11:00:00', '12:00:00', 'Available', 6005.00),
(117, 2, 1, 'Saturday', '12:00:00', '13:00:00', 'Available', 6005.00),
(118, 2, 1, 'Saturday', '13:00:00', '14:00:00', 'Available', 6005.00),
(119, 2, 1, 'Saturday', '14:00:00', '15:00:00', 'Available', 6005.00),
(120, 2, 1, 'Saturday', '15:00:00', '16:00:00', 'Available', 6005.00),
(121, 2, 1, 'Saturday', '16:00:00', '17:00:00', 'Available', 6005.00),
(122, 2, 1, 'Saturday', '17:00:00', '18:00:00', 'Available', 6005.00),
(123, 2, 1, 'Saturday', '18:00:00', '19:00:00', 'Available', 6005.00),
(124, 2, 1, 'Saturday', '19:00:00', '20:00:00', 'Available', 6005.00),
(125, 2, 1, 'Saturday', '20:00:00', '21:00:00', 'Available', 6005.00),
(126, 2, 1, 'Saturday', '21:00:00', '22:00:00', 'Available', 6005.00),
(127, 2, 1, 'Saturday', '22:00:00', '23:00:00', 'Available', 6005.00),
(128, 2, 1, 'Saturday', '23:00:00', '00:00:00', 'Available', 6005.00),
(129, 2, 1, 'Sunday', '08:00:00', '09:00:00', 'Available', 6005.00),
(130, 2, 1, 'Sunday', '09:00:00', '10:00:00', 'Available', 6005.00),
(131, 2, 1, 'Sunday', '10:00:00', '11:00:00', 'Available', 6005.00),
(132, 2, 1, 'Sunday', '11:00:00', '12:00:00', 'Available', 6005.00),
(133, 2, 1, 'Sunday', '12:00:00', '13:00:00', 'Available', 6005.00),
(134, 2, 1, 'Sunday', '13:00:00', '14:00:00', 'Available', 6005.00),
(135, 2, 1, 'Sunday', '14:00:00', '15:00:00', 'Available', 6005.00),
(136, 2, 1, 'Sunday', '15:00:00', '16:00:00', 'Available', 6005.00),
(137, 2, 1, 'Sunday', '16:00:00', '17:00:00', 'Available', 6005.00),
(138, 2, 1, 'Sunday', '17:00:00', '18:00:00', 'Available', 6005.00),
(139, 2, 1, 'Sunday', '18:00:00', '19:00:00', 'Available', 6005.00),
(140, 2, 1, 'Sunday', '19:00:00', '20:00:00', 'Available', 6005.00),
(141, 2, 1, 'Sunday', '20:00:00', '21:00:00', 'Available', 6005.00),
(142, 2, 1, 'Sunday', '21:00:00', '22:00:00', 'Available', 6005.00),
(143, 2, 1, 'Sunday', '22:00:00', '23:00:00', 'Available', 6005.00),
(144, 2, 1, 'Sunday', '23:00:00', '00:00:00', 'Available', 6005.00),
(145, 2, 1, 'Monday', '08:00:00', '09:00:00', 'Available', 6005.00),
(146, 2, 1, 'Monday', '09:00:00', '10:00:00', 'Available', 6005.00),
(147, 2, 1, 'Monday', '10:00:00', '11:00:00', 'Available', 6005.00),
(148, 2, 1, 'Monday', '11:00:00', '12:00:00', 'Available', 6005.00),
(149, 2, 1, 'Monday', '12:00:00', '13:00:00', 'Available', 6005.00),
(150, 2, 1, 'Monday', '13:00:00', '14:00:00', 'Available', 6005.00),
(151, 2, 1, 'Monday', '14:00:00', '15:00:00', 'Available', 6005.00),
(152, 2, 1, 'Monday', '15:00:00', '16:00:00', 'Available', 6005.00),
(153, 2, 1, 'Monday', '16:00:00', '17:00:00', 'Available', 6005.00),
(154, 2, 1, 'Monday', '17:00:00', '18:00:00', 'Available', 6005.00),
(155, 2, 1, 'Monday', '18:00:00', '19:00:00', 'Available', 6005.00),
(156, 2, 1, 'Monday', '19:00:00', '20:00:00', 'Available', 6005.00),
(157, 2, 1, 'Monday', '20:00:00', '21:00:00', 'Available', 6005.00),
(158, 2, 1, 'Monday', '21:00:00', '22:00:00', 'Available', 6005.00),
(159, 2, 1, 'Monday', '22:00:00', '23:00:00', 'Available', 6005.00),
(160, 2, 1, 'Monday', '23:00:00', '00:00:00', 'Available', 6005.00),
(161, 2, 1, 'Tuesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(162, 2, 1, 'Tuesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(163, 2, 1, 'Tuesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(164, 2, 1, 'Tuesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(165, 2, 1, 'Tuesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(166, 2, 1, 'Tuesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(167, 2, 1, 'Tuesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(168, 2, 1, 'Tuesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(169, 2, 1, 'Tuesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(170, 2, 1, 'Tuesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(171, 2, 1, 'Tuesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(172, 2, 1, 'Tuesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(173, 2, 1, 'Tuesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(174, 2, 1, 'Tuesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(175, 2, 1, 'Tuesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(176, 2, 1, 'Tuesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(177, 2, 1, 'Wednesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(178, 2, 1, 'Wednesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(179, 2, 1, 'Wednesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(180, 2, 1, 'Wednesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(181, 2, 1, 'Wednesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(182, 2, 1, 'Wednesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(183, 2, 1, 'Wednesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(184, 2, 1, 'Wednesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(185, 2, 1, 'Wednesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(186, 2, 1, 'Wednesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(187, 2, 1, 'Wednesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(188, 2, 1, 'Wednesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(189, 2, 1, 'Wednesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(190, 2, 1, 'Wednesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(191, 2, 1, 'Wednesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(192, 2, 1, 'Wednesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(193, 2, 1, 'Thursday', '08:00:00', '09:00:00', 'Available', 6005.00),
(194, 2, 1, 'Thursday', '09:00:00', '10:00:00', 'Available', 6005.00),
(195, 2, 1, 'Thursday', '10:00:00', '11:00:00', 'Available', 6005.00),
(196, 2, 1, 'Thursday', '11:00:00', '12:00:00', 'Available', 6005.00),
(197, 2, 1, 'Thursday', '12:00:00', '13:00:00', 'Available', 6005.00),
(198, 2, 1, 'Thursday', '13:00:00', '14:00:00', 'Available', 6005.00),
(199, 2, 1, 'Thursday', '14:00:00', '15:00:00', 'Available', 6005.00),
(200, 2, 1, 'Thursday', '15:00:00', '16:00:00', 'Available', 6005.00),
(201, 2, 1, 'Thursday', '16:00:00', '17:00:00', 'Available', 6005.00),
(202, 2, 1, 'Thursday', '17:00:00', '18:00:00', 'Available', 6005.00),
(203, 2, 1, 'Thursday', '18:00:00', '19:00:00', 'Available', 6005.00),
(204, 2, 1, 'Thursday', '19:00:00', '20:00:00', 'Available', 6005.00),
(205, 2, 1, 'Thursday', '20:00:00', '21:00:00', 'Available', 6005.00),
(206, 2, 1, 'Thursday', '21:00:00', '22:00:00', 'Available', 6005.00),
(207, 2, 1, 'Thursday', '22:00:00', '23:00:00', 'Available', 6005.00),
(208, 2, 1, 'Thursday', '23:00:00', '00:00:00', 'Available', 6005.00),
(209, 2, 1, 'Friday', '08:00:00', '09:00:00', 'Available', 6005.00),
(210, 2, 1, 'Friday', '09:00:00', '10:00:00', 'Available', 6005.00),
(211, 2, 1, 'Friday', '10:00:00', '11:00:00', 'Available', 6005.00),
(212, 2, 1, 'Friday', '11:00:00', '12:00:00', 'Available', 6005.00),
(213, 2, 1, 'Friday', '12:00:00', '13:00:00', 'Available', 6005.00),
(214, 2, 1, 'Friday', '13:00:00', '14:00:00', 'Available', 6005.00),
(215, 2, 1, 'Friday', '14:00:00', '15:00:00', 'Available', 6005.00),
(216, 2, 1, 'Friday', '15:00:00', '16:00:00', 'Available', 6005.00),
(217, 2, 1, 'Friday', '16:00:00', '17:00:00', 'Available', 6005.00),
(218, 2, 1, 'Friday', '17:00:00', '18:00:00', 'Available', 6005.00),
(219, 2, 1, 'Friday', '18:00:00', '19:00:00', 'Available', 6005.00),
(220, 2, 1, 'Friday', '19:00:00', '20:00:00', 'Available', 6005.00),
(221, 2, 1, 'Friday', '20:00:00', '21:00:00', 'Available', 6005.00),
(222, 2, 1, 'Friday', '21:00:00', '22:00:00', 'Available', 6005.00),
(223, 2, 1, 'Friday', '22:00:00', '23:00:00', 'Available', 6005.00),
(224, 2, 1, 'Friday', '23:00:00', '00:00:00', 'Available', 6005.00),
(225, 2, 2, 'Saturday', '08:00:00', '09:00:00', 'Available', 6005.00),
(226, 2, 2, 'Saturday', '09:00:00', '10:00:00', 'Available', 6005.00),
(227, 2, 2, 'Saturday', '10:00:00', '11:00:00', 'Available', 6005.00),
(228, 2, 2, 'Saturday', '11:00:00', '12:00:00', 'Available', 6005.00),
(229, 2, 2, 'Saturday', '12:00:00', '13:00:00', 'Available', 6005.00),
(230, 2, 2, 'Saturday', '13:00:00', '14:00:00', 'Available', 6005.00),
(231, 2, 2, 'Saturday', '14:00:00', '15:00:00', 'Available', 6005.00),
(232, 2, 2, 'Saturday', '15:00:00', '16:00:00', 'Available', 6005.00),
(233, 2, 2, 'Saturday', '16:00:00', '17:00:00', 'Available', 6005.00),
(234, 2, 2, 'Saturday', '17:00:00', '18:00:00', 'Available', 6005.00),
(235, 2, 2, 'Saturday', '18:00:00', '19:00:00', 'Available', 6005.00),
(236, 2, 2, 'Saturday', '19:00:00', '20:00:00', 'Available', 6005.00),
(237, 2, 2, 'Saturday', '20:00:00', '21:00:00', 'Available', 6005.00),
(238, 2, 2, 'Saturday', '21:00:00', '22:00:00', 'Available', 6005.00),
(239, 2, 2, 'Saturday', '22:00:00', '23:00:00', 'Available', 6005.00),
(240, 2, 2, 'Saturday', '23:00:00', '00:00:00', 'Available', 6005.00),
(241, 2, 2, 'Sunday', '08:00:00', '09:00:00', 'Available', 6005.00),
(242, 2, 2, 'Sunday', '09:00:00', '10:00:00', 'Available', 6005.00),
(243, 2, 2, 'Sunday', '10:00:00', '11:00:00', 'Available', 6005.00),
(244, 2, 2, 'Sunday', '11:00:00', '12:00:00', 'Booked', 6005.00),
(245, 2, 2, 'Sunday', '12:00:00', '13:00:00', 'Available', 6005.00),
(246, 2, 2, 'Sunday', '13:00:00', '14:00:00', 'Available', 6005.00),
(247, 2, 2, 'Sunday', '14:00:00', '15:00:00', 'Available', 6005.00),
(248, 2, 2, 'Sunday', '15:00:00', '16:00:00', 'Available', 6005.00),
(249, 2, 2, 'Sunday', '16:00:00', '17:00:00', 'Available', 6005.00),
(250, 2, 2, 'Sunday', '17:00:00', '18:00:00', 'Available', 6005.00),
(251, 2, 2, 'Sunday', '18:00:00', '19:00:00', 'Available', 6005.00),
(252, 2, 2, 'Sunday', '19:00:00', '20:00:00', 'Available', 6005.00),
(253, 2, 2, 'Sunday', '20:00:00', '21:00:00', 'Available', 6005.00),
(254, 2, 2, 'Sunday', '21:00:00', '22:00:00', 'Available', 6005.00),
(255, 2, 2, 'Sunday', '22:00:00', '23:00:00', 'Available', 6005.00),
(256, 2, 2, 'Sunday', '23:00:00', '00:00:00', 'Available', 6005.00),
(257, 2, 2, 'Monday', '08:00:00', '09:00:00', 'Available', 6005.00),
(258, 2, 2, 'Monday', '09:00:00', '10:00:00', 'Available', 6005.00),
(259, 2, 2, 'Monday', '10:00:00', '11:00:00', 'Available', 6005.00),
(260, 2, 2, 'Monday', '11:00:00', '12:00:00', 'Available', 6005.00),
(261, 2, 2, 'Monday', '12:00:00', '13:00:00', 'Available', 6005.00),
(262, 2, 2, 'Monday', '13:00:00', '14:00:00', 'Available', 6005.00),
(263, 2, 2, 'Monday', '14:00:00', '15:00:00', 'Available', 6005.00),
(264, 2, 2, 'Monday', '15:00:00', '16:00:00', 'Available', 6005.00),
(265, 2, 2, 'Monday', '16:00:00', '17:00:00', 'Available', 6005.00),
(266, 2, 2, 'Monday', '17:00:00', '18:00:00', 'Available', 6005.00),
(267, 2, 2, 'Monday', '18:00:00', '19:00:00', 'Available', 6005.00),
(268, 2, 2, 'Monday', '19:00:00', '20:00:00', 'Available', 6005.00),
(269, 2, 2, 'Monday', '20:00:00', '21:00:00', 'Available', 6005.00),
(270, 2, 2, 'Monday', '21:00:00', '22:00:00', 'Available', 6005.00),
(271, 2, 2, 'Monday', '22:00:00', '23:00:00', 'Available', 6005.00),
(272, 2, 2, 'Monday', '23:00:00', '00:00:00', 'Available', 6005.00),
(273, 2, 2, 'Tuesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(274, 2, 2, 'Tuesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(275, 2, 2, 'Tuesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(276, 2, 2, 'Tuesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(277, 2, 2, 'Tuesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(278, 2, 2, 'Tuesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(279, 2, 2, 'Tuesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(280, 2, 2, 'Tuesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(281, 2, 2, 'Tuesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(282, 2, 2, 'Tuesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(283, 2, 2, 'Tuesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(284, 2, 2, 'Tuesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(285, 2, 2, 'Tuesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(286, 2, 2, 'Tuesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(287, 2, 2, 'Tuesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(288, 2, 2, 'Tuesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(289, 2, 2, 'Wednesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(290, 2, 2, 'Wednesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(291, 2, 2, 'Wednesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(292, 2, 2, 'Wednesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(293, 2, 2, 'Wednesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(294, 2, 2, 'Wednesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(295, 2, 2, 'Wednesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(296, 2, 2, 'Wednesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(297, 2, 2, 'Wednesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(298, 2, 2, 'Wednesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(299, 2, 2, 'Wednesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(300, 2, 2, 'Wednesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(301, 2, 2, 'Wednesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(302, 2, 2, 'Wednesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(303, 2, 2, 'Wednesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(304, 2, 2, 'Wednesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(305, 2, 2, 'Thursday', '08:00:00', '09:00:00', 'Available', 6005.00),
(306, 2, 2, 'Thursday', '09:00:00', '10:00:00', 'Available', 6005.00),
(307, 2, 2, 'Thursday', '10:00:00', '11:00:00', 'Available', 6005.00),
(308, 2, 2, 'Thursday', '11:00:00', '12:00:00', 'Available', 6005.00),
(309, 2, 2, 'Thursday', '12:00:00', '13:00:00', 'Available', 6005.00),
(310, 2, 2, 'Thursday', '13:00:00', '14:00:00', 'Available', 6005.00),
(311, 2, 2, 'Thursday', '14:00:00', '15:00:00', 'Available', 6005.00),
(312, 2, 2, 'Thursday', '15:00:00', '16:00:00', 'Available', 6005.00),
(313, 2, 2, 'Thursday', '16:00:00', '17:00:00', 'Available', 6005.00),
(314, 2, 2, 'Thursday', '17:00:00', '18:00:00', 'Available', 6005.00),
(315, 2, 2, 'Thursday', '18:00:00', '19:00:00', 'Available', 6005.00),
(316, 2, 2, 'Thursday', '19:00:00', '20:00:00', 'Available', 6005.00),
(317, 2, 2, 'Thursday', '20:00:00', '21:00:00', 'Available', 6005.00),
(318, 2, 2, 'Thursday', '21:00:00', '22:00:00', 'Available', 6005.00),
(319, 2, 2, 'Thursday', '22:00:00', '23:00:00', 'Available', 6005.00),
(320, 2, 2, 'Thursday', '23:00:00', '00:00:00', 'Available', 6005.00),
(321, 2, 2, 'Friday', '08:00:00', '09:00:00', 'Available', 6005.00),
(322, 2, 2, 'Friday', '09:00:00', '10:00:00', 'Available', 6005.00),
(323, 2, 2, 'Friday', '10:00:00', '11:00:00', 'Available', 6005.00),
(324, 2, 2, 'Friday', '11:00:00', '12:00:00', 'Available', 6005.00),
(325, 2, 2, 'Friday', '12:00:00', '13:00:00', 'Available', 6005.00),
(326, 2, 2, 'Friday', '13:00:00', '14:00:00', 'Available', 6005.00),
(327, 2, 2, 'Friday', '14:00:00', '15:00:00', 'Available', 6005.00),
(328, 2, 2, 'Friday', '15:00:00', '16:00:00', 'Available', 6005.00),
(329, 2, 2, 'Friday', '16:00:00', '17:00:00', 'Available', 6005.00),
(330, 2, 2, 'Friday', '17:00:00', '18:00:00', 'Available', 6005.00),
(331, 2, 2, 'Friday', '18:00:00', '19:00:00', 'Available', 6005.00),
(332, 2, 2, 'Friday', '19:00:00', '20:00:00', 'Available', 6005.00),
(333, 2, 2, 'Friday', '20:00:00', '21:00:00', 'Available', 6005.00),
(334, 2, 2, 'Friday', '21:00:00', '22:00:00', 'Available', 6005.00),
(335, 2, 2, 'Friday', '22:00:00', '23:00:00', 'Available', 6005.00),
(336, 2, 2, 'Friday', '23:00:00', '00:00:00', 'Available', 6005.00),
(337, 2, 3, 'Saturday', '08:00:00', '09:00:00', 'Available', 6005.00),
(338, 2, 3, 'Saturday', '09:00:00', '10:00:00', 'Available', 6005.00),
(339, 2, 3, 'Saturday', '10:00:00', '11:00:00', 'Available', 6005.00),
(340, 2, 3, 'Saturday', '11:00:00', '12:00:00', 'Available', 6005.00),
(341, 2, 3, 'Saturday', '12:00:00', '13:00:00', 'Available', 6005.00),
(342, 2, 3, 'Saturday', '13:00:00', '14:00:00', 'Available', 6005.00),
(343, 2, 3, 'Saturday', '14:00:00', '15:00:00', 'Available', 6005.00),
(344, 2, 3, 'Saturday', '15:00:00', '16:00:00', 'Available', 6005.00),
(345, 2, 3, 'Saturday', '16:00:00', '17:00:00', 'Available', 6005.00),
(346, 2, 3, 'Saturday', '17:00:00', '18:00:00', 'Available', 6005.00),
(347, 2, 3, 'Saturday', '18:00:00', '19:00:00', 'Available', 6005.00),
(348, 2, 3, 'Saturday', '19:00:00', '20:00:00', 'Available', 6005.00),
(349, 2, 3, 'Saturday', '20:00:00', '21:00:00', 'Available', 6005.00),
(350, 2, 3, 'Saturday', '21:00:00', '22:00:00', 'Available', 6005.00),
(351, 2, 3, 'Saturday', '22:00:00', '23:00:00', 'Available', 6005.00),
(352, 2, 3, 'Saturday', '23:00:00', '00:00:00', 'Available', 6005.00),
(353, 2, 3, 'Sunday', '08:00:00', '09:00:00', 'Available', 6005.00),
(354, 2, 3, 'Sunday', '09:00:00', '10:00:00', 'Available', 6005.00),
(355, 2, 3, 'Sunday', '10:00:00', '11:00:00', 'Available', 6005.00),
(356, 2, 3, 'Sunday', '11:00:00', '12:00:00', 'Available', 6005.00),
(357, 2, 3, 'Sunday', '12:00:00', '13:00:00', 'Available', 6005.00),
(358, 2, 3, 'Sunday', '13:00:00', '14:00:00', 'Available', 6005.00),
(359, 2, 3, 'Sunday', '14:00:00', '15:00:00', 'Available', 6005.00),
(360, 2, 3, 'Sunday', '15:00:00', '16:00:00', 'Available', 6005.00),
(361, 2, 3, 'Sunday', '16:00:00', '17:00:00', 'Available', 6005.00),
(362, 2, 3, 'Sunday', '17:00:00', '18:00:00', 'Available', 6005.00),
(363, 2, 3, 'Sunday', '18:00:00', '19:00:00', 'Available', 6005.00),
(364, 2, 3, 'Sunday', '19:00:00', '20:00:00', 'Available', 6005.00),
(365, 2, 3, 'Sunday', '20:00:00', '21:00:00', 'Available', 6005.00),
(366, 2, 3, 'Sunday', '21:00:00', '22:00:00', 'Available', 6005.00),
(367, 2, 3, 'Sunday', '22:00:00', '23:00:00', 'Available', 6005.00),
(368, 2, 3, 'Sunday', '23:00:00', '00:00:00', 'Available', 6005.00),
(369, 2, 3, 'Monday', '08:00:00', '09:00:00', 'Available', 6005.00),
(370, 2, 3, 'Monday', '09:00:00', '10:00:00', 'Available', 6005.00),
(371, 2, 3, 'Monday', '10:00:00', '11:00:00', 'Available', 6005.00),
(372, 2, 3, 'Monday', '11:00:00', '12:00:00', 'Available', 6005.00),
(373, 2, 3, 'Monday', '12:00:00', '13:00:00', 'Available', 6005.00),
(374, 2, 3, 'Monday', '13:00:00', '14:00:00', 'Available', 6005.00),
(375, 2, 3, 'Monday', '14:00:00', '15:00:00', 'Available', 6005.00),
(376, 2, 3, 'Monday', '15:00:00', '16:00:00', 'Available', 6005.00),
(377, 2, 3, 'Monday', '16:00:00', '17:00:00', 'Available', 6005.00),
(378, 2, 3, 'Monday', '17:00:00', '18:00:00', 'Available', 6005.00),
(379, 2, 3, 'Monday', '18:00:00', '19:00:00', 'Available', 6005.00),
(380, 2, 3, 'Monday', '19:00:00', '20:00:00', 'Available', 6005.00),
(381, 2, 3, 'Monday', '20:00:00', '21:00:00', 'Available', 6005.00),
(382, 2, 3, 'Monday', '21:00:00', '22:00:00', 'Available', 6005.00),
(383, 2, 3, 'Monday', '22:00:00', '23:00:00', 'Available', 6005.00),
(384, 2, 3, 'Monday', '23:00:00', '00:00:00', 'Available', 6005.00),
(385, 2, 3, 'Tuesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(386, 2, 3, 'Tuesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(387, 2, 3, 'Tuesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(388, 2, 3, 'Tuesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(389, 2, 3, 'Tuesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(390, 2, 3, 'Tuesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(391, 2, 3, 'Tuesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(392, 2, 3, 'Tuesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(393, 2, 3, 'Tuesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(394, 2, 3, 'Tuesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(395, 2, 3, 'Tuesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(396, 2, 3, 'Tuesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(397, 2, 3, 'Tuesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(398, 2, 3, 'Tuesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(399, 2, 3, 'Tuesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(400, 2, 3, 'Tuesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(401, 2, 3, 'Wednesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(402, 2, 3, 'Wednesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(403, 2, 3, 'Wednesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(404, 2, 3, 'Wednesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(405, 2, 3, 'Wednesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(406, 2, 3, 'Wednesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(407, 2, 3, 'Wednesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(408, 2, 3, 'Wednesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(409, 2, 3, 'Wednesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(410, 2, 3, 'Wednesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(411, 2, 3, 'Wednesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(412, 2, 3, 'Wednesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(413, 2, 3, 'Wednesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(414, 2, 3, 'Wednesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(415, 2, 3, 'Wednesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(416, 2, 3, 'Wednesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(417, 2, 3, 'Thursday', '08:00:00', '09:00:00', 'Available', 6005.00),
(418, 2, 3, 'Thursday', '09:00:00', '10:00:00', 'Available', 6005.00),
(419, 2, 3, 'Thursday', '10:00:00', '11:00:00', 'Available', 6005.00),
(420, 2, 3, 'Thursday', '11:00:00', '12:00:00', 'Available', 6005.00),
(421, 2, 3, 'Thursday', '12:00:00', '13:00:00', 'Available', 6005.00),
(422, 2, 3, 'Thursday', '13:00:00', '14:00:00', 'Available', 6005.00),
(423, 2, 3, 'Thursday', '14:00:00', '15:00:00', 'Available', 6005.00),
(424, 2, 3, 'Thursday', '15:00:00', '16:00:00', 'Available', 6005.00),
(425, 2, 3, 'Thursday', '16:00:00', '17:00:00', 'Available', 6005.00),
(426, 2, 3, 'Thursday', '17:00:00', '18:00:00', 'Available', 6005.00),
(427, 2, 3, 'Thursday', '18:00:00', '19:00:00', 'Available', 6005.00),
(428, 2, 3, 'Thursday', '19:00:00', '20:00:00', 'Available', 6005.00),
(429, 2, 3, 'Thursday', '20:00:00', '21:00:00', 'Available', 6005.00),
(430, 2, 3, 'Thursday', '21:00:00', '22:00:00', 'Available', 6005.00),
(431, 2, 3, 'Thursday', '22:00:00', '23:00:00', 'Available', 6005.00),
(432, 2, 3, 'Thursday', '23:00:00', '00:00:00', 'Available', 6005.00),
(433, 2, 3, 'Friday', '08:00:00', '09:00:00', 'Available', 6005.00),
(434, 2, 3, 'Friday', '09:00:00', '10:00:00', 'Available', 6005.00),
(435, 2, 3, 'Friday', '10:00:00', '11:00:00', 'Available', 6005.00),
(436, 2, 3, 'Friday', '11:00:00', '12:00:00', 'Available', 6005.00),
(437, 2, 3, 'Friday', '12:00:00', '13:00:00', 'Available', 6005.00),
(438, 2, 3, 'Friday', '13:00:00', '14:00:00', 'Available', 6005.00),
(439, 2, 3, 'Friday', '14:00:00', '15:00:00', 'Available', 6005.00),
(440, 2, 3, 'Friday', '15:00:00', '16:00:00', 'Available', 6005.00),
(441, 2, 3, 'Friday', '16:00:00', '17:00:00', 'Available', 6005.00),
(442, 2, 3, 'Friday', '17:00:00', '18:00:00', 'Available', 6005.00),
(443, 2, 3, 'Friday', '18:00:00', '19:00:00', 'Available', 6005.00),
(444, 2, 3, 'Friday', '19:00:00', '20:00:00', 'Available', 6005.00),
(445, 2, 3, 'Friday', '20:00:00', '21:00:00', 'Available', 6005.00),
(446, 2, 3, 'Friday', '21:00:00', '22:00:00', 'Available', 6005.00),
(447, 2, 3, 'Friday', '22:00:00', '23:00:00', 'Available', 6005.00),
(448, 2, 3, 'Friday', '23:00:00', '00:00:00', 'Available', 6005.00),
(449, 2, 4, 'Saturday', '08:00:00', '09:00:00', 'Available', 6005.00),
(450, 2, 4, 'Saturday', '09:00:00', '10:00:00', 'Available', 6005.00),
(451, 2, 4, 'Saturday', '10:00:00', '11:00:00', 'Available', 6005.00),
(452, 2, 4, 'Saturday', '11:00:00', '12:00:00', 'Available', 6005.00),
(453, 2, 4, 'Saturday', '12:00:00', '13:00:00', 'Available', 6005.00),
(454, 2, 4, 'Saturday', '13:00:00', '14:00:00', 'Available', 6005.00),
(455, 2, 4, 'Saturday', '14:00:00', '15:00:00', 'Available', 6005.00),
(456, 2, 4, 'Saturday', '15:00:00', '16:00:00', 'Available', 6005.00),
(457, 2, 4, 'Saturday', '16:00:00', '17:00:00', 'Available', 6005.00),
(458, 2, 4, 'Saturday', '17:00:00', '18:00:00', 'Available', 6005.00),
(459, 2, 4, 'Saturday', '18:00:00', '19:00:00', 'Available', 6005.00),
(460, 2, 4, 'Saturday', '19:00:00', '20:00:00', 'Available', 6005.00),
(461, 2, 4, 'Saturday', '20:00:00', '21:00:00', 'Available', 6005.00),
(462, 2, 4, 'Saturday', '21:00:00', '22:00:00', 'Available', 6005.00),
(463, 2, 4, 'Saturday', '22:00:00', '23:00:00', 'Available', 6005.00),
(464, 2, 4, 'Saturday', '23:00:00', '00:00:00', 'Available', 6005.00),
(465, 2, 4, 'Sunday', '08:00:00', '09:00:00', 'Available', 6005.00),
(466, 2, 4, 'Sunday', '09:00:00', '10:00:00', 'Available', 6005.00),
(467, 2, 4, 'Sunday', '10:00:00', '11:00:00', 'Available', 6005.00),
(468, 2, 4, 'Sunday', '11:00:00', '12:00:00', 'Available', 6005.00),
(469, 2, 4, 'Sunday', '12:00:00', '13:00:00', 'Available', 6005.00),
(470, 2, 4, 'Sunday', '13:00:00', '14:00:00', 'Available', 6005.00),
(471, 2, 4, 'Sunday', '14:00:00', '15:00:00', 'Available', 6005.00),
(472, 2, 4, 'Sunday', '15:00:00', '16:00:00', 'Booked', 6005.00),
(473, 2, 4, 'Sunday', '16:00:00', '17:00:00', 'Available', 6005.00),
(474, 2, 4, 'Sunday', '17:00:00', '18:00:00', 'Available', 6005.00),
(475, 2, 4, 'Sunday', '18:00:00', '19:00:00', 'Available', 6005.00),
(476, 2, 4, 'Sunday', '19:00:00', '20:00:00', 'Available', 6005.00),
(477, 2, 4, 'Sunday', '20:00:00', '21:00:00', 'Available', 6005.00),
(478, 2, 4, 'Sunday', '21:00:00', '22:00:00', 'Available', 6005.00),
(479, 2, 4, 'Sunday', '22:00:00', '23:00:00', 'Available', 6005.00),
(480, 2, 4, 'Sunday', '23:00:00', '00:00:00', 'Available', 6005.00),
(481, 2, 4, 'Monday', '08:00:00', '09:00:00', 'Available', 6005.00),
(482, 2, 4, 'Monday', '09:00:00', '10:00:00', 'Available', 6005.00),
(483, 2, 4, 'Monday', '10:00:00', '11:00:00', 'Available', 6005.00),
(484, 2, 4, 'Monday', '11:00:00', '12:00:00', 'Available', 6005.00),
(485, 2, 4, 'Monday', '12:00:00', '13:00:00', 'Available', 6005.00),
(486, 2, 4, 'Monday', '13:00:00', '14:00:00', 'Available', 6005.00),
(487, 2, 4, 'Monday', '14:00:00', '15:00:00', 'Available', 6005.00),
(488, 2, 4, 'Monday', '15:00:00', '16:00:00', 'Available', 6005.00),
(489, 2, 4, 'Monday', '16:00:00', '17:00:00', 'Available', 6005.00),
(490, 2, 4, 'Monday', '17:00:00', '18:00:00', 'Available', 6005.00),
(491, 2, 4, 'Monday', '18:00:00', '19:00:00', 'Available', 6005.00),
(492, 2, 4, 'Monday', '19:00:00', '20:00:00', 'Available', 6005.00),
(493, 2, 4, 'Monday', '20:00:00', '21:00:00', 'Available', 6005.00),
(494, 2, 4, 'Monday', '21:00:00', '22:00:00', 'Available', 6005.00),
(495, 2, 4, 'Monday', '22:00:00', '23:00:00', 'Available', 6005.00),
(496, 2, 4, 'Monday', '23:00:00', '00:00:00', 'Available', 6005.00),
(497, 2, 4, 'Tuesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(498, 2, 4, 'Tuesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(499, 2, 4, 'Tuesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(500, 2, 4, 'Tuesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(501, 2, 4, 'Tuesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(502, 2, 4, 'Tuesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(503, 2, 4, 'Tuesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(504, 2, 4, 'Tuesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(505, 2, 4, 'Tuesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(506, 2, 4, 'Tuesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(507, 2, 4, 'Tuesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(508, 2, 4, 'Tuesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(509, 2, 4, 'Tuesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(510, 2, 4, 'Tuesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(511, 2, 4, 'Tuesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(512, 2, 4, 'Tuesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(513, 2, 4, 'Wednesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(514, 2, 4, 'Wednesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(515, 2, 4, 'Wednesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(516, 2, 4, 'Wednesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(517, 2, 4, 'Wednesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(518, 2, 4, 'Wednesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(519, 2, 4, 'Wednesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(520, 2, 4, 'Wednesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(521, 2, 4, 'Wednesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(522, 2, 4, 'Wednesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(523, 2, 4, 'Wednesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(524, 2, 4, 'Wednesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(525, 2, 4, 'Wednesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(526, 2, 4, 'Wednesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(527, 2, 4, 'Wednesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(528, 2, 4, 'Wednesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(529, 2, 4, 'Thursday', '08:00:00', '09:00:00', 'Available', 6005.00),
(530, 2, 4, 'Thursday', '09:00:00', '10:00:00', 'Available', 6005.00),
(531, 2, 4, 'Thursday', '10:00:00', '11:00:00', 'Available', 6005.00),
(532, 2, 4, 'Thursday', '11:00:00', '12:00:00', 'Available', 6005.00),
(533, 2, 4, 'Thursday', '12:00:00', '13:00:00', 'Available', 6005.00),
(534, 2, 4, 'Thursday', '13:00:00', '14:00:00', 'Available', 6005.00),
(535, 2, 4, 'Thursday', '14:00:00', '15:00:00', 'Available', 6005.00),
(536, 2, 4, 'Thursday', '15:00:00', '16:00:00', 'Available', 6005.00),
(537, 2, 4, 'Thursday', '16:00:00', '17:00:00', 'Available', 6005.00),
(538, 2, 4, 'Thursday', '17:00:00', '18:00:00', 'Available', 6005.00),
(539, 2, 4, 'Thursday', '18:00:00', '19:00:00', 'Available', 6005.00),
(540, 2, 4, 'Thursday', '19:00:00', '20:00:00', 'Available', 6005.00),
(541, 2, 4, 'Thursday', '20:00:00', '21:00:00', 'Available', 6005.00),
(542, 2, 4, 'Thursday', '21:00:00', '22:00:00', 'Available', 6005.00),
(543, 2, 4, 'Thursday', '22:00:00', '23:00:00', 'Available', 6005.00),
(544, 2, 4, 'Thursday', '23:00:00', '00:00:00', 'Available', 6005.00),
(545, 2, 4, 'Friday', '08:00:00', '09:00:00', 'Available', 6005.00),
(546, 2, 4, 'Friday', '09:00:00', '10:00:00', 'Available', 6005.00),
(547, 2, 4, 'Friday', '10:00:00', '11:00:00', 'Available', 6005.00),
(548, 2, 4, 'Friday', '11:00:00', '12:00:00', 'Available', 6005.00),
(549, 2, 4, 'Friday', '12:00:00', '13:00:00', 'Available', 6005.00),
(550, 2, 4, 'Friday', '13:00:00', '14:00:00', 'Available', 6005.00),
(551, 2, 4, 'Friday', '14:00:00', '15:00:00', 'Available', 6005.00),
(552, 2, 4, 'Friday', '15:00:00', '16:00:00', 'Available', 6005.00),
(553, 2, 4, 'Friday', '16:00:00', '17:00:00', 'Available', 6005.00),
(554, 2, 4, 'Friday', '17:00:00', '18:00:00', 'Available', 6005.00),
(555, 2, 4, 'Friday', '18:00:00', '19:00:00', 'Available', 6005.00),
(556, 2, 4, 'Friday', '19:00:00', '20:00:00', 'Available', 6005.00),
(557, 2, 4, 'Friday', '20:00:00', '21:00:00', 'Available', 6005.00),
(558, 2, 4, 'Friday', '21:00:00', '22:00:00', 'Available', 6005.00),
(559, 2, 4, 'Friday', '22:00:00', '23:00:00', 'Available', 6005.00),
(560, 2, 4, 'Friday', '23:00:00', '00:00:00', 'Available', 6005.00),
(561, 2, 5, 'Saturday', '08:00:00', '09:00:00', 'Available', 6005.00),
(562, 2, 5, 'Saturday', '09:00:00', '10:00:00', 'Available', 6005.00),
(563, 2, 5, 'Saturday', '10:00:00', '11:00:00', 'Available', 6005.00),
(564, 2, 5, 'Saturday', '11:00:00', '12:00:00', 'Available', 6005.00),
(565, 2, 5, 'Saturday', '12:00:00', '13:00:00', 'Available', 6005.00),
(566, 2, 5, 'Saturday', '13:00:00', '14:00:00', 'Available', 6005.00),
(567, 2, 5, 'Saturday', '14:00:00', '15:00:00', 'Available', 6005.00),
(568, 2, 5, 'Saturday', '15:00:00', '16:00:00', 'Available', 6005.00),
(569, 2, 5, 'Saturday', '16:00:00', '17:00:00', 'Available', 6005.00),
(570, 2, 5, 'Saturday', '17:00:00', '18:00:00', 'Available', 6005.00),
(571, 2, 5, 'Saturday', '18:00:00', '19:00:00', 'Available', 6005.00),
(572, 2, 5, 'Saturday', '19:00:00', '20:00:00', 'Available', 6005.00),
(573, 2, 5, 'Saturday', '20:00:00', '21:00:00', 'Available', 6005.00),
(574, 2, 5, 'Saturday', '21:00:00', '22:00:00', 'Available', 6005.00),
(575, 2, 5, 'Saturday', '22:00:00', '23:00:00', 'Available', 6005.00),
(576, 2, 5, 'Saturday', '23:00:00', '00:00:00', 'Available', 6005.00),
(577, 2, 5, 'Sunday', '08:00:00', '09:00:00', 'Available', 6005.00),
(578, 2, 5, 'Sunday', '09:00:00', '10:00:00', 'Available', 6005.00),
(579, 2, 5, 'Sunday', '10:00:00', '11:00:00', 'Available', 6005.00),
(580, 2, 5, 'Sunday', '11:00:00', '12:00:00', 'Available', 6005.00),
(581, 2, 5, 'Sunday', '12:00:00', '13:00:00', 'Booked', 6005.00),
(582, 2, 5, 'Sunday', '13:00:00', '14:00:00', 'Available', 6005.00),
(583, 2, 5, 'Sunday', '14:00:00', '15:00:00', 'Available', 6005.00),
(584, 2, 5, 'Sunday', '15:00:00', '16:00:00', 'Available', 6005.00),
(585, 2, 5, 'Sunday', '16:00:00', '17:00:00', 'Available', 6005.00),
(586, 2, 5, 'Sunday', '17:00:00', '18:00:00', 'Available', 6005.00),
(587, 2, 5, 'Sunday', '18:00:00', '19:00:00', 'Available', 6005.00),
(588, 2, 5, 'Sunday', '19:00:00', '20:00:00', 'Available', 6005.00),
(589, 2, 5, 'Sunday', '20:00:00', '21:00:00', 'Available', 6005.00),
(590, 2, 5, 'Sunday', '21:00:00', '22:00:00', 'Available', 6005.00),
(591, 2, 5, 'Sunday', '22:00:00', '23:00:00', 'Available', 6005.00),
(592, 2, 5, 'Sunday', '23:00:00', '00:00:00', 'Available', 6005.00),
(593, 2, 5, 'Monday', '08:00:00', '09:00:00', 'Available', 6005.00),
(594, 2, 5, 'Monday', '09:00:00', '10:00:00', 'Available', 6005.00),
(595, 2, 5, 'Monday', '10:00:00', '11:00:00', 'Available', 6005.00),
(596, 2, 5, 'Monday', '11:00:00', '12:00:00', 'Available', 6005.00),
(597, 2, 5, 'Monday', '12:00:00', '13:00:00', 'Available', 6005.00),
(598, 2, 5, 'Monday', '13:00:00', '14:00:00', 'Available', 6005.00),
(599, 2, 5, 'Monday', '14:00:00', '15:00:00', 'Available', 6005.00),
(600, 2, 5, 'Monday', '15:00:00', '16:00:00', 'Available', 6005.00),
(601, 2, 5, 'Monday', '16:00:00', '17:00:00', 'Available', 6005.00),
(602, 2, 5, 'Monday', '17:00:00', '18:00:00', 'Available', 6005.00),
(603, 2, 5, 'Monday', '18:00:00', '19:00:00', 'Available', 6005.00),
(604, 2, 5, 'Monday', '19:00:00', '20:00:00', 'Available', 6005.00),
(605, 2, 5, 'Monday', '20:00:00', '21:00:00', 'Available', 6005.00),
(606, 2, 5, 'Monday', '21:00:00', '22:00:00', 'Available', 6005.00),
(607, 2, 5, 'Monday', '22:00:00', '23:00:00', 'Available', 6005.00),
(608, 2, 5, 'Monday', '23:00:00', '00:00:00', 'Available', 6005.00),
(609, 2, 5, 'Tuesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(610, 2, 5, 'Tuesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(611, 2, 5, 'Tuesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(612, 2, 5, 'Tuesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(613, 2, 5, 'Tuesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(614, 2, 5, 'Tuesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(615, 2, 5, 'Tuesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(616, 2, 5, 'Tuesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(617, 2, 5, 'Tuesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(618, 2, 5, 'Tuesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(619, 2, 5, 'Tuesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(620, 2, 5, 'Tuesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(621, 2, 5, 'Tuesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(622, 2, 5, 'Tuesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(623, 2, 5, 'Tuesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(624, 2, 5, 'Tuesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(625, 2, 5, 'Wednesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(626, 2, 5, 'Wednesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(627, 2, 5, 'Wednesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(628, 2, 5, 'Wednesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(629, 2, 5, 'Wednesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(630, 2, 5, 'Wednesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(631, 2, 5, 'Wednesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(632, 2, 5, 'Wednesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(633, 2, 5, 'Wednesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(634, 2, 5, 'Wednesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(635, 2, 5, 'Wednesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(636, 2, 5, 'Wednesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(637, 2, 5, 'Wednesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(638, 2, 5, 'Wednesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(639, 2, 5, 'Wednesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(640, 2, 5, 'Wednesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(641, 2, 5, 'Thursday', '08:00:00', '09:00:00', 'Available', 6005.00),
(642, 2, 5, 'Thursday', '09:00:00', '10:00:00', 'Available', 6005.00),
(643, 2, 5, 'Thursday', '10:00:00', '11:00:00', 'Available', 6005.00),
(644, 2, 5, 'Thursday', '11:00:00', '12:00:00', 'Available', 6005.00),
(645, 2, 5, 'Thursday', '12:00:00', '13:00:00', 'Available', 6005.00),
(646, 2, 5, 'Thursday', '13:00:00', '14:00:00', 'Available', 6005.00),
(647, 2, 5, 'Thursday', '14:00:00', '15:00:00', 'Available', 6005.00),
(648, 2, 5, 'Thursday', '15:00:00', '16:00:00', 'Available', 6005.00),
(649, 2, 5, 'Thursday', '16:00:00', '17:00:00', 'Available', 6005.00),
(650, 2, 5, 'Thursday', '17:00:00', '18:00:00', 'Available', 6005.00),
(651, 2, 5, 'Thursday', '18:00:00', '19:00:00', 'Available', 6005.00),
(652, 2, 5, 'Thursday', '19:00:00', '20:00:00', 'Available', 6005.00),
(653, 2, 5, 'Thursday', '20:00:00', '21:00:00', 'Available', 6005.00),
(654, 2, 5, 'Thursday', '21:00:00', '22:00:00', 'Available', 6005.00),
(655, 2, 5, 'Thursday', '22:00:00', '23:00:00', 'Available', 6005.00),
(656, 2, 5, 'Thursday', '23:00:00', '00:00:00', 'Available', 6005.00),
(657, 2, 5, 'Friday', '08:00:00', '09:00:00', 'Available', 6005.00),
(658, 2, 5, 'Friday', '09:00:00', '10:00:00', 'Available', 6005.00),
(659, 2, 5, 'Friday', '10:00:00', '11:00:00', 'Available', 6005.00),
(660, 2, 5, 'Friday', '11:00:00', '12:00:00', 'Available', 6005.00),
(661, 2, 5, 'Friday', '12:00:00', '13:00:00', 'Available', 6005.00),
(662, 2, 5, 'Friday', '13:00:00', '14:00:00', 'Available', 6005.00),
(663, 2, 5, 'Friday', '14:00:00', '15:00:00', 'Available', 6005.00),
(664, 2, 5, 'Friday', '15:00:00', '16:00:00', 'Available', 6005.00),
(665, 2, 5, 'Friday', '16:00:00', '17:00:00', 'Available', 6005.00),
(666, 2, 5, 'Friday', '17:00:00', '18:00:00', 'Available', 6005.00),
(667, 2, 5, 'Friday', '18:00:00', '19:00:00', 'Available', 6005.00),
(668, 2, 5, 'Friday', '19:00:00', '20:00:00', 'Available', 6005.00),
(669, 2, 5, 'Friday', '20:00:00', '21:00:00', 'Available', 6005.00),
(670, 2, 5, 'Friday', '21:00:00', '22:00:00', 'Available', 6005.00),
(671, 2, 5, 'Friday', '22:00:00', '23:00:00', 'Available', 6005.00),
(672, 2, 5, 'Friday', '23:00:00', '00:00:00', 'Available', 6005.00),
(673, 2, 6, 'Saturday', '08:00:00', '09:00:00', 'Available', 6005.00),
(674, 2, 6, 'Saturday', '09:00:00', '10:00:00', 'Available', 6005.00),
(675, 2, 6, 'Saturday', '10:00:00', '11:00:00', 'Available', 6005.00),
(676, 2, 6, 'Saturday', '11:00:00', '12:00:00', 'Available', 6005.00),
(677, 2, 6, 'Saturday', '12:00:00', '13:00:00', 'Available', 6005.00),
(678, 2, 6, 'Saturday', '13:00:00', '14:00:00', 'Available', 6005.00),
(679, 2, 6, 'Saturday', '14:00:00', '15:00:00', 'Available', 6005.00),
(680, 2, 6, 'Saturday', '15:00:00', '16:00:00', 'Available', 6005.00),
(681, 2, 6, 'Saturday', '16:00:00', '17:00:00', 'Available', 6005.00),
(682, 2, 6, 'Saturday', '17:00:00', '18:00:00', 'Available', 6005.00),
(683, 2, 6, 'Saturday', '18:00:00', '19:00:00', 'Available', 6005.00),
(684, 2, 6, 'Saturday', '19:00:00', '20:00:00', 'Available', 6005.00),
(685, 2, 6, 'Saturday', '20:00:00', '21:00:00', 'Available', 6005.00),
(686, 2, 6, 'Saturday', '21:00:00', '22:00:00', 'Available', 6005.00),
(687, 2, 6, 'Saturday', '22:00:00', '23:00:00', 'Available', 6005.00),
(688, 2, 6, 'Saturday', '23:00:00', '00:00:00', 'Available', 6005.00),
(689, 2, 6, 'Sunday', '08:00:00', '09:00:00', 'Available', 6005.00),
(690, 2, 6, 'Sunday', '09:00:00', '10:00:00', 'Available', 6005.00),
(691, 2, 6, 'Sunday', '10:00:00', '11:00:00', 'Available', 6005.00),
(692, 2, 6, 'Sunday', '11:00:00', '12:00:00', 'Available', 6005.00),
(693, 2, 6, 'Sunday', '12:00:00', '13:00:00', 'Available', 6005.00),
(694, 2, 6, 'Sunday', '13:00:00', '14:00:00', 'Available', 6005.00),
(695, 2, 6, 'Sunday', '14:00:00', '15:00:00', 'Available', 6005.00),
(696, 2, 6, 'Sunday', '15:00:00', '16:00:00', 'Available', 6005.00),
(697, 2, 6, 'Sunday', '16:00:00', '17:00:00', 'Available', 6005.00),
(698, 2, 6, 'Sunday', '17:00:00', '18:00:00', 'Available', 6005.00),
(699, 2, 6, 'Sunday', '18:00:00', '19:00:00', 'Available', 6005.00),
(700, 2, 6, 'Sunday', '19:00:00', '20:00:00', 'Available', 6005.00),
(701, 2, 6, 'Sunday', '20:00:00', '21:00:00', 'Available', 6005.00),
(702, 2, 6, 'Sunday', '21:00:00', '22:00:00', 'Available', 6005.00),
(703, 2, 6, 'Sunday', '22:00:00', '23:00:00', 'Available', 6005.00),
(704, 2, 6, 'Sunday', '23:00:00', '00:00:00', 'Available', 6005.00),
(705, 2, 6, 'Monday', '08:00:00', '09:00:00', 'Available', 6005.00),
(706, 2, 6, 'Monday', '09:00:00', '10:00:00', 'Available', 6005.00),
(707, 2, 6, 'Monday', '10:00:00', '11:00:00', 'Available', 6005.00),
(708, 2, 6, 'Monday', '11:00:00', '12:00:00', 'Available', 6005.00),
(709, 2, 6, 'Monday', '12:00:00', '13:00:00', 'Available', 6005.00),
(710, 2, 6, 'Monday', '13:00:00', '14:00:00', 'Available', 6005.00),
(711, 2, 6, 'Monday', '14:00:00', '15:00:00', 'Available', 6005.00),
(712, 2, 6, 'Monday', '15:00:00', '16:00:00', 'Available', 6005.00),
(713, 2, 6, 'Monday', '16:00:00', '17:00:00', 'Available', 6005.00),
(714, 2, 6, 'Monday', '17:00:00', '18:00:00', 'Available', 6005.00),
(715, 2, 6, 'Monday', '18:00:00', '19:00:00', 'Available', 6005.00),
(716, 2, 6, 'Monday', '19:00:00', '20:00:00', 'Available', 6005.00),
(717, 2, 6, 'Monday', '20:00:00', '21:00:00', 'Available', 6005.00),
(718, 2, 6, 'Monday', '21:00:00', '22:00:00', 'Available', 6005.00),
(719, 2, 6, 'Monday', '22:00:00', '23:00:00', 'Available', 6005.00),
(720, 2, 6, 'Monday', '23:00:00', '00:00:00', 'Available', 6005.00),
(721, 2, 6, 'Tuesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(722, 2, 6, 'Tuesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(723, 2, 6, 'Tuesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(724, 2, 6, 'Tuesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(725, 2, 6, 'Tuesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(726, 2, 6, 'Tuesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(727, 2, 6, 'Tuesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(728, 2, 6, 'Tuesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(729, 2, 6, 'Tuesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(730, 2, 6, 'Tuesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(731, 2, 6, 'Tuesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(732, 2, 6, 'Tuesday', '19:00:00', '20:00:00', 'Available', 6005.00);
INSERT INTO `venuetimeslot` (`SlotID`, `VenueID`, `Week`, `DayOfWeek`, `StartTime`, `EndTime`, `Status`, `Price`) VALUES
(733, 2, 6, 'Tuesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(734, 2, 6, 'Tuesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(735, 2, 6, 'Tuesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(736, 2, 6, 'Tuesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(737, 2, 6, 'Wednesday', '08:00:00', '09:00:00', 'Available', 6005.00),
(738, 2, 6, 'Wednesday', '09:00:00', '10:00:00', 'Available', 6005.00),
(739, 2, 6, 'Wednesday', '10:00:00', '11:00:00', 'Available', 6005.00),
(740, 2, 6, 'Wednesday', '11:00:00', '12:00:00', 'Available', 6005.00),
(741, 2, 6, 'Wednesday', '12:00:00', '13:00:00', 'Available', 6005.00),
(742, 2, 6, 'Wednesday', '13:00:00', '14:00:00', 'Available', 6005.00),
(743, 2, 6, 'Wednesday', '14:00:00', '15:00:00', 'Available', 6005.00),
(744, 2, 6, 'Wednesday', '15:00:00', '16:00:00', 'Available', 6005.00),
(745, 2, 6, 'Wednesday', '16:00:00', '17:00:00', 'Available', 6005.00),
(746, 2, 6, 'Wednesday', '17:00:00', '18:00:00', 'Available', 6005.00),
(747, 2, 6, 'Wednesday', '18:00:00', '19:00:00', 'Available', 6005.00),
(748, 2, 6, 'Wednesday', '19:00:00', '20:00:00', 'Available', 6005.00),
(749, 2, 6, 'Wednesday', '20:00:00', '21:00:00', 'Available', 6005.00),
(750, 2, 6, 'Wednesday', '21:00:00', '22:00:00', 'Available', 6005.00),
(751, 2, 6, 'Wednesday', '22:00:00', '23:00:00', 'Available', 6005.00),
(752, 2, 6, 'Wednesday', '23:00:00', '00:00:00', 'Available', 6005.00),
(753, 2, 6, 'Thursday', '08:00:00', '09:00:00', 'Available', 6005.00),
(754, 2, 6, 'Thursday', '09:00:00', '10:00:00', 'Available', 6005.00),
(755, 2, 6, 'Thursday', '10:00:00', '11:00:00', 'Available', 6005.00),
(756, 2, 6, 'Thursday', '11:00:00', '12:00:00', 'Available', 6005.00),
(757, 2, 6, 'Thursday', '12:00:00', '13:00:00', 'Available', 6005.00),
(758, 2, 6, 'Thursday', '13:00:00', '14:00:00', 'Available', 6005.00),
(759, 2, 6, 'Thursday', '14:00:00', '15:00:00', 'Available', 6005.00),
(760, 2, 6, 'Thursday', '15:00:00', '16:00:00', 'Available', 6005.00),
(761, 2, 6, 'Thursday', '16:00:00', '17:00:00', 'Available', 6005.00),
(762, 2, 6, 'Thursday', '17:00:00', '18:00:00', 'Available', 6005.00),
(763, 2, 6, 'Thursday', '18:00:00', '19:00:00', 'Available', 6005.00),
(764, 2, 6, 'Thursday', '19:00:00', '20:00:00', 'Available', 6005.00),
(765, 2, 6, 'Thursday', '20:00:00', '21:00:00', 'Available', 6005.00),
(766, 2, 6, 'Thursday', '21:00:00', '22:00:00', 'Available', 6005.00),
(767, 2, 6, 'Thursday', '22:00:00', '23:00:00', 'Available', 6005.00),
(768, 2, 6, 'Thursday', '23:00:00', '00:00:00', 'Available', 6005.00),
(769, 2, 6, 'Friday', '08:00:00', '09:00:00', 'Available', 6005.00),
(770, 2, 6, 'Friday', '09:00:00', '10:00:00', 'Available', 6005.00),
(771, 2, 6, 'Friday', '10:00:00', '11:00:00', 'Available', 6005.00),
(772, 2, 6, 'Friday', '11:00:00', '12:00:00', 'Available', 6005.00),
(773, 2, 6, 'Friday', '12:00:00', '13:00:00', 'Available', 6005.00),
(774, 2, 6, 'Friday', '13:00:00', '14:00:00', 'Available', 6005.00),
(775, 2, 6, 'Friday', '14:00:00', '15:00:00', 'Available', 6005.00),
(776, 2, 6, 'Friday', '15:00:00', '16:00:00', 'Available', 6005.00),
(777, 2, 6, 'Friday', '16:00:00', '17:00:00', 'Available', 6005.00),
(778, 2, 6, 'Friday', '17:00:00', '18:00:00', 'Available', 6005.00),
(779, 2, 6, 'Friday', '18:00:00', '19:00:00', 'Available', 6005.00),
(780, 2, 6, 'Friday', '19:00:00', '20:00:00', 'Available', 6005.00),
(781, 2, 6, 'Friday', '20:00:00', '21:00:00', 'Available', 6005.00),
(782, 2, 6, 'Friday', '21:00:00', '22:00:00', 'Available', 6005.00),
(783, 2, 6, 'Friday', '22:00:00', '23:00:00', 'Available', 6005.00),
(784, 2, 6, 'Friday', '23:00:00', '00:00:00', 'Available', 6005.00),
(912, 3, 1, 'Monday', '08:00:00', '09:00:00', 'Available', 2200.00),
(913, 3, 1, 'Monday', '09:00:00', '10:00:00', 'Available', 2200.00),
(914, 3, 1, 'Monday', '10:00:00', '11:00:00', 'Available', 2200.00),
(915, 3, 1, 'Monday', '11:00:00', '12:00:00', 'Available', 2200.00),
(916, 3, 1, 'Monday', '12:00:00', '13:00:00', 'Available', 2200.00),
(917, 3, 1, 'Monday', '13:00:00', '14:00:00', 'Available', 2200.00),
(918, 3, 1, 'Monday', '14:00:00', '15:00:00', 'Available', 2200.00),
(919, 3, 1, 'Monday', '15:00:00', '16:00:00', 'Available', 2200.00),
(920, 3, 1, 'Monday', '16:00:00', '17:00:00', 'Available', 2200.00),
(921, 3, 1, 'Monday', '17:00:00', '18:00:00', 'Available', 2200.00),
(922, 3, 1, 'Monday', '18:00:00', '19:00:00', 'Available', 2200.00),
(923, 3, 1, 'Monday', '19:00:00', '20:00:00', 'Available', 2200.00),
(924, 3, 1, 'Monday', '20:00:00', '21:00:00', 'Available', 2200.00),
(925, 3, 1, 'Monday', '21:00:00', '22:00:00', 'Available', 2200.00),
(926, 3, 1, 'Monday', '22:00:00', '23:00:00', 'Available', 2200.00),
(927, 3, 1, 'Monday', '23:00:00', '00:00:00', 'Available', 2200.00),
(928, 3, 1, 'Tuesday', '08:00:00', '09:00:00', 'Available', 2200.00),
(929, 3, 1, 'Tuesday', '09:00:00', '10:00:00', 'Available', 2200.00),
(930, 3, 1, 'Tuesday', '10:00:00', '11:00:00', 'Available', 2200.00),
(931, 3, 1, 'Tuesday', '11:00:00', '12:00:00', 'Available', 2200.00),
(932, 3, 1, 'Tuesday', '12:00:00', '13:00:00', 'Available', 2200.00),
(933, 3, 1, 'Tuesday', '13:00:00', '14:00:00', 'Available', 2200.00),
(934, 3, 1, 'Tuesday', '14:00:00', '15:00:00', 'Available', 2200.00),
(935, 3, 1, 'Tuesday', '15:00:00', '16:00:00', 'Available', 2200.00),
(936, 3, 1, 'Tuesday', '16:00:00', '17:00:00', 'Available', 2200.00),
(937, 3, 1, 'Tuesday', '17:00:00', '18:00:00', 'Available', 2200.00),
(938, 3, 1, 'Tuesday', '18:00:00', '19:00:00', 'Available', 2200.00),
(939, 3, 1, 'Tuesday', '19:00:00', '20:00:00', 'Available', 2200.00),
(940, 3, 1, 'Tuesday', '20:00:00', '21:00:00', 'Available', 2200.00),
(941, 3, 1, 'Tuesday', '21:00:00', '22:00:00', 'Available', 2200.00),
(942, 3, 1, 'Tuesday', '22:00:00', '23:00:00', 'Available', 2200.00),
(943, 3, 1, 'Tuesday', '23:00:00', '00:00:00', 'Available', 2200.00),
(944, 3, 1, 'Wednesday', '08:00:00', '09:00:00', 'Available', 2200.00),
(945, 3, 1, 'Wednesday', '09:00:00', '10:00:00', 'Available', 2200.00),
(946, 3, 1, 'Wednesday', '10:00:00', '11:00:00', 'Available', 2200.00),
(947, 3, 1, 'Wednesday', '11:00:00', '12:00:00', 'Available', 2200.00),
(948, 3, 1, 'Wednesday', '12:00:00', '13:00:00', 'Available', 2200.00),
(949, 3, 1, 'Wednesday', '13:00:00', '14:00:00', 'Available', 2200.00),
(950, 3, 1, 'Wednesday', '14:00:00', '15:00:00', 'Available', 2200.00),
(951, 3, 1, 'Wednesday', '15:00:00', '16:00:00', 'Available', 2200.00),
(952, 3, 1, 'Wednesday', '16:00:00', '17:00:00', 'Available', 2200.00),
(953, 3, 1, 'Wednesday', '17:00:00', '18:00:00', 'Available', 2200.00),
(954, 3, 1, 'Wednesday', '18:00:00', '19:00:00', 'Available', 2200.00),
(955, 3, 1, 'Wednesday', '19:00:00', '20:00:00', 'Available', 2200.00),
(956, 3, 1, 'Wednesday', '20:00:00', '21:00:00', 'Available', 2200.00),
(957, 3, 1, 'Wednesday', '21:00:00', '22:00:00', 'Available', 2200.00),
(958, 3, 1, 'Wednesday', '22:00:00', '23:00:00', 'Available', 2200.00),
(959, 3, 1, 'Wednesday', '23:00:00', '00:00:00', 'Available', 2200.00),
(960, 3, 1, 'Thursday', '08:00:00', '09:00:00', 'Available', 2200.00),
(961, 3, 1, 'Thursday', '09:00:00', '10:00:00', 'Available', 2200.00),
(962, 3, 1, 'Thursday', '10:00:00', '11:00:00', 'Available', 2200.00),
(963, 3, 1, 'Thursday', '11:00:00', '12:00:00', 'Available', 2200.00),
(964, 3, 1, 'Thursday', '12:00:00', '13:00:00', 'Available', 2200.00),
(965, 3, 1, 'Thursday', '13:00:00', '14:00:00', 'Available', 2200.00),
(966, 3, 1, 'Thursday', '14:00:00', '15:00:00', 'Available', 2200.00),
(967, 3, 1, 'Thursday', '15:00:00', '16:00:00', 'Available', 2200.00),
(968, 3, 1, 'Thursday', '16:00:00', '17:00:00', 'Available', 2200.00),
(969, 3, 1, 'Thursday', '17:00:00', '18:00:00', 'Available', 2200.00),
(970, 3, 1, 'Thursday', '18:00:00', '19:00:00', 'Available', 2200.00),
(971, 3, 1, 'Thursday', '19:00:00', '20:00:00', 'Available', 2200.00),
(972, 3, 1, 'Thursday', '20:00:00', '21:00:00', 'Available', 2200.00),
(973, 3, 1, 'Thursday', '21:00:00', '22:00:00', 'Available', 2200.00),
(974, 3, 1, 'Thursday', '22:00:00', '23:00:00', 'Available', 2200.00),
(975, 3, 1, 'Thursday', '23:00:00', '00:00:00', 'Available', 2200.00),
(976, 3, 1, 'Friday', '08:00:00', '09:00:00', 'Available', 2200.00),
(977, 3, 1, 'Friday', '09:00:00', '10:00:00', 'Available', 2200.00),
(978, 3, 1, 'Friday', '10:00:00', '11:00:00', 'Available', 2200.00),
(979, 3, 1, 'Friday', '11:00:00', '12:00:00', 'Available', 2200.00),
(980, 3, 1, 'Friday', '12:00:00', '13:00:00', 'Available', 2200.00),
(981, 3, 1, 'Friday', '13:00:00', '14:00:00', 'Available', 2200.00),
(982, 3, 1, 'Friday', '14:00:00', '15:00:00', 'Available', 2200.00),
(983, 3, 1, 'Friday', '15:00:00', '16:00:00', 'Available', 2200.00),
(984, 3, 1, 'Friday', '16:00:00', '17:00:00', 'Available', 2200.00),
(985, 3, 1, 'Friday', '17:00:00', '18:00:00', 'Available', 2200.00),
(986, 3, 1, 'Friday', '18:00:00', '19:00:00', 'Available', 2200.00),
(987, 3, 1, 'Friday', '19:00:00', '20:00:00', 'Available', 2200.00),
(988, 3, 1, 'Friday', '20:00:00', '21:00:00', 'Available', 2200.00),
(989, 3, 1, 'Friday', '21:00:00', '22:00:00', 'Available', 2200.00),
(990, 3, 1, 'Friday', '22:00:00', '23:00:00', 'Available', 2200.00),
(991, 3, 1, 'Friday', '23:00:00', '00:00:00', 'Available', 2200.00),
(992, 3, 1, 'Saturday', '08:00:00', '09:00:00', 'Available', 2200.00),
(993, 3, 1, 'Saturday', '09:00:00', '10:00:00', 'Available', 2200.00),
(994, 3, 1, 'Saturday', '10:00:00', '11:00:00', 'Available', 2200.00),
(995, 3, 1, 'Saturday', '11:00:00', '12:00:00', 'Available', 2200.00),
(996, 3, 1, 'Saturday', '12:00:00', '13:00:00', 'Available', 2200.00),
(997, 3, 1, 'Saturday', '13:00:00', '14:00:00', 'Available', 2200.00),
(998, 3, 1, 'Saturday', '14:00:00', '15:00:00', 'Available', 2200.00),
(999, 3, 1, 'Saturday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1000, 3, 1, 'Saturday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1001, 3, 1, 'Saturday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1002, 3, 1, 'Saturday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1003, 3, 1, 'Saturday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1004, 3, 1, 'Saturday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1005, 3, 1, 'Saturday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1006, 3, 1, 'Saturday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1007, 3, 1, 'Saturday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1008, 3, 1, 'Sunday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1009, 3, 1, 'Sunday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1010, 3, 1, 'Sunday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1011, 3, 1, 'Sunday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1012, 3, 1, 'Sunday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1013, 3, 1, 'Sunday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1014, 3, 1, 'Sunday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1015, 3, 1, 'Sunday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1016, 3, 1, 'Sunday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1017, 3, 1, 'Sunday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1018, 3, 1, 'Sunday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1019, 3, 1, 'Sunday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1020, 3, 1, 'Sunday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1021, 3, 1, 'Sunday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1022, 3, 1, 'Sunday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1023, 3, 1, 'Sunday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1024, 3, 2, 'Monday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1025, 3, 2, 'Monday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1026, 3, 2, 'Monday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1027, 3, 2, 'Monday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1028, 3, 2, 'Monday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1029, 3, 2, 'Monday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1030, 3, 2, 'Monday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1031, 3, 2, 'Monday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1032, 3, 2, 'Monday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1033, 3, 2, 'Monday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1034, 3, 2, 'Monday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1035, 3, 2, 'Monday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1036, 3, 2, 'Monday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1037, 3, 2, 'Monday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1038, 3, 2, 'Monday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1039, 3, 2, 'Monday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1040, 3, 2, 'Tuesday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1041, 3, 2, 'Tuesday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1042, 3, 2, 'Tuesday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1043, 3, 2, 'Tuesday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1044, 3, 2, 'Tuesday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1045, 3, 2, 'Tuesday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1046, 3, 2, 'Tuesday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1047, 3, 2, 'Tuesday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1048, 3, 2, 'Tuesday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1049, 3, 2, 'Tuesday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1050, 3, 2, 'Tuesday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1051, 3, 2, 'Tuesday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1052, 3, 2, 'Tuesday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1053, 3, 2, 'Tuesday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1054, 3, 2, 'Tuesday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1055, 3, 2, 'Tuesday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1056, 3, 2, 'Wednesday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1057, 3, 2, 'Wednesday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1058, 3, 2, 'Wednesday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1059, 3, 2, 'Wednesday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1060, 3, 2, 'Wednesday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1061, 3, 2, 'Wednesday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1062, 3, 2, 'Wednesday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1063, 3, 2, 'Wednesday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1064, 3, 2, 'Wednesday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1065, 3, 2, 'Wednesday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1066, 3, 2, 'Wednesday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1067, 3, 2, 'Wednesday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1068, 3, 2, 'Wednesday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1069, 3, 2, 'Wednesday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1070, 3, 2, 'Wednesday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1071, 3, 2, 'Wednesday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1072, 3, 2, 'Thursday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1073, 3, 2, 'Thursday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1074, 3, 2, 'Thursday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1075, 3, 2, 'Thursday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1076, 3, 2, 'Thursday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1077, 3, 2, 'Thursday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1078, 3, 2, 'Thursday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1079, 3, 2, 'Thursday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1080, 3, 2, 'Thursday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1081, 3, 2, 'Thursday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1082, 3, 2, 'Thursday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1083, 3, 2, 'Thursday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1084, 3, 2, 'Thursday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1085, 3, 2, 'Thursday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1086, 3, 2, 'Thursday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1087, 3, 2, 'Thursday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1088, 3, 2, 'Friday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1089, 3, 2, 'Friday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1090, 3, 2, 'Friday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1091, 3, 2, 'Friday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1092, 3, 2, 'Friday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1093, 3, 2, 'Friday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1094, 3, 2, 'Friday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1095, 3, 2, 'Friday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1096, 3, 2, 'Friday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1097, 3, 2, 'Friday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1098, 3, 2, 'Friday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1099, 3, 2, 'Friday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1100, 3, 2, 'Friday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1101, 3, 2, 'Friday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1102, 3, 2, 'Friday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1103, 3, 2, 'Friday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1104, 3, 2, 'Saturday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1105, 3, 2, 'Saturday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1106, 3, 2, 'Saturday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1107, 3, 2, 'Saturday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1108, 3, 2, 'Saturday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1109, 3, 2, 'Saturday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1110, 3, 2, 'Saturday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1111, 3, 2, 'Saturday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1112, 3, 2, 'Saturday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1113, 3, 2, 'Saturday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1114, 3, 2, 'Saturday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1115, 3, 2, 'Saturday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1116, 3, 2, 'Saturday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1117, 3, 2, 'Saturday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1118, 3, 2, 'Saturday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1119, 3, 2, 'Saturday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1120, 3, 2, 'Sunday', '08:00:00', '09:00:00', 'Available', 2200.00),
(1121, 3, 2, 'Sunday', '09:00:00', '10:00:00', 'Available', 2200.00),
(1122, 3, 2, 'Sunday', '10:00:00', '11:00:00', 'Available', 2200.00),
(1123, 3, 2, 'Sunday', '11:00:00', '12:00:00', 'Available', 2200.00),
(1124, 3, 2, 'Sunday', '12:00:00', '13:00:00', 'Available', 2200.00),
(1125, 3, 2, 'Sunday', '13:00:00', '14:00:00', 'Available', 2200.00),
(1126, 3, 2, 'Sunday', '14:00:00', '15:00:00', 'Available', 2200.00),
(1127, 3, 2, 'Sunday', '15:00:00', '16:00:00', 'Available', 2200.00),
(1128, 3, 2, 'Sunday', '16:00:00', '17:00:00', 'Available', 2200.00),
(1129, 3, 2, 'Sunday', '17:00:00', '18:00:00', 'Available', 2200.00),
(1130, 3, 2, 'Sunday', '18:00:00', '19:00:00', 'Available', 2200.00),
(1131, 3, 2, 'Sunday', '19:00:00', '20:00:00', 'Available', 2200.00),
(1132, 3, 2, 'Sunday', '20:00:00', '21:00:00', 'Available', 2200.00),
(1133, 3, 2, 'Sunday', '21:00:00', '22:00:00', 'Available', 2200.00),
(1134, 3, 2, 'Sunday', '22:00:00', '23:00:00', 'Available', 2200.00),
(1135, 3, 2, 'Sunday', '23:00:00', '00:00:00', 'Available', 2200.00),
(1136, 4, 1, 'Monday', '08:00:00', '09:00:00', 'Available', 122.00),
(1137, 4, 1, 'Monday', '09:00:00', '10:00:00', 'Available', 122.00),
(1138, 4, 1, 'Monday', '10:00:00', '11:00:00', 'Available', 122.00),
(1139, 4, 1, 'Monday', '11:00:00', '12:00:00', 'Available', 122.00),
(1140, 4, 1, 'Monday', '12:00:00', '13:00:00', 'Available', 122.00),
(1141, 4, 1, 'Monday', '13:00:00', '14:00:00', 'Available', 122.00),
(1142, 4, 1, 'Monday', '14:00:00', '15:00:00', 'Available', 122.00),
(1143, 4, 1, 'Monday', '15:00:00', '16:00:00', 'Available', 122.00),
(1144, 4, 1, 'Monday', '16:00:00', '17:00:00', 'Available', 122.00),
(1145, 4, 1, 'Monday', '17:00:00', '18:00:00', 'Available', 122.00),
(1146, 4, 1, 'Monday', '18:00:00', '19:00:00', 'Available', 122.00),
(1147, 4, 1, 'Monday', '19:00:00', '20:00:00', 'Available', 122.00),
(1148, 4, 1, 'Monday', '20:00:00', '21:00:00', 'Available', 122.00),
(1149, 4, 1, 'Monday', '21:00:00', '22:00:00', 'Available', 122.00),
(1150, 4, 1, 'Monday', '22:00:00', '23:00:00', 'Available', 122.00),
(1151, 4, 1, 'Monday', '23:00:00', '00:00:00', 'Available', 122.00),
(1152, 4, 1, 'Tuesday', '08:00:00', '09:00:00', 'Available', 122.00),
(1153, 4, 1, 'Tuesday', '09:00:00', '10:00:00', 'Available', 122.00),
(1154, 4, 1, 'Tuesday', '10:00:00', '11:00:00', 'Available', 122.00),
(1155, 4, 1, 'Tuesday', '11:00:00', '12:00:00', 'Available', 122.00),
(1156, 4, 1, 'Tuesday', '12:00:00', '13:00:00', 'Available', 122.00),
(1157, 4, 1, 'Tuesday', '13:00:00', '14:00:00', 'Available', 122.00),
(1158, 4, 1, 'Tuesday', '14:00:00', '15:00:00', 'Available', 122.00),
(1159, 4, 1, 'Tuesday', '15:00:00', '16:00:00', 'Available', 122.00),
(1160, 4, 1, 'Tuesday', '16:00:00', '17:00:00', 'Available', 122.00),
(1161, 4, 1, 'Tuesday', '17:00:00', '18:00:00', 'Available', 122.00),
(1162, 4, 1, 'Tuesday', '18:00:00', '19:00:00', 'Available', 122.00),
(1163, 4, 1, 'Tuesday', '19:00:00', '20:00:00', 'Available', 122.00),
(1164, 4, 1, 'Tuesday', '20:00:00', '21:00:00', 'Available', 122.00),
(1165, 4, 1, 'Tuesday', '21:00:00', '22:00:00', 'Available', 122.00),
(1166, 4, 1, 'Tuesday', '22:00:00', '23:00:00', 'Available', 122.00),
(1167, 4, 1, 'Tuesday', '23:00:00', '00:00:00', 'Available', 122.00),
(1168, 4, 1, 'Wednesday', '08:00:00', '09:00:00', 'Available', 122.00),
(1169, 4, 1, 'Wednesday', '09:00:00', '10:00:00', 'Available', 122.00),
(1170, 4, 1, 'Wednesday', '10:00:00', '11:00:00', 'Available', 122.00),
(1171, 4, 1, 'Wednesday', '11:00:00', '12:00:00', 'Available', 122.00),
(1172, 4, 1, 'Wednesday', '12:00:00', '13:00:00', 'Available', 122.00),
(1173, 4, 1, 'Wednesday', '13:00:00', '14:00:00', 'Available', 122.00),
(1174, 4, 1, 'Wednesday', '14:00:00', '15:00:00', 'Available', 122.00),
(1175, 4, 1, 'Wednesday', '15:00:00', '16:00:00', 'Available', 122.00),
(1176, 4, 1, 'Wednesday', '16:00:00', '17:00:00', 'Available', 122.00),
(1177, 4, 1, 'Wednesday', '17:00:00', '18:00:00', 'Available', 122.00),
(1178, 4, 1, 'Wednesday', '18:00:00', '19:00:00', 'Available', 122.00),
(1179, 4, 1, 'Wednesday', '19:00:00', '20:00:00', 'Available', 122.00),
(1180, 4, 1, 'Wednesday', '20:00:00', '21:00:00', 'Available', 122.00),
(1181, 4, 1, 'Wednesday', '21:00:00', '22:00:00', 'Available', 122.00),
(1182, 4, 1, 'Wednesday', '22:00:00', '23:00:00', 'Available', 122.00),
(1183, 4, 1, 'Wednesday', '23:00:00', '00:00:00', 'Available', 122.00),
(1184, 4, 1, 'Thursday', '08:00:00', '09:00:00', 'Available', 122.00),
(1185, 4, 1, 'Thursday', '09:00:00', '10:00:00', 'Available', 122.00),
(1186, 4, 1, 'Thursday', '10:00:00', '11:00:00', 'Available', 122.00),
(1187, 4, 1, 'Thursday', '11:00:00', '12:00:00', 'Available', 122.00),
(1188, 4, 1, 'Thursday', '12:00:00', '13:00:00', 'Available', 122.00),
(1189, 4, 1, 'Thursday', '13:00:00', '14:00:00', 'Available', 122.00),
(1190, 4, 1, 'Thursday', '14:00:00', '15:00:00', 'Available', 122.00),
(1191, 4, 1, 'Thursday', '15:00:00', '16:00:00', 'Available', 122.00),
(1192, 4, 1, 'Thursday', '16:00:00', '17:00:00', 'Available', 122.00),
(1193, 4, 1, 'Thursday', '17:00:00', '18:00:00', 'Available', 122.00),
(1194, 4, 1, 'Thursday', '18:00:00', '19:00:00', 'Available', 122.00),
(1195, 4, 1, 'Thursday', '19:00:00', '20:00:00', 'Available', 122.00),
(1196, 4, 1, 'Thursday', '20:00:00', '21:00:00', 'Available', 122.00),
(1197, 4, 1, 'Thursday', '21:00:00', '22:00:00', 'Available', 122.00),
(1198, 4, 1, 'Thursday', '22:00:00', '23:00:00', 'Available', 122.00),
(1199, 4, 1, 'Thursday', '23:00:00', '00:00:00', 'Available', 122.00),
(1200, 4, 1, 'Friday', '08:00:00', '09:00:00', 'Available', 122.00),
(1201, 4, 1, 'Friday', '09:00:00', '10:00:00', 'Available', 122.00),
(1202, 4, 1, 'Friday', '10:00:00', '11:00:00', 'Available', 122.00),
(1203, 4, 1, 'Friday', '11:00:00', '12:00:00', 'Available', 122.00),
(1204, 4, 1, 'Friday', '12:00:00', '13:00:00', 'Available', 122.00),
(1205, 4, 1, 'Friday', '13:00:00', '14:00:00', 'Available', 122.00),
(1206, 4, 1, 'Friday', '14:00:00', '15:00:00', 'Available', 122.00),
(1207, 4, 1, 'Friday', '15:00:00', '16:00:00', 'Available', 122.00),
(1208, 4, 1, 'Friday', '16:00:00', '17:00:00', 'Available', 122.00),
(1209, 4, 1, 'Friday', '17:00:00', '18:00:00', 'Available', 122.00),
(1210, 4, 1, 'Friday', '18:00:00', '19:00:00', 'Available', 122.00),
(1211, 4, 1, 'Friday', '19:00:00', '20:00:00', 'Available', 122.00),
(1212, 4, 1, 'Friday', '20:00:00', '21:00:00', 'Available', 122.00),
(1213, 4, 1, 'Friday', '21:00:00', '22:00:00', 'Available', 122.00),
(1214, 4, 1, 'Friday', '22:00:00', '23:00:00', 'Available', 122.00),
(1215, 4, 1, 'Friday', '23:00:00', '00:00:00', 'Available', 122.00),
(1216, 4, 1, 'Saturday', '08:00:00', '09:00:00', 'Available', 122.00),
(1217, 4, 1, 'Saturday', '09:00:00', '10:00:00', 'Available', 122.00),
(1218, 4, 1, 'Saturday', '10:00:00', '11:00:00', 'Available', 122.00),
(1219, 4, 1, 'Saturday', '11:00:00', '12:00:00', 'Available', 122.00),
(1220, 4, 1, 'Saturday', '12:00:00', '13:00:00', 'Available', 122.00),
(1221, 4, 1, 'Saturday', '13:00:00', '14:00:00', 'Available', 122.00),
(1222, 4, 1, 'Saturday', '14:00:00', '15:00:00', 'Available', 122.00),
(1223, 4, 1, 'Saturday', '15:00:00', '16:00:00', 'Available', 122.00),
(1224, 4, 1, 'Saturday', '16:00:00', '17:00:00', 'Available', 122.00),
(1225, 4, 1, 'Saturday', '17:00:00', '18:00:00', 'Available', 122.00),
(1226, 4, 1, 'Saturday', '18:00:00', '19:00:00', 'Available', 122.00),
(1227, 4, 1, 'Saturday', '19:00:00', '20:00:00', 'Available', 122.00),
(1228, 4, 1, 'Saturday', '20:00:00', '21:00:00', 'Available', 122.00),
(1229, 4, 1, 'Saturday', '21:00:00', '22:00:00', 'Available', 122.00),
(1230, 4, 1, 'Saturday', '22:00:00', '23:00:00', 'Available', 122.00),
(1231, 4, 1, 'Saturday', '23:00:00', '00:00:00', 'Available', 122.00),
(1232, 4, 1, 'Sunday', '08:00:00', '09:00:00', 'Available', 122.00),
(1233, 4, 1, 'Sunday', '09:00:00', '10:00:00', 'Available', 122.00),
(1234, 4, 1, 'Sunday', '10:00:00', '11:00:00', 'Available', 122.00),
(1235, 4, 1, 'Sunday', '11:00:00', '12:00:00', 'Available', 122.00),
(1236, 4, 1, 'Sunday', '12:00:00', '13:00:00', 'Available', 122.00),
(1237, 4, 1, 'Sunday', '13:00:00', '14:00:00', 'Available', 122.00),
(1238, 4, 1, 'Sunday', '14:00:00', '15:00:00', 'Available', 122.00),
(1239, 4, 1, 'Sunday', '15:00:00', '16:00:00', 'Available', 122.00),
(1240, 4, 1, 'Sunday', '16:00:00', '17:00:00', 'Available', 122.00),
(1241, 4, 1, 'Sunday', '17:00:00', '18:00:00', 'Available', 122.00),
(1242, 4, 1, 'Sunday', '18:00:00', '19:00:00', 'Available', 122.00),
(1243, 4, 1, 'Sunday', '19:00:00', '20:00:00', 'Available', 122.00),
(1244, 4, 1, 'Sunday', '20:00:00', '21:00:00', 'Available', 122.00),
(1245, 4, 1, 'Sunday', '21:00:00', '22:00:00', 'Available', 122.00),
(1246, 4, 1, 'Sunday', '22:00:00', '23:00:00', 'Available', 122.00),
(1247, 4, 1, 'Sunday', '23:00:00', '00:00:00', 'Available', 122.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`BookingID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `VenueID` (`VenueID`),
  ADD KEY `SlotID` (`SlotID`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `BookingID` (`BookingID`);

--
-- Indexes for table `promotionrequest`
--
ALTER TABLE `promotionrequest`
  ADD PRIMARY KEY (`RequestID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`RoleID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD KEY `RoleID` (`RoleID`),
  ADD KEY `fk_manager` (`ManagerID`),
  ADD KEY `fk_user_venue` (`VenueID`);

--
-- Indexes for table `venue`
--
ALTER TABLE `venue`
  ADD PRIMARY KEY (`VenueID`),
  ADD KEY `ManagerID` (`ManagerID`);

--
-- Indexes for table `venueimages`
--
ALTER TABLE `venueimages`
  ADD PRIMARY KEY (`ImageID`),
  ADD KEY `VenueID` (`VenueID`);

--
-- Indexes for table `venuesports`
--
ALTER TABLE `venuesports`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `unique_venue_sport` (`VenueID`,`SportType`);

--
-- Indexes for table `venuestaffassignment`
--
ALTER TABLE `venuestaffassignment`
  ADD PRIMARY KEY (`AssignmentID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `VenueID` (`VenueID`),
  ADD KEY `ManagerID` (`ManagerID`);

--
-- Indexes for table `venuetimeslot`
--
ALTER TABLE `venuetimeslot`
  ADD PRIMARY KEY (`SlotID`),
  ADD KEY `VenueID` (`VenueID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `BookingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `promotionrequest`
--
ALTER TABLE `promotionrequest`
  MODIFY `RequestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `RoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `venue`
--
ALTER TABLE `venue`
  MODIFY `VenueID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `venueimages`
--
ALTER TABLE `venueimages`
  MODIFY `ImageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `venuesports`
--
ALTER TABLE `venuesports`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `venuestaffassignment`
--
ALTER TABLE `venuestaffassignment`
  MODIFY `AssignmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `venuetimeslot`
--
ALTER TABLE `venuetimeslot`
  MODIFY `SlotID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1248;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`VenueID`) REFERENCES `venue` (`VenueID`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_ibfk_3` FOREIGN KEY (`SlotID`) REFERENCES `venuetimeslot` (`SlotID`) ON DELETE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`BookingID`) REFERENCES `booking` (`BookingID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
