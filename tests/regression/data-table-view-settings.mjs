import assert from 'node:assert/strict';
import {
    DATA_TABLE_PAGE_LENGTH_OPTIONS,
    DATA_TABLE_COLUMN_WIDTH_DEFAULT,
    isDataTableColumnWidthResizable,
    normalizeDataTableColumnWidth,
} from '../../public/assets/js/common/datatable/dataTableViewPolicy.js';
import {
    buildDataTableViewModalOptions,
    buildNextDataTableViewState,
    cycleDataTableSortSettings,
} from '../../public/assets/js/common/datatable/dataTableViewSettings.js';

assert.equal(normalizeDataTableColumnWidth('220'), 220);
assert.equal(DATA_TABLE_COLUMN_WIDTH_DEFAULT, 120);
assert.equal(normalizeDataTableColumnWidth(''), null);
assert.equal(Number.isNaN(normalizeDataTableColumnWidth('31')), true);
assert.equal(Number.isNaN(normalizeDataTableColumnWidth('2001')), true);
assert.equal(isDataTableColumnWidthResizable({ settingsKey: '__actions' }), false);
assert.equal(isDataTableColumnWidthResizable({ settingsKey: '__actions', widthResizable: true }), true);
assert.deepEqual(DATA_TABLE_PAGE_LENGTH_OPTIONS, [100, 200, 300, 500, 1000, 2000, 3000, 5000, 10000]);

const context = {
    viewState: {
        columnWidths: { employee_name: 180, id: 130, locked: 48 },
        sortSettings: [{ key: 'employee_name', dir: 'asc' }, { key: 'id', dir: 'desc' }],
        pageLength: 500,
        searchFormExpanded: false,
        currentPage: 7,
        searchFormState: { conditions: [{ field: 'employee_name', value: '김' }] },
    },
    tableColumns: [
        { __dtSettingsKey: 'employee_name', title: '직원명' },
        { __dtSettingsKey: 'id', title: '식별자' },
    ],
};
const table = {
    __dtTableSettings: {
        searchFormCapability: {
            available: true,
            defaultExpanded: false,
            getExpanded: () => false,
        },
    },
};
const modal = buildDataTableViewModalOptions(context, table, {
    columnWidths: {}, sortSettings: [], pageLength: 100, searchFormExpanded: null,
});
assert.equal(modal.viewSettings.columnWidths.employee_name, 180);
assert.equal(modal.viewSettings.pageLength, 500);
assert.equal(modal.searchFormAvailable, true);
assert.deepEqual(cycleDataTableSortSettings([], 'employee_name'), [
    { key: 'employee_name', dir: 'asc' },
]);
assert.deepEqual(cycleDataTableSortSettings([
    { key: 'id', dir: 'desc' },
    { key: 'employee_name', dir: 'asc' },
], 'employee_name'), [
    { key: 'employee_name', dir: 'desc' },
]);
assert.deepEqual(cycleDataTableSortSettings([
    { key: 'id', dir: 'desc' },
    { key: 'employee_name', dir: 'desc' },
], 'employee_name'), [
]);

const { previousState, nextState } = buildNextDataTableViewState(context, [
    { key: 'employee_name', width: 220, widthResizable: true },
    { key: 'id', visible: false, width: 130, widthResizable: true },
    { key: 'locked', width: null, widthResizable: false },
], {
    sortSettings: [{ key: 'id', dir: 'desc' }, { key: 'employee_name', dir: 'asc' }],
    pageLength: 1000,
    searchFormExpanded: true,
}, true);

assert.equal(previousState.columnWidths.employee_name, 180);
assert.deepEqual(nextState.columnWidths, { employee_name: 220, locked: 48 });
assert.deepEqual(nextState.sortSettings, [
    { key: 'id', dir: 'desc' },
]);
assert.equal(nextState.pageLength, 1000);
assert.equal(nextState.searchFormExpanded, true);
assert.equal(nextState.currentPage, 7);
assert.deepEqual(nextState.searchFormState, context.viewState.searchFormState);

console.log(JSON.stringify({ success: true, viewStatePreserved: true, tableViewSeparated: true }));
