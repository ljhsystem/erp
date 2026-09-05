<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT.'/core/Database.php';
require PROJECT_ROOT.'/core/DbPdo.php';

use Core\DbPdo;

$db=DbPdo::conn();
$scalar=static function(string $sql)use($db):int{return (int)$db->query($sql)->fetchColumn();};
$checks=[
    '계정코드 중복'=>$scalar("SELECT COUNT(*) FROM (SELECT account_code FROM ledger_accounts WHERE deleted_at IS NULL GROUP BY account_code HAVING COUNT(*)>1)x"),
    '계정 상위참조 누락'=>$scalar('SELECT COUNT(*) FROM ledger_accounts a LEFT JOIN ledger_accounts p ON p.id=a.parent_id WHERE a.parent_id IS NOT NULL AND p.id IS NULL'),
    '전기계정 하위계정 보유'=>$scalar('SELECT COUNT(*) FROM ledger_accounts a WHERE a.is_posting=1 AND EXISTS(SELECT 1 FROM ledger_accounts c WHERE c.parent_id=a.id AND c.deleted_at IS NULL)'),
    '보조원장 계정참조 누락'=>$scalar('SELECT COUNT(*) FROM ledger_accounts_sub s LEFT JOIN ledger_accounts a ON a.id=s.account_id WHERE a.id IS NULL'),
    '분개규칙 결과계정 누락'=>$scalar('SELECT COUNT(*) FROM ledger_journal_rules r LEFT JOIN ledger_accounts a ON a.id=r.account_id WHERE r.deleted_at IS NULL AND a.id IS NULL'),
    '분개규칙 사용불가 결과계정'=>$scalar('SELECT COUNT(*) FROM ledger_journal_rules r JOIN ledger_accounts a ON a.id=r.account_id WHERE r.deleted_at IS NULL AND r.is_active=1 AND (a.deleted_at IS NOT NULL OR a.is_active<>1 OR a.is_posting<>1)'),
    '활성 분개규칙 코드중복'=>$scalar("SELECT COUNT(*) FROM (SELECT company_id,rule_code FROM ledger_journal_rules WHERE deleted_at IS NULL AND is_active=1 GROUP BY company_id,rule_code HAVING COUNT(*)>1)x"),
    '분개규칙 사업구분 누락'=>$scalar("SELECT COUNT(*) FROM ledger_journal_rules r LEFT JOIN system_codes c ON c.code_group='BUSINESS_UNIT' AND c.code=r.business_unit AND c.is_active=1 WHERE r.deleted_at IS NULL AND r.business_unit IS NOT NULL AND c.code IS NULL"),
    '분개규칙 업무유형 누락'=>$scalar("SELECT COUNT(*) FROM ledger_journal_rules r LEFT JOIN system_codes c ON c.code_group='OPERATION_TYPE' AND c.code=r.operation_type AND c.is_active=1 WHERE r.deleted_at IS NULL AND r.operation_type IS NOT NULL AND c.code IS NULL"),
    '분개규칙 자료유형 누락'=>$scalar("SELECT COUNT(*) FROM ledger_journal_rules r LEFT JOIN system_codes c ON c.code_group='IMPORT_TYPE' AND c.code=r.import_type AND c.is_active=1 WHERE r.deleted_at IS NULL AND r.import_type IS NOT NULL AND c.code IS NULL"),
    '기초금액 회사연도 중복'=>$scalar('SELECT COUNT(*) FROM (SELECT company_id,fiscal_year FROM ledger_opening_balances GROUP BY company_id,fiscal_year HAVING COUNT(*)>1)x'),
    '기초금액 기간오류'=>$scalar('SELECT COUNT(*) FROM ledger_opening_balances WHERE YEAR(opening_date)<>fiscal_year OR YEAR(period_end_date)<>fiscal_year OR opening_date>period_end_date'),
    '기초전표 참조누락'=>$scalar('SELECT COUNT(*) FROM ledger_opening_balances o LEFT JOIN ledger_vouchers v ON v.id=o.voucher_id WHERE o.voucher_id IS NOT NULL AND v.id IS NULL'),
    '재고 회사연도 중복'=>$scalar('SELECT COUNT(*) FROM (SELECT company_id,fiscal_year FROM ledger_inventory_balances GROUP BY company_id,fiscal_year HAVING COUNT(*)>1)x'),
    '재고 전문건설 프로젝트 누락'=>$scalar("SELECT COUNT(*) FROM ledger_inventory_balance_items WHERE business_unit='CONSTRUCTION' AND project_id IS NULL"),
    '재고 필수근거 누락'=>$scalar("SELECT COUNT(*) FROM ledger_inventory_balance_items WHERE TRIM(item_name)='' OR TRIM(calculation_basis)='' OR TRIM(evidence_reference)=''"),
    '재고 기말금액 음수'=>$scalar('SELECT COUNT(*) FROM ledger_inventory_balance_items WHERE opening_amount+increase_amount-decrease_amount<0'),
    '관련 Comment 누락'=>$scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ledger_accounts','ledger_accounts_sub','ledger_journal_rules','ledger_opening_balances','ledger_inventory_balances','ledger_inventory_balance_items') AND COALESCE(COLUMN_COMMENT,'')=''"),
    '관련 Trigger'=>$scalar("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('ledger_accounts','ledger_accounts_sub','ledger_journal_rules','ledger_opening_balances','ledger_inventory_balances','ledger_inventory_balance_items')"),
];
$failed=array_filter($checks,static fn(int $count,string $name):bool=>$name==='관련 Trigger'?$count!==0:$count!==0,ARRAY_FILTER_USE_BOTH);
$summary=[
    'accounts'=>$scalar('SELECT COUNT(*) FROM ledger_accounts WHERE deleted_at IS NULL'),
    'posting_accounts'=>$scalar('SELECT COUNT(*) FROM ledger_accounts WHERE deleted_at IS NULL AND is_active=1 AND is_posting=1'),
    'sub_account_policies'=>$scalar('SELECT COUNT(*) FROM ledger_accounts_sub'),
    'journal_rules'=>$scalar('SELECT COUNT(*) FROM ledger_journal_rules WHERE deleted_at IS NULL'),
    'opening_balances'=>$scalar('SELECT COUNT(*) FROM ledger_opening_balances'),
    'inventory_documents'=>$scalar('SELECT COUNT(*) FROM ledger_inventory_balances'),
    'inventory_items'=>$scalar('SELECT COUNT(*) FROM ledger_inventory_balance_items'),
];
$passed=$failed===[];echo json_encode(['passed'=>$passed,'summary'=>$summary,'checks'=>$checks,'failed'=>$failed],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
