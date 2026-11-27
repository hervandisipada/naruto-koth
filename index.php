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
      /* Palette Selaras dengan Overlay: #F7F7F7, #EEEEEE, #393E46, #929AAB, #222831 */
      --c-white: #F7F7F7;
      --c-light: #EEEEEE;
      --c-dark: #393E46;
      --c-slate: #929AAB;
      --bg-main: #222831;
      --bg-panel: rgba(57, 62, 70, 0.96);
      --border-subtle: #929AAB;
      --accent-gold: #F7F7F7;
      --text-main: #F7F7F7;
      --text-muted: #EEEEEE;
      --text-dim: #929AAB;
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

    body {
      background-color: var(--bg-main);
      color: var(--text-main);
      min-height: 100vh;
      padding: 10px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .app-wrapper {
      width: 100%;
      max-width: 900px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    /* TOP HEADER BAR */
    .top-header {
      background: var(--bg-panel);
      border: 1px solid var(--border-subtle);
      border-radius: 8px;
      padding: 8px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.5);
    }

    .brand-title {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: var(--c-white);
    }

    .brand-badge {
      background: var(--c-white);
      color: var(--bg-main);
      font-weight: 900;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.85rem;
    }

    .brand-text {
      font-weight: 900;
      font-size: 1.05rem;
      letter-spacing: 0.5px;
    }

    .top-nav-links {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .btn-top {
      font-size: 0.75rem;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 4px;
      text-decoration: none;
      border: 1px solid var(--border-subtle);
      color: var(--c-white);
      background: rgba(255, 255, 255, 0.06);
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .btn-top:hover {
      background: var(--c-white);
      color: var(--bg-main);
      border-color: var(--c-white);
    }

    .sync-badge {
      font-size: 0.72rem;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: 4px;
      background: rgba(34, 197, 94, 0.15);
      border: 1px solid #22c55e;
      color: #86efac;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .sync-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 6px #22c55e;
    }

    /* TICKER / NOTE MARQUEE (SELARAS OVERLAY) */
    .overlay-ticker {
      background: rgba(0, 0, 0, 0.45);
      border-radius: 8px;
      padding: 6px 12px;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 8px;
      border-left: 4px solid #ffffff;
      color: #ffffff;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      border-right: 1px solid rgba(255, 255, 255, 0.2);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.5);
    }

    .ticker-label {
      color: #ffffff;
      font-weight: 900;
      font-size: 0.92rem;
      letter-spacing: 0.8px;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .ticker-content {
      color: #ffffff;
      font-weight: 700;
      font-size: 0.90rem;
    }

    /* ARENA LIVE DISPLAY (MATCH-BOX PERSIS STYLE OVERLAY) */
    .arena-card {
      background: var(--bg-panel);
      border-radius: 8px;
      padding: 8px 12px;
      border: 1px solid var(--border-subtle);
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.5);
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .arena-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.15);
      padding-bottom: 6px;
    }

    .live-title-row {
      font-size: 0.85rem;
      font-weight: 900;
      letter-spacing: 0.8px;
      color: var(--c-white);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .live-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 8px #22c55e;
    }

    .live-mode-badge {
      font-size: 0.72rem;
      font-weight: 900;
      padding: 2px 8px;
      border-radius: 4px;
      background: var(--c-white);
      color: var(--bg-main);
      letter-spacing: 0.5px;
    }

    /* MATCH ARENA - PERSIS OVERLAY MATCH-BOX */
    .match-box {
      display: flex;
      flex-direction: column;
      gap: 2px;
      background: rgba(0, 0, 0, 0.25);
      border-radius: 8px;
      padding: 5px;
      border: 1px solid var(--border-subtle);
    }

    .player-slot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 4px 10px;
      border-radius: 6px;
      transition: background 0.15s ease;
      background: rgba(255, 255, 255, 0.03);
      border-left: 3px solid var(--c-slate);
      min-height: 44px;
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
    .player-slot.vvip,
    .player-slot.srobot,
    .player-slot.p1.fast,
    .player-slot.p1.srobot {
      background: rgba(168, 85, 247, 0.12);
      border-left: 3px solid #a855f7;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.15);
    }

    .player-slot.vip,
    .player-slot.p1.vip {
      background: rgba(255, 215, 0, 0.05);
      border-left: 3px solid #FFD700;
    }

    .player-slot.free,
    .player-slot.p1.free {
      background: rgba(255, 255, 255, 0.02);
      border-left: 3px solid rgba(146, 154, 171, 0.35);
    }

    /* P2 MIRRORED BORDER & LAYOUT */
    #arena-p2-slot,
    .player-slot.p2 {
      border-left: none;
      border-right: 3px solid var(--c-slate);
    }

    #arena-p2-slot.fast,
    #arena-p2-slot.vvip,
    #arena-p2-slot.srobot,
    .player-slot.p2.fast,
    .player-slot.p2.srobot {
      border-left: none;
      border-right: 3px solid #a855f7;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.15);
    }

    #arena-p2-slot.vip,
    .player-slot.p2.vip {
      border-left: none;
      border-right: 3px solid #FFD700;
    }

    #arena-p2-slot.free,
    .player-slot.p2.free {
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
    .p-role-tag.vvip,
    .p-role-tag.srobot {
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

    .p-king-badge {
      font-size: 0.65rem;
      font-weight: 900;
      padding: 1px 6px;
      border-radius: 3px;
      background: rgba(255, 215, 0, 0.18);
      color: #FFD700;
      border: 1px solid rgba(255, 215, 0, 0.45);
      letter-spacing: 0.5px;
      white-space: nowrap;
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

    /* MAIN 2-COLUMN LAYOUT */
    .columns-grid {
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      gap: 12px;
    }

    @media (max-width: 768px) {
      .columns-grid {
        grid-template-columns: 1fr;
      }
    }

    /* CARD CONTAINER */
    .card-panel {
      background: var(--bg-panel);
      border: 1px solid var(--border-subtle);
      border-radius: 8px;
      padding: 14px;
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.5);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .card-panel-title {
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: 0.8px;
      color: var(--c-white);
      border-bottom: 1px solid rgba(255, 255, 255, 0.15);
      padding-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* LOCKED MODE BANNER (ADMIN CONTROLLED) */
    .locked-mode-banner {
      background: rgba(0, 0, 0, 0.35);
      border: 1px solid var(--border-subtle);
      border-radius: 6px;
      padding: 8px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .locked-mode-left {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .locked-mode-title {
      font-size: 0.82rem;
      font-weight: 900;
      color: var(--c-white);
      letter-spacing: 0.5px;
    }

    .locked-mode-sub {
      font-size: 0.70rem;
      color: var(--c-slate);
      font-weight: 600;
    }

    .locked-mode-pill {
      background: #ffffff;
      color: var(--bg-main);
      font-size: 0.75rem;
      font-weight: 900;
      padding: 3px 8px;
      border-radius: 4px;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }

    /* FORM GROUPS */
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .form-label {
      font-size: 0.78rem;
      font-weight: 800;
      color: var(--c-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .form-control {
      background: #222831;
      border: 1px solid var(--border-subtle);
      border-radius: 6px;
      padding: 8px 10px;
      color: var(--c-white);
      font-size: 0.88rem;
      font-weight: 700;
      outline: none;
      transition: all 0.15s ease;
      width: 100%;
    }

    .form-control:focus {
      border-color: var(--c-white);
      box-shadow: 0 0 8px rgba(255, 255, 255, 0.25);
    }

    .form-control:disabled {
      background: rgba(34, 40, 49, 0.5);
      color: var(--c-slate);
      cursor: not-allowed;
      border-color: rgba(146, 154, 171, 0.3);
    }

    /* TIER SELECTION (SELARAS OVERLAY) */
    .tier-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px;
    }

    .tier-btn {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--border-subtle);
      border-radius: 6px;
      padding: 8px 4px;
      text-align: center;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      transition: all 0.15s ease;
    }

    .tier-btn:hover:not(.disabled) {
      border-color: var(--c-white);
    }

    .tier-btn.active.tier-srobot {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 60%, #9333ea 100%);
      border: 1px solid #d8b4fe;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.3);
    }

    .tier-btn.active.tier-vip {
      background: #FFD700;
      border: 1px solid #FFD700;
      color: #000000;
      box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
    }

    .tier-btn.active.tier-vip .tier-btn-title,
    .tier-btn.active.tier-vip .tier-btn-sub {
      color: #000000 !important;
    }

    .tier-btn.active.tier-free {
      background: rgba(255, 255, 255, 0.18);
      border: 1.5px solid #ffffff;
      color: #ffffff;
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
    }

    .tier-btn.disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }

    .tier-btn-title {
      font-size: 0.80rem;
      font-weight: 900;
      color: var(--c-white);
    }

    .tier-btn-sub {
      font-size: 0.65rem;
      color: var(--c-slate);
      font-weight: 700;
    }

    .tier-status-pill {
      font-size: 0.62rem;
      font-weight: 800;
      padding: 1px 5px;
      border-radius: 3px;
    }

    .status-open {
      background: rgba(34, 197, 94, 0.2);
      color: #86efac;
    }

    .status-closed {
      background: rgba(239, 68, 68, 0.25);
      color: #fca5a5;
    }

    /* CHAR PICKER POPUP */
    .char-picker-container {
      position: relative;
    }

    .char-picker-popup {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 999;
      background: #222831;
      border: 2px solid var(--c-white);
      border-radius: 6px;
      max-height: 290px;
      overflow-y: auto;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.9);
      margin-top: 4px;
    }

    .char-picker-header {
      background: #1a1e24;
      padding: 6px;
      border-bottom: 1px solid var(--border-subtle);
      position: sticky;
      top: 0;
      z-index: 2;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .char-sort-tabs {
      display: flex;
      gap: 4px;
    }

    .char-sort-tab-btn {
      flex: 1;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid var(--border-subtle);
      color: var(--c-light);
      font-size: 0.70rem;
      font-weight: 800;
      padding: 4px 6px;
      border-radius: 4px;
      cursor: pointer;
      text-align: center;
    }

    .char-sort-tab-btn.active {
      background: var(--c-white);
      color: var(--bg-main);
      border-color: var(--c-white);
    }

    .char-slide-tabs {
      display: flex;
      overflow-x: auto;
      gap: 4px;
      padding-bottom: 2px;
    }

    .char-slide-tab-btn {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--border-subtle);
      color: var(--c-slate);
      font-size: 0.65rem;
      font-weight: 800;
      padding: 2px 7px;
      border-radius: 3px;
      cursor: pointer;
      white-space: nowrap;
    }

    .char-slide-tab-btn.active {
      background: var(--c-white);
      color: var(--bg-main);
      border-color: var(--c-white);
    }

    .char-group-header {
      background: rgba(255, 255, 255, 0.06);
      padding: 3px 8px;
      font-size: 0.68rem;
      font-weight: 900;
      color: var(--c-white);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .char-picker-item {
      padding: 6px 10px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.82rem;
    }

    .char-picker-item:hover {
      background: rgba(255, 255, 255, 0.12);
    }

    .char-item-name {
      font-weight: 800;
      color: #ffffff;
    }

    .badge-slide {
      background: #393E46;
      color: #F7F7F7;
      font-size: 0.62rem;
      font-weight: 700;
      padding: 1px 4px;
      border-radius: 3px;
    }

    .badge-multi-ulti {
      background: rgba(234, 179, 8, 0.2);
      color: #fef08a;
      border: 1px solid rgba(234, 179, 8, 0.4);
      font-size: 0.62rem;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 3px;
    }

    .badge-single-ulti {
      background: rgba(146, 154, 171, 0.2);
      color: #929AAB;
      font-size: 0.62rem;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 3px;
    }

    /* SUBMIT BUTTON (CENTERED, CLEAN, HIGH CONTRAST) */
    .submit-btn-wrap {
      display: flex;
      justify-content: center;
      margin-top: 24px;
      margin-bottom: 8px;
      width: 100%;
    }

    .btn-submit-reg {
      background: var(--c-white);
      color: var(--bg-main);
      border: 2px solid #ffffff;
      border-radius: 8px;
      padding: 12px 48px;
      font-size: 1.05rem;
      font-weight: 900;
      letter-spacing: 2px;
      cursor: pointer;
      text-transform: uppercase;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
      transition: all 0.18s ease;
      min-width: 220px;
      text-align: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-submit-reg:hover {
      background: #ffffff;
      color: #000000;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 255, 255, 0.25);
    }

    .btn-submit-reg:active {
      transform: translateY(0);
    }

    .btn-submit-reg:hover {
      background: #EEEEEE;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(255, 255, 255, 0.25);
    }

    /* QUEUE LIST (SELARAS OVERLAY) */
    .queue-summary-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.72rem;
      color: var(--c-slate);
      font-weight: 700;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding-bottom: 6px;
    }

    .queue-list-wrap {
      display: flex;
      flex-direction: column;
      gap: 6px;
      max-height: 480px;
      overflow-y: auto;
      padding-right: 2px;
    }

    .queue-item-card {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--border-subtle);
      border-radius: 6px;
      padding: 6px 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .queue-item-card.is-me {
      border: 2px solid var(--c-white);
      background: rgba(255, 255, 255, 0.08);
    }

    .queue-rank-badge {
      font-size: 0.80rem;
      font-weight: 900;
      color: var(--c-white);
      background: #222831;
      border: 1px solid var(--border-subtle);
      border-radius: 4px;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .queue-info-wrap {
      flex: 1;
      min-width: 0;
    }

    .queue-name-row {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 2px;
    }

    .queue-name-text {
      font-weight: 900;
      font-size: 0.88rem;
      color: #ffffff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .queue-char-row {
      font-size: 0.75rem;
      color: var(--c-light);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .p-role-tag {
      font-size: 0.65rem;
      font-weight: 900;
      padding: 1px 5px;
      border-radius: 3px;
      letter-spacing: 0.5px;
      line-height: 1.3;
    }

    .p-role-tag.fast,
    .p-role-tag.srobot {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 60%, #9333ea 100%);
      border: 1px solid #d8b4fe;
      color: #ffffff;
    }

    .p-role-tag.vip {
      background: #FFD700;
      color: #000000;
    }

    .p-role-tag.free {
      background: rgba(255, 255, 255, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.3);
      color: #ffffff;
    }

    /* EMPTY QUEUE */
    .empty-queue-box {
      text-align: center;
      padding: 24px 10px;
      color: var(--c-slate);
      font-size: 0.80rem;
    }

    /* MODAL SUKSES */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      backdrop-filter: blur(4px);
      z-index: 999999;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }

    .modal-box {
      background: #393E46;
      border: 2px solid var(--c-white);
      border-radius: 10px;
      padding: 20px;
      max-width: 400px;
      width: 100%;
      text-align: center;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
      color: #ffffff;
    }

    #reg-toast {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: #222831;
      border: 1px solid var(--c-white);
      color: var(--c-white);
      padding: 8px 18px;
      border-radius: 6px;
      font-weight: 800;
      font-size: 0.85rem;
      opacity: 0;
      pointer-events: none;
      transition: all 0.2s ease;
      z-index: 9999999;
    }

    /* PLAYER SLOT TIER ACCENTS */
    .player-slot.p1.vip {
      border-left: 3px solid #FFD700;
      background: rgba(255, 215, 0, 0.05);
    }

    .player-slot.p1.fast,
    .player-slot.p1.srobot {
      border-left: 3px solid #a855f7;
      background: rgba(168, 85, 247, 0.12);
    }

    .player-slot.p1.free {
      border-left: 3px solid var(--c-slate);
      background: rgba(255, 255, 255, 0.04);
    }

    .player-slot.p2.vip {
      border-right: 3px solid #FFD700;
      background: rgba(255, 215, 0, 0.05);
    }

    .player-slot.p2.fast,
    .player-slot.p2.srobot {
      border-right: 3px solid #a855f7;
      background: rgba(168, 85, 247, 0.12);
    }

    .player-slot.p2.free {
      border-right: 3px solid var(--c-slate);
      background: rgba(255, 255, 255, 0.04);
    }

    /* MATCH & MODE BADGES */
    .q-mode-badge {
      font-size: 0.65rem;
      font-weight: 800;
      color: var(--c-light);
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(146, 154, 171, 0.35);
      padding: 1px 5px;
      border-radius: 3px;
    }

    .q-match-badge {
      font-size: 0.74rem;
      font-weight: 900;
      color: var(--c-white);
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 2px 7px;
      border-radius: 4px;
      white-space: nowrap;
    }

    /* ULTI SELECTION IN SLOTS */
    .slot-ulti-box {
      display: flex;
      align-items: center;
      gap: 8px;
      background: rgba(0, 0, 0, 0.38);
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px solid var(--border-subtle);
      margin-top: 4px;
      transition: border-color 0.18s ease;
    }

    .slot-ulti-box:focus-within {
      border-color: var(--c-white);
    }

    .slot-ulti-label {
      font-size: 0.72rem;
      font-weight: 800;
      color: var(--c-light);
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 3px;
    }

    .slot-ulti-select {
      flex: 1;
      background: transparent;
      border: none;
      outline: none;
      color: var(--c-white);
      font-size: 0.8rem;
      cursor: pointer;
    }

    .slot-ulti-select option {
      background: var(--c-dark);
      color: var(--c-white);
    }

    /* MATCH TYPE BUTTONS */
    .btn-match {
      flex: 1;
      padding: 9px 8px;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 800;
      cursor: pointer;
      border: 1px solid var(--border-subtle);
      background: rgba(0, 0, 0, 0.32);
      color: var(--text-muted);
      transition: all 0.18s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      user-select: none;
    }

    .btn-match:hover {
      background: rgba(255, 255, 255, 0.12);
      color: var(--c-white);
      border-color: rgba(255, 255, 255, 0.3);
    }

    .btn-match.active {
      background: var(--c-white);
      color: var(--bg-main);
      border-color: var(--c-white);
      box-shadow: 0 0 12px rgba(247, 247, 247, 0.3);
    }

    /* TIER HEADER */
    .tier-header-row {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .tier-icon {
      font-size: 0.85rem;
      line-height: 1;
    }

    /* QUEUE RANK BADGES */
    .queue-rank-badge {
      font-size: 0.84rem;
      font-weight: 900;
      color: var(--c-slate);
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid var(--border-subtle);
      border-radius: 6px;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .queue-rank-badge.rank-1 {
      color: #FFD700;
      border-color: #FFD700;
      background: rgba(255, 215, 0, 0.15);
      box-shadow: 0 0 8px rgba(255, 215, 0, 0.25);
    }

    .queue-rank-badge.rank-2 {
      color: var(--c-white);
      border-color: var(--c-light);
      background: rgba(238, 238, 238, 0.15);
    }

    .queue-rank-badge.rank-3 {
      color: #cbd5e1;
      border-color: #94a3b8;
      background: rgba(148, 163, 184, 0.12);
    }
  </style>
</head>

<body>
  <?php
  $sidebarActive = 'home';
  include __DIR__ . '/includes/sidebar.php';
  ?>

  <div class="app-wrapper">

    <!-- TOP HEADER BAR -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- TICKER NOTE MARQUEE (SELARAS OVERLAY) -->
    <div class="overlay-ticker" id="ticker-wrap" style="display: none;">
      <span class="ticker-label">NOTE:</span>
      <span class="ticker-content" id="ticker-text">-</span>
    </div>

    <!-- ARENA LIVE DISPLAY (PERSIS STYLE OVERLAY) -->
    <section class="arena-card" id="arena-card">
      <div class="arena-card-header">
        <div class="live-title-row">
          <span class="live-dot"></span>
          <span class="live-text">LIVE ARENA MATCH</span>
        </div>
        <div class="live-mode-badge" id="arena-mode-badge">1vs1</div>
      </div>

      <!-- Match Arena (Persis Overlay match-box) -->
      <div class="match-box" id="arena-match-box">
        <!-- Player 1 Slot -->
        <div class="player-slot p1" id="arena-p1-slot">
          <div class="p-info-p1">
            <div class="p-header-line">
              <span class="p-role-tag" id="arena-p1-tier">P1</span>
              <span class="p-king-badge" id="arena-p1-tag" style="display:none;">👑 JUARA BERTAHAN</span>
              <div id="arena-p1-name" class="p-name">Menunggu...</div>
            </div>
            <div id="arena-p1-char" class="p-char">-</div>
            <div id="arena-p1-ulti" class="p-ulti" style="display:none;"></div>
          </div>
          <div class="p-right">
            <div class="p-ws">
              WS: <span id="arena-p1-ws">0</span>
              <span id="arena-p1-bonus" class="bonus-badge" style="display:none;">+1 MATCH</span>
            </div>
            <div class="p-ticket">Match: <b id="arena-p1-tickets">0</b></div>
          </div>
        </div>

        <div class="vs-tag">VS</div>

        <!-- Player 2 Slot (Mirrored: WS/Tiket on Left, Tier/Nama/Char on Right) -->
        <div class="player-slot p2" id="arena-p2-slot">
          <div class="p-left-p2">
            <div class="p-ws">
              WS: <span id="arena-p2-ws">0</span>
              <span id="arena-p2-bonus" class="bonus-badge" style="display:none;">+1 MATCH</span>
            </div>
            <div class="p-ticket">Match: <b id="arena-p2-tickets">0</b></div>
          </div>
          <div class="p-info-p2">
            <div class="p-header-line-p2">
              <div id="arena-p2-name" class="p-name">Menunggu Penantang...</div>
              <span class="p-king-badge" id="arena-p2-tag" style="display:none;">⚔️ PENANTANG</span>
              <span class="p-role-tag" id="arena-p2-tier">P2</span>
            </div>
            <div id="arena-p2-char" class="p-char">-</div>
            <div id="arena-p2-ulti" class="p-ulti" style="display:none;"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- MAIN 2-COLUMN LAYOUT -->
    <main class="columns-grid">

      <!-- COLUMN 1: FORMULIR PENDAFTARAN -->
      <section class="card-panel">
        <div class="card-panel-title">
          <span>📝 PENDAFTARAN MATCH</span>
          <span style="font-size:0.70rem; color:var(--c-slate); font-weight:800;">MANDIRI</span>
        </div>

        <!-- LOCKED MODE BANNER (ADMIN CONTROLLED) -->
        <div class="locked-mode-banner">
          <div class="locked-mode-left">
            <span class="locked-mode-title" id="locked-mode-title">🎮 MODE SAAT INI: 1vs1 (SOLO)</span>
            <span class="locked-mode-sub" id="locked-mode-desc">Mode pertandingan dikontrol langsung oleh Admin
              stream</span>
          </div>
          <span class="locked-mode-pill" id="locked-mode-pill">1v1</span>
        </div>

        <form id="form-registration" onsubmit="handleRegistrationSubmit(event)">

          <!-- 1. USERNAME -->
          <div class="form-group">
            <label class="form-label" for="reg-name">
              <span>1. Nama TikTok *</span>
            </label>
            <input type="text" id="reg-name" class="form-control" placeholder="Ketik nama atau Nama TikTok mu..."
              autocomplete="off" required oninput="validatePlayerName()">
            <div id="reg-name-warning"
              style="display:none; margin-top:4px; font-size:0.75rem; font-weight:800; color:#fca5a5; background:rgba(239,68,68,0.2); border:1px solid #ef4444; padding:4px 8px; border-radius:4px;">
            </div>
          </div>

          <!-- 2. PEMILIHAN KARAKTER (OTOMATIS SESUAI MODE ADMIN) -->
          <div class="form-group">
            <label class="form-label">
              <span>2. Pilih Karakter Naruto & Ulti *</span>
            </label>

            <!-- SLOT 1: LEADER / UTAMA -->
            <div id="slot-wrap-1" style="margin-bottom: 6px;">
              <div style="font-size: 0.72rem; font-weight: 800; color: var(--c-light); margin-bottom: 2px;"
                id="slot-label-1">Karakter Utama (Leader):</div>
              <div class="char-picker-container">
                <input type="text" id="reg-char-1" class="form-control"
                  placeholder="Ketik atau klik untuk cari karakter..." autocomplete="off" onfocus="openCharPicker(1)"
                  oninput="filterCharPicker(1)">
                <div id="picker-popup-1" class="char-picker-popup" style="display: none;"></div>
              </div>
              <div class="slot-ulti-box">
                <span class="slot-ulti-label">⚡ Ulti:</span>
                <select id="reg-ulti-1" class="slot-ulti-select" disabled>
                  <option value="">(Pilih karakter dulu)</option>
                </select>
              </div>
            </div>

            <!-- SLOT 2: SUPPORT 1 (DUO & TIM) -->
            <div id="slot-wrap-2" style="display: none; margin-bottom: 6px;">
              <div style="font-size: 0.72rem; font-weight: 800; color: #93c5fd; margin-bottom: 2px;">Karakter 2 (Support
                1):</div>
              <div class="char-picker-container">
                <input type="text" id="reg-char-2" class="form-control" placeholder="Ketik atau klik Karakter 2..."
                  autocomplete="off" onfocus="openCharPicker(2)" oninput="filterCharPicker(2)">
                <div id="picker-popup-2" class="char-picker-popup" style="display: none;"></div>
              </div>
              <div class="slot-ulti-box">
                <span class="slot-ulti-label" style="color: #93c5fd;">⚡ Ulti:</span>
                <select id="reg-ulti-2" class="slot-ulti-select" disabled>
                  <option value="">(Pilih karakter dulu)</option>
                </select>
              </div>
            </div>

            <!-- SLOT 3: SUPPORT 2 (TIM) -->
            <!-- OPSI HANYA 2 KARAKTER UNTUK MODE TIM -->
            <div id="wrap-tim-2char" style="display: none; margin: 6px 0; padding: 7px 10px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px;">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: #fde047; margin: 0;">
                <input type="checkbox" id="check-tim-2char" onchange="toggleTim2Char()" style="width: 16px; height: 16px; accent-color: #eab308; cursor: pointer;">
                <span>Pakai hanya 2 karakter (Tanpa Karakter 3)</span>
              </label>
            </div>

            <!-- SLOT 3: SUPPORT 2 (TIM) -->
            <div id="slot-wrap-3" style="display: none; margin-bottom: 6px;">
              <div style="font-size: 0.72rem; font-weight: 800; color: #d8b4fe; margin-bottom: 2px;">Karakter 3 (Support
                2):</div>
              <div class="char-picker-container">
                <input type="text" id="reg-char-3" class="form-control" placeholder="Ketik atau klik Karakter 3..."
                  autocomplete="off" onfocus="openCharPicker(3)" oninput="filterCharPicker(3)">
                <div id="picker-popup-3" class="char-picker-popup" style="display: none;"></div>
              </div>
              <div class="slot-ulti-box">
                <span class="slot-ulti-label" style="color: #d8b4fe;">⚡ Ulti:</span>
                <select id="reg-ulti-3" class="slot-ulti-select" disabled>
                  <option value="">(Pilih karakter dulu)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- 3. PILIHAN TIER -->
          <div class="form-group">
            <label class="form-label">
              <span>3. Pilih Tier Antrian *</span>
            </label>
            <div class="tier-grid">
              <div class="tier-btn active tier-free" id="tier-btn-free" onclick="selectTier('FREE')">
                <div class="tier-header-row">
                  <span class="tier-icon">🍃</span>
                  <span class="tier-btn-title">FREE</span>
                </div>
                <span class="tier-btn-sub">Normal</span>
                <span class="tier-status-pill status-open" id="tier-pill-free">AKTIF</span>
              </div>
              <div class="tier-btn tier-vip" id="tier-btn-vip" onclick="selectTier('VIP')">
                <div class="tier-header-row">
                  <span class="tier-icon">⭐</span>
                  <span class="tier-btn-title">VIP</span>
                </div>
                <span class="tier-btn-sub">Prioritas</span>
                <span class="tier-status-pill status-open" id="tier-pill-vip">AKTIF</span>
              </div>
              <div class="tier-btn tier-srobot" id="tier-btn-srobot" onclick="selectTier('SROBOT')">
                <div class="tier-header-row">
                  <span class="tier-icon">⚡</span>
                  <span class="tier-btn-title">SROBOT</span>
                </div>
                <span class="tier-btn-sub">Paling Atas</span>
                <span class="tier-status-pill status-open" id="tier-pill-srobot">AKTIF</span>
              </div>
            </div>
            <input type="hidden" id="selected-tier-val" value="FREE">
          </div>

          <!-- 4. JUMLAH MATCH -->
          <div class="form-group" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <label class="form-label" style="margin-bottom: 0;">
                <span>4. Jumlah Match *</span>
              </label>
              <span id="match-tier-hint" style="font-size: 0.72rem; font-weight: 800; color: #cbd5e1;">(Khusus FREE:
                Maksimal 1 Match)</span>
            </div>

            <div class="match-select-container" style="display: flex; flex-direction: column; gap: 8px;">
              <!-- Quick Select Buttons -->
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn-match active" id="btn-m-1" onclick="setMatches(1)">1 Match</button>
                <button type="button" class="btn-match" id="btn-m-2" onclick="setMatches(2)" disabled
                  style="opacity:0.35; cursor:not-allowed;">2 Match</button>
                <button type="button" class="btn-match" id="btn-m-3" onclick="setMatches(3)" disabled
                  style="opacity:0.35; cursor:not-allowed;">3 Match</button>
                <button type="button" class="btn-match" id="btn-m-5" onclick="setMatches(5)" disabled
                  style="opacity:0.35; cursor:not-allowed;">5 Match</button>
                <button type="button" class="btn-match" id="btn-m-custom" onclick="toggleCustomMatch()" disabled
                  style="opacity:0.35; cursor:not-allowed;">Custom</button>
              </div>

              <!-- Custom Match Input (Hidden by default) -->
              <div id="custom-match-wrap" style="display: none; align-items: center; gap: 8px; margin-top: 4px;">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--c-light);">Jumlah Match:</span>
                <input type="number" id="custom-match-input" class="form-control" min="1" max="50" value="4"
                  style="width: 90px; padding: 6px 10px; font-weight: 900; text-align: center;"
                  oninput="onCustomMatchChange(this.value)">
                <span style="font-size: 0.74rem; color: var(--c-slate);">(Bebas pilih berapa saja)</span>
              </div>
            </div>
            <input type="hidden" id="selected-matches-val" value="1">
          </div>

          <!-- SUBMIT (CENTERED, TANPA EMOT, DENGAN JARAK DARI PEMILIHAN MATCH) -->
          <div class="submit-btn-wrap"
            style="display: flex; justify-content: center; margin-top: 24px; margin-bottom: 8px; width: 100%;">
            <button type="submit" class="btn-submit-reg" id="btn-submit-reg">
              DAFTAR
            </button>
          </div>
        </form>
      </section>

      <!-- COLUMN 2: DAFTAR ANTRIAN LIVE (SELARAS OVERLAY) -->
      <section class="card-panel">
        <div class="card-panel-title">
          <span>📋 DAFTAR ANTRIAN LIVE</span>
          <span id="queue-count-tag" class="p-role-tag free">0 Pemain</span>
        </div>

        <div class="queue-summary-line">
          <span id="queue-stats-text">SB: 0 | VIP: 0 | FREE: 0</span>
          <span>⚡ PRIORITAS OTOMATIS</span>
        </div>

        <div class="queue-list-wrap" id="queue-items-container">
          <div class="empty-queue-box">
            🍃 Antrian masih kosong.<br>Jadilah yang pertama mendaftar!
          </div>
        </div>
      </section>

    </main>
  </div>

  <!-- MODAL INSTRUKSI GIFT & KONFIRMASI PENDAFTARAN -->
  <div id="modal-success" class="modal-backdrop" onclick="closeSuccessModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()"
      style="max-width: 440px; padding: 22px 20px; text-align: center;">

      <!-- Top Icon & Title -->
      <div id="modal-header-icon" style="font-size: 36px; margin-bottom: 6px;">🎁</div>
      <h3 id="modal-header-title"
        style="font-size: 1.18rem; font-weight: 900; margin-bottom: 4px; color: #ffffff; letter-spacing: 0.5px;">
        INSTRUKSI GIFT / DONASI
      </h3>
      <p id="modal-header-desc" style="font-size: 0.80rem; color: var(--c-slate); margin-bottom: 14px;">
        Pendaftaran kamu berhasil dicatat &amp; menunggu persetujuan Admin!
      </p>

      <!-- Player Detail Card -->
      <div
        style="background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 12px 14px; text-align: left; font-size: 0.82rem; display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--c-slate);">Nama Pendaftar:</span>
          <b id="suc-name" style="color:#ffffff; font-size:0.95rem;">-</b>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--c-slate);">Tier &amp; Mode:</span>
          <div style="display:flex; gap:6px; align-items:center;">
            <span id="suc-tier" class="p-role-tag vip">VIP</span>
            <span id="suc-mode" class="p-role-tag"
              style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.25); color: #fff;">1v1</span>
          </div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--c-slate);">Jumlah Match:</span>
          <b id="suc-match-count" style="color:#fde047; font-size:0.92rem;">1 Match</b>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--c-slate);">Karakter:</span>
          <b id="suc-char"
            style="color:var(--c-light); max-width: 220px; text-align:right; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">-</b>
        </div>
        <div id="suc-ulti-row" style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--c-slate);">Ultimate:</span>
          <b id="suc-ulti"
            style="color:#fef08a; max-width: 220px; text-align:right; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">-</b>
        </div>
      </div>

      <!-- Price Box (For VIP & SROBOT) -->
      <div id="modal-price-box"
        style="background: linear-gradient(135deg, rgba(234, 179, 8, 0.12), rgba(168, 85, 247, 0.12)); border: 2px solid #eab308; border-radius: 8px; padding: 12px; margin-bottom: 14px; text-align: center;">
        <div
          style="font-size: 0.76rem; font-weight: 800; color: #fde047; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 6px;">
          ⚡ TOTAL BIAYA GIFT / DONASI
        </div>
        <div style="display: flex; justify-content: space-around; align-items: center; gap: 8px; flex-wrap: wrap;">
          <div
            style="background: rgba(0,0,0,0.4); border: 1px solid rgba(254, 240, 138, 0.3); border-radius: 6px; padding: 6px 12px; flex: 1; min-width: 130px;">
            <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 700;">🪙 TikTok Live</div>
            <div id="price-coins" style="font-size: 1.1rem; font-weight: 900; color: #facc15;">5 Koin</div>
          </div>
          <div
            style="background: rgba(0,0,0,0.4); border: 1px solid rgba(74, 222, 128, 0.3); border-radius: 6px; padding: 6px 12px; flex: 1; min-width: 130px;">
            <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 700;">💳 SociaBuzz</div>
            <div id="price-rp" style="font-size: 1.1rem; font-weight: 900; color: #4ade80;">Rp 1.000</div>
          </div>
        </div>
        <div style="font-size: 0.70rem; color: #cbd5e1; margin-top: 6px;">
          *Pilih salah satu metode pembayaran di atas.
        </div>
      </div>

            <!-- FREE Interactive Flow (Khusus Tier FREE) -->
      <div id="modal-free-flow" style="display: none;">
        
        <!-- Requirement Banner -->
        <div style="background: rgba(37, 244, 238, 0.1); border: 1.5px solid #25f4ee; border-radius: 8px; padding: 12px; margin-bottom: 14px; text-align: center;">
          <div style="font-size: 0.76rem; font-weight: 800; color: #25f4ee; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px;">
            📌 PERSYARATAN TIER FREE
          </div>
          <div style="font-size: 0.84rem; color: #f8fafc; font-weight: 800;">
            Wajib Follow Akun TikTok Host: <span style="color: #fe2c55; font-weight: 900;">@herzg1209</span>
          </div>
          <div id="free-queue-badge" style="display: inline-block; margin-top: 6px; font-size: 0.72rem; color: #86efac; background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; border-radius: 4px; padding: 2px 8px; font-weight: 800;">
            ⚡ Otomatis Masuk Antrian Live
          </div>
        </div>

        <!-- FREE Step 1: Question (Sudah / Belum) -->
        <div id="free-step-question">
          <div style="font-size: 0.88rem; font-weight: 800; color: #f8fafc; margin-bottom: 12px;">
            Apakah kamu sudah follow akun TikTok Host?
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button type="button" class="btn-reg-opt"
              style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; font-weight: 900; padding: 10px 14px; border-radius: 6px; font-size: 0.90rem; cursor: pointer;"
              onclick="handleFreeFollowAnswer('belum')">
              ❌ Belum
            </button>
            <button type="button" class="btn-reg-opt"
              style="background: rgba(34, 197, 94, 0.25); border: 1px solid #22c55e; color: #86efac; font-weight: 900; padding: 10px 14px; border-radius: 6px; font-size: 0.90rem; cursor: pointer;"
              onclick="handleFreeFollowAnswer('sudah')">
              ✅ Sudah
            </button>
          </div>
        </div>

        <!-- FREE Step 2: Choice (Jika Klik 'Belum') -->
        <div id="free-step-follow" style="display: none;">
          <div style="font-size: 0.82rem; font-weight: 800; color: #fde047; margin-bottom: 10px;">
            Silakan buka profil dan follow TikTok Host sekarang:
          </div>
          <div style="margin-bottom: 14px;">
            <a id="btn-free-follow-tiktok" href="https://www.tiktok.com/@herzg1209" target="_blank"
              rel="noopener noreferrer"
              style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; background: #000000; border: 1.5px solid #fe2c55; color: #ffffff; padding: 11px 16px; border-radius: 6px; font-weight: 900; font-size: 0.90rem; box-shadow: 0 4px 14px rgba(254, 44, 85, 0.35); transition: transform 0.15s ease;">
              <span>📱 Buka &amp; Follow @herzg1209</span>
              <span style="background: #fe2c55; color: #fff; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px;">TikTok ↗</span>
            </a>
          </div>
          <div style="display: flex; gap: 8px; justify-content: space-between;">
            <button type="button"
              style="flex: 1; background: rgba(34, 197, 94, 0.25); border: 1px solid #22c55e; color: #86efac; padding: 9px; border-radius: 6px; font-weight: 800; font-size: 0.82rem; cursor: pointer;"
              onclick="handleFreeFollowAnswer('sudah')">
              ✓ Saya Sudah Follow
            </button>
            <button type="button"
              style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #cbd5e1; padding: 9px 14px; border-radius: 6px; font-weight: 700; font-size: 0.80rem; cursor: pointer;"
              onclick="closeSuccessModal()">
              Tutup
            </button>
          </div>
        </div>

        <!-- FREE Step 3: Confirmed (Jika Klik 'Sudah') -->
        <div id="free-step-confirmed" style="display: none; text-align: center;">
          <div
            style="background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
            <div style="font-size: 1.3rem; margin-bottom: 4px;">🎉</div>
            <div style="font-weight: 900; font-size: 0.90rem; color: #86efac; margin-bottom: 4px;">
              Pendaftaran Berhasil Masuk Antrian!
            </div>
            <div id="free-confirmed-msg" style="font-size: 0.78rem; color: #cbd5e1; line-height: 1.4;">
              Terima kasih sudah mem-follow host. Nama kamu telah aktif di antrian live! Pantau streaming saat giliranmu bertanding.
            </div>
          </div>
          <button type="button" class="btn-submit-reg" style="padding: 10px; width: 100%; font-weight: 900;"
            onclick="closeSuccessModal()">
            Siap, Pantau Antrian!
          </button>
        </div>

      </div>

    </div>
  </div>

  <div id="reg-toast">Pendaftaran Berhasil!</div>

  <!-- SCRIPT -->
  <script>
    // ==========================================
    // 163 NARUTO CHARACTERS MASTER DATASET
    // ==========================================
    const NARUTO_CHARACTERS = [{ "id": 1, "name": "Naruto Uzumaki (Mode Petapa Enam Jalan)", "slide": 1, "ultis": ["Enam Jalan: Rasen Shuriken Bola Raksasa", "Rasen Shuriken Planet Siluman Berekor"], "short1v1": "Naruto (Enam Jalan)", "shortTeam": "N6J" }, { "id": 2, "name": "Naruto Uzumaki (Mode Link Kurama)", "slide": 1, "ultis": ["Rasen Shuriken Panet Siluman Berekor"], "short1v1": "Naruto (Kurama)", "shortTeam": "NLK" }, { "id": 3, "name": "Naruto Uzumaki (Bola Siluman Berekor)", "slide": 1, "ultis": ["Bola Siluman Berekor"], "short1v1": "Naruto (Bijuu)", "shortTeam": "NBS" }, { "id": 4, "name": "Naruto Uzumaki (Teknik Petapa: Rentetan Rasengan Bola Raksasa)", "slide": 1, "ultis": ["Teknik Petapa: Rentetan Rasengan Bola Raksasa"], "short1v1": "Naruto (Petapa Rasen)", "shortTeam": "NPR" }, { "id": 5, "name": "Naruto Uzumaki (Mode Petapa)", "slide": 1, "ultis": ["Sejati: Elemen Angin - Rasen Shuriken"], "short1v1": "Naruto (Petapa)", "shortTeam": "NSP" }, { "id": 6, "name": "Naruto Uzumaki", "slide": 1, "ultis": ["Elemen Angin: Rasen Shuriken", "Rasengan Ekor Sembilan"], "short1v1": "Naruto", "shortTeam": "NRT" }, { "id": 7, "name": "Sakura Haruno", "slide": 1, "ultis": ["Mekar Penuh: Tumbukan Bunga Sakura", "Teknik Seratus Penyembuhan"], "short1v1": "Sakura", "shortTeam": "SKR" }, { "id": 8, "name": "Sai", "slide": 1, "ultis": ["Teknik Ninja: Gambar Replika Monster - Gerombolan"], "short1v1": "Sai", "shortTeam": "SAI" }, { "id": 9, "name": "Kakashi Hatake (Kedua Mata Sharingan)", "slide": 1, "ultis": ["Raikiri: Kilat", "Susanoo: Kamui Raikiri", "Kamui"], "short1v1": "Kakashi (DMS)", "shortTeam": "K2S" }, { "id": 10, "name": "Kakashi Hatake (Perang Dunia Ninja)", "slide": 1, "ultis": ["Kamui", "Gempa Petir Ganda Raikiri"], "short1v1": "Kakashi (Perang)", "shortTeam": "KPD" }, { "id": 11, "name": "Kakashi Hatake", "slide": 1, "ultis": ["Kamui", "Gempa Petir Ganda Raikiri"], "short1v1": "Kakashi", "shortTeam": "KKS" }, { "id": 12, "name": "Might Guy (Perang Dunia Ninja)", "slide": 1, "ultis": ["Merak Pagi", "Harimau Siang", "Guy Malam"], "short1v1": "Guy (Perang)", "shortTeam": "GPD" }, { "id": 13, "name": "Might Guy", "slide": 1, "ultis": ["Merak Pagi", "Harimau Siang"], "short1v1": "Guy", "shortTeam": "GUY" }, { "id": 14, "name": "Yamato", "slide": 1, "ultis": ["Gen Hokage Pertama"], "short1v1": "Yamato", "shortTeam": "YMT" }, { "id": 15, "name": "Shikamaru Nara", "slide": 1, "ultis": ["Teknik Pengumpul Bayangan"], "short1v1": "Shikamaru", "shortTeam": "SKM" }, { "id": 16, "name": "Chouji Akimichi", "slide": 1, "ultis": ["Tamparan Super"], "short1v1": "Chouji", "shortTeam": "CHJ" }, { "id": 17, "name": "Ino Yamanaka", "slide": 1, "ultis": ["Taman Bunga Tarian Kelopak"], "short1v1": "Ino", "shortTeam": "INO" }, { "id": 18, "name": "Neji Hyuga", "slide": 1, "ultis": ["8 Trigraf 64 Tapak"], "short1v1": "Neji", "shortTeam": "NEJ" }, { "id": 19, "name": "Rock Lee", "slide": 1, "ultis": ["Angin Panas Konoha"], "short1v1": "Rock Lee", "shortTeam": "LEE" }, { "id": 20, "name": "Tenten", "slide": 1, "ultis": ["Kendali Senjata: Bola Besi Raksasa"], "short1v1": "Tenten", "shortTeam": "TTN" }, { "id": 21, "name": "Kiba Inuzuka", "slide": 1, "ultis": ["Absolut: Taring ke Taring"], "short1v1": "Kiba", "shortTeam": "KBA" }, { "id": 22, "name": "Shino Aburame", "slide": 1, "ultis": ["Teknik Rahasia: Rawa Serangga"], "short1v1": "Shino", "shortTeam": "SHN" }, { "id": 23, "name": "Hinata Hyuga", "slide": 1, "ultis": ["Tinju Singa Kembar Halus"], "short1v1": "Hinata", "shortTeam": "HNT" }, { "id": 24, "name": "Asuma Sarutobi", "slide": 1, "ultis": ["Bilah Chakra: Lurus"], "short1v1": "Asuma", "shortTeam": "ASM" }, { "id": 25, "name": "Konohamaru Sarutobi", "slide": 2, "ultis": ["Teknik Harem Hebat"], "short1v1": "Konohamaru", "shortTeam": "KNH" }, { "id": 26, "name": "Shisui Uchiha", "slide": 2, "ultis": ["Kotoamatsukami: Sinar"], "short1v1": "Shisui", "shortTeam": "SSU" }, { "id": 27, "name": "Iruka Umino", "slide": 2, "ultis": ["Auman Cinta"], "short1v1": "Iruka", "shortTeam": "IRK" }, { "id": 28, "name": "Kushina Uzumaki", "slide": 2, "ultis": ["Habanero Merah Darah"], "short1v1": "Kushina", "shortTeam": "KSN" }, { "id": 29, "name": "Jiraiya", "slide": 2, "ultis": ["Rasengan Pahlawan"], "short1v1": "Jiraiya", "shortTeam": "JRY" }, { "id": 30, "name": "Hashirama Senju (Edo Tensei)", "slide": 2, "ultis": ["Teknik Petapa: Elemen Kayu - Seribu Tangan Sejati"], "short1v1": "Hashirama (Edo)", "shortTeam": "HSE" }, { "id": 31, "name": "Hashirama Senju", "slide": 2, "ultis": ["Teknik Rahasia Elemen Kayu: Pencipta Hutan Belantara"], "short1v1": "Hashirama", "shortTeam": "HSR" }, { "id": 32, "name": "Tobirama Senju", "slide": 2, "ultis": ["Elemen Air: Terjangan Naga Air", "Kertas Bom Tandem Lipat  Ganda"], "short1v1": "Tobirama", "shortTeam": "TBR" }, { "id": 33, "name": "Hiruzen Sarutobi", "slide": 2, "ultis": ["Teknik Penyegelan: Segel Malaikat Maut"], "short1v1": "Hiruzen", "shortTeam": "HRZ" }, { "id": 34, "name": "Minato Namikaze (Edo Tensei)", "slide": 2, "ultis": ["Dewa Petir Terbang: Langkah Kilat Badai Ruang Waktu, Jurus Nol"], "short1v1": "Minato (Edo)", "shortTeam": "MNE" }, { "id": 35, "name": "Minato Namikaze", "slide": 2, "ultis": ["Kilat Kuning", "Dewa Petir Terbang: Langkah Dua"], "short1v1": "Minato", "shortTeam": "MNT" }, { "id": 36, "name": "Tsunade", "slide": 2, "ultis": ["Tanah Murni Kayangan"], "short1v1": "Tsunade", "shortTeam": "TSN" }, { "id": 37, "name": "Danzou Shimura", "slide": 2, "ultis": ["Izanagi"], "short1v1": "Danzou", "shortTeam": "DNZ" }, { "id": 38, "name": "Kakashi Hatake (Masa Kecil)", "slide": 2, "ultis": ["Pedang Cahaya Putih"], "short1v1": "Kakashi (Kecil)", "shortTeam": "KKC" }, { "id": 39, "name": "Obito Uchiha (Masa Kecil)", "slide": 2, "ultis": ["Elemen Api: Teknik Api Phoenix"], "short1v1": "Obito (Kecil)", "shortTeam": "OBC" }, { "id": 40, "name": "Rin Nohara", "slide": 2, "ultis": ["Amukan Ekor Tiga"], "short1v1": "Rin", "shortTeam": "RIN" }, { "id": 41, "name": "Naruto Uzumaki (The Last)", "slide": 2, "ultis": ["Kombo 5.000 Naruto Uzumaki"], "short1v1": "Naruto (The Last)", "shortTeam": "NTL" }, { "id": 42, "name": "Sasuke Uchiha (The Last)", "slide": 2, "ultis": ["Chidori: Penghancur Bintang"], "short1v1": "Sasuke (The Last)", "shortTeam": "STL" }, { "id": 43, "name": "Hanabi Hyuga", "slide": 2, "ultis": ["Aliran Hanabi: 8 Trigraf 64 Tapak"], "short1v1": "Hanabi", "shortTeam": "HNB" }, { "id": 44, "name": "Hanabi Hyuga (Shippuden)", "slide": 2, "ultis": ["Aliran Hanabi: 8 Trigraf 64 Tapak"], "short1v1": "Hanabi (Shippuden)", "shortTeam": "HNS" }, { "id": 45, "name": "Robot Naruto", "slide": 2, "ultis": ["Meriam Siluman Berekor"], "short1v1": "Robot Naruto", "shortTeam": "RBN" }, { "id": 46, "name": "Kurenai", "slide": 2, "ultis": ["Ilusi Iblis: Ikatan Pohon Kemarian - Tebaran Kelopak"], "short1v1": "Kurenai", "shortTeam": "KRN" }, { "id": 47, "name": "Sasuke Uchiha (Rinne Sharingan)", "slide": 3, "ultis": ["Takemikazuchi", "Elemen Kobaran: Susanoo Kagutsuchi"], "short1v1": "Sasuke (Rinnegan)", "shortTeam": "SRN" }, { "id": 48, "name": "Sasuke Uchiha (Mangekyou Sharingan Abadi)", "slide": 3, "ultis": ["Pedang Kagutsuchi", "Elemen Kobaran: Susanoo Kagutsuchi"], "short1v1": "Sasuke (EMS)", "shortTeam": "SEA" }, { "id": 49, "name": "Sasuke Uchiha (Pertemuan Lima Kage)", "slide": 3, "ultis": ["Susanoo"], "short1v1": "Sasuke (5 Kage)", "shortTeam": "S5K" }, { "id": 50, "name": "Sasuke Uchiha (Taka)", "slide": 3, "ultis": ["Amaterasu"], "short1v1": "Sasuke (Taka)", "shortTeam": "STK" }, { "id": 51, "name": "Sasuke Uchiha", "slide": 3, "ultis": ["Kirin", "Tombak Chidori"], "short1v1": "Sasuke", "shortTeam": "SSK" }, { "id": 52, "name": "Orochimaru", "slide": 3, "ultis": ["Teknik Pawang Ular Besar"], "short1v1": "Orochimaru", "shortTeam": "ORC" }, { "id": 53, "name": "Kabuto Yakushi (Mode Petapa)", "slide": 3, "ultis": ["Teknik Petapa: Reinkarnasi Anorganik"], "short1v1": "Kabuto (Petapa)", "shortTeam": "KBP" }, { "id": 54, "name": "Kabuto Yakushi (Jubah Ular)", "slide": 3, "ultis": ["Pemanggilan: Edo Tensei"], "short1v1": "Kabuto (Ular)", "shortTeam": "KBU" }, { "id": 55, "name": "Kabuto Yakushi", "slide": 3, "ultis": ["Teknik Terlarang: Teknik Jiwa Mati"], "short1v1": "Kabuto", "shortTeam": "KBT" }, { "id": 56, "name": "Suigetsu Hozuki", "slide": 3, "ultis": ["Jeram: Teknik Transformasi Air"], "short1v1": "Suigetsu", "shortTeam": "SGT" }, { "id": 57, "name": "Juugo", "slide": 3, "ultis": ["Tinju Piston: Jurus Nol"], "short1v1": "Juugo", "shortTeam": "JUG" }, { "id": 58, "name": "Karin", "slide": 3, "ultis": ["Tamparan Ekstrem"], "short1v1": "Karin", "shortTeam": "KRN" }, { "id": 59, "name": "Kimimaro", "slide": 3, "ultis": ["Tarian Tunas Pakis"], "short1v1": "Kimimaro", "shortTeam": "KMM" }, { "id": 60, "name": "Jiroubou", "slide": 3, "ultis": ["Tinju Arahat"], "short1v1": "Jiroubou", "shortTeam": "JRB" }, { "id": 61, "name": "Kidoumaru", "slide": 3, "ultis": ["Panah Laba-Laba: Koyak"], "short1v1": "Kidoumaru", "shortTeam": "KDM" }, { "id": 62, "name": "Sakon/Ukon", "slide": 3, "ultis": ["Rentetan Tinju: Rakshasa"], "short1v1": "Sakon/Ukon", "shortTeam": "SKN" }, { "id": 63, "name": "Tayuya", "slide": 3, "ultis": ["Seruling Setan: Rantai Suara Ilusi"], "short1v1": "Tayuya", "shortTeam": "TYY" }, { "id": 64, "name": "Gaara (Perang Dunia Ninja)", "slide": 4, "ultis": ["Makam Gurun", "Peti Segel Pasir", "Serangan Tombak Penjara Pasir"], "short1v1": "Gaara (Perang)", "shortTeam": "GPD" }, { "id": 65, "name": "Gaara (Pertemuan Lima Kage)", "slide": 4, "ultis": ["Peti Segel Pasir", "Serangan Tombak Penjara Pasir", "Makam Gurun"], "short1v1": "Gaara (5 Kage)", "shortTeam": "G5K" }, { "id": 66, "name": "Gaara", "slide": 4, "ultis": ["Peti Segel Pasir", "Serangan Tombak Penjara Pasir", "Makam Gurun"], "short1v1": "Gaara", "shortTeam": "GRA" }, { "id": 67, "name": "Kankurou", "slide": 4, "ultis": ["Teknik Hitam Rahasia: 3 Tragedi", "Teknik Hitam Rahasia: Pentas Kalajengking Api"], "short1v1": "Kankurou", "shortTeam": "KNK" }, { "id": 68, "name": "Temari", "slide": 4, "ultis": ["Bantingan Topan"], "short1v1": "Temari", "shortTeam": "TMR" }, { "id": 69, "name": "Raikage: A", "slide": 4, "ultis": ["Bom Liger", "Lightning Straight"], "short1v1": "Raikage A", "shortTeam": "RKA" }, { "id": 70, "name": "Killer Bee (Samehada)", "slide": 4, "ultis": ["Lariat"], "short1v1": "Killer Bee (Samehada)", "shortTeam": "KBS" }, { "id": 71, "name": "Killer Bee", "slide": 4, "ultis": ["Lariat"], "short1v1": "Killer Bee", "shortTeam": "KBE" }, { "id": 72, "name": "Darui", "slide": 4, "ultis": ["Elemen Badai: Hitam Berburu"], "short1v1": "Darui", "shortTeam": "DRI" }, { "id": 73, "name": "Oonoki", "slide": 4, "ultis": ["Elemen Debu: Teknik Belah Dunia Purba"], "short1v1": "Oonoki", "shortTeam": "ONK" }, { "id": 74, "name": "Mei Terumi", "slide": 4, "ultis": ["Elemen Korosi: Asam Pelarut"], "short1v1": "Mei", "shortTeam": "MEI" }, { "id": 75, "name": "Mifune", "slide": 4, "ultis": ["Tebas Wujud"], "short1v1": "Mifune", "shortTeam": "MFN" }, { "id": 76, "name": "Yugito Nii", "slide": 4, "ultis": ["Mangkuk Api Kucing"], "short1v1": "Yugito", "shortTeam": "YGT" }, { "id": 77, "name": "Yagura", "slide": 4, "ultis": ["Hempasan Laut Badai"], "short1v1": "Yagura", "shortTeam": "YGR" }, { "id": 78, "name": "Roushi", "slide": 4, "ultis": ["Letusan Sungai Berapi"], "short1v1": "Roushi", "shortTeam": "RSH" }, { "id": 79, "name": "Han", "slide": 4, "ultis": ["Melompat Lima Gunung"], "short1v1": "Han", "shortTeam": "HAN" }, { "id": 80, "name": "Utakata", "slide": 4, "ultis": ["Muatan Gelembung"], "short1v1": "Utakata", "shortTeam": "UTK" }, { "id": 81, "name": "Fuu", "slide": 4, "ultis": ["Ledakan Sisik"], "short1v1": "Fuu", "shortTeam": "FUU" }, { "id": 82, "name": "Kazekage 4: Rasa", "slide": 4, "ultis": ["Elemen Magnet: Pemakaman Pasir Emas"], "short1v1": "Rasa (Kazekage 4)", "shortTeam": "KZ4" }, { "id": 83, "name": "Raikage 3", "slide": 4, "ultis": ["Tikaman Neraka Satu Jari: Ekstrem"], "short1v1": "Raikage 3", "shortTeam": "RK3" }, { "id": 84, "name": "Tsuchikage 2", "slide": 4, "ultis": ["Elemen Debu: Teknik Pemisah Belah Dunia Purba"], "short1v1": "Tsuchikage 2", "shortTeam": "TC2" }, { "id": 85, "name": "Mizukage 2", "slide": 4, "ultis": ["Teknik Pistol Air"], "short1v1": "Mizukage 2", "shortTeam": "MZ2" }, { "id": 86, "name": "Hanzou", "slide": 4, "ultis": ["Teknik Ninja: Kabut Sabit"], "short1v1": "Hanzou", "shortTeam": "HNZ" }, { "id": 87, "name": "Nenek Chiyo", "slide": 4, "ultis": ["3 Harta Isap Lebur"], "short1v1": "Nenek Chiyo", "shortTeam": "CHY" }, { "id": 88, "name": "Madara Uchiha (Enam Jalan)", "slide": 5, "ultis": ["Meteor Cakrawala"], "short1v1": "Madara (Enam Jalan)", "shortTeam": "M6J" }, { "id": 89, "name": "Madara Uchiha (Edo Tensei: Lepas)", "slide": 5, "ultis": ["Bintang Pengguncang Langit", "Susanoo Sempurna"], "short1v1": "Madara (Edo)", "shortTeam": "MDE" }, { "id": 90, "name": "Madara Uchiha", "slide": 5, "ultis": ["Bintang Pengguncang Langit"], "short1v1": "Madara", "shortTeam": "MDR" }, { "id": 91, "name": "Kaguya Otsutsuki", "slide": 5, "ultis": ["Bola Maut Pencari Makna"], "short1v1": "Kaguya", "shortTeam": "KGY" }, { "id": 92, "name": "Indra Otsutsuki", "slide": 5, "ultis": ["Tujuh Petir Dewa"], "short1v1": "Indra", "shortTeam": "IND" }, { "id": 93, "name": "Asura Otsutsuki", "slide": 5, "ultis": ["Seribu Tangan Sejati: Tapak Spiral"], "short1v1": "Asura", "shortTeam": "ASR" }, { "id": 94, "name": "Obito Uchiha (Jinchuuriki Ekor Sepuluh)", "slide": 5, "ultis": ["Pedang Dewa: Pedang Nunoboko"], "short1v1": "Obito (Jinchuuriki)", "shortTeam": "OBJ" }, { "id": 95, "name": "Obito Uchiha", "slide": 5, "ultis": ["Pemanggilan: Ekor Sepuluh"], "short1v1": "Obito", "shortTeam": "OBT" }, { "id": 96, "name": "Tobi (Perang Dunia Ninja)", "slide": 5, "ultis": ["Pemanggilan: Patung Iblis Asing"], "short1v1": "Tobi (Perang)", "shortTeam": "TPD" }, { "id": 97, "name": "Tobi", "slide": 5, "ultis": ["Teknik Ladang Ranjau"], "short1v1": "Tobi", "shortTeam": "TOB" }, { "id": 98, "name": "Pria Bertopeng", "slide": 5, "ultis": ["Pemanggilan: Ekor Sembilan"], "short1v1": "Pria Bertopeng", "shortTeam": "PBM" }, { "id": 99, "name": "Obito Uchiha (Pelarian)", "slide": 5, "ultis": ["Elemen Kayu: Neraka Berontak"], "short1v1": "Obito (Pelarian)", "shortTeam": "OBP" }, { "id": 100, "name": "Pain", "slide": 5, "ultis": ["Enam Jalan Samsara"], "short1v1": "Pain", "shortTeam": "PAN" }, { "id": 101, "name": "Nagato", "slide": 5, "ultis": ["Chibaku Tensei"], "short1v1": "Nagato", "shortTeam": "NGT" }, { "id": 102, "name": "Konan", "slide": 5, "ultis": ["Tarian Kertas: Hukuman"], "short1v1": "Konan", "shortTeam": "KNN" }, { "id": 103, "name": "Itachi Uchiha", "slide": 5, "ultis": ["Pedang Totsuka", "Tsukoyomi", "Yasaka no Magatama"], "short1v1": "Itachi", "shortTeam": "ITC" }, { "id": 104, "name": "Kisame Hoshigaki", "slide": 5, "ultis": ["Elemen Air: Terjangan Ombak", "Elemen Air: Teknik Peluru Hiu Besar"], "short1v1": "Kisame", "shortTeam": "KSM" }, { "id": 105, "name": "Kakuzu", "slide": 5, "ultis": ["Dendam Bumi: Tembakan Terakhir"], "short1v1": "Kakuzu", "shortTeam": "KKZ" }, { "id": 106, "name": "Hidan", "slide": 5, "ultis": ["Teknik Kutukan: Kuasa Darah Maut"], "short1v1": "Hidan", "shortTeam": "HDN" }, { "id": 107, "name": "Deidara", "slide": 5, "ultis": ["C3"], "short1v1": "Deidara", "shortTeam": "DDR" }, { "id": 108, "name": "Sasori", "slide": 5, "ultis": ["Dunia Pasir Besi"], "short1v1": "Sasori", "shortTeam": "SSR" }, { "id": 109, "name": "Hagoromo Otsutsuki", "slide": 5, "ultis": ["Cahaya Petapa Halilintar"], "short1v1": "Hagoromo", "shortTeam": "HGR" }, { "id": 110, "name": "Naruto Uzumaki (Masa Kecil)", "slide": 6, "ultis": ["Kombo 2.000 Naruto Uzumaki"], "short1v1": "Naruto (Kecil)", "shortTeam": "NKC" }, { "id": 111, "name": "Sasuke Uchiha (Masa Kecil - Pakaian Akhir)", "slide": 6, "ultis": ["Chidori"], "short1v1": "Sasuke (Kecil Akhir)", "shortTeam": "SKA" }, { "id": 112, "name": "Sasuke Uchiha (Masa Kecil)", "slide": 6, "ultis": ["Chidori"], "short1v1": "Sasuke (Kecil)", "shortTeam": "SKC" }, { "id": 113, "name": "Sakura Haruno (Masa Kecil)", "slide": 6, "ultis": ["Sakura Agung"], "short1v1": "Sakura (Kecil)", "shortTeam": "SKK" }, { "id": 114, "name": "Shikamaru Nara (Masa Kecil)", "slide": 6, "ultis": ["Teknik Ninja: Jurus Bayangan Cekik"], "short1v1": "Shikamaru (Kecil)", "shortTeam": "SMC" }, { "id": 115, "name": "Chouji Akimichi (Masa Kecil)", "slide": 6, "ultis": ["Teknik Super Besar"], "short1v1": "Chouji (Kecil)", "shortTeam": "CJC" }, { "id": 116, "name": "Ino Yamanaka (Masa Kecil)", "slide": 6, "ultis": ["Teknik Ninja: Seribu Taburan Bunga"], "short1v1": "Ino (Kecil)", "shortTeam": "INC" }, { "id": 117, "name": "Neji Hyuga (Masa Kecil)", "slide": 6, "ultis": ["8 Trigraf 64 Tapak"], "short1v1": "Neji (Kecil)", "shortTeam": "NJC" }, { "id": 118, "name": "Rock Lee (Masa Kecil)", "slide": 6, "ultis": ["Tangkai Teratai"], "short1v1": "Rock Lee (Kecil)", "shortTeam": "LKC" }, { "id": 119, "name": "Tenten (Masa Kecil)", "slide": 6, "ultis": ["Kendali Senjata: Seribu Amukan Pedang"], "short1v1": "Tenten (Kecil)", "shortTeam": "TTC" }, { "id": 120, "name": "Kiba Inuzuka (Masa Kecil)", "slide": 6, "ultis": ["Taring Serigala"], "short1v1": "Kiba (Kecil)", "shortTeam": "KBC" }, { "id": 121, "name": "Shino Aburame (Masa Kecil)", "slide": 6, "ultis": ["Serangga Parasit: Lonceng"], "short1v1": "Shino (Kecil)", "shortTeam": "SHC" }, { "id": 122, "name": "Hinata Hyuga (Masa Kecil)", "slide": 6, "ultis": ["8 Trigraf Pelindung 64 Tapak"], "short1v1": "Hinata (Kecil)", "shortTeam": "HNC" }, { "id": 123, "name": "Gaara (Masa Kecil)", "slide": 6, "ultis": ["Makam Agung Arus Pasir"], "short1v1": "Gaara (Kecil)", "shortTeam": "GKC" }, { "id": 124, "name": "Kankurou (Masa Kecil)", "slide": 6, "ultis": ["Teknik Hitam Rahasia: Mekanisme Sekali Basmi"], "short1v1": "Kankurou (Kecil)", "shortTeam": "KKC" }, { "id": 125, "name": "Temari (Masa Kecil)", "slide": 6, "ultis": ["Pemanggilan: Tarian Penggal Cepat"], "short1v1": "Temari (Kecil)", "shortTeam": "TMC" }, { "id": 126, "name": "Zabuza Momochi", "slide": 6, "ultis": ["Membunuh Tanpa Suara"], "short1v1": "Zabuza", "shortTeam": "ZBZ" }, { "id": 127, "name": "Haku", "slide": 6, "ultis": ["Teknik Rahasia: Cermin Kristal Es"], "short1v1": "Haku", "shortTeam": "HAK" }, { "id": 128, "name": "Boruto Uzumaki \"Karma\"", "slide": 7, "ultis": ["\"Karma\" Rasengan"], "short1v1": "Boruto (Karma)", "shortTeam": "BKM" }, { "id": 129, "name": "Boruto Uzumaki (Ujian Chuunin)", "slide": 7, "ultis": ["Spiral Boruto Storm"], "short1v1": "Boruto (Chuunin)", "shortTeam": "BCN" }, { "id": 130, "name": "Boruto Uzumaki (Alat Ilmiah Ninja)", "slide": 7, "ultis": ["Overwind Ride"], "short1v1": "Boruto (Alat Ilmiah)", "shortTeam": "BAI" }, { "id": 131, "name": "Boruto Uzumaki", "slide": 7, "ultis": ["Boruto Strike"], "short1v1": "Boruto", "shortTeam": "BRT" }, { "id": 132, "name": "Sarada Uchiha (Chidori)", "slide": 7, "ultis": ["Penghancur Langit Hujan Sore", "Tarian Bunga Chidori"], "short1v1": "Sarada (Chidori)", "shortTeam": "SRC" }, { "id": 133, "name": "Sarada Uchiha", "slide": 7, "ultis": ["Formasi Shuriken Uchiha: Petir Musim Semi"], "short1v1": "Sarada", "shortTeam": "SRD" }, { "id": 134, "name": "Mitsuki", "slide": 7, "ultis": ["Teknik Petapa: Petir Ular Agung - Ular Putih", "Ular Liar"], "short1v1": "Mitsuki", "shortTeam": "MTK" }, { "id": 135, "name": "Kawaki", "slide": 7, "ultis": ["\"Karma\" Ombak Pemakaman Besar"], "short1v1": "Kawaki", "shortTeam": "KWK" }, { "id": 136, "name": "Naruto Uzumaki (Mode Barion)", "slide": 7, "ultis": ["Rasengan Barion: Hujaman Langit"], "short1v1": "Naruto (Barion)", "shortTeam": "NBR" }, { "id": 137, "name": "Naruto Uzumaki (Boruto)", "slide": 7, "ultis": ["Sejati: Rasen Shuriken Siluman Berekor"], "short1v1": "Naruto (Boruto)", "shortTeam": "NBT" }, { "id": 138, "name": "Sasuke Uchiha (Bayangan Pendukung)", "slide": 7, "ultis": ["Chibaku Tensei: Kirin Kembar"], "short1v1": "Sasuke (Bayangan)", "shortTeam": "SBP" }, { "id": 139, "name": "Sasuke Uchiha (Boruto)", "slide": 7, "ultis": ["Chibaku Tensei: Langit Runtuh"], "short1v1": "Sasuke (Boruto)", "shortTeam": "SBT" }, { "id": 140, "name": "Sakura Haruno (Boruto)", "slide": 7, "ultis": ["Mekar Penuh: Tumbukan Bunga Sakura", "Teknik Seratus Penyembuhan"], "short1v1": "Sakura (Boruto)", "shortTeam": "SKB" }, { "id": 141, "name": "Hinata Hyuga (Boruto)", "slide": 7, "ultis": ["Tinju Singa Kembar Halus"], "short1v1": "Hinata (Boruto)", "shortTeam": "HNB" }, { "id": 142, "name": "Shikamaru Nara (Boruto)", "slide": 7, "ultis": ["Teknik Pengumpul Bayangan"], "short1v1": "Shikamaru (Boruto)", "shortTeam": "SMB" }, { "id": 143, "name": "Chouji Akimichi (Boruto)", "slide": 7, "ultis": ["Tamparan Super"], "short1v1": "Chouji (Boruto)", "shortTeam": "CJB" }, { "id": 144, "name": "Ino Yamanaka (Boruto)", "slide": 7, "ultis": ["Taman Bunga Tarian Kelopak"], "short1v1": "Ino (Boruto)", "shortTeam": "INB" }, { "id": 145, "name": "Sai Yamanaka (Boruto)", "slide": 7, "ultis": ["Teknik Ninja: Gambar Replika Monster - Gerombolan"], "short1v1": "Sai (Boruto)", "shortTeam": "SAB" }, { "id": 146, "name": "Rock Lee (Boruto)", "slide": 7, "ultis": ["Angin Panas Konoha"], "short1v1": "Rock Lee (Boruto)", "shortTeam": "LBT" }, { "id": 147, "name": "Tenten (Boruto)", "slide": 7, "ultis": ["Kendali Senjata: Bola Besi Raksasa"], "short1v1": "Tenten (Boruto)", "shortTeam": "TTB" }, { "id": 148, "name": "Kiba Inuzuka (Boruto)", "slide": 7, "ultis": ["Taring Serigala"], "short1v1": "Kiba (Boruto)", "shortTeam": "KBB" }, { "id": 149, "name": "Shino Aburame (Boruto)", "slide": 7, "ultis": ["Teknik Rahasia: Rawa Serangga"], "short1v1": "Shino (Boruto)", "shortTeam": "SHB" }, { "id": 150, "name": "Kawaki (Evolusi \"Karma\")", "slide": 7, "ultis": ["Peluru Pelarungan Kekaisaran"], "short1v1": "Kawaki (Karma)", "shortTeam": "KWE" }, { "id": 151, "name": "Boruto Uzumaki (Evolusi \"Karma\")", "slide": 7, "ultis": ["Rasengan Bola Raksasa: Penghancur Langit"], "short1v1": "Boruto (Karma 2)", "shortTeam": "BKE" }, { "id": 152, "name": "Gaara (Boruto)", "slide": 8, "ultis": ["Makam Gurun", "Peti Segel Pasir", "Serangan Tombak Penjara Pasir"], "short1v1": "Gaara (Boruto)", "shortTeam": "GBT" }, { "id": 153, "name": "Kankurou (Boruto)", "slide": 8, "ultis": ["Teknik Hitam Rahasia: 3 Tragedi", "Teknik Hitam Rahasia: Pentas Kalajengking Api"], "short1v1": "Kankurou (Boruto)", "shortTeam": "KNB" }, { "id": 154, "name": "Temari Nara (Boruto)", "slide": 8, "ultis": ["Bantingan Topan"], "short1v1": "Temari (Boruto)", "shortTeam": "TMB" }, { "id": 155, "name": "Darui (Boruto)", "slide": 8, "ultis": ["Elemen Badai: Hitam Berburu"], "short1v1": "Darui (Boruto)", "shortTeam": "DRB" }, { "id": 156, "name": "Momoshiki Otsutsuki", "slide": 8, "ultis": ["3 Dewa Setan"], "short1v1": "Momoshiki", "shortTeam": "MMS" }, { "id": 157, "name": "Kinshiki Otsutsuki", "slide": 8, "ultis": ["Amukan Marah"], "short1v1": "Kinshiki", "shortTeam": "KNS" }, { "id": 158, "name": "Jigen", "slide": 8, "ultis": ["Sukunahikona: Kotak Kubah Jatuh"], "short1v1": "Jigen", "shortTeam": "JGN" }, { "id": 159, "name": "Koji Kashin", "slide": 8, "ultis": ["Teknik Petapa: Kipas Lima"], "short1v1": "Koji Kashin", "shortTeam": "KOJ" }, { "id": 160, "name": "Delta", "slide": 8, "ultis": ["Aerobatic Strike"], "short1v1": "Delta", "shortTeam": "DLT" }, { "id": 161, "name": "Boro", "slide": 8, "ultis": ["Ledakan Mesiu Bom Besar"], "short1v1": "Boro", "shortTeam": "BOR" }, { "id": 162, "name": "Isshiki Otsutsuki", "slide": 8, "ultis": ["Daikokuten Sukunahikona Ambruk Ganda"], "short1v1": "Isshiki", "shortTeam": "ISK" }, { "id": 163, "name": "Nanashi Uchiha", "slide": 8, "ultis": ["8.000 Tombak: Api Ganas Yang Memukau"], "short1v1": "Nanashi", "shortTeam": "NNS" }];

    function normCharName(str) {
      if (!str) return '';
      return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/ō/g, 'o').replace(/ū/g, 'u').replace(/ou/g, 'o').replace(/uu/g, 'u')
        .replace(/[^a-z0-9]/g, '');
    }

    const NARUTO_NORM_MAP = new Map();
    NARUTO_CHARACTERS.forEach(c => NARUTO_NORM_MAP.set(normCharName(c.name), c));

    function findNarutoChar(name) {
      if (!name) return null;
      const clean = name.trim();
      const direct = NARUTO_CHARACTERS.find(c => c.name.toLowerCase() === clean.toLowerCase());
      if (direct) return direct;
      return NARUTO_NORM_MAP.get(normCharName(clean)) || null;
    }

    function getPlayerDefaultUlti(charName) {
      if (!charName) return '-';
      const parts = charName.split('/').map(s => s.trim());
      const ultis = [];
      parts.forEach(p => {
        const c = findNarutoChar(p);
        if (c && c.ultis && c.ultis.length > 0) ultis.push(c.ultis[0]);
      });
      return ultis.length > 0 ? ultis.join(' / ') : '-';
    }

    function formatChar(charStr, mode = '1v1') {
      if (!charStr || charStr === '-' || charStr === 'TBA') return charStr || '-';
      const parts = charStr.split('/').map(s => s.trim()).filter(Boolean);
      if (parts.length === 0) return charStr;
      if (mode === '1v1' && parts.length === 1) {
        const c = findNarutoChar(parts[0]);
        return c ? c.short1v1 : parts[0];
      }
      return parts.map(p => {
        const c = findNarutoChar(p);
        return c ? c.shortTeam : p.substring(0, 3).toUpperCase();
      }).join(' / ');
    }

    // ==========================================
    // STATE & SYNC
    // ==========================================
    let state = <?= $initialStateJson ?>;

    let cloudMqttClient = null;
    const LOCAL_CLIENT_ID = 'reg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
    const STREAM_KEY = <?= json_encode($roomKey) ?>;
    let streamKey = STREAM_KEY;
    const MQTT_TOPIC = `koth/naruto/${streamKey}`;

    // Instant Realtime Local Sync (0ms BroadcastChannel across all browser tabs / OBS / TikTok Studio)
    const kothSyncChannel = (typeof BroadcastChannel !== 'undefined') ? new BroadcastChannel('koth_broadcast_' + STREAM_KEY) : null;
    if (kothSyncChannel) {
      kothSyncChannel.onmessage = (event) => {
        if (event.data && event.data.state) {
          if (event.data.clientId === LOCAL_CLIENT_ID) return;
          state = event.data.state;
          sanitizeState(state);
          try { localStorage.setItem('koth_naruto_state', JSON.stringify(state)); } catch (e) { }
          renderAll();
        }
      };
    }

    let currentSelectedTier = 'FREE';
    let currentRegisteredId = null;

    // Character Picker State: Sorting (slide vs alpha) & slide filter
    let regPickerSortMode = localStorage.getItem('koth_reg_char_sort') || 'slide'; // 'slide' atau 'alpha'
    let regPickerSlideFilter = 0; // 0 = Semua

    // Helper sanitasi & sinkronisasi state per mode (1v1, DUO, TIM)
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
      if (st.arena1v1) { fixP(st.arena1v1.p1); fixP(st.arena1v1.p2); }
      if (st.arenaDuo) { fixP(st.arenaDuo.p1); fixP(st.arenaDuo.p2); }
      if (st.arenaTim) { fixP(st.arenaTim.p1); fixP(st.arenaTim.p2); }
      if (Array.isArray(st.queue)) st.queue.forEach(fixP);
      if (Array.isArray(st.saved)) st.saved.forEach(fixP);

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
    }

    function getArenaKey(mode) {
      const m = String(mode || state.masterMode || '1v1').toUpperCase();
      if (m === 'DUO') return 'arenaDuo';
      if (m === 'TIM' || m === 'TRIO') return 'arenaTim';
      return 'arena1v1';
    }

    function syncActiveModeToArena() {
      const curKey = getArenaKey(state.masterMode);
      if (!state[curKey] || typeof state[curKey] !== 'object') {
        state[curKey] = { p1: null, p2: null };
      }
      state[curKey].p1 = state.p1 || null;
      state[curKey].p2 = state.p2 || null;
    }

    // Jalankan sanitasi awal
    sanitizeState(state);

    // Queue Interleaving & Sorting
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

    // CATATAN: Pemain baru yang mendaftar TIDAK PERNAH masuk arena langsung secara otomatis.
    // Slot Arena P1 & P2 dikendalikan mutlak oleh Host/Admin di panel admin.

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
            return { duplicate: true, message: `Pemain "${name}" sedang berada di Arena (${arenaKey.toUpperCase()})` };
          }
          if (st[arenaKey].p2 && st[arenaKey].p2.name && st[arenaKey].p2.name.trim().toLowerCase() === clean) {
            return { duplicate: true, message: `Pemain "${name}" sedang berada di Arena (${arenaKey.toUpperCase()})` };
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

    // ==========================================
    // RENDER UI & ACTIVE MODE ADAPTATION
    // ==========================================
    function applyActiveModeUI() {
      // User TIDAK BISA MEMILIH MODE. Mode yang aktif adalah mode yang dipilih oleh ADMIN!
      const activeMode = state.masterMode || '1v1';
      const modeBadge = document.getElementById('arena-mode-badge');
      if (modeBadge) modeBadge.innerText = activeMode.toUpperCase();

      const bannerTitle = document.getElementById('locked-mode-title');
      const bannerDesc = document.getElementById('locked-mode-desc');
      const bannerPill = document.getElementById('locked-mode-pill');

      const slot2 = document.getElementById('slot-wrap-2');
      const slot3 = document.getElementById('slot-wrap-3');
      const slotLabel1 = document.getElementById('slot-label-1');

      const wrapTim2 = document.getElementById('wrap-tim-2char');
      const chkTim2 = document.getElementById('check-tim-2char');

      if (activeMode === '1v1') {
        if (bannerTitle) bannerTitle.innerText = '🎮 MODE AKTIF LIVE: 1vs1 (SOLO)';
        if (bannerDesc) bannerDesc.innerText = 'Format 1 Karakter Utama + Pilihan Ultimate Jutsu';
        if (bannerPill) bannerPill.innerText = '1vs1';
        if (slot2) slot2.style.display = 'none';
        if (slot3) slot3.style.display = 'none';
        if (slotLabel1) slotLabel1.innerText = 'Karakter Utama:';
        if (wrapTim2) wrapTim2.style.display = 'none';
        if (chkTim2) chkTim2.checked = false;
      } else if (activeMode === 'DUO') {
        if (bannerTitle) bannerTitle.innerText = '⚔️ MODE AKTIF LIVE: DUO (2 KARAKTER)';
        if (bannerDesc) bannerDesc.innerText = 'Format 2 Karakter (Leader + Support 1) + Pilihan Ultimate Jutsu';
        if (bannerPill) bannerPill.innerText = 'DUO';
        if (slot2) slot2.style.display = 'block';
        if (slot3) slot3.style.display = 'none';
        if (slotLabel1) slotLabel1.innerText = 'Karakter 1 (Leader):';
        if (wrapTim2) wrapTim2.style.display = 'none';
        if (chkTim2) chkTim2.checked = false;
      } else {
        // TIM
        if (bannerTitle) bannerTitle.innerText = '👑 MODE AKTIF LIVE: TIM (3 / 2 KARAKTER)';
        if (bannerDesc) bannerDesc.innerText = 'Format Karakter TIM (Leader + Support) + Pilihan Ultimate Jutsu';
        if (bannerPill) bannerPill.innerText = 'TIM';
        if (slot2) slot2.style.display = 'block';
        if (slotLabel1) slotLabel1.innerText = 'Karakter 1 (Leader):';
        if (wrapTim2) wrapTim2.style.display = 'block';
        const is2Char = chkTim2 && chkTim2.checked;
        if (slot3) slot3.style.display = is2Char ? 'none' : 'block';
      }
    }

    function toggleTim2Char() {
      const chk = document.getElementById('check-tim-2char');
      const slot3 = document.getElementById('slot-wrap-3');
      const is2Char = chk && chk.checked;
      if (slot3) {
        slot3.style.display = is2Char ? 'none' : 'block';
        if (is2Char) {
          const c3 = document.getElementById('reg-char-3');
          if (c3) c3.value = '';
          updateUltiDropdown(3, '');
        }
      }
    }
else {
        // TIM
        if (bannerTitle) bannerTitle.innerText = '👑 MODE AKTIF LIVE: TIM (3 KARAKTER)';
        if (bannerDesc) bannerDesc.innerText = 'Format 3 Karakter (Leader + Support 1 + Support 2) + Pilihan Ultimate Jutsu';
        if (bannerPill) bannerPill.innerText = 'TIM';
        if (slot2) slot2.style.display = 'block';
        if (slot3) slot3.style.display = 'block';
        if (slotLabel1) slotLabel1.innerText = 'Karakter 1 (Leader):';
      }
    }

    function getArenaRoles(st) {
      const s = st || state;
      if (s.p2 && (s.p2.ws > 0 || !s.p1)) {
        if (!s.p1 || (s.p1.ws === 0 && s.p2.ws > 0)) {
          return { kingSlot: 'p2', challengerSlot: 'p1' };
        }
      }
      return { kingSlot: 'p1', challengerSlot: 'p2' };
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

    function renderArenaMini() {
      applyActiveModeUI();
      const mode = state.masterMode || '1v1';
      const roles = getArenaRoles(state);

      // Slot 1 (P1)
      const slot1 = document.getElementById('arena-p1-slot');
      const p1Tag = document.getElementById('arena-p1-tag');
      const p1Tier = document.getElementById('arena-p1-tier');
      const p1Name = document.getElementById('arena-p1-name');
      const p1Char = document.getElementById('arena-p1-char');
      const p1Ulti = document.getElementById('arena-p1-ulti');
      const p1Ws = document.getElementById('arena-p1-ws');
      const p1Tickets = document.getElementById('arena-p1-tickets');
      const p1Bonus = document.getElementById('arena-p1-bonus');

      if (state.p1 && state.p1.name && state.p1.name !== '-') {
        const rawT1 = getTierLabel(state.p1.type);
        const clsT1 = getTierCls(state.p1.type);
        if (slot1) slot1.className = 'player-slot p1 ' + clsT1;
        if (p1Tier) {
          p1Tier.className = 'p-role-tag ' + clsT1;
          p1Tier.innerText = rawT1;
          p1Tier.style.display = 'inline-flex';
        }
        if (p1Tag) {
          p1Tag.innerText = (roles.kingSlot === 'p1') ? '👑 JUARA BERTAHAN' : '⚔️ PENANTANG';
          p1Tag.style.display = 'inline-flex';
        }
        if (p1Name) p1Name.innerText = state.p1.name;
        if (p1Char) {
          const fChar1 = formatChar(state.p1.char, mode);
          p1Char.innerText = fChar1;
          p1Char.title = state.p1.char || fChar1;
          if (fChar1.length > 25) {
            p1Char.style.fontSize = '0.70rem';
          } else if (fChar1.length > 18) {
            p1Char.style.fontSize = '0.75rem';
          } else {
            p1Char.style.fontSize = '';
          }
        }
        if (p1Ulti) {
          const u = state.p1.ulti || getPlayerDefaultUlti(state.p1.char);
          if (u && u !== '-' && u !== 'TBA') {
            p1Ulti.style.display = 'block';
            p1Ulti.innerText = `⚡ ${u}`;
            p1Ulti.title = `Ultimate: ${u}`;
          } else {
            p1Ulti.style.display = 'none';
          }
        }
        if (p1Ws) p1Ws.innerText = state.p1.ws || 0;
        if (p1Tickets) p1Tickets.innerText = state.p1.matchesLeft || 0;
        if (p1Bonus) {
          if (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) {
            p1Bonus.innerText = '+1 MATCH';
            p1Bonus.style.display = 'inline-block';
          } else {
            p1Bonus.style.display = 'none';
          }
        }
      } else {
        if (slot1) slot1.className = 'player-slot p1';
        if (p1Tier) {
          p1Tier.className = 'p-role-tag';
          p1Tier.innerText = 'P1';
          p1Tier.style.display = 'inline-flex';
        }
        if (p1Tag) p1Tag.style.display = 'none';
        if (p1Name) p1Name.innerText = 'Menunggu...';
        if (p1Char) {
          p1Char.innerText = '-';
          p1Char.style.fontSize = '';
        }
        if (p1Ulti) p1Ulti.style.display = 'none';
        if (p1Ws) p1Ws.innerText = '0';
        if (p1Tickets) p1Tickets.innerText = '-';
        if (p1Bonus) p1Bonus.style.display = 'none';
      }

      // Slot 2 (P2 - Mirrored)
      const slot2 = document.getElementById('arena-p2-slot');
      const p2Tag = document.getElementById('arena-p2-tag');
      const p2Tier = document.getElementById('arena-p2-tier');
      const p2Name = document.getElementById('arena-p2-name');
      const p2Char = document.getElementById('arena-p2-char');
      const p2Ulti = document.getElementById('arena-p2-ulti');
      const p2Ws = document.getElementById('arena-p2-ws');
      const p2Tickets = document.getElementById('arena-p2-tickets');
      const p2Bonus = document.getElementById('arena-p2-bonus');

      if (state.p2 && state.p2.name && state.p2.name !== '-') {
        const rawT2 = getTierLabel(state.p2.type);
        const clsT2 = getTierCls(state.p2.type);
        if (slot2) slot2.className = 'player-slot p2 ' + clsT2;
        if (p2Tier) {
          p2Tier.className = 'p-role-tag ' + clsT2;
          p2Tier.innerText = rawT2;
          p2Tier.style.display = 'inline-flex';
        }
        if (p2Tag) {
          p2Tag.innerText = (roles.kingSlot === 'p2') ? '👑 JUARA BERTAHAN' : '⚔️ PENANTANG';
          p2Tag.style.display = 'inline-flex';
        }
        if (p2Name) p2Name.innerText = state.p2.name;
        if (p2Char) {
          const fChar2 = formatChar(state.p2.char, mode);
          p2Char.innerText = fChar2;
          p2Char.title = state.p2.char || fChar2;
          if (fChar2.length > 25) {
            p2Char.style.fontSize = '0.70rem';
          } else if (fChar2.length > 18) {
            p2Char.style.fontSize = '0.75rem';
          } else {
            p2Char.style.fontSize = '';
          }
        }
        if (p2Ulti) {
          const u = state.p2.ulti || getPlayerDefaultUlti(state.p2.char);
          if (u && u !== '-' && u !== 'TBA') {
            p2Ulti.style.display = 'block';
            p2Ulti.innerText = `⚡ ${u}`;
            p2Ulti.title = `Ultimate: ${u}`;
          } else {
            p2Ulti.style.display = 'none';
          }
        }
        if (p2Ws) p2Ws.innerText = state.p2.ws || 0;
        if (p2Tickets) p2Tickets.innerText = state.p2.matchesLeft || 0;
        if (p2Bonus) {
          if (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) {
            p2Bonus.innerText = '+1 MATCH';
            p2Bonus.style.display = 'inline-block';
          } else {
            p2Bonus.style.display = 'none';
          }
        }
      } else {
        if (slot2) slot2.className = 'player-slot p2';
        if (p2Tier) {
          p2Tier.className = 'p-role-tag';
          p2Tier.innerText = 'P2';
          p2Tier.style.display = 'inline-flex';
        }
        if (p2Tag) p2Tag.style.display = 'none';
        if (p2Name) p2Name.innerText = 'Menunggu Penantang...';
        if (p2Char) {
          p2Char.innerText = '-';
          p2Char.style.fontSize = '';
        }
        if (p2Ulti) p2Ulti.style.display = 'none';
        if (p2Ws) p2Ws.innerText = '0';
        if (p2Tickets) p2Tickets.innerText = '-';
        if (p2Bonus) p2Bonus.style.display = 'none';
      }

      // Ticker announcement
      const tickerWrap = document.getElementById('ticker-wrap');
      const tickerText = document.getElementById('ticker-text');
      if (state.announcement && state.announcement.trim()) {
        if (tickerWrap) tickerWrap.style.display = 'flex';
        if (tickerText) tickerText.innerText = state.announcement.trim();
      } else {
        if (tickerWrap) tickerWrap.style.display = 'none';
      }
    }

    function renderTierButtons() {
      const tierStatus = state.tierStatus || { 'SROBOT': 'AKTIF', 'VIP': 'AKTIF', 'FREE': 'AKTIF' };

      ['FREE', 'VIP', 'SROBOT'].forEach(k => {
        const s = tierStatus[k] || 'AKTIF';
        const isClosed = (s === 'CLOSED' || s === 'PENUH');
        const pill = document.getElementById(`tier-pill-${k.toLowerCase()}`);
        const btn = document.getElementById(`tier-btn-${k.toLowerCase()}`);

        if (pill) {
          pill.innerText = isClosed ? 'PENUH' : 'AKTIF';
          pill.className = 'tier-status-pill ' + (isClosed ? 'status-closed' : 'status-open');
        }
        if (btn) {
          if (isClosed) {
            btn.classList.add('disabled');
            if (currentSelectedTier === k) {
              if (tierStatus['FREE'] !== 'PENUH') selectTier('FREE');
              else if (tierStatus['VIP'] !== 'PENUH') selectTier('VIP');
              else if (tierStatus['SROBOT'] !== 'PENUH') selectTier('SROBOT');
            }
          } else {
            btn.classList.remove('disabled');
          }
        }
      });
    }

    function renderQueueList() {
      const container = document.getElementById('queue-items-container');
      const countTag = document.getElementById('queue-count-tag');
      const statsText = document.getElementById('queue-stats-text');
      if (!container) return;

      const queue = Array.isArray(state.queue) ? state.queue : [];
      let countSb = 0, countVip = 0, countFree = 0;

      queue.forEach(p => {
        const t = (p.type || '').toUpperCase();
        if (t === 'SROBOT' || t === 'SB') countSb++;
        else if (t === 'VIP') countVip++;
        else countFree++;
      });

      if (countTag) countTag.innerText = `${queue.length} Pemain`;
      if (statsText) statsText.innerText = `SB: ${countSb} | VIP: ${countVip} | FREE: ${countFree}`;

      if (queue.length === 0) {
        container.innerHTML = `
          <div class="empty-queue-box">
            🍃 Antrian masih kosong.<br>Jadilah yang pertama mendaftar!
          </div>
        `;
        return;
      }

      let html = '';
      queue.forEach((p, idx) => {
        const isMe = (p.id === currentRegisteredId);
        const t = (p.type || 'FREE').toUpperCase();
        let roleCls = 'free';
        let roleLabel = 'FREE';
        if (t === 'SROBOT' || t === 'SB' || t === 'FAST' || t === 'VVIP') {
          roleCls = 'srobot';
          roleLabel = 'SB';
        } else if (t === 'VIP') {
          roleCls = 'vip';
          roleLabel = 'VIP';
        }

        const modeBadge = p.ticketMode === 'TIM' ? 'TIM' : (p.ticketMode === 'DUO' ? 'DUO' : '1v1');
        const formatted = formatChar(p.char, p.ticketMode || '1v1');
        const ultiTxt = p.ulti ? ` • ⚡ ${p.ulti}` : '';

        let rankMedalCls = '';
        if (idx === 0) rankMedalCls = 'rank-1';
        else if (idx === 1) rankMedalCls = 'rank-2';
        else if (idx === 2) rankMedalCls = 'rank-3';

        html += `
          <div class="queue-item-card ${isMe ? 'is-me' : ''}">
            <div class="queue-rank-badge ${rankMedalCls}">#${idx + 1}</div>
            <div class="queue-info-wrap">
              <div class="queue-name-row">
                <span class="queue-name-text">${escapeHtml(p.name)}</span>
                <span class="p-role-tag ${roleCls}">${roleLabel}</span>
                <span class="q-mode-badge">${modeBadge}</span>
                ${isMe ? '<span style="font-size:0.65rem; font-weight:900; color:#ffffff; background:#22c55e; padding:1px 6px; border-radius:3px;">KAMU</span>' : ''}
              </div>
              <div class="queue-char-row">
                <b style="color:#ffffff;">${escapeHtml(formatted)}</b>${escapeHtml(ultiTxt)}
              </div>
            </div>
            <div class="q-match-badge">
              ${p.matchesLeft || 1}M
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
    }

    function renderAll() {
      renderArenaMini();
      renderTierButtons();
      renderQueueList();
    }

    // ==========================================
    // HARGA TIER & MATCH CALCULATOR
    // 1v1: 1x VIP = 5 coin / 1k, 1x SROBOT = 30 coin / 5k
    // TIM/DUO: 1x VIP = 10 coin / 2k, 1x SROBOT = 60 coin / 10k
    // ==========================================
    function calculateTierPrice(tier, mode, matchCount = 1) {
      const isTeamOrDuo = (mode === 'DUO' || mode === 'TIM' || mode === 'TRIO');
      let coinPerMatch = 0;
      let rpPerMatch = 0;

      if (tier === 'VIP') {
        if (isTeamOrDuo) {
          coinPerMatch = 10;
          rpPerMatch = 2000;
        } else {
          coinPerMatch = 5;
          rpPerMatch = 1000;
        }
      } else if (tier === 'SROBOT' || tier === 'SB') {
        if (isTeamOrDuo) {
          coinPerMatch = 60;
          rpPerMatch = 10000;
        } else {
          coinPerMatch = 30;
          rpPerMatch = 5000;
        }
      }

      const totalCoins = coinPerMatch * matchCount;
      const totalRp = rpPerMatch * matchCount;

      return {
        isPaid: (coinPerMatch > 0),
        coinPerMatch,
        rpPerMatch,
        totalCoins,
        totalRp,
        totalRpFormatted: new Intl.NumberFormat('id-ID').format(totalRp)
      };
    }

    function updatePriceHint() {
      const hint = document.getElementById('match-tier-hint');
      if (!hint) return;
      if (currentSelectedTier === 'FREE') {
        hint.innerText = '(Khusus FREE: Maksimal 1 Match - Gratis)';
        hint.style.color = '#86efac';
        return;
      }
      const activeMode = state.masterMode || '1v1';
      const curMatches = parseInt(document.getElementById('selected-matches-val').value) || 1;
      const price = calculateTierPrice(currentSelectedTier, activeMode, curMatches);
      hint.innerHTML = `<span style="color:#fde047; font-weight:800;">(Biaya: 🪙 ${price.totalCoins} Koin TikTok / 💳 Rp ${price.totalRpFormatted})</span>`;
    }

    // ==========================================
    // FORM LOGIC
    // ==========================================
    function selectTier(tier) {
      if (state.tierStatus && (state.tierStatus[tier] === 'PENUH' || state.tierStatus[tier] === 'CLOSED')) {
        showToast(`Tier ${tier} sedang PENUH / Ditutup.`);
        return;
      }
      currentSelectedTier = tier;
      document.getElementById('selected-tier-val').value = tier;

      ['free', 'vip', 'srobot'].forEach(t => {
        const btn = document.getElementById(`tier-btn-${t}`);
        if (btn) btn.classList.toggle('active', t.toUpperCase() === tier);
      });

      updateMatchOptionsForTier(tier);
    }

    function updateMatchOptionsForTier(tier) {
      const hint = document.getElementById('match-tier-hint');
      const btn2 = document.getElementById('btn-m-2');
      const btn3 = document.getElementById('btn-m-3');
      const btn5 = document.getElementById('btn-m-5');
      const btnCustom = document.getElementById('btn-m-custom');
      const customWrap = document.getElementById('custom-match-wrap');

      if (tier === 'FREE') {
        if (hint) {
          hint.innerText = '(Khusus FREE: Maksimal 1 Match - Gratis)';
          hint.style.color = '#86efac';
        }
        [btn2, btn3, btn5, btnCustom].forEach(btn => {
          if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.35';
            btn.style.cursor = 'not-allowed';
          }
        });
        if (customWrap) customWrap.style.display = 'none';
        setMatches(1);
      } else {
        [btn2, btn3, btn5, btnCustom].forEach(btn => {
          if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
          }
        });
        updatePriceHint();
      }
    }

    function setMatches(count) {
      if (currentSelectedTier === 'FREE' && count > 1) {
        showToast('Tier FREE hanya diperbolehkan 1 Match!');
        count = 1;
      }
      document.getElementById('selected-matches-val').value = count;
      const customWrap = document.getElementById('custom-match-wrap');
      if (customWrap) customWrap.style.display = 'none';

      [1, 2, 3, 5].forEach(n => {
        const btn = document.getElementById(`btn-m-${n}`);
        if (btn) btn.classList.toggle('active', n === count);
      });
      const btnCustom = document.getElementById('btn-m-custom');
      if (btnCustom) btnCustom.classList.remove('active');
      updatePriceHint();
    }

    function toggleCustomMatch() {
      if (currentSelectedTier === 'FREE') {
        showToast('Tier FREE hanya diperbolehkan 1 Match!');
        setMatches(1);
        return;
      }
      const customWrap = document.getElementById('custom-match-wrap');
      const customInput = document.getElementById('custom-match-input');
      const btnCustom = document.getElementById('btn-m-custom');

      [1, 2, 3, 5].forEach(n => {
        const btn = document.getElementById(`btn-m-${n}`);
        if (btn) btn.classList.remove('active');
      });
      if (btnCustom) btnCustom.classList.add('active');
      if (customWrap) customWrap.style.display = 'flex';

      const val = parseInt(customInput.value) || 4;
      document.getElementById('selected-matches-val').value = val;
      customInput.focus();
      updatePriceHint();
    }

    function onCustomMatchChange(val) {
      if (currentSelectedTier === 'FREE') {
        document.getElementById('selected-matches-val').value = 1;
        return;
      }
      let n = parseInt(val) || 1;
      if (n < 1) n = 1;
      if (n > 100) n = 100;
      document.getElementById('selected-matches-val').value = n;
      updatePriceHint();
    }

    // Pembatasan FREE 1x per hari per HP & per Nama
    function checkFreeDailyLimit(name, st) {
      const todayStr = new Date().toISOString().slice(0, 10);
      const normName = (name || '').trim().toLowerCase();

      // 1. Cek Device via localStorage
      try {
        const devKey = 'koth_free_reg_date';
        const lastRegDate = localStorage.getItem(devKey);
        if (lastRegDate === todayStr) {
          return {
            allowed: false,
            reason: 'Perangkat (HP/Laptop) ini sudah mendaftar Tier FREE hari ini (' + todayStr + ').\n\nPendaftaran FREE dibatasi 1x per hari per perangkat.\nSilakan pilih Tier VIP / SROBOT atau mendaftar kembali besok.'
          };
        }
      } catch (e) { }

      // 2. Cek Nama via localStorage
      try {
        const nameKey = 'koth_free_user_' + normName + '_' + todayStr;
        if (localStorage.getItem(nameKey)) {
          return {
            allowed: false,
            reason: 'Nama "' + name + '" sudah mendaftar Tier FREE hari ini (' + todayStr + ').\n\nPendaftaran FREE dibatasi 1x per hari per nama/HP.\nSilakan pilih Tier VIP / SROBOT atau mendaftar kembali besok.'
          };
        }
      } catch (e) { }

      const isRegisteredToday = (timestamp) => {
        if (!timestamp) return false;
        try {
          return new Date(timestamp).toISOString().slice(0, 10) === todayStr;
        } catch (e) { return false; }
      };

      // 3. Cek Antrian Aktif
      if (Array.isArray(st.queue)) {
        const foundQ = st.queue.find(p => p.type === 'FREE' && (p.name || '').trim().toLowerCase() === normName && isRegisteredToday(p.registeredAt));
        if (foundQ) {
          return {
            allowed: false,
            reason: 'Nama "' + name + '" sudah terdaftar di Tier FREE hari ini (' + todayStr + ').\n\nPendaftaran FREE dibatasi 1x per hari per nama.'
          };
        }
      }

      // 4. Cek Pending Approvals
      if (Array.isArray(st.pendingApprovals)) {
        const foundP = st.pendingApprovals.find(p => p.type === 'FREE' && (p.name || '').trim().toLowerCase() === normName && isRegisteredToday(p.registeredAt));
        if (foundP) {
          return {
            allowed: false,
            reason: 'Nama "' + name + '" sudah memiliki pendaftaran Tier FREE yang menunggu persetujuan hari ini.'
          };
        }
      }

      // 5. Cek Riwayat FREE Hari Ini di State
      if (st.freeHistory && typeof st.freeHistory === 'object') {
        const todayHistory = st.freeHistory[todayStr];
        if (Array.isArray(todayHistory) && todayHistory.includes(normName)) {
          return {
            allowed: false,
            reason: 'Nama "' + name + '" sudah mendaftar Tier FREE hari ini (' + todayStr + ').\n\nPendaftaran FREE dibatasi 1x per hari per nama.'
          };
        }
      }

      return { allowed: true };
    }

    function recordFreeRegistration(name, st) {
      const todayStr = new Date().toISOString().slice(0, 10);
      const normName = (name || '').trim().toLowerCase();
      try {
        localStorage.setItem('koth_free_reg_date', todayStr);
        localStorage.setItem('koth_free_user_' + normName + '_' + todayStr, Date.now().toString());
      } catch (e) { }

      if (!st.freeHistory || typeof st.freeHistory !== 'object') {
        st.freeHistory = {};
      }
      if (!Array.isArray(st.freeHistory[todayStr])) {
        st.freeHistory[todayStr] = [];
      }
      if (!st.freeHistory[todayStr].includes(normName)) {
        st.freeHistory[todayStr].push(normName);
      }
    }

    function validatePlayerName() {
      const input = document.getElementById('reg-name');
      const warn = document.getElementById('reg-name-warning');
      if (!input || !warn) return true;

      const name = input.value.trim();
      if (!name) {
        warn.style.display = 'none';
        return true;
      }

      const dup = checkDuplicatePlayer(name, state);
      if (dup) {
        warn.style.display = 'block';
        warn.innerText = `⚠️ ${dup.message}`;
        return false;
      } else {
        warn.style.display = 'none';
        return true;
      }
    }

    // ==========================================
    // CHARACTER PICKER: PER SLIDE & ABJAD A-Z
    // ==========================================
    let activeSlotPicker = null;

    function openCharPicker(slot) {
      activeSlotPicker = slot;
      closeAllPickers();
      const popup = document.getElementById(`picker-popup-${slot}`);
      if (popup) {
        popup.style.display = 'block';
        renderCharPickerPopup(slot, '');
      }
    }

    function closeAllPickers() {
      [1, 2, 3].forEach(s => {
        const p = document.getElementById(`picker-popup-${s}`);
        if (p) p.style.display = 'none';
      });
    }

    function filterCharPicker(slot) {
      activeSlotPicker = slot;
      const input = document.getElementById(`reg-char-${slot}`);
      const popup = document.getElementById(`picker-popup-${slot}`);
      if (popup) {
        popup.style.display = 'block';
        renderCharPickerPopup(slot, input ? input.value : '');
      }
    }

    function toggleSortMode(mode, slot, event) {
      if (event) { event.preventDefault(); event.stopPropagation(); }
      regPickerSortMode = mode;
      localStorage.setItem('koth_reg_char_sort', mode);
      const input = document.getElementById(`reg-char-${slot}`);
      renderCharPickerPopup(slot, input ? input.value : '');
    }

    function setSlideFilter(slideNum, slot, event) {
      if (event) { event.preventDefault(); event.stopPropagation(); }
      regPickerSlideFilter = slideNum;
      const input = document.getElementById(`reg-char-${slot}`);
      renderCharPickerPopup(slot, input ? input.value : '');
    }

    function renderCharPickerPopup(slot, query) {
      const popup = document.getElementById(`picker-popup-${slot}`);
      if (!popup) return;

      const q = (query || '').toLowerCase().trim();
      let filtered = NARUTO_CHARACTERS.slice();

      if (regPickerSortMode === 'slide') {
        if (regPickerSlideFilter > 0) {
          filtered = filtered.filter(c => c.slide === regPickerSlideFilter);
        }
      } else {
        // Abjad A-Z
        filtered.sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }));
      }

      if (q) {
        filtered = filtered.filter(c => {
          return c.name.toLowerCase().includes(q) ||
            normCharName(c.name).includes(normCharName(q)) ||
            c.short1v1.toLowerCase().includes(q) ||
            c.shortTeam.toLowerCase().includes(q);
        });
      }

      // Sort Switcher Header
      let sortSwitcherHtml = `
        <div class="char-sort-tabs">
          <button type="button" class="char-sort-tab-btn ${regPickerSortMode === 'slide' ? 'active' : ''}" onmousedown="toggleSortMode('slide', ${slot}, event)">📑 Per Slide</button>
          <button type="button" class="char-sort-tab-btn ${regPickerSortMode === 'alpha' ? 'active' : ''}" onmousedown="toggleSortMode('alpha', ${slot}, event)">🔤 Abjad A-Z</button>
        </div>
      `;

      let slideTabsHtml = '';
      if (regPickerSortMode === 'slide') {
        slideTabsHtml = `<div class="char-slide-tabs">`;
        slideTabsHtml += `<button type="button" class="char-slide-tab-btn ${regPickerSlideFilter === 0 ? 'active' : ''}" onmousedown="setSlideFilter(0, ${slot}, event)">Semua (163)</button>`;
        for (let s = 1; s <= 8; s++) {
          slideTabsHtml += `<button type="button" class="char-slide-tab-btn ${regPickerSlideFilter === s ? 'active' : ''}" onmousedown="setSlideFilter(${s}, ${slot}, event)">S${s}</button>`;
        }
        slideTabsHtml += `</div>`;
      } else {
        slideTabsHtml = `<div style="font-size:0.65rem; color:#929AAB; font-weight:700;">Urutan Abjad A sampai Z (${filtered.length} karakter)</div>`;
      }

      let headerHtml = `
        <div class="char-picker-header">
          ${sortSwitcherHtml}
          ${slideTabsHtml}
        </div>
      `;

      if (filtered.length === 0) {
        popup.innerHTML = headerHtml + `
          <div style="padding: 14px; text-align: center; color: #ef4444; font-size: 0.78rem; font-weight: 700;">
            ❌ Karakter tidak ditemukan.<br>Pilih karakter dari daftar resmi.
          </div>
        `;
        return;
      }

      let itemsHtml = `<div style="overflow-y:auto; flex:1;">`;
      let currentGroupHeader = '';

      filtered.forEach(c => {
        if (regPickerSortMode === 'slide') {
          if (c.slide !== currentGroupHeader) {
            currentGroupHeader = c.slide;
            itemsHtml += `<div class="char-group-header">⚡ SLIDE ${c.slide}</div>`;
          }
        } else {
          const firstChar = c.name.charAt(0).toUpperCase();
          if (firstChar !== currentGroupHeader) {
            currentGroupHeader = firstChar;
            itemsHtml += `<div class="char-group-header">🔤 HURUF ${firstChar}</div>`;
          }
        }

        const ultiBadge = c.ultis.length > 1
          ? `<span class="badge-multi-ulti">⚡ ${c.ultis.length} Ulti</span>`
          : `<span class="badge-single-ulti">1 Ulti</span>`;

        const safeName = escapeHtml(c.name).replace(/'/g, "\'");
        itemsHtml += `
          <div class="char-picker-item" onmousedown="selectChar(${slot}, '${safeName}')">
            <span class="char-item-name">${escapeHtml(c.name)}</span>
            <div style="display:flex; gap:4px; align-items:center;">
              <span class="badge-slide">S${c.slide}</span>
              ${ultiBadge}
            </div>
          </div>
        `;
      });
      itemsHtml += `</div>`;

      popup.innerHTML = headerHtml + itemsHtml;
    }

    function selectChar(slot, charName) {
      const input = document.getElementById(`reg-char-${slot}`);
      if (input) input.value = charName;
      updateUltiDropdown(slot, charName);
      closeAllPickers();
    }

    function updateUltiDropdown(slot, charName) {
      const select = document.getElementById(`reg-ulti-${slot}`);
      if (!select) return;

      const c = findNarutoChar(charName);
      select.innerHTML = '';

      if (!c) {
        select.innerHTML = '<option value="">(Pilih karakter dulu)</option>';
        select.disabled = true;
        return;
      }

      c.ultis.forEach((u, i) => {
        const opt = document.createElement('option');
        opt.value = u;
        opt.innerText = (c.ultis.length > 1) ? `⚡ [${i + 1}] ${u}` : `🔒 ${u}`;
        select.appendChild(opt);
      });

      // Kunci jika hanya 1 ulti, buka dropdown jika > 1 ulti
      select.disabled = (c.ultis.length <= 1);
    }

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.char-picker-container')) {
        closeAllPickers();
      }
    });

    // ==========================================
    // SUBMIT PENDAFTARAN
    // ==========================================
    async function handleRegistrationSubmit(e) {
      if (e) e.preventDefault();

      const nameInput = document.getElementById('reg-name');
      const name = nameInput ? nameInput.value.trim() : '';

      if (!name) {
        alert("Silakan masukkan nama atau In-Game Name kamu!");
        if (nameInput) nameInput.focus();
        return;
      }

      // 1. Anti-Double Check (Antrian & Arena)
      const dup = checkDuplicatePlayer(name, state);
      if (dup) {
        alert(`❌ PENDAFTARAN DITOLAK!

${dup.message}.

Kamu tidak bisa mendaftar dobel saat masih antri atau bertanding.`);
        return;
      }

      // Cek apakah juga sudah ada di list pending approvals
      if (Array.isArray(state.pendingApprovals)) {
        const normName = name.toLowerCase();
        const pendingFound = state.pendingApprovals.find(p => (p.name || '').trim().toLowerCase() === normName);
        if (pendingFound) {
          alert(`❌ PENDAFTARAN DITOLAK!

Nama "${name}" sudah terdaftar dan sedang menunggu persetujuan Admin.`);
          return;
        }
      }

      // 2. Mode Pertandingan Dikontrol Admin
      const activeMode = state.masterMode || '1v1';
      const c1 = document.getElementById('reg-char-1').value.trim();
      const c2 = document.getElementById('reg-char-2').value.trim();
      const c3 = document.getElementById('reg-char-3').value.trim();

      if (!c1) {
        alert("Pilih Karakter Utama (Slot 1) terlebih dahulu!");
        openCharPicker(1);
        return;
      }

      const isTim2Char = (activeMode === 'TIM' && document.getElementById('check-tim-2char') && document.getElementById('check-tim-2char').checked);

      const chars = [c1];
      if (activeMode === 'DUO' || activeMode === 'TIM') {
        if (!c2) {
          alert("Pilih Karakter 2 (Support 1) untuk mode " + activeMode + "!");
          openCharPicker(2);
          return;
        }
        chars.push(c2);
      }
      if (activeMode === 'TIM' && !isTim2Char) {
        if (!c3) {
          alert("Pilih Karakter 3 (Support 2) untuk mode TIM!\nAtau centang 'Pakai hanya 2 karakter' jika ingin bertanding dengan 2 karakter.");
          openCharPicker(3);
          return;
        }
        chars.push(c3);
      }

      for (let i = 0; i < chars.length; i++) {
        if (!findNarutoChar(chars[i])) {
          alert(`❌ KARAKTER TIDAK VALID!

Karakter "${chars[i]}" tidak ditemukan dalam daftar 163 Naruto resmi.
Silakan pilih dari popup saran.`);
          openCharPicker(i + 1);
          return;
        }
      }

      // 3. Validasi Tier
      const tier = currentSelectedTier;
      if (state.tierStatus && (state.tierStatus[tier] === 'PENUH' || state.tierStatus[tier] === 'CLOSED')) {
        alert(`❌ TIER ${tier} SEDANG PENUH!

Silakan pilih tier lain yang berstatus AKTIF.`);
        return;
      }

      // Batasan FREE: 1x per hari per HP/Nama
      if (tier === 'FREE') {
        const freeCheck = checkFreeDailyLimit(name, state);
        if (!freeCheck.allowed) {
          alert(`❌ BATAS PENDAFTARAN FREE TERCAPAI!

${freeCheck.reason}`);
          return;
        }
      }

      // 4. Ulti & Match
      const fullChar = chars.join(' / ');
      const u1 = document.getElementById('reg-ulti-1').value.trim();
      const u2 = document.getElementById('reg-ulti-2').value.trim();
      const u3 = document.getElementById('reg-ulti-3').value.trim();
      const ultiArr = [u1];
      if (activeMode === 'DUO' || activeMode === 'TIM') ultiArr.push(u2);
      if (activeMode === 'TIM' && !isTim2Char && u3) ultiArr.push(u3);
      const fullUlti = ultiArr.filter(Boolean).join(' / ') || getPlayerDefaultUlti(fullChar);

      let matchesCount = parseInt(document.getElementById('selected-matches-val').value) || 1;
      if (tier === 'FREE') {
        matchesCount = 1; // FREE dikunci hanya 1 match
      }

      // Alur pendaftaran:
      // FREE: Otomatis langsung masuk ke antrian live tanpa persetujuan admin (syarat follow host)
      // VIP & SROBOT: Memerlukan persetujuan admin setelah konfirmasi gift
      const isPendingApproval = (tier !== 'FREE');
      let confirmNote = '';
      if (tier === 'FREE') {
        confirmNote = `\n• Biaya: GRATIS (1 Match)\n• Syarat: Wajib Follow TikTok Host @herzg1209\n\n⚡ Pendaftaran Tier FREE otomatis langsung masuk antrian live!`;
      } else {
        const pr = calculateTierPrice(tier, activeMode, matchesCount);
        confirmNote = `\n• Biaya Gift: 🪙 ${pr.totalCoins} Koin TikTok atau 💳 Rp ${pr.totalRpFormatted} (SociaBuzz)\n\n⚠️ Pendaftaran Tier ${tier} memerlukan persetujuan Admin sebelum masuk ke antrian live.`;
      }

      // Konfirmasi
      const msg = `Konfirmasi Pendaftaran:\n` +
        `• Nama: ${name}\n` +
        `• Mode: ${activeMode}\n` +
        `• Karakter: ${fullChar}\n` +
        `• Ulti: ${fullUlti}\n` +
        `• Tier: ${tier}\n` +
        `• Match: ${matchesCount}` +
        confirmNote + `\n\n` +
        `Lanjutkan pendaftaran?`;

      if (!confirm(msg)) return;

      // Ambil state terbaru dari MySQL sebelum menulis
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

      // Validasi ulang dengan data terbaru
      const dupLatest = checkDuplicatePlayer(name, state);
      if (dupLatest) {
        alert(`❌ PENDAFTARAN DITOLAK!\n\n${dupLatest.message}.`);
        renderAll();
        return;
      }
      if (Array.isArray(state.pendingApprovals)) {
        const normName = name.toLowerCase();
        const pendingFound = state.pendingApprovals.find(p => (p.name || '').trim().toLowerCase() === normName);
        if (pendingFound) {
          alert(`❌ PENDAFTARAN DITOLAK!\n\nNama "${name}" sudah terdaftar dan sedang menunggu persetujuan Admin.`);
          renderAll();
          return;
        }
      }
      if (tier === 'FREE') {
        const freeCheckLatest = checkFreeDailyLimit(name, state);
        if (!freeCheckLatest.allowed) {
          alert(`❌ BATAS PENDAFTARAN FREE TERCAPAI!\n\n${freeCheckLatest.reason}`);
          renderAll();
          return;
        }
      }
      if (state.tierStatus && (state.tierStatus[tier] === 'PENUH' || state.tierStatus[tier] === 'CLOSED')) {
        alert(`❌ TIER ${tier} BARU SAJA PENUH / DITUTUP!`);
        renderAll();
        return;
      }

      const newPlayer = {
        id: (isPendingApproval ? 'pnd_' : 'pl_') + Date.now().toString(),
        name,
        char: fullChar,
        ulti: fullUlti,
        matchesLeft: matchesCount,
        ws: 0,
        type: tier,
        ticketMode: activeMode,
        registeredAt: Date.now(),
        status: isPendingApproval ? 'PENDING' : 'APPROVED'
      };

      currentRegisteredId = newPlayer.id;

      let posInfo = '';
      if (isPendingApproval) {
        // VIP & SROBOT: Masuk ke list pending approvals
        if (!Array.isArray(state.pendingApprovals)) state.pendingApprovals = [];
        state.pendingApprovals.push(newPlayer);
        posInfo = `⏳ Menunggu Persetujuan Admin (${tier})`;
      } else {
        // FREE: Otomatis masuk antrian live tanpa persetujuan
        if (!Array.isArray(state.queue)) state.queue = [];
        state.queue.push(newPlayer);
        sortQueue(state);
        recordFreeRegistration(name, state);
        const qIdx = state.queue.findIndex(p => p.id === newPlayer.id);
        posInfo = `Antrian #${qIdx !== -1 ? qIdx + 1 : state.queue.length}`;
      }

      if (!Array.isArray(state.registeredUsers)) state.registeredUsers = [];
      if (!state.registeredUsers.includes(name)) state.registeredUsers.push(name);

      saveAndBroadcastState();

      // Tampilkan popup instruksi gift (VIP/SROBOT) atau syarat follow TikTok (FREE)
      showRegistrationModal({
        name,
        tier,
        char: fullChar,
        ulti: fullUlti,
        matches: matchesCount,
        mode: activeMode,
        pos: posInfo,
        isPending: isPendingApproval
      });

      // Reset Form
      if (nameInput) nameInput.value = '';
      document.getElementById('reg-char-1').value = '';
      document.getElementById('reg-char-2').value = '';
      document.getElementById('reg-char-3').value = '';
      updateUltiDropdown(1, '');
      updateUltiDropdown(2, '');
      updateUltiDropdown(3, '');
      validatePlayerName();
      renderAll();
    }

    let pendingSaves = 0;
    let lastLocalSaveAt = 0;

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
      return `${getAppBaseUrl()}api/state.php?room=${encodeURIComponent(streamKey)}`;
    }

    function saveAndBroadcastState() {
      state._clientId = LOCAL_CLIENT_ID;
      state._ts = Date.now();
      syncActiveModeToArena();
      const stateJson = JSON.stringify(state);
      lastLocalSaveAt = Date.now();

      try {
        localStorage.setItem('koth_naruto_state', stateJson);
      } catch (e) { }

      // 1. BroadcastChannel: Notifikasi instan 0ms ke tab admin & overlay
      if (kothSyncChannel) {
        try {
          kothSyncChannel.postMessage({ type: 'KOTH_STATE_UPDATE', state: state, ts: state._ts, clientId: LOCAL_CLIENT_ID });
        } catch (e) { }
      }

      if (cloudMqttClient && cloudMqttClient.connected) {
        try {
          cloudMqttClient.publish(MQTT_TOPIC, stateJson, { retain: true, qos: 0 });
        } catch (e) { }
      }

      if (window.location.protocol.startsWith('http')) {
        pendingSaves++;
        fetch(getStateApiUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: stateJson
        })
          .catch(() => { })
          .finally(() => {
            pendingSaves = Math.max(0, pendingSaves - 1);
          });
      }
    }

    function showRegistrationModal(data) {
      const modal = document.getElementById('modal-success');
      if (!modal) return;

      const nameEl = document.getElementById('suc-name');
      const tierEl = document.getElementById('suc-tier');
      const modeEl = document.getElementById('suc-mode');
      const matchCountEl = document.getElementById('suc-match-count');
      const charEl = document.getElementById('suc-char');
      const ultiEl = document.getElementById('suc-ulti');
      const ultiRow = document.getElementById('suc-ulti-row');

      if (nameEl) nameEl.innerText = data.name;
      if (tierEl) {
        tierEl.innerText = data.tier;
        tierEl.className = 'p-role-tag ' + (data.tier === 'SROBOT' ? 'fast' : (data.tier === 'VIP' ? 'vip' : 'free'));
      }
      if (modeEl) modeEl.innerText = data.mode;
      if (matchCountEl) matchCountEl.innerText = `${data.matches} Match`;
      if (charEl) charEl.innerText = data.char;
      if (ultiEl) ultiEl.innerText = data.ulti || '-';
      if (ultiRow) ultiRow.style.display = (data.ulti && data.ulti !== '-') ? 'flex' : 'none';

      const priceBox = document.getElementById('modal-price-box');
      const giftFlow = document.getElementById('modal-gift-flow');
      const freeFlow = document.getElementById('modal-free-flow');

      const headerIcon = document.getElementById('modal-header-icon');
      const headerTitle = document.getElementById('modal-header-title');
      const headerDesc = document.getElementById('modal-header-desc');

      if (data.tier === 'FREE') {
        if (priceBox) priceBox.style.display = 'none';
        if (giftFlow) giftFlow.style.display = 'none';
        if (freeFlow) freeFlow.style.display = 'block';

        const freeBadge = document.getElementById('free-queue-badge');
        if (freeBadge) freeBadge.innerText = `⚡ ${data.pos || 'Otomatis Masuk Antrian Live'}`;

        const freeStepQuestion = document.getElementById('free-step-question');
        const freeStepFollow = document.getElementById('free-step-follow');
        const freeStepConfirmed = document.getElementById('free-step-confirmed');

        if (freeStepQuestion) freeStepQuestion.style.display = 'block';
        if (freeStepFollow) freeStepFollow.style.display = 'none';
        if (freeStepConfirmed) freeStepConfirmed.style.display = 'none';

        const freeMsg = document.getElementById('free-confirmed-msg');
        if (freeMsg) {
          freeMsg.innerHTML = `Terima kasih sudah follow! Kamu telah resmi terdaftar di <b>${data.pos || 'antrian match'}</b>. Pantau streaming saat giliranmu bertanding!`;
        }

        if (headerIcon) headerIcon.innerText = '📱';
        if (headerTitle) headerTitle.innerText = 'PERSYARATAN TIER FREE';
        if (headerDesc) headerDesc.innerText = 'Kamu sudah masuk antrian! Silakan follow akun TikTok Host untuk memenuhi syarat.';
      } else {
        // VIP or SROBOT
        if (freeFlow) freeFlow.style.display = 'none';
        if (priceBox) priceBox.style.display = 'block';
        if (giftFlow) giftFlow.style.display = 'block';

        const price = calculateTierPrice(data.tier, data.mode, data.matches);
        const coinsEl = document.getElementById('price-coins');
        const rpEl = document.getElementById('price-rp');
        const badgeTikTok = document.getElementById('badge-gift-tiktok');
        const badgeSociaBuzz = document.getElementById('badge-gift-sociabuzz');

        if (coinsEl) coinsEl.innerText = `${price.totalCoins} Koin`;
        if (rpEl) rpEl.innerText = `Rp ${price.totalRpFormatted}`;
        if (badgeTikTok) badgeTikTok.innerText = `${price.totalCoins} Koin`;
        if (badgeSociaBuzz) badgeSociaBuzz.innerText = `Rp ${price.totalRpFormatted}`;

        const stepQuestion = document.getElementById('gift-step-question');
        const stepOptions = document.getElementById('gift-step-options');
        const stepConfirmed = document.getElementById('gift-step-confirmed');

        if (stepQuestion) stepQuestion.style.display = 'block';
        if (stepOptions) stepOptions.style.display = 'none';
        if (stepConfirmed) stepConfirmed.style.display = 'none';

        if (headerIcon) headerIcon.innerText = '🎁';
        if (headerTitle) headerTitle.innerText = 'INSTRUKSI GIFT / DONASI';
        if (headerDesc) headerDesc.innerText = 'Pendaftaran kamu berhasil dicatat & menunggu persetujuan Admin!';
      }

      modal.style.display = 'flex';
    }

    function showSuccessModal(data) {
      showRegistrationModal(data);
    }

    function handleFreeFollowAnswer(ans) {
      const stepQuestion = document.getElementById('free-step-question');
      const stepFollow = document.getElementById('free-step-follow');
      const stepConfirmed = document.getElementById('free-step-confirmed');
      const headerIcon = document.getElementById('modal-header-icon');
      const headerTitle = document.getElementById('modal-header-title');
      const headerDesc = document.getElementById('modal-header-desc');

      if (ans === 'belum') {
        // Otomatis buka link profil TikTok host di tab baru sesuai instruksi
        window.open('https://www.tiktok.com/@herzg1209', '_blank');

        if (stepQuestion) stepQuestion.style.display = 'none';
        if (stepFollow) stepFollow.style.display = 'block';
        if (stepConfirmed) stepConfirmed.style.display = 'none';
        if (headerIcon) headerIcon.innerText = '📱';
        if (headerTitle) headerTitle.innerText = 'FOLLOW TIKTOK HOST';
        if (headerDesc) headerDesc.innerText = 'Silakan follow @herzg1209 di TikTok untuk memenuhi syarat:';
      } else if (ans === 'sudah') {
        if (stepQuestion) stepQuestion.style.display = 'none';
        if (stepFollow) stepFollow.style.display = 'none';
        if (stepConfirmed) stepConfirmed.style.display = 'block';
        if (headerIcon) headerIcon.innerText = '🎉';
        if (headerTitle) headerTitle.innerText = 'PENDAFTARAN AKTIF!';
        if (headerDesc) headerDesc.innerText = 'Kamu sudah terdaftar di antrian match live stream.';
      }
    }

    function handleGiftAnswer(ans) {
      const stepQuestion = document.getElementById('gift-step-question');
      const stepOptions = document.getElementById('gift-step-options');
      const stepConfirmed = document.getElementById('gift-step-confirmed');
      const headerIcon = document.getElementById('modal-header-icon');
      const headerTitle = document.getElementById('modal-header-title');
      const headerDesc = document.getElementById('modal-header-desc');

      if (ans === 'belum') {
        if (stepQuestion) stepQuestion.style.display = 'none';
        if (stepOptions) stepOptions.style.display = 'block';
        if (stepConfirmed) stepConfirmed.style.display = 'none';
        if (headerTitle) headerTitle.innerText = 'PILIHAN GIFT / DONASI';
        if (headerDesc) headerDesc.innerText = 'Silakan pilih gift TikTok Live atau donasi SociaBuzz di bawah:';
      } else if (ans === 'sudah') {
        if (stepQuestion) stepQuestion.style.display = 'none';
        if (stepOptions) stepOptions.style.display = 'none';
        if (stepConfirmed) stepConfirmed.style.display = 'block';
        if (headerIcon) headerIcon.innerText = '🎉';
        if (headerTitle) headerTitle.innerText = 'TERIMA KASIH!';
        if (headerDesc) headerDesc.innerText = 'Pendaftaran kamu telah masuk antrian persetujuan Admin.';
      }
    }

    function closeSuccessModal(e) {
      if (e && e.target && e.target.id !== 'modal-success') return;
      const modal = document.getElementById('modal-success');
      if (modal) modal.style.display = 'none';
      showToast('Pendaftaran tersimpan di antrian!');
    }

    function showToast(msg) {
      const toast = document.getElementById('reg-toast');
      if (!toast) return;
      toast.innerText = msg;
      toast.style.opacity = '1';
      toast.style.transform = 'translateX(-50%) translateY(0)';
      if (toast._timer) clearTimeout(toast._timer);
      toast._timer = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(20px)';
      }, 2500);
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // ==========================================
    // INIT SYNC
    // ==========================================
    function initSync() {
      const isHttp = window.location.protocol.startsWith('http');
      if (!isHttp) {
        try {
          const saved = localStorage.getItem('koth_naruto_state');
          if (saved) {
            const parsed = JSON.parse(saved);
            if (parsed && typeof parsed === 'object') {
              state = parsed;
            }
          }
        } catch (e) { }
      } else {
        try {
          if (state && typeof state === 'object') {
            localStorage.setItem('koth_naruto_state', JSON.stringify(state));
          }
        } catch (e) { }
      }

      sanitizeState(state);
      renderAll();

      if (typeof mqtt !== 'undefined') {
        try {
          cloudMqttClient = mqtt.connect('wss://broker.emqx.io:8084/mqtt', {
            clientId: LOCAL_CLIENT_ID,
            clean: true,
            reconnectPeriod: 2500
          });

          cloudMqttClient.on('connect', () => {
            const badge = document.getElementById('sync-text');
            if (badge) badge.innerText = 'Live Sync: Aktif';
            cloudMqttClient.subscribe(MQTT_TOPIC, { qos: 0 });
          });

          cloudMqttClient.on('message', (topic, payload) => {
            try {
              const remote = JSON.parse(payload.toString());
              if (remote && remote._clientId === LOCAL_CLIENT_ID) return;
              state = remote;
              sanitizeState(state);
              try { localStorage.setItem('koth_naruto_state', JSON.stringify(state)); } catch (e) { }
              renderAll();
            } catch (e) { }
          });

          cloudMqttClient.on('close', () => {
            const badge = document.getElementById('sync-text');
            if (badge) badge.innerText = 'Menghubungkan...';
          });
        } catch (e) { }
      }

      function fetchStateFromDb() {
        if (!window.location.protocol.startsWith('http')) return;
        if (pendingSaves > 0) return;
        const fetchStartedAt = Date.now();
        fetch(getStateApiUrl(), { cache: 'no-store' })
          .then(res => res.ok ? res.json() : null)
          .then(data => {
            if (!data || typeof data !== 'object') return;
            if (pendingSaves > 0 || fetchStartedAt < lastLocalSaveAt) return;
            if (data._clientId === LOCAL_CLIENT_ID) return;
            state = data;
            sanitizeState(state);
            try { localStorage.setItem('koth_naruto_state', JSON.stringify(state)); } catch (e) { }
            renderAll();
          })
          .catch(() => { });
      }

      if (window.location.protocol.startsWith('http')) {
        fetchStateFromDb();
        setInterval(fetchStateFromDb, 2000);
      }

      window.addEventListener('storage', (e) => {
        if (e.key === 'koth_naruto_state' && e.newValue) {
          try {
            const parsed = JSON.parse(e.newValue);
            if (parsed && typeof parsed === 'object') {
              if (parsed._clientId === LOCAL_CLIENT_ID) return;
              state = parsed;
              sanitizeState(state);
              renderAll();
            }
          } catch (err) { }
        }
      });
    }

    window.addEventListener('DOMContentLoaded', initSync);
  </script>
</body>

</html>