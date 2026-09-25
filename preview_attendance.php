<?php
require_once 'config.php';
$data = $_SESSION['csv_preview_data'] ?? [];
if (empty($data)) { redirect('upload_attendance.php'); }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Preview Attendance Import</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f5f7fa; }
        .box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid #eee; text-align: left; }
        .btn-confirm { background: #48bb78; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Confirm Attendance Import</h2>
        <p>Review the first 10 rows before saving to the database.</p>
        <table>
            <tr><th>Person ID</th><th>Name</th><th>Date</th><th>Status</th></tr>
            <?php foreach(array_slice($data, 0, 10) as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['person_id']) ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div style="margin-top: 30px;">
            <a href="upload_attendance.php?action=import" class="btn-confirm">Confirm & Save to Database</a>
            <a href="upload_attendance.php" style="margin-left: 15px; color: #666;">Cancel</a>
        </div>
    </div>
</body>
</html>