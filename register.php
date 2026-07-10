<?php
// register.php
session_start();
require_once 'config/database.php';

$success_message = '';
$error_message = '';

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Fetch Kelas & Kelompok for dropdown selections
$classes_list = [];
$groups_by_class = [];
try {
    $classes_list = $pdo->query("SELECT id_kelas, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
    
    $raw_groups = $pdo->query("SELECT id_kelompok, nama_kelompok, id_kelas FROM kelompok ORDER BY nama_kelompok ASC")->fetchAll();
    foreach ($raw_groups as $row) {
        $groups_by_class[$row['id_kelas']][] = [
            'id_kelompok' => $row['id_kelompok'],
            'nama_kelompok' => $row['nama_kelompok']
        ];
    }
} catch (PDOException $e) {
    $error_message = 'Gagal memuat data registrasi: ' . $e->getMessage();
}

// Handle Registration Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $id_kelompok = intval($_POST['id_kelompok'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Semua kolom wajib diisi.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        try {
            // Check if email already exists in email table
            $stmt = $pdo->prepare("SELECT id_email, id_mahasiswa FROM email WHERE nama_email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $existing_email = $stmt->fetch();

            if ($existing_email) {
                // Email exists. Check if they already have login credentials
                $stmt = $pdo->prepare("SELECT id_login FROM login WHERE id_email = :id_email LIMIT 1");
                $stmt->execute(['id_email' => $existing_email['id_email']]);
                $existing_login = $stmt->fetch();

                if ($existing_login) {
                    $error_message = 'Email ini sudah terdaftar dan memiliki akun. Silakan masuk.';
                } else {
                    // Pre-registered email without an account. Directly create login credentials!
                    $stmt = $pdo->prepare("INSERT INTO login (id_email, password, role) VALUES (:id_email, :password, 'user')");
                    $stmt->execute([
                        'id_email' => $existing_email['id_email'],
                        'password' => $password
                    ]);
                    $success_message = 'Pendaftaran berhasil! Akun Anda telah diaktifkan. Silakan login.';
                }
            } else {
                // Brand new email. Requires student profile creation (name and group).
                if (empty($nama) || $id_kelompok <= 0) {
                    $error_message = 'Nama Lengkap dan Kelompok wajib diisi untuk pendaftaran baru.';
                } else {
                    $pdo->beginTransaction();

                    // 1. Create student profile
                    $stmt = $pdo->prepare("INSERT INTO mahasiswa (nama, id_kelompok) VALUES (:nama, :id_kelompok)");
                    $stmt->execute([
                        'nama' => $nama,
                        'id_kelompok' => $id_kelompok
                    ]);
                    $id_mahasiswa = $pdo->lastInsertId();

                    // 2. Create email link
                    $stmt = $pdo->prepare("INSERT INTO email (nama_email, id_mahasiswa) VALUES (:email, :id_mahasiswa)");
                    $stmt->execute([
                        'email' => $email,
                        'id_mahasiswa' => $id_mahasiswa
                    ]);
                    $id_email = $pdo->lastInsertId();

                    // 3. Create login credentials
                    $stmt = $pdo->prepare("INSERT INTO login (id_email, password, role) VALUES (:id_email, :password, 'user')");
                    $stmt->execute([
                        'id_email' => $id_email,
                        'password' => $password
                    ]);

                    $pdo->commit();
                    $success_message = 'Pendaftaran mahasiswa baru berhasil! Silakan login.';
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Gagal melakukan pendaftaran: ' . $e->getMessage();
        }
    }
}

// Render Registration View using reusable layout
$page_title = 'Daftar Akun Baru - SMPPH';
$container_style = 'max-width: 500px; margin-top: 5vh; flex-grow: 0;';
$footer_style = 'margin-top: auto; background: none; border-top: none;';
$hide_navbar = true;

require_once 'includes/header.php';
?>
<div class="brand-header" style="text-align: center; border-bottom: none; margin-bottom: 8px;">
    <h1>Pendaftaran Akun</h1>
    <p>Sistem Manajemen Pemilahan Sampah Mahasiswa</p>
</div>

<hr style="border: none; border-top: 1px solid var(--border-color); margin-bottom: 20px;">

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <strong>Sukses:</strong> <?php echo htmlspecialchars($success_message); ?>
        <div style="margin-top: 8px;">
            <a href="index.php" class="btn btn-primary btn-small">Ke Halaman Masuk &raquo;</a>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Galat:</strong> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if (empty($success_message)): ?>
    <form action="register.php" method="POST" id="form-register">
        <div class="form-group">
            <label for="email">Alamat Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="contoh@example.com" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 4px;">
                *Jika email Anda sudah didaftarkan oleh admin, Anda hanya perlu membuat kata sandi baru.
            </span>
        </div>

        <!-- Profile Section: only visible/required for new emails -->
        <div id="new-profile-fields">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" placeholder="Nama lengkap Anda..." value="<?php echo htmlspecialchars($nama ?? ''); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div class="form-group">
                    <label for="kelas_select">Kelas</label>
                    <select id="kelas_select" class="form-control">
                        <option value="" disabled selected>-- Pilih Kelas --</option>
                        <?php foreach ($classes_list as $cl): ?>
                            <option value="<?php echo $cl['id_kelas']; ?>"><?php echo htmlspecialchars($cl['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="kelompok_select">Kelompok</label>
                    <select id="kelompok_select" name="id_kelompok" class="form-control">
                        <option value="0" disabled selected>-- Pilih Kelompok --</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div class="form-group">
                <label for="password">Kata Sandi Baru</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Konfirmasi Kata Sandi</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
            <button type="submit" class="btn btn-primary" style="width: 100%;">Daftarkan Akun</button>
            <a href="index.php" class="btn btn-secondary" style="width: 100%; text-align: center;">Kembali ke Login</a>
        </div>
    </form>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const kelompokByKelas = <?php echo json_encode($groups_by_class); ?>;
        const kelasSelect = document.getElementById('kelas_select');
        const kelompokSelect = document.getElementById('kelompok_select');
        const emailInput = document.getElementById('email');
        const namaInput = document.getElementById('nama');
        const newProfileFields = document.getElementById('new-profile-fields');

        // 1. Dynamic Kelompok Dropdown based on selected Kelas
        if (kelasSelect) {
            kelasSelect.addEventListener('change', function() {
                const idKelas = this.value;
                kelompokSelect.innerHTML = '<option value="0" disabled selected>-- Pilih Kelompok --</option>';
                
                if (kelompokByKelas[idKelas]) {
                    kelompokByKelas[idKelas].forEach(function(gr) {
                        const opt = document.createElement('option');
                        opt.value = gr.id_kelompok;
                        opt.textContent = gr.nama_kelompok;
                        kelompokSelect.appendChild(opt);
                    });
                }
            });
        }

        // 2. Client-side Email Check to adjust fields
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                const email = this.value.trim().toLowerCase();
                if (email.startsWith('mahasiswa') && email.includes('@example.com')) {
                    newProfileFields.style.opacity = '0.5';
                    namaInput.required = false;
                    kelompokSelect.required = false;
                    kelasSelect.required = false;
                } else {
                    newProfileFields.style.opacity = '1';
                    namaInput.required = true;
                    kelompokSelect.required = true;
                    kelasSelect.required = true;
                }
            });
        }
    });
</script>
<?php
require_once 'includes/footer.php';
?>
