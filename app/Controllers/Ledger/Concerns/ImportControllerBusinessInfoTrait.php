<?php

namespace App\Controllers\Ledger\Concerns;

use App\Models\System\CompanyModel;
use Core\Helpers\ActorHelper;

trait ImportControllerBusinessInfoTrait
{
    private function ensureEvidenceBusinessInfoColumns(): void
    {
        return;
    }

    private function mergeEvidenceBusinessInfoIntoPayload(array $evidenceRow, array &$payload): void
    {
        $payload = $this->evidenceBusinessRefService()->normalizeBusinessRefPayload($payload);
        foreach (['client_id', 'project_id', 'employee_id', 'bank_account_id', 'card_id', 'client_name', 'project_name', 'employee_name', 'bank_account_name', 'card_name'] as $key) {
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
        $businessNumber = (string) ($row[$prefix . '_business_number'] ?? '');
        $companyName = (string) ($row[$prefix . '_company_name'] ?? '');
        if ($fallbackPrefix !== null) {
            $businessNumber = $businessNumber !== '' ? $businessNumber : (string) ($row[$fallbackPrefix . '_business_number'] ?? '');
            $companyName = $companyName !== '' ? $companyName : (string) ($row[$fallbackPrefix . '_company_name'] ?? '');
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

        $company = (new CompanyModel($this->pdo))->getOne() ?? [];
        $businessNumbers = [];
        foreach (['biz_number', 'business_no', 'business_number'] as $key) {
            $value = $this->normalizeBusinessNumber((string) ($company[$key] ?? ''));
            if ($value !== '') {
                $businessNumbers[] = $value;
            }
        }

        $companyNames = [];
        foreach (['company_name_ko', 'company_name_en', 'company_name'] as $key) {
            $value = $this->normalizeCompanyNameForCompare((string) ($company[$key] ?? ''));
            if ($value !== '') {
                $companyNames[] = $value;
            }
        }

        $this->ownCompanyProfile = [
            'business_numbers' => array_values(array_unique($businessNumbers)),
            'company_names' => array_values(array_unique($companyNames)),
        ];

        return $this->ownCompanyProfile;
    }

    private function clientExistsByBusinessNumber(string $businessNumber): bool
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':business_number' => $businessNumber]);
        return (bool) $stmt->fetchColumn();
    }

    private function updateClientCompanyName(string $clientId, string $companyName): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE system_clients
            SET client_name = :client_name,
                company_name = :company_name,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $clientId,
            ':client_name' => $companyName,
            ':company_name' => $companyName,
            ':actor' => ActorHelper::user(),
        ]);
    }

    private function normalizeBusinessNumber(string $businessNumber): string
    {
        return preg_replace('/[^0-9]/', '', $businessNumber) ?? '';
    }

    private function cleanCompanyName(string $companyName): string
    {
        $companyName = trim($companyName);
        $companyName = preg_replace('/\s+/u', ' ', $companyName) ?? $companyName;
        $companyName = preg_replace('/^\s*[\(??\s*??s*[\)??\s*/u', '', $companyName) ?? $companyName;
        $companyName = preg_replace('/\s*[\(??\s*??s*[\)??\s*$/u', '', $companyName) ?? $companyName;
        return trim($companyName);
    }
}
