<?php
declare(strict_types=1);
require_once __DIR__ . '/api/helpers.php';

$roomKey = resolveRoomKey();
$initialStateJson = '{}';
try {
  $pdo = getDbConnection();
  $dbData = loadStateFromMySql($pdo, $roomKey);
  $initialStateJson = json_encode($dbData['state'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  $initialStateJson = json_encode(getDefaultState(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$appDir = rtrim(dirname($scriptName), '/\\');
$appBaseUrl = ($appDir === '' || $appDir === '.') ? '/' : $appDir . '/';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Pendaftaran Match Arena - Naruto Storm KotH</title>
  <script src="https://unpkg.com/mqtt@4.3.7/dist/mqtt.min.js"></script>

  <style>
    :root {
      --c-white: #F7F7F7;
      --c-light: #EEEEEE;
      --c-dark: #393E46;
      --c-slate: #929AAB;
      --bg-main: #222831;
      --bg-panel: rgba(57, 62, 70, 0.96);
      --border-subtle: #929AAB;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: var(--bg-main);
      color: var(--c-white);
      font-family: 'Consolas', 'Courier New', Courier, monospace;
      padding: 10px;
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .app-wrapper {
      width: 100%;
      max-width: 760px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    /* TICKER NOTE */
    .overlay-ticker {
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 6px;
      padding: 4px 10px;
      display: flex;
      align-items: center;
      gap: 8px;
      overflow: hidden;
      white-space: nowrap;
    }
    .ticker-label {
      color: #f59e0b;
      font-weight: 900;
      font-size: 0.72rem;
      letter-spacing: 0.5px;
      flex-shrink: 0;
    }
    .ticker-marquee-wrap {
      flex: 1;
      overflow: hidden;
      white-space: nowrap;
    }
    .ticker-content {
      color: #ffffff;
      font-weight: 700;
      font-size: 0.85rem;
      white-space: nowrap;
      display: inline-block;
    }

    /* HUB PENDAFTARAN UTAMA */
    .hub-card {
      background: var(--bg-panel);
      border: 1px solid var(--border-subtle);
      border-radius: 12px;
      padding: 24px 18px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .hub-header-icon {
      font-size: 42px;
      line-height: 1;
      margin-bottom: 2px;
    }

    .hub-title {
      font-size: 1.35rem;
      font-weight: 900;
      letter-spacing: 0.8px;
      color: #ffffff;
      text-transform: uppercase;
    }

    .hub-subtitle {
      font-size: 0.85rem;
      color: var(--c-slate);
      line-height: 1.4;
      max-width: 520px;
      margin: 0 auto;
    }

    .hub-mode-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(0, 0, 0, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 5px 14px;
      border-radius: 20px;
      font-size: 0.76rem;
      color: #e2e8f0;
      margin: 0 auto;
    }
    .hub-mode-pill b {
      color: #38bdf8;
    }

    /* TOMBOL PILIHAN PENDAFTARAN */
    .hub-buttons-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 6px;
    }

    .btn-hub-option {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      padding: 16px 18px;
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      text-align: left;
      user-select: none;
    }
    .btn-hub-option:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
    }
    .btn-hub-option:active {
      transform: translateY(0);
    }

    .btn-hub-option.opt-free {
      background: linear-gradient(135deg, rgba(34, 197, 94, 0.18), rgba(16, 185, 129, 0.08));
      border-color: #22c55e;
      color: #ffffff;
    }
    .btn-hub-option.opt-free:hover {
      background: linear-gradient(135deg, rgba(34, 197, 94, 0.28), rgba(16, 185, 129, 0.16));
      border-color: #4ade80;
    }

    .btn-hub-option.opt-vip {
      background: linear-gradient(135deg, rgba(234, 179, 8, 0.18), rgba(202, 138, 4, 0.08));
      border-color: #eab308;
      color: #ffffff;
    }
    .btn-hub-option.opt-vip:hover {
      background: linear-gradient(135deg, rgba(234, 179, 8, 0.28), rgba(202, 138, 4, 0.16));
      border-color: #fde047;
    }

    .btn-hub-option.opt-srobot {
      background: linear-gradient(135deg, rgba(168, 85, 247, 0.22), rgba(147, 51, 234, 0.1));
      border-color: #a855f7;
      color: #ffffff;
    }
    .btn-hub-option.opt-srobot:hover {
      background: linear-gradient(135deg, rgba(168, 85, 247, 0.32), rgba(147, 51, 234, 0.2));
      border-color: #c084fc;
    }

    .btn-hub-icon {
      font-size: 32px;
      flex-shrink: 0;
      line-height: 1;
    }
    .btn-hub-body {
      flex: 1;
      min-width: 0;
    }
    .btn-hub-title {
      font-size: 1.12rem;
      font-weight: 900;
      letter-spacing: 0.5px;
      margin-bottom: 3px;
    }
    .btn-hub-desc {
      font-size: 0.76rem;
      color: var(--c-light);
      opacity: 0.9;
    }
    .btn-hub-tag {
      font-size: 0.70rem;
      font-weight: 900;
      padding: 4px 10px;
      border-radius: 6px;
      letter-spacing: 0.5px;
      flex-shrink: 0;
      text-transform: uppercase;
    }
    .btn-hub-tag.tag-free {
      background: #22c55e;
      color: #052e16;
    }
    .btn-hub-tag.tag-vip {
      background: #eab308;
      color: #422006;
    }
    .btn-hub-tag.tag-srobot {
      background: #a855f7;
      color: #ffffff;
    }

    /* MODAL BASE */
    .koth-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.85);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: 99999;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 12px;
      overflow-y: auto;
    }
    .koth-modal-box {
      background: #1e2530;
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 12px;
      width: 100%;
      max-width: 580px;
      box-shadow: 0 16px 45px rgba(0, 0, 0, 0.8);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      max-height: 90vh;
      animation: modalZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes modalZoomIn {
      from { opacity: 0; transform: scale(0.94); }
      to { opacity: 1; transform: scale(1); }
    }

    .koth-modal-header {
      padding: 14px 18px;
      background: rgba(0, 0, 0, 0.25);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .koth-modal-title {
      font-size: 1.05rem;
      font-weight: 900;
      letter-spacing: 0.5px;
      color: #ffffff;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .koth-modal-close {
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
    .koth-modal-close:hover {
      color: #ffffff;
      background: rgba(255, 255, 255, 0.1);
    }

    /* STEP BREADCRUMB INDICATOR */
    .step-indicator-bar {
      display: flex;
      background: rgba(0, 0, 0, 0.35);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding: 8px 12px;
      gap: 6px;
    }
    .step-crumb {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 6px 4px;
      border-radius: 6px;
      font-size: 0.72rem;
      font-weight: 800;
      color: #64748b;
      background: rgba(255, 255, 255, 0.03);
      transition: all 0.18s ease;
    }
    .step-crumb.active {
      color: #ffffff;
      background: rgba(34, 197, 94, 0.25);
      border: 1px solid rgba(34, 197, 94, 0.5);
    }
    .step-crumb.done {
      color: #86efac;
      background: rgba(34, 197, 94, 0.12);
    }
    .step-num {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.68rem;
    }
    .step-crumb.active .step-num {
      background: #22c55e;
      color: #000000;
      font-weight: 900;
    }

    .koth-modal-body {
      padding: 16px 18px;
      overflow-y: auto;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .koth-modal-footer {
      padding: 12px 18px;
      background: rgba(0, 0, 0, 0.25);
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }

    /* FORM ELEMENTS */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .form-label {
      font-size: 0.82rem;
      font-weight: 800;
      color: #e2e8f0;
      display: flex;
      justify-content: space-between;
    }
    .form-control {
      width: 100%;
      background: rgba(0, 0, 0, 0.45);
      border: 1.5px solid rgba(255, 255, 255, 0.2);
      border-radius: 8px;
      padding: 11px 14px;
      color: #ffffff;
      font-family: inherit;
      font-size: 0.95rem;
      font-weight: 700;
      outline: none;
      transition: all 0.18s ease;
    }
    .form-control:focus {
      border-color: #22c55e;
      box-shadow: 0 0 10px rgba(34, 197, 94, 0.25);
      background: rgba(0, 0, 0, 0.6);
    }

    /* QUICK ALPHABET BAR */
    .alphabet-filter-bar {
      display: flex;
      gap: 4px;
      overflow-x: auto;
      padding: 4px 2px 8px;
      scrollbar-width: thin;
    }
    .btn-alpha {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #cbd5e1;
      font-size: 0.72rem;
      font-weight: 800;
      padding: 4px 8px;
      border-radius: 4px;
      cursor: pointer;
      user-select: none;
      flex-shrink: 0;
    }
    .btn-alpha:hover {
      background: rgba(255, 255, 255, 0.18);
      color: #ffffff;
    }
    .btn-alpha.active {
      background: #22c55e;
      color: #052e16;
      border-color: #22c55e;
    }

    /* CHARACTER CARDS GRID (A-Z) */
    .char-grid-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 8px;
      max-height: 280px;
      overflow-y: auto;
      padding: 4px 2px;
    }
    .char-card-item {
      background: rgba(255, 255, 255, 0.04);
      border: 1.5px solid rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      padding: 9px 10px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 4px;
      transition: all 0.15s ease;
      user-select: none;
    }
    .char-card-item:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(255, 255, 255, 0.3);
      transform: translateY(-1px);
    }
    .char-card-item.selected {
      background: rgba(34, 197, 94, 0.2);
      border-color: #22c55e;
      box-shadow: 0 0 10px rgba(34, 197, 94, 0.35);
    }
    .char-card-name {
      font-size: 0.80rem;
      font-weight: 800;
      color: #f8fafc;
      line-height: 1.25;
      word-break: break-word;
    }
    .char-card-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.65rem;
      color: #94a3b8;
    }
    .char-ulti-badge {
      background: rgba(234, 179, 8, 0.2);
      color: #fde047;
      padding: 1px 5px;
      border-radius: 3px;
      font-weight: 800;
    }
    .char-card-item.disabled {
      cursor: not-allowed;
      opacity: 0.65;
    }
    .char-card-item.disabled:hover {
      transform: none;
      box-shadow: none;
    }
    .char-card-item.banned {
      border-color: rgba(239, 68, 68, 0.45);
      background: rgba(239, 68, 68, 0.08);
      filter: grayscale(60%);
    }
    .char-card-item.banned:hover {
      border-color: #ef4444;
      background: rgba(239, 68, 68, 0.16);
    }
    .char-card-item.vip-only {
      border-color: rgba(245, 158, 11, 0.45);
      background: rgba(245, 158, 11, 0.08);
    }
    .char-card-item.vip-only:hover {
      border-color: #f59e0b;
      background: rgba(245, 158, 11, 0.16);
    }
    .badge-status-banned {
      font-size: 0.65rem;
      font-weight: 800;
      color: #ef4444;
      background: rgba(239, 68, 68, 0.2);
      border: 1px solid rgba(239, 68, 68, 0.45);
      padding: 1px 5px;
      border-radius: 3px;
    }
    .badge-status-vip {
      font-size: 0.65rem;
      font-weight: 800;
      color: #f59e0b;
      background: rgba(245, 158, 11, 0.2);
      border: 1px solid rgba(245, 158, 11, 0.45);
      padding: 1px 5px;
      border-radius: 3px;
    }

    /* ULTI CARDS */
    .ulti-options-grid {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    /* SLOT SELECTOR BAR & TABS */
    .char-slot-tabs-bar {
      display: flex;
      gap: 6px;
      margin-bottom: 8px;
      overflow-x: auto;
      padding-bottom: 2px;
    }
    .char-slot-tab {
      flex: 1;
      min-width: 100px;
      background: rgba(0, 0, 0, 0.4);
      border: 1.5px solid rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      padding: 6px 10px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      gap: 2px;
      text-align: left;
      transition: all 0.15s ease;
      user-select: none;
    }
    .char-slot-tab:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.3);
    }
    .char-slot-tab.active {
      background: rgba(34, 197, 94, 0.18);
      border-color: #22c55e;
      box-shadow: 0 0 10px rgba(34, 197, 94, 0.3);
    }
    .char-slot-tab.filled {
      border-color: #22c55e;
    }
    .char-slot-tab.filled.active {
      border-color: #4ade80;
    }
    .char-slot-badge {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #94a3b8;
    }
    .char-slot-tab.active .char-slot-badge {
      color: #4ade80;
    }
    .char-slot-name {
      font-size: 0.78rem;
      font-weight: 800;
      color: #f8fafc;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .slot-ulti-card {
      background: rgba(0, 0, 0, 0.35);
      border: 1.5px solid rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 8px;
    }
    .ulti-card-item {
      background: rgba(0, 0, 0, 0.35);
      border: 1.5px solid rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      padding: 12px 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      transition: all 0.15s ease;
      user-select: none;
    }
    .ulti-card-item:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.3);
    }
    .ulti-card-item.selected {
      background: rgba(234, 179, 8, 0.15);
      border-color: #facc15;
      box-shadow: 0 0 8px rgba(250, 204, 21, 0.25);
    }
    .ulti-card-name {
      font-size: 0.88rem;
      font-weight: 800;
      color: #ffffff;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* BUTTONS */
    .btn-step-nav {
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 0.86rem;
      font-weight: 800;
      cursor: pointer;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      user-select: none;
      transition: all 0.15s ease;
    }
    .btn-step-prev {
      background: rgba(255, 255, 255, 0.1);
      color: #cbd5e1;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .btn-step-prev:hover {
      background: rgba(255, 255, 255, 0.18);
      color: #ffffff;
    }
    .btn-step-next {
      background: #22c55e;
      color: #052e16;
      font-weight: 900;
      margin-left: auto;
    }
    .btn-step-next:hover {
      background: #4ade80;
    }
    .btn-step-submit {
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color: #ffffff;
      font-weight: 900;
      padding: 11px 22px;
      font-size: 0.92rem;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      margin-left: auto;
      box-shadow: 0 4px 14px rgba(34, 197, 94, 0.4);
    }
    .btn-step-submit:hover {
      background: linear-gradient(135deg, #4ade80, #22c55e);
    }

    /* SUMMARY BOX */
    .reg-summary-card {
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      font-size: 0.84rem;
    }
    .summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .summary-row-label {
      color: var(--c-slate);
    }
    .summary-row-val {
      font-weight: 800;
      color: #ffffff;
      text-align: right;
    }
  </style>
</head>

<body>
  <?php
  $sidebarActive = 'free';
  include __DIR__ . '/includes/sidebar.php';
  ?>

  <div class="app-wrapper">

    <!-- TOP HEADER BAR -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- TICKER NOTE MARQUEE -->
    <div class="overlay-ticker" id="ticker-wrap" style="display: none;">
      <span class="ticker-label">NOTE:</span>
      <div class="ticker-marquee-wrap" id="ticker-marquee-wrap">
        <span class="ticker-content" id="ticker-text">-</span>
      </div>
    </div>

    <!-- MAIN REGISTRATION HUB CARD -->
    <main class="hub-card">
      <div class="hub-header-icon">🎮</div>
      <h1 class="hub-title">PENDAFTARAN MATCH NARUTO</h1>
      <p class="hub-subtitle">
        Pilih jalur pendaftaran di bawah ini untuk bertanding di Live Stream TikTok!
      </p>

      <div class="hub-mode-pill" id="hub-mode-pill">
        <span>Mode Pertandingan:</span> <b id="hub-mode-text">1vs1 (SOLO)</b>
      </div>

      <!-- 3 TOMBOL UTAMA -->
      <div class="hub-buttons-grid">
        <!-- 1. DAFTAR FREE REQUEST -->
        <button type="button" class="btn-hub-option opt-free" onclick="openFreeModal()">
          <div class="btn-hub-icon">🍃</div>
          <div class="btn-hub-body">
            <div class="btn-hub-title">DAFTAR FREE REQUEST</div>
            <div class="btn-hub-desc">1 Match • Syarat Follow Host @herzg1209 • Antrian Normal</div>
          </div>
          <div class="btn-hub-tag tag-free">GRATIS</div>
        </button>

        <!-- 2. DAFTAR VIP -->
        <a href="vip.php?tier=VIP" class="btn-hub-option opt-vip">
          <div class="btn-hub-icon">⭐</div>
          <div class="btn-hub-body">
            <div class="btn-hub-title">DAFTAR VIP</div>
            <div class="btn-hub-desc">Prioritas Match • Bebas Pilih Match • 5 Koin / Rp 1K</div>
          </div>
          <div class="btn-hub-tag tag-vip">PRIORITAS</div>
        </a>

        <!-- 3. DAFTAR SROBOT -->
        <a href="vip.php?tier=SROBOT" class="btn-hub-option opt-srobot">
          <div class="btn-hub-icon">⚡</div>
          <div class="btn-hub-body">
            <div class="btn-hub-title">DAFTAR SROBOT</div>
            <div class="btn-hub-desc">Potong Antrian Paling Depan • Super Cepat • 30 Koin / Rp 5K</div>
          </div>
          <div class="btn-hub-tag tag-srobot">SUPER FAST</div>
        </a>
      </div>

      <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 4px;">
        💡 Pendaftaran berbayar (VIP & SROBOT) dipisahkan ke <a href="vip.php" style="color: #fde047; font-weight:800; text-decoration: underline;">Halaman VIP & SROBOT</a>
      </div>
    </main>
  </div>

  <!-- ==================================================== -->
  <!-- MODAL 3 LANGKAH: PENDAFTARAN FREE REQUEST -->
  <!-- ==================================================== -->
  <div id="modal-free-reg" class="koth-modal-backdrop" onclick="if(event.target === this) closeFreeModal()">
    <div class="koth-modal-box">
      <!-- HEADER -->
      <div class="koth-modal-header">
        <div class="koth-modal-title">
          <span>🍃</span>
          <span>PENDAFTARAN FREE REQUEST</span>
        </div>
        <button type="button" class="koth-modal-close" onclick="closeFreeModal()" title="Tutup">✕</button>
      </div>

      <!-- STEP BREADCRUMBS (3 LANGKAH) -->
      <div class="step-indicator-bar">
        <div class="step-crumb active" id="step-crumb-1">
          <span class="step-num">1</span>
          <span>Nama TikTok</span>
        </div>
        <div class="step-crumb" id="step-crumb-2">
          <span class="step-num">2</span>
          <span>Pilih Karakter</span>
        </div>
        <div class="step-crumb" id="step-crumb-3">
          <span class="step-num">3</span>
          <span>Pilih Ulti</span>
        </div>
      </div>

      <!-- BODY CONTENT -->
      <div class="koth-modal-body">

        <!-- LANGKAH 1: NAMA TIKTOK -->
        <div id="free-step-1" class="step-panel">
          <div class="form-group">
            <label class="form-label" for="free-name-input">
              <span>Masukkan Nama TikTok / In-Game Name:</span>
            </label>
            <input type="text" id="free-name-input" class="form-control"
              placeholder="Contoh: @narutofan atau Herz..." autocomplete="off" oninput="onFreeNameInput()">
            <div id="free-name-warning" style="display:none; font-size:0.75rem; font-weight:800; color:#fca5a5; background:rgba(239,68,68,0.2); border:1px solid #ef4444; padding:6px 10px; border-radius:6px; margin-top:4px;">
            </div>
            <p style="font-size:0.74rem; color:var(--c-slate); margin-top:6px; line-height:1.4;">
              *Pastikan nama sesuai dengan akun TikTok yang dipakai saat menonton live stream.
            </p>
          </div>
        </div>

        <!-- LANGKAH 2: PILIH KARAKTER (URUT ABJAD A - Z) -->
        <div id="free-step-2" class="step-panel" style="display: none;">
          <!-- Mode Banner -->
          <div id="free-mode-banner" style="display: none; background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; border-radius: 8px; padding: 6px 12px; margin-bottom: 8px; font-size: 0.76rem; font-weight: 800; color: #86efac; align-items: center; justify-content: space-between;">
            <span id="free-mode-banner-text">⚔️ Mode DUO Aktif: Pilih 2 Karakter</span>
          </div>

          <!-- Slot Tabs Bar for DUO / TIM -->
          <div id="free-slot-tabs" class="char-slot-tabs-bar" style="display: none;"></div>

          <!-- TIM Flexible Toggle Bar -->
          <div id="free-tim-flex-bar" style="display: none; justify-content: space-between; align-items: center; background: rgba(168,85,247,0.12); border: 1px solid rgba(168,85,247,0.3); border-radius: 6px; padding: 6px 10px; margin-bottom: 8px;">
            <span style="font-size: 0.74rem; font-weight: 700; color: #d8b4fe;">💡 Mode TIM Fleksibel: Kamu bisa pilih 2 atau 3 karakter</span>
            <button type="button" id="btn-free-tim-toggle-slot3" class="btn-step-nav" style="padding: 4px 10px; font-size: 0.72rem; background: rgba(255,255,255,0.1); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.2);" onclick="toggleFreeTimSlot3()">
              Hanya Pakai 2 Karakter
            </button>
          </div>

          <div class="form-group">
            <label class="form-label">
              <span id="free-char-label">Pilih Karakter (Urut Abjad A - Z):</span>
              <span id="free-selected-char-badge" style="color: #4ade80; font-weight:800; font-size:0.75rem;"></span>
            </label>
            <input type="text" id="free-char-search" class="form-control"
              placeholder="🔍 Cari nama karakter atau ulti (misal: Sasuke, kilat, malam...)" autocomplete="off"
              oninput="filterFreeChars()">
          </div>

          <!-- Quick Alphabet Filter -->
          <div class="alphabet-filter-bar" id="free-alpha-bar"></div>

          <!-- Grid Karakter A-Z -->
          <div class="char-grid-container" id="free-char-grid"></div>
        </div>

        <!-- LANGKAH 3: PILIH ULTI (JIKA LEBIH DARI 1) & KONFIRMASI -->
        <div id="free-step-3" class="step-panel" style="display: none;">
          <!-- Multi-slot ulti container -->
          <div id="free-slots-ulti-container">
            <!-- Rendered dynamically per slot -->
          </div>

          <div id="free-multi-ulti-box" style="display: none;">
            <div style="font-size:0.84rem; font-weight:800; color:#fde047; margin-bottom:8px;">
              ⚡ Karakter terpilih memiliki lebih dari 1 Ultimate Jutsu. Pilih salah satu:
            </div>
            <div class="ulti-options-grid" id="free-ulti-options"></div>
          </div>

          <div id="free-single-ulti-box" style="display: none; background: rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:10px 14px; margin-bottom:10px;">
            <div style="font-size:0.80rem; color:#cbd5e1;">⚡ Ultimate Bawaan Karakter:</div>
            <div id="free-single-ulti-text" style="font-size:0.95rem; font-weight:900; color:#facc15; margin-top:2px;"></div>
            <div style="font-size:0.70rem; color:#94a3b8; margin-top:2px;">*Karakter ini hanya memiliki 1 Ultimate bawaan.</div>
          </div>

          <!-- RINGKASAN DATA -->
          <div class="reg-summary-card">
            <div style="font-size:0.74rem; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Ringkasan Pendaftaran Free:</div>
            <div class="summary-row">
              <span class="summary-row-label">Nama TikTok:</span>
              <span class="summary-row-val" id="sum-free-name">-</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Mode Match:</span>
              <span class="summary-row-val" id="sum-free-mode" style="color: #38bdf8;">1v1</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Karakter:</span>
              <span class="summary-row-val" id="sum-free-char">-</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Ultimate Jutsu:</span>
              <span class="summary-row-val" id="sum-free-ulti" style="color: #facc15;">-</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Jumlah Match:</span>
              <span class="summary-row-val" style="color: #4ade80;">1 Match (Free)</span>
            </div>
          </div>

          <div style="background: rgba(34, 197, 94, 0.1); border:1px solid rgba(34, 197, 94, 0.3); border-radius:6px; padding:8px 12px; font-size:0.74rem; color:#86efac; line-height:1.4;">
            ✅ <b>Syarat Free:</b> Wajib follow akun TikTok Host <b>@herzg1209</b> dan stay di live stream!
          </div>
        </div>

      </div>

      <!-- FOOTER NAVIGATION -->
      <div class="koth-modal-footer">
        <button type="button" class="btn-step-nav btn-step-prev" id="btn-free-prev" onclick="goToFreeStep(freeCurrentStep - 1)" style="display: none;">
          ⬅ Kembali
        </button>
        <button type="button" class="btn-step-nav btn-step-next" id="btn-free-next" onclick="goToFreeStep(freeCurrentStep + 1)">
          Lanjut ke Pilih Karakter ➜
        </button>
        <button type="button" class="btn-step-submit" id="btn-free-submit" onclick="submitFreeRegistration()" style="display: none;">
          ✅ KIRIM PENDAFTARAN FREE
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL SUKSES PENDAFTARAN FREE -->
  <div id="modal-free-success" class="koth-modal-backdrop" onclick="if(event.target === this) closeFreeSuccessModal()">
    <div class="koth-modal-box" style="max-width: 440px; text-align: center; padding: 22px 18px;">
      <div style="font-size: 42px; margin-bottom: 6px;">🎉</div>
      <h3 style="font-size: 1.2rem; font-weight: 900; color: #4ade80; margin-bottom: 4px;">
        PENDAFTARAN FREE BERHASIL!
      </h3>
      <p style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 14px;">
        Nama kamu sudah berhasil masuk ke antrian live match!
      </p>

      <div class="reg-summary-card" style="text-align: left; margin-bottom: 14px;">
        <div class="summary-row">
          <span class="summary-row-label">Nama TikTok:</span>
          <span class="summary-row-val" id="suc-free-name">-</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Posisi Antrian:</span>
          <span class="summary-row-val" id="suc-free-pos" style="color: #4ade80;">#1</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Karakter:</span>
          <span class="summary-row-val" id="suc-free-char">-</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Ultimate:</span>
          <span class="summary-row-val" id="suc-free-ulti" style="color: #facc15;">-</span>
        </div>
      </div>

      <div style="font-size: 0.76rem; color: #fde047; margin-bottom: 16px; background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 6px;">
        ⚠️ Wajib follow TikTok <b>@herzg1209</b> & jangan tinggalkan live stream agar tidak terlewat!
      </div>

      <button type="button" class="btn-step-submit" style="width: 100%; margin: 0;" onclick="closeFreeSuccessModal()">
        OKE, SIAP BERTANDING!
      </button>
    </div>
  </div>

  <!-- INCLUDE MASTER KARAKTER 163 NARUTO -->
  <?php include __DIR__ . '/includes/characters.php'; ?>

  <!-- CONTROLLER SCRIPT -->
  <script>
    // State & Sync
    let state = <?= $initialStateJson ?>;
    let _lastServerJson = JSON.stringify(state);
    let cloudMqttClient = null;
    const LOCAL_CLIENT_ID = 'reg_free_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
    const STREAM_KEY = <?= json_encode($roomKey) ?>;
    let streamKey = STREAM_KEY;
    const MQTT_TOPIC = `koth/naruto/${streamKey}`;
    let kothSyncChannel = null;
    let lastLocalSaveAt = 0;
    let pendingSaves = 0;

    function getAppBaseUrl() {
      let b = <?= json_encode($appBaseUrl) ?>;
      if (!b.endsWith('/')) b += '/';
      return b;
    }

    function getStateApiUrl() {
      return `${getAppBaseUrl()}api/state.php?room=${encodeURIComponent(streamKey)}`;
    }

    // Modal state
    let freeCurrentStep = 1;
    let freeSlots = [
      { char: null, ulti: '' },
      { char: null, ulti: '' },
      { char: null, ulti: '' }
    ];
    let freeActiveSlot = 0; // 0, 1, 2
    let freeTimCount = 3; // 2 or 3 in TIM mode
    let freeSelectedChar = null; // backward compatibility
    let freeSelectedUlti = '';
    let freeAlphaFilter = 'ALL';

    function openFreeModal() {
      freeCurrentStep = 1;
      freeSlots = [
        { char: null, ulti: '' },
        { char: null, ulti: '' },
        { char: null, ulti: '' }
      ];
      freeActiveSlot = 0;
      freeTimCount = 3;
      freeSelectedChar = null;
      freeSelectedUlti = '';
      document.getElementById('free-name-input').value = '';
      document.getElementById('free-name-warning').style.display = 'none';
      document.getElementById('free-char-search').value = '';
      freeAlphaFilter = 'ALL';

      renderFreeSlotTabs();
      buildFreeAlphabetBar();
      renderFreeCharGrid();
      goToFreeStep(1);
      document.getElementById('modal-free-reg').style.display = 'flex';
      setTimeout(() => {
        const inp = document.getElementById('free-name-input');
        if (inp) inp.focus();
      }, 150);
    }

    function closeFreeModal() {
      document.getElementById('modal-free-reg').style.display = 'none';
    }

    function closeFreeSuccessModal() {
      document.getElementById('modal-free-success').style.display = 'none';
    }

    function toggleFreeTimSlot3() {
      if (freeTimCount === 3) {
        freeTimCount = 2;
        freeSlots[2] = { char: null, ulti: '' };
        if (freeActiveSlot === 2) freeActiveSlot = 1;
      } else {
        freeTimCount = 3;
      }
      renderFreeSlotTabs();
      renderFreeCharGrid();
    }

    function selectFreeSlot(idx) {
      freeActiveSlot = idx;
      renderFreeSlotTabs();
      renderFreeCharGrid();
    }

    function renderFreeSlotTabs() {
      const activeMode = (state.masterMode || '1v1').toUpperCase();
      const container = document.getElementById('free-slot-tabs');
      const flexBar = document.getElementById('free-tim-flex-bar');
      const flexBtn = document.getElementById('btn-free-tim-toggle-slot3');
      const banner = document.getElementById('free-mode-banner');
      const bannerText = document.getElementById('free-mode-banner-text');
      const label = document.getElementById('free-char-label');

      if (!container) return;

      if (activeMode === '1v1' || !['DUO', 'TIM'].includes(activeMode)) {
        if (banner) banner.style.display = 'none';
        container.style.display = 'none';
        if (flexBar) flexBar.style.display = 'none';
        if (label) label.innerText = 'Pilih Karakter (Urut Abjad A - Z):';
        return;
      }

      if (banner && bannerText) {
        banner.style.display = 'flex';
        bannerText.innerText = (activeMode === 'DUO')
          ? '⚔️ Mode DUO Aktif: Pilih 2 Karakter (Leader & Support)'
          : '👑 Mode TIM Aktif: Fleksibel, pilih 2 atau 3 Karakter';
      }

      container.style.display = 'flex';
      if (label) label.innerText = `Pilih Karakter untuk Slot ${freeActiveSlot + 1}:`;

      if (activeMode === 'TIM') {
        if (flexBar) flexBar.style.display = 'flex';
        if (flexBtn) {
          flexBtn.innerText = (freeTimCount === 3) ? '👥 Gunakan 2 Karakter Saja' : '➕ Tambah Karakter 3';
        }
      } else {
        if (flexBar) flexBar.style.display = 'none';
      }

      const totalSlots = (activeMode === 'DUO') ? 2 : freeTimCount;
      const slotDefs = [
        { label: 'Slot 1: Leader', opt: false },
        { label: (activeMode === 'DUO') ? 'Slot 2: Support' : 'Slot 2: Support 1', opt: false },
        { label: 'Slot 3: Support 2', opt: true }
      ];

      container.innerHTML = slotDefs.slice(0, totalSlots).map((def, idx) => {
        const slot = freeSlots[idx];
        const isActive = (freeActiveSlot === idx);
        const isFilled = Boolean(slot && slot.char);
        const charName = isFilled ? slot.char.name : (def.opt ? '(Opsional / Kosong)' : '(Belum Dipilih)');
        let cls = 'char-slot-tab';
        if (isActive) cls += ' active';
        if (isFilled) cls += ' filled';

        return `
          <button type="button" class="${cls}" onclick="selectFreeSlot(${idx})">
            <span class="char-slot-badge">${def.label}</span>
            <span class="char-slot-name">${escapeHtml(charName)}</span>
          </button>
        `;
      }).join('');
    }

    function goToFreeStep(step) {
      if (step === 2) {
        const nameVal = (document.getElementById('free-name-input').value || '').trim();
        if (!nameVal) {
          showFreeWarning('Silakan masukkan nama TikTok kamu terlebih dahulu!');
          return;
        }
        const dup = checkDuplicatePlayer(nameVal, state);
        if (dup) {
          showFreeWarning(dup.message);
          return;
        }
        hideFreeWarning();
        renderFreeSlotTabs();
        renderFreeCharGrid();
      }

      if (step === 3) {
        const activeMode = (state.masterMode || '1v1').toUpperCase();
        if (activeMode === '1v1') {
          if (!freeSlots[0].char) {
            alert('Silakan pilih salah satu karakter Naruto terlebih dahulu!');
            return;
          }
        } else if (activeMode === 'DUO') {
          if (!freeSlots[0].char || !freeSlots[1].char) {
            alert('Mode DUO membutuhkan 2 karakter (Slot 1: Leader & Slot 2: Support)!');
            return;
          }
        } else if (activeMode === 'TIM') {
          if (!freeSlots[0].char || !freeSlots[1].char) {
            alert('Mode TIM membutuhkan minimal 2 karakter (Slot 1: Leader & Slot 2: Support 1)!');
            return;
          }
        }
        setupFreeStep3();
      }

      freeCurrentStep = step;

      // Update step indicator
      for (let i = 1; i <= 3; i++) {
        const crumb = document.getElementById(`step-crumb-${i}`);
        if (crumb) {
          crumb.classList.toggle('active', i === step);
          crumb.classList.toggle('done', i < step);
        }
        const pnl = document.getElementById(`free-step-${i}`);
        if (pnl) pnl.style.display = (i === step) ? 'block' : 'none';
      }

      // Update buttons
      const btnPrev = document.getElementById('btn-free-prev');
      const btnNext = document.getElementById('btn-free-next');
      const btnSubmit = document.getElementById('btn-free-submit');

      btnPrev.style.display = (step > 1) ? 'inline-flex' : 'none';

      if (step === 1) {
        btnNext.style.display = 'inline-flex';
        btnNext.innerText = 'Lanjut: Pilih Karakter ➜';
        btnSubmit.style.display = 'none';
      } else if (step === 2) {
        btnNext.style.display = 'inline-flex';
        btnNext.innerText = 'Lanjut: Pilih Ulti ➜';
        btnSubmit.style.display = 'none';
      } else if (step === 3) {
        btnNext.style.display = 'none';
        btnSubmit.style.display = 'inline-flex';
      }
    }

    function showFreeWarning(msg) {
      const el = document.getElementById('free-name-warning');
      if (el) {
        el.innerText = msg;
        el.style.display = 'block';
      }
    }
    function hideFreeWarning() {
      const el = document.getElementById('free-name-warning');
      if (el) el.style.display = 'none';
    }
    function onFreeNameInput() {
      hideFreeWarning();
    }

    // Alphabet bar
    const ALPHABET_LETTERS = ['ALL', 'A', 'B', 'C', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'Y', 'Z'];
    function buildFreeAlphabetBar() {
      const bar = document.getElementById('free-alpha-bar');
      if (!bar) return;
      bar.innerHTML = ALPHABET_LETTERS.map(letter => `
        <button type="button" class="btn-alpha ${letter === freeAlphaFilter ? 'active' : ''}" onclick="setFreeAlphaFilter('${letter}')">
          ${letter}
        </button>
      `).join('');
    }

    function setFreeAlphaFilter(letter) {
      freeAlphaFilter = letter;
      buildFreeAlphabetBar();
      renderFreeCharGrid();
    }

    function filterFreeChars() {
      renderFreeCharGrid();
    }

    function renderFreeCharGrid() {
      const container = document.getElementById('free-char-grid');
      if (!container) return;
      const q = (document.getElementById('free-char-search').value || '').trim().toLowerCase();

      let list = ALPHABETICAL_CHARS.slice();
      if (freeAlphaFilter !== 'ALL') {
        list = list.filter(c => c.name.toUpperCase().startsWith(freeAlphaFilter));
      }
      if (q) {
        list = list.filter(c => {
          const matchName = c.name.toLowerCase().includes(q) || (typeof normCharName === 'function' && normCharName(c.name).includes(normCharName(q)));
          const matchShort = c.short1v1 && c.short1v1.toLowerCase().includes(q);
          const matchTeam = c.shortTeam && c.shortTeam.toLowerCase().includes(q);
          const matchedUlti = Array.isArray(c.ultis) ? c.ultis.find(u => u.toLowerCase().includes(q) || (typeof normCharName === 'function' && normCharName(u).includes(normCharName(q)))) : null;
          c._matchedUlti = matchedUlti || null;
          return matchName || matchShort || matchTeam || Boolean(matchedUlti);
        });
      } else {
        list.forEach(c => c._matchedUlti = null);
      }

      if (list.length === 0) {
        container.innerHTML = `<div style="grid-column: 1 / -1; padding: 20px; text-align: center; color: #94a3b8; font-size: 0.85rem;">Tidak ada karakter atau jurus ulti yang cocok dengan "${escapeHtml(q)}".</div>`;
        return;
      }

      const activeMode = (state.masterMode || '1v1').toUpperCase();
      const totalSlots = (activeMode === '1v1') ? 1 : (activeMode === 'DUO' ? 2 : freeTimCount);
      const curSlotChar = freeSlots[freeActiveSlot] ? freeSlots[freeActiveSlot].char : null;

      const badge = document.getElementById('free-selected-char-badge');
      if (badge) {
        const filled = freeSlots.slice(0, totalSlots).filter(s => s && s.char).length;
        badge.innerText = `${filled}/${totalSlots} Terpilih`;
      }

      container.innerHTML = list.map(c => {
        const isSel = curSlotChar && curSlotChar.id === c.id;
        const otherSlotIdx = freeSlots.findIndex((s, idx) => idx !== freeActiveSlot && s.char && s.char.id === c.id);
        const ultiCount = (c.ultis && c.ultis.length > 0) ? c.ultis.length : 1;
        const safeUltiArg = c._matchedUlti ? escapeHtml(c._matchedUlti).replace(/'/g, "\\'") : '';
        const matchTagHtml = c._matchedUlti ? `
          <div style="font-size:0.68rem; font-weight:800; color:#facc15; background:rgba(234, 179, 8, 0.15); border:1px solid rgba(234, 179, 8, 0.35); padding:2px 6px; border-radius:4px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="Ulti Cocok: ${escapeHtml(c._matchedUlti)}">
            ⚡ ${escapeHtml(c._matchedUlti)}
          </div>
        ` : '';

        const banned = typeof isCharBanned === 'function' && isCharBanned(c);
        const vipOnly = !banned && (typeof isCharVipExclusive === 'function' && isCharVipExclusive(c));

        let cardClass = 'char-card-item';
        let clickAttr = `onclick="selectFreeChar(${c.id}, '${safeUltiArg}')"`;
        let statusBadgeHtml = '';

        if (banned) {
          cardClass += ' disabled banned';
          clickAttr = `onclick="showBannedAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-banned">🚫 BANNED</span>`;
        } else if (vipOnly) {
          cardClass += ' disabled vip-only';
          clickAttr = `onclick="showVipOnlyAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-vip">🔒 KHUSUS VIP</span>`;
        } else if (isSel) {
          cardClass += ' selected';
        }

        let metaRight = statusBadgeHtml;
        if (isSel) {
          metaRight = `<span style="color:#4ade80; font-weight:800;">✅ Slot ${freeActiveSlot + 1}</span>`;
        } else if (otherSlotIdx !== -1) {
          metaRight = `<span style="color:#38bdf8; font-weight:700; font-size:0.65rem;">Slot ${otherSlotIdx + 1}</span>`;
        }

        return `
          <div class="${cardClass}" ${clickAttr}>
            <div class="char-card-name">${escapeHtml(c.name)}</div>
            ${matchTagHtml}
            <div class="char-card-meta">
              <span class="char-ulti-badge">⚡ ${ultiCount} Ulti</span>
              ${metaRight}
            </div>
          </div>
        `;
      }).join('');
    }

    function selectFreeChar(id, preselectedUlti = '') {
      const c = NARUTO_CHARACTERS.find(x => x.id === id);
      if (!c) return;
      if (typeof isCharBanned === 'function' && isCharBanned(c)) {
        showBannedAlert(c.name);
        return;
      }
      if (typeof isCharVipExclusive === 'function' && isCharVipExclusive(c)) {
        showVipOnlyAlert(c.name);
        return;
      }

      let chosenUlti = preselectedUlti;
      if (!chosenUlti && c.ultis && c.ultis.length > 0) {
        chosenUlti = c.ultis[0];
      }

      freeSlots[freeActiveSlot] = {
        char: c,
        ulti: chosenUlti || '-'
      };
      freeSelectedChar = freeSlots[0].char;
      freeSelectedUlti = freeSlots[0].ulti;

      const activeMode = (state.masterMode || '1v1').toUpperCase();
      const totalSlots = (activeMode === '1v1') ? 1 : (activeMode === 'DUO' ? 2 : freeTimCount);

      // Auto advance to next slot if empty
      if (freeActiveSlot < totalSlots - 1) {
        if (!freeSlots[freeActiveSlot + 1].char) {
          freeActiveSlot++;
        }
      }

      renderFreeSlotTabs();
      renderFreeCharGrid();
    }

    function showBannedAlert(charName) {
      alert(`🚫 KARAKTER DIBANNED!\n\nKarakter "${charName}" berstatus BANNED dan tidak dapat dipilih untuk semua tier (Free, VIP, maupun Srobot).`);
    }

    function showVipOnlyAlert(charName) {
      alert(`🔒 KHUSUS VIP / SROBOT!\n\nKarakter "${charName}" khusus untuk peserta VIP & SROBOT dan tidak bisa dipilih pada jalur Free Request.\n\nSilakan mendaftar melalui jalur "DAFTAR VIP" atau "DAFTAR SROBOT" untuk memainkan karakter ini!`);
    }

    function setupFreeStep3() {
      const activeMode = (state.masterMode || '1v1').toUpperCase();
      const container = document.getElementById('free-slots-ulti-container');
      const multiBox = document.getElementById('free-multi-ulti-box');
      const singleBox = document.getElementById('free-single-ulti-box');

      if (multiBox) multiBox.style.display = 'none';
      if (singleBox) singleBox.style.display = 'none';

      const totalSlots = (activeMode === '1v1') ? 1 : (activeMode === 'DUO' ? 2 : freeTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (freeSlots[i] && freeSlots[i].char) {
          activeSlots.push({ idx: i, slot: freeSlots[i] });
        }
      }

      if (activeSlots.length === 0) return;

      if (container) {
        container.innerHTML = activeSlots.map(({ idx, slot }) => {
          const c = slot.char;
          const ultis = c.ultis || [];
          const slotLabel = (activeMode === '1v1')
            ? 'Karakter Utama'
            : (idx === 0 ? 'Slot 1: Leader' : (idx === 1 ? (activeMode === 'DUO' ? 'Slot 2: Support' : 'Slot 2: Support 1') : 'Slot 3: Support 2'));

          let ultiSelectionHtml = '';
          if (ultis.length > 1) {
            if (!slot.ulti || !ultis.includes(slot.ulti)) {
              slot.ulti = ultis[0];
            }
            ultiSelectionHtml = `
              <div style="font-size: 0.76rem; color: #94a3b8; margin-bottom: 6px;">Pilih Jurus Ulti:</div>
              <div class="ulti-options-grid">
                ${ultis.map(u => {
                  const isSel = (slot.ulti === u);
                  return `
                    <div class="ulti-card-item ${isSel ? 'selected' : ''}" style="padding: 8px 12px;" onclick="selectFreeSlotUlti(${idx}, '${escapeHtml(u).replace(/'/g, "\\'")}')">
                      <div class="ulti-card-name" style="font-size: 0.84rem;">⚡ ${escapeHtml(u)}</div>
                      <span style="font-size: 0.72rem; font-weight: 800; color: ${isSel ? '#facc15' : '#94a3b8'};">
                        ${isSel ? '✅ Dipilih' : 'Pilih'}
                      </span>
                    </div>
                  `;
                }).join('')}
              </div>
            `;
          } else {
            slot.ulti = ultis[0] || '-';
            ultiSelectionHtml = `
              <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding: 6px 10px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.74rem; color: #94a3b8;">Ultimate Terkunci:</span>
                <span style="font-size: 0.85rem; font-weight: 800; color: #facc15;">⚡ ${escapeHtml(slot.ulti)}</span>
              </div>
            `;
          }

          return `
            <div class="slot-ulti-card">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 0.75rem; font-weight: 900; color: #4ade80; text-transform: uppercase;">${slotLabel}</span>
                <span style="font-size: 0.88rem; font-weight: 900; color: #fff;">${escapeHtml(c.name)}</span>
              </div>
              ${ultiSelectionHtml}
            </div>
          `;
        }).join('');
      }

      const allNames = activeSlots.map(a => a.slot.char.name).join(' / ');
      const allUltis = activeSlots.map(a => a.slot.ulti).join(' / ');

      const nameVal = (document.getElementById('free-name-input').value || '').trim();
      const sumName = document.getElementById('sum-free-name');
      const sumMode = document.getElementById('sum-free-mode');
      const sumChar = document.getElementById('sum-free-char');
      const sumUlti = document.getElementById('sum-free-ulti');

      if (sumName) sumName.innerText = nameVal;
      if (sumMode) sumMode.innerText = (activeMode === '1v1') ? '1vs1' : (activeMode === 'DUO' ? 'DUO (2)' : `TIM (${activeSlots.length})`);
      if (sumChar) sumChar.innerText = allNames || '-';
      if (sumUlti) sumUlti.innerText = allUltis || '-';
    }

    function selectFreeSlotUlti(slotIdx, u) {
      if (freeSlots[slotIdx]) {
        freeSlots[slotIdx].ulti = u;
        setupFreeStep3();
      }
    }

    function selectFreeUlti(u) {
      freeSelectedUlti = u;
      if (freeSlots[0]) freeSlots[0].ulti = u;
      setupFreeStep3();
    }

    // Submit pendaftaran FREE
    async function submitFreeRegistration() {
      const name = (document.getElementById('free-name-input').value || '').trim();
      if (!name) {
        alert('Nama TikTok tidak boleh kosong!');
        goToFreeStep(1);
        return;
      }

      const activeMode = (state.masterMode || '1v1').toUpperCase();
      if (activeMode === '1v1') {
        if (!freeSlots[0].char) {
          alert('Karakter belum dipilih!');
          goToFreeStep(2);
          return;
        }
      } else if (activeMode === 'DUO') {
        if (!freeSlots[0].char || !freeSlots[1].char) {
          alert('Mode DUO membutuhkan 2 karakter (Slot 1 & Slot 2)!');
          goToFreeStep(2);
          return;
        }
      } else if (activeMode === 'TIM') {
        if (!freeSlots[0].char || !freeSlots[1].char) {
          alert('Mode TIM membutuhkan minimal 2 karakter (Slot 1 & Slot 2)!');
          goToFreeStep(2);
          return;
        }
      }

      const totalSlots = (activeMode === '1v1') ? 1 : (activeMode === 'DUO' ? 2 : freeTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (freeSlots[i] && freeSlots[i].char) {
          activeSlots.push(freeSlots[i]);
        }
      }

      for (const s of activeSlots) {
        if (typeof isCharBanned === 'function' && isCharBanned(s.char)) {
          showBannedAlert(s.char.name);
          goToFreeStep(2);
          return;
        }
        if (typeof isCharVipExclusive === 'function' && isCharVipExclusive(s.char)) {
          showVipOnlyAlert(s.char.name);
          goToFreeStep(2);
          return;
        }
      }

      // 1. Anti double check
      const dup = checkDuplicatePlayer(name, state);
      if (dup) {
        alert(`❌ PENDAFTARAN DITOLAK!\n\n${dup.message}.`);
        return;
      }

      // 2. Daily free limit
      const dailyCheck = checkFreeDailyLimit(name, state);
      if (!dailyCheck.allowed) {
        alert(`❌ BATAS PENDAFTARAN FREE TERCAPAI!\n\n${dailyCheck.reason}`);
        return;
      }

      const fullChar = activeSlots.map(s => s.char.name).join(' / ');
      const fullUlti = activeSlots.map(s => s.ulti || (s.char.ultis ? s.char.ultis[0] : '-')).join(' / ');

      // Ambil state terbaru
      if (window.location.protocol.startsWith('http')) {
        try {
          const latestRes = await fetch(getStateApiUrl(), { cache: 'no-store' });
          if (latestRes.ok) {
            const latestData = await latestRes.json();
            if (latestData && typeof latestData === 'object') {
              state = latestData;
              sanitizeState(state);
            }
          }
        } catch (e) { }
      }

      // Validasi ulang
      const dupLatest = checkDuplicatePlayer(name, state);
      if (dupLatest) {
        alert(`❌ PENDAFTARAN DITOLAK!\n\n${dupLatest.message}.`);
        return;
      }

      const newPlayer = {
        id: 'pl_' + Date.now().toString(),
        name,
        char: fullChar,
        ulti: fullUlti,
        matchesLeft: 1,
        ws: 0,
        type: 'FREE',
        ticketMode: activeMode,
        registeredAt: Date.now(),
        status: 'APPROVED'
      };

      if (!Array.isArray(state.queue)) state.queue = [];
      state.queue.push(newPlayer);
      sortQueue(state);
      recordFreeRegistration(name, state);

      if (!Array.isArray(state.registeredUsers)) state.registeredUsers = [];
      if (!state.registeredUsers.includes(name)) state.registeredUsers.push(name);

      saveAndBroadcastState();

      closeFreeModal();

      // Show success modal
      const qIdx = state.queue.findIndex(p => p.id === newPlayer.id);
      document.getElementById('suc-free-name').innerText = name;
      document.getElementById('suc-free-pos').innerText = `#${qIdx !== -1 ? qIdx + 1 : state.queue.length}`;
      document.getElementById('suc-free-char').innerText = fullChar;
      document.getElementById('suc-free-ulti').innerText = fullUlti;
      document.getElementById('modal-free-success').style.display = 'flex';
    }

    // Helper functions
    function sanitizeState(st) {
      if (!st || typeof st !== 'object') return;
      if (!Array.isArray(st.queue)) st.queue = [];
      if (!Array.isArray(st.pendingApprovals)) st.pendingApprovals = [];
      if (!Array.isArray(st.registeredUsers)) st.registeredUsers = [];
      if (!st.masterMode) st.masterMode = '1v1';
    }

    function checkDuplicatePlayer(name, st) {
      if (!name || !name.trim()) return null;
      const clean = name.trim().toLowerCase();
      if (clean === '-' || clean === 'menunggu...') return null;

      if (st.p1 && st.p1.name && st.p1.name.trim().toLowerCase() === clean) {
        return { duplicate: true, message: `Pemain "${name}" sedang bertanding di Arena (P1)` };
      }
      if (st.p2 && st.p2.name && st.p2.name.trim().toLowerCase() === clean) {
        return { duplicate: true, message: `Pemain "${name}" sedang bertanding di Arena (P2)` };
      }
      for (const arenaKey of ['arena1v1', 'arenaDuo', 'arenaTim']) {
        if (st[arenaKey] && typeof st[arenaKey] === 'object') {
          if (st[arenaKey].p1 && st[arenaKey].p1.name && st[arenaKey].p1.name.trim().toLowerCase() === clean) {
            return { duplicate: true, message: `Pemain "${name}" sedang berada di Arena` };
          }
          if (st[arenaKey].p2 && st[arenaKey].p2.name && st[arenaKey].p2.name.trim().toLowerCase() === clean) {
            return { duplicate: true, message: `Pemain "${name}" sedang berada di Arena` };
          }
        }
      }
      if (Array.isArray(st.queue)) {
        const qIdx = st.queue.findIndex(p => p.name && p.name.trim().toLowerCase() === clean);
        if (qIdx !== -1) {
          return { duplicate: true, message: `Pemain "${name}" sudah ada di antrian #${qIdx + 1}` };
        }
      }
      return null;
    }

    function checkFreeDailyLimit(name, st) {
      const todayStr = new Date().toISOString().slice(0, 10);
      const normName = (name || '').trim().toLowerCase();

      try {
        const devKey = 'koth_free_reg_date';
        const lastRegDate = localStorage.getItem(devKey);
        if (lastRegDate === todayStr) {
          return {
            allowed: false,
            reason: 'Perangkat ini sudah mendaftar Tier FREE hari ini (' + todayStr + ').\nSilakan pilih Tier VIP / SROBOT atau daftar kembali besok.'
          };
        }
      } catch (e) { }

      try {
        const nameKey = 'koth_free_user_' + normName + '_' + todayStr;
        if (localStorage.getItem(nameKey)) {
          return {
            allowed: false,
            reason: 'Nama "' + name + '" sudah mendaftar Tier FREE hari ini (' + todayStr + ').\nSilakan pilih Tier VIP / SROBOT atau daftar kembali besok.'
          };
        }
      } catch (e) { }

      return { allowed: true };
    }

    function recordFreeRegistration(name, st) {
      const todayStr = new Date().toISOString().slice(0, 10);
      const normName = (name || '').trim().toLowerCase();
      try { localStorage.setItem('koth_free_reg_date', todayStr); } catch (e) { }
      try { localStorage.setItem('koth_free_user_' + normName + '_' + todayStr, Date.now().toString()); } catch (e) { }
    }

    function interleaveVipFree(vipPlayers, freePlayers, vipConsecutiveCount = 0) {
      const result = [];
      let vipIndex = 0;
      let freeIndex = 0;
      let consecutive = vipConsecutiveCount;

      while (vipIndex < vipPlayers.length || freeIndex < freePlayers.length) {
        if (vipIndex < vipPlayers.length && (consecutive < 2 || freeIndex >= freePlayers.length)) {
          result.push(vipPlayers[vipIndex++]);
          consecutive++;
        } else if (freeIndex < freePlayers.length) {
          result.push(freePlayers[freeIndex++]);
          consecutive = 0;
        } else if (vipIndex < vipPlayers.length) {
          result.push(vipPlayers[vipIndex++]);
          consecutive++;
        }
      }
      return result;
    }

    function sortQueue(st) {
      if (!st || !Array.isArray(st.queue)) return;
      const fastPlayers = [];
      const vipPlayers = [];
      const freePlayers = [];

      st.queue.forEach(p => {
        const t = (p.type || '').toUpperCase();
        if (t === 'SROBOT' || t === 'SB' || t === 'FAST' || t === 'VVIP') fastPlayers.push(p);
        else if (t === 'VIP') vipPlayers.push(p);
        else freePlayers.push(p);
      });

      const interleaved = interleaveVipFree(vipPlayers, freePlayers, st.vipConsecutiveCount || 0);
      st.queue = [...fastPlayers, ...interleaved];
    }

    function syncActiveModeToArena() {
      // safe no-op
    }

    function saveAndBroadcastState() {
      state._clientId = LOCAL_CLIENT_ID;
      state._ts = Date.now();
      const stateJson = JSON.stringify(state);
      lastLocalSaveAt = Date.now();

      try { localStorage.setItem('koth_naruto_state', stateJson); } catch (e) { }

      if (kothSyncChannel) {
        try { kothSyncChannel.postMessage({ type: 'KOTH_STATE_UPDATE', state: state, ts: state._ts, clientId: LOCAL_CLIENT_ID }); } catch (e) { }
      }

      if (cloudMqttClient && cloudMqttClient.connected) {
        try { cloudMqttClient.publish(MQTT_TOPIC, stateJson, { qos: 0, retain: false }); } catch (e) { }
      }

      if (window.location.protocol.startsWith('http')) {
        pendingSaves++;
        fetch(getStateApiUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: stateJson
        })
          .catch(() => { })
          .finally(() => { pendingSaves = Math.max(0, pendingSaves - 1); });
      }

      renderHubMode();
    }

    function renderHubMode() {
      const mode = (state.masterMode || '1v1').toUpperCase();
      const el = document.getElementById('hub-mode-text');
      if (el) {
        if (mode === 'DUO') el.innerText = 'DUO (2 Karakter)';
        else if (mode === 'TIM') el.innerText = 'TIM (2 / 3 Karakter)';
        else el.innerText = '1vs1 (SOLO)';
      }
      const tickerWrap = document.getElementById('ticker-wrap');
      const tickerText = document.getElementById('ticker-text');
      if (state.announcement && state.announcement.trim()) {
        if (tickerWrap) tickerWrap.style.display = 'flex';
        if (tickerText) tickerText.innerText = state.announcement.trim();
      } else {
        if (tickerWrap) tickerWrap.style.display = 'none';
      }
    }

    // Polling MySQL
    function fetchStateFromDb() {
      const fetchStartedAt = Date.now();
      fetch(getStateApiUrl(), { cache: 'no-store' })
        .then(res => res.ok ? res.json() : null)
        .then(data => {
          if (!data || typeof data !== 'object') return;
          if (pendingSaves > 0 || fetchStartedAt < lastLocalSaveAt) return;
          if (data._clientId === LOCAL_CLIENT_ID) return;
          const raw = JSON.stringify(data);
          if (raw === _lastServerJson) return;
          _lastServerJson = raw;
          state = data;
          sanitizeState(state);
          try { localStorage.setItem('koth_naruto_state', raw); } catch (e) { }
          renderHubMode();
        })
        .catch(() => { });
    }

    // Init
    window.addEventListener('DOMContentLoaded', () => {
      sanitizeState(state);
      renderHubMode();

      try {
        if (typeof BroadcastChannel !== 'undefined') {
          kothSyncChannel = new BroadcastChannel('koth_naruto_bus_' + streamKey);
          kothSyncChannel.onmessage = (event) => {
            const data = event.data;
            if (data && data.state && data.clientId !== LOCAL_CLIENT_ID) {
              state = data.state;
              sanitizeState(state);
              renderHubMode();
            }
          };
        }
      } catch (e) { }

      try {
        if (typeof mqtt !== 'undefined') {
          cloudMqttClient = mqtt.connect('wss://broker.emqx.io:8084/mqtt', {
            clientId: LOCAL_CLIENT_ID,
            clean: true,
            reconnectPeriod: 3000
          });
          cloudMqttClient.on('connect', () => {
            cloudMqttClient.subscribe(MQTT_TOPIC, { qos: 0 });
          });
          cloudMqttClient.on('message', (topic, payload) => {
            if (topic === MQTT_TOPIC) {
              try {
                const incoming = JSON.parse(payload.toString());
                if (incoming && incoming._clientId !== LOCAL_CLIENT_ID) {
                  state = incoming;
                  sanitizeState(state);
                  renderHubMode();
                }
              } catch (e) { }
            }
          });
        }
      } catch (e) { }

      if (window.location.protocol.startsWith('http')) {
        fetchStateFromDb();
        setInterval(fetchStateFromDb, 2000);
      }
    });
  </script>
</body>
</html>
