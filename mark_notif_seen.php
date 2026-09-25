<?php
session_start();
if (isset($_GET['count'])) {
    $_SESSION['notif_seen_count'] = (int)$_GET['count'];
}
echo json_encode(['status' => 'success']);
?>