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
    die("Invalid tag ID.");
}

// Securely fetch tag
$stmt = $conn->prepare("SELECT * FROM tags WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$tag = $res->fetch_assoc();

if (!$tag) {
    die("Tag not found.");
}
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Get tag usage count
$usage_stmt = $conn->prepare("SELECT COUNT(*) as count FROM post_tags WHERE tag_id = ?");
$usage_stmt->bind_param("i", $id);
$usage_stmt->execute();
$usage_count = $usage_stmt->get_result()->fetch_assoc()['count'];

// Handle update
$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = "Tag name is required.";
    } else {
        // Check for duplicate name (excluding current tag)
        $check_stmt = $conn->prepare("SELECT id FROM tags WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $name, $id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "A tag with this name already exists.";
        } else {
            // Generate slug if empty
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug)));
            }
            
            // Check for duplicate slug (excluding current tag)
            $slug_check_stmt = $conn->prepare("SELECT id FROM tags WHERE slug = ? AND id != ?");
            $slug_check_stmt->bind_param("si", $slug, $id);
            $slug_check_stmt->execute();
            
            if ($slug_check_stmt->get_result()->num_rows > 0) {
                $error = "A tag with this slug already exists.";
            } else {
                // Update tag
                $update_stmt = $conn->prepare("UPDATE tags SET name = ?, slug = ?, description = ? WHERE id = ?");
                $update_stmt->bind_param("sssi", $name, $slug, $description, $id);
                
                if ($update_stmt->execute()) {
                    $success = "Tag updated successfully!";
                    // Refresh tag data
                    $stmt->execute();
                    $tag = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = "Error updating tag.";
                }
            }
        }
    }
}

// Get total posts count for sidebar badge
$total_posts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$total_comments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Tag - My CMS Admin</title>
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

.wp-form-actions {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 12px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.wp-button,
.wp-button-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}

/* Primary Button */
.wp-button {
    background-color: var(--wp-blue);
    color: var(--wp-white);
    border: none;
    box-shadow: 0 2px 5px rgba(0, 115, 170, 0.15);
}

.wp-button:hover {
    background-color: var(--wp-blue-dark);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
}

/* Secondary Button */
.wp-button-secondary {
    background-color: var(--wp-white);
    color: var(--wp-gray-800);
    border: 1px solid var(--wp-gray-300);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.wp-button-secondary:hover {
    background-color: var(--wp-gray-100);
    color: var(--wp-gray-900);
    border-color: var(--wp-gray-400);
}


        /* Content Area */
        .wp-content {
            padding: 30px;
        }

        /* Form Grid */
        .wp-form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            align-items: start;
        }

        /* Form Card */
        .wp-form-card {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .wp-form-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: var(--wp-gray-100);
        }

        .wp-form-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-form-body {
            padding: 25px;
        }

        /* Form Fields */
        .wp-form-group {
            margin-bottom: 20px;
        }

        .wp-form-group:last-child {
            margin-bottom: 0;
        }

        .wp-form-label {
            display: block;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .wp-form-input,
        .wp-form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            font-family: inherit;
            transition: var(--transition);
            background: var(--wp-white);
        }

        .wp-form-input:focus,
        .wp-form-textarea:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }

        .wp-form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .wp-form-help {
            font-size: 13px;
            color: var(--wp-gray-600);
            margin-top: 5px;
            line-height: 1.4;
        }

        /* Slug Preview */
        .wp-slug-preview {
            background: var(--wp-gray-100);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            font-size: 13px;
            color: var(--wp-gray-600);
            margin-top: 8px;
            font-family: 'Courier New', monospace;
        }

        /* Form Actions */
        .wp-form-actions {
            padding: 20px 25px;
            border-top: 1px solid var(--wp-gray-200);
            background: var(--wp-gray-100);
            display: flex;
            gap: 15px;
            align-items: center;
        }

        /* Info Card */
        .wp-info-card {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .wp-info-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: var(--wp-gray-100);
        }

        .wp-info-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
        }

        .wp-info-body {
            padding: 20px;
        }

        .wp-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-info-item:last-child {
            border-bottom: none;
        }

        .wp-info-label {
            font-weight: 500;
            color: var(--wp-gray-700);
        }

        .wp-info-value {
            color: var(--wp-gray-900);
            font-weight: 600;
        }

        /* Tips Card */
        .wp-tips-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--wp-white);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
        }

        .wp-tips-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wp-tips-list {
            list-style: none;
            padding: 0;
        }

        .wp-tips-list li {
            padding: 5px 0;
            padding-left: 20px;
            position: relative;
            font-size: 14px;
            line-height: 1.5;
        }

        .wp-tips-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #4ade80;
            font-weight: bold;
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
            
            .wp-form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
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
                        <span class="wp-menu-badge"><?= $total_posts ?></span>
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
                        <?php if ($total_comments > 0): ?>
                            <span class="wp-menu-badge"><?= $total_comments ?></span>
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
                    <a href="add_tag.php" class="wp-menu-link active">
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
                    <h1>Edit Tag</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="add_tag.php" class="wp-button-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Tags
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <?php if ($success): ?>
                    <div class="wp-alert success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="wp-alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="wp-form-grid">
                    <!-- Main Form -->
                    <div class="wp-form-card">
                        <div class="wp-form-header">
                            <h2>
                                <i class="fas fa-tag"></i>
                                Tag Details
                            </h2>
                        </div>
                        
<form method="POST" id="tagForm">
    <div class="wp-form-body">
        <!-- Tag Name -->
        <div class="wp-form-group">
            <label for="name" class="wp-form-label">Tag Name *</label>
            <input type="text"
                   id="name"
                   name="name"
                   class="wp-form-input"
                   value="<?= htmlspecialchars($tag['name'] ?? '', ENT_QUOTES) ?>"
                   required
                   oninput="generateSlug()">
            <div class="wp-form-help">
                The name is how it appears on your site.
            </div>
        </div>

<div class="wp-form-group">
            <label for="slug" class="wp-form-label">Slug</label>
            <input type="text"
                   id="slug"
                   name="slug"
                   class="wp-form-input"
                   value="<?= htmlspecialchars($tag['slug'] ?? '', ENT_QUOTES) ?>"
                   pattern="[a-z0-9-]+"
                   title="Only lowercase letters, numbers, and hyphens allowed">
            <div class="wp-form-help">
                The “slug” is the URL-friendly version of the name. Leave blank to auto-generate.
            </div>
        </div>

<div class="wp-form-group">
            <label for="description" class="wp-form-label">Description</label>
            <textarea id="description"
                      name="description"
                      class="wp-form-textarea"
                      placeholder="Optional description for this tag..."><?= htmlspecialchars($tag['description'] ?? '', ENT_QUOTES) ?></textarea>
            <div class="wp-form-help">
                The description is not prominent by default; however, some themes may show it.
            </div>
        </div>

                            </div>

                            <div class="wp-form-actions">
                                <button type="submit" class="wp-button">
                                    <i class="fas fa-save"></i>
                                    Update Tag
                                </button>
                                <a href="add_tag.php" class="wp-button-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Sidebar -->
                    <div>
                        <!-- Tag Information -->
                        <div class="wp-info-card">
                            <div class="wp-info-header">
                                <h3>Tag Information</h3>
                            </div>
                            <div class="wp-info-body">
                                <div class="wp-info-item">
                                    <span class="wp-info-label">Tag ID:</span>
                                    <span class="wp-info-value">#<?= $tag['id'] ?></span>
                                </div>
                                <div class="wp-info-item">
                                    <span class="wp-info-label">Posts Using Tag:</span>
                                    <span class="wp-info-value"><?= $usage_count ?></span>
                                </div>
                                <div class="wp-info-item">
                                    <span class="wp-info-label">Created:</span>
                                    <span class="wp-info-value">
                                        <?= isset($tag['created_at']) ? date('M j, Y', strtotime($tag['created_at'])) : 'Unknown' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tips -->
                        <div class="wp-tips-card">
                            <h3>
                                <i class="fas fa-lightbulb"></i>
                                Tag Tips
                            </h3>
                            <ul class="wp-tips-list">
                                <li>Tags help organize and categorize your content</li>
                                <li>Use specific, descriptive tag names</li>
                                <li>Keep tag names concise and relevant</li>
                                <li>Avoid creating too many similar tags</li>
                                <li>Tags are typically more specific than categories</li>
                            </ul>
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

        // Generate slug from name
        function generateSlug() {
            const name = document.getElementById('name').value;
            const slug = name.toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            
            document.getElementById('slug').value = slug;
            document.getElementById('slugPreview').textContent = slug || 'tag-slug';
        }

        // Update slug preview when slug field changes
        document.getElementById('slug').addEventListener('input', function() {
            const slug = this.value || 'tag-slug';
            document.getElementById('slugPreview').textContent = slug;
        });

        // Form validation
        document.getElementById('tagForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('Please enter a tag name.');
                document.getElementById('name').focus();
                return false;
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

        // Auto-hide success messages
        <?php if ($success): ?>
        setTimeout(function() {
            const alert = document.querySelector('.wp-alert.success');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
