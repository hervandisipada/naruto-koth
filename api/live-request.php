<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/bot_helpers.php';

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
    sendJson(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $e->getMessage()], 500);
}

// Health check / GET status
if ($method === 'GET') {
    try {
        $data = loadStateFromMySql($pdo, $roomKey);
        $state = $data['state'];
        sendJson([
            'status' => 'ok',
            'room' => $roomKey,
            'activeMode' => $state['masterMode'] ?? '1v1',
            'queueCount' => count($state['queue'] ?? []),
            'botConfig' => $state['botConfig'] ?? [
                'enabled' => true,
                'host' => 'herzg1209',
                'autoVipGift' => true
            ]
        ], 200);
    } catch (Throwable $e) {
        sendJson(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

// POST: Terima komentar / gift dari bot TikTok Live
if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    if (!$rawBody) {
        sendJson(['status' => 'error', 'message' => 'Payload kosong.'], 400);
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        sendJson(['status' => 'error', 'message' => 'Format JSON tidak valid.'], 400);
    }

    $data = loadStateFromMySql($pdo, $roomKey);
    $state = $data['state'];
    $activeMode = strtoupper($state['masterMode'] ?? '1v1');

    // Cek apakah bot dinonaktifkan di admin
    if (isset($state['botConfig']['enabled']) && $state['botConfig']['enabled'] === false) {
        sendJson(['status' => 'ignored', 'reason' => 'Bot TikTok Live dinonaktifkan di panel admin.'], 200);
    }

    $event = strtolower(trim((string)($payload['event'] ?? 'comment')));
    $userObj = $payload['user'] ?? [];

    // PERMINTAAN USER: DI LIST ANTRIAN, AMBIL NAMA TIKTOK-NYA (NICKNAME/DISPLAY NAME), BUKAN USERNAME!
    $nickname = trim((string)($userObj['nickname'] ?? ''));
    $uniqueId = trim((string)($userObj['uniqueId'] ?? ''));
    $displayName = $nickname !== '' ? $nickname : ($uniqueId !== '' ? $uniqueId : 'Viewer');

    if ($event === 'comment') {
        $comment = trim((string)($payload['comment'] ?? ''));
        if ($comment === '') {
            sendJson(['status' => 'ignored', 'reason' => 'Komentar kosong.'], 200);
        }

        $parsed = parseLiveComment($comment, $activeMode);
        if ($parsed === null) {
            // Bukan format request karakter yang valid, abaikan komentar biasa
            sendJson(['status' => 'ignored', 'reason' => 'Bukan format command request (!req <karakter>).'], 200);
        }

        $characters = $parsed['characters'];
        if (empty($characters)) {
            sendJson(['status' => 'rejected', 'reason' => 'Karakter tidak ditemukan dalam daftar 163 roster Naruto.'], 200);
        }

        // Cek batasan jumlah karakter per mode
        if ($activeMode === 'DUO' && count($characters) < 2) {
            sendJson([
                'status' => 'rejected',
                'reason' => 'Mode DUO membutuhkan 2 karakter. Contoh: !req Sasuke / Naruto'
            ], 200);
        }

        // Ambil maksimal sesuai mode
        $maxSlots = ($activeMode === '1v1') ? 1 : (($activeMode === 'DUO') ? 2 : 3);
        $selectedChars = array_slice($characters, 0, $maxSlots);

        // Validasi Karakter Banned
        foreach ($selectedChars as $c) {
            if (isBannedCharacter($c)) {
                sendJson([
                    'status' => 'rejected',
                    'reason' => "Karakter '{$c['name']}' berstatus BANNED dan tidak boleh digunakan."
                ], 200);
            }
        }

        // Validasi Karakter Khusus VIP (Jalur Free tidak boleh pakai)
        $tier = 'FREE';
        foreach ($selectedChars as $c) {
            if (isVipExclusiveCharacter($c)) {
                sendJson([
                    'status' => 'rejected',
                    'reason' => "Karakter '{$c['name']}' khusus untuk peserta VIP/SROBOT. Kirim gift untuk mendaftar jalur VIP!"
                ], 200);
            }
        }

        // Validasi Anti-Duplikat Karakter dalam 1 Tim (Semua Versi)
        if ($activeMode === 'DUO' || $activeMode === 'TIM') {
            $charCount = count($selectedChars);
            for ($i = 0; $i < $charCount; $i++) {
                for ($j = $i + 1; $j < $charCount; $j++) {
                    if (isSameBaseCharPhp($selectedChars[$i], $selectedChars[$j])) {
                        $baseName = getCharBaseNamePhp($selectedChars[$i]);
                        sendJson([
                            'status' => 'rejected',
                            'reason' => "Mode {$activeMode} tidak mengizinkan karakter yang sama dalam 1 tim (Semua versi '{$baseName}')."
                        ], 200);
                    }
                }
            }
        }

        // Cek Duplikasi Pemain di Antrean / Arena
        $dupReason = checkDuplicateInQueue($displayName, $state);
        if ($dupReason !== null) {
            sendJson([
                'status' => 'rejected',
                'reason' => "{$displayName}: {$dupReason}."
            ], 200);
        }

        // Format nama karakter & ulti bawaan
        $charNames = array_map(fn($c) => $c['name'], $selectedChars);
        $fullChar = implode(' / ', $charNames);

        $ultiNames = array_map(function ($c) {
            return (!empty($c['ultis']) && is_array($c['ultis'])) ? $c['ultis'][0] : '-';
        }, $selectedChars);
        $fullUlti = implode(' / ', $ultiNames);

        // Buat objek pemain baru
        $newPlayer = [
            'id' => 'pl_tt_' . time() . '_' . substr(md5($displayName . microtime()), 0, 6),
            'name' => $displayName, // NAMA TIKTOK (Display Name / Nickname)
            'tiktokUsername' => $uniqueId, // @username asli tersimpan untuk referensi
            'char' => $fullChar,
            'ulti' => $fullUlti,
            'matchesLeft' => 1,
            'ws' => 0,
            'type' => 'FREE',
            'ticketMode' => $activeMode,
            'registeredAt' => round(microtime(true) * 1000),
            'status' => 'APPROVED',
            'source' => 'TIKTOK_LIVE'
        ];

        if (!isset($state['queue']) || !is_array($state['queue'])) {
            $state['queue'] = [];
        }
        $state['queue'][] = $newPlayer;
        sortQueuePhp($state);

        if (!isset($state['registeredUsers']) || !is_array($state['registeredUsers'])) {
            $state['registeredUsers'] = [];
        }
        if (!in_array($displayName, $state['registeredUsers'], true)) {
            $state['registeredUsers'][] = $displayName;
        }

        // Timestamp & client tracking
        $state['_clientId'] = 'tt_bot_' . time();
        $state['_ts'] = round(microtime(true) * 1000);

        // Simpan ke MySQL
        $version = syncStateToMySql($pdo, $roomKey, $state);

        // Cari posisi antrean
        $qIdx = 0;
        foreach ($state['queue'] as $i => $p) {
            if ($p['id'] === $newPlayer['id']) {
                $qIdx = $i + 1;
                break;
            }
        }

        sendJson([
            'status' => 'ok',
            'action' => 'enqueued',
            'player' => [
                'name' => $displayName,
                'char' => $fullChar,
                'ulti' => $fullUlti,
                'type' => 'FREE',
                'queuePos' => $qIdx
            ],
            'queueTotal' => count($state['queue']),
            'version' => $version
        ], 200);
    }

    // Event Gift (Auto Upgrade / Auto Add VIP / SROBOT)
    if ($event === 'gift') {
        $giftObj = $payload['gift'] ?? [];
        $giftName = trim((string)($giftObj['name'] ?? 'Gift'));
        $diamondCount = (int)($giftObj['diamondCount'] ?? 1);
        $repeatCount = (int)($giftObj['repeatCount'] ?? 1);
        $totalCoins = $diamondCount * $repeatCount;

        // Tentukan Tier berdasarkan jumlah koin gift
        $tier = 'FREE';
        if ($totalCoins >= 30) {
            $tier = 'SROBOT';
            $matches = ($activeMode === '1v1') ? 2 : 1;
        } elseif ($totalCoins >= 5) {
            $tier = 'VIP';
            $matches = 1;
        } else {
            sendJson(['status' => 'ignored', 'reason' => "Koin gift ({$totalCoins}) di bawah batas VIP (5 koin)."], 200);
        }

        // Cek jika pemain sudah ada di antrean -> Upgrade tier-nya!
        $upgraded = false;
        if (!empty($state['queue']) && is_array($state['queue'])) {
            foreach ($state['queue'] as &$p) {
                if (mb_strtolower(trim($p['name']), 'UTF-8') === mb_strtolower($displayName, 'UTF-8')) {
                    $p['type'] = $tier;
                    $p['matchesLeft'] = max((int)($p['matchesLeft'] ?? 1), $matches);
                    $upgraded = true;
                    break;
                }
            }
            unset($p);
        }

        if ($upgraded) {
            sortQueuePhp($state);
            $state['_clientId'] = 'tt_bot_' . time();
            $state['_ts'] = round(microtime(true) * 1000);
            syncStateToMySql($pdo, $roomKey, $state);
            sendJson([
                'status' => 'ok',
                'action' => 'upgraded',
                'player' => ['name' => $displayName, 'type' => $tier],
                'message' => "Pemain {$displayName} di-upgrade ke tier {$tier} berkat Gift {$giftName} ({$totalCoins} Koin)!"
            ], 200);
        }

        sendJson([
            'status' => 'ok',
            'action' => 'gift_received',
            'message' => "Gift {$giftName} dari {$displayName} diterima. Viewer dapat mengetik !req <karakter> untuk masuk jalur {$tier}!"
        ], 200);
    }

    sendJson(['status' => 'error', 'message' => "Event '{$event}' tidak dikenali."], 400);
}

sendJson(['status' => 'error', 'message' => 'Method tidak didukung.'], 405);
