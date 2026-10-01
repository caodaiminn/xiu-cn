CREATE TABLE IF NOT EXISTS xiu_mail_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 listing_id INT UNSIGNED NULL,
 event_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind ENUM('listing','test') NOT NULL DEFAULT 'listing',
 recipient VARCHAR(254) NOT NULL,
 subject VARCHAR(255) NOT NULL,
 body TEXT NOT NULL,
 status ENUM('queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 last_error VARCHAR(1000) NOT NULL DEFAULT '',
 message_id VARCHAR(255) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_attempt_at DATETIME NULL,
 sent_at DATETIME NULL,
 UNIQUE KEY mail_event_unique (event_key),
 KEY mail_queue (status,id),
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
