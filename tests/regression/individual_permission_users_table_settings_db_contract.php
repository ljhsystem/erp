<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\DataTableColumnMetaService;
use App\Repositories\Auth\UserPermissionRepository;
use Core\DbPdo;

$pdo = DbPdo::conn();
$service = new DataTableColumnMetaService($pdo);
$columns = $service->columnsForDomain('individual-permission-users');
$tables = [
    'user_employees',
];

$offset = 0;
$counts = [];
$employeeColumnNames = [];
foreach ($tables as $table) {
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME, ORDINAL_POSITION
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
          ORDER BY ORDINAL_POSITION'
    );
    $statement->execute([':table' => $table]);
    $physicalColumns = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $metadataColumns = array_slice($columns, $offset, count($physicalColumns));

    if (count($metadataColumns) !== count($physicalColumns)) {
        throw new RuntimeException("{$table}: 전체 물리컬럼 수가 일치하지 않습니다.");
    }

    foreach ($physicalColumns as $index => $physicalColumn) {
        $metadata = $metadataColumns[$index] ?? [];
        $sourceColumn = (string) ($physicalColumn['COLUMN_NAME'] ?? '');
        if (($metadata['table'] ?? '') !== $table
            || ($metadata['source_column'] ?? '') !== $sourceColumn
            || ($metadata['key'] ?? '') !== "{$table}.{$sourceColumn}"
            || (int) ($metadata['source_ordinal_position'] ?? 0) !== (int) ($physicalColumn['ORDINAL_POSITION'] ?? 0)
            || (int) ($metadata['ordinal_position'] ?? 0) !== $offset + $index + 1
        ) {
            throw new RuntimeException("{$table}: 원본 순서 또는 qualified key 계약이 일치하지 않습니다.");
        }
    }

    $counts[$table] = count($physicalColumns);
    if ($table === 'user_employees') {
        $employeeColumnNames = array_column($physicalColumns, 'COLUMN_NAME');
    }
    $offset += count($physicalColumns);
}

if ($offset !== count($columns)) {
    throw new RuntimeException('등록 원본 테이블 합계와 metadata 합계가 일치하지 않습니다.');
}

$rows = (new UserPermissionRepository($pdo))->listUsers();
$authUserCount = (int) $pdo->query('SELECT COUNT(*) FROM auth_users')->fetchColumn();
$linkedEmployeeCount = (int) $pdo->query('SELECT COUNT(*) FROM user_employees WHERE user_id IS NOT NULL')->fetchColumn();
if ($rows !== []) {
    $missingRowFields = array_values(array_filter(
        $employeeColumnNames,
        static fn(string $column): bool => !array_key_exists($column, $rows[0])
    ));
    if ($missingRowFields !== []) {
        throw new RuntimeException('사용자목록 API 물리컬럼 누락: ' . implode(', ', $missingRowFields));
    }
}

echo json_encode([
    'success' => true,
    'tables' => $counts,
    'total' => count($columns),
    'sample_row_checked' => $rows !== [],
    'auth_user_count' => $authUserCount,
    'linked_employee_count' => $linkedEmployeeCount,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
