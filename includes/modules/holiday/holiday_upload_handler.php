<?php
// ============================================
// HOLIDAY UPLOAD HANDLER
// ============================================

// Bootstrap - Fix the path
require_once __DIR__ . '/../../config/bootstrap.php';

// OR if that doesn't work, try this absolute path:
// require_once $_SERVER['DOCUMENT_ROOT'] . '/attendance/includes/modules/config/bootstrap.php';

// Authentication check
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to upload holidays.']);
    exit;
}

// Check if user has permission (HR or Centre Head only)
$user = getCurrentUser();
if (!in_array($user['role'] ?? '', ['hr', 'centre_head', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to upload holidays.']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['holiday_file']) || $_FILES['holiday_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred.']);
    exit;
}

// Get holiday year
$holiday_year = isset($_POST['holiday_year']) ? (int)$_POST['holiday_year'] : date('Y');

$file = $_FILES['holiday_file'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file extension
$allowed_extensions = ['csv', 'xlsx', 'xls'];
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload CSV or Excel files only.']);
    exit;
}

// Create uploads/holidays directory if it doesn't exist
$upload_dir = __DIR__ . '/../../../uploads/holidays/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Move uploaded file to uploads directory
$file_name = 'holidays_' . $holiday_year . '_' . date('Ymd_His') . '.' . $file_extension;
$file_path = $upload_dir . $file_name;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

// Process the file based on extension
try {
    $holidays_data = [];
    
    if ($file_extension === 'csv') {
        $holidays_data = processCSV($file_path);
    } elseif (in_array($file_extension, ['xlsx', 'xls'])) {
        $holidays_data = processExcel($file_path);
    }
    
    if (empty($holidays_data)) {
        echo json_encode(['success' => false, 'message' => 'No valid holiday data found in the file.']);
        exit;
    }
    
    // Check if holidays table exists, if not create it
    $stmt = $pdo->query("SHOW TABLES LIKE 'holidays'");
    if ($stmt->rowCount() == 0) {
        // Create holidays table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `holidays` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `date` date NOT NULL,
                `holiday_name` varchar(255) NOT NULL,
                `description` text DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_date` (`date`),
                KEY `idx_date` (`date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // Insert holidays into database
    $inserted = 0;
    $skipped = 0;
    $errors = [];
    
    $pdo->beginTransaction();
    
    try {
        // First, delete existing holidays for the year if any
        $stmt = $pdo->prepare("DELETE FROM holidays WHERE YEAR(date) = ?");
        $stmt->execute([$holiday_year]);
        
        // Insert new holidays
        $stmt = $pdo->prepare("
            INSERT INTO holidays (date, holiday_name, description, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        foreach ($holidays_data as $holiday) {
            $date = $holiday['date'] ?? '';
            $name = $holiday['holiday_name'] ?? 'Holiday';
            $description = $holiday['description'] ?? '';
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $skipped++;
                $errors[] = "Invalid date format: $date";
                continue;
            }
            
            // Check if it's a valid date
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
                $skipped++;
                $errors[] = "Invalid date: $date";
                continue;
            }
            
            // Check if date belongs to the selected year
            if ((int)$date_obj->format('Y') !== $holiday_year) {
                $skipped++;
                $errors[] = "Date $date is not in year $holiday_year";
                continue;
            }
            
            $stmt->execute([$date, $name, $description]);
            $inserted++;
        }
        
        $pdo->commit();
        
        $message = "Successfully uploaded $inserted holidays for year $holiday_year.";
        if ($skipped > 0) {
            $message .= " $skipped entries were skipped due to errors.";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 5)
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error processing file: ' . $e->getMessage()]);
}

/**
 * Process CSV file
 */
function processCSV($file_path) {
    $data = [];
    
    if (($handle = fopen($file_path, 'r')) !== false) {
        // Get header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $data;
        }
        
        // Normalize header
        $header = array_map('trim', $header);
        $header = array_map('strtolower', $header);
        
        while (($row = fgetcsv($handle)) !== false) {
            $row_data = [];
            foreach ($header as $index => $col_name) {
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                
                // Map column names
                if (strpos($col_name, 'date') !== false) {
                    $row_data['date'] = $value;
                } elseif (strpos($col_name, 'name') !== false || strpos($col_name, 'holiday') !== false) {
                    $row_data['holiday_name'] = $value;
                } elseif (strpos($col_name, 'desc') !== false) {
                    $row_data['description'] = $value;
                }
            }
            
            // Ensure we have at least a date
            if (!empty($row_data['date'])) {
                if (empty($row_data['holiday_name'])) {
                    $row_data['holiday_name'] = 'Holiday';
                }
                $data[] = $row_data;
            }
        }
        fclose($handle);
    }
    
    return $data;
}

/**
 * Process Excel file
 */
function processExcel($file_path) {
    $data = [];
    
    // Check if PhpSpreadsheet is available
    $phpSpreadsheetPath = __DIR__ . '/../../../vendor/autoload.php';
    
    if (!file_exists($phpSpreadsheetPath)) {
        // Fallback: Try to parse as CSV if Excel library not available
        error_log('PhpSpreadsheet not found. Please install: composer require phpoffice/phpspreadsheet');
        return $data;
    }
    
    try {
        require_once $phpSpreadsheetPath;
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        if (empty($rows)) {
            return $data;
        }
        
        // Get header row
        $header = array_shift($rows);
        $header = array_map('trim', $header);
        $header = array_map('strtolower', $header);
        
        foreach ($rows as $row) {
            $row_data = [];
            foreach ($header as $index => $col_name) {
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                
                if (strpos($col_name, 'date') !== false) {
                    // Handle Excel date formats
                    if (is_numeric($value)) {
                        // Excel date serial number
                        $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                        $row_data['date'] = $date->format('Y-m-d');
                    } else {
                        $row_data['date'] = $value;
                    }
                } elseif (strpos($col_name, 'name') !== false || strpos($col_name, 'holiday') !== false) {
                    $row_data['holiday_name'] = $value;
                } elseif (strpos($col_name, 'desc') !== false) {
                    $row_data['description'] = $value;
                }
            }
            
            if (!empty($row_data['date'])) {
                if (empty($row_data['holiday_name'])) {
                    $row_data['holiday_name'] = 'Holiday';
                }
                $data[] = $row_data;
            }
        }
        
    } catch (Exception $e) {
        error_log('Excel processing error: ' . $e->getMessage());
        // Fallback to CSV parsing
        $csv_path = pathinfo($file_path, PATHINFO_DIRNAME) . '/' . pathinfo($file_path, PATHINFO_FILENAME) . '.csv';
        if (copy($file_path, $csv_path)) {
            return processCSV($csv_path);
        }
    }
    
    return $data;
}
?>