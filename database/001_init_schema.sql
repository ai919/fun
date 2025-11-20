-- --------------------------------------------------------
-- Schema initialization for DoFun quiz platform (fun_quiz)
-- --------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS test_run_scores;
DROP TABLE IF EXISTS test_runs;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS dimensions;
DROP TABLE IF EXISTS tests;
DROP TABLE IF EXISTS admin_users;

CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_users (username, password_hash, display_name)
VALUES ('admin', '$2y$10$9YnmOdXwjZ1aaznXjyb0e.7qpsIr0fcWpB0l6kLjtY4sDXcJi4DjO', 'Administrator');

CREATE TABLE tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) DEFAULT NULL,
  description TEXT,
  title_color VARCHAR(7) DEFAULT '#4f46e5',
  tags VARCHAR(255) DEFAULT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  sort_order INT NOT NULL DEFAULT 0,
  scoring_mode ENUM('simple','dimensions','range','custom') NOT NULL DEFAULT 'simple',
  scoring_config JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tests_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dimensions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  key_name VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  UNIQUE KEY uk_dimensions (test_id, key_name),
  CONSTRAINT fk_dimensions_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  question_text TEXT NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_questions_test (test_id, sort_order),
  CONSTRAINT fk_questions_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  option_key VARCHAR(10) DEFAULT NULL,
  option_text TEXT NOT NULL,
  map_result_code VARCHAR(255) DEFAULT NULL,
  score_value DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_options_question (question_id),
  CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  image_url VARCHAR(255) DEFAULT NULL,
  min_score INT NOT NULL DEFAULT 0,
  max_score INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_results_code (test_id, code),
  KEY idx_results_range (test_id, min_score, max_score),
  CONSTRAINT fk_results_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  result_id INT UNSIGNED DEFAULT NULL,
  user_identifier VARCHAR(64) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  total_score DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_test_runs_test (test_id, created_at),
  CONSTRAINT fk_runs_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_runs_result FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_run_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_run_id BIGINT UNSIGNED NOT NULL,
  dimension_key VARCHAR(64) DEFAULT NULL,
  score_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  KEY idx_scores_run (test_run_id),
  KEY idx_scores_dimension (dimension_key),
  CONSTRAINT fk_scores_run FOREIGN KEY (test_run_id) REFERENCES test_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
