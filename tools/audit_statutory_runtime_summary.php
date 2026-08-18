<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = Core\DbPdo::conn();
$count = static fn(string $sql): int => (int) $db->query($sql)->fetchColumn();

$result = [
    'types' => $count("SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND is_active=1"),
    'standards' => $count('SELECT COUNT(*) FROM system_statutory_standards'),
    'sources' => $count('SELECT COUNT(*) FROM system_statutory_standard_sources'),
    'invalid_json' => $count('SELECT COUNT(*) FROM system_statutory_standards WHERE JSON_VALID(value_data)=0'),
    'invalid_period' => $count('SELECT COUNT(*) FROM system_statutory_standards WHERE effective_to IS NOT NULL AND effective_from>effective_to'),
    'overlaps' => $count(
        "SELECT COUNT(*) FROM system_statutory_standards a"
        . " JOIN system_statutory_standards b ON a.id<b.id AND a.standard_type_code=b.standard_type_code"
        . " AND a.effective_from<=COALESCE(b.effective_to,'9999-12-31')"
        . " AND COALESCE(a.effective_to,'9999-12-31')>=b.effective_from"
    ),
    'current_overlaps' => $count(
        'SELECT COUNT(*) FROM (SELECT standard_type_code FROM system_statutory_standards'
        . ' WHERE effective_from<=CURRENT_DATE AND (effective_to IS NULL OR effective_to>=CURRENT_DATE)'
        . ' GROUP BY standard_type_code HAVING COUNT(*)>1) duplicated'
    ),
    'without_source' => $count(
        'SELECT COUNT(*) FROM system_statutory_standards s'
        . ' LEFT JOIN system_statutory_standard_sources src ON src.standard_id=s.id WHERE src.id IS NULL'
    ),
    'orphan_sources' => $count(
        'SELECT COUNT(*) FROM system_statutory_standard_sources src'
        . ' LEFT JOIN system_statutory_standards s ON s.id=src.standard_id WHERE s.id IS NULL'
    ),
    'invalid_types' => $count(
        "SELECT COUNT(*) FROM system_statutory_standards s LEFT JOIN system_codes c"
        . " ON c.code_group='STATUTORY_STANDARD_TYPE' AND c.code=s.standard_type_code WHERE c.id IS NULL"
    ),
];

$permissionStatement = $db->query(
    "SELECT permission_key,is_active FROM auth_permissions"
    . " WHERE permission_key LIKE 'api.settings.statutory_standards.%' ORDER BY permission_key"
);
$result['permissions'] = $permissionStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
$result['standard_meta_keys'] = array_column(
    (new App\Services\System\DataTableColumnMetaService($db))->columnsForDomain('statutory-standard'),
    'key'
);

$startedAt = microtime(true);
$statutoryList = (new App\Services\System\StatutoryStandardService($db))->list([
    'draw' => 1,
    'start' => 0,
    'length' => 200,
    'filters' => '[]',
    'search' => ['value' => ''],
]);
$result['statutory_list_performance'] = [
    'rows' => count($statutoryList['data'] ?? []),
    'bytes' => strlen((string) json_encode($statutoryList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
    'contains_value_data' => array_key_exists('value_data', $statutoryList['data'][0] ?? []),
    'contains_value_summary' => array_key_exists('value_summary', $statutoryList['data'][0] ?? []),
];
$statutoryService = new App\Services\System\StatutoryStandardService($db);
$currentList = $statutoryService->list([
    'draw' => 2, 'start' => 0, 'length' => 200,
    'filters' => json_encode([['field' => 'period_status', 'value' => 'CURRENT']]),
    'search' => ['value' => ''],
]);
$firstId = (string) ($statutoryList['data'][0]['id'] ?? '');
$firstDetail = $firstId !== '' ? $statutoryService->detail($firstId)['data'] : [];
$result['statutory_projection_contract'] = [
    'current_rows' => count($currentList['data'] ?? []),
    'detail_contains_value_data' => array_key_exists('value_data', $firstDetail),
    'detail_contains_sources' => array_key_exists('sources', $firstDetail),
];

$startedAt = microtime(true);
$codeList = (new App\Services\System\CodeService($db))->getList();
$result['code_list_performance'] = [
    'rows' => count($codeList),
    'bytes' => strlen((string) json_encode(['success' => true, 'data' => $codeList], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
];

$result['statutory_list_explain'] = $db->query(
    "EXPLAIN SELECT s.id,s.sort_no,s.standard_type_code,c.code_name,s.effective_from,s.effective_to,"
    . "s.value_data,s.note,s.created_at,s.created_by,s.updated_at,s.updated_by,COALESCE(src.source_count,0)"
    . " FROM system_statutory_standards s"
    . " JOIN system_codes c ON c.code_group='STATUTORY_STANDARD_TYPE' AND c.code=s.standard_type_code"
    . " LEFT JOIN (SELECT standard_id,COUNT(*) source_count FROM system_statutory_standard_sources GROUP BY standard_id) src"
    . " ON src.standard_id=s.id ORDER BY s.effective_from DESC,s.sort_no DESC,s.id DESC LIMIT 200"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
