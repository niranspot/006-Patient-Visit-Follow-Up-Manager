<?php
require_once '../config/db.php';
require_once '../includes/header.php';

$errors  = [];
$success = '';

// Pre-select patient if coming from patient profile
$preselect_patient = (int)($_POST['patient_id'] ?? 0);

// Load all patients for dropdown
$patients = $pdo->query("
    SELECT patient_id, name,
           TIMESTAMPDIFF(YEAR, dob, CURDATE()) AS age
    FROM patients
    ORDER BY name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id      = (int)($_POST['patient_id'] ?? 0);
    $visit_date      = trim($_POST['visit_date'] ?? '');
    $consultation_fee= trim($_POST['consultation_fee'] ?? 0);
    $lab_fee         = trim($_POST['lab_fee'] ?? 0);

    if (!$patient_id)  $errors[] = 'Please select a patient.';
    if (!$visit_date)  $errors[] = 'Visit date is required.';

    // Validate visit_date is not future — done in SQL
    if ($visit_date) {
        $date_check = $pdo->prepare("
            SELECT CASE
                WHEN ? > CURDATE() THEN 'future'
                ELSE 'valid'
            END AS status
        ");
        $date_check->execute([$visit_date]);
        if ($date_check->fetch()['status'] === 'future') {
            $errors[] = 'Visit date cannot be in the future.';
        }
    }

    if (empty($errors)) {
        // follow_up_due calculated by SQL: visit_date + 7 days
        $stmt = $pdo->prepare("
            INSERT INTO visits
                (patient_id, visit_date, consultation_fee, lab_fee, follow_up_due)
            VALUES
                (?, ?, ?, ?, DATE_ADD(?, INTERVAL 7 DAY))
        ");
        $stmt->execute([
            $patient_id,
            $visit_date,
            $consultation_fee,
            $lab_fee,
            $visit_date   // SQL adds 7 days — no PHP date math
        ]);
        $success = 'Visit recorded successfully. Follow-up scheduled for 7 days later (via SQL).';
    }
}
?>

<div class="page-header">
    <h2><i class="bi bi-plus-circle me-2"></i>Add Visit</h2>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card p-4">

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i><?= $success ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= $e ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Patient <span class="text-danger">*</span>
                    </label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $pt): ?>
                            <option value="<?= $pt['patient_id'] ?>"
                                <?= ($preselect_patient == $pt['patient_id'] ||
                                    ($_POST['patient_id'] ?? 0) == $pt['patient_id'])
                                    ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pt['name']) ?>
                                (Age: <?= $pt['age'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Visit Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" name="visit_date" class="form-control"
                           value="<?= htmlspecialchars($_POST['visit_date'] ?? '') ?>"
                           required>
                    <div class="form-text">
                        Follow-up date will be auto-set to
                        <strong>visit date + 7 days</strong> using SQL.
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Consultation Fee (₹)</label>
                        <input type="number" name="consultation_fee"
                               class="form-control" min="0" step="0.01"
                               value="<?= htmlspecialchars($_POST['consultation_fee'] ?? '0') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Lab Fee (₹)</label>
                        <input type="number" name="lab_fee"
                               class="form-control" min="0" step="0.01"
                               value="<?= htmlspecialchars($_POST['lab_fee'] ?? '0') ?>">
                    </div>
                </div>

                <!-- Live follow-up preview via JS only for display -->
                <div class="alert alert-info py-2" id="followup-preview" style="display:none">
                    <i class="bi bi-calendar-event me-2"></i>
                    Follow-up will be scheduled for: <strong id="followup-date"></strong>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save me-1"></i>Save Visit
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- SQL info panel -->
    <!-- <div class="col-md-5">
        <div class="card p-3 mb-3" style="background:#f8f9fa;border-left:4px solid #10b981">
            <h6 class="fw-semibold mb-2" style="color:#10b981">
                <i class="bi bi-code-slash me-1"></i>SQL on Submit
            </h6>
            <pre style="font-size:12px;color:#333;margin:0">INSERT INTO visits (
  patient_id,
  visit_date,
  consultation_fee,
  lab_fee,
  follow_up_due        -- ← SQL calculates
) VALUES (
  ?,
  ?,
  ?,
  ?,
  DATE_ADD(?, INTERVAL 7 DAY)
);</pre>
        </div> -->

        <!-- Recent visits for context -->
        <div class="card p-3 mt-3">
            <h6 class="fw-semibold mb-2">Recent Visits</h6>
            <?php
            $recent = $pdo->query("
                SELECT p.name, v.visit_date,
                       DATEDIFF(CURDATE(), v.visit_date) AS days_ago,
                       v.follow_up_due
                FROM visits v
                JOIN patients p ON v.patient_id = p.patient_id
                ORDER BY v.visit_date DESC
                LIMIT 5
            ")->fetchAll();
            ?>
            <?php foreach ($recent as $r): ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span style="font-size:13px"><?= htmlspecialchars($r['name']) ?></span>
                    <span class="text-muted" style="font-size:12px">
                        <?= $r['days_ago'] ?>d ago
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Preview follow-up date in UI (display only — actual value set by SQL)
document.querySelector('[name="visit_date"]').addEventListener('change', function () {
    const d = new Date(this.value);
    if (!isNaN(d)) {
        d.setDate(d.getDate() + 7);
        const fmt = d.toLocaleDateString('en-IN', {
            day: '2-digit', month: 'short', year: 'numeric'
        });
        document.getElementById('followup-date').textContent = fmt;
        document.getElementById('followup-preview').style.display = 'block';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>