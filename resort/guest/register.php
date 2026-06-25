<?php
require_once __DIR__ . '/../includes/config.php';
if (isGuestLoggedIn()) redirect(SITE_URL . '/guest/bookings.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first  = sanitize($_POST['first_name'] ?? '');
    $last   = sanitize($_POST['last_name'] ?? '');
    $email  = sanitize($_POST['email'] ?? '');
    $phone  = sanitize($_POST['phone'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if (empty($first)||empty($last)||empty($email)||empty($pass)) {
        $error = 'Please fill in all required fields.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM guests WHERE email=?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO guests (first_name,last_name,email,phone,password) VALUES(?,?,?,?,?)");
            $stmt->bind_param('sssss', $first, $last, $email, $phone, $hashed);
            if ($stmt->execute()) {
                $gid = $db->insert_id;
                $_SESSION['guest_id']    = $gid;
                $_SESSION['guest_name']  = "$first $last";
                $_SESSION['guest_email'] = $email;
                redirect(SITE_URL . '/guest/bookings.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Account — WildNest Resort</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-left">
        <div style="position:relative;z-index:1;max-width:400px;text-align:center">
            <div style="width:72px;height:72px;background:var(--ochre);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:white"><i class="fas fa-mountain"></i></div>
            <h1 style="font-family:var(--font-display);font-size:2.2rem;font-weight:900;color:white;margin-bottom:.75rem;line-height:1.1">Join the<br><span style="color:var(--ochre-light)">WildNest Tribe.</span></h1>
            <p style="font-size:.95rem;color:rgba(255,255,255,.65);line-height:1.7">Create your free account to book your highland escape, access exclusive rates, and manage all your WildNest adventures in one place.</p>
        </div>
    </div>
    <div class="auth-right" style="overflow-y:auto">
        <div class="auth-box">
            <a href="<?= SITE_URL ?>/guest/login.php" style="display:flex;align-items:center;gap:.5rem;color:var(--text-light);font-size:.85rem;margin-bottom:2rem;font-family:var(--font-head)"><i class="fas fa-arrow-left fa-xs"></i> Back to Sign In</a>
            <div class="eyebrow" style="margin-bottom:.5rem">New Guest</div>
            <h2 class="auth-title">Create Account</h2>
            <p class="auth-subtitle">Already have one? <a href="<?= SITE_URL ?>/guest/login.php" style="color:var(--canopy);font-weight:600">Sign in here</a></p>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" placeholder="Juan" value="<?= htmlspecialchars($_POST['first_name']??'') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" placeholder="dela Cruz" value="<?= htmlspecialchars($_POST['last_name']??'') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+63 912 345 6789" value="<?= htmlspecialchars($_POST['phone']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="password2" class="form-control" placeholder="Repeat password" required>
                </div>
                <div style="font-size:.8rem;color:var(--text-light);margin-bottom:1.25rem">
                    By creating an account, you agree to our <a href="#" style="color:var(--canopy)">Terms of Service</a> and <a href="#" style="color:var(--canopy)">Privacy Policy</a>.
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem">
                    <i class="fas fa-user-plus"></i> Create My Account
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>