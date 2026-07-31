# Consumable Inventory Upgrade

This package adds consumable items to the Tool Rental System.

Examples include:

- Marker pens
- Spray paint
- Tape
- Gloves
- Zip ties
- Sandpaper
- Lubricant
- Disposable batteries

Consumables are deducted from inventory when issued and never appear on the
Return page.

## Installation

1. Back up the `tool_rental` database.
2. Import:

   `database/consumables_upgrade.sql`

3. Replace these files in the application:

   - `includes/functions.php`
   - `accessories.php`
   - `checkout.php`
   - `returns.php`

4. Open the Accessories page.
5. Add or edit an item and check **Consumable item**.
6. Set its total quantity, available quantity, and optional low-stock level.

## Important

Run the SQL migration before replacing the PHP files. The PHP files expect
the following new database columns:

- `accessories.is_consumable`
- `accessories.low_stock_level`
- `checkout_accessories.is_consumable`

## Behavior

### Returnable accessory

A battery, charger, case, or detachable part:

- quantity decreases at checkout
- appears on the Return page
- good returned quantity is added back into available inventory

### Consumable

A marker, paint can, tape roll, glove, or other used-up supply:

- quantity decreases at checkout
- does not appear on the Return page
- is never added back into inventory
- remains recorded in checkout history

A transaction containing only consumables closes automatically because there
is no returnable inventory outstanding.
