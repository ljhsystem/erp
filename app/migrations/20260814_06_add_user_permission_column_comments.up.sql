ALTER TABLE `auth_user_permission_profiles`
  MODIFY `user_id` varchar(36) NOT NULL COMMENT '사용자 ID',
  MODIFY `permission_mode` varchar(10) NOT NULL DEFAULT 'ROLE' COMMENT '권한 적용방식(ROLE: 역할별, EXTEND: 역할+개인, REPLACE: 개인별)',
  MODIFY `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  MODIFY `created_by` varchar(100) NOT NULL COMMENT '생성자',
  MODIFY `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY `updated_by` varchar(100) NOT NULL COMMENT '수정자';

ALTER TABLE `auth_user_permissions`
  MODIFY `id` varchar(36) NOT NULL COMMENT '개인권한 매핑 ID',
  MODIFY `user_id` varchar(36) NOT NULL COMMENT '사용자 ID',
  MODIFY `permission_id` varchar(36) NOT NULL COMMENT 'Permission ID',
  MODIFY `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '권한부여 일시',
  MODIFY `created_by` varchar(100) NOT NULL COMMENT '권한부여자';

ALTER TABLE `auth_user_permission_audits`
  MODIFY `id` varchar(36) NOT NULL COMMENT '개인권한 감사 ID',
  MODIFY `batch_id` varchar(36) NOT NULL COMMENT '권한변경 작업 묶음 ID',
  MODIFY `user_id` varchar(36) DEFAULT NULL COMMENT '변경 대상 사용자 ID',
  MODIFY `username_snapshot` varchar(50) NOT NULL COMMENT '변경 당시 로그인 ID',
  MODIFY `employee_name_snapshot` varchar(50) DEFAULT NULL COMMENT '변경 당시 직원명',
  MODIFY `permission_id` varchar(36) DEFAULT NULL COMMENT '변경 대상 Permission ID',
  MODIFY `permission_key_snapshot` varchar(100) DEFAULT NULL COMMENT '변경 당시 Permission 키',
  MODIFY `permission_name_snapshot` varchar(100) DEFAULT NULL COMMENT '변경 당시 Permission 명',
  MODIFY `change_type` varchar(10) NOT NULL COMMENT '변경유형(MODE, GRANT, REVOKE)',
  MODIFY `before_mode` varchar(10) DEFAULT NULL COMMENT '변경 전 권한 적용방식',
  MODIFY `after_mode` varchar(10) DEFAULT NULL COMMENT '변경 후 권한 적용방식',
  MODIFY `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '변경일시',
  MODIFY `created_by` varchar(100) NOT NULL COMMENT '변경자';
