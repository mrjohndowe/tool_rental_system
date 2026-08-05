<?php
declare(strict_types=1);

/**
 * Convert an inventory name into the readable portion of an Internal ID.
 *
 * Examples:
 *   Hammer Drill -> HAMMERDRILL
 *   4-1/2" Grinder -> 412GRINDER
 *   Black & Red Marker -> BLACKANDREDMARKER
 */
function internal_id_base_from_name(string $name): string
{
    $base = strtoupper(trim($name));
    $base = str_replace('&', ' AND ', $base);
    $base = preg_replace('/[^A-Z0-9]+/', '', $base) ?? '';
    $base = substr($base, 0, 30);

    return $base !== '' ? $base : 'ITEM';
}

/**
 * Generate the next available name-based Internal ID.
 *
 * Examples:
 *   HAMMERDRILL-001
 *   HAMMERDRILL-002
 *   SPRAYPAINT-001
 */
function generate_name_based_internal_id(
    string $name,
    string $table,
    ?int $excludeId = null
): string {
    $allowedTables = ['tools', 'accessories'];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid inventory table.');
    }

    $base = internal_id_base_from_name($name);
    $pdo = db();

    $sql = "
        SELECT internal_id
        FROM {$table}
        WHERE internal_id LIKE ?
    ";

    $params = [$base . '-%'];

    if ($excludeId !== null && $excludeId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $highest = 0;
    $pattern = '/^' . preg_quote($base, '/') . '-(\d+)$/';

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $internalId) {
        if (preg_match($pattern, (string)$internalId, $matches)) {
            $highest = max($highest, (int)$matches[1]);
        }
    }

    return sprintf('%s-%03d', $base, $highest + 1);
}
