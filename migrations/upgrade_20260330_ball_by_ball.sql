-- Ball-by-ball scoring tables + Dream11 points system
-- Run once on existing cricket_points database

ALTER TABLE matches
  ADD COLUMN IF NOT EXISTS batting_team_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS bowling_team_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS total_overs INT NOT NULL DEFAULT 20,
  ADD COLUMN IF NOT EXISTS status ENUM('setup','live','innings_break','completed') NOT NULL DEFAULT 'setup',
  ADD COLUMN IF NOT EXISTS current_innings TINYINT NOT NULL DEFAULT 1;

ALTER TABLE player_match_stats
  ADD COLUMN IF NOT EXISTS balls_faced INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS strike_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS economy DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS balls_bowled INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS runs_conceded INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS innings_number TINYINT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS is_out TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS fantasy_points INT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS ball_by_ball (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id      INT UNSIGNED NOT NULL,
  innings       TINYINT NOT NULL DEFAULT 1,
  over_number   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ball_number   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  bowler_id     INT UNSIGNED NULL,
  striker_id    INT UNSIGNED NULL,
  non_striker_id INT UNSIGNED NULL,
  runs_off_bat  TINYINT NOT NULL DEFAULT 0,
  extras        TINYINT NOT NULL DEFAULT 0,
  extra_type    ENUM('none','wide','no_ball','bye','leg_bye') NOT NULL DEFAULT 'none',
  is_wicket     TINYINT(1) NOT NULL DEFAULT 0,
  wicket_type   ENUM('none','bowled','caught','lbw','run_out','stumped','hit_wicket','other') NOT NULL DEFAULT 'none',
  fielder_id    INT UNSIGNED NULL,
  total_runs    TINYINT NOT NULL DEFAULT 0,
  is_legal      TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bbb_match (match_id),
  KEY idx_bbb_innings (match_id, innings),
  CONSTRAINT fk_bbb_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_bowler FOREIGN KEY (bowler_id) REFERENCES players(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_striker FOREIGN KEY (striker_id) REFERENCES players(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS player_points (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id      INT UNSIGNED NOT NULL,
  player_id     INT UNSIGNED NOT NULL,
  innings       TINYINT NOT NULL DEFAULT 1,
  batting_pts   INT NOT NULL DEFAULT 0,
  bowling_pts   INT NOT NULL DEFAULT 0,
  fielding_pts  INT NOT NULL DEFAULT 0,
  bonus_pts     INT NOT NULL DEFAULT 0,
  total_pts     INT NOT NULL DEFAULT 0,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pp_match_player_inn (match_id, player_id, innings),
  KEY idx_pp_match (match_id),
  KEY idx_pp_player (player_id),
  CONSTRAINT fk_pp_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
