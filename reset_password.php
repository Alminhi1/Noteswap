<?php
require_once 'db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = "";
$success = "";
$valid_reset = null;

function ensurePasswordResetTableForReset($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

try {
    ensurePasswordResetTableForReset($pdo);

    if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
        $error = "This reset link is invalid.";
    } else {
        $token_hash = hash('sha256', $token);
        $stmt = $pdo->prepare("
            SELECT password_resets.*, users.email
            FROM password_resets
            JOIN users ON users.id = password_resets.user_id
            WHERE password_resets.token_hash = ?
              AND password_resets.used_at IS NULL
              AND password_resets.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token_hash]);
        $valid_reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$valid_reset) {
            $error = "This reset link is expired or has already been used.";
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && $valid_reset) {
        $password = $_POST["password"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";

        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$hashed, $valid_reset['user_id']]);
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                ->execute([$valid_reset['id']]);
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                ->execute([$valid_reset['user_id']]);

            $success = "Password updated. You can now log in.";
            $valid_reset = null;
            $token = "";
        }
    }
} catch (Exception $e) {
    $error = "Password reset is not available right now. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - NoteSwap</title>
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
    <h2>New password</h2>
    <p class="subtitle">Choose a fresh password for your account.</p>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?>
            <a href="login.php" style="color:inherit;font-weight:700;margin-left:6px">Log in →</a>
        </div>
    <?php endif; ?>

    <?php if ($valid_reset): ?>
    <form method="POST" action="">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" placeholder="At least 6 characters" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat new password" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
    <?php endif; ?>

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
