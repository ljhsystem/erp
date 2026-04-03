<?php
// 경로: PROJECT_ROOT . '/core/DbPdo.php'
namespace Core;

 /**
 * DbPdo (Facade)
 * ------------------------------------------------------------
 * 시스템 전역에서 사용하는 PDO 접근 및 트랜잭션 관리 클래스
 *
 * ✔ 역할
 * - Database 클래스의 PDO 연결을 간편하게 사용하도록 제공
 * - 트랜잭션(begin/commit/rollback) 통합 관리
 * - 안전한 transaction() 실행 지원
 *
 * ✔ 구조
 * Controller / Service / Model
 *     ↓
 *   DbPdo (Facade)
 *     ↓
 * Database (Singleton, 실제 연결 생성)
 *     ↓
 * PDO (PHP 내장 DB 객체)
 *
 * ✔ 사용 규칙
 * - DB 연결: DbPdo::conn()
 * - 트랜잭션: DbPdo::transaction()
 * - 직접 begin/commit 사용은 최소화 (권장하지 않음)
 *
 * ✔ 주의사항
 * - Core\DbPdo 와 PHP의 \PDO 는 완전히 다른 객체
 * - \PDO 타입은 반드시 글로벌 네임스페이스(\PDO) 사용 권장
 *
 * ✔ 예시
 * ------------------------------------------------------------
 * $pdo = DbPdo::conn();
 *
 * DbPdo::transaction(function($pdo) {
 *     // DB 작업
 * });
 */

use Core\Database;

class DbPdo
{
    public static function conn(): \PDO
    {
        return Database::getInstance()->getConnection();
    }

    public static function begin(): void
    {
        $pdo = self::conn();

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }

    public static function commit(): void
    {
        $pdo = self::conn();

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    public static function rollBack(): void
    {
        $pdo = self::conn();

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    public static function inTransaction(): bool
    {
        return self::conn()->inTransaction();
    }

    public static function transaction(callable $callback)
    {
        $pdo = self::conn();

        $isOuter = !$pdo->inTransaction();

        try {
            if ($isOuter) {
                $pdo->beginTransaction();
            }

            $result = $callback($pdo);

            if ($isOuter && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;

        } catch (\Throwable $e) {

            if ($isOuter && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}