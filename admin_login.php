<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit();
}
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid credentials or not an admin account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — NoteSwap</title>
    <script>const t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);</script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        [data-theme="dark"] body { background:#0f0e17; }
        [data-theme="light"] body { background:#f0f4f8; }
        .admin-login-wrap {
            min-height:100vh; display:flex;
            align-items:center; justify-content:center; padding:20px;
        }
        .admin-login-card {
            width:100%; max-width:400px;
            border-radius:16px; padding:40px;
            border:1px solid;
        }
        [data-theme="dark"]  .admin-login-card { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .admin-login-card { background:#ffffff;  border-color:#e5e7eb; }
        .admin-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(239,68,68,0.12); color:#f87171;
            border:1px solid rgba(239,68,68,0.3);
            padding:4px 14px; border-radius:50px;
            font-size:12px; font-weight:700; margin-bottom:20px;
        }
        .admin-title {
            font-size:24px; font-weight:800; margin-bottom:6px;
            font-family:'Plus Jakarta Sans',sans-serif;
        }
        [data-theme="dark"]  .admin-title { color:#f3f0ff; }
        [data-theme="light"] .admin-title { color:#111827; }
        .admin-sub { font-size:13px; margin-bottom:28px; }
        [data-theme="dark"]  .admin-sub { color:#8b82a7; }
        [data-theme="light"] .admin-sub { color:#9ca3af; }
    </style>
</head>
<body>
<div class="admin-login-wrap">
    <div class="admin-login-card">
        <div class="admin-badge">🔐 Admin Access Only</div>
        <h1 class="admin-title">NoteSwap Admin</h1>
        <p class="admin-sub">Sign in with your admin account</p>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:16px">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#8b82a7">Email</label>
                <input type="email" name="email" class="pf-input" style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid #2e2a42;background:#0f0e17;color:#f3f0ff;font-size:14px;outline:none;margin-top:6px" placeholder="admin@email.com" required>
            </div>
            <div class="form-group" style="margin-top:14px">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#8b82a7">Password</label>
                <input type="password" name="password" style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid #2e2a42;background:#0f0e17;color:#f3f0ff;font-size:14px;outline:none;margin-top:6px" placeholder="Your password" required>
            </div>
            <button type="submit" style="width:100%;margin-top:20px;padding:13px;border:none;border-radius:12px;background:linear-gradient(90deg,#dc2626,#ef4444);color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif">
                🔐 Sign In as Admin
            </button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:13px;color:#8b82a7">
            <a href="login.php" style="color:#7c3aed;text-decoration:none">← Back to Student Login</a>
        </p>
    </div>
</div>
</body>
</html>