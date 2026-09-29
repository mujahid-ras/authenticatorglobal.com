<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Database credentials (adjust if needed)
$host = "localhost";
$db   = "adminag_clientlogin";
$user = "adminag_mujahid";
$pass = "9cKX@XQ,#(HR";

$error = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['admin_login_error'] = 'Please enter both username and password.';
        header('Location: login.php');
        exit;
    }

    // Fetch admin from database
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $_SESSION['admin_login_error'] = 'Invalid username or password.';
        header('Location: login.php');
        exit;
    }

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_login_error'] = 'Invalid username or password.';
        header('Location: login.php');
        exit;
    }

    // Success: set session variables
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];

    // Redirect to admin dashboard (index.php)
    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    // Log error (optional) and show generic message
    error_log("Admin login DB error: " . $e->getMessage());
    $_SESSION['admin_login_error'] = 'System error, please try again later.';
    header('Location: login.php');
    exit;
}
?>