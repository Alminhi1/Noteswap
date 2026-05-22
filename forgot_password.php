<?php
require_once 'db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if ($email === "" || $name === "" || $password === "" || $confirm === "") {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND name = ? LIMIT 1");
            $stmt->execute([$email, $name]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([$hashed, $user['id']]);
                $success = "Password has been reset. You can now log in.";
            } else {
                $error = "No account found with this email and full name.";
            }
        } catch (Exception $e) {
            $error = "Password reset is not available right now. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - NoteSwap</title>
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
    <h2>Reset password</h2>
    <p class="subtitle">No email needed. Verify your account and set a new password.</p>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?>
            <a href="login.php" style="color:inherit;font-weight:700;margin-left:6px">Log in →</a>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" placeholder="Enter your registered full name" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your registered email" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" placeholder="At least 6 characters" required>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat new password" required>
        </div>
        <button type="submit" class="btn btn-primary">Reset Password</button>
    </form>

    <div class="auth-divider">or</div>
    <a href="login.php" class="btn btn-accent">Back to Login</a>
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
