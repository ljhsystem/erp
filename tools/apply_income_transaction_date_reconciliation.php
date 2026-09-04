<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

if (($argv[1] ?? '') !== '--apply') throw new RuntimeException('운영 적용에는 --apply 인자가 필요합니다.');
$db = DbPdo::conn();
$sql = "SELECT transaction_row.id,transaction_row.transaction_date current_transaction_date,actual_date.transaction_date expected_date FROM ledger_transactions transaction_row JOIN ledger_evidence_links evidence_link ON evidence_link.target_type='TRANSACTION' AND evidence_link.target_id=transaction_row.id AND evidence_link.evidence_type='DAILY_EMPLOYMENT_INCOME' AND evidence_link.deleted_at IS NULL JOIN ledger_evidence_daily_employment_income evidence ON evidence.id=evidence_link.evidence_id JOIN (SELECT daily_employment_income_item_id,MAX(work_date) transaction_date FROM institution_daily_employment_income_workdays GROUP BY daily_employment_income_item_id) actual_date ON actual_date.daily_employment_income_item_id=evidence.daily_employment_income_item_id WHERE transaction_row.transaction_date<>actual_date.transaction_date";
$before = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
if ($before === []) throw new RuntimeException('보정할 일용근로소득 연결 거래가 없습니다.');
$actor = ActorHelper::system('INCOME_TRANSACTION_DATE_RECONCILIATION');
$db->beginTransaction();
try {
    $update = $db->prepare('UPDATE ledger_transactions SET transaction_date=:expected_date,updated_at=CURRENT_TIMESTAMP,updated_by=:actor WHERE id=:id AND transaction_date=:current_date');
    foreach ($before as $row) {
        $update->execute([':expected_date'=>$row['expected_date'],':actor'=>$actor,':id'=>$row['id'],':current_date'=>$row['current_transaction_date']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('연결 거래일 동시 변경이 감지되었습니다.');
    }
    $after = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($after !== []) throw new RuntimeException('실제 거래일 보정 후 대사에 실패했습니다.');
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}
echo json_encode(['success'=>true,'corrected'=>$before,'remaining_mismatches'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
