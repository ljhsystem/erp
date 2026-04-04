<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Home/AboutController.php'
namespace App\Controllers\Home;

use Core\DbPdo;
use App\Services\System\CoverImageService;

class AboutController
{
    private CoverImageService $coverimageService;

    public function __construct()
    {
        $this->coverimageService = new CoverImageService(DbPdo::conn());
    }

    /* ============================================================
     * WEB: 회사 소개 페이지
     * URL: GET /about
     * permission: public
     * ============================================================ */
    public function webAbout()
    {
        // ✅ Service에서 이미 URL 정규화 완료        
        $images = $this->coverimageService->getList();

        include PROJECT_ROOT . '/app/views/home/about.php';
    }

    /* ============================================================
     * WEB(Admin): 회사 소개 이미지 전체 목록
     * URL: GET /admin/about
     * permission: admin.about.view
     * ============================================================ */
    public function webAdminAbout()
    {
        // 관리자용 전체 목록 (비활성 포함 가능)
        $images = $this->coverimageService->getList();

        include PROJECT_ROOT . '/app/views/admin/about/index.php';
    }
}
