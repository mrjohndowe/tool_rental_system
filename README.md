# Employee Tool Checkout System

A PHP/MySQL tool checkout application designed for a single attendant to issue tools to employees by badge number, name, or work email.

## Included features

- Search employees by name, badge number, or work email.
- Add an employee directly from the checkout screen and immediately continue issuing a tool.
- Optional employee badge number.
- Tool serial number, internal ID number, and storage/location fields.
- One open checkout per physical tool because checked-out tools are removed from the available list.
- Every checkout is automatically due at 11:59:59 PM on the checkout date.
- Overdue highlighting on the dashboard, returns page, and history.
- Return condition tracking; damaged tools automatically move to Maintenance status.
- Tool and employee management pages.
- Complete checkout and return history.

## Installation with XAMPP

1. Copy the `tool_rental_system` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import `database/schema.sql`.
4. Confirm the database settings in `config.php`. XAMPP defaults are already configured: user `root`, blank password.
5. Open `http://localhost/tool_rental_system/`.

## Production note

The included build is intended as a functional internal starter. Before exposing it to the public internet, add user authentication, HTTPS, CSRF protection, audit permissions, automated backups, and your company retention policy.
