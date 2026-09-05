<?php

namespace App\Controllers\Ledger;

use App\Controllers\System\LayoutController;
use Core\DbPdo;

class LedgerController
{
    private LayoutController $layout;

    public function __construct(?\PDO $pdo = null)
    {
        $this->layout = new LayoutController($pdo ?? DbPdo::conn());
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
            'pageTitle' => '전표검토 및 확인',
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
        $this->renderPage('/app/views/ledger/data/list.php', [
            'pageTitle' => '증빙원본',
        ]);
    }

    public function webDataList(): void
    {
        $this->renderPage('/app/views/ledger/data/list.php', [
            'pageTitle' => '증빙원본',
        ]);
    }

    public function webDataRaw(): void
    {
        $this->renderPage('/app/views/ledger/data/raw.php', [
            'pageTitle' => '원본자료',
        ]);
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
            'pageAssetProfile' => $pageAssetProfile ?? 'default',
        ]);
    }
}
