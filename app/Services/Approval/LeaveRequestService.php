<?php

namespace App\Services\Approval;

use App\Services\Auth\AuthSessionService;
use App\Services\Institution\LeaveService;
use Core\Helpers\DataTableRequestHelper;
use PDO;

class LeaveRequestService
{
    private LeaveService $leave;

    public function __construct(PDO $pdo)
    {
        $this->leave = new LeaveService($pdo);
    }

    public function options(): array
    {
        $result = $this->leave->options();
        unset($result['data']['employees']);
        return $result;
    }

    public function list(): array
    {
        return $this->leave->list(DataTableRequestHelper::input(), $this->employeeId());
    }

    public function detail(string $id): array
    {
        return $this->leave->detailOwned($id);
    }

    public function save(array $input): array
    {
        unset($input['employee_id']);
        return $this->leave->save($input, $this->employeeId());
    }

    public function saveAndSubmit(array $input): array
    {
        $saved = $this->save($input);
        return $this->leave->submit((string) ($saved['data']['id'] ?? ''));
    }

    public function submit(string $id): array
    {
        return $this->leave->submit($id);
    }

    public function withdraw(string $approvalRequestId): array
    {
        return $this->leave->withdraw($approvalRequestId);
    }

    public function cancelRequest(array $input): array
    {
        return $this->leave->cancel(
            trim((string) ($input['id'] ?? '')),
            trim((string) ($input['request_key'] ?? '')),
            trim((string) ($input['reason'] ?? ''))
        );
    }

    private function employeeId(): string
    {
        $user = (new AuthSessionService())->getCurrentUser() ?? [];
        return $this->leave->employeeIdForUser((string) ($user['id'] ?? ''));
    }
}
