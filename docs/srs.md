# Software Requirements Specification (SRS) - SMPSM (Sistem Manajemen Pemilahan Sampah Mahasiswa)

Dokumen ini berisi spesifikasi kebutuhan perangkat lunak untuk aplikasi **SMPS (Sistem Manajemen Pemilahan Sampah Mahasiswa)** yang dikembangkan di lingkungan Universitas berdasarkan analisis struktur database dan fungsionalitas sistem.

---

## 1. Pendahuluan

### 1.1 Tujuan
Tujuan dari dokumen SRS ini adalah untuk mendokumentasikan kebutuhan fungsional dan non-fungsional dari aplikasi **SMPSM**. Dokumen ini akan menjadi acuan utama bagi pengembang perangkat lunak, administrator sistem, dan pemangku kepentingan untuk memahami alur kerja dan batasan sistem.

### 1.2 Lingkup Masalah
Aplikasi **SMPSM** adalah platform berbasis web yang digunakan untuk melacak, mengelola, dan mengumpulkan data pemilahan sampah yang dilakukan oleh mahasiswa dalam lingkup kelas dan kelompok belajar. Aplikasi ini mengotomatiskan pencatatan kuantitas sampah (berdasarkan kategori organik, anorganik, dan B3) serta melakukan standardisasi pengukuran satuan massa, kuantitas, dan volume.

---

## 2. Deskripsi Umum

### 2.1 Perspektif Produk
SMPSM merupakan sistem berbasis database relasional yang berinteraksi langsung dengan mahasiswa (pengguna) untuk melaporkan pemilahan sampah harian, serta administrator (dosen/koordinator) untuk memantau partisipasi mahasiswa dan statistik pemilahan sampah.

### 2.2 Fitur Utama Produk
1.  **Autentikasi & Kredensial**: Login pengguna berdasarkan role (`admin` dan `user`) dengan pemetaan alamat email mahasiswa yang terdaftar.
2.  **Manajemen Akademik**: Pengelompokan mahasiswa ke dalam kelas (C1, C2) dan sub-kelompok kerja.
3.  **Sistem Pelaporan Sampah (Multisampah)**: Mahasiswa dapat melaporkan beberapa jenis sampah sekaligus dalam satu laporan dengan kuantitas dan satuan yang berbeda secara dinamis.
4.  **Standardisasi & Konversi Satuan**: Konversi otomatis dari berbagai satuan input (misal: Kg, Liter, Pcs, Gr, ml) ke satuan standar (Gram untuk Massa, ml untuk Volume, Pcs untuk Kuantitas) berdasarkan nilai konversi yang telah ditentukan.
5.  **Pemantauan & Administrasi**: Panel administrator untuk menghapus laporan tertentu, melihat log pemilahan, dan melakukan reset data transaksi jika diperlukan.

### 2.3 Karakteristik Pengguna
*   **Mahasiswa (User)**: Dapat mendaftarkan email, melakukan login, dan mengirimkan laporan pemilahan sampah harian/mingguan.
*   **Koordinator/Dosen (Admin)**: Memiliki akses penuh untuk melihat rekapitulasi data, mengelola kategori sampah, serta menghapus/mereset data laporan.

---

## 3. Kebutuhan Sistem

### 3.1 Kebutuhan Fungsional (Functional Requirements)

#### FR-01: Manajemen Akun & Autentikasi
*   **Deskripsi**: Sistem harus membatasi akses fitur berdasarkan peran akun yang terotentikasi.
*   **Alur Kerja**:
    1.  Pengguna memasukkan email dan password.
    2.  Sistem memverifikasi kredensial pada tabel `login` dan mencocokkannya dengan `email.id_email`.
    3.  Sistem mengarahkan pengguna ke halaman Dashboard sesuai perannya (`admin` atau `user`).

#### FR-02: Pendaftaran Email Mahasiswa
*   **Deskripsi**: Mahasiswa dapat mendaftarkan satu atau lebih alamat email (email pribadi dan email kampus) yang merujuk pada profil mahasiswa yang sama.
*   **Aturan Bisnis**: Satu mahasiswa dapat memiliki banyak email (*One-to-Many*), namun satu alamat email hanya dapat merujuk ke tepat satu mahasiswa.

#### FR-03: Pelaporan Pemilahan Sampah (Input Multisampah)
*   **Deskripsi**: Mahasiswa dapat mengirimkan laporan pemilahan sampah yang berisi satu atau lebih item sampah yang berbeda.
*   **Alur Kerja**:
    1.  Sistem menyediakan form pelaporan (Keterangan, Waktu/Tanggal).
    2.  Pengguna dapat menambahkan beberapa baris item sampah secara dinamis. Setiap item berisi:
        *   Jenis sampah (misal: Plastik, Kertas, Sisa Makanan).
        *   Kuantitas/Jumlah (misal: 1.5, 2.50, 500).
        *   Satuan pengukuran (misal: Kg, Gr, Pcs).
    3.  Sistem menyimpan data laporan utama di `laporan_sampah` dan memecah item detail ke dalam `laporan_sampah_detail` dengan referensi kuantitas masing-masing.

#### FR-04: Konversi Pengukuran Satuan Otomatis
*   **Deskripsi**: Sistem harus mampu menghitung nilai pengukuran nyata ke dalam dimensi standar agar data sampah dapat diakumulasikan secara adil.
*   **Aturan Bisnis**:
    *   Dimensi **Massa** dikonversikan ke **Gram (Gr)**.
    *   Dimensi **Volume** dikonversikan ke **Mililiter (ml)**.
    *   Dimensi **Kuantitas** dikonversikan ke **Pieces (Pcs)**.
    *   *Contoh*: Jika input laporan adalah `3 Kg` sampah plastik, sistem secara internal menyimpan data konversi `3 * 1000 = 3000 Gram`.

#### FR-05: Manajemen Data Laporan (Fitur Admin)
*   **Deskripsi**: Admin dapat melakukan pembersihan dan penghapusan data laporan jika terjadi kesalahan input atau saat siklus pengumpulan baru dimulai.
*   **Kebutuhan Teknis**:
    *   Penyediaan prosedur `hapus_laporan_sampah(id_lapor)` untuk menghapus data laporan beserta detail itemnya sekaligus untuk menjaga referensi integritas.
    *   Penyediaan prosedur `reset_laporan_sampah()` untuk mengosongkan seluruh tabel transaksi laporan sampah tanpa menghapus data master (mahasiswa, kategori sampah, dll.).

---

### 3.2 Kebutuhan Non-Fungsional (Non-Functional Requirements)

#### NFR-01: Integritas Data (Data Integrity)
*   **Deskripsi**: Sistem harus menjaga konsistensi data relasional.
*   **Kebutuhan Teknis**:
    *   Semua tabel transaksi harus memiliki constraint *Foreign Key* dengan aksi pencegahan (*Restrict*) atau penghapusan berjenjang (*Cascade*) yang aman.
    *   Tidak boleh ada baris detail yatim (*orphan rows*) di tabel `laporan_sampah_detail` yang merujuk pada ID laporan yang tidak valid.

#### NFR-02: Keamanan (Security)
*   **Deskripsi**: Sandi akun pengguna tidak boleh disimpan dalam bentuk teks biasa (*plain text*). Kredensial login harus terlindungi.

#### NFR-03: Ketersediaan & Skalabilitas (Scalability)
*   **Deskripsi**: Database harus mampu menampung ribuan entri data pemilahan harian yang dikirim oleh puluhan kelompok mahasiswa secara simultan tanpa mengalami penurunan performa query.

---

## 4. Desain Basis Data & Arsitektur

Arsitektur database relasional untuk mendukung seluruh kebutuhan sistem di atas telah dirancang menggunakan kaidah normalisasi **3NF**. 

Detail visualisasi diagram hubungan entitas (ERD) serta deskripsi lengkap mengenai tipe data kolom dan constraint foreign key dapat diakses pada dokumen pendukung berikut:
*   [docs/erd.md](file:///D:/Project/web/pilah-sampah/docs/erd.md)
