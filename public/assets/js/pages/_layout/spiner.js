// 📄 /assets/js/pages/_layout/spiner.js
window.addEventListener('DOMContentLoaded', () => {
    const loading = document.getElementById('loading');
    const content = document.getElementById('content');

    // 페이지 로딩 완료 시 스피너 제거 → 내용 표시
    if (loading && content) {
        setTimeout(() => {
            loading.style.display = 'none';
            content.style.display = 'block';
        }, 500); // 약간의 fade 느낌
    }
});
