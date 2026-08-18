<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\PayrollEvidenceReadModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Services\Institution\RegularEmploymentIncomeService;
use App\Services\Auth\AuthSessionService;

$db = Core\DbPdo::conn();
$checks = [];

$metadata = (new EvidenceSourceRepository($db))->metadata('PAYROLL_REPORT');
$checks['metadata'] = ($metadata['source_table'] ?? '') === 'ledger_evidence_salary_report';

$template = $db->query(
    "SELECT id FROM user_approval_templates WHERE document_type='REGULAR_EMPLOYMENT_INCOME' AND is_active=1 LIMIT 1"
)->fetchColumn();
$checks['approval_template'] = is_string($template) && $template !== '';
if ($checks['approval_template']) {
    $statement = $db->prepare(
        "SELECT GROUP_CONCAT(step_type ORDER BY sort_no) FROM user_approval_template_steps WHERE template_id=:id AND is_active=1"
    );
    $statement->execute([':id' => $template]);
    $checks['approval_steps'] = $statement->fetchColumn() === 'SUBMIT,APPROVAL,FINAL_APPROVAL';
}

$month = date('Y-m');
$eligible = (new RegularEmploymentIncomeService($db))->eligibleEmployees($month)['data'];
$checks['eligible_employee_query'] = is_array($eligible);
$checks['historical_snapshots'] = $eligible === [] || array_key_exists('department_name', $eligible[0]);

$salaryTable = $db->query("SHOW TABLES LIKE 'ledger_evidence_salary_report'")->fetchColumn();
$checks['salary_evidence_table'] = $salaryTable === 'ledger_evidence_salary_report';
$checks['duplicate_guard'] = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_salary_report' AND INDEX_NAME IN ('uk_salary_report_external_key','uk_salary_report_source_income')"
)->fetchColumn() >= 2;
$payrollRows = (new PayrollEvidenceReadModel(
    $db,
    new EvidenceSchemaModel($db),
    new EvidenceBodyStatusProjectionModel()
))->findList('PAYROLL');
$checks['salary_evidence_read_model'] = is_array($payrollRows);

$checks['transactional_pipeline'] = false;
if ($eligible !== []) {
    (new AuthSessionService())->getCurrentUser();
    $requesterId = (string) $db->query(
        "SELECT approver_id FROM user_approval_template_steps WHERE template_id=" . $db->quote((string) $template)
        . " AND approver_id IS NOT NULL ORDER BY sort_no DESC LIMIT 1"
    )->fetchColumn();
    if ($requesterId !== '') {
        $db->beginTransaction();
        try {
            $stage = 'save';
            $_SESSION['user'] = ['id' => $requesterId];
            $service = new RegularEmploymentIncomeService($db);
            $employee = $eligible[0];
            $saved = $service->save([
                'income_year_month' => $month,
                'payment_date' => date('Y-m-d'),
                'title' => $month . ' 상용근로소득 회귀검증',
                'description' => '트랜잭션 롤백 회귀검증',
                'items' => [[
                    'employee_id' => $employee['employee_id'],
                    'base_salary_amount' => 1000000,
                    'allowance_amount' => 0,
                    'bonus_amount' => 0,
                    'non_taxable_amount' => 0,
                    'national_pension_amount' => 0,
                    'health_insurance_amount' => 0,
                    'long_term_care_amount' => 0,
                    'employment_insurance_amount' => 0,
                    'income_tax_amount' => 0,
                    'local_income_tax_amount' => 0,
                    'other_deduction_amount' => 0,
                ]],
            ]);
            $documentId = (string) $saved['data']['id'];
            $stage = 'submit';
            $submitted = $service->submit($documentId);
            $requestId = (string) $submitted['data']['request_id'];
            $service->withdraw($requestId);
            $checks['withdraw'] = true;
            $submitted = $service->submit($documentId);
            $requestId = (string) $submitted['data']['request_id'];
            $statement = $db->prepare(
                "SELECT id,approver_id FROM user_approval_request_steps WHERE request_id=:request_id AND step_type IN ('APPROVAL','FINAL_APPROVAL') ORDER BY sort_no"
            );
            $statement->execute([':request_id' => $requestId]);
            $steps = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $firstStep = $steps[0] ?? null;
            if (!is_array($firstStep)) {
                throw new RuntimeException('반려 검증용 결재단계를 찾을 수 없습니다.');
            }
            $_SESSION['user'] = ['id' => $firstStep['approver_id']];
            $service->act((string) $firstStep['id'], 'rejected', '회귀검증 반려');
            $checks['rejection'] = true;
            $_SESSION['user'] = ['id' => $requesterId];
            $submitted = $service->submit($documentId);
            $requestId = (string) $submitted['data']['request_id'];
            $statement->execute([':request_id' => $requestId]);
            $steps = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($steps as $step) {
                $stage = 'approval:' . (string) $step['id'];
                $_SESSION['user'] = ['id' => $step['approver_id']];
                $service->act((string) $step['id'], 'approved', '회귀검증 승인');
            }
            $finalize = new ReflectionMethod($service, 'finalize');
            $idempotent = $finalize->invoke($service, $documentId, Core\Helpers\ActorHelper::user());
            $checks['duplicate_finalization'] = ($idempotent['duplicate_prevented'] ?? false) === true;
            $statement = $db->prepare(
                "SELECT e.id evidence_id,l.target_id transaction_id FROM ledger_evidence_salary_report e JOIN ledger_evidence_links l ON l.evidence_type='PAYROLL_REPORT' AND l.evidence_id=e.id AND l.target_type='TRANSACTION' AND l.deleted_at IS NULL WHERE e.source_regular_employment_income_id=:document_id"
            );
            $statement->execute([':document_id' => $documentId]);
            $accounting = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
            $source = $accounting === [] ? null : (new EvidenceSourceRepository($db))->find(
                'PAYROLL_REPORT',
                (string) $accounting['evidence_id']
            );
            $selectable = (new EvidenceSourceRepository($db))->search('', ['DATA'], 500);
            $checks['voucher_evidence_selection'] = false;
            foreach ($selectable as $selectableEvidence) {
                if (($selectableEvidence['import_type'] ?? '') === 'PAYROLL_REPORT'
                    && ($selectableEvidence['evidence_id'] ?? '') === ($accounting['evidence_id'] ?? '')) {
                    $checks['voucher_evidence_selection'] = true;
                    break;
                }
            }
            $checks['transactional_pipeline'] = $accounting !== [] && is_array($source);
            $db->rollBack();
        } catch (Throwable $throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $checks['transactional_pipeline_error'] = ($stage ?? 'unknown') . ': ' . $throwable->getMessage();
        }
    }
}

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode([
    'success' => $failed === [],
    'month' => $month,
    'eligible_employee_count' => count($eligible),
    'checks' => $checks,
    'failed' => $failed,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
