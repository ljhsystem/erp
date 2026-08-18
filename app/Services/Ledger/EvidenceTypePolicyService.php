<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\System\CodeModel;
use PDO;

class EvidenceTypePolicyService
{
    private array $codeNameCache = [];
    private const DEFAULT_EVIDENCE_UPLOAD_TYPES = [
        'TAX_INVOICE',
        'TAX_INVOICE_MANUAL',
        'CASH_RECEIPT',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
        'CARD_APPROVAL',
        'BANK_TRANSACTION',
    ];

    private const DEFAULT_BUSINESS_DATA_TYPES = [
        'BUSINESS_DATA',
        'SHOPPING_ORDER',
        'PAYROLL',
        'PAYROLL_WITHHOLDING',
        'BUSINESS_INCOME',
        'EMPLOYEE_EXPENSE',
        'IMPORT_INVOICE',
        'CONSTRUCTION',
    ];

    private const DEFAULT_LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'TAX_INVOICE_HOMETAX' => 'TAX_INVOICE',
        'MANUAL_TAX_INVOICE' => 'TAX_INVOICE_MANUAL',
        'TAX_INVOICE_PURCHASE_SALES_MANUAL' => 'TAX_INVOICE_MANUAL',
        'TAX_INVOICE_BUY_SELL_MANUAL' => 'TAX_INVOICE_MANUAL',
        'CARD' => 'CARD_STATEMENT',
        'CARD_PURCHASE' => 'CARD_STATEMENT',
        'CARD_SALE' => 'CARD_STATEMENT',
        'CARD_SALES' => 'SHOPPING_ORDER',
        'CARD_SALES_SHOPPING' => 'SHOPPING_ORDER',
        'CASH_RECEIPT_PURCHASE' => 'CASH_RECEIPT',
        'CASH_RECEIPT_PURCHAS' => 'CASH_RECEIPT',
        'CASH_RECEIPT_BUY' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SALES' => 'CASH_RECEIPT_SALES',
        'CASH_RECEIPT_SALE' => 'CASH_RECEIPT_SALES',
        'CASH_RECEIPT_SELL' => 'CASH_RECEIPT_SALES',
        'CASH_SALES' => 'BUSINESS_DATA',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
        'DAILY_WORKER' => 'PAYROLL_WITHHOLDING',
        'DAILY_WORK_REPORT' => 'PAYROLL_WITHHOLDING',
        'PAYROLL_REPORT' => 'PAYROLL',
        'BUSINESS_INCOME_REPORT' => 'BUSINESS_INCOME',
        'EMPLOYEE_EXPENSE_PERSONAL' => 'EMPLOYEE_EXPENSE_PERSONAL',
    ];

    private const STATUS_VIEW_IMPORT_TYPES = [
        'TAX_INVOICE',
        'TAX_INVOICE_MANUAL',
        'CARD_HOMETAX',
        'CARD_APPROVAL',
        'CARD_STATEMENT',
        'BANK_TRANSACTION',
        'CASH_RECEIPT',
        'IMPORT_INVOICE',
        'SHOPPING_ORDER',
        'PAYROLL_WITHHOLDING',
        'BUSINESS_DATA',
        'PAYROLL',
        'BUSINESS_INCOME',
        'EMPLOYEE_EXPENSE',
        'EMPLOYEE_EXPENSE_PERSONAL',
        'CONSTRUCTION',
    ];

    private const STATUS_VIEW_POLICY_META = [
        'TAX_INVOICE_MANUAL' => [
            'excel_template' => 'tax_invoice',
            'date_label' => '작성일자',
        ],
        'TAX_INVOICE' => [
            'excel_template' => 'tax_invoice',
            'date_label' => '작성일자',
        ],
        'CARD_HOMETAX' => [
            'excel_template' => 'card_approval',
            'date_label' => '사용일자',
        ],
        'CARD_APPROVAL' => [
            'excel_template' => 'card_approval',
            'date_label' => '사용일자',
        ],
        'CARD_STATEMENT' => [
            'excel_template' => 'card_statement',
            'date_label' => '사용일자',
        ],
        'BANK_TRANSACTION' => [
            'excel_template' => 'bank_transaction',
            'date_label' => '거래일자',
        ],
        'CASH_RECEIPT' => [
            'excel_template' => 'cash_receipt',
            'date_label' => '발행일자',
        ],
        'IMPORT_INVOICE' => [
            'excel_template' => 'import_invoice',
            'date_label' => '신고일자',
        ],
        'SHOPPING_ORDER' => [
            'excel_template' => 'shopping_order',
            'date_label' => '정산일자',
        ],
        'PAYROLL_WITHHOLDING' => [
            'excel_template' => 'payroll_withholding',
            'date_label' => '귀속일자',
        ],
        'BUSINESS_DATA' => [
            'excel_template' => 'business_data',
            'date_label' => '거래일자',
        ],
        'PAYROLL' => [
            'excel_template' => 'payroll',
            'date_label' => '귀속일자',
        ],
        'BUSINESS_INCOME' => [
            'excel_template' => 'business_income',
            'date_label' => '거래일자',
        ],
        'EMPLOYEE_EXPENSE' => [
            'excel_template' => 'employee_expense',
            'date_label' => '사용일자',
        ],
        'EMPLOYEE_EXPENSE_PERSONAL' => [
            'excel_template' => '',
            'date_label' => '지출일자',
        ],
        'CONSTRUCTION' => [
            'excel_template' => 'construction',
            'date_label' => '거래일자',
        ],
    ];

    private const STATUS_VIEW_POLICY_CONFIG = [
        'PAYROLL' => [
            'meta_domain' => 'evidence-payroll-report',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['raw_payment_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['raw_payment_date'],
            'transaction_workflow_required' => false,
            'excel_manager_mode' => 'none',
            'excel_manager_domain' => '',
            'source_key_aliases' => [],
            'modal_preset' => 'default',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'EMPLOYEE_EXPENSE_PERSONAL' => [
            'meta_domain' => 'evidence-employee-expense-personal',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['raw_expense_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['raw_expense_date'],
            'transaction_workflow_required' => false,
            'excel_manager_mode' => 'none',
            'excel_manager_domain' => '',
            'source_key_aliases' => [],
            'modal_preset' => 'default',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'BANK_TRANSACTION' => [
            'meta_domain' => 'evidence-bank-transaction',
            'summary_bucket' => 'bank',
            'date_candidate_keys' => ['raw_transaction_datetime', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['raw_transaction_datetime'],
            'transaction_workflow_required' => false,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-bank-transaction',
            'source_key_aliases' => [
                'bank_transaction_raw_transaction_datetime' => 'raw_transaction_datetime',
                'bank_transaction_raw_withdraw_amount' => 'raw_withdraw_amount',
                'bank_transaction_raw_deposit_amount' => 'raw_deposit_amount',
                'bank_transaction_raw_balance_amount' => 'raw_balance_amount',
                'bank_transaction_raw_description' => 'raw_description',
                'bank_transaction_raw_counterparty_account_number' => 'raw_counterparty_account_number',
                'bank_transaction_raw_counterparty_bank_name' => 'raw_counterparty_bank_name',
                'bank_transaction_raw_memo' => 'raw_memo',
                'bank_transaction_transaction_direction' => 'transaction_direction',
                'bank_transaction_raw_check_bill_amount' => 'raw_check_bill_amount',
                'bank_transaction_raw_cms_code' => 'raw_cms_code',
                'bank_transaction_raw_counterparty_name' => 'raw_counterparty_name',
            ],
            'modal_preset' => 'bank_like',
            'deprecated_format_fields' => [
                'voucher_date',
                'summary_text',
                'note',
                'voucher_memo',
                'header_row_no',
                'sort_no',
                'account_id',
                'debit',
                'credit',
                'line_summary',
            ],
            'deprecated_format_titles' => [
                '전표일자',
                '전표적요',
                '전표메모',
                '전표비고',
                '헤더순번',
                '라인번호',
                '계정과목',
                '차변',
                '차변금액',
                '대변',
                '대변금액',
                '적요',
            ],
        ],
        'TAX_INVOICE' => [
            'meta_domain' => 'evidence-tax-invoice',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['transaction_date', 'issue_date', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['write_date', 'written_date', 'issue_write_date', 'transaction_date', 'evidence_date'],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-tax-invoice',
            'source_key_aliases' => [],
            'modal_preset' => 'business_only',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'TAX_INVOICE_MANUAL' => [
            'meta_domain' => 'evidence-tax-invoice-manual',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['transaction_date', 'issue_date', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['write_date', 'written_date', 'issue_write_date', 'transaction_date', 'evidence_date'],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-tax-invoice-manual',
            'source_key_aliases' => [],
            'modal_preset' => 'business_only',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'CASH_RECEIPT' => [
            'meta_domain' => 'evidence-cash-receipt',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['transaction_date', 'purchase_date', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['purchase_datetime', 'purchase_at', 'purchase_date'],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-cash-receipt',
            'source_key_aliases' => [],
            'modal_preset' => 'business_only',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'CARD_HOMETAX' => [
            'meta_domain' => 'evidence-card-hometax',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['transaction_date', 'approval_date', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['approval_date', 'approved_date', 'approval_datetime', 'approved_at'],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-card-hometax',
            'source_key_aliases' => [],
            'modal_preset' => 'business_only',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'CARD_APPROVAL' => [
            'meta_domain' => 'evidence-card-statement',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['transaction_date', 'approval_date', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['approval_date', 'approved_date', 'approval_datetime', 'approved_at'],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-card-statement',
            'source_key_aliases' => [],
            'modal_preset' => 'business_only',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
        'CARD_STATEMENT' => [
            'meta_domain' => 'evidence-card-statement',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['transaction_date', 'approval_date', 'evidence_date', 'created_at', 'updated_at'],
            'sort_target_keys' => ['approval_date', 'approved_date', 'approval_datetime', 'approved_at'],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'core',
            'excel_manager_domain' => 'evidence-card-statement',
            'source_key_aliases' => [],
            'modal_preset' => 'business_only',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ],
    ];

    /** @var callable(string):string|null */
    private $normalizeDataTypeCallback;

    /** @var array<string,string> */
    private array $legacyDataTypeMap;
    private ?CodeModel $codeModel = null;
    private ?EvidenceSchemaModel $schemaModel = null;

    public function __construct(
        ?callable $normalizeDataType = null,
        private ?PDO $pdo = null,
        private array $callbacks = []
    ) {
        $this->normalizeDataTypeCallback = $normalizeDataType;
        $this->legacyDataTypeMap = self::defaultLegacyDataTypeMap();
        if ($this->pdo !== null) {
            $this->codeModel = new CodeModel($this->pdo);
            $this->schemaModel = new EvidenceSchemaModel($this->pdo);
        }
    }

    /** @return array<string,string> */
    public static function defaultLegacyDataTypeMap(): array
    {
        return self::DEFAULT_LEGACY_DATA_TYPE_MAP;
    }

    /** @return list<string> */
    public static function defaultEvidenceUploadTypes(): array
    {
        return self::DEFAULT_EVIDENCE_UPLOAD_TYPES;
    }

    /** @return list<string> */
    public static function defaultBusinessDataTypes(): array
    {
        return self::DEFAULT_BUSINESS_DATA_TYPES;
    }

    public static function normalizeLegacyDataType(string $type): string
    {
        $type = strtoupper(trim($type));

        return self::DEFAULT_LEGACY_DATA_TYPE_MAP[$type] ?? $type;
    }

    /** @return list<string> */
    public function evidenceUploadTypes(): array
    {
        return self::defaultEvidenceUploadTypes();
    }

    /** @return list<string> */
    public function businessDataTypes(): array
    {
        return self::defaultBusinessDataTypes();
    }

    public function normalizeImportSourceType(string $sourceType): string
    {
        $sourceType = strtoupper(trim($sourceType));

        return match ($sourceType) {
            'HOMETAX' => 'TAX',
            'CARD_COMPANY' => 'CARD',
            'BANK_ACCOUNT' => 'BANK',
            'SHOPPING_MALL' => 'SHOPPING',
            'TRADE_IMPORT', 'IMPORT' => 'TRADE',
            default => $sourceType,
        };
    }

    public function sourceTypeForDataType(string $dataType): string
    {
        return match ($this->normalizeDataType($dataType)) {
            'TAX_INVOICE', 'CASH_RECEIPT' => 'HOMETAX',
            'CARD_HOMETAX' => 'HOMETAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'BANK_TRANSACTION' => 'BANK',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => 'MANUAL',
        };
    }

    public function importTypesForSourceType(string $sourceType): array
    {
        return match ($this->normalizeImportSourceType($sourceType)) {
            'TAX' => ['TAX_INVOICE', 'CASH_RECEIPT', 'CARD_HOMETAX'],
            'CARD' => ['CARD_STATEMENT', 'CARD_APPROVAL'],
            'BANK' => ['BANK_TRANSACTION'],
            'SHOPPING', 'TRADE' => [],
            default => [],
        };
    }

    public function sourceTypeLabel(string $sourceType): string
    {
        return $this->systemCodeName('SOURCE_TYPE', $sourceType);
    }

    public function importTypeLabel(string $importType): string
    {
        return $this->systemCodeName('IMPORT_TYPE', $importType);
    }

    private function systemCodeName(string $codeGroup, string $code): string
    {
        $codeGroup = strtoupper(trim($codeGroup));
        $code = strtoupper(trim($code));
        if ($codeGroup === '' || $code === '' || !$this->systemCodesTableExists()) {
            return '';
        }
        $cacheKey = $codeGroup . ':' . $code;
        if (!array_key_exists($cacheKey, $this->codeNameCache)) {
            $this->codeNameCache[$cacheKey] = $this->codeModel?->findActiveName($codeGroup, $code) ?? '';
        }
        return $this->codeNameCache[$cacheKey];
    }

    public function statusViewImportTypePolicies(): array
    {
        $rows = $this->statusViewImportTypeRows();
        if ($rows !== []) {
            $activeTypes = array_values(array_filter(array_map(
                static fn(array $row): string => strtoupper(trim((string) ($row['code'] ?? ''))),
                $rows
            )));
            return array_map(function (array $row) use ($activeTypes): array {
                $type = (string) ($row['code'] ?? '');
                $meta = $this->statusViewPolicyMeta($type);
                $config = $this->statusViewPolicyConfig($type);

                return [
                    'code' => $type,
                    'label' => (string) ($row['label'] ?? $type),
                    'excel_template' => (string) ($meta['excel_template'] ?? strtolower($type)),
                    'date_options' => [[
                        'value' => 'mapped_payload.transaction_date',
                        'label' => (string) ($meta['date_label'] ?? '거래일자'),
                    ]],
                    'aliases' => $this->legacyAliasesForType($type, $activeTypes),
                    'meta_domain' => (string) ($config['meta_domain'] ?? ''),
                    'summary_bucket' => (string) ($config['summary_bucket'] ?? 'evidence'),
                    'date_candidate_keys' => array_values($config['date_candidate_keys'] ?? []),
                    'sort_target_keys' => array_values($config['sort_target_keys'] ?? []),
                    'transaction_workflow_required' => (bool) ($config['transaction_workflow_required'] ?? true),
                    'read_only' => $type === 'EMPLOYEE_EXPENSE_PERSONAL',
                    'excel_manager_mode' => (string) ($config['excel_manager_mode'] ?? 'custom'),
                    'excel_manager_domain' => (string) ($config['excel_manager_domain'] ?? ''),
                    'source_key_aliases' => (array) ($config['source_key_aliases'] ?? []),
                    'modal_preset' => (string) ($config['modal_preset'] ?? 'default'),
                    'deprecated_format_fields' => array_values($config['deprecated_format_fields'] ?? []),
                    'deprecated_format_titles' => array_values($config['deprecated_format_titles'] ?? []),
                ];
            }, $rows);
        }

        return array_map(function (string $type): array {
            $meta = self::STATUS_VIEW_POLICY_META[$type] ?? [];
            $config = $this->statusViewPolicyConfig($type);

            return [
                'code' => $type,
                'label' => $this->importTypeLabel($type) !== '' ? $this->importTypeLabel($type) : $type,
                'excel_template' => (string) ($meta['excel_template'] ?? strtolower($type)),
                'date_options' => [[
                    'value' => 'mapped_payload.transaction_date',
                    'label' => (string) ($meta['date_label'] ?? '거래일자'),
                ]],
                'aliases' => $this->legacyAliasesForType($type),
                'meta_domain' => (string) ($config['meta_domain'] ?? ''),
                'summary_bucket' => (string) ($config['summary_bucket'] ?? 'evidence'),
                'date_candidate_keys' => array_values($config['date_candidate_keys'] ?? []),
                'sort_target_keys' => array_values($config['sort_target_keys'] ?? []),
                'transaction_workflow_required' => (bool) ($config['transaction_workflow_required'] ?? true),
                'read_only' => $type === 'EMPLOYEE_EXPENSE_PERSONAL',
                'excel_manager_mode' => (string) ($config['excel_manager_mode'] ?? 'custom'),
                'excel_manager_domain' => (string) ($config['excel_manager_domain'] ?? ''),
                'source_key_aliases' => (array) ($config['source_key_aliases'] ?? []),
                'modal_preset' => (string) ($config['modal_preset'] ?? 'default'),
                'deprecated_format_fields' => array_values($config['deprecated_format_fields'] ?? []),
                'deprecated_format_titles' => array_values($config['deprecated_format_titles'] ?? []),
            ];
        }, self::STATUS_VIEW_IMPORT_TYPES);
    }

    private function statusViewPolicyConfig(string $type): array
    {
        $normalizedType = $this->normalizeDataType($type);
        if (isset(self::STATUS_VIEW_POLICY_CONFIG[$normalizedType])) {
            return self::STATUS_VIEW_POLICY_CONFIG[$normalizedType];
        }

        if ($this->isManualTaxInvoiceDataType($normalizedType)) {
            return self::STATUS_VIEW_POLICY_CONFIG['TAX_INVOICE_MANUAL'] ?? self::STATUS_VIEW_POLICY_CONFIG['TAX_INVOICE'] ?? [];
        }

        return [
            'meta_domain' => '',
            'summary_bucket' => 'evidence',
            'date_candidate_keys' => ['evidence_date', 'transaction_date', 'created_at', 'updated_at'],
            'sort_target_keys' => [],
            'transaction_workflow_required' => true,
            'excel_manager_mode' => 'custom',
            'excel_manager_domain' => '',
            'source_key_aliases' => [],
            'modal_preset' => 'default',
            'deprecated_format_fields' => [],
            'deprecated_format_titles' => [],
        ];
    }

    private function legacyAliasesForType(string $type, array $activeTypes = []): array
    {
        $rawType = strtoupper(trim($type));
        $normalizedType = $this->normalizeDataType($type);
        if ($rawType !== '' && $rawType !== $normalizedType && in_array($rawType, $activeTypes, true)) {
            return [];
        }

        $aliases = [];
        foreach ($this->legacyDataTypeMap as $legacy => $current) {
            if ($current !== $normalizedType) {
                continue;
            }
            if ($legacy !== $rawType && in_array($legacy, $activeTypes, true)) {
                continue;
            }
            $aliases[] = (string) $legacy;
        }

        return array_values(array_unique($aliases));
    }

    private function statusViewImportTypeRows(): array
    {
        if (!$this->systemCodesTableExists()) {
            return [];
        }

        $rows = [];
        foreach (($this->codeModel?->getActiveCodesByGroup('IMPORT_TYPE') ?? []) as $row) {
            $rawType = strtoupper(trim((string) ($row['code'] ?? '')));
            $type = $this->normalizeDataType($rawType);
            if ($type === '' || isset($rows[$type])) {
                continue;
            }

            $label = trim((string) ($row['code_name'] ?? ''));
            $rows[$type] = [
                'code' => $type,
                'label' => $label !== '' ? $label : ($this->importTypeLabel($type) !== '' ? $this->importTypeLabel($type) : $type),
                'source_code' => $rawType,
            ];
        }

        return array_values($rows);
    }

    private function statusViewPolicyMeta(string $type): array
    {
        $normalizedType = $this->normalizeDataType($type);
        if (isset(self::STATUS_VIEW_POLICY_META[$normalizedType])) {
            return self::STATUS_VIEW_POLICY_META[$normalizedType];
        }

        if ($this->isManualTaxInvoiceDataType($normalizedType)) {
            return self::STATUS_VIEW_POLICY_META['TAX_INVOICE'] ?? [];
        }

        return [];
    }

    public function sourceTypeSql(string $column): string
    {
        return "CASE {$column}
            WHEN 'HOMETAX' THEN 'HOMETAX'
            WHEN 'TAX' THEN 'HOMETAX'
            WHEN 'TAX_INVOICE' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT_PURCHASE' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT_SALES' THEN 'HOMETAX'
            WHEN 'CARD_HOMETAX' THEN 'HOMETAX'
            WHEN 'CARD' THEN 'CARD_COMPANY'
            WHEN 'CARD_COMPANY' THEN 'CARD_COMPANY'
            WHEN 'CREDIT_CARD' THEN 'CARD_COMPANY'
            WHEN 'CARD_STATEMENT' THEN 'CARD_COMPANY'
            WHEN 'CARD_APPROVAL' THEN 'CARD_COMPANY'
            WHEN 'BANK' THEN 'BANK'
            WHEN 'BANK_ACCOUNT' THEN 'BANK'
            WHEN 'BANK_TRANSACTION' THEN 'BANK'
            WHEN 'SHOPPING_ORDER' THEN 'SHOPPING'
            WHEN 'IMPORT_INVOICE' THEN 'TRADE'
            ELSE 'MANUAL'
        END";
    }

    public function queryDataTypes(string $type): array
    {
        $types = [$type];
        foreach ($this->legacyDataTypeMap as $legacy => $current) {
            if ($current === $type) {
                $types[] = $legacy;
            }
        }

        return array_values(array_unique($types));
    }

    public function transactionDirectionForStorage(string $direction, array $row, string $dataType): string
    {
        $direction = strtoupper(trim($direction));
        $dataType = $this->normalizeDataType($dataType);

        if ($dataType === 'BANK_TRANSACTION') {
            $deposit = $this->amountOrNull($row['deposit_amount'] ?? null);
            $withdraw = $this->amountOrNull($row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null);
            $bankDirection = strtoupper(trim((string) (
                $row['bank_direction']
                ?? $row['deposit_withdrawal_type']
                ?? $row['transaction_direction']
                ?? ''
            )));
            if ($bankDirection !== '' || ($withdraw !== null && $withdraw > 0) || ($deposit !== null && $deposit > 0)) {
                return 'FUND';
            }
        }

        if ($direction === '') {
            $direction = match ($dataType) {
                'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT' => 'EXPENSE',
                'TAX_INVOICE' => 'EXPENSE',
                default => '',
            };
            if ($direction === '' && $this->isManualTaxInvoiceDataType($dataType)) {
                $direction = 'EXPENSE';
            }
        }

        return match ($direction) {
            'INCOME', 'SALES', 'SALE', 'SELL', 'OUT_SALE' => 'INCOME',
            'EXPENSE', 'PURCHASE', 'BUY', 'IN_PURCHASE' => 'EXPENSE',
            'FUND', 'IN', 'OUT', 'DEPOSIT', 'RECEIPT', 'WITHDRAWAL', 'PAYMENT', 'WITHDRAW' => 'FUND',
            default => $direction !== '' ? $direction : (
                $dataType === 'BANK_TRANSACTION'
                    ? 'FUND'
                    : (($dataType === 'TAX_INVOICE' || $this->isManualTaxInvoiceDataType($dataType)) ? 'EXPENSE' : 'GENERAL')
            ),
        };
    }

    public function isManualTaxInvoiceDataType(string $dataType): bool
    {
        $type = $this->normalizeDataType($dataType);
        if (in_array($type, [
            'TAX_INVOICE_MANUAL',
            'MANUAL_TAX_INVOICE',
            'TAX_INVOICE_PURCHASE_SALES_MANUAL',
            'TAX_INVOICE_BUY_SELL_MANUAL',
        ], true)) {
            return true;
        }

        $compact = preg_replace('/[\s_\-()]+/u', '', $type) ?? $type;

        return (
            str_contains($type, 'TAX')
            && str_contains($type, 'INVOICE')
            && str_contains($type, 'MANUAL')
        ) || (
            str_contains($compact, 'TAXINVOICE')
            && str_contains($compact, 'MANUAL')
        );
    }

    public function processingPlanForDataType(string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        if ($this->isManualTaxInvoiceDataType($dataType)) {
            return [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_HEADER',
                'objects' => ['TRANSACTION_HEADER'],
                'label' => 'Voucher create + transaction create',
            ];
        }

        return match ($dataType) {
            'TAX_INVOICE', 'CASH_RECEIPT' => [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_HEADER',
                'objects' => ['TRANSACTION_HEADER'],
                'label' => 'Voucher create + transaction create',
            ],
            'CARD_STATEMENT', 'CARD_APPROVAL' => [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_AND_VOUCHER',
                'objects' => ['TRANSACTION_HEADER', 'TRANSACTION_LINE', 'VOUCHER_HEADER', 'VOUCHER_LINE'],
                'label' => 'Voucher create + transaction create + voucher line automation',
            ],
            'CARD_HOMETAX' => [
                'type' => 'VERIFY_ONLY',
                'target' => 'VERIFY_ONLY',
                'objects' => ['TAX_VERIFY', 'RECONCILIATION'],
                'label' => 'Verification-only upload for tax reconciliation',
            ],
            'BANK_TRANSACTION' => [
                'type' => 'BANK_FLOW',
                'target' => 'RECONCILIATION_ONLY',
                'objects' => ['BANK_FLOW', 'RECONCILIATION'],
                'label' => 'Bank flow load and reconciliation',
            ],
            'BUSINESS_DATA', 'SHOPPING_ORDER', 'PAYROLL', 'PAYROLL_WITHHOLDING', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE', 'EMPLOYEE_EXPENSE_PERSONAL', 'IMPORT_INVOICE', 'CONSTRUCTION' => [
                'type' => 'BUSINESS_DATA',
                'target' => 'BUSINESS_DATA',
                'objects' => ['BUSINESS_SYSTEM'],
                'label' => 'Load as business system data only',
            ],
            default => [
                'type' => 'UNSUPPORTED',
                'target' => 'UNSUPPORTED',
                'objects' => [],
                'label' => 'Voucher create + transaction create',
            ],
        };
    }

    public function businessUnitForUpload(array $row, string $dataType): string
    {
        $value = strtoupper(trim((string) ($row['business_unit'] ?? $row['business_unit_code'] ?? '')));
        if ($value !== '') {
            return $value;
        }

        return match ($this->normalizeDataType($dataType)) {
            'SHOPPING_ORDER' => 'ECOMMERCE',
            default => 'HQ',
        };
    }

    public function isTransactionProcessingType(string $dataType): bool
    {
        return $this->processingPlanForDataType($dataType)['type'] === 'TRANSACTION';
    }

    public function transactionProcessingDataTypes(array $dataTypes): array
    {
        $types = array_values(array_filter($dataTypes, fn(string $type): bool => $this->isTransactionProcessingType($type)));
        $types[] = 'BANK_TRANSACTION';

        return array_values(array_unique($types));
    }

    public function allowedDataTypes(array $dataTypes, array $businessDataTypes): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = array_values(array_unique(array_merge($dataTypes, $businessDataTypes)));
        if (!$this->systemCodesTableExists()) {
            return $types;
        }

        $rows = array_column($this->codeModel?->getActiveCodesByGroup('IMPORT_TYPE') ?? [], 'code');
        $codeTypes = array_values(array_filter(array_map(
            fn($code): string => $this->normalizeDataType((string) $code),
            $rows
        )));

        return array_values(array_unique(array_merge($types, $codeTypes)));
    }

    /** @return list<string> */
    public function defaultAllowedDataTypes(): array
    {
        return $this->allowedDataTypes(
            self::defaultEvidenceUploadTypes(),
            self::defaultBusinessDataTypes()
        );
    }

    private function normalizeDataType(string $type): string
    {
        if (is_callable($this->normalizeDataTypeCallback)) {
            return ($this->normalizeDataTypeCallback)($type);
        }

        return self::normalizeLegacyDataType($type);
    }

    private function amountOrNull(mixed $value): ?float
    {
        if (!isset($this->callbacks['amountOrNull'])) {
            throw new \RuntimeException('Missing callback: amountOrNull');
        }

        return ($this->callbacks['amountOrNull'])($value);
    }

    private function systemCodesTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        if ($this->pdo === null) {
            return false;
        }

        $exists = $this->schemaModel?->tableExists('system_codes') ?? false;

        return $exists;
    }
}
