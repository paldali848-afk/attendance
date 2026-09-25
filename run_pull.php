<?php
require_once 'config.php'; // <--- IMPORTANT: This must define $conn
require_once 'includes/Pipeline.php';
require_once 'includes/modules/pull_logic/Stages.php';

$caseId = $_GET['id'] ?? null;

if (!$caseId) {
    die("Error: Missing ID");
}

try {
    $pipeline = new Pipeline();

    // The pipeline now interacts with your actual MySQL tables
    $result = $pipeline
        ->pipe(new FetchCase())
        ->pipe(new SaveCase())
        ->process(['id' => $caseId]);

    echo "<strong>Final Status:</strong> Case " . htmlspecialchars($result['id']) . " is now synchronized.";

} catch (Exception $e) {
    // If the ID doesn't exist or SQL fails, this catches it
    echo "<strong>Pipeline Failed:</strong> " . $e->getMessage();
}