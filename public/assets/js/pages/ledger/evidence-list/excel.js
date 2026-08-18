export function createEvidenceExcelModule({
    state,
    API,
    createExcelManagerSettingsCore,
    normalizeEvidenceType,
    defaultEvidenceTypeCode,
    currentConfig,
    evidenceMetaDomain,
}) {
    function resolveEvidenceType(type = state.currentType) {
        const fallbackType = defaultEvidenceTypeCode();
        return normalizeEvidenceType(type || fallbackType) || fallbackType;
    }

    function resolveExcelSettingsDomain(type = state.currentType) {
        return String(evidenceMetaDomain(resolveEvidenceType(type))).trim();
    }

    function resetPreparedColumns() {
        if (!state.refs.excelForm) {
            return;
        }

        state.refs.excelForm.dataset.excelTemplateColumns = JSON.stringify([]);
        state.refs.excelForm.dataset.excelDownloadColumns = JSON.stringify([]);
        state.refs.excelForm.__excelPreparedColumns = {
            template: [],
            download: [],
        };
    }

    function destroyExcelManagerCore() {
        state.excelManagerSettingsCore?.destroy?.();
        state.excelManagerSettingsCore = null;
        resetPreparedColumns();
    }

    async function ensureEvidenceExcelManagerCore(type = state.currentType) {
        if (!state.refs.excelForm) {
            return null;
        }

        const settingsDomain = resolveExcelSettingsDomain(type);
        if (settingsDomain === '') {
            destroyExcelManagerCore();
            return null;
        }

        if (!state.excelManagerSettingsCore || state.excelManagerSettingsCore.domain !== settingsDomain) {
            state.excelManagerSettingsCore?.destroy?.();
            state.excelManagerSettingsCore = await createExcelManagerSettingsCore({
                domain: settingsDomain,
                userSettingPageKey: settingsDomain,
                formSelector: '#dataExcelForm',
                metaDomain: settingsDomain,
            });
            return state.excelManagerSettingsCore;
        }

        await state.excelManagerSettingsCore.reload?.();
        return state.excelManagerSettingsCore;
    }

    function bindExcelEvents() {
        // Common excel-manager core binds its own UI events.
    }

    function syncExcelManager(config = currentConfig()) {
        if (!state.refs.excelForm) {
            return;
        }

        state.refs.excelForm.dataset.templateUrl = `/api/import/template?type=${encodeURIComponent(resolveEvidenceType(state.currentType))}`;
        state.refs.excelForm.dataset.downloadUrl = state.currentType
            ? `${API.download}?import_type=${encodeURIComponent(state.currentType)}`
            : '';
        state.refs.excelForm.dataset.uploadUrl = API.upload;
        state.refs.excelForm.dataset.importType = state.currentType || '';

        const subtitle = state.refs.excelModal?.querySelector('.excel-modal-subtitle');
        if (subtitle) {
            subtitle.textContent = `${config.label} / 자료유형 기준`;
        }

        const disabled = !state.currentType;

        const templateBtn = state.refs.excelModal?.querySelector('.btn-template-download');
        if (templateBtn) {
            templateBtn.disabled = disabled;
        }

        const downloadBtn = state.refs.excelModal?.querySelector('.btn-download-all');
        if (downloadBtn) {
            downloadBtn.disabled = disabled;
            downloadBtn.title = '';
        }

        const uploadBtn = state.refs.excelModal?.querySelector('.btn-upload-excel');
        if (uploadBtn) {
            uploadBtn.disabled = disabled;
            uploadBtn.title = '';
        }
    }

    async function prepareExcelManager(config = currentConfig()) {
        syncExcelManager(config);
        const excelManagerCore = await ensureEvidenceExcelManagerCore(state.currentType);
        const disabled = !state.currentType || !excelManagerCore;
        state.refs.excelModal?.querySelectorAll('.btn-template-download, .btn-download-all, .btn-upload-excel')
            .forEach((button) => {
                button.disabled = disabled;
            });
        return excelManagerCore;
    }

    return {
        bindExcelEvents,
        syncExcelManager,
        prepareExcelManager,
    };
}
