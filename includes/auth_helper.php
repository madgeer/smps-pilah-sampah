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

/**
 * Generates beautiful, news-website style paginated link bar.
 * Prevents pagination buttons overflow by introducing "..." (ellipsis) ranges.
 * Automatically preserves existing GET query string filter parameters.
 *
 * @param int $page Current active page number
 * @param int $total_pages Total number of pages
 * @return string HTML pagination bar
 */
function get_pagination_html($page, $total_pages) {
    if ($total_pages <= 1) return '';

    // Closure to build query strings preserving all parameters except page
    $get_page_url = function($pageNum) {
        $params = $_GET;
        $params['page'] = $pageNum;
        return '?' . http_build_query($params);
    };

    $html = '<div class="pagination">';
    $html .= '<span>Halaman:</span> ';

    // Previous link
    if ($page > 1) {
        $html .= '<a href="' . htmlspecialchars($get_page_url($page - 1)) . '">&laquo; Sebelumnya</a>';
    } else {
        $html .= '<span class="disabled">&laquo; Sebelumnya</span>';
    }

    $adjacents = 1;
    $pages = [];

    // Always include the first page
    $pages[] = 1;

    // Show ellipsis if current page is far from the beginning
    if ($page - $adjacents > 2) {
        $pages[] = '...';
    }

    // Build surrounding pages range
    $start = max(2, $page - $adjacents);
    $end = min($total_pages - 1, $page + $adjacents);
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }

    // Show ellipsis if current page is far from the end
    if ($page + $adjacents < $total_pages - 1) {
        $pages[] = '...';
    }

    // Always include the last page
    if ($total_pages > 1) {
        $pages[] = $total_pages;
    }

    // Render page items
    foreach ($pages as $p) {
        if ($p === '...') {
            $html .= '<span style="border: none; background: none; padding: 4px 8px; color: var(--text-muted); cursor: default;">...</span>';
        } elseif ($p === $page) {
            $html .= '<span class="current">' . $p . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($get_page_url($p)) . '">' . $p . '</a>';
        }
    }

    // Next link
    if ($page < $total_pages) {
        $html .= '<a href="' . htmlspecialchars($get_page_url($page + 1)) . '">Berikutnya &raquo;</a>';
    } else {
        $html .= '<span class="disabled">Berikutnya &raquo;</span>';
    }

    $html .= '</div>';
    return $html;
}
