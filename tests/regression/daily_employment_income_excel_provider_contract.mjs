import fs from 'node:fs';
import assert from 'node:assert/strict';

const manager = fs.readFileSync('public/assets/js/components/excel-manager.js', 'utf8');
const provider = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/excel-provider.js', 'utf8');
const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');

assert.match(manager, /form\.__excelProvider\?\.downloadData/);
assert.match(manager, /form\.__excelProvider\?\.prepareUpload/);
assert.match(manager, /form\.__excelProvider\?\.handleUploadResponse/);
assert.match(provider, /createExcelManagerSettingsCore/);
assert.match(provider, /body\.set\('groups', JSON\.stringify\(config\.getGroups\(\)\)\)/);
assert.match(provider, /config\.applyPreview\(preview\.groups \|\| \[\]\)/);
assert.doesNotMatch(page, /dailyIncomeExcelUpload'\)\.addEventListener/);
assert.doesNotMatch(page, /dailyIncomeExcelDownload'\)\.addEventListener/);
assert.match(page, /referenceKey\(row\.business_unit\) === referenceKey\(group\.business_unit\)/);

console.log('PASS: 일용근로소득은 공용 ExcelManager 한 개와 도메인 Provider 계약을 사용합니다.');
