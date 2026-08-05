<?php
require __DIR__ . '/includes/header.php';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? '';
$locationId = (int)($_GET['location_id'] ?? 0);
$stock = $_GET['stock'] ?? '';
$format = $_GET['format'] ?? '';

if (!in_array($type, ['', 'tool', 'accessory', 'consumable'], true)) $type = '';
if (!in_array($stock, ['', 'available', 'checked_out', 'low', 'out', 'inactive'], true)) $stock = '';

$params = [];
$parts = [];

if ($type === '' || $type === 'tool') {
    $where = ['1=1'];
    if ($q !== '') {
        $where[] = '(t.tool_name LIKE ? OR t.internal_id LIKE ? OR t.serial_number LIKE ? OR t.category LIKE ? OR t.manufacturer LIKE ? OR t.model_number LIKE ? OR t.tool_location LIKE ?)';
        $term = '%' . $q . '%';
        array_push($params, $term, $term, $term, $term, $term, $term, $term);
    }
    if ($locationId > 0) { $where[] = 't.location_id=?'; $params[] = $locationId; }
    if ($stock === 'available') $where[] = "t.status='available'";
    if ($stock === 'checked_out' || $stock === 'out') $where[] = "t.status='checked_out'";
    if ($stock === 'inactive') $where[] = "t.status='retired'";
    if ($stock === 'low') $where[] = '1=0';

    $parts[] = "SELECT 'tool' item_type,t.id,t.tool_name item_name,t.internal_id,t.serial_number,
        COALESCE(t.category,'') category,COALESCE(t.manufacturer,'') manufacturer,
        COALESCE(t.model_number,'') model_number,t.location_id,t.tool_location,
        1 quantity_total,CASE WHEN t.status='available' THEN 1 ELSE 0 END quantity_available,
        0 low_stock_level,t.status,1 active,t.notes
        FROM tools t WHERE " . implode(' AND ', $where);
}

if ($type === '' || $type === 'accessory' || $type === 'consumable') {
    $where = ['1=1'];
    if ($type === 'accessory') $where[] = 'a.is_consumable=0';
    if ($type === 'consumable') $where[] = 'a.is_consumable=1';
    if ($q !== '') {
        $where[] = '(a.accessory_name LIKE ? OR a.internal_id LIKE ? OR a.tool_location LIKE ? OR a.notes LIKE ?)';
        $term = '%' . $q . '%';
        array_push($params, $term, $term, $term, $term);
    }
    if ($locationId > 0) { $where[] = 'a.location_id=?'; $params[] = $locationId; }
    if ($stock === 'available') $where[] = 'a.active=1 AND a.quantity_available>0';
    if ($stock === 'out') $where[] = 'a.quantity_available=0';
    if ($stock === 'low') $where[] = 'a.low_stock_level>0 AND a.quantity_available<=a.low_stock_level';
    if ($stock === 'inactive') $where[] = 'a.active=0';
    if ($stock === 'checked_out') $where[] = '1=0';

    $parts[] = "SELECT CASE WHEN a.is_consumable=1 THEN 'consumable' ELSE 'accessory' END item_type,
        a.id,a.accessory_name item_name,COALESCE(a.internal_id,'') internal_id,'' serial_number,
        '' category,'' manufacturer,'' model_number,a.location_id,COALESCE(a.tool_location,'') tool_location,
        a.quantity_total,a.quantity_available,COALESCE(a.low_stock_level,0) low_stock_level,
        CASE WHEN a.active=0 THEN 'inactive' WHEN a.quantity_available=0 THEN 'out'
             WHEN a.low_stock_level>0 AND a.quantity_available<=a.low_stock_level THEN 'low'
             ELSE 'available' END status,a.active,a.notes
        FROM accessories a WHERE " . implode(' AND ', $where);
}

$sql = implode(' UNION ALL ', $parts) . ' ORDER BY item_type,item_name,internal_id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$locations = $pdo->query('SELECT * FROM tool_locations ORDER BY active DESC,location_name,area,shelf')->fetchAll();

$summary = ['tools'=>0,'accessories'=>0,'consumables'=>0,'units_available'=>0,'low_stock'=>0,'out_of_stock'=>0];
foreach ($rows as $row) {
    if ($row['item_type']==='tool') $summary['tools']++;
    if ($row['item_type']==='accessory') $summary['accessories']++;
    if ($row['item_type']==='consumable') $summary['consumables']++;
    $summary['units_available'] += (int)$row['quantity_available'];
    if ($row['status']==='low') $summary['low_stock']++;
    if ($row['status']==='out') $summary['out_of_stock']++;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inventory-report-' . date('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Type','Item','Internal ID','Serial Number','Category','Manufacturer','Model','Location','Total','Available','Status','Low Stock Level','Notes']);
    foreach ($rows as $row) {
        fputcsv($out, [ucfirst($row['item_type']),$row['item_name'],$row['internal_id'],$row['serial_number'],$row['category'],$row['manufacturer'],$row['model_number'],$row['tool_location'],$row['quantity_total'],$row['quantity_available'],$row['status'],$row['low_stock_level'],$row['notes']]);
    }
    fclose($out);
    exit;
}

$export = $_GET;
$export['format'] = 'csv';
$csvUrl = 'inventory.php?' . http_build_query($export);
?>
<style>
@media print{.topbar,.inventory-actions,.inventory-filters,.no-print{display:none!important}.container{max-width:none!important;width:100%!important}.card{box-shadow:none!important;border:0!important}table{font-size:10pt}}
.inventory-low{background:#fff7ed}.inventory-out{background:#fef2f2}
</style>

<div class="card inventory-filters">
<h2>Inventory Search and Report</h2>
<form method="get">
<div class="grid two">
<div><label>Search Inventory</label><input name="q" value="<?= e($q) ?>" placeholder="Name, ID, serial, category, model, or location"></div>
<div><label>Inventory Type</label><select name="type">
<option value="">All inventory</option>
<option value="tool" <?= $type==='tool'?'selected':'' ?>>Serialized tools</option>
<option value="accessory" <?= $type==='accessory'?'selected':'' ?>>Returnable accessories</option>
<option value="consumable" <?= $type==='consumable'?'selected':'' ?>>Consumables</option>
</select></div>
<div><label>Location and Shelf</label><select name="location_id"><option value="0">All locations</option>
<?php foreach($locations as $location): ?><option value="<?= (int)$location['id'] ?>" <?= $locationId===(int)$location['id']?'selected':'' ?>><?= e(location_label($location)) ?><?= !$location['active']?' (Inactive)':'' ?></option><?php endforeach; ?>
</select></div>
<div><label>Stock Status</label><select name="stock">
<option value="">All statuses</option>
<option value="available" <?= $stock==='available'?'selected':'' ?>>Available</option>
<option value="checked_out" <?= $stock==='checked_out'?'selected':'' ?>>Checked out tools</option>
<option value="low" <?= $stock==='low'?'selected':'' ?>>Low stock</option>
<option value="out" <?= $stock==='out'?'selected':'' ?>>Out of stock</option>
<option value="inactive" <?= $stock==='inactive'?'selected':'' ?>>Inactive or retired</option>
</select></div>
</div>
<div class="actions inventory-actions"><button>Search / Run Report</button><a class="button secondary" href="inventory.php">Clear</a><a class="button secondary" href="<?= e($csvUrl) ?>">Export CSV</a><button type="button" class="secondary" onclick="window.print()">Print Report</button></div>
</form>
</div>

<div class="grid cards" style="margin-top:18px">
<div class="card"><h3>Tool Records</h3><div class="stat"><?= $summary['tools'] ?></div></div>
<div class="card"><h3>Accessory Records</h3><div class="stat"><?= $summary['accessories'] ?></div></div>
<div class="card"><h3>Consumable Records</h3><div class="stat"><?= $summary['consumables'] ?></div></div>
<div class="card"><h3>Available Units</h3><div class="stat"><?= $summary['units_available'] ?></div></div>
<div class="card"><h3>Low Stock</h3><div class="stat"><?= $summary['low_stock'] ?></div></div>
<div class="card"><h3>Out of Stock</h3><div class="stat"><?= $summary['out_of_stock'] ?></div></div>
</div>

<div class="card" style="margin-top:18px">
<div class="actions no-print" style="float:right"><a class="button" href="inventory_adjust.php">Adjust Count</a><a class="button secondary" href="locations.php">Locations</a><a class="button secondary" href="accessories.php">Add Supply</a><a class="button secondary" href="tool_form.php">Add Tool</a></div>
<h2>Inventory Results</h2><p class="muted"><?= count($rows) ?> matching record(s)</p>
<div class="table-wrap"><table><thead><tr><th>Type</th><th>Item</th><th>ID / Serial</th><th>Location / Shelf</th><th>Count</th><th>Status</th><th class="no-print"></th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7">No inventory matched the filters.</td></tr><?php endif; ?>
<?php foreach($rows as $row): $cls=$row['status']==='low'?'inventory-low':($row['status']==='out'?'inventory-out':''); ?>
<tr class="<?= $cls ?>">
<td><?= e(ucfirst($row['item_type'])) ?></td>
<td><strong><?= e($row['item_name']) ?></strong><?php if($row['category']): ?><br><span class="muted"><?= e($row['category']) ?></span><?php endif; ?><?php if($row['manufacturer']||$row['model_number']): ?><br><span class="muted"><?= e(trim($row['manufacturer'].' '.$row['model_number'])) ?></span><?php endif; ?></td>
<td><?= e($row['internal_id']?:'—') ?><?php if($row['serial_number']): ?><br><span class="muted">Serial: <?= e($row['serial_number']) ?></span><?php endif; ?></td>
<td><?= e($row['tool_location']?:'Unassigned') ?></td>
<td><?= (int)$row['quantity_available'] ?> / <?= (int)$row['quantity_total'] ?></td>
<td><span class="badge <?= in_array($row['status'],['low','out'],true)?'overdue':'open' ?>"><?= e(ucwords(str_replace('_',' ',$row['status']))) ?></span></td>
<td class="no-print"><?php if($row['item_type']==='tool'): ?><a class="button secondary" href="tool_form.php?id=<?= (int)$row['id'] ?>">Edit</a><?php else: ?><a class="button secondary" href="accessories.php?id=<?= (int)$row['id'] ?>">Edit</a> <a class="button secondary" href="inventory_adjust.php?id=<?= (int)$row['id'] ?>">Adjust</a><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
