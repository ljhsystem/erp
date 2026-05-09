<?php

namespace App\Controllers\Ledger;

use App\Models\System\ClientModel;
use App\Models\System\CompanyModel;
use App\Services\Ledger\TransactionCrudService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController
{
    private const DATA_TYPES = [
        'TAX_INVOICE',
        'CASH_RECEIPT',
        'CARD_APPROVAL',
        'BANK_TRANSACTION',
        'SHOPPING_ORDER',
        'IMPORT_INVOICE',
        'ETC',
    ];

    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_APPROVAL',
        'CARD_PURCHASE' => 'CARD_APPROVAL',
        'CARD_SALE' => 'CARD_APPROVAL',
        'BANK' => 'BANK_TRANSACTION',
        'SHOPPING' => 'SHOPPING_ORDER',
        'TRADE_IMPORT' => 'IMPORT_INVOICE',
        'IMPORT' => 'IMPORT_INVOICE',
    ];

    private const IMPORT_FIELD_GROUPS = [
        'Header' => [
            'project_name',
            'description',
            'transaction_date',
            'approval_number',
            'issue_date',
            'transmit_date',
            'tax_invoice_category',
            'tax_invoice_type',
            'issue_type',
            'receipt_claim_type',
            'total_amount',
            'supply_amount',
            'vat_amount',
            'note',
        ],
        'Supplier' => [
            'supplier_business_number',
            'supplier_branch_number',
            'supplier_company_name',
            'supplier_ceo_name',
            'supplier_address',
            'supplier_email',
        ],
        'Customer' => [
            'customer_business_number',
            'customer_branch_number',
            'customer_company_name',
            'customer_ceo_name',
            'customer_address',
            'customer_email_1',
            'customer_email_2',
        ],
        'Broker' => [
            'broker_business_number',
            'broker_company_name',
        ],
        'Item' => [
            'item_date',
            'item_name',
            'item_spec',
            'item_qty',
            'item_price',
            'item_supply_amount',
            'item_vat_amount',
            'item_note',
        ],
    ];

    private PDO $pdo;
    private ?TransactionCrudService $transactionService = null;
    private ?array $ownCompanyProfile = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiTemplate(): void
    {
        try {
            $formatId = trim((string) ($_GET['format_id'] ?? ''));
            if ($formatId !== '') {
                $format = $this->formatWithColumns($formatId);
                if (!$format || empty($format['columns'])) {
                    $this->json(['success' => false, 'message' => '다운로드할 양식을 찾을 수 없습니다.'], 404);
                    return;
                }

                [$filename, $title, $headers, $samples] = $this->templateSpecFromFormat($format);
                $this->downloadTemplate($filename, $title, $headers, $samples);
                return;
            }

            $type = self::normalizeDataType((string) ($_GET['type'] ?? 'TAX_INVOICE'));
            if (!$this->isAllowedDataType($type)) {
                $this->json(['success' => false, 'message' => '지원하지 않는 양식 유형입니다.'], 400);
                return;
            }

            [$filename, $title, $headers, $samples] = $this->templateSpec($type);
            $this->downloadTemplate($filename, $title, $headers, $samples);
        } catch (\Throwable $e) {
            error_log('[ImportController] Template download failed: ' . $e->getMessage());
            if (!headers_sent()) {
                self::clearOutputBuffers();
                $this->json(['success' => false, 'message' => '양식 다운로드에 실패했습니다.'], 500);
            }
        }
    }

    private function downloadTemplate(string $filename, string $title, array $headers, array $samples): void
    {
        $spreadsheet = new Spreadsheet();
        $tempFile = tempnam(sys_get_temp_dir(), 'ledger_template_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary XLSX file.');
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(self::safeSheetTitle($title));
            $sheet->fromArray($headers, null, 'A1');
            $sheet->fromArray($samples, null, 'A2');

            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastColumn}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFEFF6FF');
            $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
            for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->freezePane('A2');

            (new Xlsx($spreadsheet))->save($tempFile);
            $spreadsheet->disconnectWorksheets();

            $filename = self::safeFilename($filename);
            if (!str_ends_with(strtolower($filename), '.xlsx')) {
                $filename .= '.xlsx';
            }
            $asciiFallback = preg_replace('#[^A-Za-z0-9_.-]+#', '_', $filename) ?: 'upload_template.xlsx';
            $encodedFilename = rawurlencode($filename);

            if (headers_sent($sentFile, $sentLine)) {
                throw new \RuntimeException("Headers already sent at {$sentFile}:{$sentLine}");
            }

            self::clearOutputBuffers();
            header_remove('Content-Type');
            header_remove('Content-Disposition');
            header_remove('Cache-Control');
            header_remove('Pragma');
            header_remove('Expires');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$asciiFallback}\"; filename*=UTF-8''{$encodedFilename}");
            header('Content-Transfer-Encoding: binary');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: max-age=0');
            header('Content-Length: ' . filesize($tempFile));

            if (readfile($tempFile) === false) {
                error_log('[ImportController] Template download failed while streaming XLSX.');
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
        exit;
    }

    private static function clearOutputBuffers(): void
    {
        if (ob_get_length()) {
            @ob_end_clean();
        }

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
    }
    public function apiFieldOptions(): void
    {
        $this->json([
            'success' => true,
            'data' => $this->importFieldOptionsForResponse(),
        ]);
    }

    public function apiFormats(): void
    {
        $dataType = self::normalizeDataType((string) ($_GET['data_type'] ?? ''));
        $sql = 'SELECT * FROM ledger_data_formats WHERE deleted_at IS NULL';
        $params = [];
        if ($dataType !== '') {
            if (!$this->isAllowedDataType($dataType)) {
                $this->json(['success' => true, 'data' => []]);
                return;
            }
            $types = self::queryDataTypes($dataType);
            $placeholders = [];
            foreach ($types as $index => $type) {
                $key = ':data_type_' . $index;
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $sql .= ' AND data_type IN (' . implode(', ', $placeholders) . ')';
        }
        $sql .= ' ORDER BY data_type ASC, is_default DESC, format_name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['data_type'] = self::normalizeDataType((string) ($row['data_type'] ?? ''));
        }
        unset($row);

        $includeColumns = (string) ($_GET['include_columns'] ?? '') === '1';
        if ($includeColumns) {
            foreach ($rows as &$row) {
                $row['columns'] = $this->columns((string) $row['id']);
            }
            unset($row);
        }

        $this->json(['success' => true, 'data' => $rows]);
    }

    public function apiFormatDetail(): void
    {
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => '양식 ID가 없습니다.'], 400);
            return;
        }

        $format = $this->format($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => '양식을 찾을 수 없습니다.'], 404);
            return;
        }
        $format['columns'] = $this->columns($id);
        $this->json(['success' => true, 'data' => $format]);
    }

    public function apiFormatSave(): void
    {
        $payload = $this->requestPayload();
        $id = trim((string) ($payload['id'] ?? ''));
        $formatName = trim((string) ($payload['format_name'] ?? ''));
        $dataType = self::normalizeDataType((string) ($payload['data_type'] ?? ''));
        $isDefault = !empty($payload['is_default']) ? 1 : 0;
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];

        if ($formatName === '' || !$this->isAllowedDataType($dataType)) {
            $this->json(['success' => false, 'message' => '양식명과 자료 유형을 입력하세요.'], 400);
            return;
        }

        $normalizedColumns = $this->normalizeColumns($columns);
        if ($normalizedColumns === []) {
            $this->json(['success' => false, 'message' => '컬럼 매핑을 1개 이상 입력하세요.'], 400);
            return;
        }

        $actor = ActorHelper::user();
        try {
            $this->pdo->beginTransaction();
            if ($isDefault === 1) {
                $defaultTypes = self::queryDataTypes($dataType);
                $placeholders = [];
                $params = [];
                foreach ($defaultTypes as $index => $type) {
                    $key = ':data_type_' . $index;
                    $placeholders[] = $key;
                    $params[$key] = $type;
                }
                $this->pdo->prepare('UPDATE ledger_data_formats SET is_default = 0 WHERE deleted_at IS NULL AND data_type IN (' . implode(', ', $placeholders) . ')')
                    ->execute($params);
            }

            if ($id === '') {
                $id = UuidHelper::generate();
                $stmt = $this->pdo->prepare("
                    INSERT INTO ledger_data_formats (id, format_name, data_type, is_default, created_by)
                    VALUES (:id, :format_name, :data_type, :is_default, :created_by)
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':format_name' => $formatName,
                    ':data_type' => $dataType,
                    ':is_default' => $isDefault,
                    ':created_by' => $actor,
                ]);
            } else {
                $currentFormat = $this->format($id);
                if (!$currentFormat) {
                    $this->pdo->rollBack();
                    $this->json(['success' => false, 'message' => json_decode('"\uC218\uC815\uD560 \uC591\uC2DD\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4."')], 404);
                    return;
                }

                $stmt = $this->pdo->prepare("
                    UPDATE ledger_data_formats
                    SET format_name = :format_name,
                        data_type = :data_type,
                        is_default = :is_default
                    WHERE id = :id
                      AND deleted_at IS NULL
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':format_name' => $formatName,
                    ':data_type' => $dataType,
                    ':is_default' => $isDefault,
                ]);
                $this->pdo->prepare('DELETE FROM ledger_data_format_columns WHERE format_id = :format_id')
                    ->execute([':format_id' => $id]);
            }

            $insert = $this->pdo->prepare("
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, excel_column_index, system_field_name, column_order, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :excel_column_index, :system_field_name, :column_order, :is_required)
            ");
            foreach ($normalizedColumns as $column) {
                $insert->execute([
                    ':id' => UuidHelper::generate(),
                    ':format_id' => $id,
                    ':excel_column_name' => $column['excel_column_name'],
                    ':excel_column_index' => $column['excel_column_index'],
                    ':system_field_name' => $column['system_field_name'],
                    ':column_order' => $column['column_order'],
                    ':is_required' => $column['is_required'],
                ]);
            }

            $this->pdo->commit();
            $this->json(['success' => true, 'id' => $id, 'message' => '양식이 저장되었습니다.']);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiFormatDelete(): void
    {
        $id = trim((string) ($this->requestPayload()['id'] ?? ''));
        if ($id === '') {
            $this->json(['success' => false, 'message' => json_decode('"\uC591\uC2DD ID\uAC00 \uC5C6\uC2B5\uB2C8\uB2E4."')], 400);
            return;
        }

        $format = $this->format($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => json_decode('"\uC591\uC2DD\uC744 \uCC3E\uC744 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4."')], 404);
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_formats
            SET deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $id,
            ':deleted_by' => ActorHelper::user(),
        ]);

        $this->json(['success' => true, 'message' => json_decode('"\uC591\uC2DD\uC774 \uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatTrashList(): void
    {
        $stmt = $this->pdo->query("
            SELECT
                id,
                format_name,
                data_type,
                is_default,
                created_by,
                deleted_at,
                deleted_by
            FROM ledger_data_formats
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC, format_name ASC
        ");

        $this->json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    public function apiFormatRestore(): void
    {
        $ids = $this->formatIdsFromPayload($this->requestPayload());
        if ($ids === []) {
            $this->json(['success' => false, 'message' => json_decode('"\uBCF5\uC6D0\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694."')], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'format_id');
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_formats
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE id IN ({$inSql})
              AND deleted_at IS NOT NULL
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => json_decode('"\uC591\uC2DD\uC774 \uBCF5\uC6D0\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatRestoreAll(): void
    {
        $this->pdo->exec("
            UPDATE ledger_data_formats
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE deleted_at IS NOT NULL
        ");

        $this->json(['success' => true, 'message' => json_decode('"\uC804\uCCB4 \uC591\uC2DD\uC774 \uBCF5\uC6D0\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatPurge(): void
    {
        $ids = $this->formatIdsFromPayload($this->requestPayload());
        if ($ids === []) {
            $this->json(['success' => false, 'message' => json_decode('"\uC601\uAD6C\uC0AD\uC81C\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694."')], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'format_id');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM ledger_data_format_columns WHERE format_id IN ({$inSql})")->execute($params);
            $this->pdo->prepare("DELETE FROM ledger_data_formats WHERE id IN ({$inSql}) AND deleted_at IS NOT NULL")->execute($params);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'message' => json_decode('"\uC591\uC2DD\uC774 \uC601\uAD6C\uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatPurgeAll(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("
                DELETE c
                FROM ledger_data_format_columns c
                INNER JOIN ledger_data_formats f ON f.id = c.format_id
                WHERE f.deleted_at IS NOT NULL
            ");
            $this->pdo->exec("DELETE FROM ledger_data_formats WHERE deleted_at IS NOT NULL");
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'message' => json_decode('"\uC804\uCCB4 \uC591\uC2DD\uC774 \uC601\uAD6C\uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4."')]);
    }

    public function apiFormatCopy(): void
    {
        $id = trim((string) ($this->requestPayload()['id'] ?? ''));
        $format = $this->formatWithColumns($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => '복사할 양식을 찾을 수 없습니다.'], 404);
            return;
        }

        $newId = UuidHelper::generate();
        $actor = ActorHelper::user();

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("
                INSERT INTO ledger_data_formats (id, format_name, data_type, is_default, created_by)
                VALUES (:id, :format_name, :data_type, 0, :created_by)
            ");
            $stmt->execute([
                ':id' => $newId,
                ':format_name' => '복사본 - ' . (string) $format['format_name'],
                ':data_type' => (string) $format['data_type'],
                ':created_by' => $actor,
            ]);

            $insert = $this->pdo->prepare("
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, excel_column_index, system_field_name, column_order, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :excel_column_index, :system_field_name, :column_order, :is_required)
            ");
            foreach ($format['columns'] as $column) {
                $insert->execute([
                    ':id' => UuidHelper::generate(),
                    ':format_id' => $newId,
                    ':excel_column_name' => (string) $column['excel_column_name'],
                    ':excel_column_index' => isset($column['excel_column_index']) ? (int) $column['excel_column_index'] : (int) $column['column_order'],
                    ':system_field_name' => $column['system_field_name'] !== null ? (string) $column['system_field_name'] : null,
                    ':column_order' => (int) $column['column_order'],
                    ':is_required' => (int) $column['is_required'],
                ]);
            }

            $this->pdo->commit();
            $this->json(['success' => true, 'id' => $newId, 'message' => '양식이 복사되었습니다.']);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiPreview(): void
    {
        $formatId = trim((string) ($_POST['format_id'] ?? ''));
        $format = $this->formatWithColumns($formatId);
        if (!$format || empty($_FILES['file'])) {
            $this->json(['success' => false, 'message' => '양식과 파일을 선택하세요.'], 400);
            return;
        }

        try {
            $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
            $checks = $this->validateUploadFileColumns($_FILES['file'], $format['columns']);
            $rows = $this->parseUploadedRows($_FILES['file'], $format['columns']);
            $rows = $this->enrichUploadRows($rows, $dataType);
            $rows = $this->validatePreviewRows($rows, $format['columns']);
            $rows = $this->annotateSeedComparison($rows, $dataType);
            $token = $this->storeUploadPreviewSession($format, $_FILES['file'], $rows);
            $summary = $this->uploadValidationSummary($rows);
            $summary['check_error'] = count(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'error'));
            $summary['check_warning'] = count(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'warning'));
            $this->json(['success' => true, 'data' => [
                'preview_token' => $token,
                'summary' => $summary,
                'checks' => $checks,
                'format' => $format,
                'rows' => $rows,
            ]]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function apiSeedUpload(): void
    {
        $payload = $this->requestPayload();
        $token = trim((string) ($payload['preview_token'] ?? ''));
        $preview = $this->uploadPreviewFromSession($token);
        if (!$preview) {
            $this->json(['success' => false, 'message' => '검증 결과가 없습니다. 먼저 검증을 실행하세요.'], 400);
            return;
        }

        try {
            $result = $this->storeUploadBatch($preview['format'], $preview['file'], $preview['rows']);
            $this->clearUploadPreviewSession($token);
            $this->json(['success' => true, 'data' => $result, 'message' => 'Seed 업로드가 완료되었습니다.']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function apiCreateTransactions(): void
    {
        $payload = $this->requestPayload();
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        $rowIds = is_array($payload['seed_row_ids'] ?? null)
            ? array_values(array_filter(array_map('strval', $payload['seed_row_ids'])))
            : (is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : []);
        error_log('[ImportController] apiCreateTransactions entered batch_id=' . ($batchId !== '' ? $batchId : '(empty)') . ' seed_rows=' . count($rowIds));

        if ($batchId === '' && $rowIds === []) {
            $this->json(['success' => false, 'message' => '거래 생성할 Seed Data를 선택하세요.'], 400);
            return;
        }

        $batch = null;
        if ($batchId !== '') {
            $batch = $this->uploadBatch($batchId);
            if (!$batch) {
                $this->json(['success' => false, 'message' => 'Seed 배치를 찾을 수 없습니다.'], 404);
                return;
            }
        }

        $dataType = self::normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $rows = $this->claimSeedRowsForTransactionCreate($batchId, $rowIds);
        error_log('[ImportController] transaction target rows=' . count($rows) . ' batch_id=' . $batchId);

        if ($rows === []) {
            $this->json([
                'success' => false,
                'batch_id' => $batchId !== '' ? $batchId : null,
                'target_count' => 0,
                'created_count' => 0,
                'duplicate_count' => 0,
                'error_count' => 0,
                'transaction_line_count' => 0,
                'success_count' => 0,
                'processed_ids' => [],
                'error_ids' => [],
                'updated_status' => [],
                'errors' => [],
                'message' => '거래 생성 가능한 READY Seed Data가 없습니다.',
            ], 400);
            return;
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        $createdTransactionIds = [];
        $processedIds = [];
        $errorIds = [];
        $updatedStatus = [];

        foreach ($rows as $row) {
            $rowId = (string) ($row['id'] ?? '');
            $rowNo = (int) ($row['row_no'] ?? 0);
            $mapped = json_decode((string) ($row['mapped_payload'] ?? ''), true);

            if (!is_array($mapped)) {
                $message = '매핑 데이터 JSON을 읽을 수 없습니다.';
                    $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
                continue;
            }

            try {
                $rowDataType = self::normalizeDataType((string) ($row['source_type'] ?? $dataType));
                if ($this->hasDuplicateTransaction($mapped, $rowId, $rowDataType)) {
                    $duplicates++;
                    $this->updateUploadRowStatus($rowId, 'DUPLICATE', '기존 거래 존재');
                    $updatedStatus[$rowId] = 'DUPLICATED';
                    continue;
                }

                $result = $this->transactionService()->save($this->transactionPayload($mapped, $rowDataType));
                error_log('[ImportController] transaction save row=' . $rowNo . ' success=' . (!empty($result['success']) ? '1' : '0') . ' id=' . (string) ($result['id'] ?? '') . ' message=' . (string) ($result['message'] ?? ''));

                if (!empty($result['success']) && !empty($result['id'])) {
                    $transactionId = (string) $result['id'];
                    $created++;
                    $createdTransactionIds[] = $transactionId;
                    $processedIds[] = $rowId;
                    $updatedStatus[$rowId] = 'PROCESSED';
                    $this->updateUploadRowStatus($rowId, 'CREATED', null, $transactionId);
                    continue;
                }

                $message = $this->formatTransactionCreateError((string) ($result['message'] ?? '거래 생성 실패'), $mapped, $rowNo);
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
            } catch (\Throwable $e) {
                $rawMessage = $e->getMessage();
                $message = $this->formatTransactionCreateError($rawMessage, $mapped, $rowNo);
                error_log('[ImportController] transaction create row failed row=' . $rowNo . ' row_id=' . $rowId . ' error=' . $rawMessage);
                $this->updateUploadRowStatus($rowId, 'ERROR', $message);
                $errorIds[] = $rowId;
                $updatedStatus[$rowId] = 'ERROR';
                $errors[] = ['row_id' => $rowId, 'row' => $rowNo, 'message' => $message];
            }
        }

        if ($batchId !== '') {
            $this->refreshUploadBatchStatus($batchId);
        }

        $errorCount = count($errors);
        $transactionLineCount = $this->countTransactionLines($createdTransactionIds);
        $lineCountError = $transactionLineCount < $created;
        if ($lineCountError) {
            $errors[] = [
                'row_id' => null,
                'row' => null,
                'message' => '생성된 거래 수보다 거래라인 수가 적습니다.',
            ];
            $errorCount = count($errors);
        }
        $success = $errorCount === 0;
        $message = sprintf('ERP 거래 생성 완료: 생성 %d건, 중복 %d건, 오류 %d건', $created, $duplicates, $errorCount);

        error_log('[ImportController] apiCreateTransactions completed batch_id=' . $batchId . ' target=' . count($rows) . ' created=' . $created . ' duplicate=' . $duplicates . ' error=' . $errorCount . ' lines=' . $transactionLineCount);

        $this->json([
            'success' => $success,
            'batch_id' => $batchId !== '' ? $batchId : null,
            'target_count' => count($rows),
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'error_count' => $errorCount,
            'transaction_line_count' => $transactionLineCount,
            'success_count' => $created,
            'processed_ids' => $processedIds,
            'error_ids' => $errorIds,
            'updated_status' => $updatedStatus,
            'errors' => $errors,
            'message' => $message,
        ], $success ? 200 : 422);
        return;
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '업로드 배치 ID가 없습니다.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '업로드 배치를 찾을 수 없습니다.'], 404);
            return;
        }

        $dataType = self::normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $rowIds = is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : [];
        $rows = $this->uploadRowsForTransactionCreate($batchId, $rowIds);
        if ($rows === []) {
            $this->json(['success' => false, 'message' => '거래 생성 가능한 READY Seed Data가 없습니다.'], 400);
            return;
        }

        $created = 0;
        $duplicates = 0;
        $errors = [];
        foreach ($rows as $row) {
            $mapped = json_decode((string) ($row['mapped_payload'] ?? ''), true);
            if (!is_array($mapped)) {
                $message = '매핑 데이터 JSON을 읽을 수 없습니다.';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
                continue;
            }

            if ($this->hasDuplicateTransaction($mapped, (string) ($row['id'] ?? ''), $dataType)) {
                $duplicates++;
                $this->updateUploadRowStatus((string) $row['id'], 'DUPLICATE', '기존 거래 존재');
                continue;
            }

            $result = $this->transactionService()->save($this->transactionPayload($mapped, $dataType));
            if (!empty($result['success'])) {
                $created++;
                $this->updateUploadRowStatus((string) $row['id'], 'CREATED', null, (string) ($result['id'] ?? ''));
            } else {
                $message = $result['message'] ?? '거래 생성 실패';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
            }
        }
        $this->refreshUploadBatchStatus($batchId);

        $this->json([
            'success' => $errors === [],
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'errors' => $errors,
            'message' => $errors === [] ? "{$created}건의 거래가 생성되었습니다." : '일부 거래 생성에 실패했습니다.',
        ], $errors === [] ? 200 : 422);
    }

    public function apiUploadBatches(): void
    {
        $stmt = $this->pdo->query("
            SELECT
                b.*,
                b.source_type AS data_type,
                f.format_name,
                SUM(CASE WHEN r.process_status = 'READY' THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN r.process_status = 'READY' THEN 1 ELSE 0 END) AS valid_count,
                0 AS warning_count,
                SUM(CASE WHEN r.process_status = 'ERROR' THEN 1 ELSE 0 END) AS error_count,
                SUM(CASE WHEN r.process_status = 'DUPLICATED' THEN 1 ELSE 0 END) AS duplicate_count,
                SUM(CASE WHEN r.process_status = 'PROCESSED' THEN 1 ELSE 0 END) AS created_count,
                SUM(CASE WHEN r.process_status = 'PROCESSED' THEN 1 ELSE 0 END) AS processed_count
            FROM ledger_data_seed_batches b
            LEFT JOIN ledger_data_formats f ON f.id = b.format_id
            LEFT JOIN ledger_data_seed_rows r ON r.seed_batch_id = b.id
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT 100
        ");

        $this->json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    public function apiSeedRows(): void
    {
        $status = strtoupper(trim((string) ($_GET['process_status'] ?? $_GET['status'] ?? '')));
        $requestedSourceType = strtoupper(trim((string) ($_GET['source_type'] ?? '')));
        $importType = self::normalizeDataType((string) ($_GET['import_type'] ?? $_GET['data_type'] ?? ''));
        $sourceType = '';
        if ($requestedSourceType !== '') {
            $normalizedRequested = self::normalizeDataType($requestedSourceType);
            if ($this->isAllowedDataType($normalizedRequested)) {
                $importType = $normalizedRequested;
            } else {
                $sourceType = self::normalizeImportSourceType($requestedSourceType);
            }
        }
        $filters = $this->seedRowFiltersFromRequest();

        $where = [];
        $params = [];
        if (in_array($status, ['READY', 'PROCESSED', 'ERROR', 'DUPLICATED'], true)) {
            $where[] = 'r.process_status = :process_status';
            $params[':process_status'] = $status;
        }
        if ($importType !== '') {
            $where[] = 'r.source_type = :import_type';
            $params[':import_type'] = $importType;
        } elseif ($sourceType !== '') {
            $types = self::importTypesForSourceType($sourceType);
            if ($types !== []) {
                $keys = [];
                foreach ($types as $index => $type) {
                    $key = ':source_type_' . $index;
                    $keys[] = $key;
                    $params[$key] = $type;
                }
                $where[] = 'r.source_type IN (' . implode(', ', $keys) . ')';
            }
        }
        $where[] = $status === 'DELETED' ? 'r.deleted_at IS NOT NULL' : 'r.deleted_at IS NULL';

        $sql = "
            SELECT
                r.id,
                r.seed_batch_id,
                " . self::sourceTypeSql('r.source_type') . " AS source_type,
                r.source_type AS import_type,
                st.code_name AS source_type_name,
                it.code_name AS import_type_name,
                r.row_no,
                r.raw_json,
                r.parsed_json,
                r.process_status,
                r.process_status AS status,
                r.error_message,
                r.transaction_id,
                r.processed_at,
                r.created_at,
                r.updated_at,
                r.deleted_at,
                b.file_name,
                f.format_name
            FROM ledger_data_seed_rows r
            LEFT JOIN ledger_data_seed_batches b ON b.id = r.seed_batch_id
            LEFT JOIN ledger_data_formats f ON f.id = b.format_id
            LEFT JOIN system_codes st
                ON st.deleted_at IS NULL
               AND st.is_active = 1
               AND st.code_group IN ('IMPORT_SOURCE', 'SOURCE_TYPE')
               AND st.code = " . self::sourceTypeSql('r.source_type') . "
            LEFT JOIN system_codes it
                ON it.deleted_at IS NULL
               AND it.is_active = 1
               AND it.code_group = 'IMPORT_TYPE'
               AND it.code = r.source_type
        ";
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY r.created_at DESC, r.row_no ASC LIMIT 1000';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_json'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['parsed_json'] ?? ''), true) ?: [];
            $mappedPayload = is_array($row['mapped_payload']) ? $row['mapped_payload'] : [];
            $row['client_name'] = (string) (
                $mappedPayload['client_company_name']
                ?? $mappedPayload['client_business_number']
                ?? $mappedPayload['supplier_company_name']
                ?? $mappedPayload['customer_company_name']
                ?? ''
            );
            unset($row['raw_json'], $row['parsed_json']);
            if (!empty($row['error_message'])) {
                $row['error_message'] = $this->formatTransactionCreateError(
                    (string) $row['error_message'],
                    is_array($row['mapped_payload']) ? $row['mapped_payload'] : [],
                    (int) ($row['row_no'] ?? 0)
                );
            }
        }
        unset($row);
        if ($filters !== []) {
            $rows = array_values(array_filter($rows, fn(array $row): bool => $this->seedRowMatchesFilters($row, $filters)));
        }

        $this->json(['success' => true, 'data' => $rows]);
    }

    public function apiSeedRowsTrash(): void
    {
        $_GET['status'] = 'DELETED';
        $this->apiSeedRows();
    }

    public function apiUploadBatchRows(): void
    {
        $batchId = trim((string) ($_GET['batch_id'] ?? ''));
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '업로드 배치 ID가 없습니다.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '업로드 배치를 찾을 수 없습니다.'], 404);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                seed_batch_id AS batch_id,
                source_type,
                row_no,
                raw_json AS raw_payload,
                parsed_json AS mapped_payload,
                process_status AS status,
                error_message,
                transaction_id,
                processed_at,
                created_at,
                updated_at
            FROM ledger_data_seed_rows
            WHERE seed_batch_id = :batch_id
            ORDER BY row_no ASC
        ");
        $stmt->execute([':batch_id' => $batchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['mapped_payload'] ?? ''), true) ?: [];
            if (!empty($row['error_message'])) {
                $row['error_message'] = $this->formatTransactionCreateError(
                    (string) $row['error_message'],
                    is_array($row['mapped_payload']) ? $row['mapped_payload'] : [],
                    (int) ($row['row_no'] ?? 0)
                );
            }
        }
        unset($row);

        $this->json(['success' => true, 'data' => [
            'batch' => $batch,
            'rows' => $rows,
        ]]);
    }

    public function apiSeedRowSave(): void
    {
        $payload = $this->requestPayload();
        $seedRowId = trim((string) ($payload['id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        if ($seedRowId === '' || !is_array($parsed)) {
            $this->json(['success' => false, 'message' => '수정할 Seed 행과 표준 필드값이 필요합니다.'], 400);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT process_status
            FROM ledger_data_seed_rows
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $seedRowId]);
        $status = (string) ($stmt->fetchColumn() ?: '');
        if ($status === '') {
            $this->json(['success' => false, 'message' => 'Seed 행을 찾을 수 없습니다.'], 404);
            return;
        }
        if ($status === 'PROCESSED') {
            $this->json(['success' => false, 'message' => '거래 생성이 완료된 Seed Data는 수정할 수 없습니다.'], 400);
            return;
        }

        $parsed = $this->mappedPayloadForStorage($parsed);
        $this->normalizeUploadAmountFields($parsed);
        $this->pdo->prepare("
            UPDATE ledger_data_seed_rows
            SET parsed_json = :parsed_json,
                process_status = 'READY',
                error_message = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ")->execute([
            ':id' => $seedRowId,
            ':parsed_json' => $this->jsonEncodeForStorage($parsed),
            ':actor' => ActorHelper::user(),
        ]);

        $this->json(['success' => true, 'message' => 'Seed Data가 수정되었습니다.']);
    }

    public function apiSeedRowsStatus(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        $status = strtoupper(trim((string) ($payload['process_status'] ?? $payload['status'] ?? '')));
        if ($ids === [] || !in_array($status, ['READY', 'ERROR', 'DUPLICATED'], true)) {
            $this->json(['success' => false, 'message' => '상태를 변경할 Seed Data와 상태값을 선택하세요.'], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'seed_id');
        $params[':status'] = $status;
        $params[':status_check'] = $status;
        $params[':actor'] = ActorHelper::user();
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_seed_rows
            SET process_status = :status,
                error_message = CASE WHEN :status_check = 'READY' THEN NULL ELSE error_message END,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id IN ({$inSql})
              AND process_status <> 'PROCESSED'
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => '선택 Seed Data 상태가 변경되었습니다.']);
    }

    public function apiSeedRowsDelete(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '삭제할 Seed Data를 선택하세요.'], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'seed_id');
        $actor = ActorHelper::user();
        $params[':deleted_by'] = $actor;
        $params[':updated_by'] = $actor;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_seed_rows
            SET deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id IN ({$inSql})
              AND process_status <> 'PROCESSED'
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => '선택 Seed Data가 삭제되었습니다. PROCESSED 행은 제외됩니다.']);
    }

    public function apiSeedRowsRestore(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '복구할 Seed Data를 선택하세요.'], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'seed_id');
        $params[':actor'] = ActorHelper::user();
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_seed_rows
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id IN ({$inSql})
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => '선택 Seed Data가 복구되었습니다.']);
    }

    public function apiSeedRowsRestoreAll(): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_seed_rows
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE deleted_at IS NOT NULL
        ");
        $stmt->execute([':actor' => ActorHelper::user()]);

        $this->json(['success' => true, 'message' => '휴지통 Seed Data가 복구되었습니다.']);
    }

    public function apiSeedRowsPurge(): void
    {
        $payload = $this->requestPayload();
        $ids = $this->seedRowIdsFromPayload($payload);
        if ($ids === []) {
            $this->json(['success' => false, 'message' => '영구 삭제할 Seed Data를 선택하세요.'], 400);
            return;
        }

        [$inSql, $params] = $this->placeholdersForIds($ids, 'seed_id');
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_data_seed_rows
            WHERE id IN ({$inSql})
              AND deleted_at IS NOT NULL
              AND process_status <> 'PROCESSED'
        ");
        $stmt->execute($params);

        $this->json(['success' => true, 'message' => '선택 Seed Data가 영구 삭제되었습니다.']);
    }

    public function apiSeedRowsPurgeAll(): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_data_seed_rows
            WHERE deleted_at IS NOT NULL
              AND process_status <> 'PROCESSED'
        ");
        $stmt->execute();

        $this->json(['success' => true, 'message' => '휴지통 Seed Data가 영구 삭제되었습니다.']);
    }

    public function apiUploadBatchDelete(): void
    {
        $payload = $this->requestPayload();
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '업로드 배치 ID가 없습니다.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '업로드 배치를 찾을 수 없습니다.'], 404);
            return;
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM ledger_data_seed_batches WHERE id = :id');
            $stmt->execute([':id' => $batchId]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        $this->json(['success' => true, 'message' => '업로드 배치가 삭제되었습니다.']);
    }

    private function templateSpec(string $type): array
    {
        $label = self::dataTypeLabel($type);
        if ($type === 'BANK_TRANSACTION') {
            return [
                'bank_upload_template.xlsx',
                'BANK template',
                ['거래일자', '입출구분', '상호', '적요', '금액', '잔액', '비고', '메모'],
                [
                    ['2026-05-04', '입금', '샘플상사', '매출대금 입금', 55000, 1055000, '샘플', ''],
                    ['2026-05-04', '출금', '샘플카드', '사용료 지급', 120000, 935000, '샘플', ''],
                ],
            ];
        }

        return [
            strtolower($type) . '_upload_template.xlsx',
            $label . ' template',
            ['작성일자', '공급자 사업자등록번호', '공급자 상호', '공급받는자 사업자등록번호', '공급받는자 상호', '사업명', '관리명', '적요', '공급가액', '부가세', '합계금액', '비고', '메모'],
            [
                ['2026-05-04', '123-45-67890', '샘플상사', '000-00-00000', '우리회사', '본사', '일반관리', $label . ' 매입', 50000, 5000, 55000, '샘플', ''],
                ['2026-05-04', '000-00-00000', '우리회사', '987-65-43210', '거래처상사', '운영', '매출', $label . ' 매출', 120000, 0, 120000, '샘플', ''],
            ],
        ];
    }

    private function templateSpecFromFormat(array $format): array
    {
        $columns = $format['columns'] ?? [];
        usort($columns, static fn(array $a, array $b): int => (int) ($a['column_order'] ?? 0) <=> (int) ($b['column_order'] ?? 0));

        $headers = array_values(array_filter(array_map(
            static fn(array $column): string => trim((string) ($column['excel_column_name'] ?? '')),
            $columns
        )));
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));
        $formatName = trim((string) ($format['format_name'] ?? self::dataTypeLabel($dataType)));

        return [
            self::safeFilename($formatName) . '_업로드_양식.xlsx',
            function_exists('mb_substr') ? mb_substr($formatName, 0, 31) : substr($formatName, 0, 31),
            $headers,
            [$this->sampleRowForColumns($columns, $dataType)],
        ];
    }

    private function sampleRowForColumns(array $columns, string $dataType): array
    {
        $samples = [
            'transaction_date' => '2026-05-04',
            'supplier_business_number' => '123-45-67890',
            'supplier_branch_number' => '0000',
            'supplier_company_name' => '샘플상사',
            'supplier_ceo_name' => '홍길동',
            'supplier_address' => '서울시 강남구',
            'supplier_email' => 'supplier@example.com',
            'customer_business_number' => '000-00-00000',
            'customer_branch_number' => '0000',
            'customer_company_name' => '우리회사',
            'customer_ceo_name' => '대표자',
            'customer_address' => '서울시',
            'customer_email_1' => 'tax@example.com',
            'customer_email_2' => '',
            'broker_business_number' => '',
            'broker_company_name' => '',
            'approval_number' => '20260504-0001',
            'issue_date' => '2026-05-04',
            'transmit_date' => '2026-05-04',
            'tax_invoice_category' => '일반',
            'tax_invoice_type' => '일반',
            'issue_type' => '정발급',
            'receipt_claim_type' => '청구',
            'project_name' => '본사',
            'description' => self::dataTypeLabel($dataType) . ' 샘플',
            'supply_amount' => 50000,
            'vat_amount' => $dataType === 'BANK_TRANSACTION' ? 0 : 5000,
            'total_amount' => 55000,
            'item_date' => '2026-05-04',
            'item_name' => '샘플 품목',
            'item_spec' => 'EA',
            'item_qty' => 1,
            'item_price' => 50000,
            'item_supply_amount' => 50000,
            'item_vat_amount' => $dataType === 'BANK_TRANSACTION' ? 0 : 5000,
            'item_note' => '',
            'note' => '샘플',
            'memo' => '',
        ];

        $row = [];
        foreach ($columns as $column) {
            $field = (string) ($column['system_field_name'] ?? '');
            $row[] = $samples[$field] ?? '';
        }

        return $row;
    }
    private function transactionService(): TransactionCrudService
    {
        if ($this->transactionService === null) {
            $this->transactionService = new TransactionCrudService($this->pdo);
        }

        return $this->transactionService;
    }

    private function storeUploadBatch(array $format, array $file, array $rows): array
    {
        $actor = ActorHelper::user();
        $batchId = UuidHelper::generate();
        $fileName = trim((string) ($file['name'] ?? 'upload'));
        $dataType = self::normalizeDataType((string) ($format['data_type'] ?? 'ETC'));

        $this->pdo->beginTransaction();
        try {
            $insertSeedBatch = $this->pdo->prepare("
                INSERT INTO ledger_data_seed_batches
                    (id, source_type, file_name, format_id, total_rows, created_by, updated_by)
                VALUES
                    (:id, :source_type, :file_name, :format_id, :total_rows, :created_by, :updated_by)
            ");
            $insertSeedBatch->execute([
                ':id' => $batchId,
                ':source_type' => $dataType,
                ':file_name' => $fileName !== '' ? $fileName : 'upload',
                ':format_id' => (string) ($format['id'] ?? ''),
                ':total_rows' => count($rows),
                ':created_by' => $actor,
                ':updated_by' => $actor,
            ]);

            $insertSeedRow = $this->pdo->prepare("
                INSERT INTO ledger_data_seed_rows
                    (id, seed_batch_id, source_type, source_key, row_no, raw_json, parsed_json, process_status, error_message, created_by, updated_by)
                VALUES
                    (:id, :seed_batch_id, :source_type, :source_key, :row_no, :raw_json, :parsed_json, :process_status, :error_message, :created_by, :updated_by)
            ");
            $updateSeedRow = $this->pdo->prepare("
                UPDATE ledger_data_seed_rows
                SET seed_batch_id = :seed_batch_id,
                    row_no = :row_no,
                    raw_json = :raw_json,
                    parsed_json = :parsed_json,
                    process_status = :process_status,
                    error_message = :error_message,
                    transaction_id = NULL,
                    processed_at = NULL,
                    deleted_at = NULL,
                    deleted_by = NULL,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
            ");
            $newCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;
            $errorCount = 0;
            foreach ($rows as $row) {
                $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
                $status = $this->uploadStatusFromValidation($validation);
                $processStatus = $status === 'ERROR' ? 'ERROR' : 'READY';
                $messages = is_array($validation['messages'] ?? null) ? $validation['messages'] : [];
                $parsedPayload = $this->mappedPayloadForStorage($row);
                $sourceKey = $this->seedSourceKey($parsedPayload, $dataType);
                $rawJson = $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : []);
                $parsedJson = $this->jsonEncodeForStorage($parsedPayload);
                $errorMessage = $messages !== [] ? implode(', ', $messages) : null;
                $existingSeed = $sourceKey !== null ? $this->findExistingSeedRow($dataType, $sourceKey) : null;
                if ($existingSeed && (string) ($existingSeed['raw_json'] ?? '') === $rawJson && (string) ($existingSeed['parsed_json'] ?? '') === $parsedJson) {
                    $unchangedCount++;
                    continue;
                }
                if ($existingSeed) {
                    $updatedCount++;
                    $updateSeedRow->execute([
                        ':id' => (string) $existingSeed['id'],
                        ':seed_batch_id' => $batchId,
                        ':row_no' => (int) ($row['_row_no'] ?? 0),
                        ':raw_json' => $rawJson,
                        ':parsed_json' => $parsedJson,
                        ':process_status' => $processStatus,
                        ':error_message' => $errorMessage,
                        ':updated_by' => $actor,
                    ]);
                    if ($processStatus === 'ERROR') {
                        $errorCount++;
                    }
                    continue;
                }
                $newCount++;
                $insertSeedRow->execute([
                    ':id' => UuidHelper::generate(),
                    ':seed_batch_id' => $batchId,
                    ':source_type' => $dataType,
                    ':source_key' => $sourceKey,
                    ':row_no' => (int) ($row['_row_no'] ?? 0),
                    ':raw_json' => $rawJson,
                    ':parsed_json' => $parsedJson,
                    ':process_status' => $processStatus,
                    ':error_message' => $errorMessage,
                    ':created_by' => $actor,
                    ':updated_by' => $actor,
                ]);
                if ($processStatus === 'ERROR') {
                    $errorCount++;
                }
            }

            $this->pdo->commit();
            $this->refreshUploadBatchStatus($batchId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $batchId,
            'file_name' => $fileName,
            'data_type' => $dataType,
            'format_id' => (string) ($format['id'] ?? ''),
            'total_rows' => count($rows),
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'error_count' => $errorCount,
        ];
    }

    private function uploadStatusFromValidation(array $validation): string
    {
        $status = (string) ($validation['status'] ?? 'ok');
        if ($status === 'error') {
            return 'ERROR';
        }
        if ($status === 'warning') {
            return 'WARNING';
        }
        return 'VALID';
    }

    private function storeUploadPreviewSession(array $format, array $file, array $rows): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = UuidHelper::generate();
        $_SESSION['ledger_upload_previews'][$token] = [
            'format' => $format,
            'file' => [
                'name' => (string) ($file['name'] ?? 'upload'),
                'size' => (int) ($file['size'] ?? 0),
                'type' => (string) ($file['type'] ?? ''),
            ],
            'rows' => $rows,
            'created_at' => time(),
        ];

        return $token;
    }

    private function uploadPreviewFromSession(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $preview = $_SESSION['ledger_upload_previews'][$token] ?? null;
        if (!is_array($preview)) {
            return null;
        }

        return $preview;
    }

    private function clearUploadPreviewSession(string $token): void
    {
        if ($token === '') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['ledger_upload_previews'][$token]);
    }

    private function uploadValidationSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'ok' => 0,
            'warning' => 0,
            'error' => 0,
            'new' => 0,
            'updated' => 0,
            'unchanged' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['_validation']['status'] ?? 'ok');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            $action = strtolower((string) ($row['_seed_action'] ?? 'new'));
            if (isset($summary[$action])) {
                $summary[$action]++;
            }
        }

        return $summary;
    }

    private function validateUploadFileColumns(array $file, array $columns): array
    {
        $spreadsheet = $this->loadUploadedSpreadsheet($file);
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $sheet->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        $spreadsheet->disconnectWorksheets();

        $checks = [];
        $seenExcelNames = [];
        foreach ($columns as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $sheetColumn = $this->sheetColumnFromFormatColumn($column);
            $actualName = $sheetColumn !== null ? trim((string) ($headerRow[$sheetColumn] ?? '')) : '';

            if ($excelName !== '') {
                $key = function_exists('mb_strtolower') ? mb_strtolower($excelName) : strtolower($excelName);
                $seenExcelNames[$key] = ($seenExcelNames[$key] ?? 0) + 1;
            }

            if ($systemField === '') {
                $checks[] = ['level' => 'warning', 'message' => "{$excelName} 컬럼은 업로드 표준필드에 매핑되지 않았습니다."];
            }
            if (!empty($column['is_required']) && $actualName === '') {
                $checks[] = ['level' => 'error', 'message' => "필수컬럼 누락: {$excelName}"];
                continue;
            }
            if ($excelName !== '' && $actualName !== '' && $excelName !== $actualName) {
                $checks[] = ['level' => 'warning', 'message' => "컬럼명 불일치: {$excelName} 위치에 {$actualName}"];
            }
            if ($excelName !== '' && $actualName !== '') {
                $checks[] = ['level' => 'ok', 'message' => "{$excelName} 매핑 성공"];
            }
        }

        foreach ($seenExcelNames as $name => $count) {
            if ($count > 1) {
                $checks[] = ['level' => 'error', 'message' => "중복컬럼 설정: {$name}"];
            }
        }

        return $checks;
    }

    private function annotateSeedComparison(array $rows, string $dataType): array
    {
        foreach ($rows as &$row) {
            $parsed = $this->mappedPayloadForStorage($row);
            $sourceKey = $this->seedSourceKey($parsed, $dataType);
            $row['_seed_key'] = $sourceKey;
            $row['_seed_action'] = 'NEW';
            if ($sourceKey === null) {
                continue;
            }

            $existing = $this->findExistingSeedRow($dataType, $sourceKey);
            if (!$existing) {
                continue;
            }

            $rawJson = $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : []);
            $parsedJson = $this->jsonEncodeForStorage($parsed);
            $row['_seed_action'] = ((string) ($existing['raw_json'] ?? '') === $rawJson && (string) ($existing['parsed_json'] ?? '') === $parsedJson)
                ? 'UNCHANGED'
                : 'UPDATED';
        }
        unset($row);

        return $rows;
    }

    private function seedSourceKey(array $row, string $dataType): ?string
    {
        $dataType = self::normalizeDataType($dataType);
        $parts = [];
        if ($dataType === 'TAX_INVOICE') {
            $parts = [
                $row['approval_number'] ?? '',
                $this->normalizeBusinessNumber((string) ($row['supplier_business_number'] ?? '')),
                $this->normalizeBusinessNumber((string) ($row['customer_business_number'] ?? '')),
                $this->dateValue($row['issue_date'] ?? $row['transaction_date'] ?? ''),
            ];
        } else {
            $parts = [
                $row['approval_number'] ?? '',
                $this->dateValue($row['transaction_date'] ?? $row['issue_date'] ?? ''),
                $this->normalizeBusinessNumber((string) ($row['supplier_business_number'] ?? $row['customer_business_number'] ?? $row['client_business_number'] ?? '')),
                $this->number($row['total_amount'] ?? 0),
                trim((string) ($row['description'] ?? '')),
            ];
        }

        $parts = array_map(static fn(mixed $value): string => trim((string) $value), $parts);
        if (implode('', $parts) === '') {
            return null;
        }

        return hash('sha256', $dataType . '|' . implode('|', $parts));
    }

    private function findExistingSeedRow(string $sourceType, string $sourceKey): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, raw_json, parsed_json, process_status, transaction_id
            FROM ledger_data_seed_rows
            WHERE source_type = :source_type
              AND source_key = :source_key
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':source_type' => self::normalizeDataType($sourceType),
            ':source_key' => $sourceKey,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function mappedPayloadForStorage(array $row): array
    {
        $mapped = [];
        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            $mapped[$key] = $value;
        }
        return $mapped;
    }

    private function jsonEncodeForStorage(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function seedRowIdsFromPayload(array $payload): array
    {
        $ids = [];
        if (is_array($payload['seed_row_ids'] ?? null)) {
            $ids = array_merge($ids, $payload['seed_row_ids']);
        }
        if (is_array($payload['ids'] ?? null)) {
            $ids = array_merge($ids, $payload['ids']);
        }
        foreach (['seed_row_id', 'id'] as $key) {
            if (!empty($payload[$key])) {
                $ids[] = $payload[$key];
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    private function formatIdsFromPayload(array $payload): array
    {
        $ids = [];
        if (is_array($payload['format_ids'] ?? null)) {
            $ids = array_merge($ids, $payload['format_ids']);
        }
        if (is_array($payload['ids'] ?? null)) {
            $ids = array_merge($ids, $payload['ids']);
        }
        foreach (['format_id', 'id'] as $key) {
            if (!empty($payload[$key])) {
                $ids[] = $payload[$key];
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    private function seedRowFiltersFromRequest(): array
    {
        $decoded = [];
        if (!empty($_GET['filters'])) {
            $json = json_decode((string) $_GET['filters'], true);
            $decoded = is_array($json) ? $json : [];
        }

        return array_values(array_filter($decoded, static function ($filter): bool {
            return is_array($filter) && !empty($filter['field']);
        }));
    }

    private function seedRowMatchesFilters(array $row, array $filters): bool
    {
        foreach ($filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            $actual = $this->seedRowFilterValue($row, $field);
            if (is_array($value)) {
                $start = (string) ($value['start'] ?? '');
                $end = (string) ($value['end'] ?? '');
                $text = (string) $actual;
                if ($start !== '' && $text < $start) {
                    return false;
                }
                if ($end !== '' && $text > $end) {
                    return false;
                }
                continue;
            }

            $needles = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
            if ($needles === []) {
                continue;
            }

            $haystack = mb_strtolower((string) $actual);
            $matched = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function seedRowFilterValue(array $row, string $field): string
    {
        if ($field === 'source_type') {
            $value = (string) ($row['source_type'] ?? '');
            return trim($value . ' ' . (string) ($row['source_type_name'] ?? '') . ' ' . self::sourceTypeLabel($value));
        }

        if ($field === 'import_type') {
            $value = (string) ($row['import_type'] ?? $row['seed_source_type'] ?? '');
            return trim($value . ' ' . (string) ($row['import_type_name'] ?? '') . ' ' . self::importTypeLabel($value));
        }

        if ($field === 'client_name') {
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            return (string) (
                $mapped['client_company_name']
                ?? $mapped['client_business_number']
                ?? $mapped['supplier_company_name']
                ?? $mapped['customer_company_name']
                ?? ''
            );
        }

        if (str_starts_with($field, 'mapped_payload.')) {
            $key = substr($field, strlen('mapped_payload.'));
            $mapped = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
            return (string) ($mapped[$key] ?? '');
        }

        return (string) ($row[$field] ?? '');
    }

    private function placeholdersForIds(array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    private function uploadBatch(string $batchId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, b.source_type AS data_type, f.format_name
            FROM ledger_data_seed_batches b
            LEFT JOIN ledger_data_formats f ON f.id = b.format_id
            WHERE b.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $batchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function uploadRowsForTransactionCreate(string $batchId, array $rowIds = []): array
    {
        return $this->seedRowsForTransactionCreate($batchId, $rowIds);
    }

    private function claimSeedRowsForTransactionCreate(string $batchId = '', array $rowIds = []): array
    {
        $params = [];
        $where = ["process_status = 'READY'", 'transaction_id IS NULL', 'deleted_at IS NULL'];
        if ($batchId !== '') {
            $where[] = 'seed_batch_id = :batch_id';
            $params[':batch_id'] = $batchId;
        }
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $where[] = 'id IN (' . implode(', ', $placeholders) . ')';
        }

        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare("
                SELECT id
                FROM ledger_data_seed_rows
                WHERE " . implode(' AND ', $where) . "
                ORDER BY row_no ASC
                FOR UPDATE
            ");
            $select->execute($params);
            $ids = array_map('strval', $select->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids === []) {
                $this->pdo->commit();
                return [];
            }

            $idParams = [];
            $idPlaceholders = [];
            foreach ($ids as $index => $id) {
                $key = ':claim_id_' . $index;
                $idPlaceholders[] = $key;
                $idParams[$key] = $id;
            }
            $idParams[':actor'] = ActorHelper::user();
            $update = $this->pdo->prepare("
                UPDATE ledger_data_seed_rows
                SET process_status = 'PROCESSING',
                    error_message = NULL,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE id IN (" . implode(', ', $idPlaceholders) . ")
                  AND process_status = 'READY'
                  AND transaction_id IS NULL
            ");
            $update->execute($idParams);
            $this->pdo->commit();

            return $this->seedRowsForTransactionCreate('', $ids, 'PROCESSING');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function seedRowsForTransactionCreate(string $batchId = '', array $rowIds = [], string $status = 'READY'): array
    {
        // TODO: Add an explicit ERROR -> READY retry API/button for seed rows.
        $params = [];
        $where = ['process_status = :process_status', 'transaction_id IS NULL', 'deleted_at IS NULL'];
        $params[':process_status'] = $status;
        if ($batchId !== '') {
            $where[] = 'seed_batch_id = :batch_id';
            $params[':batch_id'] = $batchId;
        }
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $where[] = 'id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                seed_batch_id AS batch_id,
                source_type,
                row_no,
                raw_json AS raw_payload,
                parsed_json AS mapped_payload,
                process_status AS status,
                error_message,
                transaction_id
            FROM ledger_data_seed_rows
            WHERE " . implode(' AND ', $where) . "
            ORDER BY row_no ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countTransactionLines(array $transactionIds): int
    {
        $transactionIds = array_values(array_filter(array_map('strval', $transactionIds)));
        if ($transactionIds === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($transactionIds as $index => $transactionId) {
            $key = ':transaction_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $transactionId;
        }

        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM ledger_transaction_lines
            WHERE transaction_id IN (' . implode(', ', $placeholders) . ')
              AND deleted_at IS NULL
        ');
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function updateUploadRowStatus(string $rowId, string $status, ?string $message, ?string $transactionId = null): void
    {
        $processStatus = match ($status) {
            'CREATED', 'PROCESSED' => 'PROCESSED',
            'DUPLICATE', 'DUPLICATED' => 'DUPLICATED',
            'PROCESSING' => 'PROCESSING',
            'ERROR' => 'ERROR',
            default => 'READY',
        };

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_seed_rows
            SET process_status = :status,
                error_message = :error_message,
                transaction_id = COALESCE(:transaction_id, transaction_id),
                processed_at = CASE WHEN :status_processed = 'PROCESSED' THEN NOW() ELSE processed_at END,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $rowId,
            ':status' => $processStatus,
            ':status_processed' => $processStatus,
            ':error_message' => $message,
            ':transaction_id' => $transactionId !== '' ? $transactionId : null,
            ':updated_by' => ActorHelper::user(),
        ]);
    }

    private function refreshUploadBatchStatus(string $batchId): void
    {
        if ($batchId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_seed_batches
            SET total_rows = (
                    SELECT COUNT(*)
                    FROM ledger_data_seed_rows
                    WHERE seed_batch_id = :count_batch_id
                ),
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $batchId,
            ':count_batch_id' => $batchId,
            ':updated_by' => ActorHelper::user(),
        ]);
    }

    private function format(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_data_formats WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            $row['data_type'] = self::normalizeDataType((string) ($row['data_type'] ?? ''));
        }

        return $row;
    }

    private function activeFormatCount(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM ledger_data_formats WHERE deleted_at IS NULL')
            ->fetchColumn();
    }

    private function formatWithColumns(string $id): ?array
    {
        $format = $this->format($id);
        if (!$format) {
            return null;
        }
        $format['columns'] = $this->columns($id);
        return $format;
    }

    private function columns(string $formatId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_data_format_columns
            WHERE format_id = :format_id
              AND (system_field_name IS NULL OR system_field_name <> 'tax_type')
            ORDER BY column_order ASC
        ");
        $stmt->execute([':format_id' => $formatId]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_filter($columns, static function (array $column): bool {
            $field = trim((string) ($column['system_field_name'] ?? ''));
            return $field === '' || self::isImportField($field);
        }));
    }

    private function normalizeColumns(array $columns): array
    {
        $rows = [];
        $usedFields = [];
        foreach (array_values($columns) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }

            $excelColumn = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $systemField = $systemField === '' ? null : $systemField;

            if ($excelColumn === '') {
                continue;
            }
            if ($systemField !== null && !self::isImportField($systemField)) {
                continue;
            }
            if ($systemField !== null) {
                if (isset($usedFields[$systemField])) {
                    throw new \RuntimeException('시스템 필드는 중복 선택할 수 없습니다: ' . self::fieldLabel($systemField));
                }
                $usedFields[$systemField] = true;
            }

            $rows[] = [
                'excel_column_name' => $excelColumn,
                'excel_column_index' => (int) ($column['excel_column_index'] ?? ($index + 1)),
                'system_field_name' => $systemField,
                'column_order' => (int) ($column['column_order'] ?? ($index + 1)),
                'is_required' => !empty($column['is_required']) ? 1 : 0,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $a['column_order'] <=> $b['column_order']);
        foreach ($rows as $index => &$row) {
            $row['column_order'] = $index + 1;
            $row['excel_column_index'] = $index + 1;
        }
        unset($row);

        return $rows;
    }
    private function parseUploadedRows(array $file, array $columns): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('업로드 파일을 읽을 수 없습니다.');
        }

        $spreadsheet = $this->loadUploadedSpreadsheet($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        if (count($rawRows) < 2) {
            return [];
        }

        array_shift($rawRows);

        $mappedColumns = [];
        foreach ($columns as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            $sheetColumn = $this->sheetColumnFromFormatColumn($column);
            if ($excelName === '' || $sheetColumn === null) {
                if (!empty($column['is_required'])) {
                    throw new \RuntimeException("필수 컬럼이 없습니다: {$excelName}");
                }
                continue;
            }
            if ($systemField === '') {
                continue;
            }
            $mappedColumns[] = [
                'sheet_column' => $sheetColumn,
                'system_field_name' => $systemField,
            ];
        }

        $rows = [];
        foreach (array_values($rawRows) as $rowIndex => $rawRow) {
            $rawPayload = [];
            foreach ($columns as $column) {
                $sheetColumn = $this->sheetColumnFromFormatColumn($column);
                if ($sheetColumn === null) {
                    continue;
                }
                $index = (int) ($column['excel_column_index'] ?? $column['column_order'] ?? 0);
                $rawPayload[(string) $index] = [
                    'column_index' => $index,
                    'column_name' => trim((string) ($column['excel_column_name'] ?? '')),
                    'value' => $this->cellValue($rawRow[$sheetColumn] ?? null),
                ];
            }
            $mapped = [];
            foreach ($mappedColumns as $column) {
                $mapped[$column['system_field_name']] = $this->cellValue($rawRow[$column['sheet_column']] ?? null);
            }
            if (implode('', array_map(static fn($value): string => trim((string) $value), $mapped)) === '') {
                continue;
            }
            $mapped['_row_no'] = $rowIndex + 2;
            $mapped['_raw_payload'] = $rawPayload;
            $rows[] = $mapped;
        }

        return $rows;
    }

    private function sheetColumnFromFormatColumn(array $column): ?string
    {
        $index = (int) ($column['excel_column_index'] ?? $column['column_order'] ?? 0);
        if ($index < 1) {
            return null;
        }

        return Coordinate::stringFromColumnIndex($index);
    }

    private function loadUploadedSpreadsheet(array $file): Spreadsheet
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = strtolower((string) ($file['type'] ?? ''));
        $isCsv = $extension === 'csv' || str_contains($mime, 'csv');

        if (!$isCsv) {
            return IOFactory::load($tmpName);
        }

        $reader = IOFactory::createReader('Csv');
        if (method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding($this->detectCsvEncoding($tmpName));
        }
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        return $reader->load($tmpName);
    }

    private function detectCsvEncoding(string $path): string
    {
        $sample = file_get_contents($path, false, null, 0, 8192);
        if ($sample === false || $sample === '') {
            return 'UTF-8';
        }

        if (str_starts_with($sample, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($sample, 'UTF-8')) {
            return 'UTF-8';
        }

        foreach (['CP949', 'EUC-KR'] as $encoding) {
            if (function_exists('mb_check_encoding') && mb_check_encoding($sample, $encoding)) {
                return $encoding;
            }
        }

        return 'CP949';
    }

    private function enrichUploadRows(array $rows, string $dataType): array
    {
        foreach ($rows as &$row) {
            $this->normalizeUploadAmountFields($row);
            $context = $this->resolveUploadTransactionContext($row, $dataType);
            foreach ($context as $key => $value) {
                if ($value !== null && $value !== '') {
                    $row[$key] = $value;
                }
            }
        }
        unset($row);

        return $rows;
    }

    private function normalizeUploadAmountFields(array &$row): void
    {
        foreach (['supply_amount', 'vat_amount', 'total_amount', 'item_supply_amount', 'item_vat_amount', 'item_price', 'item_qty'] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $amount = $this->amountOrNull($row[$field]);
            if ($amount !== null) {
                $row[$field] = $amount;
            }
        }

        $supply = $this->amountOrNull($row['supply_amount'] ?? null);
        $vat = $this->amountOrNull($row['vat_amount'] ?? null);
        $total = $this->amountOrNull($row['total_amount'] ?? null);
        $itemSupply = $this->amountOrNull($row['item_supply_amount'] ?? null);
        $itemVat = $this->amountOrNull($row['item_vat_amount'] ?? null);

        if ($supply === null && $itemSupply !== null) {
            $row['supply_amount'] = $itemSupply;
            $supply = $itemSupply;
        }
        if ($vat === null && $itemVat !== null) {
            $row['vat_amount'] = $itemVat;
            $vat = $itemVat;
        }
        if ($total === null && ($supply !== null || $vat !== null)) {
            $row['total_amount'] = (float) ($supply ?? 0) + (float) ($vat ?? 0);
        }
    }

    private function resolveUploadTransactionContext(array $row, string $dataType): array
    {
        $dataType = self::normalizeDataType($dataType);
        $supplier = $this->partyFromRow($row, 'supplier');
        $customer = $this->partyFromRow($row, 'customer', 'recipient');
        $legacyClient = [
            'business_number' => $this->normalizeBusinessNumber((string) ($row['client_business_number'] ?? $row['business_number'] ?? '')),
            'company_name' => $this->cleanCompanyName((string) ($row['client_company_name'] ?? $row['company_name'] ?? '')),
        ];

        $supplierIsOwn = $this->isOwnCompanyParty($supplier);
        $customerIsOwn = $this->isOwnCompanyParty($customer);
        $direction = $this->normalizeTransactionDirection((string) ($row['transaction_direction'] ?? ''));
        $error = null;

        if ($customerIsOwn && !$supplierIsOwn) {
            $direction = 'PURCHASE';
            $client = $supplier;
            $own = $customer;
        } elseif ($supplierIsOwn && !$customerIsOwn) {
            $direction = 'SALES';
            $client = $customer;
            $own = $supplier;
        } else {
            if ($direction === '') {
                $direction = match ($dataType) {
                    'CARD_APPROVAL', 'CASH_RECEIPT' => 'PURCHASE',
                    default => '',
                };
            }
            $client = $legacyClient;
            $own = $this->ownCompanyDefaultParty();

            if ($direction === 'PURCHASE' && $supplier['company_name'] . $supplier['business_number'] !== '') {
                $client = $supplier;
            } elseif ($direction === 'SALES' && $customer['company_name'] . $customer['business_number'] !== '') {
                $client = $customer;
            } elseif ($legacyClient['company_name'] . $legacyClient['business_number'] === '') {
                $client = $direction === 'SALES' ? $customer : $supplier;
            }

            if ($dataType === 'TAX_INVOICE'
                && ($supplier['company_name'] . $supplier['business_number'] !== '' || $customer['company_name'] . $customer['business_number'] !== '')
                && !$supplierIsOwn
                && !$customerIsOwn
            ) {
                $error = '우리회사 식별 실패: 공급자/공급받는자 중 우리회사와 일치하는 값이 없습니다.';
            }
        }

        if ($direction === '') {
            $direction = $dataType === 'BANK_TRANSACTION' ? 'BANK' : 'GENERAL';
        }

        // 개인/미기재 상대방은 Seed 검토 단계에서 보완할 수 있게 거래처 필수 오류로 막지 않는다.
        if (false && $error === null
            && in_array($direction, ['PURCHASE', 'SALES'], true)
            && (($client['company_name'] ?? '') . ($client['business_number'] ?? '')) === ''
        ) {
            $error = '상대 거래처 식별 실패: 거래처로 사용할 공급자/공급받는자 값이 없습니다.';
        }

        return [
            'transaction_direction' => $direction,
            'transaction_type' => $this->transactionTypeForUpload($dataType),
            'client_business_number' => $client['business_number'] ?? '',
            'client_company_name' => $client['company_name'] ?? '',
            'own_business_number' => $own['business_number'] ?? '',
            'own_company_name' => $own['company_name'] ?? '',
            '_direction_error' => $error,
        ];
    }

    private function transactionTypeForUpload(string $dataType): string
    {
        return match (self::normalizeDataType($dataType)) {
            'IMPORT_INVOICE' => 'TRADE_IMPORT',
            'SHOPPING_ORDER' => 'SHOPPING',
            'TAX_INVOICE', 'CASH_RECEIPT', 'CARD_APPROVAL', 'BANK_TRANSACTION', 'ETC' => 'GENERAL',
            default => 'GENERAL',
        };
    }

    private function partyFromRow(array $row, string $prefix, ?string $fallbackPrefix = null): array
    {
        $businessNumber = (string) ($row[$prefix . '_business_number'] ?? '');
        $companyName = (string) ($row[$prefix . '_company_name'] ?? '');
        if ($fallbackPrefix !== null) {
            $businessNumber = $businessNumber !== '' ? $businessNumber : (string) ($row[$fallbackPrefix . '_business_number'] ?? '');
            $companyName = $companyName !== '' ? $companyName : (string) ($row[$fallbackPrefix . '_company_name'] ?? '');
        }

        return [
            'business_number' => $this->normalizeBusinessNumber($businessNumber),
            'company_name' => $this->cleanCompanyName($companyName),
        ];
    }

    private function ownCompanyDefaultParty(): array
    {
        $profile = $this->ownCompanyProfile();
        return [
            'business_number' => $profile['business_numbers'][0] ?? '',
            'company_name' => $profile['company_names'][0] ?? '',
        ];
    }

    private function isOwnCompanyParty(array $party): bool
    {
        $profile = $this->ownCompanyProfile();
        $businessNumber = $this->normalizeBusinessNumber((string) ($party['business_number'] ?? ''));
        if ($businessNumber !== '' && in_array($businessNumber, $profile['business_numbers'], true)) {
            return true;
        }

        $companyName = $this->normalizeCompanyNameForCompare((string) ($party['company_name'] ?? ''));
        return $companyName !== '' && in_array($companyName, $profile['company_names'], true);
    }

    private function ownCompanyProfile(): array
    {
        if ($this->ownCompanyProfile !== null) {
            return $this->ownCompanyProfile;
        }

        $company = (new CompanyModel($this->pdo))->getOne() ?? [];
        $businessNumbers = [];
        foreach (['biz_number', 'business_no', 'business_number'] as $key) {
            $value = $this->normalizeBusinessNumber((string) ($company[$key] ?? ''));
            if ($value !== '') {
                $businessNumbers[] = $value;
            }
        }

        $companyNames = [];
        foreach (['company_name_ko', 'company_name_en', 'company_name'] as $key) {
            $value = $this->normalizeCompanyNameForCompare((string) ($company[$key] ?? ''));
            if ($value !== '') {
                $companyNames[] = $value;
            }
        }

        $this->ownCompanyProfile = [
            'business_numbers' => array_values(array_unique($businessNumbers)),
            'company_names' => array_values(array_unique($companyNames)),
        ];

        return $this->ownCompanyProfile;
    }

    private function normalizeTransactionDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        return match ($direction) {
            'PURCHASE', 'BUY', '매입' => 'PURCHASE',
            'SALES', 'SALE', 'SELL', '매출' => 'SALES',
            'IN', 'DEPOSIT', 'RECEIPT', '입금' => 'IN',
            'OUT', 'WITHDRAWAL', 'PAYMENT', '출금' => 'OUT',
            default => '',
        };
    }

    private function normalizeCompanyNameForCompare(string $companyName): string
    {
        $companyName = $this->cleanCompanyName($companyName);
        $companyName = preg_replace('/\s+/u', '', $companyName) ?? $companyName;
        return function_exists('mb_strtolower') ? mb_strtolower($companyName, 'UTF-8') : strtolower($companyName);
    }

    private function validatePreviewRows(array $rows, array $columns): array
    {
        return $this->validatePreviewRowsV2($rows, $columns);
    }

    private function isValidDateValue(mixed $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return strtotime($value) !== false;
    }

    private function isValidAmountValue(mixed $value): bool
    {
        $amount = $this->amountOrNull($value);
        return $amount !== null;
    }

    private function validatePreviewRowsV2(array $rows, array $columns): array
    {
        $requiredFields = [];
        foreach ($columns as $column) {
            if (!empty($column['is_required'])) {
                $requiredFields[] = (string) ($column['system_field_name'] ?? '');
            }
        }

        foreach ($rows as &$row) {
            $errors = [];
            $warnings = [];

            foreach ($requiredFields as $field) {
                if ($field !== '' && !$this->isMissingAllowedUploadField($field, $row) && trim((string) ($row[$field] ?? '')) === '') {
                    $errors[] = self::fieldLabel($field) . ' 필수값 없음';
                }
            }

            $date = trim((string) ($row['transaction_date'] ?? ''));
            if ($date !== '' && !$this->isValidDateValue($date)) {
                $errors[] = '날짜 형식 오류';
            }

            foreach (['supply_amount', 'vat_amount', 'total_amount', 'item_supply_amount', 'item_vat_amount'] as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value !== '' && !$this->isValidAmountValue($value)) {
                    $errors[] = self::fieldLabel($field) . ' 금액 오류';
                }
            }

            if (!empty($row['_direction_error'])) {
                $errors[] = (string) $row['_direction_error'];
            }

            $businessNumber = $this->normalizeBusinessNumber((string) ($row['client_business_number'] ?? $row['business_number'] ?? ''));
            $companyName = $this->cleanCompanyName((string) ($row['client_company_name'] ?? $row['company_name'] ?? ''));
            if ($businessNumber !== '' && !$this->clientExistsByBusinessNumber($businessNumber)) {
                $warnings[] = '거래처 신규 생성 예정';
            } elseif ($businessNumber === '' && $companyName !== '' && $this->findClientId($companyName) === null) {
                $warnings[] = '거래처 미매칭';
            } elseif ($businessNumber === '' && $companyName === '') {
                $warnings[] = '거래처 상호 미기재';
            }

            $status = 'ok';
            if ($errors !== []) {
                $status = 'error';
            } elseif ($warnings !== []) {
                $status = 'warning';
            }

            $row['_validation'] = [
                'status' => $status,
                'label' => ['ok' => '정상', 'warning' => '경고', 'error' => '오류'][$status],
                'messages' => array_values(array_merge($errors, $warnings)),
            ];
        }
        unset($row);

        return $rows;
    }

    private function isMissingAllowedUploadField(string $field, array $row): bool
    {
        if ($field !== 'customer_company_name') {
            return false;
        }

        return trim((string) ($row['customer_business_number'] ?? '')) !== ''
            || trim((string) ($row['customer_email_1'] ?? '')) !== ''
            || trim((string) ($row['customer_email_2'] ?? '')) !== ''
            || trim((string) ($row['approval_number'] ?? '')) !== ''
            || trim((string) ($row['total_amount'] ?? '')) !== ''
            || trim((string) ($row['supply_amount'] ?? '')) !== '';
    }

    private function transactionPayload(array $row, string $dataType): array
    {
        $context = $this->resolveUploadTransactionContext($row, $dataType);
        if (!empty($context['_direction_error'])) {
            throw new \RuntimeException((string) $context['_direction_error']);
        }

        $supplyRaw = $this->amountOrNull($row['supply_amount'] ?? null);
        $vatRaw = $this->amountOrNull($row['vat_amount'] ?? null);
        $totalRaw = $this->amountOrNull($row['total_amount'] ?? null);
        $supply = (float) ($supplyRaw ?? $totalRaw ?? 0);
        $vat = (float) ($vatRaw ?? 0);
        $total = (float) ($totalRaw ?? ($supply + $vat));
        if (($supplyRaw === null || $supply == 0.0) && $totalRaw !== null && $total != 0.0) {
            $supply = $total - $vat;
        }
        $itemSupply = $supply;
        $itemVat = $vat;
        $itemTotal = $total != 0.0 ? $total : ($itemSupply + $itemVat);
        $itemQty = 1.0;
        $itemPrice = $itemSupply;
        $taxType = abs($itemVat) > 0 ? 'TAXABLE' : 'EXEMPT';
        $note = trim((string) ($row['note'] ?? ''));

        return [
            'transaction_date' => $this->dateValue($row['transaction_date'] ?? date('Y-m-d')),
            'business_unit' => $this->businessUnitForUpload($row, $dataType),
            'transaction_type' => (string) ($context['transaction_type'] ?? 'GENERAL'),
            'transaction_direction' => $this->transactionDirectionForStorage((string) ($context['transaction_direction'] ?? ''), $row, $dataType),
            'import_type' => self::normalizeDataType($dataType),
            'client_id' => $this->findOrCreateClient(
                (string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''),
                (string) ($context['client_company_name'] ?? $row['client_company_name'] ?? $row['company_name'] ?? '')
            ),
            'project_id' => $this->findProjectId((string) ($row['project_name'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'supply_amount' => $supply,
            'vat_amount' => $vat,
            'total_amount' => $total,
            'status' => 'draft',
            'match_status' => 'none',
            'note' => $note !== '' ? $note : null,
            'memo' => trim((string) ($row['memo'] ?? '')) ?: null,
            'items' => [[
                'item_date' => $this->dateValue($row['item_date'] ?? $row['transaction_date'] ?? date('Y-m-d')),
                'item_name' => trim((string) ($row['item_name'] ?? $row['description'] ?? '업로드 자료')) ?: '업로드 자료',
                'specification' => trim((string) ($row['item_spec'] ?? '')) ?: null,
                'unit_name' => '식',
                'quantity' => $itemQty,
                'unit_price' => $itemPrice,
                'supply_amount' => $itemSupply,
                'vat_amount' => $itemVat,
                'total_amount' => $itemTotal,
                'tax_type' => $taxType,
                'description' => trim((string) ($row['item_note'] ?? $row['description'] ?? '')) ?: null,
            ]],
        ];
    }

    private function hasDuplicateTransaction(array $row, string $uploadRowId, string $dataType): bool
    {
        $approvalNo = trim((string) ($row['approval_number'] ?? $row['approval_no'] ?? ''));
        if ($approvalNo !== '') {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_data_seed_rows
                WHERE id <> :row_id
                  AND process_status = 'PROCESSED'
                  AND parsed_json LIKE :approval_no
                LIMIT 1
            ");
            $stmt->execute([
                ':row_id' => $uploadRowId,
                ':approval_no' => '%' . $approvalNo . '%',
            ]);
            if ($stmt->fetchColumn()) {
                return true;
            }

            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM ledger_transactions
                WHERE deleted_at IS NULL
                  AND (note LIKE :approval_note OR memo LIKE :approval_memo OR description LIKE :approval_description)
                LIMIT 1
            ");
            $approvalLike = '%' . $approvalNo . '%';
            $stmt->execute([
                ':approval_note' => $approvalLike,
                ':approval_memo' => $approvalLike,
                ':approval_description' => $approvalLike,
            ]);
            return (bool) $stmt->fetchColumn();
        }

        $rawDate = trim((string) ($row['transaction_date'] ?? ''));
        if ($rawDate === '') {
            return false;
        }
        $date = $this->dateValue($rawDate);
        $total = $this->number($row['total_amount'] ?? $row['supply_amount'] ?? 0);
        if ($date === '' || abs($total) <= 0) {
            return false;
        }

        $context = $this->resolveUploadTransactionContext($row, $dataType);
        $businessNumber = $this->normalizeBusinessNumber((string) ($context['client_business_number'] ?? $row['client_business_number'] ?? $row['business_number'] ?? ''));
        $companyName = $this->cleanCompanyName((string) ($context['client_company_name'] ?? $row['client_company_name'] ?? $row['company_name'] ?? ''));
        $clientId = null;
        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM system_clients
                WHERE business_number = :business_number
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':business_number' => $businessNumber]);
            $clientId = $stmt->fetchColumn() ?: null;
        }
        if ($clientId === null && $companyName !== '') {
            $clientId = $this->findClientId($companyName);
        }

        $sql = "
            SELECT 1
            FROM ledger_transactions
            WHERE deleted_at IS NULL
              AND transaction_date = :transaction_date
              AND total_amount = :total_amount
        ";
        $params = [
            ':transaction_date' => $date,
            ':total_amount' => $total,
        ];
        if ($clientId !== null) {
            $sql .= ' AND client_id = :client_id';
            $params[':client_id'] = $clientId;
        } elseif ($companyName !== '') {
            $sql .= ' AND description LIKE :company_name';
            $params[':company_name'] = '%' . $companyName . '%';
        } else {
            return false;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private function businessUnitForUpload(array $row, string $dataType): string
    {
        $value = strtoupper(trim((string) ($row['business_unit'] ?? $row['business_unit_code'] ?? '')));
        if ($value !== '') {
            return $value;
        }

        return match (self::normalizeDataType($dataType)) {
            'SHOPPING_ORDER' => 'ECOMMERCE',
            default => 'HQ',
        };
    }

    private function transactionDirectionForStorage(string $direction, array $row, string $dataType): string
    {
        $direction = strtoupper(trim($direction));
        $dataType = self::normalizeDataType($dataType);

        if ($dataType === 'BANK_TRANSACTION') {
            $bankDirection = strtoupper(trim((string) (
                $row['bank_direction']
                ?? $row['deposit_withdrawal_type']
                ?? $row['transaction_direction']
                ?? ''
            )));
            if (in_array($bankDirection, ['IN', 'DEPOSIT', 'RECEIPT', '입금'], true)) {
                return 'IN';
            }
            if (in_array($bankDirection, ['OUT', 'WITHDRAWAL', 'PAYMENT', '출금'], true)) {
                return 'OUT';
            }
        }

        return match ($direction) {
            'SALES', 'SALE', 'SELL', 'OUT' => 'SALES',
            'PURCHASE', 'BUY', 'IN' => 'PURCHASE',
            'DEPOSIT' => 'IN',
            'WITHDRAWAL', 'PAYMENT' => 'OUT',
            default => $direction !== '' ? $direction : ($dataType === 'BANK_TRANSACTION' ? 'IN' : 'GENERAL'),
        };
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));
        return self::LEGACY_DATA_TYPE_MAP[$type] ?? $type;
    }

    private function isAllowedDataType(string $type): bool
    {
        $type = self::normalizeDataType($type);
        return $type !== '' && in_array($type, $this->allowedDataTypes(), true);
    }

    private function allowedDataTypes(): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = self::DATA_TYPES;
        if (!$this->systemCodesTableExists()) {
            return $types;
        }

        $stmt = $this->pdo->prepare("
            SELECT code
            FROM system_codes
            WHERE deleted_at IS NULL
              AND is_active = 1
              AND code_group = 'IMPORT_TYPE'
            ORDER BY sort_no ASC, code ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $codeTypes = array_values(array_filter(array_map(
            static fn($code): string => self::normalizeDataType((string) $code),
            $rows
        )));

        return $codeTypes !== [] ? array_values(array_unique($codeTypes)) : $types;
    }

    private function systemCodesTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_codes'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private static function normalizeImportSourceType(string $sourceType): string
    {
        $sourceType = strtoupper(trim($sourceType));
        return match ($sourceType) {
            'HOMETAX' => 'TAX',
            'CARD_COMPANY' => 'CARD',
            'BANK_ACCOUNT' => 'BANK',
            'SHOPPING_MALL' => 'SHOPPING',
            'TRADE_IMPORT', 'IMPORT' => 'TRADE',
            default => $sourceType,
        };
    }

    private static function sourceTypeForDataType(string $dataType): string
    {
        return match (self::normalizeDataType($dataType)) {
            'TAX_INVOICE', 'CASH_RECEIPT' => 'TAX',
            'CARD_APPROVAL' => 'CARD',
            'BANK_TRANSACTION' => 'BANK',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => 'MANUAL',
        };
    }

    private static function importTypesForSourceType(string $sourceType): array
    {
        return match (self::normalizeImportSourceType($sourceType)) {
            'TAX' => ['TAX_INVOICE', 'CASH_RECEIPT'],
            'CARD' => ['CARD_APPROVAL'],
            'BANK' => ['BANK_TRANSACTION'],
            'SHOPPING' => ['SHOPPING_ORDER'],
            'TRADE' => ['IMPORT_INVOICE'],
            default => [],
        };
    }

    private static function sourceTypeLabel(string $sourceType): string
    {
        return match (self::normalizeImportSourceType($sourceType)) {
            'TAX' => '홈택스',
            'CARD' => '카드사',
            'BANK' => '은행',
            'SHOPPING' => '쇼핑몰',
            'TRADE' => '수입/무역',
            'MANUAL' => '수기입력',
            default => '',
        };
    }

    private static function importTypeLabel(string $importType): string
    {
        return match (self::normalizeDataType($importType)) {
            'TAX_INVOICE' => '세금계산서',
            'CASH_RECEIPT' => '현금영수증',
            'CARD_APPROVAL' => '카드',
            'BANK_TRANSACTION' => '입출금',
            'SHOPPING_ORDER' => '주문',
            'IMPORT_INVOICE' => '수입인보이스',
            default => '',
        };
    }

    private static function sourceTypeSql(string $column): string
    {
        return "CASE {$column}
            WHEN 'TAX_INVOICE' THEN 'TAX'
            WHEN 'CASH_RECEIPT' THEN 'TAX'
            WHEN 'CARD_APPROVAL' THEN 'CARD'
            WHEN 'BANK_TRANSACTION' THEN 'BANK'
            WHEN 'SHOPPING_ORDER' THEN 'SHOPPING'
            WHEN 'IMPORT_INVOICE' THEN 'TRADE'
            ELSE 'MANUAL'
        END";
    }

    private static function queryDataTypes(string $type): array
    {
        $types = [$type];
        foreach (self::LEGACY_DATA_TYPE_MAP as $legacy => $current) {
            if ($current === $type) {
                $types[] = $legacy;
            }
        }

        return array_values(array_unique($types));
    }

    private static function dataTypeLabel(string $type): string
    {
        return [
            'TAX_INVOICE' => '세금계산서',
            'CASH_RECEIPT' => '현금영수증',
            'CARD_APPROVAL' => '카드',
            'BANK_TRANSACTION' => '입출금',
            'SHOPPING_ORDER' => '주문',
            'IMPORT_INVOICE' => '수입인보이스',
            'ETC' => '기타',
        ][$type] ?? '자료';
    }
    private static function safeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('#[\\/:*?"<>|]+#u', '_', $name) ?? '';
        $name = preg_replace('#[^\p{L}\p{N}_. \-()\[\]]+#u', '_', $name) ?? '';
        $name = preg_replace('#\s+#u', '_', $name) ?? '';
        $name = trim($name, "._- \t\n\r\0\x0B");

        return $name !== '' ? $name : '업로드_양식';
    }

    private function formatTransactionCreateError(string $message, array $row = [], int $rowNo = 0): string
    {
        $message = trim($message);
        $isSqlParameterError = str_contains($message, 'SQLSTATE[HY093]') || str_contains($message, 'Invalid parameter number');

        if ($isSqlParameterError) {
            $clientName = $this->cleanCompanyName((string) (
                $row['client_company_name']
                ?? $row['customer_company_name']
                ?? $row['supplier_company_name']
                ?? $row['company_name']
                ?? ''
            ));
            $approvalNo = trim((string) ($row['approval_number'] ?? $row['approval_no'] ?? ''));

            $parts = [];
            if ($rowNo > 0) {
                $parts[] = $rowNo . '행';
            }
            if ($clientName !== '') {
                $parts[] = '거래처 ' . $clientName;
            }
            if ($approvalNo !== '') {
                $parts[] = '승인번호 ' . $approvalNo;
            }

            $context = $parts !== [] ? ' (' . implode(', ', $parts) . ')' : '';
            return '거래 생성 중 내부 저장 파라미터 오류가 발생했습니다' . $context . '. 중복검사 또는 거래처 저장 단계의 필드 연결을 확인해야 합니다.';
        }

        if (str_contains($message, 'SQLSTATE[')) {
            $prefix = $rowNo > 0 ? $rowNo . '행: ' : '';
            return $prefix . '거래 생성 중 데이터베이스 저장 오류가 발생했습니다. 원본 필드값과 거래처/금액 매핑을 확인해야 합니다.';
        }

        return $message !== '' ? $message : '거래 생성 실패';
    }

    private static function safeSheetTitle(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('#[\\/*?:\[\]]+#u', '_', $title) ?? '';
        $title = preg_replace('#\s+#u', ' ', $title) ?? '';
        $title = trim($title, "' ");
        if ($title === '') {
            $title = '양식';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 31) : substr($title, 0, 31);
    }
    private function findClientId(string $clientName): ?string
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM system_clients WHERE client_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $clientName]);
        return $stmt->fetchColumn() ?: null;
    }

    private function findOrCreateClient(string $businessNumber, string $companyName): ?string
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        $companyName = $this->cleanCompanyName($companyName);

        if ($businessNumber === '' && $companyName === '') {
            return null;
        }

        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare('
                SELECT id, client_name, company_name
                FROM system_clients
                WHERE business_number = :business_number
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([':business_number' => $businessNumber]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($client) {
                $oldCompanyName = $this->cleanCompanyName((string) ($client['company_name'] ?? $client['client_name'] ?? ''));
                if ($companyName !== '' && $oldCompanyName !== '' && $oldCompanyName !== $companyName) {
                    $this->insertClientNameHistory((string) $client['id'], $oldCompanyName, $companyName);
                    $this->updateClientCompanyName((string) $client['id'], $companyName);
                } elseif ($companyName !== '' && $oldCompanyName === '') {
                    $this->updateClientCompanyName((string) $client['id'], $companyName);
                }

                return (string) $client['id'];
            }
        }

        if ($businessNumber === '' && $companyName !== '') {
            $existingId = $this->findClientId($companyName);
            if ($existingId !== null) {
                return $existingId;
            }
        }

        $clientId = UuidHelper::generate();
        $clientName = $companyName !== '' ? $companyName : $businessNumber;
        $created = (new ClientModel($this->pdo))->create([
            'id' => $clientId,
            'sort_no' => SequenceHelper::next('system_clients', 'sort_no'),
            'client_name' => $clientName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'business_number' => $businessNumber !== '' ? $businessNumber : null,
            'registration_date' => date('Y-m-d'),
            'client_type' => 'CLIENT',
            'is_active' => 1,
            'created_by' => ActorHelper::user(),
            'updated_by' => ActorHelper::user(),
        ]);
        if (!$created) {
            throw new \RuntimeException('거래처 자동 생성에 실패했습니다.');
        }

        return $clientId;
    }

    private function clientExistsByBusinessNumber(string $businessNumber): bool
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([':business_number' => $businessNumber]);
        return (bool) $stmt->fetchColumn();
    }

    private function updateClientCompanyName(string $clientId, string $companyName): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE system_clients
            SET client_name = :client_name,
                company_name = :company_name,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $clientId,
            ':client_name' => $companyName,
            ':company_name' => $companyName,
            ':actor' => ActorHelper::user(),
        ]);
    }

    private function insertClientNameHistory(string $clientId, string $oldCompanyName, string $newCompanyName): void
    {
        if (!$this->clientNameHistoryTableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO system_client_name_history
                (id, client_id, old_company_name, new_company_name, changed_at, changed_by)
            VALUES
                (:id, :client_id, :old_company_name, :new_company_name, NOW(), :changed_by)
        ');
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':client_id' => $clientId,
            ':old_company_name' => $oldCompanyName,
            ':new_company_name' => $newCompanyName,
            ':changed_by' => ActorHelper::user(),
        ]);
    }

    private function clientNameHistoryTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_client_name_history'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private function normalizeBusinessNumber(string $businessNumber): string
    {
        return preg_replace('/[^0-9]/', '', $businessNumber) ?? '';
    }

    private function cleanCompanyName(string $companyName): string
    {
        $companyName = trim($companyName);
        $companyName = preg_replace('/\s+/u', ' ', $companyName) ?? $companyName;
        $companyName = preg_replace('/^\s*[\(（]\s*주\s*[\)）]\s*/u', '', $companyName) ?? $companyName;
        $companyName = preg_replace('/\s*[\(（]\s*주\s*[\)）]\s*$/u', '', $companyName) ?? $companyName;
        return trim($companyName);
    }
    private function findProjectId(string $projectName): ?string
    {
        $projectName = trim($projectName);
        if ($projectName === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM system_projects WHERE project_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetchColumn() ?: null;
    }

    private function requestPayload(): array
    {
        $raw = file_get_contents('php://input');
        $json = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : null;
        return is_array($json) ? array_replace_recursive($_POST, $json) : $_POST;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function importFieldOptionsForResponse(): array
    {
        if (!$this->ledgerTransactionImportFieldsTableExists()) {
            return self::importFieldOptions();
        }

        $stmt = $this->pdo->query("
            SELECT field_key, field_group, field_label
            FROM ledger_transaction_import_fields
            WHERE is_active = 1
            ORDER BY sort_no ASC, field_key ASC
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        if ($rows === []) {
            return self::importFieldOptions();
        }

        $optionsByKey = [];
        foreach (self::importFieldOptions() as $option) {
            $optionsByKey[(string) $option['value']] = $option;
        }
        foreach ($rows as $row) {
            $key = (string) $row['field_key'];
            if (!self::isImportField($key)) {
                continue;
            }
            $optionsByKey[$key] = [
                'value' => $key,
                'label' => (string) $row['field_label'],
                'group' => (string) $row['field_group'],
            ];
        }

        return array_values(array_filter(self::importFields(), static fn(string $field): bool => isset($optionsByKey[$field])))
            ? array_map(static fn(string $field): array => $optionsByKey[$field], self::importFields())
            : self::importFieldOptions();
    }

    private function ledgerTransactionImportFieldsTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'ledger_transaction_import_fields'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private static function importFieldOptions(): array
    {
        $options = [];
        foreach (self::IMPORT_FIELD_GROUPS as $group => $fields) {
            foreach ($fields as $field) {
                $options[] = [
                    'value' => $field,
                    'label' => self::fieldLabel($field),
                    'group' => $group,
                ];
            }
        }

        return $options;
    }

    private static function importFields(): array
    {
        return array_values(array_merge(...array_values(self::IMPORT_FIELD_GROUPS)));
    }

    private static function isImportField(string $field): bool
    {
        return in_array($field, self::importFields(), true);
    }

    private static function fieldLabel(string $field): string
    {
        return [
            'project_name' => '사업명',
            'description' => '적요',
            'transaction_date' => '작성일자',
            'approval_number' => '승인번호',
            'issue_date' => '발급일자',
            'transmit_date' => '전송일자',
            'tax_invoice_category' => '전자세금계산서분류',
            'tax_invoice_type' => '전자세금계산서종류',
            'issue_type' => '발급유형',
            'receipt_claim_type' => '영수/청구구분',
            'total_amount' => '합계금액',
            'supply_amount' => '공급가액',
            'vat_amount' => '세액',
            'note' => '비고',
            'supplier_business_number' => '공급자 사업자등록번호',
            'supplier_branch_number' => '공급자 종사업장번호',
            'supplier_company_name' => '공급자 상호',
            'supplier_ceo_name' => '공급자 대표자명',
            'supplier_address' => '공급자 주소',
            'supplier_email' => '공급자 이메일',
            'customer_business_number' => '공급받는자 사업자등록번호',
            'customer_branch_number' => '공급받는자 종사업장번호',
            'customer_company_name' => '공급받는자 상호',
            'customer_ceo_name' => '공급받는자 대표자명',
            'customer_address' => '공급받는자 주소',
            'customer_email_1' => '공급받는자 이메일1',
            'customer_email_2' => '공급받는자 이메일2',
            'broker_business_number' => '수탁사업자등록번호',
            'broker_company_name' => '수탁자 상호',
            'item_date' => '품목일자',
            'item_name' => '품목명',
            'item_spec' => '품목규격',
            'item_qty' => '품목수량',
            'item_price' => '품목단가',
            'item_supply_amount' => '품목공급가액',
            'item_vat_amount' => '품목세액',
            'item_note' => '품목비고',
        ][$field] ?? $field;
    }
    private function cellValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return trim((string) $value);
    }

    private function number(mixed $value): float
    {
        return (float) ($this->amountOrNull($value) ?? 0);
    }

    private function amountOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-' || strcasecmp($text, 'NaN') === 0) {
            return null;
        }

        $text = strtr($text, [
            '−' => '-',
            '–' => '-',
            '—' => '-',
            '－' => '-',
            '﹣' => '-',
            '△' => '-',
        ]);

        $sign = 1;
        if (preg_match('/^\(.+\)$/', $text) === 1) {
            $sign = -1;
            $text = substr($text, 1, -1);
        }

        $normalized = preg_replace('/[,\s₩원]/u', '', $text) ?? '';
        if (str_ends_with($normalized, '-') && strlen($normalized) > 1) {
            $sign *= -1;
            $normalized = rtrim($normalized, '-');
        }

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return is_numeric($normalized) ? ((float) $normalized * $sign) : null;
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $time = strtotime($value);
        return $time === false ? $value : date('Y-m-d', $time);
    }
}
