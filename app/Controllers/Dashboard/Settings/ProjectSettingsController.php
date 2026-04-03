<?php
// 경로: PROJECT_ROOT . '/app/controllers/dashboard/settings/ProjectSettingsController.php'
// 대시보드>설정>기초정보관리>프로젝트 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\ProjectService;
use App\Services\System\ClientService;
use App\Services\User\ProfileService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProjectSettingsController
{
    private ProjectService $service;
    private ClientService $clientService;
    private ProfileService $employeeService;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new ProjectService(DbPdo::conn());
        $this->clientService = new ClientService(DbPdo::conn());
        $this->employeeService = new ProfileService(DbPdo::conn());      
    }
    /* ============================================================
     * API: 프로젝트 목록 조회
     * URL: GET /api/settings/base-info/project/list
     * ============================================================ */
    public function apiList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $filters = [];

            if (!empty($_GET['filters'])) {
                $decoded = json_decode($_GET['filters'], true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $filters = $decoded;
                }
            }

            $rows = $filters
                ? $this->service->search($filters)
                : $this->service->getList();

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

/* ============================================================
     * API: 프로젝트 상세 조회
     * URL: GET /api/settings/base-info/project/detail?code=
     * ============================================================ */
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        $id = $_GET['id'] ?? null;
        $includeDeleted = isset($_GET['include_deleted']) && (string)$_GET['include_deleted'] === '1';
    
        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 코드 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    
        try {
            $row = $this->service->getById($id, $includeDeleted);
    
            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '프로젝트 없음'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            echo json_encode([
                'success' => true,
                'data'    => $row
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }
    

    public function apiSearch(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $filters = $_POST ?? [];
    
            $data = $this->service->search($filters);
    
            echo json_encode([
                'success' => true,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }
    
    /* ============================================================
     * API: 프로젝트 검색 자동완성
     * URL: GET /api/settings/base-info/project/search?q=
     * ============================================================ */
    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $keyword = $_GET['q'] ?? '';

        try {
            $rows = $this->service->searchPicker($keyword);

            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    /* ============================================================
     * API: 프로젝트 저장 (신규 + 수정)
     * URL: POST /api/settings/base-info/project/save
     * ============================================================ */
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
            $normalizeFk = static function ($value): ?string {
                if ($value === null) {
                    return null;
                }
    
                $value = trim((string)$value);
    
                if (
                    $value === '' ||
                    $value === 'null' ||
                    $value === 'undefined' ||
                    $value === '0'
                ) {
                    return null;
                }
    
                return $value;
            };
    
            $payload = [
                'id'   => $_POST['id'] ?? null,
                'code' => $_POST['code'] ?? null,
                'project_name' => trim($_POST['project_name'] ?? ''),
                'client_id' => $normalizeFk($_POST['client_id'] ?? null),
                'employee_id' => $normalizeFk($_POST['employee_id'] ?? null),
                'site_agent' => $_POST['site_agent'] ?? null,
                'contract_type' => $_POST['contract_type'] ?? null,
                'director' => $_POST['director'] ?? null,
                'manager' => $_POST['manager'] ?? null,
                'business_type' => $_POST['business_type'] ?? null,
                'housing_type' => $_POST['housing_type'] ?? null,
                'construction_name' => $_POST['construction_name'] ?? null,
                'site_region_city' => $_POST['site_region_city'] ?? null,
                'site_region_district' => $_POST['site_region_district'] ?? null,
                'site_region_address' => $_POST['site_region_address'] ?? null,
                'site_region_address_detail' => $_POST['site_region_address_detail'] ?? null,
                'work_type' => $_POST['work_type'] ?? null,
                'work_subtype' => $_POST['work_subtype'] ?? null,
                'work_detail_type' => $_POST['work_detail_type'] ?? null,
                'contract_work_type' => $_POST['contract_work_type'] ?? null,
                'bid_type' => $_POST['bid_type'] ?? null,
                'client_name' => $_POST['client_name'] ?? null,
                'client_type' => $_POST['client_type'] ?? null,
                'permit_agency' => $_POST['permit_agency'] ?? null,
                'permit_date' => $_POST['permit_date'] ?? null,
                'contract_date' => $_POST['contract_date'] ?? null,
                'start_date' => $_POST['start_date'] ?? null,
                'completion_date' => $_POST['completion_date'] ?? null,
                'bid_notice_date' => $_POST['bid_notice_date'] ?? null,
                'initial_contract_amount' => $_POST['initial_contract_amount'] ?? null,
                'authorized_company_seal' => $_POST['authorized_company_seal'] ?? null,
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
                'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            ];
    
            if ($payload['project_name'] === '') {
                echo json_encode([
                    'success' => false,
                    'message' => '관리명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            $result = $this->service->save($payload, 'USER');
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 저장 중 오류 발생',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }
    /* ============================================================
     * API: 프로젝트 삭제
     * URL: POST /api/settings/base-info/project/delete
     * ============================================================ */
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 코드 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->delete($id, 'USER');
        
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    /* ============================================================
     * API: 프로젝트 휴지통 목록
     * URL: GET /api/settings/base-info/project/trash
     * ============================================================ */
    public function apiTrashList(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $rows = $this->service->getTrashList();

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '휴지통 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    /* ============================================================
     * API: 프로젝트 복원
     * URL: POST /api/settings/base-info/project/restore
     * ============================================================ */
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 코드 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->restore($id, 'USER');
        
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 복원 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    /* ============================================================
     * API: 프로젝트 완전삭제
     * URL: POST /api/settings/base-info/project/purge
     * ============================================================ */
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '프로젝트 코드 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->purge($id, 'USER');
        
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '완전삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    /* =========================
    * API - 휴지통 (Bulk)
    ========================= */
    public function apiRestoreBulk()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
    
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'ID 없음']);
                return;
            }
    
            $result = $this->service->restoreBulk($ids, 'USER');
    
            echo json_encode($result);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '복원 실패',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function apiPurgeBulk()
    {
        try {
            $ids = $_POST['ids'] ?? [];
    
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'ID 없음']);
                return;
            }
    
            $result = $this->service->purgeBulk($ids, 'USER');
    
            echo json_encode($result);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '삭제 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }



    public function apiPurgeAll()
    {
        try {
            $result = $this->service->purgeAll('USER');
    
            echo json_encode($result);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '전체삭제 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    /* ============================================================
     * API: 프로젝트 순서 변경 (RowReorder)
     * URL: POST /api/settings/base-info/project/reorder
     * ============================================================ */
    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            $changes = $payload['changes'] ?? [];

            if (!$changes) {
                echo json_encode([
                    'success' => false,
                    'message' => '변경 데이터 없음'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->service->reorder($changes);

            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '순서 저장 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            if (!isset($_FILES['excel']) || !is_uploaded_file($_FILES['excel']['tmp_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '파일이 업로드되지 않았습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $file = $_FILES['excel']['tmp_name'];

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, false, false, false);

            if (empty($rows) || count($rows) < 2) {
                echo json_encode([
                    'success' => false,
                    'message' => '업로드할 데이터가 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* =========================
            * 헤더 매핑
            ========================= */
            $headerMap = [
                '프로젝트명'       => 'project_name',
                '공사명'           => 'construction_name',
                '발주자명'         => 'client_name',
                '계약일자'         => 'contract_date',
                '착공일자'         => 'start_date',
                '준공일자'         => 'completion_date',
                '최초계약금액'     => 'initial_contract_amount',
                '비고'             => 'note',
                '메모'             => 'memo',
            ];

            $excelHeaders = array_map(fn($v) => trim((string)$v), $rows[0]);

            $columnMap = [];
            foreach ($excelHeaders as $i => $header) {
                if (isset($headerMap[$header])) {
                    $columnMap[$headerMap[$header]] = $i;
                }
            }

            if (!isset($columnMap['project_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 양식 오류 (프로젝트명 없음)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            array_shift($rows);

            /* =========================
            * 날짜 안전 파서
            ========================= */
            $parseDate = function($field, $row, $columnMap) {

                if (!isset($columnMap[$field])) return null;

                $v = $row[$columnMap[$field]] ?? null;
                if (!$v) return null;

                try {
                    return is_numeric($v)
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($v)->format('Y-m-d')
                        : date('Y-m-d', strtotime($v));
                } catch (\Throwable $e) {
                    return null;
                }
            };

            $count = 0;

            foreach ($rows as $row) {

                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }

                $get = fn($f) => isset($columnMap[$f]) ? trim((string)($row[$columnMap[$f]] ?? '')) : '';

                /* =========================
                * 🔥 거래처명 → client_id 변환
                ========================= */
                $clientId = null;

                if ($get('client_name')) {

                    $client = $this->clientService->findByName($get('client_name'));

                    if ($client) {
                        $clientId = $client['id'];
                    }
                }

                // 🔥 담당직원 → employee_id 변환
                $employeeId = null;

                if ($get('employee_name')) {

                    $employee = $this->employeeService->findByName($get('employee_name'));

                    if ($employee) {
                        $employeeId = $employee['id'];
                    }
                }


                /* =========================
                * payload 구성
                ========================= */
                $payload = [
                    'project_name'            => $get('project_name'),
                    'construction_name'       => $get('construction_name'),
                    'client_id'               => $clientId, // 🔥 핵심
                    'employee_id'             => $employeeId,

                    'contract_date'           => $parseDate('contract_date', $row, $columnMap),
                    'start_date'              => $parseDate('start_date', $row, $columnMap),
                    'completion_date'         => $parseDate('completion_date', $row, $columnMap),

                    'initial_contract_amount' => $get('initial_contract_amount'),
                    'note'                    => $get('note'),
                    'memo'                    => $get('memo'),
                ];

                if ($payload['project_name'] === '') {
                    continue;
                }

                /* =========================
                * 저장 (actor는 서비스에서 처리)
                ========================= */
                $ok = $this->service->saveFromExcel($payload, 'SYSTEM_EXCEL_UPLOAD');

                if (!$ok) {
                    echo json_encode([
                        'success' => false,
                        'message' => '엑셀 업로드 중 오류 발생'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $count++;
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count}건 업로드 완료"
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '엑셀 업로드 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }
    

    /* ============================================================
     * API: 프로젝트 전체 엑셀 다운로드
     * URL: GET /api/settings/base-info/project/excel
     * ============================================================ */
    public function apiDownload(): void
    {    
        try {
            $projects = $this->service->getList();
    
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('프로젝트목록');
    
            $headers = [
                'A1'  => '코드',
                'B1'  => '프로젝트명',
                'C1'  => '공사명',
                'D1'  => '거래처ID',
                'E1'  => '연결거래처명',
                'F1'  => '발주자명',
                'G1'  => '발주자분류',
                'H1'  => '담당직원ID',
                'I1'  => '담당직원명',
                'J1'  => '현장대리인',
                'K1'  => '계약형태',
                'L1'  => '소장',
                'M1'  => '실장',
                'N1'  => '업종',
                'O1'  => '주력분야',
                'P1'  => '시도',
                'Q1'  => '시군구',
                'R1'  => '상세주소',
                'S1'  => '공종',
                'T1'  => '공종세분류',
                'U1'  => '세부공사종류',
                'V1'  => '도급종류',
                'W1'  => '입찰형태',
                'X1'  => '인허가기관',
                'Y1'  => '인허가일자',
                'Z1'  => '계약일자',
                'AA1' => '착공일자',
                'AB1' => '준공일자',
                'AC1' => '입찰공고일',
                'AD1' => '최초계약금액',
                'AE1' => '사용인감명',
                'AF1' => '진행상태',
                'AG1' => '비고',
                'AH1' => '메모',
                'AI1' => '등록일시',
                'AJ1' => '등록자',
                'AK1' => '수정일시',
                'AL1' => '수정자',
            ];
    
            foreach ($headers as $cell => $label) {
                $sheet->setCellValue($cell, $label);
            }
    
            $row = 2;
    
            foreach ($projects as $project) {
                $sheet->setCellValue('A'  . $row, $project['code'] ?? '');
                $sheet->setCellValue('B'  . $row, $project['project_name'] ?? '');
                $sheet->setCellValue('C'  . $row, $project['construction_name'] ?? '');
                $sheet->setCellValue('D'  . $row, $project['client_id'] ?? '');
                $sheet->setCellValue('E'  . $row, $project['linked_client_name'] ?? '');
                $sheet->setCellValue('F'  . $row, $project['client_name'] ?? '');
                $sheet->setCellValue('G'  . $row, $project['client_type'] ?? '');
                $sheet->setCellValue('H'  . $row, $project['employee_id'] ?? '');
                $sheet->setCellValue('I'  . $row, $project['employee_name'] ?? '');
                $sheet->setCellValue('J'  . $row, $project['site_agent'] ?? '');
                $sheet->setCellValue('K'  . $row, $project['contract_type'] ?? '');
                $sheet->setCellValue('L'  . $row, $project['director'] ?? '');
                $sheet->setCellValue('M'  . $row, $project['manager'] ?? '');
                $sheet->setCellValue('N'  . $row, $project['business_type'] ?? '');
                $sheet->setCellValue('O'  . $row, $project['housing_type'] ?? '');
                $sheet->setCellValue('P'  . $row, $project['site_region_city'] ?? '');
                $sheet->setCellValue('Q'  . $row, $project['site_region_district'] ?? '');
                $sheet->setCellValue('R'  . $row, $project['site_region_address'] ?? '');
                $sheet->setCellValue('R'  . $row, $project['site_region_address_detail'] ?? '');
                $sheet->setCellValue('S'  . $row, $project['work_type'] ?? '');
                $sheet->setCellValue('T'  . $row, $project['work_subtype'] ?? '');
                $sheet->setCellValue('U'  . $row, $project['work_detail_type'] ?? '');
                $sheet->setCellValue('V'  . $row, $project['contract_work_type'] ?? '');
                $sheet->setCellValue('W'  . $row, $project['bid_type'] ?? '');
                $sheet->setCellValue('X'  . $row, $project['permit_agency'] ?? '');
                $sheet->setCellValue('Y'  . $row, $project['permit_date'] ?? '');
                $sheet->setCellValue('Z'  . $row, $project['contract_date'] ?? '');
                $sheet->setCellValue('AA' . $row, $project['start_date'] ?? '');
                $sheet->setCellValue('AB' . $row, $project['completion_date'] ?? '');
                $sheet->setCellValue('AC' . $row, $project['bid_notice_date'] ?? '');
                $sheet->setCellValue('AD' . $row, $project['initial_contract_amount'] ?? '');
                $sheet->setCellValue('AE' . $row, $project['authorized_company_seal'] ?? '');
                $sheet->setCellValue('AF' . $row, ((int)($project['is_active'] ?? 1) === 1) ? '진행중' : '종료');
                $sheet->setCellValue('AG' . $row, $project['note'] ?? '');
                $sheet->setCellValue('AH' . $row, $project['memo'] ?? '');
                $sheet->setCellValue('AI' . $row, $project['created_at'] ?? '');
                $sheet->setCellValue('AJ' . $row, $project['created_by_name'] ?? $project['created_by'] ?? '');
                $sheet->setCellValue('AK' . $row, $project['updated_at'] ?? '');
                $sheet->setCellValue('AL' . $row, $project['updated_by_name'] ?? $project['updated_by'] ?? '');
    
                $row++;
            }
    
            foreach (range('A', 'Z') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            foreach (range('AA', 'AL') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
    
            $filename = '프로젝트목록_' . date('Ymd_His') . '.xlsx';
    
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: max-age=0');
    
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
    
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
    
            exit;
    
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '엑셀 다운로드 실패 : ' . $e->getMessage();
            exit;
        }
    }



    /* ============================================================
     * API: 프로젝트 양식 엑셀 다운로드
     * URL: GET /api/settings/base-info/project/template
     * ============================================================ */
    public function apiTemplate(): void
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
            '발주자명',
            '발주자분류',
            '현장대리인',
            '소장',
            '실장',
            '업종',
            '주력분야',
            '시도',
            '시군구',
            '상세주소',
            '공종',
            '공종세분류',
            '세부공사종류',
            '계약형태',
            '도급종류',
            '입찰형태',
            '인허가기관',
            '인허가일자',
            '계약일자',
            '착공일자',
            '준공일자',
            '입찰공고일',
            '최초계약금액',
            '사용인감명',
            '비고',
            '메모',
            '진행상태'
        ];
    
        $sheet->fromArray($headers, null, 'A1');
    
        /* ============================================================
         * 시드 데이터 (10건 이상)
         * ============================================================ */
        $rows = [
            ['대치동 석향빌딩 신축', '강남구 대치동 석향빌딩 신축공사 중 석공사', '경동하우징', '민간', '김현수', '박소장', '이실장', '건설업', '석공사', '서울', '강남구', '대치동 123-45', '석공사', '외장석', '화강석 붙임', '도급', '직영', '지명', '강남구청', '2026-01-05', '2026-01-10', '2026-01-15', '2026-06-30', '2025-12-20', '250000000', '석향 사용인감', '주요 현장', '외벽 중심', '진행중'],
            ['청주 커뮤니티센터', '산성유원지 숲속 커뮤니티센터 건립공사 중 석공사', '다옴홀딩스', '민간', '최민재', '정소장', '한실장', '건설업', '석공사', '충북', '청주시', '청주시 상당구 456-12', '석공사', '내장석', '대리석 마감', '하도급', '하도', '수의', '청주시청', '2026-01-07', '2026-01-12', '2026-01-18', '2026-08-31', '2025-12-28', '180000000', '석향 사용인감', '커뮤니티시설', '실내 마감 포함', '진행중'],
            ['남해 호텔 보수', '남해 라피스호텔 보수공사 중 석공사', '선경이엔씨', '민간', '이재훈', '김소장', '윤실장', '건설업', '보수공사', '경남', '남해군', '남해군 남면 78-9', '석공사', '보수', '외벽 석재 보수', '도급', '직영', '지명', '남해군청', '2026-01-08', '2026-01-20', '2026-02-01', '2026-05-31', '2026-01-02', '95000000', '석향 사용인감', '호텔 공사', '기존 외벽 보수', '진행중'],
            ['판교 오피스텔', '판교 오피스텔 신축공사 중 석공사', '세림건설', '민간', '박정우', '임소장', '문실장', '건설업', '석공사', '경기', '성남시', '분당구 판교동 111-2', '석공사', '외장석', '건물 외벽 마감', '하도급', '하도', '경쟁', '성남시청', '2026-02-01', '2026-02-10', '2026-02-15', '2026-09-30', '2026-01-15', '320000000', '석향 사용인감', '분당권역', '오피스텔 신축', '진행중'],
            ['세종 업무시설', '세종 업무시설 신축공사 중 석공사', '한빛개발', '민간', '송도윤', '오소장', '배실장', '건설업', '석공사', '세종', '세종시', '세종시 나성동 88-1', '석공사', '내장석', '로비 석재 마감', '도급', '직영', '수의', '세종시청', '2026-02-03', '2026-02-14', '2026-02-20', '2026-10-31', '2026-01-20', '210000000', '석향 사용인감', '공공업무시설 인접', '로비/벽체', '진행중'],
            ['부산 상가 신축', '부산 상가 신축공사 중 석공사', '청우종합건설', '민간', '정태호', '신소장', '조실장', '건설업', '석공사', '부산', '해운대구', '우동 222-10', '석공사', '외장석', '상가 외벽 석재', '하도급', '하도', '경쟁', '해운대구청', '2026-02-05', '2026-02-18', '2026-03-01', '2026-11-15', '2026-01-25', '275000000', '석향 사용인감', '상가시설', '외장 위주', '진행중'],
            ['대전 주상복합', '대전 주상복합 신축공사 중 석공사', '미래디자인', '민간', '권지석', '차소장', '남실장', '건설업', '석공사', '대전', '유성구', '봉명동 333-4', '석공사', '내외장', '대리석 및 화강석', '도급', '직영', '지명', '유성구청', '2026-02-08', '2026-02-22', '2026-03-05', '2026-12-20', '2026-01-28', '410000000', '석향 사용인감', '주상복합', '고급 마감', '진행중'],
            ['인천 물류센터', '인천 물류센터 증축공사 중 석공사', '대한석재', '민간', '장민혁', '류소장', '백실장', '건설업', '증축공사', '인천', '서구', '가좌동 99-8', '석공사', '보수', '출입구 석재 마감', '하도급', '하도', '수의', '인천서구청', '2026-02-10', '2026-02-25', '2026-03-10', '2026-07-31', '2026-02-01', '87000000', '석향 사용인감', '증축 현장', '소규모 공사', '진행중'],
            ['제주 리조트', '제주 리조트 신축공사 중 석공사', '우림산업', '민간', '고준혁', '민소장', '서실장', '건설업', '석공사', '제주', '서귀포시', '중문동 555-7', '석공사', '외장석', '리조트 외벽 화강석', '도급', '직영', '지명', '서귀포시청', '2026-02-12', '2026-03-01', '2026-03-15', '2027-01-31', '2026-02-05', '530000000', '석향 사용인감', '리조트 현장', '대형 프로젝트', '진행중'],
            ['광주 오피스', '광주 오피스 리모델링 공사 중 석공사', '동해물류', '민간', '하성민', '노소장', '진실장', '건설업', '리모델링', '광주', '서구', '치평동 777-3', '석공사', '내장석', '로비 리모델링', '하도급', '하도', '수의', '광주서구청', '2026-02-15', '2026-03-05', '2026-03-20', '2026-06-30', '2026-02-08', '76000000', '석향 사용인감', '리모델링 현장', '실내 중심', '진행중'],
            ['평택 공장 증설', '평택 공장 증설공사 중 석공사', '세영무역', '민간', '유동현', '구소장', '도실장', '건설업', '공장시설', '경기', '평택시', '청북읍 888-9', '석공사', '외장석', '관리동 외벽 석재', '도급', '직영', '경쟁', '평택시청', '2026-02-18', '2026-03-10', '2026-03-25', '2026-09-15', '2026-02-10', '165000000', '석향 사용인감', '공장현장', '관리동 포함', '진행중'],
            ['송도 복합시설', '송도 복합시설 신축공사 중 석공사', '경동하우징', '민간', '김도형', '천소장', '추실장', '건설업', '복합시설', '인천', '연수구', '송도동 999-1', '석공사', '내외장', '복합시설 석재 마감', '도급', '직영', '지명', '연수구청', '2026-02-20', '2026-03-15', '2026-04-01', '2027-02-28', '2026-02-12', '620000000', '석향 사용인감', '대형 복합시설', '내외장 포함', '진행중'],
        ];
    
        $sheet->fromArray($rows, null, 'A2');
    
        /* ============================================================
         * 자동 컬럼폭
         * ============================================================ */
        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        foreach (range('AA', 'AC') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    
        $filename = 'project_template.xlsx';
    
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');
    
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    
        exit;
    }









    





    





}