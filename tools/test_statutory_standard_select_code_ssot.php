<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\System\StatutoryStandardTemplateService;

$db = Core\Database::getInstance()->getConnection();
$sourceSchema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$schema = 'tmp_statutory_code_ssot_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
if (!preg_match('/^tmp_statutory_code_ssot_[0-9]{14}_[a-f0-9]{6}$/', $schema)) {
    throw new RuntimeException('격리 Schema 이름이 올바르지 않습니다.');
}
$executeSql = static function (PDO $connection, string $path): void {
    $delimiter=';'; $buffer='';
    foreach (preg_split('/\r\n|\n|\r/', (string)file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter=$match[1]; continue; }
        $buffer.=$line."\n"; $trimmed=rtrim($buffer);
        if (!str_ends_with($trimmed,$delimiter)) continue;
        $statement=trim(substr($trimmed,0,-strlen($delimiter)));
        if ($statement!=='') $connection->exec($statement);
        $buffer='';
    }
    if (trim($buffer)!=='') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$hash = static fn(PDO $connection, string $sql): string => hash('sha256', json_encode(
    $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [],
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION
));
$jsonHash = static function (PDO $connection, string $sql, string $column): string {
    $normalize = static function (mixed $value) use (&$normalize): mixed {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map($normalize,$value);
        ksort($value,SORT_STRING);
        foreach ($value as $key=>$item) $value[$key]=$normalize($item);
        return $value;
    };
    $rows=$connection->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[];
    foreach ($rows as &$row) $row[$column]=$normalize(json_decode((string)$row[$column],true,512,JSON_THROW_ON_ERROR));
    unset($row);
    return hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
};
$created=false;
try {
    $db->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"); $created=true;
    $db->exec("CREATE TABLE `{$schema}`.`_codex_execution_marker`(id TINYINT PRIMARY KEY,owner_code VARCHAR(100) NOT NULL)");
    $db->exec("INSERT INTO `{$schema}`.`_codex_execution_marker` VALUES(1,'STATUTORY_SELECT_CODE_SSOT_20260831')");
    foreach (['system_codes','system_statutory_standards','system_statutory_standard_sources','institution_daily_employment_income_calculation_results'] as $table) {
        $db->exec("CREATE TABLE `{$schema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        $db->exec("INSERT INTO `{$schema}`.`{$table}` SELECT * FROM `{$sourceSchema}`.`{$table}`");
    }
    $db->exec("USE `{$schema}`");
    $templateSql="SELECT id,extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT') ORDER BY id";
    $revisionSql="SELECT id,value_data FROM system_statutory_standards WHERE policy_component_code='ELIGIBILITY' ORDER BY id";
    $sourceSql="SELECT * FROM system_statutory_standard_sources ORDER BY id";
    $resultSql="SELECT * FROM institution_daily_employment_income_calculation_results ORDER BY id";
    $beforeTemplateRows=$db->query($templateSql)->fetchAll(PDO::FETCH_ASSOC)?:[];
    $before=['template'=>$jsonHash($db,$templateSql,'extra_data'),'revision'=>$hash($db,$revisionSql),'source'=>$hash($db,$sourceSql),'result'=>$hash($db,$resultSql)];
    $executeSql($db,PROJECT_ROOT.'/app/migrations/20260831_06_unify_statutory_standard_select_codes.up.sql');
    $groupCount=(int)$db->query("SELECT COUNT(DISTINCT code_group) FROM system_codes WHERE id LIKE '20260831-06%'")->fetchColumn();
    $codeCount=(int)$db->query("SELECT COUNT(*) FROM system_codes WHERE id LIKE '20260831-06%'")->fetchColumn();
    $duplicates=(int)$db->query("SELECT COUNT(*) FROM (SELECT code_group,code,COUNT(*) c FROM system_codes GROUP BY code_group,code HAVING c>1) duplicated")->fetchColumn();
    if ($groupCount!==15 || $codeCount!==34 || $duplicates!==0) throw new RuntimeException('코드그룹 또는 코드 건수가 다릅니다.');
    $templates=(new StatutoryStandardTemplateService($db))->all();
    $systemCodeFieldKeys=[];
    foreach ($templates as $template) foreach ((array)($template['component_templates']??[]) as $component) {
        if (($component['policy_component_code']??null)!=='ELIGIBILITY') continue;
        foreach ((array)($component['fields']??[]) as $field) {
            if (($field['type']??null)==='select') {
                if (($field['option_source']??null)!=='SYSTEM_CODES') throw new RuntimeException('가입자격 Select가 SYSTEM_CODES를 사용하지 않습니다.');
                $systemCodeFieldKeys[(string)$template['code'].'|'.(string)$field['code']]=true;
            }
        }
    }
    $systemCodeFields=count($systemCodeFieldKeys);
    if ($systemCodeFields!==85) throw new RuntimeException('보험 5종 가입자격 SYSTEM_CODES 필드 수가 다릅니다.');
    $embeddedOptionFields=0;
    foreach ($db->query($templateSql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $templateRow) {
        $templateData=json_decode((string)$templateRow['extra_data'],true,512,JSON_THROW_ON_ERROR);
        foreach ((array)($templateData['field_sets']['eligibility']??[]) as $field) {
            if (($field['type']??null)==='select' && array_key_exists('options',$field)) $embeddedOptionFields++;
        }
    }
    if ($embeddedOptionFields!==0) throw new RuntimeException('가입자격 템플릿에 내장 options가 남아 있습니다.');
    $templateService=new StatutoryStandardTemplateService($db);
    $unsupportedCombinationBlocked=false;
    try {
        $templateService->find('NATIONAL_PENSION','ELIGIBILITY','REGULAR','CONSTRUCTION_SITE');
    } catch (InvalidArgumentException) {
        $unsupportedCombinationBlocked=true;
    }
    if (!$unsupportedCombinationBlocked) throw new RuntimeException('미지원 Header 조합이 서버에서 차단되지 않았습니다.');
    $decisionField=$templateService->find('NATIONAL_PENSION','ELIGIBILITY','DAILY','HEAD_OFFICE')['fields'][1];
    $db->exec("UPDATE system_codes SET is_active=0 WHERE code_group='INSURANCE_ELIGIBILITY_DECISION' AND code='DEPENDENT_RESULT'");
    $inactiveField=(new StatutoryStandardTemplateService($db))->find('NATIONAL_PENSION','ELIGIBILITY','DAILY','HEAD_OFFICE')['fields'][1];
    if (!$templateService->isActiveSelectValue($decisionField,'RULE_EVALUATION')
        || (new StatutoryStandardTemplateService($db))->isActiveSelectValue($inactiveField,'DEPENDENT_RESULT')
        || count(array_filter($inactiveField['options'],static fn(array $row):bool=>($row['value']??'')==='DEPENDENT_RESULT'&&!empty($row['disabled'])))!==1) {
        throw new RuntimeException('활성·비활성 코드 계약이 다릅니다.');
    }
    $db->exec("UPDATE system_codes SET is_active=1 WHERE code_group='INSURANCE_ELIGIBILITY_DECISION' AND code='DEPENDENT_RESULT'");
    $after=['revision'=>$hash($db,$revisionSql),'source'=>$hash($db,$sourceSql),'result'=>$hash($db,$resultSql)];
    if ($before['revision']!==$after['revision']||$before['source']!==$after['source']||$before['result']!==$after['result']) throw new RuntimeException('업무자료가 변경됐습니다.');
    $executeSql($db,PROJECT_ROOT.'/app/migrations/20260831_06_unify_statutory_standard_select_codes.down.sql');
    $down=['template'=>$jsonHash($db,$templateSql,'extra_data'),'revision'=>$hash($db,$revisionSql),'source'=>$hash($db,$sourceSql),'result'=>$hash($db,$resultSql)];
    if ($before!==$down) {
        $afterTemplateRows=$db->query($templateSql)->fetchAll(PDO::FETCH_ASSOC)?:[];
        $differences=[];
        foreach ($beforeTemplateRows as $index=>$row) {
            $beforeJson=json_decode((string)$row['extra_data'],true);
            $afterJson=json_decode((string)($afterTemplateRows[$index]['extra_data']??''),true);
            if ($beforeJson!==$afterJson) $differences[$row['id']]=['before_eligibility'=>$beforeJson['field_sets']['eligibility']??null,'after_eligibility'=>$afterJson['field_sets']['eligibility']??null];
        }
        throw new RuntimeException('Down 후 기준선이 복원되지 않았습니다: '.json_encode(['hashes'=>['before'=>$before,'down'=>$down],'differences'=>$differences],JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['success'=>true,'schema'=>$schema,'mariadb'=>$db->query('SELECT VERSION()')->fetchColumn(),'groups'=>$groupCount,'codes'=>$codeCount,'system_code_select_fields'=>$systemCodeFields,'embedded_option_fields'=>$embeddedOptionFields,'unsupported_combination_blocked'=>$unsupportedCombinationBlocked,'inactive_existing_displayed'=>true,'inactive_new_save_blocked'=>true,'before'=>$before,'after_up'=>$after,'down_restored'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    $db->exec("USE `{$sourceSchema}`");
    if ($created) {
        $marker=(int)$db->query("SELECT COUNT(*) FROM `{$schema}`.`_codex_execution_marker` WHERE owner_code='STATUTORY_SELECT_CODE_SSOT_20260831'")->fetchColumn();
        if ($marker!==1) throw new RuntimeException('격리 Schema Marker가 없습니다.');
        $db->exec("DROP DATABASE `{$schema}`");
    }
}
