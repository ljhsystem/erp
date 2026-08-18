<?php
namespace App\Services\Institution;

use PDO;

class EmploymentRulePolicyService
{
    public function __construct(private PDO $db) {}

    public function resolve(string $companyId, string $policyCode, string $asOfDate, array $scope = []): ?array
    {
        $sql = "SELECT i.*,v.revision_no,v.effective_from,v.effective_to,r.rule_code,r.title
                FROM institution_employment_rules_items i
                JOIN institution_employment_rules_revisions v ON v.id=i.revision_id
                JOIN institution_employment_rules r ON r.id=v.rule_id
                WHERE r.company_id=:company AND r.is_active=1 AND r.deleted_at IS NULL
                  AND v.status_code='EFFECTIVE' AND v.deleted_at IS NULL
                  AND v.effective_from<=:as_of_from AND (v.effective_to IS NULL OR v.effective_to>=:as_of_to)
                  AND i.policy_code=:policy
                  AND EXISTS (
                    SELECT 1 FROM institution_employment_rules_scopes s
                    WHERE s.revision_id=v.id AND (
                      s.scope_type_code='ALL'
                      OR (s.scope_type_code='DEPARTMENT' AND s.department_id=:department)
                      OR (s.scope_type_code='POSITION' AND s.position_id=:position)
                      OR (s.scope_type_code='JOB' AND s.job_id=:job)
                      OR (s.scope_type_code='EMPLOYMENT_CATEGORY' AND s.employment_category_code=:employment_category)
                    )
                  )
                ORDER BY CASE WHEN EXISTS(SELECT 1 FROM institution_employment_rules_scopes sx WHERE sx.revision_id=v.id AND sx.scope_type_code<>'ALL') THEN 0 ELSE 1 END,
                         v.effective_from DESC,v.revision_no DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':company'=>$companyId,':as_of_from'=>$asOfDate,':as_of_to'=>$asOfDate,':policy'=>$policyCode,':department'=>$scope['department_id']??'',':position'=>$scope['position_id']??'',':job'=>$scope['job_id']??'',':employment_category'=>$scope['employment_category_code']??'']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
