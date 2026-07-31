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

function location_label(array $location): string
{
    $parts = [trim((string)($location['location_name'] ?? ''))];
    if (trim((string)($location['area'] ?? '')) !== '') $parts[] = trim((string)$location['area']);
    if (trim((string)($location['shelf'] ?? '')) !== '') $parts[] = trim((string)$location['shelf']);
    return implode(' - ', array_filter($parts));
}

function checkout_cutoff_at(): DateTimeImmutable
{
    return new DateTimeImmutable(date('Y-m-d') . ' ' . CHECKOUT_CUTOFF_TIME, new DateTimeZone(TIMEZONE));
}

function checkout_open_time(): DateTimeImmutable
{
    return new DateTimeImmutable(date('Y-m-d') . ' ' . CHECKOUT_OPEN_TIME, new DateTimeZone(TIMEZONE));
}

function return_due_at(): string
{
    return date('Y-m-d') . ' ' . RETURN_DUE_TIME;
}

function checkout_is_open(): bool
{
    $now = new DateTimeImmutable('now', new DateTimeZone(TIMEZONE));
    return $now >= checkout_open_time() && $now < checkout_cutoff_at();
}

function checkout_cutoff_label(): string
{
    return checkout_cutoff_at()->format('g:i A');
}

function return_due_label(): string
{
    $due = new DateTimeImmutable(date('Y-m-d') . ' ' . RETURN_DUE_TIME, new DateTimeZone(TIMEZONE));
    return $due->format('g:i A');
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

function checkout_many(
    array $toolIds,
    int $employeeId,
    string $issuedBy,
    string $notes = '',
    array $accessoryQuantities = [],
    ?int $bundleId = null
): int {
    if (!checkout_is_open()) {
        throw new RuntimeException(
            'Checkout is closed. New checkouts are only permitted between '
            . checkout_open_time()->format('g:i A')
            . ' and '
            . checkout_cutoff_label()
            . '.'
        );
    }

    $toolIds = array_values(array_unique(array_filter(array_map('intval', $toolIds))));
    $accessoryQuantities = array_filter(
        array_map('intval', $accessoryQuantities),
        static fn(int $qty): bool => $qty > 0
    );

    if (!$toolIds && !$accessoryQuantities) {
        throw new RuntimeException('Select at least one tool, accessory, or consumable item.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $employeeStmt = $pdo->prepare('SELECT id FROM employees WHERE id=? AND active=1');
        $employeeStmt->execute([$employeeId]);
        if (!$employeeStmt->fetch()) {
            throw new RuntimeException('Employee not found or inactive.');
        }

        if ($toolIds) {
            $placeholders = implode(',', array_fill(0, count($toolIds), '?'));
            $toolStmt = $pdo->prepare("SELECT * FROM tools WHERE id IN ($placeholders) FOR UPDATE");
            $toolStmt->execute($toolIds);
            $tools = $toolStmt->fetchAll();

            if (count($tools) !== count($toolIds)) {
                throw new RuntimeException('One or more selected tools were not found.');
            }

            foreach ($tools as $tool) {
                if ($tool['status'] !== 'available') {
                    throw new RuntimeException(
                        $tool['tool_name'] . ' (' . $tool['internal_id'] . ') is not available.'
                    );
                }
            }
        }

        $batch = $pdo->prepare(
            'INSERT INTO checkout_batches
             (employee_id,issued_by,checked_out_at,due_at,bundle_id,checkout_notes,status)
             VALUES(?,?,NOW(),?,?,?,"open")'
        );
        $batch->execute([
            $employeeId,
            $issuedBy,
            return_due_at(),
            $bundleId ?: null,
            $notes
        ]);
        $batchId = (int)$pdo->lastInsertId();

        if ($toolIds) {
            $insert = $pdo->prepare(
                'INSERT INTO checkouts
                 (batch_id,tool_id,employee_id,issued_by,checked_out_at,due_at,checkout_notes,status)
                 VALUES(?,?,?, ?,NOW(),?, ?,"open")'
            );
            $update = $pdo->prepare('UPDATE tools SET status="checked_out" WHERE id=?');

            foreach ($toolIds as $toolId) {
                $insert->execute([
                    $batchId,
                    $toolId,
                    $employeeId,
                    $issuedBy,
                    return_due_at(),
                    $notes
                ]);
                $update->execute([$toolId]);
            }
        }

        $returnableAccessoryCount = 0;

        if ($accessoryQuantities) {
            $itemGet = $pdo->prepare(
                'SELECT * FROM accessories WHERE id=? AND active=1 FOR UPDATE'
            );
            $itemInsert = $pdo->prepare(
                'INSERT INTO checkout_accessories
                 (batch_id,accessory_id,quantity,is_consumable)
                 VALUES(?,?,?,?)'
            );
            $itemUpdate = $pdo->prepare(
                'UPDATE accessories
                 SET quantity_available=quantity_available-?
                 WHERE id=?'
            );

            foreach ($accessoryQuantities as $itemId => $qty) {
                $itemId = (int)$itemId;
                $itemGet->execute([$itemId]);
                $item = $itemGet->fetch();

                if (!$item) {
                    throw new RuntimeException('Accessory or consumable item not found.');
                }

                if ((int)$item['quantity_available'] < $qty) {
                    throw new RuntimeException(
                        'Not enough ' . $item['accessory_name'] . ' available.'
                    );
                }

                $isConsumable = (int)($item['is_consumable'] ?? 0);

                $itemInsert->execute([
                    $batchId,
                    $itemId,
                    $qty,
                    $isConsumable
                ]);
                $itemUpdate->execute([$qty, $itemId]);

                if (!$isConsumable) {
                    $returnableAccessoryCount += $qty;
                }
            }
        }

        /*
         * A checkout containing only consumables has nothing to return.
         * Close the batch immediately while preserving its issue history.
         */
        if (!$toolIds && $returnableAccessoryCount === 0) {
            $pdo->prepare(
                'UPDATE checkout_batches
                 SET status="returned",
                     returned_at=NOW(),
                     received_by="Consumable issue"
                 WHERE id=?'
            )->execute([$batchId]);
        }

        $pdo->commit();
        return $batchId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function checkout_tool(
    int $toolId,
    int $employeeId,
    string $issuedBy,
    string $notes = ''
): void {
    checkout_many([$toolId], $employeeId, $issuedBy, $notes);
}

function return_tool(
    int $checkoutId,
    string $receivedBy,
    string $condition,
    string $notes = ''
): void {
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
             SET returned_at = NOW(),
                 received_by = ?,
                 return_condition = ?,
                 return_notes = ?,
                 status = "returned"
             WHERE id = ?'
        );
        $close->execute([$receivedBy, $condition, $notes, $checkoutId]);

        $newStatus = $condition === 'damaged' ? 'maintenance' : 'available';
        $tool = $pdo->prepare('UPDATE tools SET status = ? WHERE id = ?');
        $tool->execute([$newStatus, $checkout['tool_id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function auto_copyright($year = 'auto')
{
    if (intval($year) == 'auto') {
        $year = date('Y');
    }

    if (intval($year) == date('Y')) {
        echo intval($year);
    }

    if (intval($year) < date('Y')) {
        echo intval($year) . ' - ' . date('Y');
    }

    if (intval($year) > date('Y')) {
        echo date('Y');
    }
}
