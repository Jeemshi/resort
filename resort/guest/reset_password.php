<?php
require_once __DIR__ . '/../includes/config.php';
if (isGuestLoggedIn()) redirect(SITE_URL . '/guest/bookings.php');

$db = getDB();
$token = sanitize($_GET['token'] ?? '');
$error = '';
$success = '';
$valid_token = null;

if ($token) {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token=? AND user_type='guest' AND used=0 AND expires_at > NOW()");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $valid_token = $stmt->get_result()->fetch_assoc();
}

if (!$valid_token) {
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

        $upd = $db->prepare("UPDATE guests SET password=? WHERE email=?");
        $upd->bind_param('ss', $hashed, $email);
        if ($upd->execute()) {
            $db->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute() || true;
            $stmt2 = $db->prepare("UPDATE password_resets SET used=1 WHERE token=?");
            $stmt2->bind_param('s', $token);
            $stmt2->execute();
            $success = 'Your password has been reset successfully. You can now log in.';
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
    <title>Reset Password — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-left">
        <div style="position:relative;z-index:1;max-width:420px;text-align:center">
            <div style="width:72px;height:72px;background:var(--ochre);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:white">
                <i class="fas fa-lock-open"></i>
            </div>
            <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;color:white;margin-bottom:.75rem">
                Create New<br><span style="color:var(--ochre-light)">Password</span>
            </h1>
            <p style="color:rgba(255,255,255,.65);line-height:1.7">Choose a strong, unique password you haven't used before.</p>
        </div>
    </div>

    <div class="auth-right">
        <div class="auth-box">
            <div class="eyebrow" style="margin-bottom:.5rem">Guest Portal</div>
            <h2 class="auth-title">Set New Password</h2>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success" style="background:#e6f4ea;color:#137333;border-left:4px solid #137333">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
            <div style="text-align:center;margin-top:1.5rem">
                <a href="<?= SITE_URL ?>/guest/login.php" class="btn btn-primary" style="justify-content:center">
                    <i class="fas fa-sign-in-alt"></i> Sign In Now
                </a>
            </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div style="position:relative">
                        <input type="password" name="password" id="pw1" class="form-control" placeholder="Min. 8 characters" required style="padding-right:2.5rem">
                        <button type="button" onclick="togglePw('pw1','eye1')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light)"><i class="fas fa-eye" id="eye1"></i></button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1.5rem">
                    <label class="form-label">Confirm New Password</label>
                    <div style="position:relative">
                        <input type="password" name="confirm_password" id="pw2" class="form-control" placeholder="Repeat password" required style="padding-right:2.5rem">
                        <button type="button" onclick="togglePw('pw2','eye2')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light)"><i class="fas fa-eye" id="eye2"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
            <?php elseif (!$success): ?>
            <div style="text-align:center;margin-top:1.5rem">
                <a href="<?= SITE_URL ?>/guest/forgot_password.php" class="btn btn-primary" style="justify-content:center">Request New Link</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function togglePw(id, eyeId) {
    var f = document.getElementById(id), e = document.getElementById(eyeId);
    f.type = f.type === 'password' ? 'text' : 'password';
    e.className = f.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
