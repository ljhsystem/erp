import assert from 'node:assert/strict';
import { resetWorkerCalculationState } from '../../public/assets/js/pages/institution/daily-employment-income/worker-instance-state.js';

const makeItem = () => ({
    calculation: { total_payment_amount: 100000 },
    calculation_source_key: 'old',
    calculation_state: 'ready',
    calculation_error: '',
    calculation_request_version: 1,
    institution_line_overrides: [{ line_code: 'EMPLOYMENT_INSURANCE', final_amount: 3000 }],
    workdays: new Map([['2013-08-14', {
        work_date: '2013-08-14',
        actual_work_minutes: 480,
        institution_line_overrides: [{ line_code: 'DAILY_WORKER_INCOME_TAX', final_amount: 0 }],
    }]]),
});

const preserved = makeItem();
resetWorkerCalculationState(preserved);
assert.equal(preserved.institution_line_overrides.length, 1, '일반 상태 초기화는 명시적인 수동 조정을 보존해야 합니다.');
assert.equal(preserved.workdays.get('2013-08-14').institution_line_overrides.length, 1);

const reset = makeItem();
resetWorkerCalculationState(reset, { resetDraftAdjustments: true });
assert.equal(reset.calculation, null);
assert.deepEqual(reset.institution_line_overrides, []);
assert.deepEqual(reset.workdays.get('2013-08-14').institution_line_overrides, []);
assert.equal(reset.workdays.get('2013-08-14').actual_work_minutes, 480, '기준 변경 후에도 신규 Workday 기본 8시간은 유지해야 합니다.');

console.log(JSON.stringify({ success: true, scenarios: ['manual-preserve', 'basis-change-reset', '480-minutes-preserve'] }));
