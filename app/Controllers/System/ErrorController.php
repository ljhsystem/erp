<?php
// 경로: PROJECT_ROOT . '/app/controllers/system/ErrorController.php'
namespace App\Controllers\System;

class ErrorController
{
    private function render(int $code, string $message)
    {
        http_response_code($code);

        // AJAX 요청이면 JSON으로 응답
        if ($this->isAjax()) {
            echo json_encode([
                'success' => false,
                'code'    => $code,
                'message' => $message
            ]);
            return;
        }

        // 일반 페이지 렌더링
        $error = [
            'code'    => $code,
            'message' => $message
        ];

        require PROJECT_ROOT . '/app/views/errors/errors.php';
    }

    private function isAjax(): bool
    {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        );
    }

    public function error403()
    {
        $this->render(403, "접근 권한이 없습니다. 관리자에게 문의하세요.");
    }

    public function error404()
    {
        $this->render(404, "요청하신 페이지를 찾을 수 없습니다.");
    }

    public function error500()
    {
        $this->render(500, "서버 내부 오류가 발생했습니다.");
    }
}
