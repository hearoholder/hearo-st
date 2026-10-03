<?php
// Hearo Stealer — Delete Log Endpoint
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

header('Content-Type: application/json');

$hash = $_POST['session_hash'] ?? $_GET['session_hash'] ?? '';
if (empty($hash)) {
    die(json_encode(['error' => 'No session_hash provided']));
}

$stmt = $db->prepare("SELECT * FROM logs WHERE session_hash = ?");
$stmt->execute([$hash]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    die(json_encode(['error' => 'Log not found']));
}

if (!isAdmin() && $log['builder_user'] !== getCurrentUsername()) {
    die(json_encode(['error' => 'Unauthorized']));
}

// Delete uploaded file
if (!empty($log['data_path']) && file_exists($log['data_path'])) {
    @unlink($log['data_path']);
}

// Delete discord events for this session
$db->prepare("DELETE FROM discord_events WHERE session_hash = ?")->execute([$hash]);

// Delete the log
$db->prepare("DELETE FROM logs WHERE session_hash = ?")->execute([$hash]);

echo json_encode(['status' => 'deleted', 'session_hash' => $hash]);
