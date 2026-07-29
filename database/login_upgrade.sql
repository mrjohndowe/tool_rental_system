USE tool_rental;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','clerk') NOT NULL DEFAULT 'clerk',
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_username (username)
) ENGINE=InnoDB;

INSERT IGNORE INTO users (full_name, username, password_hash, role)
VALUES ('System Administrator', 'admin', '$2y$12$ETjr2J51DJLOvGb02ACOGOIcgqwNEAaV7PQbxOdBCjOiJ8z9i1UVe', 'admin');
