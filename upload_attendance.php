<?php
require_once 'config.php';
if (!isLoggedIn()) redirect('index.php');

$message = '';
$uploaded_count = 0;
$errors = [];

/**
 * Helper: Convert any date format to YYYY-MM-DD
 */
function formatToDbDate($dateStr) {
    if (empty($dateStr)) return null;
    $date = strtotime(str_replace('/', '-', $dateStr));
    return $date ? date('Y-m-d', $date) : null;
}

/**
 * Helper: Ensure time is in 24h format (HH:MM:SS)
 */
function formatToDbTime($timeStr) {
    if (empty($timeStr) || $timeStr == '--' || $timeStr == '00:00:00') return null;
    $time = strtotime($timeStr);
    return $time ? date('H:i:s', $time) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attendance_file'])) {
    $file = $_FILES['attendance_file']['tmp_name'];
    $handle = fopen($file, 'r');
    
    // Read Headers
    $headers = fgetcsv($handle); 
    if ($headers === false) {
        $message = "<div class='message message-error'>Error: File is empty.</div>";
    } else {
        // Clean headers: lowercase, remove spaces/dots to match DB columns
        $clean_headers = array_map(function($h) {
            $h = strtolower(trim($h));
            return str_replace([' ', '.', '-'], ['_', '', '_'], $h);
        }, $headers);

        $row_num = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;
            if (empty(array_filter($row))) continue; // Skip empty lines
            
            // Map row data to header keys
            $data = array_combine($clean_headers, $row);
            
            // 1. Map Core Identity
            $p_id   = $data['person_id'] ?? $data['no_'] ?? null;
            $p_name = $data['name'] ?? 'Unknown';
            $p_date = formatToDbDate($data['date'] ?? null);

            if (!$p_id || !$p_date) continue;

            // 2. Map Times and Work
            $check_in  = formatToDbTime($data['check_in'] ?? null);
            $check_out = formatToDbTime($data['check_out'] ?? null);
            $work_hrs  = !empty($data['work']) ? (float)$data['work'] : 0;
            $records   = $data['records'] ?? null;
            
            // 3. APPLY LOGIC (WO on Sunday, L after 9:45)
            $day_of_week = date('w', strtotime($p_date)); // 0 = Sunday
            $excel_status = strtoupper(trim($data['status'] ?? ''));

            if (!empty($check_in)) {
                // If they punched in, check 9:45 AM Rule
                $status = (strtotime($check_in) > strtotime('09:45:00')) ? 'L' : 'P';
            } else {
                // If NO punch, check if it's Sunday
                if ($day_of_week == 0) {
                    $status = 'WO';
                } else {
                    // Check if Excel manually says it's a special leave (PL, PH, etc)
                    if (!empty($excel_status) && !in_array($excel_status, ['P', 'A', 'L', 'PRESENT', 'ABSENT', 'LATE'])) {
                        $status = $excel_status;
                    } else {
                        $status = 'A';
                    }
                }
            }

            // 4. DATABASE INSERT/UPDATE
            try {
                $sql = "INSERT INTO atten.attendance 
                    (person_id, name, date, check_in, check_out, work, status, records) 
                    VALUES (:pid, :name, :date, :cin, :cout, :work, :stat, :rec)
                    ON DUPLICATE KEY UPDATE 
                    check_in = VALUES(check_in), 
                    check_out = VALUES(check_out), 
                    work = VALUES(work), 
                    status = VALUES(status), 
                    records = VALUES(records)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':pid'   => $p_id,
                    ':name'  => $p_name,
                    ':date'  => $p_date,
                    ':cin'   => $check_in,
                    ':cout'  => $check_out,
                    ':work'  => $work_hrs,
                    ':stat'  => $status,
                    ':rec'   => $records
                ]);
                $uploaded_count++;
            } catch (PDOException $e) {
                $errors[] = "Row $row_num ($p_id): " . $e->getMessage();
            }
        }
        fclose($handle);
        $message = "<div class='message message-success'>Successfully uploaded $uploaded_count attendance records!</div>";
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="margin-top: 30px; max-width: 900px;">
    <div class="card anim-fade-up">
        <div class="card-header">
            <h3><i class="fas fa-file-excel"></i> Manual Attendance Upload</h3>
            <span class="badge">CSV Format</span>
        </div>
        
        <div style="padding: 20px;">
            <?php echo $message; ?>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 25px;">
                <h4 style="font-size: 13px; color: #475569; margin-bottom: 8px;"><i class="fas fa-info-circle"></i> Upload Instructions:</h4>
                <ul style="font-size: 12px; color: #64748b; line-height: 1.6;">
                    <li>Save your Excel file as <b>CSV (Comma Delimited)</b>.</li>
                    <li>Headers must include: <b>Person ID, Name, Date, Check-in, Check-out</b>.</li>
                    <li>The system will automatically mark <b>WO</b> for Sundays if no time is found.</li>
                    <li>The <b>9:45 AM Late rule</b> is applied automatically on upload.</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div style="border: 2px dashed #cbd5e1; padding: 40px; text-align: center; border-radius: 12px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.borderColor='#2563eb'" onmouseout="this.style.borderColor='#cbd5e1'" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #94a3b8; margin-bottom: 15px;"></i>
                    <p style="font-weight: 600; color: #1e293b;">Click to select your Biometric CSV file</p>
                    <input type="file" name="attendance_file" id="fileInput" accept=".csv" required style="display: none;">
                </div>
                
                <button type="submit" style="width: 100%; margin-top: 20px; padding: 14px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);">
                    <i class="fas fa-upload"></i> PROCESS UPLOAD
                </button>
            </form>

            <?php if (!empty($errors)): ?>
                <div style="margin-top: 30px; border-top: 1px solid #fee2e2; padding-top: 20px;">
                    <h4 style="color: #dc2626; font-size: 14px;"><i class="fas fa-exclamation-triangle"></i> Mapped Errors:</h4>
                    <div style="max-height: 200px; overflow-y: auto; background: #fff1f2; padding: 10px; border-radius: 6px; margin-top: 10px; font-family: monospace; font-size: 11px; color: #991b1b;">
                        <?php foreach($errors as $err) echo "• " . $err . "<br>"; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('fileInput').onchange = function() {
    if(this.files[0]) {
        alert("Selected: " + this.files[0].name);
    }
};
</script>