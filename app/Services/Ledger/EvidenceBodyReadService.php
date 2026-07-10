<?php

namespace App\Services\Ledger;

use App\Models\Ledger\BankEvidenceReadModel;
use App\Models\Ledger\BusinessDataEvidenceReadModel;
use App\Models\Ledger\CardHometaxEvidenceReadModel;
use App\Models\Ledger\CardStatementEvidenceReadModel;
use App\Models\Ledger\CashReceiptEvidenceReadModel;
use App\Models\Ledger\ConstructionEvidenceReadModel;
use App\Models\Ledger\PayrollEvidenceReadModel;
use App\Models\Ledger\TaxInvoiceReadModel;
use Closure;
use PDO;

class EvidenceBodyReadService
{
    private ?BodyTableSchemaService $schemaService = null;
    private ?BankEvidenceReadModel $bankReadModel = null;
    private ?TaxInvoiceReadModel $taxInvoiceReadModel = null;
    private ?CashReceiptEvidenceReadModel $cashReceiptReadModel = null;
    private ?CardHometaxEvidenceReadModel $cardHometaxReadModel = null;
    private ?CardStatementEvidenceReadModel $cardStatementReadModel = null;
    private ?BusinessDataEvidenceReadModel $businessDataReadModel = null;
    private ?PayrollEvidenceReadModel $payrollReadModel = null;
    private ?ConstructionEvidenceReadModel $constructionReadModel = null;

    public function __construct(
        private PDO $pdo,
        private EvidenceProcessingPolicyService $evidenceProcessingPolicyService,
        private Closure $normalizeDataType
    ) {
    }

    public function readyBodyImportTypes(): array
    {
        return [
            'BANK_TRANSACTION',
            'TAX_INVOICE',
            'TAX_INVOICE_MANUAL',
            'CASH_RECEIPT',
            'CARD_HOMETAX',
            'CARD_STATEMENT',
        ];
    }

    public function rowsForTypes(array $types, string $status, string $requestedId): array
    {
        $resolvedTypes = $types !== [] ? $types : $this->readyBodyImportTypes();
        $rows = [];

        foreach ($resolvedTypes as $type) {
            $normalizedType = $this->normalizeDataType((string) $type);
            if ($normalizedType === '') {
                continue;
            }

            $rows = array_merge($rows, $this->rowsForType($normalizedType, $status, $requestedId));
        }

        return $rows;
    }

    public function countRowsForTypes(array $types, string $status, string $requestedId): int
    {
        $resolvedTypes = $types !== [] ? $types : $this->readyBodyImportTypes();
        $count = 0;

        foreach ($resolvedTypes as $type) {
            $normalizedType = $this->normalizeDataType((string) $type);
            if ($normalizedType === '') {
                continue;
            }

            $count += $this->countRowsForType($normalizedType, $status, $requestedId);
        }

        return $count;
    }

    public function bodyEvidenceTypeCounts(): array
    {
        return [
            'BANK_TRANSACTION' => $this->bankReadModel()->findCount(),
            'TAX_INVOICE' => $this->taxInvoiceReadModel()->findCount('TAX_INVOICE'),
            'TAX_INVOICE_MANUAL' => $this->taxInvoiceReadModel()->findCount('TAX_INVOICE_MANUAL'),
            'CASH_RECEIPT' => $this->cashReceiptReadModel()->findCount(),
            'CARD_HOMETAX' => $this->cardHometaxReadModel()->findCount(),
            'CARD_STATEMENT' => $this->cardStatementReadModel()->findCount('CARD_STATEMENT'),
        ];
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }

    private function rowsForType(string $normalizedType, string $status, string $requestedId): array
    {
        return match ($normalizedType) {
            'BANK_TRANSACTION' => $this->bankReadModel()->findList($status, $requestedId),
            'TAX_INVOICE', 'TAX_INVOICE_MANUAL' => $this->taxInvoiceReadModel()->findList($normalizedType, $status, $requestedId),
            'CASH_RECEIPT' => $this->cashReceiptReadModel()->findList($status, $requestedId),
            'CARD_HOMETAX' => $this->cardHometaxReadModel()->findList($status, $requestedId),
            'CARD_STATEMENT', 'CARD_APPROVAL' => $this->cardStatementReadModel()->findList($normalizedType, $status, $requestedId),
            'SHOPPING_ORDER', 'IMPORT_INVOICE', 'BUSINESS_DATA', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE'
                => $this->businessDataReadModel()->findList($normalizedType, $status, $requestedId),
            'PAYROLL', 'PAYROLL_WITHHOLDING' => $this->payrollReadModel()->findList($normalizedType, $status, $requestedId),
            'CONSTRUCTION' => $this->constructionReadModel()->findList($status, $requestedId),
            default => [],
        };
    }

    private function countRowsForType(string $normalizedType, string $status, string $requestedId): int
    {
        return match ($normalizedType) {
            'BANK_TRANSACTION' => $this->bankReadModel()->findCount($status, $requestedId),
            'TAX_INVOICE', 'TAX_INVOICE_MANUAL' => $this->taxInvoiceReadModel()->findCount($normalizedType, $status, $requestedId),
            'CASH_RECEIPT' => $this->cashReceiptReadModel()->findCount($status, $requestedId),
            'CARD_HOMETAX' => $this->cardHometaxReadModel()->findCount($status, $requestedId),
            'CARD_STATEMENT', 'CARD_APPROVAL' => $this->cardStatementReadModel()->findCount($normalizedType, $status, $requestedId),
            'SHOPPING_ORDER', 'IMPORT_INVOICE', 'BUSINESS_DATA', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE'
                => $this->businessDataReadModel()->findCount($normalizedType, $status, $requestedId),
            'PAYROLL', 'PAYROLL_WITHHOLDING' => $this->payrollReadModel()->findCount($normalizedType, $status, $requestedId),
            'CONSTRUCTION' => $this->constructionReadModel()->findCount($status, $requestedId),
            default => 0,
        };
    }

    private function schemaService(): BodyTableSchemaService
    {
        if ($this->schemaService === null) {
            $this->schemaService = new BodyTableSchemaService($this->pdo);
        }

        return $this->schemaService;
    }

    private function bankReadModel(): BankEvidenceReadModel
    {
        if ($this->bankReadModel === null) {
            $this->bankReadModel = new BankEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->bankReadModel;
    }

    private function taxInvoiceReadModel(): TaxInvoiceReadModel
    {
        if ($this->taxInvoiceReadModel === null) {
            $this->taxInvoiceReadModel = new TaxInvoiceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->taxInvoiceReadModel;
    }

    private function cashReceiptReadModel(): CashReceiptEvidenceReadModel
    {
        if ($this->cashReceiptReadModel === null) {
            $this->cashReceiptReadModel = new CashReceiptEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->cashReceiptReadModel;
    }

    private function cardStatementReadModel(): CardStatementEvidenceReadModel
    {
        if ($this->cardStatementReadModel === null) {
            $this->cardStatementReadModel = new CardStatementEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->cardStatementReadModel;
    }

    private function cardHometaxReadModel(): CardHometaxEvidenceReadModel
    {
        if ($this->cardHometaxReadModel === null) {
            $this->cardHometaxReadModel = new CardHometaxEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->cardHometaxReadModel;
    }

    private function businessDataReadModel(): BusinessDataEvidenceReadModel
    {
        if ($this->businessDataReadModel === null) {
            $this->businessDataReadModel = new BusinessDataEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->businessDataReadModel;
    }

    private function payrollReadModel(): PayrollEvidenceReadModel
    {
        if ($this->payrollReadModel === null) {
            $this->payrollReadModel = new PayrollEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->payrollReadModel;
    }

    private function constructionReadModel(): ConstructionEvidenceReadModel
    {
        if ($this->constructionReadModel === null) {
            $this->constructionReadModel = new ConstructionEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceProcessingPolicyService
            );
        }

        return $this->constructionReadModel;
    }
}
