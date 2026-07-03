import { actorDisplay } from '/public/assets/js/common/actor.js';

export function registerFiles(ctx) {
    const { bindNumberInput, initCodeSelectControls } = ctx;

    function renderFiles(files = ctx.currentFiles) {
        if (!ctx.fileListEl) return;

        const existingFiles = Array.isArray(files) ? files : [];

        const baseRows = [
            ...existingFiles.map((file) => ({
                type: 'existing',
                key: String(file.id || ''),
                orderKey: `existing:${String(file.id || '')}`,
                id: String(file.id || ''),
                file_name: file.file_name || file.name || '',
                file_size: file.file_size || file.size || 0,
                file_url: file.id
                    ? `/api/ledger/transaction/file?id=${encodeURIComponent(String(file.id))}`
                    : '',
            })),

            ...ctx.pendingFiles.map((file) => ({
                type: 'new',
                key: file._transactionTempId,
                orderKey: `new:${file._transactionTempId}`,
                file_name: file.name,
                file_size: file.size,
            })),
        ];

        const rowMap = new Map(
            baseRows.map((row) => [row.orderKey, row])
        );

        const orderedRows = ctx.fileRowOrder.length > 0
            ? ctx.fileRowOrder
                .map((key) => rowMap.get(key))
                .filter(Boolean)
            : [];

        const orderedKeys = new Set(
            orderedRows.map((row) => row.orderKey)
        );

        const rows = [
            ...orderedRows,
            ...baseRows.filter((row) => !orderedKeys.has(row.orderKey)),
        ];

        ctx.updateFileDropzone(rows);

        if (rows.length === 0) {
            ctx.fileListEl.innerHTML = '';
            return;
        }

        ctx.fileListEl.innerHTML = `
            <div class="transaction-file-table" role="table" aria-label="거래 증빙 파일">

                <div class="transaction-file-row transaction-file-head" role="row">
                    <span class="transaction-file-drag-head">
                        <i class="bi bi-arrows-move"></i>
                    </span>

                    <span>순번</span>
                    <span>파일명</span>
                    <span>크기</span>
                    <span>관리</span>
                </div>

                ${rows.map((file, index) => {

                    const id = ctx.escapeHtml(file.id || '');
                    const key = ctx.escapeHtml(file.key || '');
                    const type = ctx.escapeHtml(file.type || '');

                    const name = ctx.escapeHtml(
                        file.file_name || `파일 ${index + 1}`
                    );

                    const fileUrl = (
                        file.type === 'existing' && file.file_url
                    )
                        ? ctx.escapeHtml(file.file_url)
                        : '';

                    const nameContent = fileUrl
                        ? `<a class="transaction-file-link"
                            href="${fileUrl}"
                            target="_blank"
                            rel="noopener">${name}</a>`
                        : name;

                    const sizeText = ctx.escapeHtml(
                        ctx.formatBytes(file.file_size)
                    );

                    const order = index + 1;

                    const orderInput = file.type === 'existing'
                        ? `<input type="hidden"
                                name="file_orders[${id}]"
                                value="${order}">`
                        : `<input type="hidden"
                                name="new_file_orders[]"
                                value="${order}">`;

                    const deleteAttrs = file.type === 'existing'
                        ? `data-id="${id}"`
                        : `data-temp-id="${key}"`;

                    return `
                    <div class="transaction-file-row transaction-file-item"
                        role="row"
                        draggable="true"
                        data-file-type="${type}"
                        data-file-key="${key}"
                        data-order-key="${ctx.escapeHtml(file.orderKey || '')}">

                        <span class="transaction-file-drag"
                            aria-label="순서 변경">
                            <i class="bi bi-list"></i>
                        </span>

                        <span class="transaction-file-order">
                            ${order}
                            ${orderInput}
                        </span>

                        <span class="transaction-file-name" title="${name}">
                            ${nameContent}
                        </span>

                        <span class="transaction-file-size">
                            ${sizeText}
                        </span>

                        <button type="button"
                                class="transaction-file-delete btn-delete-transaction-file"
                                ${deleteAttrs}>
                            -삭제
                        </button>
                    </div>
                    `;
                }).join('')}

            </div>
        `;
    }

    function syncFileInputFromPending() {
        if (!ctx.fileInput || typeof DataTransfer === 'undefined') return;

        const transfer = new DataTransfer();
        ctx.pendingFiles.forEach((file) => transfer.items.add(file));
        ctx.fileInput.files = transfer.files;
    }

    function assignPendingFiles(files) {
        ctx.pendingFiles = Array.from(files || []).map((file) => {
            if (!file._transactionTempId) {
                Object.defineProperty(file, '_transactionTempId', {
                    value: `new-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    enumerable: false,
                });
            }
            return file;
        });
        syncFileInputFromPending();
        renderFiles(ctx.currentFiles);
    }

    function appendPendingFiles(files) {
        const appended = Array.from(files || []);
        if (appended.length === 0) return;

        assignPendingFiles([...ctx.pendingFiles, ...appended]);
    }

    function reorderFilesByDom() {
        if (!ctx.fileListEl) return;

        const currentMap = new Map(ctx.currentFiles.map((file) => [String(file.id || ''), file]));
        const pendingMap = new Map(ctx.pendingFiles.map((file) => [file._transactionTempId, file]));
        const nextCurrent = [];
        const nextPending = [];

        ctx.fileListEl.querySelectorAll('.transaction-file-item').forEach((row) => {
            const type = row.dataset.fileType || '';
            const key = row.dataset.fileKey || '';

            if (type === 'existing' && currentMap.has(key)) {
                nextCurrent.push(currentMap.get(key));
            }
            if (type === 'new' && pendingMap.has(key)) {
                nextPending.push(pendingMap.get(key));
            }
        });

        ctx.currentFiles = nextCurrent;
        ctx.pendingFiles = nextPending;
        ctx.fileRowOrder = Array.from(ctx.fileListEl.querySelectorAll('.transaction-file-item'))
            .map((row) => row.dataset.orderKey || '')
            .filter(Boolean);
        syncFileInputFromPending();
        renderFiles(ctx.currentFiles);
    }

    function removePendingFile(tempId) {
        ctx.pendingFiles = ctx.pendingFiles.filter((file) => file._transactionTempId !== tempId);
        ctx.fileRowOrder = ctx.fileRowOrder.filter((key) => key !== `new:${tempId}`);
        syncFileInputFromPending();
        renderFiles(ctx.currentFiles);
    }

    async function loadTransactionFilePolicy() {
        if (!ctx.fileDropzoneEl) return;

        try {
            const list = await ctx.fetchJson(ctx.API.filePolicyList);
            const policies = Array.isArray(list) ? list : (list.data || []);
            const policy = policies.find((item) => String(item.policy_key || '') === 'transaction_evidence');

            if (!policy) {
                ctx.fileDropzoneEmptyText = '파일을 드래그해서 첨부하세요';
                ctx.updateFileDropzone([]);
                return;
            }

            const ext = String(policy.allowed_ext || '').trim();
            const maxSize = Number(policy.max_size_mb || 0);
            const description = String(policy.description || '거래 증빙 파일 업로드').trim();

            if (ctx.fileInput && ext) {
                ctx.fileInput.accept = ext.split(',').map((item) => `.${item.trim().replace(/^\./, '')}`).join(',');
            }

            ctx.fileDropzoneEmptyText = `${description} / ${ext || '허용 확장자 확인'} / 최대 ${maxSize || '-'}MB`;
            if (!ctx.currentFiles.length && !ctx.pendingFiles.length) {
                ctx.updateFileDropzone([]);
            }
        } catch (error) {
            ctx.fileDropzoneEmptyText = '파일을 드래그해서 첨부하세요';
            ctx.updateFileDropzone([]);
        }
    }

    function bindFileReorderEvents() {
        if (!ctx.fileListEl) return;
        if (ctx.fileListEl.dataset.reorderBound === 'true') return;

        ctx.fileListEl.dataset.reorderBound = 'true';

        let draggingRow = null;

        ctx.fileListEl.addEventListener('dragstart', (event) => {
            const row = event.target.closest('.transaction-file-item');
            if (!row) return;

            draggingRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.fileKey || '');
        });

        ctx.fileListEl.addEventListener('dragover', (event) => {
            const row = event.target.closest('.transaction-file-item');
            if (!row || !draggingRow || row === draggingRow) return;

            event.preventDefault();
            const rect = row.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            row.parentNode.insertBefore(draggingRow, after ? row.nextSibling : row);
        });

        ctx.fileListEl.addEventListener('dragend', () => {
            if (draggingRow) {
                draggingRow.classList.remove('is-dragging');
                draggingRow = null;
                reorderFilesByDom();
            }
        });
    }

    async function initModalControls() {
        if (ctx.modalControlsInitialized) return;

        await ctx.ensureAgGridLibrary();
        ctx.modalControlsInitialized = true;
        ctx.initTransactionDatePicker();
        ctx.bindLineHeaderStickiness();
        ctx.initSettlementGrid();
        ctx.initClientSelect();
        ctx.initProjectSelect();
        ctx.initBankAccountSelect();
        ctx.initCardSelect();
        ctx.initTeamSelect();
        ctx.initEmployeeSelect();
        bindFileReorderEvents();
        ctx.modalEl.querySelectorAll('.number-input').forEach((input) => bindNumberInput(input));
        void loadTransactionFilePolicy();
        void ctx.initUnitCodeOptions();
        void initCodeSelectControls(document.getElementById('clientModal'));
        void initCodeSelectControls(ctx.modalEl);
    }

    function markFileDeleted(fileId) {
        if (!fileId) return;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_file_ids[]';
        input.value = fileId;
        input.dataset.generatedDeleteFile = 'true';
        ctx.form.appendChild(input);

        ctx.currentFiles = ctx.currentFiles.filter((file) => String(file.id || '') !== String(fileId));
        ctx.fileRowOrder = ctx.fileRowOrder.filter((key) => key !== `existing:${fileId}`);
        renderFiles(ctx.currentFiles);
    }

    function formatVoucherStatus(value) {
        const status = String(value || 'draft').toLowerCase();

        return {
            draft: '임시저장',
            confirmed: '검토요청',
            posted: '승인완료',
            closed: '마감',
        }[status] || status;
    }

    function clearVoucherSelection() {
        ctx.selectedVoucherId = '';
        ctx.selectedVoucherLabel = '';
    }

    function handleVoucherSelected(voucher) {
        ctx.selectedVoucherId = String(voucher?.id || '');
        ctx.selectedVoucherLabel = [voucher?.voucher_no, voucher?.client_name, voucher?.summary_text]
            .filter(Boolean)
            .join(' / ');
        if (ctx.voucherSummaryEl && ctx.selectedVoucherId) {
            ctx.voucherSummaryEl.innerHTML = `
                <div class="transaction-voucher-item">
                    <strong>선택됨</strong>
                    <span>${ctx.escapeHtml(voucher?.voucher_no || ctx.selectedVoucherId)}</span>
                    <span>${ctx.escapeHtml(voucher?.voucher_date || '')}</span>
                    <span>${ctx.escapeHtml(voucher?.summary_text || '')}</span>
                </div>
            `;
        }
    }

    function renderVoucherState(transaction = {}) {
        const links = Array.isArray(transaction.linked_vouchers) ? transaction.linked_vouchers : [];
        const savedId = String(transaction.id || document.getElementById('transaction_id')?.value || '').trim();
        const status = String(transaction.match_status || document.getElementById('transaction_match_status')?.value || 'none');
        const isLinked = links.length > 0 || status === 'matched';

        if (ctx.voucherStatusEl) {
            ctx.voucherStatusEl.className = `transaction-status ${isLinked ? 'matched' : 'none'}`;
            ctx.voucherStatusEl.textContent = isLinked ? '연결' : '미연결';
        }

        if (!ctx.voucherSummaryEl) return;

        if (!savedId) {
            ctx.voucherSummaryEl.textContent = '저장 후 전표를 생성하거나 기존 전표와 연결할 수 있습니다.';
            return;
        }

        if (!links.length) {
            ctx.voucherSummaryEl.textContent = '연결된 전표가 없습니다.';
            return;
        }

        ctx.voucherSummaryEl.innerHTML = links.map((voucher) => {
            const voucherId = ctx.escapeHtml(voucher.id || '');
            const label = ctx.escapeHtml(voucher.voucher_no || voucher.sort_no || voucherId);
            const date = ctx.escapeHtml(voucher.voucher_date || '');
            const voucherStatus = ctx.escapeHtml(formatVoucherStatus(voucher.status));
            const summary = ctx.escapeHtml(voucher.summary_text || '허용 확장자 없음');

            return `
                <div class="transaction-voucher-item">
                    <strong>선택됨</strong>
                    <span>${date}</span>
                    <span>${voucherStatus}</span>
                    <span>${summary}</span>
                    <input type="hidden" class="linked-voucher-id" value="${voucherId}">
                </div>
            `;
        }).join('');
    }


    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.transaction = function (row = {}) {
        const id = ctx.escapeHtml(row.id || '');
        return `
            <td>${ctx.escapeHtml(row.transaction_date || '')}</td>
            <td>${ctx.escapeHtml(row.client_name || '-')}</td>
            <td>${ctx.escapeHtml(row.description || '')}</td>
            <td class="text-end">${ctx.escapeHtml(ctx.formatAmount(row.final_amount || row.transaction_final_amount || 0))}</td>
            <td>${ctx.renderMatchStatus(row.match_status)}</td>
            <td>${ctx.escapeHtml(row.deleted_at || '')}</td>
            <td>${ctx.escapeHtml(actorDisplay(row, 'deleted_by'))}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${id}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${id}">삭제</button>
            </td>
        `;
    };


    Object.assign(ctx, { renderFiles, syncFileInputFromPending, assignPendingFiles, appendPendingFiles, reorderFilesByDom, removePendingFile, bindFileReorderEvents, markFileDeleted, formatVoucherStatus, clearVoucherSelection, handleVoucherSelected, renderVoucherState, loadTransactionFilePolicy, initModalControls });
    return ctx;
}
