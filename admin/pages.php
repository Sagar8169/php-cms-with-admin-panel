<?php

session_start();

error_reporting(E_ALL);

ini_set('display_errors', 1);

if (!isset($_SESSION['admin'])) {

    header("Location: login.php");

    exit;

}

require 'config.php';

// Handle AJAX request for drag and drop reordering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    header('Content-Type: application/json');
    
    $page_orders = json_decode($_POST['page_orders'], true);
    
    if ($page_orders) {
        foreach ($page_orders as $page_id => $order) {
            $stmt = $conn->prepare("UPDATE pages SET page_order = ? WHERE id = ?");
            $stmt->bind_param("ii", $order, $page_id);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Handle bulk actions

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {

    $action = $_POST['bulk_action'];

    $selected_pages = $_POST['selected_pages'] ?? [];

    

    if (!empty($selected_pages) && in_array($action, ['delete', 'published', 'draft'])) {

        $placeholders = str_repeat('?,', count($selected_pages) - 1) . '?';

        

        if ($action === 'delete') {

            $stmt = $conn->prepare("DELETE FROM pages WHERE id IN ($placeholders)");

            $stmt->bind_param(str_repeat('i', count($selected_pages)), ...$selected_pages);

            $stmt->execute();

            $success = count($selected_pages) . " pages deleted successfully.";

        } else {

            $stmt = $conn->prepare("UPDATE pages SET status = ? WHERE id IN ($placeholders)");

            $params = array_merge([$action], $selected_pages);

            $types = 's' . str_repeat('i', count($selected_pages));

            $stmt->bind_param($types, ...$params);

            $stmt->execute();

            $success = count($selected_pages) . " pages updated to " . $action . ".";

        }

    }

}

$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];

// Get filter parameters

$status_filter = $_GET['status'] ?? 'all';

$search = $_GET['s'] ?? '';

$orderby = $_GET['orderby'] ?? 'page_order';

$order = $_GET['order'] ?? 'ASC';

// Pagination

$page = max(1, (int)($_GET['paged'] ?? 1));

$pages_per_page = 20;

$offset = ($page - 1) * $pages_per_page;

// Build WHERE clause

$where_conditions = [];

$params = [];

$types = '';

if ($status_filter !== 'all') {

    $where_conditions[] = "status = ?";

    $params[] = $status_filter;

    $types .= 's';

}

if ($search) {

    $where_conditions[] = "(title LIKE ? OR content LIKE ? OR slug LIKE ?)";

    $search_param = "%$search%";

    $params[] = $search_param;

    $params[] = $search_param;

    $params[] = $search_param;

    $types .= 'sss';

}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];

// Valid columns for ordering

$valid_orderby = ['title', 'created_at', 'status', 'slug', 'page_order'];

$orderby = in_array($orderby, $valid_orderby) ? $orderby : 'page_order';

$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Count total pages

$count_query = "SELECT COUNT(*) as total FROM pages $where_clause";

$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {

    $count_stmt->bind_param($types, ...$params);

}

$count_stmt->execute();

$total_pages_count = $count_stmt->get_result()->fetch_assoc()['total'];

$total_pages_pagination = ceil($total_pages_count / $pages_per_page);

// Fetch all posts with authors

$posts = $conn->query("SELECT posts.*, authors.name AS author_name FROM posts LEFT JOIN authors ON posts.author_id = authors.id ORDER BY posts.created_at DESC");

// Fetch pages

$pages_query = "SELECT * FROM pages $where_clause ORDER BY $orderby $order LIMIT ? OFFSET ?";

$pages_stmt = $conn->prepare($pages_query);

$final_params = array_merge($params, [$pages_per_page, $offset]);

$final_types = $types . 'ii';

$pages_stmt->bind_param($final_types, ...$final_params);

$pages_stmt->execute();

$pages_result = $pages_stmt->get_result();

$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];

$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];

$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];

// Get status counts

$status_counts = [];

$status_query = "SELECT status, COUNT(*) as count FROM pages GROUP BY status";

$status_result = $conn->query($status_query);

while ($row = $status_result->fetch_assoc()) {

    $status_counts[$row['status']] = $row['count'];

}

$status_counts['all'] = array_sum($status_counts);

// Get total posts count for sidebar badge

$total_posts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Pages - My CMS Admin</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

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

        /* Content Area */

        .wp-content {

            padding: 30px;

        }

        /* Drag and Drop Order Section */
        .wp-order-section {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .wp-order-header {
            background: linear-gradient(135deg, var(--wp-blue), var(--wp-blue-dark));
            color: var(--wp-white);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .wp-order-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-order-toggle {
            background: rgba(255,255,255,0.2);
            border: none;
            color: var(--wp-white);
            padding: 8px 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
        }

        .wp-order-toggle:hover {
            background: rgba(255,255,255,0.3);
        }

        .wp-order-content {
            padding: 20px;
            display: none;
        }

        .wp-order-content.active {
            display: block;
        }

        .wp-order-instructions {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
            color: #0066cc;
        }

        .wp-sortable-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .wp-sortable-item {
            background: var(--wp-white);
            border: 2px solid var(--wp-gray-200);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            padding: 15px;
            cursor: move;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .wp-sortable-item:hover {
            border-color: var(--wp-blue);
            box-shadow: 0 2px 8px rgba(0,115,170,0.1);
        }

        .wp-sortable-item.sortable-ghost {
            opacity: 0.5;
            background: var(--wp-gray-100);
        }

        .wp-sortable-item.sortable-chosen {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,115,170,0.2);
        }

        .wp-drag-handle {
            color: var(--wp-gray-400);
            font-size: 18px;
            cursor: grab;
        }

        .wp-drag-handle:active {
            cursor: grabbing;
        }

        .wp-page-info {
            flex: 1;
        }

        .wp-page-title-sort {
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 5px;
        }

        .wp-page-meta {
            font-size: 13px;
            color: var(--wp-gray-500);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .wp-order-number {
            background: var(--wp-blue);
            color: var(--wp-white);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .wp-menu-visibility {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wp-visibility-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .wp-visibility-badge.visible {
            background: var(--wp-success);
            color: var(--wp-white);
        }

        .wp-visibility-badge.hidden {
            background: var(--wp-gray-400);
            color: var(--wp-white);
        }

        .wp-save-order {
            background: var(--wp-success);
            color: var(--wp-white);
            border: none;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: none;
        }

        .wp-save-order:hover {
            background: #008a00;
            transform: translateY(-1px);
        }

        .wp-save-order.show {
            display: inline-block;
        }

        /* Status Filters */

        .wp-status-filters {

            display: flex;

            gap: 20px;

            margin-bottom: 20px;

            flex-wrap: wrap;

        }

        .wp-status-filter {

            color: var(--wp-blue);

            text-decoration: none;

            font-weight: 500;

            padding: 5px 0;

            border-bottom: 2px solid transparent;

            transition: var(--transition);

        }

        .wp-status-filter:hover,

        .wp-status-filter.current {

            border-bottom-color: var(--wp-blue);

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

            display: flex;

            align-items: center;

            gap: 15px;

            flex-wrap: wrap;

        }

        .wp-search-box {

            position: relative;

            flex: 1;

            min-width: 200px;

        }

        .wp-search-input {

            width: 100%;

            padding: 8px 35px 8px 12px;

            border: 1px solid var(--wp-gray-300);

            border-radius: var(--border-radius);

            font-size: 14px;

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

        }

        /* Pages Table */

        .wp-pages-table {

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

.wp-sidebar-header .version {

    font-size: 12px;

    color: var(--wp-gray-500);

    font-weight: 400;

}

        .wp-table-checkbox {

            width: 40px;

        }

        .wp-table-title {

            width: 35%;

        }

        .wp-table-slug {

            width: 25%;

        }

        .wp-table-date {

            width: 15%;

        }

        .wp-table-status {

            width: 10%;

        }

        .wp-table-actions {

            width: 15%;

        }

        /* Page Title */

        .wp-page-title {

            font-weight: 600;

            color: var(--wp-gray-900);

            margin-bottom: 5px;

        }

        .wp-page-title a {

            color: inherit;

            text-decoration: none;

        }

        .wp-page-title a:hover {

            color: var(--wp-blue);

        }

        .wp-page-actions {

            display: flex;

            gap: 10px;

            font-size: 13px;

        }

        .wp-page-action {

            color: var(--wp-blue);

            text-decoration: none;

            transition: var(--transition);

        }

        .wp-page-action:hover {

            color: var(--wp-blue-dark);

        }

        .wp-page-action.danger {

            color: var(--wp-error);

        }

        .wp-page-action.danger:hover {

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

        /* Slug Display */

        .wp-page-slug {

            font-family: 'Courier New', monospace;

            background: var(--wp-gray-100);

            padding: 2px 6px;

            border-radius: 3px;

            font-size: 13px;

            color: var(--wp-gray-700);

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

        }

        .wp-bulk-button {

            padding: 6px 12px;

            background: var(--wp-white);

            border: 1px solid var(--wp-gray-300);

            border-radius: var(--border-radius);

            font-size: 13px;

            cursor: pointer;

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

        }

        .wp-pagination-link.disabled {

            color: var(--wp-gray-400);

            cursor: not-allowed;

        }

        /* Alert Messages */

        .wp-alert {

            padding: 15px;

            border-radius: var(--border-radius);

            margin-bottom: 20px;

            display: flex;

            align-items: center;

            gap: 10px;

        }

        .wp-alert.success {

            background: #d4edda;

            color: #155724;

            border: 1px solid #c3e6cb;

        }

        .wp-alert.error {

            background: #f8d7da;

            color: #721c24;

            border: 1px solid #f5c6cb;

        }

        /* Mobile Responsive */

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

            

            .wp-header {

                padding: 15px 20px;

            }

            

            .wp-content {

                padding: 20px;

            }

            

            .wp-filters-bar {

                flex-direction: column;

                align-items: stretch;

            }

            

            .wp-table {

                font-size: 14px;

            }

            

            .wp-table th,

            .wp-table td {

                padding: 10px 8px;

            }

            

            .wp-table-slug {

                display: none;

            }

            .wp-order-content {
                padding: 15px;
            }

            .wp-sortable-item {
                padding: 12px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .wp-page-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

        }

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

            .wp-mobile-toggle {

                display: block;

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

</h1>      </div>

            

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

        <a href="posts.php" class="wp-menu-link">

            <i class="fas fa-file-alt wp-menu-icon"></i>

            All Posts

            <span class="wp-menu-badge"><?= $totalPosts ?></span>

        </a>

    </li>

<li class="wp-menu-item">

                    <a href="pages.php" class="wp-menu-link active">

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

            Settings

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

                    <h1>Pages</h1>

                </div>

                

                <div class="wp-header-actions">

                    <a href="add_page.php" class="wp-button">

                        <i class="fas fa-plus"></i>

                        Add New Page

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

                <!-- Drag and Drop Menu Order Section -->
                <div class="wp-order-section">
                    <div class="wp-order-header">
                        <h3>
                            <i class="fas fa-sort"></i>
                            Menu Order Management
                        </h3>
                        <button class="wp-order-toggle" onclick="toggleOrderSection()">
                            <i class="fas fa-chevron-down" id="orderToggleIcon"></i>
                            Toggle
                        </button>
                    </div>
                    <div class="wp-order-content" id="orderContent">
                        <div class="wp-order-instructions">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instructions:</strong> Drag and drop pages to reorder them in the navigation menu. Only pages marked as "Show in Menu" will appear in the frontend navigation. Changes are saved automatically.
                        </div>
                        
                        <ul class="wp-sortable-list" id="sortablePages">
                            <?php
                            // Fetch all pages for drag and drop ordering
                            $order_pages = $conn->query("SELECT * FROM pages ORDER BY page_order ASC, id ASC");
                            $order_counter = 1;
                            while ($order_page = $order_pages->fetch_assoc()):
                            ?>
                                <li class="wp-sortable-item" data-id="<?= $order_page['id'] ?>">
                                    <div class="wp-drag-handle">
                                        <i class="fas fa-grip-vertical"></i>
                                    </div>
                                    <div class="wp-order-number"><?= $order_counter ?></div>
                                    <div class="wp-page-info">
                                        <div class="wp-page-title-sort"><?= htmlspecialchars($order_page['title']) ?></div>
                                        <div class="wp-page-meta">
                                            <span><i class="fas fa-link"></i> <?= htmlspecialchars($order_page['slug']) ?></span>
                                            <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($order_page['created_at'])) ?></span>
                                            <span class="wp-status-badge <?= $order_page['status'] ?? 'published' ?>">
                                                <?= ucfirst($order_page['status'] ?? 'Published') ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="wp-menu-visibility">
                                        <span class="wp-visibility-badge <?= ($order_page['show_in_menu'] ?? 1) ? 'visible' : 'hidden' ?>">
                                            <?= ($order_page['show_in_menu'] ?? 1) ? 'Visible' : 'Hidden' ?>
                                        </span>
                                        <i class="fas fa-<?= ($order_page['show_in_menu'] ?? 1) ? 'eye' : 'eye-slash' ?>"></i>
                                    </div>
                                </li>
                            <?php
                            $order_counter++;
                            endwhile;
                            ?>
                        </ul>
                        
                        <button class="wp-save-order" id="saveOrderBtn" onclick="saveOrder()">
                            <i class="fas fa-save"></i>
                            Save Order
                        </button>
                    </div>
                </div>

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

                    <form method="GET" class="wp-search-box">

                        <?php foreach ($_GET as $key => $value): ?>

                            <?php if ($key !== 's'): ?>

                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">

                            <?php endif; ?>

                        <?php endforeach; ?>

                        <input type="text" name="s" class="wp-search-input"

                               placeholder="Search pages..." value="<?= htmlspecialchars($search) ?>">

                        <button type="submit" class="wp-search-button">

                            <i class="fas fa-search"></i>

                        </button>

                    </form>

                </div>

                <!-- Pages Table -->

                <div class="wp-pages-table">

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

                                <?= $total_pages_count ?> items

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

                                    <th class="wp-table-slug">

                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'slug', 'order' => $orderby === 'slug' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">

                                            Slug

                                            <?php if ($orderby === 'slug'): ?>

                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>

                                            <?php endif; ?>

                                        </a>

                                    </th>

                                    <th class="wp-table-date">

                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'created_at', 'order' => $orderby === 'created_at' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">

                                            Date

                                            <?php if ($orderby === 'created_at'): ?>

                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>

                                            <?php endif; ?>

                                        </a>

                                    </th>

                                    <th class="wp-table-status">Status</th>

                                    <th class="wp-table-actions">Actions</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if ($pages_result->num_rows === 0): ?>

                                    <tr>

                                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--wp-gray-500);">

                                            <i class="fas fa-copy" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>

                                            <div>No pages found</div>

                                            <div style="font-size: 14px; margin-top: 5px;">

                                                <a href="add_page.php" style="color: var(--wp-blue);">Create your first page</a>

                                            </div>

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php while ($page_row = $pages_result->fetch_assoc()): ?>

                                        <tr>

                                            <td>

                                                <input type="checkbox" name="selected_pages[]" value="<?= $page_row['id'] ?>" class="page-checkbox">

                                            </td>

                                            <td>

                                                <div class="wp-page-title">

                                                    <a href="edit_page.php?id=<?= $page_row['id'] ?>">

                                                        <?= htmlspecialchars($page_row['title']) ?>

                                                    </a>

                                                </div>

                                                <div class="wp-page-actions">

                                                    <a href="edit_page.php?id=<?= $page_row['id'] ?>" class="wp-page-action">Edit</a>

                                                    <a href="../page.php?slug=<?= urlencode($page_row['slug']) ?>" class="wp-page-action" target="_blank">View</a>

                                                    <a href="delete_page.php?id=<?= $page_row['id'] ?>" class="wp-page-action danger"

                                                       onclick="return confirm('Are you sure you want to delete this page?')">Delete</a>

                                                </div>

                                            </td>

                                            <td>

                                                <span class="wp-page-slug"><?= htmlspecialchars($page_row['slug']) ?></span>

                                            </td>

                                            <td>

                                                <div><?= date('Y/m/d', strtotime($page_row['created_at'])) ?></div>

                                                <div style="font-size: 13px; color: var(--wp-gray-500);">

                                                    <?= date('g:i a', strtotime($page_row['created_at'])) ?>

                                                </div>

                                            </td>

                                            <td>

                                                <span class="wp-status-badge <?= $page_row['status'] ?? 'published' ?>">

                                                    <?= ucfirst($page_row['status'] ?? 'Published') ?>

                                                </span>

                                            </td>

                                            <td>

                                                <div style="display: flex; gap: 10px;">

                                                    <a href="edit_page.php?id=<?= $page_row['id'] ?>" class="wp-page-action" title="Edit">

                                                        <i class="fas fa-edit"></i>

                                                    </a>

                                                    <a href="../page.php?slug=<?= urlencode($page_row['slug']) ?>" class="wp-page-action" target="_blank" title="View">

                                                        <i class="fas fa-external-link-alt"></i>

                                                    </a>

                                                    <a href="delete_page.php?id=<?= $page_row['id'] ?>" class="wp-page-action danger"

                                                       onclick="return confirm('Are you sure you want to delete this page?')" title="Delete">

                                                        <i class="fas fa-trash"></i>

                                                    </a>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                        <!-- Pagination -->

                        <?php if ($total_pages_pagination > 1): ?>

                            <div class="wp-pagination">

                                <div class="wp-pagination-info">

                                    Showing <?= ($page - 1) * $pages_per_page + 1 ?> to <?= min($page * $pages_per_page, $total_pages_count) ?> of <?= $total_pages_count ?> pages

                                </div>

                                <div class="wp-pagination-links">

                                    <?php if ($page > 1): ?>

                                        <a href="?<?= http_build_query(array_merge($_GET, ['paged' => $page - 1])) ?>" class="wp-pagination-link">

                                            <i class="fas fa-chevron-left"></i> Previous

                                        </a>

                                    <?php endif; ?>

                                    

                                    <?php

                                    $start = max(1, $page - 2);

                                    $end = min($total_pages_pagination, $page + 2);

                                    

                                    for ($i = $start; $i <= $end; $i++):

                                    ?>

                                        <a href="?<?= http_build_query(array_merge($_GET, ['paged' => $i])) ?>"

                                           class="wp-pagination-link <?= $i === $page ? 'current' : '' ?>">

                                            <?= $i ?>

                                        </a>

                                    <?php endfor; ?>

                                    

                                    <?php if ($page < $total_pages_pagination): ?>

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

            const checkboxes = document.querySelectorAll('.page-checkbox');

            checkboxes.forEach(checkbox => {

                checkbox.checked = source.checked;

            });

        }

        // Confirm Bulk Action

        function confirmBulkAction() {

            const action = document.querySelector('select[name="bulk_action"]').value;

            const selected = document.querySelectorAll('.page-checkbox:checked').length;

            

            if (!action) {

                alert('Please select an action.');

                return false;

            }

            

            if (selected === 0) {

                alert('Please select at least one page.');

                return false;

            }

            

            if (action === 'delete') {

                return confirm(`Are you sure you want to delete ${selected} page(s)? This action cannot be undone.`);

            }

            

            return confirm(`Are you sure you want to ${action} ${selected} page(s)?`);

        }

        // Toggle Order Section
        function toggleOrderSection() {
            const content = document.getElementById('orderContent');
            const icon = document.getElementById('orderToggleIcon');
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.className = 'fas fa-chevron-down';
            } else {
                content.classList.add('active');
                icon.className = 'fas fa-chevron-up';
            }
        }

        // Initialize Sortable
        let sortable;
        let hasChanges = false;

        document.addEventListener('DOMContentLoaded', function() {
            const sortableList = document.getElementById('sortablePages');
            
            sortable = Sortable.create(sortableList, {
                handle: '.wp-drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
                    updateOrderNumbers();
                    showSaveButton();
                    hasChanges = true;
                }
            });
        });

        // Update order numbers after drag
        function updateOrderNumbers() {
            const items = document.querySelectorAll('.wp-sortable-item');
            items.forEach((item, index) => {
                const orderNumber = item.querySelector('.wp-order-number');
                orderNumber.textContent = index + 1;
            });
        }

        // Show save button
        function showSaveButton() {
            document.getElementById('saveOrderBtn').classList.add('show');
        }

        // Save order via AJAX
        function saveOrder() {
            const items = document.querySelectorAll('.wp-sortable-item');
            const pageOrders = {};
            
            items.forEach((item, index) => {
                const pageId = item.getAttribute('data-id');
                pageOrders[pageId] = index + 1;
            });

            // Show loading state
            const saveBtn = document.getElementById('saveOrderBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;

            // Send AJAX request
            fetch('pages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=reorder&page_orders=${encodeURIComponent(JSON.stringify(pageOrders))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success feedback
                    saveBtn.innerHTML = '<i class="fas fa-check"></i> Saved!';
                    saveBtn.style.background = 'var(--wp-success)';
                    
                    // Hide save button after 2 seconds
                    setTimeout(() => {
                        saveBtn.classList.remove('show');
                        saveBtn.innerHTML = originalText;
                        saveBtn.style.background = '';
                        saveBtn.disabled = false;
                        hasChanges = false;
                    }, 2000);

                    // Show success message
                    showNotification('Page order updated successfully!', 'success');
                } else {
                    throw new Error('Failed to save order');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
                saveBtn.style.background = 'var(--wp-error)';
                
                setTimeout(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.style.background = '';
                    saveBtn.disabled = false;
                }, 3000);

                showNotification('Failed to save page order. Please try again.', 'error');
            });
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `wp-alert ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;
            
            const content = document.querySelector('.wp-content');
            content.insertBefore(notification, content.firstChild);
            
            // Remove notification after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Warn user about unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes to the page order. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

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

    </script>

</body>

</html>
