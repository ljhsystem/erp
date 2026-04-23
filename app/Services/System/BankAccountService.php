<?php
// ??????耀붾굝??????筌뤾퍓彛?????????????? PROJECT_ROOT . '/app/Services/System/BankAccountService.php'

namespace App\Services\System;

use PDO;
use App\Models\System\BankAccountModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
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
    * ???????????熬곣뫖利당춯??쎾퐲???????????????????꿔꺂?㏘틠??怨몄젦???????????????????????????????????썹땟戮녹??諭?????⑸㎦???????????븐뼐?????????????????饔낅떽????????????????????????
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
    * ???????????????嫄???????????????????????????????(id ???????)
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
    * ????????????????????耀붾굝??????筌뤾퍓彛?????????(Service - Select2 ????
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

                // ????????????????????????????????ㅻ깹????
                if (!empty($row['bank_name'])) {
                    $text = $row['bank_name'] . ' / ' . $text;
                }

                // ???????????????????????????????????????????거????????????????????????ㅻ깹????
                if (!empty($row['account_number'])) {
                    $text .= ' (' . $row['account_number'] . ')';
                }

                // ????????????????????????????ㅻ깹????
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
    * ????(????????????諛몃마嶺뚮?????????????硫λ젒???????????+ ?????????????怨뺤떪?????????
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
            $newSortNo = null;

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
    /* =========================================================
    * ??????????????????????????ㅻ깹???????釉먮폁?????????
    * ========================================================= */
    /* =========================================================
    * ????壤굿??Β???????곕츥?嶺뚮?爰???
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
    * ?????獄쏅챶留???????곕츥?嶺뚮?爰???
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
    * ?????????
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
    * ???ャ뀕???????????
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
    * ??ш끽維???????????
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
    * ?袁⑤?獄???戮?맋 ?곌떠???(RowReorder)
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

    /* ============================================================
    * Bank account template download
    * ============================================================ */
    public function downloadTemplate(): void
    {
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

    public function saveFromExcelFile(string $filePath): array
    {
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

    public function downloadExcel(): void
    {
        $accounts = $this->model->getList();
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
    private function parseExcelActiveValue(mixed $value): int
    {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '사용'], true) ? 1 : 0;
    }
}
