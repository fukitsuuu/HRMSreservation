<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure a reservation user is logged in
function reservationCheckLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['department'])) {
        header("Location: login.php?error=" . urlencode("Please log in to access this page."));
        exit();
    }
}

// Get the current reservation user data
function getReservationUser() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'department' => $_SESSION['department'] ?? ''
        ];
    }
    return null;
}

?>


