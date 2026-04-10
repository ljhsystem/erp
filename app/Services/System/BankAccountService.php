<?php
// 경로: PROJECT_ROOT . '/app/Services/System/BankAccountService.php'

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
        $this->logger->info('searchBankAccount() called', [
            'keyword' => $keyword
        ]);

        try {

            return $this->model->searchPicker($keyword);

        } catch (\Throwable $e) {

            $this->logger->error('searchBankAccount() exception', [
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
                    throw new \Exception('존재하지 않는 계좌입니다.');
                }
            
                /* =========================
                * 🔥 파일 삭제 처리
                * ========================= */
                if (!empty($data['delete_bank_file']) && $data['delete_bank_file'] == '1') {

                    if (!empty($before['bank_file'])) {
                        $this->fileService->delete($before['bank_file']);
                    }
                
                    $data['bank_file'] = null;
                }
            
                /* =========================
                * 🔥 파일 업로드 처리
                * ========================= */
                $file = $files['bank_file'] ?? null;

                if ($file && $file['error'] === UPLOAD_ERR_OK) {

                    $upload = $this->fileService->uploadBankCopy($file);

                    if (!$upload['success']) {
                        throw new \Exception($upload['message'] ?? '파일 업로드 실패');
                    }

                    $data['bank_file'] = $upload['db_path'];
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
                    throw new \Exception('계좌 수정 실패');
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
            $newCode = CodeHelper::generateBankAccountCode($this->pdo);

            /* =========================
            * 🔥 파일 업로드 처리
            * ========================= */
            $file = $files['bank_file'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {
            
                $upload = $this->fileService->uploadBankCopy($file);
            
                if (!$upload['success']) {
                    throw new \Exception($upload['message'] ?? '파일 업로드 실패');
                }
            
                $data['bank_file'] = $upload['db_path'];
            }

            $insertData = array_merge($data, [
                'id'         => $newId,
                'code'       => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('계좌 등록 실패');
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
                    'message' => '존재하지 않는 계좌입니다.'
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
                    'message' => '계좌 삭제 실패'
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
                    'message' => '존재하지 않는 계좌입니다.'
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {

                $this->logger->error('restore() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '계좌 복원 실패'
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
                'message' => '존재하지 않는 계좌입니다.'
            ];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            /* 🔥 영구삭제 시 파일 삭제 */
            if (!empty($item['bank_file'])) {
                $this->fileService->delete($item['bank_file']);
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
                if (!empty($item['bank_file'])) {
                    $this->fileService->delete($item['bank_file']);
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
                if (!empty($row['bank_file'])) {
                    $this->fileService->delete($row['bank_file']);
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
    * 계좌 템플릿 다운로드
    * ============================================================ */
    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('계좌양식');

        /* ============================================================
        * 헤더
        * ============================================================ */
        $headers = [
            '계좌명',
            '은행명',
            '계좌번호',
            '예금주',
            '계좌구분',
            '통화',
            '사용여부',
            '비고',
            '메모'
        ];

        $sheet->fromArray($headers, null, 'A1');

        /* ============================================================
        * 시드 데이터
        * ============================================================ */
        $rows = [
            ['국민은행 본계좌', '국민은행', '123-456-789012', '주식회사 석향', '보통예금', 'KRW', '사용', '주거래계좌', ''],
            ['신한 급여계좌', '신한은행', '110-123-456789', '주식회사 석향', '보통예금', 'KRW', '사용', '급여계좌', ''],
        ];

        $sheet->fromArray($rows, null, 'A2');

        /* ============================================================
        * 자동 컬럼폭
        * ============================================================ */
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        /* ============================================================
        * 다운로드
        * ============================================================ */
        $filename = 'bank_account_template.xlsx';

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
    * 엑셀 업로드 (파일 → 전체 처리)
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
        * 1. 헤더 매핑 (계좌용)
        * ========================================================= */
        $headerMap = [
            '계좌명'        => 'account_name',
            '계좌명/별칭'   => 'account_name',
            '별칭'          => 'account_name',
        
            '은행명'        => 'bank_name',
            '계좌번호'      => 'account_number',
            '예금주'        => 'account_holder',
            '계좌구분'      => 'account_type',
            '통화'          => 'currency',
            '사용여부'      => 'is_active',
            '비고'          => 'note',
            '메모'          => 'memo',
        ];
        
        $excelHeaders = array_map(function ($v) {
            $v = (string)$v;
        
            // BOM 제거
            $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);
        
            // 개행/탭/전각공백/일반공백 제거
            $v = str_replace(["\r", "\n", "\t", '　', ' '], '', $v);
        
            return trim($v);
        }, $rows[0]);

        $this->logger->info('excel headers', [
            'headers' => $excelHeaders
        ]);

        $columnMap = [];

        foreach ($excelHeaders as $index => $headerName) {
            if (isset($headerMap[$headerName])) {
                $columnMap[$headerMap[$headerName]] = $index;
            }
        }

        $fieldLabels = [
            'account_name'   => '계좌명',
            'account_number' => '계좌번호',
        ];
        
        $requiredColumns = ['account_name', 'account_number'];
        
        foreach ($requiredColumns as $required) {
            if (!isset($columnMap[$required])) {
                return [
                    'success' => false,
                    'message' => '엑셀 양식이 올바르지 않습니다. [' . $fieldLabels[$required] . '] 필요'
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

            $payload = [
                'account_name'   => trim((string)($row[$columnMap['account_name']] ?? '')),
                'bank_name'      => trim((string)($row[$columnMap['bank_name']] ?? '')),
                'account_number' => trim((string)($row[$columnMap['account_number']] ?? '')),
                'account_holder' => trim((string)($row[$columnMap['account_holder']] ?? '')),
                'account_type'   => trim((string)($row[$columnMap['account_type']] ?? '')),
                'currency'       => trim((string)($row[$columnMap['currency']] ?? 'KRW')),
                'is_active'      => trim((string)($row[$columnMap['is_active']] ?? '')) === '사용' ? 1 : 0,
                'note'           => trim((string)($row[$columnMap['note']] ?? '')),
                'memo'           => trim((string)($row[$columnMap['memo']] ?? '')),
            ];

            if ($payload['account_name'] === '' && $payload['account_number'] === '') {
                continue;
            }
            
            if ($payload['account_number'] === '') {
                $this->logger->warning('Excel row skipped: account_number empty', [
                    'payload' => $payload,
                    'row' => $row,
                ]);
                continue;
            }

            $result = $this->save($payload, 'SYSTEM');

            if ($result['success']) {
                $count++;
            } else {
                $this->logger->warning('Excel row save failed', [
                    'payload' => $payload,
                    'error'   => $result['message'] ?? null
                ]);
            }
        }

        return [
            'success' => true,
            'message' => "{$count}건 업로드 완료"
        ];
    }

    /* ============================================================
    * 계좌 목록 엑셀 다운로드
    * ============================================================ */
    public function downloadExcel(): void
    {
        // 1️⃣ 데이터 조회
        $accounts = $this->model->getList();

        // 2️⃣ 엑셀 객체 생성
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 3️⃣ 헤더
        $headers = [
            'A1' => '코드',
            'B1' => '계좌명',
            'C1' => '은행명',
            'D1' => '계좌번호',
            'E1' => '예금주',
            'F1' => '계좌구분',
            'G1' => '통화',
            'H1' => '사용여부',
            'I1' => '비고',
            'J1' => '메모',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        // 4️⃣ 데이터
        $row = 2;

        foreach ($accounts as $account) {

            $sheet->setCellValue('A' . $row, $account['code'] ?? '');
            $sheet->setCellValue('B' . $row, $account['account_name'] ?? '');
            $sheet->setCellValue('C' . $row, $account['bank_name'] ?? '');
            $sheet->setCellValue('D' . $row, $account['account_number'] ?? '');
            $sheet->setCellValue('E' . $row, $account['account_holder'] ?? '');
            $sheet->setCellValue('F' . $row, $account['account_type'] ?? '');
            $sheet->setCellValue('G' . $row, $account['currency'] ?? 'KRW');
            $sheet->setCellValue('H' . $row, !empty($account['is_active']) ? '사용' : '미사용');
            $sheet->setCellValue('I' . $row, $account['note'] ?? '');
            $sheet->setCellValue('J' . $row, $account['memo'] ?? '');

            $row++;
        }

        // 5️⃣ 파일명
        $filename = '계좌목록_' . date('Ymd_His') . '.xlsx';

        // 6️⃣ 헤더
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        // 7️⃣ 출력
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit;
    }

}