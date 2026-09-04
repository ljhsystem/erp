<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class DailyEmploymentIncomeNonTaxPayloadService
{
    private const COMMAND_TYPES = [
        'NON_TAX_CREATE',
        'NON_TAX_CONFIRM',
        'NON_TAX_CORRECT',
        'NON_TAX_ATTACHMENT_LINK',
        'NON_TAX_ATTACHMENT_UNLINK',
    ];

    public function hash(string $commandType, array $payload): string
    {
        $canonical = $this->canonicalize($commandType, $payload);

        return hash('sha256', json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    public function canonicalize(string $commandType, array $payload): array
    {
        $commandType = strtoupper(trim($commandType));
        if (!in_array($commandType, self::COMMAND_TYPES, true)) {
            throw new \InvalidArgumentException('지원하지 않는 비과세 명령입니다.');
        }

        $attachments = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => trim((string) $value),
            is_array($payload['attachment_ids'] ?? null) ? $payload['attachment_ids'] : []
        ), static fn(string $value): bool => $value !== '')));
        sort($attachments, SORT_STRING);

        return [
            'command_type' => $commandType,
            'daily_employment_income_id' => $this->required($payload, 'daily_employment_income_id'),
            'daily_employment_income_item_id' => $this->nullable($payload['daily_employment_income_item_id'] ?? null),
            'daily_employment_income_workday_id' => $this->nullable($payload['daily_employment_income_workday_id'] ?? null),
            'effective_from' => $this->nullable($payload['effective_from'] ?? null),
            'effective_to' => $this->nullable($payload['effective_to'] ?? null),
            'non_taxable_item_code' => $this->nullable($payload['non_taxable_item_code'] ?? null),
            'applied_amount' => $this->amount($payload['applied_amount'] ?? null),
            'application_reason' => $this->nullable($payload['application_reason'] ?? null),
            'legal_basis' => $this->nullable($payload['legal_basis'] ?? null),
            'calculation_details' => $this->nullable($payload['calculation_details'] ?? null),
            'statutory_standard_id' => $this->nullable($payload['statutory_standard_id'] ?? null),
            'target_revision_id' => $this->nullable($payload['target_revision_id'] ?? null),
            'attachment_ids' => $attachments,
        ];
    }

    private function required(array $payload, string $key): string
    {
        $value = trim((string) ($payload[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException('일용근로소득 문서 ID가 필요합니다.');
        }

        return $value;
    }

    private function nullable(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized === '' ? null : $normalized;
    }

    private function amount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('비과세 적용금액을 확인해 주세요.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
