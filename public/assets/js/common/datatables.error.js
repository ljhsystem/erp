// 경로: PROJECT_ROOT . '/assets/js/common/datatables.error.js'

// DataTables 에러 기본 동작 끄기 (기본 alert 경고 제거)
$(function () {
    if ($.fn.dataTable && $.fn.dataTable.ext) {
        $.fn.dataTable.ext.errMode = 'none';
    }

    // DataTables Ajax 응답 공통 처리
    $(document).on('xhr.dt', function (e, settings, json, xhr) {

        // 정상 응답이면 처리 안 함
        if (xhr.status >= 200 && xhr.status < 300) return;

        // 403 권한 없음
        if (xhr.status === 403) {
            alert("⛔ 접근 권한이 없습니다.");
            return false; // 추가 동작 막기
        }

        // 401 로그인 필요
        if (xhr.status === 401) {
            alert("로그인이 필요합니다.");
            window.location.href = "/login";
            return false;
        }

        // 기타 서버 오류
        alert("서버 오류가 발생했습니다. (" + xhr.status + ")");
        return false;
    });
});
