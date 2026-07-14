-- Migration to add payment support for organizers and registrations in INR (₹)
ALTER TABLE users ADD COLUMN is_paid_member TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE tournament_registrations ADD COLUMN payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid';
ALTER TABLE tournament_registrations ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL;
ALTER TABLE tournament_registrations ADD COLUMN payment_tx_id VARCHAR(100) DEFAULT NULL;
