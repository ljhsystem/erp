import { statusBadge as baseStatusBadge } from '/public/assets/js/pages/ledger/shared/display-labels.js';

export function statusBadgeRenderer(status) {
    return typeof baseStatusBadge === 'function'
        ? baseStatusBadge(status)
        : String(status ?? '-');
}

export const statusBadge = statusBadgeRenderer;
