<?php

declare(strict_types=1);

use App\Models\Approval\ApprovalInboxModel;
use App\Services\Approval\EmploymentContractApprovalAdapter;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$request = $pdo->query(
    "SELECT r.*, contract.contract_no, contract.contract_status
       FROM user_approval_requests r
       JOIN institution_employment_contracts contract
         ON contract.id = r.document_id
        AND contract.current_approval_request_id = r.id
        AND contract.deleted_at IS NULL
      WHERE r.document_type = 'EMPLOYMENT_CONTRACT'
        AND r.is_active = 1
      ORDER BY r.requested_at DESC, r.sort_no DESC
      LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    throw new RuntimeException('검증할 근로계약 결재요청이 없습니다.');
}

$componentStmt = $pdo->prepare(
    'SELECT component_code, component_name, amount
       FROM institution_employment_contracts_components
      WHERE contract_id = :contract_id AND deleted_at IS NULL
      ORDER BY sort_no, id'
);
$componentStmt->execute([':contract_id' => $request['document_id']]);
$components = $componentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$monthlyTotal = round(array_sum(array_map(
    static fn(array $row): float => (float) $row['amount'],
    $components
)), 2);
if ($components === [] || $monthlyTotal <= 0) {
    throw new RuntimeException('검증 대상 근로계약에 활성 지급조건 또는 월 지급합계가 없습니다.');
}

$inbox = new ApprovalInboxModel($pdo);
$submitted = $inbox->page((string) $request['requester_id'], 'submitted', [
    'start' => 0,
    'length' => 500,
]);
$listRow = array_values(array_filter(
    $submitted['rows'],
    static fn(array $row): bool => (string) $row['request_id'] === (string) $request['id']
))[0] ?? null;
if (!$listRow || (float) $listRow['total_amount'] !== $monthlyTotal) {
    throw new RuntimeException('내가 상신한 문서의 총금액 projection이 월 지급합계와 다릅니다.');
}

$statusBox = match ((string) $request['status']) {
    'pending', 'in_progress' => 'progress',
    'approved' => 'completed',
    'rejected' => 'rejected',
    default => null,
};
$statusRows = $statusBox === null ? ['rows' => []] : $inbox->page((string) $request['requester_id'], $statusBox, [
    'start' => 0,
    'length' => 500,
]);
$statusRow = array_values(array_filter(
    $statusRows['rows'],
    static fn(array $row): bool => (string) $row['request_id'] === (string) $request['id']
))[0] ?? null;
if ($statusBox !== null && (!$statusRow || (float) $statusRow['total_amount'] !== $monthlyTotal)) {
    throw new RuntimeException($statusBox . ' 문서의 총금액 projection이 월 지급합계와 다릅니다.');
}

$sortColumns = array_map(
    static fn(string $data): array => ['data' => $data],
    ['document_type', 'document_no', 'applicant_name', 'requester_name', 'application_date',
        'title', 'total_amount', 'current_step_name', 'requested_at', 'approval_status']
);
$sorted = $inbox->page((string) $request['requester_id'], 'submitted', [
    'start' => 0,
    'length' => 500,
    'columns' => $sortColumns,
    'order' => [['column' => 8, 'dir' => 'asc']],
]);
$requestedTimes = array_column($sorted['rows'], 'requested_at');
$expectedRequestedTimes = $requestedTimes;
sort($expectedRequestedTimes);
if ($requestedTimes !== $expectedRequestedTimes) {
    throw new RuntimeException('결재함 상신일시 오름차순 정렬이 적용되지 않았습니다.');
}

$detailRequest = $inbox->requestDetail(
    (string) $request['id'],
    (string) $request['requester_id']
);
if (!$detailRequest || (float) $detailRequest['total_amount'] !== $monthlyTotal) {
    throw new RuntimeException('결재요청 상세 projection의 총금액이 월 지급합계와 다릅니다.');
}

$document = (new EmploymentContractApprovalAdapter($pdo))->detail($detailRequest);
if ((float) $document['totals']['total_amount'] !== $monthlyTotal
    || (float) $document['totals']['monthly_total_amount'] !== $monthlyTotal
    || (float) $document['totals']['annualized_amount'] !== round($monthlyTotal * 12, 2)) {
    throw new RuntimeException('근로계약 결재 Adapter의 월 지급합계 또는 연 환산액이 다릅니다.');
}

$orphanCount = (int) $pdo->query(
    'SELECT COUNT(*)
       FROM institution_employment_contracts_components component
       LEFT JOIN institution_employment_contracts contract ON contract.id = component.contract_id
      WHERE contract.id IS NULL'
)->fetchColumn();
if ($orphanCount !== 0) {
    throw new RuntimeException('근로계약 지급조건 고아 데이터가 존재합니다.');
}

echo json_encode([
    'request_id' => $request['id'],
    'contract_id' => $request['document_id'],
    'contract_no' => $request['contract_no'],
    'component_count' => count($components),
    'monthly_total_amount' => $monthlyTotal,
    'annualized_amount' => $document['totals']['annualized_amount'],
    'submitted_total_amount' => (float) $listRow['total_amount'],
    'status_box' => $statusBox,
    'status_box_total_amount' => $statusRow === null ? null : (float) $statusRow['total_amount'],
    'detail_total_amount' => (float) $detailRequest['total_amount'],
    'component_orphans' => $orphanCount,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
