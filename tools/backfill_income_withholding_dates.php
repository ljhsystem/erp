<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode = (string) ($argv[1] ?? '');
if (!in_array($mode, ['--preview','--apply'], true)) throw new RuntimeException('운영 조회에는 --preview, 보정에는 --apply 인자가 필요합니다.');
$db = DbPdo::conn();
$db->beginTransaction();
try {
    $regular = [['document_id'=>'4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6','withholding_date'=>'2013-09-11']];
    foreach ($regular as $row) {
        $db->prepare('UPDATE institution_regular_employment_incomes SET withholding_date=:date WHERE id=:id AND withholding_date IS NULL')->execute([':date'=>$row['withholding_date'],':id'=>$row['document_id']]);
        $db->prepare('UPDATE ledger_evidence_salary_report SET raw_withholding_date=:date WHERE source_regular_employment_income_id=:id AND raw_withholding_date IS NULL')->execute([':date'=>$row['withholding_date'],':id'=>$row['document_id']]);
    }

    $dailyCorrections = [['document_id'=>'e8650425-ef60-4bbb-bd5e-88deeeff7f48','withholding_date'=>'2013-09-30']];
    foreach ($dailyCorrections as $row) {
        $db->prepare('UPDATE institution_daily_employment_incomes SET withholding_date=:date WHERE id=:id AND withholding_date IS NULL')->execute([':date'=>$row['withholding_date'],':id'=>$row['document_id']]);
        $db->prepare('UPDATE ledger_evidence_daily_employment_income SET raw_withholding_date=:date WHERE source_daily_employment_income_id=:id AND raw_withholding_date IS NULL')->execute([':date'=>$row['withholding_date'],':id'=>$row['document_id']]);
    }

    $daily = $db->query("SELECT l.daily_employment_income_id document_id,MIN(t.transaction_date) min_date,MAX(t.transaction_date) max_date FROM institution_daily_employment_income_accounting_links l JOIN ledger_transactions t ON t.id=l.transaction_id WHERE l.artifact_role='WORKER_PAYMENT' GROUP BY l.daily_employment_income_id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($mode === '--preview') {
        $db->rollBack();
        echo json_encode(['success'=>true,'mode'=>'preview','regular'=>$regular,'daily_corrections'=>$dailyCorrections,'daily_transaction_dates'=>$daily],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
        exit;
    }
    $remaining = [
        'regular_headers'=>(int)$db->query('SELECT COUNT(*) FROM institution_regular_employment_incomes WHERE withholding_date IS NULL')->fetchColumn(),
        'daily_headers'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes WHERE withholding_date IS NULL')->fetchColumn(),
        'regular_evidence'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE raw_withholding_date IS NULL')->fetchColumn(),
        'daily_evidence'=>(int)$db->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE raw_withholding_date IS NULL')->fetchColumn(),
    ];
    if (array_sum($remaining) !== 0) throw new RuntimeException('기존 승인 소득자료의 원천징수일 보정이 완전하지 않습니다.');
    $db->commit();
    echo json_encode(['success'=>true,'regular'=>$regular,'daily_corrections'=>$dailyCorrections,'daily_transaction_dates'=>$daily,'remaining_nulls'=>$remaining],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}
