<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$action = $argv[1] ?? 'verify';
if (!in_array($action, ['up', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_statutory_scope_removal.php [up|verify]\n");
    exit(1);
}

$db = DbPdo::conn();
$hasScopeData = static function () use ($db): bool {
    $statement = $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS"
        . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards' AND COLUMN_NAME='scope_data'"
    );
    return (int) $statement->fetchColumn() === 1;
};

if ($action === 'up' && $hasScopeData()) {
    $invalid = (int) $db->query(
        "SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INDUSTRIAL_ACCIDENT'"
        . " AND (JSON_UNQUOTE(JSON_EXTRACT(scope_data,'$.industry_code')) IS NULL"
        . " OR JSON_UNQUOTE(JSON_EXTRACT(scope_data,'$.industry_code'))=''"
        . " OR JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.employer_rate')) IS NULL)"
    )->fetchColumn();
    if ($invalid > 0) {
        throw new RuntimeException('변환할 수 없는 산재보험 적용조건 또는 보험료율 데이터가 있습니다.');
    }

    $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260812_01_remove_statutory_standard_scope.up.sql');
    if ($sql === false) {
        throw new RuntimeException('법정기준 적용조건 제거 Migration을 읽을 수 없습니다.');
    }
    foreach (preg_split('/;\s*(?:\r?\n|$)/', trim($sql)) ?: [] as $statement) {
        if (trim($statement) !== '') {
            $db->exec($statement);
        }
    }
}

if ($action === 'up') {
    $templateStatement = $db->query(
        "SELECT id,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE'"
        . " AND code='INDUSTRIAL_ACCIDENT' LIMIT 1"
    );
    $templateRow = $templateStatement->fetch(PDO::FETCH_ASSOC) ?: [];
    $templateData = json_decode((string) ($templateRow['extra_data'] ?? ''), true);
    if (is_string($templateData['fields'][0] ?? null)) {
        $templateData['fields'][0] = json_decode($templateData['fields'][0], true);
        $update = $db->prepare(
            "UPDATE system_codes SET extra_data=:extra_data,updated_at=NOW(),updated_by='SYSTEM:MIGRATION' WHERE id=:id"
        );
        $update->execute([
            ':extra_data' => json_encode($templateData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => (string) $templateRow['id'],
        ]);
    }

    $industrialRows = $db->query(
        "SELECT id,value_data FROM system_statutory_standards WHERE standard_type_code='INDUSTRIAL_ACCIDENT'"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $update = $db->prepare(
        "UPDATE system_statutory_standards SET value_data=:value_data,updated_at=NOW(),updated_by='SYSTEM:MIGRATION' WHERE id=:id"
    );
    foreach ($industrialRows as $industrialRow) {
        $valueData = json_decode((string) $industrialRow['value_data'], true);
        if (is_string($valueData['_schema']['fields'][0] ?? null)) {
            $valueData['_schema']['fields'][0] = json_decode($valueData['_schema']['fields'][0], true);
            $update->execute([
                ':value_data' => json_encode($valueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':id' => (string) $industrialRow['id'],
            ]);
        }
    }
}

$templateRows = $db->query(
    "SELECT code,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE'"
    . " AND is_active=1 ORDER BY sort_no"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($templateRows as $row) {
    $extra = json_decode((string) $row['extra_data'], true);
    if (!is_array($extra) || array_key_exists('scope_fields', $extra)) {
        throw new RuntimeException((string) $row['code'] . ' Template에 scope_fields가 남아 있습니다.');
    }
    if (($row['code'] ?? '') === 'INDUSTRIAL_ACCIDENT' && !is_array($extra['fields'][0] ?? null)) {
        throw new RuntimeException('산재보험 Template 구조화 필드가 객체가 아닙니다.');
    }
}
if ($hasScopeData()) {
    throw new RuntimeException('system_statutory_standards.scope_data 컬럼이 남아 있습니다.');
}

$industrial = $db->query(
    "SELECT id,value_data,(SELECT COUNT(*) FROM system_statutory_standard_sources src WHERE src.standard_id=s.id) source_count"
    . " FROM system_statutory_standards s WHERE standard_type_code='INDUSTRIAL_ACCIDENT' ORDER BY effective_from"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($industrial as &$row) {
    $row['value_data'] = json_decode((string) $row['value_data'], true);
    $rates = $row['value_data']['industry_rates'] ?? null;
    if (!is_array($rates) || $rates === [] || !is_array($row['value_data']['_schema']['fields'][0] ?? null)) {
        throw new RuntimeException('산재보험 사업종류별 보험료율 또는 역사 스키마 변환이 올바르지 않습니다.');
    }
}
unset($row);

echo json_encode([
    'scope_data_absent' => true,
    'templates_without_scope_fields' => count($templateRows),
    'industrial_standards' => $industrial,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
