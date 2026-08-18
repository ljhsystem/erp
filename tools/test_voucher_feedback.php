<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Repositories\Ledger\JournalLearningFeedbackRepository;
use App\Services\Ledger\JournalCandidateEngineService;
use App\Services\Ledger\JournalLearningFeedbackService;
use Core\Helpers\UuidHelper;

$pdo = Core\DbPdo::conn();
$actor = 'SYSTEM:VOUCHER_FEEDBACK_TEST';
$accounts = $pdo->query("SELECT id FROM ledger_accounts WHERE deleted_at IS NULL AND is_active = 1 AND COALESCE(is_posting,1)=1 ORDER BY account_code LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);
if (count($accounts) < 4) throw new RuntimeException('테스트 가능한 계정과목이 부족합니다.');
$clientId = (string) $pdo->query("SELECT id FROM system_clients WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if ($clientId === '') throw new RuntimeException('테스트 가능한 거래처가 없습니다.');

$metadata = $pdo->query("SELECT import_type, source_table FROM ledger_evidence_metadata WHERE deleted_at IS NULL ORDER BY sort_no")->fetchAll(PDO::FETCH_ASSOC);
$evidence = null;
foreach ($metadata as $row) {
    $table = (string) $row['source_table'];
    if (preg_match('/^[a-z0-9_]+$/i', $table) !== 1) continue;
    $id = $pdo->query("SELECT id FROM `{$table}` ORDER BY id LIMIT 1")->fetchColumn();
    if ($id) {
        $evidence = ['import_type' => (string) $row['import_type'], 'evidence_id' => (string) $id];
        break;
    }
}
if (!$evidence) throw new RuntimeException('테스트 가능한 증빙이 없습니다.');

$pdo->beginTransaction();
try {
    $voucherId = UuidHelper::generate();
    $pdo->prepare("INSERT INTO ledger_vouchers (id,sort_no,voucher_no,voucher_date,status,is_reversal,created_at,created_by,updated_at,updated_by) VALUES (:id,:sort_no,:no,CURDATE(),'posted',0,NOW(),:created_by,NOW(),:updated_by)")->execute([
        ':id' => $voucherId, ':sort_no' => 900000001, ':no' => 'TF-' . substr($voucherId, 0, 8), ':created_by' => $actor, ':updated_by' => $actor,
    ]);
    $lines = [
        ['account' => $accounts[1], 'debit' => 90, 'credit' => 0, 'recommended' => $accounts[0], 'modified' => 1],
        ['account' => $accounts[3], 'debit' => 10, 'credit' => 0, 'recommended' => null, 'modified' => 0],
        ['account' => $accounts[2], 'debit' => 0, 'credit' => 100, 'recommended' => $accounts[2], 'modified' => 0],
    ];
    $lineIds = [];
    foreach ($lines as $index => $line) {
        $lineId = UuidHelper::generate();
        $lineIds[] = $lineId;
        $side = $line['debit'] > 0 ? 'DEBIT' : 'CREDIT';
        $pdo->prepare("INSERT INTO ledger_voucher_lines (id,sort_no,line_no,voucher_id,account_id,debit,credit,journal_rule_id,is_user_modified,recommended_account_id,recommended_line_type,recommended_amount,created_at,created_by,updated_at,updated_by) VALUES (:id,:sort_no,:line_no,:voucher_id,:account,:debit,:credit,NULL,:modified,:recommended,:side,:amount,NOW(),:created_by,NOW(),:updated_by)")->execute([
            ':id' => $lineId, ':sort_no' => 900000010 + $index, ':line_no' => $index + 1, ':voucher_id' => $voucherId,
            ':account' => $line['account'], ':debit' => $line['debit'], ':credit' => $line['credit'], ':modified' => $line['modified'],
            ':recommended' => $line['recommended'], ':side' => $line['recommended'] ? $side : null,
            ':amount' => $line['recommended'] ? max($line['debit'], $line['credit']) : null, ':created_by' => $actor, ':updated_by' => $actor,
        ]);
    }
    $pdo->prepare("INSERT INTO ledger_voucher_line_refs (id,voucher_line_id,ref_target,ref_id,created_at,created_by,updated_at,updated_by) VALUES (:id,:line,'CLIENT',:client,NOW(),:created_by,NOW(),:updated_by)")->execute([
        ':id' => UuidHelper::generate(), ':line' => $lineIds[0], ':client' => $clientId, ':created_by' => $actor, ':updated_by' => $actor,
    ]);
    $pdo->prepare("INSERT INTO ledger_evidence_links (id,evidence_type,evidence_id,target_type,target_id,link_type,amount,created_at,updated_at) VALUES (:id,:type,:evidence,'VOUCHER',:voucher,'MANUAL',0,NOW(),NOW())")->execute([
        ':id' => UuidHelper::generate(), ':type' => $evidence['import_type'], ':evidence' => $evidence['evidence_id'], ':voucher' => $voucherId,
    ]);

    $service = new JournalLearningFeedbackService($pdo);
    $writerStarted = microtime(true);
    $first = $service->recordPostedEvents($voucherId, $actor);
    $second = $service->recordPostedEvents($voucherId, $actor);
    if (count($first['created_ids']) !== 3 || count($second['created_ids']) !== 0) throw new RuntimeException('Event 멱등성 검증 실패');

    $event = $pdo->prepare("SELECT recommended_account_id,final_account_id,is_user_modified FROM ledger_journal_learning_events WHERE voucher_line_id=:line AND event_type=:type");
    $event->execute([':line' => $lineIds[0], ':type' => JournalLearningFeedbackRepository::EVENT_TYPE]);
    $modified = $event->fetch(PDO::FETCH_ASSOC);
    if ($modified['recommended_account_id'] !== $accounts[0] || $modified['final_account_id'] !== $accounts[1] || (int) $modified['is_user_modified'] !== 1) throw new RuntimeException('추천수정 Event 검증 실패');

    $projectionOne = $service->synchronizeProjections();
    $writerElapsed = round((microtime(true) - $writerStarted) * 1000, 2);
    $recentBefore = $pdo->query("SELECT usage_count FROM ledger_journal_recent_patterns WHERE updated_at >= DATE_SUB(NOW(),INTERVAL 1 MINUTE) ORDER BY updated_at DESC LIMIT 1")->fetchColumn();
    $projectionTwo = $service->synchronizeProjections();
    $recentAfter = $pdo->query("SELECT usage_count FROM ledger_journal_recent_patterns WHERE updated_at >= DATE_SUB(NOW(),INTERVAL 1 MINUTE) ORDER BY updated_at DESC LIMIT 1")->fetchColumn();
    if ($recentBefore !== $recentAfter) throw new RuntimeException('Projection 멱등성 검증 실패');
    if (($projectionOne['client_count'] ?? 0) < 1 || $projectionOne !== $projectionTwo) throw new RuntimeException('거래처 Projection 검증 실패');

    $context = $pdo->query("SELECT business_unit,operation_type,transaction_direction,import_type,client_type,client_id,project_id FROM ledger_journal_learning_events WHERE voucher_id=" . $pdo->quote($voucherId) . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $candidateResult = (new JournalCandidateEngineService($pdo))->topCandidates($context + ['total_amount' => 100, 'vat_amount' => 10], 3);
    $sources = array_values(array_unique(array_merge(...array_map(static fn(array $candidate): array => $candidate['source_types'], $candidateResult['candidates']))));
    if (!in_array('RECENT_PATTERN', $sources, true) && !in_array('LEARNING_EVENT', $sources, true)) throw new RuntimeException('다음 추천 반영 검증 실패');

    $reversalId = UuidHelper::generate();
    $pdo->prepare("INSERT INTO ledger_vouchers (id,sort_no,voucher_no,voucher_date,status,is_reversal,created_at,created_by) VALUES (:id,900000099,:no,CURDATE(),'posted',1,NOW(),:actor)")->execute([':id' => $reversalId, ':no' => 'TR-' . substr($reversalId, 0, 8), ':actor' => $actor]);
    $reversal = $service->recordPostedEvents($reversalId, $actor);
    if (count($reversal['created_ids']) !== 0) throw new RuntimeException('취소전표 제외 검증 실패');

    echo json_encode(['first_events' => count($first['created_ids']), 'second_events' => count($second['created_ids']), 'projection_first' => $projectionOne, 'projection_second' => $projectionTwo, 'candidate_sources' => $sources, 'reversal_events' => 0, 'writer_elapsed_ms' => $writerElapsed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
