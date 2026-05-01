(() => {
    'use strict';

    const API = {
        PREVIEW: '/api/file/preview',
        UPLOAD_TEST: '/api/file/upload-test',
        POLICY_LIST: '/api/system/file-policies',
        POLICY_CREATE: '/api/system/file-policies',
        POLICY_UPDATE: '/api/system/file-policies/update',
        POLICY_DELETE: '/api/system/file-policies/delete',
        POLICY_TOGGLE: '/api/system/file-policies/toggle',
        BUCKET_BROWSE: '/api/system/storage/bucket-browse'
    };

    let cachedPolicies = [];
    let pendingDeleteId = null;

    document.addEventListener('DOMContentLoaded', () => {
        highlightStorageIssues();
        bindPolicyEvents();
        bindPolicyForm();
        bindActiveSwitch();
        bindUploadTool();
        loadPolicies();
    });

    document.addEventListener('dblclick', event => {
        const row = event.target.closest('tr.bucket-item');
        if (!row) return;

        const { type, path } = row.dataset;
        if (type !== 'file' || !path) return;

        window.open(`${API.PREVIEW}?path=${encodeURIComponent(path)}`, '_blank');
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
        let json;

        try {
            json = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error('서버 응답을 해석하지 못했습니다.');
        }

        if (!response.ok) {
            throw new Error(json?.message || '요청 처리 중 오류가 발생했습니다.');
        }

        return json;
    }

    function bindUploadTool() {
        const uploadForm = document.getElementById('upload-test-form');
        const policySelect = uploadForm?.querySelector('[name="policy_key"]');
        const bucketSelect = uploadForm?.querySelector('[name="bucket"]');
        const openBucketBtn = document.getElementById('btn-open-bucket');
        const resultBox = document.getElementById('upload-test-result');
        const submitButton = document.getElementById('btn-run-upload-test');

        policySelect?.addEventListener('change', () => {
            const usingPolicy = policySelect.value !== '';
            bucketSelect.disabled = usingPolicy;
            if (usingPolicy) {
                bucketSelect.value = '';
                openBucketBtn.disabled = true;
            }
        });

        bucketSelect?.addEventListener('change', () => {
            openBucketBtn.disabled = bucketSelect.value === '';
        });

        openBucketBtn?.addEventListener('click', async () => {
            const bucket = bucketSelect?.value || '';
            if (!bucket) {
                notify('warning', '버킷을 먼저 선택하세요.');
                return;
            }

            try {
                const json = await fetchJson(`${API.BUCKET_BROWSE}?bucket=${encodeURIComponent(bucket)}`);
                if (!json?.success) {
                    throw new Error(json?.message || '버킷 목록을 불러오지 못했습니다.');
                }

                openBucketModal(bucket, json.files || []);
            } catch (error) {
                notify('error', error.message || '버킷 조회 실패');
            }
        });

        uploadForm?.addEventListener('submit', async event => {
            event.preventDefault();

            const formData = new FormData(uploadForm);
            const policyKey = formData.get('policy_key');
            const bucket = formData.get('bucket');
            const file = formData.get('file');

            if (!(file instanceof File) || !file.name) {
                notify('warning', '테스트할 파일을 선택하세요.');
                return;
            }

            if (!policyKey && !bucket) {
                notify('warning', '버킷 또는 업로드 정책을 선택하세요.');
                return;
            }

            try {
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>테스트 중...';
                }

                const json = await fetchJson(API.UPLOAD_TEST, {
                    method: 'POST',
                    body: formData
                });

                if (!json?.success) {
                    throw new Error(json?.message || '업로드 테스트에 실패했습니다.');
                }

                resultBox.innerHTML = `
                    <div class="alert alert-success mb-0">
                        <div class="fw-semibold mb-1">업로드 테스트가 완료되었습니다.</div>
                        <div class="small text-muted">저장 경로 키</div>
                        <code>${escapeHtml(json.db_path || '-')}</code>
                    </div>
                `;
                notify('success', '업로드 테스트가 완료되었습니다.');
            } catch (error) {
                resultBox.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        ${escapeHtml(error.message || '업로드 테스트 중 오류가 발생했습니다.')}
                    </div>
                `;
                notify('error', error.message || '업로드 테스트 실패');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="bi bi-upload me-1"></i>테스트 업로드';
                }
            }
        });
    }

    function bindPolicyForm() {
        const form = document.getElementById('policy-form');
        if (!form) return;

        form.addEventListener('submit', async event => {
            event.preventDefault();

            const formData = new FormData(form);
            const id = String(formData.get('id') || '').trim();
            const policyName = String(formData.get('policy_name') || '').trim();
            const policyKey = String(formData.get('policy_key') || '').trim();
            const allowedExt = String(formData.get('allowed_ext') || '').trim();
            const maxSizeMb = Number(formData.get('max_size_mb') || 0);
            const isUpdate = id !== '';

            if (!policyName) {
                notify('warning', '정책명을 입력하세요.');
                return;
            }

            if (!policyKey) {
                notify('warning', '정책 키를 입력하세요.');
                return;
            }

            if (!allowedExt) {
                notify('warning', '허용 확장자를 입력하세요.');
                return;
            }

            if (!Number.isFinite(maxSizeMb) || maxSizeMb <= 0) {
                notify('warning', '최대 용량은 1MB 이상이어야 합니다.');
                return;
            }

            try {
                const json = await fetchJson(isUpdate ? API.POLICY_UPDATE : API.POLICY_CREATE, {
                    method: 'POST',
                    body: formData
                });

                if (!json?.success) {
                    throw new Error(json?.message || '정책 저장에 실패했습니다.');
                }

                bootstrap.Modal.getInstance(document.getElementById('policyModal'))?.hide();
                notify('success', isUpdate ? '정책이 수정되었습니다.' : '정책이 등록되었습니다.');
                await loadPolicies();
            } catch (error) {
                notify('error', error.message || '정책 저장 실패');
            }
        });
    }

    async function loadPolicies() {
        try {
            const list = await fetchJson(API.POLICY_LIST);
            if (!Array.isArray(list)) {
                throw new Error('정책 목록 형식이 올바르지 않습니다.');
            }

            cachedPolicies = list;

            const tbody = document.getElementById('file-policy-table');
            tbody.innerHTML = '';

            if (!list.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">등록된 업로드 정책이 없습니다.</td>
                    </tr>
                `;
            } else {
                list.forEach(policy => {
                    tbody.insertAdjacentHTML('beforeend', renderPolicyRow(policy));
                });
            }

            refreshPolicySelect(list);
            updatePolicyCount(list.length);
        } catch (error) {
            notify('error', error.message || '정책 목록 조회 실패');
        }
    }

    function renderPolicyRow(policy) {
        const active = String(policy.is_active) === '1';

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(policy.policy_name || '-')}</strong><br>
                    <small class="text-muted">${escapeHtml(policy.policy_key || '-')}</small>
                </td>
                <td><code>${escapeHtml(policy.bucket || '-')}</code></td>
                <td>${escapeHtml(policy.allowed_ext || '-')}</td>
                <td>${policy.allowed_mime ? `<small>${escapeHtml(policy.allowed_mime)}</small>` : '<span class="text-muted">자동</span>'}</td>
                <td class="policy-description-cell">${policy.description ? `<small>${escapeHtml(policy.description)}</small>` : '<span class="text-muted">-</span>'}</td>
                <td class="text-center">${escapeHtml(String(policy.max_size_mb ?? '-'))}</td>
                <td class="text-center">
                    <span class="badge ${active ? 'bg-success' : 'bg-secondary'}">${active ? '활성' : '비활성'}</span>
                </td>
                <td class="text-center policy-actions-cell">
                    <div class="policy-action-buttons">
                        <button class="btn btn-sm btn-outline-secondary btn-edit" type="button" data-id="${escapeHtml(policy.id)}">수정</button>
                        <button class="btn btn-sm btn-outline-warning btn-toggle" type="button" data-id="${escapeHtml(policy.id)}" data-active="${active ? '1' : '0'}">
                            ${active ? '비활성' : '활성'}
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-delete" type="button" data-id="${escapeHtml(policy.id)}">삭제</button>
                    </div>
                </td>
            </tr>
        `;
    }

    function bindPolicyEvents() {
        document.getElementById('btn-add-policy')?.addEventListener('click', () => openPolicyModal(null));

        document.addEventListener('click', async event => {
            const editButton = event.target.closest('.btn-edit');
            const toggleButton = event.target.closest('.btn-toggle');
            const deleteButton = event.target.closest('.btn-delete');

            if (editButton) {
                const policy = cachedPolicies.find(item => String(item.id) === String(editButton.dataset.id));
                if (policy) {
                    openPolicyModal(policy);
                }
                return;
            }

            if (toggleButton) {
                const id = String(toggleButton.dataset.id || '');
                const next = Number(toggleButton.dataset.active || 0) === 1 ? 0 : 1;

                try {
                    const json = await fetchJson(API.POLICY_TOGGLE, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: new URLSearchParams({ id, is_active: String(next) })
                    });

                    if (!json?.success) {
                        throw new Error(json?.message || '상태 변경에 실패했습니다.');
                    }

                    notify('success', '정책 상태가 변경되었습니다.');
                    await loadPolicies();
                } catch (error) {
                    notify('error', error.message || '상태 변경 실패');
                }
                return;
            }

            if (deleteButton) {
                pendingDeleteId = String(deleteButton.dataset.id || '');
                new bootstrap.Modal(document.getElementById('policyDeleteModal')).show();
            }
        });

        document.getElementById('btn-confirm-policy-delete')?.addEventListener('click', async () => {
            if (!pendingDeleteId) return;

            try {
                const json = await fetchJson(API.POLICY_DELETE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ id: pendingDeleteId })
                });

                if (!json?.success) {
                    throw new Error(json?.message || '정책 삭제에 실패했습니다.');
                }

                bootstrap.Modal.getInstance(document.getElementById('policyDeleteModal'))?.hide();
                pendingDeleteId = null;
                notify('success', '정책이 삭제되었습니다.');
                await loadPolicies();
            } catch (error) {
                notify('error', error.message || '정책 삭제 실패');
            }
        });
    }

    function bindActiveSwitch() {
        const checkbox = document.getElementById('policy-is-active');
        const hidden = document.querySelector('#policy-form input[name="is_active"]');
        if (!checkbox || !hidden) return;

        checkbox.addEventListener('change', () => {
            hidden.value = checkbox.checked ? '1' : '0';
        });
    }

    function refreshPolicySelect(list) {
        const select = document.querySelector('select[name="policy_key"]');
        if (!select) return;

        select.innerHTML = '<option value="">정책 미사용</option>';
        list.filter(policy => String(policy.is_active) === '1').forEach(policy => {
            select.insertAdjacentHTML(
                'beforeend',
                `<option value="${escapeHtml(policy.policy_key)}">${escapeHtml(policy.policy_name)}</option>`
            );
        });
    }

    function updatePolicyCount(count) {
        const label = document.getElementById('policy-count-label');
        if (label) {
            label.textContent = `총 ${count}건`;
        }
    }

    function highlightStorageIssues() {
        document.querySelectorAll('.badge.bg-danger, .badge.bg-warning')
            .forEach(badge => badge.closest('tr')?.classList.add('table-warning'));
    }

    function openBucketModal(bucket, files) {
        const title = document.getElementById('bucketModalTitle');
        const tbody = document.getElementById('bucket-file-list');
        if (!title || !tbody) return;

        title.textContent = bucket;
        tbody.innerHTML = '';

        if (!files.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">표시할 파일이 없습니다.</td>
                </tr>
            `;
        } else {
            files.forEach(file => {
                const size = file.size ? `${(file.size / 1024).toFixed(1)} KB` : '-';
                const date = file.mtime ? new Date(file.mtime * 1000).toLocaleString() : '-';
                tbody.insertAdjacentHTML('beforeend', `
                    <tr class="bucket-item" data-type="${escapeHtml(file.type || '')}" data-path="${escapeHtml(file.db_path || '')}">
                        <td>
                            ${file.type === 'dir'
                                ? '<i class="bi bi-folder-fill text-warning me-1"></i>'
                                : '<i class="bi bi-file-earmark me-1"></i>'}
                            ${escapeHtml(file.name || '')}
                        </td>
                        <td class="text-center">${file.type === 'dir' ? '폴더' : '파일'}</td>
                        <td class="text-end">${escapeHtml(size)}</td>
                        <td class="text-center">${escapeHtml(date)}</td>
                    </tr>
                `);
            });
        }

        new bootstrap.Modal(document.getElementById('bucketModal')).show();
    }

    function openPolicyModal(policy = null) {
        const modal = document.getElementById('policyModal');
        const form = document.getElementById('policy-form');
        const title = document.getElementById('policyModalTitle');
        if (!modal || !form || !title) return;

        form.reset();

        const idInput = document.getElementById('policy-id');
        const activeCheckbox = document.getElementById('policy-is-active');
        const activeHidden = form.querySelector('input[name="is_active"]');

        if (policy) {
            title.textContent = '파일 업로드 정책 수정';
            Object.keys(policy).forEach(key => {
                if (form[key]) {
                    form[key].value = policy[key] ?? '';
                }
            });
            if (idInput) idInput.value = policy.id || '';
            const isActive = String(policy.is_active) === '1';
            if (activeCheckbox) activeCheckbox.checked = isActive;
            if (activeHidden) activeHidden.value = isActive ? '1' : '0';
        } else {
            title.textContent = '파일 업로드 정책 등록';
            if (idInput) idInput.value = '';
            if (activeCheckbox) activeCheckbox.checked = true;
            if (activeHidden) activeHidden.value = '1';
        }

        new bootstrap.Modal(modal).show();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
})();
