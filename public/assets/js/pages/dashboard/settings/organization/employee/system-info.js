import { getCachedDataTableMetaColumns } from '/public/assets/js/common/datatable/dataTableSettings.js';

const EMPLOYEE_INPUT_COLUMN_KEYS = new Set([
    'profile_image', 'username', 'password', 'email', 'employee_name', 'phone',
    'emergency_phone', 'rrn', 'address', 'address_detail', 'department_id',
    'position_id', 'role_id', 'doc_hire_date', 'real_hire_date', 'doc_retire_date',
    'real_retire_date', 'rrn_image', 'bank_name', 'account_number', 'account_holder',
    'bank_file', 'two_factor_enabled', 'email_notify', 'sms_notify', 'note', 'memo',
]);

const EMPLOYEE_RECORD_COLUMN_KEYS = new Set([
    'approved', 'approved_at', 'approved_by', 'login_fail_count',
    'account_locked_until', 'last_login', 'last_login_ip', 'last_login_device',
    'password_updated_at', 'password_updated_by', 'is_active', 'user_created_at',
    'user_created_by', 'user_updated_at', 'user_updated_by',
]);

export function resolveEmployeeSystemInfoColumns(metaColumns = []) {
    return (Array.isArray(metaColumns) ? metaColumns : []).filter((column) => {
        const key = String(column?.key || '').trim();
        return key !== ''
            && String(column?.column_type || 'physical') === 'physical'
            && !EMPLOYEE_INPUT_COLUMN_KEYS.has(key)
            && !EMPLOYEE_RECORD_COLUMN_KEYS.has(key);
    });
}

function displayValue(value) {
    if (value === null || value === undefined || value === '') return '-';
    return String(value);
}

export function renderEmployeeSystemInfo(data = {}) {
    const container = document.getElementById('employeeSystemInfoFields');
    if (!container) return;

    const metaColumns = getCachedDataTableMetaColumns({ metaDomain: 'employee' });
    const groups = new Map();
    resolveEmployeeSystemInfoColumns(metaColumns).forEach((column) => {
        const table = String(column?.table || '').trim() || 'employee';
        if (!groups.has(table)) groups.set(table, []);
        groups.get(table).push(column);
    });

    container.replaceChildren(...Array.from(groups.entries()).map(([table, columns]) => {
        const card = document.createElement('div');
        card.className = 'log-card';

        const title = document.createElement('div');
        title.className = 'log-title';
        const tableComment = String(columns[0]?.table_comment || '').trim();
        title.textContent = tableComment !== '' ? `${tableComment} (${table})` : table;
        card.append(title);

        columns.forEach((column) => {
            const key = String(column.key || '').trim();
            const row = document.createElement('div');
            row.className = 'log-row';

            const label = document.createElement('span');
            label.textContent = String(column.label || column.source_title || key);

            const value = document.createElement('span');
            value.textContent = displayValue(data[key]);

            row.append(label, value);
            card.append(row);
        });

        return card;
    }));
}
