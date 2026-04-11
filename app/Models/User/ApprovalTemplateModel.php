<?php
// 경로: PROJECT_ROOT/app/Models/User/ApprovalTemplateModel.php
namespace App\Models\User;

use PDO;
use Core\Database;

class ApprovalTemplateModel
{
    // PDO 보관
    private PDO $db;

    // 생성자 – 외부에서 PDO 주입 또는 자동 연결
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /* ============================================================
     * 🔧 공통: 문자열 Normalize (스페이스/개행 제거 + 중복공백 통일)
     * ============================================================ */
    private function normalize(?string $str): string
    {
        if ($str === null) return '';
        $str = trim($str);
        $str = preg_replace('/\s+/u', ' ', $str);
        return $str;
    }

    /* ============================================================
     * 템플릿 전체 조회
     * ============================================================ */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM user_approval_templates
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ============================================================
     * 단건 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM user_approval_templates
            WHERE id = ?
        ");

        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ============================================================
     * template_key 중복 여부
     * ============================================================ */
    public function templateKeyExists(string $key): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM user_approval_templates 
            WHERE template_key = ?
        ");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() > 0;
    }

    /* ============================================================
     * 템플릿 생성
     * ============================================================ */
    public function create(string $id, string $templateKey, array $data): bool
    {
        $name = $this->normalize($data['template_name'] ?? '');
        $doc  = $this->normalize($data['document_type'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO user_approval_templates 
                (id, template_key, template_name, document_type, description, is_active, created_by)
            VALUES 
                (:id, :template_key, :template_name, :document_type, :description, :is_active, :created_by)
        ");

        return $stmt->execute([
            ':id'            => $id,
            ':template_key'  => $templateKey,
            ':template_name' => $name,
            ':document_type' => $doc,
            ':description'   => $data['description'] ?? null,
            ':is_active'     => $data['is_active'] ?? 1,
            ':created_by'    => $data['created_by'] ?? null,
        ]);
    }

    /* ============================================================
     * 템플릿 수정
     * ============================================================ */
    public function update(string $id, array $data): bool
    {
        $name = $this->normalize($data['template_name'] ?? '');
        $doc  = $this->normalize($data['document_type'] ?? null);

        $stmt = $this->db->prepare("
            UPDATE user_approval_templates
            SET
                template_name = :template_name,
                document_type = :document_type,
                description   = :description,
                is_active     = :is_active,
                updated_by    = :updated_by,
                updated_at    = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':template_name' => $name,
            ':document_type' => $doc,
            ':description'   => $data['description'] ?? '',
            ':is_active'     => $data['is_active'] ?? 1,
            ':updated_by'    => $data['updated_by'] ?? null,
            ':id'            => $id,
        ]);
    }

    /* ============================================================
     * 템플릿 삭제
     * ============================================================ */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_approval_templates 
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }

    /* ============================================================
     * 템플릿 중복 검사
     * template_name + document_type 조합이 이미 존재하는지 검사
     * ============================================================ */
    public function existsName(string $name, string $documentType, string $exceptId = null): bool
    {
        if ($exceptId) {
            // 수정기능 → 자기 자신 제외
            $sql = "
                SELECT COUNT(*) FROM user_approval_templates
                WHERE template_name = ?
                  AND document_type = ?
                  AND id <> ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $documentType, $exceptId]);
        } else {
            // 신규 생성 → 모든 row 검사
            $sql = "
                SELECT COUNT(*) FROM user_approval_templates
                WHERE template_name = ?
                  AND document_type = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $documentType]);
        }
    
        return $stmt->fetchColumn() > 0;
    }
    
    
}
