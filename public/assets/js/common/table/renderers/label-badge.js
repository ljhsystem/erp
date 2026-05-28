import { labelBadge as baseLabelBadge } from '/public/assets/js/pages/ledger/shared/display-labels.js';

export function labelBadgeRenderer(label) {
    return typeof baseLabelBadge === 'function'
        ? baseLabelBadge(label)
        : `<span class=\"badge text-bg-light border text-dark\">${String(label ?? '-')}</span>`;
}

export const labelBadge = labelBadgeRenderer;
