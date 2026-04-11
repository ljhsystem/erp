<?php
// PROJECT_ROOT/app/Models/System/CompanyModel.php
namespace App\Models\System;

use PDO;
use Core\Database;

class CompanyModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* =========================================================
     * 회사 정보 단건 조회 (항상 1건)
     * ========================================================= */
    public function getOne(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_company
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =========================================================
     * 회사 정보 신규 생성
     * ========================================================= */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_company (
                id,
                company_name_ko,
                company_name_en,
                ceo_name,
                biz_number,
                corp_number,
                found_date,
                biz_type,
                biz_item,
                addr_main,
                addr_detail,
                tel,
                fax,
                tax_email,
                sub_email,
                company_website,
                sns_instagram,
                company_about,
                company_history,
                created_at,
                created_by
            ) VALUES (
                :id,
                :company_name_ko,
                :company_name_en,
                :ceo_name,
                :biz_number,
                :corp_number,
                :found_date,
                :biz_type,
                :biz_item,
                :addr_main,
                :addr_detail,
                :tel,
                :fax,
                :tax_email,
                :sub_email,
                :company_website,
                :sns_instagram,
                :company_about,
                :company_history,
                NOW(),
                :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id'               => $data['id'],
            ':company_name_ko'  => $data['company_name_ko'],
            ':company_name_en'  => $data['company_name_en'],
            ':ceo_name'         => $data['ceo_name'],
            ':biz_number'       => $data['biz_number'],
            ':corp_number'      => $data['corp_number'],
            ':found_date'       => $data['found_date'],
            ':biz_type'         => $data['biz_type'],
            ':biz_item'         => $data['biz_item'],
            ':addr_main'        => $data['addr_main'],
            ':addr_detail'      => $data['addr_detail'],
            ':tel'              => $data['tel'],
            ':fax'              => $data['fax'],
            ':tax_email'        => $data['tax_email'],
            ':sub_email'        => $data['sub_email'],
            ':company_website' => $data['company_website'],
            ':sns_instagram'   => $data['sns_instagram'],
            ':company_about'    => $data['company_about'],
            ':company_history'  => $data['company_history'],
            ':created_by'       => $data['created_by'],
        ]);
    }

    /* =========================================================
     * 회사 정보 수정
     * ========================================================= */
    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_company SET
                company_name_ko = :company_name_ko,
                company_name_en = :company_name_en,
                ceo_name        = :ceo_name,
                biz_number      = :biz_number,
                corp_number     = :corp_number,
                found_date      = :found_date,
                biz_type        = :biz_type,
                biz_item        = :biz_item,
                addr_main       = :addr_main,
                addr_detail     = :addr_detail,
                tel             = :tel,
                fax             = :fax,
                tax_email       = :tax_email,
                sub_email       = :sub_email,
                company_website = :company_website,
                sns_instagram   = :sns_instagram,
                company_about   = :company_about,
                company_history = :company_history,
                updated_at      = NOW(),
                updated_by      = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':company_name_ko' => $data['company_name_ko'],
            ':company_name_en' => $data['company_name_en'],
            ':ceo_name'        => $data['ceo_name'],
            ':biz_number'      => $data['biz_number'],
            ':corp_number'     => $data['corp_number'],
            ':found_date'      => $data['found_date'],
            ':biz_type'        => $data['biz_type'],
            ':biz_item'        => $data['biz_item'],
            ':addr_main'       => $data['addr_main'],
            ':addr_detail'     => $data['addr_detail'],
            ':tel'             => $data['tel'],
            ':fax'             => $data['fax'],
            ':tax_email'       => $data['tax_email'],
            ':sub_email'       => $data['sub_email'],
            ':company_website' => $data['company_website'],
            ':sns_instagram'   => $data['sns_instagram'],
            ':company_about'   => $data['company_about'],
            ':company_history' => $data['company_history'],
            ':updated_by'      => $data['updated_by'],
            ':id'              => $id,
        ]);
    }
}
