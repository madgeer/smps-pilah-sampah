<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'SMPPH'); ?></title>
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
    <?php if ($user && !isset($hide_navbar)): ?>
        <div class="navbar">
            <h2>SMPPH <?php echo $user['role'] === 'admin' ? '(Administrator)' : ''; ?></h2>
            <div class="nav-links">
                <span class="nav-user-info">Masuk sebagai: <strong><?php echo htmlspecialchars($user['email']); ?></strong> (<?php echo $user['role'] === 'admin' ? 'Admin' : 'Mahasiswa'; ?>)</span>
                <a href="logout.php">Keluar</a>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="container" <?php echo isset($container_style) ? 'style="' . $container_style . '"' : ''; ?>>
