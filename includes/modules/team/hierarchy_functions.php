<?php
// ============================================
// HIERARCHY FUNCTIONS - UNIVERSAL REPORTING
// ============================================

function getDirectReports($user_id, $current_role, $pdo) {
    
    // 1.If the Centre Head/HR/Admin is viewing their dashboard.
    if (in_array($current_role, ['centre_head','admin'])) {
        // Sabhi Process Heads ko fetch karo (Standard View)
        $stmt = $pdo->prepare("SELECT id, full_name, email, role, person_id FROM users WHERE role = 'process_head' ORDER BY full_name");
        $stmt->execute();
        $process_heads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // PLUS: Fetch anyone who reports DIRECTLY to the Centre Head (even if they are Agents/TLs)
        $stmt2 = $pdo->prepare("SELECT id, full_name, email, role, person_id FROM users WHERE reporting_to = ? AND role != 'process_head' ORDER BY full_name");
        $stmt2->execute([$user_id]);
        $direct_subordinates = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $all_members = array_merge($process_heads, $direct_subordinates);

        if (!empty($all_members)) {
            return ['role' => 'Direct Reports', 'members' => $all_members];
        }
    }

    // 2. For all other roles (Process Head, Manager, TL, and AM)
    // This query will directly check whose reporting_to field contains your ID
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, role, person_id 
        FROM users 
        WHERE reporting_to = ? 
        ORDER BY FIELD(role, 'manager', 'am', 'tl', 'agent'), full_name ASC
    ");
    $stmt->execute([$user_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($members)) {
        return ['role' => 'Direct Reports', 'members' => $members];
    }

    return ['role' => '', 'members' => []];
}

function getTeamData($pdo, $user_id, $user_role) {
    return getDirectReports($user_id, $user_role, $pdo);
}

// Baki functions (getBackUser, getHierarchyPath) same rahenge...
function getBackUser($pdo, $user, $selected_user_id) {
    if ($selected_user_id == $user['id']) return null;
    
    $stmt = $pdo->prepare("SELECT reporting_to FROM users WHERE id = ?");
    $stmt->execute([$selected_user_id]);
    $parent_id = $stmt->fetchColumn();

    if ($parent_id) {
        $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
        $stmt->execute([$parent_id]);
        $parent_data = $stmt->fetch();
        if ($parent_data) {
            return $parent_data;
        }
    }
    return null;
}


function getHierarchyPath($pdo, $loggedInUserId, $selectedUserId) {
    $path = [];
    $currentId = $selectedUserId;

    // Trace upwards from selected user to the logged-in user
    while ($currentId) {
        $stmt = $pdo->prepare("SELECT id, full_name, role, reporting_to FROM users WHERE id = ?");
        $stmt->execute([$currentId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) break;

        // Add to the front of the array
        array_unshift($path, $user);

        // Stop if we've reached the logged-in user or if they have no manager
        if ($currentId == $loggedInUserId || empty($user['reporting_to'])) {
            break;
        }

        $currentId = $user['reporting_to'];
    }
    return $path;
}
?>