import assert from 'node:assert/strict';
import fs from 'node:fs';
import { preparePeriodRenewalDraft } from '../public/assets/js/pages/main/settings/statutory-standards/period-renewal.js';

const pageSource = fs.readFileSync(new URL('../public/assets/js/pages/main/settings/statutory-standards/index.js', import.meta.url), 'utf8');
const viewSource = fs.readFileSync(new URL('../app/views/main/settings/statutory-standards/standards.php', import.meta.url), 'utf8');
const resolverSource = fs.readFileSync(new URL('../app/Services/System/StatutoryStandardResolver.php', import.meta.url), 'utf8');
const modelSource = fs.readFileSync(new URL('../app/Models/System/StatutoryStandardModel.php', import.meta.url), 'utf8');
const serviceSource = fs.readFileSync(new URL('../app/Services/System/StatutoryStandardService.php', import.meta.url), 'utf8');
const sourceModelSource = fs.readFileSync(new URL('../app/Models/System/StatutoryStandardSourceModel.php', import.meta.url), 'utf8');
const searchFormSource = fs.readFileSync(new URL('../public/assets/js/common/table/search-form.js', import.meta.url), 'utf8');
assert.match(pageSource, /confirmDialog\(\{/);
assert.match(pageSource, /bindModalCardCollapses\(modalElement, \{ resetOnShow: true \}\)/);
assert.match(viewSource, /data-ui-modal-card-collapse data-bs-target="#statutorySystemInfoCollapse"/);
assert.doesNotMatch(pageSource, /bindSystemCardToggle|window\.confirm/);
assert.match(pageSource, /form\.elements\.id\.value = ''/);
assert.match(pageSource, /preparePeriodRenewalDraft\(\{ values, sources \}\)/);
assert.doesNotMatch(pageSource, /scope_data|scope_fields|data-scope-key|적용조건/);
assert.doesNotMatch(viewSource, /standardScopeSection|standardScopeFields|적용조건/);
assert.match(pageSource, /API\.SAVE/);
assert.doesNotMatch(pageSource.slice(pageSource.indexOf('async function startPeriodRenewal'), pageSource.indexOf('function renderDynamic')), /window\.confirm/);
assert.match(viewSource, /id="standardRenewalButton">개정 등록<\/button>/);
assert.match(pageSource, /기존 현행 기준의 적용종료일을 먼저 저장해 주세요/);
assert.match(pageSource, /form\.elements\.effective_from\.value = ''/);
assert.match(pageSource, /form\.elements\.effective_to\.value = ''/);
assert.match(modelSource, /COALESCE\(:effective_to,'9999-12-31'\)/);
assert.match(modelSource, /WHEN s\.effective_from>CURRENT_DATE THEN 'SCHEDULED'/);
assert.match(modelSource, /WHEN s\.effective_to IS NULL OR s\.effective_to>=CURRENT_DATE THEN 'CURRENT'/);
assert.match(pageSource, /CURRENT: \{ label: '현재 적용'/);
assert.match(pageSource, /ENDED: \{ label: '종료'/);
assert.match(pageSource, /SCHEDULED: \{ label: '적용 예정'/);
assert.ok(pageSource.indexOf("data: 'period_status'") < pageSource.indexOf("settingsKey: '__actions'"));
assert.ok(pageSource.indexOf("data: 'period_status'") > pageSource.indexOf("data: 'updated_at'"));
assert.match(pageSource, /data: 'period_status'[\s\S]*?className: 'text-center no-colvis'/);
assert.match(pageSource, /defaultOrder: \[\[3, 'desc'\]\]/);
assert.match(pageSource, /columnsImmediatelyBeforeActions: \['__period_status'\]/);
assert.match(pageSource, /redrawAfterInitialVisibility: false/);
assert.match(pageSource, /data: 'value_summary', settingsKey: 'value_data'/);
const listModelSource = modelSource.slice(modelSource.indexOf('public function page'), modelSource.indexOf('public function detail'));
assert.doesNotMatch(listModelSource, /SELECT s\.\*/);
assert.match(modelSource, /LEFT JOIN \(SELECT standard_id,COUNT\(\*\) source_count/);
assert.match(serviceSource, /unset\(\$row\['value_data'\]\)/);
assert.doesNotMatch(searchFormSource, /\.load\(\(\) => \{\s*refreshTableLayout\(\{ draw: true \}\)/);
assert.match(resolverSource, /effective_from<=:date_from/);
assert.match(resolverSource, /effective_to IS NULL OR effective_to>=:date_to/);
assert.doesNotMatch(resolverSource, /ORDER BY|sort_no|updated_at|created_at/);
assert.match(serviceSource, /기존 현행 기준의 적용종료일을 먼저 저장해 주세요/);
assert.match(serviceSource, /현재 적용 중인 법정기준은 삭제할 수 없습니다/);
assert.match(serviceSource, /foreach \(\$ids as \$id\)[\s\S]*assertDeletable[\s\S]*foreach \(\$ids as \$id\)[\s\S]*model->delete/);
assert.match(sourceModelSource, /SELECT id,file_path,file_name,file_size,mime_type,created_at,created_by[\s\S]*FOR UPDATE/);
assert.match(sourceModelSource, /UPDATE system_statutory_standard_sources SET/);
assert.match(sourceModelSource, /array_diff\(array_keys\(\$existing\), \$retainedIds\)/);
assert.doesNotMatch(sourceModelSource, /DELETE FROM system_statutory_standard_sources WHERE standard_id=:id/);

const matrixRows = Array.from({ length: 438 }, (_, index) => ({
    salary_from: index * 5000,
    salary_to: (index + 1) * 5000,
    tax_by_dependents: { 1: index * 10, 2: index * 8 },
}));
const original = {
    values: {
        table: { salary_unit: 'KRW', dependent_counts: [1, 2], rows: matrixRows },
        tax_brackets: [
            { tax_base_from: 0, tax_base_to: 200000000, tax_rate: 0.1, progressive_deduction: 0 },
            { tax_base_from: 200000000, tax_base_to: null, tax_rate: 0.2, progressive_deduction: 20000000 },
        ],
        industry_rates: [
            { industry_name: '건설업', employer_rate: 0.037 },
            { industry_name: '서비스업', employer_rate: 0.015 },
        ],
        calculation_policy: {
            method: 'TRUNCATE', stage: 'AFTER_TAX_CREDIT', threshold: 1000,
        },
        _schema: { version: 1, fields: [{ code: 'historical_snapshot' }] },
    },
    sources: [{
        id: 'source-1', standard_id: 'standard-1',
        source_name: '공식자료', organization_name: '공식기관', law_name: '법령', notice_no: '제1호',
        published_at: '2026-01-01', source_url: 'https://example.test/law', note: '사용자가 수정한 비고',
        file_path: '/old/source.pdf', file_name: 'source.pdf', file_size: 100, mime_type: 'application/pdf',
        created_at: '2026-01-01 00:00:00', created_by: 'USER:old', updated_at: '2026-01-02 00:00:00', updated_by: 'USER:old',
    }],
};

const nextPeriod = preparePeriodRenewalDraft(original);
assert.equal(nextPeriod.values.table.rows.length, 438);
assert.deepEqual(nextPeriod.values.tax_brackets, original.values.tax_brackets);
assert.deepEqual(nextPeriod.values.industry_rates, original.values.industry_rates);
assert.deepEqual(nextPeriod.values.calculation_policy, original.values.calculation_policy);
assert.equal('_schema' in nextPeriod.values, false);
assert.deepEqual(nextPeriod.sources, [{
    source_name: '공식자료', organization_name: '공식기관', law_name: '법령', notice_no: '제1호',
    published_at: '2026-01-01', source_url: 'https://example.test/law', note: '사용자가 수정한 비고',
}]);
assert.notStrictEqual(nextPeriod.values.table.rows, original.values.table.rows);

console.log(JSON.stringify({
    matrix_rows: nextPeriod.values.table.rows.length,
    bracket_rows: nextPeriod.values.tax_brackets.length,
    industrial_rate_rows: nextPeriod.values.industry_rates.length,
    calculation_policy: true,
    source_identity_removed: true,
    historical_schema_removed: true,
}));
