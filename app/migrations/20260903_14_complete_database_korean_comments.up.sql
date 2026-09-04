-- 운영 DB Comment SSOT Forward Migration
-- 캘린더 보호영역 3개 테이블 제외, DML/Trigger/Procedure/Function/Event 없음

ALTER TABLE `institution_employment_rules` COMMENT='취업규칙 문서', ALGORITHM=INSTANT, LOCK=NONE;
ALTER TABLE `institution_employment_rules_revisions` COMMENT='취업규칙 불변 개정본', ALGORITHM=INSTANT, LOCK=NONE;
ALTER TABLE `institution_employment_rules_audits` COMMENT='취업규칙 감사이력', ALGORITHM=INSTANT, LOCK=NONE;
ALTER TABLE `approval_personal_expenses`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '개인경비 신청 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `approval_personal_expense_items`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '개인경비 신청 품목 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `auth_logs`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '인증로그 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `auth_permissions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '권한 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `auth_roles`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '역할 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `auth_role_permissions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '역할별 권한 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `auth_users`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사용자 계정 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `auth_user_permissions`
  MODIFY COLUMN `permission_id` varchar(36) NOT NULL COMMENT '권한 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_incomes`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 귀속연월 Header 고유번호',
  MODIFY COLUMN `income_year_month` char(7) NOT NULL COMMENT '귀속연월',
  MODIFY COLUMN `title` varchar(200) NOT NULL COMMENT '제목',
  MODIFY COLUMN `description` text DEFAULT NULL COMMENT '설명',
  MODIFY COLUMN `document_status` varchar(30) NOT NULL DEFAULT 'DRAFT' COMMENT '문서상태',
  MODIFY COLUMN `calculation_status` varchar(30) NOT NULL DEFAULT 'NOT_CALCULATED' COMMENT '계산상태',
  MODIFY COLUMN `approval_status` varchar(30) NOT NULL DEFAULT 'NOT_REQUESTED' COMMENT '결재상태',
  MODIFY COLUMN `payment_status` varchar(30) NOT NULL DEFAULT 'NOT_CREATED' COMMENT '지급거래 생성상태',
  MODIFY COLUMN `withholding_filing_status` varchar(30) NOT NULL DEFAULT 'NOT_FILED' COMMENT '원천징수 신고상태',
  MODIFY COLUMN `simplified_statement_status` varchar(30) NOT NULL DEFAULT 'NOT_SUBMITTED' COMMENT '간이지급명세서 상태',
  MODIFY COLUMN `current_calculation_revision_id` varchar(36) DEFAULT NULL COMMENT '현재 계산 개정본 고유번호',
  MODIFY COLUMN `current_approval_request_id` varchar(36) DEFAULT NULL COMMENT '현재 결재요청 고유번호',
  MODIFY COLUMN `sort_no` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '정렬순서',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  MODIFY COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  MODIFY COLUMN `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_artifact_links`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 승인 Evidence·Transaction 산출물 연결 고유번호',
  MODIFY COLUMN `closure_id` varchar(36) NOT NULL COMMENT '완료처리 고유번호',
  MODIFY COLUMN `business_income_id` varchar(36) NOT NULL COMMENT '사업소득 문서 고유번호',
  MODIFY COLUMN `business_income_item_id` varchar(36) NOT NULL COMMENT '사업소득 지급대상자 고유번호',
  MODIFY COLUMN `evidence_id` varchar(36) DEFAULT NULL COMMENT '증빙 고유번호',
  MODIFY COLUMN `transaction_id` varchar(36) DEFAULT NULL COMMENT '거래 고유번호',
  MODIFY COLUMN `generation_status` varchar(30) NOT NULL DEFAULT 'PROCESSING' COMMENT '산출물 생성상태',
  MODIFY COLUMN `result_hash` char(64) DEFAULT NULL COMMENT '처리결과 해시',
  MODIFY COLUMN `processed_by` varchar(100) NOT NULL COMMENT '처리자',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `completed_at` datetime DEFAULT NULL COMMENT '완료일시',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_calculation_lines`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 계산 Raw 원본 Line 고유번호',
  MODIFY COLUMN `calculation_revision_id` varchar(36) NOT NULL COMMENT '계산 개정본 고유번호',
  MODIFY COLUMN `business_income_item_id` varchar(36) NOT NULL COMMENT '사업소득 지급대상자 고유번호',
  MODIFY COLUMN `line_type` varchar(30) NOT NULL COMMENT '계산항목 유형',
  MODIFY COLUMN `line_code` varchar(50) NOT NULL COMMENT '계산항목 코드',
  MODIFY COLUMN `line_name` varchar(100) NOT NULL COMMENT '계산항목명',
  MODIFY COLUMN `calculation_base_amount` decimal(18,6) NOT NULL COMMENT '계산기준 금액',
  MODIFY COLUMN `applied_rate` decimal(18,10) DEFAULT NULL COMMENT '적용률',
  MODIFY COLUMN `amount_before_rounding` decimal(18,6) NOT NULL COMMENT '반올림 전 금액',
  MODIFY COLUMN `rounding_method` varchar(30) DEFAULT NULL COMMENT '반올림 방식',
  MODIFY COLUMN `rounding_unit` decimal(18,6) DEFAULT NULL COMMENT '반올림 단위',
  MODIFY COLUMN `calculated_amount` decimal(18,2) NOT NULL COMMENT '계산금액',
  MODIFY COLUMN `statutory_standard_revision_id` varchar(36) DEFAULT NULL COMMENT '법정기준 개정본 고유번호',
  MODIFY COLUMN `applicability_status` varchar(40) NOT NULL COMMENT '적용상태',
  MODIFY COLUMN `sort_no` int(10) unsigned NOT NULL COMMENT '정렬순서',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_calculation_revisions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 계산 Revision 고유번호',
  MODIFY COLUMN `business_income_id` varchar(36) NOT NULL COMMENT '사업소득 문서 고유번호',
  MODIFY COLUMN `revision_no` int(10) unsigned NOT NULL COMMENT '개정번호',
  MODIFY COLUMN `revision_status` varchar(30) NOT NULL DEFAULT 'DRAFT' COMMENT '개정상태',
  MODIFY COLUMN `calculation_date` date NOT NULL COMMENT '계산기준일',
  MODIFY COLUMN `policy_status` varchar(40) NOT NULL COMMENT '정책적용 상태',
  MODIFY COLUMN `source_hash` char(64) NOT NULL COMMENT '원본자료 해시',
  MODIFY COLUMN `calculated_at` datetime DEFAULT NULL COMMENT '계산일시',
  MODIFY COLUMN `calculated_by` varchar(100) DEFAULT NULL COMMENT '계산자',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_closures`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 최종승인 Closure 고유번호',
  MODIFY COLUMN `business_income_id` varchar(36) NOT NULL COMMENT '사업소득 문서 고유번호',
  MODIFY COLUMN `approval_request_id` varchar(36) NOT NULL COMMENT '결재요청 고유번호',
  MODIFY COLUMN `status` varchar(30) NOT NULL DEFAULT 'PROCESSING' COMMENT '처리상태',
  MODIFY COLUMN `processing_token` varchar(64) NOT NULL COMMENT '처리 추적 토큰',
  MODIFY COLUMN `started_at` datetime NOT NULL COMMENT '시작일시',
  MODIFY COLUMN `completed_at` datetime DEFAULT NULL COMMENT '완료일시',
  MODIFY COLUMN `failed_at` datetime DEFAULT NULL COMMENT '실패일시',
  MODIFY COLUMN `processed_by` varchar(100) NOT NULL COMMENT '처리자',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_commands`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 멱등 Command 고유번호',
  MODIFY COLUMN `business_income_id` varchar(36) NOT NULL COMMENT '사업소득 문서 고유번호',
  MODIFY COLUMN `command_type` varchar(40) NOT NULL COMMENT '명령유형',
  MODIFY COLUMN `request_key` varchar(100) NOT NULL COMMENT '멱등 요청키',
  MODIFY COLUMN `command_status` varchar(30) NOT NULL DEFAULT 'PROCESSING' COMMENT '명령상태',
  MODIFY COLUMN `result_reference_id` varchar(36) DEFAULT NULL COMMENT '처리결과 참조 고유번호',
  MODIFY COLUMN `requested_by` varchar(100) NOT NULL COMMENT '요청자',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `completed_at` datetime DEFAULT NULL COMMENT '완료일시',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_groups`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 동일 지급조건 Group 고유번호',
  MODIFY COLUMN `business_income_id` varchar(36) NOT NULL COMMENT '사업소득 문서 고유번호',
  MODIFY COLUMN `payment_date` date NOT NULL COMMENT '지급일',
  MODIFY COLUMN `business_unit` varchar(50) NOT NULL COMMENT '사업구분',
  MODIFY COLUMN `project_id` varchar(36) DEFAULT NULL COMMENT '프로젝트 고유번호',
  MODIFY COLUMN `work_team_id` varchar(36) DEFAULT NULL COMMENT '업무팀 고유번호',
  MODIFY COLUMN `group_description` varchar(500) DEFAULT NULL COMMENT '그룹 설명',
  MODIFY COLUMN `sort_no` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '정렬순서',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  MODIFY COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  MODIFY COLUMN `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_items`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '사업소득 소득자별 개별 용역 지급 Item 고유번호',
  MODIFY COLUMN `group_id` varchar(36) NOT NULL COMMENT '사업소득 그룹 고유번호',
  MODIFY COLUMN `client_id` varchar(36) NOT NULL COMMENT '거래처 고유번호',
  MODIFY COLUMN `client_tax_profile_id` varchar(36) NOT NULL COMMENT '거래처 세무프로필 고유번호',
  MODIFY COLUMN `service_type_code` varchar(50) NOT NULL COMMENT '용역유형 코드',
  MODIFY COLUMN `service_description` varchar(1000) DEFAULT NULL COMMENT '용역내용',
  MODIFY COLUMN `gross_payment_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '총지급액',
  MODIFY COLUMN `income_tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '사업소득세액',
  MODIFY COLUMN `local_income_tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '지방소득세액',
  MODIFY COLUMN `other_deduction_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '기타공제액',
  MODIFY COLUMN `total_deduction_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '총공제액',
  MODIFY COLUMN `net_payment_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '최종지급액',
  MODIFY COLUMN `sort_no` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '정렬순서',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  MODIFY COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  MODIFY COLUMN `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_incomes`
  MODIFY COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  MODIFY COLUMN `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_accounting_links`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 Group×근로자 Evidence·지급거래 연결 고유번호',
  MODIFY COLUMN `closure_id` varchar(36) NOT NULL COMMENT '완료처리 고유번호',
  MODIFY COLUMN `daily_employment_income_id` varchar(36) NOT NULL COMMENT '일용근로소득 문서 고유번호',
  MODIFY COLUMN `daily_employment_income_group_id` varchar(36) NOT NULL COMMENT '일용근로소득 그룹 고유번호',
  MODIFY COLUMN `daily_employment_income_item_id` varchar(36) NOT NULL COMMENT '일용근로소득 근로자 고유번호',
  MODIFY COLUMN `worker_client_id` varchar(36) NOT NULL COMMENT '근로자 거래처 고유번호',
  MODIFY COLUMN `artifact_role` varchar(30) NOT NULL COMMENT '연결 산출물 역할',
  MODIFY COLUMN `business_key_hash` char(64) NOT NULL COMMENT '연결 업무키 해시',
  MODIFY COLUMN `payload_hash` char(64) NOT NULL COMMENT '연결 요청내용 해시',
  MODIFY COLUMN `evidence_id` varchar(36) NOT NULL COMMENT '증빙 고유번호',
  MODIFY COLUMN `transaction_id` varchar(36) DEFAULT NULL COMMENT '근로자 지급거래 고유번호',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_allocations`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '기관 확정금액의 Group·Item·Workday 결정적 배부원장 고유번호',
  MODIFY COLUMN `calculation_result_id` varchar(36) NOT NULL COMMENT '계산결과 고유번호',
  MODIFY COLUMN `allocation_scope_code` varchar(20) NOT NULL COMMENT '배분범위 코드',
  MODIFY COLUMN `daily_employment_income_group_id` varchar(36) NOT NULL COMMENT '일용근로소득 그룹 고유번호',
  MODIFY COLUMN `daily_employment_income_item_id` varchar(36) NOT NULL COMMENT '일용근로소득 근로자 고유번호',
  MODIFY COLUMN `daily_employment_income_workday_id` varchar(36) DEFAULT NULL COMMENT '일용근로일 고유번호',
  MODIFY COLUMN `allocation_basis_amount` decimal(18,2) NOT NULL COMMENT '배분기준 금액',
  MODIFY COLUMN `allocation_numerator` decimal(24,6) NOT NULL COMMENT '배분분자',
  MODIFY COLUMN `allocation_denominator` decimal(24,6) NOT NULL COMMENT '배분분모',
  MODIFY COLUMN `allocated_employee_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '배분 근로자부담액',
  MODIFY COLUMN `allocated_employer_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '배분 사용자부담액',
  MODIFY COLUMN `residual_amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT '배분 잔액',
  MODIFY COLUMN `residual_applied` tinyint(1) NOT NULL DEFAULT 0 COMMENT '잔액 반영 여부',
  MODIFY COLUMN `decision_rank` int(10) unsigned NOT NULL COMMENT '판정 우선순위',
  MODIFY COLUMN `allocation_policy_version` varchar(100) NOT NULL COMMENT '배분정책 버전',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_calculation_results`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 세금·보험 공식 계산결과 고유번호',
  MODIFY COLUMN `calculation_revision_id` varchar(36) NOT NULL COMMENT '계산 개정본 고유번호',
  MODIFY COLUMN `result_type_code` varchar(40) NOT NULL COMMENT '결과유형 코드',
  MODIFY COLUMN `worker_client_id` varchar(36) NOT NULL COMMENT '근로자 거래처 고유번호',
  MODIFY COLUMN `social_insurance_workplace_id` varchar(36) DEFAULT NULL COMMENT '사회보험 사업장 고유번호',
  MODIFY COLUMN `work_date` date DEFAULT NULL COMMENT '근로일',
  MODIFY COLUMN `application_from` date NOT NULL COMMENT '적용시작일',
  MODIFY COLUMN `application_to` date NOT NULL COMMENT '적용종료일',
  MODIFY COLUMN `payment_date` date NOT NULL COMMENT '지급일',
  MODIFY COLUMN `payment_sequence` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '지급순번',
  MODIFY COLUMN `calculation_basis_amount` decimal(18,2) DEFAULT NULL COMMENT '계산기초 금액',
  MODIFY COLUMN `automatic_employee_amount` decimal(18,2) DEFAULT NULL COMMENT '자동계산 근로자부담액',
  MODIFY COLUMN `automatic_employer_amount` decimal(18,2) DEFAULT NULL COMMENT '자동계산 사용자부담액',
  MODIFY COLUMN `confirmed_employee_amount` decimal(18,2) DEFAULT NULL COMMENT '확정 근로자부담액',
  MODIFY COLUMN `confirmed_employer_amount` decimal(18,2) DEFAULT NULL COMMENT '확정 사용자부담액',
  MODIFY COLUMN `statutory_standard_id` char(36) DEFAULT NULL COMMENT '법정기준 고유번호',
  MODIFY COLUMN `status_code` varchar(20) NOT NULL DEFAULT 'CALCULATED' COMMENT '상태코드',
  MODIFY COLUMN `exception_reason` varchar(1000) DEFAULT NULL COMMENT '예외사유',
  MODIFY COLUMN `calculation_basis_snapshot` longtext NOT NULL COMMENT '계산기초 스냅샷',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_calculation_revisions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 기관계산 불변 Revision 고유번호',
  MODIFY COLUMN `daily_employment_income_id` varchar(36) NOT NULL COMMENT '일용근로소득 문서 고유번호',
  MODIFY COLUMN `revision_no` int(10) unsigned NOT NULL COMMENT '개정번호',
  MODIFY COLUMN `calculation_policy_version` varchar(100) NOT NULL COMMENT '계산정책 버전',
  MODIFY COLUMN `source_hash` char(64) NOT NULL COMMENT '원본자료 해시',
  MODIFY COLUMN `status_code` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT '상태코드',
  MODIFY COLUMN `supersedes_revision_id` varchar(36) DEFAULT NULL COMMENT '대체대상 개정본 고유번호',
  MODIFY COLUMN `calculated_by` varchar(100) DEFAULT NULL COMMENT '계산자',
  MODIFY COLUMN `calculated_at` datetime DEFAULT NULL COMMENT '계산일시',
  MODIFY COLUMN `confirmed_by` varchar(100) DEFAULT NULL COMMENT '확정자',
  MODIFY COLUMN `confirmed_at` datetime DEFAULT NULL COMMENT '확정일시',
  MODIFY COLUMN `failed_at` datetime DEFAULT NULL COMMENT '실패일시',
  MODIFY COLUMN `failure_code` varchar(100) DEFAULT NULL COMMENT '실패코드',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_closures`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 최종승인 후속처리 멱등 원장 고유번호',
  MODIFY COLUMN `daily_employment_income_id` varchar(36) NOT NULL COMMENT '일용근로소득 문서 고유번호',
  MODIFY COLUMN `approval_request_id` varchar(36) NOT NULL COMMENT '결재요청 고유번호',
  MODIFY COLUMN `calculation_revision_id` varchar(36) NOT NULL COMMENT '계산 개정본 고유번호',
  MODIFY COLUMN `source_hash` char(64) NOT NULL COMMENT '원본자료 해시',
  MODIFY COLUMN `status_code` varchar(20) NOT NULL DEFAULT 'PROCESSING' COMMENT '상태코드',
  MODIFY COLUMN `payload_hash` char(64) NOT NULL COMMENT '요청내용 해시',
  MODIFY COLUMN `completed_at` datetime DEFAULT NULL COMMENT '완료일시',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_commands`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 저장명령 멱등 원장 고유번호',
  MODIFY COLUMN `command_type` varchar(30) NOT NULL COMMENT '명령유형',
  MODIFY COLUMN `command_status` varchar(20) NOT NULL COMMENT '명령상태',
  MODIFY COLUMN `result_version` int(10) unsigned DEFAULT NULL COMMENT '결과버전',
  MODIFY COLUMN `result_reference_id` varchar(191) DEFAULT NULL COMMENT '처리결과 참조 고유번호',
  MODIFY COLUMN `processed_by` varchar(100) NOT NULL COMMENT '처리자',
  MODIFY COLUMN `started_at` datetime NOT NULL COMMENT '시작일시',
  MODIFY COLUMN `completed_at` datetime DEFAULT NULL COMMENT '완료일시',
  MODIFY COLUMN `failed_at` datetime DEFAULT NULL COMMENT '실패일시',
  MODIFY COLUMN `error_code` varchar(100) DEFAULT NULL COMMENT '오류코드',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_groups`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 근무그룹 SSOT 고유번호',
  MODIFY COLUMN `daily_employment_income_id` varchar(36) NOT NULL COMMENT '일용근로소득 문서 고유번호',
  MODIFY COLUMN `sort_no` int(11) NOT NULL DEFAULT 0 COMMENT '정렬순서',
  MODIFY COLUMN `business_unit` varchar(30) NOT NULL COMMENT '사업구분',
  MODIFY COLUMN `project_id` varchar(36) DEFAULT NULL COMMENT '프로젝트 고유번호',
  MODIFY COLUMN `work_team_id` varchar(36) DEFAULT NULL COMMENT '업무팀 고유번호',
  MODIFY COLUMN `work_description` varchar(500) NOT NULL COMMENT '근로내용',
  MODIFY COLUMN `employment_insurance_application_status_code` varchar(30) DEFAULT NULL COMMENT '고용보험 적용상태 코드',
  MODIFY COLUMN `employment_insurance_decision_reason` varchar(500) DEFAULT NULL COMMENT '고용보험 판정사유',
  MODIFY COLUMN `employment_insurance_decision_source_code_id` varchar(36) DEFAULT NULL COMMENT '고용보험 판정근거 코드 고유번호',
  MODIFY COLUMN `industrial_accident_application_status_code` varchar(30) DEFAULT NULL COMMENT '산재보험 적용상태 코드',
  MODIFY COLUMN `industrial_accident_decision_reason` varchar(500) DEFAULT NULL COMMENT '산재보험 판정사유',
  MODIFY COLUMN `industrial_accident_decision_source_code_id` varchar(36) DEFAULT NULL COMMENT '산재보험 판정근거 코드 고유번호',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_items`
  MODIFY COLUMN `daily_employment_income_group_id` varchar(36) NOT NULL COMMENT '일용근로소득 그룹 고유번호',
  MODIFY COLUMN `work_type_code` varchar(50) NOT NULL COMMENT '근로유형 코드',
  MODIFY COLUMN `work_description` varchar(500) NOT NULL COMMENT '근로내용',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_lines`
  MODIFY COLUMN `workday_scope_key` varchar(36) NOT NULL COMMENT '근로일 범위키',
  MODIFY COLUMN `revision_scope_key` varchar(36) NOT NULL COMMENT '개정본 범위키',
  MODIFY COLUMN `period_scope_key` varchar(32) NOT NULL COMMENT '기간 범위키',
  MODIFY COLUMN `taxability_code` varchar(20) DEFAULT NULL COMMENT '과세구분 코드',
  MODIFY COLUMN `calculated_amount` decimal(18,2) DEFAULT NULL COMMENT '계산금액',
  MODIFY COLUMN `non_taxable_revision_id` varchar(36) DEFAULT NULL COMMENT '비과세 개정본 고유번호',
  MODIFY COLUMN `effective_from` date DEFAULT NULL COMMENT '효력시작일',
  MODIFY COLUMN `effective_to` date DEFAULT NULL COMMENT '효력종료일',
  MODIFY COLUMN `final_amount` decimal(18,2) DEFAULT NULL COMMENT '최종금액',
  MODIFY COLUMN `adjustment_reason` varchar(500) DEFAULT NULL COMMENT '조정사유',
  MODIFY COLUMN `statutory_calculation_source_code_id` varchar(36) DEFAULT NULL COMMENT '법정계산 근거코드 고유번호',
  MODIFY COLUMN `actual_application_source_code_id` varchar(36) DEFAULT NULL COMMENT '실제적용 근거코드 고유번호',
  MODIFY COLUMN `processed_at` datetime DEFAULT NULL COMMENT '처리일시',
  MODIFY COLUMN `processed_by` varchar(100) DEFAULT NULL COMMENT '처리자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_line_backfill_audits`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 Line 호환 백필 감사자료 고유번호',
  MODIFY COLUMN `migration_id` varchar(100) NOT NULL COMMENT '마이그레이션 고유번호',
  MODIFY COLUMN `daily_employment_income_line_id` varchar(36) NOT NULL COMMENT '일용근로소득 계산항목 고유번호',
  MODIFY COLUMN `previous_snapshot` longtext NOT NULL COMMENT '변경 전 스냅샷',
  MODIFY COLUMN `new_snapshot` longtext NOT NULL COMMENT '변경 후 스냅샷',
  MODIFY COLUMN `decision_rule_code` varchar(150) NOT NULL COMMENT '판정규칙 코드',
  MODIFY COLUMN `decision_basis_id` varchar(191) NOT NULL COMMENT '판정근거 고유번호',
  MODIFY COLUMN `payload_hash` char(64) NOT NULL COMMENT '요청내용 해시',
  MODIFY COLUMN `verification_status_code` varchar(20) NOT NULL COMMENT '검증상태 코드',
  MODIFY COLUMN `executed_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '실행일시',
  MODIFY COLUMN `executed_by` varchar(100) NOT NULL COMMENT '실행자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_non_taxable_revisions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '일용근로소득 비과세 판단 Revision 고유번호',
  MODIFY COLUMN `daily_employment_income_id` varchar(36) NOT NULL COMMENT '일용근로소득 문서 고유번호',
  MODIFY COLUMN `daily_employment_income_item_id` varchar(36) NOT NULL COMMENT '일용근로소득 근로자 고유번호',
  MODIFY COLUMN `daily_employment_income_workday_id` varchar(36) DEFAULT NULL COMMENT '일용근로일 고유번호',
  MODIFY COLUMN `revision_no` int(10) unsigned NOT NULL COMMENT '개정번호',
  MODIFY COLUMN `non_taxable_item_code` varchar(50) NOT NULL COMMENT '비과세항목 코드',
  MODIFY COLUMN `applied_amount` decimal(18,2) NOT NULL COMMENT '적용금액',
  MODIFY COLUMN `effective_from` date DEFAULT NULL COMMENT '효력시작일',
  MODIFY COLUMN `effective_to` date DEFAULT NULL COMMENT '효력종료일',
  MODIFY COLUMN `application_reason` varchar(1000) NOT NULL COMMENT '적용사유',
  MODIFY COLUMN `legal_basis` text NOT NULL COMMENT '법적근거',
  MODIFY COLUMN `calculation_details` text NOT NULL COMMENT '계산상세',
  MODIFY COLUMN `statutory_standard_id` char(36) NOT NULL COMMENT '법정기준 고유번호',
  MODIFY COLUMN `revision_status_code` varchar(20) NOT NULL DEFAULT 'DRAFT' COMMENT '개정상태 코드',
  MODIFY COLUMN `confirmed_by` varchar(100) DEFAULT NULL COMMENT '확정자',
  MODIFY COLUMN `confirmed_at` datetime DEFAULT NULL COMMENT '확정일시',
  MODIFY COLUMN `corrects_revision_id` varchar(36) DEFAULT NULL COMMENT '정정대상 개정본 고유번호',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_daily_employment_income_workdays`
  MODIFY COLUMN `work_team_assignment_id` varchar(36) DEFAULT NULL COMMENT '업무팀 배정 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_employment_contracts`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '근로계약관리 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_employment_contracts_components`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '특정 근로계약의 급여·수당·공제 금액 및 정책 스냅샷 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_employment_contracts_pay_components`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '근로계약관리 페이지 급여항목 기준정보 마스터 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_employment_contracts_weekly_schedules`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '근로계약별 주간 반복 소정근로 일정 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_employment_contracts_work_schedule_policies`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '근로계약별 비고정 근로형태 정책 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_regular_employment_incomes`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '상용근로소득 관리 헤더 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_regular_employment_income_items`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '상용근로소득 직원별 상세 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_regular_employment_income_line_items`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '상용근로소득 동적 지급·공제·회사부담 항목 고유번호',
  MODIFY COLUMN `item_type_code` varchar(30) NOT NULL COMMENT '항목유형 코드',
  MODIFY COLUMN `application_status_code` varchar(30) DEFAULT NULL COMMENT '적용상태 코드',
  MODIFY COLUMN `rounding_method_code` varchar(30) DEFAULT NULL COMMENT '반올림 방식 코드',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_social_insurance_assessment_bases`
  MODIFY COLUMN `confirmation_status_code` varchar(30) NOT NULL COMMENT '확정상태 코드',
  MODIFY COLUMN `source_type_code` varchar(30) NOT NULL COMMENT '원본유형 코드',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_social_insurance_coverages`
  MODIFY COLUMN `coverage_status_code` varchar(30) NOT NULL COMMENT '적용상태 코드',
  MODIFY COLUMN `source_type_code` varchar(30) NOT NULL COMMENT '원본유형 코드',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_workplace_size_periods`
  MODIFY COLUMN `evidence_type_code` varchar(30) NOT NULL COMMENT '증빙유형 코드',
  MODIFY COLUMN `confirmation_status_code` varchar(30) NOT NULL COMMENT '확정상태 코드',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_accounts`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT 'ERP 회계 계정과목 마스터 테이블 (계층구조 기반) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_accounts_sub`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT 'ERP 회계 계정별 보조계정 정책 테이블 (입력 규칙 정의: 대상타입 및 필수여부) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_bank_transaction`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 입출금(은행)(BANK_TRANSACTION) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_business_income`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '최종승인 사업소득 Evidence  고유번호',
  MODIFY COLUMN `sort_no` int(10) unsigned NOT NULL COMMENT '정렬순서',
  MODIFY COLUMN `source_type` varchar(40) NOT NULL COMMENT '원본유형',
  MODIFY COLUMN `import_type` varchar(50) NOT NULL COMMENT '가져오기 유형',
  MODIFY COLUMN `business_unit` varchar(50) NOT NULL COMMENT '사업구분',
  MODIFY COLUMN `transaction_direction` varchar(10) NOT NULL COMMENT '거래방향',
  MODIFY COLUMN `operation_type` varchar(50) NOT NULL COMMENT '업무유형',
  MODIFY COLUMN `external_key` varchar(120) NOT NULL COMMENT '외부 식별키',
  MODIFY COLUMN `evidence_date` date NOT NULL COMMENT '증빙일자',
  MODIFY COLUMN `client_id` varchar(36) DEFAULT NULL COMMENT '거래처 고유번호',
  MODIFY COLUMN `employee_id` varchar(36) DEFAULT NULL COMMENT '직원 고유번호',
  MODIFY COLUMN `project_id` varchar(36) DEFAULT NULL COMMENT '프로젝트 고유번호',
  MODIFY COLUMN `bank_account_id` varchar(36) DEFAULT NULL COMMENT '은행계좌 고유번호',
  MODIFY COLUMN `card_id` varchar(36) DEFAULT NULL COMMENT '카드 고유번호',
  MODIFY COLUMN `work_team_id` varchar(36) DEFAULT NULL COMMENT '업무팀 고유번호',
  MODIFY COLUMN `raw_client_name` varchar(255) DEFAULT NULL COMMENT '원본 거래처명',
  MODIFY COLUMN `evidence_status` varchar(30) NOT NULL COMMENT '증빙상태',
  MODIFY COLUMN `raw_income_year_month` char(7) NOT NULL COMMENT '원본 귀속연월',
  MODIFY COLUMN `raw_payment_date` date NOT NULL COMMENT '원본 지급일',
  MODIFY COLUMN `raw_recipient_name` varchar(200) NOT NULL COMMENT '원본 소득자명',
  MODIFY COLUMN `raw_service_type_code` varchar(50) NOT NULL COMMENT '원본 용역유형 코드',
  MODIFY COLUMN `raw_service_description` varchar(1000) DEFAULT NULL COMMENT '원본 용역내용',
  MODIFY COLUMN `raw_business_unit` varchar(50) NOT NULL COMMENT '원본 사업구분',
  MODIFY COLUMN `raw_project_id` varchar(36) DEFAULT NULL COMMENT '원본 프로젝트 고유번호',
  MODIFY COLUMN `raw_work_team_id` varchar(36) DEFAULT NULL COMMENT '원본 업무팀 고유번호',
  MODIFY COLUMN `raw_gross_payment_amount` decimal(18,2) NOT NULL COMMENT '원본 총지급액',
  MODIFY COLUMN `raw_income_tax_amount` decimal(18,2) NOT NULL COMMENT '원본 사업소득세액',
  MODIFY COLUMN `raw_local_income_tax_amount` decimal(18,2) NOT NULL COMMENT '원본 지방소득세액',
  MODIFY COLUMN `raw_other_deduction_amount` decimal(18,2) NOT NULL COMMENT '원본 기타공제액',
  MODIFY COLUMN `raw_total_deduction_amount` decimal(18,2) NOT NULL COMMENT '원본 총공제액',
  MODIFY COLUMN `raw_net_payment_amount` decimal(18,2) NOT NULL COMMENT '원본 최종지급액',
  MODIFY COLUMN `income_date` date DEFAULT NULL COMMENT '소득발생일',
  MODIFY COLUMN `provider_name` varchar(120) DEFAULT NULL COMMENT '공급자명',
  MODIFY COLUMN `provider_reg_no` varchar(40) DEFAULT NULL COMMENT '공급자 등록번호',
  MODIFY COLUMN `supply_amount` decimal(18,2) NOT NULL COMMENT '공급가액',
  MODIFY COLUMN `vat_amount` decimal(18,2) NOT NULL COMMENT '부가가치세액',
  MODIFY COLUMN `service_amount` decimal(18,2) DEFAULT NULL COMMENT '봉사료',
  MODIFY COLUMN `total_amount` decimal(18,2) NOT NULL COMMENT '증빙 총금액',
  MODIFY COLUMN `memo` text DEFAULT NULL COMMENT '비고',
  MODIFY COLUMN `source_business_income_id` varchar(36) NOT NULL COMMENT '원본 사업소득 문서 고유번호',
  MODIFY COLUMN `business_income_group_id` varchar(36) NOT NULL COMMENT '사업소득 그룹 고유번호',
  MODIFY COLUMN `business_income_item_id` varchar(36) NOT NULL COMMENT '사업소득 지급대상자 고유번호',
  MODIFY COLUMN `approval_request_id` varchar(36) NOT NULL COMMENT '결재요청 고유번호',
  MODIFY COLUMN `calculation_revision_id` varchar(36) NOT NULL COMMENT '계산 개정본 고유번호',
  MODIFY COLUMN `approved_at` datetime NOT NULL COMMENT '승인일시',
  MODIFY COLUMN `approved_by` varchar(100) NOT NULL COMMENT '승인자',
  MODIFY COLUMN `snapshot_version` int(10) unsigned NOT NULL COMMENT '스냅샷 버전',
  MODIFY COLUMN `source_hash` char(64) NOT NULL COMMENT '원본자료 해시',
  MODIFY COLUMN `business_key_hash` char(64) NOT NULL COMMENT '업무키 해시',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  MODIFY COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  MODIFY COLUMN `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_business_income_raw_lines`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '승인 사업소득 계산 Raw Line 고유번호',
  MODIFY COLUMN `evidence_id` varchar(36) NOT NULL COMMENT '증빙 고유번호',
  MODIFY COLUMN `source_calculation_line_id` varchar(36) NOT NULL COMMENT '원본 계산항목 고유번호',
  MODIFY COLUMN `calculation_revision_id` varchar(36) NOT NULL COMMENT '계산 개정본 고유번호',
  MODIFY COLUMN `raw_line_type` varchar(30) NOT NULL COMMENT '원본 계산항목 유형',
  MODIFY COLUMN `raw_line_code` varchar(50) NOT NULL COMMENT '원본 계산항목 코드',
  MODIFY COLUMN `raw_line_name` varchar(100) NOT NULL COMMENT '원본 계산항목명',
  MODIFY COLUMN `raw_applicability_status` varchar(40) NOT NULL COMMENT '원본 적용상태',
  MODIFY COLUMN `raw_calculation_base_amount` decimal(18,6) NOT NULL COMMENT '원본 계산기준 금액',
  MODIFY COLUMN `raw_applied_rate` decimal(18,10) DEFAULT NULL COMMENT '원본 적용률',
  MODIFY COLUMN `raw_amount_before_rounding` decimal(18,6) NOT NULL COMMENT '원본 반올림 전 금액',
  MODIFY COLUMN `raw_rounding_method` varchar(30) DEFAULT NULL COMMENT '원본 반올림 방식',
  MODIFY COLUMN `raw_rounding_unit` decimal(18,6) DEFAULT NULL COMMENT '원본 반올림 단위',
  MODIFY COLUMN `raw_calculated_amount` decimal(18,2) NOT NULL COMMENT '원본 계산금액',
  MODIFY COLUMN `raw_statutory_standard_revision_id` varchar(36) DEFAULT NULL COMMENT '원본 법정기준 개정본 고유번호',
  MODIFY COLUMN `raw_sort_no` int(10) unsigned NOT NULL COMMENT '원본 정렬순서',
  MODIFY COLUMN `source_hash` char(64) NOT NULL COMMENT '원본자료 해시',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_card_hometax`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 카드매입(홈택스)(CARD_HOMTAX) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_card_statement`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 카드매입(카드사)(CARD_STATEMENT) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_cash_receipt`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 현금영수증매입(홈택스)(CAXH_RECEIPT) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_daily_employment_income_lines`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '승인 당시 일용근로소득 계산 Line 불변 Projection 고유번호',
  MODIFY COLUMN `evidence_id` varchar(36) NOT NULL COMMENT '증빙 고유번호',
  MODIFY COLUMN `sort_no` int(11) NOT NULL COMMENT '정렬순서',
  MODIFY COLUMN `source_calculation_line_id` varchar(36) NOT NULL COMMENT '원본 계산항목 고유번호',
  MODIFY COLUMN `calculation_revision_id` varchar(36) NOT NULL COMMENT '계산 개정본 고유번호',
  MODIFY COLUMN `line_type_code` varchar(30) NOT NULL COMMENT '계산항목 유형코드',
  MODIFY COLUMN `line_code` varchar(50) NOT NULL COMMENT '계산항목 코드',
  MODIFY COLUMN `line_name_snapshot` varchar(100) NOT NULL COMMENT '계산항목명 스냅샷',
  MODIFY COLUMN `burden_subject_code` varchar(20) NOT NULL COMMENT '부담주체 코드',
  MODIFY COLUMN `application_status_code` varchar(30) DEFAULT NULL COMMENT '적용상태 코드',
  MODIFY COLUMN `taxability_code` varchar(20) DEFAULT NULL COMMENT '과세구분 코드',
  MODIFY COLUMN `raw_calculation_basis_amount` decimal(18,6) DEFAULT NULL COMMENT '원본 계산기초 금액',
  MODIFY COLUMN `raw_calculation_rate` decimal(18,10) DEFAULT NULL COMMENT '원본 계산률',
  MODIFY COLUMN `raw_calculation_before_rounding` decimal(24,10) DEFAULT NULL COMMENT '원본 반올림 전 계산금액',
  MODIFY COLUMN `raw_calculated_amount` decimal(18,2) DEFAULT NULL COMMENT '원본 계산금액',
  MODIFY COLUMN `raw_adjustment_amount` decimal(18,2) DEFAULT NULL COMMENT '원본 조정금액',
  MODIFY COLUMN `raw_final_amount` decimal(18,2) DEFAULT NULL COMMENT '원본 최종금액',
  MODIFY COLUMN `rounding_method_code` varchar(30) DEFAULT NULL COMMENT '반올림 방식 코드',
  MODIFY COLUMN `rounding_unit` decimal(18,6) DEFAULT NULL COMMENT '반올림 단위',
  MODIFY COLUMN `statutory_standard_id` char(36) DEFAULT NULL COMMENT '법정기준 고유번호',
  MODIFY COLUMN `coverage_id` varchar(36) DEFAULT NULL COMMENT '사회보험 적용 고유번호',
  MODIFY COLUMN `social_insurance_workplace_id` varchar(36) DEFAULT NULL COMMENT '사회보험 사업장 고유번호',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_employee_personal_expense`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 직원경비(개인)(EMPLOYEE_PERSONAL_EXPENSE) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_links`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '증빙 연결  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_metadata`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '증빙 메타데이터 SSOT, 사용처: 회계관리>자료관리>증빙정책 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_metadata_columns`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '증빙 메타데이터 컬럼매핑, 사용처: 회계관리>자료관리>증빙정책 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_salary_report`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 급여(신고)(SALARY_REPORT) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_tax_invoice`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 세금계산서매입매출(홈택스)(TAX_INVOICE) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_evidence_tax_invoice_manual`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '자료유형(IMPORT_TYPE): 세금계산서매입매출(수기)(TAX_INVOICE_MANUAL) 증빙  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_journal_client_account_patterns`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '거래처별 계정과목 추천 학습 패턴  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_journal_learning_events`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '자동분개 추천 학습 및 사용자 수정 이력 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_journal_recent_patterns`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '최근 사용 자동분개 패턴  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_journal_rules`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '거래 기반 자동분개 규칙  고유번호',
  MODIFY COLUMN `rule_status` varchar(20) NOT NULL DEFAULT 'INACTIVE' COMMENT '규칙상태',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_transactions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '회계 원본 거래 헤더  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_transaction_files`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '거래 증빙 파일  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_transaction_items`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '회계 원본 거래 품목 상세  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_transaction_projection_repairs`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '승인 파생 Transaction Projection 정정 불변 감사 고유번호',
  MODIFY COLUMN `request_key` varchar(191) NOT NULL COMMENT '멱등 요청키',
  MODIFY COLUMN `transaction_id` char(36) NOT NULL COMMENT '거래 고유번호',
  MODIFY COLUMN `evidence_id` char(36) DEFAULT NULL COMMENT '증빙 고유번호',
  MODIFY COLUMN `approval_request_id` char(36) DEFAULT NULL COMMENT '결재요청 고유번호',
  MODIFY COLUMN `source_revision_id` char(36) DEFAULT NULL COMMENT '원본 개정본 고유번호',
  MODIFY COLUMN `repair_type` varchar(50) NOT NULL COMMENT '복구유형',
  MODIFY COLUMN `reason_code` varchar(100) NOT NULL COMMENT '사유코드',
  MODIFY COLUMN `reason_text` varchar(500) NOT NULL COMMENT '사유내용',
  MODIFY COLUMN `source_hash` char(64) DEFAULT NULL COMMENT '원본자료 해시',
  MODIFY COLUMN `before_snapshot` longtext NOT NULL COMMENT '처리 전 스냅샷',
  MODIFY COLUMN `after_snapshot` longtext NOT NULL COMMENT '처리 후 스냅샷',
  MODIFY COLUMN `changed_fields_json` longtext NOT NULL COMMENT '변경필드 내역',
  MODIFY COLUMN `result_status` varchar(20) NOT NULL COMMENT '처리결과 상태',
  MODIFY COLUMN `repaired_by` varchar(100) DEFAULT NULL COMMENT '복구자',
  MODIFY COLUMN `repaired_at` datetime(6) NOT NULL COMMENT '복구일시',
  MODIFY COLUMN `created_at` datetime(6) NOT NULL COMMENT '등록일시',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_transaction_settlements`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '회계 원본 거래 품목 금액의 정산  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_vouchers`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '회계 전표 헤더 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_voucher_lines`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '회계 전표 분개 라인  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `ledger_voucher_line_refs`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '전표라인 보조계정 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_bank_accounts`
  MODIFY COLUMN `id` varchar(50) NOT NULL DEFAULT '' COMMENT '계좌 관리  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_cards`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '카드 관리  고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_clients`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '거래처 관리 테이블 (매입/매출/협력업체 등) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_client_tax_profiles`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '거래처 세무 적격성 유효기간 SSOT 고유번호',
  MODIFY COLUMN `client_id` varchar(36) NOT NULL COMMENT '거래처 고유번호',
  MODIFY COLUMN `effective_from` date NOT NULL COMMENT '효력시작일',
  MODIFY COLUMN `effective_to` date DEFAULT NULL COMMENT '효력종료일',
  MODIFY COLUMN `taxpayer_entity_type` varchar(50) NOT NULL COMMENT '납세자 실체유형',
  MODIFY COLUMN `residency_status` varchar(50) NOT NULL COMMENT '거주자 구분',
  MODIFY COLUMN `income_recipient_type` varchar(50) NOT NULL COMMENT '소득자 유형',
  MODIFY COLUMN `withholding_policy_code` varchar(50) NOT NULL COMMENT '원천징수정책 코드',
  MODIFY COLUMN `verification_status` varchar(30) NOT NULL COMMENT '검증상태',
  MODIFY COLUMN `verified_at` datetime DEFAULT NULL COMMENT '검증일시',
  MODIFY COLUMN `verified_by` varchar(100) DEFAULT NULL COMMENT '검증자',
  MODIFY COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  MODIFY COLUMN `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  MODIFY COLUMN `updated_by` varchar(100) NOT NULL COMMENT '수정자',
  MODIFY COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '삭제일시',
  MODIFY COLUMN `deleted_by` varchar(100) DEFAULT NULL COMMENT '삭제자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_codes`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '기준정보 코드 테이블 (공통 코드 관리: 거래유형, 결제조건, REF_TYPE 등) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_coverimage_assets`
  MODIFY COLUMN `id` varchar(50) NOT NULL DEFAULT '' COMMENT '홈페이지 메인(로그인 전) 커버 이미지 관리 테이블 (UUID + 순번 구조) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_projects`
  MODIFY COLUMN `id` varchar(36) NOT NULL DEFAULT '' COMMENT '프로젝트(현장/공사) 관리 테이블 (UUID + 순번 구조) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_statutory_standards`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '법정기준 적용기간 SSOT 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_statutory_standard_sources`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '법정기준 근거자료 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_statutory_standard_supersessions`
  MODIFY COLUMN `id` char(36) NOT NULL COMMENT '법정기준 Revision 정정·대체 선형 관계 고유번호',
  MODIFY COLUMN `created_at` datetime NOT NULL COMMENT '등록일시',
  MODIFY COLUMN `created_by` varchar(100) NOT NULL COMMENT '등록자',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `system_work_teams`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '작업팀 관리 테이블 (프로젝트/스케줄용 동적 팀) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `user_approval_templates`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '결재 템플릿 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `user_approval_template_steps`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '결재 템플릿 단계 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `user_departments`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '조직(부서) 관리 테이블 (UUID + 순번 구조, 자체 참조 계층형 구조) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `user_employees`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '직원 인사 / 프로필 관리 테이블 (UUID + 순번 구조, 부서/직위 FK 연동) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `user_positions`
  MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT '직위 / 직책 관리 테이블 (UUID + 순번 구조) 고유번호',
  ALGORITHM=INSTANT, LOCK=NONE;

ALTER TABLE `institution_business_income_items`
  MODIFY COLUMN `recipient_tax_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '소득자 세무정보 스냅샷' CHECK (json_valid(`recipient_tax_snapshot_json`)),
  ALGORITHM=COPY, LOCK=SHARED;
ALTER TABLE `institution_daily_employment_income_lines`
  MODIFY COLUMN `adjustment_amount` decimal(18,2) GENERATED ALWAYS AS (case when `calculated_amount` is null or `final_amount` is null then NULL else `final_amount` - `calculated_amount` end) STORED COMMENT '조정금액',
  ALGORITHM=COPY, LOCK=SHARED;

ALTER TABLE `ledger_evidence_business_income`
  MODIFY COLUMN `snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT '승인 원본 스냅샷' CHECK (json_valid(`snapshot_json`)),
  ALGORITHM=COPY, LOCK=SHARED;
