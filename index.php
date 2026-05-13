<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// ── STAT 1: Total patients
$total_patients = $pdo->query("
    SELECT COUNT(*) AS total FROM patients
")->fetchColumn();

// ── STAT 2: Total visits
$total_visits = $pdo->query("
    SELECT COUNT(*) AS total FROM visits
")->fetchColumn();

// ── STAT 3: Follow-ups due in next 7 days
$upcoming_followups = $pdo->query("
    SELECT COUNT(*) AS total FROM visits
    WHERE follow_up_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();
// ── STAT 4: Overdue follow-ups
$overdue_followups = $pdo->query("
    SELECT COUNT(*) AS total FROM visits
    WHERE follow_up_due < CURDATE()
")->fetchColumn();

// ── STAT 5: Patients inactive 180+ days
$inactive_patients = $pdo->query("
    SELECT COUNT(DISTINCT patient_id) AS total FROM visits
    WHERE patient_id NOT IN (
        SELECT patient_id FROM visits
        WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
    )
")->fetchColumn();

// ── STAT 6: Patients with no visits at all
$no_visits = $pdo->query("
    SELECT COUNT(*) AS total FROM patients
    WHERE patient_id NOT IN (SELECT DISTINCT patient_id FROM visits)
")->fetchColumn();

// ── Recent 5 visits with SQL calculations
$recent_visits = $pdo->query("
    SELECT
        p.name,
        v.visit_date,
        DATEDIFF(CURDATE(), v.visit_date)      AS days_since,
        v.follow_up_due,
        CASE
            WHEN v.follow_up_due < CURDATE()                                              THEN 'overdue'
            WHEN v.follow_up_due BETWEEN CURDATE()
                 AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)                                  THEN 'upcoming'
            ELSE 'done'
        END                                    AS followup_status,
        (v.consultation_fee + v.lab_fee)       AS total_fee
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    ORDER BY v.visit_date DESC
    LIMIT 5
")->fetchAll();

// ── Upcoming follow-ups (next 7 days)
$upcoming_list = $pdo->query("
    SELECT
        p.name,
        v.follow_up_due,
        DATEDIFF(v.follow_up_due, CURDATE()) AS days_left
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    WHERE v.follow_up_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY v.follow_up_due ASC
    LIMIT 5
")->fetchAll();

// ── Monthly visits (last 6 months) for mini chart
$monthly = $pdo->query("
    SELECT
        DATE_FORMAT(visit_date, '%b %Y')  AS month_label,
        COUNT(*)                          AS visit_count
    FROM visits
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY MIN(visit_date) ASC
")->fetchAll();
?>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
    <small class="text-muted">
        Today: <?= $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%D %M %Y') AS d")->fetch()['d'] ?>
    </small>
</div>

<!-- ── STAT CARDS ── -->
<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#4f8ef7,#2563eb)">
            <div class="number"><?= $total_patients ?></div>
            <div class="label"><i class="bi bi-people me-1"></i>Total Patients</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="number"><?= $total_visits ?></div>
            <div class="label"><i class="bi bi-calendar-check me-1"></i>Total Visits</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="number"><?= $upcoming_followups ?></div>
            <div class="label"><i class="bi bi-clock me-1"></i>Follow-ups This Week</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#dc2626)">
            <div class="number"><?= $overdue_followups ?></div>
            <div class="label"><i class="bi bi-exclamation-circle me-1"></i>Overdue Follow-ups</div>
        </div>
    </div>

</div>

<!-- ── ROW 2: Inactive + No Visits ── -->
<div class="row g-3 mb-4">

    <div class="col-md-6">
        <div class="card p-3 d-flex flex-row align-items-center gap-3">
            <div style="background:#fff3cd;border-radius:10px;padding:14px">
                <i class="bi bi-person-slash" style="font-size:24px;color:#856404"></i>
            </div>
            <div>
                <div style="font-size:26px;font-weight:700;color:#1a1f2e"><?= $inactive_patients ?></div>
                <div class="text-muted" style="font-size:13px">Patients inactive for 180+ days</div>
            </div>
            <a href="./reports/summary.php" class="btn btn-sm btn-outline-secondary ms-auto">View</a>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card p-3 d-flex flex-row align-items-center gap-3">
            <div style="background:#e0f0ff;border-radius:10px;padding:14px">
                <i class="bi bi-person-x" style="font-size:24px;color:#1a6fc4"></i>
            </div>
            <div>
                <div style="font-size:26px;font-weight:700;color:#1a1f2e"><?= $no_visits ?></div>
                <div class="text-muted" style="font-size:13px">Patients with no visits</div>
            </div>
            <a href="./patients/list.php" class="btn btn-sm btn-outline-secondary ms-auto">View</a>
        </div>
    </div>

</div>

<!-- ── ROW 3: Recent Visits + Upcoming Follow-ups ── -->
<div class="row g-3 mb-4">

    <!-- Recent Visits -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Recent Visits</span>
                <a href="./visits/list.php" class="btn btn-sm btn-outline-primary">All Visits</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Patient</th>
                            <th>Visit Date</th>
                            <th>Days Ago</th>
                            <th>Follow-up</th>
                            <th>Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_visits as $v): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?= htmlspecialchars($v['name']) ?></td>
                            <td><?= $v['visit_date'] ?></td>
                            <td><?= $v['days_since'] ?> days</td>
                            <td>
                                <?php
                                $badge = match($v['followup_status']) {
                                    'overdue'  => ['badge-overdue',  'Overdue'],
                                    'upcoming' => ['badge-upcoming', 'Upcoming'],
                                    default    => ['badge-done',     'Done'],
                                };
                                ?>
                                <span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
                                <small class="text-muted"><?= $v['follow_up_due'] ?></small>
                            </td>
                            <td>₹<?= number_format($v['total_fee'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Upcoming Follow-ups -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event me-2"></i>Upcoming Follow-ups</span>
                <a href="/reports/followups.php" class="btn btn-sm btn-outline-warning">All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming_list)): ?>
                    <p class="text-muted p-3 mb-0">No follow-ups due this week.</p>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Patient</th>
                            <th>Due Date</th>
                            <th>Days Left</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming_list as $f): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?= htmlspecialchars($f['name']) ?></td>
                            <td><?= $f['follow_up_due'] ?></td>
                            <td>
                                <span class="badge badge-upcoming">
                                    <?= $f['days_left'] == 0 ? 'Today' : $f['days_left'] . 'd' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ── Monthly Bar Chart ── -->
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Visits — Last 6 Months
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode(array_column($monthly, 'month_label')) ?>;
const data   = <?= json_encode(array_column($monthly, 'visit_count')) ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Visits',
            data,
            backgroundColor: 'rgba(79,142,247,0.7)',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>