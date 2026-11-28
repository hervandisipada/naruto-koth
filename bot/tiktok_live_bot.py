#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
============================================================
NARUTO STORM KOTH - TIKTOK LIVE AUTO-QUEUE BOT 🍃
============================================================
Menghubungkan TikTok Live stream @herzg1209 langsung ke antrean
pertandingan Naruto KotH.

Viewer cukup mengetik komentar di live chat TikTok:
  - 1v1     : !req Naruto
  - DUO/TIM : !req Sasuke / Naruto
Pemain akan langsung otomatis terdaftar di Database & Overlay!

*Catatan penting: Nama yang diambil adalah NAMA TIKTOK (Nickname),
bukan username @unique_id.
"""

import sys
import time
import asyncio
import argparse
import requests
from typing import Optional

from TikTokLive import TikTokLiveClient
from TikTokLive.events import (
    ConnectEvent,
    DisconnectEvent,
    CommentEvent,
    GiftEvent,
    LikeEvent
)

# ANSI Color Codes
CLR_RESET = "\033[0m"
CLR_GREEN = "\033[92m"
CLR_YELLOW = "\033[93m"
CLR_RED = "\033[91m"
CLR_CYAN = "\033[96m"
CLR_BLUE = "\033[94m"
CLR_MAGENTA = "\033[95m"
CLR_BOLD = "\033[1m"

def print_banner(host: str, api_url: str, room: str):
    print(f"{CLR_CYAN}{CLR_BOLD}")
    print("╔════════════════════════════════════════════════════════════════════╗")
    print("║          NARUTO STORM KOTH - TIKTOK LIVE AUTO-QUEUE BOT 🍃          ║")
    print("╠════════════════════════════════════════════════════════════════════╣")
    print(f"║  Target Host TikTok : @{host:<43}║")
    print(f"║  Room Key           : {room:<45}║")
    print(f"║  API Backend        : {api_url:<45}║")
    print("║  Perintah Viewer    : !req <karakter> atau req <karakter>          ║")
    print("║  Mode DUO / TIM     : !req <char1> / <char2>                       ║")
    print("╚════════════════════════════════════════════════════════════════════╝")
    print(f"{CLR_RESET}")

def log_info(msg: str):
    t = time.strftime("%H:%M:%S")
    print(f"{CLR_BLUE}[{t}] [INFO]{CLR_RESET} {msg}")

def log_success(msg: str):
    t = time.strftime("%H:%M:%S")
    print(f"{CLR_GREEN}{CLR_BOLD}[{t}] [SUKSES]{CLR_RESET} {msg}")

def log_warn(msg: str):
    t = time.strftime("%H:%M:%S")
    print(f"{CLR_YELLOW}[{t}] [PERINGATAN]{CLR_RESET} {msg}")

def log_error(msg: str):
    t = time.strftime("%H:%M:%S")
    print(f"{CLR_RED}[{t}] [DITOLAK]{CLR_RESET} {msg}")

def send_to_backend(api_url: str, room: str, payload: dict) -> Optional[dict]:
    try:
        url = f"{api_url}?room={room}"
        res = requests.post(url, json=payload, timeout=5)
        if res.status_code == 200:
            return res.json()
        log_warn(f"Backend HTTP {res.status_code}: {res.text[:120]}")
    except Exception as e:
        log_warn(f"Gagal mengirim request ke backend: {e}")
    return None

async def run_bot(host: str, api_url: str, room: str):
    client = TikTokLiveClient(unique_id=host)

    @client.on(ConnectEvent)
    async def on_connect(event: ConnectEvent):
        log_success(f"Tersambung ke Live Streaming @{host}!")
        log_info("Bot aktif mendengarkan komentar viewer live...")

    @client.on(DisconnectEvent)
    async def on_disconnect(event: DisconnectEvent):
        log_warn(f"Koneksi ke Live Streaming @{host} terputus.")

    @client.on(CommentEvent)
    async def on_comment(event: CommentEvent):
        raw_comment = event.comment.strip()
        nickname = (event.user.nickname or "").strip()
        unique_id = (event.user.unique_id or "").strip()
        
        # PERMINTAAN USER: Ambil NAMA TIKTOK (Nickname/Display Name), bukan username!
        display_name = nickname if nickname else unique_id

        # Cek apakah komentar merupakan perintah request
        prefixes = (
            "!req", "!daftar", "!main", "!play", "req ", "daftar ",
            "!vip", "!srobot", "!sb", "!free", "vip ", "srobot ", "sb "
        )
        low = raw_comment.lower()
        is_command = any(low.startswith(p) for p in prefixes) or low.startswith("req:") or low.startswith("req-")

        if not is_command:
            return

        log_info(f"Komen Request dari: {CLR_BOLD}{display_name}{CLR_RESET} (@{unique_id}): \"{raw_comment}\"")

        payload = {
            "event": "comment",
            "user": {
                "nickname": nickname,
                "uniqueId": unique_id,
                "userId": str(event.user.user_id) if hasattr(event.user, "user_id") else ""
            },
            "comment": raw_comment
        }

        resp = send_to_backend(api_url, room, payload)
        if not resp:
            return

        status = resp.get("status")
        if status == "ok":
            action = resp.get("action")
            player = resp.get("player", {})
            p_name = player.get("name", display_name)
            p_char = player.get("char", "-")
            p_tier = player.get("type", "FREE")
            p_pos = player.get("queuePos", "?")
            total = resp.get("queueTotal", "?")

            if action == "enqueued":
                log_success(f"[✅ FREE] MASUK ANTREAN #{p_pos}: {CLR_BOLD}{p_name}{CLR_RESET} | Karakter: {CLR_CYAN}{p_char}{CLR_RESET} (Total Antrean: {total} pemain)")
            elif action == "enqueued_paid":
                log_success(f"[🌟 {p_tier} - AUTO GIFT] MASUK ANTREAN #{p_pos}: {CLR_BOLD}{p_name}{CLR_RESET} | Karakter: {CLR_CYAN}{p_char}{CLR_RESET} (Total Antrean: {total} pemain)")
            elif action == "pending_approval":
                pend_pos = player.get("pendingPos", "?")
                log_warn(f"[⏳ {p_tier} - MENUNGGU ADMIN/GIFT] {CLR_BOLD}{p_name}{CLR_RESET} | Karakter: {CLR_CYAN}{p_char}{CLR_RESET} masuk daftar persetujuan Admin (#{pend_pos}). (Viewer belum kirim gift atau menunggu persetujuan admin).")
            else:
                log_success(resp.get("message", "Request diproses."))
        elif status == "rejected":
            reason = resp.get("reason", "Ditolak sistem")
            log_error(f"{display_name} ditolak: {reason}")
        elif status == "ignored":
            reason = resp.get("reason", "")
            if reason:
                log_info(f"Diabaikan: {reason}")

    @client.on(GiftEvent)
    async def on_gift(event: GiftEvent):
        nickname = (event.user.nickname or "").strip()
        unique_id = (event.user.unique_id or "").strip()
        display_name = nickname if nickname else unique_id

        gift_name = event.gift.name or "Gift"
        diamond_count = getattr(event.gift, "diamond_count", 1) or 1
        repeat_count = getattr(event.gift, "repeat_count", 1) or 1
        total_coins = diamond_count * repeat_count

        log_info(f"{CLR_MAGENTA}🎁 GIFT DITERIMA:{CLR_RESET} {display_name} mengirim {repeat_count}x {gift_name} ({total_coins} Koin)")

        payload = {
            "event": "gift",
            "user": {
                "nickname": nickname,
                "uniqueId": unique_id,
                "userId": str(event.user.user_id) if hasattr(event.user, "user_id") else ""
            },
            "gift": {
                "name": gift_name,
                "diamondCount": diamond_count,
                "repeatCount": repeat_count
            }
        }

        resp = send_to_backend(api_url, room, payload)
        if resp and resp.get("status") == "ok":
            action = resp.get("action")
            msg = resp.get("message", "")
            if action == "auto_approved_from_pending":
                log_success(f"🎉 {msg}")
            elif action == "upgraded":
                log_success(f"⭐ {msg}")
            elif msg:
                log_info(msg)

    # Main loop dengan auto-reconnect
    while True:
        try:
            log_info(f"Mengecek status live stream @{host}...")
            is_live = await client.is_live()
            if not is_live:
                log_warn(f"Akun @{host} saat ini sedang TIDAK Live di TikTok.")
                log_info("Bot akan mengecek kembali otomatis dalam 15 detik... (Silakan mulai Live di TikTok)")
                await asyncio.sleep(15)
                continue

            log_info(f"Akun @{host} SEDANG LIVE! Menyambungkan bot...")
            await client.connect()
        except asyncio.CancelledError:
            break
        except Exception as e:
            log_warn(f"Koneksi terputus: {e}")
            log_info("Mencoba menyambung kembali dalam 10 detik...")
            await asyncio.sleep(10)

def main():
    parser = argparse.ArgumentParser(description="Naruto Storm KotH - TikTok Live Auto-Queue Bot")
    parser.add_argument("--host", default="herzg1209", help="Username host TikTok (tanpa @)")
    parser.add_argument("--room", default="hervandisipada", help="Room key KotH")
    parser.add_argument("--api-url", default="http://localhost/naruto-koth/api/live-request.php", help="URL endpoint live request")
    parser.add_argument("--test-comment", default=None, help="Kirim komentar tes ke backend langsung")
    parser.add_argument("--test-gift", type=int, default=None, help="Simulasi kirim gift sejumlah koin")
    parser.add_argument("--test-name", default="Penonton Setia (Nama TikTok)", help="Nama display untuk tes")
    args = parser.parse_args()

    host_clean = args.host.lstrip("@").strip()
    print_banner(host_clean, args.api_url, args.room)

    import re
    test_uid = "u_" + re.sub(r'[^a-zA-Z0-9_]', '', args.test_name.lower())[:20]

    # Mode Tes Simulasi Gift
    if args.test_gift is not None:
        log_info(f"Menjalankan TES Gift ({args.test_gift} koin) untuk '{args.test_name}'...")
        payload = {
            "event": "gift",
            "user": {
                "nickname": args.test_name,
                "uniqueId": test_uid,
                "userId": "999999"
            },
            "gift": {
                "name": "Test Gift",
                "diamondCount": args.test_gift,
                "repeatCount": 1
            }
        }
        res = send_to_backend(args.api_url, args.room, payload)
        print("Response Gift:", res)
        return

    # Mode Tes Langsung Komentar
    if args.test_comment:
        log_info(f"Menjalankan TES komentar dari terminal...")
        payload = {
            "event": "comment",
            "user": {
                "nickname": args.test_name,
                "uniqueId": test_uid,
                "userId": "999999"
            },
            "comment": args.test_comment
        }
        res = send_to_backend(args.api_url, args.room, payload)
        print("Response Komentar:", res)
        return

    # Jalankan bot
    try:
        asyncio.run(run_bot(host_clean, args.api_url, args.room))
    except KeyboardInterrupt:
        print(f"\n{CLR_YELLOW}Bot dimatikan oleh pengguna. Sampai jumpa!{CLR_RESET}")

if __name__ == "__main__":
    main()
