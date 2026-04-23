<?php
// ?嚥▲굧???뚪뜮? PROJECT_ROOT . '/app/Services/System/CardService.php'

namespace App\Services\System;

use PDO;
use App\Models\System\CardModel;
use App\Models\System\ClientModel;
use App\Models\System\BankAccountModel;
use App\Services\System\BankAccountService;
use App\Services\System\ClientService;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CardService
{
    private PDO $pdo;
    private CardModel $model;
    private ClientModel $clientModel;
    private BankAccountModel $bankAccountModel;
    private BankAccountService $accountService;
    private ClientService $clientService;
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new CardModel($pdo);
        $this->clientModel = new ClientModel($pdo);
        $this->bankAccountModel = new BankAccountModel($pdo);
        $this->accountService  = new BankAccountService($pdo);
        $this->clientService  = new ClientService($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.CardService');

        $this->logger->info('CardService initialized');
    }

    /* ============================================================
    * ????썹땟???꿔꺂??袁ㅻ븶筌믠뫀萸???됰슦????
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
    * ??壤굿??뺥떑 ??됰슦????(id ???뚯???)
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
    * ??⑤㈇?????嚥▲굧????(Service - Select2 ????
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

                // ?????⑤㈇??????ル뎨????⑸룺 ???ㅻ쿋??
                if (!empty($row['card_number'])) {
                    $text .= ' (' . $row['card_number'] . ')';
                }

                // ????꿸쑨????亦??????????ㅻ쿋??
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
    * ????(???꾩룆???+ ????볥궚??
    * ========================================================= */
    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);
        $data['client_id'] = $this->normalizeNullableId($data['client_id'] ?? null);
        $data['account_id'] = $this->normalizeNullableId($data['account_id'] ?? null);
        $data['limit_amount'] = (float)($data['limit_amount'] ?? 0);
        $id = trim((string)($data['id'] ?? ''));
        $mode = $id === '' ? 'CREATE' : 'UPDATE';
        $isCreate = ($mode === 'CREATE');

        $this->logger->info('save() called', [
            'mode' => $mode,
            'id' => $id,
            'actor' => $actor
        ]);

        try {
            $this->assertRelations($data);
            $this->pdo->beginTransaction();

            if (!$isCreate) {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('燁삳?諭??類ｋ궖??筌≪뼚??????곷뮸??덈뼄.');
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
                    $this->assertUploadOk($file, '燁삳?諭????筌왖');
                }

                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $upload = $this->fileService->uploadCardCopy($file);
                    if (!$upload['success']) {
                        throw new \Exception($upload['message'] ?? '燁삳?諭????筌왖 ??낆쨮??뽯퓠 ??쎈솭??됰뮸??덈뼄.');
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
                    throw new \Exception('燁삳?諭???륁젟????쎈솭??됰뮸??덈뼄.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $before['sort_no'] ?? null,
                    'message' => '??륁젟???袁⑥┷??뤿???щ빍??'
                ];
            }

            $file = $files['card_file'] ?? null;
            if ($file) {
                $this->assertUploadOk($file, '燁삳?諭????筌왖');
            }

            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = $this->fileService->uploadCardCopy($file);
                if (!$upload['success']) {
                    throw new \Exception($upload['message'] ?? '燁삳?諭????筌왖 ??낆쨮??뽯퓠 ??쎈솭??됰뮸??덈뼄.');
                }
                $data['card_file'] = $upload['db_path'];
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
                throw new \Exception('燁삳?諭??源낆쨯????쎈솭??됰뮸??덈뼄.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'sort_no' => $newSortNo,
                'message' => '?源낆쨯???袁⑥┷??뤿???щ빍??'
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

    private function normalizeNullableId(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function assertRelations(array $data): void
    {
        if ($data['client_id'] !== null) {
            $client = $this->clientModel->getById($data['client_id']);

            if (!$client) {
                throw new \Exception('?醫뤾문??燁삳?諭??? 筌≪뼚??????곷뮸??덈뼄.');
            }

            if (!in_array((string)($client['client_type'] ?? ''), ['카드사', 'CARD_COMPANY'], true)) {
                throw new \Exception('燁삳?諭??以??源낆쨯??椰꾧퀡?믭㎗?롮춸 ?醫뤾문??????됰뮸??덈뼄.');
            }

            if ((int)($client['is_active'] ?? 0) !== 1 || !empty($client['deleted_at'])) {
                throw new \Exception('????揶쎛?館釉?燁삳?諭??彛??醫뤾문??????됰뮸??덈뼄.');
            }
        }

        if ($data['account_id'] !== null && !$this->bankAccountModel->getById($data['account_id'])) {
            throw new \Exception('?醫뤾문??野껉퀣?ｆ④쑴伊뽫몴?筌≪뼚??????곷뮸??덈뼄.');
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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "{$label} ???뵬????낆쨮????몄쎗 ??쀫립???λ뜃???됰뮸??덈뼄.",
            UPLOAD_ERR_PARTIAL => "{$label} ??낆쨮??? 餓λ쵌而??餓λ쵎???뤿???щ빍??",
            UPLOAD_ERR_NO_TMP_DIR => "{$label} ??낆쨮??뽰뒠 ?袁⑸뻻 ???묊몴?筌≪뼚??????곷뮸??덈뼄.",
            UPLOAD_ERR_CANT_WRITE => "??뺤쒔揶쎛 {$label} ???뵬?????館釉?쭪? 筌륁궢六??щ빍??",
            UPLOAD_ERR_EXTENSION => "??뺤쒔 ?類ㅼ삢 筌뤴뫀諭??{$label} ??낆쨮??? 筌△뫀???됰뮸??덈뼄.",
            default => "{$label} ??낆쨮??餓???살첒揶쎛 獄쏆뮇源??됰뮸??덈뼄.",
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
                    'message' => '??됰슦?????? ?????놃닓 ??⑤㈇????????뉖뤁??'
                ];
            }

            // ???????ㅻ쿋??癲ル슢?????嶺?獄??嶺뚮㉡??㎘???????????? ????⑤９??

            if (!$this->model->deleteById($id, $actor)) {

                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '??⑤㈇?????????????곌숯'
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
    * ??????꿔꺂??袁ㅻ븶筌믠뫀萸?
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
    * ??⑤슢?뽫뵓??
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
                    'message' => '??됰슦?????? ?????놃닓 ??⑤㈇????????뉖뤁??'
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {

                $this->logger->error('restore() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '??⑤㈇??????⑤슢?뽫뵓???????곌숯'
                ];
            }

            $this->logger->info('restore() success', ['id' => $id]);

            return [
                'success' => true,
                'message' => '癰귣벀?꾢첎? ?袁⑥┷??뤿???щ빍??'
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
    * ????ｋ????⑤슢?뽫뵓??
    * ========================================================= */
    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids'   => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID가 없습니다.'];
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
                'message' => "선택한 카드가 복원되었습니다. ($success건)"
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
    * ????썹땟????⑤슢?뽫뵓??
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
                'message' => "삭제된 카드가 모두 복원되었습니다. ({$success}건)"
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
    * ????썹땟?????
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
                'message' => '??됰슦?????? ?????놃닓 ??⑤㈇????????뉖뤁??'
            ];
        }

        $this->pdo->beginTransaction();

        try {

            /* ???????????????????????*/
            if (!empty($item['card_file'])) {
                $this->fileService->delete($item['card_file']);
            }

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {
                throw new \Exception('DB ?????????곌숯');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '?袁⑹읈 ???ｅ첎? ?袁⑥┷??뤿???щ빍??'
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            $this->logger->error('purge() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '?袁⑹읈 ???????쎈솭??됰뮸??덈뼄.'
            ];
        }
    }

    /* =========================================================
    * ????ｋ??????썹땟?????
    * ========================================================= */
    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purgeBulk() called', [
            'ids'   => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID가 없습니다.'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                /* =========================================================
                * 1???節떷?꾨춴????뚯???????????????됰슦????
                * ========================================================= */
                $item = $this->model->getById($id);

                if (!$item) {
                    continue;
                }

                /* =========================================================
                * 2???節떷?꾨춴??????????
                * ========================================================= */
                if (!empty($item['card_file'])) {
                    $this->fileService->delete($item['card_file']);
                }

                /* =========================================================
                * 3???節떷?꾨춴?DB ????
                * ========================================================= */
                $ok = $this->model->hardDeleteById($id);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "선택한 카드가 완전 삭제되었습니다. ({$success}건)"
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
    * ????썹땟??????썹땟?????
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
                * 1???節떷?꾨춴??????????
                * ========================================================= */
                if (!empty($row['card_file'])) {
                    $this->fileService->delete($row['card_file']);
                }

                /* =========================================================
                * 2???節떷?꾨춴?DB ????
                * ========================================================= */
                $ok = $this->model->hardDeleteById($row['id']);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "?袁⑷퍥 ???ｅ첎? ?袁⑥┷??뤿???щ빍?? ({$success}椰?"
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
    * ??ш끽維?????嶺?筌???⑤슢堉???(RowReorder)
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

            /* 1???節떷?꾨춴?????怨몄７???嚥▲굧????*/
            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newSortNo'])
                ) {
                    throw new \Exception('reorder ?????????????怨몄뵒');
                }
            }

            /* 2???節떷?꾨춴?temp ?????(??롪퍓梨띄댚???熬곣뫖?삥납?) */
            foreach ($changes as $row) {

                // ?癲?????ㅼ뒩?????怨뺤퓡 (??? ??롪퍓梨띄댚??????戮?┝??
                $tempSortNo = (int)$row['newSortNo'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            /* 3???節떷?꾨춴????繹먮냱議???ш끽維???????쇨덫??*/
            foreach ($changes as $row) {

                $this->model->updateSortNo(
                    $row['id'],
                    (int)$row['newSortNo']
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
        $sheet->setTitle('카드 업로드');
        $headers = ['카드명', '카드사', '카드번호', '소유자', '카드유형', '한도금액', '사용여부', '비고', '메모'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([['법인카드', '신한카드', '1234-5678-9012-3456', '홍길동', '법인', '1000000', '사용', '', '']], null, 'A2');
        foreach (range('A', 'I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="card_template.xlsx"');
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
                'card_name' => trim((string)($row[$map['카드명'] ?? -1] ?? '')),
                'card_company' => trim((string)($row[$map['카드사'] ?? -1] ?? '')),
                'card_number' => trim((string)($row[$map['카드번호'] ?? -1] ?? '')),
                'card_holder' => trim((string)($row[$map['소유자'] ?? -1] ?? '')),
                'card_type' => trim((string)($row[$map['카드유형'] ?? -1] ?? '')),
                'limit_amount' => (float)($row[$map['한도금액'] ?? -1] ?? 0),
                'is_active' => trim((string)($row[$map['사용여부'] ?? -1] ?? '사용')) === '미사용' ? 0 : 1,
                'note' => trim((string)($row[$map['비고'] ?? -1] ?? '')),
                'memo' => trim((string)($row[$map['메모'] ?? -1] ?? '')),
            ];
            if ($payload['card_name'] === '') { continue; }
            $result = $this->save($payload, 'SYSTEM');
            if (!empty($result['success'])) { $count++; }
        }
        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
    }

    public function downloadExcel(): void
    {
        $cards = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('카드 목록');
        $sheet->fromArray(['순번', '카드명', '카드사', '카드번호', '소유자', '카드유형', '한도금액', '사용여부', '비고', '메모'], null, 'A1');
        $rowNo = 2;
        foreach ($cards as $card) {
            $sheet->fromArray([[$card['sort_no'] ?? '', $card['card_name'] ?? '', $card['card_company'] ?? '', $card['card_number'] ?? '', $card['card_holder'] ?? '', $card['card_type'] ?? '', $card['limit_amount'] ?? '', !empty($card['is_active']) ? '사용' : '미사용', $card['note'] ?? '', $card['memo'] ?? '']], null, 'A' . $rowNo);
            $rowNo++;
        }
        foreach (range('A', 'J') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="card_list.xlsx"');
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
