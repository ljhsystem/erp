<?php

namespace App\Services\System;

use PDO;
use App\Models\System\CardModel;
use App\Models\System\ClientModel;
use App\Models\System\BankAccountModel;
use App\Repositories\System\CardDependencyRepository;
use App\Services\File\FileService;
use Core\Helpers\ExcelTemplateFilenameHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CardService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => true, 'allow_upload' => false],
        ['key' => 'card_name', 'label' => '카드명', 'required' => true, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'client_name', 'label' => '카드사', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true, 'source_key' => 'client_name'],
        ['key' => 'client_id', 'label' => '카드사 ID', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => true, 'source_key' => 'client_id'],
        ['key' => 'card_number', 'label' => '카드번호', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'account_name', 'label' => '결제계좌', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true, 'source_key' => 'account_name'],
        ['key' => 'account_id', 'label' => '결제계좌 ID', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => true, 'source_key' => 'account_id'],
        ['key' => 'expiry_year', 'label' => '유효기간년', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'expiry_month', 'label' => '유효기간월', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'limit_amount', 'label' => '한도금액', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'card_file', 'label' => '카드이미지', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
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
        'card_name' => '법인카드',
        'client_name' => '신한카드',
        'client_id' => 'client-sample-id',
        'card_number' => '1234-5678-9012-3456',
        'account_name' => '법인 운영계좌',
        'account_id' => 'account-sample-id',
        'expiry_year' => '2029',
        'expiry_month' => '12',
        'limit_amount' => '1000000',
        'note' => '주사용 카드',
        'memo' => '샘플 메모',
        'is_active' => '사용',
    ];

    private PDO $pdo;
    private CardModel $model;
    private ClientModel $clientModel;
    private BankAccountModel $bankAccountModel;
    private FileService $fileService;
    private CardDependencyRepository $dependencyRepository;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CardModel($pdo);
        $this->clientModel = new ClientModel($pdo);
        $this->bankAccountModel = new BankAccountModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->dependencyRepository = new CardDependencyRepository($pdo);
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

        $rawLimitAmount = trim((string) ($data['limit_amount'] ?? '0'));
        if ($rawLimitAmount !== '' && !is_numeric($rawLimitAmount)) {
            return ['success' => false, 'message' => '한도금액은 숫자로 입력해 주세요.'];
        }
        $data = $this->normalizePayload($data);
        $uploadedPath = null;
        $obsoletePath = null;

        try {
            $validationMessage = $this->validateSaveData($data);
            if ($validationMessage !== '') {
                return ['success' => false, 'message' => $validationMessage];
            }
            $before = null;
            if (!$isCreate) {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \DomainException('카드 정보를 찾을 수 없습니다.');
                }
            }
            $this->assertRelations($data, $before);
            $file = $files['card_file'] ?? null;
            $this->assertUploadOk($file, '카드 이미지');

            if ($this->isUploadedFile($file)) {
                $upload = $this->fileService->uploadCardCopy($file);
                if (empty($upload['success']) || empty($upload['db_path'])) {
                    throw new \RuntimeException('카드 이미지 업로드 실패');
                }
                $uploadedPath = (string) $upload['db_path'];
            }

            if ($isCreate) {
                $newId = UuidHelper::generate();
                $newSortNo = SequenceHelper::next('system_cards', 'sort_no');
                $data['card_file'] = $uploadedPath;
                $this->pdo->beginTransaction();

                $insertData = array_merge($data, [
                    'id' => $newId,
                    'sort_no' => $newSortNo,
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
                    'sort_no' => $newSortNo,
                    'message' => '등록되었습니다.',
                ];
            }

            $existingPath = trim((string) ($before['card_file'] ?? ''));
            $deleteExisting = $data['delete_card_file'] === '1';
            $data['card_file'] = $uploadedPath ?: ($deleteExisting ? null : ($existingPath ?: null));
            if ($existingPath !== '' && ($deleteExisting || ($uploadedPath !== null && $uploadedPath !== $existingPath))) {
                $obsoletePath = $existingPath;
            }

            $data['updated_by'] = $actor;
            unset($data['id']);
            $this->pdo->beginTransaction();

            if (!$this->model->updateById($id, $data)) {
                throw new \Exception('카드 정보를 수정하지 못했습니다.');
            }

            $this->pdo->commit();
            $this->cleanupCommittedFile($obsoletePath, '교체된 카드 이미지');

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
            $this->cleanupCompensatingFile($uploadedPath);

            $this->logger->error('save() failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e instanceof \DomainException
                    ? $e->getMessage()
                    : '카드 저장 중 오류가 발생했습니다.',
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
            $this->logger->error('delete() failed', ['id' => $id, 'exception' => $e->getMessage()]);
            return ['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'];
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
            $this->logger->error('restore() failed', ['id' => $id, 'exception' => $e->getMessage()]);
            return ['success' => false, 'message' => '복구 중 오류가 발생했습니다.'];
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
            $this->logger->error('restoreBulk() failed', ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => '복구 중 오류가 발생했습니다.'];
        }
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $rows = $this->model->getDeleted();
        return $this->restoreBulk(array_column($rows, 'id'), $actorType);
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        return $this->purgeCards([$id]);
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->purgeCards($ids);
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        $rows = $this->model->getDeleted();
        return $this->purgeBulk(array_column($rows, 'id'), $actorType);
    }

    private function purgeCards(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return [
                'success' => true,
                'message' => '영구삭제할 카드가 없습니다.',
                'deleted_count' => 0,
                'skipped_count' => 0,
                'blocked' => [],
                'data' => ['deleted_count' => 0, 'skipped_count' => 0, 'blocked' => []],
            ];
        }

        $deleted = 0;
        $blocked = [];
        $obsoletePaths = [];
        $this->pdo->beginTransaction();

        try {
            foreach ($ids as $id) {
                $item = $this->model->getById($id);
                if (!$item || empty($item['deleted_at'])) {
                    continue;
                }

                $references = $this->dependencyRepository->findReferences($id);
                if ($references !== []) {
                    $blocked[] = [
                        'id' => $id,
                        'name' => (string) ($item['card_name'] ?? $id),
                        'references' => $references,
                    ];
                    continue;
                }

                if (!$this->model->hardDeleteById($id)) {
                    throw new \RuntimeException('카드 영구삭제 DB 처리 실패');
                }
                $deleted++;
                $path = trim((string) ($item['card_file'] ?? ''));
                if ($path !== '') {
                    $obsoletePaths[] = $path;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('purgeCards() failed', ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => '영구삭제 중 오류가 발생했습니다.'];
        }

        foreach ($obsoletePaths as $path) {
            $this->cleanupCommittedFile($path, '영구삭제 카드 이미지');
        }

        $skipped = count($blocked);
        if ($deleted === 0 && $skipped > 0) {
            $message = '다른 업무에서 사용 중인 카드이므로 영구삭제할 수 없습니다.';
        } elseif ($skipped > 0) {
            $message = "카드 {$deleted}건을 영구삭제했고, 사용 중인 {$skipped}건은 유지했습니다.";
        } elseif ($deleted > 0) {
            $message = "카드 {$deleted}건을 영구삭제했습니다.";
        } else {
            $message = '영구삭제할 카드가 없습니다.';
        }

        return [
            'success' => true,
            'message' => $message,
            'deleted_count' => $deleted,
            'skipped_count' => $skipped,
            'blocked' => $blocked,
            'data' => [
                'deleted_count' => $deleted,
                'skipped_count' => $skipped,
                'blocked' => $blocked,
            ],
        ];
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

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $headers = $this->buildHeaders($columns);
        $rows = [$this->buildTemplateSampleRow($columns)];

        $this->writeSpreadsheet($headers, $rows, '카드 업로드', 'card_template.xlsx', $columns, true);
        return;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('카드 업로드');
        $headers = ['카드명', '카드사', '카드번호', '유효기간년', '유효기간월', '결제계좌', '한도금액', '사용여부', '비고', '메모'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([['법인카드', '신한카드', '1234-5678-9012-3456', '2029', '12', '법인 운영계좌', '1000000', '사용', '', '']], null, 'A2');

        foreach (range('A', 'J') as $col) {
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
                'message' => '필수 컬럼이 누락되었습니다. ' . implode(', ', $missingRequired),
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

            if (($payload['card_name'] ?? '') === '') {
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
                'client_id' => $this->findClientIdByName($clientName),
                'account_id' => $this->findAccountIdByName($accountName),
                'expiry_year' => trim((string)($row[$map['유효기간년'] ?? -1] ?? '')),
                'expiry_month' => trim((string)($row[$map['유효기간월'] ?? -1] ?? '')),
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

    public function downloadExcel(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('download', $columnsCsv);
        $cards = ExcelValueFormatterHelper::sortRowsBySortNo($this->model->getList());
        $rows = [];

        foreach ($cards as $card) {
            $rows[] = $this->buildDownloadRow($card, $columns);
        }

        $this->writeSpreadsheet(
            $this->buildHeaders($columns),
            $rows,
            '카드 목록',
            'card_list.xlsx',
            $columns
        );
        return;

        $cards = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('카드 목록');
        $sheet->fromArray(['순번', '카드명', '카드사', '카드번호', '유효기간', '결제계좌', '한도금액', '사용여부', '비고', '메모'], null, 'A1');

        $rowNo = 2;
        foreach ($cards as $card) {
            $expiry = trim(($card['expiry_year'] ?? '') . '-' . ($card['expiry_month'] ?? ''), '-');
            $sheet->fromArray([[
                $card['sort_no'] ?? '',
                $card['card_name'] ?? '',
                $card['client_name'] ?? '',
                $card['card_number'] ?? '',
                $expiry,
                $card['account_name'] ?? '',
                $card['limit_amount'] ?? '',
                !empty($card['is_active']) ? '사용' : '미사용',
                $card['note'] ?? '',
                $card['memo'] ?? '',
            ]], null, 'A' . $rowNo);
            $rowNo++;
        }

        foreach (range('A', 'J') as $col) {
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

            if ($payloadKey === 'limit_amount') {
                $payload[$payloadKey] = (float) $rawValue;
                continue;
            }

            $payload[$payloadKey] = trim((string) $rawValue);
        }

        $clientId = trim((string) ($payload['client_id'] ?? ''));
        $clientName = trim((string) ($payload['client_name'] ?? ''));
        $accountId = trim((string) ($payload['account_id'] ?? ''));
        $accountName = trim((string) ($payload['account_name'] ?? ''));

        $payload['client_id'] = $clientId !== '' ? $clientId : $this->findClientIdByName($clientName);
        $payload['account_id'] = $accountId !== '' ? $accountId : $this->findAccountIdByName($accountName);
        $payload['card_name'] = trim((string) ($payload['card_name'] ?? ''));
        $payload['card_number'] = trim((string) ($payload['card_number'] ?? ''));
        $payload['expiry_year'] = trim((string) ($payload['expiry_year'] ?? ''));
        $payload['expiry_month'] = trim((string) ($payload['expiry_month'] ?? ''));
        $payload['note'] = trim((string) ($payload['note'] ?? ''));
        $payload['memo'] = trim((string) ($payload['memo'] ?? ''));

        if (!array_key_exists('is_active', $payload)) {
            $payload['is_active'] = 1;
        }

        unset($payload['client_name'], $payload['account_name']);

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
        $filename = ExcelTemplateFilenameHelper::normalize($filename, 'card');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns, [
            'showRequiredAsterisk' => $showRequiredAsterisk,
        ]);
        if ($showRequiredAsterisk) {
            $this->applyTemplateDropdowns($spreadsheet, $sheet, $columns);
        }

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

    private function applyTemplateDropdowns(Spreadsheet $spreadsheet, Worksheet $sheet, array $columns): void
    {
        $dropdownOptions = [
            'client_name' => $this->tableColumnDropdownOptions('system_clients', 'client_name'),
            'client_id' => $this->tableColumnDropdownOptions('system_clients', 'id'),
            'account_name' => $this->tableColumnDropdownOptions('system_bank_accounts', 'account_name'),
            'account_id' => $this->tableColumnDropdownOptions('system_bank_accounts', 'id'),
            'is_active' => ['사용', '미사용'],
        ];

        $targets = [];
        foreach (array_values($columns) as $index => $column) {
            $key = trim((string) ($column['key'] ?? ''));
            $options = $dropdownOptions[$key] ?? [];
            if ($key === '' || $options === []) {
                continue;
            }

            $targets[] = [
                'columnIndex' => $index + 1,
                'options' => $options,
            ];
        }

        if ($targets === []) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('_card_refs');

        foreach ($targets as $listIndex => $target) {
            $listColumn = Coordinate::stringFromColumnIndex($listIndex + 1);
            foreach (array_values($target['options']) as $rowIndex => $option) {
                $referenceSheet->setCellValue($listColumn . ($rowIndex + 1), $option);
            }

            $this->applyListValidation(
                $sheet,
                Coordinate::stringFromColumnIndex($target['columnIndex']),
                "'_card_refs'!$" . $listColumn . '$1:$' . $listColumn . '$' . count($target['options'])
            );
        }

        $referenceSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    private function applyListValidation(Worksheet $sheet, string $column, string $formula): void
    {
        $range = "{$column}2:{$column}1048576";
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('목록 선택 오류');
        $validation->setError('목록에 있는 값만 선택할 수 있습니다.');
        $validation->setFormula1($formula);
        $validation->setSqref($range);
        $sheet->setDataValidation($range, $validation);
    }

    private function tableColumnDropdownOptions(string $table, string $column): array
    {
        return match ($table) {
            'system_clients' => $this->clientModel->getActiveDropdownValues($column),
            'system_bank_accounts' => $this->bankAccountModel->getActiveDropdownValues($column),
            default => [],
        };
    }

    private function sortRowsForDownload(array $rows): array
    {
        $indexedRows = array_values(array_map(
            static fn(array $row, int $index): array => ['row' => $row, '_index' => $index],
            $rows,
            array_keys($rows)
        ));

        usort($indexedRows, static function (array $left, array $right): int {
            $leftSortNo = is_numeric($left['row']['sort_no'] ?? null) ? (int) $left['row']['sort_no'] : PHP_INT_MAX;
            $rightSortNo = is_numeric($right['row']['sort_no'] ?? null) ? (int) $right['row']['sort_no'] : PHP_INT_MAX;

            return [$leftSortNo, (int) $left['_index']] <=> [$rightSortNo, (int) $right['_index']];
        });

        return array_map(static fn(array $item): array => $item['row'], $indexedRows);
    }

    private function parseExcelActiveValue(mixed $value): int
    {
        $resolved = trim((string) $value);
        if ($resolved === '') {
            return 1;
        }

        return in_array(mb_strtolower($resolved), ['0', 'n', 'no', 'false', '미사용', '비활성'], true) ? 0 : 1;
    }

    private function normalizePayload(array $data): array
    {
        return [
            'id' => trim((string)($data['id'] ?? '')),
            'card_name' => trim((string)($data['card_name'] ?? '')),
            'card_number' => trim((string)($data['card_number'] ?? '')),
            'client_id' => $this->normalizeNullableId($data['client_id'] ?? null),
            'account_id' => $this->normalizeNullableId($data['account_id'] ?? null),
            'expiry_year' => trim((string)($data['expiry_year'] ?? '')) ?: null,
            'expiry_month' => $this->normalizeExpiryMonth($data['expiry_month'] ?? null),
            'limit_amount' => (float)($data['limit_amount'] ?? 0),
            'card_file' => $data['card_file'] ?? null,
            'note' => trim((string)($data['note'] ?? '')) ?: null,
            'memo' => trim((string)($data['memo'] ?? '')) ?: null,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'delete_card_file' => (string)($data['delete_card_file'] ?? '0'),
        ];
    }

    private function validateSaveData(array &$data): string
    {
        if ($data['card_name'] === '') {
            return '카드명은 필수입니다.';
        }
        if (mb_strlen($data['card_name'], 'UTF-8') > 150) {
            return '카드명은 150자 이하로 입력해 주세요.';
        }
        if (mb_strlen($data['card_number'], 'UTF-8') > 50) {
            return '카드번호는 50자 이하로 입력해 주세요.';
        }
        if ($data['expiry_year'] !== null && !preg_match('/^\d{4}$/', $data['expiry_year'])) {
            return '유효기간 연도는 4자리 숫자로 입력해 주세요.';
        }
        if ($data['expiry_month'] !== null && !preg_match('/^(0[1-9]|1[0-2])$/', $data['expiry_month'])) {
            return '유효기간 월은 01부터 12까지 입력해 주세요.';
        }
        if (!is_finite($data['limit_amount']) || $data['limit_amount'] < 0) {
            return '한도금액은 0 이상의 숫자로 입력해 주세요.';
        }
        if ($data['note'] !== null && mb_strlen($data['note'], 'UTF-8') > 255) {
            return '비고는 255자 이하로 입력해 주세요.';
        }
        if ($data['memo'] !== null && mb_strlen($data['memo'], 'UTF-8') > 65535) {
            return '메모가 허용 길이를 초과했습니다.';
        }

        $data['is_active'] = $data['is_active'] === 0 ? 0 : 1;
        return '';
    }

    private function cleanupCompensatingFile(?string $path): void
    {
        if ($path !== null && $path !== '' && !$this->fileService->delete($path)) {
            $this->logger->warning('신규 카드 이미지 보상 삭제 실패', ['path' => $path]);
        }
    }

    private function cleanupCommittedFile(?string $path, string $context): void
    {
        if ($path !== null && $path !== '' && !$this->fileService->delete($path)) {
            $this->logger->warning($context . ' 정리 실패', ['path' => $path]);
        }
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

    private function assertRelations(array $data, ?array $before = null): void
    {
        if ($data['client_id'] !== null) {
            $client = $this->clientModel->getById($data['client_id']);

            if (!$client) {
                throw new \Exception('선택한 카드사를 찾을 수 없습니다.');
            }

            $isExistingRelation = (string) ($before['client_id'] ?? '') === $data['client_id'];
            if (!$isExistingRelation && ((int)($client['is_active'] ?? 0) !== 1 || !empty($client['deleted_at']))) {
                throw new \Exception('사용 중인 카드사만 선택할 수 있습니다.');
            }
        }

        if ($data['account_id'] !== null) {
            $account = $this->bankAccountModel->getById($data['account_id']);
            if (!$account) {
                throw new \Exception('선택한 결제계좌를 찾을 수 없습니다.');
            }
            $isExistingRelation = (string) ($before['account_id'] ?? '') === $data['account_id'];
            if (!$isExistingRelation && ((int) ($account['is_active'] ?? 0) !== 1 || !empty($account['deleted_at']))) {
                throw new \Exception('사용 중인 결제계좌만 선택할 수 있습니다.');
            }
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
