<?php
require_once __DIR__ . '/../includes/config.php';
if (isGuestLoggedIn()) redirect(SITE_URL . '/guest/bookings.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM guests WHERE email = ? AND status = 'active'");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $guest = $result->fetch_assoc();

        if ($guest && password_verify($password, $guest['password'])) {
            $_SESSION['guest_id']         = $guest['id'];
            $_SESSION['guest_name']       = $guest['first_name'] . ' ' . $guest['last_name'];
            $_SESSION['guest_email']      = $guest['email'];
            $redirect = $_GET['redirect'] ?? SITE_URL . '/guest/bookings.php';
            redirect($redirect);
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Subic Resort</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <!-- Left Panel -->
    <div class="auth-left">
        <div style="position:relative;z-index:1;max-width:420px;text-align:center">
            <div style="width:72px;height:72px;background:var(--ochre);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:white">
                <i class="fas fa-mountain"></i>
            </div>
            <h1 style="font-family:var(--font-display);font-size:2.5rem;font-weight:900;color:white;margin-bottom:.75rem;line-height:1.1">
                Welcome Back,<br><span style="color:var(--ochre-light)">Subician</span>
            </h1>
            <p style="font-size:1rem;color:rgba(255,255,255,.65);line-height:1.7;margin-bottom:2.5rem">
                Your highland refuge is waiting. Sign in to manage your bookings, explore room upgrades, and add adventure packages to your stay.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;text-align:left">
                <?php
                $perks = [
                    ['fa-calendar-check','Easy Booking','Reserve in minutes'],
                    ['fa-edit','Manage Stays','Modify or cancel anytime'],
                    ['fa-tag','Exclusive Rates','Member-only pricing'],
                    ['fa-headset','Priority Support','Direct line to our team'],
                ];
                foreach ($perks as $p): ?>
                <div style="background:rgba(255,255,255,.07);border-radius:12px;padding:1rem;display:flex;align-items:flex-start;gap:.75rem">
                    <i class="fas <?= $p[0] ?>" style="color:var(--ochre-light);margin-top:.1rem"></i>
                    <div>
                        <div style="font-family:var(--font-head);font-size:.78rem;font-weight:600;color:white;text-transform:uppercase;letter-spacing:.06em"><?= $p[1] ?></div>
                        <div style="font-size:.78rem;color:rgba(255,255,255,.5);margin-top:.2rem"><?= $p[2] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="auth-right">
        <div class="auth-box">
            <a href="<?= SITE_URL ?>/index.php" style="display:flex;align-items:center;gap:.5rem;color:var(--text-light);font-size:.85rem;margin-bottom:2rem;font-family:var(--font-head);letter-spacing:.04em">
                <i class="fas fa-arrow-left fa-xs"></i> Back to Home
            </a>

            <div class="eyebrow" style="margin-bottom:.5rem">Guest Portal</div>
            <h2 class="auth-title">Sign In</h2>
            <p class="auth-subtitle">Don't have an account? <a href="<?= SITE_URL ?>/guest/register.php" style="color:var(--canopy);font-weight:600">Create one free</a></p>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="display:flex;justify-content:space-between">
                        Password
                        <a href="<?= SITE_URL ?>/guest/forgot_password.php" style="color:var(--canopy);font-size:.8rem;font-weight:500">Forgot password?</a>
                    </label>
                    <div style="position:relative">
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Your password" required style="padding-right:2.5rem">
                        <button type="button" onclick="togglePassword()" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light);font-size:.9rem">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem">
                    <input type="checkbox" id="remember" name="remember" style="width:auto">
                    <label for="remember" style="font-size:.85rem;color:var(--text-mid);cursor:pointer">Keep me signed in</label>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem">
                    <i class="fas fa-sign-in-alt"></i> Sign In to Subic Resort
                </button>
            </form>

            <div style="text-align:center;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--stone-dark)">
                <p style="font-size:.82rem;color:var(--text-light)">Admin? <a href="<?= SITE_URL ?>/admin/login.php" style="color:var(--ochre);font-weight:600">Go to Admin Panel</a></p>
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'fas fa-eye-slash'; }
    else { f.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
</body>
</html>