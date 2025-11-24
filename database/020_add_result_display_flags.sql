ALTER TABLE `tests`
  ADD COLUMN `show_secondary_archetype` TINYINT(1) NOT NULL DEFAULT 1 AFTER `scoring_config`,
  ADD COLUMN `show_dimension_table` TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_secondary_archetype`;


