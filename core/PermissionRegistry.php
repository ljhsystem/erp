<?php
// Path: PROJECT_ROOT . '/core/PermissionRegistry.php'

namespace Core;

use App\Services\Auth\PermissionService;
use Core\LoggerFactory;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\ActorHelper;
use Core\PageKeyResolver;

class PermissionRegistry
{
    /** @var array<string,array> */
    private static array $permissions = [];

    private static $logger = null;
    private static ?bool $authPermissionsHasPageKey = null;

    /**
     * Logger 초기화
     */
    private static function init(): void
    {
        if (!self::$logger) {
            self::$logger = LoggerFactory::getLogger('core-PermissionRegistry');
        }
    }

    /**
     * Router::get/post 에서 전달되는 권한 정보를 메모리에 등록한다.
     */
    public static function register(
        string $key,
        ?string $name = null,
        ?string $description = null,
        ?string $category = null,
        ?string $page = null,
        ?string $pageDescription = null,
        ?string $permissionName = null,
        ?string $permissionDescription = null
    ): void {
        self::init();

        if (empty($key)) {
            self::$logger->error('PermissionRegistry::register() 빈 key 전달됨. 등록을 건너뜁니다.');
            return;
        }

        if (isset(self::$permissions[$key])) {
            self::$logger->info('이미 등록된 권한입니다. 건너뜁니다.', [
                'key' => $key
            ]);
            return;
        }

        $normalizedCategory = self::normalizeMetaText($category);
        $normalizedPage = self::normalizeMetaText($page);
        $normalizedPermissionName = self::normalizeMetaText($permissionName)
            ?: self::normalizeMetaText($name)
            ?: $key;
        $normalizedPermissionDescription = self::normalizeMetaText($permissionDescription)
            ?: self::normalizeMetaText($description);
        $normalizedRouteName = self::normalizeMetaText($name) ?: $normalizedPermissionName;
        $normalizedRouteDescription = self::buildLegacyDescription(
            $normalizedCategory,
            $normalizedPage,
            $normalizedPermissionName,
            self::normalizeMetaText($description)
        );

        self::$permissions[$key] = [
            'key' => $key,
            'name' => $normalizedPermissionName,
            'description' => $normalizedPermissionDescription,
            'category' => $normalizedCategory,
            'page' => $normalizedPage,
            'page_description' => self::normalizeMetaText($pageDescription),
            'permission_name' => $normalizedPermissionName,
            'permission_description' => $normalizedPermissionDescription,
            'route_name' => $normalizedRouteName,
            'route_description' => $normalizedRouteDescription,
        ];

        self::$logger->info('Permission registered', [
            'key' => $key,
            'name' => $normalizedPermissionName,
            'desc' => $normalizedPermissionDescription,
            'cat' => $normalizedCategory,
            'page' => $normalizedPage,
        ]);

        ksort(self::$permissions);
    }

    /**
     * 등록된 모든 권한을 반환한다.
     */
    public static function all(): array
    {
        return self::$permissions;
    }

    /**
     * 메모리에 등록된 라우터 권한을 DB와 동기화한다.
     */
    public static function syncToDatabase(\PDO $pdo): void
    {
        self::init();
        $requestUri = self::requestUri();
        $syncStartedAt = hrtime(true);
        $insertCount = 0;
        $updateCount = 0;

        self::$logger->info('PermissionRegistry::syncToDatabase() START', [
            'request_uri' => $requestUri,
            'count' => count(self::$permissions)
        ]);

        $systemActor = ActorHelper::system('자동');
        $permissionService = new PermissionService($pdo);

        $supportsPageKey = self::hasAuthPermissionsPageKey($permissionService);
        $pageKeyResolver = $supportsPageKey ? new PageKeyResolver($pdo) : null;
        $existingRows = $permissionService->getRegistrySyncRows($supportsPageKey);
        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(string)$row['permission_key']] = $row;
        }

        foreach (self::$permissions as $perm) {
            $key = $perm['key'];
            $pageKey = $supportsPageKey && $pageKeyResolver
                ? $pageKeyResolver->resolve($perm['key'], $perm['route_description'] ?? null, $perm['category'] ?? null)
                : null;

            if (isset($existingMap[$key])) {
                if (self::syncExistingPermission($permissionService, $existingMap[$key], $perm, $systemActor, $supportsPageKey, $pageKey)) {
                    $updateCount++;
                }
                continue;
            }

            try {
                $uuid = UuidHelper::generate();
                $sortNo = SequenceHelper::next('auth_permissions', 'sort_no');

                self::$logger->info('권한 DB INSERT 시도', [
                    'uuid' => $uuid,
                    'key' => $key,
                    'sort_no' => $sortNo
                ]);

                $permissionService->insertRegistryPermission([
                    'id' => $uuid, 'sort_no' => $sortNo, 'permission_key' => $perm['key'],
                    'permission_name' => $perm['permission_name'], 'description' => $perm['permission_description'],
                    'category' => $perm['category'], 'page_key' => $pageKey, 'is_active' => 1,
                    'created_by' => $systemActor, 'updated_by' => $systemActor,
                ], $supportsPageKey);
                $permissionService->grantPermissionToRoleKey($uuid, 'super_admin', $systemActor);
                $insertCount++;

                self::$logger->info('권한 DB INSERT 성공', [
                    'key' => $perm['key'],
                    'page_key' => $pageKey
                ]);
            } catch (\Throwable $e) {
                self::$logger->error('권한 DB INSERT 실패', [
                    'key' => $perm['key'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $normalizeSummary = ['duration_ms' => 0];
        if ($insertCount > 0 || $updateCount > 0) {
            $normalizeSummary = self::normalizeRegisteredSortNo($permissionService, $systemActor);
        }
        $syncDurationMs = self::elapsedMilliseconds($syncStartedAt);

        self::$logger->info('PermissionRegistry::syncToDatabase() SUMMARY', [
            'request_uri' => $requestUri,
            'sync_ms' => $syncDurationMs,
            'insert_count' => $insertCount,
            'update_count' => $updateCount,
            'normalize_ms' => $normalizeSummary['duration_ms'],
        ]);

        error_log(sprintf(
            '%s sync=%dms insert=%d update=%d normalize=%dms',
            $requestUri,
            $syncDurationMs,
            $insertCount,
            $updateCount,
            $normalizeSummary['duration_ms']
        ));

        self::$logger->info('PermissionRegistry::syncToDatabase() END');
    }

    /**
     * 이미 존재하는 권한의 표시 정보와 시스템 액터 기록을 보정한다.
     */
    private static function syncExistingPermission(
        PermissionService $permissionService,
        array $row,
        array $perm,
        string $systemActor,
        bool $supportsPageKey = false,
        ?string $pageKey = null
    ): bool
    {
        $changes = [];

        $syncMap = [
            'permission_name' => $perm['permission_name'] ?? $perm['name'] ?? null,
            'description' => $perm['permission_description'] ?? $perm['description'] ?? null,
            'category' => $perm['category'],
        ];

        foreach ($syncMap as $column => $value) {
            if ((string)($row[$column] ?? '') !== (string)($value ?? '')) {
                $changes[$column] = $value;
            }
        }

        if ($supportsPageKey && (string)($row['page_key'] ?? '') !== (string)($pageKey ?? '')) {
            $changes['page_key'] = $pageKey;
        }

        if (empty($row['created_by']) || $row['created_by'] === 'SYSTEM') {
            $changes['created_by'] = $systemActor;
        }

        if (empty($row['updated_by']) || $row['updated_by'] === 'SYSTEM' || $changes) {
            $changes['updated_at'] = true;
            $changes['updated_by'] = $systemActor;
        }

        if (!$changes) {
            return false;
        }

        $permissionService->updateRegistryPermission((string) $row['id'], $changes);

        return true;
    }

    /**
     * 라우터에 등록된 권한 순서 기준으로 순번을 1부터 정규화한다.
     */
    private static function normalizeRegisteredSortNo(PermissionService $permissionService, string $systemActor): array
    {
        $startedAt = hrtime(true);
        $rows = $permissionService->getRegistrySortRows();

        if (!$rows) {
            return [
                'duration_ms' => self::elapsedMilliseconds($startedAt),
            ];
        }

        $registeredOrder = array_flip(array_keys(self::$permissions));

        usort($rows, static function (array $a, array $b) use ($registeredOrder): int {
            $aKey = (string)($a['permission_key'] ?? '');
            $bKey = (string)($b['permission_key'] ?? '');
            $aRegistered = array_key_exists($aKey, $registeredOrder);
            $bRegistered = array_key_exists($bKey, $registeredOrder);

            if ($aRegistered && $bRegistered) {
                return $registeredOrder[$aKey] <=> $registeredOrder[$bKey];
            }

            if ($aRegistered !== $bRegistered) {
                return $aRegistered ? -1 : 1;
            }

            return strcmp($aKey, $bKey);
        });

        $changes = [];
        $sortNo = 1;
        foreach ($rows as $row) {
            $desiredSortNo = $sortNo++;
            if ((int)($row['sort_no'] ?? 0) !== $desiredSortNo) {
                $changes[] = [
                    'id' => $row['id'],
                    'sort_no' => $desiredSortNo,
                ];
            }
        }

        if (!$changes) {
            return [
                'duration_ms' => self::elapsedMilliseconds($startedAt),
            ];
        }

        $ids = array_column($changes, 'id');
        $tempOffset = max(count($rows), 1) + 1000000;
        $permissionService->offsetSortNumbers($ids, $tempOffset, $systemActor);
        $permissionService->applySortNumbers($changes, $systemActor);

        return [
            'duration_ms' => self::elapsedMilliseconds($startedAt),
        ];
    }

    private static function hasAuthPermissionsPageKey(PermissionService $permissionService): bool
    {
        if (self::$authPermissionsHasPageKey !== null) {
            return self::$authPermissionsHasPageKey;
        }

        try {
            self::$authPermissionsHasPageKey = $permissionService->supportsPageKey();
        } catch (\Throwable $e) {
            self::$authPermissionsHasPageKey = false;
        }

        return self::$authPermissionsHasPageKey;
    }

    private static function requestUri(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        if ($uri === '') {
            return 'unknown';
        }

        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : $uri;
    }

    private static function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1000000);
    }

    private static function normalizeMetaText(?string $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private static function buildLegacyDescription(
        ?string $category,
        ?string $page,
        ?string $permissionName,
        ?string $description
    ): ?string {
        $description = self::normalizeMetaText($description);
        if ($description !== null) {
            return $description;
        }

        $parts = [];
        $category = self::normalizeMetaText($category);
        $page = self::normalizeMetaText($page);
        $permissionName = self::normalizeMetaText($permissionName);

        if ($category !== null) {
            $parts[] = $category;
        }
        if ($page !== null) {
            $parts[] = $page;
        }
        if ($permissionName !== null && $permissionName !== $page) {
            $parts[] = $permissionName;
        }

        return $parts !== [] ? implode(' > ', $parts) : null;
    }
}
