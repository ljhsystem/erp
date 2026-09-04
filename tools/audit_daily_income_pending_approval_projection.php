<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Models\Approval\ApprovalInboxModel;
use App\Services\Approval\DailyEmploymentIncomeApprovalAdapter;
use Core\DbPdo;

$db = DbPdo::conn();
$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$requestId = '875df432-dc2b-4f50-8e78-25e20050f656';
$rows = static function (PDO $db, string $sql, array $params = []): array {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};
$hash = static fn (array $value): string => hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

$rawRequests = $rows($db, 'SELECT * FROM user_approval_requests WHERE id=:id', [':id' => $requestId]);
if ($rawRequests === []) throw new RuntimeException('운영 결재요청을 찾을 수 없습니다.');
$requesterId = (string) $rawRequests[0]['requester_id'];
$request = (new ApprovalInboxModel($db))->requestDetail($requestId, $requesterId);
if (!$request) throw new RuntimeException('공용 결재 상세 요청 DTO를 조회할 수 없습니다.');
$document = (new DailyEmploymentIncomeApprovalAdapter($db))->detail($request);

$header = $rows($db, 'SELECT * FROM institution_daily_employment_incomes WHERE id=:id', [':id' => $documentId]);
$groups = $rows($db, 'SELECT * FROM institution_daily_employment_income_groups WHERE daily_employment_income_id=:id ORDER BY sort_no,id', [':id' => $documentId]);
$groupIds = array_column($groups, 'id');
$groupMarks = implode(',', array_fill(0, max(count($groupIds), 1), '?'));
$items = $groupIds === [] ? [] : $rows($db, "SELECT * FROM institution_daily_employment_income_items WHERE daily_employment_income_group_id IN ({$groupMarks}) ORDER BY daily_employment_income_group_id,sort_no,id", $groupIds);
$itemIds = array_column($items, 'id');
$itemMarks = implode(',', array_fill(0, max(count($itemIds), 1), '?'));
$workdays = $itemIds === [] ? [] : $rows($db, "SELECT * FROM institution_daily_employment_income_workdays WHERE daily_employment_income_item_id IN ({$itemMarks}) ORDER BY daily_employment_income_item_id,work_date,id", $itemIds);
$lines = $itemIds === [] ? [] : $rows($db, "SELECT * FROM institution_daily_employment_income_lines WHERE daily_employment_income_item_id IN ({$itemMarks}) ORDER BY daily_employment_income_item_id,line_type_code,line_code,id", $itemIds);
$revisions = $rows($db, 'SELECT * FROM institution_daily_employment_income_calculation_revisions WHERE daily_employment_income_id=:id ORDER BY revision_no,id', [':id' => $documentId]);
$revisionIds = array_column($revisions, 'id');
$revisionMarks = implode(',', array_fill(0, max(count($revisionIds), 1), '?'));
$results = $revisionIds === [] ? [] : $rows($db, "SELECT * FROM institution_daily_employment_income_calculation_results WHERE calculation_revision_id IN ({$revisionMarks}) ORDER BY calculation_revision_id,result_type_code,id", $revisionIds);
$steps = $rows($db, 'SELECT * FROM user_approval_request_steps WHERE request_id=:id ORDER BY sort_no,id', [':id' => $requestId]);
$template = $rows($db, 'SELECT * FROM user_approval_templates WHERE id=:id', [':id' => $rawRequests[0]['template_id']]);
$templateSteps = $rows($db, 'SELECT * FROM user_approval_template_steps WHERE template_id=:id ORDER BY sort_no,id', [':id' => $rawRequests[0]['template_id']]);
$closure = $rows($db, 'SELECT * FROM institution_daily_employment_income_closures WHERE daily_employment_income_id=:id ORDER BY id', [':id' => $documentId]);
$accountingLinks = $rows($db, 'SELECT * FROM institution_daily_employment_income_accounting_links WHERE daily_employment_income_id=:id ORDER BY id', [':id' => $documentId]);
$evidence = $rows($db, 'SELECT * FROM ledger_evidence_daily_employment_income WHERE source_daily_employment_income_id=:id ORDER BY id', [':id' => $documentId]);
$linkRows = $rows($db, "SELECT l.* FROM ledger_evidence_links l JOIN ledger_evidence_daily_employment_income e ON e.id=l.evidence_id WHERE e.source_daily_employment_income_id=:id ORDER BY l.id", [':id' => $documentId]);
$transactions = $rows($db, "SELECT t.* FROM ledger_transactions t JOIN ledger_evidence_links l ON l.target_type='TRANSACTION' AND l.target_id=t.id JOIN ledger_evidence_daily_employment_income e ON e.id=l.evidence_id WHERE e.source_daily_employment_income_id=:id ORDER BY t.id", [':id' => $documentId]);
$snapshotColumns = $rows($db, "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('user_approval_requests','user_approval_request_steps') AND (COLUMN_NAME LIKE '%snapshot%' OR COLUMN_NAME LIKE '%payload%' OR COLUMN_NAME LIKE '%document%') ORDER BY TABLE_NAME,ORDINAL_POSITION");
$metadataWaiters = $rows($db, "SELECT ID,USER,DB,COMMAND,TIME,STATE,LEFT(INFO,500) INFO FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND STATE LIKE '%metadata lock%' ORDER BY TIME DESC");
$duplicateGrains = (int) $db->query("SELECT COUNT(*) FROM (SELECT 1 FROM institution_daily_employment_income_calculation_results GROUP BY calculation_revision_id,result_type_code,worker_client_id,daily_employment_income_item_id,workplace_scope_key,workday_scope_key,application_from,application_to,payment_date,payment_sequence HAVING COUNT(*)>1) duplicate_rows")->fetchColumn();
$nullResultItems = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results WHERE daily_employment_income_item_id IS NULL')->fetchColumn();

echo json_encode([
    'read_only' => true,
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'header' => $header[0] ?? null,
    'latest_calculation_revision' => $revisions === [] ? null : end($revisions),
    'result_projection' => array_map(static fn (array $row): array => array_intersect_key($row, array_flip([
        'id','calculation_revision_id','daily_employment_income_item_id','result_type_code','component_code','status_code',
        'confirmed_employee_amount','confirmed_employer_amount','final_amount','source_hash','created_at','created_by',
    ])), $results),
    'approval' => [
        'raw_request' => $rawRequests[0],
        'request_detail_dto' => array_intersect_key($request, array_flip([
            'id','request_id','sort_no','document_no','document_id','document_type','requester_id','requester_name','department_name',
            'requested_at','status','current_step','current_step_id','current_step_name','current_approver_id','current_approver_name',
            'application_date','title','applicant_name',
        ])),
        'template' => $template,
        'template_steps' => $templateSteps,
        'request_steps' => $steps,
        'snapshot_payload_columns' => $snapshotColumns,
    ],
    'adapter_document' => [
        'header' => array_intersect_key($document['header'] ?? [], array_flip([
            'request_id','request_number','document_no','document_id','document_type','title','applicant_id','applicant_name',
            'requester_id','requester_name','department_name','applicant_department_name','application_date','requested_at',
            'status','status_code','total_work_days','total_gross_amount','total_deduction_amount','total_net_payment_amount','total_employer_burden_amount',
        ])),
        'items' => array_map(static fn (array $row): array => array_intersect_key($row, array_flip([
            'id','group_id','business_unit','business_unit_code','business_division_code','business_division_name','worker_id',
            'worker_client_id','worker_name','worker_name_snapshot','total_work_days','total_gross_amount','total_deduction_amount',
            'total_net_payment_amount','total_employer_burden_amount',
        ])), $document['items'] ?? []),
        'totals' => $document['totals'] ?? [],
    ],
    'baseline' => [
        'counts' => [
            'header' => count($header),'groups' => count($groups),'items' => count($items),'workdays' => count($workdays),
            'lines' => count($lines),'revisions' => count($revisions),'results' => count($results),'approval_requests' => count($rawRequests),
            'approval_steps' => count($steps),'evidence' => count($evidence),'transactions' => count($transactions),
            'evidence_links' => count($linkRows),'closures' => count($closure),'accounting_links' => count($accountingLinks),
        ],
        'hashes' => [
            'header' => $hash($header),'groups' => $hash($groups),'items' => $hash($items),'workdays' => $hash($workdays),
            'lines' => $hash($lines),'revisions' => $hash($revisions),'results' => $hash($results),
            'approval_requests' => $hash($rawRequests),'approval_steps' => $hash($steps),
            'evidence' => $hash($evidence),'transactions' => $hash($transactions),'evidence_links' => $hash($linkRows),
            'closures' => $hash($closure),'accounting_links' => $hash($accountingLinks),
        ],
    ],
    'migration_preflight' => [
        'line_count' => count($lines),
        'line_application_status_type' => $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines' AND COLUMN_NAME='application_status_code'")->fetchColumn(),
        'result_null_item_count' => $nullResultItems,
        'new_result_grain_duplicate_count' => $duplicateGrains,
        'metadata_lock_waiters' => $metadataWaiters,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
