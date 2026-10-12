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

// Function to make HTTP request with better error handling
function makeRequest($url, $timeout = 15) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Logistic2-App/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        error_log("Releases proxy: Failed to fetch from $url - HTTP $httpCode - Error: $error");
        return false;
    }
    
    return $response;
}

// Fetch releases from the API
$apiUrl = 'https://log1.atierahotelandrestaurant.com/api/releases.php';

$response = makeRequest($apiUrl, 15);

if ($response === false) {
    echo json_encode([
        'error' => 'Failed to fetch releases from API',
        'message' => 'Please check your internet connection or try again later'
    ]);
    exit();
}

$data = json_decode($response, true);

if ($data === null || !is_array($data)) {
    echo json_encode([
        'error' => 'Invalid response from API',
        'message' => 'The API returned invalid data'
    ]);
    exit();
}

// Return the releases data
echo json_encode($data);
exit();
?>
