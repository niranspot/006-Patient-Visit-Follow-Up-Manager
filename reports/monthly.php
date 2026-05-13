<?php
require_once '../config/db.php';
require_once '../includes/header.php';

// ── Visits per month (last 6 months)
$visits_monthly = $pdo->query("
    SELECT
        DATE_FORMAT(visit_date, '%b %Y')             AS month_label,
        DATE_FORMAT(visit_date, '%Y-%m')             AS month_key,
        COUNT(*)                                     AS visit_count,
        SUM(consultation_fee + lab_fee)              AS revenue
    FROM visits
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY MIN(visit_date) ASC
")->fetchAll();

// ── Patients joined per month
$joined_monthly = $pdo->query("
    SELECT
        DATE_FORMAT(join_date, '%b %Y')              AS month_label,
        DATE_FORMAT(join_date, '%Y-%m')              AS month_key,
        COUNT(*)                                     AS joined_count
    FROM patients
    GROUP BY DATE_FORMAT(join_date, '%Y-%m')
    ORDER BY MIN(join_date) ASC
")->fetchAll();

// ── Patients grouped by join month (which calendar month they joined)
$grouped_by_month = $pdo->query("
    SELECT
        DATE_FORMAT(join_date, '%M')                 AS join_month_name,
        MONTH(join_date)                             AS join_month_num,
        COUNT(*)                                     AS patient_count,
        GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') AS patient_names
    FROM patients
    GROUP BY MONTH(join_date)
    ORDER BY MONTH(join_date) ASC
")->fetchAll();

// ── Visits linked to their join-month group
$visits_by_join_month = $pdo->query("
    SELECT
        DATE_FORMAT(p.join_date, '%M')               AS join_month,
        COUNT(v.visit_id)                            AS total_visits,
        COUNT(DISTINCT p.patient_id)                 AS patient_count,
        ROUND(COUNT(v.visit_id) /
              NULLIF(COUNT(DISTINCT p.patient_id),0), 1) AS avg_visits_per_patient
    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    GROUP BY MONTH(p.join_date), DATE_FORMAT(p.join_date, '%M')
    ORDER BY MONTH(p.join_date) ASC
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-bar-chart me-2"></i>Monthly Report</h2>
</div>

<!-- Visits per month chart -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-graph-up me-2"></i>Visits Per Month — Last 6 Months
    </div>
    <div class="card-body">
        <canvas id="visitsChart" height="80"></canvas>
    </div>
</div>

<!-- Visits per month table -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar3 me-2"></i>Visit Count by Month
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Month</th>
                            <th>Visits</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visits_monthly as $m): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?= $m['month_label'] ?></td>
                            <td>
                                <span class="badge bg-primary"><?= $m['visit_count'] ?></span>
                            </td>
                            <td>₹<?= number_format($m['revenue'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($visits_monthly)): ?>
                        <tr>
                            <td colspan="3" class="text-muted p-3">No data.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Patients joined per month -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-check me-2"></i>Patients Joined by Month
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Month</th>
                            <th>Patients Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($joined_monthly as $m): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?= $m['month_label'] ?></td>
                            <td>
                                <span class="badge bg-success"><?= $m['joined_count'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Grouped by join month -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-diagram-3 me-2"></i>
        Patients Grouped by Join Month + Visit Behaviour
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Join Month</th>
                    <th>Patients</th>
                    <th>Total Visits</th>
                    <th>Avg Visits / Patient</th>
                    <th>Patients</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($visits_by_join_month as $i => $row): ?>
                <tr>
                    <td class="ps-3 fw-semibold"><?= $row['join_month'] ?></td>
                    <td>
                        <span class="badge bg-secondary">
                            <?= $row['patient_count'] ?>
                        </span>
                    </td>
                    <td><?= $row['total_visits'] ?></td>
                    <td>
                        <span class="badge bg-info text-dark">
                            <?= $row['avg_visits_per_patient'] ?>
                        </span>
                    </td>
                    <td class="text-muted" style="font-size:12px">
                        <?= htmlspecialchars($grouped_by_month[$i]['patient_names'] ?? '') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels  = <?= json_encode(array_column($visits_monthly, 'month_label')) ?>;
const visits  = <?= json_encode(array_column($visits_monthly, 'visit_count')) ?>;
const revenue = <?= json_encode(array_column($visits_monthly, 'revenue')) ?>;

new Chart(document.getElementById('visitsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Visits',
                data: visits,
                backgroundColor: 'rgba(79,142,247,0.75)',
                borderRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Revenue (₹)',
                data: revenue,
                type: 'line',
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
</script>

<?php require_once '../includes/footer.php'; ?>