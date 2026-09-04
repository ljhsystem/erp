import fs from 'node:fs';

const source = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/worker-cards.js', 'utf8');
const checks = [
    ['작업자는 공용 AJAX Select2를 사용한다', source.includes("PickerSelect2.createAjax(select") && source.includes("option_type: 'worker'")],
    ['작업자와 공종은 일용근로소득 권한 경계의 options API를 사용한다', (source.match(/\/api\/institution\/income-data\/daily-employment\/options/g) || []).length >= 2 && !source.includes('/api/settings/base-info/client/search-picker') && !source.includes('/api/settings/system/code/list')],
    ['작업자는 거래처유형으로 임의 제한하지 않는다', !source.includes("client_type: 'DAILY_WORKER'")],
    ['공종 Picker는 페이지네이션 응답을 처리한다', source.includes("option_type: 'work_type'") && source.includes("pagination: { more: data.data?.has_more === true }")],
    ['작업자 추가는 거래처 원본 퀵모달을 사용한다', source.includes('openClientQuickCreate({ select')],
    ['공종 추가는 코드관리 원본 퀵모달을 사용한다', source.includes("openCodeQuickModal({ codeGroup: 'WORK_TYPE'")],
    ['공용 목록은 서버 페이지네이션을 사용한다', source.includes("page: params.page || 1")],
    ['작업자·공종 선택 ID와 표시명을 Item에 함께 보존한다', source.includes('selectedData.client_name || text') && source.includes('item.work_type_name = value ? text')],
    ['재렌더 초기화 이벤트는 작업자·공종 Item을 덮어쓰지 않는다', source.includes("select.dataset.pickerReady !== 'true'") && source.includes("select.dataset.pickerReady = 'true'")],
    ['Picker Destroy 이벤트도 작업자·공종 Item을 덮어쓰지 않는다', source.includes("select.dataset.pickerReady = 'false';") && source.includes('PickerSelect2.destroy(select)')],
    ['작업자 배지는 거래처유형 원본값 또는 미등록을 표시한다', source.includes('workerReference?.client_type_name') && source.includes("'거래처유형미등록'") && !source.includes("clientType.textContent = '일용근로자'")],
    ['작업자 선택 직후 Header 이름과 거래처유형을 부분 갱신한다', source.includes('updateReferenceHeader(select, item, kind, options') && source.includes("card.querySelector('.daily-income-worker-select')") && source.includes("card.querySelector('.daily-income-worker-type')")],
    ['Select2 확정 선택 이벤트로 작업자·공종 Header를 갱신한다', source.includes('select2:select.dailyIncomeWorkerHeader') && source.includes('commitSelection(nextValue')],
    ['공종과 작업내용도 Header에 상태형 문구로 즉시 반영한다', source.includes("card.querySelector('.daily-income-worker-work-type')") && source.includes("querySelector('.daily-income-worker-description')") && source.includes("headerDescription.textContent = `작업내용: ${item.work_description.trim() || '미입력'}`")],
    ['전체선택 버튼은 현재 Workday 상태로 선택·해제를 매번 판정한다', source.includes('const shouldClear = options.dates.length > 0') && source.includes("toggleAll.textContent = allSelected ? '전체선택해제' : '전체선택'") && source.includes('toggleAll.dataset.toggleAllWorkdays')],
    ['접기·펼치기는 Picker를 재생성하지 않는다', source.includes("card.classList.toggle('is-collapsed', item.collapsed)")],
    ['날짜 변경은 작업자·공종 Picker를 유지하고 근무일 영역만 재렌더링한다', source.includes("workdayBody.className = 'daily-income-worker-workdays'") && source.includes('this.renderFields(body, group, item, options, renderWorkdays)') && source.includes('workdayBody.replaceChildren()')],
    ['날짜 Grid 관리열을 사용하지 않는다', !source.includes("key: 'management'")],
    ['근로시간과 적용단가를 일괄 적용한다', source.includes('선택근무일 일괄입력') && source.includes('일괄 적용')],
    ['일일내역은 비과세 적용사유만 표시하고 별도 근거자료 열을 두지 않는다', source.includes("key: 'non_taxable_reason'") && !source.includes("key: 'non_taxable_evidence'")],
    ['일일내역 컬럼은 근무일·요일·실제근로시간·단가·과세증감·비과세증감·비과세 적용사유·산정내역 순서다', source.indexOf("key: 'work_date'") < source.indexOf("key: 'weekday'") && source.indexOf("key: 'weekday'") < source.indexOf("key: 'actual_work_minutes'") && source.indexOf("key: 'actual_work_minutes'") < source.indexOf("key: 'daily_rate_amount'") && source.indexOf("key: 'daily_rate_amount'") < source.indexOf("key: 'taxable_additional_amount'") && source.indexOf("key: 'taxable_additional_amount'") < source.indexOf("key: 'non_taxable_additional_amount'") && source.indexOf("key: 'non_taxable_additional_amount'") < source.indexOf("key: 'non_taxable_reason'") && source.indexOf("key: 'non_taxable_reason'") < source.indexOf("key: 'calculation_note'")],
    ['날짜 선택 재렌더링은 스크롤을 복원한다', source.includes('captureScrollState') && source.includes('render({ preserveScroll: true })')],
    ['날짜별 상세는 5건씩 추가 표시한다', source.includes('workdayVisibleCount') && source.includes('날짜별 상세 ${Math.min(5') && source.includes('visibleCount + 5')],
    ['단가와 증감 0원은 빈 셀로 표시한다', source.includes('daily_rate_amount: number(day.daily_rate_amount) || null') && source.includes('taxable_additional_amount: number(day.taxable_additional_amount) || null')],
    ['날짜별 열은 산정내역을 넓히고 지급액을 축소한 명시 너비를 사용한다', source.includes("key: 'work_date', label: '근무일', editable: false, width: 90") && source.includes("key: 'weekday', label: '요일', editable: false, width: 46") && source.includes("key: 'non_taxable_reason', label: '비과세 적용사유', editor: 'text', width: 150") && source.includes("key: 'calculation_note', label: '산정내역', editor: 'text', width: 215") && source.includes("key: 'payment_amount', label: '지급액', type: 'number', formatter: 'number', editable: false, width: 80")],
];
let failed = false;
for (const [label, passed] of checks) {
    console.log(`${passed ? 'PASS' : 'FAIL'}: ${label}`);
    failed ||= !passed;
}
if (failed) process.exit(1);
