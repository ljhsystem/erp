<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\Institution\DailyEmploymentIncomeCalculationSourceService;
use App\Services\Institution\DailyEmploymentIncomeService;
use Core\Database;
use Core\Session;

$db = Database::getInstance()->getConnection();
Session::start(30);
$userId = $db->query('SELECT id FROM auth_users WHERE approved = 1 AND is_active = 1 ORDER BY created_at,id LIMIT 1')->fetchColumn();
if (!$userId) throw new RuntimeException('Fixture Actor 사용자를 찾을 수 없습니다.');
$_SESSION['user'] = ['id' => (string) $userId];
$_SESSION['auth_state'] = ['user_id' => (string) $userId, 'status' => 'NORMAL'];

$workerStatement = $db->prepare("SELECT id,client_name,client_type FROM system_clients WHERE client_name=:name AND is_active=1 AND deleted_at IS NULL ORDER BY id");
$workerStatement->execute([':name' => '정순옥']);
$workers = $workerStatement->fetchAll();
if (count($workers) !== 1) throw new RuntimeException('정순옥 활성 작업자를 하나로 확정할 수 없습니다.');
$worker = $workers[0];
$workType = $db->query("SELECT code FROM system_codes WHERE code_group='WORK_TYPE' AND is_active=1 ORDER BY sort_no,code LIMIT 1")->fetchColumn();
if (!$workType) throw new RuntimeException('활성 공종코드를 찾을 수 없습니다.');

$workDates = ['2013-08-06', '2013-08-07', '2013-08-08', '2013-08-09', '2013-08-10'];
$workdays = array_map(static fn(string $date): array => [
    'work_date' => $date,
    'actual_work_minutes' => 480,
    'daily_rate_amount' => 90000,
    'taxable_additional_amount' => 0,
    'non_taxable_additional_amount' => 0,
], $workDates);
$workdays[4]['taxable_additional_amount'] = 2940;
$payload = [
    'id' => null,
    'request_key' => 'fixture-daily-201308-' . bin2hex(random_bytes(8)),
    'income_year_month' => '2013-08',
    'payment_date' => '2013-09-11',
    'document_title' => '2013-08 정순옥 일용근로소득 Fixture',
    'description' => '트랜잭션 롤백 검증',
    'memo' => null,
    'groups' => [[
        'business_unit' => 'HQ',
        'project_id' => null,
        'work_team_id' => null,
        'work_description' => '본사 일용업무',
        'employment_insurance_application_status_code' => 'APPLICABLE',
        'employment_insurance_decision_reason' => null,
        'employment_insurance_decision_source_code' => 'MANUAL_INTERIM_GROUP',
        'industrial_accident_application_status_code' => 'EXCLUDED',
        'industrial_accident_decision_reason' => 'Fixture 산재보험 미적용',
        'industrial_accident_decision_source_code' => 'MANUAL_INTERIM_GROUP',
        'items' => [[
            'worker_client_id' => (string) $worker['id'],
            'work_type_code' => (string) $workType,
            'work_description' => '본사 지원업무',
            'workdays' => $workdays,
        ]],
    ]],
];

$service = new DailyEmploymentIncomeService($db);
$workerOptions = $service->options(['option_type' => 'worker', 'q' => '정순옥', 'page' => 1])['data'];
$workTypeOptions = $service->options(['option_type' => 'work_type', 'q' => (string) $workType, 'page' => 1])['data'];
if (!in_array((string) $worker['id'], array_column($workerOptions['results'], 'id'), true)) {
    throw new RuntimeException('작업자 Picker에서 정순옥 기준정보를 조회하지 못했습니다.');
}
if (!in_array((string) $workType, array_column($workTypeOptions['results'], 'id'), true)) {
    throw new RuntimeException('공종 Picker에서 기준정보를 조회하지 못했습니다.');
}
$preview = $service->calculate($payload)['data'];
$previewItem = $preview['groups'][0]['items'][0];
$validationResults = [];
$assertRejectedMinutes = static function (DailyEmploymentIncomeService $service, array $basePayload, mixed $minutes, string $label) use (&$validationResults): void {
    $candidate = $basePayload;
    $candidate['groups'][0]['items'][0]['workdays'] = [$candidate['groups'][0]['items'][0]['workdays'][0]];
    $candidate['groups'][0]['items'][0]['workdays'][0]['actual_work_minutes'] = $minutes;
    try {
        $service->calculate($candidate);
        throw new RuntimeException($label . ' 검증이 차단되지 않았습니다.');
    } catch (InvalidArgumentException) {
        $validationResults[$label] = 'BLOCKED';
    }
};
foreach ([[null, '빈 값'], [0, '0분'], [-1, '음수'], ['1.5', '소수'], [1441, '1,441분']] as [$minutes, $label]) {
    $assertRejectedMinutes($service, $payload, $minutes, $label);
}
$maxPayload = $payload;
$maxPayload['groups'][0]['items'][0]['workdays'] = [$maxPayload['groups'][0]['items'][0]['workdays'][0]];
$maxPayload['groups'][0]['items'][0]['workdays'][0]['actual_work_minutes'] = 1440;
$service->calculate($maxPayload);
$validationResults['1,440분'] = 'ALLOWED';

$splitPayload = $payload;
$splitPayload['groups'][0]['items'][0]['workdays'] = [$splitPayload['groups'][0]['items'][0]['workdays'][0]];
$splitPayload['groups'][0]['items'][0]['workdays'][0]['actual_work_minutes'] = 720;
$secondGroup = $splitPayload['groups'][0];
$secondGroup['client_key'] = 'fixture-second-group';
$secondGroup['work_description'] = '본사 일용업무 2';
$secondGroup['items'][0]['client_key'] = 'fixture-second-item';
$secondGroup['items'][0]['work_description'] = '본사 지원업무 2';
$secondGroup['items'][0]['workdays'][0]['actual_work_minutes'] = 720;
$splitPayload['groups'][] = $secondGroup;
$splitResult = $service->calculate($splitPayload)['data'];
if (count($splitResult['worktime_warnings'] ?? []) !== 1) throw new RuntimeException('복수 Group 확인 경고가 반환되지 않았습니다.');
$validationResults['복수 Group 합계 1,440분'] = 'ALLOWED_WITH_WARNING';
$splitPayload['groups'][1]['items'][0]['workdays'][0]['actual_work_minutes'] = 721;
try {
    $service->calculate($splitPayload);
    throw new RuntimeException('복수 Group 합계 1,441분이 차단되지 않았습니다.');
} catch (InvalidArgumentException) {
    $validationResults['복수 Group 합계 1,441분'] = 'BLOCKED';
}
$daily = [];
foreach ($previewItem['workdays'] as $day) {
    $daily[] = [
        'work_date' => $day['work_date'],
        'base_pay_amount' => $day['base_pay_amount'],
        'taxable_additional_amount' => $day['allowance_amount'],
        'non_taxable_additional_amount' => $day['non_taxable_amount'],
        'taxable_amount' => $day['taxable_amount'],
        'non_taxable_amount' => $day['non_taxable_amount'],
        'gross_amount' => $day['gross_amount'],
        'daily_income_deduction_amount' => $day['daily_income_deduction_amount'],
        'income_tax_base_amount' => $day['income_tax_base_amount'],
        'calculated_income_tax_amount' => $day['calculated_income_tax_amount'],
        'earned_income_tax_credit_amount' => $day['earned_income_tax_credit_amount'],
        'income_tax_amount' => $day['income_tax_amount'],
        'local_income_tax_amount' => $day['local_income_tax_amount'],
        'worker_social_insurance_amount' => $day['worker_social_insurance_amount'],
        'other_deduction_amount' => $day['other_deduction_amount'],
        'deduction_amount' => $day['deduction_amount'],
        'net_payment_amount' => $day['net_payment_amount'],
        'lines' => $day['lines'],
    ];
}

foreach ($daily as $day) {
    foreach (['income_tax_base_amount', 'calculated_income_tax_amount', 'income_tax_amount', 'local_income_tax_amount', 'worker_social_insurance_amount', 'other_deduction_amount', 'deduction_amount'] as $field) {
        if ((float) $day[$field] < 0) throw new RuntimeException("{$field} 비음수 불변조건 위반");
    }
    if ((float) $day['net_payment_amount'] > (float) $day['gross_amount']) throw new RuntimeException('실지급액 상한 불변조건 위반');
}
if ((float) $previewItem['summary']['total_gross_amount'] !== 452940.0
    || (float) $previewItem['summary']['total_deduction_amount'] !== 2940.0
    || (float) $previewItem['summary']['total_net_payment_amount'] !== 450000.0) {
    throw new RuntimeException('2013-08 Preview의 지급액·고용보험·실지급액 대사가 일치하지 않습니다.');
}
$employmentLine = current(array_filter($previewItem['lines'] ?? [], static fn(array $line): bool =>
    ($line['line_type_code'] ?? '') === 'DEDUCTION' && ($line['line_code'] ?? '') === 'EMPLOYMENT_INSURANCE'
)) ?: null;
if (!is_array($employmentLine)
    || (float) $employmentLine['calculation_basis_amount'] !== 452940.0
    || round((float) $employmentLine['calculation_before_rounding'], 2) !== 2944.11
    || (float) $employmentLine['calculated_amount'] !== 2940.0
    || (float) $employmentLine['final_amount'] !== 2940.0) {
    throw new RuntimeException('정순옥 고용보험 Item Line 계산결과가 공식 기대값과 일치하지 않습니다.');
}
$expectedInsurance = ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'];
foreach ($expectedInsurance as $code) {
    $line = current(array_filter($previewItem['lines'] ?? [], static fn(array $row): bool =>
        ($row['line_type_code'] ?? '') === 'DEDUCTION' && ($row['line_code'] ?? '') === $code
    )) ?: null;
    if (!is_array($line) || ($line['application_status_code'] ?? '') !== 'CONFIRMATION_REQUIRED'
        || ($line['calculation_basis_amount'] ?? null) !== null
        || ($line['calculated_amount'] ?? null) !== null
        || (float) ($line['final_amount'] ?? -1) !== 0.0
        || empty($line['statutory_standard_id'])) {
        throw new RuntimeException($code . ' 가입자격 선행판정 계약이 일치하지 않습니다.');
    }
}

$storageCounts = static fn(PDO $connection): array => [
    'headers' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn(),
    'groups' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_groups')->fetchColumn(),
    'items' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),
    'workdays' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_workdays')->fetchColumn(),
    'lines' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn(),
];
$countBefore = $storageCounts($db);
try {
    $service->save($payload);
    throw new RuntimeException('가입자격 미확정 문서 저장이 차단되지 않았습니다.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), '가입자격')) throw $exception;
}
if ($countBefore !== $storageCounts($db)) throw new RuntimeException('가입자격 차단 Fixture가 운영 DB를 변경했습니다.');
echo json_encode([
    'success' => true,
    'gross_amount' => $previewItem['summary']['total_gross_amount'],
    'deduction_amount' => $previewItem['summary']['total_deduction_amount'],
    'net_payment_amount' => $previewItem['summary']['total_net_payment_amount'],
    'eligibility_status' => array_fill_keys($expectedInsurance, 'CONFIRMATION_REQUIRED'),
    'save' => 'BLOCKED_BEFORE_ELIGIBILITY_REVISION',
    'fixture_residue' => 0,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
return;

$storageCounts = static fn(PDO $connection): array => [
    'headers' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn(),
    'groups' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_groups')->fetchColumn(),
    'items' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),
    'workdays' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_workdays')->fetchColumn(),
    'lines' => (int) $connection->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn(),
];
$countBefore = $storageCounts($db);
$stored = null;
$detail = null;
$preflight = null;
$sourceHashMatches = false;
$storagePayload = $payload;
$temporaryInsurance = [];
foreach ($previewItem['lines'] ?? [] as $line) {
    if (!in_array((string) ($line['line_code'] ?? ''), ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'], true)) continue;
    $temporaryInsurance[] = [
        'line_type_code' => $line['line_type_code'],
        'line_code' => $line['line_code'],
        'final_amount' => 0,
        'adjustment_reason' => '2013년 실제 지급자료에서 공제 및 회사부담이 확인되지 않음',
        'actual_application_source_code' => 'HISTORICAL_ACTUAL',
    ];
}
$storagePayload['groups'][0]['items'][0]['institution_line_overrides'] = array_merge(
    $storagePayload['groups'][0]['items'][0]['institution_line_overrides'] ?? [],
    $temporaryInsurance
);
try {
    $service->save($payload);
    throw new RuntimeException('실제 적용액 누락 저장이 차단되지 않았습니다.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), '실제 적용액')) throw $exception;
    $validationResults['실제 적용액 누락'] = 'BLOCKED';
}
$missingReasonPayload = $storagePayload;
foreach ($missingReasonPayload['groups'][0]['items'][0]['institution_line_overrides'] as &$override) {
    if (in_array($override['line_code'] ?? '', array_keys($expectedInsurance), true)) $override['adjustment_reason'] = null;
}
unset($override);
try {
    $service->save($missingReasonPayload);
    throw new RuntimeException('자동계산액과 다른 적용금액의 사유 누락이 차단되지 않았습니다.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), '적용사유')) throw $exception;
    $validationResults['적용사유 누락'] = 'BLOCKED';
}

$db->beginTransaction();
try {
    $stored = $service->save($storagePayload)['data'];
    $documentId = (string) $stored['id'];
    $detail = $service->detail($documentId)['data'];
    foreach ($detail['groups'][0]['items'][0]['workdays'] as $storedWorkday) {
        if ((int) ($storedWorkday['actual_work_minutes'] ?? 0) !== 480) {
            throw new RuntimeException('저장 후 실제근로시간 480분이 복원되지 않았습니다.');
        }
    }
    $preflight = $service->submissionPreflight($documentId)['data'];
    $sourceGroups = [];
    foreach ($detail['groups'] as $group) {
        $sourceItems = [];
        foreach ($group['items'] as $item) {
            $lineByWorkday = [];
            foreach ($item['lines'] as $line) $lineByWorkday[(string) $line['daily_employment_income_workday_id']][] = $line;
            $sourceWorkdays = array_map(static fn(array $day): array => $day + ['lines' => $lineByWorkday[(string) $day['id']] ?? []], $item['workdays']);
            $sourceItems[] = $item + ['workdays' => $sourceWorkdays];
        }
        $sourceGroups[] = $group + ['items' => $sourceItems];
    }
    $fixtureHash = (new DailyEmploymentIncomeCalculationSourceService())->hash([
        'daily_employment_income_id' => $documentId,
        'income_year_month' => $detail['header']['income_year_month'],
        'payment_date' => $detail['header']['payment_date'],
        'groups' => $sourceGroups,
    ]);
    $sourceHashMatches = hash_equals($fixtureHash, (string) $preflight['source_hash']);
    $db->rollBack();
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}
$countAfter = $storageCounts($db);
if ($countBefore !== $countAfter) throw new RuntimeException('Fixture 문서가 운영 DB에 잔존합니다.');

$insuranceIssues = [];
foreach (($preflight['blocking_errors'] ?? []) as $issue) {
    if (!str_starts_with((string) ($issue['code'] ?? ''), 'INSURANCE_')) continue;
    $context = (array) ($issue['context'] ?? []);
    $insuranceIssues[] = [
        'insurance_type_code' => $context['insurance_type_code'] ?? null,
        'code' => $issue['code'] ?? null,
        'workplace_candidate_count' => $context['workplace_candidate_count'] ?? null,
        'coverage_count' => $context['coverage_count'] ?? null,
        'message' => $issue['message'] ?? null,
    ];
}

echo json_encode([
    'worker' => $worker,
    'picker_options' => [
        'worker_result_count' => count($workerOptions['results']),
        'work_type_result_count' => count($workTypeOptions['results']),
    ],
    'actual_work_minutes_validation' => $validationResults,
    'business_unit' => ['value' => 'HQ', 'text' => '본사', 'project_id' => null, 'work_team_id' => null],
    'daily' => array_map(static fn(array $day): array => array_diff_key($day, ['lines' => true]), $daily),
    'preview_summary' => $previewItem['summary'],
    'statutory_item_insurance' => array_values(array_map(
        static fn(array $line): array => array_intersect_key($line, array_flip([
            'line_type_code', 'line_code', 'application_status_code', 'calculation_basis_amount',
            'calculation_rate', 'calculation_before_rounding', 'rounding_method_code', 'rounding_unit',
            'calculated_amount', 'final_amount', 'statutory_standard_id', 'standard_effective_from', 'standard_effective_to',
        ])),
        array_filter($previewItem['lines'] ?? [], static fn(array $line): bool =>
            in_array((string) ($line['line_code'] ?? ''), ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'], true)
        )
    )),
    'stored_actual_work_minutes' => array_map(
        static fn(array $workday): int => (int) $workday['actual_work_minutes'],
        $detail['groups'][0]['items'][0]['workdays']
    ),
    'rollback_storage_summary' => $detail['header'] ? array_intersect_key($detail['header'], array_flip([
        'total_work_days', 'total_gross_amount', 'total_deduction_amount', 'total_net_payment_amount',
    ])) : null,
    'preflight' => [
        'can_submit' => $preflight['can_submit'] ?? null,
        'source_hash' => $preflight['source_hash'] ?? null,
        'insurance_issues' => $insuranceIssues,
        'blocking_error_codes' => array_values(array_map(
            static fn(array $issue): string => (string) ($issue['code'] ?? ''),
            $preflight['blocking_errors'] ?? []
        )),
    ],
    'source_hash_matches' => $sourceHashMatches,
    'storage_counts_before' => $countBefore,
    'storage_counts_after' => $countAfter,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
