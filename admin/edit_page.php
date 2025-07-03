<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: pages.php");
    exit;
}

$res = $conn->query("SELECT * FROM pages WHERE id=$id");
$page = $res->fetch_assoc();

if (!$page) {
    header("Location: pages.php");
    exit;
}

$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];

// Get statistics for sidebar
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];

// Fetch authors
$authorList = $conn->query("SELECT * FROM authors ORDER BY name ASC");

$success = $error = '';

function generateSlug($text) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']) ?: generateSlug($title);
    $content = trim($_POST['content']);
    $status = $_POST['status'];
    $author_id = $_POST['author_id'];
    $excerpt = trim($_POST['excerpt'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $page_order = (int)($_POST['page_order'] ?? 0);
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;

    // Validation
    if (empty($title) || empty($slug) || empty($content) || empty($author_id)) {
        $error = "❌ Please fill in all required fields.";
    } else {
        // Check if slug already exists (excluding current page)
        $slug_check = $conn->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
        $slug_check->bind_param("si", $slug, $id);
        $slug_check->execute();
        $slug_result = $slug_check->get_result();
        
        if ($slug_result->num_rows > 0) {
            $error = "❌ This slug already exists. Please choose a different one.";
        }
        $slug_check->close();

        if (!$error) {
            $stmt = $conn->prepare("UPDATE pages SET title=?, content=?, slug=?, status=?, author_id=?, excerpt=?, meta_description=?, page_order=?, show_in_menu=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssssissiii", $title, $content, $slug, $status, $author_id, $excerpt, $meta_description, $page_order, $show_in_menu, $id);
            
            if ($stmt->execute()) {
                $success = "✅ Page updated successfully!";
                
                // Refresh page data
                $res = $conn->query("SELECT * FROM pages WHERE id=$id");
                $page = $res->fetch_assoc();
            } else {
                $error = "❌ Error updating page: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Page: <?= htmlspecialchars($page['title']) ?> - My PHP CMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
    
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

        .wp-button-success {
            background: var(--wp-success);
        }

        .wp-button-success:hover {
            background: #00892a;
        }

        .wp-button-danger {
            background: var(--wp-error);
        }

        .wp-button-danger:hover {
            background: #b32d2e;
        }

        .wp-button-large {
            padding: 12px 24px;
            font-size: 16px;
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

        /* Breadcrumb */
        .wp-breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--wp-gray-600);
        }

        .wp-breadcrumb a {
            color: var(--wp-blue);
            text-decoration: none;
        }

        .wp-breadcrumb a:hover {
            color: var(--wp-blue-dark);
        }

        /* Form Layout */
        .wp-form-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .wp-form-main {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .wp-form-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Form Containers */
        .wp-form-container {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .wp-form-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: var(--wp-white);
        }

        .wp-form-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wp-form-content {
            padding: 20px;
        }

        /* Form Elements */
        .wp-form-group {
            margin-bottom: 20px;
        }

        .wp-form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--wp-gray-700);
            margin-bottom: 8px;
        }

        .wp-form-label.required::after {
            content: ' *';
            color: var(--wp-error);
        }

        .wp-form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--wp-white);
            color: var(--wp-gray-900);
            transition: var(--transition);
            font-family: inherit;
        }

        .wp-form-control:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }

        .wp-form-control::placeholder {
            color: var(--wp-gray-500);
        }

        .wp-form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* CKEditor Styling */
        .ck-editor__editable {
            min-height: 300px;
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

        /* Action Buttons */
        .wp-form-actions {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: var(--wp-gray-100);
            border-top: 1px solid var(--wp-gray-200);
            justify-content: flex-end;
        }

        /* Slug Preview */
        .wp-slug-preview {
            font-size: 13px;
            color: var(--wp-gray-600);
            margin-top: 5px;
            padding: 8px 12px;
            background: var(--wp-gray-100);
            border-radius: var(--border-radius);
            border: 1px solid var(--wp-gray-200);
        }

        .wp-slug-preview strong {
            color: var(--wp-gray-900);
        }

        /* Page Info */
        .wp-page-info {
            background: var(--wp-gray-100);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }

        .wp-page-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .wp-page-info-item:last-child {
            margin-bottom: 0;
        }

        .wp-page-info-label {
            font-weight: 500;
            color: var(--wp-gray-700);
        }

        .wp-page-info-value {
            color: var(--wp-gray-600);
        }

        /* Character Counter */
        .wp-char-counter {
            font-size: 12px;
            color: var(--wp-gray-500);
            text-align: right;
            margin-top: 5px;
        }

        .wp-char-counter.over-limit {
            color: var(--wp-error);
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
            
            .wp-form-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .wp-form-sidebar {
                order: -1;
            }
            
            .wp-form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .wp-header h1 {
                font-size: 20px;
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
                    <h1>Edit Page</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="../page.php?slug=<?= urlencode($page['slug']) ?>" class="wp-button wp-button-secondary" target="_blank">
                        <i class="fas fa-external-link-alt"></i>
                        View Page
                    </a>
                    <a href="pages.php" class="wp-button wp-button-secondary">
                        <i class="fas fa-list"></i>
                        All Pages
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <!-- Breadcrumb -->
                <div class="wp-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> ›
                    <a href="pages.php">Pages</a> ›
                    Edit Page
                </div>

                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div class="wp-alert success">
                        <i class="fas fa-check-circle"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="wp-alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form id="editPageForm" method="POST">
                    <div class="wp-form-layout">
                        <!-- Main Content -->
                        <div class="wp-form-main">
                            <!-- Title and Slug -->
                            <div class="wp-form-container">
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <label class="wp-form-label required">Page Title</label>
                                        <input type="text" name="title" class="wp-form-control" placeholder="Enter your page title..." required value="<?= htmlspecialchars($page['title']) ?>" oninput="updateSlugFromTitle()">
                                    </div>
                                    <div class="wp-form-group">
                                        <label class="wp-form-label required">Permalink</label>
                                        <input type="text" name="slug" class="wp-form-control" placeholder="page-slug" required value="<?= htmlspecialchars($page['slug']) ?>" id="slugInput">
                                        <div class="wp-slug-preview" id="slugPreview">
                                            <strong>Permalink:</strong> <span id="slugUrl"><?= $_SERVER['HTTP_HOST'] ?>/page/<?= htmlspecialchars($page['slug']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Content Editor -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-edit"></i>
                                        Content
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <textarea id="editor" name="content" class="wp-form-control wp-form-textarea" required><?= htmlspecialchars($page['content']) ?></textarea>
                                </div>
                            </div>

                            <!-- Excerpt and SEO -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-align-left"></i>
                                        Excerpt & SEO
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Page Excerpt</label>
                                        <textarea name="excerpt" class="wp-form-control" rows="3" placeholder="Write a short description of your page..."><?= htmlspecialchars($page['excerpt'] ?? '') ?></textarea>
                                        <small style="color: var(--wp-gray-600); font-size: 13px;">Excerpts are optional hand-crafted summaries of your content.</small>
                                    </div>
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Meta Description</label>
                                        <textarea name="meta_description" class="wp-form-control" rows="2" maxlength="160" placeholder="SEO meta description (max 160 characters)..." id="metaDescription"><?= htmlspecialchars($page['meta_description'] ?? '') ?></textarea>
                                        <div class="wp-char-counter" id="metaCounter">0/160 characters</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="wp-form-sidebar">
                            <!-- Page Info -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-info-circle"></i>
                                        Page Information
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-page-info">
                                        <div class="wp-page-info-item">
                                            <span class="wp-page-info-label">Page ID:</span>
                                            <span class="wp-page-info-value">#<?= $page['id'] ?></span>
                                        </div>
                                        <div class="wp-page-info-item">
                                            <span class="wp-page-info-label">Status:</span>
                                            <span class="wp-status-badge <?= $page['status'] ?? 'published' ?>">
                                                <?= ucfirst($page['status'] ?? 'Published') ?>
                                            </span>
                                        </div>
                                        <div class="wp-page-info-item">
                                            <span class="wp-page-info-label">Created:</span>
                                            <span class="wp-page-info-value"><?= date('M j, Y @ g:i a', strtotime($page['created_at'])) ?></span>
                                        </div>
                                        <?php if ($page['updated_at']): ?>
                                        <div class="wp-page-info-item">
                                            <span class="wp-page-info-label">Updated:</span>
                                            <span class="wp-page-info-value"><?= date('M j, Y @ g:i a', strtotime($page['updated_at'])) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Publish Actions -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-paper-plane"></i>
                                        Publish
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Status</label>
                                        <select name="status" class="wp-form-control" required>
                                            <option value="published" <?= ($page['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option>
                                            <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="wp-form-actions">
                                    <button type="submit" class="wp-button wp-button-success wp-button-large">
                                        <i class="fas fa-save"></i>
                                        Update Page
                                    </button>
                                    <a href="pages.php" class="wp-button wp-button-secondary">
                                        Cancel
                                    </a>
                                </div>
                            </div>

                            <!-- Author -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-user"></i>
                                        Author
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <select name="author_id" class="wp-form-control" required>
                                            <option value="">Select Author</option>
                                            <?php while($author = $authorList->fetch_assoc()): ?>
                                                <option value="<?= $author['id'] ?>" <?= ($page['author_id'] ?? '') == $author['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($author['name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Page Settings -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-cog"></i>
                                        Page Settings
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Page Order</label>
                                        <input type="number" name="page_order" class="wp-form-control" placeholder="0" value="<?= htmlspecialchars($page['page_order'] ?? '0') ?>" min="0">
                                        <small style="color: var(--wp-gray-600); font-size: 13px; margin-top: 5px; display: block;">
                                            Pages with lower numbers appear first in navigation.
                                        </small>
                                    </div>
                                    <div class="wp-form-group">
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="show_in_menu" value="1" <?= ($page['show_in_menu'] ?? 1) ? 'checked' : '' ?>>
                                            <span style="font-size: 14px; color: var(--wp-gray-700);">Show in navigation menu</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-bolt"></i>
                                        Quick Actions
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <a href="add_page.php" class="wp-button wp-button-secondary">
                                            <i class="fas fa-plus"></i>
                                            Add New Page
                                        </a>
                                        <a href="delete_page.php?id=<?= $page['id'] ?>" class="wp-button wp-button-danger" onclick="return confirm('Are you sure you want to delete this page? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                            Delete Page
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let editor;
        let formChanged = false;

        // Initialize CKEditor
        ClassicEditor
            .create(document.querySelector('#editor'), {
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', 'underline', 'strikethrough', '|',
                        'link', 'bulletedList', 'numberedList', '|',
                        'insertImage', 'blockQuote', 'insertTable', '|',
                        'undo', 'redo'
                    ]
                },
                image: {
                    toolbar: ['imageTextAlternative', 'imageStyle:full', 'imageStyle:side'],
                    resizeUnit: 'px'
                },
                table: {
                    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
                }
            })
            .then(newEditor => {
                editor = newEditor;
                editor.model.document.on('change:data', () => {
                    formChanged = true;
                });
            })
            .catch(error => {
                console.error(error);
            });

        function toggleSidebar() {
            document.getElementById('wpSidebar').classList.toggle('open');
        }

        function updateSlugFromTitle() {
            const title = document.querySelector('[name="title"]').value;
            const slugInput = document.getElementById('slugInput');
            
            if (!slugInput.dataset.manuallyEdited) {
                const slug = title.toLowerCase()
                    .replace(/[^\w\s-]/g, '') // Remove special characters
                    .replace(/\s+/g, '-') // Replace spaces with hyphens
                    .replace(/-+/g, '-') // Replace multiple hyphens with single
                    .trim('-'); // Remove leading/trailing hyphens
                
                slugInput.value = slug;
                updateSlugPreview(slug);
            }
            formChanged = true;
        }

        function updateSlugPreview(slug) {
            const baseUrl = window.location.protocol + '//' + window.location.host;
            const previewUrl = slug ? `${baseUrl}/page/${slug}` : `${baseUrl}/page/your-page-slug`;
            document.getElementById('slugUrl').textContent = previewUrl;
        }

        // Mark slug as manually edited when user types in it
        document.getElementById('slugInput').addEventListener('input', function() {
            this.dataset.manuallyEdited = 'true';
            updateSlugPreview(this.value);
            formChanged = true;
        });

        // Meta description character counter
        const metaDescription = document.getElementById('metaDescription');
        const metaCounter = document.getElementById('metaCounter');

        function updateMetaCounter() {
            const length = metaDescription.value.length;
            metaCounter.textContent = `${length}/160 characters`;
            metaCounter.className = length > 160 ? 'wp-char-counter over-limit' : 'wp-char-counter';
        }

        metaDescription.addEventListener('input', function() {
            updateMetaCounter();
            formChanged = true;
        });

        // Initialize counter
        updateMetaCounter();

        // Track form changes
        document.getElementById('editPageForm').addEventListener('input', function() {
            formChanged = true;
        });

        document.getElementById('editPageForm').addEventListener('submit', function() {
            formChanged = false;
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
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

        // Form validation
        document.getElementById('editPageForm').addEventListener('submit', function(e) {
            const title = document.querySelector('[name="title"]').value.trim();
            const slug = document.querySelector('[name="slug"]').value.trim();
            const content = editor.getData().trim();
            const author = document.querySelector('[name="author_id"]').value;
            
            if (!title || !slug || !content || !author) {
                e.preventDefault();
                alert('Please fill in all required fields (Title, Slug, Content, and Author).');
                return false;
            }
        });
    </script>
</body>
</html>
