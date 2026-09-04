<?php

namespace App\Services\Ledger;

use App\Models\Ledger\VoucherLineSourceRefModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class VoucherLineSourceRefService
{
    private VoucherLineSourceRefModel $model;
    private EvidenceSourceRepository $evidenceRepository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new VoucherLineSourceRefModel($pdo);
        $this->evidenceRepository = new EvidenceSourceRepository($pdo);
    }

    public function deleteForVoucher(string $companyId, string $voucherId): void
    {
        $this->model->deleteByVoucher($companyId, $voucherId);
    }

    public function hydrateVoucherLines(string $companyId, string $voucherId, array $lines): array
    {
        $grouped = $this->model->groupedByVoucherLine($companyId, $voucherId);
        foreach ($lines as &$line) {
            $line['source_refs'] = $grouped[(string) ($line['id'] ?? '')] ?? [];
        }
        unset($line);
        return $lines;
    }

    public function persistVoucherLines(string $companyId, string $voucherId, array $lines): void
    {
        foreach ($lines as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            $lineAmount = (float) ($line['debit'] ?? 0) > 0 ? (float) $line['debit'] : (float) ($line['credit'] ?? 0);
            $lineSide = (float) ($line['debit'] ?? 0) > 0 ? 'DEBIT' : 'CREDIT';
            $allocated = 0.0;
            $sequence = 1;
            foreach ((array) ($line['source_refs'] ?? []) as $input) {
                $evidenceType = strtoupper(trim((string) ($input['evidence_type'] ?? '')));
                $evidenceId = trim((string) ($input['evidence_id'] ?? ''));
                $evidence = $this->evidenceRepository->find($evidenceType, $evidenceId);
                if (!$evidence) {
                    throw new \InvalidArgumentException('Source Ref의 증빙원본을 찾을 수 없습니다.');
                }
                $sourceType = strtoupper(trim((string) ($input['source_type'] ?? '')));
                $sourceLineKey = trim((string) ($input['source_line_key'] ?? ''));
                if ($sourceType === 'PERSONAL_EXPENSE_ITEM'
                    && $sourceLineKey !== trim((string) ($evidence['source_personal_expense_item_id'] ?? ''))) {
                    throw new \InvalidArgumentException('Source Ref의 개인경비 Item identity가 증빙원본과 다릅니다.');
                }
                $sourceAmount = abs((float) ($evidence['raw_total_amount'] ?? $input['source_amount'] ?? 0));
                $allocatedAmount = abs((float) ($input['allocated_amount'] ?? 0));
                if ($sourceAmount <= 0 || $allocatedAmount > $sourceAmount + 0.009) {
                    throw new \InvalidArgumentException('Source Ref의 배분금액이 증빙원본 금액 범위를 벗어났습니다.');
                }
                $roleCode = strtoupper(trim((string) ($input['accounting_role_code'] ?? '')));
                $ruleId = trim((string) ($input['journal_rule_id'] ?? ''));
                $ruleRevision = (int) ($input['journal_rule_revision_no'] ?? 0);
                if ($ruleId === '' || $ruleRevision < 1
                    || !$this->model->ruleRevisionExists($ruleId, $ruleRevision, $roleCode, $lineSide)) {
                    throw new \InvalidArgumentException('Source Ref의 분개규칙 Revision이 실제 규칙과 일치하지 않습니다.');
                }
                $input['company_id'] = $companyId;
                $input['voucher_id'] = $voucherId;
                $input['voucher_line_id'] = $lineId;
                $input['debit_credit'] = $lineSide;
                $input['reference_action_code'] = 'ORIGINAL';
                $input['allocation_sequence'] = $sequence++;
                $input['source_amount'] = $sourceAmount;
                $input['planner_snapshot'] = [
                    'source_date' => (string) ($evidence['raw_expense_date'] ?? $evidence['evidence_date'] ?? ''),
                    'summary' => (string) ($evidence['raw_item_name'] ?? $evidence['display_summary'] ?? ''),
                    'expense_category' => (string) ($evidence['raw_expense_category'] ?? ''),
                    'client_id' => (string) ($evidence['client_id'] ?? ''),
                    'transaction_id' => (string) ($evidence['transaction_id'] ?? ''),
                ];
                $allocated += $allocatedAmount;
                $this->create($input);
            }
            if ((array) ($line['source_refs'] ?? []) !== [] && abs($allocated - $lineAmount) > 0.009) {
                throw new \InvalidArgumentException('전표 Line 금액과 Source Ref 배분합계가 일치하지 않습니다.');
            }
        }
    }

    public function createReversals(string $companyId, string $originalVoucherId, string $newVoucherId, array $lineMap): void
    {
        $grouped = $this->model->groupedByVoucherLine($companyId, $originalVoucherId);
        foreach ($lineMap as $originalLineId => $newLine) {
            $sequence = 1;
            foreach ($grouped[(string) $originalLineId] ?? [] as $original) {
                $this->create([
                    'company_id' => $companyId,
                    'voucher_id' => $newVoucherId,
                    'voucher_line_id' => (string) $newLine['id'],
                    'evidence_type' => (string) $original['evidence_type'],
                    'evidence_id' => (string) $original['evidence_id'],
                    'source_type' => (string) $original['source_type'],
                    'source_line_key' => (string) $original['source_line_key'],
                    'accounting_role_code' => (string) $original['accounting_role_code'],
                    'debit_credit' => (string) $newLine['side'],
                    'reference_action_code' => 'REVERSAL',
                    'allocation_sequence' => $sequence++,
                    'source_amount' => (float) $original['source_amount'],
                    'allocated_amount' => (float) $original['allocated_amount'],
                    'journal_rule_id' => $original['journal_rule_id'],
                    'journal_rule_revision_no' => $original['journal_rule_revision_no'],
                    'recommendation_source_code' => (string) $original['recommendation_source_code'],
                    'original_source_ref_id' => (string) $original['id'],
                    'planner_code' => (string) $original['planner_code'],
                    'planner_snapshot' => json_decode((string) ($original['planner_snapshot'] ?? ''), true) ?: null,
                ]);
            }
        }
    }

    public function create(array $input): array
    {
        $row = $this->normalize($input);
        $row['id'] = UuidHelper::generate();
        $row['source_ref_key'] = hash('sha256', implode('|', [
            $row['company_id'], $row['voucher_id'], $row['voucher_line_id'], $row['evidence_type'], $row['evidence_id'],
            $row['source_type'], $row['source_line_key'], $row['accounting_role_code'], $row['debit_credit'],
            $row['reference_action_code'], (string) $row['allocation_sequence'],
        ]));
        $row['created_by'] = ActorHelper::user();
        $this->model->insert($row);
        return $row;
    }

    public function validateVoucher(string $companyId, string $voucherId, array $voucherLines): array
    {
        $refs = $this->model->byVoucher($companyId, $voucherId);
        $trackedLineIds = array_fill_keys(array_map(static fn(array $ref): string => (string) $ref['voucher_line_id'], $refs), true);
        $lineExpected = [];
        foreach ($voucherLines as $line) {
            if (!isset($trackedLineIds[(string) ($line['id'] ?? '')])) {
                continue;
            }
            $side = (float) ($line['debit'] ?? 0) > 0 ? 'DEBIT' : 'CREDIT';
            $lineExpected[(string) $line['id'] . '|' . $side] = abs((float) ($line[strtolower($side)] ?? 0));
        }
        $lineAllocated = [];
        $sourceOriginal = [];
        foreach ($refs as $ref) {
            $lineKey = (string) $ref['voucher_line_id'] . '|' . (string) $ref['debit_credit'];
            $lineAllocated[$lineKey] = ($lineAllocated[$lineKey] ?? 0.0) + (float) $ref['allocated_amount'];
            if ($ref['reference_action_code'] === 'ORIGINAL') {
                $sourceKey = implode('|', [$ref['evidence_type'], $ref['evidence_id'], $ref['source_type'], $ref['source_line_key']]);
                $sourceOriginal[$sourceKey][$ref['debit_credit']] = ($sourceOriginal[$sourceKey][$ref['debit_credit']] ?? 0.0) + (float) $ref['allocated_amount'];
            } elseif ($ref['reference_action_code'] === 'REVERSAL') {
                $original = $this->model->sourceRef($companyId, (string) $ref['original_source_ref_id']);
                if ($original === null || (float) $original['allocated_amount'] !== (float) $ref['allocated_amount']
                    || $original['debit_credit'] === $ref['debit_credit']) {
                    throw new \RuntimeException('역분개 Source Ref가 원 ORIGINAL과 반대 방향·동일 금액이 아닙니다.');
                }
            }
        }
        foreach ($lineExpected as $key => $amount) {
            if (abs($amount - ($lineAllocated[$key] ?? 0.0)) > 0.009) {
                throw new \RuntimeException('전표 Line 금액과 Source Ref 배분합계가 일치하지 않습니다.');
            }
        }
        foreach ($sourceOriginal as $amounts) {
            if (abs(($amounts['DEBIT'] ?? 0.0) - ($amounts['CREDIT'] ?? 0.0)) > 0.009) {
                throw new \RuntimeException('원천 Line별 ORIGINAL 차변·대변 배분합계가 일치하지 않습니다.');
            }
        }
        return ['line_count' => count($lineExpected), 'source_count' => count($sourceOriginal), 'ref_count' => count($refs)];
    }

    private function normalize(array $input): array
    {
        $required = ['company_id','voucher_id','voucher_line_id','evidence_type','evidence_id','source_type','source_line_key','accounting_role_code','debit_credit','reference_action_code','planner_code','recommendation_source_code'];
        foreach ($required as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                throw new \InvalidArgumentException("{$field} 값이 필요합니다.");
            }
        }
        $sourceAmount = abs((float) ($input['source_amount'] ?? 0));
        $allocatedAmount = abs((float) ($input['allocated_amount'] ?? 0));
        if ($allocatedAmount <= 0) {
            throw new \InvalidArgumentException('배분금액은 0보다 커야 합니다.');
        }
        $ruleId = trim((string) ($input['journal_rule_id'] ?? '')) ?: null;
        $ruleRevision = isset($input['journal_rule_revision_no']) ? (int) $input['journal_rule_revision_no'] : null;
        if (($ruleId === null) !== ($ruleRevision === null)) {
            throw new \InvalidArgumentException('분개규칙 ID와 Revision 번호는 함께 지정해야 합니다.');
        }
        return [
            'company_id' => trim((string) $input['company_id']), 'voucher_id' => trim((string) $input['voucher_id']),
            'voucher_line_id' => trim((string) $input['voucher_line_id']), 'evidence_type' => strtoupper(trim((string) $input['evidence_type'])),
            'evidence_id' => trim((string) $input['evidence_id']), 'source_type' => strtoupper(trim((string) $input['source_type'])),
            'source_line_key' => trim((string) $input['source_line_key']), 'accounting_role_code' => strtoupper(trim((string) $input['accounting_role_code'])),
            'debit_credit' => strtoupper(trim((string) $input['debit_credit'])), 'reference_action_code' => strtoupper(trim((string) $input['reference_action_code'])),
            'allocation_sequence' => max(1, (int) ($input['allocation_sequence'] ?? 1)), 'source_amount' => $sourceAmount,
            'allocated_amount' => $allocatedAmount, 'journal_rule_id' => $ruleId, 'journal_rule_revision_no' => $ruleRevision,
            'recommendation_source_code' => strtoupper(trim((string) $input['recommendation_source_code'])),
            'original_source_ref_id' => trim((string) ($input['original_source_ref_id'] ?? '')) ?: null,
            'planner_code' => strtoupper(trim((string) $input['planner_code'])),
            'planner_snapshot' => isset($input['planner_snapshot']) ? json_encode($input['planner_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
        ];
    }
}
