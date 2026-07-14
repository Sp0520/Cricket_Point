-- upgrade_20260714_types.sql
-- Aligns database types to prevent foreign key errors (errno 150)
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE matches MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE teams MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE matches MODIFY man_of_match_player_id INT UNSIGNED NULL;
ALTER TABLE teams MODIFY created_by_player_id INT UNSIGNED NULL;

SET FOREIGN_KEY_CHECKS = 1;
