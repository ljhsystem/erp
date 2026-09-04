<?php
declare(strict_types=1);

use App\Services\Institution\EmploymentRuleService;
use App\Services\Institution\EmploymentRuleResolver;
use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;
use Core\PageKeyResolver;
use Core\Session;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
Session::start(30);
$user = $db->query("SELECT id,username FROM auth_users WHERE is_active=1 AND approved=1 ORDER BY CASE WHEN username='ljhsystem' THEN 0 ELSE 1 END LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('Fixture 사용자 계정이 없습니다.');
$_SESSION['user'] = ['id'=>$user['id'], 'username'=>$user['username']];
$companyId = (string) $db->query('SELECT id FROM system_company ORDER BY id LIMIT 1')->fetchColumn();
if ($companyId === '') throw new RuntimeException('Fixture 회사가 없습니다.');

$uuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
};
$today = new DateTimeImmutable('today');
$past = $today->modify('-30 days')->format('Y-m-d');
$future = $today->modify('+10 days')->format('Y-m-d');
$futureAfter = $today->modify('+15 days')->format('Y-m-d');
$checks = [];
$performance = [];
$measure = static function (callable $callback, int $iterations = 20): array {
    $startedAt = hrtime(true);
    $result = null;
    for ($index = 0; $index < $iterations; $index++) $result = $callback();
    return [
        'average_ms' => round((hrtime(true) - $startedAt) / 1_000_000 / $iterations, 3),
        'payload_bytes' => strlen((string) json_encode($result, JSON_UNESCAPED_UNICODE)),
    ];
};
$before = [];
foreach (['institution_employment_rules','institution_employment_rules_revisions','institution_employment_rules_audits'] as $table) {
    $before[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

$approve = static function (EmploymentRuleService $service, PDO $db, string $revisionId, string $requesterId): void {
    $_SESSION['user'] = ['id'=>$requesterId];
    $submitted = $service->submit($revisionId, $requesterId);
    $requestId = (string) $submitted['data']['request_id'];
    $statement = $db->prepare("SELECT id,approver_id FROM user_approval_request_steps WHERE request_id=:request AND step_type IN ('APPROVAL','FINAL_APPROVAL') ORDER BY sort_no");
    $statement->execute([':request'=>$requestId]);
    $steps = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$steps) throw new RuntimeException('승인할 결재단계가 없습니다.');
    foreach ($steps as $step) {
        $approverId = (string) ($step['approver_id'] ?: $requesterId);
        $_SESSION['user'] = ['id'=>$approverId];
        $service->act((string) $step['id'], 'approved', '규정 Closure rollback Fixture', $approverId);
    }
    $_SESSION['user'] = ['id'=>$requesterId];
};

$db->beginTransaction();
try {
    $service = new EmploymentRuleService($db);
    $resolver = new EmploymentRuleResolver($db);
    $base = [
        'company_id'=>$companyId, 'regulation_type_code'=>'EMPLOYMENT_RULE',
        'regulation_code'=>'FIXTURE-RULE-' . substr($uuid(), 0, 8), 'regulation_title'=>'Fixture 취업규칙',
        'title'=>'최초 제정', 'change_reason'=>'최초 제정', 'change_summary'=>'Rollback Fixture',
        'revision_date'=>$past, 'effective_from'=>$past, 'effective_to'=>'', 'content_text'=>'공식 규정문서 Fixture',
        'request_key'=>$uuid(),
    ];
    $revision1 = $service->save($base)['data'];
    $checks['header_and_draft'] = $revision1['revision_no'] === 1 && $revision1['status_code'] === 'DRAFT';
    $approve($service, $db, (string) $revision1['id'], (string) $user['id']);
    $revision1 = $service->activate((string) $revision1['id'])['data'];
    $checks['immediate_effective'] = $revision1['status_code'] === 'EFFECTIVE';

    $revision2 = $service->revise(array_replace($base, [
        'id'=>$revision1['id'], 'title'=>'제2차 개정', 'change_reason'=>'미래 시행 검증',
        'revision_date'=>$today->format('Y-m-d'), 'effective_from'=>$future, 'request_key'=>$uuid(),
    ]))['data'];
    $approve($service, $db, (string) $revision2['id'], (string) $user['id']);
    $revision2 = $service->activate((string) $revision2['id'])['data'];
    $checks['future_scheduled'] = $revision2['status_code'] === 'SCHEDULED';
    $resolvedToday = $resolver->resolve($companyId, (string) $revision1['rule_id'], $today->format('Y-m-d'));
    $resolvedFuture = $resolver->resolve($companyId, (string) $revision1['rule_id'], $futureAfter);
    $resolvedPast = $resolver->resolve($companyId, (string) $revision1['rule_id'], $past);
    $checks['current_resolver'] = ($resolvedToday['id'] ?? '') === $revision1['id'];
    $checks['future_resolver'] = ($resolvedFuture['id'] ?? '') === $revision2['id'];
    $checks['historical_resolver'] = ($resolvedPast['id'] ?? '') === $revision1['id'];

    $overlapDraft = $service->revise(array_replace($base, [
        'id'=>$revision1['id'], 'title'=>'중복기간 검증', 'change_reason'=>'중복기간 검증',
        'revision_date'=>$today->format('Y-m-d'), 'effective_from'=>$today->modify('+5 days')->format('Y-m-d'), 'request_key'=>$uuid(),
    ]))['data'];
    $approve($service, $db, (string) $overlapDraft['id'], (string) $user['id']);
    $db->exec('SAVEPOINT overlap_guard');
    try {
        $service->activate((string) $overlapDraft['id']);
        throw new RuntimeException('시행기간 중복이 차단되지 않았습니다.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), '겹칩니다')) throw $exception;
        $checks['overlap_guard'] = true;
        $db->exec('ROLLBACK TO SAVEPOINT overlap_guard');
    }

    try {
        $service->save(array_replace($base, ['id'=>$revision1['id'], 'request_key'=>$uuid()]));
        throw new RuntimeException('시행 Revision 직접 수정이 차단되지 않았습니다.');
    } catch (RuntimeException $exception) {
        $checks['effective_update_guard'] = str_contains($exception->getMessage(), '직접 수정');
    }
    try {
        $service->delete((string) $revision1['id']);
        throw new RuntimeException('시행 Revision 삭제가 차단되지 않았습니다.');
    } catch (RuntimeException $exception) {
        $checks['effective_delete_guard'] = str_contains($exception->getMessage(), '초안 또는 회수');
    }

    $draft = $service->save(array_replace($base, [
        'regulation_code'=>'FIXTURE-DRAFT-' . substr($uuid(), 0, 8), 'regulation_title'=>'삭제 Fixture', 'request_key'=>$uuid(),
    ]))['data'];
    $service->delete((string) $draft['id']);
    $checks['draft_delete'] = true;
    $personnel = $service->save(array_replace($base, [
        'regulation_type_code'=>'PERSONNEL_REGULATION', 'regulation_code'=>'FIXTURE-PERSONNEL-' . substr($uuid(), 0, 8),
        'regulation_title'=>'Fixture 인사규정', 'request_key'=>$uuid(),
    ]))['data'];
    $checks['personnel_type'] = $personnel['regulation_type_code'] === 'PERSONNEL_REGULATION';
    $page = $service->list(['start'=>0, 'length'=>50, 'filters'=>json_encode([['field'=>'regulation_type_code','value'=>'PERSONNEL_REGULATION']]), 'columns'=>[['data'=>'revision_no']], 'order'=>[['column'=>0,'dir'=>'desc']]]);
    $checks['filter_and_sort'] = count($page['data']) >= 1 && $page['data'][0]['regulation_type_code'] === 'PERSONNEL_REGULATION';
    $metadata = (new DataTableColumnMetaService($db))->columnsForDomain('employment-rule');
    $keys = array_column($metadata, 'key');
    $checks['table_settings_metadata'] = in_array('institution_employment_rules.regulation_code', $keys, true)
        && in_array('institution_employment_rules_revisions.revision_date', $keys, true);
    $checks['registry'] = (int) $db->query("SELECT COUNT(*) FROM system_page_registry WHERE page_key='web.institution.human_resources.employment_rules'")->fetchColumn() === 1;
    $checks['permission_page_key'] = (int) $db->query("SELECT COUNT(*) FROM auth_permissions WHERE (permission_key='web.institution.human_resources.employment_rules' OR permission_key LIKE 'api.institution.human_resources.employment_rules.%') AND page_key IS NULL")->fetchColumn() === 0;
    $checks['permission_route_resolver'] = (new PageKeyResolver($db))->resolve('api.institution.human_resources.employment_rules.view') === 'web.institution.human_resources.employment_rules';
    $performance['options'] = $measure(static fn(): array => $service->options());
    $performance['list'] = $measure(static fn(): array => $service->list(['start'=>0,'length'=>20]));
    $performance['detail'] = $measure(static fn(): array => $service->detail((string) $revision1['id']));
    $performance['history'] = $measure(static fn(): array => $service->history((string) $revision1['rule_id']));
    $performance['table_settings_metadata'] = $measure(static fn(): array => (new DataTableColumnMetaService($db))->columnsForDomain('employment-rule'));
    $db->rollBack();
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}

$after = [];
foreach (['institution_employment_rules','institution_employment_rules_revisions','institution_employment_rules_audits'] as $table) {
    $after[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}
$checks['fixture_rollback'] = $before === $after;
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success'=>$failed === [], 'checks'=>$checks, 'performance'=>$performance, 'before'=>$before, 'after'=>$after, 'failed'=>$failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
exit($failed === [] ? 0 : 1);
