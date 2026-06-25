<?php
require_once __DIR__ . '/../includes/config.php';

// If already logged in, skip login screen
if (isAdminLoggedIn()) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? ''); // This reads what they type in the top box
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        
        // FIX 1: Look inside the 'email' column instead of 'username'
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = ? AND status = 'active'");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin) {
            // FIX 2: Check both hashed passwords AND plain text fallback passwords
            if (password_verify($password, $admin['password']) || $password === $admin['password']) {
                $_SESSION['admin_id'] = $admin['id'];
                // Use 'name' or 'email' as the fallback username identifier
                $_SESSION['admin_username'] = $admin['name'] ?? $admin['email'];
                redirect(SITE_URL . '/admin/dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Subic Resort</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body style="background: var(--jungle-dark); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem;">

<div style="background: var(--white); width: 100%; max-width: 420px; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="font-size: 2.5rem; color: var(--jungle); margin-bottom: 0.5rem;"><i class="fas fa-mountain"></i></div>
        <h2 style="font-family: var(--font-display); color: var(--text-dark);">Subic Resort Admin</h2>
        <p style="font-size: 0.85rem; color: var(--text-light);">Management Console</p>
    </div>

    <?php if ($error): ?>
        <div style="background: #fce8e6; color: var(--ember); padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="text" name="username" class="form-control" placeholder="Enter admin email" required>
        </div>
        <div class="form-group" style="margin-bottom: 2rem;">
            <label class="form-label" style="display:flex;justify-content:space-between">
                Password
                <a href="<?= SITE_URL ?>/admin/forgot_password.php" style="color:var(--canopy);font-size:.8rem;font-weight:500">Forgot password?</a>
            </label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
            <i class="fas fa-lock-open"></i> Authenticate
        </button>
    </form>
</div>

</body>
</html>