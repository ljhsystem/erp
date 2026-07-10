<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceGenerationSplitService;
use App\Services\Ledger\EvidencePayloadNormalizeService;
use Core\DbPdo;
use PDO;

class EvidenceProcessingController
{
    use ImportControllerUtilityTrait;

    private PDO $pdo;
    private ?EvidenceGenerationSplitService $evidenceGenerationSplitService = null;
    private ?EvidencePayloadNormalizeService $evidencePayloadNormalizeService = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiDeleteProcessingChild(): void
    {
        $result = $this->evidenceGenerationSplitService()->deleteProcessingChild($this->requestPayload());

        $this->json($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 200));
    }

    public function apiUpdateProcessingChild(): void
    {
        $result = $this->evidenceGenerationSplitService()->updateProcessingChild($this->requestPayload());

        $this->json($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 200));
    }

    private function evidenceGenerationSplitService(): EvidenceGenerationSplitService
    {
        if ($this->evidenceGenerationSplitService === null) {
            $this->evidenceGenerationSplitService = new EvidenceGenerationSplitService(
                $this->pdo,
                fn(array $payload): array => $this->evidencePayloadNormalizeService()->mappedPayloadForStorage($payload),
                fn(mixed $value): ?float => $this->amountOrNull($value),
                fn(string $type): string => self::normalizeDataType($type)
            );
        }

        return $this->evidenceGenerationSplitService;
    }

    private function evidencePayloadNormalizeService(): EvidencePayloadNormalizeService
    {
        if ($this->evidencePayloadNormalizeService === null) {
            $this->evidencePayloadNormalizeService = new EvidencePayloadNormalizeService(
                fn(array $payload): array => $payload,
                fn(mixed $value): ?string => $this->dateValueOrNull($value),
                fn(mixed $value): ?string => $this->dateTimeValue($value),
                fn(string $value): bool => $this->isUuid($value),
                fn(string $value): bool => false,
                fn(string $value): bool => trim($value) === '',
                fn(array $column): bool => false,
                fn(string $field): string => $field
            );
        }

        return $this->evidencePayloadNormalizeService;
    }
}
