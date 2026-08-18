-- 인사·노무 공통 SSOT Baseline에서 실제 운영할 근무지 유형을 완성한다.

SET @workplace_code_sort_base := (SELECT COALESCE(MAX(sort_no), 0) FROM system_codes);

INSERT INTO system_codes
    (id, sort_no, code_group, group_name, code, code_name, is_active, created_at, created_by)
SELECT UUID(), @workplace_code_sort_base + seed.ordinal_no,
       'EMPLOYEE_WORKPLACE_TYPE', '직원근무지유형', seed.code, seed.code_name,
       1, CURRENT_TIMESTAMP, 'SYSTEM:PERSONNEL_BASELINE'
FROM (
    SELECT 1 ordinal_no, 'BUSINESS_TRIP' code, '출장' code_name
    UNION ALL
    SELECT 2, 'REMOTE', '재택'
) seed
WHERE NOT EXISTS (
    SELECT 1
    FROM system_codes existing
    WHERE existing.code_group = 'EMPLOYEE_WORKPLACE_TYPE'
      AND existing.code = seed.code
);

ALTER TABLE `user_employee_workplace_assignments`
    DROP CONSTRAINT `chk_employee_workplace_type`,
    ADD CONSTRAINT `chk_employee_workplace_type`
        CHECK (`workplace_type_code` IN ('HEAD_OFFICE','PROJECT','BUSINESS_TRIP','REMOTE','OTHER'));
