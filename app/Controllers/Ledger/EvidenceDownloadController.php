<?php

namespace App\Controllers\Ledger;

use App\Controllers\Ledger\Concerns\ImportControllerUtilityTrait;
use App\Services\Ledger\EvidenceDownloadService;
use Core\DbPdo;
use PDO;

class EvidenceDownloadController
{
    use ImportControllerUtilityTrait;

    private PDO $pdo;
    private ?EvidenceDownloadService $evidenceDownloadService = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiDownload(): void
    {
        $importType = (string) ($_GET['import_type'] ?? $_GET['data_type'] ?? '');
        $columnsCsv = trim((string) ($_GET['columns'] ?? ''));
        if (!$this->evidenceDownloadService()->outputSyntheticDownloadByType($importType, $columnsCsv)) {
            $this->json(['success' => false, 'message' => '증빙 다운로드 자료유형을 찾을 수 없습니다.'], 404);
        }
    }

    public function apiSearchSummary(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        $this->json([
            'success' => true,
            'items' => $this->evidenceDownloadService()->searchSummary($query, 10),
        ]);
    }

    private function evidenceDownloadService(): EvidenceDownloadService
    {
        if ($this->evidenceDownloadService === null) {
            $this->evidenceDownloadService = new EvidenceDownloadService($this->pdo);
        }

        return $this->evidenceDownloadService;
    }

}
