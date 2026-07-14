-- upgrade_20260328_organizer_gully.sql
ALTER TABLE users MODIFY role ENUM('admin','player','organizer') NOT NULL DEFAULT 'player';

ALTER TABLE tournaments ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER tournament_name;
ALTER TABLE tournaments ADD KEY idx_tournaments_owner (owner_user_id);
ALTER TABLE tournaments ADD CONSTRAINT fk_tournaments_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE matches ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER tournament_id;
ALTER TABLE matches ADD COLUMN gully_state LONGTEXT NULL COMMENT 'JSON blob for live gully scoreboard';
ALTER TABLE matches ADD KEY idx_matches_owner (owner_user_id);
ALTER TABLE matches ADD CONSTRAINT fk_matches_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE teams ADD COLUMN created_by_player_id INT UNSIGNED NULL AFTER team_name;
ALTER TABLE teams ADD COLUMN registration_source ENUM('captain','admin') NOT NULL DEFAULT 'admin';
ALTER TABLE teams ADD KEY idx_teams_captain (created_by_player_id);
ALTER TABLE teams ADD CONSTRAINT fk_teams_created_by_player FOREIGN KEY (created_by_player_id) REFERENCES players(id) ON DELETE SET NULL ON UPDATE CASCADE;
