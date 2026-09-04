<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\Institution\DailyEmploymentIncomeService;
use App\Services\Institution\DailyEmploymentIncomeInsuranceEligibilityService;
use Core\Session;

$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$db = Core\Database::getInstance()->getConnection();
$service = new DailyEmploymentIncomeService($db);
$detail = $service->detail($documentId)['data'];
$header = $detail['header'];
$actor = (string) $header['updated_by'];
if (!preg_match('/^USER:([a-f0-9-]{36})$/i', $actor, $match)) throw new RuntimeException('대상 문서 Actor를 확인할 수 없습니다.');
Session::start(30);
$_SESSION['user'] = ['id' => $match[1]];
$_SESSION['auth_state'] = ['user_id' => $match[1], 'status' => 'NORMAL'];

$groups = [];
foreach ($detail['groups'] as $storedGroup) {
    $items = [];
    foreach ($storedGroup['items'] as $storedItem) {
        $workdays = [];
        foreach ($storedItem['workdays'] as $storedDay) {
            $workdays[] = [
                'work_date' => $storedDay['work_date'],
                'actual_work_minutes' => (int) $storedDay['actual_work_minutes'],
                'daily_rate_amount' => (float) $storedDay['daily_rate_amount'],
                'taxable_additional_amount' => (float) $storedDay['allowance_amount'],
                'non_taxable_additional_amount' => (float) $storedDay['non_taxable_amount'],
                'non_taxable_reason' => $storedDay['non_taxable_reason'],
                'calculation_note' => $storedDay['calculation_note'],
            ];
        }
        $items[] = [
            'id' => $storedItem['id'],
            'worker_client_id' => $storedItem['worker_client_id'],
            'work_type_code' => $storedItem['work_type_code'],
            'work_description' => $storedItem['work_description'],
            'workdays' => $workdays,
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
    'request_key' => 'daily-closure-preview-20260831',
    'income_year_month' => $header['income_year_month'],
    'payment_date' => $header['payment_date'],
    'document_title' => $header['document_title'],
    'description' => $header['description'],
    'memo' => $header['memo'],
    'groups' => $groups,
];
$preview = $service->calculate($payload)['data'];
$item = $preview['groups'][0]['items'][0];
$workerId = (string) ($detail['groups'][0]['items'][0]['worker_client_id'] ?? '');
$eligibilityService = new DailyEmploymentIncomeInsuranceEligibilityService($db);
$birthDateMethod = new ReflectionMethod($eligibilityService, 'birthDate');
$workerBirthDate = $birthDateMethod->invoke($eligibilityService, $workerId);
$nationalPensionRevisionId = null;
$lines = [];
foreach ($item['lines'] as $line) {
    if (!in_array($line['line_code'], ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INSURANCE_VOCATIONAL', 'INDUSTRIAL_ACCIDENT_INSURANCE'], true)) continue;
    $lines[$line['line_type_code'] . ':' . $line['line_code']] = [
        'application_status_code' => $line['application_status_code'] ?? null,
        'eligibility_status_code' => $line['eligibility_result']['status'] ?? null,
        'calculation_basis_amount' => $line['calculation_basis_amount'] ?? null,
        'calculation_rate' => $line['calculation_rate'] ?? null,
        'calculation_before_rounding' => $line['calculation_before_rounding'] ?? null,
        'rounding_method_code' => $line['rounding_method_code'] ?? null,
        'rounding_unit' => $line['rounding_unit'] ?? null,
        'calculated_amount' => $line['calculated_amount'] ?? null,
        'final_amount' => $line['final_amount'] ?? null,
        'adjustment_amount' => $line['adjustment_amount'] ?? null,
        'adjustment_reason' => $line['adjustment_reason'] ?? null,
        'eligibility_result' => $line['eligibility_result'] ?? null,
    ];
    if (($line['line_type_code'] ?? null) === 'DEDUCTION' && ($line['line_code'] ?? null) === 'NATIONAL_PENSION') {
        $nationalPensionRevisionId = $line['eligibility_result']['selected_revision_id'] ?? null;
    }
}
$revision = null;
if (is_string($nationalPensionRevisionId) && $nationalPensionRevisionId !== '') {
    $statement = $db->prepare('SELECT id,effective_from,effective_to,value_data FROM system_statutory_standards WHERE id=:id');
    $statement->execute(['id' => $nationalPensionRevisionId]);
    $revision = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if (is_array($revision)) {
        $revision['value_data_hash'] = hash('sha256', (string) $revision['value_data']);
        $revision['value_data'] = json_decode((string) $revision['value_data'], true, 512, JSON_THROW_ON_ERROR);
    }
}
$preflight = $service->submissionPreflight($documentId)['data'];
echo json_encode(['read_only' => true, 'summary' => $item['summary'], 'worker_birth_date'=>['exists'=>$workerBirthDate !== null, 'actual_value'=>$workerBirthDate], 'selected_national_pension_revision' => $revision, 'insurance_lines' => $lines, 'submission_preflight'=>$preflight], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
