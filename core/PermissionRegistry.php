<?php
// Path: PROJECT_ROOT . '/core/PermissionRegistry.php'

namespace Core;

use Core\LoggerFactory;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\ActorHelper;

class PermissionRegistry
{
    /** @var array<string,array> */
    private static array $permissions = [];

    private static $logger = null;

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
        ?string $category = null
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

        self::$permissions[$key] = [
            'key'         => $key,
            'name'        => $name ?: $key,
            'description' => $description ?: null,
            'category'    => $category ?: null,
        ];

        self::$logger->info('Permission registered', [
            'key'  => $key,
            'name' => $name,
            'desc' => $description,
            'cat'  => $category
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

        self::$logger->info('PermissionRegistry::syncToDatabase() START', [
            'count' => count(self::$permissions)
        ]);

        $systemActor = ActorHelper::system('자동');

        $stmt = $pdo->query("
            SELECT id, permission_key, permission_name, description, category, created_by, updated_by
            FROM auth_permissions
        ");
        $existingRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[(string)$row['permission_key']] = $row;
        }

        foreach (self::$permissions as $perm) {
            $key = $perm['key'];

            if (isset($existingMap[$key])) {
                self::syncExistingPermission($pdo, $existingMap[$key], $perm, $systemActor);
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

                $stmt = $pdo->prepare("
                    INSERT INTO auth_permissions
                    (id, sort_no, permission_key, permission_name, description, category, is_active, created_by, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $uuid,
                    $sortNo,
                    $perm['key'],
                    $perm['name'],
                    $perm['description'],
                    $perm['category'],
                    1,
                    $systemActor,
                    $systemActor,
                ]);

                self::$logger->info('권한 DB INSERT 성공', [
                    'key' => $perm['key']
                ]);
            } catch (\Throwable $e) {
                self::$logger->error('권한 DB INSERT 실패', [
                    'key' => $perm['key'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        self::normalizeRegisteredSortNo($pdo, $systemActor);

        self::$logger->info('PermissionRegistry::syncToDatabase() END');
    }

    /**
     * 이미 존재하는 권한의 표시 정보와 시스템 액터 기록을 보정한다.
     */
    private static function syncExistingPermission(\PDO $pdo, array $row, array $perm, string $systemActor): void
    {
        $fields = [];
        $params = [];

        $syncMap = [
            'permission_name' => $perm['name'],
            'description' => $perm['description'],
            'category' => $perm['category'],
        ];

        foreach ($syncMap as $column => $value) {
            if ((string)($row[$column] ?? '') !== (string)($value ?? '')) {
                $fields[] = "{$column} = ?";
                $params[] = $value;
            }
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
            return;
        }

        $params[] = $row['id'];
        $stmt = $pdo->prepare("
            UPDATE auth_permissions
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmt->execute($params);
    }

    /**
     * 라우터에 등록된 권한 순서 기준으로 순번을 1부터 정규화한다.
     */
    private static function normalizeRegisteredSortNo(\PDO $pdo, string $systemActor): void
    {
        $rows = $pdo->query("
            SELECT id, permission_key, sort_no
            FROM auth_permissions
            ORDER BY sort_no ASC, permission_key ASC
        ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            return;
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
            return;
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
    }
}
