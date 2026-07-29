<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function end_of_workday(): string
{
    return date('Y-m-d 23:59:59');
}

function tool_status_label(string $status): string
{
    return match ($status) {
        'available' => 'Available',
        'checked_out' => 'Checked Out',
        'maintenance' => 'Maintenance',
        'retired' => 'Retired',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function checkout_many(array $toolIds, int $employeeId, string $issuedBy, string $notes = '', array $accessoryQuantities = [], ?int $bundleId = null): int
{
    $toolIds = array_values(array_unique(array_filter(array_map('intval', $toolIds))));
    if (!$toolIds) throw new RuntimeException('Select at least one tool.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        $employeeStmt=$pdo->prepare('SELECT id FROM employees WHERE id=? AND active=1'); $employeeStmt->execute([$employeeId]);
        if(!$employeeStmt->fetch()) throw new RuntimeException('Employee not found or inactive.');
        $placeholders=implode(',',array_fill(0,count($toolIds),'?'));
        $toolStmt=$pdo->prepare("SELECT * FROM tools WHERE id IN ($placeholders) FOR UPDATE"); $toolStmt->execute($toolIds); $tools=$toolStmt->fetchAll();
        if(count($tools)!==count($toolIds)) throw new RuntimeException('One or more selected tools were not found.');
        foreach($tools as $tool) if($tool['status']!=='available') throw new RuntimeException($tool['tool_name'].' ('.$tool['internal_id'].') is not available.');
        $batch=$pdo->prepare('INSERT INTO checkout_batches(employee_id,issued_by,checked_out_at,due_at,bundle_id,checkout_notes,status) VALUES(?,?,NOW(),?,?,?,"open")');
        $batch->execute([$employeeId,$issuedBy,end_of_workday(),$bundleId?:null,$notes]); $batchId=(int)$pdo->lastInsertId();
        $insert=$pdo->prepare('INSERT INTO checkouts(batch_id,tool_id,employee_id,issued_by,checked_out_at,due_at,checkout_notes,status) VALUES(?,?,?, ?,NOW(),?, ?,"open")');
        $update=$pdo->prepare('UPDATE tools SET status="checked_out" WHERE id=?');
        foreach($toolIds as $toolId){$insert->execute([$batchId,$toolId,$employeeId,$issuedBy,end_of_workday(),$notes]);$update->execute([$toolId]);}
        if($accessoryQuantities){
            $aGet=$pdo->prepare('SELECT * FROM accessories WHERE id=? AND active=1 FOR UPDATE');
            $aInsert=$pdo->prepare('INSERT INTO checkout_accessories(batch_id,accessory_id,quantity) VALUES(?,?,?)');
            $aUpdate=$pdo->prepare('UPDATE accessories SET quantity_available=quantity_available-? WHERE id=?');
            foreach($accessoryQuantities as $accessoryId=>$qty){$accessoryId=(int)$accessoryId;$qty=(int)$qty;if($qty<1)continue;$aGet->execute([$accessoryId]);$a=$aGet->fetch();if(!$a)throw new RuntimeException('Accessory not found.');if((int)$a['quantity_available']<$qty)throw new RuntimeException('Not enough '.$a['accessory_name'].' available.');$aInsert->execute([$batchId,$accessoryId,$qty]);$aUpdate->execute([$qty,$accessoryId]);}
        }
        $pdo->commit(); return $batchId;
    } catch(Throwable $e){$pdo->rollBack();throw $e;}
}

function checkout_tool(int $toolId, int $employeeId, string $issuedBy, string $notes = ''): void
{
    checkout_many([$toolId], $employeeId, $issuedBy, $notes);
}

function return_tool(int $checkoutId, string $receivedBy, string $condition, string $notes = ''): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM checkouts WHERE id = ? FOR UPDATE');
        $stmt->execute([$checkoutId]);
        $checkout = $stmt->fetch();
        if (!$checkout || $checkout['status'] !== 'open') {
            throw new RuntimeException('Open checkout not found.');
        }

        $close = $pdo->prepare(
            'UPDATE checkouts
             SET returned_at = NOW(), received_by = ?, return_condition = ?, return_notes = ?, status = "returned"
             WHERE id = ?'
        );
        $close->execute([$receivedBy, $condition, $notes, $checkoutId]);

        $newStatus = $condition === 'damaged' ? 'maintenance' : 'available';
        $tool = $pdo->prepare('UPDATE tools SET status = ? WHERE id = ?');
        $tool->execute([$newStatus, $checkout['tool_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
