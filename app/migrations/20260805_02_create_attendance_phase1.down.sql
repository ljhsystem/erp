ALTER TABLE `user_employee_attendance_monthly_closures` DROP FOREIGN KEY `fk_attendance_closure_current_history`;
DROP TABLE `user_employee_attendance_audits`;
DROP TABLE `user_employee_attendance_monthly_closure_histories`;
DROP TABLE `user_employee_attendance_monthly_closures`;
DROP TABLE `user_employee_attendance_daily_exceptions`;
DROP TABLE `user_employee_attendance_work_segments`;
DROP TABLE `user_employee_attendance_daily_records`;
DROP TABLE `user_employee_attendance_clock_events`;
DELETE FROM `system_codes` WHERE `code_group` IN ('ATTENDANCE_CLOCK_EVENT_TYPE','ATTENDANCE_SOURCE_TYPE','ATTENDANCE_PROCESS_STATUS','ATTENDANCE_EXCEPTION_TYPE','ATTENDANCE_SEGMENT_TYPE','ATTENDANCE_CLOSE_STATUS','ATTENDANCE_AUDIT_ACTION');
