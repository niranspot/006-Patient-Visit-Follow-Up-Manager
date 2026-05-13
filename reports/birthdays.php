<?php
require_once '../config/db.php';
require_once '../includes/header.php';

// ── Birthdays in next 30 days
// Handles December → January wrap using DAYOFYEAR with leap year safety
$birthdays_soon = $pdo->query("
    SELECT
        patient_id,
        name,
        phone,
        dob,
        DATE_FORMAT(dob, '%d %b')                        AS birthday_display,
        TIMESTAMPDIFF(YEAR, dob, CURDATE())              AS current_age,
        TIMESTAMPDIFF(YEAR, dob, CURDATE()) + 1          AS turning_age,


        -- Correct cross-year birthday calculation
        DATEDIFF(
            IF(
                DATE_FORMAT(CURDATE(), '%m-%d') <= DATE_FORMAT(dob, '%m-%d'),
                DATE_FORMAT(CONCAT(YEAR(CURDATE()), '-', MONTH(dob), '-', DAY(dob)), '%Y-%m-%d'),
                DATE_FORMAT(CONCAT(YEAR(CURDATE()) + 1, '-', MONTH(dob), '-', DAY(dob)), '%Y-%m-%d')
            ),
            CURDATE()
        )                                                AS days_until_birthday

    FROM patients
    HAVING days_until_birthday BETWEEN 0 AND 30
    ORDER BY days_until_birthday ASC
")->fetchAll();

// ── Patients turning 40, 50, 60 this year
$milestone_ages = [40, 50, 60];
$milestones = [];
foreach ($milestone_ages as $age) {
    $stmt = $pdo->prepare("
        SELECT
            patient_id,
            name,
            dob,
            DATE_FORMAT(dob, '%d %b %Y')                AS dob_formatted,
            TIMESTAMPDIFF(YEAR, dob, CURDATE())         AS current_age,
            ? AS milestone_age,
            DATE_FORMAT(
                DATE_ADD(dob, INTERVAL ? YEAR), '%d %b %Y'
            )                                           AS milestone_date
        FROM patients
        WHERE TIMESTAMPDIFF(YEAR, dob,
              DATE_FORMAT(CONCAT(YEAR(CURDATE()), '-12-31'), '%Y-%m-%d')) = ?
        ORDER BY dob ASC
    ");
    $stmt->execute([$age, $age, $age]);
    $milestones[$age] = $stmt->fetchAll();
}
?>

<div class="page-header">
    <h2><i class="bi bi-cake2 me-2"></i>Birthday Report</h2>
</div>

<!-- Birthdays in next 30 days -->
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #ec4899">
        <i class="bi bi-gift me-2" style="color:#ec4899"></i>
        Birthdays in Next 30 Days
        <span class="badge bg-danger ms-2"><?= count($birthdays_soon) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($birthdays_soon)): ?>
            <p class="text-muted p-3 mb-0">No birthdays in the next 30 days.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Patient</th>
                    <th>Phone</th>
                    <th>Birthday</th>
                    <th>Current Age</th>
                    <th>Turning</th>
                    <th>Days Away</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($birthdays_soon as $b): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <a href="../patients/view.php?id=<?= $b['patient_id'] ?>"
                           class="text-decoration-none">
                            <?= htmlspecialchars($b['name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($b['phone']) ?></td>
                    <td>
                        <i class="bi bi-cake2 text-danger me-1"></i>
                        <?= $b['birthday_display'] ?>
                    </td>
                    <td><?= $b['current_age'] ?> yrs</td>
                    <td>
                        <span class="badge" style="background:#fce7f3;color:#be185d">
                            <?= $b['turning_age'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['days_until_birthday'] == 0): ?>
                            <span class="badge bg-danger">Today! 🎂</span>
                        <?php else: ?>
                            <span class="badge badge-upcoming">
                                <?= $b['days_until_birthday'] ?> days
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Milestone birthdays -->
<?php foreach ($milestone_ages as $age): ?>
<div class="card mb-4">
    <div class="card-header" style="border-left:4px solid #8b5cf6">
        <i class="bi bi-star me-2" style="color:#8b5cf6"></i>
        Patients Turning <strong><?= $age ?></strong> This Year
        <span class="badge bg-secondary ms-2">
            <?= count($milestones[$age]) ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($milestones[$age])): ?>
            <p class="text-muted p-3 mb-0">No patients turning <?= $age ?> this year.</p>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Patient</th>
                    <th>Date of Birth</th>
                    <th>Current Age</th>
                    <th>Turns <?= $age ?> On</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($milestones[$age] as $m): ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <a href="../patients/view.php?id=<?= $m['patient_id'] ?>"
                           class="text-decoration-none">
                            <?= htmlspecialchars($m['name']) ?>
                        </a>
                    </td>
                    <td><?= $m['dob_formatted'] ?></td>
                    <td><?= $m['current_age'] ?> yrs</td>
                    <td>
                        <span class="badge" style="background:#ede9fe;color:#6d28d9">
                            <?= $m['milestone_date'] ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>