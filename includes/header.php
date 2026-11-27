<?php
declare(strict_types=1);

/**
 * Top Header Component
 * Digunakan bersama di index.php dan admin.php
 *
 * Parameter opsional sebelum include:
 *   $headerTitle    = 'NARUTO STORM CONNECTIONS';
 *   $headerBadge    = 'KOTH';
 *   $headerSubtitle = 'ADMIN'; // opsional
 *   $headerRightHtml = '...';   // opsional slot kanan
 */
$headerTitle = $headerTitle ?? 'NARUTO STORM CONNECTIONS';
$headerBadge = $headerBadge ?? 'KOTH';
$headerSubtitle = $headerSubtitle ?? null;
$headerRightHtml = $headerRightHtml ?? null;
?>
<!-- TOP HEADER COMPONENT CSS -->
<style>
  .top-header {
    background: var(--bg-panel, #393E46);
    border: 1px solid var(--border-subtle, var(--border-color, #929AAB));
    border-radius: 8px;
    padding: 8px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.45);
    box-sizing: border-box;
    width: 100%;
  }

  .brand-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: var(--c-white, #F7F7F7);
    user-select: none;
  }

  .brand-title:hover {
    color: var(--c-light, #EEEEEE);
  }

  .brand-badge {
    background: var(--c-white, #F7F7F7);
    color: var(--bg-main, var(--c-dark, #222831));
    font-weight: 900;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    display: inline-block;
  }

  .brand-text {
    font-weight: 900;
    font-size: 1.05rem;
    letter-spacing: 0.5px;
    color: var(--c-white, #F7F7F7);
  }

  .brand-subtitle-tag {
    background: rgba(2, 132, 199, 0.25);
    border: 1px solid #0284c7;
    color: #7dd3fc;
    font-size: 0.72rem;
    font-weight: 900;
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }

  .top-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
  }
</style>

<!-- TOP HEADER BAR -->
<header class="top-header">
  <div style="display: flex; align-items: center; gap: 10px;">
    <button type="button" class="koth-hamburger-btn" onclick="kothToggleSidebar()" title="Buka Menu Navigasi"
      aria-label="Menu">
      ☰
    </button>
    <a href="index.php" class="brand-title">
      <span class="brand-badge"><?= htmlspecialchars($headerBadge, ENT_QUOTES, 'UTF-8') ?></span>
      <span class="brand-text"><?= htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8') ?></span>
      <?php if (!empty($headerSubtitle)): ?>
        <span class="brand-subtitle-tag"><?= htmlspecialchars($headerSubtitle, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </a>
  </div>
  <?php if (!empty($headerRightHtml)): ?>
    <div class="top-header-right">
      <?= $headerRightHtml ?>
    </div>
  <?php endif; ?>
</header>
