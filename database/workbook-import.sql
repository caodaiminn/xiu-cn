CREATE TABLE IF NOT EXISTS xiu_workbook_imports (
 listing_id INT UNSIGNED PRIMARY KEY,
 source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_row INT UNSIGNED NOT NULL,
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 source_title VARCHAR(500) NOT NULL,
 source_category VARCHAR(120) NOT NULL,
 source_payload JSON NOT NULL,
 nationwide TINYINT UNSIGNED NOT NULL DEFAULT 1,
 imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY workbook_row (source_hash,source_row),
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
