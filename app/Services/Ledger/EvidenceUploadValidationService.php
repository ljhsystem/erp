<?php

namespace App\Services\Ledger;

class EvidenceUploadValidationService
{
    public function __construct(private array $callbacks = [])
    {
    }

    public function enrichUploadRows(array $rows, string $dataType): array
    {
        foreach ($rows as &$row) {
            $this->normalizeUploadAmountFields($row);
            $context = $this->resolveUploadTransactionContext($row, $dataType);
            foreach ($context as $key => $value) {
                if ($value !== null && $value !== '') {
                    $row[$key] = $value;
                }
            }
        }
        unset($row);

        return $rows;
    }

    public function normalizeUploadAmountFields(array &$row): void
    {
        foreach ([
            'supply_amount',
            'vat_amount',
            'total_amount',
            'raw_supply_amount',
            'raw_vat_amount',
            'raw_total_amount',
            'raw_deposit_amount',
            'raw_withdraw_amount',
            'raw_balance_amount',
            'raw_check_bill_amount',
            'item_supply_amount',
            'item_vat_amount',
            'item_price',
            'item_qty',
            'service_amount',
            'purchase_amount_krw',
            'previous_notice_amount',
            'billing_amount',
            'fee_amount',
            'actual_billing_amount',
            'foreign_amount',
            'local_amount',
            'exchange_rate',
        ] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            if ($this->isBlankAmountPlaceholder($row[$field])) {
                $row[$field] = '';
                continue;
            }

            $amount = $this->amountOrNull($row[$field]);
            if ($amount !== null) {
                $row[$field] = $amount;
            }
        }

        $supply = $this->amountOrNull($row['supply_amount'] ?? null);
        $vat = $this->amountOrNull($row['vat_amount'] ?? null);
        $total = $this->amountOrNull($row['total_amount'] ?? null);
        $itemSupply = $this->amountOrNull($row['item_supply_amount'] ?? null);
        $itemVat = $this->amountOrNull($row['item_vat_amount'] ?? null);
        $service = $this->amountOrNull($row['service_amount'] ?? null);

        if ($supply === null && $itemSupply !== null) {
            $row['supply_amount'] = $itemSupply;
            $supply = $itemSupply;
        }
        if ($supply === null) {
            $rawSupply = $this->amountOrNull($row['raw_supply_amount'] ?? null);
            if ($rawSupply !== null) {
                $row['supply_amount'] = $rawSupply;
                $supply = $rawSupply;
            }
        }
        if ($vat === null && $itemVat !== null) {
            $row['vat_amount'] = $itemVat;
            $vat = $itemVat;
        }
        if ($vat === null) {
            $rawVat = $this->amountOrNull($row['raw_vat_amount'] ?? null);
            if ($rawVat !== null) {
                $row['vat_amount'] = $rawVat;
                $vat = $rawVat;
            }
        }
        if ($total === null) {
            $rawTotal = $this->amountOrNull($row['raw_total_amount'] ?? null);
            if ($rawTotal !== null) {
                $row['total_amount'] = $rawTotal;
                $total = $rawTotal;
            }
        }
        if ($total === null && ($supply !== null || $vat !== null || $service !== null)) {
            $row['total_amount'] = (float) ($supply ?? 0) + (float) ($vat ?? 0) + (float) ($service ?? 0);
        }
        if ($total === null) {
            foreach (['actual_billing_amount', 'billing_amount', 'purchase_amount_krw', 'foreign_amount', 'local_amount'] as $field) {
                $amount = $this->amountOrNull($row[$field] ?? null);
                if ($amount !== null) {
                    $row['total_amount'] = $amount;
                    break;
                }
            }
        }
    }

    public function validatePreviewRows(array $rows, array $columns, string $dataType = ''): array
    {
        return $this->validatePreviewRowsV2($rows, $columns, $dataType);
    }

    public function validatePreviewRowsV2(array $rows, array $columns, string $dataType = ''): array
    {
        $dataType = $this->normalizeDataType($dataType);

        foreach ($rows as &$row) {
            $errors = [];
            $warnings = [];
            $requiredMissingMessages = $this->requiredFormatMissingMessages($row, $columns);
            array_push($warnings, ...$requiredMissingMessages);

            if ($dataType === 'BANK_TRANSACTION') {
                $dateTime = trim((string) ($row['raw_transaction_datetime'] ?? ''));
                if ($dateTime !== '' && $this->dateTimeValue($dateTime) === null) {
                    $errors[] = '거래일시 형식이 올바르지 않습니다.';
                }
            } else {
                $date = trim((string) ($row['transaction_date'] ?? $row['issue_date'] ?? $row['raw_written_date'] ?? $row['raw_issue_date'] ?? ''));
                if ($date !== '' && !$this->isValidDateValue($date)) {
                    $errors[] = '거래일자 형식이 올바르지 않습니다.';
                }
            }

            $amountFields = $dataType === 'BANK_TRANSACTION'
                ? [
                    'raw_deposit_amount',
                    'raw_withdraw_amount',
                    'raw_balance_amount',
                    'raw_check_bill_amount',
                ]
                : [
                    'supply_amount',
                    'vat_amount',
                    'total_amount',
                    'raw_supply_amount',
                    'raw_vat_amount',
                    'raw_total_amount',
                    'item_supply_amount',
                    'item_vat_amount',
                    'service_amount',
                    'purchase_amount_krw',
                    'previous_notice_amount',
                    'billing_amount',
                    'fee_amount',
                    'actual_billing_amount',
                    'foreign_amount',
                    'local_amount',
                    'exchange_rate',
                ];
            foreach ($amountFields as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if (!$this->hasMeaningfulAmountValue($value)) {
                    continue;
                }

                if (!$this->isValidAmountValue($value)) {
                    $errors[] = $this->fieldLabel($field) . ' 형식이 올바르지 않습니다.';
                }
            }

            if (!empty($row['_direction_error'])) {
                $errors[] = (string) $row['_direction_error'];
            }

            array_push($warnings, ...$this->clientReferenceWarnings($row, $columns, $dataType));

            $status = 'ok';
            if ($errors !== []) {
                $status = 'error';
            } elseif ($warnings !== []) {
                $status = 'warning';
            }

            $row['_validation'] = [
                'status' => $status,
                'label' => ['ok' => '확인 완료', 'warning' => '확인 필요', 'error' => '오류'][$status],
                'messages' => array_values(array_merge($errors, $warnings)),
                'error_messages' => array_values($errors),
                'warning_messages' => array_values($warnings),
                'required_missing_count' => count($requiredMissingMessages),
                'required_missing_messages' => array_values($requiredMissingMessages),
            ];
        }
        unset($row);

        return $rows;
    }

    public function businessProjectRuleMessages(array $payload): array
    {
        $businessUnit = trim((string) $this->payloadScalarForStorage($payload['business_unit'] ?? $payload['business_unit_code'] ?? ''));
        $businessUnitKey = strtoupper((string) preg_replace('/\s+/u', '', $businessUnit));
        $projectId = trim((string) $this->payloadScalarForStorage($payload['project_id'] ?? ''));
        $projectName = trim((string) $this->payloadScalarForStorage($payload['project_name'] ?? ''));
        $hasProject = $projectId !== '' || $projectName !== '';

        if ($businessUnitKey === '') {
            return [];
        }

        $isHeadOffice = str_contains($businessUnitKey, 'HQ') || str_contains($businessUnit, '본사');
        $isConstruction = str_contains($businessUnitKey, 'CONSTRUCTION') || str_contains($businessUnit, '현장');

        if ($isHeadOffice && $hasProject) {
            return ['본사 사업부문은 프로젝트를 지정할 수 없습니다.'];
        }
        if ($isConstruction && !$hasProject) {
            return ['현장 사업부문은 프로젝트를 지정해야 합니다.'];
        }

        return [];
    }

    public function assertNoUploadValidationErrors(array $rows): void
    {
        foreach ($rows as $row) {
            $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
            if (($validation['status'] ?? '') !== 'error') {
                continue;
            }

            $rowNo = (int) ($row['_row_no'] ?? 0);
            $prefix = $rowNo > 0 ? "{$rowNo}행 " : '';
            $messages = array_values(array_filter(array_map('strval', is_array($validation['error_messages'] ?? null) ? $validation['error_messages'] : [])));
            $message = $messages !== [] ? implode(', ', array_slice($messages, 0, 5)) : '업로드 검증 중 오류가 발생했습니다.';
            throw new \RuntimeException($prefix . $message);
        }
    }

    private function clientReferenceWarnings(array $row, array $columns, string $dataType): array
    {
        $direction = strtoupper(trim((string) ($row['transaction_direction'] ?? '')));
        if (in_array($direction, ['INCOME', 'SALES', 'SALE', 'SELL', 'OUT_SALE'], true)) {
            return [];
        }

        $activeColumnKeys = $this->activeColumnKeys($columns);
        $businessNumberKeys = ['client_business_number', 'business_number'];
        $companyNameKeys = ['client_company_name', 'company_name', 'client_name'];

        if (
            !$this->hasAnyActiveColumn($activeColumnKeys, $businessNumberKeys)
            && !$this->hasAnyActiveColumn($activeColumnKeys, $companyNameKeys)
        ) {
            return [];
        }

        $businessNumber = $this->normalizeBusinessNumber((string) ($row['client_business_number'] ?? $row['business_number'] ?? ''));
        $companyName = $this->cleanCompanyName((string) ($row['client_company_name'] ?? $row['company_name'] ?? $row['client_name'] ?? ''));

        if ($businessNumber !== '' && $this->hasAnyActiveColumn($activeColumnKeys, $businessNumberKeys) && !$this->clientExistsByBusinessNumber($businessNumber)) {
            return ['등록된 거래처를 찾을 수 없습니다. 사업자등록번호를 확인해 주세요.'];
        }

        if ($companyName !== '' && $this->hasAnyActiveColumn($activeColumnKeys, $companyNameKeys) && $this->findClientId($companyName) === null) {
            return ['등록된 거래처를 찾을 수 없습니다. 거래처명을 확인해 주세요.'];
        }

        if ($businessNumber === '' && $companyName === '') {
            return ['거래처 정보가 비어 있습니다. 거래처 확인이 필요합니다.'];
        }

        return [];
    }

    private function isValidDateValue(mixed $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return strtotime($value) !== false;
    }

    private function isValidAmountValue(mixed $value): bool
    {
        return $this->amountOrNull($value) !== null;
    }

    private function hasMeaningfulAmountValue(mixed $value): bool
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return false;
        }

        return !$this->isBlankAmountPlaceholder($text);
    }

    private function isBlankAmountPlaceholder(mixed $value): bool
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return true;
        }

        $normalized = preg_replace('/[\s,\x{00A0}₩￦]/u', '', $text) ?? '';
        if ($normalized === '') {
            return true;
        }

        return preg_match('/^[-−–—―﹣－]+$/u', $normalized) === 1;
    }

    private function amountOrNull(mixed $value): ?float
    {
        return $this->call('amountOrNull', $value);
    }

    private function dateTimeValue(mixed $value): ?string
    {
        return $this->call('dateTimeValue', $value);
    }

    private function resolveUploadTransactionContext(array $row, string $dataType): array
    {
        return $this->call('resolveUploadTransactionContext', $row, $dataType);
    }

    private function normalizeDataType(string $dataType): string
    {
        return $this->call('normalizeDataType', $dataType);
    }

    private function requiredFormatMissingMessages(array $payload, array $columns): array
    {
        return $this->call('requiredFormatMissingMessages', $payload, $columns);
    }

    private function fieldLabel(string $field): string
    {
        return $this->call('fieldLabel', $field);
    }

    private function normalizeBusinessNumber(string $value): string
    {
        return $this->call('normalizeBusinessNumber', $value);
    }

    private function cleanCompanyName(string $value): string
    {
        return $this->call('cleanCompanyName', $value);
    }

    private function clientExistsByBusinessNumber(string $businessNumber): bool
    {
        return $this->call('clientExistsByBusinessNumber', $businessNumber);
    }

    private function findClientId(string $companyName): ?string
    {
        return $this->call('findClientId', $companyName);
    }

    private function payloadScalarForStorage(mixed $value): mixed
    {
        return $this->call('payloadScalarForStorage', $value);
    }

    private function activeColumnKeys(array $columns): array
    {
        $keys = [];

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }

            foreach (['key', 'source_key', 'payload_key', 'system_field_name', 'original_column_key', 'alias_of'] as $field) {
                $key = trim((string) ($column[$field] ?? ''));
                if ($key === '' || in_array($key, $keys, true)) {
                    continue;
                }

                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function hasAnyActiveColumn(array $activeColumnKeys, array $candidateKeys): bool
    {
        foreach ($candidateKeys as $candidateKey) {
            if (in_array($candidateKey, $activeColumnKeys, true)) {
                return true;
            }
        }

        return false;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
