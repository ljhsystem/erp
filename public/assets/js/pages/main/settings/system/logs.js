(() => {
    'use strict';

    const API = {
        VIEW: '/api/settings/system/logs/view',
        DELETE: '/api/settings/system/logs/delete',
        DELETE_ALL: '/api/settings/system/logs/delete-all'
    };

    document.addEventListener('DOMContentLoaded', () => {
        bindViewButtons();
        bindDeleteButtons();
        bindDeleteAllButton();
        bindCloseViewer();
    });

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
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

    function bindViewButtons() {
        document.querySelectorAll('.view-log').forEach(button => {
            button.addEventListener('click', async () => {
                const file = button.dataset.file;
                if (!file) return;

                try {
                    const json = await fetchJson(API.VIEW, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ file })
                    });

                    const content = json.data?.content || '';
                    const partial = json.data?.partial === true;
                    const viewer = document.getElementById('log-viewer');
                    const contentBox = document.getElementById('log-content');

                    if (!viewer || !contentBox) {
                        return;
                    }

                    contentBox.innerText = (partial ? '대용량 로그라 마지막 일부만 표시합니다.\n\n' : '') + content;
                    viewer.style.display = 'block';
                    viewer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (error) {
                    notify('error', error.message || '로그 내용을 불러오지 못했습니다.');
                }
            });
        });
    }

    function bindDeleteButtons() {
        document.querySelectorAll('.delete-log').forEach(button => {
            button.addEventListener('click', async () => {
                const file = button.dataset.file;
                if (!file) return;

                const confirmed = window.confirm(`로그 파일 "${file}"을 삭제할까요?`);
                if (!confirmed) return;

                try {
                    await fetchJson(API.DELETE, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ file })
                    });

                    notify('success', '로그 파일을 삭제했습니다.');
                    window.location.reload();
                } catch (error) {
                    notify('error', error.message || '로그 파일을 삭제하지 못했습니다.');
                }
            });
        });
    }

    function bindDeleteAllButton() {
        const button = document.getElementById('delete-all-logs');
        if (!button) return;

        button.addEventListener('click', async () => {
            const confirmed = window.confirm('모든 로그 파일을 삭제할까요?\n이 작업은 되돌릴 수 없습니다.');
            if (!confirmed) return;

            try {
                const json = await fetchJson(API.DELETE_ALL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });

                notify('success', `총 ${json.deleted || 0}개의 로그 파일을 삭제했습니다.`);
                window.location.reload();
            } catch (error) {
                notify('error', error.message || '전체 로그 삭제에 실패했습니다.');
            }
        });
    }

    function bindCloseViewer() {
        const closeButton = document.getElementById('close-log-viewer');
        if (!closeButton) return;

        closeButton.addEventListener('click', () => {
            const viewer = document.getElementById('log-viewer');
            const contentBox = document.getElementById('log-content');

            if (viewer) {
                viewer.style.display = 'none';
            }

            if (contentBox) {
                contentBox.innerText = '';
            }
        });
    }
})();
