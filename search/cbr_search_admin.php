<?php
/**
 * MediaVault - Content-Based Retrieval (CBR) Processor
 * Scans content features for any value matching the open string typed by the user.
 */

session_start();
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture user input and remove accidental outer whitespace
    $feature_value = isset($_POST['feature_value']) ? trim($_POST['feature_value']) : '';

    if ($feature_value === '') {
        $_SESSION['search_results'] = [];
        $_SESSION['search_mode']    = "Content-Based Retrieval (CBR)";
        header("Location: search_results.php");
        exit();
    }

    // Connect files directly to matching feature qualities in your database table
    $sql = "SELECT DISTINCT f.* FROM MULTIMEDIA_FILES f
            JOIN CONTENT_FEATURES cf ON f.file_id = cf.file_id
            WHERE cf.feature_value LIKE :feature_value";

    $stmt = $pdo->prepare($sql);
    
    // Using wildcards so that even partial typing (e.g., '1080' instead of '1920x1080') functions perfectly
    $stmt->execute(['feature_value' => '%' . $feature_value . '%']);
    
    $_SESSION['search_results'] = $stmt->fetchAll();
    $_SESSION['search_mode']    = "Content-Based Retrieval (CBR) (Match: '" . htmlspecialchars($feature_value) . "')";
    
    header("Location: search_results.php");
    exit();
}
?>