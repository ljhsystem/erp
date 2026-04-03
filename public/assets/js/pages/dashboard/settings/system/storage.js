/**
 * /public/assets/js/pages/dashboard/settings/system/storage.js
 */

/* =========================================================
 * 버킷 파일 더블클릭 → 미리보기 (이벤트 위임)
 * ========================================================= */
document.addEventListener('dblclick', e => {
    const row = e.target.closest('tr.bucket-item');
    if (!row) return;

    const { type, path } = row.dataset;
    if (type !== 'file' || !path) return;

    window.open(
        `/api/file/preview?path=${encodeURIComponent(path)}`,
        '_blank'
    );
});

document.getElementById('btn-add-policy')
    ?.addEventListener('click', () => {
        openPolicyModal(null);
    });


document.addEventListener('DOMContentLoaded', () => {

    const uploadForm    = document.getElementById('upload-test-form');
    const policySelect  = uploadForm?.querySelector('[name="policy_key"]');
    const bucketSelect  = uploadForm?.querySelector('[name="bucket"]');
    const openBucketBtn = document.getElementById('btn-open-bucket');
    const resultBox     = document.getElementById('upload-test-result');

    highlightStorageIssues();
    loadPolicies();
    bindPolicyEvents();
    bindPolicyForm();
    bindActiveSwitch();

    /* 정책 선택 → 버킷 잠금 */
    policySelect?.addEventListener('change', () => {
        const usingPolicy = policySelect.value !== '';
        bucketSelect.disabled = usingPolicy;
        if (usingPolicy) bucketSelect.value = '';
        openBucketBtn.disabled = usingPolicy;
    });

    /* 버킷 선택 → 폴더 열기 */
    bucketSelect?.addEventListener('change', () => {
        openBucketBtn.disabled = bucketSelect.value === '';
    });

    /* 폴더 열기 */
    openBucketBtn?.addEventListener('click', () => {
        const bucket = bucketSelect.value;
        if (!bucket) return;

        fetch(`/api/system/storage/bucket-browse?bucket=${encodeURIComponent(bucket)}`)
            .then(r => r.json())
            .then(r => {
                if (!r.success) return alert(r.message || '실패');
                openBucketModal(bucket, r.files);
            });
    });

    /* 업로드 테스트 */
    uploadForm?.addEventListener('submit', e => {
        e.preventDefault();

        fetch('/api/file/upload-test', {
            method: 'POST',
            body: new FormData(uploadForm)
        })
        .then(r => r.json())
        .then(r => {
            if (!r.success) {
                resultBox.innerHTML = `<div class="alert alert-danger">${r.message}</div>`;
                return;
            }

            resultBox.innerHTML = `
                <div class="alert alert-success">
                    업로드 성공<br>
                    <code>${r.db_path}</code>
                </div>
            `;
        });
    });
});

/* =========================================================
 * 정책 저장
 * ========================================================= */
function bindPolicyForm() {
    const form = document.getElementById('policy-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();

        const id = form.querySelector('#policy-id')?.value;
        const isUpdate = !!id;

        const url = isUpdate
            ? '/api/system/file-policies/update'
            : '/api/system/file-policies';

        fetch(url, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || '저장 실패');
                return;
            }

            bootstrap.Modal
                .getInstance(document.getElementById('policyModal'))
                .hide();

            loadPolicies();
        })
        .catch(err => {
            console.error(err);
            alert('서버 오류');
        });
    });
}


/* =========================================================
 * 정책 목록
 * ========================================================= */
function loadPolicies() {
    fetch('/api/system/file-policies')
        .then(res => res.json())
        .then(list => {
            if (!Array.isArray(list)) return;

            const tbody = document.getElementById('file-policy-table');
            tbody.innerHTML = '';

            list.forEach(p => {
                tbody.insertAdjacentHTML('beforeend', renderPolicyRow(p));
            });

            refreshPolicySelect(list);
        })
        .catch(err => {
            console.error(err);
            alert('정책 조회 실패');
        });
}

/* =========================================================
 * 테이블 렌더
 * ========================================================= */
function renderPolicyRow(p) {
    return `
    <tr>
        <td>
            <strong>${p.policy_name}</strong><br>
            <small class="text-muted">${p.policy_key}</small>
        </td>
        <td><code>${p.bucket}</code></td>
        <td>${p.allowed_ext}</td>

        <!-- ✅ MIME -->
        <td>
            ${p.allowed_mime
                ? `<small>${p.allowed_mime}</small>`
                : '<span class="text-muted">자동</span>'
            }
        </td>

        <!-- ✅ 설명 -->
        <td>
            ${p.description
                ? `<small>${p.description}</small>`
                : '<span class="text-muted">-</span>'
            }
        </td>

        <td class="text-center">${p.max_size_mb}</td>

        <td class="text-center">
            <span class="badge ${p.is_active == 1 ? 'bg-success' : 'bg-secondary'}">
                ${p.is_active == 1 ? '활성' : '비활성'}
            </span>
        </td>

        <td class="text-center">
            <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="${p.id}">수정</button>
            <button class="btn btn-sm btn-outline-warning btn-toggle"
                    data-id="${p.id}" data-active="${p.is_active}">
                ${p.is_active == 1 ? '비활성' : '활성'}
            </button>
            <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${p.id}">삭제</button>
        </td>
    </tr>
    `;
}


/* =========================================================
 * 버튼 이벤트
 * ========================================================= */
function bindPolicyEvents() {
    document.addEventListener('click', e => {

        const editBtn   = e.target.closest('.btn-edit');
        const toggleBtn = e.target.closest('.btn-toggle');
        const deleteBtn = e.target.closest('.btn-delete');

        /* 수정 */
        if (editBtn) {
            const id = editBtn.dataset.id;

            fetch('/api/system/file-policies')
                .then(res => res.json())
                .then(list => {
                    const policy = list.find(p => p.id == id);
                    if (policy) openPolicyModal(policy);
                });
            return;
        }

        /* 활성 / 비활성 */
        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            const next = Number(toggleBtn.dataset.active) === 1 ? 0 : 1;

            fetch('/api/system/file-policies/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id, is_active: next })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) loadPolicies();
                else alert('상태 변경 실패');
            });
            return;
        }

        /* 삭제 */
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            if (!confirm('정말 삭제하시겠습니까?')) return;

            fetch('/api/system/file-policies/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) loadPolicies();
                else alert(res.message || '삭제 실패');
            });
        }
    });
}



/* =========================================================
 * 기타 보조
 * ========================================================= */
function bindActiveSwitch() {
    const checkbox = document.getElementById('policy-is-active');
    if (!checkbox) return;

    checkbox.addEventListener('change', e => {
        document.querySelector('#policy-form input[name="is_active"]').value =
            e.target.checked ? '1' : '0';
    });
}

function refreshPolicySelect(list) {
    const select = document.querySelector('select[name="policy_key"]');
    if (!select) return;

    select.innerHTML = '<option value="">정책 미사용</option>';
    list.forEach(p => {
        if (p.is_active == 1) {
            select.insertAdjacentHTML(
                'beforeend',
                `<option value="${p.policy_key}">${p.policy_name}</option>`
            );
        }
    });
}

function highlightStorageIssues() {
    document.querySelectorAll('.badge.bg-danger, .badge.bg-warning')
        .forEach(badge => badge.closest('tr')?.classList.add('table-warning'));
}

function openBucketModal(bucket, files) {
    const modalEl = document.getElementById('bucketModal');
    const titleEl = document.getElementById('bucketModalTitle');
    const tbody   = document.getElementById('bucket-file-list');

    titleEl.textContent = bucket;
    tbody.innerHTML = '';

    if (!files || files.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-4">
                    파일이 없습니다.
                </td>
            </tr>
        `;
    } else {
        files.forEach(f => {
            const size = f.size
                ? (f.size / 1024).toFixed(1) + ' KB'
                : '-';

            const date = f.mtime
                ? new Date(f.mtime * 1000).toLocaleString()
                : '-';

            tbody.insertAdjacentHTML('beforeend', `
                <tr class="bucket-item"
                    data-type="${f.type}"
                    data-path="${f.db_path || ''}">
                    <td>
                        ${f.type === 'dir'
                            ? '<i class="bi bi-folder-fill text-warning me-1"></i>'
                            : '<i class="bi bi-file-earmark me-1"></i>'
                        }
                        ${f.name}
                    </td>
                    <td class="text-center">${f.type}</td>
                    <td class="text-end">${size}</td>
                    <td class="text-center">${date}</td>
                </tr>
            `);
        });
    }

    new bootstrap.Modal(modalEl).show();
}

function openPolicyModal(policy = null) {
    const modalEl = document.getElementById('policyModal');
    const form    = document.getElementById('policy-form');

    if (!modalEl || !form) return;

    form.reset();

    // hidden id
    const idInput = document.getElementById('policy-id');
    const activeCheckbox = document.getElementById('policy-is-active');
    const activeHidden   = form.querySelector('input[name="is_active"]');

    if (policy) {
        // 수정 모드
        Object.keys(policy).forEach(key => {
            if (form[key]) {
                form[key].value = policy[key];
            }
        });

        if (idInput) idInput.value = policy.id;

        const isActive = String(policy.is_active) === '1';
        if (activeCheckbox) activeCheckbox.checked = isActive;
        if (activeHidden) activeHidden.value = isActive ? '1' : '0';

    } else {
        // 신규 추가
        if (idInput) idInput.value = '';
        if (activeCheckbox) activeCheckbox.checked = true;
        if (activeHidden) activeHidden.value = '1';
    }

    new bootstrap.Modal(modalEl).show();
}
