function createListenerStore() {
    return new Map();
}

function normalizeEventName(eventName) {
    return String(eventName || '').trim();
}

export function createGridEventBus() {
    const listeners = createListenerStore();

    function on(eventName, handler) {
        const normalizedEventName = normalizeEventName(eventName);
        if (normalizedEventName === '' || typeof handler !== 'function') {
            return () => {};
        }

        const handlers = listeners.get(normalizedEventName) || new Set();
        handlers.add(handler);
        listeners.set(normalizedEventName, handlers);

        return () => off(normalizedEventName, handler);
    }

    function off(eventName, handler) {
        const normalizedEventName = normalizeEventName(eventName);
        const handlers = listeners.get(normalizedEventName);
        if (!handlers) {
            return false;
        }

        if (typeof handler === 'function') {
            handlers.delete(handler);
        } else {
            handlers.clear();
        }

        if (handlers.size === 0) {
            listeners.delete(normalizedEventName);
        }

        return true;
    }

    function emit(eventName, payload = {}) {
        const normalizedEventName = normalizeEventName(eventName);
        if (normalizedEventName === '') {
            return [];
        }

        const handlers = listeners.get(normalizedEventName);
        if (!handlers || handlers.size === 0) {
            return [];
        }

        const results = [];
        handlers.forEach((handler) => {
            results.push(handler(payload, normalizedEventName));
        });
        return results;
    }

    function once(eventName, handler) {
        if (typeof handler !== 'function') {
            return () => {};
        }

        let dispose = () => {};
        const onceHandler = (payload, normalizedEventName) => {
            dispose();
            return handler(payload, normalizedEventName);
        };
        dispose = on(eventName, onceHandler);
        return dispose;
    }

    function clear(eventName = '') {
        const normalizedEventName = normalizeEventName(eventName);
        if (normalizedEventName === '') {
            listeners.clear();
            return;
        }

        listeners.delete(normalizedEventName);
    }

    function listenerCount(eventName = '') {
        const normalizedEventName = normalizeEventName(eventName);
        if (normalizedEventName === '') {
            return Array.from(listeners.values()).reduce((count, handlers) => count + handlers.size, 0);
        }

        return listeners.get(normalizedEventName)?.size || 0;
    }

    return {
        on,
        off,
        emit,
        once,
        clear,
        listenerCount,
    };
}
