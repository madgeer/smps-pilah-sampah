# Entity Relationship Diagram (ERD) 

Dokumen ini berisi Entity Relationship Diagram (ERD) untuk database `smps`

## ER Diagram (Diperbaiki)

```mermaid
erDiagram
    kelas {
        int id_kelas PK
        varchar nama_kelas
    }
    kelompok {
        int id_kelompok PK
        varchar nama_kelompok
        int id_kelas FK
    }
    mahasiswa {
        int id_mahasiswa PK
        varchar nama
        int id_kelompok FK
    }
    email {
        int id_email PK
        varchar nama_email
        int id_mahasiswa FK
    }
    login {
        int id_login PK
        int id_email FK
        varchar password
        varchar role
    }
    kategori {
        int id_kategori PK
        varchar kategori_sampah
    }
    nama_sampah {
        int id_sampah PK
        int id_kategori FK
        varchar nama_sampah
    }
    satuan_standar {
        int id_satuan_standar PK
        varchar nama_satuan_standar
        varchar dimensi
    }
    satuan {
        int id_satuan PK
        varchar nama_satuan
        int id_satuan_standar FK
        decimal konversi
    }
    laporan_sampah {
        int id_laporan PK
        int id_email FK
        text keterangan
        date waktu
    }
    laporan_sampah_detail {
        int id_detail PK
        int id_laporan FK
        int id_sampah FK
        decimal jumlah
        int id_satuan FK
    }

    kelas ||--o{ kelompok : "memiliki"
    kelompok ||--o{ mahasiswa : "berisi"
    mahasiswa ||--o{ email : "memiliki"
    email ||--o| login : "memiliki kredensial"
    email ||--o{ laporan_sampah : "melaporkan"
    satuan_standar ||--o{ satuan : "memiliki"
    satuan ||--o{ laporan_sampah_detail : "mengukur"
    kategori ||--o{ nama_sampah : "memiliki"
    laporan_sampah ||--o{ laporan_sampah_detail : "memiliki"
    nama_sampah ||--o{ laporan_sampah_detail : "terdapat pada"
```

## Penjelasan Hubungan Entitas (ERD)

Diagram ERD di atas membagi sistem informasi pemilahan sampah ke dalam tiga alur fungsional utama:

### 1. Alur Organisasi & Pengguna
*   **`kelas` ke `kelompok` (One-to-Many)**: Setiap kelas akademik dapat memiliki banyak kelompok belajar mahasiswa, tetapi setiap kelompok hanya bernaung di bawah satu kelas tertentu.
*   **`kelompok` ke `mahasiswa` (One-to-Many)**: Kelompok belajar menampung banyak mahasiswa, sedangkan tiap mahasiswa hanya boleh menjadi anggota dari satu kelompok.
*   **`mahasiswa` ke `email` (One-to-Many)**: Satu profil mahasiswa dapat mendaftarkan lebih dari satu alamat email (misal: email kampus dan email pribadi) untuk keperluan integrasi atau kontak.
*   **`email` ke `login` (One-to-One / Zero-to-One)**: Setiap alamat email unik yang terdaftar dapat dikaitkan dengan tepat satu akun kredensial password untuk melakukan login ke dalam sistem.

### 2. Alur Transaksi & Pelaporan Sampah
*   **`email` ke `laporan_sampah` (One-to-Many)**: Mahasiswa mengirimkan laporan pemilahan sampah harian/mingguan di bawah identifikasi alamat email mereka. Satu email dapat mengirimkan banyak laporan secara berkala.
*   **`laporan_sampah` ke `laporan_sampah_detail` (One-to-Many)**: Laporan dipecah menjadi dua bagian guna mendukung **input multisampah**. Tabel induk (`laporan_sampah`) menyimpan info umum (keterangan dan waktu), sementara tabel anak (`laporan_sampah_detail`) menyimpan baris-baris rincian jenis sampah yang dilaporkan secara spesifik.

### 3. Alur Master Data & Pengukuran Satuan
*   **`kategori` ke `nama_sampah` (One-to-Many)**: Setiap jenis sampah dikategorikan ke dalam satu klasifikasi besar (seperti Organik, Anorganik, atau B3).
*   **`satuan_standar` ke `satuan` (One-to-Many)**: Mengelompokkan berbagai satuan pengukuran fisik berdasarkan dimensinya (Massa, Kuantitas, Volume) dan mengaitkannya dengan nilai konversi terhadap satuan dasar (Gram, Pcs, ml).
*   **Relasi ke `laporan_sampah_detail`**:
    *   Setiap baris detail laporan sampah mencatat jenis sampah tertentu (`nama_sampah`) dan satuan tertentu (`satuan`) yang dikalikan dengan jumlah desimal (`jumlah`) untuk menghasilkan data pemilahan yang terstandardisasi.

---

