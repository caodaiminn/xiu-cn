CREATE TABLE IF NOT EXISTS xiu_local_test_seeds (
 listing_id INT UNSIGNED NOT NULL PRIMARY KEY,
 city_id INT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 slot TINYINT UNSIGNED NOT NULL,
 seed_version VARCHAR(32) NOT NULL DEFAULT 'nationwide-v1',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE CASCADE,
 FOREIGN KEY (city_id) REFERENCES xiu_cities(id),
 FOREIGN KEY (category_id) REFERENCES xiu_categories(id),
 UNIQUE INDEX seed_pair_slot (city_id,category_id,slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
