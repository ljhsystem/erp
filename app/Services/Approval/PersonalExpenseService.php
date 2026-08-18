<?php

namespace App\Services\Approval;

use App\Models\Approval\PersonalExpenseItemModel;
use App\Models\Approval\ApprovalInboxModel;
use App\Models\Approval\PersonalExpenseModel;
use App\Models\System\CodeModel;
use App\Models\System\ClientModel;
use App\Models\System\ProjectModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class PersonalExpenseService
{
    private PersonalExpenseModel $headers;
    private PersonalExpenseItemModel $items;
    private EmployeeModel $employees;
    private CodeModel $codes;
    private ClientModel $clients;
    private ProjectModel $projects;
    private ApprovalInboxModel $approvalInbox;

    public function __construct(private readonly PDO $pdo)
    {
        $this->headers = new PersonalExpenseModel($pdo);
        $this->items = new PersonalExpenseItemModel($pdo);
        $this->employees = new EmployeeModel($pdo);
        $this->codes = new CodeModel($pdo);
        $this->clients = new ClientModel($pdo);
        $this->projects = new ProjectModel($pdo);
        $this->approvalInbox = new ApprovalInboxModel($pdo);
    }

    public function formOptions(): array
    {
        return [
            'current_employee' => $this->currentEmployee(),
            'expense_categories' => $this->codes->getActiveCodesByGroup('PERSONAL_EXPENSE_CATEGORY'),
            'payment_methods' => $this->codes->getActiveCodesByGroup('PERSONAL_EXPENSE_PAYMENT_METHOD'),
            'receipt_types' => $this->codes->getActiveCodesByGroup('PERSONAL_EXPENSE_RECEIPT_TYPE'),
            'units' => $this->codes->getActiveCodesByGroup('UNIT'),
        ];
    }

    public function list(array $query = []): array
    {
        $employee = $this->currentEmployee();
        $filters = json_decode((string) ($query['filters'] ?? '[]'), true);
        $page = $this->headers->pageForEmployee((string) $employee['id'], $query, is_array($filters) ? $filters : []);
        $page['rows'] = array_map(
            fn (array $row): array => $this->withApprovalListProjection($row),
            $page['rows']
        );
        return [
            'success' => true, 'data' => $page['rows'], 'draw' => (int) ($query['draw'] ?? 0),
            'recordsTotal' => $page['total'], 'recordsFiltered' => $page['filtered'],
        ];
    }

    public function detail(string $id): array
    {
        $employee = $this->currentEmployee();
        $header = $this->headers->findOwned($id, (string) $employee['id']);
        if (!$header || $header['deleted_at'] !== null) {
            return ['success' => false, 'message' => '개인경비 신청서를 찾을 수 없습니다.'];
        }
        $requestId = trim((string) ($header['latest_request_id'] ?? ''));
        $steps = [];
        $currentRequestAction = null;
        $history = $this->approvalInbox->history('PERSONAL_EXPENSE', $id);
        foreach ($history as &$request) {
            $request['is_current'] = (string) $request['id'] === $requestId;
            $request['steps'] = array_map(
                fn (array $step): array => $this->withApprovalTimelineStepProjection($step),
                $this->approvalInbox->steps((string) $request['id'])
            );
            $request['request_action'] = $this->approvalRequestTerminationProjection($request);
            if ($request['is_current']) {
                $steps = $request['steps'];
                $currentRequestAction = $request['request_action'];
            }
        }
        unset($request);
        return ['success' => true, 'data' => [
            'header' => $header,
            'items' => $this->items->listForHeader($id),
            'approval' => [
                'request_id' => $header['latest_request_id'] ?? null,
                'status' => $header['approval_status'] ?? 'draft',
                'requested_at' => $header['requested_at'] ?? null,
                'completed_at' => $header['completed_at'] ?? null,
                'current_step' => $header['current_step'] ?? null,
                'current_step_name' => $header['current_step_name'] ?? null,
                'rejection_reason' => $header['rejection_reason'] ?? null,
                'rejected_at' => $header['rejected_at'] ?? null,
                'request_action' => $currentRequestAction,
            ],
            'approval_steps' => $steps,
            'approval_history' => $history,
            'actions' => $this->actionsFor($header),
        ]];
    }

    public function save(array $input): array
    {
        $employee = $this->currentEmployee();
        $employeeId = (string) $employee['id'];
        $id = trim((string) ($input['id'] ?? ''));
        $actor = ActorHelper::user();
        $header = $this->validateHeader(is_array($input['header'] ?? null) ? $input['header'] : $input);
        $requestItems = $input['items'] ?? null;
        if (!is_array($requestItems) || $requestItems === []) {
            throw new \InvalidArgumentException('최소 1개 이상의 경비 아이템을 입력해 주세요.');
        }
        $validatedItems = [];
        foreach (array_values($requestItems) as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('경비 아이템 형식이 올바르지 않습니다.');
            }
            $validatedItems[] = $this->validateItem($item);
        }

        $outer = $this->pdo->inTransaction();
        if (!$outer) {
            $this->pdo->beginTransaction();
        }
        try {
            if ($id === '') {
                $this->headers->lockEmployee($employeeId);
                $id = UuidHelper::generate();
                $this->headers->insert($header + [
                    'id' => $id, 'sort_no' => $this->headers->nextSortNo($employeeId),
                    'employee_id' => $employeeId, 'created_by' => $actor, 'updated_by' => $actor,
                ]);
                foreach ($validatedItems as $index => $item) {
                    $this->items->insert(UuidHelper::generate(), $id, $index + 1, $item['data'], $actor);
                }
            } else {
                $current = $this->headers->findOwned($id, $employeeId, true);
                if (!$current || $current['deleted_at'] !== null) {
                    throw new \RuntimeException('개인경비 신청서를 찾을 수 없습니다.');
                }
                $this->assertEditable($current);
                $existingItems = $this->items->listForHeader($id, true);
                $existingIds = array_fill_keys(array_column($existingItems, 'id'), true);
                $keepIds = [];
                $this->headers->update($id, $employeeId, $header + ['updated_by' => $actor]);
                $this->items->reserveSortNumbers($id);
                foreach ($validatedItems as $index => $item) {
                    $itemId = trim((string) ($item['id'] ?? ''));
                    if ($itemId === '') {
                        $itemId = UuidHelper::generate();
                        $this->items->insert($itemId, $id, $index + 1, $item['data'], $actor);
                        $keepIds[] = $itemId;
                        continue;
                    }
                    if (!isset($existingIds[$itemId])) {
                        throw new \RuntimeException('다른 신청서의 아이템은 수정할 수 없습니다.');
                    }
                    $this->items->updateOwned($itemId, $id, $index + 1, $item['data'], $actor);
                    $keepIds[] = $itemId;
                }
                $this->items->softDeleteMissing($id, $keepIds, $actor);
            }
            $this->recalculateHeaderAggregates($id, $actor, true);
            if (!$outer) {
                $this->pdo->commit();
            }
            return ['success' => true, 'data' => ['id' => $id], 'message' => '저장되었습니다.'];
        } catch (\Throwable $exception) {
            if (!$outer && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function assertSubmittable(string $id, string $employeeId): array
    {
        $header = $this->headers->findOwned($id, $employeeId, true);
        if (!$header || $header['deleted_at'] !== null) {
            throw new \RuntimeException('결재 요청할 개인경비 신청서를 찾을 수 없습니다.');
        }
        $items = $this->items->listForHeader($id, true);
        $aggregate = $this->recalculateHeaderAggregates($id, ActorHelper::user(), true);
        $header = $this->headers->findOwned($id, $employeeId, true);
        if (!$header || !$this->aggregateMatches($header, $aggregate)) {
            throw new \RuntimeException('개인경비 신청금액 집계를 확인할 수 없습니다.');
        }
        return ['header' => $header, 'items' => $items];
    }

    public function assertExcelEditable(string $id): void
    {
        $employee = $this->currentEmployee();
        $header = $this->headers->findOwned($id, (string) $employee['id'], true);
        if (!$header || $header['deleted_at'] !== null) {
            throw new \RuntimeException('엑셀 업로드 대상 개인경비 신청서를 찾을 수 없습니다.');
        }
        $this->assertEditable($header);
    }

    public function validateExcelItems(array $rows): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('최소 1개 이상의 경비 아이템을 입력해 주세요.');
        }
        $validated = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException(($index + 2) . '행의 경비 아이템 형식이 올바르지 않습니다.');
            }
            $item = $this->validateItem($row);
            $validated[] = ['id' => '', 'sort_no' => $index + 1] + $item['data'];
        }
        return $validated;
    }

    public function deleteMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($id) => trim((string) $id), $ids))));
        if ($ids === []) {
            throw new \InvalidArgumentException('삭제할 신청서를 선택해 주세요.');
        }
        $employee = $this->currentEmployee();
        $employeeId = (string) $employee['id'];
        $outer = $this->pdo->inTransaction();
        if (!$outer) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($ids as $id) {
                $row = $this->headers->findOwned($id, $employeeId, true);
                if (!$row) {
                    throw new \RuntimeException('삭제할 개인경비 신청서를 찾을 수 없습니다.');
                }
                if ($row['deleted_at'] !== null) {
                    throw new \RuntimeException('이미 휴지통에 있는 개인경비 신청서입니다.');
                }
                $this->assertDeletable($row);
                if ($this->headers->softDelete($id, $employeeId, ActorHelper::user()) !== 1) {
                    throw new \RuntimeException('삭제할 개인경비 신청서를 찾을 수 없습니다.');
                }
            }
            if (!$outer) {
                $this->pdo->commit();
            }
            return ['success' => true, 'data' => ['deleted_count' => count($ids)], 'message' => '삭제되었습니다.'];
        } catch (\Throwable $exception) {
            if (!$outer && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function trashList(): array
    {
        $employee = $this->currentEmployee();
        $rows = $this->headers->trashForEmployee((string) $employee['id']);
        $itemsByHeader = [];
        foreach ($this->items->listForHeaders(array_column($rows, 'id')) as $item) {
            $itemsByHeader[(string) $item['personal_expense_id']][] = $item;
        }
        foreach ($rows as &$row) {
            $row['items'] = $itemsByHeader[(string) $row['id']] ?? [];
        }
        unset($row);
        return ['success' => true, 'data' => $rows];
    }

    public function restore(string $id): array
    {
        $employee = $this->currentEmployee();
        $employeeId = (string) $employee['id'];
        return $this->transaction(function () use ($id, $employeeId) {
            $this->restoreOne($id, $employeeId);
            return ['success' => true, 'data' => ['restored_count' => 1], 'message' => '복원되었습니다.'];
        });
    }

    public function restoreMany(array $ids): array
    {
        $ids = $this->normalizeIds($ids, '복원할 신청서를 선택해 주세요.');
        $employeeId = (string) $this->currentEmployee()['id'];
        return $this->transaction(function () use ($ids, $employeeId) {
            foreach ($ids as $id) {
                $this->restoreOne($id, $employeeId);
            }
            return ['success' => true, 'data' => ['restored_count' => count($ids)], 'message' => '선택한 신청서를 복원했습니다.'];
        });
    }

    public function restoreAll(): array
    {
        $employeeId = (string) $this->currentEmployee()['id'];
        $ids = array_column($this->headers->trashForEmployee($employeeId), 'id');
        if ($ids === []) {
            return ['success' => true, 'data' => ['restored_count' => 0], 'message' => '복원할 신청서가 없습니다.'];
        }
        return $this->restoreMany($ids);
    }

    public function purge(string $id): array
    {
        $employeeId = (string) $this->currentEmployee()['id'];
        return $this->transaction(function () use ($id, $employeeId) {
            $this->purgeOne($id, $employeeId);
            return ['success' => true, 'data' => ['deleted_count' => 1], 'message' => '완전삭제되었습니다.'];
        });
    }

    public function purgeMany(array $ids): array
    {
        $ids = $this->normalizeIds($ids, '완전삭제할 신청서를 선택해 주세요.');
        $employeeId = (string) $this->currentEmployee()['id'];
        return $this->transaction(function () use ($ids, $employeeId) {
            foreach ($ids as $id) {
                $this->purgeOne($id, $employeeId);
            }
            return ['success' => true, 'data' => ['deleted_count' => count($ids)], 'message' => '선택한 신청서를 완전삭제했습니다.'];
        });
    }

    public function purgeAll(): array
    {
        $employeeId = (string) $this->currentEmployee()['id'];
        $ids = array_column($this->headers->trashForEmployee($employeeId), 'id');
        if ($ids === []) {
            return ['success' => true, 'data' => ['deleted_count' => 0], 'message' => '완전삭제할 신청서가 없습니다.'];
        }
        return $this->purgeMany($ids);
    }

    public function reorder(array $changes): array
    {
        if ($changes === []) {
            throw new \InvalidArgumentException('정렬 데이터가 없습니다.');
        }
        $employee = $this->currentEmployee();
        $employeeId = (string) $employee['id'];
        $normalized = [];
        foreach ($changes as $change) {
            $id = trim((string) ($change['id'] ?? ''));
            $sortNo = (int) ($change['newSortNo'] ?? $change['sort_no'] ?? 0);
            if ($id === '' || $sortNo < 1 || !$this->headers->findOwned($id, $employeeId)) {
                throw new \InvalidArgumentException('정렬 저장 대상 또는 순번이 올바르지 않습니다.');
            }
            $normalized[] = ['id' => $id, 'sort_no' => $sortNo];
        }

        $outer = $this->pdo->inTransaction();
        if (!$outer) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($normalized as $row) {
                $this->headers->updateSortNo($row['id'], $employeeId, $row['sort_no'] + 1000000);
            }
            foreach ($normalized as $row) {
                $this->headers->updateSortNo($row['id'], $employeeId, $row['sort_no']);
            }
            if (!$outer) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => '순서가 변경되었습니다.'];
        } catch (\Throwable $exception) {
            if (!$outer && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function currentEmployee(): array
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $userId = trim((string) ($actor['id'] ?? ''));
        if ($userId === '') {
            throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');
        }
        $employee = $this->employees->findByUserId($userId);
        if (!$employee) {
            throw new \RuntimeException('로그인 사용자와 연결된 직원 정보가 없습니다.');
        }
        return $employee;
    }

    private function validateHeader(array $input): array
    {
        $applicationDate = trim((string) ($input['application_date'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        if (!$this->validDate($applicationDate)) {
            throw new \InvalidArgumentException('신청일자를 확인해 주세요.');
        }
        if ($title === '') {
            throw new \InvalidArgumentException('신청제목을 입력해 주세요.');
        }
        return [
            'application_date' => $applicationDate,
            'title' => mb_substr($title, 0, 200),
            'description' => $this->nullable($input['description'] ?? null),
            'memo' => $this->nullable($input['memo'] ?? null),
        ];
    }

    private function validateItem(array $input): array
    {
        foreach (['expense_date' => '지출일자', 'payment_method' => '지출수단', 'merchant_name' => '가맹점명', 'item_name' => '품명'] as $key => $label) {
            if (trim((string) ($input[$key] ?? '')) === '') {
                throw new \InvalidArgumentException($label . '을(를) 입력해 주세요.');
            }
        }
        if (!$this->validDate((string) $input['expense_date'])) {
            throw new \InvalidArgumentException('지출일자를 확인해 주세요.');
        }
        $quantity = $this->number($input['item_quantity'] ?? 1);
        $unitPrice = $this->number($input['item_unit_price'] ?? 0);
        $inputSupply = $this->number($input['item_supply_amount'] ?? 0);
        $vat = $this->number($input['item_vat_amount'] ?? 0);
        $unitName = trim((string) ($input['item_unit_name'] ?? ''));
        $manualAmountEntry = $unitName === '' && $quantity === 0.0 && $unitPrice === 0.0;
        if ((!$manualAmountEntry && $quantity <= 0) || $unitPrice < 0 || $inputSupply < 0 || $vat < 0) {
            throw new \InvalidArgumentException('수량과 금액을 확인해 주세요.');
        }
        $supply = $manualAmountEntry
            ? round($inputSupply, 2)
            : round($quantity * $unitPrice, 2);
        $total = round($supply + $vat, 2);
        if ($total <= 0) {
            throw new \InvalidArgumentException('합계금액은 0보다 커야 합니다.');
        }
        $businessNo = preg_replace('/\D/', '', (string) ($input['merchant_business_no'] ?? '')) ?? '';
        if ($businessNo !== '' && strlen($businessNo) !== 10) {
            throw new \InvalidArgumentException('사업자등록번호는 숫자 10자리로 입력해 주세요.');
        }
        $expenseCategory = $this->resolveOptionalCode('PERSONAL_EXPENSE_CATEGORY', $input['expense_category'] ?? null, '경비구분');
        $paymentMethod = $this->resolveCode('PERSONAL_EXPENSE_PAYMENT_METHOD', (string) $input['payment_method'], '지출수단');
        $receiptType = $this->resolveOptionalCode('PERSONAL_EXPENSE_RECEIPT_TYPE', $input['receipt_type'] ?? null, '증빙종류');
        $projectId = $this->nullable($input['project_id'] ?? null);
        if ($projectId !== null) {
            $project = $this->projects->getById($projectId);
            if (!$project || !empty($project['deleted_at']) || (isset($project['is_active']) && (int) $project['is_active'] !== 1)) {
                throw new \InvalidArgumentException('프로젝트는 현재 사용 중인 항목을 선택해 주세요.');
            }
        }
        $clientId = $this->nullable($input['client_id'] ?? null);
        if ($clientId !== null) {
            $client = $this->clients->getById($clientId);
            if (!$client || !empty($client['deleted_at']) || (isset($client['is_active']) && (int) $client['is_active'] !== 1)) {
                throw new \InvalidArgumentException('거래처는 현재 사용 중인 항목을 선택해 주세요.');
            }
            $selectedName = trim((string) ($client['company_name'] ?? ''));
            if ($selectedName === '') {
                $selectedName = trim((string) ($client['client_name'] ?? ''));
            }
            $merchantName = trim((string) $input['merchant_name']);
            if ($selectedName !== '' && $merchantName !== $selectedName) {
                throw new \InvalidArgumentException('선택한 거래처와 가맹점명이 일치하지 않습니다.');
            }
            $selectedBusinessNo = preg_replace('/\D/', '', (string) ($client['business_number'] ?? '')) ?? '';
            if ($businessNo !== '' && $selectedBusinessNo !== '' && $businessNo !== $selectedBusinessNo) {
                throw new \InvalidArgumentException('선택한 거래처와 사업자등록번호가 일치하지 않습니다.');
            }
        }
        return [
            'id' => trim((string) ($input['id'] ?? '')),
            'data' => [
                'expense_date' => trim((string) $input['expense_date']),
                'expense_category' => $expenseCategory, 'payment_method' => $paymentMethod,
                'receipt_type' => $receiptType, 'merchant_name' => trim((string) $input['merchant_name']),
                'merchant_business_no' => $this->nullable($businessNo),
                'merchant_representative' => $this->nullable($input['merchant_representative'] ?? null),
                'merchant_address' => $this->nullable($input['merchant_address'] ?? null),
                'merchant_address_detail' => $this->nullable($input['merchant_address_detail'] ?? null),
                'merchant_phone' => $this->nullable($input['merchant_phone'] ?? null),
                'project_id' => $projectId, 'client_id' => $clientId,
                'item_name' => trim((string) $input['item_name']),
                'item_specification' => $this->nullable($input['item_specification'] ?? null),
                'item_unit_name' => $this->nullable($unitName),
                'item_quantity' => $quantity, 'item_unit_price' => $unitPrice,
                'item_supply_amount' => $supply, 'item_vat_amount' => $vat,
                'item_total_amount' => $total,
                'item_description' => $this->nullable($input['item_description'] ?? null),
                'item_memo' => $this->nullable($input['item_memo'] ?? null),
            ],
        ];
    }

    private function actionsFor(array $header): array
    {
        $status = strtolower((string) ($header['approval_status'] ?? 'draft'));
        $editable = in_array($status, ['draft', 'rejected', 'withdrawn'], true);
        return [
            'can_edit' => $editable, 'can_delete' => $editable,
            'can_submit' => $editable, 'can_withdraw' => in_array($status, ['pending', 'in_progress'], true),
        ];
    }

    private function assertEditable(array $row): void
    {
        if (!in_array(strtolower((string) ($row['approval_status'] ?? 'draft')), ['draft', 'rejected', 'withdrawn'], true)) {
            throw new \RuntimeException('결재 진행 중이거나 승인된 신청서는 수정·삭제할 수 없습니다.');
        }
    }

    private function assertDeletable(array $row): void
    {
        $status = strtolower((string) ($row['approval_status'] ?? 'draft'));
        if ($status === 'approved' || strtoupper((string) ($row['document_status'] ?? '')) === 'APPROVED') {
            throw new \RuntimeException('최종 결재가 완료된 개인경비 신청서는 삭제할 수 없습니다.');
        }
        if (in_array($status, ['pending', 'in_progress'], true)) {
            throw new \RuntimeException('결재 진행 중인 개인경비 신청서는 삭제할 수 없습니다.');
        }
        if (!in_array($status, ['draft', 'rejected', 'withdrawn'], true)) {
            throw new \RuntimeException('현재 상태에서는 개인경비 신청서를 삭제할 수 없습니다.');
        }
    }

    private function restoreOne(string $id, string $employeeId): void
    {
        $row = $this->headers->findOwned($id, $employeeId, true);
        if (!$row || $row['deleted_at'] === null) {
            throw new \RuntimeException('복원할 개인경비 신청서를 찾을 수 없습니다.');
        }
        $this->assertDeletable($row);
        if ($this->items->listForHeader($id, true) === []) {
            throw new \RuntimeException('복원할 신청서의 개인경비 아이템을 찾을 수 없습니다.');
        }
        $actor = ActorHelper::user();
        if ($this->headers->restore($id, $employeeId, $actor) !== 1) {
            throw new \RuntimeException('복원할 개인경비 신청서를 찾을 수 없습니다.');
        }
        $this->recalculateHeaderAggregates($id, $actor, true);
    }

    private function purgeOne(string $id, string $employeeId): void
    {
        $row = $this->headers->findOwned($id, $employeeId, true);
        if (!$row || $row['deleted_at'] === null) {
            throw new \RuntimeException('휴지통에서 완전삭제할 개인경비 신청서를 찾을 수 없습니다.');
        }
        $this->assertDeletable($row);
        $historyStatuses = array_map('strtolower', $this->headers->approvalHistoryStatuses($id));
        if (array_intersect($historyStatuses, ['pending', 'in_progress', 'approved'])) {
            throw new \RuntimeException('진행 중이거나 최종 승인된 결재이력이 있는 신청서는 완전삭제할 수 없습니다.');
        }
        if ($this->items->evidenceReferenceCountForHeader($id) > 0) {
            throw new \RuntimeException('회계 증빙원본과 연결된 개인경비 신청서는 완전삭제할 수 없습니다.');
        }
        if ($this->items->purgeForHeader($id) < 1) {
            throw new \RuntimeException('완전삭제할 개인경비 아이템을 찾을 수 없습니다.');
        }
        if ($this->headers->purge($id, $employeeId) !== 1) {
            throw new \RuntimeException('완전삭제할 개인경비 신청서를 찾을 수 없습니다.');
        }
    }

    private function recalculateHeaderAggregates(string $id, string $actor, bool $requireItems): array
    {
        $aggregate = $this->items->activeAggregate($id);
        if ($requireItems && $aggregate['item_count'] < 1) {
            throw new \RuntimeException('최소 1개 이상의 경비 아이템이 필요합니다.');
        }
        $this->headers->updateAggregates($id, $aggregate, $actor);
        return $aggregate;
    }

    private function aggregateMatches(array $header, array $aggregate): bool
    {
        return (int) ($header['item_count'] ?? -1) === $aggregate['item_count']
            && $this->decimalEquals($header['supply_amount'] ?? null, $aggregate['supply_amount'])
            && $this->decimalEquals($header['vat_amount'] ?? null, $aggregate['vat_amount'])
            && $this->decimalEquals($header['total_amount'] ?? null, $aggregate['total_amount']);
    }

    private function decimalEquals(mixed $left, mixed $right): bool
    {
        $normalize = static function (mixed $value): string {
            $raw = trim((string) $value);
            if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $raw, $matches)) {
                return '';
            }
            $integer = ltrim($matches[2], '0');
            $integer = $integer === '' ? '0' : $integer;
            $fraction = substr(str_pad($matches[3] ?? '', 2, '0'), 0, 2);
            $sign = ($matches[1] ?? '') === '-' && ($integer !== '0' || $fraction !== '00') ? '-' : '';
            return $sign . $integer . '.' . $fraction;
        };

        return $normalize($left) === $normalize($right);
    }

    private function withApprovalListProjection(array $row): array
    {
        $status = strtolower(trim((string) ($row['approval_status'] ?? 'draft')));
        $stageName = trim((string) ($row['current_step_name'] ?? ''));
        $actorName = '-';
        $actorType = 'NONE';
        $actionResult = '미상신';
        $stageLabel = '-';

        if (in_array($status, ['pending', 'in_progress'], true)) {
            $assignedApprover = trim((string) ($row['assigned_approver_name'] ?? ''));
            $assignedRole = trim((string) ($row['assigned_role_name'] ?? ''));
            $actorName = $assignedApprover !== '' ? $assignedApprover : ($assignedRole !== '' ? $assignedRole : '미지정');
            $actorType = 'ASSIGNED';
            $stageLabel = $stageName !== '' ? $stageName : '결재 대기';
            $actionResult = $stageName !== '' ? $stageName . ' 대기' : '결재 대기';
        } elseif (in_array($status, ['approved', 'rejected'], true)) {
            $lastActionStatus = strtolower(trim((string) ($row['last_action_status'] ?? '')));
            $lastActorName = trim((string) ($row['last_action_actor_name'] ?? ''));
            $lastActionAt = trim((string) ($row['last_action_at'] ?? ''));
            $isMatchingAction = $lastActionStatus === $status;
            $actorName = $isMatchingAction && $lastActorName !== '' ? $lastActorName : '-';
            $actorType = $isMatchingAction && $lastActorName !== '' ? 'ACTED' : 'NONE';
            $label = $status === 'approved' ? '승인' : '반려';
            $actionResult = $label . ($isMatchingAction && $lastActionAt !== '' ? ' · ' . $lastActionAt : '');
        } elseif ($status === 'withdrawn') {
            $withdrawnActorName = trim((string) ($row['withdrawn_actor_name'] ?? ''));
            $withdrawnAt = trim((string) ($row['withdrawn_at'] ?? ''));
            $actorName = $withdrawnActorName !== '' ? $withdrawnActorName : '-';
            $actorType = $withdrawnActorName !== '' ? 'WITHDRAWN' : 'NONE';
            $actionResult = '회수' . ($withdrawnAt !== '' ? ' · ' . $withdrawnAt : '');
        } elseif ($status === 'cancelled') {
            $cancelledActorName = trim((string) ($row['cancelled_actor_name'] ?? ''));
            $cancelledAt = trim((string) ($row['cancelled_at'] ?? ''));
            $actorName = $cancelledActorName !== '' ? $cancelledActorName : '-';
            $actorType = $cancelledActorName !== '' ? 'CANCELLED' : 'NONE';
            $actionResult = '취소' . ($cancelledAt !== '' ? ' · ' . $cancelledAt : '');
        }

        $row['approval_actor_name'] = $actorName;
        $row['approval_actor_type'] = $actorType;
        $row['approval_action_result'] = $actionResult;
        $row['approval_stage_name'] = $stageLabel;
        return $row;
    }

    private function withApprovalTimelineStepProjection(array $step): array
    {
        $status = strtolower(trim((string) ($step['status'] ?? '')));
        $approverName = trim((string) ($step['approver_name'] ?? ''));
        $roleName = trim((string) ($step['approver_role_name'] ?? ''));
        $actedByName = trim((string) ($step['acted_by_name'] ?? ''));
        $actionAt = trim((string) ($step['action_at'] ?? ''));

        $step['timeline_user_name'] = '-';
        $step['timeline_user_label'] = '처리자';
        $step['timeline_action_at'] = null;
        $step['timeline_result'] = match ($status) {
            'approved' => strtoupper(trim((string) ($step['step_type'] ?? ''))) === 'SUBMIT' ? '발의 완료' : '승인',
            'rejected' => '반려',
            'cancelled', 'withdrawn' => '배정 취소',
            'pending' => '결재 진행중',
            default => '대기',
        };

        if (in_array($status, ['waiting', 'pending'], true)) {
            if ($approverName !== '') {
                $step['timeline_user_name'] = $approverName;
                $step['timeline_user_label'] = '예정 결재자';
            } elseif ($roleName !== '') {
                $step['timeline_user_name'] = $roleName;
                $step['timeline_user_label'] = '결재 대상 역할';
            } else {
                $step['timeline_user_label'] = '예정 결재자';
            }
            return $step;
        }

        if (in_array($status, ['approved', 'rejected'], true)) {
            $step['timeline_user_name'] = $actedByName !== '' ? $actedByName : '-';
            $step['timeline_action_at'] = $actionAt !== '' ? $actionAt : null;
        }

        return $step;
    }

    private function approvalRequestTerminationProjection(array $request): ?array
    {
        $status = strtolower(trim((string) ($request['status'] ?? '')));
        if ($status === 'withdrawn') {
            return [
                'result' => '회수',
                'actor_name' => trim((string) ($request['withdrawn_by_name'] ?? '')) ?: '-',
                'acted_at' => trim((string) ($request['withdrawn_at'] ?? '')) ?: null,
            ];
        }
        if ($status === 'cancelled') {
            return [
                'result' => '취소',
                'actor_name' => trim((string) ($request['cancelled_by_name'] ?? '')) ?: '-',
                'acted_at' => trim((string) ($request['cancelled_at'] ?? '')) ?: null,
            ];
        }
        return null;
    }

    private function normalizeIds(array $ids, string $emptyMessage): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn($id): string => trim((string) $id), $ids))));
        if ($ids === []) {
            throw new \InvalidArgumentException($emptyMessage);
        }
        return $ids;
    }

    private function transaction(callable $callback): array
    {
        $outer = $this->pdo->inTransaction();
        if (!$outer) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $callback();
            if (!$outer) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $exception) {
            if (!$outer && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function resolveCode(string $group, string $value, string $label): string
    {
        $value = trim($value);
        $code = $this->codes->resolveActiveCode($group, $value, $value);
        if ($code === null) {
            throw new \InvalidArgumentException($label . '은(는) 코드관리의 활성 항목을 선택해 주세요.');
        }
        return $code;
    }

    private function resolveOptionalCode(string $group, mixed $value, string $label): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $this->resolveCode($group, $value, $label);
    }

    private function number(mixed $value): float
    {
        $value = str_replace(',', '', trim((string) $value));
        if ($value === '') {
            return 0.0;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('금액 형식이 올바르지 않습니다.');
        }
        return (float) $value;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $date !== false && $date->format('Y-m-d') === trim($value);
    }
}
