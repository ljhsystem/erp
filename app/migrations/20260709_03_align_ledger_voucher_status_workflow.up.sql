UPDATE `ledger_vouchers`
SET `status` = CASE UPPER(TRIM(COALESCE(`status`, '')))
    WHEN 'DRAFT' THEN 'DRAFT'
    WHEN 'CONFIRMED' THEN 'REVIEW_REQUESTED'
    WHEN 'REVIEW_REQUESTED' THEN 'REVIEW_REQUESTED'
    WHEN 'REVIEWED' THEN 'REVIEWED'
    WHEN 'POSTED' THEN 'POSTED'
    WHEN 'CLOSED' THEN 'CLOSED'
    WHEN 'DELETED' THEN 'DELETED'
    ELSE 'DRAFT'
END;

ALTER TABLE `ledger_vouchers`
    MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'DRAFT' COMMENT '전표 상태(DRAFT, REVIEW_REQUESTED, REVIEWED, POSTED, CLOSED, DELETED)';

DELETE FROM `system_codes`
WHERE `code_group` = 'VOUCHER_STATUS';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 10, 'VOUCHER_STATUS', '전표상태', 'DRAFT', '작성중', '전표 작성 중 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 20, 'VOUCHER_STATUS', '전표상태', 'REVIEW_REQUESTED', '검토요청', '검토 요청이 접수된 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 30, 'VOUCHER_STATUS', '전표상태', 'REVIEWED', '검토완료', '검토가 완료된 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 40, 'VOUCHER_STATUS', '전표상태', 'POSTED', '전표승인', '장부 반영이 완료된 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 50, 'VOUCHER_STATUS', '전표상태', 'CLOSED', '마감', '전표가 마감된 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 60, 'VOUCHER_STATUS', '전표상태', 'DELETED', '삭제', '전표가 삭제된 상태', 1, 'SYSTEM', 'SYSTEM';
