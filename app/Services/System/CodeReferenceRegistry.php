<?php
namespace App\Services\System;

final class CodeReferenceRegistry
{
    private const EVIDENCE_TABLES = [
        'ledger_evidence_bank_transaction', 'ledger_evidence_card_hometax',
        'ledger_evidence_card_statement', 'ledger_evidence_cash_receipt',
        'ledger_evidence_employee_personal_expense', 'ledger_evidence_salary_report',
        'ledger_evidence_tax_invoice', 'ledger_evidence_tax_invoice_manual',
    ];

    private const EVIDENCE_COLUMNS = [
        'BUSINESS_UNIT' => 'business_unit',
        'IMPORT_TYPE' => 'import_type',
        'OPERATION_TYPE' => 'operation_type',
        'SOURCE_TYPE' => 'source_type',
        'TRANSACTION_DIRECTION' => 'transaction_direction',
    ];

    private const GROUPS = [
        'AMOUNT_SIGN', 'ATTENDANCE_AUDIT_ACTION', 'ATTENDANCE_CLOCK_EVENT_TYPE',
        'ATTENDANCE_CLOSE_STATUS', 'ATTENDANCE_EXCEPTION_TYPE', 'ATTENDANCE_PROCESS_STATUS',
        'ATTENDANCE_SEGMENT_TYPE', 'ATTENDANCE_SOURCE_TYPE', 'BANK', 'BANK_ACCOUNT_TYPE',
        'BID_TYPE', 'BUSINESS_UNIT', 'CLIENT_CATEGORY', 'CLIENT_TYPE', 'CONSTRUCTION_TYPE',
        'CONTRACT_METHOD', 'CONTRACT_TYPE', 'COST_TYPE', 'CURRENCY',
        'EDUCATION_ATTENDANCE_STATUS', 'EDUCATION_COMPLETION_STATUS', 'EDUCATION_TYPE',
        'EMPLOYEE_ASSIGNMENT_AUDIT_ACTION', 'EMPLOYEE_ASSIGNMENT_SOURCE',
        'EMPLOYEE_ASSIGNMENT_STATUS', 'EMPLOYEE_LEAVE_TYPE', 'EMPLOYEE_WORKPLACE_TYPE',
        'EMPLOYMENT_CATEGORY', 'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON',
        'EMPLOYMENT_CONTRACT_PERIOD_TYPE', 'EMPLOYMENT_CONTRACT_STATUS',
        'EMPLOYMENT_CONTRACT_TYPE', 'EMPLOYMENT_RULE_STATUS', 'EMPLOYMENT_RULE_TYPE',
        'EMPLOYMENT_STATUS',
        'EMPLOYMENT_TERMINATION_REASON', 'EMPLOYMENT_WORKING_TIME_TYPE', 'IMPORT_TYPE',
        'LEAVE_REQUEST_STATUS', 'LEAVE_REQUEST_UNIT', 'OPERATION_TYPE', 'PAYMENT_TERM',
        'PAYMENT_TIMING',
        'PERSONAL_EXPENSE_CATEGORY', 'PERSONAL_EXPENSE_PAYMENT_METHOD',
        'PERSONAL_EXPENSE_RECEIPT_TYPE', 'PERSONNEL_ACTION_STATUS', 'PERSONNEL_ACTION_TYPE',
        'QUALIFICATION_STATUS', 'QUALIFICATION_TYPE', 'REF_TARGET', 'SALARY_TYPE',
        'SETTLEMENT_TYPE', 'SOURCE_TYPE', 'STATUTORY_ROUNDING_METHOD',
        'STATUTORY_STANDARD_TYPE', 'STATUTORY_POLICY_COMPONENT', 'STATUTORY_EMPLOYMENT_TYPE',
        'STATUTORY_WORK_SCOPE', 'STATUTORY_CONDITION_COMBINATION', 'STATUTORY_STANDARD_PERIOD_STATUS',
        'INSURANCE_ELIGIBILITY_DECISION', 'INSURANCE_ELIGIBILITY_RESULT',
        'INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE', 'INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY',
        'INSURANCE_ELIGIBILITY_MONTH_JUDGMENT', 'INSURANCE_ELIGIBILITY_INCOME_BASIS',
        'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE', 'INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD',
        'INSURANCE_ELIGIBILITY_TRANSITION_POLICY', 'INSURANCE_ELIGIBILITY_TRANSITION_STATUS',
        'TAX_TYPE', 'TRANSACTION_DIRECTION', 'UNIT',
        'WORK_LOCATION_TYPE', 'WORK_SCHEDULE_TYPE', 'WORK_TYPE',
    ];

    private const COLUMN_REFERENCES = [
        'ATTENDANCE_AUDIT_ACTION' => [['institution_attendance_audits', 'action_type_code', '근태 감사이력']],
        'ATTENDANCE_CLOCK_EVENT_TYPE' => [['institution_attendance_clock_events', 'event_type_code', '근태 출퇴근 이벤트']],
        'ATTENDANCE_CLOSE_STATUS' => [['institution_attendance_monthly_closures', 'close_status_code', '근태 월 마감']],
        'ATTENDANCE_EXCEPTION_TYPE' => [['institution_attendance_daily_exceptions', 'exception_type_code', '근태 예외']],
        'ATTENDANCE_PROCESS_STATUS' => [['institution_attendance_daily_records', 'process_status_code', '근태 일별 처리']],
        'ATTENDANCE_SEGMENT_TYPE' => [['institution_attendance_work_segments', 'segment_type_code', '근태 근무구간']],
        'ATTENDANCE_SOURCE_TYPE' => [
            ['institution_attendance_clock_events', 'source_type_code', '근태 출퇴근 입력출처'],
            ['institution_attendance_daily_exceptions', 'source_type_code', '근태 예외 입력출처'],
            ['institution_attendance_work_segments', 'source_type_code', '근태 근무구간 입력출처'],
        ],
        'BANK' => [
            ['system_bank_accounts', 'bank_name', '은행계좌', true], ['system_clients', 'bank_name', '거래처 계좌', true],
            ['user_employees', 'bank_name', '직원 계좌', true],
        ],
        'BANK_ACCOUNT_TYPE' => [['system_bank_accounts', 'account_type', '은행계좌 구분']],
        'BID_TYPE' => [['system_projects', 'bid_type', '프로젝트 입찰방법']],
        'BUSINESS_UNIT' => [
            ['ledger_transactions', 'business_unit', '거래'], ['ledger_journal_rules', 'business_unit', '분개규칙'],
            ['ledger_journal_learning_events', 'business_unit', '분개학습'],
        ],
        'CLIENT_CATEGORY' => [['system_clients', 'client_category', '거래처 분류']],
        'CLIENT_TYPE' => [
            ['system_clients', 'client_type', '거래처'], ['system_projects', 'client_type', '프로젝트'],
            ['ledger_journal_rules', 'client_type', '분개규칙'], ['ledger_journal_learning_events', 'client_type', '분개학습'],
        ],
        'CONSTRUCTION_TYPE' => [['system_projects', 'housing_type', '프로젝트 공사유형']],
        'CONTRACT_METHOD' => [['system_projects', 'contract_method', '프로젝트 계약방식']],
        'CONTRACT_TYPE' => [['system_projects', 'contract_type', '프로젝트 도급종류']],
        'CURRENCY' => [
            ['system_bank_accounts', 'currency', '은행계좌 통화'], ['ledger_transactions', 'currency', '거래 통화'],
        ],
        'EDUCATION_ATTENDANCE_STATUS' => [['institution_educations_employee_records', 'attendance_status_code', '교육 참석상태']],
        'EDUCATION_COMPLETION_STATUS' => [['institution_educations_employee_records', 'completion_status_code', '교육 이수상태']],
        'EDUCATION_TYPE' => [['institution_educations_courses', 'education_type_code', '교육과정']],
        'EMPLOYEE_ASSIGNMENT_AUDIT_ACTION' => [['institution_job_assignments_audits', 'action_type', '직원배치 감사']],
        'EMPLOYEE_ASSIGNMENT_SOURCE' => [['institution_job_assignments_audits', 'source_type', '직원배치 등록출처']],
        'EMPLOYEE_ASSIGNMENT_STATUS' => [
            ['institution_job_assignments_job_histories', 'status_code', '직무배치 이력'],
            ['institution_job_assignments_project_histories', 'status_code', '프로젝트배치 이력'],
            ['institution_job_assignments_workplace_histories', 'status_code', '근무지배치 이력'],
        ],
        'EMPLOYEE_LEAVE_TYPE' => [['institution_job_assignments_leave_periods', 'leave_type_code', '휴직 이력']],
        'EMPLOYEE_WORKPLACE_TYPE' => [['institution_job_assignments_workplace_histories', 'workplace_type_code', '근무지 이력']],
        'EMPLOYMENT_CATEGORY' => [
            ['institution_employment_contracts', 'employment_category', '근로계약'],
        ],
        'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON' => [['institution_employment_contracts', 'fixed_term_reason_code', '근로계약 기간제 사유']],
        'EMPLOYMENT_CONTRACT_PERIOD_TYPE' => [['institution_employment_contracts', 'contract_period_type', '근로계약 기간구분']],
        'EMPLOYMENT_CONTRACT_STATUS' => [['institution_employment_contracts', 'contract_status', '근로계약 상태']],
        'EMPLOYMENT_CONTRACT_TYPE' => [['institution_employment_contracts', 'contract_type', '근로계약 종류']],
        'EMPLOYMENT_RULE_STATUS' => [['institution_employment_rules_revisions', 'status_code', '취업규칙 개정상태']],
        'EMPLOYMENT_RULE_TYPE' => [['institution_employment_rules', 'regulation_type_code', '규정 종류']],
        'EMPLOYMENT_STATUS' => [
            ['user_employees', 'employment_status', '직원 재직상태'],
            ['institution_job_assignments_employment_status_histories', 'status_code', '재직상태 이력'],
        ],
        'EMPLOYMENT_TERMINATION_REASON' => [['institution_employment_contracts', 'termination_reason', '근로계약 종료사유']],
        'EMPLOYMENT_WORKING_TIME_TYPE' => [['institution_employment_contracts', 'working_time_type', '근로시간 구분']],
        'IMPORT_TYPE' => [
            ['ledger_journal_rules', 'import_type', '분개규칙 자료유형'],
            ['ledger_evidence_metadata', 'import_type', '증빙 자료유형'], ['ledger_journal_learning_events', 'import_type', '분개학습 자료유형'],
            ['ledger_evidence_bank_transaction', 'import_type', '은행 증빙'],
            ['ledger_evidence_card_hometax', 'import_type', '홈택스 카드 증빙'],
            ['ledger_evidence_card_statement', 'import_type', '카드명세 증빙'],
            ['ledger_evidence_cash_receipt', 'import_type', '현금영수증 증빙'],
            ['ledger_evidence_employee_personal_expense', 'import_type', '개인경비 증빙'],
            ['ledger_evidence_salary_report', 'import_type', '급여신고 증빙'],
            ['ledger_evidence_tax_invoice', 'import_type', '세금계산서 증빙'],
            ['ledger_evidence_tax_invoice_manual', 'import_type', '수기 세금계산서 증빙'],
        ],
        'LEAVE_REQUEST_STATUS' => [['institution_leave_requests', 'business_status_code', '휴가 신청상태']],
        'LEAVE_REQUEST_UNIT' => [
            ['institution_leave_request_items', 'request_unit_code', '휴가 신청단위'],
            ['institution_leave_usages', 'request_unit_code', '휴가 사용단위'],
        ],
        'OPERATION_TYPE' => [
            ['ledger_transactions', 'operation_type', '거래 업무유형'], ['ledger_journal_rules', 'operation_type', '분개규칙 업무유형'],
            ['ledger_journal_learning_events', 'operation_type', '분개학습 업무유형'],
        ],
        'PERSONAL_EXPENSE_CATEGORY' => [['approval_personal_expense_items', 'expense_category', '개인경비 경비구분']],
        'PERSONAL_EXPENSE_PAYMENT_METHOD' => [['approval_personal_expense_items', 'payment_method', '개인경비 지출수단']],
        'PERSONAL_EXPENSE_RECEIPT_TYPE' => [['approval_personal_expense_items', 'receipt_type', '개인경비 증빙종류']],
        'PERSONNEL_ACTION_STATUS' => [['institution_personnel_actions', 'business_status', '인사발령 상태']],
        'PERSONNEL_ACTION_TYPE' => [['institution_personnel_actions', 'action_type_code', '인사발령 유형']],
        'QUALIFICATION_STATUS' => [['institution_qualifications_employee_records', 'status_code', '직원 자격상태']],
        'QUALIFICATION_TYPE' => [['institution_qualifications_employee_records', 'qualification_type_code', '자격 종류']],
        'SALARY_TYPE' => [['institution_employment_contracts', 'salary_type', '근로계약 급여형태']],
        'SETTLEMENT_TYPE' => [['ledger_transaction_settlements', 'settlement_type', '거래 정산유형']],
        'SOURCE_TYPE' => [
            ['ledger_evidence_bank_transaction', 'source_type', '은행 증빙'],
            ['ledger_evidence_card_hometax', 'source_type', '홈택스 카드 증빙'],
            ['ledger_evidence_card_statement', 'source_type', '카드명세 증빙'],
            ['ledger_evidence_cash_receipt', 'source_type', '현금영수증 증빙'],
            ['ledger_evidence_employee_personal_expense', 'source_type', '개인경비 증빙'],
            ['ledger_evidence_salary_report', 'source_type', '급여신고 증빙'],
            ['ledger_evidence_tax_invoice', 'source_type', '세금계산서 증빙'],
            ['ledger_evidence_tax_invoice_manual', 'source_type', '수기 세금계산서 증빙'],
        ],
        'STATUTORY_STANDARD_TYPE' => [['system_statutory_standards', 'standard_type_code', '법정기준']],
        'STATUTORY_POLICY_COMPONENT' => [['system_statutory_standards', 'policy_component_code', '법정기준 정책 구성요소']],
        'STATUTORY_EMPLOYMENT_TYPE' => [['system_statutory_standards', 'employment_type_code', '법정기준 고용형태']],
        'STATUTORY_WORK_SCOPE' => [['system_statutory_standards', 'work_scope_code', '법정기준 업무 Scope']],
        'TAX_TYPE' => [
            ['system_clients', 'tax_type', '거래처 과세구분'],
            ['institution_employment_contracts_components', 'tax_type', '근로계약 급여항목 과세구분'],
        ],
        'TRANSACTION_DIRECTION' => [
            ['ledger_transactions', 'transaction_direction', '거래구분'], ['ledger_journal_rules', 'transaction_direction', '분개규칙 거래구분'],
            ['system_clients', 'trade_category', '거래처 거래구분'], ['ledger_journal_learning_events', 'transaction_direction', '분개학습 거래구분'],
        ],
        'WORK_LOCATION_TYPE' => [['institution_employment_contracts', 'work_location_type', '근로계약 근무장소']],
        'WORK_SCHEDULE_TYPE' => [['institution_employment_contracts', 'work_schedule_type', '근로계약 근무형태']],
        'WORK_TYPE' => [['system_projects', 'work_type', '프로젝트 공종']],
    ];

    private const JSON_REFERENCES = [
        'STATUTORY_ROUNDING_METHOD' => [['system_statutory_standards', 'value_data', null, '법정기준 값']],
        'STATUTORY_CONDITION_COMBINATION' => [['system_statutory_standards', 'value_data', null, '가입자격 조건 결합']],
        'INSURANCE_ELIGIBILITY_DECISION' => [['system_statutory_standards', 'value_data', null, '가입자격 판정 방식']],
        'INSURANCE_ELIGIBILITY_RESULT' => [['system_statutory_standards', 'value_data', null, '가입자격 판정 결과']],
        'INSURANCE_ELIGIBILITY_AGE_REFERENCE_DATE' => [['system_statutory_standards', 'value_data', null, '가입자격 연령 기준일']],
        'INSURANCE_ELIGIBILITY_UNDER_AGE_POLICY' => [['system_statutory_standards', 'value_data', null, '가입자격 최소연령 정책']],
        'INSURANCE_ELIGIBILITY_MONTH_JUDGMENT' => [['system_statutory_standards', 'value_data', null, '가입자격 월 판단']],
        'INSURANCE_ELIGIBILITY_INCOME_BASIS' => [['system_statutory_standards', 'value_data', null, '가입자격 소득 기준']],
        'INSURANCE_ELIGIBILITY_AGGREGATION_SCOPE' => [['system_statutory_standards', 'value_data', null, '가입자격 합산 범위']],
        'INSURANCE_ELIGIBILITY_AGGREGATION_PERIOD' => [['system_statutory_standards', 'value_data', null, '가입자격 합산 기간']],
        'INSURANCE_ELIGIBILITY_TRANSITION_POLICY' => [['system_statutory_standards', 'value_data', null, '가입자격 경과정책']],
        'INSURANCE_ELIGIBILITY_TRANSITION_STATUS' => [['system_statutory_standards', 'value_data', null, '가입자격 경과상태']],
    ];

    public static function policy(string $codeGroup): ?array
    {
        $group = strtoupper(trim($codeGroup));
        if (!in_array($group, self::GROUPS, true)) {
            return null;
        }

        $columns = self::COLUMN_REFERENCES[$group] ?? [];
        if (isset(self::EVIDENCE_COLUMNS[$group])) {
            foreach (self::EVIDENCE_TABLES as $table) {
                $columns[] = [$table, self::EVIDENCE_COLUMNS[$group], '증빙 원본'];
            }
        }

        return [
            'columns' => $columns,
            'json' => self::JSON_REFERENCES[$group] ?? [],
        ];
    }

    public static function groups(): array
    {
        return self::GROUPS;
    }
}
