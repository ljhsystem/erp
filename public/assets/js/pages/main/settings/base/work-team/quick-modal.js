import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { openClientQuickCreate } from '/public/assets/js/pages/main/settings/base/client.js';
import { initCodeSelectControls } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { WORK_TEAM_API } from './api.js';
import { createWorkTeamModalModule } from './modal.js';

function ensureWorkTeamQuickModal() {
    return document.getElementById('workTeamQuickModal');
}

function visibleParentModal(exclude = null) {
    return Array.from(document.querySelectorAll('.modal.show')).filter(modal => modal !== exclude).at(-1) || null;
}

function bringModalToFront(modalElement) {
    if (!modalElement) return;
    const siblingZIndexes = Array.from(document.querySelectorAll('.modal.show'))
        .filter(modal => modal !== modalElement)
        .map(modal => Number.parseInt(window.getComputedStyle(modal).zIndex, 10) || 0);
    const modalZIndex = Math.max(20000, ...siblingZIndexes.map(value => value + 20));
    const apply = () => {
        modalElement.style.setProperty('z-index', String(modalZIndex), 'important');
        modalElement.querySelector('.modal-dialog')?.style.setProperty('z-index', String(modalZIndex + 1), 'important');
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops[backdrops.length - 1]?.style.setProperty('z-index', String(modalZIndex - 10), 'important');
    };
    apply();
    [0, 16, 50, 120].forEach(delay => window.setTimeout(apply, delay));
}

function restoreParentModal(parentModal, scrollTop) {
    if (!parentModal?.classList.contains('show')) return;
    document.body.classList.add('modal-open');
    const scrollContainer = parentModal.querySelector('.modal-body');
    if (scrollContainer) window.requestAnimationFrame(() => { scrollContainer.scrollTop = scrollTop; });
}

export function createWorkTeamQuickModal({ api, notify, openDetail, onSuccess }) {
    const modalElement = ensureWorkTeamQuickModal();
    const form = document.getElementById('workTeamQuickForm');
    const message = form?.querySelector('[data-role="quick-message"]');
    const detailButton = form?.querySelector('[data-role="quick-detail"]');
    const submitButton = form?.querySelector('[data-role="quick-submit"]');
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement, { focus: false }) : null;
    let bound = false;
    let parentModal = null;
    let parentScrollTop = 0;
    if (detailButton) detailButton.hidden = typeof openDetail !== 'function';

    const values = () => Object.fromEntries(new FormData(form).entries());

    function bind() {
        if (!form || bound) return;
        bound = true;
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (message) message.textContent = '';
            if (submitButton) submitButton.disabled = true;
            if (detailButton) detailButton.disabled = true;
            try {
                const result = await window.AppAjax.fetchJson(api.SAVE, { method: 'POST', body: new FormData(form) });
                onSuccess?.(result, values());
                modal?.hide();
                notify('success', '팀이 등록되었습니다.');
            } catch (error) {
                if (message) message.textContent = error.message || '저장 중 오류가 발생했습니다.';
            } finally {
                if (submitButton) submitButton.disabled = false;
                if (detailButton) detailButton.disabled = false;
            }
        });
        detailButton?.addEventListener('click', () => {
            const initialValues = values();
            if (!modalElement.classList.contains('show')) {
                openDetail?.(initialValues);
                return;
            }
            modalElement.addEventListener('hidden.bs.modal', () => openDetail?.(initialValues), { once: true });
            modal?.hide();
        });
        modalElement?.addEventListener('shown.bs.modal', () => bringModalToFront(modalElement));
        modalElement?.addEventListener('hidden.bs.modal', () => {
            if (message) message.textContent = '';
            modalElement.style.removeProperty('z-index');
            modalElement.querySelector('.modal-dialog')?.style.removeProperty('z-index');
            restoreParentModal(parentModal, parentScrollTop);
            parentModal = null;
        });
    }

    async function open(initialValues = {}) {
        if (!form || !modal) {
            notify('error', '팀 빠른 등록 모달을 찾을 수 없습니다.');
            return;
        }
        bind();
        form.reset();
        parentModal = visibleParentModal(modalElement);
        parentScrollTop = Number(parentModal?.querySelector('.modal-body')?.scrollTop || 0);
        await initCodeSelectControls(modalElement);
        Object.entries(initialValues).forEach(([key, value]) => {
            const input = form.elements.namedItem(key);
            if (input) input.value = value ?? '';
        });
        modal.show();
        bringModalToFront(modalElement);
    }

    return { open };
}

let standaloneOptions = {};
let standaloneModule = null;
let standaloneDetailModule = null;
let standaloneDetailParent = null;
let standaloneDetailParentScrollTop = 0;

function notifyStandalone(type, message) {
    const notify = standaloneOptions.notify || ((nextType, nextMessage) => window.AppCore?.notify?.(nextType, nextMessage));
    notify(type, message);
}

function openOriginalWorkTeamDetail(initialValues = {}) {
    const detailModal = document.getElementById('workTeamModal');
    standaloneDetailParent = visibleParentModal(detailModal);
    standaloneDetailParentScrollTop = Number(standaloneDetailParent?.querySelector('.modal-body')?.scrollTop || 0);
    standaloneDetailModule ||= createWorkTeamModalModule({
        AdminPicker,
        api: WORK_TEAM_API,
        notify: notifyStandalone,
        openClientQuickCreate: (defaultName = '') => {
            void openClientQuickCreate({
                select: document.getElementById('modal-work-team-team-leader-client-id'),
                initialValues: { client_name: defaultName },
                getOptionText: (values) => values.client_name || '',
            });
        },
        reloadTable() {},
    });
    standaloneDetailModule.initModal();
    standaloneDetailModule.initAdminDatePicker();
    standaloneDetailModule.bindModalEvents();
    standaloneDetailModule.bindAdminDateInputs();
    standaloneDetailModule.bindDateIconPicker();
    if (detailModal && detailModal.dataset.nestedStackBound !== 'true') {
        detailModal.dataset.nestedStackBound = 'true';
        detailModal.addEventListener('shown.bs.modal', () => bringModalToFront(detailModal));
        detailModal.addEventListener('hidden.bs.modal', () => {
            detailModal.style.removeProperty('z-index');
            detailModal.querySelector('.modal-dialog')?.style.removeProperty('z-index');
            restoreParentModal(standaloneDetailParent, standaloneDetailParentScrollTop);
            standaloneDetailParent = null;
        });
    }
    standaloneDetailModule.openCreateModal({
        initialValues,
        onSaved: (result, values) => standaloneOptions.onSuccess?.(result, values),
    });
    bringModalToFront(detailModal);
}

export function openWorkTeamQuickCreate(options = {}) {
    standaloneOptions = options;
    standaloneModule ||= createWorkTeamQuickModal({
        api: { SAVE: '/api/settings/base-info/work-team/save' },
        notify: notifyStandalone,
        openDetail: (values) => {
            if (typeof standaloneOptions.openDetail === 'function') {
                standaloneOptions.openDetail(values);
                return;
            }
            openOriginalWorkTeamDetail(values);
        },
        onSuccess: (result, values) => standaloneOptions.onSuccess?.(result, values),
    });
    const detailButton = document.querySelector('#workTeamQuickModal [data-role="quick-detail"]');
    if (detailButton) detailButton.hidden = false;
    return standaloneModule.open(options.initialValues || {});
}
