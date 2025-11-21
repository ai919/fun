ALTER TABLE `tests`
  ADD COLUMN `display_mode` ENUM('single_page','step_by_step') NOT NULL DEFAULT 'single_page' AFTER `scoring_config`;
