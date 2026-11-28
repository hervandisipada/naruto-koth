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

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/vip.php';
$appDir = rtrim(dirname($scriptName), '/\\');
$appBaseUrl = ($appDir === '' || $appDir === '.') ? '/' : $appDir . '/';
$urlTier = isset($_GET['tier']) ? strtoupper(trim((string)$_GET['tier'])) : '';
if ($urlTier !== 'VIP' && $urlTier !== 'SROBOT') {
  $urlTier = 'VIP';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Pendaftaran VIP & SROBOT - Naruto Storm KotH</title>
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

    /* HUB PENDAFTARAN BERBAYAR */
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
      color: #facc15;
    }

    /* TOMBOL PILIHAN BERBAYAR */
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
      max-width: 600px;
      box-shadow: 0 16px 45px rgba(0, 0, 0, 0.8);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      max-height: 92vh;
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

    /* STEP BREADCRUMBS (5 LANGKAH) */
    .step-indicator-bar {
      display: flex;
      background: rgba(0, 0, 0, 0.35);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding: 8px 10px;
      gap: 4px;
      overflow-x: auto;
    }
    .step-crumb {
      flex: 1;
      min-width: 60px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 6px 3px;
      border-radius: 6px;
      font-size: 0.70rem;
      font-weight: 800;
      color: #64748b;
      background: rgba(255, 255, 255, 0.03);
      transition: all 0.18s ease;
      white-space: nowrap;
    }
    .step-crumb.active {
      color: #ffffff;
      background: rgba(234, 179, 8, 0.25);
      border: 1px solid rgba(234, 179, 8, 0.5);
    }
    .step-crumb.done {
      color: #fde047;
      background: rgba(234, 179, 8, 0.12);
    }
    .step-num {
      width: 17px;
      height: 17px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.65rem;
    }
    .step-crumb.active .step-num {
      background: #facc15;
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
      border-color: #facc15;
      box-shadow: 0 0 10px rgba(250, 204, 21, 0.25);
      background: rgba(0, 0, 0, 0.6);
    }

    /* TIER SELECTOR IN MODAL */
    .tier-toggle-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .tier-toggle-card {
      border: 2px solid rgba(255, 255, 255, 0.15);
      background: rgba(0, 0, 0, 0.35);
      border-radius: 8px;
      padding: 12px;
      text-align: center;
      cursor: pointer;
      user-select: none;
      transition: all 0.15s ease;
    }
    .tier-toggle-card:hover {
      border-color: rgba(255, 255, 255, 0.3);
      transform: translateY(-1px);
    }
    .tier-toggle-card.selected.tier-vip {
      background: rgba(234, 179, 8, 0.2);
      border-color: #eab308;
      box-shadow: 0 0 12px rgba(234, 179, 8, 0.35);
    }
    .tier-toggle-card.selected.tier-srobot {
      background: rgba(168, 85, 247, 0.22);
      border-color: #a855f7;
      box-shadow: 0 0 12px rgba(168, 85, 247, 0.35);
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
      background: #facc15;
      color: #422006;
      border-color: #facc15;
    }

    /* CHARACTER CARDS GRID (A-Z) */
    .char-grid-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 8px;
      max-height: 270px;
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
      background: rgba(234, 179, 8, 0.2);
      border-color: #eab308;
      box-shadow: 0 0 10px rgba(234, 179, 8, 0.35);
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
    .badge-status-banned {
      font-size: 0.65rem;
      font-weight: 800;
      color: #ef4444;
      background: rgba(239, 68, 68, 0.2);
      border: 1px solid rgba(239, 68, 68, 0.45);
      padding: 1px 5px;
      border-radius: 3px;
    }
    .badge-status-vip-unlocked {
      font-size: 0.65rem;
      font-weight: 800;
      color: #facc15;
      background: rgba(234, 179, 8, 0.2);
      border: 1px solid rgba(234, 179, 8, 0.45);
      padding: 1px 5px;
      border-radius: 3px;
    }
    .char-card-item.duplicate-team {
      border-color: rgba(239, 68, 68, 0.45) !important;
      background: rgba(239, 68, 68, 0.12) !important;
      opacity: 0.55;
      cursor: not-allowed;
    }
    .badge-status-duplicate {
      font-size: 0.65rem;
      font-weight: 800;
      color: #f87171;
      background: rgba(239, 68, 68, 0.2);
      border: 1px solid rgba(239, 68, 68, 0.45);
      padding: 1px 5px;
      border-radius: 3px;
      display: inline-block;
    }

    /* ULTI CARDS */
    .ulti-options-grid {
      display: flex;
      flex-direction: column;
      gap: 8px;
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

    /* MATCH COUNTER */
    .match-buttons-row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .btn-match-count {
      flex: 1;
      min-width: 65px;
      background: rgba(255, 255, 255, 0.08);
      border: 1.5px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      padding: 10px 8px;
      border-radius: 8px;
      font-weight: 800;
      font-size: 0.86rem;
      cursor: pointer;
      transition: all 0.15s ease;
      user-select: none;
    }
    .btn-match-count:hover {
      background: rgba(255, 255, 255, 0.18);
    }
    .btn-match-count.active {
      background: #facc15;
      color: #000000;
      border-color: #facc15;
      font-weight: 900;
      box-shadow: 0 0 10px rgba(250, 204, 21, 0.4);
    }

    .counter-control-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      justify-content: center;
      margin-top: 8px;
      background: rgba(0, 0, 0, 0.35);
      padding: 10px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .btn-counter {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.25);
      background: rgba(255, 255, 255, 0.1);
      color: #ffffff;
      font-size: 1.3rem;
      font-weight: 900;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.15s ease;
    }
    .btn-counter:hover {
      background: rgba(255, 255, 255, 0.2);
    }
    .counter-val-display {
      font-size: 1.4rem;
      font-weight: 900;
      color: #fde047;
      min-width: 60px;
      text-align: center;
    }

    /* PRICE BOX */
    .price-display-card {
      background: linear-gradient(135deg, rgba(234, 179, 8, 0.15), rgba(168, 85, 247, 0.12));
      border: 2px solid #eab308;
      border-radius: 10px;
      padding: 14px;
      text-align: center;
    }
    .price-values-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 8px;
    }
    .price-value-box {
      background: rgba(0, 0, 0, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      padding: 8px 10px;
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
      background: rgba(234, 179, 8, 0.18);
      border-color: #facc15;
      box-shadow: 0 0 10px rgba(250, 204, 21, 0.3);
    }
    .char-slot-tab.filled {
      border-color: #22c55e;
    }
    .char-slot-tab.filled.active {
      border-color: #facc15;
    }
    .char-slot-badge {
      font-size: 0.65rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #94a3b8;
    }
    .char-slot-tab.active .char-slot-badge {
      color: #facc15;
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
      background: #facc15;
      color: #000000;
      font-weight: 900;
      margin-left: auto;
    }
    .btn-step-next:hover {
      background: #fde047;
    }
    .btn-step-submit {
      background: linear-gradient(135deg, #eab308, #ca8a04);
      color: #000000;
      font-weight: 900;
      padding: 11px 22px;
      font-size: 0.92rem;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      margin-left: auto;
      box-shadow: 0 4px 14px rgba(234, 179, 8, 0.4);
    }
    .btn-step-submit:hover {
      background: linear-gradient(135deg, #fde047, #eab308);
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

    /* GIFT QUESTION */
    .gift-action-link {
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px 14px;
      border-radius: 8px;
      font-weight: 900;
      font-size: 0.88rem;
      transition: all 0.15s ease;
    }
    .gift-action-link:hover {
      transform: translateY(-1px);
    }
  </style>
</head>

<body>
  <?php
  $sidebarActive = 'vip';
  include __DIR__ . '/includes/sidebar.php';
  ?>

  <div class="app-wrapper">

    <!-- TOP HEADER BAR -->
    <?php
    $headerSubtitle = 'VIP & SROBOT';
    include __DIR__ . '/includes/header.php';
    ?>

    <!-- TICKER NOTE MARQUEE -->
    <div class="overlay-ticker" id="ticker-wrap" style="display: none;">
      <span class="ticker-label">NOTE:</span>
      <div class="ticker-marquee-wrap" id="ticker-marquee-wrap">
        <span class="ticker-content" id="ticker-text">-</span>
      </div>
    </div>

    <!-- MAIN REGISTRATION HUB CARD -->
    <main class="hub-card">
      <div class="hub-header-icon">⭐</div>
      <h1 class="hub-title">PENDAFTARAN VIP & SROBOT</h1>
      <p class="hub-subtitle">
        Jalur antrian prioritas cepat &amp; super cepat untuk bertanding di Live Stream TikTok!
      </p>

      <div class="hub-mode-pill" id="hub-mode-pill">
        <span>Mode Pertandingan:</span> <b id="hub-mode-text">1vs1 (SOLO)</b>
      </div>

      <!-- 2 TOMBOL UTAMA BERBAYAR -->
      <div class="hub-buttons-grid">
        <!-- 1. DAFTAR VIP -->
        <button type="button" class="btn-hub-option opt-vip" onclick="openPaidModal('VIP')">
          <div class="btn-hub-icon">⭐</div>
          <div class="btn-hub-body">
            <div class="btn-hub-title">DAFTAR VIP</div>
            <div class="btn-hub-desc">Prioritas Match Cepat • Bebas Pilih Jumlah Match • 5 Koin / Rp 1K</div>
          </div>
          <div class="btn-hub-tag tag-vip">PRIORITAS</div>
        </button>

        <!-- 2. DAFTAR SROBOT -->
        <button type="button" class="btn-hub-option opt-srobot" onclick="openPaidModal('SROBOT')">
          <div class="btn-hub-icon">⚡</div>
          <div class="btn-hub-body">
            <div class="btn-hub-title">DAFTAR SROBOT</div>
            <div class="btn-hub-desc">Potong Antrian Terdepan • Super Instan • 30 Koin / Rp 5K</div>
          </div>
          <div class="btn-hub-tag tag-srobot">SUPER FAST</div>
        </button>
      </div>

      <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 6px;">
        🍃 Ingin antrian normal gratis? 👉 <a href="index.php" style="color: #4ade80; font-weight:800; text-decoration: underline;">Daftar Free Request di sini</a>
      </div>
    </main>
  </div>

  <!-- ==================================================== -->
  <!-- MODAL 5 LANGKAH: PENDAFTARAN VIP & SROBOT -->
  <!-- ==================================================== -->
  <div id="modal-paid-reg" class="koth-modal-backdrop" onclick="if(event.target === this) closePaidModal()">
    <div class="koth-modal-box">
      <!-- HEADER -->
      <div class="koth-modal-header">
        <div class="koth-modal-title">
          <span id="paid-modal-icon">⭐</span>
          <span id="paid-modal-title">PENDAFTARAN PRIORITAS MATCH</span>
        </div>
        <button type="button" class="koth-modal-close" onclick="closePaidModal()" title="Tutup">✕</button>
      </div>

      <!-- STEP BREADCRUMBS (5 LANGKAH) -->
      <div class="step-indicator-bar">
        <div class="step-crumb active" id="p-crumb-1">
          <span class="step-num">1</span>
          <span>Nama</span>
        </div>
        <div class="step-crumb" id="p-crumb-2">
          <span class="step-num">2</span>
          <span>Karakter</span>
        </div>
        <div class="step-crumb" id="p-crumb-3">
          <span class="step-num">3</span>
          <span>Ulti</span>
        </div>
        <div class="step-crumb" id="p-crumb-4">
          <span class="step-num">4</span>
          <span>Match</span>
        </div>
        <div class="step-crumb" id="p-crumb-5">
          <span class="step-num">5</span>
          <span>Bayar</span>
        </div>
      </div>

      <!-- BODY CONTENT -->
      <div class="koth-modal-body">

        <!-- LANGKAH 1: NAMA TIKTOK & PILIHAN TIER -->
        <div id="paid-step-1" class="step-panel">
          <div class="form-group" style="margin-bottom: 12px;">
            <label class="form-label" for="paid-name-input">
              <span>1. Masukkan Nama TikTok / In-Game Name:</span>
            </label>
            <input type="text" id="paid-name-input" class="form-control"
              placeholder="Contoh: @narutofan atau Herz..." autocomplete="off" oninput="onPaidNameInput()">
            <div id="paid-name-warning" style="display:none; font-size:0.75rem; font-weight:800; color:#fca5a5; background:rgba(239,68,68,0.2); border:1px solid #ef4444; padding:6px 10px; border-radius:6px; margin-top:4px;">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">
              <span>Pilih Jalur Prioritas:</span>
            </label>
            <div class="tier-toggle-row">
              <div class="tier-toggle-card tier-vip selected" id="opt-tier-vip" onclick="setPaidTier('VIP')">
                <div style="font-size: 24px; margin-bottom: 2px;">⭐</div>
                <div style="font-weight: 900; color: #facc15; font-size: 1.02rem;">VIP</div>
                <div style="font-size: 0.72rem; color: #cbd5e1; margin-top: 2px;">Prioritas Match Cepat</div>
                <div style="font-size: 0.68rem; color: #fde047; margin-top: 4px; font-weight: 800;">5 Koin / Rp 1K per match</div>
              </div>

              <div class="tier-toggle-card tier-srobot" id="opt-tier-srobot" onclick="setPaidTier('SROBOT')">
                <div style="font-size: 24px; margin-bottom: 2px;">⚡</div>
                <div style="font-weight: 900; color: #c084fc; font-size: 1.02rem;">SROBOT</div>
                <div style="font-size: 0.72rem; color: #cbd5e1; margin-top: 2px;">Potong Antrian Terdepan</div>
                <div style="font-size: 0.68rem; color: #e9d5ff; margin-top: 4px; font-weight: 800;">30 Koin / Rp 5K per match</div>
              </div>
            </div>
          </div>
        </div>

        <!-- LANGKAH 2: PILIH KARAKTER (URUT ABJAD A - Z) -->
        <div id="paid-step-2" class="step-panel" style="display: none;">
          <!-- Multi-Character Slot Header & Tabs for DUO / TIM -->
          <div id="paid-mode-banner" style="background: rgba(234, 179, 8, 0.12); border: 1px solid rgba(234, 179, 8, 0.35); border-radius: 8px; padding: 6px 12px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; font-weight: 800; color: #fde047;">
            <span id="paid-mode-banner-text">⚡ Mode: 1vs1</span>
            <span style="font-size: 0.72rem; color: #cbd5e1; font-weight: 600;">Pilih karakter sesuai slot</span>
          </div>

          <div class="char-slot-tabs-bar" id="paid-slot-tabs" style="display: none;"></div>

          <div id="paid-tim-flex-bar" style="display: none; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); border: 1px dashed rgba(255,255,255,0.2); border-radius: 6px; padding: 4px 10px; margin-bottom: 8px; font-size: 0.75rem; color: #cbd5e1;">
            <span>Mode Tim: Fleksibel (2 atau 3 Karakter)</span>
            <button type="button" class="btn btn-xs" id="btn-paid-tim-toggle" onclick="togglePaidTimSlot3()" style="background: rgba(234, 179, 8, 0.2); border: 1px solid #facc15; color: #facc15; border-radius: 4px; padding: 2px 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer;">
              🔄 Gunakan 2 Karakter Saja
            </button>
          </div>

          <div class="form-group">
            <label class="form-label">
              <span>2. Pilih Karakter Naruto (Urut Abjad A - Z):</span>
              <span id="paid-selected-char-badge" style="color: #facc15; font-weight:800; font-size:0.75rem;"></span>
            </label>
            <input type="text" id="paid-char-search" class="form-control"
              placeholder="🔍 Cari nama karakter atau ulti (misal: Sasuke, kilat, malam...)" autocomplete="off"
              oninput="filterPaidChars()">
          </div>

          <!-- Quick Alphabet Filter -->
          <div class="alphabet-filter-bar" id="paid-alpha-bar"></div>

          <!-- Grid Karakter A-Z -->
          <div class="char-grid-container" id="paid-char-grid"></div>
        </div>

        <!-- LANGKAH 3: PILIH ULTI (JIKA LEBIH DARI 1) -->
        <div id="paid-step-3" class="step-panel" style="display: none;">
          <div id="paid-slots-ulti-container"></div>
        </div>

        <!-- LANGKAH 4: PILIH JUMLAH MATCH -->
        <div id="paid-step-4" class="step-panel" style="display: none;">
          <div class="form-group">
            <label class="form-label">
              <span>4. Pilih Jumlah Match yang Diinginkan:</span>
            </label>
            <div class="match-buttons-row">
              <button type="button" class="btn-match-count active" id="btn-pm-1" onclick="setPaidMatches(1)">1 Match</button>
              <button type="button" class="btn-match-count" id="btn-pm-2" onclick="setPaidMatches(2)">2 Match</button>
              <button type="button" class="btn-match-count" id="btn-pm-3" onclick="setPaidMatches(3)">3 Match</button>
              <button type="button" class="btn-match-count" id="btn-pm-5" onclick="setPaidMatches(5)">5 Match</button>
            </div>

            <div class="counter-control-wrap">
              <button type="button" class="btn-counter" onclick="changePaidMatchCounter(-1)">−</button>
              <div class="counter-val-display" id="paid-match-val-display">1</div>
              <button type="button" class="btn-counter" onclick="changePaidMatchCounter(1)">+</button>
            </div>
          </div>

          <!-- LIVE ESTIMASI BIAYA -->
          <div class="price-display-card">
            <div style="font-size: 0.74rem; font-weight: 800; color: #fde047; text-transform: uppercase; letter-spacing: 0.5px;">
              ⚡ Estimasi Biaya Gift / Donasi
            </div>
            <div class="price-values-grid">
              <div class="price-value-box">
                <div style="font-size: 0.70rem; color: #94a3b8;">🪙 TikTok Live</div>
                <div id="p-calc-coins" style="font-size: 1.15rem; font-weight: 900; color: #facc15;">5 Koin</div>
              </div>
              <div class="price-value-box">
                <div style="font-size: 0.70rem; color: #94a3b8;">💳 SociaBuzz</div>
                <div id="p-calc-rp" style="font-size: 1.15rem; font-weight: 900; color: #4ade80;">Rp 1.000</div>
              </div>
            </div>
          </div>
        </div>

        <!-- LANGKAH 5: INFO JUMLAH PEMBAYARAN & KONFIRMASI -->
        <div id="paid-step-5" class="step-panel" style="display: none;">
          <!-- RINGKASAN DATA -->
          <div class="reg-summary-card">
            <div style="font-size:0.74rem; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px;">Rincian Pendaftaran:</div>
            <div class="summary-row">
              <span class="summary-row-label">Mode:</span>
              <span class="summary-row-val" id="sum-paid-mode" style="color: #38bdf8;">1v1</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Jalur:</span>
              <span class="summary-row-val" id="sum-paid-tier" style="color: #facc15;">VIP</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Nama TikTok:</span>
              <span class="summary-row-val" id="sum-paid-name">-</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Karakter:</span>
              <span class="summary-row-val" id="sum-paid-char">-</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Ultimate Jutsu:</span>
              <span class="summary-row-val" id="sum-paid-ulti" style="color: #facc15;">-</span>
            </div>
            <div class="summary-row">
              <span class="summary-row-label">Jumlah Match:</span>
              <span class="summary-row-val" id="sum-paid-matches" style="color: #fde047;">1 Match</span>
            </div>
          </div>

          <!-- INFO PEMBAYARAN -->
          <div class="price-display-card" style="margin-top: 2px;">
            <div style="font-size: 0.78rem; font-weight: 900; color: #fde047; text-transform: uppercase;">
              🪙 TOTAL BIAYA PEMBAYARAN
            </div>
            <div class="price-values-grid">
              <div class="price-value-box">
                <div style="font-size: 0.70rem; color: #94a3b8;">🪙 TikTok Live:</div>
                <div id="final-coins-val" style="font-size: 1.25rem; font-weight: 900; color: #facc15;">5 Koin</div>
              </div>
              <div class="price-value-box">
                <div style="font-size: 0.70rem; color: #94a3b8;">💳 SociaBuzz:</div>
                <div id="final-rp-val" style="font-size: 1.25rem; font-weight: 900; color: #4ade80;">Rp 1.000</div>
              </div>
            </div>
            <div style="font-size: 0.68rem; color: #cbd5e1; margin-top: 6px;">
              *Pilih salah satu metode pembayaran (Gift TikTok ATAU Donasi SociaBuzz).
            </div>
          </div>

          <!-- LINK BAYAR LANGSUNG -->
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <a href="https://www.tiktok.com/@herzg1209/live" target="_blank" rel="noopener noreferrer" class="gift-action-link"
              style="background: #000000; border: 1.5px solid #fe2c55; color: #ffffff;">
              <span>🎁 Buka TikTok Live @herzg1209</span>
              <span id="badge-gift-tt-coins" style="background: #fe2c55; color: #fff; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px;">5 Koin</span>
            </a>
            <a href="https://sociabuzz.com/herz1209/tribe" target="_blank" rel="noopener noreferrer" class="gift-action-link"
              style="background: #052e16; border: 1.5px solid #22c55e; color: #86efac;">
              <span>💳 Bayar via SociaBuzz</span>
              <span id="badge-gift-sb-rp" style="background: #22c55e; color: #000; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px;">Rp 1.000</span>
            </a>
          </div>

          <!-- KONFIRMASI SUDAH / BELUM -->
          <div style="background: rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:10px 12px;">
            <div style="font-size:0.78rem; font-weight:800; color:#f8fafc; margin-bottom:8px;">
              Apakah kamu sudah mengirim Gift / Donasi?
            </div>
            <div style="display: flex; gap: 8px;">
              <button type="button" id="btn-gift-ans-sudah" onclick="setGiftStatus('sudah')"
                style="flex:1; background:rgba(34, 197, 94, 0.25); border:1px solid #22c55e; color:#86efac; font-weight:900; padding:8px; border-radius:6px; cursor:pointer;">
                ✅ Sudah Kirim
              </button>
              <button type="button" id="btn-gift-ans-belum" onclick="setGiftStatus('belum')"
                style="flex:1; background:rgba(239, 68, 68, 0.2); border:1px solid #ef4444; color:#fca5a5; font-weight:900; padding:8px; border-radius:6px; cursor:pointer;">
                ❌ Belum
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- FOOTER NAVIGATION -->
      <div class="koth-modal-footer">
        <button type="button" class="btn-step-nav btn-step-prev" id="btn-paid-prev" onclick="goToPaidStep(paidCurrentStep - 1)" style="display: none;">
          ⬅ Kembali
        </button>
        <button type="button" class="btn-step-nav btn-step-next" id="btn-paid-next" onclick="goToPaidStep(paidCurrentStep + 1)">
          Lanjut ke Pilih Karakter ➜
        </button>
        <button type="button" class="btn-step-submit" id="btn-paid-submit" onclick="submitPaidRegistration()" style="display: none;">
          ✅ KIRIM PENDAFTARAN & TUNGGU APPROVAL
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL SUKSES PENDAFTARAN BERBAYAR -->
  <div id="modal-paid-success" class="koth-modal-backdrop" onclick="if(event.target === this) closePaidSuccessModal()">
    <div class="koth-modal-box" style="max-width: 440px; text-align: center; padding: 22px 18px;">
      <div style="font-size: 42px; margin-bottom: 6px;">⏳</div>
      <h3 style="font-size: 1.2rem; font-weight: 900; color: #facc15; margin-bottom: 4px;">
        PENDAFTARAN DICATAT!
      </h3>
      <p style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 14px;">
        Pendaftaran kamu menunggu konfirmasi Host di live stream.
      </p>

      <div class="reg-summary-card" style="text-align: left; margin-bottom: 14px;">
        <div class="summary-row">
          <span class="summary-row-label">Nama TikTok:</span>
          <span class="summary-row-val" id="suc-p-name">-</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Mode:</span>
          <span class="summary-row-val" id="suc-p-mode" style="color: #38bdf8;">1v1</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Jalur:</span>
          <span class="summary-row-val" id="suc-p-tier" style="color: #facc15;">VIP</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Karakter:</span>
          <span class="summary-row-val" id="suc-p-char">-</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Ultimate:</span>
          <span class="summary-row-val" id="suc-p-ulti" style="color: #facc15;">-</span>
        </div>
        <div class="summary-row">
          <span class="summary-row-label">Jumlah Match:</span>
          <span class="summary-row-val" id="suc-p-match" style="color: #4ade80;">1 Match</span>
        </div>
      </div>

      <div style="font-size: 0.76rem; color: #fde047; margin-bottom: 16px; background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 6px; line-height: 1.4;">
        ⚡ Host akan segera memverifikasi Gift / Donasi kamu dan memasukkan namamu ke antrian prioritas!
      </div>

      <button type="button" class="btn-step-submit" style="width: 100%; margin: 0;" onclick="closePaidSuccessModal()">
        OKE, TERIMA KASIH!
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
    const LOCAL_CLIENT_ID = 'reg_vip_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
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
    let paidCurrentStep = 1;
    let paidSelectedTier = 'VIP';
    let paidSlots = [
      { char: null, ulti: '' },
      { char: null, ulti: '' },
      { char: null, ulti: '' }
    ];
    let paidActiveSlot = 0;
    let paidTimCount = 3; // 2 or 3 for TIM mode
    let paidMatchCount = 1;
    let paidAlphaFilter = 'ALL';
    let paidGiftSent = false;

    function getPaidActiveSlotCount() {
      const mode = (state.masterMode || '1v1').toUpperCase();
      if (mode === 'DUO') return 2;
      if (mode === 'TIM' || mode === 'TRIO') return paidTimCount;
      return 1;
    }

    function getPaidSlotLabel(idx) {
      const mode = (state.masterMode || '1v1').toUpperCase();
      if (mode === 'DUO') {
        return idx === 0 ? 'Leader' : 'Support';
      }
      if (mode === 'TIM' || mode === 'TRIO') {
        if (idx === 0) return 'Leader';
        return `Support ${idx}`;
      }
      return 'Karakter Utama';
    }

    function openPaidModal(tier = 'VIP') {
      paidCurrentStep = 1;
      paidSelectedTier = (tier === 'SROBOT' || tier === 'SB') ? 'SROBOT' : 'VIP';
      paidSlots = [
        { char: null, ulti: '' },
        { char: null, ulti: '' },
        { char: null, ulti: '' }
      ];
      paidActiveSlot = 0;
      paidTimCount = 3;
      paidMatchCount = 1;
      paidGiftSent = false;

      document.getElementById('paid-name-input').value = '';
      document.getElementById('paid-name-warning').style.display = 'none';
      document.getElementById('paid-char-search').value = '';
      paidAlphaFilter = 'ALL';

      setPaidTier(paidSelectedTier);
      setPaidMatches(1);
      buildPaidAlphabetBar();
      renderPaidSlotTabs();
      renderPaidCharGrid();
      goToPaidStep(1);

      document.getElementById('modal-paid-reg').style.display = 'flex';
      setTimeout(() => {
        const inp = document.getElementById('paid-name-input');
        if (inp) inp.focus();
      }, 150);
    }

    function renderPaidSlotTabs() {
      const tabsBar = document.getElementById('paid-slot-tabs');
      const timBar = document.getElementById('paid-tim-flex-bar');
      const banner = document.getElementById('paid-mode-banner-text');
      const mode = (state.masterMode || '1v1').toUpperCase();
      const count = getPaidActiveSlotCount();

      if (banner) {
        if (mode === 'DUO') {
          banner.innerText = '⚔️ Mode: DUO (2 Karakter - Leader & Support)';
        } else if (mode === 'TIM' || mode === 'TRIO') {
          banner.innerText = `👑 Mode: TIM (${paidTimCount} Karakter - ${paidTimCount === 2 ? 'Leader + 1 Support' : 'Leader + 2 Support'})`;
        } else {
          banner.innerText = '⚡ Mode: 1vs1 (Single)';
        }
      }

      if (timBar) {
        timBar.style.display = (mode === 'TIM' || mode === 'TRIO') ? 'flex' : 'none';
        const btnToggle = document.getElementById('btn-paid-tim-toggle');
        if (btnToggle) {
          btnToggle.innerHTML = paidTimCount === 3
            ? '🔄 Mau 2 Karakter Saja? Klik Sini'
            : '➕ Tambah Jadi 3 Karakter (Full Team)';
        }
      }

      if (mode === '1V1') {
        if (tabsBar) tabsBar.style.display = 'none';
        const badge = document.getElementById('paid-selected-char-badge');
        if (badge) {
          badge.innerText = paidSlots[0].char ? `✓ ${paidSlots[0].char.name}` : '';
        }
        return;
      }

      if (!tabsBar) return;
      tabsBar.style.display = 'flex';

      let html = '';
      for (let i = 0; i < count; i++) {
        const slot = paidSlots[i];
        const isActive = (i === paidActiveSlot);
        const isFilled = Boolean(slot.char);
        const roleLabel = getPaidSlotLabel(i);
        const charName = slot.char ? slot.char.name : '(Pilih Karakter)';

        html += `
          <div class="char-slot-tab ${isActive ? 'active' : ''} ${isFilled ? 'filled' : ''}" onclick="selectPaidSlot(${i})">
            <div class="char-slot-badge">
              <span>${isActive ? '👉 ' : ''}Slot ${i + 1} • ${roleLabel}</span>
              ${isFilled ? '<span style="color:#22c55e;">✓</span>' : '<span style="color:#ef4444;">•</span>'}
            </div>
            <div class="char-slot-name">${escapeHtml(charName)}</div>
          </div>
        `;
      }
      tabsBar.innerHTML = html;

      const badge = document.getElementById('paid-selected-char-badge');
      if (badge) {
        const curSlot = paidSlots[paidActiveSlot];
        const curRole = getPaidSlotLabel(paidActiveSlot);
        badge.innerText = curSlot && curSlot.char
          ? `✓ Slot ${paidActiveSlot + 1} (${curRole}): ${curSlot.char.name}`
          : `Slot ${paidActiveSlot + 1} (${curRole}): Belum Dipilih`;
      }
    }

    function togglePaidTimSlot3() {
      if (paidTimCount === 3) {
        paidTimCount = 2;
        if (paidActiveSlot > 1) paidActiveSlot = 1;
      } else {
        paidTimCount = 3;
      }
      renderPaidSlotTabs();
      renderPaidCharGrid();
    }

    function selectPaidSlot(idx) {
      const maxSlots = getPaidActiveSlotCount();
      if (idx < 0 || idx >= maxSlots) return;
      paidActiveSlot = idx;
      renderPaidSlotTabs();
      renderPaidCharGrid();
    }

    function closePaidModal() {
      document.getElementById('modal-paid-reg').style.display = 'none';
    }

    function closePaidSuccessModal() {
      document.getElementById('modal-paid-success').style.display = 'none';
    }

    function setPaidTier(tier) {
      paidSelectedTier = (tier === 'SROBOT' || tier === 'SB') ? 'SROBOT' : 'VIP';
      const cVip = document.getElementById('opt-tier-vip');
      const cSrobot = document.getElementById('opt-tier-srobot');

      if (cVip) cVip.classList.toggle('selected', paidSelectedTier === 'VIP');
      if (cSrobot) cSrobot.classList.toggle('selected', paidSelectedTier === 'SROBOT');

      const modalIcon = document.getElementById('paid-modal-icon');
      const modalTitle = document.getElementById('paid-modal-title');
      if (modalIcon) modalIcon.innerText = (paidSelectedTier === 'VIP') ? '⭐' : '⚡';
      if (modalTitle) modalTitle.innerText = `PENDAFTARAN TIER ${paidSelectedTier}`;

      updatePaidPriceDisplay();
    }

    function setPaidMatches(count) {
      paidMatchCount = Math.max(1, parseInt(count) || 1);
      [1, 2, 3, 5].forEach(n => {
        const btn = document.getElementById(`btn-pm-${n}`);
        if (btn) btn.classList.toggle('active', n === paidMatchCount);
      });
      const display = document.getElementById('paid-match-val-display');
      if (display) display.innerText = paidMatchCount;
      updatePaidPriceDisplay();
    }

    function changePaidMatchCounter(delta) {
      setPaidMatches(paidMatchCount + delta);
    }

    function calculatePaidPrice(tier, mode, count) {
      const isTeamOrDuo = (mode === 'DUO' || mode === 'TIM' || mode === 'TRIO');
      let coinPerMatch = 0;
      let rpPerMatch = 0;

      if (tier === 'VIP') {
        if (isTeamOrDuo) { coinPerMatch = 10; rpPerMatch = 2000; }
        else { coinPerMatch = 5; rpPerMatch = 1000; }
      } else {
        // SROBOT
        if (isTeamOrDuo) { coinPerMatch = 60; rpPerMatch = 10000; }
        else { coinPerMatch = 30; rpPerMatch = 5000; }
      }

      const totalCoins = coinPerMatch * count;
      const totalRp = rpPerMatch * count;
      const totalRpFormatted = totalRp.toLocaleString('id-ID');

      return { totalCoins, totalRp, totalRpFormatted };
    }

    function updatePaidPriceDisplay() {
      const activeMode = state.masterMode || '1v1';
      const pr = calculatePaidPrice(paidSelectedTier, activeMode, paidMatchCount);

      const calcCoins = document.getElementById('p-calc-coins');
      const calcRp = document.getElementById('p-calc-rp');
      if (calcCoins) calcCoins.innerText = `${pr.totalCoins} Koin`;
      if (calcRp) calcRp.innerText = `Rp ${pr.totalRpFormatted}`;

      const finalCoins = document.getElementById('final-coins-val');
      const finalRp = document.getElementById('final-rp-val');
      if (finalCoins) finalCoins.innerText = `${pr.totalCoins} Koin`;
      if (finalRp) finalRp.innerText = `Rp ${pr.totalRpFormatted}`;

      const badgeTt = document.getElementById('badge-gift-tt-coins');
      const badgeSb = document.getElementById('badge-gift-sb-rp');
      if (badgeTt) badgeTt.innerText = `${pr.totalCoins} Koin`;
      if (badgeSb) badgeSb.innerText = `Rp ${pr.totalRpFormatted}`;
    }

    function goToPaidStep(step) {
      if (step === 2) {
        const nameVal = (document.getElementById('paid-name-input').value || '').trim();
        if (!nameVal) {
          showPaidWarning('Silakan masukkan nama TikTok kamu terlebih dahulu!');
          return;
        }
        const dup = checkDuplicatePlayer(nameVal, state);
        if (dup) {
          showPaidWarning(dup.message);
          return;
        }
        hidePaidWarning();
        renderPaidSlotTabs();
      }

      if (step === 3) {
        const count = getPaidActiveSlotCount();
        for (let i = 0; i < count; i++) {
          if (!paidSlots[i].char) {
            alert(`Silakan pilih karakter untuk Slot ${i + 1} (${getPaidSlotLabel(i)}) terlebih dahulu!`);
            selectPaidSlot(i);
            return;
          }
        }
        setupPaidStep3();
      }

      if (step === 4) {
        updatePaidPriceDisplay();
      }

      if (step === 5) {
        setupPaidStep5();
      }

      paidCurrentStep = step;

      // Update breadcrumbs (5 langkah)
      for (let i = 1; i <= 5; i++) {
        const crumb = document.getElementById(`p-crumb-${i}`);
        if (crumb) {
          crumb.classList.toggle('active', i === step);
          crumb.classList.toggle('done', i < step);
        }
        const pnl = document.getElementById(`paid-step-${i}`);
        if (pnl) pnl.style.display = (i === step) ? 'block' : 'none';
      }

      // Update buttons
      const btnPrev = document.getElementById('btn-paid-prev');
      const btnNext = document.getElementById('btn-paid-next');
      const btnSubmit = document.getElementById('btn-paid-submit');

      btnPrev.style.display = (step > 1) ? 'inline-flex' : 'none';

      if (step < 5) {
        btnNext.style.display = 'inline-flex';
        btnSubmit.style.display = 'none';
        if (step === 1) btnNext.innerText = 'Lanjut: Pilih Karakter ➜';
        else if (step === 2) btnNext.innerText = 'Lanjut: Pilih Ulti ➜';
        else if (step === 3) btnNext.innerText = 'Lanjut: Jumlah Match ➜';
        else if (step === 4) btnNext.innerText = 'Lanjut: Info Pembayaran ➜';
      } else {
        btnNext.style.display = 'none';
        btnSubmit.style.display = 'inline-flex';
      }
    }

    function showPaidWarning(msg) {
      const el = document.getElementById('paid-name-warning');
      if (el) {
        el.innerText = msg;
        el.style.display = 'block';
      }
    }
    function hidePaidWarning() {
      const el = document.getElementById('paid-name-warning');
      if (el) el.style.display = 'none';
    }
    function onPaidNameInput() {
      hidePaidWarning();
    }

    // Alphabet bar
    const ALPHABET_LETTERS = ['ALL', 'A', 'B', 'C', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'Y', 'Z'];
    function buildPaidAlphabetBar() {
      const bar = document.getElementById('paid-alpha-bar');
      if (!bar) return;
      bar.innerHTML = ALPHABET_LETTERS.map(letter => `
        <button type="button" class="btn-alpha ${letter === paidAlphaFilter ? 'active' : ''}" onclick="setPaidAlphaFilter('${letter}')">
          ${letter}
        </button>
      `).join('');
    }

    function setPaidAlphaFilter(letter) {
      paidAlphaFilter = letter;
      buildPaidAlphabetBar();
      renderPaidCharGrid();
    }

    function filterPaidChars() {
      renderPaidCharGrid();
    }

    function renderPaidCharGrid() {
      const container = document.getElementById('paid-char-grid');
      if (!container) return;
      const q = (document.getElementById('paid-char-search').value || '').trim().toLowerCase();

      let list = ALPHABETICAL_CHARS.slice();
      if (paidAlphaFilter !== 'ALL') {
        list = list.filter(c => c.name.toUpperCase().startsWith(paidAlphaFilter));
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

      container.innerHTML = list.map(c => {
        const curSlotChar = paidSlots[paidActiveSlot] ? paidSlots[paidActiveSlot].char : null;
        const isSel = curSlotChar && curSlotChar.id === c.id;
        const activeMode = (state.masterMode || '1v1').toUpperCase();
        const isTeamOrDuo = (activeMode === 'DUO' || activeMode === 'TIM' || activeMode === 'TRIO');
        const dupSlotIdx = isTeamOrDuo ? paidSlots.findIndex((s, idx) => idx !== paidActiveSlot && s.char && isSameBaseCharacter(s.char, c)) : -1;
        const ultiCount = (c.ultis && c.ultis.length > 0) ? c.ultis.length : 1;
        const safeUltiArg = c._matchedUlti ? escapeHtml(c._matchedUlti).replace(/'/g, "\\'") : '';
        const matchTagHtml = c._matchedUlti ? `
          <div style="font-size:0.68rem; font-weight:800; color:#facc15; background:rgba(234, 179, 8, 0.15); border:1px solid rgba(234, 179, 8, 0.35); padding:2px 6px; border-radius:4px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="Ulti Cocok: ${escapeHtml(c._matchedUlti)}">
            ⚡ ${escapeHtml(c._matchedUlti)}
          </div>
        ` : '';

        const banned = typeof isCharBanned === 'function' && isCharBanned(c);
        const isVipEx = !banned && (typeof isCharVipExclusive === 'function' && isCharVipExclusive(c));

        let cardClass = 'char-card-item';
        let clickAttr = `onclick="selectPaidChar(${c.id}, '${safeUltiArg}')"`;
        let statusBadgeHtml = '';

        if (banned) {
          cardClass += ' disabled banned';
          clickAttr = `onclick="showBannedAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-banned">🚫 BANNED</span>`;
        } else if (dupSlotIdx !== -1) {
          cardClass += ' disabled duplicate-team';
          const dupSlotName = (paidSlots[dupSlotIdx] && paidSlots[dupSlotIdx].char) ? paidSlots[dupSlotIdx].char.name : '';
          clickAttr = `onclick="showDuplicateTeamAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}', ${dupSlotIdx + 1}, '${escapeHtml(dupSlotName).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-duplicate">👥 DUPLIKAT (Slot ${dupSlotIdx + 1})</span>`;
        } else {
          if (isVipEx) {
            statusBadgeHtml = `<span class="badge-status-vip-unlocked">⭐ VIP EXCLUSIVE</span>`;
          }
          if (isSel) {
            cardClass += ' selected';
          }
        }

        let metaRight = statusBadgeHtml;
        if (isSel) {
          metaRight = `<span style="color:#4ade80; font-weight:800;">✅ Slot ${paidActiveSlot + 1}</span>`;
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

    function selectPaidChar(id, preselectedUlti = '') {
      const c = NARUTO_CHARACTERS.find(x => x.id === id);
      if (!c) return;
      if (typeof isCharBanned === 'function' && isCharBanned(c)) {
        showBannedAlert(c.name);
        return;
      }

      const activeMode = (state.masterMode || '1v1').toUpperCase();
      const isTeamOrDuo = (activeMode === 'DUO' || activeMode === 'TIM' || activeMode === 'TRIO');
      if (isTeamOrDuo) {
        const dupIdx = paidSlots.findIndex((s, idx) => idx !== paidActiveSlot && s.char && isSameBaseCharacter(s.char, c));
        if (dupIdx !== -1) {
          const dupName = (paidSlots[dupIdx] && paidSlots[dupIdx].char) ? paidSlots[dupIdx].char.name : '';
          showDuplicateTeamAlert(c.name, dupIdx + 1, dupName);
          return;
        }
      }

      let chosenUlti = preselectedUlti;
      if (!chosenUlti && c.ultis && c.ultis.length > 0) {
        chosenUlti = c.ultis[0];
      }

      paidSlots[paidActiveSlot] = {
        char: c,
        ulti: chosenUlti
      };

      const count = getPaidActiveSlotCount();
      // Auto-advance ke slot berikutnya jika masih kosong
      if (paidActiveSlot === 0 && count > 1 && !paidSlots[1].char) {
        paidActiveSlot = 1;
      } else if (paidActiveSlot === 1 && count > 2 && !paidSlots[2].char) {
        paidActiveSlot = 2;
      }

      renderPaidSlotTabs();
      renderPaidCharGrid();
    }

    function showBannedAlert(charName) {
      alert(`🚫 KARAKTER DIBANNED!\n\nKarakter "${charName}" berstatus BANNED dan tidak dapat dipilih untuk semua tier (Free, VIP, maupun Srobot).`);
    }

    function setupPaidStep3() {
      const container = document.getElementById('paid-slots-ulti-container');
      if (!container) return;

      const count = getPaidActiveSlotCount();
      let html = '';

      for (let i = 0; i < count; i++) {
        const s = paidSlots[i];
        if (!s || !s.char) continue;
        const c = s.char;
        const roleLabel = getPaidSlotLabel(i);
        const ultis = c.ultis || [];

        let ultiBody = '';
        if (ultis.length > 1) {
          if (!s.ulti || !ultis.includes(s.ulti)) {
            s.ulti = ultis[0];
          }
          ultiBody = `
            <div style="font-size: 0.76rem; color: #cbd5e1; margin-bottom: 6px;">Pilih Ultimate Jutsu:</div>
            <div class="ulti-options-grid">
              ${ultis.map(u => `
                <div class="ulti-card-item ${u === s.ulti ? 'selected' : ''}" onclick="selectPaidSlotUlti(${i}, '${escapeHtml(u).replace(/'/g, "\\'")}')">
                  <div class="ulti-card-name">
                    <span>⚡</span>
                    <span>${escapeHtml(u)}</span>
                  </div>
                  <span>${u === s.ulti ? '✅' : '⚪'}</span>
                </div>
              `).join('')}
            </div>
          `;
        } else {
          s.ulti = (ultis.length === 1) ? ultis[0] : c.name;
          ultiBody = `
            <div style="background: rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.08); border-radius:6px; padding:8px 10px;">
              <div style="font-size:0.72rem; color:#94a3b8;">Ultimate Bawaan:</div>
              <div style="font-size:0.92rem; font-weight:800; color:#facc15; margin-top:2px;">⚡ ${escapeHtml(s.ulti)}</div>
            </div>
          `;
        }

        html += `
          <div class="slot-ulti-card">
            <div class="slot-ulti-header">
              <span class="slot-ulti-tag">Slot ${i + 1} • ${escapeHtml(roleLabel)}</span>
              <span class="slot-ulti-char-name">${escapeHtml(c.name)}</span>
            </div>
            ${ultiBody}
          </div>
        `;
      }

      container.innerHTML = html;
    }

    function selectPaidSlotUlti(slotIdx, u) {
      if (paidSlots[slotIdx]) {
        paidSlots[slotIdx].ulti = u;
        setupPaidStep3();
      }
    }

    function setupPaidStep5() {
      const nameVal = (document.getElementById('paid-name-input').value || '').trim();
      const mode = (state.masterMode || '1v1').toUpperCase();
      const count = getPaidActiveSlotCount();
      const activeSlots = paidSlots.slice(0, count);

      const charString = activeSlots.map(s => s.char ? s.char.name : '-').join(' / ');
      const ultiString = activeSlots.map(s => (s.ulti || (s.char ? s.char.name : '-'))).join(' / ');

      const elMode = document.getElementById('sum-paid-mode');
      if (elMode) elMode.innerText = (mode === 'DUO' ? 'DUO' : (mode === 'TIM' || mode === 'TRIO' ? `TIM (${count} CHAR)` : '1v1'));

      document.getElementById('sum-paid-tier').innerText = paidSelectedTier;
      document.getElementById('sum-paid-name').innerText = nameVal;
      document.getElementById('sum-paid-char').innerText = charString;
      document.getElementById('sum-paid-ulti').innerText = ultiString;
      document.getElementById('sum-paid-matches').innerText = `${paidMatchCount} Match`;

      updatePaidPriceDisplay();
    }

    function setGiftStatus(status) {
      paidGiftSent = (status === 'sudah');
      const bSudah = document.getElementById('btn-gift-ans-sudah');
      const bBelum = document.getElementById('btn-gift-ans-belum');

      if (bSudah) {
        bSudah.style.background = paidGiftSent ? '#22c55e' : 'rgba(34, 197, 94, 0.25)';
        bSudah.style.color = paidGiftSent ? '#000000' : '#86efac';
      }
      if (bBelum) {
        bBelum.style.background = !paidGiftSent ? '#ef4444' : 'rgba(239, 68, 68, 0.2)';
        bBelum.style.color = !paidGiftSent ? '#ffffff' : '#fca5a5';
      }
    }

    // Submit pendaftaran VIP & SROBOT
    async function submitPaidRegistration() {
      const name = (document.getElementById('paid-name-input').value || '').trim();
      if (!name) {
        alert('Nama TikTok tidak boleh kosong!');
        goToPaidStep(1);
        return;
      }

      const count = getPaidActiveSlotCount();
      for (let i = 0; i < count; i++) {
        if (!paidSlots[i].char) {
          alert(`Karakter untuk Slot ${i + 1} (${getPaidSlotLabel(i)}) belum dipilih!`);
          goToPaidStep(2);
          selectPaidSlot(i);
          return;
        }
        if (typeof isCharBanned === 'function' && isCharBanned(paidSlots[i].char)) {
          showBannedAlert(paidSlots[i].char.name);
          goToPaidStep(2);
          selectPaidSlot(i);
          return;
        }
      }

      // Validasi anti-duplikat karakter dalam 1 tim (semua versi)
      const activeModeCheck = (state.masterMode || '1v1').toUpperCase();
      if (activeModeCheck === 'DUO' || activeModeCheck === 'TIM' || activeModeCheck === 'TRIO') {
        for (let i = 0; i < count; i++) {
          for (let j = i + 1; j < count; j++) {
            if (typeof isSameBaseCharacter === 'function' && isSameBaseCharacter(paidSlots[i].char, paidSlots[j].char)) {
              alert(`❌ PENDAFTARAN DITOLAK!\n\nSlot ${i + 1} (${paidSlots[i].char.name}) dan Slot ${j + 1} (${paidSlots[j].char.name}) menggunakan karakter yang sama (${getCharBaseName(paidSlots[i].char)}).\n\nMode TIM/DUO tidak mengizinkan karakter yang sama (semua versi).`);
              goToPaidStep(2);
              return;
            }
          }
        }
      }

      // 1. Anti double check
      const dup = checkDuplicatePlayer(name, state);
      if (dup) {
        alert(`❌ PENDAFTARAN DITOLAK!\n\n${dup.message}.`);
        return;
      }

      // Cek apakah juga sudah ada di list pending approvals
      if (Array.isArray(state.pendingApprovals)) {
        const normName = name.toLowerCase();
        const pendingFound = state.pendingApprovals.find(p => (p.name || '').trim().toLowerCase() === normName);
        if (pendingFound) {
          alert(`❌ PENDAFTARAN DITOLAK!\n\nNama "${name}" sudah terdaftar dan sedang menunggu persetujuan Admin.`);
          return;
        }
      }

      const activeMode = state.masterMode || '1v1';
      const activeSlots = paidSlots.slice(0, count);
      const fullChar = activeSlots.map(s => s.char.name).join(' / ');
      const fullUlti = activeSlots.map(s => s.ulti || s.char.name).join(' / ');

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
        id: 'pnd_' + Date.now().toString(),
        name,
        char: fullChar,
        ulti: fullUlti,
        matchesLeft: paidMatchCount,
        ws: 0,
        type: paidSelectedTier,
        ticketMode: activeMode,
        registeredAt: Date.now(),
        status: 'PENDING',
        giftConfirmed: paidGiftSent
      };

      if (!Array.isArray(state.pendingApprovals)) state.pendingApprovals = [];
      state.pendingApprovals.push(newPlayer);

      if (!Array.isArray(state.registeredUsers)) state.registeredUsers = [];
      if (!state.registeredUsers.includes(name)) state.registeredUsers.push(name);

      saveAndBroadcastState();

      closePaidModal();

      // Show success modal
      document.getElementById('suc-p-name').innerText = name;
      document.getElementById('suc-p-tier').innerText = paidSelectedTier;
      const elSucMode = document.getElementById('suc-p-mode');
      if (elSucMode) elSucMode.innerText = (activeMode === 'DUO' ? 'DUO' : (activeMode === 'TIM' || activeMode === 'TRIO' ? `TIM (${count} CHAR)` : '1v1'));
      document.getElementById('suc-p-char').innerText = fullChar;
      document.getElementById('suc-p-ulti').innerText = fullUlti;
      document.getElementById('suc-p-match').innerText = `${paidMatchCount} Match`;
      document.getElementById('modal-paid-success').style.display = 'flex';
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
          kothSyncChannel = new BroadcastChannel('koth_broadcast_' + streamKey);
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

      // Auto open if parameter URL passed
      const urlTier = <?= json_encode($urlTier) ?>;
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('tier') || urlTier) {
        const t = (urlParams.get('tier') || urlTier || 'VIP').toUpperCase();
        openPaidModal(t);
      }
    });
  </script>
</body>
</html>
