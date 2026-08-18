<?php

namespace App\Controllers\Dashboard\Settings;

use App\Controllers\System\LayoutController;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use App\Services\System\SettingsNavigationService;
use App\Services\System\StatutoryStandardService;
use Core\DbPdo;
use PDO;

class StatutoryStandardController
{
    private PDO $db;
    private StatutoryStandardService $service;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DbPdo::conn();
        $this->service = new StatutoryStandardService($this->db);
    }

    public function index(): void
    {
        $options = $this->service->options()['data'];
        $cap = $this->capabilities();
        $cat = 'standard';
        $sub = 'statutory-standards';
        $navigation = (new SettingsNavigationService($this->db))->getViewData();
        $settingsMenuRows = $navigation['settingsMenuRows'];
        $settingsPermissionAllowed = $navigation['settingsPermissionAllowed'];
        ob_start();
        require PROJECT_ROOT . '/app/views/dashboard/settings.php';
        $content = ob_get_clean();
        (new LayoutController($this->db))->render([
            'pageTitle' => '법정기준관리',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void { $this->respond(fn(): array => $this->service->list($_GET)); }
    public function apiDetail(): void { $this->respond(fn(): array => $this->service->detail((string) ($_GET['id'] ?? ''))); }
    public function apiOptions(): void { $this->respond(fn(): array => $this->service->options()); }
    public function apiSave(): void
    {
        $this->respond(fn(): array => $this->service->save($this->input(), $this->sourceFiles()));
    }
    public function apiDelete(): void
    {
        $input = $this->input();
        $this->respond(fn(): array => isset($input['ids']) && is_array($input['ids'])
            ? $this->service->deleteMany($input['ids'])
            : $this->service->delete((string) ($input['id'] ?? '')));
    }
    public function apiReorder(): void
    {
        $input = $this->input();
        $this->respond(fn(): array => $this->service->reorder((array) ($input['changes'] ?? [])));
    }
    public function apiResolve(): void { $this->respond(fn(): array => $this->service->resolve($_GET)); }

    public function apiSourceFile(): void
    {
        try {
            $file = $this->service->sourceFile((string) ($_GET['id'] ?? ''));
            $name = str_replace(["\r", "\n", '"'], '', $file['name']);
            header('Content-Type: ' . $file['mime']);
            header('Content-Length: ' . $file['size']);
            header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($name));
            header('X-Content-Type-Options: nosniff');
            readfile($file['path']);
        } catch (\Throwable $exception) {
            http_response_code(404);
            echo '근거자료 파일을 찾을 수 없습니다.';
        }
    }

    private function currentUserId(): string
    {
        return (string) ((new AuthSessionService())->getCurrentUser()['id'] ?? '');
    }

    private function capabilities(): array
    {
        $permissions = new PermissionService();
        $userId = $this->currentUserId();
        return [
            'save' => $permissions->hasPermission($userId, 'api.settings.statutory_standards.save'),
            'delete' => $permissions->hasPermission($userId, 'api.settings.statutory_standards.delete'),
        ];
    }

    private function input(): array
    {
        if ($_POST) {
            return $_POST;
        }
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($json) ? $json : [];
    }

    private function sourceFiles(): array
    {
        $files = $_FILES['source_files'] ?? null;
        if (!is_array($files) || !is_array($files['name'] ?? null)) {
            return [];
        }
        $result = [];
        foreach ($files['name'] as $index => $name) {
            $result[(int) $index] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $result;
    }

    private function respond(callable $callback): void
    {
        try {
            $result = $callback();
            $status = 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $result = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (\Throwable $exception) {
            error_log('[StatutoryStandard] ' . $exception->getMessage());
            $result = ['success' => false, 'message' => '법정기준 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
