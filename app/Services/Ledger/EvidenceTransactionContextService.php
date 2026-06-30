<?php

namespace App\Services\Ledger;

class EvidenceTransactionContextService
{
    /**
     * @param callable(string):string $normalizeDataType
     * @param callable(array):array $normalizeBankTransactionPayload
     * @param callable(string):string $normalizeTransactionDirection
     * @param callable(string,array,string):string $transactionDirectionForStorage
     * @param callable(array):string $bankCounterpartyName
     * @param callable():array $ownCompanyDefaultParty
     * @param callable(string):string $cleanCompanyName
     * @param callable(mixed):string $normalizeBusinessNumber
     * @param callable(array,string,?string):array $partyFromRow
     * @param callable(array):bool $isOwnCompanyParty
     * @param callable(string):bool $isManualTaxInvoiceDataType
     */
    public function __construct(
        private $normalizeDataType,
        private $normalizeBankTransactionPayload,
        private $normalizeTransactionDirection,
        private $transactionDirectionForStorage,
        private $bankCounterpartyName,
        private $ownCompanyDefaultParty,
        private $cleanCompanyName,
        private $normalizeBusinessNumber,
        private $partyFromRow,
        private $isOwnCompanyParty,
        private $isManualTaxInvoiceDataType
    ) {
    }

    public function resolveUploadTransactionContext(array $row, string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            $bankRow = $this->normalizeBankTransactionPayload($row);
            $direction = $this->normalizeTransactionDirection((string) ($bankRow['transaction_direction'] ?? ''));
            if ($direction === '') {
                $direction = $this->transactionDirectionForStorage('', $bankRow, $dataType);
            }

            return [
                'transaction_direction' => $direction,
                'transaction_type' => $this->transactionTypeForUpload($dataType),
                'client_business_number' => '',
                'client_company_name' => $this->bankCounterpartyName($bankRow),
                'own_business_number' => $this->ownCompanyDefaultParty()['business_number'] ?? '',
                'own_company_name' => $this->ownCompanyDefaultParty()['company_name'] ?? '',
                '_direction_error' => null,
            ];
        }

        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL', 'CARD_HOMETAX'], true)) {
            $merchantName = $this->cleanCompanyName((string) (
                $row['merchant_company_name']
                ?? $row['merchant_name']
                ?? $row['client_company_name']
                ?? $row['company_name']
                ?? ''
            ));

            return [
                'transaction_direction' => $this->normalizeTransactionDirection((string) ($row['transaction_direction'] ?? 'PURCHASE')) ?: 'PURCHASE',
                'transaction_type' => $this->transactionTypeForUpload($dataType),
                'client_business_number' => $this->normalizeBusinessNumber((string) ($row['merchant_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? '')),
                'client_company_name' => $merchantName,
                'own_business_number' => $this->ownCompanyDefaultParty()['business_number'] ?? '',
                'own_company_name' => $this->ownCompanyDefaultParty()['company_name'] ?? '',
                '_direction_error' => null,
            ];
        }

        $supplier = $this->partyFromRow($row, 'supplier');
        $customer = $this->partyFromRow($row, 'customer', 'recipient');
        $legacyClient = [
            'business_number' => $this->normalizeBusinessNumber((string) ($row['client_business_number'] ?? $row['business_number'] ?? '')),
            'company_name' => $this->cleanCompanyName((string) ($row['client_company_name'] ?? $row['company_name'] ?? '')),
        ];

        $supplierIsOwn = $this->isOwnCompanyParty($supplier);
        $customerIsOwn = $this->isOwnCompanyParty($customer);
        $isTaxInvoice = $dataType === 'TAX_INVOICE' || $this->isManualTaxInvoiceDataType($dataType);
        $direction = $this->normalizeTransactionDirection((string) ($row['transaction_direction'] ?? ''));
        $error = null;

        if ($customerIsOwn && !$supplierIsOwn) {
            $direction = 'PURCHASE';
            $client = $supplier;
            $own = $customer;
        } elseif ($supplierIsOwn && !$customerIsOwn) {
            $direction = 'SALES';
            $client = $customer;
            $own = $supplier;
        } else {
            if ($direction === '') {
                $direction = match ($dataType) {
                    'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE' => 'PURCHASE',
                    'CASH_RECEIPT_SALES' => 'SALES',
                    default => '',
                };
            }
            $client = $legacyClient;
            $own = $this->ownCompanyDefaultParty();

            if ($direction === 'PURCHASE' && $supplier['company_name'] . $supplier['business_number'] !== '') {
                $client = $supplier;
            } elseif ($direction === 'SALES' && $customer['company_name'] . $customer['business_number'] !== '') {
                $client = $customer;
            } elseif ($legacyClient['company_name'] . $legacyClient['business_number'] === '') {
                $client = $direction === 'SALES' ? $customer : $supplier;
            }

            if ($isTaxInvoice
                && ($supplier['company_name'] . $supplier['business_number'] !== '' || $customer['company_name'] . $customer['business_number'] !== '')
                && !$supplierIsOwn
                && !$customerIsOwn
            ) {
                $direction = $this->inferTaxInvoiceDirection($supplier, $customer, $legacyClient);
                $client = $direction === 'SALES' ? $customer : $supplier;
                if (($client['company_name'] ?? '') . ($client['business_number'] ?? '') === '') {
                    $client = $legacyClient;
                }
                $error = '거래처사 구분 실패: 공급자와 공급받는자 중 어느 쪽도 자사와 일치하는 값이 없습니다.';
            }
        }

        if ($direction === '') {
            $direction = $dataType === 'BANK_TRANSACTION'
                ? 'BANK'
                : ($isTaxInvoice ? 'PURCHASE' : 'GENERAL');
        }

        if (false && $error === null
            && in_array($direction, ['PURCHASE', 'SALES'], true)
            && (($client['company_name'] ?? '') . ($client['business_number'] ?? '')) === ''
        ) {
            $error = '상대 거래처사 구분 실패: 거래처로 사용할 공급자 또는 공급받는자 값이 없습니다.';
        }

        return [
            'transaction_direction' => $direction,
            'transaction_type' => $this->transactionTypeForUpload($dataType),
            'client_business_number' => $client['business_number'] ?? '',
            'client_company_name' => $client['company_name'] ?? '',
            'own_business_number' => $own['business_number'] ?? '',
            'own_company_name' => $own['company_name'] ?? '',
            '_direction_error' => $error,
        ];
    }

    public function transactionTypeForUpload(string $dataType): string
    {
        return match ($this->normalizeDataType($dataType)) {
            'IMPORT_INVOICE' => 'TRADE_IMPORT',
            'SHOPPING_ORDER' => 'SHOPPING',
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES', 'CARD_STATEMENT', 'CARD_APPROVAL', 'BANK_TRANSACTION', 'ETC' => 'GENERAL',
            default => 'GENERAL',
        };
    }

    private function normalizeDataType(string $dataType): string
    {
        return ($this->normalizeDataType)($dataType);
    }

    private function normalizeBankTransactionPayload(array $row): array
    {
        return ($this->normalizeBankTransactionPayload)($row);
    }

    private function normalizeTransactionDirection(string $direction): string
    {
        return ($this->normalizeTransactionDirection)($direction);
    }

    private function transactionDirectionForStorage(string $direction, array $row, string $dataType): string
    {
        return ($this->transactionDirectionForStorage)($direction, $row, $dataType);
    }

    private function bankCounterpartyName(array $row): string
    {
        return ($this->bankCounterpartyName)($row);
    }

    private function ownCompanyDefaultParty(): array
    {
        return ($this->ownCompanyDefaultParty)();
    }

    private function cleanCompanyName(string $name): string
    {
        return ($this->cleanCompanyName)($name);
    }

    private function normalizeBusinessNumber(mixed $value): string
    {
        return ($this->normalizeBusinessNumber)($value);
    }

    private function partyFromRow(array $row, string $prefix, ?string $fallbackPrefix = null): array
    {
        return ($this->partyFromRow)($row, $prefix, $fallbackPrefix);
    }

    private function isOwnCompanyParty(array $party): bool
    {
        return ($this->isOwnCompanyParty)($party);
    }

    private function isManualTaxInvoiceDataType(string $dataType): bool
    {
        return ($this->isManualTaxInvoiceDataType)($dataType);
    }

    private function inferTaxInvoiceDirection(array $supplier, array $customer, array $legacyClient): string
    {
        $supplierCompany = (string) ($supplier['company_name'] ?? '');
        $supplierBusiness = (string) ($supplier['business_number'] ?? '');
        $customerCompany = (string) ($customer['company_name'] ?? '');
        $customerBusiness = (string) ($customer['business_number'] ?? '');
        $legacyCompany = (string) ($legacyClient['company_name'] ?? '');
        $legacyBusiness = (string) ($legacyClient['business_number'] ?? '');

        if ($supplierCompany === '' && $supplierBusiness === '' && ($customerCompany !== '' || $customerBusiness !== '')) {
            return 'SALES';
        }

        if ($customerCompany === '' && $customerBusiness === '' && ($supplierCompany !== '' || $supplierBusiness !== '')) {
            return 'PURCHASE';
        }

        if ($legacyBusiness !== '' && $legacyBusiness === $supplierBusiness) {
            return 'PURCHASE';
        }

        if ($legacyBusiness !== '' && $legacyBusiness === $customerBusiness) {
            return 'SALES';
        }

        if ($legacyCompany !== '' && $legacyCompany === $supplierCompany) {
            return 'PURCHASE';
        }

        if ($legacyCompany !== '' && $legacyCompany === $customerCompany) {
            return 'SALES';
        }

        return 'PURCHASE';
    }
}
