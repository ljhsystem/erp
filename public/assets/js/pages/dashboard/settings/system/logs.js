/**
 * /public/assets/js/pages/dashboard/settings/system/logs.js
 * 시스템 설정 > 시스템 로그
 */

console.log('[SYSTEM LOGS] loaded');

document.addEventListener('DOMContentLoaded', () => {

    /* ===============================
     * 로그 내용 보기
     * =============================== */
    document.querySelectorAll('.view-log').forEach(btn => {
        btn.addEventListener('click', async () => {
            const file = btn.dataset.file;
            if (!file) return;

            try {
                const res = await fetch('/api/settings/system/logs/view', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file })
                });

                const json = await res.json();
                if (!json.success) {
                    alert(json.message || '로그를 불러올 수 없습니다.');
                    return;
                }

                const content = json.data.content || '';
                const partial = json.data.partial;

                document.getElementById('log-content').innerText =
                    (partial ? '⚠️ 일부 로그만 표시됩니다.\n\n' : '') + content;

                document.getElementById('log-viewer').style.display = 'block';
                document.getElementById('log-viewer').scrollIntoView({ behavior: 'smooth' });

            } catch (e) {
                console.error(e);
                alert('로그 조회 중 오류가 발생했습니다.');
            }
        });
    });

    /* ===============================
     * 로그 뷰어 닫기
     * =============================== */
    const closeBtn = document.getElementById('close-log-viewer');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            document.getElementById('log-viewer').style.display = 'none';
            document.getElementById('log-content').innerText = '';
        });
    }

    /* ===============================
     * 개별 로그 삭제
     * =============================== */
    document.querySelectorAll('.delete-log').forEach(btn => {
        btn.addEventListener('click', async () => {
            const file = btn.dataset.file;
            if (!file) return;

            if (!confirm(`로그 파일 "${file}" 을(를) 삭제하시겠습니까?`)) return;

            try {
                const res = await fetch('/api/settings/system/logs/delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file })
                });

                const json = await res.json();
                if (!json.success) {
                    alert(json.message || '삭제 실패');
                    return;
                }

                alert('삭제되었습니다.');
                location.reload();

            } catch (e) {
                console.error(e);
                alert('삭제 중 오류가 발생했습니다.');
            }
        });
    });

    /* ===============================
     * 전체 로그 삭제
     * =============================== */
    const deleteAllBtn = document.getElementById('delete-all-logs');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', async () => {

            if (!confirm('⚠️ 모든 로그 파일을 삭제합니다.\n되돌릴 수 없습니다.\n\n계속하시겠습니까?')) {
                return;
            }

            try {
                const res = await fetch('/api/settings/system/logs/delete-all', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });

                const json = await res.json();
                if (!json.success) {
                    alert(json.message || '전체 삭제 실패');
                    return;
                }

                alert(`총 ${json.deleted}개 로그 파일이 삭제되었습니다.`);
                location.reload();

            } catch (e) {
                console.error(e);
                alert('전체 삭제 중 오류가 발생했습니다.');
            }
        });
    }

});
