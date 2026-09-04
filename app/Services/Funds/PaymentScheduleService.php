<?php

namespace App\Services\Funds;

use App\Models\Funds\BankPaymentEvidenceModel;
use App\Models\Funds\PaymentScheduleHistoryModel;
use App\Models\Funds\PaymentScheduleModel;
use App\Models\Ledger\EvidenceLinkModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;
use Psr\Log\LoggerInterface;

class PaymentScheduleService
{
    private const ACTOR_FIELDS = [
        'created_by_name' => 'created_by',
        'updated_by_name' => 'updated_by',
        'deleted_by_name' => 'deleted_by',
        'held_by_name' => 'held_by',
        'released_by_name' => 'released_by',
    ];

    private PaymentScheduleModel $schedules;
    private PaymentScheduleHistoryModel $histories;
    private BankPaymentEvidenceModel $bankEvidence;
    private EvidenceLinkModel $links;
    private LoggerInterface $logger;

    public function __construct(private PDO $pdo)
    {
        $this->schedules = new PaymentScheduleModel($pdo);
        $this->histories = new PaymentScheduleHistoryModel($pdo);
        $this->bankEvidence = new BankPaymentEvidenceModel($pdo);
        $this->links = new EvidenceLinkModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-funds-payment-schedule');
    }

    public function create(array $input): array
    {
        $data = $this->normalizeSchedule($input);
        $this->validateReferences($data);
        $actor = ActorHelper::user();
        return $this->transaction(function () use ($data, $actor): array {
            $id = $this->schedules->create($data, $actor);
            $row = $this->schedules->find($id);
            $this->histories->record($id, 'CREATED', null, $row, null, $actor);
            return $this->projectStatus($row ?: []);
        });
    }

    public function list(array $request): array
    {
        $filters = $this->filters($request);
        $start = max(0, (int) ($request['start'] ?? 0));
        $length = max(1, min(500, (int) ($request['length'] ?? 50)));
        [$sortField, $sortDirection] = $this->sort($request);
        $rows = $this->schedules->search($filters, $start, $length, $sortField, $sortDirection);
        $rows = ActorHelper::enrichActorNames($rows, self::ACTOR_FIELDS);
        $rows = array_map(fn(array $row): array => $this->projectStatus($row), $rows);
        $count = $this->schedules->count($filters);
        return [
            'draw' => (int) ($request['draw'] ?? 0),
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $rows,
            'summary' => $this->schedules->summary($filters),
            'filters' => $filters,
        ];
    }

    public function detail(string $id): array
    {
        $schedule = $this->schedules->find($id);
        if (!$schedule) {
            throw new \RuntimeException('지급예정을 찾을 수 없습니다.');
        }
        $schedule = ActorHelper::enrichActorNamesRow($this->projectStatus($schedule), self::ACTOR_FIELDS);
        $histories = $this->histories->listBySchedule($id);
        foreach ($histories as &$history) {
            $history['processed_by_name'] = ActorHelper::displayName((string) ($history['actor_id'] ?? ''));
            $history['before'] = $this->decodeJson($history['before_value'] ?? null);
            $history['after'] = $this->decodeJson($history['after_value'] ?? null);
            $history['change_summary'] = $this->historySummary($history);
        }
        unset($history);
        return [
            'schedule' => $schedule,
            'allocations' => $this->links->getPaymentAllocations($id),
            'histories' => $histories,
        ];
    }

    public function update(string $id, array $input): array
    {
        $actor = ActorHelper::user();
        return $this->transaction(function () use ($id, $input, $actor): array {
            $before = $this->schedules->lock($id);
            if (!$before || $before['deleted_at'] !== null) {
                throw new \RuntimeException('수정할 지급예정을 찾을 수 없습니다.');
            }
            $data = $this->normalizeSchedule($input + [
                'source_type' => $before['source_type'],
                'source_id' => $before['source_id'],
                'source_line_key' => $before['source_line_key'],
                'scheduled_amount' => $before['scheduled_amount'],
            ]);
            $data['source_type'] = $before['source_type'];
            $data['source_id'] = $before['source_id'];
            $data['source_line_key'] = $before['source_line_key'];
            $data['scheduled_amount'] = $before['scheduled_amount'];
            $this->validateReferences($data);
            $paid = array_sum(array_map(
                static fn(array $link): float => (float) $link['amount'],
                $this->links->lockPaymentAllocationsBySchedule($id)
            ));
            if ((float) $data['scheduled_amount'] + 0.00001 < $paid) {
                throw new \RuntimeException('기지급액보다 작은 금액으로 수정할 수 없습니다.');
            }
            $this->schedules->update($id, $data, $actor);
            $after = $this->schedules->find($id) ?: [];
            $this->histories->record($id, 'UPDATED', $before, $after, null, $actor);
            return ActorHelper::enrichActorNamesRow($this->projectStatus($after), self::ACTOR_FIELDS);
        });
    }

    public function hold(string $id, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('보류사유를 입력해 주세요.');
        }
        return $this->changeHold($id, true, $reason);
    }

    public function releaseHold(string $id, ?string $reason = null): array
    {
        return $this->changeHold($id, false, trim((string) $reason));
    }

    public function delete(string $id): void
    {
        $actor = ActorHelper::user();
        $this->transaction(function () use ($id, $actor): void {
            $before = $this->schedules->lock($id);
            if (!$before || $before['deleted_at'] !== null) {
                throw new \RuntimeException('삭제할 지급예정을 찾을 수 없습니다.');
            }
            if ($this->links->lockPaymentAllocationsBySchedule($id) !== []) {
                throw new \RuntimeException('활성 지급연결이 있는 지급예정은 삭제할 수 없습니다.');
            }
            $this->schedules->softDelete($id, $actor);
            $after = $this->schedules->lock($id);
            $this->histories->record($id, 'DELETED', $before, $after, null, $actor);
        });
    }

    public function restore(string $id): array
    {
        $actor = ActorHelper::user();
        return $this->transaction(function () use ($id, $actor): array {
            $before = $this->schedules->lock($id);
            if (!$before || $before['deleted_at'] === null) {
                throw new \RuntimeException('복원할 지급예정을 찾을 수 없습니다.');
            }
            $this->validateReferences($before);
            $this->schedules->restore($id, $actor);
            $after = $this->schedules->find($id) ?: [];
            $this->histories->record($id, 'RESTORED', $before, $after, null, $actor);
            return ActorHelper::enrichActorNamesRow($this->projectStatus($after), self::ACTOR_FIELDS);
        });
    }

    public function options(): array
    {
        return [
            'clients' => $this->schedules->options('system_clients', 'id', 'client_name'),
            'projects' => $this->schedules->options('system_projects', 'id', 'project_name'),
            'assignees' => $this->schedules->options('user_employees', 'id', 'employee_name'),
            'bank_accounts' => $this->schedules->options('system_bank_accounts', 'id', 'account_name'),
            'source_types' => $this->schedules->sourceTypes(),
        ];
    }

    public function bankWithdrawals(array $request): array
    {
        return $this->bankEvidence->searchWithdrawals(
            trim((string) ($request['q'] ?? '')),
            (int) ($request['limit'] ?? 30)
        );
    }

    public function spreadsheet(array $request): Spreadsheet
    {
        $filters = $this->filters($request);
        $rows = $this->schedules->search($filters, 0, 50000, 'payment_due_date', 'asc');
        $rows = array_map(fn(array $row): array => $this->projectStatus($row), $rows);
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('지급예정현황');
        $headers = ['순번', '지급예정일', '지급상태', '원천유형', '원천', '거래처', '프로젝트', '담당자', '지급계좌', '지급예정액', '기지급액', '잔여액', '연체일수', '보류여부', '보류사유', '최종지급일시', '등록일시', '수정일시'];
        $data = array_map(static fn(array $row): array => [
            $row['sort_no'], $row['payment_due_date'], $row['payment_status_label'], $row['source_type'], $row['source_name'],
            $row['client_name'], $row['project_name'], $row['assignee_name'], $row['payment_bank_account_name'],
            $row['scheduled_amount'], $row['paid_amount'], $row['remaining_amount'], $row['overdue_days'],
            (int) $row['is_on_hold'] === 1 ? '예' : '아니오', $row['hold_reason'], $row['last_payment_at'],
            $row['created_at'], $row['updated_at'],
        ], $rows);
        ExcelValueFormatterHelper::writeTable($sheet, $headers, $data, 'A1');
        foreach (range('A', 'R') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        return $sheet->getParent();
    }

    public function filters(array $request): array
    {
        return [
            'date_from' => $this->validDate((string) ($request['date_from'] ?? '')),
            'date_to' => $this->validDate((string) ($request['date_to'] ?? '')),
            'payment_status' => strtoupper(trim((string) ($request['payment_status'] ?? ''))),
            'client_id' => trim((string) ($request['client_id'] ?? '')),
            'project_id' => trim((string) ($request['project_id'] ?? '')),
            'assignee_id' => trim((string) ($request['assignee_id'] ?? '')),
            'payment_bank_account_id' => trim((string) ($request['payment_bank_account_id'] ?? '')),
            'source_type' => strtoupper(trim((string) ($request['source_type'] ?? ''))),
            'q' => trim((string) ($request['q'] ?? '')),
            'deleted_scope' => strtoupper(trim((string) ($request['deleted_scope'] ?? 'ACTIVE'))),
        ];
    }

    public function allocateBankWithdrawal(
        string $scheduleId,
        string $evidenceId,
        mixed $amount,
        ?string $memo = null
    ): array {
        $allocation = $this->positiveAmount($amount);
        $actor = ActorHelper::user();
        return $this->transaction(function () use ($scheduleId, $evidenceId, $allocation, $memo, $actor): array {
            $schedule = $this->schedules->lock($scheduleId);
            if (!$schedule || $schedule['deleted_at'] !== null) {
                throw new \RuntimeException('삭제되었거나 존재하지 않는 지급예정입니다.');
            }
            if ((int) $schedule['is_on_hold'] === 1) {
                throw new \RuntimeException('지급보류 상태에서는 실제 지급을 연결할 수 없습니다.');
            }
            $lifecycle = strtoupper((string) ($schedule['obligation_lifecycle_status'] ?? 'ACTIVE'));
            if ($lifecycle === 'CANCELLED') {
                throw new \RuntimeException('취소된 지급의무에는 실제 지급을 연결할 수 없습니다.');
            }
            if ($lifecycle === 'REVIEW_REQUIRED') {
                throw new \RuntimeException('검토가 필요한 지급의무에는 검토 완료 전 새 지급을 연결할 수 없습니다.');
            }

            $evidence = $this->bankEvidence->lockWithdrawal($evidenceId);
            if (!$evidence
                || (float) $evidence['withdraw_amount'] <= 0
                || (float) $evidence['deposit_amount'] > 0) {
                throw new \RuntimeException('유효한 은행 출금 원본만 지급에 연결할 수 있습니다.');
            }
            if (strtoupper(trim((string) ($evidence['currency'] ?? 'KRW'))) !== 'KRW') {
                throw new \RuntimeException('현재 지급예정은 원화 은행 출금만 연결할 수 있습니다.');
            }
            $accountWarning = '';
            $plannedAccountId = trim((string) ($schedule['payment_bank_account_id'] ?? ''));
            $evidenceAccountId = trim((string) ($evidence['bank_account_id'] ?? ''));
            if ($plannedAccountId !== '' && $evidenceAccountId !== '' && $plannedAccountId !== $evidenceAccountId) {
                $accountWarning = '예정 지급계좌와 실제 출금계좌가 다릅니다. 계좌 정보는 자동 변경하지 않았습니다.';
            }

            $evidenceLinks = $this->links->lockPaymentAllocationsByEvidence($evidenceId);
            $scheduleLinks = $this->links->lockPaymentAllocationsBySchedule($scheduleId);
            $currentEvidenceSchedule = $this->currentPairAmount($evidenceLinks, $scheduleId);
            $currentScheduleEvidence = $this->currentPairAmount($scheduleLinks, $scheduleId, $evidenceId);
            $evidenceAllocated = $this->sumAmounts($evidenceLinks) - $currentEvidenceSchedule + $allocation;
            $scheduleAllocated = $this->sumAmounts($scheduleLinks) - $currentScheduleEvidence + $allocation;

            if ($evidenceAllocated > (float) $evidence['withdraw_amount'] + 0.00001) {
                throw new \RuntimeException('실제 출금액을 초과하여 배분할 수 없습니다.');
            }
            if ($scheduleAllocated > (float) $schedule['scheduled_amount'] + 0.00001) {
                throw new \RuntimeException('지급예정액을 초과하여 배분할 수 없습니다.');
            }

            $linkId = $this->links->upsertPaymentAllocation(
                $evidenceId,
                $scheduleId,
                number_format($allocation, 2, '.', ''),
                $memo
            );
            $after = $this->schedules->find($scheduleId) ?: [];
            $this->histories->record($scheduleId, 'PAYMENT_LINKED', null, [
                'link_id' => $linkId,
                'evidence_type' => 'BANK_TRANSACTION',
                'evidence_id' => $evidenceId,
                'amount' => $allocation,
            ], trim(implode(' ', array_filter([$memo, $accountWarning]))) ?: null, $actor);
            $projected = $this->projectStatus($after);
            $projected['warnings'] = $accountWarning !== '' ? [$accountWarning] : [];
            return $projected;
        });
    }

    public function releaseAllocation(string $scheduleId, string $linkId, ?string $reason = null): array
    {
        $actor = ActorHelper::user();
        return $this->transaction(function () use ($scheduleId, $linkId, $reason, $actor): array {
            $schedule = $this->schedules->lock($scheduleId);
            if (!$schedule || $schedule['deleted_at'] !== null) {
                throw new \RuntimeException('삭제되었거나 존재하지 않는 지급예정입니다.');
            }
            $links = $this->links->lockPaymentAllocationsBySchedule($scheduleId);
            $before = null;
            foreach ($links as $link) {
                if ((string) $link['id'] === $linkId) {
                    $before = $link;
                    break;
                }
            }
            if (!$before || $this->links->softDeletePaymentAllocation($linkId) !== 1) {
                throw new \RuntimeException('해제할 지급 연결이 없습니다.');
            }
            $this->histories->record($scheduleId, 'PAYMENT_UNLINKED', $before, null, $reason, $actor);
            return $this->projectStatus($this->schedules->find($scheduleId) ?: []);
        });
    }

    public function projectStatus(array $row, ?\DateTimeImmutable $asOf = null): array
    {
        $paid = (float) ($row['paid_amount'] ?? 0);
        $remaining = max(0.0, (float) ($row['scheduled_amount'] ?? 0) - $paid);
        $today = ($asOf ?? new \DateTimeImmutable('today'))->format('Y-m-d');
        $status = $remaining <= 0.00001 ? '지급완료' : ($paid > 0 ? '일부지급' : '지급대기');
        $dueDate = trim((string) ($row['payment_due_date'] ?? ''));
        if ($remaining > 0.00001 && $dueDate !== '' && $dueDate < $today) {
            $status = '연체';
        }
        if ((int) ($row['is_on_hold'] ?? 0) === 1) {
            $status = '지급보류';
        }
        $lifecycle = strtoupper((string) ($row['obligation_lifecycle_status'] ?? 'ACTIVE'));
        if ($lifecycle === 'CANCELLED') {
            $status = '취소';
        } elseif ($lifecycle === 'REVIEW_REQUIRED') {
            $status = '검토필요';
        }
        $row['paid_amount'] = $paid;
        $row['remaining_amount'] = $remaining;
        $row['payment_status'] = $status;
        $row['payment_status_code'] = match ($status) {
            '지급완료' => 'COMPLETED',
            '일부지급' => 'PARTIAL',
            '연체' => 'OVERDUE',
            '지급보류' => 'ON_HOLD',
            '취소' => 'CANCELLED',
            '검토필요' => 'REVIEW_REQUIRED',
            default => 'WAITING',
        };
        $row['payment_status_label'] = $status;
        $row['is_overdue'] = $status === '연체';
        $row['overdue_days'] = $row['is_overdue'] && $dueDate !== ''
            ? max(0, (int) (($asOf ?? new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable($dueDate))->days))
            : 0;
        $row['payment_due_date_label'] = $dueDate !== '' ? $dueDate : '지급일 미정';
        $row['payment_bank_account_name'] = trim((string) ($row['payment_bank_account_name'] ?? '')) !== ''
            ? $row['payment_bank_account_name']
            : '지급계좌 미정';
        return $row;
    }

    private function normalizeSchedule(array $input): array
    {
        $dueDate = trim((string) ($input['payment_due_date'] ?? ''));
        $date = $dueDate === '' ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
        if ($dueDate !== '' && (!$date || $date->format('Y-m-d') !== $dueDate)) {
            throw new \InvalidArgumentException('지급예정일을 확인해 주세요.');
        }
        $sourceType = strtoupper(trim((string) ($input['source_type'] ?? '')));
        $sourceId = trim((string) ($input['source_id'] ?? ''));
        $sourceLineKey = trim((string) ($input['source_line_key'] ?? ''));
        if ($sourceType === '' || $sourceId === '' || $sourceLineKey === '') {
            throw new \InvalidArgumentException('지급예정일 계약이 있는 원천을 검색하여 선택해 주세요.');
        }
        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_line_key' => $sourceLineKey,
            'payment_due_date' => $dueDate !== '' ? $dueDate : null,
            'scheduled_amount' => number_format($this->positiveAmount($input['scheduled_amount'] ?? 0), 2, '.', ''),
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'project_id' => trim((string) ($input['project_id'] ?? '')),
            'assignee_id' => trim((string) ($input['assignee_id'] ?? '')),
            'payment_bank_account_id' => trim((string) ($input['payment_bank_account_id'] ?? '')),
            'memo' => trim((string) ($input['memo'] ?? '')),
        ];
    }

    private function validateReferences(array $data): void
    {
        if (!$this->schedules->sourceExists((string) $data['source_type'], (string) $data['source_id'])) {
            throw new \InvalidArgumentException('유효한 원천 자료를 확인할 수 없습니다.');
        }
        $references = [
            'client_id' => 'system_clients',
            'project_id' => 'system_projects',
            'assignee_id' => 'user_employees',
            'payment_bank_account_id' => 'system_bank_accounts',
        ];
        foreach ($references as $field => $table) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '' && !$this->schedules->referenceExists($table, $value)) {
                throw new \InvalidArgumentException('삭제되었거나 유효하지 않은 참조값입니다.');
            }
        }
    }

    private function changeHold(string $id, bool $hold, ?string $reason): array
    {
        $actor = ActorHelper::user();
        return $this->transaction(function () use ($id, $hold, $reason, $actor): array {
            $before = $this->schedules->lock($id);
            if (!$before || $before['deleted_at'] !== null) {
                throw new \RuntimeException('지급예정을 찾을 수 없습니다.');
            }
            if ($hold) {
                $paidAmount = $this->sumAmounts($this->links->lockPaymentAllocationsBySchedule($id));
                if ((float) $before['scheduled_amount'] - $paidAmount <= 0.00001) {
                    throw new \RuntimeException('지급완료된 항목은 지급보류로 변경할 수 없습니다.');
                }
            }
            $this->schedules->setHold($id, $hold, $reason, $actor);
            $after = $this->schedules->find($id) ?: [];
            $this->histories->record($id, $hold ? 'HELD' : 'RELEASED', $before, $after, $reason, $actor);
            return ActorHelper::enrichActorNamesRow($this->projectStatus($after), self::ACTOR_FIELDS);
        });
    }

    private function validDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }

    private function sort(array $request): array
    {
        $field = trim((string) ($request['sort_field'] ?? ''));
        $direction = trim((string) ($request['sort_direction'] ?? 'asc'));
        if ($field !== '') {
            return [$field, $direction];
        }
        $index = (int) ($request['order'][0]['column'] ?? -1);
        $column = $index >= 0 ? trim((string) ($request['columns'][$index]['data'] ?? '')) : '';
        return [$column !== '' ? $column : 'payment_due_date', (string) ($request['order'][0]['dir'] ?? 'asc')];
    }

    private function decodeJson(mixed $json): ?array
    {
        if (!is_string($json) || trim($json) === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function historySummary(array $history): string
    {
        return match ((string) ($history['action_type'] ?? '')) {
            'CREATED' => '지급예정 등록',
            'UPDATED' => '지급예정 정보 변경',
            'HELD' => '지급보류',
            'RELEASED' => '지급보류 해제',
            'PAYMENT_LINKED' => '은행 출금 연결',
            'PAYMENT_UNLINKED' => '은행 출금 연결 해제',
            'DELETED' => '지급예정 삭제',
            'RESTORED' => '지급예정 복원',
            default => (string) ($history['action_type'] ?? ''),
        };
    }

    private function positiveAmount(mixed $amount): float
    {
        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new \InvalidArgumentException('금액은 0보다 커야 합니다.');
        }
        return round((float) $amount, 2);
    }

    private function sumAmounts(array $links): float
    {
        return array_sum(array_map(static fn(array $link): float => (float) $link['amount'], $links));
    }

    private function currentPairAmount(array $links, string $scheduleId, ?string $evidenceId = null): float
    {
        foreach ($links as $link) {
            if ((string) $link['target_id'] === $scheduleId
                && ($evidenceId === null || (string) $link['evidence_id'] === $evidenceId)) {
                return (float) $link['amount'];
            }
        }
        return 0.0;
    }

    private function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->pdo->commit();
                $this->logger->info('지급일정 변경이 완료되었습니다.',['event_code'=>'PAYMENT_SCHEDULE_CHANGED','result'=>'SUCCESS','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user()]);
            }
            return $result;
        } catch (\PDOException $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if($ownsTransaction)$this->logger->error('지급일정 변경에 실패했습니다.',['event_code'=>'PAYMENT_SCHEDULE_CHANGE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);
            throw $e;
        } catch (\InvalidArgumentException|\DomainException|\RuntimeException $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {$this->pdo->rollBack();}
            if($ownsTransaction)$this->logger->warning('지급일정 변경이 차단되었습니다.',['event_code'=>'PAYMENT_SCHEDULE_CHANGE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);
            throw $e;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {$this->pdo->rollBack();}
            if($ownsTransaction)$this->logger->error('지급일정 변경에 실패했습니다.',['event_code'=>'PAYMENT_SCHEDULE_CHANGE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);
            throw $e;
        }
    }
}
