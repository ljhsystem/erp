<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$rules = $read('app/migrations/20260824_05_extend_journal_rule_learning_ssot.up.sql');
$revisions = $read('app/migrations/20260824_06_create_journal_rule_revisions.up.sql');
$sourceRefs = $read('app/migrations/20260824_07_create_voucher_line_source_refs.up.sql');
$legacy = $read('app/migrations/20260824_08_exclude_legacy_journal_learning_events.up.sql');
$policy = $read('app/migrations/20260824_09_seed_journal_learning_policy_baseline.up.sql');
$guard = $read('app/Services/Ledger/JournalRecommendationGuardService.php');
$policyService = $read('app/Services/Ledger/JournalLearningPolicyService.php');
$revisionService = $read('app/Services/Ledger/JournalRuleRevisionService.php');
$sourceRefService = $read('app/Services/Ledger/VoucherLineSourceRefService.php');
$postedService = $read('app/Services/Ledger/JournalPostedLearningService.php');
$controller = $read('app/Controllers/Ledger/VoucherController.php');

$checks = [
    '규칙 회사 범위' => str_contains($rules, '`company_id` varchar(50)') && str_contains($rules, 'fk_journal_rule_company'),
    '규칙 import_type SSOT' => str_contains($rules, '`condition_hash`') && !str_contains($rules, 'ADD COLUMN IF NOT EXISTS `evidence_type`'),
    '기존 복합규칙 자동분해 차단' => str_contains($rules, '기존 복합 분개규칙이 존재하여 자동 단일역할 Backfill을 수행할 수 없습니다.'),
    '상태 호환값 DB 강제' => str_contains($rules, 'chk_journal_rule_status_active') && str_contains($rules, "`rule_status`='ACTIVE'"),
    '조건 Hash 단독 UNIQUE 없음' => !str_contains($rules, 'UNIQUE KEY `uk_journal_rule_condition'),
    'Revision 불변성' => str_contains($revisions, 'UNIQUE KEY `uk_journal_rule_revision` (`rule_id`,`revision_no`)')
        && !str_contains($revisions, 'FOREIGN KEY (`rule_id`)')
        && str_contains($revisionService, 'FOR UPDATE') === false
        && str_contains($read('app/Models/Ledger/JournalRuleRevisionModel.php'), 'FOR UPDATE'),
    'Source Ref 안정키' => str_contains($sourceRefs, 'UNIQUE KEY `uk_voucher_line_source_ref_key` (`source_ref_key`)')
        && str_contains($sourceRefService, "\$row['voucher_line_id']")
        && str_contains($sourceRefService, "\$row['allocation_sequence']"),
    'Source Ref 금액 방향 분리' => str_contains($sourceRefs, '`source_amount` decimal(18,2) NOT NULL')
        && str_contains($sourceRefs, '`allocated_amount` decimal(18,2) NOT NULL')
        && str_contains($sourceRefs, 'chk_source_ref_amounts'),
    'Rule Revision Pair' => str_contains($sourceRefs, 'chk_source_ref_rule_pair'),
    'Learning Event 안정키' => str_contains($rules, 'UNIQUE KEY `uk_journal_learning_event_key` (`event_key`)')
        && str_contains($rules, 'chk_journal_learning_trace')
        && str_contains($postedService, "'POSTED_CONFIRMATION'"),
    'Legacy 정확히 5건' => str_contains($legacy, 'IF legacy_count <> 5')
        && str_contains($legacy, "`learning_status`='IGNORED'")
        && str_contains($legacy, "`decision_code`='LEGACY_EVENT_EXCLUDED'"),
    '전역 Baseline 회사 Override' => str_contains($policy, 'journal_learning_policy.default')
        && str_contains($policyService, "'journal_learning_policy.'"),
    'Migration Actor 강제' => str_contains($policy, '@journal_learning_actor')
        && str_contains($policy, "NOT LIKE 'SYSTEM:%'"),
    '자동승격 및 급여 비활성' => str_contains($policy, '"auto_promotion_enabled":false')
        && str_contains($policy, '"PAYROLL":false'),
    '급여 추천 이중 Guard' => substr_count($controller, 'recommendationGuardService->assert') === 2
        && str_contains($guard, 'PAYROLL_SNAPSHOT_CONTRACT_NOT_READY')
        && str_contains($guard, '급여 승인 Snapshot의 원천 Line 추적계약이 준비되지 않아 분개추천을 제공할 수 없습니다.'),
    '파괴적 Down 차단' => str_contains($read('app/migrations/20260824_07_create_voucher_line_source_refs.down.sql'), 'Down Migration을 차단했습니다.')
        && str_contains($read('app/migrations/20260824_08_exclude_legacy_journal_learning_events.down.sql'), 'Forward Fix만 허용합니다.'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'PASS: journal learning SSOT safety contract' . PHP_EOL;
