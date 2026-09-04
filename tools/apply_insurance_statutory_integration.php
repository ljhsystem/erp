<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$version = (string)$db->query('SELECT VERSION()')->fetchColumn();
if ($database !== 'sukhyang' || !str_starts_with($version, '10.11.11-MariaDB')) {
    throw new RuntimeException('운영 DB 또는 MariaDB 버전 기준선이 다릅니다.');
}

$executeSql = static function (PDO $connection, string $path): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string)file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$hash = static function (PDO $connection, string $sql): string {
    $rows = $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};

$legacyCount = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY'")->fetchColumn();
$legacySourceCount = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY'")->fetchColumn();
$legacyReferenceCount = (int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results WHERE eligibility_revision_id LIKE '20260829-03%'")->fetchColumn();
$dimensionCount = (int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards' AND COLUMN_NAME IN('policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key')")->fetchColumn();
if ($legacyCount !== 22 || $legacySourceCount !== 22 || $legacyReferenceCount !== 3 || $dimensionCount !== 0) {
    throw new RuntimeException('운영 통합 Preflight 기준선이 다릅니다.');
}

$premiumSql = "SELECT id,standard_type_code,effective_from,effective_to,value_data FROM system_statutory_standards WHERE standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') AND id NOT LIKE '20260831-10%' ORDER BY id";
$otherResultSql = "SELECT * FROM institution_daily_employment_income_calculation_results WHERE id NOT IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') ORDER BY id";
$targetInvariantSql = "SELECT id,calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,status_code,eligibility_status_code,eligibility_reason_code,calculation_basis_amount,automatic_employee_amount,automatic_employer_amount,confirmed_employee_amount,confirmed_employer_amount,missing_inputs,exception_reason FROM institution_daily_employment_income_calculation_results WHERE id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') ORDER BY id";
$before = [
    'premium_hash'=>$hash($db, $premiumSql),
    'other_result_hash'=>$hash($db, $otherResultSql),
    'target_invariant_hash'=>$hash($db, $targetInvariantSql),
];

$files = [
    '20260831_01_integrate_insurance_eligibility_into_insurance_types.up.sql',
    '20260831_02_backfill_daily_income_integrated_eligibility_references.up.sql',
    '20260831_03_remove_insurance_eligibility_standard_type.up.sql',
    '20260831_04_add_insurance_component_input_templates.up.sql',
];
$applied = [];
foreach ($files as $file) {
    $executeSql($db, PROJECT_ROOT . '/app/migrations/' . $file);
    $applied[] = $file;
}

$after = [
    'premium_hash'=>$hash($db, $premiumSql),
    'other_result_hash'=>$hash($db, $otherResultSql),
    'target_invariant_hash'=>$hash($db, $targetInvariantSql),
];
$integratedRevisions = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'")->fetchColumn();
$integratedSources = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.policy_component_code='ELIGIBILITY'")->fetchColumn();
$integratedReferences = (int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results WHERE eligibility_revision_id LIKE '20260831-10%'")->fetchColumn();
$legacyRemaining = (int)$db->query("SELECT (SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY')+(SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY')")->fetchColumn();
if ($before !== $after || $integratedRevisions !== 22 || $integratedSources !== 22 || $integratedReferences !== 3 || $legacyRemaining !== 0) {
    throw new RuntimeException('운영 통합 사후검증이 실패했습니다.');
}

echo json_encode([
    'success'=>true,
    'database'=>$database,
    'version'=>$version,
    'applied'=>$applied,
    'before'=>$before,
    'after'=>$after,
    'integrated_revision_count'=>$integratedRevisions,
    'integrated_source_count'=>$integratedSources,
    'integrated_result_reference_count'=>$integratedReferences,
    'legacy_remaining_count'=>$legacyRemaining,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
