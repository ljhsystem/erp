<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Approval\PersonalExpenseClassificationProjectionService;
use Core\DbPdo;

$documentId = 'a31253c0-e0b4-45bf-bebc-6a4f5c7c53fa';
$rows = (new PersonalExpenseClassificationProjectionService(DbPdo::conn()))->forDocument($documentId);
if (count($rows) !== 9) {
    throw new RuntimeException('개인경비 상세 유효분류 Projection 건수가 9건이 아닙니다.');
}
foreach ($rows as $row) {
    if (($row['corrected_expense_category'] ?? null) !== null
        || ($row['classification_revision_no'] ?? null) !== null) {
        throw new RuntimeException('정정 테이블 미적용 상태에서 정정 Revision이 반환됐습니다.');
    }
    if (($row['effective_expense_category'] ?? null) !== ($row['original_expense_category'] ?? null)) {
        throw new RuntimeException('정정이 없을 때 승인 Item 원본분류가 유효분류로 반환되지 않았습니다.');
    }
}

echo "personal expense detail projection runtime: OK\n";
