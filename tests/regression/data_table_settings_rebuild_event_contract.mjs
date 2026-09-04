import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');
const common = fs.readFileSync(path.join(root, 'public/assets/js/common/table/data-table.js'), 'utf8');
const pageFiles = [
    'public/assets/js/pages/institution/employment-contract/table.js',
    'public/assets/js/pages/institution/job-assignment/index.js',
    'public/assets/js/pages/institution/personnel-action/index.js',
    'public/assets/js/pages/institution/regular-employment-income/index.js',
    'public/assets/js/pages/main/settings/statutory-standards/index.js',
    'public/assets/js/pages/funds/bank-transactions/index.js',
    'public/assets/js/pages/ledger/transaction/table.js',
];

const tbodyDelegationPages = pageFiles.filter(relative => {
    const source = fs.readFileSync(path.join(root, relative), 'utf8');
    return source.includes("addEventListener('dblclick'") || source.includes('addEventListener("dblclick"');
});

const checks = {
    capturesExistingTbody: common.includes("const preservedTbody = tableElement.tBodies?.[0]"),
    preservesListenerNode: common.includes('preservedTbody?.replaceChildren();')
        && common.includes('tableElement.replaceChildren(...(preservedTbody ? [preservedTbody] : []));'),
    keepsApiReference: common.includes('syncDataTableApiReference(table, rebuiltTable);'),
    affectedPagesCovered: tbodyDelegationPages.length >= 6,
};
const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([key]) => key);
if (failed.length > 0) throw new Error(`TableSettings 이벤트 보존 계약 실패: ${failed.join(', ')}`);

console.log(JSON.stringify({ success: true, tbodyDelegationPages, checks }, null, 2));
