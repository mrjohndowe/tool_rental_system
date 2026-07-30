<?php
require __DIR__ . '/includes/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$edit = null;
if ($id) {
    $s = $pdo->prepare('SELECT * FROM tool_locations WHERE id=?');
    $s->execute([$id]);
    $edit = $s->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['location_name'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $shelf = trim($_POST['shelf'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        if ($name === '') throw new RuntimeException('Location name is required.');
        if ($shelf === '') throw new RuntimeException('Shelf is required.');
        $pdo->beginTransaction();
        if ($id) {
            $st = $pdo->prepare('UPDATE tool_locations SET location_name=?,area=?,shelf=?,notes=?,active=? WHERE id=?');
            $st->execute([$name, $area ?: null, $shelf, $notes ?: null, $active, $id]);
        } else {
            $st = $pdo->prepare('INSERT INTO tool_locations(location_name,area,shelf,notes,active) VALUES(?,?,?,?,1)');
            $st->execute([$name, $area ?: null, $shelf, $notes ?: null]);
            $id = (int)$pdo->lastInsertId();
        }
        $label = location_label(['location_name' => $name, 'area' => $area, 'shelf' => $shelf]);
        $pdo->prepare('UPDATE tools SET tool_location=? WHERE location_id=?')->execute([$label, $id]);
        $pdo->prepare('UPDATE accessories SET tool_location=? WHERE location_id=?')->execute([$label, $id]);
        $pdo->commit();
        flash('success', 'Location saved. Assigned inventory was updated automatically.');
        redirect('locations.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
        redirect('locations.php' . ($id ? '?id=' . $id : ''));
    }
}
$rows = $pdo->query('SELECT l.*, (SELECT COUNT(*) FROM tools t WHERE t.location_id=l.id) tool_count, (SELECT COUNT(*) FROM accessories a WHERE a.location_id=l.id) accessory_count FROM tool_locations l ORDER BY l.location_name,l.area,l.shelf')->fetchAll();
?>
<div class="grid two">
    <div class="card">
        <h2><?= $edit ? 'Edit' : 'Add' ?> Tool Location</h2>
        <form method="post"><?php if ($edit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <label>Location Name</label><input name="location_name" required value="<?= e($edit['location_name'] ?? '') ?>" placeholder="Tool Crib, Warehouse, Service Truck">
            <label>Area / Room (optional)</label><input name="area" value="<?= e($edit['area'] ?? '') ?>" placeholder="North wall, Room 104, Bay 2">
            <label>Shelf</label><input name="shelf" required value="<?= e($edit['shelf'] ?? '') ?>" placeholder="Shelf A1, Cabinet 3, Drawer 2">
            <label>Notes</label><textarea name="notes" placeholder="Access instructions or storage details"><?= e($edit['notes'] ?? '') ?></textarea>
            <?php if ($edit): ?><label><input type="checkbox" name="active" <?= $edit['active'] ? 'checked' : '' ?>> Active and selectable</label><?php endif; ?>
            <div class="actions"><button>Save Location</button><?php if ($edit): ?><a class="button secondary" href="locations.php">Cancel</a><?php endif; ?></div>
        </form>
    </div>
    <div class="card">
        <h2>Saved Locations</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Shelf</th>
                        <th>Assigned</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?><tr>
                            <td><strong><?= e($r['location_name']) ?></strong><br><span class="muted"><?= e($r['area'] ?: 'No area specified') ?><?= $r['active'] ? '' : ' · Inactive' ?></span></td>
                            <td><?= e($r['shelf']) ?></td>
                            <td><?= (int)$r['tool_count'] ?> tools<br><?= (int)$r['accessory_count'] ?> accessories</td>
                            <td><a class="button secondary" href="locations.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
                        </tr><?php endforeach; ?>
                    <?php if (!$rows): ?><tr>
                            <td colspan="4">No locations have been added.</td>
                        </tr><?php endif; ?></tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>