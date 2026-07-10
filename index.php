<?php
// index.php
session_start();
require_once 'config/database.php';

$error_message = '';

// 1. Check if already logged in, route to appropriate dashboard
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: dashboard_admin.php');
        exit;
    } else {
        header('Location: dashboard_user.php');
        exit;
    }
}

// 2. Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error_message = 'Email dan kata sandi wajib diisi.';
    } else {
        try {
            // Find email in email table
            $stmt = $pdo->prepare("SELECT id_email, id_mahasiswa FROM email WHERE nama_email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $email_record = $stmt->fetch();

            if ($email_record) {
                // Find credentials in login table using id_email
                $stmt = $pdo->prepare("SELECT id_login, password, role FROM login WHERE id_email = :id_email LIMIT 1");
                $stmt->execute(['id_email' => $email_record['id_email']]);
                $login_record = $stmt->fetch();

                if ($login_record && $password === $login_record['password']) {
                    // Password matches (Simple comparison for seed data compatibility)
                    $_SESSION['user'] = [
                        'id_login' => $login_record['id_login'],
                        'id_email' => $email_record['id_email'],
                        'email' => $email,
                        'role' => $login_record['role'],
                        'id_mahasiswa' => $email_record['id_mahasiswa']
                    ];
                    
                    // Route to correct page
                    if ($login_record['role'] === 'admin') {
                        header('Location: dashboard_admin.php');
                    } else {
                        header('Location: dashboard_user.php');
                    }
                    exit;
                } else {
                    $error_message = 'Kata sandi salah.';
                }
            } else {
                $error_message = 'Alamat email tidak terdaftar.';
            }
        } catch (PDOException $e) {
            $error_message = 'Gagal melakukan autentikasi: ' . $e->getMessage();
        }
    }
}

// 3. Render Login View using reusable layout
$page_title = 'Masuk - SMPSM';
$container_style = 'max-width: 440px; margin-top: 10vh; flex-grow: 0;';
$footer_style = 'margin-top: auto; background: none; border-top: none;';
$hide_navbar = true;

require_once 'includes/header.php';
?>
<div class="brand-header" style="text-align: center; border-bottom: none; margin-bottom: 16px;">
    <h1>SMPSM</h1>
    <p>Sistem Manajemen Pemilahan Sampah Mahasiswa</p>
</div>

<hr style="border: none; border-top: 1px solid var(--border-color); margin-bottom: 24px;">

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Galat:</strong> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<form action="index.php" method="POST">
    <div class="form-group">
        <label for="email">Alamat Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="contoh@example.com" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="password">Kata Sandi</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
    </div>
    
    <div style="margin-top: 24px;">
        <button type="submit" class="btn btn-primary" style="width: 100%;">Masuk</button>
    </div>
</form>

<div style="margin-top: 16px; text-align: center; font-size: 0.85rem;">
    Belum memiliki akun? <a href="register.php" style="color: var(--primary); text-decoration: none; font-weight: bold;">Daftar di sini &raquo;</a>
</div>

<div style="margin-top: 20px; text-align: center; font-size: 0.8rem; color: var(--text-muted); border-top: 1px dashed var(--border-color); padding-top: 14px;">
    Gunakan akun demo: <strong>admin@example.com</strong> / <strong>admin123</strong>
</div>
<?php
require_once 'includes/footer.php';
?>
