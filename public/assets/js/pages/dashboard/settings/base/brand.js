(() => {
    'use strict';

    const API = {
        ACTIVE: '/api/settings/base-info/brand/active-type',
        SAVE: '/api/settings/base-info/brand/save',
        LIST: '/api/settings/base-info/brand/list',
        ACTIVATE: '/api/settings/base-info/brand/updatestatus',
        PURGE: '/api/settings/base-info/brand/purge'
    };

    const wrapper = $('#brand-settings-wrapper');

    const ASSETS = {
        main_logo: {
            input: "[name='main_logo']",
            preview: '#preview_main_logo',
            emptyText: '등록된 메인 로고가 없습니다.'
        },
        print_logo: {
            input: "[name='print_logo']",
            preview: '#preview_print_logo',
            emptyText: '등록된 인쇄용 로고가 없습니다.'
        },
        favicon: {
            input: "[name='favicon']",
            preview: '#preview_favicon',
            emptyText: '등록된 파비콘이 없습니다.'
        }
    };

    const selectedFiles = {
        main_logo: null,
        print_logo: null,
        favicon: null
    };

    $(document).ready(() => {
        loadAll();
        loadExistingFiles();
        bindEvents();
    });

    function bindEvents() {
        Object.entries(ASSETS).forEach(([type, config]) => {
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
            const fileId = $(this).data('id');
            if (!fileId) return;

            const confirmed = window.confirm('이 파일을 기본 브랜드 파일로 적용할까요?');
            if (!confirmed) return;

            try {
                const json = await postJson(API.ACTIVATE, { id: fileId, status: 1 });
                notify('success', json.message || '기본 브랜드 파일로 적용했습니다.');
                await refreshAll();
            } catch (error) {
                notify('error', error.message || '기본 파일 적용에 실패했습니다.');
            }
        });

        wrapper.on('click', '.btn-delete-brand', async function () {
            const fileId = $(this).data('id');
            if (!fileId) return;

            const confirmed = window.confirm('이 브랜드 파일을 삭제할까요?');
            if (!confirmed) return;

            try {
                const json = await postJson(API.PURGE, { file_id: fileId });
                notify('success', json.message || '브랜드 파일을 삭제했습니다.');
                await refreshAll();
            } catch (error) {
                notify('error', error.message || '브랜드 파일 삭제에 실패했습니다.');
            }
        });
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }

    async function refreshAll() {
        loadAll();
        loadExistingFiles();
    }

    function loadAll() {
        Object.keys(ASSETS).forEach(loadAsset);
    }

    function loadAsset(type) {
        const config = ASSETS[type];
        const image = $(config.preview);

        $.post(API.ACTIVE, { asset_type: type }, (response) => {
            removeEmptyMessage(image);

            if (!response?.success || !response?.data?.url) {
                image.hide().attr('src', '');
                showEmptyMessage(image, config.emptyText);
                return;
            }

            image.attr('src', response.data.url).show();
        }, 'json').fail(() => {
            image.hide().attr('src', '');
            showEmptyMessage(image, '자산 정보를 불러오지 못했습니다.');
        });
    }

    function previewFile(file, selector) {
        const reader = new FileReader();
        reader.onload = (event) => {
            const image = $(selector);
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

        $('#btn-save-brand').prop('disabled', true);

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
                    wrapper.find(ASSETS[type].input).val('');
                });

                notify('success', '브랜드 파일을 저장했습니다.');
                await refreshAll();
            })
            .finally(() => {
                $('#btn-save-brand').prop('disabled', false);
            });
    }

    function uploadFile(type, file) {
        return new Promise((resolve) => {
            const formData = new FormData();
            formData.append('asset_type', type);
            formData.append('file', file);

            $.ajax({
                url: API.SAVE,
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

    function loadExistingFiles() {
        $.post(API.LIST, {}, (response) => {
            const tbody = $('#existing-files');
            tbody.empty();

            if (!response?.success || !Array.isArray(response.data) || response.data.length === 0) {
                tbody.append('<tr><td colspan="7" class="text-center text-muted">등록된 파일이 없습니다.</td></tr>');
                return;
            }

            response.data.forEach((file) => {
                const previewUrl = escapeHtml(file.url || '/public/assets/img/default-placeholder.png');
                const fileName = escapeHtml(file.file_name || '-');
                const typeLabel = escapeHtml(file.asset_type_label || file.asset_type || '-');
                const createdAt = escapeHtml(file.created_at || '-');
                const createdBy = escapeHtml(file.created_by || '-');
                const activeBadge = Number(file.is_active) === 1
                    ? '<span class="badge bg-success">활성</span>'
                    : '<span class="badge bg-secondary">비활성</span>';
                const activateButton = Number(file.is_active) === 1
                    ? ''
                    : `<button type="button" class="btn btn-sm btn-primary btn-activate-brand" data-id="${escapeAttribute(file.id)}">기본 적용</button>`;

                const row = `
                    <tr>
                        <td><img src="${previewUrl}" alt="${typeLabel}" height="40" style="max-width: 80px;"></td>
                        <td>${typeLabel}</td>
                        <td><a href="${previewUrl}" target="_blank" rel="noopener noreferrer">${fileName}</a></td>
                        <td>${createdAt}</td>
                        <td>${createdBy}</td>
                        <td>${activeBadge}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                ${activateButton}
                                <button type="button" class="btn btn-sm btn-danger btn-delete-brand" data-id="${escapeAttribute(file.id)}">삭제</button>
                            </div>
                        </td>
                    </tr>
                `;

                tbody.append(row);
            });
        }, 'json').fail(() => {
            $('#existing-files').html('<tr><td colspan="7" class="text-center text-danger">파일 목록을 불러오지 못했습니다.</td></tr>');
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
            $('<div>')
                .addClass('brand-empty-text text-muted mt-1')
                .text(text)
                .insertAfter(image);
        }
    }

    function removeEmptyMessage(image) {
        image.next('.brand-empty-text').remove();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/`/g, '&#96;');
    }
})();
