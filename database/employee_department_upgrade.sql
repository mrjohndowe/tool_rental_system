USE tool_rental;

-- Replace employee work email with department.
-- Run this once on an existing installation, then replace the PHP files.

ALTER TABLE employees ADD COLUMN department VARCHAR(150) NULL AFTER name;
UPDATE employees SET department = 'Unassigned' WHERE department IS NULL OR TRIM(department) = '';
ALTER TABLE employees MODIFY department VARCHAR(150) NOT NULL;
ALTER TABLE employees DROP INDEX uq_employee_email;
ALTER TABLE employees DROP COLUMN work_email;
ALTER TABLE employees ADD INDEX idx_employee_department (department);
