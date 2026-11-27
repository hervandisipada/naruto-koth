<?php
declare(strict_types=1);

/**
 * Skrip CLI sekali pakai untuk mengimpor state.json ke seluruh tabel MySQL
 * (koth_rooms, koth_admins, koth_users, koth_players, koth_top_records, koth_custom_characters).
 *
 * Penggunaan:
 *   C:\xampp\php\php.exe database\import_state_json.php [room_key]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Script ini hanya dapat dijalankan lewat CLI.');
}

require_once __DIR__ . '/../api/helpers.php';

$roomKey = resolveRoomKey($argv[1] ?? null);

$stateFile = __DIR__ . '/../state.json';
if (!is_file($stateFile)) {
    fwrite(STDERR, "[ERROR] File state.json tidak ditemukan di: {$stateFile}\n");
    exit(1);
}

$raw = file_get_contents($stateFile);
$decoded = json_decode((string) $raw, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "[ERROR] Isi state.json bukan JSON yang valid.\n");
    exit(1);
}

try {
    $pdo = getDbConnection();
    ensureDefaultAdmin($pdo, $roomKey);
    $version = syncStateToMySql($pdo, $roomKey, $decoded);

    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM koth_users WHERE room_key = " . $pdo->quote($roomKey))->fetchColumn();
    $playerCount = (int) $pdo->query("SELECT COUNT(*) FROM koth_players WHERE room_key = " . $pdo->quote($roomKey))->fetchColumn();

    echo "[OK] Data state.json berhasil diimpor ke MySQL (room='{$roomKey}', version={$version}).\n";
    echo "     - koth_users   : {$userCount} user terdaftar\n";
    echo "     - koth_players : {$playerCount} pemain aktif/queue/tabungan\n";
    echo "     - koth_admins  : PIN default siap (disimpan sebagai bcrypt hash)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] Gagal impor ke MySQL: " . $e->getMessage() . "\n");
    exit(1);
}
