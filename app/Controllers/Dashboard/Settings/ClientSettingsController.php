<?php
// 경로: PROJECT_ROOT . '/app/controllers/dashboard/settings/ClientSettingsController.php'
// 대시보드>설정>기초정보관리>거래처 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\ClientService;

use App\Services\File\FileService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
//use PhpOffice\PhpSpreadsheet\Shared\Date;

class ClientSettingsController
{
    private ClientService $service;
    private FileService $file;

    public function __construct()
    {
        Session::requireAuth();  
        $this->service = new ClientService(DbPdo::conn());  
        $this->file = new FileService(DbPdo::conn());
    }

    // ============================================================
    // API: 거래처 목록 조회
    // URL: GET /api/settings/base-info/client/list
    // permission: 
    // controller: ClientSettingsController@apiList
    // ============================================================
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
    
            if ($filters) {
                $rows = $this->service->search($filters);
            } else {
                $rows = $this->service->getList();
            }
    
            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);
      
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '거래처 목록 조회 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }



    // ============================================================
    // API: 거래처 상세 조회
    // URL: GET /api/settings/base-info/client/detail
    // ============================================================
    public function apiDetail(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {

            $row = $this->service->getById($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => '거래처 없음'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $row
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '거래처 조회 실패',
                'error' => $e->getMessage()
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
                'message' => '검색 중 오류 발생',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }


    
    public function apiSearchPicker(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
            $keyword = trim($_GET['q'] ?? '');
            $rows = $this->service->searchPicker($keyword);
    
            echo json_encode([
                'success' => true,
                'data'    => $rows
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'data'    => [],
                'message' => '검색 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }


    // ============================================================
    // API: 거래처 저장 (신규 + 수정)
    // URL: POST /api/settings/base-info/client/save
    // permission: 
    // controller: ClientSettingsController@apiSave
    // ============================================================
    public function apiSave(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $actorType = 'USER';
    
            /* =========================================================
            payload 생성
            ========================================================= */
    
            $payload = [
    
                'id' => $_POST['id'] ?? null,

                'code' => $_POST['code'] ?? null,
    
                'client_name' => trim($_POST['client_name'] ?? ''),
                'company_name' => $_POST['company_name'] ?? null,
    
                'registration_date' => $_POST['registration_date'] ?? null,
    
                'business_number' => $_POST['business_number'] ?? null,
                'corporation_number'=> $_POST['corporation_number'] ?? null,
    
                'business_type' => $_POST['business_type'] ?? null,
                'business_category' => $_POST['business_category'] ?? null,
                'business_status' => $_POST['business_status'] ?? null,
    
                'address' => $_POST['address'] ?? null,
                'address_detail' => $_POST['address_detail'] ?? null,
    
                'phone' => $_POST['phone'] ?? null,
                'fax' => $_POST['fax'] ?? null,
                'email' => $_POST['email'] ?? null,
    
                'ceo_name' => $_POST['ceo_name'] ?? null,
                'ceo_phone' => $_POST['ceo_phone'] ?? null,
    
                'manager_name' => $_POST['manager_name'] ?? null,
                'manager_phone' => $_POST['manager_phone'] ?? null,
    
                'homepage' => $_POST['homepage'] ?? null,
    
                'bank_name' => $_POST['bank_name'] ?? null,
                'manufacture_number'=> $_POST['manufacture_number'] ?? null,
                'account_holder' => $_POST['account_holder'] ?? null,
    
                'trade_category' => $_POST['trade_category'] ?? null,
    
                'item_category' => $_POST['item_category'] ?? null,
    
                'client_category' => $_POST['client_category'] ?? null,
                'client_type' => $_POST['client_type'] ?? null,
                'tax_type' => $_POST['tax_type'] ?? null,
                'payment_term' => $_POST['payment_term'] ?? null,
    
                'client_grade' => $_POST['client_grade'] ?? null,
    
                'note' => $_POST['note'] ?? null,
                'memo' => $_POST['memo'] ?? null,
    
                'is_active' => 1
                ];    

    
            if ($payload['client_name'] === '') {
    
                echo json_encode([
                    'success' => false,
                    'message' => '거래처명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            /* =========================================================
            기존 데이터 조회 (한번만)
            ========================================================= */
    
            $before = [];

            if (!empty($payload['id'])) {
                $before = $this->service->getById($payload['id']) ?? [];
            }
    
            /* =========================================================
            사업자등록증 삭제 요청
            ========================================================= */
    
            $deleteCert = $_POST['delete_business_certificate'] ?? '0';
    
            if ($deleteCert === '1') {
    
                if (!empty($before) && !empty($before['business_certificate'])) {
                    $this->file->delete($before['business_certificate']);
                }
    
                $payload['business_certificate'] = null;
            }
    
            /* =========================================================
            통장사본 삭제 요청
            ========================================================= */
    
            $deleteBank = $_POST['delete_bank_copy'] ?? '0';
    
            if ($deleteBank === '1') {
    
                if (!empty($before) && !empty($before['bank_copy'])) {
                    $this->file->delete($before['bank_copy']);
                }
    
                $payload['bank_copy'] = null;
            }
    
            /* =========================================================
            사업자등록증 업로드
            ========================================================= */
    
            if (
                isset($_FILES['business_certificate']) &&
                $_FILES['business_certificate']['error'] === UPLOAD_ERR_OK
            ) {
    
                if (!empty($before['business_certificate'])) {
                    $this->file->delete($before['business_certificate']);
                }
    
                $upload = $this->file->uploadBusinessCert(
                    $_FILES['business_certificate']
                );
    
                if (!$upload['success']) {
    
                    echo json_encode([
                        'success' => false,
                        'message' => '사업자등록증 업로드 실패'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
    
                $payload['business_certificate'] = $upload['db_path'];
            }
    
            /* =========================================================
            통장사본 업로드
            ========================================================= */
    
            if (
                isset($_FILES['bank_copy']) &&
                $_FILES['bank_copy']['error'] === UPLOAD_ERR_OK
            ) {
    
                if (!empty($before['bank_copy'])) {
                    $this->file->delete($before['bank_copy']);
                }
    
                $upload = $this->file->uploadBankCopy(
                    $_FILES['bank_copy']
                );
    
                if (!$upload['success']) {
    
                    echo json_encode([
                        'success' => false,
                        'message' => '통장사본 업로드 실패'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
    
                $payload['bank_copy'] = $upload['db_path'];
            }
    
            /* =========================================================
            기존 파일 유지
            ========================================================= */
    
            if (!empty($before)) {
    
                if (!array_key_exists('business_certificate', $payload)) {
                    $payload['business_certificate'] =
                        $before['business_certificate'] ?? null;
                }
    
                if (!array_key_exists('bank_copy', $payload)) {
                    $payload['bank_copy'] =
                        $before['bank_copy'] ?? null;
                }
            }
    
            /* =========================================================
            저장
            ========================================================= */

            $result = $this->service->save($payload, 'USER');

            /* 🔥 사용자 메시지 변환 */
            if (!$result['success']) {

                $msg = $result['message'] ?? '';

                // 🔴 사업자번호 중복
                if (str_contains($msg, 'Duplicate entry') &&
                    str_contains($msg, 'uq_business_number')) {

                    echo json_encode([
                        'success' => false,
                        'message' => '이미 등록된 사업자등록번호입니다.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                // 🔴 기타 에러
                echo json_encode([
                    'success' => false,
                    'message' => $msg ?: '거래처 저장 실패'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            /* ✅ 정상 */
            echo json_encode([
                'success' => true,
                'id'      => $result['id'] ?? null,
                'code'    => $result['code'] ?? null,
                'message' => '저장 완료'
            ], JSON_UNESCAPED_UNICODE);

            } catch (\Throwable $e) {

            /* 🔥 catch에서도 동일 처리 */
            $msg = $e->getMessage();

            // 🔴 사업자번호 중복
            if (str_contains($msg, 'Duplicate entry') &&
                str_contains($msg, 'uq_business_number')) {

                echo json_encode([
                    'success' => false,
                    'message' => '이미 등록된 사업자등록번호입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'success' => false,
                'message' => '거래처 저장 중 오류 발생'
            ], JSON_UNESCAPED_UNICODE);

            }

            exit;
    }

    // ============================================================
    // API: 거래처 삭제
    // URL: POST /api/settings/base-info/client/delete
    // permission: 
    // controller: ClientSettingsController@apiDelete
    // ============================================================
    public function apiDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = $this->service->delete($id, 'USER');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '거래처 삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }


    // ============================================================
    // API: 거래처 휴지통 목록
    // URL: GET /api/settings/base-info/client/trash
    // ============================================================
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
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);

        }

        exit;
    }



    // ============================================================
    // API: 거래처 복원
    // URL: POST /api/settings/base-info/client/restore
    // ============================================================
    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if(!$id){

            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            $result = $this->service->restore($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '거래처 복원 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);

        }

        exit;
    }





    // ============================================================
    // API: 거래처 완전삭제
    // URL: POST /api/settings/base-info/client/purge
    // ============================================================
    public function apiPurge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = $_POST['id'] ?? null;

        if(!$id){

            echo json_encode([
                'success' => false,
                'message' => '거래처 아이디 누락'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        try {

            /* 파일 경로 조회 */

            $client = $this->service->getById($id);

            if(!empty($client['business_certificate'])){

                $this->file->delete(
                    $client['business_certificate']
                );
            
            }
            
            if(!empty($client['bank_copy'])){
            
                $this->file->delete(
                    $client['bank_copy']
                );
            
            }

            $result = $this->service->purge($id, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => '완전삭제 실패',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);

        }

        exit;
    }


    // ============================================================
    // API: 거래처 선택 복원
    // URL: POST /api/settings/base-info/client/restore-bulk
    // ============================================================
    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '복원할 거래처 아이디가 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->restoreBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '선택 복원 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }



    // ============================================================
    // API: 거래처 선택 완전삭제
    // URL: POST /api/settings/base-info/client/purge-bulk
    // ============================================================
    public function apiPurgeBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids) || !is_array($ids)) {
                echo json_encode([
                    'success' => false,
                    'message' => '삭제할 거래처 아이디가 없습니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $this->service->purgeBulk($ids, 'USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '선택 완전삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 거래처 전체 완전삭제
    // URL: POST /api/settings/base-info/client/purge-all
    // ============================================================
    public function apiPurgeAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $result = $this->service->purgeAll('USER');

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => '전체 완전삭제 실패',
                'error'   => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }


    // ============================================================
    // API: 거래처 순서 변경 (RowReorder)
    // URL: POST /api/settings/base-info/client/reorder
    // permission: 
    // controller: ClientSettingsController@apiReorder
    // ============================================================
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
                ]);
                return;
            }
    
            // 🔥 한번에 reorder 처리
            $this->service->reorder($changes);
    
            echo json_encode([
                'success' => true
            ]);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => '순서 저장 실패',
                'error'   => $e->getMessage()
            ]);
        }
    
        exit;
    }


    

    // ============================================================
    // API: 거래처 엑셀 업로드
    // URL: POST /api/settings/base-info/client/excel-upload
    // ============================================================
    public function apiSaveFromExcel(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $actorType = 'SYSTEM_EXCEL_UPLOAD';
    
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
    
            /* --------------------------------------------------
             * 1. 한글 헤더 매핑
             * -------------------------------------------------- */
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
    
            /* --------------------------------------------------
             * 2. 첫 줄 헤더 읽기
             * -------------------------------------------------- */
            $excelHeaders = array_map(function ($v) {
                return trim((string)$v);
            }, $rows[0]);
    
            $columnMap = [];
    
            foreach ($excelHeaders as $index => $headerName) {
                if (isset($headerMap[$headerName])) {
                    $columnMap[$headerMap[$headerName]] = $index;
                }
            }
    
            if (!isset($columnMap['client_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '엑셀 양식이 올바르지 않습니다. [거래처명] 헤더를 확인하세요.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            /* --------------------------------------------------
             * 3. 헤더 제거 후 데이터 처리
             * -------------------------------------------------- */
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
                        $registrationDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($registrationDate)
                            ->format('Y-m-d');
                    } else {
                        $registrationDate = trim((string)$registrationDate);
                    }
                }
    
                $payload = [
                    'client_name'       => isset($columnMap['client_name']) ? trim((string)($row[$columnMap['client_name']] ?? '')) : '',
                    'company_name'      => isset($columnMap['company_name']) ? trim((string)($row[$columnMap['company_name']] ?? '')) : '',
                    'ceo_name'          => isset($columnMap['ceo_name']) ? trim((string)($row[$columnMap['ceo_name']] ?? '')) : '',
                    'business_number'   => isset($columnMap['business_number']) ? trim((string)($row[$columnMap['business_number']] ?? '')) : '',
                    'business_status'   => isset($columnMap['business_status']) ? trim((string)($row[$columnMap['business_status']] ?? '')) : '',
                    'phone'             => isset($columnMap['phone']) ? trim((string)($row[$columnMap['phone']] ?? '')) : '',
                    'email'             => isset($columnMap['email']) ? trim((string)($row[$columnMap['email']] ?? '')) : '',
                    'registration_date' => $registrationDate ?: date('Y-m-d'),
                    'note'              => isset($columnMap['note']) ? trim((string)($row[$columnMap['note']] ?? '')) : ''
                ];
    
                if ($payload['client_name'] === '') {
                    continue;
                }
    
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



    // ============================================================
    // API: 거래처 전체 엑셀 다운로드
    // URL: GET /api/settings/base-info/clients/excel
    // permission: 
    // controller: ClientSettingsController@apidownload
    // ============================================================    
    public function apiDownload(): void
    {    
        try {
    
            // 1️⃣ 데이터 조회
            $clients = $this->service->getList();
    
            // 2️⃣ 엑셀 객체 생성
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
    
            // 3️⃣ 헤더 작성
            $sheet->setCellValue('A1', '코드');
            $sheet->setCellValue('B1', '거래처명');
            $sheet->setCellValue('C1', '사업자번호');
            $sheet->setCellValue('D1', '대표자');
            $sheet->setCellValue('E1', '전화번호');
            $sheet->setCellValue('F1', '이메일');
            $sheet->setCellValue('G1', '주소');
            $sheet->setCellValue('H1', '메모');
    
            // 4️⃣ 데이터 채우기
            $row = 2;
    
            foreach ($clients as $client) {
    
                $sheet->setCellValue('A'.$row, $client['code'] ?? '');
                $sheet->setCellValue('B'.$row, $client['client_name'] ?? '');
                $sheet->setCellValue('C'.$row, $client['business_number'] ?? '');
                $sheet->setCellValue('D'.$row, $client['ceo_name'] ?? '');
                $sheet->setCellValue('E'.$row, $client['phone'] ?? '');
                $sheet->setCellValue('F'.$row, $client['email'] ?? '');
                $sheet->setCellValue('G'.$row, $client['address'] ?? '');
                $sheet->setCellValue('H'.$row, $client['memo'] ?? '');
    
                $row++;
            }
    
            // 5️⃣ 파일명
            $filename = '거래처목록_' . date('Ymd_His') . '.xlsx';
    
            // 6️⃣ 다운로드 헤더
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: max-age=0');
    
            // 7️⃣ 출력
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




    // ============================================================
    // API: 거래처 양식 엑셀 다운로드
    // URL: GET /api/settings/base-info/clients/template
    // permission: 
    // controller: ClientSettingsController@apiTemplate
    // ============================================================    
    public function apiTemplate(): void
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
        * 시드 데이터 (10건 이상)
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

        $filename = 'client_template.xlsx';

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
