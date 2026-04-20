<?php
// 경로: PROJECT_ROOT . '/app/Services/System/ProjectService.php'
// 설명:
//  - 프로젝트(Project) 관리 서비스
//  - UUID 생성은 Service 책임
//  - code는 코드헬퍼사용
//  - DB 처리: DashboardProjectModel
//  - 모든 주요 흐름 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\System\ProjectModel;
use App\Models\System\ClientModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ProjectService
{
    private readonly PDO $pdo;
    private ProjectModel $model;
    private ClientModel $clientModel;
    private EmployeeModel $employeeModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new ProjectModel($this->pdo);
        $this->clientModel  = new ClientModel($this->pdo);
        $this->employeeModel  = new EmployeeModel($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-system.ProjectService');
        $this->logger->info('ProjectService initialized');
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
        $this->logger->info('getById() called', [
            'id' => $id
        ]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', [
                    'id' => $id
                ]);
                return null;
            }

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }



    /* =========================================================
    * 프로젝트 검색 (Service - Select2 포맷)
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

                $text = $row['project_name'] ?? '';

                // 🔥 공사명 추가
                if (!empty($row['construction_name']) 
                    && $row['construction_name'] !== $row['project_name']) {
                    $text .= ' / ' . $row['construction_name'];
                }

                // 🔥 코드 추가 (선택)
                if (!empty($row['code'])) {
                    $text .= ' [' . $row['code'] . ']';
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


    /* ============================================================
     * 저장 (신규 + 수정 통합)
     * ============================================================ */
    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

            $this->logger->info('save() called', [
                'mode'      => !empty($data['id']) ? 'UPDATE' : 'INSERT',
                'id'        => $data['id'] ?? null,
                'code'      => $data['code'] ?? null,
                'actorType' => $actorType,
                'actor'     => $actor,
                'file_keys' => array_keys($files)
            ]);

        try {

            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));

            /* =========================================================
            * 기존 데이터 조회 (UPDATE 시 필수)
            * ========================================================= */
            $before = [];

            if ($id) {
                $before = $this->model->getById($id);

                if (!$before) {
                    throw new \Exception('존재하지 않는 프로젝트입니다.');
                }
            }

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
                    throw new \Exception('프로젝트 수정 실패');
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
            $newCode = CodeHelper::generateProjectCode($this->pdo);

            $insertData = array_merge($data, [
                'id'         => $newId,
                'code'       => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('프로젝트 등록 실패');
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

            $this->logger->error('save() failed', [
                'error' => $e->getMessage()
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

                $this->logger->warning('delete() not found', [
                    'id' => $id
                ]);

                return [
                    'success' => false,
                    'message' => '존재하지 않는 프로젝트입니다.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {

                $this->logger->error('delete() DB failed', [
                    'id'    => $id,
                    'actor' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '프로젝트 삭제 실패'
                ];
            }

            $this->logger->info('delete() success', [
                'id' => $id
            ]);

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

            $project = $this->model->getById($id);

            if (!$project) {

                $this->logger->warning('restore() not found', [
                    'id' => $id
                ]);

                return [
                    'success' => false,
                    'message' => '존재하지 않는 프로젝트입니다.'
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {

                $this->logger->error('restore() DB failed', [
                    'id'    => $id,
                    'actor' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '프로젝트 복원 실패'
                ];
            }

            $this->logger->info('restore() success', [
                'id' => $id
            ]);

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restore() exception', [
                'id'        => $id,
                'actor'     => $actor,
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
            'ids'       => $ids,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        if (empty($ids)) {

            $this->logger->warning('restoreBulk() empty ids');

            return [
                'success' => false,
                'message' => '복원할 프로젝트가 없습니다.'
            ];
        }

        try {

            $success = 0;

            foreach ($ids as $id) {
            
                if ($this->model->restoreById($id, $actor)) {
                    $success++;
                }
            }
            
            return [
                'success' => true,
                'message' => "선택 복원 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restoreBulk() exception', [
                'ids'       => $ids,
                'actor'     => $actor,
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
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                if ($this->model->restoreById($row['id'], $actor)) {
                    $success++;
                }
            }

            return [
                'success' => true,
                'message' => "전체 복원 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restoreAll() exception', [
                'actor'     => $actor,
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

        try {

            $this->pdo->beginTransaction();

            $project = $this->model->getById($id);

            if (!$project) {

                $this->pdo->rollBack();
            
                return [
                    'success' => false,
                    'message' => '존재하지 않는 프로젝트입니다.'
                ];
            }

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {

                throw new \Exception('프로젝트 영구삭제 실패');
            }

            $this->pdo->commit();

            $this->logger->info('purge() success', [
                'id' => $id
            ]);

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purge() failed', [
                'id'    => $id,
                'actor' => $actor,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
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
            'ids'       => $ids,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        if (empty($ids)) {

            $this->logger->warning('purgeBulk() empty ids');

            return [
                'success' => false,
                'message' => '삭제할 프로젝트가 없습니다.'
            ];
        }

        try {

            $this->pdo->beginTransaction();

            $success = 0;

            foreach ($ids as $id) {
            
                if ($this->model->hardDeleteById($id)) {
                    $success++;
                }
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "선택 삭제 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purgeBulk() failed', [
                'ids'   => $ids,
                'actor' => $actor,
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
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $this->pdo->beginTransaction();

            // 🔥 삭제 대상 개수 먼저 확보
            $rows = $this->model->getDeleted();
            $count = count($rows);

            if ($count === 0) {

                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '삭제할 프로젝트가 없습니다.'
                ];
            }

            $rows = $this->model->getDeleted();

            $success = 0;
            
            foreach ($rows as $row) {
            
                if ($this->model->hardDeleteById($row['id'])) {
                    $success++;
                }
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "전체 삭제 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purgeAll() failed', [
                'actor' => $actor,
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
        $sheet->setTitle('프로젝트양식');

        /* ============================================================
        * 헤더
        * ============================================================ */
        $headers = [
            '프로젝트명',
            '공사명',
            '발주처',
            '시공사',
            '공사시작일',
            '공사종료일',
            '계약금액',
            '진행상태',
            '비고'
        ];

        $sheet->fromArray($headers, null, 'A1');

        /* ============================================================
        * 시드 데이터
        * ============================================================ */
        $rows = [
            ['청주 커뮤니티센터', '산성유원지 숲속 커뮤니티센터 건립공사', '청주시청', '석향', '2026-01-01', '2026-12-31', '1500000000', '진행중', '공공 프로젝트'],
            ['세레니티 골프리조트', '골프장 클럽하우스 건설공사', '다옴홀딩스', '석향', '2026-02-01', '2027-03-31', '3200000000', '진행중', '민간 프로젝트'],
            ['한빛 주상복합', '한빛 주상복합 신축공사', '한빛개발', '경동하우징', '2026-03-01', '2027-06-30', '2100000000', '진행중', '주거시설'],
            ['세림 산업단지', '세림 산업단지 조성공사', '세림건설', '석향', '2025-10-01', '2026-08-31', '1800000000', '진행중', '토목 포함'],
            ['청우 물류센터', '대형 물류센터 건설', '청우종합건설', '석향', '2025-05-01', '2026-04-30', '2700000000', '완료', '물류시설'],
            ['미래 디자인센터', '디자인 연구센터 건립', '미래디자인', '석향', '2026-04-01', '2026-11-30', '900000000', '진행중', '연구시설'],
            ['대한석재 공장', '석재 가공 공장 신축', '대한석재', '석향', '2026-01-15', '2026-09-30', '1200000000', '진행중', '공장'],
            ['우림 산업 플랜트', '플랜트 건설공사', '우림산업', '석향', '2025-12-01', '2026-10-31', '2500000000', '진행중', '플랜트'],
            ['동해 물류허브', '물류 허브 구축', '동해물류', '석향', '2026-02-15', '2027-01-31', '3000000000', '진행중', '물류'],
            ['세영 무역센터', '무역센터 신축', '세영무역', '석향', '2026-03-10', '2026-12-20', '1700000000', '진행중', '상업시설'],
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
        $filename = 'project_template.xlsx';

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
        $actorType = 'SYSTEM'; // 🔥 거래처와 동일

        try {

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
                '프로젝트명'   => 'project_name',
                '공사명'       => 'construction_name',
                '발주처'       => 'client_name',
                '시공사'       => 'contractor_name',
                '공사시작일'   => 'start_date',
                '공사종료일'   => 'end_date',
                '계약금액'     => 'initial_contract_amount',
                '진행상태'     => 'is_active',
                '비고'         => 'note',
            ];

            $excelHeaders = array_map(fn($v) => trim((string)$v), $rows[0]);

            $columnMap = [];

            foreach ($excelHeaders as $index => $headerName) {
                if (isset($headerMap[$headerName])) {
                    $columnMap[$headerMap[$headerName]] = $index;
                }
            }

            if (!isset($columnMap['project_name'])) {
                return [
                    'success' => false,
                    'message' => '엑셀 양식 오류: [프로젝트명] 필요'
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

                /* ======================
                * 날짜 처리
                * ====================== */
                $startDate = null;
                $endDate   = null;

                if (isset($columnMap['start_date'])) {
                    $v = $row[$columnMap['start_date']] ?? null;
                    $startDate = is_numeric($v)
                        ? Date::excelToDateTimeObject($v)->format('Y-m-d')
                        : trim((string)$v);
                }

                if (isset($columnMap['end_date'])) {
                    $v = $row[$columnMap['end_date']] ?? null;
                    $endDate = is_numeric($v)
                        ? Date::excelToDateTimeObject($v)->format('Y-m-d')
                        : trim((string)$v);
                }

                /* ======================
                * 상태 처리
                * ====================== */
                $statusText = isset($columnMap['is_active'])
                    ? trim((string)($row[$columnMap['is_active']] ?? ''))
                    : '';

                $isActive = match ($statusText) {
                    '완료' => 0,
                    '진행중' => 1,
                    default => 1
                };

                /* ======================
                * payload 구성
                * ====================== */
                $payload = [
                    'project_name' => trim((string)($row[$columnMap['project_name']] ?? '')),

                    'construction_name' => isset($columnMap['construction_name'])
                        ? trim((string)($row[$columnMap['construction_name']] ?? ''))
                        : '',

                    'client_name' => isset($columnMap['client_name'])
                        ? trim((string)($row[$columnMap['client_name']] ?? ''))
                        : '',

                    'contractor_name' => isset($columnMap['contractor_name'])
                        ? trim((string)($row[$columnMap['contractor_name']] ?? ''))
                        : '',

                    'start_date' => $startDate,
                    'end_date'   => $endDate,

                    'initial_contract_amount' => isset($columnMap['initial_contract_amount'])
                        ? (float)($row[$columnMap['initial_contract_amount']] ?? 0)
                        : 0,

                    'is_active' => $isActive,

                    'note' => isset($columnMap['note'])
                        ? trim((string)($row[$columnMap['note']] ?? ''))
                        : '',
                ];

                if ($payload['project_name'] === '') {
                    continue;
                }

                /* ======================
                * 저장 (🔥 핵심 변경)
                * ====================== */
                $result = $this->save($payload, $actorType);

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

        } catch (\Throwable $e) {

            $this->logger->error('Excel upload failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '엑셀 처리 중 오류 발생'
            ];
        }
    }
    /* ============================================================
    * 프로젝트 목록 엑셀 다운로드
    * ============================================================ */
    public function downloadExcel(): void
    {
        // 1️⃣ 데이터 조회
        $projects = $this->model->getList();

        // 2️⃣ 엑셀 객체 생성
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('프로젝트목록');

        /* ============================================================
        * 3️⃣ 헤더
        * ============================================================ */
        $headers = [
            'A1' => '코드',
            'B1' => '프로젝트명',
            'C1' => '공사명',
            'D1' => '발주처',
            'E1' => '시공사',
            'F1' => '공사시작일',
            'G1' => '공사종료일',
            'H1' => '계약금액',
            'I1' => '상태',
            'J1' => '비고',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        /* ============================================================
        * 4️⃣ 데이터
        * ============================================================ */
        $row = 2;

        foreach ($projects as $project) {

            $status = ($project['is_active'] ?? 0) == 1 ? '진행중' : '완료';

            $sheet->setCellValue('A' . $row, $project['code'] ?? '');
            $sheet->setCellValue('B' . $row, $project['project_name'] ?? '');
            $sheet->setCellValue('C' . $row, $project['construction_name'] ?? '');
            $sheet->setCellValue('D' . $row, $project['client_name'] ?? '');
            $sheet->setCellValue('E' . $row, $project['contractor_name'] ?? '');
            $sheet->setCellValue('F' . $row, $project['start_date'] ?? '');
            $sheet->setCellValue('G' . $row, $project['end_date'] ?? '');
            $sheet->setCellValue('H' . $row, $project['initial_contract_amount'] ?? '');
            $sheet->setCellValue('I' . $row, $status);
            $sheet->setCellValue('J' . $row, $project['note'] ?? '');

            $row++;
        }

        /* ============================================================
        * 5️⃣ 자동 컬럼폭
        * ============================================================ */
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        /* ============================================================
        * 6️⃣ 파일명
        * ============================================================ */
        $filename = '프로젝트목록_' . date('Ymd_His') . '.xlsx';

        /* ============================================================
        * 7️⃣ 헤더
        * ============================================================ */
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        /* ============================================================
        * 8️⃣ 출력
        * ============================================================ */
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        exit;
    }


}
