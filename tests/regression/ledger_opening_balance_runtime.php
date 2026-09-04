<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\OpeningBalanceService;
use Core\DbPdo;

$db = DbPdo::conn();
$companyIds = $db->query('SELECT id FROM system_company ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
if (count($companyIds) !== 1) throw new RuntimeException('단일 회사 검증환경이 아닙니다.');
$accounts = $db->query("SELECT a.id FROM ledger_accounts a
    WHERE a.is_posting=1 AND a.is_active=1
      AND NOT EXISTS (SELECT 1 FROM ledger_accounts_sub s WHERE s.account_id=a.id AND s.is_required=1)
    ORDER BY a.account_code,a.id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN) ?: [];
if (count($accounts) !== 2) throw new RuntimeException('보조원장 비필수 전기계정 2개가 필요합니다.');
$before = [
    'opening' => (int) $db->query('SELECT COUNT(*) FROM ledger_opening_balances')->fetchColumn(),
    'voucher' => (int) $db->query('SELECT COUNT(*) FROM ledger_vouchers')->fetchColumn(),
    'line' => (int) $db->query('SELECT COUNT(*) FROM ledger_voucher_lines')->fetchColumn(),
];
$db->beginTransaction();
try {
    $year = 2999;
    $service = new OpeningBalanceService($db);
    $saved = $service->save([
        'company_id' => (string) $companyIds[0],
        'fiscal_year' => $year,
        'note' => '기초금액 회귀검증',
        'lines' => [
            ['account_id'=>(string)$accounts[0],'debit'=>12345,'credit'=>0,'line_summary'=>'기초 차변','refs'=>[]],
            ['account_id'=>(string)$accounts[1],'debit'=>0,'credit'=>12345,'line_summary'=>'기초 대변','refs'=>[]],
        ],
    ]);
    $detail = $saved['data'] ?? [];
    $checks = [
        'relation_created' => (int) $db->query("SELECT COUNT(*) FROM ledger_opening_balances WHERE fiscal_year={$year}")->fetchColumn() === 1,
        'opening_date' => ($detail['opening_date'] ?? '') === '2998-12-31',
        'line_count' => count($detail['lines'] ?? []) === 2,
        'balanced' => (float)($detail['debit_total'] ?? 0) === 12345.0 && (float)($detail['credit_total'] ?? 0) === 12345.0,
        'draft_status' => ($detail['status'] ?? '') === 'DRAFT',
    ];
    if (in_array(false, $checks, true)) throw new RuntimeException('기초금액 Runtime 불변식이 실패했습니다.');
    $db->rollBack();
    $after = [
        'opening' => (int) $db->query('SELECT COUNT(*) FROM ledger_opening_balances')->fetchColumn(),
        'voucher' => (int) $db->query('SELECT COUNT(*) FROM ledger_vouchers')->fetchColumn(),
        'line' => (int) $db->query('SELECT COUNT(*) FROM ledger_voucher_lines')->fetchColumn(),
    ];
    $rolledBack = $before === $after;
    echo json_encode(['passed'=>$rolledBack,'checks'=>$checks,'before'=>$before,'after'=>$after,'rolled_back'=>$rolledBack], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($rolledBack ? 0 : 1);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}
