<?php
require_once '../config/db.php';


$id = (int)($_POST['patient_id'] ?? 0);
if (!$id) {
    header('Location: list.php');
    exit;
}
require_once '../includes/header.php';
$errors  = [];
$success = '';

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) {
    header('Location: list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $dob       = trim($_POST['dob'] ?? '');
    $join_date = trim($_POST['join_date'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    // DOB validation via SQL
    if ($dob) {
        $dob_check = $pdo->prepare("
            SELECT CASE
                WHEN ? > CURDATE()    THEN 'future'
                WHEN ? < '1900-01-01' THEN 'invalid'
                ELSE 'valid'
            END AS status
        ");
        $dob_check->execute([$dob, $dob]);
        $dob_status = $dob_check->fetch()['status'];

        if ($dob_status === 'future')  $errors[] = 'Date of birth cannot be a future date.';
        if ($dob_status === 'invalid') $errors[] = 'Date of birth is invalid.';
    } else {
        $errors[] = 'Date of birth is required.';
    }

    if (!$name)      $errors[] = 'Name is required.';
    if (!$join_date) $errors[] = 'Join date is required.';

    if (empty($errors)) {
        $upd = $pdo->prepare("
            UPDATE patients
            SET name=?, dob=?, join_date=?, phone=?, address=?
            WHERE patient_id=?
        ");
        $upd->execute([$name, $dob, $join_date, $phone, $address, $id]);
        $success = 'Patient updated successfully.';

        // Refresh record
        $stmt->execute([$id]);
        $patient = $stmt->fetch();
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h2><i class="bi bi-pencil-square me-2"></i>Edit Patient</h2>
    <!-- <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Back to Profile</a> -->
    <form method="POST" action="view.php">
        <input type="hidden" name="patient_id"
            value="<?= $id ?>">
        <button type="submit" class="btn btn-outline-secondary me-1">Back to Profile</button>
    </form>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card p-4">

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
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
                <input type="hidden" name="patient_id" value="<?= $id ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                        value="<?= htmlspecialchars($patient['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control"
                        value="<?= $patient['dob'] ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Join Date <span class="text-danger">*</span></label>
                    <input type="date" name="join_date" class="form-control"
                        value="<?= $patient['join_date'] ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control"
                        value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control"
                        rows="3"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    
</div>

<?php require_once '../includes/footer.php'; ?>