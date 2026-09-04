<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = Core\DbPdo::conn();
$model = new App\Models\Institution\EmploymentContractModel($db);
$service = new App\Services\Institution\EmploymentContractService($db);
$numberMethod = new ReflectionMethod($service, 'contractNo');
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$source = $db->query("SELECT * FROM institution_employment_contracts WHERE deleted_at IS NULL ORDER BY created_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$assert(is_array($source), '근로계약 Fixture 기준행이 없습니다.');
$fixtureId = Core\Helpers\UuidHelper::generate();
$contractNo = $numberMethod->invoke($service, '2013-07-19');
$db->beginTransaction();
try {
    $fixture = $source;
    $fixture['id'] = $fixtureId;
    $fixture['sort_no'] = $model->nextSortNo() + 100000;
    $fixture['contract_no'] = $contractNo;
    $fixture['contract_date'] = '2013-07-19';
    $fixture['contract_start_date'] = '2013-08-01';
    $fixture['contract_end_date'] = null;
    $fixture['previous_contract_id'] = null;
    $fixture['revision_no'] = 1;
    $fixture['revision_reason'] = 'contract date rollback fixture';
    $fixture['contract_status'] = 'DRAFT';
    $fixture['current_approval_request_id'] = null;
    $fixture['approved_at'] = null;
    $fixture['terminated_at'] = null;
    $fixture['termination_reason'] = null;
    $fixture['created_at'] = '2026-08-22 12:00:00';
    $fixture['created_by'] = Core\Helpers\ActorHelper::system('CONTRACT_DATE_FIXTURE');
    $fixture['updated_at'] = null;
    $fixture['updated_by'] = null;
    $fixture['deleted_at'] = null;
    $fixture['deleted_by'] = null;
    $model->create($fixture);

    $row = $model->find($fixtureId, false, true);
    $assert($row['contract_date'] === '2013-07-19', 'Scenario A 계약일 저장 실패');
    $assert($row['contract_start_date'] === '2013-08-01', 'Scenario A 시작일 분리 실패');
    $assert(str_starts_with($row['contract_no'], 'EC-20130719-'), 'Scenario A 계약번호 날짜 실패');
    $assert($row['created_at'] === '2026-08-22 12:00:00', 'Scenario A ERP 등록시각 분리 실패');
    $assert($row['contract_end_date'] === null, 'Scenario C 무기계약 종료일 NULL 실패');

    $originalNumber = $row['contract_no'];
    $updated = $model->updateEditable($fixtureId, [
        'contract_date' => '2026-08-22',
        'contract_start_date' => '2026-09-01',
    ]);
    $assert($updated, 'Scenario B 서로 다른 계약일·시작일 DRAFT 저장 실패');
    $row = $model->find($fixtureId, false, true);
    $assert($row['contract_date'] === '2026-08-22' && $row['contract_start_date'] === '2026-09-01', 'Scenario B 날짜 분리 실패');
    $assert($row['contract_no'] === $originalNumber, 'Model이 계약번호를 임의 변경했습니다.');

    $db->exec("UPDATE institution_employment_contracts SET contract_status='APPROVED' WHERE id=" . $db->quote($fixtureId));
    $assert(!$model->updateEditable($fixtureId, ['contract_date' => '2026-08-23']), 'Scenario F 승인계약 계약일 변경이 허용되었습니다.');

    $sourceCode = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/EmploymentContractService.php');
    $assert(str_contains($sourceCode, "\$sourceContractDate ?? \$requestedContractDate"), 'Scenario D CORRECTION 원 계약일 보존 계약 누락');
    $assert(str_contains($sourceCode, "\$revisionKind === 'CORRECTION'"), 'Scenario E CHANGE 계약일 분기 누락');

    $db->rollBack();
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}

$remaining = (int) $db->query('SELECT COUNT(*) FROM institution_employment_contracts WHERE id=' . $db->quote($fixtureId))->fetchColumn();
$assert($remaining === 0, '계약일 Runtime Fixture가 잔존합니다.');
echo "employment contract contract date runtime: OK\n";
