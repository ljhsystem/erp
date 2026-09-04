<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\EmploymentContractService;
use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

$db = DbPdo::conn();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$userId = (string) $db->query(
    "SELECT id FROM auth_users WHERE is_active = 1 ORDER BY id LIMIT 1"
)->fetchColumn();
$assert($userId !== '', '활성 사용자를 찾을 수 없습니다.');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user'] = ['id' => $userId];
$_SESSION['auth_state'] = ['user_id' => $userId, 'status' => 'NORMAL'];

$sourceId = (string) $db->query(
    "SELECT c.id
       FROM institution_employment_contracts c
       JOIN user_positions p ON p.position_name = c.job_title_snapshot AND p.is_active = 1
      WHERE c.deleted_at IS NULL
        AND c.contract_date IS NOT NULL
        AND (SELECT COUNT(*) FROM institution_employment_contracts_weekly_schedules s
              WHERE s.contract_id = c.id) = 7
      ORDER BY (c.contract_status = 'DRAFT') DESC, c.created_at DESC
      LIMIT 1"
)->fetchColumn();
$assert($sourceId !== '', '검증 가능한 근로계약 Fixture 기준행이 없습니다.');

$projectId = (string) $db->query(
    "SELECT id FROM system_projects
      WHERE deleted_at IS NULL AND is_active = 1
      ORDER BY sort_no, id LIMIT 1"
)->fetchColumn();
$assert($projectId !== '', '활성 프로젝트 Fixture 기준행이 없습니다.');

$service = new EmploymentContractService($db);
$detail = $service->detail($sourceId)['data'];
$base = array_merge($detail['contract'], [
    'weekly_schedules' => $detail['weekly_schedules'],
    'work_schedule_policy' => $detail['work_schedule_policy'] ?? [],
    'contract_period_type' => 'INDEFINITE',
    'contract_end_date' => null,
    'fixed_term_reason_code' => null,
    'fixed_term_reason_detail' => null,
    'project_id' => null,
]);

$validate = new ReflectionMethod($service, 'validateContract');
$validateScenario = static function (array $payload) use ($validate, $service): array {
    return $validate->invoke($service, $payload)[0];
};

$beforeCount = (int) $db->query('SELECT COUNT(*) FROM institution_employment_contracts')->fetchColumn();

$site = $validateScenario(array_replace($base, ['work_location_type' => 'PROJECT']));
$assert($site['project_id'] === null, 'Scenario A: 현장근무 계약에서 프로젝트 NULL이 거부되었습니다.');

$headOffice = $validateScenario(array_replace($base, ['work_location_type' => 'HEAD_OFFICE']));
$assert($headOffice['project_id'] === null, 'Scenario B: 본사근무 계약에서 프로젝트 NULL이 거부되었습니다.');

$hybrid = $validateScenario(array_replace($base, ['work_location_type' => 'HYBRID']));
$assert($hybrid['project_id'] === null, 'Scenario C: 혼합근무 계약에서 프로젝트 NULL이 거부되었습니다.');

$projectLimited = $validateScenario(array_replace($base, [
    'contract_period_type' => 'FIXED_TERM',
    'contract_end_date' => (new DateTimeImmutable((string) $base['contract_start_date']))
        ->modify('+1 year')->format('Y-m-d'),
    'fixed_term_reason_code' => 'PROJECT_COMPLETION',
    'fixed_term_reason_detail' => '특정 프로젝트 완료 시까지',
    'work_location_type' => 'PROJECT',
    'project_id' => $projectId,
]));
$assert($projectLimited['project_id'] === $projectId, 'Scenario D: 특정 프로젝트 한정 계약이 저장계약을 통과하지 못했습니다.');

try {
    $validateScenario(array_replace($base, [
        'contract_period_type' => 'FIXED_TERM',
        'contract_end_date' => (new DateTimeImmutable((string) $base['contract_start_date']))
            ->modify('+1 year')->format('Y-m-d'),
        'fixed_term_reason_code' => 'PROJECT_COMPLETION',
        'fixed_term_reason_detail' => '특정 프로젝트 완료 시까지',
        'work_location_type' => 'PROJECT',
        'project_id' => null,
    ]));
    throw new RuntimeException('특정 프로젝트 완료 계약의 프로젝트 필수 Guard가 작동하지 않았습니다.');
} catch (InvalidArgumentException $exception) {
    $assert(str_contains($exception->getMessage(), '프로젝트'), '프로젝트 필수 안내가 명확하지 않습니다.');
}

$meta = (new DataTableColumnMetaService($db))->columnsForDomain('employment-contract');
$projectMeta = array_values(array_filter($meta, static fn(array $column): bool =>
    ($column['key'] ?? '') === 'project_id'
))[0] ?? null;
$assert(is_array($projectMeta), 'project_id TableSettings metadata가 없습니다.');
$assert(($projectMeta['required'] ?? true) === false, 'project_id의 DB/TableSettings 기본 필수구분이 선택이 아닙니다.');
$assert(($projectMeta['label'] ?? '') === '특정 프로젝트', 'project_id 기본 사용컬럼명이 명확하지 않습니다.');

$serviceSource = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/EmploymentContractService.php');
$assert(!str_contains($serviceSource, "work_location_type'] ?? '') === 'PROJECT'"), 'Scenario E: 계약 Service에 현장→프로젝트 필수 Guard가 남아 있습니다.');
$assert(!str_contains($serviceSource, 'JobAssignmentService'), 'Scenario E: 근로계약 Service가 직무·배치 책임을 침범합니다.');

$afterCount = (int) $db->query('SELECT COUNT(*) FROM institution_employment_contracts')->fetchColumn();
$assert($afterCount === $beforeCount, 'Runtime Fixture가 근로계약 데이터를 변경했습니다.');

echo "근로계약 근무장소·특정 프로젝트 정책 Runtime Fixture 통과\n";
