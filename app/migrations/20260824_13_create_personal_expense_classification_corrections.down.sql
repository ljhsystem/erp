DROP PROCEDURE IF EXISTS `down_20260824_13_personal_expense_classification_corrections`;
DELIMITER $$
CREATE PROCEDURE `down_20260824_13_personal_expense_classification_corrections`()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'approval_personal_expense_item_classification_corrections'
    ) THEN
        IF (SELECT COUNT(*) FROM `approval_personal_expense_item_classification_corrections`) > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '정정 Snapshot이 존재하여 파괴적 Down을 차단합니다.';
        END IF;
        DROP TABLE `approval_personal_expense_item_classification_corrections`;
    END IF;
END$$
DELIMITER ;
CALL `down_20260824_13_personal_expense_classification_corrections`();
DROP PROCEDURE `down_20260824_13_personal_expense_classification_corrections`;
