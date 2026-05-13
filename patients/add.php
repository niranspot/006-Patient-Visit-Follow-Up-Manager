<?php
require_once '../config/db.php';
require_once '../includes/header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $dob      = trim($_POST['dob'] ?? '');
    $join_date= trim($_POST['join_date'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    // ── Validation: DOB must not be future, must be real
    if ($dob) {
        $dob_check = $pdo->prepare("
            SELECT
                CASE
                    WHEN ? > CURDATE() THEN 'future'
                    WHEN ? < '1900-01-01' THEN 'invalid'
                    ELSE 'valid'
                END AS status
        ");
        $dob_check->execute([$dob, $dob]);
        $dob_status = $dob_check->fetch()['status'];

        if ($dob_status === 'future') $errors[] = 'Date of birth cannot be a future date.';
        if ($dob_status === 'invalid') $errors[] = 'Date of birth is invalid.';
    } else {
        $errors[] = 'Date of birth is required.';
    }

    if (!$name)      $errors[] = 'Name is required.';
    if (!$join_date) $errors[] = 'Join date is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO patients (name, dob, join_date, phone, address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $dob, $join_date, $phone, $address]);
        $success = 'Patient added successfully.';
    }
}
?>

<div class="page-header">
    <h2><i class="bi bi-person-plus me-2"></i>Add Patient</h2>
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
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control"
                           value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" required>
                    <div class="form-text">Must be a real past date. Leap year dates (Feb 29) are supported.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Join Date <span class="text-danger">*</span></label>
                    <input type="date" name="join_date" class="form-control"
                           value="<?= htmlspecialchars($_POST['join_date'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Add Patient
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    
</div>

<?php require_once '../includes/footer.php'; ?>