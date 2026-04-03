<?php
// 경로: PROJECT_ROOT . '/core/Helpers/CodeHelper.php'
namespace Core\Helpers;

class CodeHelper
{
    /* ============================================================
     * 1) 공용 코드 생성 함수 (모든 테이블에 공통 적용 가능)
     * ------------------------------------------------------------
     * 기능: 해당 테이블의 code 컬럼에서 MAX 값을 읽고 +1 반환
     * 주의: code 컬럼이 integer 타입일 때 사용 가능
     * ============================================================ */
    private static function generateCode($pdo, string $table): int
    {
        try {
            $stmt = $pdo->query("
                SELECT code
                FROM {$table}
                ORDER BY code ASC
            ");
    
            $codes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
            $next = 1;
    
            foreach ($codes as $code) {
                $code = (int)$code;
    
                if ($code < $next) {
                    continue;
                }
    
                if ($code > $next) {
                    return $next;
                }
    
                $next++;
            }
    
            return $next;
    
        } catch (\Throwable $e) {
            error_log("[CodeHelper] generateCode error ({$table}): " . $e->getMessage());
            throw $e;
        }
    }

    /* ============================================================
     * 2) 사용자 코드 생성 (auth_users)
     * ============================================================ */
    public static function generateUserCode($pdo): int
    {
        return self::generateCode($pdo, 'auth_users');
    }

    /* ============================================================
     * 3) 직원 프로필 코드 생성 (user_profiles)
     * ============================================================ */
    public static function generateEmployeeCode($pdo): int
    {
        return self::generateCode($pdo, 'user_profiles');
    }

    /* ============================================================
     * 4) 클라이언트 코드 생성 (clients)
     * ============================================================ */
    public static function generateClientCode($pdo): int
    {
        return self::generateCode($pdo, 'system_clients');
    }

    /* ============================================================
     * 5) 역할 코드 생성 (auth_roles)
     * ============================================================ */
    public static function generateRoleCode($pdo): int
    {
        return self::generateCode($pdo, 'auth_roles');
    }

    /* ============================================================
     * 6) 직책 코드 생성 (user_positions)
     * ============================================================ */
    public static function generatePositionCode($pdo): int
    {
        return self::generateCode($pdo, 'user_positions');
    }

    /* ============================================================
     * 7) 부서 코드 생성 (user_departments)
     * ============================================================ */
    public static function generateDepartmentCode($pdo): int
    {
        return self::generateCode($pdo, 'user_departments');
    }

    /* ============================================================
     * 8) 권한 코드 생성 (auth_permissions)
     * ============================================================ */
    public static function generatePermissionCode($pdo): int
    {
        return self::generateCode($pdo, 'auth_permissions');
    }

    /* ============================================================
     * 9) 역할-권한 매핑 코드 생성 (auth_role_permissions)
     * ------------------------------------------------------------
     * RP-00001 형태로 생성됨 (문자 + 숫자 조합)
     * ============================================================ */
    public static function generateRolePermissionCode($pdo): string
    {
        try {
            $prefix = "RP";
            $stmt = $pdo->query("SELECT COUNT(*) FROM auth_role_permissions");
            $count = (int)$stmt->fetchColumn() + 1;
            return sprintf("%s-%05d", $prefix, $count);

        } catch (\Throwable $e) {
            error_log("[CodeHelper] generateRolePermissionCode error: " . $e->getMessage());
            return "RP-00001";
        }
    }

    /* ============================================================
    * 10) 커버 이미지 코드 생성 (system_coverimage_assets)
    * ============================================================ */
    public static function generateHomeAboutCoverImageCode($pdo): int
    {
        return self::generateCode($pdo, 'system_coverimage_assets');
    }

    /* ============================================================
    * 11) 보조계정 코드 생성 (ledger_sub_accounts)
    * account_id 기준으로 sub_code 증가
    * ============================================================ */
    public static function generateSubAccountCode($pdo, string $accountId): int
    {
        try {
    
            $sql = "
                SELECT COALESCE(MAX(CAST(sub_code AS UNSIGNED)),0) + 1
                FROM ledger_sub_accounts
                WHERE account_id = :account_id
            ";
    
            $stmt = $pdo->prepare($sql);
    
            $stmt->execute([
                ':account_id' => $accountId
            ]);
    
            return (int)$stmt->fetchColumn();
    
        } catch (\Throwable $e) {
    
            error_log("[CodeHelper] generateSubAccountCode error: " . $e->getMessage());
    
            return 1;
        }
    }

    /* ============================================================
    * 12) 프로젝트 코드 생성 (system_projects)
    * ============================================================ */
    public static function generateProjectCode($pdo): int
    {
        return self::generateCode($pdo, 'system_projects');
    }

}
