<?php
require_once __DIR__ . '/../includes/config.php';
if (isAdminLoggedIn()) redirect(SITE_URL . '/admin/dashboard.php');

$error = '';
$success = '';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $db->prepare("SELECT id FROM admins WHERE email=? AND status='active'");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db->query("DELETE FROM password_resets WHERE email='".addslashes($email)."' AND user_type='admin'");

            $ins = $db->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, 'admin', ?)");
            $ins->bind_param('sss', $email, $token, $expires);
            $ins->execute();

            $reset_link = SITE_URL . '/admin/reset_password.php?token=' . $token;
            $_SESSION['demo_reset_link_admin'] = $reset_link;
        }

        $success = "If that email is registered, a reset link will be sent. Check your inbox.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body style="background:var(--jungle-dark);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem">
<div style="background:var(--white);width:100%;max-width:420px;padding:2.5rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.3)">
    <div style="text-align:center;margin-bottom:2rem">
        <div style="font-size:2.5rem;color:var(--jungle);margin-bottom:.5rem"><i class="fas fa-key"></i></div>
        <h2 style="font-family:var(--font-display);color:var(--text-dark)">Admin Password Reset</h2>
        <p style="font-size:.85rem;color:var(--text-light)">Management Console</p>
    </div>

    <?php if ($error): ?>
    <div style="background:#fce8e6;color:var(--ember);padding:.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;font-size:.85rem">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#e6f4ea;color:#137333;padding:.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:.85rem">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php if (!empty($_SESSION['demo_reset_link_admin'])): ?>
    <div style="background:#fff8e1;border:1px dashed #f0b429;border-radius:8px;padding:1rem;margin-bottom:1.5rem;font-size:.78rem;color:#7a5300">
        <strong><i class="fas fa-flask"></i> Demo Mode — Reset Link:</strong><br>
        <a href="<?= htmlspecialchars($_SESSION['demo_reset_link_admin']) ?>" style="color:var(--canopy);word-break:break-all">
            <?= htmlspecialchars($_SESSION['demo_reset_link_admin']) ?>
        </a>
    </div>
    <?php unset($_SESSION['demo_reset_link_admin']); endif; ?>
    <?php else: ?>
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Admin Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="Enter your admin email" required value="<?= htmlspecialchars($_POST['email']??'') ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem;margin-top:.5rem">
            <i class="fas fa-paper-plane"></i> Send Reset Link
        </button>
    </form>
    <?php endif; ?>

    <div style="text-align:center;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid #eee">
        <a href="<?= SITE_URL ?>/admin/login.php" style="font-size:.82rem;color:var(--text-light)">
            <i class="fas fa-arrow-left fa-xs"></i> Back to Admin Login
        </a>
    </div>
</div>
</body>
</html>
