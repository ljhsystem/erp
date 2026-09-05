<?php

namespace App\Services\System;

use App\Models\System\SystemSchemaModel;
use PDO;

class DataTableColumnMetaService
{
    private const DOMAIN_MAP = [
        'client' => ['table' => 'system_clients'],
        'project' => ['table' => 'system_projects'],
        'bank-account' => ['table' => 'system_bank_accounts'],
        'card' => ['table' => 'system_cards'],
        'work-team' => ['table' => 'system_work_teams'],
        'cover' => ['table' => 'system_coverimage_assets'],
        'employee' => ['composite' => 'employee'],
        'department' => ['table' => 'user_departments'],
        'position' => ['table' => 'user_positions'],
        'role' => ['table' => 'auth_roles'],
        'permission' => ['table' => 'auth_permissions'],
        'permission-assignment' => ['composite' => 'permission-assignment'],
        'individual-permission-users' => ['composite' => 'individual-permission-users'],
        'approval-template' => ['table' => 'user_approval_templates'],
        'approval-template-step' => ['table' => 'user_approval_template_steps'],
        'statutory-standard' => ['table' => 'system_statutory_standards'],
        'statutory-standard-source' => ['table' => 'system_statutory_standard_sources'],
        'personal-expense' => ['table' => 'approval_personal_expenses'],
        'leave-request' => ['composite' => 'leave-request'],
        'leave-status' => ['composite' => 'leave-status'],
        'leave-balance' => ['composite' => 'leave-balance'],
        'leave-type' => ['table' => 'institution_leave_types'],
        'qualification-status' => ['composite' => 'qualification-status'],
        'education-status' => ['composite' => 'education-status'],
        'education-session' => ['composite' => 'education-session'],
        'education-session-target' => ['composite' => 'education-session-target'],
        'qualification-type' => ['table' => 'institution_qualifications_types'],
        'education-course' => ['table' => 'institution_educations_courses'],
        'job-qualification-requirement' => ['composite' => 'job-qualification-requirement'],
        'job-education-requirement' => ['composite' => 'job-education-requirement'],
        'personal-expense-item' => ['composite' => 'personal-expense-item'],
        'employment-contract' => ['composite' => 'employment-contract'],
        'employment-rule' => ['composite' => 'employment-rule'],
        'personnel-action' => ['composite' => 'personnel-action'],
        'job-assignment' => ['composite' => 'job-assignment'],
        'attendance-daily' => ['table' => 'institution_attendance_daily_records'],
        'attendance-monthly' => ['table' => 'institution_attendance_monthly_closure_histories'],
        'attendance-exceptions' => ['table' => 'institution_attendance_daily_exceptions'],
        'attendance-closures' => ['table' => 'institution_attendance_monthly_closures'],
        'regular-employment-income' => ['composite' => 'regular-employment-income'],
        'regular-employment-income-detail' => ['table' => 'institution_regular_employment_income_items'],
        'daily-employment-income' => ['composite' => 'daily-employment-income'],
        'business-income' => ['composite' => 'business-income'],
        'business-income-group' => ['table' => 'institution_business_income_groups'],
        'business-income-item' => ['table' => 'institution_business_income_items'],
        'business-income-work-line' => ['table' => 'institution_business_income_work_lines'],
        'employment-contract-weekly-schedule' => ['table' => 'institution_employment_contracts_weekly_schedules'],
        'employment-contract-work-schedule-policy' => ['table' => 'institution_employment_contracts_work_schedule_policies'],
        'employment-contract-component' => ['table' => 'institution_employment_contracts_components'],
        'employment-contract-pay-component' => ['table' => 'institution_employment_contracts_pay_components'],
        'code' => ['table' => 'system_codes'],
        'account-subject-main' => ['table' => 'ledger_accounts'],
        'account-subject-sub' => ['table' => 'ledger_accounts_sub'],
        'transaction' => ['composite' => 'transaction'],
        'transaction-header' => ['table' => 'ledger_transactions'],
        'transaction-item' => ['composite' => 'transaction-item'],
        'transaction-settlement' => ['composite' => 'transaction-settlement'],
        'voucher' => ['composite' => 'voucher'],
        'voucher-header' => ['table' => 'ledger_vouchers'],
        'opening-balance' => ['table' => 'ledger_opening_balances'],
        'inventory-balance' => ['table' => 'ledger_inventory_balances'],
        'asset' => ['table' => 'ledger_assets'],
        'vehicle-log' => ['table' => 'ledger_vehicle_trip_logs'],
        'period-closure' => ['table' => 'ledger_period_closures'],
        'tax-financial-statement' => ['table' => 'ledger_tax_financial_statements'],
        'voucher-evidence-selection' => ['composite' => 'voucher-evidence-selection'],
        'transaction-evidence-selection' => ['composite' => 'voucher-evidence-selection'],
        'ledger-journal-rule' => ['table' => 'ledger_journal_rules'],
        'evidence-metadata' => ['table' => 'ledger_evidence_metadata'],
        'evidence-create' => ['composite' => 'evidence-create'],
        'funds-bank-transaction' => ['table' => 'ledger_evidence_bank_transaction'],
        'evidence-bank-transaction' => ['table' => 'ledger_evidence_bank_transaction'],
        'evidence-tax-invoice' => ['table' => 'ledger_evidence_tax_invoice'],
        'evidence-tax-invoice-manual' => ['table' => 'ledger_evidence_tax_invoice_manual'],
        'evidence-cash-receipt' => ['table' => 'ledger_evidence_cash_receipt'],
        'evidence-card' => ['table' => 'ledger_evidence_card_hometax'],
        'evidence-card-hometax' => ['table' => 'ledger_evidence_card_hometax'],
        'evidence-card-statement' => ['table' => 'ledger_evidence_card_statement'],
        'evidence-employee-expense-personal' => ['table' => 'ledger_evidence_employee_personal_expense'],
        'evidence-payroll-report' => ['table' => 'ledger_evidence_salary_report'],
        'evidence-daily-employment-income' => ['table' => 'ledger_evidence_daily_employment_income'],
        'evidence-business-income-report' => ['table' => 'ledger_evidence_business_income'],
    ];

    private SystemSchemaModel $schemaModel;
    private array $tableColumnsCache = [];
    private array $tableCommentCache = [];

    public function __construct(PDO $pdo)
    {
        $this->schemaModel = new SystemSchemaModel($pdo);
    }

    public function columnsForDomain(string $domain): array
    {
        $resolvedDomain = trim($domain);
        if ($resolvedDomain === '' || !isset(self::DOMAIN_MAP[$resolvedDomain])) {
            return [];
        }

        $config = self::DOMAIN_MAP[$resolvedDomain];
        if (in_array($resolvedDomain, ['statutory-standard', 'statutory-standard-source'], true)) {
            return $this->columnsForStatutoryTable((string) ($config['table'] ?? ''), $resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'employee') {
            return $this->columnsForEmployeeDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'permission-assignment') {
            return $this->columnsForPermissionAssignmentDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'individual-permission-users') {
            return $this->columnsForIndividualPermissionUsersDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'personal-expense-item') {
            return $this->columnsForPersonalExpenseItemDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'leave-request') {
            return $this->columnsForLeaveRequestDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'leave-status') {
            return $this->columnsForLeaveStatusDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'leave-balance') {
            return $this->columnsForLeaveBalanceDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'regular-employment-income') {
            return $this->columnsForRegularEmploymentIncomeDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'daily-employment-income') {
            return $this->columnsForDailyEmploymentIncomeDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'business-income') {
            return $this->columnsForBusinessIncomeDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'qualification-status') {
            $columns = $this->columnsForMergedTables($resolvedDomain, ['institution_qualifications_employee_records','institution_qualifications_types']);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [['employee_name','직원명','varchar','join',['user_employees'],['user_employees.employee_name'],'직원 SSOT 표시명'],['job_name','현재 직무','varchar','join',['institution_job_assignments_jobs'],['institution_job_assignments_jobs.job_name'],'현재 직무 표시명'],['display_status_code','현재상태','varchar','projection',['institution_qualifications_employee_records'],['status_code','valid_to'],'명시상태와 유효기간 기반 상태']], count($columns)));
        }
        if (($config['composite'] ?? '') === 'education-status') {
            $columns = $this->columnsForMergedTables($resolvedDomain, ['institution_educations_employee_records','institution_educations_courses']);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [['employee_name','직원명','varchar','join',['user_employees'],['user_employees.employee_name'],'직원 SSOT 표시명'],['job_name','현재 직무','varchar','join',['institution_job_assignments_jobs'],['institution_job_assignments_jobs.job_name'],'현재 직무 표시명'],['session_title','교육 일정명','varchar','join',['institution_educations_sessions'],['institution_educations_sessions.title'],'연결된 회사 교육 일정명'],['next_due_date','다음교육 예정일','date','projection',['institution_educations_employee_records','institution_educations_courses'],['education_end_at','recurrence_policy_code','recurrence_interval_value','recurrence_interval_unit_code'],'마지막 이수일과 재교육 정책 기반 예정일'],['display_status_code','현재상태','varchar','projection',['institution_educations_employee_records','institution_educations_courses'],['completion_status_code','next_due_date'],'이수상태와 재교육 예정일 기반 상태']], count($columns)));
        }
        if (($config['composite'] ?? '') === 'education-session') {
            $columns = $this->columnsForMergedTables($resolvedDomain, ['institution_educations_sessions','institution_educations_courses']);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [['course_name','교육과정명','varchar','join',['institution_educations_courses'],['institution_educations_courses.course_name'],'교육과정 표시명'],['organizer_employee_name','담당 직원','varchar','join',['user_employees'],['user_employees.employee_name'],'담당 직원 표시명'],['target_count','대상자 수','int','projection',['institution_educations_session_targets'],['session_id'],'활성 대상자 집계'],['acknowledged_count','확인 수','int','projection',['institution_educations_session_targets'],['acknowledged_at'],'확인 대상자 집계'],['attended_count','참석 수','int','projection',['institution_educations_session_targets'],['attendance_status_code'],'참석 대상자 집계'],['absent_count','불참 수','int','projection',['institution_educations_session_targets'],['attendance_status_code'],'불참 대상자 집계'],['completed_count','이수 수','int','projection',['institution_educations_session_targets'],['completion_status_code'],'이수 대상자 집계'],['not_completed_count','미이수 수','int','projection',['institution_educations_session_targets'],['completion_status_code'],'미이수 대상자 집계']], count($columns)));
        }
        if (($config['composite'] ?? '') === 'education-session-target') {
            $columns = $this->columnsForMergedTables($resolvedDomain, ['institution_educations_session_targets']);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [['employee_name','직원명','varchar','join',['user_employees'],['user_employees.employee_name'],'대상 직원 표시명'],['department_name','부서명','varchar','join',['user_departments'],['user_departments.department_name'],'현재 부서 표시명'],['job_name','직무명','varchar','join',['institution_job_assignments_jobs'],['institution_job_assignments_jobs.job_name'],'현재 직무 표시명']], count($columns)));
        }
        if (($config['composite'] ?? '') === 'job-qualification-requirement') {
            $columns = $this->columnsForMergedTables($resolvedDomain, ['institution_qualifications_job_requirements','institution_qualifications_types']);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [['job_name','직무명','varchar','join',['institution_job_assignments_jobs'],['institution_job_assignments_jobs.job_name'],'직무 표시명'],['target_name','자격명','varchar','join',['institution_qualifications_types'],['institution_qualifications_types.qualification_name'],'요구 자격 표시명']], count($columns)));
        }
        if (($config['composite'] ?? '') === 'job-education-requirement') {
            $columns = $this->columnsForMergedTables($resolvedDomain, ['institution_educations_job_requirements','institution_educations_courses']);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [['job_name','직무명','varchar','join',['institution_job_assignments_jobs'],['institution_job_assignments_jobs.job_name'],'직무 표시명'],['target_name','교육과정명','varchar','join',['institution_educations_courses'],['institution_educations_courses.course_name'],'요구 교육과정 표시명']], count($columns)));
        }
        if (($config['composite'] ?? '') === 'employment-contract') {
            return $this->columnsForEmploymentContractDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'employment-rule') {
            $columns = $this->columnsForMergedTables($resolvedDomain, [
                'institution_employment_rules',
                'institution_employment_rules_revisions',
            ], [], true);
            return array_merge($columns, $this->projectionMetaColumns($resolvedDomain, [
                ['regulation_type_name', '규정종류', 'varchar', 'join', ['system_codes'], ['institution_employment_rules.regulation_type_code'], '규정종류 코드의 표시명'],
                ['is_current', '현재 유효 여부', 'boolean', 'projection', ['institution_employment_rules_revisions'], ['status_code','effective_from','effective_to'], '기준일과 시행기간으로 계산한 현재 유효 여부'],
            ], count($columns)));
        }
        if (($config['composite'] ?? '') === 'personnel-action') {
            return $this->columnsForPersonnelActionDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'job-assignment') {
            return $this->columnsForJobAssignmentDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'transaction') {
            return $this->columnsForMergedTables($resolvedDomain, $this->transactionMetaTables());
        }
        if (($config['composite'] ?? '') === 'transaction-item') {
            return $this->columnsForTransactionItemDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'transaction-settlement') {
            return $this->columnsForTransactionSettlementDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'voucher') {
            return $this->columnsForMergedTables($resolvedDomain, [
                'ledger_vouchers',
                'ledger_voucher_lines',
            ]);
        }
        if (($config['composite'] ?? '') === 'voucher-evidence-selection') {
            return $this->columnsForVoucherEvidenceSelectionDomain($resolvedDomain);
        }
        if (($config['composite'] ?? '') === 'evidence-create') {
            return $this->columnsForEvidenceCreateDomain($resolvedDomain);
        }
        $columns = $this->columnsForTable((string) ($config['table'] ?? ''), $resolvedDomain);
        return $columns;
    }

    private function columnsForEvidenceCreateDomain(string $domain): array
    {
        $columns = [];
        $projectionColumns = [
            ['source_type', '자료출처', 'varchar', false],
            ['import_type', '자료유형', 'varchar', false],
            ['business_unit', '사업구분', 'varchar', true],
            ['transaction_direction', '거래구분', 'varchar', true],
            ['operation_type', '업무유형', 'varchar', true],
            ['client_name', '거래처', 'varchar', true],
            ['project_name', '프로젝트', 'varchar', true],
            ['bank_account_name', '계좌', 'varchar', true],
            ['card_name', '카드', 'varchar', true],
            ['team_name', '팀', 'varchar', true],
            ['standard_date', '기준일', 'datetime', true],
            ['policy.IN_AMOUNT', '입금금액', 'decimal', true],
            ['policy.OUT_AMOUNT', '출금금액', 'decimal', true],
            ['policy.PRE_TAX_AMOUNT', '세전금액', 'decimal', true],
            ['policy.ADJUST_AMOUNT', '가감금액합계', 'decimal', true],
            ['policy.POST_TAX_AMOUNT', '세후금액', 'decimal', true],
            ['evidence_status', '증빙상태', 'varchar', true],
        ];

        foreach ($projectionColumns as $index => [$key, $label, $dataType, $nullable]) {
            $columns[] = $this->normalizeColumnMeta(
                domain: $domain,
                table: 'evidence_projection',
                tableComment: '증빙원본 증빙정책 Projection',
                column: $key,
                label: $label,
                dataType: $dataType,
                isNullable: $nullable ? 'YES' : 'NO',
                ordinalPosition: $index + 1,
                sourceTitle: $key
            );
        }

        return $columns;
    }

    private function columnsForVoucherEvidenceSelectionDomain(string $domain): array
    {
        return $this->projectionMetaColumns($domain, [
            ['evidence_id', '증빙 ID', 'varchar', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.id'], '자료유형별 증빙원본 ID'],
            ['source_type', '원천유형', 'varchar', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.source_type'], '증빙원본의 원천유형'],
            ['import_type', '자료유형', 'varchar', 'projection', ['ledger_evidence_metadata'], ['ledger_evidence_metadata.import_type'], '증빙정책의 자료유형 표시명'],
            ['evidence_type', '증빙구분', 'varchar', 'projection', ['ledger_evidence_metadata'], ['ledger_evidence_metadata.evidence_type'], '자료증빙·자금증빙·겸용 구분'],
            ['evidence_date', '기준일', 'date', 'projection', ['ledger_evidence_metadata'], ['standard_date_field'], '증빙정책 기준일 컬럼의 값'],
            ['evidence_status', '증빙상태', 'varchar', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.evidence_status'], '증빙원본 처리상태'],
            ['business_unit', '사업구분', 'varchar', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.business_unit'], '증빙원본 사업구분'],
            ['transaction_direction', '거래구분', 'varchar', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.transaction_direction'], '증빙원본 거래방향'],
            ['operation_type', '업무유형', 'varchar', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.operation_type'], '증빙원본 업무유형'],
            ['client_name', '거래처', 'varchar', 'join', ['system_clients'], ['Evidence Body.client_id'], '거래처 SSOT 표시명'],
            ['project_name', '프로젝트', 'varchar', 'join', ['system_projects'], ['Evidence Body.project_id'], '프로젝트 SSOT 표시명'],
            ['employee_name', '직원', 'varchar', 'join', ['user_employees'], ['Evidence Body.employee_id'], '직원 SSOT 표시명'],
            ['bank_account_name', '계좌', 'varchar', 'join', ['system_bank_accounts'], ['Evidence Body.bank_account_id'], '계좌 SSOT 표시명'],
            ['card_name', '카드', 'varchar', 'join', ['system_cards'], ['Evidence Body.card_id'], '카드 SSOT 표시명'],
            ['team_name', '팀', 'varchar', 'join', ['system_work_teams'], ['Evidence Body.team_id'], '팀 SSOT 표시명'],
            ['display_summary', '적요', 'varchar', 'projection', ['ledger_evidence_metadata'], ['DESCRIPTION semantic'], '증빙정책 적요 의미값'],
            ['display_amount', '금액', 'decimal', 'projection', ['ledger_evidence_metadata'], ['금액 semantic'], '증빙정책 금액 의미값'],
            ['created_at', '생성일시', 'datetime', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.created_at'], '증빙원본 생성일시'],
            ['updated_at', '수정일시', 'datetime', 'projection', ['ledger_evidence_metadata'], ['Evidence Body.updated_at'], '증빙원본 수정일시'],
        ], 0);
    }

    public function hasDomain(string $domain): bool
    {
        return isset(self::DOMAIN_MAP[trim($domain)]);
    }

    public function supportedDomains(): array
    {
        return array_keys(self::DOMAIN_MAP);
    }

    private function columnsForTable(string $tableName, string $domain): array
    {
        if ($tableName === '') {
            return [];
        }

        $tableComment = $this->queryTableComment($tableName);

        $usesExactPhysicalLabel = str_starts_with($domain, 'evidence-')
            || $domain === 'funds-bank-transaction';

        return array_map(
            fn(array $row): array => $this->normalizeColumnMeta(
                domain: $domain,
                table: $tableName,
                tableComment: $tableComment,
                column: (string) ($row['COLUMN_NAME'] ?? ''),
                label: $usesExactPhysicalLabel
                    ? $this->physicalColumnLabel(
                        (string) ($row['COLUMN_NAME'] ?? ''),
                        (string) ($row['COLUMN_COMMENT'] ?? '')
                    )
                    : $this->columnLabel(
                        (string) ($row['COLUMN_NAME'] ?? ''),
                        (string) ($row['COLUMN_COMMENT'] ?? '')
                    ),
                dataType: (string) ($row['DATA_TYPE'] ?? ''),
                isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                ordinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0)
            ),
            $this->queryTableColumns($tableName)
        );
    }

    private function physicalColumnLabel(string $columnName, string $comment): string
    {
        $normalizedComment = trim($comment);
        return $normalizedComment !== '' ? $normalizedComment : $columnName;
    }

    private function columnsForEmployeeDomain(string $domain): array
    {
        $definitions = [
            [
                'table' => 'user_employees',
                'key_map' => [],
            ],
            [
                'table' => 'auth_users',
                'key_map' => [
                    'id' => 'auth_user_id',
                    'created_at' => 'user_created_at',
                    'created_by' => 'user_created_by',
                    'updated_at' => 'user_updated_at',
                    'updated_by' => 'user_updated_by',
                ],
            ],
        ];

        $merged = [];
        $sequence = 1;

        foreach ($definitions as $definition) {
            $tableName = (string) ($definition['table'] ?? '');
            $keyMap = (array) ($definition['key_map'] ?? []);
            $tableComment = $this->queryTableComment($tableName);

            foreach ($this->queryTableColumns($tableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if ($sourceColumn === '') {
                    continue;
                }

                $key = (string) ($keyMap[$sourceColumn] ?? $sourceColumn);
                if (isset($merged[$key])) {
                    throw new \RuntimeException(sprintf(
                        '직원 테이블 설정 메타데이터 키가 중복되었습니다: %s.%s (%s)',
                        $tableName,
                        $sourceColumn,
                        $key
                    ));
                }
                $label = $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? ''));

                $merged[$key] = $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $tableName,
                    tableComment: $tableComment,
                    column: $key,
                    label: $label,
                    dataType: (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: $sequence++,
                    sourceTitle: $sourceColumn,
                    sourceOrdinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                    columnDefault: $row['COLUMN_DEFAULT'] ?? null
                );
            }
        }

        return array_values($merged);
    }

    private function columnsForEmploymentContractDomain(string $domain): array
    {
        return $this->columnsForMergedTables($domain, [
            'institution_employment_contracts',
            'institution_employment_contracts_weekly_schedules',
            'institution_employment_contracts_work_schedule_policies',
            'institution_employment_contracts_components',
        ], [
            'institution_employment_contracts' => [
                'employee_id' => ['employee_name', '직원명'],
                'project_id' => ['project_id', '특정 프로젝트'],
                'previous_contract_id' => ['previous_contract_no', '이전 계약번호'],
                'current_approval_request_id' => ['approval_request_no', '결재요청 순번'],
            ],
        ]);
    }

    private function columnsForPersonnelActionDomain(string $domain): array
    {
        return $this->columnsForMergedTables($domain, [
            'institution_personnel_actions',
            'institution_personnel_actions_targets',
        ], [
            'institution_personnel_actions' => [
                'current_approval_request_id' => ['approval_request_no', '결재요청 순번'],
                'original_action_id' => ['original_action_no', '원본 발령번호'],
            ],
        ]);
    }

    private function columnsForJobAssignmentDomain(string $domain): array
    {
        return $this->columnsForMergedTables($domain, [
            'user_employees',
            'institution_job_assignments_employment_status_histories',
            'institution_job_assignments_department_histories',
            'institution_job_assignments_position_histories',
            'institution_job_assignments_job_histories',
            'institution_job_assignments_project_histories',
            'institution_job_assignments_workplace_histories',
        ], [], true);
    }

    private function columnsForPermissionAssignmentDomain(string $domain): array
    {
        return $this->columnsForTable('auth_permissions', $domain);
    }

    private function columnsForIndividualPermissionUsersDomain(string $domain): array
    {
        return $this->columnsForMergedTables($domain, [
            'user_employees',
        ], [], true);
    }

    private function columnsForPersonalExpenseItemDomain(string $domain): array
    {
        $allowed = [
            'sort_no', 'expense_date', 'expense_category', 'payment_method', 'receipt_type',
            'project_id', 'client_id', 'merchant_name', 'merchant_business_no',
            'merchant_representative', 'merchant_address', 'merchant_address_detail',
            'merchant_phone', 'item_name', 'item_specification', 'item_unit_name',
            'item_quantity', 'item_unit_price', 'item_supply_amount', 'item_vat_amount',
            'item_total_amount', 'item_description', 'item_memo',
        ];
        $byKey = [];
        foreach ($this->columnsForTable('approval_personal_expense_items', $domain) as $column) {
            $byKey[(string) ($column['key'] ?? '')] = $column;
        }
        $columns = [];
        foreach ($allowed as $index => $key) {
            if (!isset($byKey[$key])) {
                continue;
            }
            $column = $byKey[$key];
            $column['ordinal_position'] = $index + 1;
            $columns[] = $column;
        }
        return $columns;
    }

    private function columnsForLeaveRequestDomain(string $domain): array
    {
        return $this->columnsForLeaveRequestProjection($domain, true);
    }

    private function columnsForLeaveStatusDomain(string $domain): array
    {
        return $this->columnsForLeaveRequestProjection($domain, false);
    }

    private function columnsForLeaveRequestProjection(string $domain, bool $employeeScreen): array
    {
        $columns = $this->columnsForMergedTables($domain, [
            'institution_leave_requests',
            'institution_leave_request_items',
            'institution_leave_types',
            'user_employees',
            'auth_users',
            'user_approval_requests',
            'user_approval_request_steps',
        ], [], true);
        $definitions = [
            ['employee_name', '직원명', 'varchar', 'join', ['user_employees'], ['user_employees.employee_name'], '직원 식별자의 현재 직원명 표시값'],
            ['username', '사용자 아이디', 'varchar', 'join', ['auth_users'], ['auth_users.username'], '직원과 연결된 인증 사용자 아이디'],
            ['leave_from', '시작일', 'date', 'projection', ['institution_leave_request_items'], ['institution_leave_request_items.leave_date'], '휴가 신청항목 사용일의 최솟값'],
            ['leave_to', '종료일', 'date', 'projection', ['institution_leave_request_items'], ['institution_leave_request_items.leave_date'], '휴가 신청항목 사용일의 최댓값'],
            ['leave_type_names', '휴가유형', 'varchar', 'projection', ['institution_leave_request_items','institution_leave_types'], ['institution_leave_request_items.leave_type_id','institution_leave_types.type_name'], '신청에 포함된 휴가유형명의 중복 제거 집계'],
            ['request_unit_names', '신청단위', 'varchar', 'projection', ['institution_leave_request_items'], ['institution_leave_request_items.request_unit_code'], '신청에 포함된 신청단위의 중복 제거 집계'],
            ['deductible_total_minutes', '차감시간', 'int', 'projection', ['institution_leave_request_items'], ['institution_leave_request_items.deductible_minutes'], '신청항목 차감시간의 합계'],
            ['approval_status', '결재상태', 'varchar', 'join', ['user_approval_requests'], ['user_approval_requests.status'], '현재 전자결재 요청의 상태 표시값'],
            ['current_step_name', '현재 결재단계', 'varchar', 'join', ['user_approval_request_steps'], ['user_approval_request_steps.step_name'], '현재 활성 결재단계명'],
            ['completed_at', '최종처리일시', 'datetime', 'join', ['user_approval_requests'], ['user_approval_requests.completed_at'], '현재 전자결재 요청의 완료일시'],
        ];
        if ($employeeScreen) {
            $definitions[] = ['leave_period', '사용일/기간', 'varchar', 'projection', ['institution_leave_request_items'], ['institution_leave_request_items.leave_date'], '시작일과 종료일을 결합한 화면 표시값'];
        }
        $definitions[] = ['__actions', '관리', 'virtual', 'virtual', [], [], '휴가 신청 업무동작 시스템 가상컬럼'];
        return array_merge($columns, $this->projectionMetaColumns($domain, $definitions, count($columns)));
    }

    private function columnsForLeaveBalanceDomain(string $domain): array
    {
        $columns = $this->columnsForMergedTables($domain, [
            'user_employees',
            'institution_leave_types',
            'institution_leave_ledger_entries',
        ], [], true);
        return array_merge($columns, $this->projectionMetaColumns($domain, [
            ['employee_name', '직원명', 'varchar', 'join', ['user_employees'], ['user_employees.employee_name'], '직원 식별자의 현재 직원명 표시값'],
            ['type_name', '휴가유형', 'varchar', 'join', ['institution_leave_types'], ['institution_leave_types.type_name'], '휴가유형 식별자의 현재 유형명 표시값'],
            ['base_year', '기준연도', 'smallint', 'projection', [], [], '검색 요청에서 선택한 기준연도'],
            ['balance_minutes', '잔액', 'int', 'projection', ['institution_leave_ledger_entries'], ['institution_leave_ledger_entries.amount_minutes'], '직원·휴가유형·기준연도별 원장 증감시간 합계'],
        ], count($columns)));
    }

    private function columnsForRegularEmploymentIncomeDomain(string $domain): array
    {
        return $this->columnsForTable('institution_regular_employment_incomes', $domain);
    }

    private function columnsForDailyEmploymentIncomeDomain(string $domain): array
    {
        return $this->columnsForTable('institution_daily_employment_incomes', $domain);
    }

    private function columnsForBusinessIncomeDomain(string $domain): array
    {
        $columns = $this->columnsForTable('institution_business_incomes', $domain);
        $systemColumns = $this->projectionMetaColumns($domain, [
            ['__select', '선택', 'virtual', 'virtual', [], [], '공용 선택 시스템 가상컬럼'],
            ['__reorder', '순서', 'virtual', 'virtual', [], [], '공용 순서변경 시스템 가상컬럼'],
        ], 0);
        $actions = $this->projectionMetaColumns($domain, [
            ['__actions', '관리', 'virtual', 'virtual', [], [], '사업소득 상세 업무동작 가상컬럼'],
        ], count($systemColumns) + count($columns));
        return array_merge($systemColumns, $columns, $actions);
    }

    private function projectionMetaColumns(string $domain, array $definitions, int $offset): array
    {
        $columns = [];
        foreach ($definitions as $index => [$key,$label,$dataType,$columnType,$sourceTables,$sourceColumns,$description]) {
            $column = $this->normalizeColumnMeta(
                domain: $domain,
                table: '',
                tableComment: '',
                column: $key,
                label: $label,
                dataType: $dataType,
                isNullable: 'YES',
                ordinalPosition: $offset + $index + 1,
                sourceTitle: implode(', ', $sourceColumns) ?: $key,
                columnType: $columnType,
                sourceRole: $columnType
            );
            $column['source_tables'] = $sourceTables;
            $column['source_columns'] = $sourceColumns;
            $column['description'] = $description;
            $column['required'] = false;
            $columns[] = $column;
        }
        return $columns;
    }

    private function columnsForStatutoryTable(string $tableName, string $domain): array
    {
        $hidden = $domain === 'statutory-standard'
            ? ['id', 'created_at', 'created_by']
            : ['id', 'standard_id', 'file_path', 'file_size', 'mime_type', 'created_at', 'created_by', 'updated_at', 'updated_by'];
        $columns = array_map(static function (array $column) use ($hidden): array {
            if (in_array((string) ($column['key'] ?? ''), $hidden, true)) {
                $column['settings_visible'] = false;
            }
            return $column;
        }, $this->columnsForTable($tableName, $domain));
        if ($domain !== 'statutory-standard') return $columns;
        $valueSummary = $this->projectionMetaColumns($domain, [[
            'value_summary', '기준값', 'TEXT', 'projection',
            ['system_statutory_standards'], ['value_data', 'policy_component_code', 'employment_type_code', 'work_scope_code'],
            '정책 구성요소에 따라 금액·요율·표·가입자격을 표시하는 목록 전용 요약값',
        ]], count($columns))[0];
        $valueSummary += [
            'column_key' => 'value_summary', 'column_name' => '기준값', 'source_type' => 'VIRTUAL',
            'default_visible' => true, 'default_order' => count($columns) + 1,
            'sortable' => false, 'searchable' => false, 'hideable' => true,
            'width' => 220, 'alignment' => 'left',
            'formatter_code' => 'STATUTORY_VALUE_SUMMARY', 'modal_input' => false,
        ];
        foreach ($columns as &$column) {
            if (($column['key'] ?? null) === 'value_data') {
                $column['settings_visible'] = false;
            }
        }
        unset($column);
        $periodStatus = $this->projectionMetaColumns($domain, [[
            'period_status', '적용상태', 'CODE', 'projection',
            ['system_statutory_standards'], ['effective_from', 'effective_to'],
            '회사 업무일 기준 적용기간으로 계산한 적용예정·현재적용·종료 상태',
        ]], count($columns) + 1)[0];
        $periodStatus += [
            'column_key' => 'period_status', 'column_name' => '적용상태', 'source_type' => 'VIRTUAL',
            'default_visible' => true, 'default_order' => count($columns) + 2,
            'sortable' => true, 'searchable' => true, 'hideable' => true,
            'width' => 110, 'alignment' => 'center',
            'formatter_code' => 'STATUTORY_PERIOD_STATUS_BADGE', 'modal_input' => false,
        ];
        return array_merge($columns, [$valueSummary, $periodStatus]);
    }

    /**
     * @param list<string> $tableNames
     */
    private function columnsForMergedTables(
        string $domain,
        array $tableNames,
        array $keyOverrides = [],
        bool $fullyQualifiedKeys = false
    ): array
    {
        $merged = [];
        $sequence = 1;
        $usedKeys = [];

        foreach ($tableNames as $tableName) {
            $resolvedTableName = trim((string) $tableName);
            if ($resolvedTableName === '') {
                continue;
            }

            $tableComment = $this->queryTableComment($resolvedTableName);

            foreach ($this->queryTableColumns($resolvedTableName) as $row) {
                $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
                if ($sourceColumn === '') {
                    continue;
                }

                $override = $keyOverrides[$resolvedTableName][$sourceColumn] ?? null;
                $preferredKey = is_array($override) ? (string) ($override[0] ?? $sourceColumn) : $sourceColumn;
                $columnKey = $fullyQualifiedKeys
                    ? $resolvedTableName . '.' . $sourceColumn
                    : $this->mergedTableColumnKey($resolvedTableName, $preferredKey, $usedKeys);
                $label = is_array($override) && trim((string) ($override[1] ?? '')) !== ''
                    ? (string) $override[1]
                    : $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? ''));

                $merged[$resolvedTableName . '.' . $sourceColumn] = $this->normalizeColumnMeta(
                    domain: $domain,
                    table: $resolvedTableName,
                    tableComment: $tableComment,
                    column: $columnKey,
                    label: $label,
                    dataType: (string) ($row['DATA_TYPE'] ?? ''),
                    isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                    ordinalPosition: $sequence++,
                    sourceTitle: $sourceColumn,
                    sourceOrdinalPosition: (int) ($row['ORDINAL_POSITION'] ?? 0),
                    columnDefault: $row['COLUMN_DEFAULT'] ?? null
                );
            }
        }

        return array_values($merged);
    }

    /**
     * @return list<string>
     */
    private function transactionMetaTables(): array
    {
        $tables = ['ledger_transactions'];

        if ($this->tableExists('ledger_transaction_items')
            && $this->tableColumnExists('ledger_transaction_items', 'transaction_id')
        ) {
            $tables[] = 'ledger_transaction_items';
        }

        if ($this->tableExists('ledger_transaction_settlements')
            && $this->tableColumnExists('ledger_transaction_settlements', 'transaction_id')
        ) {
            $tables[] = 'ledger_transaction_settlements';
        }

        return $tables;
    }

    private function columnsForTransactionItemDomain(string $domain): array
    {
        $tableName = $this->transactionItemMetaTable();
        if ($tableName === '') {
            return [];
        }

        return $this->columnsForTable($tableName, $domain); /*
            'item_date' => 'item_date',
            'item_name' => 'item_name',
            'item_specification' => 'specification',
            'item_unit_name' => 'unit_name',
            'item_quantity' => 'quantity',
            'item_unit_price' => 'unit_price',
            'item_foreign_unit_price' => 'foreign_unit_price',
            'item_foreign_amount' => 'foreign_amount',
            'item_supply_amount' => 'amount',
            'item_description' => 'description',
        ], [
            'specification' => '규격',
            'unit_name' => '단위',
            'quantity' => '수량',
            'unit_price' => '단가',
            'foreign_unit_price' => '외화단가',
            'foreign_amount' => '외화금액',
            'amount' => '공급가액',
            'description' => '적요',
        ]); */
    }

    private function columnsForTransactionSettlementDomain(string $domain): array
    {
        $tableName = $this->transactionSettlementMetaTable();
        if ($tableName === '') {
            return [];
        }

        return $this->columnsForTable($tableName, $domain); /*
            'settlement_type' => 'settlement_type',
            'amount_sign' => 'amount_sign',
            'amount' => 'amount',
            'settlement_description' => 'description',
        ], [
            'settlement_type' => '정산유형',
            'amount_sign' => '가감유형',
            'amount' => '정산금액',
            'description' => '적요',
        ]); */
    }

    /**
     * @param array<string, string> $keyMap
     * @param array<string, string> $labelOverrides
     */
    private function columnsForMappedTable(string $domain, string $tableName, array $keyMap, array $labelOverrides = []): array
    {
        if ($tableName === '') {
            return [];
        }

        $sequence = 1;
        $tableComment = $this->queryTableComment($tableName);
        $columns = [];

        foreach ($this->queryTableColumns($tableName) as $row) {
            $sourceColumn = (string) ($row['COLUMN_NAME'] ?? '');
            if ($sourceColumn === '' || !isset($keyMap[$sourceColumn])) {
                continue;
            }

            $key = (string) $keyMap[$sourceColumn];
            $label = (string) ($labelOverrides[$key]
                ?? $this->columnLabel($sourceColumn, (string) ($row['COLUMN_COMMENT'] ?? '')));

            $columns[] = $this->normalizeColumnMeta(
                domain: $domain,
                table: $tableName,
                tableComment: $tableComment,
                column: $key,
                label: $label,
                dataType: (string) ($row['DATA_TYPE'] ?? ''),
                isNullable: (string) ($row['IS_NULLABLE'] ?? 'YES'),
                ordinalPosition: $sequence++,
                sourceTitle: $sourceColumn
            );
        }

        return $columns;
    }

    private function transactionItemMetaTable(): string
    {
        if ($this->tableExists('ledger_transaction_items')
            && $this->tableColumnExists('ledger_transaction_items', 'transaction_id')
        ) {
            return 'ledger_transaction_items';
        }

        return '';
    }

    private function transactionSettlementMetaTable(): string
    {
        if ($this->tableExists('ledger_transaction_settlements')
            && $this->tableColumnExists('ledger_transaction_settlements', 'transaction_id')
        ) {
            return 'ledger_transaction_settlements';
        }

        return '';
    }

    private function mergedTableColumnKey(string $tableName, string $sourceColumn, array &$usedKeys): string
    {
        $baseKey = trim($sourceColumn);
        if ($baseKey === '') {
            return '';
        }

        if (!isset($usedKeys[$baseKey])) {
            $usedKeys[$baseKey] = 1;
            return $baseKey;
        }

        $candidate = $tableName . '.' . $baseKey;
        if (!isset($usedKeys[$candidate])) {
            $usedKeys[$candidate] = 1;
            return $candidate;
        }

        $suffix = $usedKeys[$baseKey];
        do {
            $candidate = $tableName . '.' . $baseKey . '.' . $suffix;
            $suffix++;
        } while (isset($usedKeys[$candidate]));

        $usedKeys[$baseKey] = $suffix;
        $usedKeys[$candidate] = 1;

        return $candidate;
    }

    private function normalizeColumnMeta(
        string $domain,
        string $table,
        string $tableComment,
        string $column,
        string $label,
        string $dataType,
        string $isNullable,
        int $ordinalPosition,
        string $sourceTitle = '',
        int $sourceOrdinalPosition = 0,
        mixed $columnDefault = null,
        string $columnType = 'physical',
        string $sourceRole = ''
    ): array {
        $sensitive = preg_match('/(?:identifier|resident|registration)_.*(?:snapshot|encrypted|cipher)/i', $column) === 1;
        return [
            'domain' => $domain,
            'table' => $table,
            'table_comment' => $tableComment !== '' ? $tableComment : $table,
            'key' => $column,
            'label' => $label !== '' ? $label : $column,
            'source_title' => $sourceTitle !== '' ? $sourceTitle : $column,
            'source_column' => $sourceTitle !== '' ? $sourceTitle : $column,
            'data_type' => $dataType,
            'is_nullable' => strtoupper($isNullable) === 'NO' ? 'NO' : 'YES',
            'column_default' => $columnDefault,
            'ordinal_position' => max(0, $ordinalPosition),
            'source_ordinal_position' => max(0, $sourceOrdinalPosition ?: $ordinalPosition),
            'column_type' => $columnType,
            'source_role' => $sourceRole,
            'required' => strtoupper($isNullable) === 'NO',
            'settings_visible' => !$sensitive,
            'settings_order' => max(0, $ordinalPosition),
        ];
    }

    private function queryTableColumns(string $tableName): array
    {
        if ($tableName === '') {
            return [];
        }

        if (!array_key_exists($tableName, $this->tableColumnsCache)) {
            $this->tableColumnsCache[$tableName] = $this->schemaModel->getTableColumns($tableName);
        }
        return $this->tableColumnsCache[$tableName];
    }

    private function queryTableComment(string $tableName): string
    {
        if ($tableName === '') {
            return '';
        }

        if (!array_key_exists($tableName, $this->tableCommentCache)) {
            $this->tableCommentCache[$tableName] = trim($this->schemaModel->getTableComment($tableName));
        }
        return $this->tableCommentCache[$tableName];
    }

    private function tableExists(string $tableName): bool
    {
        if ($tableName === '') {
            return false;
        }

        return $this->schemaModel->tableExists($tableName);
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        return $this->schemaModel->columnExists($tableName, $columnName);
    }

    private function columnLabel(string $columnName, string $comment): string
    {
        $normalizedComment = trim($comment);
        if ($normalizedComment !== '') {
            return $normalizedComment;
        }

        $fallbackLabels = [
            'sort_no' => '순번',
            'id' => 'ID',
            'standard_id' => '법정기준 ID',
            'standard_code' => '기준코드',
            'category_code' => '업무분류',
            'standard_name' => '기준명',
            'value_structure_code' => '값 구조',
            'calculation_purpose_code' => '계산 목적',
            'description' => '설명',
            'is_active' => '사용 여부',
            'revision_no' => '개정번호',
            'revision_title' => '개정 제목',
            'revision_reason' => '개정 사유',
            'status_code' => '상태',
            'effective_from' => '시행 시작일',
            'effective_to' => '시행 종료일',
            'published_date' => '공표일',
            'calculation_version' => '계산 버전',
            'request_key' => '요청 식별키',
            'reviewed_at' => '검토일시',
            'reviewed_by' => '검토자',
            'confirmed_at' => '시행 확정일시',
            'confirmed_by' => '시행 확정자',
            'created_at' => '생성일시',
            'created_by' => '생성자',
            'updated_at' => '수정일시',
            'updated_by' => '수정자',
            'deleted_at' => '삭제일시',
            'deleted_by' => '삭제자',
            'debit_account_id' => '차변계정 ID',
            'credit_account_id' => '대변계정 ID',
            'vat_account_id' => '부가세계정 ID',
            'usage_count' => '확정 사용 횟수',
            'last_used_at' => '최종 확정 사용일시',
            'confidence_score' => '규칙 신뢰도',
            'rate_value' => '요율',
            'amount_value' => '금액',
            'employee_rate' => '근로자 부담률',
            'employer_rate' => '사업주 부담률',
            'additional_employer_rate' => '추가 사업주 부담률',
            'minimum_base_amount' => '기준금액 하한',
            'maximum_base_amount' => '기준금액 상한',
            'minimum_result_amount' => '결과금액 하한',
            'maximum_result_amount' => '결과금액 상한',
            'age_from' => '최소 연령',
            'age_to' => '최대 연령',
            'month_count_threshold' => '개월수 기준',
            'boolean_value' => '적용 여부',
            'date_value' => '날짜',
            'time_value' => '시간',
            'text_value' => '문자값',
            'rounding_method_code' => '끝수처리',
            'rounding_unit' => '처리 단위',
            'decimal_scale' => '소수 자릿수',
            'lower_bound_amount' => '과세표준 시작금액',
            'upper_bound_amount' => '과세표준 종료금액',
            'progressive_deduction_amount' => '누진공제액',
            'fixed_amount' => '고정세액',
            'income_from_amount' => '급여 시작액',
            'income_to_amount' => '급여 종료액',
            'dependent_count' => '부양가족 수',
            'child_count' => '자녀 수',
            'selection_rate_percent' => '선택비율(%)',
            'tax_amount' => '세액',
            'worker_type_code' => '근로자 유형',
            'industry_code' => '업종 코드',
            'business_size_code' => '사업 규모',
            'is_construction' => '건설업 여부',
            'workplace_scope_code' => '사업장 범위',
        ];

        if (isset($fallbackLabels[$columnName])) {
            return $fallbackLabels[$columnName];
        }

        return $columnName;
    }
}
