<?php
require_once '../config/db.php';
require_once '../includes/header.php';

$per_page    = 5;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

// total count for pagination
$total = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$total_pages = ceil($total / $per_page);


// Search
$search = trim($_GET['search'] ?? '');
$where  = '';
$params = [];

if ($search) {
    $where    = "WHERE p.name LIKE ? OR p.phone LIKE ?";
    $params   = ["%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT
        p.patient_id, p.name, p.dob, p.phone,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE())    AS age_years,
        CONCAT(
            TIMESTAMPDIFF(YEAR, p.dob, CURDATE()), ' yrs ',
            MOD(TIMESTAMPDIFF(MONTH, p.dob, CURDATE()), 12), ' m'
        )                                         AS age_full,
        DATE_FORMAT(p.join_date, '%d %b %Y')      AS join_formatted,

        
        DATE_FORMAT(p.join_date, '%M')            AS join_month,
        YEAR(p.join_date)                         AS join_year,

        COUNT(v.visit_id)                         AS total_visits
    FROM patients p
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    $where
    GROUP BY p.patient_id
    ORDER BY p.name ASC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$patients = $stmt->fetchAll();

?>


<div class="page-header d-flex justify-content-between align-items-center">
    <h2><i class="bi bi-people me-2"></i>All Patients</h2>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add Patient
    </a>
</div>
<!-- Search Bar -->
<div class="card p-3 mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control"
            placeholder="Search by name or phone..."
            value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-search"></i>
        </button>
        <?php if ($search): ?>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x"></i> Clear
            </a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="ps-3">#</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Full Age</th>
                    <th>Joined</th>
                    <th>Visits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $i => $p): ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                            <small class="text-muted"><?= $p['phone'] ?></small>
                        </td>
                        <td><?= $p['age_years'] ?> yrs</td>
                        <td><?= $p['age_full'] ?></td>
                        <td>
                            <div><?= $p['join_formatted'] ?></div>
                            <small class="text-muted"><?= $p['join_month'] ?> <?= $p['join_year'] ?></small>
                        </td>
                        <td>
                            <?php if ($p['total_visits'] == 0): ?>
                                <span class="badge badge-inactive">No visits</span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $p['total_visits'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="view.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $p['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </form>
                            <form method="POST" action="edit.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $p['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary me-1">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </form>
                            <form method="POST" action="../visits/add.php" style="display:inline">
                                <input type="hidden" name="patient_id"
                                    value="<?= $p['patient_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-plus-circle"></i>
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