<?php
namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\Institution\EmploymentRuleService;
use Core\DbPdo;
use PDO;

class EmploymentRuleController
{
    private PDO $db;
    private EmploymentRuleService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? DbPdo::conn();
        $this->service = new EmploymentRuleService($this->db);
    }

    public function webIndex(): void
    {
        $options = $this->service->options()['data'];
        $cap = $this->capabilities();
        ob_start();
        require PROJECT_ROOT . '/app/views/institution/employment-rules/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->db))->render([
            'pageTitle' => '취업규칙·인사규정',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void { $this->respond(fn() => $this->service->list($_GET)); }
    public function apiDetail(): void { $this->respond(fn() => $this->service->detail((string) ($_GET['id'] ?? ''))); }
    public function apiHistory(): void { $this->respond(fn() => $this->service->history((string) ($_GET['rule_id'] ?? ''))); }
    public function apiOptions(): void { $this->respond(fn() => $this->service->options()); }
    public function apiSave(): void { $this->respond(fn() => $this->service->save($this->input())); }
    public function apiRevise(): void { $this->respond(fn() => $this->service->revise($this->input())); }
    public function apiSubmit(): void { $input = $this->input(); $this->respond(fn() => $this->service->submit((string) ($input['id'] ?? ''), $this->userId())); }
    public function apiWithdraw(): void { $input = $this->input(); $this->respond(fn() => $this->service->withdraw((string) ($input['request_id'] ?? ''), $this->userId())); }
    public function apiActivate(): void { $input = $this->input(); $this->respond(fn() => $this->service->activate((string) ($input['id'] ?? ''))); }
    public function apiDelete(): void { $input = $this->input(); $this->respond(fn() => $this->service->delete((string) ($input['id'] ?? ''))); }
    public function apiExcel(): void { $this->service->excel($_GET); }

    public function act(string $stepId, string $decision, ?string $comment): array
    {
        return $this->service->act($stepId, $decision, $comment, $this->userId());
    }

    private function userId(): string
    {
        return (string) ((new AuthSessionService())->getCurrentUser()['id'] ?? '');
    }

    private function capabilities(): array
    {
        $permission = new PermissionService();
        $userId = $this->userId();
        $result = [];
        foreach (['save', 'delete', 'revise', 'submit', 'withdraw', 'activate', 'history', 'excel'] as $key) {
            $result[$key] = $permission->hasPermission($userId, 'api.institution.human_resources.employment_rules.' . $key);
        }
        return $result;
    }

    private function input(): array
    {
        if ($_POST) return $_POST;
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($json) ? $json : [];
    }

    private function respond(callable $callback): void
    {
        try {
            $result = $callback();
            $status = 200;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
            $status = 400;
        } catch (\Throwable $e) {
            error_log('[EmploymentRuleController] ' . $e->getMessage());
            $result = ['success' => false, 'message' => '취업규칙 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
