# Naruto Storm Connections - King of the Hill (KotH) Live Overlay

Sistem Antrean & Overlay Interaktif untuk Live Streaming TikTok / YouTube / Twitch game **Naruto x Boruto Ultimate Ninja Storm Connections**.

---

## 🚀 Cara Menjalankan Server Lokal (Disarankan Saat Live)

1. Cukup klik ganda (double-click) file **`start.bat`**.
2. Jendela server akan mendeteksi IP lokal komputer secara otomatis.
3. Gunakan URL berikut:
   - **🎬 Link Overlay untuk TikTok Live Studio (Browser Source):**  
     `http://localhost:8000/overlay.html`
   - **💻 Link Panel Admin (Buka di PC):**  
     `http://localhost:8000`
   - **📱 Link Panel Admin (Buka di Browser HP Anda):**  
     `http://192.168.1.11:8000` *(atau IP yang tertera di jendela start.bat, pastikan HP dan PC berada di Wi-Fi yang sama)*

---

## ⚡ Fitur Utama & Aturan Pertandingan

1. **Aturan King of the Hill (KotH) & Tukar Posisi Bergantian (Alternating Slot)**:
   - **P1 $\leftrightarrow$ P2**: Selama pemain mempertahankan Win Streak (WS), posisinya akan berganti antara P1 dan P2 setiap kemenangan.
   - P1 Menang $\rightarrow$ Pindah ke P2 pada match berikutnya, slot P1 kosong untuk penantang baru.
   - P2 Menang $\rightarrow$ Pindah ke P1 pada match berikutnya, slot P2 kosong untuk penantang baru.
   - Slot penantang dibiarkan kosong (`null`) agar admin dapat memilih pemain yang aktif standby di live room.

2. **Bonus Win Streak (WS 5x = +1 Tiket)**:
   - Setiap kali pemain mencapai kelipatan 5x Win Streak (5x, 10x, 15x, dst), sistem otomatis memberikan **+1 Tiket Pertandingan** ke sisa tiket pemain.
   - Pada Live Overlay akan muncul badge animasi **`🎁 +1 TIKET`**.
   - Muncul notifikasi popup perayaan di Panel Admin.

3. **Status Free Match & Fire Effect**:
   - Pemain pemenang (King) yang memiliki WS $\ge 1$ bermain secara **FREE MATCH** (tiket tidak dipotong lagi di match ke-2 dan seterusnya selama mempertahankan tahta).
   - Saat mencapai WS $\ge 3$, efek api menyala **`🔥 ON FIRE`** pada kartu King.

4. **Kategori Antrean & Mode Tampilan Live**:
   - **VIP**: Prioritas teratas (untuk donasi/gift mawar TikTok).
   - **FREE**: Antrean biasa (FIFO).
   - **SAVE**: Simpan data pemain yang sedang istirahat atau belum siap tanding.
   - **Toggle Tampilan Live**: Admin dapat memilih menampilkan **Antrean Aktif** atau **Daftar Save** langsung ke layar live stream penonton.

5. **Pencarian Nama / Cek Slot Pemain**:
   - Fitur pencarian instan untuk mengecek status pemain, posisi slot, karakter, dan sisa tiket saat penonton bertanya di live chat.

6. **Refund / Tombol Turunkan (`↩️ Turunkan`)**:
   - Jika penantang yang dinaikkan tiba-tiba leave live stream sebelum match dimulai, admin cukup mengklik **`↩️ Turunkan`** untuk mengembalikan 1 tiket dan memasukkannya kembali ke antrean.

7. **Auto-Scroll Antrean Portrait 9:16**:
   - Desain overlay portrait menyerupai layar HP dengan *smooth auto-scroll* jika jumlah antrean melebihi tinggi layar.

1. **Aturan King of the Hill (KotH) & Tukar Posisi Bergantian (Alternating Slot)**:
   - **P1 $\leftrightarrow$ P2**: Selama pemain mempertahankan Win Streak (WS), posisinya akan berganti antara P1 dan P2 setiap kemenangan.
   - P1 Menang $\rightarrow$ Pindah ke P2 pada match berikutnya, slot P1 kosong untuk penantang baru.
   - P2 Menang $\rightarrow$ Pindah ke P1 pada match berikutnya, slot P2 kosong untuk penantang baru.
   - Slot penantang dibiarkan kosong (`null`) agar admin dapat memilih pemain yang aktif standby di live room.

2. **Bonus Win Streak (WS 5x = +1 Tiket)**:
   - Setiap kali pemain mencapai kelipatan 5x Win Streak (5x, 10x, 15x, dst), sistem otomatis memberikan **+1 Tiket Pertandingan** ke sisa tiket pemain.
   - Pada Live Overlay akan muncul badge animasi **`🎁 +1 TIKET`**.
   - Muncul notifikasi popup perayaan di Panel Admin.

3. **Status Free Match & Fire Effect**:
   - Pemain pemenang (King) yang memiliki WS $\ge 1$ bermain secara **FREE MATCH** (tiket tidak dipotong lagi di match ke-2 dan seterusnya selama mempertahankan tahta).
   - Saat mencapai WS $\ge 3$, efek api menyala **`🔥 ON FIRE`** pada kartu King.

4. **Kategori Antrean & Mode Tampilan Live**:
   - **VIP**: Prioritas teratas (untuk donasi/gift mawar TikTok).
   - **FREE**: Antrean biasa (FIFO).
   - **SAVE**: Simpan data pemain yang sedang istirahat atau belum siap tanding.
   - **Toggle Tampilan Live**: Admin dapat memilih menampilkan **Antrean Aktif** atau **Daftar Save** langsung ke layar live stream penonton.

5. **Pencarian Nama / Cek Slot Pemain**:
   - Fitur pencarian instan untuk mengecek status pemain, posisi slot, karakter, dan sisa tiket saat penonton bertanya di live chat.

6. **Refund / Tombol Turunkan (`↩️ Turunkan`)**:
   - Jika penantang yang dinaikkan tiba-tiba leave live stream sebelum match dimulai, admin cukup mengklik **`↩️ Turunkan`** untuk mengembalikan 1 tiket dan memasukkannya kembali ke antrean.

7. **Auto-Scroll Antrean Portrait 9:16**:
   - Desain overlay portrait menyerupai layar HP dengan *smooth auto-scroll* jika jumlah antrean melebihi tinggi layar.

---

## 🛠️ Pengaturan di TikTok Live Studio

1. Buka TikTok Live Studio.
2. Klik **Tambah Sumber (Add Source)** $\rightarrow$ **Tautan / Web (Browser Source)**.
3. Masukkan URL: `http://localhost:8000/overlay.html`
4. Atur Resolusi Portrait:
   - **Lebar (Width):** `400`
   - **Tinggi (Height):** `740` (atau `720` - `780`)
5. Simpan dan letakkan overlay di pojok kanan bawah atau area kosong live stream Anda.
