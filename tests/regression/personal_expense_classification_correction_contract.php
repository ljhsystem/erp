<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'migration' => file_get_contents($root . '/app/migrations/20260824_13_create_personal_expense_classification_corrections.up.sql'),
    'model' => file_get_contents($root . '/app/Models/Approval/PersonalExpenseClassificationCorrectionModel.php'),
    'service' => file_get_contents($root . '/app/Services/Approval/PersonalExpenseClassificationCorrectionService.php'),
    'route' => file_get_contents($root . '/routes/api/approval.php'),
];
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$assert(str_contains($files['migration'], 'UNIQUE KEY `uk_personal_expense_classification_item_revision`'), 'Item Revision UNIQUE가 없습니다.');
$assert(str_contains($files['migration'], 'UNIQUE KEY `uk_personal_expense_classification_request`'), '요청 멱등키 UNIQUE가 없습니다.');
$assert(!preg_match('/`(?:updated_at|updated_by|deleted_at|deleted_by|is_active)`/', $files['migration']), '불변 정정 테이블에 변경·삭제 컬럼이 있습니다.');
$assert(str_contains($files['service'], 'FOR UPDATE') === false, 'Service에 SQL이 직접 포함되어 있습니다.');
$assert(str_contains($files['model'], 'FOR UPDATE'), '문서·Item 잠금이 없습니다.');
$assert(str_contains($files['service'], 'ActorHelper::user()'), '처리 Actor가 ActorHelper를 사용하지 않습니다.');
$assert(str_contains($files['model'], 'COALESCE({$correctedColumn},item.expense_category)'), '유효분류 Projection 우선순위가 없습니다.');
$assert(str_contains($files['model'], '$hasCorrectionTable = $this->correctionTableExists();'), '정정 테이블 미적용 환경의 상세조회 Guard가 없습니다.');
$assert(str_contains($files['model'], "? 'correction.corrected_category' : 'NULL'"), '정정 테이블이 없을 때 승인 Item 원본분류로 복귀하는 Projection이 없습니다.');
$assert(str_contains($files['route'], '/api/approval/personal-expense/correct-classification'), '공식 정정 Route가 없습니다.');
if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "개인경비 회계분류 정정 계약 검증 통과\n";
