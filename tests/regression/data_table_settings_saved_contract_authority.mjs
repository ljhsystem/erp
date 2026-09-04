import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');
const source = fs.readFileSync(
    path.join(root, 'public/assets/js/common/datatable/dataTableSettings.js'),
    'utf8'
);

const checks = {
    detectsPersistedContract: source.includes('const hasPersistedColumnContract = ['),
    newDefaultsOnlyWhenAbsentFromSavedContract: source.includes('const parsedContractKeySet = new Set(parsedKeys);')
        && source.includes('if (!hasPersistedColumnContract || !parsedContractKeySet.has(key))'),
    requiredColumnsRemainVisible: source.includes('(defaults.requiredColumns || []).forEach((key) => visibleSet.add(key));'),
};
const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([key]) => key);
if (failed.length > 0) throw new Error(`저장 TableSettings 우선계약 실패: ${failed.join(', ')}`);

console.log(JSON.stringify({ success: true, checks }, null, 2));
