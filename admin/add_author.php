<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($username) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check for duplicate email
        $check_email = $conn->prepare("SELECT id FROM authors WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $error = "An author with this email address already exists.";
        } else {
            // Check for duplicate username
            $check_username = $conn->prepare("SELECT id FROM authors WHERE username = ?");
            $check_username->bind_param("s", $username);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $error = "This username is already taken. Please choose a different one.";
            } else {
                // Handle photo upload
                $photo = '';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $file_type = $_FILES['photo']['type'];
                    
                    if (in_array($file_type, $allowed_types)) {
                        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $photo = uniqid() . '.' . $file_extension;
                        $target = "../uploads/" . $photo;
                        
                        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                            $error = "Failed to upload photo. Please try again.";
                        }
                    } else {
                        $error = "Please upload a valid image file (JPEG, PNG, or GIF).";
                    }
                }
                
                if (empty($error)) {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO authors (name, email, username, password, photo, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("sssss", $name, $email, $username, $hashed_password, $photo);
                    
                    if ($stmt->execute()) {
                        $success = "Author added successfully!";
                        // Clear form data
                        $name = $email = $username = $password = '';
                    } else {
                        $error = "Failed to add author. Please try again.";
                    }
                }
            }
        }
    }
}

// Get total counts for sidebar badges
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Author - My CMS Admin</title>
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
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            --transition: all 0.3s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--wp-gray-100) 0%, #e8eaed 100%);
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .wp-admin-bar .site-name {
            color: var(--wp-white);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .wp-admin-bar .site-name:hover {
            color: var(--wp-blue-light);
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
            background: linear-gradient(135deg, var(--wp-blue), var(--wp-blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
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
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
        }

        .wp-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--wp-gray-200);
            background: linear-gradient(135deg, var(--wp-white) 0%, var(--wp-gray-100) 100%);
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
            background: linear-gradient(135deg, var(--wp-gray-100) 0%, rgba(0, 115, 170, 0.05) 100%);
            color: var(--wp-blue);
            transform: translateX(2px);
        }

        .wp-menu-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, var(--wp-blue), var(--wp-blue-light));
            border-radius: 0 2px 2px 0;
        }

        .wp-menu-icon {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        .wp-menu-badge {
            background: linear-gradient(135deg, var(--wp-error), #e74c3c);
            color: var(--wp-white);
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: auto;
            box-shadow: 0 2px 4px rgba(214, 54, 56, 0.3);
        }

        /* Main Content */
        .wp-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            background: transparent;
        }

        .wp-header {
            background: var(--wp-white);
            border-bottom: 1px solid var(--wp-gray-200);
            padding: 25px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .wp-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--wp-gray-900);
            margin: 0;
            background: linear-gradient(135deg, var(--wp-gray-900), var(--wp-gray-700));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--wp-blue), var(--wp-blue-light));
            color: var(--wp-white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(0, 115, 170, 0.3);
        }

        .wp-button:hover {
            background: linear-gradient(135deg, var(--wp-blue-dark), var(--wp-blue));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 115, 170, 0.4);
        }

        .wp-button-secondary {
            background: linear-gradient(135deg, var(--wp-white), var(--wp-gray-100));
            color: var(--wp-gray-700);
            border: 1px solid var(--wp-gray-300);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .wp-button-secondary:hover {
            background: linear-gradient(135deg, var(--wp-gray-100), var(--wp-gray-200));
            color: var(--wp-gray-900);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        /* Content Area */
        .wp-content {
            padding: 0px;
        }

        /* Form Container */
        .wp-form-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Form Card */
        .wp-form-card {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            border: 1px solid var(--wp-gray-200);
        }

        .wp-form-header {
            padding: 30px 40px;
            background: linear-gradient(135deg, var(--wp-blue), var(--wp-blue-light));
            color: var(--wp-white);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .wp-form-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .wp-form-header i {
            font-size: 28px;
            opacity: 0.9;
        }

        .wp-form-body {
            padding: 40px;
        }

        /* Form Grid */
        .wp-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .wp-form-grid.single {
            grid-template-columns: 1fr;
        }

        /* Form Fields */
        .wp-form-group {
            margin-bottom: 25px;
        }

        .wp-form-group:last-child {
            margin-bottom: 0;
        }

        .wp-form-label {
            display: block;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 10px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wp-form-label i {
            color: var(--wp-blue);
            font-size: 16px;
        }

        .wp-form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 15px;
            font-family: inherit;
            transition: var(--transition);
            background: var(--wp-white);
            font-weight: 500;
        }

        .wp-form-input:focus {
            outline: none;
            border-color: var(--wp-blue);
            box-shadow: 0 0 0 4px rgba(0, 115, 170, 0.1);
            transform: translateY(-1px);
        }

        .wp-form-input::placeholder {
            color: var(--wp-gray-500);
            font-weight: 400;
        }

        /* File Upload */
        .wp-form-file-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .wp-form-file {
            position: absolute;
            left: -9999px;
        }

        .wp-form-file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            padding: 30px;
            border: 3px dashed var(--wp-gray-300);
            border-radius: var(--border-radius);
            background: linear-gradient(135deg, var(--wp-gray-100), var(--wp-white));
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            font-weight: 600;
            color: var(--wp-gray-700);
        }

        .wp-form-file-label:hover {
            border-color: var(--wp-blue);
            background: linear-gradient(135deg, rgba(0, 115, 170, 0.05), var(--wp-white));
            color: var(--wp-blue);
            transform: translateY(-2px);
        }

        .wp-form-file-label i {
            font-size: 24px;
            color: var(--wp-blue);
        }

        .wp-form-help {
            font-size: 13px;
            color: var(--wp-gray-600);
            margin-top: 8px;
            font-style: italic;
        }

        /* Form Actions */
        .wp-form-actions {
            display: flex;
            gap: 20px;
            padding-top: 30px;
            border-top: 2px solid var(--wp-gray-200);
            margin-top: 40px;
            justify-content: center;
        }

        .wp-form-actions .wp-button {
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 700;
            min-width: 150px;
        }

        /* Alert Messages */
        .wp-alert {
            padding: 20px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            border-left: 5px solid;
        }

        .wp-alert i {
            font-size: 20px;
        }

        .wp-alert.success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left-color: var(--wp-success);
            box-shadow: 0 4px 12px rgba(0, 163, 42, 0.2);
        }

        .wp-alert.error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left-color: var(--wp-error);
            box-shadow: 0 4px 12px rgba(214, 54, 56, 0.2);
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
                padding: 20px 25px;
            }
            
            .wp-header h1 {
                font-size: 24px;
            }
            
            .wp-content {
                padding: 25px;
            }
            
            .wp-form-header {
                padding: 25px 30px;
            }
            
            .wp-form-body {
                padding: 30px 25px;
            }
            
            .wp-form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .wp-form-actions {
                flex-direction: column;
                align-items: stretch;
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

        /* Animation for form elements */
        .wp-form-group {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stagger animation delay */
        .wp-form-group:nth-child(1) { animation-delay: 0.1s; }
        .wp-form-group:nth-child(2) { animation-delay: 0.2s; }
        .wp-form-group:nth-child(3) { animation-delay: 0.3s; }
        .wp-form-group:nth-child(4) { animation-delay: 0.4s; }
        .wp-form-group:nth-child(5) { animation-delay: 0.5s; }
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
                    <h1>Add New Author</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="authors.php" class="wp-button wp-button-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Authors
                    </a>
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
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="wp-form-container">
                    <div class="wp-form-card">
                        <div class="wp-form-header">
                            <i class="fas fa-user-plus"></i>
                            <h2>Create New Author Account</h2>
                        </div>
                        <div class="wp-form-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="wp-form-grid">
                                    <div class="wp-form-group">
                                        <label for="name" class="wp-form-label">
                                            <i class="fas fa-user"></i>
                                            Full Name *
                                        </label>
                                        <input type="text" id="name" name="name" class="wp-form-input"
                                               placeholder="Enter author's full name"
                                               value="<?= htmlspecialchars($name ?? '') ?>"
                                               required>
                                        <div class="wp-form-help">The display name that will appear on posts</div>
                                    </div>

                                    <div class="wp-form-group">
                                        <label for="email" class="wp-form-label">
                                            <i class="fas fa-envelope"></i>
                                            Email Address *
                                        </label>
                                        <input type="email" id="email" name="email" class="wp-form-input"
                                               placeholder="author@example.com"
                                               value="<?= htmlspecialchars($email ?? '') ?>"
                                               required>
                                        <div class="wp-form-help">Used for notifications and account recovery</div>
                                    </div>
                                </div>

                                <div class="wp-form-grid">
                                    <div class="wp-form-group">
                                        <label for="username" class="wp-form-label">
                                            <i class="fas fa-at"></i>
                                            Username *
                                        </label>
                                        <input type="text" id="username" name="username" class="wp-form-input"
                                               placeholder="Enter unique username"
                                               value="<?= htmlspecialchars($username ?? '') ?>"
                                               required>
                                        <div class="wp-form-help">Used for login - must be unique</div>
                                    </div>

                                    <div class="wp-form-group">
                                        <label for="password" class="wp-form-label">
                                            <i class="fas fa-lock"></i>
                                            Password *
                                        </label>
                                        <input type="password" id="password" name="password" class="wp-form-input"
                                               placeholder="Enter secure password"
                                               required>
                                        <div class="wp-form-help">Minimum 6 characters - use a strong password</div>
                                    </div>
                                </div>

                                <div class="wp-form-grid single">
                                    <div class="wp-form-group">
                                        <label for="photo" class="wp-form-label">
                                            <i class="fas fa-camera"></i>
                                            Profile Photo
                                        </label>
                                        <div class="wp-form-file-wrapper">
                                            <input type="file" id="photo" name="photo" class="wp-form-file"
                                                   accept="image/jpeg,image/png,image/gif">
                                            <label for="photo" class="wp-form-file-label">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <div>
                                                    <div style="font-size: 16px; margin-bottom: 5px;">Choose Profile Photo</div>
                                                    <div style="font-size: 13px; color: var(--wp-gray-500);">JPEG, PNG, or GIF format</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="wp-form-help">Upload a professional profile photo for better engagement</div>
                                    </div>
                                </div>

                                <div class="wp-form-actions">
                                    <button type="submit" class="wp-button">
                                        <i class="fas fa-save"></i> Create Author
                                    </button>
                                    <a href="authors.php" class="wp-button wp-button-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
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
        setTimeout(function() {
            const successAlert = document.querySelector('.wp-alert.success');
            if (successAlert) {
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 300);
            }
        }, 5000);

        // File upload preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const label = document.querySelector('.wp-form-file-label');
            const fileName = e.target.files[0]?.name;
            
            if (fileName) {
                label.innerHTML = `
                    <i class="fas fa-check-circle" style="color: var(--wp-success);"></i>
                    <div>
                        <div style="font-size: 16px; margin-bottom: 5px; color: var(--wp-success);">Photo Selected</div>
                        <div style="font-size: 13px; color: var(--wp-gray-600);">${fileName}</div>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
