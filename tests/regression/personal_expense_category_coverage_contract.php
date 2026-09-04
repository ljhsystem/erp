<?php

declare(strict_types=1);

$root=dirname(__DIR__,2);
$migration=(string)file_get_contents($root.'/app/migrations/20260825_03_seed_personal_expense_category_coverage_rules.up.sql');
$down=(string)file_get_contents($root.'/app/migrations/20260825_03_seed_personal_expense_category_coverage_rules.down.sql');
$model=(string)file_get_contents($root.'/app/Models/Ledger/JournalRuleModel.php');
$view=(string)file_get_contents($root.'/app/views/ledger/journal_rules/index.php');
$js=(string)file_get_contents($root.'/public/assets/js/pages/ledger/journalRules.js');
$service=(string)file_get_contents($root.'/app/Services/Ledger/JournalRuleService.php');
$checks=[
    substr_count($migration,"'PE_DEBIT_")>=30,
    str_contains($migration,"'PE_DEBIT_OTHER'"),
    str_contains($migration,"'OTHER','551380'"),
    str_contains($migration,'r.item_code'),
    !str_contains($migration,'request_key'),
    str_contains($migration,"'CREATE',NULL"),
    str_contains($migration,'r.debit_account_id IS NULL AND r.credit_account_id IS NULL AND r.vat_account_id IS NULL'),
    str_contains($down,'forward-only'),
    str_contains($model,'result_account_code')&&str_contains($model,'latest_revision_id'),
    str_contains($view,'name="accounting_role_code"')&&str_contains($view,'name="account_id"')&&str_contains($view,'name="item_code"'),
    str_contains($js,"settingsKey: 'condition_hash'")&&str_contains($js,"settingsKey: 'revision_no'"),
    str_contains($service,'SYSTEM 분개규칙은 일반 저장 API에서 생성하거나 수정할 수 없습니다.'),
];
if(in_array(false,$checks,true)){fwrite(STDERR,"개인경비 분류 Coverage 계약 검증 실패\n");exit(1);}echo "개인경비 분류 Coverage 계약 검증 통과\n";
