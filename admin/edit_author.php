<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid author ID");
}

$author_id = intval($_GET['id']);
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Securely fetch author
$stmt = $conn->prepare("SELECT * FROM authors WHERE id = ?");
$stmt->bind_param("i", $author_id);
$stmt->execute();
$result = $stmt->get_result();
$author = $result->fetch_assoc();

if (!$author) {
    die("Author not found");
}

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($username)) {
        $error = "Name, email, and username are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check for duplicate email (excluding current author)
        $check_stmt = $conn->prepare("SELECT id FROM authors WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $author_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Email address already exists.";
        } else {
            // Check for duplicate username (excluding current author)
            $check_stmt = $conn->prepare("SELECT id FROM authors WHERE username = ? AND id != ?");
            $check_stmt->bind_param("si", $username, $author_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Username already exists.";
            } else {
                // Handle photo upload
                $photo_updated = false;
                $new_photo = $author['photo']; // Keep existing photo by default
                
                if (!empty($_FILES['photo']['name'])) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_type = $_FILES['photo']['type'];
                    
                    if (in_array($file_type, $allowed_types)) {
                        $photo_name = time() . '_' . basename($_FILES['photo']['name']);
                        $target_path = "../uploads/" . $photo_name;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                            $new_photo = $photo_name;
                            $photo_updated = true;
                            
                            // Delete old photo if it exists
                            if (!empty($author['photo']) && file_exists("../uploads/" . $author['photo'])) {
                                unlink("../uploads/" . $author['photo']);
                            }
                        } else {
                            $error = "Failed to upload photo.";
                        }
                    } else {
                        $error = "Please upload a valid image file (JPEG, PNG, or GIF).";
                    }
                }
                
                if (empty($error)) {
                    // Update author
                    if (!empty($password)) {
                        // Update with new password
                        if (strlen($password) < 6) {
                            $error = "Password must be at least 6 characters long.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE authors SET name=?, email=?, username=?, password=?, photo=? WHERE id=?");
                            $stmt->bind_param("sssssi", $name, $email, $username, $hashed_password, $new_photo, $author_id);
                        }
                    } else {
                        // Update without changing password
                        $stmt = $conn->prepare("UPDATE authors SET name=?, email=?, username=?, photo=? WHERE id=?");
                        $stmt->bind_param("ssssi", $name, $email, $username, $new_photo, $author_id);
                    }
                    
                    if (empty($error) && $stmt->execute()) {
                        $success = "Author updated successfully!";
                        // Refresh author data
                        $stmt = $conn->prepare("SELECT * FROM authors WHERE id = ?");
                        $stmt->bind_param("i", $author_id);
                        $stmt->execute();
                        $author = $stmt->get_result()->fetch_assoc();
                    } else {
                        $error = "Failed to update author.";
                    }
                }
            }
        }
    }
}

// Get total counts for sidebar badges
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Author - My CMS Admin</title>
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
            --transition: all 0.3s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--wp-gray-100) 0%, #e8f4f8 100%);
            color: var(--wp-gray-900);
            line-height: 1.6;
            min-height: 100vh;
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
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .wp-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: white;
            color: var(--wp-white);
        }

        .wp-sidebar-header h1 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            color: black;
            align-items: center;
            gap: 10px;
        }

        .wp-sidebar-header .version {
            font-size: 12px;
            opacity: 0.8;
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
            background: linear-gradient(135deg, var(--wp-gray-100) 0%, #e8f4f8 100%);
            color: var(--wp-blue);
        }

        .wp-menu-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, var(--wp-blue) 0%, var(--wp-blue-light) 100%);
        }

        .wp-menu-icon {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .wp-menu-badge {
            background: linear-gradient(135deg, var(--wp-error) 0%, #e74c3c 100%);
            color: var(--wp-white);
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Main Content */
        .wp-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            background: transparent;
        }

        .wp-header {
            background: linear-gradient(135deg, var(--wp-white) 0%, #f8fbff 100%);
            border-bottom: 1px solid var(--wp-gray-200);
            padding: 25px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .wp-header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .wp-header-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--wp-blue) 0%, var(--wp-blue-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--wp-white);
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(0,115,170,0.3);
        }

        .wp-header-text h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--wp-gray-900);
            margin: 0;
            background: linear-gradient(135deg, var(--wp-gray-900) 0%, var(--wp-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wp-header-text p {
            color: var(--wp-gray-600);
            margin: 5px 0 0 0;
            font-size: 16px;
        }

        /* Content Area */
        .wp-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Form Container */
        .wp-form-container {
            background: linear-gradient(135deg, var(--wp-white) 0%, #f8fbff 100%);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--wp-gray-200);
        }

        .wp-form-header {
            background: linear-gradient(135deg, var(--wp-blue) 0%, var(--wp-blue-dark) 100%);
            color: var(--wp-white);
            padding: 30px 40px;
            text-align: center;
        }

        .wp-form-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .wp-form-header i {
            font-size: 28px;
        }

        .wp-form-body {
            padding: 40px;
        }

        /* Grid Layout */
        .wp-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .wp-form-group {
            margin-bottom: 25px;
        }

        .wp-form-group.full-width {
            grid-column: 1 / -1;
        }

        .wp-form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--wp-gray-900);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wp-form-label i {
            color: var(--wp-blue);
            width: 16px;
        }

        .wp-form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--wp-gray-300);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            background: var(--wp-white);
        }

        .wp-form-input:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 3px rgba(0,115,170,0.1);
        }

        /* File Upload */
        .wp-file-upload {
            position: relative;
            border: 2px dashed var(--wp-gray-300);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, #f8fbff 0%, var(--wp-white) 100%);
            transition: var(--transition);
            cursor: pointer;
        }

        .wp-file-upload:hover {
            border-color: var(--wp-blue);
            background: linear-gradient(135deg, #e8f4f8 0%, var(--wp-white) 100%);
        }

        .wp-file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .wp-file-upload-content {
            pointer-events: none;
        }

        .wp-file-upload i {
            font-size: 48px;
            color: var(--wp-blue);
            margin-bottom: 15px;
            display: block;
        }

        .wp-file-upload-text {
            color: var(--wp-gray-700);
            font-size: 16px;
            font-weight: 500;
        }

        .wp-file-upload-hint {
            color: var(--wp-gray-500);
            font-size: 14px;
            margin-top: 8px;
        }

        /* Current Photo */
        .wp-current-photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .wp-current-photo img {
            max-width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 3px solid var(--wp-white);
        }

        .wp-current-photo-label {
            display: block;
            margin-bottom: 15px;
            font-weight: 600;
            color: var(--wp-gray-700);
        }

        /* Buttons */
        .wp-form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .wp-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--wp-blue) 0%, var(--wp-blue-dark) 100%);
            color: var(--wp-white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(0,115,170,0.3);
        }

        .wp-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,115,170,0.4);
        }

        .wp-button-secondary {
            background: linear-gradient(135deg, var(--wp-gray-600) 0%, var(--wp-gray-700) 100%);
            box-shadow: 0 4px 12px rgba(80,87,94,0.3);
        }

        .wp-button-secondary:hover {
            box-shadow: 0 6px 20px rgba(80,87,94,0.4);
        }

        /* Alert Messages */
        .wp-alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideInDown 0.5s ease-out;
        }

        .wp-alert.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .wp-alert.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .wp-alert i {
            font-size: 18px;
        }

        /* Password Field */
        .wp-password-group {
            position: relative;
        }

        .wp-password-hint {
            font-size: 12px;
            color: var(--wp-gray-500);
            margin-top: 5px;
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
                padding: 20px;
            }
            
            .wp-content {
                padding: 20px;
            }
            
            .wp-form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .wp-form-body {
                padding: 30px 20px;
            }
            
            .wp-form-actions {
                flex-direction: column;
            }
            
            .wp-button {
                justify-content: center;
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

        /* Animations */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .wp-form-container {
            animation: fadeIn 0.6s ease-out;
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
                    <i class="fas fa-newspaper"></i>
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
                <div class="wp-header-content">
                    <button class="wp-mobile-toggle" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="wp-header-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="wp-header-text">
                        <h1>Edit Author</h1>
                        <p>Update author information and credentials</p>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <?php if (!empty($success)): ?>
                    <div class="wp-alert success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="wp-alert error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="wp-form-container">
                    <div class="wp-form-header">
                        <h2>
                            <i class="fas fa-user-edit"></i>
                            Edit Author Details
                        </h2>
                    </div>

                    <div class="wp-form-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="wp-form-grid">
                                <div class="wp-form-group">
                                    <label for="name" class="wp-form-label">
                                        <i class="fas fa-user"></i>
                                        Full Name
                                    </label>
                                    <input type="text" id="name" name="name" class="wp-form-input"
                                           value="<?= htmlspecialchars($author['name']) ?>"
                                           placeholder="Enter full name" required>
                                </div>

                                <div class="wp-form-group">
                                    <label for="email" class="wp-form-label">
                                        <i class="fas fa-envelope"></i>
                                        Email Address
                                    </label>
                                    <input type="email" id="email" name="email" class="wp-form-input"
                                           value="<?= htmlspecialchars($author['email']) ?>"
                                           placeholder="Enter email address" required>
                                </div>

                                <div class="wp-form-group">
                                    <label for="username" class="wp-form-label">
                                        <i class="fas fa-at"></i>
                                        Username
                                    </label>
                                    <input type="text" id="username" name="username" class="wp-form-input"
                                           value="<?= htmlspecialchars($author['username']) ?>"
                                           placeholder="Enter username" required>
                                </div>

                                <div class="wp-form-group">
                                    <label for="password" class="wp-form-label">
                                        <i class="fas fa-lock"></i>
                                        New Password
                                    </label>
                                    <div class="wp-password-group">
                                        <input type="password" id="password" name="password" class="wp-form-input"
                                               placeholder="Leave blank to keep current password">
                                        <div class="wp-password-hint">
                                            Leave blank to keep current password. Minimum 6 characters if changing.
                                        </div>
                                    </div>
                                </div>

                                <div class="wp-form-group full-width">
                                    <label class="wp-form-label">
                                        <i class="fas fa-camera"></i>
                                        Profile Photo
                                    </label>
                                    
                                    <?php if (!empty($author['photo'])): ?>
                                        <div class="wp-current-photo">
                                            <span class="wp-current-photo-label">Current Photo:</span>
                                            <img src="../uploads/<?= htmlspecialchars($author['photo']) ?>" alt="Current Author Photo">
                                        </div>
                                    <?php endif; ?>

                                    <div class="wp-file-upload">
                                        <input type="file" name="photo" accept="image/*">
                                        <div class="wp-file-upload-content">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <div class="wp-file-upload-text">
                                                <?= !empty($author['photo']) ? 'Upload New Photo' : 'Upload Profile Photo' ?>
                                            </div>
                                            <div class="wp-file-upload-hint">
                                                Click to browse or drag and drop (JPEG, PNG, GIF)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wp-form-actions">
                                <button type="submit" class="wp-button">
                                    <i class="fas fa-save"></i>
                                    Update Author
                                </button>
                                <a href="authors.php" class="wp-button wp-button-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Authors
                                </a>
                            </div>
                        </form>
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

        // File Upload Enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[type="file"]');
            const uploadArea = document.querySelector('.wp-file-upload');
            const uploadText = document.querySelector('.wp-file-upload-text');
            const originalText = uploadText.textContent;

            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    uploadText.textContent = 'Selected: ' + this.files[0].name;
                    uploadArea.style.borderColor = 'var(--wp-success)';
                    uploadArea.style.background = 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)';
                } else {
                    uploadText.textContent = originalText;
                    uploadArea.style.borderColor = 'var(--wp-gray-300)';
                    uploadArea.style.background = 'linear-gradient(135deg, #f8fbff 0%, var(--wp-white) 100%)';
                }
            });

            // Auto-hide success messages
            const successAlert = document.querySelector('.wp-alert.success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        successAlert.remove();
                    }, 300);
                }, 5000);
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
