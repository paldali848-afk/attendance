<?php
// ============================================
// HEADER BAR - ALWAYS SHOW LOGGED-IN USER
// ============================================

// Ensure user data exists
if (!isset($user) || !$user) {
    echo '<div class="header-bar anim-fade-up" style="text-align:center;padding:20px;">';
    echo '<p style="color:var(--red);font-weight:700;">Session expired. Please <a href="index.php">login again</a>.</p>';
    echo '</div>';
    return;
}

$p_cnt = $monthly_stats['present_days'] ?? 0;
$a_cnt = $monthly_stats['absent_days'] ?? 0;
$l_cnt = $monthly_stats['late_days'] ?? 0;
$hd_cnt = $monthly_stats['half_day_days'] ?? 0;
$ex_cnt = $monthly_stats['exception_days'] ?? 0;

// Get PL balance
$pl_cnt = 0;
if (isset($user['id'])) {
    $stmt_pl = $pdo->prepare("SELECT pl_balance FROM users WHERE id = ?");
    $stmt_pl->execute([$user['id']]);
    $pl_balance_db = $stmt_pl->fetchColumn();
    $pl_cnt = ($pl_balance_db !== false && $pl_balance_db !== null)
        ? rtrim(rtrim(number_format((float)$pl_balance_db, 2, '.', ''), '0'), '.')
        : 0;
}

// ALWAYS use the logged-in user's name
$full_name = htmlspecialchars($user['full_name'] ?? 'User');
$first_letter = strtoupper(substr($full_name, 0, 1));

// Check if user is viewing someone else's data
$is_viewing_other = isset($selected_user_id) && $selected_user_id != ($user['id'] ?? 0);
?>

<div class="header-bar anim-fade-up">
    <div class="header-left">
        <!-- Avatar as Home Button - takes you back to your own dashboard -->
        <a href="dashboard.php" style="text-decoration:none;cursor:pointer;" title="Go to My Dashboard">
            <div class="avatar"><?php echo $first_letter; ?></div>
        </a>
        <div>
            <div class="greeting">
                <?php if ($is_viewing_other): ?>
                   
                <?php else: ?>
                    ATTENDANCE SUMMARY
                <?php endif; ?>
            </div>
            <!-- ALWAYS show the logged-in user's name -->
            <h1><?php echo $full_name; ?></h1>
            <div class="sub-text">
                <i class="fas fa-calendar-alt"></i> 
                <?php echo date('l, F d, Y'); ?>
                <?php if ($is_viewing_other): ?>
                   
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="header-right">
        <?php if ($is_viewing_other): ?>
            
        <?php endif; ?>
        
        <?php if (!empty($back_user) && !$is_viewing_other): ?>
            <a href="dashboard.php?user_id=<?php echo $back_user['id'] ?? 0; ?>&role=<?php echo $back_user['role'] ?? ''; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> BACK
            </a>
        <?php endif; ?>
        
        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
            <div style="display:flex;align-items:center;gap:3px;background:var(--green-bg);padding:3px 10px;border-radius:50px;border:1px solid rgba(22,163,74,0.15);">
                <span style="font-size:10px;font-weight:700;color:var(--green);">P <?php echo $p_cnt; ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:3px;background:var(--red-bg);padding:3px 10px;border-radius:50px;border:1px solid rgba(220,38,38,0.15);">
                <span style="font-size:10px;font-weight:700;color:var(--red);">A <?php echo $a_cnt; ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:3px;background:var(--yellow-bg);padding:3px 10px;border-radius:50px;border:1px solid rgba(217,119,6,0.15);">
                <span style="font-size:10px;font-weight:700;color:var(--yellow);">L <?php echo $l_cnt; ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:3px;background:var(--exception-bg);padding:3px 10px;border-radius:50px;border:1px solid rgba(139,92,246,0.2);">
                <span style="font-size:10px;font-weight:700;color:var(--exception-color);">EX <?php echo $ex_cnt; ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:3px;background:#fbcfe8;padding:3px 10px;border-radius:50px;border:1px solid rgba(219,39,119,0.15);">
                <span style="font-size:10px;font-weight:700;color:#9d174d;">PL <?php echo $pl_cnt; ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:3px;background:var(--orange-bg);padding:3px 10px;border-radius:50px;border:1px solid rgba(234,88,12,0.15);">
                <span style="font-size:10px;font-weight:700;color:var(--orange);">HD <?php echo $hd_cnt; ?></span>
            </div>
        </div>
    </div>
</div>