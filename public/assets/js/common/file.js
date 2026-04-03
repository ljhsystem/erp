// 경로: PROJECT_ROOT . '/public/assets/js/common/file.js'

// 공통 파일 경로 해석기 (UI 의미 없음)
window.resolveFileSrc = function (path, fallback = "") {
    if (!path || typeof path !== "string") {
        return fallback || "";
    }

    if (path.startsWith("public://") || path.startsWith("private://")) {
        return `/api/file/preview?path=${encodeURIComponent(path)}`;
    }

    if (path.startsWith("http") || path.startsWith("/")) {
        return path;
    }

    return fallback || "";
};
