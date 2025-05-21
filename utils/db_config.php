<?php
$db_config = array(
    'host' => 'sql111.infinityfree.com',
    'username' => 'if0_38498744',
    'password' => 'p2oZle3olkb',
    'dbname' => 'if0_38498744_TimeKiller',
    'port' => 3306,
    'charset' => 'utf8mb4'
);

function get_db_connection() {
    global $db_config;
    
    try {
        $conn = new mysqli(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['dbname'],
            $db_config['port']
        );
        
        if ($conn->connect_error) {
            die("Unable to connect to the database: " . $conn->connect_error);
        }
        
        $conn->set_charset($db_config['charset']);
        
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
} 
?>