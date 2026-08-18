export function createEvidenceUploadModule({
    state,
    API,
    MAX_EXCEL_UPLOAD_BYTES,
    notify,
    updateSummary,
    refreshEvidenceTypeCounts,
    currentStatusColumnPolicy,
}) {
    function currentColumnPolicyPayload() {
        const form = state.refs.excelForm;
        form?.dispatchEvent(new CustomEvent('excel:before-prepare-action', {
            detail: { type: 'template' },
        }));
        const columns = Array.isArray(form?.__excelPreparedColumns?.template)
            ? [...form.__excelPreparedColumns.template]
            : [];
        const settingsState = form?.__excelPreparedPolicy?.template
            && typeof form.__excelPreparedPolicy.template === 'object'
            ? form.__excelPreparedPolicy.template
            : { displayName: {}, requirementPolicy: {} };
        return {
            columns,
            column_display_name: settingsState.displayName && typeof settingsState.displayName === 'object'
                ? settingsState.displayName
                : {},
            column_requirement_policy: settingsState.requirementPolicy && typeof settingsState.requirementPolicy === 'object'
                ? settingsState.requirementPolicy
                : {},
        };
    }

    function currentStatusPolicyPayload() {
        const policyState = typeof currentStatusColumnPolicy === 'function'
            ? currentStatusColumnPolicy()
            : {};
        return {
            column_display_name: policyState?.columnDisplayName && typeof policyState.columnDisplayName === 'object'
                ? policyState.columnDisplayName
                : {},
            column_requirement_policy: policyState?.columnRequirementPolicy && typeof policyState.columnRequirementPolicy === 'object'
                ? policyState.columnRequirementPolicy
                : {},
        };
    }

    function createUploadCancelToken() {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID().replace(/-/g, '');
        }
        return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 14)}`;
    }

    function requestExcelUploadCancel() {
        if (!state.excelUploadCancelToken) return;
        const payload = JSON.stringify({
            upload_cancel_token: state.excelUploadCancelToken,
            preview_token: state.excelUploadPreviewToken || '',
        });
        const blob = new Blob([payload], { type: 'application/json' });
        if (navigator.sendBeacon?.(API.uploadCancel, blob)) {
            return;
        }
        fetch(API.uploadCancel, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: payload,
            keepalive: true,
        }).catch(() => {});
    }

    function validateExcelUploadFile(file) {
        const extension = String(file?.name || '').split('.').pop()?.toLowerCase() || '';
        if (!['xlsx', 'xls'].includes(extension)) {
            return '\uc5d1\uc140 \ud30c\uc77c(.xlsx, .xls)\ub9cc \uc5c5\ub85c\ub4dc\ud560 \uc218 \uc788\uc2b5\ub2c8\ub2e4.';
        }
        const size = Number(file?.size || 0);
        if (size > MAX_EXCEL_UPLOAD_BYTES) {
            const mb = (size / 1024 / 1024).toFixed(1);
            return `\uc5c5\ub85c\ub4dc \ud30c\uc77c\uc774 \ub108\ubb34 \ud07d\ub2c8\ub2e4. \ud604\uc7ac ${mb}MB\uc774\uba70, \uc5d1\uc140 \uc591\uc2dd \uc5c5\ub85c\ub4dc\ub294 25MB \uc774\ud558\ub9cc \ud5c8\uc6a9\ud569\ub2c8\ub2e4. \uc120\ud0dd\ud55c \uc790\ub8cc\uc720\ud615\uc758 \uc591\uc2dd \ud30c\uc77c\uc778\uc9c0 \ud655\uc778\ud558\uc138\uc694.`;
        }
        return '';
    }

    function refreshAfterUploadCancel() {
        window.setTimeout(() => {
            state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        }, 400);
        window.setTimeout(() => {
            state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        }, 1800);
    }

    function uploadResultProgressMessage(result = {}, fallback = '') {
        const total = Number(result.total_count ?? result.total_rows ?? 0);
        const inserted = Number(result.inserted_count ?? result.new_count ?? 0);
        const duplicates = Number(result.duplicate_count ?? result.unchanged_count ?? 0);
        const deletedDuplicates = Number(result.deleted_duplicate_count || 0);
        const conflicts = Number(result.conflict_count || 0);
        const errors = Number(result.error_count || 0);
        if (total <= 0) {
            return fallback || '업로드할 데이터가 없습니다.';
        }

        let message = `총 ${total.toLocaleString('ko-KR')}건 중 신규 ${inserted.toLocaleString('ko-KR')}건을 등록했습니다. `
            + `동일 원본 ${duplicates.toLocaleString('ko-KR')}건은 건너뛰었습니다.`;
        const details = [];
        if (deletedDuplicates > 0) details.push(`삭제자료 중복 ${deletedDuplicates.toLocaleString('ko-KR')}건`);
        if (conflicts > 0) details.push(`충돌 ${conflicts.toLocaleString('ko-KR')}건`);
        if (errors > 0) details.push(`오류 ${errors.toLocaleString('ko-KR')}건`);
        if (details.length > 0) message += ` ${details.join(', ')}이 있습니다.`;
        return message;
    }
    async function uploadExcelFromModal(button) {
        if (state.uploadingExcel) return;

        const form = state.refs.excelForm;
        const fileInput = state.refs.excelModal?.querySelector('input[type="file"]');
        const file = fileInput?.files?.[0] || null;
        const importType = String(form?.dataset.importType || state.currentType || '').trim();
        const progress = window.ExcelManagerProgress;

        if (!importType) {
            notify('warning', '\uc790\ub8cc\uc720\ud615\uc744 \uba3c\uc800 \uc120\ud0dd\ud558\uc138\uc694.');
            return;
        }
        if (!file) {
            notify('warning', '\uc5c5\ub85c\ub4dc\ud560 \uc5d1\uc140 \ud30c\uc77c\uc744 \uc120\ud0dd\ud558\uc138\uc694.');
            return;
        }

        const fileMessage = validateExcelUploadFile(file);
        if (fileMessage) {
            progress?.set(state.refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \ubd88\uac00',
                message: fileMessage,
            });
            notify('warning', fileMessage);
            return;
        }

        state.uploadingExcel = true;
        state.skipExcelUploadCloseConfirm = false;
        state.excelModalCloseRequested = false;
        state.excelUploadCanceled = false;
        state.excelUploadAbortController = null;
        state.excelUploadCancelToken = createUploadCancelToken();
        state.excelUploadPreviewToken = '';
        progress?.lock?.(state.refs.excelModal, true);

        const formData = new FormData();
        formData.append('upload_cancel_token', state.excelUploadCancelToken);
        formData.append('import_type', importType);
        formData.append('type', importType);
        formData.append('file', file);
        const columnPolicyPayload = currentColumnPolicyPayload();
        const templateColumns = columnPolicyPayload.columns;
        if (Array.isArray(templateColumns) && templateColumns.length > 0) {
            formData.append('excel_template_columns', templateColumns.join(','));
        }
        formData.append('column_display_name', JSON.stringify(columnPolicyPayload.column_display_name));
        formData.append('column_requirement_policy', JSON.stringify(columnPolicyPayload.column_requirement_policy));
        const statusPolicyPayload = currentStatusPolicyPayload();
        formData.append('evidence_status_column_display_name', JSON.stringify(statusPolicyPayload.column_display_name));
        formData.append('evidence_status_column_requirement_policy', JSON.stringify(statusPolicyPayload.column_requirement_policy));
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = '\uc5c5\ub85c\ub4dc \uc911';

        try {
            let uploadJson = progress?.request
                ? await progress.request(form?.dataset.uploadUrl || API.upload, formData, state.refs.excelModal)
                : await (async () => {
                    const uploadResponse = await fetch(form?.dataset.uploadUrl || API.upload, {
                        method: 'POST',
                        body: formData,
                    });
                    const json = await uploadResponse.json().catch(() => ({}));
                    if (!uploadResponse.ok) {
                        throw new Error(json.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.');
                    }
                    return json;
                })();

            if (uploadJson.requires_confirmation && uploadJson.confirmation_code === 'REQUIRED_FIELD_MISSING') {
                const confirmed = window.confirm(uploadJson.message || '\ud544\uc218 \ud56d\ubaa9 \ub204\ub77d\uc774 \uc788\uc2b5\ub2c8\ub2e4. \uacc4\uc18d \uc5c5\ub85c\ub4dc\ud560\uae4c\uc694?');
                if (!confirmed) {
                    progress?.set(state.refs.excelModal, {
                        percent: 100,
                        title: '\uc5c5\ub85c\ub4dc \ucde8\uc18c',
                        message: '\ud544\uc218 \ud56d\ubaa9 \ub204\ub77d\uc73c\ub85c \uc5c5\ub85c\ub4dc\ub97c \ucde8\uc18c\ud588\uc2b5\ub2c8\ub2e4.',
                    });
                    return;
                }

                const previewToken = String(uploadJson.data?.preview_token || '').trim();
                if (!previewToken) {
                    throw new Error('\uac80\uc99d \uacb0\uacfc \ud1a0\ud070\uc774 \uc5c6\uc2b5\ub2c8\ub2e4. \ub2e4\uc2dc \uc5c5\ub85c\ub4dc\ub97c \uc2dc\ub3c4\ud558\uc138\uc694.');
                }

                state.excelUploadPreviewToken = previewToken;
                uploadJson = await progress.saveChunks(form?.dataset.uploadUrl || API.upload, {
                    modal: state.refs.excelModal,
                    totalRows: Number(uploadJson.data?.total_rows || 0),
                    chunkSize: 5,
                    initialPayload: {
                        preview_token: previewToken,
                        allow_required_missing: true,
                        upload_cancel_token: state.excelUploadCancelToken,
                        import_type: importType,
                        type: importType,
                    },
                    isCanceled: () => state.excelUploadCanceled,
                });
                state.excelUploadAbortController = null;
            }

            if (uploadJson.success === false) {
                throw new Error(uploadJson.message || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.');
            }

            const result = uploadJson.data || {};
            const completedMessage = uploadResultProgressMessage(result, uploadJson.message);

            progress?.set(state.refs.excelModal, {
                percent: 100,
                title: '\uc5c5\ub85c\ub4dc \uc644\ub8cc',
                message: completedMessage || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5c8\uc2b5\ub2c8\ub2e4.',
            });
            notify('success', completedMessage || '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5c8\uc2b5\ub2c8\ub2e4.');
            state.skipExcelUploadCloseConfirm = true;
            bootstrap.Modal.getInstance(state.refs.excelModal)?.hide();
            state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        } catch (error) {
            const message = error instanceof Error ? error.message : '\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.';
            progress?.set(state.refs.excelModal, {
                percent: 100,
                title: state.excelUploadCanceled ? '\uc5c5\ub85c\ub4dc \ucde8\uc18c' : '\uc5c5\ub85c\ub4dc \uc2e4\ud328',
                message,
            });
            notify(state.excelUploadCanceled ? 'warning' : 'error', message);
        } finally {
            state.uploadingExcel = false;
            state.skipExcelUploadCloseConfirm = false;
            state.excelModalCloseRequested = false;
            state.excelUploadAbortController = null;
            state.excelUploadCancelToken = '';
            state.excelUploadPreviewToken = '';
            progress?.lock?.(state.refs.excelModal, false);
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function bindUploadEvents() {
        state.refs.excelModal?.addEventListener('click', (event) => {
            const dismissButton = event.target.closest('[data-bs-dismiss="modal"], .btn-close');
            if (dismissButton && state.uploadingExcel) {
                state.excelModalCloseRequested = true;
            }

            const button = event.target.closest('.btn-upload-excel');
            if (!button) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            void uploadExcelFromModal(button).catch((error) => notify('error', error.message));
        }, true);

        state.refs.excelModal?.addEventListener('hidden.bs.modal', () => {
            state.excelModalCloseRequested = false;
        });

        state.refs.excelModal?.addEventListener('hide.bs.modal', (event) => {
            if (state.skipExcelUploadCloseConfirm) return;
            if (!state.uploadingExcel) return;
            if (!state.excelModalCloseRequested) {
                event.preventDefault();
                return;
            }
            const confirmed = window.confirm('\uc5c5\ub85c\ub4dc\uac00 \uc9c4\ud589 \uc911\uc785\ub2c8\ub2e4. \uc694\uccad\uc744 \ucde8\uc18c\ud558\uace0 \ub2eb\uc744\uae4c\uc694?');
            if (!confirmed) {
                state.excelModalCloseRequested = false;
                event.preventDefault();
                return;
            }
            state.excelUploadCanceled = true;
            window.ExcelManagerProgress?.abort(state.refs.excelModal);
            state.excelUploadAbortController?.abort();
            requestExcelUploadCancel();
            refreshAfterUploadCancel();
        });

        window.addEventListener('beforeunload', () => {
            if (state.uploadingExcel) {
                state.excelUploadCanceled = true;
                window.ExcelManagerProgress?.abort(state.refs.excelModal);
                state.excelUploadAbortController?.abort();
                requestExcelUploadCancel();
            }
        });
    }

    return {
        bindUploadEvents,
    };
}
