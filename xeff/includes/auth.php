<?php
// XEFF — Authentication Middleware

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    // Ban check
    $user = getCurrentUser();
    if ($user && !empty($user['is_banned']) && $user['role'] !== 'admin') {
        session_destroy();
        header('Location: login.php?error=banned');
        exit;
    }
    // Membership check (admin exempt)
    if ($user && $user['role'] !== 'admin') {
        $mt = $user['membership_type'] ?? 'none';
        $me = $user['membership_expires'] ?? null;
        if ($mt === 'none') {
            header('Location: login.php?error=nomembership');
            exit;
        }
        if ($mt !== 'lifetime' && $me && strtotime($me) < time()) {
            header('Location: login.php?error=expired');
            exit;
        }
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied.');
    }
}

function getCurrentUser() {
    global $db;
    if (!isLoggedIn()) return null;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function getCurrentUsername() {
    return $_SESSION['username'] ?? 'unknown';
}

function getCurrentRole() {
    return $_SESSION['role'] ?? 'user';
}
