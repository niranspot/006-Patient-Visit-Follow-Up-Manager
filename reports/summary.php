<?php
require_once '../config/db.php';
require_once '../includes/header.php';

// ── Full summary — every calculation done in SQL
$summary = $pdo->query("
    SELECT
        p.patient_id,
        p.name,
        p.phone,

        -- Age
        CONCAT(
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()), ' yrs, ',
            MOD(TIMESTAMPDIFF(MONTH, p.dob, CURDATE()), 12), ' mo'
        )                                               AS age_full,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE())           AS age_years,

        -- Visit stats
        COUNT(v.visit_id)                               AS total_visits,
        DATE_FORMAT(MAX(v.visit_date), '%d %b %Y')      AS last_visit,
        DATEDIFF(CURDATE(), MAX(v.visit_date))           AS days_since_last_visit,

        -- Next follow-up (soonest future one)
        DATE_FORMAT(
            MIN(CASE WHEN v.follow_up_due >= CURDATE()
                THEN v.follow_up_due END),
            '%d %b %Y'
        )                                               AS next_followup,

        -- Overdue flag
        MAX(CASE WHEN v.follow_up_due < CURDATE()
            THEN 1 ELSE 0 END)                          AS has_overdue,

        -- Inactive flag (no visit in 180 days)
        CASE
            WHEN MAX(v.visit_date) IS NULL
                THEN 'no_visits'
            WHEN DATEDIFF(CURDATE(), MAX(v.visit_date)) >= 180
                THEN 'inactive'
            ELSE 'active'
        END                                             AS activity_status,

        -- Total fees
        SUM(v.consultation_fee + v.lab_fee)             AS total_fees

    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    GROUP BY p.patient_id
    ORDER BY days_since_last_visit DESC
")->fetchAll();

// ── Patients with no visits at all
$no_visit_patients = $pdo->query("
    SELECT p.patient_id, p.name, p.phone,
           DATE_FORMAT(p.join_date, '%d %b %Y') AS joined,
           TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age
    FROM patients p
    WHERE p.patient_id NOT IN (
        SELECT DISTINCT patient_id FROM visits
    )
")->fetchAll();

// ── Inactive 180+ days
$inactive = $pdo->query("
    SELECT
        p.patient_id, p.name, p.phone,
        DATE_FORMAT(MAX(v.visit_date), '%d %b %Y')   AS last_visit,
        DATEDIFF(CURDATE(), MAX(v.visit_date))        AS days_inactive
    FROM patients p
    JOIN visits v ON p.patient_id = v.patient_id
    GROUP BY p.patient_id
    HAVING days_inactive >= 180
    ORDER BY days_inactive DESC
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-file-earmark-text me-2"></i>Full Summary Report</h2>
</div>

<!-- Full patient summary table -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-table me-2"></i>All Patients — Complete Summary
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Name</th>
                    <th>Age</th>
                    <th>Visits</th>
                    <th>Last Visit</th>
                    <th>Days Since</th>
                    <th>Next Follow-up</th>
                    <th>Overdue</th>
                    <th>Status</th>
                    <th>Total Fees</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($summary as $s): ?>
                <tr>
                    <td class="ps-3">
                        <a href="../patients/view.php?id=<?= $s['patient_id'] ?>"
                           class="fw-semibold text-decoration-none">
                            <?= htmlspecialchars($s['name']) ?>
                        </a>
                        <div class="text-muted" style="font-size:11px">
                            <?= $s['phone'] ?>
                        </div>
                    </td>
                    <td><?= $s['age_full'] ?></td>
                    <td>
                        <span class="badge <?= $s['total_visits'] > 0 ? 'bg-primary' : 'bg-secondary' ?>">
                            <?= $s['total_visits'] ?>
                        </span>
                    </td>
                    <td><?= $s['last_visit'] ?? '—' ?></td>
                    <td>
                        <?php if ($s['days_since_last_visit'] !== null): ?>
                            <?= $s['days_since_last_visit'] ?> days
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $s['next_followup'] ?? '—' ?></td>
                    <td>
                        <?php if ($s['has_overdue']): ?>
                            <span class="badge badge-overdue">Yes</span>
                        <?php else: ?>
                            <span class="badge badge-done">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        [$cls, $label] = match($s['activity_status']) {
                            'inactive'  => ['badge-inactive', 'Inactive'],
                            'no_visits' => ['badge-overdue',  'No Visits'],
                            default     => ['badge-done',     'Active'],
                        };
                        ?>
                        <span class="badge <?= $cls ?>"><?= $label ?></span>
                    </td>
                    <td>₹<?= number_format($s['total_fees'] ?? 0, 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- No visit patients -->
<?php if (!empty($no_visit_patients)): ?>
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #ef4444">
        <i class="bi bi-person-x me-2" style="color:#ef4444"></i>
        Patients With No Visits
        <span class="badge bg-danger ms-2"><?= count($no_visit_patients) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Name</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($no_visit_patients as $np): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <?= htmlspecialchars($np['name']) ?>
                    </td>
                    <td><?= $np['age'] ?> yrs</td>
                    <td><?= $np['phone'] ?></td>
                    <td><?= $np['joined'] ?></td>
                    <td>
                        <a href="../visits/add.php?patient_id=<?= $np['patient_id'] ?>"
                           class="btn btn-sm btn-outline-success">
                            <i class="bi bi-plus-circle me-1"></i>Add Visit
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Inactive 180+ days -->
<?php if (!empty($inactive)): ?>
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #f59e0b">
        <i class="bi bi-person-slash me-2" style="color:#f59e0b"></i>
        Inactive Patients — No Visit in 180+ Days
        <span class="badge bg-warning text-dark ms-2"><?= count($inactive) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Name</th>
                    <th>Phone</th>
                    <th>Last Visit</th>
                    <th>Days Inactive</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inactive as $in): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <?= htmlspecialchars($in['name']) ?>
                    </td>
                    <td><?= $in['phone'] ?></td>
                    <td><?= $in['last_visit'] ?></td>
                    <td>
                        <span class="badge badge-inactive">
                            <?= $in['days_inactive'] ?> days
                        </span>
                    </td>
                    <td>
                        <a href="../patients/view.php?id=<?= $in['patient_id'] ?>"
                           class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>