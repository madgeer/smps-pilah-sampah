<?php
// includes/auth_helper.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the user is logged in. Optionally checks if they have the required role.
 * Redirects to the login page if not logged in.
 * Redirects to their correct dashboard if they try to access a page they don't have role permissions for.
 * 
 * @param string|null $required_role 'admin' or 'user'
 */
function check_auth($required_role = null) {
    if (!isset($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }

    if ($required_role && $_SESSION['user']['role'] !== $required_role) {
        if ($_SESSION['user']['role'] === 'admin') {
            header('Location: dashboard_admin.php');
        } else {
            header('Location: dashboard_user.php');
        }
        exit;
    }
}
