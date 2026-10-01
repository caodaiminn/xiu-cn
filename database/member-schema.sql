CREATE TABLE IF NOT EXISTS xiu_user_verifications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL UNIQUE,
 type ENUM('personal','company') NOT NULL,
 subject_name VARCHAR(120) NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 rejection_reason VARCHAR(500) NOT NULL DEFAULT '',
 reviewed_by INT UNSIGNED NULL,
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES xiu_users(id) ON DELETE CASCADE,
 FOREIGN KEY (reviewed_by) REFERENCES xiu_admins(id) ON DELETE SET NULL,
 INDEX verification_review (status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS xiu_verification_files (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 verification_id INT UNSIGNED NOT NULL,
 role ENUM('id_front','id_back','business_license') NOT NULL,
 filename VARCHAR(80) NOT NULL UNIQUE,
 mime VARCHAR(32) NOT NULL,
 width INT NOT NULL,
 height INT NOT NULL,
 FOREIGN KEY (verification_id) REFERENCES xiu_user_verifications(id) ON DELETE CASCADE,
 UNIQUE INDEX verification_role (verification_id,role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
