CREATE DATABASE IF NOT EXISTS eventrix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventrix;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student', 'organizer', 'admin') NOT NULL DEFAULT 'student',
  reg_no VARCHAR(100) DEFAULT NULL,
  department VARCHAR(150) DEFAULT NULL,
  theme VARCHAR(30) NOT NULL DEFAULT 'light',
  language VARCHAR(10) NOT NULL DEFAULT 'en',
  compact_view TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_auth TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email_role (email, role),
  UNIQUE KEY uq_users_reg_no (reg_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  start_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_date DATE NOT NULL,
  end_time TIME NOT NULL,
  registration_deadline DATE DEFAULT NULL,
  location VARCHAR(190) NOT NULL,
  venue_type VARCHAR(50) NOT NULL,
  venue_number VARCHAR(80) NOT NULL,
  category VARCHAR(80) NOT NULL,
  max_participants INT UNSIGNED NOT NULL DEFAULT 100,
  registration_link VARCHAR(255) DEFAULT NULL,
  reminder_days INT NOT NULL DEFAULT 0,
  access_type ENUM('free','paid') NOT NULL DEFAULT 'free',
  fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(120) DEFAULT NULL,
  payment_instructions TEXT DEFAULT NULL,
  poster_json JSON DEFAULT NULL,
  organizer_id INT UNSIGNED NOT NULL,
  organizer_name VARCHAR(150) NOT NULL,
  organizer_email VARCHAR(190) NOT NULL,
  organizer_dept VARCHAR(150) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_status (status),
  KEY idx_events_organizer (organizer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_registrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_user (event_id, user_id),
  KEY idx_registrations_user (user_id),
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  target_user_id INT UNSIGNED DEFAULT NULL,
  target_role ENUM('student', 'organizer', 'admin') DEFAULT NULL,
  title VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
  action_type VARCHAR(80) NOT NULL DEFAULT 'manual',
  meta_json JSON DEFAULT NULL,
  read_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_target_user (target_user_id),
  KEY idx_notifications_target_role (target_role),
  KEY idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, password, role, reg_no, department, is_active)
SELECT 'Student User', 'student@campus.edu', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'student', 'BCS/23456/2301/DT', 'Computer Science', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'student@campus.edu' AND role = 'student');

UPDATE users
SET reg_no = 'BCS/23456/2301/DT', department = 'Computer Science'
WHERE email = 'student@campus.edu' AND role = 'student' AND (reg_no IS NULL OR reg_no = '' OR reg_no NOT LIKE '%/DT');

INSERT INTO users (name, email, password, role, department, is_active)
SELECT 'Organizer User', 'organizer@campus.edu', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'organizer', 'Student Affairs', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'organizer@campus.edu' AND role = 'organizer');

INSERT INTO users (name, email, password, role, department, is_active)
SELECT 'Admin User', 'admin@campus.edu', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'admin', 'Administration', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@campus.edu' AND role = 'admin');
