// 📄 /assets/js/pages/dashboard/index.js

document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('salesChart');
    if (!el || typeof Chart === 'undefined') return;

    const ctx = el.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['1월','2월','3월','4월','5월','6월','7월'],
            datasets: [{
                label: '매출액 ($)',
                data: [5200, 6100, 5800, 7200, 6600, 8000, 8700],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    });
});

window.addEventListener('pageshow', function (event) {
    if (event.persisted) location.reload();
});

