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

function checkout_tool(int $toolId, int $employeeId, string $issuedBy, string $notes = ''): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $toolStmt = $pdo->prepare('SELECT * FROM tools WHERE id = ? FOR UPDATE');
        $toolStmt->execute([$toolId]);
        $tool = $toolStmt->fetch();
        if (!$tool) {
            throw new RuntimeException('Tool not found.');
        }
        if ($tool['status'] !== 'available') {
            throw new RuntimeException('This tool is not currently available.');
        }

        $employeeStmt = $pdo->prepare('SELECT id FROM employees WHERE id = ? AND active = 1');
        $employeeStmt->execute([$employeeId]);
        if (!$employeeStmt->fetch()) {
            throw new RuntimeException('Employee not found or inactive.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO checkouts (tool_id, employee_id, issued_by, checked_out_at, due_at, checkout_notes, status)
             VALUES (?, ?, ?, NOW(), ?, ?, "open")'
        );
        $insert->execute([$toolId, $employeeId, $issuedBy, end_of_workday(), $notes]);

        $update = $pdo->prepare('UPDATE tools SET status = "checked_out" WHERE id = ?');
        $update->execute([$toolId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
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
