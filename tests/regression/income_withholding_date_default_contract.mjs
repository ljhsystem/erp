import assert from 'node:assert/strict';
import { incomeWithholdingDate, INCOME_WITHHOLDING_RULES, isIncomeWithholdingDate } from '../../public/assets/js/common/income-withholding-date.js';

assert.equal(incomeWithholdingDate('2013-08', INCOME_WITHHOLDING_RULES.REGULAR), '2013-09-11');
assert.equal(incomeWithholdingDate('2013-08', INCOME_WITHHOLDING_RULES.MONTH_END), '2013-09-30');
assert.equal(incomeWithholdingDate('2024-04', INCOME_WITHHOLDING_RULES.REGULAR), '2024-05-10');
assert.equal(incomeWithholdingDate('2024-02', INCOME_WITHHOLDING_RULES.MONTH_END), '2024-03-29');
assert.equal(incomeWithholdingDate('invalid', INCOME_WITHHOLDING_RULES.REGULAR), '');
assert.equal(isIncomeWithholdingDate('2024-02-29'), true);
assert.equal(isIncomeWithholdingDate('2024-02-30'), false);
assert.equal(isIncomeWithholdingDate(''), false);

console.log('OK: 소득자료 원천징수일 회사 기본값 계약');
