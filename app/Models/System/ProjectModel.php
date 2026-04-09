<?php
// 경로: PROJECT_ROOT . '/app/Models/System/ProjectModel.php'
namespace App\Models\System;

use PDO;

class ProjectModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?: \Core\Database::getInstance()->getConnection();
    }


    /* -------------------------------------------------------------
    * 프로젝트 전체 목록
    * ------------------------------------------------------------- */
    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                p.*,

                c.client_name AS linked_client_name,
                e.employee_name AS employee_name,

                CASE 
                    WHEN p.created_by LIKE 'SYSTEM:%' THEN p.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE p.created_by
                END AS created_by_name,

                CASE 
                    WHEN p.updated_by LIKE 'SYSTEM:%' THEN p.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE p.updated_by
                END AS updated_by_name,

                CASE 
                    WHEN p.deleted_by LIKE 'SYSTEM:%' THEN p.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE p.deleted_by
                END AS deleted_by_name

            FROM system_projects p

            LEFT JOIN system_clients c
                ON p.client_id = c.id

            LEFT JOIN user_employees e
                ON p.employee_id = e.id

            LEFT JOIN user_employees p1
                ON p.created_by NOT LIKE 'SYSTEM:%'
                AND p1.user_id = REPLACE(p.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON p.updated_by NOT LIKE 'SYSTEM:%'
                AND p2.user_id = REPLACE(p.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON p.deleted_by NOT LIKE 'SYSTEM:%'
                AND p3.user_id = REPLACE(p.deleted_by, 'USER:', '')

            WHERE p.deleted_at IS NULL
        ";

        $params = [];

        /* ============================================================
        * 🔥 필터 (거래처와 동일 구조)
        * ============================================================ */
        if (!empty($filters)) {

            $allowed = [
                'code','project_name','construction_name',
                'client_id','employee_id',
                'start_date','end_date',
                'initial_contract_amount',
                'is_active','note',
                'created_at','updated_at'
            ];

            $likeFields = [
                'project_name','construction_name','note'
            ];

            $dateFields = [
                'start_date','end_date','created_at','updated_at'
            ];

            foreach ($filters as $f) {

                $field = $f['field'] ?? '';
                $value = $f['value'] ?? '';

                if (!in_array($field, $allowed, true)) continue;
                if ($value === '' || $value === null) continue;

                // 🔥 날짜
                if (in_array($field, $dateFields, true)) {

                    if (is_array($value) && isset($value['start'], $value['end'])) {
                        $sql .= " AND p.$field BETWEEN ? AND ?";
                        $params[] = $value['start'];
                        $params[] = $value['end'];
                    } else {
                        $sql .= " AND p.$field = ?";
                        $params[] = $value;
                    }

                    continue;
                }

                // 🔥 LIKE
                if (in_array($field, $likeFields, true)) {
                    $sql .= " AND p.$field LIKE ?";
                    $params[] = "%{$value}%";
                    continue;
                }

                // 🔥 일반
                $sql .= " AND p.$field = ?";
                $params[] = $value;
            }
        }

        /* ============================================================
        * 🔥 정렬 통일
        * ============================================================ */
        $sql .= " ORDER BY p.start_date DESC, p.code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* -------------------------------------------------------------
    * 프로젝트 단일 조회 (id 기준)
    * ------------------------------------------------------------- */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,

                c.client_name AS linked_client_name,
                e.employee_name AS employee_name,

                CASE 
                    WHEN p.created_by LIKE 'SYSTEM:%' THEN p.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE p.created_by
                END AS created_by_name,

                CASE 
                    WHEN p.updated_by LIKE 'SYSTEM:%' THEN p.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE p.updated_by
                END AS updated_by_name,

                CASE 
                    WHEN p.deleted_by LIKE 'SYSTEM:%' THEN p.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE p.deleted_by
                END AS deleted_by_name

            FROM system_projects p

            LEFT JOIN system_clients c
                ON p.client_id = c.id

            LEFT JOIN user_employees e
                ON p.employee_id = e.id

            LEFT JOIN user_employees p1
                ON p.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(p.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON p.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(p.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON p.deleted_by NOT LIKE 'SYSTEM:%'
            AND p3.user_id = REPLACE(p.deleted_by, 'USER:', '')

            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }


    /* -------------------------------------------------------------
    * 프로젝트 검색 자동완성
    * ------------------------------------------------------------- */
    public function searchPicker(string $keyword): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                code,
                project_name,
                construction_name
            FROM system_projects
            WHERE deleted_at IS NULL
            AND (
                    project_name LIKE ?
                OR construction_name LIKE ?
            )
            ORDER BY project_name ASC
            LIMIT 20
        ");

        $stmt->execute([
            "%{$keyword}%",
            "%{$keyword}%"
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* -------------------------------------------------------------
    * 프로젝트 생성
    * ------------------------------------------------------------- */
    public function create(array $data): bool
    {
        if (empty($data['id'])) {
            throw new \Exception('id 없음');
        }

        if (empty($data['created_by'])) {
            throw new \Exception('created_by 없음');
        }

        $sql = "
            INSERT INTO system_projects (
                id,
                code,
                project_name,
                client_id,
                employee_id,
                site_agent,
                contract_type,
                director,
                manager,
                business_type,
                housing_type,
                construction_name,
                site_region_city,
                site_region_district,
                site_region_address,
                site_region_address_detail,
                work_type,
                work_subtype,
                work_detail_type,
                contract_work_type,
                bid_type,
                client_name,
                client_type,
                permit_agency,
                permit_date,
                contract_date,
                start_date,
                completion_date,
                bid_notice_date,
                initial_contract_amount,
                authorized_company_seal,
                note,
                memo,
                is_active,
                created_by,
                updated_by
            ) VALUES (
                :id,
                :code,
                :project_name,
                :client_id,
                :employee_id,
                :site_agent,
                :contract_type,
                :director,
                :manager,
                :business_type,
                :housing_type,
                :construction_name,
                :site_region_city,
                :site_region_district,
                :site_region_address,
                :site_region_address_detail,
                :work_type,
                :work_subtype,
                :work_detail_type,
                :contract_work_type,
                :bid_type,
                :client_name,
                :client_type,
                :permit_agency,
                :permit_date,
                :contract_date,
                :start_date,
                :completion_date,
                :bid_notice_date,
                :initial_contract_amount,
                :authorized_company_seal,
                :note,
                :memo,
                :is_active,
                :created_by,
                :updated_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $data['id'],
            'code' => $data['code'] ?? null,

            'project_name' => $data['project_name'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,

            'site_agent' => $data['site_agent'] ?? null,
            'contract_type' => $data['contract_type'] ?? null,
            'director' => $data['director'] ?? null,
            'manager' => $data['manager'] ?? null,

            'business_type' => $data['business_type'] ?? null,
            'housing_type' => $data['housing_type'] ?? null,
            'construction_name' => $data['construction_name'] ?? null,

            'site_region_city' => $data['site_region_city'] ?? null,
            'site_region_district' => $data['site_region_district'] ?? null,
            'site_region_address' => $data['site_region_address'] ?? null,
            'site_region_address_detail' => $data['site_region_address_detail'] ?? null,

            'work_type' => $data['work_type'] ?? null,
            'work_subtype' => $data['work_subtype'] ?? null,
            'work_detail_type' => $data['work_detail_type'] ?? null,
            'contract_work_type' => $data['contract_work_type'] ?? null,

            'bid_type' => $data['bid_type'] ?? null,

            'client_name' => $data['client_name'] ?? null,
            'client_type' => $data['client_type'] ?? null,

            'permit_agency' => $data['permit_agency'] ?? null,
            'permit_date' => $data['permit_date'] ?? null,
            'contract_date' => $data['contract_date'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'completion_date' => $data['completion_date'] ?? null,
            'bid_notice_date' => $data['bid_notice_date'] ?? null,

            'initial_contract_amount' => $data['initial_contract_amount'] ?? null,

            'authorized_company_seal' => $data['authorized_company_seal'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,

            // 🔥 기본값 통일
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,

            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'] ?? $data['created_by'],
        ]);
    }


    /* -------------------------------------------------------------
    * 프로젝트 수정 (id 기준)
    * ------------------------------------------------------------- */
    public function updateById(string $id, array $data): bool
    {
        if (empty($data['updated_by'])) {
            throw new \Exception('updated_by 없음');
        }

        $sql = "
            UPDATE system_projects SET
                project_name = :project_name,
                client_id = :client_id,
                employee_id = :employee_id,

                site_agent = :site_agent,
                contract_type = :contract_type,
                director = :director,
                manager = :manager,

                business_type = :business_type,
                housing_type = :housing_type,
                construction_name = :construction_name,

                site_region_city = :site_region_city,
                site_region_district = :site_region_district,
                site_region_address = :site_region_address,
                site_region_address_detail = :site_region_address_detail,

                work_type = :work_type,
                work_subtype = :work_subtype,
                work_detail_type = :work_detail_type,
                contract_work_type = :contract_work_type,

                bid_type = :bid_type,

                client_name = :client_name,
                client_type = :client_type,

                permit_agency = :permit_agency,
                permit_date = :permit_date,
                contract_date = :contract_date,
                start_date = :start_date,
                completion_date = :completion_date,
                bid_notice_date = :bid_notice_date,

                initial_contract_amount = :initial_contract_amount,

                authorized_company_seal = :authorized_company_seal,

                note = :note,
                memo = :memo,

                is_active = :is_active,

                updated_by = :updated_by

            WHERE id = :id
        ";

        $params = [
            'id' => $id,

            'project_name' => isset($data['project_name']) 
                ? trim((string)$data['project_name']) 
                : null,

            'client_id' => $data['client_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,

            'site_agent' => $data['site_agent'] ?? null,
            'contract_type' => $data['contract_type'] ?? null,
            'director' => $data['director'] ?? null,
            'manager' => $data['manager'] ?? null,

            'business_type' => $data['business_type'] ?? null,
            'housing_type' => $data['housing_type'] ?? null,
            'construction_name' => $data['construction_name'] ?? null,

            'site_region_city' => $data['site_region_city'] ?? null,
            'site_region_district' => $data['site_region_district'] ?? null,
            'site_region_address' => $data['site_region_address'] ?? null,
            'site_region_address_detail' => $data['site_region_address_detail'] ?? null,

            'work_type' => $data['work_type'] ?? null,
            'work_subtype' => $data['work_subtype'] ?? null,
            'work_detail_type' => $data['work_detail_type'] ?? null,
            'contract_work_type' => $data['contract_work_type'] ?? null,

            'bid_type' => $data['bid_type'] ?? null,

            'client_name' => $data['client_name'] ?? null,
            'client_type' => $data['client_type'] ?? null,

            'permit_agency' => $data['permit_agency'] ?? null,
            'permit_date' => $data['permit_date'] ?? null,
            'contract_date' => $data['contract_date'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'completion_date' => $data['completion_date'] ?? null,
            'bid_notice_date' => $data['bid_notice_date'] ?? null,

            'initial_contract_amount' => $data['initial_contract_amount'] ?? null,

            'authorized_company_seal' => $data['authorized_company_seal'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,

            // 🔥 기본값 통일
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,

            'updated_by' => $data['updated_by']
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /* -------------------------------------------------------------
    * 프로젝트 삭제 (id 기준)
    * ------------------------------------------------------------- */
    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_projects
            SET 
                deleted_at = NOW(),
                deleted_by = :actor
            WHERE id = :id
              AND deleted_at IS NULL
        ";
    
        $stmt = $this->db->prepare($sql);
    
        $stmt->execute([
            ':id'    => $id,
            ':actor' => $actor
        ]);
    
        return $stmt->rowCount() > 0;
    }


    /* -------------------------------------------------------------
    * 프로젝트 휴지통 목록
    * ------------------------------------------------------------- */
    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,

                c.client_name AS linked_client_name,
                e.employee_name AS employee_name,

                CASE 
                    WHEN p.created_by LIKE 'SYSTEM:%' THEN p.created_by
                    WHEN p1.employee_name IS NOT NULL THEN CONCAT('USER:', p1.employee_name)
                    ELSE p.created_by
                END AS created_by_name,

                CASE 
                    WHEN p.updated_by LIKE 'SYSTEM:%' THEN p.updated_by
                    WHEN p2.employee_name IS NOT NULL THEN CONCAT('USER:', p2.employee_name)
                    ELSE p.updated_by
                END AS updated_by_name,

                CASE 
                    WHEN p.deleted_by LIKE 'SYSTEM:%' THEN p.deleted_by
                    WHEN p3.employee_name IS NOT NULL THEN CONCAT('USER:', p3.employee_name)
                    ELSE p.deleted_by
                END AS deleted_by_name

            FROM system_projects p

            LEFT JOIN system_clients c
                ON p.client_id = c.id

            LEFT JOIN user_employees e
                ON p.employee_id = e.id

            LEFT JOIN user_employees p1
                ON p.created_by NOT LIKE 'SYSTEM:%'
            AND p1.user_id = REPLACE(p.created_by, 'USER:', '')

            LEFT JOIN user_employees p2
                ON p.updated_by NOT LIKE 'SYSTEM:%'
            AND p2.user_id = REPLACE(p.updated_by, 'USER:', '')

            LEFT JOIN user_employees p3
                ON p.deleted_by NOT LIKE 'SYSTEM:%'
            AND p3.user_id = REPLACE(p.deleted_by, 'USER:', '')

            WHERE p.deleted_at IS NOT NULL
            ORDER BY p.deleted_at DESC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* -------------------------------------------------------------
    * 프로젝트 복원 (id 기준)
    * ------------------------------------------------------------- */
    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_projects
            SET
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NOT NULL
        ";
    
        $stmt = $this->db->prepare($sql);
    
        $stmt->execute([
            ':id' => $id,
            ':actor' => $actor
        ]);
    
        return $stmt->rowCount() > 0;
    }

    /* -------------------------------------------------------------
    * 프로젝트 영구삭제
    * ------------------------------------------------------------- */
    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_projects
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }


    /* -------------------------------------------------------------
    * ID 기준 code 수정
    * ------------------------------------------------------------- */
    public function updateCode(string $id, string $newCode): bool
    {
        $sql = "
            UPDATE system_projects
            SET code = :newCode
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            'newCode' => $newCode,
            'id' => $id
        ]);

        if (!$ok) {
            throw new \Exception('쿼리 실행 실패');
        }

        if ($stmt->rowCount() === 0) {
            throw new \Exception('업데이트된 행이 없습니다.');
        }

        return true;
    }


}