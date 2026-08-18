<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\VoucherEvidenceRecommendationService;

$pdo = Core\DbPdo::conn();
$identities = [];
foreach ($pdo->query("SELECT import_type,source_table FROM ledger_evidence_metadata WHERE deleted_at IS NULL ORDER BY sort_no")->fetchAll(PDO::FETCH_ASSOC) as $metadata) {
    $table = (string) $metadata['source_table'];
    if (preg_match('/^[a-z0-9_]+$/i', $table) !== 1) continue;
    foreach ($pdo->query("SELECT id FROM `{$table}` ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $identities[] = ['import_type' => (string) $metadata['import_type'], 'evidence_id' => (string) $id];
        if (count($identities) >= 3) break 2;
    }
}
if (count($identities) < 3) throw new RuntimeException('성능검증용 증빙 3건을 찾을 수 없습니다.');

$result = [];
foreach ([1, 3] as $count) {
    $started = microtime(true);
    $recommendations = (new VoucherEvidenceRecommendationService($pdo))->recommend(array_slice($identities, 0, $count));
    $result[$count] = ['elapsed_ms' => round((microtime(true) - $started) * 1000, 2), 'result_count' => count($recommendations)];
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
