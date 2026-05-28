(() => {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};
    const AppEvents = window.AppEvents || {};

    const onDocument = typeof AppEvents.onDocument === 'function'
        ? AppEvents.onDocument
        : (type, handler, options = false) => {
            document.addEventListener(type, handler, options);
            return () => document.removeEventListener(type, handler, options);
        };

    const onJQDocument = typeof AppEvents.onJQDocument === 'function'
        ? AppEvents.onJQDocument
        : null;

    function notify(type, message) {
        if (AppCore.notify) {
            AppCore.notify(type, message);
            return;
        }
        window.alert?.(message);
    }

    function handleXhrError(_event, _settings, _json, xhr) {
        if (!xhr || !xhr.status) return false;
        if (xhr.status >= 200 && xhr.status < 300) return false;

        if (xhr.status === 403) {
            notify('warning', '권한이 없습니다.');
            return false;
        }

        if (xhr.status === 401) {
            notify('warning', '로그인 세션이 만료되었습니다.');
            window.location.href = '/login';
            return false;
        }

        notify('error', `요청 처리 중 오류가 발생했습니다. (${xhr.status})`);
        return false;
    }

    function bind() {
        const $ = window.jQuery;
        if (!$.fn?.dataTable || !$.fn.dataTable.ext) {
            return;
        }

        $.fn.dataTable.ext.errMode = 'none';

        if (onJQDocument) {
            onJQDocument('xhr.dt', handleXhrError);
            return;
        }

        if (typeof $.fn?.on === 'function') {
            $(document).on('xhr.dt', handleXhrError);
        }
    }

    if (document.readyState === 'loading') {
        onDocument('DOMContentLoaded', bind, { once: true });
    } else {
        bind();
    }
})();
