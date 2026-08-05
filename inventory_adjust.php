<?php
require __DIR__ . '/includes/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $id=(int)($_POST['id']??0);
        $newAvailable=max(0,(int)($_POST['new_available']??0));
        $reason=trim($_POST['reason']??'');
        $reference=trim($_POST['reference']??'');
        if($reason==='') throw new RuntimeException('An adjustment reason is required.');

        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT * FROM accessories WHERE id=? FOR UPDATE');
        $s->execute([$id]);
        $item=$s->fetch();
        if(!$item) throw new RuntimeException('Inventory item not found.');

        $old=(int)$item['quantity_available'];
        $total=(int)$item['quantity_total'];
        if($newAvailable>$total) throw new RuntimeException('Available quantity cannot exceed total quantity. Increase total quantity on the item edit page first.');

        $change=$newAvailable-$old;
        $pdo->prepare('UPDATE accessories SET quantity_available=? WHERE id=?')->execute([$newAvailable,$id]);
        $user=current_user();
        $pdo->prepare('INSERT INTO inventory_adjustments(accessory_id,old_quantity,new_quantity,quantity_change,reason,reference,adjusted_by_user_id,adjusted_by_name,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())')
            ->execute([$id,$old,$newAvailable,$change,$reason,$reference?:null,(int)($user['id']??0)?:null,$user['full_name']??'Unknown User']);
        $pdo->commit();
        flash('success','Inventory count updated from '.$old.' to '.$newAvailable.'.');
        redirect('inventory_adjust.php?id='.$id);
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        flash('error',$e->getMessage());
        redirect('inventory_adjust.php'.($id?'?id='.$id:''));
    }
}

$items=$pdo->query('SELECT * FROM accessories ORDER BY is_consumable,accessory_name')->fetchAll();
$item=null;$history=[];
if($id){
    $s=$pdo->prepare('SELECT * FROM accessories WHERE id=?');$s->execute([$id]);$item=$s->fetch();
    $s=$pdo->prepare('SELECT * FROM inventory_adjustments WHERE accessory_id=? ORDER BY created_at DESC,id DESC LIMIT 100');$s->execute([$id]);$history=$s->fetchAll();
}
$recent=$pdo->query('SELECT ia.*,a.accessory_name,a.internal_id,a.is_consumable FROM inventory_adjustments ia JOIN accessories a ON a.id=ia.accessory_id ORDER BY ia.created_at DESC,ia.id DESC LIMIT 100')->fetchAll();
?>
<div class="grid two">
<div class="card"><h2>Adjust Inventory Count</h2>
<form method="get"><label>Inventory Item</label><select name="id" required onchange="this.form.submit()"><option value="">Select item...</option>
<?php foreach($items as $row): ?><option value="<?= (int)$row['id'] ?>" <?= $id===(int)$row['id']?'selected':'' ?>><?= e($row['accessory_name']) ?> — <?= $row['is_consumable']?'Consumable':'Accessory' ?> — <?= (int)$row['quantity_available'] ?>/<?= (int)$row['quantity_total'] ?></option><?php endforeach; ?>
</select></form>
<?php if($item): ?><hr><p><strong><?= e($item['accessory_name']) ?></strong><br><span class="muted"><?= e($item['internal_id']?:'No internal ID') ?> · <?= e($item['tool_location']?:'No location') ?></span></p>
<form method="post"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<div class="grid two"><div><label>Current Available</label><input value="<?= (int)$item['quantity_available'] ?>" disabled></div><div><label>Total Quantity</label><input value="<?= (int)$item['quantity_total'] ?>" disabled></div></div>
<label>New Available Count</label><input type="number" min="0" max="<?= (int)$item['quantity_total'] ?>" name="new_available" value="<?= (int)$item['quantity_available'] ?>" required>
<label>Reason</label><select name="reason" required><option value="">Select reason...</option><option>Physical inventory count</option><option>Received new stock</option><option>Damaged or discarded</option><option>Missing inventory</option><option>Data correction</option><option>Transferred location</option><option>Other</option></select>
<label>Reference / Notes</label><textarea name="reference" placeholder="Purchase order, count sheet, incident, or explanation"></textarea><button>Save Count Adjustment</button></form>
<?php else: ?><p class="muted">Select an accessory or consumable item to adjust its count.</p><?php endif; ?></div>
<div class="card"><h2><?= $item?'Adjustment History':'Recent Adjustments' ?></h2><?php $display=$item?$history:$recent; ?>
<div class="table-wrap"><table><thead><tr><?php if(!$item): ?><th>Item</th><?php endif; ?><th>Date</th><th>Change</th><th>Count</th><th>Reason</th><th>Adjusted By</th></tr></thead><tbody>
<?php if(!$display): ?><tr><td colspan="<?= $item?5:6 ?>">No adjustments recorded.</td></tr><?php endif; ?>
<?php foreach($display as $row): ?><tr><?php if(!$item): ?><td><strong><?= e($row['accessory_name']) ?></strong><br><span class="muted"><?= e($row['internal_id']?:'No ID') ?></span></td><?php endif; ?><td><?= e(date('M j, Y g:i A',strtotime($row['created_at']))) ?></td><td><?= (int)$row['quantity_change']>0?'+':'' ?><?= (int)$row['quantity_change'] ?></td><td><?= (int)$row['old_quantity'] ?> → <?= (int)$row['new_quantity'] ?></td><td><?= e($row['reason']) ?><?php if($row['reference']): ?><br><span class="muted"><?= e($row['reference']) ?></span><?php endif; ?></td><td><?= e($row['adjusted_by_name']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><div class="actions"><a class="button secondary" href="inventory.php">Back to Inventory</a></div></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
