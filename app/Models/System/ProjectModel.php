<?php
// 경로: PROJECT_ROOT . '/app/Models/System/ProjectModel.php'
namespace App\Models\System;

use PDO;
use Core\Database;

class ProjectModel
{
    // PDO 연결 객체
    private PDO $db;

    // 생성자에서 PDO 주입 또는 기본 연결 사용
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

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
            LEFT JOIN system_clients c ON p.client_id = c.id
            LEFT JOIN user_employees e ON p.employee_id = e.id
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

        /* =========================================================
         * 전체 컬럼 검색 매핑
         * ========================================================= */
        $fieldMap = [

            // 기본 정보
            'id'                      => ['col'=>'p.id','type'=>'exact'],
            'sort_no'                 => ['col'=>'p.sort_no','type'=>'exact'],
            'project_name'            => ['col'=>'p.project_name','type'=>'like'],
            'construction_name'       => ['col'=>'p.construction_name','type'=>'like'],

            // 관계 정보
            'client_id'               => ['col'=>'p.client_id','type'=>'exact'],
            'employee_id'             => ['col'=>'p.employee_id','type'=>'exact'],
            'linked_client_name'      => ['col'=>'c.client_name','type'=>'like'],
           'client_name'              => ['col'=>'p.client_name','type'=>'like'],
            'employee_name'           => ['col'=>'e.employee_name','type'=>'like'],

            // 인력 정보
            'site_agent'              => ['col'=>'p.site_agent','type'=>'like'],
            'contract_type'           => ['col'=>'p.contract_type','type'=>'like'],
            'director'                => ['col'=>'p.director','type'=>'like'],
            'manager'                 => ['col'=>'p.manager','type'=>'like'],

            // 사업 정보
            'business_type'           => ['col'=>'p.business_type','type'=>'like'],
            'housing_type'            => ['col'=>'p.housing_type','type'=>'like'],

            // 위치 정보
            'site_region_city'        => ['col'=>'p.site_region_city','type'=>'like'],
            'site_region_district'    => ['col'=>'p.site_region_district','type'=>'like'],
            'site_region_address'     => ['col'=>'p.site_region_address','type'=>'like'],
            'site_region_address_detail'=>['col'=>'p.site_region_address_detail','type'=>'like'],

            // 공종 정보
            'work_type'               => ['col'=>'p.work_type','type'=>'like'],
            'work_subtype'            => ['col'=>'p.work_subtype','type'=>'like'],
            'work_detail_type'        => ['col'=>'p.work_detail_type','type'=>'like'],
            'contract_work_type'      => ['col'=>'p.contract_work_type','type'=>'like'],

            // 입찰 정보
            'bid_type'                => ['col'=>'p.bid_type','type'=>'like'],

            // 발주자 정보
            'client_type'             => ['col'=>'p.client_type','type'=>'like'],

            // 기관 정보
            'permit_agency'           => ['col'=>'p.permit_agency','type'=>'like'],

            // 금액 정보
            'initial_contract_amount' => ['col'=>'p.initial_contract_amount','type'=>'exact'],

            // 기타 정보
            'authorized_company_seal' => ['col'=>'p.authorized_company_seal','type'=>'like'],
            'note'                    => ['col'=>'p.note','type'=>'like'],
            'memo'                    => ['col'=>'p.memo','type'=>'like'],

            // 상태 정보
            'is_active'               => ['col'=>'p.is_active','type'=>'exact'],

            // 날짜 정보
            'permit_date'             => ['col'=>'p.permit_date','type'=>'date'],
            'contract_date'           => ['col'=>'p.contract_date','type'=>'date'],
            'start_date'              => ['col'=>'p.start_date','type'=>'date'],
            'completion_date'         => ['col'=>'p.completion_date','type'=>'date'],
            'bid_notice_date'         => ['col'=>'p.bid_notice_date','type'=>'date'],
            'created_at'              => ['col'=>'p.created_at','type'=>'date'],
            'created_by'              => ['col'=>'p.created_by','type'=>'like'],
            'created_by_name'         => ['col'=>"COALESCE(CONCAT('USER:', p1.employee_name), p.created_by)",'type'=>'like'],
            'updated_at'              => ['col'=>'p.updated_at','type'=>'date'],
            'updated_by'              => ['col'=>'p.updated_by','type'=>'like'],
            'updated_by_name'         => ['col'=>"COALESCE(CONCAT('USER:', p2.employee_name), p.updated_by)",'type'=>'like'],
            'deleted_at'              => ['col'=>'p.deleted_at','type'=>'date'],
            'deleted_by'              => ['col'=>'p.deleted_by','type'=>'like'],
            'deleted_by_name'         => ['col'=>"COALESCE(CONCAT('USER:', p3.employee_name), p.deleted_by)",'type'=>'like'],
        ];

        $globalSearch = [];

        /* =========================================================
         * 필터 처리
         * ========================================================= */
        foreach ($filters as $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

            // 전체 검색
            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }

            if (!isset($fieldMap[$field])) continue;

            $col  = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];

            if ($type === 'date') {

                if (is_array($value)) {
                    $sql .= " AND DATE($col) BETWEEN ? AND ?";
                    $params[] = $value['start'];
                    $params[] = $value['end'];
                } else {
                    $sql .= " AND DATE($col) = ?";
                    $params[] = $value;
                }
                continue;
            }

            if ($type === 'like') {
                $sql .= " AND $col LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            if ($type === 'exact') {
                $sql .= " AND $col = ?";
                $params[] = $value;
                continue;
            }
        }

        /* =========================================================
         * 전체 검색(모든 텍스트 컬럼)
         * ========================================================= */
        if (!empty($globalSearch)) {

            $searchCols = [

                'p.project_name','p.construction_name',
                'p.site_agent','p.director','p.manager',
                'p.business_type','p.housing_type',
                'p.site_region_city','p.site_region_district',
                'p.site_region_address','p.site_region_address_detail',
                'p.work_type','p.work_subtype','p.work_detail_type',
                'p.contract_work_type','p.bid_type',
                'p.client_name','p.client_type',
                'p.permit_agency','p.authorized_company_seal',
                'p.note','p.memo',
                'p.created_by','p.updated_by','p.deleted_by',

                'c.client_name',
                'e.employee_name',
                "COALESCE(CONCAT('USER:', p1.employee_name), p.created_by)",
                "COALESCE(CONCAT('USER:', p2.employee_name), p.updated_by)",
                "COALESCE(CONCAT('USER:', p3.employee_name), p.deleted_by)"
            ];

            $sql .= " AND (";

            $first = true;

            foreach ($globalSearch as $keyword) {

                if (!$first) $sql .= " OR ";

                $sql .= "(";

                $colFirst = true;

                foreach ($searchCols as $col) {

                    if (!$colFirst) $sql .= " OR ";

                    $sql .= "$col LIKE ?";
                    $params[] = "%{$keyword}%";

                    $colFirst = false;
                }

                $sql .= ")";
                $first = false;
            }

            $sql .= ")";
        }

        $sql .= " ORDER BY p.sort_no DESC";

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

    /* =========================================================
    * 프로젝트 검색 (Model - RAW 데이터 반환)
    * ========================================================= */
    public function searchPicker(string $keyword = '', int $limit = 20): array
    {
        $limit = max(1, min(100, (int)$limit));

        $keyword = trim($keyword);
        $like = '%' . $keyword . '%';
        $prefix = $keyword . '%';

        $sql = "
            SELECT
                id,
                sort_no,
                project_name,
                construction_name

            FROM system_projects

            WHERE deleted_at IS NULL
            AND (
                project_name LIKE :k1
                OR construction_name LIKE :k2
                OR CAST(sort_no AS CHAR) LIKE :k3
            )

            ORDER BY
                CASE
                    WHEN project_name LIKE :prefix THEN 0
                    ELSE 1
                END,
                project_name ASC

            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':k1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':k3', $like, PDO::PARAM_STR);
        $stmt->bindValue(':prefix', $prefix, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findIdByProjectName(string $projectName): ?string
    {
        $projectName = trim($projectName);

        if ($projectName === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM system_projects
            WHERE project_name = :project_name
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'project_name' => $projectName,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string)$id : null;
    }


    /* -------------------------------------------------------------
    * 프로젝트 생성
    * ------------------------------------------------------------- */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_projects (
                id,
                sort_no,
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
                :sort_no,
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
            'sort_no' => $data['sort_no'] ?? null,

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

            // 기본값 주입
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

            // 기본값 주입
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
                is_active = 0,
                deleted_at = NOW(),
                deleted_by = :actor,
                updated_by = :actor
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
                is_active = 1,
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
    * ID 기준 sort_no 수정
    * ------------------------------------------------------------- */
    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $sql = "UPDATE system_projects SET sort_no = :newSortNo WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'newSortNo' => (int)$newSortNo,
            'id' => $id
        ]);
    }



}
