import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const paths = {
    regular: 'public/assets/js/pages/institution/regular-employment-income/index.js',
    daily: 'public/assets/js/pages/institution/daily-employment-income/index.js',
    business: 'public/assets/js/pages/institution/business-income/index.js',
};
const sources = Object.fromEntries(await Promise.all(Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, 'utf8')])));

for (const [key, source] of Object.entries(sources)) {
    assert.match(source, /isIncomeWithholdingDate/);
    assert.match(source, /withholdingDateInput\.addEventListener\('change'/);
    assert.match(source, /원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다\./);
}
assert.match(sources.regular, /items=await calculateItems\(yearMonthValue\.value,items\)/);
assert.match(sources.daily, /invalidateDocumentCalculation\(\{ resetDraftAdjustments: false \}\)/);
assert.match(sources.daily, /&& isIncomeWithholdingDate\(withholdingDateInput\.value\)/);
assert.match(sources.daily, /function calculationMissingInputs\(\)/);
assert.match(sources.daily, /function documentMissingInputs\(\)/);
assert.match(sources.daily, /자동계산에 필요한 필수값을 입력해 주세요\./);
assert.match(sources.daily, /group\.work_description = groupDescription\.value;[\s\S]{0,180}scheduleAutoCalculation\(\{ immediate: true \}\)/);
assert.match(sources.business, /settlementText=value=>calculated&&isIncomeWithholdingDate/);
assert.match(sources.business, /원천징수일 기준 법정기준/);

console.log('OK: 소득자료 원천징수일 변경·공란·자동재계산 계약');
