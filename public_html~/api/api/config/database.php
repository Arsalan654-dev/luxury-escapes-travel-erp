<?php
// =====================================================
// Database Connection Config
// Luxury Escapes Travel ERP
// =====================================================

// --- Hostinger DB credentials (Production) ---
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'u245440271_Luxuryescaperp');
// define('DB_USER', 'u245440271_Luxuryescape_');
// define('DB_PASS', 'Luxuryescape12345@@');

// --- Local Dev DB ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'u245440271_Luxuryescaperp');
define('DB_USER', 'root');
define('DB_PASS', '');


// define('DB_NAME', 'u659195349_luxuryescape');
// define('DB_USER', 'root');
// define('DB_PASS', 'umer123');


function getDbConnection()
{
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
        exit;
    }
}
