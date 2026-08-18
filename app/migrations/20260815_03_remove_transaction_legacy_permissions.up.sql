DROP PROCEDURE IF EXISTS `migrate_20260815_03_remove_transaction_legacy_permissions`;

DELIMITER $$

CREATE PROCEDURE `migrate_20260815_03_remove_transaction_legacy_permissions`()
BEGIN
    DECLARE permission_count INT DEFAULT 0;
    DECLARE role_mapping_count INT DEFAULT 0;
    DECLARE user_mapping_count INT DEFAULT 0;

    SELECT COUNT(*) INTO permission_count
    FROM `auth_permissions`
    WHERE permission_key IN (
        'api.ledger.transaction.template',
        'api.ledger.transaction.item.template',
        'api.ledger.transaction.settlement.template',
        'api.ledger.transaction.excel',
        'api.ledger.transaction.item.excel',
        'api.ledger.transaction.settlement.excel',
        'api.ledger.transaction.excel_upload',
        'api.ledger.transaction.item.excel_upload',
        'api.ledger.transaction.settlement.excel_upload',
        'api.ledger.transaction.reorder',
        'api.ledger.transaction.create_voucher',
        'api.ledger.transaction.link_voucher',
        'api.ledger.transaction.unlink_voucher',
        'api.ledger.transaction.recommend_voucher',
        'api.ledger.voucher.create_transaction',
        'api.ledger.voucher.link_transaction',
        'api.ledger.voucher.transaction_search',
        'api.import.create_transactions'
    );

    SELECT COUNT(*) INTO role_mapping_count
    FROM `auth_role_permissions` rp
    INNER JOIN `auth_permissions` p ON p.id = rp.permission_id
    WHERE p.permission_key IN (
        'api.ledger.transaction.template', 'api.ledger.transaction.item.template',
        'api.ledger.transaction.settlement.template', 'api.ledger.transaction.excel',
        'api.ledger.transaction.item.excel', 'api.ledger.transaction.settlement.excel',
        'api.ledger.transaction.excel_upload', 'api.ledger.transaction.item.excel_upload',
        'api.ledger.transaction.settlement.excel_upload', 'api.ledger.transaction.reorder',
        'api.ledger.transaction.create_voucher', 'api.ledger.transaction.link_voucher',
        'api.ledger.transaction.unlink_voucher', 'api.ledger.transaction.recommend_voucher',
        'api.ledger.voucher.create_transaction', 'api.ledger.voucher.link_transaction',
        'api.ledger.voucher.transaction_search', 'api.import.create_transactions'
    );

    SELECT COUNT(*) INTO user_mapping_count
    FROM `auth_user_permissions` up
    INNER JOIN `auth_permissions` p ON p.id = up.permission_id
    WHERE p.permission_key IN (
        'api.ledger.transaction.template', 'api.ledger.transaction.item.template',
        'api.ledger.transaction.settlement.template', 'api.ledger.transaction.excel',
        'api.ledger.transaction.item.excel', 'api.ledger.transaction.settlement.excel',
        'api.ledger.transaction.excel_upload', 'api.ledger.transaction.item.excel_upload',
        'api.ledger.transaction.settlement.excel_upload', 'api.ledger.transaction.reorder',
        'api.ledger.transaction.create_voucher', 'api.ledger.transaction.link_voucher',
        'api.ledger.transaction.unlink_voucher', 'api.ledger.transaction.recommend_voucher',
        'api.ledger.voucher.create_transaction', 'api.ledger.voucher.link_transaction',
        'api.ledger.voucher.transaction_search', 'api.import.create_transactions'
    );

    IF permission_count <> 18 OR role_mapping_count <> 36 OR user_mapping_count <> 18 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy permission preflight failed';
    END IF;

    DELETE up FROM `auth_user_permissions` up
    INNER JOIN `auth_permissions` p ON p.id = up.permission_id
    WHERE p.permission_key IN (
        'api.ledger.transaction.template', 'api.ledger.transaction.item.template',
        'api.ledger.transaction.settlement.template', 'api.ledger.transaction.excel',
        'api.ledger.transaction.item.excel', 'api.ledger.transaction.settlement.excel',
        'api.ledger.transaction.excel_upload', 'api.ledger.transaction.item.excel_upload',
        'api.ledger.transaction.settlement.excel_upload', 'api.ledger.transaction.reorder',
        'api.ledger.transaction.create_voucher', 'api.ledger.transaction.link_voucher',
        'api.ledger.transaction.unlink_voucher', 'api.ledger.transaction.recommend_voucher',
        'api.ledger.voucher.create_transaction', 'api.ledger.voucher.link_transaction',
        'api.ledger.voucher.transaction_search', 'api.import.create_transactions'
    );
    IF ROW_COUNT() <> 18 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy user permission delete failed';
    END IF;

    DELETE rp FROM `auth_role_permissions` rp
    INNER JOIN `auth_permissions` p ON p.id = rp.permission_id
    WHERE p.permission_key IN (
        'api.ledger.transaction.template', 'api.ledger.transaction.item.template',
        'api.ledger.transaction.settlement.template', 'api.ledger.transaction.excel',
        'api.ledger.transaction.item.excel', 'api.ledger.transaction.settlement.excel',
        'api.ledger.transaction.excel_upload', 'api.ledger.transaction.item.excel_upload',
        'api.ledger.transaction.settlement.excel_upload', 'api.ledger.transaction.reorder',
        'api.ledger.transaction.create_voucher', 'api.ledger.transaction.link_voucher',
        'api.ledger.transaction.unlink_voucher', 'api.ledger.transaction.recommend_voucher',
        'api.ledger.voucher.create_transaction', 'api.ledger.voucher.link_transaction',
        'api.ledger.voucher.transaction_search', 'api.import.create_transactions'
    );
    IF ROW_COUNT() <> 36 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy role permission delete failed';
    END IF;

    DELETE FROM `auth_permissions`
    WHERE permission_key IN (
        'api.ledger.transaction.template', 'api.ledger.transaction.item.template',
        'api.ledger.transaction.settlement.template', 'api.ledger.transaction.excel',
        'api.ledger.transaction.item.excel', 'api.ledger.transaction.settlement.excel',
        'api.ledger.transaction.excel_upload', 'api.ledger.transaction.item.excel_upload',
        'api.ledger.transaction.settlement.excel_upload', 'api.ledger.transaction.reorder',
        'api.ledger.transaction.create_voucher', 'api.ledger.transaction.link_voucher',
        'api.ledger.transaction.unlink_voucher', 'api.ledger.transaction.recommend_voucher',
        'api.ledger.voucher.create_transaction', 'api.ledger.voucher.link_transaction',
        'api.ledger.voucher.transaction_search', 'api.import.create_transactions'
    );
    IF ROW_COUNT() <> 18 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy permission delete failed';
    END IF;
END$$

CALL `migrate_20260815_03_remove_transaction_legacy_permissions`()$$
DROP PROCEDURE `migrate_20260815_03_remove_transaction_legacy_permissions`$$

DELIMITER ;
