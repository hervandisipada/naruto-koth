-- =====================================================================
-- Skema Database MySQL Lengkap - Naruto Storm KotH
-- =====================================================================
-- Untuk XAMPP Lokal:
--   Jalankan seluruh script ini di phpMyAdmin (tab SQL).
--
-- Untuk Hosting (InfinityFree):
--   Database sudah dibuat lewat Control Panel InfinityFree, jadi lewati
--   2 baris CREATE DATABASE / USE di bawah, pilih database Anda di
--   phpMyAdmin, lalu jalankan bagian CREATE TABLE & INSERT saja.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `naruto_koth`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `naruto_koth`;

-- 1. State & Konfigurasi Room
CREATE TABLE IF NOT EXISTS `koth_rooms` (
  `room_key` VARCHAR(64) NOT NULL,
  `state_json` LONGTEXT NOT NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Autentikasi & Password/PIN Admin (tersimpan sebagai hash bcrypt)
CREATE TABLE IF NOT EXISTS `koth_admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_key` VARCHAR(64) NOT NULL,
  `username` VARCHAR(64) NOT NULL DEFAULT 'admin',
  `pin_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_admin` (`room_key`, `username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed admin default (PIN awal: 7878) jika belum ada
INSERT IGNORE INTO `koth_admins` (`room_key`, `username`, `pin_hash`)
VALUES ('hervandisipada', 'admin', '$2y$10$RxRdtjyu1pVMI7MO3DYBm.t0TbS6uZL0JTGvdWBt2pB1kE9ylCo4a');

-- 3. Database Master Player / User Terdaftar
CREATE TABLE IF NOT EXISTS `koth_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_key` VARCHAR(64) NOT NULL,
  `username` VARCHAR(120) NOT NULL,
  `last_char` VARCHAR(255) NOT NULL DEFAULT '-',
  `last_tier` VARCHAR(20) NOT NULL DEFAULT 'FREE',
  `best_ws` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_registrations` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_user` (`room_key`, `username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Pemain Aktif di Arena (1v1/DUO/TIM), Match Queue, dan List Tabungan (Saved)
CREATE TABLE IF NOT EXISTS `koth_players` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_key` VARCHAR(64) NOT NULL,
  `player_uid` VARCHAR(64) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `char_name` VARCHAR(255) NOT NULL DEFAULT '-',
  `ulti` VARCHAR(255) NOT NULL DEFAULT '',
  `tier` VARCHAR(20) NOT NULL DEFAULT 'FREE',
  `ticket_mode` VARCHAR(16) NOT NULL DEFAULT '1v1',
  `matches_left` INT NOT NULL DEFAULT 1,
  `ws` INT NOT NULL DEFAULT 0,
  `slot_status` ENUM('arena_p1', 'arena_p2', 'queue', 'saved') NOT NULL,
  `arena_mode` VARCHAR(16) NOT NULL DEFAULT '1v1',
  `sort_order` INT NOT NULL DEFAULT 0,
  `extra_json` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_status` (`room_key`, `slot_status`, `arena_mode`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Rekor Win Streak Tertinggi per Mode (1v1, DUO, TIM)
CREATE TABLE IF NOT EXISTS `koth_top_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_key` VARCHAR(64) NOT NULL,
  `mode` VARCHAR(16) NOT NULL,
  `rank_pos` TINYINT UNSIGNED NOT NULL,
  `player_name` VARCHAR(120) NOT NULL DEFAULT '-',
  `char_name` VARCHAR(255) NOT NULL DEFAULT '-',
  `ws` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_mode_rank` (`room_key`, `mode`, `rank_pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Daftar Karakter Custom
CREATE TABLE IF NOT EXISTS `koth_custom_characters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_key` VARCHAR(64) NOT NULL,
  `char_name` VARCHAR(120) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_char` (`room_key`, `char_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
