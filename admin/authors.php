<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_authors = $_POST['selected_authors'] ?? [];
    
    if (!empty($selected_authors) && $action === 'delete') {
        $placeholders = str_repeat('?,', count($selected_authors) - 1) . '?';
        
        // First, update posts to remove author association
        $stmt = $conn->prepare("UPDATE posts SET author_id = NULL WHERE author_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_authors)), ...$selected_authors);
        $stmt->execute();
        
        // Then delete authors
        $stmt = $conn->prepare("DELETE FROM authors WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_authors)), ...$selected_authors);
        $stmt->execute();
        
        $success = count($selected_authors) . " authors deleted successfully.";
    }
}

// Get filter parameters
$search = $_GET['s'] ?? '';
$orderby = $_GET['orderby'] ?? 'name';
$order = $_GET['order'] ?? 'ASC';

// Pagination
$page = max(1, (int)($_GET['paged'] ?? 1));
$authors_per_page = 20;
$offset = ($page - 1) * $authors_per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($search) {
    $where_conditions[] = "(authors.name LIKE ? OR authors.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid columns for ordering
$valid_orderby = ['name', 'email', 'post_count', 'created_at'];
$orderby = in_array($orderby, $valid_orderby) ? $orderby : 'name';
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Count total authors
$count_query = "SELECT COUNT(*) as total FROM authors $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_authors = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_authors / $authors_per_page);

// Fetch authors with post count
$authors_query = "SELECT authors.*, COUNT(posts.id) AS post_count, 
                         MAX(posts.created_at) AS last_post_date
                  FROM authors 
                  LEFT JOIN posts ON authors.id = posts.author_id 
                  $where_clause
                  GROUP BY authors.id 
                  ORDER BY $orderby $order 
                  LIMIT ? OFFSET ?";

$authors_stmt = $conn->prepare($authors_query);
$final_params = array_merge($params, [$authors_per_page, $offset]);
$final_types = $types . 'ii';
$authors_stmt->bind_param($final_types, ...$final_params);
$authors_stmt->execute();
$authors_result = $authors_stmt->get_result();
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];

// Get statistics
$total_authors_count = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$active_authors = $conn->query("SELECT COUNT(DISTINCT author_id) as count FROM posts WHERE author_id IS NOT NULL")->fetch_assoc()['count'];
$total_posts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$avg_posts_per_author = $active_authors > 0 ? round($total_posts / $active_authors, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Authors - My CMS Admin</title>
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

.wp-sidebar-header .version {
    font-size: 12px;
    color: var(--wp-gray-500);
    font-weight: 400;
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

        /* Authors Table */
        .wp-authors-table {
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

        .wp-table-name {
            width: 25%;
        }

        .wp-table-email {
            width: 25%;
        }

        .wp-table-posts {
            width: 15%;
        }

        .wp-table-last-post {
            width: 20%;
        }

        .wp-table-actions {
            width: 15%;
        }

        /* Author Info */
        .wp-author-name {
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 5px;
        }

        .wp-author-email {
            color: var(--wp-gray-600);
            font-size: 14px;
        }

        .wp-author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--wp-blue);
            color: var(--wp-white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 15px;
            float: left;
        }

        .wp-author-info {
            overflow: hidden;
        }

        .wp-author-actions {
            display: flex;
            gap: 10px;
            font-size: 13px;
        }

        .wp-author-action {
            color: var(--wp-blue);
            text-decoration: none;
            transition: var(--transition);
        }

        .wp-author-action:hover {
            color: var(--wp-blue-dark);
        }

        .wp-author-action.danger {
            color: var(--wp-error);
        }

        .wp-author-action.danger:hover {
            color: #b32d2e;
        }

        /* Post Count Badge */
        .wp-post-count {
            background: var(--wp-blue);
            color: var(--wp-white);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .wp-post-count.zero {
            background: var(--wp-gray-400);
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
            
            .wp-table-email,
            .wp-table-last-post {
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
                   <a href="authors.php" class="wp-menu-link active">
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
                    <h1>Authors</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="add_author.php" class="wp-button">
                        <i class="fas fa-plus"></i>
                        Add New Author
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

                <!-- Stats Cards -->
                <div class="wp-stats">
                    <div class="wp-stat-card">
                        <i class="fas fa-users wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $total_authors_count ?></div>
                        <div class="wp-stat-label">Total Authors</div>
                    </div>
                    <div class="wp-stat-card success">
                        <i class="fas fa-user-check wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $active_authors ?></div>
                        <div class="wp-stat-label">Active Authors</div>
                    </div>
                    <div class="wp-stat-card warning">
                        <i class="fas fa-file-alt wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $total_posts ?></div>
                        <div class="wp-stat-label">Total Posts</div>
                    </div>
                    <div class="wp-stat-card error">
                        <i class="fas fa-chart-line wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $avg_posts_per_author ?></div>
                        <div class="wp-stat-label">Avg Posts/Author</div>
                    </div>
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
                               placeholder="Search authors..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="wp-search-button">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <!-- Authors Table -->
                <div class="wp-authors-table">
                    <form method="POST" id="bulkForm">
                        <!-- Bulk Actions -->
                        <div class="wp-bulk-actions">
                            <select name="bulk_action" class="wp-bulk-select">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="submit" class="wp-bulk-button" onclick="return confirmBulkAction()">Apply</button>
                            
                            <div style="margin-left: auto; color: var(--wp-gray-600); font-size: 14px;">
                                <?= $total_authors ?> items
                            </div>
                        </div>

                        <table class="wp-table">
                            <thead>
                                <tr>
                                    <th class="wp-table-checkbox">
                                        <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes(this)">
                                    </th>
                                    <th class="wp-table-name">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'name', 'order' => $orderby === 'name' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Author
                                            <?php if ($orderby === 'name'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-email">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'email', 'order' => $orderby === 'email' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Email
                                            <?php if ($orderby === 'email'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-posts">
                                        <a href="?<?= http_build_query(array_merge($_GET, ['orderby' => 'post_count', 'order' => $orderby === 'post_count' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>">
                                            Posts
                                            <?php if ($orderby === 'post_count'): ?>
                                                <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="wp-table-last-post">Last Post</th>
                                    <th class="wp-table-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($authors_result->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--wp-gray-500);">
                                            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                            <div>No authors found</div>
                                            <div style="font-size: 14px; margin-top: 5px;">
                                                <a href="add_author.php" style="color: var(--wp-blue);">Add your first author</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($author = $authors_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_authors[]" value="<?= $author['id'] ?>" class="author-checkbox">
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center;">
                                                    <div class="wp-author-avatar">
                                                        <?= strtoupper(substr($author['name'], 0, 1)) ?>
                                                    </div>
                                                    <div class="wp-author-info">
                                                        <div class="wp-author-name">
                                                            <?= htmlspecialchars($author['name']) ?>
                                                        </div>
                                                        <div class="wp-author-actions">
                                                            <a href="edit_author.php?id=<?= $author['id'] ?>" class="wp-author-action">Edit</a>
                                                            <a href="delete_author.php?id=<?= $author['id'] ?>" class="wp-author-action danger"
                                                               onclick="return confirm('Are you sure you want to delete this author?')">Delete</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="wp-author-email">
                                                <?= htmlspecialchars($author['email']) ?>
                                            </td>
                                            <td>
                                                <span class="wp-post-count <?= $author['post_count'] == 0 ? 'zero' : '' ?>">
                                                    <?= $author['post_count'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($author['last_post_date']): ?>
                                                    <div><?= date('Y/m/d', strtotime($author['last_post_date'])) ?></div>
                                                    <div style="font-size: 13px; color: var(--wp-gray-500);">
                                                        <?= date('g:i a', strtotime($author['last_post_date'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--wp-gray-500);">No posts yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 10px;">
                                                    <a href="edit_author.php?id=<?= $author['id'] ?>" class="wp-author-action" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="posts.php?author=<?= urlencode($author['name']) ?>" class="wp-author-action" title="View Posts">
                                                        <i class="fas fa-file-alt"></i>
                                                    </a>
                                                    <a href="delete_author.php?id=<?= $author['id'] ?>" class="wp-author-action danger"
                                                       onclick="return confirm('Are you sure you want to delete this author?')" title="Delete">
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
                        <?php if ($total_pages > 1): ?>
                            <div class="wp-pagination">
                                <div class="wp-pagination-info">
                                    Showing <?= ($page - 1) * $authors_per_page + 1 ?> to <?= min($page * $authors_per_page, $total_authors) ?> of <?= $total_authors ?> authors
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
            const checkboxes = document.querySelectorAll('.author-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

        // Confirm Bulk Action
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const selected = document.querySelectorAll('.author-checkbox:checked').length;
            
            if (!action) {
                alert('Please select an action.');
                return false;
            }
            
            if (selected === 0) {
                alert('Please select at least one author.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected} author(s)? This will also remove their association with posts.`);
            }
            
            return confirm(`Are you sure you want to ${action} ${selected} author(s)?`);
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
