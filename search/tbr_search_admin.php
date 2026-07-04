<?php
// ============================================================
//  MediaVault - Text-Based Retrieval (TBR) (Fixed to MySQLi)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keyword = $_POST['keyword'] ?? '';
    $likeKeyword = '%' . $keyword . '%';

    // Menukar SQL query menggunakan MySQLi Prepared Statement
    $sql = "SELECT DISTINCT f.* FROM multimedia_files f
            LEFT JOIN file_tags ft ON f.file_id = ft.file_id
            LEFT JOIN tag_dictionary t ON ft.tag_id = t.tag_id
            WHERE f.file_name LIKE ? 
               OR t.tag_name LIKE ?";

    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $likeKeyword, $likeKeyword);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $searchResults = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $searchResults[] = $row;
        }
        
        $_SESSION['search_results'] = $searchResults;
        $_SESSION['search_mode'] = "Text-Based Retrieval (TBR)";
        
        header("Location: search_results_admin.php");
        exit();
    }
}