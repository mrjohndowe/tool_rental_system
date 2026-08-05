NAME-BASED INTERNAL ID — DRAG-AND-DROP UPGRADE
================================================

WHAT THIS DOES
--------------
When adding a new tool, accessory, or consumable, the Internal ID preview
updates while the name is typed.

Examples:

Hammer Drill
HAMMERDRILL-001

Hammer Drill (second record)
HAMMERDRILL-002

Black Marker
BLACKMARKER-001

Spray Paint
SPRAYPAINT-001

The browser displays "-001" as a preview. When Save is clicked, PHP checks the
database and assigns the actual next number.

Existing records keep their original Internal ID even when their name changes.

INSTALLATION
------------
1. Back up these existing files:

   tool_form.php
   accessories.php
   includes/internal_id.php (if it already exists)

2. Drag all folders and files from this package into:

   C:\xampp\htdocs\mossrental\

3. Allow Windows to merge the includes folder and replace files.

4. No database migration is required.

FILES
-----
tool_form.php
accessories.php
includes/internal_id.php

IMPORTANT
---------
This version of accessories.php expects the consumables/full inventory database
upgrade to already be installed, including these columns:

accessories.is_consumable
accessories.low_stock_level

The Internal ID database columns must remain UNIQUE, which your current schema
already enforces.
