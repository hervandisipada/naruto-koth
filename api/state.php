<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

sendApiHeaders();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$roomKey = resolveRoomKey();

try {
    $pdo = getDbConnection();
} catch (Throwable $e) {
    $msg = isLocalEnvironment()
        ? 'Koneksi database gagal: ' . $e->getMessage()
        : 'Koneksi database gagal.';
    sendJson(['error' => $msg], 500);
}

if ($method === 'GET') {
    try {
        $result = loadStateFromMySql($pdo, $roomKey);
        header('X-State-Version: ' . (int) $result['version']);
        sendJson($result['state'], 200);
    } catch (Throwable $e) {
        $msg = isLocalEnvironment()
            ? 'Gagal membaca state dari database: ' . $e->getMessage()
            : 'Gagal membaca state dari database.';
        sendJson(['error' => $msg], 500);
    }
}

if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        sendJson(['error' => 'Request body kosong.'], 400);
    }

    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        sendJson(['error' => 'Payload JSON tidak valid.'], 400);
    }

    $hasValidShape = array_key_exists('p1', $decoded)
        || array_key_exists('queue', $decoded)
        || array_key_exists('tierStatus', $decoded);

    if (!$hasValidShape) {
        sendJson(['error' => 'Struktur state tidak sesuai.'], 400);
    }

    try {
        $version = syncStateToMySql($pdo, $roomKey, $decoded);
        sendJson([
            'status' => 'ok',
            'room' => $roomKey,
            'version' => $version,
        ], 200);
    } catch (Throwable $e) {
        $msg = isLocalEnvironment()
            ? 'Gagal menyimpan state ke database: ' . $e->getMessage()
            : 'Gagal menyimpan state ke database.';
        sendJson(['error' => $msg], 500);
    }
}

sendJson(['error' => 'Method tidak didukung.'], 405);
