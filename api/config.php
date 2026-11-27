<?php
declare(strict_types=1);

/**
 * Konfigurasi & koneksi PDO MySQL untuk Naruto KotH.
 *
 * Urutan prioritas kredensial:
 * 1. File override `api/config.local.php` (di-ignore Git, wajib untuk kredensial produksi).
 * 2. Environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
 * 3. Default XAMPP lokal jika diakses dari localhost / IP LAN / CLI.
 */

function isLocalEnvironment(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $rawHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = strtolower(preg_replace('/:\d+$/', '', trim($rawHost)));

    if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if (substr($host, -6) === '.local' || substr($host, -5) === '.test') {
        return true;
    }

    // Izinkan akses dari HP melalui IP LAN saat running di XAMPP lokal
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return !filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    return false;
}

function getDbConfig(): array
{
    $localOverrideFile = __DIR__ . '/config.local.php';
    if (is_file($localOverrideFile)) {
        $override = require $localOverrideFile;
        if (is_array($override)) {
            return array_merge([
                'host' => '127.0.0.1',
                'port' => 3306,
                'dbname' => 'naruto_koth',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
            ], $override);
        }
    }

    $envHost = getenv('DB_HOST');
    $envName = getenv('DB_NAME');
    $envUser = getenv('DB_USER');
    $envPass = getenv('DB_PASS');
    $envPort = getenv('DB_PORT');

    if ($envHost !== false && $envName !== false && $envUser !== false) {
        return [
            'host' => $envHost,
            'port' => $envPort !== false ? (int) $envPort : 3306,
            'dbname' => $envName,
            'username' => $envUser,
            'password' => $envPass !== false ? $envPass : '',
            'charset' => 'utf8mb4',
        ];
    }

    if (isLocalEnvironment()) {
        return [
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'naruto_koth',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ];
    }

    // Fallback default lokal XAMPP (mencegah crash saat diakses via hostname lokal / mDNS)
    return [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'naruto_koth',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ];
}

function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = getDbConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        (int) $cfg['port'],
        $cfg['dbname'],
        $cfg['charset']
    );

    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
