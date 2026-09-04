<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__, 2));
$view = (string) file_get_contents(PROJECT_ROOT.'/app/views/institution/regular-employment-income/index.php');
$script = (string) file_get_contents(PROJECT_ROOT.'/public/assets/js/pages/institution/regular-employment-income/index.js');
$style = (string) file_get_contents(PROJECT_ROOT.'/public/assets/css/pages/institution/regular-employment-income.css');
$model = (string) file_get_contents(PROJECT_ROOT.'/app/Models/Institution/RegularEmploymentIncomeModel.php');
$checks = [
    'main_data_table_preserved' => str_contains($script, "tableSelector:'#regular-income-table'") && str_contains($script, 'createDataTable'),
    'modal_html_grid' => str_contains($view, 'regularIncomeItemsGrid') && str_contains($script, 'createHtmlGrid'),
    'modal_data_table_removed' => !str_contains($script, "tableSelector:'#regularIncomeItems'") && !str_contains($view, 'id="regularIncomeItems"'),
    'modal_table_settings_removed' => !str_contains($script, "tableKey:'regular-income-items'"),
    'summary_present' => str_contains($script, '대상인원 ${items.length}명') && str_contains($script, '총실지급액'),
    'adjustment_contract' => str_contains($script, 'final_amount:finalAmount')
        && str_contains($script, 'adjustment_amount:calculatedAmount===null||finalAmount===null?null:finalAmount-calculatedAmount')
        && str_contains($script, 'adjustment_reason:wasAutomatic?null:(applied.adjustment_reason||null)'),
    'basis_inputs_owned_by_cards' => str_contains($script, 'appendBasisControl(card,line,item)')
        && str_contains($script, "EMPLOYMENT_INCOME_TAX:['dependent_count_snapshot','공제대상 가족수']"),
    'sticky_final_summary' => str_contains($script, 'regular-income-final-summary')
        && str_contains($style, 'position: sticky'),
    'approved_readonly' => str_contains($script, "setReadonly(!EDITABLE.has(documentStatus))"),
    'payroll_item_sequence' => str_contains($script, "key:'sort_no',label:'순번'")
        && str_contains($script, "rowNumberField:'sort_no'")
        && str_contains($script, "grid.on('row:moved'")
        && str_contains($script, 'item.sort_no=index+1')
        && str_contains($script, 'Number(item.sort_no)||items.indexOf(item)+1'),
    'approval_state_blocks_reorder' => str_contains($script, 'if(EDITABLE.has(documentStatus))')
        && str_contains($script, "handle.draggable=true")
        && str_contains($script, "handle.classList.add('is-disabled')"),
    'sequence_drag_handle' => str_contains($script, "createOrderEditor")
        && str_contains($style, '.regular-income-order-handle'),
    'eligible_employee_initial_order' => substr_count($model, 'ORDER BY e.sort_no,e.employee_name,e.id') >= 2,
    'backend_contract_preserved' => !str_contains($script, 'AGGrid') && str_contains($script, 'items})'),
    'modal_width_compacted' => !str_contains($style, 'min-width: 1220px')
        && str_contains($style, 'min-width: 1060px')
        && str_contains($script, "key:'calculation_message',label:'확인사항',width:190"),
    'long_reason_tooltip_preserved' => str_contains($style, '.html-grid-column-calculation_message .html-grid-cell-value'),
    'status_label_compacted' => str_contains($script, "NEEDS_CONFIRMATION:'확인필요'"),
    'pay_summary_totals_only' => !str_contains($script, "const basePayLines=payLines.filter")
        && !str_contains($script, "summaryRow('증감'")
        && str_contains($script, "summaryRow('과세대상'")
        && str_contains($script, "summaryRow('비과세'")
        && str_contains($script, "summaryRow('지급액(세전)'")
        && !str_contains($script, "summaryRow('지급총액'"),
    'pay_tax_badges' => str_contains($script, 'regular-income-pay-tax-badge')
        && str_contains($style, '.regular-income-pay-tax-badge.is-taxable')
        && str_contains($style, 'justify-content: space-between')
        && !str_contains($script, 'function createPayCard('),
    'employment_employer_summary_combined' => str_contains($script, "['EMPLOYMENT_INSURANCE','EMPLOYMENT_INSURANCE_VOCATIONAL'].includes")
        && str_contains($script, "item_name_snapshot:'고용보험 사용자부담'")
        && str_contains($script, "INCOME_INSTITUTION_CARDS.filter(definition=>!definition.employeeOnly")
        && str_contains($script, 'summaryLineRows(employerSummaryLines)'),
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success'=>$failed===[], 'checks'=>$checks, 'failed'=>$failed], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[] ? 0 : 1);
