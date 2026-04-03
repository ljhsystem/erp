<?php
// 경로: PROJECT_ROOT . '/app/services/backup/DatabaseBackupService.php'
namespace App\Services\Backup;

// require_once PROJECT_ROOT . '/app/services/system/SettingService.php';

use PDO;
use Throwable;
use function Core\storage_system_path;
use App\Services\System\SettingService;
use Core\LoggerFactory;

class DatabaseBackupService
{
    private readonly PDO $pdo;
    private string $backupDir;
    private SettingService $settings;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo      = $pdo;
        $this->settings = new SettingService($pdo);

        $backupPath = storage_system_path('db_backup');
        if (!$backupPath) {
            throw new \RuntimeException('DB backup storage path not configured');
        }

        $this->backupDir = rtrim($backupPath, '/') . '/';

        // ⚠️ PHP 전역 타임존 변경은 프로젝트 정책에 따라 OK/NO가 갈릴 수 있음.
        // 현재 프로젝트에서 Asia/Seoul 고정이 맞다면 유지.
        date_default_timezone_set('Asia/Seoul');

        $this->logger = LoggerFactory::getLogger('service-backup.DatabaseBackupService');
    }

    /**
     * 수동/공용 백업 실행
     * - 관리자 버튼(수동)에서도 사용
     * - 자동(cron)에서도 내부적으로 호출
     */
    public function backupDatabase(): array
    {
        // 1) DB명 확인
        try {
            $this->pdo->exec("SET NAMES utf8mb4");
            $dbName = $this->pdo->query("SELECT DATABASE()")->fetchColumn();

            if (!$dbName) {
                $this->logger->error('[BACKUP] Database name not resolved');
                return ['success' => false, 'message' => '데이터베이스명을 가져올 수 없습니다.'];
            }
        } catch (Throwable $e) {
            $this->logger->error('[BACKUP] DB connect failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'DB 연결 실패: ' . $e->getMessage()];
        }

        // 2) 백업 폴더 확보
        if (!is_dir($this->backupDir) && !mkdir($this->backupDir, 0777, true)) {
            $this->logger->error('[BACKUP] Backup dir create failed', ['dir' => $this->backupDir]);
            return ['success' => false, 'message' => "백업 폴더 생성 실패: {$this->backupDir}"];
        }

        // 3) 정책 기반 정리
        $cleanupEnabled = $this->settings->getBool('backup_cleanup_enabled', true);
        $retentionDays  = $this->normalizeRetentionDays(
            $this->settings->getInt('backup_retention_days', 30)
        );

        if ($cleanupEnabled) {
            $this->cleanupOldBackupsByDays($dbName, $retentionDays);
        }

        // 4) 파일명
        $timestamp = date('Y-m-d_His');
        $filename  = "{$dbName}_{$timestamp}.sql";
        $filepath  = $this->backupDir . $filename;

        $this->logger->info('[BACKUP] Start', [
            'db' => $dbName,
            'file' => $filename,
            'retention_days' => $retentionDays,
            'cleanup_enabled' => $cleanupEnabled ? 1 : 0,
        ]);

        // 5) 덤프 헤더
        $sqlDump  = "-- Sukhyang ERP Database Backup\n";
        $sqlDump .= "-- Database: {$dbName}\n";
        $sqlDump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
        $sqlDump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        // 6) 테이블 목록
        try {
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $this->logger->error('[BACKUP] SHOW TABLES failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => '테이블 목록 조회 실패: ' . $e->getMessage()];
        }

        foreach ($tables as $table) {
            try {
                // 6-1) 테이블 구조
                $create = $this->pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $createSql = $create['Create Table'] ?? '';

                $sqlDump .= "\n-- ----------------------------\n";
                $sqlDump .= "-- Table structure for `{$table}`\n";
                $sqlDump .= "-- ----------------------------\n";
                $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";

                // 6-2) 데이터
                $rows = $this->pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

                if ($rows) {
                    $sqlDump .= "-- Data for `{$table}`\n";

                    foreach ($rows as $row) {
                        $columns = array_keys($row);
                        $values  = array_map(
                            fn($v) => isset($v) ? $this->pdo->quote($v) : 'NULL',
                            $row
                        );

                        $sqlDump .= "INSERT INTO `{$table}` (`"
                            . implode("`,`", $columns)
                            . "`) VALUES ("
                            . implode(",", $values)
                            . ");\n";
                    }
                    $sqlDump .= "\n";
                } else {
                    $sqlDump .= "-- `{$table}` 데이터 없음\n\n";
                }

            } catch (Throwable $e) {
                // 특정 테이블에서 오류가 나면 전체 백업이 실패할 수 있으니 실패 처리
                $this->logger->error('[BACKUP] Table dump failed', [
                    'table' => $table,
                    'error' => $e->getMessage()
                ]);
                return ['success' => false, 'message' => "테이블 백업 실패: {$table} / " . $e->getMessage()];
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // 7) 파일 저장
        if (file_put_contents($filepath, $sqlDump) === false) {
            $this->logger->error('[BACKUP] File write failed', ['path' => $filepath]);
            return ['success' => false, 'message' => "백업 파일 저장 실패: {$filepath}"];
        }

        // 8) 로그 기록
        $this->writeLog($filename, $filepath);

        $size = @filesize($filepath) ?: 0;

        $this->logger->info('[BACKUP] Done', [
            'file' => $filename,
            'path' => $filepath,
            'size' => $size
        ]);

        return [
            'success'  => true,
            'message'  => '백업 완료',
            'filename' => $filename,
            'path'     => $filepath,
            'size'     => $size,
        ];
    }
    // ⚠️ NOTE:
    // 현재 백업은 PHP 기반 덤프 방식.
    // 대용량 DB로 성장 시 mysqldump 기반으로 교체 고려.


    /**
     * 자동 백업 실행 (cron 전용)
     * - cron은 이 메서드만 호출하는 구조를 권장
     */
    public function runAutoBackup(): array
    {
        $enabled  = $this->settings->getBool('backup_auto_enabled', false);
        $schedule = $this->settings->get('backup_schedule', 'daily'); // 운영 로그용

        if (!$enabled) {
            $this->logger->info('[AUTO_BACKUP] Disabled', ['schedule' => $schedule]);
            return [
                'success' => false,
                'message' => '자동 백업 비활성화 상태'
            ];
        }

        $this->logger->info('[AUTO_BACKUP] Start', ['schedule' => $schedule]);
        $result = $this->backupDatabase();

        if ($result['success']) {
            $restoreEnabled = $this->settings->getBool(
                'backup_restore_secondary_enabled',
                false
            );

            if ($restoreEnabled) {
                $this->logger->info('[AUTO_BACKUP] Secondary restore enabled');
                $this->restoreLatestBackupToSecondary();
            }
        }

return $result;

    }

    /**
     * 보관기간 입력값 안전 보정 (최소/최대)
     */
    private function normalizeRetentionDays(int $days): int
    {
        if ($days < 1) return 1;
        if ($days > 365) return 365;
        return $days;
    }

    /**
     * 날짜 기준 오래된 백업 삭제
     */
    private function cleanupOldBackupsByDays(string $dbName, int $days): void
    {
        $pattern = $this->backupDir . "{$dbName}_*.sql";
        $files   = glob($pattern) ?: [];

        $expireTime = time() - ($days * 86400);

        $deleted = 0;
        foreach ($files as $file) {
            if (@filemtime($file) < $expireTime) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $this->logger->info('[BACKUP] Cleanup done', [
                'db' => $dbName,
                'retention_days' => $days,
                'deleted' => $deleted
            ]);
        }
    }

    public function getBackupDirectory(): string
    {
        return $this->backupDir;
    }

    public function getLatestBackupFile(): ?array
    {
        $dbName = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        if (!$dbName) return null;

        $pattern = $this->backupDir . "{$dbName}_*.sql";
        $files = glob($pattern);

        if (!$files) return null;

        // 최근 파일
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];

        return [
            'file' => basename($latest),
            'time' => date('Y-m-d H:i:s', filemtime($latest)),
            'path' => $latest
        ];
    }

    private function writeLog(string $filename, string $filepath): void
    {
        $logFile = $this->backupDir . 'backup_log.txt';
        $size    = @filesize($filepath) ?: 0;

        $line = sprintf("[%s] 백업 완료: %s (%d bytes)\n",
            date('Y-m-d H:i:s'),
            $filename,
            $size
        );

        @file_put_contents($logFile, $line, FILE_APPEND);
    }




    private function connectSecondaryPdo(array $sec, string $db): \PDO
    {
        $host = $sec['host'] ?? '';
        $port = $sec['port'] ?? 3306;
        $user = $sec['user'] ?? '';
        $pass = $sec['pass'] ?? '';
    
        if ($host === '' || $user === '' || $db === '') {
            throw new \RuntimeException('Secondary PDO 접속 정보가 올바르지 않습니다.');
        }
    
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }
    
    private function dropAllTables(\PDO $pdo): void
    {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
        $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
    
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }







        /**
     * 최신 백업 파일을 Secondary DB에 복원
     */
    public function restoreLatestBackupToSecondary(): array
    {
        $latest = $this->getLatestBackupFile();
        if (!$latest) {
            return [
                'success' => false,
                'message' => '복원할 백업 파일이 없습니다.'
            ];
        }

        // 🔐 Secondary DB 접속 정보 (시스템 설정 기반)
        $configPath = PROJECT_ROOT . '/../secure-config/db_replication.php';

        if (!file_exists($configPath)) {
            return [
                'success' => false,
                'message' => 'DB 이중화 설정 파일이 존재하지 않습니다.'
            ];
        }
        
        $config = require $configPath;
        
        if (empty($config['secondary'])) {
            return [
                'success' => false,
                'message' => 'Secondary DB 설정이 없습니다.'
            ];
        }
        
        $sec = $config['secondary'];
        
        $host = $sec['host'] ?? null;
        $port = $sec['port'] ?? 3306;
        $user = $sec['user'] ?? null;
        $pass = $sec['pass'] ?? null;
        
        // ✅ DB 이름은 현재 DB와 동일하게 사용
        $db = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        
        if (!$host || !$user) {
            return [
                'success' => false,
                'message' => 'Secondary DB 접속 정보(host/user)가 없습니다.'
            ];
        }
        
        if (!$db) {
            return [
                'success' => false,
                'message' => 'Primary DB 이름을 확인할 수 없습니다.'
            ];
        }        
        

        // $sqlFile = $latest['path'];

        // // ⚠️ 비밀번호는 shell에 노출되므로 proc_open 사용
        // $cmd = sprintf(
        //     'mysql --protocol=tcp -h%s -P%s -u%s %s',
        //     escapeshellarg($host),
        //     escapeshellarg($port),
        //     escapeshellarg($user),
        //     escapeshellarg($db)
        // );
        

        // $this->logger->info('[SECONDARY_RESTORE] Start', [
        //     'file' => $latest['file'],
        //     'db'   => $db,
        //     'host' => $host
        // ]);

        // $descriptors = [
        //     0 => ['pipe', 'r'], // stdin
        //     1 => ['pipe', 'w'], // stdout
        //     2 => ['pipe', 'w'], // stderr
        // ];

        $sqlFile = $latest['path'];

        $this->logger->info('[SECONDARY_RESTORE] Start', [
            'file' => $latest['file'],
            'db'   => $db,
            'host' => $host
        ]);

        /* =========================================================
        * 1. Secondary DB 전체 테이블 삭제
        * ========================================================= */
        try {
            $secondaryPdo = $this->connectSecondaryPdo($sec, $db);

            $tableCountBefore = (int)$secondaryPdo
                ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $secondaryPdo->quote($db))
                ->fetchColumn();

            $this->logger->info('[SECONDARY_RESTORE] Drop start', [
                'db' => $db,
                'table_count_before' => $tableCountBefore
            ]);

            $this->dropAllTables($secondaryPdo);

            $tableCountAfterDrop = (int)$secondaryPdo
                ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $secondaryPdo->quote($db))
                ->fetchColumn();

            $this->logger->info('[SECONDARY_RESTORE] Drop done', [
                'db' => $db,
                'table_count_after_drop' => $tableCountAfterDrop
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('[SECONDARY_RESTORE] Drop failed', [
                'db'    => $db,
                'host'  => $host,
                'error' => $e->getMessage()
            ]);

            $this->writeSecondaryRestoreFailLog(
                $latest['file'] . ' / DROP 실패 / ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Secondary DB 기존 테이블 삭제 실패',
                'error'   => $e->getMessage()
            ];
        }

        /* =========================================================
        * 2. mysql CLI로 백업 SQL 전체 주입
        * ========================================================= */
        // ⚠️ 비밀번호는 shell에 노출되므로 proc_open 사용
        $cmd = sprintf(
            'mysql --protocol=tcp -h%s -P%s -u%s %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db)
        );

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];







///////////////////////////////////////////////////////////////////260319수정/////////////







        $process = proc_open($cmd, $descriptors, $pipes, null, [
            'MYSQL_PWD' => $pass
        ]);

        if (!is_resource($process)) {
            $this->logger->error('[SECONDARY_RESTORE] proc_open failed');
            return [
                'success' => false,
                'message' => 'mysql 프로세스 실행 실패'
            ];
        }
        
        // SQL 파일 주입
        $fh = fopen($sqlFile, 'rb');
        if (!$fh) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        
            return [
                'success' => false,
                'message' => '백업 파일을 열 수 없습니다.'
            ];
        }
        
        while (!feof($fh)) {
            $chunk = fread($fh, 8192);
        
            if ($chunk === false) {
                fclose($fh);
                fclose($pipes[0]);
        
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
        
                fclose($pipes[1]);
                fclose($pipes[2]);
        
                proc_close($process);
        
                return [
                    'success' => false,
                    'message' => 'SQL 파일 읽기 실패',
                    'error'   => $stderr ?: $stdout
                ];
            }
        
            if ($chunk === '') {
                continue;
            }
        
            $written = @fwrite($pipes[0], $chunk);
        
            if ($written === false || $written === 0) {
                fclose($fh);
                fclose($pipes[0]);
        
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
        
                fclose($pipes[1]);
                fclose($pipes[2]);
        
                $exitCode = proc_close($process);
        
                $this->logger->error('[SECONDARY_RESTORE] stdin write failed', [
                    'exit_code' => $exitCode,
                    'stderr'    => $stderr,
                    'stdout'    => $stdout,
                    'file'      => $latest['file'] ?? basename($sqlFile),
                ]);
        
                $this->writeSecondaryRestoreFailLog(
                    ($latest['file'] ?? basename($sqlFile)) . ' / ' . trim($stderr ?: 'stdin write failed')
                );
        
                return [
                    'success' => false,
                    'message' => 'Secondary DB 복원 중 mysql 프로세스가 종료되었습니다.',
                    'error'   => $stderr ?: 'Broken pipe'
                ];
            }
        }
        
        fclose($fh);
        fclose($pipes[0]);
        
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->logger->error('[SECONDARY_RESTORE] Failed', [
                'stderr' => $stderr,
                'stdout' => $stdout
            ]);
        
            $this->writeSecondaryRestoreFailLog(
                $latest['file'] . ' / ' . trim($stderr)
            );
        
            return [
                'success' => false,
                'message' => 'Secondary DB 복원 실패',
                'error'   => $stderr ?: 'mysql 프로세스 오류'
            ];
        }
        
        
        // // ✅ 성공 로그
        // $this->writeSecondaryRestoreLog($latest['file']);
        

        // $this->logger->info('[SECONDARY_RESTORE] Done', [
        //     'file' => $latest['file']
        // ]);

        // return [
        //     'success'  => true,
        //     'message'  => 'Secondary DB 복원 완료',
        //     'file'     => $latest['file'],
        //     'time'     => date('Y-m-d H:i:s')
        // ];


        try {
            $secondaryPdo = $this->connectSecondaryPdo($sec, $db);
        
            $tableCountAfterRestore = (int)$secondaryPdo
                ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $secondaryPdo->quote($db))
                ->fetchColumn();
        
            $this->logger->info('[SECONDARY_RESTORE] Verify done', [
                'file' => $latest['file'],
                'table_count_after_restore' => $tableCountAfterRestore
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('[SECONDARY_RESTORE] Verify skipped', [
                'error' => $e->getMessage()
            ]);
        }
        
        // ✅ 성공 로그
        $this->writeSecondaryRestoreLog($latest['file']);
        
        $this->logger->info('[SECONDARY_RESTORE] Done', [
            'file' => $latest['file']
        ]);
        
        return [
            'success'  => true,
            'message'  => 'Secondary DB 복원 완료',
            'file'     => $latest['file'],
            'time'     => date('Y-m-d H:i:s')
        ];


    }





    private function writeSecondaryRestoreLog(string $filename): void
    {
        $logFile = $this->backupDir . 'secondary_restore_log.txt';
    
        $line = sprintf(
            "[%s] Secondary 복원 완료: %s\n",
            date('Y-m-d H:i:s'),
            $filename
        );
    
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    public function getLatestSecondaryRestore(): ?array
    {
        $logFile = $this->backupDir . 'secondary_restore_log.txt';
        if (!is_file($logFile)) return null;

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return null;

        $last = array_pop($lines);

        if (preg_match('/\[(.*?)\] Secondary 복원 완료: (.+)$/', $last, $m)) {
            return [
                'time' => $m[1],
                'file' => $m[2]
            ];
        }

        return null;
    }

    private function writeSecondaryRestoreFailLog(string $message): void
    {
        $logFile = $this->backupDir . 'secondary_restore_log.txt';

        $line = sprintf(
            "[%s] ❌ FAILED: %s\n",
            date('Y-m-d H:i:s'),
            $message
        );

        @file_put_contents($logFile, $line, FILE_APPEND);
    }


    
}


