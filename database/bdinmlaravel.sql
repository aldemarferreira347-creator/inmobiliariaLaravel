-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: inmobiliaria_laravel
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned DEFAULT NULL,
  `entidad` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` bigint unsigned DEFAULT NULL,
  `accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `auditoria_usuario_id_foreign` (`usuario_id`),
  KEY `auditoria_entidad_entidad_id_index` (`entidad`,`entidad_id`),
  CONSTRAINT `auditoria_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cita`
--

DROP TABLE IF EXISTS `cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cita` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint unsigned NOT NULL,
  `asesor_id` bigint unsigned DEFAULT NULL,
  `inmueble_id` bigint unsigned NOT NULL,
  `fecha` datetime NOT NULL,
  `estado` enum('Pendiente','Asignada','Realizada','Cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cita_asesor_id_estado_index` (`asesor_id`,`estado`),
  KEY `cita_cliente_id_estado_index` (`cliente_id`,`estado`),
  KEY `cita_inmueble_id_fecha_index` (`inmueble_id`,`fecha`),
  KEY `cita_estado_index` (`estado`),
  CONSTRAINT `cita_asesor_id_foreign` FOREIGN KEY (`asesor_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cita_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cita_inmueble_id_foreign` FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita`
--

LOCK TABLES `cita` WRITE;
/*!40000 ALTER TABLE `cita` DISABLE KEYS */;
/*!40000 ALTER TABLE `cita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cita_historial`
--

DROP TABLE IF EXISTS `cita_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cita_historial` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cita_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cita_historial_usuario_id_foreign` (`usuario_id`),
  KEY `cita_historial_cita_id_index` (`cita_id`),
  CONSTRAINT `cita_historial_cita_id_foreign` FOREIGN KEY (`cita_id`) REFERENCES `cita` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cita_historial_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita_historial`
--

LOCK TABLES `cita_historial` WRITE;
/*!40000 ALTER TABLE `cita_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `cita_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_franja_cita`
--

DROP TABLE IF EXISTS `config_franja_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `config_franja_cita` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dia_semana` enum('Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `intervalo_minutos` smallint unsigned NOT NULL DEFAULT '30',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `actualizado_por` bigint unsigned DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_franja_cita_dia_semana_unique` (`dia_semana`),
  KEY `config_franja_cita_actualizado_por_foreign` (`actualizado_por`),
  CONSTRAINT `config_franja_cita_actualizado_por_foreign` FOREIGN KEY (`actualizado_por`) REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_franja_cita`
--

LOCK TABLES `config_franja_cita` WRITE;
/*!40000 ALTER TABLE `config_franja_cita` DISABLE KEYS */;
INSERT INTO `config_franja_cita` VALUES (1,'Lunes','08:00:00','18:00:00',30,1,NULL,'2026-10-22 19:30:57'),(2,'Martes','08:00:00','18:00:00',30,1,NULL,'2026-10-22 19:30:57'),(3,'Miercoles','08:00:00','18:00:00',30,1,NULL,'2026-10-22 19:30:57'),(4,'Jueves','08:00:00','18:00:00',30,1,NULL,'2026-10-22 19:30:57'),(5,'Viernes','08:00:00','18:00:00',30,1,NULL,'2026-10-22 19:30:57'),(6,'Sabado','08:00:00','18:00:00',30,1,NULL,'2026-10-22 19:30:57');
/*!40000 ALTER TABLE `config_franja_cita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contrato`
--

DROP TABLE IF EXISTS `contrato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contrato` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` bigint unsigned NOT NULL,
  `numero_contrato` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `valor_mensual` decimal(12,2) NOT NULL,
  `archivo_ruta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('Vigente','Vencido','Rescindido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Vigente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contrato_reserva_id_unique` (`reserva_id`),
  UNIQUE KEY `contrato_numero_contrato_unique` (`numero_contrato`),
  KEY `contrato_estado_index` (`estado`),
  KEY `contrato_fecha_fin_index` (`fecha_fin`),
  CONSTRAINT `contrato_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contrato`
--

LOCK TABLES `contrato` WRITE;
/*!40000 ALTER TABLE `contrato` DISABLE KEYS */;
INSERT INTO `contrato` VALUES (1,2,'CON-2026-00002','2026-09-22','2027-10-22',4162000.00,NULL,'Vigente','2026-10-22 19:31:07','2026-10-22 19:31:07');
/*!40000 ALTER TABLE `contrato` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversacion`
--

DROP TABLE IF EXISTS `conversacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint unsigned NOT NULL,
  `asesor_id` bigint unsigned NOT NULL,
  `inmueble_id` bigint unsigned DEFAULT NULL,
  `ultimo_mensaje_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversacion_cliente_id_asesor_id_inmueble_id_unique` (`cliente_id`,`asesor_id`,`inmueble_id`),
  KEY `conversacion_inmueble_id_foreign` (`inmueble_id`),
  KEY `conversacion_asesor_id_ultimo_mensaje_en_index` (`asesor_id`,`ultimo_mensaje_en`),
  KEY `conversacion_cliente_id_ultimo_mensaje_en_index` (`cliente_id`,`ultimo_mensaje_en`),
  CONSTRAINT `conversacion_asesor_id_foreign` FOREIGN KEY (`asesor_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversacion_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversacion_inmueble_id_foreign` FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversacion`
--

LOCK TABLES `conversacion` WRITE;
/*!40000 ALTER TABLE `conversacion` DISABLE KEYS */;
INSERT INTO `conversacion` VALUES (1,3,2,1,'2026-10-22 19:31:07','2026-10-22 19:31:07','2026-10-22 19:31:07');
/*!40000 ALTER TABLE `conversacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorito`
--

DROP TABLE IF EXISTS `favorito`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorito` (
  `usuario_id` bigint unsigned NOT NULL,
  `inmueble_id` bigint unsigned NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_id`,`inmueble_id`),
  KEY `favorito_inmueble_id_foreign` (`inmueble_id`),
  CONSTRAINT `favorito_inmueble_id_foreign` FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorito_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorito`
--

LOCK TABLES `favorito` WRITE;
/*!40000 ALTER TABLE `favorito` DISABLE KEYS */;
INSERT INTO `favorito` VALUES (3,1,'2026-10-22 19:31:07'),(3,2,'2026-10-22 19:31:07'),(3,3,'2026-10-22 19:31:07');
/*!40000 ALTER TABLE `favorito` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_reserva`
--

DROP TABLE IF EXISTS `historial_reserva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_reserva` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` bigint unsigned NOT NULL,
  `estado_anterior` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cambiado_por` bigint unsigned DEFAULT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `historial_reserva_cambiado_por_foreign` (`cambiado_por`),
  KEY `historial_reserva_reserva_id_creado_en_index` (`reserva_id`,`creado_en`),
  CONSTRAINT `historial_reserva_cambiado_por_foreign` FOREIGN KEY (`cambiado_por`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `historial_reserva_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_reserva`
--

LOCK TABLES `historial_reserva` WRITE;
/*!40000 ALTER TABLE `historial_reserva` DISABLE KEYS */;
INSERT INTO `historial_reserva` VALUES (1,1,NULL,'PENDIENTE_PAGO',3,'Reserva creada por el cliente.',NULL,'2026-10-22 19:31:07'),(2,2,NULL,'PENDIENTE_PAGO',3,'Reserva creada por el cliente.',NULL,'2026-10-22 19:31:07'),(3,2,'PENDIENTE_PAGO','CONFIRMADA',NULL,'Pago aprobado por la administración.',NULL,'2026-10-22 19:31:07');
/*!40000 ALTER TABLE `historial_reserva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imageninmueble`
--

DROP TABLE IF EXISTS `imageninmueble`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `imageninmueble` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inmueble_id` bigint unsigned NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT '0',
  `orden` int unsigned NOT NULL DEFAULT '0',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `imageninmueble_inmueble_id_es_principal_index` (`inmueble_id`,`es_principal`),
  KEY `imageninmueble_inmueble_id_orden_index` (`inmueble_id`,`orden`),
  CONSTRAINT `imageninmueble_inmueble_id_foreign` FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imageninmueble`
--

LOCK TABLES `imageninmueble` WRITE;
/*!40000 ALTER TABLE `imageninmueble` DISABLE KEYS */;
INSERT INTO `imageninmueble` VALUES (1,1,'storage/inmuebles/seed-1-1.jpg',1,1,'2026-10-22 19:31:07'),(2,1,'storage/inmuebles/seed-1-2.jpg',0,2,'2026-10-22 19:31:07'),(3,2,'storage/inmuebles/seed-2-1.jpg',1,1,'2026-10-22 19:31:07'),(4,2,'storage/inmuebles/seed-2-2.jpg',0,2,'2026-10-22 19:31:07'),(5,3,'storage/inmuebles/seed-3-1.jpg',1,1,'2026-10-22 19:31:07'),(6,3,'storage/inmuebles/seed-3-2.jpg',0,2,'2026-10-22 19:31:07'),(7,4,'storage/inmuebles/seed-4-1.jpg',1,1,'2026-10-22 19:31:07'),(8,4,'storage/inmuebles/seed-4-2.jpg',0,2,'2026-10-22 19:31:07'),(9,5,'storage/inmuebles/seed-5-1.jpg',1,1,'2026-10-22 19:31:07'),(10,5,'storage/inmuebles/seed-5-2.jpg',0,2,'2026-10-22 19:31:07'),(11,6,'storage/inmuebles/seed-6-1.jpg',1,1,'2026-10-22 19:31:07'),(12,6,'storage/inmuebles/seed-6-2.jpg',0,2,'2026-10-22 19:31:07'),(13,7,'storage/inmuebles/seed-7-1.jpg',1,1,'2026-10-22 19:31:07'),(14,7,'storage/inmuebles/seed-7-2.jpg',0,2,'2026-10-22 19:31:07'),(15,8,'storage/inmuebles/seed-8-1.jpg',1,1,'2026-10-22 19:31:07'),(16,8,'storage/inmuebles/seed-8-2.jpg',0,2,'2026-10-22 19:31:07'),(17,9,'storage/inmuebles/seed-9-1.jpg',1,1,'2026-10-22 19:31:07'),(18,9,'storage/inmuebles/seed-9-2.jpg',0,2,'2026-10-22 19:31:07'),(19,10,'storage/inmuebles/seed-10-1.jpg',1,1,'2026-10-22 19:31:07'),(20,10,'storage/inmuebles/seed-10-2.jpg',0,2,'2026-10-22 19:31:07'),(21,11,'storage/inmuebles/seed-11-1.jpg',1,1,'2026-10-22 19:31:07'),(22,11,'storage/inmuebles/seed-11-2.jpg',0,2,'2026-10-22 19:31:07'),(23,12,'storage/inmuebles/seed-12-1.jpg',1,1,'2026-10-22 19:31:07'),(24,12,'storage/inmuebles/seed-12-2.jpg',0,2,'2026-10-22 19:31:07');
/*!40000 ALTER TABLE `imageninmueble` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inmueble`
--

DROP TABLE IF EXISTS `inmueble`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inmueble` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('apartamento','casa','lote','oficina','finca','local') COLLATE utf8mb4_unicode_ci NOT NULL,
  `modalidad` enum('venta','arriendo','ambos') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('Disponible','Reservado','Ocupado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Disponible',
  `precio_venta` decimal(15,2) DEFAULT NULL,
  `precio_arrendamiento` decimal(12,2) DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barrio` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `habitaciones` int unsigned NOT NULL DEFAULT '1',
  `banos` int unsigned NOT NULL DEFAULT '1',
  `area` decimal(10,2) NOT NULL,
  `parqueadero` tinyint(1) NOT NULL DEFAULT '0',
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_publicacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inmueble_codigo_unique` (`codigo`),
  KEY `inmueble_estado_index` (`estado`),
  KEY `inmueble_ciudad_index` (`ciudad`),
  KEY `inmueble_modalidad_index` (`modalidad`),
  KEY `inmueble_tipo_index` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inmueble`
--

LOCK TABLES `inmueble` WRITE;
/*!40000 ALTER TABLE `inmueble` DISABLE KEYS */;
INSERT INTO `inmueble` VALUES (1,'INM-ZONJGO','Lote en Ventilador','Molestias rerum tempora et enim et delectus. Nulla sit quidem minima sequi impedit laudantium soluta. Iste placeat sequi beatae doloremque deleniti et alias cum. Ea tempora sapiente beatae quo asperiores porro sint.','lote','arriendo','Disponible',NULL,1284000.00,'Neiva','Los Cámbulos','Praza Raquel, 47, Bajos',1,1,205.00,1,'storage/inmuebles/seed-1-1.jpg','2026-10-22 19:31:07'),(2,'INM-OPZO6T','Apartamento en Altico','Qui impedit sed necessitatibus qui. Et consequatur dolorem et et sapiente eum asperiores. Corrupti beatae et qui pariatur nostrum numquam iste. Atque asperiores ad est velit vero veniam officiis. Velit ea aliquid dolorem reiciendis mollitia ut itaque quae. Necessitatibus nihil sit sequi iure. Soluta qui consequuntur quod quis fuga dolorem quo.','apartamento','arriendo','Ocupado',NULL,4162000.00,'Neiva','Los Cámbulos','Camino Jimena, 195, 0º F',1,3,54.00,1,'storage/inmuebles/seed-2-1.jpg','2026-10-22 19:31:07'),(3,'INM-67YGRS','Lote en Altico','Suscipit quae voluptatem doloremque rerum voluptas vel ipsum. Labore ut ducimus facere voluptatem repellat quos eveniet sit. Aspernatur ullam esse illum non non aut magnam. Et ut illum illum maxime nihil. Eos ad consequatur accusantium officiis soluta sunt. Omnis aut et eos. Rerum temporibus dolorem dolore eum dolor. Magnam dolore eum animi quas non et eos. Ut minus dolor voluptatum eum quia et.','lote','venta','Reservado',586000000.00,NULL,'Neiva','Los Cámbulos','Camiño Escribano, 40, 84º C',3,4,275.00,1,'storage/inmuebles/seed-3-1.jpg','2026-10-22 19:31:07'),(4,'INM-6JXEKE','Apartamento en Buganviles','Dolorum beatae impedit fugiat. Error qui asperiores voluptatem ut molestiae et beatae sequi. Velit possimus eos est mollitia veniam molestiae dolorem. Quisquam tenetur quo quia ut. Qui sunt et dolorem recusandae enim. Pariatur tenetur quasi tenetur ea.','apartamento','arriendo','Disponible',NULL,2613000.00,'Neiva','Los Cámbulos','Camiño Naia, 841, Ático 9º',3,2,135.00,1,'storage/inmuebles/seed-4-1.jpg','2026-10-22 19:31:07'),(5,'INM-SDLID8','Oficina en Candado','Ipsa at voluptatem id quisquam et quo. Atque sit omnis impedit officiis quae odit. Adipisci optio consequatur repudiandae. Doloribus sed laboriosam temporibus officia. Laboriosam id cumque asperiores qui maiores. A vero animi dolorem est. Sed asperiores sapiente rerum at non iusto corporis. Praesentium autem neque assumenda dicta ut id id.','oficina','venta','Disponible',597000000.00,NULL,'Neiva','Quirinal','Avenida Casillas, 961, 11º D',5,3,113.00,1,'storage/inmuebles/seed-5-1.jpg','2026-10-22 19:31:07'),(6,'INM-TMJW1S','Finca en Altico','Incidunt quisquam quam expedita nemo. Qui iure accusantium voluptatum aut in perspiciatis illo. Ex ipsum est ut ut iusto sunt similique id. Ratione reprehenderit dignissimos eos sequi. Sed aspernatur aut explicabo fugiat. Sint occaecati voluptatum ea ut voluptate voluptates earum architecto. Doloremque assumenda voluptatum commodi autem tempora inventore. Et et expedita omnis et.','finca','arriendo','Disponible',NULL,1060000.00,'Neiva','Buganviles','Camino Alexandra, 341, 20º E',4,2,101.00,1,'storage/inmuebles/seed-6-1.jpg','2026-10-22 19:31:07'),(7,'INM-MN2QDT','Lote en Quirinal','Aliquid quo molestiae rerum exercitationem velit adipisci expedita. Qui voluptas officiis dolores quas optio numquam. Dolore rerum recusandae perspiciatis. Eveniet accusantium voluptas voluptate alias quidem labore. Corporis dolore harum culpa corrupti ut explicabo. Deleniti nobis at a similique aut explicabo.','lote','venta','Disponible',563000000.00,NULL,'Neiva','Buganviles','Camiño Miriam, 8, 9º B',3,4,246.00,1,'storage/inmuebles/seed-7-1.jpg','2026-10-22 19:31:07'),(8,'INM-X7SHYT','Apartamento en Candado','Aut delectus molestias aspernatur perferendis cupiditate in. Aperiam consectetur est qui in temporibus facilis consequatur. Id possimus ipsam error iusto. Aliquid reiciendis delectus et eos.','apartamento','ambos','Disponible',583000000.00,4320000.00,'Neiva','Los Cámbulos','Travessera Lucía, 6, 39º B',2,3,270.00,1,'storage/inmuebles/seed-8-1.jpg','2026-10-22 19:31:07'),(9,'INM-YVEJJ3','Local en Buganviles','Est laborum provident rerum vitae et harum. Ratione itaque voluptate itaque sit ad autem maxime. Deleniti sunt eveniet neque et. Quas consequatur ducimus non quia tempora et et. In id quam nesciunt repellat asperiores possimus repudiandae. Eum voluptatem est non dolorem provident aut tempora.','local','venta','Disponible',764000000.00,NULL,'Neiva','El Altico','Praza Carmen, 5, 85º B',4,4,231.00,0,'storage/inmuebles/seed-9-1.jpg','2026-10-22 19:31:07'),(10,'INM-9M0TZA','Local en Quirinal','Omnis facilis corrupti eum earum officia. Unde ex et ipsa eaque assumenda. Et dolorem hic voluptatibus sunt quo tempora autem. Vero sapiente voluptatem perferendis assumenda et quam ipsam optio. Animi nam officiis voluptatem ea assumenda animi commodi. Hic pariatur quibusdam est delectus.','local','venta','Disponible',841000000.00,NULL,'Neiva','El Altico','Carrer Héctor, 8, 4º D',3,2,71.00,1,'storage/inmuebles/seed-10-1.jpg','2026-10-22 19:31:07'),(11,'INM-NJEGGS','Casa en Candado','Debitis voluptatem voluptatem perspiciatis recusandae. Fuga mollitia fuga veniam est. Nesciunt rerum et corrupti voluptatem enim. Voluptate ratione eum labore explicabo maiores recusandae sequi. Dolores est veniam est nesciunt. Molestiae non quam sit praesentium iste delectus.','casa','venta','Disponible',654000000.00,NULL,'Neiva','El Altico','Paseo Cristian, 8, 1º',1,1,226.00,0,'storage/inmuebles/seed-11-1.jpg','2026-10-22 19:31:07'),(12,'INM-JKJXRV','Finca en Altico','Neque officiis dicta placeat. Unde ducimus placeat amet in sapiente eligendi voluptas. In aliquam praesentium laboriosam voluptatibus suscipit ducimus. Tenetur ea adipisci ipsa est et doloribus est tempore. Qui voluptatum quo voluptatem.','finca','venta','Disponible',743000000.00,NULL,'Neiva','Quirinal','Avenida Merino, 27, 50º A',1,1,153.00,0,'storage/inmuebles/seed-12-1.jpg','2026-10-22 19:31:07');
/*!40000 ALTER TABLE `inmueble` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensaje`
--

DROP TABLE IF EXISTS `mensaje`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mensaje` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversacion_id` bigint unsigned NOT NULL,
  `emisor_id` bigint unsigned NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci,
  `adjunto_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leido_en` timestamp NULL DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mensaje_emisor_id_foreign` (`emisor_id`),
  KEY `mensaje_conversacion_id_id_index` (`conversacion_id`,`id`),
  KEY `mensaje_conversacion_id_leido_en_index` (`conversacion_id`,`leido_en`),
  CONSTRAINT `mensaje_conversacion_id_foreign` FOREIGN KEY (`conversacion_id`) REFERENCES `conversacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mensaje_emisor_id_foreign` FOREIGN KEY (`emisor_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mensaje`
--

LOCK TABLES `mensaje` WRITE;
/*!40000 ALTER TABLE `mensaje` DISABLE KEYS */;
INSERT INTO `mensaje` VALUES (1,1,3,'Hola, solicito información del inmueble con código INM-ZONJGO.',NULL,'2026-10-22 19:31:07','2026-10-22 19:11:07'),(2,1,2,'Claro que sí. ¿Te viene bien una visita el sábado por la mañana?',NULL,NULL,'2026-10-22 19:26:07');
/*!40000 ALTER TABLE `mensaje` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metodo_pago_guardado`
--

DROP TABLE IF EXISTS `metodo_pago_guardado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metodo_pago_guardado` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint unsigned NOT NULL,
  `pasarela` enum('Stripe') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Stripe',
  `token_pasarela` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_pasarela_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marca` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ultimos_4` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_titular` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mes_expiracion` tinyint unsigned DEFAULT NULL,
  `anio_expiracion` smallint unsigned DEFAULT NULL,
  `predeterminado` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `metodo_pago_guardado_pasarela_token_pasarela_unique` (`pasarela`,`token_pasarela`),
  KEY `metodo_pago_guardado_cliente_id_activo_index` (`cliente_id`,`activo`),
  CONSTRAINT `metodo_pago_guardado_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metodo_pago_guardado`
--

LOCK TABLES `metodo_pago_guardado` WRITE;
/*!40000 ALTER TABLE `metodo_pago_guardado` DISABLE KEYS */;
/*!40000 ALTER TABLE `metodo_pago_guardado` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_25_000001_create_auditoria_table',1),(5,'2026_07_25_000002_create_inmueble_table',1),(6,'2026_07_25_000003_create_imageninmueble_table',1),(7,'2026_07_25_000004_create_reserva_table',1),(8,'2026_07_25_000005_create_contrato_table',1),(9,'2026_07_25_000006_create_favorito_table',1),(10,'2026_07_25_000007_create_historial_reserva_table',1),(11,'2026_07_25_000008_create_pago_table',1),(12,'2026_07_25_000009_create_venta_table',1),(13,'2026_07_25_000010_create_conversacion_table',1),(14,'2026_07_25_000011_create_mensaje_table',1),(15,'2026_07_25_000012_create_notificacion_table',1),(16,'2026_08_06_000001_create_cita_table',1),(17,'2026_08_06_000002_create_cita_historial_table',1),(18,'2026_08_06_000003_create_observacioncita_table',1),(19,'2026_08_06_000004_create_config_franja_cita_table',1),(20,'2026_08_06_000005_create_metodo_pago_guardado_table',1),(21,'2026_08_06_000006_create_webhook_evento_table',1),(22,'2026_08_13_011554_alter_precio_venta_in_inmueble_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('info','success','warning','error','sistema') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` bigint unsigned DEFAULT NULL,
  `leida_en` timestamp NULL DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notificacion_usuario_id_leida_en_index` (`usuario_id`,`leida_en`),
  KEY `notificacion_referencia_tipo_referencia_id_index` (`referencia_tipo`,`referencia_id`),
  CONSTRAINT `notificacion_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion`
--

LOCK TABLES `notificacion` WRITE;
/*!40000 ALTER TABLE `notificacion` DISABLE KEYS */;
INSERT INTO `notificacion` VALUES (1,3,'Reserva registrada','Tu reserva quedó registrada. Tienes 24 horas para completar el pago.','info','reserva',1,NULL,'2026-10-22 19:31:07'),(2,3,'Bienvenido a Inmobiliaria García','Gracias por registrarte. Guarda tus inmuebles favoritos para no perderlos de vista.','sistema',NULL,NULL,'2026-10-21 19:31:07','2026-10-20 19:31:07');
/*!40000 ALTER TABLE `notificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `observacioncita`
--

DROP TABLE IF EXISTS `observacioncita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `observacioncita` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cita_id` bigint unsigned NOT NULL,
  `asesor_id` bigint unsigned DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `observacioncita_cita_id_unique` (`cita_id`),
  KEY `observacioncita_asesor_id_foreign` (`asesor_id`),
  CONSTRAINT `observacioncita_asesor_id_foreign` FOREIGN KEY (`asesor_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `observacioncita_cita_id_foreign` FOREIGN KEY (`cita_id`) REFERENCES `cita` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `observacioncita`
--

LOCK TABLES `observacioncita` WRITE;
/*!40000 ALTER TABLE `observacioncita` DISABLE KEYS */;
/*!40000 ALTER TABLE `observacioncita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pago`
--

DROP TABLE IF EXISTS `pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pago` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` bigint unsigned NOT NULL,
  `revisado_por` bigint unsigned DEFAULT NULL,
  `metodo_pago` enum('transferencia','consignacion','efectivo','tarjeta_credito','tarjeta_debito','pse','nequi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COP',
  `monto` decimal(12,2) NOT NULL,
  `referencia` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_pasarela` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('PENDIENTE','PROCESANDO','PAGADO','RECHAZADO','REEMBOLSADO','EXPIRADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `motivo_rechazo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revisado_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pago_revisado_por_foreign` (`revisado_por`),
  KEY `pago_reserva_id_estado_index` (`reserva_id`,`estado`),
  KEY `pago_estado_index` (`estado`),
  CONSTRAINT `pago_reserva_id_foreign` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pago_revisado_por_foreign` FOREIGN KEY (`revisado_por`) REFERENCES `usuario` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pago`
--

LOCK TABLES `pago` WRITE;
/*!40000 ALTER TABLE `pago` DISABLE KEYS */;
INSERT INTO `pago` VALUES (1,1,NULL,'transferencia','COP',1284000.00,'REF-90210042',NULL,'PROCESANDO',NULL,NULL,'2026-10-22 19:31:07','2026-10-22 19:31:07'),(2,2,NULL,'consignacion','COP',4162000.00,'REF-77120018',NULL,'PAGADO',NULL,'2026-10-20 19:31:07','2026-10-22 19:31:07','2026-10-22 19:31:07');
/*!40000 ALTER TABLE `pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permiso`
--

DROP TABLE IF EXISTS `permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permiso` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rol_id` bigint unsigned NOT NULL,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` enum('create','read','update','delete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `permiso_rol_id_modulo_accion_unique` (`rol_id`,`modulo`,`accion`),
  CONSTRAINT `permiso_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `rol` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permiso`
--

LOCK TABLES `permiso` WRITE;
/*!40000 ALTER TABLE `permiso` DISABLE KEYS */;
INSERT INTO `permiso` VALUES (1,1,'inmuebles','create',1),(2,1,'inmuebles','read',1),(3,1,'inmuebles','update',1),(4,1,'inmuebles','delete',1),(5,1,'usuarios','create',1),(6,1,'usuarios','read',1),(7,1,'usuarios','update',1),(8,1,'usuarios','delete',1),(9,1,'reservas','read',1),(10,1,'reservas','update',1),(11,1,'pagos','read',1),(12,1,'pagos','update',1),(13,1,'contratos','create',1),(14,1,'contratos','read',1),(15,1,'contratos','update',1),(16,1,'ventas','read',1),(17,1,'mensajes','read',1),(18,1,'mensajes','create',1),(19,1,'notificaciones','create',1),(20,1,'notificaciones','read',1),(21,1,'reportes','read',1),(22,2,'inmuebles','read',1),(23,2,'ventas','create',1),(24,2,'ventas','read',1),(25,2,'ventas','update',1),(26,2,'mensajes','read',1),(27,2,'mensajes','create',1),(28,2,'notificaciones','read',1),(29,3,'inmuebles','read',1),(30,3,'reservas','create',1),(31,3,'reservas','read',1),(32,3,'reservas','delete',1),(33,3,'pagos','create',1),(34,3,'pagos','read',1),(35,3,'favoritos','create',1),(36,3,'favoritos','delete',1),(37,3,'mensajes','read',1),(38,3,'mensajes','create',1),(39,3,'notificaciones','read',1);
/*!40000 ALTER TABLE `permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reserva`
--

DROP TABLE IF EXISTS `reserva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserva` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo_reserva` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inmueble_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `asesor_id` bigint unsigned DEFAULT NULL,
  `monto_reserva` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('PENDIENTE_PAGO','PROCESANDO_PAGO','RECHAZADA','CONFIRMADA','CANCELADA','EXPIRADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE_PAGO',
  `expira_en` timestamp NOT NULL,
  `notas_cliente` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reserva_codigo_reserva_unique` (`codigo_reserva`),
  KEY `reserva_asesor_id_foreign` (`asesor_id`),
  KEY `reserva_inmueble_id_estado_index` (`inmueble_id`,`estado`),
  KEY `reserva_usuario_id_estado_index` (`usuario_id`,`estado`),
  KEY `reserva_expira_en_index` (`expira_en`),
  CONSTRAINT `reserva_asesor_id_foreign` FOREIGN KEY (`asesor_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reserva_inmueble_id_foreign` FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reserva_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reserva`
--

LOCK TABLES `reserva` WRITE;
/*!40000 ALTER TABLE `reserva` DISABLE KEYS */;
INSERT INTO `reserva` VALUES (1,'RES-TBDAROIV',1,3,NULL,1284000.00,'PENDIENTE_PAGO','2026-10-23 19:31:07','Me interesa visitarlo el fin de semana.','2026-10-22 19:31:07','2026-10-22 19:31:07',NULL),(2,'RES-DWK1DUZZ',2,3,NULL,4162000.00,'CONFIRMADA','2026-10-19 19:31:07',NULL,'2026-10-22 19:31:07','2026-10-22 19:31:07',NULL);
/*!40000 ALTER TABLE `reserva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol`
--

DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rol` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rol_codigo_unique` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES (1,'administrador','Administrador','Gestiona inmuebles, contratos, usuarios, asesores, roles y reportes.'),(2,'asesor','Asesor','Gestiona citas asignadas, registra observaciones, responde mensajes y gestiona ventas.'),(3,'cliente','Cliente','Usuario externo registrado: consulta inmuebles, agenda citas, reserva, paga, mensajea.');
/*!40000 ALTER TABLE `rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contrasena` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cliente',
  `documento_tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_numero` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('activo','inactivo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `desactivado_en` timestamp NULL DEFAULT NULL,
  `desactivado_por` bigint unsigned DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_email_unique` (`email`),
  UNIQUE KEY `usuario_documento_numero_unique` (`documento_numero`),
  KEY `usuario_desactivado_por_foreign` (`desactivado_por`),
  KEY `usuario_rol_index` (`rol`),
  CONSTRAINT `usuario_desactivado_por_foreign` FOREIGN KEY (`desactivado_por`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuario_rol_foreign` FOREIGN KEY (`rol`) REFERENCES `rol` (`codigo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'Ana García','admin@inmobiliaria.test','$argon2id$v=19$m=65536,t=4,p=1$T0NCbjZ0YXA1ZTE4Y0RLTQ$iYvm98yCMlP8F0QDjljBHr5yF9ksnsYxBbIvMG0OE3Y','3100000000','administrador','CC','100000000',NULL,'Neiva',NULL,NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:06'),(2,'Carlos Rojas','asesor@inmobiliaria.test','$argon2id$v=19$m=65536,t=4,p=1$dzRTeDZOcC84djl4SHJ2dw$eiqiA/dUNN6Jbj/byK4Ejw7uc1ErzgnBhZcha0vzUXk','3100000010','asesor','CC','100000001',NULL,'Neiva',NULL,NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:06'),(3,'Laura Méndez','cliente@inmobiliaria.test','$argon2id$v=19$m=65536,t=4,p=1$YzM4UXJ4NTE5bktyQVY5Rw$jik//Ed7Z6H4ODtib99v5sKAu2dabtPWFWeECP8b5Xs','3100000020','cliente','CC','100000002',NULL,'Neiva',NULL,NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:06'),(4,'Nayara Costa','valladares.oscar@example.com','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3550311903','cliente','CC','6540808384','1999-03-26','Neiva','Plaça Cintrón, 32, 9º',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(5,'Helena Castellano','esther.mas@example.org','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3569836193','cliente','CC','5631384939','1969-05-11','Bogotá','Carrer Unai, 4, 4º 9º',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(6,'Amparo Apodaca','sofia.vega@example.com','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3347260056','cliente','CC','1738030780','1975-05-08','Bogotá','Travesía Cerda, 1, 6º D',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(7,'D. Iván Fajardo','cortes.jose@example.com','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3954102030','cliente','CC','3807601649','1983-08-21','Bogotá','Travessera Fonseca, 500, 59º 6º',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(8,'Ing. Vera Valles Segundo','kgaribay@example.com','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3975351003','cliente','CC','6839817897','2005-05-12','Pitalito','Camiño Abril, 81, 2º 5º',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(9,'Olivia Piña','patricia47@example.org','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3030241227','cliente','CC','1323785535','1976-04-26','Pitalito','Carrer Martín, 642, 5º F',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(10,'Marina Vaca Hijo','serrano.cesar@example.com','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3350032738','cliente','CC','1509321128','1977-08-30','Pitalito','Camiño Silvia, 40, 4º',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07'),(11,'Omar Mateo','navas.marcos@example.net','$argon2id$v=19$m=65536,t=4,p=1$WTRCSnNpQi5wQThGdi8yOA$DxNfaK/iwcVVjowrL7cFs6rMOC6i+8Cx9y5AZhz88LY','3444364872','cliente','CC','9897086276','1970-04-17','Bogotá','Paseo Arroyo, 8, Bajos',NULL,'activo',NULL,NULL,NULL,'2026-10-22 19:31:07');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `venta`
--

DROP TABLE IF EXISTS `venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inmueble_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `asesor_id` bigint unsigned DEFAULT NULL,
  `precio_venta` decimal(12,2) NOT NULL,
  `fecha_venta` date NOT NULL,
  `notaria` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escritura_ruta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('En proceso','Cerrada','Cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'En proceso',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venta_asesor_id_foreign` (`asesor_id`),
  KEY `venta_inmueble_id_estado_index` (`inmueble_id`,`estado`),
  KEY `venta_usuario_id_estado_index` (`usuario_id`,`estado`),
  KEY `venta_fecha_venta_index` (`fecha_venta`),
  CONSTRAINT `venta_asesor_id_foreign` FOREIGN KEY (`asesor_id`) REFERENCES `usuario` (`id`) ON DELETE SET NULL,
  CONSTRAINT `venta_inmueble_id_foreign` FOREIGN KEY (`inmueble_id`) REFERENCES `inmueble` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `venta_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venta`
--

LOCK TABLES `venta` WRITE;
/*!40000 ALTER TABLE `venta` DISABLE KEYS */;
INSERT INTO `venta` VALUES (1,3,3,2,586000000.00,'2026-10-08','Notaría 2 de Neiva',NULL,'En proceso','2026-10-22 19:31:07','2026-10-22 19:31:07');
/*!40000 ALTER TABLE `venta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webhook_evento`
--

DROP TABLE IF EXISTS `webhook_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhook_evento` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pasarela` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `evento_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `procesado_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_evento_pasarela_evento_id_unique` (`pasarela`,`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webhook_evento`
--

LOCK TABLES `webhook_evento` WRITE;
/*!40000 ALTER TABLE `webhook_evento` DISABLE KEYS */;
/*!40000 ALTER TABLE `webhook_evento` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-22  9:40:27
