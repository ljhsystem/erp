<?php
// 경로: PROJECT_ROOT . '/app/controllers/ledger/JournalController.php'

namespace App\Controllers\Ledger;


use Core\Session;
use Core\DbPdo;
use App\Controllers\System\LayoutController;


class JournalController
{
    private LayoutController $layout;

    public function __construct()
    {
        Session::requireAuth();
        $this->layout = new LayoutController(DbPdo::conn());
    }

    /* ============================================================
     * 공통: 페이지 렌더링
     * ============================================================ */
    private function renderPage(string $viewPath, array $params = []): void
    {
        // 1️⃣ 컨트롤러 → 뷰 변수 전달
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        // 2️⃣ View 렌더링
        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        // 3️⃣ View에서 세팅한 값 우선
        $pageTitle     = $pageTitle     ?? '';
        $pageStyles    = $pageStyles    ?? '';
        $pageScripts   = $pageScripts   ?? '';
        $layoutOptions = $layoutOptions ?? [];

        // 4️⃣ 레이아웃 렌더
        $this->layout->render([
            'pageTitle'     => $pageTitle,
            'content'       => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles'    => $pageStyles,
            'pageScripts'   => $pageScripts,
        ]);
    }

    // ============================================================
    // WEB: 일반전표 입력 화면
    // URL: GET /ledger/journal
    // permission: web.ledger.journal.view
    // controller: JournalController@webIndex
    // ============================================================
    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/journal/journal.php', [
            'pageTitle' => '일반전표입력'
        ]);
    }

}