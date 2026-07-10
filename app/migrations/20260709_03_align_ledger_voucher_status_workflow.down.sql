UPDATE `ledger_vouchers`
SET `status` = CASE UPPER(TRIM(COALESCE(`status`, '')))
    WHEN 'DRAFT' THEN 'draft'
    WHEN 'REVIEW_REQUESTED' THEN 'confirmed'
    WHEN 'REVIEWED' THEN 'reviewed'
    WHEN 'POSTED' THEN 'posted'
    WHEN 'CLOSED' THEN 'closed'
    WHEN 'DELETED' THEN 'deleted'
    ELSE 'draft'
END;

ALTER TABLE `ledger_vouchers`
    MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT '전표 상태. draft=작성중, posted=확정, locked=마감잠금, deleted=삭제상태';

DELETE FROM `system_codes`
WHERE `code_group` = 'VOUCHER_STATUS';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 10, 'VOUCHER_STATUS', '전표상태', 'draft', '작성중', '전표 작성 중 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 20, 'VOUCHER_STATUS', '전표상태', 'confirmed', '확정', '전표 확정 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 30, 'VOUCHER_STATUS', '전표상태', 'reviewed', '검토완료', '검토가 완료된 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 40, 'VOUCHER_STATUS', '전표상태', 'posted', '전기', '전표 전기 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 50, 'VOUCHER_STATUS', '전표상태', 'closed', '마감', '전표 마감 상태', 1, 'SYSTEM', 'SYSTEM';

INSERT INTO `system_codes` (`id`, `sort_no`, `code_group`, `group_name`, `code`, `code_name`, `note`, `is_active`, `created_by`, `updated_by`)
SELECT UUID(), 60, 'VOUCHER_STATUS', '전표상태', 'deleted', '삭제', '전표 삭제 상태', 1, 'SYSTEM', 'SYSTEM';
