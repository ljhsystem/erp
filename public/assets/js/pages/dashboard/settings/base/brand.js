/**
 * 브랜드 관리 JS
 * 경로: /public/assets/js/pages/dashboard/settings/base/brand.js
 */
(function () {
    "use strict";

    console.log('[base-brand.js] loaded');
    
    const API_GET    = "/api/settings/base-info/brand/active-type";
    const API_UPLOAD = "/api/settings/base-info/brand/save";

    const $wrapper = $("#brand-settings-wrapper");

    const ASSETS = {
        main_logo: {
            input: "[name='main_logo']",
            preview: "#preview_main_logo",
            emptyText: "등록된 메인 로고가 없습니다."
        },
        print_logo: {
            input: "[name='print_logo']",
            preview: "#preview_print_logo",
            emptyText: "등록된 인쇄용 로고가 없습니다."
        },
        favicon: {
            input: "[name='favicon']",
            preview: "#preview_favicon",
            emptyText: "등록된 파비콘이 없습니다."
        }
    };

    /** ⭐ 선택된 파일만 임시 저장 */
    const selectedFiles = {
        main_logo: null,
        print_logo: null,
        favicon: null
    };

    $(document).ready(function () {
        loadAll();
        bindEvents();
        loadExistingFiles(); // 기존 파일 목록 로드
    });

    function bindEvents() {

        // 파일 선택 → 미리보기만
        Object.keys(ASSETS).forEach(type => {
            const cfg = ASSETS[type];

            $wrapper.on("change", cfg.input, function () {
                const file = this.files[0];
                if (!file) return;

                previewFile(file, cfg.preview);
                selectedFiles[type] = file;
            });
        });

        // 저장 버튼
        $wrapper.on("click", "#btn-save-brand", function () {
            saveAll();
        });
    }

    function loadAll() {
        Object.keys(ASSETS).forEach(loadAsset);
    }

    function loadAsset(type) {
        const cfg = ASSETS[type];
        const $img = $(cfg.preview);

        $.post(API_GET, { asset_type: type }, function (res) {
            console.log("🔍 API 응답:", res); // 🔥 디버깅 로그 추가
            removeEmptyMessage($img);

            if (!res.success || !res.data || !res.data.url) {
                showEmptyMessage($img, cfg.emptyText);
                return;
            }

            // 🔥 미리보기 이미지 설정
            $img.attr("src", res.data.url).show();
        }, "json").fail(function () {
            console.error("❌ API 호출 실패: /api/settings/base-info/brand/detail");
        });
    }

    function previewFile(file, selector) {
        const reader = new FileReader();
        reader.onload = e => {
            const $img = $(selector);
            removeEmptyMessage($img);
            $img.attr("src", e.target.result).show();
        };
        reader.readAsDataURL(file);
    }

    function saveAll() {
        let hasChange = false;

        Object.keys(selectedFiles).forEach(type => {
            if (selectedFiles[type]) {
                hasChange = true;
                uploadFile(type, selectedFiles[type]);
            }
        });

        if (!hasChange) {
            alert("변경된 파일이 없습니다.");
        }
    }

    function uploadFile(type, file) {
        const formData = new FormData();
        formData.append("asset_type", type);
        formData.append("file", file);

        $.ajax({
            url: API_UPLOAD,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success(res) {
                console.log("📤 파일 업로드 응답:", res); // 🔥 디버깅 로그 추가
                if (!res.success) {
                    alert(res.message || "업로드 실패");
                    return;
                }

                selectedFiles[type] = null;
                loadAsset(type); // 🔥 업로드된 파일 미리보기 갱신
                loadExistingFiles(); // 🔥 기존 파일 목록 갱신
            },
            error() {
                alert("서버 통신 중 오류가 발생했습니다.");
            }
        });
    }

    function showEmptyMessage($img, text) {
        $img.hide();
        if ($img.next(".brand-empty-text").length === 0) {
            $("<div>")
                .addClass("brand-empty-text text-muted mt-1")
                .text(text)
                .insertAfter($img);
        }
    }

    function removeEmptyMessage($img) {
        $img.next(".brand-empty-text").remove();
    }

    function loadExistingFiles() {
        console.log("🔄 기존 파일 목록 로드 시작"); // 🔥 디버깅 로그 추가

        $.post("/api/settings/base-info/brand/list", {}, function (res) {
            console.log("🔍 기존 파일 목록 API 응답:", res); // 🔥 디버깅 로그 추가
            const $tbody = $("#existing-files");
            $tbody.empty();

            if (!res.success || !res.data || res.data.length === 0) {
                $tbody.append('<tr><td colspan="7" class="text-center">등록된 파일이 없습니다.</td></tr>');
                return;
            }

            res.data.forEach(file => {
                const previewUrl = file.url || '/public/assets/img/default-placeholder.png'; // 🔥 파일 URL 또는 기본 이미지
                const row = `
                <tr>
                    <td>
                        <img src="${previewUrl}" height="40" style="max-width:60px;">
                    </td>
                    <td>${file.asset_type}</td>
                    <td>
                        <a href="${previewUrl}" target="_blank">${file.file_name}</a>
                    </td>
                    <td>${file.created_at}</td>
                    <td>${file.created_by_name ?? file.created_by}</td>

                    <!-- 상태 -->
                    <td>
                        ${Number(file.is_active) === 1 
                            ? '<span class="badge bg-success">활성</span>' 
                            : '<span class="badge bg-secondary">비활성</span>'}
                    </td>

                    <!-- 액션 -->
                    <td>
                        ${
                            !file.is_active
                            ? `
                            <button 
                                class="btn btn-sm btn-primary"
                                onclick="activateFile('${file.id}')">
                                활성화
                            </button>
                            `
                            : ''
                        }

                        <button 
                            class="btn btn-sm btn-danger"
                            onclick="deleteFile('${file.id}')">
                            삭제
                        </button>
                    </td>
                </tr>
                `;
                $tbody.append(row);
            });
        }, "json").fail(function () {
            console.error("❌ API 호출 실패: /api/settings/base-info/brand/list");
        });
    }

    window.activateFile = function (fileId) {

        console.log(`🔄 활성화 요청: ${fileId}`);
    
        if (!confirm("이 파일을 활성화하시겠습니까?")) return;
    
        $.post("/api/settings/base-info/brand/updatestatus", {
            id: fileId,
            status: 1
        }, function (res) {
    
            console.log("🔄 활성화 API 응답:", res);
    
            if (res.success) {
    
                loadAll();           // 🔥 미리보기 전체 갱신
                loadExistingFiles(); // 🔥 목록 갱신
    
            } else {
                alert(res.message || "파일 활성화 실패");
            }
    
        }, "json").fail(function (xhr) {
    
            console.error("❌ API 호출 실패: /api/settings/base-info/brand/updatestatus");
            console.error("❌ 상태 코드:", xhr.status);
            console.error("❌ 응답 메시지:", xhr.responseText);
    
        });
    };

    window.deleteFile = function (fileId) {
        console.log(`🗑 삭제 요청: ${fileId}`); // 🔥 디버깅 로그 추가
        if (!confirm("이 파일을 삭제하시겠습니까?")) return;

        $.post("/api/settings/base-info/brand/purge", { file_id: fileId }, function (res) {
            console.log("🗑 삭제 API 응답:", res); // 🔥 디버깅 로그 추가
            if (res.success) {
                alert("파일이 삭제되었습니다.");
                loadExistingFiles();
            } else {
                alert(res.message || "파일 삭제에 실패했습니다.");
            }
        }, "json").fail(function () {
            console.error("❌ API 호출 실패: /api/settings/base-info/brand/purge");
        });
    };

})();
