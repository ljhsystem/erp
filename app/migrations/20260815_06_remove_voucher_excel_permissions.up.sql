DROP PROCEDURE IF EXISTS `migrate_20260815_06_remove_voucher_excel_permissions`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260815_06_remove_voucher_excel_permissions`()
BEGIN
    IF (SELECT COUNT(*) FROM `auth_permissions` WHERE permission_key IN ('api.ledger.voucher.template', 'api.ledger.voucher.excel', 'api.ledger.voucher.excel_upload')) <> 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Voucher Excel permission preflight failed';
    END IF;
    DELETE up FROM `auth_user_permissions` up
    INNER JOIN `auth_permissions` p ON p.id = up.permission_id
    WHERE p.permission_key IN ('api.ledger.voucher.template', 'api.ledger.voucher.excel', 'api.ledger.voucher.excel_upload');

    DELETE rp FROM `auth_role_permissions` rp
    INNER JOIN `auth_permissions` p ON p.id = rp.permission_id
    WHERE p.permission_key IN ('api.ledger.voucher.template', 'api.ledger.voucher.excel', 'api.ledger.voucher.excel_upload');

    DELETE FROM `auth_permissions`
    WHERE permission_key IN ('api.ledger.voucher.template', 'api.ledger.voucher.excel', 'api.ledger.voucher.excel_upload');

    DELETE FROM `system_user_settings`
    WHERE page_key IN ('ledger.voucher', 'ledger.vouchers')
      AND setting_type IN ('EXCEL_UPLOAD', 'EXCEL_DOWNLOAD');
END$$
DELIMITER ;
CALL `migrate_20260815_06_remove_voucher_excel_permissions`();
DROP PROCEDURE `migrate_20260815_06_remove_voucher_excel_permissions`;
