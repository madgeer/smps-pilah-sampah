<?php
// export_csv.php
session_start();
require_once 'config/database.php';

// 1. Verify Authentication & Role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo "Akses ditolak.";
    exit;
}

try {
    // 2. Fetch all transaction data with detailed units and standard conversions
    $stmt = $pdo->query("
        SELECT 
            l.waktu, 
            m.nama AS nama_mahasiswa, 
            kl.nama_kelas,
            k.nama_kelompok, 
            n.nama_sampah, 
            cat.kategori_sampah, 
            d.jumlah, 
            s.nama_satuan,
            (d.jumlah * s.konversi) AS kuantitas_standar_gr_ml
        FROM laporan_sampah l
        JOIN email e ON l.id_email = e.id_email
        JOIN mahasiswa m ON e.id_mahasiswa = m.id_mahasiswa
        JOIN kelompok k ON m.id_kelompok = k.id_kelompok
        JOIN kelas kl ON k.id_kelas = kl.id_kelas
        JOIN laporan_sampah_detail d ON l.id_laporan = d.id_laporan
        JOIN nama_sampah n ON d.id_sampah = n.id_sampah
        JOIN kategori cat ON n.id_kategori = cat.id_kategori
        JOIN satuan s ON d.id_satuan = s.id_satuan
        ORDER BY l.waktu DESC, l.id_laporan DESC
    ");
    $data = $stmt->fetchAll();

    // 3. Set CSV headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=log_pemilahan_sampah_' . date('Ymd_His') . '.csv');

    // 4. Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility with Indonesian/special characters
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write column headers
    fputcsv($output, [
        'Tanggal', 
        'Nama Mahasiswa', 
        'Kelas', 
        'Kelompok', 
        'Jenis Sampah', 
        'Kategori', 
        'Jumlah Input', 
        'Satuan', 
        'Kuantitas Standar (Gram/ml)'
    ]);

    // Write rows
    foreach ($data as $row) {
        fputcsv($output, [
            $row['waktu'],
            $row['nama_mahasiswa'],
            $row['nama_kelas'],
            $row['nama_kelompok'],
            $row['nama_sampah'],
            $row['kategori_sampah'],
            number_format($row['jumlah'], 2, ',', ''), // format comma decimal for Indo Excel
            $row['nama_satuan'],
            number_format($row['kuantitas_standar_gr_ml'], 2, ',', '')
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Gagal mengekspor data: " . $e->getMessage();
    exit;
}
