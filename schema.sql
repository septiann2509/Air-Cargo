-- Database: air_cargo_db
-- Proyek Akhir Kelompok 1: Air Cargo & Intermodal Terminal
-- Sistem Konsultansi & Simulasi Perpindahan Data

CREATE DATABASE IF NOT EXISTS `air_cargo_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `air_cargo_db`;

-- 0. Tabel Users (Untuk Akses Konsultan & Klien)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('consultant', 'client', 'admin') NOT NULL DEFAULT 'consultant',
    `organization` VARCHAR(100) NOT NULL DEFAULT 'Kelompok 1 - ITL Trisakti',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1. Tabel: flight_manifests (Manifes Penerbangan)
DROP TABLE IF EXISTS `flight_manifests`;
CREATE TABLE `flight_manifests` (
    `id` VARCHAR(50) PRIMARY KEY,
    `flight_number` VARCHAR(20) NOT NULL,
    `airline` VARCHAR(50) NOT NULL DEFAULT 'Garuda Indonesia Cargo',
    `aircraft_type` VARCHAR(50) NOT NULL DEFAULT 'Boeing 737-800F',
    `origin` VARCHAR(50) NOT NULL DEFAULT 'CGK (Jakarta)',
    `destination` VARCHAR(50) NOT NULL,
    `departure_time` DATETIME NOT NULL,
    `max_cargo_weight_kg` DECIMAL(10,2) NOT NULL DEFAULT 20000.00,
    `current_total_weight_kg` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('OPEN', 'LOADING', 'CLOSED', 'DEPARTED') NOT NULL DEFAULT 'OPEN',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel: uld_containers (Kontainer ULD)
DROP TABLE IF EXISTS `uld_containers`;
CREATE TABLE `uld_containers` (
    `id` VARCHAR(50) PRIMARY KEY,
    `uld_code` VARCHAR(30) NOT NULL UNIQUE,
    `uld_type` ENUM('AKE', 'PMC', 'PLA', 'PAG') NOT NULL DEFAULT 'AKE',
    `tare_weight_kg` DECIMAL(8,2) NOT NULL DEFAULT 75.00,
    `max_weight_kg` DECIMAL(10,2) NOT NULL DEFAULT 1588.00,
    `current_weight_kg` DECIMAL(10,2) NOT NULL DEFAULT 75.00,
    `flight_id` VARCHAR(50) NULL,
    `compartment` VARCHAR(30) NOT NULL DEFAULT 'AFT-LOWER-1',
    `status` ENUM('EMPTY', 'LOADING', 'FULL', 'LOADED') NOT NULL DEFAULT 'EMPTY',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`flight_id`) REFERENCES `flight_manifests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel: truck_arrivals (Kedatangan Truk - TMS/TAS)
DROP TABLE IF EXISTS `truck_arrivals`;
CREATE TABLE `truck_arrivals` (
    `id` VARCHAR(50) PRIMARY KEY,
    `truck_plate` VARCHAR(20) NOT NULL,
    `driver_name` VARCHAR(100) NOT NULL,
    `transporter_company` VARCHAR(100) NOT NULL DEFAULT 'PT Antar Lintas Logistik',
    `dock_slot` VARCHAR(20) NOT NULL,
    `booking_code` VARCHAR(50) NOT NULL,
    `arrival_time` DATETIME NOT NULL,
    `status` ENUM('SCHEDULED', 'ARRIVED', 'UNLOADING', 'COMPLETED') NOT NULL DEFAULT 'ARRIVED',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel: cargo_shipments (Data Kargo & Tracking Intermodal)
DROP TABLE IF EXISTS `cargo_shipments`;
CREATE TABLE `cargo_shipments` (
    `id` VARCHAR(50) PRIMARY KEY,
    `awb_number` VARCHAR(30) NOT NULL,
    `sscc` VARCHAR(20) NOT NULL UNIQUE,
    `gtin` VARCHAR(20) NOT NULL,
    `batch_no` VARCHAR(50) NOT NULL,
    `expiry_date` DATE NOT NULL,
    `shipper` VARCHAR(100) NOT NULL,
    `consignee` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `commodity_type` ENUM('GENERAL_CARGO', 'PERISHABLE', 'DANGEROUS_GOODS', 'VALUABLE', 'PHARMA') NOT NULL DEFAULT 'GENERAL_CARGO',
    `quantity` INT NOT NULL DEFAULT 1,
    `declared_weight_kg` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `actual_weight_kg` DECIMAL(10,2) NULL,
    `truck_arrival_id` VARCHAR(50) NULL,
    `uld_id` VARCHAR(50) NULL,
    `scan_time` DATETIME NULL,
    `current_stage` INT NOT NULL DEFAULT 1,
    `status` ENUM('REGISTERED', 'SCANNED', 'SECURITY_CHECK', 'CLEARED', 'SUSPECT', 'WEIGHED', 'ALLOCATED', 'LOADED') NOT NULL DEFAULT 'REGISTERED',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`truck_arrival_id`) REFERENCES `truck_arrivals`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`uld_id`) REFERENCES `uld_containers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel: security_checks (Pemeriksaan AVSEC & X-Ray)
DROP TABLE IF EXISTS `security_checks`;
CREATE TABLE `security_checks` (
    `id` VARCHAR(50) PRIMARY KEY,
    `cargo_id` VARCHAR(50) NOT NULL,
    `scanner_type` VARCHAR(100) NOT NULL DEFAULT 'Dual-View High-Energy X-Ray (180x180cm)',
    `xray_result` ENUM('CLEARED', 'SUSPECT') NOT NULL DEFAULT 'CLEARED',
    `inspector_id` VARCHAR(50) NOT NULL DEFAULT 'AVSEC-OPR-07',
    `csd_number` VARCHAR(50) NULL COMMENT 'Consignment Security Declaration Number',
    `check_time` DATETIME NOT NULL,
    `density_reading` VARCHAR(50) NOT NULL DEFAULT 'Normal Organic/Inorganic Level',
    `notes` TEXT NULL,
    FOREIGN KEY (`cargo_id`) REFERENCES `cargo_shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel: weight_records (Data Penimbangan Kargo & ULD)
DROP TABLE IF EXISTS `weight_records`;
CREATE TABLE `weight_records` (
    `id` VARCHAR(50) PRIMARY KEY,
    `cargo_id` VARCHAR(50) NOT NULL,
    `declared_weight_kg` DECIMAL(10,2) NOT NULL,
    `actual_weight_kg` DECIMAL(10,2) NOT NULL,
    `weight_discrepancy_kg` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `scale_device_id` VARCHAR(50) NOT NULL DEFAULT 'SCALE-FLOOR-ULD-01',
    `weigh_time` DATETIME NOT NULL,
    `tolerance_status` ENUM('WITHIN_TOLERANCE', 'DISCREPANCY_WARNING', 'OVERWEIGHT') NOT NULL DEFAULT 'WITHIN_TOLERANCE',
    FOREIGN KEY (`cargo_id`) REFERENCES `cargo_shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel: scan_logs (Audit Trail Perpindahan Data & JSON Payload)
DROP TABLE IF EXISTS `scan_logs`;
CREATE TABLE `scan_logs` (
    `id` VARCHAR(50) PRIMARY KEY,
    `cargo_id` VARCHAR(50) NOT NULL,
    `stage` INT NOT NULL,
    `stage_name` VARCHAR(100) NOT NULL,
    `hardware_device` VARCHAR(100) NOT NULL,
    `software_system` VARCHAR(100) NOT NULL,
    `action` VARCHAR(150) NOT NULL,
    `json_payload` LONGTEXT NOT NULL,
    `response_payload` LONGTEXT NOT NULL,
    `http_status` INT NOT NULL DEFAULT 200,
    `timestamp` DATETIME NOT NULL,
    FOREIGN KEY (`cargo_id`) REFERENCES `cargo_shipments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Data Awal
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `organization`) VALUES
('consultant', '$2y$10$e84Womk8L0V3U/bVnB6/1.kR7Y01X10QpQWcQ1lJ7/P0c7E.M8G/e', 'Tim Konsultan Kelompok 1', 'consultant', 'ITL Trisakti - Advisory Group'),
('client', '$2y$10$e84Womk8L0V3U/bVnB6/1.kR7Y01X10QpQWcQ1lJ7/P0c7E.M8G/e', 'Direktur Operasional Terminal Kargo', 'client', 'PT Bandara Cargo Terminal Internasional');
-- Catatan: Password default untuk demo adalah 'password123' atau 1-click login.

INSERT INTO `flight_manifests` (`id`, `flight_number`, `airline`, `aircraft_type`, `origin`, `destination`, `departure_time`, `max_cargo_weight_kg`, `current_total_weight_kg`, `status`) VALUES
('FL-GA707-2026', 'GA-707', 'Garuda Indonesia Cargo', 'Boeing 737-800F', 'CGK (Jakarta)', 'DPS (Denpasar/Bali)', DATE_ADD(NOW(), INTERVAL 3 HOUR), 18000.00, 0.00, 'OPEN'),
('FL-SQ801-2026', 'SQ-801', 'Singapore Airlines Cargo', 'Boeing 777F', 'CGK (Jakarta)', 'SIN (Singapore)', DATE_ADD(NOW(), INTERVAL 6 HOUR), 45000.00, 0.00, 'OPEN');

INSERT INTO `uld_containers` (`id`, `uld_code`, `uld_type`, `tare_weight_kg`, `max_weight_kg`, `current_weight_kg`, `flight_id`, `compartment`, `status`) VALUES
('ULD-AKE-12345', 'AKE-12345-GA', 'AKE', 75.00, 1588.00, 75.00, 'FL-GA707-2026', 'AFT-LOWER-1', 'EMPTY'),
('ULD-AKE-67890', 'AKE-67890-GA', 'AKE', 75.00, 1588.00, 75.00, 'FL-GA707-2026', 'FWD-LOWER-2', 'EMPTY'),
('ULD-PMC-99120', 'PMC-99120-SQ', 'PMC', 110.00, 6800.00, 110.00, 'FL-SQ801-2026', 'MAIN-DECK-01', 'EMPTY');

INSERT INTO `truck_arrivals` (`id`, `truck_plate`, `driver_name`, `transporter_company`, `dock_slot`, `booking_code`, `arrival_time`, `status`, `notes`) VALUES
('TRK-20260926-01', 'B 9482 KXT', 'Bambang Supriyanto', 'PT Indotruck Multimoda Logistik', 'DOCK-04', 'TAS-SLOT-9482', NOW(), 'ARRIVED', 'Truk membawa muatan kargo farmasi & ekspor garmen'),
('TRK-20260926-02', 'B 9112 PXZ', 'Agus Setiawan', 'PT Lintas Samudera Darat', 'DOCK-02', 'TAS-SLOT-9112', DATE_SUB(NOW(), INTERVAL 1 HOUR), 'COMPLETED', 'Bongkar muat selesai');

INSERT INTO `cargo_shipments` (`id`, `awb_number`, `sscc`, `gtin`, `batch_no`, `expiry_date`, `shipper`, `consignee`, `description`, `commodity_type`, `quantity`, `declared_weight_kg`, `actual_weight_kg`, `truck_arrival_id`, `uld_id`, `scan_time`, `current_stage`, `status`) VALUES
('CGO-20260926-001', '126-98741022', '389912345000000128', '08991234567890', 'LOT-BIO-9988', '2027-06-30', 'PT Bio Farma Internasional', 'Bali Medical Distribution Ltd', 'Vaksin & Produk Medis Cold Chain', 'PHARMA', 80, 480.00, NULL, 'TRK-20260926-01', NULL, NULL, 1, 'REGISTERED'),
('CGO-20260926-002', '126-44589211', '389912345000000135', '08994567891234', 'BATCH-TEX-2026', '2029-12-31', 'PT Sinar Garmen Nusantara', 'Tropical Resort Boutique Bali', 'Pakaian & Tekstil Premium Ekspor', 'GENERAL_CARGO', 120, 320.00, NULL, 'TRK-20260926-01', NULL, NULL, 1, 'REGISTERED'),
('CGO-20260926-003', '126-77812049', '389912345000000142', '08997788990011', 'LOT-EXP-4401', '2026-11-20', 'PT Sumber Laut Tropis', 'Gourmet Seafood Resto Denpasar', 'Fresh Tuna Loin Perishable Pack', 'PERISHABLE', 50, 290.00, NULL, 'TRK-20260926-01', NULL, NULL, 1, 'REGISTERED');
