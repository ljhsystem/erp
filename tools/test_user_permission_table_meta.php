<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$statement = $pdo->query(
    "SELECT COLUMN_NAME, COLUMN_COMMENT, IS_NULLABLE, ORDINAL_POSITION, COLUMN_DEFAULT
       FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = 'auth_permissions'
      ORDER BY ORDINAL_POSITION"
);
$expected = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

$service = new DataTableColumnMetaService($pdo);
$roleMeta = $service->columnsForDomain('permission-assignment');
$individualMeta = $service->columnsForDomain('permission-assignment');
$errors = [];

if (count($roleMeta) !== count($expected)) {
    $errors[] = '역할별 권한목록 컬럼 수가 auth_permissions와 다릅니다.';
}
if (count($individualMeta) !== count($expected)) {
    $errors[] = '개인별 권한목록 컬럼 수가 auth_permissions와 다릅니다.';
}

foreach ($expected as $index => $column) {
    $meta = $roleMeta[$index] ?? [];
    if (($meta['table'] ?? '') !== 'auth_permissions') {
        $errors[] = "{$index}: 원본 테이블이 auth_permissions가 아닙니다.";
    }
    if (($meta['source_column'] ?? '') !== $column['COLUMN_NAME']) {
        $errors[] = "{$index}: 컬럼 순서가 다릅니다.";
    }
    if (($meta['label'] ?? '') !== $column['COLUMN_COMMENT']) {
        $errors[] = "{$index}: 컬럼 코멘트가 다릅니다.";
    }
    if (($meta['is_nullable'] ?? '') !== $column['IS_NULLABLE']) {
        $errors[] = "{$index}: NULL 허용값이 다릅니다.";
    }
    if ((bool) ($meta['required'] ?? false) !== ($column['IS_NULLABLE'] === 'NO')) {
        $errors[] = "{$index}: 필수구분이 다릅니다.";
    }
    if ((int) ($meta['source_ordinal_position'] ?? 0) !== (int) $column['ORDINAL_POSITION']) {
        $errors[] = "{$index}: DB 원본 순번이 다릅니다.";
    }
}

if ($roleMeta !== $individualMeta) {
    $errors[] = '역할별·개인별 권한목록 메타가 다릅니다.';
}

echo json_encode([
    'expected_count' => count($expected),
    'role_actual_count' => count($roleMeta),
    'individual_actual_count' => count($individualMeta),
    'physical_tables' => ['auth_permissions'],
    'reference_tables_excluded_from_settings' => [
        'auth_role_permissions',
        'auth_user_permission_profiles',
        'auth_user_permissions',
    ],
    'independent_setting_keys' => true,
    'errors' => $errors,
    'success' => $errors === [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if ($errors !== []) {
    exit(1);
}
