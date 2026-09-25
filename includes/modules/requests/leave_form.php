<?php
// ============================================
// LEAVE REQUEST FORM - UPDATED
// ============================================
?>
<div class="request-form" id="leaveRequestForm">
    <h4 style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-calendar-plus" style="color:var(--blue);"></i> REQUEST LEAVE
        <button style="margin-left:auto;background:none;border:none;font-size:18px;cursor:pointer;color:var(--text-muted);" onclick="closeLeaveRequestForm()">&times;</button>
    </h4>
    <form method="POST" action="" id="leaveRequestFormSubmit" onsubmit="return validatePLBalance()">
        <input type="hidden" name="action" value="submit_leave_request">

        <!-- NEW: PL Balance Display -->
        <div style="background: #f0f7ff; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #4A90D9;">
            <span style="font-size: 13px; color: #2c3e50; font-weight: 600;">
                <i class="fas fa-coins" style="color: #4A90D9;"></i> Available PL Balance:
            </span>
            <span id="plBalanceDisplay" style="font-size: 16px; font-weight: 700; color: <?php echo $user['pl_balance'] > 0 ? '#27ae60' : '#e74c3c'; ?>;">
                <?php echo number_format($user['pl_balance'] ?? 0, 1); ?> days
            </span>

                     </div>

        <div class="form-row">
            <div class="form-group">
                <label for="leave_start_date"><i class="fas fa-calendar-alt"></i> Start Date</label>
                <input type="date" id="leave_start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required onchange="calculateLeaveDays()">
            </div>
            <div class="form-group">
                <label for="leave_end_date"><i class="fas fa-calendar-alt"></i> End Date</label>
                <input type="date" id="leave_end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required onchange="calculateLeaveDays()">
            </div>
        </div>

        <!-- NEW: Days count display -->
        <div id="leaveDaysDisplay" style="display: none; background: #fff3cd; padding: 8px 12px; border-radius: 4px; margin-bottom: 10px; font-size: 13px; color: #856404;">
            <i class="fas fa-info-circle"></i> 
            <span id="leaveDaysCount">0</span> day(s) requested
        </div>

        <div class="form-row">
            <div class="form-group form-group-full">
                <label for="leave_type"><i class="fas fa-tag"></i> Leave Type</label>
                <select id="leave_type" name="leave_type" class="request-type-select" required onchange="togglePLWarning()">
                    <option value="leave">Leave (LWP)</option>
                    <option value="pl">Planned Leave (PL)</option>
                    
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-group-full">
                <label for="leave_reason"><i class="fas fa-comment"></i> Reason</label>
                <textarea id="leave_reason" name="reason" placeholder="Please describe the reason for your leave request..." required></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-cancel-request" onclick="closeLeaveRequestForm()">CANCEL</button>
            <button type="submit" class="btn-submit-request"><i class="fas fa-paper-plane"></i> SUBMIT REQUEST</button>
        </div>
    </form>
</div>

<script>
function calculateLeaveDays() {
    var startDate = document.getElementById('leave_start_date').value;
    var endDate = document.getElementById('leave_end_date').value;
    var displayDiv = document.getElementById('leaveDaysDisplay');
    var countSpan = document.getElementById('leaveDaysCount');
    
    if (startDate && endDate) {
        var start = new Date(startDate);
        var end = new Date(endDate);
        var diffTime = Math.abs(end - start);
        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        
        countSpan.textContent = diffDays;
        displayDiv.style.display = 'block';
        
        // Check if PL is selected and balance is sufficient
        var leaveType = document.getElementById('leave_type').value;
        var balance = <?php echo $user['pl_balance'] ?? 0; ?>;
        
        if (leaveType === 'pl' && diffDays > balance) {
            displayDiv.style.background = '#f8d7da';
            displayDiv.style.color = '#721c24';
            displayDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Warning: You only have ' + balance + ' PL days available, but requested ' + diffDays + ' days.';
        } else {
            displayDiv.style.background = '#fff3cd';
            displayDiv.style.color = '#856404';
            displayDiv.innerHTML = '<i class="fas fa-info-circle"></i> <span id="leaveDaysCount">' + diffDays + '</span> day(s) requested';
        }
    }
}

function togglePLWarning() {
    calculateLeaveDays();
}

function validatePLBalance() {
    var leaveType = document.getElementById('leave_type').value;
    var startDate = document.getElementById('leave_start_date').value;
    var endDate = document.getElementById('leave_end_date').value;
    
    if (leaveType === 'pl' && startDate && endDate) {
        var start = new Date(startDate);
        var end = new Date(endDate);
        var diffTime = Math.abs(end - start);
        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        var balance = <?php echo $user['pl_balance'] ?? 0; ?>;
        
        if (diffDays > balance) {
            alert('You have insufficient PL balance. Available: ' + balance + ' days, Requested: ' + diffDays + ' days.');
            return false;
        }
    }
    return true;
}

// Calculate days on form load
document.addEventListener('DOMContentLoaded', function() {
    calculateLeaveDays();
});

function validateLeaveRequest() {
    var leaveType = document.getElementById('leave_type').value;
    var coBalance = <?php echo $available_co; ?>;
    
    if (leaveType === 'comp_off' && coBalance <= 0) {
        alert("You cannot apply for Comp Off. You haven't worked on any Sundays to earn a credit!");
        return false;
    }
    return true; // proceed with other validations (PL balance, etc)
}
</script>