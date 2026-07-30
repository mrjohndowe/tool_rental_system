<?php
require __DIR__ . '/includes/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$returnTo = $_GET['return_to'] ?? $_POST['return_to'] ?? '';
$employee = ['name' => trim($_GET['prefill'] ?? ''), 'department' => '', 'badge_number' => '', 'active' => 1];
if ($id) {
    $s = $pdo->prepare('SELECT * FROM employees WHERE id=?');
    $s->execute([$id]);
    $employee = $s->fetch() ?: $employee;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $badge = trim($_POST['badge_number'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($name === '' || $department === '') throw new RuntimeException('Employee name and department are required.');
        if ($id) {
            $s = $pdo->prepare('UPDATE employees SET name=?,department=?,badge_number=?,active=? WHERE id=?');
            $s->execute([$name, $department, $badge ?: null, $active, $id]);
            $newId = $id;
        } else {
            $s = $pdo->prepare('INSERT INTO employees(name,department,badge_number,active) VALUES(?,?,?,?)');
            $s->execute([$name, $department, $badge ?: null, $active]);
            $newId = (int)$pdo->lastInsertId();
        }
        flash('success', 'Employee saved.');
        redirect($returnTo === 'checkout' ? 'checkout.php?employee_id=' . $newId : 'employees.php');
    } catch (Throwable $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate') ? 'That badge number is already in use.' : $e->getMessage());
        redirect('employee_form.php' . ($id ? '?id=' . $id : ''));
    }
}
?>
<div class="card">
    <h2><?= $id ? 'Edit' : 'Add' ?> Employee</h2>
    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <label>Full Name</label><input name="name" required value="<?= e($employee['name']) ?>">
        <label>Department</label><input name="department" required placeholder="Examples: Maintenance, Production, Shipping" value="<?= e($employee['department']) ?>">
        <label>Badge Number <span class="muted">(optional)</span></label><input name="badge_number" value="<?= e($employee['badge_number']) ?>">
        <label><input type="checkbox" name="active" value="1" style="width:auto" <?= $employee['active'] ? 'checked' : '' ?>> Active employee</label>
        <div class="actions"><button>Save Employee</button><a class="button secondary" href="<?= $returnTo === 'checkout' ? 'checkout.php' : 'employees.php' ?>">Cancel</a></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>