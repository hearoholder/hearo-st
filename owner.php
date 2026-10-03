<?php
require_once 'xeff/includes/config.php';
require_once 'xeff/includes/auth.php';
requireAuth();

$msg = ''; $msgType = '';

// --- Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create user
    if ($action === 'create_user') {
        $uname = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        $mtype = $_POST['membership_type'] ?? 'month';
        $role = $_POST['role'] ?? 'user';
        $maxBuilds = (int)($_POST['max_builds'] ?? 3);
        $notes = trim($_POST['notes'] ?? '');

        if (empty($uname) || empty($pass)) {
            $msg = 'Kullanıcı adı ve şifre gerekli.'; $msgType = 'error';
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$uname]);
            if ($check->fetch()) {
                $msg = 'Bu kullanıcı adı zaten mevcut.'; $msgType = 'error';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $apiKey = bin2hex(random_bytes(16));

                // Calculate expiry
                $expires = null;
                if ($mtype === 'trial') $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
                elseif ($mtype === 'month') $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                elseif ($mtype === '3month') $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
                elseif ($mtype === 'year') $expires = date('Y-m-d H:i:s', strtotime('+365 days'));
                // lifetime = null (no expiry)

                $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, api_key, membership_type, membership_expires, max_builds, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$uname, $hash, $role, $apiKey, $mtype, $expires, $maxBuilds, $notes, getCurrentUsername()]);
                $msg = "Kullanıcı '$uname' oluşturuldu ($mtype)."; $msgType = 'success';
            }
        }
    }

    // Ban user
    if ($action === 'ban_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $reason = trim($_POST['ban_reason'] ?? '');
        if ($uid > 0) {
            $db->prepare("UPDATE users SET is_banned = 1, ban_reason = ?, banned_at = CURRENT_TIMESTAMP WHERE id = ? AND role != 'admin'")->execute([$reason, $uid]);
            $msg = 'Kullanıcı banlandı.'; $msgType = 'success';
        }
    }

    // Unban user
    if ($action === 'unban_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            $db->prepare("UPDATE users SET is_banned = 0, ban_reason = '', banned_at = NULL WHERE id = ?")->execute([$uid]);
            $msg = 'Ban kaldırıldı.'; $msgType = 'success';
        }
    }

    // Delete user
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$uid]);
            $msg = 'Kullanıcı silindi.'; $msgType = 'success';
        }
    }

    // Change membership
    if ($action === 'change_membership') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $mtype = $_POST['membership_type'] ?? 'month';
        if ($uid > 0) {
            $expires = null;
            if ($mtype === 'trial') $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            elseif ($mtype === 'month') $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            elseif ($mtype === '3month') $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
            elseif ($mtype === 'year') $expires = date('Y-m-d H:i:s', strtotime('+365 days'));
            elseif ($mtype === 'none') $expires = null;

            $db->prepare("UPDATE users SET membership_type = ?, membership_expires = ? WHERE id = ?")->execute([$mtype, $expires, $uid]);
            $msg = "Üyelik güncellendi: $mtype"; $msgType = 'success';
        }
    }

    // Extend membership
    if ($action === 'extend_membership') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $days = (int)($_POST['extend_days'] ?? 30);
        if ($uid > 0 && $days > 0) {
            $user = $db->prepare("SELECT membership_expires FROM users WHERE id = ?");
            $user->execute([$uid]);
            $u = $user->fetch(PDO::FETCH_ASSOC);
            $base = ($u && $u['membership_expires'] && strtotime($u['membership_expires']) > time())
                ? $u['membership_expires'] : date('Y-m-d H:i:s');
            $newExp = date('Y-m-d H:i:s', strtotime($base . " +$days days"));
            $db->prepare("UPDATE users SET membership_expires = ? WHERE id = ?")->execute([$newExp, $uid]);
            $msg = "Üyelik $days gün uzatıldı. Yeni bitiş: $newExp"; $msgType = 'success';
        }
    }

    // Reset password
    if ($action === 'reset_password') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $np = $_POST['new_password'] ?? '';
        if ($uid > 0 && strlen($np) >= 4) {
            $hash = password_hash($np, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $uid]);
            $msg = 'Şifre sıfırlandı.'; $msgType = 'success';
        }
    }

    // Update max builds
    if ($action === 'update_max_builds') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $mb = (int)($_POST['max_builds'] ?? 3);
        if ($uid > 0) {
            $db->prepare("UPDATE users SET max_builds = ? WHERE id = ?")->execute([$mb, $uid]);
            $msg = "Max build sayısı güncellendi: $mb"; $msgType = 'success';
        }
    }
}

// --- Fetch Data ---
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalUsers = count($users);
$activeUsers = 0; $expiredUsers = 0; $bannedUsers = 0; $lifetimeUsers = 0;
foreach ($users as $u) {
    if (!empty($u['is_banned'])) { $bannedUsers++; continue; }
    if ($u['role'] === 'admin') { $activeUsers++; continue; }
    $mt = $u['membership_type'] ?? 'none';
    if ($mt === 'lifetime') { $lifetimeUsers++; $activeUsers++; }
    elseif ($mt !== 'none' && ($u['membership_expires'] === null || strtotime($u['membership_expires']) > time())) { $activeUsers++; }
    else { $expiredUsers++; }
}
$totalLogs = $db->query("SELECT COUNT(*) FROM logs")->fetchColumn() ?: 0;
$totalBuilds = $db->query("SELECT COUNT(*) FROM build_configs")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Panel — Hearo Stealer</title>
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
        <h1 style="font-weight:300; font-size:28px; margin-bottom:6px;">Owner Panel</h1>
        <p style="color:rgba(255,255,255,0.35); font-size:12px; margin-bottom:28px;">Kullanıcı yönetimi, üyelik, ban sistemi</p>

        <?php if ($msg): ?><div class="msg <?= $msgType ?>"><?= sanitize($msg) ?></div><?php endif; ?>

        <!-- Stats -->
        <div class="owner-grid">
            <div class="owner-stat"><div class="val"><?= $totalUsers ?></div><div class="lbl">Toplam Üye</div></div>
            <div class="owner-stat"><div class="val" style="color:#10b981;"><?= $activeUsers ?></div><div class="lbl">Aktif</div></div>
            <div class="owner-stat"><div class="val" style="color:#8b5cf6;"><?= $lifetimeUsers ?></div><div class="lbl">Lifetime</div></div>
            <div class="owner-stat"><div class="val" style="color:#f59e0b;"><?= $expiredUsers ?></div><div class="lbl">Süresi Dolmuş</div></div>
            <div class="owner-stat"><div class="val" style="color:#ef4444;"><?= $bannedUsers ?></div><div class="lbl">Banlı</div></div>
            <div class="owner-stat"><div class="val"><?= $totalLogs ?></div><div class="lbl">Toplam Log</div></div>
        </div>

        <!-- Create User -->
        <div class="section-card">
            <div class="section-title"><i class="fa-solid fa-user-plus"></i> Yeni Kullanıcı Oluştur</div>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-row">
                    <div>
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-input" placeholder="kullanici" required>
                    </div>
                    <div>
                        <label class="form-label">Şifre</label>
                        <input type="text" name="password" class="form-input" placeholder="sifre123" required>
                    </div>
                    <div>
                        <label class="form-label">Üyelik Türü</label>
                        <select name="membership_type" class="form-select">
                            <option value="trial">Trial (7 Gün)</option>
                            <option value="month" selected>Aylık (30 Gün)</option>
                            <option value="3month">3 Aylık (90 Gün)</option>
                            <option value="year">Yıllık (365 Gün)</option>
                            <option value="lifetime">Lifetime ♾️</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select">
                            <option value="user" selected>User</option>
                            <option value="reseller">Reseller</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Max Build</label>
                        <input type="number" name="max_builds" class="form-input" value="3" min="0" max="999">
                    </div>
                </div>
                <div class="form-row">
                    <div style="flex:3;">
                        <label class="form-label">Not (opsiyonel)</label>
                        <input type="text" name="notes" class="form-input" placeholder="Discord: user#1234 vb.">
                    </div>
                    <div style="flex:1; display:flex; align-items:flex-end;">
                        <button type="submit" class="btn-create" style="width:100%;">
                            <i class="fa-solid fa-plus"></i> Oluştur
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- User List -->
        <div class="section-card">
            <div class="section-title"><i class="fa-solid fa-users"></i> Tüm Kullanıcılar (<?= $totalUsers ?>)</div>
            <div style="overflow-x:auto;">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Üyelik</th>
                            <th>Bitiş</th>
                            <th>Durum</th>
                            <th>Build</th>
                            <th>Kayıt</th>
                            <th style="text-align:right;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u):
                        $isBanned = !empty($u['is_banned']);
                        $isExpired = false;
                        $mt = $u['membership_type'] ?? 'none';
                        $me = $u['membership_expires'] ?? null;
                        if ($mt !== 'lifetime' && $mt !== 'none' && $me && strtotime($me) < time()) $isExpired = true;
                        $isAdmin = ($u['role'] === 'admin');

                        if ($isBanned) $statusClass = 'badge-banned';
                        elseif ($isAdmin) $statusClass = 'badge-admin';
                        elseif ($mt === 'lifetime') $statusClass = 'badge-lifetime';
                        elseif ($isExpired) $statusClass = 'badge-expired';
                        elseif ($mt === 'none') $statusClass = 'badge-none';
                        else $statusClass = 'badge-active';

                        $statusText = $isBanned ? 'BANNED' : ($isAdmin ? 'OWNER' : ($mt === 'lifetime' ? 'LIFETIME' : ($isExpired ? 'EXPIRED' : ($mt === 'none' ? 'NONE' : 'ACTIVE'))));

                        $buildCount = $db->prepare("SELECT COUNT(*) FROM build_configs WHERE user_id = ?");
                        $buildCount->execute([$u['id']]);
                        $bc = $buildCount->fetchColumn();
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:2px;">
                                <span style="font-weight:500;"><?= sanitize($u['username']) ?></span>
                                <?php if (!empty($u['notes'])): ?>
                                <span style="font-size:10px; color:rgba(255,255,255,0.3);"><?= sanitize($u['notes']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge <?= $isAdmin ? 'badge-admin' : 'badge-active' ?>"><?= sanitize($u['role']) ?></span></td>
                        <td style="font-family:'JetBrains Mono',monospace; font-size:11px;">
                            <?= $mt ?>
                        </td>
                        <td style="font-family:'JetBrains Mono',monospace; font-size:10px; color:rgba(255,255,255,0.4);">
                            <?php if ($mt === 'lifetime'): ?>
                                <span style="color:#8b5cf6;">♾️ Sınırsız</span>
                            <?php elseif ($me): ?>
                                <?= date('d M Y', strtotime($me)) ?>
                                <?php if ($isExpired): ?><br><span style="color:#ef4444;">Dolmuş</span><?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td style="font-family:'JetBrains Mono',monospace; font-size:11px;">
                            <?= $bc ?>/<?= $u['max_builds'] ?? '∞' ?>
                        </td>
                        <td style="font-family:'JetBrains Mono',monospace; font-size:10px; color:rgba(255,255,255,0.35);">
                            <?= date('d M Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td>
                            <?php if (!$isAdmin): ?>
                            <div class="action-btns" style="justify-content:flex-end;">
                                <!-- Membership -->
                                <button class="btn-sm" onclick="openModal('membership-<?= $u['id'] ?>')">Üyelik</button>
                                <!-- Extend -->
                                <button class="btn-sm success" onclick="openModal('extend-<?= $u['id'] ?>')">Uzat</button>
                                <!-- Reset PW -->
                                <button class="btn-sm" onclick="openModal('resetpw-<?= $u['id'] ?>')">Şifre</button>
                                <!-- Ban/Unban -->
                                <?php if ($isBanned): ?>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="unban_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-sm success">Unban</button></form>
                                <?php else: ?>
                                <button class="btn-sm danger" onclick="openModal('ban-<?= $u['id'] ?>')">Ban</button>
                                <?php endif; ?>
                                <!-- Delete -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğine emin misin?');">
                                    <input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-sm danger">Sil</button>
                                </form>
                            </div>

                            <!-- Modals for this user -->
                            <div class="modal-overlay" id="membership-<?= $u['id'] ?>">
                                <div class="modal-box">
                                    <div class="modal-title">Üyelik Değiştir — <?= sanitize($u['username']) ?></div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_membership">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="membership_type" class="form-select" style="margin-bottom:12px;">
                                            <option value="none">Üyelik Yok</option>
                                            <option value="trial">Trial (7 Gün)</option>
                                            <option value="month">Aylık (30 Gün)</option>
                                            <option value="3month">3 Aylık (90 Gün)</option>
                                            <option value="year">Yıllık (365 Gün)</option>
                                            <option value="lifetime">Lifetime ♾️</option>
                                        </select>
                                        <div style="display:flex;gap:8px;">
                                            <button type="submit" class="btn-create">Kaydet</button>
                                            <button type="button" class="btn-sm" onclick="closeModal('membership-<?= $u['id'] ?>')">İptal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="modal-overlay" id="extend-<?= $u['id'] ?>">
                                <div class="modal-box">
                                    <div class="modal-title">Üyelik Uzat — <?= sanitize($u['username']) ?></div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="extend_membership">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <label class="form-label">Eklenecek Gün</label>
                                        <input type="number" name="extend_days" class="form-input" value="30" min="1" style="margin-bottom:12px;">
                                        <div style="display:flex;gap:8px;">
                                            <button type="submit" class="btn-create">Uzat</button>
                                            <button type="button" class="btn-sm" onclick="closeModal('extend-<?= $u['id'] ?>')">İptal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="modal-overlay" id="resetpw-<?= $u['id'] ?>">
                                <div class="modal-box">
                                    <div class="modal-title">Şifre Sıfırla — <?= sanitize($u['username']) ?></div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <label class="form-label">Yeni Şifre</label>
                                        <input type="text" name="new_password" class="form-input" placeholder="yenisifre123" required style="margin-bottom:12px;">
                                        <div style="display:flex;gap:8px;">
                                            <button type="submit" class="btn-create">Sıfırla</button>
                                            <button type="button" class="btn-sm" onclick="closeModal('resetpw-<?= $u['id'] ?>')">İptal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="modal-overlay" id="ban-<?= $u['id'] ?>">
                                <div class="modal-box">
                                    <div class="modal-title" style="color:#ef4444;">Ban — <?= sanitize($u['username']) ?></div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="ban_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <label class="form-label">Ban Sebebi (opsiyonel)</label>
                                        <input type="text" name="ban_reason" class="form-input" placeholder="Kural ihlali..." style="margin-bottom:12px;">
                                        <div style="display:flex;gap:8px;">
                                            <button type="submit" class="btn-sm danger" style="padding:10px 24px;">Banla</button>
                                            <button type="button" class="btn-sm" onclick="closeModal('ban-<?= $u['id'] ?>')">İptal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php else: ?>
                            <span style="font-size:10px; color:rgba(255,255,255,0.2);">owner</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
        });
    </script>
</body>
</html>
