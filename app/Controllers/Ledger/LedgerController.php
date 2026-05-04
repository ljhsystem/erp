<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Ledger/LedgerController.php'

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use Core\DbPdo;
use Core\Router;

class LedgerController
{
    private LayoutController $layout;

    public function __construct()
    {
        $this->layout = new LayoutController(DbPdo::conn());
    }

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
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    public function webIndex(): void
    {
        $this->renderPage('/app/views/ledger/index.php', [
            'pageTitle' => '회계관리',
        ]);
    }

    public function webAccount(): void
    {
        $this->renderPage('/app/views/ledger/account/index.php', [
            'pageTitle' => '계정과목',
        ]);
    }

    public function webJournal(): void
    {
        $this->renderPage('/app/views/ledger/journal/index.php', [
            'pageTitle' => '전표입력',
        ]);
    }

    public function webVoucherReview(): void
    {
        $this->renderPage('/app/views/ledger/voucher/review.php', [
            'pageTitle' => '전표검토/승인',
        ]);
    }

    public function webDataUpload(): void
    {
        $this->renderPage('/app/views/ledger/data/upload.php', [
            'pageTitle' => '자료 업로드',
        ]);
    }

    public function webDataIndex(): void
    {
        $this->renderPage('/app/views/ledger/data/index.php', [
            'pageTitle' => '자료목록',
        ]);
    }

    public function webDataFormat(): void
    {
        $this->renderPage('/app/views/ledger/data/format.php', [
            'pageTitle' => '양식관리',
        ]);
    }

    public function webPlaceholder(): void
    {
        $meta = Router::currentRouteMeta();

        $this->renderPage('/app/views/ledger/placeholder.php', [
            'pageTitle' => $meta['name'] ?? '회계관리',
        ]);
    }
}
