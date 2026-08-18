ALTER TABLE `user_employees`
  ADD COLUMN `representative_qualification_id` char(36) DEFAULT NULL COMMENT '대표 자격증 원장 ID' AFTER `profile_image`,
  ADD KEY `idx_employee_representative_qualification` (`representative_qualification_id`);

UPDATE `user_employees` e
JOIN (
  SELECT employee_id,
         SUBSTRING_INDEX(
           GROUP_CONCAT(id ORDER BY (request_key LIKE 'LEGACY-EMPLOYEE-CERTIFICATE-%') DESC, created_at DESC, id DESC),
           ',',
           1
         ) AS qualification_id
  FROM `institution_qualifications_employee_records`
  WHERE deleted_at IS NULL
  GROUP BY employee_id
) q ON q.employee_id = e.id
SET e.representative_qualification_id = q.qualification_id
WHERE e.representative_qualification_id IS NULL;

ALTER TABLE `user_employees`
  ADD CONSTRAINT `fk_employee_representative_qualification`
  FOREIGN KEY (`representative_qualification_id`)
  REFERENCES `institution_qualifications_employee_records` (`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
