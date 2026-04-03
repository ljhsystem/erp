<?php
// 경로: PROJECT_ROOT . '/core/Router.php'
namespace Core;

//use App\Controllers\System\ErrorController;
use Core\Middleware\AuthMiddleware;
use Core\Middleware\PermissionMiddleware;
use Core\Middleware\ApiAccessMiddleware;
use Core\PermissionRegistry;
use Core\LoggerFactory;

class Router
{
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    private $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger('core-Router');
        $this->logger->info("📌 Router initialized");
    }

    /* ============================================================
       1. GET / POST 라우트 등록
       ============================================================ */
    public function get(string $uri, string $controllerAction, $permission = null)
    {
        $norm = $this->normalize($uri);
        $permissionData = $this->registerPermission($permission);

        $this->routes['GET'][$norm] = [
            'action'     => $controllerAction,
            'permission' => $permissionData,
        ];

        $this->logger->info("📌 GET 라우트 등록", [
            'uri'        => $norm,
            'action'     => $controllerAction,
            'permission' => $permissionData
        ]);
    }

    public function post(string $uri, string $controllerAction, $permission = null)
    {
        $norm = $this->normalize($uri);
        $permissionData = $this->registerPermission($permission);

        $this->routes['POST'][$norm] = [
            'action'     => $controllerAction,
            'permission' => $permissionData,
        ];

        $this->logger->info("📌 POST 라우트 등록", [
            'uri'        => $norm,
            'action'     => $controllerAction,
            'permission' => $permissionData
        ]);
    }

    private function registerPermission($permission)
    {
        if (!$permission) return null;

        if (is_string($permission)) {
            PermissionRegistry::register($permission);
            return ['key' => $permission];
        }

        if (is_array($permission)) {
            PermissionRegistry::register(
                $permission['key'],
                $permission['name']        ?? null,
                $permission['description'] ?? null,
                $permission['category']    ?? null
            );
            return $permission;
        }

        return null;
    }

    private function normalize(string $uri): string
    {
        return '/' . trim($uri, '/');
    }

    /* ============================================================
       2. 요청 처리
       ============================================================ */
    public function resolve()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $this->normalize($requestUri);

        // ⭐ 에러 라우트 우선 처리
        if ($path === '/403') {
            return $this->runController('ErrorController@error403');
        }
        if ($path === '/404') {
            return $this->runController('ErrorController@error404');
        }
        if ($path === '/500') {
            return $this->runController('ErrorController@error500');
        }


        $this->logger->info("🚦 Router resolve()", [
            'method' => $method,
            'path'   => $path
        ]);

        // ⭐ 루트 처리
        if ($path === '/' || $path === '') {
            return $this->runController('HomeController@webRoot');
        }

        // ⭐ 정식 라우트 매칭
        if (isset($this->routes[$method][$path])) {

            AuthMiddleware::handle($path);
        
            $route = $this->routes[$method][$path];
        
            // 🔐 Permission 체크
            if (!empty($route['permission'])) {
                if (empty($route['permission']['skip_permission'])) {
                    PermissionMiddleware::check($route['permission']);
                }
            }
        
            // 🔥 외부 API 미들웨어 실행 (핵심)
            if (!empty($route['permission']['middleware'])) {
                foreach ($route['permission']['middleware'] as $mw) {
                    if ($mw === 'ApiAccessMiddleware') {
                        ApiAccessMiddleware::handle();
                    }
                }
            }
        
            return $this->runController($route['action']);
        }
        

        // ⭐ fallback: /login GET
        if ($method === 'GET' && $path === '/login') {
            return $this->runController('LoginController@webLoginPage');
        }

        // ⭐ 자동 view 매핑
        $autoView = PROJECT_ROOT . "/app/views/home{$path}.php";
        if (is_file($autoView)) {
            include $autoView;
            exit;
        }

        /* ============================================================
           ⭐⭐ 404 처리 — 단일 에러 페이지 호출로 변경됨 ⭐⭐
           ============================================================ */
        $this->logger->warning("❌ 라우트 없음 → 404", [
            'method' => $method,
            'path'   => $path
        ]);

        http_response_code(404);

        $c = new \App\Controllers\System\ErrorController();                  
        return $c->error404();
    }

    /* ============================================================
       3. 컨트롤러 실행
       ============================================================ */
       private function runController(string $controllerAction)
       {
           list($shortName, $method) = explode('@', $controllerAction);
       
           $controllerFile = $this->findControllerFileByShortName($shortName);
       
           if (!$controllerFile) {
               http_response_code(404);
               return (new \App\Controllers\System\ErrorController())->error404();
           }
       
           // 🔥 require 제거
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

    /* ============================================================
       4. 컨트롤러 파일 탐색
       ============================================================ */
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

    /* ============================================================
       5. 네임스페이스 해석
       ============================================================ */
    private function resolveNamespace(string $filePath, string $shortName): string
    {
        $relative = str_replace(PROJECT_ROOT . '/app/Controllers/', '', $filePath);
        $dir = str_replace('/', '\\', dirname($relative));

        return ($dir === '.' || $dir === '')
            ? "App\\Controllers\\{$shortName}"
            : "App\\Controllers\\{$dir}\\{$shortName}";
    }
}
