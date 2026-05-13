<?php
require_once '../config/db.php';
require_once '../includes/header.php';

$patient_id = (int)($_POST['patient_id'] ?? 0);
if (!$patient_id) {
    header('Location: list.php');
    exit;
}

// Patient info + SQL summary stats
$patient = $pdo->prepare("
    SELECT
        p.name,
        p.dob,
        DATE_FORMAT(p.dob, '%d %b %Y')               AS dob_formatted,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE())         AS age,

        -- Total visits
        COUNT(v.visit_id)                             AS total_visits,

        -- Days between first and last visit (SQL only)
        DATEDIFF(MAX(v.visit_date), MIN(v.visit_date)) AS days_as_patient,

        -- First and last visit dates
        DATE_FORMAT(MIN(v.visit_date), '%d %b %Y')    AS first_visit,
        DATE_FORMAT(MAX(v.visit_date), '%d %b %Y')    AS last_visit,

        -- Total fees
        SUM(v.consultation_fee + v.lab_fee)           AS total_fees,
        SUM(v.consultation_fee)                       AS total_consultation,
        SUM(v.lab_fee)                                AS total_lab

    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    WHERE p.patient_id = ?
    GROUP BY p.patient_id
");
$patient->execute([$patient_id]);
$p = $patient->fetch();

if (!$p) {
    header('Location: list.php');
    exit;
}

// All visits for this patient — SQL handles all calculations
$visits = $pdo->prepare("
    SELECT
        v.visit_id,
        v.visit_date,
        DATE_FORMAT(v.visit_date, '%d %b %Y')         AS visit_formatted,
        v.consultation_fee,
        v.lab_fee,
        (v.consultation_fee + v.lab_fee)              AS total_fee,
        v.follow_up_due,
        DATE_FORMAT(v.follow_up_due, '%d %b %Y')      AS followup_formatted,

        -- Days since this visit
        DATEDIFF(CURDATE(), v.visit_date)             AS days_since,

        -- Days between this visit and the next (SQL window-style with subquery)
        DATEDIFF(
            LEAD(v.visit_date) OVER (ORDER BY v.visit_date ASC),
            v.visit_date
        )                                             AS days_to_next,

        -- Follow-up status
        CASE
            WHEN v.follow_up_due < CURDATE()
                THEN 'overdue'
            WHEN v.follow_up_due BETWEEN CURDATE()
                 AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                THEN 'upcoming'
            ELSE 'done'
        END                                           AS followup_status

    FROM visits v
    WHERE v.patient_id = ?
    ORDER BY v.visit_date DESC
");
$visits->execute([$patient_id]);
$visit_list = $visits->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h2>
        <i class="bi bi-clock-history me-2"></i>
        Visit History — <?= htmlspecialchars($p['name']) ?>
    </h2>
    <div class="d-flex gap-2">
       
        <form method="POST" action="add.php">
            <input type="hidden" name="patient_id"
                value="<?= $patient_id ?>">
            <button type="submit" class="btn btn-success">Add Visit</button>
        </form>

        <form method="POST" action="../patients/view.php">
            <input type="hidden" name="patient_id"
                value="<?= $patient_id ?>">
            <button type="submit" class="btn btn-outline-secondary">Patient Profile</button>
        </form>
    </div>
</div>

<!-- Stats row — all from SQL -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:30px;font-weight:700;color:#4f8ef7">
                <?= $p['total_visits'] ?>
            </div>
            <div class="text-muted" style="font-size:13px">Total Visits</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:30px;font-weight:700;color:#8b5cf6">
                <?= $p['days_as_patient'] ?? 0 ?>
            </div>
            <div class="text-muted" style="font-size:13px">Days: First → Last Visit</div>
            
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:22px;font-weight:700;color:#10b981">
                ₹<?= number_format($p['total_fees'] ?? 0, 0) ?>
            </div>
            <div class="text-muted" style="font-size:13px">Total Fees</div>
            <small class="text-muted">
                Consult: ₹<?= number_format($p['total_consultation'] ?? 0, 0) ?> |
                Lab: ₹<?= number_format($p['total_lab'] ?? 0, 0) ?>
            </small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:16px;font-weight:600;color:#1a1f2e">
                <?= $p['first_visit'] ?? '—' ?>
            </div>
            <div class="text-muted" style="font-size:13px">First Visit</div>
            <div style="font-size:13px;color:#1a1f2e"><?= $p['last_visit'] ?? '—' ?></div>
            <div class="text-muted" style="font-size:13px">Last Visit</div>
        </div>
    </div>
</div>

<!-- Visit timeline table -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-list-ul me-2"></i>All Visits
    </div>
    <div class="card-body p-0">
        <?php if (empty($visit_list)): ?>
            <p class="text-muted p-3 mb-0">No visits found for this patient.</p>
        <?php else: ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Visit Date</th>
                        <th>Days Ago</th>
                        <th>Gap to Next</th>
                        <th>Consultation</th>
                        <th>Lab</th>
                        <th>Total</th>
                        <th>Follow-up Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visit_list as $i => $v): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $i+1 ?></td>
                            <td class="fw-semibold"><?= $v['visit_formatted'] ?></td>
                            <td><?= $v['days_since'] ?> days</td>
                            <td>
                                <?php if ($v['days_to_next']): ?>
                                    <span class="text-muted"><?= $v['days_to_next'] ?> days</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>₹<?= number_format($v['consultation_fee'], 2) ?></td>
                            <td>₹<?= number_format($v['lab_fee'], 2) ?></td>
                            <td class="fw-semibold">₹<?= number_format($v['total_fee'], 2) ?></td>
                            <td><?= $v['followup_formatted'] ?></td>
                            <td>
                                <?php
                                [$cls, $label] = match ($v['followup_status']) {
                                    'overdue'  => ['badge-overdue',  'Overdue'],
                                    'upcoming' => ['badge-upcoming', 'Upcoming'],
                                    default    => ['badge-done',     'Done'],
                                };
                                ?>
                                <span class="badge <?= $cls ?>"><?= $label ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>