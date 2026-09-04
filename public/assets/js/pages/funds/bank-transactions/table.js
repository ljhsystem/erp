import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { labelBadgeRenderer } from '/public/assets/js/common/table/renderers/index.js';
import { formatAccountNumber, formatNumber, loadBankAccountFormats } from '/public/assets/js/common/format.js';
import { actorColumn } from '/public/assets/js/common/actor.js';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function money(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number) || number === 0) return '-';
    return formatNumber(number);
}

function balanceCell(value, row = {}) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    const calculated = Number(value);
    const original = row.original_balance_amount === null || row.original_balance_amount === undefined
        ? null
        : Number(row.original_balance_amount);
    const title = original === null
        ? '계좌별 순번 기준 계산잔액'
        : `계좌별 순번 기준 계산잔액 ${formatNumber(calculated)} / 원본잔액 ${formatNumber(original)}`;
    return `<span title="${escapeHtml(title)}">${escapeHtml(formatNumber(calculated))}</span>`;
}

function statusBadge(text, tone = 'secondary') {
    return `<span class="badge text-bg-${tone}">${escapeHtml(text)}</span>`;
}

function voucherBadge(row = {}) {
    return row.voucher_link_status === 'LINKED'
        ? statusBadge(row.voucher_no ? `연결 ${row.voucher_no}` : '연결완료', 'success')
        : statusBadge('미연결', 'warning');
}

function evidenceBadge(row = {}) {
    if (row.deleted_at) return statusBadge('삭제됨', 'danger');
    const status = String(row.evidence_status || '').toUpperCase();
    if (status === 'ACTIVE') return statusBadge('정상', 'success');
    if (status === 'ERROR') return statusBadge('오류', 'danger');
    if (status === 'DUPLICATED') return statusBadge('중복', 'warning');
    return labelBadgeRenderer(row.evidence_label || status || '원본');
}

function transactionDateCell(value, row = {}) {
    const text = value || row.transaction_date || '-';
    const status = String(row.transaction_datetime_order_status || '').toUpperCase();
    const message = row.transaction_datetime_order_message || '';
    if (!['OUT_OF_ORDER', 'MISSING'].includes(status)) {
        return escapeHtml(text);
    }
    return `<span class="text-danger fw-semibold" title="${escapeHtml(message)}">${escapeHtml(text)}</span>`;
}

function manageButtons(row = {}) {
    const id = escapeHtml(row.id || '');
    const evidenceId = escapeHtml(row.evidence_id || '');
    const voucherId = escapeHtml(row.voucher_id || '');
    const deleted = Boolean(row.deleted_at);
    return `
        <div class="funds-row-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="source" data-id="${id}">원본보기</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-action="edit-evidence" data-evidence-id="${evidenceId}">증빙수정</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-action="voucher" data-voucher-id="${voucherId}" ${voucherId ? '' : 'disabled'}>전표보기</button>
            <button type="button" class="btn btn-outline-success btn-sm" data-action="create-voucher" data-evidence-id="${evidenceId}" ${voucherId ? 'disabled' : ''}>전표생성/연결</button>
            ${deleted
                ? `<button type="button" class="btn btn-success btn-sm" data-action="restore" data-id="${id}">복구</button>`
                : `<button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${id}">삭제</button>`}
        </div>
    `;
}

function compactManageButton(row = {}) {
    const id = escapeHtml(row.id || '');
    const evidenceId = escapeHtml(row.evidence_id || '');
    const voucherId = escapeHtml(row.voucher_id || '');
    return `
        <button type="button"
                class="btn btn-outline-primary btn-sm funds-edit-row-btn"
                data-action="manage"
                data-id="${id}"
                data-evidence-id="${evidenceId}"
                data-voucher-id="${voucherId}">
            \uC218\uC815
        </button>
    `;
}

function textColumn(data, title, options = {}) {
    return {
        data,
        title,
        defaultContent: '',
        visible: options.visible,
        className: options.className || 'text-nowrap',
        render: options.render || ((value) => escapeHtml(value || '-')),
    };
}

function calculatedColumn(data, title, options = {}) {
    return {
        ...textColumn(data, title, options),
        settingsKey: data,
        settingsVirtualType: 'calculated',
        __dtColumnKind: 'virtual',
    };
}

function referenceColumn(data, title, displayField, visible = true) {
    return textColumn(data, title, {
        visible,
        render: (_value, _type, row) => escapeHtml(row?.[displayField] || '-'),
    });
}

export async function createFundsBankTransactionTable({ api, reorderApi, onSummary, onAction, onNotify }) {
    const table = await createDataTable({
        tableSelector: '#fundsBankTransactionsTable',
        api,
        density: 'compact',
        pageLength: 100,
        defaultOrder: [[1, 'asc']],
        searchTableId: 'fundsBankTransactions',
        tableSettings: {
            enabled: true,
            pageKey: 'ledger.funds.bank_transactions',
            tableKey: 'funds-bank-transactions',
            storageKey: 'datatable.settings.funds.bank-transactions.table.v1',
            tableLabel: 'Funds Bank Transactions',
            title: 'Table Settings',
            metaDomain: 'funds-bank-transaction',
            resetOnColumnSchemaChange: true,
        },
        cellSearchFill: {
            valueMap: ({ field, row, cell }) => {
                if (field === 'transaction_direction') {
                    const direction = String(row.transaction_direction || '').toUpperCase();
                    if (direction === 'IN') return '입금';
                    if (direction === 'OUT') return '출금';
                }
                if (field === 'voucher_link_status') {
                    return row.voucher_link_status === 'LINKED' ? '연결' : '미연결';
                }
                if (field === 'evidence_status') {
                    const status = String(row.evidence_status || '').toUpperCase();
                    if (row.deleted_at) return '삭제';
                    if (status === 'ACTIVE') return '정상';
                    if (status === 'ERROR') return '오류';
                    if (status === 'DUPLICATED') return '중복';
                }
                if (field === 'raw_deposit_amount' || field === 'raw_withdraw_amount') {
                    return formatNumber(Number(row[field] || 0));
                }
                if (field === 'raw_description') {
                    return row.raw_description || row.raw_memo || '';
                }
                return row[field] ?? cell.data();
            },
        },
        rowIdField: 'id',
        buttons: [
            {
                text: '휴지통',
                className: 'btn btn-outline-danger btn-sm',
                action() {
                    const modalEl = document.getElementById('fundsBankTrashModal');
                    if (!modalEl) return;
                    window.bootstrap?.Modal.getOrCreateInstance(modalEl)?.show();
                },
            },
        ],
        columns: [
            {
                data: null,
                title: '<i class="bi bi-arrows-move"></i>',
                orderable: false,
                searchable: false,
                className: 'reorder-handle no-sort no-colvis text-center',
                headerClassName: 'no-colvis text-center',
                defaultContent: '<i class="bi bi-list"></i>',
                render: (_value, type) => (type === 'display' ? '<i class="bi bi-list"></i>' : ''),
            },
            {
                data: 'sort_no',
                title: '순번',
                className: 'text-center text-nowrap dt-sequence-column',
                render(value, type, _row, meta) {
                    if (type === 'sort' || type === 'type') {
                        const sortNo = Number(value || 0);
                        return Number.isFinite(sortNo) && sortNo > 0 ? sortNo : Number.MAX_SAFE_INTEGER;
                    }
                    return escapeHtml(value || (meta.row + meta.settings._iDisplayStart + 1));
                },
            },
            {
                data: 'raw_transaction_datetime',
                title: '거래일시',
                defaultContent: '',
                className: 'text-nowrap',
                render: (value, _type, row) => transactionDateCell(value, row),
            },
            {
                data: 'source_type',
                title: '자료출처',
                defaultContent: '',
                className: 'text-nowrap',
                render: (_value, _type, row) => escapeHtml(row.source_type_name || row.source_type || '-'),
            },
            {
                data: 'import_type',
                title: '자료유형',
                defaultContent: '',
                className: 'text-nowrap',
                render: (_value, _type, row) => escapeHtml(row.import_type_name || row.import_type || '-'),
            },
            textColumn('business_unit', '사업구분', { visible: false }),
            {
                data: 'transaction_direction',
                title: '거래구분',
                defaultContent: '',
                className: 'text-nowrap',
                render: (value, type, row) => {
                    const direction = String(value || row.transaction_direction || '').toUpperCase();
                    if (type === 'sort' || type === 'type') return direction;
                    if (direction === 'IN') return '입금';
                    if (direction === 'OUT') return '출금';
                    return escapeHtml(value || '-');
                },
            },
            textColumn('operation_type', '업무유형', { visible: false }),
            referenceColumn('client_id', '거래처', 'client_name', false),
            referenceColumn('project_id', '프로젝트', 'project_name', false),
            referenceColumn('bank_account_id', '계좌', 'bank_account_name'),
            referenceColumn('card_id', '카드', 'card_name', false),
            referenceColumn('team_id', '팀', 'team_name', false),
            referenceColumn('employee_id', '직원', 'employee_name', false),
            { data: 'raw_deposit_amount', title: '입금', defaultContent: 0, className: 'text-end text-nowrap', render: money },
            { data: 'raw_withdraw_amount', title: '출금', defaultContent: 0, className: 'text-end text-nowrap', render: money },
            { data: 'raw_balance_amount', title: '거래 후 잔액', defaultContent: '', className: 'text-end text-nowrap', render: (value, _type, row) => balanceCell(value, row) },
            { data: 'raw_description', title: '거래내용', defaultContent: '', render: (value, _type, row) => `<span title="${escapeHtml(row.raw_memo || value || '')}">${escapeHtml(value || row.raw_memo || '-')}</span>` },
            { data: 'raw_counterparty_account_number', title: '상대계좌번호', defaultContent: '', className: 'text-nowrap', render: (value, _type, row) => escapeHtml(formatAccountNumber(value, row.raw_counterparty_bank_name) || '-') },
            textColumn('raw_counterparty_bank_name', '상대은행'),
            textColumn('raw_memo', '메모'),
            textColumn('raw_transaction_type', '원본 거래구분'),
            { data: 'raw_check_bill_amount', title: '수표어음금액', defaultContent: '', className: 'text-end text-nowrap', render: money },
            textColumn('raw_cms_code', 'CMS코드'),
            textColumn('raw_counterparty_name', '상대계좌예금주명'),
            { data: 'evidence_status', title: '증빙상태', defaultContent: '', className: 'text-nowrap', render: (_value, _type, row) => evidenceBadge(row) },
            {
                data: 'voucher_link_status',
                settingsKey: 'voucher_link_status',
                __dtColumnKind: 'virtual',
                title: '전표연결상태',
                defaultContent: '',
                className: 'text-nowrap',
                render: (_value, _type, row) => voucherBadge(row),
            },
            calculatedColumn('payment_link_label', '지급배분상태'),
            calculatedColumn('internal_transfer_label', '내부이체상태'),
            calculatedColumn('internal_transfer_direction_label', '내부이체방향'),
            calculatedColumn('internal_transfer_counterpart_label', '상대 자사계좌'),
            calculatedColumn('internal_transfer_voucher_no', '내부이체 전표번호'),
            calculatedColumn('internal_transfer_amount', '내부이체금액', {
                className: 'text-end text-nowrap',
                render: money,
            }),
            textColumn('external_key', '외부원본식별값', { visible: false }),
            textColumn('created_at', '생성일시', { visible: false }),
            actorColumn('created_by', '생성자', { visible: false }),
            textColumn('updated_at', '수정일시', { visible: false }),
            actorColumn('updated_by', '수정자', { visible: false }),
            textColumn('deleted_at', '삭제일시', { visible: false }),
            actorColumn('deleted_by', '삭제자', { visible: false }),
            {
                data: null,
                settingsKey: '__actions',
                __dtColumnKind: 'virtual',
                title: '관리',
                orderable: false,
                searchable: false,
                className: 'text-nowrap funds-manage-cell no-colvis',
                headerClassName: 'no-colvis',
                render: (_value, _type, row) => compactManageButton(row),
            },
        ],
    });

    if (reorderApi) {
        bindRowReorder(table, {
            api: reorderApi,
            idField: 'evidence_id',
            sortNoField: 'sort_no',
            includeAppliedRows: true,
            isReorderableRow: (row) => Boolean(row?.evidence_id),
            extraData: () => ({
                scope: 'status',
                import_type: 'BANK_TRANSACTION',
                data_type: 'BANK_TRANSACTION',
            }),
            onSuccess(json) {
                if (typeof onNotify === 'function') {
                    onNotify('success', json?.message || '\uC21C\uC11C\uAC00 \uBCC0\uACBD\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                }
                table?.ajax.reload(null, false);
            },
            onError(json) {
                if (typeof onNotify === 'function') {
                    onNotify('error', json?.message || '\uC21C\uC11C \uBCC0\uACBD\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                }
                table?.ajax.reload(null, false);
            },
        });
    }

    table.on('xhr.dt', (_event, _settings, json) => {
        if (typeof onSummary === 'function') {
            onSummary(json?.summary || {});
        }
        const countEl = document.getElementById('fundsBankTransactionCount');
        if (countEl) {
            countEl.textContent = `${(json?.data || []).length.toLocaleString('ko-KR')}건`;
        }
    });

    loadBankAccountFormats?.().then(() => {
        table?.rows?.().invalidate('data');
        table?.draw?.(false);
    });

    document.getElementById('fundsBankTransactionsTable')?.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        if (typeof onAction === 'function') {
            onAction(button.dataset.action, button);
        }
    });

    return table;
}

export { escapeHtml };
