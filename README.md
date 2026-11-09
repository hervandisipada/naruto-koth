# Naruto Storm Connections - King of the Hill (KotH) Live Overlay

Sistem Antrean & Overlay Interaktif untuk Live Streaming TikTok / YouTube / Twitch game **Naruto x Boruto Ultimate Ninja Storm Connections**.

## 🌐 Link Akses Langsung (GitHub Pages)

* **🎬 Link Overlay untuk TikTok Live Studio:**  
  `https://hervandisipada.github.io/naruto-koth/overlay.html`
* **📱 Link Panel Admin (Kontrol dari HP / PC):**  
  `https://hervandisipada.github.io/naruto-koth/`

---

## ⚡ Fitur Utama
1. **Real-time Cloud Sync:** Perubahan status di HP langsung otomatis memperbarui overlay di TikTok Live Studio secara instan melalui Cloud WebSocket.
2. **Aturan King of the Hill (KotH):**
   - Pemain pemenang (King) yang mempertahankan Win Streak (WS $\ge$ 1) mendapat status **Free Match** (tiket tidak terpotong lagi di match ke-2 dst).
   - Penantang kalah tiketnya berkurang dan dapat kembali ke antrean, disimpan, atau keluar jika tiket habis.
3. **Kategori Antrean:**
   - **VIP:** Prioritas teratas (untuk donasi/gift mawar TikTok).
   - **FREE:** Antrean biasa (FIFO).
   - **SAVE:** Simpan pemain yang belum siap bertanding.
4. **Offline Local Server:** Tersedia file `start.bat` dan `server.py` jika ingin menjalankan server secara lokal tanpa koneksi internet luar.

---

## 🛠️ Cara Pakai di TikTok Live Studio
1. Buka TikTok Live Studio.
2. Klik **Tambah Sumber (Add Source)** $\rightarrow$ **Tautan / Web (Browser Source)**.
3. Masukkan URL: `https://hervandisipada.github.io/naruto-koth/overlay.html`
4. Atur Resolusi:
   - **Lebar (Width):** `480`
   - **Tinggi (Height):** `480`
5. Buka link Admin di HP (`https://hervandisipada.github.io/naruto-koth/`) untuk mengontrol jalannya pertandingan sambil live stream.
