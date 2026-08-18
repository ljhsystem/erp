<?php

declare(strict_types=1);

use App\Services\System\BankAccountService;
use App\Services\File\FileService;
use Core\Database;

define('PROJECT_ROOT', dirname(__DIR__));
require_once PROJECT_ROOT . '/core/Storage.php';
require_once PROJECT_ROOT . '/core/Bootstrap.php';

final class BankAccountRegressionFileService extends FileService
{
    public array $deletedPaths = [];
    private int $uploadSequence = 0;

    public function __construct()
    {
    }

    public function uploadBankCopy(array $file): array
    {
        $this->uploadSequence++;
        return ['success' => true, 'db_path' => 'private://bank-copy/regression-' . $this->uploadSequence . '.png'];
    }

    public function delete(?string $dbPath): bool
    {
        if ($dbPath !== null && $dbPath !== '') {
            $this->deletedPaths[] = $dbPath;
        }
        return true;
    }
}

$pdo = Database::getInstance()->getConnection();
$service = new BankAccountService($pdo);
$fileService = new BankAccountRegressionFileService();
$fileServiceProperty = new ReflectionProperty(BankAccountService::class, 'fileService');
$fileServiceProperty->setValue($service, $fileService);
$suffix = bin2hex(random_bytes(6));
$ids = [
    'unused' => 'test-bank-unused-' . $suffix,
    'used' => 'test-bank-used-' . $suffix,
    'bulk_unused' => 'test-bank-bulk-u-' . $suffix,
    'bulk_used' => 'test-bank-bulk-r-' . $suffix,
];
$cardIds = [
    'used' => 'test-card-used-' . $suffix,
    'bulk_used' => 'test-card-bulk-' . $suffix,
];
$checks = [];
$createdServiceIds = [];

$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($label);
    }
    $checks[] = $label;
};

$insertAccount = static function (PDO $db, string $id, int $sortNo): void {
    $stmt = $db->prepare(
        'INSERT INTO system_bank_accounts '
        . '(id, sort_no, account_name, currency, is_active, created_by, updated_by, deleted_at, deleted_by) '
        . "VALUES (:id, :sort_no, :name, 'KRW', 1, 'SYSTEM:REGRESSION', 'SYSTEM:REGRESSION', NOW(), 'SYSTEM:REGRESSION')"
    );
    $stmt->execute([':id' => $id, ':sort_no' => $sortNo, ':name' => '회귀검증 계좌 ' . $id]);
};

$insertCard = static function (PDO $db, string $id, string $accountId, int $sortNo): void {
    $stmt = $db->prepare(
        'INSERT INTO system_cards (id, sort_no, card_name, account_id, is_active, created_by) '
        . "VALUES (:id, :sort_no, :name, :account_id, 1, 'SYSTEM:REGRESSION')"
    );
    $stmt->execute([
        ':id' => $id,
        ':sort_no' => $sortNo,
        ':name' => '회귀검증 카드 ' . $id,
        ':account_id' => $accountId,
    ]);
};

try {
    $baseAccountSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_no), 0) + 100 FROM system_bank_accounts')->fetchColumn();
    $baseCardSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_no), 0) + 100 FROM system_cards')->fetchColumn();

    $insertAccount($pdo, $ids['unused'], $baseAccountSort);
    $unusedResult = $service->purge($ids['unused'], 'SYSTEM');
    $assert(($unusedResult['success'] ?? false) === true, '미참조 계좌 영구삭제 성공');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM system_bank_accounts WHERE id = " . $pdo->quote($ids['unused']))->fetchColumn() === 0, '미참조 계좌 DB 행 제거');

    $insertAccount($pdo, $ids['used'], $baseAccountSort + 1);
    $insertCard($pdo, $cardIds['used'], $ids['used'], $baseCardSort);
    $usedResult = $service->purge($ids['used'], 'SYSTEM');
    $assert(($usedResult['success'] ?? true) === false, '참조 계좌 영구삭제 차단');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM system_bank_accounts WHERE id = " . $pdo->quote($ids['used']))->fetchColumn() === 1, '차단된 계좌 유지');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM system_cards WHERE id = " . $pdo->quote($cardIds['used']) . " AND account_id = " . $pdo->quote($ids['used']))->fetchColumn() === 1, '참조 카드 관계 유지');

    $insertAccount($pdo, $ids['bulk_unused'], $baseAccountSort + 2);
    $insertAccount($pdo, $ids['bulk_used'], $baseAccountSort + 3);
    $insertCard($pdo, $cardIds['bulk_used'], $ids['bulk_used'], $baseCardSort + 1);
    $bulkResult = $service->purgeBulk([$ids['bulk_unused'], $ids['bulk_used']], 'SYSTEM');
    $assert((int) ($bulkResult['deleted_count'] ?? -1) === 1, '일괄 영구삭제 부분성공 삭제 건수');
    $assert((int) ($bulkResult['skipped_count'] ?? -1) === 1, '일괄 영구삭제 부분성공 차단 건수');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM system_bank_accounts WHERE id = " . $pdo->quote($ids['bulk_unused']))->fetchColumn() === 0, '일괄 미참조 계좌 제거');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM system_bank_accounts WHERE id = " . $pdo->quote($ids['bulk_used']))->fetchColumn() === 1, '일괄 참조 계좌 유지');

    $validationResult = $service->save(['account_name' => ''], 'SYSTEM');
    $assert(($validationResult['success'] ?? true) === false, '서버 필수값 검증');
    $assert(($validationResult['message'] ?? '') === '계좌명은 필수입니다.', '서버 검증 한글 메시지');

    $uploadFile = ['name' => 'bank-copy.png', 'error' => UPLOAD_ERR_OK, 'size' => 128, 'tmp_name' => 'regression'];
    $failedSave = $service->save([
        'id' => 'missing-bank-' . $suffix,
        'account_name' => '존재하지 않는 계좌',
        'account_number' => '1234',
    ], 'SYSTEM', ['bank_file' => $uploadFile]);
    $assert(($failedSave['success'] ?? true) === false, 'DB 실패 저장 차단');
    $assert(in_array('private://bank-copy/regression-1.png', $fileService->deletedPaths, true), 'DB 실패 신규 파일 보상 삭제');

    $createdSave = $service->save([
        'account_name' => '파일 원자성 회귀검증 계좌',
        'account_number' => '5678',
    ], 'SYSTEM', ['bank_file' => $uploadFile]);
    $assert(($createdSave['success'] ?? false) === true, '파일 포함 계좌 저장 성공');
    $createdServiceIds[] = (string) $createdSave['id'];
    $assert(!in_array('private://bank-copy/regression-2.png', $fileService->deletedPaths, true), 'DB 성공 신규 파일 유지');
    $pdo->prepare('UPDATE system_bank_accounts SET deleted_at = NOW(), deleted_by = :actor WHERE id = :id')
        ->execute([':actor' => 'SYSTEM:REGRESSION', ':id' => $createdSave['id']]);
    $purgedFileAccount = $service->purge((string) $createdSave['id'], 'SYSTEM');
    $assert(($purgedFileAccount['success'] ?? false) === true, '파일 포함 계좌 영구삭제 성공');
    $assert(in_array('private://bank-copy/regression-2.png', $fileService->deletedPaths, true), '영구삭제 commit 후 파일 정리');

    $method = new ReflectionMethod(BankAccountService::class, 'resolveColumns');
    $templateColumns = $method->invoke($service, 'template', 'created_at,created_by_name,bank_file,account_name');
    $templateKeys = array_column($templateColumns, 'key');
    $assert($templateKeys === ['account_name'], 'Excel 입력 비허용 시스템/파일 컬럼 제외');

    $registry = $pdo->prepare('SELECT default_route_url FROM system_page_registry WHERE page_key = :page_key');
    $registry->execute([':page_key' => 'settings.base_info.bank_accounts']);
    $assert($registry->fetchColumn() === '/dashboard/settings/base-info/bank-account', 'Page Registry Canonical URL');

    echo json_encode(['success' => true, 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} finally {
    $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM system_cards WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($cardIds));

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM system_bank_accounts WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($ids));
    if ($createdServiceIds !== []) {
        $placeholders = implode(',', array_fill(0, count($createdServiceIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM system_bank_accounts WHERE id IN ({$placeholders})");
        $stmt->execute($createdServiceIds);
    }
}
