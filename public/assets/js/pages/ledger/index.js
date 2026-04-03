// 📄 /assets/js/pages/ledger/index.js

document.addEventListener('DOMContentLoaded', function () {
    function tryGetCtx(id) {
        var el = document.getElementById(id);
        return el ? el.getContext('2d') : null;
    }

    if (typeof Chart === 'undefined') return;

    var salesCtx = tryGetCtx('ledgerSalesChart');
    var profitCtx = tryGetCtx('ledgerProfitChart');

    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['3월', '4월', '5월', '6월', '7월'],
                datasets: [{
                    label: '매출액 (₩)',
                    data: [3200000, 4500000, 3900000, 5100000, 4800000],
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
    }

    if (profitCtx) {
        new Chart(profitCtx, {
            type: 'bar',
            data: {
                labels: ['3월', '4월', '5월', '6월', '7월'],
                datasets: [
                    { label: '수익 (백만원)', data: [5.2, 6.0, 4.8, 7.1, 6.4], backgroundColor: 'rgba(25,135,84,.6)' },
                    { label: '지출 (백만원)', data: [4.1, 4.8, 5.0, 6.2, 5.5], backgroundColor: 'rgba(220,53,69,.5)' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: 'rgba(0,0,0,0.05)' } }
                }
            }
        });
    }
});

window.addEventListener('pageshow', function (event) {
    if (event.persisted) location.reload();
});