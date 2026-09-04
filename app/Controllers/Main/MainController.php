<?php

namespace App\Controllers\Main;

use App\Controllers\System\LayoutController;
use App\Services\System\SettingsNavigationService;
use Core\DbPdo;

class MainController
{
    private LayoutController $layout;

    public function __construct()
    {
        $this->layout = new LayoutController(DbPdo::conn());
    }

    private function renderPage(string $viewPath, array $params = []): void
    {
        if ($viewPath === '/app/views/main/settings.php') {
            $navigation = (new SettingsNavigationService(DbPdo::conn()))->getViewData();
            $params = array_replace($navigation, $params);
        }
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        $pageTitle = $pageTitle ?? ($params['pageTitle'] ?? '대시보드');
        $pageStyles = $pageStyles ?? ($params['pageStyles'] ?? '');
        $pageScripts = $pageScripts ?? ($params['pageScripts'] ?? '');
        $pageAssetProfile = $pageAssetProfile ?? ($params['pageAssetProfile'] ?? 'default');
        $layoutOptions = $layoutOptions ?? ($params['layoutOptions'] ?? []);

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $this->layout->render([
            'pageTitle' => $pageTitle,
            'content' => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles' => $pageStyles,
            'pageScripts' => $pageScripts,
            'pageAssetProfile' => $pageAssetProfile,
        ]);
    }

    public function webDashboard(): void
    {
        $this->renderPage('/app/views/main/index.php', [
            'pageTitle' => '대시보드',
        ]);
    }

    public function webReport(): void
    {
        $this->renderPage('/app/views/main/report.php', [
            'pageTitle' => '보고서',
        ]);
    }

    public function webActivity(): void
    {
        $this->renderPage('/app/views/main/activity.php', [
            'pageTitle' => '활동 로그',
        ]);
    }

    public function webNotifications(): void
    {
        $this->renderPage('/app/views/main/notifications.php', [
            'pageTitle' => '알림',
        ]);
    }

    public function webKpi(): void
    {
        $this->renderPage('/app/views/main/kpi.php', [
            'pageTitle' => 'KPI',
        ]);
    }

    public function webCalendar(): void
    {
        $this->renderPage('/app/views/main/calendar.php', [
            'pageTitle' => '캘린더',
        ]);
    }

    public function webSettings(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '환경설정',
        ]);
    }

    public function settingsBaseInfoCompany(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '회사정보',
            'cat' => 'base-info',
            'sub' => 'company',
        ]);
    }

    public function settingsBaseInfoBrand(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '브랜드',
            'cat' => 'base-info',
            'sub' => 'brand',
        ]);
    }

    public function settingsBaseInfoCover(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '커버이미지',
            'cat' => 'base-info',
            'sub' => 'cover',
        ]);
    }

    public function settingsStandardCode(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '코드관리',
            'cat' => 'standard',
            'sub' => 'code',
        ]);
    }

    public function settingsBaseInfoClient(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '거래처',
            'cat' => 'base-info',
            'sub' => 'client',
        ]);
    }

    public function settingsBaseInfoProject(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '프로젝트',
            'cat' => 'base-info',
            'sub' => 'project',
        ]);
    }

    public function settingsBaseInfoBankAccount(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '계좌',
            'cat' => 'base-info',
            'sub' => 'bank-account',
        ]);
    }

    public function settingsBaseInfoCard(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '카드',
            'cat' => 'base-info',
            'sub' => 'card',
        ]);
    }

    public function settingsBaseInfoWorkTeams(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '팀',
            'cat' => 'base-info',
            'sub' => 'work-team',
        ]);
    }

    public function settingsOrgEmployees(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '직원',
            'cat' => 'organization',
            'sub' => 'employee',
        ]);
    }

    public function settingsOrgDepartments(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '부서',
            'cat' => 'organization',
            'sub' => 'department',
        ]);
    }

    public function settingsOrgPositions(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '직책',
            'cat' => 'organization',
            'sub' => 'position',
        ]);
    }

    public function settingsOrgRoles(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '역할',
            'cat' => 'organization',
            'sub' => 'role',
        ]);
    }

    public function settingsOrgPermissionAssignment(): void
    {
        $pageRegistryRows = [];
        try {
            $pageRegistryRows = (new PageRegistryQueryService(DbPdo::conn()))->getAll();
        } catch (\Throwable $e) {
            $pageRegistryRows = [];
        }

        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '권한부여',
            'cat' => 'organization',
            'sub' => 'permission-assignment',
            'pageRegistryRows' => $pageRegistryRows,
        ]);
    }

    public function settingsOrgApprovalTemplate(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '결재템플릿',
            'cat' => 'organization',
            'sub' => 'approval-template',
        ]);
    }

    public function settingsSystemSite(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '사이트정보',
            'cat' => 'system',
            'sub' => 'site',
        ]);
    }

    public function settingsSystemSession(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '세션관리',
            'cat' => 'system',
            'sub' => 'session',
        ]);
    }

    public function settingsSystemSecurity(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '보안정책',
            'cat' => 'system',
            'sub' => 'security',
        ]);
    }

    public function settingsSystemApi(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => 'API',
            'cat' => 'system',
            'sub' => 'api',
        ]);
    }

    public function settingsSystemExternal(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '외부서비스',
            'cat' => 'system',
            'sub' => 'external_services',
        ]);
    }

    public function settingsSystemStorage(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '파일저장소',
            'cat' => 'system',
            'sub' => 'storage',
        ]);
    }

    public function settingsSystemBackup(): void
    {
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '데이터백업',
            'cat' => 'system',
            'sub' => 'databasebackup',
        ]);
    }

    public function settingsSystemLogs(): void
    {
        $logSummary = (new SystemLogService(LOGS_DIR))->summary();
        $this->renderPage('/app/views/main/settings.php', [
            'pageTitle' => '로그관리',
            'cat' => 'system',
            'sub' => 'logs',
            'logSummary' => $logSummary,
        ]);
    }
}
