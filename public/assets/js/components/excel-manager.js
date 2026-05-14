// Common Excel management modal events.
(() => {
    'use strict';

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

            const startProcessingTimer = () => {
                processingStartedAt = Date.now();
                stopProcessingTimer();
                processingTimer = setInterval(() => {
                    setUploadProgress(modal, {
                        percent: 100,
                        percentLabel: t('\uc804\uc1a1 100%'),
                        title: t('\uc11c\ubc84 \ucc98\ub9ac \uc911'),
                        message: `${t('\ud30c\uc77c \uc804\uc1a1\uc740 \uc644\ub8cc\ub418\uc5c8\uace0, \uc11c\ubc84\uc5d0\uc11c \ub370\uc774\ud130\ub97c \ucc98\ub9ac\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.')} ${t('\uacbd\uacfc')} ${formatElapsed(Date.now() - processingStartedAt)}`,
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
                    percentLabel: t('\uc804\uc1a1 100%'),
                    title: t('\uc11c\ubc84 \ucc98\ub9ac \uc911'),
                    message: totalBytes > 0
                        ? `${formatBytes(totalBytes)} ${t('\uc804\uc1a1 \uc644\ub8cc. \uc11c\ubc84\uc5d0\uc11c \ub370\uc774\ud130\ub97c \ucc98\ub9ac\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.')}`
                        : t('\ud30c\uc77c \uc804\uc1a1\uc774 \uc644\ub8cc\ub418\uc5c8\uace0 \uc11c\ubc84\uc5d0\uc11c \ub370\uc774\ud130\ub97c \ucc98\ub9ac\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.'),
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
                    reject(new Error(t('\uc11c\ubc84 \uc751\ub2f5\uc744 \ud574\uc11d\ud558\uc9c0 \ubabb\ud588\uc2b5\ub2c8\ub2e4.')));
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new Error(json.message || t('\uc5d1\uc140 \uc5c5\ub85c\ub4dc\uc5d0 \uc2e4\ud328\ud588\uc2b5\ub2c8\ub2e4.')));
                    return;
                }

                resolve(json);
            });

            xhr.addEventListener('error', () => {
                stopProcessingTimer();
                reject(new Error(t('\ud30c\uc77c \uc804\uc1a1 \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.')));
            });
            xhr.addEventListener('abort', () => {
                stopProcessingTimer();
                reject(new Error(t('\ud30c\uc77c \uc804\uc1a1\uc774 \ucde8\uc18c\ub418\uc5c8\uc2b5\ub2c8\ub2e4.')));
            });
            xhr.open('POST', url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }

    window.ExcelManagerProgress = {
        set: setUploadProgress,
        request: uploadFormData,
        reset(modal) {
            setUploadProgress(modal, {
                visible: false,
                percent: 0,
                title: t('\uc5c5\ub85c\ub4dc \uc900\ube44 \uc911'),
                message: t('\ud30c\uc77c\uc744 \ud655\uc778\ud558\uace0 \uc788\uc2b5\ub2c8\ub2e4.'),
                indeterminate: false,
            });
        },
    };

    function updateDropzoneFileName(input) {
        const dropzone = input?.closest('.excel-dropzone');
        const fileNameEl = dropzone?.querySelector('[data-excel-file-name]');
        if (!fileNameEl) return;
        fileNameEl.textContent = input?.files?.[0]?.name || t('\uc120\ud0dd\ub41c \ud30c\uc77c \uc5c6\uc74c');
        dropzone?.classList.toggle('has-file', Boolean(input?.files?.length));
    }

    document.addEventListener('change', (event) => {
        const input = event.target.closest('.excel-file-input');
        if (!input) return;
        updateDropzoneFileName(input);
    });

    document.addEventListener('dragover', (event) => {
        const dropzone = event.target.closest('.excel-dropzone');
        if (!dropzone) return;
        event.preventDefault();
        dropzone.classList.add('is-dragover');
    });

    document.addEventListener('dragleave', (event) => {
        const dropzone = event.target.closest('.excel-dropzone');
        if (!dropzone || dropzone.contains(event.relatedTarget)) return;
        dropzone.classList.remove('is-dragover');
    });

    document.addEventListener('drop', (event) => {
        const dropzone = event.target.closest('.excel-dropzone');
        if (!dropzone) return;
        event.preventDefault();
        dropzone.classList.remove('is-dragover');

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

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('button');
        if (!btn) return;

        const modal = btn.closest('.modal');
        if (!modal) return;

        const form = modal.querySelector('form');
        if (!form) return;

        const fileInput = modal.querySelector('input[type="file"]');

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
        btn.disabled = true;
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
            btn.disabled = false;
        }
    });

    document.addEventListener('shown.bs.modal', (event) => {
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
