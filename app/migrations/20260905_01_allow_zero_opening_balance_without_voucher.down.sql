ALTER TABLE `ledger_opening_balances`
  DROP FOREIGN KEY `fk_ledger_opening_balances_voucher`,
  DROP CHECK `chk_ledger_opening_balances_period`,
  DROP COLUMN `period_end_date`;

ALTER TABLE `ledger_opening_balances`
  MODIFY COLUMN `opening_date` date NOT NULL COMMENT '회계연도 시작 직전 기초잔액 기준일',
  MODIFY COLUMN `voucher_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '기초금액을 분개로 보존하는 전표';

ALTER TABLE `ledger_opening_balances`
  ADD CONSTRAINT `fk_ledger_opening_balances_voucher`
    FOREIGN KEY (`voucher_id`) REFERENCES `ledger_vouchers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
