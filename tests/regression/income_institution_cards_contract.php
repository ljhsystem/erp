<?php

$root = dirname(__DIR__, 2);
$common = file_get_contents($root . '/public/assets/js/common/income-calculation-cards.js');
$regular = file_get_contents($root . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$daily = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$service = file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$model = file_get_contents($root . '/app/Models/Institution/RegularEmploymentIncomeModel.php');

$codes = [
    'INCOME_TAX', 'LOCAL_INCOME_TAX', 'NATIONAL_PENSION', 'HEALTH_INSURANCE',
    'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INSURANCE_VOCATIONAL',
    'INDUSTRIAL_ACCIDENT_INSURANCE',
];
$checks = [
    'shared_eight_cards' => array_reduce($codes, static fn(bool $ok, string $code): bool => $ok && str_contains($common, "key: '{$code}'"), true),
    'income_tax_display_name' => str_contains($common, "key: 'INCOME_TAX', name: '근로소득세'")
        && str_contains($daily, "row('근로소득세'")
        && !str_contains($daily, "row('소득세'"),
    'regular_shared_renderer' => str_contains($regular, 'incomeInstitutionCardsDto') && str_contains($regular, 'renderIncomeCalculationCards'),
    'shared_rounding_contract' => str_contains($common, 'incomeCalculationRoundingText')
        && str_contains($common, "TRUNCATE: '절사'")
        && str_contains($regular, 'const roundingText=incomeCalculationRoundingText')
        && str_contains($daily, 'roundingLabel: incomeCalculationRoundingText(line)'),
    'daily_shared_renderer' => str_contains($daily, 'incomeInstitutionCardsDto') && str_contains($daily, 'renderIncomeCalculationCards'),
    'regular_summary_reuses_canonical_order' => str_contains($regular, 'INCOME_INSTITUTION_CARDS')
        && str_contains($regular, 'sortInstitutionLines((item.line_items||[])'),
    'daily_summary_reuses_canonical_order' => str_contains($daily, 'INCOME_INSTITUTION_CARDS')
        && str_contains($daily, 'const deductionLines = sortInstitutionLines')
        && str_contains($daily, 'const employerLines = sortInstitutionLines'),
    'daily_employment_employer_summary_combined' => str_contains($daily, "['EMPLOYMENT_INSURANCE','EMPLOYMENT_INSURANCE_VOCATIONAL'].includes")
        && str_contains($daily, "line_name_snapshot: '고용보험 사용자부담'")
        && str_contains($daily, "INCOME_INSTITUTION_CARDS.filter(definition => !definition.employeeOnly")
        && str_contains($daily, 'lineRows(employerSummaryLines)'),
    'daily_work_summary_single_line' => str_contains($daily, "row('근무일수/근로시간/단가', workSummaryLabel)")
        && str_contains($daily, 'remainingMinutes ?')
        && str_contains($daily, "String(remainingMinutes).padStart(2, '0')")
        && str_contains($daily, ": ''}`")
        && !str_contains($daily, "row('실제근로시간'"),
    'shared_editable_status_contract' => str_contains($common, "new Set(['DRAFT', 'REJECTED', 'WITHDRAWN'])")
        && str_contains($common, 'isIncomeCalculationEditableStatus')
        && str_contains($regular, 'has: isIncomeCalculationEditableStatus')
        && substr_count($daily, 'isIncomeCalculationEditableStatus') >= 3,
    'all_stored_cards_allow_historical_actual_input' => !str_contains($common, "primary?.application_status_code !== 'EXCLUDED'")
        && str_contains($regular, "['DEDUCTION','EMPLOYER_BURDEN'].includes(line.item_type_code)")
        && str_contains($regular, 'institution_override_line_items:')
        && str_contains($daily, 'editable: line => editableStatus && (itemLines.includes(line) || dayLines.includes(line))')
        && str_contains($daily, 'line_type_code: line.line_type_code'),
    'daily_workday_tax_cards_are_worker_aggregated' => !str_contains($common, 'appliedEntries')
        && str_contains($daily, 'const automaticTotal = taxLines.reduce')
        && str_contains($daily, "['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX'].includes(dtoLine?.sourceCode)")
        && str_contains($daily, 'day.institution_line_overrides')
        && str_contains($daily, "['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX']"),
    'worker_editor_has_role_specific_restore_without_persistent_guidance' => str_contains($common, "restoreButton.textContent = '계산액으로 복원'")
        && str_contains($common, "setAttribute('aria-label', '근로자 적용금액을 계산액으로 복원')")
        && str_contains($common, "setAttribute('aria-label', '사용자 적용금액을 계산액으로 복원')")
        && str_contains($common, 'if (differs && !restoreButton.isConnected)')
        && str_contains($common, 'if (!differs && restoreButton.isConnected)')
        && str_contains($common, "['EXCLUDED', 'CONFIRMATION_REQUIRED', 'CALCULATION_ERROR'].includes(eligibility.statusCode)")
        && !str_contains($common, 'placeholder="자동계산액과 다른 금액을 적용하는 이유"'),
    'contribution_party_contract' => str_contains($common, 'employeeContributionApplicable')
        && str_contains($common, 'employerContributionApplicable')
        && str_contains($common, 'employeeAmountEditable')
        && str_contains($common, 'employerAmountEditable')
        && str_contains($common, 'else if (!line.employerOnly)'),
    'reason_is_visible_only_for_amount_difference' => substr_count($common, "reasonWrap.classList.toggle('d-none', !required)") === 2
        && substr_count($common, "if (!required) reason.value = ''") === 2,
    'industrial_accident_is_employer_only' => str_contains($common, "key: 'INDUSTRIAL_ACCIDENT_INSURANCE'") && str_contains($common, 'employerOnly: true'),
    'excluded_is_not_unresolved' => str_contains($common, "excluded ? '적용 제외'")
        && str_contains($common, "excluded ? '해당 없음'"),
    'calculated_card_has_no_default_warning' => str_contains($common, "primary ? (primary.calculation_message || primary.business_reason || primary.message || '') : missingMessage"),
    'excluded_reason_persistence_fallback' => str_contains($common, 'primary.business_reason'),
    'excluded_reason_only_in_badge_popover' => !str_contains($common, '미적용 사유:')
        && str_contains($common, 'if (line.message && !excluded)'),
    'tax_has_no_employer_burden' => str_contains($common, "key: 'INCOME_TAX'")
        && str_contains($common, 'employeeOnly: true') && str_contains($common, "line.employeeOnly ? '해당 없음'"),
    'tax_standard_visible_while_input_pending' => str_contains($service, "DEDUCTION:EMPLOYMENT_INCOME_TAX']['statutory_standard_id'")
        && str_contains($service, "DEDUCTION:LOCAL_INCOME_TAX']['rounding_method_code'"),
    'tax_defaults_to_self_one_person' => str_contains($service, "\$dependentCounts[\$employeeId] = 1;"),
    'stored_standard_period_projection' => str_contains($model, 'LEFT JOIN system_statutory_standards')
        && str_contains($model, 'standard_effective_from'),
    'employment_exclusion_projects_employer_lines' => str_contains($service, "EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL")
        && str_contains($service, "'EMPLOYER_BURDEN', 'EMPLOYMENT_INSURANCE_VOCATIONAL'"),
    'regular_unresolved_industrial_line' => str_contains($service, "EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE")
        && str_contains($service, '공식 업종·보험관계가 연결되지 않아 법정기준 확인이 필요합니다.'),
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
