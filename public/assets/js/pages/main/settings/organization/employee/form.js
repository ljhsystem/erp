import { readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';

const EMPLOYEE_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.main.settings.organization.employee.employee-table.v1';
const EMPLOYEE_MODAL_FIELD_POLICIES = Object.freeze([
    { selector: '#edit_employee_username', key: 'username', fallback: '아이디' },
    { selector: '#edit_employee_name', key: 'employee_name', fallback: '직원명' },
    { selector: '#edit_employee_phone', key: 'phone', fallback: '연락처' },
    { selector: '#edit_employee_emergency_phone', key: 'emergency_phone', fallback: '비상연락처' },
    { selector: '#edit_employee_email', key: 'email', fallback: '이메일' },
    { selector: '#edit_employee_rrn', key: 'rrn', fallback: '주민등록번호' },
    { selector: '#edit_employee_address', key: 'address', fallback: '주소' },
    { selector: '#edit_employee_address_detail', key: 'address_detail', fallback: '상세주소' },
    { selector: '#edit_employee_password', key: 'password', fallback: '비밀번호' },
    { selector: '#edit_department_select', key: 'department_id', fallback: '부서' },
    { selector: '#edit_position_select', key: 'position_id', fallback: '직책' },
    { selector: '#edit_job_select', key: 'job_id', fallback: '직무' },
    { selector: '#edit_employment_status', key: 'employment_status', fallback: '재직상태' },
    { selector: '#edit_role_select', key: 'role_id', fallback: '역할' },
    { selector: '#edit_doc_hire_date', key: 'doc_hire_date', fallback: '서류입사일' },
    { selector: '#edit_real_hire_date', key: 'real_hire_date', fallback: '실입사일' },
    { selector: '#edit_doc_retire_date', key: 'doc_retire_date', fallback: '서류퇴사일' },
    { selector: '#edit_real_retire_date', key: 'real_retire_date', fallback: '실퇴사일' },
    { selector: '#edit_profile_image', key: 'profile_image', fallback: '프로필사진' },
    { selector: '#edit_rrn_image', key: 'rrn_image', fallback: '신분증파일' },
    { selector: '#edit_representative_qualification_id', key: 'representative_qualification_id', fallback: '대표 자격증' },
    { selector: '#edit_bank_name', key: 'bank_name', fallback: '은행명' },
    { selector: '#edit_account_number', key: 'account_number', fallback: '계좌번호' },
    { selector: '#edit_account_holder', key: 'account_holder', fallback: '예금주' },
    { selector: '#edit_bank_file', key: 'bank_file', fallback: '통장사본' },
    { selector: '#edit_two_factor', key: 'two_factor_enabled', fallback: '2차인증' },
    { selector: '#edit_email_notify', key: 'email_notify', fallback: '이메일알림' },
    { selector: '#edit_sms_notify', key: 'sms_notify', fallback: 'SMS알림' },
    { selector: '#edit_employee_note', key: 'note', fallback: '노트' },
    { selector: '#edit_employee_memo', key: 'memo', fallback: '메모' },
]);
let employeePolicyBound = false;

function currentEmployeePolicyState() {
    return readDataTableSettingsState(EMPLOYEE_TABLE_SETTINGS_STORAGE_KEY, { userSettingPageKey: 'employee' }) || {};
}

function employeeFieldLabel(key, fallback = '') {
    const normalizedKey = String(key || '').trim();
    return resolveDataTableColumnDisplayName(
        { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
        currentEmployeePolicyState(),
        fallback || normalizedKey
    );
}

function employeeFieldRequirement(key) {
    const normalizedKey = String(key || '').trim();
    return resolveDataTableColumnRequirementPolicy(
        { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
        currentEmployeePolicyState()
    );
}

function employeeFieldStarMarkup(key) {
    const policy = employeeFieldRequirement(key);
    if (policy === 'required') return '<span class="column-policy-star is-required" aria-label="필수">*</span>';
    if (policy === 'optional') return '<span class="column-policy-star is-optional" aria-label="선택">*</span>';
    return '';
}

function findEmployeeModalLabel(fieldSelector, root = document) {
    const field = root.querySelector(fieldSelector);
    if (!field) return null;
    if (field.id) {
        const labelByFor = root.querySelector(`label[for="${field.id}"]`);
        if (labelByFor) return labelByFor;
    }
    const column = field.closest('div[class*="col-"]');
    if (column) return column.querySelector('label.form-label');
    return field.closest('label.form-label');
}

export function applyEmployeeModalPolicyLabels(root = document) {
    EMPLOYEE_MODAL_FIELD_POLICIES.forEach((field) => {
        const labelEl = findEmployeeModalLabel(field.selector, root);
        if (!labelEl) return;
        const displayName = employeeFieldLabel(field.key, field.fallback);
        const starMarkup = employeeFieldStarMarkup(field.key);
        labelEl.innerHTML = `${escapeHtml(displayName)}${starMarkup ? ` ${starMarkup}` : ''}`;
    });
}

function isEmployeeFilePolicyKey(key) {
    return ['profile_image', 'rrn_image', 'bank_file'].includes(String(key || '').trim());
}

function isEmployeeFieldVisible(field) {
    if (!field || field.type === 'hidden' || field.disabled) return false;
    const style = window.getComputedStyle(field);
    return style.display !== 'none' && style.visibility !== 'hidden';
}

function hasEmployeeFileValue(key) {
    const definitions = {
        profile_image: ['edit_profile_image', 'edit_profile_image_delete', 'edit_profile_preview'],
        rrn_image: ['edit_rrn_image', 'edit_rrn_image_delete', 'edit_id_preview'],
        representative_qualification_id: ['edit_representative_qualification_file', 'edit_representative_qualification_delete', 'edit_representative_qualification_preview'],
        bank_file: ['edit_bank_file', 'edit_bank_file_delete', 'edit_bank_preview'],
    };
    const definition = definitions[String(key || '').trim()];
    if (!definition) return false;
    const [inputId, deleteId, previewId] = definition;
    const input = document.getElementById(inputId);
    const deleteFlag = document.getElementById(deleteId);
    const preview = document.getElementById(previewId);
    const hasExisting = String(preview?.getAttribute('data-file-path') || '').trim() !== '';
    return (input?.files?.length || 0) > 0 || (hasExisting && String(deleteFlag?.value || '0') !== '1');
}

export function collectEmployeeDetailValues(form, formData) {
    const values = {};
    EMPLOYEE_MODAL_FIELD_POLICIES.forEach((field) => {
        const key = String(field?.key || '').trim();
        if (!key || isEmployeeFilePolicyKey(key)) return;
        const input = form?.querySelector(field.selector) || document.querySelector(field.selector);
        if (!input) return;
        if (input.type === 'checkbox') {
            values[key] = input.checked ? '1' : '0';
            return;
        }
        values[key] = formData.get(String(input.name || key).trim()) ?? input.value ?? '';
    });
    return values;
}

export function validateEmployeeRequiredPolicies(values = {}) {
    for (const field of EMPLOYEE_MODAL_FIELD_POLICIES) {
        const key = String(field?.key || '').trim();
        if (!key || employeeFieldRequirement(key) !== 'required') continue;
        const input = document.querySelector(field.selector);
        if (!isEmployeeFieldVisible(input)) continue;
        const label = employeeFieldLabel(key, field.fallback || key);
        const hasValue = isEmployeeFilePolicyKey(key)
            ? hasEmployeeFileValue(key)
            : String(values[key] ?? '').trim() !== '';
        if (!hasValue) return `${label} 항목은 필수입니다.`;
    }
    return '';
}

export function bindEmployeePolicySync() {
    if (employeePolicyBound) return;
    employeePolicyBound = true;
    document.addEventListener('datatable-settings:updated', (event) => {
        const storageKey = String(event?.detail?.storageKey || '').trim();
        if (storageKey && storageKey !== EMPLOYEE_TABLE_SETTINGS_STORAGE_KEY) return;
        applyEmployeeModalPolicyLabels(document);
    });
}

export function bindFilePreviewAndDeleteEvents($) {
    $(document)
        .off('click.profilePreview')
        .on('click.profilePreview', '#edit_profile_preview', function () {
            const src = $(this).attr('src');

            if (!src || src.includes('default-avatar.png')) {
                $('#edit_profile_image').trigger('click');
                return;
            }

            window.open(src, '_blank');
        });

    $(document)
        .off('click.idPreview')
        .on('click.idPreview', '#edit_id_preview', function () {
            const src = $(this).attr('src');

            if (!src || src.includes('placeholder-id.png')) {
                $('#edit_rrn_image').trigger('click');
                return;
            }

            window.open(src, '_blank');
        });

    $(document)
        .off('click.representativeQualificationPreview')
        .on('click.representativeQualificationPreview', '#edit_representative_qualification_preview', function () {
            const filePath = $(this).attr('data-file-path');
            if (!filePath) {
                $('#edit_representative_qualification_file').trigger('click');
                return;
            }
            window.open(`/api/file/preview?path=${encodeURIComponent(filePath)}`, '_blank');
        });

    $(document)
        .off('click.employeeProfileDelete')
        .on('click.employeeProfileDelete', '#edit_profile_delete_btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('#edit_profile_preview')
                .attr('src', '/public/assets/img/default-avatar.png')
                .removeAttr('data-file-path');

            const $input = $('#edit_profile_image');
            const $newInput = $input.clone().val('');
            $input.replaceWith($newInput);

            $('#edit_profile_image_delete').val('1');
            $('#profile_box').attr('data-label', '업로드');
            $(this).hide();
        });

    $(document)
        .off('click.employeeIdDelete')
        .on('click.employeeIdDelete', '#edit_id_delete_btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('#edit_id_preview')
                .attr('src', '/public/assets/img/placeholder-id.png')
                .removeAttr('data-file-path');

            const $input = $('#edit_rrn_image');
            const $newInput = $input.clone().val('');
            $input.replaceWith($newInput);

            $('#edit_rrn_image_delete').val('1');
            $('#id_box').attr('data-label', '업로드');
            $(this).hide();
        });

    $(document)
        .off('click.employeeRepresentativeQualificationDelete')
        .on('click.employeeRepresentativeQualificationDelete', '#edit_representative_qualification_delete_btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $('#edit_representative_qualification_preview').attr('src', '/public/assets/img/placeholder-cert.png').attr('data-file-path', '');
            const $input = $('#edit_representative_qualification_file');
            $input.replaceWith($input.clone().val(''));
            $('#edit_representative_qualification_delete').val('1');
            $('#edit_representative_qualification_name').val('');
            $('#representative_qualification_box').attr('data-label', '업로드');
            $(this).hide();
        });

    $(document)
        .off('change.employeeProfilePreview', '#edit_profile_image')
        .on('change.employeeProfilePreview', '#edit_profile_image', function () {
            const file = this.files?.[0];
            if (!file) return;

            $('#edit_profile_image_delete').val('0');
            $('#edit_profile_delete_btn').show();
            $('#profile_box').attr('data-label', '원본 보기');

            const reader = new FileReader();
            reader.onload = function (e) {
                $('#edit_profile_preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        });

    $(document)
        .off('change.employeeIdPreview', '#edit_rrn_image')
        .on('change.employeeIdPreview', '#edit_rrn_image', function () {
            const file = this.files?.[0];
            if (!file) return;

            $('#edit_rrn_image_delete').val('0');
            $('#edit_id_delete_btn').show();
            $('#id_box').attr('data-label', '원본 보기');

            const ext = file.name.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#edit_id_preview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            } else {
                $('#edit_id_preview').attr('src', '/public/assets/img/placeholder-id.png');
            }
        });

    $(document)
        .off('change.employeeRepresentativeQualificationPreview', '#edit_representative_qualification_file')
        .on('change.employeeRepresentativeQualificationPreview', '#edit_representative_qualification_file', function () {
            const file = this.files?.[0];
            if (!file) return;
            $('#edit_representative_qualification_delete').val('0');
            $('#edit_representative_qualification_delete_btn').show();
            $('#representative_qualification_box').attr('data-label', '원본 보기');
            const ext = file.name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                const reader = new FileReader();
                reader.onload = (e) => $('#edit_representative_qualification_preview').attr('src', e.target.result).attr('data-file-path', '');
                reader.readAsDataURL(file);
            } else {
                $('#edit_representative_qualification_preview').attr('src', '/public/assets/img/has-cert.png').attr('data-file-path', '');
            }
        });

    $(document)
        .off('click.bankPreview')
        .on('click.bankPreview', '#edit_bank_preview', function () {
            const filePath = $(this).data('file-path');

            if (!filePath) {
                $('#edit_bank_file').trigger('click');
                return;
            }

            const url = `/api/file/preview?path=${encodeURIComponent(filePath)}`;
            window.open(url, '_blank');
        });

    $(document)
        .off('click.employeeBankDelete')
        .on('click.employeeBankDelete', '#edit_bank_delete_btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('#edit_bank_preview')
                .attr('src', '/public/assets/img/placeholder-bank.png')
                .data('file-path', '');

            const $input = $('#edit_bank_file');
            const $newInput = $input.clone().val('');
            $input.replaceWith($newInput);

            $('#edit_bank_file_delete').val('1');
            $('#bank_box').attr('data-label', '업로드');
            $(this).hide();
        });

    $(document)
        .off('change.employeeBankPreview', '#edit_bank_file')
        .on('change.employeeBankPreview', '#edit_bank_file', function () {
            const file = this.files?.[0];
            if (!file) return;

            $('#edit_bank_file_delete').val('0');
            $('#edit_bank_delete_btn').show();
            $('#bank_box').attr('data-label', '원본 보기');

            const ext = file.name.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#edit_bank_preview')
                        .attr('src', e.target.result)
                        .data('file-path', '');
                };
                reader.readAsDataURL(file);
            } else {
                $('#edit_bank_preview')
                    .attr('src', '/public/assets/img/has-bank-file.png')
                    .data('file-path', '');
            }
        });
}

export function resolveFileSrc(path, fallback = '') {
    if (!path) return fallback;

    if (typeof path === 'string' && (path.startsWith('private://') || path.startsWith('public://'))) {
        return `/api/file/preview?path=${encodeURIComponent(path)}`;
    }

    if (typeof path === 'string' && (path.startsWith('http') || path.startsWith('/'))) {
        return path;
    }

    return fallback;
}

export async function renderRepresentativeQualification(row = {}) {
    const qualificationCount = Math.max(0, Number.parseInt(row.qualification_count, 10) || 0);
    const educationCount = Math.max(0, Number.parseInt(row.education_count, 10) || 0);
    const select = document.getElementById('edit_representative_qualification_id');
    if (select) {
        select.innerHTML = '<option value="">대표자격 없음</option>';
        if (row.id) {
            try {
                const response = await fetch(`/api/settings/organization/employee/representative-qualifications?employee_id=${encodeURIComponent(row.id)}`);
                const payload = await response.json();
                (payload.data || []).forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = `${item.qualification_name}${item.credential_number ? ` (${item.credential_number})` : ''}${item.display_status_code === 'EXPIRING' ? ' · 만료예정' : ''}`;
                    select.appendChild(option);
                });
            } catch (_) {
                // 조회 실패 시 기존 선택을 유효 후보처럼 오인하지 않는다.
            }
        }
        select.value = String(row.representative_qualification_id || '');
        if (window.jQuery?.fn?.select2) $(select).trigger('change.select2');
    }
    $('#edit_qualification_education_status').text(`자격 ${qualificationCount}건 · 교육 ${educationCount}건`);
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
