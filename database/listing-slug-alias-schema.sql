CREATE TABLE IF NOT EXISTS xiu_listing_slug_aliases (
 slug VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 listing_id INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE CASCADE,
 INDEX listing_slug_alias_owner (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
