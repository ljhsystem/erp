<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/DashboardController.php'
// 대시보드>메인 WEB 컨트롤러
namespace App\Controllers\Dashboard;

use Core\Session;
use Core\DbPdo;
use App\Controllers\System\LayoutController;
// use App\Services\Calendar\QueryService;
// use App\Services\Calendar\SyncService;

class DashboardController
{
    private LayoutController $layout;
    // private QueryService $queryService;
    // private SyncService $calendarSync;

    public function __construct()
    {
        Session::requireAuth();
        $this->layout = new LayoutController(DbPdo::conn());
        // $this->queryService = new QueryService(DbPdo::conn());
        // $this->calendarSync = new SyncService(DbPdo::conn());
    }

    /* ============================================================
     * 공통: 로그인 이후 페이지 렌더
     * - 인증 강제
     * - view는 본문만 렌더링
     * - controller: DashboardController@renderPage
     * ============================================================ */
    private function renderPage(string $viewPath, array $params = []): void
    {
        Session::requireAuth();

        // 1️⃣ 기본 파라미터만 extract
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }
    
        // 2️⃣ 기본값만 먼저 정의 (뷰가 덮어쓸 수 있도록)
        $pageTitle   = $pageTitle   ?? ($params['pageTitle']   ?? '대시보드');
        $pageStyles  = $pageStyles  ?? ($params['pageStyles']  ?? '');
        $pageScripts = $pageScripts ?? ($params['pageScripts'] ?? '');
        $layoutOptions = $layoutOptions ?? ($params['layoutOptions'] ?? []);
    
        // 3️⃣ 뷰 렌더 (여기서 뷰가 변수 덮어씀)
        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();
    
        // 4️⃣ 디버그 (필요 시)
        error_log("Page Title: " . ($pageTitle ?? ''));
        error_log("Page Styles: " . $pageStyles);
        error_log("Page Scripts: " . $pageScripts);
    
        // 5️⃣ 레이아웃 렌더
        $this->layout->render([
            'pageTitle'     => $pageTitle,
            'content'       => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles'    => $pageStyles,
            'pageScripts'   => $pageScripts,
        ]);
    }
    

    // ============================================================
    // WEB: 대시보드 메인 화면
    // URL: GET /dashboard
    // permission: 미설정(공개)
    // controller: DashboardController@webDashboard
    // ============================================================
    public function webDashboard(): void
    {
        $this->renderPage('/app/views/dashboard/index.php', [
            'pageTitle' => '대시보드'
        ]);
    }

    // ============================================================
    // WEB: 보고서 화면
    // URL: GET /dashboard/report
    // permission: web.dashboard.report
    // controller: DashboardController@webReport
    // ============================================================
    public function webReport(): void
    {
        $this->renderPage('/app/views/dashboard/report.php', [
            'pageTitle' => '보고서'
        ]);
    }


    // ============================================================
    // WEB: 활동 로그 화면
    // URL: GET /dashboard/activity
    // permission: web.dashboard.activity
    // controller: DashboardController@webActivity
    // ============================================================
    public function webActivity(): void
    {
        $this->renderPage('/app/views/dashboard/activity.php', [
            'pageTitle' => '활동 로그'
        ]);
    }



       

    // ============================================================
    // WEB: 알림 화면
    // URL: GET /dashboard/notifications
    // permission: web.dashboard.notifications
    // controller: DashboardController@webNotifications
    // ============================================================
    public function webNotifications(): void
    {
        $this->renderPage('/app/views/dashboard/notifications.php', [
            'pageTitle' => '알림'
        ]);
    }

    // ============================================================
    // WEB: KPI 화면
    // URL: GET /dashboard/kpi
    // permission: web.dashboard.kpi
    // controller: DashboardController@webKpi
    // ============================================================
    public function webKpi(): void
    {
        $this->renderPage('/app/views/dashboard/kpi.php', [
            'pageTitle' => 'KPI'
        ]);
    }



    // ============================================================
    // WEB: 캘린더 화면
    // URL        : GET /dashboard/calendar
    // Permission : web.dashboard.calendar
    // 역할       :
    //   - 로그인 사용자만 접근
    //   - 페이지 진입 시 캘린더 데이터 "조건부 동기화"
    //   - 캘린더 메인 화면 렌더
    // ============================================================
    public function webCalendar(): void
    {
        $this->renderPage('/app/views/dashboard/calendar.php', [
            'pageTitle' => '캘린더'
        ]);
    }


///////// 서브메뉴 ////////////////////////////////////////////////////////////////////////////////////////////////////////////

    
    // ============================================================
    // WEB: 환경설정 메인 화면
    // URL: GET /dashboard/settings
    // permission: web.dashboard.settings
    // controller: DashboardController@webSettings
    // ============================================================

    public function webSettings(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '환경설정'
        ]);
    }


///////// 서브메뉴 ////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function settingsBaseInfoCompany(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '회사정보',
            'cat' => 'base-info',
            'sub' => 'company'
        ]);
    }

    public function settingsBaseInfoBrand(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '브랜드',
            'cat' => 'base-info',
            'sub' => 'brand-logo'
        ]);
    }

    public function settingsBaseInfoCover(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '커버이미지',
            'cat' => 'base-info',
            'sub' => 'cover'
        ]);
    }

    public function settingsBaseInfoClients(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '거래처',
            'cat' => 'base-info',
            'sub' => 'clients'
        ]);
    }

    public function settingsBaseInfoProjects(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '프로젝트',
            'cat' => 'base-info',
            'sub' => 'projects'
        ]);
    }


    public function settingsOrgEmployees(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '직원',
            'cat' => 'organization',
            'sub' => 'employees'
        ]);
    }

    public function settingsOrgDepartments(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '부서',
            'cat' => 'organization',
            'sub' => 'departments'
        ]);
    }

    public function settingsOrgPositions(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '직책',
            'cat' => 'organization',
            'sub' => 'positions'
        ]);
    }

    public function settingsOrgRoles(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '역할',
            'cat' => 'organization',
            'sub' => 'roles'
        ]);
    }

    public function settingsOrgPermissions(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '권한',
            'cat' => 'organization',
            'sub' => 'permissions'
        ]);
    }

    public function settingsOrgRolePermissions(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '권한부여',
            'cat' => 'organization',
            'sub' => 'role_permissions'
        ]);
    }

    public function settingsOrgApproval(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '결재템플릿',
            'cat' => 'organization',
            'sub' => 'approval'
        ]);
    }


    public function settingsSystemSite(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '사이트정보',
            'cat' => 'system',
            'sub' => 'site'
        ]);
    }

    public function settingsSystemSession(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '세션관리',
            'cat' => 'system',
            'sub' => 'session'
        ]);
    }

    public function settingsSystemSecurity(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '보안정책',
            'cat' => 'system',
            'sub' => 'security'
        ]);
    }

    public function settingsSystemApi(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => 'API',
            'cat' => 'system',
            'sub' => 'api'
        ]);
    }

    public function settingsSystemExternal(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '외부서비스',
            'cat' => 'system',
            'sub' => 'external_services'
        ]);
    }

    public function settingsSystemStorage(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '파일저장소',
            'cat' => 'system',
            'sub' => 'storage'
        ]);
    }

    public function settingsSystemBackup(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '데이터백업',
            'cat' => 'system',
            'sub' => 'databasebackup'
        ]);
    }

    public function settingsSystemLogs(): void
    {
        $this->renderPage('/app/views/dashboard/settings.php', [
            'pageTitle' => '로그관리',
            'cat' => 'system',
            'sub' => 'logs'
        ]);
    }





    
}
