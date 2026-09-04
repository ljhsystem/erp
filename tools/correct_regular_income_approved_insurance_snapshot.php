<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;

function correctionAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$apply = ($argv[1] ?? '') === '--apply';
$db = DbPdo::conn();
$actor = ActorHelper::system('REGULAR_INCOME_INSURANCE_SNAPSHOT_CORRECTION');
$document = $db->query("SELECT * FROM institution_regular_employment_incomes WHERE income_year_month='2013-08' AND deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
correctionAssert(count($document) === 1, '2013년 8월 상용근로소득 문서가 정확히 1건이어야 합니다.');
$header = $document[0];
correctionAssert($header['document_status'] === 'APPROVED', '대상 상용근로소득 문서가 승인 상태가 아닙니다.');

$documentId = (string)$header['id'];
$linksStatement = $db->prepare('SELECT generation_role,aggregation_key,transaction_id FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:id ORDER BY generation_role,aggregation_key,id');
$linksStatement->execute([':id' => $documentId]);
$links = $linksStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
correctionAssert(count(array_filter($links, static fn(array $row): bool => $row['generation_role'] === 'INSTITUTION_LIABILITY')) === 0, '이미 사용자부담 기관부채가 생성되어 승인 Snapshot만 단독 보정할 수 없습니다.');

$service = new RegularEmploymentIncomeService($db);
$detail = $service->detail($documentId)['data'];
$inputs = [];
foreach ($detail['items'] as $item) {
    $inputs[] = [
        'employee_id' => $item['employee_id'],
        'dependent_count_snapshot' => $item['dependent_count_snapshot'],
        'national_pension_basis_snapshot' => $item['national_pension_basis_snapshot'],
        'health_insurance_basis_snapshot' => $item['health_insurance_basis_snapshot'],
        'employment_insurance_basis_snapshot' => $item['employment_insurance_basis_snapshot'],
        'pay_line_items' => array_values(array_filter($item['line_items'], static fn(array $line): bool => $line['item_type_code'] === 'PAY' && in_array($line['pay_effect_code'] ?? null, ['INCREASE','DECREASE'], true))),
        'deduction_line_items' => array_values(array_filter($item['line_items'], static fn(array $line): bool => $line['item_type_code'] === 'DEDUCTION' && str_starts_with((string)($line['source_key'] ?? ''), 'SETTLEMENT|'))),
        'insurance_override_line_items' => array_values(array_filter($item['line_items'], static fn(array $line): bool => $line['item_type_code'] === 'DEDUCTION' && in_array($line['item_code'] ?? '', ['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'], true) && (abs((float)($line['adjustment_amount'] ?? 0)) >= .01 || str_starts_with((string)($line['source_key'] ?? ''), 'INSURANCE_OVERRIDE')))),
    ];
}
$preview = (new RegularEmploymentIncomeCalculationService($db))->preview('2013-08', (string)$header['payment_date'], $inputs, $actor);
correctionAssert($preview['readiness'] === 'READY', '현재 근로계약과 법정기준의 재계산 결과가 확정 상태가 아닙니다.');
$results = [];
foreach ($preview['results'] as $result) $results[(string)$result['employee_id']] = $result;

$targetKeys = [
    'DEDUCTION:EMPLOYMENT_INSURANCE',
    'EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE',
    'EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL',
    'EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE',
];
$columns = [
    'item_type_code','pay_effect_code','item_code','item_name_snapshot','taxable_flag',
    'calculated_amount','adjustment_amount','final_amount','adjustment_reason','calculation_source_code',
    'business_source_code','source_reference_id','source_key','business_reason','processed_at','processed_by',
    'application_status_code','calculation_basis_amount','calculation_rate','calculation_before_rounding',
    'rounding_method_code','rounding_unit','statutory_standard_id','social_insurance_coverage_id','workplace_size_period_id',
];
$numericColumns = array_flip([
    'calculated_amount','adjustment_amount','final_amount','calculation_basis_amount','calculation_rate',
    'calculation_before_rounding','rounding_unit',
]);
$before = [];
$planned = [];
foreach ($detail['items'] as $item) {
    $employeeId = (string)$item['employee_id'];
    $result = $results[$employeeId] ?? null;
    correctionAssert($result !== null, '직원별 재계산 결과가 누락됐습니다.');
    foreach (['gross_amount','deduction_amount','net_payment_amount'] as $amountKey) {
        correctionAssert(abs((float)$item[$amountKey] - (float)$result[$amountKey]) < .01, $item['employee_name_snapshot'] . ' 직원의 지급·공제·실지급 금액이 기존 승인값과 다릅니다.');
    }
    $existing = [];
    foreach ($item['line_items'] as $line) $existing[$line['item_type_code'] . ':' . $line['item_code']] = $line;
    foreach ($result['line_items'] as $line) {
        $key = $line['item_type_code'] . ':' . $line['item_code'];
        if (!in_array($key, $targetKeys, true)) continue;
        if (($line['application_status_code'] ?? null) === 'EXCLUDED') $line['business_reason'] = $line['calculation_message'] ?? '근로계약상 적용 제외';
        $planned[] = ['item' => $item, 'key' => $key, 'existing' => $existing[$key] ?? null, 'line' => array_intersect_key($line, array_flip($columns))];
    }
    $before[] = ['item_id'=>$item['id'],'employee_name'=>$item['employee_name_snapshot'],'employer_burden_amount'=>$item['employer_burden_amount']];
}

$changedRows = 0;
if ($apply) {
    $db->beginTransaction();
    try {
        foreach ($planned as $entry) {
            $item = $entry['item'];
            $line = $entry['line'];
            $existing = $entry['existing'];
            if ($existing) {
                $updates = [];
                $params = [':id' => $existing['id']];
                foreach ($line as $column => $value) {
                    if (isset($numericColumns[$column])) {
                        $oldNumber = $existing[$column] === null ? null : (float)$existing[$column];
                        $newNumber = $value === null ? null : (float)$value;
                        if ($oldNumber === $newNumber) continue;
                    } elseif (($existing[$column] ?? null) === $value) {
                        continue;
                    }
                    if (in_array($column, ['processed_at','processed_by'], true) && !empty($existing[$column])) continue;
                    $updates[] = "`{$column}`=:{$column}";
                    $params[':' . $column] = $value;
                }
                if ($updates !== []) {
                    $updates[] = 'updated_at=NOW()';
                    $updates[] = 'updated_by=:updated_by';
                    $params[':updated_by'] = $actor;
                    $db->prepare('UPDATE institution_regular_employment_income_line_items SET ' . implode(',', $updates) . ' WHERE id=:id')->execute($params);
                    $changedRows++;
                }
                continue;
            }
            $sortStatement = $db->prepare('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_regular_employment_income_line_items WHERE regular_employment_income_item_id=:id');
            $sortStatement->execute([':id' => $item['id']]);
            $row = ['id'=>UuidHelper::generate(),'regular_employment_income_item_id'=>$item['id'],'sort_no'=>(int)$sortStatement->fetchColumn(),'created_by'=>$actor,'updated_by'=>$actor] + $line;
            $names = array_keys($row);
            $insert = $db->prepare('INSERT INTO institution_regular_employment_income_line_items (`' . implode('`,`', $names) . '`) VALUES (:' . implode(',:', $names) . ')');
            $insert->execute(array_combine(array_map(static fn(string $name): string => ':' . $name, $names), array_values($row)));
            $changedRows++;
        }
        foreach ($detail['items'] as $item) {
            $sum = $db->prepare("SELECT COALESCE(SUM(final_amount),0) FROM institution_regular_employment_income_line_items WHERE regular_employment_income_item_id=:id AND item_type_code='EMPLOYER_BURDEN' AND application_status_code='APPLICABLE'");
            $sum->execute([':id' => $item['id']]);
            $burden = round((float)$sum->fetchColumn(), 2);
            if (abs($burden - (float)$item['employer_burden_amount']) >= .01) {
                $db->prepare('UPDATE institution_regular_employment_income_items SET employer_burden_amount=:amount,updated_at=NOW(),updated_by=:actor WHERE id=:id')->execute([':amount'=>$burden,':actor'=>$actor,':id'=>$item['id']]);
                $db->prepare("UPDATE ledger_evidence_salary_report SET raw_employer_burden_amount=:amount,updated_at=NOW(),updated_by=:actor WHERE source_regular_employment_income_id=:document_id AND regular_employment_income_item_id=:item_id AND evidence_status='CORRECTION_REQUIRED'")->execute([':amount'=>$burden,':actor'=>$actor,':document_id'=>$documentId,':item_id'=>$item['id']]);
                $changedRows++;
            }
        }
        $afterDetail = $service->detail($documentId)['data'];
        $db->prepare("INSERT INTO institution_regular_employment_income_audits (id,regular_employment_income_id,regular_employment_income_item_id,action_code,reason,before_value,after_value,request_key,acted_by) VALUES (:id,:document_id,NULL,'CORRECT',:reason,:before_value,:after_value,:request_key,:actor)")->execute([
            ':id'=>UuidHelper::generate(),':document_id'=>$documentId,':reason'=>'승인 근로계약 고용·산재 적용정책 Snapshot 보정',
            ':before_value'=>json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':after_value'=>json_encode(array_map(static fn(array $item): array => ['item_id'=>$item['id'],'employee_name'=>$item['employee_name_snapshot'],'employer_burden_amount'=>$item['employer_burden_amount']], $afterDetail['items']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':request_key'=>'REGULAR_INCOME_INSURANCE_POLICY_CORRECTION|' . $documentId,':actor'=>$actor,
        ]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

echo json_encode(['success'=>true,'mode'=>$apply?'APPLIED':'DRY_RUN','document_id'=>$documentId,'actor'=>$actor,'planned_line_count'=>count($planned),'changed_row_groups'=>$changedRows,'before'=>$before,'accounting_links'=>$links], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
