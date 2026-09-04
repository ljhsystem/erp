<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$options = getopt('', ['schema:', 'no-create-schema', 'no-drop-schema', 'bootstrap-fixtures', 'reset-fixtures', 'upgrade-manifest']);
$schema = trim((string) ($options['schema'] ?? ''));
$bootstrapFixtures = array_key_exists('bootstrap-fixtures', $options);
$resetFixtures = array_key_exists('reset-fixtures', $options);
$upgradeManifest = array_key_exists('upgrade-manifest', $options);
if ($schema === '' || preg_match('/^[A-Za-z0-9_]+$/', $schema) !== 1) {
    throw new RuntimeException('--schema=<test_schema>를 지정해 주세요.');
}
if (!str_starts_with(strtolower($schema), 'tmp_')) {
    throw new RuntimeException('테스트 Schema는 tmp_ 접두사로 시작해야 합니다.');
}
if ($schema !== 'tmp_erp_daily_evidence_p1_test') {
    throw new RuntimeException('이 도구는 승인된 격리 Schema에서만 실행할 수 있습니다.');
}
if (!array_key_exists('no-create-schema', $options) || !array_key_exists('no-drop-schema', $options)) {
    throw new RuntimeException('--no-create-schema와 --no-drop-schema가 모두 필요합니다.');
}
if ($resetFixtures && !$bootstrapFixtures) {
    throw new RuntimeException('--reset-fixtures는 --bootstrap-fixtures와 함께 사용해야 합니다.');
}

$pdo = DbPdo::conn();
$sourceSchema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (strcasecmp($schema, $sourceSchema) === 0) {
    throw new RuntimeException('운영 Schema에서는 격리 Migration 검증을 실행할 수 없습니다.');
}
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:schema');
$exists->execute([':schema' => $schema]);
if ((int) $exists->fetchColumn() !== 1) {
    throw new RuntimeException('사용자가 준비한 테스트 Schema를 찾을 수 없습니다. CREATE DATABASE는 실행하지 않았습니다.');
}

$execute = static function (PDO $connection, string $file) use ($schema): void {
    if ((string) $connection->query('SELECT DATABASE()')->fetchColumn() !== $schema) {
        throw new RuntimeException('테스트 Schema 세션이 변경되어 실행을 차단했습니다.');
    }
    $delimiter = ';'; $buffer = '';
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file);
    foreach (preg_split('/\r\n|\n|\r/', $sql) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n"; $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') {
            if ((string) $connection->query('SELECT DATABASE()')->fetchColumn() !== $schema) {
                throw new RuntimeException('테스트 Schema 세션이 변경되어 실행을 차단했습니다.');
            }
            $connection->exec($statement);
        }
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException($file . ' SQL 구문이 완결되지 않았습니다.');
};

$requiredTables = [
    'ledger_evidence_daily_employment_income','institution_daily_employment_income_groups',
    'institution_daily_employment_income_lines','institution_daily_employment_income_calculation_revisions',
    'system_codes','system_user_settings','system_statutory_standards',
    'institution_daily_worker_social_insurance_coverages','institution_social_insurance_workplaces',
];
$pdo->exec('USE `' . $schema . '`');

$fixtureSuite = 'DAILY_EVIDENCE_NORMALIZATION_P1';
$fixtureVersion = '20260901.1';
$manifestTable = 'tmp_test_fixture_manifest';
$ownedTables = array_merge(['ledger_evidence_daily_employment_income_lines'], $requiredTables, [$manifestTable]);
$baselineHash = hash('sha256', $fixtureSuite . '|' . $fixtureVersion . '|' . implode('|', $ownedTables));
$tableExists = static function (PDO $connection, string $table) use ($schema): bool {
    $statement = $connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table');
    $statement->execute([':schema' => $schema, ':table' => $table]);
    return (int) $statement->fetchColumn() === 1;
};
$assertTarget = static function () use ($pdo, $schema): void {
    if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $schema) {
        throw new RuntimeException('테스트 Schema 세션이 변경되어 작업을 차단했습니다.');
    }
};

if ($upgradeManifest) {
    $assertTarget();
    $actualTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    if (!$tableExists($pdo, $manifestTable)) {
        throw new RuntimeException('전환할 기존 Fixture Manifest가 없습니다.');
    }
    $legacyColumns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tmp_test_fixture_manifest' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
    $expectedLegacyColumns = ['fixture_suite_code','fixture_version','schema_name','created_at','created_by','baseline_hash','owned_tables_json'];
    if ($legacyColumns !== $expectedLegacyColumns) {
        throw new RuntimeException('TEST_SCHEMA_STRUCTURE_MISMATCH: 기존 Manifest 구조가 전환 기준과 다릅니다.');
    }
    $legacyRows = $pdo->query('SELECT * FROM tmp_test_fixture_manifest')->fetchAll(PDO::FETCH_ASSOC);
    if (count($legacyRows) !== 1) {
        throw new RuntimeException('기존 Manifest 소유권 행을 단일 기준선으로 확정할 수 없습니다.');
    }
    $legacy = $legacyRows[0];
    $decodedOwned = json_decode((string) $legacy['owned_tables_json'], true);
    if (!is_array($decodedOwned) || $decodedOwned === []) {
        throw new RuntimeException('기존 Manifest owned_tables_json이 유효하지 않습니다.');
    }
    $decodedOwned = array_values(array_unique(array_map('strval', $decodedOwned)));
    sort($decodedOwned);
    $actualSorted = array_values(array_map('strval', $actualTables));
    sort($actualSorted);
    if ($decodedOwned !== $actualSorted) {
        throw new RuntimeException('기존 Manifest에 없는 테이블 또는 누락된 소유 테이블이 있습니다.');
    }
    $baselineCounts = [
        'headers' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income')->fetchColumn(),
        'calculation_lines' => (int) $pdo->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn(),
        'raw_lines' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines')->fetchColumn(),
    ];
    if ($baselineCounts !== ['headers'=>1,'calculation_lines'=>35,'raw_lines'=>35]) {
        throw new RuntimeException('기존 Migration Fixture 기준선이 훼손됐습니다.');
    }
    $transitionTable = 'tmp_test_fixture_manifest_v2';
    $legacyTable = 'tmp_test_fixture_manifest_legacy';
    if ($tableExists($pdo, $transitionTable) || $tableExists($pdo, $legacyTable)) {
        throw new RuntimeException('이전 Manifest 전환 부산물이 남아 있습니다.');
    }
    $pdo->exec("CREATE TABLE tmp_test_fixture_manifest_v2 (
      id CHAR(36) NOT NULL,
      suite_code VARCHAR(100) NOT NULL,
      fixture_key VARCHAR(150) NOT NULL,
      object_type VARCHAR(30) NOT NULL,
      object_name VARCHAR(150) NOT NULL,
      row_id VARCHAR(100) NULL,
      schema_name VARCHAR(100) NOT NULL,
      fixture_version VARCHAR(30) NOT NULL,
      tool_version VARCHAR(30) NOT NULL,
      baseline_hash CHAR(64) NOT NULL,
      ownership_key CHAR(64) NOT NULL,
      created_at DATETIME NOT NULL,
      created_by VARCHAR(100) NOT NULL,
      PRIMARY KEY(id),
      UNIQUE KEY uq_tmp_fixture_ownership(ownership_key),
      KEY idx_tmp_fixture_suite(suite_code,fixture_key,object_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $canonicalSuite = 'DAILY_EVIDENCE_MIGRATION_P1';
    $fixtureKey = 'migration-baseline-2099-01';
    $toolVersion = '20260901.2';
    $insertOwnership = $pdo->prepare('INSERT INTO tmp_test_fixture_manifest_v2 (id,suite_code,fixture_key,object_type,object_name,row_id,schema_name,fixture_version,tool_version,baseline_hash,ownership_key,created_at,created_by) VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($decodedOwned as $table) {
        $ownershipKey = hash('sha256', implode('|', [$canonicalSuite,$fixtureKey,'TABLE',$table,'']));
        $insertOwnership->execute([$canonicalSuite,$fixtureKey,'TABLE',$table,null,$schema,(string)$legacy['fixture_version'],$toolVersion,(string)$legacy['baseline_hash'],$ownershipKey,(string)$legacy['created_at'],(string)$legacy['created_by']]);
    }
    $fixtureSetName = 'daily-evidence-migration-baseline';
    $fixtureSetKey = hash('sha256', implode('|', [$canonicalSuite,$fixtureKey,'FIXTURE_SET',$fixtureSetName,'']));
    $insertOwnership->execute([$canonicalSuite,$fixtureKey,'FIXTURE_SET',$fixtureSetName,null,$schema,(string)$legacy['fixture_version'],$toolVersion,(string)$legacy['baseline_hash'],$fixtureSetKey,(string)$legacy['created_at'],(string)$legacy['created_by']]);
    $convertedTables = $pdo->query("SELECT object_name FROM tmp_test_fixture_manifest_v2 WHERE suite_code='DAILY_EVIDENCE_MIGRATION_P1' AND object_type='TABLE' ORDER BY object_name")->fetchAll(PDO::FETCH_COLUMN);
    if ($convertedTables !== $decodedOwned || (int)$pdo->query('SELECT COUNT(*) FROM tmp_test_fixture_manifest_v2 GROUP BY ownership_key HAVING COUNT(*)>1')->fetchColumn() > 0) {
        $pdo->exec('DROP TABLE tmp_test_fixture_manifest_v2');
        throw new RuntimeException('Manifest 전환 대사에서 누락 또는 중복이 발견됐습니다.');
    }
    $pdo->exec('RENAME TABLE tmp_test_fixture_manifest TO tmp_test_fixture_manifest_legacy, tmp_test_fixture_manifest_v2 TO tmp_test_fixture_manifest');
    $finalColumns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tmp_test_fixture_manifest' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['suite_code','fixture_key','object_type','object_name','row_id','schema_name','fixture_version','tool_version','baseline_hash','ownership_key'] as $column) {
        if (!in_array($column, $finalColumns, true)) {
            $pdo->exec('RENAME TABLE tmp_test_fixture_manifest TO tmp_test_fixture_manifest_v2, tmp_test_fixture_manifest_legacy TO tmp_test_fixture_manifest');
            throw new RuntimeException('Manifest 전환 후 최종 구조검증에 실패했습니다.');
        }
    }
    $pdo->exec('DROP TABLE tmp_test_fixture_manifest_legacy');
    echo json_encode(['success'=>true,'mode'=>'manifest_upgrade','schema'=>$schema,'suite_code'=>$canonicalSuite,'table_ownership_rows'=>count($decodedOwned),'fixture_set_rows'=>1,'missing'=>0,'duplicates'=>0,'baseline_counts'=>$baselineCounts,'transition_tables_remaining'=>0,'operating_ddl'=>0,'operating_dml'=>0], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}

if ($resetFixtures) {
    if (!$tableExists($pdo, $manifestTable)) {
        throw new RuntimeException('Fixture Manifest가 없어 reset을 차단했습니다.');
    }
    $manifest = $pdo->query('SELECT fixture_suite_code,fixture_version,schema_name,baseline_hash,owned_tables_json FROM tmp_test_fixture_manifest LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($manifest) || $manifest['fixture_suite_code'] !== $fixtureSuite
        || $manifest['fixture_version'] !== $fixtureVersion || $manifest['schema_name'] !== $schema
        || $manifest['baseline_hash'] !== $baselineHash
        || json_decode((string) $manifest['owned_tables_json'], true) !== $ownedTables) {
        throw new RuntimeException('Fixture Manifest 소유권이 일치하지 않아 reset을 차단했습니다.');
    }
    $actual = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($actual, $ownedTables) !== []) {
        throw new RuntimeException('다른 Suite 또는 사용자 소유 테이블이 있어 reset을 차단했습니다.');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($ownedTables as $table) {
        $assertTarget();
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

if ($bootstrapFixtures) {
    $existing = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    if ($existing !== []) {
        throw new RuntimeException('테스트 Schema가 비어 있지 않습니다. 재구성에는 유효한 Manifest와 --reset-fixtures가 필요합니다.');
    }
    $assertTarget();
    $pdo->exec("CREATE TABLE tmp_test_fixture_manifest (fixture_suite_code VARCHAR(100) PRIMARY KEY,fixture_version VARCHAR(30) NOT NULL,schema_name VARCHAR(100) NOT NULL,created_at DATETIME NOT NULL,created_by VARCHAR(100) NOT NULL,baseline_hash CHAR(64) NOT NULL,owned_tables_json LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ($requiredTables as $table) {
        $assertTarget();
        $pdo->exec('CREATE TABLE `' . $table . '` LIKE `' . str_replace('`', '``', $sourceSchema) . '`.`' . $table . '`');
    }
    $newColumnCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND COLUMN_NAME IN ('business_unit','raw_income_year_month','raw_gross_payment_amount','evidence_status')")->fetchColumn();
    if ($newColumnCount !== 0 || $tableExists($pdo, 'ledger_evidence_daily_employment_income_lines')) {
        throw new RuntimeException('Bootstrap 기준선이 구 스키마 계약과 일치하지 않습니다.');
    }

    $actor = 'SYSTEM:DAILY_EVIDENCE_P1_FIXTURE';
    $at = '2099-02-10 12:00:00';
    $id = static fn(int $number): string => sprintf('10000000-0000-4000-8000-%012d', $number);
    $sourceHash = str_repeat('a', 64);
    $pdo->prepare('INSERT INTO tmp_test_fixture_manifest VALUES (?,?,?,?,?,?,?)')->execute([$fixtureSuite,$fixtureVersion,$schema,$at,$actor,$baselineHash,json_encode($ownedTables)]);
    $pdo->prepare('INSERT INTO system_codes (id,sort_no,code_group,group_name,code,code_name,is_active,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$id(11),1,'BUSINESS_UNIT','사업구분','HQ','본사',1,$actor,$actor]);
    $pdo->prepare('INSERT INTO system_user_settings (id,user_id,page_key,setting_type,settings_json,created_by,updated_by) VALUES (?,?,?,?,?,?,?)')->execute([$id(12),$id(6),'evidence-daily-employment-income','TABLE',json_encode(['visibleColumns'=>['income_year_month','payment_date','total_work_days','total_gross_amount','total_deduction_amount','total_net_payment_amount','total_employer_burden_amount','evidence_status_code']]),$id(6),$id(6)]);
    $pdo->prepare('INSERT INTO system_statutory_standards (id,sort_no,standard_type_code,policy_component_code,employment_type_code,work_scope_code,effective_from,value_data,note,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id(8),1,'TEST_STANDARD','PREMIUM','DAILY','HEAD_OFFICE','2099-01-01','{}','비식별 테스트 기준',$at,$actor,$at,$actor]);
    $pdo->prepare('INSERT INTO institution_social_insurance_workplaces (id,company_id,business_unit,work_scope_code,project_id,workplace_name,effective_from,evidence_type_code,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id(9),'TEST_COMPANY','HQ','HEAD_OFFICE',null,'테스트 사업장','2099-01-01','TEST',$at,$actor,$at,$actor]);
    $pdo->prepare('INSERT INTO institution_daily_worker_social_insurance_coverages (id,company_id,worker_client_id,social_insurance_workplace_id,insurance_type_code,application_status_code,effective_from,evidence_type_code,confirmed_by,confirmed_at,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id(10),'TEST_COMPANY',$id(6),$id(9),'EMPLOYMENT','APPLICABLE','2099-01-01','TEST',$actor,$at,$at,$actor,$at,$actor]);
    $pdo->prepare('INSERT INTO institution_daily_employment_income_groups (id,daily_employment_income_id,sort_no,business_unit,project_id,work_team_id,work_description,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$id(2),$id(1),1,'HQ',null,null,'비식별 Fixture',$at,$actor,$at,$actor]);
    $pdo->prepare('INSERT INTO institution_daily_employment_income_calculation_revisions (id,daily_employment_income_id,revision_no,calculation_policy_version,source_hash,status_code,calculated_by,calculated_at,confirmed_by,confirmed_at,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id(4),$id(1),1,'FIXTURE_V1',$sourceHash,'CONFIRMED',$actor,$at,$actor,$at,$at,$actor,$at,$actor]);
    $pdo->prepare('INSERT INTO ledger_evidence_daily_employment_income (id,source_daily_employment_income_id,daily_employment_income_item_id,daily_employment_income_group_id,approval_request_id,calculation_revision_id,source_hash,worker_client_id,work_scope_code,project_id,work_team_id,income_year_month,payment_date,total_work_days,total_gross_amount,total_deduction_amount,total_net_payment_amount,total_employer_burden_amount,snapshot_json,business_key_hash,evidence_status_code,approved_by,approved_at,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id(5),$id(1),$id(3),$id(2),$id(7),$id(4),$sourceHash,$id(6),'HEAD_OFFICE',null,null,'2099-01','2099-02-10',5,452940,2940,450000,20820,'{"fixture":true}',str_repeat('b',64),'CORRECTION_REQUIRED',$actor,$at,$at,$actor,$at,$actor]);

    $lineSql = 'INSERT INTO institution_daily_employment_income_lines (id,sort_no,daily_employment_income_item_id,daily_employment_income_workday_id,workday_scope_key,revision_scope_key,period_scope_key,line_type_code,taxability_code,line_code,line_name_snapshot,application_status_code,calculation_basis_amount,calculation_rate,calculation_before_rounding,calculated_amount,rounding_method_code,rounding_unit,statutory_standard_id,effective_from,effective_to,coverage_id,social_insurance_workplace_id,final_amount,created_at,created_by,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $lineInsert = $pdo->prepare($lineSql);
    for ($number=1; $number<=35; $number++) {
        $type = $number===1 ? 'PAY' : ($number<=3 ? 'DEDUCTION' : ($number===4 ? 'EMPLOYER_BURDEN' : ($number%3===0 ? 'DEDUCTION' : 'PAY')));
        $status = $number<=4 ? 'APPLICABLE' : ($number%2===0 ? 'APPLICABLE' : 'EXCLUDED');
        $amount = match ($number) { 1=>452940,2=>2000,3=>940,4=>20820,default=>0 };
        $taxability = $type === 'PAY' ? 'TAXABLE' : null;
        $lineInsert->execute([sprintf('20000000-0000-4000-8000-%012d',$number),$number,$id(3),null,'ITEM',$id(4),'2099-01',$type,$taxability,'LINE_'.$number,'비식별 계산항목 '.$number,$status,452940,0,$amount,$amount,'ROUND',1,in_array($number,[2,4],true)?$id(8):null,null,null,$number===2?$id(10):null,in_array($number,[2,4],true)?$id(9):null,$amount,$at,$actor,$at,$actor]);
    }
}

foreach ($requiredTables as $table) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table');
    $statement->execute([':schema' => $schema, ':table' => $table]);
    if ((int) $statement->fetchColumn() !== 1) throw new RuntimeException('테스트 Schema 필수 테이블 누락: ' . $table);
}

$files = [
    '20260901_01_normalize_daily_employment_income_evidence.up.sql',
    '20260901_02_backfill_daily_employment_income_evidence_raw.up.sql',
    '20260901_03_add_daily_employment_income_evidence_raw_checks.up.sql',
    '20260901_04_migrate_daily_evidence_table_settings_keys.up.sql',
    '20260901_05_create_daily_employment_income_evidence_raw_lines.up.sql',
    '20260901_06_backfill_daily_employment_income_evidence_raw_lines.up.sql',
];
foreach ($files as $file) $execute($pdo, $file);

$checks = [];
$checks['header_backfill'] = (int) $pdo->query("SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE
 business_unit IS NULL OR transaction_direction<>'EXPENSE' OR operation_type<>'DAILY_WORKER'
 OR raw_income_year_month<>income_year_month OR raw_payment_date<>payment_date
 OR raw_work_day_count<>total_work_days OR raw_gross_payment_amount<>total_gross_amount
 OR raw_worker_deduction_amount<>total_deduction_amount OR raw_net_payment_amount<>total_net_payment_amount
 OR raw_employer_burden_amount<>total_employer_burden_amount OR evidence_status<>evidence_status_code")->fetchColumn() === 0;
$checks['signed_formula'] = (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income WHERE ROUND(raw_gross_payment_amount-raw_worker_deduction_amount,2)<>ROUND(raw_net_payment_amount,2)')->fetchColumn() === 0;
$checks['raw_line_count'] = (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines')->fetchColumn()
    === (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income e JOIN institution_daily_employment_income_lines l ON l.daily_employment_income_item_id=e.daily_employment_income_item_id')->fetchColumn();
$checks['raw_line_exactly_35'] = (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines')->fetchColumn() === 35;
$checks['raw_line_duplicate_0'] = (int) $pdo->query('SELECT COUNT(*) FROM (SELECT evidence_id,source_calculation_line_id,COUNT(*) AS row_count FROM ledger_evidence_daily_employment_income_lines GROUP BY evidence_id,source_calculation_line_id HAVING row_count<>1) duplicated')->fetchColumn() === 0;
$checks['zero_and_excluded_preserved'] = (int) $pdo->query("SELECT COUNT(*) FROM institution_daily_employment_income_lines source JOIN ledger_evidence_daily_employment_income e ON e.daily_employment_income_item_id=source.daily_employment_income_item_id WHERE (source.final_amount=0 OR source.application_status_code='EXCLUDED') AND NOT EXISTS (SELECT 1 FROM ledger_evidence_daily_employment_income_lines raw_line WHERE raw_line.evidence_id=e.id AND raw_line.source_calculation_line_id=source.id)")->fetchColumn() === 0;
$checks['employer_burden_preserved'] = (int) $pdo->query("SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='EMPLOYER_BURDEN' AND burden_subject_code<>'EMPLOYER'")->fetchColumn() === 0;
$checks['pay_total_452940'] = (float) $pdo->query("SELECT COALESCE(SUM(raw_final_amount),0) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='PAY' AND application_status_code='APPLICABLE'")->fetchColumn() === 452940.0;
$checks['deduction_total_2940'] = (float) $pdo->query("SELECT COALESCE(SUM(raw_final_amount),0) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='DEDUCTION' AND application_status_code='APPLICABLE'")->fetchColumn() === 2940.0;
$checks['employer_burden_total_20820'] = (float) $pdo->query("SELECT COALESCE(SUM(raw_final_amount),0) FROM ledger_evidence_daily_employment_income_lines WHERE line_type_code='EMPLOYER_BURDEN' AND application_status_code='APPLICABLE'")->fetchColumn() === 20820.0;
$checks['reference_projection'] = (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines WHERE statutory_standard_id IS NOT NULL OR coverage_id IS NOT NULL OR social_insurance_workplace_id IS NOT NULL')->fetchColumn() >= 2;
$checks['line_order_preserved'] = (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines raw_line JOIN institution_daily_employment_income_lines source ON source.id=raw_line.source_calculation_line_id WHERE raw_line.sort_no<>source.sort_no')->fetchColumn() === 0;
$checks['revision_preserved'] = (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income_lines raw_line JOIN ledger_evidence_daily_employment_income evidence ON evidence.id=raw_line.evidence_id WHERE raw_line.calculation_revision_id<>evidence.calculation_revision_id')->fetchColumn() === 0;
$checks['table_settings_migrated'] = (int) $pdo->query("SELECT COUNT(*) FROM system_user_settings WHERE page_key='evidence-daily-employment-income' AND (settings_json LIKE '%total_gross_amount%' OR settings_json LIKE '%evidence_status_code%')")->fetchColumn() === 0;
$checks['header_checks_5'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND CONSTRAINT_NAME IN ('ck_daily_evidence_raw_non_negative','ck_daily_evidence_raw_amounts','ck_daily_evidence_raw_period','ck_daily_evidence_business_classification','ck_daily_evidence_review_status')")->fetchColumn() === 5;
$checks['raw_line_foreign_keys_6'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income_lines' AND CONSTRAINT_TYPE='FOREIGN KEY'")->fetchColumn() === 6;
$manifestColumns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tmp_test_fixture_manifest'")->fetchAll(PDO::FETCH_COLUMN);
$checks['manifest_owned'] = in_array('suite_code', $manifestColumns, true)
    ? (int) $pdo->query("SELECT COUNT(*) FROM tmp_test_fixture_manifest WHERE suite_code='DAILY_EVIDENCE_MIGRATION_P1' AND schema_name=DATABASE()")->fetchColumn() >= 1
    : (int) $pdo->query("SELECT COUNT(*) FROM tmp_test_fixture_manifest WHERE fixture_suite_code='DAILY_EVIDENCE_NORMALIZATION_P1' AND schema_name=DATABASE()")->fetchColumn() === 1;

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'schema' => $schema, 'create_schema' => false, 'drop_schema' => false,
    'schema_retained' => true, 'fixture_suite_code' => $fixtureSuite, 'fixture_header_count' => 1,
    'fixture_calculation_line_count' => 35, 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
