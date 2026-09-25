<?php
// footer.php - Role-based footer
$current_year = date('Y');
?>
<footer style="
    background: white;
    border-top: 1px solid #e2e8f0;
    padding: 20px 30px;
    margin-top: 40px;
    font-size: 14px;
    color: #666;
">
    <div style="
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    ">
        <div>
            <strong>&copy; <?php echo $current_year; ?> <?php echo SITE_NAME; ?></strong>
            <span style="margin: 0 10px;">|</span>
            <span>Version 1.0</span>
        </div>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <?php if ($user): ?>
                <div>
                    <i class="fas fa-user"></i> 
                    <?php echo htmlspecialchars($user['full_name']); ?>
                    <span style="color:#888;font-size:12px;margin-left:5px;">
                        (<?php echo str_replace('_', ' ', $user['role']); ?>)
                    </span>
                </div>
                <div>
                    <i class="fas fa-building"></i> 
                    <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?>
                </div>
                <div>
                    <i class="fas fa-clock"></i> 
                    <?php echo date('h:i A'); ?>
                </div>
                <div>
                    <i class="fas fa-calendar"></i> 
                    <?php echo date('l, F j, Y'); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="display: flex; gap: 15px;">
            <a href="#" style="color:#667eea;text-decoration:none;">Help</a>
            <a href="#" style="color:#667eea;text-decoration:none;">Support</a>
            <a href="#" style="color:#667eea;text-decoration:none;">Privacy</a>
        </div>
    </div>
</footer>