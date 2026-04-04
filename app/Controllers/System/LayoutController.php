<?php
// 경로: PROJECT_ROOT/app/Controllers/System/LayoutController.php

namespace App\Controllers\System;

use Core\DbPdo;
use App\Services\System\LayoutService;
use App\Services\System\BrandService;


class LayoutController
{
    private \PDO $pdo;
    private LayoutService $layoutService;
    private BrandService $brandService;   
    
    public function __construct()
    {
        $this->layoutService = new LayoutService(DbPdo::conn());
        $this->brandService = new BrandService(DbPdo::conn());
    }

    /**
     * 공통 레이아웃 렌더
     */
    public function render(array $params = []): void
    {
        // ⭐ 핵심: 전달된 파라미터를 뷰 변수로 풀어줌
        extract($params, EXTR_SKIP);
        
        // 1. 레이아웃 데이터 로드
        $layoutData = $this->layoutService->getLayoutData();
    
        // 레이아웃 데이터에서 UI, 세션, 사용자, 브랜드 정보를 각각 추출
        $ui      = $layoutData['ui'] ?? [];
        $session = $layoutData['session'] ?? [];
        $user    = $layoutData['user'] ?? [];
        $brand   = $layoutData['brand'] ?? [];
    
        // 세션 정보에서 필요한 값들 추출
        $sessionTimeout = (int)($session['timeout'] ?? 30);  // 분
        $sessionAlert   = (int)($session['alert'] ?? 5);     // 분
        $sessionSound   = !empty($session['sound']) ? $session['sound'] : 'default.mp3'; // 기본값 처리
        $expireTime     = (int)($session['expire_time'] ?? (time() + ($sessionTimeout * 60)));


    
        // 2. 브랜드 관련 데이터
        $brandAssets = $this->brandService->getActiveAssets();
        $mainLogoUrl = $brandAssets['main_logo_url'] ?? null;
        $faviconUrl  = $brandAssets['favicon_url'] ?? null;
    
        // 3. 페이지 데이터 준비
        $pageTitle   = $params['pageTitle'] ?? 'SUKHYANG ERP';
        $content     = $params['content'] ?? '';
        $pageScripts = $params['pageScripts'] ?? '';
        $pageStyles  = $params['pageStyles'] ?? '';
    
        // 4. layout.php로 데이터 전달
        // 데이터 배열로 모든 변수를 전달
        $viewData = [
            'sessionAlert'   => $sessionAlert,
            'sessionTimeout' => $sessionTimeout,
            'sessionSound'   => $sessionSound,
            'expireTime'     => $expireTime,
            'ui'             => $ui,
            'user'           => $user,
            'brand'          => $brand,
            'pageTitle'      => $pageTitle,
            'content'        => $content,
            'pageScripts'    => $pageScripts,
            'pageStyles'     => $pageStyles,
            'mainLogoUrl'    => $mainLogoUrl,
            'faviconUrl'     => $faviconUrl,
        ];
    
        // 5. layout.php로 데이터 전달
        // viewData를 include로 넘겨줌
        extract($viewData);  // 배열을 개별 변수로 변환
        require PROJECT_ROOT . '/app/views/layout/layout.php';  // layout.php 호출
    }
    
}