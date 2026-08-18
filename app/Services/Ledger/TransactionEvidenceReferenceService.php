<?php

namespace App\Services\Ledger;

use App\Models\System\CodeModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use PDO;

class TransactionEvidenceReferenceService
{
    private EvidenceSourceRepository $repository;
    private EvidenceTypePolicyService $typePolicyService;
    private array $settlementTypeOptions;

    public function __construct(PDO $pdo)
    {
        $this->repository = new EvidenceSourceRepository($pdo);
        $this->typePolicyService = new EvidenceTypePolicyService(null, $pdo);
        $this->settlementTypeOptions = (new CodeModel($pdo))->getOptionsByGroup('SETTLEMENT_TYPE');
    }

    public function search(string $keyword = ''): array
    {
        return array_values(array_map(
            fn(array $row): array => $this->project($row),
            $this->repository->search(trim($keyword), ['DATA', 'BOTH'])
        ));
    }

    public function searchPage(array $criteria = []): array
    {
        $result = $this->repository->pagedProjections([
            'evidence_types' => ['DATA', 'BOTH'],
            'keyword' => trim((string) ($criteria['keyword'] ?? '')),
            'unlinked_transaction_only' => true,
            'start' => max(0, (int) ($criteria['start'] ?? 0)),
            'length' => max(10, min(100, (int) ($criteria['length'] ?? 20))),
            'order_field' => (string) ($criteria['order_field'] ?? 'standard_date'),
            'order_direction' => (string) ($criteria['order_direction'] ?? 'desc'),
            'exclude_evidences' => is_array($criteria['exclude_evidences'] ?? null)
                ? $criteria['exclude_evidences']
                : [],
        ]);
        $items = array_map(
            fn(array $projection): array => $this->project(array_merge(
                $projection['body'] ?? [],
                $projection['identity'] ?? []
            )),
            $result['projections'] ?? []
        );
        return [
            'items' => array_values($items),
            'records_total' => (int) ($result['records_total'] ?? 0),
            'records_filtered' => (int) ($result['records_filtered'] ?? 0),
        ];
    }

    public function hydrateLinks(array $links): array
    {
        $rows = [];
        $sources = $this->repository->findMany($links);
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $importType = strtoupper(trim((string) ($link['import_type'] ?? '')));
            $evidenceId = trim((string) ($link['evidence_id'] ?? ''));
            $source = $sources[$importType . "\0" . $evidenceId] ?? null;
            if (!is_array($source)) {
                continue;
            }
            $rows[] = $this->project($source);
        }
        return $rows;
    }

    private function project(array $row): array
    {
        $importType = strtoupper(trim((string) ($row['import_type'] ?? $row['source_type'] ?? '')));
        $evidenceId = trim((string) ($row['evidence_id'] ?? $row['id'] ?? ''));
        $semantics = $this->repository->semanticValues($importType, $row);
        $entries = $this->repository->semanticEntries($importType, $row);
        $date = $this->firstText($semantics['BASE_DATE'] ?? [])
            ?: trim((string) ($row['standard_date'] ?? $row['evidence_date'] ?? ''));
        $summary = $this->firstText($semantics['DESCRIPTION'] ?? []) ?: '-';
        $preTaxAmount = $this->firstAmount($semantics['PRE_TAX_AMOUNT'] ?? []);
        $settlements = [];
        $adjustmentTotal = 0.0;
        foreach ($entries as $entry) {
            if (($entry['semantic_key'] ?? '') !== 'ADJUST_AMOUNT') {
                continue;
            }
            $amount = abs($this->numeric($entry['value'] ?? 0));
            if ($amount <= 0) {
                continue;
            }
            $direction = ($entry['adjustment_direction'] ?? '') === 'DEDUCT' ? 'MINUS' : 'PLUS';
            $adjustmentTotal += $direction === 'MINUS' ? -$amount : $amount;
            $physicalColumn = trim((string) ($entry['physical_column'] ?? ''));
            $settlementType = $this->resolveSettlementType($physicalColumn);
            if ($settlementType === null) {
                continue;
            }
            $settlements[] = [
                'settlement_type' => $settlementType['code'],
                'amount_sign' => $direction,
                'amount' => $amount,
                'description' => $summary === '-'
                    ? $settlementType['name'] . ' 정산'
                    : $summary,
                'evidence_identity' => ['import_type' => $importType, 'evidence_id' => $evidenceId],
            ];
        }
        $postTaxAmount = $this->firstAmount($semantics['POST_TAX_AMOUNT'] ?? []);
        $displayAmount = $postTaxAmount !== 0.0 ? $postTaxAmount : $preTaxAmount + $adjustmentTotal;
        $clientName = trim((string) ($row['client_name']
            ?? $row['raw_counterparty_name']
            ?? $row['raw_merchant_company_name']
            ?? $row['raw_supplier_company_name']
            ?? $row['raw_customer_company_name']
            ?? '')) ?: '-';

        return [
            'import_type' => $importType,
            'evidence_id' => $evidenceId,
            'evidence_type' => strtoupper(trim((string) ($row['evidence_type'] ?? 'DATA'))) ?: 'DATA',
            'evidence_status' => strtoupper(trim((string) ($row['evidence_status'] ?? $row['status'] ?? ''))),
            'evidence_date' => $date,
            'display_type' => $this->typePolicyService->importTypeLabel($importType),
            'client_name' => $clientName,
            'display_summary' => $summary,
            'display_amount' => $displayAmount,
            'business_recommendation' => [
                'client_id' => trim((string) ($row['client_id'] ?? '')),
                'client_name' => $clientName === '-' ? '' : $clientName,
                'project_id' => trim((string) ($row['project_id'] ?? '')),
                'project_name' => trim((string) ($row['project_name'] ?? '')),
                'bank_account_id' => trim((string) ($row['bank_account_id'] ?? '')),
                'bank_account_name' => trim((string) ($row['bank_account_name'] ?? $row['account_name'] ?? '')),
                'card_id' => trim((string) ($row['card_id'] ?? '')),
                'card_name' => trim((string) ($row['card_name'] ?? '')),
                'team_id' => trim((string) ($row['team_id'] ?? '')),
                'team_name' => trim((string) ($row['team_name'] ?? '')),
                'employee_id' => trim((string) ($row['employee_id'] ?? '')),
                'employee_name' => trim((string) ($row['employee_name'] ?? $row['user_name'] ?? '')),
            ],
            'overview_recommendation' => [
                'transaction_date' => $date,
                'business_unit' => trim((string) ($row['business_unit'] ?? '')),
                'transaction_direction' => trim((string) ($row['transaction_direction'] ?? '')),
                'operation_type' => trim((string) ($row['operation_type'] ?? '')),
                'description' => $summary === '-' ? '' : $summary,
                'supply_amount' => $preTaxAmount,
                'settlement_amount' => $adjustmentTotal,
                'final_amount' => $displayAmount,
            ],
            'transaction_item' => $preTaxAmount === 0.0 ? null : [
                'item_date' => $date,
                'item_name' => $summary === '-' ? '1식' : $summary,
                'unit_name' => '식',
                'quantity' => 1,
                'unit_price' => $preTaxAmount,
                'amount' => $preTaxAmount,
                'supply_amount' => $preTaxAmount,
                'description' => $summary === '-' ? '' : $summary,
                'evidence_identity' => ['import_type' => $importType, 'evidence_id' => $evidenceId],
            ],
            'transaction_settlements' => $settlements,
        ];
    }

    private function firstText(array $values): string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }
        return '';
    }

    private function firstAmount(array $values): float
    {
        foreach ($values as $value) {
            $number = $this->numeric($value);
            if ($number !== 0.0) {
                return $number;
            }
        }
        return 0.0;
    }

    private function numeric(mixed $value): float
    {
        $normalized = str_replace(',', '', trim((string) ($value ?? '')));
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function resolveSettlementType(string $physicalColumn): ?array
    {
        $column = strtolower(trim($physicalColumn));
        $preferences = match (true) {
            str_contains($column, 'business_income_tax') => ['WITHHOLDING_BUSINESS', '사업소득세'],
            str_contains($column, 'local_income_tax') => ['LOCAL_INCOME_TAX', '지방소득세'],
            str_contains($column, 'national_pension') => ['NATIONAL_PENSION', '국민연금'],
            str_contains($column, 'health_insurance') => ['HEALTH_INSURANCE', '건강보험'],
            str_contains($column, 'employment_insurance') => ['EMPLOYMENT_INSURANCE', '고용보험'],
            str_contains($column, 'income_tax'), str_contains($column, 'withholding_tax') => ['WITHHOLDING_INCOME', '근로소득세'],
            str_contains($column, 'service_fee'), str_contains($column, 'tip_amount') => ['SERVICE_FEE', '봉사료'],
            str_contains($column, 'vat') => ['VAT', '부가세'],
            default => [],
        };
        if ($preferences === []) {
            return null;
        }
        foreach ($preferences as $preference) {
            foreach ($this->settlementTypeOptions as $option) {
                $code = strtoupper(trim((string) ($option['code'] ?? '')));
                $name = trim((string) ($option['code_name'] ?? $option['code'] ?? ''));
                if ($code === strtoupper($preference) || $name === $preference) {
                    return ['code' => $code, 'name' => $name !== '' ? $name : $code];
                }
            }
        }
        return null;
    }
}
