RENAME TABLE
    `main_calendar_list` TO `dashboard_calendar_list`,
    `main_calendar_events` TO `dashboard_calendar_events`,
    `main_calendar_tasks` TO `dashboard_calendar_tasks`,
    `main_calendar_visibility` TO `dashboard_calendar_visibility`,
    `main_calendar_sync_state` TO `dashboard_calendar_sync_state`;

ALTER TABLE `dashboard_calendar_list`
    COMMENT = 'Dashboard Calendar List (Synology raw + ERP admin color)';

ALTER TABLE `dashboard_calendar_events`
    COMMENT = 'Synology Calendar Event (RAW + ERP Shadow Columns)';

ALTER TABLE `dashboard_calendar_tasks`
    COMMENT = '';

ALTER TABLE `dashboard_calendar_visibility`
    MODIFY COLUMN `calendar_id` varchar(100) NOT NULL COMMENT 'FK → dashboard_calendar_list.id (컬렉션 실체 식별자)',
    COMMENT = 'Dashboard Calendar Visibility (Synology 로그인 계정 기준 가시성 제어 테이블)';

ALTER TABLE `dashboard_calendar_sync_state`
    COMMENT = '캘린더 동기화 실행 상태 관리 테이블';
