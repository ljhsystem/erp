<?php
namespace App\Controllers\Home;

use App\Services\System\CoverService;
use Core\DbPdo;

class AboutController
{
    private CoverService $coverService;

    public function __construct()
    {
        $this->coverService = new CoverService(DbPdo::conn());
    }

    public function webAbout()
    {
        $images = $this->coverService->getPublicList();

        include PROJECT_ROOT . '/app/views/home/about.php';
    }

    public function webAdminAbout()
    {
        $images = $this->coverService->getList();

        include PROJECT_ROOT . '/app/views/admin/about/index.php';
    }
}
