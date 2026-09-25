<?php
// ============================================
// UPLOAD HOLIDAYS - Standalone Page with Smart Parser
// ============================================

require_once __DIR__ . '/includes/modules/config/bootstrap.php';
require_once __DIR__ . '/includes/modules/auth/authentication.php';

$user = getCurrentUser();

// Check if user has permission
if (!in_array($user['role'] ?? '', ['hr', 'centre_head', 'admin'])) {
    header("Location: dashboard.php");
    exit;
}

// Check if holidays table exists, if not create it
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'holidays'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE `holidays` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `holiday_date` date NOT NULL,
                `holiday_name` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `year` int(4) NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_date` (`holiday_date`),
                KEY `idx_date` (`holiday_date`),
                KEY `idx_year` (`year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    // Table might already exist
}

// Handle form submission
$message = '';
$message_type = '';
$uploaded_data = [];
$duplicates = [];
$inserted_count = 0;
$duplicate_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['holiday_file'])) {
    $holiday_year = isset($_POST['holiday_year']) ? (int)$_POST['holiday_year'] : date('Y');
    $file = $_FILES['holiday_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['csv', 'xlsx', 'xls'];
        
        if (in_array($file_extension, $allowed)) {
            // Create upload directory
            $upload_dir = __DIR__ . '/uploads/holidays/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = 'holidays_' . $holiday_year . '_' . date('Ymd_His') . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Parse the file with smart detection
                $holidays = parseHolidayFile($file_path, $file_extension, $holiday_year);
                
                if (!empty($holidays)) {
                    // Store for preview
                    $uploaded_data = $holidays;
                    
                    // Insert into database
                    try {
                        $pdo->beginTransaction();
                        
                        // Delete existing holidays for the year
                        $stmt = $pdo->prepare("DELETE FROM holidays WHERE year = ?");
                        $stmt->execute([$holiday_year]);
                        
                        // Insert new holidays
                        $stmt = $pdo->prepare("
                            INSERT INTO holidays (holiday_date, holiday_name, description, year) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $inserted = 0;
                        
                        foreach ($holidays as $holiday) {
                            try {
                                $stmt->execute([
                                    $holiday['date'], 
                                    $holiday['name'], 
                                    $holiday['description'] ?? '', 
                                    $holiday_year
                                ]);
                                $inserted++;
                            } catch (PDOException $e) {
                                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                                    $duplicates[] = $holiday['date'] . ' - ' . $holiday['name'];
                                    $duplicate_count++;
                                } else {
                                    throw $e;
                                }
                            }
                        }
                        
                        $pdo->commit();
                        
                        $inserted_count = $inserted;
                        
                        if ($inserted > 0 && $duplicate_count == 0) {
                            $message = "Successfully uploaded $inserted holidays for year $holiday_year.";
                            $message_type = 'success';
                        } elseif ($inserted > 0 && $duplicate_count > 0) {
                            $message = "Uploaded $inserted holidays for year $holiday_year. $duplicate_count duplicate dates were skipped.";
                            $message_type = 'warning';
                        } elseif ($inserted == 0 && $duplicate_count > 0) {
                            $message = "No new holidays uploaded. $duplicate_count duplicate dates already exist in the database.";
                            $message_type = 'warning';
                        } else {
                            $message = "No holidays were uploaded. Please check the file format.";
                            $message_type = 'error';
                        }
                        
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $message = "Database error: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = "No valid holiday data found in the file. Please check the format.";
                    $message_type = 'error';
                }
            } else {
                $message = "Failed to move uploaded file.";
                $message_type = 'error';
            }
        } else {
            $message = "Invalid file type. Please upload CSV or Excel files.";
            $message_type = 'error';
        }
    } else {
        $message = "File upload error. Please try again.";
        $message_type = 'error';
    }
}

function parseHolidayFile($file_path, $file_extension, $year) {
    $data = [];
    $rows = ($file_extension === 'csv') ? readCSV($file_path) : readExcel($file_path);
    
    if (empty($rows)) return $data;

    // Remove empty rows and keep header to check column count
    $rows = array_values(array_filter($rows, function($row) {
        return !empty(array_filter($row));
    }));

    // Detect if first row is a header
    $firstRow = $rows[0];
    if (isset($firstRow[0]) && (strtolower($firstRow[0]) == 's.no' || strtolower($firstRow[0]) == 'sr no')) {
        array_shift($rows); // Skip header
    }

    foreach ($rows as $row) {
        // Based on your specific file:
        // [0] S.No, [1] Holiday Name, [2] Date, [3] Day
        $holiday_name = trim($row[1] ?? '');
        $raw_date = trim($row[2] ?? '');
        $day_info = trim($row[3] ?? '');

        // If the columns are shifted (e.g. no S.No), let's try to find the date
        if (!preg_match('/\d/', $raw_date)) {
             // If column 2 isn't a date, check if column 1 is (some files differ)
             if (preg_match('/\d/', trim($row[1] ?? ''))) {
                 $raw_date = trim($row[1] ?? '');
                 $holiday_name = trim($row[2] ?? '');
             }
        }

        $parsed_date = parseDate($raw_date, $year);

        if ($parsed_date && !empty($holiday_name)) {
            $data[] = [
                'date' => $parsed_date,
                'name' => $holiday_name,
                'description' => $day_info,
                'year' => $year
            ];
        }
    }
    return $data;
}
function readCSV($file_path) {
    $rows = [];
    if (($handle = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Read Excel file - FIXED VERSION
 */
function readExcel($file_path) {
    $rows = [];
    
    // Try multiple methods to read Excel file
    
    // Method 1: Try PhpSpreadsheet
    $phpSpreadsheetPath = __DIR__ . '/../vendor/autoload.php';
    
    if (file_exists($phpSpreadsheetPath)) {
        try {
            require_once $phpSpreadsheetPath;
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // If we got data, return it
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Exception $e) {
            error_log('PhpSpreadsheet error: ' . $e->getMessage());
        }
    }
    
    // Method 2: Try using SimpleXML (for XLSX files)
    if (pathinfo($file_path, PATHINFO_EXTENSION) === 'xlsx') {
        try {
            $zip = new ZipArchive();
            if ($zip->open($file_path) === true) {
                $xml = $zip->getFromName('xl/sharedStrings.xml');
                $zip->close();
                
                if ($xml) {
                    $shared_strings = [];
                    $dom = new DOMDocument();
                    $dom->loadXML($xml);
                    $items = $dom->getElementsByTagName('t');
                    foreach ($items as $item) {
                        $shared_strings[] = $item->nodeValue;
                    }
                    
                    // Now read the sheet
                    $zip = new ZipArchive();
                    if ($zip->open($file_path) === true) {
                        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                        $zip->close();
                        
                        if ($xml) {
                            $dom = new DOMDocument();
                            $dom->loadXML($xml);
                            $rows_data = [];
                            $rows_elements = $dom->getElementsByTagName('row');
                            
                            foreach ($rows_elements as $row_elem) {
                                $row_data = [];
                                $cells = $row_elem->getElementsByTagName('c');
                                foreach ($cells as $cell) {
                                    $type = $cell->getAttribute('t');
                                    $value = '';
                                    $text = $cell->getElementsByTagName('v');
                                    if ($text->length > 0) {
                                        $val = $text->item(0)->nodeValue;
                                        if ($type === 's') {
                                            $value = isset($shared_strings[$val]) ? $shared_strings[$val] : $val;
                                        } else {
                                            // Check if it's a date
                                            if (is_numeric($val) && $val > 30000 && $val < 50000) {
                                                $date = date('Y-m-d', strtotime('1899-12-30 + ' . $val . ' days'));
                                                $value = $date;
                                            } else {
                                                $value = $val;
                                            }
                                        }
                                    }
                                    $row_data[] = $value;
                                }
                                if (!empty($row_data)) {
                                    $rows_data[] = $row_data;
                                }
                            }
                            
                            if (!empty($rows_data)) {
                                return $rows_data;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Excel XML parsing error: ' . $e->getMessage());
        }
    }
    
    // Method 3: Try to convert Excel to CSV using external tools (if available)
    // This is a fallback
    
    return $rows;
}

/**
 * Detect column mapping intelligently
 */
function detectColumns($header) {
    $mapping = [];
    
    // Keywords for date columns
    $date_keywords = ['date', 'day', 'dt', 'caldate', 'holiday date', 'event date', 'date of holiday'];
    
    // Keywords for name columns
    $name_keywords = ['holiday', 'name', 'holiday name', 'event', 'occasion', 'festival', 'title', 'holidays'];
    
    // Keywords for description columns
    $desc_keywords = ['description', 'desc', 'details', 'note', 'remarks', 'comment', 'about'];
    
    foreach ($header as $column) {
        $col = strtolower(trim($column));
        
        // Check if it's a date column
        foreach ($date_keywords as $keyword) {
            if (strpos($col, $keyword) !== false) {
                $mapping[$column] = 'date';
                break;
            }
        }
        
        // Check if it's a name column
        foreach ($name_keywords as $keyword) {
            if (strpos($col, $keyword) !== false) {
                $mapping[$column] = 'name';
                break;
            }
        }
        
        // Check if it's a description column
        foreach ($desc_keywords as $keyword) {
            if (strpos($col, $keyword) !== false) {
                $mapping[$column] = 'description';
                break;
            }
        }
    }
    
    // If no date column found, try to auto-detect
    if (!in_array('date', $mapping)) {
        if (!empty($header)) {
            $mapping[$header[0]] = 'date';
        }
    }
    
    // If no name column found, use second column or create default
    if (!in_array('name', $mapping)) {
        if (count($header) > 1) {
            $mapping[$header[1]] = 'name';
        } else {
            $mapping['holiday_name'] = 'name';
        }
    }
    
    return $mapping;
}

/**
 * Specifically parses 26-Jan-26 or Excel Serial Numbers
 */
function parseDate($value, $year) {
    if (empty($value)) return null;

    // Handle Excel Serial Numbers
    if (is_numeric($value) && $value > 30000) {
        return date('Y-m-d', strtotime('1899-12-30 + ' . $value . ' days'));
    }

    // Clean the string (remove 'st', 'nd', 'rd', 'th' if present)
    $clean_value = preg_replace('/(\d+)(st|nd|rd|th)/i', '$1', $value);

    try {
        $date = new DateTime($clean_value);
        
        // If the user only provided "01-Jan", force the selected year
        // Otherwise use the year present in the string
        if (strlen($clean_value) < 10 && !preg_match('/\d{4}/', $clean_value)) {
            $date->setDate($year, $date->format('m'), $date->format('d'));
        }
        
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}
include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Holidays - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/includes/modules/styles/main_styles.php'; ?>
    <style>
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 14px;
        }
        .preview-table th {
            background: #f1f5f9;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        .preview-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .preview-table tr:hover {
            background: #f8fafc;
        }
        .preview-table .duplicate {
            background: #fef3c7 !important;
        }
        .preview-table .duplicate td {
            color: #92400e;
        }
        #dropZone.dragover {
            border-color: #2563eb !important;
            background: #eff6ff !important;
        }
        #dropZone.has-file {
            border-color: #16a34a !important;
            background: #f0fdf4 !important;
        }
        .format-example {
            background: #0a1628;
            color: #e2e8f0;
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
            margin: 4px 0 0 0;
            font-family: 'Courier New', monospace;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .file-info {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }
        .file-info .label {
            font-weight: 600;
            color: #0a1628;
        }
        .file-info .value {
            color: #2563eb;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
        <div class="card" style="padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <h2 style="display: flex; align-items: center; gap: 12px; margin: 0;">
                    <i class="fas fa-calendar-plus" style="color: #2563eb; font-size: 28px;"></i>
                    Upload Holiday List
                </h2>
                <div style="display: flex; gap: 10px;">
                    <a href="dashboard.php" style="padding: 8px 20px; background: #f1f5f9; color: #475569; text-decoration: none; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div style="padding: 16px; border-radius: 8px; margin-bottom: 20px; background: <?php echo $message_type === 'success' ? '#dcfce7' : ($message_type === 'warning' ? '#fef3c7' : '#fee2e2'); ?>; border: 1px solid <?php echo $message_type === 'success' ? '#16a34a' : ($message_type === 'warning' ? '#d97706' : '#dc2626'); ?>; color: <?php echo $message_type === 'success' ? '#166534' : ($message_type === 'warning' ? '#92400e' : '#991b1b'); ?>;">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Preview uploaded data -->
            <?php if (!empty($uploaded_data)): ?>
                <div style="background: <?php echo $message_type === 'success' ? '#f0fdf4' : '#fef3c7'; ?>; border: 1px solid <?php echo $message_type === 'success' ? '#16a34a' : '#d97706'; ?>; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: <?php echo $message_type === 'success' ? '#166534' : '#92400e'; ?>;">
                        <i class="fas fa-list"></i> Holiday Data Preview (<?php echo count($uploaded_data); ?> holidays)
                        <?php if ($duplicate_count > 0): ?>
                            <span class="badge-warning" style="margin-left: 10px;">
                                <i class="fas fa-info-circle"></i> <?php echo $duplicate_count; ?> duplicates skipped
                            </span>
                        <?php endif; ?>
                    </h4>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Holiday Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $dup_dates = array_column($duplicates, null);
                                foreach ($uploaded_data as $index => $holiday):
                                    $is_duplicate = in_array($holiday['date'], array_column($duplicates, 'date'));
                                ?>
                                    <tr class="<?php echo $is_duplicate ? 'duplicate' : ''; ?>">
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($holiday['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                                        <td><?php echo htmlspecialchars($holiday['description'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($is_duplicate): ?>
                                                <span class="badge-warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Duplicate
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-success">
                                                    <i class="fas fa-check"></i> Uploaded
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #0a1628;">
                        <i class="fas fa-calendar-year" style="color: #2563eb;"></i> Holiday Year
                    </label>
                    <select name="holiday_year" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 16px;">
                        <?php
                        $current_year = date('Y');
                        for ($y = $current_year - 2; $y <= $current_year + 1; $y++) {
                            $selected = ($y == $current_year) ? 'selected' : '';
                            echo "<option value=\"$y\" $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #0a1628;">
                        <i class="fas fa-file-csv" style="color: #2563eb;"></i> Upload File (CSV or Excel)
                    </label>
                    <div style="border: 2px dashed #d1d5db; border-radius: 12px; padding: 40px; text-align: center; background: #f8fafc; transition: all 0.3s ease;" 
                         id="dropZone"
                         ondrop="handleDrop(event)" 
                         ondragover="handleDragOver(event)" 
                         ondragleave="handleDragLeave(event)">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #94a3b8; margin-bottom: 12px;"></i>
                        <p style="color: #64748b; font-size: 16px; margin-bottom: 8px;">
                            <strong>Drag & drop your file here</strong>
                        </p>
                        <p style="color: #94a3b8; font-size: 14px;">or click to browse</p>
                        <input type="file" 
                               id="holidayFile" 
                               name="holiday_file" 
                               accept=".csv,.xlsx,.xls" 
                               style="display:none;" 
                               onchange="handleFileSelect(event)" 
                               required>
                        <button type="button" 
                                onclick="document.getElementById('holidayFile').click()" 
                                style="margin-top: 16px; padding: 10px 32px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            <i class="fas fa-folder-open"></i> Browse Files
                        </button>
                        <div id="fileNameDisplay" style="margin-top: 16px; font-size: 14px; color: #16a34a; font-weight: 600; display: none;">
                            <i class="fas fa-check-circle"></i> <span id="fileNameText"></span>
                        </div>
                    </div>
                    <p style="font-size: 13px; color: #94a3b8; margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> Supports CSV, XLSX, and XLS files. Existing holidays with same dates will be replaced.
                    </p>
                </div>
                
                <div style="background: #f1f5f9; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <p style="font-weight: 600; color: #0a1628; margin-bottom: 8px;">
                        <i class="fas fa-info-circle" style="color: #2563eb;"></i> Your file format is supported!
                    </p>
                    <p style="font-size: 13px; color: #475569; margin-bottom: 8px;">
                        <strong>Your file has columns:</strong> Sr No, Holidays, Date, Day
                    </p>
                    <p style="font-size: 13px; color: #475569;">
                        <strong>Will be parsed as:</strong> Date → <span style="color: #2563eb;">holiday_date</span>, Holidays → <span style="color: #2563eb;">holiday_name</span>
                    </p>
                    <div class="format-example">
                        Sr No,Holidays,Date,Day
                        1,New Year's Day*,01-Jan,Thursday
                        2,Republic Day,26-Jan,Monday
                    </div>
                    <p style="font-size: 13px; color: #475569; margin-top: 8px;">
                        <strong>Date parsing:</strong> 01-Jan → <?php echo date('d-M-Y', strtotime($current_year . '-01-01')); ?>
                    </p>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button type="submit" style="flex: 1; padding: 14px; background: #2563eb; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 16px; transition: all 0.3s ease;">
                        <i class="fas fa-upload"></i> Upload Holidays
                    </button>
                    <a href="dashboard.php" style="flex: 0 0 auto; padding: 14px 32px; background: #f1f5f9; color: #475569; text-decoration: none; border-radius: 10px; font-weight: 600; text-align: center;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Drag and drop handlers
    function handleDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        const dropZone = document.getElementById('dropZone');
        if (dropZone) {
            dropZone.classList.remove('dragover');
        }
        
        const files = event.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('holidayFile').files = files;
            handleFileSelect({ target: { files: files } });
        }
    }
    
    function handleDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        const dropZone = document.getElementById('dropZone');
        if (dropZone) {
            dropZone.classList.add('dragover');
        }
    }
    
    function handleDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        const dropZone = document.getElementById('dropZone');
        if (dropZone) {
            dropZone.classList.remove('dragover');
        }
    }
    
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            const dropZone = document.getElementById('dropZone');
            if (dropZone) {
                dropZone.classList.add('has-file');
            }
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const fileNameText = document.getElementById('fileNameText');
            if (fileNameDisplay && fileNameText) {
                fileNameDisplay.style.display = 'block';
                fileNameText.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            }
        }
    }
    </script>
</body>
</html>