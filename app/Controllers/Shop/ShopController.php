<?php

namespace App\Controllers\Shop;

use App\Controllers\System\LayoutController;
use Core\DbPdo;

class ShopController
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
            'pageTitle' => $pageTitle ?? '쇼핑몰관리',
            'content' => $content,
            'pageStyles' => $pageStyles ?? '',
            'pageScripts' => $pageScripts ?? '',
            'layoutOptions' => $layoutOptions ?? [],
        ]);
    }

    public function webIndex(): void
    {
        $this->renderModule('쇼핑몰 대시보드', '쇼핑몰 주문, 결제, 정산 흐름을 관리합니다.');
    }

    public function webProducts(): void
    {
        $this->renderModule('상품관리', '쇼핑몰 상품 마스터를 관리하는 영역입니다.');
    }

    public function webCategories(): void
    {
        $this->renderModule('카테고리관리', '사전 정의된 쇼핑몰 카테고리를 관리하는 영역입니다.');
    }

    public function webOrders(): void
    {
        $this->renderModule('주문관리', '주문 완료 후 결제 상태에 따라 거래가 자동 생성되는 구조입니다.');
    }

    public function webPayments(): void
    {
        $this->renderModule('결제관리', '결제수단 및 결제 완료 이력을 관리하는 영역입니다.');
    }

    public function webSettlement(): void
    {
        $this->renderModule('매출/정산', '매출과 정산 현황을 관리하는 영역입니다.');
    }

    private function renderModule(string $title, string $description): void
    {
        $this->renderPage('/app/views/shop/index.php', [
            'pageTitle' => $title,
            'shopTitle' => $title,
            'shopDescription' => $description,
        ]);
    }
}
