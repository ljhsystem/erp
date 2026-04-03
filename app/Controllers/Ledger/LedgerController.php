<?php
// 경로: PROJECT_ROOT . '/app/controllers/ledger/LedgerController.php'

namespace App\Controllers\Ledger;

use Core\Session;
use Core\DbPdo;
use App\Controllers\System\LayoutController;

class LedgerController
{
    private LayoutController $layout;

    public function __construct()
    {
        Session::requireAuth();
        $this->layout = new LayoutController(DbPdo::conn());
    }

    /* ============================================================
     * 공통 렌더
     * ============================================================ */

    private function renderPage(string $viewPath, array $params = []): void
    {
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle ?? '',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
            'layoutOptions' => $layoutOptions ?? []
        ]);
    }

   /* ============================================================
    * WEB: 장부 대시보드
    * URL: GET /ledger
    * permission: web.ledger.index.view
    * controller: LedgerController@webIndex
    * ============================================================ */
    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/index.php', [
            'pageTitle' => '거래원장 대시보드'
        ]);
    }





    /* ============================================================
    * WEB: 계정과목 관리 화면
    * URL: GET /ledger/accounts
    * permission: web.ledger.account.view
    * controller: LedgerController@webAccount
    * ============================================================ */
    public function webAccount(): void
    {
        $this->renderPage('/app/views/ledger/account/index.php', [
            'pageTitle' => '계정과목 관리'
        ]);
    }


    /* ============================================================
    * WEB: 전표 관리 화면
    * URL: GET /ledger/vouchers
    * permission: web.ledger.voucher.view
    * controller: LedgerController@webVoucher
    * ============================================================ */
    public function webVoucher(): void
    {
        $this->renderPage('/app/views/ledger/voucher/index.php', [
            'pageTitle' => '전표 관리'
        ]);
    }

}