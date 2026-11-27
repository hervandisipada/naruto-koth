<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$error = null;
$pdo = null;
$tables = [];
$configFileFound = findLocalConfigFile();
$cfg = getDbConfig();

$setupMsg = null;
try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($pdo && $action === 'setup_tables') {
    try {
        $sql = "
        CREATE TABLE IF NOT EXISTS `koth_rooms` (
          `room_key` VARCHAR(64) NOT NULL,
          `state_json` LONGTEXT NOT NULL,
          `version` INT UNSIGNED NOT NULL DEFAULT 1,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`room_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `koth_admins` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `room_key` VARCHAR(64) NOT NULL,
          `username` VARCHAR(64) NOT NULL DEFAULT 'admin',
          `pin_hash` VARCHAR(255) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_room_admin` (`room_key`, `username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        INSERT IGNORE INTO `koth_admins` (`room_key`, `username`, `pin_hash`)
        VALUES ('hervandisipada', 'admin', '$2y$10$RxRdtjyu1pVMI7MO3DYBm.t0TbS6uZL0JTGvdWBt2pB1kE9ylCo4a');

        CREATE TABLE IF NOT EXISTS `koth_users` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `room_key` VARCHAR(64) NOT NULL,
          `username` VARCHAR(120) NOT NULL,
          `last_char` VARCHAR(255) NOT NULL DEFAULT '-',
          `last_tier` VARCHAR(20) NOT NULL DEFAULT 'FREE',
          `best_ws` INT UNSIGNED NOT NULL DEFAULT 0,
          `total_registrations` INT UNSIGNED NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_room_user` (`room_key`, `username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `koth_players` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `room_key` VARCHAR(64) NOT NULL,
          `player_uid` VARCHAR(64) NOT NULL,
          `name` VARCHAR(120) NOT NULL,
          `char_name` VARCHAR(255) NOT NULL DEFAULT '-',
          `ulti` VARCHAR(255) NOT NULL DEFAULT '',
          `tier` VARCHAR(20) NOT NULL DEFAULT 'FREE',
          `ticket_mode` VARCHAR(16) NOT NULL DEFAULT '1v1',
          `matches_left` INT NOT NULL DEFAULT 1,
          `ws` INT NOT NULL DEFAULT 0,
          `slot_status` ENUM('arena_p1', 'arena_p2', 'queue', 'saved') NOT NULL,
          `arena_mode` VARCHAR(16) NOT NULL DEFAULT '1v1',
          `sort_order` INT NOT NULL DEFAULT 0,
          `extra_json` TEXT NULL,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_room_status` (`room_key`, `slot_status`, `arena_mode`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `koth_top_records` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `room_key` VARCHAR(64) NOT NULL,
          `mode` VARCHAR(16) NOT NULL,
          `rank_pos` TINYINT UNSIGNED NOT NULL,
          `player_name` VARCHAR(120) NOT NULL DEFAULT '-',
          `char_name` VARCHAR(255) NOT NULL DEFAULT '-',
          `ws` INT UNSIGNED NOT NULL DEFAULT 0,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_room_mode_rank` (`room_key`, `mode`, `rank_pos`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `koth_custom_characters` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `room_key` VARCHAR(64) NOT NULL,
          `char_name` VARCHAR(120) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_room_custom_char` (`room_key`, `char_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
        $setupMsg = "✅ Seluruh tabel database (koth_rooms, koth_admins, koth_users, dll.) berhasil dibuat!";
    } catch (Throwable $e) {
        $setupMsg = "❌ Gagal membuat tabel: " . $e->getMessage();
    }
}

if ($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}
}

$hasKothRooms = in_array('koth_rooms', $tables, true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diagnostik Database MySQL - Naruto KotH</title>
  <style>
    body {
      background: #1e293b;
      color: #f8fafc;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      padding: 24px;
      line-height: 1.5;
    }
    .card {
      max-width: 680px;
      margin: 0 auto;
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    h1 { font-size: 1.25rem; font-weight: 800; margin-bottom: 16px; border-bottom: 1px solid #334155; padding-bottom: 12px; }
    .status-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 6px;
      font-weight: 900;
      font-size: 0.95rem;
      margin-bottom: 16px;
    }
    .status-ok { background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #86efac; }
    .status-err { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 0.88rem; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #1e293b; }
    th { color: #94a3b8; width: 35%; }
    td { font-family: monospace; color: #f1f5f9; }
    .hint-box {
      margin-top: 18px;
      background: rgba(234, 179, 8, 0.12);
      border: 1px solid #eab308;
      border-radius: 8px;
      padding: 14px;
      font-size: 0.85rem;
      color: #fef08a;
    }
    .btn {
      display: inline-block;
      margin-top: 14px;
      background: #2563eb;
      color: #ffffff;
      padding: 10px 18px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 800;
      font-size: 0.88rem;
      cursor: pointer;
      border: none;
    }
    .btn:hover { background: #1d4ed8; }
  </style>
</head>
<body>
  <div class="card">
    <h1>🔧 Diagnostik Koneksi Database MySQL</h1>

    <?php if ($error === null): ?>
      <div class="status-badge status-ok">✅ KONEKSI KE DATABASE BERHASIL!</div>
      <p style="color: #cbd5e1; font-size: 0.90rem;">
        Server PHP berhasil terhubung ke server MySQL dengan kredensial aktif.
      </p>

      <?php if ($setupMsg): ?>
        <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; padding: 10px; border-radius: 6px; margin: 12px 0; color: #86efac; font-weight: 800; font-size: 0.85rem;">
          <?= htmlspecialchars($setupMsg) ?>
        </div>
      <?php endif; ?>

      <?php if (!$hasKothRooms): ?>
        <div class="hint-box" style="background: rgba(239,68,68,0.15); border-color: #ef4444; color: #fca5a5;">
          <b>⚠️ Tabel Belum Ada:</b> Database terhubung, namun tabel <code>koth_rooms</code> belum dibuat.
          <br><br>
          <a href="?action=setup_tables" class="btn" style="background: #22c55e;">⚡ Klik di Sini untuk Buat Tabel Otomatis</a>
        </div>
      <?php else: ?>
        <div style="margin-top: 14px; background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; border-radius: 8px; padding: 12px; font-size: 0.85rem; color: #86efac;">
          <b>🎉 Database Siap:</b> Tabel <code>koth_rooms</code> sudah aktif! (Total tabel ditemukan: <?= count($tables) ?>).
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="status-badge status-err">❌ KONEKSI DATABASE GAGAL</div>
      <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; border-radius: 8px; padding: 12px; margin-bottom: 14px; color: #fca5a5; font-family: monospace; font-size: 0.85rem; word-break: break-all;">
        <b>Pesan Error PDO:</b><br>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <h2 style="font-size: 1rem; font-weight: 800; margin-top: 20px; color: #94a3b8;">Informasi Konfigurasi Aktif</h2>
    <table>
      <tr>
        <th>File Config Ditemukan:</th>
        <td><?= htmlspecialchars($configFileFound ? $configFileFound : 'TIDAK ADA (pakai default)') ?></td>
      </tr>
      <tr>
        <th>MySQL Host:</th>
        <td><?= htmlspecialchars($cfg['host']) ?></td>
      </tr>
      <tr>
        <th>MySQL Port:</th>
        <td><?= (int)$cfg['port'] ?></td>
      </tr>
      <tr>
        <th>Database Name:</th>
        <td><?= htmlspecialchars($cfg['dbname']) ?></td>
      </tr>
      <tr>
        <th>Username:</th>
        <td><?= htmlspecialchars($cfg['username']) ?></td>
      </tr>
      <tr>
        <th>Password Status:</th>
        <td><?= !empty($cfg['password']) ? 'Terisi (' . strlen($cfg['password']) . ' karakter)' : '<span style="color:#ef4444;">KOSONG</span>' ?></td>
      </tr>
    </table>

    <?php if ($error !== null): ?>
      <div class="hint-box">
        <b>💡 Panduan Mengatasi Error di InfinityFree:</b>
        <ol style="margin-left: 20px; margin-top: 8px;">
          <?php if (!$configFileFound): ?>
            <li><b>File config belum terbaca:</b> Pastikan Anda sudah membuat file <code>config.local.php</code> di dalam folder <code>htdocs/api/</code> (atau <code>herz.kesug.com/htdocs/api/</code>) lewat File Manager InfinityFree.</li>
          <?php endif; ?>
          <?php if ($cfg['host'] === '127.0.0.1' || $cfg['host'] === 'localhost'): ?>
            <li><b>Host Masih Localhost:</b> InfinityFree tidak mendukung <code>127.0.0.1</code> atau <code>localhost</code>. Cek Control Panel InfinityFree pada bagian <b>Account Details</b> / <b>MySQL Details</b> untuk melihat <b>MySQL Hostname</b> Anda (misal: <code>sql205.infinityfree.com</code>).</li>
          <?php elseif (strpos($cfg['host'], 'sqlXXX') !== false): ?>
            <li><b>Host Masih sqlXXX:</b> Nilai <code>sqlXXX.infinityfree.com</code> adalah template contoh. Gantilah dengan nomor server asli akun Anda (misal: <code>sql105.infinityfree.com</code> atau <code>sql311.infinityfree.com</code>).</li>
          <?php endif; ?>
          <?php if (strpos($error, '1045') !== false || strpos($error, 'Access denied') !== false): ?>
            <li><b>Password / Username Salah:</b> Di InfinityFree, password MySQL sama persis dengan <b>Password Akun vPanel</b> Anda (bukan password login email client area). Anda bisa menyalinnya dari Client Area InfinityFree ➔ Manage Account ➔ Account Details ➔ Password.</li>
          <?php endif; ?>
          <?php if (strpos($error, '1049') !== false || strpos($error, 'Unknown database') !== false): ?>
            <li><b>Database Belum Dibuat:</b> Pastikan nama database <code>if0_41791529_naruto_koth</code> sudah dibuat di menu <b>MySQL Databases</b> di Control Panel hosting Anda.</li>
          <?php endif; ?>
        </ol>
      </div>
    <?php endif; ?>

    <div style="margin-top: 20px; text-align: center;">
      <a href="../index.php" style="color: #38bdf8; text-decoration: none; font-size: 0.85rem; font-weight: 700;">← Kembali ke Halaman Pendaftaran</a>
      &nbsp;|&nbsp;
      <a href="../admin.php" style="color: #38bdf8; text-decoration: none; font-size: 0.85rem; font-weight: 700;">Panel Admin →</a>
    </div>
  </div>
</body>
</html>
