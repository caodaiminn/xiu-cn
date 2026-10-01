CREATE TABLE IF NOT EXISTS xiu_listing_sources (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 listing_id INT UNSIGNED NOT NULL UNIQUE,
 source_url VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 city_id INT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 source_title VARCHAR(120) NOT NULL,
 source_category VARCHAR(120) NOT NULL,
 source_category_url VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 company VARCHAR(120) NOT NULL,
 source_published_at DATETIME NOT NULL,
 collected_at DATETIME NOT NULL,
 imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE CASCADE,
 FOREIGN KEY (city_id) REFERENCES xiu_cities(id),
 FOREIGN KEY (category_id) REFERENCES xiu_categories(id),
 INDEX imported_pair (city_id,category_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
