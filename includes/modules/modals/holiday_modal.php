<?php
// ============================================
// HOLIDAY UPLOAD MODAL
// ============================================
?>
<!-- Holiday Upload Modal - Hidden by default -->
<div id="holidayModal" class="modal-overlay" style="display:none !important;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color: #2563eb;"></i> Upload Holiday List</h3>
            <button class="modal-close" onclick="closeHolidayModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="holidayUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_holidays">
                
                <div style="margin-bottom: 20px;">
                    <label style="display:block;font-weight:600;margin-bottom:8px;color:#0a1628;">
                        <i class="fas fa-file-csv" style="color:#2563eb;"></i> Select CSV/Excel File
                    </label>
                    <div style="border:2px dashed #d1d5db;border-radius:12px;padding:30px;text-align:center;background:#f8fafc;transition:all 0.3s ease;" 
                         id="dropZone"
                         ondrop="handleDrop(event)" 
                         ondragover="handleDragOver(event)" 
                         ondragleave="handleDragLeave(event)">
                        <i class="fas fa-cloud-upload-alt" style="font-size:48px;color:#94a3b8;margin-bottom:12px;"></i>
                        <p style="color:#64748b;font-size:14px;margin-bottom:8px;">
                            <strong>Drag & drop your file here</strong>
                        </p>
                        <p style="color:#94a3b8;font-size:13px;">or click to browse</p>
                        <input type="file" 
                               id="holidayFile" 
                               name="holiday_file" 
                               accept=".csv,.xlsx,.xls" 
                               style="display:none;" 
                               onchange="handleFileSelect(event)">
                        <button type="button" 
                                onclick="document.getElementById('holidayFile').click()" 
                                style="margin-top:12px;padding:8px 24px;background:#2563eb;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                            <i class="fas fa-folder-open"></i> Browse Files
                        </button>
                        <div id="fileNameDisplay" style="margin-top:12px;font-size:13px;color:#16a34a;font-weight:600;display:none;">
                            <i class="fas fa-check-circle"></i> <span id="fileNameText"></span>
                        </div>
                    </div>
                    <p style="font-size:12px;color:#94a3b8;margin-top:8px;">
                        <i class="fas fa-info-circle"></i> 
                        Supported formats: CSV, Excel (.xlsx, .xls)
                    </p>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-weight:600;margin-bottom:8px;color:#0a1628;">
                        <i class="fas fa-calendar-year" style="color:#2563eb;"></i> Holiday Year
                    </label>
                    <select name="holiday_year" id="holidayYear" style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
                        <?php
                        $current_year = date('Y');
                        for ($y = $current_year - 2; $y <= $current_year + 1; $y++) {
                            $selected = ($y == $current_year) ? 'selected' : '';
                            echo "<option value=\"$y\" $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="background:#f1f5f9;padding:16px;border-radius:8px;margin-bottom:20px;">
                    <p style="font-weight:600;color:#0a1628;margin-bottom:8px;">
                        <i class="fas fa-info-circle" style="color:#2563eb;"></i> File Format Requirements:
                    </p>
                    <ul style="list-style:none;padding:0;margin:0;font-size:13px;color:#475569;">
                        <li style="padding:4px 0;">• <strong>CSV</strong> or <strong>Excel</strong> file</li>
                        <li style="padding:4px 0;">• Columns: <strong>date, holiday_name, description</strong> (optional)</li>
                        <li style="padding:4px 0;">• Date format: <strong>YYYY-MM-DD</strong></li>
                        <li style="padding:4px 0;">• First row should be the header</li>
                    </ul>
                </div>

                <div id="uploadProgress" style="display:none;margin-bottom:20px;">
                    <div style="background:#e5e7eb;border-radius:50px;height:8px;overflow:hidden;">
                        <div id="progressBar" style="background:#2563eb;height:100%;width:0%;transition:width 0.3s ease;"></div>
                    </div>
                    <p id="progressText" style="font-size:13px;color:#475569;margin-top:6px;text-align:center;">Uploading...</p>
                </div>

                <div id="uploadResult" style="display:none;padding:16px;border-radius:8px;margin-bottom:20px;"></div>

                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <button type="button" onclick="closeHolidayModal()" style="padding:10px 24px;background:#f1f5f9;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;font-weight:600;color:#475569;">
                        Cancel
                    </button>
                    <button type="submit" id="uploadHolidayBtn" style="padding:10px 32px;background:#2563eb;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Override any conflicting styles */
#holidayModal.modal-overlay {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0,0,0,0.5) !important;
    backdrop-filter: blur(4px) !important;
    z-index: 999999 !important;
    align-items: center !important;
    justify-content: center !important;
}

#holidayModal.modal-overlay.active {
    display: flex !important;
}

#holidayModal .modal-content {
    background: white !important;
    border-radius: 16px !important;
    max-width: 600px !important;
    width: 90% !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
    animation: modalSlideUp 0.3s ease !important;
}

@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

#holidayModal .modal-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 20px 24px !important;
    border-bottom: 1px solid #e8edf4 !important;
}

#holidayModal .modal-header h3 {
    margin: 0 !important;
    font-size: 18px !important;
    color: #0a1628 !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
}

#holidayModal .modal-close {
    background: none !important;
    border: none !important;
    font-size: 24px !important;
    cursor: pointer !important;
    color: #94a3b8 !important;
    padding: 4px 8px !important;
    transition: all 0.3s ease !important;
}

#holidayModal .modal-close:hover {
    color: #dc2626 !important;
    transform: rotate(90deg) !important;
}

#holidayModal .modal-body {
    padding: 24px !important;
}

#dropZone.dragover {
    border-color: #2563eb !important;
    background: #eff6ff !important;
}

#dropZone.has-file {
    border-color: #16a34a !important;
    background: #f0fdf4 !important;
}
</style>

<script>
// Make sure modal is hidden on page load
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('holidayModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
});

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

// Open modal function
function openHolidayModal() {
    var modal = document.getElementById('holidayModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reset form
        var form = document.getElementById('holidayUploadForm');
        if (form) {
            form.reset();
        }
        
        var resultDiv = document.getElementById('uploadResult');
        if (resultDiv) {
            resultDiv.style.display = 'none';
        }
        
        var progressDiv = document.getElementById('uploadProgress');
        if (progressDiv) {
            progressDiv.style.display = 'none';
        }
        
        var fileNameDisplay = document.getElementById('fileNameDisplay');
        if (fileNameDisplay) {
            fileNameDisplay.style.display = 'none';
        }
        
        var dropZone = document.getElementById('dropZone');
        if (dropZone) {
            dropZone.classList.remove('has-file');
        }
        
        var submitBtn = document.getElementById('uploadHolidayBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
        }
    }
}

// Close modal function
function closeHolidayModal() {
    var modal = document.getElementById('holidayModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    var modal = document.getElementById('holidayModal');
    if (modal && modal.classList.contains('active')) {
        if (event.target === modal) {
            closeHolidayModal();
        }
    }
});

// Form submission
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('holidayUploadForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('holidayFile');
            const file = fileInput ? fileInput.files[0] : null;
            
            if (!file) {
                if (typeof showNotification === 'function') {
                    showNotification('error', 'Please select a file to upload.');
                } else {
                    alert('Please select a file to upload.');
                }
                return;
            }
            
            const formData = new FormData(this);
            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const resultDiv = document.getElementById('uploadResult');
            const submitBtn = document.getElementById('uploadHolidayBtn');
            
            if (progressDiv) progressDiv.style.display = 'block';
            if (resultDiv) resultDiv.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            }
            if (progressBar) progressBar.style.width = '0%';
            if (progressText) progressText.textContent = 'Uploading file...';
            
            fetch('includes/modules/holiday/holiday_upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (progressBar) progressBar.style.width = '100%';
                if (progressText) progressText.textContent = 'Completed!';
                
                if (resultDiv) {
                    resultDiv.style.display = 'block';
                    if (data.success) {
                        resultDiv.style.background = '#dcfce7';
                        resultDiv.style.border = '1px solid #16a34a';
                        resultDiv.style.color = '#166534';
                        resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        
                        setTimeout(function() {
                            closeHolidayModal();
                            if (typeof showNotification === 'function') {
                                showNotification('success', data.message);
                            }
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }, 2000);
                    } else {
                        resultDiv.style.background = '#fee2e2';
                        resultDiv.style.border = '1px solid #dc2626';
                        resultDiv.style.color = '#991b1b';
                        resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (resultDiv) {
                    resultDiv.style.display = 'block';
                    resultDiv.style.background = '#fee2e2';
                    resultDiv.style.border = '1px solid #dc2626';
                    resultDiv.style.color = '#991b1b';
                    resultDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error: ' + error.message;
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                }
            });
        });
    }
});

// Make functions globally accessible
window.openHolidayModal = openHolidayModal;
window.closeHolidayModal = closeHolidayModal;
window.handleDrop = handleDrop;
window.handleDragOver = handleDragOver;
window.handleDragLeave = handleDragLeave;
window.handleFileSelect = handleFileSelect;

console.log('Holiday upload modal loaded successfully!');
</script>