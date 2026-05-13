<?php
require_once '../config/db.php';
require_once '../includes/header.php';

$id = (int)($_POST['patient_id'] ?? 0);
if (!$id) {
    header('Location: list.php');
    exit;
}

// SQL calculates everything about this patient
$patient = $pdo->prepare("
    SELECT
        p.*,
        DATE_FORMAT(p.dob, '%d %b %Y')              AS dob_formatted,
        DATE_FORMAT(p.join_date, '%d %b %Y')         AS join_formatted,

        -- Age in years
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE())        AS age_years,

        -- Full age years + months
        CONCAT(
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()), ' years, ',
            MOD(TIMESTAMPDIFF(MONTH, p.dob, CURDATE()), 12), ' months'
        )                                            AS age_full,

        -- Days since last visit
        DATEDIFF(CURDATE(), MAX(v.visit_date))       AS days_since_last_visit,
        DATE_FORMAT(MAX(v.visit_date), '%d %b %Y')   AS last_visit_formatted,

        -- Next follow-up
        MIN(CASE WHEN v.follow_up_due >= CURDATE()
            THEN v.follow_up_due END)                AS next_followup,

        -- Is any follow-up overdue?
        MAX(CASE WHEN v.follow_up_due < CURDATE()
            THEN 1 ELSE 0 END)                       AS has_overdue,

        -- Total visits
        COUNT(v.visit_id)                            AS total_visits,

        -- Total fees paid
        SUM(v.consultation_fee + v.lab_fee)          AS total_fees

    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    WHERE p.patient_id = ?
    GROUP BY p.patient_id
");
$patient->execute([$id]);
$p = $patient->fetch();

if (!$p) {
    header('Location: list.php');
    exit;
}

// Visit history for this patient with SQL date logic
$visits = $pdo->prepare("
    SELECT
        v.visit_id,
        v.visit_date,
        DATE_FORMAT(v.visit_date, '%d %b %Y')        AS visit_formatted,
        v.consultation_fee,
        v.lab_fee,
        (v.consultation_fee + v.lab_fee)             AS total_fee,
        v.follow_up_due,
        DATE_FORMAT(v.follow_up_due, '%d %b %Y')     AS followup_formatted,
        DATEDIFF(CURDATE(), v.visit_date)            AS days_since,
        CASE
            WHEN v.follow_up_due < CURDATE()
                THEN 'overdue'
            WHEN v.follow_up_due BETWEEN CURDATE()
                 AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                THEN 'upcoming'
            ELSE 'done'
        END                                          AS followup_status
    FROM visits v
    WHERE v.patient_id = ?
    ORDER BY v.visit_date DESC
");
$visits->execute([$id]);
$visit_list = $visits->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h2><i class="bi bi-person-lines-fill me-2"></i><?= htmlspecialchars($p['name']) ?></h2>
    <div class="d-flex gap-2">
        <!-- <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a> -->

        <form method="POST" action="edit.php">
            <input type="hidden" name="patient_id"
                value="<?= $id ?>">
            <button type="submit" class="btn btn-outline-secondary me-1">
                <i class="bi bi-pencil"></i>
                <span>Edit</span>
            </button>
        </form>



        <a href="../visits/add.php?patient_id=<?= $id ?>" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Add Visit
        </a>
        <a href="list.php" class="btn btn-outline-primary">Back</a>
    </div>
</div>

<!-- Patient Info Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:36px;font-weight:700;color:#4f8ef7"><?= $p['age_years'] ?></div>
            <div class="text-muted" style="font-size:13px">Age (years)</div>
            <div style="font-size:12px;color:#888"><?= $p['age_full'] ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:36px;font-weight:700;color:#10b981"><?= $p['total_visits'] ?></div>
            <div class="text-muted" style="font-size:13px">Total Visits</div>
            <div style="font-size:12px;color:#888">Last: <?= $p['last_visit_formatted'] ?? 'None' ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:36px;font-weight:700;color:#f59e0b">
                <?= $p['days_since_last_visit'] ?? '—' ?>
            </div>
            <div class="text-muted" style="font-size:13px">Days Since Last Visit</div>
            <?php if ($p['has_overdue']): ?>
                <span class="badge badge-overdue">Follow-up Overdue</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:24px;font-weight:700;color:#8b5cf6">
                ₹<?= number_format($p['total_fees'] ?? 0, 0) ?>
            </div>
            <div class="text-muted" style="font-size:13px">Total Fees Paid</div>
            <div style="font-size:12px;color:#888">
                Next follow-up: <?= $p['next_followup'] ?? 'None' ?>
            </div>
        </div>
    </div>
</div>

<!-- Patient Details -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card p-4">
            <h6 class="fw-semibold mb-3">Patient Details</h6>
            <table class="table table-sm mb-0">
                <tr>
                    <td class="text-muted">DOB</td>
                    <td><?= $p['dob_formatted'] ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Age</td>
                    <td><?= $p['age_full'] ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Joined</td>
                    <td><?= $p['join_formatted'] ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Phone</td>
                    <td><?= htmlspecialchars($p['phone'] ?? '—') ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Address</td>
                    <td><?= htmlspecialchars($p['address'] ?? '—') ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Visit History -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-clock-history me-2"></i>Visit History</span>
                

                <form method="POST" action="../visits/patient_visits.php">
                    <input type="hidden" name="patient_id"
                        value="<?= $id ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary me-1">Full History</button>
                </form>
            </div>


            <div class="card-body p-0">
                <?php if (empty($visit_list)): ?>
                    <p class="text-muted p-3 mb-0">No visits recorded yet.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Days Ago</th>
                                <th>Fee</th>
                                <th>Follow-up</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visit_list as $v): ?>
                                <tr>
                                    <td class="ps-3"><?= $v['visit_formatted'] ?></td>
                                    <td><?= $v['days_since'] ?> days</td>
                                    <td>₹<?= number_format($v['total_fee'], 2) ?></td>
                                    <td>
                                        <?php
                                        $badge = match ($v['followup_status']) {
                                            'overdue'  => ['badge-overdue',  'Overdue'],
                                            'upcoming' => ['badge-upcoming', 'Upcoming'],
                                            default    => ['badge-done',     'Done'],
                                        };
                                        ?>
                                        <span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
                                        <small class="text-muted"><?= $v['followup_formatted'] ?></small>
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

<?php require_once '../includes/footer.php'; ?>