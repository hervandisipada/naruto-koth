<?php
declare(strict_types=1);
require_once __DIR__ . '/api/helpers.php';

$roomKey = resolveRoomKey();
$isAdmin = isAdminAuthenticated($roomKey);
$initialStateJson = '{}';
try {
  $pdo = getDbConnection();
  $dbData = loadStateFromMySql($pdo, $roomKey);
  $initialStateJson = json_encode($dbData['state'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  $initialStateJson = json_encode(getDefaultState(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/admin.php';
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Naruto Storm Connections - KotH Queue & Overlay System</title>
  <!-- MQTT.js for Realtime Cloud Sync across HP & TikTok Live Studio -->
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <script>
    // ==========================================
    // PENDING APPROVAL MANAGEMENT (FREE & SROBOT)
    // ==========================================
    function saveAndBroadcastState() {
      if (typeof saveState === 'function') {
        saveState();
      }
    }
    // Hitung Biaya Gift Sesuai Mode & Tier
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

    function renderPendingApprovals() {
      const container = document.getElementById('pending-list-container');
      const countEl = document.getElementById('pending-count');
      const pendingList = Array.isArray(state.pendingApprovals) ? state.pendingApprovals : [];

      if (countEl) countEl.innerText = `${pendingList.length}`;
      if (!container) return;
      container.innerHTML = '';

      if (pendingList.length === 0) {
        container.innerHTML = `
          <div style="background: rgba(255,255,255,0.03); border: 1px dashed var(--border-color); border-radius: 8px; padding: 24px; text-align: center; color: var(--text-muted);">
            <div style="font-size: 1.5rem; margin-bottom: 6px;">🎉</div>
            <div style="font-weight: 800; font-size: 0.95rem; color: var(--c-white);">Tidak Ada Pendaftar Menunggu Persetujuan</div>
            <div style="font-size: 0.78rem; margin-top: 4px;">Pendaftar baru Tier VIP & SROBOT akan muncul di sini untuk ditinjau (Tier FREE otomatis masuk antrian).</div>
          </div>
        `;
        return;
      }

      pendingList.forEach((p, idx) => {
        const item = document.createElement('div');
        const tierCls = (p.type === 'SROBOT' || p.type === 'SB') ? 'fast' : (p.type === 'VIP' ? 'vip' : 'free');
        const tierLabel = (p.type === 'SROBOT' || p.type === 'SB') ? 'SROBOT' : (p.type === 'VIP' ? 'VIP' : 'FREE');
        const mode = p.ticketMode || '1v1';
        const matches = p.matchesLeft || 1;

        let priceHtml = '';
        if (tierLabel === 'VIP' || tierLabel === 'SROBOT') {
          const pr = calculateTierPrice(tierLabel, mode, matches);
          priceHtml = `
            <div style="margin-top: 6px; display: inline-flex; align-items: center; gap: 8px; background: rgba(234, 179, 8, 0.12); border: 1px solid rgba(234, 179, 8, 0.4); border-radius: 4px; padding: 3px 8px; font-size: 0.75rem; font-weight: 800;">
              <span style="color: #facc15;">🪙 ${pr.totalCoins} Koin TikTok</span>
              <span style="color: var(--text-muted);">atau</span>
              <span style="color: #4ade80;">💳 Rp ${pr.totalRpFormatted} (SociaBuzz)</span>
            </div>
          `;
        } else {
          priceHtml = `
            <div style="margin-top: 6px; display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 4px; padding: 3px 8px; font-size: 0.73rem; font-weight: 800; color: #86efac;">
              <span>Gratis (1 Match)</span>
            </div>
          `;
        }

        let timeStr = '';
        if (p.registeredAt) {
          const d = new Date(p.registeredAt);
          timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        const borderCol = tierCls === 'fast' ? '#a855f7' : (tierCls === 'vip' ? '#FFD700' : 'var(--c-slate)');

        item.style.cssText = `
          background: rgba(255, 255, 255, 0.04);
          border: 1px solid var(--border-color);
          border-left: 4px solid ${borderCol};
          border-radius: 8px;
          padding: 10px 14px;
          margin-bottom: 8px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          flex-wrap: wrap;
          gap: 10px;
        `;

        item.innerHTML = `
          <div style="flex: 1; min-width: 220px;">
            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px; flex-wrap: wrap;">
              <span class="q-badge ${tierCls}" style="font-size: 0.70rem; font-weight: 900; padding: 1px 6px; border-radius: 3px;">${tierLabel}</span>
              <span class="badge" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); font-size: 0.68rem; padding: 1px 5px; border-radius: 3px; color: var(--c-light); font-weight: 800;">${mode}</span>
              <strong style="font-size: 1.05rem; color: var(--c-white); font-weight: 900;">${p.name || '-'}</strong>
              ${timeStr ? `<span style="font-size: 0.70rem; color: var(--text-muted); opacity: 0.7;">(${timeStr})</span>` : ''}
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); font-weight: 700;">
              🎮 Karakter: <span style="color: var(--c-white); font-weight: 800;">${p.char || '-'}</span>
              ${p.ulti && p.ulti !== '-' ? `<span style="color: #facc15; font-size: 0.75rem; margin-left: 6px;">⚡ ${p.ulti}</span>` : ''}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
              🎟️ Match: <b style="color: var(--c-white);">${matches} Match</b>
            </div>
            ${priceHtml}
          </div>
          <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="openPendingNotifModalForPlayer('${p.id}')" title="Buka Modal Tinjauan / Konfirmasi" style="font-weight: 800; font-size: 0.78rem; padding: 6px 10px; background: #393E46; color:#EEEEEE; border-color:#929AAB;">
              🔍 Tinjau
            </button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="openEditPendingModal('${p.id}')" title="Edit Detail Pendaftar" style="font-weight: 800; font-size: 0.78rem; padding: 6px 12px; background: #374151; color:#ffffff; border-color:#4b5563;">
              ✏️ Edit
            </button>
            <button type="button" class="btn btn-sm btn-primary" onclick="approvePendingPlayer('${p.id}')" title="Setujui ke Antrian Live" style="font-weight: 900; font-size: 0.78rem; padding: 6px 14px; background: #22c55e; border-color: #22c55e; color:#ffffff;">
              ✅ Setujui
            </button>
            <button type="button" class="btn btn-sm btn-danger" onclick="rejectPendingPlayer('${p.id}')" title="Tolak Pendaftaran" style="font-weight: 800; font-size: 0.78rem; padding: 6px 12px;">
              ❌ Tolak
            </button>
          </div>
        `;
        container.appendChild(item);
      });
    }

    function approvePendingPlayer(id) {
      if (!Array.isArray(state.pendingApprovals)) return;
      const idx = state.pendingApprovals.findIndex(p => p.id === id);
      if (idx === -1) {
        showToast('Pendaftar tidak ditemukan.');
        return;
      }
      const player = state.pendingApprovals.splice(idx, 1)[0];
      player.status = 'APPROVED';
      if (!Array.isArray(state.queue)) state.queue = [];
      state.queue.push(player);
      sortQueue();
      saveAndBroadcastState();
      renderAll();
      showToast(`✅ ${player.name} (${player.type}) telah disetujui & masuk antrian live!`);
    }

    function rejectPendingPlayer(id, skipConfirm = false) {
      if (!Array.isArray(state.pendingApprovals)) return;
      const idx = state.pendingApprovals.findIndex(p => p.id === id);
      if (idx === -1) return;
      const player = state.pendingApprovals[idx];
      if (!skipConfirm && !confirm(`Tolak pendaftaran ${player.name} (${player.type})?`)) return;
      state.pendingApprovals.splice(idx, 1);
      saveAndBroadcastState();
      renderAll();
      showToast(`Pendaftaran ${player.name} telah ditolak.`);
    }

    function approveAllPending() {
      if (!Array.isArray(state.pendingApprovals) || state.pendingApprovals.length === 0) {
        showToast('Tidak ada pendaftar yang menunggu persetujuan.');
        return;
      }
      if (!confirm(`Setujui semua (${state.pendingApprovals.length}) pendaftar sekaligus ke antrian live?`)) return;
      if (!Array.isArray(state.queue)) state.queue = [];
      state.pendingApprovals.forEach(p => {
        p.status = 'APPROVED';
        state.queue.push(p);
      });
      state.pendingApprovals = [];
      sortQueue();
      saveAndBroadcastState();
      renderAll();
      showToast('✅ Semua pendaftar berhasil disetujui ke antrian live!');
    }

    // =======================================================
    // NOTIFIKASI REAL-TIME MODAL PENDAFTAR VIP & SROBOT BARU
    // =======================================================
    let isPendingNotifInitialized = false;
    let knownPendingIds = new Set();
    let pendingNotifQueue = [];
    let currentNotifPlayer = null;

    function playNotificationSound() {
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;

        // Ding-dong chord (D5 - 587.33Hz -> A5 - 880Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now);
        gain1.gain.setValueAtTime(0.2, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);

        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880, now + 0.15);
        gain2.gain.setValueAtTime(0.25, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.15);
        osc2.stop(now + 0.6);
      } catch (e) { }
    }

    function checkNewPendingApprovals() {
      if (!Array.isArray(state.pendingApprovals)) return;

      // Pada inisialisasi awal saat halaman admin baru dimuat/refresh,
      // rekam semua id pendaftar yang sudah ada agar tidak memicu popup massal.
      if (!isPendingNotifInitialized) {
        state.pendingApprovals.forEach(p => {
          if (p && p.id) knownPendingIds.add(p.id);
        });
        isPendingNotifInitialized = true;
        return;
      }

      // Deteksi pendaftar baru yang belum ada di knownPendingIds
      const newPlayers = state.pendingApprovals.filter(p => p && p.id && !knownPendingIds.has(p.id));
      if (newPlayers.length === 0) return;

      newPlayers.forEach(p => {
        knownPendingIds.add(p.id);
        if (!pendingNotifQueue.some(item => item.id === p.id)) {
          pendingNotifQueue.push(p);
        }
      });

      if (pendingNotifQueue.length > 0) {
        playNotificationSound();
        showNextPendingNotif();
      }
    }

    function showNextPendingNotif() {
      const modal = document.getElementById('modal-new-pending-notif');
      if (!modal) return;

      // Filter queue: pastikan pemain masih ada di state.pendingApprovals
      pendingNotifQueue = pendingNotifQueue.filter(p =>
        Array.isArray(state.pendingApprovals) && state.pendingApprovals.some(item => item.id === p.id)
      );

      if (pendingNotifQueue.length === 0) {
        modal.style.display = 'none';
        currentNotifPlayer = null;
        return;
      }

      currentNotifPlayer = pendingNotifQueue[0];
      const p = currentNotifPlayer;
      const tierCls = (p.type === 'SROBOT' || p.type === 'SB') ? 'fast' : (p.type === 'VIP' ? 'vip' : 'free');
      const tierLabel = (p.type === 'SROBOT' || p.type === 'SB') ? 'SROBOT' : (p.type === 'VIP' ? 'VIP' : 'FREE');
      const mode = p.ticketMode || '1v1';
      const matches = p.matchesLeft || 1;

      const nameEl = document.getElementById('notif-player-name');
      const tierEl = document.getElementById('notif-player-tier');
      const modeEl = document.getElementById('notif-player-mode');
      const charEl = document.getElementById('notif-player-char');
      const ultiEl = document.getElementById('notif-player-ulti');
      const matchesEl = document.getElementById('notif-player-matches');
      const priceEl = document.getElementById('notif-player-price');
      const counterEl = document.getElementById('notif-pending-counter');
      const cardBox = document.getElementById('notif-card-box');

      if (nameEl) nameEl.innerText = p.name || '-';
      if (tierEl) {
        tierEl.className = 'q-badge ' + tierCls;
        tierEl.innerText = tierLabel;
      }
      if (modeEl) modeEl.innerText = mode;
      if (charEl) charEl.innerText = p.char || '-';
      if (ultiEl) {
        if (p.ulti && p.ulti !== '-') {
          ultiEl.style.display = 'block';
          ultiEl.innerText = `⚡ Jurus: ${p.ulti}`;
        } else {
          ultiEl.style.display = 'none';
        }
      }
      if (matchesEl) matchesEl.innerText = `${matches} Match`;

      if (counterEl) {
        if (pendingNotifQueue.length > 1) {
          counterEl.style.display = 'inline-block';
          counterEl.innerText = `${pendingNotifQueue.length} Pendaftar Baru`;
        } else {
          counterEl.style.display = 'none';
        }
      }

      if (priceEl) {
        const pr = calculateTierPrice(tierLabel, mode, matches);
        if (pr.isPaid) {
          priceEl.innerHTML = `
            <div style="display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap; background:rgba(234,179,8,0.12); border:1px solid rgba(234,179,8,0.4); border-radius:6px; padding:6px 12px; font-size:0.80rem; font-weight:800;">
              <span style="color:#facc15;">🪙 ${pr.totalCoins} Koin TikTok</span>
              <span style="color:#929AAB;">atau</span>
              <span style="color:#4ade80;">💳 Rp ${pr.totalRpFormatted} (SociaBuzz)</span>
            </div>
          `;
        } else {
          priceEl.innerHTML = `
            <div style="display:inline-block; background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.3); border-radius:6px; padding:5px 10px; font-size:0.78rem; font-weight:800; color:#86efac;">
              Gratis (1 Match)
            </div>
          `;
        }
      }

      if (cardBox) {
        const borderCol = (tierLabel === 'VIP') ? '#FFD700' : ((tierLabel === 'SROBOT') ? '#a855f7' : '#929AAB');
        cardBox.style.borderColor = borderCol;
        cardBox.style.boxShadow = `0 0 20px ${borderCol}33`;
      }

      modal.style.display = 'flex';
    }

    function approveCurrentNotifPlayer() {
      if (!currentNotifPlayer) return;
      const id = currentNotifPlayer.id;
      pendingNotifQueue = pendingNotifQueue.filter(p => p.id !== id);
      approvePendingPlayer(id);
      showNextPendingNotif();
    }

    function rejectCurrentNotifPlayer() {
      if (!currentNotifPlayer) return;
      const p = currentNotifPlayer;
      const id = p.id;
      if (!confirm(`Tolak pendaftaran ${p.name} (${p.type})?`)) return;
      pendingNotifQueue = pendingNotifQueue.filter(item => item.id !== id);
      rejectPendingPlayer(id, true);
      showNextPendingNotif();
    }

    function closeNewPendingNotifModal() {
      const modal = document.getElementById('modal-new-pending-notif');
      if (modal) modal.style.display = 'none';
      pendingNotifQueue = [];
      currentNotifPlayer = null;
      showToast('Notifikasi ditutup. Pendaftar tetap tersimpan di Daftar Tunggu.');
    }

    function editCurrentNotifPlayer() {
      if (!currentNotifPlayer) return;
      const id = currentNotifPlayer.id;
      const modal = document.getElementById('modal-new-pending-notif');
      if (modal) modal.style.display = 'none';
      pendingNotifQueue = pendingNotifQueue.filter(p => p.id !== id);
      currentNotifPlayer = null;
      openEditPendingModal(id);
    }

    function openPendingNotifModalForPlayer(id) {
      if (!Array.isArray(state.pendingApprovals)) return;
      const p = state.pendingApprovals.find(item => item.id === id);
      if (!p) return;
      pendingNotifQueue = [p];
      showNextPendingNotif();
    }

    function testPendingNotifModal() {
      let testP = (Array.isArray(state.pendingApprovals) && state.pendingApprovals.length > 0)
        ? state.pendingApprovals[0]
        : {
            id: 'demo_pnd_' + Date.now(),
            name: 'Pemain_VIP_Demo',
            char: 'Naruto (Kurama Link Mode)',
            ulti: 'Bijudama Rasenshuriken',
            matchesLeft: 3,
            type: 'VIP',
            ticketMode: state.masterMode || '1v1',
            registeredAt: Date.now()
          };
      pendingNotifQueue = [testP];
      playNotificationSound();
      showNextPendingNotif();
    }

    let currentEditingPendingId = null;

    function openEditPendingModal(id) {
      if (!Array.isArray(state.pendingApprovals)) return;
      const player = state.pendingApprovals.find(p => p.id === id);
      if (!player) return;

      currentEditingPendingId = id;
      document.getElementById('edit-pending-name').value = player.name || '';
      document.getElementById('edit-pending-char').value = player.char || '';
      document.getElementById('edit-pending-ulti').value = player.ulti || '';
      document.getElementById('edit-pending-matches').value = player.matchesLeft || 1;
      document.getElementById('edit-pending-tier').value = (player.type === 'SROBOT' || player.type === 'SB') ? 'SROBOT' : (player.type === 'VIP' ? 'VIP' : 'FREE');
      document.getElementById('edit-pending-mode').value = player.ticketMode || '1v1';

      document.getElementById('modal-edit-pending').style.display = 'flex';
    }

    function closeEditPendingModal() {
      document.getElementById('modal-edit-pending').style.display = 'none';
      currentEditingPendingId = null;
    }

    function saveEditPendingPlayer(andApprove = false) {
      if (!currentEditingPendingId || !Array.isArray(state.pendingApprovals)) return;
      const player = state.pendingApprovals.find(p => p.id === currentEditingPendingId);
      if (!player) return;

      const newName = document.getElementById('edit-pending-name').value.trim();
      const newChar = document.getElementById('edit-pending-char').value.trim();
      const newUlti = document.getElementById('edit-pending-ulti').value.trim();
      const newMatches = parseInt(document.getElementById('edit-pending-matches').value) || 1;
      const newTier = document.getElementById('edit-pending-tier').value;
      const newMode = document.getElementById('edit-pending-mode').value;

      if (!newName) {
        alert('Nama tidak boleh kosong!');
        return;
      }
      if (!newChar) {
        alert('Karakter tidak boleh kosong!');
        return;
      }

      player.name = newName;
      player.char = newChar;
      player.ulti = newUlti;
      player.matchesLeft = newMatches;
      player.type = newTier;
      player.ticketMode = newMode;

      if (andApprove) {
        const idx = state.pendingApprovals.findIndex(p => p.id === currentEditingPendingId);
        if (idx !== -1) {
          state.pendingApprovals.splice(idx, 1);
        }
        player.status = 'APPROVED';
        if (!Array.isArray(state.queue)) state.queue = [];
        state.queue.push(player);
        sortQueue();
        showToast(`✅ ${player.name} berhasil diperbarui & disetujui ke antrian live!`);
      } else {
        showToast(`✅ Perubahan data ${player.name} tersimpan!`);
      }

      closeEditPendingModal();
      saveAndBroadcastState();
      renderAll();
    }

  </script>
  <style>
    :root {
      /* User's Exact 4-Color Palette: #F7F7F7, #EEEEEE, #393E46, #929AAB */
      --c-white: #F7F7F7;
      --c-light: #EEEEEE;
      --c-dark: #393E46;
      --c-slate: #929AAB;

      /* Semantic tokens for Light Theme (Default) */
      --bg-dark: #F7F7F7;
      --panel-bg: #FFFFFF;
      --panel-subtle: #EEEEEE;
      --border-color: #E2E6EC;
      --border-strong: #929AAB;
      --text-light: #393E46;
      --text-muted: #929AAB;
      --header-bg: #393E46;

      /* Button & Accent tokens */
      --accent-orange: #393E46;
      --accent-gold: #393E46;
      --accent-blue: #929AAB;
      --accent-green: #393E46;
      --accent-red: #596170;
    }

    [data-theme="dark"] {
      --bg-dark: #272B30;
      --panel-bg: #393E46;
      --panel-subtle: #2F343C;
      --border-color: #4C5462;
      --border-strong: #929AAB;
      --text-light: #F7F7F7;
      --text-muted: #929AAB;
      --header-bg: #1E2125;

      --accent-orange: #EEEEEE;
      --accent-gold: #F7F7F7;
      --accent-blue: #929AAB;
      --accent-green: #EEEEEE;
      --accent-red: #929AAB;
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
      background-color: var(--bg-dark);
      color: var(--text-light);
      min-height: 100vh;
      font-size: 15px;
      font-family: 'Consolas', 'Courier New', Courier, monospace;
      transition: background-color 0.2s ease, color 0.2s ease;
    }

    /* Tab Switcher */
    .view-switcher {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      background-color: var(--c-dark);
      border-bottom: 2px solid var(--c-slate);
      position: sticky;
      top: 0;
      z-index: 100;
      padding: 0 4px;
    }

    /* Undo & Redo Action Buttons */
    .btn-undo-action,
    .btn-redo-action {
      background: rgba(255, 255, 255, 0.12);
      color: var(--c-white);
      border: 1px solid var(--c-slate);
      font-size: 0.85rem;
      font-weight: 800;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.15s ease;
      white-space: nowrap;
      user-select: none;
    }

    .btn-undo-action:not(:disabled),
    .btn-redo-action:not(:disabled) {
      background: var(--c-blue);
      border-color: #2563eb;
      color: #ffffff;
      box-shadow: 0 2px 6px rgba(59, 130, 246, 0.35);
    }

    .btn-undo-action:not(:disabled):hover,
    .btn-redo-action:not(:disabled):hover {
      background: #1d4ed8;
      border-color: #1d4ed8;
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(59, 130, 246, 0.5);
    }

    .btn-undo-action:disabled,
    .btn-redo-action:disabled {
      opacity: 0.38;
      cursor: not-allowed;
      background: rgba(255, 255, 255, 0.05);
      border-color: rgba(255, 255, 255, 0.1);
      box-shadow: none;
      transform: none;
    }

    [data-theme="light"] .card-title .btn-undo-action:disabled,
    [data-theme="light"] .card-title .btn-redo-action:disabled {
      background: rgba(0, 0, 0, 0.05);
      color: var(--text-muted);
      border-color: var(--border-color);
    }

    .tab-btn {
      flex: 1;
      padding: 13px;
      border: none;
      background: transparent;
      color: var(--c-slate);
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
      text-align: center;
    }

    .tab-btn.active {
      color: var(--c-white);
      background-color: rgba(255, 255, 255, 0.08);
      border-bottom: 3px solid var(--c-white);
    }

    .view-container {
      display: none;
      padding: 18px;
    }

    .view-container.active {
      display: block;
    }

    /* Layout Admin Panel */
    .admin-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
      max-width: 1200px;
      margin: 0 auto;
    }

    @media (min-width: 900px) {
      .admin-grid {
        grid-template-columns: 1fr 1fr;
      }

      .full-width {
        grid-column: span 2;
      }
    }

    .card {
      background-color: var(--panel-bg);
      border-radius: 10px;
      padding: 18px;
      border: 1px solid var(--border-color);
      box-shadow: 0 2px 8px rgba(57, 62, 70, 0.06);
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 800;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      color: var(--text-light);
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 8px;
      letter-spacing: 0.3px;
    }

    /* Form Inputs */
    .form-group {
      margin-bottom: 12px;
    }

    .form-group label {
      display: block;
      font-size: 0.92rem;
      color: var(--text-muted);
      margin-bottom: 4px;
      font-weight: 700;
    }

    .form-control {
      width: 100%;
      padding: 9px 12px;
      border-radius: 6px;
      border: 1px solid var(--border-strong);
      background-color: var(--panel-bg);
      color: var(--text-light);
      font-size: 1rem;
      transition: border-color 0.2s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--c-dark);
      box-shadow: 0 0 0 2px rgba(57, 62, 70, 0.15);
    }

    .form-row {
      display: flex;
      gap: 10px;
    }

    .btn {
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 0.95rem;
    }

    .btn-primary {
      background-color: var(--c-dark);
      color: var(--c-white);
    }

    .btn-primary:hover {
      opacity: 0.92;
    }

    .btn-success {
      background-color: var(--c-dark);
      color: var(--c-white);
    }

    .btn-success:hover {
      opacity: 0.92;
    }

    .btn-danger {
      background-color: var(--c-light);
      color: var(--c-dark);
      border: 1px solid var(--c-slate);
    }

    .btn-danger:hover {
      background-color: var(--c-slate);
      color: var(--c-white);
    }

    .btn-blue {
      background-color: var(--c-slate);
      color: var(--c-white);
    }

    .btn-blue:hover {
      opacity: 0.92;
    }

    .btn-sm {
      padding: 5px 10px;
      font-size: 0.88rem;
    }

    .btn-block {
      width: 100%;
    }

    /* Match Arena View (Admin) */
    .arena-container {
      display: flex;
      flex-direction: column;
      gap: 15px;
      background: var(--panel-subtle);
      border: 2px solid var(--c-dark);
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 4px 16px rgba(57, 62, 70, 0.08);
    }

    @media (min-width: 600px) {
      .arena-container {
        flex-direction: row;
        align-items: center;
      }
    }

    .player-slot {
      flex: 1;
      background: var(--panel-bg);
      border-radius: 8px;
      padding: 14px;
      border: 1px solid var(--border-color);
      position: relative;
    }

    .player-slot.fast,
    .player-slot.vvip {
      border: 2px solid #a855f7;
      background: linear-gradient(135deg, rgba(147, 51, 234, 0.08) 0%, rgba(168, 85, 247, 0.03) 100%);
      box-shadow: 0 0 12px rgba(168, 85, 247, 0.15);
    }

    [data-theme="dark"] .player-slot.fast,
    [data-theme="dark"] .player-slot.vvip {
      border: 2px solid #c084fc;
      background: linear-gradient(135deg, rgba(147, 51, 234, 0.16) 0%, rgba(88, 28, 135, 0.2) 100%);
      box-shadow: 0 0 14px rgba(168, 85, 247, 0.25);
    }

    .player-slot.vip,
    .player-slot.srobot {
      border: 2px solid #FFD700;
      background: rgba(255, 215, 0, 0.04);
    }

    [data-theme="dark"] .player-slot.vip,
    [data-theme="dark"] .player-slot.srobot {
      border: 2px solid #FFD700;
      background: rgba(255, 215, 0, 0.06);
    }

    .player-slot.free {
      border: 2px solid var(--border-color);
    }

    .role-badge {
      font-size: 0.82rem;
      font-weight: 800;
      padding: 4px 9px;
      border-radius: 4px;
      text-transform: uppercase;
      display: inline-block;
      margin-bottom: 6px;
      letter-spacing: 0.5px;
      background-color: var(--c-slate);
      color: var(--c-white);
    }

    .role-badge.fast,
    .role-badge.badge-fast,
    .role-badge.vvip,
    .role-badge.badge-vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 50%, #9333ea 100%);
      color: #ffffff;
      font-weight: 900;
      border: 1.5px solid #d8b4fe;
      letter-spacing: 0.8px;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.45), inset 0 1px 1px rgba(255, 255, 255, 0.4);
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
    }

    [data-theme="dark"] .role-badge.fast,
    [data-theme="dark"] .role-badge.badge-fast,
    [data-theme="dark"] .role-badge.vvip,
    [data-theme="dark"] .role-badge.badge-vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 50%, #a855f7 100%);
      color: #ffffff;
      font-weight: 900;
      border: 1.5px solid #d8b4fe;
      letter-spacing: 0.8px;
      box-shadow: 0 0 12px rgba(168, 85, 247, 0.55), inset 0 1px 1px rgba(255, 255, 255, 0.5);
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7);
    }

    .role-badge.vip,
    .role-badge.badge-vip,
    .role-badge.srobot,
    .role-badge.badge-srobot {
      background-color: #1e242b;
      color: #FFD700;
      font-weight: 900;
      border: 1px solid #FFD700;
      letter-spacing: 0.8px;
    }

    [data-theme="dark"] .role-badge.vip,
    [data-theme="dark"] .role-badge.badge-vip,
    [data-theme="dark"] .role-badge.srobot,
    [data-theme="dark"] .role-badge.badge-srobot {
      background-color: rgba(255, 215, 0, 0.16);
      color: #FFD700;
      border: 1px solid #FFD700;
      letter-spacing: 0.8px;
    }

    .role-badge.free,
    .role-badge.badge-free {
      background-color: var(--panel-subtle);
      color: var(--text-light);
      border: 1px solid var(--border-color);
      font-weight: 700;
    }

    /* DROPDOWN ICON BUTTONS (URUTAN # & ROLE 🏷️) */
    .dropdown-icon-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 38px;
    }

    .btn-select-face {
      font-size: 0.92rem;
      font-weight: 800;
      padding: 7px 12px;
      min-width: 48px;
      height: 38px;
      border-radius: 6px;
      background: var(--panel-subtle);
      color: var(--text-light);
      border: 1px solid var(--border-color);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      pointer-events: none;
      transition: all 0.2s ease;
      box-sizing: border-box;
      white-space: nowrap;
    }

    .dropdown-icon-btn:hover .btn-select-face,
    .dropdown-icon-btn:focus-within .btn-select-face {
      border-color: var(--border-strong);
      background: var(--border-color);
    }

    .dropdown-icon-btn select.icon-select-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
      z-index: 2;
      border: none;
      outline: none;
      margin: 0;
      padding: 0;
    }

    .dropdown-icon-btn select.icon-select-overlay option {
      background-color: var(--panel-bg);
      color: var(--text-light);
      padding: 8px 12px;
    }

    /* ARENA ACTION BUTTONS (WIN, CHAR, TURUN) */
    .arena-action-btn {
      height: 44px;
      padding: 8px 18px;
      font-size: 1rem;
      font-weight: 800;
      border-radius: 8px;
      min-width: 95px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
      transition: all 0.15s ease;
      cursor: pointer;
    }

    .arena-action-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
    }

    .player-name {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text-light);
      word-break: break-word;
    }

    .player-char {
      font-size: 1rem;
      color: var(--c-slate);
      margin-top: 2px;
      font-weight: 600;
    }

    .player-stats {
      margin-top: 10px;
      display: flex;
      gap: 12px;
      font-size: 0.92rem;
      color: var(--text-muted);
    }

    .stat-val {
      color: var(--text-light);
      font-weight: bold;
    }

    .vs-divider {
      font-size: 1.4rem;
      font-weight: 900;
      color: var(--c-slate);
      text-align: center;
      padding: 10px;
      letter-spacing: 1px;
    }

    /* Queue & Saved Lists */
    .list-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 12px;
      background-color: var(--panel-bg);
      border-radius: 6px;
      margin-bottom: 8px;
      border: 1px solid var(--border-color);
      border-left: 4px solid var(--c-slate);
    }

    .list-item.fast,
    .list-item.vvip {
      border-left-color: #a855f7;
      background: linear-gradient(90deg, rgba(147, 51, 234, 0.07) 0%, var(--panel-subtle) 100%);
    }

    .list-item.vip {
      border-left-color: #FFD700;
      background-color: var(--panel-subtle);
    }

    .list-item.free {
      border-left-color: var(--c-slate);
    }

    .list-item.save {
      border-left-color: var(--c-slate);
    }

    [data-theme="dark"] .list-item.fast,
    [data-theme="dark"] .list-item.vvip {
      border-left-color: #c084fc;
      background: linear-gradient(90deg, rgba(147, 51, 234, 0.15) 0%, rgba(20, 20, 30, 0.5) 100%);
    }

    [data-theme="dark"] .list-item.vip {
      border-left-color: #FFD700;
      background-color: rgba(255, 215, 0, 0.04);
    }

    .item-info {
      flex: 1;
    }

    .item-name {
      font-weight: 800;
      font-size: 1.05rem;
      color: var(--text-light);
    }

    .item-char {
      font-size: 0.88rem;
      color: var(--text-muted);
    }

    .item-meta {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 4px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .item-meta .btn {
      font-size: 0.92rem;
      font-weight: 700;
      padding: 7px 13px;
      min-width: 46px;
      height: 38px;
      box-sizing: border-box;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      border-radius: 6px;
      white-space: nowrap;
    }

    /* Clean & Detailed Queue Item Card */
    .queue-item-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 14px;
      background-color: var(--panel-bg);
      border-radius: 8px;
      margin-bottom: 8px;
      border: 1px solid var(--border-color);
      border-left: 5px solid var(--c-slate);
      transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .queue-item-card:hover {
      border-color: rgba(255, 255, 255, 0.22);
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
    }
    .queue-item-card.fast,
    .queue-item-card.vvip {
      border-left-color: #a855f7;
      background: linear-gradient(90deg, rgba(168, 85, 247, 0.09) 0%, var(--panel-subtle) 100%);
    }
    .queue-item-card.vip {
      border-left-color: #eab308;
      background: linear-gradient(90deg, rgba(234, 179, 8, 0.08) 0%, var(--panel-subtle) 100%);
    }
    .queue-item-card.free {
      border-left-color: #64748b;
      background-color: var(--panel-bg);
    }
    [data-theme="dark"] .queue-item-card.fast,
    [data-theme="dark"] .queue-item-card.vvip {
      border-left-color: #c084fc;
      background: linear-gradient(90deg, rgba(168, 85, 247, 0.16) 0%, rgba(20, 20, 30, 0.6) 100%);
    }
    [data-theme="dark"] .queue-item-card.vip {
      border-left-color: #FFD700;
      background: linear-gradient(90deg, rgba(255, 215, 0, 0.08) 0%, rgba(20, 20, 30, 0.6) 100%);
    }

    .queue-item-left {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      min-width: 0;
    }
    .queue-item-right {
      display: flex;
      align-items: center;
      flex-shrink: 0;
    }

    .queue-rank-badge {
      font-family: 'Consolas', 'Courier New', monospace;
      font-size: 0.92rem;
      font-weight: 900;
      color: var(--text-light);
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: 6px;
      padding: 5px 8px;
      min-width: 36px;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .queue-player-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .queue-player-header {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .queue-player-name {
      font-size: 1.02rem;
      font-weight: 800;
      color: var(--text-light);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 260px;
    }

    .queue-player-details {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    .queue-char-text {
      font-weight: 700;
      color: #f1f5f9;
      font-size: 0.84rem;
    }

    .queue-ulti-pill {
      font-size: 0.72rem;
      font-weight: 700;
      color: #facc15;
      background: rgba(234, 179, 8, 0.15);
      border: 1px solid rgba(234, 179, 8, 0.35);
      padding: 1px 7px;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      gap: 3px;
      max-width: 240px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .queue-match-pill {
      font-size: 0.75rem;
      font-weight: 800;
      color: #38bdf8;
      background: rgba(56, 189, 248, 0.12);
      border: 1px solid rgba(56, 189, 248, 0.3);
      padding: 1px 8px;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .btn-queue-dots {
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: var(--text-light);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
      flex-shrink: 0;
      user-select: none;
    }
    .btn-queue-dots:hover {
      background: rgba(56, 189, 248, 0.15);
      border-color: #38bdf8;
      color: #38bdf8;
      transform: scale(1.06);
      box-shadow: 0 0 10px rgba(56, 189, 248, 0.3);
    }

    .action-group-box {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 8px;
      padding: 10px 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .action-group-title {
      font-size: 0.72rem;
      font-weight: 900;
      color: #94a3b8;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 2px;
    }

    .badge {
      font-size: 0.76rem;
      padding: 2px 6px;
      border-radius: 4px;
      font-weight: bold;
    }

    .badge-fast,
    .badge-vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 50%, #9333ea 100%);
      color: #ffffff;
      font-weight: 900;
      border: 1.5px solid #d8b4fe;
      letter-spacing: 0.8px;
      box-shadow: 0 0 8px rgba(168, 85, 247, 0.45);
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.6);
    }

    [data-theme="dark"] .badge-fast,
    [data-theme="dark"] .badge-vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 50%, #a855f7 100%);
      color: #ffffff;
      font-weight: 900;
      border: 1.5px solid #d8b4fe;
      letter-spacing: 0.8px;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.55);
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7);
    }

    .badge-vip,
    .badge-srobot {
      background-color: #1e242b;
      color: #FFD700;
      font-weight: 900;
      border: 1px solid #FFD700;
      letter-spacing: 0.8px;
    }

    [data-theme="dark"] .badge-vip,
    [data-theme="dark"] .badge-srobot {
      background-color: rgba(255, 215, 0, 0.16);
      color: #FFD700;
      font-weight: 900;
      border: 1px solid #FFD700;
      letter-spacing: 0.8px;
    }

    .badge-free {
      background-color: var(--c-light);
      color: var(--c-dark);
      border: 1px solid var(--c-slate);
      font-weight: 700;
    }

    [data-theme="dark"] .badge-free {
      background-color: transparent;
      color: var(--c-slate);
      border: 1px solid var(--c-slate);
    }

    .badge-save {
      background-color: var(--c-light);
      color: var(--c-dark);
      border: 1px solid var(--c-slate);
    }

    /* Vertical Tier Sections */
    .tier-section {
      margin-bottom: 14px;
    }

    .tier-section.fast,
    .tier-section.vvip {
      margin-bottom: 22px;
      padding-bottom: 16px;
      border-bottom: 2px dashed rgba(168, 85, 247, 0.45);
    }

    [data-theme="dark"] .tier-section.fast,
    [data-theme="dark"] .tier-section.vvip {
      border-bottom: 2px dashed rgba(192, 132, 252, 0.4);
    }

    .tier-section.vip {
      margin-bottom: 22px;
      padding-bottom: 16px;
      border-bottom: 2px dashed rgba(212, 175, 55, 0.4);
    }

    [data-theme="dark"] .tier-section.vip {
      border-bottom: 2px dashed rgba(255, 215, 0, 0.35);
    }

    .tier-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 5px 10px;
      margin-bottom: 6px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 900;
      letter-spacing: 0.8px;
      text-transform: uppercase;
    }

    .tier-header.fast,
    .tier-header.vvip {
      background: linear-gradient(135deg, #581c87 0%, #7e22ce 100%);
      color: #ffffff;
      border-left: 3px solid #d8b4fe;
      box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.08);
    }

    .tier-header.fast span:first-child,
    .tier-header.fast .tier-title,
    .tier-header.vvip span:first-child,
    .tier-header.vvip .tier-title {
      color: #ffffff;
      font-weight: 900;
      letter-spacing: 1px;
      text-shadow: 0 0 8px rgba(216, 180, 254, 0.6);
    }

    [data-theme="dark"] .tier-header.fast,
    [data-theme="dark"] .tier-header.vvip {
      background: linear-gradient(135deg, #581c87 0%, #9333ea 100%);
      color: #ffffff;
      border-left: 3px solid #d8b4fe;
    }

    .tier-header.vip {
      background: #1e242b;
      color: #FFD700;
      border-left: 3px solid #FFD700;
    }

    .tier-header.vip span:first-child,
    .tier-header.vip .tier-title {
      color: #FFD700;
      font-weight: 900;
      letter-spacing: 1px;
    }

    [data-theme="dark"] .tier-header.vip {
      background: rgba(255, 215, 0, 0.14);
      color: #FFD700;
      border-left: 3px solid #FFD700;
    }

    .tier-header.free {
      background: var(--panel-subtle);
      color: var(--text-muted);
      border-left: 3px solid var(--border-color);
      font-weight: 700;
    }

    .tier-section.vipfree {
      margin-bottom: 22px;
      padding-bottom: 16px;
    }

    .tier-header.vipfree {
      background: linear-gradient(135deg, rgba(255, 215, 0, 0.16) 0%, rgba(56, 189, 248, 0.12) 100%);
      color: #FFD700;
      border-left: 3px solid #FFD700;
    }

    .tier-header.vipfree span:first-child,
    .tier-header.vipfree .tier-title {
      color: #FFD700;
      font-weight: 900;
      letter-spacing: 1px;
    }

    [data-theme="dark"] .tier-header.free {
      background: rgba(255, 255, 255, 0.04);
      color: var(--c-slate);
      border-left: 3px solid rgba(146, 154, 171, 0.3);
    }

    .tier-empty {
      font-size: 0.75rem;
      color: var(--text-muted);
      padding: 6px 10px;
      font-style: italic;
    }

    /* TIER STATUS (OPEN / CLOSED) */
    .tier-status-btn {
      padding: 2px 8px;
      font-size: 0.74rem;
      font-weight: 900;
      border-radius: 4px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
      letter-spacing: 0.5px;
      border: 1px solid transparent;
    }

    .tier-status-btn.open {
      background: rgba(34, 197, 94, 0.2);
      color: #22c55e;
      border-color: #22c55e;
    }

    .tier-status-btn.open:hover {
      background: #22c55e;
      color: #000;
    }

    .tier-status-btn.closed {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
      border-color: #ef4444;
    }

    .tier-status-btn.closed:hover {
      background: #ef4444;
      color: #fff;
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

    /* Overlay Mode Styles in Index Preview */
    #overlay-view {
      background-color: transparent;
      padding: 0;
    }

    .overlay-container {
      width: 420px;
      background: var(--c-dark);
      border-radius: 12px;
      padding: 14px;
      color: var(--c-white);
      border: 1px solid var(--c-slate);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      margin: 20px auto;
    }

    .overlay-header {
      text-align: center;
      font-size: 0.95rem;
      font-weight: 900;
      color: var(--c-white);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 10px;
      border-bottom: 1px solid var(--c-slate);
      padding-bottom: 6px;
    }

    .overlay-match {
      display: flex;
      flex-direction: column;
      gap: 8px;
      background: rgba(0, 0, 0, 0.25);
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 12px;
      border: 1px solid rgba(146, 154, 171, 0.2);
    }

    .overlay-player {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 6px 10px;
      border-radius: 6px;
    }

    .overlay-player.p1 {
      background: rgba(255, 255, 255, 0.06);
      border-left: 3px solid var(--c-white);
    }

    .overlay-player.p2 {
      background: rgba(255, 255, 255, 0.04);
      border-left: 3px solid var(--c-slate);
    }

    .overlay-pname {
      font-weight: 800;
      font-size: 1.05rem;
      color: var(--c-white);
    }

    .overlay-pchar {
      font-size: 0.8rem;
      color: var(--c-slate);
    }

    .overlay-ws {
      font-weight: 900;
      color: var(--c-white);
      font-size: 0.9rem;
    }

    .free-streak-badge {
      font-size: 0.65rem;
      background: var(--c-light);
      color: var(--c-dark);
      padding: 2px 4px;
      border-radius: 3px;
      margin-left: 4px;
      font-weight: bold;
    }

    .overlay-queue-title {
      font-size: 0.78rem;
      color: var(--c-slate);
      font-weight: 800;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .overlay-qitem {
      display: flex;
      justify-content: space-between;
      padding: 6px 8px;
      font-size: 0.85rem;
      border-bottom: 1px solid rgba(146, 154, 171, 0.15);
      color: var(--c-white);
    }

    /* Modal Dialog */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(57, 62, 70, 0.7);
      backdrop-filter: blur(4px);
      z-index: 999;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal-box {
      background: var(--panel-bg);
      border-radius: 10px;
      padding: 20px;
      max-width: 400px;
      width: 100%;
      border: 2px solid var(--c-dark);
      color: var(--text-light);
    }

    .instructions {
      font-size: 0.85rem;
      color: var(--text-muted);
      line-height: 1.4;
      margin-top: 10px;
    }

    /* BANNER FREE TUTUP DIBAWAH LIST ANTRIAN */
    .free-closed-banner {
      margin-top: 8px;
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
      0%,
      100% {
        transform: scale(1);
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.6);
      }
      50% {
        transform: scale(1.02);
        box-shadow: 0 6px 24px rgba(239, 68, 68, 0.9);
      }
    }

    /* Searchable Character Picker & Autocomplete */
    .char-picker-container {
      position: relative;
      width: 100%;
    }
    .char-picker-popup {
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      z-index: 10000;
      background: #0f172a;
      border: 1px solid #3b82f6;
      border-radius: 8px;
      box-shadow: 0 14px 35px rgba(0, 0, 0, 0.85);
      max-height: 290px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }
    .char-picker-sticky-header {
      position: sticky;
      top: 0;
      background: #1e293b;
      border-bottom: 1px solid #334155;
      padding: 6px 8px;
      z-index: 2;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .char-slide-tabs {
      display: flex;
      gap: 3px;
      flex-wrap: wrap;
    }
    .char-slide-tab-btn {
      background: #0f172a;
      border: 1px solid #475569;
      color: #cbd5e1;
      font-size: 0.68rem;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 4px;
      cursor: pointer;
      transition: all 0.15s;
    }
    .char-slide-tab-btn:hover,
    .char-slide-tab-btn.active {
      background: #3b82f6;
      color: #ffffff;
      border-color: #60a5fa;
    }
    .char-slide-group-header {
      background: rgba(30, 41, 59, 0.95);
      color: #facc15;
      font-size: 0.70rem;
      font-weight: 800;
      padding: 4px 10px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    .char-picker-item {
      padding: 7px 10px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      cursor: pointer;
      transition: background 0.12s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .char-picker-item:hover,
    .char-picker-item.highlighted {
      background: #1e3a8a;
    }
    .char-item-title {
      font-size: 0.82rem;
      font-weight: 700;
      color: #f8fafc;
      line-height: 1.25;
    }
    .char-item-tags {
      display: flex;
      gap: 4px;
      align-items: center;
      flex-shrink: 0;
      margin-left: 8px;
    }
    .badge-slide {
      background: #334155;
      color: #94a3b8;
      font-size: 0.65rem;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 3px;
    }
    .badge-multi-ulti {
      background: rgba(234, 179, 8, 0.2);
      color: #fef08a;
      border: 1px solid rgba(234, 179, 8, 0.4);
      font-size: 0.65rem;
      font-weight: 700;
      padding: 1px 5px;
      border-radius: 3px;
    }
    .badge-single-ulti {
      background: rgba(100, 116, 139, 0.15);
      color: #64748b;
      font-size: 0.65rem;
      font-weight: 600;
      padding: 1px 5px;
      border-radius: 3px;
    }


    /* PIN LOCK MODAL CSS */
    #admin-pin-lock-modal {
      position: fixed;
      inset: 0;
      z-index: 9999999;
      background: radial-gradient(circle at center, #1e293b 0%, #090d16 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      font-family: 'Outfit', sans-serif;
    }
    .pin-card {
      background: rgba(15, 23, 42, 0.95);
      border: 2px solid #f59e0b;
      box-shadow: 0 0 50px rgba(245, 158, 11, 0.25), 0 20px 40px rgba(0, 0, 0, 0.8);
      border-radius: 16px;
      padding: 32px 24px;
      width: 100%;
      max-width: 380px;
      text-align: center;
      backdrop-filter: blur(12px);
      color: #fff;
    }
    .pin-box {
      width: 52px;
      height: 58px;
      background: rgba(0, 0, 0, 0.4);
      border: 2px solid #334155;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 900;
      color: #f59e0b;
      transition: all 0.2s;
    }
    .pin-key-btn {
      background: rgba(30, 41, 59, 0.85);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 10px;
      color: #fff;
      font-size: 1.35rem;
      font-weight: 800;
      padding: 12px 0;
      cursor: pointer;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    .pin-key-btn:hover {
      background: rgba(245, 158, 11, 0.25);
      border-color: #f59e0b;
      color: #f59e0b;
      transform: translateY(-2px);
    }
    .pin-key-btn:active {
      transform: translateY(1px);
    }
    @keyframes pinShake {
      0%,
      100% {
        transform: translateX(0);
      }
      20%,
      60% {
        transform: translateX(-8px);
      }
      40%,
      80% {
        transform: translateX(8px);
      }
    }
    @keyframes notifPopIn {
      from {
        opacity: 0;
        transform: scale(0.92) translateY(-12px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }
    .admin-pin-input-field {
      width: 100%;
      max-width: 220px;
      font-size: 2rem;
      text-align: center;
      letter-spacing: 12px;
      padding: 10px 14px;
      background: #222831;
      border: 2px solid #F7F7F7;
      border-radius: 8px;
      color: #F7F7F7;
      font-family: inherit;
      font-weight: 900;
      outline: none;
      box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.5);
    }

    /* =================================================== */
    /* ADMIN STEPPED REGISTRATION MODAL (ALUR PENDAFTARAN) */
    /* =================================================== */
    .admin-reg-steps {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      margin-bottom: 12px;
      background: rgba(0, 0, 0, 0.4);
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid var(--border-color);
      overflow-x: auto;
    }
    .admin-reg-step-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.74rem;
      font-weight: 700;
      color: var(--text-muted);
      white-space: nowrap;
      padding: 4px 8px;
      border-radius: 6px;
      transition: all 0.15s ease;
    }
    .admin-reg-step-item.active {
      background: rgba(234, 179, 8, 0.18);
      color: #facc15;
      font-weight: 800;
    }
    .admin-reg-step-item.done {
      color: #4ade80;
    }
    .admin-reg-step-num {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.68rem;
    }
    .admin-reg-step-item.active .admin-reg-step-num {
      background: #facc15;
      color: #000;
      font-weight: 900;
    }
    .admin-reg-step-item.done .admin-reg-step-num {
      background: #4ade80;
      color: #000;
      font-weight: 900;
    }
    .admin-tier-btn {
      flex: 1;
      padding: 10px 8px;
      border-radius: 8px;
      border: 1.5px solid rgba(255, 255, 255, 0.15);
      background: rgba(0, 0, 0, 0.35);
      color: var(--text-muted);
      font-weight: 800;
      font-size: 0.82rem;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      transition: all 0.15s ease;
      user-select: none;
    }
    .admin-tier-btn:hover {
      border-color: rgba(255, 255, 255, 0.35);
      color: #fff;
    }
    .admin-tier-btn.active.tier-free {
      background: rgba(34, 197, 94, 0.2);
      border-color: #22c55e;
      color: #4ade80;
      box-shadow: 0 0 10px rgba(34, 197, 94, 0.25);
    }
    .admin-tier-btn.active.tier-vip {
      background: rgba(234, 179, 8, 0.2);
      border-color: #eab308;
      color: #fde047;
      box-shadow: 0 0 10px rgba(234, 179, 8, 0.25);
    }
    .admin-tier-btn.active.tier-srobot {
      background: rgba(168, 85, 247, 0.2);
      border-color: #a855f7;
      color: #d8b4fe;
      box-shadow: 0 0 10px rgba(168, 85, 247, 0.25);
    }
    .admin-char-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: 8px;
      max-height: 250px;
      overflow-y: auto;
      padding: 4px 2px;
    }
    .admin-char-card {
      background: rgba(0, 0, 0, 0.35);
      border: 1.5px solid rgba(255, 255, 255, 0.12);
      border-radius: 8px;
      padding: 8px 10px;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 4px;
      transition: all 0.15s ease;
      user-select: none;
    }
    .admin-char-card:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.3);
      transform: translateY(-1px);
    }
    .admin-char-card.selected {
      background: rgba(234, 179, 8, 0.2);
      border-color: #facc15;
      box-shadow: 0 0 10px rgba(250, 204, 21, 0.35);
    }
    .admin-char-card.disabled {
      cursor: not-allowed;
      opacity: 0.6;
    }
    .admin-char-card.disabled:hover {
      transform: none;
      box-shadow: none;
    }
    .admin-char-card.banned {
      border-color: rgba(239, 68, 68, 0.45);
      background: rgba(239, 68, 68, 0.08);
      filter: grayscale(60%);
    }
    .admin-char-card.banned:hover {
      border-color: #ef4444;
      background: rgba(239, 68, 68, 0.16);
    }
    .admin-char-card.vip-only {
      border-color: rgba(245, 158, 11, 0.45);
      background: rgba(245, 158, 11, 0.08);
    }
    .admin-char-card.vip-only:hover {
      border-color: #f59e0b;
      background: rgba(245, 158, 11, 0.16);
    }
    .admin-char-card.duplicate-team {
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
    .admin-ulti-card {
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
    .admin-ulti-card:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(255, 255, 255, 0.3);
    }
    .admin-ulti-card.selected {
      background: rgba(234, 179, 8, 0.18);
      border-color: #facc15;
      box-shadow: 0 0 8px rgba(250, 204, 21, 0.25);
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
    .btn-match-count {
      flex: 1;
      min-width: 60px;
      background: rgba(255, 255, 255, 0.08);
      border: 1.5px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      padding: 8px 6px;
      border-radius: 8px;
      font-weight: 800;
      font-size: 0.82rem;
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
  </style>
</head>

<body>
  <?php
  $sidebarActive = 'admin';
  include __DIR__ . '/includes/sidebar.php';
  ?>

  <!-- PIN LOCK SCREEN FOR ADMIN (Terhubung ke MySQL api/auth.php) -->
  <div id="admin-pin-lock-modal"
    style="position: fixed; inset: 0; z-index: 99999999; background: rgba(34, 40, 49, 0.98); display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Consolas', 'Courier New', monospace;">
    <div
      style="background: #393E46; border: 2px solid #F7F7F7; box-shadow: 0 10px 40px rgba(0,0,0,0.85); border-radius: 12px; padding: 30px 24px; width: 100%; max-width: 380px; text-align: center; color: #F7F7F7;">
      <div
        style="width: 58px; height: 58px; margin: 0 auto 12px; background: rgba(247, 247, 247, 0.1); border: 2px solid #F7F7F7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px;">
        🔒
      </div>
      <h2 style="margin: 0 0 6px; font-size: 1.3rem; color: #F7F7F7; letter-spacing: 1px; font-weight: 900;">
        PANEL ADMIN TERKUNCI
      </h2>
      <p style="margin: 0 0 18px; font-size: 0.85rem; color: #929AAB; line-height: 1.4;">
        Masukkan 4 digit PIN keamanan:
      </p>

      <!-- Input PIN Langsung (Keyboard PC & HP Otomatis Muncul) -->
      <div style="margin-bottom: 14px;">
        <input type="password" id="admin-pin-direct-input" class="admin-pin-input-field" maxlength="4"
          autocomplete="off" autofocus placeholder="****" oninput="handleDirectPinInput(this.value)"
          onkeydown="handlePinKey(event)" />
      </div>

      <div id="pin-error-msg"
        style="min-height: 22px; font-size: 0.82rem; font-weight: 800; color: #ef4444; margin-bottom: 14px;"></div>

      <!-- Numeric Keypad Virtual -->
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px;">
        <button type="button" class="pin-key-btn" onclick="pressPinKey('1')">1</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('2')">2</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('3')">3</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('4')">4</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('5')">5</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('6')">6</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('7')">7</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('8')">8</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('9')">9</button>
        <button type="button" class="pin-key-btn"
          style="background: rgba(239, 68, 68, 0.25); border-color: #ef4444; color: #fca5a5;"
          onclick="pressPinClear()">C</button>
        <button type="button" class="pin-key-btn" onclick="pressPinKey('0')">0</button>
        <button type="button" class="pin-key-btn"
          style="background: rgba(34, 197, 94, 0.25); border-color: #22c55e; color: #86efac;"
          onclick="submitPin()">✓</button>
      </div>

      <div style="border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; margin-top: 4px;">
        <a href="index.php"
          style="color: #EEEEEE; text-decoration: none; font-size: 0.85rem; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
          ← Kembali ke Halaman Pendaftaran
        </a>
      </div>
    </div>
  </div>

  <script>
    // Standalone PIN Security Script (Terhubung ke MySQL via api/auth.php)
    const APP_BASE_URL = <?= json_encode($appBaseUrl) ?>;

    function getAppBaseUrl() {
      if (typeof APP_BASE_URL === 'string' && APP_BASE_URL.startsWith('/')) {
        return `${window.location.origin}${APP_BASE_URL}`;
      }
      let p = window.location.pathname.replace(/\/(index|admin|overlay)(\.(html|php))?$/i, '/').replace(/\/admin\/?$/i, '/');
      if (!p.endsWith('/')) p += '/';
      return `${window.location.origin}${p}`;
    }

    function getAuthRoomKey() {
      try {
        const params = new URLSearchParams(window.location.search);
        return params.get('room') || params.get('key') || localStorage.getItem('koth_stream_key') || 'hervandisipada';
      } catch (e) {
        return 'hervandisipada';
      }
    }

    function getAuthApiUrl(query = '') {
      const room = encodeURIComponent(getAuthRoomKey());
      return `${getAppBaseUrl()}api/auth.php?room=${room}${query ? '&' + query : ''}`;
    }

    function setPinModalVisible(visible) {
      const modal = document.getElementById('admin-pin-lock-modal');
      const input = document.getElementById('admin-pin-direct-input');
      if (!modal) return;
      if (visible) {
        modal.style.display = 'flex';
        if (input) {
          input.value = '';
          setTimeout(() => input.focus(), 100);
        }
      } else {
        modal.style.display = 'none';
      }
    }

    const isServerAuthenticated = <?= $isAdmin ? 'true' : 'false' ?>;

    async function checkAdminAuth() {
      if (isServerAuthenticated) {
        setPinModalVisible(false);
      }
      if (!window.location.protocol.startsWith('http')) {
        setPinModalVisible(true);
        const errMsg = document.getElementById('pin-error-msg');
        if (errMsg) {
          errMsg.style.color = '#eab308';
          errMsg.innerText = '⚠️ Buka lewat XAMPP: http://localhost/naruto-koth/admin';
        }
        return;
      }

      try {
        const res = await fetch(getAuthApiUrl('action=status'), {
          credentials: 'same-origin',
          cache: 'no-store'
        });
        const data = await res.json();
        setPinModalVisible(!(res.ok && data && data.authenticated));
      } catch (e) {
        setPinModalVisible(true);
      }
    }

    function handlePinKey(e) {
      if (e && (e.key === 'Enter' || e.keyCode === 13)) {
        submitPin();
      }
    }

    function handleDirectPinInput(val) {
      const cleaned = val.replace(/[^0-9]/g, '');
      const input = document.getElementById('admin-pin-direct-input');
      if (input && input.value !== cleaned) input.value = cleaned;
      const errMsg = document.getElementById('pin-error-msg');
      if (errMsg) errMsg.innerText = '';

      if (cleaned.length === 4) {
        setTimeout(submitPin, 120);
      }
    }

    function pressPinKey(num) {
      const input = document.getElementById('admin-pin-direct-input');
      if (!input) return;
      if (input.value.length < 4) {
        input.value += num;
        handleDirectPinInput(input.value);
      }
    }

    function pressPinClear() {
      const input = document.getElementById('admin-pin-direct-input');
      if (input) {
        input.value = '';
        input.focus();
      }
      const errMsg = document.getElementById('pin-error-msg');
      if (errMsg) errMsg.innerText = '';
    }

    let isSubmittingPin = false;
    async function submitPin() {
      if (isSubmittingPin) return;
      const input = document.getElementById('admin-pin-direct-input');
      const entered = input ? input.value.trim() : '';
      const errMsg = document.getElementById('pin-error-msg');

      if (!window.location.protocol.startsWith('http')) {
        if (errMsg) {
          errMsg.style.color = '#ef4444';
          errMsg.innerText = '❌ Jalankan lewat XAMPP Apache (http://localhost/naruto-koth/admin).';
        }
        return;
      }

      if (!entered) {
        if (errMsg) {
          errMsg.style.color = '#ef4444';
          errMsg.innerText = '❌ Masukkan 4 digit PIN terlebih dahulu.';
        }
        return;
      }

      isSubmittingPin = true;
      try {
        const res = await fetch(getAuthApiUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            action: 'login',
            room: getAuthRoomKey(),
            pin: entered
          })
        });
        const data = await res.json();

        if (res.ok && data && data.authenticated) {
          if (errMsg) {
            errMsg.style.color = '#86efac';
            errMsg.innerText = '✅ Akses Diterima! Membuka panel...';
          }
          setTimeout(() => setPinModalVisible(false), 180);
        } else {
          if (errMsg) {
            errMsg.style.color = '#ef4444';
            errMsg.innerText = '❌ ' + ((data && data.error) || 'PIN SALAH! Akses ditolak.');
          }
          if (input) {
            input.style.borderColor = '#ef4444';
            setTimeout(() => {
              input.value = '';
              input.style.borderColor = '#F7F7F7';
              input.focus();
            }, 600);
          }
        }
      } catch (e) {
        if (errMsg) {
          errMsg.style.color = '#ef4444';
          errMsg.innerText = '❌ Gagal terhubung ke server MySQL (api/auth.php).';
        }
      } finally {
        isSubmittingPin = false;
      }
    }

    async function changeAdminPin() {
      const oldPin = prompt('Masukkan PIN Admin saat ini (4 digit angka):');
      if (oldPin === null) return;
      const newPin = prompt('Masukkan PIN Admin baru (4 digit angka):');
      if (newPin === null) return;
      if (!/^\d{4}$/.test(newPin.trim())) {
        alert('❌ PIN baru harus tepat 4 digit angka!');
        return;
      }
      const confirmPin = prompt('Ulangi PIN Admin baru (4 digit angka):');
      if (confirmPin === null) return;
      if (newPin.trim() !== confirmPin.trim()) {
        alert('❌ Konfirmasi PIN baru tidak cocok!');
        return;
      }

      try {
        const res = await fetch(getAuthApiUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            action: 'change_pin',
            room: getAuthRoomKey(),
            old_pin: oldPin.trim(),
            new_pin: newPin.trim()
          })
        });
        const data = await res.json();
        if (res.ok && data && data.status === 'ok') {
          alert('✅ PIN Admin berhasil diperbarui di database MySQL!');
        } else {
          alert('❌ ' + ((data && data.error) || 'Gagal mengubah PIN Admin.'));
        }
      } catch (e) {
        alert('❌ Gagal menghubungi server api/auth.php.');
      }
    }

    async function logoutAdmin() {
      try {
        await fetch(getAuthApiUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            action: 'logout',
            room: getAuthRoomKey()
          })
        });
      } catch (e) { }
      setPinModalVisible(true);
    }

    // Auto check auth saat script dimuat
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', checkAdminAuth);
    } else {
      checkAdminAuth();
    }
  </script>

  <!-- TOP HEADER BAR (SAMA PERSIS DARI HALAMAN PENDAFTARAN) -->
  <div style="padding: 10px 14px 4px 14px;">
    <?php
    $headerSubtitle = 'ADMIN';
    include __DIR__ . '/includes/header.php';
    ?>
  </div>

  <!-- Admin Action Bar -->
  <div class="view-switcher" style="padding: 6px 14px; gap: 8px; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      <button class="btn-undo-action" onclick="undo()" title="Undo perubahan terakhir (Ctrl+Z)" disabled>↩ Undo</button>
      <button class="btn-redo-action" onclick="redo()" title="Redo perubahan (Ctrl+Y)" disabled>↪ Redo</button>
      <button id="theme-toggle-btn" onclick="toggleTheme()" class="btn btn-sm"
        style="margin: 0 4px; background: rgba(255,255,255,0.12); color: var(--c-white); border: 1px solid var(--c-slate); font-size: 0.78rem;"
        title="Ganti mode tema">Tema: Terang</button>
    </div>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      <button type="button" class="tab-btn"
        style="background: rgba(234, 179, 8, 0.18); color: #fde047; border: 1px solid rgba(234, 179, 8, 0.4);"
        onclick="changeAdminPin()" title="Ubah PIN Admin di database MySQL">🔑 Ubah PIN</button>
      <button type="button" class="tab-btn"
        style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4);"
        onclick="logoutAdmin()" title="Kunci panel admin dengan PIN">🔒 Kunci</button>
    </div>
  </div>

  <!-- VIEW 1: ADMIN PANEL -->
  <div id="admin-view" class="view-container active">
    <div class="admin-grid">

      <!-- MASTER MODE CONTROLLER (1v1 / DUO / TIM) -->
      <div class="card full-width"
        style="padding: 10px 14px; margin-bottom: 4px; background: var(--panel-bg); border: 2px solid var(--border-color); border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-weight: 900; font-size: 0.95rem; color: var(--accent-gold); letter-spacing: 0.5px;">🎮
                MASTER MODE:</span>
              <span id="master-mode-status-badge" class="badge"
                style="font-weight: 800; font-size: 0.85rem; padding: 4px 12px; border-radius: 4px; background: #0284c7; color: #fff;">1v1
                (1 CHAR)</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;">
              Pilih format mode pertandingan live. Arena pemain, format form karakter & Top WS otomatis beralih terpisah
              per mode.
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" id="btn-master-1v1" class="btn btn-primary"
              style="font-weight: 800; padding: 6px 14px; font-size: 0.85rem;" onclick="setMasterMode('1v1')">
              ⚡ 1v1 (1 Char)
            </button>
            <button type="button" id="btn-master-duo" class="btn btn-blue"
              style="font-weight: 800; padding: 6px 14px; font-size: 0.85rem; opacity: 0.7;"
              onclick="setMasterMode('DUO')">
              ⚔️ DUO (2 Char)
            </button>
            <button type="button" id="btn-master-tim" class="btn btn-blue"
              style="font-weight: 800; padding: 6px 14px; font-size: 0.85rem; opacity: 0.7;"
              onclick="setMasterMode('TIM')">
              👑 TIM (2-3 Char)
            </button>
          </div>
        </div>
      </div>

      <!-- ARENA MATCH CONTROLLER -->
      <div class="card full-width">
        <div class="card-title">
          <span>ARENA (<span id="arena-current-mode-label">1v1</span>)</span>
          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <button class="btn-undo-action" onclick="undo()" title="Undo perubahan terakhir (Ctrl+Z)" disabled>↩
              Undo</button>
            <button class="btn-redo-action" onclick="redo()" title="Redo perubahan (Ctrl+Y)" disabled>↪ Redo</button>
            <button class="btn btn-sm btn-danger" onclick="resetArena()">Reset Arena</button>
          </div>
        </div>

        <div class="arena-container">
          <!-- PLAYER 1 SLOT -->
          <div class="player-slot king" id="p1-slot-card">
            <span class="role-badge" id="p1-role-badge">P1</span>
            <div id="p1-name" class="player-name">Kosong</div>
            <div id="p1-char" class="player-char">-</div>
            <div id="p1-ulti-row"
              style="display:none; font-size:0.75rem; color:var(--accent-gold); font-weight:700; margin-top:2px;">
              <span>⚡ </span><span id="p1-ulti">-</span>
            </div>
            <div class="player-stats">
              <span>WS: <span id="p1-ws" class="stat-val">0</span> <span id="p1-bonus-badge" class="badge"
                  style="display:none; background:var(--c-dark); color:var(--c-white); font-size:0.72rem; border:1px solid var(--c-slate);">+1
                  Match</span></span>
              <span style="cursor:pointer;" onclick="promptEditTickets('p1')" title="Klik untuk edit match">Match: <span
                  id="p1-matches" class="stat-val">0</span></span>
            </div>
            <div id="p1-btn-group" style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
              <button class="btn btn-success arena-action-btn" onclick="triggerWin('p1')" title="Pemenang P1">🏆
                MENANG</button>
              <button class="btn btn-blue arena-action-btn" onclick="promptEditChar('p1')" title="Ubah Karakter">🥋
                CHAR</button>
              <button class="btn btn-danger arena-action-btn" id="p1-demote-btn" onclick="removeSlotToQueue('p1')"
                title="Turunkan ke antrean (WS & Karakter tetap terbawa)">⬇ TURUN</button>
            </div>
            <div id="p1-empty-notice"
              style="display:none; margin-top: 10px; font-size: 0.88rem; color: var(--text-muted); background: var(--panel-subtle); padding: 8px; border-radius: 6px; border: 1px solid var(--border-color);">
              <div id="p1-empty-text">Slot P1 Kosong.</div>
              <button id="p1-quick-btn" class="btn btn-sm btn-success btn-block" style="margin-top: 6px;"
                onclick="quickAssignSlot('p1')">⬆ #1 ke P1</button>
            </div>
          </div>

          <div class="vs-divider"
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
            <span>VS</span>
            <button type="button" class="btn btn-blue" onclick="swapArenaSlots()" title="Tukar Posisi P1 dan P2"
              style="font-size: 0.82rem; font-weight: 800; padding: 6px 12px; border-radius: 6px; white-space: nowrap;">
              ⇄ TUKAR (P1 ↔ P2)
            </button>
          </div>

          <!-- PLAYER 2 SLOT -->
          <div class="player-slot challenger" id="p2-slot-card">
            <span class="role-badge" id="p2-role-badge">P2</span>
            <div id="p2-name" class="player-name">Kosong</div>
            <div id="p2-char" class="player-char">-</div>
            <div id="p2-ulti-row"
              style="display:none; font-size:0.75rem; color:var(--accent-gold); font-weight:700; margin-top:2px;">
              <span>⚡ </span><span id="p2-ulti">-</span>
            </div>
            <div class="player-stats">
              <span>WS: <span id="p2-ws" class="stat-val">0</span> <span id="p2-bonus-badge" class="badge"
                  style="display:none; background:var(--c-dark); color:var(--c-white); font-size:0.75rem; border:1px solid var(--c-slate);">+1
                  Match</span></span>
              <span style="cursor:pointer;" onclick="promptEditTickets('p2')" title="Klik untuk edit match">Match: <span
                  id="p2-matches" class="stat-val">0</span></span>
            </div>
            <div id="p2-btn-group" style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
              <button class="btn btn-success arena-action-btn" onclick="triggerWin('p2')" title="Pemenang P2">🏆
                MENANG</button>
              <button class="btn btn-blue arena-action-btn" onclick="promptEditChar('p2')" title="Ubah Karakter">🥋
                CHAR</button>
              <button class="btn btn-danger arena-action-btn" id="p2-demote-btn" onclick="removeSlotToQueue('p2')"
                title="Turunkan ke antrean (WS & Karakter tetap terbawa)">⬇ TURUN</button>
            </div>
            <div id="p2-empty-notice"
              style="display:none; margin-top: 10px; font-size: 0.88rem; color: var(--text-muted); background: var(--panel-subtle); padding: 8px; border-radius: 6px; border: 1px solid var(--border-color);">
              <div id="p2-empty-text">Slot P2 Kosong.</div>
              <button id="p2-quick-btn" class="btn btn-sm btn-success btn-block" style="margin-top: 6px;"
                onclick="quickAssignSlot('p2')">⬆ #1 ke P2</button>
            </div>
          </div>
        </div>

        <!-- TOMBOL TAMBAH PEMAIN BARU DI BAWAH ARENA -->
        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border-color);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="font-size: 0.8rem; font-weight: 800; color: var(--accent-gold); text-transform: uppercase; letter-spacing: 0.5px;">➕ Pendaftaran Pemain (Sistem Modal):</span>
          </div>
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
            <button type="button" class="btn btn-success" onclick="openAdminRegModal('FREE')"
              style="font-size: 0.84rem; font-weight: 800; padding: 10px 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
              <span style="font-size: 1.1rem;">🟢</span>
              <span>DAFTAR FREE</span>
            </button>
            <button type="button" class="btn btn-warning" onclick="openAdminRegModal('VIP')"
              style="font-size: 0.84rem; font-weight: 800; padding: 10px 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border-radius: 8px; cursor: pointer; color: #000; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);">
              <span style="font-size: 1.1rem;">🟡</span>
              <span>DAFTAR VIP</span>
            </button>
            <button type="button" class="btn btn-purple" onclick="openAdminRegModal('SROBOT')"
              style="font-size: 0.84rem; font-weight: 800; padding: 10px 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.25);">
              <span style="font-size: 1.1rem;">🟣</span>
              <span>DAFTAR SROBOT</span>
            </button>
          </div>
        </div>
      </div>

      <!-- KELOLA MATCH QUEUE & LIST TABUNGAN -->
      <div class="card full-width">
        <!-- Search bar untuk cek slot penonton -->
        <div
          style="background: var(--panel-subtle); padding: 10px; border-radius: 8px; margin-bottom: 12px; border: 1px solid var(--border-color);">
          <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
            <label style="font-weight: 700; font-size: 0.88rem; color: var(--text-light);">Cari Pemain:</label>
            <span style="font-size: 0.75rem; color: var(--text-muted);">Cek posisi match queue & sisa match</span>
          </div>
          <div style="display:flex; gap: 8px;">
            <input type="text" id="search-player-input" class="form-control" placeholder="Ketik nama..."
              oninput="handleSearchPlayer()">
            <button class="btn btn-sm btn-blue"
              onclick="document.getElementById('search-player-input').value=''; handleSearchPlayer();">Reset</button>
          </div>
          <div id="search-result-box"
            style="display:none; margin-top: 8px; padding: 10px; border-radius: 6px; font-size: 0.88rem; line-height: 1.5;">
          </div>
        </div>

        <!-- Ceklis Pilihan Tampilan Daftar di Overlay (Centang Salah Satu) -->
        <div
          style="background: var(--panel-subtle); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 12px;">
          <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
            <span style="font-size: 0.8rem; font-weight: 800; color: var(--accent-gold);">📺 TAMPILKAN DAFTAR DI
              OVERLAY:</span>
            <span style="font-size: 0.72rem; color: var(--text-muted);">(Hanya bisa centang salah satu)</span>
          </div>
          <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <label
              style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin: 0; color: #fff;">
              <input type="checkbox" id="chk-overlay-queue" onchange="handleOverlayListCheck('queue', this.checked)">
              <span>Match Queue (Antrean)</span>
            </label>
            <label
              style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin: 0; color: #fff;">
              <input type="checkbox" id="chk-overlay-saved" onchange="handleOverlayListCheck('saved', this.checked)">
              <span>List Tabungan (Saved)</span>
            </label>
          </div>
        </div>

        <!-- Sub-Tabs Switcher -->
        <div
          style="display: flex; gap: 8px; margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px;">
          <button id="subtab-queue-btn" class="btn btn-sm btn-primary" onclick="switchListTab('queue')">
            Match Queue (<span id="queue-count">0</span>)
          </button>
          <button id="subtab-pending-btn" class="btn btn-sm btn-danger" onclick="switchListTab('pending')"
            style="background: var(--c-light); color: var(--c-dark);">
            ⏳ Persetujuan (<span id="pending-count">0</span>)
          </button>
          <button id="subtab-saved-btn" class="btn btn-sm btn-danger" onclick="switchListTab('saved')"
            style="background: var(--c-light); color: var(--c-dark);">
            List Tabungan (<span id="save-count">0</span>)
          </button>
        </div>

        <!-- TAB 1: MATCH QUEUE AKTIF LIVE -->
        <div id="subtab-queue-content">
          <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
            <span style="font-size: 0.82rem; color: var(--text-muted);">Pemain dalam match queue:</span>
            <button class="btn btn-sm btn-danger" onclick="clearQueue()">Reset Match Queue</button>
          </div>
          <div id="queue-list-container"></div>
        </div>

        <!-- TAB 2: LIST TABUNGAN -->
        <div id="subtab-saved-content" style="display: none;">
          <!-- TABS KHUSUS LIST TABUNGAN: 1vs1 vs TIM -->
          <div
            style="display: flex; gap: 8px; margin-bottom: 12px; background: var(--panel-subtle); padding: 6px; border-radius: 8px; border: 1px solid var(--border-color); flex-wrap: wrap;">
            <button type="button" id="tab-saved-1v1" class="btn btn-sm btn-primary"
              style="flex: 1; min-width: 140px; font-weight: 800; font-size: 0.88rem; padding: 8px 12px;"
              onclick="switchSavedModeTab('1v1')">
              ⚡ Tabungan 1vs1 (<span id="saved-count-1v1">0</span>)
            </button>
            <button type="button" id="tab-saved-tim" class="btn btn-sm btn-blue"
              style="flex: 1; min-width: 140px; font-weight: 800; font-size: 0.88rem; padding: 8px 12px; opacity: 0.7;"
              onclick="switchSavedModeTab('TIM')">
              👑 Tabungan TIM (<span id="saved-count-tim">0</span>)
            </button>
            <button type="button" id="tab-saved-all" class="btn btn-sm btn-blue"
              style="font-weight: 800; font-size: 0.82rem; padding: 8px 14px; opacity: 0.7;"
              onclick="switchSavedModeTab('ALL')">
              Semua (<span id="saved-count-all">0</span>)
            </button>
          </div>

          <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
            <span style="font-size: 0.82rem; color: var(--text-muted);" id="saved-mode-header-label">Pemain di Tabungan
              1vs1:</span>
            <button class="btn btn-sm btn-danger" onclick="clearSaved()">Reset Tabungan</button>
          </div>
          <div id="saved-list-container"></div>
        </div>

        <!-- TAB 3: MENUNGGU PERSETUJUAN (VIP & SROBOT) -->
        <div id="subtab-pending-content" style="display: none;">
          <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div>
              <span style="font-size: 0.88rem; font-weight: 800; color: #fde047;">⏳ Pendaftar Menunggu Persetujuan (VIP & SROBOT):</span>
              <span style="font-size: 0.78rem; color: var(--text-muted); display: block;">Pendaftar Tier VIP & SROBOT
                harus disetujui Admin sebelum tampil di antrian live overlay. Modal konfirmasi akan otomatis muncul setiap kali ada pendaftar baru.</span>
            </div>
            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
              <button type="button" class="btn btn-sm btn-secondary" onclick="testPendingNotifModal()"
                style="background: #393E46; border: 1px solid #929AAB; color: #EEEEEE; font-weight: 800;" title="Test Tampilan Popup Notifikasi Modal">🔔 Test Notif Modal</button>
              <button class="btn btn-sm btn-primary" onclick="approveAllPending()"
                style="background:#22c55e; border-color:#22c55e; font-weight:800;">⚡ Setujui Semua</button>
            </div>
          </div>
          <div id="pending-list-container"></div>
        </div>
      </div>

      <!-- PENGUMUMAN / NOTE RUNNING TEXT OVERLAY -->
      <div class="card">
        <div class="card-title">
          <span>NOTE / Running Text Overlay</span>
        </div>
        <p class="instructions" style="margin-bottom: 8px;">Teks pengumuman berjalan di bar atas overlay live (Label
          "NOTE:").</p>
        <div class="form-group" style="display: flex; gap: 8px;">
          <input type="text" id="input-announcement" class="form-control" placeholder="Tulis pengumuman NOTE di sini..."
            onkeydown="if(event.key==='Enter') saveAnnouncement()">
          <button class="btn btn-primary" onclick="saveAnnouncement()"
            style="white-space: nowrap; padding: 0 16px; font-weight: 800;">Simpan</button>
          <button class="btn btn-danger" onclick="clearAnnouncement()"
            style="white-space: nowrap; padding: 0 12px; font-weight: 800;"
            title="Kosongkan & Sembunyikan NOTE">Hapus</button>
        </div>
      </div>

      <!-- TOP WS (TOP 3) -->
      <div class="card">
        <div class="card-title"
          style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
          <span>TOP 3 WS</span>
          <span id="admin-top-ws-badge" class="badge"
            style="background: var(--c-white); color: var(--c-dark); font-weight: 800; padding: 2px 8px; border-radius: 4px;">[1v1]
            🥇 0 WS | 🥈 0 WS | 🥉 0 WS</span>
        </div>
        <p class="instructions" style="margin-bottom: 8px;">Daftar 3 Rekor WS tertinggi di live overlay khusus
          masing-masing mode (1v1, DUO, TIM).</p>

        <!-- Ceklis Pilihan Tampil di Overlay -->
        <div
          style="background: var(--panel-subtle); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 10px;">
          <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
            <span style="font-size: 0.8rem; font-weight: 800; color: var(--accent-gold);">📺 TAMPILKAN DI
              OVERLAY:</span>
            <span style="font-size: 0.72rem; color: var(--text-muted);">(Ceklis 1, 2, atau semuanya)</span>
          </div>
          <div style="display: flex; gap: 16px; flex-wrap: wrap;">
            <label
              style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin: 0; color: #fff;">
              <input type="checkbox" id="chk-topws-1v1" onchange="toggleTopWsOverlay('1v1', this.checked)">
              <span>TOP WS 1v1</span>
            </label>
            <label
              style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin: 0; color: #fff;">
              <input type="checkbox" id="chk-topws-duo" onchange="toggleTopWsOverlay('DUO', this.checked)">
              <span>TOP WS DUO</span>
            </label>
            <label
              style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; margin: 0; color: #fff;">
              <input type="checkbox" id="chk-topws-tim" onchange="toggleTopWsOverlay('TIM', this.checked)">
              <span>TOP WS TIM</span>
            </label>
          </div>
        </div>

        <!-- Tab Pilihan Mode Editor Top WS: 1v1 | DUO | TIM -->
        <div
          style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; background: var(--panel-subtle); padding: 6px 8px; border-radius: 6px; border: 1px solid var(--border-color); flex-wrap: wrap; gap: 6px;">
          <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-light);">Edit Rekor Mode:</span>
          <div style="display: flex; gap: 4px;">
            <button type="button" id="btn-topws-tab-1v1" class="btn btn-sm btn-primary"
              style="font-weight: 800; padding: 3px 10px; font-size: 0.78rem;" onclick="switchTopWsTab('1v1')">⚡
              1v1</button>
            <button type="button" id="btn-topws-tab-duo" class="btn btn-sm btn-blue"
              style="font-weight: 800; padding: 3px 10px; font-size: 0.78rem; opacity: 0.7;"
              onclick="switchTopWsTab('DUO')">⚔️ DUO</button>
            <button type="button" id="btn-topws-tab-tim" class="btn btn-sm btn-blue"
              style="font-weight: 800; padding: 3px 10px; font-size: 0.78rem; opacity: 0.7;"
              onclick="switchTopWsTab('TIM')">👑 TIM</button>
          </div>
        </div>

        <!-- TOP 1 -->
        <div class="top-record-row"
          style="background: rgba(255,215,0,0.08); border: 1px solid rgba(255,215,0,0.35); border-radius: 6px; padding: 6px 8px; margin-bottom: 6px;">
          <div style="font-weight: 800; font-size: 0.8rem; color: #ffd700; margin-bottom: 4px;">🥇 TOP 1 (JUARA 1)</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 6px;">
            <input type="text" id="input-record-name-1" class="form-control" placeholder="Nama juara 1...">
            <input type="text" id="input-record-char-1" class="form-control" placeholder="Karakter..."
              list="storm-characters" autocomplete="off">
            <input type="number" id="input-record-ws-1" class="form-control" placeholder="0" min="0">
          </div>
        </div>

        <!-- TOP 2 -->
        <div class="top-record-row"
          style="background: rgba(192,192,192,0.08); border: 1px solid rgba(192,192,192,0.35); border-radius: 6px; padding: 6px 8px; margin-bottom: 6px;">
          <div style="font-weight: 800; font-size: 0.8rem; color: #c0c0c0; margin-bottom: 4px;">🥈 TOP 2 (JUARA 2)</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 6px;">
            <input type="text" id="input-record-name-2" class="form-control" placeholder="Nama juara 2...">
            <input type="text" id="input-record-char-2" class="form-control" placeholder="Karakter..."
              list="storm-characters" autocomplete="off">
            <input type="number" id="input-record-ws-2" class="form-control" placeholder="0" min="0">
          </div>
        </div>

        <!-- TOP 3 -->
        <div class="top-record-row"
          style="background: rgba(205,127,50,0.08); border: 1px solid rgba(205,127,50,0.35); border-radius: 6px; padding: 6px 8px; margin-bottom: 8px;">
          <div style="font-weight: 800; font-size: 0.8rem; color: #cd7f32; margin-bottom: 4px;">🥉 TOP 3 (JUARA 3)</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr 80px; gap: 6px;">
            <input type="text" id="input-record-name-3" class="form-control" placeholder="Nama juara 3...">
            <input type="text" id="input-record-char-3" class="form-control" placeholder="Karakter..."
              list="storm-characters" autocomplete="off">
            <input type="number" id="input-record-ws-3" class="form-control" placeholder="0" min="0">
          </div>
        </div>

        <!-- Hidden input picker fallback agar dropdown lama tidak error jika ada script membaca -->
        <input type="hidden" id="input-record-name">
        <input type="hidden" id="input-record-char">
        <input type="hidden" id="input-record-ws">

        <div style="display: flex; gap: 8px;">
          <button class="btn btn-primary" style="flex: 1;" onclick="saveTopRecord()">Simpan Top 3 Rekor</button>
          <button class="btn btn-danger" style="flex: 1;" onclick="resetTopRecord()">Reset Top 3</button>
        </div>
      </div>

      <!-- BACKUP & RESTORE DATA -->
      <div class="card full-width">
        <div class="card-title">
          <span>Backup & Restore</span>
        </div>
        <p class="instructions" style="margin-bottom: 10px;">Download / upload file data pemain.</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
          <button type="button" class="btn btn-blue" onclick="exportDataBackup()">Download Backup</button>
          <label class="btn btn-primary" style="margin-bottom: 0; cursor: pointer;">
            Restore Backup
            <input type="file" accept=".json" style="display: none;" onchange="importDataBackup(event)">
          </label>
        </div>
      </div>

      <!-- RESET ALL SESSION -->
      <div class="card full-width" style="text-align: center; border-color: var(--border-strong);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
          <div style="text-align: left;">
            <span style="font-weight: bold; color: var(--text-light);">Reset Sesi</span>
            <div style="font-size: 0.8rem; color: var(--text-muted);">Hapus semua data untuk sesi live baru.</div>
          </div>
          <button class="btn btn-sm btn-danger" onclick="resetAllData()">Reset Total</button>
        </div>
      </div>

    </div>
  </div>

  <!-- VIEW 2: TIKTOK LIVE OVERLAY SOURCE -->
  <div id="overlay-view" class="view-container">
    <div class="overlay-container">
      <!-- NOTE / TICKER BAR PREVIEW -->
      <div id="ov-ticker-bar"
        style="background: rgba(0,0,0,0.45); border-radius: 8px; padding: 6px 10px; margin-bottom: 6px; border: 1px solid rgba(255,255,255,0.2); border-left: 4px solid #fff; display: none; align-items: center; gap: 8px; font-size: 0.95rem; color: #fff; overflow: hidden;">
        <span style="font-weight: 900; font-size: 0.85rem; letter-spacing: 0.8px; white-space: nowrap;">NOTE:</span>
        <div style="flex: 1; white-space: nowrap; overflow: hidden; min-width: 0;">
          <span id="ov-ticker-text" style="font-weight: 700;"></span>
        </div>
      </div>
      <div id="ov-top-record-bar"
        style="background: rgba(0,0,0,0.45); border-radius: 8px; padding: 6px 10px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.2); border-left: 4px solid #fff; display: flex; align-items: center; gap: 8px; font-size: 0.92rem; color: #fff;">
        <span style="font-weight: 900; font-size: 0.85rem; letter-spacing: 0.8px;">TOP WS:</span>
        <span id="ov-top-rec-text" style="font-weight: 700;">- | - | 0 WS</span>
      </div>
      <div class="overlay-match">
        <div class="overlay-player p1">
          <div>
            <div id="ov-p1-name" class="overlay-pname">Waiting...</div>
            <div id="ov-p1-char" class="overlay-pchar">-</div>
          </div>
          <div style="text-align: right;">
            <div class="overlay-ws">WS: <span id="ov-p1-ws">0</span> <span id="ov-p1-bonus" class="free-streak-badge"
                style="display:none; background:#a855f7;">+1 MATCH</span></div>
            <div style="font-size:0.75rem; color:#aaa;">Match: <span id="ov-p1-m">0</span></div>
          </div>
        </div>

        <div style="text-align: center; font-size: 0.75rem; font-weight:bold; color:var(--accent-red);">— VS —</div>

        <div class="overlay-player p2">
          <div>
            <div id="ov-p2-name" class="overlay-pname">Waiting...</div>
            <div id="ov-p2-char" class="overlay-pchar">-</div>
          </div>
          <div style="text-align: right;">
            <div class="overlay-ws">WS: <span id="ov-p2-ws">0</span> <span id="ov-p2-bonus" class="free-streak-badge"
                style="display:none; background:#a855f7;">+1 MATCH</span></div>
            <div style="font-size:0.75rem; color:#aaa;">Match: <span id="ov-p2-m">0</span></div>
          </div>
        </div>
      </div>

      <div class="overlay-queue-title">MATCH QUEUE:</div>
      <div id="ov-queue-list"></div>
      <div id="ov-free-closed-banner" class="free-closed-banner" style="display:none;">FREE TUTUP</div>
    </div>
  </div>

  <!-- MODAL PENDAFTARAN PEMAIN BARU (MULTI-STEP SESUAI ALUR VIEWER) -->
  <div id="modal-add-player" class="modal-overlay" onclick="handleAdminRegBackdrop(event)">
    <div class="modal-box"
      style="max-width: 580px; width: 100%; max-height: 92vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; border: 2px solid var(--border-strong); box-shadow: 0 16px 40px rgba(0,0,0,0.7); border-radius: 12px;">
      
      <!-- Modal Header -->
      <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25);">
        <div style="display: flex; align-items: center; gap: 8px;">
          <h3 id="admin-reg-modal-title" style="margin: 0; color: var(--accent-gold); font-size: 1.15rem; font-weight: 800;">
            ➕ Pendaftaran Pemain
          </h3>
          <span id="admin-reg-tier-pill" style="font-size: 0.72rem; font-weight: 900; padding: 2px 8px; border-radius: 4px; background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #22c55e;">
            FREE
          </span>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="closeAdminRegModal()"
          style="padding: 2px 8px; font-weight: 900; font-size: 1rem; border-radius: 4px;">✕</button>
      </div>

      <!-- Step Breadcrumb Bar -->
      <div style="padding: 10px 18px 0; background: rgba(0,0,0,0.15);">
        <div id="admin-reg-step-bar" class="admin-reg-steps">
          <!-- Dynamic steps based on tier (3 steps for FREE, 5 steps for VIP/SROBOT) -->
        </div>
      </div>

      <!-- Modal Body (Step Contents) -->
      <div style="padding: 14px 18px; overflow-y: auto; flex: 1;">
        
        <!-- STEP 1: NAMA, TIER, TIKET -->
        <div id="admin-reg-step-1" style="display: flex; flex-direction: column; gap: 12px;">
          <!-- Tier Selector -->
          <div class="form-group">
            <label style="font-size: 0.8rem; font-weight: 800; color: var(--text-light); margin-bottom: 4px;">Pilih Jalur / Tier Pendaftaran:</label>
            <div style="display: flex; gap: 8px;">
              <button type="button" class="admin-tier-btn active tier-free" id="btn-atier-free" onclick="selectAdminRegTier('FREE')">
                <span style="font-size: 1rem;">🟢</span>
                <span>FREE REQUEST</span>
                <span style="font-size: 0.68rem; opacity: 0.8;">(3 Langkah)</span>
              </button>
              <button type="button" class="admin-tier-btn tier-vip" id="btn-atier-vip" onclick="selectAdminRegTier('VIP')">
                <span style="font-size: 1rem;">🟡</span>
                <span>VIP</span>
                <span style="font-size: 0.68rem; opacity: 0.8;">(5 Langkah)</span>
              </button>
              <button type="button" class="admin-tier-btn tier-srobot" id="btn-atier-srobot" onclick="selectAdminRegTier('SROBOT')">
                <span style="font-size: 1rem;">🟣</span>
                <span>SROBOT</span>
                <span style="font-size: 0.68rem; opacity: 0.8;">(5 Langkah)</span>
              </button>
            </div>
          </div>

          <!-- Pilihan Jenis Tiket -->
          <div class="form-group" style="background: rgba(0,0,0,0.25); padding: 8px 10px; border-radius: 6px; border: 1px solid var(--border-color);">
            <label style="display: block; margin-bottom: 6px; font-size: 0.80rem; font-weight: 800; color: var(--accent-gold);">
              🎟️ Jenis Tiket Match:
            </label>
            <div style="display: flex; gap: 6px;">
              <button type="button" id="btn-aticket-1v1" class="btn btn-sm btn-primary"
                style="flex: 1; padding: 6px 4px; font-weight: 800; font-size: 0.78rem;" onclick="setAdminRegTicketMode('1v1')">
                ⚡ 1vs1
              </button>
              <button type="button" id="btn-aticket-duo" class="btn btn-sm btn-blue"
                style="flex: 1; padding: 6px 4px; font-weight: 800; font-size: 0.78rem; opacity: 0.7;" onclick="setAdminRegTicketMode('DUO')">
                ⚔️ DUO (2)
              </button>
              <button type="button" id="btn-aticket-tim" class="btn btn-sm btn-blue"
                style="flex: 1; padding: 6px 4px; font-weight: 800; font-size: 0.78rem; opacity: 0.7;" onclick="setAdminRegTicketMode('TIM')">
                👑 TIM (2-3)
              </button>
            </div>
          </div>

          <!-- Input Username (Dropdown / Ketik) -->
          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <label style="font-size: 0.82rem; font-weight: 800; color: var(--text-light); margin: 0;">Nama TikTok / Username Pemain *</label>
              <span id="admin-reg-user-count" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;"></span>
            </div>
            <select id="admin-reg-select-user" class="form-control" style="background: var(--panel-subtle); cursor: pointer; margin-bottom: 6px;" onchange="handleAdminRegSelectUser(this.value)">
              <option value="">▼ PILIH DARI USER TERDAFTAR (A-Z)...</option>
            </select>
            <input type="text" id="admin-reg-name-input" class="form-control" placeholder="Atau ketik nama TikTok pemain..." autocomplete="off" oninput="checkAdminRegNameDup()" onchange="checkAdminRegNameDup()">
            <div id="admin-reg-name-warning" style="display:none; margin-top: 6px; padding: 6px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; line-height: 1.4;">
            </div>
          </div>
        </div>

        <!-- STEP 2: PILIH KARAKTER A-Z -->
        <div id="admin-reg-step-2" style="display: none; flex-direction: column; gap: 10px;">
          <!-- Slot Tabs Bar for DUO / TIM -->
          <div id="admin-reg-slot-tabs" class="char-slot-tabs-bar" style="display: none;"></div>

          <!-- TIM Flexible Toggle Bar -->
          <div id="admin-reg-tim-flex-bar" style="display: none; justify-content: space-between; align-items: center; background: rgba(168,85,247,0.12); border: 1px solid rgba(168,85,247,0.3); border-radius: 6px; padding: 6px 10px;">
            <span style="font-size: 0.74rem; font-weight: 700; color: #d8b4fe;">💡 Mode TIM Fleksibel: Bisa pakai 2 atau 3 karakter</span>
            <button type="button" id="btn-admin-tim-toggle-slot3" class="btn btn-sm btn-blue" style="font-size: 0.72rem; padding: 2px 8px;" onclick="toggleAdminRegTimSlot3()">
              Hanya Pakai 2 Karakter
            </button>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center;">
            <label id="admin-reg-char-label" style="font-size: 0.82rem; font-weight: 800; color: var(--text-light); margin: 0;">Pilih Karakter (A-Z Master Roster):</label>
            <span id="admin-reg-char-badge" style="font-size: 0.75rem; font-weight: 800; color: #facc15; background: rgba(234, 179, 8, 0.15); padding: 2px 8px; border-radius: 4px;">Belum dipilih</span>
          </div>

          <!-- Search Input Karakter atau Ulti -->
          <input type="text" id="admin-reg-char-search" class="form-control" placeholder="🔍 Cari nama karakter atau ulti (misal: Sasuke, kilat, malam...)" autocomplete="off" oninput="renderAdminRegCharGrid()">

          <!-- Alphabet Filter Bar -->
          <div class="alphabet-filter-bar" id="admin-reg-alpha-bar" style="display: flex; gap: 4px; overflow-x: auto; padding-bottom: 4px;">
            <!-- Buttons ALL, A, B, C... Z -->
          </div>

          <!-- Character Cards Grid -->
          <div class="char-grid-container admin-char-grid" id="admin-reg-char-grid" style="max-height: 250px; overflow-y: auto;">
            <!-- Rendered cards -->
          </div>
        </div>

        <!-- STEP 3: PILIH ULTI -->
        <div id="admin-reg-step-3" style="display: none; flex-direction: column; gap: 12px;">
          <!-- Multi-slot ulti container -->
          <div id="admin-reg-slots-ulti-container">
            <!-- Rendered dynamically per slot -->
          </div>

          <!-- Multi ulti box (fallback / single slot) -->
          <div id="admin-reg-multi-ulti-box" style="display: none;">
            <div style="font-size: 0.82rem; font-weight: 800; color: var(--text-light); margin-bottom: 8px;">
              ⚡ Karakter ini memiliki lebih dari 1 jurus Ulti. Pilih salah satu:
            </div>
            <div class="ulti-options-grid" id="admin-reg-ulti-options">
              <!-- Ulti card items -->
            </div>
          </div>

          <!-- Single ulti box (fallback / single slot) -->
          <div id="admin-reg-single-ulti-box" style="display: none; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 12px; text-align: center;">
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 4px;">Jurus Ultimate Terkunci:</div>
            <div id="admin-reg-single-ulti-name" style="font-size: 1rem; font-weight: 900; color: var(--accent-gold);"></div>
          </div>

          <!-- Ringkasan jika tier FREE (karena FREE selesai di langkah 3) -->
          <div id="admin-reg-free-summary-box" style="display: none; background: rgba(34, 197, 94, 0.08); border: 1.5px solid rgba(34, 197, 94, 0.3); border-radius: 8px; padding: 12px;">
            <div style="font-size: 0.8rem; font-weight: 800; color: #4ade80; margin-bottom: 8px;">📋 Ringkasan Pendaftaran Free Request:</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.8rem;">
              <div><span style="color:var(--text-muted)">Nama TT:</span> <b id="admin-sum-free-name" style="color:#fff;">-</b></div>
              <div><span style="color:var(--text-muted)">Tiket:</span> <b id="admin-sum-free-ticket" style="color:#fff;">1v1</b></div>
              <div style="grid-column: 1 / -1;"><span style="color:var(--text-muted)">Karakter:</span> <b id="admin-sum-free-char" style="color:var(--accent-gold);">-</b></div>
              <div style="grid-column: 1 / -1;"><span style="color:var(--text-muted)">Ulti:</span> <b id="admin-sum-free-ulti" style="color:#fde047;">-</b></div>
            </div>
          </div>
        </div>

        <!-- STEP 4: JUMLAH MATCH (KHUSUS VIP / SROBOT) -->
        <div id="admin-reg-step-4" style="display: none; flex-direction: column; gap: 12px;">
          <div style="font-size: 0.82rem; font-weight: 800; color: var(--text-light);">
            Pilih Jumlah Match Tiket VIP/SROBOT:
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm btn-match-count active" onclick="setAdminRegMatchCount(1)" id="btn-amatch-1">1 Match</button>
            <button type="button" class="btn btn-sm btn-match-count" onclick="setAdminRegMatchCount(3)" id="btn-amatch-3">3 Match</button>
            <button type="button" class="btn btn-sm btn-match-count" onclick="setAdminRegMatchCount(5)" id="btn-amatch-5">5 Match</button>
            <button type="button" class="btn btn-sm btn-match-count" onclick="setAdminRegMatchCount(10)" id="btn-amatch-10">10 Match</button>
          </div>
          <div class="counter-control-wrap" style="display: flex; align-items: center; justify-content: center; gap: 12px; background: rgba(0,0,0,0.35); padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            <button type="button" class="btn-counter" onclick="changeAdminRegMatchCount(-1)" style="width:36px; height:36px; font-size:1.2rem; cursor:pointer;">-</button>
            <div id="admin-reg-match-display" style="font-size: 1.4rem; font-weight: 900; color: #fde047; min-width: 60px; text-align: center;">1</div>
            <button type="button" class="btn-counter" onclick="changeAdminRegMatchCount(1)" style="width:36px; height:36px; font-size:1.2rem; cursor:pointer;">+</button>
          </div>
          <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; text-align: center;">
            <div>
              <div style="font-size:0.7rem; color:var(--text-muted)">Estimasi Koin TikTok</div>
              <div id="admin-calc-coins" style="font-size:1.1rem; font-weight:900; color:#facc15;">5 Koin</div>
            </div>
            <div>
              <div style="font-size:0.7rem; color:var(--text-muted)">Estimasi SociaBuzz</div>
              <div id="admin-calc-rp" style="font-size:1.1rem; font-weight:900; color:#4ade80;">Rp 1.000</div>
            </div>
          </div>
        </div>

        <!-- STEP 5: INFO PEMBAYARAN & KONFIRMASI (KHUSUS VIP / SROBOT) -->
        <div id="admin-reg-step-5" style="display: none; flex-direction: column; gap: 12px;">
          <div class="price-display-card" style="background: linear-gradient(135deg, rgba(234, 179, 8, 0.15), rgba(168, 85, 247, 0.12)); border: 1.5px solid #eab308; border-radius: 10px; padding: 12px; text-align: center;">
            <div style="font-size: 0.78rem; font-weight: 800; color: #fde047; text-transform: uppercase;">💳 Info Pembayaran & Donasi:</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
              <div style="background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 6px;">
                <div style="font-size:0.7rem; color:var(--text-muted);">🎁 TikTok Gift:</div>
                <div id="admin-final-coins" style="font-size:1.15rem; font-weight:900; color:#facc15;">5 Koin</div>
              </div>
              <div style="background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 6px;">
                <div style="font-size:0.7rem; color:var(--text-muted);">💳 SociaBuzz:</div>
                <div id="admin-final-rp" style="font-size:1.15rem; font-weight:900; color:#4ade80;">Rp 1.000</div>
              </div>
            </div>
          </div>

          <div style="background: rgba(0,0,0,0.3); border: 1.5px solid var(--border-color); border-radius: 8px; padding: 12px;">
            <div style="font-size: 0.8rem; font-weight: 800; color: var(--accent-gold); margin-bottom: 8px;">📋 Ringkasan Pendaftaran Berbayar:</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.8rem;">
              <div><span style="color:var(--text-muted)">Nama TT:</span> <b id="admin-sum-paid-name" style="color:#fff;">-</b></div>
              <div><span style="color:var(--text-muted)">Tier:</span> <b id="admin-sum-paid-tier" style="color:#fde047;">VIP</b></div>
              <div><span style="color:var(--text-muted)">Tiket:</span> <b id="admin-sum-paid-ticket" style="color:#fff;">1v1</b></div>
              <div><span style="color:var(--text-muted)">Match:</span> <b id="admin-sum-paid-match" style="color:#4ade80;">1 Match</b></div>
              <div style="grid-column: 1 / -1;"><span style="color:var(--text-muted)">Karakter:</span> <b id="admin-sum-paid-char" style="color:var(--accent-gold);">-</b></div>
              <div style="grid-column: 1 / -1;"><span style="color:var(--text-muted)">Ulti:</span> <b id="admin-sum-paid-ulti" style="color:#fde047;">-</b></div>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer (Navigation & Submit Buttons) -->
      <div style="padding: 12px 18px; border-top: 1px solid var(--border-color); background: rgba(0,0,0,0.25); display: flex; justify-content: space-between; align-items: center; gap: 10px;">
        <button type="button" id="btn-admin-reg-prev" class="btn btn-sm btn-blue" onclick="prevAdminRegStep()" style="display: none; padding: 8px 14px; font-weight: 800;">
          ← Kembali
        </button>
        <div style="flex: 1;"></div>
        <button type="button" id="btn-admin-reg-next" class="btn btn-sm btn-primary" onclick="nextAdminRegStep()" style="padding: 8px 16px; font-weight: 800;">
          Lanjut ke Karakter ➔
        </button>
        
        <!-- Submit Buttons (Visible on Final Step: Step 3 for FREE, Step 5 for VIP/SROBOT) -->
        <button type="button" id="btn-admin-reg-queue" class="btn btn-sm btn-primary" onclick="submitAdminRegistration('queue')" style="display: none; padding: 8px 14px; font-weight: 800; background: #22c55e; border-color: #16a34a;">
          ➕ Masuk Antrean
        </button>
        <button type="button" id="btn-admin-reg-saved" class="btn btn-sm btn-blue" onclick="submitAdminRegistration('saved')" style="display: none; padding: 8px 14px; font-weight: 800;">
          💾 Simpan Tabungan
        </button>
      </div>

    </div>
  </div>

  <!-- MODAL KONVERSI TIKET (2x VIP 1v1 = 1x VIP TIM) -->
  <div id="modal-convert-ticket" class="modal-overlay" onclick="handleModalConvertBackdrop(event)">
    <div class="modal-box"
      style="max-width: 440px; border: 2px solid var(--border-strong); box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
      <div
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
        <h3
          style="margin: 0; color: var(--accent-gold); font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 6px;">
          <span>🔄 Konversi Tiket Match</span>
        </h3>
        <button type="button" class="btn btn-sm btn-danger" onclick="closeConvertModal()"
          style="padding: 2px 8px; font-weight: 900; font-size: 1rem; border-radius: 4px;">✕</button>
      </div>

      <div
        style="background: var(--panel-subtle); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 12px; font-size: 0.82rem;">
        <div style="color: var(--text-muted); margin-bottom: 2px;">Pemain:</div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <b id="convert-player-name" style="font-size: 1.05rem; color: #fff;">-</b>
          <span id="convert-player-badge" class="badge badge-vip">VIP</span>
        </div>
        <div style="margin-top: 6px; font-size: 0.85rem; color: var(--text-light);">
          Tiket Saat Ini: <b id="convert-current-tickets" style="color: var(--accent-gold);">0 Match</b> (<span
            id="convert-current-mode">1vs1</span>)
        </div>
      </div>

      <div
        style="background: rgba(2, 132, 199, 0.12); border: 1px dashed #0284c7; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; font-size: 0.82rem; line-height: 1.4;">
        <div style="font-weight: 800; color: var(--accent-gold); margin-bottom: 3px;">⚖️ Aturan Kurs Konversi:</div>
        <div>• <b>2x Match 1vs1</b> = <b>1x Match TIM</b></div>
        <div>• <b>1x Match TIM</b> = <b>2x Match 1vs1</b></div>
      </div>

      <form id="convert-ticket-form" onsubmit="handleExecuteConversion(event)">
        <input type="hidden" id="convert-player-id" value="">
        <input type="hidden" id="convert-list-type" value="saved">
        <input type="hidden" id="convert-from-mode" value="1v1">

        <div class="form-group" style="margin-bottom: 10px;">
          <label style="font-weight: 700; font-size: 0.82rem; margin-bottom: 4px;">Arah Konversi:</label>
          <div id="convert-direction-label"
            style="font-size: 0.9rem; font-weight: 800; color: #fff; background: var(--panel-subtle); padding: 8px; border-radius: 6px; border: 1px solid var(--border-color);">
            ⚡ 1vs1 ➔ 👑 TIM (Rasio 2 : 1)
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
          <label style="font-weight: 700; font-size: 0.82rem; margin-bottom: 4px;" id="convert-amount-label">Jumlah
            Tiket yang Dikonversi:</label>
          <input type="number" id="convert-amount-input" class="form-control" value="2" min="1" required
            oninput="updateConvertPreview()">
          <div id="convert-preview-text"
            style="margin-top: 6px; font-size: 0.82rem; color: var(--accent-gold); font-weight: 700;">
            Hasil: 1 Match TIM
          </div>
          <div id="convert-error-text"
            style="display: none; margin-top: 4px; font-size: 0.8rem; color: #f87171; font-weight: 700;"></div>
        </div>

        <div style="display: flex; gap: 8px;">
          <button type="submit" id="btn-submit-convert" class="btn btn-primary"
            style="flex: 1; padding: 10px; font-weight: 800;">
            ✅ Konversi Tiket
          </button>
          <button type="button" class="btn btn-danger" style="flex: 1; padding: 10px; font-weight: 800;"
            onclick="closeConvertModal()">
            Batal
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL MENU AKSI PEMAIN (TITIK TIGA) -->
  <div id="modal-player-actions" class="modal-overlay" onclick="handlePlayerActionBackdrop(event)">
    <div class="modal-box"
      style="max-width: 480px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; padding: 18px; border: 2px solid var(--border-strong); box-shadow: 0 16px 40px rgba(0,0,0,0.85); border-radius: 12px; color: var(--text-light); background: var(--panel-bg);">
      
      <!-- Modal Header -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; flex-shrink: 0;">
        <h3 style="margin: 0; color: var(--accent-gold); font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
          <span>⚙️ Menu Aksi Pemain</span>
        </h3>
        <button type="button" class="btn btn-sm btn-danger" onclick="closePlayerActionModal()"
          style="padding: 2px 8px; font-weight: 900; font-size: 1rem; border-radius: 4px;">✕</button>
      </div>

      <!-- Player Preview Summary Card -->
      <div id="action-modal-player-card" style="flex-shrink: 0; margin-bottom: 12px;"></div>

      <!-- Actions Container (Scrollable) -->
      <div id="action-modal-buttons-container"
        style="display: flex; flex-direction: column; gap: 10px; overflow-y: auto; padding-right: 4px; flex: 1;">
      </div>

    </div>
  </div>

  <!-- MODAL GANTI KARAKTER PEMAIN (PERSIS FORM PENDAFTARAN) -->
  <div id="modal-edit-player-char" class="modal-overlay" onclick="handleEditCharBackdrop(event)">
    <div class="modal-box"
      style="max-width: 580px; width: 100%; max-height: 92vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; border: 2px solid var(--border-strong); box-shadow: 0 16px 40px rgba(0,0,0,0.85); border-radius: 12px; background: var(--panel-bg); color: var(--text-light);">
      
      <!-- Modal Header -->
      <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); flex-shrink: 0;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <h3 style="margin: 0; color: var(--accent-gold); font-size: 1.15rem; font-weight: 800;">
            🥋 Ganti Karakter Pemain
          </h3>
          <span id="edit-char-player-badge" style="font-size: 0.72rem; font-weight: 900; padding: 2px 8px; border-radius: 4px; background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3);">
            -
          </span>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="closeEditPlayerCharModal()"
          style="padding: 2px 8px; font-weight: 900; font-size: 1rem; border-radius: 4px;">✕</button>
      </div>

      <!-- Mode Selector Bar -->
      <div style="background: rgba(0,0,0,0.3); padding: 8px 18px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
        <span style="font-size: 0.78rem; font-weight: 800; color: var(--accent-gold);">🎟️ Mode Match:</span>
        <div style="display: flex; gap: 6px;">
          <button type="button" id="btn-edit-mode-1v1" class="btn btn-sm btn-primary" style="padding: 4px 10px; font-size: 0.76rem; font-weight: 800;" onclick="setEditCharMode('1v1')">⚡ 1v1</button>
          <button type="button" id="btn-edit-mode-duo" class="btn btn-sm btn-blue" style="padding: 4px 10px; font-size: 0.76rem; font-weight: 800; opacity: 0.7;" onclick="setEditCharMode('DUO')">⚔️ DUO (2)</button>
          <button type="button" id="btn-edit-mode-tim" class="btn btn-sm btn-blue" style="padding: 4px 10px; font-size: 0.76rem; font-weight: 800; opacity: 0.7;" onclick="setEditCharMode('TIM')">👑 TIM (2-3)</button>
        </div>
      </div>

      <!-- Step Indicator Bar -->
      <div class="admin-reg-steps-bar" style="background: rgba(0,0,0,0.2); padding: 8px 18px; border-bottom: 1px solid var(--border-color); display: flex; gap: 8px; justify-content: center; flex-shrink: 0;">
        <div id="edit-char-step-tab-1" class="admin-step-item active" style="flex: 1; text-align: center; font-size: 0.8rem; font-weight: 800; padding: 5px; border-radius: 6px; background: rgba(234, 179, 8, 0.18); color: #facc15; border: 1px solid #facc15; cursor: pointer;" onclick="editCharGoToStep(1)">
          1. Pilih Karakter
        </div>
        <div id="edit-char-step-tab-2" class="admin-step-item" style="flex: 1; text-align: center; font-size: 0.8rem; font-weight: 800; padding: 5px; border-radius: 6px; background: rgba(255, 255, 255, 0.05); color: #94a3b8; border: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer;" onclick="editCharNextStep()">
          2. Pilih Jurus Ulti
        </div>
      </div>

      <!-- Modal Body (Scrollable) -->
      <div style="padding: 16px 18px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 14px;">
        
        <!-- STEP 1: PILIH KARAKTER A-Z & SEARCH -->
        <div id="edit-char-step-1" style="display: flex; flex-direction: column; gap: 10px;">
          <!-- Slot Tabs Bar for DUO / TIM -->
          <div id="edit-char-slot-tabs" class="char-slot-tabs-bar" style="display: none;"></div>

          <!-- TIM Flexible Toggle Bar -->
          <div id="edit-char-tim-flex-bar" style="display: none; justify-content: space-between; align-items: center; background: rgba(168,85,247,0.12); border: 1px solid rgba(168,85,247,0.3); border-radius: 6px; padding: 6px 10px;">
            <span style="font-size: 0.74rem; font-weight: 700; color: #d8b4fe;">💡 Mode TIM Fleksibel: Bisa pakai 2 atau 3 karakter</span>
            <button type="button" id="btn-edit-tim-toggle-slot3" class="btn btn-sm btn-blue" style="font-size: 0.72rem; padding: 2px 8px;" onclick="toggleEditTimSlot3()">
              Hanya Pakai 2 Karakter
            </button>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center;">
            <label id="edit-char-label" style="font-size: 0.82rem; font-weight: 800; color: var(--text-light); margin: 0;">Pilih Karakter (A-Z Master Roster):</label>
            <span id="edit-char-selected-badge" style="font-size: 0.75rem; font-weight: 800; color: #facc15; background: rgba(234, 179, 8, 0.15); padding: 2px 8px; border-radius: 4px;">Belum dipilih</span>
          </div>

          <!-- Search Input Karakter atau Ulti -->
          <input type="text" id="edit-char-search" class="form-control" placeholder="🔍 Cari nama karakter atau ulti (misal: Sasuke, kilat, malam...)" autocomplete="off" oninput="renderEditCharGrid()">

          <!-- Alphabet Filter Bar -->
          <div class="alphabet-filter-bar" id="edit-char-alpha-bar" style="display: flex; gap: 4px; overflow-x: auto; padding-bottom: 4px;">
            <!-- Buttons ALL, A, B, C... Z -->
          </div>

          <!-- Character Cards Grid -->
          <div class="char-grid-container admin-char-grid" id="edit-char-grid" style="max-height: 270px; overflow-y: auto;">
            <!-- Rendered cards -->
          </div>
        </div>

        <!-- STEP 2: PILIH ULTI -->
        <div id="edit-char-step-2" style="display: none; flex-direction: column; gap: 12px;">
          <!-- Multi-slot ulti container -->
          <div id="edit-char-slots-ulti-container">
            <!-- Rendered dynamically per slot -->
          </div>

          <!-- Multi ulti box (fallback) -->
          <div id="edit-char-multi-ulti-box" style="display: none;">
            <div style="font-size: 0.82rem; font-weight: 800; color: var(--text-light); margin-bottom: 8px;">
              ⚡ Karakter ini memiliki lebih dari 1 jurus Ulti. Pilih salah satu:
            </div>
            <div class="ulti-options-grid" id="edit-char-ulti-options">
              <!-- Ulti card items -->
            </div>
          </div>

          <!-- Single ulti box (fallback) -->
          <div id="edit-char-single-ulti-box" style="display: none; background: rgba(0,0,0,0.3); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 12px; text-align: center;">
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 4px;">Jurus Ultimate Terkunci:</div>
            <div id="edit-char-single-ulti-name" style="font-size: 1rem; font-weight: 900; color: var(--accent-gold);"></div>
          </div>

          <!-- Summary Box -->
          <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
            <div style="font-size: 0.8rem; font-weight: 800; color: var(--accent-gold); margin-bottom: 8px;">📋 Ringkasan Karakter yang Dipilih:</div>
            <div style="display: grid; grid-template-columns: 1fr; gap: 6px; font-size: 0.83rem;">
              <div><span style="color:var(--text-muted)">Pemain:</span> <b id="edit-char-sum-player-name" style="color:#fff;">-</b></div>
              <div><span style="color:var(--text-muted)">Mode:</span> <b id="edit-char-sum-mode" style="color:#38bdf8;">1v1</b></div>
              <div><span style="color:var(--text-muted)">Karakter:</span> <b id="edit-char-sum-char-name" style="color:var(--accent-gold);">-</b></div>
              <div><span style="color:var(--text-muted)">Jurus Ulti:</span> <b id="edit-char-sum-ulti-name" style="color:#fde047;">-</b></div>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div style="padding: 12px 18px; border-top: 1px solid var(--border-color); background: rgba(0,0,0,0.2); display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-shrink: 0;">
        <button type="button" id="btn-edit-char-prev" class="btn btn-secondary" onclick="editCharGoToStep(1)" style="display: none; padding: 8px 14px; font-weight: 800;">
          ← Kembali ke Roster
        </button>
        <div style="display: flex; gap: 8px; margin-left: auto;">
          <button type="button" class="btn btn-danger" onclick="closeEditPlayerCharModal()" style="padding: 8px 14px; font-weight: 800;">
            Batal
          </button>
          <button type="button" id="btn-edit-char-next" class="btn btn-primary" onclick="editCharNextStep()" style="padding: 8px 18px; font-weight: 800;">
            Lanjut Pilih Ulti ➔
          </button>
          <button type="button" id="btn-edit-char-save" class="btn btn-success" onclick="saveEditedPlayerChar()" style="display: none; padding: 8px 18px; font-weight: 800;">
            💾 Simpan Karakter
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- MODAL EDIT CHAR -->
  <div id="modal-char" class="modal-overlay">
    <div class="modal-box">
      <h3 style="margin-bottom: 10px; color: var(--accent-gold);">Pilih / Ubah Karakter</h3>
      <div class="form-group">
        <label id="modal-target-label">Pilih / Ubah Karakter:</label>

        <!-- Format Tim Modal -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Format Karakter:</span>
          <div style="display: flex; gap: 4px;">
            <button type="button" id="modal-btn-1v1" class="btn btn-sm btn-primary"
              style="padding: 2px 8px; font-size: 0.75rem;" onclick="setModalTeamMode('1v1')">1v1 (1)</button>
            <button type="button" id="modal-btn-duo" class="btn btn-sm btn-blue"
              style="padding: 2px 8px; font-size: 0.75rem; opacity: 0.7;" onclick="setModalTeamMode('duo')">Duo
              (2)</button>
            <button type="button" id="modal-btn-tim" class="btn btn-sm btn-blue"
              style="padding: 2px 8px; font-size: 0.75rem; opacity: 0.7;" onclick="setModalTeamMode('tim')">Tim
              (3)</button>
          </div>
        </div>

        <!-- Slot 1 Modal -->
        <div id="modal-group-char-1" style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 6px;">
          <div id="modal-label-char-1"
            style="font-size: 0.75rem; font-weight: 700; color: var(--accent-gold); display: none;">Karakter 1 (Utama /
            Leader):</div>
          <div class="char-picker-container">
            <input type="text" id="modal-char-input-1" class="form-control"
              placeholder="Ketik atau klik untuk cari karakter..." autocomplete="off"
              onfocus="openCharPicker(this, 1, 'modal')" oninput="handleCharInput(this, 1, 'modal')">
            <div id="picker-popup-modal-1" class="char-picker-popup" style="display:none;"></div>
          </div>
          <div id="modal-group-ulti-1"
            style="display:flex; align-items:center; gap:6px; background:rgba(0,0,0,0.25); padding:4px 8px; border-radius:4px; border:1px solid rgba(255,255,255,0.08); margin-top:2px;">
            <span style="font-size:0.75rem; font-weight:700; color:var(--accent-gold); white-space:nowrap;">⚡
              Ulti:</span>
            <select id="modal-select-ulti-1" class="form-control" style="font-size:0.78rem; padding:3px 6px; flex:1;"
              onchange="updateModalTeamCharValue()" disabled>
              <option value="">(Pilih karakter dulu)</option>
            </select>
          </div>
        </div>

        <!-- Slot 2 Modal -->
        <div id="modal-group-char-2" style="display: none; flex-direction: column; gap: 4px; margin-bottom: 6px;">
          <div style="font-size: 0.75rem; font-weight: 700; color: #60a5fa;">Karakter 2 (Support 1):</div>
          <div class="char-picker-container">
            <input type="text" id="modal-char-input-2" class="form-control" placeholder="Ketik atau klik Karakter 2..."
              autocomplete="off" onfocus="openCharPicker(this, 2, 'modal')" oninput="handleCharInput(this, 2, 'modal')">
            <div id="picker-popup-modal-2" class="char-picker-popup" style="display:none;"></div>
          </div>
          <div id="modal-group-ulti-2"
            style="display:flex; align-items:center; gap:6px; background:rgba(0,0,0,0.25); padding:4px 8px; border-radius:4px; border:1px solid rgba(255,255,255,0.08); margin-top:2px;">
            <span style="font-size:0.75rem; font-weight:700; color:#60a5fa; white-space:nowrap;">⚡ Ulti:</span>
            <select id="modal-select-ulti-2" class="form-control" style="font-size:0.78rem; padding:3px 6px; flex:1;"
              onchange="updateModalTeamCharValue()" disabled>
              <option value="">(Pilih karakter dulu)</option>
            </select>
          </div>
        </div>

        <!-- Slot 3 Modal -->
        <div id="modal-group-char-3" style="display: none; flex-direction: column; gap: 4px; margin-bottom: 6px;">
          <div style="font-size: 0.75rem; font-weight: 700; color: #c084fc;">Karakter 3 (Support 2 - Opsional):</div>
          <div class="char-picker-container">
            <input type="text" id="modal-char-input-3" class="form-control" placeholder="Ketik atau klik Karakter 3..."
              autocomplete="off" onfocus="openCharPicker(this, 3, 'modal')" oninput="handleCharInput(this, 3, 'modal')">
            <div id="picker-popup-modal-3" class="char-picker-popup" style="display:none;"></div>
          </div>
          <div id="modal-group-ulti-3"
            style="display:flex; align-items:center; gap:6px; background:rgba(0,0,0,0.25); padding:4px 8px; border-radius:4px; border:1px solid rgba(255,255,255,0.08); margin-top:2px;">
            <span style="font-size:0.75rem; font-weight:700; color:#c084fc; white-space:nowrap;">⚡ Ulti:</span>
            <select id="modal-select-ulti-3" class="form-control" style="font-size:0.78rem; padding:3px 6px; flex:1;"
              onchange="updateModalTeamCharValue()" disabled>
              <option value="">(Pilih karakter dulu)</option>
            </select>
          </div>
        </div>

        <!-- Preview Modal & Hidden Input -->
        <input type="hidden" id="modal-char-input" value="">
        <input type="hidden" id="modal-char-ulti" value="">
        <div id="modal-preview-team-char"
          style="display: none; padding: 4px 8px; border-radius: 4px; background: rgba(0,0,0,0.25); border: 1px dashed var(--border-color); font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
          Format Tim: <b id="modal-preview-team-char-text" style="color: var(--text-light);">-</b>
        </div>
      </div>
      <div style="display: flex; gap: 10px; margin-top: 15px;">
        <button class="btn btn-primary" style="flex:1;" onclick="saveModalChar()">Simpan</button>
        <button class="btn btn-danger" style="flex:1;" onclick="closeModal()">Batal</button>
      </div>
    </div>
  </div>

  <!-- DATALIST KARAKTER NARUTO STORM CONNECTIONS (148 KARAKTER LENGKAP & DINAMIS) -->
  <datalist id="storm-characters">
    <option value="Ashura">
    <option value="Asuma">
    <option value="Boro">
    <option value="Boruto (Chūnin)">
    <option value="Boruto (Era Boruto)">
    <option value="Boruto (Karma)">
    <option value="Chiyo">
    <option value="Chōji (Boruto)">
    <option value="Chōji (Masa Kecil)">
    <option value="Chōji (Shippuden)">
    <option value="Danzō">
    <option value="Darui">
    <option value="Deidara">
    <option value="Delta">
    <option value="Fū">
    <option value="Gaara (Boruto)">
    <option value="Gaara (Kazekage)">
    <option value="Gaara (Masa Kecil)">
    <option value="Gaara (Perang Ninja)">
    <option value="Hagoromo">
    <option value="Haku">
    <option value="Han">
    <option value="Hanabi">
    <option value="Hanzō">
    <option value="Hashirama">
    <option value="Hashirama (Edo Tensei)">
    <option value="Hidan">
    <option value="Hinata (Boruto)">
    <option value="Hinata (Masa Kecil)">
    <option value="Hinata (Shippuden)">
    <option value="Hiruzen">
    <option value="Hiruzen (Edo Tensei)">
    <option value="Indra">
    <option value="Ino (Boruto)">
    <option value="Ino (Masa Kecil)">
    <option value="Ino (Shippuden)">
    <option value="Iruka">
    <option value="Isshiki">
    <option value="Itachi">
    <option value="Itachi (Edo Tensei)">
    <option value="Jigen">
    <option value="Jiraiya">
    <option value="Jirōbō">
    <option value="Jūgo">
    <option value="Kabuto">
    <option value="Kabuto (Mode Petapa)">
    <option value="Kaguya">
    <option value="Kakashi (Masa Kecil)">
    <option value="Kakashi (Perang Ninja)">
    <option value="Kakashi (Sharingan Ganda)">
    <option value="Kakashi (Shippuden)">
    <option value="Kakuzu">
    <option value="Kankurō (Boruto)">
    <option value="Kankurō (Masa Kecil)">
    <option value="Kankurō (Shippuden)">
    <option value="Karin">
    <option value="Kawaki">
    <option value="Kiba (Boruto)">
    <option value="Kiba (Masa Kecil)">
    <option value="Kiba (Shippuden)">
    <option value="Kidōmaru">
    <option value="Killer B">
    <option value="Kimimaro">
    <option value="Kinshiki">
    <option value="Kisame">
    <option value="Koji Kashin">
    <option value="Konan">
    <option value="Konohamaru">
    <option value="Kurenai">
    <option value="Kushina">
    <option value="Madara (Edo Tensei)">
    <option value="Madara (Enam Jalan)">
    <option value="Madara (Hidup)">
    <option value="Manusia Bertopeng">
    <option value="Mecha-Naruto">
    <option value="Mei">
    <option value="Mifune">
    <option value="Might Guy">
    <option value="Might Guy (Gerbang Kedelapan)">
    <option value="Minato">
    <option value="Minato (Edo Tensei)">
    <option value="Mitsuki">
    <option value="Momoshiki">
    <option value="Mū">
    <option value="Nagato">
    <option value="Nanashi">
    <option value="Naruto (Hokage)">
    <option value="Naruto (Masa Kecil)">
    <option value="Naruto (Mode Baryon)">
    <option value="Naruto (Mode Enam Jalan)">
    <option value="Naruto (Mode Kurama)">
    <option value="Naruto (Mode Petapa)">
    <option value="Naruto (Shippuden)">
    <option value="Naruto (The Last)">
    <option value="Neji (Masa Kecil)">
    <option value="Neji (Shippuden)">
    <option value="Obito (Bertopeng)">
    <option value="Obito (Jinchūriki Sepuluh Ekor)">
    <option value="Obito (Masa Kecil)">
    <option value="Obito (Mengamuk)">
    <option value="Orochimaru">
    <option value="Ōnoki">
    <option value="Pain">
    <option value="Raikage A">
    <option value="Rasa">
    <option value="Rin">
    <option value="Rock Lee (Boruto)">
    <option value="Rock Lee (Masa Kecil)">
    <option value="Rock Lee (Shippuden)">
    <option value="Rōshi">
    <option value="Sai (Boruto)">
    <option value="Sai (Shippuden)">
    <option value="Sakon / Ukon">
    <option value="Sakura (Boruto)">
    <option value="Sakura (Masa Kecil)">
    <option value="Sakura (Shippuden)">
    <option value="Sarada">
    <option value="Sarada (Chidori)">
    <option value="Sasori">
    <option value="Sasuke (Boruto)">
    <option value="Sasuke (Mangekyō Abadi)">
    <option value="Sasuke (Masa Kecil)">
    <option value="Sasuke (Pendukung Lima Kage)">
    <option value="Sasuke (Rinnegan)">
    <option value="Sasuke (Shippuden)">
    <option value="Sasuke (The Last)">
    <option value="Sasuke (Tim Taka)">
    <option value="Shikamaru (Boruto)">
    <option value="Shikamaru (Masa Kecil)">
    <option value="Shikamaru (Shippuden)">
    <option value="Shino">
    <option value="Shisui">
    <option value="Suigetsu">
    <option value="Tayuya">
    <option value="Temari (Boruto)">
    <option value="Temari (Masa Kecil)">
    <option value="Temari (Shippuden)">
    <option value="Tenten (Boruto)">
    <option value="Tenten (Masa Kecil)">
    <option value="Tenten (Shippuden)">
    <option value="Tobi">
    <option value="Tobirama">
    <option value="Tsunade">
    <option value="Utakata">
    <option value="Yagura">
    <option value="Yamato">
    <option value="Yugito">
    <option value="Zabuza">
  </datalist>

  <script>
    // State Aplikasi
    let state = <?= $initialStateJson ?>;

    // Master Data 163 Karakter Naruto Storm Connections (Urutan In-Game Slide 1 - 8)
    const NARUTO_CHARACTERS = [{ "id": 1, "name": "Naruto Uzumaki (Mode Petapa Enam Jalan)", "slide": 1, "ultis": ["Enam Jalan: Rasen Shuriken Bola Raksasa", "Rasen Shuriken Planet Siluman Berekor"], "short1v1": "Naruto (Enam Jalan)", "shortTeam": "N6J" }, { "id": 2, "name": "Naruto Uzumaki (Mode Link Kurama)", "slide": 1, "ultis": ["Rasen Shuriken Panet Siluman Berekor"], "short1v1": "Naruto (Kurama)", "shortTeam": "NLK" }, { "id": 3, "name": "Naruto Uzumaki (Bola Siluman Berekor)", "slide": 1, "ultis": ["Bola Siluman Berekor"], "short1v1": "Naruto (Bijuu)", "shortTeam": "NBS" }, { "id": 4, "name": "Naruto Uzumaki (Teknik Petapa: Rentetan Rasengan Bola Raksasa)", "slide": 1, "ultis": ["Teknik Petapa: Rentetan Rasengan Bola Raksasa"], "short1v1": "Naruto (Petapa Rasen)", "shortTeam": "NPR" }, { "id": 5, "name": "Naruto Uzumaki (Mode Petapa)", "slide": 1, "ultis": ["Sejati: Elemen Angin - Rasen Shuriken"], "short1v1": "Naruto (Petapa)", "shortTeam": "NSP" }, { "id": 6, "name": "Naruto Uzumaki", "slide": 1, "ultis": ["Elemen Angin: Rasen Shuriken", "Rasengan Ekor Sembilan"], "short1v1": "Naruto", "shortTeam": "NRT" }, { "id": 7, "name": "Sakura Haruno", "slide": 1, "ultis": ["Mekar Penuh: Tumbukan Bunga Sakura", "Teknik Seratus Penyembuhan"], "short1v1": "Sakura", "shortTeam": "SKR" }, { "id": 8, "name": "Sai", "slide": 1, "ultis": ["Teknik Ninja: Gambar Replika Monster - Gerombolan"], "short1v1": "Sai", "shortTeam": "SAI" }, { "id": 9, "name": "Kakashi Hatake (Kedua Mata Sharingan)", "slide": 1, "ultis": ["Raikiri: Kilat", "Susanoo: Kamui Raikiri", "Kamui"], "short1v1": "Kakashi (DMS)", "shortTeam": "K2S" }, { "id": 10, "name": "Kakashi Hatake (Perang Dunia Ninja)", "slide": 1, "ultis": ["Kamui", "Gempa Petir Ganda Raikiri"], "short1v1": "Kakashi (Perang)", "shortTeam": "KPD" }, { "id": 11, "name": "Kakashi Hatake", "slide": 1, "ultis": ["Kamui", "Gempa Petir Ganda Raikiri"], "short1v1": "Kakashi", "shortTeam": "KKS" }, { "id": 12, "name": "Might Guy (Perang Dunia Ninja)", "slide": 1, "ultis": ["Merak Pagi", "Harimau Siang", "Guy Malam"], "short1v1": "Guy (Perang)", "shortTeam": "GPD" }, { "id": 13, "name": "Might Guy", "slide": 1, "ultis": ["Merak Pagi", "Harimau Siang"], "short1v1": "Guy", "shortTeam": "GUY" }, { "id": 14, "name": "Yamato", "slide": 1, "ultis": ["Gen Hokage Pertama"], "short1v1": "Yamato", "shortTeam": "YMT" }, { "id": 15, "name": "Shikamaru Nara", "slide": 1, "ultis": ["Teknik Pengumpul Bayangan"], "short1v1": "Shikamaru", "shortTeam": "SKM" }, { "id": 16, "name": "Chouji Akimichi", "slide": 1, "ultis": ["Tamparan Super"], "short1v1": "Chouji", "shortTeam": "CHJ" }, { "id": 17, "name": "Ino Yamanaka", "slide": 1, "ultis": ["Taman Bunga Tarian Kelopak"], "short1v1": "Ino", "shortTeam": "INO" }, { "id": 18, "name": "Neji Hyuga", "slide": 1, "ultis": ["8 Trigraf 64 Tapak"], "short1v1": "Neji", "shortTeam": "NEJ" }, { "id": 19, "name": "Rock Lee", "slide": 1, "ultis": ["Angin Panas Konoha"], "short1v1": "Rock Lee", "shortTeam": "LEE" }, { "id": 20, "name": "Tenten", "slide": 1, "ultis": ["Kendali Senjata: Bola Besi Raksasa"], "short1v1": "Tenten", "shortTeam": "TTN" }, { "id": 21, "name": "Kiba Inuzuka", "slide": 1, "ultis": ["Absolut: Taring ke Taring"], "short1v1": "Kiba", "shortTeam": "KBA" }, { "id": 22, "name": "Shino Aburame", "slide": 1, "ultis": ["Teknik Rahasia: Rawa Serangga"], "short1v1": "Shino", "shortTeam": "SHN" }, { "id": 23, "name": "Hinata Hyuga", "slide": 1, "ultis": ["Tinju Singa Kembar Halus"], "short1v1": "Hinata", "shortTeam": "HNT" }, { "id": 24, "name": "Asuma Sarutobi", "slide": 1, "ultis": ["Bilah Chakra: Lurus"], "short1v1": "Asuma", "shortTeam": "ASM" }, { "id": 25, "name": "Konohamaru Sarutobi", "slide": 2, "ultis": ["Teknik Harem Hebat"], "short1v1": "Konohamaru", "shortTeam": "KNH" }, { "id": 26, "name": "Shisui Uchiha", "slide": 2, "ultis": ["Kotoamatsukami: Sinar"], "short1v1": "Shisui", "shortTeam": "SSU" }, { "id": 27, "name": "Iruka Umino", "slide": 2, "ultis": ["Auman Cinta"], "short1v1": "Iruka", "shortTeam": "IRK" }, { "id": 28, "name": "Kushina Uzumaki", "slide": 2, "ultis": ["Habanero Merah Darah"], "short1v1": "Kushina", "shortTeam": "KSN" }, { "id": 29, "name": "Jiraiya", "slide": 2, "ultis": ["Rasengan Pahlawan"], "short1v1": "Jiraiya", "shortTeam": "JRY" }, { "id": 30, "name": "Hashirama Senju (Edo Tensei)", "slide": 2, "ultis": ["Teknik Petapa: Elemen Kayu - Seribu Tangan Sejati"], "short1v1": "Hashirama (Edo)", "shortTeam": "HSE" }, { "id": 31, "name": "Hashirama Senju", "slide": 2, "ultis": ["Teknik Rahasia Elemen Kayu: Pencipta Hutan Belantara"], "short1v1": "Hashirama", "shortTeam": "HSR" }, { "id": 32, "name": "Tobirama Senju", "slide": 2, "ultis": ["Elemen Air: Terjangan Naga Air", "Kertas Bom Tandem Lipat  Ganda"], "short1v1": "Tobirama", "shortTeam": "TBR" }, { "id": 33, "name": "Hiruzen Sarutobi", "slide": 2, "ultis": ["Teknik Penyegelan: Segel Malaikat Maut"], "short1v1": "Hiruzen", "shortTeam": "HRZ" }, { "id": 34, "name": "Minato Namikaze (Edo Tensei)", "slide": 2, "ultis": ["Dewa Petir Terbang: Langkah Kilat Badai Ruang Waktu, Jurus Nol"], "short1v1": "Minato (Edo)", "shortTeam": "MNE" }, { "id": 35, "name": "Minato Namikaze", "slide": 2, "ultis": ["Kilat Kuning", "Dewa Petir Terbang: Langkah Dua"], "short1v1": "Minato", "shortTeam": "MNT" }, { "id": 36, "name": "Tsunade", "slide": 2, "ultis": ["Tanah Murni Kayangan"], "short1v1": "Tsunade", "shortTeam": "TSN" }, { "id": 37, "name": "Danzou Shimura", "slide": 2, "ultis": ["Izanagi"], "short1v1": "Danzou", "shortTeam": "DNZ" }, { "id": 38, "name": "Kakashi Hatake (Masa Kecil)", "slide": 2, "ultis": ["Pedang Cahaya Putih"], "short1v1": "Kakashi (Kecil)", "shortTeam": "KKC" }, { "id": 39, "name": "Obito Uchiha (Masa Kecil)", "slide": 2, "ultis": ["Elemen Api: Teknik Api Phoenix"], "short1v1": "Obito (Kecil)", "shortTeam": "OBC" }, { "id": 40, "name": "Rin Nohara", "slide": 2, "ultis": ["Amukan Ekor Tiga"], "short1v1": "Rin", "shortTeam": "RIN" }, { "id": 41, "name": "Naruto Uzumaki (The Last)", "slide": 2, "ultis": ["Kombo 5.000 Naruto Uzumaki"], "short1v1": "Naruto (The Last)", "shortTeam": "NTL" }, { "id": 42, "name": "Sasuke Uchiha (The Last)", "slide": 2, "ultis": ["Chidori: Penghancur Bintang"], "short1v1": "Sasuke (The Last)", "shortTeam": "STL" }, { "id": 43, "name": "Hanabi Hyuga", "slide": 2, "ultis": ["Aliran Hanabi: 8 Trigraf 64 Tapak"], "short1v1": "Hanabi", "shortTeam": "HNB" }, { "id": 44, "name": "Hanabi Hyuga (Shippuden)", "slide": 2, "ultis": ["Aliran Hanabi: 8 Trigraf 64 Tapak"], "short1v1": "Hanabi (Shippuden)", "shortTeam": "HNS" }, { "id": 45, "name": "Robot Naruto", "slide": 2, "ultis": ["Meriam Siluman Berekor"], "short1v1": "Robot Naruto", "shortTeam": "RBN" }, { "id": 46, "name": "Kurenai", "slide": 2, "ultis": ["Ilusi Iblis: Ikatan Pohon Kemarian - Tebaran Kelopak"], "short1v1": "Kurenai", "shortTeam": "KRN" }, { "id": 47, "name": "Sasuke Uchiha (Rinne Sharingan)", "slide": 3, "ultis": ["Takemikazuchi", "Elemen Kobaran: Susanoo Kagutsuchi"], "short1v1": "Sasuke (Rinnegan)", "shortTeam": "SRN" }, { "id": 48, "name": "Sasuke Uchiha (Mangekyou Sharingan Abadi)", "slide": 3, "ultis": ["Pedang Kagutsuchi", "Elemen Kobaran: Susanoo Kagutsuchi"], "short1v1": "Sasuke (EMS)", "shortTeam": "SEA" }, { "id": 49, "name": "Sasuke Uchiha (Pertemuan Lima Kage)", "slide": 3, "ultis": ["Susanoo"], "short1v1": "Sasuke (5 Kage)", "shortTeam": "S5K" }, { "id": 50, "name": "Sasuke Uchiha (Taka)", "slide": 3, "ultis": ["Amaterasu"], "short1v1": "Sasuke (Taka)", "shortTeam": "STK" }, { "id": 51, "name": "Sasuke Uchiha", "slide": 3, "ultis": ["Kirin", "Tombak Chidori"], "short1v1": "Sasuke", "shortTeam": "SSK" }, { "id": 52, "name": "Orochimaru", "slide": 3, "ultis": ["Teknik Pawang Ular Besar"], "short1v1": "Orochimaru", "shortTeam": "ORC" }, { "id": 53, "name": "Kabuto Yakushi (Mode Petapa)", "slide": 3, "ultis": ["Teknik Petapa: Reinkarnasi Anorganik"], "short1v1": "Kabuto (Petapa)", "shortTeam": "KBP" }, { "id": 54, "name": "Kabuto Yakushi (Jubah Ular)", "slide": 3, "ultis": ["Pemanggilan: Edo Tensei"], "short1v1": "Kabuto (Ular)", "shortTeam": "KBU" }, { "id": 55, "name": "Kabuto Yakushi", "slide": 3, "ultis": ["Teknik Terlarang: Teknik Jiwa Mati"], "short1v1": "Kabuto", "shortTeam": "KBT" }, { "id": 56, "name": "Suigetsu Hozuki", "slide": 3, "ultis": ["Jeram: Teknik Transformasi Air"], "short1v1": "Suigetsu", "shortTeam": "SGT" }, { "id": 57, "name": "Juugo", "slide": 3, "ultis": ["Tinju Piston: Jurus Nol"], "short1v1": "Juugo", "shortTeam": "JUG" }, { "id": 58, "name": "Karin", "slide": 3, "ultis": ["Tamparan Ekstrem"], "short1v1": "Karin", "shortTeam": "KRN" }, { "id": 59, "name": "Kimimaro", "slide": 3, "ultis": ["Tarian Tunas Pakis"], "short1v1": "Kimimaro", "shortTeam": "KMM" }, { "id": 60, "name": "Jiroubou", "slide": 3, "ultis": ["Tinju Arahat"], "short1v1": "Jiroubou", "shortTeam": "JRB" }, { "id": 61, "name": "Kidoumaru", "slide": 3, "ultis": ["Panah Laba-Laba: Koyak"], "short1v1": "Kidoumaru", "shortTeam": "KDM" }, { "id": 62, "name": "Sakon/Ukon", "slide": 3, "ultis": ["Rentetan Tinju: Rakshasa"], "short1v1": "Sakon/Ukon", "shortTeam": "SKN" }, { "id": 63, "name": "Tayuya", "slide": 3, "ultis": ["Seruling Setan: Rantai Suara Ilusi"], "short1v1": "Tayuya", "shortTeam": "TYY" }, { "id": 64, "name": "Gaara (Perang Dunia Ninja)", "slide": 4, "ultis": ["Makam Gurun", "Peti Segel Pasir", "Serangan Tombak Penjara Pasir"], "short1v1": "Gaara (Perang)", "shortTeam": "GPD" }, { "id": 65, "name": "Gaara (Pertemuan Lima Kage)", "slide": 4, "ultis": ["Peti Segel Pasir", "Serangan Tombak Penjara Pasir", "Makam Gurun"], "short1v1": "Gaara (5 Kage)", "shortTeam": "G5K" }, { "id": 66, "name": "Gaara", "slide": 4, "ultis": ["Peti Segel Pasir", "Serangan Tombak Penjara Pasir", "Makam Gurun"], "short1v1": "Gaara", "shortTeam": "GRA" }, { "id": 67, "name": "Kankurou", "slide": 4, "ultis": ["Teknik Hitam Rahasia: 3 Tragedi", "Teknik Hitam Rahasia: Pentas Kalajengking Api"], "short1v1": "Kankurou", "shortTeam": "KNK" }, { "id": 68, "name": "Temari", "slide": 4, "ultis": ["Bantingan Topan"], "short1v1": "Temari", "shortTeam": "TMR" }, { "id": 69, "name": "Raikage: A", "slide": 4, "ultis": ["Bom Liger", "Lightning Straight"], "short1v1": "Raikage A", "shortTeam": "RKA" }, { "id": 70, "name": "Killer Bee (Samehada)", "slide": 4, "ultis": ["Lariat"], "short1v1": "Killer Bee (Samehada)", "shortTeam": "KBS" }, { "id": 71, "name": "Killer Bee", "slide": 4, "ultis": ["Lariat"], "short1v1": "Killer Bee", "shortTeam": "KBE" }, { "id": 72, "name": "Darui", "slide": 4, "ultis": ["Elemen Badai: Hitam Berburu"], "short1v1": "Darui", "shortTeam": "DRI" }, { "id": 73, "name": "Oonoki", "slide": 4, "ultis": ["Elemen Debu: Teknik Belah Dunia Purba"], "short1v1": "Oonoki", "shortTeam": "ONK" }, { "id": 74, "name": "Mei Terumi", "slide": 4, "ultis": ["Elemen Korosi: Asam Pelarut"], "short1v1": "Mei", "shortTeam": "MEI" }, { "id": 75, "name": "Mifune", "slide": 4, "ultis": ["Tebas Wujud"], "short1v1": "Mifune", "shortTeam": "MFN" }, { "id": 76, "name": "Yugito Nii", "slide": 4, "ultis": ["Mangkuk Api Kucing"], "short1v1": "Yugito", "shortTeam": "YGT" }, { "id": 77, "name": "Yagura", "slide": 4, "ultis": ["Hempasan Laut Badai"], "short1v1": "Yagura", "shortTeam": "YGR" }, { "id": 78, "name": "Roushi", "slide": 4, "ultis": ["Letusan Sungai Berapi"], "short1v1": "Roushi", "shortTeam": "RSH" }, { "id": 79, "name": "Han", "slide": 4, "ultis": ["Melompat Lima Gunung"], "short1v1": "Han", "shortTeam": "HAN" }, { "id": 80, "name": "Utakata", "slide": 4, "ultis": ["Muatan Gelembung"], "short1v1": "Utakata", "shortTeam": "UTK" }, { "id": 81, "name": "Fuu", "slide": 4, "ultis": ["Ledakan Sisik"], "short1v1": "Fuu", "shortTeam": "FUU" }, { "id": 82, "name": "Kazekage 4: Rasa", "slide": 4, "ultis": ["Elemen Magnet: Pemakaman Pasir Emas"], "short1v1": "Rasa (Kazekage 4)", "shortTeam": "KZ4" }, { "id": 83, "name": "Raikage 3", "slide": 4, "ultis": ["Tikaman Neraka Satu Jari: Ekstrem"], "short1v1": "Raikage 3", "shortTeam": "RK3" }, { "id": 84, "name": "Tsuchikage 2", "slide": 4, "ultis": ["Elemen Debu: Teknik Pemisah Belah Dunia Purba"], "short1v1": "Tsuchikage 2", "shortTeam": "TC2" }, { "id": 85, "name": "Mizukage 2", "slide": 4, "ultis": ["Teknik Pistol Air"], "short1v1": "Mizukage 2", "shortTeam": "MZ2" }, { "id": 86, "name": "Hanzou", "slide": 4, "ultis": ["Teknik Ninja: Kabut Sabit"], "short1v1": "Hanzou", "shortTeam": "HNZ" }, { "id": 87, "name": "Nenek Chiyo", "slide": 4, "ultis": ["3 Harta Isap Lebur"], "short1v1": "Nenek Chiyo", "shortTeam": "CHY" }, { "id": 88, "name": "Madara Uchiha (Enam Jalan)", "slide": 5, "ultis": ["Meteor Cakrawala"], "short1v1": "Madara (Enam Jalan)", "shortTeam": "M6J" }, { "id": 89, "name": "Madara Uchiha (Edo Tensei: Lepas)", "slide": 5, "ultis": ["Bintang Pengguncang Langit", "Susanoo Sempurna"], "short1v1": "Madara (Edo)", "shortTeam": "MDE" }, { "id": 90, "name": "Madara Uchiha", "slide": 5, "ultis": ["Bintang Pengguncang Langit"], "short1v1": "Madara", "shortTeam": "MDR" }, { "id": 91, "name": "Kaguya Otsutsuki", "slide": 5, "ultis": ["Bola Maut Pencari Makna"], "short1v1": "Kaguya", "shortTeam": "KGY" }, { "id": 92, "name": "Indra Otsutsuki", "slide": 5, "ultis": ["Tujuh Petir Dewa"], "short1v1": "Indra", "shortTeam": "IND" }, { "id": 93, "name": "Asura Otsutsuki", "slide": 5, "ultis": ["Seribu Tangan Sejati: Tapak Spiral"], "short1v1": "Asura", "shortTeam": "ASR" }, { "id": 94, "name": "Obito Uchiha (Jinchuuriki Ekor Sepuluh)", "slide": 5, "ultis": ["Pedang Dewa: Pedang Nunoboko"], "short1v1": "Obito (Jinchuuriki)", "shortTeam": "OBJ" }, { "id": 95, "name": "Obito Uchiha", "slide": 5, "ultis": ["Pemanggilan: Ekor Sepuluh"], "short1v1": "Obito", "shortTeam": "OBT" }, { "id": 96, "name": "Tobi (Perang Dunia Ninja)", "slide": 5, "ultis": ["Pemanggilan: Patung Iblis Asing"], "short1v1": "Tobi (Perang)", "shortTeam": "TPD" }, { "id": 97, "name": "Tobi", "slide": 5, "ultis": ["Teknik Ladang Ranjau"], "short1v1": "Tobi", "shortTeam": "TOB" }, { "id": 98, "name": "Pria Bertopeng", "slide": 5, "ultis": ["Pemanggilan: Ekor Sembilan"], "short1v1": "Pria Bertopeng", "shortTeam": "PBM" }, { "id": 99, "name": "Obito Uchiha (Pelarian)", "slide": 5, "ultis": ["Elemen Kayu: Neraka Berontak"], "short1v1": "Obito (Pelarian)", "shortTeam": "OBP" }, { "id": 100, "name": "Pain", "slide": 5, "ultis": ["Enam Jalan Samsara"], "short1v1": "Pain", "shortTeam": "PAN" }, { "id": 101, "name": "Nagato", "slide": 5, "ultis": ["Chibaku Tensei"], "short1v1": "Nagato", "shortTeam": "NGT" }, { "id": 102, "name": "Konan", "slide": 5, "ultis": ["Tarian Kertas: Hukuman"], "short1v1": "Konan", "shortTeam": "KNN" }, { "id": 103, "name": "Itachi Uchiha", "slide": 5, "ultis": ["Pedang Totsuka", "Tsukoyomi", "Yasaka no Magatama"], "short1v1": "Itachi", "shortTeam": "ITC" }, { "id": 104, "name": "Kisame Hoshigaki", "slide": 5, "ultis": ["Elemen Air: Terjangan Ombak", "Elemen Air: Teknik Peluru Hiu Besar"], "short1v1": "Kisame", "shortTeam": "KSM" }, { "id": 105, "name": "Kakuzu", "slide": 5, "ultis": ["Dendam Bumi: Tembakan Terakhir"], "short1v1": "Kakuzu", "shortTeam": "KKZ" }, { "id": 106, "name": "Hidan", "slide": 5, "ultis": ["Teknik Kutukan: Kuasa Darah Maut"], "short1v1": "Hidan", "shortTeam": "HDN" }, { "id": 107, "name": "Deidara", "slide": 5, "ultis": ["C3"], "short1v1": "Deidara", "shortTeam": "DDR" }, { "id": 108, "name": "Sasori", "slide": 5, "ultis": ["Dunia Pasir Besi"], "short1v1": "Sasori", "shortTeam": "SSR" }, { "id": 109, "name": "Hagoromo Otsutsuki", "slide": 5, "ultis": ["Cahaya Petapa Halilintar"], "short1v1": "Hagoromo", "shortTeam": "HGR" }, { "id": 110, "name": "Naruto Uzumaki (Masa Kecil)", "slide": 6, "ultis": ["Kombo 2.000 Naruto Uzumaki"], "short1v1": "Naruto (Kecil)", "shortTeam": "NKC" }, { "id": 111, "name": "Sasuke Uchiha (Masa Kecil - Pakaian Akhir)", "slide": 6, "ultis": ["Chidori"], "short1v1": "Sasuke (Kecil Akhir)", "shortTeam": "SKA" }, { "id": 112, "name": "Sasuke Uchiha (Masa Kecil)", "slide": 6, "ultis": ["Chidori"], "short1v1": "Sasuke (Kecil)", "shortTeam": "SKC" }, { "id": 113, "name": "Sakura Haruno (Masa Kecil)", "slide": 6, "ultis": ["Sakura Agung"], "short1v1": "Sakura (Kecil)", "shortTeam": "SKK" }, { "id": 114, "name": "Shikamaru Nara (Masa Kecil)", "slide": 6, "ultis": ["Teknik Ninja: Jurus Bayangan Cekik"], "short1v1": "Shikamaru (Kecil)", "shortTeam": "SMC" }, { "id": 115, "name": "Chouji Akimichi (Masa Kecil)", "slide": 6, "ultis": ["Teknik Super Besar"], "short1v1": "Chouji (Kecil)", "shortTeam": "CJC" }, { "id": 116, "name": "Ino Yamanaka (Masa Kecil)", "slide": 6, "ultis": ["Teknik Ninja: Seribu Taburan Bunga"], "short1v1": "Ino (Kecil)", "shortTeam": "INC" }, { "id": 117, "name": "Neji Hyuga (Masa Kecil)", "slide": 6, "ultis": ["8 Trigraf 64 Tapak"], "short1v1": "Neji (Kecil)", "shortTeam": "NJC" }, { "id": 118, "name": "Rock Lee (Masa Kecil)", "slide": 6, "ultis": ["Tangkai Teratai"], "short1v1": "Rock Lee (Kecil)", "shortTeam": "LKC" }, { "id": 119, "name": "Tenten (Masa Kecil)", "slide": 6, "ultis": ["Kendali Senjata: Seribu Amukan Pedang"], "short1v1": "Tenten (Kecil)", "shortTeam": "TTC" }, { "id": 120, "name": "Kiba Inuzuka (Masa Kecil)", "slide": 6, "ultis": ["Taring Serigala"], "short1v1": "Kiba (Kecil)", "shortTeam": "KBC" }, { "id": 121, "name": "Shino Aburame (Masa Kecil)", "slide": 6, "ultis": ["Serangga Parasit: Lonceng"], "short1v1": "Shino (Kecil)", "shortTeam": "SHC" }, { "id": 122, "name": "Hinata Hyuga (Masa Kecil)", "slide": 6, "ultis": ["8 Trigraf Pelindung 64 Tapak"], "short1v1": "Hinata (Kecil)", "shortTeam": "HNC" }, { "id": 123, "name": "Gaara (Masa Kecil)", "slide": 6, "ultis": ["Makam Agung Arus Pasir"], "short1v1": "Gaara (Kecil)", "shortTeam": "GKC" }, { "id": 124, "name": "Kankurou (Masa Kecil)", "slide": 6, "ultis": ["Teknik Hitam Rahasia: Mekanisme Sekali Basmi"], "short1v1": "Kankurou (Kecil)", "shortTeam": "KKC" }, { "id": 125, "name": "Temari (Masa Kecil)", "slide": 6, "ultis": ["Pemanggilan: Tarian Penggal Cepat"], "short1v1": "Temari (Kecil)", "shortTeam": "TMC" }, { "id": 126, "name": "Zabuza Momochi", "slide": 6, "ultis": ["Membunuh Tanpa Suara"], "short1v1": "Zabuza", "shortTeam": "ZBZ" }, { "id": 127, "name": "Haku", "slide": 6, "ultis": ["Teknik Rahasia: Cermin Kristal Es"], "short1v1": "Haku", "shortTeam": "HAK" }, { "id": 128, "name": "Boruto Uzumaki \"Karma\"", "slide": 7, "ultis": ["\"Karma\" Rasengan"], "short1v1": "Boruto (Karma)", "shortTeam": "BKM" }, { "id": 129, "name": "Boruto Uzumaki (Ujian Chuunin)", "slide": 7, "ultis": ["Spiral Boruto Storm"], "short1v1": "Boruto (Chuunin)", "shortTeam": "BCN" }, { "id": 130, "name": "Boruto Uzumaki (Alat Ilmiah Ninja)", "slide": 7, "ultis": ["Overwind Ride"], "short1v1": "Boruto (Alat Ilmiah)", "shortTeam": "BAI" }, { "id": 131, "name": "Boruto Uzumaki", "slide": 7, "ultis": ["Boruto Strike"], "short1v1": "Boruto", "shortTeam": "BRT" }, { "id": 132, "name": "Sarada Uchiha (Chidori)", "slide": 7, "ultis": ["Penghancur Langit Hujan Sore", "Tarian Bunga Chidori"], "short1v1": "Sarada (Chidori)", "shortTeam": "SRC" }, { "id": 133, "name": "Sarada Uchiha", "slide": 7, "ultis": ["Formasi Shuriken Uchiha: Petir Musim Semi"], "short1v1": "Sarada", "shortTeam": "SRD" }, { "id": 134, "name": "Mitsuki", "slide": 7, "ultis": ["Teknik Petapa: Petir Ular Agung - Ular Putih", "Ular Liar"], "short1v1": "Mitsuki", "shortTeam": "MTK" }, { "id": 135, "name": "Kawaki", "slide": 7, "ultis": ["\"Karma\" Ombak Pemakaman Besar"], "short1v1": "Kawaki", "shortTeam": "KWK" }, { "id": 136, "name": "Naruto Uzumaki (Mode Barion)", "slide": 7, "ultis": ["Rasengan Barion: Hujaman Langit"], "short1v1": "Naruto (Barion)", "shortTeam": "NBR" }, { "id": 137, "name": "Naruto Uzumaki (Boruto)", "slide": 7, "ultis": ["Sejati: Rasen Shuriken Siluman Berekor"], "short1v1": "Naruto (Boruto)", "shortTeam": "NBT" }, { "id": 138, "name": "Sasuke Uchiha (Bayangan Pendukung)", "slide": 7, "ultis": ["Chibaku Tensei: Kirin Kembar"], "short1v1": "Sasuke (Bayangan)", "shortTeam": "SBP" }, { "id": 139, "name": "Sasuke Uchiha (Boruto)", "slide": 7, "ultis": ["Chibaku Tensei: Langit Runtuh"], "short1v1": "Sasuke (Boruto)", "shortTeam": "SBT" }, { "id": 140, "name": "Sakura Haruno (Boruto)", "slide": 7, "ultis": ["Mekar Penuh: Tumbukan Bunga Sakura", "Teknik Seratus Penyembuhan"], "short1v1": "Sakura (Boruto)", "shortTeam": "SKB" }, { "id": 141, "name": "Hinata Hyuga (Boruto)", "slide": 7, "ultis": ["Tinju Singa Kembar Halus"], "short1v1": "Hinata (Boruto)", "shortTeam": "HNB" }, { "id": 142, "name": "Shikamaru Nara (Boruto)", "slide": 7, "ultis": ["Teknik Pengumpul Bayangan"], "short1v1": "Shikamaru (Boruto)", "shortTeam": "SMB" }, { "id": 143, "name": "Chouji Akimichi (Boruto)", "slide": 7, "ultis": ["Tamparan Super"], "short1v1": "Chouji (Boruto)", "shortTeam": "CJB" }, { "id": 144, "name": "Ino Yamanaka (Boruto)", "slide": 7, "ultis": ["Taman Bunga Tarian Kelopak"], "short1v1": "Ino (Boruto)", "shortTeam": "INB" }, { "id": 145, "name": "Sai Yamanaka (Boruto)", "slide": 7, "ultis": ["Teknik Ninja: Gambar Replika Monster - Gerombolan"], "short1v1": "Sai (Boruto)", "shortTeam": "SAB" }, { "id": 146, "name": "Rock Lee (Boruto)", "slide": 7, "ultis": ["Angin Panas Konoha"], "short1v1": "Rock Lee (Boruto)", "shortTeam": "LBT" }, { "id": 147, "name": "Tenten (Boruto)", "slide": 7, "ultis": ["Kendali Senjata: Bola Besi Raksasa"], "short1v1": "Tenten (Boruto)", "shortTeam": "TTB" }, { "id": 148, "name": "Kiba Inuzuka (Boruto)", "slide": 7, "ultis": ["Taring Serigala"], "short1v1": "Kiba (Boruto)", "shortTeam": "KBB" }, { "id": 149, "name": "Shino Aburame (Boruto)", "slide": 7, "ultis": ["Teknik Rahasia: Rawa Serangga"], "short1v1": "Shino (Boruto)", "shortTeam": "SHB" }, { "id": 150, "name": "Kawaki (Evolusi \"Karma\")", "slide": 7, "ultis": ["Peluru Pelarungan Kekaisaran"], "short1v1": "Kawaki (Karma)", "shortTeam": "KWE" }, { "id": 151, "name": "Boruto Uzumaki (Evolusi \"Karma\")", "slide": 7, "ultis": ["Rasengan Bola Raksasa: Penghancur Langit"], "short1v1": "Boruto (Karma 2)", "shortTeam": "BKE" }, { "id": 152, "name": "Gaara (Boruto)", "slide": 8, "ultis": ["Makam Gurun", "Peti Segel Pasir", "Serangan Tombak Penjara Pasir"], "short1v1": "Gaara (Boruto)", "shortTeam": "GBT" }, { "id": 153, "name": "Kankurou (Boruto)", "slide": 8, "ultis": ["Teknik Hitam Rahasia: 3 Tragedi", "Teknik Hitam Rahasia: Pentas Kalajengking Api"], "short1v1": "Kankurou (Boruto)", "shortTeam": "KNB" }, { "id": 154, "name": "Temari Nara (Boruto)", "slide": 8, "ultis": ["Bantingan Topan"], "short1v1": "Temari (Boruto)", "shortTeam": "TMB" }, { "id": 155, "name": "Darui (Boruto)", "slide": 8, "ultis": ["Elemen Badai: Hitam Berburu"], "short1v1": "Darui (Boruto)", "shortTeam": "DRB" }, { "id": 156, "name": "Momoshiki Otsutsuki", "slide": 8, "ultis": ["3 Dewa Setan"], "short1v1": "Momoshiki", "shortTeam": "MMS" }, { "id": 157, "name": "Kinshiki Otsutsuki", "slide": 8, "ultis": ["Amukan Marah"], "short1v1": "Kinshiki", "shortTeam": "KNS" }, { "id": 158, "name": "Jigen", "slide": 8, "ultis": ["Sukunahikona: Kotak Kubah Jatuh"], "short1v1": "Jigen", "shortTeam": "JGN" }, { "id": 159, "name": "Koji Kashin", "slide": 8, "ultis": ["Teknik Petapa: Kipas Lima"], "short1v1": "Koji Kashin", "shortTeam": "KOJ" }, { "id": 160, "name": "Delta", "slide": 8, "ultis": ["Aerobatic Strike"], "short1v1": "Delta", "shortTeam": "DLT" }, { "id": 161, "name": "Boro", "slide": 8, "ultis": ["Ledakan Mesiu Bom Besar"], "short1v1": "Boro", "shortTeam": "BOR" }, { "id": 162, "name": "Isshiki Otsutsuki", "slide": 8, "ultis": ["Daikokuten Sukunahikona Ambruk Ganda"], "short1v1": "Isshiki", "shortTeam": "ISK" }, { "id": 163, "name": "Nanashi Uchiha", "slide": 8, "ultis": ["8.000 Tombak: Api Ganas Yang Memukau"], "short1v1": "Nanashi", "shortTeam": "NNS" }];

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

    // URUTAN KARAKTER BERDASARKAN ABJAD (A - Z)
    const ALPHABETICAL_CHARS = [...NARUTO_CHARACTERS].sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }));

    // ==========================================
    // ATURAN PEMBATASAN KARAKTER (BANNED & VIP)
    // ==========================================
    // 1. CHAR BANNED ALL TIER: Konohamaru (ID 25)
    const BANNED_CHAR_IDS = [25];

    // 2. CHAR KHUSUS VIP/SROBOT (Tidak bisa dipilih oleh user FREE):
    // Hanzou (86), All Kiba (21, 120, 148), All Lee (19, 118, 146), Shisui (26),
    // All Sai (8, 145), Hashirama Edo Tensei (30), Minato Edo (34), Ino Kecil (116),
    // Kakashi Kilat/DMS (9), Sasuke Kirin (51), Guy Malam (12), Kabuto Enam Jalan/Petapa (53), Mitsuki (134)
    const VIP_EXCLUSIVE_CHAR_IDS = [8, 9, 12, 19, 21, 26, 30, 34, 51, 53, 86, 116, 118, 120, 134, 145, 146, 148];

    function isCharBanned(c) {
      if (!c) return false;
      if (typeof c === 'number') return BANNED_CHAR_IDS.includes(c);
      const obj = (typeof c === 'object') ? c : findNarutoChar(c);
      if (obj && obj.id && BANNED_CHAR_IDS.includes(obj.id)) return true;
      const name = (typeof c === 'string' ? c : (c.name || '')).toLowerCase();
      return name.includes('konohamaru');
    }

    function isCharVipExclusive(c) {
      if (!c) return false;
      if (typeof c === 'number') return VIP_EXCLUSIVE_CHAR_IDS.includes(c);
      const obj = (typeof c === 'object') ? c : findNarutoChar(c);
      if (obj && obj.id && VIP_EXCLUSIVE_CHAR_IDS.includes(obj.id)) return true;
      const rawName = typeof c === 'string' ? c : (c.name || '');
      const name = rawName.toLowerCase();
      if (name.startsWith('hanzou')) return true;
      if (name.includes('kiba')) return true;
      if (name.includes('rock lee')) return true;
      if (name.includes('shisui')) return true;
      if (rawName === 'Sai' || name.startsWith('sai ')) return true;
      if (name.includes('hashirama') && name.includes('edo')) return true;
      if (name.includes('minato') && name.includes('edo')) return true;
      if (name.includes('ino') && name.includes('kecil') && !name.includes('shino')) return true;
      if (name.includes('kedua mata sharingan')) return true;
      if (rawName === 'Sasuke Uchiha') return true;
      if (name.startsWith('might guy') && name.includes('perang')) return true;
      if (name.includes('kabuto') && name.includes('petapa')) return true;
      if (rawName === 'Mitsuki') return true;
      return false;
    }

    function showBannedAlert(charName) {
      alert(`🚫 KARAKTER DIBANNED!\n\nKarakter "${charName}" berstatus BANNED dan tidak dapat dipilih untuk semua tier (Free, VIP, maupun Srobot).`);
    }

    function showVipOnlyAlert(charName) {
      alert(`🔒 KHUSUS VIP / SROBOT!\n\nKarakter "${charName}" khusus untuk peserta VIP & SROBOT dan tidak bisa dipilih pada jalur Free Request.\n\nSilakan mendaftar melalui jalur "DAFTAR VIP" atau "DAFTAR SROBOT" untuk memainkan karakter ini!`);
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

    function getCustomCharacters() {
      return [];
    }

    function getAllCharactersSorted() {
      return NARUTO_CHARACTERS.map(c => c.name);
    }

    function renderCharacterDatalist() {
      const all = getAllCharactersSorted();
      const datalist = document.getElementById('storm-characters');
      if (datalist) {
        datalist.innerHTML = all.map(c => `<option value="${escapeHtml(c)}">`).join('\n');
      }
    }

    // Kunci tambah karakter baru: Admin TIDAK BISA menambahkan karakter baru sembarangan
    function registerCharacter(charName) {
      return;
    }

    // === SISTEM PISAH ARENA PER MODE (1v1 / DUO / TIM) ===
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

    function syncArenaToActiveMode() {
      const curKey = getArenaKey(state.masterMode);
      if (!state[curKey] || typeof state[curKey] !== 'object') {
        state[curKey] = { p1: null, p2: null };
      }
      state.p1 = state[curKey].p1 || null;
      state.p2 = state[curKey].p2 || null;
    }

    // === SISTEM MASTER MODE (1v1 / DUO / TIM) ===
    function setMasterMode(mode) {
      // 1. Simpan pemain arena mode saat ini sebelum berpindah
      syncActiveModeToArena();

      let cleanMode = '1v1';
      const up = String(mode).toUpperCase();
      if (up === 'DUO') cleanMode = 'DUO';
      else if (up === 'TIM' || up === 'TRIO') cleanMode = 'TIM';
      else cleanMode = '1v1';

      state.masterMode = cleanMode;

      // 2. Muat pemain arena khusus mode yang dipilih
      syncArenaToActiveMode();

      // 3. Update UI tombol master & label arena
      updateMasterModeUI();

      // 4. Sesuaikan format form tambah pemain & tiket mode default
      setTeamCharMode(cleanMode.toLowerCase());
      if (typeof setPlayerTicketMode === 'function') {
        setPlayerTicketMode(cleanMode === '1v1' ? '1v1' : 'TIM');
      }

      // 5. Switch tab Top WS ke mode yang baru dipilih
      switchTopWsTab(cleanMode, false);

      // 6. Sinkronkan topRecords & topRecord aktif
      syncActiveTopRecords();

      renderAll();
      saveState();
    }

    function updateMasterModeUI() {
      const m = state.masterMode || '1v1';
      const badge = document.getElementById('master-mode-status-badge');
      const btn1v1 = document.getElementById('btn-master-1v1') || document.getElementById('btn-master-solo');
      const btnDuo = document.getElementById('btn-master-duo');
      const btnTim = document.getElementById('btn-master-tim') || document.getElementById('btn-master-trio');
      const arenaLabel = document.getElementById('arena-current-mode-label');

      if (arenaLabel) {
        arenaLabel.innerText = m;
      }

      if (badge) {
        if (m === '1v1' || m === 'SOLO') {
          badge.innerText = '1v1 (1 CHAR)';
          badge.style.background = '#0284c7';
        } else if (m === 'DUO') {
          badge.innerText = 'DUO (2 CHAR)';
          badge.style.background = '#2563eb';
        } else if (m === 'TIM' || m === 'TRIO') {
          badge.innerText = 'TIM (2-3 CHAR)';
          badge.style.background = '#7c3aed';
        }
      }

      if (btn1v1) {
        const isAct = (m === '1v1' || m === 'SOLO');
        btn1v1.className = isAct ? 'btn btn-primary' : 'btn btn-blue';
        btn1v1.style.opacity = isAct ? '1' : '0.65';
        btn1v1.style.boxShadow = isAct ? '0 0 10px rgba(2, 132, 199, 0.5)' : 'none';
      }
      if (btnDuo) {
        const isAct = (m === 'DUO');
        btnDuo.className = isAct ? 'btn btn-primary' : 'btn btn-blue';
        btnDuo.style.opacity = isAct ? '1' : '0.65';
        btnDuo.style.boxShadow = isAct ? '0 0 10px rgba(37, 99, 235, 0.5)' : 'none';
      }
      if (btnTim) {
        const isAct = (m === 'TIM' || m === 'TRIO');
        btnTim.className = isAct ? 'btn btn-primary' : 'btn btn-blue';
        btnTim.style.opacity = isAct ? '1' : '0.65';
        btnTim.style.boxShadow = isAct ? '0 0 10px rgba(124, 58, 237, 0.5)' : 'none';
      }
    }

    // === TOP WS CEKLIS & TAB (1v1 | DUO | TIM) ===
    let currentTopWsTab = '1v1';

    function getTopWsKey(mode) {
      const m = String(mode || '1v1').toUpperCase();
      if (m === 'DUO') return 'topRecordsDuo';
      if (m === 'TIM' || m === 'TRIO') return 'topRecordsTim';
      return 'topRecords1v1';
    }

    function renderTopWsCheckboxes() {
      if (!state.topWsOverlay || typeof state.topWsOverlay !== 'object') {
        state.topWsOverlay = { '1v1': true, 'DUO': true, 'TIM': true };
      }
      const c1 = document.getElementById('chk-topws-1v1');
      const c2 = document.getElementById('chk-topws-duo');
      const c3 = document.getElementById('chk-topws-tim');
      if (c1) c1.checked = state.topWsOverlay['1v1'] !== false;
      if (c2) c2.checked = state.topWsOverlay['DUO'] !== false;
      if (c3) c3.checked = state.topWsOverlay['TIM'] !== false;
    }

    function toggleTopWsOverlay(modeKey, isChecked) {
      if (!state.topWsOverlay || typeof state.topWsOverlay !== 'object') {
        state.topWsOverlay = { '1v1': true, 'DUO': true, 'TIM': true };
      }
      state.topWsOverlay[modeKey] = !!isChecked;
      saveState();
    }

    function switchTopWsTab(tab, doRender = true) {
      let cleanTab = '1v1';
      const up = String(tab).toUpperCase();
      if (up === 'DUO') cleanTab = 'DUO';
      else if (up === 'TIM' || up === 'TRIO') cleanTab = 'TIM';
      else cleanTab = '1v1';

      currentTopWsTab = cleanTab;

      ['1v1', 'DUO', 'TIM'].forEach(m => {
        const btn = document.getElementById(`btn-topws-tab-${m.toLowerCase()}`);
        if (btn) {
          if (m === cleanTab) {
            btn.className = 'btn btn-sm btn-primary';
            btn.style.opacity = '1';
            btn.style.fontWeight = '800';
          } else {
            btn.className = 'btn btn-sm btn-blue';
            btn.style.opacity = '0.7';
            btn.style.fontWeight = 'normal';
          }
        }
      });

      if (doRender) {
        renderTopRecordAdmin();
      }
    }

    function syncActiveTopRecords() {
      const mode = state.masterMode || '1v1';
      const targetKey = getTopWsKey(mode);
      let activeList = state[targetKey];

      if (!Array.isArray(activeList) || activeList.length < 3) {
        activeList = [
          { rank: 1, name: '-', char: '-', ws: 0 },
          { rank: 2, name: '-', char: '-', ws: 0 },
          { rank: 3, name: '-', char: '-', ws: 0 }
        ];
        state[targetKey] = activeList;
      }

      // Aliases sync for compatibility
      state.topRecordsSolo = state.topRecords1v1 || activeList;
      state.topRecordsTrio = state.topRecordsTim || activeList;

      state.topRecords = activeList;
      state.topRecord = activeList[0];
    }

    // === SISTEM FORMAT TIM (1v1 / DUO / TIM) ===
    let currentTeamCharMode = '1v1'; // '1v1' | 'duo' | 'tim'

    function setTeamCharMode(mode) {
      let clean = '1v1';
      const m = String(mode).toLowerCase();
      if (m === 'duo') clean = 'duo';
      else if (m === 'tim' || m === 'trio') clean = 'tim';
      else clean = '1v1';

      currentTeamCharMode = clean;

      const badge = document.getElementById('add-player-mode-badge');
      if (badge) {
        if (clean === '1v1') badge.innerText = '(Mode: 1v1 - 1 Karakter)';
        else if (clean === 'duo') badge.innerText = '(Mode: DUO - 2 Karakter)';
        else if (clean === 'tim') badge.innerText = '(Mode: TIM - 2 atau 3 Karakter)';
      }

      const g1Label = document.getElementById('label-char-1');
      const g2 = document.getElementById('group-char-2');
      const g3 = document.getElementById('group-char-3');

      if (clean === '1v1') {
        if (g1Label) g1Label.style.display = 'none';
        if (g2) g2.style.display = 'none';
        if (g3) g3.style.display = 'none';
      } else if (clean === 'duo') {
        if (g1Label) g1Label.style.display = 'block';
        if (g2) g2.style.display = 'flex';
        if (g3) g3.style.display = 'none';
      } else if (clean === 'tim') {
        if (g1Label) g1Label.style.display = 'block';
        if (g2) g2.style.display = 'flex';
        if (g3) g3.style.display = 'flex';
      }
      updateTeamCharValue();
    }

    let activePickerSlideFilter = 0; // 0 = Semua
    let activePickerSortMode = localStorage.getItem('koth_admin_char_sort') || 'slide'; // 'slide' atau 'alpha'

    function togglePickerSortMode(context, slot, mode, e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      activePickerSortMode = mode;
      localStorage.setItem('koth_admin_char_sort', mode);
      const popupId = `picker-popup-${context}-${slot}`;
      const popup = document.getElementById(popupId);
      const inputId = context === 'add' ? `input-char-${slot}` : `modal-char-input-${slot}`;
      const inputEl = document.getElementById(inputId);
      if (popup && inputEl) {
        renderCharPickerPopup(popup, slot, context, inputEl.value.trim(), activePickerSlideFilter);
      }
    }

    function openCharPicker(inputEl, slot, context) {
      closeAllCharPickers();
      const popupId = `picker-popup-${context}-${slot}`;
      const popup = document.getElementById(popupId);
      if (!popup) return;

      renderCharPickerPopup(popup, slot, context, inputEl.value.trim(), activePickerSlideFilter);
      popup.style.display = 'flex';
    }

    function handleCharInput(inputEl, slot, context) {
      const popupId = `picker-popup-${context}-${slot}`;
      const popup = document.getElementById(popupId);
      if (!popup) return;

      popup.style.display = 'flex';
      renderCharPickerPopup(popup, slot, context, inputEl.value.trim(), activePickerSlideFilter);

      const exact = findNarutoChar(inputEl.value.trim());
      if (exact) {
        updateUltiSelectForChar(slot, context, exact.name);
      }
      if (context === 'add') {
        updateTeamCharValue();
      } else {
        updateModalTeamCharValue();
      }
    }

    function filterPickerSlide(context, slot, slideNum, e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      activePickerSlideFilter = slideNum;
      const popupId = `picker-popup-${context}-${slot}`;
      const popup = document.getElementById(popupId);
      const inputId = context === 'add' ? `input-char-${slot}` : `modal-char-input-${slot}`;
      const inputEl = document.getElementById(inputId);
      if (popup && inputEl) {
        renderCharPickerPopup(popup, slot, context, inputEl.value.trim(), slideNum);
      }
    }

    function renderCharPickerPopup(popup, slot, context, query, slideFilter = 0) {
      const q = (query || '').toLowerCase().trim();

      let filtered = NARUTO_CHARACTERS.slice();
      if (activePickerSortMode === 'slide') {
        if (slideFilter > 0) {
          filtered = filtered.filter(c => c.slide === slideFilter);
        }
      } else {
        // Abjad A-Z
        filtered.sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' }));
      }

      if (q) {
        filtered = filtered.filter(c => {
          const matchName = c.name.toLowerCase().includes(q) ||
            normCharName(c.name).includes(normCharName(q)) ||
            c.short1v1.toLowerCase().includes(q) ||
            c.shortTeam.toLowerCase().includes(q);
          const matchedUlti = Array.isArray(c.ultis) ? c.ultis.find(u => u.toLowerCase().includes(q) || normCharName(u).includes(normCharName(q))) : null;
          c._matchedUlti = matchedUlti || null;
          return matchName || Boolean(matchedUlti);
        });
      } else {
        filtered.forEach(c => c._matchedUlti = null);
      }

      let sortSwitcherHtml = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px; gap:4px;">
          <span style="font-size:0.70rem; font-weight:800; color:var(--accent-gold);">📜 DAFTAR KARAKTER</span>
          <div style="display:flex; gap:3px;">
            <button type="button" class="char-slide-tab-btn ${activePickerSortMode === 'slide' ? 'active' : ''}" onmousedown="togglePickerSortMode('${context}', ${slot}, 'slide', event)">📑 Per Slide</button>
            <button type="button" class="char-slide-tab-btn ${activePickerSortMode === 'alpha' ? 'active' : ''}" onmousedown="togglePickerSortMode('${context}', ${slot}, 'alpha', event)">🔤 Abjad A-Z</button>
          </div>
        </div>
      `;

      let slideTabsHtml = '';
      if (activePickerSortMode === 'slide') {
        slideTabsHtml = `<div class="char-slide-tabs">`;
        slideTabsHtml += `<button type="button" class="char-slide-tab-btn ${slideFilter === 0 ? 'active' : ''}" onmousedown="filterPickerSlide('${context}', ${slot}, 0, event)">Semua (163)</button>`;
        for (let s = 1; s <= 8; s++) {
          slideTabsHtml += `<button type="button" class="char-slide-tab-btn ${slideFilter === s ? 'active' : ''}" onmousedown="filterPickerSlide('${context}', ${slot}, ${s}, event)">S${s}</button>`;
        }
        slideTabsHtml += `</div>`;
      } else {
        slideTabsHtml = `<div style="font-size:0.68rem; color:#929AAB; font-weight:700; padding:2px 0;">Urutan Abjad A sampai Z (${filtered.length} karakter ditemukan)</div>`;
      }

      let headerHtml = `
        <div class="char-picker-sticky-header">
          ${sortSwitcherHtml}
          ${slideTabsHtml}
        </div>
      `;

      if (filtered.length === 0) {
        popup.innerHTML = headerHtml + `
          <div style="padding: 14px; text-align: center; color: #ef4444; font-size: 0.8rem; font-weight: 600;">
            ❌ Tidak ada karakter cocok dengan "${escapeHtml(query)}"<br>
            <span style="color:#94a3b8; font-size:0.75rem;">Admin tidak bisa menambah karakter baru di luar roster resmi.</span>
          </div>
        `;
        return;
      }

      let itemsHtml = `<div style="overflow-y:auto; flex:1;">`;
      let currentGroupHeader = '';

      filtered.forEach(c => {
        if (activePickerSortMode === 'slide') {
          if (c.slide !== currentGroupHeader) {
            currentGroupHeader = c.slide;
            itemsHtml += `<div class="char-slide-group-header">⚡ SLIDE ${c.slide}</div>`;
          }
        } else {
          const firstChar = c.name.charAt(0).toUpperCase();
          if (firstChar !== currentGroupHeader) {
            currentGroupHeader = firstChar;
            itemsHtml += `<div class="char-slide-group-header">🔤 HURUF ${firstChar}</div>`;
          }
        }

        const ultiBadge = c.ultis.length > 1
          ? `<span class="badge-multi-ulti">⚡ ${c.ultis.length} Ulti</span>`
          : `<span class="badge-single-ulti">1 Ulti (Kunci)</span>`;

        const matchedUltiTag = c._matchedUlti
          ? `<span style="font-size:0.68rem; font-weight:800; color:#facc15; background:rgba(234,179,8,0.2); border:1px solid rgba(234,179,8,0.4); padding:1px 5px; border-radius:3px;">⚡ Cocok: ${escapeHtml(c._matchedUlti)}</span>`
          : '';

        const bannedTag = (typeof isCharBanned === 'function' && isCharBanned(c))
          ? `<span style="font-size:0.68rem; font-weight:800; color:#ef4444; background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); padding:1px 5px; border-radius:3px;">🚫 BANNED</span>`
          : '';

        const vipTag = (!bannedTag && typeof isCharVipExclusive === 'function' && isCharVipExclusive(c))
          ? `<span style="font-size:0.68rem; font-weight:800; color:#f59e0b; background:rgba(245,158,11,0.2); border:1px solid rgba(245,158,11,0.4); padding:1px 5px; border-radius:3px;">⭐ VIP ONLY</span>`
          : '';

        const safeName = escapeHtml(c.name).replace(/'/g, "\'");
        const safeUlti = c._matchedUlti ? escapeHtml(c._matchedUlti).replace(/'/g, "\'") : '';
        itemsHtml += `
          <div class="char-picker-item" onmousedown="selectCharFromPicker(${slot}, '${context}', '${safeName}', '${safeUlti}')">
            <div class="char-item-title">${escapeHtml(c.name)}</div>
            <div class="char-item-tags">
              <span class="badge-slide">S${c.slide}</span>
              ${bannedTag}
              ${vipTag}
              ${ultiBadge}
              ${matchedUltiTag}
            </div>
          </div>
        `;
      });
      itemsHtml += `</div>`;

      popup.innerHTML = headerHtml + itemsHtml;
    }

    function selectCharFromPicker(slot, context, charName, preselectedUlti = '') {
      const inputId = context === 'add' ? `input-char-${slot}` : `modal-char-input-${slot}`;
      const inputEl = document.getElementById(inputId);
      if (inputEl) {
        inputEl.value = charName;
      }

      updateUltiSelectForChar(slot, context, charName, preselectedUlti);
      closeAllCharPickers();

      if (context === 'add') {
        updateTeamCharValue();
      } else {
        updateModalTeamCharValue();
      }
    }

    function updateUltiSelectForChar(slot, context, charName, preselectedUlti = '') {
      const selectId = context === 'add' ? `select-ulti-${slot}` : `modal-select-ulti-${slot}`;
      const selectEl = document.getElementById(selectId);
      if (!selectEl) return;

      const charObj = findNarutoChar(charName);
      if (!charObj || !charObj.ultis || charObj.ultis.length === 0) {
        selectEl.innerHTML = '<option value="">(Pilih karakter dulu)</option>';
        selectEl.value = '';
        selectEl.disabled = true;
        return;
      }

      if (charObj.ultis.length === 1) {
        const u = charObj.ultis[0];
        selectEl.innerHTML = `<option value="${escapeHtml(u)}">${escapeHtml(u)} (Terkunci)</option>`;
        selectEl.value = u;
        selectEl.disabled = true;
      } else {
        selectEl.innerHTML = charObj.ultis.map(u => `<option value="${escapeHtml(u)}">${escapeHtml(u)}</option>`).join('');
        if (preselectedUlti && charObj.ultis.includes(preselectedUlti)) {
          selectEl.value = preselectedUlti;
        } else {
          selectEl.value = charObj.ultis[0];
        }
        selectEl.disabled = false;
      }
    }

    function closeAllCharPickers() {
      document.querySelectorAll('.char-picker-popup').forEach(p => {
        p.style.display = 'none';
      });
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.char-picker-container')) {
        closeAllCharPickers();
      }
    });

    function updateTeamCharValue() {
      const c1 = document.getElementById('input-char-1') ? document.getElementById('input-char-1').value.trim() : '';
      const c2 = document.getElementById('input-char-2') ? document.getElementById('input-char-2').value.trim() : '';
      const c3 = document.getElementById('input-char-3') ? document.getElementById('input-char-3').value.trim() : '';

      const u1 = document.getElementById('select-ulti-1') ? document.getElementById('select-ulti-1').value.trim() : '';
      const u2 = document.getElementById('select-ulti-2') ? document.getElementById('select-ulti-2').value.trim() : '';
      const u3 = document.getElementById('select-ulti-3') ? document.getElementById('select-ulti-3').value.trim() : '';

      let combinedChar = '';
      let combinedUlti = '';

      if (currentTeamCharMode === '1v1' || currentTeamCharMode === 'solo') {
        combinedChar = c1;
        combinedUlti = u1;
      } else if (currentTeamCharMode === 'duo') {
        const arrC = [c1, c2].filter(Boolean);
        const arrU = [u1, u2].filter(Boolean);
        combinedChar = arrC.join(' / ');
        combinedUlti = arrU.join(' / ');
      } else if (currentTeamCharMode === 'tim' || currentTeamCharMode === 'trio') {
        const arrC = [c1, c2, c3].filter(Boolean);
        const arrU = [u1, u2, u3].filter(Boolean);
        combinedChar = arrC.join(' / ');
        combinedUlti = arrU.join(' / ');
      }

      const hiddenChar = document.getElementById('input-char');
      if (hiddenChar) hiddenChar.value = combinedChar;
      const hiddenUlti = document.getElementById('input-ulti');
      if (hiddenUlti) hiddenUlti.value = combinedUlti;

      const previewBox = document.getElementById('preview-team-char');
      const previewText = document.getElementById('preview-team-char-text');
      if (previewBox && previewText) {
        if (currentTeamCharMode !== '1v1' && currentTeamCharMode !== 'solo' && combinedChar) {
          previewBox.style.display = 'block';
          previewText.innerText = combinedChar;
        } else {
          previewBox.style.display = 'none';
        }
      }
    }

    let modalTeamMode = '1v1';

    function setModalTeamMode(mode) {
      let clean = '1v1';
      const m = String(mode).toLowerCase();
      if (m === 'duo') clean = 'duo';
      else if (m === 'tim' || m === 'trio') clean = 'tim';
      else clean = '1v1';

      modalTeamMode = clean;
      const btn1v1 = document.getElementById('modal-btn-1v1') || document.getElementById('modal-btn-solo');
      const btnDuo = document.getElementById('modal-btn-duo');
      const btnTim = document.getElementById('modal-btn-tim') || document.getElementById('modal-btn-trio');

      const g1Label = document.getElementById('modal-label-char-1');
      const g2 = document.getElementById('modal-group-char-2');
      const g3 = document.getElementById('modal-group-char-3');

      if (btn1v1) {
        btn1v1.className = clean === '1v1' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        btn1v1.style.opacity = clean === '1v1' ? '1' : '0.7';
      }
      if (btnDuo) {
        btnDuo.className = clean === 'duo' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        btnDuo.style.opacity = clean === 'duo' ? '1' : '0.7';
      }
      if (btnTim) {
        btnTim.className = clean === 'tim' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        btnTim.style.opacity = clean === 'tim' ? '1' : '0.7';
      }

      if (clean === '1v1') {
        if (g1Label) g1Label.style.display = 'none';
        if (g2) g2.style.display = 'none';
        if (g3) g3.style.display = 'none';
      } else if (clean === 'duo') {
        if (g1Label) g1Label.style.display = 'block';
        if (g2) g2.style.display = 'flex';
        if (g3) g3.style.display = 'none';
      } else if (clean === 'tim') {
        if (g1Label) g1Label.style.display = 'block';
        if (g2) g2.style.display = 'flex';
        if (g3) g3.style.display = 'flex';
      }
      updateModalTeamCharValue();
    }

    function updateModalTeamCharValue() {
      const c1 = document.getElementById('modal-char-input-1') ? document.getElementById('modal-char-input-1').value.trim() : '';
      const c2 = document.getElementById('modal-char-input-2') ? document.getElementById('modal-char-input-2').value.trim() : '';
      const c3 = document.getElementById('modal-char-input-3') ? document.getElementById('modal-char-input-3').value.trim() : '';

      const u1 = document.getElementById('modal-select-ulti-1') ? document.getElementById('modal-select-ulti-1').value.trim() : '';
      const u2 = document.getElementById('modal-select-ulti-2') ? document.getElementById('modal-select-ulti-2').value.trim() : '';
      const u3 = document.getElementById('modal-select-ulti-3') ? document.getElementById('modal-select-ulti-3').value.trim() : '';

      let combinedChar = '';
      let combinedUlti = '';

      if (modalTeamMode === '1v1' || modalTeamMode === 'solo') {
        combinedChar = c1;
        combinedUlti = u1;
      } else if (modalTeamMode === 'duo') {
        const arrC = [c1, c2].filter(Boolean);
        const arrU = [u1, u2].filter(Boolean);
        combinedChar = arrC.join(' / ');
        combinedUlti = arrU.join(' / ');
      } else if (modalTeamMode === 'tim' || modalTeamMode === 'trio') {
        const arrC = [c1, c2, c3].filter(Boolean);
        const arrU = [u1, u2, u3].filter(Boolean);
        combinedChar = arrC.join(' / ');
        combinedUlti = arrU.join(' / ');
      }

      const hiddenChar = document.getElementById('modal-char-input');
      if (hiddenChar) hiddenChar.value = combinedChar;
      const hiddenUlti = document.getElementById('modal-char-ulti');
      if (hiddenUlti) hiddenUlti.value = combinedUlti;

      const previewBox = document.getElementById('modal-preview-team-char');
      const previewText = document.getElementById('modal-preview-team-char-text');
      if (previewBox && previewText) {
        if (modalTeamMode !== '1v1' && modalTeamMode !== 'solo' && combinedChar) {
          previewBox.style.display = 'block';
          previewText.innerText = combinedChar;
        } else {
          previewBox.style.display = 'none';
        }
      }
    }

    // === SISTEM USER TERDAFTAR SEBELUMNYA (RIWAYAT USERNAME) ===
    function getRegisteredUsersSorted() {
      const set = new Set();

      // 1. Dari state.registeredUsers (bisa format string murni atau object nama)
      if (Array.isArray(state.registeredUsers)) {
        state.registeredUsers.forEach(u => {
          const rawName = (typeof u === 'string') ? u : (u && u.name);
          if (rawName && typeof rawName === 'string' && rawName.trim() && rawName.trim() !== '-' && rawName.trim() !== 'Menunggu...') {
            set.add(rawName.trim());
          }
        });
      }

      // 2. Gabungkan pemain aktif dari p1, p2, queue, saved, dan topRecords
      const harvest = (p) => {
        if (!p || !p.name || !p.name.trim() || p.name === '-' || p.name === 'Menunggu...') return;
        set.add(p.name.trim());
      };

      harvest(state.p1);
      harvest(state.p2);
      if (state.arena1v1) { harvest(state.arena1v1.p1); harvest(state.arena1v1.p2); }
      if (state.arenaDuo) { harvest(state.arenaDuo.p1); harvest(state.arenaDuo.p2); }
      if (state.arenaTim) { harvest(state.arenaTim.p1); harvest(state.arenaTim.p2); }
      if (Array.isArray(state.queue)) state.queue.forEach(harvest);
      if (Array.isArray(state.saved)) state.saved.forEach(harvest);
      if (Array.isArray(state.topRecords)) state.topRecords.forEach(harvest);
      if (Array.isArray(state.topRecords1v1)) state.topRecords1v1.forEach(harvest);
      if (Array.isArray(state.topRecordsSolo)) state.topRecordsSolo.forEach(harvest);
      if (Array.isArray(state.topRecordsDuo)) state.topRecordsDuo.forEach(harvest);
      if (Array.isArray(state.topRecordsTim)) state.topRecordsTim.forEach(harvest);
      if (Array.isArray(state.topRecordsTrio)) state.topRecordsTrio.forEach(harvest);

      const list = Array.from(set);
      // Urutkan sesuai abjad nama pemain (A-Z)
      list.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base', numeric: true }));
      return list;
    }

    function registerUserHistory(name) {
      if (!name || !name.trim() || name.trim() === '-' || name.trim() === 'Menunggu...') return;
      const cleanName = name.trim();
      if (!Array.isArray(state.registeredUsers)) {
        state.registeredUsers = [];
      }
      const exists = state.registeredUsers.some(u => {
        const n = (typeof u === 'string') ? u : (u && u.name);
        return n && n.toLowerCase() === cleanName.toLowerCase();
      });
      if (!exists) {
        state.registeredUsers.push(cleanName);
      }
      state.registeredUsers.sort((a, b) => {
        const na = (typeof a === 'string') ? a : (a && a.name) || '';
        const nb = (typeof b === 'string') ? b : (b && b.name) || '';
        return na.localeCompare(nb, undefined, { sensitivity: 'base', numeric: true });
      });
    }

    // === SISTEM CEK DUPLIKAT PEMAIN (ANTI-DOUBLE USER) ===
    function checkDuplicatePlayer(name) {
      if (!name || typeof name !== 'string') return null;
      const clean = name.trim().toLowerCase();
      if (!clean || clean === '-' || clean === 'menunggu...' || clean === 'tba') return null;

      // 1. Cek Arena (P1 / P2 Mode Aktif & Mode Lainnya)
      const checkArenaSlot = (p, label) => {
        if (p && p.name && p.name.trim().toLowerCase() === clean) {
          return {
            location: label,
            player: p,
            message: `Pemain "${p.name}" sedang bermain di ${label}`
          };
        }
        return null;
      };

      const c1 = checkArenaSlot(state.p1, `Arena ${state.masterMode || '1v1'} (P1)`);
      if (c1) return c1;
      const c2 = checkArenaSlot(state.p2, `Arena ${state.masterMode || '1v1'} (P2)`);
      if (c2) return c2;

      const otherArenas = [
        { label: 'Arena 1v1', data: state.arena1v1 },
        { label: 'Arena DUO', data: state.arenaDuo },
        { label: 'Arena TIM', data: state.arenaTim }
      ];
      for (const a of otherArenas) {
        if (a.data) {
          const s1 = checkArenaSlot(a.data.p1, `${a.label} (P1)`);
          if (s1) return s1;
          const s2 = checkArenaSlot(a.data.p2, `${a.label} (P2)`);
          if (s2) return s2;
        }
      }

      // 2. Cek Match Queue
      if (Array.isArray(state.queue)) {
        const qIdx = state.queue.findIndex(p => p && p.name && p.name.trim().toLowerCase() === clean);
        if (qIdx !== -1) {
          const p = state.queue[qIdx];
          return {
            location: 'Match Queue',
            player: p,
            index: qIdx + 1,
            message: `Pemain "${p.name}" sudah ada di Match Queue (Urutan #${qIdx + 1}, Tier: ${getTierLabel(p.type)})`
          };
        }
      }

      // 3. Cek List Tabungan (Saved)
      if (Array.isArray(state.saved)) {
        const sIdx = state.saved.findIndex(p => p && p.name && p.name.trim().toLowerCase() === clean);
        if (sIdx !== -1) {
          const p = state.saved[sIdx];
          return {
            location: 'List Tabungan',
            player: p,
            message: `Pemain "${p.name}" sudah ada di List Tabungan (Tier: ${getTierLabel(p.type)}, Sisa: ${p.matchesLeft} Match)`
          };
        }
      }

      return null;
    }

    function updatePlayerFormValidation() {
      const input = document.getElementById('input-username');
      const warnEl = document.getElementById('username-duplicate-warning');
      const btnQueue = document.getElementById('btn-add-queue');
      const btnSaved = document.getElementById('btn-add-saved');
      if (!input) return;

      const val = input.value.trim();
      const dup = checkDuplicatePlayer(val);

      if (dup) {
        if (warnEl) {
          warnEl.style.display = 'block';
          warnEl.innerHTML = `⚠️ <b>PERINGATAN DOUBLE USER (AKAN GAGAL):</b><br>${escapeHtml(dup.message)}.<br><span style="font-size:0.75rem; font-weight:normal; opacity:0.9;">Pemain tidak bisa ditambahkan lagi karena masih aktif di antrean/tabungan.</span>`;
        }
        input.style.borderColor = '#ef4444';
        input.style.boxShadow = '0 0 0 2px rgba(239, 68, 68, 0.25)';
        if (btnQueue) {
          btnQueue.style.opacity = '0.65';
          btnQueue.title = `Pemain "${val}" sudah ada di ${dup.location}. Menambahkan akan ditolak.`;
        }
        if (btnSaved) {
          btnSaved.style.opacity = '0.65';
          btnSaved.title = `Pemain "${val}" sudah ada di ${dup.location}. Menambahkan akan ditolak.`;
        }
      } else {
        if (warnEl) {
          warnEl.style.display = 'none';
          warnEl.innerHTML = '';
        }
        input.style.borderColor = '';
        input.style.boxShadow = '';
        if (btnQueue) {
          btnQueue.style.opacity = '';
          btnQueue.title = '';
        }
        if (btnSaved) {
          btnSaved.style.opacity = '';
          btnSaved.title = '';
        }
      }
    }

    function renderRegisteredUsersDropdown() {
      const users = getRegisteredUsersSorted();
      const selectEl = document.getElementById('select-registered-user');
      const datalistEl = document.getElementById('registered-users-datalist');
      const countEl = document.getElementById('registered-user-count');

      if (countEl) {
        countEl.innerText = `${users.length} user terdaftar`;
      }

      if (datalistEl) {
        datalistEl.innerHTML = users.map(u => `<option value="${escapeHtml(u)}">`).join('\n');
      }

      if (selectEl) {
        const curVal = selectEl.value;
        let html = `<option value="">▼ PILIH DARI USER TERDAFTAR (${users.length} PEMAIN A-Z)...</option>`;
        users.forEach(u => {
          const dup = checkDuplicatePlayer(u);
          if (dup) {
            html += `<option value="${escapeHtml(u)}" style="color: #f87171; background: #2b1518;">⛔ ${escapeHtml(u)} (Ada di ${escapeHtml(dup.location)})</option>`;
          } else {
            html += `<option value="${escapeHtml(u)}">${escapeHtml(u)}</option>`;
          }
        });
        selectEl.innerHTML = html;
        if (curVal) selectEl.value = curVal;
      }
    }

    function handleSelectRegisteredUser(userName) {
      // HANYA ambil usernamenya saja, JANGAN ambil data tier dan karakter
      const usernameInput = document.getElementById('input-username');
      if (usernameInput) {
        usernameInput.value = userName || '';
        updatePlayerFormValidation();
      }
    }

    let editingTarget = null; // for modal

    let cloudMqttClient = null;
    const LOCAL_CLIENT_ID = 'adm_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
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
          lastSavedStateJson = JSON.stringify(state);
          try { localStorage.setItem('koth_naruto_state', lastSavedStateJson); } catch (e) { }
          renderAll();
        }
      };
    }

    // Undo & Redo History System
    let undoStack = [];
    let redoStack = [];
    let lastSavedStateJson = null;
    const MAX_HISTORY_STACK = 50;

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

    // Sanitize State: pastikan tier SROBOT/VIP/FREE valid dan migrasikan FAST/SROBOT++/VVIP/SB
    function sanitizeState(st) {
      if (!st || typeof st !== 'object') return false;
      let changed = false;
      const fixP = (p) => {
        if (!p) return;
        if (p.type === 'VVIP' || p.type === 'FAST' || p.type === 'SROBOT++' || p.type === 'SB') { p.type = 'SROBOT'; changed = true; }
        if (!p.type || p.type === 'SAVE' || (p.type !== 'SROBOT' && p.type !== 'VIP' && p.type !== 'FREE')) {
          p.type = 'FREE';
          changed = true;
        }
        if (!p.ticketMode) {
          if (p.char && (p.char.includes('/') || p.char.includes(',') || p.char.includes('&'))) {
            p.ticketMode = 'TIM';
          } else {
            p.ticketMode = '1v1';
          }
          changed = true;
        }
      };
      fixP(st.p1);
      fixP(st.p2);
      if (st.arena1v1) { fixP(st.arena1v1.p1); fixP(st.arena1v1.p2); }
      if (st.arenaDuo) { fixP(st.arenaDuo.p1); fixP(st.arenaDuo.p2); }
      if (st.arenaTim) { fixP(st.arenaTim.p1); fixP(st.arenaTim.p2); }
      if (Array.isArray(st.queue)) st.queue.forEach(fixP);
      if (!Array.isArray(st.pendingApprovals)) {
        st.pendingApprovals = [];
        changed = true;
      } else {
        st.pendingApprovals.forEach(fixP);
      }
      if (!st.freeHistory || typeof st.freeHistory !== 'object') {
        st.freeHistory = {};
        changed = true;
      }
      if (Array.isArray(st.saved)) {
        st.saved.forEach(p => {
          fixP(p);
          if (!p.savedAt) {
            const numId = Number(p.id);
            if (!isNaN(numId) && numId > 1600000000000 && numId < 2500000000000) {
              p.savedAt = numId;
              changed = true;
            } else {
              p.savedAt = Date.now();
              changed = true;
            }
          }
        });
      }

      if (!st.tierStatus || typeof st.tierStatus !== 'object') {
        st.tierStatus = { 'SROBOT': 'AKTIF', 'VIP': 'AKTIF', 'FREE': 'AKTIF' };
        changed = true;
      } else {
        if (st.tierStatus['SROBOT++'] || st.tierStatus.FAST || st.tierStatus.VVIP) {
          if (!st.tierStatus['SROBOT']) st.tierStatus['SROBOT'] = st.tierStatus['SROBOT++'] || st.tierStatus.FAST || st.tierStatus.VVIP;
          delete st.tierStatus['SROBOT++'];
          delete st.tierStatus.FAST;
          delete st.tierStatus.VVIP;
          changed = true;
        }
        if (!st.tierStatus.VIP) { st.tierStatus.VIP = 'AKTIF'; changed = true; }
        if (!st.tierStatus.FREE) { st.tierStatus.FREE = 'AKTIF'; changed = true; }
        ['SROBOT', 'VIP', 'FREE'].forEach(k => {
          if (st.tierStatus[k] === 'OPEN') { st.tierStatus[k] = 'AKTIF'; changed = true; }
          if (st.tierStatus[k] === 'CLOSED') { st.tierStatus[k] = 'PENUH'; changed = true; }
          if (!st.tierStatus[k]) { st.tierStatus[k] = 'AKTIF'; changed = true; }
        });
      }
      if (st.masterMode === 'SOLO') { st.masterMode = '1v1'; changed = true; }
      if (st.masterMode === 'TRIO') { st.masterMode = 'TIM'; changed = true; }
      if (!st.masterMode || !['1v1', 'DUO', 'TIM'].includes(st.masterMode)) {
        st.masterMode = '1v1';
        changed = true;
      }

      // Pisah Arena per Mode
      if (!st.arena1v1 || typeof st.arena1v1 !== 'object') {
        st.arena1v1 = st.arenaSolo || { p1: st.p1 || null, p2: st.p2 || null };
        changed = true;
      }
      if (!st.arenaDuo || typeof st.arenaDuo !== 'object') {
        st.arenaDuo = { p1: null, p2: null };
        changed = true;
      }
      if (!st.arenaTim || typeof st.arenaTim !== 'object') {
        st.arenaTim = st.arenaTrio || { p1: null, p2: null };
        changed = true;
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
        changed = true;
      } else {
        st.topRecords1v1 = normalizeRecList(st.topRecords1v1);
      }
      st.topRecordsSolo = st.topRecords1v1;

      if (!Array.isArray(st.topRecordsDuo) || st.topRecordsDuo.length < 3) {
        st.topRecordsDuo = normalizeRecList(st.topRecordsDuo);
        changed = true;
      } else {
        st.topRecordsDuo = normalizeRecList(st.topRecordsDuo);
      }

      if (!Array.isArray(st.topRecordsTim) || st.topRecordsTim.length < 3) {
        st.topRecordsTim = normalizeRecList(st.topRecordsTrio || st.topRecordsTim);
        changed = true;
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
        changed = true;
      }

      if (!Array.isArray(st.registeredUsers)) {
        st.registeredUsers = [];
        changed = true;
      }
      const normalizedUsers = [];
      st.registeredUsers.forEach(u => {
        const rawName = (typeof u === 'string') ? u : (u && u.name);
        if (rawName && typeof rawName === 'string' && rawName.trim() && rawName.trim() !== '-' && rawName.trim() !== 'Menunggu...') {
          const clean = rawName.trim();
          if (!normalizedUsers.some(x => x.toLowerCase() === clean.toLowerCase())) {
            normalizedUsers.push(clean);
          }
        }
      });
      const harvestUser = (p) => {
        if (!p || !p.name || !p.name.trim() || p.name === '-' || p.name === 'Menunggu...') return;
        const cleanName = p.name.trim();
        if (!normalizedUsers.some(x => x.toLowerCase() === cleanName.toLowerCase())) {
          normalizedUsers.push(cleanName);
          changed = true;
        }
      };
      harvestUser(st.p1);
      harvestUser(st.p2);
      if (st.arena1v1) { harvestUser(st.arena1v1.p1); harvestUser(st.arena1v1.p2); }
      if (st.arenaDuo) { harvestUser(st.arenaDuo.p1); harvestUser(st.arenaDuo.p2); }
      if (st.arenaTim) { harvestUser(st.arenaTim.p1); harvestUser(st.arenaTim.p2); }
      if (Array.isArray(st.queue)) st.queue.forEach(harvestUser);
      if (Array.isArray(st.saved)) st.saved.forEach(harvestUser);
      if (Array.isArray(st.topRecords)) st.topRecords.forEach(harvestUser);
      if (Array.isArray(st.topRecords1v1)) st.topRecords1v1.forEach(harvestUser);
      if (Array.isArray(st.topRecordsDuo)) st.topRecordsDuo.forEach(harvestUser);
      if (Array.isArray(st.topRecordsTim)) st.topRecordsTim.forEach(harvestUser);
      normalizedUsers.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base', numeric: true }));
      if (JSON.stringify(normalizedUsers) !== JSON.stringify(st.registeredUsers)) {
        st.registeredUsers = normalizedUsers;
        changed = true;
      }

      if (typeof st.vipConsecutiveCount !== 'number' || isNaN(st.vipConsecutiveCount) || st.vipConsecutiveCount < 0) {
        st.vipConsecutiveCount = 0;
        changed = true;
      } else if (st.vipConsecutiveCount > 3) {
        st.vipConsecutiveCount = 3;
        changed = true;
      }

      return changed;
    }

    // Load Data & Server Sync (MySQL + MQTT)
    let pendingSaves = 0;
    let lastLocalSaveAt = 0;

    function getAppBaseUrl() {
      if (typeof APP_BASE_URL === 'string' && APP_BASE_URL.startsWith('/')) {
        return `${window.location.origin}${APP_BASE_URL}`;
      }
      let basePath = window.location.pathname;
      basePath = basePath.replace(/\/(index|admin|overlay)(\.(html|php))?$/i, '/');
      basePath = basePath.replace(/\/admin\/?$/i, '/');
      if (!basePath.endsWith('/')) basePath += '/';
      return `${window.location.origin}${basePath}`;
    }

    function getStateApiUrl() {
      return `${getAppBaseUrl()}api/state.php?room=${encodeURIComponent(streamKey)}`;
    }

    function loadState() {
      const isHttp = window.location.protocol.startsWith('http');

      // Jika diakses via HTTP (XAMPP / Hosting), database MySQL adalah sumber kebenaran utama.
      // JANGAN timpa state yang sudah di-load oleh PHP dari MySQL dengan localStorage browser yang usang.
      if (!isHttp) {
        const data = localStorage.getItem('koth_naruto_state');
        if (data) {
          try {
            state = JSON.parse(data);
          } catch (e) { }
        }
      } else {
        // Sinkronkan state terkini dari database ke localStorage browser
        try {
          if (state && typeof state === 'object') {
            localStorage.setItem('koth_naruto_state', JSON.stringify(state));
          }
        } catch (e) { }
      }

      sanitizeState(state);
      lastSavedStateJson = JSON.stringify(state);
      renderAll();
      setupSync();

      // Initial GET dari MySQL API (sinkronisasi live dari database)
      if (isHttp) {
        const fetchStartedAt = Date.now();
        fetch(getStateApiUrl(), { cache: 'no-store' })
          .then(res => res.ok ? res.json() : null)
          .then(serverState => {
            if (serverState && typeof serverState === 'object') {
              if (pendingSaves > 0 || fetchStartedAt < lastLocalSaveAt) return;
              sanitizeState(serverState);
              state = serverState;
              lastSavedStateJson = JSON.stringify(state);
              try { localStorage.setItem('koth_naruto_state', lastSavedStateJson); } catch (e) { }
              renderAll();
            }
          })
          .catch(() => { });
      }
    }

    function toggleTierStatus(tierKey) {
      if (!state.tierStatus) {
        state.tierStatus = { 'SROBOT': 'AKTIF', 'VIP': 'AKTIF', 'FREE': 'AKTIF' };
      }
      const cur = state.tierStatus[tierKey] || 'AKTIF';
      const next = (cur === 'CLOSED' || cur === 'PENUH') ? 'AKTIF' : 'PENUH';
      if (!confirm(`Ubah status tier ${tierKey} menjadi ${next}?`)) return;
      state.tierStatus[tierKey] = next;
      saveState();
    }

    function saveState(isUndoRedo = false) {
      syncActiveModeToArena();
      syncActiveTopRecords();
      const currentStateJson = JSON.stringify(state);

      if (!isUndoRedo) {
        if (lastSavedStateJson && lastSavedStateJson !== currentStateJson) {
          undoStack.push(lastSavedStateJson);
          if (undoStack.length > MAX_HISTORY_STACK) {
            undoStack.shift();
          }
          redoStack = []; // Setiap perubahan data baru me-reset redo stack
        }
      }
      state._clientId = LOCAL_CLIENT_ID;
      state._ts = Date.now();
      const syncJson = JSON.stringify(state);
      lastSavedStateJson = syncJson;
      lastLocalSaveAt = state._ts;

      try {
        localStorage.setItem('koth_naruto_state', syncJson);
      } catch (e) { }

      // 1. BroadcastChannel: Notifikasi instan 0ms ke tab overlay & pendaftaran
      if (kothSyncChannel) {
        try {
          kothSyncChannel.postMessage({ type: 'KOTH_STATE_UPDATE', state: state, ts: state._ts, clientId: LOCAL_CLIENT_ID });
        } catch (e) { }
      }

      renderAll();

      // Cloud MQTT Sync (Real-time across Internet / TikTok Live Studio)
      if (cloudMqttClient && cloudMqttClient.connected) {
        try {
          cloudMqttClient.publish(MQTT_TOPIC, syncJson, { retain: true, qos: 0 });
        } catch (e) {
          console.warn('MQTT publish error', e);
        }
      }

      // Simpan ke MySQL melalui PHP API (XAMPP lokal & hosting)
      if (window.location.protocol.startsWith('http')) {
        pendingSaves++;
        fetch(getStateApiUrl(), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: syncJson
        })
          .catch(err => console.warn('MySQL API sync error:', err))
          .finally(() => {
            pendingSaves = Math.max(0, pendingSaves - 1);
          });
      }
    }

    function undo() {
      if (undoStack.length === 0) return;
      const previousStateJson = undoStack.pop();
      redoStack.push(JSON.stringify(state));
      if (redoStack.length > MAX_HISTORY_STACK) {
        redoStack.shift();
      }

      try {
        state = JSON.parse(previousStateJson);
      } catch (e) {
        console.error('Gagal undo:', e);
        return;
      }

      sanitizeState(state);

      saveState(true);
      showToast('↩ Perubahan dibatalkan (Undo)');
    }

    function redo() {
      if (redoStack.length === 0) return;
      const nextStateJson = redoStack.pop();
      undoStack.push(JSON.stringify(state));
      if (undoStack.length > MAX_HISTORY_STACK) {
        undoStack.shift();
      }

      try {
        state = JSON.parse(nextStateJson);
      } catch (e) {
        console.error('Gagal redo:', e);
        return;
      }

      sanitizeState(state);

      saveState(true);
      showToast('↪ Perubahan dipulihkan (Redo)');
    }

    function updateUndoRedoUI() {
      const canUndo = (undoStack.length > 0);
      const canRedo = (redoStack.length > 0);

      document.querySelectorAll('.btn-undo-action').forEach(btn => {
        btn.disabled = !canUndo;
        btn.title = canUndo ? `Undo perubahan terakhir (${undoStack.length} langkah tersimpan) [Ctrl+Z]` : 'Tidak ada riwayat untuk Undo';
      });

      document.querySelectorAll('.btn-redo-action').forEach(btn => {
        btn.disabled = !canRedo;
        btn.title = canRedo ? `Redo perubahan (${redoStack.length} langkah tersimpan) [Ctrl+Y]` : 'Tidak ada riwayat untuk Redo';
      });
    }

    function showToast(message) {
      let toast = document.getElementById('koth-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'koth-toast';
        toast.style.cssText = `
          position: fixed;
          bottom: 24px;
          right: 24px;
          z-index: 999999;
          background: #0f172a;
          color: #ffffff;
          padding: 10px 18px;
          border-radius: 8px;
          font-weight: 800;
          font-size: 0.92rem;
          box-shadow: 0 10px 25px rgba(0,0,0,0.5);
          border: 1px solid rgba(255,255,255,0.2);
          pointer-events: none;
          opacity: 0;
          transform: translateY(12px);
          transition: all 0.22s ease;
        `;
        document.body.appendChild(toast);
      }
      toast.innerText = message;
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
      if (toast._timer) clearTimeout(toast._timer);
      toast._timer = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(12px)';
      }, 2000);
    }

    function setupSync() {
      const appBaseUrl = window.location.protocol.startsWith('http')
        ? getAppBaseUrl()
        : 'http://localhost/naruto-koth/';
      let overlayUrl = `${appBaseUrl}overlay.php`;
      if (window.location.hostname.includes('kesug.com') || window.location.hostname.includes('epizy.com')) {
        overlayUrl = `https://hervandisipada.github.io/naruto-koth/overlay.html?room=${encodeURIComponent(STREAM_KEY)}`;
      }
      const adminUrl = appBaseUrl;

      const linkEl = document.getElementById('tiktok-link-display');
      if (linkEl) linkEl.innerText = overlayUrl;
      const hpEl = document.getElementById('hp-link-display');
      if (hpEl) hpEl.innerText = adminUrl;

      // 1. MQTT Cloud Realtime Sync
      if (typeof mqtt !== 'undefined') {
        try {
          cloudMqttClient = mqtt.connect('wss://broker.emqx.io:8084/mqtt', {
            clientId: LOCAL_CLIENT_ID,
            clean: true,
            reconnectPeriod: 2500
          });

          cloudMqttClient.on('connect', () => {
            console.log('Connected to Cloud Realtime Sync!');
            const badge = document.getElementById('cloud-sync-badge');
            if (badge) {
              badge.innerHTML = 'Cloud Sync: <b>Aktif</b>';
              badge.style.color = '#22c55e';
            }
            cloudMqttClient.subscribe(MQTT_TOPIC, { qos: 0 });
          });

          cloudMqttClient.on('message', (topic, payload) => {
            try {
              const remoteData = JSON.parse(payload.toString());
              // ABAIKAN PESAN DARI DIRI SENDIRI: Mencegah echo loop & glitch re-render terus menerus!
              if (remoteData && remoteData._clientId === LOCAL_CLIENT_ID) {
                return;
              }
              sanitizeState(remoteData);
              const cleanRemoteStr = JSON.stringify(remoteData);
              if (cleanRemoteStr !== JSON.stringify(state) && cleanRemoteStr !== lastSavedStateJson) {
                state = remoteData;
                lastSavedStateJson = cleanRemoteStr;
                localStorage.setItem('koth_naruto_state', cleanRemoteStr);
                renderAll();
              }
            } catch (e) { }
          });

          cloudMqttClient.on('close', () => {
            const badge = document.getElementById('cloud-sync-badge');
            if (badge) {
              badge.innerHTML = 'Cloud Sync: <b>Menghubungkan...</b>';
              badge.style.color = '#eab308';
            }
          });
        } catch (e) {
          console.warn('MQTT init failed:', e);
        }
      }

      // 2. Fallback polling MySQL API (menggantikan SSE Python agar kompatibel dengan PHP/InfinityFree)
      if (window.location.protocol.startsWith('http')) {
        setInterval(() => {
          if (document.visibilityState !== 'visible' || pendingSaves > 0) return;
          const fetchStartedAt = Date.now();
          fetch(getStateApiUrl(), { cache: 'no-store' })
            .then(res => res.ok ? res.json() : null)
            .then(data => {
              if (!data || typeof data !== 'object') return;
              if (pendingSaves > 0 || fetchStartedAt < lastLocalSaveAt) return;
              if (data._clientId === LOCAL_CLIENT_ID) return;
              if (data._ts && state._ts && data._ts < state._ts) return;
              sanitizeState(data);
              const cleanDataStr = JSON.stringify(data);
              if (cleanDataStr !== JSON.stringify(state) && cleanDataStr !== lastSavedStateJson) {
                state = data;
                lastSavedStateJson = cleanDataStr;
                localStorage.setItem('koth_naruto_state', cleanDataStr);
                renderAll();
              }
            })
            .catch(() => { });
        }, 4000);
      }

      window.addEventListener('storage', (e) => {
        if (e.key === 'koth_naruto_state' && e.newValue) {
          try {
            state = JSON.parse(e.newValue);
            sanitizeState(state);
            lastSavedStateJson = JSON.stringify(state);
            renderAll();
          } catch (err) { }
        }
      });
    }

    function copyOverlayLink() {
      const linkEl = document.getElementById('tiktok-link-display');
      let text = linkEl ? linkEl.innerText : `${getAppBaseUrl()}overlay.php`;
      if (window.location.hostname.includes('kesug.com') || window.location.hostname.includes('epizy.com')) {
        text = `https://hervandisipada.github.io/naruto-koth/overlay.html?room=${encodeURIComponent(STREAM_KEY)}`;
      }
      navigator.clipboard.writeText(text).then(() => {
        alert('Link Overlay (Bebas Blokir Cookie TikTok Studio) berhasil disalin!\nTempelkan (Paste) ke Browser Source di TikTok Live Studio:\n' + text + '\n\nResolusi Rekomendasi di TT Studio:\nLebar: 400 | Tinggi: 720 (atau 420 x 740)');
      }).catch(() => {
        prompt('Salin link ini secara manual:', text);
      });
    }

    // Switch View Tab
    function switchTab(tabName) {
      const adminV = document.getElementById('admin-view');
      if (adminV) adminV.classList.add('active');
    }

    // Helper Escape HTML
    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Helper Format Durasi Saved
    function formatSavedDuration(savedAt) {
      if (!savedAt) return '0m';
      const now = Date.now();
      const diffMs = Math.max(0, now - Number(savedAt));
      const totalSec = Math.floor(diffMs / 1000);
      const totalMin = Math.floor(totalSec / 60);
      const hours = Math.floor(totalMin / 60);
      const days = Math.floor(hours / 24);

      if (days > 0) {
        return `${days}h ${hours % 24}j`;
      }
      if (hours > 0) {
        return `${hours}j ${totalMin % 60}m`;
      }
      if (totalMin > 0) {
        return `${totalMin}m`;
      }
      return `${totalSec}d`;
    }

    // Rank Tier Prioritas: SROBOT (1) -> VIP (2) -> FREE (3)
    const tierRank = { 'SROBOT': 1, 'SB': 1, 'SROBOT++': 1, 'FAST': 1, 'VVIP': 1, 'VIP': 2, 'FREE': 3 };

    // Helper Interleave Aturan Mutlak: Maksimal 3x VIP naik arena -> 1x FREE -> 3x VIP dst
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

    function sortQueue() {
      if (!Array.isArray(state.queue)) state.queue = [];
      const fastPlayers = [];
      const vipPlayers = [];
      const freePlayers = [];

      state.queue.forEach(p => {
        const pTier = getTierLabel(p.type);
        if (pTier === 'SB') fastPlayers.push(p);
        else if (pTier === 'VIP') vipPlayers.push(p);
        else freePlayers.push(p);
      });

      const interleaved = interleaveVipFree(vipPlayers, freePlayers, state.vipConsecutiveCount || 0);
      state.queue = [...fastPlayers, ...interleaved];
    }

    function sortSaved() {
      if (!Array.isArray(state.saved)) state.saved = [];
      // Urutkan daftar Saved sesuai abjad username (A-Z)
      state.saved.sort((a, b) => {
        return (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base', numeric: true });
      });
    }

    // Handle Tambah Pemain (Dialihkan ke Sistem Pendaftaran Baru)
    function handleAddPlayer(e) {
      if (e) e.preventDefault();
      submitAdminRegistration('queue');
    }

    function handleAddPlayerDirectSave() {
      submitAdminRegistration('saved');
    }

    function saveNewPlayer(target) {
      submitAdminRegistration(target);
    }

    // Move Saved to Active Queue: MEMPERTAHANKAN TIER (SROBOT / VIP / FREE)
    function moveSavedToQueue(id, overrideType) {
      const index = state.saved.findIndex(p => String(p.id) === String(id));
      if (index !== -1) {
        const player = state.saved[index];
        const existingInQueue = state.queue.find(p => p.name && p.name.trim().toLowerCase() === player.name.trim().toLowerCase());
        if (existingInQueue) {
          alert(`❌ Gagal Menaikkan Pemain!\n\nPemain "${player.name}" sudah ada di Match Queue.`);
          return;
        }
        if (!confirm(`Naikkan [ ${player.name} ] (${getTierLabel(player.type)}) dari Tabungan ke Match Queue?`)) return;
        state.saved.splice(index, 1);
        if (overrideType) {
          player.type = overrideType;
        } else if (!player.type || player.type === 'SAVE') {
          player.type = 'FREE';
        }
        delete player.savedAt;
        state.queue.push(player);
        sortQueue();
        saveState();
      }
    }

    // Move Active Queue to Save: MEMPERTAHANKAN TIER (SROBOT / VIP / FREE)
    function moveQueueToSave(id) {
      const index = state.queue.findIndex(p => String(p.id) === String(id));
      if (index !== -1) {
        const player = state.queue[index];
        const existingInSaved = state.saved.find(p => p.name && p.name.trim().toLowerCase() === player.name.trim().toLowerCase());
        if (existingInSaved) {
          alert(`❌ Gagal Menyimpan Pemain!\n\nPemain "${player.name}" sudah ada di List Tabungan.`);
          return;
        }
        if (!confirm(`Simpan [ ${player.name} ] (${getTierLabel(player.type)}) ke List Tabungan?`)) return;
        state.queue.splice(index, 1);
        if (!player.type || player.type === 'SAVE') player.type = 'FREE';
        player.savedAt = Date.now();
        state.saved.push(player);
        sortSaved();
        saveState();
      }
    }

    // Dropdown Ganti Tier Pemain
    function changePlayerTier(id, listType, newTier) {
      const list = listType === 'queue' ? state.queue : state.saved;
      const player = list.find(p => String(p.id) === String(id));
      if (!player) return;
      if (!confirm(`Ubah tier [ ${player.name} ] menjadi ${newTier}?`)) {
        renderAll();
        return;
      }
      player.type = newTier;
      if (listType === 'queue') {
        sortQueue();
      } else {
        sortSaved();
      }
      saveState();
    }

    // Dropdown Ubah Urutan Pemain Dalam Tier (Tidak Mengganggu Tier Lain)
    function reorderPlayerWithinTier(id, listType, newPosInTier) {
      const list = listType === 'queue' ? state.queue : state.saved;
      const player = list.find(p => String(p.id) === String(id));
      if (!player) return;

      const pTier = getTierLabel(player.type);

      const fastList = list.filter(p => getTierLabel(p.type) === 'SB');
      const vipList = list.filter(p => getTierLabel(p.type) === 'VIP');
      const freeList = list.filter(p => getTierLabel(p.type) === 'FREE');

      let targetList = (pTier === 'SB') ? fastList : ((pTier === 'VIP') ? vipList : freeList);
      const curIdx = targetList.findIndex(p => String(p.id) === String(id));
      if (curIdx === -1) return;

      if (!confirm(`Ubah posisi urutan [ ${player.name} ] menjadi #${newPosInTier}?`)) {
        renderAll();
        return;
      }

      // Geser posisi dalam tier sendiri
      targetList.splice(curIdx, 1);
      targetList.splice(newPosInTier - 1, 0, player);

      if (listType === 'queue') {
        const interleaved = interleaveVipFree(vipList, freeList, state.vipConsecutiveCount || 0);
        state.queue = [...fastList, ...interleaved];
      } else {
        state.saved = [...fastList, ...vipList, ...freeList];
      }
      saveState();
    }

    // Quick Switch Tier fallback
    function togglePlayerTier(id, listType) {
      const list = listType === 'queue' ? state.queue : state.saved;
      const player = list.find(p => String(p.id) === String(id));
      if (!player) return;
      let nextTier = 'FREE';
      const curTier = getTierLabel(player.type);
      if (curTier === 'FREE') {
        nextTier = 'VIP';
      } else if (curTier === 'VIP') {
        nextTier = 'SROBOT';
      } else {
        nextTier = 'FREE';
      }
      if (!confirm(`Ubah tier [ ${player.name} ] menjadi ${nextTier}?`)) return;
      player.type = nextTier;
      if (listType === 'queue') {
        sortQueue();
      } else {
        sortSaved();
      }
      saveState();
    }

    function deletePlayer(listType, id) {
      const list = listType === 'queue' ? state.queue : state.saved;
      const p = list.find(x => String(x.id) === String(id));
      const pName = p ? p.name : 'pemain ini';
      if (!confirm(`Hapus [ ${pName} ] dari ${listType === 'queue' ? 'Match Queue' : 'List Tabungan'}?`)) return;
      if (listType === 'queue') {
        state.queue = state.queue.filter(x => String(x.id) !== String(id));
      } else if (listType === 'saved') {
        state.saved = state.saved.filter(x => String(x.id) !== String(id));
      }
      saveState();
    }

    // Filter & Tab Tiket Match (1v1 vs TIM)
    let currentQueueTicketFilter = 'ALL';
    let currentSavedModeTab = '1v1';
    let convertingPlayer = null;

    function setPlayerTicketMode(mode) {
      const clean = (mode === 'TIM' || mode === 'DUO' || mode === 'DUO_TIM') ? 'TIM' : '1v1';
      const input = document.getElementById('input-ticket-mode');
      if (input) input.value = clean;

      const btn1v1 = document.getElementById('btn-ticket-1v1');
      const btnTim = document.getElementById('btn-ticket-tim');

      if (btn1v1 && btnTim) {
        if (clean === '1v1') {
          btn1v1.className = 'btn btn-sm btn-primary';
          btn1v1.style.opacity = '1';
          btnTim.className = 'btn btn-sm btn-blue';
          btnTim.style.opacity = '0.7';
          if (typeof setTeamCharMode === 'function' && currentTeamCharMode !== '1v1') {
            setTeamCharMode('1v1');
          }
        } else {
          btnTim.className = 'btn btn-sm btn-primary';
          btnTim.style.opacity = '1';
          btn1v1.className = 'btn btn-sm btn-blue';
          btn1v1.style.opacity = '0.7';
          if (typeof setTeamCharMode === 'function' && currentTeamCharMode === '1v1') {
            const m = (state.masterMode === 'TIM' || state.masterMode === 'TRIO') ? 'tim' : 'duo';
            setTeamCharMode(m);
          }
        }
      }
    }

    function filterQueueList(filter) {
      currentQueueTicketFilter = filter;
      renderQueue();
    }

    function switchSavedModeTab(tab) {
      currentSavedModeTab = tab;
      ['1v1', 'tim', 'all'].forEach(k => {
        const btn = document.getElementById(`tab-saved-${k}`);
        if (btn) {
          if (k.toUpperCase() === tab.toUpperCase()) {
            btn.className = 'btn btn-sm btn-primary';
            btn.style.opacity = '1';
          } else {
            btn.className = 'btn btn-sm btn-blue';
            btn.style.opacity = '0.7';
          }
        }
      });
      const headerLabel = document.getElementById('saved-mode-header-label');
      if (headerLabel) {
        if (tab === '1v1') headerLabel.innerText = 'Pemain di Tabungan 1vs1:';
        else if (tab === 'TIM') headerLabel.innerText = 'Pemain di Tabungan TIM:';
        else headerLabel.innerText = 'Semua Pemain di Tabungan:';
      }
      renderSaved();
    }

    function filterSavedList(filter) {
      switchSavedModeTab(filter);
    }

    function changePlayerTicketMode(playerId, listType, newMode) {
      const list = listType === 'saved' ? state.saved : state.queue;
      const p = list.find(x => String(x.id) === String(playerId));
      if (p) {
        p.ticketMode = (newMode === 'TIM' || newMode === 'DUO') ? 'TIM' : '1v1';
        saveState();
        renderAll();
      }
    }

    // Modal Konversi Tiket Match (2x VIP 1v1 = 1x VIP TIM)
    function promptConvertPlayerTicket(playerId, listType) {
      const list = listType === 'queue' ? state.queue : state.saved;
      const p = list.find(x => String(x.id) === String(playerId));
      if (!p) return;
      convertingPlayer = { player: p, listType };
      const modal = document.getElementById('modal-convert-ticket');
      if (!modal) return;

      const pTicketMode = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'TIM' : '1v1';
      document.getElementById('convert-player-id').value = p.id;
      document.getElementById('convert-list-type').value = listType;
      document.getElementById('convert-from-mode').value = pTicketMode;
      document.getElementById('convert-player-name').innerText = p.name;
      const badgeEl = document.getElementById('convert-player-badge');
      if (badgeEl) {
        badgeEl.className = `badge badge-${getTierCls(p.type)}`;
        badgeEl.innerText = getTierLabel(p.type);
      }
      document.getElementById('convert-current-tickets').innerText = `${p.matchesLeft} Match`;
      document.getElementById('convert-current-mode').innerText = pTicketMode === '1v1' ? '1vs1' : 'TIM';

      const dirLabel = document.getElementById('convert-direction-label');
      const amountLabel = document.getElementById('convert-amount-label');
      const amountInput = document.getElementById('convert-amount-input');

      if (pTicketMode === '1v1') {
        dirLabel.innerHTML = '⚡ <b>1vs1</b> ➔ 👑 <b>TIM</b> (2 Tiket 1v1 = 1 Tiket TIM)';
        amountLabel.innerText = `Jumlah Tiket 1vs1 yang Ditukar (Maks ${p.matchesLeft}):`;
        amountInput.value = (p.matchesLeft >= 2) ? 2 : (p.matchesLeft > 0 ? p.matchesLeft : 1);
        amountInput.max = p.matchesLeft;
        amountInput.step = 2;
      } else {
        dirLabel.innerHTML = '👑 <b>TIM</b> ➔ ⚡ <b>1vs1</b> (1 Tiket TIM = 2 Tiket 1v1)';
        amountLabel.innerText = `Jumlah Tiket TIM yang Ditukar (Maks ${p.matchesLeft}):`;
        amountInput.value = (p.matchesLeft >= 1) ? 1 : 0;
        amountInput.max = p.matchesLeft;
        amountInput.step = 1;
      }
      updateConvertPreview();
      modal.classList.add('active');
    }

    function updateConvertPreview() {
      if (!convertingPlayer) return;
      const p = convertingPlayer.player;
      const pTicketMode = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'TIM' : '1v1';
      const amountInput = document.getElementById('convert-amount-input');
      const amount = parseInt(amountInput.value) || 0;
      const previewText = document.getElementById('convert-preview-text');
      const errorText = document.getElementById('convert-error-text');
      const submitBtn = document.getElementById('btn-submit-convert');
      errorText.style.display = 'none';
      submitBtn.disabled = false;

      if (amount <= 0) {
        previewText.innerText = 'Hasil: 0 Match';
        submitBtn.disabled = true;
        return;
      }
      if (amount > p.matchesLeft) {
        errorText.innerText = `❌ Melebihi sisa match pemain (${p.matchesLeft} Match).`;
        errorText.style.display = 'block';
        submitBtn.disabled = true;
        return;
      }
      if (pTicketMode === '1v1') {
        if (amount % 2 !== 0) {
          errorText.innerText = `⚠️ Konversi 1vs1 ke TIM harus kelipatan 2! (2x Match 1v1 = 1x Match TIM).`;
          errorText.style.display = 'block';
          submitBtn.disabled = true;
          return;
        }
        const resTim = amount / 2;
        const rem1v1 = p.matchesLeft - amount;
        previewText.innerHTML = `Hasil: <b>+${resTim} Match TIM</b> (Sisa di 1vs1: <b>${rem1v1} Match</b>)`;
      } else {
        const res1v1 = amount * 2;
        const remTim = p.matchesLeft - amount;
        previewText.innerHTML = `Hasil: <b>+${res1v1} Match 1vs1</b> (Sisa di TIM: <b>${remTim} Match</b>)`;
      }
    }

    function closeConvertModal() {
      const modal = document.getElementById('modal-convert-ticket');
      if (modal) modal.classList.remove('active');
      convertingPlayer = null;
    }

    function handleModalConvertBackdrop(event) {
      if (event.target && event.target.id === 'modal-convert-ticket') {
        closeConvertModal();
      }
    }

    function handleExecuteConversion(e) {
      if (e) e.preventDefault();
      if (!convertingPlayer) return;
      const { player: p, listType } = convertingPlayer;
      const pTicketMode = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'TIM' : '1v1';
      const amount = parseInt(document.getElementById('convert-amount-input').value) || 0;
      const list = listType === 'queue' ? state.queue : state.saved;
      if (amount <= 0 || amount > p.matchesLeft) return;

      if (pTicketMode === '1v1') {
        if (amount % 2 !== 0) {
          alert("Konversi 1v1 ke TIM harus dalam kelipatan 2 match!");
          return;
        }
        const convertedMatches = amount / 2;
        if (!confirm(`Konversi ${amount} Match 1vs1 milik [ ${p.name} ] menjadi ${convertedMatches} Match TIM?`)) return;
        p.matchesLeft -= amount;
        const existingTim = state.saved.find(x => x.name.trim().toLowerCase() === p.name.trim().toLowerCase() && (x.ticketMode === 'TIM' || x.ticketMode === 'DUO'));
        if (existingTim) {
          existingTim.matchesLeft += convertedMatches;
        } else {
          state.saved.push({
            id: Date.now().toString(),
            name: p.name,
            char: p.char,
            matchesLeft: convertedMatches,
            ws: 0,
            type: p.type,
            ticketMode: 'TIM',
            savedAt: Date.now()
          });
        }
        if (p.matchesLeft <= 0) {
          const idx = list.findIndex(x => String(x.id) === String(p.id));
          if (idx !== -1) list.splice(idx, 1);
        }
      } else {
        const convertedMatches = amount * 2;
        if (!confirm(`Konversi ${amount} Match TIM milik [ ${p.name} ] menjadi ${convertedMatches} Match 1vs1?`)) return;
        p.matchesLeft -= amount;
        const existing1v1 = state.saved.find(x => x.name.trim().toLowerCase() === p.name.trim().toLowerCase() && (x.ticketMode === '1v1' || !x.ticketMode));
        if (existing1v1) {
          existing1v1.matchesLeft += convertedMatches;
        } else {
          state.saved.push({
            id: Date.now().toString(),
            name: p.name,
            char: p.char,
            matchesLeft: convertedMatches,
            ws: 0,
            type: p.type,
            ticketMode: '1v1',
            savedAt: Date.now()
          });
        }
        if (p.matchesLeft <= 0) {
          const idx = list.findIndex(x => String(x.id) === String(p.id));
          if (idx !== -1) list.splice(idx, 1);
        }
      }
      saveState();
      closeConvertModal();
      renderAll();
      alert(`✅ Sukses mengonversi tiket [ ${p.name} ]!`);
    }

    // ==========================================
    // SISTEM MODAL PENDAFTARAN ADMIN (MULTI-STEP)
    // ==========================================
    let adminRegTier = 'FREE'; // 'FREE' | 'VIP' | 'SROBOT'
    let adminRegStep = 1;
    let adminRegTicketMode = '1v1'; // '1v1' | 'DUO' | 'TIM'
    let adminRegSlots = [
      { char: null, ulti: '' },
      { char: null, ulti: '' },
      { char: null, ulti: '' }
    ];
    let adminRegActiveSlot = 0; // 0, 1, 2
    let adminRegTimCount = 3; // 2 or 3 in TIM mode
    let adminRegSelectedChar = null; // backward compatibility
    let adminRegSelectedUlti = '';
    let adminRegMatchCount = 1;
    let adminRegAlphaFilter = 'ALL';

    function openAdminRegModal(tier = 'FREE') {
      const modal = document.getElementById('modal-add-player');
      if (!modal) return;

      adminRegTier = tier.toUpperCase();
      adminRegStep = 1;
      adminRegSlots = [
        { char: null, ulti: '' },
        { char: null, ulti: '' },
        { char: null, ulti: '' }
      ];
      adminRegActiveSlot = 0;
      adminRegTimCount = 3;
      adminRegSelectedChar = null;
      adminRegSelectedUlti = '';
      adminRegMatchCount = 1;
      adminRegAlphaFilter = 'ALL';
      adminRegTicketMode = (state.masterMode === 'DUO' || state.masterMode === 'TIM') ? state.masterMode : '1v1';

      // Reset form fields
      const nameInput = document.getElementById('admin-reg-name-input');
      if (nameInput) nameInput.value = '';
      const warn = document.getElementById('admin-reg-name-warning');
      if (warn) { warn.style.display = 'none'; warn.innerText = ''; }
      const charSearch = document.getElementById('admin-reg-char-search');
      if (charSearch) charSearch.value = '';
      const charBadge = document.getElementById('admin-reg-char-badge');
      if (charBadge) charBadge.innerText = 'Belum dipilih';

      renderAdminRegUserDropdown();
      selectAdminRegTier(adminRegTier, false);
      setAdminRegTicketMode(adminRegTicketMode);
      setAdminRegMatchCount(1);
      renderAdminRegAlphaBar();

      modal.classList.add('active');
      goToAdminRegStep(1);

      setTimeout(() => {
        if (nameInput) nameInput.focus();
      }, 100);
    }

    // Alias kompatibilitas
    function openAddPlayerModal() {
      openAdminRegModal('FREE');
    }

    function closeAdminRegModal() {
      const modal = document.getElementById('modal-add-player');
      if (modal) modal.classList.remove('active');
    }

    function closeAddPlayerModal() {
      closeAdminRegModal();
    }

    function handleAdminRegBackdrop(e) {
      if (e.target && e.target.id === 'modal-add-player') {
        closeAdminRegModal();
      }
    }

    function handleModalAddPlayerBackdrop(e) {
      handleAdminRegBackdrop(e);
    }

    function selectAdminRegTier(tier, resetStep = true) {
      adminRegTier = tier.toUpperCase();

      ['free', 'vip', 'srobot'].forEach(t => {
        const btn = document.getElementById(`btn-atier-${t}`);
        if (btn) {
          if (t === adminRegTier.toLowerCase()) {
            btn.classList.add('active');
          } else {
            btn.classList.remove('active');
          }
        }
      });

      const pill = document.getElementById('admin-reg-tier-pill');
      if (pill) {
        pill.innerText = adminRegTier;
        if (adminRegTier === 'FREE') {
          pill.style.cssText = 'font-size: 0.72rem; font-weight: 900; padding: 2px 8px; border-radius: 4px; background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #22c55e;';
        } else if (adminRegTier === 'VIP') {
          pill.style.cssText = 'font-size: 0.72rem; font-weight: 900; padding: 2px 8px; border-radius: 4px; background: rgba(234, 179, 8, 0.2); color: #fde047; border: 1px solid #eab308;';
        } else {
          pill.style.cssText = 'font-size: 0.72rem; font-weight: 900; padding: 2px 8px; border-radius: 4px; background: rgba(168, 85, 247, 0.2); color: #d8b4fe; border: 1px solid #a855f7;';
        }
      }

      renderAdminRegSteps();
      if (resetStep) {
        goToAdminRegStep(adminRegStep);
      }
    }

    function setAdminRegTicketMode(mode) {
      let clean = '1v1';
      if (mode === 'DUO') clean = 'DUO';
      else if (mode === 'TIM') clean = 'TIM';
      adminRegTicketMode = clean;

      const b1 = document.getElementById('btn-aticket-1v1');
      const bDuo = document.getElementById('btn-aticket-duo');
      const bTim = document.getElementById('btn-aticket-tim');

      if (b1) {
        b1.className = clean === '1v1' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        b1.style.opacity = clean === '1v1' ? '1' : '0.6';
      }
      if (bDuo) {
        bDuo.className = clean === 'DUO' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        bDuo.style.opacity = clean === 'DUO' ? '1' : '0.6';
      }
      if (bTim) {
        bTim.className = clean === 'TIM' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        bTim.style.opacity = clean === 'TIM' ? '1' : '0.6';
      }

      if (adminRegActiveSlot > 1 && clean === 'DUO') adminRegActiveSlot = 0;
      if (adminRegActiveSlot > 0 && clean === '1v1') adminRegActiveSlot = 0;

      renderAdminRegSlotTabs();
      renderAdminRegCharGrid();
    }

    function toggleAdminRegTimSlot3() {
      if (adminRegTimCount === 3) {
        adminRegTimCount = 2;
        adminRegSlots[2] = { char: null, ulti: '' };
        if (adminRegActiveSlot === 2) adminRegActiveSlot = 1;
      } else {
        adminRegTimCount = 3;
      }
      renderAdminRegSlotTabs();
      renderAdminRegCharGrid();
    }

    function selectAdminRegSlot(idx) {
      adminRegActiveSlot = idx;
      renderAdminRegSlotTabs();
      renderAdminRegCharGrid();
    }

    function renderAdminRegSlotTabs() {
      const container = document.getElementById('admin-reg-slot-tabs');
      const flexBar = document.getElementById('admin-reg-tim-flex-bar');
      const flexBtn = document.getElementById('btn-admin-tim-toggle-slot3');
      if (!container) return;

      if (adminRegTicketMode === '1v1') {
        container.style.display = 'none';
        if (flexBar) flexBar.style.display = 'none';
        const label = document.getElementById('admin-reg-char-label');
        if (label) label.innerText = 'Pilih Karakter (A-Z Master Roster):';
        return;
      }

      container.style.display = 'flex';
      const label = document.getElementById('admin-reg-char-label');
      if (label) label.innerText = `Pilih Karakter untuk Slot ${adminRegActiveSlot + 1}:`;

      if (adminRegTicketMode === 'TIM') {
        if (flexBar) flexBar.style.display = 'flex';
        if (flexBtn) {
          flexBtn.innerText = (adminRegTimCount === 3) ? '👥 Gunakan 2 Karakter Saja' : '➕ Tambah Karakter 3';
          flexBtn.className = (adminRegTimCount === 3) ? 'btn btn-sm btn-secondary' : 'btn btn-sm btn-primary';
        }
      } else {
        if (flexBar) flexBar.style.display = 'none';
      }

      const totalSlots = (adminRegTicketMode === 'DUO') ? 2 : adminRegTimCount;
      const slotDefs = [
        { label: 'Slot 1: Leader', opt: false },
        { label: (adminRegTicketMode === 'DUO') ? 'Slot 2: Support' : 'Slot 2: Support 1', opt: false },
        { label: 'Slot 3: Support 2', opt: true }
      ];

      container.innerHTML = slotDefs.slice(0, totalSlots).map((def, idx) => {
        const slot = adminRegSlots[idx];
        const isActive = (adminRegActiveSlot === idx);
        const isFilled = Boolean(slot && slot.char);
        const charName = isFilled ? slot.char.name : (def.opt ? '(Opsional / Kosong)' : '(Belum Dipilih)');
        let cls = 'char-slot-tab';
        if (isActive) cls += ' active';
        if (isFilled) cls += ' filled';

        return `
          <button type="button" class="${cls}" onclick="selectAdminRegSlot(${idx})">
            <span class="char-slot-badge">${def.label}</span>
            <span class="char-slot-name">${escapeHtml(charName)}</span>
          </button>
        `;
      }).join('');
    }

    function renderAdminRegSteps() {
      const container = document.getElementById('admin-reg-step-bar');
      if (!container) return;

      const isFree = (adminRegTier === 'FREE');
      const steps = isFree
        ? [
            { num: 1, label: 'Nama & Tier' },
            { num: 2, label: 'Karakter' },
            { num: 3, label: 'Ulti & Konfirmasi' }
          ]
        : [
            { num: 1, label: 'Nama & Tier' },
            { num: 2, label: 'Karakter' },
            { num: 3, label: 'Ulti' },
            { num: 4, label: 'Jumlah Match' },
            { num: 5, label: 'Info Pembayaran' }
          ];

      container.innerHTML = steps.map(s => {
        let cls = 'admin-reg-step-item';
        if (s.num === adminRegStep) cls += ' active';
        else if (s.num < adminRegStep) cls += ' done';
        return `
          <div class="${cls}">
            <span class="admin-reg-step-num">${s.num < adminRegStep ? '✓' : s.num}</span>
            <span>${s.label}</span>
          </div>
        `;
      }).join('');
    }

    function goToAdminRegStep(step) {
      const isFree = (adminRegTier === 'FREE');
      const maxSteps = isFree ? 3 : 5;
      if (step < 1) step = 1;
      if (step > maxSteps) step = maxSteps;
      adminRegStep = step;

      renderAdminRegSteps();

      for (let i = 1; i <= 5; i++) {
        const el = document.getElementById(`admin-reg-step-${i}`);
        if (el) {
          el.style.display = (i === step) ? 'flex' : 'none';
        }
      }

      const btnPrev = document.getElementById('btn-admin-reg-prev');
      const btnNext = document.getElementById('btn-admin-reg-next');
      const btnQueue = document.getElementById('btn-admin-reg-queue');
      const btnSaved = document.getElementById('btn-admin-reg-saved');

      if (btnPrev) btnPrev.style.display = (step > 1) ? 'inline-block' : 'none';

      const isFinalStep = (step === maxSteps);
      if (isFinalStep) {
        if (btnNext) btnNext.style.display = 'none';
        if (btnQueue) btnQueue.style.display = 'inline-block';
        if (btnSaved) btnSaved.style.display = 'inline-block';
      } else {
        if (btnNext) {
          btnNext.style.display = 'inline-block';
          if (step === 1) btnNext.innerText = 'Lanjut ke Karakter ➔';
          else if (step === 2) btnNext.innerText = 'Lanjut ke Ulti ➔';
          else if (step === 3) btnNext.innerText = 'Lanjut ke Jumlah Match ➔';
          else if (step === 4) btnNext.innerText = 'Lanjut ke Pembayaran ➔';
        }
        if (btnQueue) btnQueue.style.display = 'none';
        if (btnSaved) btnSaved.style.display = 'none';
      }

      if (step === 2) {
        renderAdminRegSlotTabs();
        renderAdminRegCharGrid();
      } else if (step === 3) {
        setupAdminRegStep3();
      } else if (step === 4) {
        updateAdminRegPrice();
      } else if (step === 5) {
        setupAdminRegStep5();
      }
    }

    function nextAdminRegStep() {
      if (adminRegStep === 1) {
        const name = (document.getElementById('admin-reg-name-input').value || '').trim();
        if (!name) {
          alert('Nama TikTok / Username pemain tidak boleh kosong!');
          return;
        }
        goToAdminRegStep(2);
      } else if (adminRegStep === 2) {
        if (adminRegTicketMode === '1v1') {
          if (!adminRegSlots[0].char) {
            alert('Karakter belum dipilih! Silakan klik salah satu karakter di daftar roster.');
            return;
          }
        } else if (adminRegTicketMode === 'DUO') {
          if (!adminRegSlots[0].char || !adminRegSlots[1].char) {
            alert('Mode DUO membutuhkan 2 karakter (Slot 1: Leader & Slot 2: Support)!');
            return;
          }
        } else if (adminRegTicketMode === 'TIM') {
          if (!adminRegSlots[0].char || !adminRegSlots[1].char) {
            alert('Mode TIM membutuhkan minimal 2 karakter (Slot 1: Leader & Slot 2: Support 1)!');
            return;
          }
        }
        goToAdminRegStep(3);
      } else if (adminRegStep === 3) {
        goToAdminRegStep(4);
      } else if (adminRegStep === 4) {
        goToAdminRegStep(5);
      }
    }

    function prevAdminRegStep() {
      goToAdminRegStep(adminRegStep - 1);
    }

    function renderAdminRegAlphaBar() {
      const container = document.getElementById('admin-reg-alpha-bar');
      if (!container) return;
      const letters = ['ALL', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];
      container.innerHTML = letters.map(l => `
        <button type="button" class="btn-alpha ${adminRegAlphaFilter === l ? 'active' : ''}" onclick="filterAdminRegAlpha('${l}')">
          ${l}
        </button>
      `).join('');
    }

    function filterAdminRegAlpha(letter) {
      adminRegAlphaFilter = letter;
      renderAdminRegAlphaBar();
      renderAdminRegCharGrid();
    }

    function renderAdminRegCharGrid() {
      const container = document.getElementById('admin-reg-char-grid');
      if (!container) return;
      const q = (document.getElementById('admin-reg-char-search').value || '').trim().toLowerCase();

      const baseChars = (typeof ALPHABETICAL_CHARS !== 'undefined' && Array.isArray(ALPHABETICAL_CHARS))
        ? ALPHABETICAL_CHARS
        : (typeof NARUTO_CHARACTERS !== 'undefined' ? [...NARUTO_CHARACTERS].sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' })) : []);
      let list = baseChars.slice();
      if (adminRegAlphaFilter !== 'ALL') {
        list = list.filter(c => c.name.toUpperCase().startsWith(adminRegAlphaFilter));
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
        container.innerHTML = `<div style="grid-column: 1 / -1; padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">Tidak ada karakter atau jurus ulti yang cocok dengan "${escapeHtml(q)}".</div>`;
        return;
      }

      const isFreeTier = (adminRegTier === 'FREE');
      const curSlotChar = adminRegSlots[adminRegActiveSlot] ? adminRegSlots[adminRegActiveSlot].char : null;

      const badge = document.getElementById('admin-reg-char-badge');
      if (badge) {
        const total = (adminRegTicketMode === '1v1') ? 1 : (adminRegTicketMode === 'DUO' ? 2 : adminRegTimCount);
        const filled = adminRegSlots.slice(0, total).filter(s => s && s.char).length;
        badge.innerText = `${filled}/${total} Terpilih`;
      }

      container.innerHTML = list.map(c => {
        const isSel = curSlotChar && curSlotChar.id === c.id;
        const isTeamOrDuo = (adminRegTicketMode === 'DUO' || adminRegTicketMode === 'TIM');
        const dupSlotIdx = isTeamOrDuo ? adminRegSlots.findIndex((s, idx) => idx !== adminRegActiveSlot && s.char && isSameBaseCharacter(s.char, c)) : -1;
        const ultiCount = (c.ultis && c.ultis.length > 0) ? c.ultis.length : 1;
        const safeUltiArg = c._matchedUlti ? escapeHtml(c._matchedUlti).replace(/'/g, "\\'") : '';
        const matchTagHtml = c._matchedUlti ? `
          <div style="font-size:0.68rem; font-weight:800; color:#facc15; background:rgba(234, 179, 8, 0.15); border:1px solid rgba(234, 179, 8, 0.35); padding:2px 6px; border-radius:4px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="Ulti Cocok: ${escapeHtml(c._matchedUlti)}">
            ⚡ ${escapeHtml(c._matchedUlti)}
          </div>
        ` : '';

        const banned = typeof isCharBanned === 'function' && isCharBanned(c);
        const isVipEx = !banned && (typeof isCharVipExclusive === 'function' && isCharVipExclusive(c));

        let cardClass = 'admin-char-card';
        let clickAttr = `onclick="selectAdminRegChar(${c.id}, '${safeUltiArg}')"`;
        let statusBadgeHtml = '';

        if (banned) {
          cardClass += ' disabled banned';
          clickAttr = `onclick="showBannedAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-banned">🚫 BANNED</span>`;
        } else if (isFreeTier && isVipEx) {
          cardClass += ' disabled vip-only';
          clickAttr = `onclick="showVipOnlyAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-vip">🔒 KHUSUS VIP</span>`;
        } else if (dupSlotIdx !== -1) {
          cardClass += ' disabled duplicate-team';
          const dupSlotName = (adminRegSlots[dupSlotIdx] && adminRegSlots[dupSlotIdx].char) ? adminRegSlots[dupSlotIdx].char.name : '';
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
          metaRight = `<span style="color:#4ade80; font-weight:800;">✅ Slot ${adminRegActiveSlot + 1}</span>`;
        }

        return `
          <div class="${cardClass}" ${clickAttr}>
            <div style="font-size:0.80rem; font-weight:800; color:#f8fafc; line-height:1.25; word-break:break-word;">${escapeHtml(c.name)}</div>
            ${matchTagHtml}
            <div style="display:flex; align-items:center; justify-content:space-between; font-size:0.65rem; color:#94a3b8; margin-top:4px;">
              <span class="char-ulti-badge">⚡ ${ultiCount} Ulti</span>
              ${metaRight}
            </div>
          </div>
        `;
      }).join('');
    }

    function selectAdminRegChar(id, preselectedUlti = '') {
      const c = NARUTO_CHARACTERS.find(x => x.id === id);
      if (!c) return;
      if (typeof isCharBanned === 'function' && isCharBanned(c)) {
        showBannedAlert(c.name);
        return;
      }
      if (adminRegTier === 'FREE' && typeof isCharVipExclusive === 'function' && isCharVipExclusive(c)) {
        showVipOnlyAlert(c.name);
        return;
      }

      const isTeamOrDuo = (adminRegTicketMode === 'DUO' || adminRegTicketMode === 'TIM');
      if (isTeamOrDuo) {
        const dupIdx = adminRegSlots.findIndex((s, idx) => idx !== adminRegActiveSlot && s.char && isSameBaseCharacter(s.char, c));
        if (dupIdx !== -1) {
          const dupName = (adminRegSlots[dupIdx] && adminRegSlots[dupIdx].char) ? adminRegSlots[dupIdx].char.name : '';
          showDuplicateTeamAlert(c.name, dupIdx + 1, dupName);
          return;
        }
      }

      let chosenUlti = preselectedUlti;
      if (!chosenUlti && c.ultis && c.ultis.length > 0) {
        chosenUlti = c.ultis[0];
      }

      adminRegSlots[adminRegActiveSlot] = {
        char: c,
        ulti: chosenUlti || '-'
      };
      adminRegSelectedChar = adminRegSlots[0].char;
      adminRegSelectedUlti = adminRegSlots[0].ulti;

      const total = (adminRegTicketMode === '1v1') ? 1 : (adminRegTicketMode === 'DUO' ? 2 : adminRegTimCount);

      // Auto advance to next slot if empty
      if (adminRegActiveSlot < total - 1) {
        if (!adminRegSlots[adminRegActiveSlot + 1].char) {
          adminRegActiveSlot++;
        }
      }

      renderAdminRegSlotTabs();
      renderAdminRegCharGrid();
    }

    function setupAdminRegStep3() {
      const container = document.getElementById('admin-reg-slots-ulti-container');
      const multiBox = document.getElementById('admin-reg-multi-ulti-box');
      const singleBox = document.getElementById('admin-reg-single-ulti-box');
      const freeSumBox = document.getElementById('admin-reg-free-summary-box');

      if (multiBox) multiBox.style.display = 'none';
      if (singleBox) singleBox.style.display = 'none';

      const totalSlots = (adminRegTicketMode === '1v1') ? 1 : (adminRegTicketMode === 'DUO' ? 2 : adminRegTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (adminRegSlots[i] && adminRegSlots[i].char) {
          activeSlots.push({ idx: i, slot: adminRegSlots[i] });
        }
      }

      if (activeSlots.length === 0) return;

      if (container) {
        container.innerHTML = activeSlots.map(({ idx, slot }) => {
          const c = slot.char;
          const ultis = c.ultis || [];
          const slotLabel = (adminRegTicketMode === '1v1')
            ? 'Karakter Utama'
            : (idx === 0 ? 'Slot 1: Leader' : (idx === 1 ? (adminRegTicketMode === 'DUO' ? 'Slot 2: Support' : 'Slot 2: Support 1') : 'Slot 3: Support 2'));

          let ultiSelectionHtml = '';
          if (ultis.length > 1) {
            if (!slot.ulti || !ultis.includes(slot.ulti)) {
              slot.ulti = ultis[0];
            }
            ultiSelectionHtml = `
              <div style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 6px;">Pilih Jurus Ulti:</div>
              <div class="ulti-options-grid">
                ${ultis.map(u => {
                  const isSel = (slot.ulti === u);
                  return `
                    <div class="admin-ulti-card ${isSel ? 'selected' : ''}" style="padding: 8px 12px;" onclick="selectAdminRegSlotUlti(${idx}, '${escapeHtml(u).replace(/'/g, "\\'")}')">
                      <div style="font-size: 0.84rem; font-weight: 800; color: #fff;">⚡ ${escapeHtml(u)}</div>
                      <div style="font-size: 0.72rem; font-weight: 800; color: ${isSel ? '#facc15' : '#94a3b8'};">
                        ${isSel ? '✅ Dipilih' : 'Pilih'}
                      </div>
                    </div>
                  `;
                }).join('')}
              </div>
            `;
          } else {
            slot.ulti = ultis[0] || '-';
            ultiSelectionHtml = `
              <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding: 6px 10px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.74rem; color: var(--text-muted);">Ultimate Terkunci:</span>
                <span style="font-size: 0.85rem; font-weight: 800; color: var(--accent-gold);">⚡ ${escapeHtml(slot.ulti)}</span>
              </div>
            `;
          }

          return `
            <div class="slot-ulti-card">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 0.75rem; font-weight: 900; color: var(--accent-gold); text-transform: uppercase;">${slotLabel}</span>
                <span style="font-size: 0.88rem; font-weight: 900; color: #fff;">${escapeHtml(c.name)}</span>
              </div>
              ${ultiSelectionHtml}
            </div>
          `;
        }).join('');
      }

      const allNames = activeSlots.map(a => a.slot.char.name).join(' / ');
      const allUltis = activeSlots.map(a => a.slot.ulti).join(' / ');

      if (adminRegTier === 'FREE') {
        if (freeSumBox) freeSumBox.style.display = 'block';
        const nameVal = (document.getElementById('admin-reg-name-input').value || '').trim();
        const sumName = document.getElementById('admin-sum-free-name');
        const sumTicket = document.getElementById('admin-sum-free-ticket');
        const sumChar = document.getElementById('admin-sum-free-char');
        const sumUlti = document.getElementById('admin-sum-free-ulti');
        if (sumName) sumName.innerText = nameVal;
        if (sumTicket) sumTicket.innerText = (adminRegTicketMode === '1v1') ? '1vs1' : (adminRegTicketMode === 'DUO' ? 'DUO (2)' : `TIM (${activeSlots.length})`);
        if (sumChar) sumChar.innerText = allNames;
        if (sumUlti) sumUlti.innerText = allUltis;
      } else {
        if (freeSumBox) freeSumBox.style.display = 'none';
      }
    }

    function selectAdminRegSlotUlti(slotIdx, ultiName) {
      if (adminRegSlots[slotIdx]) {
        adminRegSlots[slotIdx].ulti = ultiName;
        setupAdminRegStep3();
      }
    }

    function selectAdminRegUlti(ultiName) {
      adminRegSelectedUlti = ultiName;
      if (adminRegSlots[0]) adminRegSlots[0].ulti = ultiName;
      setupAdminRegStep3();
    }

    function setAdminRegMatchCount(cnt) {
      adminRegMatchCount = Math.max(1, parseInt(cnt) || 1);
      [1, 3, 5, 10].forEach(n => {
        const btn = document.getElementById(`btn-amatch-${n}`);
        if (btn) {
          if (n === adminRegMatchCount) btn.classList.add('active');
          else btn.classList.remove('active');
        }
      });
      const disp = document.getElementById('admin-reg-match-display');
      if (disp) disp.innerText = adminRegMatchCount;
      updateAdminRegPrice();
    }

    function changeAdminRegMatchCount(delta) {
      setAdminRegMatchCount(adminRegMatchCount + delta);
    }

    function calculatePaidPrice(tier, mode, count) {
      const isTeamOrDuo = (mode === 'DUO' || mode === 'TIM' || mode === 'TRIO');
      let coinPerMatch = 0;
      let rpPerMatch = 0;
      if (tier === 'VIP') {
        if (isTeamOrDuo) { coinPerMatch = 10; rpPerMatch = 2000; }
        else { coinPerMatch = 5; rpPerMatch = 1000; }
      } else {
        if (isTeamOrDuo) { coinPerMatch = 60; rpPerMatch = 10000; }
        else { coinPerMatch = 30; rpPerMatch = 5000; }
      }
      const totalCoins = coinPerMatch * count;
      const totalRp = rpPerMatch * count;
      const totalRpFormatted = totalRp.toLocaleString('id-ID');
      return { totalCoins, totalRp, totalRpFormatted, coinPerMatch, rpPerMatch };
    }

    function updateAdminRegPrice() {
      const activeMode = adminRegTicketMode || state.masterMode || '1v1';
      const pr = calculatePaidPrice(adminRegTier, activeMode, adminRegMatchCount);
      const calcCoins = document.getElementById('admin-calc-coins');
      const calcRp = document.getElementById('admin-calc-rp');
      if (calcCoins) calcCoins.innerText = `${pr.totalCoins} Koin`;
      if (calcRp) calcRp.innerText = `Rp ${pr.totalRpFormatted}`;

      const finalCoins = document.getElementById('admin-final-coins');
      const finalRp = document.getElementById('admin-final-rp');
      if (finalCoins) finalCoins.innerText = `${pr.totalCoins} Koin`;
      if (finalRp) finalRp.innerText = `Rp ${pr.totalRpFormatted}`;
    }

    function setupAdminRegStep5() {
      updateAdminRegPrice();
      const totalSlots = (adminRegTicketMode === '1v1') ? 1 : (adminRegTicketMode === 'DUO' ? 2 : adminRegTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (adminRegSlots[i] && adminRegSlots[i].char) {
          activeSlots.push(adminRegSlots[i]);
        }
      }
      const allNames = activeSlots.map(s => s.char.name).join(' / ');
      const allUltis = activeSlots.map(s => s.ulti).join(' / ');

      const nameVal = (document.getElementById('admin-reg-name-input').value || '').trim();
      document.getElementById('admin-sum-paid-name').innerText = nameVal;
      document.getElementById('admin-sum-paid-tier').innerText = adminRegTier;
      document.getElementById('admin-sum-paid-tier').style.color = (adminRegTier === 'SROBOT') ? '#d8b4fe' : '#fde047';
      document.getElementById('admin-sum-paid-ticket').innerText = (adminRegTicketMode === '1v1') ? '1vs1' : (adminRegTicketMode === 'DUO' ? 'DUO (2)' : `TIM (${activeSlots.length})`);
      document.getElementById('admin-sum-paid-match').innerText = `${adminRegMatchCount} Match`;
      document.getElementById('admin-sum-paid-char').innerText = allNames || '-';
      document.getElementById('admin-sum-paid-ulti').innerText = allUltis || '-';
    }

    function renderAdminRegUserDropdown() {
      const select = document.getElementById('admin-reg-select-user');
      const countEl = document.getElementById('admin-reg-user-count');
      if (!select) return;

      const users = Array.isArray(state.registeredUsers) ? state.registeredUsers.slice() : [];
      if (countEl) countEl.innerText = `(${users.length} Terdaftar)`;

      let opts = '<option value="">▼ PILIH DARI USER TERDAFTAR (A-Z)...</option>';
      users.forEach(u => {
        const name = (typeof u === 'string') ? u : (u && u.name) || '';
        if (name) opts += `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`;
      });
      select.innerHTML = opts;
    }

    function handleAdminRegSelectUser(val) {
      const nameInput = document.getElementById('admin-reg-name-input');
      if (nameInput) {
        nameInput.value = val || '';
        checkAdminRegNameDup();
      }
    }

    function checkAdminRegNameDup() {
      const nameInput = document.getElementById('admin-reg-name-input');
      const warn = document.getElementById('admin-reg-name-warning');
      if (!nameInput || !warn) return;

      const name = nameInput.value.trim();
      if (!name) {
        warn.style.display = 'none';
        warn.innerText = '';
        return;
      }

      const dup = checkDuplicatePlayer(name);
      if (dup) {
        warn.style.display = 'block';
        warn.innerText = `⚠️ Perhatian: ${dup.message}. Tetap bisa didaftarkan jika dikehendaki Admin.`;
      } else {
        warn.style.display = 'none';
        warn.innerText = '';
      }
    }

    function submitAdminRegistration(target = 'queue') {
      const name = (document.getElementById('admin-reg-name-input').value || '').trim();
      if (!name) {
        alert('Masukkan nama TikTok / username pemain!');
        goToAdminRegStep(1);
        return;
      }

      if (adminRegTicketMode === '1v1') {
        if (!adminRegSlots[0].char) {
          alert('Karakter Slot 1 belum dipilih!');
          goToAdminRegStep(2);
          return;
        }
      } else if (adminRegTicketMode === 'DUO') {
        if (!adminRegSlots[0].char || !adminRegSlots[1].char) {
          alert('Mode DUO membutuhkan 2 karakter (Slot 1 & Slot 2)!');
          goToAdminRegStep(2);
          return;
        }
      } else if (adminRegTicketMode === 'TIM') {
        if (!adminRegSlots[0].char || !adminRegSlots[1].char) {
          alert('Mode TIM membutuhkan minimal 2 karakter (Slot 1 & Slot 2)!');
          goToAdminRegStep(2);
          return;
        }
      }

      const totalSlots = (adminRegTicketMode === '1v1') ? 1 : (adminRegTicketMode === 'DUO' ? 2 : adminRegTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (adminRegSlots[i] && adminRegSlots[i].char) {
          activeSlots.push(adminRegSlots[i]);
        }
      }

      for (const s of activeSlots) {
        if (typeof isCharBanned === 'function' && isCharBanned(s.char)) {
          showBannedAlert(s.char.name);
          goToAdminRegStep(2);
          return;
        }
        if (adminRegTier === 'FREE' && typeof isCharVipExclusive === 'function' && isCharVipExclusive(s.char)) {
          showVipOnlyAlert(s.char.name);
          goToAdminRegStep(2);
          return;
        }
      }

      // Validasi anti-duplikat karakter dalam 1 tim (semua versi)
      if (adminRegTicketMode === 'DUO' || adminRegTicketMode === 'TIM') {
        for (let i = 0; i < activeSlots.length; i++) {
          for (let j = i + 1; j < activeSlots.length; j++) {
            if (typeof isSameBaseCharacter === 'function' && isSameBaseCharacter(activeSlots[i].char, activeSlots[j].char)) {
              alert(`❌ PENDAFTARAN DITOLAK!\n\nSlot ${i + 1} (${activeSlots[i].char.name}) dan Slot ${j + 1} (${activeSlots[j].char.name}) menggunakan karakter yang sama (${getCharBaseName(activeSlots[i].char)}).\n\nMode TIM/DUO tidak mengizinkan karakter yang sama (semua versi).`);
              goToAdminRegStep(2);
              return;
            }
          }
        }
      }

      const dup = checkDuplicatePlayer(name);
      if (dup) {
        const proceed = confirm(`⚠️ PERINGATAN DOUBLE USER!\n\n${dup.message}.\n\nApakah Admin yakin tetap ingin menambahkan pemain ini?`);
        if (!proceed) return;
      }

      const fullChar = activeSlots.map(s => s.char.name).join(' / ');
      const fullUlti = activeSlots.map(s => s.ulti || getPlayerDefaultUlti(s.char.name)).join(' / ');
      const matchesLeft = (adminRegTier === 'FREE') ? 1 : adminRegMatchCount;
      const targetLabel = (target === 'saved') ? 'LIST TABUNGAN' : 'MATCH QUEUE';

      const newPlayer = {
        id: Date.now().toString(),
        name,
        char: fullChar,
        ulti: fullUlti,
        matchesLeft,
        ws: 0,
        type: adminRegTier,
        ticketMode: adminRegTicketMode
      };

      if (target === 'saved') {
        newPlayer.savedAt = Date.now();
        if (!Array.isArray(state.saved)) state.saved = [];
        state.saved.push(newPlayer);
        sortSaved();
      } else {
        if (!Array.isArray(state.queue)) state.queue = [];
        state.queue.push(newPlayer);
        sortQueue();
        checkAutoFillArena();
      }

      registerUserHistory(name);
      saveState();
      closeAdminRegModal();

      if (typeof showToast === 'function') {
        showToast(`✅ [${name}] (${adminRegTier}, ${matchesLeft} match, ${adminRegTicketMode}) berhasil ditambahkan ke ${targetLabel}!`);
      } else {
        alert(`✅ [${name}] berhasil ditambahkan ke ${targetLabel}!`);
      }
    }

    // Mode Tampilan Live Overlay (Antrean vs Saved) - Centang Eksklusif (Hanya salah satu yang bisa aktif)
    function handleOverlayListCheck(mode, isChecked) {
      if (!isChecked) {
        // Jika user mencoba uncheck opsi yang sedang aktif, tolak uncheck karena salah satu harus aktif
        updateModeButtons();
        return;
      }
      setOverlayListMode(mode);
    }

    function setOverlayListMode(mode) {
      state.overlayListMode = (mode === 'saved') ? 'saved' : 'queue';
      saveState();
      updateModeButtons();
    }

    function updateModeButtons() {
      const mode = state.overlayListMode || 'queue';
      const chkQueue = document.getElementById('chk-overlay-queue');
      const chkSaved = document.getElementById('chk-overlay-saved');
      if (chkQueue) chkQueue.checked = (mode === 'queue');
      if (chkSaved) chkSaved.checked = (mode === 'saved');

      const qBtn = document.getElementById('mode-queue-btn');
      const sBtn = document.getElementById('mode-saved-btn');
      if (qBtn && sBtn) {
        if (mode === 'queue') {
          qBtn.className = 'btn btn-sm btn-primary';
          qBtn.style.background = 'var(--c-dark)';
          qBtn.style.color = 'var(--c-white)';
          qBtn.innerText = 'Match Queue di Live (Aktif)';
          sBtn.className = 'btn btn-sm btn-danger';
          sBtn.style.background = 'var(--c-light)';
          sBtn.style.color = 'var(--c-dark)';
          sBtn.innerText = 'Tampilkan Tabungan di Live';
        } else {
          sBtn.className = 'btn btn-sm btn-primary';
          sBtn.style.background = 'var(--c-dark)';
          sBtn.style.color = 'var(--c-white)';
          sBtn.innerText = 'List Tabungan di Live (Aktif)';
          qBtn.className = 'btn btn-sm btn-danger';
          qBtn.style.background = 'var(--c-light)';
          qBtn.style.color = 'var(--c-dark)';
          qBtn.innerText = 'Tampilkan Match Queue di Live';
        }
      }
    }

    // Switch Sub-Tabs di Admin
    function switchListTab(tab) {
      const qBtn = document.getElementById('subtab-queue-btn');
      const sBtn = document.getElementById('subtab-saved-btn');
      const pBtn = document.getElementById('subtab-pending-btn');
      const qContent = document.getElementById('subtab-queue-content');
      const sContent = document.getElementById('subtab-saved-content');
      const pContent = document.getElementById('subtab-pending-content');

      // Reset style all buttons
      [qBtn, sBtn, pBtn].forEach(b => {
        if (b) {
          b.className = 'btn btn-sm btn-danger';
          b.style.background = 'var(--c-light)';
          b.style.color = 'var(--c-dark)';
        }
      });
      // Hide all contents
      if (qContent) qContent.style.display = 'none';
      if (sContent) sContent.style.display = 'none';
      if (pContent) pContent.style.display = 'none';

      if (tab === 'queue') {
        if (qBtn) {
          qBtn.className = 'btn btn-sm btn-primary';
          qBtn.style.background = 'var(--c-dark)';
          qBtn.style.color = 'var(--c-white)';
        }
        if (qContent) qContent.style.display = 'block';
      } else if (tab === 'saved') {
        if (sBtn) {
          sBtn.className = 'btn btn-sm btn-primary';
          sBtn.style.background = 'var(--c-dark)';
          sBtn.style.color = 'var(--c-white)';
        }
        if (sContent) sContent.style.display = 'block';
      } else if (tab === 'pending') {
        if (pBtn) {
          pBtn.className = 'btn btn-sm btn-primary';
          pBtn.style.background = 'var(--c-dark)';
          pBtn.style.color = 'var(--c-white)';
        }
        if (pContent) pContent.style.display = 'block';
      }
    }

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

    // Pencarian Slot / Nama Pemain untuk menjawab chat penonton
    function handleSearchPlayer() {
      const q = document.getElementById('search-player-input').value.trim().toLowerCase();
      const resBox = document.getElementById('search-result-box');
      if (!q) {
        resBox.style.display = 'none';
        return;
      }

      let matches = [];
      const roles = getArenaRoles(state);
      const isP1King = roles.kingSlot === 'p1';

      // Cek Arena
      if (state.p1 && state.p1.name.toLowerCase().includes(q)) {
        const roleLabel = 'Arena (P1)';
        matches.push(`[${roleLabel}] <b>${state.p1.name}</b> sedang main (WS: ${state.p1.ws}, Sisa Match: <b>${state.p1.matchesLeft}</b>)`);
      }
      if (state.p2 && state.p2.name.toLowerCase().includes(q)) {
        const roleLabel = 'Arena (P2)';
        matches.push(`[${roleLabel}] <b>${state.p2.name}</b> sedang main (WS: ${state.p2.ws}, Sisa Match: <b>${state.p2.matchesLeft}</b>)`);
      }

      // Cek Match Queue
      state.queue.forEach((p, idx) => {
        if (p.name.toLowerCase().includes(q)) {
          matches.push(`[Match Queue #${idx + 1}] <b>${p.name}</b> (${getTierLabel(p.type)}) - Char: ${p.char}, Match Sisa: <b>${p.matchesLeft}</b>`);
        }
      });

      // Cek List Tabungan
      state.saved.forEach(p => {
        if (p.name.toLowerCase().includes(q)) {
          matches.push(`[Tabungan] <b>${p.name}</b> (${getTierLabel(p.type)}) - Char: ${p.char}, Match Sisa: <b>${p.matchesLeft}</b>`);
        }
      });

      resBox.style.display = 'block';
      if (matches.length > 0) {
        resBox.style.background = 'var(--panel-subtle)';
        resBox.style.border = '1px solid var(--c-dark)';
        resBox.style.color = 'var(--text-light)';
        resBox.innerHTML = `<b>Ditemukan (${matches.length}):</b><br>` + matches.map(m => `• ${m}`).join('<br>');
      } else {
        resBox.style.background = 'var(--panel-subtle)';
        resBox.style.border = '1px solid var(--c-slate)';
        resBox.style.color = 'var(--text-light)';
        resBox.innerHTML = `<b>Tidak Ditemukan:</b> Pemain dengan nama "<b>${q}</b>" tidak ada di antrean maupun simpanan.`;
      }
    }

    // Assign Player Manual ke Arena
    function assignToArena(playerId, targetSlot) {
      const index = state.queue.findIndex(p => String(p.id) === String(playerId));
      if (index === -1) return;

      const playerCandidate = state.queue[index];
      const pTier = getTierLabel(playerCandidate.type);

      // Validasi Kesesuaian Tiket dengan Mode Arena Aktif
      const curMode = state.masterMode || '1v1';
      const pTicket = (playerCandidate.ticketMode === 'TIM' || playerCandidate.ticketMode === 'DUO') ? 'TIM' : '1v1';
      if (curMode === '1v1' && pTicket !== '1v1') {
        if (!confirm(`⚠️ PERINGATAN TIKET:\nPemain [ ${playerCandidate.name} ] memiliki Tiket DUO & TIM, sedangkan Arena saat ini Mode 1v1.\n\nTetap naikkan ke arena?`)) {
          return;
        }
      } else if (curMode !== '1v1' && pTicket === '1v1') {
        if (!confirm(`⚠️ PERINGATAN TIKET:\nPemain [ ${playerCandidate.name} ] memiliki Tiket 1v1, sedangkan Arena saat ini Mode ${curMode}.\n\nTetap naikkan ke arena?`)) {
          return;
        }
      }

      // Validasi Aturan Mutlak: Maksimal 3x VIP berturut-turut, lalu 1x FREE
      const hasFreeInQueue = state.queue.some(p => getTierLabel(p.type) === 'FREE');
      if (pTier === 'VIP' && (state.vipConsecutiveCount || 0) >= 3 && hasFreeInQueue) {
        if (!confirm(`⚠️ ATURAN MUTLAK:\nSudah 3x pemain VIP naik ke arena berturut-turut!\nGiliran saat ini seharusnya adalah pemain FREE (1x).\n\nTetap paksa naikkan [ ${playerCandidate.name} ] (VIP)?`)) {
          return;
        }
      }

      if (!confirm(`Naikkan [ ${playerCandidate.name} ] (${pTier}, ${playerCandidate.matchesLeft} match) ke arena ${targetSlot.toUpperCase()}?`)) {
        return;
      }

      const player = state.queue.splice(index, 1)[0];
      // Potong 1 tiket match pertama
      player.matchesLeft = Math.max(0, player.matchesLeft - 1);
      player.ws = (player.ws !== undefined && player.ws !== null) ? player.ws : 0;

      // Update Aturan Mutlak Hitungan Naik Arena:
      if (pTier === 'VIP') {
        state.vipConsecutiveCount = Math.min(3, (state.vipConsecutiveCount || 0) + 1);
      } else if (pTier === 'FREE') {
        state.vipConsecutiveCount = 0; // Reset ke 0 karena FREE sudah naik!
      }

      if (targetSlot === 'p1') {
        state.p1 = player;
      } else {
        state.p2 = player;
      }

      sortQueue();
      saveState();
    }

    function quickAssignSlot(slot) {
      if (state.queue.length === 0) {
        alert("Match queue kosong! Belum ada pemain lain yang mengantre.");
        return;
      }
      sortQueue();
      assignToArena(state.queue[0].id, slot);
    }

    function quickAssignP2() {
      quickAssignSlot('p2');
    }

    function removeSlotToQueue(slot) {
      const player = slot === 'p1' ? state.p1 : state.p2;
      if (!player) return;
      const wsInfo = (player.ws && player.ws > 0) ? `\n(WS: ${player.ws} & Karakter: "${formatChar(player.char)}" tetap terjaga)` : '';
      if (confirm(`Kembalikan [ ${player.name} ] (${slot.toUpperCase()}) ke match queue? Match 1 pertandingan akan dikembalikan.${wsInfo}`)) {
        player.matchesLeft += 1;
        // PENTING: BUKAN KALAH! player.ws dan player.char TIDAK DIRESET!
        // Diturunkan admin -> otomatis masuk ke urutan terakhir sesuai tier-nya
        const pTier = getTierLabel(player.type);
        if (pTier === 'VIP' && (state.vipConsecutiveCount || 0) > 0) {
          state.vipConsecutiveCount -= 1;
        } else if (pTier === 'FREE') {
          state.vipConsecutiveCount = 3;
        }
        state.queue.push(player);
        sortQueue();
        if (slot === 'p1') state.p1 = null;
        else state.p2 = null;
        saveState();
      }
    }

    function promptSetVipStreak() {
      const cur = state.vipConsecutiveCount || 0;
      const val = prompt("Atur giliran VIP berturut-turut saat ini (0 - 3):\n0 = Giliran 3x VIP baru dimulai\n1 = Sudah 1x VIP naik (sisa 2x VIP)\n2 = Sudah 2x VIP naik (sisa 1x VIP)\n3 = Sudah 3x VIP naik (Giliran 1x FREE berikutnya)", cur);
      if (val !== null) {
        const num = parseInt(val);
        if (!isNaN(num) && num >= 0 && num <= 3) {
          state.vipConsecutiveCount = num;
          sortQueue();
          saveState();
        }
      }
    }

    function removeP2ToQueue() {
      removeSlotToQueue('p2');
    }

    // Tukar Posisi Arena P1 ⇄ P2 (Manual Fleksibel)
    function swapArenaSlots() {
      if (!state.p1 && !state.p2) {
        alert("Kedua slot arena masih kosong!");
        return;
      }
      const p1Label = state.p1 ? state.p1.name : '(Kosong)';
      const p2Label = state.p2 ? state.p2.name : '(Kosong)';
      if (!confirm(`Tukar posisi pemain di arena?\n\nP1: ${p1Label} ⇄ P2: ${p2Label}`)) return;

      const temp = state.p1;
      state.p1 = state.p2;
      state.p2 = temp;
      saveState();
    }

    function checkAndUpdateTopRecord(candidate) {
      if (!candidate || candidate.ws === undefined || candidate.ws === null || candidate.ws <= 0) return;
      const curMode = state.masterMode || '1v1';
      const targetKey = getTopWsKey(curMode);

      if (!Array.isArray(state[targetKey]) || state[targetKey].length < 3) {
        state[targetKey] = [
          { rank: 1, name: '-', char: '-', ws: 0 },
          { rank: 2, name: '-', char: '-', ws: 0 },
          { rank: 3, name: '-', char: '-', ws: 0 }
        ];
      }

      const list = state[targetKey];
      const candName = String(candidate.name).trim();
      const candChar = formatChar(candidate.char);
      const candWs = Number(candidate.ws) || 0;

      // Cek apakah entri sama persis: HANYA jika username DAN karakter sama persis (case-insensitive)
      // Jika username sama tapi karakter beda, atau username beda: diperlakukan sebagai rekor terpisah
      const existingIdx = list.findIndex(r =>
        r.name && r.name !== '-' &&
        r.name.toLowerCase() === candName.toLowerCase() &&
        formatChar(r.char).toLowerCase() === candChar.toLowerCase()
      );

      if (existingIdx !== -1) {
        // Jika nama DAN karakter sama persis, perbarui WS jika rekor saat ini lebih tinggi
        if (candWs > (list[existingIdx].ws || 0)) {
          list[existingIdx].ws = candWs;
        }
      } else {
        const minWs = Math.min(...list.map(r => r.ws || 0));
        const hasEmptySlot = list.some(r => !r.name || r.name === '-' || (r.ws || 0) === 0);

        if (hasEmptySlot || candWs > minWs) {
          const valid = list.filter(r => r.name && r.name !== '-' && (r.ws || 0) > 0);
          valid.push({ name: candName, char: candChar, ws: candWs });
          valid.sort((a, b) => (b.ws || 0) - (a.ws || 0));
          const top3 = valid.slice(0, 3);
          while (top3.length < 3) {
            top3.push({ name: '-', char: '-', ws: 0 });
          }
          state[targetKey] = top3.map((r, i) => ({ rank: i + 1, ...r }));
        }
      }

      state[targetKey].sort((a, b) => (b.ws || 0) - (a.ws || 0));
      state[targetKey] = state[targetKey].slice(0, 3).map((r, i) => ({
        rank: i + 1,
        name: r.name || '-',
        char: formatChar(r.char),
        ws: Number(r.ws) || 0
      }));

      // Sinkronkan ke rekor aktif
      syncActiveTopRecords();
    }

    // Fill Arena Slot otomatis: Hanya isi jika arena KOSONG TOTAL di awal sesi live
    function checkAutoFillArena() {
      let changed = false;
      if (!state.p1 && !state.p2 && state.queue.length > 0) {
        state.p1 = state.queue.shift();
        state.p1.matchesLeft = Math.max(0, state.p1.matchesLeft - 1);
        changed = true;
      }
      // P1/P2 penantang TIDAK DI-AUTOFILL! Admin yang memilih siapa penantang berikutnya agar aman jika penonton sudah leave live.
      if (changed) saveState();
    }

    // Trigger Win Logika Pertandingan: PEMENANG SELALU KE P1 UNTUK NEXT MATCH
    function triggerWin(winnerRole) {
      const winner = winnerRole === 'p1' ? state.p1 : state.p2;
      const loser = winnerRole === 'p1' ? state.p2 : state.p1;

      if (!winner || !loser) {
        alert("Pemain di arena belum lengkap!");
        return;
      }

      if (!confirm(`Konfirmasi kemenangan untuk [ ${winner.name} ] (${winnerRole.toUpperCase()})?\nWin streak akan menjadi ${(winner.ws || 0) + 1}.`)) {
        return;
      }

      // 1. UPDATE WINNER & BONUS WS KELIPATAN 5 (WS 5x = +1 MATCH)
      winner.ws += 1; // Win streak bertambah

      if (winner.ws > 0 && winner.ws % 5 === 0) {
        winner.bonusBase = winner.matchesLeft;
        winner.matchesLeft += 1; // Bonus +1 match
        winner.bonusAnimUntil = Date.now() + 5000; // Notif 'Match: 1+1' selama 5 detik lalu otomatis jadi Match: 2
      }

      // Update TOP WS jika menang dan WS lebih tinggi dari rekor sesi
      checkAndUpdateTopRecord(winner);

      // 2. UPDATE LOSER
      loser.ws = 0; // WS reset ke 0 karena kalah

      // Penanganan Pemain Kalah: Auto reset karakter jika match masih ada
      if (loser.matchesLeft > 0) {
        loser.char = '-'; // Auto reset karakter ke '-'
        const backToQueue = confirm(
          `[ ${loser.name} ] KALAH!\nWS di-reset ke 0.\nSisa Match: ${loser.matchesLeft}.\n\nKembalikan ke MATCH QUEUE?`
        );

        if (backToQueue) {
          delete loser.savedAt;
          state.queue.push(loser);
          sortQueue();
        } else {
          const saveDulu = confirm(`Simpan [ ${loser.name} ] ke LIST TABUNGAN?`);
          if (saveDulu) {
            // PERTAHANKAN TIER: SROBOT, VIP, atau FREE tetap melekat!
            if (!loser.type || loser.type === 'SAVE') loser.type = 'FREE';
            loser.savedAt = Date.now();
            state.saved.push(loser);
            sortSaved();
          }
        }
      } else {
        alert(`[ ${loser.name} ] KALAH & Match HABIS.`);
      }

      // 3. PEMENANG SELALU OTOMATIS DI SLOT P1 UNTUK MATCH BERIKUTNYA
      // Slot P2 dikosongkan untuk penantang baru dari match queue
      state.p1 = winner;
      state.p2 = null;

      saveState();
      // JANGAN PANGGIL checkAutoFillArena! Slot penantang dibiarkan kosong agar admin yang memilih penantang dari match queue.
    }

    function resetArena() {
      const curMode = state.masterMode || '1v1';
      if (confirm(`Kosongkan arena pertandingan untuk mode [ ${curMode} ]? Pemain di arena mode ini akan dikosongkan.`)) {
        state.p1 = null;
        state.p2 = null;
        state.vipConsecutiveCount = 0;
        syncActiveModeToArena();
        sortQueue();
        saveState();
      }
    }

    function clearQueue() {
      if (confirm("Kosongkan semua daftar match queue aktif?")) {
        state.queue = [];
        state.vipConsecutiveCount = 0;
        saveState();
      }
    }

    function clearSaved() {
      if (confirm("Hapus semua daftar pemain yang disimpan di List Tabungan?")) {
        state.saved = [];
        saveState();
      }
    }

    function resetAllData() {
      if (confirm("PERINGATAN: Yakin ingin mereset SEMUA data (Arena, Match Queue, dan List Tabungan) untuk memulai sesi streaming baru?")) {
        state = {
          masterMode: state.masterMode || '1v1',
          arena1v1: { p1: null, p2: null },
          arenaDuo: { p1: null, p2: null },
          arenaTim: { p1: null, p2: null },
          p1: null,
          p2: null,
          queue: [],
          saved: [],
          announcement: '',
          topWsOverlay: state.topWsOverlay || { '1v1': true, 'DUO': true, 'TIM': true },
          topRecords1v1: state.topRecords1v1 || [],
          topRecordsDuo: state.topRecordsDuo || [],
          topRecordsTim: state.topRecordsTim || [],
          topRecordsSolo: state.topRecordsSolo || [],
          topRecordsTrio: state.topRecordsTrio || [],
          topRecord: { name: '-', char: '-', ws: 0 },
          topRecords: state.topRecords || [],
          overlayListMode: state.overlayListMode || 'queue',
          customCharacters: state.customCharacters || [],
          registeredUsers: state.registeredUsers || [],
          vipConsecutiveCount: 0,
          tierStatus: state.tierStatus || { SROBOT: 'AKTIF', VIP: 'AKTIF', FREE: 'AKTIF' }
        };
        saveState();
      }
    }

    // Backup & Restore Functions
    function exportDataBackup() {
      const dataStr = JSON.stringify(state, null, 2);
      const blob = new Blob([dataStr], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      const dateStr = new Date().toISOString().slice(0, 10);
      a.href = url;
      a.download = `naruto_koth_backup_${dateStr}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    function importDataBackup(event) {
      const file = event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (e) {
        try {
          const imported = JSON.parse(e.target.result);
          if (imported && typeof imported === 'object') {
            state = imported;
            const allPlayers = [state.p1, state.p2, ...(state.queue || []), ...(state.saved || [])];
            allPlayers.forEach(p => { if (p && p.char) registerCharacter(p.char); });
            saveState();
            alert("Data berhasil dipulihkan dari file backup!");
          } else {
            alert("Format file backup tidak valid!");
          }
        } catch (err) {
          alert("Gagal membaca file backup: " + err.message);
        }
      };
      reader.readAsText(file);
      event.target.value = '';
    }

    // Modal Character Editor
    function promptEditChar(target) {
      if (target === 'p1') {
        openEditPlayerCharModal('p1', 'arena');
        return;
      }
      if (target === 'p2') {
        openEditPlayerCharModal('p2', 'arena');
        return;
      }
      if (target && typeof target === 'object' && target.id) {
        openEditPlayerCharModal(target.id, 'queue');
        return;
      }

      editingTarget = target;
      let name = "";
      let curChar = "";
      let curUlti = "";

      if (target === 'p1' && state.p1) { name = state.p1.name; curChar = state.p1.char; curUlti = state.p1.ulti || ''; }
      else if (target === 'p2' && state.p2) { name = state.p2.name; curChar = state.p2.char; curUlti = state.p2.ulti || ''; }
      else if (typeof target === 'object') { name = target.name; curChar = target.char; curUlti = target.ulti || ''; }

      document.getElementById('modal-target-label').innerText = `Ubah Karakter [ ${name} ]:`;

      let cleanChar = (curChar === 'Belum Pilih / TBA' || curChar === 'TBA' || curChar === '-') ? '' : curChar;
      const parts = cleanChar ? cleanChar.split('/').map(s => s.trim()).filter(Boolean) : [];
      const partsUlti = curUlti ? curUlti.split('/').map(s => s.trim()).filter(Boolean) : [];

      let detectedMode = (state.masterMode || '1v1').toLowerCase();
      if (detectedMode === 'solo') detectedMode = '1v1';
      if (detectedMode === 'trio') detectedMode = 'tim';
      if (parts.length >= 3) detectedMode = 'tim';
      else if (parts.length === 2) detectedMode = 'duo';
      else if (parts.length <= 1) detectedMode = '1v1';

      for (let i = 1; i <= 3; i++) {
        const inp = document.getElementById(`modal-char-input-${i}`);
        const ch = parts[i - 1] || '';
        const ul = partsUlti[i - 1] || '';
        if (inp) inp.value = ch;
        updateUltiSelectForChar(i, 'modal', ch, ul);
      }

      setModalTeamMode(detectedMode);
      document.getElementById('modal-char').classList.add('active');
    }

    function saveModalChar() {
      updateModalTeamCharValue();
      const rawVal = document.getElementById('modal-char-input').value.trim();
      const val = rawVal || 'Belum Pilih / TBA';
      const rawUlti = document.getElementById('modal-char-ulti') ? document.getElementById('modal-char-ulti').value.trim() : '';
      const ultiVal = rawUlti || getPlayerDefaultUlti(val);

      if (!confirm(`Simpan karakter "${val}"?`)) return;

      // Validasi Karakter: Admin tidak bisa sembarangan menambah karakter baru
      if (rawVal) {
        const parts = rawVal.split('/').map(s => s.trim()).filter(Boolean);
        for (const p of parts) {
          if (!findNarutoChar(p)) {
            alert(`❌ KARAKTER TIDAK VALID!\n\nKarakter "${p}" tidak terdaftar dalam roster Naruto.\nAdmin tidak bisa menambahkan karakter baru. Silakan pilih dari saran.`);
            return;
          }
        }
      }

      let updatedPlayer = null;
      if (editingTarget === 'p1' && state.p1) {
        state.p1.char = val;
        state.p1.ulti = ultiVal;
        updatedPlayer = state.p1;
      }
      else if (editingTarget === 'p2' && state.p2) {
        state.p2.char = val;
        state.p2.ulti = ultiVal;
        updatedPlayer = state.p2;
      }
      else if (typeof editingTarget === 'object') {
        editingTarget.char = val;
        editingTarget.ulti = ultiVal;
        updatedPlayer = editingTarget;
      }

      if (updatedPlayer) {
        const updateRecList = (list) => {
          if (Array.isArray(list)) {
            list.forEach(r => {
              if (r.name && r.name === updatedPlayer.name && r.ws === updatedPlayer.ws && r.ws > 0) {
                r.char = formatChar(val);
              }
            });
          }
        };
        updateRecList(state.topRecords1v1);
        updateRecList(state.topRecordsSolo);
        updateRecList(state.topRecordsDuo);
        updateRecList(state.topRecordsTim);
        updateRecList(state.topRecordsTrio);
        updateRecList(state.topRecords);
        if (state.topRecord && state.topRecord.name === updatedPlayer.name && state.topRecord.ws === updatedPlayer.ws && state.topRecord.ws > 0) {
          state.topRecord.char = formatChar(val);
        }
      }

      closeModal();
      saveState();
    }

    function closeModal() {
      document.getElementById('modal-char').classList.remove('active');
      editingTarget = null;
    }

    // Ubah Match Manual (Admin)
    function promptEditTickets(slot) {
      const p = slot === 'p1' ? state.p1 : state.p2;
      if (!p) return;
      const val = prompt(`Ubah Jumlah Match untuk [ ${p.name} ]:`, p.matchesLeft);
      if (val !== null) {
        const parsed = parseInt(val);
        if (!isNaN(parsed) && parsed >= 0) {
          if (!confirm(`Ubah match [ ${p.name} ] dari ${p.matchesLeft} menjadi ${parsed}?`)) return;
          p.matchesLeft = parsed;
          saveState();
        }
      }
    }

    function promptEditPlayerTickets(id) {
      const p = state.queue.find(x => String(x.id) === String(id)) || state.saved.find(x => String(x.id) === String(id));
      if (!p) return;
      const val = prompt(`Ubah Jumlah Match untuk [ ${p.name} ]:`, p.matchesLeft);
      if (val !== null) {
        const parsed = parseInt(val);
        if (!isNaN(parsed) && parsed >= 0) {
          if (!confirm(`Ubah match [ ${p.name} ] dari ${p.matchesLeft} menjadi ${parsed}?`)) return;
          p.matchesLeft = parsed;
          saveState();
        }
      }
    }

    // RENDER FUNCTIONS
    function renderAll() {
      updateMasterModeUI();
      renderArena();
      renderQueue();
      renderSaved();
      renderPendingApprovals();
      renderOverlay();
      updateModeButtons();
      renderCharacterDatalist();
      renderRegisteredUsersDropdown();
      renderTopRecordAdmin();
      updateUndoRedoUI();
      updatePlayerFormValidation();
      checkNewPendingApprovals();

      const annInput = document.getElementById('input-announcement');
      if (annInput && state.announcement !== undefined) {
        if (document.activeElement !== annInput) {
          annInput.value = state.announcement;
        }
      }
    }

    function renderTopRecordAdmin() {
      const tab = currentTopWsTab || '1v1';
      const targetKey = getTopWsKey(tab);

      if (!Array.isArray(state[targetKey]) || state[targetKey].length < 3) {
        state[targetKey] = [
          { rank: 1, name: '-', char: '-', ws: 0 },
          { rank: 2, name: '-', char: '-', ws: 0 },
          { rank: 3, name: '-', char: '-', ws: 0 }
        ];
      }
      const records = state[targetKey];

      const topWsBadge = document.getElementById('admin-top-ws-badge');
      if (topWsBadge) {
        const r1 = records[0] ? records[0].ws || 0 : 0;
        const r2 = records[1] ? records[1].ws || 0 : 0;
        const r3 = records[2] ? records[2].ws || 0 : 0;
        topWsBadge.innerText = `[${tab}] 🥇 ${r1} WS | 🥈 ${r2} WS | 🥉 ${r3} WS`;
      }

      // Render Checkbox Tampilkan di Overlay
      renderTopWsCheckboxes();

      // Jangan timpa input jika admin sedang fokus / mengetik di salah satu field rekor
      const inputs = [
        document.getElementById('input-record-name-1'),
        document.getElementById('input-record-char-1'),
        document.getElementById('input-record-ws-1'),
        document.getElementById('input-record-name-2'),
        document.getElementById('input-record-char-2'),
        document.getElementById('input-record-ws-2'),
        document.getElementById('input-record-name-3'),
        document.getElementById('input-record-char-3'),
        document.getElementById('input-record-ws-3')
      ];
      if (inputs.some(el => el && document.activeElement === el)) return;

      for (let i = 1; i <= 3; i++) {
        const rec = records[i - 1] || { name: '-', char: '-', ws: 0 };
        const nInput = document.getElementById(`input-record-name-${i}`);
        const cInput = document.getElementById(`input-record-char-${i}`);
        const wInput = document.getElementById(`input-record-ws-${i}`);
        if (nInput) nInput.value = (rec.name === '-' ? '' : (rec.name || ''));
        if (cInput) cInput.value = (rec.char === '-' ? '' : (rec.char || ''));
        if (wInput) wInput.value = (rec.ws !== undefined && rec.ws !== null) ? rec.ws : 0;
      }
    }

    function saveTopRecord() {
      const tab = currentTopWsTab || '1v1';
      const records = [];
      for (let i = 1; i <= 3; i++) {
        const nInput = document.getElementById(`input-record-name-${i}`);
        const cInput = document.getElementById(`input-record-char-${i}`);
        const wInput = document.getElementById(`input-record-ws-${i}`);
        const name = (nInput ? nInput.value.trim() : '') || '-';
        const char = (cInput ? cInput.value.trim() : '') || '-';
        const ws = Math.max(0, parseInt(wInput ? wInput.value : '0') || 0);
        records.push({ rank: i, name, char: formatChar(char), ws });
      }

      if (!confirm(`Simpan perubahan TOP 3 WS untuk mode [ ${tab} ]?`)) return;

      records.sort((a, b) => (b.ws || 0) - (a.ws || 0));
      const targetKey = getTopWsKey(tab);
      state[targetKey] = records.slice(0, 3).map((r, i) => ({ ...r, rank: i + 1 }));

      syncActiveTopRecords();

      saveState();
      alert(`TOP 3 WS (${tab}) berhasil diperbarui!`);
    }

    function resetTopRecord() {
      const tab = currentTopWsTab || '1v1';
      if (!confirm(`Reset semua rekor TOP 3 WS untuk mode [ ${tab} ] menjadi 0?`)) return;
      const targetKey = getTopWsKey(tab);
      state[targetKey] = [
        { rank: 1, name: '-', char: '-', ws: 0 },
        { rank: 2, name: '-', char: '-', ws: 0 },
        { rank: 3, name: '-', char: '-', ws: 0 }
      ];

      syncActiveTopRecords();

      for (let i = 1; i <= 3; i++) {
        const nInput = document.getElementById(`input-record-name-${i}`);
        const cInput = document.getElementById(`input-record-char-${i}`);
        const wInput = document.getElementById(`input-record-ws-${i}`);
        if (nInput) nInput.value = '';
        if (cInput) cInput.value = '';
        if (wInput) wInput.value = '0';
      }

      saveState();
      alert(`Semua rekor TOP 3 WS (${tab}) berhasil di-reset menjadi 0.`);
    }

    function saveAnnouncement() {
      const val = document.getElementById('input-announcement').value.trim();
      state.announcement = val;
      saveState();
      showToast('✅ Teks NOTE overlay berhasil disimpan!');
    }

    function clearAnnouncement() {
      if (!confirm("Kosongkan dan sembunyikan NOTE dari overlay?")) return;
      const input = document.getElementById('input-announcement');
      if (input) input.value = '';
      state.announcement = '';
      saveState();
      showToast('🗑️ NOTE overlay berhasil dikosongkan!');
    }

    function renderArena() {
      // 1. UPDATE P1 SLOT
      const p1SlotCard = document.getElementById('p1-slot-card');
      const p1RoleBadge = document.getElementById('p1-role-badge');
      const p1BtnGroup = document.getElementById('p1-btn-group');
      const p1EmptyNotice = document.getElementById('p1-empty-notice');
      const p1EmptyText = document.getElementById('p1-empty-text');
      const p1QuickBtn = document.getElementById('p1-quick-btn');
      const p1DemoteBtn = document.getElementById('p1-demote-btn');

      if (state.p1) {
        const p1TierLabel = getTierLabel(state.p1.type);
        const p1TierCls = getTierCls(state.p1.type);
        if (p1SlotCard) p1SlotCard.className = 'player-slot ' + p1TierCls;
        if (p1RoleBadge) {
          p1RoleBadge.innerText = `${p1TierLabel} (P1)`;
          p1RoleBadge.className = 'role-badge badge-' + p1TierCls;
        }
        document.getElementById('p1-name').innerText = state.p1.name;
        document.getElementById('p1-char').innerText = formatChar(state.p1.char);
        document.getElementById('p1-ws').innerText = state.p1.ws;
        const p1Ulti = state.p1.ulti || getPlayerDefaultUlti(state.p1.char);
        const p1UltiRow = document.getElementById('p1-ulti-row');
        const p1UltiEl = document.getElementById('p1-ulti');
        if (p1UltiRow && p1UltiEl) {
          if (p1Ulti && p1Ulti !== '-' && p1Ulti !== 'TBA') {
            p1UltiEl.innerText = p1Ulti;
            p1UltiRow.style.display = 'block';
          } else {
            p1UltiRow.style.display = 'none';
          }
        }

        // WS 5 bonus notification
        let p1TicketText = String(state.p1.matchesLeft);
        if (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) {
          const base = state.p1.bonusBase !== undefined ? state.p1.bonusBase : (state.p1.matchesLeft - 1);
          p1TicketText = `${base}+1`;
          const remaining = state.p1.bonusAnimUntil - Date.now();
          setTimeout(renderAll, remaining + 50);
        }
        document.getElementById('p1-matches').innerText = p1TicketText;

        if (p1BtnGroup) p1BtnGroup.style.display = 'flex';
        if (p1EmptyNotice) p1EmptyNotice.style.display = 'none';
        if (p1DemoteBtn) p1DemoteBtn.style.display = 'inline-flex';

        // P1 Bonus Badge (kelipatan 5 WS)
        const p1BonusBadge = document.getElementById('p1-bonus-badge');
        if (p1BonusBadge) {
          if (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) {
            p1BonusBadge.innerText = '+1 Match';
            p1BonusBadge.style.display = 'inline-block';
          } else {
            p1BonusBadge.style.display = 'none';
          }
        }
      } else {
        if (p1SlotCard) p1SlotCard.className = 'player-slot free';
        if (p1RoleBadge) {
          p1RoleBadge.innerText = 'KOSONG (P1)';
          p1RoleBadge.className = 'role-badge badge-free';
        }
        document.getElementById('p1-name').innerText = "Slot P1 Kosong";
        document.getElementById('p1-char').innerText = "-";
        const p1UltiRow = document.getElementById('p1-ulti-row');
        if (p1UltiRow) p1UltiRow.style.display = 'none';
        document.getElementById('p1-ws').innerText = "0";
        document.getElementById('p1-matches').innerText = "0";
        const p1BonusBadge = document.getElementById('p1-bonus-badge');
        if (p1BonusBadge) p1BonusBadge.style.display = 'none';
        if (p1BtnGroup) p1BtnGroup.style.display = 'none';
        if (p1EmptyNotice) {
          p1EmptyNotice.style.display = 'block';
          if (p1EmptyText) {
            p1EmptyText.innerHTML = `<b>Slot P1 Kosong</b>. Pilih pemain dari match queue di bawah.`;
          }
          if (p1QuickBtn) {
            if (state.queue.length > 0) {
              p1QuickBtn.style.display = 'block';
              p1QuickBtn.innerHTML = `⬆ #1 (${escapeHtml(state.queue[0].name)}) ke P1`;
            } else {
              p1QuickBtn.style.display = 'none';
            }
          }
        }
      }

      // 2. UPDATE P2 SLOT
      const p2SlotCard = document.getElementById('p2-slot-card');
      const p2RoleBadge = document.getElementById('p2-role-badge');
      const p2BtnGroup = document.getElementById('p2-btn-group');
      const p2EmptyNotice = document.getElementById('p2-empty-notice');
      const p2EmptyText = document.getElementById('p2-empty-text');
      const p2QuickBtn = document.getElementById('p2-quick-btn');
      const p2DemoteBtn = document.getElementById('p2-demote-btn');

      if (state.p2) {
        const p2TierLabel = getTierLabel(state.p2.type);
        const p2TierCls = getTierCls(state.p2.type);
        if (p2SlotCard) p2SlotCard.className = 'player-slot ' + p2TierCls;
        if (p2RoleBadge) {
          p2RoleBadge.innerText = `${p2TierLabel} (P2)`;
          p2RoleBadge.className = 'role-badge badge-' + p2TierCls;
        }
        document.getElementById('p2-name').innerText = state.p2.name;
        document.getElementById('p2-char').innerText = formatChar(state.p2.char);
        document.getElementById('p2-ws').innerText = state.p2.ws;
        const p2Ulti = state.p2.ulti || getPlayerDefaultUlti(state.p2.char);
        const p2UltiRow = document.getElementById('p2-ulti-row');
        const p2UltiEl = document.getElementById('p2-ulti');
        if (p2UltiRow && p2UltiEl) {
          if (p2Ulti && p2Ulti !== '-' && p2Ulti !== 'TBA') {
            p2UltiEl.innerText = p2Ulti;
            p2UltiRow.style.display = 'block';
          } else {
            p2UltiRow.style.display = 'none';
          }
        }

        // WS 5 bonus notification
        let p2TicketText = String(state.p2.matchesLeft);
        if (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) {
          const base = state.p2.bonusBase !== undefined ? state.p2.bonusBase : (state.p2.matchesLeft - 1);
          p2TicketText = `${base}+1`;
          const remaining = state.p2.bonusAnimUntil - Date.now();
          setTimeout(renderAll, remaining + 50);
        }
        document.getElementById('p2-matches').innerText = p2TicketText;

        if (p2BtnGroup) p2BtnGroup.style.display = 'flex';
        if (p2EmptyNotice) p2EmptyNotice.style.display = 'none';
        if (p2DemoteBtn) p2DemoteBtn.style.display = 'inline-flex';

        // P2 Bonus Badge (kelipatan 5 WS)
        const p2BonusBadge = document.getElementById('p2-bonus-badge');
        if (p2BonusBadge) {
          if (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) {
            p2BonusBadge.innerText = '+1 Match';
            p2BonusBadge.style.display = 'inline-block';
          } else {
            p2BonusBadge.style.display = 'none';
          }
        }
      } else {
        if (p2SlotCard) p2SlotCard.className = 'player-slot free';
        if (p2RoleBadge) {
          p2RoleBadge.innerText = 'KOSONG (P2)';
          p2RoleBadge.className = 'role-badge badge-free';
        }
        document.getElementById('p2-name').innerText = "Slot P2 Kosong";
        document.getElementById('p2-char').innerText = "-";
        const p2UltiRow = document.getElementById('p2-ulti-row');
        if (p2UltiRow) p2UltiRow.style.display = 'none';
        document.getElementById('p2-ws').innerText = "0";
        document.getElementById('p2-matches').innerText = "0";
        const p2BonusBadge = document.getElementById('p2-bonus-badge');
        if (p2BonusBadge) p2BonusBadge.style.display = 'none';
        if (p2BtnGroup) p2BtnGroup.style.display = 'none';
        if (p2EmptyNotice) {
          p2EmptyNotice.style.display = 'block';
          if (p2EmptyText) {
            p2EmptyText.innerHTML = `<b>Slot P2 Kosong</b> (Admin yang memilih).`;
          }
          if (p2QuickBtn) {
            if (state.queue.length > 0) {
              p2QuickBtn.style.display = 'block';
              p2QuickBtn.innerHTML = `⬆ #1 (${escapeHtml(state.queue[0].name)}) ke P2`;
            } else {
              p2QuickBtn.style.display = 'none';
            }
          }
        }
      }
    }

    // ==========================================
    // MODAL MENU AKSI PEMAIN (TITIK TIGA)
    // ==========================================
    let currentActionPlayerId = null;
    let currentActionListType = 'queue';

    function openPlayerActionModal(playerId, listType = 'queue') {
      currentActionPlayerId = playerId;
      currentActionListType = listType;

      const list = (listType === 'queue') ? state.queue : state.saved;
      const player = list.find(p => String(p.id) === String(playerId));
      if (!player) return;

      const modal = document.getElementById('modal-player-actions');
      if (!modal) return;

      const pTierLabel = getTierLabel(player.type);
      const itemCls = getTierCls(player.type);
      const tLabel = (player.ticketMode === 'TIM' || player.ticketMode === 'DUO') ? 'DUO/TIM' : '1v1';
      const tBg = tLabel === '1v1' ? '#0284c7' : '#7c3aed';
      const wsBadge = (player.ws && player.ws > 0)
        ? `<span class="badge" style="background:var(--c-dark); color:var(--c-white); border:1px solid var(--c-slate); font-weight:800;">🔥 ${player.ws} WS</span>`
        : '';
      const ticketBadge = `<span class="badge" style="background:${tBg}; color:#fff; font-size:0.75rem; font-weight:800;">🎟️ ${tLabel}</span>`;
      const tierBadge = `<span class="badge badge-${itemCls}" style="font-size:0.75rem; font-weight:900;">${pTierLabel}</span>`;

      // Hitung posisi pemain dalam tier kelompoknya
      let rankInTier = 1;
      let totalInTier = 1;
      const isSB = (pTierLabel === 'SB');
      const tierGroup = list.filter(p => isSB ? (getTierLabel(p.type) === 'SB') : (getTierLabel(p.type) !== 'SB'));
      totalInTier = Math.max(1, tierGroup.length);
      const foundIdx = tierGroup.findIndex(p => String(p.id) === String(playerId));
      rankInTier = (foundIdx >= 0) ? foundIdx + 1 : 1;

      const ultiText = player.ulti || getPlayerDefaultUlti(player.char) || '-';

      // 1. Render Card Preview Pemain
      const cardContainer = document.getElementById('action-modal-player-card');
      if (cardContainer) {
        cardContainer.innerHTML = `
          <div style="background:var(--panel-subtle); padding:10px 12px; border-radius:8px; border:1px solid var(--border-color);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">
              <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-family:'Consolas',monospace; font-size:1rem; font-weight:900; color:#facc15; background:rgba(234,179,8,0.18); border:1px solid rgba(234,179,8,0.35); padding:2px 8px; border-radius:6px;">
                  #${rankInTier}
                </span>
                <b style="font-size:1.1rem; color:#f8fafc;">${escapeHtml(player.name)}</b>
              </div>
              <div style="display:flex; align-items:center; gap:5px;">
                ${tierBadge}
                ${ticketBadge}
                ${wsBadge}
              </div>
            </div>
            <div style="display:flex; flex-direction:column; gap:3px; font-size:0.83rem; color:#cbd5e1; background:rgba(0,0,0,0.25); padding:8px 10px; border-radius:6px; border:1px solid rgba(255,255,255,0.06);">
              <div>🥋 <b>Karakter:</b> <span style="color:#fff; font-weight:700;">${escapeHtml(formatChar(player.char))}</span></div>
              <div>⚡ <b>Jurus Ulti:</b> <span style="color:#facc15; font-weight:700;">${escapeHtml(ultiText)}</span></div>
              <div>🎟️ <b>Sisa Match:</b> <span style="color:#38bdf8; font-weight:800;">${player.matchesLeft} Match</span></div>
            </div>
          </div>
        `;
      }

      // 2. Render Action Buttons
      const btnContainer = document.getElementById('action-modal-buttons-container');
      if (btnContainer) {
        let arenaSection = '';
        if (listType === 'queue') {
          const p1Disabled = Boolean(state.p1);
          const p2Disabled = Boolean(state.p2);

          arenaSection = `
            <div class="action-group-box">
              <div class="action-group-title">🚀 NAIKKAN KE ARENA PERTANDINGAN</div>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <button type="button" class="btn btn-sm btn-success" style="padding:10px; font-weight:800; font-size:0.85rem;" ${p1Disabled ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : `onclick="handleActionAssignArena('${player.id}', 'p1')"`}>
                  ⬆️ ${p1Disabled ? `P1 Terisi (${escapeHtml(state.p1.name)})` : 'Naikkan ke P1'}
                </button>
                <button type="button" class="btn btn-sm btn-primary" style="padding:10px; font-weight:800; font-size:0.85rem;" ${p2Disabled ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : `onclick="handleActionAssignArena('${player.id}', 'p2')"`}>
                  ⬆️ ${p2Disabled ? `P2 Terisi (${escapeHtml(state.p2.name)})` : 'Naikkan ke P2'}
                </button>
              </div>
            </div>
          `;
        } else {
          arenaSection = `
            <div class="action-group-box">
              <div class="action-group-title">📋 PINDAHKAN KE MATCH QUEUE</div>
              <button type="button" class="btn btn-sm btn-success" style="width:100%; padding:10px; font-weight:800; font-size:0.88rem;" onclick="handleActionMoveSavedToQueue('${player.id}')">
                ⬆️ Masukkan ke Match Queue
              </button>
            </div>
          `;
        }

        // Posisi options
        let posOptions = '';
        for (let i = 1; i <= totalInTier; i++) {
          posOptions += `<option value="${i}" ${i === rankInTier ? 'selected' : ''}>Posisi #${i}${i === rankInTier ? ' (Saat ini)' : ''}</option>`;
        }

        btnContainer.innerHTML = `
          ${arenaSection}

          <!-- Ganti Karakter, Nama & Match -->
          <div class="action-group-box">
            <div class="action-group-title">🥋 DATA KARAKTER & MATCH</div>
            <div style="display:flex; flex-direction:column; gap:6px;">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <button type="button" class="btn btn-sm btn-blue" style="padding:8px; font-weight:800; font-size:0.82rem;" onclick="handleActionEditChar('${player.id}', '${listType}')">
                  🥋 Ganti Karakter & Ulti
                </button>
                <button type="button" class="btn btn-sm" style="background:#334155; color:#fff; padding:8px; font-weight:800; font-size:0.82rem;" onclick="handleActionEditPlayerName('${player.id}', '${listType}')">
                  ✏️ Edit Nama Pemain
                </button>
              </div>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                <button type="button" class="btn btn-sm" style="background:#1e3a8a; color:#93c5fd; padding:8px; font-weight:800; font-size:0.82rem;" onclick="handleActionEditMatches('${player.id}', '${listType}')">
                  🎟️ Edit Match (${player.matchesLeft})
                </button>
                <button type="button" class="btn btn-sm btn-blue" style="padding:8px; font-weight:800; font-size:0.82rem;" onclick="handleActionConvertTickets('${player.id}', '${listType}')">
                  🔄 Konversi Tiket
                </button>
              </div>
            </div>
          </div>

          <!-- Ganti Role / Tier & Mode Tiket -->
          <div class="action-group-box">
            <div class="action-group-title">🏷️ GANTI ROLE / TIER & MODE TIKET</div>
            <div style="display:flex; flex-direction:column; gap:6px;">
              <div>
                <label style="font-size:0.75rem; color:#94a3b8; font-weight:700; margin-bottom:3px; display:block;">Pilih Role / Tier:</label>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px;">
                  <button type="button" class="btn btn-sm" style="padding:6px 4px; font-size:0.78rem; font-weight:800; ${pTierLabel === 'SB' ? 'background:linear-gradient(135deg, #7e22ce, #9333ea); color:#fff; border:1px solid #d8b4fe;' : 'background:#1e293b; color:#94a3b8;'}" onclick="handleActionChangeTier('${player.id}', '${listType}', 'SROBOT')">
                    ${pTierLabel === 'SB' ? '✓ ' : ''}SROBOT
                  </button>
                  <button type="button" class="btn btn-sm" style="padding:6px 4px; font-size:0.78rem; font-weight:800; ${pTierLabel === 'VIP' ? 'background:linear-gradient(135deg, #ca8a04, #eab308); color:#000; border:1px solid #fef08a;' : 'background:#1e293b; color:#94a3b8;'}" onclick="handleActionChangeTier('${player.id}', '${listType}', 'VIP')">
                    ${pTierLabel === 'VIP' ? '✓ ' : ''}VIP
                  </button>
                  <button type="button" class="btn btn-sm" style="padding:6px 4px; font-size:0.78rem; font-weight:800; ${pTierLabel === 'FREE' ? 'background:#475569; color:#fff; border:1px solid #94a3b8;' : 'background:#1e293b; color:#94a3b8;'}" onclick="handleActionChangeTier('${player.id}', '${listType}', 'FREE')">
                    ${pTierLabel === 'FREE' ? '✓ ' : ''}FREE
                  </button>
                </div>
              </div>

              <div>
                <label style="font-size:0.75rem; color:#94a3b8; font-weight:700; margin-bottom:3px; display:block;">Pilih Mode Tiket:</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                  <button type="button" class="btn btn-sm" style="padding:6px 4px; font-size:0.78rem; font-weight:800; ${tLabel === '1v1' ? 'background:#0284c7; color:#fff; border:1px solid #7dd3fc;' : 'background:#1e293b; color:#94a3b8;'}" onclick="handleActionChangeTicketMode('${player.id}', '${listType}', '1v1')">
                    ${tLabel === '1v1' ? '✓ ' : ''}⚡ Tiket 1v1
                  </button>
                  <button type="button" class="btn btn-sm" style="padding:6px 4px; font-size:0.78rem; font-weight:800; ${tLabel === 'DUO/TIM' ? 'background:#7c3aed; color:#fff; border:1px solid #c4b5fd;' : 'background:#1e293b; color:#94a3b8;'}" onclick="handleActionChangeTicketMode('${player.id}', '${listType}', 'TIM')">
                    ${tLabel === 'DUO/TIM' ? '✓ ' : ''}⚔️ Tiket DUO/TIM
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pindah Posisi Urutan -->
          <div class="action-group-box">
            <div class="action-group-title">🔢 URUTAN ANTREAN (DALAM TIER)</div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span style="font-size:0.8rem; color:#94a3b8; white-space:nowrap;">Pindah ke:</span>
              <select class="form-control" style="flex:1; padding:6px 10px; font-size:0.85rem;" onchange="handleActionReorder('${player.id}', '${listType}', parseInt(this.value))">
                ${posOptions}
              </select>
            </div>
          </div>

          <!-- Simpan ke Tabungan / Hapus -->
          <div class="action-group-box" style="border-color:rgba(239, 68, 68, 0.25);">
            <div class="action-group-title" style="color:#f87171;">⚠️ MANAJEMEN DATA</div>
            <div style="display:grid; grid-template-columns:${listType === 'queue' ? '1fr 1fr' : '1fr'}; gap:8px;">
              ${listType === 'queue' ? `
                <button type="button" class="btn btn-sm btn-primary" style="padding:9px; font-weight:800; font-size:0.82rem;" onclick="handleActionMoveQueueToSave('${player.id}')">
                  💾 Simpan ke Tabungan
                </button>
              ` : ''}
              <button type="button" class="btn btn-sm btn-danger" style="padding:9px; font-weight:800; font-size:0.82rem;" onclick="handleActionDeletePlayer('${listType}', '${player.id}')">
                🗑️ Hapus Pemain
              </button>
            </div>
          </div>
        `;
      }

      modal.classList.add('active');
    }

    function closePlayerActionModal() {
      const modal = document.getElementById('modal-player-actions');
      if (modal) modal.classList.remove('active');
      currentActionPlayerId = null;
    }

    function handlePlayerActionBackdrop(e) {
      if (e.target && e.target.id === 'modal-player-actions') {
        closePlayerActionModal();
      }
    }

    function handleActionAssignArena(playerId, targetSlot) {
      assignToArena(playerId, targetSlot);
      closePlayerActionModal();
    }

    function handleActionMoveSavedToQueue(playerId) {
      moveSavedToQueue(playerId);
      closePlayerActionModal();
    }

    function handleActionEditChar(playerId, listType) {
      closePlayerActionModal();
      openEditPlayerCharModal(playerId, listType);
    }

    function handleActionEditPlayerName(playerId, listType) {
      const list = (listType === 'queue') ? state.queue : state.saved;
      const p = list.find(x => String(x.id) === String(playerId));
      if (!p) return;
      const newName = prompt(`Ubah Nama Pemain [ ${p.name} ]:`, p.name);
      if (newName !== null && newName.trim() && newName.trim() !== p.name) {
        p.name = newName.trim();
        saveState();
        openPlayerActionModal(playerId, listType);
      }
    }

    function handleActionEditMatches(playerId, listType) {
      promptEditPlayerTickets(playerId);
      openPlayerActionModal(playerId, listType);
    }

    function handleActionConvertTickets(playerId, listType) {
      closePlayerActionModal();
      promptConvertPlayerTicket(playerId, listType);
    }

    function handleActionChangeTier(playerId, listType, newTier) {
      changePlayerTier(playerId, listType, newTier);
      openPlayerActionModal(playerId, listType);
    }

    function handleActionChangeTicketMode(playerId, listType, newMode) {
      changePlayerTicketMode(playerId, listType, newMode);
      openPlayerActionModal(playerId, listType);
    }

    function handleActionReorder(playerId, listType, newPos) {
      reorderPlayerWithinTier(playerId, listType, newPos);
      openPlayerActionModal(playerId, listType);
    }

    function handleActionMoveQueueToSave(playerId) {
      moveQueueToSave(playerId);
      closePlayerActionModal();
    }

    function handleActionDeletePlayer(listType, playerId) {
      deletePlayer(listType, playerId);
      closePlayerActionModal();
    }

    // ==========================================
    // LOGIKA GANTI KARAKTER (PERSIS FORM PENDAFTARAN)
    // ==========================================
    let editCharTargetPlayer = null; // { id, listType, name, type }
    let editCharStep = 1;
    let editCharMode = '1v1'; // '1v1', 'DUO', 'TIM'
    let editCharSlots = [
      { char: null, ulti: '' },
      { char: null, ulti: '' },
      { char: null, ulti: '' }
    ];
    let editCharActiveSlot = 0; // 0, 1, 2
    let editCharTimCount = 3; // 2 or 3 in TIM mode
    let editCharAlphaFilter = 'ALL';
    let editCharSelectedChar = null; // backward compatibility
    let editCharSelectedUlti = '';

    function openEditPlayerCharModal(playerId, listType = 'queue') {
      let player = null;
      if (playerId === 'p1') {
        player = state.p1;
      } else if (playerId === 'p2') {
        player = state.p2;
      } else {
        const list = (listType === 'queue') ? state.queue : state.saved;
        player = list.find(p => String(p.id) === String(playerId));
      }
      if (!player) return;

      editCharTargetPlayer = {
        id: playerId,
        listType: listType,
        name: player.name || (playerId === 'p1' ? 'Player 1' : (playerId === 'p2' ? 'Player 2' : 'Pemain')),
        type: player.type || 'VIP',
        ticketMode: player.ticketMode || '1v1'
      };

      const modal = document.getElementById('modal-edit-player-char');
      if (!modal) return;

      // Set badge header
      const pTierLabel = getTierLabel(editCharTargetPlayer.type);
      const badgeEl = document.getElementById('edit-char-player-badge');
      if (badgeEl) {
        badgeEl.innerText = `${editCharTargetPlayer.name} (${pTierLabel})`;
        badgeEl.className = `badge badge-${getTierCls(editCharTargetPlayer.type)}`;
      }

      editCharAlphaFilter = 'ALL';
      const searchInput = document.getElementById('edit-char-search');
      if (searchInput) searchInput.value = '';

      // Parse existing player character and ulti
      const cleanChar = (player.char && player.char !== '-' && player.char !== 'TBA' && player.char !== 'Belum Pilih' && player.char !== 'Belum Pilih / TBA')
        ? String(player.char)
        : '';
      const partsChar = cleanChar ? cleanChar.split('/').map(s => s.trim()).filter(Boolean) : [];
      const partsUlti = player.ulti ? String(player.ulti).split('/').map(s => s.trim()).filter(Boolean) : [];

      // Detect Mode
      let detectedMode = '1v1';
      if (player.ticketMode) {
        const tm = String(player.ticketMode).toUpperCase();
        if (tm === 'TIM') detectedMode = 'TIM';
        else if (tm === 'DUO') detectedMode = 'DUO';
        else detectedMode = '1v1';
      } else if (state.masterMode) {
        detectedMode = (state.masterMode === 'DUO' || state.masterMode === 'TIM') ? state.masterMode : '1v1';
      }
      if (partsChar.length === 2 && detectedMode === '1v1') detectedMode = 'DUO';
      if (partsChar.length >= 3) detectedMode = 'TIM';

      editCharMode = detectedMode;
      editCharTimCount = (detectedMode === 'TIM' && partsChar.length === 2) ? 2 : 3;

      editCharSlots = [
        { char: null, ulti: '' },
        { char: null, ulti: '' },
        { char: null, ulti: '' }
      ];

      partsChar.forEach((cName, idx) => {
        if (idx < 3) {
          const found = findNarutoChar(cName);
          if (found) {
            editCharSlots[idx] = {
              char: found,
              ulti: partsUlti[idx] || (found.ultis && found.ultis[0]) || '-'
            };
          }
        }
      });

      editCharActiveSlot = 0;
      editCharSelectedChar = editCharSlots[0].char;
      editCharSelectedUlti = editCharSlots[0].ulti;

      setEditCharMode(editCharMode);
      renderEditCharAlphaBar();
      modal.classList.add('active');
      editCharGoToStep(1);
    }

    function closeEditPlayerCharModal() {
      const modal = document.getElementById('modal-edit-player-char');
      if (modal) modal.classList.remove('active');
      editCharTargetPlayer = null;
      editCharSelectedChar = null;
      editCharSelectedUlti = '';
      editCharSlots = [
        { char: null, ulti: '' },
        { char: null, ulti: '' },
        { char: null, ulti: '' }
      ];
    }

    function handleEditCharBackdrop(e) {
      if (e.target && e.target.id === 'modal-edit-player-char') {
        closeEditPlayerCharModal();
      }
    }

    function setEditCharMode(mode) {
      let clean = '1v1';
      if (mode === 'DUO') clean = 'DUO';
      else if (mode === 'TIM') clean = 'TIM';
      editCharMode = clean;

      const b1 = document.getElementById('btn-edit-mode-1v1');
      const bDuo = document.getElementById('btn-edit-mode-duo');
      const bTim = document.getElementById('btn-edit-mode-tim');

      if (b1) {
        b1.className = clean === '1v1' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        b1.style.opacity = clean === '1v1' ? '1' : '0.6';
      }
      if (bDuo) {
        bDuo.className = clean === 'DUO' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        bDuo.style.opacity = clean === 'DUO' ? '1' : '0.6';
      }
      if (bTim) {
        bTim.className = clean === 'TIM' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-blue';
        bTim.style.opacity = clean === 'TIM' ? '1' : '0.6';
      }

      if (editCharActiveSlot > 1 && clean === 'DUO') editCharActiveSlot = 0;
      if (editCharActiveSlot > 0 && clean === '1v1') editCharActiveSlot = 0;

      renderEditCharSlotTabs();
      renderEditCharGrid();
    }

    function toggleEditTimSlot3() {
      if (editCharTimCount === 3) {
        editCharTimCount = 2;
        editCharSlots[2] = { char: null, ulti: '' };
        if (editCharActiveSlot === 2) editCharActiveSlot = 1;
      } else {
        editCharTimCount = 3;
      }
      renderEditCharSlotTabs();
      renderEditCharGrid();
    }

    function selectEditCharSlot(idx) {
      editCharActiveSlot = idx;
      renderEditCharSlotTabs();
      renderEditCharGrid();
    }

    function renderEditCharSlotTabs() {
      const container = document.getElementById('edit-char-slot-tabs');
      const flexBar = document.getElementById('edit-char-tim-flex-bar');
      const flexBtn = document.getElementById('btn-edit-tim-toggle-slot3');
      if (!container) return;

      if (editCharMode === '1v1') {
        container.style.display = 'none';
        if (flexBar) flexBar.style.display = 'none';
        const label = document.getElementById('edit-char-label');
        if (label) label.innerText = 'Pilih Karakter (A-Z Master Roster):';
        return;
      }

      container.style.display = 'flex';
      const label = document.getElementById('edit-char-label');
      if (label) label.innerText = `Pilih Karakter untuk Slot ${editCharActiveSlot + 1}:`;

      if (editCharMode === 'TIM') {
        if (flexBar) flexBar.style.display = 'flex';
        if (flexBtn) {
          flexBtn.innerText = (editCharTimCount === 3) ? '👥 Gunakan 2 Karakter Saja' : '➕ Tambah Karakter 3';
          flexBtn.className = (editCharTimCount === 3) ? 'btn btn-sm btn-secondary' : 'btn btn-sm btn-primary';
        }
      } else {
        if (flexBar) flexBar.style.display = 'none';
      }

      const totalSlots = (editCharMode === 'DUO') ? 2 : editCharTimCount;
      const slotDefs = [
        { label: 'Slot 1: Leader', opt: false },
        { label: (editCharMode === 'DUO') ? 'Slot 2: Support' : 'Slot 2: Support 1', opt: false },
        { label: 'Slot 3: Support 2', opt: true }
      ];

      container.innerHTML = slotDefs.slice(0, totalSlots).map((def, idx) => {
        const slot = editCharSlots[idx];
        const isActive = (editCharActiveSlot === idx);
        const isFilled = Boolean(slot && slot.char);
        const charName = isFilled ? slot.char.name : (def.opt ? '(Opsional / Kosong)' : '(Belum Dipilih)');
        let cls = 'char-slot-tab';
        if (isActive) cls += ' active';
        if (isFilled) cls += ' filled';

        return `
          <button type="button" class="${cls}" onclick="selectEditCharSlot(${idx})">
            <span class="char-slot-badge">${def.label}</span>
            <span class="char-slot-name">${escapeHtml(charName)}</span>
          </button>
        `;
      }).join('');
    }

    function editCharGoToStep(step) {
      editCharStep = step;

      const tab1 = document.getElementById('edit-char-step-tab-1');
      const tab2 = document.getElementById('edit-char-step-tab-2');
      const step1El = document.getElementById('edit-char-step-1');
      const step2El = document.getElementById('edit-char-step-2');
      const btnPrev = document.getElementById('btn-edit-char-prev');
      const btnNext = document.getElementById('btn-edit-char-next');
      const btnSave = document.getElementById('btn-edit-char-save');

      if (step === 1) {
        if (tab1) {
          tab1.style.background = 'rgba(234, 179, 8, 0.18)';
          tab1.style.color = '#facc15';
          tab1.style.borderColor = '#facc15';
        }
        if (tab2) {
          tab2.style.background = 'rgba(255, 255, 255, 0.05)';
          tab2.style.color = '#94a3b8';
          tab2.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        }
        if (step1El) step1El.style.display = 'flex';
        if (step2El) step2El.style.display = 'none';

        if (btnPrev) btnPrev.style.display = 'none';
        if (btnNext) {
          btnNext.style.display = 'inline-block';
          btnNext.innerText = 'Lanjut Pilih Ulti ➔';
        }
        if (btnSave) btnSave.style.display = 'none';

        renderEditCharSlotTabs();
        renderEditCharGrid();
      } else if (step === 2) {
        if (tab1) {
          tab1.style.background = 'rgba(34, 197, 94, 0.15)';
          tab1.style.color = '#4ade80';
          tab1.style.borderColor = '#22c55e';
        }
        if (tab2) {
          tab2.style.background = 'rgba(234, 179, 8, 0.18)';
          tab2.style.color = '#facc15';
          tab2.style.borderColor = '#facc15';
        }
        if (step1El) step1El.style.display = 'none';
        if (step2El) step2El.style.display = 'flex';

        if (btnPrev) btnPrev.style.display = 'inline-block';
        if (btnNext) btnNext.style.display = 'none';
        if (btnSave) btnSave.style.display = 'inline-block';

        setupEditCharStep2();
      }
    }

    function editCharNextStep() {
      if (editCharMode === '1v1') {
        if (!editCharSlots[0].char) {
          alert('Silakan pilih salah satu karakter terlebih dahulu!');
          return;
        }
      } else if (editCharMode === 'DUO') {
        if (!editCharSlots[0].char || !editCharSlots[1].char) {
          alert('Mode DUO membutuhkan 2 karakter (Slot 1: Leader & Slot 2: Support)!');
          return;
        }
      } else if (editCharMode === 'TIM') {
        if (!editCharSlots[0].char || !editCharSlots[1].char) {
          alert('Mode TIM membutuhkan minimal 2 karakter (Slot 1: Leader & Slot 2: Support 1)!');
          return;
        }
      }
      editCharGoToStep(2);
    }

    function renderEditCharAlphaBar() {
      const container = document.getElementById('edit-char-alpha-bar');
      if (!container) return;
      const letters = ['ALL', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];
      container.innerHTML = letters.map(l => `
        <button type="button" class="btn-alpha ${editCharAlphaFilter === l ? 'active' : ''}" onclick="filterEditCharAlpha('${l}')">
          ${l}
        </button>
      `).join('');
    }

    function filterEditCharAlpha(letter) {
      editCharAlphaFilter = letter;
      renderEditCharAlphaBar();
      renderEditCharGrid();
    }

    function renderEditCharGrid() {
      const container = document.getElementById('edit-char-grid');
      if (!container) return;
      const q = (document.getElementById('edit-char-search').value || '').trim().toLowerCase();

      const baseChars = (typeof ALPHABETICAL_CHARS !== 'undefined' && Array.isArray(ALPHABETICAL_CHARS))
        ? ALPHABETICAL_CHARS
        : (typeof NARUTO_CHARACTERS !== 'undefined' ? [...NARUTO_CHARACTERS].sort((a, b) => a.name.localeCompare(b.name, 'id', { sensitivity: 'base' })) : []);
      let list = baseChars.slice();
      if (editCharAlphaFilter !== 'ALL') {
        list = list.filter(c => c.name.toUpperCase().startsWith(editCharAlphaFilter));
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
        container.innerHTML = `<div style="grid-column: 1 / -1; padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">Tidak ada karakter atau jurus ulti yang cocok dengan "${escapeHtml(q)}".</div>`;
        return;
      }

      const pTier = editCharTargetPlayer ? editCharTargetPlayer.type : 'FREE';
      const isFreeTier = (pTier === 'FREE');
      const curSlotChar = editCharSlots[editCharActiveSlot] ? editCharSlots[editCharActiveSlot].char : null;

      const badge = document.getElementById('edit-char-selected-badge');
      if (badge) {
        const total = (editCharMode === '1v1') ? 1 : (editCharMode === 'DUO' ? 2 : editCharTimCount);
        const filled = editCharSlots.slice(0, total).filter(s => s && s.char).length;
        badge.innerText = `${filled}/${total} Terpilih`;
      }

      container.innerHTML = list.map(c => {
        const isSel = curSlotChar && curSlotChar.id === c.id;
        const isTeamOrDuo = (editCharMode === 'DUO' || editCharMode === 'TIM');
        const dupSlotIdx = isTeamOrDuo ? editCharSlots.findIndex((s, idx) => idx !== editCharActiveSlot && s.char && isSameBaseCharacter(s.char, c)) : -1;
        const ultiCount = (c.ultis && c.ultis.length > 0) ? c.ultis.length : 1;
        const safeUltiArg = c._matchedUlti ? escapeHtml(c._matchedUlti).replace(/'/g, "\\'") : '';
        const matchTagHtml = c._matchedUlti ? `
          <div style="font-size:0.68rem; font-weight:800; color:#facc15; background:rgba(234, 179, 8, 0.15); border:1px solid rgba(234, 179, 8, 0.35); padding:2px 6px; border-radius:4px; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="Ulti Cocok: ${escapeHtml(c._matchedUlti)}">
            ⚡ ${escapeHtml(c._matchedUlti)}
          </div>
        ` : '';

        const banned = typeof isCharBanned === 'function' && isCharBanned(c);
        const isVipEx = !banned && (typeof isCharVipExclusive === 'function' && isCharVipExclusive(c));

        let cardClass = 'admin-char-card';
        let clickAttr = `onclick="selectEditChar(${c.id}, '${safeUltiArg}')"`;
        let statusBadgeHtml = '';

        if (banned) {
          cardClass += ' disabled banned';
          clickAttr = `onclick="showBannedAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-banned">🚫 BANNED</span>`;
        } else if (isFreeTier && isVipEx) {
          cardClass += ' disabled vip-only';
          clickAttr = `onclick="showVipOnlyAlert('${escapeHtml(c.name).replace(/'/g, "\\'")}')"`;
          statusBadgeHtml = `<span class="badge-status-vip">🔒 KHUSUS VIP</span>`;
        } else if (dupSlotIdx !== -1) {
          cardClass += ' disabled duplicate-team';
          const dupSlotName = (editCharSlots[dupSlotIdx] && editCharSlots[dupSlotIdx].char) ? editCharSlots[dupSlotIdx].char.name : '';
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
          metaRight = `<span style="color:#4ade80; font-weight:800;">✅ Slot ${editCharActiveSlot + 1}</span>`;
        }

        return `
          <div class="${cardClass}" ${clickAttr}>
            <div style="font-size:0.80rem; font-weight:800; color:#f8fafc; line-height:1.25; word-break:break-word;">${escapeHtml(c.name)}</div>
            ${matchTagHtml}
            <div style="display:flex; align-items:center; justify-content:space-between; font-size:0.65rem; color:#94a3b8; margin-top:4px;">
              <span class="char-ulti-badge">⚡ ${ultiCount} Ulti</span>
              ${metaRight}
            </div>
          </div>
        `;
      }).join('');
    }

    function selectEditChar(id, preselectedUlti = '') {
      const c = NARUTO_CHARACTERS.find(x => x.id === id);
      if (!c) return;
      if (typeof isCharBanned === 'function' && isCharBanned(c)) {
        showBannedAlert(c.name);
        return;
      }
      const pTier = editCharTargetPlayer ? editCharTargetPlayer.type : 'FREE';
      if (pTier === 'FREE' && typeof isCharVipExclusive === 'function' && isCharVipExclusive(c)) {
        showVipOnlyAlert(c.name);
        return;
      }

      const isTeamOrDuo = (editCharMode === 'DUO' || editCharMode === 'TIM');
      if (isTeamOrDuo) {
        const dupIdx = editCharSlots.findIndex((s, idx) => idx !== editCharActiveSlot && s.char && isSameBaseCharacter(s.char, c));
        if (dupIdx !== -1) {
          const dupName = (editCharSlots[dupIdx] && editCharSlots[dupIdx].char) ? editCharSlots[dupIdx].char.name : '';
          showDuplicateTeamAlert(c.name, dupIdx + 1, dupName);
          return;
        }
      }

      let chosenUlti = preselectedUlti;
      if (!chosenUlti && c.ultis && c.ultis.length > 0) {
        chosenUlti = c.ultis[0];
      }

      editCharSlots[editCharActiveSlot] = {
        char: c,
        ulti: chosenUlti || '-'
      };
      editCharSelectedChar = editCharSlots[0].char;
      editCharSelectedUlti = editCharSlots[0].ulti;

      const total = (editCharMode === '1v1') ? 1 : (editCharMode === 'DUO' ? 2 : editCharTimCount);

      // Auto advance to next slot if empty
      if (editCharActiveSlot < total - 1) {
        if (!editCharSlots[editCharActiveSlot + 1].char) {
          editCharActiveSlot++;
        }
      }

      renderEditCharSlotTabs();
      renderEditCharGrid();
    }

    function setupEditCharStep2() {
      const container = document.getElementById('edit-char-slots-ulti-container');
      const multiBox = document.getElementById('edit-char-multi-ulti-box');
      const singleBox = document.getElementById('edit-char-single-ulti-box');

      if (multiBox) multiBox.style.display = 'none';
      if (singleBox) singleBox.style.display = 'none';

      const totalSlots = (editCharMode === '1v1') ? 1 : (editCharMode === 'DUO' ? 2 : editCharTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (editCharSlots[i] && editCharSlots[i].char) {
          activeSlots.push({ idx: i, slot: editCharSlots[i] });
        }
      }

      if (activeSlots.length === 0) return;

      if (container) {
        container.innerHTML = activeSlots.map(({ idx, slot }) => {
          const c = slot.char;
          const ultis = c.ultis || [];
          const slotLabel = (editCharMode === '1v1')
            ? 'Karakter Utama'
            : (idx === 0 ? 'Slot 1: Leader' : (idx === 1 ? (editCharMode === 'DUO' ? 'Slot 2: Support' : 'Slot 2: Support 1') : 'Slot 3: Support 2'));

          let ultiSelectionHtml = '';
          if (ultis.length > 1) {
            if (!slot.ulti || !ultis.includes(slot.ulti)) {
              slot.ulti = ultis[0];
            }
            ultiSelectionHtml = `
              <div style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 6px;">Pilih Jurus Ulti:</div>
              <div class="ulti-options-grid">
                ${ultis.map(u => {
                  const isSel = (slot.ulti === u);
                  return `
                    <div class="admin-ulti-card ${isSel ? 'selected' : ''}" style="padding: 8px 12px;" onclick="selectEditCharSlotUlti(${idx}, '${escapeHtml(u).replace(/'/g, "\\'")}')">
                      <div style="font-size: 0.84rem; font-weight: 800; color: #fff;">⚡ ${escapeHtml(u)}</div>
                      <div style="font-size: 0.72rem; font-weight: 800; color: ${isSel ? '#facc15' : '#94a3b8'};">
                        ${isSel ? '✅ Dipilih' : 'Pilih'}
                      </div>
                    </div>
                  `;
                }).join('')}
              </div>
            `;
          } else {
            slot.ulti = ultis[0] || '-';
            ultiSelectionHtml = `
              <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding: 6px 10px; display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.74rem; color: var(--text-muted);">Ultimate Terkunci:</span>
                <span style="font-size: 0.85rem; font-weight: 800; color: var(--accent-gold);">⚡ ${escapeHtml(slot.ulti)}</span>
              </div>
            `;
          }

          return `
            <div class="slot-ulti-card">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 0.75rem; font-weight: 900; color: var(--accent-gold); text-transform: uppercase;">${slotLabel}</span>
                <span style="font-size: 0.88rem; font-weight: 900; color: #fff;">${escapeHtml(c.name)}</span>
              </div>
              ${ultiSelectionHtml}
            </div>
          `;
        }).join('');
      }

      const allNames = activeSlots.map(a => a.slot.char.name).join(' / ');
      const allUltis = activeSlots.map(a => a.slot.ulti).join(' / ');

      if (editCharTargetPlayer) {
        const sumPlayer = document.getElementById('edit-char-sum-player-name');
        const sumMode = document.getElementById('edit-char-sum-mode');
        const sumChar = document.getElementById('edit-char-sum-char-name');
        const sumUlti = document.getElementById('edit-char-sum-ulti-name');
        if (sumPlayer) sumPlayer.innerText = `${editCharTargetPlayer.name} (${getTierLabel(editCharTargetPlayer.type)})`;
        if (sumMode) sumMode.innerText = (editCharMode === '1v1') ? '1vs1' : (editCharMode === 'DUO' ? 'DUO (2)' : `TIM (${activeSlots.length})`);
        if (sumChar) sumChar.innerText = allNames || '-';
        if (sumUlti) sumUlti.innerText = allUltis || '-';
      }
    }

    function selectEditCharSlotUlti(slotIdx, ultiName) {
      if (editCharSlots[slotIdx]) {
        editCharSlots[slotIdx].ulti = ultiName;
        setupEditCharStep2();
      }
    }

    function selectEditCharUlti(ultiName) {
      editCharSelectedUlti = ultiName;
      if (editCharSlots[0]) editCharSlots[0].ulti = ultiName;
      setupEditCharStep2();
    }

    function saveEditedPlayerChar() {
      if (!editCharTargetPlayer) return;

      if (editCharMode === '1v1') {
        if (!editCharSlots[0].char) {
          alert('Karakter Slot 1 belum dipilih!');
          editCharGoToStep(1);
          return;
        }
      } else if (editCharMode === 'DUO') {
        if (!editCharSlots[0].char || !editCharSlots[1].char) {
          alert('Mode DUO membutuhkan 2 karakter (Slot 1 & Slot 2)!');
          editCharGoToStep(1);
          return;
        }
      } else if (editCharMode === 'TIM') {
        if (!editCharSlots[0].char || !editCharSlots[1].char) {
          alert('Mode TIM membutuhkan minimal 2 karakter (Slot 1 & Slot 2)!');
          editCharGoToStep(1);
          return;
        }
      }

      const totalSlots = (editCharMode === '1v1') ? 1 : (editCharMode === 'DUO' ? 2 : editCharTimCount);
      const activeSlots = [];
      for (let i = 0; i < totalSlots; i++) {
        if (editCharSlots[i] && editCharSlots[i].char) {
          activeSlots.push(editCharSlots[i]);
        }
      }

      for (const s of activeSlots) {
        if (typeof isCharBanned === 'function' && isCharBanned(s.char)) {
          showBannedAlert(s.char.name);
          editCharGoToStep(1);
          return;
        }
        if (editCharTargetPlayer.type === 'FREE' && typeof isCharVipExclusive === 'function' && isCharVipExclusive(s.char)) {
          showVipOnlyAlert(s.char.name);
          editCharGoToStep(1);
          return;
        }
      }

      // Validasi anti-duplikat karakter dalam 1 tim (semua versi)
      if (editCharMode === 'DUO' || editCharMode === 'TIM') {
        for (let i = 0; i < activeSlots.length; i++) {
          for (let j = i + 1; j < activeSlots.length; j++) {
            if (typeof isSameBaseCharacter === 'function' && isSameBaseCharacter(activeSlots[i].char, activeSlots[j].char)) {
              alert(`❌ GANTI KARAKTER DITOLAK!\n\nSlot ${i + 1} (${activeSlots[i].char.name}) dan Slot ${j + 1} (${activeSlots[j].char.name}) menggunakan karakter yang sama (${getCharBaseName(activeSlots[i].char)}).\n\nMode TIM/DUO tidak mengizinkan karakter yang sama (semua versi).`);
              editCharGoToStep(1);
              return;
            }
          }
        }
      }

      const finalChar = activeSlots.map(s => s.char.name).join(' / ');
      const finalUlti = activeSlots.map(s => s.ulti || (s.char.ultis && s.char.ultis[0]) || '-').join(' / ');

      if (editCharTargetPlayer.id === 'p1' && state.p1) {
        state.p1.char = finalChar;
        state.p1.ulti = finalUlti;
        state.p1.ticketMode = editCharMode;
      } else if (editCharTargetPlayer.id === 'p2' && state.p2) {
        state.p2.char = finalChar;
        state.p2.ulti = finalUlti;
        state.p2.ticketMode = editCharMode;
      } else {
        const list = (editCharTargetPlayer.listType === 'queue') ? state.queue : state.saved;
        const player = list.find(p => String(p.id) === String(editCharTargetPlayer.id));
        if (!player) {
          alert('Pemain tidak ditemukan!');
          closeEditPlayerCharModal();
          return;
        }
        player.char = finalChar;
        player.ulti = finalUlti;
        player.ticketMode = editCharMode;
      }

      saveState();
      closeEditPlayerCharModal();

      if (currentActionPlayerId && String(currentActionPlayerId) === String(editCharTargetPlayer.id)) {
        openPlayerActionModal(editCharTargetPlayer.id, editCharTargetPlayer.listType);
      }
    }

    // ==========================================
    // RENDER MATCH QUEUE (DETAIL NAMUN RAPI)
    // ==========================================
    function renderQueue() {
      const container = document.getElementById('queue-list-container');
      const qCountEl = document.getElementById('queue-count');
      if (qCountEl) qCountEl.innerText = `${state.queue.length}`;
      container.innerHTML = '';

      if (state.queue.length === 0) {
        container.innerHTML = '<div style="color:var(--text-muted); font-size:0.85rem; padding: 12px 0;">Match queue kosong. Tambah pemain di atas atau dari List Tabungan.</div>';
        return;
      }

      sortQueue();

      const fastPlayers = [];
      const vipFreePlayers = [];

      state.queue.forEach((p, idx) => {
        const pTicketMode = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'TIM' : '1v1';
        if (currentQueueTicketFilter === '1v1' && pTicketMode !== '1v1') return;
        if (currentQueueTicketFilter === 'TIM' && pTicketMode !== 'TIM') return;

        const pTier = getTierLabel(p.type);
        if (pTier === 'SB') {
          fastPlayers.push({ player: p, globalIdx: idx + 1, originalIdx: idx });
        } else {
          vipFreePlayers.push({ player: p, globalIdx: idx + 1, originalIdx: idx });
        }
      });

      const queueSections = [
        {
          key: 'SROBOT',
          label: 'SROBOT',
          cls: 'fast',
          players: fastPlayers,
          isFast: true
        },
        {
          key: 'VIPFREE',
          label: 'VIP & FREE',
          cls: 'vipfree',
          players: vipFreePlayers,
          isFast: false
        }
      ];

      queueSections.forEach(sec => {
        const tierSec = document.createElement('div');
        tierSec.className = `tier-section ${sec.cls}`;

        let headerControls = '';
        if (sec.isFast) {
          const rawStatusFast = (state.tierStatus && (state.tierStatus['SROBOT'] || state.tierStatus['SROBOT++'] || state.tierStatus['FAST'] || state.tierStatus['VVIP'])) || 'AKTIF';
          const statusFastLabel = getStatusLabel(rawStatusFast);
          const statusClass = getStatusCls(rawStatusFast);
          const statusText = statusFastLabel === 'AKTIF' ? '🟢 AKTIF' : '🔴 PENUH';
          headerControls = `
            <div style="display:flex; align-items:center; gap:8px;">
              <span>${sec.label}</span>
              <button class="tier-status-btn ${statusClass}" onclick="toggleTierStatus('SROBOT')" title="Klik untuk ubah status SROBOT (AKTIF / PENUH)">${statusText}</button>
            </div>
            <span>${sec.players.length}</span>
          `;
        } else {
          const rawStatusVip = (state.tierStatus && state.tierStatus['VIP']) || 'AKTIF';
          const rawStatusFree = (state.tierStatus && state.tierStatus['FREE']) || 'AKTIF';
          const statusVipLabel = getStatusLabel(rawStatusVip);
          const statusFreeLabel = getStatusLabel(rawStatusFree);
          const scVip = getStatusCls(rawStatusVip);
          const scFree = getStatusCls(rawStatusFree);
          const streakText = (state.vipConsecutiveCount >= 3) ? '🔴 Giliran: 1x FREE' : `🟢 VIP: ${state.vipConsecutiveCount || 0}/3`;
          headerControls = `
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
              <span>${sec.label}</span>
              <button class="tier-status-btn open" onclick="promptSetVipStreak()" title="Atur/Reset Giliran VIP/FREE (Klik untuk ubah)">${streakText}</button>
              <button class="tier-status-btn ${scVip}" onclick="toggleTierStatus('VIP')" title="Klik status VIP">VIP: ${statusVipLabel}</button>
              <button class="tier-status-btn ${scFree}" onclick="toggleTierStatus('FREE')" title="Klik status FREE">FREE: ${statusFreeLabel}</button>
            </div>
            <span>${sec.players.length}</span>
          `;
        }

        const tierHead = document.createElement('div');
        tierHead.className = `tier-header ${sec.cls}`;
        tierHead.innerHTML = headerControls;
        tierSec.appendChild(tierHead);

        if (sec.players.length === 0) {
          const emptyDiv = document.createElement('div');
          emptyDiv.className = 'tier-empty';
          emptyDiv.innerText = '(Kosong)';
          tierSec.appendChild(emptyDiv);
        } else {
          sec.players.forEach(({ player: p, globalIdx }, rankInSec) => {
            const posInSec = rankInSec + 1;
            const pTierLabel = getTierLabel(p.type);
            const itemCls = getTierCls(p.type);

            const item = document.createElement('div');
            item.className = `queue-item-card ${itemCls}`;

            const wsBadge = (p.ws && p.ws > 0)
              ? `<span class="badge" style="background:var(--c-dark); color:var(--c-white); border:1px solid var(--c-slate); font-weight:800;">🔥 ${p.ws} WS</span>`
              : '';
            const tLabel = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'DUO/TIM' : '1v1';
            const tBg = tLabel === '1v1' ? '#0284c7' : '#7c3aed';
            const ticketBadge = `<span class="badge" style="background:${tBg}; color:#fff; font-size:0.72rem; font-weight:800;" title="Jenis Tiket: ${tLabel}">🎟️ ${tLabel}</span>`;
            
            const ultiText = p.ulti || getPlayerDefaultUlti(p.char) || '';
            const ultiBadgeHtml = ultiText
              ? `<span class="queue-ulti-pill" title="Jurus Ulti: ${escapeHtml(ultiText)}">⚡ ${escapeHtml(ultiText)}</span>`
              : '';

            item.innerHTML = `
              <div class="queue-item-left">
                <div class="queue-rank-badge" title="Urutan Tier ${sec.label}: #${posInSec} (Global #${globalIdx})">
                  #${posInSec}
                </div>
                <div class="queue-player-info">
                  <div class="queue-player-header">
                    <span class="queue-player-name" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</span>
                    <span class="badge badge-${itemCls}">${pTierLabel}</span>
                    ${ticketBadge}
                    ${wsBadge}
                  </div>
                  <div class="queue-player-details">
                    <span class="queue-char-text">🥋 ${escapeHtml(formatChar(p.char))}</span>
                    ${ultiBadgeHtml}
                    <span class="queue-match-pill" title="Sisa Match: ${p.matchesLeft}">🎟️ ${p.matchesLeft} Match</span>
                  </div>
                </div>
              </div>
              <div class="queue-item-right">
                <button type="button" class="btn-queue-dots" onclick="openPlayerActionModal('${p.id}', 'queue')" title="Menu Aksi [ ${escapeHtml(p.name)} ]">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="5" r="2.2"></circle>
                    <circle cx="12" cy="12" r="2.2"></circle>
                    <circle cx="12" cy="19" r="2.2"></circle>
                  </svg>
                </button>
              </div>
            `;
            tierSec.appendChild(item);
          });
        }

        container.appendChild(tierSec);
      });
    }

    // ==========================================
    // RENDER LIST TABUNGAN (DETAIL NAMUN RAPI)
    // ==========================================
    function renderSaved() {
      const container = document.getElementById('saved-list-container');
      const sCountEl = document.getElementById('save-count');
      if (sCountEl) sCountEl.innerText = `${state.saved.length}`;

      const count1v1 = state.saved.filter(p => (p.ticketMode || '1v1') === '1v1').length;
      const countTim = state.saved.filter(p => (p.ticketMode === 'TIM' || p.ticketMode === 'DUO')).length;
      const el1v1 = document.getElementById('saved-count-1v1');
      const elTim = document.getElementById('saved-count-tim');
      const elAll = document.getElementById('saved-count-all');
      if (el1v1) el1v1.innerText = `${count1v1}`;
      if (elTim) elTim.innerText = `${countTim}`;
      if (elAll) elAll.innerText = `${state.saved.length}`;

      container.innerHTML = '';

      if (state.saved.length === 0) {
        container.innerHTML = '<div style="color:var(--text-muted); font-size:0.85rem; padding: 12px 0;">Tidak ada pemain di List Tabungan.</div>';
        return;
      }

      sortSaved();

      const fastSaved = [];
      const vipFreeSaved = [];

      state.saved.forEach((p, idx) => {
        const pTicketMode = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'TIM' : '1v1';
        if (currentSavedModeTab === '1v1' && pTicketMode !== '1v1') return;
        if (currentSavedModeTab === 'TIM' && pTicketMode !== 'TIM') return;

        const pTier = getTierLabel(p.type);
        if (pTier === 'SB') {
          fastSaved.push({ player: p, originalIdx: idx });
        } else {
          vipFreeSaved.push({ player: p, originalIdx: idx });
        }
      });

      const savedSections = [];
      if (fastSaved.length > 0) {
        savedSections.push({
          key: 'SROBOT',
          label: 'SROBOT',
          cls: 'fast',
          players: fastSaved,
          isFast: true
        });
      }
      savedSections.push({
        key: 'VIPFREE',
        label: 'VIP & FREE',
        cls: 'vipfree',
        players: vipFreeSaved,
        isFast: false
      });

      savedSections.forEach(sec => {
        if (sec.players.length === 0 && sec.key === 'SROBOT') return;

        const tierSec = document.createElement('div');
        tierSec.className = `tier-section ${sec.cls}`;

        let headerControls = '';
        if (sec.isFast) {
          const rawStatusFast = (state.tierStatus && (state.tierStatus['SROBOT'] || state.tierStatus['SROBOT++'] || state.tierStatus['FAST'] || state.tierStatus['VVIP'])) || 'AKTIF';
          const statusFastLabel = getStatusLabel(rawStatusFast);
          const statusClass = getStatusCls(rawStatusFast);
          const statusText = statusFastLabel === 'AKTIF' ? '🟢 AKTIF' : '🔴 PENUH';
          headerControls = `
            <div style="display:flex; align-items:center; gap:8px;">
              <span>${sec.label}</span>
              <button class="tier-status-btn ${statusClass}" onclick="toggleTierStatus('SROBOT')" title="Klik untuk ubah status SROBOT">${statusText}</button>
            </div>
            <span>${sec.players.length}</span>
          `;
        } else {
          headerControls = `
            <div style="display:flex; align-items:center; gap:8px;">
              <span>${sec.label} (A-Z)</span>
            </div>
            <span>${sec.players.length}</span>
          `;
        }

        const tierHead = document.createElement('div');
        tierHead.className = `tier-header ${sec.cls}`;
        tierHead.innerHTML = headerControls;
        tierSec.appendChild(tierHead);

        if (sec.players.length === 0) {
          const emptyDiv = document.createElement('div');
          emptyDiv.className = 'tier-empty';
          emptyDiv.innerText = '(Kosong)';
          tierSec.appendChild(emptyDiv);
        } else {
          sec.players.forEach(({ player: p }, rankInSec) => {
            const posInSec = rankInSec + 1;
            const pTierLabel = getTierLabel(p.type);
            const itemCls = getTierCls(p.type);

            const item = document.createElement('div');
            item.className = `queue-item-card ${itemCls}`;
            
            const wsBadge = (p.ws && p.ws > 0)
              ? `<span class="badge" style="background:var(--c-dark); color:var(--c-white); border:1px solid var(--c-slate); font-weight:800;">🔥 ${p.ws} WS</span>`
              : '';
            const tLabel = (p.ticketMode === 'TIM' || p.ticketMode === 'DUO') ? 'DUO/TIM' : '1v1';
            const tBg = tLabel === '1v1' ? '#0284c7' : '#7c3aed';
            const ticketBadge = `<span class="badge" style="background:${tBg}; color:#fff; font-size:0.72rem; font-weight:800;" title="Jenis Tiket: ${tLabel}">🎟️ ${tLabel}</span>`;
            
            const ultiText = p.ulti || getPlayerDefaultUlti(p.char) || '';
            const ultiBadgeHtml = ultiText
              ? `<span class="queue-ulti-pill" title="Jurus Ulti: ${escapeHtml(ultiText)}">⚡ ${escapeHtml(ultiText)}</span>`
              : '';

            item.innerHTML = `
              <div class="queue-item-left">
                <div class="queue-rank-badge" title="Urutan di Tabungan: #${posInSec}">
                  #${posInSec}
                </div>
                <div class="queue-player-info">
                  <div class="queue-player-header">
                    <span class="queue-player-name" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</span>
                    <span class="badge badge-${itemCls}">${pTierLabel}</span>
                    ${ticketBadge}
                    ${wsBadge}
                  </div>
                  <div class="queue-player-details">
                    <span class="queue-char-text">🥋 ${escapeHtml(formatChar(p.char))}</span>
                    ${ultiBadgeHtml}
                    <span class="queue-match-pill" title="Sisa Match: ${p.matchesLeft}">🎟️ ${p.matchesLeft} Match</span>
                  </div>
                </div>
              </div>
              <div class="queue-item-right">
                <button type="button" class="btn-queue-dots" onclick="openPlayerActionModal('${p.id}', 'saved')" title="Menu Aksi [ ${escapeHtml(p.name)} ]">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="5" r="2.2"></circle>
                    <circle cx="12" cy="12" r="2.2"></circle>
                    <circle cx="12" cy="19" r="2.2"></circle>
                  </svg>
                </button>
              </div>
            `;
            tierSec.appendChild(item);
          });
        }

        container.appendChild(tierSec);
      });
    }

    function renderOverlay() {
      // NOTE / Announcement Ticker in Preview
      const ovTickerBar = document.getElementById('ov-ticker-bar');
      const ovTickerText = document.getElementById('ov-ticker-text');
      if (ovTickerBar && ovTickerText) {
        if (state.announcement && state.announcement.trim()) {
          let cleanNote = state.announcement.trim();
          if (/^NOTE\s*:\s*/i.test(cleanNote)) {
            cleanNote = cleanNote.replace(/^NOTE\s*:\s*/i, '');
          }
          ovTickerText.innerText = cleanNote;
          ovTickerBar.style.display = 'flex';
        } else {
          ovTickerBar.style.display = 'none';
        }
      }

      // Top Record Bar in Preview (Top 3 WS)
      const ovTopRecEl = document.getElementById('ov-top-rec-text');
      if (ovTopRecEl) {
        const recs = (Array.isArray(state.topRecords) && state.topRecords.length >= 3)
          ? state.topRecords
          : [
            state.topRecord || { rank: 1, name: '-', char: '-', ws: 0 },
            { rank: 2, name: '-', char: '-', ws: 0 },
            { rank: 3, name: '-', char: '-', ws: 0 }
          ];
        const medals = ['🥇', '🥈', '🥉'];
        ovTopRecEl.innerText = recs.slice(0, 3).map((r, i) => `${medals[i]} ${r.name || '-'} (${r.ws || 0} WS)`).join(' • ');
      }

      // P1
      if (state.p1) {
        document.getElementById('ov-p1-name').innerText = state.p1.name;
        document.getElementById('ov-p1-char').innerText = formatChar(state.p1.char);
        document.getElementById('ov-p1-ws').innerText = state.p1.ws || 0;
        let ovP1Tickets = String(state.p1.matchesLeft);
        if (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) {
          const base = state.p1.bonusBase !== undefined ? state.p1.bonusBase : (state.p1.matchesLeft - 1);
          ovP1Tickets = `${base}+1`;
        }
        document.getElementById('ov-p1-m').innerText = ovP1Tickets;
        const ovB1El = document.getElementById('ov-p1-bonus');
        if (ovB1El) {
          ovB1El.innerText = '+1 MATCH';
          ovB1El.style.display = (state.p1.bonusAnimUntil && Date.now() < state.p1.bonusAnimUntil) ? 'inline-block' : 'none';
        }
      } else {
        document.getElementById('ov-p1-name').innerText = "Menunggu...";
        document.getElementById('ov-p1-char').innerText = "-";
        document.getElementById('ov-p1-ws').innerText = "0";
        document.getElementById('ov-p1-m').innerText = "0";
        const ovB1El = document.getElementById('ov-p1-bonus');
        if (ovB1El) ovB1El.style.display = 'none';
      }

      // P2
      if (state.p2) {
        document.getElementById('ov-p2-name').innerText = state.p2.name;
        document.getElementById('ov-p2-char').innerText = formatChar(state.p2.char);
        document.getElementById('ov-p2-ws').innerText = state.p2.ws || 0;
        let ovP2Tickets = String(state.p2.matchesLeft);
        if (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) {
          const base = state.p2.bonusBase !== undefined ? state.p2.bonusBase : (state.p2.matchesLeft - 1);
          ovP2Tickets = `${base}+1`;
        }
        document.getElementById('ov-p2-m').innerText = ovP2Tickets;
        const ovB2El = document.getElementById('ov-p2-bonus');
        if (ovB2El) {
          ovB2El.innerText = '+1 MATCH';
          ovB2El.style.display = (state.p2.bonusAnimUntil && Date.now() < state.p2.bonusAnimUntil) ? 'inline-block' : 'none';
        }
      } else {
        document.getElementById('ov-p2-name').innerText = "Menunggu...";
        document.getElementById('ov-p2-char').innerText = "-";
        document.getElementById('ov-p2-ws').innerText = "0";
        document.getElementById('ov-p2-m').innerText = "0";
        const ovB2El = document.getElementById('ov-p2-bonus');
        if (ovB2El) ovB2El.style.display = 'none';
      }

      // List Overlay (Queue vs Saved)
      const qContainer = document.getElementById('ov-queue-list');
      qContainer.innerHTML = '';
      const isSaved = state.overlayListMode === 'saved';

      if (isSaved) {
        const sList = (state.saved || []).slice();
        sList.sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base', numeric: true }));

        if (sList.length === 0) {
          qContainer.innerHTML = '<div style="font-size:0.8rem; color:#aaa; padding:10px 0;">(Tabungan Kosong)</div>';
        } else {
          const fastSaved = sList.filter(p => getTierLabel(p.type) === 'SB');
          const vipFreeSaved = sList.filter(p => getTierLabel(p.type) !== 'SB');

          const sections = [];
          if (fastSaved.length > 0) {
            sections.push({ key: 'SROBOT', label: 'SROBOT', cls: 'fast', players: fastSaved });
          }
          sections.push({ key: 'VIPFREE', label: 'VIP & FREE', cls: 'vipfree', players: vipFreeSaved });

          let globalSavedIdx = 0;
          sections.forEach(sec => {
            if (sec.players.length === 0 && sec.key === 'SROBOT') return;

            const tierSection = document.createElement('div');
            tierSection.className = `tier-section ${sec.cls}`;

            const tierHeader = document.createElement('div');
            tierHeader.className = `tier-header ${sec.cls}`;
            tierHeader.style.cssText = 'margin-top:6px; margin-bottom:3px; padding:3px 8px; font-size:0.78rem; font-weight:900;';
            tierHeader.innerHTML = `
              <div style="display:flex; align-items:center; gap:6px;">
                <span>${sec.label}</span>
                <span>(${sec.players.length})</span>
              </div>
              <span style="letter-spacing:0.8px;">MATCH</span>
            `;
            tierSection.appendChild(tierHeader);

            sec.players.slice(0, 4).forEach(p => {
              globalSavedIdx++;
              const pTierLabel = getTierLabel(p.type);
              const itemCls = getTierCls(p.type);
              const qEl = document.createElement('div');
              qEl.className = 'overlay-qitem';
              qEl.innerHTML = `
                <span style="font-weight:700;">#${globalSavedIdx} ${escapeHtml(p.name)} | <span style="font-weight:400; color:var(--c-slate);">${escapeHtml(formatChar(p.char))}</span></span>
                <span style="display:flex; gap:6px; align-items:center;">
                  <span class="badge badge-${itemCls}">${pTierLabel}</span>
                  <span style="font-size:0.85rem; font-weight:900; color:var(--c-white); background:rgba(255,255,255,0.12); padding:1px 6px; border-radius:3px; border:1px solid var(--c-slate); min-width:28px; text-align:center;">${p.matchesLeft}</span>
                </span>
              `;
              tierSection.appendChild(qEl);
            });
            qContainer.appendChild(tierSection);
          });
        }
      } else {
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

        if (qList.length === 0) {
          qContainer.innerHTML = '<div style="font-size:0.8rem; color:#aaa; padding:10px 0;">(Match Queue Kosong)</div>';
        } else {
          let globalIdx = 0;

          // 1. SROBOT Section
          if (fastPlayers.length > 0 || state.queue.length > 0) {
            const tierSection = document.createElement('div');
            tierSection.className = 'tier-section fast';

            const rawStatusFast = (state.tierStatus && (state.tierStatus['SROBOT'] || state.tierStatus['SROBOT++'] || state.tierStatus['FAST'] || state.tierStatus['VVIP'])) || 'AKTIF';
            const statusFastLabel = getStatusLabel(rawStatusFast);
            const statusClassFast = getStatusCls(rawStatusFast);

            const tierHeader = document.createElement('div');
            tierHeader.className = 'tier-header fast';
            tierHeader.style.cssText = 'margin-top:6px; margin-bottom:3px; padding:3px 8px; font-size:0.78rem; font-weight:900;';
            tierHeader.innerHTML = `
              <div style="display:flex; align-items:center; gap:6px;">
                <span>SROBOT</span>
                <span class="tier-status-tag ${statusClassFast}">${statusFastLabel}</span>
                <span>(${fastPlayers.length})</span>
              </div>
              <span style="letter-spacing:0.8px;">MATCH</span>
            `;
            tierSection.appendChild(tierHeader);

            fastPlayers.slice(0, 4).forEach(p => {
              globalIdx++;
              const qEl = document.createElement('div');
              qEl.className = 'overlay-qitem';
              qEl.innerHTML = `
                <span style="font-weight:700;">#${globalIdx} ${escapeHtml(p.name)} | <span style="font-weight:400; color:var(--c-slate);">${escapeHtml(formatChar(p.char))}</span></span>
                <span style="display:flex; gap:6px; align-items:center;">
                  <span class="badge badge-fast">SB</span>
                  <span style="font-size:0.85rem; font-weight:900; color:var(--c-white); background:rgba(255,255,255,0.12); padding:1px 6px; border-radius:3px; border:1px solid var(--c-slate); min-width:28px; text-align:center;">${p.matchesLeft}</span>
                </span>
              `;
              tierSection.appendChild(qEl);
            });
            qContainer.appendChild(tierSection);
          }

          // 2. VIP & FREE Section
          if (interleavedVipFree.length > 0) {
            const tierSection = document.createElement('div');
            tierSection.className = 'tier-section vipfree';

            const tierHeader = document.createElement('div');
            tierHeader.className = 'tier-header vipfree';
            tierHeader.style.cssText = 'margin-top:6px; margin-bottom:3px; padding:3px 8px; font-size:0.78rem; font-weight:900;';
            tierHeader.innerHTML = `
              <div style="display:flex; align-items:center; gap:6px;">
                <span>VIP & FREE</span>
                <span>(${interleavedVipFree.length})</span>
              </div>
              <span style="letter-spacing:0.8px;">MATCH</span>
            `;
            tierSection.appendChild(tierHeader);

            interleavedVipFree.slice(0, 4).forEach(p => {
              globalIdx++;
              const pTierLabel = getTierLabel(p.type);
              const itemCls = getTierCls(p.type);
              const qEl = document.createElement('div');
              qEl.className = 'overlay-qitem';
              qEl.innerHTML = `
                <span style="font-weight:700;">#${globalIdx} ${escapeHtml(p.name)} | <span style="font-weight:400; color:var(--c-slate);">${escapeHtml(formatChar(p.char))}</span></span>
                <span style="display:flex; gap:6px; align-items:center;">
                  <span class="badge badge-${itemCls}">${pTierLabel}</span>
                  <span style="font-size:0.85rem; font-weight:900; color:var(--c-white); background:rgba(255,255,255,0.12); padding:1px 6px; border-radius:3px; border:1px solid var(--c-slate); min-width:28px; text-align:center;">${p.matchesLeft}</span>
                </span>
              `;
              tierSection.appendChild(qEl);
            });
            qContainer.appendChild(tierSection);
          }
        }
      }

      // Tampilkan banner FREE TUTUP jika status tier FREE tutup/penuh
      const rawStatusFree = (state.tierStatus && state.tierStatus['FREE']) || 'AKTIF';
      const isFreeClosed = (rawStatusFree === 'CLOSED' || rawStatusFree === 'PENUH' || rawStatusFree === 'TUTUP');
      const ovFreeBanner = document.getElementById('ov-free-closed-banner');
      if (ovFreeBanner) {
        ovFreeBanner.style.display = isFreeClosed ? 'block' : 'none';
      }
    }

    // Toggle Theme (Palet Terang / Gelap)
    function toggleTheme() {
      const current = document.documentElement.getAttribute('data-theme') || 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('koth_theme', next);
      const btn = document.getElementById('theme-toggle-btn');
      if (btn) btn.innerText = next === 'dark' ? 'Tema: Gelap' : 'Tema: Terang';
    }

    (function initTheme() {
      const savedTheme = localStorage.getItem('koth_theme') || 'light';
      document.documentElement.setAttribute('data-theme', savedTheme);
      const btn = document.getElementById('theme-toggle-btn');
      if (btn) btn.innerText = savedTheme === 'dark' ? 'Tema: Gelap' : 'Tema: Terang';
    })();

    // Keyboard Shortcuts: Ctrl+Z untuk Undo, Ctrl+Y / Ctrl+Shift+Z untuk Redo, ESC untuk tutup modal
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeAddPlayerModal();
        closeModal();
        closeConvertModal();
        closePlayerActionModal();
        closeEditPlayerCharModal();
      }

      const activeEl = document.activeElement;
      const tag = activeEl ? activeEl.tagName.toLowerCase() : '';
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || (activeEl && activeEl.isContentEditable)) {
        return; // Jangan cegat saat mengetik di input form
      }

      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
        if (e.shiftKey) {
          e.preventDefault();
          redo();
        } else {
          e.preventDefault();
          undo();
        }
      } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
        e.preventDefault();
        redo();
      }
    });

    // Inisialisasi
    loadState();

    // Auto-refresh timer Saved setiap 15 detik jika tab Saved aktif
    setInterval(() => {
      const sContent = document.getElementById('subtab-saved-content');
      if (sContent && sContent.style.display !== 'none' && state.saved && state.saved.length > 0) {
        renderSaved();
      }
    }, 15000);
  </script>

  <!-- MODAL EDIT PENDING REGISTRANT -->
  <div id="modal-edit-pending"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center; padding:16px;">
    <div
      style="background:#222831; border:2px solid #929AAB; border-radius:10px; max-width:480px; width:100%; padding:20px; color:#F7F7F7; box-shadow:0 16px 40px rgba(0,0,0,0.85); font-family:'Consolas','Courier New',monospace;">
      <div
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.15); padding-bottom:8px;">
        <h4 style="margin:0; font-size:1.05rem; font-weight:900; color:#F7F7F7;">✏️ Edit Detail Pendaftar</h4>
        <button type="button" onclick="closeEditPendingModal()"
          style="background:transparent; border:none; color:#F7F7F7; font-size:1.2rem; cursor:pointer;">✕</button>
      </div>

      <div style="display:flex; flex-direction:column; gap:10px;">
        <div>
          <label style="font-size:0.75rem; font-weight:800; color:#cbd5e1; display:block; margin-bottom:3px;">Nama
            TikTok / In-Game:</label>
          <input type="text" id="edit-pending-name" class="form-control" style="width:100%;">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
          <div>
            <label style="font-size:0.75rem; font-weight:800; color:#cbd5e1; display:block; margin-bottom:3px;">Tier
              Pendaftar:</label>
            <select id="edit-pending-tier" class="form-control" style="width:100%;">
              <option value="FREE">FREE</option>
              <option value="VIP">VIP</option>
              <option value="SROBOT">SROBOT</option>
            </select>
          </div>
          <div>
            <label
              style="font-size:0.75rem; font-weight:800; color:#cbd5e1; display:block; margin-bottom:3px;">Mode:</label>
            <select id="edit-pending-mode" class="form-control" style="width:100%;">
              <option value="1v1">1v1</option>
              <option value="DUO">DUO</option>
              <option value="TIM">TIM</option>
            </select>
          </div>
        </div>

        <div>
          <label style="font-size:0.75rem; font-weight:800; color:#cbd5e1; display:block; margin-bottom:3px;">Karakter
            (pisahkan dengan / jika Duo/Tim):</label>
          <input type="text" id="edit-pending-char" class="form-control" style="width:100%;">
        </div>

        <div>
          <label style="font-size:0.75rem; font-weight:800; color:#cbd5e1; display:block; margin-bottom:3px;">Jurus
            Ultimate (Opsional):</label>
          <input type="text" id="edit-pending-ulti" class="form-control" style="width:100%;">
        </div>

        <div>
          <label style="font-size:0.75rem; font-weight:800; color:#cbd5e1; display:block; margin-bottom:3px;">Jumlah
            Match:</label>
          <input type="number" id="edit-pending-matches" class="form-control" min="1" max="50" style="width:100%;">
        </div>
      </div>

      <div
        style="display:flex; justify-content:flex-end; gap:8px; margin-top:18px; border-top:1px solid rgba(255,255,255,0.15); padding-top:12px; flex-wrap:wrap;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="closeEditPendingModal()">Batal</button>
        <button type="button" class="btn btn-sm btn-primary" onclick="saveEditPendingPlayer(false)"
          style="background:#3b82f6; border-color:#3b82f6;">💾 Simpan</button>
        <button type="button" class="btn btn-sm btn-primary" onclick="saveEditPendingPlayer(true)"
          style="background:#22c55e; border-color:#22c55e;">💾 Simpan & Setujui</button>
      </div>
    </div>
  </div>

  <!-- MODAL NOTIFIKASI PENDAFTAR VIP / SROBOT BARU -->
  <div id="modal-new-pending-notif"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(6px); z-index:9999999; align-items:center; justify-content:center; padding:16px;">
    <div
      style="background:#222831; border:2px solid #F7F7F7; border-radius:12px; max-width:470px; width:100%; padding:22px; color:#F7F7F7; box-shadow:0 20px 50px rgba(0,0,0,0.9); font-family:'Consolas','Courier New',monospace; position:relative; animation:notifPopIn 0.25s ease-out;">

      <!-- Header -->
      <div
        style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.15); padding-bottom:10px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div
            style="width:38px; height:38px; background:rgba(234,179,8,0.15); border:1.5px solid #facc15; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
            🔔
          </div>
          <div>
            <h4 style="margin:0; font-size:1.05rem; font-weight:900; color:#F7F7F7; letter-spacing:0.5px;">
              PENDAFTAR BARU MASUK!
            </h4>
            <div style="font-size:0.72rem; color:#929AAB; margin-top:2px;">
              Menunggu konfirmasi persetujuan admin
            </div>
          </div>
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
          <span id="notif-pending-counter"
            style="display:none; background:rgba(234,179,8,0.2); border:1px solid #facc15; color:#facc15; font-size:0.70rem; padding:2px 7px; border-radius:4px; font-weight:900;">
            1 Pendaftar
          </span>
          <button type="button" onclick="closeNewPendingNotifModal()" title="Abaikan / Tutup Modal"
            style="background:transparent; border:none; color:#EEEEEE; font-size:1.3rem; cursor:pointer; line-height:1; padding:2px 6px;">✕</button>
        </div>
      </div>

      <!-- Detail Card Box -->
      <div id="notif-card-box"
        style="background:rgba(57,62,70,0.7); border:2px solid #FFD700; border-radius:10px; padding:14px 16px; margin-bottom:14px; transition:border-color 0.2s, box-shadow 0.2s;">

        <!-- Row 1: Nama, Tier, Mode -->
        <div
          style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <span id="notif-player-tier" class="q-badge vip"
              style="font-size:0.75rem; font-weight:900; padding:2px 8px; border-radius:4px;">VIP</span>
            <span id="notif-player-mode" class="badge"
              style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); font-size:0.70rem; padding:2px 6px; border-radius:4px; color:#EEEEEE; font-weight:800;">1v1</span>
          </div>
          <div id="notif-player-matches"
            style="font-size:0.80rem; font-weight:900; color:#86efac; background:rgba(34,197,94,0.15); border:1px solid #22c55e; border-radius:4px; padding:2px 8px;">
            1 Match
          </div>
        </div>

        <!-- Nama Pemain -->
        <div style="margin-bottom:10px;">
          <div
            style="font-size:0.70rem; color:#929AAB; text-transform:uppercase; font-weight:800; letter-spacing:0.5px;">
            Nama Pemain (TikTok / IG):</div>
          <div id="notif-player-name"
            style="font-size:1.25rem; font-weight:900; color:#ffffff; word-break:break-word;">
            -
          </div>
        </div>

        <!-- Karakter & Ulti -->
        <div
          style="background:rgba(34,40,49,0.85); border:1px solid rgba(255,255,255,0.1); border-radius:6px; padding:8px 10px; margin-bottom:10px;">
          <div style="font-size:0.70rem; color:#929AAB; font-weight:800; text-transform:uppercase;">Karakter Pilihan:
          </div>
          <div id="notif-player-char"
            style="font-size:0.90rem; font-weight:900; color:#F7F7F7; margin-top:2px; word-break:break-word;">
            -
          </div>
          <div id="notif-player-ulti"
            style="display:none; color:#facc15; font-size:0.78rem; font-weight:800; margin-top:3px; word-break:break-word;">
            -
          </div>
        </div>

        <!-- Estimasi Biaya Gift -->
        <div id="notif-player-price" style="text-align:center;">
          <!-- dynamic price hint -->
        </div>
      </div>

      <!-- Action Buttons -->
      <div
        style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:16px; flex-wrap:wrap;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="closeNewPendingNotifModal()"
          style="background:#393E46; border:1px solid #929AAB; color:#EEEEEE; font-weight:800; font-size:0.82rem; padding:8px 14px; border-radius:6px;"
          title="Tutup notifikasi, pemain tetap ada di daftar tunggu">
          Abaikan
        </button>
        <div style="display:flex; gap:8px; align-items:center;">
          <button type="button" class="btn btn-sm btn-secondary" onclick="editCurrentNotifPlayer()"
            title="Edit data pendaftar sebelum disetujui"
            style="background:#374151; border:1px solid #4b5563; color:#ffffff; font-weight:800; font-size:0.82rem; padding:8px 12px; border-radius:6px;">
            ✏️ Edit
          </button>
          <button type="button" class="btn btn-sm btn-danger" onclick="rejectCurrentNotifPlayer()"
            style="background:#ef4444; border:1px solid #dc2626; color:#ffffff; font-weight:800; font-size:0.85rem; padding:8px 14px; border-radius:6px;">
            ❌ Tolak
          </button>
          <button type="button" class="btn btn-sm btn-primary" onclick="approveCurrentNotifPlayer()"
            style="background:#22c55e; border:1px solid #16a34a; color:#ffffff; font-weight:900; font-size:0.88rem; padding:8px 18px; border-radius:6px; box-shadow:0 4px 14px rgba(34,197,94,0.4);">
            ✅ Setujui
          </button>
        </div>
      </div>

    </div>
  </div>
</body>

</html>