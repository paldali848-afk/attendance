<?php
require_once 'config.php';
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'centre_head')) {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

$user = getCurrentUser();

// Get all users
$users = $pdo->query("
    SELECT u.*, 
           r.full_name as reporting_manager_name
    FROM users u
    LEFT JOIN users r ON u.reporting_to = r.id
    ORDER BY u.created_at DESC
")->fetchAll();

// Get statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'centre_head' THEN 1 ELSE 0 END) as centre_head,
        SUM(CASE WHEN role = 'process_head' THEN 1 ELSE 0 END) as process_head,
        SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as manager,
        SUM(CASE WHEN role = 'am' THEN 1 ELSE 0 END) as am,
        SUM(CASE WHEN role = 'tl' THEN 1 ELSE 0 END) as tl,
        SUM(CASE WHEN role = 'agent' THEN 1 ELSE 0 END) as agent
    FROM users
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ===== PROFESSIONAL BRIGHT WHITE THEME ===== */
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

            /* Enhanced Colors */
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --blue-light: #dbeafe;
            --blue-bg: rgba(37, 99, 235, 0.06);

            --green: #16a34a;
            --green-light: #dcfce7;
            --green-bg: rgba(22, 163, 74, 0.06);

            --red: #dc2626;
            --red-light: #fee2e2;
            --red-bg: rgba(220, 38, 38, 0.06);

            --yellow: #d97706;
            --yellow-light: #fef3c7;
            --yellow-bg: rgba(217, 119, 6, 0.06);

            --purple: #7c3aed;
            --purple-light: #ede9fe;
            --purple-bg: rgba(124, 58, 237, 0.06);

            --cyan: #0891b2;
            --cyan-light: #cffafe;
            --cyan-bg: rgba(8, 145, 178, 0.06);

            --orange: #ea580c;
            --orange-light: #ffedd5;
            --orange-bg: rgba(234, 88, 12, 0.06);

            --pink: #db2777;
            --pink-light: #fce7f3;
            --pink-bg: rgba(219, 39, 119, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f6;
            padding: 20px;
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
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

        /* ===== HEADER ===== */
        .header {
            background: var(--white);
            border-radius: var(--radius);
            padding: 22px 30px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .header:hover {
            box-shadow: var(--shadow-md);
        }

        .header .header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .header .system-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .system-label .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: pulseDot 2s ease-in-out infinite;
            display: inline-block;
        }

        @keyframes pulseDot {

            0%,
            100% {
                opacity: 0.6;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.3);
            }
        }

        .header h1 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }

        .header h1 i {
            color: var(--blue);
            font-size: 26px;
        }

        .header h1 .highlight {
            color: var(--blue);
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .header-actions a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 22px;
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border-light);
            background: var(--bg-light);
        }

        .header-actions a:hover {
            background: var(--blue-bg);
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .header-actions .btn-add {
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
            border: none;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
            font-weight: 600;
        }

        .header-actions .btn-add:hover {
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--blue), var(--purple));
            color: #fff;
        }

        .header-actions .btn-add i {
            color: #fff;
        }

        .header-actions .btn-dashboard i {
            color: var(--blue);
        }

        .header-actions .btn-dashboard:hover i {
            color: var(--blue);
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius-sm);
            padding: 18px 22px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .stat-card .number {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .stat-card .number.blue {
            color: var(--blue);
        }

        .stat-card .number.green {
            color: var(--green);
        }

        .stat-card .number.cyan {
            color: var(--cyan);
        }

        .stat-card .number.yellow {
            color: var(--yellow);
        }

        .stat-card .number.purple {
            color: var(--purple);
        }

        .stat-card .number.orange {
            color: var(--orange);
        }

        .stat-card .number.pink {
            color: var(--pink);
        }

        .stat-card .label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        /* ===== CARD ===== */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 28px 32px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-light);
        }

        .card-header h2 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.3px;
        }

        .card-header h2 i {
            color: var(--blue);
            font-size: 18px;
        }

        .card-header .user-count {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 6px 18px;
            border: 1px solid var(--border-light);
            border-radius: 50px;
            background: var(--bg-light);
        }

        .card-header .user-count i {
            color: var(--blue);
            margin-right: 6px;
        }

        /* ===== TABLE ===== */
        .table-responsive {
            overflow-x: auto;
        }

        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: var(--bg-light);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--blue);
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table th {
            background: var(--bg-light);
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-light);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
            font-weight: 500;
        }

        table tr {
            transition: all 0.2s ease;
        }

        table tr:hover {
            background: var(--blue-bg);
        }

        table td .name {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 14px;
        }

        table td .username {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ===== ROLE BADGE ===== */
        .role-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }

        .role-badge.centre_head {
            background: var(--blue-bg);
            color: var(--blue);
            border-color: rgba(37, 99, 235, 0.15);
        }

        .role-badge.process_head {
            background: var(--cyan-bg);
            color: var(--cyan);
            border-color: rgba(8, 145, 178, 0.15);
        }

        .role-badge.manager {
            background: var(--green-bg);
            color: var(--green);
            border-color: rgba(22, 163, 74, 0.15);
        }

        .role-badge.am {
            background: var(--yellow-bg);
            color: var(--yellow);
            border-color: rgba(217, 119, 6, 0.15);
        }

        .role-badge.tl {
            background: var(--orange-bg);
            color: var(--orange);
            border-color: rgba(234, 88, 12, 0.15);
        }

        .role-badge.agent {
            background: var(--bg-light);
            color: var(--text-muted);
            border-color: var(--border-light);
        }

        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-buttons a {
            padding: 6px 14px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .action-buttons .btn-edit {
            background: var(--blue-bg);
            color: var(--blue);
            border: 1px solid rgba(37, 99, 235, 0.1);
        }

        .action-buttons .btn-edit:hover {
            background: var(--blue);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.2);
        }

        .action-buttons .btn-delete {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid rgba(220, 38, 38, 0.1);
        }

        .action-buttons .btn-delete:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(220, 38, 38, 0.2);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--text-muted);
            opacity: 0.2;
        }

        .empty-state p {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-secondary);
        }

        .empty-state .sub {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ===== PERSON ID ===== */
        .person-id {
            font-family: 'Inter', monospace;
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .header {
                padding: 20px 24px;
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .header h1 {
                font-size: 22px;
            }

            .header-actions {
                justify-content: stretch;
            }

            .header-actions a {
                flex: 1;
                justify-content: center;
                font-size: 12px;
                padding: 8px 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 14px 16px;
            }

            .stat-card .number {
                font-size: 22px;
            }

            .stat-card .label {
                font-size: 9px;
            }

            .card {
                padding: 18px 16px;
            }

            .card-header h2 {
                font-size: 16px;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 10px 12px;
            }

            .action-buttons a {
                font-size: 10px;
                padding: 4px 10px;
            }

            .role-badge {
                font-size: 9px;
                padding: 2px 10px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }

            .stat-card .number {
                font-size: 18px;
            }

            .stat-card .label {
                font-size: 8px;
                letter-spacing: 0.3px;
            }

            .header h1 {
                font-size: 18px;
            }

            .header .system-label {
                font-size: 9px;
            }

            table {
                font-size: 11px;
            }

            table th,
            table td {
                padding: 8px 8px;
            }

            .person-id {
                font-size: 10px;
            }

            .card-header h2 {
                font-size: 14px;
            }

            .card-header .user-count {
                font-size: 11px;
                padding: 4px 12px;
            }
        }





        /* Search Bar next to Records */
        .search-container {
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
            pointer-events: none;
        }

        .search-container input {
            padding: 8px 12px 8px 35px;
            border: 1px solid var(--border-light);
            border-radius: 50px;
            background: var(--bg-light);
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 500;
            width: 220px;
            /* Slimmer width for right alignment */
            transition: all 0.3s ease;
        }

        .search-container input:focus {
            outline: none;
            background: var(--white);
            border-color: var(--blue);
            width: 280px;
            /* Expands slightly when you click it */
            box-shadow: var(--shadow-sm);
        }

        /* Adjustments for mobile */
        @media (max-width: 600px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .search-container,
            .search-container input {
                width: 100% !important;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header anim-fade-up">
            <div class="header-left">
                <div class="system-label">
                    <span class="dot"></span>
                    USER MANAGEMENT
                </div>
                <h1>
                    <i class="fas fa-users-cog"></i>
                    USER <span class="highlight">DIRECTORY</span>
                </h1>
            </div>
            <div class="header-actions">
                <a href="add_user.php" class="btn-add">
                    <i class="fas fa-user-plus"></i> ADD USER
                </a>
                <a href="dashboard.php" class="btn-dashboard">
                    <i class="fas fa-th-large"></i> DASHBOARD
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card anim-fade-up anim-delay-1">
                <div class="number blue"><?php echo $stats['total']; ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-card anim-fade-up anim-delay-1">
                <div class="number purple"><?php echo $stats['centre_head']; ?></div>
                <div class="label">Centre Heads</div>
            </div>
            <div class="stat-card anim-fade-up anim-delay-1">
                <div class="number cyan"><?php echo $stats['process_head']; ?></div>
                <div class="label">Process Heads</div>
            </div>
            <div class="stat-card anim-fade-up anim-delay-2">
                <div class="number green"><?php echo $stats['manager']; ?></div>
                <div class="label">Managers</div>
            </div>
            <div class="stat-card anim-fade-up anim-delay-2">
                <div class="number yellow"><?php echo $stats['am']; ?></div>
                <div class="label">AM</div>
            </div>
            <div class="stat-card anim-fade-up anim-delay-3">
                <div class="number orange"><?php echo $stats['tl']; ?></div>
                <div class="label">Team Leads</div>
            </div>
            <div class="stat-card anim-fade-up anim-delay-3">
                <div class="number pink"><?php echo $stats['agent']; ?></div>
                <div class="label">Agents</div>
            </div>
        </div>

        <!-- User List -->
        <div class="card anim-fade-up anim-delay-2">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h2><i class="fas fa-list"></i> USER DIRECTORY</h2>

                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <!-- Search Bar -->
                    <div class="search-container">
                        <i class="fas fa-search"></i>
                        <input type="text" id="userSearch" placeholder="Search employees..." onkeyup="filterTable()">
                    </div>

                    <!-- Record Count -->
                    <span class="user-count">
                        <i class="fas fa-users"></i> <?php echo count($users); ?> RECORDS
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Person ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Role</th>
                            <th>Process</th>
                            <th>Reports To</th>
                            <th>Shift</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>NO USERS FOUND</p>
                                        <div class="sub">Click "Add User" to create a new user</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><span class="person-id"><?php echo htmlspecialchars($u['person_id']); ?></span></td>
                                    <td>
                                        <div class="name"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                        <div class="username"><?php echo htmlspecialchars($u['username']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['department'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($u['position'] ?? '-'); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo $u['role']; ?>">
                                            <?php echo str_replace('_', ' ', $u['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['process'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($u['reporting_manager_name'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($u['shift_id'] ?? '-'); ?></td>
                                    <td style="font-size:12px;color:var(--text-muted);font-weight:500;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i> EDIT
                                            </a>
                                            <?php if ($u['id'] != $user['id']): ?>
                                                <a href="delete_user.php?id=<?php echo $u['id']; ?>" class="btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function filterTable() {
            // Get search input value
            const input = document.getElementById("userSearch");
            const filter = input.value.toUpperCase();
            const table = document.querySelector("table");
            const tr = table.getElementsByTagName("tr");

            // Loop through all table rows (starting from index 1 to skip header)
            for (let i = 1; i < tr.length; i++) {
                const row = tr[i];
                // Only search if it's not the "No Users Found" row
                if (row.cells.length > 1) {
                    const textContent = row.textContent || row.innerText;

                    // Check if filter string exists anywhere in the row's text
                    if (textContent.toUpperCase().indexOf(filter) > -1) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                }
            }
        }
    </script>
</body>

</html>