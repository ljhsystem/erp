ALTER TABLE `ledger_inventory_balance_items`
  MODIFY COLUMN `business_unit` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '재고 귀속 사업구분(BUSINESS_UNIT)';
