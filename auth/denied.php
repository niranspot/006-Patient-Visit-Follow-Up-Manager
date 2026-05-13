<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
    </style>
</head>
<body>
<div class="text-center">
    <i class="bi bi-shield-lock" style="font-size:64px;color:#ef4444"></i>
    <h3 class="mt-3 fw-bold">Access Denied</h3>
    <p class="text-muted">You don't have permission to view this page.</p>
    <a href="../index.php" class="btn btn-primary">
        <i class="bi bi-house me-1"></i>Go to Dashboard
    </a>
</div>
</body>
</html>