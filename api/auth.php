<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

sendApiHeaders();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    $msg = isLocalEnvironment()
        ? 'Koneksi database gagal: ' . $e->getMessage()
        : 'Koneksi database gagal.';
    sendJson(['error' => $msg], 500);
}

$rawBody = ($method === 'POST') ? file_get_contents('php://input') : '';
$body = ($rawBody !== false && trim($rawBody) !== '') ? json_decode($rawBody, true) : [];
if (!is_array($body)) {
    $body = [];
}

$roomKey = resolveRoomKey(isset($body['room']) ? (string) $body['room'] : null);

try {
    ensureDefaultAdmin($pdo, $roomKey);
} catch (Throwable $e) {
    $msg = isLocalEnvironment()
        ? 'Tabel koth_admins belum siap. Jalankan database/schema.sql di phpMyAdmin. (' . $e->getMessage() . ')'
        : 'Konfigurasi autentikasi belum siap.';
    sendJson(['error' => $msg], 500);
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'status';
    if ($action === 'status') {
        $auth = isAdminAuthenticated($roomKey);
        sendJson([
            'authenticated' => $auth,
            'room' => $roomKey,
            'username' => $auth ? ($_SESSION['koth_admin_rooms'][$roomKey] ?? 'admin') : null,
        ], 200);
    }
    sendJson(['error' => 'Action tidak dikenali.'], 400);
}

if ($method === 'POST') {
    $action = (string) ($body['action'] ?? '');

    if ($action === 'login') {
        $pin = trim((string) ($body['pin'] ?? ''));
        if ($pin === '') {
            sendJson(['authenticated' => false, 'error' => 'PIN wajib diisi.'], 400);
        }

        $stmt = $pdo->prepare('SELECT username, pin_hash FROM koth_admins WHERE room_key = ? LIMIT 1');
        $stmt->execute([$roomKey]);
        $admin = $stmt->fetch();

        // Fallback ke master admin jika room_key belum punya baris khusus
        if (!$admin) {
            $stmtFallback = $pdo->query('SELECT username, pin_hash FROM koth_admins ORDER BY id ASC LIMIT 1');
            $admin = $stmtFallback->fetch();
        }

        if (!$admin || !password_verify($pin, (string) $admin['pin_hash'])) {
            sendJson(['authenticated' => false, 'error' => 'PIN SALAH! PIN default adalah 7878.'], 401);
        }

        startAdminSession();
        session_regenerate_id(true);
        if (!isset($_SESSION['koth_admin_rooms']) || !is_array($_SESSION['koth_admin_rooms'])) {
            $_SESSION['koth_admin_rooms'] = [];
        }
        $_SESSION['koth_admin_rooms'][$roomKey] = $admin['username'];

        sendJson([
            'authenticated' => true,
            'room' => $roomKey,
            'username' => $admin['username'],
        ], 200);
    }

    if ($action === 'logout') {
        startAdminSession();
        if (isset($_SESSION['koth_admin_rooms'][$roomKey])) {
            unset($_SESSION['koth_admin_rooms'][$roomKey]);
        }
        sendJson(['authenticated' => false, 'room' => $roomKey], 200);
    }

    if ($action === 'change_pin') {
        if (!isAdminAuthenticated($roomKey)) {
            sendJson(['error' => 'Silakan login admin terlebih dahulu.'], 401);
        }

        $oldPin = trim((string) ($body['old_pin'] ?? ''));
        $newPin = trim((string) ($body['new_pin'] ?? ''));

        if (!preg_match('/^\d{4}$/', $newPin)) {
            sendJson(['error' => 'PIN baru harus tepat 4 digit angka.'], 400);
        }

        $stmt = $pdo->prepare('SELECT id, pin_hash FROM koth_admins WHERE room_key = ? LIMIT 1');
        $stmt->execute([$roomKey]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($oldPin, (string) $admin['pin_hash'])) {
            sendJson(['error' => 'PIN lama tidak sesuai.'], 403);
        }

        $newHash = password_hash($newPin, PASSWORD_BCRYPT);
        $upd = $pdo->prepare('UPDATE koth_admins SET pin_hash = ? WHERE id = ?');
        $upd->execute([$newHash, (int) $admin['id']]);

        sendJson(['status' => 'ok', 'message' => 'PIN admin berhasil diperbarui di MySQL.'], 200);
    }

    sendJson(['error' => 'Action tidak didukung.'], 400);
}

sendJson(['error' => 'Method tidak didukung.'], 405);
