const PANEL_ID = 'dt-delete-progress-panel';
const STYLE_ID = 'dt-delete-progress-style';

function ensureDeleteProgressPanel() {
    let panel = document.getElementById(PANEL_ID);
    if (panel) return panel;

    panel = document.createElement('div');
    panel.id = PANEL_ID;
    panel.className = 'dt-delete-progress-panel';
    panel.innerHTML = `
        <div class="dt-delete-progress-card" role="status" aria-live="polite">
            <div class="dt-delete-progress-head">
                <strong data-dt-delete-title>삭제 처리 중</strong>
                <span data-dt-delete-percent>0%</span>
            </div>
            <div class="dt-delete-progress-bar" aria-hidden="true">
                <span data-dt-delete-bar></span>
            </div>
            <div class="dt-delete-progress-meta">
                <span data-dt-delete-count>0 / 0건</span>
                <span data-dt-delete-step>준비 중</span>
            </div>
        </div>
    `;
    document.body.appendChild(panel);

    if (!document.getElementById(STYLE_ID)) {
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = `
            .dt-delete-progress-panel { position: fixed; inset: 0; z-index: 100000; display: none; align-items: center; justify-content: center; padding: 24px; background: rgba(15, 23, 42, 0.28); }
            .dt-delete-progress-panel.is-active { display: flex; }
            .dt-delete-progress-card { width: min(420px, 100%); border: 1px solid #d9e2ef; border-radius: 8px; background: #fff; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22); padding: 18px 20px; color: #111827; }
            .dt-delete-progress-head, .dt-delete-progress-meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .dt-delete-progress-head strong { font-size: 16px; font-weight: 700; }
            .dt-delete-progress-head span { font-size: 18px; font-weight: 700; color: #2563eb; }
            .dt-delete-progress-bar { height: 10px; margin: 14px 0 10px; overflow: hidden; border-radius: 999px; background: #e5e7eb; }
            .dt-delete-progress-bar span { display: block; width: 0%; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2563eb, #10b981); transition: width 160ms ease; }
            .dt-delete-progress-meta { font-size: 13px; color: #4b5563; }
        `;
        document.head.appendChild(style);
    }

    return panel;
}

export function updateDeleteProgress({
    total = 0,
    processed = 0,
    title = '삭제 처리 중',
    step = '처리 중',
} = {}) {
    const panel = ensureDeleteProgressPanel();
    const safeTotal = Math.max(1, Number(total) || 1);
    const safeProcessed = Math.min(safeTotal, Math.max(0, Number(processed) || 0));
    const percent = Math.round((safeProcessed / safeTotal) * 100);

    panel.classList.add('is-active');
    panel.querySelector('[data-dt-delete-title]').textContent = title;
    panel.querySelector('[data-dt-delete-percent]').textContent = `${percent}%`;
    panel.querySelector('[data-dt-delete-count]').textContent = `${safeProcessed.toLocaleString('ko-KR')} / ${safeTotal.toLocaleString('ko-KR')}건`;
    panel.querySelector('[data-dt-delete-step]').textContent = step;
    panel.querySelector('[data-dt-delete-bar]').style.width = `${percent}%`;
}

export function hideDeleteProgress() {
    document.getElementById(PANEL_ID)?.classList.remove('is-active');
}

export async function runDeleteProgress({
    total = 1,
    title = '삭제 처리 중',
    step = '삭제 요청 처리 중',
    trashChanged = false,
} = {}, callback) {
    updateDeleteProgress({ total, processed: 0, title, step });
    try {
        const result = await callback();
        updateDeleteProgress({ total, processed: total, title, step: '삭제 처리 완료' });
        if (trashChanged) {
            markTrashButtonsHasData(Math.max(1, Number(total) || 1));
            document.dispatchEvent(new CustomEvent('datatable:soft-delete-completed', {
                bubbles: true,
                detail: { total: Math.max(1, Number(total) || 1) },
            }));
        }
        return result;
    } finally {
        hideDeleteProgress();
    }
}
import { markTrashButtonsHasData } from './trash/trash-button-state.js';
