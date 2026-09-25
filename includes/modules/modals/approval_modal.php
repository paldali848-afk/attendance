 <?php
// ============================================
// APPROVAL MODAL
// ============================================
?>
<div id="approvalModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-check-circle" style="color:var(--green);"></i> <span id="modalActionText">APPROVE REQUEST</span></h3>
            <button class="close-btn" onclick="closeApprovalModal()">&times;</button>
        </div>
        <div class="request-info">
            <p><span class="label">REQUESTER:</span> <span id="modalRequester"></span></p>
            <p><span class="label">DATE:</span> <span id="modalDate"></span></p>
            <p><span class="label">TYPE:</span> <span id="modalType"></span></p>
            <p><span class="label">REASON:</span> <span id="modalReason"></span></p>
        </div>
        <form method="POST" id="approvalForm">
            <input type="hidden" name="action" value="process_approval">
            <input type="hidden" name="request_id" id="modalRequestId">
            <input type="hidden" name="request_type" id="modalRequestType">
            <input type="hidden" name="status" id="modalStatus">
            <div style="margin-bottom:8px;">
                <label style="font-weight:700;display:block;margin-bottom:4px;color:var(--text-secondary);font-size:11px;letter-spacing:0.5px;">
                    <i class="fas fa-comment"></i> REMARKS
                </label>
                <textarea name="remarks" id="modalRemarks" placeholder="Enter remarks..." required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeApprovalModal()">CANCEL</button>
                <button type="submit" class="btn-confirm-approve" id="modalConfirmBtn"><i class="fas fa-check"></i> CONFIRM</button>
            </div>
        </form>
    </div>
</div>
