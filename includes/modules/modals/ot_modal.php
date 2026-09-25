<!-- ===== OT REQUEST MODAL (submit form) ===== -->
<div id="otModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-clock" style="color: #2563eb;"></i> REQUEST OVERTIME</h3>
            <button class="close-btn" onclick="closeOTModal()">&times;</button>
        </div>

        <div style="padding: 5px 0;">
            <div style="background: #e0f2fe; padding: 14px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #2563eb;">
                <div style="display: flex; justify-content: space-between; font-size: 14px; flex-wrap: wrap; gap: 8px;">
                    <span><strong>?? Date:</strong> <span id="otDateDisplay"></span></span>
                    <span><strong>?? Work Hours:</strong> <span id="otWorkHoursDisplay"></span></span>
                    <span><strong>?? OT Hours:</strong> <span id="otHoursDisplay" style="color: #2563eb; font-weight: 800; font-size: 16px;"></span></span>
                </div>
                <div style="margin-top: 8px; font-size: 12px; color: #64748b; background: #f0f9ff; padding: 6px 10px; border-radius: 6px;">
                    <i class="fas fa-info-circle"></i>
                    OT is calculated only for <strong>full hours</strong> (1h, 2h, 3h...).
                    Extra minutes are not counted.
                </div>
            </div>

            <form method="POST" id="otForm" action="dashboard.php">
                <input type="hidden" name="action" value="submit_overtime">
                <input type="hidden" id="otDate" name="date">
                <input type="hidden" id="otWorkHours" name="work_hours">
                <input type="hidden" id="otHours" name="ot_hours">

                <div style="margin-bottom: 12px;">
                    <label style="font-weight: 700; display: block; margin-bottom: 4px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fas fa-pencil-alt"></i> REASON FOR OVERTIME
                    </label>
                    <textarea name="reason" id="otReason" required
                        placeholder="Describe why you need OT approval (e.g., Project deadline, Extra work, Client call, etc.)..."
                        style="width: 100%; height: 80px; padding: 10px 12px; border: 1px solid #e8edf4; border-radius: 10px; font-family: inherit; font-size: 13px; resize: vertical; transition: 0.3s; background: #ffffff; color: #0a1628;"></textarea>
                </div>

                <div style="background: #fef3c7; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #d97706;">
                    <div style="font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clock" style="font-size: 14px;"></i>
                        <span>? You can request OT only until <strong>tomorrow</strong>. After that, the request will expire.</span>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 10px;">
                    <button type="button" onclick="closeOTModal()"
                        style="background: #f4f6fa; color: #1e293b; border: 1px solid #e8edf4; padding: 8px 24px; border-radius: 50px; cursor: pointer; font-size: 12px; font-weight: 700; transition: 0.3s;">
                        CANCEL
                    </button>
                    <button type="submit"
                        style="background: linear-gradient(135deg, #2563eb, #7c3aed); color: #fff; border: none; padding: 8px 24px; border-radius: 50px; cursor: pointer; font-size: 12px; font-weight: 700; transition: 0.3s; box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);">
                        <i class="fas fa-paper-plane"></i> SUBMIT OT REQUEST
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== OT REPORT MODAL (view-only, only for FNM10824) ===== -->
<?php if (!empty($can_view_ot)): ?>
<div id="otReportModal" class="sidebar-overlay" style="display:none; justify-content: center; align-items: center;">
    <div style="background: #fff; padding: 25px; border-radius: 15px; width: 380px; position: relative; box-shadow: 0 8px 28px rgba(0,0,0,0.08);">
        <h3 style="margin-bottom: 15px; font-size: 18px; color: #0a1628;">
            <i class="fas fa-clock" style="color:#d97706; margin-right:10px;"></i> Overtime Report
        </h3>

        <label style="display:block; margin-bottom: 5px; font-size: 12px; font-weight: 700; color:#64748b;">
            SELECT MONTH:
        </label>
        <input type="month" id="otReportMonthYear" value="<?php echo date('Y-m'); ?>"
               style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; font-weight:600;">

        <div style="display: flex; gap: 10px;">
            <button type="button" onclick="processOTReport()"
                style="flex:1; background: linear-gradient(135deg, #d97706, #f59e0b); color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 700;">
                <i class="fas fa-eye"></i> VIEW
            </button>
            <button type="button" onclick="closeOTReportModal()"
                style="flex:1; background: #f1f5f9; color: #64748b; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 700;">
                CANCEL
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ============================================
   OT REQUEST MODAL STYLES (submit form)
   ============================================ */
#otModal .modal-box {
    max-width: 500px !important;
}

.btn-ot-apply {
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: #fff;
    border: none;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 9px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 4px;
    width: 100%;
    white-space: nowrap;
}

.btn-ot-apply:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 12px rgba(37, 99, 235, 0.4);
}

.btn-ot-apply i {
    font-size: 8px;
    margin-right: 3px;
}

.ot-status-badge {
    font-size: 9px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    display: inline-block;
    margin-top: 2px;
    width: 100%;
    text-align: center;
}

.ot-status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.ot-status-badge.approved {
    background: #dcfce7;
    color: #16a34a;
}

.ot-status-badge.rejected {
    background: #fee2e2;
    color: #dc2626;
}

.ot-not-eligible {
    font-size: 8px;
    color: #94a3b8;
    display: block;
    text-align: center;
    margin-top: 2px;
    cursor: help;
}
</style>