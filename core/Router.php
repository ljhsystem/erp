<?php

namespace Core;

use Core\LoggerFactory;
use Core\Middleware\ApiAccessMiddleware;
use Core\Middleware\AuthMiddleware;
use Core\Middleware\PermissionMiddleware;

class Router
{
    private static ?array $currentRoute = null;

    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    private $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger('core-Router');
        $this->logger->info('Router initialized');
    }

    public function get(string $uri, string $controllerAction, $permission = null)
    {
        $norm = $this->normalize($uri);
        $permissionData = $this->registerPermission($permission) ?? [];

        $this->routes['GET'][$norm] = [
            'action' => $controllerAction,
            'permission' => $permissionData,
        ];
    }

    public function post(string $uri, string $controllerAction, $permission = null)
    {
        $norm = $this->normalize($uri);
        $permissionData = $this->registerPermission($permission) ?? [];

        $this->routes['POST'][$norm] = [
            'action' => $controllerAction,
            'permission' => $permissionData,
        ];
    }

    private function registerPermission($permission): ?array
    {
        if (!$permission) {
            return null;
        }

        if (is_string($permission)) {
            PermissionRegistry::register($permission);
            return ['key' => $permission];
        }

        if (is_array($permission)) {
            $permission['permissions'] = is_array($permission['permissions'] ?? null)
                ? $permission['permissions']
                : [];
            $permission['skip_permission'] = (bool) ($permission['skip_permission'] ?? false);

            if (
                !$permission['skip_permission'] &&
                !empty($permission['key']) &&
                $permission['permissions'] !== []
            ) {
                PermissionRegistry::register(
                    ($permission['permission_key'] ?? null) ?: $permission['key'],
                    $permission['name'] ?? null,
                    $permission['description'] ?? null,
                    $permission['category'] ?? null,
                    $permission['page'] ?? null,
                    $permission['page_description'] ?? null,
                    $permission['permission_name'] ?? null,
                    $permission['permission_description'] ?? null,
                    $permission['page_key'] ?? null
                );
            }

            return $permission;
        }

        return null;
    }

    private function shouldCheckPermission(array $meta): bool
    {
        if (!empty($meta['skip_permission']) || empty($meta['key'])) {
            return false;
        }

        $permissions = $meta['permissions'] ?? [];

        return is_array($permissions) && $permissions !== [];
    }

    private function normalize(string $uri): string
    {
        return '/' . trim($uri, '/');
    }

    public function resolve()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $this->normalize($requestUri);

        if ($path === '/403') {
            return $this->runController('ErrorController@error403');
        }
        if ($path === '/404') {
            return $this->runController('ErrorController@error404');
        }
        if ($path === '/500') {
            return $this->runController('ErrorController@error500');
        }

        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];
            self::$currentRoute = [
                'method' => $method,
                'path' => $path,
                'action' => $route['action'] ?? null,
                'meta' => $route['permission'] ?? [],
            ];

            AuthMiddleware::handle($path, $route);

            if ($this->shouldCheckPermission($route['permission'] ?? [])) {
                PermissionMiddleware::check($route['permission']);
            }

            foreach ($route['permission']['middleware'] ?? [] as $middleware) {
                if ($middleware === 'ApiAccessMiddleware') {
                    ApiAccessMiddleware::handle();
                }
            }

            return $this->runController($route['action']);
        }

        $autoView = PROJECT_ROOT . "/app/views/home{$path}.php";
        if (is_file($autoView)) {
            self::$currentRoute = [
                'method' => $method,
                'path' => $path,
                'action' => null,
                'meta' => [],
            ];
            include $autoView;
            exit;
        }

        http_response_code(404);
        return (new \App\Controllers\System\ErrorController())->error404();
    }

    public static function currentRoute(): ?array
    {
        return self::$currentRoute;
    }

    public static function currentRouteMeta(): array
    {
        return self::$currentRoute['meta'] ?? [];
    }

    public static function currentBreadcrumbMeta(): array
    {
        $meta = self::currentRouteMeta();
        $category = trim((string) ($meta['category'] ?? ''));
        $page = trim((string) ($meta['page'] ?? ''));
        $name = trim((string) ($meta['name'] ?? ''));
        $permissionName = trim((string) ($meta['permission_name'] ?? ''));
        $description = trim((string) ($meta['description'] ?? ''));

        if ($description === '' && $category !== '' && $page !== '') {
            $description = $category . ' > ' . $page;
        }

        $path = trim((string) (self::$currentRoute['path'] ?? ''));
        $fallback = self::fallbackBreadcrumbMeta($path);

        if (self::containsGarbledText($description) && $fallback !== []) {
            $description = (string) ($fallback['description'] ?? $description);
        }
        if (self::containsGarbledText($category) && $fallback !== []) {
            $category = (string) ($fallback['category'] ?? $category);
        }
        if (self::containsGarbledText($page) && $fallback !== []) {
            $page = (string) ($fallback['page'] ?? $page);
        }
        if (self::containsGarbledText($name) && $fallback !== []) {
            $name = (string) ($fallback['name'] ?? $name);
        }

        return [
            'category' => $category,
            'description' => $description,
            'group' => trim((string) ($meta['group'] ?? '')),
            'name' => $page !== '' ? $page : ($name !== '' ? $name : $permissionName),
        ];
    }

    public static function currentPageDescription(): string
    {
        return trim((string) (self::currentBreadcrumbMeta()['description'] ?? ''));
    }

    private static function containsGarbledText(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return str_contains($value, '?')
            || preg_match('/[\x{F900}-\x{FAFF}]/u', $value) === 1
            || preg_match('/\x{FFFD}/u', $value) === 1;
    }

    private static function fallbackBreadcrumbMeta(string $path): array
    {
        $map = [
            '/ledger' => ['category' => '회계관리', 'page' => '회계', 'name' => '회계대시보드', 'description' => '회계관리 > 회계 > 회계대시보드'],
            '/ledger/settings/accounts' => ['category' => '회계관리 > 기초정보관리', 'page' => '계정과목', 'name' => '계정과목', 'description' => '회계관리 > 기초정보관리 > 계정과목'],
            '/ledger/settings/opening-balances' => ['category' => '회계관리 > 기초정보관리', 'page' => '기초기금액', 'name' => '기초기금액', 'description' => '회계관리 > 기초정보관리 > 기초기금액'],
            '/ledger/settings/journal-rules' => ['category' => '회계관리 > 기초정보관리', 'page' => '분개규칙', 'name' => '분개규칙', 'description' => '회계관리 > 기초정보관리 > 분개규칙'],
            '/ledger/data/evidence-metadata' => ['category' => '회계관리 > 자료관리', 'page' => '증빙정책', 'name' => '증빙정책', 'description' => '회계관리 > 자료관리 > 증빙정책'],
            '/ledger/data/formats' => ['category' => '회계관리 > 자료관리', 'page' => '양식관리', 'name' => '양식관리', 'description' => '회계관리 > 자료관리 > 양식관리'],
            '/ledger/data/format' => ['category' => '회계관리 > 자료관리', 'page' => '양식관리', 'name' => '양식관리', 'description' => '회계관리 > 자료관리 > 양식관리'],
            '/ledger/data/list' => ['category' => '회계관리 > 자료관리', 'page' => '증빙원본', 'name' => '증빙원본', 'description' => '회계관리 > 자료관리 > 증빙원본'],
            '/ledger/data/bank-transactions' => ['category' => '회계관리 > 자료관리', 'page' => '증빙원본', 'name' => '증빙원본', 'description' => '회계관리 > 자료관리 > 증빙원본'],
            '/ledger/data/tax-invoices' => ['category' => '회계관리 > 자료관리', 'page' => '증빙원본', 'name' => '증빙원본', 'description' => '회계관리 > 자료관리 > 증빙원본'],
            '/ledger/data/raw' => ['category' => '회계관리 > 자료관리', 'page' => '원본자료', 'name' => '원본자료', 'description' => '회계관리 > 자료관리 > 원본자료'],
            '/ledger/data/upload' => ['category' => '회계관리 > 자료관리', 'page' => '자료업로드', 'name' => '자료업로드', 'description' => '회계관리 > 자료관리 > 자료업로드'],
            '/ledger/data' => ['category' => '회계관리 > 자료관리', 'page' => '증빙원본', 'name' => '증빙원본', 'description' => '회계관리 > 자료관리 > 증빙원본'],
            '/ledger/transactions/input' => ['category' => '회계관리 > 전표관리', 'page' => '거래입력', 'name' => '거래입력', 'description' => '회계관리 > 전표관리 > 거래입력'],
            '/ledger/transactions' => ['category' => '회계관리 > 전표관리', 'page' => '거래입력', 'name' => '거래입력', 'description' => '회계관리 > 전표관리 > 거래입력'],
            '/ledger/transactions/create' => ['category' => '회계관리 > 전표관리', 'page' => '거래입력', 'name' => '거래입력', 'description' => '회계관리 > 전표관리 > 거래입력'],
            '/ledger/transaction' => ['category' => '회계관리 > 전표관리', 'page' => '거래입력', 'name' => '거래입력', 'description' => '회계관리 > 전표관리 > 거래입력'],
            '/ledger/transaction/create' => ['category' => '회계관리 > 전표관리', 'page' => '거래입력', 'name' => '거래입력', 'description' => '회계관리 > 전표관리 > 거래입력'],
            '/ledger/vouchers/input' => ['category' => '회계관리 > 전표관리', 'page' => '전표입력', 'name' => '전표입력', 'description' => '회계관리 > 전표관리 > 전표입력'],
            '/ledger/vouchers/review' => ['category' => '회계관리 > 전표관리', 'page' => '전표검토·전기', 'name' => '전표검토·전기', 'description' => '회계관리 > 전표관리 > 전표검토·전기'],
            '/ledger/accounts' => ['category' => '회계관리 > 기초정보관리', 'page' => '계정과목', 'name' => '계정과목', 'description' => '회계관리 > 기초정보관리 > 계정과목'],
            '/ledger/journal' => ['category' => '회계관리 > 장부관리', 'page' => '분개장', 'name' => '분개장', 'description' => '회계관리 > 장부관리 > 분개장'],
            '/ledger/funds/account-transactions' => ['category' => '회계관리 > 자금관리', 'page' => '계좌별거래내역', 'name' => '계좌별거래내역', 'description' => '회계관리 > 자금관리 > 계좌별거래내역'],
            '/ledger/opening-balances' => ['category' => '회계관리 > 기초정보관리', 'page' => '기초기금액', 'name' => '기초기금액', 'description' => '회계관리 > 기초정보관리 > 기초기금액'],
            '/ledger/book/journal' => ['category' => '회계관리 > 장부관리', 'page' => '분개장', 'name' => '분개장', 'description' => '회계관리 > 장부관리 > 분개장'],
            '/ledger/book/account' => ['category' => '회계관리 > 장부관리', 'page' => '계정별원장', 'name' => '계정별원장', 'description' => '회계관리 > 장부관리 > 계정별원장'],
            '/ledger/book/general' => ['category' => '회계관리 > 장부관리', 'page' => '총계정원장', 'name' => '총계정원장', 'description' => '회계관리 > 장부관리 > 총계정원장'],
            '/ledger/book/partner' => ['category' => '회계관리 > 장부관리', 'page' => '거래처원장', 'name' => '거래처원장', 'description' => '회계관리 > 장부관리 > 거래처원장'],
            '/ledger/book/project' => ['category' => '회계관리 > 장부관리', 'page' => '프로젝트원장', 'name' => '프로젝트원장', 'description' => '회계관리 > 장부관리 > 프로젝트원장'],
            '/ledger/book/daily' => ['category' => '회계관리 > 장부관리', 'page' => '일계표', 'name' => '일계표', 'description' => '회계관리 > 장부관리 > 일계표'],
            '/ledger/book/purchase-sales' => ['category' => '회계관리 > 장부관리', 'page' => '매입매출장', 'name' => '매입매출장', 'description' => '회계관리 > 장부관리 > 매입매출장'],
            '/ledger/book/vehicle-log' => ['category' => '회계관리 > 장부관리', 'page' => '차량운행기록부', 'name' => '차량운행기록부', 'description' => '회계관리 > 장부관리 > 차량운행기록부'],
            '/ledger/funds/daily-report' => ['category' => '회계관리 > 자금관리', 'page' => '자금일보', 'name' => '자금일보', 'description' => '회계관리 > 자금관리 > 자금일보'],
            '/ledger/funds/account-balances' => ['category' => '회계관리 > 자금관리', 'page' => '계좌잔액현황', 'name' => '계좌잔액현황', 'description' => '회계관리 > 자금관리 > 계좌잔액현황'],
            '/ledger/funds/payment-schedule' => ['category' => '회계관리 > 자금관리', 'page' => '지급예정현황', 'name' => '지급예정현황', 'description' => '회계관리 > 자금관리 > 지급예정현황'],
            '/ledger/financial/trial-balance' => ['category' => '회계관리 > 재무제표', 'page' => '시산표', 'name' => '시산표', 'description' => '회계관리 > 재무제표 > 시산표'],
            '/ledger/financial/income-statement' => ['category' => '회계관리 > 재무제표', 'page' => '손익계산서', 'name' => '손익계산서', 'description' => '회계관리 > 재무제표 > 손익계산서'],
            '/ledger/financial/statement-position' => ['category' => '회계관리 > 재무제표', 'page' => '재무상태표', 'name' => '재무상태표', 'description' => '회계관리 > 재무제표 > 재무상태표'],
            '/ledger/financial/product-cost' => ['category' => '회계관리 > 재무제표', 'page' => '상품원가명세서', 'name' => '상품원가명세서', 'description' => '회계관리 > 재무제표 > 상품원가명세서'],
            '/ledger/financial/construction-cost' => ['category' => '회계관리 > 재무제표', 'page' => '공사원가명세서', 'name' => '공사원가명세서', 'description' => '회계관리 > 재무제표 > 공사원가명세서'],
            '/ledger/financial/retained-earnings' => ['category' => '회계관리 > 재무제표', 'page' => '이익잉여금처분계산서', 'name' => '이익잉여금처분계산서', 'description' => '회계관리 > 재무제표 > 이익잉여금처분계산서'],
            '/ledger/assets/create' => ['category' => '회계관리 > 고정자산관리', 'page' => '자산등록', 'name' => '자산등록', 'description' => '회계관리 > 고정자산관리 > 자산등록'],
            '/ledger/assets' => ['category' => '회계관리 > 고정자산관리', 'page' => '자산대장', 'name' => '자산대장', 'description' => '회계관리 > 고정자산관리 > 자산대장'],
            '/ledger/assets/depreciation' => ['category' => '회계관리 > 고정자산관리', 'page' => '감가상각', 'name' => '감가상각', 'description' => '회계관리 > 고정자산관리 > 감가상각'],
            '/ledger/assets/transfer' => ['category' => '회계관리 > 고정자산관리', 'page' => '자산이동', 'name' => '자산이동', 'description' => '회계관리 > 고정자산관리 > 자산이동'],
            '/ledger/assets/disposal' => ['category' => '회계관리 > 고정자산관리', 'page' => '자산폐기', 'name' => '자산폐기', 'description' => '회계관리 > 고정자산관리 > 자산폐기'],
            '/ledger/tax/trial-balance' => ['category' => '회계관리 > 세무관리', 'page' => '세무 시산표', 'name' => '세무 시산표', 'description' => '회계관리 > 세무관리 > 세무 시산표'],
            '/ledger/tax/income-statement' => ['category' => '회계관리 > 세무관리', 'page' => '세무 손익계산서', 'name' => '세무 손익계산서', 'description' => '회계관리 > 세무관리 > 세무 손익계산서'],
            '/ledger/tax/statement-position' => ['category' => '회계관리 > 세무관리', 'page' => '세무 재무상태표', 'name' => '세무 재무상태표', 'description' => '회계관리 > 세무관리 > 세무 재무상태표'],
            '/ledger/tax/cost-statement' => ['category' => '회계관리 > 세무관리', 'page' => '세무 원가명세서', 'name' => '세무 원가명세서', 'description' => '회계관리 > 세무관리 > 세무 원가명세서'],
            '/ledger/tax/retained-earnings' => ['category' => '회계관리 > 세무관리', 'page' => '세무 이익잉여금', 'name' => '세무 이익잉여금', 'description' => '회계관리 > 세무관리 > 세무 이익잉여금'],
            '/ledger/tax/comparison' => ['category' => '회계관리 > 세무관리', 'page' => '비교/차이분석', 'name' => '비교/차이분석', 'description' => '회계관리 > 세무관리 > 비교/차이분석'],
        ];

        return $map[$path] ?? [];
    }

    private function runController(string $controllerAction)
    {
        [$shortName, $method] = explode('@', $controllerAction);

        $controllerFile = $this->findControllerFileByShortName($shortName);
        if (!$controllerFile) {
            http_response_code(404);
            return (new \App\Controllers\System\ErrorController())->error404();
        }

        $fqcn = $this->resolveNamespace($controllerFile, $shortName);
        if (!class_exists($fqcn)) {
            http_response_code(500);
            return (new \App\Controllers\System\ErrorController())->error500();
        }

        $pdo = \Core\Database::getInstance()->getConnection();
        $instance = new $fqcn($pdo);

        if (!method_exists($instance, $method)) {
            http_response_code(404);
            return (new \App\Controllers\System\ErrorController())->error404();
        }

        return $instance->$method();
    }

    private function findControllerFileByShortName(string $shortName): ?string
    {
        $base = PROJECT_ROOT . '/app/Controllers';
        $target = $shortName . '.php';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === $target) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function resolveNamespace(string $filePath, string $shortName): string
    {
        $filePath = str_replace('\\', '/', $filePath);
        $basePath = str_replace('\\', '/', PROJECT_ROOT . '/app/Controllers/');
        $relative = str_replace($basePath, '', $filePath);
        $dir = dirname($relative);
        $dir = str_replace('/', '\\', $dir);

        return ($dir === '.' || $dir === '')
            ? "App\\Controllers\\{$shortName}"
            : "App\\Controllers\\{$dir}\\{$shortName}";
    }
}
