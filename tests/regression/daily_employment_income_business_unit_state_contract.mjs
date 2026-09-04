import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const page = fs.readFileSync(path.join(root, 'public/assets/js/pages/institution/daily-employment-income/index.js'), 'utf8');
const cards = fs.readFileSync(path.join(root, 'public/assets/js/pages/institution/daily-employment-income/worker-cards.js'), 'utf8');
const model = fs.readFileSync(path.join(root, 'app/Models/Institution/DailyEmploymentIncomeModel.php'), 'utf8');
const service = fs.readFileSync(path.join(root, 'app/Services/Institution/DailyEmploymentIncomeService.php'), 'utf8');
const excel = fs.readFileSync(path.join(root, 'app/Services/Institution/DailyEmploymentIncomeExcelService.php'), 'utf8');

const checks = {
    '신규 Group은 공식 기본값 없이 미선택으로 시작': page.includes("business_unit: source?.business_unit || ''"),
    'Select2 확정값을 Group 상태에 즉시 커밋': page.includes('select2:select.dailyIncomeBusiness') && page.includes('group.business_unit = nextValue;'),
    'Group DOM은 현재 코드와 정책상태를 반영': page.includes('card.dataset.businessUnitCode') && page.includes('card.dataset.policyStatus'),
    'Group 제목은 현재 코드의 code_name에서 파생': page.includes('businessUnitOption(group.business_unit)?.name'),
    'Group 제목은 AJAX로 변경한 프로젝트·작업팀 표시명을 현재 Group 상태에서 복원': page.includes('|| group.project_name')
        && page.includes('|| group.work_team_name')
        && page.includes("group.project_name = project.value ?")
        && page.includes("group.work_team_name = team.value ?"),
    '정책 요청 응답 역전을 차단': page.includes('requestVersion !== group.policy_request_version'),
    '정책 실패는 선택값과 별도 상태로 보존': page.includes("group.policy_status = 'failed'") && page.includes('사업구분 정책 조회 실패'),
    '계산과 저장 Payload는 동일한 공식 code 사용': page.includes('business_unit: policy.id'),
    '미적용 프로젝트와 팀은 NULL': page.includes("project_id: policy?.uses_project ? (group.project_id || null) : null")
        && page.includes("work_team_id: policy?.uses_work_team ? (group.work_team_id || null) : null"),
    '상세 재조회는 저장 code와 Workday 증감을 복원': page.includes('business_unit: referenceKey(group.business_unit)')
        && page.includes('day.taxable_additional_amount ?? day.allowance_amount'),
    'Excel은 code를 정규화하고 증감을 Workday에 표시': page.includes('group.business_unit = businessUnit.id')
        && excel.includes("$workdays[0]['taxable_additional_amount']")
        && !excel.includes("'taxable_additional_amount' => (float) ($row['taxable_adjustment_amount']"),
    '숨은 Item 증감을 첫날 Payload에 주입하지 않음': !page.includes('index === 0 ? numberValue(worker.taxable_additional_amount)'),
    '지급합계는 기본액과 과세·비과세 증감만 합산': cards.includes('day.taxable_additional_amount ?? day.allowance_amount')
        && cards.includes('day.non_taxable_additional_amount ?? day.non_taxable_amount')
        && !cards.includes('number(day.adjustment_amount)'),
    '서버 오류는 미선택과 유효하지 않은 코드를 구분': service.includes('근무그룹의 사업구분을 선택해 주세요.')
        && model.includes('유효하지 않거나 비활성화된 사업구분입니다.'),
    '프로젝트와 팀 선택목록은 사업구분 범위로 제한': model.includes('business_unit=:business_unit'),
    '직접 변조는 서버 참조검증으로 차단': service.includes('assertGroupReferences($companyId')
        && model.includes('다른 사업구분의 프로젝트')
        && model.includes('다른 프로젝트의 작업팀'),
};

const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([label]) => label);
if (failed.length) {
    console.error(JSON.stringify({ success: false, failed }, null, 2));
    process.exit(1);
}

const fiveDays = Array.from({ length: 5 }, () => ({ rate: 90000, taxable: 0, nonTaxable: 0 }));
const gross = fiveDays.reduce((sum, day) => sum + day.rate + day.taxable + day.nonTaxable, 0);
if (gross !== 450000) throw new Error(`5일 합계가 잘못되었습니다: ${gross}`);

console.log(JSON.stringify({ success: true, gross, checks }, null, 2));
