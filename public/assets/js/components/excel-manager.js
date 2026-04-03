// 경로: /assets/js/components/excel-manager.js
(() => {

    "use strict";

    console.log("[excel-manager] loaded");

    document.addEventListener("click", async (e) => {

        const btn = e.target.closest("button");
        if (!btn) return;
    
        const modal = btn.closest(".modal");
        if (!modal) return;
    
        const form = modal.querySelector("form");
        if (!form) return;
    
        const fileInput = modal.querySelector("input[type='file']");
        const spinnerWrap = modal.querySelector(".excel-spinner");
    
        /* 양식 다운로드 */
        if (btn.classList.contains("btn-template-download")) {
            window.location.href = form.dataset.templateUrl;
            return;
        }
    
        /* 전체 다운로드 */
        if (btn.classList.contains("btn-download-all")) {
            window.location.href = form.dataset.downloadUrl;
            return;
        }
    
        /* 업로드 */
        if (btn.classList.contains("btn-upload-excel")) {
    
            if (!fileInput.files.length) {
                alert("파일 선택하세요");
                return;
            }
    
            const formData = new FormData(form);
    
            spinnerWrap.style.display = "block";
    
            try {
    
                const res = await fetch(form.dataset.uploadUrl, {
                    method: "POST",
                    body: formData
                });
    
                const json = await res.json();
    
                if (json.success) {
    
                    AppCore.notify("success", "업로드 완료");
    
                    const instance = bootstrap.Modal.getInstance(modal) 
                    || new bootstrap.Modal(modal);
      
                    instance.hide();
    
                    document.dispatchEvent(new Event("excel:uploaded"));
    
                } else {
    
                    AppCore.notify("error", json.message);
    
                }
    
            } catch (err) {
    
                console.error(err);
                alert("업로드 오류");
    
            } finally {
    
                spinnerWrap.style.display = "none";
    
            }
    
        }
    
    });
    document.addEventListener("shown.bs.modal", (e) => {

        const modal = e.target;
        if (!modal.classList.contains("modal")) return;
    
        const fileInput = modal.querySelector("input[type='file']");
        const spinner = modal.querySelector(".excel-spinner");
    
        if (fileInput) fileInput.value = "";
    
        if (spinner) spinner.style.display = "none";
    
    });




})();