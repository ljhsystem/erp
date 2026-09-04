<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardTemplateService;
use App\Services\Institution\InsuranceEligibilityResolver;
use App\Services\Institution\InsuranceEligibilityConditionEvaluator;

$db = Core\Database::getInstance()->getConnection();
$sourceSchema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$schema = 'tmp_insurance_integration_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
if (!preg_match('/^tmp_insurance_integration_[0-9]{14}_[a-f0-9]{6}$/', $schema)) {
    throw new RuntimeException('격리 Schema 이름이 올바르지 않습니다.');
}

$executeSql = static function (PDO $connection, string $path): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string)file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) {
            continue;
        }
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $connection->exec($statement);
        }
        $buffer = '';
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
    }
};
$hash = static function (PDO $connection, string $sql): string {
    $rows = $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
$jsonColumnHash = static function (PDO $connection, string $sql, string $column): string {
    $normalize = static function (mixed $value) use (&$normalize): mixed {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map($normalize, $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = $normalize($item);
        return $value;
    };
    $rows = $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) $row[$column] = $normalize(json_decode((string)$row[$column], true));
    unset($row);
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};

$created = false;
try {
    $db->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $created = true;
    $db->exec("CREATE TABLE `{$schema}`.`_codex_execution_marker`(id TINYINT PRIMARY KEY,owner_code VARCHAR(100) NOT NULL)");
    $db->exec("INSERT INTO `{$schema}`.`_codex_execution_marker` VALUES(1,'INSURANCE_STATUTORY_INTEGRATION_20260831')");
    foreach (['system_codes', 'system_statutory_standards', 'system_statutory_standard_sources', 'institution_daily_employment_income_calculation_results'] as $table) {
        $db->exec("CREATE TABLE `{$schema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        $db->exec("INSERT INTO `{$schema}`.`{$table}` SELECT * FROM `{$sourceSchema}`.`{$table}`");
    }
    $db->exec("USE `{$schema}`");

    $legacySql = "SELECT id,sort_no,standard_type_code,effective_from,effective_to,value_data,note,created_at,created_by,updated_at,updated_by FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY' ORDER BY id";
    $sourceContentSql = "SELECT id,organization_name,law_name,notice_no,source_name,source_url,published_at,file_path,file_name,file_size,mime_type,note,sort_no,created_at,created_by,updated_at,updated_by FROM system_statutory_standard_sources WHERE id LIKE '20260829-13%' ORDER BY id";
    $resultSql = 'SELECT * FROM institution_daily_employment_income_calculation_results ORDER BY id';
    $otherResultSql = "SELECT * FROM institution_daily_employment_income_calculation_results WHERE id NOT IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') ORDER BY id";
    $targetInvariantSql = "SELECT id,calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,status_code,eligibility_status_code,eligibility_reason_code,calculation_basis_amount,automatic_employee_amount,automatic_employer_amount,confirmed_employee_amount,confirmed_employer_amount,missing_inputs,exception_reason FROM institution_daily_employment_income_calculation_results WHERE id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') ORDER BY id";
    $premiumSql = "SELECT id,standard_type_code,effective_from,effective_to,value_data FROM system_statutory_standards WHERE standard_type_code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') AND id NOT LIKE '20260831-10%' ORDER BY id";
    $beforeLegacyRows = $db->query($legacySql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $beforeResultRows = $db->query($resultSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $before = [
        'legacy_hash' => $hash($db, $legacySql),
        'source_content_hash' => $hash($db, $sourceContentSql),
        'result_hash' => $hash($db, $resultSql),
        'other_result_hash' => $hash($db, $otherResultSql),
        'target_result_invariant_hash' => $hash($db, $targetInvariantSql),
        'premium_hash' => $hash($db, $premiumSql),
    ];

    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_01_integrate_insurance_eligibility_into_insurance_types.up.sql');
    $after = [
        'legacy_hash' => $hash($db, $legacySql),
        'source_content_hash' => $hash($db, $sourceContentSql),
        'result_hash' => $hash($db, $resultSql),
        'other_result_hash' => $hash($db, $otherResultSql),
        'target_result_invariant_hash' => $hash($db, $targetInvariantSql),
        'premium_hash' => $hash($db, $premiumSql),
    ];
    if ($before !== $after) {
        throw new RuntimeException('기존 Revision·Result·Source 내용 또는 보험료 value_data가 변경됐습니다.');
    }

    $newCount = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY'")->fetchColumn();
    $newSourceCount = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.policy_component_code='ELIGIBILITY'")->fetchColumn();
    $mappingMismatch = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standards legacy LEFT JOIN system_statutory_standards integrated ON integrated.id=CONCAT('20260831-10',RIGHT(SUBSTRING_INDEX(SUBSTRING_INDEX(legacy.id,'-',2),'-',-1),2),'-4000-8000-',RIGHT(legacy.id,12)) WHERE legacy.standard_type_code='INSURANCE_ELIGIBILITY' AND (integrated.id IS NULL OR integrated.value_data<>legacy.value_data OR integrated.effective_from<>legacy.effective_from OR NOT(integrated.effective_to<=>legacy.effective_to))")->fetchColumn();
    $overlapCount = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standards left_row JOIN system_statutory_standards right_row ON left_row.id<right_row.id AND left_row.standard_type_code=right_row.standard_type_code AND left_row.policy_component_code=right_row.policy_component_code AND left_row.employment_type_code=right_row.employment_type_code AND left_row.work_scope_code=right_row.work_scope_code AND left_row.additional_dimension_key=right_row.additional_dimension_key AND left_row.effective_from<=COALESCE(right_row.effective_to,'9999-12-31') AND right_row.effective_from<=COALESCE(left_row.effective_to,'9999-12-31') WHERE left_row.policy_component_code='ELIGIBILITY'")->fetchColumn();
    $legacySourceLinkCount = (int)$db->query("SELECT COUNT(*) FROM system_statutory_standard_sources source_row JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id WHERE standard_row.standard_type_code='INSURANCE_ELIGIBILITY'")->fetchColumn();
    $legacyReferences = (int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results WHERE eligibility_revision_id LIKE '20260829-03%'")->fetchColumn();
    if ($newCount !== 22 || $newSourceCount !== 22 || $mappingMismatch !== 0 || $overlapCount !== 0 || $legacySourceLinkCount !== 0 || $legacyReferences !== 3) {
        throw new RuntimeException('보험별 가입자격 이관 검증값이 계약과 다릅니다.');
    }

    $resolver = new StatutoryStandardResolver($db);
    $premium = $resolver->resolve('NATIONAL_PENSION', '2013-08-06');
    $eligibility = $resolver->resolveComponent('NATIONAL_PENSION', 'ELIGIBILITY', 'DAILY', 'HEAD_OFFICE', '2013-08-06');
    if (($premium['policy_component_code'] ?? null) !== 'PREMIUM' || ($eligibility['id'] ?? null) !== '20260831-1003-4000-8000-000000000003') {
        throw new RuntimeException('보험료 또는 가입자격 Resolver가 정확한 Grain을 선택하지 못했습니다.');
    }

    $eligibilityResolver = new InsuranceEligibilityResolver($db);
    $conditionEvaluator = new InsuranceEligibilityConditionEvaluator();
    $truthTable = [
        ['ALL', [InsuranceEligibilityConditionEvaluator::FALSE, InsuranceEligibilityConditionEvaluator::UNKNOWN], InsuranceEligibilityConditionEvaluator::FALSE],
        ['ALL', [InsuranceEligibilityConditionEvaluator::TRUE, InsuranceEligibilityConditionEvaluator::UNKNOWN], InsuranceEligibilityConditionEvaluator::UNKNOWN],
        ['ALL', [InsuranceEligibilityConditionEvaluator::TRUE, InsuranceEligibilityConditionEvaluator::TRUE], InsuranceEligibilityConditionEvaluator::TRUE],
        ['ANY', [InsuranceEligibilityConditionEvaluator::TRUE, InsuranceEligibilityConditionEvaluator::UNKNOWN], InsuranceEligibilityConditionEvaluator::TRUE],
        ['ANY', [InsuranceEligibilityConditionEvaluator::FALSE, InsuranceEligibilityConditionEvaluator::UNKNOWN], InsuranceEligibilityConditionEvaluator::UNKNOWN],
        ['ANY', [InsuranceEligibilityConditionEvaluator::FALSE, InsuranceEligibilityConditionEvaluator::FALSE], InsuranceEligibilityConditionEvaluator::FALSE],
        ['NONE', [InsuranceEligibilityConditionEvaluator::TRUE, InsuranceEligibilityConditionEvaluator::UNKNOWN], InsuranceEligibilityConditionEvaluator::FALSE],
        ['NONE', [InsuranceEligibilityConditionEvaluator::FALSE, InsuranceEligibilityConditionEvaluator::UNKNOWN], InsuranceEligibilityConditionEvaluator::UNKNOWN],
        ['NONE', [InsuranceEligibilityConditionEvaluator::FALSE, InsuranceEligibilityConditionEvaluator::FALSE], InsuranceEligibilityConditionEvaluator::TRUE],
    ];
    foreach ($truthTable as [$operator, $states, $expectedState]) {
        if ($conditionEvaluator->combine($states, $operator) !== $expectedState) {
            throw new RuntimeException('가입자격 3값 조건 결합 회귀가 발생했습니다.');
        }
    }
    $forbiddenFallbackBlocked = false;
    try {
        $resolver->resolveComponent('NATIONAL_PENSION', 'ELIGIBILITY', 'REGULAR', 'CONSTRUCTION_SITE', '2013-08-06');
    } catch (RuntimeException) {
        $forbiddenFallbackBlocked = true;
    }
    if (!$forbiddenFallbackBlocked) throw new RuntimeException('금지된 고용형태 또는 업무 Scope fallback이 발생했습니다.');
    $baseContext = [
        'company_id'=>'fixture-company',
        'worker_client_id'=>'4481fb0f-d04e-46eb-9b05-1862188f4fb7',
        'attribution_date'=>'2013-08-06',
        'employment_type_code'=>'DAILY',
        'work_scope_code'=>'HEAD_OFFICE',
        'birth_date'=>'1963-06-07',
        'employment_start_date'=>'2013-08-06',
        'employment_end_date'=>'2013-08-10',
        'employment_end_open'=>false,
        'continuous_employment_confirmed'=>false,
        'monthly_work_days'=>5,
        'monthly_work_minutes'=>2400,
        'monthly_income_amount'=>452940,
    ];
    $pensionEligibility = $eligibilityResolver->resolve($baseContext + ['insurance_type_code'=>'NATIONAL_PENSION']);
    $healthEligibility = $eligibilityResolver->resolve($baseContext + ['insurance_type_code'=>'HEALTH_INSURANCE']);
    $careEligibility = $eligibilityResolver->resolve($baseContext + ['insurance_type_code'=>'LONG_TERM_CARE', 'dependent_result'=>$healthEligibility]);
    if (($pensionEligibility['status'] ?? null) !== 'NOT_ELIGIBLE'
        || ($healthEligibility['status'] ?? null) !== 'NOT_ELIGIBLE'
        || ($careEligibility['status'] ?? null) !== 'NOT_ELIGIBLE') {
        throw new RuntimeException('정순옥 가입자격 Fixture가 세 보험 모두 NOT_ELIGIBLE이 아닙니다.');
    }

    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_02_backfill_daily_income_integrated_eligibility_references.up.sql');
    $integratedResultHash = $hash($db, $resultSql);
    if ($hash($db, $otherResultSql) !== $before['other_result_hash'] || $hash($db, $targetInvariantSql) !== $before['target_result_invariant_hash']) {
        throw new RuntimeException('승인된 참조·Snapshot 외 계산결과 값이 변경됐습니다.');
    }
    $integratedReferenceCount = (int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results WHERE id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') AND eligibility_revision_id LIKE '20260831-10%' AND JSON_UNQUOTE(JSON_EXTRACT(eligibility_snapshot,'$.eligibility_revision_id'))=eligibility_revision_id AND JSON_UNQUOTE(JSON_EXTRACT(calculation_basis_snapshot,'$.eligibility_revision_id'))=eligibility_revision_id")->fetchColumn();
    if ($integratedReferenceCount !== 3) {
        throw new RuntimeException('계산결과 참조·Snapshot 3건이 함께 이관되지 않았습니다.');
    }

    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_03_remove_insurance_eligibility_standard_type.up.sql');
    $legacyTypeCount = (int)$db->query("SELECT (SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='INSURANCE_ELIGIBILITY')+(SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='INSURANCE_ELIGIBILITY')")->fetchColumn();
    if ($legacyTypeCount !== 0) {
        throw new RuntimeException('구형 가입자격 Type과 Revision이 물리 삭제되지 않았습니다.');
    }

    $codeTemplateSql = "SELECT id,code,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') ORDER BY code";
    $codeBeforeTemplateHash = $jsonColumnHash($db, $codeTemplateSql, 'extra_data');
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_04_add_insurance_component_input_templates.up.sql');
    $templateService = new StatutoryStandardTemplateService($db);
    $eligibilityTemplate = $templateService->find('NATIONAL_PENSION', 'ELIGIBILITY', 'DAILY', 'HEAD_OFFICE');
    $templateValuePaths = array_column((array)$eligibilityTemplate['fields'], 'value_path');
    foreach (['age.minimum_age','employment_period.minimum_continuous_months','monthly_conditions.minimum_work_days','aggregation.scope_code','transition.required_status_code'] as $requiredPath) {
        if (!in_array($requiredPath, $templateValuePaths, true)) {
            throw new RuntimeException('가입자격 입력 템플릿의 중첩 필드가 누락됐습니다: ' . $requiredPath);
        }
    }
    if (in_array('json', array_column((array)$eligibilityTemplate['fields'], 'type'), true)) {
        throw new RuntimeException('가입자격 입력 템플릿에 원시 JSON 필드가 포함됐습니다.');
    }
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_04_add_insurance_component_input_templates.down.sql');
    if ($jsonColumnHash($db, $codeTemplateSql, 'extra_data') !== $codeBeforeTemplateHash) {
        throw new RuntimeException('입력 템플릿 Down 후 코드 extra_data가 복원되지 않았습니다.');
    }

    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_03_remove_insurance_eligibility_standard_type.down.sql');
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_02_backfill_daily_income_integrated_eligibility_references.down.sql');
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260831_01_integrate_insurance_eligibility_into_insurance_types.down.sql');
    $down = [
        'legacy_hash' => $hash($db, $legacySql),
        'source_content_hash' => $hash($db, $sourceContentSql),
        'result_hash' => $hash($db, $resultSql),
        'other_result_hash' => $hash($db, $otherResultSql),
        'target_result_invariant_hash' => $hash($db, $targetInvariantSql),
        'premium_hash' => $hash($db, $premiumSql),
    ];
    if ($before !== $down) {
        $downLegacyRows = $db->query($legacySql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $downResultRows = $db->query($resultSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        throw new RuntimeException('Down 후 기준선이 복원되지 않았습니다: ' . json_encode(['before'=>$before,'down'=>$down,'legacy_before_first'=>$beforeLegacyRows[0] ?? null,'legacy_down_first'=>$downLegacyRows[0] ?? null,'result_before_first'=>$beforeResultRows[0] ?? null,'result_down_first'=>$downResultRows[0] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $dimensionCount = (int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards' AND COLUMN_NAME IN('policy_component_code','employment_type_code','work_scope_code','additional_dimension_data','additional_dimension_key')")->fetchColumn();
    if ($dimensionCount !== 0) {
        throw new RuntimeException('Down 후 Dimension 컬럼이 남았습니다.');
    }

    echo json_encode([
        'success' => true,
        'schema' => $schema,
        'before' => $before,
        'after_up' => $after,
        'new_revision_count' => $newCount,
        'new_source_count' => $newSourceCount,
        'timeline_overlap_count' => $overlapCount,
        'legacy_source_link_count' => $legacySourceLinkCount,
        'legacy_result_reference_count' => $legacyReferences,
        'integrated_result_reference_count' => $integratedReferenceCount,
        'integrated_result_hash' => $integratedResultHash,
        'legacy_type_count_after_remove' => $legacyTypeCount,
        'eligibility_template_field_count' => count((array)$eligibilityTemplate['fields']),
        'eligibility_template_raw_json_field_count' => count(array_filter((array)$eligibilityTemplate['fields'], static fn(array $field): bool => ($field['type'] ?? '') === 'json')),
        'premium_revision_id' => $premium['id'],
        'eligibility_revision_id' => $eligibility['id'],
        'condition_truth_table_case_count' => count($truthTable),
        'forbidden_fallback_blocked' => $forbiddenFallbackBlocked,
        'jeong_sun_ok_eligibility' => [
            'national_pension'=>$pensionEligibility,
            'health_insurance'=>$healthEligibility,
            'long_term_care'=>$careEligibility,
        ],
        'down_restored' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $db->exec("USE `{$sourceSchema}`");
    if ($created) {
        $marker = $db->query("SELECT COUNT(*) FROM `{$schema}`.`_codex_execution_marker` WHERE owner_code='INSURANCE_STATUTORY_INTEGRATION_20260831'")->fetchColumn();
        if ((int)$marker !== 1) {
            throw new RuntimeException('격리 Schema 실행 소유권 Marker가 없습니다.');
        }
        $db->exec("DROP DATABASE `{$schema}`");
    }
}
