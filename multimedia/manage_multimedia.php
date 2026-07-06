<?php
// =============================================================================
// MediaVault - Module 4: Multimedia Content Management Service
// Member 1: MUHAMMAD HAIKAL BIN JOHARI (B032420087)
// Member 2: HANNAN SAFFIYAH BINTI MOHD IDRIS (B0324XXXXX)
// Course: BITP3353 MULTIMEDIA DATABASE | UTeM 2025/2026
// Module Focus: Multimedia Asset Ingestion and Management.
// =============================================================================

require_once '../includes/session_guard.php';
require_once '../config/db.php'; // Menyediakan sambungan $conn menggunakan MySQLi
function asset_from_row(array $row): array
{
    $fileName = $row['file_name'] ?? '';
    $mediaGroup = $row['file_type'] ?? 'Document';
    $details = [];

    if ($mediaGroup === 'Document') {
        $details = [
            'pageCount' => (int) ($row['page_count'] ?? 0),
            'wordCount' => (int) ($row['word_count'] ?? 0),
            'language' => $row['language'] ?? 'English',
            'version' => $row['version_number'] ?? '1.0',
        ];
    } elseif ($mediaGroup === 'Audio') {
        $details = [
            'duration' => (int) ($row['audio_duration_seconds'] ?? 0),
            'bitrate' => ((int) ($row['bitrate_kbps'] ?? 0)) . ' kbps',
            'frequency' => ((int) ($row['frequency_hz'] ?? 0)) . ' Hz',
            'genre' => $row['genre_tag'] ?? 'Unknown',
        ];
    } elseif ($mediaGroup === 'Video') {
        $details = [
            'duration' => (int) ($row['video_duration_seconds'] ?? 0),
            'resolution' => $row['resolution'] ?? 'Unknown',
            'frameRate' => (float) ($row['frame_rate'] ?? 0) . ' fps',
            'codec' => $row['codec'] ?? 'Unknown',
        ];
    }

    return [
        'id' => (int) $row['file_id'],
        'fileName' => $fileName,
        'displayName' => preg_replace('/\.[^.]+$/', '', $fileName),
        'fileType' => strtoupper(pathinfo($fileName, PATHINFO_EXTENSION) ?: $mediaGroup),
        'mediaGroup' => $mediaGroup,
        'fileSize' => (int) round(((float) ($row['size_kb'] ?? 0)) * 1024),
        'uploadDate' => substr((string) ($row['upload_timestamp'] ?? date('Y-m-d')), 0, 10),
        'uploader' => (string) ($row['user_id'] ?? ''),
        'userId' => (int) ($row['user_id'] ?? 0),
        'details' => $details,
    ];
}

function get_multimedia_assets($conn): array
{
    $assets = [];
    $result = mysqli_query($conn, "SELECT
            m.*,
            d.page_count,
            d.word_count,
            d.language,
            d.version_number,
            a.duration_seconds AS audio_duration_seconds,
            a.bitrate_kbps,
            a.frequency_hz,
            a.genre_tag,
            v.duration_seconds AS video_duration_seconds,
            v.resolution,
            v.frame_rate,
            v.codec
        FROM multimedia_files m
        LEFT JOIN document_metadata d ON d.file_id = m.file_id
        LEFT JOIN audio_metadata a ON a.file_id = m.file_id
        LEFT JOIN video_metadata v ON v.file_id = m.file_id
        ORDER BY m.file_id DESC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $assets[] = asset_from_row($row);
        }
        mysqli_free_result($result);
    }
    return $assets;
}

function clean_text($value, string $default = ''): string
{
    return trim((string) ($value ?? $default));
}

function save_multimedia_asset($conn, array $asset): array
{
    $id = isset($asset['id']) ? (int) $asset['id'] : 0;
    $fileName = clean_text($asset['fileName']);
    $mediaGroup = clean_text($asset['mediaGroup']);
    $sizeKb = round(((int) ($asset['fileSize'] ?? 0)) / 1024, 2);
    $userId = (int) ($asset['userId'] ?? $asset['uploader'] ?? 0);
    $details = is_array($asset['details'] ?? null) ? $asset['details'] : [];

    if ($fileName === '' || !in_array($mediaGroup, ['Document', 'Audio', 'Video'], true) || $userId <= 0) {
        throw new RuntimeException('File name, valid media type, and numeric user ID are required.');
    }

    mysqli_begin_transaction($conn);

    try {
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE multimedia_files SET file_name = ?, file_type = ?, size_kb = ?, user_id = ? WHERE file_id = ?");
            if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmt, 'ssdii', $fileName, $mediaGroup, $sizeKb, $userId, $id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO multimedia_files (file_name, file_type, size_kb, user_id) VALUES (?, ?, ?, ?)");
            if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmt, 'ssdi', $fileName, $mediaGroup, $sizeKb, $userId);
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException(mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);

        if ($id === 0) {
            $id = (int) mysqli_insert_id($conn);
        }

        save_metadata($conn, $id, $mediaGroup, $details);
        mysqli_commit($conn);
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }

    $asset = get_multimedia_asset($conn, $id);
    if (!$asset) {
        throw new RuntimeException('Saved record could not be loaded.');
    }

    return $asset;
}

function save_metadata($conn, int $fileId, string $mediaGroup, array $details): void
{
    mysqli_query($conn, "DELETE FROM document_metadata WHERE file_id = " . $fileId);
    mysqli_query($conn, "DELETE FROM audio_metadata WHERE file_id = " . $fileId);
    mysqli_query($conn, "DELETE FROM video_metadata WHERE file_id = " . $fileId);

    if ($mediaGroup === 'Document') {
        $pageCount = (int) ($details['pageCount'] ?? 0);
        $wordCount = (int) ($details['wordCount'] ?? 0);
        $language = clean_text($details['language'] ?? 'English', 'English');
        $version = clean_text($details['version'] ?? '1.0', '1.0');
        $stmt = mysqli_prepare($conn, "INSERT INTO document_metadata (file_id, page_count, word_count, language, version_number) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
        mysqli_stmt_bind_param($stmt, 'iiiss', $fileId, $pageCount, $wordCount, $language, $version);
        if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException(mysqli_error($conn)); }
        $metadataId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "UPDATE multimedia_files SET doc_id = $metadataId, audio_id = NULL, video_id = NULL WHERE file_id = $fileId");
        return;
    }

    if ($mediaGroup === 'Audio') {
        $duration = (int) round((float) ($details['duration'] ?? 0));
        $bitrate = number_from_text($details['bitrate'] ?? 0);
        $frequency = number_from_text($details['frequency'] ?? 0);
        $genre = clean_text($details['genre'] ?? '');
        $stmt = mysqli_prepare($conn, "INSERT INTO audio_metadata (file_id, duration_seconds, bitrate_kbps, frequency_hz, genre_tag) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
        mysqli_stmt_bind_param($stmt, 'iiiis', $fileId, $duration, $bitrate, $frequency, $genre);
        if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException(mysqli_error($conn)); }
        $metadataId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "UPDATE multimedia_files SET audio_id = $metadataId, doc_id = NULL, video_id = NULL WHERE file_id = $fileId");
        return;
    }

    if ($mediaGroup === 'Video') {
        $duration = (int) round((float) ($details['duration'] ?? 0));
        $resolution = clean_text($details['resolution'] ?? '');
        $frameRate = (float) number_from_text($details['frameRate'] ?? 0);
        $codec = clean_text($details['codec'] ?? '');
        $stmt = mysqli_prepare($conn, "INSERT INTO video_metadata (file_id, duration_seconds, resolution, frame_rate, codec) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
        mysqli_stmt_bind_param($stmt, 'iisds', $fileId, $duration, $resolution, $frameRate, $codec);
        if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException(mysqli_error($conn)); }
        $metadataId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "UPDATE multimedia_files SET video_id = $metadataId, doc_id = NULL, audio_id = NULL WHERE file_id = $fileId");
    }
}

function number_from_text($value): int
{
    if (is_numeric($value)) {
        return (int) $value;
    }
    preg_match('/\d+/', (string) $value, $matches);
    return isset($matches[0]) ? (int) $matches[0] : 0;
}

function get_multimedia_asset($conn, int $id): ?array
{
    $result = mysqli_query($conn, "SELECT
            m.*,
            d.page_count,
            d.word_count,
            d.language,
            d.version_number,
            a.duration_seconds AS audio_duration_seconds,
            a.bitrate_kbps,
            a.frequency_hz,
            a.genre_tag,
            v.duration_seconds AS video_duration_seconds,
            v.resolution,
            v.frame_rate,
            v.codec
        FROM multimedia_files m
        LEFT JOIN document_metadata d ON d.file_id = m.file_id
        LEFT JOIN audio_metadata a ON a.file_id = m.file_id
        LEFT JOIN video_metadata v ON v.file_id = m.file_id
        WHERE m.file_id = " . $id);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? asset_from_row($row) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);

    try {
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid request data.');
        }

        $action = $payload['action'] ?? '';
        if ($action === 'save') {
            $asset = save_multimedia_asset($conn, $payload['asset'] ?? []);
            echo json_encode(['success' => true, 'asset' => $asset]);
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($payload['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid record ID.');
            }
            $stmt = mysqli_prepare($conn, "DELETE FROM multimedia_files WHERE file_id = ?");
            if (!$stmt) { throw new RuntimeException(mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true]);
            exit;
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $error) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $error->getMessage()]);
        exit;
    }
}

$serverAssets = get_multimedia_assets($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MediaVault - Multimedia Manager</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, sans-serif; }
    body { margin: 0; background: #f4f7fb; color: #222; }
    .container { width: 95%; max-width: 1220px; margin: 28px auto; }
    .header { background: #173b63; color: white; padding: 18px 22px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .header-text h1 { margin: 0; font-size: 28px; }
    .header-text p { margin: 8px 0 0; opacity: 0.86; }
    .card, .details-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); margin-bottom: 20px; }
    .top-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .top-row h2, .details-panel h3 { margin: 0; }
    .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 9px 14px; border: none; border-radius: 6px; cursor: pointer; color: white; text-decoration: none; font-size: 14px; transition: transform 0.15s ease, background-color 0.15s ease; white-space: nowrap; }
    .btn:hover { transform: translateY(-1px); }
    .btn-primary { background: #173b63; }
    .btn-secondary { background: #2d7dd2; }
    .btn-edit { background: #b66b00; }
    .btn-delete { background: #c83f3a; }
    .btn-view { background: #21844a; }
    .btn-home { background: #ffffff; color: #173b63; font-weight: bold; border: 1px solid #ffffff; }
    .btn-home:hover { background: #e7eef8; color: #173b63; }
    .table-wrap { width: 100%; overflow-x: auto; margin-top: 16px; border: 1px solid #d8e2ed; border-radius: 8px; }
    table { width: 100%; min-width: 900px; border-collapse: collapse; }
    th, td { border: 1px solid #e1e8f0; padding: 12px; text-align: left; }
    th { background: #173b63; color: white; }
    .filter-bar { display: grid; grid-template-columns: minmax(220px, 1fr) 180px 160px auto; gap: 12px; align-items: end; margin-top: 18px; padding: 14px; border: 1px solid #dce7f5; border-radius: 8px; background: #f8fbff; }
    .filter-bar label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 13px; }
    .filter-bar input, .filter-bar select { width: 100%; height: 38px; padding: 6px; border: 1px solid #ccd8e6; border-radius: 4px; }
    .details-panel { display: none; background: #fff; border-left: 5px solid #21844a; }
    .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 16px; }
    .detail-item { border: 1px solid #e0e7ef; border-radius: 6px; padding: 10px; background: #f9fbfd; }
    .detail-item strong { display: block; color: #555; font-size: 12px; text-transform: uppercase; }
    .badge { display: inline-block; padding: 4px 9px; border-radius: 999px; background: #e7eef8; color: #173b63; font-weight: bold; font-size: 12px; }
    
    /* MODAL STYLES FOR INGESTION FORM */
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); display: none; z-index: 100; }
    .modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none; background: white; border-radius: 8px; width: min(90%, 600px); max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); padding: 24px; z-index: 101; }
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    .metadata-section { background: #f0f4f8; padding: 12px; border-radius: 6px; margin-top: 10px; display: none; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-text">
        <h1>MediaVault</h1>
        <p>Upload PDF, DOCX, MP3, WAV, and MP4 files, then manage and inspect their metadata.</p>
      </div>
      <div>
        <a href="../index.php" class="btn btn-home">🏠 Back to Home</a>
      </div>
    </div>

    <div class="card">
      <div class="top-row">
        <div>
          <h2>Multimedia Files</h2>
          <p style="color:#666; margin:4px 0 0 0; font-size:14px;">Stored details include file name, file type, file size, upload timestamp, user ID, and scanned media metadata.</p>
        </div>
        <button class="btn btn-primary" id="openUploadBtn">Add / Upload Asset</button>
      </div>

      <div class="filter-bar">
        <div>
          <label for="filterSearch">Search File</label>
          <input type="search" id="filterSearch" placeholder="Search by file name..." />
        </div>
        <div>
          <label for="filterType">File Type</label>
          <select id="filterType">
            <option value="">All types</option>
            <option value="Document">Document</option>
            <option value="Audio">Audio</option>
            <option value="Video">Video</option>
          </select>
        </div>
        <div>
          <label for="filterUser">User ID</label>
          <input type="number" id="filterUser" min="1" placeholder="Any User" />
        </div>
        <div>
          <button type="button" class="btn btn-secondary" id="resetFiltersBtn">Reset</button>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>File Name</th>
              <th>Group</th>
              <th>Size</th>
              <th>Upload Date</th>
              <th>User ID</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="fileTableBody">
            </tbody>
        </table>
      </div>
    </div>

    <div class="details-panel" id="fileDetailsPanel">
      <div class="top-row">
        <h3>🔍 Asset Structural Metadata Inspection</h3>
        <button class="btn btn-secondary" id="hideDetailsBtn">⬅️ Back to List</button>
      </div>
      <div id="fileDetailsContent"></div>
    </div>
  </div>

  <div class="modal-backdrop" id="modalBackdrop"></div>
  <div class="modal" id="assetModal">
    <h3 id="modalTitle">Ingest New Multimedia Asset</h3>
    <form id="assetForm">
      <input type="hidden" id="assetId" value="0" />
      
      <div class="form-group">
        <label for="formFileName">File Name (with extension, e.g., report.pdf, song.mp3)</label>
        <input type="text" id="formFileName" required placeholder="example.mp4" />
      </div>
      
      <div class="form-group">
        <label for="formMediaGroup">Media Group</label>
        <select id="formMediaGroup" required>
          <option value="Document">Document (PDF, DOCX)</option>
          <option value="Audio">Audio (MP3, WAV)</option>
          <option value="Video">Video (MP4)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="formFileSize">File Size (Bytes)</label>
        <input type="number" id="formFileSize" required min="1" placeholder="e.g. 2048576" />
      </div>

      <div class="form-group">
        <label for="formUserId">Uploader User ID</label>
        <input type="number" id="formUserId" required min="1" placeholder="e.g. 12" />
      </div>

      <div class="metadata-section" id="metaDocument">
        <h4>Document Sub-table Attributes</h4>
        <div class="form-group">
          <label>Page Count</label>
          <input type="number" id="docPageCount" value="0" />
        </div>
        <div class="form-group">
          <label>Word Count</label>
          <input type="number" id="docWordCount" value="0" />
        </div>
        <div class="form-group">
          <label>Language</label>
          <input type="text" id="docLanguage" value="English" />
        </div>
        <div class="form-group">
          <label>Version</label>
          <input type="text" id="docVersion" value="1.0" />
        </div>
      </div>

      <div class="metadata-section" id="metaAudio">
        <h4>Audio Sub-table Attributes</h4>
        <div class="form-group">
          <label>Duration (Seconds)</label>
          <input type="number" id="audioDuration" value="0" />
        </div>
        <div class="form-group">
          <label>Bitrate (kbps)</label>
          <input type="number" id="audioBitrate" value="0" />
        </div>
        <div class="form-group">
          <label>Frequency (Hz)</label>
          <input type="number" id="audioFrequency" value="0" />
        </div>
        <div class="form-group">
          <label>Genre Tag</label>
          <input type="text" id="audioGenre" value="Unknown" />
        </div>
      </div>

      <div class="metadata-section" id="metaVideo">
        <h4>Video Sub-table Attributes</h4>
        <div class="form-group">
          <label>Duration (Seconds)</label>
          <input type="number" id="videoDuration" value="0" />
        </div>
        <div class="form-group">
          <label>Resolution</label>
          <input type="text" id="videoResolution" value="1920x1080" />
        </div>
        <div class="form-group">
          <label>Frame Rate (fps)</label>
          <input type="number" step="0.01" id="videoFrameRate" value="30" />
        </div>
        <div class="form-group">
          <label>Codec</label>
          <input type="text" id="videoCodec" value="H.264" />
        </div>
      </div>

      <div style="margin-top:20px; text-align:right;">
        <button type="button" class="btn btn-secondary" id="closeModalBtn" style="background:#555;">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Asset</button>
      </div>
    </form>
  </div>

  <script>
    // Injeksi JSON data dari PHP secara selamat
    const initialAssets = <?php echo json_encode($serverAssets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    let assets = Array.isArray(initialAssets) ? initialAssets : [];

    // DOM Elements
    const tableBody = document.getElementById('fileTableBody');
    const filterSearch = document.getElementById('filterSearch');
    const filterType = document.getElementById('filterType');
    const filterUser = document.getElementById('filterUser');
    const resetBtn = document.getElementById('resetFiltersBtn');
    
    const detailsPanel = document.getElementById('fileDetailsPanel');
    const detailsContent = document.getElementById('fileDetailsContent');
    const hideDetailsBtn = document.getElementById('hideDetailsBtn');

    const modal = document.getElementById('assetModal');
    const backdrop = document.getElementById('modalBackdrop');
    const openUploadBtn = document.getElementById('openUploadBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const assetForm = document.getElementById('assetForm');
    const formMediaGroup = document.getElementById('formMediaGroup');

    // --- RENDER TABLE ---
    function renderTable() {
      const searchVal = filterSearch.value.toLowerCase().trim();
      const typeVal = filterType.value;
      const userVal = filterUser.value;

      const filtered = assets.filter(asset => {
        if (searchVal && !asset.fileName.toLowerCase().includes(searchVal)) return false;
        if (typeVal && asset.mediaGroup !== typeVal) return false;
        if (userVal && parseInt(asset.userId) !== parseInt(userVal)) return false;
        return true;
      });

      if (filtered.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No multimedia assets found match criteria.</td></tr>`;
        return;
      }

      tableBody.innerHTML = filtered.map(asset => `
        <tr>
          <td>${asset.id}</td>
          <td><strong>${asset.fileName}</strong></td>
          <td><span class="badge">${asset.mediaGroup}</span> <small>(${asset.fileType})</small></td>
          <td>${(asset.fileSize / 1024).toFixed(2)} KB</td>
          <td>${asset.uploadDate}</td>
          <td>User #${asset.userId}</td>
          <td>
            <button class="btn btn-view" onclick="viewAsset(${asset.id})">Inspect</button>
            <button class="btn btn-edit" onclick="editAsset(${asset.id})">Edit</button>
            <button class="btn btn-delete" onclick="deleteAsset(${asset.id})">Delete</button>
          </td>
        </tr>
      `).join('');
    }

    // --- FILTER EVENTS ---
    [filterSearch, filterType, filterUser].forEach(el => el.addEventListener('input', renderTable));
    resetBtn.addEventListener('click', () => {
      filterSearch.value = '';
      filterType.value = '';
      filterUser.value = '';
      renderTable();
    });

    // --- DETAIL INSPECTION WITH BACK BUTTON ---
    window.viewAsset = function(id) {
      const asset = assets.find(a => a.id === id);
      if (!asset) return;

      let subMetadataHTML = '';
      if (asset.mediaGroup === 'Document') {
        subMetadataHTML = `
          <div class="detail-item"><strong>Pages</strong> ${asset.details.pageCount}</div>
          <div class="detail-item"><strong>Words</strong> ${asset.details.wordCount}</div>
          <div class="detail-item"><strong>Language</strong> ${asset.details.language}</div>
          <div class="detail-item"><strong>Version</strong> v${asset.details.version}</div>
        `;
      } else if (asset.mediaGroup === 'Audio') {
        subMetadataHTML = `
          <div class="detail-item"><strong>Duration</strong> ${asset.details.duration} seconds</div>
          <div class="detail-item"><strong>Bitrate</strong> ${asset.details.bitrate}</div>
          <div class="detail-item"><strong>Frequency</strong> ${asset.details.frequency}</div>
          <div class="detail-item"><strong>Genre</strong> ${asset.details.genre}</div>
        `;
      } else if (asset.mediaGroup === 'Video') {
        subMetadataHTML = `
          <div class="detail-item"><strong>Duration</strong> ${asset.details.duration} seconds</div>
          <div class="detail-item"><strong>Resolution</strong> ${asset.details.resolution}</div>
          <div class="detail-item"><strong>Frame Rate</strong> ${asset.details.frameRate}</div>
          <div class="detail-item"><strong>Codec</strong> ${asset.details.codec}</div>
        `;
      }

      detailsContent.innerHTML = `
        <div style="margin-top:10px;">
          <h4>Baseline File Attributes</h4>
          <p><strong>System ID:</strong> ${asset.id} | <strong>File Name:</strong> ${asset.fileName} | <strong>Size:</strong> ${(asset.fileSize/1024).toFixed(2)} KB</p>
          <h4>Relational Sub-Table Core Structural Metadata</h4>
          <div class="details-grid">${subMetadataHTML}</div>
        </div>
      `;
      detailsPanel.style.display = 'block';
      detailsPanel.scrollIntoView({ behavior: 'smooth' });
    };

    // FIX: Back Button Action to hide details panel
    hideDetailsBtn.addEventListener('click', () => {
      detailsPanel.style.display = 'none';
    });

    // --- FORM INGESTION MODAL CONTROL ---
    formMediaGroup.addEventListener('change', () => {
      document.getElementById('metaDocument').style.display = 'none';
      document.getElementById('metaAudio').style.display = 'none';
      document.getElementById('metaVideo').style.display = 'none';
      
      const val = formMediaGroup.value;
      if(val) document.getElementById('meta' + val).style.display = 'block';
    });

    // --- MODAL GENERICS ---
    function showModal(title, isEdit = false) {
      document.getElementById('modalTitle').innerText = title;
      modal.style.display = 'block';
      backdrop.style.display = 'block';
      formMediaGroup.dispatchEvent(new Event('change'));
    }

    function closeModal() {
      modal.style.display = 'none';
      backdrop.style.display = 'none';
      assetForm.reset();
      document.getElementById('assetId').value = "0";
    }

    openUploadBtn.addEventListener('click', () => {
      document.getElementById('assetId').value = "0";
      showModal('Ingest New Multimedia Asset');
    });
    closeModalBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    // --- EDIT ASSET ---
    window.editAsset = function(id) {
      const asset = assets.find(a => a.id === id);
      if (!asset) return;

      document.getElementById('assetId').value = asset.id;
      document.getElementById('formFileName').value = asset.fileName;
      document.getElementById('formMediaGroup').value = asset.mediaGroup;
      document.getElementById('formFileSize').value = asset.fileSize;
      document.getElementById('formUserId').value = asset.userId;

      formMediaGroup.dispatchEvent(new Event('change'));

      if (asset.mediaGroup === 'Document') {
        document.getElementById('docPageCount').value = asset.details.pageCount;
        document.getElementById('docWordCount').value = asset.details.wordCount;
        document.getElementById('docLanguage').value = asset.details.language;
        document.getElementById('docVersion').value = asset.details.version;
      } else if (asset.mediaGroup === 'Audio') {
        document.getElementById('audioDuration').value = asset.details.duration;
        document.getElementById('audioBitrate').value = parseInt(asset.details.bitrate);
        document.getElementById('audioFrequency').value = parseInt(asset.details.frequency);
        document.getElementById('audioGenre').value = asset.details.genre;
      } else if (asset.mediaGroup === 'Video') {
        document.getElementById('videoDuration').value = asset.details.duration;
        document.getElementById('videoResolution').value = asset.details.resolution;
        document.getElementById('videoFrameRate').value = parseFloat(asset.details.frameRate);
        document.getElementById('videoCodec').value = asset.details.codec;
      }

      showModal('Modify Asset Metadata & Route Structure');
    };

    // --- CRUD: SUBMIT/SAVE ASSET ---
    assetForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const id = parseInt(document.getElementById('assetId').value);
      const mediaGroup = formMediaGroup.value;

      let details = {};
      if (mediaGroup === 'Document') {
        details = {
          pageCount: document.getElementById('docPageCount').value,
          wordCount: document.getElementById('docWordCount').value,
          language: document.getElementById('docLanguage').value,
          version: document.getElementById('docVersion').value,
        };
      } else if (mediaGroup === 'Audio') {
        details = {
          duration: document.getElementById('audioDuration').value,
          bitrate: document.getElementById('audioBitrate').value,
          frequency: document.getElementById('audioFrequency').value,
          genre: document.getElementById('audioGenre').value,
        };
      } else if (mediaGroup === 'Video') {
        details = {
          duration: document.getElementById('videoDuration').value,
          resolution: document.getElementById('videoResolution').value,
          frameRate: document.getElementById('videoFrameRate').value,
          codec: document.getElementById('videoCodec').value,
        };
      }

      const assetPayload = {
        id: id,
        fileName: document.getElementById('formFileName').value,
        mediaGroup: mediaGroup,
        fileSize: parseInt(document.getElementById('formFileSize').value),
        userId: parseInt(document.getElementById('formUserId').value),
        details: details
      };

      fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save', asset: assetPayload })
      })
      .then(res => res.json())
      .then(data => {
        if(data.success) {
          if (id > 0) {
            const idx = assets.findIndex(a => a.id === id);
            assets[idx] = data.asset;
          } else {
            assets.unshift(data.asset);
          }
          closeModal();
          renderTable();
          alert('Asset dynamic ingestion successfully processed!');
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(err => alert('Network processing failure.'));
    });

    // --- CRUD: DELETE ASSET ---
    window.deleteAsset = function(id) {
      if(!confirm('Are you absolutely sure you want to completely erase this media asset record?')) return;
      
      fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
      })
      .then(res => res.json())
      .then(data => {
        if(data.success) {
          assets = assets.filter(a => a.id !== id);
          renderTable();
          if(detailsPanel.style.display === 'block') detailsPanel.style.display = 'none';
          alert('Asset safely purged from the multimedia system.');
        } else {
          alert('Error: ' + data.message);
        }
      });
    };

    // Initial Bootstrap Run
    renderTable();
  </script>
</body>
</html>