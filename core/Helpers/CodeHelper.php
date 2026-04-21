<?php

namespace Core\Helpers;

use Core\Database;
use PDO;
use Throwable;

class CodeHelper
{
    public static function next(string $table): int
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }

        $pdo = Database::getInstance()->getConnection();

        try {
            $stmt = $pdo->query("SELECT COALESCE(MAX(code), 0) + 1 FROM {$table}");
            $next = (int) $stmt->fetchColumn();

            return $next > 0 ? $next : 1;
        } catch (Throwable $e) {
            error_log("[CodeHelper] next error ({$table}): " . $e->getMessage());
            throw $e;
        }
    }

    public static function normalizeCoverImageCodes(PDO $pdo): void
    {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->query("
                SELECT id, deleted_at, code
                FROM system_coverimage_assets
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pdo->exec("
                UPDATE system_coverimage_assets
                SET code = code + 100000
            ");

            usort($rows, function (array $a, array $b): int {
                $aDeleted = empty($a['deleted_at']) ? 0 : 1;
                $bDeleted = empty($b['deleted_at']) ? 0 : 1;

                if ($aDeleted !== $bDeleted) {
                    return $aDeleted <=> $bDeleted;
                }

                return (int) $a['code'] <=> (int) $b['code'];
            });

            $seq = 1;

            foreach ($rows as $row) {
                $stmt = $pdo->prepare("
                    UPDATE system_coverimage_assets
                    SET code = :code
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':code' => $seq++,
                    ':id' => $row['id'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
