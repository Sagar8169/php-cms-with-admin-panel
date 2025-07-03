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
$error = "";
$success = "";

if (!$id) {
    die("Invalid category ID.");
}

// Securely fetch category
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$category = $res->fetch_assoc();

if (!$category) {
    die("Category not found.");
}
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Get category post count
$count_stmt = $conn->prepare("SELECT COUNT(*) as post_count FROM posts WHERE category = ?");
$count_stmt->bind_param("i", $id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$post_count = $count_result->fetch_assoc()['post_count'];

// Handle update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $slug = trim($_POST['slug']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        // Check for duplicate name (excluding current category)
        $check_stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $name, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "A category with this name already exists.";
        } else {
            // Generate slug if empty
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            } else {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug)));
            }
            
            // Check for duplicate slug (excluding current category)
            $slug_check_stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $slug_check_stmt->bind_param("si", $slug, $id);
            $slug_check_stmt->execute();
            $slug_check_result = $slug_check_stmt->get_result();
            
            if ($slug_check_result->num_rows > 0) {
                $error = "A category with this slug already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $slug, $description, $id);
                
                if ($stmt->execute()) {
                    $success = "Category updated successfully!";
                    // Refresh category data
                    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $category = $res->fetch_assoc();
                } else {
                    $error = "Error updating category.";
                }
            }
        }
    }
}

// Get total posts count for sidebar badge
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Category - My CMS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

        /* Form Styles */
        .wp-form-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            align-items: start;
        }

        .wp-form-card {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .wp-form-header {
            background: var(--wp-gray-100);
            padding: 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            font-weight: 600;
            color: var(--wp-gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-form-body {
            padding: 30px;
        }

        .wp-form-field {
            margin-bottom: 25px;
        }

        .wp-form-field:last-child {
            margin-bottom: 0;
        }

        .wp-form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--wp-gray-900);
        }

        .wp-form-input,
        .wp-form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--wp-white);
            transition: var(--transition);
            font-family: inherit;
        }

        .wp-form-input:focus,
        .wp-form-textarea:focus {
            border-color: var(--wp-blue);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
        }

        .wp-form-textarea {
            height: 100px;
            resize: vertical;
        }

        .wp-form-description {
            font-size: 13px;
            color: var(--wp-gray-600);
            margin-top: 6px;
            line-height: 1.4;
        }

        .wp-form-actions {
            background: var(--wp-gray-100);
            padding: 20px 30px;
            border-top: 1px solid var(--wp-gray-200);
            display: flex;
            gap: 15px;
        }

        /* Info Box */
        .wp-info-box {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }

        .wp-info-header {
            background: var(--wp-gray-100);
            padding: 15px 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            font-weight: 600;
            color: var(--wp-gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wp-info-body {
            padding: 20px;
        }

        .wp-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-info-item:last-child {
            border-bottom: none;
        }

        .wp-info-label {
            font-weight: 600;
            color: var(--wp-gray-900);
        }

        .wp-info-value {
            color: var(--wp-gray-600);
        }

        /* Back Link */
        .wp-back-link {
            display: inline-flex;
            align-items: center;
            color: var(--wp-blue);
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }

        .wp-back-link:hover {
            color: var(--wp-blue-dark);
        }

        .wp-back-link i {
            margin-right: 8px;
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
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .wp-alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Slug Preview */
        .wp-slug-preview {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: var(--wp-gray-100);
            padding: 8px 12px;
            border-radius: var(--border-radius);
            font-size: 12px;
            color: var(--wp-gray-600);
            margin-top: 6px;
            border: 1px solid var(--wp-gray-200);
        }

        /* Tips List */
        .wp-tips-list {
            margin: 0;
            padding-left: 20px;
            color: var(--wp-gray-600);
            font-size: 13px;
            line-height: 1.6;
        }

        .wp-tips-list li {
            margin-bottom: 8px;
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
            
            .wp-form-container {
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
                    <a href="add_category.php" class="wp-menu-link active">
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
                    <h1>Edit Category</h1>
                </div>
                
                <div class="wp-header-actions">
                    <span style="color: var(--wp-gray-600); font-size: 14px;">
                        Editing "<?= htmlspecialchars($category['name']) ?>"
                    </span>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <a href="add_category.php" class="wp-back-link">
                    <i class="fas fa-arrow-left"></i> Back to Categories
                </a>

                <?php if ($error): ?>
                    <div class="wp-alert error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="wp-alert success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <div class="wp-form-container">
                    <!-- Main Form -->
                    <div class="wp-form-card">
                        <div class="wp-form-header">
                            <i class="fas fa-folder"></i>
                            Category Details
                        </div>
                        <form method="POST">
                            <div class="wp-form-body">
                                <div class="wp-form-field">
                                    <label for="name" class="wp-form-label">Category Name *</label>
                                    <input type="text" id="name" name="name" class="wp-form-input"
                                           value="<?= htmlspecialchars($category['name']) ?>" required onkeyup="generateSlug()">
                                    <div class="wp-form-description">The name is how it appears on your site.</div>
                                </div>

                                <div class="wp-form-field">
                                    <label for="slug" class="wp-form-label">Slug</label>
                                    <input type="text" id="slug" name="slug" class="wp-form-input"
                                           value="<?= htmlspecialchars($category['slug'] ?? '') ?>">
                                    <div class="wp-form-description">The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.</div>
                                    <div class="wp-slug-preview">URL: /category/<span id="slug-preview"><?= htmlspecialchars($category['slug'] ?? '') ?></span></div>
                                </div>

                                <div class="wp-form-field">
                                    <label for="description" class="wp-form-label">Description</label>
                                    <textarea id="description" name="description" class="wp-form-textarea"
                                              placeholder="Optional description for this category..."><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
                                    <div class="wp-form-description">The description is not prominent by default; however, some themes may show it.</div>
                                </div>
                            </div>

                            <div class="wp-form-actions">
                                <button type="submit" class="wp-button">
                                    <i class="fas fa-save"></i> Update Category
                                </button>
                                <a href="add_category.php" class="wp-button wp-button-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Sidebar Info -->
                    <div>
                        <div class="wp-info-box">
                            <div class="wp-info-header">
                                <i class="fas fa-info-circle"></i>
                                Category Information
                            </div>
                            <div class="wp-info-body">
                                <div class="wp-info-item">
                                    <span class="wp-info-label">ID:</span>
                                    <span class="wp-info-value">#<?= $category['id'] ?></span>
                                </div>
                                <div class="wp-info-item">
                                    <span class="wp-info-label">Posts:</span>
                                    <span class="wp-info-value"><?= $post_count ?> post<?= $post_count != 1 ? 's' : '' ?></span>
                                </div>
                                <div class="wp-info-item">
                                    <span class="wp-info-label">Created:</span>
                                    <span class="wp-info-value"><?= isset($category['created_at']) ? date('M j, Y', strtotime($category['created_at'])) : 'N/A' ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="wp-info-box">
                            <div class="wp-info-header">
                                <i class="fas fa-lightbulb"></i>
                                Tips
                            </div>
                            <div class="wp-info-body">
                                <ul class="wp-tips-list">
                                    <li>Category names should be descriptive and unique</li>
                                    <li>Keep slugs short and SEO-friendly</li>
                                    <li>Use descriptions to help organize your content</li>
                                    <li>Categories help visitors navigate your site</li>
                                </ul>
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

        function generateSlug() {
            const name = document.getElementById('name').value;
            const slugInput = document.getElementById('slug');
            const slugPreview = document.getElementById('slug-preview');
            
            if (name && !slugInput.dataset.manual) {
                const slug = name.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .trim('-');
                
                slugInput.value = slug;
                slugPreview.textContent = slug;
            }
        }

        // Mark slug as manually edited
        document.getElementById('slug').addEventListener('input', function() {
            this.dataset.manual = 'true';
            document.getElementById('slug-preview').textContent = this.value;
        });

        // Auto-hide success message
        setTimeout(function() {
            const successAlert = document.querySelector('.wp-alert.success');
            if (successAlert) {
                successAlert.style.opacity = '0';
                successAlert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 3000);

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
