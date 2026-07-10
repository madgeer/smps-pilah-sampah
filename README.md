# SMPSM - Sistem Manajemen Pemilahan Sampah Mahasiswa

SMPSM adalah aplikasi web pemantauan dan pelaporan pemilahan sampah mandiri untuk mahasiswa. Aplikasi ini dikembangkan menggunakan **PHP Native (PDO)** dan **MySQL** dengan arsitektur database ternormalisasi penuh (**Third Normal Form / 3NF**), serta dibalut dengan antarmuka pengguna minimalis yang bersih terinspirasi oleh **estetika Wikipedia**.

---

## Fitur Utama

### Autentikasi & Multi-role
*   **Halaman Registrasi Mandiri**: Mendukung *dual-flow registration* (aktivasi instan untuk email mahasiswa bawaan, dan pembuatan profil baru untuk mahasiswa baru).
*   **Multi-role Session**: Pemisahan hak akses otomatis antara **Mahasiswa (User)** dan **Dosen/Koordinator (Administrator)**.

### Dashboard Mahasiswa
*   **Formulir Pelaporan Multisampah Dinamis**: Mahasiswa dapat melaporkan beberapa jenis sampah sekaligus dalam satu kali pengiriman secara dinamis (menggunakan JavaScript & Database Transaction).
*   **Ringkasan Pemilahan**: *Infobox* ringkasan yang menampilkan akumulasi berat sampah terpilah (Organik, Anorganik, B3) yang otomatis terkonversi secara presisi ke satuan standar **Gram (Gr)**.
*   **Tabel Riwayat Laporan**: Riwayat pengiriman laporan lengkap dengan paginasi halaman yang cepat.

### Dashboard Administrator
*   **Log Pelaporan Global**: Pemantauan seluruh log aktivitas pengiriman sampah mahasiswa.
*   **Pencarian & Penyaringan Lanjut**:
    *   Saring log berdasarkan **Kelas** dan **Kelompok** (dropdown dinamis dikelompokkan per kelas).
    *   Cari berdasarkan nama mahasiswa dengan opsi **Akurat (Exact Match)** untuk hasil pencarian yang presisi.
*   **Papan Peringkat Kelompok**: Peringkat kelompok mahasiswa secara *real-time* berdasarkan kontribusi berat sampah terpilah terkumpul (Massa).
*   **Ekspor Data ke CSV**: Unduh seluruh log pelaporan ke format CSV yang kompatibel penuh dengan **Microsoft Excel** (pemisah desimal koma).
*   **Kontrol Database**: Aksi penghapusan data laporan tertentu atau pengosongan total database laporan menggunakan *Stored Procedures* bawaan.

### Fitur Tambahan
*   **Paginasi (Windowing & Ellipsis)**: Navigasi tabel log bergaya situs berita (`1 ... 4 5 6 ... 50`) yang menjaga kerapihan layout dan performa server.
*   **Profil Saya**: Halaman ringkasan profil pribadi dan fitur perubahan kata sandi mandiri yang aman.
*   **Struktur Kode Modular (Refactored)**: Pemisahan logika backend, manajemen sesi, dan layout reusable (`header.php` / `footer.php`) demi kemudahan pemeliharaan (*maintenance*).

---

## Spesifikasi Teknologi
*   **Bahasa Pemrograman**: PHP Native (Minimal PHP 8.0)
*   **Database**: MySQL / MariaDB (Terstruktur 3NF dengan Stored Procedures dan Constraints)
*   **Styling (CSS)**: Vanilla CSS (Wikipedia-inspired Minimalist, responsive grid layout)
*   **Interaktivitas (JS)**: Vanilla JavaScript

---

## Struktur Database (3NF)
Aplikasi ini berjalan di atas skema database yang bersih dari redundansi (*orphan/orphaned records* telah dihapus).
*   **Entitas Utama**: `mahasiswa`, `kelas`, `kelompok`, `email`, `login`
*   **Entitas Transaksi**: `laporan_sampah`, `laporan_sampah_detail`
*   **Entitas Master**: `nama_sampah`, `kategori`, `satuan`, `satuan_standar`

*Penjelasan lengkap dan diagram hubungan antar-tabel dapat dibaca di berkas [docs/erd.md](docs/erd.md).*

---

## Panduan Instalasi Lokal (Menggunakan Laragon)

### Langkah 1: Persiapan Folder Proyek
Secara default Laragon melayani berkas di dalam `C:\laragon\www\`. Jika folder proyek Anda berada di tempat lain (misal: `D:\Project\web\pilah-sampah`), hubungkan folder tersebut menggunakan Junction Link agar terdeteksi oleh Laragon.
1.  Buka terminal/PowerShell sebagai Administrator.
2.  Jalankan perintah berikut:
    ```powershell
    New-Item -ItemType Junction -Path "C:\laragon\www\pilah-sampah" -Value "D:\Project\web\pilah-sampah"
    ```

### Langkah 2: Impor Database
1.  Buka aplikasi **Laragon** dan klik **Start All**.
2.  Klik tombol **Database** untuk membuka **HeidiSQL**.
3.  Buat database baru bernama **`smps`**.
4.  Pilih database `smps`, muat (*load*) berkas database **`smps.sql`**, lalu tekan **F9** untuk mengeksekusi impor tabel, data bawaan, dan stored procedures.

### Langkah 3: Konfigurasi Database
Buka berkas `config/database.php` dan sesuaikan kredensial server MySQL lokal Anda:
```php
$host = '127.0.0.1';
$db   = 'smps';
$user = 'root';
$pass = ''; // Default Laragon adalah kosong
```

### Langkah 4: Akses Web Aplikasi
Buka browser pilihan Anda lalu ketik alamat virtual host otomatis dari Laragon:
```url
http://pilah-sampah.test
```

---

## Akun Demo Pengujian

Anda dapat menggunakan akun uji coba bawaan berikut untuk menguji sistem:

| Peran (Role) | Alamat Email | Kata Sandi (Password) |
| :--- | :--- | :--- |
| **Administrator (Admin)** | `admin@example.com` | `admin123` |
| **Mahasiswa (User)** | `user@example.com` | `user123` |
| **Mahasiswa 2 (User)** | `mahasiswa2_email4@example.com` | `user123` |
