<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/app/migrations/20260825_02_seed_personal_expense_role_based_journal_rules.up.sql');
$down = file_get_contents($root . '/app/migrations/20260825_02_seed_personal_expense_role_based_journal_rules.down.sql');
$recommendation = file_get_contents($root . '/app/Services/Ledger/VoucherEvidenceRecommendationService.php');
$repository = file_get_contents($root . '/app/Repositories/Ledger/JournalCandidateRepository.php');
$resolver = file_get_contents($root . '/app/Services/Ledger/JournalRuleEvaluationService.php');

foreach ([$migration, $down, $recommendation, $repository, $resolver] as $source) {
    if ($source === false) throw new RuntimeException('역할형 개인경비 계약 파일을 읽을 수 없습니다.');
}

$contracts = [
    substr_count($migration, "'PE_DEBIT_") === 20,
    str_contains($migration, "'PE_CREDIT_EMPLOYEE_ACCRUED'"),
    str_contains($migration, "'PERSONAL_EXPENSE_ITEM','ITEM',NULL"),
    str_contains($migration, 'debit_account_id IS NULL AND r.credit_account_id IS NULL AND r.vat_account_id IS NULL'),
    str_contains($migration, "'CREATE',NULL"),
    !str_contains($migration, 'request_key'),
    str_contains($down, 'forward-only'),
    str_contains($recommendation, "'SOURCE_CONTEXT_MISSING'"),
    str_contains($recommendation, "'ITEM_CODE_MISSING'"),
    str_contains($recommendation, "'JOURNAL_RULE_NOT_MATCHED'"),
    str_contains($repository, "accounting_role_code='EMPLOYEE_ACCRUED_EXPENSE'"),
    str_contains($resolver, "\$generic['item_code'] = ''"),
];

if (in_array(false, $contracts, true)) {
    fwrite(STDERR, "개인경비 역할형 Rule Seed·추천 계약 검증 실패\n");
    exit(1);
}

echo "개인경비 역할형 Rule Seed·추천 계약 검증 통과\n";
