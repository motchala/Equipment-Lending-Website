<?php
    // define('DB_HOST', 'localhost');
    // define('DB_USER', 'root');
    // define('DB_PASS', '');
    // define('DB_NAME', 'lending_db');

    define('DB_HOST', 'fdb1032.awardspace.net');
    define('DB_USER', '4766450_pupsync');
    define('DB_PASS', 'sandynapiza123');
    define('DB_NAME', '4766450_pupsync');
   
    function getDB() {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die(json_encode(['error' => 'DB connection failed']));
        }
        return $conn;
    }
?>