CREATE TABLE `ledger_opening_balances` (
  `id` char(36) NOT NULL COMMENT '기초금액 문서 고유번호',
  `company_id` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL COMMENT '기초금액 적용 회사',
  `fiscal_year` smallint unsigned NOT NULL COMMENT '기초금액이 적용되는 회계연도',
  `opening_date` date NOT NULL COMMENT '회계연도 시작 직전 기초잔액 기준일',
  `voucher_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '기초금액을 분개로 보존하는 전표',
  `note` varchar(500) DEFAULT NULL COMMENT '기초금액 문서 비고',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `created_by` varchar(100) NOT NULL COMMENT '생성자',
  `updated_at` datetime DEFAULT NULL COMMENT '수정일시',
  `updated_by` varchar(100) DEFAULT NULL COMMENT '수정자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ledger_opening_balances_company_year` (`company_id`,`fiscal_year`),
  UNIQUE KEY `uq_ledger_opening_balances_voucher` (`voucher_id`),
  KEY `idx_ledger_opening_balances_date` (`opening_date`),
  CONSTRAINT `fk_ledger_opening_balances_company` FOREIGN KEY (`company_id`) REFERENCES `system_company` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_ledger_opening_balances_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `ledger_vouchers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `ck_ledger_opening_balances_fiscal_year` CHECK (`fiscal_year` between 1900 and 9999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회사·회계연도별 기초금액 문서와 기초전표의 일대일 연결';

UPDATE `system_page_registry`
SET `default_route_key`='web.ledger.settings.opening_balances',
    `default_route_url`='/ledger/settings/opening-balances',
    `updated_at`=CURRENT_TIMESTAMP
WHERE `page_key`='ledger.settings.opening_balances';

DELETE FROM `auth_permissions`
WHERE `permission_key`='web.ledger.opening_balances';
