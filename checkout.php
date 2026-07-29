<?php
require __DIR__ . '/includes/header.php';
$pdo = db();
$search = trim($_GET['employee_search'] ?? '');
$employeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $toolId = (int)($_POST['tool_id'] ?? 0);
        $issuedBy = current_user()['full_name'];
        if (!$toolId || !$employeeId) throw new RuntimeException('Employee and tool are required.');
        checkout_tool($toolId, $employeeId, $issuedBy, trim($_POST['notes'] ?? ''));
        flash('success', 'Tool checked out successfully. It is due by the end of today.');
        redirect('checkout.php');
    } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('checkout.php?employee_id=' . $employeeId); }
}

$employees = [];
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM employees WHERE active=1 AND (name LIKE ? OR badge_number LIKE ? OR work_email LIKE ?) ORDER BY name LIMIT 20');
    $term = '%' . $search . '%'; $stmt->execute([$term,$term,$term]); $employees = $stmt->fetchAll();
}
$selected = null;
if ($employeeId) { $stmt=$pdo->prepare('SELECT * FROM employees WHERE id=?'); $stmt->execute([$employeeId]); $selected=$stmt->fetch(); }
$tools = $pdo->query("SELECT * FROM tools WHERE status='available' ORDER BY tool_name, internal_id")->fetchAll();
?>
<div class="grid two">
<div class="card">
<h2>1. Find Employee</h2>
<form method="get" class="search-row">
<div><label>Name, badge number, or email</label><input name="employee_search" value="<?= e($search) ?>" autofocus></div>
<button>Search</button>
</form>
<?php if ($search !== ''): ?>
<div class="table-wrap" style="margin-top:15px"><table><tbody>
<?php foreach ($employees as $emp): ?><tr><td><strong><?= e($emp['name']) ?></strong><br><span class="muted"><?= e($emp['badge_number'] ?: 'No badge') ?> · <?= e($emp['work_email']) ?></span></td><td><a class="button" href="checkout.php?employee_id=<?= (int)$emp['id'] ?>">Select</a></td></tr><?php endforeach; ?>
<?php if (!$employees): ?><tr><td>No roster match found.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="actions"><a class="button secondary" href="employee_form.php?return_to=checkout&prefill=<?= urlencode($search) ?>">Add New Employee and Issue Tool</a></div>
<?php endif; ?>
</div>
<div class="card">
<h2>2. Issue Tool</h2>
<?php if ($selected): ?>
<p>Issuing to <strong><?= e($selected['name']) ?></strong> <?= $selected['badge_number'] ? '(Badge ' . e($selected['badge_number']) . ')' : '' ?></p>
<form method="post">
<input type="hidden" name="employee_id" value="<?= (int)$selected['id'] ?>">
<label>Tool</label><select name="tool_id" required><option value="">Select an available tool</option><?php foreach ($tools as $tool): ?><option value="<?= (int)$tool['id'] ?>"><?= e($tool['tool_name']) ?> — <?= e($tool['internal_id']) ?> — <?= e($tool['tool_location']) ?></option><?php endforeach; ?></select>
<label>Issued By</label><input value="<?= e(current_user()['full_name']) ?>" disabled><p class="muted">Recorded from the signed-in account.</p>
<label>Checkout Notes</label><textarea name="notes" placeholder="Optional accessories, condition, or job assignment"></textarea>
<p class="muted">Due: <?= e(date('F j, Y')) ?> by 11:59 PM</p>
<button>Check Out Tool</button>
</form>
<?php else: ?><p class="muted">Search for and select an employee first.</p><?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
