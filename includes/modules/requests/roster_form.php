<?php
// ============================================
// ROSTER REQUEST FORM
// ============================================
?>
<div class="request-form" id="rosterForm">
    <h4 style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-clock" style="color:var(--blue);"></i> ROSTER REQUEST
        <button style="margin-left:auto;background:none;border:none;font-size:18px;cursor:pointer;color:var(--text-muted);" onclick="closeRosterForm()">&times;</button>
    </h4>
    <form method="POST" action="" id="rosterFormSubmit">
        <input type="hidden" name="action" value="submit_roster_request">

        <div class="form-row">
            <div class="form-group">
                <label for="roster_start_date"><i class="fas fa-calendar-alt"></i> Start Date</label>
                <input type="date" id="roster_start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label for="roster_end_date"><i class="fas fa-calendar-alt"></i> End Date</label>
                <input type="date" id="roster_end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-group-full">
                <label for="roster_start_time"><i class="fas fa-clock"></i> Start Time</label>
                <select id="roster_start_time" name="start_time" class="request-type-select" required onchange="calculateEndTime()">
                    <option value="08:00 AM">08:00 AM</option>
                    <option value="08:30 AM">08:30 AM</option>
                    <option value="09:00 AM" selected>09:00 AM</option>
                    <option value="09:30 AM">09:30 AM</option>
                    <option value="10:00 AM">10:00 AM</option>
                    <option value="10:30 AM">10:30 AM</option>
                    <option value="11:00 AM">11:00 AM</option>
                    <option value="11:30 AM">11:30 AM</option>
                    <option value="12:00 PM">12:00 PM</option>
                    <option value="12:30 PM">12:30 PM</option>
                    <option value="01:00 PM">01:00 PM</option>
                    <option value="01:30 PM">01:30 PM</option>
                    <option value="02:00 PM">02:00 PM</option>
                </select>
            </div>
        </div>

        <div class="time-display" id="timeDisplay">
            <span><span class="label">Working Hours:</span> <span class="value" id="workingHoursDisplay">9 hrs</span></span>
            <span><span class="label">End Time:</span> <span class="value" id="endTimeDisplay">06:00 PM</span></span>
        </div>

        <div class="form-row">
            <div class="form-group form-group-full">
                <label for="roster_reason"><i class="fas fa-comment"></i> Reason</label>
                <textarea id="roster_reason" name="reason" placeholder="Please describe the reason for your roster request..." required></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-cancel-request" onclick="closeRosterForm()">CANCEL</button>
            <button type="submit" class="btn-submit-request"><i class="fas fa-paper-plane"></i> SUBMIT ROSTER</button>
        </div>
    </form>
</div>