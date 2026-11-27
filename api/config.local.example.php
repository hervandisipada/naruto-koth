<?php
declare(strict_types=1);

/**
 * Salin file ini menjadi `api/config.local.php` untuk kredensial database produksi
 * (misalnya di InfinityFree). File `api/config.local.php` sudah masuk .gitignore
 * sehingga password tidak akan ikut ter-push ke GitHub.
 */
return [
    'host' => 'sqlXXX.infinityfree.com',
    'port' => 3306,
    'dbname' => 'if0_XXXXXXXX_naruto_koth',
    'username' => 'if0_XXXXXXXX',
    'password' => 'ISI_PASSWORD_DB_ANDA',
    'charset' => 'utf8mb4',
];
