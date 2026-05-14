import { createSpreadsheet, setSpreadsheetDataState } from '/public/assets/js/common/handsontable.js';
import { initCodeSelectControls, onCodeOptionsLoaded } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';

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
    const validateBtn = document.getElementById('validateBtn');
    const seedUploadBtn = document.getElementById('seedUploadBtn');
    const validationSummaryCard = document.getElementById('validationSummaryCard');
    const validationSummaryList = document.getElementById('validationSummaryList');
    const validationDetailCard = document.getElementById('validationDetailCard');
    const validationDetailList = document.getElementById('validationDetailList');
    const previewGridEl = document.getElementById('previewGrid');
    const previewGridWrapEl = document.getElementById('previewGridWrap');
    const previewGridSummaryEl = document.getElementById('previewGridSummary');
    const previewFilterErrorsEl = document.getElementById('previewFilterErrors');
    const previewFilterMappedEl = document.getElementById('previewFilterMapped');
    const previewHideUnusedEl = document.getElementById('previewHideUnused');
    const uploadResultAlert = document.getElementById('uploadResultAlert');
    const uploadResultText = document.getElementById('uploadResultText');

    let previewRows = [];
    let previewColumns = [];
    let previewHot = null;
    let currentPreviewToken = '';
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
        saved: 'Seed 업로드가 완료되었습니다.',
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
        console[type === 'error' ? 'error' : 'warn'](message);
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            throw new Error(json.message || TEXT.requestFailed);
        }
        return json;
    }

    function filenameFromDisposition(disposition) {
        const encoded = disposition?.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        if (encoded) {
            try {
                return decodeURIComponent(encoded);
            } catch (_) {
                return encoded;
            }
        }

        return disposition?.match(/filename="?([^";]+)"?/i)?.[1] || 'upload_template.xlsx';
    }

    async function downloadTemplate(formatId) {
        const res = await fetch(`/api/import/template?format_id=${encodeURIComponent(formatId)}`);
        const contentType = res.headers.get('Content-Type') || '';
        if (!res.ok || contentType.includes('application/json')) {
            const json = await res.json().catch(() => ({}));
            throw new Error(json.message || '양식 다운로드에 실패했습니다.');
        }

        const blob = await res.blob();
        const signature = await blob.slice(0, 2).text();
        if (signature !== 'PK') {
            const body = await blob.text().catch(() => '');
            const message = body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            throw new Error(message || '양식 다운로드에 실패했습니다.');
        }

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filenameFromDisposition(res.headers.get('Content-Disposition'));
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
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
        return dataTypeEl?.value || '';
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

    function validationStatus(validation = {}) {
        const status = validation.status || 'ok';
        if (status === 'error') return 'ERROR';
        if (status === 'warning') return 'WARNING';
        return 'VALID';
    }

    function validationMessage(validation = {}) {
        const messages = Array.isArray(validation.messages) ? validation.messages : [];
        return messages.length ? messages.join(', ') : '-';
    }

    function seedActionLabel(action) {
        return {
            NEW: '신규',
            UPDATED: '변경',
            UNCHANGED: '동일',
        }[String(action || '').toUpperCase()] || '신규';
    }

    function setSeedUploadEnabled(enabled) {
        if (seedUploadBtn) {
            seedUploadBtn.disabled = !enabled;
        }
    }

    function destroyPreviewGrid() {
        if (previewHot) {
            previewHot.destroy();
            previewHot = null;
        }
        setSpreadsheetDataState(previewGridWrapEl, false);
        if (previewGridSummaryEl) {
            previewGridSummaryEl.textContent = '';
        }
        setSeedUploadEnabled(false);
    }

    function columnValue(row, column) {
        const excelName = column.excel_column_name || '';
        const excelIndex = String(column.excel_column_index || column.column_order || '');
        const systemField = column.system_field_name || '';
        const rawPayload = row?._raw_payload || {};
        let value = row?.[systemField];

        if (Object.prototype.hasOwnProperty.call(rawPayload, excelIndex)) {
            const rawCell = rawPayload[excelIndex];
            value = rawCell && typeof rawCell === 'object' && Object.prototype.hasOwnProperty.call(rawCell, 'value')
                ? rawCell.value
                : rawCell;
        } else if (Object.prototype.hasOwnProperty.call(rawPayload, excelName)) {
            value = rawPayload[excelName];
        }

        return ['supply_amount', 'vat_amount', 'total_amount'].includes(systemField)
            ? formatNumber(value)
            : (value ?? '');
    }

    function visiblePreviewColumns(columns) {
        const mappedOnly = Boolean(previewFilterMappedEl?.checked);
        const hideUnused = Boolean(previewHideUnusedEl?.checked);
        if (!mappedOnly && !hideUnused) return columns;

        return columns.filter((column) => String(column.system_field_name || '').trim() !== '');
    }

    function visiblePreviewRows(rows) {
        if (!previewFilterErrorsEl?.checked) return rows;
        return rows.filter((row) => row?._validation?.status === 'error');
    }

    function buildPreviewDataset(rows, columns) {
        return rows.map((row, index) => {
        const item = {
            _source: row,
            _row_index: index + 1,
            _status: validationStatus(row?._validation),
            _seed_action: seedActionLabel(row?._seed_action),
            _message: validationMessage(row?._validation),
        };

            columns.forEach((column, columnIndex) => {
                item[`col_${columnIndex}`] = columnValue(row, column);
            });

            return item;
        });
    }

    function buildHotColumns(columns) {
        return [
            { data: '_status', readOnly: true, width: 90 },
            { data: '_seed_action', readOnly: true, width: 90 },
            { data: '_message', readOnly: true, width: 260 },
            ...columns.map((column, columnIndex) => ({
                data: `col_${columnIndex}`,
                readOnly: true,
                width: ['supply_amount', 'vat_amount', 'total_amount'].includes(column.system_field_name) ? 120 : 140,
            })),
        ];
    }

    function buildHotHeaders(columns) {
        return [
            TEXT.status,
            'Seed판정',
            TEXT.errorMessage,
            ...columns.map((column) => column.excel_column_name || column.system_field_name || '-'),
        ];
    }

    function requiredColumnMissing(row, column) {
        if (!Number(column.is_required || 0)) return false;
        return String(columnValue(row, column) ?? '').trim() === '';
    }

    function previewCellRenderer(instance, td, row, col, prop, value, cellProperties) {
        Handsontable.renderers.TextRenderer(instance, td, row, col, prop, value, cellProperties);
        td.classList.remove(
            'spreadsheet-status-valid',
            'spreadsheet-status-warning',
            'spreadsheet-status-error',
            'spreadsheet-message-cell',
            'spreadsheet-cell-warning',
            'spreadsheet-cell-error',
        );

        const sourceRow = instance.getSourceDataAtRow(row)?._source || {};
        const status = sourceRow?._validation?.status || 'ok';
        if (prop === '_status') {
            td.classList.add(status === 'error' ? 'spreadsheet-status-error' : (status === 'warning' ? 'spreadsheet-status-warning' : 'spreadsheet-status-valid'));
            return;
        }
        if (prop === '_message') {
            td.classList.add('spreadsheet-message-cell');
            return;
        }

        const columnIndex = Number(String(prop).replace('col_', ''));
        const column = cellProperties.previewColumns?.[columnIndex];
        if (column && requiredColumnMissing(sourceRow, column)) {
            td.classList.add('spreadsheet-cell-error');
        } else if (status === 'warning') {
            td.classList.add('spreadsheet-cell-warning');
        }
    }

    function renderPreviewRows(rows, columns) {
        if (!previewGridEl) return;
        const filteredColumns = visiblePreviewColumns(columns);
        const filteredRows = visiblePreviewRows(rows);
        const data = buildPreviewDataset(filteredRows, filteredColumns);
        const hotColumns = buildHotColumns(filteredColumns);
        const headers = buildHotHeaders(filteredColumns);

        destroyPreviewGrid();

        if (!data.length) {
            if (previewGridSummaryEl) {
                previewGridSummaryEl.textContent = TEXT.noRows;
            }
            return;
        }

        setSpreadsheetDataState(previewGridWrapEl, true);
        if (previewGridSummaryEl) {
            const errors = rows.filter((row) => row?._validation?.status === 'error').length;
            const warnings = rows.filter((row) => row?._validation?.status === 'warning').length;
            previewGridSummaryEl.textContent = `${data.length.toLocaleString('ko-KR')}\uD589 / ${filteredColumns.length.toLocaleString('ko-KR')}\uCEEC\uB7FC · ERROR ${errors} · WARNING ${warnings}`;
        }

        try {
            previewHot = createSpreadsheet(previewGridEl, {
            data,
            columns: hotColumns,
            colHeaders: headers,
            rowHeaders: true,
            fixedColumnsStart: Math.min(4, hotColumns.length),
            fixedRowsTop: 0,
            cells(row, col) {
                const prop = hotColumns[col]?.data || '';
                return {
                    renderer: previewCellRenderer,
                    previewColumns: filteredColumns,
                    className: ['supply_amount', 'vat_amount', 'total_amount'].includes(filteredColumns[col - 3]?.system_field_name) ? 'htRight' : '',
                    data: prop,
                };
            },
            afterGetColHeader(column, th) {
                if (column < 0) return;
                th.title = headers[column] || '';
            },
            });
        } catch (error) {
            notify('error', error.message);
        }
    }

    function renderValidationSummary(summary = {}) {
        if (!validationSummaryCard || !validationSummaryList) return;
        const items = [
            ['정상', summary.ok || 0, 'text-bg-success'],
            ['경고', summary.warning || 0, 'text-bg-warning'],
            ['오류', summary.error || 0, 'text-bg-danger'],
            ['신규', summary.new || 0, 'text-bg-primary'],
            ['변경', summary.updated || 0, 'text-bg-info'],
            ['동일', summary.unchanged || 0, 'text-bg-secondary'],
            ['컬럼오류', summary.check_error || 0, 'text-bg-danger'],
            ['컬럼경고', summary.check_warning || 0, 'text-bg-warning'],
        ];
        validationSummaryList.innerHTML = items.map(([label, count, cls]) => `<span class="badge ${cls}">${label} ${Number(count).toLocaleString('ko-KR')}</span>`).join('');
        validationSummaryCard.classList.remove('d-none');
    }

    function renderValidationDetails(rows = [], checks = []) {
        if (!validationDetailCard || !validationDetailList) return;
        const details = [];
        checks.forEach((check) => {
            details.push([String(check.level || 'ok').toUpperCase(), check.message || '']);
        });
        rows.forEach((row) => {
            const messages = Array.isArray(row?._validation?.messages) ? row._validation.messages : [];
            messages.forEach((message) => {
                details.push([validationStatus(row._validation), `${row._row_no || '-'}행: ${message}`]);
            });
        });
        if (!details.length) {
            details.push(['정상', '검증 오류가 없습니다.']);
        }
        validationDetailList.innerHTML = details.map(([type, message]) => `
            <tr>
                <td class="text-nowrap">${escapeHtml(type)}</td>
                <td>${escapeHtml(message)}</td>
            </tr>
        `).join('');
        validationDetailCard.classList.remove('d-none');
    }

    function showUploadResult(result) {
        if (!uploadResultAlert || !uploadResultText) return;
        const newCount = Number(result?.new_count || 0);
        const updatedCount = Number(result?.updated_count || 0);
        const unchangedCount = Number(result?.unchanged_count || 0);
        uploadResultText.textContent = `${TEXT.saved} 신규 ${newCount}건, 변경 ${updatedCount}건, 동일 ${unchangedCount}건`;
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
        previewColumns = [];
        currentPreviewToken = '';
        validationSummaryCard?.classList.add('d-none');
        validationDetailCard?.classList.add('d-none');
        destroyPreviewGrid();
        uploadResultAlert?.classList.add('d-none');
        renderSelectedFile(selectedFile());
    });

    dataTypeEl?.addEventListener('change', () => {
        previewRows = [];
        previewColumns = [];
        currentPreviewToken = '';
        validationSummaryCard?.classList.add('d-none');
        validationDetailCard?.classList.add('d-none');
        destroyPreviewGrid();
        uploadResultAlert?.classList.add('d-none');
        void loadFormats().catch((error) => notify('error', error.message));
    });

    if (window.jQuery?.fn?.select2 && dataTypeEl) {
        window.jQuery(dataTypeEl).on('select2:select', () => {
            dataTypeEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    formatSelectEl?.addEventListener('change', () => {
        const formatId = currentFormatId();
        if (formatId !== '') {
            localStorage.setItem(`${LAST_FORMAT_PREFIX}${currentDataType()}`, formatId);
        }
        previewRows = [];
        previewColumns = [];
        currentPreviewToken = '';
        destroyPreviewGrid();
        updateTemplateButtonState();
    });

    formatManageBtn?.addEventListener('click', () => {
        window.location.href = '/ledger/data/formats';
    });

    downloadTemplateBtn?.addEventListener('click', () => {
        const dataType = currentDataType();
        const formatId = currentFormatId();
        if (!dataType || !formatId) {
            notify('warning', TEXT.selectTypeAndFormat);
            return;
        }
        void downloadTemplate(formatId).catch((error) => notify('error', error.message));
    });

    validateBtn?.addEventListener('click', async () => {
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
        validateBtn.disabled = true;
        validateBtn.textContent = '검증 중';
        setSeedUploadEnabled(false);
        try {
            const json = await fetchJson('/api/import/preview', {
                method: 'POST',
                body: formData,
            });
            previewRows = json.data?.rows || [];
            previewColumns = json.data?.format?.columns || [];
            currentPreviewToken = json.data?.preview_token || '';
            renderPreviewRows(previewRows, previewColumns);
        renderValidationSummary(json.data?.summary || {});
        renderValidationDetails(previewRows, json.data?.checks || []);
        setSeedUploadEnabled(Boolean(currentPreviewToken) && Number(json.data?.summary?.check_error || 0) === 0);
        if (Number(json.data?.summary?.error || 0) > 0) {
            notify('warning', '검증 오류가 있는 행은 ERROR 상태로 Seed에 적재됩니다. 자료목록에서 수정 후 READY로 변경하세요.');
        }
            uploadResultAlert?.classList.add('d-none');
            console.log('[dataUpload] validated preview:', json.data?.summary);
        } catch (error) {
            notify('error', error.message);
        } finally {
            validateBtn.disabled = false;
            validateBtn.textContent = '검증';
        }
    });

    seedUploadBtn?.addEventListener('click', async () => {
        if (!currentPreviewToken) {
            notify('warning', '먼저 검증을 실행하세요.');
            return;
        }
        seedUploadBtn.disabled = true;
        seedUploadBtn.textContent = '적재 중';
        try {
            const json = await fetchJson('/api/import/evidence-upload', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ preview_token: currentPreviewToken }),
            });
            currentPreviewToken = '';
            const result = json.data || {};
            showUploadResult(result);
            notify(
                'success',
                `Seed 업로드가 완료되었습니다. 신규 ${Number(result.new_count || 0).toLocaleString('ko-KR')}건, 변경 ${Number(result.updated_count || 0).toLocaleString('ko-KR')}건, 동일 ${Number(result.unchanged_count || 0).toLocaleString('ko-KR')}건`
            );
            setSeedUploadEnabled(false);
        } catch (error) {
            notify('error', error.message);
            setSeedUploadEnabled(true);
        } finally {
            seedUploadBtn.textContent = 'Seed 업로드';
        }
    });

    [previewFilterErrorsEl, previewFilterMappedEl, previewHideUnusedEl].forEach((optionEl) => {
        optionEl?.addEventListener('change', () => renderPreviewRows(previewRows, previewColumns));
    });

    window.addEventListener('resize', () => previewHot?.render());

    onCodeOptionsLoaded(() => {
        if (currentDataType() === '') {
            const firstOption = Array.from(dataTypeEl?.options || []).find((option) => option.value && !option.disabled);
            if (firstOption) {
                dataTypeEl.value = firstOption.value;
                if (window.jQuery?.fn?.select2) {
                    window.jQuery(dataTypeEl).val(firstOption.value).trigger('change.select2');
                }
            }
        }
        void loadFormats().catch((error) => notify('error', error.message));
    });

    renderSelectedFile(selectedFile());
    initCodeSelectControls(document.getElementById('ledgerDataUploadPage') || document)
        .catch((error) => notify('error', error.message));
})();
