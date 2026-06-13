<?php

namespace App\Services\System;

class ClientPayloadService
{
    public function normalizePayload(array $input): array
    {
        return [
            'id' => $input['id'] ?? null,
            'sort_no' => $input['sort_no'] ?? null,
            'client_name' => trim((string) ($input['client_name'] ?? '')),
            'company_name' => $input['company_name'] ?? null,
            'registration_date' => $input['registration_date'] ?? null,
            'business_number' => isset($input['business_number']) && trim((string) $input['business_number']) !== ''
                ? trim((string) $input['business_number'])
                : null,
            'rrn' => isset($input['rrn']) && trim((string) $input['rrn']) !== ''
                ? trim((string) $input['rrn'])
                : null,
            'business_type' => $input['business_type'] ?? null,
            'business_category' => $input['business_category'] ?? null,
            'business_status' => $input['business_status'] ?? null,
            'address' => $input['address'] ?? null,
            'address_detail' => $input['address_detail'] ?? null,
            'phone' => $input['phone'] ?? null,
            'fax' => $input['fax'] ?? null,
            'email' => $input['email'] ?? null,
            'ceo_name' => $input['ceo_name'] ?? null,
            'ceo_phone' => $input['ceo_phone'] ?? null,
            'manager_name' => $input['manager_name'] ?? null,
            'manager_phone' => $input['manager_phone'] ?? null,
            'homepage' => $input['homepage'] ?? null,
            'bank_name' => $input['bank_name'] ?? null,
            'account_number' => $input['account_number'] ?? null,
            'account_holder' => $input['account_holder'] ?? null,
            'trade_category' => $input['trade_category'] ?? null,
            'default_account_id' => $input['default_account_id'] ?? null,
            'item_category' => $input['item_category'] ?? null,
            'client_category' => $input['client_category'] ?? null,
            'client_type' => $input['client_type'] ?? null,
            'tax_type' => $input['tax_type'] ?? null,
            'payment_term' => $input['payment_term'] ?? null,
            'client_grade' => $input['client_grade'] ?? null,
            'note' => $input['note'] ?? null,
            'memo' => $input['memo'] ?? null,
            'delete_business_certificate' => $input['delete_business_certificate'] ?? '0',
            'delete_rrn_image' => $input['delete_rrn_image'] ?? '0',
            'delete_bank_file' => $input['delete_bank_file'] ?? '0',
            'is_active' => isset($input['is_active']) ? (int) $input['is_active'] : 1,
        ];
    }

    public function validatePayload(array $payload): array
    {
        if (($payload['client_name'] ?? '') === '') {
            return [
                'success' => false,
                'message' => '거래처명을 입력해주세요.',
            ];
        }

        return ['success' => true];
    }

    public function mapSaveErrorMessage(string $message): string
    {
        if (
            str_contains($message, 'Duplicate entry') &&
            str_contains($message, 'uq_business_number')
        ) {
            return '사업자등록번호가 이미 등록되어 있습니다.';
        }

        return $message !== '' ? $message : '저장 중 오류가 발생했습니다.';
    }

    public function normalizeNullableClientFields(array $data): array
    {
        $nullableFields = [
            'business_number',
            'rrn',
            'company_name',
            'registration_date',
            'business_type',
            'business_category',
            'business_status',
            'ceo_name',
            'ceo_phone',
            'manager_name',
            'manager_phone',
            'phone',
            'fax',
            'email',
            'homepage',
            'address',
            'address_detail',
            'bank_name',
            'account_number',
            'account_holder',
            'trade_category',
            'default_account_id',
            'item_category',
            'client_category',
            'client_type',
            'tax_type',
            'payment_term',
            'client_grade',
            'note',
            'memo',
        ];

        foreach ($nullableFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            $value = trim((string) $data[$field]);
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }
}
