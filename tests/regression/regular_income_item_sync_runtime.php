<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use Core\DbPdo;

function syncAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$db = DbPdo::conn();
$user = $db->query("SELECT id,username FROM auth_users WHERE is_active=1 ORDER BY created_at,id LIMIT 1")
    ->fetch(PDO::FETCH_ASSOC);
syncAssert(is_array($user), '테스트 Actor로 사용할 활성 사용자가 없습니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id'=>$user['id'],'username'=>$user['username']];
$_SESSION['auth_state'] = ['user_id'=>$user['id'],'status'=>'NORMAL'];

$service = new RegularEmploymentIncomeService($db);
$template = $service->detail('4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6')['data'];
$db->beginTransaction();
register_shutdown_function(static function () use ($db): void {
    if ($db->inTransaction()) $db->rollBack();
});
$preview = (new RegularEmploymentIncomeCalculationService($db))->preview('2013-09', '2013-10-11', array_map(
    static fn(array $item): array => [
        'employee_id' => $item['employee_id'],
        'dependent_count_snapshot' => $item['dependent_count_snapshot'],
    ],
    $template['items']
), 'SYSTEM:REGRESSION');
$created = $service->savePayEffect([
    'income_year_month' => '2013-09',
    'payment_date' => '2013-10-11',
    'title' => '직원 동기화 롤백 Fixture',
    'description' => '회귀검사 후 전체 롤백',
    'items' => $preview['results'],
]);
$documentId = (string) $created['data']['id'];
$before = $service->detail($documentId)['data'];
$beforeIds = array_column($before['items'], 'id', 'employee_id');
$beforeCreatedBy = array_column($before['items'], 'created_by', 'employee_id');
$headerInput = [
    'id'=>$documentId,
    'income_year_month'=>$before['header']['income_year_month'],
    'payment_date'=>$before['header']['payment_date'],
    'title'=>$before['header']['title'],
    'description'=>$before['header']['description'],
];
$checks = [];

try {
    $db->exec('SAVEPOINT item_order_swap');
    $reversed = array_reverse($before['items']);
    $service->savePayEffect($headerInput + ['items'=>$reversed]);
    $after = $service->detail($documentId)['data'];
    $checks['order_swap_uses_two_phase_reorder'] = array_column($after['items'], 'employee_id') === array_column($reversed, 'employee_id');
    $checks['order_swap_keeps_item_ids'] = array_column($after['items'], 'id', 'employee_id') == $beforeIds;
    $checks['order_swap_keeps_created_by'] = array_column($after['items'], 'created_by', 'employee_id') == $beforeCreatedBy;
    $checks['order_swap_is_continuous'] = array_map('intval', array_column($after['items'], 'sort_no')) === [1,2];
} finally {
    $db->exec('ROLLBACK TO SAVEPOINT item_order_swap');
}

try {
    $db->exec('SAVEPOINT item_missing_ids');
    $withoutIds = array_map(static function(array $item): array { unset($item['id']); return $item; }, $before['items']);
    $service->savePayEffect($headerInput + ['items'=>$withoutIds]);
    $after = $service->detail($documentId)['data'];
    $checks['missing_payload_ids_match_by_employee'] = array_column($after['items'], 'id', 'employee_id') === $beforeIds;
    $checks['missing_payload_ids_keep_line_parent'] = count(array_filter(
        $after['items'],
        static fn(array $item): bool => count(array_filter($item['line_items'], static fn(array $line): bool => $line['regular_employment_income_item_id'] === $item['id'])) === count($item['line_items'])
    )) === count($after['items']);
} finally {
    $db->exec('ROLLBACK TO SAVEPOINT item_missing_ids');
}

$updatedBefore = $before['header']['updated_at'];
$duplicateEmployee = [$before['items'][0], $before['items'][0]];
$db->exec('SAVEPOINT duplicate_employee_failure');
try {
    $service->savePayEffect($headerInput + ['items'=>$duplicateEmployee]);
    $checks['duplicate_employee_blocked'] = false;
} catch (InvalidArgumentException $exception) {
    $checks['duplicate_employee_blocked'] = str_contains($exception->getMessage(), '동일 직원');
}
$db->exec('ROLLBACK TO SAVEPOINT duplicate_employee_failure');
$afterFailure = $service->detail($documentId)['data'];
$checks['failed_save_rolls_back_header'] = $afterFailure['header']['updated_at'] === $updatedBefore;
$checks['failed_save_rolls_back_items'] = array_column($afterFailure['items'], 'id', 'employee_id') === $beforeIds
    && array_map('intval', array_column($afterFailure['items'], 'sort_no')) === [1,2];

$duplicateItemId = $before['items'];
$duplicateItemId[1]['id'] = $duplicateItemId[0]['id'];
try {
    $service->savePayEffect($headerInput + ['items'=>$duplicateItemId]);
    $checks['duplicate_item_id_blocked'] = false;
} catch (InvalidArgumentException $exception) {
    $checks['duplicate_item_id_blocked'] = str_contains($exception->getMessage(), '계산행 ID');
}

$duplicateSort = $before['items'];
$duplicateSort[0]['sort_no'] = 1;$duplicateSort[1]['sort_no'] = 1;
try {
    $service->savePayEffect($headerInput + ['items'=>$duplicateSort]);
    $checks['duplicate_payload_sort_blocked'] = false;
} catch (InvalidArgumentException $exception) {
    $checks['duplicate_payload_sort_blocked'] = str_contains($exception->getMessage(), '순번');
}

$missingItem = $before['items'];
$missingItem[0]['id'] = '00000000-0000-4000-8000-000000000000';
try {
    $service->savePayEffect($headerInput + ['items'=>$missingItem]);
    $checks['unknown_item_id_blocked'] = false;
} catch (InvalidArgumentException $exception) {
    $checks['unknown_item_id_blocked'] = str_contains($exception->getMessage(), '존재하지 않는');
}

try {
    $db->exec('SAVEPOINT item_exclusion');
    $service->savePayEffect($headerInput + ['items'=>[$before['items'][0]]]);
    $excluded = $service->detail($documentId)['data'];
    $checks['explicit_exclusion_keeps_remaining_item_id'] = count($excluded['items']) === 1
        && $excluded['items'][0]['id'] === $before['items'][0]['id'];
    $readded = $before['items'];unset($readded[1]['id']);
    $service->savePayEffect($headerInput + ['items'=>$readded]);
    $restored = $service->detail($documentId)['data'];
    $checks['excluded_employee_can_be_readded'] = count($restored['items']) === 2
        && array_map('intval', array_column($restored['items'], 'sort_no')) === [1,2];
} finally {
    $db->exec('ROLLBACK TO SAVEPOINT item_exclusion');
}

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if ($db->inTransaction()) $db->rollBack();
exit($failed===[] ? 0 : 1);
