<?php
// Path: PROJECT_ROOT . '/app/Services/System/CardService.php'

namespace App\Services\System;

use PDO;
use App\Models\System\CardModel;
use App\Models\System\ClientModel;
use App\Models\System\BankAccountModel;
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
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CardModel($pdo);
        $this->clientModel = new ClientModel($pdo);
        $this->bankAccountModel = new BankAccountModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.CardService');
    }

    public function getList(array $filters = []): array
    {
        try {
            return $this->model->getList($filters);
        } catch (\Throwable $e) {
            $this->logger->error('getList() failed', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById() failed', ['id' => $id, 'exception' => $e->getMessage()]);
            return null;
        }
    }

    public function searchPicker(string $keyword): array
    {
        try {
            $rows = $this->model->searchPicker($keyword, 20);

            return array_map(static function (array $row): array {
                $text = $row['card_name'] ?? '';

                if (!empty($row['card_number'])) {
                    $text .= ' (' . $row['card_number'] . ')';
                }

                if (!empty($row['client_name'])) {
                    $text .= ' / ' . $row['client_name'];
                }

                return [
                    'id' => $row['id'],
                    'text' => $text,
                ];
            }, $rows);
        } catch (\Throwable $e) {
            $this->logger->error('searchPicker() failed', [
                'keyword' => $keyword,
                'exception' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);
        $id = trim((string)($data['id'] ?? ''));
        $isCreate = $id === '';

        $data = $this->normalizePayload($data);

        try {
            $this->assertRelations($data);
            $this->pdo->beginTransaction();

            if ($isCreate) {
                $newId = UuidHelper::generate();

                $file = $files['card_file'] ?? null;
                $this->assertUploadOk($file, '카드 이미지');

                if ($this->isUploadedFile($file)) {
                    $upload = $this->fileService->uploadCardCopy($file);
                    if (!$upload['success']) {
                        throw new \Exception($upload['message'] ?? '카드 이미지 업로드에 실패했습니다.');
                    }
                    $data['card_file'] = $upload['db_path'];
                }

                $insertData = array_merge($data, [
                    'id' => $newId,
                    'sort_no' => null,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);

                if (!$this->model->create($insertData)) {
                    throw new \Exception('카드를 등록하지 못했습니다.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $newId,
                    'message' => '등록되었습니다.',
                ];
            }

            $before = $this->model->getById($id);
            if (!$before) {
                throw new \Exception('카드 정보를 찾을 수 없습니다.');
            }

            $data['card_file'] = $before['card_file'] ?? null;

            if (!empty($data['delete_card_file']) && $data['delete_card_file'] === '1') {
                if (!empty($before['card_file'])) {
                    $this->fileService->delete($before['card_file']);
                }
                $data['card_file'] = null;
            }

            $file = $files['card_file'] ?? null;
            $this->assertUploadOk($file, '카드 이미지');

            if ($this->isUploadedFile($file)) {
                $upload = $this->fileService->uploadCardCopy($file);
                if (!$upload['success']) {
                    throw new \Exception($upload['message'] ?? '카드 이미지 업로드에 실패했습니다.');
                }

                if (!empty($before['card_file']) && $before['card_file'] !== ($upload['db_path'] ?? null)) {
                    $this->fileService->delete($before['card_file']);
                }

                $data['card_file'] = $upload['db_path'];
            }

            $data['updated_by'] = $actor;
            unset($data['id']);

            if (!$this->model->updateById($id, $data)) {
                throw new \Exception('카드 정보를 수정하지 못했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $id,
                'sort_no' => $before['sort_no'] ?? null,
                'message' => '수정되었습니다.',
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            if (!$this->model->getById($id)) {
                return ['success' => false, 'message' => '카드 정보를 찾을 수 없습니다.'];
            }

            if (!$this->model->deleteById($id, $actor)) {
                return ['success' => false, 'message' => '카드를 삭제하지 못했습니다.'];
            }

            return ['success' => true, 'message' => '삭제되었습니다.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTrashList(): array
    {
        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() failed', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            if (!$this->model->getById($id)) {
                return ['success' => false, 'message' => '카드 정보를 찾을 수 없습니다.'];
            }

            if (!$this->model->restoreById($id, $actor)) {
                return ['success' => false, 'message' => '카드를 복원하지 못했습니다.'];
            }

            return ['success' => true, 'message' => '복원되었습니다.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => '복원할 카드가 없습니다.'];
        }

        $actor = ActorHelper::resolve($actorType);
        $success = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($ids as $id) {
                if ($this->model->restoreById((string)$id, $actor)) {
                    $success++;
                }
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => "선택한 카드가 복원되었습니다. ({$success}건)"];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $rows = $this->model->getDeleted();
        return $this->restoreBulk(array_column($rows, 'id'), $actorType);
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        try {
            $item = $this->model->getById($id);
            if (!$item) {
                return ['success' => false, 'message' => '카드 정보를 찾을 수 없습니다.'];
            }

            $this->pdo->beginTransaction();

            if (!empty($item['card_file'])) {
                $this->fileService->delete($item['card_file']);
            }

            if (!$this->model->hardDeleteById($id)) {
                throw new \Exception('카드를 영구삭제하지 못했습니다.');
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => '영구삭제되었습니다.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => '영구삭제할 카드가 없습니다.'];
        }

        $success = 0;
        $this->pdo->beginTransaction();

        try {
            foreach ($ids as $id) {
                $item = $this->model->getById((string)$id);
                if (!$item) continue;

                if (!empty($item['card_file'])) {
                    $this->fileService->delete($item['card_file']);
                }

                if ($this->model->hardDeleteById((string)$id)) {
                    $success++;
                }
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => "선택한 카드가 영구삭제되었습니다. ({$success}건)"];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        $rows = $this->model->getDeleted();
        return $this->purgeBulk(array_column($rows, 'id'), $actorType);
    }

    public function reorder(array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \Exception('정렬 데이터가 올바르지 않습니다.');
                }

                $this->model->updateSortNo((string)$row['id'], (string)((int)$row['newSortNo'] + 1000000));
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)(int)$row['newSortNo']);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('카드 업로드');
        $headers = ['카드명', '카드사', '카드번호', '카드유형', '유효기간년', '유효기간월', '결제계좌', '통화', '한도금액', '사용여부', '비고', '메모'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([['법인카드', '신한카드', '1234-5678-9012-3456', '법인카드', '2029', '12', '법인 운영계좌', 'KRW', '1000000', '사용', '', '']], null, 'A2');

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

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

        if (empty($rows) || count($rows) < 2) {
            return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
        }

        $header = array_map(fn($value) => trim((string)$value), array_shift($rows));
        $map = array_flip($header);
        $count = 0;

        foreach ($rows as $row) {
            if (count(array_filter($row, fn($value) => trim((string)$value) !== '')) === 0) {
                continue;
            }

            $clientName = trim((string)($row[$map['카드사'] ?? -1] ?? ''));
            $accountName = trim((string)($row[$map['결제계좌'] ?? -1] ?? ''));

            $payload = [
                'card_name' => trim((string)($row[$map['카드명'] ?? -1] ?? '')),
                'card_number' => trim((string)($row[$map['카드번호'] ?? -1] ?? '')),
                'card_type' => trim((string)($row[$map['카드유형'] ?? -1] ?? '')),
                'client_id' => $this->findClientIdByName($clientName),
                'account_id' => $this->findAccountIdByName($accountName),
                'expiry_year' => trim((string)($row[$map['유효기간년'] ?? -1] ?? '')),
                'expiry_month' => trim((string)($row[$map['유효기간월'] ?? -1] ?? '')),
                'currency' => strtoupper(trim((string)($row[$map['통화'] ?? -1] ?? 'KRW'))),
                'limit_amount' => (float)($row[$map['한도금액'] ?? -1] ?? 0),
                'is_active' => trim((string)($row[$map['사용여부'] ?? -1] ?? '사용')) === '미사용' ? 0 : 1,
                'note' => trim((string)($row[$map['비고'] ?? -1] ?? '')),
                'memo' => trim((string)($row[$map['메모'] ?? -1] ?? '')),
            ];

            if ($payload['card_name'] === '') {
                continue;
            }

            $result = $this->save($payload, 'SYSTEM');
            if (!empty($result['success'])) {
                $count++;
            }
        }

        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
    }

    public function downloadExcel(): void
    {
        $cards = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('카드 목록');
        $sheet->fromArray(['순번', '카드명', '카드사', '카드번호', '카드유형', '유효기간', '결제계좌', '통화', '한도금액', '사용여부', '비고', '메모'], null, 'A1');

        $rowNo = 2;
        foreach ($cards as $card) {
            $expiry = trim(($card['expiry_year'] ?? '') . '-' . ($card['expiry_month'] ?? ''), '-');
            $sheet->fromArray([[
                $card['sort_no'] ?? '',
                $card['card_name'] ?? '',
                $card['client_name'] ?? '',
                $card['card_number'] ?? '',
                $this->displayCardType($card['card_type'] ?? ''),
                $expiry,
                $card['account_name'] ?? '',
                $card['currency'] ?? '',
                $card['limit_amount'] ?? '',
                !empty($card['is_active']) ? '사용' : '미사용',
                $card['note'] ?? '',
                $card['memo'] ?? '',
            ]], null, 'A' . $rowNo);
            $rowNo++;
        }

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="card_list.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    private function normalizePayload(array $data): array
    {
        return [
            'id' => trim((string)($data['id'] ?? '')),
            'card_name' => trim((string)($data['card_name'] ?? '')),
            'card_number' => trim((string)($data['card_number'] ?? '')),
            'card_type' => $this->normalizeCardType($data['card_type'] ?? 'corporate'),
            'client_id' => $this->normalizeNullableId($data['client_id'] ?? null),
            'account_id' => $this->normalizeNullableId($data['account_id'] ?? null),
            'expiry_year' => trim((string)($data['expiry_year'] ?? '')) ?: null,
            'expiry_month' => $this->normalizeExpiryMonth($data['expiry_month'] ?? null),
            'currency' => strtoupper(trim((string)($data['currency'] ?? 'KRW'))) ?: 'KRW',
            'limit_amount' => (float)($data['limit_amount'] ?? 0),
            'card_file' => $data['card_file'] ?? null,
            'note' => trim((string)($data['note'] ?? '')) ?: null,
            'memo' => trim((string)($data['memo'] ?? '')) ?: null,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'delete_card_file' => (string)($data['delete_card_file'] ?? '0'),
        ];
    }

    private function normalizeNullableId(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function normalizeExpiryMonth(mixed $value): ?string
    {
        $month = trim((string)$value);
        if ($month === '') return null;

        return str_pad($month, 2, '0', STR_PAD_LEFT);
    }

    private function normalizeCardType(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            '법인', '법인카드', 'corporate' => 'corporate',
            '개인', '개인카드', 'personal' => 'personal',
            '가상', '가상카드', 'virtual' => 'virtual',
            default => $normalized ?: 'corporate',
        };
    }

    private function displayCardType(string $value): string
    {
        return match ($this->normalizeCardType($value)) {
            'corporate' => '법인카드',
            'personal' => '개인카드',
            'virtual' => '가상카드',
            default => $value,
        };
    }

    private function findClientIdByName(string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        $rows = $this->clientModel->searchPicker($name, 1, ['is_active' => 1]);
        return $rows[0]['id'] ?? null;
    }

    private function findAccountIdByName(string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        $rows = $this->bankAccountModel->searchPicker($name, 1);
        return $rows[0]['id'] ?? null;
    }

    private function assertRelations(array $data): void
    {
        if ($data['client_id'] !== null) {
            $client = $this->clientModel->getById($data['client_id']);

            if (!$client) {
                throw new \Exception('선택한 카드사를 찾을 수 없습니다.');
            }

            if ((int)($client['is_active'] ?? 0) !== 1 || !empty($client['deleted_at'])) {
                throw new \Exception('사용 중인 카드사만 선택할 수 있습니다.');
            }
        }

        if ($data['account_id'] !== null && !$this->bankAccountModel->getById($data['account_id'])) {
            throw new \Exception('선택한 결제계좌를 찾을 수 없습니다.');
        }
    }

    private function assertUploadOk(?array $file, string $label): void
    {
        if (!$file) return;

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || $error === UPLOAD_ERR_OK) {
            return;
        }

        throw new \Exception($this->resolveUploadErrorMessage($error, $label));
    }

    private function resolveUploadErrorMessage(int $errorCode, string $label): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "{$label} 파일 크기가 허용 범위를 초과했습니다.",
            UPLOAD_ERR_PARTIAL => "{$label} 파일이 일부만 업로드되었습니다.",
            UPLOAD_ERR_NO_TMP_DIR => "{$label} 업로드 임시 폴더를 찾을 수 없습니다.",
            UPLOAD_ERR_CANT_WRITE => "{$label} 파일을 저장하지 못했습니다.",
            UPLOAD_ERR_EXTENSION => "{$label} 업로드가 확장 기능에 의해 중단되었습니다.",
            default => "{$label} 업로드 중 알 수 없는 오류가 발생했습니다.",
        };
    }

    private function isUploadedFile(?array $file): bool
    {
        return $file !== null && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }
}
