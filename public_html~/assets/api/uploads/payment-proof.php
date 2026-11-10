<?php
// =====================================================
// POST /api/uploads/payment-proof.php
// multipart/form-data with field name "file"
// Returns { path: "uploads/payments/xxxx.jpg" } to store
// against the payment record.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_ledger');

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded, or upload failed']);
    exit;
}

$file = $_FILES['file'];

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, WEBP, or PDF files are allowed']);
    exit;
}

if ($file['size'] > 8 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 8MB)']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/payments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file']);
    exit;
}

echo json_encode(['status' => 'success', 'path' => 'uploads/payments/' . $filename]);
