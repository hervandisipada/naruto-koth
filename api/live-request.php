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

        // Deteksi Tier dari komentar (FREE / VIP / SROBOT)
        $tier = $parsed['tier'] ?? 'FREE';

        // Validasi Karakter Khusus VIP (Jalur Free tidak boleh pakai)
        if ($tier === 'FREE') {
            foreach ($selectedChars as $c) {
                if (isVipExclusiveCharacter($c)) {
                    sendJson([
                        'status' => 'rejected',
                        'reason' => "Karakter '{$c['name']}' khusus untuk peserta VIP/SROBOT. Gunakan '!req vip {$c['name']}' atau kirim gift untuk bermain karakter ini!"
                    ], 200);
                }
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

        // Cek apakah pemain sudah ada di Pending Approvals
        $pendingReason = checkDuplicateInPending($displayName, $state);
        if ($pendingReason !== null) {
            sendJson([
                'status' => 'rejected',
                'reason' => "{$displayName}: {$pendingReason}."
            ], 200);
        }

        // Format nama karakter & ulti bawaan
        $charNames = array_map(fn($c) => $c['name'], $selectedChars);
        $fullChar = implode(' / ', $charNames);

        $ultiNames = array_map(function ($c) {
            return (!empty($c['ultis']) && is_array($c['ultis'])) ? $c['ultis'][0] : '-';
        }, $selectedChars);
        $fullUlti = implode(' / ', $ultiNames);

        // Cek Live Gift Ledger (Apakah viewer SUDAH KIRIM GIFT?)
        $userKeyUnique = ($uniqueId !== '') ? mb_strtolower($uniqueId, 'UTF-8') : '';
        $userKeyNick = ($displayName !== '') ? mb_strtolower($displayName, 'UTF-8') : '';

        $giftInfo = null;
        $userKeyToUse = $userKeyUnique !== '' ? $userKeyUnique : $userKeyNick;

        if (!empty($state['liveGiftLedger']) && is_array($state['liveGiftLedger'])) {
            if ($userKeyUnique !== '' && isset($state['liveGiftLedger'][$userKeyUnique])) {
                $giftInfo = $state['liveGiftLedger'][$userKeyUnique];
                $userKeyToUse = $userKeyUnique;
            } elseif ($userKeyNick !== '' && isset($state['liveGiftLedger'][$userKeyNick])) {
                $giftInfo = $state['liveGiftLedger'][$userKeyNick];
                $userKeyToUse = $userKeyNick;
            }
        }

        $totalCoins = (int)($giftInfo['totalCoins'] ?? 0);
        $usedCoins = (int)($giftInfo['usedCoins'] ?? 0);
        $availableCoins = max(0, $totalCoins - $usedCoins);

        $requiredCoins = ($tier === 'SROBOT') ? 30 : (($tier === 'VIP') ? 5 : 0);

        // ========================================================
        // KASUS 1: FREE REQUEST -> LANGSUNG MASUK KE ANTREAN
        // ========================================================
        if ($tier === 'FREE') {
            $newPlayer = [
                'id' => 'pl_tt_' . time() . '_' . substr(md5($displayName . microtime()), 0, 6),
                'name' => $displayName, // NAMA TIKTOK DISPLAY NAME
                'tiktokUsername' => $uniqueId,
                'char' => $fullChar,
                'ulti' => $fullUlti,
                'matchesLeft' => 1,
                'ws' => 0,
                'type' => 'FREE',
                'ticketMode' => $activeMode,
                'registeredAt' => round(microtime(true) * 1000),
                'status' => 'APPROVED',
                'source' => 'TIKTOK_LIVE_BOT'
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

            $state['_clientId'] = 'tt_bot_' . time();
            $state['_ts'] = round(microtime(true) * 1000);
            $version = syncStateToMySql($pdo, $roomKey, $state);

            $qIdx = 1;
            foreach ($state['queue'] as $i => $p) {
                if ($p['id'] === $newPlayer['id']) {
                    $qIdx = $i + 1;
                    break;
                }
            }

            sendJson([
                'status' => 'ok',
                'action' => 'enqueued',
                'tier' => 'FREE',
                'player' => [
                    'name' => $displayName,
                    'char' => $fullChar,
                    'ulti' => $fullUlti,
                    'type' => 'FREE',
                    'queuePos' => $qIdx
                ],
                'queueTotal' => count($state['queue']),
                'version' => $version,
                'message' => "Pemain {$displayName} berhasil masuk antrean FREE (#{$qIdx})!"
            ], 200);
        }

        // ========================================================
        // KASUS 2: VIP / SROBOT -> CEK APAKAH SUDAH GIFT?
        // ========================================================
        $isGiftSatisfied = ($availableCoins >= $requiredCoins && $requiredCoins > 0);

        if ($isGiftSatisfied) {
            // VIEWER SUDAH GIFT -> AUTO MASUK KE ANTREAN LANGSUNG!
            if (!isset($state['liveGiftLedger']) || !is_array($state['liveGiftLedger'])) {
                $state['liveGiftLedger'] = [];
            }
            if (!isset($state['liveGiftLedger'][$userKeyToUse])) {
                $state['liveGiftLedger'][$userKeyToUse] = [
                    'nickname' => $displayName,
                    'uniqueId' => $uniqueId,
                    'totalCoins' => $totalCoins,
                    'usedCoins' => 0
                ];
            }
            $state['liveGiftLedger'][$userKeyToUse]['usedCoins'] = $usedCoins + $requiredCoins;

            $matches = ($tier === 'SROBOT') ? (($activeMode === '1v1') ? 2 : 1) : 1;

            $newPlayer = [
                'id' => 'pl_tt_' . time() . '_' . substr(md5($displayName . microtime()), 0, 6),
                'name' => $displayName, // NAMA TIKTOK DISPLAY NAME
                'tiktokUsername' => $uniqueId,
                'char' => $fullChar,
                'ulti' => $fullUlti,
                'matchesLeft' => $matches,
                'ws' => 0,
                'type' => $tier,
                'ticketMode' => $activeMode,
                'registeredAt' => round(microtime(true) * 1000),
                'status' => 'APPROVED',
                'source' => 'TIKTOK_LIVE_BOT'
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

            $state['_clientId'] = 'tt_bot_' . time();
            $state['_ts'] = round(microtime(true) * 1000);
            $version = syncStateToMySql($pdo, $roomKey, $state);

            $qIdx = 1;
            foreach ($state['queue'] as $i => $p) {
                if ($p['id'] === $newPlayer['id']) {
                    $qIdx = $i + 1;
                    break;
                }
            }

            sendJson([
                'status' => 'ok',
                'action' => 'enqueued_paid',
                'tier' => $tier,
                'player' => [
                    'name' => $displayName,
                    'char' => $fullChar,
                    'ulti' => $fullUlti,
                    'type' => $tier,
                    'queuePos' => $qIdx
                ],
                'queueTotal' => count($state['queue']),
                'version' => $version,
                'message' => "✅ [AUTO GIFT] Pemain {$displayName} langsung otomatis masuk antrean {$tier} (#{$qIdx}) karena sudah mengirim gift ({$availableCoins} koin)!"
            ], 200);

        } else {
            // VIEWER BELUM GIFT -> MASUK KE PENDING APPROVAL (ATAS PERSETUJUAN ADMIN ATAU GIFT)
            $matches = ($tier === 'SROBOT') ? (($activeMode === '1v1') ? 2 : 1) : 1;

            $pendingPlayer = [
                'id' => 'pend_tt_' . time() . '_' . substr(md5($displayName . microtime()), 0, 6),
                'name' => $displayName, // NAMA TIKTOK DISPLAY NAME
                'tiktokUsername' => $uniqueId,
                'char' => $fullChar,
                'ulti' => $fullUlti,
                'matchesLeft' => $matches,
                'ws' => 0,
                'type' => $tier,
                'ticketMode' => $activeMode,
                'registeredAt' => round(microtime(true) * 1000),
                'status' => 'PENDING',
                'source' => 'TIKTOK_LIVE_BOT',
                'note' => "Menunggu persetujuan admin atau gift viewer ({$requiredCoins} Koin)"
            ];

            if (!isset($state['pendingApprovals']) || !is_array($state['pendingApprovals'])) {
                $state['pendingApprovals'] = [];
            }
            $state['pendingApprovals'][] = $pendingPlayer;

            $state['_clientId'] = 'tt_bot_' . time();
            $state['_ts'] = round(microtime(true) * 1000);
            $version = syncStateToMySql($pdo, $roomKey, $state);

            sendJson([
                'status' => 'ok',
                'action' => 'pending_approval',
                'tier' => $tier,
                'player' => [
                    'name' => $displayName,
                    'char' => $fullChar,
                    'ulti' => $fullUlti,
                    'type' => $tier,
                    'pendingPos' => count($state['pendingApprovals'])
                ],
                'pendingTotal' => count($state['pendingApprovals']),
                'version' => $version,
                'message' => "⏳ [MENUNGGU PERSETUJUAN ADMIN] Permintaan {$tier} dari {$displayName} masuk daftar persetujuan Admin (Viewer belum kirim gift {$requiredCoins} koin, menunggu persetujuan admin atau gift)."
            ], 200);
        }
    }

    // Event Gift (Auto Upgrade / Auto Approve Pending VIP / SROBOT)
    if ($event === 'gift') {
        $giftObj = $payload['gift'] ?? [];
        $giftName = trim((string)($giftObj['name'] ?? 'Gift'));
        $diamondCount = max(1, (int)($giftObj['diamondCount'] ?? 1));
        $repeatCount = max(1, (int)($giftObj['repeatCount'] ?? 1));
        $coinsReceived = $diamondCount * $repeatCount;

        $userKeyUnique = ($uniqueId !== '') ? mb_strtolower($uniqueId, 'UTF-8') : '';
        $userKeyNick = ($displayName !== '') ? mb_strtolower($displayName, 'UTF-8') : '';
        $userKeyToUse = $userKeyUnique !== '' ? $userKeyUnique : $userKeyNick;

        if (!isset($state['liveGiftLedger']) || !is_array($state['liveGiftLedger'])) {
            $state['liveGiftLedger'] = [];
        }

        // Cari record yang cocok di ledger
        if ($userKeyUnique !== '' && isset($state['liveGiftLedger'][$userKeyUnique])) {
            $userKeyToUse = $userKeyUnique;
        } elseif ($userKeyNick !== '' && isset($state['liveGiftLedger'][$userKeyNick])) {
            $userKeyToUse = $userKeyNick;
        }

        if (!isset($state['liveGiftLedger'][$userKeyToUse])) {
            $state['liveGiftLedger'][$userKeyToUse] = [
                'nickname' => $displayName,
                'uniqueId' => $uniqueId,
                'totalCoins' => 0,
                'usedCoins' => 0
            ];
        }

        $state['liveGiftLedger'][$userKeyToUse]['totalCoins'] += $coinsReceived;
        $state['liveGiftLedger'][$userKeyToUse]['nickname'] = $displayName;
        if ($uniqueId !== '') {
            $state['liveGiftLedger'][$userKeyToUse]['uniqueId'] = $uniqueId;
        }

        $totCoins = (int)$state['liveGiftLedger'][$userKeyToUse]['totalCoins'];
        $usdCoins = (int)$state['liveGiftLedger'][$userKeyToUse]['usedCoins'];
        $availCoins = max(0, $totCoins - $usdCoins);

        // ========================================================
        // 1. CEK APAKAH VIEWER MEMILIKI REQUEST DI PENDING APPROVALS?
        // ========================================================
        $pendingIndex = -1;
        $pendingPlayer = null;
        if (!empty($state['pendingApprovals']) && is_array($state['pendingApprovals'])) {
            foreach ($state['pendingApprovals'] as $idx => $pend) {
                $pNick = mb_strtolower(trim($pend['name'] ?? ''), 'UTF-8');
                $pUser = mb_strtolower(trim($pend['tiktokUsername'] ?? ''), 'UTF-8');
                if (($userKeyNick !== '' && $pNick === $userKeyNick) ||
                    ($userKeyUnique !== '' && $pUser === $userKeyUnique)) {
                    $pendingIndex = $idx;
                    $pendingPlayer = $pend;
                    break;
                }
            }
        }

        if ($pendingPlayer !== null && $pendingIndex !== -1) {
            $pTier = strtoupper($pendingPlayer['type'] ?? 'VIP');
            $cost = ($pTier === 'SROBOT') ? 30 : 5;

            if ($availCoins >= $cost) {
                // AUTO-APPROVE DARI PENDING KE ANTREAN!
                $state['liveGiftLedger'][$userKeyToUse]['usedCoins'] += $cost;
                array_splice($state['pendingApprovals'], $pendingIndex, 1);

                $pendingPlayer['status'] = 'APPROVED';
                $pendingPlayer['source'] = 'TIKTOK_LIVE_BOT_GIFT_AUTO';
                if (!isset($state['queue']) || !is_array($state['queue'])) {
                    $state['queue'] = [];
                }
                $state['queue'][] = $pendingPlayer;
                sortQueuePhp($state);

                if (!isset($state['registeredUsers']) || !is_array($state['registeredUsers'])) {
                    $state['registeredUsers'] = [];
                }
                if (!in_array($displayName, $state['registeredUsers'], true)) {
                    $state['registeredUsers'][] = $displayName;
                }

                $state['_clientId'] = 'tt_bot_' . time();
                $state['_ts'] = round(microtime(true) * 1000);
                $version = syncStateToMySql($pdo, $roomKey, $state);

                $qIdx = 1;
                foreach ($state['queue'] as $i => $q) {
                    if ($q['id'] === $pendingPlayer['id']) {
                        $qIdx = $i + 1;
                        break;
                    }
                }

                sendJson([
                    'status' => 'ok',
                    'action' => 'auto_approved_from_pending',
                    'tier' => $pTier,
                    'player' => [
                        'name' => $displayName,
                        'char' => $pendingPlayer['char'] ?? '-',
                        'type' => $pTier,
                        'queuePos' => $qIdx
                    ],
                    'queueTotal' => count($state['queue']),
                    'version' => $version,
                    'message' => "✅ [AUTO APPROVAL] Viewer {$displayName} kirim Gift {$giftName} (+{$coinsReceived} koin). Permintaan {$pTier} disetujui otomatis dan langsung masuk antrean (#{$qIdx})!"
                ], 200);
            }
        }

        // ========================================================
        // 2. CEK APAKAH VIEWER SUDAH ADA DI ANTREAN (UPGRADE KE VIP/SROBOT)
        // ========================================================
        $queueIndex = -1;
        if (!empty($state['queue']) && is_array($state['queue'])) {
            foreach ($state['queue'] as $idx => $qPlayer) {
                $qNick = mb_strtolower(trim($qPlayer['name'] ?? ''), 'UTF-8');
                $qUser = mb_strtolower(trim($qPlayer['tiktokUsername'] ?? ''), 'UTF-8');
                if (($userKeyNick !== '' && $qNick === $userKeyNick) ||
                    ($userKeyUnique !== '' && $qUser === $userKeyUnique)) {
                    $queueIndex = $idx;
                    break;
                }
            }
        }

        if ($queueIndex !== -1) {
            $currType = strtoupper($state['queue'][$queueIndex]['type'] ?? 'FREE');
            if ($availCoins >= 30 && $currType !== 'SROBOT') {
                $state['liveGiftLedger'][$userKeyToUse]['usedCoins'] += 30;
                $state['queue'][$queueIndex]['type'] = 'SROBOT';
                $state['queue'][$queueIndex]['matchesLeft'] = ($activeMode === '1v1') ? 2 : 1;
                sortQueuePhp($state);
                $state['_clientId'] = 'tt_bot_' . time();
                $state['_ts'] = round(microtime(true) * 1000);
                syncStateToMySql($pdo, $roomKey, $state);

                sendJson([
                    'status' => 'ok',
                    'action' => 'upgraded',
                    'tier' => 'SROBOT',
                    'player' => $state['queue'][$queueIndex],
                    'message' => "⭐ [UPGRADE SROBOT] Pemain {$displayName} di antrean di-upgrade ke SROBOT karena kirim Gift {$giftName} (+{$coinsReceived} koin)!"
                ], 200);
            } elseif ($availCoins >= 5 && $currType === 'FREE') {
                $state['liveGiftLedger'][$userKeyToUse]['usedCoins'] += 5;
                $state['queue'][$queueIndex]['type'] = 'VIP';
                sortQueuePhp($state);
                $state['_clientId'] = 'tt_bot_' . time();
                $state['_ts'] = round(microtime(true) * 1000);
                syncStateToMySql($pdo, $roomKey, $state);

                sendJson([
                    'status' => 'ok',
                    'action' => 'upgraded',
                    'tier' => 'VIP',
                    'player' => $state['queue'][$queueIndex],
                    'message' => "⭐ [UPGRADE VIP] Pemain {$displayName} di antrean di-upgrade ke VIP karena kirim Gift {$giftName} (+{$coinsReceived} koin)!"
                ], 200);
            }
        }

        // ========================================================
        // 3. CATAT GIFT BIASA KE LEDGER
        // ========================================================
        $state['_clientId'] = 'tt_bot_' . time();
        $state['_ts'] = round(microtime(true) * 1000);
        syncStateToMySql($pdo, $roomKey, $state);

        sendJson([
            'status' => 'ok',
            'action' => 'gift_recorded',
            'availableCoins' => $availCoins,
            'message' => "🎁 Gift {$giftName} (+{$coinsReceived} koin) dari {$displayName} berhasil dicatat (Saldo Koin: {$availCoins}). Viewer bisa mengetik '!req vip <char>' atau '!req srobot <char>' untuk auto masuk antrean!"
        ], 200);
    }

    sendJson(['status' => 'error', 'message' => "Event '{$event}' tidak dikenali."], 400);
}

sendJson(['status' => 'error', 'message' => 'Method tidak didukung.'], 405);
