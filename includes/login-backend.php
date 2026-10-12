<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Check if we can connect to database
try {
    include '../../includes/connections.php';
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    header("Location: ../login.php?error=" . urlencode("Database connection error: " . $e->getMessage()));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get input values safely
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        header("Location: ../login.php?error=" . urlencode("Username and password are required."));
        exit();
    }

    // Debug: Check if table exists and has data
    $checkTable = $conn->query("SHOW TABLES LIKE 'reservationaccount_tb'");
    if ($checkTable->num_rows === 0) {
        header("Location: ../login.php?error=" . urlencode("Database table 'reservationaccount_tb' does not exist"));
        exit();
    }
    
    $countQuery = $conn->query("SELECT COUNT(*) as total FROM reservationaccount_tb");
    $countResult = $countQuery->fetch_assoc();
    if ($countResult['total'] == 0) {
        header("Location: ../login.php?error=" . urlencode("No accounts found in database. Please create accounts first."));
        exit();
    }

    // First, try to find in reservationaccount_tb
    $stmt = $conn->prepare("SELECT pk, reservationAccount_username, reservationAccount_email, reservationAccount_password, reservationAccount_department FROM reservationaccount_tb WHERE reservationAccount_username = ? OR reservationAccount_email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Check if password matches (currently stored as plain text in DB)
        if ($password === $user['reservationAccount_password']) {
            // Store session data
            $_SESSION['user_id'] = $user['pk'];
            $_SESSION['username'] = $user['reservationAccount_username'];
            $_SESSION['department'] = $user['reservationAccount_department'];

            // Redirect to reservation portal
            header("Location: ../reservation.php");
            exit();
        } else {
            header("Location: ../login.php?error=" . urlencode("Incorrect password."));
            exit();
        }
    }
    
    $stmt->close();
    
    // If not found in reservationaccount_tb, try login_tb (main system accounts)
    $stmt = $conn->prepare("SELECT login_id, employee_username, employee_email, employee_password, employee_position FROM login_tb WHERE employee_username = ? OR employee_email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check password (supports both plaintext and hashed)
        $passwordOk = false;
        if (!empty($user['employee_password'])) {
            if (function_exists('password_verify') && strlen($user['employee_password']) >= 32) {
                $passwordOk = password_verify($password, $user['employee_password']);
            } else {
                $passwordOk = ($password === $user['employee_password']);
            }
        }
        
        if ($passwordOk) {
            // Store session data (use negative login_id to distinguish from reservation accounts)
            $_SESSION['user_id'] = -((int)$user['login_id']);
            $_SESSION['username'] = $user['employee_username'];
            $_SESSION['department'] = 'LOGISTIC2'; // Default department for main system users
            
            // Redirect to reservation portal
            header("Location: ../reservation.php");
            exit();
        } else {
            header("Location: ../login.php?error=" . urlencode("Incorrect password."));
            exit();
        }
    }
    
    $stmt->close();
    
    // User not found in either table
    header("Location: ../login.php?error=" . urlencode("User not found."));
    exit();
    $conn->close();
} else {
    // Only show error for non-POST requests, not for initial page load
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header("Location: ../login.php?error=" . urlencode("Invalid request method."));
        exit();
    }
}
?>
