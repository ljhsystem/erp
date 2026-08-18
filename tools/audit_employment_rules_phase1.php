<?php
declare(strict_types=1);

use App\Services\Institution\EmploymentRulePolicyService;
use App\Services\Institution\EmploymentRuleService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$service = new EmploymentRuleService($pdo);
$options = $service->options()['data'];
$list = $service->list(['draw'=>1,'start'=>0,'length'=>10,'filters'=>'[]','search'=>['value'=>'']]);
$resolver = new EmploymentRulePolicyService($pdo);
$companyId = (string) ($options['companies'][0]['value'] ?? '');
$resolved = $companyId === '' ? null : $resolver->resolve($companyId, 'WORK_START_TIME', date('Y-m-d'));
$constraints = $pdo->query("SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'institution_employment_rules%' ORDER BY TABLE_NAME,CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC);
$indexes = $pdo->query("SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_used FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'institution_employment_rules%' GROUP BY TABLE_NAME,INDEX_NAME ORDER BY TABLE_NAME,INDEX_NAME")->fetchAll(PDO::FETCH_ASSOC);
$employmentCodeGroups = $pdo->query("SELECT code_group,COUNT(*) row_count FROM system_codes WHERE code_group LIKE 'EMPLOYMENT_%' AND is_active=1 GROUP BY code_group ORDER BY code_group")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['options'=>array_map('count',$options),'list_count'=>count($list['data']),'resolver_result'=>$resolved,'employment_code_groups'=>$employmentCodeGroups,'constraints'=>$constraints,'indexes'=>$indexes], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), "\n";
