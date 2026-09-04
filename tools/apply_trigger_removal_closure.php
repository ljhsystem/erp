<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = strtolower((string)($argv[1] ?? 'preflight'));
if (!in_array($mode, ['preflight', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_trigger_removal_closure.php [preflight|up|verify]');
}
$db = DbPdo::conn();
$names = [
    'trg_client_tax_profile_no_overlap_insert', 'trg_client_tax_profile_no_overlap_update',
    'trg_business_income_evidence_canonical_insert', 'trg_statutory_supersession_bi',
    'trg_statutory_supersession_bu', 'trg_statutory_supersession_bd', 'trg_statutory_standard_bu',
    'trg_statutory_standard_bd', 'trg_statutory_standard_source_bu', 'trg_statutory_standard_source_bd',
];
$quoted = implode(',', array_map([$db, 'quote'], $names));
$triggerRows = static fn(): array => $db->query(
    "SELECT TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT,DEFINER,CREATED"
    . " FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$hash = static function (string $table, array $columns) use ($db): string {
    $parts = implode(',', array_map(static fn(string $column): string => "COALESCE(CAST(`{$column}` AS CHAR),'∅')", $columns));
    $rows = $db->query("SELECT SHA2(CONCAT_WS('|',{$parts}),256) row_hash FROM `{$table}` ORDER BY id")
        ->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return hash('sha256', implode('|', $rows));
};
$snapshot = static function () use ($db, $triggerRows, $hash): array {
    $businessTables = [
        'system_client_tax_profiles', 'institution_business_incomes', 'institution_business_income_groups',
        'institution_business_income_items', 'institution_business_income_calculation_revisions',
        'institution_business_income_calculation_lines', 'institution_business_income_commands',
        'institution_business_income_closures', 'institution_business_income_artifact_links',
        'ledger_evidence_business_income', 'ledger_evidence_business_income_raw_lines',
    ];
    $counts = [];
    foreach ($businessTables as $table) $counts[$table] = (int)$db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    return [
        'triggers' => $triggerRows(), 'table_counts' => $counts,
        'system_code_count' => (int)$db->query("SELECT COUNT(*) FROM system_codes WHERE code_group IN ('TAXPAYER_ENTITY_TYPE','RESIDENCY_STATUS','INCOME_RECIPIENT_TYPE','WITHHOLDING_POLICY','CLIENT_TAX_PROFILE_VERIFICATION','STATUTORY_STANDARD_TYPE')")->fetchColumn(),
        'statutory_fk_count' => (int)$db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'system_statutory_standard%' OR REFERENCED_TABLE_NAME LIKE 'system_statutory_standard%') AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn(),
        'revision_hash' => $hash('system_statutory_standards', ['id','standard_type_code','effective_from','effective_to','value_data']),
        'source_hash' => $hash('system_statutory_standard_sources', ['id','standard_id','source_name','source_url','file_path']),
        'supersession_hash' => $hash('system_statutory_standard_supersessions', ['id','predecessor_revision_id','successor_revision_id','correction_reason']),
    ];
};
$before = $snapshot();
if ($mode === 'preflight' && (count($before['triggers']) !== 10 || count(array_intersect($names, array_column($before['triggers'], 'TRIGGER_NAME'))) !== 10)) {
    throw new RuntimeException('운영 Trigger 사전조건이 일치하지 않습니다.');
}
if ($mode === 'up') {
    if (count($before['triggers']) !== 10 || count(array_intersect($names, array_column($before['triggers'], 'TRIGGER_NAME'))) !== 10) {
        throw new RuntimeException('제거 전 Trigger 10개 일치 검증에 실패했습니다.');
    }
    foreach (file(PROJECT_ROOT . '/app/migrations/20260903_13_remove_statutory_and_business_income_triggers.up.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $sql) {
        $db->exec($sql);
    }
}
$after = $snapshot();
if ($mode !== 'preflight') {
    if (count($after['triggers']) !== 0) throw new RuntimeException('운영 Trigger 최종 수가 0이 아닙니다.');
    foreach (['table_counts','system_code_count','statutory_fk_count','revision_hash','source_hash','supersession_hash'] as $key) {
        if ($before[$key] !== $after[$key]) throw new RuntimeException("Trigger 제거 중 {$key} 값이 변경됐습니다.");
    }
}
echo json_encode(['success'=>true,'mode'=>$mode,'before'=>$before,'after'=>$after], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
