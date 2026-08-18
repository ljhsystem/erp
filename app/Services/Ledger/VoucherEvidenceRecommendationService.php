<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Repositories\Ledger\JournalCandidateRepository;
use Core\LoggerFactory;
use PDO;

class VoucherEvidenceRecommendationService
{
    private EvidenceSourceRepository $evidenceRepository;
    private JournalCandidateEngineService $candidateEngine;
    private JournalCandidateRepository $candidateRepository;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->evidenceRepository = new EvidenceSourceRepository($pdo);
        $this->candidateEngine = new JournalCandidateEngineService($pdo);
        $this->candidateRepository = new JournalCandidateRepository($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.VoucherEvidenceRecommendationService');
    }

    public function recommend(array $identities): array
    {
        try {
            return $this->recommendInternal($identities);
        } catch (\Throwable $e) {
            $this->logger->error('분개추천 후보 조회에 실패했습니다.', [
                'evidence_count' => count($identities),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('분개추천 조회 중 오류가 발생했습니다. 수동으로 분개를 입력해 주세요.');
        }
    }

    private function recommendInternal(array $identities): array
    {
        $entries = [];
        $normalizedIdentities = $this->normalizeIdentities($identities);
        $evidenceRows = $this->evidenceRepository->findMany($normalizedIdentities);
        foreach ($normalizedIdentities as $identity) {
            $key = $identity['import_type'] . "\0" . $identity['evidence_id'];
            $evidence = $evidenceRows[$key] ?? null;
            $entries[] = ['identity' => $identity, 'evidence' => $evidence];
        }
        $referenceValues = $this->referenceValues($entries);
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
            if (!in_array($evidenceStatus, ['COMPLETED', 'READY', 'VERIFY_ONLY'], true)) {
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
            $recommendation['candidates'] = $this->attachReferences($recommendation['candidates'] ?? [], $referenceValues);
            $candidate = $recommendation['candidates'][0] ?? null;
            if (!$candidate) {
                [$reasonCode, $message] = $this->unavailableReason($evidence, $identity['import_type']);
                $results[] = $this->result($identity, 'ALLOWED', 'NONE', $reasonCode, $message);
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
            ];
        }
        return $results;
    }

    public function recommendationSets(array $results): array
    {
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
                if (!$candidate) continue 2;
                $selected[] = $candidate;
            }
            $lines = array_merge(...array_map(static fn(array $candidate): array => $candidate['lines'] ?? [], $selected));
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
            ];
        }
        return array_values($sets);
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

        return [
            'JOURNAL_RULE_NOT_FOUND',
            '추천할 분개규칙이 없습니다.',
        ];
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
        return [
            'business_unit' => trim((string) ($row['business_unit'] ?? '')) ?: 'HQ',
            'operation_type' => trim((string) ($row['operation_type'] ?? '')) ?: 'GENERAL',
            'transaction_direction' => trim((string) ($row['transaction_direction'] ?? '')) ?: $direction,
            'import_type' => $importType,
            'client_type' => $row['client_type'] ?? '',
            'client_id' => $row['client_id'] ?? '',
            'project_id' => $row['project_id'] ?? '',
            'total_amount' => $total,
            'vat_amount' => $vat,
            'summary' => trim((string) ($semantics['DESCRIPTION'][0] ?? $row['display_summary'] ?? $row['description'] ?? '')),
        ];
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
