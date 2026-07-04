<?php
session_start();
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php'; // Ensure $pdo is defined here!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_type = $_POST['file_type'] ?? '';
    $size_range = $_POST['size_range'] ?? '';

    $sql = "SELECT * FROM MULTIMEDIA_FILES WHERE 1=1";
    $params = [];

    // 1. Apply file type filter if chosen
    if (!empty($file_type)) {
        $sql .= " AND file_type = :file_type";
        $params['file_type'] = $file_type;
    }
    
    // 2. Handle friendly size ranges smoothly
    if (!empty($size_range)) {
        if ($size_range === 'small') {
            $sql .= " AND size_kb < 5120"; 
        } elseif ($size_range === 'medium') {
            $sql .= " AND size_kb >= 5120 AND size_kb <= 51200"; 
        } elseif ($size_range === 'large') {
            $sql .= " AND size_kb > 51200"; 
        }
    }

    try {
        // Double check that PDO exists
        if (!isset($pdo)) {
            throw new Exception("Database connection variable (\$pdo) is not defined.");
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll();
        
        // Debugging: If it redirects to an empty page, uncomment the next 2 lines to see what was found
        // var_dump($results); die();

        $_SESSION['search_results'] = $results;
        $_SESSION['search_mode'] = "Attribute-Based Retrieval (ABR)";
        
        header("Location: search_results.php");
        exit();

    } catch (PDOException $e) {
        // This will stop the script and tell you if your SQL syntax or table name is wrong
        die("Database Error: " . $e->getMessage());
    } catch (Exception $e) {
        die("General Error: " . $e->getMessage());
    }
} else {
    die("Error: Request method is not POST. Current method: " . $_SERVER['REQUEST_METHOD']);
}
?>