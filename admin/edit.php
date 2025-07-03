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
    header("Location: posts.php");
    exit;
}

$res = $conn->query("SELECT * FROM posts WHERE id=$id");
$post = $res->fetch_assoc();

if (!$post) {
    header("Location: posts.php");
    exit;
}

// Get statistics for sidebar
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Fetch categories, tags, authors
$categoryList = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$tagList = $conn->query("SELECT * FROM tags ORDER BY name ASC");
$authorList = $conn->query("SELECT * FROM authors ORDER BY name ASC");

// Convert saved tags into array
$selectedTags = !empty($post['tags']) ? array_map('trim', explode(',', $post['tags'])) : [];

$success = $error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $content = trim($_POST['content']);
    $category = $_POST['category'];
    $author_id = $_POST['author_id'];
    $tags = isset($_POST['tags']) ? implode(',', $_POST['tags']) : '';
    $status = $_POST['status'];
    $excerpt = trim($_POST['excerpt'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $image = $post['image'];

    // Validation
    if (empty($title) || empty($slug) || empty($content) || empty($category) || empty($author_id)) {
        $error = "❌ Please fill in all required fields.";
    } else {
        // Check if slug already exists (excluding current post)
        $slug_check = $conn->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
        $slug_check->bind_param("si", $slug, $id);
        $slug_check->execute();
        $slug_result = $slug_check->get_result();
        
        if ($slug_result->num_rows > 0) {
            $error = "❌ This slug already exists. Please choose a different one.";
        }
        $slug_check->close();

        // Handle image upload
        if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                $error = "❌ Invalid image type. Please upload JPEG, PNG, GIF, or WebP images.";
            } elseif ($_FILES['image']['size'] > $maxSize) {
                $error = "❌ Image size too large. Maximum size is 5MB.";
            } else {
                $imageName = time() . '_' . basename($_FILES['image']['name']);
                $targetDir = '../uploads/';
                
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                $targetFile = $targetDir . $imageName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    // Delete old image if exists
                    if (!empty($post['image']) && file_exists($targetDir . $post['image'])) {
                        unlink($targetDir . $post['image']);
                    }
                    $image = $imageName;
                } else {
                    $error = "❌ Failed to upload image.";
                }
            }
        }

        if (!$error) {
            $stmt = $conn->prepare("UPDATE posts SET title=?, content=?, category=?, tags=?, slug=?, status=?, image=?, author_id=?, excerpt=?, meta_description=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssssssssssi", $title, $content, $category, $tags, $slug, $status, $image, $author_id, $excerpt, $meta_description, $id);
            
            if ($stmt->execute()) {
                $success = "✅ Post updated successfully!";
                
                // Refresh post data
                $res = $conn->query("SELECT * FROM posts WHERE id=$id");
                $post = $res->fetch_assoc();
                $selectedTags = !empty($post['tags']) ? array_map('trim', explode(',', $post['tags'])) : [];
            } else {
                $error = "❌ Error updating post: " . $conn->error;
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
    <title>Edit Post: <?= htmlspecialchars($post['title']) ?> - My PHP CMS</title>
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

        .wp-form-file {
            padding: 8px 12px;
            background: var(--wp-gray-100);
        }

        /* CKEditor Styling */
        .ck-editor__editable {
            min-height: 300px;
        }

        /* Tag Checkboxes */
        .wp-tag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            background: var(--wp-white);
        }

        .wp-checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            transition: var(--transition);
            cursor: pointer;
        }

        .wp-checkbox-item:hover {
            background: var(--wp-gray-100);
        }

        .wp-checkbox-item input[type="checkbox"] {
            margin: 0;
        }

        .wp-checkbox-item label {
            margin: 0;
            cursor: pointer;
            font-weight: 400;
            font-size: 13px;
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

        /* Image Preview */
        .wp-image-preview {
            margin-top: 15px;
        }

        .wp-image-preview img {
            max-width: 100%;
            height: auto;
            border-radius: var(--border-radius);
            border: 1px solid var(--wp-gray-200);
        }

        .wp-image-current {
            font-size: 13px;
            color: var(--wp-gray-600);
            margin-top: 5px;
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

        /* Post Info */
        .wp-post-info {
            background: var(--wp-gray-100);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }

        .wp-post-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .wp-post-info-item:last-child {
            margin-bottom: 0;
        }

        .wp-post-info-label {
            font-weight: 500;
            color: var(--wp-gray-700);
        }

        .wp-post-info-value {
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
            
            .wp-tag-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            
            .wp-form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .wp-header h1 {
                font-size: 20px;
            }
            
            .wp-tag-grid {
                grid-template-columns: 1fr;
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
                    <h1>Edit Post</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="../post.php?slug=<?= urlencode($post['slug']) ?>" class="wp-button wp-button-secondary" target="_blank">
                        <i class="fas fa-external-link-alt"></i>
                        View Post
                    </a>
                    <a href="posts.php" class="wp-button wp-button-secondary">
                        <i class="fas fa-list"></i>
                        All Posts
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <!-- Breadcrumb -->
                <div class="wp-breadcrumb">
                    <a href="dashboard.php">Dashboard</a> ›
                    <a href="posts.php">Posts</a> ›
                    Edit Post
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

                <form id="editPostForm" method="POST" enctype="multipart/form-data">
                    <div class="wp-form-layout">
                        <!-- Main Content -->
                        <div class="wp-form-main">
                            <!-- Title and Slug -->
                            <div class="wp-form-container">
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <label class="wp-form-label required">Post Title</label>
                                        <input type="text" name="title" class="wp-form-control" placeholder="Enter your post title..." required value="<?= htmlspecialchars($post['title']) ?>" oninput="updateSlugFromTitle()">
                                    </div>
                                    <div class="wp-form-group">
                                        <label class="wp-form-label required">Permalink</label>
                                        <input type="text" name="slug" class="wp-form-control" placeholder="post-slug" required value="<?= htmlspecialchars($post['slug']) ?>" id="slugInput">
                                        <div class="wp-slug-preview" id="slugPreview">
                                            <strong>Permalink:</strong> <span id="slugUrl"><?= $_SERVER['HTTP_HOST'] ?>/post/<?= htmlspecialchars($post['slug']) ?></span>
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
                                    <textarea id="editor" name="content" class="wp-form-control wp-form-textarea" required><?= htmlspecialchars($post['content']) ?></textarea>
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
                                        <label class="wp-form-label">Post Excerpt</label>
                                        <textarea name="excerpt" class="wp-form-control" rows="3" placeholder="Write a short description of your post..."><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                                        <small style="color: var(--wp-gray-600); font-size: 13px;">Excerpts are optional hand-crafted summaries of your content.</small>
                                    </div>
                                    <div class="wp-form-group">
                                        <label class="wp-form-label">Meta Description</label>
                                        <textarea name="meta_description" class="wp-form-control" rows="2" maxlength="160" placeholder="SEO meta description (max 160 characters)..." id="metaDescription"><?= htmlspecialchars($post['meta_description'] ?? '') ?></textarea>
                                        <div class="wp-char-counter" id="metaCounter">0/160 characters</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="wp-form-sidebar">
                            <!-- Post Info -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-info-circle"></i>
                                        Post Information
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-post-info">
                                        <div class="wp-post-info-item">
                                            <span class="wp-post-info-label">Post ID:</span>
                                            <span class="wp-post-info-value">#<?= $post['id'] ?></span>
                                        </div>
                                        <div class="wp-post-info-item">
                                            <span class="wp-post-info-label">Status:</span>
                                            <span class="wp-status-badge <?= $post['status'] ?>">
                                                <?= ucfirst($post['status']) ?>
                                            </span>
                                        </div>
                                        <div class="wp-post-info-item">
                                            <span class="wp-post-info-label">Created:</span>
                                            <span class="wp-post-info-value"><?= date('M j, Y @ g:i a', strtotime($post['created_at'])) ?></span>
                                        </div>
                                        <?php if ($post['updated_at']): ?>
                                        <div class="wp-post-info-item">
                                            <span class="wp-post-info-label">Updated:</span>
                                            <span class="wp-post-info-value"><?= date('M j, Y @ g:i a', strtotime($post['updated_at'])) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="wp-post-info-item">
                                            <span class="wp-post-info-label">Views:</span>
                                            <span class="wp-post-info-value"><?= number_format($post['views']) ?></span>
                                        </div>
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
                                            <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="wp-form-actions">
                                    <button type="submit" class="wp-button wp-button-success wp-button-large">
                                        <i class="fas fa-save"></i>
                                        Update Post
                                    </button>
                                    <a href="posts.php" class="wp-button wp-button-secondary">
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
                                                <option value="<?= $author['id'] ?>" <?= $post['author_id'] == $author['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($author['name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-folder"></i>
                                        Category
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-form-group">
                                        <select name="category" class="wp-form-control" required>
                                            <option value="">Select Category</option>
                                            <?php while($category = $categoryList->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($category['name']) ?>" <?= $post['category'] === $category['name'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Tags -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-tags"></i>
                                        Tags
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <div class="wp-tag-grid">
                                        <?php while($tag = $tagList->fetch_assoc()): ?>
                                            <div class="wp-checkbox-item">
                                                <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag['name']) ?>" id="tag_<?= $tag['id'] ?>" <?= in_array($tag['name'], $selectedTags) ? 'checked' : '' ?>>
                                                <label for="tag_<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Featured Image -->
                            <div class="wp-form-container">
                                <div class="wp-form-header">
                                    <h3 class="wp-form-title">
                                        <i class="fas fa-image"></i>
                                        Featured Image
                                    </h3>
                                </div>
                                <div class="wp-form-content">
                                    <?php if (!empty($post['image'])): ?>
                                        <div class="wp-image-preview">
                                            <img src="../uploads/<?= htmlspecialchars($post['image']) ?>" alt="Current featured image">
                                            <div class="wp-image-current">Current featured image</div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="wp-form-group">
                                        <input type="file" name="image" class="wp-form-control wp-form-file" accept="image/*" onchange="previewNewImage(this)">
                                        <small style="color: var(--wp-gray-600); font-size: 13px; margin-top: 5px; display: block;">
                                            <?= !empty($post['image']) ? 'Upload a new image to replace the current one.' : 'Upload a featured image for this post.' ?>
                                            <br>Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF, WebP
                                        </small>
                                        <div id="newImagePreview" style="margin-top: 15px; display: none;">
                                            <img id="newPreviewImg" style="max-width: 100%; height: auto; border-radius: var(--border-radius); border: 1px solid var(--wp-gray-200);">
                                            <div style="font-size: 13px; color: var(--wp-gray-600); margin-top: 5px;">New image preview</div>
                                        </div>
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
                                        <a href="create.php" class="wp-button wp-button-secondary">
                                            <i class="fas fa-plus"></i>
                                            Add New Post
                                        </a>
                                        <a href="delete.php?id=<?= $post['id'] ?>" class="wp-button wp-button-danger" onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                            Delete Post
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
            const previewUrl = slug ? `${baseUrl}/post/${slug}` : `${baseUrl}/post/your-post-slug`;
            document.getElementById('slugUrl').textContent = previewUrl;
        }

        // Mark slug as manually edited when user types in it
        document.getElementById('slugInput').addEventListener('input', function() {
            this.dataset.manuallyEdited = 'true';
            updateSlugPreview(this.value);
            formChanged = true;
        });

        function previewNewImage(input) {
            const preview = document.getElementById('newImagePreview');
            const previewImg = document.getElementById('newPreviewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
            formChanged = true;
        }

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
        document.getElementById('editPostForm').addEventListener('input', function() {
            formChanged = true;
        });

        document.getElementById('editPostForm').addEventListener('submit', function() {
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
        document.getElementById('editPostForm').addEventListener('submit', function(e) {
            const title = document.querySelector('[name="title"]').value.trim();
            const slug = document.querySelector('[name="slug"]').value.trim();
            const content = editor.getData().trim();
            const category = document.querySelector('[name="category"]').value;
            const author = document.querySelector('[name="author_id"]').value;
            
            if (!title || !slug || !content || !category || !author) {
                e.preventDefault();
                alert('Please fill in all required fields (Title, Slug, Content, Category, and Author).');
                return false;
            }
        });
    </script>
</body>
</html>
