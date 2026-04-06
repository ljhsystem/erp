<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/Settings/CompanyController.php'
// 대시보드>설정>기초정보관리>회사정보 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\CompanyService;

class CompanyController
{
    private CompanyService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new CompanyService(DbPdo::conn());
    }

    /* ============================================================
    API: 회사 기본정보 단건 조회
    URL: GET /api/settings/base-info/company/detail
    permission: api.settings.baseinfo.company.view
    controller: CompanyController@apiDetail
    설명:
    - 시스템에 단 1건 존재하는 회사 기본정보 조회
    - id 기반 조회가 아닌 단일 리소스 조회 (Singleton)
    - 브랜드/커버와 달리 상태(active) 개념 없음
    ============================================================ */
    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => true,
            'data'    => $this->service->get()
        ], JSON_UNESCAPED_UNICODE);
    }


    /* ============================================================
    API: 회사 기본정보 저장 (신규/수정)
    URL: POST /api/settings/base-info/company/save
    permission: api.settings.baseinfo.company.save
    controller: CompanyController@apiSave
    설명:
    - 시스템에 단 1건 존재하는 회사 기본정보 저장
    - 기존 데이터가 없으면 신규 생성 (INSERT)
    - 기존 데이터가 존재하면 수정 처리 (UPDATE)
    - 단일 리소스(Singleton) 구조로 항상 1건 유지
    - 프론트에서는 동일 API로 생성/수정 구분 없이 호출
    ============================================================ */
    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        try {
    
            $userId = $_SESSION['user']['id'] ?? null;
            if (!$userId) {
                throw new \Exception('인증 오류');
            }
    
            $input = $_POST ?: json_decode(file_get_contents('php://input'), true);
    
            $data = [
                'company_name_ko' => trim($input['company_name_ko'] ?? ''),
                'company_name_en' => trim($input['company_name_en'] ?? '') ?: null,
                'ceo_name'        => trim($input['ceo_name'] ?? '') ?: null,
                'biz_number'      => preg_replace('/[^0-9]/', '', $input['biz_number'] ?? ''),
                'corp_number'     => preg_replace('/[^0-9]/', '', $input['corp_number'] ?? ''),
                'found_date'      => $input['found_date'] ?? null,
                'biz_type'        => $input['biz_type'] ?? null,
                'biz_item'        => $input['biz_item'] ?? null,
                'addr_main'       => $input['addr_main'] ?? null,
                'addr_detail'     => $input['addr_detail'] ?? null,
                'tel'             => $input['tel'] ?? null,
                'fax'             => $input['fax'] ?? null,
                'tax_email'       => $input['tax_email'] ?? null,
                'sub_email'       => $input['sub_email'] ?? null,
                'company_website' => $input['company_website'] ?? null,
                'sns_instagram'   => $input['sns_instagram'] ?? null,
                'company_about'   => $input['company_about'] ?? null,
                'company_history' => $input['company_history'] ?? null,
            ];
    
            if ($data['company_name_ko'] === '') {
                throw new \Exception('회사명은 필수입니다.');
            }
    
            $result = $this->service->save($data, $userId);
    
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
        } catch (\Throwable $e) {
    
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
