<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EvidenceUploadService
{
    private EvidenceBodyStorageModel $bodyStorageModel;
    private EvidenceExternalKeyService $externalKeyService;
    /**
     * @param callable(string):string $dataTypeNormalizer
     * @param callable(array):array $bankPayloadNormalizer
     * @param callable(array):array $responsePayloadNormalizer
     * @param callable(array):array $storagePayloadNormalizer
     * @param callable(array,string):?string $seedSourceKeyBuilder
     * @param callable(array):string $jsonEncoder
     * @param callable(string):string $evidenceTransactionIdSelectBuilder
     * @param callable(mixed):?float $amountParser
     * @param callable(mixed):?string $dateParser
     * @param callable(array):bool $hasBankVoucherLineColumnsChecker
     * @param callable(array,bool):array $splitBankFormatColumns
     * @param callable(Spreadsheet):bool $bankLineSheetHasRowTypeColumnChecker
     * @param callable(array):array $uploadHeaderColumnsByNameBuilder
     * @param callable(array,array,array,?array):?string $uploadSheetColumnForFormatColumnResolver
     * @param callable(array):bool $requiredFormatColumnChecker
     * @param array<string,callable> $callbacks
     */
    public function __construct(
        private PDO $pdo,
        private $dataTypeNormalizer,
        private $bankPayloadNormalizer,
        private $responsePayloadNormalizer,
        private $storagePayloadNormalizer,
        private $seedSourceKeyBuilder,
        private $jsonEncoder,
        private $evidenceTransactionIdSelectBuilder,
        private $amountParser,
        private $dateParser,
        private $hasBankVoucherLineColumnsChecker,
        private $splitBankFormatColumns,
        private $bankLineSheetHasRowTypeColumnChecker,
        private $uploadHeaderColumnsByNameBuilder,
        private $uploadSheetColumnForFormatColumnResolver,
        private $requiredFormatColumnChecker,
        private array $callbacks = []
    ) {
        $this->bodyStorageModel = new EvidenceBodyStorageModel($pdo);
        $this->externalKeyService = new EvidenceExternalKeyService();
    }

    private array $existingSeedRowCache = [];

    public function cancel(array $payload): array
    {
        $cancelToken = $this->uploadCancelTokenFromPayload($payload);
        if ($cancelToken === '') {
            return ['success' => false, 'message' => '업로드 취소 토큰이 없습니다.', 'status' => 400];
        }

        $this->markUploadCanceled($cancelToken);
        $this->uploadTrace('cancel_requested', ['token' => $cancelToken]);
        $previewToken = trim((string) ($payload['preview_token'] ?? ''));
        if ($previewToken !== '') {
            $this->clearPreviewSession($previewToken);
        }

        return ['success' => true, 'message' => '업로드 취소 요청이 접수되었습니다.'];
    }

    public function storePreviewSession(array $format, array $file, array $rows): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $previewPath = tempnam(sys_get_temp_dir(), 'ledger_upload_preview_');
        if ($previewPath === false || $tmpName === '' || !is_file($tmpName) || !copy($tmpName, $previewPath)) {
            if (is_string($previewPath) && is_file($previewPath)) {
                @unlink($previewPath);
            }
            throw new \RuntimeException('업로드 미리보기 파일을 준비하는 중 오류가 발생했습니다.');
        }

        $token = UuidHelper::generate();
        $_SESSION['ledger_upload_previews'][$token] = [
            'format' => $format,
            'file' => [
                'name' => (string) ($file['name'] ?? 'upload'),
                'tmp_name' => $previewPath,
                'error' => UPLOAD_ERR_OK,
                'size' => (int) ($file['size'] ?? 0),
                'type' => (string) ($file['type'] ?? ''),
            ],
            'rows' => $rows,
            'created_at' => time(),
        ];
        session_write_close();

        return $token;
    }

    public function previewFromSession(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $preview = $_SESSION['ledger_upload_previews'][$token] ?? null;
        if (!is_array($preview)) {
            session_write_close();
            return null;
        }

        session_write_close();

        return $preview;
    }

    public function clearPreviewSession(string $token): void
    {
        if ($token === '') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $preview = $_SESSION['ledger_upload_previews'][$token] ?? null;
        $previewFile = is_array($preview['file'] ?? null) ? $preview['file'] : [];
        $tmpName = trim((string) ($previewFile['tmp_name'] ?? ''));
        if ($tmpName !== '' && is_file($tmpName)) {
            @unlink($tmpName);
        }
        unset($_SESSION['ledger_upload_previews'][$token]);
        session_write_close();
    }

    public function validationSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'ok' => 0,
            'warning' => 0,
            'error' => 0,
            'new' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'protected_update' => 0,
            'required_missing_rows' => 0,
            'required_missing_items' => 0,
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
            $requiredMissingCount = (int) ($row['_validation']['required_missing_count'] ?? 0);
            if ($requiredMissingCount > 0) {
                $summary['required_missing_rows']++;
                $summary['required_missing_items'] += $requiredMissingCount;
            }
        }

        return $summary;
    }

    public function allowsRequiredMissing(array $payload): bool
    {
        $value = $payload['allow_required_missing'] ?? $payload['confirm_required_missing'] ?? false;
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    public function requiredMissingSummary(array $rows): array
    {
        $summary = [
            'rows' => 0,
            'items' => 0,
            'messages' => [],
        ];

        foreach ($rows as $row) {
            $validation = is_array($row['_validation'] ?? null) ? $row['_validation'] : [];
            $messages = array_values(array_filter(array_map('strval', is_array($validation['required_missing_messages'] ?? null) ? $validation['required_missing_messages'] : [])));
            if ($messages === []) {
                continue;
            }

            $summary['rows']++;
            $summary['items'] += count($messages);
            $rowNo = (int) ($row['_row_no'] ?? 0);
            $prefix = $rowNo > 0 ? "{$rowNo}행: " : '';
            foreach ($messages as $message) {
                $summary['messages'][] = $prefix . $message;
            }
        }

        $summary['messages'] = array_slice(array_values(array_unique($summary['messages'])), 0, 5);

        return $summary;
    }

    public function requiredMissingConfirmationMessage(array $summary): string
    {
        $rows = number_format((int) ($summary['rows'] ?? 0));
        $items = number_format((int) ($summary['items'] ?? 0));
        $examples = array_values(array_filter(array_map('strval', is_array($summary['messages'] ?? null) ? $summary['messages'] : [])));
        $message = "필수값이 누락된 항목이 있습니다. 계속 업로드하시겠습니까? {$rows}행 {$items}건의 필수값 누락을 확인했습니다. 아래 예시를 확인한 뒤 계속 진행하거나 업로드양식 설정을 수정해 주세요.";
        if ($examples !== []) {
            $message .= "\n\n" . implode("\n", $examples);
        }

        return $message;
    }

    public function buildRequiredMissingConfirmationResponse(array $requiredMissing, ?string $previewToken = null, int $totalRows = 0): array
    {
        $data = ['required_missing' => $requiredMissing];
        if ($previewToken !== null && $previewToken !== '') {
            $data['preview_token'] = $previewToken;
        }
        if ($totalRows > 0) {
            $data['total_rows'] = $totalRows;
        }

        return [
            'success' => false,
            'requires_confirmation' => true,
            'confirmation_code' => 'REQUIRED_FIELD_MISSING',
            'message' => $this->requiredMissingConfirmationMessage($requiredMissing),
            'data' => $data,
        ];
    }

    public function buildUploadStartTraceContext(string $cancelToken, array $file, string $mode): array
    {
        return [
            'token' => $cancelToken,
            'mode' => $mode,
            'file' => (string) ($file['name'] ?? ''),
            'size' => (string) ($file['size'] ?? ''),
        ];
    }

    public function prepareSeedUploadFilePath(
        array $format,
        array $file,
        string $dataType,
        string $cancelToken,
        float $startedAt,
        array $requestPayload
    ): array {
        $this->assertUploadNotCanceled($cancelToken);
        $stageStartedAt = microtime(true);
        $checks = $this->validateUploadFileColumns($file, $format['columns']);
        $this->uploadTrace('validated_columns', [
            'token' => $cancelToken,
            'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
        ]);
        $this->assertUploadFileMatchesFormat($checks, $format['columns']);
        $checkErrors = array_values(array_filter($checks, static fn(array $check): bool => ($check['level'] ?? '') === 'error'));
        if ($checkErrors !== []) {
            throw new \RuntimeException('업로드 파일 형식 검증 중 오류가 발생했습니다. ' . (string) ($checkErrors[0]['message'] ?? ''));
        }

        $this->assertUploadNotCanceled($cancelToken);
        $stageStartedAt = microtime(true);
        $rows = $this->call('parseUploadedRows', $file, $format['columns']);
        $this->uploadTrace('parsed_rows', [
            'token' => $cancelToken,
            'rows' => count($rows),
            'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
        ]);
        if ($rows === []) {
            throw new \RuntimeException('업로드할 데이터 행을 찾지 못했습니다. 헤더 아래 2행부터 데이터가 있는지 확인해 주세요.');
        }

        $this->assertUploadNotCanceled($cancelToken);
        $stageStartedAt = microtime(true);
        $rows = $this->call('enrichUploadRows', $rows, $dataType);
        $rows = $this->call('validatePreviewRows', $rows, $format['columns'], $dataType);
        $rows = $this->call('annotateSeedComparison', $rows, $dataType);
        $this->uploadTrace('prepared_rows', [
            'token' => $cancelToken,
            'rows' => count($rows),
            'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
        ]);

        $this->call('assertNoUploadValidationErrors', $rows);
        $requiredMissing = $this->requiredMissingSummary($rows);
        $confirmationResponse = null;
        if (!$this->allowsRequiredMissing($requestPayload) && $requiredMissing['items'] > 0) {
            $token = $this->storePreviewSession($format, $file, $rows);
            $this->uploadTrace('requires_confirmation', [
                'token' => $cancelToken,
                'rows' => count($rows),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            $confirmationResponse = $this->buildRequiredMissingConfirmationResponse($requiredMissing, $token, count($rows));
        }

        return [
            'checks' => $checks,
            'rows' => $rows,
            'confirmation_response' => $confirmationResponse,
        ];
    }

    public function preparePreviewConfirmation(array $payload, float $startedAt): array
    {
        $cancelToken = $this->uploadCancelTokenFromPayload($payload);
        $this->uploadTrace('preview_payload_loaded', [
            'token' => $cancelToken,
            'preview_token' => trim((string) ($payload['preview_token'] ?? '')),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        $token = trim((string) ($payload['preview_token'] ?? ''));
        $this->uploadTrace('preview_session_loading', [
            'token' => $cancelToken,
            'preview_token' => $token,
        ]);
        $preview = $this->previewFromSession($token);
        $this->uploadTrace('preview_session_loaded', [
            'token' => $cancelToken,
            'preview_token' => $token,
            'found' => $preview ? 1 : 0,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        if (!$preview) {
            throw new \RuntimeException('미리보기 정보를 찾을 수 없습니다. 파일을 다시 업로드해 주세요.');
        }
        $this->assertUploadNotCanceled($cancelToken);
        $previewFile = is_array($preview['file'] ?? null) ? $preview['file'] : [];
        if (trim((string) ($previewFile['tmp_name'] ?? '')) === '' || !is_file((string) $previewFile['tmp_name'])) {
            throw new \RuntimeException('미리보기 파일을 찾을 수 없습니다. 파일을 다시 업로드해 주세요.');
        }
        $dataType = ($this->dataTypeNormalizer)((string) ($preview['format']['data_type'] ?? 'ETC'));
        $rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
        if ($rows === []) {
            $rows = $this->call('parseUploadedRows', $previewFile, $preview['format']['columns']);
            $rows = $this->call('enrichUploadRows', $rows, $dataType);
            $rows = $this->call('validatePreviewRows', $rows, $preview['format']['columns'], $dataType);
            $rows = $this->call('annotateSeedComparison', $rows, $dataType);
        }
        $this->call('assertNoUploadValidationErrors', $rows);
        $requiredMissing = $this->requiredMissingSummary($rows);
        $preview['file'] = $previewFile;
        $preview['rows'] = $rows;
        if (($preview['rows'] ?? []) === []) {
            throw new \RuntimeException('미리보기 데이터가 없습니다. 파일을 다시 업로드해 주세요.');
        }
        return [
            'cancel_token' => $cancelToken,
            'preview_token' => $token,
            'preview' => $preview,
            'required_missing' => $requiredMissing,
            'total_rows' => count($preview['rows']),
        ];
    }
    public function buildPreviewRequiredMissingConfirmationResponse(array $payload, array $requiredMissing): ?array
    {
        if (!$this->allowsRequiredMissing($payload) && ((int) ($requiredMissing['items'] ?? 0)) > 0) {
            return $this->buildRequiredMissingConfirmationResponse($requiredMissing);
        }

        return null;
    }

    public function buildCompletedChunkUploadResult(int $totalRows, int $offset): array
    {
        return [
            'total_count' => $totalRows,
            'inserted_count' => 0,
            'duplicate_count' => 0,
            'deleted_duplicate_count' => 0,
            'conflict_count' => 0,
            'details' => [],
            'total_rows' => $totalRows,
            'processed_count' => 0,
            'new_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'error_count' => 0,
            'skipped_count' => 0,
            'chunk_offset' => $offset,
            'next_offset' => $totalRows,
            'done' => true,
        ];
    }

    public function buildChunkUploadProgressResult(array $result, int $totalRows, int $offset, int $chunkRows): array
    {
        $nextOffset = min($totalRows, $offset + $chunkRows);
        $result['total_rows'] = $totalRows;
        $result['chunk_rows'] = $chunkRows;
        $result['chunk_offset'] = $offset;
        $result['next_offset'] = $nextOffset;
        $result['done'] = $nextOffset >= $totalRows;

        return $result;
    }

    public function buildStoredRowsTraceContext(string $cancelToken, int $rows, float $stageStartedAt, float $startedAt): array
    {
        return [
            'token' => $cancelToken,
            'rows' => $rows,
            'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
            'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function buildFailedUploadTraceContext(string $cancelToken, float $startedAt, \Throwable $e): array
    {
        return [
            'token' => $cancelToken,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => $e->getMessage(),
        ];
    }

    public function buildPreviewPayloadReadTraceContext(float $startedAt): array
    {
        return [
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function buildStoredPreviewChunkTraceContext(
        string $cancelToken,
        int $offset,
        int $nextOffset,
        int $totalRows,
        float $stageStartedAt,
        float $startedAt
    ): array {
        return [
            'token' => $cancelToken,
            'offset' => $offset,
            'next' => $nextOffset,
            'total' => $totalRows,
            'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
            'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function buildStoredPreviewRowsTraceContext(string $cancelToken, int $rows, float $stageStartedAt, float $startedAt): array
    {
        return [
            'token' => $cancelToken,
            'rows' => $rows,
            'elapsed_ms' => (int) round((microtime(true) - $stageStartedAt) * 1000),
            'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function buildFailedPreviewTraceContext(string $cancelToken, float $startedAt, \Throwable $e): array
    {
        return [
            'token' => $cancelToken,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => $e->getMessage(),
        ];
    }

    public function prepareLargeUploadRuntime(): void
    {
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(false);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '1024M');
        }
    }

    public function uploadCancelTokenFromPayload(array $payload): string
    {
        $token = trim((string) ($payload['upload_cancel_token'] ?? $payload['cancel_token'] ?? ''));

        return preg_match('/^[a-zA-Z0-9_-]{12,80}$/', $token) === 1 ? $token : '';
    }

    public function clearUploadCancelToken(string $token): void
    {
        if ($token === '') {
            return;
        }

        $path = $this->uploadCancelPath($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function assertUploadNotCanceled(string $token): void
    {
        if ($token !== '' && is_file($this->uploadCancelPath($token))) {
            throw new \RuntimeException('업로드가 취소되었습니다.');
        }
    }

    public function uploadTrace(string $event, array $context = []): void
    {
        $parts = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $parts[] = $key . '=' . (string) $value;
            }
        }

        error_log('[ImportUpload] ' . $event . ($parts !== [] ? ' ' . implode(' ', $parts) : ''));
    }

    public function validateUploadFileColumns(array $file, array $columns): array
    {
        $spreadsheet = $this->loadUploadedSpreadsheetHeaderOnly($file);
        if ($spreadsheet->getSheetCount() > 1 && ($this->hasBankVoucherLineColumnsChecker)($columns)) {
            [$headerColumns, $lineColumns] = ($this->splitBankFormatColumns)(
                $columns,
                ($this->bankLineSheetHasRowTypeColumnChecker)($spreadsheet)
            );
            $checks = array_merge(
                $this->validateSheetColumns($spreadsheet->getSheet(0), $headerColumns, true, '전표 헤더 시트'),
                $this->validateSheetColumns($spreadsheet->getSheet(1), $lineColumns, true, '전표 분개 시트')
            );
            $spreadsheet->disconnectWorksheets();
            return $checks;
        }
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $sheet->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        $spreadsheet->disconnectWorksheets();
        $checks = [];
        $headerColumnsByName = ($this->uploadHeaderColumnsByNameBuilder)($headerRow);
        $usedHeaderColumns = [];
        foreach ($columns as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $sheetColumn = ($this->uploadSheetColumnForFormatColumnResolver)($column, $headerRow, $headerColumnsByName, $usedHeaderColumns);
            $actualName = $sheetColumn !== null ? trim((string) ($headerRow[$sheetColumn] ?? '')) : '';
            $actualName = preg_replace('/\s*\*$/u', '', $actualName) ?? $actualName;
            if (($this->requiredFormatColumnChecker)($column) && $actualName === '') {
                $checks[] = ['level' => 'warning', 'message' => "필수 컬럼이 업로드 파일 헤더에 없습니다. {$excelName}"];
                continue;
            }
            if ($excelName !== '' && $actualName !== '') {
                $checks[] = ['level' => 'ok', 'message' => $excelName . ' 컬럼이 업로드 파일에 확인되었습니다.'];
            }
        }
        return $checks;
    }
    public function validateSheetColumns(Worksheet $sheet, array $columns, bool $sequentialColumns, string $sheetLabel): array
    {
        if ($sequentialColumns && $sheetLabel === '') {
            $sheetLabel = '시트';
        }
        if (substr_count($sheetLabel, '?') >= 2) {
            $sheetLabel = '시트';
        }
        $headerRow = $sheet->rangeToArray('1:1', null, true, true, true)[1] ?? [];
        $checks = [];
        $headerColumnsByName = ($this->uploadHeaderColumnsByNameBuilder)($headerRow);
        $usedHeaderColumns = [];
        foreach (array_values($columns) as $column) {
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $sheetColumn = ($this->uploadSheetColumnForFormatColumnResolver)($column, $headerRow, $headerColumnsByName, $usedHeaderColumns);
            $actualName = $sheetColumn !== null ? trim((string) ($headerRow[$sheetColumn] ?? '')) : '';
            $actualName = preg_replace('/\s*\*$/u', '', $actualName) ?? $actualName;
            if (($this->requiredFormatColumnChecker)($column) && $actualName === '') {
                $checks[] = ['level' => 'warning', 'message' => "{$sheetLabel} 필수 컬럼이 업로드 파일 헤더에 없습니다. {$excelName}"];
                continue;
            }
            if ($excelName !== '' && $actualName !== '') {
                $checks[] = ['level' => 'ok', 'message' => "{$sheetLabel} {$excelName} 컬럼이 업로드 파일에 확인되었습니다."];
            }
        }
        return $checks;
    }
    public function assertUploadFileMatchesFormat(array $checks, array $columns): void
    {
        $configuredCount = 0;
        foreach ($columns as $column) {
            if (trim((string) ($column['excel_column_name'] ?? '')) !== '') {
                $configuredCount++;
            }
        }
        if ($configuredCount < 1) {
            throw new \RuntimeException('이미 생성된 전표가 있어 수정할 수 없습니다.');
        }
        $matchedCount = 0;
        foreach ($checks as $check) {
            if (($check['level'] ?? '') === 'ok') {
                $matchedCount++;
            }
        }
        $minimumMatched = min(2, $configuredCount);
        if ($matchedCount < $minimumMatched) {
            throw new \RuntimeException("업로드 파일 헤더와 양식이 충분히 일치하지 않습니다. 일치 컬럼 {$matchedCount}건 / 설정 컬럼 {$configuredCount}건입니다. 열 이름을 다시 확인해 주세요.");
        }
    }
    public function annotateSeedComparison(array $rows, string $dataType): array
    {
        $this->preloadExistingSeedRowsForUploadRows($rows, $dataType);
        foreach ($rows as &$row) {
            $parsed = ($this->storagePayloadNormalizer)($row);
            $sourceKey = ($this->seedSourceKeyBuilder)($parsed, $dataType);
            $row['_seed_key'] = $sourceKey;
            $row['_seed_action'] = 'NEW';
            if ($sourceKey === null) {
                continue;
            }

            $existing = $this->findExistingSeedRow($dataType, $sourceKey);
            if ($existing) {
                $row['_seed_action'] = !empty($existing['deleted_at']) ? 'DELETED_DUPLICATE' : 'DUPLICATE';
            }
        }
        unset($row);

        return $rows;
    }

    public function preloadExistingSeedRowsForUploadRows(array $rows, string $dataType): void
    {
        $sourceKeys = [];
        $dataType = ($this->dataTypeNormalizer)($dataType);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parsedPayload = ($this->storagePayloadNormalizer)($row);
            if ($dataType === 'BANK_TRANSACTION') {
                $parsedPayload = ($this->bankPayloadNormalizer)($parsedPayload);
            }
            $sourceKey = ($this->seedSourceKeyBuilder)($parsedPayload, $dataType);
            if ($sourceKey !== '') {
                $sourceKeys[] = $sourceKey;
            }
        }

        $this->preloadExistingSeedRowsBySourceKeys($dataType, $sourceKeys);
    }

    public function findExistingSeedRow(string $sourceType, string $sourceKey): ?array
    {
        $cacheKey = ($this->dataTypeNormalizer)($sourceType) . '|' . $sourceKey;
        if (array_key_exists($cacheKey, $this->existingSeedRowCache)) {
            return $this->existingSeedRowCache[$cacheKey];
        }

        $row = $this->bodyStorageModel->findUploadSeedByImportType(
            ($this->dataTypeNormalizer)($sourceType), $sourceKey
        );
        $this->existingSeedRowCache[$cacheKey] = $row;

        return $row;
    }

    public function findExistingSeedRowByFingerprint(string $sourceType, array $payload): ?array
    {
        return $this->findExistingSeedRow($sourceType, $this->externalKeyService->key($payload, $sourceType));
    }

    public function sourceFingerprintKey(array $row, string $dataType): string
    {
        return $this->externalKeyService->key($row, ($this->dataTypeNormalizer)($dataType));
    }

    public function usesFingerprintSourceKey(string $dataType): bool
    {
        return false;
    }
    public function seedSourceKey(array $row, string $dataType): ?string
    {
        return $this->externalKeyService->key($row, ($this->dataTypeNormalizer)($dataType));
    }

    public function externalKeyContentDigest(array $row, string $dataType): string
    {
        return $this->externalKeyService->contentDigest($row, ($this->dataTypeNormalizer)($dataType));
    }
    public function updateUploadRowStatus(string $rowId, string $status, ?string $message, ?string $transactionId = null): void
    {
        // 생성센터 처리상태 저장은 폐기되었으며 연결 상태는 ledger_evidence_links에서 조회한다.
    }

    public function isUploadProtectedExistingSeed(array $row): bool
    {
        return $this->existingSeedHasCreatedTransaction($row) || $this->existingSeedHasCreatedVoucher($row);
    }

    public function existingSeedHasCreatedTransaction(array $row): bool
    {
        $transactionId = trim((string) ($row['transaction_id'] ?? ''));
        $status = strtoupper(trim((string) ($row['transaction_status'] ?? '')));

        return $transactionId !== '' || in_array($status, ['PROCESSED', 'LINKED', 'COMPLETED'], true);
    }

    public function existingSeedHasCreatedVoucher(array $row): bool
    {
        $status = strtoupper(trim((string) ($row['voucher_status'] ?? '')));

        return in_array($status, ['LINKED', 'COMPLETED', 'POSTED'], true);
    }

    public function rememberExistingSeedRow(string $dataType, string $sourceKey, array $row, ?array $payload = null): void
    {
        if ($sourceKey === '') {
            return;
        }

        $dataType = ($this->dataTypeNormalizer)($dataType);
        $this->existingSeedRowCache[$dataType . '|' . $sourceKey] = $row;

    }

    private function uploadCancelPath(string $token): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ledger_upload_cancel_' . $token;
    }

    private function isGenerationCorrectionMessage(?string $message): bool
    {
        $text = trim((string) $message);
        if ($text === '') {
            return false;
        }
        return str_contains($text, '자동으로 새 전표를 만들지 않았습니다.')
            || (str_contains($text, '이미 생성된 전표가 있어') && str_contains($text, '수정할 수 없습니다.'));
    }
    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }

    private function markUploadCanceled(string $token): void
    {
        if ($token === '') {
            return;
        }

        @file_put_contents($this->uploadCancelPath($token), (string) time(), LOCK_EX);
    }

    private function preloadExistingSeedRowsBySourceKeys(string $sourceType, array $sourceKeys): void
    {
        $sourceType = ($this->dataTypeNormalizer)($sourceType);
        $sourceKeys = array_values(array_unique(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), $sourceKeys))));
        if ($sourceKeys === []) {
            return;
        }

        $missing = [];
        foreach ($sourceKeys as $sourceKey) {
            $cacheKey = $sourceType . '|' . $sourceKey;
            if (!array_key_exists($cacheKey, $this->existingSeedRowCache)) {
                $missing[] = $sourceKey;
            }
        }
        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 1000) as $chunkIndex => $chunk) {
            $seen = [];
            $foundRows = [];
            foreach ($chunk as $sourceKey) {
                $row = $this->bodyStorageModel->findUploadSeedByImportType($sourceType, $sourceKey);
                if ($row !== null) $foundRows[] = $row;
            }
            foreach ($foundRows as $row) {
                $sourceKey = (string) ($row['source_key'] ?? '');
                if ($sourceKey === '' || isset($seen[$sourceKey])) {
                    continue;
                }
                $seen[$sourceKey] = true;
                $this->existingSeedRowCache[$sourceType . '|' . $sourceKey] = $row;
            }

            foreach ($chunk as $sourceKey) {
                $cacheKey = $sourceType . '|' . $sourceKey;
                if (!array_key_exists($cacheKey, $this->existingSeedRowCache)) {
                    $this->existingSeedRowCache[$cacheKey] = null;
                }
            }
        }
    }

    private function loadUploadedSpreadsheetHeaderOnly(array $file): Spreadsheet
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = strtolower((string) ($file['type'] ?? ''));
        $isCsv = $extension === 'csv' || str_contains($mime, 'csv');
        $readFilter = new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 1;
            }
        };

        if (!$isCsv) {
            $reader = IOFactory::createReaderForFile($tmpName);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            if (method_exists($reader, 'setPreCalculateFormulas')) {
                $reader->setPreCalculateFormulas(false);
            }
            if (method_exists($reader, 'setReadFilter')) {
                $reader->setReadFilter($readFilter);
            }

            return $reader->load($tmpName);
        }

        $reader = IOFactory::createReader('Csv');
        $encoding = function_exists('mb_detect_encoding')
            ? mb_detect_encoding((string) file_get_contents($tmpName), ['UTF-8', 'CP949', 'EUC-KR', 'SJIS-win', 'ISO-8859-1'], true)
            : false;
        if ($encoding && method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding($encoding);
        }
        if (method_exists($reader, 'setReadFilter')) {
            $reader->setReadFilter($readFilter);
        }

        return $reader->load($tmpName);
    }

    public function batches(): array
    {
        return $this->bodyStorageModel->uploadBatches();
    }

    public function batchRows(string $batchId): array
    {
        $rows = $this->bodyStorageModel->uploadBatchRows($batchId);
        foreach ($rows as $index => &$row) {
            $row['row_no'] = $index + 1;
            $row['raw_payload'] = json_decode((string) ($row['raw_payload'] ?? ''), true) ?: [];
            $row['mapped_payload'] = json_decode((string) ($row['mapped_payload'] ?? ''), true) ?: [];
            if (($this->dataTypeNormalizer)((string) ($row['source_type'] ?? '')) === 'BANK_TRANSACTION') {
                $row['mapped_payload'] = ($this->bankPayloadNormalizer)($row['mapped_payload']);
            }
            $row['mapped_payload'] = ($this->responsePayloadNormalizer)($row['mapped_payload']);
        }
        unset($row);

        return $rows;
    }
}
