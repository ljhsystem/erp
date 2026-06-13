<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerBusinessInfoTrait;
use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceBusinessRefService;
use App\Services\Ledger\EvidencePayloadHelperService;
use App\Services\Ledger\EvidenceReferenceResolverService;
use App\Services\Ledger\EvidenceSortHelperService;
use App\Services\Ledger\EvidenceStatusService;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\VoucherPolicyService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use PDO;

class EvidenceStatusController
{
    use ImportControllerBusinessInfoTrait;
    use ImportControllerUtilityTrait;

    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_STATEMENT',
        'CARD_PURCHASE' => 'CARD_STATEMENT',
        'CARD_SALE' => 'CARD_STATEMENT',
        'CASH_RECEIPT_PURCHAS' => 'CASH_RECEIPT_PURCHASE',
        'CASH_RECEIPT_BUY' => 'CASH_RECEIPT_PURCHASE',
        'CASH_RECEIPT_SALE' => 'CASH_RECEIPT_SALES',
        'CASH_RECEIPT_SELL' => 'CASH_RECEIPT_SALES',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
    ];

    private PDO $pdo;
    private ?EvidenceStatusService $evidenceStatusService = null;
    private ?EvidencePayloadHelperService $evidencePayloadHelperService = null;
    private ?EvidenceTypePolicyService $evidenceTypePolicyService = null;
    private ?EvidenceSortHelperService $evidenceSortHelperService = null;
    private ?EvidenceBusinessRefService $evidenceBusinessRefService = null;
    private ?EvidenceReferenceResolverService $evidenceReferenceResolverService = null;
    private ?VoucherPolicyService $voucherPolicyService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiUpdateStatus(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->evidencePayloadHelperService()->seedRowIdsFromPayload($payload);
        $status = strtoupper(trim((string) ($payload['process_status'] ?? $payload['status'] ?? '')));
        $result = $this->evidenceStatusService()->updateStatus($ids, $status);

        $this->json(
            ['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? '')],
            (int) ($result['status'] ?? 200)
        );
    }

    public function apiReorder(): void
    {
        $payload = $this->requestPayload();
        $result = $this->evidenceStatusService()->reorder($payload, ActorHelper::user());

        $this->json(
            ['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? '')],
            (int) ($result['status'] ?? 200)
        );
    }

    private function evidenceStatusService(): EvidenceStatusService
    {
        if ($this->evidenceStatusService === null) {
            $this->evidenceStatusService = new EvidenceStatusService(
                $this->pdo,
                fn(array $ids, string $prefix): array => $this->placeholdersForIds($ids, $prefix),
                fn(array $payload): string => $this->evidencePayloadHelperService()->jsonEncodeForStorage($payload),
                fn(string $type): string => self::normalizeDataType($type),
                fn(string $type): array => $this->evidenceTypePolicyService()->queryDataTypes($type),
                fn(string $table): bool => $this->tableExists($table),
                fn(array $row, string $key): int => $this->evidenceSortHelperService()->evidencePayloadSortNo($row, $key)
            );
        }

        return $this->evidenceStatusService;
    }

    private function evidencePayloadHelperService(): EvidencePayloadHelperService
    {
        if ($this->evidencePayloadHelperService === null) {
            $this->evidencePayloadHelperService = new EvidencePayloadHelperService([
                'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                'dateValue' => fn(mixed $value): string => $this->dateValue($value),
                'isEmptySelectionLabel' => fn(string $value): bool => $this->evidenceBusinessRefService()->isEmptySelectionLabel($value),
                'normalizeDataType' => fn(string $type): string => self::normalizeDataType($type),
            ]);
        }

        return $this->evidencePayloadHelperService;
    }

    private function evidenceTypePolicyService(): EvidenceTypePolicyService
    {
        if ($this->evidenceTypePolicyService === null) {
            $this->evidenceTypePolicyService = new EvidenceTypePolicyService(
                fn(string $type): string => self::normalizeDataType($type),
                self::LEGACY_DATA_TYPE_MAP,
                $this->pdo,
                [
                    'amountOrNull' => fn(mixed $value): ?float => $this->amountOrNull($value),
                ]
            );
        }

        return $this->evidenceTypePolicyService;
    }

    private function evidenceSortHelperService(): EvidenceSortHelperService
    {
        if ($this->evidenceSortHelperService === null) {
            $this->evidenceSortHelperService = new EvidenceSortHelperService();
        }

        return $this->evidenceSortHelperService;
    }

    private function evidenceBusinessRefService(): EvidenceBusinessRefService
    {
        if ($this->evidenceBusinessRefService === null) {
            $this->evidenceBusinessRefService = new EvidenceBusinessRefService([
                'normalizeVoucherRefType' => fn(string $refType): string => $this->voucherPolicyService()->normalizeVoucherRefType($refType),
                'resolveBankAccountId' => fn(string $value): ?string => $this->evidenceReferenceResolverService()->resolveBankAccountId($value),
                'resolveVoucherRefId' => fn(string $refType, string $value): ?string => $this->evidenceReferenceResolverService()->resolveVoucherRefId($refType, $value),
                'businessRefNameById' => fn(string $refType, string $id): ?string => $this->evidenceReferenceResolverService()->businessRefNameById($refType, $id),
                'payloadScalarForStorage' => fn(mixed $value, bool $preferId = false): mixed => $this->evidencePayloadHelperService()->payloadScalarForStorage($value, $preferId),
                'clientNameFromImportParty' => fn(array $payload): string => trim((string) ($payload['client_name'] ?? $payload['client_company_name'] ?? '')),
                'isUuid' => fn(string $value): bool => $this->isUuid($value),
            ]);
        }

        return $this->evidenceBusinessRefService;
    }

    private function evidenceReferenceResolverService(): EvidenceReferenceResolverService
    {
        if ($this->evidenceReferenceResolverService === null) {
            $this->evidenceReferenceResolverService = new EvidenceReferenceResolverService(
                $this->pdo,
                fn(string $tableName): bool => $this->tableExists($tableName),
                fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                fn(string $value): bool => $this->isUuid($value),
                fn(string $value): string => $this->voucherPolicyService()->normalizeVoucherRefType($value),
                fn(mixed $value): string => self::normalizeBusinessNumber($value)
            );
        }

        return $this->evidenceReferenceResolverService;
    }

    private function voucherPolicyService(): VoucherPolicyService
    {
        if ($this->voucherPolicyService === null) {
            $this->voucherPolicyService = new VoucherPolicyService($this->pdo, [
                'tableExists' => fn(string $tableName): bool => $this->tableExists($tableName),
                'tableColumnExists' => fn(string $tableName, string $columnName): bool => $this->tableColumnExists($tableName, $columnName),
                'businessRefIdForStorage' => fn(string $refType, array $payload): ?string => $this->evidenceBusinessRefService()->businessRefIdForStorage($refType, $payload),
            ]);
        }

        return $this->voucherPolicyService;
    }
}
