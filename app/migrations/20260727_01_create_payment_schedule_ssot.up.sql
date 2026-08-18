-- 지급의무·지급계획 SSOT와 실제 은행 출금 배분 기반을 생성한다.
-- 현재 범위는 원화(KRW) 및 BANK_TRANSACTION 출금 원본만 지원한다.

CREATE TABLE IF NOT EXISTS `ledger_payment_schedules` (
    `id` VARCHAR(36) COLLATE utf8mb4_general_ci NOT NULL COMMENT '고유 ID(UUID)',
    `sort_no` INT UNSIGNED NOT NULL COMMENT '정렬순서',
    `source_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '원천 유형',
    `source_id` VARCHAR(36) COLLATE utf8mb4_general_ci NOT NULL COMMENT '원천 ID',
    `source_line_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '원천 라인 키(헤더는 HEADER)',
    `payment_due_date` DATE NOT NULL COMMENT '지급예정일',
    `scheduled_amount` DECIMAL(18,2) NOT NULL COMMENT '지급예정액(KRW)',
    `client_id` VARCHAR(36) COLLATE utf8mb4_general_ci NULL COMMENT '거래처 ID',
    `project_id` VARCHAR(36) COLLATE utf8mb4_general_ci NULL COMMENT '프로젝트 ID',
    `assignee_id` VARCHAR(36) COLLATE utf8mb4_general_ci NULL COMMENT '담당 직원 ID',
    `payment_bank_account_id` VARCHAR(50) COLLATE utf8mb4_general_ci NULL COMMENT '지급 계좌 ID',
    `is_on_hold` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '지급보류 여부',
    `hold_reason` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL COMMENT '지급보류 사유',
    `held_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL COMMENT '보류 처리 Actor',
    `held_at` DATETIME NULL COMMENT '보류 처리일시',
    `released_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL COMMENT '보류 해제 Actor',
    `released_at` DATETIME NULL COMMENT '보류 해제일시',
    `memo` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NULL COMMENT '비고',
    `created_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '생성 Actor',
    `updated_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL COMMENT '수정 Actor',
    `deleted_by` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL COMMENT '삭제 Actor',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '생성일시',
    `updated_at` DATETIME NULL COMMENT '수정일시',
    `deleted_at` DATETIME NULL COMMENT '삭제일시',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_payment_schedule_source` (`source_type`, `source_id`, `source_line_key`),
    KEY `idx_payment_schedule_due` (`payment_due_date`, `deleted_at`),
    KEY `idx_payment_schedule_bank` (`payment_bank_account_id`, `payment_due_date`, `deleted_at`),
    KEY `idx_payment_schedule_client` (`client_id`),
    KEY `idx_payment_schedule_project` (`project_id`),
    KEY `idx_payment_schedule_assignee` (`assignee_id`),
    KEY `idx_payment_schedule_hold` (`is_on_hold`, `deleted_at`),
    CONSTRAINT `chk_payment_schedule_amount` CHECK (`scheduled_amount` > 0),
    CONSTRAINT `chk_payment_schedule_hold` CHECK (
        (`is_on_hold` = 0)
        OR (`is_on_hold` = 1 AND `hold_reason` IS NOT NULL AND `held_by` IS NOT NULL AND `held_at` IS NOT NULL)
    ),
    CONSTRAINT `fk_payment_schedule_client`
        FOREIGN KEY (`client_id`) REFERENCES `system_clients` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_payment_schedule_project`
        FOREIGN KEY (`project_id`) REFERENCES `system_projects` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_payment_schedule_assignee`
        FOREIGN KEY (`assignee_id`) REFERENCES `user_employees` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `fk_payment_schedule_bank_account`
        FOREIGN KEY (`payment_bank_account_id`) REFERENCES `system_bank_accounts` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='지급의무 및 지급계획 SSOT(KRW)';

CREATE TABLE IF NOT EXISTS `ledger_payment_schedule_histories` (
    `id` VARCHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '고유 ID(UUID)',
    `payment_schedule_id` VARCHAR(36) COLLATE utf8mb4_general_ci NOT NULL COMMENT '지급예정 ID',
    `action_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '업무이력 유형',
    `before_value` LONGTEXT COLLATE utf8mb4_bin NULL COMMENT '변경 전 JSON',
    `after_value` LONGTEXT COLLATE utf8mb4_bin NULL COMMENT '변경 후 JSON',
    `reason` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NULL COMMENT '처리 사유',
    `actor_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '처리 Actor',
    `acted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '처리일시',
    PRIMARY KEY (`id`),
    KEY `idx_payment_schedule_history_schedule` (`payment_schedule_id`, `acted_at`),
    KEY `idx_payment_schedule_history_action` (`action_type`, `acted_at`),
    CONSTRAINT `chk_payment_schedule_history_before_json`
        CHECK (`before_value` IS NULL OR JSON_VALID(`before_value`)),
    CONSTRAINT `chk_payment_schedule_history_after_json`
        CHECK (`after_value` IS NULL OR JSON_VALID(`after_value`)),
    CONSTRAINT `fk_payment_schedule_history_schedule`
        FOREIGN KEY (`payment_schedule_id`) REFERENCES `ledger_payment_schedules` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='지급예정 및 실제 지급 연결 업무이력';

ALTER TABLE `ledger_payment_schedule_histories`
    DROP FOREIGN KEY IF EXISTS `fk_payment_schedule_history_schedule`;

ALTER TABLE `ledger_payment_schedules`
    MODIFY COLUMN `id` VARCHAR(36) COLLATE utf8mb4_general_ci NOT NULL COMMENT '고유 ID(UUID)',
    MODIFY COLUMN `source_id` VARCHAR(36) COLLATE utf8mb4_general_ci NOT NULL COMMENT '원천 ID';

ALTER TABLE `ledger_payment_schedule_histories`
    MODIFY COLUMN `payment_schedule_id` VARCHAR(36) COLLATE utf8mb4_general_ci NOT NULL COMMENT '지급예정 ID';

SET @payment_history_fk_sql := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ledger_payment_schedule_histories'
          AND CONSTRAINT_NAME = 'fk_payment_schedule_history_schedule'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE ledger_payment_schedule_histories ADD CONSTRAINT fk_payment_schedule_history_schedule FOREIGN KEY (payment_schedule_id) REFERENCES ledger_payment_schedules (id) ON UPDATE RESTRICT ON DELETE RESTRICT'
);
PREPARE payment_history_fk_statement FROM @payment_history_fk_sql;
EXECUTE payment_history_fk_statement;
DEALLOCATE PREPARE payment_history_fk_statement;

ALTER TABLE `ledger_evidence_links`
    ADD INDEX IF NOT EXISTS `idx_evl_payment_schedule_target`
        (`target_type`, `target_id`, `deleted_at`, `amount`),
    ADD INDEX IF NOT EXISTS `idx_evl_payment_schedule_evidence`
        (`evidence_type`, `evidence_id`, `target_type`, `deleted_at`, `amount`),
    ADD CONSTRAINT IF NOT EXISTS `chk_evl_payment_schedule_allocation`
        CHECK (
            `target_type` <> 'PAYMENT_SCHEDULE'
            OR (
                `evidence_type` = 'BANK_TRANSACTION'
                AND `link_type` = 'PAYMENT'
                AND `amount` IS NOT NULL
                AND `amount` > 0
            )
        );

DELETE FROM `auth_role_permissions`
WHERE `id` IN (
    '6f37b483-c672-4cb7-9f5c-12e162398ea7',
    '951c8260-b1b8-4329-887b-d269c1a1e79a'
)
  AND `permission_id` = '24530ccd-0695-4c5d-993b-e8cd4b5bed1f';

DELETE FROM `auth_permissions`
WHERE `id` = '24530ccd-0695-4c5d-993b-e8cd4b5bed1f'
  AND `permission_key` = 'web.ledger.funds.unlinked_transactions';

DELETE FROM `system_menu_registry`
WHERE `menu_key` = 'ledger.funds.unlinked_transactions';

DELETE FROM `system_page_registry`
WHERE `page_key` = 'ledger.funds.unlinked_transactions';

UPDATE `system_page_registry`
SET `page_label` = '지급예정현황',
    `page_description` = '회계관리 > 자금관리 > 지급예정현황',
    `breadcrumb` = '회계관리 > 자금관리 > 지급예정현황',
    `source_description` = '회계관리 > 자금관리 > 지급예정현황',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `page_key` = 'ledger.funds.payment_schedule';

UPDATE `system_menu_registry`
SET `menu_label` = '지급예정현황',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `menu_key` = 'ledger.funds.payment_schedule';

UPDATE `auth_permissions`
SET `page` = '지급예정현황',
    `description` = '지급예정현황 화면 조회',
    `updated_at` = CURRENT_TIMESTAMP,
    `updated_by` = 'SYSTEM:MIGRATION'
WHERE `permission_key` = 'web.ledger.funds.payment_schedule';
