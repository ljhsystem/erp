<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$service = new DataTableColumnMetaService($pdo);
$expected = [
    'attendance-daily' => ['table' => 'institution_attendance_daily_records', 'keys' => ['work_date', 'actual_work_seconds', 'calculation_status_code']],
    'attendance-monthly' => ['table' => 'institution_attendance_monthly_closure_histories', 'keys' => ['closing_month', 'actual_work_seconds', 'revision']],
    'attendance-exceptions' => ['table' => 'institution_attendance_daily_exceptions', 'keys' => ['daily_record_id', 'exception_type_code', 'resolution_status_code']],
    'attendance-closures' => ['table' => 'institution_attendance_monthly_closures', 'keys' => ['closing_month', 'close_status_code', 'current_revision']],
];

$result = [];
foreach ($expected as $domain => $contract) {
    if (!$service->hasDomain($domain)) {
        throw new RuntimeException("지원 메타 도메인 누락: {$domain}");
    }
    $columns = $service->columnsForDomain($domain);
    $byKey = array_column($columns, null, 'key');
    foreach ($columns as $column) {
        if (($column['column_type'] ?? '') !== 'physical' || ($column['table'] ?? '') !== $contract['table']) {
            throw new RuntimeException("{$domain}에 원본 테이블 외 metadata가 포함되었습니다.");
        }
    }
    foreach ($contract['keys'] as $key) {
        $column = $byKey[$key] ?? null;
        if (!is_array($column)) {
            throw new RuntimeException("{$domain} 컬럼 누락: {$key}");
        }
        foreach (['source_column', 'source_ordinal_position', 'data_type', 'is_nullable', 'required'] as $metaKey) {
            if (!array_key_exists($metaKey, $column)) {
                throw new RuntimeException("{$domain}.{$key} DB metadata 누락: {$metaKey}");
            }
        }
        $statement = $pdo->prepare('SELECT COLUMN_COMMENT, IS_NULLABLE, ORDINAL_POSITION, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND COLUMN_NAME=:column_name');
        $statement->execute([':table_name' => $column['table'], ':column_name' => $column['source_column']]);
        $schema = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($schema)) {
            throw new RuntimeException("{$domain}.{$key} information_schema 원본 누락");
        }
        if ((int) $column['source_ordinal_position'] !== (int) $schema['ORDINAL_POSITION']
            || (string) $column['data_type'] !== (string) $schema['DATA_TYPE']
            || (string) $column['is_nullable'] !== (string) $schema['IS_NULLABLE']
            || (bool) $column['required'] !== ((string) $schema['IS_NULLABLE'] === 'NO')) {
            throw new RuntimeException("{$domain}.{$key} information_schema metadata 불일치");
        }
        if (trim((string) $schema['COLUMN_COMMENT']) !== '' && (string) $column['label'] !== trim((string) $schema['COLUMN_COMMENT'])) {
            throw new RuntimeException("{$domain}.{$key} DB COMMENT 기본명 불일치");
        }
    }
    $result[$domain] = count($columns);
}

foreach (['employment-contract', 'personnel-action', 'job-assignment'] as $referenceDomain) {
    if (!$service->hasDomain($referenceDomain) || $service->columnsForDomain($referenceDomain) === []) {
        throw new RuntimeException("기준 화면 metadata 회귀: {$referenceDomain}");
    }
}

echo json_encode(['success' => true, 'domains' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
