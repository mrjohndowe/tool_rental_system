<?php
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/internal_id.php';

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$tool = [
    'tool_name' => '',
    'category' => '',
    'manufacturer' => '',
    'model_number' => '',
    'serial_number' => '',
    'internal_id' => '',
    'location_id' => '',
    'tool_location' => '',
    'status' => 'available',
    'notes' => ''
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM tools WHERE id=?');
    $stmt->execute([$id]);
    $tool = $stmt->fetch() ?: $tool;
}

$locations = $pdo->query(
    'SELECT *
     FROM tool_locations
     WHERE active=1 OR id=' . (int)($tool['location_id'] ?: 0) . '
     ORDER BY location_name,area,shelf'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $toolName = trim($_POST['tool_name'] ?? '');
        $serialNumber = trim($_POST['serial_number'] ?? '');
        $locationId = (int)($_POST['location_id'] ?? 0);

        if ($toolName === '') {
            throw new RuntimeException('Tool name is required.');
        }

        if ($serialNumber === '') {
            throw new RuntimeException('Serial number is required.');
        }

        $locationStmt = $pdo->prepare(
            'SELECT * FROM tool_locations WHERE id=?'
        );
        $locationStmt->execute([$locationId]);
        $location = $locationStmt->fetch();

        if (!$location) {
            throw new RuntimeException('Select a valid tool location.');
        }

        $locationText = location_label($location);

        /*
         * Existing records keep their permanent Internal ID.
         * New records receive the next unique ID based on the tool name.
         */
        if ($id) {
            $existingStmt = $pdo->prepare(
                'SELECT internal_id FROM tools WHERE id=?'
            );
            $existingStmt->execute([$id]);
            $internalId = (string)$existingStmt->fetchColumn();

            if ($internalId === '') {
                throw new RuntimeException('Existing tool Internal ID was not found.');
            }
        } else {
            $internalId = generate_name_based_internal_id(
                $toolName,
                'tools'
            );
        }

        $values = [
            $toolName,
            trim($_POST['category'] ?? ''),
            trim($_POST['manufacturer'] ?? ''),
            trim($_POST['model_number'] ?? ''),
            $serialNumber,
            $internalId,
            $locationId,
            $locationText,
            $_POST['status'] ?? 'available',
            trim($_POST['notes'] ?? '')
        ];

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE tools
                 SET tool_name=?,
                     category=?,
                     manufacturer=?,
                     model_number=?,
                     serial_number=?,
                     internal_id=?,
                     location_id=?,
                     tool_location=?,
                     status=?,
                     notes=?
                 WHERE id=?'
            );
            $stmt->execute([...$values, $id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO tools
                 (
                    tool_name,
                    category,
                    manufacturer,
                    model_number,
                    serial_number,
                    internal_id,
                    location_id,
                    tool_location,
                    status,
                    notes
                 )
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute($values);
        }

        flash(
            'success',
            $id
                ? 'Tool updated.'
                : 'Tool saved with Internal ID ' . $internalId . '.'
        );
        redirect('tools.php');
    } catch (Throwable $e) {
        $message = $e->getMessage();

        if (
            str_contains($message, 'Duplicate') ||
            str_contains($message, '1062')
        ) {
            $message = 'Serial number or Internal ID already exists.';
        }

        flash('error', $message);
        redirect('tool_form.php' . ($id ? '?id=' . $id : ''));
    }
}
?>
<div class="card">
    <h2><?= $id ? 'Edit' : 'Add' ?> Tool</h2>

    <?php if (!$locations): ?>
        <div class="alert error">
            Add a saved location and shelf before adding a tool.
            <a href="locations.php">Manage Locations</a>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="grid two">
            <div>
                <label>Tool Name</label>
                <input
                    name="tool_name"
                    id="tool_name"
                    required
                    value="<?= e($tool['tool_name']) ?>"
                    autocomplete="off"
                >

                <label>Category</label>
                <input
                    name="category"
                    value="<?= e($tool['category']) ?>"
                >

                <label>Manufacturer</label>
                <input
                    name="manufacturer"
                    value="<?= e($tool['manufacturer']) ?>"
                >

                <label>Model Number</label>
                <input
                    name="model_number"
                    value="<?= e($tool['model_number']) ?>"
                >
            </div>

            <div>
                <label>Serial Number</label>
                <input
                    name="serial_number"
                    required
                    value="<?= e($tool['serial_number']) ?>"
                >

                <label>Internal ID Number</label>
                <input
                    name="internal_id_preview"
                    id="internal_id"
                    readonly
                    value="<?= e($tool['internal_id']) ?>"
                    placeholder="Generated from the tool name"
                >
                <p class="muted">
                    The final unique number is confirmed when the tool is saved.
                    Existing tool IDs never change.
                </p>

                <label>Tool Location and Shelf</label>
                <select name="location_id" required>
                    <option value="">Select location...</option>
                    <?php foreach ($locations as $location): ?>
                        <option
                            value="<?= (int)$location['id'] ?>"
                            <?= ((int)$tool['location_id'] === (int)$location['id'])
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

                <label>Status</label>
                <select name="status">
                    <?php foreach (
                        ['available', 'checked_out', 'maintenance', 'retired']
                        as $status
                    ): ?>
                        <option
                            value="<?= $status ?>"
                            <?= $tool['status'] === $status ? 'selected' : '' ?>
                        >
                            <?= e(tool_status_label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label>Notes</label>
        <textarea name="notes"><?= e($tool['notes']) ?></textarea>

        <div class="actions">
            <button <?= $locations ? '' : 'disabled' ?>>Save Tool</button>
            <a class="button secondary" href="tools.php">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nameInput = document.getElementById('tool_name');
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
