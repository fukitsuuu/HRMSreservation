<?php

date_default_timezone_set('Asia/Manila');

$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$httpHost = preg_replace('/:\d+$/', '', $httpHost);

// This copy lives under XAMPP. The hosted copy does not, so deploy can use the same file.
$runningOnXampp = stripos(__DIR__, DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR) !== false;
$isLocal = $runningOnXampp || in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true);

if ($isLocal) {
    $host = '127.0.0.1';
    $port = 3306;
    $dbname = 'logistic2_db';
    $username = 'root';
    $password = '';
} else {
    $host = 'sql101.infinityfree.com';
    $port = 3306;
    $dbname = 'if0_42928387_logistic2_db';
    $username = 'if0_42928387';
    $password = 'anonymous0203';
}

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->query("SET time_zone = '+08:00'");
