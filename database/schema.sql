CREATE TABLE IF NOT EXISTS xiu_admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(32) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(32) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, enabled TINYINT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parent_id INT UNSIGNED NULL,
 kind ENUM('service','knowledge','encyclopedia') NOT NULL DEFAULT 'service',
 name VARCHAR(64) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE,
 seo_slug VARCHAR(180) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', INDEX seo_slug_lookup (seo_slug),
 sort_order INT NOT NULL DEFAULT 0, enabled TINYINT NOT NULL DEFAULT 1,
 show_in_filter TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY (parent_id) REFERENCES xiu_categories(id) ON DELETE RESTRICT,
 INDEX category_tree (kind,parent_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_cities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, province VARCHAR(32) NOT NULL,
 name VARCHAR(32) NOT NULL, slug VARCHAR(80) NOT NULL UNIQUE,
 seo_slug VARCHAR(180) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', INDEX seo_slug_lookup (seo_slug),
 is_hot TINYINT NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, enabled TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_districts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, city_id INT UNSIGNED NOT NULL,
 name VARCHAR(32) NOT NULL, seo_slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sort_order INT NOT NULL DEFAULT 0, enabled TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY (city_id) REFERENCES xiu_cities(id) ON DELETE RESTRICT,
 UNIQUE INDEX district_slug (city_id,seo_slug), INDEX district_navigation (city_id,enabled,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_listings (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL, city_id INT UNSIGNED NOT NULL,
 district_id INT UNSIGNED NULL DEFAULT NULL,
 type ENUM('service','request') NOT NULL, title VARCHAR(120) NOT NULL,
 short_slug VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL, UNIQUE INDEX short_slug_unique (short_slug),
 seo_slug VARCHAR(180) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', INDEX seo_slug_lookup (seo_slug),
 address VARCHAR(255) NOT NULL, contact VARCHAR(64) NOT NULL, phone VARCHAR(32) NOT NULL,
 body MEDIUMTEXT NOT NULL, status ENUM('pending','published','rejected','offline') NOT NULL DEFAULT 'pending',
 rejection_reason VARCHAR(500) NOT NULL DEFAULT '', is_demo TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES xiu_users(id), FOREIGN KEY (category_id) REFERENCES xiu_categories(id),
 FOREIGN KEY (city_id) REFERENCES xiu_cities(id), INDEX listings_filter (status,city_id,category_id,type,id),
 FOREIGN KEY (district_id) REFERENCES xiu_districts(id) ON DELETE RESTRICT, INDEX listings_district (status,district_id,id),
 INDEX listings_owner (user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_articles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NOT NULL,
 kind ENUM('knowledge','encyclopedia') NOT NULL, title VARCHAR(120) NOT NULL,
 short_slug VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL, UNIQUE INDEX short_slug_unique (short_slug),
 seo_slug VARCHAR(180) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '', INDEX seo_slug_lookup (seo_slug),
 summary VARCHAR(500) NOT NULL DEFAULT '', body MEDIUMTEXT NOT NULL,
 body_format ENUM('text','html') NOT NULL DEFAULT 'text',
 status ENUM('draft','published') NOT NULL DEFAULT 'draft', is_demo TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (category_id) REFERENCES xiu_categories(id), INDEX articles_filter (kind,status,category_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_images (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, listing_id INT UNSIGNED NULL, article_id INT UNSIGNED NULL,
 filename VARCHAR(80) NOT NULL UNIQUE, mime VARCHAR(32) NOT NULL, width INT NOT NULL, height INT NOT NULL,
 FOREIGN KEY (listing_id) REFERENCES xiu_listings(id) ON DELETE CASCADE,
 FOREIGN KEY (article_id) REFERENCES xiu_articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_settings (
 setting_key VARCHAR(64) PRIMARY KEY, setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS xiu_navigation (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(40) NOT NULL, url VARCHAR(1000) NOT NULL,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0, enabled TINYINT NOT NULL DEFAULT 1,
 new_tab TINYINT NOT NULL DEFAULT 0, match_mode ENUM('exact','prefix') NOT NULL DEFAULT 'exact',
 INDEX navigation_order (enabled,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
