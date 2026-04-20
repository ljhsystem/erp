<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\System\CompanyService;
use Core\DbPdo;
use Core\Session;

class CompanyController
{
    private CompanyService $service;

    public function __construct()
    {
        Session::requireAuth();
        $this->service = new CompanyService(DbPdo::conn());
    }

    public function apiDetail()
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => true,
            'data' => $this->service->get(),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function apiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $userId = $_SESSION['user']['id'] ?? null;
            if (!$userId) {
                http_response_code(401);
                throw new \Exception('인증 정보가 없습니다.');
            }

            $rawInput = file_get_contents('php://input');
            $decoded = $rawInput ? json_decode($rawInput, true) : null;
            $input = is_array($decoded) ? $decoded : $_POST;

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }

            $data = [
                'company_name_ko' => trim((string) ($input['company_name_ko'] ?? '')),
                'company_name_en' => $this->nullableString($input['company_name_en'] ?? null),
                'ceo_name' => $this->nullableString($input['ceo_name'] ?? null),
                'biz_number' => preg_replace('/[^0-9]/', '', (string) ($input['biz_number'] ?? '')),
                'corp_number' => preg_replace('/[^0-9]/', '', (string) ($input['corp_number'] ?? '')),
                'found_date' => $this->nullableDate($input['found_date'] ?? null),
                'biz_type' => $this->nullableString($input['biz_type'] ?? null),
                'biz_item' => $this->nullableString($input['biz_item'] ?? null),
                'addr_main' => $this->nullableString($input['addr_main'] ?? null),
                'addr_detail' => $this->nullableString($input['addr_detail'] ?? null),
                'tel' => $this->nullablePhone($input['tel'] ?? null),
                'fax' => $this->nullablePhone($input['fax'] ?? null),
                'tax_email' => $this->nullableString($input['tax_email'] ?? null),
                'sub_email' => $this->nullableString($input['sub_email'] ?? null),
                'company_website' => $this->nullableString($input['company_website'] ?? null),
                'sns_instagram' => $this->nullableString($input['sns_instagram'] ?? null),
                'company_about' => $this->nullableString($input['company_about'] ?? null),
                'company_history' => $this->nullableString($input['company_history'] ?? null),
            ];

            if ($data['company_name_ko'] === '') {
                throw new \Exception('회사명(국문)은 필수입니다.');
            }

            if ($data['biz_number'] !== '' && strlen($data['biz_number']) !== 10) {
                throw new \Exception('사업자등록번호는 숫자 10자리여야 합니다.');
            }

            if ($data['corp_number'] !== '' && strlen($data['corp_number']) !== 13) {
                throw new \Exception('법인등록번호는 숫자 13자리여야 합니다.');
            }

            if ($data['tax_email'] !== null && !filter_var($data['tax_email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('세금계산서 이메일 형식이 올바르지 않습니다.');
            }

            if ($data['sub_email'] !== null && !filter_var($data['sub_email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('서브 이메일 형식이 올바르지 않습니다.');
            }

            if ($data['company_website'] !== null && !filter_var($data['company_website'], FILTER_VALIDATE_URL)) {
                throw new \Exception('홈페이지 주소 형식이 올바르지 않습니다.');
            }

            if ($data['sns_instagram'] !== null && !$this->isValidUrlOrHandle($data['sns_instagram'])) {
                throw new \Exception('인스타그램은 URL 또는 계정명 형식으로 입력해주세요.');
            }

            $result = $this->service->save($data, $userId);
            http_response_code(!empty($result['success']) ? 200 : 400);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code(400);
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \Exception('설립일 형식이 올바르지 않습니다.');
        }

        return $value;
    }

    private function nullablePhone($value): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) ($value ?? ''));
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) < 9 || strlen($digits) > 11) {
            throw new \Exception('전화번호 형식이 올바르지 않습니다.');
        }

        return $digits;
    }

    private function isValidUrlOrHandle(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        return (bool) preg_match('/^@?[A-Za-z0-9._]{2,30}$/', $value);
    }
}
