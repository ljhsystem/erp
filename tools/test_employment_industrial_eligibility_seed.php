<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\InsuranceEligibilityPolicyValidator;
use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

$db = DbPdo::conn();
$sourceSchema = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$schema = 'tmp_insurance_eligibility_seed_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
if (!preg_match('/^tmp_insurance_eligibility_seed_[0-9]{14}_[a-f0-9]{6}$/', $schema)) {
    throw new RuntimeException('격리 Schema 이름이 올바르지 않습니다.');
}

$executeSql = static function (PDO $connection, string $path): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
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
$hash = static fn(PDO $connection, string $sql): string => hash('sha256', json_encode(
    $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
));

$created = false;
try {
    $db->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $created = true;
    $db->exec("CREATE TABLE `{$schema}`.`_codex_execution_marker`(id TINYINT PRIMARY KEY,owner_code VARCHAR(100) NOT NULL)");
    $db->exec("INSERT INTO `{$schema}`.`_codex_execution_marker` VALUES(1,'EMPLOYMENT_INDUSTRIAL_ELIGIBILITY_SEED_20260831')");
    foreach (['system_statutory_standards', 'system_statutory_standard_sources', 'institution_daily_employment_income_calculation_results'] as $table) {
        $db->exec("CREATE TABLE `{$schema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        $db->exec("INSERT INTO `{$schema}`.`{$table}` SELECT * FROM `{$sourceSchema}`.`{$table}`");
    }
    $db->exec("USE `{$schema}`");
    $legacyRevisionSql = "SELECT * FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY' AND standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE') ORDER BY id";
    $legacySourceSql = "SELECT source_row.* FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.policy_component_code='ELIGIBILITY' AND standard_row.standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE') ORDER BY source_row.id";
    $resultSql = 'SELECT * FROM institution_daily_employment_income_calculation_results ORDER BY id';
    $before = ['revision' => $hash($db, $legacyRevisionSql), 'source' => $hash($db, $legacySourceSql), 'result' => $hash($db, $resultSql)];

    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_07_seed_employment_industrial_eligibility.up.sql');
    $after = ['revision' => $hash($db, $legacyRevisionSql), 'source' => $hash($db, $legacySourceSql), 'result' => $hash($db, $resultSql)];
    if ($before !== $after) throw new RuntimeException('기존 Revision·Source·Calculation Result가 변경됐습니다.');

    $validator = new InsuranceEligibilityPolicyValidator();
    $seedRows = $db->query("SELECT * FROM system_statutory_standards WHERE id LIKE '68310700-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($seedRows as $row) {
        $policy = json_decode((string) $row['value_data'], true, 512, JSON_THROW_ON_ERROR);
        $validator->validate($policy);
    }
    $sourceCount = (int) $db->query("SELECT COUNT(*) FROM system_statutory_standard_sources WHERE id LIKE '68311700-%'")->fetchColumn();
    $missingKoreanReasonCount = 0;
    foreach ($seedRows as $row) {
        $policy = json_decode((string) $row['value_data'], true, 512, JSON_THROW_ON_ERROR);
        foreach ((array) ($policy['reason_codes'] ?? []) as $reason) {
            if (trim((string) ($reason['name'] ?? '')) === '') $missingKoreanReasonCount++;
        }
    }
    $resolver = new StatutoryStandardResolver($db);
    $boundarySelections = [];
    foreach (['2019-01-14', '2019-01-15', '2023-06-30', '2023-07-01'] as $date) {
        $boundarySelections['employment|' . $date] = $resolver->resolveComponent('EMPLOYMENT_INSURANCE', 'ELIGIBILITY', 'DAILY', 'HEAD_OFFICE', $date)['id'];
    }
    foreach (['2017-12-31', '2018-01-01'] as $date) {
        $boundarySelections['industrial|' . $date] = $resolver->resolveComponent('INDUSTRIAL_ACCIDENT', 'ELIGIBILITY', 'DAILY', 'CONSTRUCTION_SITE', $date)['id'];
    }
    if (count($seedRows) !== 15 || $sourceCount !== 15 || $missingKoreanReasonCount !== 0) {
        throw new RuntimeException('신규 Revision·Source 또는 한글 판정사유 검증값이 다릅니다.');
    }

    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_07_seed_employment_industrial_eligibility.down.sql');
    $down = ['revision' => $hash($db, $legacyRevisionSql), 'source' => $hash($db, $legacySourceSql), 'result' => $hash($db, $resultSql)];
    if ($before !== $down || (int) $db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE id LIKE '68310700-%'")->fetchColumn() !== 0) {
        throw new RuntimeException('Down 후 기준선이 복원되지 않았습니다.');
    }
    echo json_encode([
        'success' => true,
        'schema' => $schema,
        'revision_count' => count($seedRows),
        'source_count' => $sourceCount,
        'missing_korean_reason_count' => $missingKoreanReasonCount,
        'boundary_selections' => $boundarySelections,
        'before' => $before,
        'after_up' => $after,
        'down_restored' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    $db->exec("USE `{$sourceSchema}`");
    if ($created) {
        $marker = (int) $db->query("SELECT COUNT(*) FROM `{$schema}`.`_codex_execution_marker` WHERE owner_code='EMPLOYMENT_INDUSTRIAL_ELIGIBILITY_SEED_20260831'")->fetchColumn();
        if ($marker !== 1) throw new RuntimeException('격리 Schema 실행 소유권 Marker가 없습니다.');
        $db->exec("DROP DATABASE `{$schema}`");
    }
}
