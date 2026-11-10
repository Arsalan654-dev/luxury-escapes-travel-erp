<?php
// =====================================================
// CORS + common headers
// Included at the top of every API endpoint
// =====================================================

// Production domain - restrict to your frontend URL
define('ALLOWED_ORIGIN', 'https://luxuryescaperp.com'); // Update with your actual domain
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [ALLOWED_ORIGIN, 'https://www.luxuryescaperp.com'];

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
