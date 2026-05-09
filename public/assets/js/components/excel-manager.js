// 공통 엑셀관리 모달 이벤트
(() => {
    'use strict';

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('button');
        if (!btn) return;

        const modal = btn.closest('.modal');
        if (!modal) return;

        const form = modal.querySelector('form');
        if (!form) return;

        const fileInput = modal.querySelector('input[type="file"]');
        const spinnerWrap = modal.querySelector('.excel-spinner');

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
            window.AppCore?.notify?.('warning', '업로드할 엑셀 파일을 선택하세요.');
            return;
        }

        const formData = new FormData(form);
        if (spinnerWrap) spinnerWrap.style.display = 'block';
        btn.disabled = true;

        try {
            const res = await fetch(form.dataset.uploadUrl, {
                method: 'POST',
                body: formData,
            });

            const json = await res.json();

            if (json.success) {
                window.AppCore?.notify?.('success', json.message || '엑셀 업로드가 완료되었습니다.');

                const instance = bootstrap.Modal.getInstance(modal)
                    || new bootstrap.Modal(modal);

                instance.hide();
                document.dispatchEvent(new Event('excel:uploaded'));
                return;
            }

            window.AppCore?.notify?.('error', json.message || '엑셀 업로드에 실패했습니다.');
        } catch (error) {
            console.error(error);
            window.AppCore?.notify?.('error', '엑셀 업로드 중 오류가 발생했습니다.');
        } finally {
            btn.disabled = false;
            if (spinnerWrap) spinnerWrap.style.display = 'none';
        }
    });

    document.addEventListener('shown.bs.modal', (event) => {
        const modal = event.target;
        if (!modal.classList.contains('modal')) return;

        const fileInput = modal.querySelector('input[type="file"]');
        const spinner = modal.querySelector('.excel-spinner');

        if (fileInput) fileInput.value = '';
        if (spinner) spinner.style.display = 'none';
    });
})();
