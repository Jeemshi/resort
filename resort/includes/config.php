<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wildnest_resort');

// Site Configuration
define('SITE_NAME', 'WildNest Resort');
define('SITE_URL', 'http://localhost/resort');
define('SITE_TAGLINE', 'Where Adventure Meets Luxury');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Helper Functions
function sanitize($data) {
    $db = getDB();
    return $db->real_escape_string(htmlspecialchars(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isGuestLoggedIn() {
    return isset($_SESSION['guest_id']);
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireGuestLogin() {
    if (!isGuestLoggedIn()) {
        redirect(SITE_URL . '/guest/login.php');
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'pending'   => 'badge-warning',
        'confirmed' => 'badge-success',
        'checked_in'=> 'badge-info',
        'checked_out'=> 'badge-secondary',
        'cancelled' => 'badge-danger',
        'paid'      => 'badge-success',
        'unpaid'    => 'badge-danger',
        'partial'   => 'badge-warning',
    ];
    return $badges[$status] ?? 'badge-secondary';
}
?>
