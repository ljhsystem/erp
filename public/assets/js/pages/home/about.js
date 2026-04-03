//📂 경로: /assets/js/pages/home/about.js

// 📌 현재 연도 기준으로 연도 버튼 2개에 텍스트 및 필터 값 할당
function setYearButtons() {
    const currentYear = new Date().getFullYear(); // 현재 연도 가져오기
    const yearButtons = [1, 2];
    yearButtons.forEach((i) => {
        const yearButton = document.getElementById(`yearButton${i}`);
        const year = currentYear - (2 - i); // 버튼1 = 현재-1, 버튼2 = 현재
        if (yearButton) {
            yearButton.textContent = year;
            yearButton.dataset.filter = year;
        }
    });
}

// 📌 특정 연도, all, before 필터를 적용하여 이미지들을 필터링
function filterItems(filterValue) {
    const items = document.querySelectorAll('.js-masonry-elm');
    const currentYear = new Date().getFullYear();
    const yearLimit = currentYear - 1; // 'before' 필터 기준 (현재-1보다 작아야)

    items.forEach(item => {
        const itemYear = parseInt(item.getAttribute('data-year'));

        let visible = false;

        if (filterValue === 'all') {
            visible = true;
        } else if (filterValue === 'before') {
            visible = itemYear < yearLimit;
        } else {
            visible = itemYear === parseInt(filterValue);
        }

        if (visible) {
            item.style.display = 'block';
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'scale(1)';
            }, 20);
        } else {
            item.style.opacity = '0';
            item.style.transform = 'scale(0.5)';
            setTimeout(() => {
                item.style.display = 'none';
            }, 200);
        }
    });

    // 📌 버튼에 active 클래스 부여
    document.querySelectorAll('.filter-button').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeButton = document.querySelector(`.filter-button[data-filter="${filterValue}"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }
}

// 📌 이미지 클릭 시 팝업으로 확대 표시하는 기능 연결
function setupImagePopup() {
    const images = document.querySelectorAll('.js-masonry-elm .img');
    const popup = document.getElementById('popup');
    const popupImg = document.getElementById('popup-img');
    const popupText = document.getElementById('popup-text');
    const popupClose = document.querySelector('.popup-close');

    images.forEach(image => {
        image.addEventListener('click', function () {
            const imgSrc = this.dataset.src;
            const imgAlt = this.dataset.alt || '';
            popupImg.src = imgSrc;
            popupText.textContent = imgAlt;
            popup.style.display = 'block';
        });
    });

    [popup, popupClose, popupImg].forEach(el => {
        el.addEventListener('click', () => {
            popup.style.display = 'none';
        });
    });
}


// 📌 페이지 진입 시 실행되는 초기화 함수
function initGallery() {
    setYearButtons();         // 연도 버튼 세팅
    setupImagePopup();        // 이미지 팝업 설정

    // 필터 버튼 클릭 이벤트 연결
    document.querySelectorAll('.filter-button').forEach(button => {
        button.addEventListener('click', function () {
            const filterValue = this.dataset.filter;
            filterItems(filterValue); // 해당 필터로 이미지 필터링
        });
    });

    // ✅ 초기 진입 시 현재 연도 필터 자동 적용
    //onst currentYear = new Date().getFullYear();
    //filterItems(currentYear);

    // ✅ 초기 진입 시 전체(all) 필터 적용
    filterItems('all');

    // 로딩 화면 숨기고 실제 콘텐츠 보여주기
    document.getElementById('loading').style.display = 'none';
    document.getElementById('content').style.display = 'block';
}

// 📌 DOM이 모두 준비되었을 때 초기화 실행
window.addEventListener('DOMContentLoaded', initGallery);