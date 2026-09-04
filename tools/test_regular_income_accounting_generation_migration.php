<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$operatingTables=['institution_regular_employment_income_accounting_links','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_salary_report','ledger_payment_schedules'];
$operatingCounts=static function()use($pdo,$source,$operatingTables):array{$counts=[];foreach($operatingTables as$table)$counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$source}`.`{$table}`")->fetchColumn();return$counts;};
$operatingBefore=$operatingCounts();
$test = 'codex_regular_income_generation_' . bin2hex(random_bytes(5));
$created = false;
if (!preg_match('/^codex_regular_income_generation_[0-9a-f]{10}$/', $test)) throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');

$execute = static function (string $file) use ($pdo): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('SQL 구분자가 닫히지 않았습니다: ' . $file);
};

$tables = [
    'system_statutory_standards','user_approval_requests','institution_regular_employment_incomes',
    'institution_regular_employment_income_items','institution_regular_employment_income_line_items',
    'institution_regular_employment_income_calculation_bases','ledger_evidence_salary_report',
    'ledger_transactions','ledger_transaction_items','ledger_transaction_settlements',
    'ledger_payment_schedules','institution_regular_employment_income_accounting_links',
];

$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"); $created = true;
try {
    foreach ($tables as $table) $pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");
    $pdo->exec("USE `{$test}`");
    $up = '20260825_04_extend_regular_income_accounting_generation_identity.up.sql';
    $down = '20260825_04_extend_regular_income_accounting_generation_identity.down.sql';
    $execute($up);
    $checks = [
        'item_source_columns' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_items' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')")->fetchColumn(),
        'settlement_source_columns' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_settlements' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')")->fetchColumn(),
        'registry_columns' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash')")->fetchColumn(),
        'schedule_registry' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn(),
        'new_foreign_keys' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (CONSTRAINT_NAME LIKE 'fk_transaction_item_%' OR CONSTRAINT_NAME LIKE 'fk_transaction_settlement_%' OR CONSTRAINT_NAME LIKE 'fk_regular_income_accounting_%')")->fetchColumn(),
    ];
    if ($checks !== ['item_source_columns'=>3,'settlement_source_columns'=>3,'registry_columns'=>6,'schedule_registry'=>1,'new_foreign_keys'=>9]) throw new RuntimeException('Up 구조 검증 실패: ' . json_encode($checks));

    $duplicateBlocked = false;
    try { $execute($up); } catch (PDOException $exception) { $duplicateBlocked = $exception->getCode() === '45000'; }
    if (!$duplicateBlocked) throw new RuntimeException('중복 또는 부분 적용 차단에 실패했습니다.');

    $execute($down);
    $restoredUk = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='uk_regular_income_accounting_detail'")->fetchColumn();
    if ($restoredUk !== 1) throw new RuntimeException('기존 직원 Item UK가 복원되지 않았습니다.');
    $execute($up);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $service = new RegularEmploymentIncomeAccountingGenerationService($pdo);
    $employerFixture=[
        ['id'=>'item-1','employee_id'=>'employee-1','line_items'=>[
            ['id'=>'line-np-1','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'NATIONAL_PENSION','final_amount'=>44460],
            ['id'=>'line-hi-1','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'HEALTH_INSURANCE','final_amount'=>29120],
            ['id'=>'line-ltc-1','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'LONG_TERM_CARE','final_amount'=>1900],
            ['id'=>'line-ei-1','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'EMPLOYMENT_INSURANCE','final_amount'=>10000],
            ['id'=>'line-ia-1','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'INDUSTRIAL_ACCIDENT','final_amount'=>12000],
        ]],
        ['id'=>'item-2','employee_id'=>'employee-2','line_items'=>[
            ['id'=>'line-np-2','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'NATIONAL_PENSION','final_amount'=>44460],
            ['id'=>'line-hi-2','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'HEALTH_INSURANCE','final_amount'=>29120],
            ['id'=>'line-ltc-2','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'LONG_TERM_CARE','final_amount'=>1900],
            ['id'=>'line-ei-2','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'EMPLOYMENT_INSURANCE','final_amount'=>10000],
            ['id'=>'line-ia-2','item_type_code'=>'EMPLOYER_BURDEN','item_code'=>'INDUSTRIAL_ACCIDENT','final_amount'=>12000],
        ]],
    ];
    foreach($employerFixture as&$fixtureItem)foreach($fixtureItem['line_items']as&$fixtureLine){$fixtureLine['statutory_standard_revision_id']='standard-'.$fixtureLine['id'];$fixtureLine['calculation_basis_id']='basis-'.$fixtureLine['id'];}unset($fixtureItem,$fixtureLine);
    $employerGroups=$service->employerContributionGroups($employerFixture);
    if(count($employerGroups)!==3||array_sum(array_map(static fn(array $group):float=>$group['amount'],$employerGroups))!==194960.0||array_sum(array_map(static fn(array $group):int=>count($group['lines']),$employerGroups))!==10)throw new RuntimeException('사용자부담 기관별 집계 또는 직원별 원천 보존 검증에 실패했습니다.');
    $employerPayloads=$service->employerContributionTransactionPayloads(['income_year_month'=>'2013-08'],'evidence',$employerFixture,'2013-08-31');
    if(count($employerPayloads)!==3||array_sum(array_map(static fn(array$payload):int=>count($payload['items']),$employerPayloads))!==10)throw new RuntimeException('사용자부담 거래 Payload 원천 FK 검증에 실패했습니다.');
    $base = ['regular_employment_income_id'=>'header','approval_request_id'=>'approval','evidence_id'=>'evidence','attribution_month'=>'2013-08'];
    $evidence = $service->register($base + ['generation_role'=>'PAYROLL_REPORT_EVIDENCE','aggregation_key'=>'HEADER'], 'SYSTEM:TEST');
    $sameEvidence = $service->register($base + ['generation_role'=>'PAYROLL_REPORT_EVIDENCE','aggregation_key'=>'HEADER'], 'SYSTEM:TEST');
    if ($evidence['duplicate_prevented'] || !$sameEvidence['duplicate_prevented']) throw new RuntimeException('동일 Payload 멱등 검증에 실패했습니다.');
    foreach ([1,2] as $number) {
        $service->register($base + ['generation_role'=>'EMPLOYEE_PAYROLL','aggregation_key'=>'employee-'.$number,'regular_employment_income_item_id'=>'item-'.$number,'transaction_id'=>'employee-tx-'.$number,'payment_schedule_id'=>'employee-schedule-'.$number,'recognition_date'=>'2013-08-31'], 'SYSTEM:TEST');
    }
    foreach (['NATIONAL_PENSION_SERVICE','NATIONAL_HEALTH_INSURANCE_SERVICE','KOREA_WORKERS_COMPENSATION_WELFARE_SERVICE'] as $agency) {
        $service->register($base + ['generation_role'=>'EMPLOYER_CONTRIBUTION','aggregation_key'=>$agency.'|PAYABLE','transaction_id'=>substr(hash('sha256',$agency),0,36),'recognition_date'=>'2013-08-31'], 'SYSTEM:TEST');
    }
    $collisionBlocked=false;
    try {$service->register(array_replace($base, ['generation_role'=>'PAYROLL_REPORT_EVIDENCE','aggregation_key'=>'HEADER','evidence_id'=>'evidence-other']), 'SYSTEM:TEST');} catch (RuntimeException) {$collisionBlocked=true;}
    if(!$collisionBlocked)throw new RuntimeException('동일 request key의 다른 Payload 충돌 차단에 실패했습니다.');
    $roleCounts=array_map('intval',$pdo->query('SELECT generation_role,COUNT(*) count FROM institution_regular_employment_income_accounting_links GROUP BY generation_role')->fetchAll(PDO::FETCH_KEY_PAIR));
    if($roleCounts!==['EMPLOYEE_PAYROLL'=>2,'EMPLOYER_CONTRIBUTION'=>3,'PAYROLL_REPORT_EVIDENCE'=>1])throw new RuntimeException('역할별 Registry 건수가 다릅니다: '.json_encode($roleCounts));
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $downBlocked = false;
    try { $execute($down); } catch (PDOException $exception) { $downBlocked = $exception->getCode() === '45000'; }
    if (!$downBlocked) throw new RuntimeException('신규 데이터 존재 시 Down 차단에 실패했습니다.');

    $operatingAfter=$operatingCounts();
    echo json_encode(['success'=>true,'checks'=>$checks,'duplicate_up_blocked'=>$duplicateBlocked,'empty_down_and_reup'=>'OK','employer_group_count'=>count($employerGroups),'employer_transaction_payload_count'=>count($employerPayloads),'employer_source_line_count'=>array_sum(array_map(static fn(array $group):int=>count($group['lines']),$employerGroups)),'role_counts'=>$roleCounts,'same_payload_idempotent'=>true,'payload_collision_blocked'=>$collisionBlocked,'data_down_blocked'=>$downBlocked,'operating_before'=>$operatingBefore,'operating_after'=>$operatingAfter,'operating_database_changed'=>$operatingBefore!==$operatingAfter], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$source}`");
    if ($created) $pdo->exec("DROP DATABASE `{$test}`");
}
