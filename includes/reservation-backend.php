<?php
// Simple working backend for reservation portal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../../includes/connections.php';

// Generate next sequential Reservation_ID like RIDN-1000000, RIDN-1000001, ...
function generateNextReservationId($conn) {
    $base = 1000000;
    $sql = "SELECT MAX(CAST(SUBSTRING(Reservation_ID, 6) AS UNSIGNED)) AS maxnum
            FROM reservation_tb
            WHERE Reservation_ID LIKE 'RIDN-%'";
    $res = $conn->query($sql);
    $max = $base - 1;
    if ($res && ($row = $res->fetch_assoc()) && $row['maxnum'] !== null) {
        $max = (int)$row['maxnum'];
    }
    $next = $max + 1;
    return 'RIDN-' . $next;
}

// Function to capitalize text (first letter of each word)
function capitalizeText($text) {
    return ucwords(strtolower(trim($text)));
}

// Debug log
error_log('Backend accessed at: ' . date('Y-m-d H:i:s'));

// Handle cancel request
if (isset($_GET['cancel_request']) && !empty($_GET['cancel_request'])) {
    $requestId = $_GET['cancel_request'];
    $accountNumber = $_GET['account_number'] ?? '';
    
    if (isset($conn) && $conn && !empty($accountNumber)) {
        $requestIdEsc = $conn->real_escape_string($requestId);
        $accountNumberEsc = $conn->real_escape_string($accountNumber);
        
        // Update reservation status to Cancelled
        $cancelSql = "UPDATE reservation_tb 
                      SET Reservation_status = 'Cancelled' 
                      WHERE Reservation_ID = '$requestIdEsc' 
                      AND reservationAccount_number = '$accountNumberEsc'
                      AND Reservation_status = 'Pending'";
        
        if ($conn->query($cancelSql)) {
            if ($conn->affected_rows > 0) {
                $_SESSION['success_message'] = 'Reservation cancelled successfully!';
            } else {
                $_SESSION['error_message'] = 'Reservation not found or cannot be cancelled.';
            }
        } else {
            $_SESSION['error_message'] = 'Failed to cancel reservation: ' . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = 'Invalid request or database connection error.';
    }
    
    // Redirect back
    header('Location: ../reservation.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST data: ' . print_r($_POST, true));
    
    // Get form data and capitalize text fields
    $reservation_by_name = capitalizeText($_POST['reservation_by_name'] ?? '');
    $reservation_by_dept = $_POST['reservation_by_dept'] ?? ''; // Department is read-only, keep as is
    $reservation_purpose = $_POST['reservation_purpose'] ?? '';
    $reservation_urgency = $_POST['reservation_urgency'] ?? '';
    $reservation_description = $_POST['reservation_description'] ?? '';
    $reservation_location = capitalizeText($_POST['reservation_location'] ?? '');
    $reservation_address = capitalizeText($_POST['reservation_address'] ?? '');
    $estimated_departure = $_POST['estimated_departure'] ?? '';
    $estimated_arrival = $_POST['estimated_arrival'] ?? '';
    $estimated_distance = $_POST['estimated_distance'] ?? '';
    $estimated_time = $_POST['estimated_time'] ?? '';
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    $reservation_account_number = $_POST['reservation_account_number'] ?? '';
    $release_number = $_POST['release_number'] ?? '';
    
    // Items coming from release selection (arrays)
    $item_names = isset($_POST['item_name']) && is_array($_POST['item_name']) ? $_POST['item_name'] : [];
    $item_quantities = isset($_POST['released_quantity']) && is_array($_POST['released_quantity']) ? $_POST['released_quantity'] : [];
    
    // Generate reservation ID (deterministic, sequential)
    $reservation_id = generateNextReservationId($conn);
    
    // Simple insert query
    $sql = "INSERT INTO reservation_tb (
        Reservation_ID, 
        reservationAccount_number, 
        Reservation_byName, 
        Reservation_byDept, 
        Reservation_urgency, 
        Reservation_purpose, 
        Reservation_description, 
        Reservation_location, 
        Reservation_address, 
        Estimated_distance, 
        Estimated_time, 
        assigned_vehicle, 
        assigned_driver, 
        Estimated_departureTime, 
        Estimated_arrivalTime, 
        Reservation_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $driver_id = null; // No driver assignment for user reservations
        $status = 'Pending';
        
        $stmt->bind_param('ssssssssssssssss', 
            $reservation_id,
            $reservation_account_number,
            $reservation_by_name,
            $reservation_by_dept,
            $reservation_urgency,
            $reservation_purpose,
            $reservation_description,
            $reservation_location,
            $reservation_address,
            $estimated_distance,
            $estimated_time,
            $vehicle_id,
            $driver_id,
            $estimated_departure,
            $estimated_arrival,
            $status
        );
        
        // Execute with a small retry loop in case of a rare duplicate key race
        $attempts = 0; $ok = false; $lastErr = '';
        while ($attempts < 3) {
            if ($stmt->execute()) { $ok = true; break; }
            $lastErr = $stmt->error;
            if (strpos($lastErr, 'Duplicate') !== false) {
                $reservation_id = generateNextReservationId($conn);
                // rebind updated id
                $stmt->bind_param('ssssssssssssssss', 
                    $reservation_id,
                    $reservation_account_number,
                    $reservation_by_name,
                    $reservation_by_dept,
                    $reservation_urgency,
                    $reservation_purpose,
                    $reservation_description,
                    $reservation_location,
                    $reservation_address,
                    $estimated_distance,
                    $estimated_time,
                    $vehicle_id,
                    $driver_id,
                    $estimated_departure,
                    $estimated_arrival,
                    $status
                );
                $attempts++;
                continue;
            }
            break;
        }

        if ($ok) {
            error_log('Reservation inserted successfully: ' . $reservation_id);
            
            // If there is a release_number and at least one item, insert into items_tb
            if (!empty($release_number) && !empty($item_names)) {
                // Build pipe-separated strings for item names and quantities
                $namesJoined = [];
                $qtyJoined = [];
                
                foreach ($item_names as $idx => $name) {
                    $name = trim((string)$name);
                    if ($name === '') {
                        continue;
                    }
                    $qtyRaw = $item_quantities[$idx] ?? '0';
                    // Keep numeric-looking value as string; fallback to 0
                    $qty = is_numeric($qtyRaw) ? (string)$qtyRaw : '0';
                    
                    $namesJoined[] = $name;
                    $qtyJoined[] = $qty;
                }
                
                if (!empty($namesJoined)) {
                    $namesStr = implode('|', $namesJoined);
                    $qtyStr = implode('|', $qtyJoined);
                    
                    $itemSql = "INSERT INTO items_tb (reservation_id, release_number, item_name, item_quantity, item_status)
                                VALUES (?, ?, ?, ?, 'Pending')";
                    $itemStmt = $conn->prepare($itemSql);
                    if ($itemStmt) {
                        $itemStmt->bind_param(
                            'ssss',
                            $reservation_id,
                            $release_number,
                            $namesStr,
                            $qtyStr
                        );
                        
                        if (!$itemStmt->execute()) {
                            error_log('Failed to insert items_tb row for reservation ' . $reservation_id . ': ' . $itemStmt->error);
                        }
                        $itemStmt->close();
                    } else {
                        error_log('Prepare items_tb failed: ' . $conn->error);
                    }
                }
            }
            
            $_SESSION['success_message'] = 'Reservation created successfully!';
        } else {
            error_log('Insert failed: ' . $lastErr);
            $_SESSION['error_message'] = 'Failed to create reservation: ' . $lastErr;
        }
        $stmt->close();
    } else {
        error_log('Prepare failed: ' . $conn->error);
        $_SESSION['error_message'] = 'Database error: ' . $conn->error;
    }
} else {
    error_log('Not a POST request');
    $_SESSION['error_message'] = 'Invalid request method';
}

// Redirect back
header('Location: ../reservation.php');
exit;
?>