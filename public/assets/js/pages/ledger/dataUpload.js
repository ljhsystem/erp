(() => {
    const dataTypeEl = document.getElementById('dataType');
    const formatSelectEl = document.getElementById('formatSelect');
    const formatManageBtn = document.getElementById('formatManageBtn');
    const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
    const uploadFileBtn = document.getElementById('uploadFileBtn');
    const uploadFileEl = document.getElementById('uploadFile');
    const uploadFileSummaryEl = document.getElementById('uploadFileSummary');
    const uploadFileNameEl = document.getElementById('uploadFileName');
    const uploadFileSizeEl = document.getElementById('uploadFileSize');
    const previewBtn = document.getElementById('previewBtn');
    const previewTable = document.getElementById('previewTable');
    const previewTableBody = document.querySelector('#previewTable tbody');
    const uploadResultAlert = document.getElementById('uploadResultAlert');
    const uploadResultText = document.getElementById('uploadResultText');

    let previewRows = [];
    let currentBatchId = '';
    const LAST_FORMAT_PREFIX = 'ledger.dataUpload.lastFormat.';
    const TEXT = {
        requestFailed: '\uC694\uCCAD \uCC98\uB9AC\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.',
        noFile: '\uC120\uD0DD\uD55C \uD30C\uC77C\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.',
        status: '\uC0C1\uD0DC',
        errorMessage: '\uC624\uB958\uB0B4\uC6A9',
        error: '\uC624\uB958',
        warning: '\uACBD\uACE0',
        ok: '\uC815\uC0C1',
        noRows: '\uC5C5\uB85C\uB4DC\uB41C \uB370\uC774\uD130\uAC00 \uC5C6\uC2B5\uB2C8\uB2E4.',
        saved: '\uC5C5\uB85C\uB4DC \uBC30\uCE58\uAC00 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4. \uBC30\uCE58 ID: ',
        selectFormat: '\uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694',
        selectTypeAndFormat: '\uC790\uB8CC\uC720\uD615\uACFC \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
        selectFormatAndFile: '\uC591\uC2DD\uACFC \uD30C\uC77C\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (type === 'error' || type === 'warning') alert(message);
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            throw new Error(json.message || TEXT.requestFailed);
        }
        return json;
    }

    function selectedFile() {
        return uploadFileEl?.files?.[0] || null;
    }

    function formatFileSize(bytes) {
        const size = Number(bytes || 0);
        if (size < 1024) return `${size} B`;
        if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
        return `${(size / 1024 / 1024).toFixed(1)} MB`;
    }

    function renderSelectedFile(file) {
        const hasFile = Boolean(file);
        if (uploadFileNameEl) {
            uploadFileNameEl.textContent = hasFile ? file.name : TEXT.noFile;
        }
        if (uploadFileSizeEl) {
            uploadFileSizeEl.textContent = hasFile ? `(${formatFileSize(file.size)})` : '';
            uploadFileSizeEl.classList.toggle('d-none', !hasFile);
        }
        if (uploadFileSummaryEl) {
            uploadFileSummaryEl.classList.toggle('has-file', hasFile);
            uploadFileSummaryEl.title = hasFile ? `${file.name} (${formatFileSize(file.size)})` : TEXT.noFile;
        }
    }

    function currentDataType() {
        return dataTypeEl?.value || 'TAX_INVOICE';
    }

    function currentFormatId() {
        return formatSelectEl?.value || '';
    }

    function updateTemplateButtonState() {
        if (!downloadTemplateBtn) return;
        downloadTemplateBtn.disabled = currentDataType() === '' || currentFormatId() === '';
    }

    function formatNumber(value) {
        return Number(String(value || 0).replaceAll(',', '') || 0).toLocaleString('ko-KR');
    }

    function renderTableHeader(columns) {
        const thead = previewTable?.querySelector('thead');
        if (!thead) return;
        thead.innerHTML = `
            <tr>
                <th>${TEXT.status}</th>
                <th>${TEXT.errorMessage}</th>
                ${columns.map((column) => `<th>${escapeHtml(column.excel_column_name || column.system_field_name)}</th>`).join('')}
            </tr>
        `;
    }

    function validationBadge(validation = {}) {
        const status = validation.status || 'ok';
        const label = validation.label || (status === 'error' ? TEXT.error : status === 'warning' ? TEXT.warning : TEXT.ok);
        const badgeClass = status === 'error'
            ? 'bg-danger'
            : (status === 'warning' ? 'bg-warning text-dark' : 'bg-success');

        return `<span class="badge ${badgeClass}">${escapeHtml(label)}</span>`;
    }

    function validationMessage(validation = {}) {
        const messages = Array.isArray(validation.messages) ? validation.messages : [];
        return messages.length ? messages.join(', ') : '-';
    }

    function renderPreviewRows(rows, columns) {
        if (!previewTableBody) return;
        renderTableHeader(columns);

        if (!rows.length) {
            previewTableBody.innerHTML = `<tr><td colspan="${Math.max(columns.length + 2, 1)}" class="text-center text-muted py-4">${TEXT.noRows}</td></tr>`;
            return;
        }

        previewTableBody.innerHTML = rows.map((row) => `
            <tr class="${row?._validation?.status === 'error' ? 'table-danger' : (row?._validation?.status === 'warning' ? 'table-warning' : '')}">
                <td>${validationBadge(row._validation)}</td>
                <td>${escapeHtml(validationMessage(row._validation))}</td>
                ${columns.map((column) => {
                    const field = column.system_field_name;
                    const value = ['supply_amount', 'vat_amount', 'total_amount'].includes(field)
                        ? formatNumber(row[field])
                        : row[field];
                    return `<td class="${['supply_amount', 'vat_amount', 'total_amount'].includes(field) ? 'text-end' : ''}">${escapeHtml(value)}</td>`;
                }).join('')}
            </tr>
        `).join('');
    }

    function showUploadResult(batch) {
        if (!uploadResultAlert || !uploadResultText) return;
        uploadResultText.textContent = `${TEXT.saved}${batch?.id || currentBatchId}`;
        uploadResultAlert.classList.remove('d-none');
    }

    async function loadFormats() {
        const dataType = currentDataType();
        const json = await fetchJson(`/api/import/formats?data_type=${encodeURIComponent(dataType)}&include_columns=1`);
        const formats = json.data || [];
        const lastFormatId = localStorage.getItem(`${LAST_FORMAT_PREFIX}${dataType}`) || '';
        const defaultFormat = formats.find((format) => Number(format.is_default || 0) === 1);
        const selectedFormatId = formats.some((format) => format.id === lastFormatId)
            ? lastFormatId
            : (defaultFormat?.id || formats[0]?.id || '');

        formatSelectEl.innerHTML = `<option value="">${TEXT.selectFormat}</option>` + formats.map((format) => `
            <option value="${escapeHtml(format.id)}" ${format.id === selectedFormatId ? 'selected' : ''}>
                ${escapeHtml(format.format_name)}
            </option>
        `).join('');
        updateTemplateButtonState();
    }

    uploadFileBtn?.addEventListener('click', () => uploadFileEl?.click());
    uploadFileSummaryEl?.addEventListener('click', () => uploadFileEl?.click());

    uploadFileEl?.addEventListener('change', () => {
        previewRows = [];
        currentBatchId = '';
        uploadResultAlert?.classList.add('d-none');
        renderSelectedFile(selectedFile());
    });

    dataTypeEl?.addEventListener('change', () => {
        previewRows = [];
        currentBatchId = '';
        uploadResultAlert?.classList.add('d-none');
        void loadFormats().catch((error) => notify('error', error.message));
    });

    formatSelectEl?.addEventListener('change', () => {
        const formatId = currentFormatId();
        if (formatId !== '') {
            localStorage.setItem(`${LAST_FORMAT_PREFIX}${currentDataType()}`, formatId);
        }
        updateTemplateButtonState();
    });

    formatManageBtn?.addEventListener('click', () => {
        window.location.href = '/ledger/data/format';
    });

    downloadTemplateBtn?.addEventListener('click', () => {
        const dataType = currentDataType();
        const formatId = currentFormatId();
        if (!dataType || !formatId) {
            notify('warning', TEXT.selectTypeAndFormat);
            return;
        }
        window.location.href = `/api/import/template?format_id=${encodeURIComponent(formatId)}`;
    });

    previewBtn?.addEventListener('click', async () => {
        const file = selectedFile();
        const formatId = currentFormatId();
        if (!file || !formatId) {
            notify('warning', TEXT.selectFormatAndFile);
            return;
        }
        localStorage.setItem(`${LAST_FORMAT_PREFIX}${currentDataType()}`, formatId);

        const formData = new FormData();
        formData.append('format_id', formatId);
        formData.append('file', file);
        const json = await fetchJson('/api/import/preview', {
            method: 'POST',
            body: formData,
        });
        previewRows = json.data?.rows || [];
        currentBatchId = json.data?.batch?.id || '';
        renderPreviewRows(previewRows, json.data?.format?.columns || []);
        showUploadResult(json.data?.batch);
        console.log('[dataUpload] uploaded batch:', json.data?.batch);
    });

    renderSelectedFile(selectedFile());
    loadFormats().catch((error) => notify('error', error.message));
})();
