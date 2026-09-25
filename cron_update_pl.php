<?php
// cron_update_pl.php
// Location: /srv/www/htdocs/attendance/cron_update_pl.php

require_once __DIR__ . '/config.php';

try {
    $today_date = date('Y-m-d');
    $month_day = date('m-d'); // Example: "08-01" or "04-01"
    $current_month_name = date('F Y');

    // --- SAFETY CHECK 1: ONLY RUN ON THE 1st ---
    if (date('d') !== date('d')) {
        die("STOP: PL is only added on the 1st of the month. Today is " . $today_date);
    }

    // --- SAFETY CHECK 2: DON'T RUN TWICE ---
    // This checks if we already added a 'monthly_credit' today
    $checkLog = $pdo->prepare("SELECT COUNT(*) FROM pl_history WHERE action_type = 'monthly_credit' AND DATE(created_at) = ?");
    $checkLog->execute([$today_date]);
    if ($checkLog->fetchColumn() > 0) {
        die("STOP: PL was already credited for this month today ($today_date).");
    }

    $pdo->beginTransaction();

    // --- PART 1: APRIL 1st LAPSE (Capping at 9 days) ---
    if ($month_day === '04-01') {
        // Find users with more than 9 days
        $lapse_query = $pdo->query("SELECT id, pl_balance FROM users WHERE pl_balance > 9 AND is_active = 1");
        $lapse_users = $lapse_query->fetchAll();

        foreach ($lapse_users as $l_user) {
            $lost_amount = (float)$l_user['pl_balance'] - 9;
            
            // Log the lapse so users know why they lost days
            $log_lapse = $pdo->prepare("INSERT INTO pl_history (user_id, action_type, amount_changed, new_balance, description, created_at) 
                                       VALUES (?, 'yearly_lapse', ?, 9, 'Yearly lapse: Balance capped at 9 days', NOW())");
            $log_lapse->execute([$l_user['id'], -$lost_amount]);
        }

        // Cap balances at 9
        $pdo->exec("UPDATE users SET pl_balance = 9 WHERE pl_balance > 9 AND is_active = 1");
    }

    // --- PART 2: MONTHLY CREDIT (Adding 1.5 days) ---
    $stmt = $pdo->query("SELECT id, pl_balance FROM users WHERE is_active = 1");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        $old_balance = (float)$user['pl_balance'];
        $new_balance = $old_balance + 1.5;

        // Update User table
        $update = $pdo->prepare("UPDATE users SET pl_balance = ? WHERE id = ?");
        $update->execute([$new_balance, $user['id']]);

        // Log the credit
        $log = $pdo->prepare("INSERT INTO pl_history (user_id, action_type, amount_changed, new_balance, description, created_at) 
                             VALUES (?, 'monthly_credit', 1.5, ?, 'Monthly auto-credit', NOW())");
        $log->execute([$user['id'], $new_balance]);
    }

    $pdo->commit();
    echo "SUCCESS: Processed " . count($users) . " users for $current_month_name.";
    if ($month_day === '04-01') echo " (April 1st Lapse logic applied)";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PL Cron Error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage();
}