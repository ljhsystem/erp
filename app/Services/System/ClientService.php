<?php
// 경로: PROJECT_ROOT . '/app/Services/System/ClientService.php'
// 설명:
//  - 거래처(Client) 관리 서비스
//  - UUID / Code 생성은 Service 책임
//  - DB 처리: ClientModel
//  - 모든 주요 흐름 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\System\ClientModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\Helpers\DataHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;


class ClientService
{
    private readonly PDO $pdo;
    private ClientModel $model;
    private FileService $fileService;

    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new ClientModel($this->pdo);
        $this->fileService  = new FileService($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-system.ClientService');

        $this->logger->info('ClientService initialized');
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
    
            $crypto = new Crypto();

            foreach ($rows as &$row) {
                if (!empty($row['rrn'])) {
                    $rrn = $crypto->decryptResidentNumber($row['rrn']);
                    $row['rrn'] = preg_replace('/\D+/', '', $rrn);
                } else {
                    $row['rrn'] = '';
                }
            }
            
            unset($row);
            
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

            $crypto = new Crypto();
            $this->logger->info('rrn raw', [
                'db' => $row['rrn']
            ]);
            if (!empty($row['rrn'])) {
                $rrn = $crypto->decryptResidentNumber($row['rrn']);
                $row['rrn'] = preg_replace('/\D+/', '', $rrn);
            } else {
                $row['rrn'] = '';
            }

            $this->logger->info('rrn decrypted', [
                'value' => $rrn ?? null
            ]);
            return $row;
        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }



    /* ============================================================
    * 거래처 자동검색 (입력 자동완성)
    * ============================================================ */
    public function searchPicker(string $keyword): array
    {
        $this->logger->info('searchClient() called', [
            'keyword' => $keyword
        ]);

        try {

            return $this->model->searchPicker($keyword);
        } catch (\Throwable $e) {

            $this->logger->error('searchClient() exception', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }
    
    /* ============================================================
    * 저장 (생성 + 수정)
    * ============================================================ */
    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('save() called', [
            'mode'      => !empty($data['id']) ? 'UPDATE' : 'INSERT',
            'id'        => $data['id'] ?? null,
            'code'      => $data['code'] ?? null,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        $newBusinessPath = null;
        $newRrnPath = null; 
        $newBankPath = null;

        try {

            $this->pdo->beginTransaction();

            /* 🔥 normalize 전에 삭제 플래그를 먼저 고정 */
            $deleteBusiness = !empty($data['delete_business_certificate']);
            $deleteRrn      = !empty($data['delete_rrn_image']); 
            $deleteBank     = !empty($data['delete_bank_file']);

            
            $data = DataHelper::normalizeClient($data);

            /* =========================================================
            * 🔥 기존 데이터 먼저 조회 (중요)
            * ========================================================= */
            $id   = trim((string)($data['id'] ?? ''));
            $mode = $id === '' ? 'CREATE' : 'UPDATE';

            $before = [];

            if ($id) {
                $before = $this->model->getById($id) ?? [];

                if (!$before) {
                    throw new \Exception('존재하지 않는 거래처입니다.');
                }
            }

            /* =========================================================
            * 🔥 rrn 처리 (여기로 이동)
            * ========================================================= */
            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if ($rrnInput === '') {

                $data['rrn'] = $before['rrn'] ?? null;

            } else {

                if (strpos($rrnInput, '*') !== false) {
                    throw new \Exception('마스킹된 주민번호는 저장할 수 없습니다.');
                }

                $rrnRaw = preg_replace('/\D+/', '', $rrnInput);

                if ($rrnRaw !== '') {

                    $crypto = new Crypto();
                    $data['rrn'] = $crypto->encryptResidentNumber($rrnRaw);

                } else {
                    $data['rrn'] = null;
                }
            }
            
            /* =========================================================
            * 🔥 ID / 모드 결정
            * ========================================================= */
            $id   = trim((string)($data['id'] ?? ''));
            $mode = $id === '' ? 'CREATE' : 'UPDATE';

            /* =========================================================
            * 🔥 기존 데이터 먼저 조회 (중요)
            * ========================================================= */
            $before = [];

            if ($id) {
                $before = $this->model->getById($id) ?? [];

                if (!$before) {
                    throw new \Exception('존재하지 않는 거래처입니다.');
                }
            }


            /* =========================================================
            * 파일 처리
            * ========================================================= */

            // 🔴 삭제 요청
            if ($deleteBusiness && empty($files['business_certificate']['tmp_name'])) {
                if (!empty($before['business_certificate'])) {
                    $this->fileService->delete($before['business_certificate']);
                }
                $data['business_certificate'] = null;
            }
            
            if ($deleteBank && empty($files['bank_file']['tmp_name'])) {
                if (!empty($before['bank_file'])) {
                    $this->fileService->delete($before['bank_file']);
                }
                $data['bank_file'] = null;
            }

            if ($deleteRrn) {

                if (!empty($before['rrn_image'])) {
                    $this->fileService->delete($before['rrn_image']);
                }
            
                $data['rrn_image'] = null;
            }
            
            // 🔴 파일용량체크
            if (
                isset($files['business_certificate']['error']) &&
                $files['business_certificate']['error'] === UPLOAD_ERR_INI_SIZE
            )
            

            if (
                isset($files['bank_file']['error']) &&
                $files['bank_file']['error'] === UPLOAD_ERR_INI_SIZE
            )


            // 🔴 업로드
            if (!empty($files['business_certificate']['tmp_name'])) {

                $oldPath = $before['business_certificate'] ?? null;
            
                $upload = $this->fileService->uploadBusinessCert(
                    $files['business_certificate']
                );
            
                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }
            
                $data['business_certificate'] = $upload['db_path'];
                $newBusinessPath = $upload['db_path'];
            
                if (!empty($oldPath)) {
                    $this->fileService->delete($oldPath);
                }
            }

            // 🔥 rrn_image 업로드 처리
            if (!empty($files['rrn_image']['tmp_name'])) {

                $oldPath = $before['rrn_image'] ?? null;
            
                $upload = $this->fileService->uploadPrivateIdDoc(
                    $files['rrn_image']
                );
            
                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }
            
                $data['rrn_image'] = $upload['db_path'];
                $newRrnPath = $upload['db_path'];   // 🔥 여기서 넣어야 맞다
            
                if (!empty($oldPath)) {
                    $this->fileService->delete($oldPath);
                }
            }

            if (!empty($files['bank_file']['tmp_name'])) {

                $oldPath = $before['bank_file'] ?? null;
            
                $upload = $this->fileService->uploadBankCopy(
                    $files['bank_file']
                );
            
                if (empty($upload['success'])) {
                    throw new \Exception($upload['message']);
                }
            
                $data['bank_file'] = $upload['db_path'];
                $newBankPath = $upload['db_path'];
            
                if (!empty($oldPath)) {
                    $this->fileService->delete($oldPath);
                }
            }

            // 🔴 기존 파일 유지
            if (
                !array_key_exists('business_certificate', $data)
                && !$deleteBusiness
            ) {
                $data['business_certificate'] =
                    $before['business_certificate'] ?? null;
            }
            if (
                !array_key_exists('rrn_image', $data)
                && !$deleteRrn
            ) {
                $data['rrn_image'] =
                    $before['rrn_image'] ?? null;
            }
            
            if (
                !array_key_exists('bank_file', $data)
                && !$deleteBank
            ) {
                $data['bank_file'] =
                    $before['bank_file'] ?? null;
            }

            /* =========================================================
            * 🔥 삭제 플래그 제거 (DB 보호)
            * ========================================================= */
            unset($data['delete_business_certificate']);
            unset($data['delete_bank_file']);
            unset($data['delete_rrn_image']);
            /* =========================================================
            * UPDATE
            * ========================================================= */
            if ($id) {

                $data['updated_by'] = $actor;

                $updateData = $data;

                unset($updateData['id']);

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
                    throw new \Exception('거래처 수정 실패');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id'      => $id,
                    'code'    => $before['code'] ?? null
                ];
            }

            /* =========================================================
            * INSERT
            * ========================================================= */
            $newId   = UuidHelper::generate();
            $newCode = CodeHelper::generateClientCode($this->pdo);

            $insertData = array_merge($data, [
                'id'         => $newId,
                'code'       => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('거래처 등록 실패');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id'      => $newId,
                'code'    => $newCode
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        
            // 🔥 업로드만 되고 DB 반영 실패한 파일 정리
            if (!empty($newBusinessPath)) {
                $this->fileService->delete($newBusinessPath);
            }

            if (!empty($newRrnPath)) {
                $this->fileService->delete($newRrnPath);
            }

            if (!empty($newBankPath)) {
                $this->fileService->delete($newBankPath);
            }
        
            $this->logger->error('save() failed', [
                'error' => $e->getMessage(),
                'newBusinessPath' => $newBusinessPath,
                'newBankPath' => $newBankPath
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
                    'message' => '존재하지 않는 거래처입니다.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {

                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '거래처 삭제 실패'
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
    복원
    ========================================================= */

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restore() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        $client = $this->model->getById($id);

        if (!$client) {
            return [
                'success' => false,
                'message' => '존재하지 않는 거래처입니다.'
            ];
        }

        $ok = $this->model->restoreById($id, $actor);

        return [
            'success' => $ok
        ];
    }






    /* =========================================================
    * 선택 복원
    * ========================================================= */
    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('restoreBulk() called', [
            'ids' => $ids,
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
    
        $client = $this->model->getById($id);
    
        if (!$client) {
            return [
                'success' => false,
                'message' => '존재하지 않는 거래처입니다.'
            ];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            /* =========================
             * 1️⃣ 파일 삭제 (핵심)
             * ========================= */
    
            if (!empty($client['business_certificate'])) {
    
                $this->fileService->delete($client['business_certificate']);
    
                $this->logger->info('business_certificate deleted', [
                    'path' => $client['business_certificate']
                ]);
            }
            if (!empty($client['rrn_image'])) {

                $this->fileService->delete($client['rrn_image']);

                $this->logger->info('rrn_image deleted', [
                    'path' => $client['rrn_image']
                ]);
            }
            if (!empty($client['bank_file'])) {
    
                $this->fileService->delete($client['bank_file']);
    
                $this->logger->info('bank_file deleted', [
                    'path' => $client['bank_file']
                ]);
            }
    
            /* =========================
             * 2️⃣ DB 삭제
             * ========================= */
    
            $ok = $this->model->hardDeleteById($id);
    
            if (!$ok) {
                throw new \Exception('DB 삭제 실패');
            }
    
            $this->pdo->commit();
    
            return [
                'success' => true
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
                $client = $this->model->getById($id);
    
                if (!$client) {
                    continue;
                }
    
                /* =========================================================
                 * 2️⃣ 파일 삭제
                 * ========================================================= */
                if (!empty($client['business_certificate'])) {
    
                    $this->fileService->delete($client['business_certificate']);
    
                    $this->logger->info('business_certificate deleted', [
                        'id'   => $id,
                        'path' => $client['business_certificate']
                    ]);
                }

                if (!empty($client['rrn_image'])) {

                    $this->fileService->delete($client['rrn_image']);
                
                    $this->logger->info('rrn_image deleted', [
                        'id'   => $id,
                        'path' => $client['rrn_image']
                    ]);
                }

                if (!empty($client['bank_file'])) {
    
                    $this->fileService->delete($client['bank_file']);
    
                    $this->logger->info('bank_file deleted', [
                        'id'   => $id,
                        'path' => $client['bank_file']
                    ]);
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

        $this->pdo->beginTransaction();

        try {

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                /* =========================================================
                * 1️⃣ 파일 삭제
                * ========================================================= */
                if (!empty($row['business_certificate'])) {

                    $this->fileService->delete($row['business_certificate']);

                    $this->logger->info('business_certificate deleted', [
                        'id'   => $row['id'],
                        'path' => $row['business_certificate']
                    ]);
                }
                if (!empty($row['rrn_image'])) {

                    $this->fileService->delete($row['rrn_image']);

                    $this->logger->info('rrn_image deleted', [
                        'id'   => $row['id'],
                        'path' => $row['rrn_image']
                    ]);
                }
                if (!empty($row['bank_file'])) {

                    $this->fileService->delete($row['bank_file']);

                    $this->logger->info('bank_file deleted', [
                        'id'   => $row['id'],
                        'path' => $row['bank_file']
                    ]);
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
    * 템플릿 다운로드
    * ============================================================ */    
    public function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('거래처양식');
    
        /* ============================================================
         * 헤더
         * ============================================================ */
        $headers = [
            '거래처명',
            '상호',
            '대표자명',
            '사업자등록번호',
            '사업자상태',
            '전화번호',
            '이메일',
            '등록일자',
            '비고'
        ];
    
        $sheet->fromArray($headers, null, 'A1');
    
        /* ============================================================
         * 시드 데이터
         * ============================================================ */
        $rows = [
            ['석향', '주식회사 석향', '이정호', '123-45-67890', '계속사업자', '02-1234-5678', 'admin@sukhyang.co.kr', '2026-01-01', '본사'],
            ['경동하우징', '주식회사 경동하우징', '김영수', '234-56-78901', '계속사업자', '02-2345-6789', 'kdhousing@example.com', '2026-01-02', '주요 발주처'],
            ['다옴홀딩스', '주식회사 다옴홀딩스', '정복선', '345-67-89012', '계속사업자', '02-3456-7890', 'daom@example.com', '2026-01-03', '민간 거래처'],
            ['선경이엔씨', '주식회사 선경이엔씨', '박선우', '456-78-90123', '계속사업자', '031-456-7890', 'skenc@example.com', '2026-01-04', '협력사'],
            ['세림건설', '주식회사 세림건설', '최민호', '567-89-01234', '계속사업자', '032-567-8901', 'serim@example.com', '2026-01-05', '건설사'],
            ['한빛개발', '주식회사 한빛개발', '윤지훈', '678-90-12345', '계속사업자', '042-678-9012', 'hanbit@example.com', '2026-01-06', '개발사'],
            ['청우종합건설', '주식회사 청우종합건설', '오세훈', '789-01-23456', '계속사업자', '051-789-0123', 'cwconst@example.com', '2026-01-07', '원도급사'],
            ['미래디자인', '주식회사 미래디자인', '강다은', '890-12-34567', '계속사업자', '053-890-1234', 'design@example.com', '2026-01-08', '디자인 협력업체'],
            ['대한석재', '대한석재', '임성호', '901-23-45678', '계속사업자', '041-901-2345', 'stone@example.com', '2026-01-09', '자재업체'],
            ['우림산업', '주식회사 우림산업', '한지수', '135-79-24680', '계속사업자', '061-135-2468', 'woorim@example.com', '2026-01-10', '자재 납품'],
            ['동해물류', '주식회사 동해물류', '서동민', '246-80-13579', '계속사업자', '033-246-1357', 'logi@example.com', '2026-01-11', '운송업체'],
            ['세영무역', '주식회사 세영무역', '문태성', '357-91-24680', '계속사업자', '070-357-2468', 'trade@example.com', '2026-01-12', '수입 관련'],
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
        $filename = 'client_template.xlsx';
    
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');
    
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    
        exit;
    }

    /* ============================================================
    * 엑셀 업로드 (파일 → 전체 처리)
    * ============================================================ */
    
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
    
        /* ============================================================
         * 1. 헤더 매핑
         * ============================================================ */
        $headerMap = [
            '거래처명'       => 'client_name',
            '상호'          => 'company_name',
            '대표자명'       => 'ceo_name',
            '사업자등록번호' => 'business_number',
            '사업자상태'     => 'business_status',
            '전화번호'       => 'phone',
            '이메일'         => 'email',
            '등록일자'       => 'registration_date',
            '비고'          => 'note',
        ];
    
        $excelHeaders = array_map(fn($v) => trim((string)$v), $rows[0]);
    
        $columnMap = [];
    
        foreach ($excelHeaders as $index => $headerName) {
            if (isset($headerMap[$headerName])) {
                $columnMap[$headerMap[$headerName]] = $index;
            }
        }
    
        if (!isset($columnMap['client_name'])) {
            return [
                'success' => false,
                'message' => '엑셀 양식이 올바르지 않습니다. [거래처명] 필요'
            ];
        }
    
        /* ============================================================
         * 2. 데이터 처리
         * ============================================================ */
        array_shift($rows);
    
        $count = 0;
    
        foreach ($rows as $row) {
    
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }
    
            $registrationDate = null;
    
            if (isset($columnMap['registration_date'])) {
                $registrationDate = $row[$columnMap['registration_date']] ?? null;
    
                if (is_numeric($registrationDate)) {
                    $registrationDate = Date::excelToDateTimeObject($registrationDate)
                        ->format('Y-m-d');
                } else {
                    $registrationDate = trim((string)$registrationDate);
                }
            }
    
            $payload = [
                'client_name'       => trim((string)($row[$columnMap['client_name']] ?? '')),
                'company_name'      => trim((string)($row[$columnMap['company_name']] ?? '')),
                'ceo_name'          => trim((string)($row[$columnMap['ceo_name']] ?? '')),
                'business_number'   => trim((string)($row[$columnMap['business_number']] ?? '')),
                'business_status'   => trim((string)($row[$columnMap['business_status']] ?? '')),
                'phone'             => trim((string)($row[$columnMap['phone']] ?? '')),
                'email'             => trim((string)($row[$columnMap['email']] ?? '')),
                'registration_date' => $registrationDate ?: date('Y-m-d'),
                'note'              => trim((string)($row[$columnMap['note']] ?? '')),
            ];
    
            if ($payload['client_name'] === '') {
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
    * 거래처처 목록 엑셀 다운로드
    * ============================================================ */
    
    public function downloadExcel(): void
    {
        // 1️⃣ 데이터 조회
        $clients = $this->model->getList(); // 🔥 service → model로 변경 권장
    
        // 2️⃣ 엑셀 객체 생성
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        // 3️⃣ 헤더
        $headers = [
            'A1' => '코드',
            'B1' => '거래처명',
            'C1' => '사업자번호',
            'D1' => '대표자',
            'E1' => '전화번호',
            'F1' => '이메일',
            'G1' => '주소',
            'H1' => '메모',
        ];
    
        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }
    
        // 4️⃣ 데이터
        $row = 2;
    
        foreach ($clients as $client) {
    
            $sheet->setCellValue('A' . $row, $client['code'] ?? '');
            $sheet->setCellValue('B' . $row, $client['client_name'] ?? '');
            $sheet->setCellValue('C' . $row, $client['business_number'] ?? '');
            $sheet->setCellValue('D' . $row, $client['ceo_name'] ?? '');
            $sheet->setCellValue('E' . $row, $client['phone'] ?? '');
            $sheet->setCellValue('F' . $row, $client['email'] ?? '');
            $sheet->setCellValue('G' . $row, $client['address'] ?? '');
            $sheet->setCellValue('H' . $row, $client['memo'] ?? '');
    
            $row++;
        }
    
        // 5️⃣ 파일명
        $filename = '거래처목록_' . date('Ymd_His') . '.xlsx';
    
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
