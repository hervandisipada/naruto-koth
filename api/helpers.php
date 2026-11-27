<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function sendApiHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function resolveRoomKey(?string $override = null): string
{
    $raw = $override ?? $_GET['room'] ?? $_GET['key'] ?? 'hervandisipada';
    $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $raw);
    if ($clean === '') {
        return 'hervandisipada';
    }
    return substr($clean, 0, 64);
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('KOTH_SESSID');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isAdminAuthenticated(string $roomKey): bool
{
    startAdminSession();
    return !empty($_SESSION['koth_admin_rooms'][$roomKey]);
}

function ensureDefaultAdmin(PDO $pdo, string $roomKey): void
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM koth_admins WHERE room_key = ?');
    $check->execute([$roomKey]);
    if ((int) $check->fetchColumn() === 0) {
        $defaultHash = password_hash('7878', PASSWORD_BCRYPT);
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO koth_admins (room_key, username, pin_hash) VALUES (?, ?, ?)'
        );
        $ins->execute([$roomKey, 'admin', $defaultHash]);
    }
}

function getDefaultState(): array
{
    return [
        'p1' => null,
        'p2' => null,
        'queue' => [],
        'saved' => [],
        'pendingApprovals' => [],
        'freeHistory' => [],
        'overlayListMode' => 'queue',
        'announcement' => '',
        'customCharacters' => [],
        'masterMode' => '1v1',
        'arena1v1' => ['p1' => null, 'p2' => null],
        'arenaDuo' => ['p1' => null, 'p2' => null],
        'arenaTim' => ['p1' => null, 'p2' => null],
        'topWsOverlay' => ['1v1' => true, 'DUO' => true, 'TIM' => true],
        'topRecords' => [
            ['rank' => 1, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 2, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 3, 'name' => '-', 'char' => '-', 'ws' => 0],
        ],
        'topRecords1v1' => [
            ['rank' => 1, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 2, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 3, 'name' => '-', 'char' => '-', 'ws' => 0],
        ],
        'topRecordsDuo' => [
            ['rank' => 1, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 2, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 3, 'name' => '-', 'char' => '-', 'ws' => 0],
        ],
        'topRecordsTim' => [
            ['rank' => 1, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 2, 'name' => '-', 'char' => '-', 'ws' => 0],
            ['rank' => 3, 'name' => '-', 'char' => '-', 'ws' => 0],
        ],
        'topRecord' => [
            'name' => '-',
            'char' => '-',
            'ws' => 0,
        ],
        'tierStatus' => [
            'SROBOT' => 'AKTIF',
            'VIP' => 'AKTIF',
            'FREE' => 'AKTIF',
        ],
        'vipConsecutiveCount' => 0,
        'registeredUsers' => [],
    ];
}

function normalizePlayerTier(string $rawTier): string
{
    $upper = strtoupper(trim($rawTier));
    if (in_array($upper, ['SROBOT', 'SB', 'SROBOT++', 'FAST', 'VVIP'], true)) {
        return 'SROBOT';
    }
    if ($upper === 'VIP') {
        return 'VIP';
    }
    return 'FREE';
}

function syncPlayersRows(PDO $pdo, string $roomKey, array $state): void
{
    $del = $pdo->prepare('DELETE FROM koth_players WHERE room_key = ?');
    $del->execute([$roomKey]);

    $ins = $pdo->prepare(
        'INSERT INTO koth_players
         (room_key, player_uid, name, char_name, ulti, tier, ticket_mode, matches_left, ws, slot_status, arena_mode, sort_order, extra_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $insertPlayer = static function (array $p, string $status, string $arenaMode, int $order) use ($ins, $roomKey): void {
        $name = trim((string) ($p['name'] ?? ''));
        if ($name === '') {
            return;
        }
        $extra = [];
        foreach (['bonusBase', 'bonusAnimUntil', 'savedAt', 'registeredAt'] as $k) {
            if (array_key_exists($k, $p)) {
                $extra[$k] = $p[$k];
            }
        }
        $ins->execute([
            $roomKey,
            substr((string) ($p['id'] ?? uniqid('p_', true)), 0, 64),
            substr($name, 0, 120),
            substr((string) ($p['char'] ?? '-'), 0, 255),
            substr((string) ($p['ulti'] ?? ''), 0, 255),
            normalizePlayerTier((string) ($p['type'] ?? 'FREE')),
            substr((string) ($p['ticketMode'] ?? $arenaMode), 0, 16),
            (int) ($p['matchesLeft'] ?? 1),
            (int) ($p['ws'] ?? 0),
            $status,
            $arenaMode,
            $order,
            $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
        ]);
    };

    $arenas = [
        '1v1' => $state['arena1v1'] ?? ['p1' => $state['p1'] ?? null, 'p2' => $state['p2'] ?? null],
        'DUO' => $state['arenaDuo'] ?? ['p1' => null, 'p2' => null],
        'TIM' => $state['arenaTim'] ?? ['p1' => null, 'p2' => null],
    ];

    foreach ($arenas as $mode => $slots) {
        if (!is_array($slots)) {
            continue;
        }
        if (!empty($slots['p1']) && is_array($slots['p1'])) {
            $insertPlayer($slots['p1'], 'arena_p1', $mode, 1);
        }
        if (!empty($slots['p2']) && is_array($slots['p2'])) {
            $insertPlayer($slots['p2'], 'arena_p2', $mode, 2);
        }
    }

    if (!empty($state['queue']) && is_array($state['queue'])) {
        foreach (array_values($state['queue']) as $idx => $p) {
            if (is_array($p)) {
                $insertPlayer($p, 'queue', (string) ($p['ticketMode'] ?? '1v1'), $idx + 1);
            }
        }
    }

    if (!empty($state['saved']) && is_array($state['saved'])) {
        foreach (array_values($state['saved']) as $idx => $p) {
            if (is_array($p)) {
                $insertPlayer($p, 'saved', (string) ($p['ticketMode'] ?? '1v1'), $idx + 1);
            }
        }
    }
}

function syncUsersRows(PDO $pdo, string $roomKey, array $state): void
{
    $usersMap = [];

    $registerName = static function (string $rawName, string $char = '-', string $tier = 'FREE', int $ws = 0) use (&$usersMap): void {
        $name = trim($rawName);
        if ($name === '' || $name === '-' || strcasecmp($name, 'Menunggu...') === 0) {
            return;
        }
        $key = mb_strtolower($name, 'UTF-8');
        if (!isset($usersMap[$key])) {
            $usersMap[$key] = [
                'username' => substr($name, 0, 120),
                'last_char' => '-',
                'last_tier' => 'FREE',
                'best_ws' => 0,
            ];
        }
        if ($char !== '' && $char !== '-') {
            $usersMap[$key]['last_char'] = substr($char, 0, 255);
        }
        if ($tier !== '') {
            $usersMap[$key]['last_tier'] = normalizePlayerTier($tier);
        }
        if ($ws > $usersMap[$key]['best_ws']) {
            $usersMap[$key]['best_ws'] = $ws;
        }
    };

    if (!empty($state['registeredUsers']) && is_array($state['registeredUsers'])) {
        foreach ($state['registeredUsers'] as $u) {
            $uName = is_string($u) ? $u : (is_array($u) ? (string) ($u['name'] ?? '') : '');
            $registerName($uName);
        }
    }

    $harvestPlayer = static function ($p) use ($registerName): void {
        if (!is_array($p) || empty($p['name'])) {
            return;
        }
        $registerName(
            (string) $p['name'],
            (string) ($p['char'] ?? '-'),
            (string) ($p['type'] ?? 'FREE'),
            (int) ($p['ws'] ?? 0)
        );
    };

    $harvestPlayer($state['p1'] ?? null);
    $harvestPlayer($state['p2'] ?? null);
    foreach (['arena1v1', 'arenaDuo', 'arenaTim'] as $arenaKey) {
        if (!empty($state[$arenaKey]) && is_array($state[$arenaKey])) {
            $harvestPlayer($state[$arenaKey]['p1'] ?? null);
            $harvestPlayer($state[$arenaKey]['p2'] ?? null);
        }
    }
    foreach (['queue', 'saved'] as $listKey) {
        if (!empty($state[$listKey]) && is_array($state[$listKey])) {
            foreach ($state[$listKey] as $p) {
                $harvestPlayer($p);
            }
        }
    }
    foreach (['topRecords', 'topRecords1v1', 'topRecordsDuo', 'topRecordsTim'] as $recKey) {
        if (!empty($state[$recKey]) && is_array($state[$recKey])) {
            foreach ($state[$recKey] as $r) {
                if (is_array($r) && !empty($r['name'])) {
                    $registerName((string) $r['name'], (string) ($r['char'] ?? '-'), '', (int) ($r['ws'] ?? 0));
                }
            }
        }
    }

    if (!$usersMap) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO koth_users (room_key, username, last_char, last_tier, best_ws)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           last_char = IF(VALUES(last_char) <> "-", VALUES(last_char), last_char),
           last_tier = VALUES(last_tier),
           best_ws = GREATEST(best_ws, VALUES(best_ws))'
    );

    foreach ($usersMap as $u) {
        $stmt->execute([
            $roomKey,
            $u['username'],
            $u['last_char'],
            $u['last_tier'],
            $u['best_ws'],
        ]);
    }
}

function syncTopRecordsRows(PDO $pdo, string $roomKey, array $state): void
{
    $modeMap = [
        '1v1' => $state['topRecords1v1'] ?? ($state['topRecords'] ?? []),
        'DUO' => $state['topRecordsDuo'] ?? [],
        'TIM' => $state['topRecordsTim'] ?? [],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO koth_top_records (room_key, mode, rank_pos, player_name, char_name, ws)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           player_name = VALUES(player_name),
           char_name = VALUES(char_name),
           ws = VALUES(ws)'
    );

    foreach ($modeMap as $mode => $records) {
        if (!is_array($records)) {
            continue;
        }
        foreach ([0, 1, 2] as $idx) {
            $rec = $records[$idx] ?? ['rank' => $idx + 1, 'name' => '-', 'char' => '-', 'ws' => 0];
            $stmt->execute([
                $roomKey,
                $mode,
                (int) ($rec['rank'] ?? ($idx + 1)),
                substr((string) ($rec['name'] ?? '-'), 0, 120),
                substr((string) ($rec['char'] ?? '-'), 0, 255),
                max(0, (int) ($rec['ws'] ?? 0)),
            ]);
        }
    }
}

function syncCustomCharsRows(PDO $pdo, string $roomKey, array $state): void
{
    if (empty($state['customCharacters']) || !is_array($state['customCharacters'])) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO koth_custom_characters (room_key, char_name) VALUES (?, ?)'
    );

    foreach ($state['customCharacters'] as $charName) {
        $clean = trim((string) $charName);
        if ($clean !== '') {
            $stmt->execute([$roomKey, substr($clean, 0, 120)]);
        }
    }
}

function syncStateToMySql(PDO $pdo, string $roomKey, array $state): int
{
    if (empty($state['masterMode']) || !in_array($state['masterMode'], ['1v1', 'DUO', 'TIM'], true)) {
        $state['masterMode'] = '1v1';
    }
    if (empty($state['arena1v1']) || !is_array($state['arena1v1'])) {
        $state['arena1v1'] = ['p1' => $state['p1'] ?? null, 'p2' => $state['p2'] ?? null];
    }
    if (empty($state['arenaDuo']) || !is_array($state['arenaDuo'])) {
        $state['arenaDuo'] = ['p1' => null, 'p2' => null];
    }
    if (empty($state['arenaTim']) || !is_array($state['arenaTim'])) {
        $state['arenaTim'] = ['p1' => null, 'p2' => null];
    }

    // Pastikan slot arena aktif selaras dengan p1 / p2
    $curKey = ($state['masterMode'] === 'DUO') ? 'arenaDuo' : (($state['masterMode'] === 'TIM') ? 'arenaTim' : 'arena1v1');
    $state[$curKey]['p1'] = $state['p1'] ?? null;
    $state[$curKey]['p2'] = $state['p2'] ?? null;

    $normalizedJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($normalizedJson === false) {
        throw new RuntimeException('Gagal mengenkode JSON state.');
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO koth_rooms (room_key, state_json, version)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE
               state_json = VALUES(state_json),
               version = version + 1'
        );
        $stmt->execute([$roomKey, $normalizedJson]);

        syncPlayersRows($pdo, $roomKey, $state);
        syncUsersRows($pdo, $roomKey, $state);
        syncTopRecordsRows($pdo, $roomKey, $state);
        syncCustomCharsRows($pdo, $roomKey, $state);

        $verStmt = $pdo->prepare('SELECT version FROM koth_rooms WHERE room_key = ? LIMIT 1');
        $verStmt->execute([$roomKey]);
        $version = (int) ($verStmt->fetchColumn() ?: 1);

        if ($ownTx) {
            $pdo->commit();
        }
        return $version;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function loadStateFromMySql(PDO $pdo, string $roomKey): array
{
    $stmt = $pdo->prepare('SELECT state_json, version FROM koth_rooms WHERE room_key = ? LIMIT 1');
    $stmt->execute([$roomKey]);
    $row = $stmt->fetch();

    $state = getDefaultState();
    $version = 0;

    if ($row) {
        $decoded = json_decode($row['state_json'], true);
        if (is_array($decoded)) {
            $state = array_merge($state, $decoded);
            if (empty($state['masterMode']) || !in_array($state['masterMode'], ['1v1', 'DUO', 'TIM'], true)) {
                $state['masterMode'] = '1v1';
            }
            if (empty($state['arena1v1']) || !is_array($state['arena1v1'])) {
                $state['arena1v1'] = ['p1' => $state['p1'] ?? null, 'p2' => $state['p2'] ?? null];
            }
            if (empty($state['arenaDuo']) || !is_array($state['arenaDuo'])) {
                $state['arenaDuo'] = ['p1' => null, 'p2' => null];
            }
            if (empty($state['arenaTim']) || !is_array($state['arenaTim'])) {
                $state['arenaTim'] = ['p1' => null, 'p2' => null];
            }
            if (!isset($state['pendingApprovals']) || !is_array($state['pendingApprovals'])) {
                $state['pendingApprovals'] = [];
            }
            if (!isset($state['freeHistory']) || !is_array($state['freeHistory'])) {
                $state['freeHistory'] = [];
            }
            $activeArena = ($state['masterMode'] === 'DUO') ? $state['arenaDuo'] : (($state['masterMode'] === 'TIM') ? $state['arenaTim'] : $state['arena1v1']);
            $state['p1'] = $activeArena['p1'] ?? null;
            $state['p2'] = $activeArena['p2'] ?? null;
            $version = (int) $row['version'];
        }
    }

    // Sinkronkan daftar user terdaftar dari tabel koth_users
    $userStmt = $pdo->prepare('SELECT username FROM koth_users WHERE room_key = ? ORDER BY username ASC');
    $userStmt->execute([$roomKey]);
    $dbUsers = $userStmt->fetchAll(PDO::FETCH_COLUMN);
    if (is_array($dbUsers) && count($dbUsers) > 0) {
        $state['registeredUsers'] = $dbUsers;
    }

    return [
        'state' => $state,
        'version' => $version,
    ];
}
