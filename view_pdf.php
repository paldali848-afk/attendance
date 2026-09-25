<?php
// view_pdf.php - PDF Viewer using PDF.js



require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get policy ID
$policy_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($policy_id <= 0) {
    die('Invalid policy ID');
}

// Fetch policy details
try {
    $stmt = $pdo->prepare("SELECT * FROM policies WHERE id = ?");
    $stmt->execute([$policy_id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$policy) {
        die('Policy not found');
    }

    $file_path = $policy['file_path'];
    $policy_title = htmlspecialchars($policy['title']);
    $policy_category = htmlspecialchars($policy['category']);

    // Check if file exists
    if (!file_exists($file_path)) {
        die('File not found: ' . $file_path);
    }

    // Convert file path to URL
    $pdf_url = $file_path;
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $policy_title; ?> - Policy Viewer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f6;
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .top-bar {
            background: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e8edf4;
            flex-shrink: 0;
            min-height: 64px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            z-index: 10;
        }

        .top-bar .left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .top-bar .left .back-btn {
            background: #f1f5f9;
            border: none;
            cursor: pointer;
            color: #1e293b;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
        }

        .top-bar .left .back-btn:hover {
            background: #2563eb;
            color: white;
        }

        .top-bar .left .policy-title {
            font-weight: 700;
            font-size: 16px;
            color: #0a1628;
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .top-bar .left .policy-title i {
            color: #dc2626;
            margin-right: 8px;
        }

        .top-bar .right .badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            background: #dbeafe;
            color: #1d4ed8;
        }

        /* Update these specific blocks in your <style> section */

        .pdf-container {
            flex: 1;
            overflow: auto;
            padding: 10px;
            /* Reduced from 20px to give more space on phones */
            background: #e5e7eb;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            /* Centers the PDF */
        }

        .pdf-container #pdf-viewer {
            width: 100%;
            max-width: 1400px;
            /* Increased from 900px to make it much bigger on desktop */
            margin: 0 auto;
        }

        .pdf-container canvas {
            display: block;
            margin: 0 auto 15px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            background: white;
            border-radius: 4px;
            /* Remove width: 100% here, JavaScript will handle the scaling */
            max-width: 100%;
            height: auto !important;
        }

        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #64748b;
        }

        .loading .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e2e8f0;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .controls {
            background: white;
            padding: 12px 24px;
            border-top: 1px solid #e8edf4;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
            flex-wrap: wrap;
            z-index: 100;
            /* Ensure it stays above the PDF container */
            position: relative;
            /* Or use 'sticky' if needed */
        }

        .controls button {
            background: #f1f5f9;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .controls button:hover:not(:disabled) {
            background: #2563eb;
            color: white;
        }

        .controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .controls .page-info {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            min-width: 100px;
            text-align: center;
        }

        .controls .close-btn {
            background: #2563eb;
            color: white;
            padding: 8px 24px;
        }

        .controls .close-btn:hover {
            background: #1d4ed8;
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 8px 12px;
                min-height: 50px;
            }

             .pdf-container {
        padding: 5px; /* Even less padding on very small screens */
    }
    
            .top-bar .left .policy-title {
                max-width: 130px;
                font-size: 13px;
            }

            .controls {
                padding: 8px;
                gap: 5px;
                justify-content: space-between;
            }

            /* Hide the text "Previous" and "Next" on small phones to save space */
            .controls button span.btn-text {
                display: none;
            }

            .controls button {
                padding: 8px 10px;
                font-size: 14px;
            }

            .controls .page-info {
                font-size: 12px;
                min-width: unset;
            }

            .controls .close-btn {
                padding: 8px 12px;
            }
        }
    </style>
</head>

<body>
    <div class="top-bar">
        <div class="left">
            <a href="policy.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <span class="policy-title">
                <i class="fas fa-file-pdf"></i>
                <?php echo $policy_title; ?>
            </span>
        </div>
        <div class="right">
            <span class="badge">
                <i class="fas fa-tag"></i> <?php echo $policy_category; ?>
            </span>
        </div>
    </div>

    <div class="pdf-container" id="pdfContainer">
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Loading policy document...</p>
        </div>
        <div id="pdf-viewer"></div>
    </div>

    <div class="controls">
        <button onclick="prevPage()" id="prevBtn">
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <span class="page-info" id="pageInfo">Page 1 / 1</span>
        <button onclick="nextPage()" id="nextBtn">
            Next <i class="fas fa-chevron-right"></i>
        </button>
        <button class="close-btn" onclick="window.location.href='policy.php'">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <script>
        const PDF_URL = '<?php echo $pdf_url; ?>';
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let currentPage = 1;
        let totalPages = 0;

        function renderPage(num) {
    const viewer = document.getElementById('pdf-viewer');
    const loading = document.getElementById('loading');
    
    pdfDoc.getPage(num).then(function(page) {
        const containerWidth = viewer.clientWidth;
        const viewport = page.getViewport({ scale: 1 });
        
        // Calculate scale to fit width exactly
        const scale = containerWidth / viewport.width;
        const scaledViewport = page.getViewport({ scale: scale });
        
        // --- HIGH RESOLUTION FIX START ---
        // Detect mobile pixel density (usually 2x or 3x)
        const outputScale = window.devicePixelRatio || 1;
        
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        // Set the internal drawing resolution higher for sharpness
        canvas.width = Math.floor(scaledViewport.width * outputScale);
        canvas.height = Math.floor(scaledViewport.height * outputScale);
        
        // Set the visual display size
        canvas.style.width = "100%";
        canvas.style.height = "auto";
        
        // Scale the context so drawing happens at the correct resolution
        const transform = outputScale !== 1 
            ? [outputScale, 0, 0, outputScale, 0, 0] 
            : null;
        // --- HIGH RESOLUTION FIX END ---
        
        viewer.innerHTML = '';
        viewer.appendChild(canvas);
        
        const renderContext = {
            canvasContext: context,
            viewport: scaledViewport,
            transform: transform
        };
        
        page.render(renderContext).promise.then(function() {
            loading.style.display = 'none';
            document.getElementById('pageInfo').textContent = 'Page ' + num + ' / ' + totalPages;
            document.getElementById('prevBtn').disabled = (num <= 1);
            document.getElementById('nextBtn').disabled = (num >= totalPages);
        });
    });
}
        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                renderPage(currentPage);
            }
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderPage(currentPage);
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                nextPage();
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                prevPage();
            } else if (e.key === 'Escape') {
                window.location.href = 'policy.php';
            }
        });

        function loadPDF() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';

            pdfjsLib.getDocument(PDF_URL).promise.then(function(pdf) {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                currentPage = 1;
                renderPage(currentPage);
            }).catch(function(error) {
                loading.innerHTML = '<p style="color: #dc2626;"><i class="fas fa-exclamation-circle"></i> Failed to load PDF. Please try again.</p>';
            });
        }

        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (pdfDoc) renderPage(currentPage);
            }, 300);
        });

        document.addEventListener('DOMContentLoaded', loadPDF);
    </script>
</body>

</html>