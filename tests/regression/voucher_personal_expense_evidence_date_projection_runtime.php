<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Repositories\Ledger\EvidenceSourceRepository;
use Core\DbPdo;

$db = DbPdo::conn();
$ids = $db->query("SELECT id FROM ledger_evidence_employee_personal_expense WHERE deleted_at IS NULL ORDER BY sort_no,id LIMIT 50")
    ->fetchAll(PDO::FETCH_COLUMN) ?: [];
if ($ids === []) throw new RuntimeException('검증할 직원경비(개인) 증빙이 없습니다.');

$repository = new EvidenceSourceRepository($db);
foreach ($ids as $id) {
    $row = $repository->find('EMPLOYEE_EXPENSE_PERSONAL', (string) $id);
    $rawDate = substr(trim((string) ($row['raw_expense_date'] ?? '')), 0, 10);
    $evidenceDate = substr(trim((string) ($row['evidence_date'] ?? '')), 0, 10);
    if ($rawDate === '' || $evidenceDate !== $rawDate) {
        throw new RuntimeException("직원경비 증빙일자 Projection 불일치: {$id}");
    }
}

echo json_encode(['success' => true, 'checked_count' => count($ids)], JSON_UNESCAPED_UNICODE) . PHP_EOL;
