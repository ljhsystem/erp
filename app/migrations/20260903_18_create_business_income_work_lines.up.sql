SET NAMES utf8mb4;

DELIMITER $$
CREATE PROCEDURE migrate_20260903_18_create_business_income_work_lines()
BEGIN
    IF EXISTS (SELECT 1 FROM institution_business_income_items) OR EXISTS (SELECT 1 FROM ledger_evidence_business_income) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 사업소득 자료가 있어 작업내역 Grain을 자동 생성할 수 없습니다.';
    END IF;

    ALTER TABLE institution_business_income_items
        ADD COLUMN other_deduction_reason VARCHAR(500) NULL COMMENT '기타공제 사유' AFTER other_deduction_amount,
        ALGORITHM=INSTANT, LOCK=NONE;

    ALTER TABLE ledger_evidence_business_income
        ADD COLUMN raw_other_deduction_reason VARCHAR(500) NULL COMMENT '원본 기타공제 사유' AFTER raw_other_deduction_amount,
        ALGORITHM=INSTANT, LOCK=NONE;

    CREATE TABLE institution_business_income_work_lines (
        id VARCHAR(36) NOT NULL,
        business_income_item_id VARCHAR(36) NOT NULL,
        item_name VARCHAR(200) NOT NULL COMMENT '품명',
        item_specification VARCHAR(255) NULL COMMENT '규격',
        item_unit_name VARCHAR(50) NOT NULL COMMENT '단위',
        item_quantity DECIMAL(18,4) NOT NULL COMMENT '수량',
        item_unit_price DECIMAL(18,2) NOT NULL COMMENT '단가',
        calculated_amount DECIMAL(18,2) NOT NULL COMMENT '수량 곱하기 단가',
        adjustment_amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '증감액',
        adjustment_reason VARCHAR(500) NULL COMMENT '증감 사유',
        calculation_note VARCHAR(1000) NULL COMMENT '산정내역',
        final_amount DECIMAL(18,2) NOT NULL COMMENT '작업 확정금액',
        sort_no INT UNSIGNED NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100) NOT NULL,
        deleted_at DATETIME NULL,
        deleted_by VARCHAR(100) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_business_income_work_line_order (business_income_item_id,sort_no),
        CONSTRAINT fk_business_income_work_line_item FOREIGN KEY (business_income_item_id) REFERENCES institution_business_income_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_business_income_work_line_amounts CHECK (item_quantity>0 AND item_unit_price>=0 AND calculated_amount>=0 AND final_amount>=0),
        CONSTRAINT chk_business_income_work_line_calculation CHECK (calculated_amount=ROUND(item_quantity*item_unit_price,2) AND final_amount=calculated_amount+adjustment_amount),
        CONSTRAINT chk_business_income_work_line_adjustment CHECK (adjustment_amount=0 OR (adjustment_reason IS NOT NULL AND TRIM(adjustment_reason)<>''))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사업소득 소득자별 외주 작업내역';

    CREATE TABLE ledger_evidence_business_income_work_lines (
        id VARCHAR(36) NOT NULL,
        evidence_id VARCHAR(36) NOT NULL,
        source_work_line_id VARCHAR(36) NOT NULL,
        raw_item_name VARCHAR(200) NOT NULL,
        raw_item_specification VARCHAR(255) NULL,
        raw_item_unit_name VARCHAR(50) NOT NULL,
        raw_item_quantity DECIMAL(18,4) NOT NULL,
        raw_item_unit_price DECIMAL(18,2) NOT NULL,
        raw_calculated_amount DECIMAL(18,2) NOT NULL,
        raw_adjustment_amount DECIMAL(18,2) NOT NULL,
        raw_adjustment_reason VARCHAR(500) NULL,
        raw_calculation_note VARCHAR(1000) NULL,
        raw_final_amount DECIMAL(18,2) NOT NULL,
        raw_sort_no INT UNSIGNED NOT NULL,
        source_hash CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_business_income_evidence_work_source (evidence_id,source_work_line_id),
        KEY idx_business_income_evidence_work_order (evidence_id,raw_sort_no,id),
        CONSTRAINT fk_business_income_evidence_work_evidence FOREIGN KEY (evidence_id) REFERENCES ledger_evidence_business_income(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_business_income_evidence_work_source FOREIGN KEY (source_work_line_id) REFERENCES institution_business_income_work_lines(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_business_income_evidence_work_amounts CHECK (raw_item_quantity>0 AND raw_item_unit_price>=0 AND raw_calculated_amount>=0 AND raw_final_amount>=0),
        CONSTRAINT chk_business_income_evidence_work_calculation CHECK (raw_calculated_amount=ROUND(raw_item_quantity*raw_item_unit_price,2) AND raw_final_amount=raw_calculated_amount+raw_adjustment_amount),
        CONSTRAINT chk_business_income_evidence_work_hash CHECK (source_hash REGEXP '^[0-9a-f]{64}$')
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='승인 사업소득 외주 작업내역 원본';
END$$
DELIMITER ;

CALL migrate_20260903_18_create_business_income_work_lines();
DROP PROCEDURE migrate_20260903_18_create_business_income_work_lines;
