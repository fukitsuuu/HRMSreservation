<?php
include 'includes/session_check.php';
reservationCheckLogin();
include __DIR__ . '/../includes/connections.php'; // provides $conn for driver/vehicle selects

$currentUser = getReservationUser();

// Compute avatar initial from reservationaccount_tb name (first letter uppercased)
$avatarInitial = 'U';
$userDisplayName = $currentUser['username'] ?? 'User'; // fallback
$userAccountNumber = '0'; // fallback
if (isset($conn) && $conn) {
    $userId = $currentUser['id'] ?? null;
    $username = $currentUser['username'] ?? null;
    
    // Check if this is a login_tb account (negative user_id)
    if ($userId && $userId < 0) {
        // Get user info from login_tb
        $loginId = abs($userId);
        $loginIdEsc = $conn->real_escape_string($loginId);
        $q = "SELECT employee_name FROM login_tb WHERE login_id = '$loginIdEsc' LIMIT 1";
        $r = $conn->query($q);
        if ($r && $r->num_rows > 0) {
            $nameRow = $r->fetch_assoc();
            $name = trim($nameRow['employee_name'] ?? '');
            if ($name !== '') {
                $userDisplayName = $name;
                if (function_exists('mb_substr')) {
                    $avatarInitial = strtoupper(mb_substr($name, 0, 1, 'UTF-8'));
                } else {
                    $avatarInitial = strtoupper(substr($name, 0, 1));
                }
            }
        }
    } elseif ($username) {
        // Traditional reservation account - get from reservationaccount_tb
        $usernameEsc = $conn->real_escape_string($username);
        $q = "SELECT reservationAccount_name, reservationAccount_number FROM reservationaccount_tb WHERE reservationAccount_username = '$usernameEsc' LIMIT 1";
        $r = $conn->query($q);
        if ($r && $r->num_rows > 0) {
            $nameRow = $r->fetch_assoc();
            $name = trim($nameRow['reservationAccount_name'] ?? '');
            $accountNumber = trim($nameRow['reservationAccount_number'] ?? '');
            if ($name !== '') {
                $userDisplayName = $name;
                if (function_exists('mb_substr')) {
                    $avatarInitial = strtoupper(mb_substr($name, 0, 1, 'UTF-8'));
                } else {
                    $avatarInitial = strtoupper(substr($name, 0, 1));
                }
            }
            if ($accountNumber !== '') {
                $userAccountNumber = $accountNumber;
            }
        }
    }
}

// Handle view request (moved after userAccountNumber is defined)
$viewReservation = null;
if (isset($_GET['view_request']) && !empty($_GET['view_request'])) {
    $requestId = $_GET['view_request'];
    if (isset($conn) && $conn) {
        $requestIdEsc = $conn->real_escape_string($requestId);
        $accountNumberEsc = $conn->real_escape_string($userAccountNumber);
        
        $viewSql = "SELECT 
                        r.*,
                        v.brand_name, v.model_name, v.vehicle_image
                    FROM reservation_tb r
                    LEFT JOIN vehiclelist_tb v ON r.assigned_vehicle = v.vehicle_number
                    WHERE r.Reservation_ID = '$requestIdEsc' 
                    AND r.reservationAccount_number = '$accountNumberEsc'
                    LIMIT 1";
        
        $viewResult = $conn->query($viewSql);
        if ($viewResult && $viewResult->num_rows > 0) {
            $viewReservation = $viewResult->fetch_assoc();
            
            // Fetch items for this reservation from items_tb
            $itemsSql = "SELECT release_number, item_name, item_quantity FROM items_tb WHERE reservation_id = '$requestIdEsc' LIMIT 1";
            $itemsResult = $conn->query($itemsSql);
            if ($itemsResult && $itemsResult->num_rows > 0) {
                $itemsRow = $itemsResult->fetch_assoc();
                $viewReservation['release_number'] = $itemsRow['release_number'] ?? '';
                $viewReservation['item_names'] = !empty($itemsRow['item_name']) ? explode('|', $itemsRow['item_name']) : [];
                $viewReservation['item_quantities'] = !empty($itemsRow['item_quantity']) ? explode('|', $itemsRow['item_quantity']) : [];
            } else {
                $viewReservation['release_number'] = '';
                $viewReservation['item_names'] = [];
                $viewReservation['item_quantities'] = [];
            }
        }
    }
}

// Get release_numbers already used in items_tb so we can hide them in the dropdown
$usedReleaseNumbers = [];
if (isset($conn) && $conn) {
    $usedSql = "SELECT DISTINCT release_number FROM items_tb";
    if ($usedRes = $conn->query($usedSql)) {
        while ($row = $usedRes->fetch_assoc()) {
            if (!empty($row['release_number'])) {
                $usedReleaseNumbers[] = $row['release_number'];
            }
        }
        $usedRes->free();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Portal | Logistic 2</title>
    <link rel="icon" href="../images/logo2.png" type="image/x-icon">
    <link href="../css/tailwind.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .topnavbar {
            background-color: #1f2937ff;
            color: #ffffffff;
        }
        /* General scrollbar improvements */
        .main-content {
            scrollbar-gutter: stable;
        }

        .content-box {
            overflow-y: overlay;
        }

        /* Match FVM table aesthetics */
        table.min-w-full.table-auto {
            border-collapse: separate;
            border-spacing: 0;
        }
        table.min-w-full.table-auto thead th {
            font-size: 0.875rem; /* text-sm */
            text-transform: none;
        }
        table.min-w-full.table-auto tbody tr:nth-child(even) {
            background-color: #f9fafb; /* even zebra */
        }
        table.min-w-full.table-auto tbody tr:hover {
            background-color: #f3f4f6; /* hover */
        }
        table.min-w-full.table-auto th,
        table.min-w-full.table-auto td {
            vertical-align: top; /* align like FVM */
        }
        /* Action column: tighten button spacing (7th col in admin; here action is 6th) */
        #requestsTableBody td:nth-child(6) {
            display: flex;
            align-items: center;
            gap: 0.25rem; /* 4px */
            white-space: nowrap;
        }
        #requestsTableBody td:nth-child(6) > * { margin: 0 !important; }
        #requestsTableBody td:nth-child(6) button,
        #requestsTableBody td:nth-child(6) a {
            font-size: 0.875rem !important; /* text-sm */
            padding: 0.25rem 0.75rem !important; /* py-1 px-3 */
            line-height: 1.25 !important;
            border-radius: 0.375rem !important; /* rounded-md */
            min-height: 0 !important;
        }

        /* Invisible scrollbar for view and update request modals */
        #viewRequestModal .modal-container {
            margin-bottom: 0;
            max-height: 95vh;
            overflow-y: scroll;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        #viewRequestModal .modal-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        #approveRequestModal .modal-container {
            margin-bottom: 0;
            max-height: 95vh;
            overflow-y: scroll;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        #approveRequestModal .modal-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        #rejectRequestModal .modal-container {
            margin-bottom: 0;
            max-height: 95vh;
            overflow-y: scroll;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        #rejectRequestModal .modal-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        /* Desktop: modal scrolls as one, footer at end */
        #addRequestModal .modal-container {
            margin-bottom: 0;
            max-height: 92vh;
            overflow-y: auto;
        }
        /* Hide scrollbar for the left side column in Add Request modal */
        #addRequestModal .overflow-y-auto {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        #addRequestModal .overflow-y-auto::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        /* Status badges */
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }

        /* Map styles */
        #map {
            height: 320px;
            width: 100%;
            border-radius: 0.5rem;
            z-index: 1;
            min-height: 320px;
            position: relative;
        }
        /* Leaflet popup styling - wider bubble with softer tail */
        .leaflet-popup.popup-wide .leaflet-popup-content-wrapper,
        .leaflet-popup .leaflet-popup-content-wrapper.popup-wide { border-radius: 12px; }
        .popup-wide .leaflet-popup-content { min-width: 150px; white-space: normal; }
        .map-container { position: relative; height: 320px; width: 100%; }
        .map-controls { position: absolute; z-index: 30; padding: 8px; }
        .location-info { background: white; padding: 12px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 12px; }

        /* Urgency levels */
        .urgency-low { background-color: #d1fae5; color: #065f46; }
        .urgency-normal { background-color: #fef3c7; color: #92400e; }
        .urgency-high { background-color: #fee2e2; color: #991b1b; }
        .urgency-urgent { background-color: #fecaca; color: #7f1d1d; }

        /* Mobile-view styling */
        @media only screen and (max-width: 767px) {
            .main-content { max-width: 100%; margin: 0 auto; }
            /* Content box adjustments */
            .content-box { padding: 1rem; }
            /* Page title adjustments */
            .text-2xl.font-semibold.text-gray-800.mb-4 { font-size: 1.5rem; margin-bottom: 1rem; }
            /* Subtitle adjustments */
            .text-xl.font-semibold.text-gray-800 { font-size: 1.25rem; }
            /* Table responsive handling */
            .overflow-x-auto { -webkit-overflow-scrolling: touch; }
            /* Controls section - stack vertically on mobile */
            .flex.flex-col.md\:flex-row.justify-between.md\:items-center.gap-4.mb-4 { gap: 1rem; }
            .flex.flex-col.sm\:flex-row.items-start.sm\:items-center.gap-2 { gap: 0.75rem; }
            /* Modal adjustments for mobile */
            #addRequestModal .modal-container {
                max-height: 95vh;
                overflow-y: auto;
                margin-top: 50px;
                margin-bottom: 50px;
                padding-bottom: 20px;
            }
            /* Mobile-specific performance optimizations */
            .modal-container { transform: translateZ(0); will-change: transform; }
            /* Touch-friendly improvements */
            button:not(.topnavbar button), select, input { min-height: 44px; }
            /* Driver and Vehicle info sections - stack vertically on mobile */
            .grid.grid-cols-1.md\:grid-cols-3.gap-4.mb-4 { grid-template-columns: 1fr !important; gap: 1.5rem !important; }
            .grid.grid-cols-1.md\:grid-cols-2.gap-4.mb-4 { grid-template-columns: 1fr !important; gap: 1.5rem !important; }
            /* Estimated time/distance grid - stack vertically on mobile */
            .grid.grid-cols-3.gap-4 { grid-template-columns: 1fr; gap: 1rem; }
            /* Map container adjustments */
            .map-container { height: 250px; }
            #map { height: 250px; min-height: 250px; }
            /* Address suggestions positioning */
            #address-suggestions { max-height: 60vh; }
            /* Pagination adjustments */
            .flex.flex-col.sm\:flex-row.justify-between.sm\:justify-end.items-start.sm\:items-center.gap-2.sm\:gap-4 { gap: 1rem; }
            /* Action buttons spacing */
            .space-x-1.flex.items-center.justify-center { flex-direction: column; gap: 0.5rem; }
            .space-x-1.flex.items-center.justify-center button { width: 100%; margin: 0; }
            /* Form field improvements for mobile */
            .block.mb-1.text-sm.font-medium.text-gray-700 { margin-bottom: 0.5rem; }
            /* Image upload areas - make them more touch-friendly */
            .w-32.h-32.bg-gray-100.border-2.border-dashed.border-gray-300.rounded-lg { width: 100%; height: 120px; min-height: 120px; }
            /* Modal form improvements */
            #addRequestForm .space-y-4 { gap: 1.5rem; }
            #addRequestForm .mb-4 { margin-bottom: 1.5rem; }
            /* Better spacing for form sections */
            hr.my-4 { margin: 1.5rem 0; }
            /* Modal footer button improvements */
            .flex.justify-end.gap-3.mt-6 { flex-direction: column; gap: 0.75rem; }
            .flex.justify-end.gap-3.mt-6 button { width: 100%; }
            /* Table responsive improvements */
            table.min-w-full.table-auto { font-size: 1rem; }
            table.min-w-full.table-auto th, table.min-w-full.table-auto td { padding: 0.75rem 0.5rem; }
            table.min-w-full.table-auto th { padding: 0.75rem 0.75rem; font-size: 0.875rem; white-space: nowrap; }
            /* Table cell wrapping for mobile — avoid breaking text into letters */
            table.min-w-full.table-auto td { word-break: normal; overflow-wrap: break-word; max-width: none; }
            /* Keep ID, Status and Action columns on a single line */
            table.min-w-full.table-auto th:nth-child(2),
            table.min-w-full.table-auto td:nth-child(2),
            table.min-w-full.table-auto th:nth-child(6),
            table.min-w-full.table-auto td:nth-child(6) { white-space: nowrap; }
            /* Better mobile table scrolling */
            .overflow-x-auto { -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none; }
            .overflow-x-auto::-webkit-scrollbar { display: none; }
            /* Map controls positioning for mobile */
            .map-controls.absolute.bottom-4.right-4.z-10 { bottom: 1rem; right: 1rem; }
            /* Remove marker button positioning for mobile */
            .absolute.top-4.right-4.z-40 { top: 1rem; right: 1rem; }
            /* Ensure modal container is relative for absolute positioning */
            #addRequestModal .modal-container { position: relative !important; }
            .yourlocation-btn { position: absolute; top: 5px; right: 5px; }
        }

        /* Desktop-specific modal heights and scrolling */
        @media screen and (min-width: 768px) {
            #addRequestModal .modal-container { max-height: 92vh; overflow-y: auto; }
            #addRequestModal .grid.grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr 1fr; gap: 2rem; }
            #addRequestModal .grid.grid-cols-1.lg\:grid-cols-2>div:first-child { overflow-y: auto; max-height: calc(90vh - 200px); }
            #addRequestModal .grid.grid-cols-1.lg\:grid-cols-2>div:last-child { overflow-y: auto; max-height: calc(90vh - 200px); }
            #viewRequestModal .modal-container,
            #approveRequestModal .modal-container,
            #rejectRequestModal .modal-container { max-height: 80vh; overflow-y: auto; }
            /* Match view modal height with add modal on desktop */
            #viewRequestModal .modal-container { max-height: 92vh; }
        }
    </style>
</head>

<body class="bg-gray-100">
    <!-- Top Navbar -->
    <div class="topnavbar fixed top-0 left-0 w-full h-20 border-b border-gray-600 z-60 shadow-sm select-none">
        <div class="flex items-center justify-start h-full px-4">

            <!-- Logo & Title -->
            <div class="flex items-center space-x-3 ml-5 md:ml-40">
                <img src="../images/logo.png" alt="Logo" class="h-16 object-contain" onerror="this.style.display='none'">
            </div>

            <!-- Right Icons -->
            <div class="flex items-center space-x-6 ml-auto mr-5 md:mr-40">

                <!-- User Avatar -->
                <div class="relative">
                    <button id="userDropdownBtn" onclick="toggleUserDropdown()" class="buttonRight w-8 h-8 rounded-sm bg-blue-600 text-white flex items-center justify-center font-bold focus:outline-none">
                        <?= htmlspecialchars($avatarInitial); ?>
                    </button>
                    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded shadow-lg z-50 text-sm">
                        <div class="px-4 pt-2 text-gray-800 font-semibold cursor-default"><?= htmlspecialchars($userDisplayName); ?></div>
                        <div class="px-4 text-gray-500 cursor-default"><?= htmlspecialchars($currentUser['department']); ?></div>
                        <hr class="my-1 border-gray-200">
                        <a href="logout.php" class="block px-4 py-2 hover:bg-red-100 text-red-600 font-semibold">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container mx-auto my-20 px-4 py-8">
        <h1 class="text-2xl font-semibold text-gray-800 mb-4">Reservation Portal</h1>
        
        <div class="content-box bg-white rounded-lg shadow p-6">
            <div class="bg-white rounded-lg shadow p-6 overflow-x-auto">
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-4 select-none w-full">
                    <h2 class="text-xl font-semibold text-gray-800 select-none">My Reservation</h2>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 w-full sm:w-auto">
                        <button onclick="openAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 w-full sm:w-auto">
                            <i class="fas fa-plus mr-2"></i>Add Reservation
                        </button>
                        <select id="sortSelect" class="border border-gray-300 rounded px-3 py-1 text-sm outline-none w-full sm:w-auto">
                            <option value="">Sort by</option>
                            <option value="requestid">Reservation ID</option>
                            <option value="location">Location</option>
                            <option value="date">Date</option>
                            <option value="status">Status</option>
                        </select>
                        <div class="flex items-center justify-center w-full sm:w-auto">
                            <button id="sortAscBtn" title="Sort Ascending" class="ml-1 px-2 py-1 border border-gray-300 rounded hover:bg-gray-200" aria-label="Sort Ascending">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button id="sortDescBtn" title="Sort Descending" class="ml-2 px-2 py-1 border border-gray-300 rounded hover:bg-gray-200" aria-label="Sort Descending">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                        </div>
                        <input type="text" id="searchInput" placeholder="Search..." class="border border-gray-300 rounded px-3 py-1 text-sm outline-none w-full sm:w-auto" />
                    </div>
                </div>
                <!-- Reservations Table -->
                <table class="min-w-full table-auto border border-gray-200 divide-y divide-gray-200">
                    <thead class="bg-gray-800 text-white select-none">
                        <tr>
                            <th class="px-4 py-2 border-r text-center w-14">#</th>
                            <th class="px-4 py-2 border-r text-left w-36">Reservation ID</th>
                            <th class="px-4 py-2 border-r text-left w-64">Reservation Location</th>
                            <th class="px-4 py-2 border-r text-left w-40">Reservation Date</th>
                            <th class="px-4 py-2 border-r text-left w-24">Status</th>
                            <th class="px-4 py-2 text-left w-28">Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody" class="divide-y divide-gray-200">
                        <?php
                        // Fetch real data from database for the logged-in user
                        $reservations = [];
                        $rowNumber = 1;
                        
                        if (isset($conn) && $conn) {
                            // Query reservations for the current user's account number
                            $accountNumberEsc = $conn->real_escape_string($userAccountNumber);
                            
                            $sql = "SELECT 
                                        Reservation_ID,
                                        Reservation_location,
                                        Estimated_departureTime,
                                        Reservation_status,
                                        date_added
                                    FROM reservation_tb 
                                    WHERE reservationAccount_number = '$accountNumberEsc'
                                    ORDER BY date_added DESC";
                            
                            $result = $conn->query($sql);
                            
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $reservations[] = [
                                        'id' => $row['Reservation_ID'],
                                        'location' => $row['Reservation_location'],
                                        'date' => $row['Estimated_departureTime'] ? date('Y-m-d H:i', strtotime($row['Estimated_departureTime'])) : date('Y-m-d H:i', strtotime($row['date_added'])),
                                        'status' => $row['Reservation_status']
                                    ];
                                }
                            }
                        }
                        ?>
                        
                        <?php
                        // Display reservations
                        if (!empty($reservations)) {
                            foreach ($reservations as $r):
                                $status = strtolower($r['status']);
                                $statusClass = 'text-gray-600';
                                if ($status === 'pending') {
                                    $statusClass = 'text-yellow-600';
                                } elseif ($status === 'approved') {
                                    $statusClass = 'text-green-600';
                                } elseif ($status === 'rejected') {
                                    $statusClass = 'text-red-600';
                                } elseif ($status === 'cancelled') {
                                    $statusClass = 'text-red-600';
                                }
                        ?>
                        <tr class="odd:bg-white even:bg-gray-50 hover:bg-gray-100">
                            <td class="px-4 py-2 border-r text-center"><?= $rowNumber++; ?></td>
                            <td class="px-4 py-2 border-r"><?= htmlspecialchars($r['id']); ?></td>
                            <td class="px-4 py-2 border-r"><?= htmlspecialchars($r['location']); ?></td>
                            <td class="px-4 py-2 border-r"><?= htmlspecialchars($r['date']); ?></td>
                            <td class="px-4 py-2 border-r font-semibold <?= $statusClass ?>"><?= htmlspecialchars($r['status']); ?></td>
                            <td class="px-4 py-2 space-x-1 flex items-center justify-center select-none">
                                <a href="?view_request=<?= urlencode($r['id']); ?>" class="bg-green-500 text-white rounded-md px-3 py-1 text-sm hover:bg-green-600 inline-block text-center">View</a>
                                <?php if ($status === 'pending'): ?>
                                <button onclick="openCancelModal('<?= htmlspecialchars($r['id']); ?>')" class="bg-red-500 text-white rounded-md px-3 py-1 text-sm hover:bg-red-600 inline-block text-center">Cancel</button>
                                <?php else: ?>
                                <span class="bg-gray-400 text-white rounded-md px-3 py-1 text-sm inline-block text-center">Cancel</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        }
                        ?>
                    </tbody>
                </table>
                <!-- Empty state (shown when there are no reservations) -->
                <div id="emptyState" class="flex flex-col items-center justify-center py-12 px-6 <?= empty($reservations) ? '' : 'hidden' ?>">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H9M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No requests found</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new vehicle request.</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row justify-between sm:justify-end items-start sm:items-center gap-2 sm:gap-4 select-none w-full">
                    <span id="pageIndicator" class="text-gray-600 text-sm sm:text-right w-full sm:w-auto"></span>
                    <div id="pagination" class="flex flex-wrap items-center space-x-1"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Reservation Modal -->
    <div id="addRequestModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="modal-container bg-white w-full max-w-2xl mx-auto rounded-lg shadow-lg p-6 relative overflow-y-auto max-h-[90vh]">

            <!-- Modal Title -->
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 mt-2 sm:mt-0">Add New Reservation</h2>
                <button onclick="closeAddModal()" class="text-gray-600 hover:text-gray-800 text-2xl font-bold">&times;</button>
            </div>

            <!-- Page Navigation -->
            <div class="flex items-end justify-center gap-4 mt-4 mb-4 border-b border-gray-200">
                <button id="page1Btn" onclick="showPage(1)" class="px-4 pt-3 pb-2 text-sm font-medium border-b-2 border-blue-500 text-blue-600 bg-blue-50 rounded-t-lg">
                    Page 1: Reservation Details
                </button>
                <button id="page2Btn" onclick="showPage(2)" class="px-4 pt-3 pb-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 rounded-t-lg">
                    Page 2: Location & Map
                </button>
            </div>

            <form id="addRequestForm" method="POST" action="includes/reservation-backend.php">
                <!-- Page 1: Left Side Input Fields -->
                <div id="page1" class="page-content">
                    <div class="pr-4 space-y-4">

                        <input type="text" name="reservation_account_number" value="<?= htmlspecialchars($userAccountNumber); ?>" hidden>
                        <!-- Reservation Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Reservation by <span class="text-red-500">*</span></label>
                                <input type="text" name="reservation_by_name" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500 capitalize">
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Department <span class="text-red-500">*</span></label>
                                <input type="text" name="reservation_by_dept" value="<?= htmlspecialchars($currentUser['department'] ?? ''); ?>" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500 bg-gray-100" readonly>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Purpose and Urgency Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Purpose <span class="text-red-500">*</span></label>
                                <select name="reservation_purpose" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500">
                                    <option value="" hidden>Select Purpose</option>
                                    <option value="Guest Shuttle Service">Guest Shuttle Service</option>
                                    <option value="Supply Pickup">Supply Pickup</option>
                                    <option value="Supply Delivery">Supply Delivery</option>
                                    <option value="VIP / Executive Transport">VIP / Executive Transport</option>
                                    <option value="Staff Transport">Staff Transport</option>
                                    <option value="Maintenance Support">Maintenance Support</option>
                                    <option value="Admin Errand">Admin Errand</option>
                                    <option value="Waste Disposal">Waste Disposal</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Urgency <span class="text-red-500">*</span></label>
                                <select name="reservation_urgency" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500">
                                    <option value="" hidden>Select Urgency</option>
                                    <option value="Low">Low</option>
                                    <option value="Normal">Normal</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Release Information Row -->
                        <div class="mb-4">
                            <div class="mb-3">
                                <label class="block mb-1 text-sm font-medium text-gray-700">Release Number</label>
                                <select id="release_number_select" name="release_number" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500">
                                    <option value="" hidden>Select Release Number</option>
                                </select>
                            </div>
                            <!-- Items Container -->
                            <div id="release_items_container" class="hidden">
                                <label class="block mb-2 text-sm font-medium text-gray-700">Items</label>
                                <div id="release_items_list" class="space-y-2">
                                    <!-- Items will be dynamically added here -->
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Description Row -->
                        <div class="mb-4">
                            <label class="block mb-1 text-sm font-medium text-gray-700">Description</label>
                            <textarea id="reservation_description" name="reservation_description" rows="3" maxlength="400" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500 resize-none" placeholder="Optional description..." oninput="updateCharCount('reservation_description','vrdsDescCount')"></textarea>
                            <div class="text-right text-xs text-gray-500 mt-1">
                                <span id="vrdsDescCount">400</span> characters remaining
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Vehicle Image Row -->
                        <div class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-gray-700">Vehicle Image</label>
                            <div class="flex justify-center">
                                <div class="w-32 h-32 bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden">
                                    <div id="vehicleImagePlaceholder" class="text-center text-gray-500">
                                        <i class="fas fa-car text-2xl mb-1"></i>
                                        <p class="text-xs">Vehicle Image</p>
                                    </div>
                                    <img id="vehicleImagePreview" src="" alt="Vehicle Image" class="hidden w-full h-full object-cover rounded-lg" />
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Vehicle Information Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Vehicle Type <span class="text-red-500">*</span></label>
                                <select name="vehicle_type" id="vehicle_type_field" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500">
                                    <option value="" hidden>Select Vehicle Type</option>
                                    <option value="Light Commercial Vehicle">Light Commercial Vehicle</option>
                                    <option value="Cargo Van">Cargo Van</option>
                                    <option value="Box Truck">Box Truck</option>
                                    <option value="Refrigerated Truck">Refrigerated Truck</option>
                                    <option value="Pickup Truck">Pickup Truck</option>
                                    <option value="Waste Collection Vehicle">Waste Collection Vehicle</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Vehicle ID Number <span class="text-red-500">*</span></label>
                                <select name="vehicle_id" id="vehicle_id_select" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500" disabled>
                                    <option value="" disabled selected hidden data-role="placeholder">Select Vehicle ID</option>
                                    <?php
                                    if (isset($conn) && $conn) {
                                        $vehicles_sql = "SELECT vehicle_number, brand_name, model_name, vehicle_type, vehicle_image FROM vehiclelist_tb WHERE status = 'Available' ORDER BY vehicle_number";
                                        $vehicles_result = $conn->query($vehicles_sql);
                                        if ($vehicles_result && $vehicles_result->num_rows > 0) {
                                            while ($v = $vehicles_result->fetch_assoc()) {
                                                $vehicle_name = trim(($v['brand_name'] ?? '') . ' ' . ($v['model_name'] ?? ''));
                                                $vehicle_type = $v['vehicle_type'] ?? '';
                                                $vehicle_image = $v['vehicle_image'] ?? '';
                                                echo '<option value="' . htmlspecialchars($v['vehicle_number']) . '" data-name="' . htmlspecialchars($vehicle_name) . '" data-type="' . htmlspecialchars($vehicle_type) . '" data-image="' . htmlspecialchars($vehicle_image) . '" data-vtype="' . htmlspecialchars($vehicle_type) . '">' . htmlspecialchars($v['vehicle_number']) . '</option>';
                                            }
                                        } else {
                                            echo '<option disabled>No available vehicles</option>';
                                        }
                                    } else {
                                        echo '<option disabled>DB connection error</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Vehicle Name</label>
                                <input type="text" name="vehicle_name" id="vehicle_name_field" readonly class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none bg-gray-100 text-gray-600">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page 2: Right Side Map Section -->
                <div id="page2" class="page-content hidden">
                    <div class="space-y-4 pr-2">

                        <!-- MAPS -->
                        <div class="map-container relative">
                            <div id="map" style="height: 320px; width: 100%; border-radius: 0.5rem;"></div>
                            <div class="map-controls absolute bottom-4 right-4 z-10">
                                <!--<button id="getLocationBtn" type="button" onclick="getCurrentLocation()" class="yourlocation-btn bg-white text-blue-500 p-2 rounded-full shadow-lg hover:bg-gray-50 transition-colors border border-gray-200">
                                    <svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" />
                                        <circle cx="12" cy="12" r="3" fill="currentColor" />
                                        <line x1="12" y1="2" x2="12" y2="4" stroke="currentColor" stroke-width="2" />
                                        <line x1="12" y1="20" x2="12" y2="22" stroke="currentColor" stroke-width="2" />
                                        <line x1="2" y1="12" x2="4" y2="12" stroke="currentColor" stroke-width="2" />
                                        <line x1="20" y1="12" x2="22" y2="12" stroke="currentColor" stroke-width="2" />
                                    </svg>
                                </button>-->
                            </div>
                            
                        </div>

                        <hr class="border-gray-300">

                        <!-- Reservation Address -->
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700">Reservation Address</label>
                            <div class="relative">
                                <input type="text" id="reservation_address" name="reservation_address" class="w-full border border-gray-300 rounded px-3 py-2 pr-10 text-sm outline-none focus:border-blue-500" placeholder="Search for address on map" autocomplete="off">
                                <input type="hidden" id="reservation_address_joined" name="reservation_address_joined" value="">
                                <button type="button" onclick="triggerAddressSuggestions()" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-black p-1.5 rounded text-sm hover:bg-gray-200 transition-colors">
                                    <i class="fas fa-search"></i>
                                </button>
                                <!-- Suggestion list container -->
                                <div id="address-suggestions" class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-lg shadow-lg z-40 max-h-48 overflow-y-auto hidden" style="z-index: 9999;">
                                    <!-- Suggestions will be populated here -->
                                </div>
                            </div>
                            <!-- Waypoints list (restored) -->
                            <div class="mt-3">
                                <div id="waypointsList" class="space-y-2"></div>

                            </div>
                        </div>

                        <hr class="border-gray-300">

                        <!-- Reservation Location -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Reservation Location</label>
                                <input type="text" id="reservation_location" name="reservation_location" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500 capitalize" placeholder="Enter location name" required>
                            </div>
                            <div>
                                <label class="block mb-1 text-sm font-medium text-gray-700">Departure Time</label>
                                <input type="datetime-local" id="estimated_departure" name="estimated_departure" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none focus:border-blue-500" autocomplete="off" required>
                            </div>
                        </div>

                        <!-- Estimated Times and Distance -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div class="md:col-span-2">
                                <label class="block mb-1 text-sm font-medium text-gray-700">Estimated Arrival</label>
                                <input type="datetime-local" id="estimated_arrival" name="estimated_arrival" class="w-full border border-gray-300 rounded px-3 py-2 text-sm outline-none bg-gray-100 text-gray-600" readonly>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block mb-1 text-sm font-medium text-gray-700">Estimated Travel Time</label>
                                <span id="estimated_travel_time" class="block w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 text-gray-600">-- hours</span>
                                <input type="hidden" name="estimated_time" id="hidden_estimated_time" value="">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block mb-1 text-sm font-medium text-gray-700">Estimated Distance</label>
                                <span id="estimated_distance" class="block w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 text-gray-600">-- km</span>
                                <input type="hidden" name="estimated_distance" id="hidden_estimated_distance" value="">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Action buttons - at the end of scrolling content -->
                <div class="flex flex-wrap items-center justify-between order-2 mt-4 mb-4 w-full">
                    <!-- Page Navigation Buttons -->
                    <div class="flex gap-2 order-1 mb-4">
                        <button id="prevBtn" type="button" onclick="previousPage()" class="bg-gray-500 hover:bg-gray-600 text-white font-medium px-4 py-2 rounded text-sm hidden">
                            <i class="fas fa-arrow-left mr-2"></i>Previous
                        </button>
                        <button id="nextBtn" type="button" onclick="nextPage()" class="bg-blue-500 hover:bg-blue-600 text-white font-medium px-4 py-2 rounded text-sm">
                            Next<i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>

                    <!-- Form Action Buttons -->
                    <div id="formActions" class="flex gap-2 order-2 ml-auto mb-4">
                        <button onclick="closeAddModal()" type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium px-4 py-2 rounded text-sm">Cancel</button>
                        <button type="submit" form="addRequestForm" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded text-sm">Submit Request</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- View Request Modal -->
    <div id="viewRequestModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="modal-container bg-white w-full max-w-2xl mx-auto rounded-lg shadow-lg p-6 relative overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between p-2 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Request Details</h3>
                <button onclick="closeViewModal()" class="text-gray-600 hover:text-gray-800 text-2xl font-bold">&times;</button>
            </div>
                <!-- Top row: Reservation ID | Date Added | Urgency -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reservation ID Number</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_request_id_input" value="<?= htmlspecialchars($viewReservation['Reservation_ID'] ?? '') ?>" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date Added</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_date_created_input" value="<?= htmlspecialchars($viewReservation['date_added'] ?? '') ?>" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Urgency</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_urgency_input" value="<?= htmlspecialchars($viewReservation['Reservation_urgency'] ?? '') ?>" />
                    </div>
                </div>

                <hr class="my-2">

                <!-- Estimated Distance | Estimated Travel Time -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estimated Distance</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_estimated_distance" value="<?= htmlspecialchars($viewReservation['Estimated_distance'] ?? '') ?>" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estimated Travel Time</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_estimated_time" value="<?= htmlspecialchars($viewReservation['Estimated_time'] ?? '') ?>" />
                    </div>
                </div>

                <!-- Map area -->
                <div class="border rounded-lg p-3 my-4 outline-none">
                    <div id="view_map" style="height: 320px; width: 100%; border-radius: 0.5rem;"></div>
                </div>

                <!-- Reservation Location | Departure / Arrival | Address (incrementing) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reservation Location</label>
                    <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_reservation_location" value="<?= htmlspecialchars($viewReservation['Reservation_location'] ?? '') ?>" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Departure Time</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_departure_time" value="<?= htmlspecialchars($viewReservation['Estimated_departureTime'] ?? '') ?>" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Arrival Time</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_arrival_time" value="<?= htmlspecialchars($viewReservation['Estimated_arrivalTime'] ?? '') ?>" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reservation Address</label>
                    <div id="view_reservation_address_list" class="space-y-2">
                        <?php
                        $addr = $viewReservation['Reservation_address'] ?? '';
                        if ($addr !== '') {
                            $parts = array_map('trim', explode('|', str_replace(' | ', '|', $addr)));
                            foreach ($parts as $p) {
                                if ($p === '') continue;
                                echo '<input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" value="' . htmlspecialchars($p) . '">';
                            }
                        }
                        ?>
                    </div>
                </div>

                <hr class="my-2">

                <!-- Purpose -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Purpose</label>
                    <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_purpose" value="<?= htmlspecialchars($viewReservation['Reservation_purpose'] ?? '') ?>" />
                </div>
                
                <!-- Release Number -->
                <?php if (!empty($viewReservation['release_number'])): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Release Number</label>
                    <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" value="<?= htmlspecialchars($viewReservation['release_number']) ?>" />
                </div>
                
                <!-- Items -->
                <?php 
                $itemNames = $viewReservation['item_names'] ?? [];
                $itemQuantities = $viewReservation['item_quantities'] ?? [];
                if (!empty($itemNames) && count($itemNames) > 0): 
                ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Items</label>
                    <div class="space-y-2">
                        <?php 
                        $maxItems = max(count($itemNames), count($itemQuantities));
                        for ($i = 0; $i < $maxItems; $i++): 
                            $name = trim($itemNames[$i] ?? '');
                            $qty = trim($itemQuantities[$i] ?? '0');
                            if ($name === '') continue;
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center border border-gray-200 rounded px-3 py-2 bg-gray-50">
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-700 mr-2">Item Name:</span>
                                <span class="text-sm text-gray-900"><?= htmlspecialchars($name) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-700 mr-2">Released Quantity:</span>
                                <span class="text-sm text-gray-900"><?= htmlspecialchars($qty) ?> Kg</span>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea readonly class="w-full bg-gray-100 h-24 border border-gray-300 rounded px-3 py-2 text-sm outline-none resize-none cursor-default" id="view_description"><?=
                        htmlspecialchars($viewReservation['Reservation_description'] ?? '')
                    ?></textarea>
                </div>

                <hr class="my-2">

                <!-- Reservation by | Department -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reservation by</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_requester_name_input" value="<?= htmlspecialchars($viewReservation['Reservation_byName'] ?? '') ?>" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <input type="text" readonly class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2 text-sm outline-none cursor-default" id="view_department" value="<?= htmlspecialchars($viewReservation['Reservation_byDept'] ?? '') ?>" />
                    </div>
                </div>

                <!-- Cards: Driver | Vehicle (with thumbnail images like the reference) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 my-4">
                    <!-- Driver Card -->
                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0">
                                <div class="w-full h-full bg-gray-300 flex items-center justify-center">
                                    <i class="fas fa-user text-gray-500"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Driver ID: <span class="text-gray-700"><?= htmlspecialchars($viewReservation['assigned_driver'] ?? 'Not assigned') ?></span>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <?php 
                                    if (!empty($viewReservation['assigned_driver'])) {
                                        echo htmlspecialchars($viewReservation['assigned_driver']);
                                    } else {
                                        echo 'No driver assigned';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vehicle Card -->
                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0">
                                <?php 
                                $vehicleImagePath = '';
                                if (!empty($viewReservation['vehicle_image'])) {
                                    // The database stores paths like '../database/uploads/filename.jpg'
                                    // Since database/uploads is outside public_html, we need to use a PHP script to serve images
                                    $imagePath = $viewReservation['vehicle_image'];
                                    
                                    // Extract just the filename from the path
                                    $filename = basename($imagePath);
                                    
                                    // Use the image serving script with cache busting
                                    $vehicleImagePath = 'get_image.php?file=' . urlencode($filename) . '&t=' . time();
                                }
                                if (!empty($vehicleImagePath)): ?>
                                    <!-- Debug: Initial path is <?= htmlspecialchars($vehicleImagePath) ?> -->
                                    <img src="<?= htmlspecialchars($vehicleImagePath) ?>" alt="Vehicle" class="w-full h-full object-cover cursor-pointer hover:opacity-80 transition-opacity" 
                                         onload="console.log('Image loaded successfully:', this.src)" 
                                         onerror="console.log('Image failed to load:', this.src); tryAlternativeImagePath(this, '<?= htmlspecialchars($imagePath ?? '') ?>')" 
                                         onclick="openImageLightbox(this.src)">
                                    <div class="w-full h-full bg-gray-300 flex items-center justify-center" style="display: none;">
                                        <i class="fas fa-car text-gray-500"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-car text-gray-500"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Vehicle ID: <span class="text-gray-700"><?= htmlspecialchars($viewReservation['assigned_vehicle'] ?? 'Not assigned') ?></span>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <?php 
                                    if (!empty($viewReservation['assigned_vehicle'])) {
                                        $vehicleName = trim($viewReservation['brand_name'] . ' ' . $viewReservation['model_name']);
                                        echo htmlspecialchars($vehicleName ?: $viewReservation['assigned_vehicle']);
                                    } else {
                                        echo 'No vehicle assigned';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keep original status badges (hidden containers for compatibility) -->
                <div class="hidden">
                    <span id="view_status"></span>
                    <span id="view_urgency"></span>
                    <p id="view_date_created"></p>
                </div>

                <div class="flex justify-end items-end items-center mt-2">
                    <button onclick="closeViewModal()" type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium px-4 py-2 rounded text-sm">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div id="imageLightbox" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden items-center justify-center">
        <div class="relative max-w-2xl w-full px-4">
            <button type="button" id="closeImageLightbox" class="absolute -top-10 -right-4 text-white text-3xl font-bold">&times;</button>
            <img id="lightboxImage" src="" class="w-full h-auto object-contain rounded" alt="Preview" />
        </div>
    </div>

    <script>
        // Image lightbox functions
        function openImageLightbox(imageSrc) {
            const lightbox = document.getElementById('imageLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            
            if (lightbox && lightboxImage) {
                lightboxImage.src = imageSrc;
                lightbox.classList.remove('hidden');
                lightbox.classList.add('flex');
            }
        }

        // Close lightbox when clicking the close button
        document.addEventListener('DOMContentLoaded', function() {
            const closeButton = document.getElementById('closeImageLightbox');
            if (closeButton) {
                closeButton.addEventListener('click', function() {
                    const lightbox = document.getElementById('imageLightbox');
                    if (lightbox) {
                        lightbox.classList.add('hidden');
                        lightbox.classList.remove('flex');
                    }
                });
            }
        });

        // Close lightbox when clicking outside the image
        document.addEventListener('DOMContentLoaded', function() {
            const lightbox = document.getElementById('imageLightbox');
            if (lightbox) {
                lightbox.addEventListener('click', function(e) {
                    if (e.target === this) {
                        lightbox.classList.add('hidden');
                        lightbox.classList.remove('flex');
                    }
                });
            }
        });

        // Function to try alternative image paths
        function tryAlternativeImagePath(imgElement, basePath) {
            console.log('Image failed to load, trying alternative paths for:', basePath);
            
            if (!basePath) {
                console.log('No base path provided, showing placeholder');
                imgElement.style.display = 'none';
                imgElement.nextElementSibling.style.display = 'flex';
                return;
            }
            
            // Extract filename from the base path
            const filename = basePath.split('/').pop();
            const alternativePaths = [
                'get_image.php?file=' + encodeURIComponent(filename),  // Use PHP script
                '../' + basePath,                    // Fallback: direct path
                '../../' + basePath,                 // Fallback: go up two levels
                basePath                             // Fallback: direct path
            ];
            
            console.log('Trying paths:', alternativePaths);
            let currentIndex = 0;
            
            function tryNextPath() {
                if (currentIndex >= alternativePaths.length) {
                    // All paths failed, show placeholder
                    console.log('All paths failed, showing placeholder');
                    imgElement.style.display = 'none';
                    imgElement.nextElementSibling.style.display = 'flex';
                    return;
                }
                
                const testPath = alternativePaths[currentIndex];
                console.log('Testing path:', testPath);
                const testImg = new Image();
                
                testImg.onload = function() {
                    // This path works, use it
                    console.log('Path works:', testPath);
                    imgElement.src = testPath;
                    imgElement.style.display = 'block';
                    imgElement.nextElementSibling.style.display = 'none';
                };
                
                testImg.onerror = function() {
                    // Try next path
                    console.log('Path failed:', testPath);
                    currentIndex++;
                    tryNextPath();
                };
                
                testImg.src = testPath;
            }
            
            tryNextPath();
        }

        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const lightbox = document.getElementById('imageLightbox');
                if (lightbox && !lightbox.classList.contains('hidden')) {
                    lightbox.classList.add('hidden');
                    lightbox.classList.remove('flex');
                }
            }
        });

        // View modal functions
        function closeViewModal() {
            const modal = document.getElementById('viewRequestModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            // Reset ?view_request=... in the URL without reloading
            try {
                const current = new URL(window.location.href);
                if (current.searchParams.has('view_request')) {
                    current.searchParams.delete('view_request');
                    const newUrl = current.pathname + (current.search ? current.search : '') + (current.hash || '');
                    window.history.replaceState({}, '', newUrl);
                }
            } catch(_) {}
        }

        // Auto-open view modal when viewing via PHP param
        <?php if (!empty($viewReservation)): ?>
        window.addEventListener('DOMContentLoaded', function(){
            const modal = document.getElementById('viewRequestModal');
            if (modal) { 
                modal.classList.remove('hidden'); 
                modal.classList.add('flex'); 
                
                // Wait for modal to be fully visible before initializing map
                setTimeout(() => {
                    initializeViewMap();
                }, 500);
            }
        });

        // Initialize view map function
        function initializeViewMap() {
            console.log('initializeViewMap called');
            
            try {
                const mapEl = document.getElementById('view_map');
                if (!mapEl) {
                    console.log('Map element not found, retrying...');
                    setTimeout(initializeViewMap, 100);
                    return;
                }
                
                if (typeof L === 'undefined') {
                    console.log('Leaflet not loaded yet, retrying...');
                    setTimeout(initializeViewMap, 100);
                    return;
                }
                
                // Check if map is already initialized
                if (mapEl._leaflet_id) {
                    console.log('Map already initialized, skipping...');
                    return;
                }
                
                console.log('Starting map initialization...');

                // Extract all address parts
                    const addrRaw = <?php echo json_encode($viewReservation['Reservation_address'] ?? ''); ?> || '';
                    const parts = addrRaw.split('|').map(s => (s || '').trim()).filter(Boolean);

                    // Fallback center (Manila)
                    const center = [14.5995, 120.9842];

                    // Extended Philippines bounds with reduced southern coverage
                    const philippinesBounds = [
                        [3.1, 115.0],  // Southwest (reduced southern coverage)
                        [22.5, 129.5]  // Northeast (extended north and east)
                    ];

                    const viewMap = L.map('view_map', {
                        maxBounds: philippinesBounds,
                        maxBoundsViscosity: 1.0,
                        minZoom: 4.5,
                        maxZoom: 18,
                        zoomSnap: 0.5,
                        zoomDelta: 0.5,
                        wheelPxPerZoomLevel: 60,
                        tap: false,
                        bounceAtZoomLimits: false,
                        zoomControl: true,
                        dragging: true,
                        scrollWheelZoom: true,
                        doubleClickZoom: true,
                        boxZoom: true,
                        keyboard: true,
                        touchZoom: true,
                    }).setView(center, 6);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(viewMap);

                    const pin = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    // Vehicle icon (SVG data URI) for the first point
                    const vehicleSvg = encodeURIComponent(`
                        <svg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 64 64'>
                          <defs>
                            <linearGradient id='gBody' x1='0' x2='1' y1='0' y2='1'>
                              <stop offset='0%' stop-color='#86efac'/>
                              <stop offset='100%' stop-color='#065f46'/>
                            </linearGradient>
                            <radialGradient id='gWheel' cx='50%' cy='50%' r='50%'>
                              <stop offset='0%' stop-color='#f3f4f6'/>
                              <stop offset='60%' stop-color='#9ca3af'/>
                              <stop offset='100%' stop-color='#111827'/>
                            </radialGradient>
                            <linearGradient id='gGlass' x1='0' x2='0' y1='0' y2='1'>
                              <stop offset='0%' stop-color='#e0f2fe' stop-opacity='0.95'/>
                              <stop offset='100%' stop-color='#93c5fd' stop-opacity='0.9'/>
                            </linearGradient>
                          </defs>
                          <ellipse cx='32' cy='56' rx='18' ry='6' fill='rgba(0,0,0,0.25)'/>
                          <path d='M8 40v6c0 1.1.9 2 2 2h4a7 7 0 0 0 14 0h16a7 7 0 0 0 14 0h2c1.1 0 2-.9 2-2v-7.5c0-2.5-2-4.5-4.5-4.5H52l-6.2-11c-1.1-2-3.3-3.5-5.7-3.5H24c-2.4 0-4.6 1.4-5.7 3.5L14 34H10c-1.1 0-2 .9-2 2z' fill='url(#gBody)' stroke='#0f172a' stroke-width='1'/>
                          <path d='M44.5 24h5c1.1 0 2.1.6 2.6 1.6L54 30h-9.5V24z' fill='#065f46' opacity='0.35'/>
                          <rect x='24' y='24' width='16' height='8' rx='2' fill='url(#gGlass)' stroke='#1d4ed8' stroke-opacity='0.3'/>
                          <circle cx='22' cy='48' r='6.5' fill='url(#gWheel)'/>
                          <circle cx='48' cy='48' r='6.5' fill='url(#gWheel)'/>
                          <circle cx='22' cy='48' r='2' fill='#111827'/>
                          <circle cx='48' cy='48' r='2' fill='#111827'/>
                          <path d='M18 38h28' stroke='rgba(255,255,255,0.35)' stroke-width='2' stroke-linecap='round'/>
                        </svg>`);
                    const vehicleIcon = L.icon({
                        iconUrl: `data:image/svg+xml;charset=UTF-8,${vehicleSvg}`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 28],
                        popupAnchor: [0, -28]
                    });

                    const markers = [];
                    const points = []; // [ [lat,lng], ... ] in order

                    function addMarker(lat, lng, label) {
                        const isFirst = markers.length === 0;
                        const usedIcon = isFirst ? vehicleIcon : pin;
                        const m = L.marker([lat, lng], { draggable: false, icon: usedIcon });
                        if (label) { m.bindPopup(label); }
                        m.addTo(viewMap);
                        markers.push(m);
                        points.push([lat, lng]);
                    }

                    // Pin any coordinates already embedded as "lat,lng"
                    const textParts = [];
                    parts.forEach((p) => {
                        const m = p.match(/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
                        if (m) {
                            addMarker(parseFloat(m[1]), parseFloat(m[2]), p);
                        } else {
                            textParts.push(p);
                        }
                    });

                // Geocode remaining textual addresses with caching
                (async function geocodeAll(list) {
                    console.log('Starting geocoding for', list.length, 'addresses');
                    
                    const geoCacheKey = 'vrds_geocode_cache_v2';
                    const geoCache = (() => { try { return JSON.parse(localStorage.getItem(geoCacheKey) || '{}'); } catch(_) { return {}; } })();

                    for (let i = 0; i < list.length; i++) {
                        const q = list[i];
                        console.log(`Processing address ${i + 1}/${list.length}:`, q);
                        
                        // Check cache first
                        if (geoCache[q]) {
                            const cached = geoCache[q];
                            addMarker(cached.lat, cached.lon, q);
                            console.log('Using cached result for:', q);
                            continue;
                        }
                        
                        try {
                            const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
                            const proxyPath = isLocalhost ? '/logistic2/api/geocode_proxy.php' : '/api/geocode_proxy.php';
                            const url = proxyPath + '?action=search&q=' + encodeURIComponent(q);
                            const resp = await fetch(url, { 
                                headers: { 'Accept': 'application/json' }
                            });
                            
                            if (resp.ok) {
                                const data = await resp.json();
                                if (Array.isArray(data) && data.length > 0) {
                                    const res = { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon) };
                                    addMarker(res.lat, res.lon, q);
                                    geoCache[q] = res;
                                    try { localStorage.setItem(geoCacheKey, JSON.stringify(geoCache)); } catch(_) {}
                                    console.log('Added marker for:', q, 'at:', res.lat, res.lon);
                                }
                            }
                        } catch(e) { 
                            console.log('Geocoding error for:', q, 'Error:', e.message);
                        }
                        
                        // Small delay between requests
                        await new Promise(r => setTimeout(r, 200));
                    }
                    
                    console.log('Geocoding complete. Total markers:', markers.length);

                    // Draw route if we have enough points
                    if (points.length >= 2) {
                        drawRoute();
                    }

                    // Fit map bounds to show all markers with proper padding and zoom limit
                    if (markers.length > 0) {
                        const group = L.featureGroup(markers);
                        try { 
                            const bounds = group.getBounds();
                            
                            // Ensure bounds are within Philippines limits
                            const limitedBounds = L.latLngBounds([
                                [Math.max(3.1, bounds.getSouth()), Math.max(115.0, bounds.getWest())],
                                [Math.min(22.5, bounds.getNorth()), Math.min(129.5, bounds.getEast())]
                            ]);
                            
                            viewMap.fitBounds(limitedBounds, { 
                                padding: [20, 20],
                                maxZoom: 10 // Limit zoom level
                            });
                            console.log('Map bounds fitted to markers with Philippines limits');
                            
                            // Zoom out slightly after fitting bounds for better overview
                            setTimeout(() => {
                                const currentZoom = viewMap.getZoom();
                                if (currentZoom > 6) { // Only zoom out if currently zoomed in too much
                                    viewMap.setZoom(currentZoom - 0.5);
                                    console.log('Zoomed out slightly for better route overview');
                                }
                            }, 300);
                        } catch(e) { 
                            console.log('Error fitting bounds:', e.message);
                        }
                    }
                    
                    console.log('Map initialization complete');
                })(textParts);
                
                // Simple function to draw route
                function drawRoute() {
                    if (points.length >= 2) {
                        console.log('Drawing route for', points.length, 'points');
                        const coords = points.map(p => [Number(p[0].toFixed(5)), Number(p[1].toFixed(5))]);
                        const coordStr = coords.map(p => `${p[1]},${p[0]}`).join(';'); // lon,lat
                        
                        try {
                            const url = `https://router.project-osrm.org/route/v1/driving/${coordStr}?overview=simplified&geometries=geojson&steps=false&annotations=false&skip_waypoints=true`;
                            console.log('Fetching route from:', url);
                            
                            fetch(url)
                                .then(r => r.json())
                                .then(j => {
                                    if (j && j.routes && j.routes[0] && j.routes[0].geometry) {
                                        // Clear existing route
                                        viewMap.eachLayer(layer => {
                                            if (layer instanceof L.GeoJSON) {
                                                viewMap.removeLayer(layer);
                                            }
                                        });
                                        
                                        L.geoJSON(j.routes[0].geometry, { style: { color: '#2563eb', weight: 4 } }).addTo(viewMap);
                                        console.log('Route drawn successfully');
                                    }
                                })
                                .catch(e => console.log('Route fetching error:', e.message));
                        } catch(e) { 
                            console.log('Route fetching error:', e.message);
                        }
                    }
                }

                // Ensure correct sizing after map is fully loaded
                setTimeout(() => { 
                    try { 
                        viewMap.invalidateSize(); 
                        // Force a resize event to ensure proper rendering
                        window.dispatchEvent(new Event('resize'));
                        
                        // Additional check to ensure map is properly rendered
                        if (markers.length > 0) {
                            const group = L.featureGroup(markers);
                            try { 
                                viewMap.fitBounds(group.getBounds().pad(0.25)); 
                                // Force another invalidate after bounds fitting
                                setTimeout(() => viewMap.invalidateSize(), 100);
                            } catch(_) {}
                        }
                        
                        // Final verification - ensure map is properly sized
                        if (markers.length > 0) {
                            console.log('Final map setup complete with', markers.length, 'markers');
                        }
                    } catch(_){} 
                }, 500);
            } catch (e) { 
                console.error('Error initializing view map:', e);
                // Retry on error
                setTimeout(initializeViewMap, 200);
            }
        }
        <?php endif; ?>

        // Cancel modal functions
        let currentCancelRequestId = null;

        function openCancelModal(requestId) {
            currentCancelRequestId = requestId;
            const modal = document.getElementById('confirmCancelModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function cancelCancel() {
            const modal = document.getElementById('confirmCancelModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            currentCancelRequestId = null;
        }

        function confirmCancel() {
            if (currentCancelRequestId) {
                const accountNumber = '<?= htmlspecialchars($userAccountNumber); ?>';
                window.location.href = `includes/reservation-backend.php?cancel_request=${encodeURIComponent(currentCancelRequestId)}&account_number=${encodeURIComponent(accountNumber)}`;
            }
        }

        // Close cancel modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('confirmCancelModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cancelCancel();
                    }
                });
            }
        });

        // Close cancel modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('confirmCancelModal');
                if (modal && !modal.classList.contains('hidden')) {
                    cancelCancel();
                }
            }
        });
    </script>

    <!-- Confirm Cancel Modal -->
    <div id="confirmCancelModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white w-full max-w-sm mx-auto rounded-lg shadow-lg p-6 relative text-center">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Confirm Cancellation</h2>
            <p class="text-sm text-gray-600 mb-6">Are you sure you want to cancel this reservation?</p>
            <div class="flex justify-center space-x-4">
                <button onclick="cancelCancel()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium px-4 py-2 rounded text-sm">Cancel</button>
                <button onclick="confirmCancel()" class="bg-red-600 hover:bg-red-700 text-white font-medium px-4 py-2 rounded text-sm">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        // Release numbers already used in items_tb (PHP → JS)
        const usedReleaseNumbers = <?php echo json_encode($usedReleaseNumbers ?? []); ?>;

        // Set minimum datetime for departure time (24 hours ahead in Philippine time)
        document.addEventListener('DOMContentLoaded', function() {
            const departureInput = document.getElementById('estimated_departure');
            if (departureInput) {
                // Get current time in Philippine time (UTC+8)
                const now = new Date();
                const phTime = new Date(now.getTime() + (8 * 60 * 60 * 1000) - (now.getTimezoneOffset() * 60 * 1000));
                
                // Add 24 hours (1 day) to the current Philippine time
                const minDateTime = new Date(phTime.getTime() + (24 * 60 * 60 * 1000));
                
                // Format as datetime-local string (YYYY-MM-DDTHH:mm)
                const year = minDateTime.getFullYear();
                const month = String(minDateTime.getMonth() + 1).padStart(2, '0');
                const day = String(minDateTime.getDate()).padStart(2, '0');
                const hours = String(minDateTime.getHours()).padStart(2, '0');
                const minutes = String(minDateTime.getMinutes()).padStart(2, '0');
                
                const minDateTimeString = `${year}-${month}-${day}T${hours}:${minutes}`;
                
                // Set the minimum allowed datetime
                departureInput.min = minDateTimeString;
                
                // Clear any existing value to ensure field starts empty
                departureInput.value = '';
                
                // Disable autocomplete to prevent browser auto-filling
                departureInput.setAttribute('autocomplete', 'off');
                
                // Add validation on input change
                departureInput.addEventListener('change', function() {
                    const selectedDateTime = new Date(this.value);
                    const currentPHTime = new Date(new Date().getTime() + (8 * 60 * 60 * 1000) - (new Date().getTimezoneOffset() * 60 * 1000));
                    const minAllowedTime = new Date(currentPHTime.getTime() + (24 * 60 * 60 * 1000));
                    
                    if (selectedDateTime < minAllowedTime) {
                        alert('Departure time must be at least 24 hours from now (Philippine time).');
                        this.value = minDateTimeString;
                    }
                });
                
                console.log('Departure time restriction set - minimum allowed:', minDateTimeString);
            }

            // Release Number API Integration
            const releaseNumberSelect = document.getElementById('release_number_select');
            const releaseItemsContainer = document.getElementById('release_items_container');
            const releaseItemsList = document.getElementById('release_items_list');
            let releasesData = []; // Store API data

            // Fetch releases from API via PHP proxy
            async function loadReleases() {
                try {
                    // Use PHP proxy to avoid CORS issues
                    const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
                    const proxyPath = isLocalhost ? '/Logistic2/reservation/api/releases_proxy.php' : 'api/releases_proxy.php';
                    
                    const response = await fetch(proxyPath);
                    if (!response.ok) {
                        throw new Error('Failed to fetch releases');
                    }
                    
                    const data = await response.json();
                    
                    // Check if response has error
                    if (data.error) {
                        throw new Error(data.message || data.error);
                    }
                    
                    releasesData = Array.isArray(data) ? data : [];
                    
                    // Populate release_number dropdown
                    if (releaseNumberSelect) {
                        // Clear existing options
                        releaseNumberSelect.innerHTML = '<option value="" hidden>Select Release Number</option>';
                        
                        if (releasesData.length > 0) {
                            releasesData.forEach(release => {
                                const relNum = release.release_number;
                                // Skip releases that already exist in items_tb
                                if (!relNum || usedReleaseNumbers.includes(relNum)) {
                                    return;
                                }

                                const option = document.createElement('option');
                                option.value = relNum;
                                option.textContent = relNum;
                                // Store the full release data as data attribute
                                option.setAttribute('data-release-id', release.release_id);
                                releaseNumberSelect.appendChild(option);
                            });
                            
                            console.log('Loaded', releasesData.length, 'releases');
                        } else {
                            const noDataOption = document.createElement('option');
                            noDataOption.value = '';
                            noDataOption.textContent = 'No releases available';
                            noDataOption.disabled = true;
                            releaseNumberSelect.appendChild(noDataOption);
                        }
                    }
                } catch (error) {
                    console.error('Error loading releases:', error);
                    if (releaseNumberSelect) {
                        // Clear and show error
                        releaseNumberSelect.innerHTML = '<option value="" hidden>Select Release Number</option>';
                        const errorOption = document.createElement('option');
                        errorOption.value = '';
                        errorOption.textContent = 'Error loading releases: ' + error.message;
                        errorOption.disabled = true;
                        releaseNumberSelect.appendChild(errorOption);
                    }
                }
            }

            // Function to display all items for a release
            function displayReleaseItems(release) {
                if (!releaseItemsList || !releaseItemsContainer) return;
                
                // Clear existing items
                releaseItemsList.innerHTML = '';
                
                if (!release || !release.items || release.items.length === 0) {
                    releaseItemsContainer.classList.add('hidden');
                    return;
                }
                
                // Display all items
                release.items.forEach((item, index) => {
                    const itemRow = document.createElement('div');
                    itemRow.className = 'grid grid-cols-1 md:grid-cols-2 gap-4 items-center border border-gray-200 rounded px-3 py-2 bg-gray-50';
                    
                    // Item Name
                    const itemNameDiv = document.createElement('div');
                    itemNameDiv.className = 'flex items-center';
                    const itemNameLabel = document.createElement('span');
                    itemNameLabel.className = 'text-sm font-medium text-gray-700 mr-2';
                    itemNameLabel.textContent = 'Item Name:';
                    const itemNameValue = document.createElement('span');
                    itemNameValue.className = 'text-sm text-gray-900';
                    itemNameValue.textContent = item.item_name || 'N/A';
                    itemNameDiv.appendChild(itemNameLabel);
                    itemNameDiv.appendChild(itemNameValue);
                    
                    // Released Quantity
                    const quantityDiv = document.createElement('div');
                    quantityDiv.className = 'flex items-center';
                    const quantityLabel = document.createElement('span');
                    quantityLabel.className = 'text-sm font-medium text-gray-700 mr-2';
                    quantityLabel.textContent = 'Released Quantity:';
                    const quantityValue = document.createElement('span');
                    quantityValue.className = 'text-sm text-gray-900';
                    quantityValue.textContent = (item.released_quantity || '0') + ' ' + (item.unit || '');
                    quantityDiv.appendChild(quantityLabel);
                    quantityDiv.appendChild(quantityValue);
                    
                    // Add hidden inputs for form submission
                    const hiddenItemName = document.createElement('input');
                    hiddenItemName.type = 'hidden';
                    hiddenItemName.name = 'item_name[]';
                    hiddenItemName.value = item.item_name || '';
                    
                    const hiddenQuantity = document.createElement('input');
                    hiddenQuantity.type = 'hidden';
                    hiddenQuantity.name = 'released_quantity[]';
                    hiddenQuantity.value = item.released_quantity || '0';
                    
                    itemRow.appendChild(itemNameDiv);
                    itemRow.appendChild(quantityDiv);
                    itemRow.appendChild(hiddenItemName);
                    itemRow.appendChild(hiddenQuantity);
                    
                    releaseItemsList.appendChild(itemRow);
                });
                
                // Show the container
                releaseItemsContainer.classList.remove('hidden');
            }

            // Handle release number selection change
            if (releaseNumberSelect) {
                releaseNumberSelect.addEventListener('change', function() {
                    const selectedReleaseNumber = this.value;
                    
                    if (!selectedReleaseNumber) {
                        // Hide container if no selection
                        if (releaseItemsContainer) {
                            releaseItemsContainer.classList.add('hidden');
                        }
                        if (releaseItemsList) {
                            releaseItemsList.innerHTML = '';
                        }
                        return;
                    }
                    
                    // Find the selected release in the data
                    const selectedRelease = releasesData.find(r => r.release_number === selectedReleaseNumber);
                    
                    // Display all items for the selected release
                    displayReleaseItems(selectedRelease);
                });
            }

            // Load releases on page load
            loadReleases();
        });
    </script>

    <script src="scripts/reservation.js?v=<?php echo time(); ?>"></script>

</body>
</html>