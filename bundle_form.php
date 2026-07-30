<?php
require __DIR__ . '/includes/header.php';
$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['bundle_name'] ?? '');
        $toolIds = array_values(array_unique(array_map('intval', $_POST['tool_ids'] ?? [])));
        if ($name === '')
            throw new RuntimeException('Bundle name is required.');
        $pdo->beginTransaction();
        if ($id) {
            $s = $pdo->prepare('UPDATE bundles SET bundle_name=?,description=?,active=? WHERE id=?');
            $s->execute([$name, trim($_POST['description'] ?? ''), isset($_POST['active']) ? 1 : 0, $id]);
            $pdo->prepare('DELETE FROM bundle_tools WHERE bundle_id=?')->execute([$id]);
        } else {
            $s = $pdo->prepare('INSERT INTO bundles(bundle_name,description,active) VALUES(?,?,1)');
            $s->execute([$name, trim($_POST['description'] ?? '')]);
            $id = (int) $pdo->lastInsertId();
        }
        $ins = $pdo->prepare('INSERT INTO bundle_tools(bundle_id,tool_id) VALUES(?,?)');
        foreach ($toolIds as $toolId)
            $ins->execute([$id, $toolId]);
        $pdo->commit();
        flash('success', 'Bundle saved.');
        redirect('bundles.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        flash('error', $e->getMessage());
        redirect('bundle_form.php' . ($id ? '?id=' . $id : ''));
    }
}
$bundle = null;
$selected = [];
if ($id) {
    $s = $pdo->prepare('SELECT * FROM bundles WHERE id=?');
    $s->execute([$id]);
    $bundle = $s->fetch();
    $s = $pdo->prepare('SELECT tool_id FROM bundle_tools WHERE bundle_id=?');
    $s->execute([$id]);
    $selected = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}
$tools = $pdo->query("SELECT * FROM tools WHERE status<>'retired' ORDER BY category,tool_name,internal_id")->fetchAll();
?><div class="card">
    <h2><?= $bundle ? 'Edit' : 'Create' ?> Tool Bundle</h2>
    <form method="post"><?php if ($bundle): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?><label>Bundle Name</label><input name="bundle_name" required value="<?= e($bundle['bundle_name'] ?? '') ?>"><label>Description</label><textarea name="description"><?= e($bundle['description'] ?? '') ?></textarea><label>Tools in Bundle</label>
        <div class="selection-list"><?php foreach ($tools as $t): ?><label class="select-item"><input type="checkbox" name="tool_ids[]" value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $selected, true) ? 'checked' : '' ?>><span><strong><?= e($t['tool_name']) ?></strong><br><small><?= e($t['internal_id']) ?> · <?= e($t['serial_number']) ?> · <?= e(tool_status_label($t['status'])) ?></small></span></label><?php endforeach; ?></div><?php if ($bundle): ?><label><input type="checkbox" name="active" <?= $bundle['active'] ? 'checked' : '' ?>> Active</label><?php endif; ?><div class="actions"><button>Save Bundle</button><a class="button secondary" href="bundles.php">Cancel</a></div>
    </form>
</div><?php require __DIR__ . '/includes/footer.php'; ?>