<?php
// 野껋럥以? PROJECT_ROOT . '/app/Services/System/CardService.php'

namespace App\Services\System;

use PDO;
use App\Models\System\CardModel;
use App\Services\System\BankAccountService;
use App\Services\System\ClientService;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CardService
{
    private PDO $pdo;
    private CardModel $model;
    private BankAccountService $accountService;
    private ClientService $clientService;
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new CardModel($pdo);
        $this->accountService  = new BankAccountService($pdo);
        $this->clientService  = new ClientService($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.CardService');

        $this->logger->info('CardService initialized');
    }

    /* ============================================================
    * ?袁⑷퍥 筌뤴뫖以?鈺곌퀬??
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
    * ??ｊ탷 鈺곌퀬??(id 疫꿸퀣?)
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
    * 燁삳?諭?野꺜??(Service - Select2 ????
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

                $text = $row['card_name'] ?? '';

                // ?逾?燁삳?諭띈린?딆깈 ?곕떽?
                if (!empty($row['card_number'])) {
                    $text .= ' (' . $row['card_number'] . ')';
                }

                // ?逾?椰꾧퀡?믭㎗?롮구 ?곕떽?
                if (!empty($row['client_name'])) {
                    $text .= ' / ' . $row['client_name'];
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
    * ????(??밴쉐 + ??륁젟)
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
                    throw new \Exception('Card not found.');
                }

                if (!empty($data['delete_card_file']) && $data['delete_card_file'] == '1') {
                    if (!empty($before['card_file'])) {
                        $this->fileService->delete($before['card_file']);
                    }
                    $data['card_file'] = null;
                } else {
                    $data['card_file'] = $before['card_file'] ?? null;
                }

                $file = $files['card_file'] ?? null;
                if ($file) {
                    $this->assertUploadOk($file, 'card image');
                }

                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $upload = $this->fileService->uploadCardCopy($file);
                    if (!$upload['success']) {
                        throw new \Exception($upload['message'] ?? 'Card image upload failed.');
                    }

                    if (!empty($before['card_file']) && $before['card_file'] !== ($upload['db_path'] ?? null)) {
                        $this->fileService->delete($before['card_file']);
                    }

                    $data['card_file'] = $upload['db_path'];
                }

                $data['updated_by'] = $actor;
                $updateData = $data;
                unset($updateData['id']);

                if (!$this->model->updateById($id, $updateData)) {
                    throw new \Exception('Failed to update card.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'code' => $before['code'] ?? null,
                    'message' => 'Update completed.'
                ];
            }

            $file = $files['card_file'] ?? null;
            if ($file) {
                $this->assertUploadOk($file, 'card image');
            }

            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = $this->fileService->uploadCardCopy($file);
                if (!$upload['success']) {
                    throw new \Exception($upload['message'] ?? 'Card image upload failed.');
                }
                $data['card_file'] = $upload['db_path'];
            }

            $newId = UuidHelper::generate();
            $newCode = CodeHelper::generateCardCode($this->pdo);

            $insertData = array_merge($data, [
                'id' => $newId,
                'code' => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('Failed to create card.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'code' => $newCode,
                'message' => 'Create completed.'
            ];
        } catch (\Throwable $e) {
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

    /* ============================================================
    * ????
    * ============================================================ */
    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('delete() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        try {
    
            $item = $this->model->getById($id);
    
            if (!$item) {
                $this->logger->warning('delete() not found', ['id' => $id]);
                return [
                    'success' => false,
                    'message' => '鈺곕똻???? ??낅뮉 燁삳?諭??낅빍??'
                ];
            }
    
            // ?逾???곕늄?紐꾧텣??뽯퓠??뺣뮉 ???뵬 ?????? ??놁벉
    
            if (!$this->model->deleteById($id, $actor)) {
    
                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);
    
                return [
                    'success' => false,
                    'message' => '燁삳?諭???????쎈솭'
                ];
            }
    
            $this->logger->info('delete() success', ['id' => $id]);
    
            return ['success' => true];
    
        } catch (\Throwable $e) {
    
            $this->logger->error('delete() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* =========================================================
    * ?????筌뤴뫖以?
    * ========================================================= */
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

    /* =========================================================
    * 癰귣벊??
    * ========================================================= */
    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restore() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $item = $this->model->getById($id);

            if (!$item) {
                $this->logger->warning('restore() not found', ['id' => $id]);

                return [
                    'success' => false,
                    'message' => '鈺곕똻???? ??낅뮉 燁삳?諭??낅빍??'
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {

                $this->logger->error('restore() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '燁삳?諭?癰귣벊????쎈솭'
                ];
            }

            $this->logger->info('restore() success', ['id' => $id]);

            return [
                'success' => true,
                'message' => '癰귣벊???袁⑥┷'
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restore() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* =========================================================
    * ?醫뤾문 癰귣벊??
    * ========================================================= */
    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids'   => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID ??곸벉'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                $ok = $this->model->restoreById($id, $actor);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "癰귣벊???袁⑥┷ ({$success}椰?"
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
    * ?袁⑷퍥 癰귣벊??
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

                $ok = $this->model->restoreById($row['id'], $actor);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "?袁⑷퍥 癰귣벊???袁⑥┷ ({$success}椰?"
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
    * ?袁⑹읈????
    * ========================================================= */
    public function purge(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purge() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        $item = $this->model->getById($id);
    
        if (!$item) {
            return [
                'success' => false,
                'message' => '鈺곕똻???? ??낅뮉 燁삳?諭??낅빍??'
            ];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            /* ?逾??怨대럡?????????뵬 ????*/
            if (!empty($item['card_file'])) {
                $this->fileService->delete($item['card_file']);
            }
    
            $ok = $this->model->hardDeleteById($id);
    
            if (!$ok) {
                throw new \Exception('DB ??????쎈솭');
            }
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => '?袁⑹읈?????袁⑥┷'
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            $this->logger->error('purge() failed', [
                'error' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => '??????쎈솭'
            ];
        }
    }

    /* =========================================================
    * ?醫뤾문 ?袁⑹읈????
    * ========================================================= */
    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purgeBulk() called', [
            'ids'   => $ids,
            'actor' => $actor
        ]);
    
        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID ??곸벉'];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            $success = 0;
    
            foreach ($ids as $id) {
    
                /* =========================================================
                * 1?るㅄ源?疫꿸퀣???怨쀬뵠??鈺곌퀬??
                * ========================================================= */
                $item = $this->model->getById($id);
    
                if (!$item) {
                    continue;
                }
    
                /* =========================================================
                * 2?るㅄ源????뵬 ????
                * ========================================================= */
                if (!empty($item['card_file'])) {
                    $this->fileService->delete($item['card_file']);
                }
    
                /* =========================================================
                * 3?るㅄ源?DB ????
                * ========================================================= */
                $ok = $this->model->hardDeleteById($id);
    
                if ($ok) $success++;
            }
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => "?????袁⑥┷ ({$success}椰?"
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
    * ?袁⑷퍥 ?袁⑹읈????
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

                /* =========================================================
                * 1?るㅄ源????뵬 ????
                * ========================================================= */
                if (!empty($row['card_file'])) {
                    $this->fileService->delete($row['card_file']);
                }

                /* =========================================================
                * 2?るㅄ源?DB ????
                * ========================================================= */
                $ok = $this->model->hardDeleteById($row['id']);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "?袁⑷퍥 ?????袁⑥┷ ({$success}椰?"
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
    * ?꾨뗀諭???뽮퐣 癰궰野?(RowReorder)
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

            /* 1?るㅄ源???낆젾揶?野꺜筌?*/
            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newCode'])
                ) {
                    throw new \Exception('reorder ?怨쀬뵠????살첒');
                }
            }

            /* 2?るㅄ源?temp ??猷?(?겸뫖猷?獄쎻뫗?) */
            foreach ($changes as $row) {

                // ?紐???곌석??띿쓺 (??? ?겸뫖猷???덇돌野?
                $tempCode = (int)$row['newCode'] + 1000000;

                $this->model->updateCode(
                    $row['id'],
                    $tempCode
                );
            }

            /* 3?るㅄ源???쇱젫 ?꾨뗀諭??怨몄뒠 */
            foreach ($changes as $row) {

                $this->model->updateCode(
                    $row['id'],
                    (int)$row['newCode']
                );
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
    * Card template download
    * ============================================================ */
    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cards');

        $headers = [
            'Card Name',
            'Client Name',
            'Card Number',
            'Card Type',
            'Account Name',
            'Expiry Year',
            'Expiry Month',
            'Currency',
            'Limit Amount',
            'Is Active',
            'Note',
            'Memo'
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['Corporate Main', 'Kookmin Card', '1234-5678-9012-3456', 'corporate', 'Main Operating', '2028', '12', 'KRW', '5000000', '1', 'Sample note', ''],
            ['Travel Card', 'Shinhan Card', '1111-2222-3333-4444', 'corporate', 'Payroll', '2027', '06', 'KRW', '3000000', '1', 'Travel use', '']
        ], null, 'A2');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="card_template.xlsx"');
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
            'cardname' => 'card_name',
            'clientname' => 'client_name',
            'cardnumber' => 'card_number',
            'cardtype' => 'card_type',
            'accountname' => 'account_name',
            'expiryyear' => 'expiry_year',
            'expirymonth' => 'expiry_month',
            'currency' => 'currency',
            'limitamount' => 'limit_amount',
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

        if (!isset($columnMap['card_name']) || !isset($columnMap['card_number'])) {
            return [
                'success' => false,
                'message' => 'Card Name and Card Number columns are required.'
            ];
        }

        array_shift($rows);
        $count = 0;

        foreach ($rows as $row) {
            if (count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            $clientId = null;
            if (isset($columnMap['client_name'])) {
                $clientName = trim((string)($row[$columnMap['client_name']] ?? ''));
                if ($clientName !== '') {
                    $clients = $this->clientService->searchPicker($clientName);
                    foreach ($clients as $client) {
                        if (($client['text'] ?? '') === $clientName) {
                            $clientId = $client['id'];
                            break;
                        }
                    }
                }
            }

            $accountId = null;
            if (isset($columnMap['account_name'])) {
                $accountName = trim((string)($row[$columnMap['account_name']] ?? ''));
                if ($accountName !== '') {
                    $accounts = $this->accountService->searchPicker($accountName);
                    foreach ($accounts as $account) {
                        if (str_contains(($account['text'] ?? ''), $accountName)) {
                            $accountId = $account['id'];
                            break;
                        }
                    }
                }
            }

            $payload = [
                'card_name' => trim((string)($row[$columnMap['card_name']] ?? '')),
                'card_number' => trim((string)($row[$columnMap['card_number']] ?? '')),
                'card_type' => trim((string)($row[$columnMap['card_type']] ?? 'corporate')) ?: 'corporate',
                'client_id' => $clientId,
                'account_id' => $accountId,
                'expiry_year' => trim((string)($row[$columnMap['expiry_year']] ?? '')),
                'expiry_month' => trim((string)($row[$columnMap['expiry_month']] ?? '')),
                'currency' => strtoupper(trim((string)($row[$columnMap['currency']] ?? 'KRW'))) ?: 'KRW',
                'limit_amount' => (float)($row[$columnMap['limit_amount']] ?? 0),
                'is_active' => in_array(strtolower(trim((string)($row[$columnMap['is_active']] ?? '1'))), ['1', 'true', 'yes', 'use', 'active'], true) ? 1 : 0,
                'note' => trim((string)($row[$columnMap['note']] ?? '')),
                'memo' => trim((string)($row[$columnMap['memo']] ?? '')),
            ];

            if ($payload['card_name'] === '' || $payload['card_number'] === '') {
                continue;
            }

            $result = $this->save($payload, 'SYSTEM');
            if ($result['success']) {
                $count++;
            } else {
                $this->logger->warning('Excel save failed', [
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
    * Card Excel download
    * ============================================================ */
    public function downloadExcel(): void
    {
        $cards = $this->model->getList();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'Code',
            'B1' => 'Card Name',
            'C1' => 'Client Name',
            'D1' => 'Card Number',
            'E1' => 'Card Type',
            'F1' => 'Account Name',
            'G1' => 'Expiry Year',
            'H1' => 'Expiry Month',
            'I1' => 'Currency',
            'J1' => 'Limit Amount',
            'K1' => 'Is Active',
            'L1' => 'Note',
            'M1' => 'Memo',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        $rowIndex = 2;
        foreach ($cards as $card) {
            $sheet->setCellValue('A' . $rowIndex, $card['code'] ?? '');
            $sheet->setCellValue('B' . $rowIndex, $card['card_name'] ?? '');
            $sheet->setCellValue('C' . $rowIndex, $card['client_name'] ?? '');
            $sheet->setCellValue('D' . $rowIndex, $card['card_number'] ?? '');
            $sheet->setCellValue('E' . $rowIndex, $card['card_type'] ?? '');
            $sheet->setCellValue('F' . $rowIndex, $card['account_name'] ?? '');
            $sheet->setCellValue('G' . $rowIndex, $card['expiry_year'] ?? '');
            $sheet->setCellValue('H' . $rowIndex, $card['expiry_month'] ?? '');
            $sheet->setCellValue('I' . $rowIndex, $card['currency'] ?? 'KRW');
            $sheet->setCellValue('J' . $rowIndex, $card['limit_amount'] ?? 0);
            $sheet->setCellValue('K' . $rowIndex, !empty($card['is_active']) ? '1' : '0');
            $sheet->setCellValue('L' . $rowIndex, $card['note'] ?? '');
            $sheet->setCellValue('M' . $rowIndex, $card['memo'] ?? '');
            $rowIndex++;
        }

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cards_' . date('Ymd_His') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }
}
