<!-- ========================================== -->
<!-- RESIGNATION FORM MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="resignationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-slash" style="color: #dc2626;"></i>
                    Resignation Letter
                </h5>
                <button type="button" class="close" onclick="closeResignationModal()">
                    <span>&times;</span>
                </button>
            </div>
            
            <form id="resignationForm" method="POST" action="" onsubmit="submitResignation(event)">
                <div class="modal-body">
                    <input type="hidden" name="action" value="submit_resignation">
                    
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="resignation_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>To HR,</label>
                    </div>
                    
                    <div class="form-group">
                        <label>I <span class="text-danger">*</span></label>
                        <input type="text" name="employee_name" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                               placeholder="Your Full Name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>writing to provide you a <span class="text-danger">*</span></label>
                        <input type="number" name="notice_period_days" class="form-control" 
                               placeholder="Number of days (e.g., 30, 60)" required>
                        <small class="text-muted">Standard notice period is 30 or 60 days</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Days' Notice of my Resignation from my Position as <span class="text-danger">*</span></label>
                        <input type="text" name="position" class="form-control" 
                               value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" 
                               placeholder="Your Position/Designation" required>
                    </div>
                    
                    <div class="form-group">
                        <label>with <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" 
                               value="<?php echo SITE_NAME ?? 'Company'; ?>" 
                               placeholder="Company Name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>My last day of work in this Position will be <span class="text-danger">*</span></label>
                        <input type="date" name="last_working_date" class="form-control" required>
                        <small class="text-muted">Please select your last working day</small>
                    </div>
                    
                    <div class="form-group">
                        <label>I am leaving due to <span class="text-danger">*</span></label>
                        <select name="reason" class="form-control" required>
                            <option value="">Select Reason</option>
                            <option value="Better Career Opportunity">Better Career Opportunity</option>
                            <option value="Higher Education">Higher Education</option>
                            <option value="Personal Reasons">Personal Reasons</option>
                            <option value="Health Issues">Health Issues</option>
                            <option value="Relocation">Relocation</option>
                            <option value="Salary/Compensation">Salary/Compensation</option>
                            <option value="Work-Life Balance">Work-Life Balance</option>
                            <option value="Company Culture">Company Culture</option>
                            <option value="Career Growth">Career Growth</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Detailed Reason (Optional)</label>
                        <textarea name="reason_detail" class="form-control" rows="3" 
                                  placeholder="Please provide more details about your reason for leaving..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Faithfully,</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeResignationModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger" id="submitResignationBtn">
                        <i class="fas fa-paper-plane"></i> Submit Resignation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999999;
    overflow-y: auto;
}

.modal-dialog {
    margin: 30px auto;
    max-width: 700px;
    padding: 20px;
}

.modal-content {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e8edf4;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fef2f2;
    border-radius: 12px 12px 0 0;
}

.modal-header .modal-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.modal-header .close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #94a3b8;
    transition: all 0.3s ease;
}

.modal-header .close:hover {
    color: #dc2626;
    transform: rotate(90deg);
}

.modal-body {
    padding: 25px;
}

.modal-body .form-group {
    margin-bottom: 18px;
}

.modal-body .form-group label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #1e293b;
    margin-bottom: 5px;
}

.modal-body .form-group .text-danger {
    color: #dc2626;
}

.modal-body .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e8edf4;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
}

.modal-body .form-control:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.modal-body .form-control[disabled] {
    background: #f8fafc;
    cursor: not-allowed;
}

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid #e8edf4;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-danger {
    background: #dc2626;
    color: #fff;
}

.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.btn-danger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

@media (max-width: 768px) {
    .modal-dialog {
        margin: 10px;
        padding: 10px;
    }
}
</style>

<script>
function openResignationForm() {
    document.getElementById('resignationModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeResignationModal() {
    document.getElementById('resignationModal').style.display = 'none';
    document.body.style.overflow = '';
}

function submitResignation(event) {
    event.preventDefault();
    
    const form = document.getElementById('resignationForm');
    const submitBtn = document.getElementById('submitResignationBtn');
    const formData = new FormData(form);
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    fetch('includes/modules/resignation/resignation_form_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showNotification('error', data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Resignation';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Resignation';
    });
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeResignationModal();
    }
});

// Close modal when clicking outside
document.getElementById('resignationModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeResignationModal();
    }
});
</script>