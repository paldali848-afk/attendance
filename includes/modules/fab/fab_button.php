<?php if (hasFullAccess()): ?>

<div class="fab-container">
    <div class="fab-menu" id="fabMenu">
        <div class="fab-title"><i class="fas fa-user-cog"></i> USER MANAGEMENT</div>
        
        <a href="users.php?action=add"><i class="fas fa-user-plus"></i> ADD USER</a>
        <a href="users.php"><i class="fas fa-users"></i> MANAGE USERS</a>
        
        <div class="divider"></div>

        <?php if (canUploadAttendance()): ?>
            <a href="upload_attendance.php"><i class="fas fa-upload"></i> UPLOAD ATTENDANCE</a>
            <a href="upload_pl.php"><i class="fas fa-upload"></i> UPLOAD PL</a>
        <?php endif; ?>

        <a href="user_upload.php"><i class="fas fa-upload"></i> UPLOAD USERS</a>
        <a href="upload_holidays.php"><i class="fas fa-calendar-plus"></i> UPLOAD HOLIDAYS</a>

    </div>
    <button class="fab-button" id="fabButton" onclick="toggleFab()">
        <i class="fas fa-plus"></i>
        <i class="fas fa-times"></i>
    </button>
</div>

<?php endif; ?>