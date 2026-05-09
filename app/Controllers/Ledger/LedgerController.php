<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use Core\DbPdo;
use Core\Router;

class LedgerController
{
    private LayoutController $layout;

    public function __construct(?\PDO $pdo = null)
    {
        $this->layout = new LayoutController($pdo ?? DbPdo::conn());
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($params !== []) {
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

    public function webJournalRules(): void
    {
        $this->renderPage('/app/views/ledger/journal_rules/index.php', [
            'pageTitle' => '분개규칙',
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
            'pageTitle' => '자료업로드',
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
        $title = $meta['page_title'] ?? $meta['menu_label'] ?? $meta['name'] ?? '회계관리';

        $this->renderPage('/app/views/ledger/placeholder.php', [
            'pageTitle' => $title,
        ]);
    }
}
