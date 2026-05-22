<?php
require_once 'db.php';
session_start();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm = $_POST["confirm_password"];

    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "This email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashed]);
            $success = "Account created! You can now log in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — NoteSwap</title>
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
    <span class="logo-icon">📓</span>
    <span>NoteSwap</span>
</a>

<div class="auth-card">
    <h2>Create Account</h2>
    <p class="subtitle">Join NoteSwap and start sharing notes</p>

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
            <input type="text" name="name" placeholder="Your full name" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="yourname@email.com" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="At least 6 characters" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat your password" required>
        </div>
        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>

    <div class="auth-divider">or</div>
    <a href="login.php" class="btn btn-accent">Already have an account?</a>
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