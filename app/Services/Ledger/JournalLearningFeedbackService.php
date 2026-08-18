<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use App\Repositories\Ledger\JournalLearningFeedbackRepository;
use PDO;

class JournalLearningFeedbackService
{
    private JournalLearningFeedbackRepository $repository;
    private VoucherModel $voucherModel;
    private VoucherLineModel $lineModel;
    private VoucherLineRefModel $lineRefModel;
    private EvidenceLinkModel $linkModel;
    private EvidenceSourceRepository $evidenceRepository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new JournalLearningFeedbackRepository($pdo);
        $this->voucherModel = new VoucherModel($pdo);
        $this->lineModel = new VoucherLineModel($pdo);
        $this->lineRefModel = new VoucherLineRefModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->evidenceRepository = new EvidenceSourceRepository($pdo);
    }

    public function recordPostedEvents(string $voucherId, string $actor): array
    {
        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher || VoucherStatus::normalize($voucher['status'] ?? null, '') !== VoucherStatus::POSTED || (int) ($voucher['is_reversal'] ?? 0) === 1) {
            return ['created_ids' => [], 'event_ids' => []];
        }

        $context = $this->canonicalContext($voucherId);
        $createdIds = [];
        $eventIds = [];
        foreach ($this->lineModel->getByVoucherId($voucherId) as $line) {
            $lineType = (float) ($line['debit'] ?? 0) > 0 ? 'DEBIT' : 'CREDIT';
            $amount = $lineType === 'DEBIT' ? (float) $line['debit'] : (float) $line['credit'];
            $recommendedAccount = trim((string) ($line['recommended_account_id'] ?? '')) ?: null;
            $recommendedLineType = trim((string) ($line['recommended_line_type'] ?? '')) ?: null;
            $recommendedAmount = $line['recommended_amount'] !== null ? (float) $line['recommended_amount'] : null;
            $result = $this->repository->insertEvent([
                'event_type' => JournalLearningFeedbackRepository::EVENT_TYPE,
                'transaction_id' => null,
                'voucher_id' => $voucherId,
                'voucher_line_id' => (string) $line['id'],
                'client_id' => $context['client_id'],
                'project_id' => $context['project_id'],
                'business_unit' => $context['business_unit'],
                'transaction_direction' => $context['transaction_direction'],
                'operation_type' => $context['operation_type'],
                'import_type' => $context['import_type'],
                'client_type' => $context['client_type'],
                'line_no' => (int) $line['line_no'],
                'line_type' => $lineType,
                'recommended_line_type' => $recommendedLineType,
                'final_line_type' => $lineType,
                'recommended_account_id' => $recommendedAccount,
                'final_account_id' => (string) $line['account_id'],
                'recommended_amount' => $recommendedAmount,
                'final_amount' => $amount,
                'recommend_source' => $recommendedAccount === null ? 'MANUAL' : 'RECOMMENDATION',
                'recommend_confidence' => null,
                'journal_rule_id' => trim((string) ($line['journal_rule_id'] ?? '')) ?: null,
                'recommend_reason' => null,
                'is_user_modified' => (int) ($line['is_user_modified'] ?? 0),
                'failure_type' => null,
                'source_payload' => json_encode(['context_is_unambiguous' => $context['unambiguous']], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $actor,
            ]);
            $eventIds[] = $result['id'];
            if ($result['created']) $createdIds[] = $result['id'];
        }
        return ['created_ids' => $createdIds, 'event_ids' => $eventIds];
    }

    public function synchronizeProjections(): array
    {
        $eventsByVoucher = [];
        foreach ($this->repository->feedbackEvents() as $event) {
            $eventsByVoucher[(string) $event['voucher_id']][] = $event;
        }
        $recent = [];
        $clients = [];
        foreach ($eventsByVoucher as $events) {
            $context = $events[0];
            if (trim((string) ($context['transaction_direction'] ?? '')) === '' || trim((string) ($context['import_type'] ?? '')) === '') continue;
            $debits = array_values(array_filter($events, static fn(array $event): bool => $event['final_line_type'] === 'DEBIT'));
            $credits = array_values(array_filter($events, static fn(array $event): bool => $event['final_line_type'] === 'CREDIT'));
            usort($debits, static fn(array $a, array $b): int => (float) $b['final_amount'] <=> (float) $a['final_amount']);
            usort($credits, static fn(array $a, array $b): int => (float) $b['final_amount'] <=> (float) $a['final_amount']);
            if ($debits === [] || $credits === []) continue;
            $vat = null;
            $direction = (string) $context['transaction_direction'];
            if (in_array($direction, ['OUT', 'PURCHASE'], true) && count($debits) > 1) $vat = (string) end($debits)['final_account_id'];
            if ($direction === 'IN' && count($credits) > 1) $vat = (string) end($credits)['final_account_id'];
            $debit = (string) $debits[0]['final_account_id'];
            $credit = (string) $credits[0]['final_account_id'];
            $hash = sha1($debit . '|' . $credit . '|' . ($vat ?? ''));
            $recent[$hash] ??= ['pattern_hash' => $hash, 'client_id' => $this->nullable($context['client_id']), 'project_id' => $this->nullable($context['project_id']), 'transaction_direction' => $direction, 'debit_account_id' => $debit, 'credit_account_id' => $credit, 'vat_account_id' => $vat, 'usage_count' => 0, 'last_used_at' => $context['created_at']];
            $recent[$hash]['usage_count']++;
            $recent[$hash]['last_used_at'] = max($recent[$hash]['last_used_at'], $context['created_at']);

            $clientId = trim((string) ($context['client_id'] ?? ''));
            if ($clientId === '') continue;
            $learningSide = in_array($direction, ['OUT', 'PURCHASE'], true) ? 'DEBIT' : 'CREDIT';
            foreach ($events as $event) {
                if ($event['final_line_type'] !== $learningSide) continue;
                $key = $clientId . '|' . $direction . '|' . $learningSide . '|' . $event['final_account_id'];
                $clients[$key] ??= ['client_id' => $clientId, 'transaction_direction' => $direction, 'line_type' => $learningSide, 'account_id' => $event['final_account_id'], 'usage_count' => 0, 'last_used_at' => $event['created_at']];
                $clients[$key]['usage_count']++;
                $clients[$key]['last_used_at'] = max($clients[$key]['last_used_at'], $event['created_at']);
            }
        }
        foreach ($recent as $pattern) $this->repository->upsertRecent($pattern);
        foreach ($clients as $pattern) $this->repository->upsertClient($pattern);
        return ['recent_count' => count($recent), 'client_count' => count($clients)];
    }

    private function canonicalContext(string $voucherId): array
    {
        $links = $this->linkModel->getVoucherEvidences($voucherId);
        $rows = $this->evidenceRepository->findMany($links);
        $contexts = [];
        foreach ($links as $link) {
            $importType = strtoupper(trim((string) $link['import_type']));
            $row = $rows[$importType . "\0" . $link['evidence_id']] ?? null;
            if (!$row) continue;
            $semantics = $this->evidenceRepository->semanticValues($importType, $row);
            $in = $this->amount($semantics['IN_AMOUNT'][0] ?? 0);
            $out = $this->amount($semantics['OUT_AMOUNT'][0] ?? 0);
            $contexts[] = [
                'business_unit' => strtoupper(trim((string) ($row['business_unit'] ?? ''))) ?: 'HQ',
                'operation_type' => strtoupper(trim((string) ($row['operation_type'] ?? ''))) ?: 'GENERAL',
                'transaction_direction' => strtoupper(trim((string) ($row['transaction_direction'] ?? ''))) ?: ($in > 0 ? 'IN' : ($out > 0 ? 'OUT' : 'PURCHASE')),
                'import_type' => $importType,
                'client_type' => strtoupper(trim((string) ($row['client_type'] ?? ''))),
                'client_id' => trim((string) ($row['client_id'] ?? '')),
                'project_id' => trim((string) ($row['project_id'] ?? '')),
            ];
        }
        $context = $contexts[0] ?? ['business_unit' => null, 'operation_type' => null, 'transaction_direction' => null, 'import_type' => null, 'client_type' => null, 'client_id' => null, 'project_id' => null];
        $unambiguous = $contexts !== [] && count(array_unique(array_map('serialize', $contexts))) === 1;
        if (!$unambiguous) {
            foreach (array_keys($context) as $key) $context[$key] = null;
        } else {
            $lines = $this->lineModel->getByVoucherId($voucherId);
            $refs = $this->lineRefModel->getGroupedByVoucherLineIds(array_column($lines, 'id'));
            $common = ['CLIENT' => [], 'PROJECT' => []];
            foreach ($refs as $lineRefs) {
                foreach ($lineRefs as $ref) {
                    $target = strtoupper(trim((string) ($ref['ref_target'] ?? '')));
                    if (in_array($target, ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'], true)) $target = 'CLIENT';
                    $id = trim((string) ($ref['ref_id'] ?? ''));
                    if (isset($common[$target]) && $id !== '') $common[$target][$id] = true;
                }
            }
            if (trim((string) $context['client_id']) === '' && count($common['CLIENT']) === 1) $context['client_id'] = array_key_first($common['CLIENT']);
            if (trim((string) $context['project_id']) === '' && count($common['PROJECT']) === 1) $context['project_id'] = array_key_first($common['PROJECT']);
        }
        $context['unambiguous'] = $unambiguous;
        return $context;
    }

    private function amount(mixed $value): float
    {
        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $normalized !== '' && is_numeric($normalized) ? abs((float) $normalized) : 0.0;
    }

    private function nullable(mixed $value): ?string
    {
        return trim((string) $value) ?: null;
    }
}
