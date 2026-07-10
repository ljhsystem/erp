function normalizeName(name) {
    return String(name || '').trim();
}

function normalizePluginNames(value = '') {
    if (Array.isArray(value)) {
        return value.map(normalizeName).filter(Boolean);
    }

    return String(value || '')
        .split(',')
        .map(normalizeName)
        .filter((plugin, index, array) => plugin !== '' && array.indexOf(plugin) === index);
}

function createPluginStore() {
    return new WeakMap();
}

function resolveEditorElement(target) {
    if (target?.__htmlGridEditor?.element) {
        return target.__htmlGridEditor.element;
    }

    return target?.querySelector?.('.html-grid-editor') || null;
}

function collectTargets(root) {
    if (!root?.querySelectorAll) {
        return [];
    }

    return Array.from(root.querySelectorAll('.html-grid-cell-editor-slot'));
}

export function createPluginRegistry() {
    const registry = new Map();
    const mounted = createPluginStore();

    function register(name, pluginFactory) {
        const normalizedName = normalizeName(name);
        if (normalizedName === '' || typeof pluginFactory !== 'function') {
            throw new Error('[html-grid] plugin registry requires name and factory.');
        }

        registry.set(normalizedName, pluginFactory);
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

    function entries() {
        return Array.from(registry.entries());
    }

    function ensureMountedPlugins(target) {
        const current = mounted.get(target);
        if (current) {
            return current;
        }

        const next = new Map();
        mounted.set(target, next);
        return next;
    }

    function buildContext(target, sharedContext = {}, pluginName = '') {
        return {
            ...sharedContext,
            pluginName,
            target,
            editor: target?.__htmlGridEditor || null,
            editorElement: resolveEditorElement(target),
            rowId: String(target?.dataset?.rowId || ''),
            columnKey: String(target?.dataset?.columnKey || ''),
        };
    }

    function mount(root, sharedContext = {}) {
        const targets = collectTargets(root);

        targets.forEach((target) => {
            const pluginNames = normalizePluginNames(target.dataset?.plugins || '');
            if (pluginNames.length === 0) {
                return;
            }

            const currentPlugins = ensureMountedPlugins(target);
            pluginNames.forEach((pluginName) => {
                if (currentPlugins.has(pluginName)) {
                    return;
                }

                const factory = resolve(pluginName);
                if (!factory) {
                    return;
                }

                const instance = factory(buildContext(target, sharedContext, pluginName)) || null;
                if (!instance) {
                    return;
                }

                instance.init?.(buildContext(target, sharedContext, pluginName));
                instance.mount?.(buildContext(target, sharedContext, pluginName));
                currentPlugins.set(pluginName, instance);
            });
        });

        return targets.length;
    }

    function update(root, sharedContext = {}) {
        const targets = collectTargets(root);
        const liveTargets = new Set(targets);

        targets.forEach((target) => {
            const pluginNames = normalizePluginNames(target.dataset?.plugins || '');
            const currentPlugins = ensureMountedPlugins(target);

            pluginNames.forEach((pluginName) => {
                if (!currentPlugins.has(pluginName)) {
                    const factory = resolve(pluginName);
                    if (!factory) {
                        return;
                    }

                    const instance = factory(buildContext(target, sharedContext, pluginName)) || null;
                    if (!instance) {
                        return;
                    }

                    instance.init?.(buildContext(target, sharedContext, pluginName));
                    instance.mount?.(buildContext(target, sharedContext, pluginName));
                    currentPlugins.set(pluginName, instance);
                    return;
                }

                currentPlugins.get(pluginName)?.update?.(buildContext(target, sharedContext, pluginName));
            });

            Array.from(currentPlugins.keys()).forEach((pluginName) => {
                if (pluginNames.includes(pluginName)) {
                    return;
                }

                currentPlugins.get(pluginName)?.destroy?.(buildContext(target, sharedContext, pluginName));
                currentPlugins.delete(pluginName);
            });
        });

        mountedCleanup(liveTargets);
        return targets.length;
    }

    function mountedCleanup(liveTargets = new Set()) {
        entries().forEach(() => {});
        collectWeakMapTargets(mounted).forEach((target) => {
            if (liveTargets.has(target)) {
                return;
            }

            destroyTarget(target);
        });
    }

    function destroyTarget(target, sharedContext = {}) {
        const currentPlugins = mounted.get(target);
        if (!currentPlugins) {
            return 0;
        }

        currentPlugins.forEach((instance, pluginName) => {
            instance?.destroy?.(buildContext(target, sharedContext, pluginName));
        });
        currentPlugins.clear();
        mounted.delete(target);
        return 1;
    }

    function destroy(root = null, sharedContext = {}) {
        if (root?.matches?.('.html-grid-cell-editor-slot')) {
            return destroyTarget(root, sharedContext);
        }

        const targets = root ? collectTargets(root) : collectWeakMapTargets(mounted);
        targets.forEach((target) => {
            destroyTarget(target, sharedContext);
        });

        return targets.length;
    }

    const api = {
        register,
        unregister,
        has,
        resolve,
        entries,
        mount,
        update,
        destroy,
    };

    return api;
}

function collectWeakMapTargets(store) {
    const targets = [];
    if (!store || typeof store !== 'object') {
        return targets;
    }

    // WeakMap cannot be iterated directly, so registry destroy/update paths pass
    // concrete roots in normal runtime. This fallback keeps the API shape stable.
    return targets;
}
