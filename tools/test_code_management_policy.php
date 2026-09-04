<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require_once PROJECT_ROOT . '/core/Storage.php';
require_once PROJECT_ROOT . '/core/Bootstrap.php';

use App\Models\System\CodeModel;
use App\Services\System\CodeReferenceRegistry;
use App\Services\System\CodeReferenceService;
use Core\Database;

$pdo = Database::getInstance()->getConnection();
$model = new CodeModel($pdo);
$references = new CodeReferenceService($model);
$passed = 0;

$assert = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passed++;
};

$dbGroups = $pdo->query('SELECT DISTINCT code_group FROM system_codes ORDER BY code_group')->fetchAll(PDO::FETCH_COLUMN);
$registryGroups = CodeReferenceRegistry::groups();
$assert(count($dbGroups) === 67, '운영 코드그룹 수가 67개가 아닙니다.');
$assert(count($registryGroups) === 67, 'Registry 코드그룹 수가 67개가 아닙니다.');
$assert(array_diff($dbGroups, $registryGroups) === [], 'Registry에 누락된 운영 코드그룹이 있습니다.');
$assert(array_diff($registryGroups, $dbGroups) === [], '운영 DB에 없는 Registry 코드그룹이 있습니다.');
$assert(CodeReferenceRegistry::policy('UNREGISTERED_TEST_GROUP') === null, '미등록 그룹이 fail-closed 상태가 아닙니다.');
$assert(CodeReferenceRegistry::policy('STATUTORY_STANDARD_TYPE') !== null, '법정기준 종류 정책이 없습니다.');
$assert(CodeReferenceRegistry::policy('STATUTORY_ROUNDING_METHOD') !== null, '끝수처리 정책이 없습니다.');

foreach ($registryGroups as $group) {
    $policy = CodeReferenceRegistry::policy($group);
    $assert(is_array($policy), "{$group} 정책을 읽을 수 없습니다.");
    foreach (array_merge($policy['columns'], $policy['json']) as $target) {
        [$table, $column] = $target;
        $assert($model->tableExists($table), "Registry 테이블이 없습니다: {$table}");
        $assert($model->columnExists($table, $column), "Registry 컬럼이 없습니다: {$table}.{$column}");
    }
}

$duplicateCount = (int) $pdo->query('SELECT COUNT(*) FROM (SELECT code_group,code FROM system_codes GROUP BY code_group,code HAVING COUNT(*)>1) duplicates')->fetchColumn();
$assert($duplicateCount === 0, '중복 코드가 있습니다.');
$invalidJsonCount = (int) $pdo->query("SELECT COUNT(*) FROM system_codes WHERE extra_data IS NOT NULL AND TRIM(extra_data)<>'' AND JSON_VALID(extra_data)=0")->fetchColumn();
$assert($invalidJsonCount === 0, '잘못된 extra_data JSON이 있습니다.');
$softDeleteColumns = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_codes' AND COLUMN_NAME IN ('deleted_at','deleted_by')")->fetchColumn();
$assert($softDeleteColumns === 0, 'system_codes에 Soft Delete 컬럼이 남아 있습니다.');
$inactiveReturned = (int) $pdo->query('SELECT COUNT(*) FROM system_codes WHERE is_active<>1 AND id IN (SELECT id FROM system_codes WHERE is_active=1)')->fetchColumn();
$assert($inactiveReturned === 0, '활성 선택 조건이 올바르지 않습니다.');

$type = $pdo->query("SELECT code,code_name FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' ORDER BY sort_no LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$typeStatus = $references->inspect('STATUTORY_STANDARD_TYPE', (string) $type['code'], (string) $type['code_name']);
$assert($typeStatus['checked'] === true, '법정기준 종류 참조 검사를 완료하지 못했습니다.');
$assert(is_array($typeStatus['references']), '법정기준 종류 참조 결과 형식이 올바르지 않습니다.');

$systemRoutes = file_get_contents(PROJECT_ROOT . '/routes/api/system.php');
$settingsRoutes = file_get_contents(PROJECT_ROOT . '/routes/api/settings.php');
$controller = file_get_contents(PROJECT_ROOT . '/app/Controllers/Main/Settings/CodeController.php');
$columnRegistry = file_get_contents(PROJECT_ROOT . '/public/assets/js/common/column-meta/domains/code.js');
$modelSource = file_get_contents(PROJECT_ROOT . '/app/Models/System/CodeModel.php');
$assert(str_contains($systemRoutes, '/api/settings/system/code/references'), '참조내역 Route가 없습니다.');
$assert(!str_contains($settingsRoutes, '/api/settings/base-info/code/'), 'legacy code API Route가 남아 있습니다.');
$assert(!str_contains($controller, 'redirectBaseInfoApi'), 'legacy redirect 메서드가 남아 있습니다.');
$assert(!str_contains($columnRegistry, "key: 'deleted_at'"), '삭제일시 metadata가 남아 있습니다.');
$assert(!str_contains($columnRegistry, "key: 'deleted_by_name'"), '삭제자 metadata가 남아 있습니다.');
$assert(str_contains($modelSource, 'updated_by = :updated_by'), '정렬 Actor 갱신 SQL이 없습니다.');

echo "코드관리 정책 테스트 통과: {$passed}개 검증\n";
