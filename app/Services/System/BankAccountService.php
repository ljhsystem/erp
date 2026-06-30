<?php

namespace App\Services\System;

use PDO;
use App\Models\System\BankAccountModel;
use App\Services\File\FileService;
use Core\Helpers\ExcelTemplateFilenameHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BankAccountService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => true, 'allow_upload' => false],
        ['key' => 'account_name', 'label' => '계좌명', 'required' => true, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'bank_name', 'label' => '은행명', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'account_number', 'label' => '계좌번호', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'account_holder', 'label' => '예금주', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'account_type', 'label' => '계좌유형', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'currency', 'label' => '통화', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'bank_file', 'label' => '통장사본', 'required' => false, 'template_default' => false, 'download_default' => true, 'allow_upload' => false],
        ['key' => 'note', 'label' => '비고', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'memo', 'label' => '메모', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'is_active', 'label' => '상태', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'created_at', 'label' => '등록일시', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'created_by_name', 'label' => '등록자', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'updated_at', 'label' => '수정일시', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'updated_by_name', 'label' => '수정자', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'deleted_at', 'label' => '삭제일시', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'deleted_by_name', 'label' => '삭제자', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
    ];

    private const SAMPLE_ROW = [
        'account_name' => '운영계좌',
        'bank_name' => '기업은행',
        'account_number' => '123-456-789012',
        'account_holder' => '숙향',
        'account_type' => '보통예금',
        'currency' => 'KRW',
        'note' => '주거래 계좌',
        'memo' => '샘플 메모',
        'is_active' => '사용',
    ];

    private PDO $pdo;
    private BankAccountModel $model;
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new BankAccountModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.BankAccountService');

        $this->logger->info('BankAccountService initialized');
    }

    public function getList(array $filters = []): array
    {
        $this->logger->info('getList() called', [
            'filters' => $filters
        ]);

        try {

            $rows = $this->model->getList($filters);

            $this->logger->info('getList() success', [
                'count' => count($rows)
            ]);

            return $rows;

        } catch (\Throwable $e) {

            $this->logger->error('getList() failed', [
                'filters'   => $filters,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', ['id' => $id]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', ['id' => $id]);
                return null;
            }

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function searchPicker(string $keyword): array
    {
        $this->logger->info('searchPicker() called', [
            'keyword' => $keyword
        ]);

        try {

            $rows = $this->model->searchPicker($keyword, 20);

            if (empty($rows)) {
                return [];
            }

            $results = [];

            foreach ($rows as $row) {

                $text = $row['account_name'] ?? '';

                if (!empty($row['bank_name'])) {
                    $text = $row['bank_name'] . ' / ' . $text;
                }

                if (!empty($row['account_number'])) {
                    $text .= ' (' . $row['account_number'] . ')';
                }

                if (!empty($row['account_holder'])) {
                    $text .= ' - ' . $row['account_holder'];
                }

                $results[] = [
                    'id'             => $row['id'],
                    'text'           => $text,
                    'account_name'   => $row['account_name'] ?? '',
                    'account_number' => $row['account_number'] ?? '',
                    'bank_name'      => $row['bank_name'] ?? '',
                    'account_holder' => $row['account_holder'] ?? '',
                ];
            }

            return $results;

        } catch (\Throwable $e) {

            $this->logger->error('searchPicker() failed', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);
        $id = trim((string)($data['id'] ?? ''));
        $mode = $id === '' ? 'CREATE' : 'UPDATE';
        $isCreate = ($mode === 'CREATE');

        $this->logger->info('save() called', [
            'mode' => $mode,
            'id' => $id,
            'actor' => $actor
        ]);

        try {
            $this->pdo->beginTransaction();

            if (!$isCreate) {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('Account not found.');
                }

                if (!empty($data['delete_bank_file']) && $data['delete_bank_file'] == '1') {
                    if (!empty($before['bank_file'])) {
                        $this->fileService->delete($before['bank_file']);
                    }
                    $data['bank_file'] = null;
                } else {
                    $data['bank_file'] = $before['bank_file'] ?? null;
                }

                $file = $files['bank_file'] ?? null;
                if ($file) {
                    $this->assertUploadOk($file, 'bank copy');
                }

                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $upload = $this->fileService->uploadBankCopy($file);
                    if (!$upload['success']) {
                        throw new \Exception($upload['message'] ?? 'Bank copy upload failed.');
                    }

                    if (!empty($before['bank_file']) && $before['bank_file'] !== ($upload['db_path'] ?? null)) {
                        $this->fileService->delete($before['bank_file']);
                    }

                    $data['bank_file'] = $upload['db_path'];
                }

                $data['updated_by'] = $actor;
                $updateData = $data;
                unset($updateData['id']);

                if (!$this->model->updateById($id, $updateData)) {
                    throw new \Exception('Failed to update bank account.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $before['sort_no'] ?? null,
                    'message' => 'Update completed.'
                ];
            }

            $file = $files['bank_file'] ?? null;
            if ($file) {
                $this->assertUploadOk($file, 'bank copy');
            }

            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = $this->fileService->uploadBankCopy($file);
                if (!$upload['success']) {
                    throw new \Exception($upload['message'] ?? 'Bank copy upload failed.');
                }
                $data['bank_file'] = $upload['db_path'];
            }

            $newId = UuidHelper::generate();
            $newSortNo = SequenceHelper::next('system_bank_accounts', 'sort_no');

            $insertData = array_merge($data, [
                'id' => $newId,
                'sort_no' => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('Failed to create bank account.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'sort_no' => $newSortNo,
                'message' => 'Create completed.'
            ];
        }
        catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function assertUploadOk(array $file, string $label): void
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE || $error === UPLOAD_ERR_OK) {
            return;
        }

        throw new \Exception($this->resolveUploadErrorMessage($error, $label));
    }

    private function resolveUploadErrorMessage(int $errorCode, string $label): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "The {$label} file exceeds the size limit.",
            UPLOAD_ERR_PARTIAL => "The {$label} upload was interrupted.",
            UPLOAD_ERR_NO_TMP_DIR => "No temporary upload directory is available for {$label}.",
            UPLOAD_ERR_CANT_WRITE => "The server could not write the {$label} file.",
            UPLOAD_ERR_EXTENSION => "A server extension blocked the {$label} upload.",
            default => "An upload error occurred while processing {$label}.",
        };
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $ok = $this->model->deleteById($id, $actor);

            return [
                'success' => $ok,
                'message' => $ok ? '삭제 완료' : '삭제 실패'
            ];
        } catch (\Throwable $e) {
            $this->logger->error('delete() failed', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getTrashList(): array
    {
        $this->logger->info('getTrashList() called');

        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() exception', [
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $ok = $this->model->restoreById($id, $actor);

            return [
                'success' => $ok,
                'message' => $ok ? '복원 완료' : '복원 실패'
            ];
        } catch (\Throwable $e) {
            $this->logger->error('restore() failed', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids' => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'No ids provided.'];
        }

        $this->pdo->beginTransaction();

        try {
            $success = 0;

            foreach ($ids as $id) {
                if ($this->model->restoreById($id, $actor)) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Restore completed ({$success} items)."
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('restoreBulk() failed', [
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreAll() called', [
            'actor' => $actor
        ]);

        $this->pdo->beginTransaction();

        try {
            $rows = $this->model->getDeleted();
            $success = 0;

            foreach ($rows as $row) {
                if ($this->model->restoreById($row['id'], $actor)) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Restore all completed ({$success} items)."
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('restoreAll() failed', [
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purge() called', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor
        ]);

        $item = $this->model->getById($id);
        if (!$item) {
            return [
                'success' => false,
                'message' => 'Account not found.'
            ];
        }

        $this->pdo->beginTransaction();

        try {
            if (!empty($item['bank_file'])) {
                $this->fileService->delete($item['bank_file']);
            }

            if (!$this->model->hardDeleteById($id)) {
                throw new \Exception('Failed to delete account from database.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Purge completed.'
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('purge() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Purge failed.'
            ];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purgeBulk() called', [
            'ids' => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'No ids provided.'];
        }

        $this->pdo->beginTransaction();

        try {
            $success = 0;

            foreach ($ids as $id) {
                $item = $this->model->getById($id);
                if (!$item) {
                    continue;
                }

                if (!empty($item['bank_file'])) {
                    $this->fileService->delete($item['bank_file']);
                }

                if ($this->model->hardDeleteById($id)) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Purge completed ({$success} items)."
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('purgeBulk() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purgeAll() called', [
            'actor' => $actor
        ]);

        $this->pdo->beginTransaction();

        try {
            $rows = $this->model->getDeleted();
            $success = 0;

            foreach ($rows as $row) {
                if (!empty($row['bank_file'])) {
                    $this->fileService->delete($row['bank_file']);
                }

                if ($this->model->hardDeleteById($row['id'])) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "Purge all completed ({$success} items)."
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('purgeAll() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function reorder(array $changes): bool
    {
        $this->logger->info('reorder() called', [
            'changes' => $changes
        ]);

        if (empty($changes)) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \Exception('Invalid reorder payload.');
                }
            }

            foreach ($changes as $row) {
                $tempSortNo = (int)$row['newSortNo'] + 1000000;
                $this->model->updateSortNo($row['id'], $tempSortNo);
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], (int)$row['newSortNo']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->logger->info('reorder() success');
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('reorder() failed', [
                'exception' => $e->getMessage(),
                'changes' => $changes
            ]);

            throw $e;
        }
    }

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $headers = $this->buildHeaders($columns);
        $rows = [$this->buildTemplateSampleRow($columns)];

        $this->writeSpreadsheet($headers, $rows, '계좌 업로드', 'bank_account_template.xlsx', $columns, true);
        return;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('계좌 업로드');
        $headers = ['계좌명', '은행명', '계좌번호', '예금주', '계좌유형', '통화', '상태', '비고', '메모'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([['운영계좌', '기업은행', '123-456-789012', '수향', '보통예금', 'KRW', '사용', '', '']], null, 'A2');
        foreach (range('A', 'I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="bank_account_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function saveFromExcelFile(string $filePath, ?string $columnsCsv = null): array
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);

        if (empty($rows) || count($rows) < 2) {
            return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
        }

        $headerRow = array_map(fn($value) => trim((string) $value), array_shift($rows));
        $headerMap = $this->buildHeaderIndexMap($headerRow, $columns);
        $missingRequired = $this->findMissingRequiredColumns($columns, $headerMap);

        if ($missingRequired !== []) {
            return [
                'success' => false,
                'message' => '필수 컬럼이 누락되었습니다: ' . implode(', ', $missingRequired),
            ];
        }

        $count = 0;
        $requiredValueErrors = [];
        foreach ($rows as $index => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $payload = $this->buildUploadPayload($row, $headerMap, $columns);
            $missingRequiredValues = $this->findMissingRequiredValues($payload, $columns);
            if ($missingRequiredValues !== []) {
                $rowNo = $index + 2;
                foreach ($missingRequiredValues as $label) {
                    $requiredValueErrors[] = sprintf('%d행 : %s 필수', $rowNo, $label);
                }
                continue;
            }

            if (($payload['account_name'] ?? '') === '') {
                continue;
            }

            $result = $this->save($payload, 'SYSTEM');
            if (!empty($result['success'])) {
                $count++;
            }
        }

        if ($requiredValueErrors !== []) {
            return [
                'success' => false,
                'message' => "업로드할 수 없습니다.\n\n" . implode("\n", array_values(array_unique($requiredValueErrors))),
            ];
        }

        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        if (empty($rows) || count($rows) < 2) { return ['success' => false, 'message' => '업로드할 데이터가 없습니다.']; }
        $header = array_map(fn($v) => trim((string)$v), array_shift($rows));
        $map = array_flip($header);
        $count = 0;
        foreach ($rows as $row) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) { continue; }
            $payload = [
                'account_name' => trim((string)($row[$map['계좌명'] ?? -1] ?? '')),
                'bank_name' => trim((string)($row[$map['은행명'] ?? -1] ?? '')),
                'account_number' => trim((string)($row[$map['계좌번호'] ?? -1] ?? '')),
                'account_holder' => trim((string)($row[$map['예금주'] ?? -1] ?? '')),
                'account_type' => trim((string)($row[$map['계좌유형'] ?? -1] ?? '')),
                'currency' => trim((string)($row[$map['통화'] ?? -1] ?? 'KRW')) ?: 'KRW',
                'is_active' => trim((string)($row[$map['상태'] ?? -1] ?? '사용')) === '미사용' ? 0 : 1,
                'note' => trim((string)($row[$map['비고'] ?? -1] ?? '')),
                'memo' => trim((string)($row[$map['메모'] ?? -1] ?? '')),
            ];
            if ($payload['account_name'] === '') { continue; }
            $result = $this->save($payload, 'SYSTEM');
            if (!empty($result['success'])) { $count++; }
        }
        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
    }

    public function downloadExcel(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('download', $columnsCsv);
        $accounts = ExcelValueFormatterHelper::sortRowsBySortNo($this->model->getList());
        $rows = [];

        foreach ($accounts as $account) {
            $rows[] = $this->buildDownloadRow($account, $columns);
        }

        $this->writeSpreadsheet(
            $this->buildHeaders($columns),
            $rows,
            '계좌 목록',
            'bank_account_list.xlsx',
            $columns
        );
        return;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('계좌 목록');
        $sheet->fromArray(['순번', '계좌명', '은행명', '계좌번호', '예금주', '계좌유형', '통화', '상태', '비고', '메모'], null, 'A1');
        $rowNo = 2;
        foreach ($accounts as $account) {
            $sheet->fromArray([[$account['sort_no'] ?? '', $account['account_name'] ?? '', $account['bank_name'] ?? '', $account['account_number'] ?? '', $account['account_holder'] ?? '', $account['account_type'] ?? '', $account['currency'] ?? '', !empty($account['is_active']) ? '사용' : '미사용', $account['note'] ?? '', $account['memo'] ?? '']], null, 'A' . $rowNo);
            $rowNo++;
        }
        foreach (range('A', 'J') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="bank_account_list.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }
    private function resolveColumns(string $type, ?string $columnsCsv = null): array
    {
        $columnsByKey = [];
        foreach (self::COLUMN_DEFINITIONS as $column) {
            $columnsByKey[$column['key']] = $column;
        }

        $requestedKeys = $this->parseColumnsCsv($columnsCsv);
        $selectedKeys = [];

        if ($requestedKeys === []) {
            foreach (self::COLUMN_DEFINITIONS as $column) {
                if ($column['required'] || $column[$type . '_default']) {
                    $selectedKeys[] = $column['key'];
                }
            }
        } else {
            foreach ($requestedKeys as $key) {
                if (isset($columnsByKey[$key])) {
                    $selectedKeys[] = $key;
                }
            }
        }

        if ($selectedKeys === []) {
            return $this->resolveColumns($type, '');
        }

        $selectedColumns = [];
        foreach ($selectedKeys as $key) {
            $selectedColumns[] = $columnsByKey[$key];
        }

        return $this->decorateColumns($selectedColumns);
    }

    private function parseColumnsCsv(?string $columnsCsv): array
    {
        $resolved = trim((string) $columnsCsv);
        if ($resolved === '') {
            return [];
        }

        $keys = array_map('trim', explode(',', $resolved));
        $keys = array_values(array_filter($keys, static fn($key) => $key !== ''));

        return array_values(array_unique($keys));
    }

    private function decorateColumns(array $columns): array
    {
        $displayNameMap = ColumnPolicyRequestHelper::displayNameMap($_REQUEST['column_display_name'] ?? null);
        $requirementPolicyMap = ColumnPolicyRequestHelper::requirementPolicyMap($_REQUEST['column_requirement_policy'] ?? null);

        return array_map(static function (array $column) use ($displayNameMap, $requirementPolicyMap): array {
            $label = ColumnPolicyRequestHelper::displayNameForColumn($column, $displayNameMap, (string) ($column['label'] ?? ''));
            $policy = ColumnPolicyRequestHelper::requirementPolicyForColumn(
                $column,
                $requirementPolicyMap,
                !empty($column['required']) ? 'required' : 'none'
            );
            return $column + [
                'label' => $label,
                'required' => $policy === 'required',
                'requirement_policy' => $policy,
                'header' => $label,
                'source_key' => $column['source_key'] ?? $column['key'],
                'payload_key' => $column['payload_key'] ?? $column['key'],
            ];
        }, $columns);
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => $column['header'], $columns);
    }

    private function buildTemplateSampleRow(array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = self::SAMPLE_ROW[$column['key']] ?? '';
        }

        return $row;
    }

    private function buildDownloadRow(array $record, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = $this->exportCellValue($record, $column);
        }

        return $row;
    }

    private function exportCellValue(array $record, array $column): mixed
    {
        $sourceKey = $column['source_key'];
        $value = $record[$sourceKey] ?? $record[$column['key']] ?? '';

        if ($column['key'] === 'is_active') {
            return !empty($value) ? '사용' : '미사용';
        }

        return $value;
    }

    private function buildHeaderIndexMap(array $headerRow, array $columns): array
    {
        $lookup = [];
        foreach ($columns as $column) {
            $lookup[$column['header']] = $column['key'];
            $lookup[$column['label']] = $column['key'];
            if (($column['requirement_policy'] ?? 'none') !== 'none') {
                $lookup[$column['header'] . ' *'] = $column['key'];
                $lookup[$column['label'] . ' *'] = $column['key'];
            }
            $lookup[$column['key']] = $column['key'];
        }

        $indexMap = [];
        foreach ($headerRow as $index => $header) {
            $trimmed = trim((string) $header);
            if ($trimmed === '' || !isset($lookup[$trimmed])) {
                continue;
            }

            $key = $lookup[$trimmed];
            if (!array_key_exists($key, $indexMap)) {
                $indexMap[$key] = $index;
            }
        }

        return $indexMap;
    }

    private function findMissingRequiredColumns(array $columns, array $headerMap): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if ($column['required'] && !array_key_exists($column['key'], $headerMap)) {
                $missing[] = $column['label'];
            }
        }

        return $missing;
    }

    private function findMissingRequiredValues(array $payload, array $columns): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if (empty($column['required'])) {
                continue;
            }

            $payloadKey = (string) ($column['payload_key'] ?? $column['key'] ?? '');
            if ($payloadKey === '') {
                continue;
            }

            $value = $payload[$payloadKey] ?? null;
            if (is_array($value)) {
                if ($value === []) {
                    $missing[] = $column['label'];
                }
                continue;
            }

            if (trim((string) $value) === '') {
                $missing[] = $column['label'];
            }
        }

        return $missing;
    }

    private function buildUploadPayload(array $row, array $headerMap, array $columns): array
    {
        $payload = [];

        foreach ($columns as $column) {
            if (empty($column['allow_upload']) || !array_key_exists($column['key'], $headerMap)) {
                continue;
            }

            $rawValue = $row[$headerMap[$column['key']]] ?? '';
            $payloadKey = $column['payload_key'];

            if ($payloadKey === 'is_active') {
                $payload[$payloadKey] = $this->parseExcelActiveValue($rawValue);
                continue;
            }

            if ($payloadKey === 'currency') {
                $payload[$payloadKey] = trim((string) $rawValue) !== '' ? trim((string) $rawValue) : 'KRW';
                continue;
            }

            $payload[$payloadKey] = trim((string) $rawValue);
        }

        if (($payload['currency'] ?? '') === '') {
            $payload['currency'] = 'KRW';
        }

        if (!array_key_exists('is_active', $payload)) {
            $payload['is_active'] = 1;
        }

        return $payload;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function writeSpreadsheet(
        array $headers,
        array $rows,
        string $title,
        string $filename,
        array $columns = [],
        bool $showRequiredAsterisk = false
    ): void
    {
        $filename = ExcelTemplateFilenameHelper::normalize($filename, 'bank_account');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns, [
            'showRequiredAsterisk' => $showRequiredAsterisk,
        ]);

        for ($index = 1; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    private function parseExcelActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '사용'], true) ? 1 : 0;

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '사용'], true) ? 1 : 0;
    }
}
