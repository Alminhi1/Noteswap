<?php
require_once 'db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — NoteSwap</title>
    <!-- ANTI-FLASH: runs before page renders -->
    <script>
        const t = localStorage.getItem('theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    </script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
<button class="dark-toggle" onclick="toggleDark()" title="Toggle dark mode">🌙</button>

<a href="index.php" class="auth-logo">
    
    <span>NoteSwap</span>
</a>

<div class="auth-card">
    <h2>Welcome back</h2>
    <p class="subtitle">Log in to your account</p>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="yourname@email.com" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Your password" required>
        </div>
        <div style="text-align:right;margin:-8px 0 16px">
            <a href="forgot_password.php" style="font-size:13px;color:var(--primary);font-weight:600;text-decoration:none">
                Forgot password?
            </a>
        </div>
        <button type="submit" class="btn btn-primary">Log In</button>
    </form>

    <div class="auth-divider">or</div>
    <a href="register.php" class="btn btn-accent">Create an Account</a>
</div>

<script>
function toggleDark() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    document.querySelector('.dark-toggle').textContent = isDark ? '🌙' : '☀️';
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
}
const saved = localStorage.getItem('theme');
if (saved === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    document.querySelector('.dark-toggle').textContent = '☀️';
}
</script>
</body>
</html>
