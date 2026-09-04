<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$codes = ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'INDUSTRIAL_ACCIDENT', 'INSURANCE_ELIGIBILITY'];
$marks = implode(',', array_fill(0, count($codes), '?'));
$codeStatement = $db->prepare("SELECT id,code,code_name,extra_data,is_active FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN ({$marks}) ORDER BY sort_no,id");
$codeStatement->execute($codes);
$codeRows = $codeStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

$types = [];
foreach ($codeRows as $codeRow) {
    $type = (string)$codeRow['code'];
    $standardStatement = $db->prepare('SELECT id,effective_from,effective_to,value_data FROM system_statutory_standards WHERE standard_type_code=:type ORDER BY effective_from,effective_to,id');
    $standardStatement->execute(['type'=>$type]);
    $standards = $standardStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($standards as &$standard) {
        $standard['value_data_hash'] = hash('sha256', (string)$standard['value_data']);
        $standard['value_data'] = json_decode((string)$standard['value_data'], true, 512, JSON_THROW_ON_ERROR);
    }
    unset($standard);
    $types[$type] = [
        'system_code'=>array_replace($codeRow, ['extra_data'=>json_decode((string)$codeRow['extra_data'], true, 512, JSON_THROW_ON_ERROR)]),
        'revision_count'=>count($standards),
        'revisions'=>$standards,
    ];
}

$eligibilitySources = $db->query("SELECT source_row.* FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY' ORDER BY source_row.standard_id,source_row.sort_no,source_row.id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$calculationRevisions = $db->query('SELECT id,daily_employment_income_id,revision_no,status_code,source_hash,created_at FROM institution_daily_employment_income_calculation_revisions ORDER BY daily_employment_income_id,revision_no,id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$calculationResults = $db->query('SELECT result_row.id,result_row.calculation_revision_id,result_row.result_type_code,result_row.statutory_standard_id,result_row.eligibility_revision_id,result_row.status_code FROM institution_daily_employment_income_calculation_results result_row ORDER BY result_row.calculation_revision_id,result_row.result_type_code,result_row.id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$allocations = (int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_allocations')->fetchColumn();
$eligibilityResultReferences = (int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results result_row JOIN system_statutory_standards standard_row ON standard_row.id=result_row.eligibility_revision_id WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY'")->fetchColumn();

echo json_encode([
    'read_only'=>true,
    'database'=>$db->query('SELECT DATABASE()')->fetchColumn(),
    'version'=>$db->query('SELECT VERSION()')->fetchColumn(),
    'types'=>$types,
    'eligibility_source_count'=>count($eligibilitySources),
    'eligibility_sources'=>$eligibilitySources,
    'calculation_revision_count'=>count($calculationRevisions),
    'calculation_revisions'=>$calculationRevisions,
    'calculation_result_count'=>count($calculationResults),
    'calculation_results'=>$calculationResults,
    'allocation_count'=>$allocations,
    'calculation_result_insurance_eligibility_reference_count'=>$eligibilityResultReferences,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
