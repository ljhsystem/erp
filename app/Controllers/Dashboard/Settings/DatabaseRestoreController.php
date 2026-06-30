<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\Backup\DatabaseRestoreService;
use App\Services\Backup\DatabaseSyncService;
use Core\DbPdo;

class DatabaseRestoreController
{
    private DatabaseRestoreService $restoreService;
    private DatabaseSyncService $syncService;

    public function __construct()
    {
        $pdo = DbPdo::conn();
        $this->restoreService = new DatabaseRestoreService($pdo);
        $this->syncService = new DatabaseSyncService($pdo);
    }

    public function apiRestore(): void
    {
        try {
            $latestRestore = $this->restoreService->getLatestRestoreInfo();
            if (($latestRestore['state'] ?? '') === 'running') {
                $this->respondJson([
                    'success' => false,
                    'state' => 'running',
                    'message' => 'DB 복원이 이미 진행 중입니다.',
                ], 409);
                return;
            }

            $latestSync = $this->syncService->getLatestSyncInfo();
            if (($latestSync['state'] ?? '') === 'running') {
                $this->respondJson([
                    'success' => false,
                    'state' => 'running',
                    'message' => 'DB 동기화 진행 중에는 복원을 실행할 수 없습니다.',
                ], 409);
                return;
            }

            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $_POST;
            }

            $file = trim((string) ($payload['file'] ?? ''));
            if ($file === '') {
                $this->respondJson([
                    'success' => false,
                    'message' => '복원할 백업 파일을 선택해 주세요.',
                ], 422);
                return;
            }

            @session_write_close();
            ignore_user_abort(true);
            @set_time_limit(0);

            $this->respondJson([
                'success' => true,
                'state' => 'running',
                'message' => 'DB 복원 요청을 접수했습니다. 상태 카드에서 진행 상황을 확인해 주세요.',
            ], 202);

            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } else {
                @flush();
            }

            $this->restoreService->restoreBackupFileToActive($file, 'restore');
        } catch (\Throwable) {
            $this->respondJson([
                'success' => false,
                'state' => 'failed',
                'message' => 'DB 복원 시작 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    public function apiRestoreInfo(): void
    {
        try {
            $info = $this->restoreService->getLatestRestoreInfo();

            $this->respondJson([
                'success' => true,
                'data' => [
                    'state' => $this->normalizeText($info['state'] ?? 'idle'),
                    'message' => $this->toRestoreMessage($info),
                    'file' => $this->normalizeText($info['file'] ?? null),
                    'started_at' => $this->normalizeText($info['started_at'] ?? null),
                    'finished_at' => $this->normalizeText($info['finished_at'] ?? null),
                    'updated_at' => $this->normalizeText($info['updated_at'] ?? null),
                    'stage' => $this->normalizeText($info['stage'] ?? null),
                    'stage_label' => $this->toStageLabel((string) ($info['stage'] ?? '')),
                    'last_restored_file' => $this->normalizeText($info['restored_file'] ?? null),
                    'last_restored_at' => $this->normalizeText($info['restored_at'] ?? null),
                    'last_error' => $this->normalizeText($info['error'] ?? null),
                    'stale' => (bool) ($info['stale'] ?? false),
                    'statement_count' => (int) ($info['statement_count'] ?? 0),
                    'runtime' => is_array($info['runtime'] ?? null) ? $info['runtime'] : null,
                    'active_db' => $this->normalizeNode($info['active_db'] ?? null),
                    'standby_db' => $this->normalizeNode($info['standby_db'] ?? null),
                ],
            ]);
        } catch (\Throwable) {
            $this->respondJson([
                'success' => false,
                'message' => 'DB 복원 상태 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function toRestoreMessage(array $info): string
    {
        return match ((string) ($info['state'] ?? 'idle')) {
            'running' => 'DB 복원이 진행 중입니다.',
            'stale_suspected' => '복원 상태 확인이 필요합니다.',
            'success' => 'DB 복원이 완료되었습니다.',
            'failed' => 'DB 복원에 실패했습니다.',
            default => 'DB 복원 이력이 없습니다.',
        };
    }

    private function toStageLabel(string $stage): string
    {
        return match ($stage) {
            'starting' => 'starting',
            'validate-backup-file' => 'validate-backup-file',
            'prepare-active' => 'prepare-active',
            'apply-backup' => 'apply-sql-by-pdo',
            'stale-suspected' => 'stale-suspected',
            'completed' => 'completed',
            'stale-timeout' => 'timeout',
            'no-backup-file' => 'no-backup-file',
            default => $stage !== '' ? $stage : '-',
        };
    }

    private function normalizeNode(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return [
            'role' => $this->normalizeText($value['role'] ?? null),
            'host' => $this->normalizeText($value['host'] ?? null),
            'port' => isset($value['port']) ? (int) $value['port'] : null,
            'dbname' => $this->normalizeText($value['dbname'] ?? null),
            'label' => $this->normalizeText($value['label'] ?? null),
            'topology_role' => $this->normalizeText($value['topology_role'] ?? null),
        ];
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        $normalized = mb_convert_encoding($text, 'UTF-8', 'UTF-8,CP949,EUC-KR,ISO-8859-1');
        return preg_replace('/^\xEF\xBB\xBF/u', '', $normalized) ?? $normalized;
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
