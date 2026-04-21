<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Controllers/Ledger/LedgerController.php'

namespace App\Controllers\Ledger;

use Core\DbPdo;
use App\Controllers\System\LayoutController;

class LedgerController
{
    private LayoutController $layout;

    public function __construct()
    {
        $this->layout = new LayoutController(DbPdo::conn());
    }

    /* ============================================================
     * 怨듯넻 ?뚮뜑
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
    * WEB: ?λ? ??쒕낫??
    * URL: GET /ledger
    * permission: web.ledger.index.view
    * controller: LedgerController@webIndex
    * ============================================================ */
    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/index.php', [
            'pageTitle' => '회계관리'
        ]);
    }





    /* ============================================================
    * WEB: 怨꾩젙怨쇰ぉ 愿由??붾㈃
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
    * WEB: ?꾪몴 愿由??붾㈃
    * URL: GET /ledger/vouchers
    * permission: web.ledger.voucher.view
    * controller: LedgerController@webVoucher
    * ============================================================ */
    /* ============================================================
    * WEB: ?쇰컲 ?꾪몴 ?낅젰 ?붾㈃
    * URL: GET /ledger/journal
    * permission: web.ledger.journal.view
    * controller: LedgerController@webJournal
    * ============================================================ */
    public function webJournal(): void
    {
        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => '?쇰컲?꾪몴?낅젰'
        ]);
    }
    public function webVoucher(): void
    {
        $this->renderPage('/app/views/ledger/voucher/index.php', [
            'pageTitle' => '전표 관리'
        ]);
    }

}

