<?php
$attempts = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'password'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'umer123'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
];

foreach ($attempts as $a) {
    echo "Trying {$a['user']}@{$a['host']} pass='{$a['pass']}'...\n";
    try {
        $pdo = new PDO("mysql:host={$a['host']}", $a['user'], $a['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        echo "SUCCESS!\n";
        
        // Check if our DB exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '%luxury%'");
        $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Luxury DBs: " . implode(', ', $dbs) . "\n";
        
        // List all databases
        $stmt = $pdo->query("SHOW DATABASES");
        $allDbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "All DBs: " . implode(', ', $allDbs) . "\n";
        
        exit(0);
    } catch (PDOException $e) {
        echo "Failed: " . $e->getMessage() . "\n";
    }
}
echo "\nNo local MySQL access found.\n";
