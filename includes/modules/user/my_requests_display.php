<?php
// ============================================
// MY REQUESTS DISPLAY
// ============================================

// Ensure $my_requests is defined and is an array
if (!isset($my_requests) || !is_array($my_requests)) {
    $my_requests = [];
}
?>

<!-- My Requests Display HTML - ONLY ONE INSTANCE -->
<div class="card anim-fade-up anim-delay-5" style="border-left:4px solid var(--yellow);">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> MY REQUESTS (<?php echo count($my_requests); ?>)</h3>
        <span style="font-size:11px;color:var(--text-muted);font-weight:600;">
            <i class="fas fa-clock"></i> LATEST
        </span>
    </div>

    <?php if (empty($my_requests)): ?>
        <div style="text-align:center;padding:20px 10px;">
            <i class="fas fa-inbox" style="font-size:28px;color:var(--text-muted);opacity:0.2;"></i>
            <p style="color:var(--text-muted);margin-top:6px;font-weight:700;font-size:14px;">NO REQUESTS</p>
            <p style="color:var(--text-muted);font-size:12px;font-weight:600;">You haven't submitted any requests.</p>
        </div>
    <?php else: ?>
        <div class="my-request-list" style="overflow-x: hidden;">
            <?php 
            $display_requests = array_slice($my_requests, 0, 5);
            foreach ($display_requests as $req):
                if (isset($req['request_type']) && $req['request_type'] === 'overtime') {
    $req_type_display = 'Overtime (' . ($req['ot_hours'] ?? 0) . 'h)';
} else {
    $req_type_display = isset($req['request_type']) ? ucfirst(str_replace('_', ' ', $req['request_type'])) : 'Request';
}
                $is_roster = isset($req['start_time']) && isset($req['end_time']);
                
                // Determine date display
                $date_display = '--';
                if (!empty($req['date'])) {
                    $date_display = date('M d, Y', strtotime($req['date']));
                }
                if (!empty($req['start_date']) && !empty($req['end_date']) && $req['start_date'] != $req['end_date']) {
                    $date_display = date('M d', strtotime($req['start_date'])) . ' - ' . date('M d, Y', strtotime($req['end_date']));
                }
                
                $time_info = '';
                if ($is_roster && isset($req['start_time']) && isset($req['end_time'])) {
                    $time_info = ' | ' . $req['start_time'] . ' to ' . $req['end_time'] . ' (' . ($req['working_hours'] ?? 9) . ' hrs)';
                }
                
                // Determine status class
                $status_class = strtolower($req['status'] ?? 'pending');
            ?>
                <div class="my-request-item <?php echo $status_class; ?>">
                    <div class="info">
                        <div class="date">
                            <?php echo $date_display; ?>
                            <?php if ($is_roster): ?>
                                <span style="font-size:10px;color:var(--blue);font-weight:600;"><?php echo $time_info; ?></span>
                            <?php endif; ?>
                            <span class="status-badge-sm <?php echo $status_class; ?>">
                                <?php echo strtoupper($req['status'] ?? 'PENDING'); ?>
                            </span>
                            <?php if (!$is_roster): ?>
                                <span style="font-size:9px;color:var(--text-muted);font-weight:600;margin-left:4px;">
                                    <?php echo $req_type_display; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="reason"><i class="fas fa-comment"></i> <?php echo htmlspecialchars($req['reason'] ?? 'No reason provided'); ?></div>
                        <?php if (($req['status'] ?? '') !== 'pending' && !empty($req['remarks'])): ?>
                            <div class="remark"><i class="fas fa-reply"></i> <?php echo htmlspecialchars($req['remarks']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="status-label <?php echo $status_class; ?>">
                        <?php if ($status_class === 'pending'): ?>
                            <i class="fas fa-clock"></i> AWAITING
                        <?php elseif ($status_class === 'approved'): ?>
                            <i class="fas fa-check-circle"></i> APPROVED
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i> REJECTED
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($my_requests) > 5): ?>
                <div style="text-align:center;padding:6px 0;font-size:11px;color:var(--text-muted);font-weight:600;">
                    <i class="fas fa-ellipsis-h"></i> +<?php echo count($my_requests) - 5; ?> more
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>