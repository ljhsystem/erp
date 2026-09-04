<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\InsuranceEligibilityPolicyValidator;
use Core\DbPdo;

$db = DbPdo::conn();
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'sukhyang'
    || !str_starts_with((string) $db->query('SELECT VERSION()')->fetchColumn(), '10.11.11-MariaDB')) {
    throw new RuntimeException('운영 DB 또는 MariaDB 기준선이 다릅니다.');
}
$executeSql = static function (PDO $connection, string $path): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n"; $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$hash = static fn(string $sql): string => hash('sha256', json_encode(
    $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
));
$legacyRevisionSql = "SELECT * FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY' AND standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE') ORDER BY id";
$legacySourceSql = "SELECT source_row.* FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.policy_component_code='ELIGIBILITY' AND standard_row.standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE') ORDER BY source_row.id";
$resultSql = 'SELECT * FROM institution_daily_employment_income_calculation_results ORDER BY id';
$before = ['revision' => $hash($legacyRevisionSql), 'source' => $hash($legacySourceSql), 'result' => $hash($resultSql)];
if ((int) $db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE id LIKE '68310700-%'")->fetchColumn() !== 0) {
    throw new RuntimeException('신규 가입자격 Seed가 이미 존재합니다.');
}
$executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_07_seed_employment_industrial_eligibility.up.sql');
$after = ['revision' => $hash($legacyRevisionSql), 'source' => $hash($legacySourceSql), 'result' => $hash($resultSql)];
if ($before !== $after) throw new RuntimeException('기존 Revision·Source·Calculation Result가 변경됐습니다.');
$rows = $db->query("SELECT id,value_data FROM system_statutory_standards WHERE id LIKE '68310700-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$validator = new InsuranceEligibilityPolicyValidator();
foreach ($rows as $row) $validator->validate(json_decode((string) $row['value_data'], true, 512, JSON_THROW_ON_ERROR));
$sourceCount = (int) $db->query("SELECT COUNT(*) FROM system_statutory_standard_sources WHERE id LIKE '68311700-%'")->fetchColumn();
if (count($rows) !== 15 || $sourceCount !== 15) throw new RuntimeException('운영 Seed 사후 건수가 다릅니다.');
echo json_encode([
    'success' => true,
    'database' => 'sukhyang',
    'migration' => '20260831_07_seed_employment_industrial_eligibility.up.sql',
    'revision_count' => count($rows),
    'source_count' => $sourceCount,
    'before' => $before,
    'after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
