<?php

namespace App\Controllers\Approval;

use App\Controllers\System\LayoutController;
use App\Services\Approval\ApprovalInboxService;
use App\Services\Institution\RegularEmploymentIncomeAccountingException;
use Core\DbPdo;
use Core\Helpers\UuidHelper;
use PDO;

class ApprovalInboxController
{
    private PDO $pdo;
    private ApprovalInboxService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
        $this->service = new ApprovalInboxService($this->pdo);
    }

    public function webIndex(): void
    {
        ob_start();
        require PROJECT_ROOT . '/app/views/approval/inbox/index.php';
        $content = ob_get_clean();
        (new LayoutController($this->pdo))->render([
            'pageTitle' => '결재함',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
        ]);
    }

    public function apiList(): void
    {
        $this->respond(fn(): array => $this->service->list(\Core\Helpers\DataTableRequestHelper::input()), '결재함을 조회할 수 없습니다.');
    }

    public function apiDetail(): void
    {
        $this->respond(
            fn(): array => $this->service->detail(trim((string) ($_GET['request_id'] ?? ''))),
            '결재문서를 불러올 수 없습니다.'
        );
    }

    public function apiAct(): void
    {
        $this->respond(function (): array {
            $input = $this->input();
            return $this->service->act(
                trim((string) ($input['step_id'] ?? '')),
                trim((string) ($input['decision'] ?? '')),
                isset($input['comment']) ? (string) $input['comment'] : null
            );
        }, '결재 처리 중 오류가 발생했습니다.');
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function respond(callable $callback, string $failureMessage): void
    {
        try {
            $result = $callback();
            $status = empty($result['success']) ? 400 : 200;
        } catch (RegularEmploymentIncomeAccountingException $exception) {
            $correlationId=$exception->correlationId()??UuidHelper::generate();
            $userMessage='급여 증빙과 관련 거래를 생성하기 위한 업무 검증을 완료하지 못했습니다. 문서 내용을 확인해 주세요.';
            $result=['success'=>false,'error_code'=>$exception->errorCode(),'result_code'=>$exception->errorCode(),'correlation_id'=>$correlationId,'message'=>$userMessage,'user_message'=>$userMessage];
            $status=400;
        } catch (\InvalidArgumentException $exception) {
            $result = ['success' => false, 'result_code' => 'VALIDATION_FAILED', 'message' => $exception->getMessage(), 'user_message' => $exception->getMessage()];
            $status = 400;
        } catch (\RuntimeException $exception) {
            $correlationId=UuidHelper::generate();
            $result = ['success' => false, 'result_code' => 'APPROVAL_PROCESSING_FAILED', 'correlation_id' => $correlationId, 'message' => $failureMessage, 'user_message' => $failureMessage];
            $status = 400;
        } catch (\Throwable $exception) {
            $correlationId=UuidHelper::generate();
            $result = ['success' => false, 'result_code' => 'APPROVAL_SYSTEM_ERROR', 'correlation_id' => $correlationId, 'message' => $failureMessage, 'user_message' => $failureMessage];
            $status = 500;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
