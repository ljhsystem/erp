function resolveDocument(target, fallbackDocument = null) {
    if (target?.ownerDocument) {
        return target.ownerDocument;
    }
    if (fallbackDocument) {
        return fallbackDocument;
    }
    if (typeof document !== 'undefined') {
        return document;
    }
    throw new Error('[html-grid] empty-renderer requires document context.');
}

export function createEmptyRenderer(config = {}) {
    const documentRef = config.document || (typeof document !== 'undefined' ? document : null);

    function renderEmpty(target, options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        const container = target.tagName === 'DIV' ? target : resolvedDocument.createElement('div');
        container.className = 'html-grid-empty';
        container.textContent = '';

        let stateClass = 'is-empty';
        let message = options.emptyMessage || '데이터가 없습니다.';

        if (options.loading) {
            stateClass = 'is-loading';
            message = options.loadingMessage || '로딩 중입니다.';
        } else if (options.error) {
            stateClass = 'is-error';
            message = options.errorMessage || '오류가 발생했습니다.';
        } else if (options.noData) {
            stateClass = 'is-no-data';
            message = options.noDataMessage || '조회된 데이터가 없습니다.';
        }

        container.classList.add(stateClass);

        const messageEl = resolvedDocument.createElement('div');
        messageEl.className = 'html-grid-empty-message';
        messageEl.textContent = message;
        container.appendChild(messageEl);

        return container;
    }

    return {
        renderEmpty,
    };
}
