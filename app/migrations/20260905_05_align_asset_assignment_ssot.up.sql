ALTER TABLE ledger_assets
  MODIFY work_team_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '자산 관리 팀',
  MODIFY employee_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '자산 책임 직원';

ALTER TABLE ledger_asset_assignments
  MODIFY work_team_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '이동 후 팀',
  MODIFY employee_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '이동 후 책임 직원';
