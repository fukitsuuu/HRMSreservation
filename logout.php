<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// For the Reservation Portal, we only want to log the user out of the
// reservation session, not the entire main Logistic 2 session.
// Reservation login checks for user_id + username + department, so clearing
// the department flag is enough to treat the user as logged out here.

if (isset($_SESSION['department'])) {
    unset($_SESSION['department']);
}

// Optional: if you ever want to fully clear only reservation-side identifiers
// without touching a shared main-system session, you could unset additional
// keys that are used exclusively by the reservation module.
// For now we keep user_id/username in case they belong to the main system.

// Redirect back to Reservation login page
header("Location: login.php");
exit();
?>
