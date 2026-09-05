ALTER TABLE `ledger_opening_balances`
  DROP FOREIGN KEY `fk_ledger_opening_balances_voucher`;

ALTER TABLE `ledger_opening_balances`
  MODIFY COLUMN `opening_date` date NOT NULL COMMENT '해당 회계연도의 기초잔액 적용일이자 회계기간 시작일',
  ADD COLUMN `period_end_date` date NULL COMMENT '해당 회계연도의 회계기간 종료일' AFTER `opening_date`,
  MODIFY COLUMN `voucher_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '금액이 있을 때 생성되는 기초전표 고유번호, 0원 개시는 미생성';

ALTER TABLE `ledger_opening_balances`
  ADD CONSTRAINT `fk_ledger_opening_balances_voucher`
    FOREIGN KEY (`voucher_id`) REFERENCES `ledger_vouchers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

UPDATE `ledger_opening_balances`
SET `period_end_date` = STR_TO_DATE(CONCAT(`fiscal_year`, '-12-31'), '%Y-%m-%d')
WHERE `period_end_date` IS NULL;

ALTER TABLE `ledger_opening_balances`
  MODIFY COLUMN `period_end_date` date NOT NULL COMMENT '해당 회계연도의 회계기간 종료일',
  ADD CONSTRAINT `chk_ledger_opening_balances_period`
    CHECK (`opening_date` <= `period_end_date` AND YEAR(`period_end_date`) = `fiscal_year`);
