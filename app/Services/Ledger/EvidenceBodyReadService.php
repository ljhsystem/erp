<?php

namespace App\Services\Ledger;

use App\Models\Ledger\BankEvidenceReadModel;
use App\Models\Ledger\BusinessDataEvidenceReadModel;
use App\Models\Ledger\CardHometaxEvidenceReadModel;
use App\Models\Ledger\CardStatementEvidenceReadModel;
use App\Models\Ledger\CashReceiptEvidenceReadModel;
use App\Models\Ledger\ConstructionEvidenceReadModel;
use App\Models\Ledger\DailyEmploymentIncomeEvidenceReadModel;
use App\Models\Ledger\PayrollEvidenceReadModel;
use App\Models\Ledger\TaxInvoiceReadModel;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use App\Models\Ledger\EvidenceSchemaModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use Closure;
use PDO;

class EvidenceBodyReadService
{
    private ?EvidenceSchemaModel $schemaService = null;
    private ?BankEvidenceReadModel $bankReadModel = null;
    private ?TaxInvoiceReadModel $taxInvoiceReadModel = null;
    private ?CashReceiptEvidenceReadModel $cashReceiptReadModel = null;
    private ?CardHometaxEvidenceReadModel $cardHometaxReadModel = null;
    private ?CardStatementEvidenceReadModel $cardStatementReadModel = null;
    private ?BusinessDataEvidenceReadModel $businessDataReadModel = null;
    private ?PayrollEvidenceReadModel $payrollReadModel = null;
    private ?DailyEmploymentIncomeEvidenceReadModel $dailyEmploymentIncomeReadModel = null;
    private ?ConstructionEvidenceReadModel $constructionReadModel = null;
    private ?EvidenceSourceRepository $evidenceSourceRepository = null;

    public function __construct(
        private PDO $pdo,
        private EvidenceBodyStatusProjectionModel $evidenceBodyStatusProjectionModel,
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
            'EMPLOYEE_EXPENSE_PERSONAL',
            'PAYROLL',
            'DAILY_EMPLOYMENT_INCOME',
            'BUSINESS_INCOME_REPORT',
        ];
    }

    public function rowsForTypes(array $types, string $status, string $requestedId): array
    {
        $resolvedTypes = $types !== [] ? $types : $this->readyBodyImportTypes();
        $rows = [];

        foreach ($resolvedTypes as $type) {
            $normalizedType = $this->canonicalReadType($this->normalizeDataType((string) $type));
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
            $normalizedType = $this->canonicalReadType($this->normalizeDataType((string) $type));
            if ($normalizedType === '') {
                continue;
            }

            $count += $this->countRowsForType($normalizedType, $status, $requestedId);
        }

        return $count;
    }

    public function bodyEvidenceTypeCounts(): array
    {
        $counts = [];
        foreach ($this->readyBodyImportTypes() as $type) {
            $counts[$type] = $this->countRowsForType($type, '', '');
        }
        return $counts;
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }

    private function canonicalReadType(string $type): string
    {
        if ($type === 'BUSINESS_INCOME') {
            return 'BUSINESS_INCOME_REPORT';
        }
        return in_array($type, ['DAILY_WORK_REPORT', 'PAYROLL_WITHHOLDING'], true)
            ? 'DAILY_EMPLOYMENT_INCOME'
            : $type;
    }

    private function rowsForType(string $normalizedType, string $status, string $requestedId): array
    {
        return match ($normalizedType) {
            'BANK_TRANSACTION' => $this->bankReadModel()->findList($status, $requestedId),
            'TAX_INVOICE', 'TAX_INVOICE_MANUAL' => $this->taxInvoiceReadModel()->findList($normalizedType, $status, $requestedId),
            'CASH_RECEIPT' => $this->cashReceiptReadModel()->findList($status, $requestedId),
            'CARD_HOMETAX' => $this->cardHometaxReadModel()->findList($status, $requestedId),
            'CARD_STATEMENT', 'CARD_APPROVAL' => $this->cardStatementReadModel()->findList($normalizedType, $status, $requestedId),
            'SHOPPING_ORDER', 'IMPORT_INVOICE', 'BUSINESS_DATA', 'BUSINESS_INCOME_REPORT', 'EMPLOYEE_EXPENSE'
                => $this->businessDataReadModel()->findList($normalizedType, $status, $requestedId),
            'PAYROLL' => $this->payrollReadModel()->findList($normalizedType, $status, $requestedId),
            'DAILY_EMPLOYMENT_INCOME' => $requestedId !== ''
                ? array_values(array_filter([$this->dailyEmploymentIncomeReadModel()->findById($requestedId)]))
                : $this->dailyEmploymentIncomeReadModel()->findList($status, ''),
            'CONSTRUCTION' => $this->constructionReadModel()->findList($status, $requestedId),
            'EMPLOYEE_EXPENSE_PERSONAL' => $this->personalExpenseRows($status, $requestedId),
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
            'SHOPPING_ORDER', 'IMPORT_INVOICE', 'BUSINESS_DATA', 'BUSINESS_INCOME_REPORT', 'EMPLOYEE_EXPENSE'
                => $this->businessDataReadModel()->findCount($normalizedType, $status, $requestedId),
            'PAYROLL' => $this->payrollReadModel()->findCount($normalizedType, $status, $requestedId),
            'DAILY_EMPLOYMENT_INCOME' => $this->dailyEmploymentIncomeReadModel()->findCount($status, $requestedId),
            'CONSTRUCTION' => $this->constructionReadModel()->findCount($status, $requestedId),
            'EMPLOYEE_EXPENSE_PERSONAL' => $this->personalExpenseCount($status, $requestedId),
            default => 0,
        };
    }

    private function personalExpenseRows(string $status, string $requestedId): array
    {
        $page = $this->evidenceSourceRepository()->pagedProjections([
            'import_types' => ['EMPLOYEE_EXPENSE_PERSONAL'],
            'status' => $status,
            'id' => $requestedId,
            'filters' => [],
            'start' => 0,
            'length' => 5000,
            'order_field' => 'raw_expense_date',
            'order_direction' => 'desc',
        ]);
        return array_values(array_filter(array_map(
            static fn(array $projection): mixed => $projection['body'] ?? null,
            $page['projections'] ?? []
        ), 'is_array'));
    }

    private function personalExpenseCount(string $status, string $requestedId): int
    {
        $page = $this->evidenceSourceRepository()->pagedProjections([
            'import_types' => ['EMPLOYEE_EXPENSE_PERSONAL'],
            'status' => $status,
            'id' => $requestedId,
            'filters' => [],
            'start' => 0,
            'length' => 1,
        ]);
        return (int) ($page['records_filtered'] ?? 0);
    }

    private function evidenceSourceRepository(): EvidenceSourceRepository
    {
        return $this->evidenceSourceRepository ??= new EvidenceSourceRepository($this->pdo);
    }

    private function schemaService(): EvidenceSchemaModel
    {
        if ($this->schemaService === null) {
            $this->schemaService = new EvidenceSchemaModel($this->pdo);
        }

        return $this->schemaService;
    }

    private function bankReadModel(): BankEvidenceReadModel
    {
        if ($this->bankReadModel === null) {
            $this->bankReadModel = new BankEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceBodyStatusProjectionModel
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
                $this->evidenceBodyStatusProjectionModel
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
                $this->evidenceBodyStatusProjectionModel
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
                $this->evidenceBodyStatusProjectionModel
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
                $this->evidenceBodyStatusProjectionModel
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
                $this->evidenceBodyStatusProjectionModel
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
                $this->evidenceBodyStatusProjectionModel
            );
        }

        return $this->payrollReadModel;
    }

    private function dailyEmploymentIncomeReadModel(): DailyEmploymentIncomeEvidenceReadModel
    {
        return $this->dailyEmploymentIncomeReadModel ??= new DailyEmploymentIncomeEvidenceReadModel(
            $this->pdo,
            $this->schemaService()
        );
    }

    private function constructionReadModel(): ConstructionEvidenceReadModel
    {
        if ($this->constructionReadModel === null) {
            $this->constructionReadModel = new ConstructionEvidenceReadModel(
                $this->pdo,
                $this->schemaService(),
                $this->evidenceBodyStatusProjectionModel
            );
        }

        return $this->constructionReadModel;
    }
}
