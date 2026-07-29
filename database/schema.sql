CREATE DATABASE IF NOT EXISTS tool_rental CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tool_rental;

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    work_email VARCHAR(190) NOT NULL,
    badge_number VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_email (work_email),
    UNIQUE KEY uq_employee_badge (badge_number)
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
    UNIQUE KEY uq_tool_serial (serial_number),
    UNIQUE KEY uq_tool_internal (internal_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    CONSTRAINT fk_checkout_tool FOREIGN KEY (tool_id) REFERENCES tools(id),
    CONSTRAINT fk_checkout_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_checkout_status_due (status, due_at),
    INDEX idx_checkout_employee (employee_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO employees (name, work_email, badge_number) VALUES
('Sample Employee', 'employee@example.com', '1001');

INSERT IGNORE INTO tools (tool_name, category, manufacturer, model_number, serial_number, internal_id, tool_location) VALUES
('Cordless Drill', 'Power Tools', 'DeWalt', 'DCD791', 'SN-DEMO-001', 'TOOL-0001', 'Tool Crib - Shelf A1');
