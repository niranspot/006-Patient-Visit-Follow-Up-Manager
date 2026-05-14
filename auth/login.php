<?php
session_start();

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, name, email, password, role
            FROM users
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            header('Location: ../index.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1f2e 0%, #2e3549 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-logo i {
            font-size: 48px;
            color: #e74c3c;
        }

        .login-logo h4 {
            font-weight: 700;
            color: #1a1f2e;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .login-logo p {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }

        .form-control {
            padding: 11px 14px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #4f8ef7;
            box-shadow: 0 0 0 3px rgba(79,142,247,0.15);
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
        }

        .btn-login {
            background: linear-gradient(135deg, #4f8ef7, #2563eb);
            border: none;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            width: 100%;
            transition: opacity 0.2s;
        }

        .btn-login:hover { opacity: 0.9; color: #fff; }

        .role-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .input-group-text {
            background: #f8f9fa;
            border-right: none;
            color: #6c757d;
        }

        .form-control.with-icon { border-left: none; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-logo">
        <img src="<?= BASE_URL ?>assets/img/cap" alt="Logo" style="width:48px; height:48px;">
        <h4>HealthCare System</h4>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" style="font-size:14px">
            <i class="bi bi-exclamation-circle"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-envelope"></i>
                </span>
                <input type="email" name="email" class="form-control with-icon"
                       placeholder="Enter your email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-lock"></i>
                </span>
                <input type="password" name="password" id="passwordInput"
                       class="form-control with-icon"
                       placeholder="Enter your password"
                       required>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="togglePassword()"
                        style="border-radius:0 8px 8px 0">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>

    
</div>

<script>
function togglePassword() {
    const input   = document.getElementById('passwordInput');
    const icon    = document.getElementById('eyeIcon');
    const isHidden = input.type === 'password';
    input.type    = isHidden ? 'text' : 'password';
    icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>

</body>
</html>