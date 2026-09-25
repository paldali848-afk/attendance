<?php
require_once __DIR__ . '/includes/modules/config/bootstrap.php';
require_once __DIR__ . '/includes/modules/auth/authentication.php';
require_once __DIR__ . '/includes/modules/approvals/history_functions.php'; // Aapki file link ho gayi

$user = getCurrentUser();
if (!$user) { header("Location: login.php"); exit(); }

$uid = $user['id'];
$pid = $user['person_id'] ?? '';

// Function call karein
$history = getDetailedHistory($pdo, $uid, $pid);

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="margin-top: 30px;">
    <div class="card shadow-sm" style="background:#fff; border-radius: 12px; overflow:hidden; border: 1px solid #e2e8f0;">
        <div class="card-header" style="background:#fff; padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin:0; font-size: 18px; color: #1e293b;"><i class="fas fa-history"></i> REQUEST HISTORY</h2>
            <span style="font-size: 12px; color: #94a3b8;">Total: <?php echo count($history); ?></span>
        </div>

        <div style="padding: 0;">
            <?php if (empty($history)): ?>
                <div style="text-align:center; padding:60px;">
                    <p style="color:#64748b;">No approved/rejected records found.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:14px;">
                        <thead>
                            <tr style="background:#f8fafc; text-align:left;">
                                <th style="padding:15px; border-bottom:1px solid #e2e8f0;">DATE</th>
                                <th style="padding:15px; border-bottom:1px solid #e2e8f0;">TYPE</th>
                                <th style="padding:15px; border-bottom:1px solid #e2e8f0;">STATUS</th>
                                <th style="padding:15px; border-bottom:1px solid #e2e8f0;">REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): 
                                $status = strtolower(trim($row['final_status']));
                                $color = ($status === 'approved') ? '#16a34a' : '#dc2626';
                                $bg = ($status === 'approved') ? '#f0fdf4' : '#fef2f2';
                            ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:15px;">
                                    <strong><?php echo date('M d, Y', strtotime($row['action_date'])); ?></strong>
                                </td>
                                <td style="padding:15px;">
                                    <span style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-weight:700; font-size:11px;">
                                        <?php echo strtoupper(str_replace('_', ' ', (string)$row['display_type'])); ?>
                                    </span>
                                </td>
                                <td style="padding:15px;">
                                    <span style="background:<?php echo $bg; ?>; color:<?php echo $color; ?>; padding:5px 12px; border-radius:50px; font-weight:800; font-size:11px;">
                                        <?php echo strtoupper($status); ?>
                                    </span>
                                </td>
                                <td style="padding:15px;">
                                    <div style="font-size:13px;">Reason: <?php echo htmlspecialchars($row['reason'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($row['remarks'])): ?>
                                        <div style="font-size:11px; color:#64748b; margin-top:4px;">
                                            <strong><?php echo strtoupper($row['action_by_name'] ?? 'MANAGER'); ?>:</strong> 
                                            <?php echo htmlspecialchars($row['remarks']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

