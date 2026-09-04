<?php

namespace App\Controllers\Main\Settings;

use App\Services\Backup\DatabaseBackupService;
use Core\DbPdo;

class DatabaseLogController
{
    private DatabaseBackupService $backupService;

    public function __construct()
    {
        $this->backupService = new DatabaseBackupService(DbPdo::conn());
    }

    public function apiActivityLog(): void
    {
        try {
            $dir = rtrim($this->backupService->getBackupDirectory(), '/');
            $backupLog = $this->readLogTail($dir . '/backup_log.txt', '백업 로그가 없습니다.');
            $syncLog = $this->readLogTail($dir . '/secondary_restore_log.txt', '동기화 로그가 없습니다.');
            $restoreLog = $this->readLogTail($dir . '/active_restore_log.txt', '복원 로그가 없습니다.');

            $this->respondJson([
                'success' => true,
                'data' => [
                    'log' => "[SQL 백업]\n{$backupLog}\n\n[DB 동기화]\n{$syncLog}\n\n[DB 복원]\n{$restoreLog}",
                ],
            ]);
        } catch (\Throwable) {
            $this->respondJson([
                'success' => false,
                'message' => '통합 로그 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function readLogTail(string $path, string $fallback): string
    {
        if (!is_file($path)) {
            return $fallback;
        }

        $fp = fopen($path, 'rb');
        if (!$fp) {
            return $fallback;
        }

        $size = filesize($path);
        $readSize = min($size, 20000);
        if ($size > 0) {
            fseek($fp, -$readSize, SEEK_END);
        }

        $text = fread($fp, $readSize) ?: '';
        fclose($fp);

        return mb_convert_encoding((string) $text, 'UTF-8', 'UTF-8,CP949,EUC-KR,ISO-8859-1');
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'JSON 응답 생성 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
    }
}
