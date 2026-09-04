import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const common = fs.readFileSync(path.join(root, 'public/assets/js/common/table/data-table.js'), 'utf8');
const contract = fs.readFileSync(path.join(root, 'public/assets/js/pages/institution/employment-contract/table.js'), 'utf8');

const checks = {
    employmentContractDefersSchemaRebuild: contract.includes('deferSchemaRebuild: true'),
    saveDoesNotAwaitDeferredRebuild: common.includes('settingsApi.pendingSchemaRebuildTimer = window.setTimeout(async () => {')
        && common.includes('await rebuildDataTableFromSettings(table, config);')
        && common.includes('}, 0);\n                return true;'),
    repeatedSaveCoalescesRebuild: common.includes('window.clearTimeout(settingsApi.pendingSchemaRebuildTimer)'),
};

const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([key]) => key);
console.log(JSON.stringify({ success: failed.length === 0, checks, failed }, null, 2));
process.exit(failed.length === 0 ? 0 : 1);
