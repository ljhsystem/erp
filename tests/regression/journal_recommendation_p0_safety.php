<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Repositories\Ledger\JournalCandidateRepository;
use App\Services\Ledger\JournalCandidateEngineService;
use App\Services\Ledger\JournalCandidateProviderInterface;
use App\Services\Ledger\VoucherEvidenceRecommendationService;
use Core\DbPdo;

function p0Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class P0FixedCandidateProvider implements JournalCandidateProviderInterface
{
    public function __construct(private array $candidate)
    {
    }

    public function provide(array $context): array
    {
        return [$this->candidate];
    }
}

$pdo = DbPdo::conn();
$accounts = $pdo->query(
    "SELECT id, is_posting FROM ledger_accounts
      WHERE deleted_at IS NULL AND is_active = 1
      ORDER BY is_posting DESC, sort_no ASC"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$postingIds = array_values(array_map(
    static fn(array $row): string => (string) $row['id'],
    array_filter($accounts, static fn(array $row): bool => (int) $row['is_posting'] === 1)
));
$nonPostingRows = array_values(array_filter($accounts, static fn(array $row): bool => (int) $row['is_posting'] === 0));
$nonPostingId = (string) ($nonPostingRows[0]['id'] ?? '');
p0Assert(count($postingIds) >= 2, '전기 가능한 계정 Fixture가 부족합니다.');
p0Assert($nonPostingId !== '', '전기 불가 계정 Fixture가 없습니다.');

$context = [
    'business_unit' => 'HQ',
    'operation_type' => 'GENERAL',
    'transaction_direction' => 'OUT',
    'import_type' => 'P0_TEST',
    'client_id' => '',
    'project_id' => '',
    'total_amount' => 100,
];
$candidate = static fn(string $source, string $debit, string $credit): array => [
    'accounts' => ['debit' => $debit, 'credit' => $credit, 'vat' => ''],
    'source' => $source,
    'source_id' => 'P0_TEST',
    'metrics' => [],
];

$withoutRule = new JournalCandidateEngineService($pdo, [
    new P0FixedCandidateProvider($candidate('LEARNING_EVENT', $postingIds[0], $postingIds[1])),
]);
p0Assert($withoutRule->topCandidates($context)['candidate_count'] === 0, '공식 규칙 없는 후보가 반환됐습니다.');

$nonPosting = new JournalCandidateEngineService($pdo, [
    new P0FixedCandidateProvider($candidate('JOURNAL_RULE', $nonPostingId, $postingIds[1])),
]);
p0Assert($nonPosting->topCandidates($context)['candidate_count'] === 0, '전기 불가 계정 후보가 반환됐습니다.');

$official = new JournalCandidateEngineService($pdo, [
    new P0FixedCandidateProvider($candidate('JOURNAL_RULE', $postingIds[0], $postingIds[1])),
]);
$officialResult = $official->topCandidates($context);
p0Assert($officialResult['candidate_count'] === 1, '안전한 공식 규칙 후보가 반환되지 않았습니다.');
p0Assert(($officialResult['candidates'][0]['balanced'] ?? false) === true, '공식 규칙 후보가 불균형합니다.');

$repository = new JournalCandidateRepository($pdo);
p0Assert($repository->learningPatterns($context) === [], '비공식 또는 미확정 학습 이벤트가 추천에 포함됐습니다.');

$recommendationService = new VoucherEvidenceRecommendationService($pdo);
$result = static fn(array $candidate): array => [
    'status' => 'RECOMMENDED',
    'candidates' => [$candidate],
];
$unresolvedCandidate = [
    'balanced' => true,
    'score' => 10,
    'lines' => [
        ['line_type' => 'DEBIT', 'account_id' => '', 'debit' => 100, 'credit' => 0],
        ['line_type' => 'CREDIT', 'account_id' => $postingIds[1], 'debit' => 0, 'credit' => 100],
    ],
];
p0Assert($recommendationService->recommendationSets([$result($unresolvedCandidate)]) === [], '미결정 후보가 적용 가능한 추천으로 조립됐습니다.');

$unbalancedCandidate = $unresolvedCandidate;
$unbalancedCandidate['balanced'] = false;
$unbalancedCandidate['lines'][0]['account_id'] = $postingIds[0];
$unbalancedCandidate['lines'][1]['credit'] = 90;
p0Assert($recommendationService->recommendationSets([$result($unbalancedCandidate)]) === [], '불균형 후보가 적용 가능한 추천으로 조립됐습니다.');

$completeCandidate = $unresolvedCandidate;
$completeCandidate['lines'][0]['account_id'] = $postingIds[0];
$sets = $recommendationService->recommendationSets([$result($completeCandidate)]);
p0Assert(count($sets) === 1 && ($sets[0]['is_applicable'] ?? false) === true, '완전한 균형 후보의 적용 계약이 잘못됐습니다.');

$recentProviderSource = file_get_contents(PROJECT_ROOT . '/app/Services/Ledger/JournalRecentPatternCandidateProvider.php');
$uiSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/ledger/voucher/actions.js');
p0Assert(is_string($recentProviderSource) && !str_contains($recentProviderSource, 'recentPatterns($context)'), 'Legacy 최근패턴 Provider가 저장소를 조회합니다.');
p0Assert(is_string($uiSource) && str_contains($uiSource, "candidate.is_applicable === true"), '추천 적용 UI의 서버 상태 검증이 없습니다.');
p0Assert(str_contains($uiSource, 'Math.abs(debitTotal - creditTotal) < 0.01'), '추천 적용 UI의 차대 불변식 검증이 없습니다.');

echo "journal recommendation P0 safety: OK\n";
