

-- Reset existing schema to avoid foreign key errors when re-importing.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS player_points;
DROP TABLE IF EXISTS ball_by_ball;
DROP TABLE IF EXISTS tournament_registrations;
DROP TABLE IF EXISTS team_players;
DROP TABLE IF EXISTS player_match_stats;
DROP TABLE IF EXISTS otp_verifications;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS tournaments;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS players;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS players (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  photo_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_players_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','player','organizer') NOT NULL DEFAULT 'player',
  player_id INT UNSIGNED NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  KEY idx_users_role (role),
  KEY idx_users_player_id (player_id),
  CONSTRAINT fk_users_player
    FOREIGN KEY (player_id) REFERENCES players(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS otp_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  purpose ENUM('registration','login') NOT NULL DEFAULT 'registration',
  otp_hash VARCHAR(255) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  last_sent_at DATETIME NOT NULL,
  verified_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_otp_user (user_id),
  KEY idx_otp_email (email),
  KEY idx_otp_phone (phone),
  CONSTRAINT fk_otp_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teams (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_name VARCHAR(200) NOT NULL,
  created_by_player_id INT UNSIGNED NULL,
  registration_source ENUM('captain','admin') NOT NULL DEFAULT 'admin',
  contact_phone VARCHAR(32) NOT NULL DEFAULT '',
  logo_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_teams_name (team_name),
  KEY idx_teams_captain (created_by_player_id),
  CONSTRAINT fk_teams_created_by_player
    FOREIGN KEY (created_by_player_id) REFERENCES players(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tournaments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_name VARCHAR(200) NOT NULL,
  owner_user_id INT UNSIGNED NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  venue VARCHAR(200) NULL,
  entry_fees DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  description TEXT NULL,
  max_teams INT UNSIGNED NOT NULL DEFAULT 16,
  overs_per_match INT UNSIGNED NOT NULL DEFAULT 20,
  wickets_per_team INT UNSIGNED NOT NULL DEFAULT 10,
  registration_open_from DATE NULL,
  registration_open_to DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tournaments_start (start_date),
  KEY idx_tournaments_registration (registration_open_from, registration_open_to),
  KEY idx_tournaments_owner (owner_user_id),
  CONSTRAINT fk_tournaments_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_name VARCHAR(200) NOT NULL,
  match_date DATE NOT NULL,
  match_time TIME NULL,
  venue VARCHAR(200) NULL,
  overs_limit INT UNSIGNED NOT NULL DEFAULT 20,
  wickets_limit INT UNSIGNED NOT NULL DEFAULT 10,
  batting_team_id INT UNSIGNED NULL,
  bowling_team_id INT UNSIGNED NULL,
  total_overs INT UNSIGNED NOT NULL DEFAULT 20,
  man_of_match_player_id INT UNSIGNED NULL,
  man_of_match_points INT NOT NULL DEFAULT 0,
  tournament_id INT UNSIGNED NULL,
  owner_user_id INT UNSIGNED NULL,
  team_a_id INT UNSIGNED NULL,
  team_b_id INT UNSIGNED NULL,
  gully_state LONGTEXT NULL,
  status ENUM('scheduled','setup','live','paused','innings_break','completed') NOT NULL DEFAULT 'scheduled',
  current_innings TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_matches_date (match_date),
  KEY idx_matches_mom (man_of_match_player_id),
  KEY idx_matches_tournament (tournament_id),
  KEY idx_matches_owner (owner_user_id),
  KEY idx_matches_team_a (team_a_id),
  KEY idx_matches_team_b (team_b_id),
  KEY idx_matches_batting_team (batting_team_id),
  KEY idx_matches_bowling_team (bowling_team_id),
  CONSTRAINT fk_matches_mom_player
    FOREIGN KEY (man_of_match_player_id) REFERENCES players(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_matches_tournament
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_matches_owner
    FOREIGN KEY (owner_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_matches_team_a
    FOREIGN KEY (team_a_id) REFERENCES teams(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_matches_team_b
    FOREIGN KEY (team_b_id) REFERENCES teams(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_matches_batting_team
    FOREIGN KEY (batting_team_id) REFERENCES teams(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_matches_bowling_team
    FOREIGN KEY (bowling_team_id) REFERENCES teams(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS player_match_stats (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  innings_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
  runs INT NOT NULL DEFAULT 0,
  fours INT NOT NULL DEFAULT 0,
  sixes INT NOT NULL DEFAULT 0,
  wickets INT NOT NULL DEFAULT 0,
  catches INT NOT NULL DEFAULT 0,
  runouts INT NOT NULL DEFAULT 0,
  stumpings INT NOT NULL DEFAULT 0,
  maiden_overs INT NOT NULL DEFAULT 0,
  balls_faced INT NOT NULL DEFAULT 0,
  strike_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  economy DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  balls_bowled INT NOT NULL DEFAULT 0,
  runs_conceded INT NOT NULL DEFAULT 0,
  is_out TINYINT(1) NOT NULL DEFAULT 0,
  fantasy_points INT NOT NULL DEFAULT 0,
  points INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_match_player_inn (match_id, player_id, innings_number),
  KEY idx_stats_match (match_id),
  KEY idx_stats_player (player_id),
  KEY idx_stats_points (points),
  CONSTRAINT fk_stats_match
    FOREIGN KEY (match_id) REFERENCES matches(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_stats_player
    FOREIGN KEY (player_id) REFERENCES players(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS team_players (
  team_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  role ENUM('Batsman','Bowler','All-rounder','Wicketkeeper') NOT NULL DEFAULT 'Batsman',
  jersey_number INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (team_id, player_id),
  UNIQUE KEY uq_team_players_player (player_id),
  KEY idx_team_players_team (team_id),
  CONSTRAINT fk_team_players_team
    FOREIGN KEY (team_id) REFERENCES teams(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_team_players_player
    FOREIGN KEY (player_id) REFERENCES players(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tournament_registrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id INT UNSIGNED NOT NULL,
  registrant_type ENUM('player','team') NOT NULL,
  player_id INT UNSIGNED NULL,
  team_id INT UNSIGNED NULL,
  contact_phone VARCHAR(32) NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reg_player (tournament_id, player_id),
  UNIQUE KEY uq_reg_team (tournament_id, team_id),
  KEY idx_reg_tournament (tournament_id),
  CONSTRAINT fk_reg_tournament
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reg_player
    FOREIGN KEY (player_id) REFERENCES players(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reg_team
    FOREIGN KEY (team_id) REFERENCES teams(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ball_by_ball (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id INT UNSIGNED NOT NULL,
  innings TINYINT NOT NULL DEFAULT 1,
  over_number TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ball_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
  bowler_id INT UNSIGNED NULL,
  striker_id INT UNSIGNED NULL,
  non_striker_id INT UNSIGNED NULL,
  runs_off_bat TINYINT NOT NULL DEFAULT 0,
  extras TINYINT NOT NULL DEFAULT 0,
  extra_type ENUM('none','wide','no_ball','bye','leg_bye') NOT NULL DEFAULT 'none',
  is_wicket TINYINT(1) NOT NULL DEFAULT 0,
  wicket_type ENUM('none','bowled','caught','lbw','run_out','stumped','hit_wicket','other') NOT NULL DEFAULT 'none',
  fielder_id INT UNSIGNED NULL,
  total_runs TINYINT NOT NULL DEFAULT 0,
  is_legal TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bbb_match (match_id),
  KEY idx_bbb_innings (match_id, innings),
  KEY idx_bbb_bowler (bowler_id),
  KEY idx_bbb_striker (striker_id),
  CONSTRAINT fk_bbb_match
    FOREIGN KEY (match_id) REFERENCES matches(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_bowler
    FOREIGN KEY (bowler_id) REFERENCES players(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_striker
    FOREIGN KEY (striker_id) REFERENCES players(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_fielder
    FOREIGN KEY (fielder_id) REFERENCES players(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS player_points (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  innings TINYINT NOT NULL DEFAULT 1,
  batting_pts INT NOT NULL DEFAULT 0,
  bowling_pts INT NOT NULL DEFAULT 0,
  fielding_pts INT NOT NULL DEFAULT 0,
  bonus_pts INT NOT NULL DEFAULT 0,
  total_pts INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pp_match_player_inn (match_id, player_id, innings),
  KEY idx_pp_match (match_id),
  KEY idx_pp_player (player_id),
  CONSTRAINT fk_pp_match
    FOREIGN KEY (match_id) REFERENCES matches(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pp_player
    FOREIGN KEY (player_id) REFERENCES players(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed admin
-- email: admin@cricket.local

-- password: admin123
INSERT INTO users (name, email, password_hash, role, verified)
SELECT 'Admin', 'admin@cricket.local', '$2y$10$UuiL9Hu1z03WMmJC2IU8GO5L8i/y3ZunNWM/RQOURY2EXrESxJIIG', 'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='admin@cricket.local');

