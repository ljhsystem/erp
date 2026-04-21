<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Ledger/JournalController.php'

namespace App\Controllers\Ledger;


use Core\DbPdo;
use App\Controllers\System\LayoutController;


class JournalController
{
    private LayoutController $layout;

    public function __construct()
    {
        $this->layout = new LayoutController(DbPdo::conn());
    }

    /* ============================================================
     * 怨듯넻: ?섏씠吏 ?뚮뜑留?
     * ============================================================ */
    private function renderPage(string $viewPath, array $params = []): void
    {
        // 1截뤴깵 而⑦듃濡ㅻ윭 ??酉?蹂???꾨떖
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        // 2截뤴깵 View ?뚮뜑留?
        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        // 3截뤴깵 View?먯꽌 ?명똿??媛??곗꽑
        $pageTitle     = $pageTitle     ?? '';
        $pageStyles    = $pageStyles    ?? '';
        $pageScripts   = $pageScripts   ?? '';
        $layoutOptions = $layoutOptions ?? [];

        // 4截뤴깵 ?덉씠?꾩썐 ?뚮뜑
        $this->layout->render([
            'pageTitle'     => $pageTitle,
            'content'       => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles'    => $pageStyles,
            'pageScripts'   => $pageScripts,
        ]);
    }

    // ============================================================
    // WEB: ?쇰컲?꾪몴 ?낅젰 ?붾㈃
    // URL: GET /ledger/journal
    // permission: web.ledger.journal.view
    // controller: JournalController@webIndex
    // ============================================================
    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => '일반전표입력'
        ]);
    }

}

