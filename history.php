<?php require __DIR__ . '/includes/header.php';
$rows = db()->query("SELECT c.*,c.batch_id,t.tool_name,t.internal_id,t.serial_number,e.name employee_name,e.badge_number FROM checkouts c JOIN tools t ON t.id=c.tool_id JOIN employees e ON e.id=c.employee_id ORDER BY c.checked_out_at DESC LIMIT 1000")->fetchAll(); ?><div class="card">
    <h2>Tool Checkout History</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Checkout</th>
                    <th>Tool</th>
                    <th>Employee</th>
                    <th>Issued By</th>
                    <th>Checked Out</th>
                    <th>Due</th>
                    <th>Returned</th>
                    <th>Condition</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody><?php foreach ($rows as $r): $over = $r['status'] === 'open' && strtotime($r['due_at']) < time(); ?><tr class="<?= $over ? 'overdue-row' : '' ?>">
                        <td><?= $r['batch_id'] ? '#' . (int)$r['batch_id'] : 'Legacy' ?></td>
                        <td><?= e($r['tool_name']) ?><br><span class="muted"><?= e($r['internal_id']) ?> / <?= e($r['serial_number']) ?></span></td>
                        <td><?= e($r['employee_name']) ?><br><span class="muted"><?= e($r['badge_number'] ?: 'No badge') ?></span></td>
                        <td><?= e($r['issued_by']) ?></td>
                        <td><?= e(date('M j, Y g:i A', strtotime($r['checked_out_at']))) ?></td>
                        <td><?= e(date('M j, Y g:i A', strtotime($r['due_at']))) ?></td>
                        <td><?= $r['returned_at'] ? e(date('M j, Y g:i A', strtotime($r['returned_at']))) : '—' ?></td>
                        <td><?= e($r['return_condition'] ?: '—') ?></td>
                        <td><span class="badge <?= $over ? 'overdue' : e($r['status']) ?>"><?= $over ? 'OVERDUE' : e(ucfirst($r['status'])) ?></span></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div><?php require __DIR__ . '/includes/footer.php'; ?>