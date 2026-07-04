<?php
/**
 * MediaVault - Unified Hybrid Search Display
 * Renders tabbed search forms and displays matched multimedia query results.
 */
session_start();

require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../config/db.php'; 

$message = '';
$messageType = 'ok';

// Track which tab should stay open after a page refresh
$active_tab = 'ABR'; 

// --- 1. HYBRID SEARCH PROCESSOR ENGINE (Intercepts POST requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $engine_type = $_POST['search_engine_type'] ?? 'ABR';
    $active_tab = $engine_type; // Set active view to match the submitted engine type
    $results_pool = [];
    
    try {
        if (!isset($pdo)) {
            throw new Exception("Database connection layer initialization missing.");
        }

        if ($engine_type === 'ABR') {
            $file_type = trim($_POST['file_type'] ?? '');
            $size_range = $_POST['size_range'] ?? '';

            $sql = "SELECT * FROM multimedia_files WHERE 1=1";
            $params = [];

            if (!empty($file_type)) {
                $sql .= " AND (file_type LIKE :file_type OR file_name LIKE :file_type_alt)";
                $params['file_type'] = '%' . $file_type . '%';
                $params['file_type_alt'] = '%' . $file_type . '%';
            }

            if (!empty($size_range)) {
                if ($size_range === 'small') {
                    $sql .= " AND size_kb < 5120"; 
                } elseif ($size_range === 'medium') {
                    $sql .= " AND size_kb >= 5120 AND size_kb <= 51200"; 
                } elseif ($size_range === 'large') {
                    $sql .= " AND size_kb > 51200"; 
                }
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $_SESSION['search_mode'] = "Attribute-Based Retrieval (ABR)";

        } elseif ($engine_type === 'TBR') {
            $keyword = trim($_POST['keyword'] ?? '');
            
            $sql = "SELECT * FROM multimedia_files WHERE (file_name LIKE :keyword OR file_type LIKE :keyword)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['keyword' => '%' . $keyword . '%']);
            $results_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $_SESSION['search_mode'] = "Text-Based Retrieval (TBR)";

        } elseif ($engine_type === 'CBR') {
            $feature_value = trim($_POST['feature_value'] ?? '');
            
            if ($feature_value === '') {
                $results_pool = [];
            } else {
                // Normalize the input structural query sequence
                $clean_input = strtolower(str_replace(' ', '', $feature_value));
                
                // Comprehensive hybrid cross-join matching content features and video specifications
                $sql = "SELECT DISTINCT f.* FROM multimedia_files f
                        LEFT JOIN content_features cf ON f.file_id = cf.file_id
                        LEFT JOIN video_metadata vm ON f.file_id = vm.file_id
                        WHERE LOWER(REPLACE(cf.feature_value, ' ', '')) LIKE :feat_val
                           OR LOWER(REPLACE(vm.resolution, ' ', '')) LIKE :feat_val_alt";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'feat_val'     => '%' . $clean_input . '%',
                    'feat_val_alt' => '%' . $clean_input . '%'
                ]);
                $results_pool = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $_SESSION['search_mode'] = "Content-Based Retrieval (CBR) (Match: '" . htmlspecialchars($feature_value) . "')";
        }

        $_SESSION['search_results'] = $results_pool;
        $_SESSION['active_tab'] = $active_tab;

    } catch (PDOException $e) {
        $message = 'DB Core Error: ' . $e->getMessage();
        $messageType = 'error';
    } catch (Exception $e) {
        $message = 'System Fault: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Persist active tab across basic pagination or custom column sorting maps
if (isset($_SESSION['active_tab'])) {
    $active_tab = $_SESSION['active_tab'];
}

// --- 2. INTEGRITY CHECK FLAGS ---
if (empty($_SESSION)) {
    $message = 'Session Context Error: The PHP Session is unconfigured.';
    $messageType = 'error';
}

if ($message === '' && !isset($_SESSION['search_results'])) {
    $message = 'No active search parameters found. Execute a query option tab below.';
    $messageType = 'error';
}

$results = $_SESSION['search_results'] ?? [];
$mode = $_SESSION['search_mode'] ?? 'Engine Retrieval Matrix';

// --- 3. IN-MEMORY SORTING MATRIX ENGINE ---
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$direction = strtoupper(trim((string)($_GET['direction'] ?? 'DESC')));

if (!empty($results) && is_array($results)) {
    usort($results, function($a, $b) use ($sort, $direction) {
        $keyMapA = [
            'newest' => $a['file_id'] ?? $a['FILE_ID'] ?? 0,
            'name'   => $a['file_name'] ?? $a['FILE_NAME'] ?? '',
            'type'   => $a['file_type'] ?? $a['FILE_TYPE'] ?? '',
            'size'   => $a['size_kb'] ?? $a['SIZE_KB'] ?? 0,
            'date'   => $a['upload_timestamp'] ?? $a['UPLOAD_TIMESTAMP'] ?? '',
        ];
        
        $keyMapB = [
            'newest' => $b['file_id'] ?? $b['FILE_ID'] ?? 0,
            'name'   => $b['file_name'] ?? $b['FILE_NAME'] ?? '',
            'type'   => $b['file_type'] ?? $b['FILE_TYPE'] ?? '',
            'size'   => $b['size_kb'] ?? $b['SIZE_KB'] ?? 0,
            'date'   => $b['upload_timestamp'] ?? $b['UPLOAD_TIMESTAMP'] ?? '',
        ];

        $valA = $keyMapA[$sort] ?? ($a['file_id'] ?? $a['FILE_ID'] ?? 0);
        $valB = $keyMapB[$sort] ?? ($b['file_id'] ?? $b['FILE_ID'] ?? 0);

        if (is_numeric($valA) && is_numeric($valB)) {
            $cmp = $valA <=> $valB;
        } else {
            $cmp = strcasecmp((string)$valA, (string)$valB);
        }

        return ($direction === 'ASC') ? $cmp : -$cmp;
    });
}

// Clear Filters Logic Handler Trigger
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    unset($_SESSION['search_results'], $_SESSION['active_tab'], $_SESSION['search_mode']);
    header('Location: search_results.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Engine - MediaVault</title>
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
            --danger: #c92a2a;
            --danger-dark: #9f1d1d;
            --success-bg: #e8f7ee;
            --success-text: #17643a;
            --error-bg: #fdeaea;
            --error-text: #a12727;
            --warning-bg: #fff7ed;
            --warning-text: #9a3412;
            --shadow: 0 18px 45px rgba(31, 41,55, 0.08);
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
        h1, h2, h3 { margin: 0; }
        h1 { color: #fff; font-size: 31px; line-height: 1.15; }
        h2 { color: #152238; font-size: 21px; margin-bottom: 8px; }
        h3 { color: #152238; font-size: 18px; margin-bottom: 12px; }
        .page-header .muted { color: #d8e2f0; margin-bottom: 0; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
            padding: 24px;
        }
        
        /* Tab Navigation Controls */
        .tabs-nav { display: flex; gap: 6px; margin-bottom: 20px; border-bottom: 1px solid var(--line); padding-bottom: 1px; }
        .tab-btn {
            background: none; border: 1px solid transparent; padding: 12px 16px; font-weight: bold; color: var(--muted);
            cursor: pointer; border-radius: 8px 8px 0 0; margin-bottom: -1px; font-size: 14px; transition: all 0.15s;
        }
        .tab-btn:hover { color: var(--text); background: var(--surface-soft); }
        .tab-btn.active { color: var(--primary); background: var(--surface); border-color: var(--line) var(--line) var(--surface); }

        .tab-content-panel { display: none; background: var(--surface-soft); padding: 20px; border-radius: 8px; border: 1px solid var(--line); }
        .tab-content-panel.active { display: block; }

        .form-group { margin-bottom: 16px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 16px; }
        .form-row .form-group { flex: 1; min-width: 240px; }
        
        label { display: block; font-weight: bold; margin-bottom: 6px; font-size: 13px; color: var(--text); }
        select, input[type="text"] {
            background: #fff; border: 1px solid var(--line-strong); border-radius: 8px;
            color: var(--text); width: 100%; min-height: 44px; padding: 11px 12px; font-size: 14px;
        }
        select:focus, input[type="text"]:focus { border-color: var(--primary); outline: 3px solid rgba(29, 78, 216, 0.16); }
        
        .btn {
            background: var(--primary); border: 0; border-radius: 8px; color: #fff; cursor: pointer;
            display: inline-block; font-weight: 800; min-height: 42px; padding: 11px 20px; text-align: center;
            text-decoration: none; transition: background 0.15s, box-shadow 0.15s, transform 0.15s; font-size: 14px;
        }
        .btn:hover { background: var(--primary-dark); box-shadow: 0 10px 22px rgba(29, 78, 216, 0.22); transform: translateY(-1px); }
        .btn.secondary { background: #edf2f7; color: #1f2d3d; }
        .btn.secondary:hover { background: #dbe5ef; box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12); }
        .btn.warning { background: #f59e0b; color: #fff; }
        .btn.warning:hover { background: #d97706; box-shadow: 0 10px 22px rgba(217, 119, 6, 0.22); }
        .btn.danger { background: var(--danger); }

        /* Dashboard Results Layout */
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .results-info-tag { font-family: monospace; font-weight: bold; color: var(--primary); background: var(--surface-soft); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--line); }
        
        .sorting-bar { display: flex; align-items: center; gap: 10px; background: var(--surface-soft); padding: 12px; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 20px; }
        .sorting-bar select { min-height: 36px; padding: 6px 12px; width: auto; flex: initial; }

        .results-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        .result-card { background: var(--surface-soft); border: 1px solid var(--line); border-radius: 8px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; }
        .result-card-title { font-weight: bold; font-size: 16px; color: var(--text); margin-bottom: 8px; word-break: break-all; }
        
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 800; border-radius: 4px; text-transform: uppercase; margin-bottom: 10px; border: 1px solid transparent;}
        .badge.type-document { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .badge.type-audio { background: #fdf2f8; color: #9d174d; border-color: #fbcfe8; }
        .badge.type-video { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }

        .message { border-radius: 8px; font-weight: 800; margin-bottom: 16px; padding: 13px 14px; font-size: 14px; }
        .ok { background: var(--success-bg); color: var(--success-text); }
        .error { background: var(--error-bg); color: var(--error-text); }
        .muted { color: var(--muted); line-height: 1.55; font-size: 14px; }

        @media (max-width: 720px) {
            .container { padding: 18px 12px 30px; }
            .header-row { display: block; }
            .header-row .btn { margin-top: 14px; width: 100%; }
            .tabs-nav { flex-direction: column; border-bottom: none; }
            .tab-btn { border-radius: 8px; border: 1px solid var(--line); }
            .form-row { display: block; }
            .sorting-bar { display: block; }
            .sorting-bar form { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
            .sorting-bar select, .sorting-bar .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="page-header">
            <div class="header-row">
                <div>
                    <p class="brand-mark">MediaVault Multimedia</p>
                    <h1>Hybrid Search Engine</h1>
                    <p class="muted">Query your enterprise vault using structural properties, keywords, or features.</p>
                </div>
                <a class="btn secondary" href="../index_admin.php">Back to Dashboard</a>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <section class="panel">
            <h2>Search Criteria</h2>
            <p class="muted" style="margin-bottom: 20px;">Select a system retrieval mechanism interface strategy below:</p>
            
            <div class="tabs-nav">
                <button class="tab-btn <?php echo $active_tab === 'ABR' ? 'active' : ''; ?>" onclick="switchTab('ABR')">📁 Properties Filter (ABR)</button>
                <button class="tab-btn <?php echo $active_tab === 'TBR' ? 'active' : ''; ?>" onclick="switchTab('TBR')">🔍 Keyword Tag (TBR)</button>
                <button class="tab-btn <?php echo $active_tab === 'CBR' ? 'active' : ''; ?>" onclick="switchTab('CBR')">⚙️ Quality Features (CBR)</button>
            </div>

            <div id="pane-ABR" class="tab-content-panel <?php echo $active_tab === 'ABR' ? 'active' : ''; ?>">
                <h3>Attribute-Based Retrieval</h3>
                <form action="search_results_admin.php" method="POST">
                    <input type="hidden" name="search_engine_type" value="ABR">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Target File Format</label>
                            <select name="file_type">
                                <option value="">-- Show All Formats --</option>
                                <option value="PDF">📄 PDF Document</option>
                                <option value="DOCX">📝 Word Document</option>
                                <option value="MP3">🎵 MP3 Audio</option>
                                <option value="WAV">🎼 WAV Audio</option>
                                <option value="MP4">🎬 MP4 Video</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>File Size Threshold</label>
                            <select name="size_range">
                                <option value="">-- Any Size (No Limit) --</option>
                                <option value="small">Small Files (Under 5 MB)</option>
                                <option value="medium">Medium Files (5 MB to 50 MB)</option>
                                <option value="large">Large Files (Over 50 MB)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn">Run Property Query</button>
                </form>
            </div>

            <div id="pane-TBR" class="tab-content-panel <?php echo $active_tab === 'TBR' ? 'active' : ''; ?>">
                <h3>Text-Based Retrieval</h3>
                <form action="search_results.php" method="POST">
                    <input type="hidden" name="search_engine_type" value="TBR">
                    <div class="form-group">
                        <label>Enter Keyword or Title Sequence</label>
                        <input type="text" name="keyword" placeholder="e.g. tutorial, project_spec, background_track..." required>
                    </div>
                    <button type="submit" class="btn">Run Keyword Search</button>
                </form>
            </div>

            <div id="pane-CBR" class="tab-content-panel <?php echo $active_tab === 'CBR' ? 'active' : ''; ?>">
                <h3>Content-Based Retrieval</h3>
                <form action="search_results.php" method="POST">
                    <input type="hidden" name="search_engine_type" value="CBR">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Dynamic Metadata Presets</label>
                            <select id="featureDropdown" onchange="syncFeatureValue()">
                                <option value="">-- Select dimensional metrics --</option>
                                <option value="1920x1080">🎬 Screen Resolution (1920x1080)</option>
                                <option value="16:9">📐 Aspect Ratio (16:9)</option>
                                <option value="#0000FF">🎨 Color Profile Hex (#0000FF)</option>
                                <option value="440Hz">🎵 Sound Pitch (440Hz)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Media Quality Metric Value</label>
                            <input type="text" id="featureInput" name="feature_value" placeholder="Type custom value configuration directly..." required>
                        </div>
                    </div>
                    <button type="submit" class="btn warning">Evaluate Features</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="dashboard-header">
                <div>
                    <h2>Query Results Dashboard</h2>
                    <p class="muted">Active System Matrix Strategy: <span class="results-info-tag"><?php echo htmlspecialchars($mode); ?></span></p>
                </div>
                <a href="search_results.php?clear=1" class="btn secondary" style="min-height:36px; padding: 8px 14px;">🔄 Clear Filter</a>
            </div>

            <?php if (!empty($results)): ?>
                <div class="sorting-bar">
                    <label style="margin-bottom:0; margin-right:10px;">Sort Results By:</label>
                    <form method="GET" action="search_results.php" style="display: inline-flex; gap: 8px; flex-wrap: wrap; align-items:center;">
                        <select name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>File ID Sequence</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Alphabetical Name</option>
                            <option value="type" <?php echo $sort === 'type' ? 'selected' : ''; ?>>Format Category</option>
                            <option value="size" <?php echo $sort === 'size' ? 'selected' : ''; ?>>Allocated Data Size</option>
                            <option value="date" <?php echo $sort === 'date' ? 'selected' : ''; ?>>Registration Timestamp</option>
                        </select>
                        <select name="direction">
                            <option value="DESC" <?php echo $direction === 'DESC' ? 'selected' : ''; ?>>Descending Ordering</option>
                            <option value="ASC" <?php echo $direction === 'ASC' ? 'selected' : ''; ?>>Ascending Ordering</option>
                        </select>
                        <button class="btn secondary" type="submit" style="min-height:36px; padding:0 12px;">Apply Matrix Sort</button>
                    </form>
                </div>

                <div class="results-grid">
                    <?php foreach ($results as $item): 
                        $fId = (int)($item['file_id'] ?? $item['FILE_ID'] ?? 0);
                        $fName = htmlspecialchars($item['file_name'] ?? $item['FILE_NAME'] ?? 'Unnamed Asset File');
                        $fType = htmlspecialchars($item['file_type'] ?? $item['FILE_TYPE'] ?? 'Unknown');
                        $fSize = number_format((float)($item['size_kb'] ?? $item['SIZE_KB'] ?? 0), 2);
                        $badgeClass = 'type-' . strtolower($fType);
                    ?>
                        <div class="result-card">
                            <div>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $fType; ?></span>
                                <div class="result-card-title">#<?php echo $fId; ?> - <?php echo $fName; ?></div>
                                <p class="muted" style="font-size:12px; margin-top:4px; margin-bottom:14px;">Capacity Metric: <?php echo $fSize; ?> KB</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <p class="muted">No matching assets found in the storage arrays matching active parameters. Try alternative parameters above.</p>
            <?php endif; ?>
        </section>
    </main>

    <script>
        function switchTab(tabId) {
            // Deactivate all tab elements
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content-panel').forEach(panel => panel.classList.remove('active'));
            
            // Activate selected option
            event.currentTarget.classList.add('active');
            document.getElementById('pane-' + tabId).classList.add('active');
        }

        function syncFeatureValue() {
            const dropdown = document.getElementById('featureDropdown');
            const inputField = document.getElementById('featureInput');
            if(dropdown.value !== "") {
                inputField.value = dropdown.value;
            }
        }
    </script>
</body>
</html>