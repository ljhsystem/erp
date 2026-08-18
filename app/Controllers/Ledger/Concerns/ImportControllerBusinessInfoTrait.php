<?php

namespace App\Controllers\Ledger\Concerns;

use App\Services\Ledger\EvidenceImportBusinessService;

trait ImportControllerBusinessInfoTrait
{
    private ?EvidenceImportBusinessService $evidenceImportBusiness = null;

    private function evidenceImportBusinessService(): EvidenceImportBusinessService
    {
        return $this->evidenceImportBusiness ??= new EvidenceImportBusinessService();
    }
    private function ensureEvidenceBusinessInfoColumns(): void
    {
        return;
    }

    private function mergeEvidenceBusinessInfoIntoPayload(array $evidenceRow, array &$payload): void
    {
        $payload = $this->evidenceBusinessRefService()->normalizeBusinessRefPayload($payload);
        foreach ([
            'business_unit',
            'transaction_direction',
            'operation_type',
            'client_id',
            'project_id',
            'employee_id',
            'bank_account_id',
            'card_id',
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
        ] as $key) {
            $rowValue = trim((string) ($evidenceRow[$key] ?? ''));
            if ($rowValue === '' || $this->evidenceBusinessRefService()->isEmptySelectionLabel($rowValue)) {
                continue;
            }
            $payloadValue = trim((string) $this->evidencePayloadHelperService()->payloadScalarForStorage($payload[$key] ?? null));
            if ($payloadValue === '' || $this->evidenceBusinessRefService()->isEmptySelectionLabel($payloadValue) || ($this->isUuid($payloadValue) && str_ends_with($key, '_name'))) {
                $payload[$key] = $rowValue;
            }
        }
        $payload = $this->evidenceBusinessRefService()->normalizeBusinessRefPayload($payload);
    }

    private function partyFromRow(array $row, string $prefix, ?string $fallbackPrefix = null): array
    {
        $businessNumber = (string) (
            $row[$prefix . '_business_number']
            ?? $row['raw_' . $prefix . '_business_number']
            ?? ''
        );
        $companyName = (string) (
            $row[$prefix . '_company_name']
            ?? $row['raw_' . $prefix . '_company_name']
            ?? $row[$prefix . '_name']
            ?? $row['raw_' . $prefix . '_name']
            ?? ''
        );
        if ($fallbackPrefix !== null) {
            $businessNumber = $businessNumber !== '' ? $businessNumber : (string) (
                $row[$fallbackPrefix . '_business_number']
                ?? $row['raw_' . $fallbackPrefix . '_business_number']
                ?? ''
            );
            $companyName = $companyName !== '' ? $companyName : (string) (
                $row[$fallbackPrefix . '_company_name']
                ?? $row['raw_' . $fallbackPrefix . '_company_name']
                ?? $row[$fallbackPrefix . '_name']
                ?? $row['raw_' . $fallbackPrefix . '_name']
                ?? ''
            );
        }

        return [
            'business_number' => $this->normalizeBusinessNumber($businessNumber),
            'company_name' => $this->cleanCompanyName($companyName),
        ];
    }

    private function ownCompanyDefaultParty(): array
    {
        $profile = $this->ownCompanyProfile();
        return [
            'business_number' => $profile['business_numbers'][0] ?? '',
            'company_name' => $profile['company_names'][0] ?? '',
        ];
    }

    private function isOwnCompanyParty(array $party): bool
    {
        $profile = $this->ownCompanyProfile();
        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        if ($businessNumber !== '' && in_array($businessNumber, $profile['business_numbers'], true)) {
            return true;
        }

        $companyName = $this->normalizeCompanyNameForCompare((string) ($party['company_name'] ?? ''));
        return $companyName !== '' && in_array($companyName, $profile['company_names'], true);
    }

    private function ownCompanyProfile(): array
    {
        if ($this->ownCompanyProfile !== null) {
            return $this->ownCompanyProfile;
        }

        $this->ownCompanyProfile = $this->evidenceImportBusinessService()->ownCompanyProfile();

        return $this->ownCompanyProfile;
    }

    private function clientExistsByBusinessNumber(string $businessNumber): bool
    {
        return $this->evidenceImportBusinessService()->clientExistsByBusinessNumber($businessNumber);
    }

    private function updateClientCompanyName(string $clientId, string $companyName): void
    {
        $this->evidenceImportBusinessService()->updateClientCompanyName($clientId, $companyName);
    }

    private function normalizeBusinessNumber(string $businessNumber): string
    {
        return preg_replace('/[^0-9]/', '', $businessNumber) ?? '';
    }

}
