<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\VoucherEvidenceRecommendationService;
use App\Models\Ledger\VoucherLineSourceRefModel;
use Core\DbPdo;

$db = DbPdo::conn();
$stmt = $db->prepare("SELECT e.id evidence_id,e.import_type FROM ledger_evidence_employee_personal_expense e JOIN approval_personal_expense_items i ON i.id=e.source_personal_expense_item_id WHERE i.personal_expense_id=:document_id AND e.deleted_at IS NULL ORDER BY e.sort_no,e.id");
$stmt->execute([':document_id' => 'a31253c0-e0b4-45bf-bebc-6a4f5c7c53fa']);
$identities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
if (count($identities) !== 9) throw new RuntimeException('개인경비 추천 대상 증빙이 9건이 아닙니다.');

$service = new VoucherEvidenceRecommendationService($db);
$results = $service->recommend($identities);
$coverage = $service->coverage($results);
$sets = $service->recommendationSets($results);
if (($coverage['status'] ?? '') !== 'COMPLETE' || ($coverage['identity_covered_count'] ?? 0) !== 9
    || ($coverage['sub_account_covered_count'] ?? 0) !== 9) {
    throw new RuntimeException('9건 추천의 금액·Identity·보조정보 Coverage가 COMPLETE가 아닙니다: ' . json_encode([
        'coverage' => $coverage,
        'first_result_identity' => array_intersect_key($results[0] ?? [], array_flip(['import_type', 'evidence_id'])),
        'first_lines' => $results[0]['candidates'][0]['lines'] ?? [],
    ], JSON_UNESCAPED_UNICODE));
}
if ($sets === [] || ($sets[0]['is_balanced'] ?? false) !== true
    || (float) $sets[0]['debit_total'] !== 1126100.0 || (float) $sets[0]['credit_total'] !== 1126100.0) {
    throw new RuntimeException('9건 추천안의 차대 균형이 올바르지 않습니다.');
}
$lines = $sets[0]['lines'];
$sourceRefModel = new VoucherLineSourceRefModel($db);
foreach ($lines as $line) {
    foreach ($line['source_refs'] ?? [] as $sourceRef) {
        if (!$sourceRefModel->ruleRevisionExists(
            (string) ($sourceRef['journal_rule_id'] ?? ''),
            (int) ($sourceRef['journal_rule_revision_no'] ?? 0),
            (string) ($sourceRef['accounting_role_code'] ?? ''),
            (string) ($sourceRef['debit_credit'] ?? '')
        )) {
            throw new RuntimeException('저장 전 Rule Revision 검증이 실제 규칙을 찾지 못했습니다.');
        }
    }
}
$debits = array_values(array_filter($lines, static fn(array $line): bool => (float) ($line['debit'] ?? 0) > 0));
$credits = array_values(array_filter($lines, static fn(array $line): bool => (float) ($line['credit'] ?? 0) > 0));
if (count($debits) !== 9 || count($credits) !== 1 || count($lines) !== 10) {
    throw new RuntimeException('개인경비 추천이 증빙별 차변 9줄 + 통합 대변 1줄 구조가 아닙니다.');
}
foreach ($debits as $line) {
    if (count($line['source_refs'] ?? []) !== 1 || (($line['refs'][0]['ref_target'] ?? '') !== 'CLIENT')) {
        throw new RuntimeException('차변 Line의 단일 Source identity 또는 거래처 보조정보가 누락되었습니다.');
    }
}
if (count($credits[0]['source_refs'] ?? []) !== 9 || (($credits[0]['refs'][0]['ref_target'] ?? '') !== 'EMPLOYEE')) {
    throw new RuntimeException('통합 대변 Line의 9개 Source identity 또는 직원 보조정보가 누락되었습니다.');
}
$accountIds = [];
foreach ($db->query("SELECT id,account_code FROM ledger_accounts WHERE deleted_at IS NULL") as $account) {
    $accountIds[(string) $account['account_code']] = (string) $account['id'];
}
$accountCodes = array_flip($accountIds);
$countByAccount = array_count_values(array_map(static fn(array $line): string => (string) $line['account_id'], $debits));
if (($countByAccount[$accountIds['551091'] ?? ''] ?? 0) !== 4 || ($countByAccount[$accountIds['551380'] ?? ''] ?? 0) !== 2) {
    throw new RuntimeException('세금과공과 4건 또는 기타경비 2건의 Item별 차변 Line이 유지되지 않았습니다.');
}

echo json_encode([
    'success' => true, 'coverage' => $coverage, 'line_count' => count($lines),
    'debit_line_count' => count($debits), 'credit_line_count' => count($credits),
    'debit_total' => $sets[0]['debit_total'], 'credit_total' => $sets[0]['credit_total'],
    'credit_source_ref_count' => count($credits[0]['source_refs'] ?? []),
    'debit_lines' => array_map(static fn(array $line): array => [
        'account_code' => $accountCodes[(string) $line['account_id']] ?? (string) $line['account_id'],
        'amount' => (float) $line['debit'],
        'summary' => (string) ($line['summary'] ?? ''),
        'client' => (string) ($line['refs'][0]['ref_name'] ?? ''),
        'source_date' => (string) ($line['source_date'] ?? ''),
        'expense_category' => (string) ($line['expense_category'] ?? ''),
        'source_line_key' => (string) ($line['source_refs'][0]['source_line_key'] ?? ''),
    ], $debits),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
