export function createEvidenceUploadModule({
    state,
    API,
    MAX_EXCEL_UPLOAD_BYTES,
    notify,
    updateSummary,
    refreshEvidenceTypeCounts,
    evidenceStatusTableSettingsStorageKey,
    readDataTableSettingsState,
    normalizeEvidenceType,
    defaultEvidenceTypeCode,
    evidenceMetaDomain,
}) {
    function currentColumnPolicyPayload() {
        const fallbackType = defaultEvidenceTypeCode();
        const normalizedType = normalizeEvidenceType(state.currentType || fallbackType);
        const storageKey = evidenceStatusTableSettingsStorageKey(normalizedType);
        const userSettingPageKey = evidenceMetaDomain(normalizedType);
        const settingsState = readDataTableSettingsState(storageKey, {
            metaDomain: userSettingPageKey,
            userSettingPageKey,
        }) || {};
        return {
            column_display_name: settingsState.columnDisplayName && typeof settingsState.columnDisplayName === 'object'
                ? settingsState.columnDisplayName
                : {},
            column_requirement_policy: settingsState.columnRequirementPolicy && typeof settingsState.columnRequirementPolicy === 'object'
                ? settingsState.columnRequirementPolicy
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
        const totalRows = Number(result.total_rows || 0);
        const processed = Number(result.processed_count || 0)
            || Number(result.new_count || 0)
            + Number(result.updated_count || 0)
            + Number(result.unchanged_count || 0)
            + Number(result.error_count || 0);
        const skipped = Number(result.skipped_count || Math.max(0, totalRows - processed));
        const parts = [
            `\uc2e4\uc81c \ucc98\ub9ac ${processed.toLocaleString('ko-KR')}\uac74`,
            `\uc2e0\uaddc ${Number(result.new_count || 0).toLocaleString('ko-KR')}\uac74`,
            `\ubcc0\uacbd ${Number(result.updated_count || 0).toLocaleString('ko-KR')}\uac74`,
            `\ub3d9\uc77c ${Number(result.unchanged_count || 0).toLocaleString('ko-KR')}\uac74`,
        ];
        if (Number(result.error_count || 0) > 0) {
            parts.push(`\uc624\ub958 ${Number(result.error_count || 0).toLocaleString('ko-KR')}\uac74`);
        }
        if (Number(result.protected_update_count || 0) > 0) {
            const protectedParts = [];
            if (Number(result.protected_transaction_count || 0) > 0) {
                protectedParts.push(`\uac70\ub798\uc0dd\uc131 ${Number(result.protected_transaction_count || 0).toLocaleString('ko-KR')}\uac74`);
            }
            if (Number(result.protected_voucher_count || 0) > 0) {
                protectedParts.push(`\uc804\ud45c\uc0dd\uc131 ${Number(result.protected_voucher_count || 0).toLocaleString('ko-KR')}\uac74`);
            }
            parts.push(`\uc0dd\uc131\uc644\ub8cc \uc218\uc815\uc81c\uc678 ${Number(result.protected_update_count || 0).toLocaleString('ko-KR')}\uac74${protectedParts.length > 0 ? `(${protectedParts.join(', ')})` : ''}`);
        }
        const otherSkipped = Math.max(0, skipped - Number(result.protected_update_count || 0));
        if (otherSkipped > 0) {
            parts.push(`\uc81c\uc678 ${otherSkipped.toLocaleString('ko-KR')}\uac74`);
        }
        if (totalRows > 0 && totalRows !== processed) {
            return `\uc5d1\uc140 \uac10\uc9c0 ${totalRows.toLocaleString('ko-KR')}\ud589 / ${parts.join(', ')}`;
        }
        if (processed > 0) {
            return `\uc5c5\ub85c\ub4dc \uc644\ub8cc: ${parts.join(', ')}`;
        }
        return fallback || '\uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5b4 \ubaa9\ub85d\uc744 \uc0c8\ub85c\uace0\uce68\ud569\ub2c8\ub2e4.';
    }

    function dualWriteUploadMessage(result = {}) {
        const status = String(result?.dual_write_status || '').trim();
        if (!status) return '';
        const successCount = Number(result?.dual_write_success_count || 0);
        const failedCount = Number(result?.dual_write_failed_count || 0);
        return `Dual write: ${status} (success ${successCount}, failed ${failedCount})`;
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
        const templateColumns = state.refs.excelForm?.__excelPreparedColumns?.template || [];
        const columnPolicyPayload = currentColumnPolicyPayload();
        if (Array.isArray(templateColumns) && templateColumns.length > 0) {
            formData.append('excel_template_columns', templateColumns.join(','));
        }
        formData.append('column_display_name', JSON.stringify(columnPolicyPayload.column_display_name));
        formData.append('column_requirement_policy', JSON.stringify(columnPolicyPayload.column_requirement_policy));
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
            const dualWriteMessage = dualWriteUploadMessage(result);
            const completedMessage = [
                uploadResultProgressMessage(result, uploadJson.message),
                dualWriteMessage,
            ].filter(Boolean).join('\n');

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
