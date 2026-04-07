// 경로: /assets/js/components/trash-manager.js
(() => {
    "use strict";

    console.log("[trash-manager v3] loaded");

    const trashCacheMap = new Map();

    /* =========================
     * 1. 모달 열릴 때 초기화 + 로딩
     ========================= */
    document.addEventListener('shown.bs.modal', function (e) {

        const modal = e.target;
        if (!modal.classList.contains('modal')) return;
        if (!modal.dataset.listUrl) return;

        const layout = modal.querySelector('.trash');
        const detail = modal.querySelector('.trash-detail');
        const tbody  = modal.querySelector('.trash-table tbody');

        if (layout) layout.classList.remove('open');
        if (detail) detail.style.display = 'none';
        if (tbody) tbody.innerHTML = '';

        modal.querySelectorAll('.trash-check').forEach(cb => cb.checked = false);

        const checkAll = modal.querySelector('.trash-check-all');
        if (checkAll) checkAll.checked = false;

        loadTrash(modal);
    });

    /* =========================
     * 2. 휴지통 버튼 클릭 → preload
     ========================= */
    document.addEventListener('click', function(e){

        const btn = e.target.closest('[data-bs-target]');
        if(!btn) return;

        const modal = document.querySelector(btn.getAttribute('data-bs-target'));
        if(!modal || !modal.dataset.listUrl) return;

        preloadTrash(modal);
    });


    /* =========================
    * 3. 행 클릭 → 상세 열기 / 외부 클릭 → 닫기
    ========================= */
    document.addEventListener('click', function (e) {

        const modal = e.target.closest('.modal');
        if (!modal) return;

        const layout = modal.querySelector('.trash');
        const detail = modal.querySelector('.trash-detail');
        const table  = modal.querySelector('.trash-table');

        if (!layout || !detail || !table) return;

        const row = e.target.closest('tr');
        const btn = e.target.closest('button');
        const cb  = e.target.closest('.trash-check, .trash-check-all');

        /* 버튼 / 체크박스 클릭 → 무시 */
        if (btn || cb) return;

        /* 🔥 1. 상세패널 클릭 → 닫지 않음 */
        if (e.target.closest('.trash-detail')) {
            return;
        }

        /* 🔥 2. 행 클릭 → 열기 */
        if (row && row.closest('.trash-table')) {

            table.querySelectorAll('tbody tr')
                .forEach(tr => tr.classList.remove('active'));

            row.classList.add('active');

            layout.classList.add('open');
            detail.style.display = 'block';

            //const data = row.dataset.row ? JSON.parse(row.dataset.row) : {};


            const data = row.dataset.row
            ? JSON.parse(decodeURIComponent(row.dataset.row))
            : {};


            

            document.dispatchEvent(new CustomEvent('trash:detail-render', {
                detail: {
                    data,
                    modal,
                    type: modal.dataset.type
                }
            }));

            return;
        }

        /* 🔥 3. 그 외 영역 클릭 → 닫기 */
        layout.classList.remove('open');
        detail.style.display = 'none';
    });

    /* =========================
     * 4. ESC → 상세 닫기
     ========================= */
    document.addEventListener('keydown', function (e) {

        if (e.key !== 'Escape') return;

        const modal = document.querySelector('.modal.show');
        if (!modal) return;

        const layout = modal.querySelector('.trash');
        const detail = modal.querySelector('.trash-detail');

        if (layout) layout.classList.remove('open');
        if (detail) detail.style.display = 'none';
    });

    /* =========================
     * 5. 복원 / 삭제 / 벌크
     ========================= */
    document.addEventListener('click', async function (e) {

        const modal = e.target.closest('.modal');
        if (!modal) return;

        const restoreUrl   = modal.dataset.restoreUrl;
        const deleteUrl    = modal.dataset.deleteUrl;
        const deleteAllUrl = modal.dataset.deleteAllUrl;

        /* 단일 복원 */
        const restoreBtn = e.target.closest('.btn-restore');
        if (restoreBtn) {

            e.preventDefault();
            e.stopPropagation();

            const id = restoreBtn.dataset.id;
            if (!id || !confirm('복원하시겠습니까?')) return;

            const res = await fetch(restoreUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(id)}`
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '복원 완료');
                triggerChange(modal);
            }

            return;
        }

        /* 단일 삭제 */
        const deleteBtn = e.target.closest('.btn-purge');
        if (deleteBtn) {

            e.preventDefault();
            e.stopPropagation();

            const id = deleteBtn.dataset.id;
            if (!id || !confirm('삭제하시겠습니까?')) return;

            const res = await fetch(deleteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(id)}`
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '삭제 완료');
                triggerChange(modal);
            }

            return;
        }

        /* 선택 복원 */
        if (e.target.closest('.btn-restore-selected')) {

            const ids = getSelectedIds(modal, 'restore');
            if (!ids.length) return AppCore?.notify('warning', '선택 없음');
            if (!confirm('선택 복원하시겠습니까?')) return;

            const res = await fetch(restoreUrl + '-bulk', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids })
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '선택 복원 완료');
                triggerChange(modal);
            }

            return;
        }

        /* 선택 삭제 */
        if (e.target.closest('.btn-delete-selected')) {

            const ids = getSelectedIds(modal, 'delete');
            if (!ids.length) return AppCore?.notify('warning', '선택 없음');
            if (!confirm('선택 삭제하시겠습니까?')) return;

            const res = await fetch(deleteUrl + '-bulk', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids })
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '선택 삭제 완료');
                triggerChange(modal);
            }

            return;
        }
        /* 전체 복원 */
        if (e.target.closest('.btn-restore-all')) {

            if (!confirm('전체 복원하시겠습니까?')) return;

            const restoreAllUrl = restoreUrl + '-all';

            const res = await fetch(restoreAllUrl, {
                method: 'POST'
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '전체 복원 완료');
                triggerChange(modal);
            }

            return;
        }
        /* 전체 삭제 */
        if (e.target.closest('.btn-delete-all')) {

            if (!confirm('전체 삭제하시겠습니까?')) return;

            const res = await fetch(deleteAllUrl, { method: 'POST' });
            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '전체 삭제 완료');
                triggerChange(modal);
            }

            return;
        }
    });

    /* =========================
     * 6. 체크 전체 선택
     ========================= */
    document.addEventListener('change', function (e) {

        const checkAll = e.target.closest('.trash-check-all');
        if (!checkAll) return;

        const modal = e.target.closest('.modal');
        if (!modal) return;

        modal.querySelectorAll('.trash-check')
            .forEach(cb => cb.checked = checkAll.checked);
    });

    /* =========================
     * 7. 변경 이벤트 (캐시만 처리)
     ========================= */
    document.addEventListener('trash:changed', (e) => {

        const { listUrl } = e.detail || {};
        if (listUrl) trashCacheMap.delete(listUrl);
    });

    /* =========================
     * 공통 함수
     ========================= */

    function triggerChange(modal){
        document.dispatchEvent(new CustomEvent('trash:changed', {
            detail: {
                type: modal.dataset.type,
                listUrl: modal.dataset.listUrl
            }
        }));
        loadTrash(modal);
    }

    function getSelectedIds(modal, mode){
        const selector = mode === 'restore' ? '.btn-restore' : '.btn-purge';

        return [...modal.querySelectorAll('.trash-check:checked')]
            .map(cb => cb.closest('tr')?.querySelector(selector)?.dataset.id)
            .filter(Boolean);
    }

    async function loadTrash(modal){

        const listUrl = modal.dataset.listUrl;
        const tbody = modal.querySelector('.trash-table tbody');
        if (!tbody || !listUrl) return;

        const cached = trashCacheMap.get(listUrl);

        if (cached) {
            renderRows(tbody, cached, modal);
        } else {
            tbody.innerHTML = `<tr><td colspan="6">불러오는 중...</td></tr>`;
        }

        try {
            const res = await fetch(listUrl);
            const json = await res.json();

            const rows = json.success ? (json.data || []) : [];

            trashCacheMap.set(listUrl, rows);
            renderRows(tbody, rows, modal);

        } catch (e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="6">로드 실패</td></tr>`;
        }
    }

    async function preloadTrash(modal){
        const listUrl = modal.dataset.listUrl;
        if (!listUrl) return;

        try {
            const res = await fetch(listUrl);
            const json = await res.json();

            if (json.success) {
                trashCacheMap.set(listUrl, json.data || []);
            }
        } catch (e) {
            console.error(e);
        }
    }

    function renderRows(tbody, data, modal){

        tbody.innerHTML = '';
    
        if (!data.length) {
            tbody.innerHTML = `<tr><td colspan="6">데이터 없음</td></tr>`;
            return;
        }
    
        const frag = document.createDocumentFragment();
    
        data.forEach(row => {
    
            const tr = document.createElement('tr');
            tr.dataset.row = encodeURIComponent(JSON.stringify(row));
    
            tr.innerHTML = `
                <td><input type="checkbox" class="trash-check"></td>
                ${getColumns(row, modal)}
            `;
    
            frag.appendChild(tr);
        });
    
        tbody.appendChild(frag);
    }
    function getColumns(row, modal){
        const fn = window.TrashColumns?.[modal.dataset.type];
        return fn ? fn(row) : `<td>${row.id}</td>`;
    }

})();