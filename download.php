<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$hash = $_GET['hash'] ?? '';
if (empty($hash)) die('No hash provided.');

$stmt = $db->prepare("SELECT * FROM logs WHERE session_hash = ?");
$stmt->execute([$hash]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) die('Log not found.');
if (!isAdmin() && $log['builder_user'] !== getCurrentUsername()) die('Unauthorized access to this log.');

$filePath = $log['data_path'];
if (!file_exists($filePath)) die('File missing on disk.');

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="log_'.$log['session_hash'].'.zip"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>
