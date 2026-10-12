<?php
// Reservation Portal SSO entry point.
// Called from the main Logistic 2 system with a short‑lived token.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/connections.php'; // provides $conn (same DB as main system)

if (!isset($conn)) {
    header('Location: login.php?error=' . urlencode('Unable to connect to database for SSO.'));
    exit();
}

// Ensure SSO token table exists (shared with admin side)
try {
    $conn->query("CREATE TABLE IF NOT EXISTS reservation_sso_tokens (
        token VARCHAR(128) PRIMARY KEY,
        reservation_username VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        KEY idx_reservation_username (reservation_username),
        KEY idx_expires_at (expires_at),
        KEY idx_used (used)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    header('Location: login.php?error=' . urlencode('Unable to initialize SSO token storage.'));
    exit();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    header('Location: login.php?error=' . urlencode('Missing SSO token. Please sign in normally.'));
    exit();
}

// Look up and validate token
$stmt = $conn->prepare(
    "SELECT reservation_username 
     FROM reservation_sso_tokens 
     WHERE token = ? AND used = 0 AND expires_at > NOW() 
     LIMIT 1"
);
if (!$stmt) {
    header('Location: login.php?error=' . urlencode('Invalid SSO token. Please sign in normally.'));
    exit();
}

$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    header('Location: login.php?error=' . urlencode('This SSO link is invalid or has expired. Please sign in normally.'));
    exit();
}

$reservationUsername = $row['reservation_username'] ?? '';
if ($reservationUsername === '') {
    header('Location: login.php?error=' . urlencode('SSO token is incomplete. Please sign in normally.'));
    exit();
}

// Check if this is a login_tb account (prefixed with LOGIN_ID:)
if (strpos($reservationUsername, 'LOGIN_ID:') === 0) {
    // Extract login_id from prefix
    $loginId = (int)substr($reservationUsername, 9);
    
    // Load account from login_tb
    $stmt = $conn->prepare(
        "SELECT login_id, employee_username, employee_name, employee_position 
         FROM login_tb 
         WHERE login_id = ? 
         LIMIT 1"
    );
    if (!$stmt) {
        header('Location: login.php?error=' . urlencode('Unable to complete SSO login. Please sign in normally.'));
        exit();
    }
    
    $stmt->bind_param('i', $loginId);
    $stmt->execute();
    $res = $stmt->get_result();
    $loginAccount = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$loginAccount) {
        header('Location: login.php?error=' . urlencode('Login account not found.'));
        exit();
    }
    
    // Map employee_position to department (default to LOGISTIC2 for admin/dispatcher/fleet manager)
    $department = 'LOGISTIC2'; // Default department for main system users
    
    // Mark token as used
    $stmt = $conn->prepare("UPDATE reservation_sso_tokens SET used = 1 WHERE token = ?");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }
    
    // Establish Reservation Portal session using login_tb data
    // Use negative login_id to avoid conflicts with reservationaccount_tb.pk
    $_SESSION['user_id']   = -((int)$loginAccount['login_id']); // Negative to distinguish from reservation accounts
    $_SESSION['username']  = $loginAccount['employee_username'] ?? '';
    $_SESSION['department'] = $department;
    
} else {
    // Traditional reservation account (backward compatibility)
    $stmt = $conn->prepare(
        "SELECT pk, reservationAccount_username, reservationAccount_department 
         FROM reservationaccount_tb 
         WHERE reservationAccount_username = ? 
         LIMIT 1"
    );
    if (!$stmt) {
        header('Location: login.php?error=' . urlencode('Unable to complete SSO login. Please sign in normally.'));
        exit();
    }
    
    $stmt->bind_param('s', $reservationUsername);
    $stmt->execute();
    $res = $stmt->get_result();
    $account = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    
    if (!$account) {
        header('Location: login.php?error=' . urlencode('No Reservation Portal account is linked to this SSO user.'));
        exit();
    }
    
    // Mark token as used to prevent reuse
    $stmt = $conn->prepare("UPDATE reservation_sso_tokens SET used = 1 WHERE token = ?");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }
    
    // Establish Reservation Portal session
    $_SESSION['user_id']   = (int)($account['pk'] ?? 0);
    $_SESSION['username']  = $account['reservationAccount_username'] ?? '';
    $_SESSION['department'] = $account['reservationAccount_department'] ?? '';
}

// Redirect into the main Reservation Portal
header('Location: reservation.php');
exit();

