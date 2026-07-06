<?php
session_start();

// 1. Dapatkan nama group daripada URL parameter atau nama folder semasa
if (!isset($_GET['group'])) {
    $group = basename(dirname(__FILE__));
} else {
    $group = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['group']);
}

// 2. Panggil db.php (Naik 2 tingkat ke atas untuk ke folder 'All')
include '../../db.php'; 

// 3. Ambil data full_name dan matric_no sahaja menggunakan INNER JOIN
$members = [];
$sql = "SELECT S.full_name, S.matric_no FROM stu S 
        JOIN groupdb G ON S.group_no = G.groupID 
        WHERE G.groupID = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt->close();
}
$conn->close(); 
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Senarai Ahli Kumpulan | <?php echo htmlspecialchars($group); ?></title>
    <style>
        body { background: #0f0f0f; color: white; font-family: sans-serif; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 40px; }
        
        /* Gaya reka bentuk jadual (Table Styling) */
        .table-container { border: 1px solid #444; border-radius: 12px; overflow: hidden; background: rgba(255,255,255,0.02); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        /* FONT SIZE BESAR: Saiz padding dan font th & td dibesarkan */
        th, td { padding: 22px 30px; border-bottom: 1px solid #333; font-size: 1.3rem; }
        th { background: #161616; color: #00d2ff; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 1px; }
        
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.04); transition: 0.2s; }
        
        /* Memastikan jika ada data XXXXX yang panjang, ia tidak melimpah keluar */
        .text-break { word-break: break-all; line-height: 1.5; font-size: 1.35rem; font-weight: 500; }
        .matrix-code { color: #00d2ff; font-weight: bold; font-family: monospace; font-size: 1.4rem; }
        .bil-col { font-size: 1.3rem; font-weight: bold; }
        
        .btn-back { display: inline-block; padding: 14px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; background: #555; color: white; transition: 0.3s; margin-top: 40px; font-size: 1.1rem; }
        .btn-back:hover { background: #666; }
        .btn-gr06 {
            display: inline-block;
            padding: 16px 35px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            background: #00d2ff;
            color: #0f0f0f;
            transition: 0.3s;
            margin-top: 40px;
            font-size: 1.2rem;
            border: 2px solid #00d2ff;
            margin-right: 15px;
        }
        .btn-gr06:hover {
            background: transparent;
            color: #00d2ff;
            transform: scale(1.02);
        }
        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>SENARAI AHLI KUMPULAN</h1>
    <div style="border: 1px solid #00d2ff; padding: 10px 25px; font-size: 1.6rem; border-radius: 5px; font-weight: bold;">
        GROUP: <?php echo htmlspecialchars($group); ?>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th style="width: 100px;">BIL</th>
                <th>NAMA PENUH</th>
                <th style="width: 350px;">NO. MATRIK</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; color: #ff4444; padding: 40px; font-size: 1.4rem;">
                        Tiada data ahli kumpulan ditemui untuk kod group "<?php echo htmlspecialchars($group); ?>".
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as $index => $row): ?>
                    <tr>
                        <td class="bil-col"><?php echo $index + 1; ?></td>
                        <td class="text-break" style="text-transform: uppercase;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td class="matrix-code"><?php echo htmlspecialchars($row['matric_no'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="../../dashboard.php?group=<?php echo urlencode($group); ?>" class="btn-back">BACK TO DASHBOARD</a>
<a href="https://bitp3353.utem.edu.my/2026/all/GroupMDB/GS05/MediaVault/index.php" class="btn-gr06">
            🚀 GO TO GS05 DASHBOARD
        </a>

</body>
</html>