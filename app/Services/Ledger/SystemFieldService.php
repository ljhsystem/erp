<?php

namespace App\Services\Ledger;

use PDO;

class SystemFieldService
{
    private array $fieldOptionsCache = [];
    private array $sourceColumnOptionsCache = [];

    private const AUTO_MANAGED_COLUMNS = [
        'id',
        'sort_no',
        'source_type',
        'format_id',
        'raw_json',
        'mapped_payload_json',
        'evidence_status',
        'transaction_status',
        'voucher_status',
        'review_status',
        'error_message',
        'latest_imported_at',
        'evidence_id',
        'status',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];

    private const FORMAT_HIDDEN_FIELDS = [
        'evidence_sort_no',
        'client_id',
        'client_name_ko',
        'client_name_en',
        'project_id',
        'employee_id',
        'bank_account_id',
        'card_id',
        'evidence_date',
        'write_date',
        'written_date',
        'purchase_datetime',
        'purchase_date',
        'purchase_at',
        'approval_datetime',
        'approval_date',
        'approved_date',
        'approved_at',
        'billing_date',
        'transaction_datetime',
        'transaction_at',
        'transaction_time',
        'line_row_type',
    ];

    private const FORMAT_DEPRECATED_FIELDS = [
        'voucher_date',
        'summary_text',
        'note',
        'voucher_memo',
        'header_row_no',
        'line_no',
        'account_id',
        'debit',
        'credit',
        'line_summary',
    ];

    private const FIELD_LABELS = [
        'client_name' => '거래처명',
        'client_name_ko' => '거래처명(한글)',
        'client_name_en' => '거래처명(영문)',
        'project_name' => '프로젝트명',
        'employee_id' => '직원 ID',
        'employee_name' => '직원명',
        'team_id' => '팀 ID',
        'bank_account_name' => '계좌명',
        'card_id' => '카드 ID',
        'card_name' => '카드명',
        'import_type' => '자료유형',
        'business_unit' => '사업구분',
        'transaction_direction' => '거래구분',
        'raw_transaction_datetime' => '거래일시',
        'raw_deposit_amount' => '입금',
        'raw_withdraw_amount' => '출금',
        'raw_balance_amount' => '거래 후 잔액',
        'raw_description' => '거래내용',
        'raw_counterparty_account_number' => '상대계좌번호',
        'raw_counterparty_bank_name' => '상대은행',
        'raw_memo' => '메모',
        'raw_check_bill_amount' => '수표어음금액',
        'raw_cms_code' => 'CMS코드',
        'raw_counterparty_name' => '상대계좌예금주명',
        'client_company_name' => '상호',
        'company_name' => '회사명',
        'business_type' => '업태',
        'business_category' => '업종',
        'supplier_business_number' => '공급자 사업자등록번호',
        'supplier_company_name' => '공급자 상호',
        'supplier_ceo_name' => '공급자 대표자명',
        'supplier_address' => '공급자 주소',
        'supplier_email' => '공급자 이메일',
        'customer_business_number' => '공급받는자 사업자등록번호',
        'customer_company_name' => '공급받는자 상호',
        'customer_ceo_name' => '공급받는자 대표자명',
        'customer_address' => '공급받는자 주소',
        'customer_email_1' => '공급받는자 이메일1',
        'raw_item_date' => '품목일자',
        'raw_item_name' => '품목명',
        'raw_item_spec' => '품목규격',
        'raw_item_quantity' => '품목수량',
        'raw_item_unit_price' => '품목단가',
        'raw_item_supply_amount' => '품목공급가액',
        'raw_item_tax_amount' => '품목세액',
        'raw_item_note' => '품목비고',
        'source_key' => '승인번호',
        'evidence_date' => '증빙일자',
        'client_id' => '거래처 ID',
        'project_id' => '프로젝트 ID',
        'currency' => '기본통화',
        'supply_amount' => '공급가액',
        'adjustment_amount' => '가감금액',
        'vat_amount' => '부가세',
        'total_amount' => '금액',
        'transaction_date' => '거래일자',
        'transaction_datetime' => '거래일시',
        'transaction_time' => '거래시간',
        'bank_account_id' => '은행계좌',
        'operation_type' => '입출금유형',
        'transaction_direction' => '거래구분',
        'bank_direction' => '입출금구분',
        'business_unit' => '사업구분',
        'deposit_amount' => '입금액',
        'withdraw_amount' => '출금액',
        'balance_amount' => '잔액',
        'balance_status' => '거래후잔액상태',
        'check_bill_amount' => '수표어음금액',
        'currency_code' => '통화',
        'exchange_rate' => '기본환율',
        'description' => '적요',
        'counterparty_name' => '상대방명',
        'counterparty_account_number' => '상대계좌번호',
        'counterparty_bank_name' => '상대은행',
        'bank_reference_no' => '은행거래번호',
        'external_key' => '은행거래번호',
        'memo' => '메모',
        'voucher_date' => '전표일자',
        'voucher_no' => '전표번호',
        'summary_text' => '전표적요',
        'note' => '비고',
        'voucher_memo' => '전표메모',
        'header_row_no' => '헤더순번',
        'line_no' => '분개라인번호',
        'line_row_type' => '행타입',
        'account_id' => '계정',
        'debit' => '차변금액',
        'credit' => '대변금액',
        'line_summary' => '라인적요',
    ];

    private const DATA_EVIDENCE_TYPES = [
        'TAX_INVOICE',
        'CARD',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
        'CARD_APPROVAL',
        'CASH_RECEIPT',
        'BANK_TRANSACTION',
    ];

    private const BANK_TRANSACTION_REQUIRED_FIELDS = [
        'raw_transaction_datetime',
    ];

    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_STATEMENT',
        'CARD_PURCHASE' => 'CARD_STATEMENT',
        'CARD_SALE' => 'CARD_STATEMENT',
        'CASH_RECEIPT_PURCHASE' => 'CASH_RECEIPT',
        'CASH_RECEIPT_PURCHAS' => 'CASH_RECEIPT',
        'CASH_RECEIPT_BUY' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SALES' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SALE' => 'CASH_RECEIPT',
        'CASH_RECEIPT_SELL' => 'CASH_RECEIPT',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
    ];

    private const MAPPED_PAYLOAD_FIELDS = [
        ['value' => 'bank_direction', 'label' => '입출금구분', 'group' => '은행입출금 원본컬럼'],
        ['value' => 'transaction_date', 'label' => '거래일자', 'group' => '기준정보(JSON)'],
        ['value' => 'currency_code', 'label' => '통화', 'group' => '기준정보(JSON)'],
        ['value' => 'exchange_rate', 'label' => '기본환율', 'group' => '기준정보(JSON)'],
        ['value' => 'adjustment_amount', 'label' => '가감금액', 'group' => '금액 후보(JSON)'],
        ['value' => 'business_unit', 'label' => '사업구분', 'group' => '기준정보(JSON)'],
        ['value' => 'operation_type', 'label' => '입출금유형', 'group' => '기준정보(JSON)'],
        ['value' => 'client_id', 'label' => '거래처 ID', 'group' => '업무 기준정보(JSON)'],
        ['value' => 'client_name', 'label' => '거래처명', 'group' => '기초정보(JSON)'],
        ['value' => 'client_name_ko', 'label' => '거래처(한글)', 'group' => '기초정보(JSON)'],
        ['value' => 'client_name_en', 'label' => '거래처(영문)', 'group' => '기초정보(JSON)'],
        ['value' => 'project_id', 'label' => '프로젝트 ID', 'group' => '업무 기준정보(JSON)'],
        ['value' => 'project_name', 'label' => '프로젝트명', 'group' => '기초정보(JSON)'],
        ['value' => 'employee_id', 'label' => '직원 ID', 'group' => '업무 기준정보(JSON)'],
        ['value' => 'employee_name', 'label' => '직원명', 'group' => '기초정보(JSON)'],
        ['value' => 'bank_account_name', 'label' => '계좌명', 'group' => '기초정보(JSON)'],
        ['value' => 'card_id', 'label' => '카드 ID', 'group' => '업무 기준정보(JSON)'],
        ['value' => 'card_name', 'label' => '카드명', 'group' => '기초정보(JSON)'],
        ['value' => 'total_amount', 'label' => '금액', 'group' => '금액 정보(JSON)'],
        ['value' => 'description', 'label' => '적요', 'group' => '표준 매핑(JSON)'],
        ['value' => 'issue_date', 'label' => '발급일자', 'group' => '세금계산서 원본 속성(JSON)'],
        ['value' => 'transmit_date', 'label' => '전송일자', 'group' => '세금계산서 원본 속성(JSON)'],
        ['value' => 'tax_invoice_category', 'label' => '전자세금계산서분류', 'group' => '세금계산서 원본 속성(JSON)'],
        ['value' => 'tax_invoice_type', 'label' => '전자세금계산서종류', 'group' => '세금계산서 원본 속성(JSON)'],
        ['value' => 'issue_type', 'label' => '발급유형', 'group' => '세금계산서 원본 속성(JSON)'],
        ['value' => 'receipt_claim_type', 'label' => '영수/청구구분', 'group' => '세금계산서 원본 속성(JSON)'],
        ['value' => 'user_name', 'label' => '사용자명', 'group' => '카드/현금영수증 원본 속성(JSON)'],
        ['value' => 'purchase_datetime', 'label' => '매입일시', 'group' => '카드/현금영수증 원본 속성(JSON)'],
        ['value' => 'issue_method', 'label' => '발급수단', 'group' => '카드/현금영수증 원본 속성(JSON)'],
        ['value' => 'deduction_status', 'label' => '공제여부', 'group' => '카드/현금영수증 원본 속성(JSON)'],
        ['value' => 'note', 'label' => '비고', 'group' => '입출금(은행)원본'],
        ['value' => 'raw_item_date', 'label' => '품목일자', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_name', 'label' => '품목명', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_spec', 'label' => '품목규격', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_quantity', 'label' => '품목수량', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_unit_price', 'label' => '품목단가', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_supply_amount', 'label' => '품목공급가액', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_tax_amount', 'label' => '품목세액', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'raw_item_note', 'label' => '품목비고', 'group' => '거래라인 후보(JSON)'],
        ['value' => 'supplier_business_number', 'label' => '공급자 사업자등록번호', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'supplier_branch_number', 'label' => '공급자 종사업장번호', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'supplier_company_name', 'label' => '공급자 상호', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'supplier_ceo_name', 'label' => '공급자 대표자명', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'supplier_address', 'label' => '공급자 주소', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'supplier_email', 'label' => '공급자 이메일', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_business_number', 'label' => '공급받는자 사업자등록번호', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_branch_number', 'label' => '공급받는자 종사업장번호', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_company_name', 'label' => '공급받는자 상호', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_ceo_name', 'label' => '공급받는자 대표자명', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_address', 'label' => '공급받는자 주소', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_email_1', 'label' => '공급받는자 이메일1', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'customer_email_2', 'label' => '공급받는자 이메일2', 'group' => '세금계산서 거래처 후보(JSON)'],
        ['value' => 'merchant_business_number', 'label' => '가맹점 사업자등록번호', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_company_name', 'label' => '가맹점명/상호', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_type', 'label' => '가맹점유형', 'group' => '카드/현금영수증 원본 속성(JSON)'],
        ['value' => 'merchant_industry_code', 'label' => '가맹점 업종코드', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_business_type', 'label' => '가맹점 업태', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_business_category', 'label' => '가맹점 업종', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_address1', 'label' => '가맹점 주소1', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_address2', 'label' => '가맹점 주소2', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_address', 'label' => '가맹점 주소', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_phone', 'label' => '가맹점 전화번호', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'merchant_zip_code', 'label' => '가맹점 우편번호', 'group' => '카드/현금영수증 거래처 후보(JSON)'],
        ['value' => 'card_number', 'label' => '카드번호', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'card_type', 'label' => '카드종류', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'source_card_company_name', 'label' => '카드사', 'group' => '카드(홈택스) 원본 속성(JSON)'],
        ['value' => 'card_company_name', 'label' => '카드사', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'payment_account_number', 'label' => '결제계좌번호', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'payment_bank_name', 'label' => '결제계좌은행명', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'receive_info_type_code', 'label' => '수신정보구분코드', 'group' => '카드(카드사)원본'],
        ['value' => 'designee_korean_name', 'label' => '지정자한글명', 'group' => '카드(카드사)원본'],
        ['value' => 'domestic_foreign_type', 'label' => '국내외사용구분', 'group' => '카드(카드사)원본'],
        ['value' => 'sales_type', 'label' => '매출종류', 'group' => '카드(카드사)원본'],
        ['value' => 'amount_sign_code', 'label' => '금액SIGN코드', 'group' => '카드(카드사)원본'],
        ['value' => 'accounting_code', 'label' => '회계코드', 'group' => '카드(카드사)원본'],
        ['value' => 'accounting_code_name', 'label' => '회계코드명', 'group' => '카드(카드사)원본'],
        ['value' => 'purchase_amount_krw', 'label' => '매입금액(원화)', 'group' => '카드(카드사)원본'],
        ['value' => 'billing_date', 'label' => '청구일자', 'group' => '카드(카드사)원본'],
        ['value' => 'previous_notice_amount', 'label' => '기통지액', 'group' => '카드(카드사)원본'],
        ['value' => 'billing_amount', 'label' => '청구금액', 'group' => '카드 금액 후보(JSON)'],
        ['value' => 'service_amount', 'label' => '봉사료', 'group' => '카드 금액 후보(JSON)'],
        ['value' => 'withholding_amount', 'label' => '원천세', 'group' => '카드 금액 후보(JSON)'],
        ['value' => 'fee_amount', 'label' => '수수료', 'group' => '카드 금액 후보(JSON)'],
        ['value' => 'actual_billing_amount', 'label' => '실청구금액', 'group' => '카드 금액 후보(JSON)'],
        ['value' => 'installment_period', 'label' => '할부기간', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'installment_sequence', 'label' => '할부회차', 'group' => '카드 원본 후보(JSON)'],
        ['value' => 'foreign_amount', 'label' => '거래금액(외화)', 'group' => '카드 외화 후보(JSON)'],
        ['value' => 'local_amount', 'label' => '현지금액', 'group' => '카드 외화 후보(JSON)'],
        ['value' => 'foreign_country_code', 'label' => '외화거래국가코드', 'group' => '카드 외화 후보(JSON)'],
        ['value' => 'foreign_country_name', 'label' => '외화거래국가명', 'group' => '카드 외화 후보(JSON)'],
        ['value' => 'foreign_city_name', 'label' => '외화거래도시명', 'group' => '카드 외화 후보(JSON)'],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function targetTableForDataType(string $dataType): string
    {
        $dataType = $this->normalizeDataType($dataType);

        return match ($dataType) {
            'BANK_TRANSACTION' => 'ledger_evidence_bank_transaction',
            'TAX_INVOICE' => 'ledger_evidence_tax_invoice',
            'TAX_INVOICE_MANUAL' => 'ledger_evidence_tax_invoice_manual',
            'CASH_RECEIPT' => 'ledger_evidence_cash_receipt',
            'CARD_HOMETAX' => 'ledger_evidence_card_hometax',
            'CARD', 'CARD_STATEMENT', 'CARD_APPROVAL' => 'ledger_evidence_card_statement',
            default => 'ledger_evidence_tax_invoice',
        };
    }

    public function fieldOptions(string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        if (isset($this->fieldOptionsCache[$dataType])) {
            return $this->fieldOptionsCache[$dataType];
        }

        $tableName = $this->targetTableForDataType($dataType);
        $usesCurrency = $this->dataTypeUsesCurrency($dataType);
        $isManualTaxInvoice = $this->isManualTaxInvoiceDataType($dataType);
        $isTaxInvoiceLike = $dataType === 'TAX_INVOICE' || $isManualTaxInvoice;
        $isCashReceipt = $dataType === 'CASH_RECEIPT';
        $isCardHometax = $dataType === 'CARD_HOMETAX';
        $isCardCompany = in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true);
        $cashReceiptHiddenFields = [
            'description',
            'merchant_zip_code',
            'merchant_address1',
            'merchant_address2',
            'merchant_address',
            'merchant_phone',
        ];
        $cardCompanyHiddenFields = [
            'client_name',
            'client_name_ko',
            'client_name_en',
            'employee_name',
            'bank_account_name',
            'description',
            'card_company_name',
            'merchant_industry_code',
            'merchant_address',
            'total_amount',
        ];
        $manualTaxInvoiceHiddenFields = [
            'source_key',
            'approval_number',
            'employee_name',
            'bank_account_name',
            'card_name',
        ];
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_COMMENT, DATA_TYPE, IS_NULLABLE, ORDINAL_POSITION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            ORDER BY ORDINAL_POSITION ASC
        ");
        $stmt->execute([':table_name' => $tableName]);

        $rows = array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            static fn(array $row): bool => !in_array((string) $row['COLUMN_NAME'], self::AUTO_MANAGED_COLUMNS, true)
                && !in_array((string) $row['COLUMN_NAME'], self::FORMAT_HIDDEN_FIELDS, true)
                && !in_array((string) $row['COLUMN_NAME'], self::FORMAT_DEPRECATED_FIELDS, true)
                && !(!$usesCurrency && in_array((string) $row['COLUMN_NAME'], ['currency'], true))
                && !($isManualTaxInvoice && in_array((string) $row['COLUMN_NAME'], $manualTaxInvoiceHiddenFields, true))
                && !(($dataType === 'TAX_INVOICE' || $isCashReceipt || $isCardHometax) && in_array((string) $row['COLUMN_NAME'], ['employee_name', 'bank_account_name', 'card_name'], true))
                && !($isCardHometax && in_array((string) $row['COLUMN_NAME'], ['source_key', 'approval_number', 'user_name'], true))
                && !($isCashReceipt && in_array((string) $row['COLUMN_NAME'], $cashReceiptHiddenFields, true))
                && !($isCardCompany && in_array((string) $row['COLUMN_NAME'], $cardCompanyHiddenFields, true))
        ));

        $physicalFields = array_map(static function (array $row) use ($tableName, $dataType): array {
            $columnName = (string) $row['COLUMN_NAME'];
            $comment = trim((string) ($row['COLUMN_COMMENT'] ?? ''));
            $label = self::FIELD_LABELS[$columnName] ?? ($comment !== '' ? $comment : $columnName);

            return [
                'value' => $columnName,
                'label' => $label,
                'group' => self::physicalFieldGroupLabel($dataType, $tableName, $columnName),
                'table' => $tableName,
                'data_type' => (string) ($row['DATA_TYPE'] ?? ''),
                'is_nullable' => (string) ($row['IS_NULLABLE'] ?? ''),
                'ordinal_position' => (int) ($row['ORDINAL_POSITION'] ?? 0),
            ];
        }, $rows);

        if ($dataType === 'BANK_TRANSACTION') {
            return $this->fieldOptionsCache[$dataType] = $this->applyFormatRequirementPolicy($dataType, $physicalFields);
        }

        $fields = $this->mergeFieldOptions(
            $dataType,
            $this->referenceFieldOptions($dataType),
            $physicalFields,
            $this->mappedPayloadFieldOptions($dataType)
        );

        return $this->fieldOptionsCache[$dataType] = $this->applyFormatRequirementPolicy($dataType, $fields);
    }

    public function sourceColumnOptions(string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        if (isset($this->sourceColumnOptionsCache[$dataType])) {
            return $this->sourceColumnOptionsCache[$dataType];
        }

        $tableName = $this->targetTableForDataType($dataType);
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_COMMENT, DATA_TYPE, IS_NULLABLE, ORDINAL_POSITION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            ORDER BY ORDINAL_POSITION ASC
        ");

        $stmt->execute([':table_name' => $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $columns = [];
        foreach ($rows as $row) {
            $columnName = (string) ($row['COLUMN_NAME'] ?? '');
            if ($columnName === '') {
                continue;
            }
            if ($this->isHiddenFormatColumn(['value' => $columnName], $dataType)) {
                continue;
            }
            $comment = trim((string) ($row['COLUMN_COMMENT'] ?? ''));
            $label = $comment !== ''
                ? $comment
                : (self::FIELD_LABELS[$columnName] ?? $columnName);
            $fieldOption = [
                'value' => $columnName,
                'label' => $label,
                'table' => $tableName,
                'column' => $columnName,
                'data_type' => (string) ($row['DATA_TYPE'] ?? ''),
                'is_nullable' => (string) ($row['IS_NULLABLE'] ?? 'YES'),
                'ordinal_position' => (int) ($row['ORDINAL_POSITION'] ?? 0),
            ];
            $fieldOption['is_required'] = $this->formatFieldRequiredMode($dataType, $columnName, $fieldOption);
            $columns[] = $fieldOption;
        }

        return $this->sourceColumnOptionsCache[$dataType] = $columns;
    }

    public function formatFieldRequiredMode(string $dataType, string $field, ?array $fieldOption = null): int
    {
        $dataType = $this->normalizeDataType($dataType);
        $field = trim($field);
        if ($field === '') {
            return 0;
        }

        if ($dataType === 'BANK_TRANSACTION') {
            return in_array($field, self::BANK_TRANSACTION_REQUIRED_FIELDS, true) ? 1 : 0;
        }

        $nullable = strtoupper(trim((string) (($fieldOption ?? [])['is_nullable'] ?? 'YES')));

        return $nullable === 'NO' ? 1 : 0;
    }

    public function normalizeFormatRequirementMode(mixed $value): int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $mode = (int) $value;
            return in_array($mode, [0, 1, 2], true) ? $mode : 0;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'required', 'true', 'yes', 'y' => 1,
            'recommended', 'suggested' => 2,
            'hidden', 'optional', 'false', 'no', 'n', '' => 0,
            default => 0,
        };
    }

    public function isSystemRequiredFormatField(string $dataType, string $field, ?array $fieldOption = null): bool
    {
        return $this->formatFieldRequiredMode($dataType, $field, $fieldOption) === 1;
    }

    public function effectiveFormatRequirementMode(string $dataType, string $field, mixed $requested = null, ?array $fieldOption = null): int
    {
        if ($this->isSystemRequiredFormatField($dataType, $field, $fieldOption)) {
            return 1;
        }

        return $this->normalizeFormatRequirementMode($requested);
    }

    private function applyFormatRequirementPolicy(string $dataType, array $fields): array
    {
        foreach ($fields as &$field) {
            $fieldName = trim((string) ($field['value'] ?? ''));
            $field['is_required'] = $this->formatFieldRequiredMode($dataType, $fieldName, $field);
        }
        unset($field);

        return $fields;
    }

    private function isHiddenFormatColumn(array $field, string $dataType): bool
    {
        $dataType = $this->normalizeDataType($dataType);
        $value = trim((string) ($field['value'] ?? ''));
        if ($value === '') {
            return true;
        }

        $allowHiddenField = in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true) && $value === 'billing_date';
        $allowDeprecatedField = ($dataType === 'BANK_TRANSACTION' || $dataType === 'CARD_HOMETAX' || $this->isManualTaxInvoiceDataType($dataType)) && $value === 'note';

        return (!$allowHiddenField && in_array($value, self::FORMAT_HIDDEN_FIELDS, true))
            || (!$allowDeprecatedField && in_array($value, self::FORMAT_DEPRECATED_FIELDS, true));
    }

    private function referenceFieldOptions(string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        $isManualTaxInvoice = $this->isManualTaxInvoiceDataType($dataType);
        $isTaxInvoiceLike = $dataType === 'TAX_INVOICE' || $isManualTaxInvoice;
        $currencyField = $dataType === 'BANK_TRANSACTION' ? 'currency_code' : 'currency';
        $needsCurrency = $this->dataTypeUsesCurrency($dataType);
        $isCashReceipt = $dataType === 'CASH_RECEIPT';
        $isCardHometax = $dataType === 'CARD_HOMETAX';
        $isCardCompany = in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true);
        $fields = [
            $this->fixedFieldOption('transaction_date', '거래일자', '기준정보', 'standard_date', 'transaction_date', 'date'),
            $needsCurrency
                ? $this->tableFieldOption($currencyField, '통화', '기준정보', 'system_codes', 'code', 'varchar', [
                    'code_group' => 'CURRENCY',
                ])
                : null,
            $needsCurrency
                ? $this->fixedFieldOption('exchange_rate', '환율', '기준정보', 'mapped_payload_json', 'exchange_rate', 'decimal')
                : null,
            $this->tableFieldOption('business_unit', '사업구분', '기준정보', 'system_codes', 'code', 'varchar', [
                'code_group' => 'BUSINESS_UNIT',
            ]),
            $dataType === 'BANK_TRANSACTION'
                ? $this->tableFieldOption('operation_type', '입출금유형', '기준정보', 'system_codes', 'code', 'varchar', [
                    'code_group' => 'OPERATION_TYPE',
                ])
                : null,
            ($isManualTaxInvoice || in_array($dataType, ['BANK_TRANSACTION', 'TAX_INVOICE', 'CASH_RECEIPT', 'CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL'], true))
                ? $this->tableFieldOption('transaction_direction', '거래구분', '기준정보', 'system_codes', 'code', 'varchar', [
                    'code_group' => 'TRANSACTION_DIRECTION',
                ])
                : null,
            $isCardHometax
                ? $this->fixedFieldOption('source_card_company_name', '카드사', '원본정보', 'mapped_payload_json', 'source_card_company_name', 'json')
                : null,
            $this->tableFieldOption(
                'client_name',
                '거래처명',
                '기초정보',
                'system_clients',
                'client_name'
            ),
            $dataType === 'CASH_RECEIPT'
                ? $this->tableFieldOption('user_name', '사용자명', '기초정보', 'system_company', 'company_name_ko')
                : null,
            ($isCashReceipt || $isCardHometax || $isCardCompany)
                ? $this->tableFieldOption('merchant_business_number', '가맹점 사업자등록번호', '기초정보', 'system_clients', 'business_number')
                : null,
            ($isCashReceipt || $isCardHometax || $isCardCompany)
                ? $this->tableFieldOption('merchant_company_name', '가맹점명', '기초정보', 'system_clients', 'company_name')
                : null,
            ($isCashReceipt || $isCardHometax)
                ? $this->tableFieldOption('merchant_business_type', '업태', '기초정보', 'system_clients', 'business_type')
                : null,
            ($isCashReceipt || $isCardHometax || $isCardCompany)
                ? $this->tableFieldOption('merchant_business_category', '업종', '기초정보', 'system_clients', 'business_category')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('merchant_address1', '가맹점주소1', '기초정보', 'system_clients', 'address')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('merchant_address2', '가맹점주소2', '기초정보', 'system_clients', 'address_detail')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('merchant_phone', '가맹점전화번호', '기초정보', 'system_clients', 'phone')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('headquarters_name', '본부명', '기초정보', 'system_company', 'company_name_ko')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('department_name', '부서명', '기초정보', 'user_departments', 'dept_name')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('supplier_business_number', '공급자 사업자등록번호', '기초정보', 'system_clients', 'business_number')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('supplier_company_name', '공급자 상호', '기초정보', 'system_clients', 'company_name')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('supplier_ceo_name', '공급자 대표자명', '기초정보', 'system_clients', 'ceo_name')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('supplier_address', '공급자 주소', '기초정보', 'system_clients', 'address')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('supplier_email', '공급자 이메일', '기초정보', 'system_clients', 'email')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('customer_business_number', '공급받는자 사업자등록번호', '기초정보', 'system_clients', 'business_number')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('customer_company_name', '공급받는자 상호', '기초정보', 'system_clients', 'company_name')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('customer_ceo_name', '공급받는자 대표자명', '기초정보', 'system_clients', 'ceo_name')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('customer_address', '공급받는자 주소', '기초정보', 'system_clients', 'address')
                : null,
            $isTaxInvoiceLike
                ? $this->tableFieldOption('customer_email_1', '공급받는자 이메일1', '기초정보', 'system_clients', 'email')
                : null,
            $this->tableFieldOption('project_name', '프로젝트명', '기초정보', 'system_projects', $this->firstExistingColumn('system_projects', ['project_name', 'project_code'])),
            !$isManualTaxInvoice && !$isCashReceipt && !$isCardHometax && !$isCardCompany && $dataType !== 'TAX_INVOICE'
                ? $this->tableFieldOption('employee_name', '직원명', '기초정보', 'user_employees', $this->firstExistingColumn('user_employees', ['employee_name', 'name']))
                : null,
            !$isManualTaxInvoice && !$isCashReceipt && !$isCardHometax && !$isCardCompany && $dataType !== 'TAX_INVOICE'
                ? $this->tableFieldOption('bank_account_name', '계좌명', '기초정보', 'system_bank_accounts', $this->firstExistingColumn('system_bank_accounts', ['account_name', 'bank_account_name', 'account_number']))
                : null,
            ($isCardHometax || $isCardCompany)
                ? $this->tableFieldOption('card_number', '카드번호', '기초정보', 'system_cards', 'card_number')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('payment_account_number', '결제계좌번호', '기초정보', 'system_bank_accounts', 'account_number')
                : null,
            $isCardCompany
                ? $this->tableFieldOption('payment_bank_name', '결제계좌은행명', '기초정보', 'system_bank_accounts', 'bank_name')
                : null,
            !$isManualTaxInvoice && !$isCashReceipt && !$isCardHometax && $dataType !== 'TAX_INVOICE'
                ? $this->tableFieldOption('card_name', '카드명', '기초정보', 'system_cards', $this->firstExistingColumn('system_cards', ['card_name', 'card_number']))
                : null,
        ];

        return array_values(array_filter($fields));
    }

    private function fixedFieldOption(string $value, string $label, string $group, string $table, string $column, string $dataType = 'varchar'): array
    {
        return [
            'value' => $value,
            'label' => $label,
            'group' => $group,
            'table' => $table,
            'column' => $column,
            'data_type' => $dataType,
            'is_nullable' => 'YES',
            'ordinal_position' => 9000,
        ];
    }

    private function tableFieldOption(
        string $value,
        string $label,
        string $group,
        string $table,
        ?string $column,
        string $fallbackType = 'varchar',
        array $meta = []
    ): ?array {
        if ($column === null || $column === '') {
            return null;
        }

        $info = $this->columnInfo($table, $column);

        return [
            'value' => $value,
            'label' => $label,
            'group' => $group,
            'table' => $table,
            'column' => $column,
            'data_type' => (string) ($info['DATA_TYPE'] ?? $fallbackType),
            'is_nullable' => (string) ($info['IS_NULLABLE'] ?? 'YES'),
            'ordinal_position' => (int) ($info['ORDINAL_POSITION'] ?? 9000),
        ] + $meta;
    }

    private static function fieldGroupLabel(string $tableName): string
    {
        $labels = [
            'ledger_evidence_bank_transaction' => '입출금(은행)원본',
            'ledger_evidence_tax_invoice' => '세금계산서매입매출(홈택스)원본',
            'ledger_evidence_tax_invoice_manual' => '세금계산서매입매출(수기)원본',
            'ledger_evidence_cash_receipt' => '현금영수증 원본',
            'ledger_evidence_card_hometax' => '카드 원본',
            'ledger_data_evidences' => '통합증빙 원본컬럼',
            'ledger_evidence_bank' => '입출금(은행)원본',
        ];
        $labels['ledger_evidence_card_statement'] = '카드(카드사)원본';
        if (isset($labels[$tableName])) {
            return $labels[$tableName];
        }

        if ($tableName === 'ledger_evidence_bank_transaction') {
            return '입출금(은행)원본';
        }
        if ($tableName === 'ledger_evidence_tax_invoice') {
            return '세금계산서 원본';
        }
        if ($tableName === 'ledger_evidence_cash_receipt') {
            return '현금영수증 원본';
        }
        if ($tableName === 'ledger_evidence_card_hometax') {
            return '카드 원본';
        }

        if ($tableName === 'ledger_evidence_tax_invoice_manual') {
            return '?멸툑怨꾩궛?쒕ℓ?낅ℓ異??섍린)?먮낯';
        }
        return match ($tableName) {
            'ledger_data_evidences' => '통합증빙 원본컬럼',
            'ledger_evidence_bank' => '입출금(은행)원본',
            default => $tableName,
        };
    }

    private static function physicalFieldGroupLabel(string $dataType, string $tableName, string $columnName): string
    {
        if (in_array($tableName, [
            'ledger_evidence_bank_transaction',
            'ledger_evidence_tax_invoice',
            'ledger_evidence_tax_invoice_manual',
            'ledger_evidence_cash_receipt',
            'ledger_evidence_card_hometax',
            'ledger_evidence_card_statement',
        ], true)) {
            return self::originalFieldGroupLabel($dataType);
        }

        if ($tableName === 'ledger_data_evidences' && in_array($columnName, ['currency'], true)) {
            return '기준정보';
        }
        if ($tableName === 'ledger_data_evidences') {
            return self::originalFieldGroupLabel($dataType);
        }
        if ($tableName === 'ledger_evidence_bank') {
            return self::originalFieldGroupLabel($dataType);
        }

        return self::fieldGroupLabel($tableName);
    }

    private static function originalFieldGroupLabel(string $dataType): string
    {
        $dataType = strtoupper(trim($dataType));
        $dataType = self::LEGACY_DATA_TYPE_MAP[$dataType] ?? $dataType;

        $labels = [
            'BANK_TRANSACTION' => '입출금(은행)원본',
            'TAX_INVOICE' => '세금계산서매입매출(홈택스)원본',
            'CASH_RECEIPT' => '현금영수증(홈택스)원본',
            'CARD_HOMETAX' => '카드(홈택스)원본',
            'CARD_STATEMENT' => '카드(카드사)원본',
            'CARD_APPROVAL' => '카드(카드사)원본',
            'CARD' => '카드(카드사)원본',
        ];
        if (self::isManualTaxInvoiceTypeCode($dataType)) {
            return '세금계산서매입매출(수기)원본';
        }
        if (isset($labels[$dataType])) {
            return $labels[$dataType];
        }

        if (self::isManualTaxInvoiceTypeCode($dataType)) {
            return '세금계산서매입매출(수기)원본';
        }

        return match ($dataType) {
            'BANK_TRANSACTION' => '입출금(은행)원본',
            'TAX_INVOICE' => '세금계산서(홈택스)원본',
            'CASH_RECEIPT' => '현금영수증(홈택스)원본',
            'CARD_HOMETAX' => '카드(홈택스)원본',
            'CARD_STATEMENT', 'CARD_APPROVAL', 'CARD' => '카드(카드사)원본',
            default => '통합증빙 원본컬럼',
        };
    }

    private function mappedPayloadFieldOptions(string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        if ($this->isManualTaxInvoiceDataType($dataType)) {
            return $this->manualTaxInvoiceFieldOptions($dataType);
        }

        if (!in_array($dataType, self::DATA_EVIDENCE_TYPES, true)) {
            return [];
        }

        $fieldsByName = [];
        foreach (self::MAPPED_PAYLOAD_FIELDS as $field) {
            $fieldName = (string) ($field['value'] ?? '');
            if ($dataType !== 'BANK_TRANSACTION' && $fieldName === 'operation_type') {
                continue;
            }
            $fieldsByName[$fieldName] = $field;
        }
        if ($this->isManualTaxInvoiceDataType($dataType)) {
            unset($fieldsByName['employee_name'], $fieldsByName['bank_account_name'], $fieldsByName['card_name']);
        }
        if (!$this->dataTypeUsesCurrency($dataType)) {
            unset($fieldsByName['currency_code'], $fieldsByName['exchange_rate']);
        }
        if ($dataType !== 'BANK_TRANSACTION') {
            unset($fieldsByName['bank_direction']);
            unset($fieldsByName['currency_code']);
        }
        if (!in_array($dataType, ['BANK_TRANSACTION', 'CARD_HOMETAX'], true)) {
            unset($fieldsByName['note']);
        }
        $orderedNames = $this->mappedPayloadFieldOrder($dataType);
        $orderedFields = [];
        foreach ($orderedNames as $name) {
            if (isset($fieldsByName[$name])) {
                $orderedFields[] = $fieldsByName[$name];
                unset($fieldsByName[$name]);
            }
        }
        if ($dataType === 'BANK_TRANSACTION') {
            return array_map(static fn(array $field): array => $field + [
                'table' => 'mapped_payload_json',
                'data_type' => 'json',
                'is_nullable' => 'YES',
                'ordinal_position' => 10000,
            ], array_map(fn(array $field): array => $this->normalizeMappedPayloadFieldGroup($dataType, $field), $orderedFields));
        }
        return array_map(static fn(array $field): array => $field + [
            'table' => 'mapped_payload_json',
            'data_type' => 'json',
            'is_nullable' => 'YES',
            'ordinal_position' => 10000,
        ], array_map(fn(array $field): array => $this->normalizeMappedPayloadFieldGroup($dataType, $field), $orderedFields));
    }

    private function normalizeMappedPayloadFieldGroup(string $dataType, array $field): array
    {
        $value = (string) ($field['value'] ?? '');
        $dataType = $this->normalizeDataType($dataType);

        if (in_array($value, ['transaction_date', 'currency_code', 'exchange_rate', 'business_unit', 'operation_type', 'transaction_direction'], true)) {
            $field['group'] = '기준정보';
            return $field;
        }

        if (in_array($value, [
            'client_name',
            'client_company_name',
            'company_name',
            'business_type',
            'business_category',
            'user_name',
            'merchant_business_number',
            'merchant_company_name',
            'merchant_business_type',
            'merchant_business_category',
            'supplier_business_number',
            'supplier_company_name',
            'supplier_ceo_name',
            'supplier_address',
            'supplier_email',
            'customer_business_number',
            'customer_company_name',
            'customer_ceo_name',
            'customer_address',
            'customer_email_1',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
            'card_number',
        ], true)) {
            $field['group'] = '기초정보';
            return $field;
        }

        $field['group'] = $dataType === 'BANK_TRANSACTION'
            ? '입출금(은행)원본'
            : self::originalFieldGroupLabel($dataType);

        return $field;
    }

    private function manualTaxInvoiceFieldOptions(string $dataType): array
    {
        $group = self::originalFieldGroupLabel($dataType);
        $clientGroup = '기초정보';
        $fields = [
            ['supplier_business_number', '공급자 사업자등록번호'],
            ['supplier_company_name', '공급자 상호'],
            ['supplier_ceo_name', '공급자 대표자명'],
            ['supplier_address', '공급자 주소'],
            ['customer_business_number', '공급받는자 사업자등록번호'],
            ['customer_company_name', '공급받는자 상호'],
            ['customer_ceo_name', '공급받는자 대표자명'],
            ['customer_address', '공급받는자 주소'],
            ['note', '비고'],
            ['receipt_claim_type', '영수/청구구분'],
            ['raw_item_date', '품목일자'],
            ['raw_item_name', '품목명'],
            ['raw_item_spec', '품목규격'],
            ['raw_item_quantity', '품목수량'],
            ['raw_item_unit_price', '품목단가'],
            ['raw_item_supply_amount', '품목공급가액'],
            ['raw_item_tax_amount', '품목세액'],
            ['raw_item_note', '품목비고'],
        ];

        $clientColumns = [
            'supplier_business_number' => 'business_number',
            'supplier_company_name' => 'company_name',
            'supplier_ceo_name' => 'ceo_name',
            'supplier_address' => 'address',
            'customer_business_number' => 'business_number',
            'customer_company_name' => 'company_name',
            'customer_ceo_name' => 'ceo_name',
            'customer_address' => 'address',
        ];

        return array_values(array_filter(array_map(
            function (array $field) use ($group, $clientGroup, $clientColumns): ?array {
                $fieldName = (string) $field[0];
                if (isset($clientColumns[$fieldName])) {
                    $option = $this->tableFieldOption(
                        $fieldName,
                        (string) $field[1],
                        $clientGroup,
                        'system_clients',
                        $clientColumns[$fieldName]
                    );

                    return $option !== null ? array_replace($option, ['ordinal_position' => 10000]) : null;
                }

                return $this->fixedFieldOption(
                    $fieldName,
                    (string) $field[1],
                    $group,
                    'mapped_payload_json',
                    $fieldName,
                    'json'
                ) + ['ordinal_position' => 10000];
            },
            $fields
        )));
    }

    private function dataTypeUsesCurrency(string $dataType): bool
    {
        if ($this->isManualTaxInvoiceDataType($dataType)) {
            return false;
        }

        return !in_array($this->normalizeDataType($dataType), [
            'BANK_TRANSACTION',
            'TAX_INVOICE',
            'CARD_HOMETAX',
            'CASH_RECEIPT',
        ], true);
    }

    private function isManualTaxInvoiceDataType(string $dataType): bool
    {
        return self::isManualTaxInvoiceTypeCode($this->normalizeDataType($dataType));
    }

    private static function isManualTaxInvoiceTypeCode(string $dataType): bool
    {
        $type = strtoupper(trim($dataType));
        if ($type === '') {
            return false;
        }

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
            str_contains($compact, '세금계산서')
            && str_contains($compact, '수기')
        );
    }

    private function mappedPayloadFieldOrder(string $dataType): array
    {
        $common = [
            'transaction_date',
            'currency_code',
            'exchange_rate',
            'business_unit',
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
            'description',
        ];
        $cardCompanyCommon = [
            'transaction_date',
            'currency_code',
            'exchange_rate',
            'business_unit',
            'transaction_direction',
            'client_name',
            'project_name',
            'card_name',
        ];
        $taxCommon = [
            'transaction_date',
            'business_unit',
            'transaction_direction',
            'client_name',
            'project_name',
            'description',
        ];
        $cashCommon = [
            'transaction_date',
            'business_unit',
            'transaction_direction',
            'client_name',
            'project_name',
        ];
        $cardHometaxCommon = [
            'transaction_date',
            'business_unit',
            'transaction_direction',
            'source_card_company_name',
            'client_name',
            'merchant_business_number',
            'merchant_company_name',
            'merchant_business_type',
            'merchant_business_category',
            'project_name',
            'card_number',
        ];
        $bank = [
            'transaction_date',
            'bank_direction',
            'business_unit',
            'operation_type',
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
            'total_amount',
            'balance_amount',
            'description',
            'counterparty_name',
            'memo',
            'note',
        ];
        $taxInvoice = [
            'issue_date',
            'transmit_date',
            'tax_invoice_category',
            'tax_invoice_type',
            'issue_type',
            'receipt_claim_type',
            'supplier_branch_number',
            'customer_branch_number',
            'customer_email_2',
        ];
        $merchant = [
            'merchant_business_number',
            'merchant_zip_code',
            'merchant_address2',
        ];
        $cardHometaxMerchant = [
            'merchant_type',
        ];
        $cardBase = [
            'receive_info_type_code',
            'designee_korean_name',
            'domestic_foreign_type',
            'sales_type',
            'amount_sign_code',
            'accounting_code',
            'accounting_code_name',
            'purchase_amount_krw',
            'previous_notice_amount',
            'card_number',
            'card_type',
            'payment_account_number',
            'payment_bank_name',
            'installment_period',
            'installment_sequence',
        ];
        $cardAmounts = [
            'service_amount',
            'withholding_amount',
            'billing_date',
            'billing_amount',
            'fee_amount',
            'actual_billing_amount',
            'foreign_amount',
            'local_amount',
            'foreign_country_code',
            'foreign_country_name',
            'foreign_city_name',
        ];
        $cashAndCardHometax = [
            'purchase_datetime',
            'issue_method',
            'deduction_status',
            'service_amount',
            'withholding_amount',
        ];
        $cashReceipt = [
            'merchant_industry_code',
        ];
        $cardHometax = [
            'purchase_datetime',
            'deduction_status',
            'service_amount',
            'withholding_amount',
            'note',
        ];
        $transactionLine = [
            'raw_item_date',
            'raw_item_name',
            'raw_item_spec',
            'raw_item_quantity',
            'raw_item_unit_price',
            'raw_item_supply_amount',
            'raw_item_tax_amount',
            'raw_item_note',
        ];
        return match ($dataType) {
            'BANK_TRANSACTION' => $bank,
            'TAX_INVOICE' => array_merge($taxCommon, $taxInvoice, $transactionLine),
            'CARD_STATEMENT', 'CARD', 'CARD_APPROVAL' => array_merge($cardCompanyCommon, $cardBase, $merchant, $cardAmounts),
            'CARD_HOMETAX' => array_merge($cardHometaxCommon, $cardHometax, $cardHometaxMerchant),
            'CASH_RECEIPT' => array_merge($cashCommon, $cashAndCardHometax, $cashReceipt),
            default => $common,
        };
    }

    private function mergeFieldOptions(string $dataType, array ...$groups): array
    {
        $dataType = $this->normalizeDataType($dataType);
        $merged = [];
        foreach ($groups as $fields) {
            foreach ($fields as $field) {
                $value = (string) ($field['value'] ?? '');
                $allowHiddenField = in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true) && $value === 'billing_date';
                $allowDeprecatedField = ($dataType === 'BANK_TRANSACTION' || $dataType === 'CARD_HOMETAX' || $this->isManualTaxInvoiceDataType($dataType)) && $value === 'note';
                if ($value === ''
                    || isset($merged[$value])
                    || (!$allowHiddenField && in_array($value, self::FORMAT_HIDDEN_FIELDS, true))
                    || (!$allowDeprecatedField && in_array($value, self::FORMAT_DEPRECATED_FIELDS, true))
                ) {
                    continue;
                }
                $merged[$value] = $field;
            }
        }

        return array_values($merged);
    }

    public function fieldNames(string $dataType): array
    {
        return array_map(
            static fn(array $field): string => (string) $field['value'],
            $this->fieldOptions($dataType)
        );
    }

    public function isValidField(string $dataType, string $fieldName): bool
    {
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            return false;
        }

        return in_array($fieldName, $this->fieldNames($dataType), true);
    }
    private function firstExistingColumn(string $tableName, array $columnNames): ?string
    {
        foreach ($columnNames as $columnName) {
            $columnName = (string) $columnName;
            if ($columnName !== '' && $this->columnExists($tableName, $columnName)) {
                return $columnName;
            }
        }

        $first = reset($columnNames);
        return $first === false ? null : (string) $first;
    }

    private function columnInfo(string $tableName, string $columnName): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, ORDINAL_POSITION, COLUMN_COMMENT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
                LIMIT 1
            ");
            $stmt->execute([
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
                LIMIT 1
            ");
            $stmt->execute([
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND INDEX_NAME = :index_name
                LIMIT 1
            ");
            $stmt->execute([
                ':table_name' => $tableName,
                ':index_name' => $indexName,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeDataType(string $dataType): string
    {
        $dataType = strtoupper(trim($dataType));
        return self::LEGACY_DATA_TYPE_MAP[$dataType] ?? $dataType;
    }
}
