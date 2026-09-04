<?php

namespace App\Services\Institution;

use App\Models\Institution\WorkplaceSizePeriodModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use Closure;
use PDO;
use Psr\Log\LoggerInterface;

final class WorkplaceSizePeriodService
{
    public const PURPOSE_EMPLOYMENT_INSURANCE_VOCATIONAL = 'EMPLOYMENT_INSURANCE_VOCATIONAL';

    private readonly ?Closure $actorProvider;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $db, private readonly WorkplaceSizePeriodModel $model, ?callable $actorProvider = null)
    {
        $this->actorProvider = $actorProvider === null ? null : Closure::fromCallable($actorProvider);
        $this->logger = LoggerFactory::getLogger('service-institution-workplace-size-period');
    }

    public function register(array $input): array
    {
        return $this->transaction(function () use ($input): array {
            $companyId = trim((string) ($input['company_id'] ?? ''));
            $purpose = trim((string) ($input['calculation_purpose_code'] ?? ''));
            $from = trim((string) ($input['effective_from'] ?? ''));
            $to = trim((string) ($input['effective_to'] ?? '')) ?: null;
            $requestKey = trim((string) ($input['request_key'] ?? ''));
            if ($companyId === '' || $purpose === '' || $from === '' || $requestKey === '') {
                throw new \InvalidArgumentException('회사, 계산목적, 적용기간, 요청키를 확인해 주세요.');
            }
            $this->model->lockCompany($companyId);
            $existing = $this->model->findByRequestKey($companyId, $requestKey);
            if ($existing) {
                $this->assertSameRequestPayload($existing, $input, $purpose, $from, $to);
                return $existing;
            }
            $requestedPreviousId = trim((string) ($input['previous_period_id'] ?? ''));
            $previous = $requestedPreviousId !== '' ? $this->model->findForUpdate($requestedPreviousId) : null;
            if ($requestedPreviousId !== '' && (!$previous || (string) $previous['company_id'] !== $companyId || (string) $previous['calculation_purpose_code'] !== $purpose)) {
                throw new \InvalidArgumentException('정정할 회사규모 기간 Revision을 확인해 주세요.');
            }
            $previousId = $previous['id'] ?? null;
            if ($this->model->hasLeafOverlap($companyId, $purpose, $from, $to, $previousId)) {
                throw new \DomainException('같은 계산목적의 회사규모 적용기간이 중복됩니다.');
            }
            $status = strtoupper(trim((string) ($input['confirmation_status_code'] ?? 'DRAFT')));
            $actor = $this->actorProvider === null ? ActorHelper::user() : ($this->actorProvider)();
            $row = [
                'id' => UuidHelper::generate(), 'company_id' => $companyId, 'calculation_purpose_code' => $purpose,
                'effective_from' => $from, 'effective_to' => $to,
                'business_size_code' => strtoupper(trim((string) ($input['business_size_code'] ?? ''))),
                'business_size_name_snapshot' => trim((string) ($input['business_size_name_snapshot'] ?? '')),
                'regular_worker_count' => (int) ($input['regular_worker_count'] ?? -1),
                'calculation_basis_description' => trim((string) ($input['calculation_basis_description'] ?? '')),
                'evidence_type_code' => strtoupper(trim((string) ($input['evidence_type_code'] ?? ''))),
                'evidence_id' => trim((string) ($input['evidence_id'] ?? '')) ?: null,
                'evidence_description' => trim((string) ($input['evidence_description'] ?? '')) ?: null,
                'statutory_standard_id' => trim((string) ($input['statutory_standard_id'] ?? '')) ?: null,
                'confirmation_status_code' => $status,
                'confirmed_at' => $status === 'CONFIRMED' ? date('Y-m-d H:i:s') : null,
                'confirmed_by' => $status === 'CONFIRMED' ? $actor : null,
                'revision_no' => $previous ? ((int) $previous['revision_no'] + 1) : 1,
                'previous_period_id' => $previousId,
                'correction_reason' => $previous ? trim((string) ($input['correction_reason'] ?? '')) : null,
                'request_key' => $requestKey, 'created_by' => $actor,
            ];
            if ($row['business_size_code'] === '' || $row['business_size_name_snapshot'] === '' || $row['regular_worker_count'] < 0 || $row['calculation_basis_description'] === '' || !in_array($row['evidence_type_code'], ['OFFICIAL_CONFIRMED','MANUAL_CONFIRMED','HISTORICAL_IMPORT'], true) || ($row['evidence_id'] === null && $row['evidence_description'] === null) || ($previous && $row['correction_reason'] === '')) {
                throw new \InvalidArgumentException('회사규모 산정값과 확인근거를 확인해 주세요.');
            }
            $this->model->insert($row);
            return $row;
        });
    }

    private function assertSameRequestPayload(array $existing, array $input, string $purpose, string $from, ?string $to): void
    {
        $expected = [
            'calculation_purpose_code' => $purpose,
            'effective_from' => $from,
            'effective_to' => $to,
            'business_size_code' => strtoupper(trim((string) ($input['business_size_code'] ?? ''))),
            'business_size_name_snapshot' => trim((string) ($input['business_size_name_snapshot'] ?? '')),
            'regular_worker_count' => (int) ($input['regular_worker_count'] ?? -1),
            'calculation_basis_description' => trim((string) ($input['calculation_basis_description'] ?? '')),
            'evidence_type_code' => strtoupper(trim((string) ($input['evidence_type_code'] ?? ''))),
            'evidence_id' => trim((string) ($input['evidence_id'] ?? '')) ?: null,
            'evidence_description' => trim((string) ($input['evidence_description'] ?? '')) ?: null,
            'statutory_standard_id' => trim((string) ($input['statutory_standard_id'] ?? '')) ?: null,
            'confirmation_status_code' => strtoupper(trim((string) ($input['confirmation_status_code'] ?? 'DRAFT'))),
            'previous_period_id' => trim((string) ($input['previous_period_id'] ?? '')) ?: null,
            'correction_reason' => trim((string) ($input['correction_reason'] ?? '')) ?: null,
        ];
        foreach ($expected as $column => $value) {
            $stored = $existing[$column] ?? null;
            if ($column === 'regular_worker_count') $stored = (int) $stored;
            if ($stored !== $value) {
                throw new \DomainException('같은 요청키에 다른 회사규모 Payload를 사용할 수 없습니다.');
            }
        }
    }

    private function transaction(callable $callback): mixed
    {
        $owned = !$this->db->inTransaction();
        if ($owned) $this->db->beginTransaction();
        try {
            $result = $callback();
            if ($owned) {$this->db->commit();$this->logger->info('사업장 규모 적용기간이 등록되었습니다.',['event_code'=>'WORKPLACE_SIZE_PERIOD_REGISTERED','result'=>'SUCCESS','service'=>self::class,'action'=>'register','actor'=>ActorHelper::user()]);}
            return $result;
        } catch (\InvalidArgumentException|\DomainException $exception) {
            if ($owned && $this->db->inTransaction()) $this->db->rollBack();
            if($owned)$this->logger->warning('사업장 규모 적용기간 등록이 차단되었습니다.',['event_code'=>'WORKPLACE_SIZE_PERIOD_REGISTER_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'register','actor'=>ActorHelper::user(),'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        } catch (\Throwable $exception) {
            if ($owned && $this->db->inTransaction()) $this->db->rollBack();
            if($owned)$this->logger->error('사업장 규모 적용기간 등록에 실패했습니다.',['event_code'=>'WORKPLACE_SIZE_PERIOD_REGISTER_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'register','actor'=>ActorHelper::user(),'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        }
    }
}
