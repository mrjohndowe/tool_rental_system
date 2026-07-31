<?php
require __DIR__ . '/includes/header.php';

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$edit = null;

if ($id) {
    $s = $pdo->prepare('SELECT * FROM accessories WHERE id=?');
    $s->execute([$id]);
    $edit = $s->fetch();
}

$locations = $pdo->query(
    'SELECT * FROM tool_locations
     WHERE active=1 OR id=' . (int)($edit['location_id'] ?? 0) . '
     ORDER BY location_name,area,shelf'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['accessory_name'] ?? '');
        $total = max(0, (int)($_POST['quantity_total'] ?? 0));
        $available = max(0, (int)($_POST['quantity_available'] ?? $total));
        $locationId = (int)($_POST['location_id'] ?? 0);
        $isConsumable = isset($_POST['is_consumable']) ? 1 : 0;
        $lowStockLevel = max(0, (int)($_POST['low_stock_level'] ?? 0));

        $ls = $pdo->prepare('SELECT * FROM tool_locations WHERE id=?');
        $ls->execute([$locationId]);
        $loc = $ls->fetch();

        if (!$loc) {
            throw new RuntimeException('Select a valid inventory location.');
        }

        if ($name === '') {
            throw new RuntimeException('Item name is required.');
        }

        if ($available > $total) {
            throw new RuntimeException('Available quantity cannot exceed total quantity.');
        }

        $locationText = location_label($loc);

        if ($id) {
            $s = $pdo->prepare(
                'UPDATE accessories
                 SET accessory_name=?,
                     internal_id=?,
                     location_id=?,
                     tool_location=?,
                     quantity_total=?,
                     quantity_available=?,
                     is_consumable=?,
                     low_stock_level=?,
                     active=?,
                     notes=?
                 WHERE id=?'
            );

            $s->execute([
                $name,
                trim($_POST['internal_id'] ?? '') ?: null,
                $locationId,
                $locationText,
                $total,
                $available,
                $isConsumable,
                $lowStockLevel,
                isset($_POST['active']) ? 1 : 0,
                trim($_POST['notes'] ?? ''),
                $id
            ]);
        } else {
            $s = $pdo->prepare(
                'INSERT INTO accessories
                 (accessory_name,internal_id,location_id,tool_location,
                  quantity_total,quantity_available,is_consumable,
                  low_stock_level,active,notes)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            );

            $s->execute([
                $name,
                trim($_POST['internal_id'] ?? '') ?: null,
                $locationId,
                $locationText,
                $total,
                $available,
                $isConsumable,
                $lowStockLevel,
                1,
                trim($_POST['notes'] ?? '')
            ]);
        }

        flash('success', $isConsumable ? 'Consumable item saved.' : 'Accessory saved.');
        redirect('accessories.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('accessories.php' . ($id ? '?id=' . $id : ''));
    }
}

$rows = $pdo->query(
    'SELECT * FROM accessories
     ORDER BY is_consumable, accessory_name'
)->fetchAll();
?>
<div class="grid two">
    <div class="card">
        <h2><?= $edit ? 'Edit' : 'Add' ?> Inventory Item</h2>

        <?php if (!$locations): ?>
            <div class="alert error">
                Add a location and shelf first.
                <a href="locations.php">Manage Locations</a>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <label>Item Name</label>
            <input
                name="accessory_name"
                required
                value="<?= e($edit['accessory_name'] ?? '') ?>"
                placeholder="Example: Black Marker or Drill Battery"
            >

            <label>Internal ID (optional)</label>
            <input
                name="internal_id"
                value="<?= e($edit['internal_id'] ?? '') ?>"
            >

            <label>Location and Shelf</label>
            <select name="location_id" required>
                <option value="">Select location...</option>
                <?php foreach ($locations as $l): ?>
                    <option
                        value="<?= (int)$l['id'] ?>"
                        <?= ((int)($edit['location_id'] ?? 0) === (int)$l['id']) ? 'selected' : '' ?>
                    >
                        <?= e(location_label($l)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="muted">
                <a href="locations.php">Add or edit locations and shelves</a>
            </p>

            <label class="select-item">
                <input
                    type="checkbox"
                    name="is_consumable"
                    value="1"
                    <?= !empty($edit['is_consumable']) ? 'checked' : '' ?>
                >
                <span>
                    <strong>Consumable item</strong><br>
                    <small>
                        Consumables are deducted when issued and are never returned,
                        such as markers, spray paint, tape, gloves, or zip ties.
                    </small>
                </span>
            </label>

            <div class="grid two">
                <div>
                    <label>Total Quantity</label>
                    <input
                        type="number"
                        min="0"
                        name="quantity_total"
                        value="<?= e((string)($edit['quantity_total'] ?? 1)) ?>"
                    >
                </div>

                <div>
                    <label>Available Quantity</label>
                    <input
                        type="number"
                        min="0"
                        name="quantity_available"
                        value="<?= e((string)($edit['quantity_available'] ?? 1)) ?>"
                    >
                </div>
            </div>

            <label>Low Stock Alert Level</label>
            <input
                type="number"
                min="0"
                name="low_stock_level"
                value="<?= e((string)($edit['low_stock_level'] ?? 0)) ?>"
            >
            <p class="muted">
                Set to 0 to disable. The inventory table highlights quantities
                at or below this level.
            </p>

            <label>Notes</label>
            <textarea name="notes"><?= e($edit['notes'] ?? '') ?></textarea>

            <?php if ($edit): ?>
                <label>
                    <input
                        type="checkbox"
                        name="active"
                        <?= $edit['active'] ? 'checked' : '' ?>
                    >
                    Active
                </label>
            <?php endif; ?>

            <button <?= $locations ? '' : 'disabled' ?>>Save Inventory Item</button>
        </form>
    </div>

    <div class="card">
        <h2>Accessory and Consumable Inventory</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Location / Shelf</th>
                        <th>Available</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="5">No accessory or consumable inventory found.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $r):
                    $lowStockLevel = (int)($r['low_stock_level'] ?? 0);
                    $low = $lowStockLevel > 0
                             && (int)($r['quantity_available'] ?? 0) <= $lowStockLevel;
                ?>
                    <tr class="<?= $low ? 'overdue-row' : '' ?>">
                        <td>
                            <strong><?= e($r['accessory_name']) ?></strong><br>
                            <span class="muted">
                                <?= e($r['internal_id'] ?: 'No internal ID') ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge <?= $r['is_consumable'] ? 'overdue' : 'open' ?>">
                                <?= $r['is_consumable'] ? 'Consumable' : 'Returnable' ?>
                            </span>
                        </td>

                        <td><?= e($r['tool_location'] ?: '—') ?></td>

                        <td>
                            <?= (int)$r['quantity_available'] ?>
                            /
                            <?= (int)$r['quantity_total'] ?>
                            <?php if ($low): ?>
                                <br><strong>LOW STOCK</strong>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a
                                class="button secondary"
                                href="accessories.php?id=<?= (int)$r['id'] ?>"
                            >Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
