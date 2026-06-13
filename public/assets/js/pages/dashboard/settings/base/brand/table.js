export function createBrandTableModule({ api, notify }) {
    function loadExistingFiles() {
        window.jQuery.post(api.LIST, {}, (response) => {
            const tbody = window.jQuery('#existing-files');
            tbody.empty();

            if (!response?.success || !Array.isArray(response.data) || response.data.length === 0) {
                tbody.append('<tr><td colspan="7" class="text-center text-muted">등록된 파일이 없습니다.</td></tr>');
                return;
            }

            response.data.forEach((file) => {
                const previewUrl = escapeHtml(file.url || '/public/assets/img/default-placeholder.png');
                const fileName = escapeHtml(file.file_name || '-');
                const typeLabel = escapeHtml(file.asset_type_label || file.asset_type || '-');
                const createdAt = escapeHtml(file.created_at || '-');
                const createdBy = escapeHtml(file.created_by || '-');
                const activeBadge = Number(file.is_active) === 1
                    ? '<span class="badge bg-success">활성</span>'
                    : '<span class="badge bg-secondary">비활성</span>';
                const activateButton = Number(file.is_active) === 1
                    ? ''
                    : `<button type="button" class="btn btn-sm btn-primary btn-activate-brand" data-id="${escapeAttribute(file.id)}">기본 적용</button>`;

                const row = `
                    <tr>
                        <td><img src="${previewUrl}" alt="${typeLabel}" height="40" style="max-width: 80px;"></td>
                        <td>${typeLabel}</td>
                        <td><a href="${previewUrl}" target="_blank" rel="noopener noreferrer">${fileName}</a></td>
                        <td>${createdAt}</td>
                        <td>${createdBy}</td>
                        <td>${activeBadge}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                ${activateButton}
                                <button type="button" class="btn btn-sm btn-danger btn-delete-brand" data-id="${escapeAttribute(file.id)}">삭제</button>
                            </div>
                        </td>
                    </tr>
                `;

                tbody.append(row);
            });
        }, 'json').fail(() => {
            window.jQuery('#existing-files').html('<tr><td colspan="7" class="text-center text-danger">파일 목록을 불러오지 못했습니다.</td></tr>');
            notify('error', '파일 목록을 불러오지 못했습니다.');
        });
    }

    return {
        loadExistingFiles,
    };
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
}
