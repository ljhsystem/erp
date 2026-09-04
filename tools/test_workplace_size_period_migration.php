<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Models\Institution\WorkplaceSizePeriodModel;
use Core\DbPdo;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$operatingTables = ['institution_regular_employment_income_line_items','institution_social_insurance_coverages'];
$counts = static function () use ($pdo, $source, $operatingTables): array {
    $result = [];
    foreach ($operatingTables as $table) $result[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$source}`.`{$table}`")->fetchColumn();
    return $result;
};
$before = $counts();
$test = 'codex_workplace_size_' . bin2hex(random_bytes(5));
if (!preg_match('/^codex_workplace_size_[0-9a-f]{10}$/', $test)) throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');
$execute = static function (string $file) use ($pdo): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n"; $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다: ' . $file);
};

$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    foreach (['system_company','system_codes','system_statutory_standards','institution_social_insurance_coverages','institution_regular_employment_income_line_items'] as $table) {
        $pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
    $pdo->exec("INSERT INTO `{$test}`.`system_codes` SELECT * FROM `{$source}`.`system_codes` WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='EMPLOYMENT_INSURANCE'");
    $pdo->exec("INSERT INTO `{$test}`.`system_statutory_standards` SELECT * FROM `{$source}`.`system_statutory_standards` WHERE standard_type_code='EMPLOYMENT_INSURANCE'");
    $pdo->exec("USE `{$test}`");
    $execute('20260826_01_create_workplace_size_period_ssot.up.sql');
    $matrixBefore = (int) $pdo->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE' AND JSON_EXTRACT(value_data,'$.additional_employer_rates[0].business_size_code') IS NULL")->fetchColumn();
    $execute('20260826_02_migrate_employment_insurance_business_size_codes.up.sql');
    $matrixAfter = (int) $pdo->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE' AND JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.additional_employer_rates[0].business_size_code'))='LESS_THAN_150'")->fetchColumn();
    if ($matrixBefore < 1 || $matrixAfter !== $matrixBefore) throw new RuntimeException('Matrix 코드 이관 전후 건수 검증 실패');
    $columnCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_line_items' AND COLUMN_NAME IN ('application_status_code','calculation_basis_amount','calculation_rate','calculation_before_rounding','rounding_method_code','rounding_unit','statutory_standard_id','social_insurance_coverage_id','workplace_size_period_id')")->fetchColumn();
    if ($columnCount !== 9) throw new RuntimeException('Line 계산결과 컬럼 검증 실패');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $insert = $pdo->prepare("INSERT INTO institution_workplace_size_periods(id,company_id,calculation_purpose_code,effective_from,effective_to,business_size_code,business_size_name_snapshot,regular_worker_count,calculation_basis_description,evidence_type_code,evidence_description,confirmation_status_code,confirmed_at,confirmed_by,revision_no,previous_period_id,correction_reason,request_key,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insert->execute(['period-1','company-1','EMPLOYMENT_INSURANCE_VOCATIONAL','2013-08-01','2013-08-31','LESS_THAN_150','150명 미만',2,'사용자 확인 역사자료','HISTORICAL_IMPORT','2013년 8월 사실 확인','CONFIRMED','2026-08-26 00:00:00','SYSTEM:TEST',1,null,null,'request-1','SYSTEM:TEST']);
    $model = new WorkplaceSizePeriodModel($pdo);
    if (!$model->hasLeafOverlap('company-1','EMPLOYMENT_INSURANCE_VOCATIONAL','2013-08-15','2013-08-31')) throw new RuntimeException('같은 계산목적 기간중복 차단 계약 실패');
    if ($model->hasLeafOverlap('company-1','OTHER_LEGAL_PURPOSE','2013-08-15','2013-08-31')) throw new RuntimeException('다른 계산목적 동일기간 허용 계약 실패');
    $insert->execute(['period-2','company-1','EMPLOYMENT_INSURANCE_VOCATIONAL','2013-08-01','2013-08-31','LESS_THAN_150','변경 표시명',2,'사용자 확인 역사자료','HISTORICAL_IMPORT','표시명 정정','CONFIRMED','2026-08-26 00:01:00','SYSTEM:TEST',2,'period-1','표시명 정정','request-2','SYSTEM:TEST']);
    $resolved = $model->resolve('company-1','EMPLOYMENT_INSURANCE_VOCATIONAL','2013-08-15');
    if (($resolved['id'] ?? '') !== 'period-2') throw new RuntimeException('불변 정정 Revision leaf Resolve 실패');
    $downBlocked = false;
    try { $execute('20260826_01_create_workplace_size_period_ssot.down.sql'); } catch (PDOException $exception) { $downBlocked = $exception->getCode() === '45000'; }
    if (!$downBlocked) throw new RuntimeException('데이터 존재 시 Down 차단 실패');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $after = $counts();
    echo json_encode(['success'=>true,'line_result_columns'=>$columnCount,'matrix_before'=>$matrixBefore,'matrix_after'=>$matrixAfter,'same_purpose_overlap_blocked'=>true,'different_purpose_same_period_allowed'=>true,'correction_leaf_resolved'=>true,'data_down_blocked'=>true,'operating_before'=>$before,'operating_after'=>$after,'operating_database_changed'=>$before!==$after], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$source}`");
    $pdo->exec("DROP DATABASE IF EXISTS `{$test}`");
}
