// Common Excel management modal events.
(() => {
    'use strict';

    const AppEvents = window.AppEvents || {};
    const onDocument = AppEvents.onDocument || ((type, handler, options = false) => {
        document.addEventListener(type, handler, options);
        return () => document.removeEventListener(type, handler, options);
    });

    function t(value) {
        return value;
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        console[type === 'error' ? 'error' : 'log'](message);
    }

    function formatBytes(bytes) {
        const size = Number(bytes) || 0;
        if (size >= 1024 * 1024) return `${(size / 1024 / 1024).toFixed(1)}MB`;
        if (size >= 1024) return `${(size / 1024).toFixed(1)}KB`;
        return `${size}B`;
    }

    function formatElapsed(ms) {
        const totalSeconds = Math.max(0, Math.floor((Number(ms) || 0) / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    function messageFromResponseText(text, fallback) {
        const raw = String(text || '').trim();
        if (!raw) return fallback;
        const cleaned = raw
            .replace(/<style[\s\S]*?<\/style>/gi, ' ')
            .replace(/<script[\s\S]*?<\/script>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/&nbsp;/g, ' ')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&amp;/g, '&')
            .replace(/\s+/g, ' ')
            .trim();
        if (/Call to undefined method|Fatal error|Parse error|Throwable|Exception/i.test(cleaned)) {
            return t('\uc11c\ubc84 \ucc98\ub9ac \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4. \uad00\ub9ac\uc790\uc5d0\uac8c \ubb38\uc758\ud558\uc138\uc694.');
        }
        return cleaned ? cleaned.slice(0, 240) : fallback;
    }

    function setUploadProgress(modal, options = {}) {
        const panel = modal?.querySelector('.excel-spinner');
        if (!panel) return;

        const {
            visible = true,
            percent = 0,
            percentLabel = '',
            title = t('\uc5c5\ub85c\ub4dc \uc900\ube44 \uc911'),
            message = t('\ud30c\uc77c\uc744 \ud655\uc778\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.'),
            indeterminate = false,
        } = options;

        const value = Math.max(0, Math.min(100, Number(percent) || 0));
        const bar = panel.querySelector('[data-excel-progress-bar]');
        const percentEl = panel.querySelector('[data-excel-progress-percent]');
        const titleEl = panel.querySelector('[data-excel-progress-title]');
        const messageEl = panel.querySelector('[data-excel-progress-message]');
        const progress = panel.querySelector('.excel-progress');

        panel.style.display = visible ? 'block' : 'none';
        panel.classList.toggle('is-indeterminate', Boolean(indeterminate));
        if (bar) bar.style.width = `${value}%`;
        if (percentEl) percentEl.textContent = percentLabel || `${Math.round(value)}%`;
        if (titleEl) titleEl.textContent = title;
        if (messageEl) messageEl.textContent = message;
        if (progress) progress.setAttribute('aria-valuenow', String(Math.round(value)));
    }

    const activeUploads = new WeakMap();

    function setModalBusy(modal, busy) {
        if (!modal) return;
        modal.classList.toggle('is-excel-uploading', Boolean(busy));
        modal.setAttribute('aria-busy', busy ? 'true' : 'false');

        const lockable = modal.querySelectorAll('.btn-template-download, .btn-download-all, .btn-upload-excel, .excel-file-input');
        lockable.forEach((element) => {
            if (busy) {
                if (!element.dataset.excelWasDisabled) {
                    element.dataset.excelWasDisabled = element.disabled ? '1' : '0';
                }
                element.disabled = true;
                return;
            }
            const wasDisabled = element.dataset.excelWasDisabled === '1';
            delete element.dataset.excelWasDisabled;
            element.disabled = wasDisabled;
        });

        modal.querySelectorAll('.excel-dropzone').forEach((dropzone) => {
            dropzone.classList.toggle('is-disabled', Boolean(busy));
            dropzone.setAttribute('aria-disabled', busy ? 'true' : 'false');
        });
    }

    function isActiveUpload(handle) {
        if (!handle) return false;
        if (typeof handle.readyState === 'number') {
            return handle.readyState !== XMLHttpRequest.DONE;
        }
        return Boolean(handle.active);
    }

    function uploadFormData(url, formData, modal) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            let processingStartedAt = 0;
            let processingTimer = null;
            let uploadedBytes = 0;
            let totalBytes = 0;

            const stopProcessingTimer = () => {
                if (processingTimer) {
                    clearInterval(processingTimer);
                    processingTimer = null;
                }
            };
            const clearActiveUpload = () => {
                if (modal) {
                    activeUploads.delete(modal);
                }
            };

            const startProcessingTimer = () => {
                processingStartedAt = Date.now();
                stopProcessingTimer();
                processingTimer = setInterval(() => {
                    setUploadProgress(modal, {
                        percent: 100,
                        percentLabel: t('\uc800\uc7a5 \uc911'),
                        title: t('\uc11c\ubc84 \uc800\uc7a5 \uc911'),
                        message: `${t('\ud30c\uc77c \uc804\uc1a1\uc740 \uc644\ub8cc\ub418\uc5c8\uace0, \uc11c\ubc84\uc5d0\uc11c \ub370\uc774\ud130\ub97c \uc800\uc7a5\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.')} ${t('\uacbd\uacfc')} ${formatElapsed(Date.now() - processingStartedAt)}`,
                        indeterminate: true,
                    });
                }, 1000);
            };

            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable) {
                    setUploadProgress(modal, {
                        percent: 12,
                        percentLabel: t('\uc804\uc1a1 \uc911'),
                        title: t('\uc11c\ubc84\ub85c \uc804\uc1a1 \uc911'),
                        message: t('\ube0c\ub77c\uc6b0\uc800\uac00 \uc804\uc1a1 \ud06c\uae30\ub97c \ud655\uc778\ud558\uc9c0 \ubabb\ud574 \uc804\uc1a1 \uc0c1\ud0dc\ub9cc \ud45c\uc2dc\ud569\ub2c8\ub2e4.'),
                        indeterminate: true,
                    });
                    return;
                }

                uploadedBytes = event.loaded;
                totalBytes = event.total;
                const uploadPercent = totalBytes > 0 ? uploadedBytes / totalBytes : 0;
                setUploadProgress(modal, {
                    percent: uploadPercent * 100,
                    percentLabel: `${Math.round(uploadPercent * 100)}%`,
                    title: t('\uc11c\ubc84\ub85c \uc804\uc1a1 \uc911'),
                    message: `${formatBytes(uploadedBytes)} / ${formatBytes(totalBytes)} ${t('\uc804\uc1a1')} (${Math.round(uploadPercent * 100)}%)`,
                });
            });

            xhr.upload.addEventListener('load', () => {
                setUploadProgress(modal, {
                    percent: 100,
                    percentLabel: t('\uc800\uc7a5 \uc911'),
                    title: t('\uc11c\ubc84 \uc800\uc7a5 \uc911'),
                    message: totalBytes > 0
                        ? `${formatBytes(totalBytes)} ${t('\uc804\uc1a1 \uc644\ub8cc. \uc11c\ubc84\uc5d0\uc11c \ub370\uc774\ud130\ub97c \uc800\uc7a5\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.')} ${t('\uacbd\uacfc')} 00:00`
                        : `${t('\ud30c\uc77c \uc804\uc1a1\uc774 \uc644\ub8cc\ub418\uc5c8\uace0 \uc11c\ubc84\uc5d0\uc11c \ub370\uc774\ud130\ub97c \uc800\uc7a5\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.')} ${t('\uacbd\uacfc')} 00:00`,
                    indeterminate: true,
                });
                startProcessingTimer();
            });

            xhr.addEventListener('load', () => {
                stopProcessingTimer();
                let json = {};
                try {
                    json = xhr.responseText ? JSON.parse(xhr.responseText) : {};
                } catch (error) {
                    reject(new Error(messageFromResponseText(xhr.responseText, t('\uc5c5\ub85c\ub4dc \ucc98\ub9ac \uc911 \uc11c\ubc84 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.'))));
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    clearActiveUpload();
                    reject(new Error(json.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.')));
                    return;
                }

                const totalRows = Number(json?.data?.total_rows || json?.data?.summary?.total || 0);
                if (totalRows > 0) {
                    setUploadProgress(modal, {
                        percent: 100,
                        percentLabel: t('\ucc98\ub9ac \uc644\ub8cc'),
                        title: json?.requires_confirmation ? t('\ud655\uc778 \ud544\uc694') : t('\uc11c\ubc84 \ucc98\ub9ac \uc644\ub8cc'),
                        message: `${t('\uc5d1\uc140\uc5d0\uc11c \uac10\uc9c0\ud55c \ub370\uc774\ud130')} ${totalRows.toLocaleString('ko-KR')}${t('\ud589\uc744 \ud655\uc778\ud588\uc2b5\ub2c8\ub2e4.')}`,
                        indeterminate: Boolean(json?.requires_confirmation),
                    });
                }

                clearActiveUpload();
                resolve(json);
            });

            xhr.addEventListener('error', () => {
                stopProcessingTimer();
                clearActiveUpload();
                reject(new Error(t('\ud30c\uc77c \uc804\uc1a1 \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.')));
            });
            xhr.addEventListener('abort', () => {
                stopProcessingTimer();
                clearActiveUpload();
                reject(new Error(t('\ud30c\uc77c \uc804\uc1a1\uc774 \ucde8\uc18c\ub418\uc5c8\uc2b5\ub2c8\ub2e4.')));
            });
            xhr.open('POST', url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (modal) {
                setModalBusy(modal, true);
                activeUploads.set(modal, xhr);
            }
            xhr.send(formData);
        });
    }

    async function postJson(url, payload = {}, options = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(payload),
            signal: options.signal,
        });
        const text = await response.text();
        let json = {};
        try {
            json = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error(messageFromResponseText(text, t('\uc11c\ubc84 \uc751\ub2f5\uc744 \ud655\uc778\ud560 \uc218 \uc5c6\uc2b5\ub2c8\ub2e4.')));
        }
        if (!response.ok || json.success === false) {
            throw new Error(json.message || t('\uc5c5\ub85c\ub4dc \ucc98\ub9ac\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.'));
        }
        return json;
    }

    async function saveChunks(url, options = {}) {
        const {
            modal = null,
            totalRows = 0,
            chunkSize = 5,
            initialPayload = {},
            isCanceled = () => false,
        } = options;
        const total = Math.max(0, Number(totalRows) || 0);
        const size = Math.max(1, Math.min(100, Number(chunkSize) || 5));
        const aggregate = {
            total_rows: total,
            processed_count: 0,
            new_count: 0,
            updated_count: 0,
            unchanged_count: 0,
            error_count: 0,
            skipped_count: 0,
            protected_update_count: 0,
            protected_transaction_count: 0,
            protected_voucher_count: 0,
        };
        const startedAt = Date.now();
        let offset = 0;
        let lastJson = null;
        let currentMessage = '';
        let timer = null;

        const render = (nextOffset = offset, title = t('\uc11c\ubc84 \uc800\uc7a5 \uc911')) => {
            const base = aggregate.total_rows > 0 ? aggregate.total_rows : total;
            const percent = base > 0 ? Math.round((nextOffset / base) * 100) : 0;
            currentMessage = `${nextOffset.toLocaleString('ko-KR')} / ${base.toLocaleString('ko-KR')}${t('\ud589 \uc800\uc7a5 \uc911')} (${t('\uacbd\uacfc')} ${formatElapsed(Date.now() - startedAt)})`;
            setUploadProgress(modal, {
                percent,
                percentLabel: `${percent}%`,
                title,
                message: currentMessage,
                indeterminate: false,
            });
        };
        const stopTimer = () => {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        };

        render(0);
        timer = setInterval(() => render(offset), 1000);
        setModalBusy(modal, true);

        try {
            while (offset < total) {
                if (isCanceled()) {
                    throw new DOMException(t('\uc5c5\ub85c\ub4dc\uac00 \ucde8\uc18c\ub418\uc5c8\uc2b5\ub2c8\ub2e4.'), 'AbortError');
                }
                const controller = new AbortController();
                const handle = {
                    active: true,
                    abort() {
                        controller.abort();
                        this.active = false;
                    },
                };
                if (modal) {
                    activeUploads.set(modal, handle);
                }
                render(offset);

                try {
                    lastJson = await postJson(url, {
                        ...initialPayload,
                        chunked_upload: true,
                        chunk_offset: offset,
                        chunk_size: size,
                    }, { signal: controller.signal });
                } finally {
                    handle.active = false;
                    if (modal && activeUploads.get(modal) === handle) {
                        activeUploads.delete(modal);
                    }
                }

                const data = lastJson?.data || {};
                for (const key of ['processed_count', 'new_count', 'updated_count', 'unchanged_count', 'error_count', 'skipped_count', 'protected_update_count', 'protected_transaction_count', 'protected_voucher_count']) {
                    aggregate[key] += Number(data[key] || 0);
                }
                aggregate.total_rows = Number(data.total_rows || total || aggregate.total_rows);
                const nextOffset = Number(data.next_offset || 0);
                offset = nextOffset > offset ? nextOffset : Math.min(total, offset + size);

                const done = Boolean(data.done) || offset >= aggregate.total_rows;
                render(offset, done ? t('\uc11c\ubc84 \ucc98\ub9ac \uc644\ub8cc') : t('\uc11c\ubc84 \uc800\uc7a5 \uc911'));
                if (done) {
                    break;
                }
            }

            return {
                success: true,
                data: aggregate,
                message: lastJson?.message || t('\uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5c8\uc2b5\ub2c8\ub2e4.'),
            };
        } finally {
            stopTimer();
        }
    }

    window.ExcelManagerProgress = {
        set: setUploadProgress,
        lock: setModalBusy,
        request: uploadFormData,
        saveChunks,
        abort(modal) {
            const active = modal ? activeUploads.get(modal) : null;
            if (active && typeof active.abort === 'function' && isActiveUpload(active)) {
                active.abort();
                return true;
            }
            return false;
        },
        isActive(modal) {
            const active = modal ? activeUploads.get(modal) : null;
            return isActiveUpload(active);
        },
        reset(modal) {
            setUploadProgress(modal, {
                visible: false,
                percent: 0,
                title: t('\uc5c5\ub85c\ub4dc \uc900\ube44 \uc911'),
                message: t('\ud30c\uc77c\uc744 \ud655\uc778\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.'),
                indeterminate: false,
            });
            setModalBusy(modal, false);
        },
    };

    function updateDropzoneFileName(input) {
        const dropzone = input?.closest('.excel-dropzone');
        const fileNameEl = dropzone?.querySelector('[data-excel-file-name]');
        if (!fileNameEl) return;
        fileNameEl.textContent = input?.files?.[0]?.name || t('\uc120\ud0dd\ub41c \ud30c\uc77c \uc5c6\uc74c');
        dropzone?.classList.toggle('has-file', Boolean(input?.files?.length));
    }

    onDocument('change', (event) => {
        const input = event.target.closest('.excel-file-input');
        if (!input) return;
        updateDropzoneFileName(input);
    });

    onDocument('dragover', (event) => {
        const dropzone = event.target.closest('.excel-dropzone');
        if (!dropzone) return;
        if (dropzone.closest('.modal')?.classList.contains('is-excel-uploading')) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        dropzone.classList.add('is-dragover');
    });

    onDocument('dragleave', (event) => {
        const dropzone = event.target.closest('.excel-dropzone');
        if (!dropzone || dropzone.contains(event.relatedTarget)) return;
        dropzone.classList.remove('is-dragover');
    });

    onDocument('drop', (event) => {
        const dropzone = event.target.closest('.excel-dropzone');
        if (!dropzone) return;
        event.preventDefault();
        dropzone.classList.remove('is-dragover');
        if (dropzone.closest('.modal')?.classList.contains('is-excel-uploading')) {
            return;
        }

        const input = dropzone.querySelector('.excel-file-input');
        const file = event.dataTransfer?.files?.[0];
        if (!input || !file) return;

        const extension = file.name.split('.').pop()?.toLowerCase();
        if (!['xlsx', 'xls'].includes(extension || '')) {
            notify('warning', t('\uc5d1\uc140 \ud30c\uc77c\ub9cc \uc5c5\ub85c\ub4dc\ud560 \uc218 \uc788\uc2b5\ub2c8\ub2e4.'));
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        updateDropzoneFileName(input);
    });

    onDocument('click', async (event) => {
        const btn = event.target.closest('button');
        if (!btn) return;

        const modal = btn.closest('.modal');
        if (!modal) return;

        const form = modal.querySelector('form');
        if (!form) return;

        const fileInput = modal.querySelector('input[type="file"]');
        if (modal.classList.contains('is-excel-uploading') && !btn.matches('[data-bs-dismiss="modal"], .btn-close')) {
            event.preventDefault();
            return;
        }

        if (btn.classList.contains('btn-template-download')) {
            if (form.dataset.templateUrl) {
                window.location.href = form.dataset.templateUrl;
            }
            return;
        }

        if (btn.classList.contains('btn-download-all')) {
            if (form.dataset.downloadUrl) {
                window.location.href = form.dataset.downloadUrl;
            }
            return;
        }

        if (!btn.classList.contains('btn-upload-excel')) {
            return;
        }

        if (!fileInput || !fileInput.files.length) {
            notify('warning', t('\uc5c5\ub85c\ub4dc\ud560 \uc5d1\uc140 \ud30c\uc77c\uc744 \uc120\ud0dd\ud558\uc138\uc694.'));
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData(form);
        setModalBusy(modal, true);
        setUploadProgress(modal, {
            percent: 0,
            percentLabel: '0%',
            title: t('\ud30c\uc77c \uc900\ube44 \uc911'),
            message: `${file.name} (${formatBytes(file.size)}) ${t('\ud30c\uc77c\uc744 \uc5c5\ub85c\ub4dc \uc694\uccad\uc73c\ub85c \uc900\ube44\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.')}`,
        });

        try {
            await new Promise((resolve) => requestAnimationFrame(resolve));
            const json = await uploadFormData(form.dataset.uploadUrl, formData, modal);

            if (json.success) {
                setUploadProgress(modal, {
                    percent: 100,
                    percentLabel: '100%',
                    title: t('\uc5c5\ub85c\ub4dc \uc644\ub8cc'),
                    message: json.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5c8\uc2b5\ub2c8\ub2e4.'),
                });
                notify('success', json.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uac00 \uc644\ub8cc\ub418\uc5c8\uc2b5\ub2c8\ub2e4.'));

                const instance = bootstrap.Modal.getInstance(modal)
                    || new bootstrap.Modal(modal);

                setTimeout(() => {
                    instance.hide();
                    document.dispatchEvent(new Event('excel:uploaded'));
                }, 250);
                return;
            }

            setUploadProgress(modal, {
                percent: 100,
                percentLabel: t('\uc2e4\ud328'),
                title: t('\uc5c5\ub85c\ub4dc \uc2e4\ud328'),
                message: json.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.'),
            });
            notify('error', json.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.'));
        } catch (error) {
            console.error(error);
            setUploadProgress(modal, {
                percent: 100,
                percentLabel: t('\uc624\ub958'),
                title: t('\uc5c5\ub85c\ub4dc \uc624\ub958'),
                message: error.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.'),
            });
            notify('error', error.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.'));
        } finally {
            setModalBusy(modal, false);
        }
    });

    onDocument('shown.bs.modal', (event) => {
        const modal = event.target;
        if (!modal.classList.contains('modal')) return;

        const fileInput = modal.querySelector('input[type="file"]');

        if (fileInput) {
            fileInput.value = '';
            updateDropzoneFileName(fileInput);
        }
        window.ExcelManagerProgress.reset(modal);
    });
})();
