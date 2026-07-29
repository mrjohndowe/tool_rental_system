# Employee Tool Checkout System

A PHP/MySQL tool checkout application where authorized attendants issue and return tools for employees.

## Login system

- Every application page requires a signed-in account.
- Checkout and return records automatically use the signed-in user's full name.
- Clerk accounts can issue/return tools and manage the employee and tool records.
- Administrator accounts can also create, edit, activate, and deactivate system users.
- Passwords are stored with PHP's secure `password_hash()` format.
- Login sessions regenerate their ID after successful authentication.

### Initial administrator account

- Username: `admin`
- Password: `ChangeMe123!`

Sign in and use **Users** to edit the administrator account and change this password immediately.

## Other features

- Search employees by name, badge number, or department.
- Add an employee from checkout and immediately issue a tool.
- Required employee department and optional badge number.
- Tool serial number, internal ID, and location fields.
- Every checkout is due at 11:59:59 PM on the checkout date.
- Overdue highlighting and return condition tracking.
- Damaged tools automatically enter Maintenance status.

## New installation with XAMPP

1. Copy `tool_rental_system` into `C:\xampp\htdocs\`.
2. Start Apache and MySQL.
3. Import `database/schema.sql` in phpMyAdmin.
4. Open `http://localhost/tool_rental_system/`.
5. Sign in using the initial administrator account above.

## Upgrade an existing installation

1. Replace the existing application files with this updated version.
2. Import `database/login_upgrade.sql` in phpMyAdmin.
3. Open the application and sign in with the initial administrator account.

The default database configuration is in `config.php` and uses XAMPP's `root` account with a blank password.


## Bundles, Multiple Tools, and Accessories Upgrade
For an existing installation, import `database/bundles_accessories_upgrade.sql` in phpMyAdmin, then replace the PHP files.

New features:
- Select multiple individually tracked tools in one checkout.
- Create reusable tool bundles from inventory.
- Select a bundle to automatically choose its currently available tools.
- Manage optional accessory inventory and quantities.
- Return all or only some tools/accessories from a grouped checkout.
- Each tool retains its own serial number, internal ID, status, and location.

## Locations and shelves upgrade
Existing installations should import `database/locations_upgrade.sql`. A new **Locations** page lets authorized users add and edit storage locations, areas/rooms, and shelves. Tools and accessories are assigned from a saved-location dropdown.


## Employee department upgrade
For an existing installation, import `database/employee_department_upgrade.sql` in phpMyAdmin after making a database backup. This removes the employee work-email field, adds a required department field, and preserves existing employees with the temporary department `Unassigned` until you edit them.

## Keep me logged in upgrade
Existing installations should import `database/remember_login_upgrade.sql`, then replace the application files. The login page now has a **Keep me logged in on this device for 30 days** option. It stores a rotating, hashed login token in the database and a secure HTTP-only cookie on the device; it never stores the user's password. Logging out revokes the token immediately.
