<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== 'sukhyang') {
    throw new RuntimeException('운영 데이터베이스가 아닙니다.');
}
if (!str_starts_with((string)$db->query('SELECT VERSION()')->fetchColumn(), '10.11.11-MariaDB')) {
    throw new RuntimeException('승인된 MariaDB 버전이 아닙니다.');
}

$hash = static function (PDO $connection, string $sql): string {
    $rows = $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
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
        if (!str_ends_with($trimmed, $delimiter)) {
            continue;
        }
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $connection->exec($statement);
        }
        $buffer = '';
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
    }
};

$groups = "'STATUTORY_POLICY_COMPONENT','STATUTORY_EMPLOYMENT_TYPE','STATUTORY_WORK_SCOPE','STATUTORY_CONDITION_COMBINATION','INSURANCE_ELIGIBILITY_DECISION','INSURANCE_ELIGIBILITY_RESULT','INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE','INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY','INSURANCE_ELIGIBILITY_MONTH_JUDGMENT','INSURANCE_ELIGIBILITY_INCOME_BASIS','INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE','INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD','INSURANCE_ELIGIBILITY_TRANSITION_POLICY','INSURANCE_ELIGIBILITY_TRANSITION_STATUS','STATUTORY_STANDARD_PERIOD_STATUS'";
$templateSql = "SELECT id,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') ORDER BY id";
$invariants = [
    'standards' => 'SELECT * FROM system_statutory_standards ORDER BY id',
    'sources' => 'SELECT * FROM system_statutory_standard_sources ORDER BY id',
    'calculation_revisions' => 'SELECT * FROM institution_daily_employment_income_calculation_revisions ORDER BY id',
    'calculation_results' => 'SELECT * FROM institution_daily_employment_income_calculation_results ORDER BY id',
    'daily_headers' => 'SELECT * FROM institution_daily_employment_incomes ORDER BY id',
    'daily_groups' => 'SELECT * FROM institution_daily_employment_income_groups ORDER BY id',
    'daily_items' => 'SELECT * FROM institution_daily_employment_income_items ORDER BY id',
    'daily_workdays' => 'SELECT * FROM institution_daily_employment_income_workdays ORDER BY id',
    'daily_lines' => 'SELECT * FROM institution_daily_employment_income_lines ORDER BY id',
];
$capture = static function () use ($db, $hash, $invariants): array {
    $result = [];
    foreach ($invariants as $key => $sql) {
        $result[$key] = $hash($db, $sql);
    }
    return $result;
};

$existingCodeCount = (int)$db->query("SELECT COUNT(*) FROM system_codes WHERE code_group IN({$groups})")->fetchColumn();
$templateBeforeHash = $hash($db, $templateSql);
if ($existingCodeCount !== 0 || $templateBeforeHash !== 'bd10fd26b6517844508adc7828f22ffb2a3affa5c6f9f04dba6d332041cc3e22') {
    throw new RuntimeException('운영 선택코드 SSOT 기준선이 승인값과 다릅니다.');
}
$before = $capture();

$executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_06_unify_statutory_standard_select_codes.up.sql');

$after = $capture();
$groupCount = (int)$db->query("SELECT COUNT(DISTINCT code_group) FROM system_codes WHERE id LIKE '20260831-06%'")->fetchColumn();
$codeCount = (int)$db->query("SELECT COUNT(*) FROM system_codes WHERE id LIKE '20260831-06%'")->fetchColumn();
$duplicateCount = (int)$db->query('SELECT COUNT(*) FROM (SELECT code_group,code FROM system_codes GROUP BY code_group,code HAVING COUNT(*)>1) duplicated')->fetchColumn();
if ($before !== $after || $groupCount !== 15 || $codeCount !== 34 || $duplicateCount !== 0) {
    throw new RuntimeException('운영 선택코드 SSOT 적용 후 검증에 실패했습니다.');
}

echo json_encode([
    'success' => true,
    'migration' => '20260831_06_unify_statutory_standard_select_codes.up.sql',
    'template_before_hash' => $templateBeforeHash,
    'template_after_hash' => $hash($db, $templateSql),
    'business_hashes_before' => $before,
    'business_hashes_after' => $after,
    'group_count' => $groupCount,
    'code_count' => $codeCount,
    'duplicate_count' => $duplicateCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
