<?php
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/internal_id.php';

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$edit = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM accessories WHERE id=?');
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
}

$locations = $pdo->query(
    'SELECT *
     FROM tool_locations
     WHERE active=1 OR id=' . (int)($edit['location_id'] ?? 0) . '
     ORDER BY location_name,area,shelf'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['accessory_name'] ?? '');
        $total = max(0, (int)($_POST['quantity_total'] ?? 0));
        $available = max(
            0,
            (int)($_POST['quantity_available'] ?? $total)
        );
        $locationId = (int)($_POST['location_id'] ?? 0);
        $isConsumable = isset($_POST['is_consumable']) ? 1 : 0;
        $lowStockLevel = max(
            0,
            (int)($_POST['low_stock_level'] ?? 0)
        );

        if ($name === '') {
            throw new RuntimeException('Item name is required.');
        }

        if ($available > $total) {
            throw new RuntimeException(
                'Available quantity cannot exceed total quantity.'
            );
        }

        $locationStmt = $pdo->prepare(
            'SELECT * FROM tool_locations WHERE id=?'
        );
        $locationStmt->execute([$locationId]);
        $location = $locationStmt->fetch();

        if (!$location) {
            throw new RuntimeException('Select a valid inventory location.');
        }

        $locationText = location_label($location);

        /*
         * Existing items keep their permanent Internal ID.
         * New items receive the next number based on the item name.
         */
        if ($id) {
            $existingStmt = $pdo->prepare(
                'SELECT internal_id FROM accessories WHERE id=?'
            );
            $existingStmt->execute([$id]);
            $internalId = (string)$existingStmt->fetchColumn();

            if ($internalId === '') {
                $internalId = generate_name_based_internal_id(
                    $name,
                    'accessories',
                    $id
                );
            }
        } else {
            $internalId = generate_name_based_internal_id(
                $name,
                'accessories'
            );
        }

        if ($id) {
            $stmt = $pdo->prepare(
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

            $stmt->execute([
                $name,
                $internalId,
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
            $stmt = $pdo->prepare(
                'INSERT INTO accessories
                 (
                    accessory_name,
                    internal_id,
                    location_id,
                    tool_location,
                    quantity_total,
                    quantity_available,
                    is_consumable,
                    low_stock_level,
                    active,
                    notes
                 )
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            );

            $stmt->execute([
                $name,
                $internalId,
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

        flash(
            'success',
            ($isConsumable ? 'Consumable' : 'Accessory')
            . ($id ? ' updated.' : ' saved with Internal ID ' . $internalId . '.')
        );
        redirect('accessories.php');
    } catch (Throwable $e) {
        $message = $e->getMessage();

        if (
            str_contains($message, 'Duplicate') ||
            str_contains($message, '1062')
        ) {
            $message = 'The generated Internal ID already exists. Please try saving again.';
        }

        flash('error', $message);
        redirect('accessories.php' . ($id ? '?id=' . $id : ''));
    }
}

$rows = $pdo->query(
    'SELECT *
     FROM accessories
     ORDER BY is_consumable,accessory_name'
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
                id="item_name"
                required
                value="<?= e($edit['accessory_name'] ?? '') ?>"
                placeholder="Example: Black Marker or Drill Battery"
                autocomplete="off"
            >

            <label>Internal ID</label>
            <input
                id="internal_id"
                readonly
                value="<?= e($edit['internal_id'] ?? '') ?>"
                placeholder="Generated from the item name"
            >
            <p class="muted">
                The final unique number is confirmed when the item is saved.
                Existing IDs remain unchanged.
            </p>

            <label>Location and Shelf</label>
            <select name="location_id" required>
                <option value="">Select location...</option>
                <?php foreach ($locations as $location): ?>
                    <option
                        value="<?= (int)$location['id'] ?>"
                        <?= ((int)($edit['location_id'] ?? 0)
                            === (int)$location['id'])
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e(location_label($location)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="muted">
                <a href="locations.php">
                    Add or edit locations and shelves
                </a>
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
                        Deducted when issued and never returned, such as
                        markers, spray paint, tape, gloves, or zip ties.
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

            <label>Notes</label>
            <textarea name="notes"><?= e($edit['notes'] ?? '') ?></textarea>

            <?php if ($edit): ?>
                <label>
                    <input
                        type="checkbox"
                        name="active"
                        <?= !empty($edit['active']) ? 'checked' : '' ?>
                    >
                    Active
                </label>
            <?php endif; ?>

            <button <?= $locations ? '' : 'disabled' ?>>
                Save Inventory Item
            </button>
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
                        <td colspan="5">
                            No accessory or consumable inventory found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row):
                    $lowStockLevel = (int)($row['low_stock_level'] ?? 0);
                    $availableQuantity = (int)($row['quantity_available'] ?? 0);
                    $low = $lowStockLevel > 0
                        && $availableQuantity <= $lowStockLevel;
                ?>
                    <tr class="<?= $low ? 'overdue-row' : '' ?>">
                        <td>
                            <strong><?= e($row['accessory_name']) ?></strong><br>
                            <span class="muted">
                                <?= e($row['internal_id'] ?: 'No Internal ID') ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge <?= $row['is_consumable']
                                ? 'overdue'
                                : 'open' ?>">
                                <?= $row['is_consumable']
                                    ? 'Consumable'
                                    : 'Returnable' ?>
                            </span>
                        </td>

                        <td><?= e($row['tool_location'] ?: '—') ?></td>

                        <td>
                            <?= $availableQuantity ?>
                            /
                            <?= (int)($row['quantity_total'] ?? 0) ?>

                            <?php if ($low): ?>
                                <br><strong>LOW STOCK</strong>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a
                                class="button secondary"
                                href="accessories.php?id=<?= (int)$row['id'] ?>"
                            >
                                Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nameInput = document.getElementById('item_name');
    const idInput = document.getElementById('internal_id');
    const editingExisting = <?= $id ? 'true' : 'false' ?>;

    if (!nameInput || !idInput || editingExisting) {
        return;
    }

    function createBase(name) {
        return name
            .trim()
            .toUpperCase()
            .replace(/&/g, ' AND ')
            .replace(/[^A-Z0-9]+/g, '')
            .substring(0, 30);
    }

    function updatePreview() {
        const base = createBase(nameInput.value);
        idInput.value = base ? base + '-001' : '';
    }

    nameInput.addEventListener('input', updatePreview);
    updatePreview();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
