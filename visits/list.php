<?php
require_once '../config/db.php';
require_once '../includes/header.php';


$per_page    = 5;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

// total count for pagination
$total = $pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn();
$total_pages = ceil($total / $per_page);
// All visits — SQL calculates days since, follow-up status
$visits = $pdo->query("
    SELECT
        v.visit_id,
        v.visit_date,
        DATE_FORMAT(v.visit_date, '%d %b %Y')        AS visit_formatted,
        p.patient_id,
        p.name,

        -- Days since this visit
        DATEDIFF(CURDATE(), v.visit_date)            AS days_since,

        -- Fees
        v.consultation_fee,
        v.lab_fee,
        (v.consultation_fee + v.lab_fee)             AS total_fee,

        -- Follow-up date
        v.follow_up_due,
        DATE_FORMAT(v.follow_up_due, '%d %b %Y')     AS followup_formatted,

        -- Follow-up status — all done in SQL
        CASE
            WHEN v.follow_up_due < CURDATE()
                THEN 'overdue'
            WHEN v.follow_up_due BETWEEN CURDATE()
                 AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                THEN 'upcoming'
            ELSE 'done'
        END                                          AS followup_status,

        -- Days until follow-up (positive = future, negative = overdue)
        DATEDIFF(v.follow_up_due, CURDATE())         AS followup_in_days

    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    ORDER BY v.visit_date DESC
    LIMIT $per_page OFFSET $offset
")->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h2><i class="bi bi-calendar-check me-2"></i>All Visits</h2>
    <a href="add.php" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i>Add Visit
    </a>
</div>

<!-- Summary pills -->
<?php
$summary = $pdo->query("
    SELECT
        COUNT(*)                                                              AS total,
        SUM(CASE WHEN follow_up_due < CURDATE() THEN 1 ELSE 0 END)          AS overdue,
        SUM(CASE WHEN follow_up_due BETWEEN CURDATE()
             AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END)     AS upcoming,
        SUM(consultation_fee + lab_fee)                                      AS total_fees
    FROM visits
")->fetch();
?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:28px;font-weight:700;color:#4f8ef7"><?= $summary['total'] ?></div>
            <div class="text-muted" style="font-size:13px">Total Visits</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:28px;font-weight:700;color:#ef4444"><?= $summary['overdue'] ?></div>
            <div class="text-muted" style="font-size:13px">Overdue Follow-ups</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:28px;font-weight:700;color:#f59e0b"><?= $summary['upcoming'] ?></div>
            <div class="text-muted" style="font-size:13px">Upcoming This Week</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 text-center">
            <div style="font-size:22px;font-weight:700;color:#10b981">
                ₹<?= number_format($summary['total_fees'], 0) ?>
            </div>
            <div class="text-muted" style="font-size:13px">Total Revenue</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Patient</th>
                    <th>Visit Date</th>
                    <th>Days Ago</th>
                    <th>Consultation</th>
                    <th>Lab Fee</th>
                    <th>Total</th>
                    <th>Follow-up Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($visits as $v): ?>
                <tr>
                    <td class="ps-3">
                        <a href="../patients/view.php?id=<?= $v['patient_id'] ?>"
                           class="fw-semibold text-decoration-none">
                            <?= htmlspecialchars($v['name']) ?>
                        </a>
                    </td>
                    <td><?= $v['visit_formatted'] ?></td>
                    <td>
                        <span class="text-muted"><?= $v['days_since'] ?> days</span>
                    </td>
                    <td>₹<?= number_format($v['consultation_fee'], 2) ?></td>
                    <td>₹<?= number_format($v['lab_fee'], 2) ?></td>
                    <td class="fw-semibold">₹<?= number_format($v['total_fee'], 2) ?></td>
                    <td><?= $v['followup_formatted'] ?></td>
                    <td>
                        <?php
                        [$cls, $label] = match($v['followup_status']) {
                            'overdue'  => ['badge-overdue',
                                          'Overdue ' . abs($v['followup_in_days']) . 'd'],
                            'upcoming' => ['badge-upcoming',
                                          $v['followup_in_days'] == 0
                                              ? 'Today'
                                              : 'In ' . $v['followup_in_days'] . 'd'],
                            default    => ['badge-done', 'Done'],
                        };
                        ?>
                        <span class="badge <?= $cls ?>"><?= $label ?></span>
                    </td>
                    <td>
                        <form method="POST" action="patient_visits.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $v['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">

        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>

    </ul>
    <p class="text-center text-muted" style="font-size:13px">
        Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?>
        of <?= $total ?> patients
    </p>
</nav>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>