<?php
// 경로: PROJECT_ROOT . '/app/controllers/dashboard/settings/EmployeeSettingsController.php'
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\Security\Crypto;
use App\Models\Auth\AuthUserModel;
use App\Services\User\ProfileService;
use App\Services\Auth\AuthService;

class EmployeeSettingsController
{
    private \PDO $pdo;
    private AuthUserModel $userModel;
    private ProfileService $profileService;
    private AuthService $authService;

    public function __construct(\PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->userModel      = new AuthUserModel($pdo);
        $this->profileService = new ProfileService($pdo);
        $this->authService    = new AuthService($pdo);
    }

    // ============================================================
    // WEB: 직원 관리 페이지
    // URL: GET /dashboard/settings/employee
    // permission: settings.employee.view
    // controller: EmployeeSettingsController@webIndex
    // ============================================================
    public function webIndex()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/organization/employees.php';
    }

    // ============================================================
    // API: 직원 목록
    // URL: POST /api/settings/employee/list
    // permission: settings.employee.list
    // controller: EmployeeSettingsController@apiList
    // ============================================================
    public function apiList()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
            $filtersRaw = $_POST['filters'] ?? '[]';
            $filters = json_decode($filtersRaw, true);
    
            if (!is_array($filters)) {
                $filters = [];
            }
    
            $rows = $this->profileService->getAllProfiles($filters);

            /* 🔥 주민번호 복호화 추가 */
            $crypto = new Crypto();

            foreach ($rows as &$row) {
                $row['rrn'] = $crypto->decryptResidentNumber($row['rrn'] ?? null);
            }
            
            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '직원 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }

    // ============================================================
    // API: 직원 저장/수정/삭제
    // URL: POST /api/settings/employee/save
    // permission: settings.employee.save
    // controller: EmployeeSettingsController@apiSave
    // ============================================================
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        $action = $_POST['action'] ?? '';
        $id     = $_POST['id'] ?? '';

        try {

            switch ($action) {
                case 'create':     $result = $this->createEmployee(); break;
                case 'save':       $result = $this->updateEmployee($id); break;
                case 'deactivate': $result = $this->deactivateEmployee($id); break;
                case 'activate':   $result = $this->activateEmployee($id); break;
                case 'delete':     $result = $this->deleteEmployee($id); break;
                default: throw new \Exception("Invalid action");
            }

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '처리 중 오류 발생',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // 내부: 신규 직원 생성 (AuthService 로 이관됨)
    // 사용처: apiSave() → action=create
    // ============================================================
    private function createEmployee(): array
    {
        // POST 값 그대로 서비스에 전달
        $input = [
            'username'      => $_POST['username']      ?? '',
            'password'      => $_POST['password']      ?? '',
            'employee_name' => $_POST['employee_name'] ?? '',
            'role_id'       => $_POST['role_id']       ?? null,
            'department_id' => $_POST['department_id'] ?? null,
            'position_id'   => $_POST['position_id']   ?? null,
        ];

        // AuthService에서 트랜잭션 + UUID + INSERT 수행
        return $this->authService->createUserWithProfile($input);
    }

    // ============================================================
    // 내부: 직원 수정
    // 사용처: apiSave() 내부
    // ============================================================
    private function updateEmployee(string $userId): array
    {
        if (!$userId) {
            return ['success' => false, 'message' => 'user_id 누락'];
        }

        // =========================
        // username 중복 체크
        // =========================
        $newUsername = trim($_POST["username"] ?? "");

        if ($newUsername !== "") {

            $exists = $this->userModel->existsByUsername($newUsername);

            if ($exists) {

                // 본인 아이디면 통과
                $current = $this->userModel->getUsername($userId);

                if ($current !== $newUsername) {
                    return [
                        "success" => false,
                        "message" => "이미 사용중인 아이디입니다."
                    ];
                }
            }
        }
    
        /* ------------------------------------------
         * AUTH_USERS 데이터 준비
         * ------------------------------------------ */
        $authData = [
            "username"   => trim($_POST["username"] ?? ""),
            "email"      => trim($_POST["email"] ?? ""),
            "role_id"    => ($_POST["role_id"] ?? "") === "" ? null : $_POST["role_id"],
            "two_factor_enabled" => ($_POST["two_factor_enabled"] ?? "0") == "1" ? 1 : 0,
            "email_notify"       => ($_POST["email_notify"] ?? "0") == "1" ? 1 : 0,
            "sms_notify"         => ($_POST["sms_notify"] ?? "0") == "1" ? 1 : 0,
            "updated_by"         => $_SESSION["user"]["id"] ?? null,
        ];
    
        if (!empty($_POST["password"])) {
            $authData["password"] = password_hash($_POST["password"], PASSWORD_DEFAULT);
            $authData["password_updated_at"] = date('Y-m-d H:i:s');
            $authData["password_updated_by"] = $_SESSION["user"]["id"] ?? null;
        }
    
        /* ------------------------------------------
         * PROFILE 데이터 준비
         * ------------------------------------------ */
        $profileFields = [
            'employee_name','phone','address','address_detail',
            'department_id','position_id',
            'certificate_name','note','memo',
            'doc_hire_date','real_hire_date','doc_retire_date','real_retire_date',
            'emergency_phone'
        ];
        
        $profileData = [];
        foreach ($profileFields as $f) {
            if (array_key_exists($f, $_POST)) {
                $profileData[$f] = ($_POST[$f] === "") ? null : $_POST[$f];
            }
        }
        
        /* ------------------------------------------
         * 주민등록번호는 별도 처리
         * - 빈값이면 기존값 유지
         * - 값이 있으면 숫자만 남기고 암호화 저장
         * ------------------------------------------ */
        $rrnInput = trim((string)($_POST['rrn'] ?? ''));

        if (strpos($rrnInput, '*') !== false) {
            return [
                'success' => false,
                'message' => '마스킹된 주민번호는 저장할 수 없습니다.'
            ];
        }
        
        $rrnRaw = preg_replace('/\D+/', '', $rrnInput);
        
        if ($rrnRaw !== '') {
        
            $crypto = new Crypto();   // 🔥 핵심
        
            $profileData['rrn'] = $crypto->encryptResidentNumber($rrnRaw);
        }

        
        /* ============================================================
        * 1) 파일 업로드 먼저 처리
        * ============================================================ */
        if (!empty($_FILES['profile_image']['name'])) {
            $res = $this->profileService->updateProfileImage($userId, $_FILES['profile_image']);
            if (!empty($res['success']) && !empty($res['db_path'])) {
                $profileData['profile_image'] = $res['db_path'];
            }
        }

        if (!empty($_FILES['rrn_image']['name'])) {
            $res = $this->profileService->updateIdDocument($userId, $_FILES['rrn_image']);
            if (!empty($res['success']) && !empty($res['db_path'])) {
                $profileData['rrn_image'] = $res['db_path'];
            }
        }

        if (!empty($_FILES['certificate_file']['name'])) {
            $res = $this->profileService->updateCertificateFile($userId, $_FILES['certificate_file']);
            if (!empty($res['success']) && !empty($res['db_path'])) {
                $profileData['certificate_file'] = $res['db_path'];
            }
        }

        /* ============================================================
        * 2) 삭제 플래그 처리 (업로드 이후에 실행해야 한다!)
        * ============================================================ */
        $profile = $this->profileService->getProfile($userId);

        if (!empty($_POST['profile_image_delete']) && $_POST['profile_image_delete'] == "1") {

            // 업로드가 없을 때만 null 처리 (중복 방지)
            if (empty($profileData['profile_image'])) {
                if (!empty($profile['profile_image'])) {
                    $this->profileService->deleteFile($profile['profile_image']);
                }
                $profileData['profile_image'] = null;
            }
        }

        if (!empty($_POST['rrn_image_delete']) && $_POST['rrn_image_delete'] == "1") {

            if (empty($profileData['rrn_image'])) {
                if (!empty($profile['rrn_image'])) {
                    $this->profileService->deleteFile($profile['rrn_image']);
                }
                $profileData['rrn_image'] = null;
            }
        }

        if (!empty($_POST['certificate_file_delete']) && $_POST['certificate_file_delete'] == "1") {

            if (empty($profileData['certificate_file'])) {
                if (!empty($profile['certificate_file'])) {
                    $this->profileService->deleteFile($profile['certificate_file']);
                }
                $profileData['certificate_file'] = null;
            }
        }

    
        /* ------------------------------------------
         * 3) 트랜잭션 실행
         * ------------------------------------------ */
        $this->pdo->beginTransaction();
    
        try {
            $this->userModel->updateUserDirect($userId, $authData);
            $this->profileService->updateProfile($userId, $profileData);
    
            $this->pdo->commit();
            return ["success" => true];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return [
                "success" => false,
                "message" => "저장 실패",
                "error"   => $e->getMessage()
            ];
        }
    }
    

    // ============================================================
    // 내부: 직원 비활성화
    // ============================================================
    private function deactivateEmployee(string $userId): array
    {
        if (!$userId) return ['success' => false, 'message' => 'user_id 누락'];

        $adminId = $_SESSION['user']['id'] ?? null;

        $sql = "
            UPDATE auth_users
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :admin_id
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':admin_id' => $adminId,
            ':id'       => $userId
        ]);

        return ['success' => (bool)$ok, 'message' => '계정이 비활성화되었습니다.'];
    }

    // ============================================================
    // 내부: 직원 활성화
    // ============================================================
    private function activateEmployee(string $userId): array
    {
        if (!$userId) return ['success' => false, 'message' => 'user_id 누락'];

        $adminId = $_SESSION['user']['id'] ?? null;

        $sql = "
            UPDATE auth_users
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :admin_id
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':admin_id' => $adminId,
            ':id'       => $userId
        ]);

        return ['success' => (bool)$ok, 'message' => '계정이 활성화되었습니다.'];
    }

    // ============================================================
    // 내부: 직원 영구 삭제
    // ============================================================
    private function deleteEmployee(string $userId): array
    {
        if (!$userId) return ['success' => false, 'message' => 'user_id 누락'];

        $stmt = $this->pdo->prepare("DELETE FROM auth_users WHERE id = ?");
        $stmt->execute([$userId]);

        return ['success' => true, 'message' => '직원 삭제 완료'];
    }

    // =====// ============================================================
    // API: 직원 검색 (Select2용)
    // URL: GET /api/settings/employee/search?q=홍길동
    // controller: EmployeeSettingsController@apiSearch
    // ============================================================
    public function apiSearch()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
            $q = trim((string)($_GET['q'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 20);
    
            if ($limit < 1) $limit = 20;
            if ($limit > 100) $limit = 100;
    
            $rows = $this->profileService->searchEmployees($q, $limit);
    
            echo json_encode([
                'success' => true,
                'debug' => [
                    'controller' => __METHOD__,
                    'q' => $q,
                    'limit' => $limit,
                    'row_count' => count($rows),
                    'rows' => $rows,
                ],
                'results' => array_map(function ($row) {
                    $employeeName = trim((string)($row['employee_name'] ?? ''));
                    $username     = trim((string)($row['username'] ?? ''));
                    $department   = trim((string)($row['department_name'] ?? $row['dept_name'] ?? ''));
                    $position     = trim((string)($row['position_name'] ?? ''));
    
                    $mainName = $employeeName !== '' ? $employeeName : $username;
    
                    $subParts = array_filter([
                        $department,
                        $position,
                        $username !== '' ? '@' . $username : ''
                    ]);
    
                    return [
                        'id'              => $row['id'] ?? '',
                        'text'            => $mainName . (count($subParts) ? ' (' . implode(' / ', $subParts) . ')' : ''),
                        'employee_name'   => $employeeName,
                        'username'        => $username,
                        'department_name' => $department,
                        'position_name'   => $position,
                        'is_active'       => (int)($row['is_active'] ?? 0),
                    ];
                }, $rows)
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'debug' => [
                    'controller' => __METHOD__,
                    'q' => $_GET['q'] ?? null,
                ],
                'message' => '직원 검색 실패',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'results' => []
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }


    // ============================================================
    // API: 직원 순서 변경
    // URL: POST /api/settings/employee/reorder
    // ============================================================
    public function apiReorder(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $payload = json_decode(file_get_contents('php://input'), true);
            $changes = $payload['changes'] ?? [];
    
            if (!$changes) {
                echo json_encode([
                    'success' => false,
                    'message' => '변경 데이터 없음'
                ]);
                return;
            }
    
            $this->profileService->reorderProfiles($changes);
    
            echo json_encode([
                'success' => true
            ]);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '순서 저장 실패',
                'error'   => $e->getMessage()
            ]);
        }
    
        exit;
    }
}
