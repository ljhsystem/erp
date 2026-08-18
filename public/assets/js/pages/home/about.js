function setYearButtons() {
    const currentYear = new Date().getFullYear();
    const yearButtons = [1, 2];
    yearButtons.forEach((i) => {
        const yearButton = document.getElementById(`yearButton${i}`);
        const year = currentYear - (2 - i);
        if (yearButton) {
            yearButton.textContent = year;
            yearButton.dataset.filter = year;
        }
    });
}
function filterItems(filterValue) {
    const items = document.querySelectorAll('.js-masonry-elm');
    const currentYear = new Date().getFullYear();
    const yearLimit = currentYear - 1;

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

    document.querySelectorAll('.filter-button').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeButton = document.querySelector(`.filter-button[data-filter="${filterValue}"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }
}

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

function initGallery() {
    setYearButtons();
    setupImagePopup();

    document.querySelectorAll('.filter-button').forEach(button => {
        button.addEventListener('click', function () {
            const filterValue = this.dataset.filter;
            filterItems(filterValue);
        });
    });

    filterItems('all');

    document.getElementById('loading').style.display = 'none';
    document.getElementById('content').style.display = 'block';
}

window.addEventListener('DOMContentLoaded', initGallery);
