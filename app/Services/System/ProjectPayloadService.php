<?php

namespace App\Services\System;

class ProjectPayloadService
{
    public function normalizePayload(array $input): array
    {
        return [
            'id' => $input['id'] ?? null,
            'sort_no' => $input['sort_no'] ?? null,
            'project_name' => trim((string) ($input['project_name'] ?? '')),
            'client_id' => $input['client_id'] ?? null,
            'employee_id' => $input['employee_id'] ?? null,
            'site_agent' => $input['site_agent'] ?? null,
            'contract_type' => $input['contract_type'] ?? null,
            'contract_method' => $input['contract_method'] ?? null,
            'director' => $input['director'] ?? null,
            'manager' => $input['manager'] ?? null,
            'business_type' => $input['business_type'] ?? null,
            'housing_type' => $input['housing_type'] ?? null,
            'construction_name' => $input['construction_name'] ?? null,
            'site_region_city' => $input['site_region_city'] ?? null,
            'site_region_district' => $input['site_region_district'] ?? null,
            'site_region_address' => $input['site_region_address'] ?? null,
            'site_region_address_detail' => $input['site_region_address_detail'] ?? null,
            'work_type' => $input['work_type'] ?? null,
            'work_subtype' => $input['work_subtype'] ?? null,
            'work_detail_type' => $input['work_detail_type'] ?? null,
            'contract_work_type' => $input['contract_work_type'] ?? null,
            'bid_type' => $input['bid_type'] ?? null,
            'client_name' => $input['client_name'] ?? null,
            'client_type' => $input['client_type'] ?? null,
            'permit_agency' => $input['permit_agency'] ?? null,
            'permit_date' => $input['permit_date'] ?? null,
            'contract_date' => $input['contract_date'] ?? null,
            'start_date' => $input['start_date'] ?? null,
            'completion_date' => $input['completion_date'] ?? null,
            'bid_notice_date' => $input['bid_notice_date'] ?? null,
            'initial_contract_amount' => $input['initial_contract_amount'] ?? null,
            'authorized_company_seal' => $input['authorized_company_seal'] ?? null,
            'note' => $input['note'] ?? null,
            'memo' => $input['memo'] ?? null,
            'delete_project_image' => $input['delete_project_image'] ?? '0',
            'is_active' => isset($input['is_active']) ? (int) $input['is_active'] : 1,
        ];
    }

    public function validatePayload(array $payload): array
    {
        if (($payload['project_name'] ?? '') === '') {
            return ['success' => false, 'message' => 'Project name is required.'];
        }

        foreach (['contract_date', 'start_date', 'completion_date'] as $field) {
            if (!empty($payload[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $payload[$field])) {
                return ['success' => false, 'message' => $field . ' must use YYYY-MM-DD format.'];
            }
        }

        if (!empty($payload['start_date']) && !empty($payload['completion_date']) && (string) $payload['start_date'] > (string) $payload['completion_date']) {
            return ['success' => false, 'message' => 'start_date cannot be later than completion_date.'];
        }

        if (($payload['initial_contract_amount'] ?? null) !== null && ($payload['initial_contract_amount'] ?? '') !== '' && !is_numeric((string) $payload['initial_contract_amount'])) {
            return ['success' => false, 'message' => 'initial_contract_amount must be numeric.'];
        }

        return ['success' => true];
    }

    public function normalizeNullableId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || strtolower($normalized) === 'null' || strtolower($normalized) === 'undefined') {
            return null;
        }

        return $normalized;
    }

    public function normalizeNullableProjectFields(array $data): array
    {
        $nullableFields = [
            'client_id',
            'employee_id',
            'site_agent',
            'contract_type',
            'contract_method',
            'director',
            'manager',
            'business_type',
            'housing_type',
            'construction_name',
            'site_region_city',
            'site_region_district',
            'site_region_address',
            'site_region_address_detail',
            'work_type',
            'work_subtype',
            'work_detail_type',
            'contract_work_type',
            'bid_type',
            'client_name',
            'client_type',
            'permit_agency',
            'permit_date',
            'contract_date',
            'start_date',
            'completion_date',
            'bid_notice_date',
            'initial_contract_amount',
            'authorized_company_seal',
            'note',
            'memo',
        ];

        foreach ($nullableFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            if ($field === 'initial_contract_amount') {
                $normalized = trim(str_replace(',', '', (string) $data[$field]));
                $data[$field] = $normalized === '' ? null : $normalized;
                continue;
            }

            $value = trim((string) $data[$field]);
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }
}
