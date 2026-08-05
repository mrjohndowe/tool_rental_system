USE tool_rental;

ALTER TABLE accessories
    ADD COLUMN IF NOT EXISTS is_consumable TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity_available,
    ADD COLUMN IF NOT EXISTS low_stock_level INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_consumable;

ALTER TABLE checkout_accessories
    ADD COLUMN IF NOT EXISTS is_consumable TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity;

CREATE TABLE IF NOT EXISTS inventory_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accessory_id INT UNSIGNED NOT NULL,
    old_quantity INT UNSIGNED NOT NULL,
    new_quantity INT UNSIGNED NOT NULL,
    quantity_change INT NOT NULL,
    reason VARCHAR(150) NOT NULL,
    reference TEXT NULL,
    adjusted_by_user_id INT UNSIGNED NULL,
    adjusted_by_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_adjustment_item_date (accessory_id,created_at),
    INDEX idx_inventory_adjustment_user (adjusted_by_user_id),
    CONSTRAINT fk_inventory_adjustment_item FOREIGN KEY (accessory_id) REFERENCES accessories(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_adjustment_user FOREIGN KEY (adjusted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
