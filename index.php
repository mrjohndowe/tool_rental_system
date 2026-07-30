<?php require __DIR__ . '/includes/header.php';
$pdo = db();
$counts = ['available' => (int)$pdo->query("SELECT COUNT(*) FROM tools WHERE status='available'")->fetchColumn(), 'checked_out' => (int)$pdo->query("SELECT COUNT(*) FROM tools WHERE status='checked_out'")->fetchColumn(), 'overdue' => (int)$pdo->query("SELECT COUNT(*) FROM checkout_batches WHERE status<>'returned' AND due_at<NOW()")->fetchColumn(), 'employees' => (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE active=1")->fetchColumn()];
$open = $pdo->query("SELECT b.*,e.name employee_name,e.badge_number,COUNT(DISTINCT CASE WHEN c.status='open' THEN c.id END) open_tools,COALESCE(SUM(ca.quantity-ca.returned_quantity),0) open_accessories,GROUP_CONCAT(DISTINCT CASE WHEN c.status='open' THEN CONCAT(t.tool_name,' [',t.internal_id,']') END SEPARATOR ', ') tool_list FROM checkout_batches b JOIN employees e ON e.id=b.employee_id LEFT JOIN checkouts c ON c.batch_id=b.id LEFT JOIN tools t ON t.id=c.tool_id LEFT JOIN checkout_accessories ca ON ca.batch_id=b.id WHERE b.status<>'returned' GROUP BY b.id ORDER BY b.due_at")->fetchAll();
?><div class="grid cards">
    <div class="card">
        <h3>Available Tools</h3>
        <div class="stat"><?= $counts['available'] ?></div>
    </div>
    <div class="card">
        <h3>Tools Checked Out</h3>
        <div class="stat"><?= $counts['checked_out'] ?></div>
    </div>
    <div class="card">
        <h3>Overdue Checkouts</h3>
        <div class="stat"><?= $counts['overdue'] ?></div>
    </div>
    <div class="card">
        <h3>Active Employees</h3>
        <div class="stat"><?= $counts['employees'] ?></div>
    </div>
</div>
<div class="card" style="margin-top:18px">
    <h2>Currently Issued Inventory</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Checkout</th>
                    <th>Employee</th>
                    <th>Inventory</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th>Issued By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><?php if (!$open): ?><tr>
                        <td colspan="7">No inventory is currently checked out.</td>
                    </tr><?php endif;
                        foreach ($open as $r): $late = strtotime($r['due_at']) < time(); ?><tr class="<?= $late ? 'overdue-row' : '' ?>">
                        <td>#<?= (int)$r['id'] ?></td>
                        <td><?= e($r['employee_name']) ?><br><span class="muted"><?= e($r['badge_number'] ?: 'No badge') ?></span></td>
                        <td><?= (int)$r['open_tools'] ?> tool(s), <?= (int)$r['open_accessories'] ?> accessory item(s)<br><span class="muted"><?= e($r['tool_list'] ?: 'Accessories only') ?></span></td>
                        <td><?= e(date('M j, Y g:i A', strtotime($r['checked_out_at']))) ?></td>
                        <td><span class="badge <?= $late ? 'overdue' : 'open' ?>"><?= $late ? 'OVERDUE' : e(date('M j, Y g:i A', strtotime($r['due_at']))) ?></span></td>
                        <td><?= e($r['issued_by']) ?></td>
                        <td><a class="button success" href="returns.php?batch_id=<?= (int)$r['id'] ?>">Return</a></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</div><?php require __DIR__ . '/includes/footer.php'; ?>