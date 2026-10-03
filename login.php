<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$errParam = $_GET['error'] ?? '';
if ($errParam === 'banned') $error = 'Hesabınız banlanmıştır. Yönetici ile iletişime geçin.';
elseif ($errParam === 'expired') $error = 'Üyelik süreniz dolmuştur. Yönetici ile iletişime geçin.';
elseif ($errParam === 'nomembership') $error = 'Aktif üyeliğiniz bulunmamaktadır.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hearo — Login</title>
    <link rel="icon" href="xeff/assets/logo.png" type="image/png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500&family=Inter:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="xeff/css/elite.css">
    <link rel="stylesheet" href="xeff/assets/theme.css">
</head>
<body>
    <div class="bg-glow"></div>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-tagline">Login to your premium account</p>
            </div>

            <?php if ($error): ?>
            <div class="error-banner"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <div class="input-group">
                    <label class="input-label">Email Address / Username</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username" class="input-field" placeholder="hearo" required autofocus>
                    </div>
                </div>
                <div class="input-group">
                    <label class="input-label">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" class="input-field" placeholder="••••••••••••" required>
                        <button type="button" class="pw-toggle" id="pwToggle"><i class="fa-regular fa-eye" id="pwEye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="authBtn">Sign In</button>
            </form>

            <div class="footer-links">
                Don't have an account? <a href="#">Sign up</a>
            </div>
            <a href="#" class="back-link">← Back to Home</a>
        </div>
    </div>

    <script>
        const pw = document.getElementById('password');
        const toggle = document.getElementById('pwToggle');
        const eye = document.getElementById('pwEye');
        let shown = false;
        toggle.addEventListener('click', () => {
            shown = !shown;
            pw.type = shown ? 'text' : 'password';
            eye.className = shown ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        });
        document.getElementById('loginForm').addEventListener('submit', () => {
            const btn = document.getElementById('authBtn');
            btn.innerHTML = 'Verifying...';
            btn.style.opacity = '0.75';
            btn.style.pointerEvents = 'none';
        });
    </script>
</body>
</html>
