USE tool_rental;

CREATE TABLE IF NOT EXISTS tool_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(150) NOT NULL,
    area VARCHAR(150) NULL,
    shelf VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tool_location (location_name, area, shelf)
) ENGINE=InnoDB;

ALTER TABLE tools ADD COLUMN IF NOT EXISTS location_id INT UNSIGNED NULL AFTER internal_id;
ALTER TABLE accessories ADD COLUMN IF NOT EXISTS location_id INT UNSIGNED NULL AFTER internal_id;

-- Existing typed locations are preserved as legacy saved locations. Their shelf defaults to "Unspecified" and can be edited afterward.
INSERT IGNORE INTO tool_locations(location_name, shelf)
SELECT DISTINCT tool_location, 'Unspecified' FROM tools WHERE tool_location IS NOT NULL AND tool_location<>'';
INSERT IGNORE INTO tool_locations(location_name, shelf)
SELECT DISTINCT tool_location, 'Unspecified' FROM accessories WHERE tool_location IS NOT NULL AND tool_location<>'';

UPDATE tools t JOIN tool_locations l ON l.location_name=t.tool_location AND l.shelf='Unspecified'
SET t.location_id=l.id WHERE t.location_id IS NULL;
UPDATE accessories a JOIN tool_locations l ON l.location_name=a.tool_location AND l.shelf='Unspecified'
SET a.location_id=l.id WHERE a.location_id IS NULL;

SET @fk_tool_exists=(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tools' AND CONSTRAINT_NAME='fk_tool_location');
SET @sql=IF(@fk_tool_exists=0,'ALTER TABLE tools ADD CONSTRAINT fk_tool_location FOREIGN KEY (location_id) REFERENCES tool_locations(id) ON DELETE SET NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_acc_exists=(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='accessories' AND CONSTRAINT_NAME='fk_accessory_location');
SET @sql=IF(@fk_acc_exists=0,'ALTER TABLE accessories ADD CONSTRAINT fk_accessory_location FOREIGN KEY (location_id) REFERENCES tool_locations(id) ON DELETE SET NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
