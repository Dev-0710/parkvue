
CREATE DATABASE IF NOT EXISTS `parkvue_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `parkvue_db`;

CREATE TABLE IF NOT EXISTS `zones` (
    `code` CHAR(1) PRIMARY KEY,
    `label` VARCHAR(50) NOT NULL,
    `type` VARCHAR(30) NOT NULL,
    `capacity` INT NOT NULL,
    `hourly_rate` DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS `bays` (
    `id` VARCHAR(10) PRIMARY KEY,
    `zone_code` CHAR(1) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'free',
    `plate` VARCHAR(20) NULL,
    `category` VARCHAR(30) NULL,
    `ticket` VARCHAR(20) NULL,
    `checkin` DATETIME NULL,
    `dwell` VARCHAR(50) NULL,
    FOREIGN KEY (`zone_code`) REFERENCES `zones`(`code`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket` VARCHAR(20) NULL,
    `plate` VARCHAR(20) NULL,
    `zone_bay` VARCHAR(20) NULL,
    `event_type` VARCHAR(30) NOT NULL,
    `tender` VARCHAR(30) NULL,
    `amount` DECIMAL(10,2) DEFAULT 0.00,
    `status` VARCHAR(20) DEFAULT 'COMPLETED',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS `shift_metrics` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `operator` VARCHAR(50) DEFAULT 'Alan Vance',
    `badge` VARCHAR(10) DEFAULT 'B',
    `shift_start` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `vehicles_processed` INT DEFAULT 0,
    `cash_in_drawer` DECIMAL(10,2) DEFAULT 0.00,
    `digital_fastag_total` DECIMAL(10,2) DEFAULT 0.00,
    `barrier_overrides` INT DEFAULT 0
);

INSERT INTO `zones` (`code`, `label`, `type`, `capacity`, `hourly_rate`) VALUES
('A', 'Zone A — 2-Wheeler', '2-wheeler', 60, 20.00),
('B', 'Zone B — Sedan & SUV', 'sedan-suv', 120, 40.00),
('C', 'Zone C — EV Charging', 'ev', 16, 60.00),
('V', 'Zone V — Valet Staging', 'valet', 25, 80.00);

INSERT INTO `bays` (`id`, `zone_code`, `status`, `plate`, `category`, `ticket`, `checkin`, `dwell`) VALUES
('A-01', 'A', 'occupied', 'MH12AB1234', '2-Wheeler', 'TKT-7390', NOW() - INTERVAL 2 HOUR, '2h 15m'),
('A-02', 'A', 'occupied', 'DL7SZ9876',  '2-Wheeler', 'TKT-7391', NOW() - INTERVAL 3 HOUR, '2h 40m'),
('A-03', 'A', 'free',     NULL,          '2-Wheeler', NULL,       NULL, NULL),
('A-04', 'A', 'free',     NULL,          '2-Wheeler', NULL,       NULL, NULL),
('A-05', 'A', 'free',     NULL,          '2-Wheeler', NULL,       NULL, NULL),
('A-06', 'A', 'free',     NULL,          '2-Wheeler', NULL,       NULL, NULL),
('L1-A01', 'B', 'occupied', 'CA 7XYZ89', 'Sedan', 'TKT-7395', NOW() - INTERVAL 2 HOUR, '2h 14m'),
('L1-A02', 'B', 'occupied', 'NY K49-WQP', 'SUV',  'TKT-7402', NOW() - INTERVAL 5 HOUR, '5h 02m'),
('L1-A03', 'B', 'free',     NULL,         'Sedan', NULL,       NULL, NULL),
('L1-A04', 'B', 'free',     NULL,         'Sedan', NULL,       NULL, NULL),
('L1-A05', 'B', 'free',     NULL,         'Sedan', NULL,       NULL, NULL),
('L1-A06', 'B', 'free',     NULL,         'Sedan', NULL,       NULL, NULL),
('L2-EV01', 'C', 'charging', 'WA 9EV-TES', 'EV', 'TKT-7396', NOW() - INTERVAL 1 HOUR, '1h 10m'),
('L2-EV02', 'C', 'free',     NULL,         'EV', NULL,       NULL, NULL),
('L2-EV03', 'C', 'free',     NULL,         'EV', NULL,       NULL, NULL),
('L1-V01',  'V', 'occupied', 'KA09EF4321', 'Valet', 'TKT-7392', NOW() - INTERVAL 1 HOUR, '1h 25m'),
('L1-V02',  'V', 'free',     NULL,         'Valet', NULL,       NULL, NULL);

INSERT INTO `shift_metrics` (`id`, `operator`, `badge`, `shift_start`, `vehicles_processed`, `cash_in_drawer`, `digital_fastag_total`, `barrier_overrides`)
VALUES (1, 'Alan Vance', 'B', NOW() - INTERVAL 8 HOUR, 14, 480.00, 1240.00, 0)
ON DUPLICATE KEY UPDATE `id`=1;
