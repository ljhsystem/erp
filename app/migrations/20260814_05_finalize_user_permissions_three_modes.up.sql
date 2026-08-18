-- 사용자별 권한을 ROLE / EXTEND / REPLACE 3모드 SSOT로 전환한다.
DROP TABLE `auth_user_permission_override_audits`;
DROP TABLE `auth_user_permission_overrides`;

CREATE TABLE `auth_user_permission_profiles` (
  `user_id` varchar(36) NOT NULL COMMENT '사용자',
  `permission_mode` varchar(10) NOT NULL DEFAULT 'ROLE' COMMENT 'ROLE, EXTEND, REPLACE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `chk_auth_user_permission_profile_mode` CHECK (`permission_mode` IN ('ROLE','EXTEND','REPLACE')),
  CONSTRAINT `fk_auth_user_permission_profile_user` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자별 Permission 적용방식 SSOT';

CREATE TABLE `auth_user_permissions` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `permission_id` varchar(36) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auth_user_permission` (`user_id`,`permission_id`),
  KEY `idx_auth_user_permission_permission` (`permission_id`),
  CONSTRAINT `fk_auth_user_permission_user` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_auth_user_permission_permission` FOREIGN KEY (`permission_id`) REFERENCES `auth_permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자별 직접 Permission Mapping';

CREATE TABLE `auth_user_permission_audits` (
  `id` varchar(36) NOT NULL,
  `batch_id` varchar(36) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `username_snapshot` varchar(50) NOT NULL,
  `employee_name_snapshot` varchar(50) DEFAULT NULL,
  `permission_id` varchar(36) DEFAULT NULL,
  `permission_key_snapshot` varchar(100) DEFAULT NULL,
  `permission_name_snapshot` varchar(100) DEFAULT NULL,
  `change_type` varchar(10) NOT NULL,
  `before_mode` varchar(10) DEFAULT NULL,
  `after_mode` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_auth_user_permission_audit_batch` (`batch_id`),
  KEY `idx_auth_user_permission_audit_user` (`user_id`,`created_at`),
  KEY `idx_auth_user_permission_audit_permission` (`permission_id`,`created_at`),
  CONSTRAINT `chk_auth_user_permission_audit_type` CHECK (`change_type` IN ('MODE','GRANT','REVOKE')),
  CONSTRAINT `chk_auth_user_permission_audit_before_mode` CHECK (`before_mode` IS NULL OR `before_mode` IN ('ROLE','EXTEND','REPLACE')),
  CONSTRAINT `chk_auth_user_permission_audit_after_mode` CHECK (`after_mode` IS NULL OR `after_mode` IN ('ROLE','EXTEND','REPLACE')),
  CONSTRAINT `fk_auth_user_permission_audit_user` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_auth_user_permission_audit_permission` FOREIGN KEY (`permission_id`) REFERENCES `auth_permissions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자 Permission Mode 및 직접 Permission 영구 감사';

INSERT INTO auth_user_permission_profiles (user_id, permission_mode, created_at, created_by, updated_at, updated_by)
SELECT id, 'ROLE', NOW(), 'SYSTEM:MIGRATION', NOW(), 'SYSTEM:MIGRATION' FROM auth_users;

-- 라우트 동기화가 선생성한 canonical 중복행은 기존 Override Permission ID 보존을 위해 제거한다.
DELETE rp FROM auth_role_permissions rp
JOIN auth_permissions p ON p.id=rp.permission_id
WHERE p.permission_key IN ('api.settings.user_permission.list','api.settings.user_permission.detail','api.settings.user_permission.save');
DELETE FROM auth_permissions
WHERE permission_key IN ('api.settings.user_permission.list','api.settings.user_permission.detail','api.settings.user_permission.save');

UPDATE auth_permissions SET permission_key = REPLACE(permission_key, 'api.settings.user_permission_override.', 'api.settings.user_permission.'),
  permission_name = REPLACE(permission_name, 'Override', '개별권한'),
  description = REPLACE(description, 'Override', '개별권한')
WHERE permission_key IN ('api.settings.user_permission_override.list','api.settings.user_permission_override.detail','api.settings.user_permission_override.save');

INSERT INTO auth_role_permissions (id, role_id, permission_id, created_at, created_by)
SELECT UUID(), r.id, p.id, NOW(), 'SYSTEM:MIGRATION'
FROM auth_roles r JOIN auth_permissions p ON p.permission_key IN
 ('api.settings.user_permission.list','api.settings.user_permission.detail','api.settings.user_permission.save')
LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id AND rp.permission_id=p.id
WHERE r.role_key IN ('super_admin','admin') AND rp.id IS NULL;
