<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$msg = "";
$msg_class = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $msg = "✅ Tag added successfully!";
            $msg_class = "success";
        } else {
            $msg = "❌ Error: " . $conn->error;
            $msg_class = "error";
        }
    } else {
        $msg = "⚠️ Tag name cannot be empty.";
        $msg_class = "warning";
    }
}
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Get statistics
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalViews = $conn->query("SELECT SUM(views) as count FROM posts")->fetch_assoc()['count'];
$popularPost = $conn->query("SELECT title, views FROM posts ORDER BY views DESC LIMIT 1")->fetch_assoc();
$tags = $conn->query("SELECT * FROM tags ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tags Management - My PHP CMS</title>
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
            --header-height: 60px;
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
        }

        .wp-button-secondary:hover {
            background-color: var(--wp-gray-100);
            border-color: var(--wp-gray-400);
            color: var(--wp-gray-900);
        }

        /* Content Area */
        .wp-content {
            padding: 30px;
        }

        /* Form Styles */
        .wp-form-container {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .wp-form-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: var(--wp-white);
        }

        .wp-form-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-form-content {
            padding: 30px;
        }

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

        .wp-form-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 25px;
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

        .wp-alert.warning {
            background: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }

        /* Tags List */
        .wp-tags-container {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .wp-tags-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--wp-gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .wp-tags-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-tag-count {
            background: var(--wp-gray-200);
            color: var(--wp-gray-700);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .wp-tags-list {
            padding: 0;
        }

        .wp-tag-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            border-bottom: 1px solid var(--wp-gray-200);
            transition: var(--transition);
        }

        .wp-tag-item:last-child {
            border-bottom: none;
        }

        .wp-tag-item:hover {
            background: var(--wp-gray-100);
        }

        .wp-tag-name {
            font-weight: 500;
            color: var(--wp-gray-900);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-tag-actions {
            display: flex;
            gap: 15px;
        }

        .wp-tag-action {
            color: var(--wp-blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
            padding: 5px 10px;
            border-radius: 4px;
        }

        .wp-tag-action:hover {
            background: var(--wp-gray-100);
            color: var(--wp-blue-dark);
        }

        .wp-tag-action.danger {
            color: var(--wp-error);
        }

        .wp-tag-action.danger:hover {
            background: #fef2f2;
            color: #b91c1c;
        }

        .wp-empty-state {
            padding: 60px 30px;
            text-align: center;
            color: var(--wp-gray-600);
        }

        .wp-empty-state i {
            font-size: 48px;
            color: var(--wp-gray-400);
            margin-bottom: 20px;
        }

        .wp-empty-state h3 {
            font-size: 18px;
            color: var(--wp-gray-700);
            margin-bottom: 10px;
        }

        .wp-empty-state p {
            font-size: 14px;
            color: var(--wp-gray-600);
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
            
            .wp-form-content {
                padding: 20px;
            }
            
            .wp-tag-item {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .wp-tag-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        @media (max-width: 480px) {
            .wp-header h1 {
                font-size: 20px;
            }
            
            .wp-form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .wp-button {
                justify-content: center;
            }
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
                    <h1>Manage Tags</h1>
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
                <!-- Add Tag Form -->
                <div class="wp-form-container">
                    <div class="wp-form-header">
                        <h2 class="wp-form-title">
                            <i class="fas fa-plus-circle"></i>
                            Add New Tag
                        </h2>
                    </div>
                    <div class="wp-form-content">
                        <?php if ($msg): ?>
                            <div class="wp-alert <?= $msg_class ?>">
                                <?= $msg ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="wp-form-group">
                                <label for="tag-name" class="wp-form-label">Tag Name</label>
                                <input
                                    type="text"
                                    id="tag-name"
                                    name="name"
                                    class="wp-form-control"
                                    placeholder="Enter tag name (e.g., Technology, News, Tutorial)"
                                    required
                                    maxlength="50"
                                >
                            </div>
                            
                            <div class="wp-form-actions">
                                <button type="submit" class="wp-button">
                                    <i class="fas fa-plus"></i>
                                    Add Tag
                                </button>
                                <a href="dashboard.php" class="wp-button wp-button-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tags List -->
                <div class="wp-tags-container">
                    <div class="wp-tags-header">
                        <h2 class="wp-tags-title">
                            <i class="fas fa-tags"></i>
                            All Tags
                            <span class="wp-tag-count"><?= $tags->num_rows ?></span>
                        </h2>
                    </div>
                    
                    <div class="wp-tags-list">
                        <?php if ($tags->num_rows > 0): ?>
                            <?php while ($tag = $tags->fetch_assoc()): ?>
                                <div class="wp-tag-item">
                                    <div class="wp-tag-name">
                                        <i class="fas fa-tag" style="color: var(--wp-gray-500);"></i>
                                        <?= htmlspecialchars($tag['name']) ?>
                                    </div>
                                    <div class="wp-tag-actions">
                                        <a href="edit_tag.php?id=<?= $tag['id'] ?>" class="wp-tag-action">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        <a href="delete_tag.php?id=<?= $tag['id'] ?>"
                                           class="wp-tag-action danger"
                                           onclick="return confirm('Are you sure you want to delete this tag? This action cannot be undone.')">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="wp-empty-state">
                                <i class="fas fa-tags"></i>
                                <h3>No tags found</h3>
                                <p>Start by adding your first tag using the form above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('wpSidebar');
            sidebar.classList.toggle('open');
        }

        // Auto-hide success messages after 5 seconds
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
        document.querySelector('form').addEventListener('submit', function(e) {
            const tagName = document.getElementById('tag-name').value.trim();
            if (tagName.length < 2) {
                e.preventDefault();
                alert('Tag name must be at least 2 characters long.');
                return false;
            }
            if (tagName.length > 50) {
                e.preventDefault();
                alert('Tag name cannot exceed 50 characters.');
                return false;
            }
        });
    </script>
</body>
</html>
