DELIMITER $$
CREATE TRIGGER trg_statutory_standard_bu
BEFORE UPDATE ON system_statutory_standards
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision은 수정할 수 없습니다. 신규 정정 Revision을 등록하세요.';
END$$

CREATE TRIGGER trg_statutory_standard_bd
BEFORE DELETE ON system_statutory_standards
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Revision은 삭제할 수 없습니다.';
END$$

CREATE TRIGGER trg_statutory_standard_source_bu
BEFORE UPDATE ON system_statutory_standard_sources
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Source는 수정할 수 없습니다. 신규 Revision에 Source를 등록하세요.';
END$$

CREATE TRIGGER trg_statutory_standard_source_bd
BEFORE DELETE ON system_statutory_standard_sources
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='확정된 법정기준 Source는 삭제할 수 없습니다.';
END$$
DELIMITER ;
