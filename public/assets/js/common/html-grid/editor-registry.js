function normalizeName(name) {
    return String(name || '').trim();
}

export function createEditorRegistry() {
    const registry = new Map();

    function register(name, editorFactory) {
        const normalizedName = normalizeName(name);
        if (normalizedName === '' || typeof editorFactory !== 'function') {
            throw new Error('[html-grid] editor registry requires name and factory.');
        }
        registry.set(normalizedName, editorFactory);
        return api;
    }

    function unregister(name) {
        registry.delete(normalizeName(name));
        return api;
    }

    function has(name) {
        return registry.has(normalizeName(name));
    }

    function resolve(name) {
        return registry.get(normalizeName(name)) || null;
    }

    function create(context = {}) {
        const name = normalizeName(context.editor || context.column?.editor || '');
        if (name === '') {
            return null;
        }

        const editorFactory = resolve(name);
        if (!editorFactory) {
            throw new Error(`[html-grid] unknown editor "${name}".`);
        }

        return editorFactory(context);
    }

    function clear() {
        registry.clear();
        return api;
    }

    function entries() {
        return Array.from(registry.entries());
    }

    const api = {
        register,
        unregister,
        has,
        resolve,
        create,
        clear,
        entries,
    };

    return api;
}
