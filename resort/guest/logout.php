<?php
// 1. Initialize the session to gain access to it
require_once __DIR__ . '/../includes/config.php';

// 2. Unset all session variables
$_SESSION = array();

// 3. If cookies are used for the session, destroy the session cookie as well
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 4. Finally, completely destroy the session on the server
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 5. Redirect the user back to the login page
header("Location: " . SITE_URL . "/guest/login.php");
exit;
?>