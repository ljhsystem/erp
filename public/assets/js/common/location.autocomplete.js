// 📄 /public/assets/js/common/location.autocomplete.js

function loadKakaoSdk(callback) {

    // 이미 로딩된 경우
    if (window.kakao && window.kakao.maps) {
      return kakao.maps.load(callback);
    }
  
    const script = document.createElement('script');
    script.src =
      'https://dapi.kakao.com/v2/maps/sdk.js?appkey=3c2d6eb4d55d042e4c5d31c650f5ac85&libraries=services&autoload=false';
  
    script.onload = () => {
      kakao.maps.load(callback);
    };
  
    document.head.appendChild(script);
  }


(function () {
    "use strict";
  
    window.LocationAutocomplete = {
  
        bind(inputSelector) {

            const input = document.querySelector(inputSelector);
            if (!input) return;
          
            if (input.__locBound) return;
            input.__locBound = true;
          
            loadKakaoSdk(() => {
              this._init(input);
            });
          },
  
      _init(input) {
  
        const ps = new kakao.maps.services.Places();
  
        // 🔥 body 포탈 (modal overflow 문제 해결)
        const box = document.createElement("div");
        box.className = "loc-suggest";
        box.hidden = true;
        document.body.appendChild(box);
  
        let timer = null;
        let results = [];
        let activeIndex = -1;
        let lastKeyword = "";
  
        const positionBox = () => {
          const r = input.getBoundingClientRect();
          box.style.position = "fixed";
          box.style.left = `${r.left}px`;
          box.style.top = `${r.bottom + 4}px`;
          box.style.width = `${r.width}px`;
          box.style.zIndex = 999999;
        };
  
        const render = (data) => {
          box.innerHTML = "";
          results = data.slice(0, 7);
          activeIndex = -1;
  
          results.forEach((item, i) => {
  
            const div = document.createElement("div");
            div.className = "loc-item";
  
            div.innerHTML = `
              <strong>${item.place_name}</strong>
              <small>${item.road_address_name || item.address_name}</small>
            `;
  
            div.addEventListener("mousedown", (e) => {
              e.preventDefault();
              select(i);
            });
  
            box.appendChild(div);
          });
  
          positionBox();
          box.hidden = results.length === 0;
        };
  
        const highlight = () => {
          [...box.children].forEach((el, i) => {
            el.classList.toggle("is-active", i === activeIndex);
          });
        };
  
        const select = (i) => {
            const item = results[i];
            if (!item) return;
          
            const title = (item.place_name || '').trim();
            const addr  = (item.road_address_name || item.address_name || '').trim();
          
            // 🔥 표시값: 제목 (주소)
            input.value = addr ? `${title} (${addr})` : title;
          
            // 🔥 ERP 저장용 메타데이터 (중요)
            input.dataset.place_name   = title;
            input.dataset.road_address = item.road_address_name || '';
            input.dataset.address      = item.address_name || '';
            input.dataset.lat          = item.y;
            input.dataset.lng          = item.x;
          
            box.hidden = true;
          };
          
        input.addEventListener("input", () => {
  
          const keyword = input.value.trim();
  
          if (keyword.length < 2) {
            box.hidden = true;
            return;
          }
  
          if (keyword === lastKeyword) return;
          lastKeyword = keyword;
  
          clearTimeout(timer);
  
          timer = setTimeout(() => {
  
            ps.keywordSearch(keyword, (data, status) => {
  
              if (status !== kakao.maps.services.Status.OK) {
                box.hidden = true;
                return;
              }
  
              render(data);
  
            });
  
          }, 120); // 🔥 더 빠르게
  
        });
  
        // 🔥 방향키 지원
        input.addEventListener("keydown", (e) => {
  
          if (box.hidden || !results.length) return;
  
          if (e.key === "ArrowDown") {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % results.length;
            highlight();
          }
  
          if (e.key === "ArrowUp") {
            e.preventDefault();
            activeIndex =
              (activeIndex - 1 + results.length) % results.length;
            highlight();
          }
  
          if (e.key === "Enter") {
            e.preventDefault();
            if (activeIndex >= 0) {
              select(activeIndex);
            }
          }
  
          if (e.key === "Escape") {
            box.hidden = true;
          }
  
        });
  
        // 외부 클릭 닫기
        document.addEventListener("mousedown", (e) => {
          if (!box.contains(e.target) && e.target !== input) {
            box.hidden = true;
          }
        });
  
        // 창 스크롤 시 위치 재계산
        window.addEventListener("scroll", positionBox, true);
        window.addEventListener("resize", positionBox);
      }
    };
  
  })();