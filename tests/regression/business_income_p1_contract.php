<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
$read=static fn(string $path):string=>file_get_contents(PROJECT_ROOT.'/'.$path)?:'';
$migration=implode("\n",array_map($read,array_map(static fn(int $number):string=>'app/migrations/20260903_0'.$number.'_'.[
    1=>'create_client_tax_profile_ssot',2=>'create_business_income_core',3=>'create_business_income_calculation',4=>'create_business_income_workflow',5=>'create_business_income_evidence',6=>'create_business_income_evidence_raw_lines',7=>'register_business_income_page_approval',8=>'register_business_income_evidence_metadata',9=>'activate_business_income_runtime'][$number].'.up.sql',range(1,9))));
$calculation=$read('app/Services/Institution/BusinessIncomeCalculationService.php');
$generation=$read('app/Services/Institution/BusinessIncomeTransactionGenerationService.php');
$policy=$read('app/Services/Ledger/EvidenceTypePolicyService.php');
$checks=[
    '세무 프로필 기간중복 차단'=>str_contains($migration,'trg_client_tax_profile_no_overlap_insert')&&str_contains($migration,'trg_client_tax_profile_no_overlap_update'),
    'Header Group Item Grain'=>str_contains($migration,'institution_business_incomes')&&str_contains($migration,'institution_business_income_groups')&&str_contains($migration,'institution_business_income_items'),
    '상태 분리'=>str_contains($migration,'calculation_status')&&str_contains($migration,'approval_status')&&str_contains($migration,'withholding_filing_status')&&str_contains($migration,'simplified_statement_status'),
    'Snapshot Hash'=>str_contains($migration,'recipient_tax_snapshot_json')&&str_contains($migration,'source_hash')&&str_contains($migration,'business_key_hash'),
    'Canonical Evidence'=>str_contains($generation,"'source_type' => 'INTERNAL_APPROVAL'")&&str_contains($generation,"'transaction_direction' => 'EXPENSE'")&&str_contains($generation,"'operation_type' => 'BUSINESS_INCOME'")&&str_contains($generation,"public const EVIDENCE_TYPE = 'BUSINESS_INCOME_REPORT'"),
    'Evidence 원본금액 Grain'=>str_contains($generation,"'raw_gross_payment_amount' => \$item['gross_payment_amount']")&&!str_contains($generation,"'total_amount' =>"),
    'Legacy API Alias'=>str_contains($policy,"'BUSINESS_INCOME' => 'BUSINESS_INCOME_REPORT'")&&!str_contains($policy,"'BUSINESS_INCOME_REPORT' => 'BUSINESS_INCOME'"),
    '지방소득세 계산기초'=>str_contains($calculation,'$localBefore=$incomeTax*(float)$localRate'),
    '요율 상수 금지'=>!str_contains($calculation,'0.03')&&!str_contains($calculation,'0.003'),
    'Raw Line 전체 보존'=>str_contains($generation,"'ledger_evidence_business_income_raw_lines'")&&str_contains($generation,"'source_calculation_line_id'"),
    'Settlement 양수만'=>str_contains($generation,"calculated_amount'] <= 0")&&str_contains($generation,"'amount_sign' => 'MINUS'"),
    '산출물 한 건 Grain'=>str_contains($migration,'uk_business_income_artifact_item')&&str_contains($generation,'upsertAutoTransactionEvidence'),
    '전표 책임 없음'=>!preg_match('/Voucher|Journal|Posting|account_id|account_code/', $generation),
    '공용 거래 생성 사용'=>str_contains($generation,'TransactionCrudService')&&str_contains($generation,'EvidenceLinkModel'),
    '운영 자동 실행 없음'=>!str_contains($migration,'migrate:run'),
];
$failed=array_keys(array_filter($checks,static fn(bool $passed):bool=>!$passed));
echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
