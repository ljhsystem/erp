let activeDialog = null;

export function confirmDialog({
    title = '확인',
    message = '',
    confirmText = '확인',
    cancelText = '취소',
    confirmClass = 'btn-primary',
} = {}) {
    if (activeDialog) return activeDialog;

    activeDialog = new Promise(resolve => {
        const returnFocus = document.activeElement;
        const element = document.createElement('div');
        element.className = 'modal fade';
        element.tabIndex = -1;
        element.setAttribute('aria-hidden', 'true');
        element.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body"><p class="mb-0 text-pre-wrap"></p></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"></button>
                        <button type="button" class="btn btn-sm"></button>
                    </div>
                </div>
            </div>`;
        element.querySelector('.modal-title').textContent = title;
        const messageElement = element.querySelector('.modal-body p');
        String(message).split('\n').forEach((line, index) => {
            if (index > 0) messageElement.appendChild(document.createElement('br'));
            messageElement.appendChild(document.createTextNode(line));
        });
        const [cancelButton, confirmButton] = element.querySelectorAll('.modal-footer button');
        cancelButton.textContent = cancelText;
        confirmButton.textContent = confirmText;
        confirmButton.classList.add(confirmClass);
        document.body.appendChild(element);

        const instance = new bootstrap.Modal(element, { backdrop: 'static', keyboard: false });
        let confirmed = false;
        confirmButton.addEventListener('click', () => {
            confirmed = true;
            instance.hide();
        });
        element.addEventListener('hidden.bs.modal', () => {
            instance.dispose();
            element.remove();
            if (document.querySelector('.modal.show')) document.body.classList.add('modal-open');
            if (returnFocus instanceof HTMLElement && returnFocus.isConnected) returnFocus.focus();
            activeDialog = null;
            resolve(confirmed);
        }, { once: true });
        instance.show();
    });

    return activeDialog;
}
