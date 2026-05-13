<?php
require_once '../config/db.php';
require_once '../includes/header.php';

// visits per month last 6 months
$monthly_visits = $pdo->query("
    SELECT
        DATE_FORMAT(visit_date, '%b %Y')  AS month_label,
        COUNT(*)                          AS visit_count,
        SUM(consultation_fee + lab_fee)   AS revenue
    FROM visits
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY MIN(visit_date) ASC
")->fetchAll();

// follow-up status breakdown
$followup_status = $pdo->query("
    SELECT
        SUM(CASE WHEN follow_up_due < CURDATE() THEN 1 ELSE 0 END)                         AS overdue,
        SUM(CASE WHEN follow_up_due BETWEEN CURDATE()
             AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END)                    AS upcoming,
        SUM(CASE WHEN follow_up_due > DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS done
    FROM visits
")->fetch();

// patient growth by join month
$patient_growth = $pdo->query("
    SELECT
        DATE_FORMAT(join_date, '%b %Y')  AS month_label,
        COUNT(*)                         AS joined_count
    FROM patients
    GROUP BY DATE_FORMAT(join_date, '%Y-%m')
    ORDER BY MIN(join_date) ASC
")->fetchAll();

// top patients by visits
$top_patients = $pdo->query("
    SELECT
        p.name,
        COUNT(v.visit_id)              AS visit_count,
        SUM(v.consultation_fee + v.lab_fee) AS total_fees
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    GROUP BY p.patient_id
    ORDER BY visit_count DESC
    LIMIT 6
")->fetchAll();

// age group distribution
$age_groups = $pdo->query("
    SELECT
        CASE
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 18  THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 30  THEN '18 - 29'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 45  THEN '30 - 44'
            WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 60  THEN '45 - 59'
            ELSE '60+'
        END AS age_group,
        COUNT(*) AS patient_count
    FROM patients
    GROUP BY age_group
    ORDER BY MIN(TIMESTAMPDIFF(YEAR, dob, CURDATE())) ASC
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-bar-chart-line me-2"></i>Charts & Analytics</h2>
</div>

<!-- Row 1: Visits + Revenue -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Visits & Revenue — Last 6 Months
            </div>
            <div class="card-body">
                <canvas id="visitsRevenueChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Follow-up Status
            </div>
            <div class="card-body">
                <canvas id="followupChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Patient Growth + Age Groups -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people me-2"></i>Patient Growth by Month
            </div>
            <div class="card-body">
                <canvas id="growthChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-diagram-2 me-2"></i>Patients by Age Group
            </div>
            <div class="card-body">
                <canvas id="ageChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Top Patients -->
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-trophy me-2"></i>Top Patients by Visit Count
            </div>
            <div class="card-body">
                <canvas id="topPatientsChart" height="60"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── 1. Visits & Revenue
new Chart(document.getElementById('visitsRevenueChart'), {
    data: {
        labels: <?= json_encode(array_column($monthly_visits, 'month_label')) ?>,
        datasets: [
            {
                type: 'bar',
                label: 'Visits',
                data: <?= json_encode(array_column($monthly_visits, 'visit_count')) ?>,
                backgroundColor: 'rgba(79,142,247,0.75)',
                borderRadius: 6,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Revenue (₹)',
                data: <?= json_encode(array_column($monthly_visits, 'revenue')) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y:  { beginAtZero: true, title: { display: true, text: 'Visits' } },
            y1: { beginAtZero: true, position: 'right',
                  title: { display: true, text: 'Revenue (₹)' },
                  grid: { drawOnChartArea: false } }
        }
    }
});

// ── 2. Follow-up Doughnut
new Chart(document.getElementById('followupChart'), {
    type: 'doughnut',
    data: {
        labels: ['Overdue', 'Upcoming', 'Done'],
        datasets: [{
            data: [
                <?= $followup_status['overdue'] ?>,
                <?= $followup_status['upcoming'] ?>,
                <?= $followup_status['done'] ?>
            ],
            backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// ── 3. Patient Growth Line
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($patient_growth, 'month_label')) ?>,
        datasets: [{
            label: 'Patients Joined',
            data: <?= json_encode(array_column($patient_growth, 'joined_count')) ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139,92,246,0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// ── 4. Age Group Bar
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($age_groups, 'age_group')) ?>,
        datasets: [{
            label: 'Patients',
            data: <?= json_encode(array_column($age_groups, 'patient_count')) ?>,
            backgroundColor: [
                'rgba(79,142,247,0.8)',
                'rgba(16,185,129,0.8)',
                'rgba(245,158,11,0.8)',
                'rgba(139,92,246,0.8)',
                'rgba(239,68,68,0.8)'
            ],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// ── 5. Top Patients Horizontal Bar
new Chart(document.getElementById('topPatientsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($top_patients, 'name')) ?>,
        datasets: [{
            label: 'Visits',
            data: <?= json_encode(array_column($top_patients, 'visit_count')) ?>,
            backgroundColor: 'rgba(79,142,247,0.75)',
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>