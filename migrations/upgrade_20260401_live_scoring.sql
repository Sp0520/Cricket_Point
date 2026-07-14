-- upgrade_20260401_live_scoring.sql
ALTER TABLE matches ADD COLUMN team_a_id INT UNSIGNED NULL;
ALTER TABLE matches ADD COLUMN team_b_id INT UNSIGNED NULL;
ALTER TABLE matches ADD COLUMN match_time TIME NULL;
ALTER TABLE matches ADD COLUMN overs_limit INT UNSIGNED NOT NULL DEFAULT 20;
ALTER TABLE matches ADD COLUMN wickets_limit INT UNSIGNED NOT NULL DEFAULT 10;
ALTER TABLE matches ADD COLUMN batting_team_id INT UNSIGNED NULL;
ALTER TABLE matches ADD COLUMN bowling_team_id INT UNSIGNED NULL;
ALTER TABLE matches ADD COLUMN total_overs INT NOT NULL DEFAULT 20;
ALTER TABLE matches ADD COLUMN status ENUM('setup','live','paused','innings_break','completed') NOT NULL DEFAULT 'setup';
ALTER TABLE matches ADD COLUMN current_innings TINYINT NOT NULL DEFAULT 1;

ALTER TABLE matches ADD KEY idx_matches_team_a (team_a_id);
ALTER TABLE matches ADD KEY idx_matches_team_b (team_b_id);
ALTER TABLE matches ADD KEY idx_matches_batting_team (batting_team_id);
ALTER TABLE matches ADD KEY idx_matches_bowling_team (bowling_team_id);

ALTER TABLE matches ADD CONSTRAINT fk_matches_team_a FOREIGN KEY (team_a_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE matches ADD CONSTRAINT fk_matches_team_b FOREIGN KEY (team_b_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE matches ADD CONSTRAINT fk_matches_batting_team FOREIGN KEY (batting_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE matches ADD CONSTRAINT fk_matches_bowling_team FOREIGN KEY (bowling_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE player_match_stats ADD COLUMN balls_faced INT NOT NULL DEFAULT 0;
ALTER TABLE player_match_stats ADD COLUMN strike_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00;
ALTER TABLE player_match_stats ADD COLUMN economy DECIMAL(6,2) NOT NULL DEFAULT 0.00;
ALTER TABLE player_match_stats ADD COLUMN balls_bowled INT NOT NULL DEFAULT 0;
ALTER TABLE player_match_stats ADD COLUMN runs_conceded INT NOT NULL DEFAULT 0;
ALTER TABLE player_match_stats ADD COLUMN innings_number TINYINT NOT NULL DEFAULT 1;
ALTER TABLE player_match_stats ADD COLUMN is_out TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE player_match_stats ADD COLUMN fantasy_points INT NOT NULL DEFAULT 0;

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
  KEY idx_bbb_bowler (bowler_id),
  KEY idx_bbb_striker (striker_id),
  CONSTRAINT fk_bbb_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_bowler FOREIGN KEY (bowler_id) REFERENCES players(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_striker FOREIGN KEY (striker_id) REFERENCES players(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_bbb_fielder FOREIGN KEY (fielder_id) REFERENCES players(id) ON DELETE SET NULL ON UPDATE CASCADE
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
  KEY idx_pp_total_pts (total_pts DESC),
  CONSTRAINT fk_pp_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pp_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_bbb_created ON ball_by_ball(created_at);
CREATE INDEX idx_pms_fantasy ON player_match_stats(fantasy_points DESC);

ALTER TABLE player_match_stats DROP INDEX uq_match_player;
ALTER TABLE player_match_stats ADD UNIQUE KEY uq_match_player_inn (match_id, player_id, innings_number);
