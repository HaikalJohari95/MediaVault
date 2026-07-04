<?php
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php';

function ensure_file_storage_columns(mysqli $conn): void
{
    $columns = [];
    $result = mysqli_query($conn, "SHOW COLUMNS FROM multimedia_files");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = true;
        }
    }

    if (!isset($columns['stored_path'])) {
        mysqli_query($conn, "ALTER TABLE multimedia_files ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL AFTER size_kb");
    }
    if (!isset($columns['mime_type'])) {
        mysqli_query($conn, "ALTER TABLE multimedia_files ADD COLUMN mime_type VARCHAR(120) DEFAULT NULL AFTER stored_path");
    }
}

function fail_file_request(int $statusCode): void
{
    http_response_code($statusCode);
    exit;
}

ensure_file_storage_columns($conn);

$fileId = (int) ($_GET['id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($fileId <= 0 || $userId <= 0) {
    fail_file_request(404);
}

$stmt = mysqli_prepare($conn, "SELECT file_name, stored_path, mime_type FROM multimedia_files WHERE file_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $fileId, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$file = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$file || empty($file['stored_path'])) {
    fail_file_request(404);
}

$absolutePath = realpath(__DIR__ . '/' . $file['stored_path']);
$uploadRoot = realpath(__DIR__ . '/uploads');
if ($absolutePath === false || $uploadRoot === false || strpos($absolutePath, $uploadRoot) !== 0 || !is_file($absolutePath)) {
    fail_file_request(404);
}

$fileSize = filesize($absolutePath);
if ($fileSize === false) {
    fail_file_request(404);
}

$mimeType = trim((string) ($file['mime_type'] ?? ''));
if ($mimeType === '') {
    $mimeType = 'application/octet-stream';
}

$download = isset($_GET['download']);
$disposition = $download ? 'attachment' : 'inline';
$safeName = str_replace(["\r", "\n", '"'], '', (string) $file['file_name']);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');

$start = 0;
$end = $fileSize - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';

if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
    if ($matches[1] !== '') {
        $start = (int) $matches[1];
    }
    if ($matches[2] !== '') {
        $end = (int) $matches[2];
    }
    if ($start > $end || $start >= $fileSize) {
        header('Content-Range: bytes */' . $fileSize);
        fail_file_request(416);
    }
    $end = min($end, $fileSize - 1);
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

$handle = fopen($absolutePath, 'rb');
if (!$handle) {
    fail_file_request(404);
}

fseek($handle, $start);
$remaining = $length;
while ($remaining > 0 && !feof($handle)) {
    $chunkSize = min(8192, $remaining);
    $chunk = fread($handle, $chunkSize);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    flush();
    $remaining -= strlen($chunk);
}
fclose($handle);
exit;
