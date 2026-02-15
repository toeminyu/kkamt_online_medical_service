-- MySQL dump 10.13  Distrib 8.0.34, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: kkamt
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `admin_ID` int(11) NOT NULL AUTO_INCREMENT,
  `admin_username` varchar(255) NOT NULL,
  `admin_pw` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_email` varchar(255) NOT NULL,
  PRIMARY KEY (`admin_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (1,'sysadmin1','adminpw1','2025-10-02 11:09:35',''),(2,'sysadmin2','adminpw2','2025-10-02 11:09:35',''),(3,'sysadmin3','adminpw3','2025-10-02 11:09:35','admin3@gmail.com');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment`
--

DROP TABLE IF EXISTS `appointment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `preferred_language` varchar(50) NOT NULL,
  `status` enum('pending','confirmed','completed','canceled','no_show') NOT NULL DEFAULT 'confirmed',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `canceled_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appt_patient` (`patient_id`,`requested_at`),
  KEY `fk_appt_service` (`service_id`),
  KEY `idx_appt_slot` (`slot_id`),
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_slot` FOREIGN KEY (`slot_id`) REFERENCES `schedule_slot` (`slot_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (36,104,4,1,'English','canceled','2025-10-07 04:20:19',NULL,'2025-10-07 04:23:43',NULL,''),(37,104,4,3,'English','canceled','2025-10-07 04:24:09',NULL,'2025-10-07 04:24:25',NULL,''),(38,104,4,2,'English','canceled','2025-10-07 04:29:09',NULL,'2025-10-07 04:39:20',NULL,''),(39,104,4,3,'English','canceled','2025-10-07 10:24:47',NULL,'2025-10-07 10:25:59',NULL,''),(40,104,4,3,'English','canceled','2025-10-07 13:16:52','2025-10-07 13:17:04',NULL,NULL,''),(44,104,4,3,'English','confirmed','2025-10-07 18:19:28','2025-10-07 18:19:38',NULL,NULL,''),(45,103,4,3,'English','confirmed','2025-10-07 18:48:29','2025-10-07 18:48:45',NULL,NULL,'');
/*!40000 ALTER TABLE `appointment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_user`
--

DROP TABLE IF EXISTS `auth_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('patient','doctor','admin') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_auth_user_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_user`
--

LOCK TABLES `auth_user` WRITE;
/*!40000 ALTER TABLE `auth_user` DISABLE KEYS */;
INSERT INTO `auth_user` VALUES (1,'alice@example.com','hash_alice','patient',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(2,'bob@example.com','hash_bob','patient',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(3,'chen@example.com','hash_chen','patient',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(4,'somchai@hospital.com','hash_doc1','doctor',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(5,'maria@hospital.com','hash_doc2','doctor',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(6,'nguyen@hospital.com','hash_doc3','doctor',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(7,'admin@system.com','hash_admin','admin',1,NULL,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(8,'toe@gmail.com','$2y$10$xMbJQLZy8gNJ1VrRyuQkYekqvUcIjUJSKtcWYvgcLMx8F9VkkUQNC','patient',1,NULL,'2025-10-03 16:32:05','2025-10-05 16:36:31'),(9,'doctoe@gmail.com','$2y$10$PvsU8Sg45LCKbYC2e7qruOhJ6UlrSRoTI.VxGkhB9G6SGdq6W75ra','doctor',1,NULL,'2025-10-03 19:11:37','2025-10-03 19:11:37');
/*!40000 ALTER TABLE `auth_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor`
--

DROP TABLE IF EXISTS `doctor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor` (
  `doctor_ID` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `doctor_name` varchar(255) NOT NULL,
  `doctor_gender` varchar(45) DEFAULT NULL,
  `doctor_ph` varchar(255) DEFAULT NULL,
  `doctor_email` varchar(255) DEFAULT NULL,
  `doctor_degree` varchar(255) DEFAULT NULL,
  `doctor_description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`doctor_ID`),
  UNIQUE KEY `uq_doctor_user` (`user_id`),
  UNIQUE KEY `uq_doctor_email` (`doctor_email`),
  CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor`
--

LOCK TABLES `doctor` WRITE;
/*!40000 ALTER TABLE `doctor` DISABLE KEYS */;
INSERT INTO `doctor` VALUES (1,4,'Dr. Somchai Prasert','Male','0911111111','somchai@hospital.com','MD, Cardiology','Expert in hypertension and heart conditions','2025-10-02 11:09:35','2025-10-02 11:09:35'),(2,5,'Dr. Maria Lopez','Female','0922222222','maria@hospital.com','MD, Pediatrics','Specialist in child healthcare and asthma management','2025-10-02 11:09:35','2025-10-02 11:09:35'),(3,6,'Dr. Nguyen An','Male','0933333333','nguyen@hospital.com','MD, General Practice','General practitioner with telemedicine experience','2025-10-02 11:09:35','2025-10-02 11:09:35'),(4,9,'Toe Min Yu','Male','0993152062','doctoe@gmail.com','MMIT','','2025-10-03 19:11:37','2025-10-03 19:11:37');
/*!40000 ALTER TABLE `doctor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_shift`
--

DROP TABLE IF EXISTS `doctor_shift`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_shift` (
  `shift_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`shift_id`),
  KEY `fk_ds_doctor` (`doctor_id`),
  CONSTRAINT `fk_ds_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_shift`
--

LOCK TABLES `doctor_shift` WRITE;
/*!40000 ALTER TABLE `doctor_shift` DISABLE KEYS */;
INSERT INTO `doctor_shift` VALUES (1,4,'09:00:00','23:00:00','2025-10-05 13:08:16');
/*!40000 ALTER TABLE `doctor_shift` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_record`
--

DROP TABLE IF EXISTS `health_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_record` (
  `health_record_ID` int(11) NOT NULL AUTO_INCREMENT,
  `health_record_description` varchar(255) DEFAULT NULL,
  `patient_ID` int(11) NOT NULL,
  `appointment_ID` int(11) DEFAULT NULL,
  `prescription_ID` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`health_record_ID`),
  KEY `fk_hr_patient` (`patient_ID`),
  KEY `fk_hr_appointment` (`appointment_ID`),
  KEY `fk_hr_prescription` (`prescription_ID`),
  CONSTRAINT `fk_hr_appointment` FOREIGN KEY (`appointment_ID`) REFERENCES `appointment` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_patient` FOREIGN KEY (`patient_ID`) REFERENCES `patient` (`patient_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hr_prescription` FOREIGN KEY (`prescription_ID`) REFERENCES `prescription` (`prescription_ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_record`
--

LOCK TABLES `health_record` WRITE;
/*!40000 ALTER TABLE `health_record` DISABLE KEYS */;
INSERT INTO `health_record` VALUES (1,'Hypertension checkup, lifestyle changes advised',1,NULL,NULL,'2025-10-02 11:09:35'),(2,'Asthma symptoms, prescribed inhaler',3,NULL,NULL,'2025-10-02 11:09:35'),(3,'General checkup, vitamin supplements recommended',2,NULL,NULL,'2025-10-02 11:09:35'),(4,'Hello',4,44,NULL,'2025-10-07 15:52:05');
/*!40000 ALTER TABLE `health_record` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `user_id` int(11) NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `idx_token` (`token`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient`
--

DROP TABLE IF EXISTS `patient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient` (
  `patient_ID` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `patient_name` varchar(255) NOT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_gender` varchar(45) DEFAULT NULL,
  `patient_ph` varchar(255) DEFAULT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `patient_address` varchar(255) DEFAULT NULL,
  `patient_weight` float DEFAULT NULL,
  `patient_height` float DEFAULT NULL,
  `patient_blood` varchar(255) DEFAULT NULL,
  `patient_condition` varchar(255) DEFAULT NULL,
  `patient_nationality` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`patient_ID`),
  UNIQUE KEY `uq_patient_user` (`user_id`),
  UNIQUE KEY `uq_patient_email` (`patient_email`),
  CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `auth_user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient`
--

LOCK TABLES `patient` WRITE;
/*!40000 ALTER TABLE `patient` DISABLE KEYS */;
INSERT INTO `patient` VALUES (1,1,'Alice Wong','2000-05-10','Female','0812345678','alice@example.com','Bangkok, Thailand',52.5,160,'O+','Asthma','Thai','2025-10-02 11:09:35','2025-10-02 11:09:35'),(2,2,'Bob Smith','1998-11-02','Male','0823456789','bob@example.com','Chiang Mai, Thailand',70.2,175,'A-','Hypertension','American','2025-10-02 11:09:35','2025-10-02 11:09:35'),(3,3,'Chen Li','2001-03-15','Male','0834567890','chen@example.com','Hanoi, Vietnam',65,170,'B+','None','Vietnamese','2025-10-02 11:09:35','2025-10-02 11:09:35'),(4,8,'Toe Min Yu','2002-01-19','Male','0993152061','toe@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,'2025-10-03 16:32:05','2025-10-03 16:32:05');
/*!40000 ALTER TABLE `patient` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription`
--

DROP TABLE IF EXISTS `prescription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription` (
  `prescription_ID` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_date` datetime NOT NULL DEFAULT current_timestamp(),
  `prescription_description` varchar(255) DEFAULT NULL,
  `patient_ID` int(11) NOT NULL,
  `doctor_ID` int(11) NOT NULL,
  `appointment_ID` int(11) NOT NULL,
  PRIMARY KEY (`prescription_ID`),
  KEY `fk_presc_patient` (`patient_ID`),
  KEY `fk_presc_doctor` (`doctor_ID`),
  KEY `fk_presc_appointment` (`appointment_ID`),
  CONSTRAINT `fk_presc_appointment` FOREIGN KEY (`appointment_ID`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_presc_doctor` FOREIGN KEY (`doctor_ID`) REFERENCES `doctor` (`doctor_ID`) ON UPDATE CASCADE,
  CONSTRAINT `fk_presc_patient` FOREIGN KEY (`patient_ID`) REFERENCES `patient` (`patient_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription`
--

LOCK TABLES `prescription` WRITE;
/*!40000 ALTER TABLE `prescription` DISABLE KEYS */;
INSERT INTO `prescription` VALUES (4,'2025-10-07 18:19:46','',4,4,44),(5,'2025-10-07 18:48:53',NULL,4,4,45);
/*!40000 ALTER TABLE `prescription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription_items`
--

DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_ID` int(11) NOT NULL,
  `medicine_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `instructions` varchar(255) DEFAULT NULL,
  `duration_days` int(11) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `fk_pi_prescription` (`prescription_ID`),
  CONSTRAINT `fk_pi_prescription` FOREIGN KEY (`prescription_ID`) REFERENCES `prescription` (`prescription_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription_items`
--

LOCK TABLES `prescription_items` WRITE;
/*!40000 ALTER TABLE `prescription_items` DISABLE KEYS */;
INSERT INTO `prescription_items` VALUES (4,4,'Sweets','5500mg','3 times a day',2);
/*!40000 ALTER TABLE `prescription_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_slot`
--

DROP TABLE IF EXISTS `schedule_slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_slot` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `start_dt` time NOT NULL,
  `end_dt` time NOT NULL,
  `status` enum('available','held','booked','completed','disabled','expired') NOT NULL DEFAULT 'available',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`slot_id`),
  UNIQUE KEY `uq_slot_doctor_start` (`doctor_id`,`start_dt`),
  KEY `idx_slot_start` (`start_dt`),
  CONSTRAINT `fk_slot_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=271 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_slot`
--

LOCK TABLES `schedule_slot` WRITE;
/*!40000 ALTER TABLE `schedule_slot` DISABLE KEYS */;
INSERT INTO `schedule_slot` VALUES (103,4,'22:00:00','22:30:00','expired','2025-10-03 20:44:52'),(104,4,'22:30:00','23:00:00','expired','2025-10-03 20:44:52'),(233,4,'09:00:00','09:30:00','expired','2025-10-08 01:57:22'),(234,4,'09:30:00','10:00:00','expired','2025-10-08 01:57:22'),(235,4,'10:00:00','10:30:00','expired','2025-10-08 01:57:22'),(236,4,'10:30:00','11:00:00','expired','2025-10-08 01:57:22'),(237,4,'11:00:00','11:30:00','expired','2025-10-08 01:57:22'),(238,4,'11:30:00','12:00:00','expired','2025-10-08 01:57:22'),(239,4,'12:00:00','12:30:00','expired','2025-10-08 01:57:22'),(240,4,'12:30:00','13:00:00','expired','2025-10-08 01:57:22'),(241,4,'13:00:00','13:30:00','expired','2025-10-08 01:57:22'),(242,4,'13:30:00','14:00:00','expired','2025-10-08 01:57:22'),(243,4,'14:00:00','14:30:00','expired','2025-10-08 01:57:22'),(244,4,'14:30:00','15:00:00','expired','2025-10-08 01:57:22'),(245,4,'15:00:00','15:30:00','expired','2025-10-08 01:57:22'),(246,4,'15:30:00','16:00:00','expired','2025-10-08 01:57:22'),(247,4,'16:00:00','16:30:00','expired','2025-10-08 01:57:22'),(248,4,'16:30:00','17:00:00','expired','2025-10-08 01:57:22'),(249,4,'17:00:00','17:30:00','expired','2025-10-08 17:00:41'),(256,4,'17:30:00','18:00:00','expired','2025-10-08 17:38:17'),(257,4,'18:00:00','18:30:00','expired','2025-10-08 17:38:17'),(258,4,'18:30:00','19:00:00','expired','2025-10-08 17:38:17'),(259,4,'19:00:00','19:30:00','expired','2025-10-08 17:38:17'),(260,4,'19:30:00','20:00:00','expired','2025-10-08 17:38:17'),(261,4,'20:00:00','20:30:00','expired','2025-10-08 17:38:17'),(267,4,'20:30:00','21:00:00','available','2025-10-09 20:24:13'),(268,4,'21:00:00','21:30:00','available','2025-10-09 20:24:13'),(269,4,'21:30:00','22:00:00','available','2025-10-09 20:24:13'),(270,4,'23:00:00','23:30:00','available','2025-10-09 20:24:13');
/*!40000 ALTER TABLE `schedule_slot` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service`
--

DROP TABLE IF EXISTS `service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service` (
  `service_ID` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) NOT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `service_duration_min` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`service_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service`
--

LOCK TABLES `service` WRITE;
/*!40000 ALTER TABLE `service` DISABLE KEYS */;
INSERT INTO `service` VALUES (1,'General Consultation','Online consultation for general health concerns',30,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(2,'Pediatrics Consultation','Consultation for children’s health',30,'2025-10-02 11:09:35','2025-10-02 11:09:35'),(3,'Cardiology Checkup','Consultation for heart and blood pressure issues',30,'2025-10-02 11:09:35','2025-10-02 11:09:35');
/*!40000 ALTER TABLE `service` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-09 20:26:53
