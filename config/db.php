<?php
// config/db.php
$host = "localhost";
$username = "GS05DB"; 
$password = "1234"; 
$dbname = "gs05"; // Ensure this matches your project database name

// 1. MySQLi Connection (Used by Hanim's Dashboard/IC Parser)
$conn = mysqli_connect($host, $username, $password, $dbname);
if (!$conn) {
    die("MySQLi Connection failed: " . mysqli_connect_error());
}

// 2. PDO Connection (Used by Vaanishah's Hybrid Search Engine)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}
?>