<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$tables = [
    'institution_employment_contracts',
    'institution_employment_contracts_components',
    'institution_employment_contracts_pay_components',
    'institution_employment_contracts_weekly_schedules',
    'institution_employment_contracts_work_schedule_policies',
];
$result = ['identity' => $pdo->query(
    'SELECT DATABASE() AS db_name, @@hostname AS hostname, @@port AS port, VERSION() AS version'
)->fetch(PDO::FETCH_ASSOC)];
$result['codes'] = $pdo->query(
    "SELECT code_group, code, code_name, sort_no, is_active
     FROM system_codes
     WHERE code_group LIKE '%EMPLOY%' OR code_group IN
       ('WORK_LOCATION_TYPE','WORK_SCHEDULE_TYPE','SALARY_TYPE','PAYMENT_TIMING',
        'PUBLIC_HOLIDAY_POLICY','LABOR_DAY_POLICY','ANNUAL_LEAVE_POLICY')
     ORDER BY code_group, sort_no"
)->fetchAll(PDO::FETCH_ASSOC);
if (($argv[1] ?? '') === '--codes') {
    echo json_encode(['identity' => $result['identity'], 'codes' => $result['codes']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === '--pay-components') {
    $rows = $pdo->query(
        'SELECT component_code, component_name, component_type, default_calculation_type,
                default_tax_type, tax_policy_code, ordinary_wage_treatment,
                average_wage_treatment, minimum_wage_treatment, is_active,
                effective_from, effective_to, deleted_at
         FROM institution_employment_contracts_pay_components ORDER BY sort_no'
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
if (($argv[1] ?? '') === '--integrity') {
    $queries = [
        'contracts' => 'SELECT COUNT(*) FROM institution_employment_contracts',
        'component_orphans' => 'SELECT COUNT(*) FROM institution_employment_contracts_components x LEFT JOIN institution_employment_contracts c ON c.id=x.contract_id LEFT JOIN institution_employment_contracts_pay_components p ON p.id=x.pay_component_id WHERE c.id IS NULL OR p.id IS NULL',
        'weekly_orphans' => 'SELECT COUNT(*) FROM institution_employment_contracts_weekly_schedules x LEFT JOIN institution_employment_contracts c ON c.id=x.contract_id WHERE c.id IS NULL',
        'policy_orphans' => 'SELECT COUNT(*) FROM institution_employment_contracts_work_schedule_policies x LEFT JOIN institution_employment_contracts c ON c.id=x.contract_id WHERE c.id IS NULL',
        'policy_type_mismatch' => 'SELECT COUNT(*) FROM institution_employment_contracts_work_schedule_policies x JOIN institution_employment_contracts c ON c.id=x.contract_id WHERE c.work_schedule_type IN (\'NORMAL\',\'NIGHT\')',
        'weekly_type_mismatch' => 'SELECT COUNT(*) FROM institution_employment_contracts_weekly_schedules x JOIN institution_employment_contracts c ON c.id=x.contract_id WHERE c.work_schedule_type IN (\'SELECTIVE\',\'SHIFT\')',
    ];
    foreach ($queries as $key => $sql) $integrity[$key] = (int) $pdo->query($sql)->fetchColumn();
    echo json_encode($integrity, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
foreach ($tables as $table) {
    $columns = $pdo->prepare(
        'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, COLUMN_COMMENT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
         ORDER BY ORDINAL_POSITION'
    );
    $columns->execute([':table' => $table]);
    $indexes = $pdo->prepare(
        'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
         ORDER BY INDEX_NAME, SEQ_IN_INDEX'
    );
    $indexes->execute([':table' => $table]);
    $constraints = $pdo->prepare(
        'SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
         FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
         ORDER BY CONSTRAINT_NAME'
    );
    $constraints->execute([':table' => $table]);
    $keys = $pdo->prepare(
        'SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
         ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION'
    );
    $keys->execute([':table' => $table]);
    $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
    $result[$table] = [
        'create' => $create[1] ?? '',
        'columns' => $columns->fetchAll(PDO::FETCH_ASSOC),
        'indexes' => $indexes->fetchAll(PDO::FETCH_ASSOC),
        'constraints' => $constraints->fetchAll(PDO::FETCH_ASSOC),
        'keys' => $keys->fetchAll(PDO::FETCH_ASSOC),
    ];
}
if (($argv[1] ?? '') === '--counts') {
    foreach ($tables as $table) {
        $emptyComments = array_filter($result[$table]['columns'], static fn(array $column): bool => trim((string) $column['COLUMN_COMMENT']) === '');
        echo $table . ': ' . count($result[$table]['columns']) . ', empty_comments=' . count($emptyComments) . PHP_EOL;
    }
    exit;
}
if (($argv[1] ?? '') === '--matrix') {
    $direct = [
        'institution_employment_contracts' => ['employee_id','contract_type','contract_period_type','employment_category','working_time_type','contract_start_date','contract_end_date','fixed_term_reason_code','fixed_term_reason_detail','work_location_type','project_id','work_location_detail','job_description','work_schedule_type','salary_type','payment_day','payment_timing','probation_start_date','probation_end_date','probation_rate','note','termination_reason'],
        'institution_employment_contracts_components' => ['pay_component_id','amount','rate','quantity','work_type','premium_rate','excess_payment_policy','agreement_basis','wage_treatment_basis','note'],
        'institution_employment_contracts_weekly_schedules' => ['day_of_week','day_type','start_time','end_time','end_day_offset','break_minutes'],
        'institution_employment_contracts_work_schedule_policies' => ['settlement_period_days','reference_weekly_hours','selectable_start_time','selectable_end_time','core_start_time','core_end_time','policy_detail'],
    ];
    $readonly = ['contract_no','previous_contract_id','revision_no','revision_reason','job_title_snapshot','contract_status','approved_at','terminated_at'];
    $automatic = ['sort_no','employee_name_snapshot','employee_address_snapshot','employee_identifier_snapshot','employer_name_snapshot','employer_registration_no_snapshot','employer_address_snapshot','employer_representative_snapshot','current_approval_request_id'];
    $systemPattern = '/^(id|contract_id|created_at|created_by|updated_at|updated_by|deleted_at|deleted_by)$/';
    echo '| 테이블 | 컬럼 | COMMENT | 업무 의미 | 현재 모달 표현 | 구분 | 필수 | 저장 경로 | 조회 복원 | 문제 | 조치 |' . PHP_EOL;
    echo '|---|---|---|---|---|---|---|---|---|---|---|' . PHP_EOL;
    foreach ($tables as $table) foreach ($result[$table]['columns'] as $column) {
        $name = $column['COLUMN_NAME'];
        $comment = str_replace('|', '/', (string) $column['COLUMN_COMMENT']);
        if (in_array($name, $direct[$table] ?? [], true)) [$expression,$kind,$problem,$action] = ['입력 또는 조건부 입력','직접입력','-','유지'];
        elseif ($table === 'institution_employment_contracts_pay_components') [$expression,$kind,$problem,$action] = ['급여항목 선택의 마스터','마스터 전용','-','활성기간 기준 자동 스냅샷'];
        elseif (in_array($name, $readonly, true)) [$expression,$kind,$problem,$action] = ['시스템 처리정보','읽기전용','-','한글 표시'];
        elseif (in_array($name, $automatic, true)) [$expression,$kind,$problem,$action] = ['상세 SSOT에서 파생','자동계산/생성','헤더 직접입력 금지','Service 정규화'];
        elseif (preg_match($systemPattern, $name)) [$expression,$kind,$problem,$action] = ['화면 비노출','시스템 내부','-','ActorHelper/Model'];
        else [$expression,$kind,$problem,$action] = ['급여 마스터 스냅샷 또는 내부 감사값','자동/비노출','직접 편집 금지','Service 정규화'];
        $required = $column['IS_NULLABLE'] === 'NO' ? 'DB 필수' : '조건부';
        echo "| `$table` | `$name` | $comment | $comment | $expression | $kind | $required | Controller→Service→Model | 상세 API 복원 | $problem | $action |" . PHP_EOL;
    }
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
