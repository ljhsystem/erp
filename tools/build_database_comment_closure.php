<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$protected = ['main_calendar_events', 'main_calendar_list', 'main_calendar_tasks'];
$korean = '/[가-힣]/u';
$broken = '/(?:\?뚯|\?뱀|\?붿|\?꾩|\?대|\?놁|硫|媛|湲|�)/u';
$isIssue = static fn(string $comment): bool => trim($comment) === '' || !preg_match($korean, $comment) || preg_match($broken, $comment) === 1;

$labels = [
    'id'=>'고유번호','sort_no'=>'정렬순서','created_at'=>'등록일시','created_by'=>'등록자','updated_at'=>'수정일시','updated_by'=>'수정자',
    'deleted_at'=>'삭제일시','deleted_by'=>'삭제자','approved_at'=>'승인일시','approved_by'=>'승인자','completed_at'=>'완료일시','processed_at'=>'처리일시',
    'processed_by'=>'처리자','calculated_at'=>'계산일시','calculated_by'=>'계산자','confirmed_at'=>'확정일시','confirmed_by'=>'확정자',
    'failed_at'=>'실패일시','executed_at'=>'실행일시','executed_by'=>'실행자','verified_at'=>'검증일시','verified_by'=>'검증자',
    'permission_id'=>'권한 고유번호','business_income_id'=>'사업소득 문서 고유번호','business_income_group_id'=>'사업소득 그룹 고유번호',
    'business_income_item_id'=>'사업소득 지급대상자 고유번호','group_id'=>'사업소득 그룹 고유번호','calculation_revision_id'=>'계산 개정본 고유번호',
    'approval_request_id'=>'결재요청 고유번호','closure_id'=>'완료처리 고유번호','evidence_id'=>'증빙 고유번호','transaction_id'=>'거래 고유번호',
    'client_id'=>'거래처 고유번호','employee_id'=>'직원 고유번호','project_id'=>'프로젝트 고유번호','work_team_id'=>'업무팀 고유번호',
    'worker_client_id'=>'근로자 거래처 고유번호','client_tax_profile_id'=>'거래처 세무프로필 고유번호','statutory_standard_id'=>'법정기준 고유번호',
    'statutory_standard_revision_id'=>'법정기준 개정본 고유번호','source_calculation_line_id'=>'원본 계산항목 고유번호','calculation_result_id'=>'계산결과 고유번호',
    'daily_employment_income_id'=>'일용근로소득 문서 고유번호','daily_employment_income_group_id'=>'일용근로소득 그룹 고유번호',
    'daily_employment_income_item_id'=>'일용근로소득 근로자 고유번호','daily_employment_income_workday_id'=>'일용근로일 고유번호',
    'daily_employment_income_line_id'=>'일용근로소득 계산항목 고유번호','social_insurance_workplace_id'=>'사회보험 사업장 고유번호',
    'work_team_assignment_id'=>'업무팀 배정 고유번호','coverage_id'=>'사회보험 적용 고유번호','non_taxable_revision_id'=>'비과세 개정본 고유번호',
    'corrects_revision_id'=>'정정대상 개정본 고유번호','supersedes_revision_id'=>'대체대상 개정본 고유번호','source_revision_id'=>'원본 개정본 고유번호',
    'income_year_month'=>'귀속연월','title'=>'제목','description'=>'설명','document_status'=>'문서상태','calculation_status'=>'계산상태','approval_status'=>'결재상태',
    'payment_status'=>'지급거래 생성상태','withholding_filing_status'=>'원천징수 신고상태','simplified_statement_status'=>'간이지급명세서 상태',
    'current_calculation_revision_id'=>'현재 계산 개정본 고유번호','current_approval_request_id'=>'현재 결재요청 고유번호','generation_status'=>'산출물 생성상태',
    'result_hash'=>'처리결과 해시','source_hash'=>'원본자료 해시','business_key_hash'=>'업무키 해시','payload_hash'=>'요청내용 해시','request_key'=>'멱등 요청키',
    'snapshot_json'=>'승인 원본 스냅샷','snapshot_version'=>'스냅샷 버전','recipient_tax_snapshot_json'=>'소득자 세무정보 스냅샷',
    'line_type'=>'계산항목 유형','line_code'=>'계산항목 코드','line_name'=>'계산항목명','calculation_base_amount'=>'계산기준 금액','applied_rate'=>'적용률',
    'amount_before_rounding'=>'반올림 전 금액','rounding_method'=>'반올림 방식','rounding_unit'=>'반올림 단위','calculated_amount'=>'계산금액','applicability_status'=>'적용상태',
    'revision_no'=>'개정번호','revision_status'=>'개정상태','calculation_date'=>'계산기준일','policy_status'=>'정책적용 상태','status'=>'처리상태','processing_token'=>'처리 추적 토큰',
    'started_at'=>'시작일시','requested_by'=>'요청자','command_type'=>'명령유형','command_status'=>'명령상태','result_reference_id'=>'처리결과 참조 고유번호',
    'payment_date'=>'지급일','business_unit'=>'사업구분','group_description'=>'그룹 설명','service_type_code'=>'용역유형 코드','service_description'=>'용역내용',
    'gross_payment_amount'=>'총지급액','income_tax_amount'=>'사업소득세액','local_income_tax_amount'=>'지방소득세액','other_deduction_amount'=>'기타공제액',
    'total_deduction_amount'=>'총공제액','net_payment_amount'=>'최종지급액','artifact_role'=>'연결 산출물 역할',
    'allocation_scope_code'=>'배분범위 코드','allocation_basis_amount'=>'배분기준 금액','allocation_numerator'=>'배분분자','allocation_denominator'=>'배분분모',
    'allocated_employee_amount'=>'배분 근로자부담액','allocated_employer_amount'=>'배분 사용자부담액','residual_amount'=>'배분 잔액','residual_applied'=>'잔액 반영 여부',
    'decision_rank'=>'판정 우선순위','allocation_policy_version'=>'배분정책 버전','result_type_code'=>'결과유형 코드','work_date'=>'근로일',
    'application_from'=>'적용시작일','application_to'=>'적용종료일','payment_sequence'=>'지급순번','calculation_basis_amount'=>'계산기초 금액',
    'automatic_employee_amount'=>'자동계산 근로자부담액','automatic_employer_amount'=>'자동계산 사용자부담액','confirmed_employee_amount'=>'확정 근로자부담액',
    'confirmed_employer_amount'=>'확정 사용자부담액','exception_reason'=>'예외사유','calculation_basis_snapshot'=>'계산기초 스냅샷','calculation_policy_version'=>'계산정책 버전',
    'failure_code'=>'실패코드','result_version'=>'결과버전','error_code'=>'오류코드','employment_insurance_application_status_code'=>'고용보험 적용상태 코드',
    'employment_insurance_decision_reason'=>'고용보험 판정사유','employment_insurance_decision_source_code_id'=>'고용보험 판정근거 코드 고유번호',
    'industrial_accident_application_status_code'=>'산재보험 적용상태 코드','industrial_accident_decision_reason'=>'산재보험 판정사유',
    'industrial_accident_decision_source_code_id'=>'산재보험 판정근거 코드 고유번호','work_type_code'=>'근로유형 코드','workday_scope_key'=>'근로일 범위키',
    'revision_scope_key'=>'개정본 범위키','period_scope_key'=>'기간 범위키','final_amount'=>'최종금액','adjustment_amount'=>'조정금액','adjustment_reason'=>'조정사유',
    'statutory_calculation_source_code_id'=>'법정계산 근거코드 고유번호','actual_application_source_code_id'=>'실제적용 근거코드 고유번호','migration_id'=>'마이그레이션 고유번호',
    'previous_snapshot'=>'변경 전 스냅샷','new_snapshot'=>'변경 후 스냅샷','decision_rule_code'=>'판정규칙 코드','decision_basis_id'=>'판정근거 고유번호',
    'verification_status_code'=>'검증상태 코드','non_taxable_item_code'=>'비과세항목 코드','applied_amount'=>'적용금액','application_reason'=>'적용사유','legal_basis'=>'법적근거',
    'calculation_details'=>'계산상세','revision_status_code'=>'개정상태 코드','item_type_code'=>'항목유형 코드','coverage_status_code'=>'적용상태 코드','evidence_type_code'=>'증빙유형 코드',
    'source_type'=>'원본유형','import_type'=>'가져오기 유형','transaction_direction'=>'거래방향','operation_type'=>'업무유형','external_key'=>'외부 식별키','evidence_date'=>'증빙일자',
    'bank_account_id'=>'은행계좌 고유번호','card_id'=>'카드 고유번호','raw_client_name'=>'원본 거래처명','evidence_status'=>'증빙상태','raw_income_year_month'=>'원본 귀속연월',
    'raw_payment_date'=>'원본 지급일','raw_recipient_name'=>'원본 소득자명','raw_service_type_code'=>'원본 용역유형 코드','raw_service_description'=>'원본 용역내용',
    'raw_business_unit'=>'원본 사업구분','raw_project_id'=>'원본 프로젝트 고유번호','raw_work_team_id'=>'원본 업무팀 고유번호','raw_gross_payment_amount'=>'원본 총지급액',
    'raw_income_tax_amount'=>'원본 사업소득세액','raw_local_income_tax_amount'=>'원본 지방소득세액','raw_other_deduction_amount'=>'원본 기타공제액',
    'raw_total_deduction_amount'=>'원본 총공제액','raw_net_payment_amount'=>'원본 최종지급액','income_date'=>'소득발생일','provider_name'=>'공급자명','provider_reg_no'=>'공급자 등록번호',
    'supply_amount'=>'공급가액','vat_amount'=>'부가가치세액','service_amount'=>'봉사료','total_amount'=>'증빙 총금액','memo'=>'비고','source_business_income_id'=>'원본 사업소득 문서 고유번호',
    'raw_line_type'=>'원본 계산항목 유형','raw_line_code'=>'원본 계산항목 코드','raw_line_name'=>'원본 계산항목명','raw_applicability_status'=>'원본 적용상태',
    'raw_calculation_base_amount'=>'원본 계산기준 금액','raw_applied_rate'=>'원본 적용률','raw_amount_before_rounding'=>'원본 반올림 전 금액','raw_rounding_method'=>'원본 반올림 방식',
    'raw_rounding_unit'=>'원본 반올림 단위','raw_calculated_amount'=>'원본 계산금액','raw_statutory_standard_revision_id'=>'원본 법정기준 개정본 고유번호','raw_sort_no'=>'원본 정렬순서',
    'line_type_code'=>'계산항목 유형코드','line_name_snapshot'=>'계산항목명 스냅샷','burden_subject_code'=>'부담주체 코드','raw_calculation_basis_amount'=>'원본 계산기초 금액',
    'raw_calculation_rate'=>'원본 계산률','raw_calculation_before_rounding'=>'원본 반올림 전 계산금액','raw_adjustment_amount'=>'원본 조정금액','raw_final_amount'=>'원본 최종금액',
    'rule_status'=>'규칙상태','repair_type'=>'복구유형','reason_code'=>'사유코드','reason_text'=>'사유내용','before_snapshot'=>'처리 전 스냅샷','after_snapshot'=>'처리 후 스냅샷',
    'changed_fields_json'=>'변경필드 내역','result_status'=>'처리결과 상태','repaired_by'=>'복구자','repaired_at'=>'복구일시','taxpayer_entity_type'=>'납세자 실체유형',
    'residency_status'=>'거주자 구분','income_recipient_type'=>'소득자 유형','withholding_policy_code'=>'원천징수정책 코드','verification_status'=>'검증상태',
    'source_type_code'=>'원본유형 코드','application_status_code'=>'적용상태 코드','rounding_method_code'=>'반올림 방식 코드','confirmation_status_code'=>'확정상태 코드',
    'work_description'=>'근로내용','taxability_code'=>'과세구분 코드',
    'status_code'=>'상태코드','effective_from'=>'효력시작일','effective_to'=>'효력종료일',
];

$tableComments = [
    'institution_employment_rules'=>'취업규칙 문서',
    'institution_employment_rules_revisions'=>'취업규칙 불변 개정본',
    'institution_employment_rules_audits'=>'취업규칙 감사이력',
];
$tableLabels = [
    'auth_logs'=>'인증로그','auth_permissions'=>'권한','auth_roles'=>'역할','auth_role_permissions'=>'역할별 권한',
    'auth_users'=>'사용자 계정','approval_personal_expenses'=>'개인경비 신청','approval_personal_expense_items'=>'개인경비 신청 품목',
];
$special = [
    'institution_daily_employment_income_accounting_links'=>[
        'evidence_id'=>'증빙 고유번호','transaction_id'=>'근로자 지급거래 고유번호','business_key_hash'=>'연결 업무키 해시','payload_hash'=>'연결 요청내용 해시',
    ],
];

$tables = $db->query("SELECT TABLE_NAME,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$columns = $db->query("SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$tableByName = array_column($tables, null, 'TABLE_NAME');
$show = [];
$manifest = [];
$alterByTable = [];
$copyAlterByTable = [];
$unmapped = [];
foreach ($columns as $column) {
    $table = (string)$column['TABLE_NAME'];
    $name = (string)$column['COLUMN_NAME'];
    $current = (string)$column['COLUMN_COMMENT'];
    if (in_array($table, $protected, true) || !$isIssue($current)) continue;
    $label = $special[$table][$name] ?? $labels[$name] ?? null;
    if ($name === 'id') {
        $base = $tableLabels[$table] ?? $tableComments[$table] ?? trim((string)($tableByName[$table]['TABLE_COMMENT'] ?? ''));
        if ($base !== '' && preg_match($korean, $base)) $label = preg_replace('/(?:테이블|원본)$/u', '', $base) . ' 고유번호';
    }
    if ($label === null) { $unmapped[] = $table . '.' . $name; continue; }
    if (!isset($show[$table])) {
        $quoted = $db->quote($table);
        $row = $db->query('SHOW CREATE TABLE ' . str_replace("'", '`', $quoted))->fetch(PDO::FETCH_NUM);
        $show[$table] = (string)($row[1] ?? '');
    }
    $pattern = '/^  `' . preg_quote($name, '/') . '` [^\r\n]+/m';
    if (!preg_match($pattern, $show[$table], $match)) { $unmapped[] = $table . '.' . $name . ':DEFINITION'; continue; }
    $definition = rtrim($match[0], ',');
    $definition = preg_replace("/ COMMENT '(?:''|[^'])*'/", '', $definition);
    $commentSql = " COMMENT '" . str_replace("'", "''", $label) . "'";
    if (preg_match('/\sCHECK\s*\(/i', $definition, $checkMatch, PREG_OFFSET_CAPTURE)) {
        $offset = $checkMatch[0][1];
        $definition = substr($definition, 0, $offset) . $commentSql . substr($definition, $offset);
    } else {
        $definition .= $commentSql;
    }
    if (preg_match('/\s(?:CHECK\s*\(|GENERATED\s+ALWAYS\s+AS\s*\()/i', $definition)) $copyAlterByTable[$table][] = 'MODIFY COLUMN ' . trim($definition);
    else $alterByTable[$table][] = 'MODIFY COLUMN ' . trim($definition);
    $manifest[] = ['domain'=>explode('_', $table)[0],'table'=>$table,'column'=>$name,'current_comment'=>$current,'confirmed_comment'=>$label,'status'=>$current===''?'MISSING':'NON_KOREAN','basis'=>'운영 DB 구조·FK·코드 사용·공식 용어','table_settings_exposure'=>'물리컬럼 기본 메타데이터','classification'=>'PHYSICAL'];
}
if ($unmapped !== []) {
    fwrite(STDERR, json_encode(['success'=>false,'unmapped'=>$unmapped], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}
$sql = "-- 운영 DB Comment SSOT Forward Migration\n-- 캘린더 보호영역 3개 테이블 제외, DML/Trigger/Procedure/Function/Event 없음\n\n";
foreach ($tableComments as $table => $comment) {
    if (isset($tableByName[$table]) && $isIssue((string)$tableByName[$table]['TABLE_COMMENT'])) {
        $sql .= "ALTER TABLE `{$table}` COMMENT='" . str_replace("'", "''", $comment) . "', ALGORITHM=INSTANT, LOCK=NONE;\n";
    }
}
foreach ($alterByTable as $table => $clauses) {
    $sql .= "ALTER TABLE `{$table}`\n  " . implode(",\n  ", $clauses) . ",\n  ALGORITHM=INSTANT, LOCK=NONE;\n\n";
}
foreach ($copyAlterByTable as $table => $clauses) {
    $sql .= "ALTER TABLE `{$table}`\n  " . implode(",\n  ", $clauses) . ",\n  ALGORITHM=COPY, LOCK=SHARED;\n\n";
}
$manifestPayload = ['generated_at'=>date(DATE_ATOM),'schema'=>(string)$db->query('SELECT DATABASE()')->fetchColumn(),'protected_tables'=>$protected,'table_changes'=>$tableComments,'column_changes'=>$manifest];
file_put_contents(PROJECT_ROOT . '/app/migrations/20260903_14_complete_database_korean_comments.up.sql', str_replace("\r\n", "\n", $sql));
file_put_contents(PROJECT_ROOT . '/app/migrations/20260903_14_complete_database_korean_comments.down.sql', "-- Comment SSOT는 의미 데이터이므로 자동 역변환하지 않는다.\n-- 복구는 적용 전 Manifest와 SHOW CREATE TABLE 백업을 사용한다.\n");
file_put_contents(PROJECT_ROOT . '/docs/projects/DatabaseCommentSsotManifest20260903.json', json_encode($manifestPayload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode(['success'=>true,'table_changes'=>count($tableComments),'column_changes'=>count($manifest),'migration'=>'20260903_14_complete_database_korean_comments.up.sql'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
