DELETE FROM `system_codes` WHERE `code_group` IN ('LEAVE_REQUEST_UNIT','LEAVE_REQUEST_STATUS');
DROP TABLE IF EXISTS `institution_leave_audits`;
DROP TABLE IF EXISTS `institution_leave_ledger_entries`;
DROP TABLE IF EXISTS `institution_leave_usages`;
DROP TABLE IF EXISTS `institution_leave_request_items`;
DROP TABLE IF EXISTS `institution_leave_requests`;
DROP TABLE IF EXISTS `institution_leave_grants`;
DROP TABLE IF EXISTS `institution_leave_types`;
DROP TABLE IF EXISTS `institution_employment_contracts_break_schedules`;
