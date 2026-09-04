<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeAccountingGenerationService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$test = 'codex_regular_income_closure_full_' . bin2hex(random_bytes(5));
$documentId = '4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';
$approvalId = 'e7f37bc9-82d7-4113-bb64-c5b01cf9e0f1';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id'=>'f113b666-ff40-4f93-a7e7-8cea4cdc9c28','username'=>'employee-evidence-fixture'];
$_SESSION['auth_state'] = ['user_id'=>'f113b666-ff40-4f93-a7e7-8cea4cdc9c28','status'=>'NORMAL'];
if (!preg_match('/^codex_regular_income_closure_full_[0-9a-f]{10}$/', $test)) throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');

$execute = static function (PDO $pdo, string $file): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n"; $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
};
$tables = [
    'user_approval_requests','user_approval_request_steps','user_employees','institution_employment_contracts',
    'institution_regular_employment_incomes','institution_regular_employment_income_items',
    'institution_regular_employment_income_line_items','institution_regular_employment_income_calculation_bases',
    'institution_regular_employment_income_audits','institution_social_insurance_coverages',
    'institution_workplace_size_periods','system_statutory_standards','system_codes','system_settings_config','auth_users',
    'ledger_evidence_metadata','ledger_evidence_metadata_columns','ledger_evidence_salary_report','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements',
    'ledger_evidence_links','institution_regular_employment_income_accounting_links',
    'ledger_payment_schedules','ledger_payment_schedule_histories','ledger_vouchers','ledger_voucher_lines',
];
$tracked = [
    'ledger_evidence_salary_report','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements',
    'ledger_evidence_links','institution_regular_employment_income_accounting_links','institution_regular_employment_income_audits',
    'ledger_payment_schedules','ledger_payment_schedule_histories','ledger_vouchers','ledger_voucher_lines',
];
$counts = static function (PDO $pdo, array $tracked): array {
    $result=[]; foreach($tracked as $table)$result[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); return $result;
};

$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");
        $columnStatement=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema_name AND TABLE_NAME=:table_name AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION");
        $columnStatement->execute([':schema_name'=>$source,':table_name'=>$table]);
        $columns=array_map(static fn(string $column):string=>'`'.str_replace('`','``',$column).'`',$columnStatement->fetchAll(PDO::FETCH_COLUMN));
        if($columns!==[]){$list=implode(',',$columns);$pdo->exec("INSERT INTO `{$test}`.`{$table}` ({$list}) SELECT {$list} FROM `{$source}`.`{$table}`");}
    }
    $pdo->exec("ALTER TABLE `{$test}`.`ledger_evidence_salary_report` ADD CONSTRAINT fk_salary_report_source_income FOREIGN KEY (source_regular_employment_income_id) REFERENCES `institution_regular_employment_incomes` (id)");
    $pdo->exec("ALTER TABLE `{$test}`.`institution_regular_employment_income_accounting_links` ADD CONSTRAINT fk_regular_income_accounting_schedule FOREIGN KEY (payment_schedule_id) REFERENCES `ledger_payment_schedules` (id)");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->exec("USE `{$test}`");
    $execute($pdo, '20260826_05_enable_employee_salary_report_evidence.up.sql');
    $baseline = $counts($pdo, $tracked);
    $plan = (new RegularEmploymentIncomeAccountingGenerationService($pdo))->preflight($documentId, $approvalId, false);

    $scenarios = [
        ['closure.before_evidence_1',1], ['closure.after_evidence_1',1], ['transaction.after_header',1],
        ['transaction.after_items',1], ['transaction.after_settlements',1], ['transaction.after_links',1],
        ['transaction.after_header',2], ['closure.before_evidence_2',1], ['transaction.after_links',2],
        ['closure.after_registry',1], ['closure.before_audit',1],
    ];
    $rollback = [];
    foreach ($scenarios as [$target,$occurrence]) {
        $seen=0; $pdo->beginTransaction();
        try {
            $injector=static function(string $checkpoint)use($target,$occurrence,&$seen):void{if($checkpoint===$target&&++$seen===$occurrence)throw new RuntimeException('INJECTED:'.$target.':'.$occurrence);};
            (new RegularEmploymentIncomeAccountingGenerationService($pdo,$injector))->materialize($plan,'SYSTEM:TEST');
            throw new RuntimeException('실패주입 지점이 실행되지 않았습니다: '.$target.':'.$occurrence);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (!str_contains($exception->getMessage(),'INJECTED:')) throw $exception;
        }
        $after=$counts($pdo,$tracked); $rollback[$target.'#'.$occurrence]=$after===$baseline;
        if($after!==$baseline)throw new RuntimeException('실패 Rollback 후 건수가 달라졌습니다: '.$target.':'.$occurrence);
    }

    $pdo->beginTransaction();
    $result=(new RegularEmploymentIncomeAccountingGenerationService($pdo))->materialize($plan,'SYSTEM:TEST');
    $normal=$counts($pdo,$tracked);
    $created=array_map(static fn(string $table):int=>$normal[$table]-$baseline[$table],array_combine($tracked,$tracked));
    $statuses=$pdo->query("SELECT evidence_status,COUNT(*) FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=".$pdo->quote($documentId)." GROUP BY evidence_status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $roleCounts=array_map('intval',$pdo->query("SELECT generation_role,COUNT(*) FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=".$pdo->quote($documentId)." GROUP BY generation_role ORDER BY generation_role")->fetchAll(PDO::FETCH_KEY_PAIR));
    $evidenceTotals=$pdo->query("SELECT ROUND(SUM(raw_gross_amount),2) gross_amount,ROUND(SUM(raw_deduction_amount),2) deduction_amount,ROUND(SUM(raw_net_payment_amount),2) net_amount,ROUND(SUM(raw_employer_burden_amount),2) employer_burden_amount FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=".$pdo->quote($documentId))->fetch(PDO::FETCH_ASSOC);
    $employeeEvidenceRows=(int)$pdo->query("SELECT COUNT(*) FROM ledger_evidence_salary_report evidence JOIN institution_regular_employment_income_items item ON item.id=evidence.regular_employment_income_item_id AND item.regular_employment_income_id=evidence.source_regular_employment_income_id AND item.employee_id=evidence.employee_id WHERE evidence.source_regular_employment_income_id=".$pdo->quote($documentId))->fetchColumn();
    $idempotentBlocked=false; try{(new RegularEmploymentIncomeAccountingGenerationService($pdo))->preflight($documentId,$approvalId,false);}catch(Throwable){$idempotentBlocked=true;}
    $normalChecks=[
        'evidence'=>$created['ledger_evidence_salary_report']===2,
        'transactions'=>$created['ledger_transactions']===2,
        'items'=>$created['ledger_transaction_items']===8,
        'settlements'=>$created['ledger_transaction_settlements']===7,
        'links'=>$created['ledger_evidence_links']===2,
        'registries'=>$created['institution_regular_employment_income_accounting_links']===4,
        'audit'=>$created['institution_regular_employment_income_audits']===1,
        'schedules'=>$created['ledger_payment_schedules']===0&&$created['ledger_payment_schedule_histories']===0,
        'vouchers'=>$created['ledger_vouchers']===0&&$created['ledger_voucher_lines']===0,
        'correction_required_status'=>$statuses===['CORRECTION_REQUIRED'=>2],
        'roles'=>$roleCounts===['EMPLOYEE_PAYROLL'=>2,'PAYROLL_REPORT_EVIDENCE'=>2],
        'employee_evidence_identity'=>$employeeEvidenceRows===2,
        'totals'=>(float)$evidenceTotals['gross_amount']===2177780.0&&(float)$evidenceTotals['deduction_amount']===157380.0&&(float)$evidenceTotals['net_amount']===2020400.0&&(float)$evidenceTotals['employer_burden_amount']===159850.0,
        'idempotent_preflight_blocked'=>$idempotentBlocked,
        'reported_zero_schedules'=>(int)$result['payment_schedule_count']===0,
    ];
    if(in_array(false,$normalChecks,true))throw new RuntimeException('정상 Closure 검증 실패: '.json_encode($normalChecks,JSON_UNESCAPED_UNICODE));
    $pdo->rollBack();
    if($counts($pdo,$tracked)!==$baseline)throw new RuntimeException('정상 Fixture Rollback 후 기준선이 다릅니다.');
    echo json_encode(['success'=>true,'failure_injection'=>$rollback,'normal'=>$normalChecks,'created'=>$created,'roles'=>$roleCounts],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $pdo->exec("USE `{$source}`");
    $pdo->exec("DROP DATABASE `{$test}`");
}
