RENAME TABLE
  `institution_personnel_action_targets` TO `institution_personnel_actions_targets`,
  `institution_personnel_action_changes` TO `institution_personnel_actions_changes`,
  `institution_employment_pay_components` TO `institution_employment_contracts_pay_components`,
  `institution_employment_contract_components` TO `institution_employment_contracts_components`,
  `institution_employment_contract_weekly_schedules` TO `institution_employment_contracts_weekly_schedules`,
  `institution_employment_contract_work_schedule_policies` TO `institution_employment_contracts_work_schedule_policies`,
  `user_jobs` TO `institution_job_assignments_jobs`,
  `user_employee_employment_status_histories` TO `institution_job_assignments_employment_status_histories`,
  `user_employee_department_assignments` TO `institution_job_assignments_department_histories`,
  `user_employee_position_assignments` TO `institution_job_assignments_position_histories`,
  `user_employee_job_assignments` TO `institution_job_assignments_job_histories`,
  `user_employee_project_assignments` TO `institution_job_assignments_project_histories`,
  `user_employee_workplace_assignments` TO `institution_job_assignments_workplace_histories`,
  `user_employee_leave_periods` TO `institution_job_assignments_leave_periods`,
  `user_employee_assignment_audits` TO `institution_job_assignments_audits`,
  `user_employee_attendance_clock_events` TO `institution_attendance_clock_events`,
  `user_employee_attendance_daily_records` TO `institution_attendance_daily_records`,
  `user_employee_attendance_work_segments` TO `institution_attendance_work_segments`,
  `user_employee_attendance_daily_exceptions` TO `institution_attendance_daily_exceptions`,
  `user_employee_attendance_monthly_closures` TO `institution_attendance_monthly_closures`,
  `user_employee_attendance_monthly_closure_histories` TO `institution_attendance_monthly_closure_histories`,
  `user_employee_attendance_audits` TO `institution_attendance_audits`;

ALTER TABLE `institution_employment_contracts_pay_components`
  COMMENT='근로계약관리 페이지 급여항목 기준정보 마스터';
ALTER TABLE `institution_employment_contracts_components`
  COMMENT='특정 근로계약의 급여·수당·공제 금액 및 정책 스냅샷';
ALTER TABLE `institution_employment_contracts_weekly_schedules`
  COMMENT='근로계약별 주간 반복 소정근로 일정';
ALTER TABLE `institution_employment_contracts_work_schedule_policies`
  COMMENT='근로계약별 비고정 근로형태 정책';
ALTER TABLE `institution_personnel_actions_targets`
  COMMENT='인사발령관리 대상 직원 및 대상별 적용 결과';
ALTER TABLE `institution_personnel_actions_changes`
  COMMENT='인사발령관리 대상별 변경 명령 행';
