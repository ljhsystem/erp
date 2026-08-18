function preparedExcelAction(excelForm, type) {
    excelForm.dispatchEvent(new CustomEvent('excel:before-prepare-action', { detail: { type } }));
    const key = type === 'template' ? 'excelTemplateColumns' : 'excelDownloadColumns';
    let columns = [];
    try {
        const parsed = JSON.parse(excelForm.dataset[key] || '[]');
        columns = Array.isArray(parsed) ? parsed : [];
    } catch {
        columns = [];
    }
    const policy = excelForm.__excelPreparedPolicy?.[type] || {};
    return {
        columns,
        columnDisplayName: { ...(policy.displayName || {}) },
        columnRequirementPolicy: type === 'template' ? { ...(policy.requirementPolicy || {}) } : {},
    };
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

export async function initPersonalExpenseExcelManager({
    api,
    createSettingsCore,
    form,
    getItemGrid,
    isReadOnly,
    notify,
    prepareItemRows,
    refreshSummary,
}) {
    const modalNode = document.getElementById('personalExpenseExcelModal');
    const excelForm = document.getElementById('personal-expense-excel-upload-form');
    const button = document.getElementById('expenseExcelManager');
    if (!modalNode || !excelForm || !button || !window.bootstrap) return null;

    const excelModal = window.bootstrap.Modal.getOrCreateInstance(modalNode, { focus: false });
    const manager = await createSettingsCore({
        domain: 'personal-expense-item',
        userSettingPageKey: 'approval.personal_expense',
        formSelector: '#personal-expense-excel-upload-form',
        metaDomain: 'personal-expense-item',
        description: '개인경비 신청 및 결재',
    });
    button.addEventListener('click', () => {
        if (isReadOnly()) {
            notify('warning', '수정 가능한 신청서에서만 엑셀 업로드를 사용할 수 있습니다.');
            return;
        }
        manager?.reload?.();
        excelModal.show();
    });

    excelForm.addEventListener('click', event => {
        const actionButton = event.target.closest('button');
        if (!actionButton) return;
        if (actionButton.classList.contains('btn-download-all')) {
            event.preventDefault();
            event.stopPropagation();
            const prepared = preparedExcelAction(excelForm, 'download');
            const body = new FormData();
            body.set('rows', JSON.stringify(getItemGrid()?.getData?.() || []));
            body.set('columns', prepared.columns.join(','));
            body.set('column_display_name', JSON.stringify(prepared.columnDisplayName));
            void fetch(api.excelDownload, { method: 'POST', body }).then(async response => {
                if (!response.ok) throw new Error((await response.text()) || '엑셀 다운로드 중 오류가 발생했습니다.');
                downloadBlob(await response.blob(), 'personal_expense_items.xlsx');
            }).catch(error => notify('error', error.message));
        }
        if (actionButton.classList.contains('btn-upload-excel')) {
            event.preventDefault();
            event.stopPropagation();
            if (isReadOnly()) {
                notify('warning', '수정 가능한 신청서에서만 엑셀 업로드를 사용할 수 있습니다.');
                return;
            }
            const file = excelForm.querySelector('input[type="file"]')?.files?.[0];
            if (!file) {
                notify('warning', '업로드할 엑셀 파일을 선택해 주세요.');
                return;
            }
            const prepared = preparedExcelAction(excelForm, 'template');
            const body = new FormData(excelForm);
            body.set('personal_expense_id', form.elements.id.value);
            body.set('excel_template_columns', prepared.columns.join(','));
            body.set('column_display_name', JSON.stringify(prepared.columnDisplayName));
            body.set('column_requirement_policy', JSON.stringify(prepared.columnRequirementPolicy));
            void window.ExcelManagerProgress.request(api.excelUpload, body, modalNode).then(result => {
                if (!result?.success) throw new Error(result?.message || '엑셀 업로드 중 오류가 발생했습니다.');
                const rows = Array.isArray(result.data?.rows) ? result.data.rows : [];
                if (!rows.length) throw new Error('업로드할 개인경비 아이템이 없습니다.');
                getItemGrid().loadData(prepareItemRows(rows));
                refreshSummary();
                excelModal.hide();
                notify('success', result.message);
            }).catch(error => notify('error', error.message)).finally(() => {
                window.ExcelManagerProgress.lock(modalNode, false);
            });
        }
    });
    return manager;
}
