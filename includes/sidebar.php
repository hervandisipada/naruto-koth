<?php
declare(strict_types=1);

/**
 * Sidebar Component with Hamburger Menu
 * Digunakan bersama di index.php dan admin.php
 *
 * Parameter opsional sebelum include:
 *   $sidebarActive = 'home' | 'overlay' | 'admin';
 */
$sidebarActive = $sidebarActive ?? 'home';
$currentRoomKey = $roomKey ?? (function_exists('resolveRoomKey') ? resolveRoomKey() : 'hervandisipada');
?>
<!-- SIDEBAR COMPONENT CSS -->
<style>
  .koth-hamburger-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #f7f7f7;
    cursor: pointer;
    font-size: 1.25rem;
    line-height: 1;
    transition: all 0.18s ease;
    user-select: none;
    flex-shrink: 0;
  }
  .koth-hamburger-btn:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.35);
    transform: scale(1.04);
  }
  .koth-hamburger-btn:active {
    transform: scale(0.96);
  }

  .koth-sidebar-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999990;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s ease, visibility 0.25s ease;
  }
  .koth-sidebar-backdrop.active {
    opacity: 1;
    visibility: visible;
  }

  .koth-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 295px;
    max-width: 84vw;
    background: #1e2530;
    border-right: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 12px 0 35px rgba(0, 0, 0, 0.65);
    z-index: 999995;
    display: flex;
    flex-direction: column;
    transform: translateX(-100%);
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: 'Consolas', 'Courier New', Courier, monospace;
    color: #f7f7f7;
  }
  .koth-sidebar.open {
    transform: translateX(0);
  }

  .koth-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.18);
  }
  .koth-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 900;
    font-size: 1.05rem;
    letter-spacing: 0.5px;
    color: #ffffff;
    text-decoration: none;
  }
  .koth-sidebar-badge {
    background: #f59e0b;
    color: #111827;
    font-size: 0.72rem;
    font-weight: 900;
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: 0.5px;
  }
  .koth-sidebar-close {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 1.4rem;
    cursor: pointer;
    line-height: 1;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.15s ease;
  }
  .koth-sidebar-close:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
  }

  .koth-sidebar-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .koth-sidebar-group-title {
    font-size: 0.72rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 6px 10px 4px;
    margin-top: 6px;
  }

  .koth-sidebar-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 14px;
    border-radius: 8px;
    text-decoration: none;
    color: #e2e8f0;
    font-weight: 700;
    font-size: 0.92rem;
    transition: all 0.18s ease;
    border-left: 3px solid transparent;
    background: rgba(255, 255, 255, 0.03);
  }
  .koth-sidebar-link:hover {
    background: rgba(255, 255, 255, 0.09);
    color: #ffffff;
    transform: translateX(3px);
  }
  .koth-sidebar-link.active {
    background: linear-gradient(90deg, rgba(2, 132, 199, 0.3), rgba(2, 132, 199, 0.08));
    border-left-color: #0284c7;
    color: #38bdf8;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.2);
  }
  .koth-sidebar-link-left {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .koth-sidebar-link-icon {
    font-size: 1.15rem;
    width: 22px;
    text-align: center;
  }
  .koth-sidebar-active-tag {
    font-size: 0.68rem;
    font-weight: 900;
    background: #0284c7;
    color: #ffffff;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.5px;
  }

  .koth-sidebar-footer {
    padding: 14px 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.22);
    font-size: 0.78rem;
    color: #94a3b8;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .koth-sidebar-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 4px 8px;
    border-radius: 6px;
    color: #cbd5e1;
    font-size: 0.74rem;
  }
  .koth-sidebar-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 6px #22c55e;
  }
</style>

<!-- SIDEBAR BACKDROP & DRAWER -->
<div id="koth-sidebar-backdrop" class="koth-sidebar-backdrop" onclick="kothToggleSidebar(false)"></div>

<aside id="koth-sidebar" class="koth-sidebar" aria-label="Menu Navigasi">
  <div class="koth-sidebar-header">
    <a href="index.php" class="koth-sidebar-brand" title="Ke Beranda Pendaftaran">
      <span>👑</span>
      <span>NARUTO KOTH</span>
      <span class="koth-sidebar-badge">PHP</span>
    </a>
    <button type="button" class="koth-sidebar-close" onclick="kothToggleSidebar(false)" title="Tutup Menu">✕</button>
  </div>

  <div class="koth-sidebar-body">
    <div class="koth-sidebar-group-title">NAVIGASI UTAMA</div>

    <!-- 1. PENDAFTARAN FREE -->
    <a href="index.php" class="koth-sidebar-link <?= ($sidebarActive === 'home' || $sidebarActive === 'free') ? 'active' : '' ?>">
      <div class="koth-sidebar-link-left">
        <span class="koth-sidebar-link-icon">🍃</span>
        <span>Daftar Free Request</span>
      </div>
      <?php if ($sidebarActive === 'home' || $sidebarActive === 'free'): ?>
        <span class="koth-sidebar-active-tag">AKTIF</span>
      <?php endif; ?>
    </a>

    <!-- 2. PENDAFTARAN VIP & SROBOT -->
    <a href="vip.php" class="koth-sidebar-link <?= ($sidebarActive === 'vip') ? 'active' : '' ?>">
      <div class="koth-sidebar-link-left">
        <span class="koth-sidebar-link-icon">⭐</span>
        <span>Daftar VIP &amp; SROBOT</span>
      </div>
      <?php if ($sidebarActive === 'vip'): ?>
        <span class="koth-sidebar-active-tag">AKTIF</span>
      <?php endif; ?>
    </a>

    <!-- 3. OVERLAY -->
    <a href="overlay.php" target="_blank" class="koth-sidebar-link <?= ($sidebarActive === 'overlay') ? 'active' : '' ?>" title="Buka Live Overlay untuk OBS / TikTok Live Studio">
      <div class="koth-sidebar-link-left">
        <span class="koth-sidebar-link-icon">📺</span>
        <span>Live Overlay</span>
      </div>
      <span style="font-size: 0.8rem; opacity: 0.7;">↗</span>
    </a>

    <!-- 4. ADMIN -->
    <a href="admin.php" class="koth-sidebar-link <?= ($sidebarActive === 'admin') ? 'active' : '' ?>">
      <div class="koth-sidebar-link-left">
        <span class="koth-sidebar-link-icon">🔒</span>
        <span>Panel Admin</span>
      </div>
      <?php if ($sidebarActive === 'admin'): ?>
        <span class="koth-sidebar-active-tag">AKTIF</span>
      <?php endif; ?>
    </a>
  </div>

  <div class="koth-sidebar-footer">
    <div class="koth-sidebar-pill">
      <span class="koth-sidebar-dot"></span>
      <span>Server: <b>MySQL Native</b></span>
    </div>
    <div style="font-size: 0.7rem; color: #64748b;">
      Room: <b><?= htmlspecialchars($currentRoomKey, ENT_QUOTES, 'UTF-8') ?></b>
    </div>
  </div>
</aside>

<!-- SIDEBAR JAVASCRIPT CONTROLLER -->
<script>
  function kothToggleSidebar(shouldOpen) {
    const sidebar = document.getElementById('koth-sidebar');
    const backdrop = document.getElementById('koth-sidebar-backdrop');
    if (!sidebar || !backdrop) return;

    const isOpen = (typeof shouldOpen === 'boolean') ? shouldOpen : !sidebar.classList.contains('open');
    if (isOpen) {
      sidebar.classList.add('open');
      backdrop.classList.add('active');
      document.body.style.overflow = 'hidden';
    } else {
      sidebar.classList.remove('open');
      backdrop.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  // Keyboard shortcut: Esc to close sidebar
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      kothToggleSidebar(false);
    }
  });
</script>
