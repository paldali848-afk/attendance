<style>
    /* ============================================
   MAIN STYLES - All CSS from original file
   ============================================ */

    :root {
        --white: #ffffff;
        --bg-light: #f4f6fa;
        --bg-card: #ffffff;
        --text-primary: #0a1628;
        --text-secondary: #1e293b;
        --text-muted: #64748b;
        --border-light: #e8edf4;
        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
        --shadow-md: 0 8px 28px rgba(0, 0, 0, 0.08);
        --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.06);
        --radius: 16px;
        --radius-sm: 10px;
        --blue: #2563eb;
        --blue-hover: #1d4ed8;
        --blue-light: #dbeafe;
        --blue-bg: rgba(37, 99, 235, 0.06);
        --green: #16a34a;
        --green-bg: rgba(22, 163, 74, 0.06);
        --red: #dc2626;
        --red-bg: rgba(220, 38, 38, 0.06);
        --yellow: #d97706;
        --yellow-bg: rgba(217, 119, 6, 0.06);
        --purple: #7c3aed;
        --purple-bg: rgba(124, 58, 237, 0.06);
        --cyan: #0891b2;
        --cyan-bg: rgba(8, 145, 178, 0.06);
        --orange: #ea580c;
        --orange-bg: rgba(234, 88, 12, 0.06);
        --pink: #db2777;
        --pink-bg: rgba(219, 39, 119, 0.06);
        --exception-color: #8b5cf6;
        --exception-bg: rgba(139, 92, 246, 0.12);
    }

    /* ===== BASE STYLES ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f0f2f6;
        /* padding-top: 70px; */
        color: var(--text-primary);
        min-height: 100vh;
position: relative;

    }

    .container {
        max-width: 1440px;
        margin: 20px auto;
        padding: 0 24px;
        position: relative;
        z-index: 1;
    }

    /* ===== ANIMATIONS ===== */
    @keyframes fadeUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .anim-fade-up {
        animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    .anim-delay-1 {
        animation-delay: 0.05s;
    }

    .anim-delay-2 {
        animation-delay: 0.1s;
    }

    .anim-delay-3 {
        animation-delay: 0.15s;
    }

    .anim-delay-4 {
        animation-delay: 0.2s;
    }

    .anim-delay-5 {
        animation-delay: 0.25s;
    }

    /* ===== CARDS & HEADER ===== */
    .card {
        background: var(--white);
        border-radius: var(--radius);
        padding: 22px 26px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-light);
    }

    .card-header h3 {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: 0.3px;
    }

    .card-header h3 i {
        color: var(--blue);
        font-size: 16px;
    }

    /* ===== HEADER BAR ===== */
    .header-bar {
        background: var(--white);
        border-radius: var(--radius);
        padding: 20px 28px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .header-bar:hover {
        box-shadow: var(--shadow-md);
    }

    .header-bar .header-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .header-bar .avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--blue), var(--purple));
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        color: #fff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .header-bar .greeting {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .header-bar h1 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .header-bar .sub-text {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-muted);
    }

    .header-bar .sub-text i {
        color: var(--blue);
        margin-right: 6px;
    }

    /* ===== DASHBOARD GRID ===== */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 0.7fr 1.3fr;
        gap: 20px;
        margin-bottom: 24px;
        align-items: start;
    }

    .dashboard-grid>div:first-child {
        position: relative;
        z-index: 10;
    }

    .right-panel {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .dashboard-grid.full-width {
        grid-template-columns: 1fr;
    }

    /* ===== CALENDAR ===== */
    .calendar-grid {
        border-radius: var(--radius-sm);
        overflow: visible !important;
    }

    .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: var(--bg-light);
        text-align: center;
        padding: 8px 0;
        font-weight: 700;
        font-size: 11px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-light);
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        overflow: visible !important;
    }

    .calendar-day {
        min-height: 44px;
        border: 1px solid var(--border-light);
        padding: 3px;
        transition: all 0.2s ease;
        position: relative;
        background: var(--white);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: visible !important;
        cursor: default;
    }

    .calendar-day:hover {
        background: var(--blue-bg);
        transform: scale(1.02);
        z-index: 2;
        border-color: var(--blue);
    }

    .calendar-day.empty {
        background: var(--bg-light);
        cursor: default;
    }

    .calendar-day.empty:hover {
        transform: none;
    }

    /* Calendar statuses */
    .calendar-day.status-present {
        background: var(--green-bg) !important;
        border-color: rgba(22, 163, 74, 0.2) !important;
    }

    .calendar-day.status-present .day-number {
        color: var(--green);
        font-weight: 700;
    }

    .calendar-day.status-late {
        background: var(--yellow-bg) !important;
        border-color: rgba(217, 119, 6, 0.2) !important;
    }

    .calendar-day.status-late .day-number {
        color: var(--yellow);
        font-weight: 700;
    }

    .calendar-day.status-absent {
        background: var(--red-bg) !important;
        border-color: rgba(220, 38, 38, 0.2) !important;
    }

    .calendar-day.status-absent .day-number {
        color: var(--red);
        font-weight: 700;
    }

    .calendar-day.status-weekoff {
        background: var(--cyan-bg) !important;
        border-color: rgba(8, 145, 178, 0.2) !important;
    }

    .calendar-day.status-weekoff .day-number {
        color: var(--cyan);
        font-weight: 700;
    }

    .calendar-day.status-holiday {
        background: var(--purple-bg) !important;
        border-color: rgba(124, 58, 237, 0.2) !important;
    }

    .calendar-day.status-holiday .day-number {
        color: var(--purple);
        font-weight: 700;
    }

    .calendar-day.status-leave {
        background: var(--pink-bg) !important;
        border-color: rgba(219, 39, 119, 0.2) !important;
        cursor: pointer !important;
        pointer-events: auto !important;
    }

    .calendar-day.status-leave:hover {
        background-color: #f0f7ff;
        /* Hover effect taaki pata chale clickable hai */
        border: 1px solid #2563eb;
    }

    .calendar-day.status-leave .day-number {
        color: var(--pink);
        font-weight: 700;
    }

    .calendar-day.status-half-day {
        background: var(--orange-bg) !important;
        border-color: rgba(234, 88, 12, 0.2) !important;
    }

    .calendar-day.status-half-day .day-number {
        color: var(--orange);
        font-weight: 700;
    }

    .calendar-day.status-coming-late {
        background: var(--cyan-bg) !important;
        border-color: rgba(8, 145, 178, 0.2) !important;
    }

    .calendar-day.status-coming-late .day-number {
        color: var(--cyan);
        font-weight: 700;
    }

    .calendar-day.status-no-data {
        background: var(--bg-light) !important;
    }

    .calendar-day.weekend {
        background: var(--bg-light) !important;
    }

    .day-number {
        font-weight: 600;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .day-number.today {
        background: var(--blue);
        color: #fff !important;
        padding: 0 6px;
        border-radius: 4px;
        font-weight: 700;
    }

    .status-code {
        display: block;
        font-size: 8px;
        font-weight: 700;
        margin-top: 1px;
        letter-spacing: 0.5px;
    }

    .status-present .status-code {
        color: var(--green);
    }

    .status-late .status-code {
        color: var(--yellow);
    }

    .status-absent .status-code {
        color: var(--red);
    }

    .status-weekoff .status-code {
        color: var(--cyan);
    }

    .status-holiday .status-code {
        color: var(--purple);
    }

    .status-leave .status-code {
        color: var(--pink);
    }

    .status-half-day .status-code {
        color: var(--orange);
    }

    .status-coming-late .status-code {
        color: var(--cyan);
    }

    .status-no-data .status-code {
        color: var(--text-muted);
    }

    /* ===== CALENDAR TOOLTIP ===== */
    .calendar-day .tooltip {
        visibility: hidden;
        opacity: 0;
        width: 220px;
        background: var(--text-primary);
        color: var(--white);
        text-align: left;
        border-radius: var(--radius-sm);
        padding: 12px 16px;
        position: absolute;
        z-index: 1000;
        bottom: calc(100% + 10px);
        left: 50%;
        transform: translateX(-50%);
        transition: all 0.3s ease;
        font-size: 12px;
        font-weight: 500;
        box-shadow: var(--shadow-lg);
        pointer-events: none;
        line-height: 1.5;
    }

    .calendar-day .tooltip::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -8px;
        border-width: 8px;
        border-style: solid;
        border-color: var(--text-primary) transparent transparent transparent;
    }

    .calendar-day:hover .tooltip {
        visibility: visible;
        opacity: 1;
    }

    .calendar-day .tooltip .tooltip-date {
        font-weight: 700;
        font-size: 13px;
        color: rgba(255, 255, 255, 0.95);
        padding-bottom: 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 6px;
        text-align: center;
    }

    .calendar-day .tooltip .time-row {
        display: flex;
        justify-content: space-between;
        padding: 3px 0;
        font-size: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .calendar-day .tooltip .time-row:last-child {
        border-bottom: none;
    }

    .calendar-day .tooltip .label {
        color: rgba(255, 255, 255, 0.7);
        font-weight: 500;
        margin-right: 16px;
    }

    .calendar-day .tooltip .value {
        color: var(--white);
        font-weight: 700;
    }

    .calendar-day .tooltip .status-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 700;
        margin-top: 4px;
        text-align: center;
        width: 100%;
    }

    .calendar-day .tooltip .status-badge.present {
        background: var(--green);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.late {
        background: var(--yellow);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.absent {
        background: var(--red);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.weekoff {
        background: var(--cyan);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.holiday {
        background: var(--purple);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.leave {
        background: var(--pink);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.half-day {
        background: var(--orange);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.coming-late {
        background: var(--cyan);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.exception {
        background: var(--exception-color);
        color: #fff;
    }

    .calendar-day .tooltip .status-badge.no-data {
        background: var(--text-muted);
        color: #fff;
    }

    /* ===== LEGEND ===== */
    .legend {
        display: flex;
        gap: 12px;
        margin-top: 12px;
        padding: 10px 16px;
        background: var(--bg-light);
        border-radius: var(--radius-sm);
        flex-wrap: wrap;
        border: 1px solid var(--border-light);
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .legend-color {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        border: 1px solid var(--border-light);
    }

    .legend-color.present {
        background: var(--green);
        opacity: 0.6;
    }

    .legend-color.late {
        background: var(--yellow);
        opacity: 0.6;
    }

    .legend-color.absent {
        background: var(--red);
        opacity: 0.6;
    }

    .legend-color.weekoff {
        background: var(--cyan);
        opacity: 0.6;
    }

    .legend-color.holiday {
        background: var(--purple);
        opacity: 0.6;
    }

    .legend-color.leave {
        background: var(--pink);
        opacity: 0.6;
    }

    .legend-color.half-day {
        background: var(--orange);
        opacity: 0.6;
    }

    .legend-color.coming-late {
        background: var(--cyan);
        opacity: 0.6;
    }

    .legend-color.exception {
        background: var(--exception-color);
        opacity: 0.6;
    }

    .legend-color.no-data {
        background: var(--text-muted);
        opacity: 0.3;
    }

    /* ===== MONTH NAV ===== */
    .month-nav {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .month-nav a {
        background: var(--bg-light);
        color: var(--text-secondary);
        padding: 4px 12px;
        border-radius: 50px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: 0.3s;
        border: 1px solid var(--border-light);
    }

    .month-nav a:hover {
        background: var(--blue-light);
        border-color: var(--blue);
        color: var(--blue);
    }

    .month-nav span {
        font-weight: 700;
        font-size: 14px;
        min-width: 80px;
        text-align: center;
        color: var(--text-primary);
    }

    /* ===== MESSAGES ===== */
    .message {
        padding: 12px 20px;
        border-radius: var(--radius-sm);
        margin-bottom: 16px;
        font-weight: 700;
        font-size: 13px;
        border-left: 4px solid;
    }

    .message-success {
        background: var(--green-bg);
        color: var(--green);
        border-color: var(--green);
    }

    .message-error {
        background: var(--red-bg);
        color: var(--red);
        border-color: var(--red);
    }

    /* ===== DROPDOWN ===== */
    .dropdown-container {
        position: relative;
        display: inline-block;
        margin-top: 12px;
        width: 100%;
        z-index: 999;
    }

    .btn-dropdown {
        background: linear-gradient(135deg, var(--green), #22c55e);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        transition: 0.3s;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 10px rgba(22, 163, 74, 0.2);
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .btn-dropdown:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
    }

    .btn-dropdown .arrow {
        transition: transform 0.3s ease;
    }

    .btn-dropdown .arrow.open {
        transform: rotate(180deg);
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--white);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-md);
        z-index: 9999 !important;
        overflow: hidden;
        margin-top: 0;
    }

    .dropdown-menu.show {
        display: block;
    }

    .dropdown-menu .dropdown-item {
        padding: 12px 16px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        transition: 0.3s;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dropdown-menu .dropdown-item:last-child {
        border-bottom: none;
    }

    .dropdown-menu .dropdown-item:hover {
        background: var(--blue-bg);
        color: var(--blue);
    }

    .dropdown-menu .dropdown-item i {
        width: 20px;
        color: var(--blue);
    }

    /* ===== REQUEST FORMS ===== */
    .request-form {
        margin-top: 16px;
        padding: 16px;
        background: var(--bg-light);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-light);
        display: none;
    }

    .request-form.show {
        display: block;
    }

    .request-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 12px;
    }

    .request-form .form-group {
        display: flex;
        flex-direction: column;
    }

    .request-form .form-group label {
        font-size: 11px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .request-form .form-group input,
    .request-form .form-group select,
    .request-form .form-group textarea {
        padding: 8px 12px;
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-primary);
        background: var(--white);
        transition: 0.3s;
    }

    .request-form .form-group input:focus,
    .request-form .form-group select:focus,
    .request-form .form-group textarea:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .request-form .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }

    .request-form .form-group-full {
        grid-column: 1 / -1;
    }

    .request-form .form-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin-top: 8px;
    }

    .request-type-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-size: 14px;
        transition: 0.3s;
        background: var(--white);
        color: var(--text-primary);
        font-family: 'Inter', sans-serif;
        font-weight: 600;
    }

    .request-type-select:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .btn-submit-request {
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border: none;
        padding: 8px 24px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        transition: 0.3s;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
    }

    .btn-submit-request:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
    }

    .btn-cancel-request {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
        padding: 8px 24px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        transition: 0.3s;
    }

    .btn-cancel-request:hover {
        background: var(--border-light);
    }

    /* ===== TIME DISPLAY ===== */
    .time-display {
        background: var(--white);
        padding: 10px 14px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-light);
        margin-top: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .time-display .label {
        color: var(--text-muted);
        font-weight: 500;
    }

    .time-display .value {
        color: var(--blue);
        font-weight: 700;
    }

    /* ===== APPROVAL ITEMS ===== */
    .approval-item {
        padding: 10px 14px;
        background: var(--bg-light);
        border-radius: var(--radius-sm);
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        border-left: 4px solid var(--yellow);
    }

    .approval-item:hover {
        background: var(--blue-bg);
        transform: translateX(4px);
    }

    .approval-item .info .date-text {
        font-weight: 700;
        font-size: 14px;
        color: var(--text-primary);
    }

    .approval-item .info .reason-text {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .approval-actions {
        display: flex;
        gap: 6px;
    }

    .btn-approve {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
        padding: 4px 14px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 10px;
        font-weight: 700;
        transition: 0.3s;
        letter-spacing: 0.5px;
    }

    .btn-approve:hover {
        background: var(--green);
        color: #fff;
    }

    .btn-reject {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.2);
        padding: 4px 14px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 10px;
        font-weight: 700;
        transition: 0.3s;
        letter-spacing: 0.5px;
    }

    .btn-reject:hover {
        background: var(--red);
        color: #fff;
    }

    /* ===== STATUS BADGES ===== */
    .status-badge-sm {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge-sm.pending {
        background: var(--yellow-bg);
        color: var(--yellow);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    .status-badge-sm.approved {
        background: var(--green-bg);
        color: var(--green);
        border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .status-badge-sm.rejected {
        background: var(--red-bg);
        color: var(--red);
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .role-badge-sm {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 50px;
        font-size: 9px;
        font-weight: 700;
        margin-left: 6px;
        letter-spacing: 0.5px;
        border: 1px solid var(--border-light);
    }

    .role-badge-sm.process_head {
        background: var(--cyan-bg);
        color: var(--cyan);
        border-color: rgba(8, 145, 178, 0.2);
    }

    .role-badge-sm.centre_head {
        background: var(--blue-bg);
        color: var(--blue);
        border-color: rgba(37, 99, 235, 0.2);
    }

    .role-badge-sm.manager {
        background: var(--green-bg);
        color: var(--green);
        border-color: rgba(22, 163, 74, 0.2);
    }

    .role-badge-sm.am {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: rgba(217, 119, 6, 0.2);
    }

    .role-badge-sm.tl {
        background: var(--red-bg);
        color: var(--red);
        border-color: rgba(220, 38, 38, 0.2);
    }

    .role-badge-sm.agent {
        background: var(--bg-light);
        color: var(--text-muted);
        border-color: var(--border-light);
    }

    /* ===== MY REQUESTS ===== */
    .my-request-item {
        padding: 10px 14px;
        background: var(--bg-light);
        border-radius: var(--radius-sm);
        margin-bottom: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 4px solid var(--border-light);
        transition: all 0.3s ease;
    }

    .my-request-item:hover {
        background: var(--blue-bg);
        transform: translateX(4px);
    }

    .my-request-item.approved {
        border-left-color: var(--green);
    }

    .my-request-item.rejected {
        border-left-color: var(--red);
    }

    .my-request-item.pending {
        border-left-color: var(--yellow);
    }

    .my-request-item .info .date {
        font-weight: 700;
        font-size: 13px;
        color: var(--text-primary);
    }

    .my-request-item .info .reason {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .my-request-item .info .remark {
        font-size: 11px;
        font-weight: 500;
        color: var(--text-muted);
        font-style: italic;
    }

    .my-request-item .status-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .my-request-item .status-label.approved {
        color: var(--green);
    }

    .my-request-item .status-label.rejected {
        color: var(--red);
    }

    .my-request-item .status-label.pending {
        color: var(--yellow);
    }

    .my-request-list {
        max-height: 150px;
        overflow-y: auto;
    }

    .my-request-list::-webkit-scrollbar {
        width: 4px;
    }

    .my-request-list::-webkit-scrollbar-track {
        background: var(--bg-light);
        border-radius: 4px;
    }

    .my-request-list::-webkit-scrollbar-thumb {
        background: var(--blue);
        border-radius: 4px;
    }

    /* ===== TEAM TABLE ===== */
    .team-table-wrapper {
        overflow-x: auto;
        overflow-y: visible !important;
        position: relative;
        padding-top: 10px;
        margin-top: 0;
    }

    .team-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
        min-width: 800px;
        position: relative;
    }

    .team-table th {
        background: var(--bg-light);
        color: var(--text-muted);
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 6px;
        border-bottom: 2px solid var(--border-light);
        text-align: center;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .team-table td {
        padding: 6px 4px;
        border-bottom: 1px solid var(--border-light);
        text-align: center;
        vertical-align: middle;
        font-size: 12px;
        position: relative;
    }

    .team-table td.day-cell {
    position: relative; /* CRITICAL for hover positioning */
    cursor: pointer;
}

    .team-table .name-cell {
        text-align: left;
        font-weight: 700;
        font-size: 14px;
        color: var(--text-primary);
        padding-left: 12px;
        white-space: nowrap;
        min-width: 150px;
    }

    .team-table .row-clickable {
        cursor: pointer;
        transition: background 0.2s;
    }

    .team-table .row-clickable:hover {
        background: var(--blue-bg);
    }

    .team-table .row-static {
        cursor: default;
        transition: background 0.2s;
    }

    .team-table .row-static:hover {
        background: var(--bg-light);
    }

    .team-table .day-dot {
        display: inline-block;
        width: 26px;
        height: 26px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        line-height: 26px;
        text-align: center;
        border: 1px solid var(--border-light);
        transition: all 0.2s;
    }

    .team-table .day-dot:hover {
        transform: scale(1.2);
        z-index: 2;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .team-table .day-dot.present {
        background: var(--green-bg);
        color: var(--green);
        border-color: rgba(22, 163, 74, 0.2);
    }

    .team-table .day-dot.late {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: rgba(217, 119, 6, 0.2);
    }

    .team-table .day-dot.absent {
        background: var(--red-bg);
        color: var(--red);
        border-color: rgba(220, 38, 38, 0.2);
    }

    .team-table .day-dot.weekoff {
        background: var(--cyan-bg);
        color: var(--cyan);
        border-color: rgba(8, 145, 178, 0.2);
    }

    .team-table .day-dot.holiday {
        background: var(--yellow-bg);
        color: var(--yellow);
        border-color: rgba(124, 58, 237, 0.2);
    }

    .team-table .day-dot.leave {
    background: rgb(39 51 219 / 6%);
    color: #001299;
    border-color: rgb(59 69 227 / 54%);
    }

    .team-table .day-dot.half-day {
        background: var(--orange-bg);
        color: var(--orange);
        border-color: rgba(234, 88, 12, 0.2);
    }

    .team-table .day-dot.coming-late {
        background: var(--cyan-bg);
        color: var(--cyan);
        border-color: rgba(8, 145, 178, 0.2);
    }

    .team-table .day-dot.exception {
        background: var(--exception-bg);
        color: var(--exception-color);
        border-color: rgba(139, 92, 246, 0.25);
    }

    .team-table .day-dot.no-data {
        background: var(--bg-light);
        color: var(--text-muted);
        border-color: var(--border-light);
    }

    .team-table .stat-cell {
        font-weight: 800;
        font-size: 14px;
    }

    .team-table .stat-cell.present {
        color: var(--green);
    }

    .team-table .stat-cell.absent {
        color: var(--red);
    }

    .team-table .stat-cell.late {
        color: var(--yellow);
    }

    .team-table .stat-cell.exception {
        color: var(--exception-color);
    }

    /* ===== TEAM TABLE TOOLTIP ===== */
    .team-table .day-cell .tooltip {
        visibility: hidden;
        opacity: 0;
        width: 200px;
        background: var(--text-primary);
        color: var(--white);
        text-align: left;
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        position: absolute;
        z-index: 9999 !important;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%);
        transition: all 0.2s ease;
        font-size: 11px;
        font-weight: 500;
        box-shadow: var(--shadow-lg);
        pointer-events: none;
        line-height: 1.5;
    }

    .team-table .day-cell .tooltip::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -6px;
        border-width: 6px;
        border-style: solid;
        border-color: var(--text-primary) transparent transparent transparent;
    }

    .team-table .day-cell:hover .tooltip {
        visibility: visible;
        opacity: 1;
    }

    .team-table .day-cell .tooltip .tooltip-date {
        font-weight: 700;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.95);
        padding-bottom: 4px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 4px;
        text-align: center;
    }

    .team-table .day-cell .tooltip .time-row {
        display: flex;
        justify-content: space-between;
        padding: 2px 0;
        font-size: 11px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .team-table .day-cell .tooltip .time-row:last-child {
        border-bottom: none;
    }

    .team-table .day-cell .tooltip .label {
        color: rgba(255, 255, 255, 0.6);
        font-weight: 500;
        margin-right: 12px;
    }

    .team-table .day-cell .tooltip .value {
        color: var(--white);
        font-weight: 700;
    }

    .team-table .day-cell .tooltip .status-badge {
        display: inline-block;
        padding: 1px 10px;
        border-radius: 10px;
        font-size: 9px;
        font-weight: 700;
        margin-top: 3px;
        text-align: center;
        width: 100%;
    }

    .team-table .day-cell .tooltip .status-badge.present {
        background: var(--green);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.late {
        background: var(--yellow);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.absent {
        background: var(--red);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.weekoff {
        background: var(--cyan);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.holiday {
        background: var(--purple);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.leave {
        background: var(--pink);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.half-day {
        background: var(--orange);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.exception {
        background: var(--exception-color);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.no-data {
        background: var(--text-muted);
        color: #fff;
    }

    .team-table .day-cell .tooltip .status-badge.coming-late {
        background: var(--cyan);
        color: #fff;
    }

    /* ===== MODAL ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(8px);
    }

    .modal-box {
        background: var(--white);
        padding: 28px 32px;
        border-radius: var(--radius);
        width: 440px;
        max-width: 95%;
        box-shadow: var(--shadow-lg);
        animation: fadeUp 0.4s ease;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        width: 400px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .modal-box .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--border-light);
    }

    .modal-box .modal-header h3 {
        font-size: 17px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-box .modal-header .close-btn {
        background: var(--bg-light);
        border: 1px solid var(--border-light);
        font-size: 18px;
        cursor: pointer;
        color: var(--text-secondary);
        transition: 0.3s;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-box .modal-header .close-btn:hover {
        background: var(--red-bg);
        color: var(--red);
        border-color: var(--red);
    }

    .modal-box .request-info {
        background: var(--bg-light);
        padding: 12px 16px;
        border-radius: var(--radius-sm);
        margin-bottom: 14px;
        border: 1px solid var(--border-light);
    }

    .modal-box .request-info p {
        margin: 4px 0;
        font-size: 13px;
    }

    .modal-box .request-info .label {
        font-weight: 700;
        color: var(--text-muted);
        font-size: 11px;
        letter-spacing: 0.5px;
    }

    .modal-box textarea {
        width: 100%;
        height: 80px;
        padding: 10px 14px;
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 13px;
        resize: vertical;
        transition: 0.3s;
        background: var(--white);
        color: var(--text-primary);
        font-weight: 500;
    }

    .modal-box textarea:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .modal-box .modal-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
        justify-content: flex-end;
    }

    .modal-box .modal-actions button {
        padding: 8px 20px;
        border: 1px solid var(--border-light);
        border-radius: 50px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        transition: 0.3s;
        letter-spacing: 0.5px;
    }

    .btn-confirm-approve {
        background: var(--green);
        color: #fff;
        border: none !important;
        box-shadow: 0 2px 10px rgba(22, 163, 74, 0.2);
    }

    .btn-confirm-approve:hover {
        background: #15803d;
        transform: translateY(-2px);
    }

    .btn-confirm-reject {
        background: var(--red);
        color: #fff;
        border: none !important;
        box-shadow: 0 2px 10px rgba(220, 38, 38, 0.2);
    }

    .btn-confirm-reject:hover {
        background: #b91c1c;
        transform: translateY(-2px);
    }

    .btn-cancel-modal {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
    }

    .btn-cancel-modal:hover {
        background: var(--border-light);
    }

    /* ===== FAB ===== */
    .fab-container {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }

    .fab-menu {
        display: none;
        flex-direction: column;
        gap: 4px;
        background: var(--white);
        border-radius: var(--radius-sm);
        padding: 8px;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-md);
        min-width: 190px;
        animation: fadeUp 0.3s ease;
    }

    .fab-menu.open {
        display: flex;
    }

    .fab-menu .fab-title {
        padding: 6px 14px;
        font-weight: 700;
        font-size: 11px;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border-light);
        margin-bottom: 4px;
        letter-spacing: 0.5px;
    }

    .fab-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        color: var(--text-secondary);
        text-decoration: none;
        border-radius: var(--radius-sm);
        transition: 0.3s;
        font-size: 12px;
        font-weight: 600;
    }

    .fab-menu a:hover {
        background: var(--blue-bg);
        color: var(--blue);
    }

    .fab-menu a i {
        width: 18px;
        color: var(--blue);
        font-size: 14px;
    }

    .fab-menu .divider {
        border-top: 1px solid var(--border-light);
        margin: 4px 0;
    }

    .fab-button {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--blue), var(--purple));
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 24px;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .fab-button:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 24px rgba(37, 99, 235, 0.4);
    }

    .fab-button .fa-times {
        display: none;
    }

    .fab-button.open .fa-plus {
        display: none;
    }

    .fab-button.open .fa-times {
        display: inline-block;
    }

    .fab-button.open {
        transform: rotate(90deg);
    }

    /* ===== HIERARCHY INFO ===== */
    .hierarchy-info {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        padding: 8px 12px;
        background: var(--bg-light);
        border-radius: var(--radius-sm);
        margin-bottom: 12px;
        border: 1px solid var(--border-light);
    }

    .hierarchy-info i {
        color: var(--blue);
        margin-right: 6px;
    }

    .hierarchy-info strong {
        color: var(--text-primary);
        font-weight: 700;
    }

    /* ===== BACK BUTTON ===== */
    .header-bar .back-btn {
        background: var(--bg-light);
        color: var(--text-secondary);
        border: 1px solid var(--border-light);
        padding: 4px 14px;
        border-radius: 50px;
        cursor: pointer;
        font-size: 11px;
        font-weight: 600;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .header-bar .back-btn:hover {
        background: var(--blue-light);
        border-color: var(--blue);
        color: var(--blue);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .right-panel {
            flex-direction: row;
        }

        .right-panel .card {
            flex: 1;
        }
    }

    @media (max-width: 768px) {
    /* 1. Reduce tooltip width for mobile screens */
    .calendar-day .tooltip {
        width: 180px !important;
        padding: 10px 12px !important;
        font-size: 11px !important;
    }

    /* 2. Prevent clipping on the right edge (Friday and Saturday columns) */
    .calendar-days .calendar-day:nth-child(7n),
    .calendar-days .calendar-day:nth-child(7n-1) {
        position: relative;
    }
    
    .calendar-days .calendar-day:nth-child(7n) .tooltip,
    .calendar-days .calendar-day:nth-child(7n-1) .tooltip {
        left: auto !important;
        right: 0 !important;
        transform: translateX(0) !important;
    }

    /* Shift the tooltip triangle/arrow so it still points to the day on the right edge */
    .calendar-days .calendar-day:nth-child(7n) .tooltip::after,
    .calendar-days .calendar-day:nth-child(7n-1) .tooltip::after {
        left: auto !important;
        right: 15px !important;
        margin-left: 0 !important;
    }

    /* 3. Prevent clipping on the left edge (Sunday and Monday columns) */
    .calendar-days .calendar-day:nth-child(7n+1) .tooltip,
    .calendar-days .calendar-day:nth-child(7n+2) .tooltip {
        left: 0 !important;
        transform: translateX(0) !important;
    }

    .calendar-days .calendar-day:nth-child(7n+1) .tooltip::after,
    .calendar-days .calendar-day:nth-child(7n+2) .tooltip::after {
        left: 15px !important;
        margin-left: 0 !important;
    }
}
.calendar-day .tooltip .time-row .value {
    white-space: nowrap; /* Prevents the time from breaking into two lines */
    text-align: right;
    flex-shrink: 0;
}

.calendar-day .tooltip .time-row {
    display: flex;
    justify-content: space-between;
    gap: 10px; /* Ensures a gap between label and time */
}

    @media (max-width: 768px) {
        .container {
            padding: 0 16px;
margin: 10px auto;
       overflow-x: visible !important; 
        }

        .header-bar {
            flex-direction: column;
            text-align: center;
            gap: 12px;
            padding: 16px 20px;
        }

        .header-bar .header-left {
            flex-direction: column;
        }

        .header-bar .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }

        .calendar-day {
            min-height: 38px;
            padding: 2px;
        }

        .day-number {
            font-size: 12px;
        }

        .status-code {
            font-size: 8px;
        }

        .right-panel {
            flex-direction: column;
        }

        .team-table {
            font-size: 12px;
            min-width: 600px;
        }

        .team-table .day-dot {
            width: 22px;
            height: 22px;
            font-size: 8px;
            line-height: 22px;
        }

        .team-table .name-cell {
            font-size: 13px;
            min-width: 120px;
        }

        .request-form .form-row {
            grid-template-columns: 1fr;
        }

        .btn-dropdown {
            font-size: 12px;
            padding: 8px 16px;
        }

        .time-display {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 480px) {
        .calendar-day {
            min-height: 32px;
        }

        .calendar-weekdays {
            font-size: 9px;
            padding: 4px 0;
        }

        .modal-box {
            padding: 20px;
        }

        .card-header h3 {
            font-size: 13px;
        }

        .card {
            padding: 14px;
        }
    }

    /* ===== EXCEPTION STATUSES ===== */
    .calendar-day.status-exception {
        background: var(--exception-bg) !important;
        border-color: rgba(139, 92, 246, 0.3) !important;
    }

    .calendar-day.status-exception .day-number {
        color: var(--exception-color);
        font-weight: 700;
    }

    .calendar-day.status-exception .status-code {
        color: var(--exception-color);
        font-weight: 800;
    }

    .calendar-day.status-exception-full-day {
        background: rgba(124, 58, 237, 0.12) !important;
        border-color: rgba(124, 58, 237, 0.25) !important;
    }

    .calendar-day.status-exception-half-day {
        background: rgba(245, 158, 11, 0.12) !important;
        border-color: rgba(245, 158, 11, 0.25) !important;
    }

    .calendar-day.status-exception-late {
        background: rgba(239, 68, 68, 0.12) !important;
        border-color: rgba(239, 68, 68, 0.25) !important;
    }

    .calendar-day.status-exception-pl {
        background: rgba(236, 72, 153, 0.12) !important;
        border-color: rgba(236, 72, 153, 0.25) !important;
    }

    .calendar-day.status-exception-full-day .day-number {
        color: #7c3aed;
        font-weight: 700;
    }

    .calendar-day.status-exception-full-day .status-code {
        color: #7c3aed;
        font-weight: 800;
    }

    .calendar-day.status-exception-half-day .day-number {
        color: #f59e0b;
        font-weight: 700;
    }

    .calendar-day.status-exception-half-day .status-code {
        color: #f59e0b;
        font-weight: 800;
    }

    .calendar-day.status-exception-late .day-number {
        color: #ef4444;
        font-weight: 700;
    }

    .calendar-day.status-exception-late .status-code {
        color: #ef4444;
        font-weight: 800;
    }

    .calendar-day.status-exception-pl .day-number {
        color: #ec4899;
        font-weight: 700;
    }

    .calendar-day.status-exception-pl .status-code {
        color: #ec4899;
        font-weight: 800;
    }


    /* ============================================
   SCHEDULED STATUS STYLES
   ============================================ */

    /* Scheduled status - entire box becomes yellow */
    .status-scheduled {
        background: #ffd93d !important;
        border: 2px solid #f0c000 !important;
        border-radius: 8px !important;
        position: relative;
        box-shadow: 0 2px 4px rgba(255, 217, 61, 0.3);
    }

    .status-scheduled .day-number {
        color: #6b5200 !important;
        font-weight: 700;
    }

    .status-scheduled .status-code {
        color: #6b5200 !important;
        font-weight: bold;
        background: rgba(255, 255, 255, 0.3);
        padding: 2px 8px;
        border-radius: 4px;
    }

    .status-scheduled .status-code.scheduled-status {
        background: rgba(255, 255, 255, 0.4);
        color: #6b5200;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
    }

    /* Legend color for scheduled */
    .legend-color.scheduled {
        background: #ffd93d;
        border: 2px solid #f0c000;
        border-radius: 4px;
    }

    /* Hover effect for scheduled */
    .status-scheduled:hover {
        background: #f5c800 !important;
        border-color: #d4a800 !important;
        transform: scale(1.02);
        transition: all 0.2s ease;
        box-shadow: 0 4px 8px rgba(255, 217, 61, 0.4);
    }

    /* Tooltip for scheduled */
    .has-scheduled .tooltip .time-row {
        border-bottom: 1px dashed #ffd93d;
        padding-bottom: 3px;
    }

    /* Scheduled summary */
    .scheduled-summary {
        animation: slideDown 0.5s ease;
        background: #fff8e1 !important;
        border-left: 3px solid #ffd93d !important;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Remove any circle styles */
    .status-scheduled .status-code {
        border-radius: 4px !important;
    }

    .status-scheduled .day-number {
        border-radius: 0 !important;
    }

    /* Make sure the box is fully yellow */
    .status-scheduled .calendar-day {
        background: #ffd93d !important;
    }



    /* Styling for the 13th (LWP) */
    .status-lwp {
        background-color: #ffeef0 !important;
        color: #d63384 !important;
        border: 1px solid #ffc1cc;
    }

    /* Styling for the 14th (PL) */
    .status-pl {
         background: rgb(39 51 219 / 6%);
    color: #001299;
    border-color: rgb(59 69 227 / 54%);
    }

    /* Legend items */
    .legend-color.lwp {
        background-color: #ffeef0;
        border: 1px solid #ffc1cc;
    }

    .legend-color.pl {
        background-color: #001299;
        border: 1px solid #c8e6c9;
    }



    .breadcrumb-trail {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        padding: 10px 15px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #e2e8f0;
    }

    .breadcrumb-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #64748b;
    }

    .breadcrumb-link {
        color: var(--blue);
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }

    .breadcrumb-link:hover {
        text-decoration: underline;
    }

    .breadcrumb-current {
        color: #1e293b;
        font-weight: 700;
    }

    .breadcrumb-separator {
        color: #94a3b8;
        font-size: 10px;
    }

    .role-badge-small {
        font-size: 9px;
        text-transform: uppercase;
        background: #e2e8f0;
        padding: 2px 6px;
        border-radius: 4px;
        color: #475569;
    }







    /* Ensure the table cell is the reference point for the tooltip */
.team-table td.day-cell {
    position: relative; /* CRITICAL for hover positioning */
    cursor: pointer;
}

/* Base tooltip style - hidden by default */
.team-table .tooltip {
    display: none;
    position: absolute;
    bottom: 125%; /* Position above the dot */
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 10px;
    border-radius: 8px;
    width: 160px;
    z-index: 9999; /* Higher than breadcrumbs and headers */
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
    font-size: 11px;
    pointer-events: none; /* Prevents flickering */
}

/* Triangle for tooltip */
.team-table .tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
}

/* SHOW Tooltip on hover */
.team-table td.day-cell:hover .tooltip {
    display: block !important;
}


/* Hierarchy Breadcrumb Styles (Fixed) */
.breadcrumb-trail {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding: 12px 18px;
    background: #f1f5f9;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid #e2e8f0;
    width: 100%;
}

.breadcrumb-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
}

.breadcrumb-current {
    color: #0f172a;
    font-weight: 700;
    font-size: 13px;
}


.team-table tbody tr:nth-child(-n+2) td.day-cell .tooltip {
    bottom: auto;
    top: 130%; /* Shows the tooltip BELOW the dot */
}

/* Flip the triangle to the top of the tooltip for the first 2 rows */
.team-table tbody tr:nth-child(-n+2) td.day-cell .tooltip::after {
    top: auto;
    bottom: 100%; 
    border-color: transparent transparent #1e293b transparent; /* Points triangle UP */
}

/* IMPORTANT: Boost z-index on hover so the tooltip stays ABOVE the row below it */
.team-table tbody tr:nth-child(-n+2) td.day-cell:hover {
    z-index: 1000;
}




.status-comp-off, .status-co {
    background: #f5f3ff !important; /* Very light purple bg */
    color: #7c3aed !important;      /* Purple text */
    border: 1px solid #ddd6fe !important;
}

/* For the Team Table Dot/Square */
.day-dot.comp-off, .status-badge.comp-off {
    background: #7c3aed !important;
    color: white !important;
}

/* Legend update */
.legend-color.comp-off {
    background: #7c3aed;
}
/* OT Button Styles */
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


/* ======================================================
   PENDING APPROVALS - DESKTOP STYLE ON MOBILE
   ====================================================== */
@media (max-width: 768px) {
    /* 1. Stack the card content so buttons move to the bottom */
    .approval-item {
        flex-direction: column !important;
        align-items: flex-start !important;
        padding: 16px !important;
        gap: 12px !important;
    }

    /* 2. Ensure the info text doesn't squeeze */
    .approval-item .info {
        width: 100% !important;
    }

    /* 3. Button Container - Keep them side by side but on a new line */
    .approval-actions {
        display: flex !important;
        justify-content: flex-end !important; /* Aligns buttons to the right like desktop */
        width: 100% !important;
        gap: 8px !important;
        margin-top: 5px !important;
    }

    /* 4. RESTORE DESKTOP BUTTON STYLE */
    .btn-approve, .btn-reject {
        /* Reset any full-width logic */
        flex: none !important; 
        width: auto !important;
        
        /* Re-applying your Desktop CSS exactly */
        padding: 4px 14px !important;
        border-radius: 50px !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        letter-spacing: 0.5px !important;
        height: auto !important;
        display: inline-block !important;
    }

    /* Keep the light background colors from desktop */
    .btn-approve {
        background: var(--green-bg) !important;
        color: var(--green) !important;
        border: 1px solid rgba(22, 163, 74, 0.2) !important;
    }

    .btn-reject {
        background: var(--red-bg) !important;
        color: var(--red) !important;
        border: 1px solid rgba(220, 38, 38, 0.2) !important;
    }
}


</style>