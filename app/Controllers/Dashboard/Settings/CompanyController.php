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

        $userId = $_SESSION['user']['id'] ?? null;
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => '인증 오류']);
            return;
        }

        $data = [
            'company_name_ko' => trim($_POST['company_name_ko'] ?? ''),
            'company_name_en' => trim($_POST['company_name_en'] ?? '') ?: null,
            'ceo_name'        => trim($_POST['ceo_name'] ?? '') ?: null,
            'biz_number'      => preg_replace('/[^0-9]/', '', $_POST['biz_number'] ?? ''),
            'corp_number'     => preg_replace('/[^0-9]/', '', $_POST['corp_number'] ?? ''),
            'found_date'      => $_POST['found_date'] ?? null,
            'biz_type'        => $_POST['biz_type'] ?? null,
            'biz_item'        => $_POST['biz_item'] ?? null,
            'addr_main'       => $_POST['addr_main'] ?? null,
            'addr_detail'     => $_POST['addr_detail'] ?? null,
            'tel'             => $_POST['tel'] ?? null,
            'fax'             => $_POST['fax'] ?? null,
            'tax_email'       => $_POST['tax_email'] ?? null,
            'sub_email'       => $_POST['sub_email'] ?? null,
            'company_website' => $_POST['company_website'] ?? null,
            'sns_instagram'   => $_POST['sns_instagram'] ?? null,
            'company_about'   => $_POST['company_about'] ?? null,
            'company_history' => $_POST['company_history'] ?? null,
        ];

        echo json_encode(
            $this->service->save($data, $userId),
            JSON_UNESCAPED_UNICODE
        );
    }
}
