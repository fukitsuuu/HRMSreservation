<?php
// Image serving script for vehicle images
// This script serves images from the database/uploads folder that's outside public_html

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the image filename from the URL parameter
$filename = $_GET['file'] ?? '';

// Debug: Log the request
error_log("get_image.php called with file: " . $filename);

// Validate filename (only allow jpg, jpeg, png files with spaces allowed)
if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\s-]+\.(jpg|jpeg|png)$/', $filename)) {
    error_log("Invalid filename: " . $filename);
    http_response_code(404);
    exit('Invalid filename');
}

// Based on diagnostic results: database/uploads is at ../database/uploads/ from reservation folder
$possiblePaths = [
    '../database/uploads/' . $filename,  // This is the correct path based on diagnostic
    '../../database/uploads/' . $filename,  // Fallback
    '../../../database/uploads/' . $filename,  // Fallback
    'database/uploads/' . $filename  // Fallback
];

$imagePath = null;
foreach ($possiblePaths as $testPath) {
    error_log("Testing path: " . $testPath . " - Exists: " . (file_exists($testPath) ? 'YES' : 'NO'));
    if (file_exists($testPath)) {
        $imagePath = $testPath;
        error_log("Found image at: " . $imagePath);
        break;
    }
}

// Check if file exists
if (!$imagePath) {
    error_log("File not found in any of the tested paths");
    http_response_code(404);
    exit('Image not found. Tested paths: ' . implode(', ', $possiblePaths));
}

// Debug: File found
error_log("File found: " . $imagePath);

// Get file info
$fileInfo = pathinfo($imagePath);
$extension = strtolower($fileInfo['extension']);

error_log("File extension: " . $extension);

// Set correct content type based on file extension
switch ($extension) {
    case 'jpg':
    case 'jpeg':
        header('Content-Type: image/jpeg');
        break;
    case 'png':
        header('Content-Type: image/png');
        break;
    default:
        error_log("Unsupported file type: " . $extension);
        http_response_code(404);
        exit('Unsupported file type');
}

// Set cache headers
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year

error_log("About to output image: " . $imagePath);

// Output the image
$result = readfile($imagePath);
if ($result === false) {
    error_log("Failed to read file: " . $imagePath);
    http_response_code(500);
    exit('Failed to read image file');
}

error_log("Successfully output image: " . $imagePath);
exit;
?>
