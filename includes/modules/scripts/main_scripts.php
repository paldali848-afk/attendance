<script>

// Global path — populated by PHP via $base_path (set in header.php)
var BASE_PATH = '<?php echo isset($base_path) ? $base_path : ""; ?>';

// ===== DROPDOWN FUNCTIONS =====
function toggleDropdown() {
    var menu = document.getElementById('dropdownMenu');
    var arrow = document.getElementById('dropdownArrow');
    if (menu) {
        menu.classList.toggle('show');
        if (arrow) {
            arrow.classList.toggle('open');
        }
    }
}

function closeDropdown() {
    var menu = document.getElementById('dropdownMenu');
    var arrow = document.getElementById('dropdownArrow');
    if (menu) {
        menu.classList.remove('show');
    }
    if (arrow) {
        arrow.classList.remove('open');
    }
}

function openLeaveRequestForm() {
    closeDropdown();
    var leaveForm = document.getElementById('leaveRequestForm');
    var rosterForm = document.getElementById('rosterForm');
    if (leaveForm) leaveForm.classList.add('show');
    if (rosterForm) rosterForm.classList.remove('show');
}

function closeLeaveRequestForm() {
    var form = document.getElementById('leaveRequestForm');
    if (form) form.classList.remove('show');
}

function openRosterForm() {
    closeDropdown();
    var rosterForm = document.getElementById('rosterForm');
    var leaveForm = document.getElementById('leaveRequestForm');
    if (rosterForm) rosterForm.classList.add('show');
    if (leaveForm) leaveForm.classList.remove('show');
    calculateEndTime();
}

function closeRosterForm() {
    var form = document.getElementById('rosterForm');
    if (form) form.classList.remove('show');
}

function calculateEndTime() {
    var startTimeSelect = document.getElementById('roster_start_time');
    if (!startTimeSelect) return;
    var startTime = startTimeSelect.value;
    var role = '<?php echo $user['role'] ?? 'agent'; ?>';
    var workingHours = 9;
    if (role === 'process_head') workingHours = 8;

    var workingHoursDisplay = document.getElementById('workingHoursDisplay');
    var endTimeDisplay = document.getElementById('endTimeDisplay');

    if (workingHoursDisplay) {
        workingHoursDisplay.textContent = workingHours + ' hrs';
    }

    var timeParts = startTime.match(/(\d+):(\d+)\s*(AM|PM)/);
    if (!timeParts) return;

    var hours = parseInt(timeParts[1]);
    var minutes = parseInt(timeParts[2]);
    var ampm = timeParts[3];

    if (ampm === 'PM' && hours !== 12) hours += 12;
    if (ampm === 'AM' && hours === 12) hours = 0;

    var totalMinutes = hours * 60 + minutes + workingHours * 60;
    var endHours = Math.floor(totalMinutes / 60);
    var endMinutes = totalMinutes % 60;

    var endAmpm = endHours >= 12 ? 'PM' : 'AM';
    var displayHours = endHours % 12;
    if (displayHours === 0) displayHours = 12;
    var displayMinutes = String(endMinutes).padStart(2, '0');

    if (endTimeDisplay) {
        endTimeDisplay.textContent = displayHours + ':' + displayMinutes + ' ' + endAmpm;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    var container = document.querySelector('.dropdown-container');
    if (container && !container.contains(event.target)) {
        var menu = document.getElementById('dropdownMenu');
        var arrow = document.getElementById('dropdownArrow');
        if (menu) menu.classList.remove('show');
        if (arrow) arrow.classList.remove('open');
    }
});

// ===== TEAM NAVIGATION =====
function navigateToTeam(userId, role) {
    var url = 'dashboard.php?user_id=' + userId + '&role=' + role;
    var urlParams = new URLSearchParams(window.location.search);
    var month = urlParams.get('month');
    var year = urlParams.get('year');
    if (month) url += '&month=' + month;
    if (year) url += '&year=' + year;
    window.location.href = url;
}

function viewAgentAttendance(agentId, agentName) {
    var url = 'dashboard.php?view=attendance&agent_id=' + agentId + '&user_id=' + agentId + '&role=agent';
    var urlParams = new URLSearchParams(window.location.search);
    var month = urlParams.get('month');
    var year = urlParams.get('year');
    if (month) url += '&month=' + month;
    if (year) url += '&year=' + year;
    window.location.href = url;
}

// ===== FAB =====
function toggleFab() {
    var menu = document.getElementById('fabMenu');
    var button = document.getElementById('fabButton');
    if (menu) menu.classList.toggle('open');
    if (button) button.classList.toggle('open');
}

document.addEventListener('click', function(event) {
    var container = document.querySelector('.fab-container');
    if (container && !container.contains(event.target)) {
        var menu = document.getElementById('fabMenu');
        var button = document.getElementById('fabButton');
        if (menu) menu.classList.remove('open');
        if (button) button.classList.remove('open');
    }
});

// ===== MODAL OVERLAY CLOSE =====
window.onclick = function(event) {
    if (event.target.className === 'modal-overlay') {
        event.target.style.display = 'none';
    }
}

// ===== KEYBOARD SHORTCUTS =====
document.addEventListener('keydown', function(e) {
    // Escape key - close modals
    if (e.key === 'Escape') {
        if (typeof closeExceptionModal === 'function') closeExceptionModal();
        if (typeof closeApprovalModal === 'function') closeApprovalModal();
        if (typeof closeOTReportModal === 'function') closeOTReportModal();
        if (typeof closeOTModal === 'function') closeOTModal();
        closeDropdown();
        closeResignationForm();
        closeResignationDetail();
    }
    // Ctrl+R - refresh
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        window.location.reload();
    }
});

// ===== AUTO REFRESH =====
// Refresh every 5 minutes to keep data current
setTimeout(function() {
    if (!document.querySelector('.modal-overlay[style*="display: flex"]')) {
        window.location.reload();
    }
}, 300000);

// ===== APPROVAL MODAL =====
function openApprovalModal(id, type, status, requester, date, reason) {
    const modal = document.getElementById('approvalModal');
    document.getElementById('modalRequestId').value = id;
    document.getElementById('modalRequestType').value = type;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalRequester').textContent = requester;
    document.getElementById('modalDate').textContent = date;

    var typeDisplay = '';
    // Normalize type to lowercase for comparison
    var t = type ? type.toLowerCase() : '';

    if (t === 'pl') {
        typeDisplay = 'PLANNED LEAVE (PL)';
    } else if (t === 'leave') {
        typeDisplay = 'LEAVE (LWP)';
    } else if (t === 'late') {
        typeDisplay = 'LATE EXCEPTION';
    } else if (t === 'half_day') {
        typeDisplay = 'HALF DAY';
    } else if (t === 'coming_late') {
        typeDisplay = 'COMING LATE';
    } else if (t === 'early_leave') {
        typeDisplay = 'EARLY LEAVE';
    } else if (t === 'roster') {
        typeDisplay = 'ROSTER';
    } else {
        typeDisplay = type ? type.toUpperCase().replace('_', ' ') : 'REQUEST';
    }

    document.getElementById('modalType').textContent = typeDisplay;
    document.getElementById('modalReason').textContent = reason || 'No reason provided';
    document.getElementById('modalRemarks').value = '';

    // Handle Title and Buttons UI
    const title = document.getElementById('modalTitle');
    const actionText = document.getElementById('modalActionText');
    const confirmBtn = document.getElementById('modalConfirmBtn');

    if (status === 'approved') {
        title.innerHTML = '<i class="fas fa-check-circle" style="color:var(--green);"></i> <span id="modalActionText">APPROVE REQUEST</span>';
        actionText.textContent = 'APPROVE REQUEST';
        confirmBtn.className = 'btn-confirm-approve';
        confirmBtn.innerHTML = '<i class="fas fa-check"></i> CONFIRM';
    } else {
        title.innerHTML = '<i class="fas fa-times-circle" style="color:var(--red);"></i> <span id="modalActionText">REJECT REQUEST</span>';
        actionText.textContent = 'REJECT REQUEST';
        confirmBtn.className = 'btn-confirm-reject';
        confirmBtn.innerHTML = '<i class="fas fa-times"></i> CONFIRM';
    }

    modal.style.display = 'flex';
}
function closeApprovalModal() {
    document.getElementById('approvalModal').style.display = 'none';
}

document.getElementById('approvalForm').addEventListener('submit', function(e) {
    var remarks = document.getElementById('modalRemarks').value.trim();
    if (!remarks) {
        e.preventDefault();
        alert('Please enter remarks before submitting.');
        document.getElementById('modalRemarks').focus();
    }
});

// ============================================
// NOTIFICATION FUNCTION
// ============================================
function showNotification(type, message) {
    // Remove existing notifications
    const existing = document.querySelector('.custom-notification');
    if (existing) {
        existing.remove();
    }

    const colors = {
        success: { bg: '#dcfce7', border: '#16a34a', color: '#166534', icon: 'fa-check-circle' },
        error: { bg: '#fee2e2', border: '#dc2626', color: '#991b1b', icon: 'fa-times-circle' },
        warning: { bg: '#fef3c7', border: '#d97706', color: '#92400e', icon: 'fa-exclamation-triangle' },
        info: { bg: '#dbeafe', border: '#2563eb', color: '#1e40af', icon: 'fa-info-circle' }
    };

    const style = colors[type] || colors.info;

    const notification = document.createElement('div');
    notification.className = 'custom-notification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${style.bg};
        border-left: 4px solid ${style.border};
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        z-index: 9999999;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease;
        font-family: 'Inter', sans-serif;
    `;

    notification.innerHTML = `
        <i class="fas ${style.icon}" style="color: ${style.border}; font-size: 20px;"></i>
        <div>
            <div style="font-weight: 600; color: ${style.color};">${message}</div>
        </div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: ${style.color}; opacity: 0.6; padding: 0 4px;">

        </button>
    `;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100px)';
            notification.style.transition = 'all 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Add animation keyframes
const styleSheet = document.createElement('style');
styleSheet.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(styleSheet);

// ============================================
// RESIGNATION FUNCTIONS
// ============================================

/**
 * Open Resignation Form Modal
 */
function openResignationForm() {
    var modal = document.getElementById('resignationFormModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Set min date for last working day
        var today = new Date();
        var minDate = new Date();
        minDate.setDate(today.getDate() + 15);

        var year = minDate.getFullYear();
        var month = String(minDate.getMonth() + 1).padStart(2, '0');
        var day = String(minDate.getDate()).padStart(2, '0');

        var lastWorkingDate = document.getElementById('lastWorkingDate');
        if (lastWorkingDate) {
            lastWorkingDate.min = year + '-' + month + '-' + day;
        }
    }
}

/**
 * Close Resignation Form Modal
 */
function closeResignationForm() {
    var modal = document.getElementById('resignationFormModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
 * Submit Resignation Form
 */
function submitResignation(event) {
    if (!event) return;
    event.preventDefault();

    const form = document.getElementById('resignationForm');
    const submitBtn = document.getElementById('submitResignationBtn');
    if (!form || !submitBtn) return;

    const formData = new FormData(form);

    // Validate last working date (minimum 15 days from today)
    const lastWorkingDate = new Date(formData.get('last_working_date'));
    const today = new Date();
    const minDate = new Date();
    minDate.setDate(today.getDate() + 15);

    if (lastWorkingDate < minDate) {
        showNotification('error', 'Last working day must be at least 15 days from today.');
        return;
    }

    // Validate resignation date
    const resignationDate = new Date(formData.get('resignation_date'));
    if (resignationDate > today) {
        showNotification('error', 'Resignation date cannot be in the future.');
        return;
    }

    // Validate notice period
    const noticePeriod = parseInt(formData.get('notice_period_days'));
    if (noticePeriod < 15) {
        showNotification('error', 'Notice period must be at least 15 days.');
        return;
    }

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
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit';
    });
}

/**
 * Close Resignation Detail Modal
 */
function closeResignationDetail() {
    var modal = document.getElementById('resignationDetailModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
 * View Resignation Detail - Only for resignations.php page
 */
function viewResignationDetail(id) {
    const modal = document.getElementById('resignationDetailModal');
    const body = document.getElementById('resignationDetailBody');

    // Check if the modal exists on this page
    if (!modal || !body) {
        console.log('Resignation detail modal not found on this page. Redirecting to resignations.php');
        window.location.href = 'resignations.php?view=' + id;
        return;
    }

    body.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 36px; color: #2563eb;"></i>
            <p style="margin-top: 15px; color: #64748b;">Loading resignation details...</p>
        </div>
    `;

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    fetch('includes/modules/resignation/get_resignation_detail.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderResignationDetail(data.resignation);
            } else {
                body.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #dc2626;">
                        <i class="fas fa-exclamation-circle" style="font-size: 36px;"></i>
                        <p style="margin-top: 15px;">${data.message || 'Failed to load details'}</p>
                        <button onclick="closeResignationDetail()" style="margin-top: 15px; padding: 8px 24px; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            body.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #dc2626;">
                    <i class="fas fa-exclamation-circle" style="font-size: 36px;"></i>
                    <p style="margin-top: 15px;">An error occurred. Please try again.</p>
                    <button onclick="closeResignationDetail()" style="margin-top: 15px; padding: 8px 24px; background: #f1f5f9; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
                </div>
            `;
        });
}

/**
 * Render Resignation Detail
 */
function renderResignationDetail(res) {
    const body = document.getElementById('resignationDetailBody');
    if (!body) return;

    const statusColors = {
        pending: { bg: '#fef3c7', color: '#d97706', icon: 'fa-clock', text: 'Pending' },
        approved: { bg: '#dcfce7', color: '#16a34a', icon: 'fa-check-circle', text: 'Approved' },
        rejected: { bg: '#fee2e2', color: '#dc2626', icon: 'fa-times-circle', text: 'Rejected' }
    };

    const status = statusColors[res.status] || statusColors.pending;

    const levelColors = {
        process_head: { bg: '#dbeafe', color: '#2563eb', text: 'Waiting for Process Head Approval' },
        hr: { bg: '#ede9fe', color: '#7c3aed', text: 'Waiting for HR Approval' },
        completed: { bg: '#dcfce7', color: '#16a34a', text: 'Completed' },
        rejected: { bg: '#fee2e2', color: '#dc2626', text: 'Rejected' }
    };

    const level = levelColors[res.approval_level] || levelColors.process_head;

    // Update status badge in header - check if element exists
    const statusBadge = document.getElementById('detailStatusBadge');
    if (statusBadge) {
        statusBadge.innerHTML = `
            <span style="background: ${status.bg}; color: ${status.color}; padding: 4px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; margin-left: 12px;">
                <i class="fas ${status.icon}"></i>
                ${status.text}
            </span>
        `;
    }

    let html = `
        <div class="detail-grid">
            <div class="detail-item">
                <span class="label">Employee Name</span>
                <span class="value">${res.employee_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="label">Position</span>
                <span class="value">${res.position || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="label">Company</span>
                <span class="value">${res.company_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="label">Resignation Date</span>
                <span class="value">${formatDate(res.resignation_date)}</span>
            </div>
            <div class="detail-item">
                <span class="label">Last Working Day</span>
                <span class="value" style="color: #dc2626;">${formatDate(res.last_working_date)}</span>
            </div>
            <div class="detail-item">
                <span class="label">Submitted Date</span>
                <span class="value">${formatDateTime(res.submitted_date)}</span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1;">
                <span class="label">Reason for Leaving</span>
                <span class="value reason-text">${res.reason || 'Not specified'}</span>
            </div>
            <div class="detail-item" style="grid-column: 1 / -1; border-bottom: none;">
                <span class="label">Approval Status</span>
                <div style="margin-top: 8px;">
                    <div class="history-box ${res.approval_level || 'process_head'}">
                        <span class="history-text" style="color: ${level.color};">
                            <i class="fas ${res.status === 'pending' ? 'fa-spinner fa-spin' : res.status === 'approved' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                            ${level.text}
                        </span>
                    </div>
    `;

    // Show Process Head approval info if approved
    if (res.process_head_approved_by && res.process_head_approved_date) {
        html += `
            <div class="history-box process_head" style="margin-top: 8px;">
                <span class="history-text" style="color: #2563eb;">
                    <i class="fas fa-check-circle"></i>
                    Process Head approved on ${formatDateTime(res.process_head_approved_date)}
                    ${res.process_head_comment ? '<br><small style="color: #64748b;">Comment: ' + res.process_head_comment + '</small>' : ''}
                </span>
            </div>
        `;
    }

    // Show HR approval info if approved
    if (res.status === 'approved' && res.approved_by) {
        html += `
            <div class="history-box approved" style="margin-top: 8px;">
                <span class="history-text approved-text">
                    <i class="fas fa-check-circle"></i>
                    HR approved on ${formatDateTime(res.approved_date)}
                    ${res.hr_comment ? '<br><small style="color: #64748b;">Comment: ' + res.hr_comment + '</small>' : ''}
                </span>
            </div>
        `;
    }

    // Show rejection info
    if (res.status === 'rejected') {
        html += `
            <div class="history-box rejected" style="margin-top: 8px;">
                <span class="history-text rejected-text">
                    <i class="fas fa-times-circle"></i>
                    Rejected on ${formatDateTime(res.rejected_date)}
                    ${res.rejection_reason ? '<br><small style="color: #64748b;">Reason: ' + res.rejection_reason + '</small>' : ''}
                </span>
            </div>
        `;
    }

    html += `
                </div>
            </div>
        </div>
    `;

    // Comments section
    html += `
        <div class="comment-box">
            <label><i class="fas fa-comment"></i> Comments</label>
            <textarea id="approvalComment" placeholder="Add your comments here...">${res.hr_comment || ''}</textarea>
        </div>
    `;

    // Actions based on role and approval level - Using global variables
    const canApprove = res.status === 'pending';
    const isDesignatedHr = typeof window.isDesignatedHr !== 'undefined' ? window.isDesignatedHr : false;
    const isProcessHead = typeof window.isProcessHead !== 'undefined' ? window.isProcessHead : false;
    const userRole = typeof window.userRole !== 'undefined' ? window.userRole : '';
    const isProcessHeadLevel = res.approval_level === 'process_head';
    const isHRLevel = res.approval_level === 'hr';

    let showApprove = false;
    let showReject = false;
    let actionMessage = '';

    if (canApprove) {
        // FNM5735 can approve at HR level regardless of role
        if (isDesignatedHr && isHRLevel) {
            showApprove = true;
            showReject = true;
            actionMessage = 'HR final approval required (FNM5735)';
        }
        // FNM5735 can also approve at Process Head level if they have that role
        else if (isDesignatedHr && isProcessHead && isProcessHeadLevel) {
            showApprove = true;
            showReject = true;
            actionMessage = 'Process Head approval (FNM5735 with Process Head role)';
        }
        // Normal Process Head (not FNM5735) can approve at Process Head level
        else if (isProcessHead && !isDesignatedHr && isProcessHeadLevel) {
            showApprove = true;
            showReject = true;
            actionMessage = 'Process Head approval required';
        }
        // FNM5735 at Process Head level but waiting for Process Head
        else if (isDesignatedHr && isProcessHeadLevel && !isProcessHead) {
            showReject = true;
            actionMessage = 'Process Head must approve first (FNM5735 view only at this stage)';
        }
        // Other scenarios
        else if (isDesignatedHr && !isHRLevel && !isProcessHeadLevel) {
            actionMessage = 'This resignation is not pending for approval.';
        } else if (!isDesignatedHr && !isProcessHead) {
            actionMessage = 'You do not have permission to take action. Only Process Head or Designated HR (FNM5735) can take action.';
        } else {
            actionMessage = 'You do not have permission to take action at this stage.';
        }
    } else {
        if (res.status === 'approved') {
            actionMessage = 'This resignation has been approved.';
        } else if (res.status === 'rejected') {
            actionMessage = 'This resignation has been rejected.';
        } else {
            actionMessage = 'This resignation is already processed.';
        }
    }

    html += `
        <div class="detail-actions">
            ${showApprove ? `<button class="btn-approve" onclick="handleApproval(${res.id}, 'approve')">
                <i class="fas fa-check"></i> Approve
            </button>` : ''}
            ${showReject ? `<button class="btn-reject" onclick="handleApproval(${res.id}, 'reject')">
                <i class="fas fa-times"></i> Reject
            </button>` : ''}
            ${actionMessage ? `<span style="font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-info-circle"></i> ${actionMessage}
            </span>` : ''}
            <button class="btn-close-modal" onclick="closeResignationDetail()" ${showApprove || showReject ? '' : 'style="margin-left: 0;"'}>
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    `;

    body.innerHTML = html;
}

/**
 * Handle Approval Action
 */
function handleApproval(id, action) {
    const comment = document.getElementById('approvalComment')?.value || '';

    if (!confirm(`Are you sure you want to ${action} this resignation?`)) {
        return;
    }

    const btn = action === 'approve' ? document.querySelector('.btn-approve') : document.querySelector('.btn-reject');
    if (!btn) return;

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('resignation_id', id);
    formData.append('comment', comment);

    fetch('includes/modules/resignation/resignation_approval_handler.php', {
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
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

/**
 * Format Date
 */
function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric'
    });
}

/**
 * Format Date and Time
 */
function formatDateTime(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Search Resignations
 */
function searchResignations() {
    const search = document.getElementById('searchInput');
    if (!search) return;
    const statusSelect = document.querySelector('select[onchange*="status"]');
    const statusValue = statusSelect ? statusSelect.value : '';
    window.location.href = '?status=' + statusValue + '&search=' + encodeURIComponent(search.value);
}

/**
 * Clear Search
 */
function clearSearch() {
    const search = document.getElementById('searchInput');
    if (search) {
        search.value = '';
    }
    searchResignations();
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    var modal = document.getElementById('resignationFormModal');
    if (modal && event.target === modal) {
        closeResignationForm();
    }

    var detailModal = document.getElementById('resignationDetailModal');
    if (detailModal && event.target === detailModal) {
        closeResignationDetail();
    }
});

// ============================================
// OT MODAL FUNCTIONS (Submit Request — for employees)
// ============================================
function openOTModal(date, workHours, otHours) {
    const modal = document.getElementById('otModal');
    if (!modal) return;

    document.getElementById('otDate').value = date;
    document.getElementById('otWorkHours').value = workHours;
    document.getElementById('otHours').value = otHours;

    // Format date for display
    const d = new Date(date + 'T00:00:00');
    document.getElementById('otDateDisplay').textContent = d.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });

    document.getElementById('otWorkHoursDisplay').textContent = formatWorkHoursDisplay(workHours);
    document.getElementById('otHoursDisplay').textContent = otHours + 'h';

    document.getElementById('otReason').value = '';
    modal.classList.add('show');
}

function closeOTModal() {
    const modal = document.getElementById('otModal');
    if (modal) modal.classList.remove('show');
}

function formatWorkHoursDisplay(hours) {
    if (hours <= 0) return '00:00';
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}

// OT Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const otForm = document.getElementById('otForm');
    if (otForm) {
        otForm.addEventListener('submit', function(e) {
            const reason = document.getElementById('otReason').value.trim();
            if (!reason) {
                e.preventDefault();
                alert('Please enter a reason for OT request.');
                return false;
            }
            return true;
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const typeField = document.querySelector('select[name="exception_type"]');
    const firstDateField = document.querySelector('input[name="date"]');
    const secondDateDropdown = document.querySelector('select[name="second_date"]');

    if (!typeField || !firstDateField || !secondDateDropdown) return;

    function applyWeekoffLogic() {
        const type = typeField.value;
        const selectedDate = firstDateField.value;

        if (type === 'weekoff_exception' && selectedDate) {
            const d = new Date(selectedDate);

            // Check if Sunday
            if (d.getDay() !== 0) {
                alert("Select valid date: Weekoff Exception can only be applied to Sundays.");
                firstDateField.value = "";
                return;
            }

            // AUTO-SELECT in second dropdown and DISABLE others
            let dateFound = false;
            Array.from(secondDateDropdown.options).forEach(option => {
                if (option.value === selectedDate) {
                    option.selected = true;
                    option.disabled = false;
                    dateFound = true;
                } else {
                    option.disabled = true;
                }
            });

            if (!dateFound) {
                const newOpt = new Option(selectedDate, selectedDate, true, true);
                secondDateDropdown.add(newOpt);
            }
        } else {
            Array.from(secondDateDropdown.options).forEach(option => {
                option.disabled = false;
            });
        }
    }

    typeField.addEventListener('change', applyWeekoffLogic);
    firstDateField.addEventListener('change', applyWeekoffLogic);
});

// Click outside to close OT submit modal
document.addEventListener('mousedown', function(event) {
    const modal = document.getElementById('otModal');
    if (modal && modal.classList.contains('show')) {
        const modalBox = modal.querySelector('.modal-box');
        if (modalBox && !modalBox.contains(event.target)) {
            closeOTModal();
        }
    }
});

// Auto-redirect to login page after 30 minutes of inactivity
let idleTime = 0;
const maxIdle = 30; // Minutes

setInterval(timerIncrement, 60000);

window.onmousemove = resetTimer;
window.onkeypress = resetTimer;

function resetTimer() {
    idleTime = 0;
}

function timerIncrement() {
    idleTime++;
    if (idleTime >= maxIdle) {
        window.location.href = "index.php";
    }
}

if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

window.addEventListener('DOMContentLoaded', (event) => {
    const scrollPos = sessionStorage.getItem('sidebar-scroll');
    if (scrollPos) {
        window.scrollTo(0, scrollPos);
    }
});

window.addEventListener('beforeunload', () => {
    sessionStorage.setItem('sidebar-scroll', window.scrollY);
});

// ============================================
// OT REPORT MODAL (view-only, for FNM10824)
// ============================================
function openOTReportModal() {
    var modal = document.getElementById('otReportModal');
    if (!modal) return;
    modal.style.display = 'flex';
    var overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.classList.add('active');
}

function closeOTReportModal() {
    var modal = document.getElementById('otReportModal');
    if (!modal) return;
    modal.style.display = 'none';
    var overlay = document.getElementById('sidebarOverlay');
    if (overlay) overlay.classList.remove('active');
}

function processOTReport() {
    var el = document.getElementById('otReportMonthYear');
    if (!el) return;
    var val = el.value;
    if (!val) { alert('Please select a month'); return; }
    var parts = val.split('-');
    window.location.href = BASE_PATH + '/ot_data.php?year=' + parts[0] + '&month=' + parseInt(parts[1], 10);
}

// Close report modal when clicking the dark overlay
document.addEventListener('mousedown', function(event) {
    var modal = document.getElementById('otReportModal');
    if (modal && modal.style.display === 'flex' && event.target === modal) {
        closeOTReportModal();
    }
});

// Expose functions globally (in case any inline onclick needs them)
window.openOTModal          = openOTModal;
window.closeOTModal         = closeOTModal;
window.openOTReportModal    = openOTReportModal;
window.closeOTReportModal   = closeOTReportModal;
window.processOTReport      = processOTReport;

console.log('Dashboard loaded successfully!');
console.log('User: <?php echo htmlspecialchars($user['full_name']); ?>');
console.log('Role: <?php echo htmlspecialchars($user['role'] ?? 'agent'); ?>');

</script>