CREATE DATABASE IF NOT EXISTS clinic_scheduler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinic_scheduler;

-- Non-destructive setup: existing tables are not dropped.

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  address VARCHAR(255) NOT NULL,
  description VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE patients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_code VARCHAR(20) NOT NULL UNIQUE,
  patient_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appointment_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
  INDEX idx_appointment_date (appointment_date),
  INDEX idx_appointment_branch_date (branch_id, appointment_date),
  INDEX idx_appointment_status (status)
) ENGINE=InnoDB;

INSERT IGNORE INTO admins (username,password_hash) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

INSERT IGNORE INTO branches (name,address,description,sort_order) VALUES
('Adjustment with Main Branch','Main Branch, Cebu','Book your adjustment appointment at the Main Branch.',1),
('Adjustment with Lapu Lapu Branch','Lapu-Lapu City, Cebu','Book your adjustment appointment at the Lapu Lapu Branch.',2);
