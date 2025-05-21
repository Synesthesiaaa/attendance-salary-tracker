<?php
// Database configuration for connection (adjust as needed)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'testdb');
define('DB_CHARSET', 'utf8mb4');

try {
    // Connect to MySQL server without specifying database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci");

    echo "Database '" . DB_NAME . "' created or already exists.<br>";

    // Connect to the newly created database
    $pdo->exec("USE `" . DB_NAME . "`");

    // Create the users table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_verified BOOLEAN NOT NULL DEFAULT 0,
        verification_token VARCHAR(64) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($createTableSQL);

    echo "Table 'users' created or already exists.<br>";

    // Insert default users with hashed passwords using PHP password_hash
    $users = [
        ['username' => 'user1', 'email' => 'user1@example.com', 'password' => 'password123', 'is_verified' => 1, 'verification_token' => null],
        ['username' => 'alice', 'email' => 'alice@example.com', 'password' => 'alicepass', 'is_verified' => 0, 'verification_token' => bin2hex(random_bytes(16))],
        ['username' => 'bob', 'email' => 'bob@example.com', 'password' => 'bobpass', 'is_verified' => 0, 'verification_token' => bin2hex(random_bytes(16))]
    ];

    // Prepare insert statement
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, is_verified, verification_token) VALUES (?, ?, ?, ?, ?)");

    foreach ($users as $u) {
        $hashedPassword = password_hash($u['password'], PASSWORD_DEFAULT);
        $stmt->execute([$u['username'], $u['email'], $hashedPassword, $u['is_verified'], $u['verification_token']]);
        $stmt = $pdo->prepare("INSERT INTO users (username, first_name, last_name, birthday, gender, email, password, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$username, $first_name, $last_name, $birthday, $gender, $email, $password_hash]);

    }

    echo "Default users inserted or already exist.<br>";

    echo "Setup complete.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

