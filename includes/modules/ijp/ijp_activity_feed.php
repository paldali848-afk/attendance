<?php
// includes/modules/ijp/ijp_activity_feed.php

$viewer_role = $user['role'] ?? 'agent';
$is_management = in_array($viewer_role, ['hr', 'centre_head', 'process_head', 'manager', 'am', 'tl']);

// SQL Logic: 
// 1. Management sees ALL statuses.
// 2. Agents/Staff only see 'Selected', 'Interviewed', or 'Shortlisted'.
$status_filter = $is_management ? "" : "WHERE h.status NOT IN ('rejected', 'on_hold')";

$ijp_query = "
    SELECT 
        h.status, 
        h.created_at, 
        a.full_name as applicant_name, 
        j.title as job_title,
        u.full_name as hr_name
    FROM ijp_status_history h
    JOIN ijp_applications a ON h.application_id = a.id
    JOIN ijp_jobs j ON a.job_id = j.id
    JOIN users u ON h.changed_by = u.id
    $status_filter
    ORDER BY h.created_at DESC 
    LIMIT 5
";

try {
    $ijp_activities = $pdo->query($ijp_query)->fetchAll();
} catch (Exception $e) {
    $ijp_activities = []; // Fail gracefully if tables don't exist yet
}

function getIJPColor($status) {
    switch (strtolower($status)) {
        case 'selected': return '#10b981'; // Green
        case 'rejected': return '#ef4444'; // Red
        case 'interviewed': return '#3b82f6'; // Blue
        case 'shortlisted': return '#f59e0b'; // Amber
        default: return '#64748b';
    }
}
?>

<div class="card anim-fade-up anim-delay-4" style="margin-top: 20px; border-top: 4px solid #f59e0b;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; background: #fff;">
        <h3 class="card-title" style="font-size: 16px; font-weight: 700; color: #1e293b;">
            <i class="fas fa-bullhorn" style="margin-right: 8px; color: #f59e0b;"></i> IJP Updates
        </h3>
        <span class="badge" style="background: #fffbeb; color: #92400e; font-size: 10px; border: 1px solid #fef3c7;">Hiring Feed</span>
    </div>
    
    <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
        <?php if (!empty($ijp_activities)): ?>
            <div class="activity-list">
                <?php foreach ($ijp_activities as $activity): ?>
                    <div class="activity-item" style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 700; color: #334155; font-size: 13px;">
                                <?php echo htmlspecialchars($activity['applicant_name']); ?>
                            </span>
                            <small style="color: #94a3b8; font-size: 10px;">
                                <?php echo date('d M, h:i A', strtotime($activity['created_at'])); ?>
                            </small>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                            Status: <strong style="color: <?php echo getIJPColor($activity['status']); ?>;"><?php echo strtoupper($activity['status']); ?></strong> 
                            for <span style="color: #475569; font-weight: 500;"><?php echo htmlspecialchars($activity['job_title']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="padding: 40px 20px; text-align: center; color: #cbd5e1;">
                <i class="fas fa-newspaper fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                <p style="font-size: 12px;">No hiring updates at the moment</p>
            </div>
        <?php endif; ?>
    </div>
</div>