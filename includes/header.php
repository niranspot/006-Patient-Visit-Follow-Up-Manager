<?php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

require_once dirname(__DIR__) . '/auth/check.php';



// require_once './config/db.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthcare Mini System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Mobile menu toggle button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="bi bi-list"></i>
    </button>

    <!-- Mobile overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <?php
    // Helper: detect active link - IMPROVED VERSION
    function isActive($file, $folder = '')
    {
        $currentFile = basename($_SERVER['PHP_SELF']);
        // Get the name of the folder the current file is in
        $currentDir = basename(dirname($_SERVER['PHP_SELF']));

        // If we are looking for a file in a specific folder
        if ($folder !== '') {
            return ($currentFile === $file && $currentDir === $folder) ? 'active' : '';
        }
        // If we are checking index.php (which is in the root, not a subfolder)
        return ($currentFile === $file) ? 'active' : '';
    }
    ?>

    <!-- ── Sidebar ── -->
    <div class="sidebar" id="sidebar">
        <!-- <div class="brand">
            <i class="bi bi-heart-pulse-fill brand-icon"></i>
            <span class="brand-name">HealthCare</span>
            <button class="toggle-btn" id="toggleBtn" title="Toggle sidebar">
                <i class="bi bi-chevron-left" id="toggleIcon"></i>
            </button>
        </div> -->
        <div class="brand">
            <!-- <i class="bi bi-heart-pulse-fill brand-icon"></i> -->
            <img src="<?= BASE_URL ?>assets/img/capmind" alt="Logo" class="brand-img">

            <span class="brand-name">Healthcare</span>

            <button class="toggle-btn" id="toggleBtn" title="Toggle sidebar">
                <i class="bi bi-chevron-left" id="toggleIcon"></i>
            </button>
        </div>

        <div class="sidebar-nav-container">
            <div class="nav-section">Main</div>
            <a href="<?= BASE_URL ?>index.php" class="nav-link <?= isActive('index.php') ?>" data-label="Dashboard">
                <i class="bi bi-speedometer2"></i>
                <span class="link-label">Dashboard</span>
            </a>

            <div class="nav-section">Patients</div>
            <a href="<?= BASE_URL ?>patients/list.php" class="nav-link <?= isActive('list.php', 'patients') ?>" data-label="All Patients">
                <i class="bi bi-people"></i>
                <span class="link-label">All Patients</span>
            </a>
            <a href="<?= BASE_URL ?>patients/add.php" class="nav-link <?= isActive('add.php', 'patients') ?>" data-label="Add Patient">
                <i class="bi bi-person-plus"></i>
                <span class="link-label">Add Patient</span>
            </a>

            <div class="nav-section">Visits</div>
            <a href="<?= BASE_URL ?>visits/list.php" class="nav-link <?= isActive('list.php', 'visits') ?>" data-label="All Visits">
                <i class="bi bi-calendar-check"></i>
                <span class="link-label">All Visits</span>
            </a>
            <a href="<?= BASE_URL ?>visits/add.php" class="nav-link <?= isActive('add.php', 'visits') ?>" data-label="Add Visit">
                <i class="bi bi-plus-circle"></i>
                <span class="link-label">Add Visit</span>
            </a>

            <div class="nav-section">Reports</div>
            <a href="<?= BASE_URL ?>reports/followups.php" class="nav-link <?= isActive('followups.php', 'reports') ?>" data-label="Follow-Ups">
                <i class="bi bi-clock-history"></i>
                <span class="link-label">Follow-Ups</span>
            </a>
            <a href="<?= BASE_URL ?>reports/monthly.php" class="nav-link <?= isActive('monthly.php', 'reports') ?>" data-label="Monthly">
                <i class="bi bi-calendar3"></i>
                <span class="link-label">Monthly</span>
            </a>
            <a href="<?= BASE_URL ?>reports/charts.php" class="nav-link <?= isActive('charts.php', 'reports') ?>" data-label="Charts">
                <i class="bi bi-bar-chart-line"></i>
                <span class="link-label">Charts</span>
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>reports/birthdays.php" class="nav-link <?= isActive('birthdays.php', 'reports') ?>" data-label="Birthdays">
                    <i class="bi bi-calendar-heart"></i>
                    <span class="link-label">Birthdays</span>
                </a>
                <a href="<?= BASE_URL ?>reports/summary.php" class="nav-link <?= isActive('summary.php', 'reports') ?>" data-label="Summary">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="link-label">Summary</span>
                </a>
            <?php endif; ?>
        </div>
        <div class="sidebar-footer" style="margin-top:auto; border-top:1px solid #2e3549; padding:16px 20px;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="background:#2e3549; border-radius:50%; width:34px; height:34px; 
                        display:flex; align-items:center; justify-content:center; flex-shrink:0">
                    <i class="bi bi-person-fill" style="color:#a0aec0; font-size:16px"></i>
                </div>
                <div class="link-label" style="overflow:hidden">
                    <div style="color:#fff; font-size:13px; font-weight:600; 
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis">
                        <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
                    </div>
                    <div style="font-size:11px">
                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <span style="color:#4f8ef7">● Admin</span>
                        <?php else: ?>
                            <span style="color:#10b981">● Staff</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>auth/logout.php" class="nav-link logout-link" data-label="Logout"
                style="padding:8px 0; color:#ef4444; margin:0;">
                <i class="bi bi-box-arrow-right" style="color:#ef4444"></i>
                <span class="link-label">Logout</span>
            </a>
        </div>
    </div>
    </div>
    </div>

    <!-- ── Main Content ── -->
    <div class="main-content" id="mainContent">
        <!-- Your page content will go here -->

        <script src="<?= BASE_URL ?>assets/js/index.js"></script>