<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\VoucherService;
use App\Services\Ledger\VoucherQueryService;
use App\Services\Auth\AuthSessionService;
use Core\Helpers\UuidHelper;

$pdo = Core\DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mode = strtolower((string) ($argv[1] ?? 'inspect'));

if ($mode === 'worker') {
    $action = (string) ($argv[2] ?? '');
    $voucherId = (string) ($argv[3] ?? '');
    $startAt = (float) ($argv[4] ?? microtime(true));
    while (microtime(true) < $startAt) {
        usleep(1000);
    }
    try {
        session_id('voucher-fixture-' . bin2hex(random_bytes(8)));
        $userId = (string) $pdo->query("SELECT id FROM auth_users WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
        if ($userId === '') {
            throw new RuntimeException('Fixture actor user is unavailable.');
        }
        (new AuthSessionService())->createLoginSession(['id' => $userId, 'username' => 'fixture']);
        $service = new VoucherService($pdo);
        $result = $action === 'review'
            ? $service->completeReview($voucherId)
            : $service->post($voucherId);
        echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(2);
    }
}

function tableCount(PDO $pdo, string $table): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

function snapshot(PDO $pdo): array
{
    $tables = [
        'ledger_vouchers',
        'ledger_voucher_lines',
        'ledger_voucher_line_refs',
        'ledger_evidence_links',
        'ledger_journal_learning_events',
        'ledger_journal_recent_patterns',
        'ledger_journal_client_account_patterns',
        'ledger_payment_schedules',
        'ledger_journal_rules',
        'system_notifications',
    ];
    $result = [];
    foreach ($tables as $table) {
        $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($table))->fetchColumn();
        $result[$table] = $exists === 1 ? tableCount($pdo, $table) : null;
    }
    return $result;
}

function insertRow(PDO $pdo, string $table, array $row): void
{
    $columns = array_keys($row);
    $quoted = array_map(static fn(string $column): string => "`{$column}`", $columns);
    $params = array_map(static fn(string $column): string => ':' . $column, $columns);
    $stmt = $pdo->prepare("INSERT INTO `{$table}` (" . implode(',', $quoted) . ') VALUES (' . implode(',', $params) . ')');
    $stmt->execute(array_combine($params, array_values($row)));
}

function cloneFixture(PDO $pdo, string $status): array
{
    $source = $pdo->query("SELECT v.* FROM ledger_vouchers v WHERE v.deleted_at IS NULL AND EXISTS (SELECT 1 FROM ledger_voucher_lines l WHERE l.voucher_id=v.id) ORDER BY CASE v.status WHEN 'REVIEWED' THEN 0 WHEN 'REVIEW_REQUESTED' THEN 1 ELSE 2 END, v.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$source) {
        throw new RuntimeException('Fixture source voucher is unavailable.');
    }

    $sourceId = (string) $source['id'];
    $voucherId = UuidHelper::generate();
    $source['sort_no'] = (int) $pdo->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_vouchers')->fetchColumn();
    $source['id'] = $voucherId;
    $source['voucher_no'] = 'F' . substr(str_replace('-', '', $voucherId), 0, 14);
    $source['status'] = $status;
    $source['is_reversal'] = 0;
    $source['reversal_of'] = null;
    $source['deleted_at'] = null;
    $source['deleted_by'] = null;
    $source['created_by'] = 'SYSTEM:FIXTURE';
    $source['updated_by'] = 'SYSTEM:FIXTURE';
    $source['created_at'] = date('Y-m-d H:i:s');
    $source['updated_at'] = date('Y-m-d H:i:s');
    insertRow($pdo, 'ledger_vouchers', $source);

    $lineMap = [];
    $stmt = $pdo->prepare('SELECT * FROM ledger_voucher_lines WHERE voucher_id=:id ORDER BY line_no');
    $stmt->execute([':id' => $sourceId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
        $oldLineId = (string) $line['id'];
        $newLineId = UuidHelper::generate();
        $line['sort_no'] = (int) $pdo->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_voucher_lines')->fetchColumn();
        $line['id'] = $newLineId;
        $line['voucher_id'] = $voucherId;
        $line['journal_rule_id'] = null;
        $line['recommended_account_id'] = $line['account_id'];
        $line['recommended_line_type'] = (float) $line['debit'] > 0 ? 'DEBIT' : 'CREDIT';
        $line['recommended_amount'] = (float) $line['debit'] > 0 ? $line['debit'] : $line['credit'];
        $line['is_user_modified'] = 0;
        $line['created_by'] = 'SYSTEM:FIXTURE';
        $line['updated_by'] = 'SYSTEM:FIXTURE';
        insertRow($pdo, 'ledger_voucher_lines', $line);
        $lineMap[$oldLineId] = $newLineId;
    }

    foreach ($lineMap as $oldLineId => $newLineId) {
        $stmt = $pdo->prepare('SELECT * FROM ledger_voucher_line_refs WHERE voucher_line_id=:id');
        $stmt->execute([':id' => $oldLineId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ref) {
            $ref['id'] = UuidHelper::generate();
            $ref['voucher_line_id'] = $newLineId;
            $ref['created_by'] = 'SYSTEM:FIXTURE';
            $ref['updated_by'] = 'SYSTEM:FIXTURE';
            insertRow($pdo, 'ledger_voucher_line_refs', $ref);
        }
    }

    return ['voucher_id' => $voucherId, 'line_ids' => array_values($lineMap)];
}

function runConcurrent(string $action, string $voucherId): array
{
    $startAt = microtime(true) + 0.8;
    $command = [PHP_BINARY, __FILE__, 'worker', $action, $voucherId, (string) $startAt];
    $processes = [];
    foreach ([1, 2] as $index) {
        $processes[$index] = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $processes[$index] = [$processes[$index], $pipes];
    }
    $results = [];
    foreach ($processes as $index => [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $results[$index] = ['exit_code' => $exitCode, 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
    }
    return $results;
}

function cleanupFixture(PDO $pdo, array $fixtureIds): void
{
    foreach ($fixtureIds as $voucherId) {
        $lineStmt = $pdo->prepare('SELECT id FROM ledger_voucher_lines WHERE voucher_id=:id');
        $lineStmt->execute([':id' => $voucherId]);
        $lineIds = $lineStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($lineIds !== []) {
            $marks = implode(',', array_fill(0, count($lineIds), '?'));
            foreach (['ledger_voucher_line_refs', 'ledger_journal_learning_events'] as $table) {
                $column = $table === 'ledger_voucher_line_refs' ? 'voucher_line_id' : 'voucher_line_id';
                $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$marks})")->execute($lineIds);
            }
        }
        $scheduleStmt = $pdo->prepare("SELECT id FROM ledger_payment_schedules WHERE source_type='VOUCHER_LINE' AND source_id=:id");
        $scheduleStmt->execute([':id' => $voucherId]);
        $scheduleIds = $scheduleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($scheduleIds !== []) {
            $marks = implode(',', array_fill(0, count($scheduleIds), '?'));
            $pdo->prepare("DELETE FROM ledger_payment_schedule_histories WHERE payment_schedule_id IN ({$marks})")->execute($scheduleIds);
        }
        foreach ([
            ['system_notifications', 'ref_id'],
            ['ledger_evidence_links', 'target_id'],
            ['ledger_payment_schedules', 'source_id'],
            ['ledger_journal_learning_events', 'voucher_id'],
        ] as [$table, $column]) {
            $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($table) . ' AND COLUMN_NAME=' . $pdo->quote($column))->fetchColumn();
            if ($exists === 1) {
                $pdo->prepare("DELETE FROM {$table} WHERE {$column}=:id")->execute([':id' => $voucherId]);
            }
        }
        $pdo->prepare('DELETE FROM ledger_voucher_lines WHERE voucher_id=:id')->execute([':id' => $voucherId]);
        $pdo->prepare('DELETE FROM ledger_vouchers WHERE id=:id')->execute([':id' => $voucherId]);
    }
}

function loginFixtureActor(PDO $pdo): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id('voucher-fixture-parent-' . bin2hex(random_bytes(8)));
    $userId = (string) $pdo->query("SELECT id FROM auth_users WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if ($userId === '') {
        throw new RuntimeException('Fixture actor user is unavailable.');
    }
    (new AuthSessionService())->createLoginSession(['id' => $userId, 'username' => 'fixture']);
    return $userId;
}

function createDeletedAccountFixture(PDO $pdo, string $voucherId): string
{
    $line = $pdo->prepare('SELECT id,account_id FROM ledger_voucher_lines WHERE voucher_id=:id ORDER BY line_no LIMIT 1');
    $line->execute([':id' => $voucherId]);
    $lineRow = $line->fetch(PDO::FETCH_ASSOC);
    $accountStmt = $pdo->prepare('SELECT * FROM ledger_accounts WHERE id=:id');
    $accountStmt->execute([':id' => $lineRow['account_id']]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    $accountId = UuidHelper::generate();
    $account['id'] = $accountId;
    $account['sort_no'] = (int) $pdo->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_accounts')->fetchColumn();
    if (array_key_exists('account_code', $account)) {
        $account['account_code'] = 'F' . substr(str_replace('-', '', $accountId), 0, 14);
    }
    if (array_key_exists('code', $account)) {
        $account['code'] = 'F' . substr(str_replace('-', '', $accountId), 0, 14);
    }
    $account['is_active'] = 0;
    $account['deleted_at'] = date('Y-m-d H:i:s');
    $account['deleted_by'] = 'SYSTEM:FIXTURE';
    $account['created_by'] = 'SYSTEM:FIXTURE';
    $account['updated_by'] = 'SYSTEM:FIXTURE';
    insertRow($pdo, 'ledger_accounts', $account);
    $pdo->prepare('UPDATE ledger_voucher_lines SET account_id=:account WHERE id=:line')->execute([':account' => $accountId, ':line' => $lineRow['id']]);
    return $accountId;
}

function attachEvidenceFixture(PDO $pdo, string $voucherId): array
{
    $source = $pdo->query("SELECT link.evidence_type AS import_type,link.evidence_id,metadata.source_table FROM ledger_evidence_links link INNER JOIN ledger_evidence_metadata metadata ON metadata.import_type=link.evidence_type AND metadata.deleted_at IS NULL WHERE link.target_type='VOUCHER' AND link.deleted_at IS NULL ORDER BY link.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$source) {
        throw new RuntimeException('Fixture evidence source is unavailable.');
    }

    $table = (string) $source['source_table'];
    if (preg_match('/^ledger_evidence_[a-z0-9_]+$/', $table) !== 1) {
        throw new RuntimeException('Fixture evidence table is invalid.');
    }
    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id=:id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $source['evidence_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Fixture evidence body is unavailable.');
    }

    $newEvidenceId = UuidHelper::generate();
    $row['id'] = $newEvidenceId;
    if (array_key_exists('sort_no', $row)) {
        $row['sort_no'] = (int) $pdo->query("SELECT COALESCE(MAX(sort_no),0)+1 FROM `{$table}`")->fetchColumn();
    }
    if (array_key_exists('external_key', $row)) {
        $row['external_key'] = 'FIXTURE:' . $newEvidenceId;
    }
    foreach (['created_by', 'updated_by'] as $column) {
        if (array_key_exists($column, $row)) $row[$column] = 'SYSTEM:FIXTURE';
    }
    foreach (['created_at', 'updated_at'] as $column) {
        if (array_key_exists($column, $row)) $row[$column] = date('Y-m-d H:i:s');
    }
    foreach (['deleted_at', 'deleted_by'] as $column) {
        if (array_key_exists($column, $row)) $row[$column] = null;
    }
    insertRow($pdo, $table, $row);

    insertRow($pdo, 'ledger_evidence_links', [
        'id' => UuidHelper::generate(),
        'evidence_type' => (string) $source['import_type'],
        'evidence_id' => $newEvidenceId,
        'target_type' => 'VOUCHER',
        'target_id' => $voucherId,
        'link_type' => 'MANUAL',
        'amount' => '0.00',
        'memo' => 'SYSTEM:FIXTURE',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'deleted_at' => null,
    ]);

    return ['table' => $table, 'id' => $newEvidenceId];
}

if ($mode === 'inspect') {
    echo json_encode([
        'snapshot' => snapshot($pdo),
        'statuses' => $pdo->query('SELECT status,COUNT(*) count FROM ledger_vouchers GROUP BY status')->fetchAll(PDO::FETCH_ASSOC),
        'candidate' => $pdo->query("SELECT v.id,v.voucher_no,v.status,COUNT(l.id) line_count FROM ledger_vouchers v JOIN ledger_voucher_lines l ON l.voucher_id=v.id WHERE v.deleted_at IS NULL GROUP BY v.id ORDER BY v.id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

if ($mode === 'cleanup') {
    $fixtureIds = $pdo->query("SELECT id FROM ledger_vouchers WHERE created_by='SYSTEM:FIXTURE'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    cleanupFixture($pdo, array_map('strval', $fixtureIds));
    $pdo->exec("DELETE FROM ledger_accounts WHERE created_by='SYSTEM:FIXTURE'");
    echo json_encode(['removed_fixture_vouchers' => count($fixtureIds), 'after_cleanup' => snapshot($pdo)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$before = snapshot($pdo);
$fixtureIds = [];
$fixtureAccountIds = [];
$fixtureEvidenceBodies = [];
try {
    $actorId = loginFixtureActor($pdo);
    $reviewFixture = cloneFixture($pdo, 'REVIEW_REQUESTED');
    $fixtureIds[] = $reviewFixture['voucher_id'];
    $reviewRace = runConcurrent('review', $reviewFixture['voucher_id']);

    $postFixture = cloneFixture($pdo, 'REVIEWED');
    $fixtureIds[] = $postFixture['voucher_id'];
    $postRace = runConcurrent('post', $postFixture['voucher_id']);

    $postState = $pdo->prepare('SELECT status FROM ledger_vouchers WHERE id=:id');
    $postState->execute([':id' => $postFixture['voucher_id']]);
    $eventStmt = $pdo->prepare('SELECT COUNT(*) FROM ledger_journal_learning_events WHERE voucher_id=:id');
    $eventStmt->execute([':id' => $postFixture['voucher_id']]);

    $deletedAccountFixture = cloneFixture($pdo, 'REVIEWED');
    $fixtureIds[] = $deletedAccountFixture['voucher_id'];
    $fixtureAccountIds[] = createDeletedAccountFixture($pdo, $deletedAccountFixture['voucher_id']);
    try {
        (new VoucherService($pdo))->post($deletedAccountFixture['voucher_id']);
        $deletedAccountResult = ['blocked' => false, 'error' => null];
    } catch (Throwable $e) {
        $deletedAccountResult = ['blocked' => true, 'error' => $e->getMessage()];
    }

    $reversalSource = cloneFixture($pdo, 'POSTED');
    $fixtureIds[] = $reversalSource['voucher_id'];
    $fixtureEvidenceBodies[] = attachEvidenceFixture($pdo, $reversalSource['voucher_id']);
    $ruleUsageBeforeReversal = (int) $pdo->query('SELECT COALESCE(SUM(usage_count),0) FROM ledger_journal_rules')->fetchColumn();
    $reversalResult = (new VoucherService($pdo))->createReversalVoucher($reversalSource['voucher_id'], 'USER:' . $actorId);
    $reversalId = (string) $reversalResult['id'];
    $fixtureIds[] = $reversalId;
    $recentCountBeforeReversalPost = (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_recent_patterns')->fetchColumn();
    $clientCountBeforeReversalPost = (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_client_account_patterns')->fetchColumn();
    $reverseCheck = $pdo->prepare("SELECT COUNT(*) FROM ledger_voucher_lines original JOIN ledger_voucher_lines reversal ON reversal.voucher_id=:reversal AND original.voucher_id=:original AND reversal.line_no=original.line_no AND reversal.debit=original.credit AND reversal.credit=original.debit");
    $reverseCheck->execute([':reversal' => $reversalId, ':original' => $reversalSource['voucher_id']]);
    $originalLineCount = $pdo->prepare('SELECT COUNT(*) FROM ledger_voucher_lines WHERE voucher_id=:id');
    $originalLineCount->execute([':id' => $reversalSource['voucher_id']]);
    $refCount = $pdo->prepare('SELECT COUNT(*) FROM ledger_voucher_line_refs ref JOIN ledger_voucher_lines line ON line.id=ref.voucher_line_id WHERE line.voucher_id=:id');
    $refCount->execute([':id' => $reversalSource['voucher_id']]);
    $originalRefCount = (int) $refCount->fetchColumn();
    $refCount->execute([':id' => $reversalId]);
    $reversalRefCount = (int) $refCount->fetchColumn();
    $evidenceCount = $pdo->prepare("SELECT COUNT(*) FROM ledger_evidence_links WHERE target_type='VOUCHER' AND target_id=:id AND deleted_at IS NULL");
    $evidenceCount->execute([':id' => $reversalSource['voucher_id']]);
    $originalEvidenceCount = (int) $evidenceCount->fetchColumn();
    $evidenceCount->execute([':id' => $reversalId]);
    $reversalEvidenceCount = (int) $evidenceCount->fetchColumn();
    $reversalLearning = $pdo->prepare('SELECT COUNT(*) FROM ledger_journal_learning_events WHERE voucher_id=:id');
    $reversalLearning->execute([':id' => $reversalId]);
    $reversalDetail = (new VoucherQueryService($pdo))->getDetail(
        $reversalId,
        static fn(array $evidence): array => $evidence
    ) ?: [];
    (new VoucherService($pdo))->requestReview($reversalId);
    (new VoucherService($pdo))->completeReview($reversalId);
    (new VoucherService($pdo))->post($reversalId);
    $evidenceCount->execute([':id' => $reversalSource['voucher_id']]);
    $originalEvidenceCountAfterPost = (int) $evidenceCount->fetchColumn();
    $evidenceCount->execute([':id' => $reversalId]);
    $reversalEvidenceCountAfterPost = (int) $evidenceCount->fetchColumn();
    $reversalLearning->execute([':id' => $reversalId]);
    $reversalVerification = [
        'original_status' => $pdo->query('SELECT status FROM ledger_vouchers WHERE id=' . $pdo->quote($reversalSource['voucher_id']))->fetchColumn(),
        'reversal_status' => $pdo->query('SELECT status FROM ledger_vouchers WHERE id=' . $pdo->quote($reversalId))->fetchColumn(),
        'reversal_of' => $pdo->query('SELECT reversal_of FROM ledger_vouchers WHERE id=' . $pdo->quote($reversalId))->fetchColumn(),
        'reversed_line_count' => (int) $reverseCheck->fetchColumn(),
        'original_line_count' => (int) $originalLineCount->fetchColumn(),
        'original_ref_count' => $originalRefCount,
        'reversal_ref_count' => $reversalRefCount,
        'original_evidence_count' => $originalEvidenceCount,
        'reversal_evidence_count' => $reversalEvidenceCount,
        'original_evidence_count_after_post' => $originalEvidenceCountAfterPost,
        'reversal_evidence_count_after_post' => $reversalEvidenceCountAfterPost,
        'learning_event_count' => (int) $reversalLearning->fetchColumn(),
        'recent_projection_delta' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_recent_patterns')->fetchColumn() - $recentCountBeforeReversalPost,
        'client_projection_delta' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_journal_client_account_patterns')->fetchColumn() - $clientCountBeforeReversalPost,
        'rule_usage_delta' => (int) $pdo->query('SELECT COALESCE(SUM(usage_count),0) FROM ledger_journal_rules')->fetchColumn() - $ruleUsageBeforeReversal,
        'detail_direct_evidence_count' => count((array) ($reversalDetail['linked_evidences'] ?? [])),
        'detail_original_evidence_count' => count((array) ($reversalDetail['original_linked_evidences'] ?? [])),
        'posted_status' => $pdo->query('SELECT status FROM ledger_vouchers WHERE id=' . $pdo->quote($reversalId))->fetchColumn(),
    ];

    echo json_encode([
        'before' => $before,
        'review' => ['fixture' => $reviewFixture['voucher_id'], 'workers' => $reviewRace],
        'post' => ['fixture' => $postFixture['voucher_id'], 'workers' => $postRace, 'status' => $postState->fetchColumn(), 'learning_events' => (int) $eventStmt->fetchColumn()],
        'deleted_account' => $deletedAccountResult,
        'reversal' => $reversalVerification,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    cleanupFixture($pdo, $fixtureIds);
    foreach ($fixtureAccountIds as $accountId) {
        $pdo->prepare('DELETE FROM ledger_accounts WHERE id=:id')->execute([':id' => $accountId]);
    }
    foreach ($fixtureEvidenceBodies as $body) {
        $pdo->prepare("DELETE FROM `{$body['table']}` WHERE id=:id")->execute([':id' => $body['id']]);
    }
    echo json_encode(['after_cleanup' => snapshot($pdo)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
