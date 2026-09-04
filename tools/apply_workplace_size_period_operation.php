<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = Core\DbPdo::conn();
$execute = static function (string $file) use ($pdo): void {
    $delimiter=';'; $buffer='';
    foreach (preg_split('/\r\n|\n|\r/', (string)file_get_contents(PROJECT_ROOT.'/app/migrations/'.$file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter=$match[1]; continue; }
        $buffer.=$line."\n"; $trimmed=rtrim($buffer,"\r\n");
        if (!str_ends_with($trimmed,$delimiter)) continue;
        $statement=trim(substr($trimmed,0,-strlen($delimiter)));
        if ($statement!=='') $pdo->exec($statement);
        $buffer='';
    }
    if (trim($buffer)!=='') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다: '.$file);
};
$count = static fn(string $table): int => (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$before=[
    'income_headers'=>$count('institution_regular_employment_incomes'),
    'income_items'=>$count('institution_regular_employment_income_items'),
    'income_lines'=>$count('institution_regular_employment_income_line_items'),
    'coverages'=>$count('institution_social_insurance_coverages'),
    'employment_standards'=>(int)$pdo->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE'")->fetchColumn(),
];
$tableExists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_workplace_size_periods'")->fetchColumn();
$lineColumns=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_line_items' AND COLUMN_NAME IN ('application_status_code','calculation_basis_amount','calculation_rate','calculation_before_rounding','rounding_method_code','rounding_unit','statutory_standard_id','social_insurance_coverage_id','workplace_size_period_id')")->fetchColumn();
if ($tableExists===0 && $lineColumns===0) $execute('20260826_01_create_workplace_size_period_ssot.up.sql');
elseif ($tableExists!==1 || $lineColumns!==9) throw new RuntimeException('회사규모 Migration이 부분 적용된 상태입니다.');
$missingCodes=(int)$pdo->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE' AND JSON_EXTRACT(value_data,'$.additional_employer_rates[0].business_size_code') IS NULL")->fetchColumn();
if ($missingCodes>0) $execute('20260826_02_migrate_employment_insurance_business_size_codes.up.sql');
$after=[
    'income_headers'=>$count('institution_regular_employment_incomes'),
    'income_items'=>$count('institution_regular_employment_income_items'),
    'income_lines'=>$count('institution_regular_employment_income_line_items'),
    'coverages'=>$count('institution_social_insurance_coverages'),
    'employment_standards'=>(int)$pdo->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE'")->fetchColumn(),
];
$matrixCoded=(int)$pdo->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE' AND JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.additional_employer_rates[0].business_size_code'))='LESS_THAN_150'")->fetchColumn();
$structure=[
    'table'=>(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_workplace_size_periods'")->fetchColumn(),
    'line_columns'=>(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_line_items' AND COLUMN_NAME IN ('application_status_code','calculation_basis_amount','calculation_rate','calculation_before_rounding','rounding_method_code','rounding_unit','statutory_standard_id','social_insurance_coverage_id','workplace_size_period_id')")->fetchColumn(),
    'foreign_keys'=>(int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (CONSTRAINT_NAME LIKE 'fk_workplace_size_%' OR CONSTRAINT_NAME LIKE 'fk_regular_income_line_%')")->fetchColumn(),
];
if ($before!==$after || $matrixCoded!==$after['employment_standards'] || $structure['table']!==1 || $structure['line_columns']!==9) throw new RuntimeException('운영 Migration 사후검증에 실패했습니다.');
echo json_encode(['success'=>true,'before'=>$before,'after'=>$after,'matrix_coded'=>$matrixCoded,'structure'=>$structure],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
