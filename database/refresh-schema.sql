CREATE TABLE IF NOT EXISTS xiu_listing_refreshes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 listing_id INT UNSIGNED NULL,
 refresh_date DATE NOT NULL,
 refreshed_at DATETIME(6) NOT NULL,
 FOREIGN KEY (user_id) REFERENCES xiu_users(id) ON DELETE CASCADE,
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE SET NULL,
 UNIQUE INDEX refresh_daily_quota (user_id,refresh_date),
 INDEX refresh_listing (listing_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
