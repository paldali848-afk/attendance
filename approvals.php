<?php
// ============================================
// APPROVAL MANAGEMENT & HISTORY PAGE
// ============================================
require_once __DIR__ . '/includes/modules/config/bootstrap.php';
require_once __DIR__ . '/includes/modules/auth/authentication.php';
require_once __DIR__ . '/includes/modules/approvals/approval_fetch.php';

$user = getCurrentUser();

// 1. FIXED ACCESS CHECK: Include all roles that have subordinates
if (!in_array($user['role'], ['hr', 'process_head', 'centre_head', 'manager', 'am', 'tl'])) {
    header('Location: dashboard.php');
    exit();
}

$tab = $_GET['tab'] ?? 'pending';

// 2. FIXED FETCH LOGIC: Map the tab to the correct data variable
if ($tab === 'pending') {
    $requests = getPendingApprovals($pdo, $user); 
} elseif ($tab === 'approved') {
    $requests = getApprovalHistory($pdo, $user); // Fetches all approved
} elseif ($tab === 'rejected') {
    $requests = getRejectedRequests($pdo, $user); // Fetches all rejected
}

// 3. Include Header (Shows Sidebar)
include __DIR__ . '/includes/header.php';
?>

<div class="main-content">
    <div class="card anim-fade-up">
        <div class="card-header" style="display:flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
            <h3 style="margin:0;"><i class="fas fa-check-double"></i> APPROVAL CENTER</h3>
            <div class="header-actions" style="display:flex; gap: 10px;">
                <a href="?tab=pending" class="header-ijp-btn" style="<?php echo $tab === 'pending' ? 'background:var(--blue);color:#fff;' : 'background:#f1f5f9;color:#64748b;'; ?>">
                   <i class="fas fa-clock"></i> Pending (<?php echo count(getPendingApprovals($pdo, $user)); ?>)
                </a>
                <a href="?tab=approved" class="header-ijp-btn" style="<?php echo $tab === 'approved' ? 'background:var(--green);color:#fff;' : 'background:#f1f5f9;color:#64748b;'; ?>">
                   <i class="fas fa-check-circle"></i> Approved Log
                </a>
                <a href="?tab=rejected" class="header-ijp-btn" style="<?php echo $tab === 'rejected' ? 'background:var(--red);color:#fff;' : 'background:#f1f5f9;color:#64748b;'; ?>">
                   <i class="fas fa-times-circle"></i> Rejected Log
                </a>
            </div>
        </div>

        <div class="table-wrapper" style="padding: 0 10px;">
            <table class="team-table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom: 2px solid var(--border-light);">
                        <th style="padding:15px;">Requester</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <?php if ($tab === 'pending'): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:60px;color:var(--text-muted);">
                                <i class="fas fa-folder-open" style="font-size:32px;display:block;margin-bottom:10px;opacity:0.3;"></i>
                                No <?php echo $tab; ?> requests found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $row): 
                            $type = $row['request_type'] ?? 'exception';
                            $display_date = $row['date'] ?? $row['start_date'] ?? $row['resignation_date'] ?? '--';
                            $badge_class = ($type === 'resignation') ? 'rejected' : (($type === 'leave') ? 'leave' : 'exception-status');
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding:15px;">
                                <strong><?php echo htmlspecialchars($row['requester_name']); ?></strong><br>
                                <small style="color:var(--text-muted);"><?php echo strtoupper($row['requester_role'] ?? 'Agent'); ?></small>
                            </td>
                            <td><span class="status-badge-sm <?php echo $badge_class; ?>"><?php echo strtoupper($type); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($display_date)); ?></td>
                            <td style="max-width:250px; font-size:12px; color:var(--text-secondary);"><?php echo htmlspecialchars($row['reason'] ?? 'N/A'); ?></td>
                            <td><span class="status-badge-sm <?php echo $row['status']; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                            <?php if ($tab === 'pending'): ?>
                            <td>
                                <button class="btn-approve" style="padding:6px 12px; font-size:11px;" 
                                    onclick="openApprovalModal(<?php echo $row['id']; ?>, '<?php echo $type; ?>', 'approved', '<?php echo addslashes($row['requester_name']); ?>', '<?php echo $display_date; ?>', '<?php echo addslashes($row['reason'] ?? ''); ?>')">
                                    PROCESS
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/modules/modals/approval_modal.php'; ?>
<?php include __DIR__ . '/includes/modules/scripts/main_scripts.php'; ?>
</body>
</html>