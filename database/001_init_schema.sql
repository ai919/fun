-- --------------------------------------------------------
-- Schema initialization for DoFun quiz platform
-- --------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS test_run_scores;
DROP TABLE IF EXISTS test_runs;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS dimensions;
DROP TABLE IF EXISTS tests;

CREATE TABLE tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) DEFAULT NULL,
  description TEXT,
  cover_image VARCHAR(255) DEFAULT NULL,
  tags VARCHAR(255) DEFAULT NULL,
  title_emoji VARCHAR(16) DEFAULT NULL,
  title_color VARCHAR(20) DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tests_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dimensions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  key_name VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  UNIQUE KEY uk_dimensions (test_id, key_name),
  CONSTRAINT fk_dimensions_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  order_number INT UNSIGNED NOT NULL DEFAULT 1,
  content TEXT NOT NULL,
  KEY idx_questions_test (test_id, order_number),
  CONSTRAINT fk_questions_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  dimension_key VARCHAR(64) DEFAULT NULL,
  score INT NOT NULL DEFAULT 0,
  KEY idx_options_question (question_id),
  CONSTRAINT fk_options_question
    FOREIGN KEY (question_id) REFERENCES questions(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  dimension_key VARCHAR(64) NOT NULL,
  range_min INT NOT NULL,
  range_max INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  KEY idx_results_range (test_id, dimension_key, range_min, range_max),
  CONSTRAINT fk_results_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  client_ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_test_runs_test (test_id, created_at),
  CONSTRAINT fk_runs_test
    FOREIGN KEY (test_id) REFERENCES tests(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_run_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  dimension_key VARCHAR(64) NOT NULL,
  score DECIMAL(10,2) NOT NULL,
  result_id INT UNSIGNED DEFAULT NULL,
  KEY idx_scores_run (run_id),
  KEY idx_scores_result (result_id),
  CONSTRAINT fk_scores_run
    FOREIGN KEY (run_id) REFERENCES test_runs(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_scores_result
    FOREIGN KEY (result_id) REFERENCES results(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
