<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Controllers\Approval\ApprovalInboxController;
use Core\DbPdo;

[$script, $schema, $stepId, $actorId, $workerCode] = $argv + [null, '', '', '', ''];
if (!preg_match('/^tmp_daily_income_approval_[0-9a-f]{12}$/', $schema)
    || !preg_match('/^[0-9a-f-]{36}$/i', $stepId)
    || !preg_match('/^[0-9a-f-]{36}$/i', $actorId)
    || !in_array($workerCode, ['A', 'B'], true)) {
    throw new InvalidArgumentException('동시성 Worker 실행 인자가 올바르지 않습니다.');
}

$db = DbPdo::conn();
$db->exec("USE `{$schema}`");
$ready = $db->prepare("UPDATE _approval_concurrency_barrier SET ready=1 WHERE worker_code=:worker_code");
$ready->execute([':worker_code' => $workerCode]);
$deadline = microtime(true) + 15;
do {
    $released = (int) $db->query("SELECT released FROM _approval_concurrency_barrier WHERE worker_code=" . $db->quote($workerCode))->fetchColumn();
    if ($released === 1) break;
    usleep(20000);
} while (microtime(true) < $deadline);
if ($released !== 1) throw new RuntimeException('동시성 Barrier 해제 시간을 초과했습니다.');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_id('daily-approval-concurrency-' . strtolower($workerCode) . '-' . substr(str_replace('-', '', $stepId), 0, 16));
    session_start();
}
$_SESSION['user'] = ['id' => $actorId, 'username' => 'daily-approval-concurrency-' . $workerCode];
$_SESSION['auth_state'] = ['user_id' => $actorId, 'status' => 'NORMAL'];
$_POST = ['step_id' => $stepId, 'decision' => 'approved', 'comment' => '격리 동시 승인 ' . $workerCode];
ob_start();
(new ApprovalInboxController($db))->apiAct();
$response = (string) ob_get_clean();
echo $response, PHP_EOL;
