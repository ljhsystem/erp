DELIMITER $$
CREATE PROCEDURE migrate_20260827_20_down()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_daily_income_non_tax_revision_attachments LIMIT 1)
       OR EXISTS (SELECT 1 FROM system_attachments LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Attachment 원본 또는 Revision 연결이 있어 Down할 수 없습니다.';
    END IF;
    DROP TABLE institution_daily_income_non_tax_revision_attachments;
    DROP TABLE system_attachments;
    DELETE FROM system_file_upload_policies
    WHERE policy_key='daily_income_non_taxable_evidence';
END$$
CALL migrate_20260827_20_down()$$
DROP PROCEDURE migrate_20260827_20_down$$
DELIMITER ;
