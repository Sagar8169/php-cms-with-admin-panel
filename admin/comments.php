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
    $selected_comments = $_POST['selected_comments'] ?? [];
    
    if (!empty($selected_comments) && in_array($action, ['approve', 'unapprove', 'spam', 'delete'])) {
        $placeholders = str_repeat('?,', count($selected_comments) - 1) . '?';
        
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM comments WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_comments)), ...$selected_comments);
            $stmt->execute();
            $success = count($selected_comments) . " comments deleted successfully.";
        } else {
            $status = $action === 'approve' ? 'approved' : ($action === 'unapprove' ? 'pending' : 'spam');
            $stmt = $conn->prepare("UPDATE comments SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$status], $selected_comments);
            $types = 's' . str_repeat('i', count($selected_comments));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $success = count($selected_comments) . " comments updated to " . $status . ".";
        }
    }
}

// Handle individual comment actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $comment_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if (in_array($action, ['approve', 'unapprove', 'spam', 'delete'])) {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
            $success = "Comment deleted successfully.";
        } else {
            $status = $action === 'approve' ? 'approved' : ($action === 'unapprove' ? 'pending' : 'spam');
            $stmt = $conn->prepare("UPDATE comments SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $comment_id);
            $stmt->execute();
            $success = "Comment " . $status . " successfully.";
        }
    }
    
    header("Location: comments.php");
    exit;
}

$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['s'] ?? '';
$orderby = $_GET['orderby'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Pagination
$page = max(1, (int)($_GET['paged'] ?? 1));
$comments_per_page = 20;
$offset = ($page - 1) * $comments_per_page;
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "comments.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(comments.author_name LIKE ? OR comments.content LIKE ? OR posts.title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid columns for ordering
$valid_orderby = ['author_name', 'created_at', 'status'];
$orderby = in_array($orderby, $valid_orderby) ? $orderby : 'created_at';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Count total comments
$count_query = "SELECT COUNT(*) as total FROM comments LEFT JOIN posts ON comments.post_id = posts.id $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_comments = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_comments / $comments_per_page);

// Fetch comments
$comments_query = "SELECT comments.*, posts.title as post_title, posts.slug as post_slug 
                   FROM comments 
                   LEFT JOIN posts ON comments.post_id = posts.id 
                   $where_clause 
                   ORDER BY comments.$orderby $order 
                   LIMIT ? OFFSET ?";

$comments_stmt = $conn->prepare($comments_query);
$final_params = array_merge($params, [$comments_per_page, $offset]);
$final_types = $types . 'ii';
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$comments_stmt->bind_param($final_types, ...$final_params);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

// Get status counts
$status_counts = [];
$status_query = "SELECT status, COUNT(*) as count FROM comments GROUP BY status";
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
    <title>Comments - My CMS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
.wp-sidebar-header .version {
    font-size: 12px;
    color: var(--wp-gray-500);
    font-weight: 400;
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

        /* Stats Cards */
        .wp-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .wp-stat-card {
            background: var(--wp-white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border-left: 4px solid var(--wp-blue);
            transition: var(--transition);
        }

        .wp-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .wp-stat-card.success {
            border-left-color: var(--wp-success);
        }

        .wp-stat-card.warning {
            border-left-color: var(--wp-warning);
        }

        .wp-stat-card.error {
            border-left-color: var(--wp-error);
        }

        .wp-stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--wp-gray-900);
            margin-bottom: 5px;
        }

        .wp-stat-label {
            color: var(--wp-gray-600);
            font-size: 14px;
            font-weight: 500;
        }

        .wp-stat-icon {
            float: right;
            font-size: 24px;
            color: var(--wp-gray-400);
            margin-top: -5px;
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

        /* Comments Table */
        .wp-comments-table {
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

        .wp-table-checkbox {
            width: 40px;
        }

        .wp-table-author {
            width: 20%;
        }

        .wp-table-comment {
            width: 40%;
        }

        .wp-table-post {
            width: 20%;
        }

        .wp-table-date {
            width: 15%;
        }

        .wp-table-status {
            width: 10%;
        }

        /* Comment Content */
        .wp-comment-author {
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 5px;
        }

        .wp-comment-email {
            font-size: 13px;
            color: var(--wp-gray-600);
        }

        .wp-comment-content {
            color: var(--wp-gray-700);
            line-height: 1.5;
            margin-bottom: 10px;
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }

        .wp-comment-content.expanded {
            max-height: none;
        }

        .wp-comment-actions {
            display: flex;
            gap: 10px;
            font-size: 13px;
        }

        .wp-comment-action {
            color: var(--wp-blue);
            text-decoration: none;
            transition: var(--transition);
        }

        .wp-comment-action:hover {
            color: var(--wp-blue-dark);
        }

        .wp-comment-action.danger {
            color: var(--wp-error);
        }

        .wp-comment-action.danger:hover {
            color: #b32d2e;
        }

        .wp-comment-action.success {
            color: var(--wp-success);
        }

        .wp-comment-action.success:hover {
            color: #008a20;
        }

        /* Status Badge */
        .wp-status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .wp-status-badge.approved {
            background: var(--wp-success);
            color: var(--wp-white);
        }

        .wp-status-badge.pending {
            background: var(--wp-warning);
            color: var(--wp-white);
        }

       

        /* Post Link */
        .wp-post-link {
            color: var(--wp-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .wp-post-link:hover {
            text-decoration: underline;
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
            
            .wp-table-post {
                display: none;
            }
            
            .wp-stats {
                grid-template-columns: repeat(2, 1fr);
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

        /* Expand/Collapse Comment */
        .wp-expand-toggle {
            color: var(--wp-blue);
            cursor: pointer;
            font-size: 12px;
            margin-top: 5px;
        }

        .wp-expand-toggle:hover {
            text-decoration: underline;
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
        <a href="posts.php" class="wp-menu-link">
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
        <a href="comments.php" class="wp-menu-link active">
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
                    <h1>Comments</h1>
                </div>
                
                <div class="wp-header-actions">
                    <span style="color: var(--wp-gray-600); font-size: 14px;">
                        Manage and moderate user comments
                    </span>
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

                <!-- Stats Cards -->
                <div class="wp-stats">
                    <div class="wp-stat-card">
                        <i class="fas fa-comments wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $status_counts['all'] ?? 0 ?></div>
                        <div class="wp-stat-label">Total Comments</div>
                    </div>
                    <div class="wp-stat-card success">
                        <i class="fas fa-check-circle wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $status_counts['approved'] ?? 0 ?></div>
                        <div class="wp-stat-label">Approved</div>
                    </div>
                    <div class="wp-stat-card warning">
                        <i class="fas fa-clock wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $status_counts['pending'] ?? 0 ?></div>
                        <div class="wp-stat-label">Pending</div>
                    </div>
                    
                </div>

                <!-- Status Filters -->
                <div class="wp-status-filters">
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'all'])) ?>"
                       class="wp-status-filter <?= $status_filter === 'all' ? 'current' : '' ?>">
                        All <span class="wp-status-count">(<?= $status_counts['all'] ?? 0 ?>)</span>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'approved'])) ?>"
                       class="wp-status-filter <?= $status_filter === 'approved' ? 'current' : '' ?>">
                        Approved <span class="wp-status-count">(<?= $status_counts['approved'] ?? 0 ?>)</span>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'pending'])) ?>"
                       class="wp-status-filter <?= $status_filter === 'pending' ? 'current' : '' ?>">
                        Pending <span class="wp-status-count">(<?= $status_counts['pending'] ?? 0 ?>)</span>
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
                               placeholder="Search comments..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="wp-search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <!-- Comments Table -->
                <div class="wp-comments-table">
                    <form method="POST" id="bulkForm">
                        <!-- Bulk Actions -->
                        <div class="wp-bulk-actions">
                            <select name="bulk_action" class="wp-bulk-select">
                                <option value="">Bulk Actions</option>
                                <option value="approve">Approve</option>
                                <option value="unapprove">Unapprove</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="submit" class="wp-bulk-button" onclick="return confirmBulkAction()">Apply</button>
                            
                            <div style="margin-left: auto; color: var(--wp-gray-600); font-size: 14px;">
                                <?= $total_comments ?> items
                            </div>
                        </div>

                        <table class="wp-table">
                            <thead>
                                <tr>
                                    <th class="wp-table-checkbox">
                                        <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes(this)">
                                    </th>
                                    <th class="wp-table-author">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'author_name', 'order' => $orderby === 'author_name' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Author
                                            <?php if ($orderby === 'author_name'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-comment">Comment</th>
                                    <th class="wp-table-post">In Response To</th>
                                    <th class="wp-table-date">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'created_at', 'order' => $orderby === 'created_at' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Date
                                            <?php if ($orderby === 'created_at'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-status">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($comments_result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--wp-gray-500);">
                                            <i class="fas fa-comments" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                            <div>No comments found</div>
                                            <div style="font-size: 14px; margin-top: 5px;">
                                                Comments will appear here when users leave them on your posts.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($comment = $comments_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_comments[]" value="<?= $comment['id'] ?>" class="comment-checkbox">
                                            </td>
                                            <td>
                                                <div class="wp-comment-author">
                                                    <?= htmlspecialchars($comment['author_name']) ?>
                                                </div>
                                                <div class="wp-comment-email">
                                                    <?= htmlspecialchars($comment['author_email']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="wp-comment-content" id="comment-<?= $comment['id'] ?>">
                                                    <?= nl2br(htmlspecialchars(substr($comment['content'], 0, 200))) ?>
                                                    <?php if (strlen($comment['content']) > 200): ?>
                                                        <span class="wp-expand-toggle" onclick="toggleComment(<?= $comment['id'] ?>)">
                                                            ... Show more
                                                        </span>
                                                        <div style="display: none;">
                                                            <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="wp-comment-actions">
                                                    <?php if ($comment['status'] === 'pending'): ?>
                                                        <a href="?action=approve&id=<?= $comment['id'] ?>" class="wp-comment-action success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </a>
                                                    <?php elseif ($comment['status'] === 'approved'): ?>
                                                        <a href="?action=unapprove&id=<?= $comment['id'] ?>" class="wp-comment-action">
                                                            <i class="fas fa-times"></i> Unapprove
                                                        </a>
                                                    <?php endif; ?>
                                                 
                                                    
                                                    <a href="?action=delete&id=<?= $comment['id'] ?>" class="wp-comment-action danger"
                                                       onclick="return confirm('Are you sure you want to delete this comment?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($comment['post_title']): ?>
                                                    <a href="../post.php?slug=<?= urlencode($comment['post_slug']) ?>"
                                                       class="wp-post-link" target="_blank">
                                                        <?= htmlspecialchars($comment['post_title']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: var(--wp-gray-500);">Post not found</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= date('Y/m/d', strtotime($comment['created_at'])) ?></div>
                                                <div style="font-size: 13px; color: var(--wp-gray-500);">
                                                    <?= date('g:i a', strtotime($comment['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="wp-status-badge <?= $comment['status'] ?>">
                                                    <?= ucfirst($comment['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="wp-pagination">
                                <div class="wp-pagination-info">
                                    Showing <?= ($page - 1) * $comments_per_page + 1 ?> to <?= min($page * $comments_per_page, $total_comments) ?> of <?= $total_comments ?> comments
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
            const checkboxes = document.querySelectorAll('.comment-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

        // Confirm Bulk Action
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const selected = document.querySelectorAll('.comment-checkbox:checked').length;
            
            if (!action) {
                alert('Please select an action.');
                return false;
            }
            
            if (selected === 0) {
                alert('Please select at least one comment.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected} comment(s)? This action cannot be undone.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${selected} comment(s)?`);
        }

        // Toggle Comment Expansion
        function toggleComment(commentId) {
            const commentDiv = document.getElementById('comment-' + commentId);
            const toggle = commentDiv.querySelector('.wp-expand-toggle');
            const fullContent = commentDiv.querySelector('div[style="display: none;"]');
            
            if (fullContent.style.display === 'none') {
                fullContent.style.display = 'block';
                toggle.textContent = '... Show less';
                commentDiv.firstChild.style.display = 'none';
            } else {
                fullContent.style.display = 'none';
                toggle.textContent = '... Show more';
                commentDiv.firstChild.style.display = 'block';
            }
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
    </script>
</body>
</html>
