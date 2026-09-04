import fs from 'node:fs';

const page = fs.readFileSync('public/assets/js/pages/institution/daily-employment-income/index.js', 'utf8');
const common = fs.readFileSync('public/assets/js/common/datatable/dataTableFormSettings.js', 'utf8');
const policy = fs.readFileSync('app/Services/Institution/DailyEmploymentIncomeFieldPolicyService.php', 'utf8');
const service = fs.readFileSync('app/Services/Institution/DailyEmploymentIncomeService.php', 'utf8');
const view = fs.readFileSync('app/views/institution/daily-employment-income/index.php', 'utf8');

const checks = {
    modalUsesCommonFormSettings: page.includes('createDataTableFormSettings({ form'),
    normalizedSettingsEventKey: page.includes('datatable.settings.${TABLE_SETTINGS_KEY}'),
    modalReappliesBeforeOpenAndSave: page.includes('applyFormSettings(); renderGroups(); syncWorkflowActions(); modal.show();')
        && page.includes('if (!form.reportValidity()) return;'),
    commonAppliesDisplayName: common.includes('replaceLabelText(label, displayName);'),
    commonAppliesRequirement: common.includes('control.required = required;'),
    serverReadsSamePageSettings: policy.includes("private const PAGE_KEY = 'institution.income-data.daily-employment';"),
    serverValidatesConfiguredRequired: service.includes('$this->fieldPolicy->validateRequiredFields($input);'),
    everyHeaderFieldHasExplicitMapping: ['income_year_month', 'document_title']
        .every(key => view.includes(`data-table-settings-field="${key}"`)),
    documentNumberIsNotUsed: !view.includes('document_number') && !view.includes('dailyIncomeDocumentNumber') && !view.includes('문서번호') && !page.includes('document_number') && !page.includes('dailyIncomeDocumentNumber'),
};

const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([name]) => name);
console.log(JSON.stringify({ success: failed.length === 0, checks, failed }, null, 2));
process.exit(failed.length === 0 ? 0 : 1);
