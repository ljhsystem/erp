import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..', '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const common = read('public/assets/js/common/datatable/dataTableFormSettings.js');
const controls = read('public/assets/js/pages/institution/employment-contract/modal-form-controls.js');
const service = read('app/Services/Institution/EmploymentContractService.php');
const fieldPolicy = read('app/Services/Institution/EmploymentContractFieldPolicyService.php');
const metaService = read('app/Services/System/DataTableColumnMetaService.php');
const table = read('public/assets/js/pages/institution/employment-contract/table.js');
const rules = read('AGENTS.md');

const assertions = {
    commonFormAdapter: common.includes('export function createDataTableFormSettings'),
    configuredLabel: common.includes('resolveDataTableColumnDisplayName'),
    requiredMarker: common.includes("policy === POLICY.REQUIRED ? 'text-danger' : 'text-primary'"),
    dataTableVisibilityIsolated: !common.includes('visibleColumns.has(key)')
        && !common.includes('container.hidden ='),
    requirementDoesNotForceVisibility: !common.includes('business.forceVisible'),
    siteDoesNotRequireProject: !controls.includes("locationType === 'PROJECT'"),
    otherStableCode: controls.includes("locationType === 'OTHER'"),
    conditionalProjectRequired: controls.includes('return { required };'),
    settingsUpdateListener: read('public/assets/js/pages/institution/employment-contract/modal-runtime.js')
        .includes("document.addEventListener('datatable-settings:updated'"),
    serverConfiguredRequired: service.includes('$this->fieldPolicy->validateRequiredFields($input);'),
    approvalConfiguredRequired: service.includes('$this->fieldPolicy->validateRequiredFields($contract);'),
    serverSiteProjectGuardRemoved: !service.includes("($contract['work_location_type'] ?? '') === 'PROJECT'"),
    projectCompletionGuardKept: service.includes("$fixedTermReasonCode === 'PROJECT_COMPLETION' && $projectId === null"),
    userSettingsPolicyService: fieldPolicy.includes("columnRequirementPolicy"),
    projectHeaderUsesMetadataKey: table.includes("data: 'project_name', title: '특정 프로젝트', settingsKey: 'project_id'"),
    projectMetadataUsesPhysicalKey: metaService.includes("'project_id' => ['project_id', '특정 프로젝트']"),
    employeeHeaderUsesMetadataKey: table.includes("{ data: 'employee_name', title: '직원명'")
        && !table.includes("data: 'employee_name', settingsKey:"),
    projectRuleDocumented: rules.includes('보기 설정(`visibleColumns`)은 DataTable 목록에만 적용'),
};

const failed = Object.entries(assertions).filter(([, passed]) => !passed).map(([key]) => key);
if (failed.length > 0) {
    throw new Error(`근로계약 TableSettings Form 계약 실패: ${failed.join(', ')}`);
}

console.log(JSON.stringify({ success: true, assertions }, null, 2));
