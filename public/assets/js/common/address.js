// 경로: /assets/js/common/address.js
(function () {

    "use strict";

    window.KakaoAddress = {

        layer: null,
        postcode: null,

        /* ===============================
           주소검색 열기
        =============================== */

        open(options){

            /* --------------------------------
               🔥 하위호환 (string 지원)
            -------------------------------- */
            if(typeof options === "string"){
                options = { address: options };
            }

            const addressEl = document.querySelector(options.address);

            const sidoEl = options.sido
                ? document.querySelector(options.sido)
                : null;

            const sigunguEl = options.sigungu
                ? document.querySelector(options.sigungu)
                : null;

            const detailEl = options.detail
                ? document.querySelector(options.detail)
                : null;

            const closeHandler = () => {
                this.close();
            };
            
            this._escHandler = closeHandler;
            
            window.ESCStack.push(closeHandler);

            if(!addressEl){
                alert("주소 입력 필드를 찾을 수 없습니다.");
                return;
            }

            if(!window.daum || !window.daum.Postcode){
                alert("카카오 주소검색 스크립트가 없습니다.");
                return;
            }

            /* 기존 레이어 제거 */
            if(this.layer){
                this.close();
            }

            /* ===============================
               레이어 생성
            =============================== */

            const overlay = document.createElement("div");

            overlay.style.position = "fixed";
            overlay.style.left = "0";
            overlay.style.top = "0";
            overlay.style.width = "100%";
            overlay.style.height = "100%";
            overlay.style.background = "rgba(0,0,0,0.2)";
            overlay.style.zIndex = "99998";

            document.body.appendChild(overlay);

            const layer = document.createElement("div");

            layer.style.position = "fixed";
            layer.style.width = "500px";
            layer.style.height = "600px";
            layer.style.left = "50%";
            layer.style.top = "50%";
            layer.style.transform = "translate(-50%, -50%)";
            layer.style.border = "1px solid #ccc";
            layer.style.background = "#fff";
            layer.style.zIndex = "99999";
            layer.style.boxShadow = "0 10px 30px rgba(0,0,0,.3)";

            overlay.appendChild(layer);

            this.layer = overlay;

            const self = this;

            /* ===============================
               카카오 주소 API
            =============================== */

            this.postcode = new daum.Postcode({

                oncomplete(data){

                    let addr = data.address;

                    if(data.buildingName){
                        addr += " " + data.buildingName;
                    }

                    /* ------------------------------
                       주소 (필수)
                    ------------------------------ */
                    addressEl.value = addr;

                    /* ------------------------------
                       시도 (옵션)
                    ------------------------------ */
                    if(sidoEl){
                        sidoEl.value = data.sido || '';
                    }

                    /* ------------------------------
                       시군구 (옵션)
                    ------------------------------ */
                    if(sigunguEl){
                        sigunguEl.value = data.sigungu || '';
                    }

                    /* ------------------------------
                       상세주소 focus (옵션)
                    ------------------------------ */
                    if(detailEl){
                        setTimeout(() => detailEl.focus(), 100);
                    }

                    /* 이벤트 트리거 */
                    addressEl.dispatchEvent(new Event("change"));

                    self.close();
                }

            });

            this.postcode.embed(layer);

 

            /* 외부 클릭 닫기 */
            overlay.addEventListener("mousedown", (e) => {
                if(e.target === overlay){
                    self.close();
                }
            });

        },

        /* ===============================
           ESC 핸들러
        =============================== */

        escHandler: (e) => {
            if(e.key === "Escape"){
                window.KakaoAddress.close();
            }
        },

        /* ===============================
           닫기
        =============================== */

        close(){

            if(this.postcode){
                this.postcode = null;
            }
        
            if(this.layer){
                this.layer.remove();
                this.layer = null;
            }
        
            /* 🔥 추가 */
            if(this._escHandler){
                window.ESCStack.remove(this._escHandler);
                this._escHandler = null;
            }
        },

        /* ===============================
           자동 바인딩
        =============================== */

        bind(){

            if(!window.jQuery){
                console.warn("jQuery not loaded (KakaoAddress.bind)");
                return;
            }

            $(document).on("click", "[data-addr-picker]", function(){

                const opts = {
                    address: this.dataset.address || this.dataset.target,
                    sido: this.dataset.sido || null,
                    sigungu: this.dataset.sigungu || null,
                    detail: this.dataset.detail || null
                };

                if(!opts.address){
                    console.warn("address target missing");
                    return;
                }

                window.KakaoAddress.open(opts);

            });

        }

    };

})();