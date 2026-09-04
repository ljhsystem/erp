<?php

namespace App\Services\Ledger;

use App\Services\Approval\PersonalExpenseClassificationProjectionService;
use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Repositories\Ledger\JournalCandidateRepository;
use Core\LoggerFactory;
use PDO;

class VoucherEvidenceRecommendationService
{
    private EvidenceSourceRepository $evidenceRepository;
    private JournalCandidateEngineService $candidateEngine;
    private JournalCandidateRepository $candidateRepository;
    private PersonalExpenseClassificationProjectionService $classificationProjection;
    private $logger;
    private ?string $companyId = null;

    public function __construct(PDO $pdo)
    {
        $this->evidenceRepository = new EvidenceSourceRepository($pdo);
        $this->candidateEngine = new JournalCandidateEngineService($pdo);
        $this->candidateRepository = new JournalCandidateRepository($pdo);
        $this->classificationProjection = new PersonalExpenseClassificationProjectionService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.VoucherEvidenceRecommendationService');
    }

    public function recommend(array $identities, ?string $accountingDate = null): array
    {
        $accountingDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $accountingDate)) === 1
            ? trim((string) $accountingDate)
            : date('Y-m-d');
        try {
            return $this->recommendInternal($identities, $accountingDate);
        } catch (\Throwable $e) {
            $this->logger->error('분개추천 후보 조회에 실패했습니다.', [
                'evidence_count' => count($identities),
                'exception' => $e::class,
                'error_code' => get_class($e),
                'error' => $e,
            ]);
            throw new \RuntimeException('분개추천 조회 중 오류가 발생했습니다. 수동으로 분개를 입력해 주세요.');
        }
    }

    private function recommendInternal(array $identities, string $accountingDate): array
    {
        $entries = [];
        $normalizedIdentities = $this->normalizeIdentities($identities);
        $evidenceRows = $this->evidenceRepository->findMany($normalizedIdentities);
        $itemIds = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['source_personal_expense_item_id'] ?? '')),
            $evidenceRows
        )));
        $classifications = $this->classificationProjection->forItemIds($itemIds);
        foreach ($normalizedIdentities as $identity) {
            $key = $identity['import_type'] . "\0" . $identity['evidence_id'];
            $evidence = $evidenceRows[$key] ?? null;
            $itemId = trim((string) ($evidence['source_personal_expense_item_id'] ?? ''));
            if ($evidence && isset($classifications[$itemId])) {
                $evidence['original_expense_category'] = $classifications[$itemId]['original_expense_category'];
                $evidence['corrected_expense_category'] = $classifications[$itemId]['corrected_expense_category'];
                $evidence['effective_expense_category'] = $classifications[$itemId]['effective_expense_category'];
                $evidence['raw_expense_category'] = $classifications[$itemId]['effective_expense_category'];
            }
            $entries[] = ['identity' => $identity, 'evidence' => $evidence];
        }
        $paired = $this->pairedFundEvidenceKeys($entries);
        $results = [];
        foreach ($entries as $entry) {
            $identity = $entry['identity'];
            $evidence = $entry['evidence'];
            if (!$evidence) {
                $results[] = $this->result($identity, 'BLOCKED', 'NONE', 'EVIDENCE_NOT_FOUND', '증빙원본을 찾을 수 없어 추가할 수 없습니다.');
                continue;
            }
            $evidenceStatus = strtoupper(trim((string) ($evidence['evidence_status'] ?? '')));
            if ($evidenceStatus !== 'COMPLETED') {
                $results[] = $this->result($identity, 'BLOCKED', 'NONE', 'EVIDENCE_NOT_READY', '완료된 증빙만 추가할 수 있습니다.');
                continue;
            }
            if (isset($paired[$identity['import_type'] . ':' . $identity['evidence_id']])) {
                $results[] = $this->result($identity, 'ALLOWED', 'PAIRED', 'PAIRED_WITH_DATA_EVIDENCE', '동일 거래의 자료증빙 추천에 함께 반영되었습니다.');
                continue;
            }
            $context = $this->context($evidence, $identity['import_type']);
            if ((float) $context['total_amount'] <= 0) {
                $results[] = $this->result(
                    $identity,
                    'ALLOWED',
                    'NONE',
                    'RECOMMENDATION_AMOUNT_MISSING',
                    '추천에 필요한 입출금 금액이 없습니다.'
                );
                continue;
            }
            $recommendation = $this->candidateEngine->topCandidates($context, 3);
            $recommendation['candidates'] = $this->attachSourceIdentity(
                $this->attachReferences($recommendation['candidates'] ?? [], $this->referenceValues([$entry])),
                $identity,
                $evidence,
                $context + ['accounting_date' => $accountingDate]
            );
            $candidate = $recommendation['candidates'][0] ?? null;
            if (!$candidate) {
                [$reasonCode, $message] = $this->unavailableReason($evidence, $identity['import_type']);
                $results[] = array_replace($this->result($identity, 'ALLOWED', 'NONE', $reasonCode, $message), [
                    'summary' => (string) $context['summary'],
                    'amount' => (float) $context['total_amount'],
                    'effective_expense_category' => (string) ($evidence['effective_expense_category'] ?? $evidence['raw_expense_category'] ?? ''),
                ]);
                continue;
            }
            $results[] = $identity + [
                'connection_status' => 'ALLOWED',
                'recommendation_status' => 'FULL',
                'reason_code' => 'RECOMMENDATION_CREATED',
                'status' => 'RECOMMENDED',
                'message' => '분개 추천을 생성했습니다.',
                'candidate' => $candidate,
                'candidates' => array_slice($recommendation['candidates'] ?? [], 0, 3),
                'candidate_count' => (int) ($recommendation['candidate_count'] ?? 0),
                'summary' => (string) $context['summary'],
                'amount' => (float) $context['total_amount'],
                'effective_expense_category' => (string) ($evidence['effective_expense_category'] ?? $evidence['raw_expense_category'] ?? ''),
                'source_personal_expense_item_id' => $itemId,
                'source_date' => (string) ($context['base_date'] ?? ''),
                'client_id' => (string) ($evidence['client_id'] ?? ''),
                'client_name' => (string) ($evidence['client_name'] ?? ''),
            ];
        }
        return $results;
    }

    public function recommendationSets(array $results): array
    {
        if ($this->coverage($results)['status'] !== 'COMPLETE') return [];
        $candidateGroups = [];
        foreach ($results as $result) {
            if (($result['status'] ?? '') !== 'RECOMMENDED') continue;
            $candidates = is_array($result['candidates'] ?? null) ? $result['candidates'] : [];
            if ($candidates !== []) $candidateGroups[] = $candidates;
        }
        if ($candidateGroups === []) return [];

        $sets = [];
        for ($rank = 0; $rank < 3; $rank++) {
            $selected = [];
            foreach ($candidateGroups as $group) {
                $candidate = $group[$rank] ?? $group[0] ?? null;
                if (!$candidate || ($candidate['balanced'] ?? false) !== true) continue 2;
                $selected[] = $candidate;
            }
            $lines = $this->mergeLines(array_merge(...array_map(static fn(array $candidate): array => $candidate['lines'] ?? [], $selected)));
            $unresolvedLines = array_values(array_filter($lines, static fn(array $line): bool =>
                trim((string) ($line['account_id'] ?? '')) === ''
                || ((float) ($line['debit'] ?? 0) <= 0 && (float) ($line['credit'] ?? 0) <= 0)
            ));
            $debitTotal = array_sum(array_map(static fn(array $line): float => (float) ($line['debit'] ?? 0), $lines));
            $creditTotal = array_sum(array_map(static fn(array $line): float => (float) ($line['credit'] ?? 0), $lines));
            $differenceAmount = round($debitTotal - $creditTotal, 2);
            $isBalanced = $lines !== [] && abs($differenceAmount) < 0.01 && $debitTotal > 0;
            if ($unresolvedLines !== [] || !$isBalanced) continue;
            $accountKey = implode('|', array_map(
                static fn(array $line): string => implode(':', [
                    (string) ($line['line_type'] ?? ''),
                    (string) ($line['account_id'] ?? ''),
                    (string) ($line['debit'] ?? 0),
                    (string) ($line['credit'] ?? 0),
                ]),
                $lines
            ));
            $key = sha1($accountKey);
            if (isset($sets[$key])) continue;
            $sets[$key] = [
                'candidate_id' => $key,
                'rank' => count($sets) + 1,
                'score' => round(array_sum(array_column($selected, 'score')) / count($selected), 2),
                'confidence' => $selected[0]['confidence'] ?? 'LOW',
                'source_types' => array_values(array_unique(array_merge(...array_map(
                    static fn(array $candidate): array => $candidate['source_types'] ?? [],
                    $selected
                )))),
                'reasons' => array_values(array_unique(array_merge(...array_map(
                    static fn(array $candidate): array => $candidate['reasons'] ?? [],
                    $selected
                )))),
                'signals' => array_merge(...array_map(static fn(array $candidate): array => $candidate['signals'] ?? [], $selected)),
                'lines' => $lines,
                'unresolved_lines' => [],
                'debit_total' => round($debitTotal, 2),
                'credit_total' => round($creditTotal, 2),
                'difference_amount' => $differenceAmount,
                'is_balanced' => true,
                'recommendation_status' => 'COMPLETE',
                'is_applicable' => true,
            ];
        }
        return array_values($sets);
    }

    public function coverage(array $results): array
    {
        $requestAmount = 0.0;
        $matchedCount = 0;
        $matchedAmount = 0.0;
        foreach ($results as $result) {
            $amount = (float) ($result['amount'] ?? 0);
            $requestAmount += $amount;
            if (($result['status'] ?? '') === 'RECOMMENDED') {
                $matchedCount++;
                $matchedAmount += $amount;
            }
        }
        $requestCount = count($results);
        $unmatchedCount = $requestCount - $matchedCount;
        $unmatchedAmount = round($requestAmount - $matchedAmount, 2);
        $identityCoverage = $this->identityCoverage($results);
        return [
            'request_count' => $requestCount,
            'request_amount' => round($requestAmount, 2),
            'matched_count' => $matchedCount,
            'matched_amount' => round($matchedAmount, 2),
            'unmatched_count' => $unmatchedCount,
            'unmatched_amount' => $unmatchedAmount,
            'identity_request_count' => $identityCoverage['request_count'],
            'identity_covered_count' => $identityCoverage['covered_count'],
            'identity_missing_count' => $identityCoverage['missing_count'],
            'identity_duplicate_count' => $identityCoverage['duplicate_count'],
            'identity_status' => $identityCoverage['status'],
            'sub_account_covered_count' => $identityCoverage['sub_account_covered_count'],
            'sub_account_missing_count' => $identityCoverage['sub_account_missing_count'],
            'sub_account_status' => $identityCoverage['sub_account_status'],
            'status' => $requestCount > 0 && $unmatchedCount === 0 && abs($unmatchedAmount) < 0.01
                && $identityCoverage['status'] === 'COMPLETE'
                && $identityCoverage['sub_account_status'] === 'COMPLETE' ? 'COMPLETE' : 'INCOMPLETE',
        ];
    }

    private function mergeLines(array $lines): array
    {
        $merged = [];
        foreach ($lines as $line) {
            $side = (float) ($line['debit'] ?? 0) > 0 ? 'DEBIT' : 'CREDIT';
            $refs = is_array($line['refs'] ?? null) ? $line['refs'] : [];
            $sourceRefs = is_array($line['source_refs'] ?? null) ? $line['source_refs'] : [];
            $personalExpenseDebit = $side === 'DEBIT' && $this->personalExpenseSourceRefs($sourceRefs);
            $identityKey = $personalExpenseDebit ? ':' . $this->sourceIdentityKey($sourceRefs[0] ?? []) : '';
            $key = $side . ':' . (string) ($line['account_id'] ?? '') . ':'
                . json_encode($refs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ':'
                . (string) ($line['company_id'] ?? '') . ':' . (string) ($line['currency_code'] ?? '') . ':'
                . (string) ($line['accounting_date'] ?? '') . ':' . (string) ($line['aggregation_policy_code'] ?? '')
                . $identityKey;
            if (!isset($merged[$key])) {
                $merged[$key] = $line;
                $merged[$key]['debit'] = 0.0;
                $merged[$key]['credit'] = 0.0;
                $merged[$key]['source_refs'] = [];
            }
            $merged[$key]['debit'] += (float) ($line['debit'] ?? 0);
            $merged[$key]['credit'] += (float) ($line['credit'] ?? 0);
            foreach ($sourceRefs as $sourceRef) {
                $sourceKey = $this->sourceIdentityKey($sourceRef) . '|' . (string) ($sourceRef['debit_credit'] ?? '');
                $merged[$key]['source_refs'][$sourceKey] = $sourceRef;
            }
        }
        foreach ($merged as &$line) {
            $line['source_refs'] = array_values($line['source_refs'] ?? []);
            if ((float) ($line['credit'] ?? 0) > 0 && $this->personalExpenseSourceRefs($line['source_refs'])) {
                $line['summary'] = $this->personalExpenseCreditSummary($line['source_refs']);
            }
        }
        unset($line);
        return array_values($merged);
    }

    private function attachSourceIdentity(array $candidates, array $identity, array $evidence, array $context): array
    {
        $itemId = trim((string) ($evidence['source_personal_expense_item_id'] ?? ''));
        if (strtoupper((string) ($context['source_type'] ?? '')) !== 'PERSONAL_EXPENSE_ITEM' || $itemId === '') return $candidates;
        foreach ($candidates as &$candidate) {
            if (!is_array($candidate['lines'] ?? null)) {
                continue;
            }
            foreach ($candidate['lines'] as &$line) {
                $side = (float) ($line['debit'] ?? 0) > 0 ? 'DEBIT' : 'CREDIT';
                $amount = abs((float) ($line[strtolower($side)] ?? 0));
                $line['source_type'] = 'PERSONAL_EXPENSE_ITEM';
                $line['source_line_type'] = 'ITEM';
                $line['source_line_key'] = $itemId;
                $line['evidence_id'] = (string) $identity['evidence_id'];
                $line['evidence_type'] = (string) $identity['import_type'];
                $line['source_date'] = (string) ($context['base_date'] ?? '');
                $line['expense_category'] = (string) ($evidence['effective_expense_category'] ?? $evidence['raw_expense_category'] ?? '');
                $line['client_id'] = (string) ($evidence['client_id'] ?? '');
                $line['client_name'] = (string) ($evidence['client_name'] ?? '');
                $line['company_id'] = $this->companyId();
                $line['currency_code'] = (string) ($evidence['currency_code'] ?? 'KRW');
                $line['accounting_date'] = (string) ($context['accounting_date'] ?? '');
                $line['aggregation_policy_code'] = $side === 'CREDIT' ? 'EMPLOYEE_ACCRUED_EXPENSE_V1' : 'ITEM_IDENTITY_V1';
                $line['source_refs'] = [[
                    'evidence_type' => (string) $identity['import_type'],
                    'evidence_id' => (string) $identity['evidence_id'],
                    'source_type' => 'PERSONAL_EXPENSE_ITEM',
                    'source_line_key' => $itemId,
                    'source_amount' => (float) ($context['total_amount'] ?? 0),
                    'allocated_amount' => $amount,
                    'accounting_role_code' => (string) ($line['accounting_role_code'] ?? ($side === 'DEBIT' ? 'EXPENSE' : 'EMPLOYEE_ACCRUED_EXPENSE')),
                    'debit_credit' => $side,
                    'journal_rule_id' => (string) ($line['journal_rule_id'] ?? ''),
                    'journal_rule_revision_no' => (int) ($line['journal_rule_revision_no'] ?? 0),
                    'recommendation_source_code' => 'JOURNAL_RULE',
                    'planner_code' => 'PERSONAL_EXPENSE_ITEM_V1',
                    'planner_snapshot' => [
                        'source_date' => (string) ($context['base_date'] ?? ''),
                        'summary' => (string) ($context['summary'] ?? ''),
                        'expense_category' => (string) ($evidence['effective_expense_category'] ?? $evidence['raw_expense_category'] ?? ''),
                        'client_id' => (string) ($evidence['client_id'] ?? ''),
                        'transaction_id' => (string) ($evidence['transaction_id'] ?? ''),
                    ],
                ]];
            }
            unset($line);
        }
        unset($candidate);
        return $candidates;
    }

    private function identityCoverage(array $results): array
    {
        $seen = [];
        $covered = [];
        $subAccountCovered = [];
        foreach ($results as $result) {
            $requestKey = strtoupper((string) ($result['import_type'] ?? '')) . ':' . (string) ($result['evidence_id'] ?? '');
            $seen[$requestKey] = true;
            if (($result['status'] ?? '') !== 'RECOMMENDED') continue;
            $candidate = is_array($result['candidate'] ?? null)
                ? $result['candidate']
                : (is_array($result['candidates'][0] ?? null) ? $result['candidates'][0] : []);
            $sides = [];
            $subAccounts = [];
            foreach ($candidate['lines'] ?? [] as $line) {
                $lineSide = (float) ($line['debit'] ?? 0) > 0 ? 'DEBIT' : 'CREDIT';
                foreach ($line['refs'] ?? [] as $ref) {
                    $subAccounts[$lineSide][strtoupper((string) ($ref['ref_target'] ?? ''))] = true;
                }
                foreach ($line['source_refs'] ?? [] as $sourceRef) {
                    $key = strtoupper((string) ($sourceRef['evidence_type'] ?? '')) . ':' . (string) ($sourceRef['evidence_id'] ?? '');
                    if ($key !== $requestKey) continue;
                    $side = strtoupper((string) ($sourceRef['debit_credit'] ?? ''));
                    $sides[$side] = ($sides[$side] ?? 0) + 1;
                }
            }
            if (($sides['DEBIT'] ?? 0) === 1 && ($sides['CREDIT'] ?? 0) === 1) $covered[$requestKey] = true;
            if (isset($subAccounts['DEBIT']['CLIENT'], $subAccounts['CREDIT']['EMPLOYEE'])) {
                $subAccountCovered[$requestKey] = true;
            }
        }
        $duplicateCount = max(0, count($results) - count($seen));
        $missingCount = count(array_diff_key($seen, $covered));
        $subAccountMissing = count(array_diff_key($seen, $subAccountCovered));
        return [
            'request_count' => count($seen),
            'covered_count' => count($covered),
            'missing_count' => $missingCount,
            'duplicate_count' => $duplicateCount,
            'status' => $seen !== [] && $missingCount === 0 && $duplicateCount === 0 ? 'COMPLETE' : 'INCOMPLETE',
            'sub_account_covered_count' => count($subAccountCovered),
            'sub_account_missing_count' => $subAccountMissing,
            'sub_account_status' => $seen !== [] && $subAccountMissing === 0 ? 'COMPLETE' : 'INCOMPLETE',
        ];
    }

    private function personalExpenseSourceRefs(array $sourceRefs): bool
    {
        return $sourceRefs !== [] && strtoupper((string) ($sourceRefs[0]['source_type'] ?? '')) === 'PERSONAL_EXPENSE_ITEM';
    }

    private function sourceIdentityKey(array $sourceRef): string
    {
        return implode(':', [(string)($sourceRef['evidence_type']??''),(string)($sourceRef['evidence_id']??''),(string)($sourceRef['source_type']??''),(string)($sourceRef['source_line_key']??'')]);
    }

    private function personalExpenseCreditSummary(array $sourceRefs): string
    {
        $date=(string)($sourceRefs[0]['planner_snapshot']['source_date']??'');
        $timestamp=strtotime($date);
        return $timestamp!==false ? date('Y년 m월', $timestamp).' 귀속 개인경비' : '개인경비 미지급비용';
    }

    private function result(
        array $identity,
        string $connectionStatus,
        string $recommendationStatus,
        string $reasonCode,
        string $message
    ): array {
        return $identity + [
            'connection_status' => $connectionStatus,
            'recommendation_status' => $recommendationStatus,
            'reason_code' => $reasonCode,
            'status' => $recommendationStatus === 'PAIRED' ? 'PAIRED' : 'UNAVAILABLE',
            'message' => $message,
            'candidate' => null,
            'candidates' => [],
            'candidate_count' => 0,
            'summary' => '',
            'amount' => 0.0,
            'effective_expense_category' => '',
        ];
    }

    private function unavailableReason(array $evidence, string $importType): array
    {
        if (strtoupper(trim($importType)) === 'BANK_TRANSACTION') {
            if (trim((string) ($evidence['bank_account_id'] ?? '')) === '') {
                return [
                    'BANK_ACCOUNT_NOT_MAPPED',
                    '은행계좌 매핑이 없어 자금계정을 추천할 수 없습니다.',
                ];
            }
            return [
                'BANK_COUNTER_ACCOUNT_REQUIRED',
                '은행계좌는 확인했지만 추천할 상대계정을 결정할 수 없습니다.',
            ];
        }

        if (strtoupper(trim($importType)) === 'EMPLOYEE_EXPENSE_PERSONAL') {
            if (trim((string) ($evidence['source_personal_expense_item_id'] ?? '')) === '') {
                return ['SOURCE_CONTEXT_MISSING', '개인경비 Source Context가 없어 분개규칙을 조회할 수 없습니다.'];
            }
            if (trim((string) ($evidence['raw_expense_category'] ?? '')) === '') {
                return ['ITEM_CODE_MISSING', '개인경비 비용분류가 없어 분개규칙을 조회할 수 없습니다.'];
            }
            return ['JOURNAL_RULE_NOT_MATCHED', '개인경비 Source 조건과 비용분류에 일치하는 분개규칙이 없습니다.'];
        }
        return ['JOURNAL_RULE_NOT_FOUND', '등록된 분개규칙이 없거나 현재 조건과 일치하지 않습니다.'];
    }

    private function referenceValues(array $entries): array
    {
        $fields = [
            'CLIENT' => ['id' => 'client_id', 'name' => 'client_name'],
            'PROJECT' => ['id' => 'project_id', 'name' => 'project_name'],
            'BANK_ACCOUNT' => ['id' => 'bank_account_id', 'name' => 'bank_account_name'],
            'CARD' => ['id' => 'card_id', 'name' => 'card_name'],
            'TEAM' => ['id' => 'team_id', 'name' => 'team_name'],
            'EMPLOYEE' => ['id' => 'employee_id', 'name' => 'employee_name'],
        ];
        $values = [];
        foreach ($fields as $refTarget => $field) {
            $ids = [];
            foreach ($entries as $entry) {
                $id = trim((string) (($entry['evidence'][$field['id']] ?? '')));
                if ($id !== '') {
                    $ids[$id] = trim((string) ($entry['evidence'][$field['name']] ?? ''));
                }
            }
            if (count($ids) === 1) {
                $id = (string) array_key_first($ids);
                $values[$refTarget] = ['id' => $id, 'name' => (string) $ids[$id]];
            }
        }
        return $values;
    }

    private function attachReferences(array $candidates, array $referenceValues): array
    {
        $accountIds = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate['lines'] ?? [] as $line) {
                $accountId = trim((string) ($line['account_id'] ?? ''));
                if ($accountId !== '') $accountIds[] = $accountId;
            }
        }
        $policies = $this->candidateRepository->accountReferencePolicies($accountIds);
        foreach ($candidates as &$candidate) {
            foreach ($candidate['lines'] as &$line) {
                $refs = [];
                foreach ($policies[(string) ($line['account_id'] ?? '')] ?? [] as $policy) {
                    $refTarget = $this->normalizeRefTarget((string) ($policy['ref_target'] ?? ''));
                    $roleCode = strtoupper(trim((string) ($line['accounting_role_code'] ?? '')));
                    if ($roleCode === 'EXPENSE' && $refTarget !== 'CLIENT') continue;
                    if ($roleCode === 'EMPLOYEE_ACCRUED_EXPENSE' && $refTarget !== 'EMPLOYEE') continue;
                    $refId = trim((string) ($referenceValues[$refTarget]['id'] ?? ''));
                    if ($refTarget !== '' && $refId !== '') {
                        $refs[] = [
                            'ref_target' => $refTarget,
                            'ref_id' => $refId,
                            'ref_name' => trim((string) ($referenceValues[$refTarget]['name'] ?? '')),
                        ];
                    }
                }
                $line['refs'] = $refs;
            }
            unset($line);
        }
        unset($candidate);
        return $candidates;
    }

    private function normalizeRefTarget(string $refTarget): string
    {
        return match (strtoupper(trim($refTarget))) {
            'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY', 'PARTNER' => 'CLIENT',
            'PROJECT', 'CONSTRUCTION' => 'PROJECT',
            'ACCOUNT', 'BANK', 'BANK_ACCOUNT' => 'BANK_ACCOUNT',
            'CARD' => 'CARD',
            'TEAM', 'WORK_TEAM' => 'TEAM',
            'EMPLOYEE', 'USER' => 'EMPLOYEE',
            default => '',
        };
    }

    private function pairedFundEvidenceKeys(array $entries): array
    {
        $paired = [];
        foreach ($entries as $dataEntry) {
            $data = $dataEntry['evidence'];
            if (!$data || strtoupper((string) ($data['evidence_type'] ?? '')) !== 'DATA') continue;
            foreach ($entries as $fundEntry) {
                $fund = $fundEntry['evidence'];
                if (!$fund || strtoupper((string) ($fund['evidence_type'] ?? '')) !== 'FUND') continue;
                if (!$this->sameAccountingEvent($dataEntry, $fundEntry)) continue;
                $identity = $fundEntry['identity'];
                $paired[$identity['import_type'] . ':' . $identity['evidence_id']] = true;
                break;
            }
        }
        return $paired;
    }

    private function sameAccountingEvent(array $dataEntry, array $fundEntry): bool
    {
        $dataIdentity = $dataEntry['identity'];
        $fundIdentity = $fundEntry['identity'];
        $data = $dataEntry['evidence'];
        $fund = $fundEntry['evidence'];
        $dataSemantics = $this->evidenceRepository->semanticValues($dataIdentity['import_type'], $data);
        $fundSemantics = $this->evidenceRepository->semanticValues($fundIdentity['import_type'], $fund);
        $dataContext = $this->context($data, $dataIdentity['import_type']);
        $fundContext = $this->context($fund, $fundIdentity['import_type']);
        if (abs((float) $dataContext['total_amount'] - (float) $fundContext['total_amount']) >= 0.01) return false;
        $dataDate = substr(trim((string) (($dataSemantics['BASE_DATE'][0] ?? $data['evidence_date'] ?? ''))), 0, 10);
        $fundDate = substr(trim((string) (($fundSemantics['BASE_DATE'][0] ?? $fund['evidence_date'] ?? ''))), 0, 10);
        if ($dataDate === '' || $dataDate !== $fundDate) return false;
        $dataClient = trim((string) ($data['client_name'] ?? $data['client_id'] ?? ''));
        $fundClient = trim((string) ($fund['client_name'] ?? $fund['client_id'] ?? ''));
        if ($dataClient === '' || $dataClient !== $fundClient) return false;
        $dataSummary = $this->normalizedSummary($dataSemantics['DESCRIPTION'][0] ?? $dataContext['summary']);
        $fundSummary = $this->normalizedSummary($fundSemantics['DESCRIPTION'][0] ?? $fundContext['summary']);
        return $dataSummary !== '' && $fundSummary !== ''
            && (str_contains($dataSummary, $fundSummary) || str_contains($fundSummary, $dataSummary));
    }

    private function normalizedSummary(mixed $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower(trim((string) $value), 'UTF-8')) ?: '';
    }

    private function normalizeIdentities(array $identities): array
    {
        $unique = [];
        foreach ($identities as $identity) {
            if (!is_array($identity)) continue;
            $importType = strtoupper(trim((string) ($identity['import_type'] ?? '')));
            $evidenceId = trim((string) ($identity['evidence_id'] ?? ''));
            if ($importType !== '' && $evidenceId !== '') $unique[$importType . ':' . $evidenceId] = ['import_type' => $importType, 'evidence_id' => $evidenceId];
        }
        return array_values($unique);
    }

    private function context(array $row, string $importType): array
    {
        $semantics = $this->evidenceRepository->semanticValues($importType, $row);
        $deposit = $this->firstSemanticAmount($semantics, 'IN_AMOUNT');
        $withdraw = $this->firstSemanticAmount($semantics, 'OUT_AMOUNT');
        $total = $deposit > 0 ? $deposit : ($withdraw > 0 ? $withdraw : $this->firstSemanticAmount($semantics, 'POST_TAX_AMOUNT'));
        $preTax = $this->firstSemanticAmount($semantics, 'PRE_TAX_AMOUNT');
        $vat = $this->firstSemanticAmount($semantics, 'VAT_AMOUNT');
        if ($vat <= 0 && $preTax > 0 && $total > $preTax) {
            $vat = $total - $preTax;
        }
        $direction = $deposit > 0 ? 'IN' : ($withdraw > 0 ? 'OUT' : 'PURCHASE');
        $personalExpense = strtoupper(trim($importType)) === 'EMPLOYEE_EXPENSE_PERSONAL';
        return [
            'company_id' => $this->companyId(),
            'business_unit' => $personalExpense ? 'CONSTRUCTION' : (trim((string) ($row['business_unit'] ?? '')) ?: 'HQ'),
            'operation_type' => $personalExpense ? 'PERSONAL_EXPENSE' : (trim((string) ($row['operation_type'] ?? '')) ?: 'GENERAL'),
            'transaction_direction' => $personalExpense ? 'OUT' : (trim((string) ($row['transaction_direction'] ?? '')) ?: $direction),
            'import_type' => $importType,
            'source_type' => $personalExpense ? 'PERSONAL_EXPENSE_ITEM' : '',
            'source_line_type' => $personalExpense ? 'ITEM' : '',
            'item_code' => $personalExpense ? trim((string) ($row['raw_expense_category'] ?? '')) : '',
            'base_date' => $personalExpense ? substr(trim((string) ($row['raw_expense_date'] ?? '')), 0, 10) : date('Y-m-d'),
            'client_type' => $row['client_type'] ?? '',
            'client_id' => $row['client_id'] ?? '',
            'project_id' => $row['project_id'] ?? '',
            'total_amount' => $total,
            'vat_amount' => $vat,
            'summary' => trim((string) ($semantics['DESCRIPTION'][0] ?? $row['display_summary'] ?? $row['description'] ?? '')),
        ];
    }

    private function companyId(): string
    {
        if ($this->companyId === null) {
            $ids = $this->candidateRepository->companyIds();
            if (count($ids) !== 1) {
                throw new \RuntimeException('분개추천 회사 범위를 확정할 수 없습니다.');
            }
            $this->companyId = $ids[0];
        }
        return $this->companyId;
    }

    private function firstSemanticAmount(array $semantics, string $key): float
    {
        foreach ($semantics[$key] ?? [] as $value) {
            $amount = $this->number($value);
            if ($amount > 0) return $amount;
        }
        return 0.0;
    }

    private function number(mixed $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', (string) ($value ?? ''));
        return $normalized !== '' && is_numeric($normalized) ? abs((float) $normalized) : 0.0;
    }
}
