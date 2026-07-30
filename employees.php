<?php
require __DIR__ . '/includes/header.php';
$rows = db()->query('SELECT * FROM employees ORDER BY active DESC, name')->fetchAll();
?>
<div class="card">
    <div class="page-head">
        <div>
            <h2>Employees</h2>
            <p class="muted">Manage the employee roster and department assignments.</p>
        </div><a class="button" href="employee_form.php">Add Employee</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Badge</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?><tr>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e($r['badge_number'] ?: '—') ?></td>
                        <td><?= e($r['department']) ?></td>
                        <td><span class="badge <?= $r['active'] ? 'available' : 'maintenance' ?>"><?= $r['active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><a class="button secondary" href="employee_form.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
                    </tr><?php endforeach; ?>
                <?php if (!$rows): ?><tr>
                        <td colspan="5">No employees have been added.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>