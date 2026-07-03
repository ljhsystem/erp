<?php

namespace App\Services\Ledger;

class EvidenceRuleEngineService
{
    public function __construct(
        private $normalizeDataType,
        private $processingPlanForDataType,
        private $normalizeBankTransactionPayload,
        private $businessRefIdForStorage,
        private $hasVoucherLinesPayload,
        private $transactionDirectionForStorage,
        private $resolveUploadTransactionContext,
        private $amountOrNull,
        private $dateValueOrNull,
        private $normalizeBusinessNumber,
        private $cleanCompanyName,
        private $isEmptySelectionLabel
    ) {
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }

    private function processingPlanForDataType(string $dataType): array
    {
        return ($this->processingPlanForDataType)($dataType);
    }

    private function normalizeBankTransactionPayload(array $payload): array
    {
        return ($this->normalizeBankTransactionPayload)($payload);
    }

    private function businessRefIdForStorage(string $refType, array $payload): ?string
    {
        return ($this->businessRefIdForStorage)($refType, $payload);
    }

    private function hasVoucherLinesPayload(array $payload): bool
    {
        return ($this->hasVoucherLinesPayload)($payload);
    }

    private function transactionDirectionForStorage(string $direction, array $payload, string $dataType): string
    {
        return ($this->transactionDirectionForStorage)($direction, $payload, $dataType);
    }

    private function resolveUploadTransactionContext(array $payload, string $dataType): array
    {
        return ($this->resolveUploadTransactionContext)($payload, $dataType);
    }

    private function amountOrNull(mixed $value): ?float
    {
        return ($this->amountOrNull)($value);
    }

    private function dateValueOrNull(mixed $value): ?string
    {
        return ($this->dateValueOrNull)($value);
    }

    private function normalizeBusinessNumber(mixed $value): string
    {
        return ($this->normalizeBusinessNumber)($value);
    }

    private function cleanCompanyName(string $value): string
    {
        return ($this->cleanCompanyName)($value);
    }

    private function isEmptySelectionLabel(string $value): bool
    {
        return ($this->isEmptySelectionLabel)($value);
    }

    public function readinessForEvidenceRow(array $row, array $payload): array
    {
        $dataType = $this->normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? $payload['import_type'] ?? ''));
        $processing = $this->processingPlanForDataType($dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            $payload = $this->normalizeBankTransactionPayload($payload);
        }
        if (in_array($processing['type'], ['BUSINESS_DATA', 'UNSUPPORTED'], true)) {
            return $this->readinessResult(
                ['현재 자료유형은 증빙 생성 대상이 아닙니다. 업로드 형식과 자료유형 설정을 확인해 주세요.'],
                [],
                ['import_type'],
                $processing
            );
        }

        $missing = [];
        $errors = [];
        $warnings = [];
        $context = [];
        $require = function (string $field, mixed $value, string $message) use (&$missing, &$errors): void {
            $text = trim((string) ($value ?? ''));
            if ($text === '' || $this->isEmptySelectionLabel($text)) {
                $missing[] = $field;
                $errors[] = $message;
            }
        };

        $require('source_type', $dataType, '자료유형이 없습니다. 자료유형을 확인해 주세요.');
        $require('source_key', $this->policyFieldValue('source_key', $row, $payload, $context, $dataType), '원본 식별값이 없습니다. 승인번호 또는 외부 원본 식별값을 확인해 주세요.');
        $require('evidence_date', $this->policyFieldValue('evidence_date', $row, $payload, $context, $dataType), '증빙일자가 없습니다.');

        if ($processing['type'] === 'VERIFY_ONLY') {
            if ($this->amountOrNull(
                $payload['billing_amount']
                ?? $payload['claim_amount']
                ?? $payload['actual_billing_amount']
                ?? $payload['purchase_amount_krw']
                ?? $payload['total_amount']
                ?? $payload['supply_amount']
                ?? null
            ) === null) {
                $missing[] = 'billing_amount';
                $errors[] = '금액 후보 정보가 없습니다. 청구금액 또는 금액을 확인해 주세요.';
            }

            $result = $this->readinessResult($errors, $warnings, $missing, $processing);
            if ($result['status'] === 'READY') {
                $result['status'] = 'VERIFY_ONLY';
            }

            return $this->applyPolicyResult($result, $row, $payload, $context, $dataType);
        }

        if ($processing['type'] === 'BANK_FLOW') {
            if ($this->amountOrNull($payload['deposit_amount'] ?? null) === null && $this->amountOrNull($payload['withdraw_amount'] ?? null) === null) {
                $missing[] = 'deposit_amount';
                $missing[] = 'withdraw_amount';
                $errors[] = '입금 또는 출금 금액이 없습니다.';
            }

            $bankProcessing = $processing;
            $bankProcessing['target'] = 'VOUCHER_WAITING';
            if ($this->hasVoucherLinesPayload($payload)) {
                $bankProcessing['objects'] = ['BANK_FLOW', 'VOUCHER_HEADER', 'VOUCHER_LINE'];
                $bankProcessing['label'] = '입출금 전표 생성 대기';
            } else {
                $bankProcessing['objects'] = ['BANK_FLOW'];
                $bankProcessing['label'] = '입출금 흐름 확인';
            }

            return $this->applyPolicyResult($this->readinessResult($errors, $warnings, $missing, $bankProcessing), $row, $payload, $context, $dataType);
        }

        try {
            $context = $this->resolveUploadTransactionContext($payload, $dataType);
        } catch (\Throwable) {
            $context = [];
        }

        $clientId = trim((string) ($payload['client_id'] ?? ''));
        if ($this->isEmptySelectionLabel($clientId)) {
            $clientId = '';
        }
        $clientBusinessNumber = $this->normalizeBusinessNumber((string) ($context['client_business_number'] ?? $payload['client_business_number'] ?? $payload['business_number'] ?? ''));
        $clientName = $this->cleanCompanyName((string) ($context['client_company_name'] ?? $payload['client_company_name'] ?? $payload['company_name'] ?? $payload['counterparty_name'] ?? ''));
        if ($this->isEmptySelectionLabel($clientName)) {
            $clientName = '';
        }
        if ($clientId === '' && $clientBusinessNumber === '' && $clientName === '') {
            $missing[] = 'client_id';
            $errors[] = '거래처 식별 정보가 없습니다. 거래처를 확인해 주세요.';
        }

        if (in_array((string) ($processing['target'] ?? ''), ['TRANSACTION_FULL', 'TRANSACTION_AND_VOUCHER'], true)) {
            $itemName = trim((string) ($payload['item_name'] ?? ''));
            if ($itemName === '') {
                $warnings[] = '적요가 없습니다. 거래 생성 전 적요를 입력해 주세요.';
            }
        }

        return $this->applyPolicyResult($this->readinessResult($errors, $warnings, $missing, $processing), $row, $payload, $context, $dataType);
    }

    public function businessReadinessForEvidenceRow(array $row, array $payload): array
    {
        $dataType = $this->normalizeDataType((string) ($row['import_type'] ?? $row['source_type'] ?? $payload['import_type'] ?? ''));
        $processing = $this->processingPlanForDataType($dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            $payload = $this->normalizeBankTransactionPayload($payload);
        }

        return $this->applyBusinessPolicyResult($this->readinessResult([], [], [], $processing), $row, $payload);
    }

    public function readinessResult(array $errors, array $warnings, array $missing, array $processing): array
    {
        $missing = array_values(array_unique($missing));
        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));

        return [
            'status' => $errors === [] ? 'READY' : 'NOT_READY',
            'errors' => $errors !== [] ? $errors : $warnings,
            'missing_fields' => $missing,
            'processing_type' => $processing['type'],
            'processing_objects' => $processing['objects'],
            'processing_label' => $processing['label'],
            'generation_target' => $processing['target'] ?? $processing['type'],
            'generation_objects' => $processing['objects'],
            'generation_label' => $processing['label'],
        ];
    }

    private function applyPolicyResult(array $result, array $row, array $payload, array $context, string $dataType): array
    {
        $displayNameMap = $this->policyMap($payload['_column_display_name'] ?? ($_REQUEST['column_display_name'] ?? null));
        $requirementPolicyMap = $this->policyMap($payload['_column_requirement_policy'] ?? ($_REQUEST['column_requirement_policy'] ?? ($_REQUEST['column_requirement'] ?? null)));
        if ($requirementPolicyMap === []) {
            return $result;
        }

        $missingFields = is_array($result['missing_fields'] ?? null) ? $result['missing_fields'] : [];
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        foreach ($requirementPolicyMap as $key => $policy) {
            if (strtolower(trim((string) $policy)) !== 'required') {
                continue;
            }

            $field = trim((string) $key);
            if ($field === '' || $this->isBusinessStatusExcludedField($field)) {
                continue;
            }

            $text = trim((string) $this->policyFieldValue($field, $row, $payload, $context, $dataType));
            if ($text !== '' && !$this->isEmptySelectionLabel($text)) {
                continue;
            }

            $missingFields[] = $field;
            $label = trim((string) ($displayNameMap[$field] ?? $field));
            $errors[] = $label . ' 필수값이 없습니다.';
        }

        $missingFields = array_values(array_unique($missingFields));
        $errors = array_values(array_unique($errors));
        $result['missing_fields'] = $missingFields;
        if ($errors !== []) {
            $result['errors'] = $errors;
            $result['status'] = 'NOT_READY';
        }

        return $result;
    }

    private function applyBusinessPolicyResult(array $result, array $row, array $payload): array
    {
        $displayNameMap = $this->policyMap($payload['_column_display_name'] ?? ($_REQUEST['column_display_name'] ?? null));
        $requirementPolicyMap = $this->policyMap($payload['_column_requirement_policy'] ?? ($_REQUEST['column_requirement_policy'] ?? ($_REQUEST['column_requirement'] ?? null)));
        if ($requirementPolicyMap === []) {
            return $result;
        }

        $missingFields = is_array($result['missing_fields'] ?? null) ? $result['missing_fields'] : [];
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        foreach ($requirementPolicyMap as $key => $policy) {
            if (strtolower(trim((string) $policy)) !== 'required') {
                continue;
            }

            $field = trim((string) $key);
            if ($field === '') {
                continue;
            }

            $text = trim((string) $this->businessPolicyFieldValue($field, $row, $payload));
            if ($text !== '' && !$this->isEmptySelectionLabel($text)) {
                continue;
            }

            $missingFields[] = $field;
            $label = trim((string) ($displayNameMap[$field] ?? $field));
            $errors[] = $label . ' 필수값이 없습니다.';
        }

        $missingFields = array_values(array_unique($missingFields));
        $errors = array_values(array_unique($errors));
        $result['missing_fields'] = $missingFields;
        if ($errors !== []) {
            $result['errors'] = $errors;
            $result['status'] = 'NOT_READY';
        }

        return $result;
    }

    private function policyFieldValue(string $field, array $row, array $payload, array $context, string $dataType): mixed
    {
        $field = trim($field);
        if ($field === '') {
            return '';
        }

        $resolved = match ($field) {
            'source_type', 'import_type' => $dataType,
            'source_key', 'approval_number', 'approval_no' => $this->resolvedSourceKey($row, $payload),
            'evidence_date' => $this->resolvedEvidenceDate($row, $payload, $dataType),
            'transaction_date' => $this->resolvedTransactionDate($row, $payload, $dataType),
            'transaction_direction' => $this->resolvedTransactionDirection($payload, $context, $dataType),
            'business_unit' => trim((string) ($payload['business_unit'] ?? $payload['business_unit_code'] ?? '')),
            'operation_type' => trim((string) ($payload['operation_type'] ?? '')),
            'bank_account_name', 'bank_account_id', 'bank_account' => $this->resolvedBankAccountValue($payload),
            'counterparty_name' => $this->resolvedCounterpartyName($payload),
            'merchant', 'merchant_company_name', 'merchant_name' => $this->resolvedMerchantValue($payload),
            'card_company', 'card_company_name', 'source_card_company_name' => $this->resolvedCardCompanyValue($payload),
            'client_id', 'client_name', 'client_company_name' => $this->resolvedClientIdentityValue($payload, $context),
            default => null,
        };

        if ($resolved !== null && !(is_string($resolved) && trim($resolved) === '')) {
            return $resolved;
        }

        if (array_key_exists($field, $payload)) {
            return $payload[$field];
        }
        if (array_key_exists($field, $row)) {
            return $row[$field];
        }

        return '';
    }

    private function businessPolicyFieldValue(string $field, array $row, array $payload): mixed
    {
        $field = trim($field);
        if ($field === '') {
            return '';
        }

        if (array_key_exists($field, $payload)) {
            return $payload[$field];
        }
        if (array_key_exists($field, $row)) {
            return $row[$field];
        }

        return '';
    }

    private function isBusinessStatusExcludedField(string $field): bool
    {
        return in_array($field, ['source_type', 'import_type', 'source_key', 'evidence_date'], true);
    }

    private function resolvedSourceKey(array $row, array $payload): string
    {
        return trim((string) ($row['source_key'] ?? $payload['source_key'] ?? $payload['approval_number'] ?? $payload['approval_no'] ?? ''));
    }

    private function resolvedEvidenceDate(array $row, array $payload, string $dataType): ?string
    {
        $candidate = $row['evidence_date'] ?? $payload['evidence_date'] ?? $payload['transaction_date'] ?? $payload['issue_date'] ?? null;
        if ($dataType === 'BANK_TRANSACTION' && ($candidate === null || trim((string) $candidate) === '')) {
            $candidate = $payload['raw_transaction_datetime'] ?? $payload['transaction_datetime'] ?? $payload['transaction_at'] ?? null;
        }

        return $this->dateValueOrNull($candidate);
    }

    private function resolvedTransactionDate(array $row, array $payload, string $dataType): ?string
    {
        $candidate = $payload['transaction_date'] ?? $row['evidence_date'] ?? $payload['evidence_date'] ?? null;
        if ($dataType === 'BANK_TRANSACTION' && ($candidate === null || trim((string) $candidate) === '')) {
            $candidate = $payload['raw_transaction_datetime'] ?? $payload['transaction_datetime'] ?? $payload['transaction_at'] ?? null;
        }

        return $this->dateValueOrNull($candidate);
    }

    private function resolvedTransactionDirection(array $payload, array $context, string $dataType): string
    {
        try {
            $direction = $this->transactionDirectionForStorage(
                (string) ($context['transaction_direction'] ?? $payload['transaction_direction'] ?? ''),
                $payload,
                $dataType
            );
        } catch (\Throwable) {
            return '';
        }

        return in_array($direction, ['EXPENSE', 'INCOME', 'FUND'], true) ? $direction : '';
    }

    private function resolvedBankAccountValue(array $payload): string
    {
        $value = $this->businessRefIdForStorage('ACCOUNT', $payload)
            ?? ($payload['bank_account_name'] ?? $payload['account_name'] ?? $payload['payment_account_name'] ?? $payload['bank_account'] ?? $payload['bank_account_id'] ?? '');

        return trim((string) $value);
    }

    private function resolvedCounterpartyName(array $payload): string
    {
        return trim((string) ($payload['counterparty_name'] ?? $payload['counterparty_account_holder_name'] ?? $payload['raw_counterparty_name'] ?? ''));
    }

    private function resolvedMerchantValue(array $payload): string
    {
        return trim((string) ($payload['merchant_company_name'] ?? $payload['merchant_name'] ?? $payload['client_company_name'] ?? $payload['company_name'] ?? ''));
    }

    private function resolvedCardCompanyValue(array $payload): string
    {
        return trim((string) ($payload['source_card_company_name'] ?? $payload['card_company'] ?? $payload['card_company_name'] ?? $payload['card_name'] ?? ''));
    }

    private function resolvedClientIdentityValue(array $payload, array $context): string
    {
        $clientId = trim((string) ($payload['client_id'] ?? ''));
        if (!$this->isEmptySelectionLabel($clientId)) {
            return $clientId;
        }

        $clientBusinessNumber = $this->normalizeBusinessNumber((string) ($context['client_business_number'] ?? $payload['client_business_number'] ?? $payload['business_number'] ?? ''));
        if ($clientBusinessNumber !== '') {
            return $clientBusinessNumber;
        }

        $clientName = $this->cleanCompanyName((string) ($context['client_company_name'] ?? $payload['client_company_name'] ?? $payload['company_name'] ?? $payload['counterparty_name'] ?? ''));

        return $this->isEmptySelectionLabel($clientName) ? '' : $clientName;
    }

    private function policyMap(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $parsed = json_decode($text, true);
        return is_array($parsed) ? $parsed : [];
    }

    public function formatTransactionCreateError(string $message, array $row = [], int $rowNo = 0): string
    {
        $message = trim($message);
        $isSqlParameterError = str_contains($message, 'SQLSTATE[HY093]') || str_contains($message, 'Invalid parameter number');

        if ($isSqlParameterError) {
            $clientName = $this->cleanCompanyName((string) (
                $row['client_company_name']
                ?? $row['customer_company_name']
                ?? $row['supplier_company_name']
                ?? $row['company_name']
                ?? ''
            ));
            $approvalNo = trim((string) ($row['approval_number'] ?? $row['approval_no'] ?? ''));

            $parts = [];
            if ($rowNo > 0) {
                $parts[] = $rowNo . '행';
            }
            if ($clientName !== '') {
                $parts[] = '거래처 ' . $clientName;
            }
            if ($approvalNo !== '') {
                $parts[] = '승인번호 ' . $approvalNo;
            }

            $context = $parts !== [] ? ' (' . implode(', ', $parts) . ')' : '';
            return '거래 생성 중 필수 파라미터가 누락되었습니다' . $context . '. 거래처, 승인번호, 금액 등 필수값을 확인해 주세요.';
        }

        if (str_contains($message, 'SQLSTATE[')) {
            $prefix = $rowNo > 0 ? $rowNo . '행 ' : '';
            return $prefix . '거래 생성 중 데이터베이스 오류가 발생했습니다. 입력값과 필수 항목을 확인해 주세요.';
        }

        return $message !== '' ? $message : '검증 중 오류가 발생했습니다.';
    }
}
