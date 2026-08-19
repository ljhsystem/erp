<?php

namespace App\Controllers\Institution;

use App\Controllers\System\LayoutController;
use App\Services\Institution\EmploymentContractService;
use Core\DbPdo;
use Core\Session;
use PDO;

class EmploymentContractController
{
    private PDO $pdo;
    private EmploymentContractService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new EmploymentContractService($this->pdo);
    }

    public function webIndex(): void
    {
        ob_start();
        require PROJECT_ROOT . '/app/views/institution/employment-contract/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->pdo))->render([
            'pageTitle' => '근로계약관리',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void { $this->respond(fn(): array => $this->service->list(\Core\Helpers\DataTableRequestHelper::input())); }
    public function apiDetail(): void { $this->respond(fn(): array => $this->service->detail($this->queryId())); }
    public function apiOptions(): void { $this->respond(fn(): array => ['success'=>true,'data'=>$this->service->formOptions()]); }
    public function apiSave(): void { $this->respond(fn(): array => $this->service->save($this->input())); }
    public function apiReorder(): void { $input=$this->input();$this->respond(fn(): array => $this->service->reorder(is_array($input['changes']??null)?$input['changes']:[])); }
    public function apiSubmit(): void { $input=$this->input();$this->respond(fn(): array => $this->service->submit((string)($input['id']??''),(string)($input['request_key']??''))); }
    public function apiWithdraw(): void { $input=$this->input();$this->respond(fn(): array => $this->service->withdraw(trim((string) ($input['request_id'] ?? '')),(string)($input['request_key']??''))); }
    public function apiRevise(): void { $input=$this->input();$this->respond(fn(): array => $this->service->revise((string)($input['id']??''), (string) ($input['reason'] ?? ''),(string)($input['request_key']??''))); }
    public function apiTerminate(): void { $input=$this->input();$this->respond(fn(): array => $this->service->terminate((string)($input['id']??''), (string) ($input['reason'] ?? ''),(string)($input['request_key']??''))); }
    public function apiDelete(): void { $input=$this->input();$this->respond(fn(): array => $this->service->delete((string)($input['id']??''),(string)($input['request_key']??''))); }
    public function apiTrashList(): void { $this->respond(fn(): array => $this->service->trash($_GET)); }
    public function apiRestore(): void { $input=$this->input();$this->respond(fn(): array => $this->service->restore((string)($input['id']??''),(string)($input['request_key']??''))); }
    public function apiPurge(): void { $input=$this->input();$this->respond(fn(): array => $this->service->purge((string)($input['id']??''),(string)($input['request_key']??''))); }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function inputId(): string { return trim((string) ($this->input()['id'] ?? '')); }
    private function queryId(): string { return trim((string) ($_GET['id'] ?? '')); }

    private function respond(callable $callback): void
    {
        Session::write();
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $result = ['success' => false, 'message' => $exception->getMessage()];
            $status = 400;
        } catch (\Throwable $exception) {
            $result = ['success' => false, 'message' => '근로계약 처리 중 오류가 발생했습니다.'];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
