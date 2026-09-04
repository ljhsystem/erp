<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\System\StatutoryStandardModel;
use App\Services\Institution\BusinessIncomeEvidenceCanonicalPolicy;

$policy = new BusinessIncomeEvidenceCanonicalPolicy();
$canonical = ['source_type'=>'INTERNAL_APPROVAL','import_type'=>'BUSINESS_INCOME_REPORT','transaction_direction'=>'OUT','operation_type'=>'BUSINESS_INCOME','employee_id'=>null];
$policy->assert($canonical);
$blocked = false;
try { $policy->assert(array_merge($canonical, ['source_type'=>'EXTERNAL'])); } catch (DomainException) { $blocked = true; }
if (!$blocked) throw new RuntimeException('잘못된 Evidence canonical 요청이 차단되지 않았습니다.');
$reflection = new ReflectionClass(StatutoryStandardModel::class);
foreach (['update','delete','reorder'] as $method) {
    $source = file(PROJECT_ROOT . '/app/Models/System/StatutoryStandardModel.php') ?: [];
    $text = implode('', array_slice($source, $reflection->getMethod($method)->getStartLine()-1, 8));
    if (!str_contains($text, 'throw new \\LogicException')) throw new RuntimeException("{$method} 불변성 차단이 없습니다.");
}
$migration = file_get_contents(PROJECT_ROOT . '/app/migrations/20260903_13_remove_statutory_and_business_income_triggers.up.sql');
if (substr_count((string)$migration, 'DROP TRIGGER IF EXISTS') !== 10) throw new RuntimeException('명시적 Trigger DROP 수가 10이 아닙니다.');
echo json_encode(['success'=>true,'canonical_normal'=>'PASS','canonical_invalid'=>'BLOCKED','revision_mutation'=>'BLOCKED','drop_count'=>10], JSON_UNESCAPED_UNICODE), PHP_EOL;
