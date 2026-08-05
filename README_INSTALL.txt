FULL INVENTORY SYSTEM UPGRADE

Features:
- Search tools, accessories, and consumables in one place
- Search by name, ID, serial, category, model, manufacturer, or location
- Filter by inventory type, location/shelf, and stock status
- Location and count display
- Low-stock and out-of-stock reporting
- Printable report
- CSV export for Excel
- Audited count adjustments with reason, user, date, and before/after count

Installation:
1. Back up the database and application.
2. Import database/full_inventory_upgrade.sql in phpMyAdmin.
3. Copy inventory.php and inventory_adjust.php to the application root.
4. Replace includes/header.php.
5. Visit http://localhost/mossrental/inventory.php

The SQL expects the database name tool_rental. Change the USE line if your database has another name.
