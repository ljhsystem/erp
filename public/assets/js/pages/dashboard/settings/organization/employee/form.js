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
        .off('click.certPreview')
        .on('click.certPreview', '#edit_cert_preview_img', function () {
            const filePath = $(this).data('file-path');

            if (!filePath) {
                $('#edit_certificate_file').trigger('click');
                return;
            }

            const url = `/api/file/preview?path=${encodeURIComponent(filePath)}`;
            window.open(url, '_blank');
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
        .off('click.employeeCertDelete')
        .on('click.employeeCertDelete', '#edit_cert_delete_btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('#edit_cert_preview_img')
                .attr('src', '/public/assets/img/placeholder-cert.png')
                .data('file-path', '');

            const $input = $('#edit_certificate_file');
            const $newInput = $input.clone().val('');
            $input.replaceWith($newInput);

            $('#edit_certificate_file_delete').val('1');
            $('#edit_certificate_name').val('');
            $('#cert_box').attr('data-label', '업로드');

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
        .off('change.employeeCertPreview', '#edit_certificate_file')
        .on('change.employeeCertPreview', '#edit_certificate_file', function () {
            const file = this.files?.[0];
            if (!file) return;

            $('#edit_cert_delete_btn').show();
            $('#cert_box').attr('data-label', '원본 보기');

            const ext = file.name.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#edit_cert_preview_img')
                        .attr('src', e.target.result)
                        .data('file-path', '');
                };
                reader.readAsDataURL(file);
            } else {
                $('#edit_cert_preview_img')
                    .attr('src', '/public/assets/img/has-cert.png')
                    .data('file-path', '');
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

export function getCertPreview(filePath) {
    if (!filePath) {
        return '/public/assets/img/placeholder-cert.png';
    }

    const ext = String(filePath).split('.').pop().toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        return `/api/file/preview?path=${encodeURIComponent(filePath)}`;
    }

    return '/public/assets/img/has-cert.png';
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
