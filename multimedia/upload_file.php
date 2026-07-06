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

function clean_value($value, string $default = ''): string
{
    $value = trim((string) ($value ?? $default));
    return $value === '' ? $default : $value;
}

function detect_media_group(string $fileName): string
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (in_array($extension, ['pdf', 'docx'], true)) {
        return 'Document';
    }
    if (in_array($extension, ['mp3', 'wav'], true)) {
        return 'Audio';
    }
    if ($extension === 'mp4') {
        return 'Video';
    }
    return '';
}

function safe_upload_name(string $fileName): string
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $baseName) ?? 'file';
    $baseName = trim($baseName, '-_');
    if ($baseName === '') {
        $baseName = 'file';
    }

    return $baseName . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
}

function detect_mime_type(string $path): string
{
    if (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($path);
        if (is_string($mimeType) && $mimeType !== '') {
            return $mimeType;
        }
    }

    return 'application/octet-stream';
}

function number_from_text($value): int
{
    if (is_numeric($value)) {
        return (int) $value;
    }
    preg_match('/\d+/', (string) $value, $matches);
    return isset($matches[0]) ? (int) $matches[0] : 0;
}

function count_words_in_text(string $text): int
{
    preg_match_all('/[A-Za-z0-9]+(?:[\'-][A-Za-z0-9]+)?/', $text, $matches);
    return count($matches[0] ?? []);
}

function detect_text_language(string $text): string
{
    $sample = strtolower($text);
    $malayWords = ['yang', 'dan', 'dengan', 'untuk', 'dalam', 'kepada', 'adalah', 'ini', 'itu', 'tidak'];
    $englishWords = ['the', 'and', 'with', 'for', 'from', 'this', 'that', 'is', 'are', 'not'];
    $malayScore = 0;
    $englishScore = 0;

    foreach ($malayWords as $word) {
        $malayScore += preg_match_all('/\b' . preg_quote($word, '/') . '\b/', $sample);
    }
    foreach ($englishWords as $word) {
        $englishScore += preg_match_all('/\b' . preg_quote($word, '/') . '\b/', $sample);
    }

    if ($malayScore > $englishScore) {
        return 'Malay';
    }
    if ($englishScore > 0) {
        return 'English';
    }
    return 'Unknown';
}

function language_from_code(string $code): string
{
    $prefix = strtolower(substr($code, 0, 2));
    $languages = [
        'en' => 'English',
        'ms' => 'Malay',
        'id' => 'Indonesian',
        'zh' => 'Chinese',
        'ta' => 'Tamil',
        'ar' => 'Arabic',
    ];

    return $languages[$prefix] ?? strtoupper($code);
}

function extract_pdf_metadata(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        return [];
    }

    $pages = preg_match_all('/\/Type\s*\/Page\b/', $content);
    $version = preg_match('/%PDF-(\d\.\d)/', substr($content, 0, 30), $versionMatch)
        ? 'PDF ' . $versionMatch[1]
        : 'PDF';

    preg_match_all('/\(([^()]*)\)/', $content, $textMatches);
    $text = html_entity_decode(implode(' ', $textMatches[1] ?? []), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/\\\\[nrtbf()\\\\]/', ' ', $text) ?? $text;

    return [
        'pageCount' => max(0, (int) $pages),
        'wordCount' => count_words_in_text($text),
        'language' => detect_text_language($text),
        'version' => $version,
    ];
}

function read_docx_entry(ZipArchive $zip, string $entry): string
{
    $content = $zip->getFromName($entry);
    return is_string($content) ? $content : '';
}

function extract_docx_metadata(string $path): array
{
    if (!class_exists('ZipArchive')) {
        return ['pageCount' => 0, 'wordCount' => 0, 'language' => 'Unknown', 'version' => 'DOCX'];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['pageCount' => 0, 'wordCount' => 0, 'language' => 'Unknown', 'version' => 'DOCX'];
    }

    $documentXml = read_docx_entry($zip, 'word/document.xml');
    $appXml = read_docx_entry($zip, 'docProps/app.xml');
    $stylesXml = read_docx_entry($zip, 'word/styles.xml');
    $settingsXml = read_docx_entry($zip, 'word/settings.xml');
    $zip->close();

    $text = trim(html_entity_decode(strip_tags($documentXml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    $wordCount = 0;
    $pageCount = 0;
    $language = 'Unknown';

    if (preg_match('/<Words>(\d+)<\/Words>/', $appXml, $match)) {
        $wordCount = (int) $match[1];
    }
    if ($wordCount <= 0) {
        $wordCount = count_words_in_text($text);
    }

    if (preg_match('/<Pages>(\d+)<\/Pages>/', $appXml, $match)) {
        $pageCount = (int) $match[1];
    }

    $languageXml = $settingsXml . $stylesXml . $documentXml;
    if (preg_match('/w:val="([A-Za-z-]+)"/', $languageXml, $match)) {
        $language = language_from_code($match[1]);
    }
    if ($language === 'Unknown') {
        $language = detect_text_language($text);
    }

    return [
        'pageCount' => $pageCount,
        'wordCount' => $wordCount,
        'language' => $language,
        'version' => 'DOCX',
    ];
}

function merge_detected_metadata(array $details, array $detected): array
{
    foreach ($detected as $key => $value) {
        $existing = $details[$key] ?? null;
        $existingIsEmpty = $existing === null || $existing === '' || $existing === 'Unknown' || $existing === 'Auto-detected on save' || (is_numeric($existing) && (float) $existing <= 0);
        $detectedHasValue = $value !== null && $value !== '' && $value !== 'Unknown' && (!is_numeric($value) || (float) $value > 0);

        if ($existingIsEmpty || $detectedHasValue) {
            $details[$key] = $value;
        }
    }

    return $details;
}

function extract_document_metadata(string $path, string $fileName, array $details): array
{
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $detected = [];

    if ($extension === 'pdf') {
        $detected = extract_pdf_metadata($path);
    } elseif ($extension === 'docx') {
        $detected = extract_docx_metadata($path);
    }

    return merge_detected_metadata($details, $detected);
}

function save_metadata(mysqli $conn, int $fileId, string $mediaGroup, array $details): void
{
    mysqli_query($conn, "DELETE FROM document_metadata WHERE file_id = " . $fileId);
    mysqli_query($conn, "DELETE FROM audio_metadata WHERE file_id = " . $fileId);
    mysqli_query($conn, "DELETE FROM video_metadata WHERE file_id = " . $fileId);

    if ($mediaGroup === 'Document') {
        $pageCount = (int) ($details['pageCount'] ?? 0);
        $wordCount = (int) ($details['wordCount'] ?? 0);
        $language = clean_value($details['language'] ?? 'Unknown', 'Unknown');
        $version = clean_value($details['version'] ?? 'Unknown', 'Unknown');
        $stmt = mysqli_prepare($conn, "INSERT INTO document_metadata (file_id, page_count, word_count, language, version_number) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiiss', $fileId, $pageCount, $wordCount, $language, $version);
        mysqli_stmt_execute($stmt);
        $metadataId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "UPDATE multimedia_files SET doc_id = $metadataId, audio_id = NULL, video_id = NULL WHERE file_id = $fileId");
        return;
    }

    if ($mediaGroup === 'Audio') {
        $duration = (int) round((float) ($details['duration'] ?? 0));
        $bitrate = number_from_text($details['bitrate'] ?? 0);
        $frequency = number_from_text($details['frequency'] ?? 0);
        $genre = clean_value($details['genre'] ?? 'Unknown', 'Unknown');
        $stmt = mysqli_prepare($conn, "INSERT INTO audio_metadata (file_id, duration_seconds, bitrate_kbps, frequency_hz, genre_tag) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiiis', $fileId, $duration, $bitrate, $frequency, $genre);
        mysqli_stmt_execute($stmt);
        $metadataId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "UPDATE multimedia_files SET audio_id = $metadataId, doc_id = NULL, video_id = NULL WHERE file_id = $fileId");
        return;
    }

    $duration = (int) round((float) ($details['duration'] ?? 0));
    $resolution = clean_value($details['resolution'] ?? 'Unknown', 'Unknown');
    $frameRate = (float) number_from_text($details['frameRate'] ?? 0);
    $codec = clean_value($details['codec'] ?? 'Unknown', 'Unknown');
    $stmt = mysqli_prepare($conn, "INSERT INTO video_metadata (file_id, duration_seconds, resolution, frame_rate, codec) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iisds', $fileId, $duration, $resolution, $frameRate, $codec);
    mysqli_stmt_execute($stmt);
    $metadataId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    mysqli_query($conn, "UPDATE multimedia_files SET video_id = $metadataId, doc_id = NULL, audio_id = NULL WHERE file_id = $fileId");
}

$message = '';
$messageType = 'ok';
$transactionStarted = false;
$maxUploadBytes = 150 * 1024 * 1024;
$storedPath = '';

ensure_file_storage_columns($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['media_file']['name']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please choose a supported file to upload.');
        }

        $fileName = basename($_FILES['media_file']['name']);
        $mediaGroup = detect_media_group($fileName);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $details = json_decode((string) ($_POST['metadata_json'] ?? '{}'), true);

        if ($mediaGroup === '') {
            throw new RuntimeException('Only PDF, DOCX, MP3, WAV, and MP4 files are supported.');
        }
        if ($userId <= 0) {
            throw new RuntimeException('Please log in before uploading a file.');
        }
        if ((int) $_FILES['media_file']['size'] > $maxUploadBytes) {
            throw new RuntimeException('File is too large. Please upload a file up to 150 MB.');
        }
        if (!is_array($details)) {
            $details = [];
        }
        if ($mediaGroup === 'Document') {
            $details = extract_document_metadata($_FILES['media_file']['tmp_name'], $fileName, $details);
        }

        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            throw new RuntimeException('Unable to create upload directory.');
        }
        if (!is_writable($uploadDir)) {
            throw new RuntimeException('Upload directory is not writable.');
        }

        $storedName = safe_upload_name($fileName);
        $storedPath = 'uploads/' . $storedName;
        $absoluteStoredPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
        $mimeType = detect_mime_type($_FILES['media_file']['tmp_name']);

        if (!move_uploaded_file($_FILES['media_file']['tmp_name'], $absoluteStoredPath)) {
            throw new RuntimeException('Unable to save the uploaded file.');
        }

        $sizeKb = round(((int) $_FILES['media_file']['size']) / 1024, 2);
        mysqli_begin_transaction($conn);
        $transactionStarted = true;

        $stmt = mysqli_prepare($conn, "INSERT INTO multimedia_files (file_name, file_type, size_kb, stored_path, mime_type, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new RuntimeException(mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, 'ssdssi', $fileName, $mediaGroup, $sizeKb, $storedPath, $mimeType, $userId);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException(mysqli_error($conn));
        }
        $fileId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        save_metadata($conn, $fileId, $mediaGroup, $details);
        mysqli_commit($conn);
        $message = 'File metadata saved successfully.';
    } catch (Throwable $error) {
        if ($transactionStarted) {
            mysqli_rollback($conn);
        }
        if ($storedPath !== '') {
            $failedUploadPath = __DIR__ . '/' . $storedPath;
            if (is_file($failedUploadPath)) {
                unlink($failedUploadPath);
            }
        }
        $message = $error->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File - MediaVault</title>
    <style>
        :root {
            --bg: #eef2f7;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --line: #d9e2ec;
            --line-strong: #c6d3e1;
            --text: #162033;
            --muted: #66758a;
            --primary: #1d4ed8;
            --primary-dark: #173ea8;
            --primary-soft: #e8f0ff;
            --success-bg: #e8f7ee;
            --success-text: #17643a;
            --error-bg: #fdeaea;
            --error-text: #a12727;
            --shadow: 0 18px 45px rgba(31, 41, 55, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #f7f9fc 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }
        .container { max-width: 980px; margin: 0 auto; padding: 34px 18px 42px; }
        .page-header {
            background: #102a43;
            border-radius: 8px;
            box-shadow: var(--shadow);
            color: #fff;
            margin-bottom: 18px;
            padding: 24px;
        }
        .header-row { align-items: flex-start; display: flex; gap: 16px; justify-content: space-between; }
        .brand-mark { color: #9fbdfc; font-size: 12px; font-weight: 800; letter-spacing: 0.9px; margin: 0 0 7px; text-transform: uppercase; }
        h1, h2 { margin: 0; }
        h1 { color: #fff; font-size: 31px; line-height: 1.15; }
        h2 { color: #152238; font-size: 21px; margin-bottom: 8px; }
        .page-header .muted { color: #d8e2f0; margin-bottom: 0; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 24px;
        }
        .grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
        label { color: #27364a; display: block; font-size: 13px; font-weight: 800; margin-bottom: 8px; }
        input {
            background: #fff;
            border: 1px solid var(--line-strong);
            border-radius: 8px;
            min-height: 46px;
            padding: 11px 12px;
            width: 100%;
        }
        input[type="file"] {
            background: var(--surface-soft);
            border-style: dashed;
            cursor: pointer;
        }
        input:focus { border-color: var(--primary); outline: 3px solid rgba(29, 78, 216, 0.16); }
        .btn {
            background: var(--primary);
            border: 0;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            display: inline-block;
            font-weight: 800;
            min-height: 42px;
            padding: 11px 16px;
            text-decoration: none;
            transition: background 0.15s, box-shadow 0.15s, transform 0.15s;
        }
        .btn:hover { background: var(--primary-dark); box-shadow: 0 10px 22px rgba(29, 78, 216, 0.22); transform: translateY(-1px); }
        .btn.secondary { background: #edf2f7; color: #1f2d3d; }
        .btn.secondary:hover { background: #dbe5ef; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
        .scan-box {
            background: var(--surface-soft);
            border: 1px dashed #91a4bb;
            border-radius: 8px;
            margin-top: 20px;
            padding: 18px;
        }
        .scan-box strong { color: #152238; display: block; margin-bottom: 5px; }
        .details-grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); margin-top: 14px; }
        .detail { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 14px; }
        .detail span { color: var(--muted); display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 5px; text-transform: uppercase; }
        .message { border-radius: 8px; font-weight: 800; margin-bottom: 16px; padding: 13px 14px; }
        .ok { background: var(--success-bg); color: var(--success-text); }
        .error { background: var(--error-bg); color: var(--error-text); }
        .muted { color: var(--muted); line-height: 1.55; }
        form > p { margin-bottom: 0; }
        @media (max-width: 720px) {
            .container { padding: 18px 12px 30px; }
            .header-row { display: block; }
            .header-row .btn { margin-top: 14px; text-align: center; width: 100%; }
            .panel, .page-header { padding: 18px; }
            h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <div class="header-row">
                <div>
                <p class="brand-mark">MediaVault Multimedia</p>
                <h1>Upload File</h1>
                <p class="muted">Add multimedia metadata and route it to the correct document, audio, or video table.</p>
                </div>
                <a class="btn secondary" href="../index.php">Back to Dashboard</a>
            </div>
        </header>

        <section class="panel">
            <h2>Upload Multimedia File</h2>
            <p class="muted">Upload PDF, DOCX, MP3, WAV, or MP4 metadata into the matching document, audio, or video table.</p>

            <?php if ($message !== ''): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="grid">
                    <div>
                        <label for="media_file">File</label>
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) $maxUploadBytes; ?>">
                        <input type="file" name="media_file" id="media_file" accept=".pdf,.docx,.mp3,.wav,.mp4" required>
                    </div>
                </div>

                <input type="hidden" name="metadata_json" id="metadata_json" value="{}">

                <div class="scan-box">
                    <strong>Scanned Metadata</strong>
                    <div id="scanStatus" class="muted">No file selected yet.</div>
                    <div id="scanPreview" class="details-grid"></div>
                </div>

                <p>
                    <button class="btn" type="submit" id="saveBtn">Save File</button>
                </p>
            </form>
        </section>
    </main>

    <script>
        const fileInput = document.getElementById('media_file');
        const scanStatus = document.getElementById('scanStatus');
        const scanPreview = document.getElementById('scanPreview');
        const metadataJson = document.getElementById('metadata_json');
        const supportedExtensions = ['pdf', 'docx', 'mp3', 'wav', 'mp4'];
        const maxUploadBytes = <?php echo (int) $maxUploadBytes; ?>;

        function escapeHtml(value) {
            return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
        }
        function extension(name) {
            return name.split('.').pop().toLowerCase();
        }
        function mediaGroup(ext) {
            if (['pdf', 'docx'].includes(ext)) return 'Document';
            if (['mp3', 'wav'].includes(ext)) return 'Audio';
            if (ext === 'mp4') return 'Video';
            return '';
        }
        function renderDetails(details) {
            scanPreview.innerHTML = Object.entries(details).map(([label, value]) => `<div class="detail"><span>${escapeHtml(label)}</span>${escapeHtml(value || 'Unknown')}</div>`).join('');
        }
        function formatDuration(seconds) {
            const value = Math.round(Number(seconds) || 0);
            if (!value) return 'Unknown';
            const minutes = Math.floor(value / 60);
            const remainder = String(value % 60).padStart(2, '0');
            return `${minutes}m ${remainder}s`;
        }
        async function getMediaDuration(file, tagName) {
            return new Promise(resolve => {
                const element = document.createElement(tagName);
                const url = URL.createObjectURL(file);
                element.preload = 'metadata';
                element.onloadedmetadata = () => {
                    URL.revokeObjectURL(url);
                    resolve(Number.isFinite(element.duration) ? element.duration : 0);
                };
                element.onerror = () => {
                    URL.revokeObjectURL(url);
                    resolve(0);
                };
                element.src = url;
            });
        }
        async function getVideoMetadata(file) {
            return new Promise(resolve => {
                const video = document.createElement('video');
                const url = URL.createObjectURL(file);
                video.preload = 'metadata';
                video.onloadedmetadata = () => {
                    URL.revokeObjectURL(url);
                    resolve({ duration: video.duration || 0, width: video.videoWidth, height: video.videoHeight });
                };
                video.onerror = () => {
                    URL.revokeObjectURL(url);
                    resolve({ duration: 0, width: 0, height: 0 });
                };
                video.src = url;
            });
        }
        async function scanFile(file) {
            const ext = extension(file.name);
            if (!supportedExtensions.includes(ext)) {
                throw new Error('Unsupported file type.');
            }

            if (ext === 'pdf') {
                const text = await file.text();
                const pages = (text.match(/\/Type\s*\/Page\b/g) || []).length;
                const version = (text.slice(0, 20).match(/%PDF-(\d\.\d)/) || [])[1];
                const words = text.match(/[A-Za-z0-9]+(?:['-][A-Za-z0-9]+)?/g) || [];
                return { pageCount: pages || 0, wordCount: words.length, language: 'Auto-detected on save', version: version ? `PDF ${version}` : 'PDF' };
            }
            if (ext === 'docx') {
                return { pageCount: 'Auto-detected on save', wordCount: 'Auto-detected on save', language: 'Auto-detected on save', version: 'DOCX' };
            }
            if (['mp3', 'wav'].includes(ext)) {
                const duration = await getMediaDuration(file, 'audio');
                return {
                    duration,
                    bitrate: duration ? `${Math.round((file.size * 8) / duration / 1000)} kbps` : 'Unknown',
                    frequency: 'Unknown',
                    genre: 'Unknown'
                };
            }
            const video = await getVideoMetadata(file);
            return {
                duration: video.duration,
                resolution: video.width && video.height ? `${video.width} x ${video.height}` : 'Unknown',
                frameRate: 'Unknown',
                codec: 'Unknown'
            };
        }

        fileInput.addEventListener('change', async () => {
            const file = fileInput.files[0];
            metadataJson.value = '{}';
            scanPreview.innerHTML = '';
            if (!file) {
                scanStatus.textContent = 'No file selected yet.';
                return;
            }
            if (file.size > maxUploadBytes) {
                scanStatus.textContent = 'File is too large. Please choose a file up to 150 MB.';
                fileInput.value = '';
                return;
            }

            scanStatus.textContent = 'Scanning selected file...';
            try {
                const details = await scanFile(file);
                metadataJson.value = JSON.stringify(details);
                const ext = extension(file.name);
                scanStatus.textContent = `${mediaGroup(ext)} file scanned: ${file.name}`;
                renderDetails({
                    'File Name': file.name,
                    'Type': mediaGroup(ext),
                    'Size': `${(file.size / 1024).toFixed(2)} KB`,
                    ...details,
                    duration: details.duration ? formatDuration(details.duration) : details.duration
                });
            } catch (error) {
                scanStatus.textContent = error.message;
            }
        });
    </script>
</body>
</html>