<?php

namespace App\Services\Ledger;

use App\Models\Ledger\OpeningBalanceModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class OpeningBalanceService
{
    private OpeningBalanceModel $model;
    private VoucherService $vouchers;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new OpeningBalanceModel($pdo);
        $this->vouchers = new VoucherService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.OpeningBalanceService');
    }

    public function getList(array $filters = []): array
    {
        $rows = $this->model->list($filters);
        foreach ($rows as &$row) $row = $this->project($row);
        unset($row);
        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
        ]);
    }

    public function getDetail(string $id): ?array
    {
        $row = $this->model->find($id);
        if (!$row) return null;
        $row = ActorHelper::enrichActorNamesRow($this->project($row), [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
        ]);
        $row['lines'] = array_map(fn(array $line): array => $this->projectLine($line), $this->model->lines((string) $row['voucher_id']));
        return $row;
    }

    public function options(): array
    {
        return ['companies' => $this->model->companies()];
    }

    public function save(array $input): array
    {
        $id = trim((string) ($input['id'] ?? ''));
        $companyId = trim((string) ($input['company_id'] ?? ''));
        $year = (int) ($input['fiscal_year'] ?? 0);
        $openingDate = sprintf('%04d-12-31', $year - 1);
        $lines = $input['lines'] ?? [];
        if (is_string($lines)) $lines = json_decode($lines, true);
        if ($companyId === '' || $year < 1900 || $year > 9999) throw new \InvalidArgumentException('회사와 회계연도를 입력해 주세요.');
        if (!is_array($lines) || $lines === []) throw new \InvalidArgumentException('기초금액 분개를 한 건 이상 입력해 주세요.');

        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $current = $id !== '' ? $this->model->find($id, true) : null;
            if ($id !== '' && !$current) throw new \InvalidArgumentException('기초금액 문서를 찾을 수 없습니다.');
            $duplicate = $this->model->findByCompanyYear($companyId, $year);
            if ($duplicate && (string) $duplicate['id'] !== $id) throw new \InvalidArgumentException('해당 회사와 회계연도의 기초금액이 이미 있습니다.');

            $voucher = $this->vouchers->save([
                'id' => $current['voucher_id'] ?? '',
                'voucher_no' => $current['voucher_no'] ?? $this->voucherNo($year, $companyId),
                'voucher_date' => $openingDate,
                'source_type' => 'SYSTEM',
                'lines' => $lines,
                'linked_evidences' => [],
            ]);
            $voucherId = (string) $voucher['voucher_id'];
            if ($current) {
                $this->model->update($id, [
                    ':company_id' => $companyId,
                    ':fiscal_year' => $year,
                    ':opening_date' => $openingDate,
                    ':note' => $this->nullable($input['note'] ?? null),
                    ':updated_at' => $now,
                    ':updated_by' => $actor,
                ]);
            } else {
                $id = UuidHelper::generate();
                $this->model->insert([
                    ':id' => $id,
                    ':company_id' => $companyId,
                    ':fiscal_year' => $year,
                    ':opening_date' => $openingDate,
                    ':voucher_id' => $voucherId,
                    ':note' => $this->nullable($input['note'] ?? null),
                    ':created_at' => $now,
                    ':created_by' => $actor,
                    ':updated_at' => $now,
                    ':updated_by' => $actor,
                ]);
            }
            $this->pdo->commit();
            $this->logger->info('기초금액을 저장했습니다.', ['event_code'=>'OPENING_BALANCE_SAVED','result'=>'SUCCESS','service'=>self::class,'action'=>'save','target_id'=>$id,'actor'=>$actor]);
            return ['success'=>true,'message'=>'기초금액을 저장했습니다.','data'=>$this->getDetail($id)];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('기초금액 저장에 실패했습니다.', ['event_code'=>'OPENING_BALANCE_SAVE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'save','target_id'=>$id,'actor'=>$actor,'error_code'=>$e::class,'error'=>$e]);
            throw $e;
        }
    }

    public function transition(string $id, string $action): array
    {
        $row = $this->model->find($id);
        if (!$row) throw new \InvalidArgumentException('기초금액 문서를 찾을 수 없습니다.');
        $result = match ($action) {
            'request-review' => $this->vouchers->requestReview((string) $row['voucher_id']),
            'cancel-review' => $this->vouchers->cancelReviewRequest((string) $row['voucher_id']),
            'review' => $this->vouchers->completeReview((string) $row['voucher_id']),
            'cancel-reviewed' => $this->vouchers->cancelCompleteReview((string) $row['voucher_id']),
            'post' => $this->vouchers->post((string) $row['voucher_id']),
            default => throw new \InvalidArgumentException('지원하지 않는 상태 변경입니다.'),
        };
        $this->logger->info('기초금액 상태를 변경했습니다.', ['event_code'=>'OPENING_BALANCE_STATUS_CHANGED','result'=>'SUCCESS','service'=>self::class,'action'=>$action,'target_id'=>$id,'actor'=>ActorHelper::user()]);
        return ['success'=>true,'message'=>'기초금액 상태를 변경했습니다.','data'=>$this->getDetail($id),'voucher_result'=>$result];
    }

    public function delete(string $id): array
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->model->find($id, true);
            if (!$row) throw new \InvalidArgumentException('기초금액 문서를 찾을 수 없습니다.');
            $this->model->delete($id);
            $this->vouchers->deleteVoucher((string) $row['voucher_id']);
            $this->pdo->commit();
            $this->logger->info('기초금액을 삭제했습니다.', ['event_code'=>'OPENING_BALANCE_DELETED','result'=>'SUCCESS','service'=>self::class,'action'=>'delete','target_id'=>$id,'actor'=>ActorHelper::user()]);
            return ['success'=>true,'message'=>'기초금액을 삭제했습니다.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reverse(string $id): array
    {
        $row = $this->model->find($id);
        if (!$row) throw new \InvalidArgumentException('기초금액 문서를 찾을 수 없습니다.');
        $result = $this->vouchers->createReversalVoucher((string) $row['voucher_id'], ActorHelper::user());
        return ['success'=>true,'message'=>'기초금액 취소전표를 생성했습니다.','data'=>$result];
    }

    private function project(array $row): array
    {
        $row['status'] = VoucherStatus::normalize($row['status'] ?? null, '');
        $row['balance_difference'] = (float) ($row['debit_total'] ?? 0) - (float) ($row['credit_total'] ?? 0);
        return $row;
    }

    private function projectLine(array $line): array
    {
        $refs = [];
        foreach (array_filter(explode('|', (string) ($line['ref_tokens'] ?? ''))) as $token) {
            [$target,$refId] = array_pad(explode(':', $token, 2), 2, '');
            if ($target !== '' && $refId !== '') $refs[] = ['ref_target'=>$target,'ref_id'=>$refId];
        }
        $line['refs'] = $refs;
        unset($line['ref_tokens']);
        return $line;
    }

    private function voucherNo(int $year, string $companyId): string
    {
        return 'OB'.$year.'-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $companyId), 0, 8));
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
