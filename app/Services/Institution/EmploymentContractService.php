<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentContractComponentModel;
use App\Models\Institution\EmploymentContractModel;
use App\Models\Institution\EmploymentContractWeeklyScheduleModel;
use App\Models\Institution\EmploymentContractWorkSchedulePolicyModel;
use App\Models\Institution\PayComponentModel;
use App\Models\System\CodeModel;
use App\Models\System\CompanyModel;
use App\Models\User\ApprovalRequestModel;
use App\Services\Approval\ApprovalWorkflowService;
use App\Services\System\UserSettingService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EmploymentContractService
{
    public const DOCUMENT_TYPE = 'EMPLOYMENT_CONTRACT';
    private const FIXED_TERM_CONTRACT_PERIOD_TYPE = 'FIXED_TERM';
    private const FIXED_TERM_REASON_GROUP = 'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON';
    private const FIXED_TERM_DETAIL_REQUIRED = [
        'PROJECT_COMPLETION', 'TASK_COMPLETION', 'REPLACEMENT',
        'STATUTORY_EXCEPTION', 'OTHER', 'REVIEW_REQUIRED',
    ];
    private const FIXED_TERM_REASONS = [
        'GENERAL', 'PROJECT_COMPLETION', 'TASK_COMPLETION', 'REPLACEMENT',
        'SENIOR', 'STATUTORY_EXCEPTION', 'OTHER', 'REVIEW_REQUIRED',
    ];
    private const COMPONENT_WORK_TYPES = ['OVERTIME', 'NIGHT', 'HOLIDAY'];
    private const COMPONENT_EXCESS_POLICIES = ['SEPARATE_PAYMENT'];

    private EmploymentContractModel $contracts;
    private EmploymentContractComponentModel $components;
    private EmploymentContractWeeklyScheduleModel $weeklySchedules;
    private EmploymentContractWorkSchedulePolicyModel $schedulePolicies;
    private PayComponentModel $payComponents;
    private ApprovalRequestModel $requests;
    private ApprovalWorkflowService $workflow;
    private CodeModel $codes;
    private CompanyModel $company;
    private UserSettingService $userSettings;
    private EmploymentContractValidityService $validity;
    private EmploymentContractAuditService $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->contracts = new EmploymentContractModel($pdo);
        $this->components = new EmploymentContractComponentModel($pdo);
        $this->weeklySchedules = new EmploymentContractWeeklyScheduleModel($pdo);
        $this->schedulePolicies = new EmploymentContractWorkSchedulePolicyModel($pdo);
        $this->payComponents = new PayComponentModel($pdo);
        $this->requests = new ApprovalRequestModel($pdo);
        $this->workflow = new ApprovalWorkflowService($pdo);
        $this->codes = new CodeModel($pdo);
        $this->company = new CompanyModel($pdo);
        $this->userSettings = new UserSettingService($pdo);
        $this->validity = new EmploymentContractValidityService($this->contracts);
        $this->audit = new EmploymentContractAuditService($pdo);
    }

    public function formOptions(): array
    {
        return [
            'pay_components' => array_map(
                static fn(array $component): array => [
                    'value' => (string) $component['id'],
                    'label' => (string) $component['component_name'],
                    'meta' => [
                        'sort_no' => (int) $component['sort_no'],
                        'component_code' => (string) $component['component_code'],
                        'component_name' => (string) $component['component_name'],
                        'component_type' => (string) $component['component_type'],
                        'default_calculation_type' => (string) $component['default_calculation_type'],
                        'default_tax_type' => (string) $component['default_tax_type'],
                        'tax_policy_code' => $component['tax_policy_code'],
                        'minimum_wage_treatment' => (string) $component['minimum_wage_treatment'],
                        'ordinary_wage_treatment' => (string) $component['ordinary_wage_treatment'],
                        'average_wage_treatment' => (string) $component['average_wage_treatment'],
                    ],
                ],
                $this->payComponents->activeForDate(date('Y-m-d'))
            ),
            'component_input_options' => [
                'work_type' => [
                    ['value' => '', 'label' => '선택'],
                    ['value' => 'OVERTIME', 'label' => '연장근로'],
                    ['value' => 'NIGHT', 'label' => '야간근로'],
                    ['value' => 'HOLIDAY', 'label' => '휴일근로'],
                ],
                'excess_payment_policy' => [
                    ['value' => '', 'label' => '선택'],
                    ['value' => 'SEPARATE_PAYMENT', 'label' => '초과분 별도 지급'],
                ],
            ],
            'fixed_term_contract_period_type' => $this->activeCode(
                'EMPLOYMENT_CONTRACT_PERIOD_TYPE',
                self::FIXED_TERM_CONTRACT_PERIOD_TYPE,
                '기간의 정함 있음'
            ),
            'weekly_schedule_day_types' => [
                ['value' => 'WORKDAY', 'label' => '근무일'],
                ['value' => 'UNPAID_DAY_OFF', 'label' => '무급 휴무일'],
                ['value' => 'WEEKLY_HOLIDAY', 'label' => '유급 주휴일'],
                ['value' => 'COMPANY_PAID_HOLIDAY', 'label' => '회사 약정 유급휴일'],
            ],
            'weekdays' => [
                ['value' => 1, 'label' => '월요일'], ['value' => 2, 'label' => '화요일'],
                ['value' => 3, 'label' => '수요일'], ['value' => 4, 'label' => '목요일'],
                ['value' => 5, 'label' => '금요일'], ['value' => 6, 'label' => '토요일'],
                ['value' => 7, 'label' => '일요일'],
            ],
            'work_schedule_types' => array_combine(
                ['normal', 'flexible', 'selective', 'shift', 'night', 'other'],
                array_map(fn(string $code): string => $this->activeCode('WORK_SCHEDULE_TYPE', $code, '근무형태'),
                    ['NORMAL', 'FLEXIBLE', 'SELECTIVE', 'SHIFT', 'NIGHT', 'OTHER'])
            ),
        ];
    }

    public function list(array $query): array
    {
        $page = $this->contracts->page($query);
        return [
            'success' => true, 'data' => $page['rows'],
            'draw' => (int) ($query['draw'] ?? 0),
            'recordsTotal' => $page['total'], 'recordsFiltered' => $page['filtered'],
        ];
    }

    public function reorder(array $changes): array
    {
        if ($changes === []) {
            return ['success' => true, 'message' => '변경할 순서가 없습니다.'];
        }
        $normalized = [];
        foreach ($changes as $change) {
            $id = trim((string) ($change['id'] ?? ''));
            $sortNo = filter_var($change['newSortNo'] ?? $change['sort_no'] ?? null, FILTER_VALIDATE_INT);
            if ($id === '' || $sortNo === false || $sortNo < 1) {
                throw new \InvalidArgumentException('순서 변경 데이터가 올바르지 않습니다.');
            }
            $normalized[] = ['id' => $id, 'sort_no' => $sortNo];
        }
        if (count(array_unique(array_column($normalized, 'id'))) !== count($normalized)
            || count(array_unique(array_column($normalized, 'sort_no'))) !== count($normalized)) {
            throw new \InvalidArgumentException('순서 변경 대상 또는 순번이 중복되었습니다.');
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($normalized as $index => $change) {
                $this->contracts->updateSortNo($change['id'], 1000000 + $index + 1);
            }
            foreach ($normalized as $change) {
                if (!$this->contracts->updateSortNo($change['id'], $change['sort_no'])) {
                    throw new \RuntimeException('순서를 변경할 근로계약을 찾을 수 없습니다.');
                }
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
        return ['success' => true, 'message' => '순서가 저장되었습니다.'];
    }

    public function detail(string $id, bool $includeCompensation = true): array
    {
        $contract = $this->requireContract($id);
        $components = $includeCompensation ? array_map(function (array $row): array {
            return $row + $this->componentCalculation($row);
        }, $this->components->activeForContract($id)) : [];
        $weeklySchedules = $this->weeklySchedules->forContract($id);
        $schedulePolicy = $this->schedulePolicies->forContract($id);
        return ['success' => true, 'data' => [
            'contract' => $contract,
            'weekly_schedules' => $weeklySchedules,
            'work_schedule_policy' => $schedulePolicy,
            'schedule_summary' => $this->scheduleSummary($weeklySchedules, $schedulePolicy),
            'components' => $components,
            'compensation_summary' => $this->compensationSummary(
                $components,
                (string) ($contract['salary_type'] ?? '')
            ),
            'compensation_visible' => $includeCompensation,
        ]];
    }

    public function compensationSummary(array $components, string $salaryType): array
    {
        $totalAmount = round(array_sum(array_map(
            static fn(array $row): float => (float) ($row['amount'] ?? 0),
            $components
        )), 2);
        if (!is_finite($totalAmount) || $totalAmount < 0) {
            throw new \InvalidArgumentException('지급조건 합계금액을 확인해 주세요.');
        }

        return match ($salaryType) {
            'MONTHLY' => [
                'salary_type' => $salaryType,
                'total_label' => '월 지급합계',
                'total_amount' => $totalAmount,
                'converted_label' => '연 환산액',
                'converted_amount' => round($totalAmount * 12, 2),
            ],
            'ANNUAL' => [
                'salary_type' => $salaryType,
                'total_label' => '연봉 합계',
                'total_amount' => $totalAmount,
                'converted_label' => '월 환산액',
                'converted_amount' => round($totalAmount / 12, 2),
            ],
            'DAILY' => [
                'salary_type' => $salaryType,
                'total_label' => '일 지급합계',
                'total_amount' => $totalAmount,
                'converted_label' => null,
                'converted_amount' => null,
            ],
            'HOURLY' => [
                'salary_type' => $salaryType,
                'total_label' => '시간급 합계',
                'total_amount' => $totalAmount,
                'converted_label' => null,
                'converted_amount' => null,
            ],
            default => [
                'salary_type' => $salaryType,
                'total_label' => '지급합계',
                'total_amount' => $totalAmount,
                'converted_label' => null,
                'converted_amount' => null,
            ],
        };
    }

    public function componentCalculation(array $component): array
    {
        $calculationType = trim((string) ($component['calculation_type'] ?? ''));
        $paymentCycle = trim((string) ($component['payment_cycle'] ?? ''));
        if (!$this->usesComponentFormula($component)) {
            $cycleLabels = [
                'MONTHLY' => '월 정액', 'ANNUAL' => '연 정액',
                'DAILY' => '일 정액', 'HOURLY' => '시간 정액',
            ];
            return [
                'formula_display' => $cycleLabels[$paymentCycle] ?? '정액',
                'calculated_amount' => null,
                'difference_amount' => null,
            ];
        }

        $basis = null;
        $basisLabel = null;
        $componentType = (string) ($component['component_type'] ?? '');
        $basisFields = ['quantity' => in_array($componentType, ['BASE_PAY', 'STATUTORY_PREMIUM', 'OTHER_WAGE'], true)
            ? '시간' : '단위'];
        foreach ($basisFields as $field => $unit) {
            $value = $this->nullableNumber($component[$field] ?? null);
            if ($value !== null && $value > 0) {
                $basis = $value;
                $basisLabel = $this->formulaNumber($value) . $unit;
                break;
            }
        }
        $rate = $this->nullableNumber($component['rate'] ?? null);
        $premiumRate = $this->nullableNumber($component['premium_rate'] ?? null);
        $parts = array_values(array_filter([
            $basisLabel,
            $premiumRate !== null && $premiumRate !== 1.0
                ? $this->formulaNumber($premiumRate) . '배'
                : null,
            $rate !== null && $rate > 0 ? $this->formulaNumber($rate) . '원' : null,
        ]));
        $calculatedAmount = $basis !== null && $rate !== null && $rate > 0
            ? round($basis * $rate * ($premiumRate ?? 1), 0, PHP_ROUND_HALF_UP)
            : null;
        $amount = $this->nullableNumber($component['amount'] ?? null);

        return [
            'formula_display' => $parts !== [] ? implode(' × ', $parts) : '산식 정보 없음',
            'calculated_amount' => $calculatedAmount,
            'difference_amount' => $calculatedAmount !== null && $amount !== null
                ? round($amount - $calculatedAmount, 2)
                : null,
        ];
    }

    public function save(array $input): array
    {
        [, $actor] = $this->identity();
        $requestKey=$this->requestKey($input['request_key']??null);
        return $this->transaction(function () use ($input, $actor, $requestKey): array {
            $id = trim((string) ($input['id'] ?? ''));
            $existing = $id !== '' ? $this->contracts->find($id, false, true) : null;
            $before=$existing?$this->audit->snapshot($id):null;
            if ($id !== '' && !$existing) {
                throw new \RuntimeException('수정할 근로계약을 찾을 수 없습니다.');
            }
            [$data, $weeklySchedules, $schedulePolicy] = $this->validateContract($input);
            $data += $this->buildPartySnapshots((string) $data['employee_id']);
            $components = $this->validateComponents(
                is_array($input['components'] ?? null) ? $input['components'] : [],
                $data['contract_start_date'],
                $data
            );
            if ($components === []) {
                throw new \InvalidArgumentException('지급조건을 한 건 이상 입력해 주세요.');
            }
            $compensationSummary = $this->compensationSummary($components, (string) $data['salary_type']);
            if ($existing) {
                if ((string) $existing['contract_status'] !== 'DRAFT') {
                    throw new \RuntimeException('승인 진행 중이거나 승인된 계약은 직접 수정할 수 없습니다.');
                }
                $data['updated_at'] = date('Y-m-d H:i:s');
                $data['updated_by'] = $actor;
                if (!$this->contracts->updateEditable($id, $data)) {
                    throw new \RuntimeException('수정 중 오류가 발생했습니다.');
                }
            } else {
                $id = UuidHelper::generate();
                $draftStatus = $this->activeCode(
                    'EMPLOYMENT_CONTRACT_STATUS',
                    'DRAFT',
                    '근로계약상태(작성중)'
                );
                $data += [
                    'id' => $id,
                    'sort_no' => $this->contracts->nextSortNo(),
                    'contract_no' => $this->contractNo(),
                    'revision_no' => 1,
                    'contract_status' => $draftStatus,
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ];
                $this->contracts->create($data);
            }
            $this->components->replace($id, $components, $actor);
            $this->weeklySchedules->replace($id, $weeklySchedules, $actor);
            $this->schedulePolicies->replace($id, $schedulePolicy, $actor);
            $this->audit->record($id,$existing?'UPDATE_DRAFT':'CREATE',$before,$this->audit->snapshot($id),$existing?'근로계약 초안 수정':'근로계약 초안 작성',$actor,$requestKey);
            return ['success' => true, 'data' => [
                'id' => $id,
                'compensation_summary' => $compensationSummary,
            ], 'message' => '저장했습니다.'];
        });
    }

    public function revise(string $id, string $reason, string $requestKey): array
    {
        [, $actor] = $this->identity();
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('개정사유를 입력해 주세요.');
        }
        $requestKey=$this->requestKey($requestKey);
        return $this->transaction(function () use ($id, $reason, $actor, $requestKey): array {
            $source = $this->contracts->find($id, false, true);
            $before=$source?$this->audit->snapshot($id):null;
            if (!$source || (string) $source['contract_status'] !== 'APPROVED') {
                throw new \RuntimeException('최종 승인된 계약만 개정할 수 있습니다.');
            }
            $newId = UuidHelper::generate();
            $copyColumns = [
                'employee_id', 'employee_name_snapshot', 'employee_address_snapshot',
                'employee_identifier_snapshot', 'employer_name_snapshot',
                'employer_registration_no_snapshot', 'employer_address_snapshot',
                'employer_representative_snapshot', 'contract_type', 'contract_period_type',
                'employment_category', 'working_time_type', 'contract_start_date', 'contract_end_date',
                'fixed_term_reason_code', 'fixed_term_reason_detail', 'work_location_type',
                'project_id', 'work_location_detail', 'job_title_snapshot', 'job_description',
                'work_schedule_type', 'salary_type', 'payment_day',
                'payment_timing', 'probation_start_date', 'probation_end_date', 'probation_rate',
                'note',
            ];
            $copy = [];
            foreach ($copyColumns as $column) {
                $copy[$column] = $source[$column] ?? null;
            }
            $draftStatus = $this->activeCode(
                'EMPLOYMENT_CONTRACT_STATUS',
                'DRAFT',
                '근로계약상태(작성중)'
            );
            $copy += [
                'id' => $newId, 'sort_no' => $this->contracts->nextSortNo(),
                'contract_no' => $this->contractNo(), 'previous_contract_id' => $id,
                'revision_no' => (int) $source['revision_no'] + 1,
                'revision_reason' => $reason, 'contract_status' => $draftStatus,
                'created_at' => date('Y-m-d H:i:s'), 'created_by' => $actor, 'updated_by' => $actor,
            ];
            $this->contracts->create($copy);
            $rows = $this->components->activeForContract($id, true);
            foreach ($rows as &$row) {
                $row['id'] = UuidHelper::generate();
            }
            unset($row);
            $this->components->replace($newId, $rows, $actor);
            $this->weeklySchedules->replace($newId, $this->weeklySchedules->forContract($id, true), $actor);
            $this->schedulePolicies->replace($newId, $this->schedulePolicies->forContract($id, true), $actor);
            $this->audit->record($newId,'CREATE_REVISION',$before,$this->audit->snapshot($newId),$reason,$actor,$requestKey);
            return [
                'success' => true,
                'data' => ['id' => $newId],
                'message' => '개정 계약 초안을 생성했습니다. 계약기간·프로젝트와 기간제 계약 사유를 다시 확인해 주세요.',
            ];
        });
    }

    public function submit(string $id, string $requestKey): array
    {
        [$userId, $actor] = $this->identity();
        $requestKey=$this->requestKey($requestKey);
        return $this->transaction(function () use ($id, $userId, $actor, $requestKey): array {
            $contract = $this->contracts->find($id, false, true);
            $before=$contract?$this->audit->snapshot($id):null;
            if (!$contract || (string) $contract['contract_status'] !== 'DRAFT') {
                throw new \RuntimeException('결재요청 가능한 계약이 아닙니다.');
            }
            $components = $this->components->activeForContract($id, true);
            if ($components === []) {
                throw new \RuntimeException('지급조건을 한 건 이상 등록해 주세요.');
            }
            $monthlyTotalAmount = round(array_sum(array_map(
                static fn(array $row): float => (float) ($row['amount'] ?? 0),
                $components
            )), 2);
            if (!is_finite($monthlyTotalAmount) || $monthlyTotalAmount <= 0) {
                throw new \RuntimeException('지급조건 월 합계가 0원입니다. 지급항목과 계약금액을 확인해주세요.');
            }
            $this->validateStoredSchedule($contract);
            $this->validateStoredForApproval($contract);
            $this->validity->assertNoOverlap($id);
            $pendingStatus = $this->activeCode(
                'EMPLOYMENT_CONTRACT_STATUS',
                'APPROVAL_PENDING',
                '근로계약상태(결재대기)'
            );
            $result = $this->workflow->submit(self::DOCUMENT_TYPE, $id, $userId, $actor);
            if (!$this->contracts->updateWorkflow($id, $pendingStatus, $result['request_id'], $actor)) {
                throw new \RuntimeException('계약 결재상태를 반영하지 못했습니다.');
            }
            $this->audit->record($id,'SUBMIT',$before,$this->audit->snapshot($id),'근로계약 결재요청',$actor,$requestKey,(string)$result['request_id']);
            return ['success' => true, 'data' => $result, 'message' => '결재를 요청했습니다.'];
        });
    }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        [$userId, $actor] = $this->identity();
        return $this->transaction(function () use ($stepId, $decision, $comment, $userId, $actor): array {
            $result = $this->workflow->act(
                $stepId, self::DOCUMENT_TYPE, $decision, $comment, $userId, $actor
            );
            $request = $result['request'];
            $contractId=(string)$request['document_id'];
            $before=$this->audit->snapshot($contractId);
            if ((string) $result['state'] === 'APPROVED') {
                $this->validity->assertNoOverlap((string) $request['document_id']);
            }
            $nextStatusCode = match ((string) $result['state']) {
                'REJECTED' => 'DRAFT',
                'IN_PROGRESS' => 'APPROVAL_PENDING',
                'APPROVED' => 'APPROVED',
                default => throw new \RuntimeException('결재 결과 상태를 근로계약에 반영할 수 없습니다.'),
            };
            $nextStatus = $this->activeCode(
                'EMPLOYMENT_CONTRACT_STATUS',
                $nextStatusCode,
                '근로계약상태'
            );
            if (!$this->contracts->updateWorkflow(
                (string) $request['document_id'],
                $nextStatus,
                (string) $request['id'],
                $actor
            )) {
                throw new \RuntimeException('계약 결재상태를 반영하지 못했습니다.');
            }
            if(in_array((string)$result['state'],['REJECTED','APPROVED'],true)){
                $action=(string)$result['state']==='REJECTED'?'REJECT':(((int)($before['header']['revision_no']??1)>1)?'APPROVE_REVISION':'APPROVE');
                $this->audit->record($contractId,$action,$before,$this->audit->snapshot($contractId),(string)($comment?:($action==='REJECT'?'근로계약 반려':'근로계약 승인')),$actor,'APPROVAL_STEP:'.$stepId.':'.$action,(string)$request['id']);
            }
            return ['success' => true, 'message' => match ($result['state']) {
                'REJECTED' => '반려했습니다.',
                'APPROVED' => '최종 승인했습니다.',
                default => '승인했습니다.',
            }];
        });
    }

    public function withdraw(string $requestId, string $requestKey): array
    {
        [$userId, $actor] = $this->identity();
        $requestKey=$this->requestKey($requestKey);
        return $this->transaction(function () use ($requestId, $userId, $actor, $requestKey): array {
            $requestRow=$this->requests->getById($requestId,true);
            $contractId=(string)($requestRow['document_id']??'');
            $before=$contractId!==''?$this->audit->snapshot($contractId):null;
            $request = $this->workflow->withdraw(
                $requestId, self::DOCUMENT_TYPE, $userId, $actor
            );
            $withdrawnStatus = $this->activeCode(
                'EMPLOYMENT_CONTRACT_STATUS',
                'DRAFT',
                '근로계약상태(회수)'
            );
            if (!$this->contracts->updateWorkflow(
                (string) $request['document_id'], $withdrawnStatus, $requestId, $actor
            )) {
                throw new \RuntimeException('계약 회수상태를 반영하지 못했습니다.');
            }
            $this->audit->record((string)$request['document_id'],'WITHDRAW',$before,$this->audit->snapshot((string)$request['document_id']),'근로계약 결재 회수',$actor,$requestKey,$requestId);
            return ['success' => true, 'message' => '기안을 회수했습니다.'];
        });
    }

    public function terminate(string $id, string $reason, string $requestKey): array
    {
        [, $actor] = $this->identity();
        $reasonCode = $this->activeCode('EMPLOYMENT_TERMINATION_REASON', $reason, '종료사유');
        $this->activeCode('EMPLOYMENT_CONTRACT_STATUS', 'TERMINATED', '근로계약상태(종료·해지)');
        $requestKey=$this->requestKey($requestKey);
        return$this->transaction(function()use($id,$reasonCode,$actor,$requestKey):array{$before=$this->audit->snapshot($id);if (!$this->contracts->terminate($id, $reasonCode, $actor)) {throw new \RuntimeException('최종 승인된 계약만 종료 또는 해지할 수 있습니다.');}$this->audit->record($id,'TERMINATE',$before,$this->audit->snapshot($id),$reasonCode,$actor,$requestKey);return ['success' => true, 'message' => '계약을 종료·해지 처리했습니다.'];});
    }

    public function delete(string $id, string $requestKey): array
    {
        [, $actor] = $this->identity();
        $requestKey=$this->requestKey($requestKey);
        return$this->transaction(function()use($id,$actor,$requestKey):array{$before=$this->audit->snapshot($id);if (!$this->contracts->softDelete($id, $actor)) {throw new \RuntimeException('진행 중이거나 승인된 계약은 삭제할 수 없습니다.');}$this->audit->record($id,'DELETE',$before,$this->audit->snapshot($id,true),'근로계약 초안 삭제',$actor,$requestKey);return ['success' => true, 'message' => '삭제했습니다.'];});
    }

    public function trash(array $query): array
    {
        $page = $this->contracts->page($query, true);
        return ['success' => true, 'data' => $page['rows']];
    }

    public function restore(string $id, string $requestKey): array
    {
        [, $actor] = $this->identity();
        $requestKey=$this->requestKey($requestKey);
        return$this->transaction(function()use($id,$actor,$requestKey):array{$before=$this->audit->snapshot($id,true);if (!$this->contracts->restore($id, $actor)) {throw new \RuntimeException('복구할 계약을 찾을 수 없습니다.');}$this->audit->record($id,'RESTORE',$before,$this->audit->snapshot($id),'근로계약 초안 복구',$actor,$requestKey);return ['success' => true, 'message' => '복구했습니다.'];});
    }

    public function purge(string $id, string $requestKey): array
    {
        [, $actor]=$this->identity();$requestKey=$this->requestKey($requestKey);
        return $this->transaction(function () use ($id,$actor,$requestKey): array {
            $contract = $this->contracts->find($id, true, true);
            if (!$contract || empty($contract['deleted_at'])) {
                throw new \RuntimeException('휴지통의 계약만 완전삭제할 수 있습니다.');
            }
            if ($this->requests->hasBlockingHistory(self::DOCUMENT_TYPE, $id)) {
                throw new \RuntimeException('진행 또는 승인 이력이 있는 계약은 완전삭제할 수 없습니다.');
            }
            $before=$this->audit->snapshot($id,true);
            $this->components->purgeForContract($id);
            if (!$this->contracts->purge($id)) {
                throw new \RuntimeException('완전삭제 중 오류가 발생했습니다.');
            }
            $this->audit->record($id,'PURGE',$before,null,'근로계약 초안 영구삭제',$actor,$requestKey);
            return ['success' => true, 'message' => '완전삭제했습니다.'];
        });
    }

    private function buildPartySnapshots(string $employeeId): array
    {
        $employee = $this->contracts->employeeSnapshotSource($employeeId);
        if (!$employee) {
            throw new \InvalidArgumentException('선택한 직원을 찾을 수 없습니다.');
        }
        $company = $this->company->getOne();
        if (!$company) {
            throw new \RuntimeException('회사 기본정보를 먼저 등록해 주세요.');
        }
        $employeeAddress = trim(implode(' ', array_filter([
            $employee['address'] ?? null, $employee['address_detail'] ?? null,
        ])));
        $employerAddress = trim(implode(' ', array_filter([
            $company['addr_main'] ?? null, $company['addr_detail'] ?? null,
        ])));

        return [
            'employee_name_snapshot' => trim((string) ($employee['employee_name'] ?? '')),
            'employee_identifier_snapshot' => $employee['rrn'] ?: null,
            'employee_address_snapshot' => $employeeAddress !== '' ? $employeeAddress : null,
            'employer_name_snapshot' => trim((string) ($company['company_name_ko'] ?? '')),
            'employer_registration_no_snapshot' => $company['biz_number'] ?: null,
            'employer_address_snapshot' => $employerAddress !== '' ? $employerAddress : null,
            'employer_representative_snapshot' => $company['ceo_name'] ?: null,
            'job_title_snapshot' => $employee['position_name'] ?: null,
        ];
    }
    private function validateContract(array $input): array
    {
        $this->validateConfiguredRequiredFields($input);
        foreach ([
            'employee_id', 'contract_type', 'contract_period_type', 'employment_category',
            'working_time_type', 'contract_start_date',
            'work_location_type', 'work_schedule_type', 'salary_type', 'payment_timing',
        ] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                throw new \InvalidArgumentException('필수 계약정보를 모두 입력해 주세요.');
            }
        }
        $contractPeriodType = $this->activeCode(
            'EMPLOYMENT_CONTRACT_PERIOD_TYPE',
            $input['contract_period_type'],
            '계약기간 구분'
        );
        $employmentCategory = $this->activeCode(
            'EMPLOYMENT_CATEGORY',
            $input['employment_category'],
            '고용구분'
        );
        $workingTimeType = $this->activeCode(
            'EMPLOYMENT_WORKING_TIME_TYPE',
            $input['working_time_type'],
            '근로시간 구분'
        );
        $isFixedTerm = $contractPeriodType === self::FIXED_TERM_CONTRACT_PERIOD_TYPE;
        $start = $this->date((string) $input['contract_start_date'], '계약시작일');
        $end = $isFixedTerm
            ? $this->optionalDate($input['contract_end_date'] ?? null, '계약종료일')
            : null;
        if ($isFixedTerm && $end === null) {
            throw new \InvalidArgumentException('기간제 고용형태는 계약종료일을 입력해 주세요.');
        }
        if ($end !== null && $end < $start) {
            throw new \InvalidArgumentException('계약종료일은 계약시작일보다 빠를 수 없습니다.');
        }
        $probationStart = $this->optionalDate($input['probation_start_date'] ?? null, '수습시작일');
        $probationEnd = $this->optionalDate($input['probation_end_date'] ?? null, '수습종료일');
        if (($probationStart === null) !== ($probationEnd === null)
            || ($probationStart !== null && $probationEnd < $probationStart)) {
            throw new \InvalidArgumentException('수습기간 시작일과 종료일을 확인해 주세요.');
        }
        if ($probationStart !== null
            && ($probationStart < $start || ($end !== null && $probationEnd > $end))) {
            throw new \InvalidArgumentException('수습기간은 계약기간 안에서 입력해 주세요.');
        }

        $scheduleType = $this->activeCode('WORK_SCHEDULE_TYPE', $input['work_schedule_type'], '근무형태');
        [$weeklySchedules, $schedulePolicy, $projection] = $this->validateSchedule(
            $scheduleType,
            is_array($input['weekly_schedules'] ?? null) ? $input['weekly_schedules'] : [],
            is_array($input['work_schedule_policy'] ?? null) ? $input['work_schedule_policy'] : []
        );
        $locationType = $this->activeCode('WORK_LOCATION_TYPE', $input['work_location_type'], '근무장소구분');
        $projectId = $this->nullableString($input['project_id'] ?? null);
        $locationDetail = $this->nullableString($input['work_location_detail'] ?? null);
        $fixedTermReasonCode = null;
        $fixedTermReasonDetail = null;
        if ($isFixedTerm) {
            $fixedTermReasonCode = $this->activeCode(
                self::FIXED_TERM_REASON_GROUP,
                $input['fixed_term_reason_code'] ?? null,
                '기간제 계약 사유'
            );
            if (!in_array($fixedTermReasonCode, self::FIXED_TERM_REASONS, true)) {
                throw new \InvalidArgumentException('기간제 계약 사유의 코드관리 값을 확인해 주세요.');
            }
            $fixedTermReasonDetail = $this->nullableString($input['fixed_term_reason_detail'] ?? null);
            if (in_array($fixedTermReasonCode, self::FIXED_TERM_DETAIL_REQUIRED, true)
                && $fixedTermReasonDetail === null) {
                throw new \InvalidArgumentException('선택한 기간제 계약 사유의 상세 사유를 입력해 주세요.');
            }
            if ($fixedTermReasonCode === 'PROJECT_COMPLETION' && $projectId === null) {
                throw new \InvalidArgumentException('특정 사업·프로젝트 완료 사유는 프로젝트를 선택해 주세요.');
            }
        }
        if ($locationType === 'PROJECT' && $projectId === null) {
            throw new \InvalidArgumentException('특정 현장 근무는 프로젝트를 선택해 주세요.');
        }
        if ($locationType === 'OTHER' && $locationDetail === null) {
            throw new \InvalidArgumentException('기타 근무장소의 상세 내용을 입력해 주세요.');
        }
        $paymentDay = (int) ($input['payment_day'] ?? 0);
        $probationRate = $this->nullableNumber($input['probation_rate'] ?? null);
        if ($paymentDay < 1 || $paymentDay > 31
            || ($probationRate !== null && ($probationRate < 0 || $probationRate > 100))) {
            throw new \InvalidArgumentException('근무·지급·수습 조건 값이 허용 범위를 벗어났습니다.');
        }
        $contract = [
            'employee_id' => trim((string) $input['employee_id']),
            'contract_type' => $this->activeCode('EMPLOYMENT_CONTRACT_TYPE', $input['contract_type'], '계약종류'),
            'contract_period_type' => $contractPeriodType,
            'employment_category' => $employmentCategory,
            'working_time_type' => $workingTimeType,
            'contract_start_date' => $start, 'contract_end_date' => $end,
            'fixed_term_reason_code' => $fixedTermReasonCode,
            'fixed_term_reason_detail' => $fixedTermReasonDetail,
            'work_location_type' => $locationType,
            'project_id' => $projectId,
            'work_location_detail' => $locationDetail,
            'job_description' => $this->nullableString($input['job_description'] ?? null),
            'work_schedule_type' => $scheduleType,
            'salary_type' => $this->activeCode('SALARY_TYPE', $input['salary_type'], '급여형태'),
            'payment_day' => $paymentDay,
            'payment_timing' => $this->activeCode('PAYMENT_TIMING', $input['payment_timing'], '급여지급기준'),
            'probation_start_date' => $probationStart, 'probation_end_date' => $probationEnd,
            'probation_rate' => $probationRate,
            'note' => $this->nullableString($input['note'] ?? null),
        ];
        return [$contract, $weeklySchedules, $schedulePolicy];
    }

    private function validateConfiguredRequiredFields(array $input): void
    {
        $editableFields = [
            'employee_id', 'contract_type', 'contract_period_type', 'employment_category',
            'working_time_type', 'contract_start_date',
            'work_location_type', 'project_id', 'work_location_detail', 'job_description',
            'work_schedule_type',
            'salary_type', 'payment_day', 'payment_timing', 'probation_start_date',
            'probation_end_date', 'probation_rate', 'note',
        ];
        $settings = $this->userSettings->detail(
            'institution.human_resources.employment_contracts',
            'TABLE'
        )['settings_json'] ?? [];
        $policies = is_array($settings['columnRequirementPolicy'] ?? null)
            ? $settings['columnRequirementPolicy']
            : [];
        $displayNames = is_array($settings['columnDisplayName'] ?? null)
            ? $settings['columnDisplayName']
            : [];

        foreach ($editableFields as $field) {
            if (strtolower(trim((string) ($policies[$field] ?? ''))) !== 'required') {
                continue;
            }
            if (trim((string) ($input[$field] ?? '')) !== '') {
                continue;
            }
            $label = trim((string) ($displayNames[$field] ?? $field));
            throw new \InvalidArgumentException($label . '은(는) 필수 입력입니다.');
        }
    }

    private function validateSchedule(
        string $scheduleType,
        array $rows,
        array $policyInput
    ): array {
        if (in_array($scheduleType, ['NORMAL', 'NIGHT'], true)) {
            if (array_filter($policyInput, static fn(mixed $value): bool => $value !== null && $value !== '') !== []) {
                throw new \InvalidArgumentException('일반·야간근무에는 비고정 근무형태 정책을 입력할 수 없습니다.');
            }
            $schedules = $this->validateWeeklySchedules($rows, $scheduleType);
            return [$schedules, null, $this->weeklyScheduleProjection($schedules)];
        }
        if (in_array($scheduleType, ['SELECTIVE', 'SHIFT'], true) && $rows !== []) {
            throw new \InvalidArgumentException('선택한 근무형태에는 주간 반복 일정을 저장할 수 없습니다.');
        }
        $policy = $this->validateSchedulePolicy($scheduleType, $policyInput);
        if (in_array($scheduleType, ['FLEXIBLE', 'OTHER'], true)) {
            $schedules = $this->validateWeeklySchedules($rows, $scheduleType);
            return [$schedules, $policy, $this->weeklyScheduleProjection($schedules)];
        }
        return [[], $policy, []];
    }

    private function validateWeeklySchedules(array $rows, string $scheduleType): array
    {
        if (count($rows) !== 7) {
            $dayNames = [1 => '월요일', 2 => '화요일', 3 => '수요일', 4 => '목요일', 5 => '금요일', 6 => '토요일', 7 => '일요일'];
            $presentDays = array_map(static fn(array $row): int => (int) ($row['day_of_week'] ?? 0), $rows);
            $missingDays = array_diff(array_keys($dayNames), $presentDays);
            $missingLabels = array_map(static fn(int $day): string => $dayNames[$day], $missingDays);
            throw new \InvalidArgumentException(
                '주간 근무일정에서 ' . implode(', ', $missingLabels) . ' 일정이 누락되었습니다.'
            );
        }
        $result = [];
        foreach ($rows as $row) {
            $day = (int) ($row['day_of_week'] ?? 0);
            $dayLabel = [1 => '월요일', 2 => '화요일', 3 => '수요일', 4 => '목요일', 5 => '금요일', 6 => '토요일', 7 => '일요일'][$day] ?? '알 수 없는 요일';
            if ($day < 1 || $day > 7 || isset($result[$day])) {
                throw new \InvalidArgumentException('요일은 1부터 7까지 중복 없이 입력해 주세요.');
            }
            $dayType = trim((string) ($row['day_type'] ?? ''));
            if (!in_array($dayType, ['WORKDAY', 'UNPAID_DAY_OFF', 'WEEKLY_HOLIDAY', 'COMPANY_PAID_HOLIDAY'], true)) {
                throw new \InvalidArgumentException('요일 상태를 확인해 주세요.');
            }
            $start = $this->scheduleTime($row['start_time'] ?? null);
            $end = $this->scheduleTime($row['end_time'] ?? null);
            $breakRaw = $row['break_minutes'] ?? '';
            $offsetRaw = $row['end_day_offset'] ?? '';
            $break = $breakRaw === '' || $breakRaw === null ? null
                : (filter_var($breakRaw, FILTER_VALIDATE_INT) !== false ? (int) $breakRaw : -1);
            $offset = $offsetRaw === '' || $offsetRaw === null ? null
                : (filter_var($offsetRaw, FILTER_VALIDATE_INT) !== false ? (int) $offsetRaw : -1);
            if ($dayType === 'WORKDAY') {
                if ($start === null || $end === null || $break === null || !in_array($offset, [0, 1], true)
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
                    || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
                    || $break < 0 || $break > 1440 || ($scheduleType === 'NORMAL' && $offset !== 0)) {
                    throw new \InvalidArgumentException($dayLabel . '의 출근시간, 퇴근시간, 퇴근일 또는 휴게시간을 확인해 주세요.');
                }
                $grossMinutes = $this->timeMinutes($end) + 1440 * $offset - $this->timeMinutes($start);
                if ($grossMinutes <= 0) {
                    throw new \InvalidArgumentException($dayLabel . '의 퇴근 시각은 출근 시각 이후여야 합니다. 익일 종료 여부를 확인해 주세요.');
                }
                if ($break >= $grossMinutes) {
                    throw new \InvalidArgumentException($dayLabel . '의 휴게시간은 출근부터 퇴근까지의 시간보다 짧아야 합니다.');
                }
                $netMinutes = $grossMinutes - $break;
                $requiredBreak = $netMinutes >= 480 ? 60 : ($netMinutes >= 240 ? 30 : 0);
                if ($break < $requiredBreak) {
                    throw new \InvalidArgumentException($dayLabel . '의 근로시간에 필요한 최소 휴게시간을 입력해 주세요.');
                }
            } elseif ($start !== null || $end !== null || $break !== null || $offset !== null) {
                throw new \InvalidArgumentException($dayLabel . '은 비근무일이므로 출퇴근·퇴근일·휴게시간을 입력할 수 없습니다.');
            }

            $breakSchedules=$this->validateBreakSchedules(is_array($row['break_schedules']??null)?$row['break_schedules']:[],$dayType,$start,$end,$offset,$break,$dayLabel);
            $result[$day] = [
                'day_of_week' => $day, 'day_type' => $dayType,
                'start_time' => $dayType === 'WORKDAY' ? $start : null,
                'end_time' => $dayType === 'WORKDAY' ? $end : null,
                'end_day_offset' => $dayType === 'WORKDAY' ? $offset : null,
                'break_minutes' => $dayType === 'WORKDAY' ? $break : null,
                'break_schedules' => $breakSchedules,
            ];
        }
        $weeklyHolidayCount = count(array_filter(
            $result,
            static fn(array $row): bool => $row['day_type'] === 'WEEKLY_HOLIDAY'
        ));
        if ($weeklyHolidayCount !== 1) {
            throw new \InvalidArgumentException('유급 주휴일을 정확히 한 요일로 지정해 주세요.');
        }
        ksort($result);
        return array_values($result);
    }

    private function validateBreakSchedules(array $rows,string $dayType,?string $workStart,?string $workEnd,?int $workOffset,?int $breakMinutes,string $dayLabel): array
    {
        if($dayType!=='WORKDAY'){if($rows!==[])throw new \InvalidArgumentException($dayLabel.' 비근무일에는 휴게구간을 입력할 수 없습니다.');return[];}
        if($breakMinutes===0){if($rows!==[])throw new \InvalidArgumentException($dayLabel.' 휴게시간이 0분이면 상세 휴게구간을 입력할 수 없습니다.');return[];}
        if($rows===[])throw new \InvalidArgumentException($dayLabel.'의 상세 휴게구간을 입력해 주세요.');
        $workStartMinutes=$this->timeMinutes((string)$workStart);$workEndMinutes=$this->timeMinutes((string)$workEnd)+1440*(int)$workOffset;$out=[];$sum=0;$lastEnd=null;
        foreach($rows as$index=>$row){$start=$this->scheduleTime($row['start_time']??null);$end=$this->scheduleTime($row['end_time']??null);$offset=filter_var($row['end_day_offset']??0,FILTER_VALIDATE_INT);if($start===null||$end===null||!in_array($offset,[0,1],true))throw new \InvalidArgumentException($dayLabel.' 휴게구간 시각을 확인해 주세요.');$a=$this->timeMinutes($start);$b=$this->timeMinutes($end)+1440*$offset;if($b<=$a||$a<$workStartMinutes||$b>$workEndMinutes)throw new \InvalidArgumentException($dayLabel.' 휴게구간은 예정 근무구간 안에 있어야 합니다.');if($lastEnd!==null&&$a<$lastEnd)throw new \InvalidArgumentException($dayLabel.' 휴게구간이 서로 겹칩니다.');$sum+=$b-$a;$lastEnd=$b;$out[]=['start_time'=>$start,'end_time'=>$end,'end_day_offset'=>$offset];}
        if($sum!==$breakMinutes)throw new \InvalidArgumentException($dayLabel.' 상세 휴게구간 합계와 휴게시간(분)이 일치하지 않습니다.');return$out;
    }

    private function weeklyScheduleProjection(array $rows): array
    {
        $working = array_values(array_filter($rows, static fn(array $row): bool => $row['day_type'] === 'WORKDAY'));
        if ($working === []) {
            throw new \InvalidArgumentException('근무요일을 한 개 이상 선택해 주세요.');
        }
        $minutes = array_sum(array_map(fn(array $row): int =>
            $this->timeMinutes((string) $row['end_time']) + 1440 * (int) $row['end_day_offset']
            - $this->timeMinutes((string) $row['start_time'])
            - (int) $row['break_minutes'], $working));
        $common = static function (array $rows, string $key): mixed {
            $values = array_values(array_unique(array_column($rows, $key), SORT_REGULAR));
            return count($values) === 1 ? $values[0] : null;
        };
        $weeklyHoliday = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['day_type'] === 'WEEKLY_HOLIDAY'
        ));
        return [
            'weekly_days' => count($working),
            'weekly_hours' => round($minutes / 60, 2),
            'average_daily_hours' => round($minutes / 60 / count($working), 2),
            'representative_start_time' => $common($working, 'start_time'),
            'representative_end_time' => $common($working, 'end_time'),
            'representative_break_minutes' => $common($working, 'break_minutes'),
            'weekly_holiday' => (int) $weeklyHoliday[0]['day_of_week'],
        ];
    }

    private function scheduleSummary(array $rows, ?array $policy): array
    {
        if ($rows === []) {
            $weeklyHours = $this->nullableNumber($policy['reference_weekly_hours'] ?? null);
            return [
                'weekly_days' => null, 'weekly_hours' => $weeklyHours,
                'average_daily_hours' => null, 'representative_start_time' => null,
                'representative_end_time' => null, 'representative_break_minutes' => null,
                'weekly_holiday' => null, 'monthly_average_days' => null,
                'monthly_average_hours' => $weeklyHours === null ? null : round($weeklyHours * 365 / 7 / 12, 2),
                'monthly_ordinary_wage_hours' => null,
            ];
        }
        $summary = $this->weeklyScheduleProjection($rows);
        $monthFactor = 365 / 7 / 12;
        return $summary + [
            'monthly_average_days' => round((float) $summary['weekly_days'] * $monthFactor, 2),
            'monthly_average_hours' => round((float) $summary['weekly_hours'] * $monthFactor, 2),
            'monthly_ordinary_wage_hours' => round(
                ((float) $summary['weekly_hours'] + (float) $summary['average_daily_hours']) * $monthFactor,
                2
            ),
        ];
    }

    private function validateSchedulePolicy(string $scheduleType, array $input): array
    {
        if (!in_array($scheduleType, ['SELECTIVE', 'SHIFT', 'FLEXIBLE', 'OTHER'], true)) {
            throw new \InvalidArgumentException('근무형태 정책을 저장할 수 없는 코드입니다.');
        }
        $detail = $this->nullableString($input['policy_detail'] ?? null);
        if ($detail === null) throw new \InvalidArgumentException('근무형태 정책 상세를 입력해 주세요.');
        $policy = [
            ':settlement_period_days' => null,
            ':reference_weekly_hours' => null, ':selectable_start_time' => null,
            ':selectable_end_time' => null, ':core_start_time' => null,
            ':core_end_time' => null, ':policy_detail' => $detail,
        ];
        $period = $this->nullableNumber($input['settlement_period_days'] ?? null);
        $hoursInput = $this->nullableNumber($input['reference_weekly_hours'] ?? null);
        if ($hoursInput !== null && ($hoursInput <= 0 || $hoursInput > 168)) {
            throw new \InvalidArgumentException('기준 주근로시간은 0시간 초과 168시간 이하로 입력해 주세요.');
        }
        $hours = $hoursInput ?? 0;
        if ($scheduleType === 'FLEXIBLE') {
            if (($period ?? 0) < 1) throw new \InvalidArgumentException('탄력근무 정산기간을 입력해 주세요.');
            $policy[':settlement_period_days'] = (int) $period;
            $policy[':reference_weekly_hours'] = $hours > 0 && $hours <= 168 ? $hours : null;
            return $policy;
        }
        if ($scheduleType === 'SHIFT') {
            $policy[':reference_weekly_hours'] = $hours > 0 && $hours <= 168 ? $hours : null;
            return $policy;
        }
        if ($scheduleType === 'OTHER') {
            $policy[':settlement_period_days'] = ($period ?? 0) > 0 ? (int) $period : null;
            $policy[':reference_weekly_hours'] = $hours > 0 && $hours <= 168 ? $hours : null;
            foreach (['selectable_start_time', 'selectable_end_time', 'core_start_time', 'core_end_time'] as $field) {
                $value = $this->nullableString($input[$field] ?? null);
                $policy[':' . $field] = $value === null ? null : $this->validTime($value, $field);
            }
            if (($policy[':core_start_time'] === null) !== ($policy[':core_end_time'] === null)) {
                throw new \InvalidArgumentException('의무근로 시작시간과 종료시간을 함께 입력해 주세요.');
            }
            if ($policy[':core_start_time'] !== null && $policy[':core_end_time'] <= $policy[':core_start_time']) {
                throw new \InvalidArgumentException('의무근로 종료시간은 시작시간보다 이후여야 합니다.');
            }
            return $policy;
        }
        $selectStart = $this->validTime($input['selectable_start_time'] ?? null, '선택가능 시작시간');
        $selectEnd = $this->validTime($input['selectable_end_time'] ?? null, '선택가능 종료시간');
        if ($hours <= 0 || $hours > 168 || $selectEnd <= $selectStart) {
            throw new \InvalidArgumentException('선택근무 정산기간·기준시간·선택가능 시간대를 확인해 주세요.');
        }
        $coreStart = $this->nullableString($input['core_start_time'] ?? null);
        $coreEnd = $this->nullableString($input['core_end_time'] ?? null);
        if (($coreStart === null) !== ($coreEnd === null)
            || ($coreStart !== null && ($this->validTime($coreStart, '의무시간대 시작') >= $this->validTime($coreEnd, '의무시간대 종료')
                || $coreStart < $selectStart || $coreEnd > $selectEnd))) {
            throw new \InvalidArgumentException('의무시간대는 선택가능 시간대 안에서 입력해 주세요.');
        }
        return [
            ':settlement_period_days' => null,
            ':reference_weekly_hours' => $hours, ':selectable_start_time' => $selectStart,
            ':selectable_end_time' => $selectEnd, ':core_start_time' => $coreStart,
            ':core_end_time' => $coreEnd, ':policy_detail' => $detail,
        ];
    }

    private function validTime(mixed $value, string $label): string
    {
        $time = trim((string) $value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new \InvalidArgumentException($label . '을(를) HH:MM 형식으로 입력해 주세요.');
        }
        return $time;
    }

    private function scheduleTime(mixed $value): ?string
    {
        $time = $this->nullableString($value);
        if ($time === null) return null;
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)
            ? substr($time, 0, 5)
            : $time;
    }

    private function timeMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        return $hour * 60 + $minute;
    }

    private function componentPaymentCycle(array $contract): string
    {
        $paymentDay = (int) ($contract['payment_day'] ?? 0);
        $paymentTiming = trim((string) ($contract['payment_timing'] ?? ''));
        if ($paymentDay < 1 || $paymentDay > 31 || $paymentTiming === '') {
            throw new \InvalidArgumentException('급여 지급일과 지급기준을 확인해 주세요.');
        }

        // 현재 헤더는 월 단위 급여 지급일·기준만 표현한다. 비정기 주기는 별도 구조 승인 전 지원하지 않는다.
        return 'MONTHLY';
    }
    private function validateComponents(array $rows, string $effectiveDate, array $contract): array
    {
        $result = [];
        $codes = [];
        $baseRate = null;
        $baseComponentCode = null;
        foreach ($rows as $candidate) {
            $candidateId = trim((string) ($candidate['pay_component_id'] ?? ''));
            $candidateMaster = $candidateId !== ''
                ? $this->payComponents->findActive($candidateId, $effectiveDate)
                : null;
            if (!$candidateMaster || (string) $candidateMaster['component_type'] !== 'BASE_PAY') continue;
            $baseBasis = $this->nullableNumber($candidate['quantity'] ?? null) ?? 0;
            $candidateRate = $this->nullableNumber($candidate['rate'] ?? null);
            if (($candidateRate ?? 0) > 0 && $baseBasis > 0) {
                $baseRate = $candidateRate;
                $baseComponentCode = (string) $candidateMaster['component_code'];
            }
            break;
        }
        foreach ($rows as $row) {
            $masterId = trim((string) ($row['pay_component_id'] ?? ''));
            $master = $masterId !== '' ? $this->payComponents->findActive($masterId, $effectiveDate) : null;
            if (!$master) {
                throw new \InvalidArgumentException('활성 급여항목을 선택해 주세요.');
            }
            $code = (string) $master['component_code'];
            $name = (string) $master['component_name'];
            $amount = $this->nullableNumber($row['amount'] ?? null);
            if ($code === '' || $name === '' || $amount === null || $amount <= 0 || isset($codes[$code])) {
                throw new \InvalidArgumentException('지급조건의 항목명과 금액을 확인해 주세요.');
            }
            $codes[$code] = true;
            $paymentCycle = $this->componentPaymentCycle($contract);

            $isWorkAllowance = (string) $master['component_type'] === 'STATUTORY_PREMIUM';
            $calculationType = (string) $master['default_calculation_type'];
            $usesFormula = $this->usesComponentFormula([
                'calculation_type' => $calculationType,
                'component_type' => (string) $master['component_type'],
                'component_code' => $code,
            ]);
            $rate = $usesFormula ? $baseRate : $this->nullableNumber($row['rate'] ?? null);
            $quantity = $this->nullableNumber($row['quantity'] ?? null);
            $workType = $isWorkAllowance ? $this->nullableString($row['work_type'] ?? null) : null;
            $premiumRate = $isWorkAllowance ? $this->nullableNumber($row['premium_rate'] ?? null) : null;
            $excessPolicy = $isWorkAllowance ? $this->nullableString($row['excess_payment_policy'] ?? null) : null;
            $agreementBasis = $isWorkAllowance ? $this->nullableString($row['agreement_basis'] ?? null) : null;
            if ($isWorkAllowance && (!in_array($workType, self::COMPONENT_WORK_TYPES, true)
                || ($quantity ?? 0) <= 0
                || ($premiumRate ?? 0) <= 0
                || !in_array($excessPolicy, self::COMPONENT_EXCESS_POLICIES, true)
                || $agreementBasis === null)) {
                throw new \InvalidArgumentException('근로수당의 적용 구분, 계산수량, 가산율, 초과근로 정산방법 및 산정·약정 근거를 입력해 주세요.');
            }
            if ($usesFormula && (($rate ?? 0) <= 0
                || ($quantity ?? 0) <= 0)) {
                throw new \InvalidArgumentException($name . ' 산식에 필요한 기본급 계약금액과 계산시간을 확인해 주세요.');
            }
            $component = [
                'id' => UuidHelper::generate(), 'pay_component_id' => $masterId,
                'component_type' => (string) $master['component_type'],
                'component_code' => $code, 'component_name' => $name,
                'calculation_type' => $calculationType,
                'amount' => $amount, 'rate' => $rate,
                'quantity' => $quantity,
                'base_component_code' => $usesFormula
                    && (string) $master['component_type'] !== 'BASE_PAY'
                    ? $baseComponentCode
                    : null,
                'work_type' => $workType,
                'premium_rate' => $premiumRate,
                'excess_payment_policy' => $excessPolicy,
                'agreement_basis' => $agreementBasis,
                'tax_type' => (string) $master['default_tax_type'],
                'tax_policy_code' => $master['tax_policy_code'],
                'payment_cycle' => $paymentCycle,
                'is_fixed' => (string) $master['default_calculation_type'] === 'FIXED_AMOUNT' ? 1 : 0,
                'minimum_wage_treatment' => (string) $master['minimum_wage_treatment'],
                'ordinary_wage_treatment' => (string) $master['ordinary_wage_treatment'],
                'average_wage_treatment' => (string) $master['average_wage_treatment'],
                'wage_treatment_basis' => $this->nullableString($row['wage_treatment_basis'] ?? null),
                'note' => $isWorkAllowance ? $this->nullableString($row['note'] ?? null) : null,
            ];
            $calculation = $this->componentCalculation($component);
            if ($calculation['calculated_amount'] !== null
                && abs((float) $calculation['difference_amount']) > 1.0) {
                throw new \InvalidArgumentException(
                    $name . ' 계약금액이 산식 계산금액과 1원보다 크게 차이 납니다.'
                );
            }
            $result[] = $component;
        }
        return $result;
    }

    private function validateStoredForApproval(array $contract): void
    {
        foreach ([
            'work_location_detail' => '근무장소',
            'job_description' => '종사업무',
        ] as $column => $label) {
            if (trim((string) ($contract[$column] ?? '')) === '') {
                throw new \RuntimeException($label . '을(를) 입력해야 결재를 요청할 수 있습니다.');
            }
        }
        $isFixedTerm = (string) ($contract['contract_period_type'] ?? '')
            === self::FIXED_TERM_CONTRACT_PERIOD_TYPE;
        if (!$isFixedTerm) {
            if (!empty($contract['contract_end_date'])
                || !empty($contract['fixed_term_reason_code'])
                || !empty($contract['fixed_term_reason_detail'])) {
                throw new \RuntimeException('비기간제 계약의 기간제 전용 정보를 제거하도록 다시 저장해 주세요.');
            }
        } else {
            if (empty($contract['contract_end_date'])) {
                throw new \RuntimeException('기간의 정함이 있는 계약은 계약종료일을 입력해야 결재를 요청할 수 있습니다.');
            }
            $reasonCode = $this->activeCode(
                self::FIXED_TERM_REASON_GROUP,
                $contract['fixed_term_reason_code'] ?? null,
                '기간제 계약 사유'
            );
            if (!in_array($reasonCode, self::FIXED_TERM_REASONS, true)) {
                throw new \RuntimeException('기간제 계약 사유의 코드관리 값을 확인해 주세요.');
            }
            $reasonDetail = $this->nullableString($contract['fixed_term_reason_detail'] ?? null);
            if (in_array($reasonCode, self::FIXED_TERM_DETAIL_REQUIRED, true) && $reasonDetail === null) {
                throw new \RuntimeException('선택한 기간제 계약 사유의 상세 사유를 입력해야 결재를 요청할 수 있습니다.');
            }
            if ($reasonCode === 'PROJECT_COMPLETION' && empty($contract['project_id'])) {
                throw new \RuntimeException('특정 사업·프로젝트 완료 사유는 프로젝트를 선택해야 결재를 요청할 수 있습니다.');
            }
            if ($reasonCode === 'REVIEW_REQUIRED') {
                throw new \RuntimeException('검토 필요 사유는 결재를 요청할 수 없습니다. 적정한 기간제 계약 사유로 변경해 주세요.');
            }
            if ($reasonCode === 'GENERAL' && $this->exceedsTwoYearsOfContinuousEmployment($contract)) {
                throw new \RuntimeException('일반 기간제 계약의 계속근로기간이 2년을 초과하여 결재를 요청할 수 없습니다.');
            }
        }
        $components = $this->components->activeForContract((string) $contract['id'], true);
        if (!array_filter(
            $components,
            static fn(array $row): bool => (string) $row['component_type'] === 'BASE_PAY'
                && (float) $row['amount'] > 0
        )) {
            throw new \RuntimeException('금액이 있는 기본급 항목을 등록해야 합니다.');
        }
    }

    private function validateStoredSchedule(array $contract): void
    {
        $type = (string) ($contract['work_schedule_type'] ?? '');
        $rows = $this->weeklySchedules->forContract((string) $contract['id'], true);
        $policy = $this->schedulePolicies->forContract((string) $contract['id'], true);
        if (in_array($type, ['NORMAL', 'NIGHT'], true)) {
            if ($policy !== null) throw new \RuntimeException('고정 반복근무에 비고정 근무정책이 남아 있습니다. 다시 저장해 주세요.');
            if ($rows === []) {
                throw new \RuntimeException('저장된 주간 근무일정이 없습니다. 근무조건을 입력하고 다시 임시저장해 주세요.');
            }
            $this->validateWeeklySchedules($rows, $type);
            return;
        }
        if (in_array($type, ['SELECTIVE', 'SHIFT'], true) && $rows !== []) throw new \RuntimeException('선택·교대근무에 주간 반복 일정이 남아 있습니다. 다시 저장해 주세요.');
        if ($policy === null) throw new \RuntimeException('근무형태 상세 정책을 저장해야 결재를 요청할 수 있습니다.');
        $this->validateSchedulePolicy($type, $policy);
        if (in_array($type, ['FLEXIBLE', 'OTHER'], true)) $this->validateWeeklySchedules($rows, $type);
    }

    private function exceedsTwoYearsOfContinuousEmployment(array $contract): bool
    {
        if ((string) ($contract['contract_period_type'] ?? '') !== self::FIXED_TERM_CONTRACT_PERIOD_TYPE
            || empty($contract['contract_end_date'])) {
            return false;
        }

        $rows = $this->contracts->employmentPeriodHistory((string) $contract['employee_id']);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(string) $row['id']] = $row;
        }
        $rootId = static function (array $row) use (&$byId): string {
            $id = (string) $row['id'];
            $visited = [];
            while (!empty($row['previous_contract_id'])) {
                $previousId = (string) $row['previous_contract_id'];
                if (isset($visited[$previousId]) || !isset($byId[$previousId])) {
                    break;
                }
                $visited[$previousId] = true;
                $id = $previousId;
                $row = $byId[$previousId];
            }
            return $id;
        };

        $currentRoot = $rootId($contract);
        $latestByRoot = [];
        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])
                || !in_array((string) $row['contract_status'], ['APPROVED', 'TERMINATED'], true)) {
                continue;
            }
            $root = $rootId($row);
            if ($root === $currentRoot) {
                continue;
            }
            if (!isset($latestByRoot[$root])
                || (int) $row['revision_no'] > (int) $latestByRoot[$root]['revision_no']) {
                $latestByRoot[$root] = $row;
            }
        }

        $periodRows = array_values($latestByRoot);
        $periodRows[] = $contract;
        $periods = [];
        foreach ($periodRows as $row) {
            $start = new \DateTimeImmutable((string) $row['contract_start_date']);
            $endValue = (string) ($row['contract_end_date'] ?? '9999-12-31');
            if ((string) ($row['contract_status'] ?? '') === 'TERMINATED' && !empty($row['terminated_at'])) {
                $terminatedDate = substr((string) $row['terminated_at'], 0, 10);
                if ($terminatedDate < $endValue) {
                    $endValue = $terminatedDate;
                }
            }
            $periods[] = ['start' => $start, 'end' => new \DateTimeImmutable($endValue)];
        }
        usort($periods, static fn(array $left, array $right): int => $left['start'] <=> $right['start']);

        $merged = [];
        foreach ($periods as $period) {
            $last = array_key_last($merged);
            if ($last === null || $period['start'] > $merged[$last]['end']->modify('+1 day')) {
                $merged[] = $period;
                continue;
            }
            if ($period['end'] > $merged[$last]['end']) {
                $merged[$last]['end'] = $period['end'];
            }
        }

        $currentStart = new \DateTimeImmutable((string) $contract['contract_start_date']);
        $currentEnd = new \DateTimeImmutable((string) $contract['contract_end_date']);
        foreach ($merged as $period) {
            if ($period['start'] <= $currentStart && $period['end'] >= $currentEnd) {
                return $period['end'] > $period['start']->modify('+2 years');
            }
        }
        return false;
    }

    private function requireContract(string $id): array
    {
        $contract = $id !== '' ? $this->contracts->find($id) : null;
        if (!$contract) {
            throw new \RuntimeException('근로계약을 찾을 수 없습니다.');
        }
        return $contract;
    }

    private function identity(): array
    {
        $actor = ActorHelper::user();
        $parsed = ActorHelper::parse($actor);
        $userId = trim((string) ($parsed['id'] ?? ''));
        if ($userId === '') {
            throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');
        }
        return [$userId, $actor];
    }

    private function contractNo(): string
    {
        return 'EC-' . date('YmdHis') . '-' . strtoupper(substr(str_replace('-', '', UuidHelper::generate()), 0, 6));
    }

    private function date(string $value, string $label): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException($label . ' 형식이 올바르지 않습니다.');
        }
        return $value;
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $this->date($value, $label);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function activeCode(string $group, mixed $value, string $label): string
    {
        $code = trim((string) $value);
        $resolved = $code === '' ? null : $this->codes->resolveActiveCode($group, $code, '');
        if ($resolved === null) {
            throw new \InvalidArgumentException($label . '의 활성 코드관리 값을 확인해 주세요.');
        }
        return $resolved;
    }

    private function requestKey(mixed $value): string
    {
        $key=trim((string)$value);
        if($key===''||strlen($key)>100)throw new \InvalidArgumentException('요청 키를 확인해 주세요.');
        return$key;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $normalized = trim((string) $value);
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException('숫자 입력값의 형식을 확인해 주세요.');
        }
        return (float) $normalized;
    }

    private function formulaNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ','), '0'), '.');
    }

    private function usesComponentFormula(array $component): bool
    {
        return (string) ($component['calculation_type'] ?? '') === 'FORMULA'
            || in_array((string) ($component['component_type'] ?? ''), ['BASE_PAY', 'STATUTORY_PREMIUM'], true)
            || (string) ($component['component_code'] ?? '') === 'ANNUAL_LEAVE_ALLOWANCE';
    }

    private function boolean(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
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
}
