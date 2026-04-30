// 寃쎈줈: /assets/js/components/trash-manager.js
(() => {
    "use strict";

    console.log("[trash-manager v3] loaded");

    const trashCacheMap = new Map();

    /* =========================
     * 1. 紐⑤떖 ?대┫ ??珥덇린??+ 濡쒕뵫
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
     * 2. ?댁???踰꾪듉 ?대┃ ??preload
     ========================= */
    document.addEventListener('click', function(e){

        const btn = e.target.closest('[data-bs-target]');
        if(!btn) return;

        const modal = document.querySelector(btn.getAttribute('data-bs-target'));
        if(!modal || !modal.dataset.listUrl) return;

        preloadTrash(modal);
    });


    /* =========================
    * 3. ???대┃ ???곸꽭 ?닿린 / ?몃? ?대┃ ???リ린
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

        /* 踰꾪듉 / 泥댄겕諛뺤뒪 ?대┃ ??臾댁떆 */
        if (btn || cb) return;

        /* ?뵦 1. ?곸꽭?⑤꼸 ?대┃ ???レ? ?딆쓬 */
        if (e.target.closest('.trash-detail')) {
            return;
        }

        /* ?뵦 2. ???대┃ ???닿린 */
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

        /* ?뵦 3. 洹????곸뿭 ?대┃ ???リ린 */
        layout.classList.remove('open');
        detail.style.display = 'none';
    });

    /* =========================
     * 4. ESC ???곸꽭 ?リ린
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
     * 5. 蹂듭썝 / ??젣 / 踰뚰겕
     ========================= */
    document.addEventListener('click', async function (e) {

        const modal = e.target.closest('.modal');
        if (!modal) return;

        const restoreUrl   = modal.dataset.restoreUrl;
        const deleteUrl    = modal.dataset.deleteUrl;
        const deleteAllUrl = modal.dataset.deleteAllUrl;

        /* ?⑥씪 蹂듭썝 */
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

        /* ?⑥씪 ??젣 */
        const deleteBtn = e.target.closest('.btn-purge');
        if (deleteBtn) {

            e.preventDefault();
            e.stopPropagation();

            const id = deleteBtn.dataset.id;
            if (!id || !confirm('영구삭제하시겠습니까?')) return;

            const res = await fetch(deleteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(id)}`
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '영구삭제 완료');
                triggerChange(modal);
            }

            return;
        }

        /* ?좏깮 蹂듭썝 */
        if (e.target.closest('.btn-restore-selected')) {

            const ids = getSelectedIds(modal, 'restore');
            if (!ids.length) return AppCore?.notify('warning', '선택된 항목이 없습니다.');
            if (!confirm('선택 항목을 복원하시겠습니까?')) return;

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

        /* ?좏깮 ??젣 */
        if (e.target.closest('.btn-delete-selected')) {

            const ids = getSelectedIds(modal, 'delete');
            if (!ids.length) return AppCore?.notify('warning', '선택된 항목이 없습니다.');
            if (!confirm('선택 항목을 영구삭제하시겠습니까?')) return;

            const res = await fetch(deleteUrl + '-bulk', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids })
            });

            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '선택 영구삭제 완료');
                triggerChange(modal);
            }

            return;
        }
        /* ?꾩껜 蹂듭썝 */
        if (e.target.closest('.btn-restore-all')) {

            if (!confirm('전체 항목을 복원하시겠습니까?')) return;

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
        /* ?꾩껜 ??젣 */
        if (e.target.closest('.btn-delete-all')) {

            if (!confirm('전체 항목을 영구삭제하시겠습니까?')) return;

            const res = await fetch(deleteAllUrl, { method: 'POST' });
            const json = await res.json();

            if (json.success) {
                AppCore?.notify('success', '전체 영구삭제 완료');
                triggerChange(modal);
            }

            return;
        }
    });

    /* =========================
     * 6. 泥댄겕 ?꾩껜 ?좏깮
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
     * 7. 蹂寃??대깽??(罹먯떆留?泥섎━)
     ========================= */
    document.addEventListener('trash:changed', (e) => {

        const { listUrl } = e.detail || {};
        if (listUrl) trashCacheMap.delete(listUrl);
    });

    /* =========================
     * 怨듯넻 ?⑥닔
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
            tbody.innerHTML = `<tr><td colspan="${getColumnCount(modal)}">불러오는 중...</td></tr>`;
        }

        try {
            const res = await fetch(listUrl);
            const json = await res.json();

            const rows = json.success ? (json.data || []) : [];

            trashCacheMap.set(listUrl, rows);
            renderRows(tbody, rows, modal);

        } catch (e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="${getColumnCount(modal)}">로드 실패</td></tr>`;
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
            tbody.innerHTML = `<tr><td colspan="${getColumnCount(modal)}">데이터 없음</td></tr>`;
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

    function getColumnCount(modal) {
        return modal.querySelectorAll('.trash-table thead th').length || 1;
    }

})();
