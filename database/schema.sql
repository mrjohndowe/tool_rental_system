CREATE DATABASE IF NOT EXISTS tool_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    work_email VARCHAR(190) NOT NULL,
    badge_number VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_email (work_email), UNIQUE KEY uq_employee_badge (badge_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tool_name VARCHAR(160) NOT NULL,
    category VARCHAR(100) NULL,
    manufacturer VARCHAR(100) NULL,
    model_number VARCHAR(100) NULL,
    serial_number VARCHAR(150) NOT NULL,
    internal_id VARCHAR(100) NOT NULL,
    tool_location VARCHAR(190) NOT NULL,
    status ENUM('available','checked_out','maintenance','retired') NOT NULL DEFAULT 'available',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tool_serial (serial_number), UNIQUE KEY uq_tool_internal (internal_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bundles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bundle_name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bundle_name (bundle_name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bundle_tools (
    bundle_id INT UNSIGNED NOT NULL,
    tool_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (bundle_id, tool_id),
    CONSTRAINT fk_bundle_tools_bundle FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE CASCADE,
    CONSTRAINT fk_bundle_tools_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS accessories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accessory_name VARCHAR(160) NOT NULL,
    internal_id VARCHAR(100) NULL,
    tool_location VARCHAR(190) NULL,
    quantity_total INT UNSIGNED NOT NULL DEFAULT 1,
    quantity_available INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_accessory_internal (internal_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkout_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    issued_by VARCHAR(150) NOT NULL,
    checked_out_at DATETIME NOT NULL,
    due_at DATETIME NOT NULL,
    returned_at DATETIME NULL,
    received_by VARCHAR(150) NULL,
    bundle_id INT UNSIGNED NULL,
    checkout_notes TEXT NULL,
    return_notes TEXT NULL,
    status ENUM('open','partial','returned') NOT NULL DEFAULT 'open',
    CONSTRAINT fk_batch_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_batch_bundle FOREIGN KEY (bundle_id) REFERENCES bundles(id) ON DELETE SET NULL,
    INDEX idx_batch_status_due (status,due_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NULL,
    tool_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    issued_by VARCHAR(150) NOT NULL,
    checked_out_at DATETIME NOT NULL,
    due_at DATETIME NOT NULL,
    returned_at DATETIME NULL,
    received_by VARCHAR(150) NULL,
    checkout_notes TEXT NULL,
    return_notes TEXT NULL,
    return_condition ENUM('good','damaged','missing_parts') NULL,
    status ENUM('open','returned') NOT NULL DEFAULT 'open',
    CONSTRAINT fk_checkout_batch FOREIGN KEY (batch_id) REFERENCES checkout_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_checkout_tool FOREIGN KEY (tool_id) REFERENCES tools(id),
    CONSTRAINT fk_checkout_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_checkout_status_due (status, due_at), INDEX idx_checkout_employee (employee_id), INDEX idx_checkout_batch (batch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkout_accessories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    accessory_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    returned_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    return_condition ENUM('good','damaged','missing') NULL,
    returned_at DATETIME NULL,
    CONSTRAINT fk_checkout_accessory_batch FOREIGN KEY (batch_id) REFERENCES checkout_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_checkout_accessory_item FOREIGN KEY (accessory_id) REFERENCES accessories(id),
    UNIQUE KEY uq_batch_accessory (batch_id, accessory_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO employees (name, work_email, badge_number) VALUES ('Sample Employee', 'employee@example.com', '1001');
INSERT IGNORE INTO tools (tool_name, category, manufacturer, model_number, serial_number, internal_id, tool_location) VALUES ('Cordless Drill', 'Power Tools', 'DeWalt', 'DCD791', 'SN-DEMO-001', 'TOOL-0001', 'Tool Crib - Shelf A1');
INSERT IGNORE INTO accessories (accessory_name, internal_id, tool_location, quantity_total, quantity_available) VALUES ('Drill Battery', 'ACC-0001', 'Tool Crib - Battery Rack', 4, 4), ('Charger', 'ACC-0002', 'Tool Crib - Shelf A1', 2, 2);
