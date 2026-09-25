<?php
// ============================================
// DROPDOWN BUTTON
// ============================================
?>
<div class="dropdown-container">
    <button class="btn-dropdown" onclick="toggleDropdown()" id="dropdownBtn">
        <span><i class="fas fa-calendar-plus"></i> REQUEST</span>
        <span class="arrow" id="dropdownArrow"><i class="fas fa-chevron-down"></i></span>
    </button>
    <div class="dropdown-menu" id="dropdownMenu">
        <div class="dropdown-item" onclick="openLeaveRequestForm()">
            <i class="fas fa-calendar-alt"></i> Request Leave
        </div>
        <div class="dropdown-item" onclick="openRosterForm()">
            <i class="fas fa-clock"></i> Roster
        </div>
    </div>
</div>