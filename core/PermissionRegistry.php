<?php
// 경로: PROJECT_ROOT . '/core/PermissionRegistry.php'

namespace Core;

use Core\LoggerFactory;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;

class PermissionRegistry
{
    /** @var array<string,array>  */
    private static array $permissions = [];

    private static $logger = null;

    /** ------------------------------------------------------------
     *  Logger 초기화
     * ------------------------------------------------------------ */
    private static function init()
    {
        if (!self::$logger) {
            self::$logger = LoggerFactory::getLogger('core-PermissionRegistry');
        }
    }

    /** ------------------------------------------------------------
     *  권한 등록 (Router::get/post 에서 자동 호출됨)
     * ------------------------------------------------------------ */
    public static function register(
        string $key,
        ?string $name = null,
        ?string $description = null,
        ?string $category = null
    )
    {
        self::init();

        // ❗ key 비어있으면 문제 있음 → 자동 무시 + 로그
        if (empty($key)) {
            self::$logger->error("⚠ PermissionRegistry::register() → 빈 key 전달됨! 등록 스킵");
            return;
        }

        // 이미 등록된 키는 덮어쓰지 않고 스킵
        if (isset(self::$permissions[$key])) {
            self::$logger->info("이미 등록된 권한 → Skip", [
                'key' => $key
            ]);
            return;
        }

        // 권한 저장
        self::$permissions[$key] = [
            'key'         => $key,
            'name'        => $name        ?: $key,
            'description' => $description ?: null,
            'category'    => $category    ?: null,
        ];

        self::$logger->info("Permission 등록됨", [
            'key'   => $key,
            'name'  => $name,
            'desc'  => $description,
            'cat'   => $category
        ]);

        // 키 정렬 (일관성 유지)
        ksort(self::$permissions);
    }

    /** ------------------------------------------------------------
     *  등록된 모든 권한 반환
     * ------------------------------------------------------------ */
    public static function all(): array
    {
        return self::$permissions;
    }

    /** ------------------------------------------------------------
     *  DB 동기화
     * ------------------------------------------------------------ */
    public static function syncToDatabase(\PDO $pdo)
    {
        self::init();

        self::$logger->info("PermissionRegistry::syncToDatabase() START", [
            'count' => count(self::$permissions)
        ]);

        // 기존 DB 권한 조회
        $stmt = $pdo->query("SELECT permission_key FROM auth_permissions");
        $existingKeys = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $existingMap = array_flip($existingKeys);

        foreach (self::$permissions as $perm) {

            $key = $perm['key'];

            // 이미 DB에 존재
            if (isset($existingMap[$key])) {
                self::$logger->info("DB 존재 → Skip", ['key' => $key]);
                continue;
            }

            // 신규 등록
            try {
                $uuid = UuidHelper::generate();
                $code = CodeHelper::next('auth_permissions');

                self::$logger->info("DB INSERT 시도", [
                    'uuid' => $uuid,
                    'code' => $code,
                    'key'  => $key
                ]);

                $stmt = $pdo->prepare("
                    INSERT INTO auth_permissions
                    (id, code, permission_key, permission_name, description, category)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $uuid,
                    $code,
                    $perm['key'],
                    $perm['name'],
                    $perm['description'],
                    $perm['category'],
                ]);

                self::$logger->info("DB INSERT 성공", [
                    'key'  => $perm['key'],
                    'code' => $code
                ]);

            } catch (\Throwable $e) {

                self::$logger->error("DB INSERT 실패", [
                    'key'   => $perm['key'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        self::$logger->info("PermissionRegistry::syncToDatabase() END");
    }
}
