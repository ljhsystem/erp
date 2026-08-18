<?php

declare(strict_types=1);

use App\Services\Institution\EmploymentContractService;
use App\Services\Approval\EmploymentContractApprovalAdapter;
use App\Models\Approval\ApprovalInboxModel;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$first = static function (PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('테스트 선행 데이터를 찾을 수 없습니다.');
    }
    return $row;
};
$code = static fn(string $group): string => (string) $first(
    $pdo,
    'SELECT code FROM system_codes
     WHERE code_group = :code_group AND is_active = 1
     ORDER BY sort_no LIMIT 1',
    [':code_group' => $group]
)['code'];
$fixedReasonCode = static fn(string $code): string => (string) $first(
    $pdo,
    'SELECT code FROM system_codes
     WHERE code_group = :code_group AND code = :code
       AND is_active = 1 LIMIT 1',
    [':code_group' => 'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON', ':code' => $code]
)['code'];
$contractClassificationCode = static fn(string $group, string $code): string => (string) $first(
    $pdo,
    'SELECT code FROM system_codes
     WHERE code_group = :code_group AND code = :code
       AND is_active = 1 LIMIT 1',
    [':code_group' => $group, ':code' => $code]
)['code'];

$employee = $first($pdo, 'SELECT id FROM user_employees ORDER BY id LIMIT 1');
$user = $first($pdo, 'SELECT id FROM auth_users WHERE is_active = 1 ORDER BY id LIMIT 1');
$payComponent = $first(
    $pdo,
    "SELECT * FROM institution_employment_contracts_pay_components
     WHERE component_type = 'BASE_PAY' AND is_active = 1 AND deleted_at IS NULL
     ORDER BY sort_no LIMIT 1"
);
$allowanceComponent = $first(
    $pdo,
    "SELECT * FROM institution_employment_contracts_pay_components
     WHERE component_type <> 'BASE_PAY' AND is_active = 1 AND deleted_at IS NULL
     ORDER BY sort_no LIMIT 1"
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user'] = ['id' => $user['id']];
$_SESSION['auth_state'] = ['user_id' => $user['id'], 'status' => 'NORMAL'];

$payload = [
    'employee_id' => $employee['id'],
    'employee_name_snapshot' => '위조 이름',
    'employer_name_snapshot' => '위조 회사',
    'contract_type' => $code('EMPLOYMENT_CONTRACT_TYPE'),
    'contract_period_type' => $contractClassificationCode('EMPLOYMENT_CONTRACT_PERIOD_TYPE', 'INDEFINITE'),
    'employment_category' => $contractClassificationCode('EMPLOYMENT_CATEGORY', 'GENERAL'),
    'working_time_type' => $contractClassificationCode('EMPLOYMENT_WORKING_TIME_TYPE', 'FULL_TIME'),
    'contract_start_date' => date('Y-m-d'),
    'work_location_type' => $code('WORK_LOCATION_TYPE'),
    'work_location_detail' => '원자적 저장 검증',
    'job_description' => '근로계약 저장 검증',
    'work_schedule_type' => $code('WORK_SCHEDULE_TYPE'),
    // API가 보낸 projection은 신뢰하지 않고 weekly_schedules에서 다시 계산해야 한다.
    'weekly_schedules' => array_map(static fn(int $day): array => [
        'day_of_week' => $day,
        'day_type' => $day <= 5 ? 'WORKDAY' : ($day === 7 ? 'WEEKLY_HOLIDAY' : 'UNPAID_DAY_OFF'),
        'start_time' => $day <= 5 ? '09:00' : null,
        'end_time' => $day <= 5 ? '18:00' : null,
        'end_day_offset' => $day <= 5 ? 0 : null,
        'break_minutes' => $day <= 5 ? 60 : null,
        'break_schedules' => $day <= 5 ? [[
            'start_time' => '12:00',
            'end_time' => '13:00',
            'end_day_offset' => 0,
        ]] : [],
    ], range(1, 7)),
    'salary_type' => $code('SALARY_TYPE'),
    'payment_day' => 25,
    'payment_timing' => $code('PAYMENT_TIMING'),
    'components' => [[
        'pay_component_id' => $payComponent['id'],
        'amount' => 3000000,
        'quantity' => 209,
        'rate' => 3000000 / 209,
    ], [
        'pay_component_id' => $allowanceComponent['id'],
        'amount' => 200000,
    ]],
];
if ((string) $allowanceComponent['component_type'] === 'STATUTORY_PREMIUM') {
    $payload['components'][1] += [
        'work_type' => 'OVERTIME',
        'quantity' => 10,
        'premium_rate' => 1.5,
        'excess_payment_policy' => 'SEPARATE_PAYMENT',
        'agreement_basis' => '통합 테스트 근로수당 약정',
    ];
    $payload['components'][1]['amount'] = round(10 * 1.5 * (3000000 / 209));
} elseif ((string) $allowanceComponent['default_calculation_type'] === 'FORMULA') {
    $payload['components'][1] += ['quantity' => 10];
    $payload['components'][1]['amount'] = round(10 * (3000000 / 209));
}

$expectedTables = [
    'institution_employment_contracts' => true,
    'institution_employment_contracts_components' => true,
    'institution_employment_contracts_weekly_schedules' => true,
    'institution_employment_contracts_work_schedule_policies' => true,
    'institution_employment_contracts_pay_components' => true,
    'institution_pay_components' => false,
    ('institution_employment_contract_' . 'schedules') => false,
    ('institution_employment_contract_schedule_' . 'breaks') => false,
];
foreach ($expectedTables as $table => $expected) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    if (((int) $stmt->fetchColumn() === 1) !== $expected) {
        throw new RuntimeException('실제 근로계약 테이블 구조가 예상과 다릅니다: ' . $table);
    }
}
$pdo->beginTransaction();
try {
    $service = new EmploymentContractService($pdo);
    $save = static function (EmploymentContractService $service, array $savePayload): array {
        $savePayload['request_key'] = 'fixture-save-' . bin2hex(random_bytes(12));
        return $service->save($savePayload);
    };
    (new ApprovalInboxModel($pdo))->requestDetail(
        '00000000-0000-0000-0000-000000000000',
        (string) $user['id']
    );
    $formOptions = $service->formOptions();
    $assertSortedBySortNo = static function (array $rows, string $label): void {
        $previous = null;
        foreach ($rows as $row) {
            $current = (int) ($row['sort_no'] ?? $row['meta']['sort_no'] ?? 0);
            if ($previous !== null && $current < $previous) {
                throw new RuntimeException($label . ' 목록이 순번 오름차순이 아닙니다.');
            }
            $previous = $current;
        }
    };
    $assertSortedBySortNo($formOptions['pay_components'] ?? [], '급여항목');
    $monthlySummary = $service->compensationSummary([
        ['amount' => 653011], ['amount' => 304634], ['amount' => 31245], ['amount' => 100000],
    ], 'MONTHLY');
    if ((float) $monthlySummary['total_amount'] !== 1088890.0
        || (float) $monthlySummary['converted_amount'] !== 13066680.0) {
        throw new RuntimeException('월 지급합계와 연 환산액 계산 결과가 올바르지 않습니다.');
    }
    $annualSummary = $service->compensationSummary([['amount' => 12000000]], 'ANNUAL');
    if ((float) $annualSummary['converted_amount'] !== 1000000.0) {
        throw new RuntimeException('연봉의 월 환산액 계산 결과가 올바르지 않습니다.');
    }
    $formula = $service->componentCalculation([
        'calculation_type' => 'FORMULA',
        'component_type' => 'STATUTORY_PREMIUM',
        'quantity' => 65,
        'premium_rate' => 1.5,
        'rate' => 3124.455,
        'amount' => 304634,
    ]);
    if ($formula['formula_display'] !== '65시간 × 1.5배 × 3,124.455원'
        || (float) $formula['calculated_amount'] !== 304634.0) {
        throw new RuntimeException('지급항목 산식 표시 또는 원 단위 반올림 결과가 올바르지 않습니다.');
    }
    $invalidCases = [];
    $missingDay = $payload;
    array_pop($missingDay['weekly_schedules']);
    try {
        $save($service, $missingDay);
        throw new RuntimeException('요일 누락 일정이 저장되었습니다.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), '일요일 일정이 누락')) throw $exception;
    }
    $invalidCases[] = $missingDay;
    $overnight = $payload;
    $overnight['weekly_schedules'][0]['start_time'] = '18:00';
    $overnight['weekly_schedules'][0]['end_time'] = '09:00';
    $invalidCases[] = $overnight;
    $holidayConflict = $payload;
    $holidayConflict['weekly_schedules'][6]['day_type'] = 'WORKDAY';
    $holidayConflict['weekly_schedules'][6]['start_time'] = '09:00';
    $holidayConflict['weekly_schedules'][6]['end_time'] = '18:00';
    $holidayConflict['weekly_schedules'][6]['end_day_offset'] = 0;
    $holidayConflict['weekly_schedules'][6]['break_minutes'] = 60;
    $invalidCases[] = $holidayConflict;
    $paidWorkday = $payload;
    $paidWorkday['weekly_schedules'][0]['day_type'] = 'COMPANY_PAID_HOLIDAY';
    $invalidCases[] = $paidWorkday;
    $nonWorkdayTime = $payload;
    $nonWorkdayTime['weekly_schedules'][5]['start_time'] = '09:00';
    $invalidCases[] = $nonWorkdayTime;
    $duplicateDay = $payload;
    $duplicateDay['weekly_schedules'][6]['day_of_week'] = 6;
    $invalidCases[] = $duplicateDay;
    foreach ($invalidCases as $invalidPayload) {
        try {
            $save($service, $invalidPayload);
            throw new RuntimeException('근무조건 Validation이 잘못된 값을 허용했습니다.');
        } catch (InvalidArgumentException) {
        }
    }
    $missingFixedReasonCodes = [];
    foreach ([
        'GENERAL', 'PROJECT_COMPLETION', 'TASK_COMPLETION', 'REPLACEMENT',
        'SENIOR', 'STATUTORY_EXCEPTION', 'OTHER', 'REVIEW_REQUIRED',
    ] as $reasonCode) {
        try {
            $fixedReasonCode($reasonCode);
        } catch (RuntimeException) {
            $missingFixedReasonCodes[] = $reasonCode;
        }
    }

    $indefinite = array_replace($payload, [
        'contract_end_date' => (new DateTimeImmutable($payload['contract_start_date']))->modify('+1 year')->format('Y-m-d'),
        'fixed_term_reason_code' => 'OTHER',
        'fixed_term_reason_detail' => '저장되면 안 되는 값',
    ]);
    $indefiniteId = (string) $save($service, $indefinite)['data']['id'];
    $indefiniteContract = $service->detail($indefiniteId)['data']['contract'];
    if ($indefiniteContract['contract_end_date'] !== null
        || $indefiniteContract['fixed_term_reason_code'] !== null
        || $indefiniteContract['fixed_term_reason_detail'] !== null) {
        throw new RuntimeException('무기계약의 기간제 사유가 NULL로 정규화되지 않았습니다.');
    }

    $fixedEnd = (new DateTimeImmutable($payload['contract_start_date']))->modify('+1 year')->format('Y-m-d');
    $generalPayload = array_replace($payload, [
        'contract_period_type' => $contractClassificationCode('EMPLOYMENT_CONTRACT_PERIOD_TYPE', 'FIXED_TERM'),
        'contract_end_date' => $fixedEnd,
        'fixed_term_reason_code' => 'GENERAL',
        'fixed_term_reason_detail' => '',
    ]);
    $generalId = (string) $save($service, $generalPayload)['data']['id'];
    $generalContract = $service->detail($generalId)['data']['contract'];
    if ($generalContract['fixed_term_reason_code'] !== 'GENERAL'
        || $generalContract['employment_category'] !== 'GENERAL'
        || $generalContract['working_time_type'] !== 'FULL_TIME') {
        throw new RuntimeException('기간제 계약의 분류체계가 저장·조회되지 않았습니다.');
    }
    $indefiniteSortNo = (int) $indefiniteContract['sort_no'];
    $generalSortNo = (int) $generalContract['sort_no'];
    $service->reorder([
        ['id' => $indefiniteId, 'newSortNo' => $generalSortNo],
        ['id' => $generalId, 'newSortNo' => $indefiniteSortNo],
    ]);
    if ((int) $service->detail($indefiniteId)['data']['contract']['sort_no'] !== $generalSortNo
        || (int) $service->detail($generalId)['data']['contract']['sort_no'] !== $indefiniteSortNo) {
        throw new RuntimeException('근로계약 공용 순서변경 결과가 저장되지 않았습니다.');
    }
    $flexiblePayload = array_replace($generalPayload, [
        'work_schedule_type' => $contractClassificationCode('WORK_SCHEDULE_TYPE', 'FLEXIBLE'),
        'work_schedule_policy' => [
            'settlement_period_days' => 28,
            'reference_weekly_hours' => 40, 'policy_detail' => '4주 정산 기준 반복일정',
        ],
    ]);
    $flexibleId = (string) $save($service, $flexiblePayload)['data']['id'];
    $flexibleDetail = $service->detail($flexibleId)['data'];
    if (count($flexibleDetail['weekly_schedules']) !== 7
        || (int) ($flexibleDetail['work_schedule_policy']['settlement_period_days'] ?? 0) !== 28) {
        throw new RuntimeException('탄력근무의 기준 반복일정과 추가정책이 함께 저장·조회되지 않았습니다.');
    }
    $pdo->prepare("UPDATE institution_employment_contracts SET contract_status = 'APPROVED' WHERE id = :id")
        ->execute([':id' => $flexibleId]);
    $flexibleRevision = $service->revise($flexibleId, '탄력근무 일정·정책 복사 검증', 'fixture-revise-flexible');
    $flexibleRevisionDetail = $service->detail((string) $flexibleRevision['data']['id'])['data'];
    if (count($flexibleRevisionDetail['weekly_schedules']) !== 7
        || (int) ($flexibleRevisionDetail['work_schedule_policy']['settlement_period_days'] ?? 0) !== 28) {
        throw new RuntimeException('계약 개정 시 탄력근무 일정·정책이 복사되지 않았습니다.');
    }
    $approvalDetail = (new EmploymentContractApprovalAdapter($pdo))->detail([
        'id' => 'rollback-test-request', 'document_id' => (string) $flexibleRevision['data']['id'],
    ]);
    if (count($approvalDetail['weekly_schedules']) !== 7
        || (int) ($approvalDetail['work_schedule_policy']['settlement_period_days'] ?? 0) !== 28) {
        throw new RuntimeException('결재 상세에 탄력근무 일정·정책이 함께 복원되지 않았습니다.');
    }
    $transitionId = (string) $save($service, $generalPayload)['data']['id'];
    $save($service, array_replace($flexiblePayload, ['id' => $transitionId]));
    $transitionFlexible = $service->detail($transitionId)['data'];
    if (count($transitionFlexible['weekly_schedules']) !== 7
        || $transitionFlexible['work_schedule_policy'] === null
        || (float) $transitionFlexible['schedule_summary']['weekly_hours'] !== 40.0) {
        throw new RuntimeException('일반근무에서 탄력근무 전환 시 일정·정책·헤더 요약이 동기화되지 않았습니다.');
    }
    $selectivePayload = array_replace($generalPayload, [
        'id' => $transitionId,
        'work_schedule_type' => $contractClassificationCode('WORK_SCHEDULE_TYPE', 'SELECTIVE'),
        'weekly_schedules' => [],
        'work_schedule_policy' => [
            'reference_weekly_hours' => 40,
            'selectable_start_time' => '07:00', 'selectable_end_time' => '22:00',
            'core_start_time' => '10:00', 'core_end_time' => '15:00',
            'policy_detail' => '선택근무 전환 검증',
        ],
    ]);
    $save($service, $selectivePayload);
    $transitionSelective = $service->detail($transitionId)['data'];
    if ($transitionSelective['weekly_schedules'] !== []
        || (float) ($transitionSelective['work_schedule_policy']['reference_weekly_hours'] ?? 0) !== 40.0
        || (float) $transitionSelective['schedule_summary']['weekly_hours'] !== 40.0) {
        throw new RuntimeException('탄력근무에서 선택근무 전환 시 반복일정 정리와 정책 요약이 동기화되지 않았습니다.');
    }
    $save($service, array_replace($generalPayload, ['id' => $transitionId]));
    $transitionNormal = $service->detail($transitionId)['data'];
    if (count($transitionNormal['weekly_schedules']) !== 7
        || $transitionNormal['work_schedule_policy'] !== null) {
        throw new RuntimeException('선택근무에서 일반근무 전환 시 정책 정리와 반복일정 복원이 실패했습니다.');
    }
    try {
        $save($service, array_replace($generalPayload, ['contract_end_date' => '']));
        throw new RuntimeException('기간의 정함이 있는 계약의 계약종료일 누락이 허용되었습니다.');
    } catch (InvalidArgumentException) {
    }

    $requiredDetailCodes = ['PROJECT_COMPLETION', 'TASK_COMPLETION', 'REPLACEMENT', 'STATUTORY_EXCEPTION', 'OTHER'];
    foreach ($requiredDetailCodes as $reasonCode) {
        try {
            $save($service, array_replace($generalPayload, [
                'fixed_term_reason_code' => $reasonCode,
                'fixed_term_reason_detail' => '',
            ]));
            throw new RuntimeException($reasonCode . ' 상세 사유 누락이 허용되었습니다.');
        } catch (InvalidArgumentException) {
        }
    }
    try {
        $save($service, array_replace($generalPayload, [
            'fixed_term_reason_code' => 'PROJECT_COMPLETION',
            'fixed_term_reason_detail' => '프로젝트 완료 시까지',
            'project_id' => '',
        ]));
        throw new RuntimeException('프로젝트 완료 사유의 프로젝트 누락이 허용되었습니다.');
    } catch (InvalidArgumentException) {
    }

    $reviewId = (string) $save($service, array_replace($generalPayload, [
        'fixed_term_reason_code' => 'REVIEW_REQUIRED',
        'fixed_term_reason_detail' => '노무 검토 필요',
    ]))['data']['id'];
    $approvalValidator = new ReflectionMethod(EmploymentContractService::class, 'validateStoredForApproval');
    try {
        $approvalValidator->invoke($service, $service->detail($reviewId)['data']['contract']);
        throw new RuntimeException('검토 필요 계약의 결재 검증이 허용되었습니다.');
    } catch (ReflectionException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '검토 필요 사유')) {
            throw $exception;
        }
    }

    $pdo->prepare(
        "UPDATE institution_employment_contracts SET contract_status = 'APPROVED' WHERE id = :id"
    )->execute([':id' => $generalId]);
    $continuousPayload = array_replace($generalPayload, [
        'contract_start_date' => (new DateTimeImmutable($fixedEnd))->modify('+1 day')->format('Y-m-d'),
        'contract_end_date' => (new DateTimeImmutable($payload['contract_start_date']))->modify('+2 years +1 day')->format('Y-m-d'),
    ]);
    $continuousId = (string) $save($service, $continuousPayload)['data']['id'];
    try {
        $approvalValidator->invoke($service, $service->detail($continuousId)['data']['contract']);
        throw new RuntimeException('2년 초과 일반 기간제 계약의 결재 검증이 허용되었습니다.');
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '2년을 초과')) {
            throw $exception;
        }
    }

    $revisionPayload = array_replace($generalPayload, [
        'contract_start_date' => $payload['contract_start_date'],
        'contract_end_date' => (new DateTimeImmutable($payload['contract_start_date']))->modify('+2 years')->format('Y-m-d'),
    ]);
    $revisionId = (string) $save($service, $revisionPayload)['data']['id'];
    $pdo->prepare(
        'UPDATE institution_employment_contracts
         SET previous_contract_id = :previous_id, revision_no = 2 WHERE id = :id'
    )->execute([':previous_id' => $generalId, ':id' => $revisionId]);
    $approvalValidator->invoke($service, $service->detail($revisionId)['data']['contract']);

    $revised = $service->revise($generalId, '고용구분 SSOT 개정 복사 검증', 'fixture-revise-general');
    $revisedContract = $service->detail((string) $revised['data']['id'])['data']['contract'];
    if ($revisedContract['previous_contract_id'] !== $generalId
        || (int) $revisedContract['revision_no'] !== 2
        || $revisedContract['employment_category'] !== $generalContract['employment_category']
        || $revisedContract['working_time_type'] !== $generalContract['working_time_type']) {
        throw new RuntimeException('계약 개정 시 고용구분·근로시간 구분이 복사되지 않았습니다.');
    }

    $categoryList = $service->list([
        'search' => ['value' => 'GENERAL'],
        'columns' => [['data' => 'employment_category']],
        'order' => [['column' => 0, 'dir' => 'asc']],
        'start' => 0,
        'length' => 50,
    ]);
    if ($categoryList['recordsFiltered'] < 1
        || array_filter(
            $categoryList['data'],
            static fn(array $row): bool => ($row['employment_category'] ?? null) !== 'GENERAL'
        )) {
        throw new RuntimeException('고용구분 검색·정렬 결과가 예상과 다릅니다.');
    }

    $pdo->prepare(
        'UPDATE system_codes SET is_active = 0
         WHERE code_group = :code_group AND code = :code'
    )->execute([':code_group' => 'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON', ':code' => 'GENERAL']);
    if ($service->detail($generalId)['data']['contract']['fixed_term_reason_name'] === '') {
        throw new RuntimeException('비활성 기간제 사유의 코드명이 조회되지 않았습니다.');
    }
    try {
        $save($service, $generalPayload);
        throw new RuntimeException('비활성 기간제 사유 코드가 신규 저장에 허용되었습니다.');
    } catch (InvalidArgumentException) {
    }
    $pdo->prepare(
        'UPDATE system_codes SET is_active = 1
         WHERE code_group = :code_group AND code = :code'
    )->execute([':code_group' => 'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON', ':code' => 'GENERAL']);
    $invalidComponentPayload = $payload;
    $invalidComponentPayload['components'][0]['pay_component_id'] = '00000000-0000-0000-0000-000000000000';
    try {
        $save($service, $invalidComponentPayload);
        throw new RuntimeException('존재하지 않는 급여항목이 허용되었습니다.');
    } catch (InvalidArgumentException) {
    }
    $zeroAmountPayload = $payload;
    $zeroAmountPayload['components'][0]['amount'] = 0;
    try {
        $save($service, $zeroAmountPayload);
        throw new RuntimeException('0원 지급항목이 허용되었습니다.');
    } catch (InvalidArgumentException) {
    }
    $duplicateComponentPayload = $payload;
    $duplicateComponentPayload['components'][1]['pay_component_id'] = $duplicateComponentPayload['components'][0]['pay_component_id'];
    try {
        $save($service, $duplicateComponentPayload);
        throw new RuntimeException('중복 지급항목이 허용되었습니다.');
    } catch (InvalidArgumentException) {
    }
    $formulaMismatchPayload = $payload;
    if ((string) $allowanceComponent['default_calculation_type'] === 'FORMULA') {
        $formulaMismatchPayload['components'][1]['amount'] += 2;
        try {
            $save($service, $formulaMismatchPayload);
            throw new RuntimeException('산식 계산금액과 허용오차를 초과한 계약금액이 허용되었습니다.');
        } catch (InvalidArgumentException) {
        }
    }
    $saved = $save($service, $payload);
    $id = (string) $saved['data']['id'];
    $detail = $service->detail($id)['data'];
    if (count($detail['components']) !== 2
        || (float) $detail['compensation_summary']['total_amount'] !== array_sum(array_column($payload['components'], 'amount'))
        || count($detail['weekly_schedules']) !== 7
        || (float) $detail['schedule_summary']['weekly_hours'] !== 40.0
        || (int) $detail['schedule_summary']['weekly_holiday'] !== 7
        || $detail['contract']['employee_name_snapshot'] === '위조 이름'
        || $detail['contract']['employer_name_snapshot'] === '위조 회사'
    ) {
        throw new RuntimeException('저장 후 상세 복원 결과가 예상과 다릅니다.');
    }
    $payload['id'] = $id;
    $payload['weekly_schedules'][4]['end_time'] = '16:00';
    $save($service, $payload);
    $updated = $service->detail($id)['data'];
    if ((float) $updated['schedule_summary']['weekly_hours'] !== 38.0
        || count($updated['weekly_schedules']) !== 7 || count($updated['components']) !== 2) {
        throw new RuntimeException('수정 저장 결과가 예상과 다릅니다.');
    }
    $pdo->prepare('DELETE FROM institution_employment_contracts_weekly_schedules WHERE contract_id = :id')
        ->execute([':id' => $id]);
    $storedScheduleValidator = new ReflectionMethod(EmploymentContractService::class, 'validateStoredSchedule');
    try {
        $storedScheduleValidator->invoke($service, $updated['contract']);
        throw new RuntimeException('저장 일정 0건인 계약의 결재 검증이 허용되었습니다.');
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '저장된 주간 근무일정이 없습니다')) throw $exception;
    }
    $save($service, $payload);
    if (count($service->detail($id)['data']['weekly_schedules']) !== 7) {
        throw new RuntimeException('일정 0건 계약의 수정 임시저장 후 7행이 복원되지 않았습니다.');
    }
    $pdo->prepare(
        'DELETE FROM institution_employment_contracts_components WHERE contract_id = :id'
    )->execute([':id' => $id]);
    try {
        $service->submit($id, 'fixture-submit-zero');
        throw new RuntimeException('지급조건 0건인 계약의 결재요청이 허용되었습니다.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), '지급조건을 한 건 이상')) throw $exception;
    }
    $save($service, $payload);
    $pdo->prepare(
        'UPDATE institution_employment_contracts_components SET amount = 0 WHERE contract_id = :id'
    )->execute([':id' => $id]);
    try {
        $service->submit($id, 'fixture-submit-review');
        throw new RuntimeException('월 지급합계가 0원인 계약의 결재요청이 허용되었습니다.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), '지급조건 월 합계가 0원')) throw $exception;
    }
    $save($service, $payload);
    $service->delete($id, 'fixture-delete-first');
    $trash = $service->trash(['start' => 0, 'length' => 50]);
    if (!array_filter($trash['data'], static fn(array $row): bool => (string) $row['id'] === $id)) {
        throw new RuntimeException('휴지통 조회에서 삭제한 계약을 찾을 수 없습니다.');
    }
    $service->restore($id, 'fixture-restore');
    if ($service->detail($id)['data']['contract']['deleted_at'] !== null) {
        throw new RuntimeException('근로계약 복원 결과가 올바르지 않습니다.');
    }
    $service->delete($id, 'fixture-delete-second');
    $service->purge($id, 'fixture-purge');
    if ($pdo->query("SELECT COUNT(*) FROM institution_employment_contracts WHERE id = " . $pdo->quote($id))->fetchColumn() !== 0) {
        throw new RuntimeException('테스트 계약 완전삭제가 처리되지 않았습니다.');
    }
    if ($missingFixedReasonCodes !== []) {
        throw new RuntimeException(
            '코드관리에 활성 기간제 계약 사유가 누락되었습니다: ' . implode(', ', $missingFixedReasonCodes)
        );
    }
    $pdo->rollBack();
    echo "employment-contract atomic save/restore: OK\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString() . PHP_EOL);
    exit(1);
}
