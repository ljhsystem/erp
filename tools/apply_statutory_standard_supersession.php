<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

$mode = strtolower((string)($argv[1] ?? 'verify'));
if (!in_array($mode, ['preflight', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_statutory_standard_supersession.php [preflight|up|verify]');
}
$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$tableExists = static fn(PDO $connection): bool => (bool)$connection->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standard_supersessions'"
)->fetchColumn();
$snapshot = static function (PDO $connection, bool $useResolver): array {
    $scopes = $connection->query(
        "SELECT DISTINCT standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_data,additional_dimension_key"
        . " FROM system_statutory_standards ORDER BY standard_type_code,policy_component_code,employment_type_code,work_scope_code,additional_dimension_key"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $dates = $connection->query(
        "SELECT effective_from test_date FROM system_statutory_standards UNION SELECT effective_to FROM system_statutory_standards WHERE effective_to IS NOT NULL UNION SELECT CURRENT_DATE() ORDER BY test_date"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $result = [];
    $resolver = new StatutoryStandardResolver($connection);
    foreach ($scopes as $scope) {
        foreach ($dates as $date) {
            $params = [':type'=>$scope['standard_type_code'], ':date1'=>$date, ':date2'=>$date];
            $sql = 'SELECT id FROM system_statutory_standards WHERE standard_type_code=:type'
                . ' AND policy_component_code<=>:component AND employment_type_code<=>:employment'
                . ' AND work_scope_code<=>:scope AND additional_dimension_key<=>:dimension'
                . ' AND effective_from<=:date1 AND (effective_to IS NULL OR effective_to>=:date2) ORDER BY id';
            $params += [':component'=>$scope['policy_component_code'], ':employment'=>$scope['employment_type_code'], ':scope'=>$scope['work_scope_code'], ':dimension'=>$scope['additional_dimension_key']];
            $statement=$connection->prepare($sql);$statement->execute($params);
            $ids=$statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (count($ids) !== 1) continue;
            $key=implode('|', array_map(static fn($value): string => (string)($value ?? ''), [
                $scope['standard_type_code'],$scope['policy_component_code'],$scope['employment_type_code'],$scope['work_scope_code'],$scope['additional_dimension_key'],$date,
            ]));
            if (!$useResolver) {$result[$key]=(string)$ids[0];continue;}
            $dimensions=json_decode((string)($scope['additional_dimension_data'] ?? ''),true) ?: [];
            try {
                $resolved=$scope['policy_component_code'] === null
                    ? $resolver->resolve((string)$scope['standard_type_code'],(string)$date)
                    : $resolver->resolveComponent((string)$scope['standard_type_code'],(string)$scope['policy_component_code'],(string)$scope['employment_type_code'],(string)$scope['work_scope_code'],(string)$date,$dimensions);
            } catch (Throwable) {
                continue;
            }
            $result[$key]=(string)$resolved['id'];
        }
    }
    return $result;
};

$before = $tableExists($db) ? $snapshot($db, true) : $snapshot($db, false);
$countsBefore = [
    'revisions'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),
    'sources'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standard_sources')->fetchColumn(),
];
if ($mode === 'up') {
    if ($tableExists($db)) throw new RuntimeException('법정기준 Supersession 구조가 이미 존재합니다.');
    $sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260903_10_create_statutory_standard_supersessions.up.sql');
    $delimiter=';';$buffer='';
    foreach (preg_split('/\r\n|\n|\r/', $sql) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {$delimiter=$match[1];continue;}
        $buffer.=$line."\n";$trimmed=rtrim($buffer);
        if (!str_ends_with($trimmed,$delimiter)) continue;
        $statement=trim(substr($trimmed,0,-strlen($delimiter)));
        if ($statement!=='') $db->exec($statement);
        $buffer='';
    }
    if (trim($buffer)!=='') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
}
$existsAfter=$tableExists($db);
if ($mode !== 'preflight' && !$existsAfter) throw new RuntimeException('법정기준 Supersession 테이블이 없습니다.');
$after=$existsAfter ? $snapshot($db,true) : $before;
$countsAfter = [
    'revisions'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),
    'sources'=>(int)$db->query('SELECT COUNT(*) FROM system_statutory_standard_sources')->fetchColumn(),
];
if ($before !== $after || $countsBefore !== $countsAfter) {
    throw new RuntimeException('Migration 전후 기존 법정기준 Resolver 결과 또는 업무 데이터 건수가 변경됐습니다.');
}
echo json_encode(['success'=>true,'mode'=>$mode,'table_exists'=>$existsAfter,'resolver_cases'=>count($after),'counts'=>$countsAfter,'changed_results'=>0],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
