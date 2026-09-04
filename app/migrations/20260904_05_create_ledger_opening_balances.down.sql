UPDATE `system_page_registry`
SET `default_route_key`='web.ledger.opening_balances',
    `default_route_url`=NULL,
    `updated_at`=CURRENT_TIMESTAMP
WHERE `page_key`='ledger.settings.opening_balances';

DROP TABLE IF EXISTS `ledger_opening_balances`;
