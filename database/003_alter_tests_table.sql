-- --------------------------------------------------------
-- Migration: ensure tests table contains extended metadata fields
-- --------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE tests
  ADD COLUMN IF NOT EXISTS subtitle VARCHAR(255) DEFAULT NULL AFTER title,
  ADD COLUMN IF NOT EXISTS description TEXT AFTER subtitle,
  ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) DEFAULT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS tags VARCHAR(255) DEFAULT NULL AFTER cover_image,
  ADD COLUMN IF NOT EXISTS title_emoji VARCHAR(16) DEFAULT NULL AFTER tags,
  ADD COLUMN IF NOT EXISTS title_color VARCHAR(7) DEFAULT NULL AFTER title_emoji,
  ADD COLUMN IF NOT EXISTS status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft' AFTER title_color,
  ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE tests
  MODIFY COLUMN title_color VARCHAR(7) DEFAULT NULL;

ALTER TABLE tests
  CHANGE COLUMN status status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft'
  USING (
    CASE
      WHEN status = 'archived' THEN 'archived'
      WHEN status = 'published' THEN 'published'
      WHEN status = 'draft' THEN 'draft'
      WHEN status = 2 THEN 'archived'
      WHEN status = 1 THEN 'published'
      ELSE 'draft'
    END
  );

SET FOREIGN_KEY_CHECKS = 1;
