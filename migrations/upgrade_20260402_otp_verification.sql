-- Migration to add OTP verification columns/table
ALTER TABLE users
  ADD COLUMN phone VARCHAR(32) NULL,
  ADD COLUMN verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD UNIQUE KEY uq_users_phone (phone);

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
) ENGINE=InnoDB;

UPDATE users SET verified = 1 WHERE role <> 'player';
