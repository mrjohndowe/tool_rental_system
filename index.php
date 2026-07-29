<?php require __DIR__ . '/includes/header.php';
$pdo = db();
$counts = [
    'available' => (int)$pdo->query("SELECT COUNT(*) FROM tools WHERE status='available'")->fetchColumn(),
    'checked_out' => (int)$pdo->query("SELECT COUNT(*) FROM checkouts WHERE status='open'")->fetchColumn(),
    'overdue' => (int)$pdo->query("SELECT COUNT(*) FROM checkouts WHERE status='open' AND due_at < NOW()")->fetchColumn(),
    'employees' => (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE active=1")->fetchColumn(),
];
$open = $pdo->query("SELECT c.*, t.tool_name, t.internal_id, t.serial_number, e.name employee_name, e.badge_number
                     FROM checkouts c JOIN tools t ON t.id=c.tool_id JOIN employees e ON e.id=c.employee_id
                     WHERE c.status='open' ORDER BY c.due_at ASC")->fetchAll();
?>
<div class="grid cards">
    <div class="card"><h3>Available Tools</h3><div class="stat"><?= $counts['available'] ?></div></div>
    <div class="card"><h3>Checked Out</h3><div class="stat"><?= $counts['checked_out'] ?></div></div>
    <div class="card"><h3>Overdue</h3><div class="stat"><?= $counts['overdue'] ?></div></div>
    <div class="card"><h3>Active Employees</h3><div class="stat"><?= $counts['employees'] ?></div></div>
</div>
<div class="card" style="margin-top:18px">
    <h2>Currently Issued Tools</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Tool</th><th>Employee</th><th>Issued</th><th>Due</th><th>Issued By</th><th></th></tr></thead>
        <tbody>
        <?php if (!$open): ?><tr><td colspan="6">No tools are currently checked out.</td></tr><?php endif; ?>
        <?php foreach ($open as $row): $overdue = strtotime($row['due_at']) < time(); ?>
            <tr class="<?= $overdue ? 'overdue-row' : '' ?>">
                <td><strong><?= e($row['tool_name']) ?></strong><br><span class="muted"><?= e($row['internal_id']) ?> / <?= e($row['serial_number']) ?></span></td>
                <td><?= e($row['employee_name']) ?><br><span class="muted"><?= e($row['badge_number'] ?: 'No badge') ?></span></td>
                <td><?= e(date('M j, Y g:i A', strtotime($row['checked_out_at']))) ?></td>
                <td><span class="badge <?= $overdue ? 'overdue' : 'open' ?>"><?= $overdue ? 'OVERDUE' : e(date('M j, Y g:i A', strtotime($row['due_at']))) ?></span></td>
                <td><?= e($row['issued_by']) ?></td>
                <td><a class="button success" href="returns.php?id=<?= (int)$row['id'] ?>">Return</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
