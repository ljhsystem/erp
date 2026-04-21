<?php
// 📄 경로: /app/views/sukhyang/stats.php
use Core\Helpers\AssetHelper;

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/sukhyang/stats.css') ?>
<?= AssetHelper::js('https://cdn.jsdelivr.net/npm/chart.js') ?>

<main class="sukhyang-stats-main">
    <h5 class="page-title">📊 문서 통계</h5>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-bold">월별 문서 등록 수</h6>
                    <canvas id="docMonthlyChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-bold">문서 유형별 비율</h6>
                    <canvas id="docTypeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    const ctx1 = document.getElementById('docMonthlyChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: ['1월', '2월', '3월', '4월', '5월', '6월', '7월'],
            datasets: [{
                label: '등록 문서 수',
                data: [12, 15, 18, 22, 16, 25, 28],
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const ctx2 = document.getElementById('docTypeChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['출장보고서', '계약서', '회의록', '기타'],
            datasets: [{
                data: [35, 25, 20, 20],
                backgroundColor: ['#198754', '#ffc107', '#0d6efd', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
