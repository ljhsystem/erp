<?php
namespace App\Services\Approval;

use App\Models\Approval\PersonalExpenseModel;
use App\Models\Approval\PersonalExpenseItemModel;
use App\Models\Ledger\EmployeePersonalExpenseEvidenceModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Models\System\CodeModel;
use App\Models\User\ApprovalRequestModel;
use App\Models\User\ApprovalRequestStepModel;
use App\Models\User\ApprovalTemplateModel;
use App\Models\User\ApprovalTemplateStepModel;
use App\Models\User\EmployeeModel;
use App\Services\System\NotificationService;
use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Services\Ledger\EvidenceClientSyncService;
use App\Services\Ledger\EvidenceExternalKeyService;
use App\Services\Ledger\TransactionCrudService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class PersonalExpenseApprovalService
{
    private const DOCUMENT_TYPE = 'PERSONAL_EXPENSE';
    private const IMPORT_TYPE = 'EMPLOYEE_EXPENSE_PERSONAL';
    private ApprovalRequestModel $requests;
    private ApprovalRequestStepModel $steps;
    private ApprovalTemplateModel $templates;
    private ApprovalTemplateStepModel $templateSteps;
    private EmployeeModel $employees;
    private NotificationService $notifications;
    private PersonalExpenseModel $expenses;
    private PersonalExpenseItemModel $expenseItems;
    private EmployeePersonalExpenseEvidenceModel $evidences;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->requests = new ApprovalRequestModel($pdo);
        $this->steps = new ApprovalRequestStepModel($pdo);
        $this->templates = new ApprovalTemplateModel($pdo);
        $this->templateSteps = new ApprovalTemplateStepModel($pdo);
        $this->employees = new EmployeeModel($pdo);
        $this->notifications = new NotificationService($pdo);
        $this->expenses = new PersonalExpenseModel($pdo);
        $this->expenseItems = new PersonalExpenseItemModel($pdo);
        $this->evidences = new EmployeePersonalExpenseEvidenceModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval-personal-expense');
    }

    public function submit(string $documentId): array
    {
        [$userId, $employeeId, $actor] = $this->identity();
        return $this->transaction(function () use ($documentId, $userId, $employeeId, $actor) {
            $document = (new PersonalExpenseService($this->pdo))->assertSubmittable($documentId, $employeeId);
            $expense = $document['header'];
            $latest = $this->requests->latestForDocument(self::DOCUMENT_TYPE, $documentId, true);
            if ($latest && !in_array((string) $latest['status'], ['rejected', 'withdrawn'], true)) throw new \RuntimeException('이미 결재 진행 중이거나 승인된 신청입니다.');
            if (!$latest && (
                trim((string) ($expense['current_approval_request_id'] ?? '')) !== ''
                || strtoupper((string) ($expense['document_status'] ?? 'DRAFT')) !== 'DRAFT'
            )) {
                $this->expenses->updateWorkflow($documentId, 'DRAFT', null, $actor);
            }
            $template = $this->templates->findActiveByDocumentType(self::DOCUMENT_TYPE, true);
            if (!$template) throw new \RuntimeException('활성 개인경비 결재템플릿이 없습니다. 관리자에게 문의해 주세요.');
            $this->templateSteps->lockTemplate((string) $template['id']);
            $this->templateSteps->normalizeActiveExecutionTypes((string) $template['id'], $actor);
            $sourceSteps = $this->templateSteps->getActiveSteps((string) $template['id']);
            if ($sourceSteps === []) throw new \RuntimeException('개인경비 결재단계가 설정되지 않았습니다.');
            $resolved = [];
            $firstApprovalSortNo = null;
            foreach ($sourceSteps as $index => $source) {
                $stepType = strtoupper(trim((string) ($source['step_type'] ?? 'APPROVAL')));
                $roleId = trim((string) ($source['role_id'] ?? ''));
                $approverId = trim((string) ($source['approver_id'] ?? ''));
                $stepName = (string) ($source['step_name'] ?? (($index + 1) . '단계'));
                if (!in_array($stepType, ['SUBMIT', 'APPROVAL', 'FINAL_APPROVAL'], true)) {
                    throw new \RuntimeException($stepName . '의 단계유형이 올바르지 않습니다.');
                }
                if ($stepType === 'SUBMIT') {
                    $resolved[] = [
                        'sort_no' => $index + 1, 'step_name' => $stepName, 'step_type' => $stepType,
                        'role_id' => $roleId ?: null, 'approver_id' => null,
                        'acted_by' => $userId, 'action_at' => date('Y-m-d H:i:s'), 'status' => 'approved',
                    ];
                    continue;
                }
                if ($approverId === '' && $roleId === '') {
                    throw new \RuntimeException($stepName . '의 결재 역할 또는 특정 결재자를 설정해 주세요.');
                }
                if ($approverId !== '' && $roleId !== '' && !$this->employees->userIsEligibleForRole($approverId, $roleId)) {
                    throw new \RuntimeException($stepName . '의 지정 결재자가 역할 조건을 충족하지 않습니다.');
                }
                if ($approverId !== '' && $roleId === '') {
                    $eligibility = $this->employees->userEligibility($approverId);
                    if (!($eligibility['eligible'] ?? false)) {
                        throw new \RuntimeException($stepName . ': ' . ($eligibility['message'] ?? '지정 결재자가 적격하지 않습니다.'));
                    }
                }
                if ($approverId === '' && !$this->employees->hasEligibleUserForRole($roleId)) {
                    throw new \RuntimeException($stepName . '을 처리할 수 있는 적격 역할 사용자가 없습니다.');
                }
                $firstApprovalSortNo ??= $index + 1;
                $resolved[] = [
                    'sort_no' => $index + 1, 'step_name' => $stepName, 'step_type' => $stepType,
                    'role_id' => $roleId ?: null, 'approver_id' => $approverId ?: null,
                    'acted_by' => null, 'action_at' => null,
                    'status' => $firstApprovalSortNo === $index + 1 ? 'pending' : 'waiting',
                ];
            }
            if ($firstApprovalSortNo === null) throw new \RuntimeException('실제 승인단계가 설정되지 않았습니다.');
            $requestId = UuidHelper::generate();
            $this->requests->create(['id' => $requestId, 'sort_no' => $this->requests->nextSortNo(), 'template_id' => $template['id'], 'document_type' => self::DOCUMENT_TYPE, 'document_id' => $documentId, 'requester_id' => $userId, 'status' => 'pending', 'current_step' => $firstApprovalSortNo, 'is_active' => 1, 'created_by' => $actor]);
            foreach ($resolved as $step) $this->steps->create($step + ['id' => UuidHelper::generate(), 'request_id' => $requestId, 'created_by' => $actor]);
            $this->expenses->updateWorkflow($documentId, 'PENDING', $requestId, $actor);
            $this->notifyRequester($userId, $userId, $requestId, 'APPROVAL_SUBMITTED', '개인경비 결재요청 상신', '개인경비 신청서가 결재 요청되었습니다.');
            return ['success' => true, 'data' => ['request_id' => $requestId], 'message' => '결재를 요청했습니다.'];
        });
    }

    public function saveAndSubmit(array $input): array
    {
        return $this->transaction(function () use ($input) {
            $saved = (new PersonalExpenseService($this->pdo))->save($input);
            $id = trim((string) ($saved['data']['id'] ?? ''));
            if ($id === '') throw new \RuntimeException('저장된 신청내역을 확인할 수 없습니다.');
            return $this->submit($id);
        });
    }

    public function act(string $stepId, string $decision, ?string $comment = null): array
    {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new \InvalidArgumentException('결재 처리구분이 올바르지 않습니다.');
        if ($decision === 'rejected' && trim((string) $comment) === '') throw new \InvalidArgumentException('반려사유를 입력해 주세요.');
        [$userId, , $actor] = $this->identity();
        return $this->transaction(function () use ($stepId, $decision, $comment, $userId, $actor) {
            $step = $this->steps->getById($stepId, true);
            if (!$step) throw new \RuntimeException('결재단계를 찾을 수 없습니다.');
            $request = $this->requests->getById((string) $step['request_id'], true);
            if (!$request || (string) $request['document_type'] !== self::DOCUMENT_TYPE) throw new \RuntimeException('개인경비 결재요청이 아닙니다.');
            if (!in_array((string) $request['status'], ['pending', 'in_progress'], true)) throw new \RuntimeException('처리할 수 있는 결재요청이 아닙니다.');
            if (!in_array(strtoupper((string) ($step['step_type'] ?? '')), ['APPROVAL', 'FINAL_APPROVAL'], true)) {
                throw new \RuntimeException('발의 단계는 승인 또는 반려할 수 없습니다.');
            }
            if ((int) $request['current_step'] !== (int) $step['sort_no'] || $step['status'] !== 'pending') throw new \RuntimeException('현재 처리할 결재단계가 아닙니다.');
            $requestSteps = $this->steps->getSteps((string) $request['id']);
            foreach ($requestSteps as $previousStep) {
                if ((int) $previousStep['sort_no'] >= (int) $step['sort_no']) break;
                if ((string) $previousStep['status'] !== 'approved') throw new \RuntimeException('이전 결재단계가 완료되지 않았습니다.');
            }
            $approverId = trim((string) ($step['approver_id'] ?? ''));
            $roleId = trim((string) ($step['role_id'] ?? ''));
            if ($approverId !== '' && $approverId !== $userId) {
                throw new \RuntimeException('배정된 결재자만 처리할 수 있습니다.');
            }
            if ($approverId === '' && ($roleId === '' || !$this->employees->userIsEligibleForRole($userId, $roleId))) {
                throw new \RuntimeException('현재 역할로 처리할 수 있는 결재단계가 아닙니다.');
            }
            if (!$this->steps->act($stepId, $decision, $comment, $userId, $roleId ?: null, $actor)) {
                throw new \RuntimeException('이미 처리되었거나 처리 권한이 없는 결재단계입니다.');
            }
            if ($decision === 'rejected') {
                $this->steps->cancelRemaining((string) $request['id'], $actor);
                $this->requests->updateStatus((string) $request['id'], 'rejected', $actor);
                $this->expenses->updateWorkflow((string) $request['document_id'], 'REJECTED', (string) $request['id'], $actor);
                $this->notifyRequester((string) $request['requester_id'], $userId, (string) $request['id'], 'APPROVAL_REJECTED', '개인경비 결재 반려', '개인경비 신청서가 반려되었습니다.');
                return ['success' => true, 'message' => '반려했습니다.'];
            }
            foreach ($requestSteps as $next) {
                if ((int) $next['sort_no'] > (int) $step['sort_no'] && $next['status'] === 'waiting') {
                    if (!$this->steps->activate((string) $request['id'], (int) $next['sort_no'], $actor)) throw new \RuntimeException('다음 결재단계를 활성화하지 못했습니다.');
                    $this->requests->updateCurrentStep((string) $request['id'], (int) $next['sort_no'], $actor);
                    $this->expenses->updateWorkflow((string) $request['document_id'], 'IN_PROGRESS', (string) $request['id'], $actor);
                    return ['success' => true, 'message' => '승인했습니다.'];
                }
            }
            $result = $this->finalize($request, $actor);
            $this->requests->updateStatus((string) $request['id'], 'approved', $actor);
            $this->expenses->updateWorkflow((string) $request['document_id'], 'APPROVED', (string) $request['id'], $actor);
            $this->notifyRequester((string) $request['requester_id'], $userId, (string) $request['id'], 'APPROVAL_APPROVED', '개인경비 최종 승인', '개인경비 신청서가 최종 승인되었습니다.');
            return ['success' => true, 'data' => $result, 'message' => '최종 승인과 회계처리를 완료했습니다.'];
        });
    }

    public function withdraw(string $requestId): array
    {
        [$userId, , $actor] = $this->identity();
        return $this->transaction(function () use ($requestId, $userId, $actor) {
            $request = $this->requests->getById($requestId, true);
            if (!$request || $request['document_type'] !== self::DOCUMENT_TYPE || $request['requester_id'] !== $userId) throw new \RuntimeException('회수할 결재요청을 찾을 수 없습니다.');
            if (!$this->requests->withdraw($requestId, $userId, $actor)) throw new \RuntimeException('현재 상태에서는 기안을 회수할 수 없습니다.');
            $this->steps->cancelRemaining($requestId, $actor);
            $this->expenses->updateWorkflow((string) $request['document_id'], 'WITHDRAWN', $requestId, $actor);
            return ['success' => true, 'message' => '기안을 회수했습니다.'];
        });
    }

    private function finalize(array $request, string $actor): array
    {
        $codes = new CodeModel($this->pdo);
        foreach (['SOURCE_TYPE' => 'APPROVAL', 'IMPORT_TYPE' => self::IMPORT_TYPE, 'OPERATION_TYPE' => 'PERSONAL_EXPENSE', 'TRANSACTION_DIRECTION' => 'EXPENSE', 'BUSINESS_UNIT' => 'HQ'] as $group => $code) {
            if (trim((string) ($codes->findActiveName($group, $code) ?? '')) === '') throw new \RuntimeException($group . ' / ' . $code . ' 운영 코드가 활성화되어 있지 않습니다.');
        }
        $sourceId = (string) $request['document_id'];
        $metadata = (new EvidenceSourceRepository($this->pdo))->metadata(self::IMPORT_TYPE);
        if (!$metadata || (string) $metadata['source_table'] !== 'ledger_evidence_employee_personal_expense' || (string) $metadata['evidence_type'] !== 'DATA') throw new \RuntimeException('직원경비(개인) 증빙정책이 등록되지 않았거나 올바르지 않습니다.');
        $document = (new PersonalExpenseService($this->pdo))->assertSubmittable(
            $sourceId,
            $this->employeeIdForUser((string) $request['requester_id'])
        );
        $expense = $document['header'];
        $items = $document['items'];
        $status = 'COMPLETED';
        $evidenceIds = [];
        $transactionIds = [];
        $transactionItemCount = 0;
        $vatSettlementCount = 0;
        $generatedTotal = 0.0;
        $externalKeys = new EvidenceExternalKeyService();
        $evidenceLinks = new EvidenceLinkModel($this->pdo);
        $clientSync = new EvidenceClientSyncService($this->pdo, [
            'normalizeBusinessNumber' => static fn(string $value): string => preg_replace('/\D/', '', $value) ?? '',
            'cleanCompanyName' => static fn(string $value): string => trim(preg_replace('/\s+/u', ' ', $value) ?? $value),
            'normalizeCompanyNameForCompare' => static fn(string $value): string => mb_strtolower(preg_replace('/[\s().,\-]+/u', '', trim($value)) ?? trim($value)),
            'payloadScalarForStorage' => static fn(mixed $value): mixed => is_scalar($value) ? $value : null,
            'isUuid' => static fn(string $value): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
        ]);
        foreach ($items as $item) {
            $existing = $this->evidences->findBySourceItemId((string) $item['id'], true);
            if ($existing) {
                $evidenceId = (string) $existing['id'];
                $existingClientId = trim((string) ($existing['client_id'] ?? ''));
                $clientId = $existingClientId !== '' ? $existingClientId : $this->resolveMerchantClient($item, $clientSync);
            } else {
                $clientId = $this->resolveMerchantClient($item, $clientSync);
                $evidenceId = UuidHelper::generate();
                $snapshot = [
                    'source_document_id' => $sourceId,
                    'source_item_id' => (string) $item['id'],
                    'approval_request_id' => (string) $request['id'],
                    'raw_application_date' => $expense['application_date'],
                    'raw_project_id' => $item['project_id'],
                    'raw_client_id' => $item['client_id'],
                    'raw_expense_date' => $item['expense_date'],
                    'raw_expense_category' => $item['expense_category'],
                    'raw_payment_method' => $item['payment_method'],
                    'raw_receipt_type' => $item['receipt_type'],
                    'raw_merchant_company_name' => $item['merchant_name'],
                    'raw_item_name' => $item['item_name'],
                    'raw_quantity' => $item['item_quantity'],
                    'raw_unit_price' => $item['item_unit_price'],
                    'raw_supply_amount' => $item['item_supply_amount'],
                    'raw_vat_amount' => $item['item_vat_amount'],
                    'raw_total_amount' => $item['item_total_amount'],
                ];
                $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $this->evidences->insert([
                    'id'=>$evidenceId,'sort_no'=>$this->evidences->nextSortNo(),
                    'external_key'=>$externalKeys->key(['approval_item_id'=>(string)$item['id']], self::IMPORT_TYPE),'source_type'=>'APPROVAL',
                    'import_type'=>self::IMPORT_TYPE,'source_personal_expense_item_id'=>$item['id'],
                    'source_document_id'=>$sourceId,'source_item_id'=>$item['id'],
                    'business_key_hash'=>hash('sha256', self::IMPORT_TYPE . '|' . $sourceId . '|' . $item['id']),
                    'business_unit'=>'HQ','transaction_direction'=>'EXPENSE','operation_type'=>'PERSONAL_EXPENSE',
                    'client_id'=>$clientId,'project_id'=>$item['project_id'],'bank_account_id'=>null,'card_id'=>null,
                    'work_team_id'=>null,'team_id'=>null,'employee_id'=>$expense['employee_id'],
                    'raw_application_date'=>$expense['application_date'],'raw_project_id'=>$item['project_id'],
                    'raw_client_id'=>$item['client_id'],'raw_expense_date'=>$item['expense_date'],
                    'raw_expense_category'=>$item['expense_category'],'raw_payment_method'=>$item['payment_method'],
                    'raw_receipt_type'=>$item['receipt_type'],'raw_merchant_company_name'=>$item['merchant_name'],
                    'raw_merchant_business_number'=>$item['merchant_business_no'],
                    'raw_merchant_representative'=>$item['merchant_representative'],
                    'raw_merchant_address'=>$item['merchant_address'],
                    'raw_merchant_address_detail'=>$item['merchant_address_detail'],
                    'raw_merchant_phone'=>$item['merchant_phone'],'raw_item_name'=>$item['item_name'],
                    'raw_specification'=>$item['item_specification'],'raw_unit'=>$item['item_unit_name'],
                    'raw_quantity'=>$item['item_quantity'],'raw_unit_price'=>$item['item_unit_price'],
                    'raw_supply_amount'=>$item['item_supply_amount'],'raw_vat_amount'=>$item['item_vat_amount'],
                    'raw_total_amount'=>$item['item_total_amount'],'raw_description'=>$item['item_description'],
                    'raw_memo'=>$item['item_memo'],'evidence_status'=>$status,
                    'snapshot_json'=>$snapshotJson,'snapshot_version'=>1,'snapshot_origin_code'=>'APPROVAL_CAPTURED',
                    'source_hash'=>hash('sha256', $snapshotJson),
                    'approval_request_id'=>$request['id'],'approved_at'=>date('Y-m-d H:i:s'),'approved_by'=>$actor,
                    'created_by'=>$actor,'updated_by'=>$actor,
                ]);
            }
            $evidenceIds[] = $evidenceId;
            $activeTransactions = $evidenceLinks->activeTransactionsForEvidence(self::IMPORT_TYPE, $evidenceId, true);
            if (count($activeTransactions) > 1) {
                throw new \RuntimeException('한 개인경비 증빙이 여러 활성 거래에 연결되어 있습니다.');
            }
            if ($activeTransactions !== []) {
                $transactionIds[] = (string) $activeTransactions[0]['transaction_id'];
                continue;
            }

            $settlements = [];
            if ((float) $item['item_vat_amount'] > 0) {
                $settlements[] = [
                    'settlement_type' => 'VAT', 'amount_sign' => 'PLUS',
                    'amount' => $item['item_vat_amount'], 'transaction_item_id' => null,
                    'meta_json' => [
                        'personal_expense_id' => $sourceId,
                        'personal_expense_item_id' => (string) $item['id'],
                        'sort_no' => (int) $item['sort_no'],
                    ],
                ];
            }
            $transaction = (new TransactionCrudService($this->pdo))->save([
                'business_unit'=>'HQ','transaction_direction'=>'EXPENSE','operation_type'=>'PERSONAL_EXPENSE',
                'client_id'=>$clientId,'project_id'=>$item['project_id'],'employee_id'=>$expense['employee_id'],
                'transaction_date'=>$item['expense_date'],
                'transaction_supply_amount'=>$item['item_supply_amount'],
                'transaction_final_amount'=>$item['item_total_amount'],
                'transaction_description'=>$item['item_name'],
                'transaction_note'=>$item['item_description'],'transaction_memo'=>$item['item_memo'],
                'status'=>'completed',
                'items'=>[[
                    'item_date'=>$item['expense_date'],'item_name'=>$item['item_name'],
                    'item_specification'=>$item['item_specification'],'item_unit_name'=>$item['item_unit_name'],
                    'item_quantity'=>$item['item_quantity'],'item_unit_price'=>$item['item_unit_price'],
                    'item_supply_amount'=>$item['item_supply_amount'],'item_description'=>$item['item_description'],
                ]],
                'settlements'=>$settlements,
                'linked_evidences'=>[['import_type'=>self::IMPORT_TYPE,'evidence_id'=>$evidenceId]],
            ]);
            if (empty($transaction['success'])) {
                throw new \RuntimeException('개인경비 아이템 거래 자동생성에 실패했습니다.');
            }
            $transactionIds[] = (string) $transaction['id'];
            $transactionItemCount++;
            $vatSettlementCount += count($settlements);
            $generatedTotal += (float) $item['item_total_amount'];
        }
        if (count($items) !== count(array_unique($evidenceIds)) || count($items) !== count(array_unique($transactionIds))) {
            throw new \RuntimeException('개인경비 회계처리 건수 검증에 실패했습니다.');
        }
        $expectedTotal = array_sum(array_map(static fn(array $item): float => (float) $item['item_total_amount'], $items));
        if ($transactionItemCount === count($items) && abs($expectedTotal - $generatedTotal) > 0.009) {
            throw new \RuntimeException('개인경비 회계처리 합계 검증에 실패했습니다.');
        }
        return [
            'evidence_ids'=>$evidenceIds,
            'transaction_ids'=>$transactionIds,
            'transaction_item_count'=>$transactionItemCount,
            'vat_settlement_count'=>$vatSettlementCount,
            'duplicate_prevented'=>$transactionItemCount === 0,
        ];
    }

    private function resolveMerchantClient(array $expense, EvidenceClientSyncService $clientSync): string
    {
        $selectedClientId = trim((string) ($expense['client_id'] ?? ''));
        if ($selectedClientId !== '') {
            $client = (new \App\Models\System\ClientModel($this->pdo))->getById($selectedClientId);
            if (!$client || !empty($client['deleted_at']) || (isset($client['is_active']) && (int) $client['is_active'] !== 1)) {
                throw new \RuntimeException('선택한 거래처를 최종 승인 시 확인할 수 없습니다.');
            }
            return $selectedClientId;
        }
        $clientId = $clientSync->upsertClientFromImportParty([
            'role' => 'merchant',
            'business_number' => $expense['merchant_business_no'] ?? null,
            'company_name' => $expense['merchant_name'] ?? null,
            'ceo_name' => $expense['merchant_representative'] ?? null,
            'address' => trim(implode(' ', array_filter([
                trim((string) ($expense['merchant_address'] ?? '')),
                trim((string) ($expense['merchant_address_detail'] ?? '')),
            ]))),
            'phone' => $expense['merchant_phone'] ?? null,
        ]);
        if ($clientId === null || $clientId === '') {
            throw new \RuntimeException('가맹점 거래처를 등록하지 못했습니다.');
        }
        return $clientId;
    }

    private function identity(): array
    {
        $parsed = ActorHelper::parse(ActorHelper::user());
        $userId = trim((string)($parsed['id'] ?? ''));
        if ($userId === '') throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');
        $employee = $this->employees->findByUserId($userId);
        if (!$employee) throw new \RuntimeException('로그인 사용자와 연결된 직원 정보가 없습니다.');
        return [$userId, (string)$employee['id'], ActorHelper::user()];
    }
    private function employeeIdForUser(string $userId): string
    {
        $employee = $this->employees->findByUserId($userId);
        if (!$employee) throw new \RuntimeException('기안자와 연결된 직원 정보를 찾을 수 없습니다.');
        return (string)$employee['id'];
    }

    private function notifyRequester(
        string $recipientUserId,
        string $actorUserId,
        string $requestId,
        string $actionType,
        string $title,
        string $message
    ): void {
        if (!$this->notifications->createNotification([
            'recipient_user_id' => $recipientUserId,
            'actor_user_id' => $actorUserId,
            'action_type' => $actionType,
            'ref_table' => 'user_approval_requests',
            'ref_id' => $requestId,
            'title' => $title,
            'message' => $message,
        ])) {
            throw new \RuntimeException('알림 처리 중 오류가 발생했습니다.');
        }
    }
    private function transaction(callable $callback): array
    {
        $outer = $this->pdo->inTransaction();
        if (!$outer) $this->pdo->beginTransaction();
        try {
            $result = $callback();
            if (!$outer) {$this->pdo->commit();$this->logger->info('개인경비 결재업무가 완료되었습니다.',['event_code'=>'PERSONAL_EXPENSE_APPROVAL_COMPLETED','result'=>'SUCCESS','service'=>self::class,'action'=>'approval','actor'=>ActorHelper::user()]);}
            return $result;
        } catch (\PDOException $exception) {
            if (!$outer && $this->pdo->inTransaction()) $this->pdo->rollBack();
            if(!$outer)$this->logger->error('개인경비 결재업무에 실패했습니다.',['event_code'=>'PERSONAL_EXPENSE_APPROVAL_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'approval','actor'=>ActorHelper::user(),'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        } catch (\InvalidArgumentException|\DomainException|\RuntimeException $exception) {
            if (!$outer && $this->pdo->inTransaction()) $this->pdo->rollBack();
            if(!$outer)$this->logger->warning('개인경비 결재업무가 차단되었습니다.',['event_code'=>'PERSONAL_EXPENSE_APPROVAL_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'approval','actor'=>ActorHelper::user(),'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        } catch (\Throwable $exception) {
            if (!$outer && $this->pdo->inTransaction()) $this->pdo->rollBack();
            if(!$outer)$this->logger->error('개인경비 결재업무에 실패했습니다.',['event_code'=>'PERSONAL_EXPENSE_APPROVAL_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'approval','actor'=>ActorHelper::user(),'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        }
    }
}
