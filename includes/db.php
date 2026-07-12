<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lending_db');

function getDB() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // The handler in init.php will catch this exception
        throw new Exception("DB Connection Failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4"); 
    return $conn;
}
?>