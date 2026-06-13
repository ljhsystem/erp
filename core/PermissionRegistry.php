<?php
// Path: PROJECT_ROOT . '/core/PermissionRegistry.php'

namespace Core;

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

        $supportsPageKey = self::hasAuthPermissionsPageKey($pdo);
        $pageKeyResolver = $supportsPageKey ? new PageKeyResolver($pdo) : null;
        $selectColumns = 'id, permission_key, permission_name, description, category, created_by, updated_by';
        if ($supportsPageKey) {
            $selectColumns .= ', page_key';
        }

        $stmt = $pdo->query("
            SELECT {$selectColumns}
            FROM auth_permissions
        ");
        $existingRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
                if (self::syncExistingPermission($pdo, $existingMap[$key], $perm, $systemActor, $supportsPageKey, $pageKey)) {
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

                if ($supportsPageKey) {
                    $stmt = $pdo->prepare("
                        INSERT INTO auth_permissions
                        (id, sort_no, permission_key, permission_name, description, category, page_key, is_active, created_by, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $uuid,
                        $sortNo,
                        $perm['key'],
                        $perm['permission_name'],
                        $perm['permission_description'],
                        $perm['category'],
                        $pageKey,
                        1,
                        $systemActor,
                        $systemActor,
                    ]);
                    $insertCount++;
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO auth_permissions
                        (id, sort_no, permission_key, permission_name, description, category, is_active, created_by, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $uuid,
                        $sortNo,
                        $perm['key'],
                        $perm['permission_name'],
                        $perm['permission_description'],
                        $perm['category'],
                        1,
                        $systemActor,
                        $systemActor,
                    ]);
                    $insertCount++;
                }

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

        $normalizeSummary = self::normalizeRegisteredSortNo($pdo, $systemActor);
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
        \PDO $pdo,
        array $row,
        array $perm,
        string $systemActor,
        bool $supportsPageKey = false,
        ?string $pageKey = null
    ): bool
    {
        $fields = [];
        $params = [];

        $syncMap = [
            'permission_name' => $perm['permission_name'] ?? $perm['name'] ?? null,
            'description' => $perm['permission_description'] ?? $perm['description'] ?? null,
            'category' => $perm['category'],
        ];

        foreach ($syncMap as $column => $value) {
            if ((string)($row[$column] ?? '') !== (string)($value ?? '')) {
                $fields[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        if ($supportsPageKey && (string)($row['page_key'] ?? '') !== (string)($pageKey ?? '')) {
            $fields[] = 'page_key = ?';
            $params[] = $pageKey;
        }

        if (empty($row['created_by']) || $row['created_by'] === 'SYSTEM') {
            $fields[] = 'created_by = ?';
            $params[] = $systemActor;
        }

        if (empty($row['updated_by']) || $row['updated_by'] === 'SYSTEM' || $fields) {
            $fields[] = 'updated_at = NOW()';
            $fields[] = 'updated_by = ?';
            $params[] = $systemActor;
        }

        if (!$fields) {
            return false;
        }

        $params[] = $row['id'];
        $stmt = $pdo->prepare("
            UPDATE auth_permissions
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);

        return true;
    }

    /**
     * 라우터에 등록된 권한 순서 기준으로 순번을 1부터 정규화한다.
     */
    private static function normalizeRegisteredSortNo(\PDO $pdo, string $systemActor): array
    {
        $startedAt = hrtime(true);
        $rows = $pdo->query("
            SELECT id, permission_key, sort_no
            FROM auth_permissions
            ORDER BY sort_no ASC, permission_key ASC
        ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $tempOffset = max(count($rows), 1) + 1000000;

        $temp = $pdo->prepare("
            UPDATE auth_permissions
            SET sort_no = sort_no + {$tempOffset},
                updated_at = NOW(),
                updated_by = ?
            WHERE id IN ({$placeholders})
        ");
        $temp->execute(array_merge([$systemActor], $ids));

        foreach (array_chunk($changes, 200) as $chunk) {
            $ids = array_column($chunk, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $caseParts = [];
            $params = [];
            foreach ($chunk as $row) {
                $caseParts[] = 'WHEN ? THEN ?';
                $params[] = $row['id'];
                $params[] = $row['sort_no'];
            }

            $final = $pdo->prepare("
                UPDATE auth_permissions
                SET sort_no = CASE id " . implode(' ', $caseParts) . " END,
                    updated_at = NOW(),
                    updated_by = ?
                WHERE id IN ({$placeholders})
            ");
            $final->execute(array_merge($params, [$systemActor], $ids));
        }

        return [
            'duration_ms' => self::elapsedMilliseconds($startedAt),
        ];
    }

    private static function hasAuthPermissionsPageKey(\PDO $pdo): bool
    {
        if (self::$authPermissionsHasPageKey !== null) {
            return self::$authPermissionsHasPageKey;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM auth_permissions LIKE 'page_key'");
            self::$authPermissionsHasPageKey = (bool)($stmt->fetch(\PDO::FETCH_ASSOC) ?: false);
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
