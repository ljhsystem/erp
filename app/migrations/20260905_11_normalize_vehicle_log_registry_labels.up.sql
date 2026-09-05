UPDATE `system_page_registry`
SET `page_label`='차량운행기록부',
    `page_description`='회계관리 > 장부관리 > 차량운행기록부',
    `breadcrumb`='회계관리 > 장부관리 > 차량운행기록부',
    `default_route_url`='/ledger/book/vehicle-log',
    `source_description`='회계관리 > 장부관리 > 차량운행기록부',
    `updated_at`=CURRENT_TIMESTAMP
WHERE `page_key`='ledger.book.vehicle_log';

UPDATE `system_menu_registry`
SET `menu_label`='차량운행기록부',
    `updated_at`=CURRENT_TIMESTAMP
WHERE `page_key`='ledger.book.vehicle_log';
