<?php
require_once __DIR__ . '/../includes/config.php';
if (isAdminLoggedIn()) redirect(SITE_URL . '/admin/dashboard.php');

$db = getDB();
$token = sanitize($_GET['token'] ?? '');
$error = '';
$success = '';
$valid_token = null;

if ($token) {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token=? AND user_type='admin' AND used=0 AND expires_at > NOW()");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $valid_token = $stmt->get_result()->fetch_assoc();
}

if (!$valid_token && empty($success)) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $pw1 = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';

    if (strlen($pw1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($pw1, PASSWORD_DEFAULT);
        $email  = $valid_token['email'];

        $upd = $db->prepare("UPDATE admins SET password=? WHERE email=?");
        $upd->bind_param('ss', $hashed, $email);
        if ($upd->execute()) {
            $stmt2 = $db->prepare("UPDATE password_resets SET used=1 WHERE token=?");
            $stmt2->bind_param('s', $token);
            $stmt2->execute();
            $success = 'Admin password has been reset. You can now log in with your new password.';
            $valid_token = null;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body style="background:var(--jungle-dark);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem">
<div style="background:var(--white);width:100%;max-width:420px;padding:2.5rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.3)">
    <div style="text-align:center;margin-bottom:2rem">
        <div style="font-size:2.5rem;color:var(--jungle);margin-bottom:.5rem"><i class="fas fa-lock-open"></i></div>
        <h2 style="font-family:var(--font-display);color:var(--text-dark)">Set New Password</h2>
        <p style="font-size:.85rem;color:var(--text-light)">Management Console</p>
    </div>

    <?php if ($error): ?>
    <div style="background:#fce8e6;color:var(--ember);padding:.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;font-size:.85rem">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        <div style="margin-top:.75rem"><a href="<?= SITE_URL ?>/admin/forgot_password.php" style="color:var(--ember);font-weight:600">Request a new link</a></div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div style="background:#e6f4ea;color:#137333;padding:.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;font-size:.85rem">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <a href="<?= SITE_URL ?>/admin/login.php" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem">
        <i class="fas fa-sign-in-alt"></i> Go to Admin Login
    </a>
    <?php endif; ?>

    <?php if ($valid_token): ?>
    <form method="POST">
        <div class="form-group">
            <label class="form-label">New Password</label>
            <div style="position:relative">
                <input type="password" name="password" id="pw1" class="form-control" placeholder="Min. 8 characters" required style="padding-right:2.5rem">
                <button type="button" onclick="tp('pw1','e1')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light)"><i class="fas fa-eye" id="e1"></i></button>
            </div>
        </div>
        <div class="form-group" style="margin-bottom:2rem">
            <label class="form-label">Confirm New Password</label>
            <div style="position:relative">
                <input type="password" name="confirm_password" id="pw2" class="form-control" placeholder="Repeat password" required style="padding-right:2.5rem">
                <button type="button" onclick="tp('pw2','e2')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light)"><i class="fas fa-eye" id="e2"></i></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem">
            <i class="fas fa-save"></i> Reset Admin Password
        </button>
    </form>
    <?php endif; ?>
</div>
<script>
function tp(id, eid) {
    var f=document.getElementById(id), e=document.getElementById(eid);
    f.type=f.type==='password'?'text':'password';
    e.className=f.type==='password'?'fas fa-eye':'fas fa-eye-slash';
}
</script>
</body>
</html>
