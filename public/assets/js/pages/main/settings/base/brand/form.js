export function createBrandFormModule({
    wrapper,
    api,
    assets,
    notify,
    tableModule,
}) {
    const selectedFiles = {
        main_logo: null,
        print_logo: null,
        favicon: null
    };

    function bindEvents() {
        Object.entries(assets).forEach(([type, config]) => {
            wrapper.on('change', config.input, function () {
                const file = this.files?.[0];
                if (!file) {
                    selectedFiles[type] = null;
                    return;
                }

                const validationError = validateFile(type, file);
                if (validationError) {
                    notify('warning', validationError);
                    this.value = '';
                    selectedFiles[type] = null;
                    return;
                }

                selectedFiles[type] = file;
                previewFile(file, config.preview);
            });
        });

        wrapper.on('click', '#btn-save-brand', saveAll);

        wrapper.on('click', '.btn-activate-brand', async function () {
            const fileId = window.jQuery(this).data('id');
            if (!fileId) return;

            if (!window.confirm('해당 파일을 기본 브랜드 파일로 적용하시겠습니까?')) return;

            try {
                const json = await postJson(api.ACTIVATE, { id: fileId, status: 1 });
                notify('success', json.message || '기본 브랜드 파일로 적용되었습니다.');
                await refreshAll();
            } catch (error) {
                notify('error', error.message || '기본 파일 적용에 실패했습니다.');
            }
        });

        wrapper.on('click', '.btn-delete-brand', async function () {
            const fileId = window.jQuery(this).data('id');
            if (!fileId) return;

            if (!window.confirm('브랜드 파일을 삭제하시겠습니까?')) return;

            try {
                const json = await postJson(api.PURGE, { file_id: fileId });
                notify('success', json.message || '브랜드 파일이 삭제되었습니다.');
                await refreshAll();
            } catch (error) {
                notify('error', error.message || '브랜드 파일 삭제에 실패했습니다.');
            }
        });
    }

    async function refreshAll() {
        const rows = await tableModule.loadExistingFiles();
        renderActiveAssets(rows);
    }

    function renderActiveAssets(rows = []) {
        Object.entries(assets).forEach(([type, config]) => {
            const image = window.jQuery(config.preview);
            const active = rows.find((row) => row.asset_type === type && Number(row.is_active) === 1);
            removeEmptyMessage(image);
            if (!active?.url) {
                image.hide().attr('src', '');
                showEmptyMessage(image, config.emptyText);
                return;
            }
            image.attr('src', active.url).show();
        });
    }

    function previewFile(file, selector) {
        const reader = new FileReader();
        reader.onload = (event) => {
            const image = window.jQuery(selector);
            removeEmptyMessage(image);
            image.attr('src', event.target.result).show();
        };
        reader.readAsDataURL(file);
    }

    function saveAll() {
        const changedTypes = Object.keys(selectedFiles).filter((type) => selectedFiles[type]);
        if (changedTypes.length === 0) {
            notify('warning', '변경한 파일이 없습니다.');
            return;
        }

        window.jQuery('#btn-save-brand').prop('disabled', true);
        const uploads = changedTypes.map((type) => uploadFile(type, selectedFiles[type]));

        Promise.all(uploads)
            .then(async (results) => {
                const failed = results.find((item) => !item.success);
                if (failed) {
                    notify('error', failed.message || '브랜드 파일 저장 중 오류가 발생했습니다.');
                    return;
                }

                changedTypes.forEach((type) => {
                    selectedFiles[type] = null;
                    wrapper.find(assets[type].input).val('');
                });

                notify('success', '브랜드 파일을 저장했습니다.');
                await refreshAll();
            })
            .finally(() => {
                window.jQuery('#btn-save-brand').prop('disabled', false);
            });
    }

    function uploadFile(type, file) {
        return new Promise((resolve) => {
            const formData = new FormData();
            formData.append('asset_type', type);
            formData.append('file', file);

            window.jQuery.ajax({
                url: api.SAVE,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success(response) {
                    resolve(response || { success: false, message: '업로드 응답이 비어 있습니다.' });
                },
                error(xhr) {
                    let message = '서버 통신 중 오류가 발생했습니다.';
                    try {
                        const json = JSON.parse(xhr.responseText || '{}');
                        message = json?.message || message;
                    } catch (_) {
                        // ignore
                    }
                    resolve({ success: false, message });
                }
            });
        });
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams(payload).toString()
        });

        const text = await response.text();
        let json = {};
        try {
            json = text ? JSON.parse(text) : {};
        } catch (_) {
            throw new Error('서버 응답을 해석하지 못했습니다.');
        }

        if (!response.ok || !json?.success) {
            throw new Error(json?.message || '요청 처리 중 오류가 발생했습니다.');
        }

        return json;
    }

    function validateFile(type, file) {
        const maxSize = 5 * 1024 * 1024;
        const allowedTypes = type === 'favicon'
            ? ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml']
            : ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            return type === 'favicon'
                ? '파비콘은 PNG, ICO, SVG 파일만 업로드할 수 있습니다.'
                : '로고는 PNG, JPG, SVG, WEBP 파일만 업로드할 수 있습니다.';
        }

        if (file.size <= 0 || file.size > maxSize) {
            return '이미지 크기는 5MB 이하만 업로드할 수 있습니다.';
        }

        return '';
    }

    function showEmptyMessage(image, text) {
        if (image.next('.brand-empty-text').length === 0) {
            window.jQuery('<div>')
                .addClass('brand-empty-text text-muted mt-1')
                .text(text)
                .insertAfter(image);
        }
    }

    function removeEmptyMessage(image) {
        image.next('.brand-empty-text').remove();
    }

    return {
        bindEvents,
        renderActiveAssets,
    };
}
