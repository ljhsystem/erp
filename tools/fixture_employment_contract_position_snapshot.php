<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\EmploymentContractService;
use Core\DbPdo;
use Core\Session;

$db = DbPdo::conn();
$contractId = (string) ($argv[1] ?? '');
if ($contractId === '') {
    throw new InvalidArgumentException('검증할 승인 근로계약 ID를 입력해 주세요.');
}

Session::start();
$user = $db->query('SELECT id,username FROM auth_users WHERE is_active=1 ORDER BY created_at LIMIT 1')
    ->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    throw new RuntimeException('Fixture Actor로 사용할 활성 사용자가 없습니다.');
}
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];

$service = new EmploymentContractService($db);
$source = $service->detail($contractId)['data'];
$contract = $source['contract'];
$employeeId = (string) $contract['employee_id'];
$before = $db->prepare(
    'SELECT e.position_id,p.position_name FROM user_employees e '
    . 'LEFT JOIN user_positions p ON p.id=e.position_id WHERE e.id=:id'
);
$before->execute([':id' => $employeeId]);
$currentBefore = $before->fetch(PDO::FETCH_ASSOC) ?: [];
$alternative = $db->prepare(
    'SELECT position_name FROM user_positions '
    . 'WHERE is_active=1 AND id<>:current_id ORDER BY sort_no,id LIMIT 1'
);
$alternative->execute([':current_id' => (string) ($currentBefore['position_id'] ?? '')]);
$alternativeName = (string) $alternative->fetchColumn();
if ($alternativeName === '') {
    throw new RuntimeException('현재값과 다른 활성 직위·직책 기준정보가 없습니다.');
}

$payload = $contract;
unset($payload['id'], $payload['contract_no'], $payload['contract_status'], $payload['current_approval_request_id']);
$payload['job_title_snapshot'] = $alternativeName;
$payload['weekly_schedules'] = $source['weekly_schedules'];
$payload['work_schedule_policy'] = $source['work_schedule_policy'];
$payload['components'] = $source['components'];
$payload['request_key'] = 'FIXTURE:CONTRACT_POSITION:' . bin2hex(random_bytes(8));

$db->beginTransaction();
try {
    $saved = $service->save($payload);
    $createdId = (string) $saved['data']['id'];
    $created = $service->detail($createdId)['data']['contract'];
    $after = $db->prepare(
        'SELECT e.position_id,p.position_name FROM user_employees e '
        . 'LEFT JOIN user_positions p ON p.id=e.position_id WHERE e.id=:id'
    );
    $after->execute([':id' => $employeeId]);
    $currentAfter = $after->fetch(PDO::FETCH_ASSOC) ?: [];
    $result = [
        'success' => (string) $created['job_title_snapshot'] === $alternativeName
            && $currentBefore === $currentAfter,
        'scenario_a' => [
            'employee_current_before' => $currentBefore['position_name'] ?? null,
            'selected_contract_snapshot' => $alternativeName,
            'saved_contract_snapshot' => $created['job_title_snapshot'] ?? null,
            'employee_current_after' => $currentAfter['position_name'] ?? null,
            'employee_current_unchanged' => $currentBefore === $currentAfter,
        ],
        'fixture_residue' => 0,
    ];
    $db->rollBack();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['success'] ? 0 : 1);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}
