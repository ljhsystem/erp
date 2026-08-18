<?php

namespace App\Services\Institution;

use App\Repositories\Institution\PersonnelActionRepository;
use Core\Helpers\UuidHelper;
use PDO;

class EmployeeHrBaselineService
{
    private const HISTORY_TABLES = [
        'department_id' => 'institution_job_assignments_department_histories',
        'position_id' => 'institution_job_assignments_position_histories',
        'job_id' => 'institution_job_assignments_job_histories',
        'employment_status' => 'institution_job_assignments_employment_status_histories',
    ];

    private PersonnelActionRepository $repository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new PersonnelActionRepository($pdo);
    }

    public function create(string $employeeId, array $employee, string $actor): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new \LogicException('직원 인사 Baseline은 직원 생성 트랜잭션 안에서만 생성할 수 있습니다.');
        }
        if (!$this->repository->employee($employeeId, true)) {
            throw new \RuntimeException('신규 직원 Master를 확인할 수 없습니다.');
        }
        foreach (self::HISTORY_TABLES as $table) {
            if ($this->repository->employeeHistoryExists($table, $employeeId)) {
                throw new \RuntimeException('이미 인사 Baseline이 존재하는 직원입니다.');
            }
        }

        $startDate = $this->date($employee['real_hire_date'] ?? null)
            ?? $this->date($employee['doc_hire_date'] ?? null);
        if ($startDate === null) {
            throw new \InvalidArgumentException('실입사일 또는 문서상 입사일을 입력해 주세요.');
        }

        $status = strtoupper(trim((string) ($employee['employment_status'] ?? '')));
        if (!in_array($status, ['PENDING_HIRE', 'ACTIVE', 'RETIRED'], true)) {
            throw new \InvalidArgumentException('신규 직원의 재직상태는 입사예정, 재직 또는 퇴직만 선택할 수 있습니다.');
        }

        $retireDate = $this->date($employee['real_retire_date'] ?? null)
            ?? $this->date($employee['doc_retire_date'] ?? null);
        $today = date('Y-m-d');
        if ($status === 'PENDING_HIRE' && $startDate <= $today) {
            throw new \InvalidArgumentException('입사예정 직원의 입사 기준일은 오늘 이후여야 합니다.');
        }
        if ($status === 'ACTIVE' && ($startDate > $today || $retireDate !== null)) {
            throw new \InvalidArgumentException('재직 직원은 오늘까지 입사한 직원이어야 하며 퇴사일을 입력할 수 없습니다.');
        }
        if ($status === 'RETIRED' && ($retireDate === null || $retireDate <= $startDate || $retireDate > $today)) {
            throw new \InvalidArgumentException('퇴직 직원은 입사일 이후이면서 오늘 이전인 퇴사일이 필요합니다.');
        }

        $periodEnd = $status === 'RETIRED' ? $retireDate : null;
        $this->insertPeriod($employeeId, 'department_id', $employee['department_id'] ?? null, $startDate, $periodEnd, $actor);
        $this->insertPeriod($employeeId, 'position_id', $employee['position_id'] ?? null, $startDate, $periodEnd, $actor);
        $this->insertJob($employeeId, $employee['job_id'] ?? null, $startDate, $periodEnd, $status, $actor);
        $this->insertEmploymentStatus($employeeId, $startDate, $retireDate, $status, $actor);
    }

    private function insertPeriod(string $employeeId, string $field, mixed $value, string $start, ?string $end, string $actor): void
    {
        $id = trim((string) $value);
        if ($id === '') {
            return;
        }
        $kind = $field === 'department_id' ? 'department' : 'position';
        if (!$this->repository->referenceExists($kind, $id)) {
            throw new \InvalidArgumentException(($kind === 'department' ? '부서' : '직위·직책') . ' 선택값이 유효하지 않습니다.');
        }
        $this->repository->insertHistory(self::HISTORY_TABLES[$field], [
            'id' => UuidHelper::generate(), 'employee_id' => $employeeId, $field => $id,
            'effective_from' => $start, 'effective_to' => $end,
            'start_action_target_id' => null, 'end_action_target_id' => null, 'created_by' => $actor,
        ]);
    }

    private function insertJob(string $employeeId, mixed $value, string $start, ?string $end, string $employmentStatus, string $actor): void
    {
        $jobId = trim((string) $value);
        if ($jobId === '') {
            return;
        }
        if (!$this->repository->referenceExists('job', $jobId)) {
            throw new \InvalidArgumentException('직무 선택값이 유효하지 않습니다.');
        }
        $status = $employmentStatus === 'PENDING_HIRE' ? 'PLANNED' : ($employmentStatus === 'RETIRED' ? 'ENDED' : 'ACTIVE');
        $this->repository->insertHistory(self::HISTORY_TABLES['job_id'], [
            'id' => UuidHelper::generate(), 'employee_id' => $employeeId, 'job_id' => $jobId,
            'start_date' => $start, 'end_date' => $end, 'status_code' => $status,
            'assignment_personnel_action_target_id' => null, 'end_personnel_action_target_id' => null, 'created_by' => $actor,
        ]);
    }

    private function insertEmploymentStatus(string $employeeId, string $start, ?string $retire, string $status, string $actor): void
    {
        $initialStatus = $status === 'PENDING_HIRE' ? 'PENDING_HIRE' : 'ACTIVE';
        $initialEnd = $status === 'RETIRED' ? date('Y-m-d', strtotime($retire . ' -1 day')) : null;
        $this->repository->insertHistory(self::HISTORY_TABLES['employment_status'], [
            'id' => UuidHelper::generate(), 'employee_id' => $employeeId, 'status_code' => $initialStatus,
            'effective_date' => $start, 'ended_date' => $initialEnd,
            'reason' => '신규 직원 인사 Baseline', 'source_personnel_action_target_id' => null, 'created_by' => $actor,
        ]);
        if ($status === 'RETIRED') {
            $this->repository->insertHistory(self::HISTORY_TABLES['employment_status'], [
                'id' => UuidHelper::generate(), 'employee_id' => $employeeId, 'status_code' => 'RETIRED',
                'effective_date' => $retire, 'ended_date' => null,
                'reason' => '신규 퇴직 직원 인사 Baseline', 'source_personnel_action_target_id' => null, 'created_by' => $actor,
            ]);
        }
    }

    private function date(mixed $value): ?string
    {
        $date = trim((string) $value);
        return $date === '' ? null : $date;
    }
}
