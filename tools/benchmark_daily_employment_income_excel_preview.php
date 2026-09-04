<?php

declare(strict_types=1);

use App\Services\Institution\DailyEmploymentIncomeExcelService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$service = new DailyEmploymentIncomeExcelService($db);
$db->beginTransaction();
$path = tempnam(sys_get_temp_dir(), 'daily-income-benchmark-');
if ($path === false) throw new RuntimeException('임시 파일을 생성할 수 없습니다.');
try {
    $actor = ActorHelper::system('DAILY_INCOME_BENCHMARK');
    $insert = $db->prepare('INSERT INTO system_clients (id,sort_no,client_name,client_type,is_active,created_at,created_by,updated_at,updated_by) VALUES (:id,:sort_no,:name,\'DAILY_WORKER\',1,NOW(),:created_by,NOW(),:updated_by)');
    $workerIds = [];
    for ($worker = 1; $worker <= 20; $worker++) {
        $id = UuidHelper::generate();
        $insert->execute([':id' => $id, ':sort_no' => 900000 + $worker, ':name' => '성능검증 작업자 ' . $worker, ':created_by' => $actor, ':updated_by' => $actor]);
        $workerIds[] = $id;
    }
    $groups = [];
    for ($group = 1; $group <= 10; $group++) {
        $items = [];
        foreach ($workerIds as $workerIndex => $workerId) {
            $workdays = [];
            for ($day = 1; $day <= 20; $day++) $workdays[] = ['work_date' => sprintf('2026-08-%02d', $day), 'daily_rate_amount' => 100000 + $workerIndex];
            $items[] = ['worker_client_id' => $workerId, 'work_type_code' => 'STONE', 'daily_rate_amount' => 100000 + $workerIndex, 'workdays' => $workdays];
        }
        $groups[] = ['business_unit' => 'HQ', 'project_id' => null, 'work_team_id' => null, 'work_description' => '성능검증 그룹 ' . $group, 'items' => $items];
    }
    (new Xlsx($service->createDownload($groups)))->save($path);
    $started = hrtime(true);
    $preview = $service->preview($path, '2026-08');
    $elapsedMs = (hrtime(true) - $started) / 1_000_000;
    if (($preview['data']['valid'] ?? false) !== true) throw new RuntimeException('200행 Preview 검증에 실패했습니다.');
    echo json_encode([
        'groups' => count($preview['data']['groups']),
        'rows' => $preview['data']['summary']['row_count'],
        'workdays' => 4000,
        'preview_elapsed_ms' => round($elapsedMs, 2),
        'db_persisted' => false,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} finally {
    if ($db->inTransaction()) $db->rollBack();
    @unlink($path);
}
