<?php
// 경로: PROJECT_ROOT . '/app/Services/System/CardService.php'

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
    * 전체 목록 조회
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
    * 단건 조회 (id 기준)
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
    * 계좌 자동검색 (입력 자동완성)
    * ========================================================= */
    public function searchPicker(string $keyword): array
    {
        $this->logger->info('searchPicker() called', [
            'keyword' => $keyword
        ]);
        
        try {

            return $this->model->searchPicker($keyword);

        } catch (\Throwable $e) {

            $this->logger->error('searchPicker() exception', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }



    /* =========================================================
    * 저장 (생성 + 수정)
    * ========================================================= */
    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

        /* =========================================================
        * 1️⃣ 기본값 / 모드 결정
        * ========================================================= */
        $id   = trim((string)($data['id'] ?? ''));
        $mode = $id === '' ? 'CREATE' : 'UPDATE';
        $isCreate = ($mode === 'CREATE');

        $this->logger->info('save() called', [
            'mode'  => $mode,
            'id'    => $id,
            'actor' => $actor
        ]);

        try {

            $this->pdo->beginTransaction();

            /* =========================================================
            * 2️⃣ UPDATE
            * ========================================================= */
            if (!$isCreate) {

                $before = $this->model->getById($id);
            
                if (!$before) {
                    throw new \Exception('존재하지 않는 카드입니다.');
                }
            
                /* =========================
                * 🔥 파일 삭제 처리
                * ========================= */
                if (!empty($data['delete_card_file']) && $data['delete_card_file'] == '1') {

                    if (!empty($before['card_file'])) {
                        $this->fileService->delete($before['card_file']);
                    }
                
                    $data['card_file'] = null;
                }
            
                /* =========================
                * 🔥 파일 업로드 처리
                * ========================= */
                $file = $files['card_file'] ?? null;

                if ($file && $file['error'] === UPLOAD_ERR_OK) {

                    $upload = $this->fileService->uploadCardCopy($file);

                    if (!$upload['success']) {
                        throw new \Exception($upload['message'] ?? '파일 업로드 실패');
                    }

                    $data['card_file'] = $upload['db_path'];
                }
            
                $data['updated_by'] = $actor;
            
                $updateData = $data;
                unset($updateData['id']);

                // 변경 데이터 없으면 종료
                if (empty($updateData)) {
                    $this->pdo->commit();

                    return [
                        'success' => true,
                        'id'      => $id,
                        'code'    => $before['code'] ?? null,
                        'message' => '변경사항 없음'
                    ];
                }

                if (!$this->model->updateById($id, $updateData)) {
                    throw new \Exception('카드 수정 실패');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id'      => $id,
                    'code'    => $before['code'] ?? null,
                    'message' => '수정 완료'
                ];
            }

            /* =========================================================
            * 3️⃣ INSERT
            * ========================================================= */
            $newId   = UuidHelper::generate();
            $newCode = CodeHelper::generateCardCode($this->pdo);

            /* =========================
            * 🔥 파일 업로드 처리
            * ========================= */
            $file = $files['card_file'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {
            
                $upload = $this->fileService->uploadCardCopy($file);
            
                if (!$upload['success']) {
                    throw new \Exception($upload['message'] ?? '파일 업로드 실패');
                }
            
                $data['card_file'] = $upload['db_path'];
            }

            $insertData = array_merge($data, [
                'id'         => $newId,
                'code'       => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('카드 등록 실패');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id'      => $newId,
                'code'    => $newCode,
                'message' => '등록 완료'
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', [
                'error' => $e->getMessage(),
                'data'  => $data
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ============================================================
    * 삭제
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
                    'message' => '존재하지 않는 카드입니다.'
                ];
            }
    
            // 🔥 소프트삭제에서는 파일 삭제하지 않음
    
            if (!$this->model->deleteById($id, $actor)) {
    
                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);
    
                return [
                    'success' => false,
                    'message' => '카드 삭제 실패'
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
    * 휴지통 목록
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
    * 복원
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
                    'message' => '존재하지 않는 카드입니다.'
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {

                $this->logger->error('restore() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '카드 복원 실패'
                ];
            }

            $this->logger->info('restore() success', ['id' => $id]);

            return [
                'success' => true,
                'message' => '복원 완료'
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
    * 선택 복원
    * ========================================================= */
    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids'   => $ids,
            'actor' => $actor
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID 없음'];
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
                'message' => "복원 완료 ({$success}건)"
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
    * 전체 복원
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
                'message' => "전체 복원 완료 ({$success}건)"
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
    * 완전삭제
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
                'message' => '존재하지 않는 카드입니다.'
            ];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            /* 🔥 영구삭제 시 파일 삭제 */
            if (!empty($item['card_file'])) {
                $this->fileService->delete($item['card_file']);
            }
    
            $ok = $this->model->hardDeleteById($id);
    
            if (!$ok) {
                throw new \Exception('DB 삭제 실패');
            }
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => '완전삭제 완료'
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            $this->logger->error('purge() failed', [
                'error' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => '삭제 실패'
            ];
        }
    }

    /* =========================================================
    * 선택 완전삭제
    * ========================================================= */
    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purgeBulk() called', [
            'ids'   => $ids,
            'actor' => $actor
        ]);
    
        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID 없음'];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            $success = 0;
    
            foreach ($ids as $id) {
    
                /* =========================================================
                * 1️⃣ 기존 데이터 조회
                * ========================================================= */
                $item = $this->model->getById($id);
    
                if (!$item) {
                    continue;
                }
    
                /* =========================================================
                * 2️⃣ 파일 삭제
                * ========================================================= */
                if (!empty($item['card_file'])) {
                    $this->fileService->delete($item['card_file']);
                }
    
                /* =========================================================
                * 3️⃣ DB 삭제
                * ========================================================= */
                $ok = $this->model->hardDeleteById($id);
    
                if ($ok) $success++;
            }
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => "삭제 완료 ({$success}건)"
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
    * 전체 완전삭제
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
                * 1️⃣ 파일 삭제
                * ========================================================= */
                if (!empty($row['card_file'])) {
                    $this->fileService->delete($row['card_file']);
                }

                /* =========================================================
                * 2️⃣ DB 삭제
                * ========================================================= */
                $ok = $this->model->hardDeleteById($row['id']);

                if ($ok) $success++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 삭제 완료 ({$success}건)"
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
    * 코드 순서 변경 (RowReorder)
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

            /* 1️⃣ 입력값 검증 */
            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newCode'])
                ) {
                    throw new \Exception('reorder 데이터 오류');
                }
            }

            /* 2️⃣ temp 이동 (충돌 방지) */
            foreach ($changes as $row) {

                // 👉 넉넉하게 (절대 충돌 안나게)
                $tempCode = (int)$row['newCode'] + 1000000;

                $this->model->updateCode(
                    $row['id'],
                    $tempCode
                );
            }

            /* 3️⃣ 실제 코드 적용 */
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
    * 카드 템플릿 다운로드
    * ============================================================ */
    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('카드양식');

        /* ============================================================
        * 헤더 (DB 기준 정확히 매핑)
        * ============================================================ */
        $headers = [
            '카드명',
            '카드사',
            '카드번호',
            '카드유형',
            '결제계좌',
            '유효기간(년)',
            '유효기간(월)',
            '통화',
            '한도금액',
            '사용여부',
            '비고',
            '메모'
        ];

        $sheet->fromArray($headers, null, 'A1');

        /* ============================================================
        * 시드 데이터
        * ============================================================ */
        $rows = [
            [
                '법인카드(메인)',
                '국민카드',
                '1234-5678-9012-3456',
                'corporate',
                '국민은행 본계좌',
                '2028',
                '12',
                'KRW',
                '5000000',
                '사용',
                '주사용카드',
                ''
            ],
            [
                '출장카드',
                '신한카드',
                '1111-2222-3333-4444',
                'corporate',
                '신한 급여계좌',
                '2027',
                '06',
                'KRW',
                '3000000',
                '사용',
                '출장용',
                ''
            ]
        ];

        $sheet->fromArray($rows, null, 'A2');

        /* ============================================================
        * 자동 컬럼폭
        * ============================================================ */
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        /* ============================================================
        * 다운로드
        * ============================================================ */
        $filename = 'card_template.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit;
    }




    /* =========================================================
    * 엑셀 업로드 (카드)
    * ========================================================= */
    public function saveFromExcelFile(string $filePath): array
    {
        $actor = ActorHelper::resolve('SYSTEM:EXCEL_UPLOAD');

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, false, false);

        if (empty($rows) || count($rows) < 2) {
            return [
                'success' => false,
                'message' => '업로드할 데이터가 없습니다.'
            ];
        }

        /* =========================================================
        * 1. 헤더 매핑 (DB 기준)
        * ========================================================= */
        $headerMap = [
            '카드명'        => 'card_name',
            '카드사'        => 'client_name',
            '카드번호'      => 'card_number',
            '카드유형'      => 'card_type',
            '결제계좌'      => 'account_name',
            '유효기간(년)'  => 'expiry_year',
            '유효기간(월)'  => 'expiry_month',
            '통화'          => 'currency',
            '한도금액'      => 'limit_amount',
            '사용여부'      => 'is_active',
            '비고'          => 'note',
            '메모'          => 'memo',
        ];

        $excelHeaders = array_map(function ($v) {
            $v = (string)$v;
            $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);
            $v = str_replace(["\r", "\n", "\t", '　', ' '], '', $v);
            return trim($v);
        }, $rows[0]);

        $columnMap = [];

        foreach ($excelHeaders as $index => $headerName) {
            if (isset($headerMap[$headerName])) {
                $columnMap[$headerMap[$headerName]] = $index;
            }
        }

        $required = ['card_name', 'card_number'];

        foreach ($required as $r) {
            if (!isset($columnMap[$r])) {
                return [
                    'success' => false,
                    'message' => "엑셀 양식 오류: {$r} 필요"
                ];
            }
        }

        /* =========================================================
        * 2. 데이터 처리
        * ========================================================= */
        array_shift($rows);

        $count = 0;

        foreach ($rows as $row) {

            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            /* ===============================
            * 값 추출
            * =============================== */
            $cardTypeRaw = trim((string)($row[$columnMap['card_type']] ?? ''));

            // 카드유형 변환
            $cardType = match ($cardTypeRaw) {
                '법인' => 'corporate',
                '개인' => 'personal',
                '가상' => 'virtual',
                default => $cardTypeRaw ?: 'corporate'
            };

            // 사용여부
            $isActive = trim((string)($row[$columnMap['is_active']] ?? '')) === '사용' ? 1 : 0;

            /* ===============================
            * FK 변환
            * =============================== */

            /* ===============================
            * 카드사 → client_id 변환
            * =============================== */
            $clientId = null;

            if (isset($columnMap['client_name'])) {

                $clientName = trim((string)$row[$columnMap['client_name']]);

                if ($clientName !== '') {

                    $clients = $this->clientService->getList([
                        'search' => $clientName
                    ]);

                    foreach ($clients as $c) {
                        if ($c['client_name'] === $clientName) {
                            $clientId = $c['id'];
                            break;
                        }
                    }
                }
            }

            // 결제계좌 → account_id
            $accountId = null;

            if (isset($columnMap['account_name'])) {
            
                $accountName = trim((string)$row[$columnMap['account_name']]);
            
                if ($accountName !== '') {
            
                    $accounts = $this->accountService->getList([
                        'search' => $accountName
                    ]);
            
                    foreach ($accounts as $a) {
                        if ($a['account_name'] === $accountName) {
                            $accountId = $a['id'];
                            break;
                        }
                    }
                }
            }

            /* ===============================
            * payload 구성 (DB 기준)
            * =============================== */
            $payload = [
                'card_name'    => trim((string)($row[$columnMap['card_name']] ?? '')),
                'card_number'  => trim((string)($row[$columnMap['card_number']] ?? '')),
                'card_type'    => $cardType,
                'client_id'    => $clientId,
                'account_id'   => $accountId,
                'expiry_year'  => trim((string)($row[$columnMap['expiry_year']] ?? '')),
                'expiry_month' => trim((string)($row[$columnMap['expiry_month']] ?? '')),
                'currency'     => trim((string)($row[$columnMap['currency']] ?? 'KRW')),
                'limit_amount' => (float)($row[$columnMap['limit_amount']] ?? 0),
                'is_active'    => $isActive,
                'note'         => trim((string)($row[$columnMap['note']] ?? '')),
                'memo'         => trim((string)($row[$columnMap['memo']] ?? '')),
            ];

            if ($payload['card_name'] === '' && $payload['card_number'] === '') {
                continue;
            }

            if ($payload['card_number'] === '') {
                continue;
            }

            /* ===============================
            * 저장
            * =============================== */
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
            'message' => "{$count}건 업로드 완료"
        ];
    }

    /* ============================================================
    * 카드 목록 엑셀 다운로드
    * ============================================================ */
    public function downloadExcel(): void
    {
        // 1️⃣ 데이터 조회 (Service 통해 조회가 더 맞지만 현재 구조 유지)
        $cards = $this->model->getList();

        // 2️⃣ 엑셀 객체 생성
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 3️⃣ 헤더 (DB 기준 + 사용자 표시용)
        $headers = [
            'A1' => '코드',
            'B1' => '카드명',
            'C1' => '카드사',
            'D1' => '카드번호',
            'E1' => '카드유형',
            'F1' => '결제계좌',
            'G1' => '유효기간(년)',
            'H1' => '유효기간(월)',
            'I1' => '통화',
            'J1' => '한도금액',
            'K1' => '사용여부',
            'L1' => '비고',
            'M1' => '메모',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        // 4️⃣ 데이터
        $row = 2;

        foreach ($cards as $card) {

            // 🔥 카드유형 표시 변환
            $cardType = match ($card['card_type'] ?? '') {
                'corporate' => '법인',
                'personal'  => '개인',
                'virtual'   => '가상',
                default     => $card['card_type'] ?? ''
            };

            // 🔥 FK 표시용 (이미 JOIN 되어있으면 그대로 사용)
            $clientName  = $card['client_name'] ?? '';   // model에서 JOIN 필요
            $accountName = $card['account_name'] ?? ''; // model에서 JOIN 필요

            $sheet->setCellValue('A' . $row, $card['code'] ?? '');
            $sheet->setCellValue('B' . $row, $card['card_name'] ?? '');
            $sheet->setCellValue('C' . $row, $clientName);
            $sheet->setCellValue('D' . $row, $card['card_number'] ?? '');
            $sheet->setCellValue('E' . $row, $cardType);
            $sheet->setCellValue('F' . $row, $accountName);
            $sheet->setCellValue('G' . $row, $card['expiry_year'] ?? '');
            $sheet->setCellValue('H' . $row, $card['expiry_month'] ?? '');
            $sheet->setCellValue('I' . $row, $card['currency'] ?? 'KRW');
            $sheet->setCellValue('J' . $row, $card['limit_amount'] ?? 0);
            $sheet->setCellValue('K' . $row, !empty($card['is_active']) ? '사용' : '미사용');
            $sheet->setCellValue('L' . $row, $card['note'] ?? '');
            $sheet->setCellValue('M' . $row, $card['memo'] ?? '');

            $row++;
        }

        // 5️⃣ 자동 컬럼폭
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 6️⃣ 파일명
        $filename = '카드목록_' . date('Ymd_His') . '.xlsx';

        // 7️⃣ 헤더
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        // 8️⃣ 출력
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit;
    }

}