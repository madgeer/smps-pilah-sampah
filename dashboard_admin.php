<?php
// dashboard_admin.php
session_start();
require_once 'config/database.php';

// 1. Verify Authentication & Role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
$success_message = '';
$error_message = '';

// 2. Handle Actions (Delete & Reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_report') {
        $id_laporan = intval($_POST['id_laporan'] ?? 0);
        if ($id_laporan > 0) {
            try {
                $stmt = $pdo->prepare("CALL hapus_laporan_sampah(:id_laporan)");
                $stmt->execute(['id_laporan' => $id_laporan]);
                $success_message = 'Laporan #' . $id_laporan . ' berhasil dihapus.';
            } catch (PDOException $e) {
                $error_message = 'Gagal menghapus laporan: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'reset_reports') {
        try {
            $pdo->query("CALL reset_laporan_sampah()");
            $success_message = 'Seluruh data transaksi pelaporan sampah berhasil dikosongkan.';
        } catch (PDOException $e) {
            $error_message = 'Gagal melakukan reset data: ' . $e->getMessage();
        }
    }
}

// 3. Fetch Classes & Groups list for Dropdown Filters
$classes_list = [];
$groups_list = [];
try {
    // Classes
    $classes_list = $pdo->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
    
    // Groups with Class context (ordered by Class then Group name)
    $stmt = $pdo->query("
        SELECT k.id_kelompok, k.nama_kelompok, kl.nama_kelas 
        FROM kelompok k
        JOIN kelas kl ON k.id_kelas = kl.id_kelas
        ORDER BY kl.nama_kelas ASC, k.nama_kelompok ASC
    ");
    $raw_groups = $stmt->fetchAll();
    
    // Group them by Class Name for <optgroup> formatting
    foreach ($raw_groups as $row) {
        $groups_list[$row['nama_kelas']][] = [
            'id_kelompok' => $row['id_kelompok'],
            'nama_kelompok' => $row['nama_kelompok']
        ];
    }
} catch (PDOException $e) {
    // Fail silently
}

// 4. Fetch Overview Statistics
$totals = ['mahasiswa' => 0, 'laporan' => 0];
$weight_stats = ['Organik' => 0.0, 'Anorganik' => 0.0, 'B3' => 0.0];

try {
    // Total Mahasiswa
    $totals['mahasiswa'] = $pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
    
    // Total Laporan
    $totals['laporan'] = $pdo->query("SELECT COUNT(*) FROM laporan_sampah")->fetchColumn();
    
    // Converted Weights by Category (Grams)
    $stmt = $pdo->query("
        SELECT k.kategori_sampah, SUM(d.jumlah * s.konversi) AS total_gr
        FROM laporan_sampah_detail d
        JOIN nama_sampah n ON d.id_sampah = n.id_sampah
        JOIN kategori k ON n.id_kategori = k.id_kategori
        JOIN satuan s ON d.id_satuan = s.id_satuan
        JOIN satuan_standar st ON s.id_satuan_standar = st.id_satuan_standar
        WHERE st.dimensi = 'Massa'
        GROUP BY k.kategori_sampah
    ");
    $weights_raw = $stmt->fetchAll();
    foreach ($weights_raw as $row) {
        $weight_stats[$row['kategori_sampah']] = floatval($row['total_gr']);
    }
} catch (PDOException $e) {
    $error_message = 'Gagal memuat statistik ringkasan: ' . $e->getMessage();
}

// 5. Fetch Group (Kelompok) Rankings by Sorted Waste Weight (Grams)
$rankings = [];
try {
    $stmt = $pdo->query("
        SELECT k.nama_kelompok, kl.nama_kelas,
               SUM(d.jumlah * s.konversi) AS total_berat_gr
        FROM laporan_sampah_detail d
        JOIN laporan_sampah l ON d.id_laporan = l.id_laporan
        JOIN email e ON l.id_email = e.id_email
        JOIN mahasiswa m ON e.id_mahasiswa = m.id_mahasiswa
        JOIN kelompok k ON m.id_kelompok = k.id_kelompok
        JOIN kelas kl ON k.id_kelas = kl.id_kelas
        JOIN satuan s ON d.id_satuan = s.id_satuan
        JOIN satuan_standar st ON s.id_satuan_standar = st.id_satuan_standar
        WHERE st.dimensi = 'Massa'
        GROUP BY k.id_kelompok
        ORDER BY total_berat_gr DESC
    ");
    $rankings = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fail silently or handle
}

// 6. Fetch Global Logs of all reports with dynamic Search, Filtering & Pagination
$logs = [];
$total_rows = 0;
$total_pages = 0;
$limit = 10; // Number of items per page
$page = intval($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $where_clauses = [];
    $params = [];

    // Search input
    $search = trim($_GET['name_search'] ?? '');
    $exact_match = isset($_GET['exact_match']); // Exact match toggle
    
    if (!empty($search)) {
        if ($exact_match) {
            $where_clauses[] = "m.nama = :search";
            $params['search'] = $search;
        } else {
            $where_clauses[] = "m.nama LIKE :search";
            $params['search'] = '%' . $search . '%';
        }
    }

    // Kelas Filter
    $kelas_filter = intval($_GET['kelas_filter'] ?? 0);
    if ($kelas_filter > 0) {
        $where_clauses[] = "kl.id_kelas = :kelas_filter";
        $params['kelas_filter'] = $kelas_filter;
    }

    // Kelompok Filter
    $kelompok_filter = intval($_GET['kelompok_filter'] ?? 0);
    if ($kelompok_filter > 0) {
        $where_clauses[] = "k.id_kelompok = :kelompok_filter";
        $params['kelompok_filter'] = $kelompok_filter;
    }

    $where_str = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // 6a. Get total rows for pagination based on filter parameters
    $stmt_count = $pdo->prepare("
        SELECT COUNT(DISTINCT l.id_laporan)
        FROM laporan_sampah l
        JOIN email e ON l.id_email = e.id_email
        JOIN mahasiswa m ON e.id_mahasiswa = m.id_mahasiswa
        JOIN kelompok k ON m.id_kelompok = k.id_kelompok
        JOIN kelas kl ON k.id_kelas = kl.id_kelas
        $where_str
    ");
    $stmt_count->execute($params);
    $total_rows = $stmt_count->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 6b. Get paginated results
    $stmt = $pdo->prepare("
        SELECT l.id_laporan, l.waktu, l.keterangan, m.nama AS nama_mahasiswa, k.nama_kelompok, kl.nama_kelas,
               GROUP_CONCAT(CONCAT(n.nama_sampah, ' (', d.jumlah, ' ', s.nama_satuan, ')') SEPARATOR ', ') AS rincian
        FROM laporan_sampah l
        JOIN email e ON l.id_email = e.id_email
        JOIN mahasiswa m ON e.id_mahasiswa = m.id_mahasiswa
        JOIN kelompok k ON m.id_kelompok = k.id_kelompok
        JOIN kelas kl ON k.id_kelas = kl.id_kelas
        LEFT JOIN laporan_sampah_detail d ON l.id_laporan = d.id_laporan
        LEFT JOIN nama_sampah n ON d.id_sampah = n.id_sampah
        LEFT JOIN satuan s ON d.id_satuan = s.id_satuan
        $where_str
        GROUP BY l.id_laporan
        ORDER BY l.waktu DESC, l.id_laporan DESC
        LIMIT :limit OFFSET :offset
    ");
    
    // Bind all parameters properly
    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Gagal memuat log aktivitas: ' . $e->getMessage();
}

// Helper function to build page URLs while keeping query string params
function getPageUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SMPSM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .pagination {
            display: flex;
            align-items: center;
            margin-top: 16px;
            font-size: 0.85rem;
            gap: 4px;
        }
        .pagination a, .pagination span {
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: var(--primary);
            background: #ffffff;
            border-radius: 2px;
        }
        .pagination a:hover {
            background-color: #f8f9fa;
            text-decoration: underline;
        }
        .pagination span.current {
            background-color: var(--bg-header);
            color: var(--text-main);
            font-weight: bold;
        }
        .pagination span.disabled {
            color: var(--text-muted);
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>SMPSM (Administrator)</h2>
        <div class="nav-links">
            <span class="nav-user-info">Masuk sebagai: <strong><?php echo htmlspecialchars($user['email']); ?></strong> (Admin)</span>
            <a href="logout.php">Keluar</a>
        </div>
    </div>

    <div class="container">
        <div class="brand-header">
            <h1>Panel Kontrol Administrator</h1>
            <p>Pemantauan aktivitas, statistik kelompok, dan manajemen database pemilahan sampah</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <strong>Sukses:</strong> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <strong>Galat:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Top Actions -->
        <div style="margin-bottom: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <a href="export_csv.php" class="btn btn-primary btn-small" style="background: #3366cc; border-color: #3366cc; color: #fff;">Ekspor Data ke CSV</a>
            <form action="dashboard_admin.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus seluruh data laporan transaksi? Tindakan ini tidak dapat dibatalkan.');">
                <input type="hidden" name="action" value="reset_reports">
                <button type="submit" class="btn btn-danger btn-small">Kosongkan Seluruh Laporan</button>
            </form>
        </div>

        <div class="dashboard-grid">
            <!-- Left Side: Global Logs -->
            <div>
                <div style="border: 1px solid var(--border-color); padding: 24px; background: #fff; margin-bottom: 30px;">
                    <h2 class="section-title">Log Seluruh Pelaporan</h2>
                    
                    <!-- Search & Filter Form (Wikipedia Minimalist) -->
                    <form action="dashboard_admin.php" method="GET" style="display: flex; gap: 12px; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; background: #f8f9fa; border: 1px solid var(--border-color); padding: 16px;">
                        <div class="form-group" style="margin-bottom: 0; flex-grow: 2; min-width: 200px;">
                            <label for="name_search" style="font-size: 0.75rem;">Cari Nama Mahasiswa</label>
                            <input type="text" id="name_search" name="name_search" class="form-control" placeholder="Nama mahasiswa..." value="<?php echo htmlspecialchars($search); ?>">
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem; margin-top: 6px; cursor: pointer; text-transform: none; font-weight: normal; color: var(--text-muted);">
                                <input type="checkbox" name="exact_match" value="1" <?php echo $exact_match ? 'checked' : ''; ?>>
                                Akurat (Exact Match)
                            </label>
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex-grow: 1; min-width: 150px;">
                            <label for="kelas_filter" style="font-size: 0.75rem;">Filter Kelas</label>
                            <select id="kelas_filter" name="kelas_filter" class="form-control">
                                <option value="0">-- Semua Kelas --</option>
                                <?php foreach ($classes_list as $cl): ?>
                                    <option value="<?php echo $cl['id_kelas']; ?>" <?php echo $kelas_filter === intval($cl['id_kelas']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cl['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex-grow: 1; min-width: 150px;">
                            <label for="kelompok_filter" style="font-size: 0.75rem;">Filter Kelompok</label>
                            <select id="kelompok_filter" name="kelompok_filter" class="form-control">
                                <option value="0">-- Semua Kelompok --</option>
                                <?php foreach ($groups_list as $kelas_name => $grp_items): ?>
                                    <optgroup label="Kelas <?php echo htmlspecialchars($kelas_name); ?>">
                                        <?php foreach ($grp_items as $gr): ?>
                                            <option value="<?php echo $gr['id_kelompok']; ?>" <?php echo $kelompok_filter === intval($gr['id_kelompok']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($gr['nama_kelompok']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">Saring</button>
                            <?php if (!empty($search) || $kelas_filter > 0 || $kelompok_filter > 0): ?>
                                <a href="dashboard_admin.php" class="btn" style="padding: 8px 16px;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if (empty($logs)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem; font-style: italic;">Tidak ada laporan pemilahan sampah yang sesuai dengan penyaringan.</p>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="wikitable">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Tanggal</th>
                                        <th>Mahasiswa</th>
                                        <th>Rincian Sampah</th>
                                        <th>Keterangan</th>
                                        <th style="width: 80px; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $row): ?>
                                        <tr>
                                            <td><strong><?php echo date('d-m-Y', strtotime($row['waktu'])); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['nama_mahasiswa']); ?></strong>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                    <?php echo htmlspecialchars($row['nama_kelas']); ?> — <?php echo htmlspecialchars($row['nama_kelompok']); ?>
                                                </div>
                                            </td>
                                            <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($row['rincian'] ?? '-'); ?></td>
                                            <td style="color: var(--text-muted); font-size: 0.8rem;"><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                                            <td style="text-align: center;">
                                                <form action="dashboard_admin.php" method="POST" style="display:inline;" onsubmit="return confirm('Hapus laporan #<?php echo $row['id_laporan']; ?>?');">
                                                    <input type="hidden" name="action" value="delete_report">
                                                    <input type="hidden" name="id_laporan" value="<?php echo $row['id_laporan']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small" style="padding: 2px 6px; font-size: 0.75rem;">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <span>Halaman:</span>
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo getPageUrl($page - 1); ?>">&laquo; Sebelum</a>
                                <?php else: ?>
                                    <span class="disabled">&laquo; Sebelum</span>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i === $page): ?>
                                        <span class="current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo getPageUrl($i); ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo getPageUrl($page + 1); ?>">Berikut &raquo;</a>
                                <?php else: ?>
                                    <span class="disabled">Berikut &raquo;</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Statistics & Rankings -->
            <div>
                <!-- Infobox stats -->
                <div class="infobox-style">
                    <div class="infobox-title">Ringkasan Sistem</div>
                    
                    <div class="infobox-row">
                        <span class="infobox-label">Total Mahasiswa</span>
                        <span class="infobox-value"><strong><?php echo number_format($totals['mahasiswa'], 0, ',', '.'); ?></strong></span>
                    </div>
                    <div class="infobox-row">
                        <span class="infobox-label">Total Laporan</span>
                        <span class="infobox-value"><strong><?php echo number_format($totals['laporan'], 0, ',', '.'); ?></strong></span>
                    </div>
                    
                    <div style="margin-top: 16px; font-family: Georgia, serif; font-size: 0.95rem; font-weight: bold; border-bottom: 1px solid var(--border-color); padding-bottom: 4px; margin-bottom: 8px;">
                        Berat Sampah Terkumpul:
                    </div>
                    
                    <div class="infobox-row">
                        <span class="infobox-label">Organik</span>
                        <span class="infobox-value"><strong><?php echo number_format($weight_stats['Organik'] / 1000, 2, ',', '.'); ?></strong> Kg</span>
                    </div>
                    <div class="infobox-row">
                        <span class="infobox-label">Anorganik</span>
                        <span class="infobox-value"><strong><?php echo number_format($weight_stats['Anorganik'] / 1000, 2, ',', '.'); ?></strong> Kg</span>
                    </div>
                    <div class="infobox-row">
                        <span class="infobox-label">B3</span>
                        <span class="infobox-value"><strong><?php echo number_format($weight_stats['B3'] / 1000, 2, ',', '.'); ?></strong> Kg</span>
                    </div>
                </div>

                <!-- Infobox Kelompok Rankings -->
                <div class="infobox-style">
                    <div class="infobox-title">Peringkat Kelompok</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-bottom: 12px; font-style: italic;">
                        Berdasarkan berat sampah terpilah (Massa)
                    </div>
                    
                    <?php if (empty($rankings)): ?>
                        <p style="font-size: 0.8rem; text-align: center; color: var(--text-muted); font-style: italic;">Belum ada kontribusi data berat.</p>
                    <?php else: ?>
                        <?php 
                        $rank = 1;
                        foreach ($rankings as $row): 
                        ?>
                            <div class="infobox-row" style="align-items: center;">
                                <div>
                                    <span style="font-weight: bold; color: var(--primary);"><?php echo $rank++; ?>.</span> 
                                    <strong><?php echo htmlspecialchars($row['nama_kelompok']); ?></strong>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['nama_kelas']); ?></div>
                                </div>
                                <span class="infobox-value" style="font-weight: bold;">
                                    <?php echo number_format($row['total_berat_gr'] / 1000, 2, ',', '.'); ?> Kg
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer>
        SMPSM Administrator Panel &copy;
    </footer>
</body>
</html>
