<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\QualificationEducationService;
use Core\DbPdo;
use PDO;

class QualificationEducationController
{
    private PDO $db;
    private QualificationEducationService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DbPdo::conn();
        $this->service = new QualificationEducationService($this->db);
    }

    public function webIndex(): void
    {
        $scope = $this->scope();
        $bootstrap = $this->service->options($scope)['data'];
        $capabilities = $this->capabilities();
        ob_start();
        require PROJECT_ROOT . '/app/views/institution/qualification-education/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->db))->render([
            'pageTitle' => '자격·교육관리',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiOptions(): void
    {
        $scope = $this->scope();
        $this->releaseSession();
        $this->respond(fn() => $this->service->options($scope));
    }

    public function apiQualificationList(): void
    {
        $input = $this->input();
        $scope = $this->scope();
        $this->releaseSession();
        $this->respond(fn() => $this->service->qualificationList($input, $scope));
    }

    public function apiQualificationDetail(): void { $this->respond(fn() => $this->service->qualificationDetail((string) ($_GET['id'] ?? ''), $this->scope())); }
    public function apiQualificationSave(): void { $this->respond(fn() => $this->service->saveQualification($this->input(), $_FILES)); }
    public function apiQualificationVerify(): void { $this->respond(fn() => $this->service->verifyQualification($this->input())); }
    public function apiQualificationRenew(): void { $this->respond(fn() => $this->service->renewQualification($this->input(), $_FILES)); }
    public function apiQualificationInvalidate(): void { $this->respond(fn() => $this->service->invalidateQualification($this->input())); }
    public function apiQualificationDelete(): void { $this->respond(fn() => $this->service->deleteQualification($this->input())); }

    public function apiEducationList(): void
    {
        $input = $this->input();
        $scope = $this->scope();
        $this->releaseSession();
        $this->respond(fn() => $this->service->educationList($input, $scope));
    }

    public function apiEducationDetail(): void { $this->respond(fn() => $this->service->educationDetail((string) ($_GET['id'] ?? ''), $this->scope())); }
    public function apiEducationSave(): void { $this->respond(fn() => $this->service->saveEducation($this->input(), $_FILES)); }
    public function apiEducationDelete(): void { $this->respond(fn() => $this->service->deleteEducation($this->input())); }
    public function apiEducationInvalidate(): void { $this->respond(fn() => $this->service->invalidateEducation($this->input())); }
    public function apiQualificationTypeList(): void { $this->respond(fn() => $this->service->qualificationTypeList()); }
    public function apiQualificationTypeSave(): void { $this->respond(fn() => $this->service->saveQualificationType($this->input())); }
    public function apiCourseList(): void { $this->respond(fn() => $this->service->courseList()); }
    public function apiCourseSave(): void { $this->respond(fn() => $this->service->saveCourse($this->input())); }
    public function apiRequirementList(): void { $this->respond(fn() => $this->service->requirementList((string) ($_GET['kind'] ?? ''), $_GET)); }

    public function apiRequirementSave(): void
    {
        $input = $this->input();
        $this->respond(fn() => $this->service->saveRequirement((string) ($input['kind'] ?? ''), $input));
    }

    public function apiPolicyReorder(): void
    {
        $input = $this->input();
        $this->respond(fn() => $this->service->reorderPolicy((string) ($input['kind'] ?? ''), $input));
    }

    private function scope(): ?string
    {
        $user = $this->user();
        $id = (string) ($user['id'] ?? '');
        $permission = new PermissionService();
        if ($permission->hasPermission($id, 'api.institution.human_resources.qualification_education.view_all')) {
            return null;
        }
        if ($permission->hasPermission($id, 'api.institution.human_resources.qualification_education.view_self')) {
            return $this->service->employeeIdForUser($id);
        }
        throw new \RuntimeException('자격·교육 조회 권한이 없습니다.');
    }

    private function capabilities(): array
    {
        $id = (string) ($this->user()['id'] ?? '');
        $permission = new PermissionService();
        $output = [];
        foreach (['view_self', 'view_all', 'save', 'delete', 'verify', 'renew', 'course_manage', 'education_manage', 'policy_manage'] as $key) {
            $output[$key] = $id !== '' && $permission->hasPermission(
                $id,
                'api.institution.human_resources.qualification_education.' . $key
            );
        }
        return $output;
    }

    private function user(): array { return (new AuthSessionService())->getCurrentUser() ?? []; }

    private function input(): array
    {
        if ($_POST) {
            return $_POST;
        }
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);
        return is_array($json) ? $json : [];
    }

    private function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function respond(callable $callback): void
    {
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $result = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (\Throwable) {
            $result = ['success' => false, 'message' => '자격·교육 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
