<?php

namespace App\Services\Ledger;

use App\Models\System\ClientModel;
use App\Models\System\CompanyModel;
use Core\Helpers\ActorHelper;

class EvidenceImportBusinessService
{
    public function __construct(
        private readonly CompanyModel $companyModel = new CompanyModel(),
        private readonly ClientModel $clientModel = new ClientModel()
    ) {
    }

    public function ownCompanyProfile(): array
    {
        $company = $this->companyModel->getOne() ?? [];
        $businessNumbers = [];
        foreach (['biz_number', 'business_no', 'business_number'] as $key) {
            $value = $this->normalizeBusinessNumber((string) ($company[$key] ?? ''));
            if ($value !== '') $businessNumbers[] = $value;
        }
        $companyNames = [];
        foreach (['company_name_ko', 'company_name_en', 'company_name'] as $key) {
            $value = $this->normalizeCompanyName((string) ($company[$key] ?? ''));
            if ($value !== '') $companyNames[] = $value;
        }
        return [
            'business_numbers' => array_values(array_unique($businessNumbers)),
            'company_names' => array_values(array_unique($companyNames)),
        ];
    }

    public function clientExistsByBusinessNumber(string $businessNumber): bool
    {
        return $this->clientModel->findIdByBusinessNumber($businessNumber) !== null;
    }

    public function updateClientCompanyName(string $clientId, string $companyName): void
    {
        $this->clientModel->updateCompanyName($clientId, $companyName, ActorHelper::user());
    }

    private function normalizeBusinessNumber(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    private function normalizeCompanyName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = preg_replace('/^\s*(?:\(\s*주\s*\)|㈜)\s*/u', '', $value) ?? $value;
        $value = preg_replace('/\s*(?:\(\s*주\s*\)|㈜)\s*$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', trim($value)) ?? $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
