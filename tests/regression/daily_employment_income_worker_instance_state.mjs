import assert from 'node:assert/strict';
import {
    calculationTotals,
    copyWorkerInstance,
    resetWorkerCalculationState,
    selectionAfterDelete,
    workerCalculationSourceKey,
} from '../../public/assets/js/pages/institution/daily-employment-income/worker-instance-state.js';

let sequence = 0;
const key = () => `card-${++sequence}`;
const workdays = new Map(Array.from({ length: 5 }, (_, index) => {
    const date = `2013-08-${String(index + 6).padStart(2, '0')}`;
    return [date, { work_date: date, actual_work_minutes: 480, daily_rate_amount: 90000, taxable_additional_amount: index === 4 ? 2940 : 0, non_taxable_additional_amount: 0, calculation_note: '산정 근거' }];
}));
const original = {
    id: 'db-item-must-not-copy', client_key: key(), worker_client_id: 'worker-1', worker_name: '정순옥',
    work_type_code: 'COMMON', work_description: '일용 작업', workdays,
    calculation: { summary: { total_work_days: 5, total_gross_amount: 452940, total_deduction_amount: 2940, total_net_payment_amount: 450000, total_employer_burden_amount: 0 } },
};
const firstCopy = copyWorkerInstance(original, key);
const secondCopy = copyWorkerInstance(original, key);

assert.equal(new Set([original.client_key, firstCopy.client_key, secondCopy.client_key]).size, 3, '복사 카드는 고유 키를 가져야 한다.');
assert.equal(firstCopy.id, undefined, 'DB Item ID를 복사하면 안 된다.');
assert.equal(firstCopy.calculation, null, '계산결과를 복사하면 안 된다.');
assert.notEqual(firstCopy.workdays, original.workdays, 'Workday Map은 독립 인스턴스여야 한다.');
assert.equal(new Set([...firstCopy.workdays.values()].map(day => day.client_key)).size, 5, '복사 Workday는 각각 고유 임시 키를 가져야 한다.');
assert.notEqual(firstCopy.workdays.get('2013-08-06').client_key, secondCopy.workdays.get('2013-08-06').client_key, '복사 카드 간 Workday 키가 충돌하면 안 된다.');

[firstCopy, secondCopy].forEach(item => { item.calculation = structuredClone(original.calculation); });
const group = { client_key: 'group-1', business_unit: 'HEAD_OFFICE', project_id: null, work_team_id: null, items: [original, firstCopy, secondCopy] };
const totals = calculationTotals([group]);
assert.deepEqual(
    { item: totals.item_count, unique: totals.unique_worker_count, duplicate: totals.duplicate_item_count, days: totals.total_work_days, gross: totals.total_gross_amount },
    { item: 3, unique: 1, duplicate: 2, days: 15, gross: 1358820 },
);

const noteSource = workerCalculationSourceKey(group, firstCopy);
firstCopy.workdays.get('2013-08-06').calculation_note = '표현만 변경';
assert.equal(workerCalculationSourceKey(group, firstCopy), noteSource, '산정내역은 계산 source를 바꾸면 안 된다.');
firstCopy.workdays.get('2013-08-06').daily_rate_amount = 91000;
assert.notEqual(workerCalculationSourceKey(group, firstCopy), noteSource, '단가 변경은 계산 source를 바꿔야 한다.');

const retainedWorkdays = firstCopy.workdays;
firstCopy.coverage_id = 'coverage-old';
resetWorkerCalculationState(firstCopy);
assert.equal(firstCopy.calculation, null);
assert.equal(firstCopy.coverage_id, undefined);
assert.equal(firstCopy.workdays, retainedWorkdays, '작업자 변경 시 입력 Workday는 보존한다.');
assert.equal(selectionAfterDelete([original, secondCopy], 1), original, '선택 카드 삭제 후 이전 카드를 우선 선택한다.');

console.log('daily employment income worker instance state: ok');
