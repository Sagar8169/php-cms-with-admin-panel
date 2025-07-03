<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

// Add views column if it doesn't exist
$conn->query("ALTER TABLE posts ADD COLUMN IF NOT EXISTS views INT DEFAULT 0");

// Fetch all authors
$authors = $conn->query("SELECT id, name FROM authors ORDER BY name");

// Fetch all posts with authors
$posts = $conn->query("SELECT posts.*, authors.name AS author_name FROM posts LEFT JOIN authors ON posts.author_id = authors.id ORDER BY posts.created_at DESC");

// Get statistics - Fixed views calculation
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalViews = $conn->query("SELECT COALESCE(SUM(views), 0) as count FROM posts")->fetch_assoc()['count'];

$popularPost = $conn->query("SELECT title, views FROM posts ORDER BY views DESC LIMIT 1")->fetch_assoc();

// Get recent activity
$recentPosts = $conn->query("SELECT title, created_at, status FROM posts ORDER BY created_at DESC LIMIT 5");
$recentComments = $conn->query("SELECT c.author_name, c.content, c.created_at, p.title FROM comments c JOIN posts p ON c.post_id = p.id ORDER BY c.created_at DESC LIMIT 5");

// Get filter values
$filterAuthor = $_GET['author'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterFrom = $_GET['from'] ?? '';
$filterTo = $_GET['to'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterTag = $_GET['tag'] ?? '';

// Build filter query
$where = [];
$params = [];
$types = '';

if ($filterAuthor) {
    $where[] = "authors.name = ?";
    $params[] = $filterAuthor;
    $types .= 's';
}

if ($filterStatus) {
    $where[] = "posts.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterFrom) {
    $where[] = "posts.created_at >= ?";
    $params[] = $filterFrom;
    $types .= 's';
}

if ($filterTo) {
    $where[] = "posts.created_at <= ?";
    $params[] = $filterTo;
    $types .= 's';
}

if ($filterCategory) {
    $where[] = "posts.category = ?";
    $params[] = $filterCategory;
    $types .= 's';
}

if ($filterTag) {
    $where[] = "posts.tags LIKE ?";
    $params[] = "%$filterTag%";
    $types .= 's';
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch filtered posts
$query = "SELECT posts.*, authors.name AS author_name FROM posts LEFT JOIN authors ON posts.author_id = authors.id $whereClause ORDER BY posts.created_at DESC";
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$filteredPosts = $stmt->get_result();

$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL");

// Get tags for filter
$tags = $conn->query("SELECT tags FROM posts WHERE tags IS NOT NULL");
$allTags = [];
while ($row = $tags->fetch_assoc()) {
    $postTags = explode(",", $row['tags']);
    foreach ($postTags as $tag) {
        $tag = trim($tag);
        if ($tag && !in_array($tag, $allTags)) {
            $allTags[] = $tag;
        }
    }
}
sort($allTags);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - My CMS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --header-height: 60px;
            --border-radius: 6px;
            --box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            --transition: all 0.2s ease-in-out;
        }

        [data-theme="dark"] {
            --wp-gray-100: #1d2327;
            --wp-gray-200: #2c3338;
            --wp-gray-300: #3c434a;
            --wp-gray-400: #50575e;
            --wp-gray-500: #646970;
            --wp-gray-600: #8c8f94;
            --wp-gray-700: #a7aaad;
            --wp-gray-800: #c3c4c7;
            --wp-gray-900: #f6f7f7;
            --wp-white: #1d2327;
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

        /* WordPress-style Admin Bar */
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
            background-color: var(--wp-white);
            color: var(--wp-gray-700);
            border: 1px solid var(--wp-gray-300);
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }

        .wp-button-secondary:hover {
            background-color: var(--wp-gray-100);
            border-color: var(--wp-gray-400);
            color: var(--wp-gray-900);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--wp-gray-300);
            color: var(--wp-gray-700);
            padding: 8px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .theme-toggle:hover {
            background: var(--wp-gray-100);
        }

        /* Content Area */
        .wp-content {
            padding: 30px;
        }

        /* Dashboard Widgets */
        .wp-dashboard {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .wp-dashboard-main {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .wp-dashboard-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        /* Widget Boxes */
        .wp-widget {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .wp-widget-header {
            padding: 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: var(--wp-white);
        }

        .wp-widget-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-widget-content {
            padding: 20px;
        }

        /* Filters */
        .wp-filters {
            background: var(--wp-white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .wp-filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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

        .wp-form-control {
            padding: 8px 12px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--wp-white);
            color: var(--wp-gray-900);
            transition: var(--transition);
        }

        .wp-form-control:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }

        .wp-search {
            position: relative;
            max-width: 300px;
        }

        .wp-search input {
            padding-left: 40px;
        }

        .wp-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--wp-gray-500);
        }

        /* Posts Grid */
        .wp-posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .wp-post-card {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .wp-post-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .wp-post-image {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 6px;
        }

        .wp-post-card:hover .wp-post-image {
            transform: scale(1.05);
        }

        .wp-post-content {
            padding: 20px;
        }

        .wp-post-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .wp-post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 13px;
            color: var(--wp-gray-600);
        }

        .wp-post-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .wp-post-excerpt {
            color: var(--wp-gray-700);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .wp-post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 15px;
        }

        .wp-tag {
            background: var(--wp-gray-100);
            color: var(--wp-gray-700);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .wp-post-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .wp-post-status.published {
            background: var(--wp-success);
            color: var(--wp-white);
        }

        .wp-post-status.draft {
            background: var(--wp-warning);
            color: var(--wp-white);
        }

        .wp-post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-top: 1px solid var(--wp-gray-200);
            background: var(--wp-gray-100);
        }

        .wp-post-actions-left {
            display: flex;
            gap: 15px;
        }

        .wp-post-action {
            color: var(--wp-blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
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

        .wp-post-views {
            font-size: 13px;
            color: var(--wp-gray-600);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Activity Feed */
        .wp-activity-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-activity-item:last-child {
            border-bottom: none;
        }

        .wp-activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--wp-blue);
            color: var(--wp-white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .wp-activity-content {
            flex: 1;
        }

        .wp-activity-title {
            font-weight: 500;
            color: var(--wp-gray-900);
            margin-bottom: 5px;
        }

        .wp-activity-meta {
            font-size: 13px;
            color: var(--wp-gray-600);
        }

        /* Chart Container */
        .wp-chart {
            height: 300px;
            margin-top: 20px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .wp-dashboard {
                grid-template-columns: 1fr;
            }
            
            .wp-dashboard-sidebar {
                order: -1;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
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
            
            .wp-header {
                padding: 15px 20px;
            }
            
            .wp-content {
                padding: 20px;
            }
            
            .wp-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .wp-posts-grid {
                grid-template-columns: 1fr;
            }
            
            .wp-filters-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .wp-stats {
                grid-template-columns: 1fr;
            }
            
            .wp-admin-bar {
                padding: 0 15px;
            }
            
            .wp-header h1 {
                font-size: 20px;
            }
        }

        /* Mobile Menu Toggle */
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

        /* Loading States */
        .wp-loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--wp-gray-300);
            border-radius: 50%;
            border-top-color: var(--wp-blue);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Tooltips */
        .wp-tooltip {
            position: relative;
            cursor: help;
        }

        .wp-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--wp-gray-900);
            color: var(--wp-white);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
        }

        .wp-tooltip:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* Animations */
        .wp-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <!-- WordPress-style Admin Bar -->
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
                    <a href="dashboard.php" class="wp-menu-link active">
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
                    <h1>Dashboard</h1>
                </div>
                
                <div class="wp-header-actions">
                    <div class="wp-search">
                        <i class="fas fa-search wp-search-icon"></i>
                        <input type="text" id="searchInput" class="wp-form-control" placeholder="Search posts...">
                    </div>
                
                    <a href="create.php" class="wp-button">
                        <i class="fas fa-plus"></i>
                        Add New Post
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <!-- Stats Cards -->
                <div class="wp-stats">
                    <div class="wp-stat-card">
                        <i class="fas fa-file-alt wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $totalPosts ?></div>
                        <div class="wp-stat-label">Total Posts</div>
                    </div>
                    <div class="wp-stat-card success">
                        <i class="fas fa-users wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $totalAuthors ?></div>
                        <div class="wp-stat-label">Authors</div>
                    </div>
                    <div class="wp-stat-card warning">
                        <i class="fas fa-comments wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= $totalComments ?></div>
                        <div class="wp-stat-label">Comments</div>
                    </div>
                    <div class="wp-stat-card error">
                        <i class="fas fa-eye wp-stat-icon"></i>
                        <div class="wp-stat-number"><?= number_format($totalViews ?? 0) ?></div>
                        <div class="wp-stat-label">Total Views</div>
                    </div>
                </div>

                <!-- Dashboard Layout -->
                <div class="wp-dashboard">
                    <div class="wp-dashboard-main">
                        <!-- Filters -->
                        <div class="wp-filters">
                            <form method="GET" id="filterForm">
                                <div class="wp-filters-grid">
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Author</label>
                                        <select name="author" class="wp-form-control" id="authorFilter">
                                            <option value="">All Authors</option>
                                            <?php
                                            $authors->data_seek(0);
                                            while ($author = $authors->fetch_assoc()):
                                            ?>
                                                <option value="<?= htmlspecialchars($author['name']) ?>" <?= $filterAuthor === $author['name'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($author['name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Status</label>
                                        <select name="status" class="wp-form-control" id="statusFilter">
                                            <option value="">All Statuses</option>
                                            <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
                                            <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        </select>
                                    </div>
                                    
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Category</label>
                                        <select name="category" class="wp-form-control" id="categoryFilter">
                                            <option value="">All Categories</option>
                                            <?php while ($category = $categories->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($category['category']) ?>" <?= $filterCategory === $category['category'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['category']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">From Date</label>
                                        <input type="date" name="from" class="wp-form-control" value="<?= htmlspecialchars($filterFrom) ?>">
                                    </div>
                                    
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">To Date</label>
                                        <input type="date" name="to" class="wp-form-control" value="<?= htmlspecialchars($filterTo) ?>">
                                    </div>
                                    
                                    <div class="wp-form-group" style="align-self: end;">
                                        <button type="button" class="wp-button-secondary" onclick="resetFilters()">
                                            <i class="fas fa-undo"></i>
                                            Reset Filters
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Posts Grid -->
                        <div class="wp-posts-grid" id="postsGrid">
                            <?php while ($row = $filteredPosts->fetch_assoc()): ?>
                                <article class="wp-post-card wp-fade-in"
                                         data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
                                         data-author="<?= htmlspecialchars($row['author_name']) ?>"
                                         data-status="<?= htmlspecialchars($row['status']) ?>"
                                         data-date="<?= htmlspecialchars($row['created_at']) ?>"
                                         data-category="<?= htmlspecialchars($row['category']) ?>"
                                         data-tags="<?= strtolower(htmlspecialchars($row['tags'])) ?>">
                                    
                                    <div class="wp-post-status <?= $row['status'] ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </div>
                                    
                                    <?php if (!empty($row['image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($row['image']) ?>"
                                             alt="<?= htmlspecialchars($row['title']) ?>"
                                             class="wp-post-image"
                                             onclick="previewImage(this.src)">
                                    <?php else: ?>
                                        <div class="wp-post-image" style="background: var(--wp-gray-200); display: flex; align-items: center; justify-content: center; color: var(--wp-gray-500);">
                                            <i class="fas fa-image" style="font-size: 48px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="wp-post-content">
                                        <h3 class="wp-post-title"><?= htmlspecialchars($row['title']) ?></h3>
                                        
                                        <div class="wp-post-meta">
                                            <div class="wp-post-meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <?= date("M d, Y", strtotime($row['created_at'])) ?>
                                            </div>
                                            <div class="wp-post-meta-item">
                                                <i class="fas fa-user"></i>
                                                <?= htmlspecialchars($row['author_name']) ?>
                                            </div>
                                            <?php if (!empty($row['category'])): ?>
                                                <div class="wp-post-meta-item">
                                                    <i class="fas fa-folder"></i>
                                                    <?= htmlspecialchars($row['category']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="wp-post-excerpt">
                                            <?= mb_strimwidth(strip_tags($row['content']), 0, 120, '...') ?>
                                        </div>
                                        
                                        <?php if (!empty($row['tags'])): ?>
                                            <div class="wp-post-tags">
                                                <?php
                                                $tags = explode(',', $row['tags']);
                                                foreach ($tags as $tag):
                                                    $tag = trim($tag);
                                                    if (!empty($tag)):
                                                ?>
                                                    <span class="wp-tag"><?= htmlspecialchars($tag) ?></span>
                                                <?php
                                                    endif;
                                                endforeach;
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="wp-post-actions">
                                        <div class="wp-post-actions-left">
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="wp-post-action">
                                                <i class="fas fa-edit"></i>
                                                Edit
                                            </a>
                                            <a href="../post.php?slug=<?= urlencode($row['slug']) ?>" class="wp-post-action" target="_blank">
                                                <i class="fas fa-external-link-alt"></i>
                                                View
                                            </a>
                                            <a href="delete.php?id=<?= $row['id'] ?>" class="wp-post-action danger" onclick="return confirmDelete('<?= htmlspecialchars($row['title']) ?>')">
                                                <i class="fas fa-trash"></i>
                                                Delete
                                            </a>
                                        </div>
                                        <div class="wp-post-views">
                                            <i class="fas fa-eye"></i>
                                            <?= number_format($row['views'] ?? 0) ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Sidebar Widgets -->
                    <div class="wp-dashboard-sidebar">
                        <!-- Quick Stats -->
                        <div class="wp-widget">
                            <div class="wp-widget-header">
                                <h3 class="wp-widget-title">
                                    <i class="fas fa-chart-line"></i>
                                    Quick Stats
                                </h3>
                            </div>
                            <div class="wp-widget-content">
                                <canvas id="statsChart" class="wp-chart"></canvas>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="wp-widget">
                            <div class="wp-widget-header">
                                <h3 class="wp-widget-title">
                                    <i class="fas fa-clock"></i>
                                    Recent Activity
                                </h3>
                            </div>
                            <div class="wp-widget-content">
                                <?php while ($post = $recentPosts->fetch_assoc()): ?>
                                    <div class="wp-activity-item">
                                        <div class="wp-activity-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="wp-activity-content">
                                            <div class="wp-activity-title">
                                                <?= htmlspecialchars($post['title']) ?>
                                            </div>
                                            <div class="wp-activity-meta">
                                                <?= ucfirst($post['status']) ?> • <?= date("M d, Y", strtotime($post['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <!-- Recent Comments -->
                        <div class="wp-widget">
                            <div class="wp-widget-header">
                                <h3 class="wp-widget-title">
                                    <i class="fas fa-comments"></i>
                                    Recent Comments
                                </h3>
                            </div>
                            <div class="wp-widget-content">
                                <?php if ($recentComments->num_rows > 0): ?>
                                    <?php while ($comment = $recentComments->fetch_assoc()): ?>
                                        <div class="wp-activity-item">
                                            <div class="wp-activity-icon" style="background: var(--wp-success);">
                                                <i class="fas fa-comment"></i>
                                            </div>
                                            <div class="wp-activity-content">
                                                <div class="wp-activity-title">
                                                    <?= htmlspecialchars($comment['author_name']) ?>
                                                </div>
                                                <div class="wp-activity-meta">
                                                    On "<?= htmlspecialchars($comment['title']) ?>" • <?= date("M d", strtotime($comment['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p style="color: var(--wp-gray-600); text-align: center; padding: 20px 0;">
                                        No recent comments
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile Sidebar Toggle
        function toggleSidebar() {
            document.getElementById('wpSidebar').classList.toggle('open');
        }

        // Search Functionality
        const searchInput = document.getElementById('searchInput');
        const postCards = document.querySelectorAll('.wp-post-card');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            postCards.forEach(card => {
                const title = card.dataset.title;
                const author = card.dataset.author.toLowerCase();
                const category = card.dataset.category.toLowerCase();
                const tags = card.dataset.tags;
                
                const matches = title.includes(searchTerm) ||
                               author.includes(searchTerm) ||
                               category.includes(searchTerm) ||
                               tags.includes(searchTerm);
                
                card.style.display = matches ? 'block' : 'none';
            });
        });

        // Filter Form Submission
        const filterSelects = document.querySelectorAll('#filterForm select, #filterForm input[type="date"]');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                document.getElementById('filterForm').submit();
            });
        });

        // Reset Filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        // Image Preview
        function previewImage(src) {
            Swal.fire({
                imageUrl: src,
                imageAlt: 'Post Image Preview',
                showConfirmButton: false,
                showCloseButton: true,
                background: document.body.getAttribute('data-theme') === 'dark' ? 'var(--wp-gray-800)' : 'var(--wp-white)',
                customClass: {
                    image: 'wp-preview-image'
                }
            });
        }

        // Confirm Delete
        function confirmDelete(title) {
            return Swal.fire({
                title: 'Delete Post?',
                text: `Are you sure you want to delete "${title}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--wp-error)',
                cancelButtonColor: 'var(--wp-gray-400)',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                return result.isConfirmed;
            });
        }

        // Initialize Chart with Fixed Colors
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Posts', 'Comments', 'Authors'],
                datasets: [{
                    data: [<?= $totalPosts ?>, <?= $totalComments ?>, <?= $totalAuthors ?>],
                    backgroundColor: [
                        '#0073aa',  // WordPress blue
                        '#00a32a',  // Success green
                        '#dba617'   // Warning yellow
                    ],
                    borderColor: [
                        '#005177',  // Darker blue
                        '#007a1f',  // Darker green
                        '#b8940f'   // Darker yellow
                    ],
                    borderWidth: 2,
                    hoverBackgroundColor: [
                        '#005177',
                        '#007a1f',
                        '#b8940f'
                    ],
                    hoverBorderColor: '#ffffff',
                    hoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                family: 'Inter',
                                size: 12,
                                weight: '500'
                            },
                            color: '#646970'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1d2327',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#646970',
                        borderWidth: 1,
                        cornerRadius: 6,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 1000,
                    easing: 'easeOutQuart'
                },
                cutout: '60%',
                elements: {
                    arc: {
                        borderWidth: 0
                    }
                }
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
