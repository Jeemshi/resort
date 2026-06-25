<?php
require_once __DIR__ . '/../includes/config.php';
if (isGuestLoggedIn()) redirect(SITE_URL . '/guest/bookings.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM guests WHERE email = ? AND status = 'active'");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $guest = $stmt->get_result()->fetch_assoc();

        // Always show success to prevent email enumeration
        if ($guest) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any existing unused tokens for this email
            $db->query("DELETE FROM password_resets WHERE email='".addslashes($email)."' AND user_type='guest'");

            $ins = $db->prepare("INSERT INTO password_resets (email, token, user_type, expires_at) VALUES (?, ?, 'guest', ?)");
            $ins->bind_param('sss', $email, $token, $expires);
            $ins->execute();

            // In a real app: send email. Here we expose the link for demo.
            $reset_link = SITE_URL . '/guest/reset_password.php?token=' . $token;
            $_SESSION['demo_reset_link_guest'] = $reset_link; // DEMO ONLY
        }

        $success = "If that email is registered, you'll receive a password reset link shortly. Check your inbox (and spam folder).";
    }
}

$page_title = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <!-- Left Panel -->
    <div class="auth-left">
        <div style="position:relative;z-index:1;max-width:420px;text-align:center">
            <div style="width:72px;height:72px;background:var(--ochre);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:white">
                <i class="fas fa-key"></i>
            </div>
            <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;color:white;margin-bottom:.75rem;line-height:1.1">
                Reset Your<br><span style="color:var(--ochre-light)">Password</span>
            </h1>
            <p style="font-size:.95rem;color:rgba(255,255,255,.65);line-height:1.7">
                No worries — it happens to the best of us. Enter your registered email and we'll send you a secure link to create a new password.
            </p>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-right">
        <div class="auth-box">
            <a href="<?= SITE_URL ?>/guest/login.php" style="display:flex;align-items:center;gap:.5rem;color:var(--text-light);font-size:.85rem;margin-bottom:2rem;font-family:var(--font-head);letter-spacing:.04em">
                <i class="fas fa-arrow-left fa-xs"></i> Back to Login
            </a>

            <div class="eyebrow" style="margin-bottom:.5rem">Guest Portal</div>
            <h2 class="auth-title">Forgot Password</h2>
            <p class="auth-subtitle">Enter your email address to receive a reset link.</p>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" style="background:#e6f4ea;color:#137333;border-left:4px solid #137333">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
            <?php if (!empty($_SESSION['demo_reset_link_guest'])): ?>
            <div style="background:#fff8e1;border:1px dashed #f0b429;border-radius:8px;padding:1rem;margin-top:.75rem;font-size:.82rem;color:#7a5300">
                <strong><i class="fas fa-flask"></i> Demo Mode — Reset Link:</strong><br>
                <a href="<?= htmlspecialchars($_SESSION['demo_reset_link_guest']) ?>" style="color:var(--canopy);word-break:break-all">
                    <?= htmlspecialchars($_SESSION['demo_reset_link_guest']) ?>
                </a>
            </div>
            <?php unset($_SESSION['demo_reset_link_guest']); endif; ?>
            <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem;margin-top:.5rem">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            <?php endif; ?>

            <div style="text-align:center;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--stone-dark)">
                <p style="font-size:.82rem;color:var(--text-light)">Remember your password? <a href="<?= SITE_URL ?>/guest/login.php" style="color:var(--canopy);font-weight:600">Sign in</a></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
