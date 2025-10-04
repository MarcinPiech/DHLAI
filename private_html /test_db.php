<?php

// Load .env
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

echo "🔍 Testing database connection...\n\n";

echo "Host: " . getenv('DB_HOST') . "\n";
echo "Port: " . getenv('DB_PORT') . "\n";
echo "Database: " . getenv('DB_DATABASE') . "\n";
echo "Username: " . getenv('DB_USERNAME') . "\n\n";

try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;charset=utf8mb4",
        getenv('DB_HOST'),
        getenv('DB_PORT')
    );
    
    $pdo = new PDO(
        $dsn,
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    
    echo "✅ MySQL connection: OK\n";
    
    // Sprawdź czy baza istnieje
    $dbName = getenv('DB_DATABASE');
    $stmt = $pdo->query("SHOW DATABASES LIKE '$dbName'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ Database '$dbName': EXISTS\n";
        
        // Połącz się z bazą
        $pdo->exec("USE $dbName");
        
        // Sprawdź tabele
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "📊 Tables: " . (count($tables) > 0 ? count($tables) : "0 (empty)") . "\n";
        
    } else {
        echo "⚠️  Database '$dbName': NOT EXISTS\n";
        echo "💡 Create it: http://localhost:8888/phpMyAdmin/\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✨ Test completed!\n";
