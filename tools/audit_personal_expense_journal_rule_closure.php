<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\JournalRuleEvaluationService;
use App\Services\Ledger\VoucherEvidenceRecommendationService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$companyId = (string) $pdo->query('SELECT id FROM system_company')->fetchColumn();
$rules = $pdo->query("SELECT r.id,r.rule_code,r.rule_name,r.condition_hash,r.accounting_role_code,r.debit_credit,r.item_code,a.account_code,r.revision_no,r.debit_account_id,r.credit_account_id,r.vat_account_id FROM ledger_journal_rules r JOIN ledger_accounts a ON a.id=r.account_id WHERE r.operation_type='PERSONAL_EXPENSE' ORDER BY r.sort_no")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$revisions = $pdo->query("SELECT rv.id,rv.rule_id,rv.revision_no,rv.action_code,rv.change_reason,rv.created_by FROM ledger_journal_rule_revisions rv JOIN ledger_journal_rules r ON r.id=rv.rule_id WHERE r.operation_type='PERSONAL_EXPENSE' ORDER BY r.sort_no")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$resolver = new JournalRuleEvaluationService($pdo);
$resolverResults = [];
foreach (['TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES','TRANSPORTATION','MEAL'] as $itemCode) {
    $context = ['company_id'=>$companyId,'business_unit'=>'CONSTRUCTION','operation_type'=>'PERSONAL_EXPENSE','transaction_direction'=>'OUT','client_type'=>'','import_type'=>'EMPLOYEE_EXPENSE_PERSONAL','source_type'=>'PERSONAL_EXPENSE_ITEM','source_line_type'=>'ITEM','item_code'=>$itemCode,'base_date'=>'2013-07-19'];
    $resolverResults[$itemCode] = $resolver->evaluate($context);
}
$mismatch = $resolver->evaluate(['company_id'=>$companyId,'business_unit'=>'CONSTRUCTION','operation_type'=>'PERSONAL_EXPENSE','transaction_direction'=>'OUT','client_type'=>'','import_type'=>'EMPLOYEE_EXPENSE_PERSONAL','source_type'=>'OTHER','source_line_type'=>'ITEM','item_code'=>'TAXES_AND_DUES','base_date'=>'2013-07-19']);

$evidences = $pdo->query("SELECT id AS evidence_id,import_type,raw_item_name,raw_expense_category,raw_total_amount FROM ledger_evidence_employee_personal_expense WHERE deleted_at IS NULL AND raw_total_amount IN (800000,25000) ORDER BY raw_total_amount DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$classifications = $pdo->query("SELECT item.id AS personal_expense_item_id,item.expense_category AS original_item_category,evidence.id AS evidence_id,evidence.raw_expense_category AS original_evidence_category,correction.id AS correction_id,correction.revision_no,correction.corrected_category,COALESCE(correction.corrected_category,item.expense_category) AS effective_expense_category,correction.correction_reason,correction.processed_at,correction.processed_by FROM approval_personal_expense_items item JOIN ledger_evidence_employee_personal_expense evidence ON evidence.source_personal_expense_item_id=item.id LEFT JOIN approval_personal_expense_item_classification_corrections correction ON correction.personal_expense_item_id=item.id AND correction.revision_no=(SELECT MAX(latest.revision_no) FROM approval_personal_expense_item_classification_corrections latest WHERE latest.personal_expense_item_id=item.id) WHERE evidence.raw_item_name='법인도장' AND evidence.raw_total_amount=25000")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$identities = array_map(static fn(array $row): array => ['import_type'=>(string)$row['import_type'],'evidence_id'=>(string)$row['evidence_id']], $evidences);
$recommendationService = new VoucherEvidenceRecommendationService($pdo);
$recommendationResults = $recommendationService->recommend($identities);
$recommendationSets = $recommendationService->recommendationSets($recommendationResults);

$accountCodeById = [];
foreach ($pdo->query('SELECT id,account_code FROM ledger_accounts')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $account) $accountCodeById[(string)$account['id']] = (string)$account['account_code'];
$project = static function (array $result) use ($accountCodeById): array {
    $lines = [];
    foreach (($result['candidate']['lines'] ?? []) as $line) {
        $lines[] = ['side'=>$line['line_type'] ?? '', 'account_code'=>$accountCodeById[(string)($line['account_id'] ?? '')] ?? '', 'debit'=>$line['debit'] ?? 0, 'credit'=>$line['credit'] ?? 0];
    }
    return ['evidence_id'=>$result['evidence_id'] ?? '', 'status'=>$result['status'] ?? '', 'reason_code'=>$result['reason_code'] ?? '', 'message'=>$result['message'] ?? '', 'lines'=>$lines];
};

echo json_encode([
    'success'=>count($rules)===6 && count($revisions)===6,
    'rules'=>$rules,
    'revisions'=>$revisions,
    'resolver'=>array_map(static fn(array $result): array => ['resolved'=>array_map(static fn(array $rule): string => (string)$rule['rule_code'], $result['resolved'] ?? []),'conflicts'=>array_keys($result['conflicts'] ?? [])], $resolverResults),
    'source_mismatch_resolved'=>count($mismatch['resolved'] ?? []),
    'evidences'=>$evidences,
    'classifications'=>$classifications,
    'recommendation_results'=>array_map($project, $recommendationResults),
    'recommendation_sets'=>$recommendationSets,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
