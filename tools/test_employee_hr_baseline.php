<?php

declare(strict_types=1);

use App\Services\System\EmployeeService;
use App\Services\Institution\EmployeeHrBaselineService;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$service = new EmployeeService($pdo);
$baseline = new EmployeeHrBaselineService($pdo);
$users = new UserModel($pdo);
$employees = new EmployeeModel($pdo);
$checks = [];
$pdo->beginTransaction();

try {
    $departmentId = (string) $pdo->query('SELECT id FROM user_departments WHERE is_active=1 ORDER BY sort_no LIMIT 1')->fetchColumn();
    $positionId = (string) $pdo->query('SELECT id FROM user_positions WHERE is_active=1 ORDER BY sort_no LIMIT 1')->fetchColumn();
    if ($departmentId === '' || $positionId === '') {
        throw new RuntimeException('Fixture에 사용할 활성 부서 또는 직위·직책이 없습니다.');
    }

    $cases = [
        'active' => ['employment_status' => 'ACTIVE', 'real_hire_date' => date('Y-m-d', strtotime('-30 days'))],
        'pending' => ['employment_status' => 'PENDING_HIRE', 'doc_hire_date' => date('Y-m-d', strtotime('+30 days'))],
        'retired' => [
            'employment_status' => 'RETIRED',
            'real_hire_date' => date('Y-m-d', strtotime('-120 days')),
            'real_retire_date' => date('Y-m-d', strtotime('-30 days')),
        ],
    ];

    foreach ($cases as $name => $dates) {
        $token = substr(str_replace('.', '', uniqid('', true)), -12);
        $employeeData = array_merge([
            'username' => 'hr_fixture_' . $token,
            'employee_name' => '인사 Baseline Fixture',
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'job_id' => null,
            'email' => '',
        ], $dates);
        $userId = UuidHelper::generate();
        $employeeId = UuidHelper::generate();
        if (!$users->createUser([
            'id' => $userId, 'username' => $employeeData['username'],
            'password' => password_hash('Fixture!1234', PASSWORD_DEFAULT),
            'created_by' => 'SYSTEM:EMPLOYEE_HR_BASELINE_FIXTURE',
            'updated_by' => 'SYSTEM:EMPLOYEE_HR_BASELINE_FIXTURE',
        ])) {
            throw new RuntimeException($name . ' 사용자 생성 실패');
        }
        $employeeData['id'] = $employeeId;
        $employeeData['user_id'] = $userId;
        $employeeData['sort_no'] = 900000 + count($checks);
        if (!$employees->create($employeeData)) {
            throw new RuntimeException($name . ' 직원 Master 생성 실패');
        }
        $baseline->create($employeeId, $employeeData, 'SYSTEM:EMPLOYEE_HR_BASELINE_FIXTURE');
        foreach ([
            'institution_job_assignments_department_histories',
            'institution_job_assignments_position_histories',
            'institution_job_assignments_employment_status_histories',
        ] as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE employee_id=:id");
            $stmt->execute([':id' => $employeeId]);
            if ((int) $stmt->fetchColumn() < 1) {
                throw new RuntimeException($name . ' Baseline 누락: ' . $table);
            }
        }
        $checks[$name . '_baseline'] = true;
    }

    $existing = $pdo->query('SELECT * FROM user_employees ORDER BY sort_no LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        throw new RuntimeException('기존 직원 보호 검증 대상이 없습니다.');
    }
    $method = new ReflectionMethod(EmployeeService::class, 'validateProtectedHrFields');
    $same = $method->invoke($service, ['department_id' => $existing['department_id']], $existing);
    $changed = $method->invoke($service, ['department_id' => '00000000-0000-0000-0000-000000000000'], $existing);
    $missing = $method->invoke($service, [], $existing);
    $checks['same_value_allowed'] = $same === null;
    $checks['missing_value_preserved'] = $missing === null;
    $checks['changed_value_blocked'] = is_string($changed) && str_contains($changed, '인사발령관리');
    if (in_array(false, $checks, true)) {
        throw new RuntimeException('보호 컬럼 검증 결과가 기대와 다릅니다.');
    }

    $pdo->rollBack();
    echo json_encode(['success' => true, 'rolled_back' => true, 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
