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

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/overlay.php';
$appDir = rtrim(dirname($scriptName), '/\\');
$appBaseUrl = ($appDir === '' || $appDir === '.') ? '/' : $appDir . '/';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KotH Live Overlay</title>
  <!-- MQTT.js for Realtime Cloud Sync on GitHub Pages -->
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <style>
    :root {
      /* User's Exact 4-Color Palette: #F7F7F7, #EEEEEE, #393E46, #929AAB */
      --c-white: #F7F7F7;
      --c-light: #EEEEEE;
      --c-dark: #393E46;
      --c-slate: #929AAB;

      --bg-dark: rgba(57, 62, 70, 0.96);
      --border-subtle: #929AAB;
      --accent-gold: #F7F7F7;
      --accent-blue: #929AAB;
      --accent-green: #EEEEEE;
      --accent-red: #929AAB;
      --accent-purple: #929AAB;
      --text-main: #F7F7F7;
      --text-muted: #EEEEEE;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Consolas', 'Courier New', Courier, monospace;
    }

    button,
    input,
    select,
    textarea {
      font-family: 'Consolas', 'Courier New', Courier, monospace;
    }

    html,
    body {
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 6px;
      box-sizing: border-box;
      background-color: transparent;
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--text-main);
      font-family: 'Consolas', 'Courier New', Courier, monospace;
    }

    /* RESPONSIVE & SAFE FIT STREAM OVERLAY (MAX 400x720) */
    .mobile-frame {
      width: 100%;
      height: 100%;
      max-width: 400px;
      max-height: 720px;
      background: var(--bg-dark);
      border-radius: 12px;
      padding: 8px;
      border: 1px solid var(--border-subtle);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.5);
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
      box-sizing: border-box;
    }

    /* ANNOUNCEMENT TICKER */
    .overlay-ticker {
      background: rgba(0, 0, 0, 0.45);
      border-radius: 8px;
      padding: 6px 10px;
      margin-bottom: 6px;
      font-size: 1rem;
      display: flex;
      align-items: center;
      gap: 8px;
      border-left: 4px solid #ffffff;
      color: #ffffff;
      overflow: hidden;
      flex-shrink: 0;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      border-right: 1px solid rgba(255, 255, 255, 0.2);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .ticker-label {
      color: #ffffff;
      font-weight: 900;
      font-size: 1rem;
      letter-spacing: 0.8px;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .ticker-marquee-wrap {
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      min-width: 0;
      display: flex;
      align-items: center;
    }

    .ticker-content {
      white-space: nowrap;
      color: #ffffff;
      font-weight: 700;
      font-size: 0.98rem;
      display: inline-flex;
      align-items: center;
      flex-shrink: 0;
      will-change: transform;
    }

    /* TOP WS BANNER */
    .overlay-top-record {
      background: rgba(0, 0, 0, 0.45);
      border-radius: 8px;
      padding: 5px 10px;
      margin-bottom: 6px;
      font-size: 0.92rem;
      display: flex;
      align-items: center;
      gap: 8px;
      border-left: 4px solid #ffffff;
      color: #ffffff;
      overflow: hidden;
      flex-shrink: 0;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      border-right: 1px solid rgba(255, 255, 255, 0.2);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .record-label {
      color: #ffffff;
      font-weight: 900;
      font-size: 0.88rem;
      letter-spacing: 0.8px;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .record-marquee-wrap {
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      min-width: 0;
      display: flex;
      align-items: center;
    }

    .record-content {
      color: #ffffff;
      font-weight: 700;
      font-size: 0.92rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      will-change: transform;
    }

    .rec-item {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
    }

    .rec-medal {
      font-size: 0.95rem;
      filter: drop-shadow(0 0 2px rgba(255,255,255,0.4));
    }

    .rec-divider {
      color: rgba(255, 255, 255, 0.4);
      font-weight: 900;
      margin: 0 4px;
    }

    .rec-username {
      font-weight: 800;
      color: #ffffff;
    }

    .rec-char {
      font-weight: 600;
      color: #ffffff;
    }

    .rec-ws {
      font-weight: 900;
      color: #ffffff;
      background: rgba(255, 255, 255, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.4);
      padding: 1px 6px;
      border-radius: 4px;
      letter-spacing: 0.5px;
    }

    .rec-sep {
      color: #ffffff;
      opacity: 0.8;
      font-weight: 800;
      margin: 0 1px;
    }

    /* MATCH ARENA (1 VS 1) - COMPACT & HIGH CONTRAST */
    .match-box {
      display: flex;
      flex-direction: column;
      gap: 2px;
      background: rgba(0, 0, 0, 0.25);
      border-radius: 8px;
      padding: 4px;
      border: 1px solid var(--border-subtle);
      margin-bottom: 5px;
      flex-shrink: 0;
    }

    .player-slot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 3px 8px;
      border-radius: 6px;
      transition: background 0.15s ease;
      background: rgba(255, 255, 255, 0.03);
      border-left: 3px solid var(--c-slate);
      min-height: 40px;
    }

    .p-info-p1 {
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-width: 0;
      flex: 1;
      gap: 1px;
    }

    .p-info-p2 {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: flex-end;
      text-align: right;
      min-width: 0;
      flex: 1;
      gap: 1px;
    }

    .p-header-line {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 0;
    }

    .p-header-line-p2 {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 6px;
      min-width: 0;
    }

    .player-slot.fast,
    .player-slot.vvip {
      background: rgba(168, 85, 247, 0.12);
      border-left: 3px solid #a855f7;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.15);
    }

    .player-slot.vip {
      background: rgba(255, 215, 0, 0.05);
      border-left: 3px solid #FFD700;
    }

    .player-slot.free {
      background: rgba(255, 255, 255, 0.02);
      border-left: 3px solid rgba(146, 154, 171, 0.35);
    }

    /* P2 MIRRORED BORDER & LAYOUT */
    #slot-p2 {
      border-left: none;
      border-right: 3px solid var(--c-slate);
    }
    #slot-p2.fast,
    #slot-p2.vvip {
      border-left: none;
      border-right: 3px solid #a855f7;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.15);
    }
    #slot-p2.vip {
      border-left: none;
      border-right: 3px solid #FFD700;
    }
    #slot-p2.free {
      border-left: none;
      border-right: 3px solid rgba(146, 154, 171, 0.35);
    }

    /* ROLE BADGE = TIER BADGE IN ARENA */
    .p-role-tag {
      font-size: 0.70rem;
      font-weight: 800;
      text-transform: uppercase;
      padding: 1px 6px;
      border-radius: 3px;
      display: inline-flex;
      align-items: center;
      letter-spacing: 0.5px;
      background: rgba(255, 255, 255, 0.15);
      color: #ffffff;
      flex-shrink: 0;
      line-height: 1.3;
    }

    .p-role-tag.fast,
    .p-role-tag.vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 60%, #9333ea 100%);
      border: 1px solid #d8b4fe;
      color: #ffffff;
      font-weight: 900;
      letter-spacing: 0.8px;
      text-shadow: 0 0 8px rgba(216, 180, 254, 0.7);
      box-shadow: 0 0 8px rgba(168, 85, 247, 0.5);
    }

    .p-role-tag.vip {
      background-color: rgba(255, 215, 0, 0.16);
      border: 1px solid #FFD700;
      color: #FFD700;
      font-weight: 900;
      letter-spacing: 0.8px;
      text-shadow: 0 0 6px rgba(255, 215, 0, 0.35);
    }

    .p-role-tag.free {
      background-color: transparent;
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.35);
      font-weight: 700;
    }

    /* LARGE PLAYER NAME */
    .p-name {
      font-weight: 800;
      font-size: 1.15rem;
      color: #ffffff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.15;
    }

    /* LARGE CHARACTER NAME */
    .p-ulti {
      font-size: 0.70rem;
      color: #facc15;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.9);
      line-height: 1.1;
      max-width: 100%;
    }
    .p-char {
      font-size: 0.82rem;
      color: rgba(255, 255, 255, 0.85);
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.1;
    }

    .p-right {
      text-align: right;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 1px;
      flex-shrink: 0;
      margin-left: 8px;
    }

    .p-left-p2 {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 1px;
      flex-shrink: 0;
      margin-right: 8px;
    }

    /* PROMINENT WIN STREAK */
    .p-ws {
      font-weight: 800;
      font-size: 1.05rem;
      color: #ffffff;
      display: flex;
      align-items: center;
      gap: 4px;
      line-height: 1.15;
    }

    .free-streak {
      font-size: 0.70rem;
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.4);
      color: #ffffff;
      padding: 0 4px;
      border-radius: 3px;
      font-weight: 800;
      letter-spacing: 0.5px;
    }

    .bonus-badge {
      font-size: 0.68rem;
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.4);
      color: #ffffff;
      padding: 0 4px;
      border-radius: 3px;
      font-weight: 800;
      letter-spacing: 0.5px;
    }

    .p-ticket {
      font-size: 0.82rem;
      color: rgba(255, 255, 255, 0.85);
      font-weight: 600;
      line-height: 1.1;
    }

    .p-ticket b {
      color: #ffffff;
      font-size: 0.88rem;
      font-weight: 800;
    }

    .vs-tag {
      text-align: center;
      font-size: 0.65rem;
      font-weight: 900;
      color: rgba(255, 255, 255, 0.7);
      letter-spacing: 1.5px;
      margin: 0;
      line-height: 1;
    }

    /* SCROLLABLE QUEUE SECTION */
    .queue-section {
      background: rgba(0, 0, 0, 0.2);
      border-radius: 8px;
      padding: 6px 8px;
      border: 1px solid rgba(146, 154, 171, 0.25);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 0;
      overflow: hidden;
    }

    .queue-title {
      font-size: 0.82rem;
      font-weight: 800;
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 5px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
      border-bottom: 1px solid rgba(146, 154, 171, 0.25);
      padding-bottom: 3px;
    }

    /* SCROLLABLE LIST */
    .queue-list {
      display: flex;
      flex-direction: column;
      gap: 4px;
      overflow-y: auto;
      flex: 1;
      padding-right: 2px;
    }

    .queue-list::-webkit-scrollbar {
      width: 4px;
    }

    .queue-list::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.2);
      border-radius: 2px;
    }

    .queue-list::-webkit-scrollbar-thumb {
      background: var(--c-slate);
      border-radius: 2px;
    }

    /* VERTICAL TIER SECTIONS */
    .tier-section {
      display: flex;
      flex-direction: column;
      gap: 3px;
      margin-bottom: 5px;
    }

    .tier-section.fast,
    .tier-section.vvip {
      margin-bottom: 14px;
      padding-bottom: 10px;
      border-bottom: 1px dashed rgba(192, 132, 252, 0.45);
    }

    .tier-section.vip {
      margin-bottom: 14px;
      padding-bottom: 10px;
      border-bottom: 1px dashed rgba(255, 215, 0, 0.45);
    }

    .tier-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 3px 6px;
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 900;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      color: #ffffff;
    }

    .th-ticket {
      font-size: 0.76rem;
      font-weight: 900;
      letter-spacing: 1px;
      color: #ffffff;
      margin-right: 2px;
      min-width: 32px;
      text-align: center;
    }

    .tier-header.fast,
    .tier-header.vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 100%);
      color: #ffffff;
      border-left: 3px solid #d8b4fe;
      box-shadow: inset 0 1px 1px rgba(255,255,255,0.08);
    }

    .tier-header.fast span:first-child,
    .tier-header.fast .th-title,
    .tier-header.vvip span:first-child,
    .tier-header.vvip .th-title {
      color: #ffffff;
      font-weight: 900;
      text-shadow: 0 0 8px rgba(216, 180, 254, 0.6);
    }

    .tier-header.vip {
      background: rgba(255, 215, 0, 0.14);
      color: #FFD700;
      border-left: 3px solid #FFD700;
    }

    .tier-header.vip span:first-child,
    .tier-header.vip .th-title {
      color: #FFD700;
      font-weight: 900;
    }

    .tier-header.free {
      background: rgba(255, 255, 255, 0.05);
      color: #ffffff;
      border-left: 3px solid rgba(255, 255, 255, 0.4);
      font-weight: 700;
    }

    .tier-section.vipfree {
      margin-bottom: 14px;
      padding-bottom: 10px;
    }

    .tier-header.vipfree {
      background: linear-gradient(135deg, rgba(255, 215, 0, 0.16) 0%, rgba(56, 189, 248, 0.12) 100%);
      color: #FFD700;
      border-left: 3px solid #FFD700;
    }

    .tier-header.vipfree span:first-child,
    .tier-header.vipfree .th-title {
      color: #FFD700;
      font-weight: 900;
    }

    .tier-status-tag {
      font-size: 0.68rem;
      font-weight: 900;
      padding: 1px 6px;
      border-radius: 3px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      display: inline-block;
      vertical-align: middle;
      color: #ffffff;
    }

    .tier-status-tag.open {
      background: rgba(34, 197, 94, 0.35);
      border: 1px solid #22c55e;
    }

    .tier-status-tag.closed {
      background: rgba(239, 68, 68, 0.35);
      border: 1px solid #ef4444;
    }

    .tier-empty {
      font-size: 0.72rem;
      color: #ffffff;
      opacity: 0.85;
      padding: 3px 8px;
      font-style: italic;
    }

    /* CLEAN QUEUE ROW WITH TICKET DISPLAY */
    .queue-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 5px 7px;
      border-radius: 6px;
      background: rgba(255, 255, 255, 0.04);
      border-left: 3px solid var(--c-slate);
      flex-shrink: 0;
    }

    .queue-row.fast,
    .queue-row.vvip {
      border-left-color: #a855f7;
      background: linear-gradient(90deg, rgba(168, 85, 247, 0.14) 0%, rgba(255, 255, 255, 0.04) 100%);
    }

    .queue-row.vip {
      border-left-color: #FFD700;
      background: rgba(255, 215, 0, 0.05);
    }

    .queue-row.free {
      border-left-color: rgba(146, 154, 171, 0.35);
      background: rgba(255, 255, 255, 0.02);
    }

    .queue-row.saved {
      border-left-color: var(--c-light);
      background: rgba(255, 255, 255, 0.03);
    }

    /* HORIZONTAL SCROLL / MARQUEE FOR LONG NAMES */
    .q-left {
      display: flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
      overflow: hidden;
      flex: 1;
      min-width: 0;
    }

    .q-text-wrap {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
      flex-shrink: 0;
      will-change: transform;
    }

    .q-sep {
      color: #ffffff;
      font-weight: 800;
      opacity: 0.8;
      margin: 0 1px;
    }

    @keyframes marqueeH {
      0%    { transform: translateX(0); }
      25%   { transform: translateX(0); }
      75%   { transform: translateX(var(--marquee-dist, -50px)); }
      99.9% { transform: translateX(var(--marquee-dist, -50px)); }
      100%  { transform: translateX(0); }
    }

    .marquee-active {
      animation: marqueeH 12s linear infinite;
    }

    .q-idx {
      font-weight: 800;
      color: #ffffff;
      font-size: 0.95rem;
      min-width: 20px;
    }

    .queue-row.fast .q-idx,
    .queue-row.vvip .q-idx {
      color: #d8b4fe;
      font-weight: 900;
    }

    .queue-row.vip .q-idx {
      color: #FFD700;
      font-weight: 900;
    }

    .q-name {
      font-weight: 800;
      color: #ffffff;
      font-size: 1.05rem;
    }

    .q-char {
      font-size: 0.92rem;
      color: #ffffff;
      font-weight: 600;
    }

    .q-right {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-shrink: 0;
      margin-left: 8px;
    }

    /* USER TICKET DISPLAY IN QUEUE */
    .q-ticket {
      font-size: 0.92rem;
      font-weight: 900;
      color: #ffffff;
      background: rgba(255, 255, 255, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.4);
      padding: 2px 7px;
      border-radius: 4px;
      white-space: nowrap;
      min-width: 28px;
      text-align: center;
    }

    .q-badge {
      font-size: 0.72rem;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 3px;
      letter-spacing: 0.5px;
      flex-shrink: 0;
      color: #ffffff;
    }

    .q-badge.fast,
    .q-badge.vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 60%, #9333ea 100%);
      border: 1px solid #d8b4fe;
      color: #ffffff;
      font-weight: 900;
      letter-spacing: 0.8px;
      box-shadow: 0 0 6px rgba(168, 85, 247, 0.5);
      text-shadow: 0 0 6px rgba(216, 180, 254, 0.5);
    }

    .q-badge.vip {
      background-color: rgba(255, 215, 0, 0.16);
      border: 1px solid #FFD700;
      color: #FFD700;
      font-weight: 900;
      letter-spacing: 0.8px;
    }

    .q-badge.free {
      background-color: transparent;
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.35);
      font-weight: 700;
    }

    .q-badge.saved {
      background-color: rgba(255, 255, 255, 0.18);
      border: 1px solid rgba(255, 255, 255, 0.5);
      color: #ffffff;
      font-weight: 800;
    }

    .empty-queue {
      color: #ffffff;
      font-size: 0.85rem;
      text-align: center;
      padding: 24px 0;
    }

    .scroll-hint {
      font-size: 0.68rem;
      color: #ffffff;
      font-weight: 700;
    }

    /* BANNER FREE TUTUP DIBAWAH LIST ANTRIAN */
    .free-closed-banner {
      flex-shrink: 0;
      margin-top: 6px;
      padding: 10px 14px;
      background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
      color: #ffffff;
      border: 2px solid #ef4444;
      border-radius: 8px;
      text-align: center;
      font-size: 1.35rem;
      font-weight: 900;
      letter-spacing: 2px;
      text-transform: uppercase;
      box-shadow: 0 4px 16px rgba(220, 38, 38, 0.6);
      animation: pulseFreeClosed 2s infinite ease-in-out;
      user-select: none;
    }

    @keyframes pulseFreeClosed {
      0%, 100% {
        transform: scale(1);
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.6);
      }
      50% {
        transform: scale(1.02);
        box-shadow: 0 6px 24px rgba(239, 68, 68, 0.9);
      }
    }
  </style>
</head>

<body>
<?php
$sidebarActive = 'overlay';
include __DIR__ . '/includes/sidebar.php';
?>
  <!-- Floating Menu Button for Overlay -->
  <button type="button" class="koth-hamburger-btn overlay-menu-btn" onclick="kothToggleSidebar()" title="Buka Menu Navigasi" style="position: fixed; top: 8px; right: 8px; z-index: 99990; opacity: 0.25; background: rgba(0,0,0,0.6);" onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0.25'">
    ☰
  </button>

  <div class="mobile-frame">
    <!-- Announcement Ticker -->
    <div id="overlay-ticker" class="overlay-ticker" style="display:none;">
      <span class="ticker-label">NOTE:</span>
      <div class="ticker-marquee-wrap">
        <span id="ticker-text" class="ticker-content"></span>
      </div>
    </div>

    <!-- Top Record WS Banner (Top 3) -->
    <div id="overlay-top-record" class="overlay-top-record">
      <span class="record-label" id="record-label">TOP WS:</span>
      <div class="record-marquee-wrap">
        <span id="record-text" class="record-content">
          <span class="rec-item">
            <span class="rec-medal">🥇</span>
            <span class="rec-username">-</span>
            <span class="rec-sep">|</span>
            <span class="rec-char">-</span>
            <span class="rec-ws">0 WS</span>
          </span>
        </span>
      </div>
    </div>

    <!-- Match Arena (1 VS 1) -->
    <div class="match-box">
      <!-- Player 1 Slot -->
      <div class="player-slot" id="slot-p1">
        <div class="p-info-p1">
          <div class="p-header-line">
            <span class="p-role-tag" id="p1-role-tag">P1</span>
            <div id="p1-name" class="p-name">Menunggu...</div>
          </div>
          <div id="p1-char" class="p-char">-</div>
          <div id="p1-ulti" class="p-ulti" style="display:none;"></div>
        </div>
        <div class="p-right">
          <div class="p-ws">
            WS: <span id="p1-ws">0</span>
            <span id="p1-bonus" class="bonus-badge" style="display:none;">+1 MATCH</span>
          </div>
          <div class="p-ticket">Match: <b id="p1-tickets">0</b></div>
        </div>
      </div>

      <div class="vs-tag">VS</div>

      <!-- Player 2 Slot (Mirrored: WS/Tiket on Left, Tier/Nama/Char on Right) -->
      <div class="player-slot" id="slot-p2">
        <div class="p-left-p2">
          <div class="p-ws">
            WS: <span id="p2-ws">0</span>
            <span id="p2-bonus" class="bonus-badge" style="display:none;">+1 MATCH</span>
          </div>
          <div class="p-ticket">Match: <b id="p2-tickets">0</b></div>
        </div>
        <div class="p-info-p2">
          <div class="p-header-line-p2">
            <div id="p2-name" class="p-name">Menunggu...</div>
            <span class="p-role-tag" id="p2-role-tag">P2</span>
          </div>
          <div id="p2-char" class="p-char">-</div>
          <div id="p2-ulti" class="p-ulti" style="display:none;"></div>
        </div>
      </div>
    </div>

    <!-- Scrollable Queue Section -->
    <div class="queue-section">
      <div class="queue-title">
        <span id="queue-section-title">MATCH QUEUE</span>
        <div style="display:flex; align-items:center; gap:6px;">
          <span id="queue-total-count" style="color:#ffffff; font-weight:800; font-size:0.85rem;">0 Pemain</span>
        </div>
      </div>

      <!-- ALL QUEUE OR SAVED ITEMS RENDERED HERE WITH MATCHES -->
      <div id="queue-list" class="queue-list">
        <div class="empty-queue">(Match Queue Kosong)</div>
      </div>

      <!-- BANNER STATUS FREE TUTUP DIBAWAH LIST ANTRIAN -->
      <div id="free-closed-banner" class="free-closed-banner" style="display:none;">FREE TUTUP</div>
    </div>
  </div>

  <script>
    let state = <?= $initialStateJson ?>;

    // Helper deteksi peran King vs Penantang
    function getArenaRoles(st) {
      const s = st || state;
      if (s.p2 && (s.p2.ws > 0 || !s.p1)) {
        if (!s.p1 || (s.p1.ws === 0 && s.p2.ws > 0)) {
          return { kingSlot: 'p2', challengerSlot: 'p1' };
        }
      }
      return { kingSlot: 'p1', challengerSlot: 'p2' };
    }

    // Peta Singkatan 3 Huruf Karakter Naruto Storm (Khusus Tampilan Overlay Duo/Trio)
        // Master Data 163 Karakter Naruto Storm Connections (Urutan In-Game Slide 1 - 8)
    const NARUTO_CHARACTERS = [{"id": 1, "name": "Naruto Uzumaki (Mode Petapa Enam Jalan)", "slide": 1, "ultis": ["Enam Jalan: Rasen Shuriken Bola Raksasa", "Rasen Shuriken Planet Siluman Berekor"], "short1v1": "Naruto (Enam Jalan)", "shortTeam": "N6J"}, {"id": 2, "name": "Naruto Uzumaki (Mode Link Kurama)", "slide": 1, "ultis": ["Rasen Shuriken Panet Siluman Berekor"], "short1v1": "Naruto (Kurama)", "shortTeam": "NLK"}, {"id": 3, "name": "Naruto Uzumaki (Bola Siluman Berekor)", "slide": 1, "ultis": ["Bola Siluman Berekor"], "short1v1": "Naruto (Bijuu)", "shortTeam": "NBS"}, {"id": 4, "name": "Naruto Uzumaki (Teknik Petapa: Rentetan Rasengan Bola Raksasa)", "slide": 1, "ultis": ["Teknik Petapa: Rentetan Rasengan Bola Raksasa"], "short1v1": "Naruto (Petapa Rasen)", "shortTeam": "NPR"}, {"id": 5, "name": "Naruto Uzumaki (Mode Petapa)", "slide": 1, "ultis": ["Sejati: Elemen Angin - Rasen Shuriken"], "short1v1": "Naruto (Petapa)", "shortTeam": "NSP"}, {"id": 6, "name": "Naruto Uzumaki", "slide": 1, "ultis": ["Elemen Angin: Rasen Shuriken", "Rasengan Ekor Sembilan"], "short1v1": "Naruto", "shortTeam": "NRT"}, {"id": 7, "name": "Sakura Haruno", "slide": 1, "ultis": ["Mekar Penuh: Tumbukan Bunga Sakura", "Teknik Seratus Penyembuhan"], "short1v1": "Sakura", "shortTeam": "SKR"}, {"id": 8, "name": "Sai", "slide": 1, "ultis": ["Teknik Ninja: Gambar Replika Monster - Gerombolan"], "short1v1": "Sai", "shortTeam": "SAI"}, {"id": 9, "name": "Kakashi Hatake (Kedua Mata Sharingan)", "slide": 1, "ultis": ["Raikiri: Kilat", "Susanoo: Kamui Raikiri", "Kamui"], "short1v1": "Kakashi (DMS)", "shortTeam": "K2S"}, {"id": 10, "name": "Kakashi Hatake (Perang Dunia Ninja)", "slide": 1, "ultis": ["Kamui", "Gempa Petir Ganda Raikiri"], "short1v1": "Kakashi (Perang)", "shortTeam": "KPD"}, {"id": 11, "name": "Kakashi Hatake", "slide": 1, "ultis": ["Kamui", "Gempa Petir Ganda Raikiri"], "short1v1": "Kakashi", "shortTeam": "KKS"}, {"id": 12, "name": "Might Guy (Perang Dunia Ninja)", "slide": 1, "ultis": ["Merak Pagi", "Harimau Siang", "Guy Malam"], "short1v1": "Guy (Perang)", "shortTeam": "GPD"}, {"id": 13, "name": "Might Guy", "slide": 1, "ultis": ["Merak Pagi", "Harimau Siang"], "short1v1": "Guy", "shortTeam": "GUY"}, {"id": 14, "name": "Yamato", "slide": 1, "ultis": ["Gen Hokage Pertama"], "short1v1": "Yamato", "shortTeam": "YMT"}, {"id": 15, "name": "Shikamaru Nara", "slide": 1, "ultis": ["Teknik Pengumpul Bayangan"], "short1v1": "Shikamaru", "shortTeam": "SKM"}, {"id": 16, "name": "Chouji Akimichi", "slide": 1, "ultis": ["Tamparan Super"], "short1v1": "Chouji", "shortTeam": "CHJ"}, {"id": 17, "name": "Ino Yamanaka", "slide": 1, "ultis": ["Taman Bunga Tarian Kelopak"], "short1v1": "Ino", "shortTeam": "INO"}, {"id": 18, "name": "Neji Hyuga", "slide": 1, "ultis": ["8 Trigraf 64 Tapak"], "short1v1": "Neji", "shortTeam": "NEJ"}, {"id": 19, "name": "Rock Lee", "slide": 1, "ultis": ["Angin Panas Konoha"], "short1v1": "Rock Lee", "shortTeam": "LEE"}, {"id": 20, "name": "Tenten", "slide": 1, "ultis": ["Kendali Senjata: Bola Besi Raksasa"], "short1v1": "Tenten", "shortTeam": "TTN"}, {"id": 21, "name": "Kiba Inuzuka", "slide": 1, "ultis": ["Absolut: Taring ke Taring"], "short1v1": "Kiba", "shortTeam": "KBA"}, {"id": 22, "name": "Shino Aburame", "slide": 1, "ultis": ["Teknik Rahasia: Rawa Serangga"], "short1v1": "Shino", "shortTeam": "SHN"}, {"id": 23, "name": "Hinata Hyuga", "slide": 1, "ultis": ["Tinju Singa Kembar Halus"], "short1v1": "Hinata", "shortTeam": "HNT"}, {"id": 24, "name": "Asuma Sarutobi", "slide": 1, "ultis": ["Bilah Chakra: Lurus"], "short1v1": "Asuma", "shortTeam": "ASM"}, {"id": 25, "name": "Konohamaru Sarutobi", "slide": 2, "ultis": ["Teknik Harem Hebat"], "short1v1": "Konohamaru", "shortTeam": "KNH"}, {"id": 26, "name": "Shisui Uchiha", "slide": 2, "ultis": ["Kotoamatsukami: Sinar"], "short1v1": "Shisui", "shortTeam": "SSU"}, {"id": 27, "name": "Iruka Umino", "slide": 2, "ultis": ["Auman Cinta"], "short1v1": "Iruka", "shortTeam": "IRK"}, {"id": 28, "name": "Kushina Uzumaki", "slide": 2, "ultis": ["Habanero Merah Darah"], "short1v1": "Kushina", "shortTeam": "KSN"}, {"id": 29, "name": "Jiraiya", "slide": 2, "ultis": ["Rasengan Pahlawan"], "short1v1": "Jiraiya", "shortTeam": "JRY"}, {"id": 30, "name": "Hashirama Senju (Edo Tensei)", "slide": 2, "ultis": ["Teknik Petapa: Elemen Kayu - Seribu Tangan Sejati"], "short1v1": "Hashirama (Edo)", "shortTeam": "HSE"}, {"id": 31, "name": "Hashirama Senju", "slide": 2, "ultis": ["Teknik Rahasia Elemen Kayu: Pencipta Hutan Belantara"], "short1v1": "Hashirama", "shortTeam": "HSR"}, {"id": 32, "name": "Tobirama Senju", "slide": 2, "ultis": ["Elemen Air: Terjangan Naga Air", "Kertas Bom Tandem Lipat  Ganda"], "short1v1": "Tobirama", "shortTeam": "TBR"}, {"id": 33, "name": "Hiruzen Sarutobi", "slide": 2, "ultis": ["Teknik Penyegelan: Segel Malaikat Maut"], "short1v1": "Hiruzen", "shortTeam": "HRZ"}, {"id": 34, "name": "Minato Namikaze (Edo Tensei)", "slide": 2, "ultis": ["Dewa Petir Terbang: Langkah Kilat Badai Ruang Waktu, Jurus Nol"], "short1v1": "Minato (Edo)", "shortTeam": "MNE"}, {"id": 35, "name": "Minato Namikaze", "slide": 2, "ultis": ["Kilat Kuning", "Dewa Petir Terbang: Langkah Dua"], "short1v1": "Minato", "shortTeam": "MNT"}, {"id": 36, "name": "Tsunade", "slide": 2, "ultis": ["Tanah Murni Kayangan"], "short1v1": "Tsunade", "shortTeam": "TSN"}, {"id": 37, "name": "Danzou Shimura", "slide": 2, "ultis": ["Izanagi"], "short1v1": "Danzou", "shortTeam": "DNZ"}, {"id": 38, "name": "Kakashi Hatake (Masa Kecil)", "slide": 2, "ultis": ["Pedang Cahaya Putih"], "short1v1": "Kakashi (Kecil)", "shortTeam": "KKC"}, {"id": 39, "name": "Obito Uchiha (Masa Kecil)", "slide": 2, "ultis": ["Elemen Api: Teknik Api Phoenix"], "short1v1": "Obito (Kecil)", "shortTeam": "OBC"}, {"id": 40, "name": "Rin Nohara", "slide": 2, "ultis": ["Amukan Ekor Tiga"], "short1v1": "Rin", "shortTeam": "RIN"}, {"id": 41, "name": "Naruto Uzumaki (The Last)", "slide": 2, "ultis": ["Kombo 5.000 Naruto Uzumaki"], "short1v1": "Naruto (The Last)", "shortTeam": "NTL"}, {"id": 42, "name": "Sasuke Uchiha (The Last)", "slide": 2, "ultis": ["Chidori: Penghancur Bintang"], "short1v1": "Sasuke (The Last)", "shortTeam": "STL"}, {"id": 43, "name": "Hanabi Hyuga", "slide": 2, "ultis": ["Aliran Hanabi: 8 Trigraf 64 Tapak"], "short1v1": "Hanabi", "shortTeam": "HNB"}, {"id": 44, "name": "Hanabi Hyuga (Shippuden)", "slide": 2, "ultis": ["Aliran Hanabi: 8 Trigraf 64 Tapak"], "short1v1": "Hanabi (Shippuden)", "shortTeam": "HNS"}, {"id": 45, "name": "Robot Naruto", "slide": 2, "ultis": ["Meriam Siluman Berekor"], "short1v1": "Robot Naruto", "shortTeam": "RBN"}, {"id": 46, "name": "Kurenai", "slide": 2, "ultis": ["Ilusi Iblis: Ikatan Pohon Kemarian - Tebaran Kelopak"], "short1v1": "Kurenai", "shortTeam": "KRN"}, {"id": 47, "name": "Sasuke Uchiha (Rinne Sharingan)", "slide": 3, "ultis": ["Takemikazuchi", "Elemen Kobaran: Susanoo Kagutsuchi"], "short1v1": "Sasuke (Rinnegan)", "shortTeam": "SRN"}, {"id": 48, "name": "Sasuke Uchiha (Mangekyou Sharingan Abadi)", "slide": 3, "ultis": ["Pedang Kagutsuchi", "Elemen Kobaran: Susanoo Kagutsuchi"], "short1v1": "Sasuke (EMS)", "shortTeam": "SEA"}, {"id": 49, "name": "Sasuke Uchiha (Pertemuan Lima Kage)", "slide": 3, "ultis": ["Susanoo"], "short1v1": "Sasuke (5 Kage)", "shortTeam": "S5K"}, {"id": 50, "name": "Sasuke Uchiha (Taka)", "slide": 3, "ultis": ["Amaterasu"], "short1v1": "Sasuke (Taka)", "shortTeam": "STK"}, {"id": 51, "name": "Sasuke Uchiha", "slide": 3, "ultis": ["Kirin", "Tombak Chidori"], "short1v1": "Sasuke", "shortTeam": "SSK"}, {"id": 52, "name": "Orochimaru", "slide": 3, "ultis": ["Teknik Pawang Ular Besar"], "short1v1": "Orochimaru", "shortTeam": "ORC"}, {"id": 53, "name": "Kabuto Yakushi (Mode Petapa)", "slide": 3, "ultis": ["Teknik Petapa: Reinkarnasi Anorganik"], "short1v1": "Kabuto (Petapa)", "shortTeam": "KBP"}, {"id": 54, "name": "Kabuto Yakushi (Jubah Ular)", "slide": 3, "ultis": ["Pemanggilan: Edo Tensei"], "short1v1": "Kabuto (Ular)", "shortTeam": "KBU"}, {"id": 55, "name": "Kabuto Yakushi", "slide": 3, "ultis": ["Teknik Terlarang: Teknik Jiwa Mati"], "short1v1": "Kabuto", "shortTeam": "KBT"}, {"id": 56, "name": "Suigetsu Hozuki", "slide": 3, "ultis": ["Jeram: Teknik Transformasi Air"], "short1v1": "Suigetsu", "shortTeam": "SGT"}, {"id": 57, "name": "Juugo", "slide": 3, "ultis": ["Tinju Piston: Jurus Nol"], "short1v1": "Juugo", "shortTeam": "JUG"}, {"id": 58, "name": "Karin", "slide": 3, "ultis": ["Tamparan Ekstrem"], "short1v1": "Karin", "shortTeam": "KRN"}, {"id": 59, "name": "Kimimaro", "slide": 3, "ultis": ["Tarian Tunas Pakis"], "short1v1": "Kimimaro", "shortTeam": "KMM"}, {"id": 60, "name": "Jiroubou", "slide": 3, "ultis": ["Tinju Arahat"], "short1v1": "Jiroubou", "shortTeam": "JRB"}, {"id": 61, "name": "Kidoumaru", "slide": 3, "ultis": ["Panah Laba-Laba: Koyak"], "short1v1": "Kidoumaru", "shortTeam": "KDM"}, {"id": 62, "name": "Sakon/Ukon", "slide": 3, "ultis": ["Rentetan Tinju: Rakshasa"], "short1v1": "Sakon/Ukon", "shortTeam": "SKN"}, {"id": 63, "name": "Tayuya", "slide": 3, "ultis": ["Seruling Setan: Rantai Suara Ilusi"], "short1v1": "Tayuya", "shortTeam": "TYY"}, {"id": 64, "name": "Gaara (Perang Dunia Ninja)", "slide": 4, "ultis": ["Makam Gurun", "Peti Segel Pasir", "Serangan Tombak Penjara Pasir"], "short1v1": "Gaara (Perang)", "shortTeam": "GPD"}, {"id": 65, "name": "Gaara (Pertemuan Lima Kage)", "slide": 4, "ultis": ["Peti Segel Pasir", "Serangan Tombak Penjara Pasir", "Makam Gurun"], "short1v1": "Gaara (5 Kage)", "shortTeam": "G5K"}, {"id": 66, "name": "Gaara", "slide": 4, "ultis": ["Peti Segel Pasir", "Serangan Tombak Penjara Pasir", "Makam Gurun"], "short1v1": "Gaara", "shortTeam": "GRA"}, {"id": 67, "name": "Kankurou", "slide": 4, "ultis": ["Teknik Hitam Rahasia: 3 Tragedi", "Teknik Hitam Rahasia: Pentas Kalajengking Api"], "short1v1": "Kankurou", "shortTeam": "KNK"}, {"id": 68, "name": "Temari", "slide": 4, "ultis": ["Bantingan Topan"], "short1v1": "Temari", "shortTeam": "TMR"}, {"id": 69, "name": "Raikage: A", "slide": 4, "ultis": ["Bom Liger", "Lightning Straight"], "short1v1": "Raikage A", "shortTeam": "RKA"}, {"id": 70, "name": "Killer Bee (Samehada)", "slide": 4, "ultis": ["Lariat"], "short1v1": "Killer Bee (Samehada)", "shortTeam": "KBS"}, {"id": 71, "name": "Killer Bee", "slide": 4, "ultis": ["Lariat"], "short1v1": "Killer Bee", "shortTeam": "KBE"}, {"id": 72, "name": "Darui", "slide": 4, "ultis": ["Elemen Badai: Hitam Berburu"], "short1v1": "Darui", "shortTeam": "DRI"}, {"id": 73, "name": "Oonoki", "slide": 4, "ultis": ["Elemen Debu: Teknik Belah Dunia Purba"], "short1v1": "Oonoki", "shortTeam": "ONK"}, {"id": 74, "name": "Mei Terumi", "slide": 4, "ultis": ["Elemen Korosi: Asam Pelarut"], "short1v1": "Mei", "shortTeam": "MEI"}, {"id": 75, "name": "Mifune", "slide": 4, "ultis": ["Tebas Wujud"], "short1v1": "Mifune", "shortTeam": "MFN"}, {"id": 76, "name": "Yugito Nii", "slide": 4, "ultis": ["Mangkuk Api Kucing"], "short1v1": "Yugito", "shortTeam": "YGT"}, {"id": 77, "name": "Yagura", "slide": 4, "ultis": ["Hempasan Laut Badai"], "short1v1": "Yagura", "shortTeam": "YGR"}, {"id": 78, "name": "Roushi", "slide": 4, "ultis": ["Letusan Sungai Berapi"], "short1v1": "Roushi", "shortTeam": "RSH"}, {"id": 79, "name": "Han", "slide": 4, "ultis": ["Melompat Lima Gunung"], "short1v1": "Han", "shortTeam": "HAN"}, {"id": 80, "name": "Utakata", "slide": 4, "ultis": ["Muatan Gelembung"], "short1v1": "Utakata", "shortTeam": "UTK"}, {"id": 81, "name": "Fuu", "slide": 4, "ultis": ["Ledakan Sisik"], "short1v1": "Fuu", "shortTeam": "FUU"}, {"id": 82, "name": "Kazekage 4: Rasa", "slide": 4, "ultis": ["Elemen Magnet: Pemakaman Pasir Emas"], "short1v1": "Rasa (Kazekage 4)", "shortTeam": "KZ4"}, {"id": 83, "name": "Raikage 3", "slide": 4, "ultis": ["Tikaman Neraka Satu Jari: Ekstrem"], "short1v1": "Raikage 3", "shortTeam": "RK3"}, {"id": 84, "name": "Tsuchikage 2", "slide": 4, "ultis": ["Elemen Debu: Teknik Pemisah Belah Dunia Purba"], "short1v1": "Tsuchikage 2", "shortTeam": "TC2"}, {"id": 85, "name": "Mizukage 2", "slide": 4, "ultis": ["Teknik Pistol Air"], "short1v1": "Mizukage 2", "shortTeam": "MZ2"}, {"id": 86, "name": "Hanzou", "slide": 4, "ultis": ["Teknik Ninja: Kabut Sabit"], "short1v1": "Hanzou", "shortTeam": "HNZ"}, {"id": 87, "name": "Nenek Chiyo", "slide": 4, "ultis": ["3 Harta Isap Lebur"], "short1v1": "Nenek Chiyo", "shortTeam": "CHY"}, {"id": 88, "name": "Madara Uchiha (Enam Jalan)", "slide": 5, "ultis": ["Meteor Cakrawala"], "short1v1": "Madara (Enam Jalan)", "shortTeam": "M6J"}, {"id": 89, "name": "Madara Uchiha (Edo Tensei: Lepas)", "slide": 5, "ultis": ["Bintang Pengguncang Langit", "Susanoo Sempurna"], "short1v1": "Madara (Edo)", "shortTeam": "MDE"}, {"id": 90, "name": "Madara Uchiha", "slide": 5, "ultis": ["Bintang Pengguncang Langit"], "short1v1": "Madara", "shortTeam": "MDR"}, {"id": 91, "name": "Kaguya Otsutsuki", "slide": 5, "ultis": ["Bola Maut Pencari Makna"], "short1v1": "Kaguya", "shortTeam": "KGY"}, {"id": 92, "name": "Indra Otsutsuki", "slide": 5, "ultis": ["Tujuh Petir Dewa"], "short1v1": "Indra", "shortTeam": "IND"}, {"id": 93, "name": "Asura Otsutsuki", "slide": 5, "ultis": ["Seribu Tangan Sejati: Tapak Spiral"], "short1v1": "Asura", "shortTeam": "ASR"}, {"id": 94, "name": "Obito Uchiha (Jinchuuriki Ekor Sepuluh)", "slide": 5, "ultis": ["Pedang Dewa: Pedang Nunoboko"], "short1v1": "Obito (Jinchuuriki)", "shortTeam": "OBJ"}, {"id": 95, "name": "Obito Uchiha", "slide": 5, "ultis": ["Pemanggilan: Ekor Sepuluh"], "short1v1": "Obito", "shortTeam": "OBT"}, {"id": 96, "name": "Tobi (Perang Dunia Ninja)", "slide": 5, "ultis": ["Pemanggilan: Patung Iblis Asing"], "short1v1": "Tobi (Perang)", "shortTeam": "TPD"}, {"id": 97, "name": "Tobi", "slide": 5, "ultis": ["Teknik Ladang Ranjau"], "short1v1": "Tobi", "shortTeam": "TOB"}, {"id": 98, "name": "Pria Bertopeng", "slide": 5, "ultis": ["Pemanggilan: Ekor Sembilan"], "short1v1": "Pria Bertopeng", "shortTeam": "PBM"}, {"id": 99, "name": "Obito Uchiha (Pelarian)", "slide": 5, "ultis": ["Elemen Kayu: Neraka Berontak"], "short1v1": "Obito (Pelarian)", "shortTeam": "OBP"}, {"id": 100, "name": "Pain", "slide": 5, "ultis": ["Enam Jalan Samsara"], "short1v1": "Pain", "shortTeam": "PAN"}, {"id": 101, "name": "Nagato", "slide": 5, "ultis": ["Chibaku Tensei"], "short1v1": "Nagato", "shortTeam": "NGT"}, {"id": 102, "name": "Konan", "slide": 5, "ultis": ["Tarian Kertas: Hukuman"], "short1v1": "Konan", "shortTeam": "KNN"}, {"id": 103, "name": "Itachi Uchiha", "slide": 5, "ultis": ["Pedang Totsuka", "Tsukoyomi", "Yasaka no Magatama"], "short1v1": "Itachi", "shortTeam": "ITC"}, {"id": 104, "name": "Kisame Hoshigaki", "slide": 5, "ultis": ["Elemen Air: Terjangan Ombak", "Elemen Air: Teknik Peluru Hiu Besar"], "short1v1": "Kisame", "shortTeam": "KSM"}, {"id": 105, "name": "Kakuzu", "slide": 5, "ultis": ["Dendam Bumi: Tembakan Terakhir"], "short1v1": "Kakuzu", "shortTeam": "KKZ"}, {"id": 106, "name": "Hidan", "slide": 5, "ultis": ["Teknik Kutukan: Kuasa Darah Maut"], "short1v1": "Hidan", "shortTeam": "HDN"}, {"id": 107, "name": "Deidara", "slide": 5, "ultis": ["C3"], "short1v1": "Deidara", "shortTeam": "DDR"}, {"id": 108, "name": "Sasori", "slide": 5, "ultis": ["Dunia Pasir Besi"], "short1v1": "Sasori", "shortTeam": "SSR"}, {"id": 109, "name": "Hagoromo Otsutsuki", "slide": 5, "ultis": ["Cahaya Petapa Halilintar"], "short1v1": "Hagoromo", "shortTeam": "HGR"}, {"id": 110, "name": "Naruto Uzumaki (Masa Kecil)", "slide": 6, "ultis": ["Kombo 2.000 Naruto Uzumaki"], "short1v1": "Naruto (Kecil)", "shortTeam": "NKC"}, {"id": 111, "name": "Sasuke Uchiha (Masa Kecil - Pakaian Akhir)", "slide": 6, "ultis": ["Chidori"], "short1v1": "Sasuke (Kecil Akhir)", "shortTeam": "SKA"}, {"id": 112, "name": "Sasuke Uchiha (Masa Kecil)", "slide": 6, "ultis": ["Chidori"], "short1v1": "Sasuke (Kecil)", "shortTeam": "SKC"}, {"id": 113, "name": "Sakura Haruno (Masa Kecil)", "slide": 6, "ultis": ["Sakura Agung"], "short1v1": "Sakura (Kecil)", "shortTeam": "SKK"}, {"id": 114, "name": "Shikamaru Nara (Masa Kecil)", "slide": 6, "ultis": ["Teknik Ninja: Jurus Bayangan Cekik"], "short1v1": "Shikamaru (Kecil)", "shortTeam": "SMC"}, {"id": 115, "name": "Chouji Akimichi (Masa Kecil)", "slide": 6, "ultis": ["Teknik Super Besar"], "short1v1": "Chouji (Kecil)", "shortTeam": "CJC"}, {"id": 116, "name": "Ino Yamanaka (Masa Kecil)", "slide": 6, "ultis": ["Teknik Ninja: Seribu Taburan Bunga"], "short1v1": "Ino (Kecil)", "shortTeam": "INC"}, {"id": 117, "name": "Neji Hyuga (Masa Kecil)", "slide": 6, "ultis": ["8 Trigraf 64 Tapak"], "short1v1": "Neji (Kecil)", "shortTeam": "NJC"}, {"id": 118, "name": "Rock Lee (Masa Kecil)", "slide": 6, "ultis": ["Tangkai Teratai"], "short1v1": "Rock Lee (Kecil)", "shortTeam": "LKC"}, {"id": 119, "name": "Tenten (Masa Kecil)", "slide": 6, "ultis": ["Kendali Senjata: Seribu Amukan Pedang"], "short1v1": "Tenten (Kecil)", "shortTeam": "TTC"}, {"id": 120, "name": "Kiba Inuzuka (Masa Kecil)", "slide": 6, "ultis": ["Taring Serigala"], "short1v1": "Kiba (Kecil)", "shortTeam": "KBC"}, {"id": 121, "name": "Shino Aburame (Masa Kecil)", "slide": 6, "ultis": ["Serangga Parasit: Lonceng"], "short1v1": "Shino (Kecil)", "shortTeam": "SHC"}, {"id": 122, "name": "Hinata Hyuga (Masa Kecil)", "slide": 6, "ultis": ["8 Trigraf Pelindung 64 Tapak"], "short1v1": "Hinata (Kecil)", "shortTeam": "HNC"}, {"id": 123, "name": "Gaara (Masa Kecil)", "slide": 6, "ultis": ["Makam Agung Arus Pasir"], "short1v1": "Gaara (Kecil)", "shortTeam": "GKC"}, {"id": 124, "name": "Kankurou (Masa Kecil)", "slide": 6, "ultis": ["Teknik Hitam Rahasia: Mekanisme Sekali Basmi"], "short1v1": "Kankurou (Kecil)", "shortTeam": "KKC"}, {"id": 125, "name": "Temari (Masa Kecil)", "slide": 6, "ultis": ["Pemanggilan: Tarian Penggal Cepat"], "short1v1": "Temari (Kecil)", "shortTeam": "TMC"}, {"id": 126, "name": "Zabuza Momochi", "slide": 6, "ultis": ["Membunuh Tanpa Suara"], "short1v1": "Zabuza", "shortTeam": "ZBZ"}, {"id": 127, "name": "Haku", "slide": 6, "ultis": ["Teknik Rahasia: Cermin Kristal Es"], "short1v1": "Haku", "shortTeam": "HAK"}, {"id": 128, "name": "Boruto Uzumaki \"Karma\"", "slide": 7, "ultis": ["\"Karma\" Rasengan"], "short1v1": "Boruto (Karma)", "shortTeam": "BKM"}, {"id": 129, "name": "Boruto Uzumaki (Ujian Chuunin)", "slide": 7, "ultis": ["Spiral Boruto Storm"], "short1v1": "Boruto (Chuunin)", "shortTeam": "BCN"}, {"id": 130, "name": "Boruto Uzumaki (Alat Ilmiah Ninja)", "slide": 7, "ultis": ["Overwind Ride"], "short1v1": "Boruto (Alat Ilmiah)", "shortTeam": "BAI"}, {"id": 131, "name": "Boruto Uzumaki", "slide": 7, "ultis": ["Boruto Strike"], "short1v1": "Boruto", "shortTeam": "BRT"}, {"id": 132, "name": "Sarada Uchiha (Chidori)", "slide": 7, "ultis": ["Penghancur Langit Hujan Sore", "Tarian Bunga Chidori"], "short1v1": "Sarada (Chidori)", "shortTeam": "SRC"}, {"id": 133, "name": "Sarada Uchiha", "slide": 7, "ultis": ["Formasi Shuriken Uchiha: Petir Musim Semi"], "short1v1": "Sarada", "shortTeam": "SRD"}, {"id": 134, "name": "Mitsuki", "slide": 7, "ultis": ["Teknik Petapa: Petir Ular Agung - Ular Putih", "Ular Liar"], "short1v1": "Mitsuki", "shortTeam": "MTK"}, {"id": 135, "name": "Kawaki", "slide": 7, "ultis": ["\"Karma\" Ombak Pemakaman Besar"], "short1v1": "Kawaki", "shortTeam": "KWK"}, {"id": 136, "name": "Naruto Uzumaki (Mode Barion)", "slide": 7, "ultis": ["Rasengan Barion: Hujaman Langit"], "short1v1": "Naruto (Barion)", "shortTeam": "NBR"}, {"id": 137, "name": "Naruto Uzumaki (Boruto)", "slide": 7, "ultis": ["Sejati: Rasen Shuriken Siluman Berekor"], "short1v1": "Naruto (Boruto)", "shortTeam": "NBT"}, {"id": 138, "name": "Sasuke Uchiha (Bayangan Pendukung)", "slide": 7, "ultis": ["Chibaku Tensei: Kirin Kembar"], "short1v1": "Sasuke (Bayangan)", "shortTeam": "SBP"}, {"id": 139, "name": "Sasuke Uchiha (Boruto)", "slide": 7, "ultis": ["Chibaku Tensei: Langit Runtuh"], "short1v1": "Sasuke (Boruto)", "shortTeam": "SBT"}, {"id": 140, "name": "Sakura Haruno (Boruto)", "slide": 7, "ultis": ["Mekar Penuh: Tumbukan Bunga Sakura", "Teknik Seratus Penyembuhan"], "short1v1": "Sakura (Boruto)", "shortTeam": "SKB"}, {"id": 141, "name": "Hinata Hyuga (Boruto)", "slide": 7, "ultis": ["Tinju Singa Kembar Halus"], "short1v1": "Hinata (Boruto)", "shortTeam": "HNB"}, {"id": 142, "name": "Shikamaru Nara (Boruto)", "slide": 7, "ultis": ["Teknik Pengumpul Bayangan"], "short1v1": "Shikamaru (Boruto)", "shortTeam": "SMB"}, {"id": 143, "name": "Chouji Akimichi (Boruto)", "slide": 7, "ultis": ["Tamparan Super"], "short1v1": "Chouji (Boruto)", "shortTeam": "CJB"}, {"id": 144, "name": "Ino Yamanaka (Boruto)", "slide": 7, "ultis": ["Taman Bunga Tarian Kelopak"], "short1v1": "Ino (Boruto)", "shortTeam": "INB"}, {"id": 145, "name": "Sai Yamanaka (Boruto)", "slide": 7, "ultis": ["Teknik Ninja: Gambar Replika Monster - Gerombolan"], "short1v1": "Sai (Boruto)", "shortTeam": "SAB"}, {"id": 146, "name": "Rock Lee (Boruto)", "slide": 7, "ultis": ["Angin Panas Konoha"], "short1v1": "Rock Lee (Boruto)", "shortTeam": "LBT"}, {"id": 147, "name": "Tenten (Boruto)", "slide": 7, "ultis": ["Kendali Senjata: Bola Besi Raksasa"], "short1v1": "Tenten (Boruto)", "shortTeam": "TTB"}, {"id": 148, "name": "Kiba Inuzuka (Boruto)", "slide": 7, "ultis": ["Taring Serigala"], "short1v1": "Kiba (Boruto)", "shortTeam": "KBB"}, {"id": 149, "name": "Shino Aburame (Boruto)", "slide": 7, "ultis": ["Teknik Rahasia: Rawa Serangga"], "short1v1": "Shino (Boruto)", "shortTeam": "SHB"}, {"id": 150, "name": "Kawaki (Evolusi \"Karma\")", "slide": 7, "ultis": ["Peluru Pelarungan Kekaisaran"], "short1v1": "Kawaki (Karma)", "shortTeam": "KWE"}, {"id": 151, "name": "Boruto Uzumaki (Evolusi \"Karma\")", "slide": 7, "ultis": ["Rasengan Bola Raksasa: Penghancur Langit"], "short1v1": "Boruto (Karma 2)", "shortTeam": "BKE"}, {"id": 152, "name": "Gaara (Boruto)", "slide": 8, "ultis": ["Makam Gurun", "Peti Segel Pasir", "Serangan Tombak Penjara Pasir"], "short1v1": "Gaara (Boruto)", "shortTeam": "GBT"}, {"id": 153, "name": "Kankurou (Boruto)", "slide": 8, "ultis": ["Teknik Hitam Rahasia: 3 Tragedi", "Teknik Hitam Rahasia: Pentas Kalajengking Api"], "short1v1": "Kankurou (Boruto)", "shortTeam": "KNB"}, {"id": 154, "name": "Temari Nara (Boruto)", "slide": 8, "ultis": ["Bantingan Topan"], "short1v1": "Temari (Boruto)", "shortTeam": "TMB"}, {"id": 155, "name": "Darui (Boruto)", "slide": 8, "ultis": ["Elemen Badai: Hitam Berburu"], "short1v1": "Darui (Boruto)", "shortTeam": "DRB"}, {"id": 156, "name": "Momoshiki Otsutsuki", "slide": 8, "ultis": ["3 Dewa Setan"], "short1v1": "Momoshiki", "shortTeam": "MMS"}, {"id": 157, "name": "Kinshiki Otsutsuki", "slide": 8, "ultis": ["Amukan Marah"], "short1v1": "Kinshiki", "shortTeam": "KNS"}, {"id": 158, "name": "Jigen", "slide": 8, "ultis": ["Sukunahikona: Kotak Kubah Jatuh"], "short1v1": "Jigen", "shortTeam": "JGN"}, {"id": 159, "name": "Koji Kashin", "slide": 8, "ultis": ["Teknik Petapa: Kipas Lima"], "short1v1": "Koji Kashin", "shortTeam": "KOJ"}, {"id": 160, "name": "Delta", "slide": 8, "ultis": ["Aerobatic Strike"], "short1v1": "Delta", "shortTeam": "DLT"}, {"id": 161, "name": "Boro", "slide": 8, "ultis": ["Ledakan Mesiu Bom Besar"], "short1v1": "Boro", "shortTeam": "BOR"}, {"id": 162, "name": "Isshiki Otsutsuki", "slide": 8, "ultis": ["Daikokuten Sukunahikona Ambruk Ganda"], "short1v1": "Isshiki", "shortTeam": "ISK"}, {"id": 163, "name": "Nanashi Uchiha", "slide": 8, "ultis": ["8.000 Tombak: Api Ganas Yang Memukau"], "short1v1": "Nanashi", "shortTeam": "NNS"}];

    const NARUTO_CHAR_MAP = {};
    NARUTO_CHARACTERS.forEach(c => {
      NARUTO_CHAR_MAP[c.name.toLowerCase().trim()] = c;
    });

        function normCharName(str) {
      if (!str) return '';
      return String(str).toLowerCase()
        .normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
        .replace(/ō/g, 'o').replace(/ū/g, 'u')
        .replace(/ou/g, 'o').replace(/uu/g, 'u')
        .replace(/[^a-z0-9]/g, '');
    }

    const NARUTO_NORM_MAP = {};
    NARUTO_CHARACTERS.forEach(c => {
      NARUTO_NORM_MAP[normCharName(c.name)] = c;
    });

    function findNarutoChar(name) {
      if (!name) return null;
      const clean = String(name).trim().toLowerCase();
      if (NARUTO_CHAR_MAP[clean]) return NARUTO_CHAR_MAP[clean];
      const n = normCharName(clean);
      if (NARUTO_NORM_MAP[n]) return NARUTO_NORM_MAP[n];
      for (const c of NARUTO_CHARACTERS) {
        if (normCharName(c.name).startsWith(n)) return c;
      }
      return null;
    }

    function getCharShort1v1(charName) {
      if (!charName) return '-';
      const c = String(charName).trim();
      if (!c || c === '-' || c === 'TBA' || c === 'Belum Pilih' || c === 'Belum Pilih / TBA') return '-';
      
      const found = findNarutoChar(c);
      if (found && found.short1v1) return found.short1v1;

      const parenMatch = c.match(/^([^(]+)\s*\(([^)]+)\)/);
      if (parenMatch) {
        const firstName = parenMatch[1].trim().split(/\s+/)[0];
        let variant = parenMatch[2].trim();
        variant = variant.replace(/^masa\s+/i, '').replace(/^mode\s+/i, '');
        return `${firstName} (${variant})`;
      }
      return c.split(/\s+/)[0] || c;
    }

    function getCharShortTeam(charName) {
      if (!charName) return 'CHR';
      const c = String(charName).trim();
      if (!c || c === '-' || c === 'TBA' || c === 'Belum Pilih' || c === 'Belum Pilih / TBA') return '-';

      const found = findNarutoChar(c);
      if (found && found.shortTeam) return found.shortTeam;

      const alpha = c.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
      return (alpha.length >= 3 ? alpha.slice(0, 3) : alpha) || 'CHR';
    }

    function getPlayerDefaultUlti(charName) {
      if (!charName) return '';
      const parts = String(charName).split('/').map(s => s.trim()).filter(Boolean);
      const ultis = [];
      for (const p of parts) {
        const found = findNarutoChar(p);
        if (found && found.ultis && found.ultis.length > 0) {
          ultis.push(found.ultis[0]);
        }
      }
      return ultis.join(' / ');
    }

    function formatChar(char) {
      if (!char) return '-';
      let c = String(char).trim();
      if (!c || c === 'TBA' || c === 'Belum Pilih' || c === 'Belum Pilih / TBA' || c.toLowerCase() === 'tba') {
        return '-';
      }

      const parts = c.split('/').map(s => s.trim()).filter(Boolean);
      // Mode Duo (2) & Tim (3): maks 3 huruf per char
      if (parts.length >= 2) {
        return parts.map(p => getCharShortTeam(p)).join(' / ');
      }

      // Mode 1v1: nama depan + jenis char (contoh: Naruto (Kecil))
      return getCharShort1v1(parts[0] || c);
    }

    function applyMarquee(wrap, container, speed = 30, pauseStart = 3000, pauseEnd = 2500) {
      if (!wrap || !container) return;

      const currentText = wrap.innerText || wrap.textContent || '';
      // JIKA ANIMASI SUDAH BERJALAN DENGAN TEKS YANG SAMA: JANGAN DI-RESET!
      // Ini mencegah glitch teks melompat/berhenti saat ada pembaruan data live.
      if (wrap._marqueeAnim && wrap._marqueeText === currentText) {
        return;
      }
      wrap._marqueeText = currentText;

      if (wrap._marqueeAnim) {
        try { wrap._marqueeAnim.cancel(); } catch (e) { }
        wrap._marqueeAnim = null;
      }
      wrap.style.transform = 'none';

      const diff = wrap.scrollWidth - container.clientWidth;
      if (diff <= 3) return; // Tidak overflow, tidak perlu scroll

      const dist = -(diff + 16);
      // Durasi berjalan: dihitung proporsional dari jarak pixel overflow / speed px per detik
      const walkDuration = Math.max(3000, (diff / speed) * 1000);
      const totalDuration = pauseStart + walkDuration + pauseEnd;

      const p1 = pauseStart / totalDuration; // tahan di awal
      const p2 = (pauseStart + walkDuration) / totalDuration; // sampai di ujung kanan

      try {
        wrap._marqueeAnim = wrap.animate([
          { transform: 'translateX(0px)', offset: 0 },
          { transform: 'translateX(0px)', offset: p1 },
          { transform: `translateX(${dist}px)`, offset: p2 },
          { transform: `translateX(${dist}px)`, offset: 0.999 },
          { transform: 'translateX(0px)', offset: 1.0 }
        ], {
          duration: totalDuration,
          iterations: Infinity,
          easing: 'linear'
        });
      } catch (err) {
        wrap.style.setProperty('--marquee-dist', `${dist}px`);
        wrap.classList.add('marquee-active');
      }
    }

    function getTierCls(type) {
      if (type === 'SROBOT' || type === 'SB' || type === 'SROBOT++' || type === 'FAST' || type === 'VVIP') return 'fast';
      if (type === 'VIP') return 'vip';
      return 'free';
    }

    function getTierLabel(type) {
      if (type === 'SROBOT' || type === 'SB' || type === 'SROBOT++' || type === 'FAST' || type === 'VVIP') return 'SB';
      if (type === 'VIP') return 'VIP';
      return 'FREE';
    }

    function getStatusLabel(status) {
      if (status === 'CLOSED' || status === 'PENUH') return 'PENUH';
      return 'AKTIF';
    }

    function getStatusCls(status) {
      if (status === 'CLOSED' || status === 'PENUH') return 'closed';
      return 'open';
    }

    function sanitizeState(st) {
      if (!st || typeof st !== 'object') return;
      const fixP = (p) => {
        if (!p) return;
        if (p.type === 'VVIP' || p.type === 'FAST' || p.type === 'SROBOT++' || p.type === 'SB') p.type = 'SROBOT';
        if (!p.type || p.type === 'SAVE' || (p.type !== 'SROBOT' && p.type !== 'VIP' && p.type !== 'FREE')) {
          p.type = 'FREE';
        }
        if (!p.ticketMode) {
          if (p.char && (p.char.includes('/') || p.char.includes(',') || p.char.includes('&'))) {
            p.ticketMode = 'TIM';
          } else {
            p.ticketMode = '1v1';
          }
        }
      };
      fixP(st.p1);
      fixP(st.p2);
      if (Array.isArray(st.queue)) st.queue.forEach(fixP);
      if (!Array.isArray(st.pendingApprovals)) st.pendingApprovals = [];
      if (Array.isArray(st.saved)) {
        st.saved.forEach(p => {
          fixP(p);
          if (!p.savedAt) {
            const numId = Number(p.id);
            if (!isNaN(numId) && numId > 1600000000000 && numId < 2500000000000) {
              p.savedAt = numId;
            } else {
              p.savedAt = Date.now();
            }
          }
        });
      }
      if (!st.tierStatus || typeof st.tierStatus !== 'object') {
        st.tierStatus = { 'SROBOT': 'AKTIF', 'VIP': 'AKTIF', 'FREE': 'AKTIF' };
      } else {
        if (st.tierStatus['SROBOT++'] || st.tierStatus.FAST || st.tierStatus.VVIP) {
          if (!st.tierStatus['SROBOT']) st.tierStatus['SROBOT'] = st.tierStatus['SROBOT++'] || st.tierStatus.FAST || st.tierStatus.VVIP;
          delete st.tierStatus['SROBOT++'];
          delete st.tierStatus.FAST;
          delete st.tierStatus.VVIP;
        }
        if (!st.tierStatus.VIP) st.tierStatus.VIP = 'AKTIF';
        if (!st.tierStatus.FREE) st.tierStatus.FREE = 'AKTIF';
        ['SROBOT', 'VIP', 'FREE'].forEach(k => {
          if (st.tierStatus[k] === 'OPEN') st.tierStatus[k] = 'AKTIF';
          if (st.tierStatus[k] === 'CLOSED') st.tierStatus[k] = 'PENUH';
          if (!st.tierStatus[k]) st.tierStatus[k] = 'AKTIF';
        });
      }

      if (st.masterMode === 'SOLO') st.masterMode = '1v1';
      if (st.masterMode === 'TRIO') st.masterMode = 'TIM';
      if (!st.masterMode || !['1v1', 'DUO', 'TIM'].includes(st.masterMode)) {
        st.masterMode = '1v1';
      }

      // Normalisasi Arena per mode
      if (!st.arena1v1 || typeof st.arena1v1 !== 'object') {
        st.arena1v1 = st.arenaSolo || { p1: st.p1 || null, p2: st.p2 || null };
      }
      if (!st.arenaDuo || typeof st.arenaDuo !== 'object') {
        st.arenaDuo = { p1: null, p2: null };
      }
      if (!st.arenaTim || typeof st.arenaTim !== 'object') {
        st.arenaTim = st.arenaTrio || { p1: null, p2: null };
      }

      const activeArena = (st.masterMode === 'DUO') ? st.arenaDuo : ((st.masterMode === 'TIM') ? st.arenaTim : st.arena1v1);
      st.p1 = activeArena.p1 || null;
      st.p2 = activeArena.p2 || null;

      const normalizeRecList = (list) => {
        const existing = Array.isArray(list) ? list.slice() : [];
        while (existing.length < 3) {
          existing.push({ rank: existing.length + 1, name: '-', char: '-', ws: 0 });
        }
        return existing.slice(0, 3).map((r, i) => ({
          rank: i + 1,
          name: r.name || '-',
          char: formatChar(r.char),
          ws: Number(r.ws) || 0
        }));
      };

      if (!Array.isArray(st.topRecords1v1) || st.topRecords1v1.length < 3) {
        st.topRecords1v1 = normalizeRecList(st.topRecordsSolo || st.topRecords);
      } else {
        st.topRecords1v1 = normalizeRecList(st.topRecords1v1);
      }
      st.topRecordsSolo = st.topRecords1v1;

      if (!Array.isArray(st.topRecordsDuo) || st.topRecordsDuo.length < 3) {
        st.topRecordsDuo = normalizeRecList(st.topRecordsDuo);
      } else {
        st.topRecordsDuo = normalizeRecList(st.topRecordsDuo);
      }

      if (!Array.isArray(st.topRecordsTim) || st.topRecordsTim.length < 3) {
        st.topRecordsTim = normalizeRecList(st.topRecordsTrio || st.topRecordsTim);
      } else {
        st.topRecordsTim = normalizeRecList(st.topRecordsTim);
      }
      st.topRecordsTrio = st.topRecordsTim;

      let activeRecList = st.topRecords1v1;
      if (st.masterMode === 'DUO') activeRecList = st.topRecordsDuo;
      else if (st.masterMode === 'TIM') activeRecList = st.topRecordsTim;

      st.topRecords = activeRecList;
      st.topRecord = st.topRecords[0];

      if (!st.topWsOverlay || typeof st.topWsOverlay !== 'object') {
        st.topWsOverlay = { '1v1': true, 'DUO': true, 'TIM': true };
      }

      if (typeof st.vipConsecutiveCount !== 'number' || isNaN(st.vipConsecutiveCount) || st.vipConsecutiveCount < 0) {
        st.vipConsecutiveCount = 0;
      } else if (st.vipConsecutiveCount > 3) {
        st.vipConsecutiveCount = 3;
      }
    }

    function applyState(incoming) {
      if (!incoming || typeof incoming !== 'object') return;
      state = incoming;
      sanitizeState(state);
      // Tulis ke localStorage agar tab lain sync
      try {
        localStorage.setItem('koth_naruto_state', JSON.stringify(state));
      } catch (e) { }
      renderOverlay();
    }

    // Aturan Mutlak Antrean: Maks 3x VIP naik arena -> 1x FREE -> 3x VIP dst
    function interleaveVipFree(vipList, freeList, currentVipStreak = 0) {
      const result = [];
      let vIdx = 0;
      let fIdx = 0;

      // Jatah VIP ronde pertama sebelum giliran 1x FREE (maksimal 3x VIP berturut-turut)
      const firstBlockVipCount = Math.max(0, 3 - (currentVipStreak || 0));
      if (firstBlockVipCount > 0) {
        for (let i = 0; i < firstBlockVipCount && vIdx < vipList.length; i++) {
          result.push(vipList[vIdx++]);
        }
      }

      while (vIdx < vipList.length || fIdx < freeList.length) {
        // Giliran 1x FREE
        for (let i = 0; i < 1 && fIdx < freeList.length; i++) {
          result.push(freeList[fIdx++]);
        }
        // Giliran 3x VIP
        for (let i = 0; i < 3 && vIdx < vipList.length; i++) {
          result.push(vipList[vIdx++]);
        }
      }

      return result;
    }

    function renderOverlay() {
      if (!state) return;

      // Announcement Ticker (NOTE)
      const tickerEl = document.getElementById('overlay-ticker');
      const tickerWrap = document.querySelector('.ticker-marquee-wrap');
      const tickerText = document.getElementById('ticker-text');
      if (state.announcement && state.announcement.trim()) {
        let cleanNote = state.announcement.trim();
        if (/^NOTE\s*:\s*/i.test(cleanNote)) {
          cleanNote = cleanNote.replace(/^NOTE\s*:\s*/i, '');
        }
        tickerText.innerText = cleanNote;
        tickerEl.style.display = 'flex';
      } else {
        tickerEl.style.display = 'none';
        if (tickerText && tickerText._marqueeAnim) {
          try { tickerText._marqueeAnim.cancel(); } catch (e) { }
          tickerText._marqueeAnim = null;
          tickerText.style.transform = 'none';
        }
      }

      // Top Record WS Banner (Top WS 1v1, TOP WS DUO, TOP WS TIM)
      const topRecordEl = document.getElementById('overlay-top-record');
      const recordLabel = document.getElementById('record-label') || document.querySelector('.record-label');
      const recContent = document.getElementById('record-text');

      const enabledModes = ['1v1', 'DUO', 'TIM'].filter(m => {
        if (!state.topWsOverlay || typeof state.topWsOverlay !== 'object') return true;
        return state.topWsOverlay[m] !== false;
      });

      if (enabledModes.length === 0) {
        if (topRecordEl) topRecordEl.style.display = 'none';
      } else {
        if (topRecordEl) topRecordEl.style.display = 'flex';

        const getModeRecords = (m) => {
          let list = [];
          if (m === '1v1') list = state.topRecords1v1 || state.topRecordsSolo;
          else if (m === 'DUO') list = state.topRecordsDuo;
          else if (m === 'TIM') list = state.topRecordsTim || state.topRecordsTrio;
          if (!Array.isArray(list) || list.length < 3) {
            list = [
              { rank: 1, name: '-', char: '-', ws: 0 },
              { rank: 2, name: '-', char: '-', ws: 0 },
              { rank: 3, name: '-', char: '-', ws: 0 }
            ];
          }
          return list.slice(0, 3);
        };

        const medals = ['🥇', '🥈', '🥉'];
        const renderItems = (recs) => {
          return recs.map((r, i) => {
            const medal = medals[i] || `#${i + 1}`;
            const rName = escapeHtml(r.name && r.name !== '-' ? r.name : '-');
            const rChar = escapeHtml(formatChar(r.char));
            const rWs = (r.ws !== undefined && r.ws !== null) ? r.ws : 0;
            return `
              <span class="rec-item">
                <span class="rec-medal">${medal}</span>
                <span class="rec-username">${rName}</span>
                <span class="rec-sep">|</span>
                <span class="rec-char">${rChar}</span>
                <span class="rec-ws">${rWs} WS</span>
              </span>
            `;
          }).join('<span class="rec-divider">•</span>');
        };

        if (recordLabel) {
          recordLabel.innerText = 'TOP WS:';
          recordLabel.style.display = 'inline-block';
        }

        if (recContent) {
          const blocks = enabledModes.map(m => {
            const col = m === '1v1' ? '#ffd700' : (m === 'DUO' ? '#60a5fa' : '#c084fc');
            return `
              <span class="rec-mode-block" style="display:inline-flex; align-items:center; gap:5px;">
                <span class="rec-mode-title" style="font-weight:900; color:${col}; font-size:0.86rem; letter-spacing:0.5px;">${m}:</span>
                ${renderItems(getModeRecords(m))}
              </span>
            `;
          }).join('<span class="rec-mode-sep" style="margin: 0 10px; color: rgba(255,255,255,0.7); font-weight:900; font-size: 0.95rem;">;</span>');
          recContent.innerHTML = blocks;
        }
      }

      // Slot 1 (P1) - Menggunakan Label Tier (Bukan King/Penantang)
      const slot1 = document.getElementById('slot-p1');
      const p1RoleTag = document.getElementById('p1-role-tag');
      const p1Name = document.getElementById('p1-name');
      const p1Char = document.getElementById('p1-char');
      const p1Ws = document.getElementById('p1-ws');
      const p1Tickets = document.getElementById('p1-tickets');
      const p1Bonus = document.getElementById('p1-bonus');

      if (state.p1) {
        const rawT1 = getTierLabel(state.p1.type);
        const clsT1 = getTierCls(state.p1.type);
        slot1.className = 'player-slot ' + clsT1;
        p1RoleTag.className = 'p-role-tag ' + clsT1;
        p1RoleTag.innerText = rawT1;

        p1Name.innerText = state.p1.name;
        const fChar1 = formatChar(state.p1.char);
        p1Char.innerText = fChar1;
        p1Char.title = state.p1.char || fChar1;
        const p1UltiEl = document.getElementById('p1-ulti');
        if (p1UltiEl) {
          const p1Ulti = state.p1.ulti || getPlayerDefaultUlti(state.p1.char);
          if (p1Ulti && p1Ulti !== '-' && p1Ulti !== 'TBA') {
            p1UltiEl.innerText = `⚡ ${p1Ulti}`;
            p1UltiEl.title = `Ultimate: ${p1Ulti}`;
            p1UltiEl.style.display = 'block';
          } else {
            p1UltiEl.style.display = 'none';
          }
        }
        if (fChar1.length > 25) {
          p1Char.style.fontSize = '0.70rem';
        } else if (fChar1.length > 18) {
          p1Char.style.fontSize = '0.75rem';
        } else {
          p1Char.style.fontSize = '';
        }
        p1Ws.innerText = state.p1.ws || 0;

        // Match: 1+1 lalu 5 detik otomatis terjumlah jadi 2
        let p1TicketText = state.p1.matchesLeft;
        if (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) {
          p1TicketText = `${state.p1.bonusBase}+1`;
          const msLeft = state.p1.bonusAnimUntil - Date.now();
          setTimeout(() => renderOverlay(), msLeft + 50);
        }
        p1Tickets.innerText = p1TicketText;

        if (p1Bonus) {
          if (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) {
            p1Bonus.innerText = '+1 MATCH';
            p1Bonus.style.display = 'inline-block';
          } else {
            p1Bonus.style.display = 'none';
          }
        }
      } else {
        slot1.className = 'player-slot';
        p1RoleTag.className = 'p-role-tag';
        p1RoleTag.innerText = 'P1';

        p1Name.innerText = "Menunggu...";
        p1Char.innerText = "-";
        const p1UltiEl = document.getElementById('p1-ulti');
        if (p1UltiEl) p1UltiEl.style.display = 'none';
        p1Ws.innerText = "0";
        p1Tickets.innerText = "-";
        if (p1Bonus) p1Bonus.style.display = 'none';
      }

      // Slot 2 (P2) - Menggunakan Label Tier (Bukan King/Penantang)
      const slot2 = document.getElementById('slot-p2');
      const p2RoleTag = document.getElementById('p2-role-tag');
      const p2Name = document.getElementById('p2-name');
      const p2Char = document.getElementById('p2-char');
      const p2Ws = document.getElementById('p2-ws');
      const p2Tickets = document.getElementById('p2-tickets');
      const p2Bonus = document.getElementById('p2-bonus');

      if (state.p2) {
        const rawT2 = getTierLabel(state.p2.type);
        const clsT2 = getTierCls(state.p2.type);
        slot2.className = 'player-slot ' + clsT2;
        p2RoleTag.className = 'p-role-tag ' + clsT2;
        p2RoleTag.innerText = rawT2;

        p2Name.innerText = state.p2.name;
        const fChar2 = formatChar(state.p2.char);
        p2Char.innerText = fChar2;
        p2Char.title = state.p2.char || fChar2;
        const p2UltiEl = document.getElementById('p2-ulti');
        if (p2UltiEl) {
          const p2Ulti = state.p2.ulti || getPlayerDefaultUlti(state.p2.char);
          if (p2Ulti && p2Ulti !== '-' && p2Ulti !== 'TBA') {
            p2UltiEl.innerText = `⚡ ${p2Ulti}`;
            p2UltiEl.title = `Ultimate: ${p2Ulti}`;
            p2UltiEl.style.display = 'block';
          } else {
            p2UltiEl.style.display = 'none';
          }
        }
        if (fChar2.length > 25) {
          p2Char.style.fontSize = '0.70rem';
        } else if (fChar2.length > 18) {
          p2Char.style.fontSize = '0.75rem';
        } else {
          p2Char.style.fontSize = '';
        }
        p2Ws.innerText = state.p2.ws || 0;

        // Match: 1+1 lalu 5 detik otomatis terjumlah jadi 2
        let p2TicketText = state.p2.matchesLeft;
        if (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) {
          p2TicketText = `${state.p2.bonusBase}+1`;
          const msLeft = state.p2.bonusAnimUntil - Date.now();
          setTimeout(() => renderOverlay(), msLeft + 50);
        }
        p2Tickets.innerText = p2TicketText;

        if (p2Bonus) {
          if (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) {
            p2Bonus.innerText = '+1 MATCH';
            p2Bonus.style.display = 'inline-block';
          } else {
            p2Bonus.style.display = 'none';
          }
        }
      } else {
        slot2.className = 'player-slot';
        p2RoleTag.className = 'p-role-tag';
        p2RoleTag.innerText = 'P2';

        p2Name.innerText = "Menunggu...";
        p2Char.innerText = "-";
        const p2UltiEl = document.getElementById('p2-ulti');
        if (p2UltiEl) p2UltiEl.style.display = 'none';
        p2Ws.innerText = "0";
        p2Tickets.innerText = "-";
        if (p2Bonus) p2Bonus.style.display = 'none';
      }

      // Render Queue or Saved list with Match Count
      const isSavedMode = (state.overlayListMode === 'saved');
      const qSectionTitle = document.getElementById('queue-section-title');
      const qContainer = document.getElementById('queue-list');
      const qTotal = document.getElementById('queue-total-count');

      if (isSavedMode) {
        if (qSectionTitle) qSectionTitle.innerText = 'LIST TABUNGAN';
        const sList = (state.saved || []).slice();
        // Urutkan sesuai abjad username (A-Z)
        sList.sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base', numeric: true }));

        qTotal.innerText = `${sList.length} Pemain`;

        if (sList.length === 0) {
          qContainer.innerHTML = '<div class="empty-queue">(Tabungan Kosong)</div>';
        } else {
          qContainer.innerHTML = '';
          let globalSavedIdx = 0;

          // SROBOT (SB) tetap dipisahkan section-nya jika ada
          const fastSaved = sList.filter(p => getTierLabel(p.type) === 'SB');
          const vipFreeSaved = sList.filter(p => getTierLabel(p.type) !== 'SB');

          const savedSections = [];
          if (fastSaved.length > 0) {
            savedSections.push({
              key: 'SROBOT',
              label: 'SROBOT',
              cls: 'fast',
              players: fastSaved
            });
          }
          savedSections.push({
            key: 'VIPFREE',
            label: 'VIP & FREE',
            cls: 'vipfree',
            players: vipFreeSaved
          });

          savedSections.forEach(sec => {
            if (sec.players.length === 0 && sec.key === 'SROBOT') return;

            const tierDiv = document.createElement('div');
            tierDiv.className = `tier-section ${sec.cls}`;
            tierDiv.innerHTML = `
              <div class="tier-header ${sec.cls}">
                <div style="display:flex; align-items:center; gap:6px;">
                  <span>${sec.label}</span>
                  <span>(${sec.players.length})</span>
                </div>
                <span class="th-ticket">MATCH</span>
              </div>
            `;

            if (sec.players.length === 0) {
              const emptyDiv = document.createElement('div');
              emptyDiv.className = 'tier-empty';
              emptyDiv.innerText = '(Kosong)';
              tierDiv.appendChild(emptyDiv);
            } else {
              sec.players.forEach(p => {
                globalSavedIdx++;
                const pTierLabel = getTierLabel(p.type);
                const rowCls = getTierCls(p.type);
                const row = document.createElement('div');
                row.className = `queue-row ${rowCls}`;
                row.innerHTML = `
                  <div class="q-left">
                    <div class="q-text-wrap">
                      <span class="q-idx">#${globalSavedIdx}</span>
                      <span class="q-name">${escapeHtml(p.name)}</span>
                      <span class="q-sep">|</span>
                      <span class="q-char">${escapeHtml(formatChar(p.char))}</span>
                    </div>
                  </div>
                  <div class="q-right">
                    <span class="q-badge ${rowCls}">${pTierLabel}</span>
                    <span class="q-ticket">${p.matchesLeft}</span>
                  </div>
                `;
                tierDiv.appendChild(row);
              });
            }

            qContainer.appendChild(tierDiv);
          });
        }
      } else {
        if (qSectionTitle) qSectionTitle.innerText = 'MATCH QUEUE';
        
        // SROBOT dipisahkan; VIP & FREE disatukan dengan aturan mutlak: maks 3x VIP naik arena -> 1x FREE -> 3x VIP dst
        const fastPlayers = [];
        const vipPlayers = [];
        const freePlayers = [];

        (state.queue || []).forEach(p => {
          const pTier = getTierLabel(p.type);
          if (pTier === 'SB') fastPlayers.push(p);
          else if (pTier === 'VIP') vipPlayers.push(p);
          else freePlayers.push(p);
        });

        const interleavedVipFree = interleaveVipFree(vipPlayers, freePlayers, state.vipConsecutiveCount || 0);
        const qList = [...fastPlayers, ...interleavedVipFree];

        qTotal.innerText = `${qList.length} Pemain`;

        if (qList.length === 0) {
          qContainer.innerHTML = '<div class="empty-queue">(Match Queue Kosong)</div>';
        } else {
          qContainer.innerHTML = '';
          let globalIdx = 0;

          // 1. Section SROBOT (SB)
          const rawStatusFast = (state.tierStatus && (state.tierStatus['SROBOT'] || state.tierStatus['SROBOT++'] || state.tierStatus['FAST'] || state.tierStatus['VVIP'])) || 'AKTIF';
          const statusFastLabel = getStatusLabel(rawStatusFast);
          const statusClassFast = getStatusCls(rawStatusFast);

          const fastSec = document.createElement('div');
          fastSec.className = 'tier-section fast';
          fastSec.innerHTML = `
            <div class="tier-header fast">
              <div style="display:flex; align-items:center; gap:6px;">
                <span>SROBOT</span>
                <span class="tier-status-tag ${statusClassFast}">${statusFastLabel}</span>
                <span>(${fastPlayers.length})</span>
              </div>
              <span class="th-ticket">MATCH</span>
            </div>
          `;

          if (fastPlayers.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'tier-empty';
            emptyDiv.innerText = '(Kosong)';
            fastSec.appendChild(emptyDiv);
          } else {
            fastPlayers.forEach(p => {
              globalIdx++;
              const row = document.createElement('div');
              row.className = 'queue-row fast';
              row.innerHTML = `
                <div class="q-left">
                  <div class="q-text-wrap">
                    <span class="q-idx">#${globalIdx}</span>
                    <span class="q-name">${escapeHtml(p.name)}</span>
                    <span class="q-sep">|</span>
                    <span class="q-char">${escapeHtml(formatChar(p.char))}</span>
                  </div>
                </div>
                <div class="q-right">
                  <span class="q-badge fast">SB</span>
                  <span class="q-ticket">${p.matchesLeft}</span>
                </div>
              `;
              fastSec.appendChild(row);
            });
          }
          qContainer.appendChild(fastSec);

          // 2. Section VIP & FREE
          const vipFreeSec = document.createElement('div');
          vipFreeSec.className = 'tier-section vipfree';

          const rawStatusVip = (state.tierStatus && state.tierStatus['VIP']) || 'AKTIF';
          const rawStatusFree = (state.tierStatus && state.tierStatus['FREE']) || 'AKTIF';
          const statusVipLabel = getStatusLabel(rawStatusVip);
          const statusFreeLabel = getStatusLabel(rawStatusFree);
          const statusClassVip = getStatusCls(rawStatusVip);
          const statusClassFree = getStatusCls(rawStatusFree);

          vipFreeSec.innerHTML = `
            <div class="tier-header vipfree">
              <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <span>VIP & FREE</span>
                <span class="tier-status-tag ${statusClassVip}">VIP: ${statusVipLabel}</span>
                <span class="tier-status-tag ${statusClassFree}">FREE: ${statusFreeLabel}</span>
                <span>(${interleavedVipFree.length})</span>
              </div>
              <span class="th-ticket">MATCH</span>
            </div>
          `;

          if (interleavedVipFree.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'tier-empty';
            emptyDiv.innerText = '(Kosong)';
            vipFreeSec.appendChild(emptyDiv);
          } else {
            interleavedVipFree.forEach(p => {
              globalIdx++;
              const pTierLabel = getTierLabel(p.type);
              const rowCls = getTierCls(p.type);
              const row = document.createElement('div');
              row.className = `queue-row ${rowCls}`;
              row.innerHTML = `
                <div class="q-left">
                  <div class="q-text-wrap">
                    <span class="q-idx">#${globalIdx}</span>
                    <span class="q-name">${escapeHtml(p.name)}</span>
                    <span class="q-sep">|</span>
                    <span class="q-char">${escapeHtml(formatChar(p.char))}</span>
                  </div>
                </div>
                <div class="q-right">
                  <span class="q-badge ${rowCls}">${pTierLabel}</span>
                  <span class="q-ticket">${p.matchesLeft}</span>
                </div>
              `;
              vipFreeSec.appendChild(row);
            });
          }
          qContainer.appendChild(vipFreeSec);
        }
      }

      // Tampilkan banner FREE TUTUP jika status tier FREE tutup/penuh
      const rawStatusFree = (state.tierStatus && state.tierStatus['FREE']) || 'AKTIF';
      const isFreeClosed = (rawStatusFree === 'CLOSED' || rawStatusFree === 'PENUH' || rawStatusFree === 'TUTUP');
      const freeBanner = document.getElementById('free-closed-banner');
      if (freeBanner) {
        freeBanner.style.display = isFreeClosed ? 'block' : 'none';
      }

      // Marquee & auto-scroll triggers after DOM paint
      requestAnimationFrame(() => {
        // TOP WS Marquee (kecepatan pelan & santai dibaca)
        const recWrap = document.querySelector('.record-marquee-wrap');
        const recContent = document.getElementById('record-text');
        if (recWrap && recContent) {
          applyMarquee(recContent, recWrap, 18, 3500, 3000);
        }

        // Ticker Marquee
        if (tickerWrap && tickerText && state.announcement && state.announcement.trim()) {
          applyMarquee(tickerText, tickerWrap);
        }

        // Queue Items Marquee
        document.querySelectorAll('.q-left').forEach(el => {
          const wrap = el.querySelector('.q-text-wrap');
          if (wrap) {
            applyMarquee(wrap, el);
          }
        });

        // Start vertical scroll
        startSmartAutoScroll();
      });
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Auto-Scroll: Scroll ke bawah -> Berhenti 3 dtk -> Langsung JUMP kembali ke paling atas
    let autoScrollTimer = null;
    let isUserInteracting = false;

    function startSmartAutoScroll() {
      const container = document.getElementById('queue-list');
      if (!container) return;

      // Jika scroll timer sudah aktif berjalan, jangan bunuh & reset ke atas agar tidak glitch freeze
      if (autoScrollTimer) return;

      let stateMode = 'paused_at_top'; // 'paused_at_top', 'scrolling', 'paused_at_bottom'
      let pauseTicks = 60; // 3 detik awal di posisi atas (60 * 50ms = 3000ms)
      let currentScrollY = container.scrollTop || 0;

      autoScrollTimer = setInterval(() => {
        if (isUserInteracting) return;
        const maxScroll = container.scrollHeight - container.clientHeight;
        if (maxScroll <= 4) return;

        if (pauseTicks > 0) {
          pauseTicks--;
          if (pauseTicks === 0) {
            if (stateMode === 'paused_at_top') {
              stateMode = 'scrolling';
            } else if (stateMode === 'paused_at_bottom') {
              // LANGSUNG JUMP KE PALING ATAS! (bukan scroll balik)
              currentScrollY = 0;
              container.scrollTop = 0;
              stateMode = 'paused_at_top';
              pauseTicks = 60; // Tahan 3 detik di paling atas
            }
          }
          return;
        }

        if (stateMode === 'scrolling') {
          currentScrollY += 1;
          container.scrollTop = Math.round(currentScrollY);

          if (container.scrollTop >= maxScroll - 1 || currentScrollY >= maxScroll) {
            stateMode = 'paused_at_bottom';
            pauseTicks = 60; // Tahan 3 detik di bawah
          }
        }
      }, 50);

      container.onmouseenter = () => { isUserInteracting = true; };
      container.onmouseleave = () => { isUserInteracting = false; currentScrollY = container.scrollTop; };
      container.ontouchstart = () => { isUserInteracting = true; };
      container.ontouchend = () => { setTimeout(() => { isUserInteracting = false; currentScrollY = container.scrollTop; }, 2000); };
    }

    // Cloud Realtime Sync (MQTT over WebSockets) + MySQL DB API + Local BroadcastChannel
    const STREAM_KEY = <?= json_encode($roomKey) ?>;
    const streamKey = STREAM_KEY;
    const MQTT_TOPIC = `koth/naruto/${STREAM_KEY}`;
    let _lastServerJson = '';

    // Instant Realtime Local Sync (0ms BroadcastChannel across all browser tabs / OBS / TikTok Studio)
    const kothSyncChannel = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('koth_broadcast_' + STREAM_KEY) : null;
    if (kothSyncChannel) {
      kothSyncChannel.onmessage = (event) => {
        if (event.data && event.data.state) {
          _lastServerJson = JSON.stringify(event.data.state);
          applyState(event.data.state);
        }
      };
    }

    const APP_BASE_URL = <?= json_encode($appBaseUrl) ?>;

    function getAppBaseUrl() {
      if (typeof APP_BASE_URL === 'string' && APP_BASE_URL.startsWith('/')) {
        return `${window.location.origin}${APP_BASE_URL}`;
      }
      let p = window.location.pathname.replace(/\/(index|admin|overlay)(\.(html|php))?$/i, '/').replace(/\/admin\/?$/i, '/');
      if (!p.endsWith('/')) p += '/';
      return `${window.location.origin}${p}`;
    }

    function getStateApiUrl() {
      return `${getAppBaseUrl()}api/state.php?room=${encodeURIComponent(STREAM_KEY)}`;
    }

    function fetchStateFromDb() {
      if (!window.location.protocol.startsWith('http')) return;
      fetch(getStateApiUrl(), { cache: 'no-store' })
        .then(res => res.ok ? res.json() : null)
        .then(data => {
          if (!data || typeof data !== 'object') return;
          const raw = JSON.stringify(data);
          if (raw === _lastServerJson) return;
          _lastServerJson = raw;
          applyState(data);
        })
        .catch(() => { });
    }

    function loadFromLocalStorage() {
      if (window.location.protocol.startsWith('http')) {
        // Pada HTTP / PHP, state awal sudah di-load dari database MySQL
        try {
          if (state && typeof state === 'object') {
            localStorage.setItem('koth_naruto_state', JSON.stringify(state));
          }
        } catch (e) { }
        renderOverlay();
        return;
      }
      try {
        const raw = localStorage.getItem('koth_naruto_state');
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            state = parsed;
            sanitizeState(state);
          }
        }
      } catch (e) { }
      renderOverlay();
    }

    function connectCloudMQTT() {
      if (typeof mqtt === 'undefined') return;
      try {
        const client = mqtt.connect('wss://broker.emqx.io:8084/mqtt', {
          clientId: 'ov_' + Math.random().toString(16).substring(2, 8),
          clean: true,
          reconnectPeriod: 2500
        });

        client.on('connect', () => {
          client.subscribe(MQTT_TOPIC, { qos: 0 });
        });

        client.on('message', (topic, payload) => {
          try {
            const raw = payload.toString();
            const data = JSON.parse(raw);
            _lastServerJson = raw;
            applyState(data);
          } catch (err) { }
        });
      } catch (err) { }
    }

    // Initialize: Render data awal -> Sinkronisasi LocalStorage / MySQL / BroadcastChannel / MQTT
    sanitizeState(state);
    renderOverlay();
    loadFromLocalStorage();
    window.addEventListener('DOMContentLoaded', renderOverlay);
    window.addEventListener('storage', (e) => {
      if (e.key === 'koth_naruto_state' && e.newValue) {
        try {
          const parsed = JSON.parse(e.newValue);
          if (parsed && typeof parsed === 'object') {
            applyState(parsed);
          }
        } catch (err) { }
      }
    });
    connectCloudMQTT();
    fetchStateFromDb();
    if (window.location.protocol.startsWith('http')) {
      setInterval(fetchStateFromDb, 2000);
    }
  </script>
</body>

</html>