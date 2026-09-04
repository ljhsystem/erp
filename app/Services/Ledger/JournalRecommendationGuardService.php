<?php

namespace App\Services\Ledger;

use PDO;

class JournalRecommendationGuardService
{
    public const REASON_CODE = 'PAYROLL_SNAPSHOT_CONTRACT_NOT_READY';
    public const MESSAGE = '급여 승인 Snapshot의 원천 Line 추적계약이 준비되지 않아 분개추천을 제공할 수 없습니다.';

    private const BLOCKED_IMPORT_TYPES = [
        'PAYROLL',
        'PAYROLL_REPORT',
        'PAYROLL_WITHHOLDING',
    ];

    private ?JournalLearningPolicyService $policyService;

    public function __construct(?PDO $pdo = null)
    {
        $this->policyService = $pdo ? new JournalLearningPolicyService($pdo) : null;
    }

    public function assertRecommendationAllowed(array $evidences, array $context = []): void
    {
        if ($this->containsBlockedPayroll($evidences)) {
            throw new JournalRecommendationGuardException(self::MESSAGE, self::REASON_CODE);
        }
        $this->assertConfiguredGuards($evidences, $context);
    }

    public function assertApplicationAllowed(array $evidences, array $lines): void
    {
        if ($this->hasRecommendationMetadata($lines)) {
            $this->assertConfiguredGuards($evidences, []);
        }
        if (!$this->containsBlockedPayroll($evidences)) {
            return;
        }
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if (trim((string) ($line['recommendation_source'] ?? '')) !== ''
                || trim((string) ($line['recommendation_snapshot'] ?? '')) !== ''
                || trim((string) ($line['recommended_account_id'] ?? '')) !== '') {
                throw new JournalRecommendationGuardException(self::MESSAGE, self::REASON_CODE);
            }
        }
    }

    private function hasRecommendationMetadata(array $lines): bool
    {
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if (trim((string) ($line['recommendation_source'] ?? '')) !== ''
                || trim((string) ($line['recommendation_snapshot'] ?? '')) !== ''
                || trim((string) ($line['recommended_account_id'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private function assertConfiguredGuards(array $evidences, array $context): void
    {
        if (!$this->policyService) {
            return;
        }
        $companyId = trim((string) ($context['company_id'] ?? ''));
        $operationType = strtoupper(trim((string) ($context['operation_type'] ?? '')));
        $workflow = strtoupper(trim((string) ($context['workflow'] ?? '')));
        foreach ($evidences as $evidence) {
            if (!is_array($evidence)) {
                continue;
            }
            $companyId = $companyId !== '' ? $companyId : trim((string) ($evidence['company_id'] ?? ''));
            $operationType = $operationType !== '' ? $operationType : strtoupper(trim((string) ($evidence['operation_type'] ?? '')));
            $importType = strtoupper(trim((string) ($evidence['import_type'] ?? '')));
            if ($companyId !== '' && (!$this->policyService->isGuardEnabled($companyId, 'operation_type', $operationType)
                || !$this->policyService->isGuardEnabled($companyId, 'import_type', $importType)
                || !$this->policyService->isGuardEnabled($companyId, 'workflow', $workflow))) {
                throw new JournalRecommendationGuardException('현재 자료유형의 분개추천은 검증 완료 전까지 사용할 수 없습니다.', 'JOURNAL_RECOMMENDATION_DOMAIN_DISABLED');
            }
        }
    }

    private function containsBlockedPayroll(array $evidences): bool
    {
        foreach ($evidences as $evidence) {
            if (!is_array($evidence)) {
                continue;
            }
            $importType = strtoupper(trim((string) ($evidence['import_type'] ?? '')));
            if (in_array($importType, self::BLOCKED_IMPORT_TYPES, true)) {
                return true;
            }
        }
        return false;
    }
}

class JournalRecommendationGuardException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $reasonCode)
    {
        parent::__construct($message);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
