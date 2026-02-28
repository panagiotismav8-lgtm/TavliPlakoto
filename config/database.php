<?php
/**
 * Database Configuration
 * Plakoto Game - MySQL Database Connection Settings
 */

// MySQL Database Configuration - University Server (Unix Socket)
define('DB_USER', 'iee2021087');         // Your MySQL username
define('DB_PASS', 'Qwer1234#io');        // Your MySQL password
define('DB_NAME', 'iee2021087');         // Your database name (same as username)
define('DB_CHARSET', 'utf8mb4');

// Socket path from ~/.my.cnf
define('DB_SOCKET', '/home/student/iee/2021/iee2021087/mysql/run/mysql.sock');

/**
 * Get PDO Database Connection for MySQL via Unix Socket
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Connect via Unix socket instead of TCP/IP
            $dsn = "mysql:unix_socket=" . DB_SOCKET . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
?>
