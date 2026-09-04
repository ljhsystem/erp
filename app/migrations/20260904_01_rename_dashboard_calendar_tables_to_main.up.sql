RENAME TABLE
    `dashboard_calendar_list` TO `main_calendar_list`,
    `dashboard_calendar_events` TO `main_calendar_events`,
    `dashboard_calendar_tasks` TO `main_calendar_tasks`,
    `dashboard_calendar_visibility` TO `main_calendar_visibility`,
    `dashboard_calendar_sync_state` TO `main_calendar_sync_state`;

ALTER TABLE `main_calendar_list`
    COMMENT = '시놀로지 캘린더 목록과 ERP 관리색상';

ALTER TABLE `main_calendar_events`
    COMMENT = '시놀로지 일정 원본과 ERP 확장정보';

ALTER TABLE `main_calendar_tasks`
    COMMENT = '시놀로지 할 일 원본과 ERP 확장정보';

ALTER TABLE `main_calendar_visibility`
    MODIFY COLUMN `calendar_id` varchar(100) NOT NULL COMMENT 'FK → main_calendar_list.id (캘린더 실체 식별자)',
    COMMENT = '사용자별 캘린더 표시 설정';

ALTER TABLE `main_calendar_sync_state`
    COMMENT = '캘린더 동기화 실행 상태';
