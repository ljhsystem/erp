<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Database.php';
require_once PROJECT_ROOT . '/core/DbPdo.php';

use App\Models\Institution\WorkplaceSizePeriodModel;
use App\Services\Institution\WorkplaceSizePeriodService;
use Core\Database;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

function concurrencyAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function runtimeConfig(): array
{
    $database = Database::getInstance();
    $method = new ReflectionMethod($database, 'loadRuntimeConfig');
    return $method->invoke($database);
}

function independentConnection(array $config, string $database): PDO
{
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'] ?? 3306, $database),
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
}

if (($argv[1] ?? '') === '--worker') {
    $database = (string) ($argv[2] ?? '');
    $payload = json_decode((string) base64_decode((string) ($argv[3] ?? ''), true), true, 512, JSON_THROW_ON_ERROR);
    $delay = (int) ($argv[4] ?? 0);
    $pdo = independentConnection(runtimeConfig(), $database);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
    $service = new WorkplaceSizePeriodService(
        $pdo,
        new WorkplaceSizePeriodModel($pdo),
        static function () use ($delay): string {
            if ($delay > 0) usleep($delay);
            return ActorHelper::system('WORKPLACE_SIZE_CONCURRENCY_TEST');
        }
    );
    try {
        $row = $service->register($payload);
        echo json_encode(['success' => true, 'id' => $row['id']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        echo json_encode(['success' => false, 'class' => $exception::class, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function runPair(string $database, array $first, array $second, int $firstDelay = 600000): array
{
    $start = static function (array $payload, int $delay) use ($database): array {
        $command = [PHP_BINARY, __FILE__, '--worker', $database, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), (string) $delay];
        $pipes = [];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, PROJECT_ROOT);
        concurrencyAssert(is_resource($process), '독립 DB Connection 프로세스를 시작할 수 없습니다.');
        fclose($pipes[0]);
        return [$process, $pipes];
    };
    [$processA, $pipesA] = $start($first, $firstDelay);
    usleep(100000);
    [$processB, $pipesB] = $start($second, 0);
    $collect = static function ($process, array $pipes): array {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        concurrencyAssert($exit === 0, '동시성 Worker 실패: ' . trim($stderr));
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    };
    return [$collect($processA, $pipesA), $collect($processB, $pipesB)];
}

$operating = DbPdo::conn();
$source = (string) $operating->query('SELECT DATABASE()')->fetchColumn();
$database = 'codex_workplace_concurrency_' . bin2hex(random_bytes(4));
concurrencyAssert((bool) preg_match('/^codex_workplace_concurrency_[0-9a-f]{8}$/', $database), '격리 DB 이름 검증 실패');
$before = [
    'periods' => (int) $operating->query('SELECT COUNT(*) FROM institution_workplace_size_periods')->fetchColumn(),
    'coverages' => (int) $operating->query('SELECT COUNT(*) FROM institution_social_insurance_coverages')->fetchColumn(),
];
$operating->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    $operating->exec("CREATE TABLE `{$database}`.`system_company` LIKE `{$source}`.`system_company`");
    $operating->exec("CREATE TABLE `{$database}`.`system_statutory_standards` LIKE `{$source}`.`system_statutory_standards`");
    $companyColumns = $operating->query("SHOW COLUMNS FROM `{$database}`.`system_company`")->fetchAll(PDO::FETCH_ASSOC);
    $values = [];
    foreach ($companyColumns as $column) {
        $name = (string) $column['Field'];
        if ($name === 'id') $values[$name] = 'fixture-company';
        elseif ($name === 'company_name_ko') $values[$name] = '격리검증 회사';
        elseif ($column['Null'] === 'NO' && $column['Default'] === null && $column['Extra'] === '') {
            $type = strtolower((string) $column['Type']);
            if (str_contains($type, 'int') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) $values[$name] = 0;
            elseif (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) $values[$name] = '2000-01-01 00:00:00';
            elseif (str_contains($type, 'date')) $values[$name] = '2000-01-01';
            else $values[$name] = 'FIXTURE';
        }
    }
    $columns = array_keys($values);
    $insertCompany = $operating->prepare("INSERT INTO `{$database}`.`system_company` (`" . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')');
    $insertCompany->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($values)));
    $migration = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260826_01_create_workplace_size_period_ssot.up.sql');
    $createTable = trim(strstr($migration, "\n\nALTER TABLE", true) ?: '');
    concurrencyAssert($createTable !== '', '회사규모 CREATE TABLE DDL을 찾을 수 없습니다.');
    independentConnection(runtimeConfig(), $database)->exec($createTable);

    $base = [
        'company_id' => 'fixture-company',
        'calculation_purpose_code' => 'EMPLOYMENT_INSURANCE_VOCATIONAL',
        'effective_from' => '2013-08-01',
        'effective_to' => '2013-08-31',
        'business_size_code' => 'LESS_THAN_150',
        'business_size_name_snapshot' => '150명 미만',
        'regular_worker_count' => 2,
        'calculation_basis_description' => '격리검증 산정기준',
        'evidence_type_code' => 'MANUAL_CONFIRMED',
        'evidence_description' => '격리검증 근거',
        'confirmation_status_code' => 'CONFIRMED',
    ];
    [$overlapA, $overlapB] = runPair($database, $base + ['request_key' => 'overlap-a'], $base + ['request_key' => 'overlap-b']);
    concurrencyAssert((int) $overlapA['success'] + (int) $overlapB['success'] === 1, '중복기간 동시 등록은 정확히 하나만 성공해야 합니다.');

    $pdo = independentConnection(runtimeConfig(), $database);
    $pdo->exec('DELETE FROM institution_workplace_size_periods');
    [$sameA, $sameB] = runPair($database, $base + ['request_key' => 'same-key'], $base + ['request_key' => 'same-key']);
    concurrencyAssert($sameA['success'] && $sameB['success'] && $sameA['id'] === $sameB['id'], '동일 요청 동시 멱등성 검증 실패');

    $pdo->exec('DELETE FROM institution_workplace_size_periods');
    [$collisionA, $collisionB] = runPair(
        $database,
        $base + ['request_key' => 'collision-key'],
        array_replace($base, ['regular_worker_count' => 3, 'request_key' => 'collision-key'])
    );
    concurrencyAssert((int) $collisionA['success'] + (int) $collisionB['success'] === 1, '동일 요청키의 다른 Payload는 하나만 성공해야 합니다.');

    $pdo->exec('DELETE FROM institution_workplace_size_periods');
    $seedService = new WorkplaceSizePeriodService($pdo, new WorkplaceSizePeriodModel($pdo), static fn(): string => ActorHelper::system('WORKPLACE_SIZE_CONCURRENCY_TEST'));
    $original = $seedService->register($base + ['request_key' => 'revision-original']);
    $correction = array_replace($base, ['previous_period_id' => $original['id'], 'correction_reason' => '격리 정정', 'request_key' => 'correction-a']);
    [$correctionA, $correctionB] = runPair($database, $correction, array_replace($correction, ['regular_worker_count' => 4, 'request_key' => 'correction-b']));
    concurrencyAssert((int) $correctionA['success'] + (int) $correctionB['success'] === 1, '동시 정정은 정확히 하나만 성공해야 합니다.');

    $counts = $pdo->query('SELECT COUNT(*) total, SUM(previous_period_id IS NULL) roots, SUM(previous_period_id IS NOT NULL) successors FROM institution_workplace_size_periods')->fetch(PDO::FETCH_ASSOC);
    concurrencyAssert((int) $counts['total'] === 2 && (int) $counts['successors'] === 1, '동시 정정 Revision 수 검증 실패');
    $after = [
        'periods' => (int) $operating->query('SELECT COUNT(*) FROM institution_workplace_size_periods')->fetchColumn(),
        'coverages' => (int) $operating->query('SELECT COUNT(*) FROM institution_social_insurance_coverages')->fetchColumn(),
    ];
    concurrencyAssert($before === $after, '격리검증 중 운영 회사규모 또는 Coverage가 변경되었습니다.');
    echo json_encode([
        'success' => true,
        'independent_connections' => true,
        'overlap' => [$overlapA, $overlapB],
        'same_request' => [$sameA, $sameB],
        'payload_collision' => [$collisionA, $collisionB],
        'concurrent_correction' => [$correctionA, $correctionB],
        'operating_before' => $before,
        'operating_after' => $after,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $operating->exec("DROP DATABASE IF EXISTS `{$database}`");
}
