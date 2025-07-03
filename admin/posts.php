<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_posts = $_POST['selected_posts'] ?? [];
    
    if (!empty($selected_posts) && in_array($action, ['delete', 'published', 'draft'])) {
        $placeholders = str_repeat('?,', count($selected_posts) - 1) . '?';
        
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_posts)), ...$selected_posts);
            $stmt->execute();
            $success = count($selected_posts) . " posts deleted successfully.";
        } else {
            $stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$action], $selected_posts);
            $types = 's' . str_repeat('i', count($selected_posts));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $success = count($selected_posts) . " posts updated to " . $action . ".";
        }
    }
}

$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$author_filter = $_GET['author'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['s'] ?? '';
$orderby = $_GET['orderby'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Pagination
$page = max(1, (int)($_GET['paged'] ?? 1));
$posts_per_page = 20;
$offset = ($page - 1) * $posts_per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "posts.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Fix author filtering to handle both ID and name
if ($author_filter) {
    if (is_numeric($author_filter)) {
        // Filter by author ID
        $where_conditions[] = "posts.author_id = ?";
        $params[] = (int)$author_filter;
        $types .= 'i';
    } else {
        // Filter by author name
        $where_conditions[] = "authors.name = ?";
        $params[] = $author_filter;
        $types .= 's';
    }
}

if ($category_filter) {
    $where_conditions[] = "posts.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(posts.title LIKE ? OR posts.content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid columns for ordering
$valid_orderby = ['title', 'created_at', 'status', 'author_name', 'views'];
$orderby = in_array($orderby, $valid_orderby) ? $orderby : 'created_at';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Count total posts
$count_query = "SELECT COUNT(*) as total FROM posts LEFT JOIN authors ON posts.author_id = authors.id $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_posts = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_posts = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_posts / $posts_per_page);

// Fetch posts
$posts_query = "SELECT posts.*, authors.name as author_name 
                FROM posts 
                LEFT JOIN authors ON posts.author_id = authors.id 
                $where_clause 
                ORDER BY $orderby $order 
                LIMIT ? OFFSET ?";

$final_params = array_merge($params, [$posts_per_page, $offset]);
$final_types = $types . 'ii';

$posts_stmt = $conn->prepare($posts_query);
$posts_stmt->bind_param($final_types, ...$final_params);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();

// Get authors for filter
$authors = $conn->query("SELECT DISTINCT authors.id, authors.name FROM authors JOIN posts ON authors.id = posts.author_id ORDER BY authors.name");

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL AND category != '' ORDER BY category");
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];

// Get status counts
$status_counts = [];
$status_query = "SELECT status, COUNT(*) as count FROM posts GROUP BY status";
$status_result = $conn->query($status_query);
while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
$status_counts['all'] = array_sum($status_counts);
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];

// Get current author name for display
$current_author_name = '';
if ($author_filter && is_numeric($author_filter)) {
    $author_stmt = $conn->prepare("SELECT name FROM authors WHERE id = ?");
    $author_stmt->bind_param("i", $author_filter);
    $author_stmt->execute();
    $author_result = $author_stmt->get_result();
    if ($author_row = $author_result->fetch_assoc()) {
        $current_author_name = $author_row['name'];
    }
    $author_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts - My PHP CMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --wp-blue: #0073aa;
            --wp-blue-dark: #005177;
            --wp-blue-light: #00a0d2;
            --wp-gray-100: #f6f7f7;
            --wp-gray-200: #dcdcde;
            --wp-gray-300: #c3c4c7;
            --wp-gray-400: #a7aaad;
            --wp-gray-500: #8c8f94;
            --wp-gray-600: #646970;
            --wp-gray-700: #50575e;
            --wp-gray-800: #3c434a;
            --wp-gray-900: #1d2327;
            --wp-white: #ffffff;
            --wp-success: #00a32a;
            --wp-warning: #dba617;
            --wp-error: #d63638;
            --wp-info: #72aee6;
            --sidebar-width: 280px;
            --border-radius: 6px;
            --box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--wp-gray-100);
            color: var(--wp-gray-900);
            line-height: 1.6;
        }

        /* WordPress Admin Bar */
        .wp-admin-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 32px;
            background: var(--wp-gray-900);
            color: var(--wp-white);
            z-index: 99999;
            display: flex;
            align-items: center;
            padding: 0 20px;
            font-size: 13px;
        }

        .wp-admin-bar .site-name {
            color: var(--wp-white);
            text-decoration: none;
            font-weight: 500;
        }

        .wp-admin-bar .spacer {
            flex: 1;
        }

        .wp-admin-bar .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-admin-bar .avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--wp-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        /* Main Layout */
        .wp-admin {
            display: flex;
            min-height: 100vh;
            padding-top: 32px;
        }

        /* Sidebar */
        .wp-sidebar {
            width: var(--sidebar-width);
            background: var(--wp-white);
            border-right: 1px solid var(--wp-gray-200);
            position: fixed;
            top: 32px;
            bottom: 0;
            left: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .wp-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: var(--wp-white);
        }

        .wp-sidebar-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: var(--wp-gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-sidebar-header .version {
            font-size: 12px;
            color: var(--wp-gray-500);
            font-weight: 400;
        }

        .wp-menu {
            list-style: none;
            padding: 0;
        }

        .wp-menu-item {
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-menu-link {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--wp-gray-700);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .wp-menu-link:hover,
        .wp-menu-link.active {
            background: var(--wp-gray-100);
            color: var(--wp-blue);
        }

        .wp-menu-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--wp-blue);
        }

        .wp-menu-icon {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .wp-menu-badge {
            background: var(--wp-error);
            color: var(--wp-white);
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
        }

        /* Main Content */
        .wp-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            background: var(--wp-gray-100);
        }

        .wp-header {
            background: var(--wp-white);
            border-bottom: 1px solid var(--wp-gray-200);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .wp-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
        }

        .wp-header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .wp-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--wp-blue);
            color: var(--wp-white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .wp-button:hover {
            background: var(--wp-blue-dark);
            transform: translateY(-1px);
        }

        .wp-button-secondary {
            background: var(--wp-white);
            color: var(--wp-gray-700);
            border: 1px solid var(--wp-gray-300);
        }

        .wp-button-secondary:hover {
            background: var(--wp-gray-100);
            color: var(--wp-gray-900);
        }

        .wp-button-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Content Area */
        .wp-content {
            padding: 30px;
        }

        /* Alert Messages */
        .wp-alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .wp-alert.success {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .wp-alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c2c7;
        }

        /* Filter Notice */
        .wp-filter-notice {
            background: var(--wp-info);
            color: var(--wp-white);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }

        .wp-filter-notice a {
            color: var(--wp-white);
            text-decoration: underline;
        }

        /* Status Filters */
        .wp-status-filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--wp-gray-200);
            padding-bottom: 15px;
        }

        .wp-status-filter {
            color: var(--wp-blue);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            position: relative;
        }

        .wp-status-filter:hover,
        .wp-status-filter.current {
            border-bottom-color: var(--wp-blue);
            color: var(--wp-blue-dark);
        }

        .wp-status-count {
            color: var(--wp-gray-500);
            font-weight: 400;
        }

        /* Filters Bar */
        .wp-filters-bar {
            background: var(--wp-white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }

        .wp-filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }

        .wp-form-group {
            display: flex;
            flex-direction: column;
        }

        .wp-form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--wp-gray-700);
            margin-bottom: 5px;
        }

        .wp-search-box {
            position: relative;
        }

        .wp-search-input {
            width: 100%;
            padding: 8px 35px 8px 12px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--wp-white);
            color: var(--wp-gray-900);
            transition: var(--transition);
        }

        .wp-search-input:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }

        .wp-search-button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--wp-gray-500);
            cursor: pointer;
            padding: 5px;
        }

        .wp-filter-select {
            padding: 8px 12px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--wp-white);
            color: var(--wp-gray-900);
            transition: var(--transition);
        }

        .wp-filter-select:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }

        /* Posts Table */
        .wp-posts-table {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .wp-table {
            width: 100%;
            border-collapse: collapse;
        }

        .wp-table th,
        .wp-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-table th {
            background: var(--wp-gray-100);
            font-weight: 600;
            color: var(--wp-gray-900);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .wp-table th a {
            color: var(--wp-gray-900);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .wp-table th a:hover {
            color: var(--wp-blue);
        }

        .wp-table tbody tr:hover {
            background: var(--wp-gray-100);
        }

        .wp-table-checkbox {
            width: 40px;
        }

        .wp-table-title {
            width: 40%;
        }

        .wp-table-author {
            width: 15%;
        }

        .wp-table-category {
            width: 15%;
        }

        .wp-table-date {
            width: 15%;
        }

        .wp-table-status {
            width: 10%;
        }

        .wp-table-views {
            width: 10%;
        }

        /* Post Title */
        .wp-post-title {
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .wp-post-title a {
            color: inherit;
            text-decoration: none;
        }

        .wp-post-title a:hover {
            color: var(--wp-blue);
        }

        .wp-post-actions {
            display: flex;
            gap: 10px;
            font-size: 13px;
        }

        .wp-post-action {
            color: var(--wp-blue);
            text-decoration: none;
            transition: var(--transition);
            padding: 2px 0;
        }

        .wp-post-action:hover {
            color: var(--wp-blue-dark);
        }

        .wp-post-action.danger {
            color: var(--wp-error);
        }

        .wp-post-action.danger:hover {
            color: #b32d2e;
        }

        /* Status Badge */
        .wp-status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .wp-status-badge.published {
            background: var(--wp-success);
            color: var(--wp-white);
        }

        .wp-status-badge.draft {
            background: var(--wp-warning);
            color: var(--wp-white);
        }

        /* Bulk Actions */
        .wp-bulk-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            background: var(--wp-gray-100);
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-bulk-select {
            padding: 6px 10px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 13px;
            background: var(--wp-white);
        }

        .wp-bulk-button {
            padding: 6px 12px;
            background: var(--wp-white);
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
        }

        .wp-bulk-button:hover {
            background: var(--wp-gray-100);
        }

        /* Pagination */
        .wp-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--wp-white);
            border-top: 1px solid var(--wp-gray-200);
        }

        .wp-pagination-info {
            color: var(--wp-gray-600);
            font-size: 14px;
        }

        .wp-pagination-links {
            display: flex;
            gap: 5px;
        }

        .wp-pagination-link {
            padding: 8px 12px;
            color: var(--wp-blue);
            text-decoration: none;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }

        .wp-pagination-link:hover,
        .wp-pagination-link.current {
            background: var(--wp-blue);
            color: var(--wp-white);
            border-color: var(--wp-blue);
        }

        .wp-pagination-link.disabled {
            color: var(--wp-gray-400);
            cursor: not-allowed;
        }

        /* Empty State */
        .wp-empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--wp-gray-600);
        }

        .wp-empty-state i {
            font-size: 64px;
            color: var(--wp-gray-400);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .wp-empty-state h3 {
            font-size: 18px;
            color: var(--wp-gray-700);
            margin-bottom: 10px;
        }

        .wp-empty-state p {
            font-size: 14px;
            color: var(--wp-gray-600);
            margin-bottom: 20px;
        }

        .wp-empty-state a {
            color: var(--wp-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .wp-empty-state a:hover {
            color: var(--wp-blue-dark);
        }

        /* Mobile Responsive */
        .wp-mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--wp-gray-700);
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
        }

        @media (max-width: 768px) {
            .wp-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .wp-sidebar.open {
                transform: translateX(0);
            }
            
            .wp-main {
                margin-left: 0;
            }
            
            .wp-mobile-toggle {
                display: block;
            }
            
            .wp-header {
                padding: 15px 20px;
            }
            
            .wp-content {
                padding: 20px;
            }
            
            .wp-filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .wp-table {
                font-size: 14px;
            }
            
            .wp-table th,
            .wp-table td {
                padding: 10px 8px;
            }
            
            .wp-table-title {
                width: 50%;
            }
            
            .wp-table-author,
            .wp-table-category {
                display: none;
            }
            
            .wp-post-actions {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .wp-header h1 {
                font-size: 20px;
            }
            
            .wp-status-filters {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <!-- WordPress Admin Bar -->
    <div class="wp-admin-bar">
        <a href="../index.php" class="site-name" target="_blank">
            <i class="fas fa-external-link-alt"></i> Visit Site
        </a>
        <div class="spacer"></div>
        <div class="user-info">
            <div class="avatar">A</div>
            <span>Admin</span>
            <a href="logout.php" style="color: var(--wp-white); margin-left: 10px;">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="wp-admin">
        <!-- Sidebar -->
        <nav class="wp-sidebar" id="wpSidebar">
            <div class="wp-sidebar-header">
                <h1>
                    <i class="fas fa-newspaper" style="color: var(--wp-blue);"></i>
                    My PHP CMS
                    <span class="version">v2.0</span>
                </h1>
            </div>
            
            <ul class="wp-menu">
                <li class="wp-menu-item">
                    <a href="dashboard.php" class="wp-menu-link">
                        <i class="fas fa-tachometer-alt wp-menu-icon"></i>
                        Dashboard
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="create.php" class="wp-menu-link">
                        <i class="fas fa-plus wp-menu-icon"></i>
                        Add New Post
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="posts.php" class="wp-menu-link active">
                        <i class="fas fa-file-alt wp-menu-icon"></i>
                        All Posts
                        <span class="wp-menu-badge"><?= $totalPosts ?></span>
                    </a>
                </li>
<li class="wp-menu-item">
                    <a href="pages.php" class="wp-menu-link">
                        <i class="fas fa-copy wp-menu-icon"></i>
                        Pages
                        <span class="wp-menu-badge"><?= $totalPages ?></span>
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="comments.php" class="wp-menu-link">
                        <i class="fas fa-comments wp-menu-icon"></i>
                        Comments
                        <?php if ($totalComments > 0): ?>
                            <span class="wp-menu-badge"><?= $totalComments ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="media.php" class="wp-menu-link">
                        <i class="fas fa-photo-video wp-menu-icon"></i>
                        Media Library
                    </a>
                </li>
<li class="wp-menu-item">
                   <a href="authors.php" class="wp-menu-link">
                       <i class="fas fa-users wp-menu-icon"></i>
                       Authors
                       <span class="wp-menu-badge"><?= $totalAuthors ?></span>
                   </a>
               </li>
                <li class="wp-menu-item">
                    <a href="add_category.php" class="wp-menu-link">
                        <i class="fas fa-folder wp-menu-icon"></i>
                        Manage Categories
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="add_tag.php" class="wp-menu-link">
                        <i class="fas fa-tags wp-menu-icon"></i>
                        Manage Tags
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="manage_users.php" class="wp-menu-link">
                        <i class="fas fa-cog wp-menu-icon"></i>
                        Manage Users
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="wp-main">
            <!-- Header -->
            <header class="wp-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <button class="wp-mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Posts</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="create.php" class="wp-button">
                        <i class="fas fa-plus"></i>
                        Add New Post
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <?php if (isset($success)): ?>
                    <div class="wp-alert success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Filter Notice -->
                <?php if ($author_filter || $category_filter || $search): ?>
                    <div class="wp-filter-notice">
                        <div>
                            <i class="fas fa-filter"></i>
                            Showing filtered results
                            <?php if ($current_author_name): ?>
                                for author: <strong><?= htmlspecialchars($current_author_name) ?></strong>
                            <?php endif; ?>
                            <?php if ($category_filter): ?>
                                in category: <strong><?= htmlspecialchars($category_filter) ?></strong>
                            <?php endif; ?>
                            <?php if ($search): ?>
                                matching: <strong>"<?= htmlspecialchars($search) ?>"</strong>
                            <?php endif; ?>
                        </div>
                        <a href="posts.php">Clear filters</a>
                    </div>
                <?php endif; ?>

                <!-- Status Filters -->
                <div class="wp-status-filters">
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'all'])) ?>"
                       class="wp-status-filter <?= $status_filter === 'all' ? 'current' : '' ?>">
                        All <span class="wp-status-count">(<?= $status_counts['all'] ?? 0 ?>)</span>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'published'])) ?>"
                       class="wp-status-filter <?= $status_filter === 'published' ? 'current' : '' ?>">
                        Published <span class="wp-status-count">(<?= $status_counts['published'] ?? 0 ?>)</span>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'draft'])) ?>"
                       class="wp-status-filter <?= $status_filter === 'draft' ? 'current' : '' ?>">
                        Draft <span class="wp-status-count">(<?= $status_counts['draft'] ?? 0 ?>)</span>
                    </a>
                </div>

                <!-- Filters Bar -->
                <div class="wp-filters-bar">
                    <div class="wp-filters-grid">
                        <div class="wp-form-group">
                            <label class="wp-form-label">Search Posts</label>
                            <form method="GET" class="wp-search-box">
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 's'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <input type="text" name="s" class="wp-search-input"
                                       placeholder="Search posts..." value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="wp-search-button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>

                        <div class="wp-form-group">
                            <label class="wp-form-label">Filter by Author</label>
                            <form method="GET">
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'author'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <select name="author" class="wp-filter-select" onchange="this.form.submit()">
                                    <option value="">All Authors</option>
                                    <?php while ($author = $authors->fetch_assoc()): ?>
                                        <option value="<?= $author['id'] ?>"
                                                <?= $author_filter == $author['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($author['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>

                        <div class="wp-form-group">
                            <label class="wp-form-label">Filter by Category</label>
                            <form method="GET">
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'category'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <select name="category" class="wp-filter-select" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php while ($category = $categories->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($category['category']) ?>"
                                                <?= $category_filter === $category['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Posts Table -->
                <div class="wp-posts-table">
                    <form method="POST" id="bulkForm">
                        <!-- Bulk Actions -->
                        <div class="wp-bulk-actions">
                            <select name="bulk_action" class="wp-bulk-select">
                                <option value="">Bulk Actions</option>
                                <option value="published">Mark as Published</option>
                                <option value="draft">Mark as Draft</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="submit" class="wp-bulk-button" onclick="return confirmBulkAction()">Apply</button>
                            
                            <div style="margin-left: auto; color: var(--wp-gray-600); font-size: 14px;">
                                <?= $total_posts ?> items
                            </div>
                        </div>

                        <table class="wp-table">
                            <thead>
                                <tr>
                                    <th class="wp-table-checkbox">
                                        <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes(this)">
                                    </th>
                                    <th class="wp-table-title">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'title', 'order' => $orderby === 'title' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Title
                                            <?php if ($orderby === 'title'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-author">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'author_name', 'order' => $orderby === 'author_name' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Author
                                            <?php if ($orderby === 'author_name'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-category">Category</th>
                                    <th class="wp-table-date">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'created_at', 'order' => $orderby === 'created_at' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Date
                                            <?php if ($orderby === 'created_at'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-status">Status</th>
                                    <th class="wp-table-views">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'views', 'order' => $orderby === 'views' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Views
                                            <?php if ($orderby === 'views'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($posts_result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="wp-empty-state">
                                                <i class="fas fa-file-alt"></i>
                                                <h3>No posts found</h3>
                                                <p>
                                                    <?php if ($author_filter || $category_filter || $search): ?>
                                                        No posts match your current filters. <a href="posts.php">Clear filters</a> to see all posts.
                                                    <?php else: ?>
                                                        You haven't created any posts yet.
                                                    <?php endif; ?>
                                                </p>
                                                <a href="create.php">Create your first post</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($post = $posts_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_posts[]" value="<?= $post['id'] ?>" class="post-checkbox">
                                            </td>
                                            <td>
                                                <div class="wp-post-title">
                                                    <a href="edit.php?id=<?= $post['id'] ?>">
                                                        <?= htmlspecialchars($post['title']) ?>
                                                    </a>
                                                </div>
                                                <div class="wp-post-actions">
                                                    <a href="edit.php?id=<?= $post['id'] ?>" class="wp-post-action">Edit</a>
                                                    <a href="../post.php?slug=<?= urlencode($post['slug']) ?>" class="wp-post-action" target="_blank">View</a>
                                                    <a href="delete.php?id=<?= $post['id'] ?>" class="wp-post-action danger"
                                                       onclick="return confirm('Are you sure you want to delete this post?')">Delete</a>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="?author=<?= $post['author_id'] ?>" style="color: var(--wp-blue); text-decoration: none;">
                                                    <?= htmlspecialchars($post['author_name'] ?? 'Unknown') ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($post['category']): ?>
                                                    <a href="?category=<?= urlencode($post['category']) ?>" style="color: var(--wp-blue); text-decoration: none;">
                                                        <?= htmlspecialchars($post['category']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: var(--wp-gray-500);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= date('Y/m/d', strtotime($post['created_at'])) ?></div>
                                                <div style="font-size: 13px; color: var(--wp-gray-500);">
                                                    <?= date('g:i a', strtotime($post['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="wp-status-badge <?= $post['status'] ?>">
                                                    <?= ucfirst($post['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= number_format($post['views']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="wp-pagination">
                                <div class="wp-pagination-info">
                                    Showing <?= ($page - 1) * $posts_per_page + 1 ?> to <?= min($page * $posts_per_page, $total_posts) ?> of <?= $total_posts ?> posts
                                </div>
                                <div class="wp-pagination-links">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['paged' => $page - 1])) ?>" class="wp-pagination-link">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['paged' => $i])) ?>"
                                           class="wp-pagination-link <?= $i === $page ? 'current' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['paged' => $page + 1])) ?>" class="wp-pagination-link">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile Sidebar Toggle
        function toggleSidebar() {
            document.getElementById('wpSidebar').classList.toggle('open');
        }

        // Select All Checkboxes
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.post-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

        // Confirm Bulk Action
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const selected = document.querySelectorAll('.post-checkbox:checked').length;
            
            if (!action) {
                alert('Please select an action.');
                return false;
            }
            
            if (selected === 0) {
                alert('Please select at least one post.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected} post(s)? This action cannot be undone.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${selected} post(s)?`);
        }

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('wpSidebar');
            const toggle = document.querySelector('.wp-mobile-toggle');
            
            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });

        // Auto-hide success messages
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.wp-alert.success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => {
                        successAlert.remove();
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>
