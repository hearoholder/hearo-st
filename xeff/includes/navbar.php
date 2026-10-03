<?php
// Hearo Stealer - Premium Navigation Bar Component
$current_page = basename($_SERVER['PHP_SELF']);
$nav_items = [
    ['href' => 'index.php',    'label' => 'Dashboard',  'icon' => 'fa-chart-simple'],
    ['href' => 'all_logs.php', 'label' => 'Vault',      'icon' => 'fa-vault'],
    ['href' => 'build.php',    'label' => 'Builder',     'icon' => 'fa-hammer'],
    ['href' => 'active_victims.php',  'label' => 'Victims',     'icon' => 'fa-satellite-dish'],
    ['href' => 'settings.php', 'label' => 'Settings',    'icon' => 'fa-gear'],
];
if (true) {
    $nav_items[] = ['href' => 'owner.php', 'label' => 'Owner', 'icon' => 'fa-crown'];
}
?>
<nav class="nav-wrapper">
    <!-- Brand -->
    <a href="index.php" class="nav-brand">
        <div class="nav-logo-box">
            <i class="fa-solid fa-bolt" style="font-size: 20px; color: #fff;"></i>
        </div>
        <span class="nav-brand-text">
            HEARO<span>STEALER</span>
        </span>
    </a>

    <!-- Nav Links -->
    <div class="nav-links">
        <?php foreach ($nav_items as $item): ?>
            <?php $is_active = ($current_page === $item['href']); ?>
            <a href="<?= $item['href'] ?>" class="nav-link <?= $is_active ? 'active' : '' ?>">
                <i class="fa-solid <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Right: User Badge + Logout -->
    <div class="nav-right">
        <div class="user-badge">
            <div class="status-dot"></div>
            <span class="user-info">
                <?= sanitize(getCurrentUsername()) ?>
                <span class="user-role">/// <?= strtoupper(sanitize(getCurrentRole()) === 'admin' ? 'OWNER' : getCurrentRole()) ?></span>
            </span>
        </div>
        <a href="logout.php" class="nav-logout">
            <span>Exit System</span>
            <i class="fa-solid fa-power-off"></i>
        </a>
    </div>
</nav>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanta/0.5.24/vanta.net.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let bgDiv = document.createElement("div");
    bgDiv.style.position = "fixed";
    bgDiv.style.zIndex = "-3";
    bgDiv.style.top = "0";
    bgDiv.style.left = "0";
    bgDiv.style.width = "100vw";
    bgDiv.style.height = "100vh";
    document.body.prepend(bgDiv);
    
    VANTA.NET({
      el: bgDiv,
      mouseControls: true,
      touchControls: true,
      gyroControls: false,
      minHeight: 200.00,
      minWidth: 200.00,
      scale: 1.00,
      scaleMobile: 1.00,
      color: 0xa855f7,
      backgroundColor: 0x0f0a1a,
      points: 15.00,
      maxDistance: 20.00,
      spacing: 16.00,
      showDots: true
    });
});
</script>

