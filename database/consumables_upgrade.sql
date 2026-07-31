USE tool_rental;

ALTER TABLE accessories
    ADD COLUMN is_consumable TINYINT(1) NOT NULL DEFAULT 0
        AFTER quantity_available,
    ADD COLUMN low_stock_level INT UNSIGNED NOT NULL DEFAULT 0
        AFTER is_consumable;

ALTER TABLE checkout_accessories
    ADD COLUMN is_consumable TINYINT(1) NOT NULL DEFAULT 0
        AFTER quantity;

UPDATE checkout_accessories ca
JOIN accessories a ON a.id = ca.accessory_id
SET ca.is_consumable = a.is_consumable;

-- Optional sample consumable items.
-- Change the location_id values after checking your tool_locations table.
--
-- INSERT INTO accessories
-- (accessory_name, internal_id, location_id, tool_location,
--  quantity_total, quantity_available, is_consumable,
--  low_stock_level, active, notes)
-- VALUES
-- ('Black Marker', 'CON-0001', NULL, 'Tool Crib', 24, 24, 1, 5, 1,
--  'Permanent marker pens'),
-- ('Black Spray Paint', 'CON-0002', NULL, 'Tool Crib', 12, 12, 1, 3, 1,
--  'Consumable aerosol paint');
