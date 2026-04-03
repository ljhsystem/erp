<?php
// 경로: PROJECT_ROOT . '/app/controllers/ledger/AccountController.php'
// 설명:
//  - 회계 계정과목 관리 컨트롤러

namespace App\Controllers\Ledger;

use Core\Session;
use Core\DbPdo;
use App\Services\Ledger\AccountService;
use App\Controllers\System\LayoutController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AccountController
{
    private AccountService $service;
    private LayoutController $layout;

    public function __construct()
    {   
        Session::requireAuth();
        $this->service = new AccountService(DbPdo::conn());
        $this->layout = new LayoutController(DbPdo::conn());
    }

    /* ============================================================
     * 페이지 렌더
     * ============================================================ */

    private function renderPage(string $viewPath, array $params = []): void
    {
        if (!empty($params)) {
            extract($params, EXTR_SKIP);
        }

        ob_start();
        require PROJECT_ROOT . $viewPath;
        $content = ob_get_clean();

        $pageTitle     = $pageTitle     ?? '';
        $pageStyles    = $pageStyles    ?? '';
        $pageScripts   = $pageScripts   ?? '';
        $layoutOptions = $layoutOptions ?? [];

        $this->layout->render([
            'pageTitle'     => $pageTitle,
            'content'       => $content,
            'layoutOptions' => $layoutOptions,
            'pageStyles'    => $pageStyles,
            'pageScripts'   => $pageScripts,
        ]);
    }

    /* ============================================================
     * 계정과목관리 페이지
     * URL: /ledger/account
     * ============================================================ */

    public function index(): void
    {
        $this->renderPage('/app/views/ledger/account/index.php');
    }

    /* ============================================================
     * API: 계정 목록
     * GET /api/ledger/account/list
     * ============================================================ */
    public function apiList(): void
    {  
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $filters = [];
    
            if (!empty($_GET['filters'])) {
                $filters = json_decode($_GET['filters'], true) ?? [];
            }
    
            // 🔥 여기 바꿔라
            $rows = $this->service->getList($filters);
    
            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }

    /* ============================================================
     * API: 계정 트리
     * GET /api/ledger/account/tree
     * ============================================================ */

    public function apiTree(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $rows = $this->service->getTreeStructured();

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     * API: 계정 저장 (신규 + 수정)
     * POST /api/ledger/account/save
     * ============================================================ */

    public function apiSave(): void
    {   
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $actor = makeActor('USER');

            $payload = [

                'account_code'      => $_POST['account_code'] ?? null,
                'account_name'      => $_POST['account_name'] ?? null,
            
                // 🔥 빈값 → NULL 처리
                'parent_id'         => !empty($_POST['parent_id']) ? $_POST['parent_id'] : null,
            
                'account_group'     => $_POST['account_group'] ?? null,
                'normal_balance'    => $_POST['normal_balance'] ?? 'debit',
            
                // 🔥 제거됨 (DB 없음)
                // 'industry_type'
                // 'statement_type'
            
                // 🔥 level은 Service에서 자동계산
                // 'level' 제거
            
                'is_posting'        => isset($_POST['is_posting']) ? (int)$_POST['is_posting'] : 1,
            
                'note'              => $_POST['note'] ?? null,
                'memo'              => $_POST['memo'] ?? null,
            
                'is_active'         => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            
                'updated_by' => $actor
            ];
            
            if (empty($payload['account_code']) || empty($payload['account_name'])) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정코드와 계정명은 필수입니다.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $id = $_POST['id'] ?? null;

            if ($id) {

                $result = $this->service->update($id, $payload);
            
            } else {
            
                $payload['created_by'] = $actor;
            
                $result = $this->service->create($payload);
            }
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
     * API: 계정 삭제
     * POST /api/ledger/account/delete
     * ============================================================ */

    public function apiSoftDelete(): void
    {   
        header('Content-Type: application/json; charset=UTF-8');

        try {

            $id = $_POST['id'] ?? null;

            if (!$id) {

                echo json_encode([
                    'success' => false,
                    'message' => '계정 ID가 없습니다.'
                ], JSON_UNESCAPED_UNICODE);

                exit;
            }

            $actor = makeActor('USER');
            $result = $this->service->softDelete($id, $actor);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        exit;
    }

    /* ============================================================
    * API: 계정 휴지통 목록
    * GET /api/ledger/account/trash
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
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
    
        }
    
        exit;
    }

    /* ============================================================
    * API: 엑셀 템플릿 다운로드
    * GET /api/ledger/account/template
    * ============================================================ */    
    public function apiTemplate(): void
    {    
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        /* ============================================================
         * 🔥 헤더
         * ============================================================ */
        $sheet->fromArray([
            'account_code',
            'account_name',
            'parent_code',
            'account_group',
            'sub_name'
        ], null, 'A1');
    
        /* ============================================================
         * 🔥 씨드 데이터
         * ============================================================ */
        $rows = [
    
            // 자산
            ['1000','현금','','자산',''],
            ['1100','보통예금','1000','자산',''],
            ['1110','국민은행','1100','자산','일반'],
            ['1110','국민은행','1100','자산','법인'],
            ['1120','기업은행','1100','자산',''],
    
            // 부채
            ['2000','외상매입금','','부채',''],
            ['2100','단기차입금','2000','부채',''],
    
            // 자본
            ['3000','자본금','','자본',''],
    
            // 수익
            ['4000','매출','','수익',''],
            ['4100','상품매출','4000','수익',''],
    
            // 비용
            ['5000','급여','','비용',''],
            ['5100','임차료','','비용',''],
            ['5200','통신비','','비용','']
        ];
    
        $sheet->fromArray($rows, null, 'A2');
    
        /* ============================================================
         * 🔥 가독성 (자동 컬럼폭)
         * ============================================================ */
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    
        /* ============================================================
         * 🔥 다운로드
         * ============================================================ */
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="account_template.xlsx"');
        header('Cache-Control: max-age=0');
    
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    
        exit;
    }

    // ============================================================
    // API: 계정 전체 엑셀 다운로드
    // URL: GET /api/ledger/accounts/excel
    // permission: api.ledger.account.export
    // controller: AccountController@apidownloadAllExcel
    // ============================================================

    public function apidownloadAllExcel(): void
    {
        try {

            // 1️⃣ 데이터 조회
            $accounts = $this->service->getAll();

            // 2️⃣ 엑셀 생성
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 3️⃣ 헤더
            $sheet->setCellValue('A1', '코드');
            $sheet->setCellValue('B1', '계정명');
            $sheet->setCellValue('C1', '구분');
            $sheet->setCellValue('D1', '상위계정');
            $sheet->setCellValue('E1', '레벨');
            $sheet->setCellValue('F1', '차/대');
            $sheet->setCellValue('G1', '전표입력');
            $sheet->setCellValue('H1', '사용여부');
            $sheet->setCellValue('I1', '보조계정');
            $sheet->setCellValue('J1', '비고');
            $sheet->setCellValue('K1', '메모');

            // 4️⃣ 데이터
            $row = 2;

            foreach ($accounts as $acc) {

                $sheet->setCellValue('A'.$row, $acc['account_code'] ?? '');
                $sheet->setCellValue('B'.$row, $acc['account_name'] ?? '');
                $sheet->setCellValue('C'.$row, $acc['account_group'] ?? '');
                $sheet->setCellValue('D'.$row, $acc['parent_name'] ?? '');
                $sheet->setCellValue('E'.$row, $acc['level'] ?? '');

                // 차/대 변환
                $sheet->setCellValue('F'.$row,
                    ($acc['normal_balance'] ?? '') === 'debit' ? '차변' : '대변'
                );

                // 전표입력
                $sheet->setCellValue('G'.$row,
                    ($acc['is_posting'] ?? 0) ? '가능' : '불가'
                );

                // 사용여부
                $sheet->setCellValue('H'.$row,
                    ($acc['is_active'] ?? 0) ? '사용' : '미사용'
                );

                // 보조계정
                $sheet->setCellValue('I'.$row,
                    ($acc['allow_sub_account'] ?? 0) ? '허용' : '미허용'
                );

                $sheet->setCellValue('J'.$row, $acc['note'] ?? '');
                $sheet->setCellValue('K'.$row, $acc['memo'] ?? '');

                $row++;
            }

            // 5️⃣ 파일명
            $filename = '계정목록_' . date('Ymd_His') . '.xlsx';

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

    /* ============================================================
    * API: 엑셀 업로드
    * POST /api/ledger/account/excel-upload
    * ============================================================ */


    public function apiExcelUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $actor = makeActor('SYSTEM');
            
            if (empty($_FILES['file']['tmp_name'])) {
                throw new \Exception('파일이 없습니다.');
            }
    
            $file = $_FILES['file']['tmp_name'];
    
            // 🔥 XLSX 로드
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
    
            $rows = $sheet->toArray();
    
            // 🔥 헤더 제거
            array_shift($rows);
    
            $createdAccounts = [];
            $errors = [];
    
            foreach ($rows as $row) {
    
                [$code, $name, $parentCode, $group, $subName] = array_pad($row, 5, null);
    

                

                // 🔥 문자열 정리 (이거 중요)
                $code = trim((string)$code);
                $name = trim((string)$name);
                $parentCode = trim((string)$parentCode);
                $group = trim((string)$group);
                $subName = trim((string)$subName);
    
                if (!$code || !$name) {
                    $errors[] = "필수값 누락: {$code}";
                    continue;
                }
    
                // 🔥 부모 찾기
                $parentId = null;
    
                if ($parentCode) {
                    $parent = $this->service->findByCode($parentCode);
                    if ($parent) {
                        $parentId = $parent['id'];
                    }
                }
    
                // 🔥 계정 존재 확인
                $account = $this->service->findByCode($code);
    
                if (!$account) {
    
                    $payload = [
                        'account_code'   => $code,
                        'account_name'   => $name,
                        'parent_id'      => $parentId,
                        'account_group'  => $group,
    
                        // 🔥 자동 처리 추천
                        'normal_balance' => in_array($group, ['자산','비용']) ? 'debit' : 'credit',
    
                        'is_posting'     => 1,
                        'is_active'      => 1,
                        'created_by' => $actor
                    ];
    
                    $result = $this->service->create($payload);
    
                    if (!$result['success']) {
                        $errors[] = "계정 생성 실패: {$code}";
                        continue;
                    }
    
                    $account = [
                        'id' => $result['id']
                    ];
    
                } else {
    
                    $this->service->update($account['id'], [
                        'account_code'  => $code,
                        'account_name'  => $name,
                        'parent_id'     => $parentId,
                        'account_group' => $group,
                        'is_posting'    => 1,
                        'is_active'     => 1,
                        'updated_by' => $actor
                    ]);
                }
    
                // 🔥 안전장치
                if (empty($account['id'])) {
                    $errors[] = "계정 ID 없음: {$code}";
                    continue;
                }
    
                // 🔥 보조계정
                if ($subName) {

                    // 1. 보조계정 생성
                    $this->service->createSubAccount([
                        'account_id' => $account['id'],
                        'sub_name'   => $subName,
                        'created_by' => $actor
                    ]);

                    // 2. 🔥 계정 설정 자동 ON (핵심)
                    $this->service->update($account['id'], [
                        'allow_sub_account' => 1,
                        'updated_by' => $actor
                    ]);
                }
    
                $createdAccounts[] = $code;
            }
    
            echo json_encode([
                'success' => true,
                'created_count' => count($createdAccounts),
                'errors' => $errors
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }




    // 순서변경
    public function apiReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $input = json_decode(file_get_contents('php://input'), true);
    
            $changes = $input['changes'] ?? [];
    
            if(empty($changes)){
                throw new \Exception('변경 데이터 없음');
            }
    
            $this->service->reorder($changes);
    
            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }

    public function apiRestore(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $id = $_POST['id'] ?? null;
    
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정 ID 없음'
                ]);
                exit;
            }
    
            $actor = makeActor('USER');
            $result = $this->service->restore($id, $actor);
    
            echo json_encode($result);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        exit;
    }

    public function apiHardDelete(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $id = $_POST['id'] ?? null;
    
            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => '계정 ID 없음'
                ]);
                exit;
            }
    
            $actor = makeActor('USER');
            $result = $this->service->hardDelete($id, $actor);
    
            echo json_encode($result);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    
        exit;
    }


    public function apiDetail(): void
    {
        $code = $_GET['code'] ?? null;

        if (!$code) {
            echo json_encode(['success'=>false]);
            exit;
        }

        $row = $this->service->getDetailByAccountCode($code);

        echo json_encode([
            'success'=>true,
            'data'=>$row
        ]);
    }


    public function apiRestoreBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
    
            if(empty($ids)){
                throw new \Exception('ids 없음');
            }
    
            $this->service->restoreBulk($ids);
    
            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }


    public function apiHardDeleteBulk(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
    
            if(empty($ids)){
                throw new \Exception('ids 없음');
            }
    
            foreach($ids as $id){
                $this->service->hardDelete($id, makeActor('USER'));
            }
    
            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }


    public function apiHardDeleteAll(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    
        try {
    
            $this->service->hardDeleteAll();
    
            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    
        exit;
    }
}