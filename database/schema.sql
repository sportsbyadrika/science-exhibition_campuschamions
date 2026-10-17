-- =====================================================================
-- CBSE South Zone Sahodaya Complex - Science Exhibition Portal
-- Database schema (MySQL 8 / MariaDB 10.4+)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Institutions (participating schools)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS institutions (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref_no             VARCHAR(40)  NOT NULL UNIQUE,
  affiliation_code   VARCHAR(20)  NOT NULL UNIQUE,
  name               VARCHAR(255) NOT NULL,
  address            VARCHAR(255) NULL,
  district           VARCHAR(100) NULL,
  head_name          VARCHAR(150) NOT NULL,
  head_designation   VARCHAR(100) NOT NULL,
  head_email         VARCHAR(190) NOT NULL,
  head_phone         VARCHAR(15)  NOT NULL,
  coord_name         VARCHAR(150) NOT NULL,
  coord_designation  VARCHAR(100) NOT NULL,
  coord_email        VARCHAR(190) NOT NULL,
  coord_phone        VARCHAR(15)  NOT NULL,
  status             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  status_remarks     VARCHAR(255) NULL,
  approved_by        INT UNSIGNED NULL,
  approved_at        DATETIME NULL,
  submission_status  ENUM('draft','submitted','reopened') NOT NULL DEFAULT 'draft',
  submission_ref     VARCHAR(40) NULL,
  submitted_at       DATETIME NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Users: superadmin (hidden) / admin / institution
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role                ENUM('superadmin','admin','institution') NOT NULL,
  institution_id      INT UNSIGNED NULL,
  name                VARCHAR(150) NOT NULL,
  email               VARCHAR(190) NOT NULL UNIQUE,
  phone               VARCHAR(15)  NULL,
  password_hash       VARCHAR(255) NOT NULL,
  status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
  reset_token_hash    CHAR(64) NULL,
  reset_expires_at    DATETIME NULL,
  last_login_at       DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Key/value application settings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at    DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Masters: categories (class brackets)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  description  VARCHAR(255) NULL,
  classes      VARCHAR(255) NOT NULL COMMENT 'Comma separated class names',
  sort_order   INT NOT NULL DEFAULT 0,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Masters: events (Working Model, Still Model, ...) with configuration & fees
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code                  VARCHAR(20)  NOT NULL UNIQUE,
  name                  VARCHAR(150) NOT NULL,
  description           TEXT NULL,
  venue                 VARCHAR(190) NULL,
  event_date            DATE NULL,
  registration_fee      DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Fee per team',
  min_members           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  max_members           TINYINT UNSIGNED NOT NULL DEFAULT 2,
  max_teams_per_school  SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Per category',
  photo_required        TINYINT(1) NOT NULL DEFAULT 1,
  registration_open     TINYINT(1) NOT NULL DEFAULT 1,
  status                ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order            INT NOT NULL DEFAULT 0,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_categories (
  event_id     INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, category_id),
  CONSTRAINT fk_ec_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_ec_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Masters: food coupon heads (Breakfast, Lunch, ...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS food_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  description  VARCHAR(255) NULL,
  meal_date    DATE NULL,
  rate         DECIMAL(10,2) NOT NULL DEFAULT 0,
  sort_order   INT NOT NULL DEFAULT 0,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Team entries
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS teams (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id  INT UNSIGNED NOT NULL,
  event_id        INT UNSIGNED NOT NULL,
  category_id     INT UNSIGNED NOT NULL,
  team_code       VARCHAR(40) NOT NULL UNIQUE,
  project_title   VARCHAR(255) NOT NULL,
  guide_teacher   VARCHAR(150) NULL,
  fee             DECIMAL(10,2) NOT NULL DEFAULT 0,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  remarks         VARCHAR(255) NULL,
  reviewed_by     INT UNSIGNED NULL,
  reviewed_at     DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_team_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
  CONSTRAINT fk_team_event FOREIGN KEY (event_id) REFERENCES events(id),
  CONSTRAINT fk_team_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id     INT UNSIGNED NOT NULL,
  position    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  name        VARCHAR(150) NOT NULL,
  class_name  VARCHAR(50) NOT NULL,
  gender      ENUM('Male','Female','Other') NULL,
  photo_path  VARCHAR(255) NULL,
  CONSTRAINT fk_member_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Food coupon requests (head wise quantity per institution)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS food_requests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id  INT UNSIGNED NOT NULL,
  food_item_id    INT UNSIGNED NOT NULL,
  quantity        INT UNSIGNED NOT NULL DEFAULT 0,
  rate            DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inst_item (institution_id, food_item_id),
  CONSTRAINT fk_food_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
  CONSTRAINT fk_food_item FOREIGN KEY (food_item_id) REFERENCES food_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fund transfer details
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id  INT UNSIGNED NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  utr_no          VARCHAR(60) NOT NULL,
  payment_date    DATE NOT NULL,
  payment_mode    VARCHAR(30) NOT NULL DEFAULT 'NEFT',
  remitter_bank   VARCHAR(150) NULL,
  proof_path      VARCHAR(255) NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approved_amount DECIMAL(12,2) NULL,
  remarks         VARCHAR(255) NULL,
  reviewed_by     INT UNSIGNED NULL,
  reviewed_at     DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_utr (utr_no),
  CONSTRAINT fk_payment_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Activity log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(80) NOT NULL,
  details     VARCHAR(500) NULL,
  ip_address  VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Seed data
-- =====================================================================
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_title',            'Sahodaya Science Fair Registration Portal'),
('organisation_name',     'CBSE South Zone Sahodaya Complex'),
('event_title',           'Science Fair 2026'),
('event_year_label',      'South Zone 2026-27'),
('event_dates',           'Nov 14–16, 2026'),
('event_host',            'Trivandrum Chapter'),
('event_venue',           'Trivandrum, Kerala'),
('registration_closes',   '2026-10-31 17:00'),
('registration_open',     '1'),
('require_institution_approval', '1'),
('support_phone',         '+91 471 278 9901'),
('support_email',         'sciencefair@sahodayasouth.org'),
('notice_text',           'Participation Requests open exclusively for CBSE Affiliated Schools in the Southern Region. Keep Affiliation Code handy.'),
('bank_account_name',     'SAHODAYA COMPLEX SZ SCIENCE'),
('bank_name_branch',      'State Bank of India, Trivandrum'),
('bank_account_no',       '40291083921'),
('bank_ifsc',             'SBIN0007821'),
('bank_upi',              'sahodayascience@sbi'),
('mail_enabled',          '0'),
('smtp_host',             ''),
('smtp_port',             '587'),
('smtp_secure',           'tls'),
('smtp_username',         ''),
('smtp_password',         ''),
('mail_from_email',       'no-reply@sahodayasouth.org'),
('mail_from_name',        'Sahodaya Science Fair');

INSERT INTO categories (id, name, description, classes, sort_order) VALUES
(1, 'Category 1', 'Students from Class 2 and 3', 'Class 2, Class 3', 1),
(2, 'Category 2', 'Students from Class 4, 5 and 6', 'Class 4, Class 5, Class 6', 2),
(3, 'Category 3', 'Students from Class 7 and 8', 'Class 7, Class 8', 3),
(4, 'Category 4', 'Students from Class 9 to 12', 'Class 9, Class 10, Class 11, Class 12', 4)
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO events (id, code, name, description, registration_fee, min_members, max_members, max_teams_per_school, sort_order) VALUES
(1, 'WM', 'Working Model', 'Functional working exhibit demonstrating a scientific principle.', 800, 2, 2, 1, 1),
(2, 'SM', 'Still Model',   'Static model / chart based exhibit.',                              800, 2, 2, 1, 2)
ON DUPLICATE KEY UPDATE id = id;

INSERT IGNORE INTO event_categories (event_id, category_id) VALUES
(1,1),(1,2),(1,3),(1,4),(2,1),(2,2),(2,3),(2,4);

INSERT INTO food_items (id, name, description, rate, sort_order) VALUES
(1, 'Breakfast', 'Morning breakfast coupon', 60, 1),
(2, 'Lunch',     'Vegetarian lunch coupon',  120, 2),
(3, 'Evening Snacks', 'Tea & snacks coupon', 40, 3)
ON DUPLICATE KEY UPDATE id = id;
