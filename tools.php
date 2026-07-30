<?php require __DIR__ . '/includes/header.php';
$q = trim($_GET['q'] ?? '');
$pdo = db();
if ($q !== '') {
    $s = $pdo->prepare('SELECT * FROM tools WHERE tool_name LIKE ? OR serial_number LIKE ? OR internal_id LIKE ? OR tool_location LIKE ? ORDER BY tool_name');
    $t = '%' . $q . '%';
    $s->execute([$t, $t, $t, $t]);
    $rows = $s->fetchAll();
} else {
    $rows = $pdo->query('SELECT * FROM tools ORDER BY tool_name,internal_id')->fetchAll();
}
?>
<div class="card">
    <div class="actions" style="justify-content:space-between">
        <h2>Tool Inventory</h2><a class="button" href="tool_form.php">Add Tool</a>
    </div>
    <form method="get" class="search-row">
        <div><label>Search tools</label><input name="q" value="<?= e($q) ?>" placeholder="Name, serial number, internal ID, or location"></div><button>Search</button>
    </form>
</div>
<div class="table-wrap" style="margin-top:18px">
    <table>
        <thead>
            <tr>
                <th>Tool</th>
                <th>Serial Number</th>
                <th>Internal ID</th>
                <th>Location</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
                    <td><strong><?= e($r['tool_name']) ?></strong><br><span class="muted"><?= e(trim(($r['manufacturer'] ?? '') . ' ' . ($r['model_number'] ?? ''))) ?></span></td>
                    <td><?= e($r['serial_number']) ?></td>
                    <td><?= e($r['internal_id']) ?></td>
                    <td><?= e($r['tool_location']) ?></td>
                    <td><span class="badge <?= e($r['status']) ?>"><?= e(tool_status_label($r['status'])) ?></span></td>
                    <td><a class="button secondary" href="tool_form.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
                </tr><?php endforeach; ?></tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>