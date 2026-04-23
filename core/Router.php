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
            $permission['skip'] = (bool) ($permission['skip'] ?? false);

            if (!$permission['skip'] && !empty($permission['key'])) {
                PermissionRegistry::register(
                    $permission['key'],
                    $permission['name'] ?? null,
                    $permission['description'] ?? null,
                    $permission['category'] ?? null
                );
            }

            return $permission;
        }

        return null;
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

            if (
                !empty($route['permission']['key']) &&
                empty($route['permission']['skip'])
            ) {
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

        return [
            'category' => trim((string) ($meta['category'] ?? '')),
            'group' => trim((string) ($meta['group'] ?? '')),
            'name' => trim((string) ($meta['name'] ?? '')),
        ];
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
