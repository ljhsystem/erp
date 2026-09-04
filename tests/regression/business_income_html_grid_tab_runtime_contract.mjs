import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const source = fs.readFileSync(path.join(root, 'public/assets/js/pages/institution/business-income/index.js'), 'utf8');
const style = fs.readFileSync(path.join(root, 'public/assets/css/pages/institution/business-income/index.css'), 'utf8');
const service = fs.readFileSync(path.join(root, 'app/Services/Institution/BusinessIncomeService.php'), 'utf8');
const calculationService = fs.readFileSync(path.join(root, 'app/Services/Institution/BusinessIncomeCalculationService.php'), 'utf8');
const changedStart = source.indexOf("grid.on('cell:changed'");
const changedEnd = source.indexOf('grid.render();renderAdjustmentDetail()', changedStart);
const changedHandler = source.slice(changedStart, changedEnd);
const invalidationStart = source.indexOf('function invalidateWorkLineCell()');
const invalidationEnd = source.indexOf('function renderItem(', invalidationStart);
const workLineInvalidation = source.slice(invalidationStart, invalidationEnd);

const checks = [
    ['사업소득 외주 작업내역 cell:changed Handler가 존재한다', changedStart >= 0 && changedEnd > changedStart],
    ['셀 변경은 키보드 이동 전용 무렌더 갱신을 사용한다', changedHandler.includes('invalidateWorkLineCell()')],
    ['셀 변경 중 전체 invalidate를 호출하지 않는다', !changedHandler.includes('invalidate();')],
    ['셀 변경 중 활성 셀을 덮어쓰는 행 전체 updateRow를 호출하지 않는다', !changedHandler.includes('grid.updateRow(')],
    ['금액과 확정금액은 해당 표시 셀만 부분 갱신한다', changedHandler.includes("['calculated_amount',line.calculated_amount]") && changedHandler.includes("['final_amount',line.final_amount]")],
    ['키보드 이동 전용 갱신은 전체 render를 호출하지 않는다', invalidationStart >= 0 && !workLineInvalidation.includes('render();')],
    ['키보드 이동 전용 갱신도 자동계산 예약을 유지한다', workLineInvalidation.includes('scheduleAutoCalculation();')],
    ['자동 계산 대기 Badge를 노출하지 않는다', !source.includes("'자동 계산 대기'")],
    ['외주 작업내역 입력 이벤트가 즉시 금액과 우측 집계를 갱신한다', source.includes("groupsHost.addEventListener('input'") && source.includes('renderLiveAmounts();syncActions();scheduleAutoCalculation();')],
    ['자동계산은 지연 없이 예약한다', source.includes("window.setTimeout(()=>{calculationTimer=null;calculateAutomatically();},0)")],
    ['자동계산 응답은 편집 Grid 전체를 재생성하지 않는다', source.includes('renderLiveAmounts();syncActions();')],
    ['증감 입력도 change 대기 없이 즉시 집계한다', source.includes("querySelectorAll('[data-adjustment-field]').forEach(input=>input.addEventListener('input'") && source.includes('invalidateWorkLineCell();')],
    ['서버 계산 Line을 소득자 상태에 보존한다', source.includes('lines:Array.isArray(result.lines)?result.lines.map')],
    ['공용 기관별 원천징수 계산카드를 렌더링한다', source.includes('renderIncomeCalculationCards(cards,businessIncomeInstitutionDto(item))')],
    ['문서 합계는 현재 소득자 상태에서 산출한다', source.includes('function renderTotals(){const totals=documentDetailTotals()')],
    ['소득자·그룹·문서 3단계 집계를 제공한다', source.includes('business-income-item__totals') && source.includes('business-income-group-totals') && source.includes('business-income-preview-grid')],
    ['외주 작업내역 컬럼 순서와 명칭을 유지한다', source.indexOf("key:'calculated_amount',label:physicalLabel('workLine','calculated_amount','금액')") < source.indexOf("key:'adjustment_action',label:'증감'") && source.indexOf("key:'adjustment_action',label:'증감'") < source.indexOf("key:'final_amount',label:physicalLabel('workLine','final_amount','확정금액')") && source.indexOf("key:'final_amount',label:physicalLabel('workLine','final_amount','확정금액')") < source.indexOf("key:'row_action',label:'관리'")],
    ['증감과 관리 Editor를 분리한다', source.includes("'business-income-work-adjustment':adjustmentEditor") && source.includes("'business-income-work-action':actionEditor")],
    ['증감 상세에는 중복 확정금액 입력을 표시하지 않는다', !source.includes('data-adjustment-final')],
    ['외주 작업내역은 100% 비율 컬럼으로 가로 스크롤을 방지한다', ['5','21','15','8','8','10','10','6','10','7'].reduce((sum, width) => sum + Number(width), 0) === 100 && style.includes('overflow-x: hidden') && source.includes('columnResize:false')],
    ['비율 너비는 셀에만 적용하고 입력기는 셀 전체 너비를 사용한다', style.includes(':is(.html-grid-header-cell, .html-grid-cell)[data-column-key="item_name"]') && style.includes('.html-grid-cell-editor-slot { width: 100% !important; min-width: 0 !important; max-width: none !important; }')],
    ['기관별 원천징수 카드는 일용근로소득과 동일한 데스크톱 4열을 사용한다', style.includes('.business-income-institution-detail .income-calculation-card-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }')],
    ['단위 Select2는 인접 입력필드와 동일한 32px 높이를 사용한다', style.includes('.select2-container { display: block; width: 100% !important; height: 32px !important; min-height: 32px !important; }') && style.includes('.select2-selection--single { height: 32px !important; min-height: 32px !important;')],
    ['사업구분 Select2 선택은 프로젝트·작업팀 정책을 즉시 다시 렌더링한다', source.includes("select2:select.businessIncomeBusiness") && source.includes("group.project_id='';") && source.includes("group.work_team_id='';") && source.includes('commitBusinessUnit(event.params?.data?.id??select.value)')],
    ['사업구분별 프로젝트·작업팀 목록은 정규화된 코드로 필터링한다', source.includes('referenceKey(row.business_unit)===businessUnitKey')],
    ['자동계산 요청은 귀속연월을 서버에 전달한다', source.includes("JSON.stringify({income_year_month:form.elements.income_year_month.value,groups})")],
    ['사업소득 법정기준은 귀속연월 말일 기준으로 조회한다', service.includes("return $date->format('Y-m-t');") && service.includes('$this->calculator->calculate($statutoryReferenceDate,$gross)') && calculationService.includes('resolve(self::STANDARD_TYPE, $statutoryReferenceDate)')],
    ['계산 해시는 귀속연월을 포함해 법정기준 변경을 보호한다', service.includes("$canonical=['income_year_month'=>$incomeYearMonth,'groups'=>[]]") && service.includes('$this->sourceHash($groups,$incomeYearMonth)')],
    ['원천징수 카드에는 귀속연월 기준을 표시한다', source.includes("standardLabel:line.statutory_standard_revision_id?'귀속연월 기준 법정기준'")],
];

for (const [label, passed] of checks) {
    if (!passed) {
        console.error(`FAIL: ${label}`);
        process.exitCode = 1;
    } else {
        console.log(`PASS: ${label}`);
    }
}
