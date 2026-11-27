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

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, username, last_char, last_tier, best_ws, total_registrations, updated_at
             FROM koth_users
             WHERE room_key = ?
             ORDER BY username ASC'
        );
        $stmt->execute([$roomKey]);
        $rows = $stmt->fetchAll();

        sendJson([
            'room' => $roomKey,
            'count' => count($rows),
            'users' => $rows,
        ], 200);
    } catch (Throwable $e) {
        sendJson(['error' => 'Gagal membaca daftar user dari MySQL.'], 500);
    }
}

if ($method === 'POST') {
    $action = (string) ($body['action'] ?? 'add');
    $username = trim((string) ($body['username'] ?? ''));

    if ($username === '') {
        sendJson(['error' => 'Username tidak boleh kosong.'], 400);
    }

    if ($action === 'add') {
        $lastChar = trim((string) ($body['last_char'] ?? '-')) ?: '-';
        $lastTier = normalizePlayerTier((string) ($body['last_tier'] ?? 'FREE'));

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO koth_users (room_key, username, last_char, last_tier, total_registrations)
                 VALUES (?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                   last_char = IF(VALUES(last_char) <> "-", VALUES(last_char), last_char),
                   last_tier = VALUES(last_tier),
                   total_registrations = total_registrations + 1'
            );
            $stmt->execute([
                $roomKey,
                substr($username, 0, 120),
                substr($lastChar, 0, 255),
                $lastTier,
            ]);
            sendJson(['status' => 'ok', 'username' => $username], 200);
        } catch (Throwable $e) {
            sendJson(['error' => 'Gagal menyimpan user ke MySQL.'], 500);
        }
    }

    if ($action === 'delete') {
        if (!isAdminAuthenticated($roomKey)) {
            sendJson(['error' => 'Hanya admin yang dapat menghapus user.'], 401);
        }

        try {
            $del = $pdo->prepare('DELETE FROM koth_users WHERE room_key = ? AND username = ?');
            $del->execute([$roomKey, $username]);
            sendJson(['status' => 'ok', 'deleted' => $username], 200);
        } catch (Throwable $e) {
            sendJson(['error' => 'Gagal menghapus user dari MySQL.'], 500);
        }
    }

    sendJson(['error' => 'Action tidak didukung.'], 400);
}

sendJson(['error' => 'Method tidak didukung.'], 405);
