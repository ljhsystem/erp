<?php

namespace App\Services\System;

use App\Models\System\ClientModel;
use App\Models\User\EmployeeModel;
use PDO;

class ProjectReferenceResolver
{
    private ClientModel $clientModel;
    private EmployeeModel $employeeModel;

    public function __construct(
        private readonly PDO $pdo
    ) {
        $this->clientModel = new ClientModel($pdo);
        $this->employeeModel = new EmployeeModel($pdo);
    }

    public function resolveClientIdByName(string $clientName): ?string
    {
        $clientName = trim($clientName);

        if ($clientName === '') {
            return null;
        }

        return $this->clientModel->findIdByClientName($clientName);
    }

    public function resolveEmployeeIdByName(string $employeeName): ?string
    {
        $employeeName = trim($employeeName);

        if ($employeeName === '') {
            return null;
        }

        return $this->employeeModel->findActiveIdByEmployeeName($employeeName);
    }
}
