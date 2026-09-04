<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\Institution\DailyEmploymentIncomeService;
use Core\Session;

$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$db = Core\Database::getInstance()->getConnection();
$service = new DailyEmploymentIncomeService($db);
$detail = $service->detail($documentId)['data'];
$header = $detail['header'];
$actor = (string) $header['updated_by'];
if (!preg_match('/^USER:([a-f0-9-]{36})$/i', $actor, $match)) {
    throw new RuntimeException('대상 문서 Actor를 확인할 수 없습니다.');
}
Session::start(30);
$_SESSION['user'] = ['id' => $match[1]];
$_SESSION['auth_state'] = ['user_id' => $match[1], 'status' => 'NORMAL'];

$groups = [];
foreach ($detail['groups'] as $storedGroup) {
    $items = [];
    foreach ($storedGroup['items'] as $storedItem) {
        $items[] = [
            'id' => $storedItem['id'],
            'worker_client_id' => $storedItem['worker_client_id'],
            'work_type_code' => $storedItem['work_type_code'],
            'work_description' => $storedItem['work_description'],
            'workdays' => array_map(static fn(array $day): array => [
                'work_date' => $day['work_date'],
                'actual_work_minutes' => (int) $day['actual_work_minutes'],
                'daily_rate_amount' => (float) $day['daily_rate_amount'],
                'taxable_additional_amount' => (float) $day['allowance_amount'],
                'non_taxable_additional_amount' => (float) $day['non_taxable_amount'],
                'non_taxable_reason' => $day['non_taxable_reason'],
                'calculation_note' => $day['calculation_note'],
            ], $storedItem['workdays']),
        ];
    }
    $groups[] = [
        'business_unit' => $storedGroup['business_unit'],
        'project_id' => $storedGroup['project_id'],
        'work_team_id' => $storedGroup['work_team_id'],
        'work_description' => $storedGroup['work_description'],
        'employment_insurance_application_status_code' => $storedGroup['employment_insurance_application_status_code'],
        'employment_insurance_decision_reason' => $storedGroup['employment_insurance_decision_reason'],
        'employment_insurance_decision_source_code_id' => $storedGroup['employment_insurance_decision_source_code_id'],
        'industrial_accident_application_status_code' => $storedGroup['industrial_accident_application_status_code'],
        'industrial_accident_decision_reason' => $storedGroup['industrial_accident_decision_reason'],
        'industrial_accident_decision_source_code_id' => $storedGroup['industrial_accident_decision_source_code_id'],
        'items' => $items,
    ];
}
$payload = [
    'id' => $documentId,
    'income_year_month' => $header['income_year_month'],
    'groups' => $groups,
];

$lineSummary = static function (array $calculation): array {
    $lines = $calculation['groups'][0]['items'][0]['lines'];
    $result = [];
    foreach ($lines as $line) {
        if (!in_array((string) $line['line_code'], [
            'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INSURANCE_VOCATIONAL', 'INDUSTRIAL_ACCIDENT_INSURANCE',
        ], true)) continue;
        $key = $line['line_type_code'] . ':' . $line['line_code'];
        $result[$key] = [
            'status' => $line['application_status_code'] ?? null,
            'automatic_amount' => $line['calculated_amount'] ?? null,
            'applied_amount' => $line['final_amount'] ?? null,
            'decision_source_code' => $line['eligibility_result']['decision_source_code'] ?? null,
            'manual_setting_reason' => $line['eligibility_result']['manual_setting_reason'] ?? null,
            'premium_revision_id' => $line['eligibility_result']['premium_revision_id'] ?? null,
        ];
    }
    return $result;
};

$applicable = $service->calculate($payload, true)['data'];

$excludedPayload = $payload;
$excludedPayload['groups'][0]['employment_insurance_application_status_code'] = 'EXCLUDED';
$excludedPayload['groups'][0]['employment_insurance_decision_reason'] = '검증용 고용보험 적용 제외 설정';
$excludedPayload['groups'][0]['industrial_accident_application_status_code'] = 'EXCLUDED';
$excludedPayload['groups'][0]['industrial_accident_decision_reason'] = '검증용 산재보험 적용 제외 설정';
$excluded = $service->calculate($excludedPayload, true)['data'];

$missingPayload = $payload;
foreach (['employment_insurance', 'industrial_accident'] as $prefix) {
    $missingPayload['groups'][0][$prefix . '_application_status_code'] = null;
    $missingPayload['groups'][0][$prefix . '_decision_reason'] = null;
    $missingPayload['groups'][0][$prefix . '_decision_source_code_id'] = null;
}
$missing = $service->calculate($missingPayload, false)['data'];

$datePayload = $payload;
$newDay = $datePayload['groups'][0]['items'][0]['workdays'][0];
$newDay['work_date'] = '2013-08-11';
$newDay['actual_work_minutes'] = 480;
$datePayload['groups'][0]['items'][0]['workdays'][] = $newDay;
$dateChanged = $service->calculate($datePayload, true)['data'];

echo json_encode([
    'read_only' => true,
    'applicable' => [
        'summary' => $applicable['groups'][0]['items'][0]['summary'],
        'lines' => $lineSummary($applicable),
        'insurance_preflight' => $applicable['insurance_preflight'],
    ],
    'excluded' => [
        'lines' => $lineSummary($excluded),
        'insurance_preflight' => $excluded['insurance_preflight'],
    ],
    'missing' => [
        'lines' => $lineSummary($missing),
        'insurance_preflight' => $missing['insurance_preflight'],
    ],
    'date_changed' => [
        'added_work_date' => $newDay['work_date'],
        'actual_work_minutes' => $newDay['actual_work_minutes'],
        'summary' => $dateChanged['groups'][0]['items'][0]['summary'],
        'lines' => $lineSummary($dateChanged),
        'insurance_preflight' => $dateChanged['insurance_preflight'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
