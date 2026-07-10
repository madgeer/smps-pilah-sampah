<?php
// profile.php
require_once 'includes/auth_helper.php';
check_auth(); // Ensure logged in

require_once 'config/database.php';

$user = $_SESSION['user'];
$success_message = '';
$error_message = '';

$profile_data = [
    'nama' => 'Administrator',
    'kelas' => 'N/A',
    'kelompok' => 'N/A',
    'total_laporan' => 0,
    'total_berat_kg' => 0.0
];

// 1. Fetch Profile and Stats depending on Role
try {
    if ($user['role'] === 'user') {
        // Fetch Student Profile (Nama, Kelas, Kelompok)
        $stmt = $pdo->prepare("
            SELECT m.nama, k.nama_kelompok, kl.nama_kelas
            FROM mahasiswa m
            JOIN kelompok k ON m.id_kelompok = k.id_kelompok
            JOIN kelas kl ON k.id_kelas = kl.id_kelas
            WHERE m.id_mahasiswa = :id_mahasiswa
        ");
        $stmt->execute(['id_mahasiswa' => $user['id_mahasiswa']]);
        $student = $stmt->fetch();
        if ($student) {
            $profile_data['nama'] = $student['nama'];
            $profile_data['kelas'] = $student['nama_kelas'];
            $profile_data['kelompok'] = $student['nama_kelompok'];
        }

        // Fetch Total Reports Submitted
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM laporan_sampah WHERE id_email = :id_email");
        $stmt->execute(['id_email' => $user['id_email']]);
        $profile_data['total_laporan'] = $stmt->fetchColumn();

        // Fetch Total Weight Converted (Massa -> Kg)
        $stmt = $pdo->prepare("
            SELECT SUM(d.jumlah * s.konversi) 
            FROM laporan_sampah_detail d
            JOIN laporan_sampah l ON d.id_laporan = l.id_laporan
            JOIN satuan s ON d.id_satuan = s.id_satuan
            JOIN satuan_standar st ON s.id_satuan_standar = st.id_satuan_standar
            WHERE l.id_email = :id_email AND st.dimensi = 'Massa'
        ");
        $stmt->execute(['id_email' => $user['id_email']]);
        $total_gr = floatval($stmt->fetchColumn());
        $profile_data['total_berat_kg'] = $total_gr / 1000.0;
    } else {
        // Fetch Admin stats: count total reports in system
        $profile_data['total_laporan'] = $pdo->query("SELECT COUNT(*) FROM laporan_sampah")->fetchColumn();
    }
} catch (PDOException $e) {
    $error_message = 'Gagal memuat profil: ' . $e->getMessage();
}

// 2. Handle Password Change Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Semua kolom kata sandi wajib diisi.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Konfirmasi kata sandi baru tidak cocok.';
    } else {
        try {
            // Verify current password from database
            $stmt = $pdo->prepare("SELECT password FROM login WHERE id_login = :id_login LIMIT 1");
            $stmt->execute(['id_login' => $user['id_login']]);
            $db_password = $stmt->fetchColumn();

            if ($current_password !== $db_password) {
                $error_message = 'Kata sandi saat ini salah.';
            } else {
                // Update password in login table
                $stmt = $pdo->prepare("UPDATE login SET password = :new_password WHERE id_login = :id_login");
                $stmt->execute([
                    'new_password' => $new_password,
                    'id_login' => $user['id_login']
                ]);
                $success_message = 'Kata sandi Anda berhasil diperbarui.';
            }
        } catch (PDOException $e) {
            $error_message = 'Gagal memperbarui kata sandi: ' . $e->getMessage();
        }
    }
}

// Render View using layout template
$page_title = 'Profil Saya - SMPSM';
require_once 'includes/header.php';
?>
<div class="brand-header">
    <h1>Profil Saya</h1>
    <p>Lihat detail akun dan perbarui kata sandi keamanan Anda</p>
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
    <!-- Left Column: Profile Card (Wikipedia Infobox Style) -->
    <div>
        <div style="border: 1px solid var(--border-color); padding: 24px; background: #fff;">
            <h2 class="section-title" style="margin-bottom: 20px;">Informasi Pengguna</h2>
            
            <div class="table-container">
                <table class="wikitable">
                    <tbody>
                        <tr>
                            <th style="width: 200px; background-color: var(--bg-header);">Nama Lengkap</th>
                            <td><strong><?php echo htmlspecialchars($profile_data['nama']); ?></strong></td>
                        </tr>
                        <tr>
                            <th style="background-color: var(--bg-header);">Alamat Email</th>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th style="background-color: var(--bg-header);">Hak Akses / Peran</th>
                            <td>
                                <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-b3' : 'badge-organik'; ?>">
                                    <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Mahasiswa'; ?>
                                </span>
                            </td>
                        </tr>
                        
                        <?php if ($user['role'] === 'user'): ?>
                            <tr>
                                <th style="background-color: var(--bg-header);">Kelas</th>
                                <td>Kelas <?php echo htmlspecialchars($profile_data['kelas']); ?></td>
                            </tr>
                            <tr>
                                <th style="background-color: var(--bg-header);">Kelompok</th>
                                <td><?php echo htmlspecialchars($profile_data['kelompok']); ?></td>
                            </tr>
                            <tr>
                                <th style="background-color: var(--bg-header);">Total Laporan Dikirim</th>
                                <td><strong><?php echo number_format($profile_data['total_laporan'], 0, ',', '.'); ?></strong> laporan</td>
                            </tr>
                            <tr>
                                <th style="background-color: var(--bg-header);">Total Kontribusi Pemilahan</th>
                                <td><strong><?php echo number_format($profile_data['total_berat_kg'], 2, ',', '.'); ?></strong> Kg</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th style="background-color: var(--bg-header);">Total Laporan di Sistem</th>
                                <td><strong><?php echo number_format($profile_data['total_laporan'], 0, ',', '.'); ?></strong> laporan terdaftar</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Change Password Form -->
    <div>
        <div style="border: 1px solid var(--border-color); padding: 24px; background: #fff;">
            <h2 class="section-title">Perbarui Kata Sandi</h2>
            
            <form action="profile.php" method="POST">
                <input type="hidden" name="change_password" value="1">
                
                <div class="form-group">
                    <label for="current_password">Kata Sandi Saat Ini</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Kata Sandi Baru</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Simpan Kata Sandi</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once 'includes/footer.php';
?>
