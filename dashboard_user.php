<?php
// dashboard_user.php
require_once 'includes/auth_helper.php';
check_auth('user'); // Ensure logged in as 'user' (mahasiswa)

require_once 'config/database.php';

$user = $_SESSION['user'];
$success_message = '';
$error_message = '';

// 1. Fetch dropdown choices (Satuan and Nama Sampah)
try {
    // Satuan
    $stmt = $pdo->query("SELECT id_satuan, nama_satuan FROM satuan ORDER BY id_satuan ASC");
    $satuan_list = $stmt->fetchAll();

    // Nama Sampah grouped by Kategori
    $stmt = $pdo->query("
        SELECT s.id_sampah, s.nama_sampah, k.kategori_sampah 
        FROM nama_sampah s 
        JOIN kategori k ON s.id_kategori = k.id_kategori 
        ORDER BY k.kategori_sampah, s.nama_sampah ASC
    ");
    $raw_sampah = $stmt->fetchAll();
    
    // Group them for display
    $sampah_list = [];
    foreach ($raw_sampah as $row) {
        $sampah_list[$row['kategori_sampah']][] = [
            'id_sampah' => $row['id_sampah'],
            'nama_sampah' => $row['nama_sampah']
        ];
    }
} catch (PDOException $e) {
    $error_message = 'Gagal memuat master data: ' . $e->getMessage();
}

// 2. Handle Report Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $keterangan = trim($_POST['keterangan'] ?? '');
    $waktu = trim($_POST['waktu'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($waktu)) {
        $error_message = 'Tanggal pelaporan wajib diisi.';
    } elseif (empty($items)) {
        $error_message = 'Wajib menambahkan minimal satu item sampah.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert parent report
            $stmt = $pdo->prepare("
                INSERT INTO laporan_sampah (id_email, keterangan, waktu) 
                VALUES (:id_email, :keterangan, :waktu)
            ");
            $stmt->execute([
                'id_email' => $user['id_email'],
                'keterangan' => $keterangan,
                'waktu' => $waktu
            ]);
            $id_laporan = $pdo->lastInsertId();

            // Insert child items
            $stmt_detail = $pdo->prepare("
                INSERT INTO laporan_sampah_detail (id_laporan, id_sampah, jumlah, id_satuan) 
                VALUES (:id_laporan, :id_sampah, :jumlah, :id_satuan)
            ");

            foreach ($items as $item) {
                $id_sampah = intval($item['id_sampah'] ?? 0);
                $jumlah = floatval($item['jumlah'] ?? 0);
                $id_satuan = intval($item['id_satuan'] ?? 0);

                if ($id_sampah > 0 && $jumlah > 0 && $id_satuan > 0) {
                    $stmt_detail->execute([
                        'id_laporan' => $id_laporan,
                        'id_sampah' => $id_sampah,
                        'jumlah' => $jumlah,
                        'id_satuan' => $id_satuan
                    ]);
                }
            }

            $pdo->commit();
            $success_message = 'Laporan pemilahan sampah berhasil dikirim.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Gagal menyimpan laporan: ' . $e->getMessage();
        }
    }
}

// 3. Fetch User Stats (Infobox Style)
$stats = ['Organik' => 0.0, 'Anorganik' => 0.0, 'B3' => 0.0];
try {
    $stmt = $pdo->prepare("
        SELECT k.kategori_sampah, SUM(d.jumlah * s.konversi) AS total_konversi
        FROM laporan_sampah_detail d
        JOIN laporan_sampah l ON d.id_laporan = l.id_laporan
        JOIN nama_sampah n ON d.id_sampah = n.id_sampah
        JOIN kategori k ON n.id_kategori = k.id_kategori
        JOIN satuan s ON d.id_satuan = s.id_satuan
        WHERE l.id_email = :id_email
        GROUP BY k.kategori_sampah
    ");
    $stmt->execute(['id_email' => $user['id_email']]);
    $stats_raw = $stmt->fetchAll();
    foreach ($stats_raw as $row) {
        $stats[$row['kategori_sampah']] = floatval($row['total_konversi']);
    }
} catch (PDOException $e) {
    // Fail silently
}

// 4. Fetch User Report History with Pagination
$limit = 10;
$page = intval($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$history = [];
$total_rows = 0;
$total_pages = 0;

try {
    // Total count
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM laporan_sampah WHERE id_email = :id_email");
    $stmt_count->execute(['id_email' => $user['id_email']]);
    $total_rows = $stmt_count->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // Paginated history list
    $stmt = $pdo->prepare("
        SELECT l.id_laporan, l.waktu, l.keterangan,
               GROUP_CONCAT(CONCAT(n.nama_sampah, ' (', d.jumlah, ' ', s.nama_satuan, ')') SEPARATOR ', ') AS rincian
        FROM laporan_sampah l
        LEFT JOIN laporan_sampah_detail d ON l.id_laporan = d.id_laporan
        LEFT JOIN nama_sampah n ON d.id_sampah = n.id_sampah
        LEFT JOIN satuan s ON d.id_satuan = s.id_satuan
        WHERE l.id_email = :id_email
        GROUP BY l.id_laporan
        ORDER BY l.waktu DESC, l.id_laporan DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':id_email', $user['id_email'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Gagal memuat riwayat: ' . $e->getMessage();
}

// Helper function to build page URLs while keeping query string params
function getPageUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

// Render View using layout template
$page_title = 'Dashboard Mahasiswa - SMPSM';
require_once 'includes/header.php';
?>
<div class="brand-header">
    <h1>Dashboard Pelaporan Pemilahan Sampah</h1>
    <p>Halaman pencatatan dan pelaporan pemilahan sampah mandiri mahasiswa</p>
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

<div class="dashboard-grid">
    <!-- Left Side: Reporting Form & History -->
    <div>
        <!-- Form input laporan -->
        <div class="report-section" style="border: 1px solid var(--border-color); padding: 24px; background: #fff; margin-bottom: 30px;">
            <h2 class="section-title" style="margin-bottom: 16px;">Buat Laporan Baru</h2>
            <form action="dashboard_user.php" method="POST" id="form-laporan">
                <input type="hidden" name="submit_report" value="1">
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="waktu">Tanggal Laporan</label>
                        <input type="date" id="waktu" name="waktu" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="keterangan">Keterangan Tambahan (Opsional)</label>
                        <input type="text" id="keterangan" name="keterangan" class="form-control" placeholder="Contoh: Plastik bekas makanan ringan, kardus paket.">
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <h3 style="font-size: 1rem; font-weight: bold; margin-bottom: 12px; font-family: Georgia, serif;">Daftar Item Sampah</h3>
                    
                    <!-- Rows container -->
                    <div id="trash-rows-container">
                        <!-- First default row -->
                        <div class="trash-item-row">
                            <div class="form-group" style="flex-grow: 2; margin-bottom: 0;">
                                <label>Jenis Sampah</label>
                                <select name="items[0][id_sampah]" class="form-control" required>
                                    <option value="" disabled selected>-- Pilih Jenis Sampah --</option>
                                    <?php foreach ($sampah_list as $kategori => $items_cat): ?>
                                        <optgroup label="<?php echo htmlspecialchars($kategori); ?>">
                                            <?php foreach ($items_cat as $sampah): ?>
                                                <option value="<?php echo $sampah['id_sampah']; ?>"><?php echo htmlspecialchars($sampah['nama_sampah']); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
                                <label>Jumlah</label>
                                <input type="number" step="0.01" name="items[0][jumlah]" class="form-control" placeholder="0.00" min="0.01" required>
                            </div>
                            <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
                                <label>Satuan</label>
                                <select name="items[0][id_satuan]" class="form-control" required>
                                    <option value="" disabled selected>-- Satuan --</option>
                                    <?php foreach ($satuan_list as $satuan): ?>
                                        <option value="<?php echo $satuan['id_satuan']; ?>"><?php echo htmlspecialchars($satuan['nama_satuan']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-danger btn-remove-row" style="display: none;">Hapus</button>
                        </div>
                    </div>

                    <div style="margin-top: 16px; display: flex; gap: 12px;">
                        <button type="button" class="btn" id="btn-add-row" style="flex-grow: 1;">+ Tambah Item Sampah</button>
                        <button type="submit" class="btn btn-primary" style="flex-grow: 1;">Kirim Laporan</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- History -->
        <div style="border: 1px solid var(--border-color); padding: 24px; background: #fff;">
            <h2 class="section-title">Riwayat Laporan Anda</h2>
            <?php if (empty($history)): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-style: italic;">Anda belum pernah mengirimkan laporan pemilahan sampah.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="wikitable">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Tanggal</th>
                                <th>Rincian Item Sampah Terpilah</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td><strong><?php echo date('d-m-Y', strtotime($row['waktu'])); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['rincian'] ?? 'Tidak ada rincian'); ?></td>
                                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <?php echo get_pagination_html($page, $total_pages); ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Side: Infobox Stats -->
    <div>
        <div class="infobox-style">
            <div class="infobox-title">Ringkasan Pemilahan</div>
            <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-bottom: 12px; font-style: italic;">
                Akumulasi pemilahan terkonversi
            </div>
            
            <div class="infobox-row">
                <span class="infobox-label">Organik</span>
                <span class="infobox-value"><strong><?php echo number_format($stats['Organik'], 2, ',', '.'); ?></strong> Gr</span>
            </div>
            <div class="infobox-row">
                <span class="infobox-label">Anorganik</span>
                <span class="infobox-value"><strong><?php echo number_format($stats['Anorganik'], 2, ',', '.'); ?></strong> Gr</span>
            </div>
            <div class="infobox-row">
                <span class="infobox-label">B3</span>
                <span class="infobox-value"><strong><?php echo number_format($stats['B3'], 2, ',', '.'); ?></strong> Gr</span>
            </div>
            
            <hr style="border: none; border-top: 1px solid var(--border-color); margin: 12px 0;">
            
            <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                * Semua berat satuan massa dikonversikan ke <strong>Gram (Gr)</strong>. Satuan volume dan kuantitas diakumulasikan di level admin.
            </div>
        </div>
    </div>
</div>

<!-- Template row for JS cloning -->
<template id="row-template">
    <div class="trash-item-row">
        <div class="form-group" style="flex-grow: 2; margin-bottom: 0;">
            <label>Jenis Sampah</label>
            <select name="items[{index}][id_sampah]" class="form-control" required>
                <option value="" disabled selected>-- Pilih Jenis Sampah --</option>
                <?php foreach ($sampah_list as $kategori => $items_cat): ?>
                    <optgroup label="<?php echo htmlspecialchars($kategori); ?>">
                        <?php foreach ($items_cat as $sampah): ?>
                            <option value="<?php echo $sampah['id_sampah']; ?>"><?php echo htmlspecialchars($sampah['nama_sampah']); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
            <label>Jumlah</label>
            <input type="number" step="0.01" name="items[{index}][jumlah]" class="form-control" placeholder="0.00" min="0.01" required>
        </div>
        <div class="form-group" style="flex-grow: 1; margin-bottom: 0;">
            <label>Satuan</label>
            <select name="items[{index}][id_satuan]" class="form-control" required>
                <option value="" disabled selected>-- Satuan --</option>
                <?php foreach ($satuan_list as $satuan): ?>
                    <option value="<?php echo $satuan['id_satuan']; ?>"><?php echo htmlspecialchars($satuan['nama_satuan']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" class="btn btn-danger btn-remove-row">Hapus</button>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.getElementById('trash-rows-container');
        var btnAddRow = document.getElementById('btn-add-row');
        var template = document.getElementById('row-template').innerHTML;
        var rowCounter = 1;

        // Handle adding rows
        if (btnAddRow) {
            btnAddRow.addEventListener('click', function() {
                var newRowHtml = template.replace(/{index}/g, rowCounter);
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = newRowHtml;
                var rowElement = tempDiv.firstElementChild;
                container.appendChild(rowElement);
                rowCounter++;
                toggleDeleteButtons();
            });
        }

        // Handle removing rows (event delegation)
        if (container) {
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-remove-row')) {
                    var row = e.target.closest('.trash-item-row');
                    if (row) {
                        row.remove();
                        toggleDeleteButtons();
                    }
                }
            });
        }

        function toggleDeleteButtons() {
            var rows = container.querySelectorAll('.trash-item-row');
            var deleteButtons = container.querySelectorAll('.btn-remove-row');
            if (rows.length <= 1) {
                deleteButtons[0].style.display = 'none';
            } else {
                deleteButtons.forEach(function(btn) {
                    btn.style.display = 'inline-flex';
                });
            }
        }
    });
</script>
<?php
require_once 'includes/footer.php';
?>
