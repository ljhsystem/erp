<?php
// ??????轅붽틓????몃마?????????????? PROJECT_ROOT . '/app/Services/System/BankAccountService.php'

namespace App\Services\System;

use PDO;
use App\Models\System\BankAccountModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BankAccountService
{
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
    /* ============================================================
    * ???????????꾩룆梨띰쭕?뚢뵾??????????????嶺뚮죭?댁젘????????癲??????????????癲????????熬곣뫖利당춯??쎾퐲????????耀붾굝????????????????꿔꺂???????????????????????
    * ============================================================ */
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

    /* ============================================================
    * ??????????????됰Ŧ????????????????雍??????????????(id ???????)
    * ============================================================ */
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

    /* =========================================================
    * ????????????????????轅붽틓????몃마?????????(Service - Select2 ????
    * ========================================================= */
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

                // ??????????????????????????????怨뺤꽢???
                if (!empty($row['bank_name'])) {
                    $text = $row['bank_name'] . ' / ' . $text;
                }

                // ?????????????????????????????????????????釉먮폁?????????????????????怨뺤꽢???
                if (!empty($row['account_number'])) {
                    $text .= ' (' . $row['account_number'] . ')';
                }

                // ??????????????????????????怨뺤꽢???
                if (!empty($row['account_holder'])) {
                    $text .= ' - ' . $row['account_holder'];
                }

                $results[] = [
                    'id'   => $row['id'],
                    'text' => $text
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

    /* =========================================================
    * ????(???????????썹땟戮녹??諭?????⑸㎦???????????+ ????????????산뭐?????????
    * ========================================================= */
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
                    'code' => $before['code'] ?? null,
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
            $newCode = CodeHelper::generateBankAccountCode($this->pdo);

            $insertData = array_merge($data, [
                'id' => $newId,
                'code' => $newCode,
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
                'code' => $newCode,
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
    /* =========================================================
    * ????????????????????????怨뺤떪????遺얘턁????????
    * ========================================================= */
    /* =========================================================
    * ????節떷?????ㅼ뒧?戮ル탶??
    * ========================================================= */
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

    /* =========================================================
    * ?????밸븶?????ㅼ뒧?戮ル탶??
    * ========================================================= */
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
    /* =========================================================
    * ?????????
    * ========================================================= */
    /* =========================================================
    * ??⑤??????
    * ========================================================= */
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
    /* =========================================================
    * ?醫뤾문 ?怨대럡????
    * ========================================================= */
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

    /* =========================================================
    * ?袁⑷퍥 ?怨대럡????
    * ========================================================= */
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
    /* ============================================================
    * 肄붾뱶 ?쒖꽌 蹂寃?(RowReorder)
    * ============================================================ */
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
                if (empty($row['id']) || !isset($row['newCode'])) {
                    throw new \Exception('Invalid reorder payload.');
                }
            }

            foreach ($changes as $row) {
                $tempCode = (int)$row['newCode'] + 1000000;
                $this->model->updateCode($row['id'], $tempCode);
            }

            foreach ($changes as $row) {
                $this->model->updateCode($row['id'], (int)$row['newCode']);
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

    /* ============================================================
    * Bank account template download
    * ============================================================ */
    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('BankAccounts');

        $headers = [
            'Account Name',
            'Bank Name',
            'Account Number',
            'Account Holder',
            'Account Type',
            'Currency',
            'Is Active',
            'Note',
            'Memo'
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['Main Operating', 'Kookmin', '123-456-789012', 'Sukhyang Corp', 'Checking', 'KRW', '1', 'Sample note', ''],
            ['Payroll', 'Shinhan', '110-123-456789', 'Sukhyang Corp', 'Checking', 'KRW', '1', 'Payroll account', '']
        ], null, 'A2');

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="bank_account_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }

    /* =========================================================
    * Save from Excel upload
    * ========================================================= */
    public function saveFromExcelFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, false, false);

        if (empty($rows) || count($rows) < 2) {
            return [
                'success' => false,
                'message' => 'No rows found in the uploaded Excel file.'
            ];
        }

        $normalize = static function ($value): string {
            $value = strtolower(trim((string)$value));
            $value = preg_replace('/\s+/', '', $value);
            return $value;
        };

        $headerMap = [
            'accountname' => 'account_name',
            'bankname' => 'bank_name',
            'accountnumber' => 'account_number',
            'accountholder' => 'account_holder',
            'accounttype' => 'account_type',
            'currency' => 'currency',
            'isactive' => 'is_active',
            'note' => 'note',
            'memo' => 'memo'
        ];

        $headers = array_map($normalize, $rows[0]);
        $columnMap = [];

        foreach ($headers as $index => $header) {
            if (isset($headerMap[$header])) {
                $columnMap[$headerMap[$header]] = $index;
            }
        }

        if (!isset($columnMap['account_name'])) {
            return [
                'success' => false,
                'message' => 'The Account Name column is required.'
            ];
        }

        array_shift($rows);
        $count = 0;

        foreach ($rows as $row) {
            if (count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $payload = [
                'account_name' => trim((string)($row[$columnMap['account_name']] ?? '')),
                'bank_name' => trim((string)($row[$columnMap['bank_name']] ?? '')),
                'account_number' => trim((string)($row[$columnMap['account_number']] ?? '')),
                'account_holder' => trim((string)($row[$columnMap['account_holder']] ?? '')),
                'account_type' => trim((string)($row[$columnMap['account_type']] ?? '')),
                'currency' => strtoupper(trim((string)($row[$columnMap['currency']] ?? 'KRW'))) ?: 'KRW',
                'is_active' => in_array(strtolower(trim((string)($row[$columnMap['is_active']] ?? '1'))), ['1', 'true', 'yes', 'use', 'active'], true) ? 1 : 0,
                'note' => trim((string)($row[$columnMap['note']] ?? '')),
                'memo' => trim((string)($row[$columnMap['memo']] ?? '')),
            ];

            if ($payload['account_name'] === '') {
                continue;
            }

            $result = $this->save($payload, 'SYSTEM');
            if ($result['success']) {
                $count++;
            } else {
                $this->logger->warning('Excel row save failed', [
                    'payload' => $payload,
                    'error' => $result['message'] ?? null
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "{$count} rows processed."
        ];
    }

    /* ============================================================
    * Bank account Excel download
    * ============================================================ */
    public function downloadExcel(): void
    {
        $accounts = $this->model->getList();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'Code',
            'B1' => 'Account Name',
            'C1' => 'Bank Name',
            'D1' => 'Account Number',
            'E1' => 'Account Holder',
            'F1' => 'Account Type',
            'G1' => 'Currency',
            'H1' => 'Is Active',
            'I1' => 'Note',
            'J1' => 'Memo',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        $rowIndex = 2;
        foreach ($accounts as $account) {
            $sheet->setCellValue('A' . $rowIndex, $account['code'] ?? '');
            $sheet->setCellValue('B' . $rowIndex, $account['account_name'] ?? '');
            $sheet->setCellValue('C' . $rowIndex, $account['bank_name'] ?? '');
            $sheet->setCellValue('D' . $rowIndex, $account['account_number'] ?? '');
            $sheet->setCellValue('E' . $rowIndex, $account['account_holder'] ?? '');
            $sheet->setCellValue('F' . $rowIndex, $account['account_type'] ?? '');
            $sheet->setCellValue('G' . $rowIndex, $account['currency'] ?? 'KRW');
            $sheet->setCellValue('H' . $rowIndex, !empty($account['is_active']) ? '1' : '0');
            $sheet->setCellValue('I' . $rowIndex, $account['note'] ?? '');
            $sheet->setCellValue('J' . $rowIndex, $account['memo'] ?? '');
            $rowIndex++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="bank_accounts_' . date('Ymd_His') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }
}
