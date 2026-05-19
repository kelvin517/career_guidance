<?php
// This file can be included in other pages to display the Holland Code chart
// Usage: require_once 'holland_code_chart.php';
if (!isset($scores)) {
    // If scores not provided, fetch from database
    $userId = $_SESSION['user_id'] ?? 0;
    if ($userId) {
        $result = mysqli_query($conn, "SELECT scores_json FROM riasec_results WHERE user_id = $userId ORDER BY taken_at DESC LIMIT 1");
        $assessment = mysqli_fetch_assoc($result);
        if ($assessment) {
            $scores = json_decode($assessment['scores_json'], true);
        }
    }
}

if (isset($scores)):
?>
<div class="holland-chart-container" style="max-width:500px; margin:0 auto;">
    <canvas id="hollandRadarChart" height="250"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('hollandRadarChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['R', 'I', 'A', 'S', 'E', 'C'],
                    datasets: [{
                        label: 'RIASEC Profile',
                        data: [
                            <?php echo $scores['R'] ?? 0; ?>,
                            <?php echo $scores['I'] ?? 0; ?>,
                            <?php echo $scores['A'] ?? 0; ?>,
                            <?php echo $scores['S'] ?? 0; ?>,
                            <?php echo $scores['E'] ?? 0; ?>,
                            <?php echo $scores['C'] ?? 0; ?>
                        ],
                        backgroundColor: 'rgba(37, 99, 168, 0.2)',
                        borderColor: '#2563a8',
                        borderWidth: 2,
                        pointBackgroundColor: '#2563a8',
                        pointBorderColor: '#fff',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: { r: { beginAtZero: true, max: 50, ticks: { stepSize: 10 } } },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
</script>
<?php endif; ?>