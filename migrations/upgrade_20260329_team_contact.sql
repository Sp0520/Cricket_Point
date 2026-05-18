-- Contact for captain-led teams; snapshot on team tournament registration.
ALTER TABLE teams
  ADD COLUMN contact_phone VARCHAR(32) NOT NULL DEFAULT '' AFTER registration_source;

ALTER TABLE tournament_registrations
  ADD COLUMN contact_phone VARCHAR(32) NULL AFTER team_id;
