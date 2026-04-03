document.addEventListener('DOMContentLoaded', async () => {


    /* =====================================================
     * API
     * ===================================================== */
    const API_ME     = '/api/user/profile/detail';   // ✅
    const API_UPDATE = '/api/user/profile/save';     // ✅

    /* =====================================================
    * External Accounts API
    * ===================================================== */
    const API_EXTERNAL_LIST   = '/api/user/external-accounts';        // GET
    const API_EXTERNAL_SAVE   = '/api/user/external-accounts/save';   // POST
    const API_EXTERNAL_DELETE = '/api/user/external-accounts/delete'; // POST

    /* =====================================================
     * DOM
     * ===================================================== */
    const loading = document.getElementById('profile-loading');
    const content = document.getElementById('profile-content');

    const profileImg       = document.getElementById('profile-image');
    const usernameEl       = document.getElementById('profile-username');
    const emailEl          = document.getElementById('profile-email');

    const employeeNameEl   = document.getElementById('employee_name');
    const emailInputEl     = document.getElementById('email');
    const phoneEl          = document.getElementById('phone');
    const emergencyPhoneEl = document.getElementById('emergency_phone');
    const addressEl        = document.getElementById('address');
    const addressDetailEl  = document.getElementById('address_detail');

    const certNameEl     = document.getElementById('certificate_name');
    const certFileInput  = document.getElementById('certificate_file');
    const certFileBtn    = document.getElementById('certificate_file_btn');
    const certFileLabel  = document.getElementById('certificate_file_label');
    const certPreviewImg = document.getElementById('profile_cert_preview');

    const twoFactorEl   = document.getElementById('two_factor_enabled');
    const emailNotifyEl = document.getElementById('email_notify');
    const smsNotifyEl   = document.getElementById('sms_notify');

    const btnSaveAccount = document.getElementById('btn-save-account');
    const btnSaveNotify  = document.getElementById('btn-save-notify');

    const profileInput = document.getElementById('profile-image-input');    
    const changeBtn    = document.getElementById('btn-change-image');



    /* =====================================================
     * fetch wrapper
     * ===================================================== */
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

    /* =====================================================
     * 프로필 로드
     * ===================================================== */
    async function loadProfile() {
        try {
            loading.style.display = 'block';
            content.style.display = 'none';

            const { data: u } = await fetchJson(API_ME);

            profileImg.src         = u.profile_image
            ? `/api/file/preview?path=${encodeURIComponent(u.profile_image)}`
            : '/public/assets/img/default-avatar.png';
            usernameEl.textContent = u.username || '';
            emailEl.textContent    = u.email || '';

            employeeNameEl.value   = u.employee_name || '';
            emailInputEl.value     = u.email || '';
            phoneEl.value          = u.phone || '';
            emergencyPhoneEl.value = u.emergency_phone || '';
            addressEl.value        = u.address || '';
            addressDetailEl.value  = u.address_detail || '';
            certNameEl.value       = u.certificate_name || '';

            // 🔐 보안 / 알림
            twoFactorEl.checked   = Number(u.two_factor_enabled) === 1;
            emailNotifyEl.checked = Number(u.email_notify) === 1;
            smsNotifyEl.checked   = Number(u.sms_notify) === 1;

            // 📎 자격증 기존 파일
            if (u.certificate_file) {
                certPreviewImg.src = getCertPreview(u.certificate_file);
                certPreviewImg.dataset.filePath = u.certificate_file;
                certFileLabel.textContent = '기존 파일 있음';
            } else {
                certPreviewImg.src = '/public/assets/img/placeholder-cert.png';
                certPreviewImg.dataset.filePath = '';
                certFileLabel.textContent = '선택된 파일 없음';
            }

        } catch (err) {
            console.error('[PROFILE] load error', err);
            loading.innerHTML = `<div class="text-danger small">프로필 정보를 불러올 수 없습니다.</div>`;
        } finally {
            loading.style.display = 'none';
            content.style.display = 'block';
        }
    }
    /* =====================================================
     * 저장 (🔥 기존 정상 동작 로직 유지 + FormData)
     * ===================================================== */
    async function saveProfile() {
        if (btnSaveAccount) btnSaveAccount.disabled = true;
        if (btnSaveNotify)  btnSaveNotify.disabled  = true;

        try {
            const fd = new FormData();
    
            // profile
            fd.append('employee_name', employeeNameEl.value.trim());
            fd.append('phone', phoneEl.value.trim());
            fd.append('emergency_phone', emergencyPhoneEl.value.trim());
            fd.append('address', addressEl.value.trim());
            fd.append('address_detail', addressDetailEl.value.trim());
            fd.append('certificate_name', certNameEl.value.trim());
    
            // auth_users (⭐ 알림설정 포함)
            fd.append('email', emailInputEl.value.trim());
            fd.append('two_factor_enabled', twoFactorEl.checked ? 1 : 0);
            fd.append('email_notify', emailNotifyEl.checked ? 1 : 0);
            fd.append('sms_notify', smsNotifyEl.checked ? 1 : 0);
    
            if (certFileInput.files.length) {
                fd.append('certificate_file', certFileInput.files[0]);
            }
    
            const json = await fetchJson(API_UPDATE, {
                method: 'POST',
                body: fd
            });
    
            alert(json.message || '저장되었습니다.');
            await loadProfile();
    
        } catch (err) {
            alert(err.message || '저장 실패');
        } finally {
            if (btnSaveAccount) btnSaveAccount.disabled = false;
            if (btnSaveNotify)  btnSaveNotify.disabled  = false; 
        }
    }

    /* =====================================================
     * 자격증 미리보기 유틸 (단일 정의)
     * ===================================================== */
    function getCertPreview(filePath) {
        if (!filePath) return "/public/assets/img/placeholder-cert.png";

        const ext = filePath.split('.').pop().toLowerCase();

        // 이미지 → 서버 프록시 미리보기
        if (["jpg","jpeg","png","gif","webp"].includes(ext)) {
            return `/api/file/preview?path=${encodeURIComponent(filePath)}`;
        }

        // PDF / HWP / XLS 등 → “있음” 이미지 통일
        return "/public/assets/img/has-cert.png";
    }
    function bindSaveButtons() {
        if (btnSaveAccount) {
            btnSaveAccount.addEventListener('click', saveProfile);
        }
        if (btnSaveNotify) {
            btnSaveNotify.addEventListener('click', saveProfile);
        }
    }
    
    function bindProfileImageModal() {
        const img = document.getElementById('profile-image');
        const modalImg = document.getElementById('profile-image-modal-img');
        const modalEl = document.getElementById('profileImageModal');
        if (!img || !modalImg || !modalEl) return;
    
        img.addEventListener('click', () => {
            modalImg.src = img.src;
            new bootstrap.Modal(modalEl).show();
        });
    
        modalEl.addEventListener('hidden.bs.modal', () => {
            modalImg.src = '';
        });
    }

    /* =====================================================
     * 자격증 파일 선택
     * ===================================================== */
    if (certFileBtn && certFileInput) {
        certFileBtn.addEventListener('click', () => certFileInput.click());
    }    

    certFileInput.addEventListener('change', () => {
        if (!certFileInput.files.length) return;

        const file = certFileInput.files[0];
        certFileLabel.textContent = file.name;

        const ext = file.name.split('.').pop().toLowerCase();

        if (["jpg","jpeg","png","gif","webp"].includes(ext)) {
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

    /* =====================================================
     * 자격증 미리보기 클릭
     * ===================================================== */
    certPreviewImg.addEventListener('click', () => {
        const path = certPreviewImg.dataset.filePath;
        if (!path) {
            alert('등록된 자격증 파일이 없습니다.');
            return;
        }
        window.open(`/api/file/preview?path=${encodeURIComponent(path)}`, '_blank');
    });   
    /* =========================================================
    * 프로필 이미지 변경 (즉시 업로드)
    * ========================================================= */
    if (changeBtn && profileInput) {
        changeBtn.addEventListener('click', () => profileInput.click());
        }
        if (profileInput && profileImg) {
        profileInput.addEventListener('change', async function () {
    
            const file = this.files[0];
            if (!file) return;
    
            const fd = new FormData();
            fd.append('profile_image', file);
    
            try {
            const res = await fetch('/api/user/profile/save', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            });
    
            const json = await res.json();
    
            if (!json.success) {
                alert(json.message || '프로필 이미지 변경 실패');
                return;
            }
    
            // ✅ 즉시 UI 반영
            profileImg.src =
                json.url ||
                `/api/file/preview?path=${encodeURIComponent(json.profile_image)}`;
    
            } catch (e) {
            alert('프로필 이미지 업로드 중 오류가 발생했습니다.');
            } finally {
            // 같은 파일 다시 선택 가능하게 초기화
            profileInput.value = '';
            }
        });
        }
    /* =====================================================
    * 탭 전환 처리
    * ===================================================== */
    document.querySelectorAll('.nav-tabs .nav-link').forEach(btn => {
        btn.addEventListener('click', () => {

            // 1️⃣ 탭 버튼 active 처리
            document.querySelectorAll('.nav-tabs .nav-link')
                .forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // 2️⃣ 모든 섹션 숨김
            document.querySelectorAll('.tab-section')
                .forEach(sec => sec.style.display = 'none');

            // 3️⃣ 선택한 섹션 표시
            const tab = btn.dataset.tab;
            const target = document.getElementById(`tab-${tab}`);
            if (target) {
                target.style.display = 'block';
            }
        });
    });
    /* =====================================================
    * 비밀번호 보이고 숨기기
    * ===================================================== */
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? '🙈' : '👁';
        });
    });
    /* =====================================================
    * 비밀번호 변경 로직
    * ===================================================== */
    document.getElementById('btn-change-password')?.addEventListener('click', async () => {
        const currentEl = document.getElementById('current_password');
        const newEl     = document.getElementById('new_password');
        const confirmEl = document.getElementById('confirm_password');

        const fd = new FormData();
        fd.append('current_password', currentEl.value);
        fd.append('new_password', newEl.value);
        fd.append('confirm_password', confirmEl.value);

        try {
            const res  = await fetch('/api/user/profile/change-password', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            });

            const json = await res.json();
            alert(json.message);

            // ✅ 성공 시 입력값 초기화
            if (json.success) {
                currentEl.value = '';
                newEl.value     = '';
                confirmEl.value = '';
            }

        } catch (e) {
            alert('비밀번호 변경 중 오류가 발생했습니다.');
        }
    });





    async function renderExternalAccountList() {
        const { data } = await fetchJson(API_EXTERNAL_LIST);
    
        const listEl = document.getElementById('external-account-list');
        listEl.innerHTML = '';
    
        if (!data || !data.length) {
            listEl.innerHTML = `
              <div class="text-muted small p-2">
                연결된 외부 서비스가 없습니다.
              </div>`;
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
    
            listEl.appendChild(el);
        });
    }


    async function getRegisteredServiceKeys() {
        const { data } = await fetchJson(API_EXTERNAL_LIST);
        return (data || []).map(v => v.service_key);
    }

    
    async function openNewExternalEditor() {
        const editor = document.getElementById('external-account-editor');
        const form   = document.getElementById('external-account-form');
    
        editor.classList.remove('d-none');

        // ✅ 여기서 await 사용
        const registeredKeys = await getRegisteredServiceKeys();
    
        const services = [
            { key: 'synology', label: 'Synology Calendar' },
            { key: 'hometax',  label: '국세청 홈택스' },
            { key: 'bank_kb',  label: 'KB국민은행' },
        ];
    
        const optionsHtml = services.map(s => {
            const disabled = registeredKeys.includes(s.key) ? 'disabled' : '';
            const suffix   = disabled ? ' (이미 등록됨)' : '';
            return `<option value="${s.key}" ${disabled}>${s.label}${suffix}</option>`;
        }).join('');
    
        form.innerHTML = `
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
            <button id="btn-ext-add" class="btn btn-primary btn-sm">
              추가
            </button>
          </div>
        `;
    
        // ✅ 추가 버튼 바인딩
        document
          .getElementById('btn-ext-add')
          .addEventListener('click', saveNewExternalAccount);
    
        // ✅ 비밀번호 토글
        form.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? '🙈' : '👁';
            });
        });
    }
    
    function renderStatusBadge(item) {
        if (Number(item.is_connected) === 1) {
            return `<span class="badge bg-success">연결됨</span>`;
        }
    
        if (item.last_error_message) {
            return `<span class="badge bg-danger"
                         title="${item.last_error_message}">
                       오류
                    </span>`;
        }
    
        return `<span class="badge bg-secondary">미확인</span>`;
    }
    
    
    function openExternalEditor(item) {
        const editor = document.getElementById('external-account-editor');
        const form   = document.getElementById('external-account-form');
    
        editor.classList.remove('d-none');
    
        form.innerHTML = `
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
                    class="btn btn-primary btn-sm">
              저장
            </button>
            <button id="btn-ext-delete"
                    type="button"
                    class="btn btn-outline-danger btn-sm">
              삭제
            </button>
          </div>
        `;
    
        // ✅ 이벤트 바인딩 (핵심)
        document.getElementById('btn-ext-save')
            .addEventListener('click', () => saveExternalAccount(item.service_key));
    
        document.getElementById('btn-ext-delete')
            .addEventListener('click', () => deleteExternalAccount(item.service_key));
    
        // ✅ 비밀번호 토글 바인딩
        form.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
    
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? '🙈' : '👁';
            });
        });
    }
    


    async function saveNewExternalAccount() {        
        const serviceKey = document.getElementById('ext-service-key').value;
        const loginId    = document.getElementById('ext-login-id').value.trim();
        const password   = document.getElementById('ext-password').value.trim();

        if (!serviceKey) {
            alert('서비스를 선택하세요.');
            return;
        }

        if (!loginId) {
            alert('로그인 ID는 필수입니다.');
            return;
        }

        if (!password) {
            alert('비밀번호는 필수입니다.');
            return;
        }   
    
        await fetchJson(API_EXTERNAL_SAVE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                service_key: serviceKey,
                external_login_id: loginId,
                external_password: password
            })            
        });
    
        alert('외부 서비스가 추가되었습니다.');
    
        document
          .getElementById('external-account-editor')
          .classList.add('d-none');
    
        await renderExternalAccountList();
    }
    
    async function saveExternalAccount(serviceKey) {
        const loginId = document.getElementById('ext-login-id')?.value.trim();

        if (!loginId) {
            alert('로그인 ID는 필수입니다.');
            return;
        }

        const payload = {
            service_key: serviceKey,
            external_login_id: document.getElementById('ext-login-id').value.trim()
        };
    
        const pw = document.getElementById('ext-password').value.trim();
        if (pw) payload.external_password = pw;
    
        await fetchJson(API_EXTERNAL_SAVE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
    
        alert('저장되었습니다.');
        await renderExternalAccountList();
    }
    
    async function deleteExternalAccount(serviceKey) {
        if (!confirm('이 외부 계정을 완전히 삭제하시겠습니까?')) return;
    
        await fetchJson(API_EXTERNAL_DELETE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                service_key: serviceKey   // ✅ 통일
            })
        });
    
        alert('삭제되었습니다.');
    
        document
            .getElementById('external-account-editor')
            .classList.add('d-none');
    
        await renderExternalAccountList();
    }
    

      

    /* =====================================================
     * External Account Bindings
     * ===================================================== */
    document
      .getElementById('btn-add-external')
      ?.addEventListener('click', openNewExternalEditor);

    /* =====================================================
     * 초기 실행
     * ===================================================== */
    bindSaveButtons();
    bindProfileImageModal();
    window.KakaoAddress?.bind();

    await loadProfile();
    await renderExternalAccountList();
});


