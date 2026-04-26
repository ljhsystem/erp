import {
    onlyNumber,
    formatMobile
} from '/public/assets/js/common/format.js';

(() => {
    'use strict';

    console.log('[auth-profile.js] loaded');

    /* ============================================================
     * API
     * ============================================================ */
    const API = {
        DETAIL: '/api/user/profile/detail',
        SAVE: '/api/user/profile/save',
        CHANGE_PASSWORD: '/api/user/profile/change-password',

        EXTERNAL_LIST: '/api/user/external-accounts',
        EXTERNAL_SAVE: '/api/user/external-accounts/save',
        EXTERNAL_DELETE: '/api/user/external-accounts/delete'
    };

    /* ============================================================
     * 상태
     * ============================================================ */
    let currentProfileImagePath = '';
    let currentCertificatePath = '';
    let isInitialized = false;

    /* ============================================================
     * DOM READY
     * ============================================================ */
    document.addEventListener('DOMContentLoaded', async () => {
        initProfilePage();
    });

    /* ============================================================
     * PAGE INIT
     * ============================================================ */
    async function initProfilePage() {
        if (isInitialized) return;
        isInitialized = true;

        bindSaveButtons();
        bindProfileImageModal();
        bindProfileImagePicker();
        bindCertificatePicker();
        bindTabs();
        bindPasswordToggles(document);
        bindPasswordChange();
        bindExternalEvents();
        bindInputFormatters();

        if (window.KakaoAddress) {
            window.KakaoAddress.bind();
        }

        await loadProfile();
        await renderExternalAccountList();
    }

    /* ============================================================
     * DOM HELPERS
     * ============================================================ */
    function getDom() {
        return {
            loading: document.getElementById('profile-loading'),
            content: document.getElementById('profile-content'),

            profileImg: document.getElementById('profile-image'),
            profileModalImg: document.getElementById('profile-image-modal-img'),
            profileModalEl: document.getElementById('profileImageModal'),
            profileInput: document.getElementById('profile-image-input'),
            changeBtn: document.getElementById('btn-change-image'),

            usernameEl: document.getElementById('profile-username'),
            emailEl: document.getElementById('profile-email'),

            employeeNameEl: document.getElementById('employee_name'),
            emailInputEl: document.getElementById('email'),
            phoneEl: document.getElementById('phone'),
            emergencyPhoneEl: document.getElementById('emergency_phone'),
            addressEl: document.getElementById('address'),
            addressDetailEl: document.getElementById('address_detail'),

            certNameEl: document.getElementById('certificate_name'),
            certFileInput: document.getElementById('certificate_file'),
            certPreviewImg: document.getElementById('profile_cert_preview'),
            certDeleteBtn: document.getElementById('certificate_file_delete_btn'),
            certDeleteFlag: document.getElementById('certificate_file_delete'),
            certBox: document.getElementById('profile_cert_box'),

            twoFactorEl: document.getElementById('two_factor_enabled'),
            emailNotifyEl: document.getElementById('email_notify'),
            smsNotifyEl: document.getElementById('sms_notify'),

            btnSaveAccount: document.getElementById('btn-save-account'),
            btnSaveCertificate: document.getElementById('btn-save-certificate'),
            btnSaveNotify: document.getElementById('btn-save-notify'),

            btnChangePassword: document.getElementById('btn-change-password'),
            currentPasswordEl: document.getElementById('current_password'),
            newPasswordEl: document.getElementById('new_password'),
            confirmPasswordEl: document.getElementById('confirm_password'),

            externalListEl: document.getElementById('external-account-list'),
            externalEditorEl: document.getElementById('external-account-editor'),
            externalFormEl: document.getElementById('external-account-form'),
            btnAddExternal: document.getElementById('btn-add-external')
        };
    }

    /* ============================================================
     * 공통
     * ============================================================ */
    function notify(type, message, opts = {}) {
        if (window.AppCore && typeof window.AppCore.notify === 'function') {
            window.AppCore.notify(type, message, opts);
            return;
        }

        console.log(`[${type}] ${message}`);
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        if (res.status === 401) {
            location.href = '/login';
            throw new Error('Unauthorized');
        }

        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            throw new Error('Invalid JSON response (HTML received)');
        }

        const json = await res.json();

        if (json.success === false) {
            throw new Error(json.message || 'API 오류');
        }

        return json;
    }

    function getProfileImageUrl(path) {
        if (!path) {
            return '/public/assets/img/default-avatar.png';
        }
    
        return `/api/file/preview?path=${encodeURIComponent(path)}`;
    }
    
    function hasProfileImage() {
        return !!currentProfileImagePath;
    }

    function getCertPreview(filePath) {
        if (!filePath) return '/public/assets/img/placeholder-cert.png';

        const ext = String(filePath).split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            return `/api/file/preview?path=${encodeURIComponent(filePath)}`;
        }

        return '/public/assets/img/has-cert.png';
    }

    function resetFileInputs() {
        const {
            profileInput,
            certFileInput,
            certDeleteFlag
        } = getDom();

        if (profileInput) profileInput.value = '';
        if (certFileInput) certFileInput.value = '';
        if (certDeleteFlag) certDeleteFlag.value = '0';
    }

    /* ============================================================
     * 프로필 로드
     * ============================================================ */
    async function loadProfile() {
        const {
            loading,
            content,
            profileImg,
            usernameEl,
            emailEl,
            employeeNameEl,
            emailInputEl,
            phoneEl,
            emergencyPhoneEl,
            addressEl,
            addressDetailEl,
            certNameEl,
            certPreviewImg,
            certDeleteBtn,
            certBox,
            twoFactorEl,
            emailNotifyEl,
            smsNotifyEl
        } = getDom();

        try {
            if (loading) loading.style.display = 'block';
            if (content) content.style.display = 'none';

            const { data: u } = await fetchJson(API.DETAIL);

            currentProfileImagePath = u.profile_image || '';
            currentCertificatePath = u.certificate_file || '';

            if (profileImg) {
                profileImg.src = getProfileImageUrl(currentProfileImagePath);
            
                // 🔥 상태 표시
                if (!currentProfileImagePath) {
                    profileImg.classList.add('is-empty');
                    profileImg.title = '등록된 프로필 사진이 없습니다.';
                } else {
                    profileImg.classList.remove('is-empty');
                    profileImg.title = '';
                }
            }

            if (usernameEl) usernameEl.textContent = u.username || '';
            if (emailEl) emailEl.textContent = u.email || '';

            if (employeeNameEl) employeeNameEl.value = u.employee_name || '';
            if (emailInputEl) emailInputEl.value = u.email || '';
            if (phoneEl) phoneEl.value = formatMobile(u.phone || '');
            if (emergencyPhoneEl) emergencyPhoneEl.value = formatMobile(u.emergency_phone || '');
            if (addressEl) addressEl.value = u.address || '';
            if (addressDetailEl) addressDetailEl.value = u.address_detail || '';
            if (certNameEl) certNameEl.value = u.certificate_name || '';

            if (twoFactorEl) twoFactorEl.checked = Number(u.two_factor_enabled) === 1;
            if (emailNotifyEl) emailNotifyEl.checked = Number(u.email_notify) === 1;
            if (smsNotifyEl) smsNotifyEl.checked = Number(u.sms_notify) === 1;

            if (certPreviewImg) {
                if (currentCertificatePath) {
                    certPreviewImg.src = getCertPreview(currentCertificatePath);
                    certPreviewImg.dataset.filePath = currentCertificatePath;
                } else {
                    certPreviewImg.src = '/public/assets/img/placeholder-cert.png';
                    certPreviewImg.dataset.filePath = '';
                }
            }

            if (certDeleteBtn) {
                certDeleteBtn.style.display = currentCertificatePath ? '' : 'none';
            }

            if (certBox) {
                certBox.dataset.label = currentCertificatePath ? '원본 보기' : '업로드';
            }

            resetFileInputs();

        } catch (err) {
            console.error('[PROFILE] load error', err);

            if (loading) {
                loading.innerHTML = `<div class="text-danger small">프로필 정보를 불러올 수 없습니다.</div>`;
            }
        } finally {
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        }
    }

    /* ============================================================
     * 저장
     * ============================================================ */
    async function saveProfile() {
        const {
            employeeNameEl,
            emailInputEl,
            phoneEl,
            emergencyPhoneEl,
            addressEl,
            addressDetailEl,
            certNameEl,
            twoFactorEl,
            emailNotifyEl,
            smsNotifyEl,
            profileInput,
            certFileInput,
            certDeleteFlag,
            btnSaveAccount,
            btnSaveCertificate,
            btnSaveNotify
        } = getDom();

        if (btnSaveAccount) btnSaveAccount.disabled = true;
        if (btnSaveCertificate) btnSaveCertificate.disabled = true;
        if (btnSaveNotify) btnSaveNotify.disabled = true;

        try {
            const fd = new FormData();

            fd.append('employee_name', employeeNameEl?.value.trim() ?? '');
            fd.append('phone', onlyNumber(phoneEl?.value ?? ''));
            fd.append('emergency_phone', onlyNumber(emergencyPhoneEl?.value ?? ''));
            fd.append('address', addressEl?.value.trim() ?? '');
            fd.append('address_detail', addressDetailEl?.value.trim() ?? '');
            fd.append('certificate_name', certNameEl?.value.trim() ?? '');

            fd.append('email', emailInputEl?.value.trim() ?? '');
            fd.append('two_factor_enabled', twoFactorEl?.checked ? '1' : '0');
            fd.append('email_notify', emailNotifyEl?.checked ? '1' : '0');
            fd.append('sms_notify', smsNotifyEl?.checked ? '1' : '0');

            if (profileInput && profileInput.files.length) {
                fd.append('profile_image', profileInput.files[0]);
            }

            if (certFileInput && certFileInput.files.length) {
                fd.append('certificate_file', certFileInput.files[0]);
            }

            fd.append('certificate_file_delete', certDeleteFlag?.value === '1' ? '1' : '0');

            const json = await fetchJson(API.SAVE, {
                method: 'POST',
                body: fd
            });

            notify('success', json.message || '저장되었습니다.');

            await loadProfile();

        } catch (err) {
            console.error('[PROFILE] save error', err);
            notify('error', err.message || '저장 실패');
        } finally {
            if (btnSaveAccount) btnSaveAccount.disabled = false;
            if (btnSaveCertificate) btnSaveCertificate.disabled = false;
            if (btnSaveNotify) btnSaveNotify.disabled = false;
        }
    }

    function bindSaveButtons() {
        const { btnSaveAccount, btnSaveCertificate, btnSaveNotify } = getDom();

        btnSaveAccount?.addEventListener('click', saveProfile);
        btnSaveCertificate?.addEventListener('click', saveProfile);
        btnSaveNotify?.addEventListener('click', saveProfile);
    }

    /* ============================================================
     * 입력 포맷
     * ============================================================ */
    function bindInputFormatters() {
        const { phoneEl, emergencyPhoneEl } = getDom();

        if (phoneEl) {
            phoneEl.addEventListener('input', () => {
                phoneEl.value = formatMobile(phoneEl.value);
            });
        }

        if (emergencyPhoneEl) {
            emergencyPhoneEl.addEventListener('input', () => {
                emergencyPhoneEl.value = formatMobile(emergencyPhoneEl.value);
            });
        }
    }

    /* ============================================================
     * 프로필 이미지 모달
     * ============================================================ */
    function bindProfileImageModal() {
        const { profileImg, profileModalImg, profileModalEl } = getDom();
        if (!profileImg || !profileModalImg || !profileModalEl) return;

        profileImg.addEventListener('click', () => {

            // 🔥 프로필 없을때 차단
            if (!currentProfileImagePath) {
                notify('warning', '등록된 프로필 사진이 없습니다.');
                return;
            }
        
            profileModalImg.src = profileImg.src;
            new bootstrap.Modal(profileModalEl).show();
        });

        profileModalEl.addEventListener('hidden.bs.modal', () => {
            profileModalImg.src = '';
        });
    }

    /* ============================================================
     * 프로필 이미지 선택
     * - 즉시 업로드 금지
     * - 미리보기만 변경
     * ============================================================ */
    function bindProfileImagePicker() {
        const { changeBtn, profileInput, profileImg } = getDom();

        if (changeBtn && profileInput) {
            changeBtn.addEventListener('click', () => profileInput.click());
        }

        if (!profileInput || !profileImg) return;

        profileInput.addEventListener('change', () => {
            if (!profileInput.files.length) {

                profileImg.src = getProfileImageUrl(currentProfileImagePath);
            
                if (!currentProfileImagePath) {
                    profileImg.classList.add('is-empty');
                    profileImg.title = '등록된 프로필 사진이 없습니다.';
                } else {
                    profileImg.classList.remove('is-empty');
                    profileImg.title = '';
                }
            
                return;
            }
            const file = profileInput.files[0];
            const ext = String(file.name).split('.').pop().toLowerCase();

            if (!['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                notify('warning', '이미지 파일만 선택할 수 있습니다.');
                profileInput.value = '';
                profileImg.src = getProfileImageUrl(currentProfileImagePath);

                if (!currentProfileImagePath) {
                    profileImg.classList.add('is-empty');
                    profileImg.title = '등록된 프로필 사진이 없습니다.';
                } else {
                    profileImg.classList.remove('is-empty');
                    profileImg.title = '';
                }
                return;
            }

            const reader = new FileReader();
            reader.onload = e => {
                profileImg.src = e.target.result;
            
                // 🔥 추가
                profileImg.classList.remove('is-empty');
                profileImg.title = '';
            };
            reader.readAsDataURL(file);
        });
    }

    /* ============================================================
     * 자격증 선택 / 미리보기
     * ============================================================ */
    function bindCertificatePicker() {
        const {
            certFileInput,
            certPreviewImg,
            certDeleteBtn,
            certDeleteFlag,
            certBox,
            certNameEl
        } = getDom();

        if (certDeleteBtn) {
            certDeleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                currentCertificatePath = '';

                if (certPreviewImg) {
                    certPreviewImg.src = '/public/assets/img/placeholder-cert.png';
                    certPreviewImg.dataset.filePath = '';
                }

                if (certFileInput) {
                    certFileInput.value = '';
                }

                if (certDeleteFlag) {
                    certDeleteFlag.value = '1';
                }

                if (certNameEl) {
                    certNameEl.value = '';
                }

                if (certBox) {
                    certBox.dataset.label = '업로드';
                }

                certDeleteBtn.style.display = 'none';
            });
        }

        if (certFileInput && certPreviewImg) {
            certFileInput.addEventListener('change', () => {
                if (!certFileInput.files.length) {
                    certPreviewImg.src = currentCertificatePath
                        ? getCertPreview(currentCertificatePath)
                        : '/public/assets/img/placeholder-cert.png';

                    certPreviewImg.dataset.filePath = currentCertificatePath || '';

                    if (certDeleteBtn) {
                        certDeleteBtn.style.display = currentCertificatePath ? '' : 'none';
                    }

                    if (certBox) {
                        certBox.dataset.label = currentCertificatePath ? '원본 보기' : '업로드';
                    }

                    return;
                }

                const file = certFileInput.files[0];
                const ext = String(file.name).split('.').pop().toLowerCase();

                if (certDeleteFlag) {
                    certDeleteFlag.value = '0';
                }

                if (certDeleteBtn) {
                    certDeleteBtn.style.display = '';
                }

                if (certBox) {
                    certBox.dataset.label = '원본 보기';
                }

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        certPreviewImg.src = e.target.result;
                        certPreviewImg.dataset.filePath = '';
                    };
                    reader.readAsDataURL(file);
                } else {
                    certPreviewImg.src = '/public/assets/img/has-cert.png';
                    certPreviewImg.dataset.filePath = '';
                }
            });
        }

        if (certPreviewImg) {
            certPreviewImg.addEventListener('click', () => {
                const path = certPreviewImg.dataset.filePath || '';

                if (!path) {
                    certFileInput?.click();
                    return;
                }

                window.open(`/api/file/preview?path=${encodeURIComponent(path)}`, '_blank');
            });
        }
    }

    /* ============================================================
     * 탭
     * ============================================================ */
    function bindTabs() {
        document.querySelectorAll('.nav-tabs .nav-link').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.nav-tabs .nav-link')
                    .forEach(b => b.classList.remove('active'));

                btn.classList.add('active');

                document.querySelectorAll('.tab-section')
                    .forEach(sec => {
                        sec.style.display = 'none';
                    });

                const tab = btn.dataset.tab;
                const target = document.getElementById(`tab-${tab}`);
                if (target) {
                    target.style.display = 'block';
                }
            });
        });
    }

    /* ============================================================
     * 비밀번호 토글
     * ============================================================ */
    function bindPasswordToggles(scope = document) {
        scope.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;

                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? '🙈' : '👁';
            });
        });
    }

    /* ============================================================
     * 비밀번호 변경
     * ============================================================ */
    function bindPasswordChange() {
        const { btnChangePassword } = getDom();
        btnChangePassword?.addEventListener('click', changePassword);
    }

    async function changePassword() {
        const {
            currentPasswordEl,
            newPasswordEl,
            confirmPasswordEl
        } = getDom();

        const fd = new FormData();
        fd.append('current_password', currentPasswordEl?.value ?? '');
        fd.append('new_password', newPasswordEl?.value ?? '');
        fd.append('confirm_password', confirmPasswordEl?.value ?? '');

        try {
            const res = await fetch(API.CHANGE_PASSWORD, {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            });

            const ct = res.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                throw new Error('Invalid JSON response (HTML received)');
            }

            const json = await res.json();

            if (!json.success) {
                throw new Error(json.message || '비밀번호 변경 실패');
            }

            notify('success', json.message || '비밀번호가 변경되었습니다.');

            if (currentPasswordEl) currentPasswordEl.value = '';
            if (newPasswordEl) newPasswordEl.value = '';
            if (confirmPasswordEl) confirmPasswordEl.value = '';

        } catch (e) {
            console.error('[PROFILE] password change error', e);
            notify('error', e.message || '비밀번호 변경 중 오류가 발생했습니다.');
        }
    }

    /* ============================================================
     * 외부 계정
     * ============================================================ */
    function bindExternalEvents() {
        const { btnAddExternal } = getDom();
        btnAddExternal?.addEventListener('click', openNewExternalEditor);
    }

    async function renderExternalAccountList() {
        const { externalListEl } = getDom();
        if (!externalListEl) return;

        const { data } = await fetchJson(API.EXTERNAL_LIST);

        externalListEl.innerHTML = '';

        if (!data || !data.length) {
            externalListEl.innerHTML = `
                <div class="text-muted small p-2">
                    연결된 외부 서비스가 없습니다.
                </div>
            `;
            return;
        }

        data.forEach(item => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'list-group-item list-group-item-action';

            el.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span>${item.service_name}</span>
                    ${renderStatusBadge(item)}
                </div>
            `;

            el.addEventListener('click', () => {
                document
                    .querySelectorAll('#external-account-list .active')
                    .forEach(x => x.classList.remove('active'));

                el.classList.add('active');
                openExternalEditor(item);
            });

            externalListEl.appendChild(el);
        });
    }

    async function getRegisteredServiceKeys() {
        const { data } = await fetchJson(API.EXTERNAL_LIST);
        return (data || []).map(v => v.service_key);
    }

    function renderStatusBadge(item) {
        if (Number(item.is_connected) === 1) {
            return `<span class="badge bg-success">연결됨</span>`;
        }

        if (item.last_error_message) {
            return `<span class="badge bg-danger" title="${item.last_error_message}">오류</span>`;
        }

        return `<span class="badge bg-secondary">미확인</span>`;
    }

    async function openNewExternalEditor() {
        const { externalEditorEl, externalFormEl } = getDom();
        if (!externalEditorEl || !externalFormEl) return;

        externalEditorEl.classList.remove('d-none');

        const registeredKeys = await getRegisteredServiceKeys();

        const services = [
            { key: 'synology', label: 'Synology Calendar' },
            { key: 'hometax', label: '국세청 홈택스' },
            { key: 'bank_kb', label: 'KB국민은행' }
        ];

        const optionsHtml = services.map(s => {
            const disabled = registeredKeys.includes(s.key) ? 'disabled' : '';
            const suffix = disabled ? ' (이미 등록됨)' : '';
            return `<option value="${s.key}" ${disabled}>${s.label}${suffix}</option>`;
        }).join('');

        externalFormEl.innerHTML = `
            <h6 class="fw-bold mb-3">외부 서비스 추가</h6>

            <div class="mb-2">
                <label class="form-label small">서비스 종류</label>
                <select id="ext-service-key" class="form-select form-select-sm">
                    <option value="">선택하세요</option>
                    ${optionsHtml}
                </select>
            </div>

            <div class="mb-2">
                <label class="form-label small">로그인 ID</label>
                <input id="ext-login-id" class="form-control form-control-sm">
            </div>

            <div class="mb-2">
                <label class="form-label small">비밀번호</label>
                <div class="input-group input-group-sm">
                    <input id="ext-password" type="password" class="form-control">
                    <button type="button"
                            class="btn btn-outline-secondary password-toggle"
                            data-target="ext-password">👁</button>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button id="btn-ext-add" type="button" class="btn btn-primary btn-sm">추가</button>
            </div>
        `;

        document.getElementById('btn-ext-add')
            ?.addEventListener('click', saveNewExternalAccount);

        bindPasswordToggles(externalFormEl);
    }

    function openExternalEditor(item) {
        const { externalEditorEl, externalFormEl } = getDom();
        if (!externalEditorEl || !externalFormEl) return;

        externalEditorEl.classList.remove('d-none');

        externalFormEl.innerHTML = `
            <h6 class="fw-bold mb-3">${item.service_name}</h6>

            <div class="mb-2">
                <label class="form-label small">로그인 ID</label>
                <input id="ext-login-id"
                       class="form-control form-control-sm"
                       value="${item.external_login_id ?? ''}">
            </div>

            <div class="mb-2">
                <label class="form-label small">비밀번호</label>
                <div class="input-group input-group-sm">
                    <input id="ext-password"
                           type="password"
                           class="form-control"
                           placeholder="변경 시에만 입력">
                    <button type="button"
                            class="btn btn-outline-secondary password-toggle"
                            data-target="ext-password">👁</button>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button id="btn-ext-save"
                        type="button"
                        class="btn btn-primary btn-sm">저장</button>
                <button id="btn-ext-delete"
                        type="button"
                        class="btn btn-outline-danger btn-sm">삭제</button>
            </div>
        `;

        document.getElementById('btn-ext-save')
            ?.addEventListener('click', () => saveExternalAccount(item.service_key));

        document.getElementById('btn-ext-delete')
            ?.addEventListener('click', () => deleteExternalAccount(item.service_key));

        bindPasswordToggles(externalFormEl);
    }

    async function saveNewExternalAccount() {
        const serviceKey = document.getElementById('ext-service-key')?.value ?? '';
        const loginId = document.getElementById('ext-login-id')?.value.trim() ?? '';
        const password = document.getElementById('ext-password')?.value.trim() ?? '';

        if (!serviceKey) {
            notify('warning', '서비스를 선택하세요.');
            return;
        }

        if (!loginId) {
            notify('warning', '로그인 ID는 필수입니다.');
            return;
        }

        if (!password) {
            notify('warning', '비밀번호는 필수입니다.');
            return;
        }

        try {
            await fetchJson(API.EXTERNAL_SAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_key: serviceKey,
                    external_login_id: loginId,
                    external_password: password
                })
            });

            notify('success', '외부 서비스가 추가되었습니다.');

            document.getElementById('external-account-editor')
                ?.classList.add('d-none');

            await renderExternalAccountList();

        } catch (err) {
            console.error('[PROFILE] external add error', err);
            notify('error', err.message || '외부 서비스 추가 실패');
        }
    }

    async function saveExternalAccount(serviceKey) {
        const loginId = document.getElementById('ext-login-id')?.value.trim() ?? '';

        if (!loginId) {
            notify('warning', '로그인 ID는 필수입니다.');
            return;
        }

        const payload = {
            service_key: serviceKey,
            external_login_id: loginId
        };

        const pw = document.getElementById('ext-password')?.value.trim() ?? '';
        if (pw) {
            payload.external_password = pw;
        }

        try {
            await fetchJson(API.EXTERNAL_SAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            notify('success', '저장되었습니다.');
            await renderExternalAccountList();

        } catch (err) {
            console.error('[PROFILE] external save error', err);
            notify('error', err.message || '외부 서비스 저장 실패');
        }
    }

    async function deleteExternalAccount(serviceKey) {
        if (!confirm('이 외부 계정을 완전히 삭제하시겠습니까?')) return;

        try {
            await fetchJson(API.EXTERNAL_DELETE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_key: serviceKey
                })
            });

            notify('success', '삭제되었습니다.');

            document.getElementById('external-account-editor')
                ?.classList.add('d-none');

            await renderExternalAccountList();

        } catch (err) {
            console.error('[PROFILE] external delete error', err);
            notify('error', err.message || '외부 서비스 삭제 실패');
        }
    }

})();
