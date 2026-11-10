<?php
$hosts = ['localhost', 'sql209.infinityfree.com', 'sql309.infinityfree.com', 'sql123.infinityfree.com'];
$db = 'u245440271_Luxuryescaperp';
$user = 'u245440271_Luxuryescape_';
$pass = 'Luxuryescape12345@@';

foreach ($hosts as $host) {
    echo "Trying $host...\n";
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        echo "SUCCESS! Connected to $host\n";
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
        $row = $stmt->fetch();
        echo "Users table has " . $row['cnt'] . " records\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vendors");
        $row = $stmt->fetch();
        echo "Vendors table has " . $row['cnt'] . " records\n";
        
        exit(0);
    } catch (PDOException $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}
echo "\nAll hosts failed. The DB is only accessible from Hostinger servers.\n";
