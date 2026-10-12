<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set longer execution time for slow connections
set_time_limit(30);
ini_set('max_execution_time', 30);

$action = $_GET['action'] ?? '';
$lat = $_GET['lat'] ?? '';
$lng = $_GET['lng'] ?? '';
$query = $_GET['q'] ?? '';

// Function to make HTTP request with better error handling
function makeRequest($url, $timeout = 15) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Logistic2-App/1.0',
                'Accept: application/json',
                'Connection: close'
            ],
            'timeout' => $timeout,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        error_log("Geocoding proxy: Failed to fetch from $url");
        return false;
    }
    
    // Check HTTP response code
    $httpCode = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $httpCode = (int) substr($header, 9, 3);
                break;
            }
        }
    }
    
    if ($httpCode >= 400) {
        error_log("Geocoding proxy: HTTP error $httpCode for $url");
        return false;
    }
    
    return $response;
}

if ($action === 'reverse' && $lat && $lng) {
    // Simple reverse geocoding - just return a readable address
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($lat) . "&lon=" . urlencode($lng) . "&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Logistic2-App/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        // Simple fallback - create a readable address from coordinates
        $address = "Location at " . round($lat, 4) . ", " . round($lng, 4);
        echo json_encode(['address' => $address]);
        exit();
    }
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['display_name']) && !empty(trim($data['display_name']))) {
        echo json_encode(['address' => trim($data['display_name'])]);
    } else {
        // Build address from components
        $address = "Location at " . round($lat, 4) . ", " . round($lng, 4);
        
        if ($data && isset($data['address'])) {
            $addr = $data['address'];
            $parts = [];
            
            if (isset($addr['road'])) { $parts[] = $addr['road']; }
            if (isset($addr['suburb'])) { $parts[] = $addr['suburb']; }
            if (isset($addr['city'])) { $parts[] = $addr['city']; }
            if (isset($addr['state'])) { $parts[] = $addr['state']; }
            if (isset($addr['country'])) { $parts[] = $addr['country']; }
            
            if (!empty($parts)) {
                $address = implode(', ', $parts);
            }
        }
        
        echo json_encode(['address' => $address]);
    }
    
} elseif ($action === 'search' && $query) {
    // Forward geocoding - get coordinates from address
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&limit=10&addressdetails=1&countrycodes=ph&bounded=0&dedupe=0";
    
    $response = makeRequest($url, 15); // 15 second timeout for search
    
    if ($response === false) {
        // Try with broader search (no country restriction)
        $broaderUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&limit=5&addressdetails=1";
        $response = makeRequest($broaderUrl, 10); // 10 second timeout for broader search
    }
    
    if ($response === false) {
        echo json_encode(['error' => 'Search failed - check your internet connection']);
        exit();
    }
    
    $data = json_decode($response, true);
    if ($data && is_array($data)) {
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
    
} elseif ($action === 'test') {
    // Test endpoint to verify proxy is working
    echo json_encode([
        'status' => 'ok',
        'message' => 'Geocoding proxy is working',
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
    ]);
} elseif ($action === 'debug') {
    // Debug endpoint to test reverse geocoding with detailed output
    $testLat = $_GET['lat'] ?? '14.5995';
    $testLng = $_GET['lng'] ?? '120.9842';
    
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($testLat) . "&lon=" . urlencode($testLng);
    
    $response = makeRequest($url, 10);
    
    if ($response === false) {
        echo json_encode(['error' => 'Failed to fetch from Nominatim', 'url' => $url]);
    } else {
        $data = json_decode($response, true);
        echo json_encode([
            'url' => $url,
            'raw_response' => $response,
            'parsed_data' => $data,
            'has_display_name' => isset($data['display_name']),
            'display_name' => $data['display_name'] ?? 'NOT_SET',
            'has_address' => isset($data['address']),
            'address_components' => $data['address'] ?? 'NOT_SET'
        ]);
    }
} else {
    echo json_encode(['error' => 'Invalid parameters']);
}
?>


