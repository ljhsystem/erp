<?php

namespace App\Controllers\Ledger;

use App\Models\System\ClientModel;
use App\Services\Ledger\TransactionCrudService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController
{
    private const DATA_TYPES = [
        'TAX_INVOICE',
        'CASH_RECEIPT',
        'CARD_PURCHASE',
        'CARD_SALE',
        'BANK',
        'ETC',
    ];

    private const LEGACY_DATA_TYPE_MAP = [
        'DATA' => 'TAX_INVOICE',
        'TAX' => 'TAX_INVOICE',
        'CARD' => 'CARD_PURCHASE',
    ];

    private const SYSTEM_FIELDS = [
        'transaction_date',
        'business_number',
        'company_name',
        'project_name',
        'description',
        'supply_amount',
        'vat_amount',
        'total_amount',
        'tax_type',
        'note',
        'memo',
    ];

    private PDO $pdo;
    private ?TransactionCrudService $transactionService = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DbPdo::conn();
    }

    public function apiTemplate(): void
    {
        $formatId = trim((string) ($_GET['format_id'] ?? ''));
        if ($formatId !== '') {
            $format = $this->formatWithColumns($formatId);
            if (!$format || empty($format['columns'])) {
                $this->json(['success' => false, 'message' => '?ㅼ슫濡쒕뱶???묒떇??李얠쓣 ???놁뒿?덈떎.'], 404);
                return;
            }

            [$filename, $title, $headers, $samples] = $this->templateSpecFromFormat($format);
            $this->downloadTemplate($filename, $title, $headers, $samples);
            return;
        }

        $type = self::normalizeDataType((string) ($_GET['type'] ?? 'TAX_INVOICE'));
        if (!in_array($type, self::DATA_TYPES, true)) {
            $this->json(['success' => false, 'message' => '吏?먰븯吏 ?딅뒗 ?묒떇 ?좏삎?낅땲??'], 400);
            return;
        }

        [$filename, $title, $headers, $samples] = $this->templateSpec($type);
        $this->downloadTemplate($filename, $title, $headers, $samples);
    }

    private function downloadTemplate(string $filename, string $title, array $headers, array $samples): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($samples, null, 'A2');

        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF6FF');
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $encodedFilename = rawurlencode($filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$encodedFilename}\"; filename*=UTF-8''{$encodedFilename}");
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function apiFieldOptions(): void
    {
        $this->json([
            'success' => true,
            'data' => array_map(static fn(string $field): array => [
                'value' => $field,
                'label' => self::fieldLabel($field),
            ], self::SYSTEM_FIELDS),
        ]);
    }

    public function apiFormats(): void
    {
        $dataType = self::normalizeDataType((string) ($_GET['data_type'] ?? ''));
        $sql = 'SELECT * FROM ledger_data_formats';
        $params = [];
        if ($dataType !== '') {
            if (!in_array($dataType, self::DATA_TYPES, true)) {
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
            $sql .= ' WHERE data_type IN (' . implode(', ', $placeholders) . ')';
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
            $this->json(['success' => false, 'message' => '?묒떇 ID媛 ?놁뒿?덈떎.'], 400);
            return;
        }

        $format = $this->format($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => '?묒떇??李얠쓣 ???놁뒿?덈떎.'], 404);
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

        if ($formatName === '' || !in_array($dataType, self::DATA_TYPES, true)) {
            $this->json(['success' => false, 'message' => '?묒떇紐낃낵 ?먮즺?좏삎???뺤씤?섏꽭??'], 400);
            return;
        }

        $normalizedColumns = $this->normalizeColumns($columns);
        if ($normalizedColumns === []) {
            $this->json(['success' => false, 'message' => '而щ읆 留ㅽ븨??1媛??댁긽 ?낅젰?섏꽭??'], 400);
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
                $this->pdo->prepare('UPDATE ledger_data_formats SET is_default = 0 WHERE data_type IN (' . implode(', ', $placeholders) . ')')
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
                $stmt = $this->pdo->prepare("
                    UPDATE ledger_data_formats
                    SET format_name = :format_name,
                        data_type = :data_type,
                        is_default = :is_default
                    WHERE id = :id
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
                    (id, format_id, excel_column_name, system_field_name, column_order, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :system_field_name, :column_order, :is_required)
            ");
            foreach ($normalizedColumns as $column) {
                $insert->execute([
                    ':id' => UuidHelper::generate(),
                    ':format_id' => $id,
                    ':excel_column_name' => $column['excel_column_name'],
                    ':system_field_name' => $column['system_field_name'],
                    ':column_order' => $column['column_order'],
                    ':is_required' => $column['is_required'],
                ]);
            }

            $this->pdo->commit();
            $this->json(['success' => true, 'id' => $id, 'message' => '?묒떇????λ릺?덉뒿?덈떎.']);
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
            $this->json(['success' => false, 'message' => '?묒떇 ID媛 ?놁뒿?덈떎.'], 400);
            return;
        }
        $this->pdo->prepare('DELETE FROM ledger_data_formats WHERE id = :id')->execute([':id' => $id]);
        $this->json(['success' => true, 'message' => '?묒떇????젣?섏뿀?듬땲??']);
    }

    public function apiFormatCopy(): void
    {
        $id = trim((string) ($this->requestPayload()['id'] ?? ''));
        $format = $this->formatWithColumns($id);
        if (!$format) {
            $this->json(['success' => false, 'message' => '蹂듭궗???묒떇??李얠쓣 ???놁뒿?덈떎.'], 404);
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
                ':format_name' => '蹂듭궗蹂?- ' . (string) $format['format_name'],
                ':data_type' => (string) $format['data_type'],
                ':created_by' => $actor,
            ]);

            $insert = $this->pdo->prepare("
                INSERT INTO ledger_data_format_columns
                    (id, format_id, excel_column_name, system_field_name, column_order, is_required)
                VALUES
                    (:id, :format_id, :excel_column_name, :system_field_name, :column_order, :is_required)
            ");
            foreach ($format['columns'] as $column) {
                $insert->execute([
                    ':id' => UuidHelper::generate(),
                    ':format_id' => $newId,
                    ':excel_column_name' => (string) $column['excel_column_name'],
                    ':system_field_name' => (string) $column['system_field_name'],
                    ':column_order' => (int) $column['column_order'],
                    ':is_required' => (int) $column['is_required'],
                ]);
            }

            $this->pdo->commit();
            $this->json(['success' => true, 'id' => $newId, 'message' => '?묒떇??蹂듭궗?섏뿀?듬땲??']);
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
            $this->json(['success' => false, 'message' => '?묒떇怨??뚯씪???좏깮?섏꽭??'], 400);
            return;
        }

        try {
            $rows = $this->parseUploadedRows($_FILES['file'], $format['columns']);
            $rows = $this->validatePreviewRows($rows, $format['columns']);
            $batch = $this->storeUploadBatch($format, $_FILES['file'], $rows);
            $this->json(['success' => true, 'data' => [
                'batch' => $batch,
                'format' => $format,
                'rows' => $rows,
            ]]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function apiCreateTransactions(): void
    {
        $payload = $this->requestPayload();
        $batchId = trim((string) ($payload['batch_id'] ?? ''));
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '?낅줈??諛곗튂 ID媛 ?놁뒿?덈떎.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '?낅줈??諛곗튂瑜?李얠쓣 ???놁뒿?덈떎.'], 404);
            return;
        }

        $dataType = self::normalizeDataType((string) ($batch['data_type'] ?? 'TAX_INVOICE'));
        $rowIds = is_array($payload['row_ids'] ?? null) ? array_values(array_filter(array_map('strval', $payload['row_ids']))) : [];
        $rows = $this->uploadRowsForTransactionCreate($batchId, $rowIds);
        if ($rows === []) {
            $this->json(['success' => false, 'message' => '嫄곕옒 ?앹꽦 媛?ν븳 ?낅줈???됱씠 ?놁뒿?덈떎.'], 400);
            return;
        }

        $created = 0;
        $errors = [];
        foreach ($rows as $row) {
            $mapped = json_decode((string) ($row['mapped_payload'] ?? ''), true);
            if (!is_array($mapped)) {
                $message = '留ㅽ븨 ?곗씠??JSON???쎌쓣 ???놁뒿?덈떎.';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
                continue;
            }

            $result = $this->transactionService()->save($this->transactionPayload($mapped, $dataType));
            if (!empty($result['success'])) {
                $created++;
                $this->updateUploadRowStatus((string) $row['id'], 'CREATED', null);
            } else {
                $message = $result['message'] ?? '嫄곕옒 ?앹꽦 ?ㅽ뙣';
                $this->updateUploadRowStatus((string) $row['id'], 'ERROR', $message);
                $errors[] = ['row' => (int) $row['row_no'], 'message' => $message];
            }
        }

        $this->json([
            'success' => $errors === [],
            'created_count' => $created,
            'errors' => $errors,
            'message' => $errors === [] ? "{$created}嫄댁쓽 嫄곕옒媛 ?앹꽦?섏뿀?듬땲??" : '?쇰? 嫄곕옒 ?앹꽦???ㅽ뙣?덉뒿?덈떎.',
        ], $errors === [] ? 200 : 422);
    }

    public function apiUploadBatches(): void
    {
        $stmt = $this->pdo->query("
            SELECT
                b.*,
                f.format_name,
                SUM(CASE WHEN r.status = 'VALID' THEN 1 ELSE 0 END) AS valid_count,
                SUM(CASE WHEN r.status = 'MAPPING_REQUIRED' THEN 1 ELSE 0 END) AS mapping_required_count,
                SUM(CASE WHEN r.status = 'ERROR' THEN 1 ELSE 0 END) AS error_count,
                SUM(CASE WHEN r.status = 'CREATED' THEN 1 ELSE 0 END) AS created_count
            FROM ledger_data_upload_batches b
            LEFT JOIN ledger_data_formats f ON f.id = b.format_id
            LEFT JOIN ledger_data_upload_rows r ON r.batch_id = b.id
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT 100
        ");

        $this->json(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    public function apiUploadBatchRows(): void
    {
        $batchId = trim((string) ($_GET['batch_id'] ?? ''));
        if ($batchId === '') {
            $this->json(['success' => false, 'message' => '?낅줈??諛곗튂 ID媛 ?놁뒿?덈떎.'], 400);
            return;
        }

        $batch = $this->uploadBatch($batchId);
        if (!$batch) {
            $this->json(['success' => false, 'message' => '?낅줈??諛곗튂瑜?李얠쓣 ???놁뒿?덈떎.'], 404);
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_data_upload_rows
            WHERE batch_id = :batch_id
            ORDER BY row_no ASC
        ");
        $stmt->execute([':batch_id' => $batchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['raw_payload'] = json_decode((string) ($row['raw_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['mapped_payload'] ?? ''), true) ?: [];
        }
        unset($row);

        $this->json(['success' => true, 'data' => [
            'batch' => $batch,
            'rows' => $rows,
        ]]);
    }

    private function templateSpec(string $type): array
    {
        $label = self::dataTypeLabel($type);
        if ($type === 'BANK') {
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
            ['작성일자', '사업자등록번호', '상호', '사업명', '관리명', '적요', '공급가액', '부가세', '합계금액', '과세구분', '비고', '메모'],
            [
                ['2026-05-04', '123-45-67890', '샘플상사', '본사', '일반관리', $label . ' 매입', 50000, 5000, 55000, 'TAXABLE', '샘플', ''],
                ['2026-05-04', '987-65-43210', '우리카드', '운영', '카드', $label . ' 사용분', 120000, 0, 120000, 'EXEMPT', '샘플', ''],
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
            self::safeFilename($formatName) . '_?낅줈???묒떇.xlsx',
            function_exists('mb_substr') ? mb_substr($formatName, 0, 31) : substr($formatName, 0, 31),
            $headers,
            [$this->sampleRowForColumns($columns, $dataType)],
        ];
    }

    private function sampleRowForColumns(array $columns, string $dataType): array
    {
        $samples = [
            'transaction_date' => '2026-05-04',
            'business_number' => '123-45-67890',
            'company_name' => $dataType === 'BANK' ? '?좎슜?곸궗' : '?곕━?곸궗',
            'project_name' => '蹂몄궗',
            'description' => self::dataTypeLabel($dataType) . ' ?섑뵆',
            'supply_amount' => 50000,
            'vat_amount' => $dataType === 'BANK' ? 0 : 5000,
            'total_amount' => $dataType === 'BANK' ? 55000 : 55000,
            'tax_type' => 'TAXABLE',
            'note' => '?섑뵆',
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
            $stmt = $this->pdo->prepare("
                INSERT INTO ledger_data_upload_batches
                    (id, file_name, data_type, format_id, total_rows, created_by)
                VALUES
                    (:id, :file_name, :data_type, :format_id, :total_rows, :created_by)
            ");
            $stmt->execute([
                ':id' => $batchId,
                ':file_name' => $fileName !== '' ? $fileName : 'upload',
                ':data_type' => $dataType,
                ':format_id' => (string) ($format['id'] ?? ''),
                ':total_rows' => count($rows),
                ':created_by' => $actor,
            ]);

            $insertRow = $this->pdo->prepare("
                INSERT INTO ledger_data_upload_rows
                    (id, batch_id, row_no, raw_payload, mapped_payload, status, error_message, created_by)
                VALUES
                    (:id, :batch_id, :row_no, :raw_payload, :mapped_payload, :status, :error_message, :created_by)
            ");
            foreach ($rows as $row) {
                $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
                $status = $this->uploadStatusFromValidation($validation);
                $messages = is_array($validation['messages'] ?? null) ? $validation['messages'] : [];
                $insertRow->execute([
                    ':id' => UuidHelper::generate(),
                    ':batch_id' => $batchId,
                    ':row_no' => (int) ($row['_row_no'] ?? 0),
                    ':raw_payload' => $this->jsonEncodeForStorage(is_array($row['_raw_payload'] ?? null) ? $row['_raw_payload'] : []),
                    ':mapped_payload' => $this->jsonEncodeForStorage($this->mappedPayloadForStorage($row)),
                    ':status' => $status,
                    ':error_message' => $messages !== [] ? implode(', ', $messages) : null,
                    ':created_by' => $actor,
                ]);
            }

            $this->pdo->commit();
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
        ];
    }

    private function uploadStatusFromValidation(array $validation): string
    {
        $status = (string) ($validation['status'] ?? 'ok');
        if ($status === 'error') {
            return 'ERROR';
        }
        if ($status === 'warning') {
            return 'MAPPING_REQUIRED';
        }
        return 'VALID';
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

    private function uploadBatch(string $batchId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, f.format_name
            FROM ledger_data_upload_batches b
            LEFT JOIN ledger_data_formats f ON f.id = b.format_id
            WHERE b.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $batchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function uploadRowsForTransactionCreate(string $batchId, array $rowIds = []): array
    {
        $params = [':batch_id' => $batchId];
        $idSql = '';
        if ($rowIds !== []) {
            $placeholders = [];
            foreach ($rowIds as $index => $rowId) {
                $key = ':row_id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $rowId;
            }
            $idSql = ' AND id IN (' . implode(', ', $placeholders) . ')';
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_data_upload_rows
            WHERE batch_id = :batch_id
              AND status IN ('VALID', 'MAPPING_REQUIRED')
              {$idSql}
            ORDER BY row_no ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function updateUploadRowStatus(string $rowId, string $status, ?string $message): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_upload_rows
            SET status = :status,
                error_message = :error_message
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $rowId,
            ':status' => $status,
            ':error_message' => $message,
        ]);
    }

    private function format(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_data_formats WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            $row['data_type'] = self::normalizeDataType((string) ($row['data_type'] ?? ''));
        }

        return $row;
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
            ORDER BY column_order ASC
        ");
        $stmt->execute([':format_id' => $formatId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeColumns(array $columns): array
    {
        $rows = [];
        foreach (array_values($columns) as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $excelColumn = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            if ($excelColumn === '' || !in_array($systemField, self::SYSTEM_FIELDS, true)) {
                continue;
            }
            $rows[] = [
                'excel_column_name' => $excelColumn,
                'system_field_name' => $systemField,
                'column_order' => (int) ($column['column_order'] ?? ($index + 1)),
                'is_required' => !empty($column['is_required']) ? 1 : 0,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $a['column_order'] <=> $b['column_order']);
        foreach ($rows as $index => &$row) {
            $row['column_order'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    private function parseUploadedRows(array $file, array $columns): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('?낅줈???뚯씪???쎌쓣 ???놁뒿?덈떎.');
        }

        $spreadsheet = IOFactory::load((string) $file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        if (count($rawRows) < 2) {
            return [];
        }

        $headerRow = array_shift($rawRows);
        $headerMap = [];
        foreach ($headerRow as $columnKey => $header) {
            $headerMap[trim((string) $header)] = $columnKey;
        }

        $mappedColumns = [];
        foreach ($columns as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $systemField = trim((string) ($column['system_field_name'] ?? ''));
            if ($excelName === '' || $systemField === '' || !isset($headerMap[$excelName])) {
                if (!empty($column['is_required'])) {
                    throw new \RuntimeException("?꾩닔 而щ읆???놁뒿?덈떎: {$excelName}");
                }
                continue;
            }
            $mappedColumns[] = [
                'sheet_column' => $headerMap[$excelName],
                'system_field_name' => $systemField,
            ];
        }

        $rows = [];
        foreach ($rawRows as $rowNo => $rawRow) {
            $rawPayload = [];
            foreach ($headerMap as $header => $columnKey) {
                $rawPayload[$header] = $this->cellValue($rawRow[$columnKey] ?? null);
            }
            $mapped = [];
            foreach ($mappedColumns as $column) {
                $mapped[$column['system_field_name']] = $this->cellValue($rawRow[$column['sheet_column']] ?? null);
            }
            if (implode('', array_map(static fn($value): string => trim((string) $value), $mapped)) === '') {
                continue;
            }
            $mapped['_row_no'] = (int) $rowNo;
            $mapped['_raw_payload'] = $rawPayload;
            $rows[] = $mapped;
        }

        return $rows;
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
        $normalized = str_replace(',', '', trim((string) $value));
        return $normalized !== '' && is_numeric($normalized) && (float) $normalized >= 0;
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
                if ($field !== '' && trim((string) ($row[$field] ?? '')) === '') {
                    $errors[] = self::fieldLabel($field) . ' 필수값 없음';
                }
            }

            $date = trim((string) ($row['transaction_date'] ?? ''));
            if ($date !== '' && !$this->isValidDateValue($date)) {
                $errors[] = '날짜 형식 오류';
            }

            foreach (['supply_amount', 'vat_amount', 'total_amount'] as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value !== '' && !$this->isValidAmountValue($value)) {
                    $errors[] = self::fieldLabel($field) . ' 금액 오류';
                }
            }

            $businessNumber = $this->normalizeBusinessNumber((string) ($row['business_number'] ?? ''));
            $companyName = $this->cleanCompanyName((string) ($row['company_name'] ?? ''));
            if ($businessNumber !== '' && !$this->clientExistsByBusinessNumber($businessNumber)) {
                $warnings[] = '거래처 신규 생성 예정';
            } elseif ($businessNumber === '' && $companyName !== '' && $this->findClientId($companyName) === null) {
                $warnings[] = '거래처 미매핑';
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

    private function transactionPayload(array $row, string $dataType): array
    {
        $supply = $this->number($row['supply_amount'] ?? $row['total_amount'] ?? 0);
        $vat = $this->number($row['vat_amount'] ?? 0);
        $total = $this->number($row['total_amount'] ?? ($supply + $vat));
        if ($supply <= 0 && $total > 0) {
            $supply = $total - $vat;
        }
        $taxType = strtoupper(trim((string) ($row['tax_type'] ?? '')));
        if ($taxType === '') {
            $taxType = $vat > 0 ? 'TAXABLE' : 'EXEMPT';
        }

        return [
            'transaction_date' => $this->dateValue($row['transaction_date'] ?? date('Y-m-d')),
            'business_unit' => 'HQ',
            'transaction_type' => $dataType === 'BANK' ? 'GENERAL' : 'GENERAL',
            'client_id' => $this->findOrCreateClient(
                (string) ($row['business_number'] ?? ''),
                (string) ($row['company_name'] ?? '')
            ),
            'project_id' => $this->findProjectId((string) ($row['project_name'] ?? '')),
            'tax_type' => $taxType,
            'description' => trim((string) ($row['description'] ?? '')),
            'status' => 'draft',
            'match_status' => 'none',
            'note' => trim((string) ($row['note'] ?? '')) ?: null,
            'memo' => trim((string) ($row['memo'] ?? '')) ?: null,
            'items' => [[
                'item_date' => $this->dateValue($row['transaction_date'] ?? date('Y-m-d')),
                'item_name' => trim((string) ($row['description'] ?? '?낅줈???먮즺')) ?: '?낅줈???먮즺',
                'quantity' => 1,
                'unit_price' => $supply,
                'tax_type' => $taxType,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
            ]],
        ];
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));
        return self::LEGACY_DATA_TYPE_MAP[$type] ?? $type;
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
            'CARD_PURCHASE' => '移대뱶_留ㅼ엯',
            'CARD_SALE' => '移대뱶_留ㅼ텧',
            'BANK' => '?낆텧',
            'ETC' => '湲고?',
        ][$type] ?? '?먮즺';
    }

    private static function safeFilename(string $name): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|]+/u', '_', trim($name)) ?: '?낅줈???묒떇';
        return preg_replace('/\s+/u', '_', $name) ?: '?낅줈???묒떇';
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
        (new ClientModel($this->pdo))->insert([
            'id' => $clientId,
            'client_name' => $clientName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'business_number' => $businessNumber !== '' ? $businessNumber : null,
            'registration_date' => date('Y-m-d'),
            'client_type' => 'CLIENT',
            'is_active' => 1,
            'created_by' => ActorHelper::user(),
            'updated_by' => ActorHelper::user(),
        ]);

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
            SET client_name = :company_name,
                company_name = :company_name,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $clientId,
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
        $companyName = preg_replace('/^\s*[\(竊?\s*(二???\s*[\)竊?\s*/u', '', $companyName) ?? $companyName;
        $companyName = preg_replace('/\s*[\(竊?\s*(二???\s*[\)竊?\s*$/u', '', $companyName) ?? $companyName;
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

    private static function fieldLabel(string $field): string
    {
        return [
            'transaction_date' => '거래일자',
            'business_number' => '사업자등록번호',
            'company_name' => '상호',
            'project_name' => '사업명',
            'description' => '적요',
            'supply_amount' => '공급가액',
            'vat_amount' => '부가세',
            'total_amount' => '합계금액',
            'tax_type' => '과세구분',
            'note' => '비고',
            'memo' => '메모',
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
        return (float) str_replace(',', '', trim((string) $value));
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
