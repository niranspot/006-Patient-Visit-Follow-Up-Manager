<?php
require_once '../config/db.php';
require_once '../includes/header.php';

// ── Upcoming follow-ups (next 7 days)
$upcoming = $pdo->query("
    SELECT
        p.patient_id,
        p.name,
        p.phone,
        v.visit_date,
        DATE_FORMAT(v.visit_date, '%d %b %Y')        AS visit_formatted,
        v.follow_up_due,
        DATE_FORMAT(v.follow_up_due, '%d %b %Y')     AS followup_formatted,
        DATEDIFF(v.follow_up_due, CURDATE())          AS days_left
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    WHERE v.follow_up_due BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY v.follow_up_due ASC
")->fetchAll();

// ── Overdue follow-ups (follow_up_due < today)
$overdue = $pdo->query("
    SELECT
        p.patient_id,
        p.name,
        p.phone,
        v.visit_date,
        DATE_FORMAT(v.visit_date, '%d %b %Y')        AS visit_formatted,
        v.follow_up_due,
        DATE_FORMAT(v.follow_up_due, '%d %b %Y')     AS followup_formatted,
        DATEDIFF(CURDATE(), v.follow_up_due)          AS days_overdue
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    WHERE v.follow_up_due < CURDATE()
    ORDER BY v.follow_up_due ASC
")->fetchAll();

// ── Missed follow-ups:
// follow_up_due has passed AND patient made no visit after that due date
$missed = $pdo->query("
    SELECT
        p.patient_id,
        p.name,
        p.phone,
        v.follow_up_due,
        DATE_FORMAT(v.follow_up_due, '%d %b %Y')     AS followup_formatted,
        DATEDIFF(CURDATE(), v.follow_up_due)          AS days_missed,
        (
            SELECT COUNT(*)
            FROM visits v2
            WHERE v2.patient_id = p.patient_id
              AND v2.visit_date > v.follow_up_due
        )                                             AS visits_after_due
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    WHERE v.follow_up_due < CURDATE()
    HAVING visits_after_due = 0
    ORDER BY v.follow_up_due ASC
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-clock-history me-2"></i>Follow-Up Report</h2>
</div>

<!-- Summary pills -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 text-center" style="border-left:4px solid #f59e0b">
            <div style="font-size:30px;font-weight:700;color:#f59e0b">
                <?= count($upcoming) ?>
            </div>
            <div class="text-muted" style="font-size:13px">Upcoming (Next 7 Days)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center" style="border-left:4px solid #ef4444">
            <div style="font-size:30px;font-weight:700;color:#ef4444">
                <?= count($overdue) ?>
            </div>
            <div class="text-muted" style="font-size:13px">Overdue Follow-ups</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 text-center" style="border-left:4px solid #6b7280">
            <div style="font-size:30px;font-weight:700;color:#6b7280">
                <?= count($missed) ?>
            </div>
            <div class="text-muted" style="font-size:13px">Missed (No Return Visit)</div>
        </div>
    </div>
</div>

<!-- Upcoming -->
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #f59e0b">
        <i class="bi bi-calendar-event me-2" style="color:#f59e0b"></i>
        Upcoming Follow-ups — Next 7 Days
    </div>
    <div class="card-body p-0">
        <?php if (empty($upcoming)): ?>
            <p class="text-muted p-3 mb-0">No follow-ups due in the next 7 days.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Patient</th>
                    <th>Phone</th>
                    <th>Last Visit</th>
                    <th>Follow-up Due</th>
                    <th>Days Left</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $r): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <?= htmlspecialchars($r['name']) ?>
                    </td>
                    <td><?= htmlspecialchars($r['phone']) ?></td>
                    <td><?= $r['visit_formatted'] ?></td>
                    <td><?= $r['followup_formatted'] ?></td>
                    <td>
                        <span class="badge badge-upcoming">
                            <?= $r['days_left'] == 0 ? 'Today' : 'In ' . $r['days_left'] . ' days' ?>
                        </span>
                    </td>
                    <td>
                        
                        <form method="POST" action="../patients/view.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $r['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Overdue -->
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #ef4444">
        <i class="bi bi-exclamation-circle me-2" style="color:#ef4444"></i>
        Overdue Follow-ups
    </div>
    <div class="card-body p-0">
        <?php if (empty($overdue)): ?>
            <p class="text-muted p-3 mb-0">No overdue follow-ups.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Patient</th>
                    <th>Phone</th>
                    <th>Last Visit</th>
                    <th>Was Due</th>
                    <th>Days Overdue</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($overdue as $r): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <?= htmlspecialchars($r['name']) ?>
                    </td>
                    <td><?= htmlspecialchars($r['phone']) ?></td>
                    <td><?= $r['visit_formatted'] ?></td>
                    <td><?= $r['followup_formatted'] ?></td>
                    <td>
                        <span class="badge badge-overdue">
                            <?= $r['days_overdue'] ?> days late
                        </span>
                    </td>
                    <td>
                        
                        <form method="POST" action="../patients/view.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $r['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Missed -->
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #6b7280">
        <i class="bi bi-person-dash me-2" style="color:#6b7280"></i>
        Missed Follow-ups — No Return Visit After Due Date
    </div>
    <div class="card-body p-0">
        <?php if (empty($missed)): ?>
            <p class="text-muted p-3 mb-0">No missed follow-ups.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Patient</th>
                    <th>Phone</th>
                    <th>Follow-up Was Due</th>
                    <th>Days Since Due</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($missed as $r): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <?= htmlspecialchars($r['name']) ?>
                    </td>
                    <td><?= htmlspecialchars($r['phone']) ?></td>
                    <td><?= $r['followup_formatted'] ?></td>
                    <td>
                        <span class="badge badge-inactive">
                            <?= $r['days_missed'] ?> days ago
                        </span>
                    </td>
                    <td>
                        
                        <form method="POST" action="../patients/view.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $r['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>