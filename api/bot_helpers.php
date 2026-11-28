<?php
declare(strict_types=1);

/**
 * Helper modul untuk pencocokan karakter, parsing komentar TikTok Live,
 * dan manajemen antrean otomatis.
 */

function getCharactersDataset(): array
{
    static $characters = null;
    if ($characters !== null) {
        return $characters;
    }
    $path = __DIR__ . '/characters_data.json';
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $characters = json_decode($json, true) ?: [];
    return $characters;
}

function normalizeCharString(string $str): string
{
    $str = mb_strtolower(trim($str), 'UTF-8');
    // Hilangkan tanda baca & aksen
    $str = str_replace(
        ['ō', 'ū', 'ou', 'uu', 'é', 'è', 'â', 'î', 'ô', 'û'],
        ['o', 'u', 'o', 'u', 'e', 'e', 'a', 'i', 'o', 'u'],
        $str
    );
    $str = preg_replace('/[^a-z0-9]/', '', $str);
    return $str ?? '';
}

function isBannedCharacter(array $c): bool
{
    $name = mb_strtolower($c['name'] ?? '', 'UTF-8');
    if (str_contains($name, 'baryon')) return true;
    if (str_contains($name, 'kushina')) return true;
    if (str_contains($name, 'rin nohara')) return true;
    if (str_contains($name, 'mecha') || str_contains($name, 'robot naruto')) return true;
    if (str_contains($name, 'iruka')) return true;
    if (str_contains($name, 'konohamaru')) return true;
    return false;
}

function isVipExclusiveCharacter(array $c): bool
{
    $name = mb_strtolower($c['name'] ?? '', 'UTF-8');
    $rawName = trim($c['name'] ?? '');

    if (str_starts_with($name, 'hanzou')) return true;
    if (str_contains($name, 'kiba')) return true;
    if (str_contains($name, 'rock lee')) return true;
    if (str_contains($name, 'shisui')) return true;
    if ($rawName === 'Sai' || str_starts_with($name, 'sai ')) return true;
    if (str_contains($name, 'hashirama') && str_contains($name, 'edo')) return true;
    if (str_contains($name, 'minato') && str_contains($name, 'edo')) return true;
    if (str_contains($name, 'ino') && str_contains($name, 'kecil') && !str_contains($name, 'shino')) return true;
    if (str_contains($name, 'kedua mata sharingan')) return true;
    if ($rawName === 'Sasuke Uchiha') return true;
    if (str_starts_with($name, 'might guy') && str_contains($name, 'perang')) return true;
    if (str_contains($name, 'kabuto') && str_contains($name, 'petapa')) return true;
    if ($rawName === 'Mitsuki') return true;

    return false;
}

function getCharBaseNamePhp(array|string $c): string
{
    $raw = is_array($c) ? ($c['name'] ?? '') : (string) $c;
    $raw = trim($raw);
    if ($raw === '') return '';

    $low = mb_strtolower($raw, 'UTF-8');
    if (str_starts_with($low, 'tobi ') || $low === 'tobi' || $low === 'pria bertopeng' || str_contains($low, 'obito')) {
        return 'Obito Uchiha';
    }
    if (str_contains($low, 'robot naruto')) {
        return 'Naruto Uzumaki';
    }
    if (str_starts_with($low, 'sai ') || $low === 'sai') {
        return 'Sai';
    }
    if (str_starts_with($low, 'temari')) {
        return 'Temari';
    }

    $clean = preg_replace('/\(.*?\)/', '', $raw);
    $clean = preg_replace('/\".*?\"/', '', (string) $clean);
    $clean = trim((string) preg_replace('/\s+/', ' ', (string) $clean));
    return $clean;
}

function isSameBaseCharPhp(array $c1, array $c2): bool
{
    $b1 = mb_strtolower(getCharBaseNamePhp($c1), 'UTF-8');
    $b2 = mb_strtolower(getCharBaseNamePhp($c2), 'UTF-8');
    return ($b1 !== '' && $b2 !== '' && $b1 === $b2);
}

/**
 * Pencocokan nama karakter dari input teks viewer (Mendukung alias, typo, & short names)
 */
function matchNarutoCharacter(string $input): ?array
{
    $characters = getCharactersDataset();
    $raw = trim($input);
    if ($raw === '') {
        return null;
    }

    $clean = mb_strtolower($raw, 'UTF-8');
    // Normalisasi sinonim umum
    $clean = str_replace(['reanimated', 'reanimation', 'reanimasi'], 'edo', $clean);
    $clean = str_replace(['kid', 'bocil', 'anak'], 'kecil', $clean);
    $clean = str_replace(['sage mode', 'petapa mode', 'sennin mode'], 'sage', $clean);
    $clean = str_replace(['pds', 'war', 'perang dunia'], 'perang', $clean);
    $normInput = normalizeCharString($clean);

    // 1. Direct exact match
    foreach ($characters as $c) {
        if (mb_strtolower($c['name'], 'UTF-8') === $clean) {
            return $c;
        }
    }

    // 2. Exact match normalized
    foreach ($characters as $c) {
        if (normalizeCharString($c['name']) === $normInput) {
            return $c;
        }
        if (!empty($c['short1v1']) && normalizeCharString($c['short1v1']) === $normInput) {
            return $c;
        }
        if (!empty($c['shortTeam']) && mb_strtolower($c['shortTeam'], 'UTF-8') === $clean) {
            return $c;
        }
    }

    // 3. Kamus Alias Populer
    $aliases = [
        'naruto' => 'Naruto Uzumaki',
        'naruto kecil' => 'Naruto Uzumaki (Masa Kecil)',
        'naruto bijuu' => 'Naruto Uzumaki (Bola Siluman Berekor)',
        'naruto sage' => 'Naruto Uzumaki (Mode Petapa)',
        'naruto petapa' => 'Naruto Uzumaki (Mode Petapa)',
        'naruto kurama' => 'Naruto Uzumaki (Mode Link Kurama)',
        'naruto 6 path' => 'Naruto Uzumaki (Mode Petapa Enam Jalan)',
        'naruto so6p' => 'Naruto Uzumaki (Mode Petapa Enam Jalan)',
        'naruto rikudo' => 'Naruto Uzumaki (Mode Petapa Enam Jalan)',
        'naruto the last' => 'Naruto Uzumaki (The Last)',
        'naruto baryon' => 'Naruto Uzumaki (Mode Baryon)',
        'sasuke' => 'Sasuke Uchiha',
        'sasuke kecil' => 'Sasuke Uchiha (Masa Kecil)',
        'sasuke rinnegan' => 'Sasuke Uchiha (Rinne Sharingan)',
        'sasuke ems' => 'Sasuke Uchiha (Mangekyou Sharingan Abadi)',
        'sasuke taka' => 'Sasuke Uchiha (Taka)',
        'sasuke the last' => 'Sasuke Uchiha (The Last)',
        'minato' => 'Minato Namikaze',
        'minato edo' => 'Minato Namikaze (Edo Tensei)',
        'kakashi' => 'Kakashi Hatake',
        'kakashi dms' => 'Kakashi Hatake (Kedua Mata Sharingan)',
        'kakashi perang' => 'Kakashi Hatake (Perang Dunia Ninja)',
        'kakashi kecil' => 'Kakashi Hatake (Masa Kecil)',
        'guy' => 'Might Guy',
        'might guy' => 'Might Guy',
        'guy perang' => 'Might Guy (Perang Dunia Ninja)',
        'guy malam' => 'Might Guy (Perang Dunia Ninja)',
        'itachi' => 'Itachi Uchiha',
        'itachi edo' => 'Itachi Uchiha (Edo Tensei)',
        'madara' => 'Madara Uchiha',
        'madara edo' => 'Madara Uchiha (Edo Tensei)',
        'madara rikudo' => 'Madara Uchiha (Enam Jalan)',
        'madara 6 path' => 'Madara Uchiha (Enam Jalan)',
        'obito' => 'Obito Uchiha',
        'obito perang' => 'Obito Uchiha',
        'obito jinchuriki' => 'Obito Uchiha (Jinchuriki Ekor Sepuluh)',
        'obito rikudo' => 'Obito Uchiha (Jinchuriki Ekor Sepuluh)',
        'obito kecil' => 'Obito Uchiha (Masa Kecil)',
        'tobi' => 'Tobi',
        'tobi akatsuki' => 'Tobi',
        'pain' => 'Pain',
        'nagato' => 'Nagato',
        'jiraiya' => 'Jiraiya',
        'tsunade' => 'Tsunade',
        'orochimaru' => 'Orochimaru',
        'hashirama' => 'Hashirama Senju',
        'hashirama edo' => 'Hashirama Senju (Edo Tensei)',
        'tobirama' => 'Tobirama Senju',
        'hiruzen' => 'Hiruzen Sarutobi',
        'gaara' => 'Gaara',
        'gaara perang' => 'Gaara (Perang Dunia Ninja)',
        'rock lee' => 'Rock Lee',
        'lee' => 'Rock Lee',
        'neji' => 'Neji Hyuga',
        'hinata' => 'Hinata Hyuga',
        'shisui' => 'Shisui Uchiha',
        'deidara' => 'Deidara',
        'sasori' => 'Sasori',
        'hidan' => 'Hidan',
        'kakuzu' => 'Kakuzu',
        'konan' => 'Konan',
        'kisame' => 'Kisame Hoshigaki',
        'killer bee' => 'Killer Bee',
        'bee' => 'Killer Bee',
        'raikage' => 'Raikage: A',
        'darui' => 'Darui',
        'shikamaru' => 'Shikamaru Nara',
        'chouji' => 'Chouji Akimichi',
        'choji' => 'Chouji Akimichi',
        'ino' => 'Ino Yamanaka',
        'kiba' => 'Kiba Inuzuka',
        'shino' => 'Shino Aburame',
        'tenten' => 'Tenten',
        'sai' => 'Sai',
        'yamato' => 'Yamato',
        'kabuto' => 'Kabuto Yakushi',
        'kabuto snake' => 'Kabuto Yakushi (Jubah Ular)',
        'kabuto sage' => 'Kabuto Yakushi (Mode Petapa)',
        'suigetsu' => 'Suigetsu Hozuki',
        'juugo' => 'Juugo',
        'karin' => 'Karin',
        'kimimaro' => 'Kimimaro',
        'mifune' => 'Mifune',
        'danzo' => 'Danzou Shimura',
        'danzou' => 'Danzou Shimura',
        'boruto' => 'Boruto Uzumaki',
        'sarada' => 'Sarada Uchiha',
        'mitsuki' => 'Mitsuki',
        'kawaki' => 'Kawaki',
        'jigen' => 'Jigen',
        'kashin koji' => 'Kashin Koji',
        'kaguya' => 'Kaguya Otsutsuki',
        'indra' => 'Indra Otsutsuki',
        'ashura' => 'Ashura Otsutsuki',
        'momoshiki' => 'Momoshiki Otsutsuki',
        'kinshiki' => 'Kinshiki Otsutsuki',
        'hanabi' => 'Hanabi Hyuga',
        'kushina' => 'Kushina Uzumaki',
        'kurenai' => 'Kurenai',
        'asuma' => 'Asuma Sarutobi',
        'hanzou' => 'Hanzou',
        'chiyo' => 'Nenek Chiyo',
        'nenek chiyo' => 'Nenek Chiyo',
        'yugito' => 'Yugito Nii',
        'yagura' => 'Yagura',
        'roushi' => 'Roushi',
        'han' => 'Han',
        'utakata' => 'Utakata',
        'fuu' => 'Fuu'
    ];

    if (isset($aliases[$clean])) {
        $targetName = $aliases[$clean];
        foreach ($characters as $c) {
            if ($c['name'] === $targetName) {
                return $c;
            }
        }
    }

    // 4. Substring match (name starts with or contains)
    $bestMatch = null;
    $bestScore = 0;
    foreach ($characters as $c) {
        $normName = normalizeCharString($c['name']);
        if (str_starts_with($normName, $normInput)) {
            return $c;
        }
        if (str_contains($normName, $normInput) && strlen($normInput) >= 3) {
            return $c;
        }
        // Levenshtein similarity
        similar_text($normInput, $normName, $perc);
        if ($perc > $bestScore && $perc >= 65.0) {
            $bestScore = $perc;
            $bestMatch = $c;
        }
    }

    return $bestMatch;
}

/**
 * Parsing teks komentar live stream untuk mengekstrak permintaan karakter dan tier (FREE / VIP / SROBOT)
 */
function parseLiveComment(string $rawComment, string $activeMode): ?array
{
    $trim = trim($rawComment);
    if ($trim === '') return null;

    $tier = 'FREE';
    $charQuery = '';

    // 1. Cek pola dengan Tier eksplisit (misal: !req srobot ..., !req sb ..., !srobot ..., !sb ..., !req vip ..., !vip ..., !req free ...)
    $patternTier = '/^(?:[!#\/]?(?:req(?:uest)?|daftar|main|play|pilih)|req\s*[:\-])?\s*(?:[!#\/]?(srobot|\bsb\b|vip|free))\s+(.+)$/i';
    if (preg_match($patternTier, $trim, $matches)) {
        $rawTier = strtoupper($matches[1]);
        $tier = in_array($rawTier, ['SROBOT', 'SB'], true) ? 'SROBOT' : ($rawTier === 'VIP' ? 'VIP' : 'FREE');
        $charQuery = trim($matches[2]);
    } else {
        // 2. Pola normal tanpa tier eksplisit (default: FREE)
        $patternNormal = '/^(?:[!#\/]?(?:req(?:uest)?|daftar|main|play|pilih)|req\s*[:\-])\s*(.+)$/i';
        if (preg_match($patternNormal, $trim, $matches)) {
            $tier = 'FREE';
            $charQuery = trim($matches[1]);
        }
    }

    if ($charQuery === '') {
        return null;
    }

    // Pisahkan jika mode DUO / TIM (pemisah: /, +, &, atau "dan")
    $activeModeUpper = strtoupper($activeMode);
    $isMulti = ($activeModeUpper === 'DUO' || $activeModeUpper === 'TIM');

    $tokens = [];
    if ($isMulti) {
        $parts = preg_split('/\s*(?:\/|\+|\bdan\b|,)\s*/i', $charQuery);
        foreach ($parts as $p) {
            $cleanP = trim($p);
            if ($cleanP !== '') {
                $tokens[] = $cleanP;
            }
        }
    } else {
        $tokens = [$charQuery];
    }

    if (empty($tokens)) {
        return null;
    }

    $matchedChars = [];
    foreach ($tokens as $token) {
        $matched = matchNarutoCharacter($token);
        if ($matched !== null) {
            $matchedChars[] = $matched;
        }
    }

    return [
        'mode' => $activeModeUpper,
        'tier' => $tier,
        'characters' => $matchedChars,
        'rawQuery' => $charQuery
    ];
}

function checkDuplicateInPending(string $playerName, array $state): ?string
{
    $clean = mb_strtolower(trim($playerName), 'UTF-8');
    if ($clean === '' || $clean === '-' || $clean === 'menunggu...') {
        return null;
    }
    if (!empty($state['pendingApprovals']) && is_array($state['pendingApprovals'])) {
        foreach ($state['pendingApprovals'] as $idx => $p) {
            if (!empty($p['name']) && mb_strtolower(trim($p['name']), 'UTF-8') === $clean) {
                $pos = $idx + 1;
                $t = $p['type'] ?? 'VIP';
                return "Pemain sudah ada di daftar persetujuan {$t} (#{$pos})";
            }
        }
    }
    return null;
}

function checkDuplicateInQueue(string $playerName, array $state): ?string
{
    $clean = mb_strtolower(trim($playerName), 'UTF-8');
    if ($clean === '' || $clean === '-' || $clean === 'menunggu...') {
        return null;
    }

    if (!empty($state['p1']['name']) && mb_strtolower(trim($state['p1']['name']), 'UTF-8') === $clean) {
        return 'Pemain sedang bertanding di Arena (P1)';
    }
    if (!empty($state['p2']['name']) && mb_strtolower(trim($state['p2']['name']), 'UTF-8') === $clean) {
        return 'Pemain sedang bertanding di Arena (P2)';
    }

    foreach (['arena1v1', 'arenaDuo', 'arenaTim'] as $arenaKey) {
        if (!empty($state[$arenaKey]) && is_array($state[$arenaKey])) {
            if (!empty($state[$arenaKey]['p1']['name']) && mb_strtolower(trim($state[$arenaKey]['p1']['name']), 'UTF-8') === $clean) {
                return 'Pemain sedang berada di Arena';
            }
            if (!empty($state[$arenaKey]['p2']['name']) && mb_strtolower(trim($state[$arenaKey]['p2']['name']), 'UTF-8') === $clean) {
                return 'Pemain sedang berada di Arena';
            }
        }
    }

    if (!empty($state['queue']) && is_array($state['queue'])) {
        foreach ($state['queue'] as $idx => $p) {
            if (!empty($p['name']) && mb_strtolower(trim($p['name']), 'UTF-8') === $clean) {
                $pos = $idx + 1;
                return "Pemain sudah ada di antrean (#{$pos})";
            }
        }
    }

    return null;
}

function interleaveVipFreePhp(array $vipPlayers, array $freePlayers, int $vipConsecutiveCount = 0): array
{
    $result = [];
    $vipIndex = 0;
    $freeIndex = 0;
    $consecutive = $vipConsecutiveCount;
    $vipCount = count($vipPlayers);
    $freeCount = count($freePlayers);

    while ($vipIndex < $vipCount || $freeIndex < $freeCount) {
        if ($vipIndex < $vipCount && ($consecutive < 2 || $freeIndex >= $freeCount)) {
            $result[] = $vipPlayers[$vipIndex++];
            $consecutive++;
        } elseif ($freeIndex < $freeCount) {
            $result[] = $freePlayers[$freeIndex++];
            $consecutive = 0;
        } elseif ($vipIndex < $vipCount) {
            $result[] = $vipPlayers[$vipIndex++];
            $consecutive++;
        }
    }

    return $result;
}

function sortQueuePhp(array &$state): void
{
    if (empty($state['queue']) || !is_array($state['queue'])) {
        $state['queue'] = [];
        return;
    }

    $fastPlayers = [];
    $vipPlayers = [];
    $freePlayers = [];

    foreach ($state['queue'] as $p) {
        $t = strtoupper(trim((string)($p['type'] ?? 'FREE')));
        if (in_array($t, ['SROBOT', 'SB', 'FAST', 'VVIP'], true)) {
            $fastPlayers[] = $p;
        } elseif ($t === 'VIP') {
            $vipPlayers[] = $p;
        } else {
            $freePlayers[] = $p;
        }
    }

    $consecutive = (int)($state['vipConsecutiveCount'] ?? 0);
    $interleaved = interleaveVipFreePhp($vipPlayers, $freePlayers, $consecutive);
    $state['queue'] = array_merge($fastPlayers, $interleaved);
}
