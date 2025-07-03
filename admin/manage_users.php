<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$admin_id = $_SESSION['admin'];
$success = $error = '';

// Handle filters
$role_filter = $_GET['role'] ?? '';
$search_filter = $_GET['search'] ?? '';
$filter_conditions = [];
$filter_params = [];
$filter_types = '';

if ($role_filter) {
    $filter_conditions[] = "role = ?";
    $filter_params[] = $role_filter;
    $filter_types .= 's';
}

if ($search_filter) {
    $filter_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $filter_params[] = "%$search_filter%";
    $filter_params[] = "%$search_filter%";
    $filter_types .= 'ss';
}

$filter_query = !empty($filter_conditions) ? " WHERE " . implode(" AND ", $filter_conditions) : '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $id = intval($_POST['user_id']);
        $new_name = trim($_POST['name']);
        $new_email = trim($_POST['email']);
        $new_password = trim($_POST['password']);
        $new_role = $_POST['role'] ?? '';

        // Validate email
        if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "❌ Invalid email format.";
        } else {
            $updates = [];
            $params = [];
            $types = '';

            if (!empty($new_name)) {
                $updates[] = "name = ?";
                $params[] = $new_name;
                $types .= 's';
            }

            if (!empty($new_email)) {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM authors WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $new_email, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "❌ Email already exists.";
                } else {
                    $updates[] = "email = ?";
                    $params[] = $new_email;
                    $types .= 's';
                }
                $stmt->close();
            }

            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $updates[] = "password = ?";
                $params[] = $hashed;
                $types .= 's';
            }

            if (!empty($new_role)) {
                $updates[] = "role = ?";
                $params[] = $new_role;
                $types .= 's';
            }

            if (!empty($updates) && !$error) {
                $params[] = $id;
                $types .= 'i';
                
                $stmt = $conn->prepare("UPDATE authors SET " . implode(", ", $updates) . " WHERE id = ?");
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success = "✅ User updated successfully.";
                } else {
                    $error = "❌ Error updating user.";
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['delete_user'])) {
        $id = intval($_POST['user_id']);
        $delete_posts = isset($_POST['delete_posts']);

        if ($id == $admin_id) {
            $error = "⚠️ You cannot delete your own account!";
        } else {
            if ($delete_posts) {
                $stmt = $conn->prepare("DELETE FROM posts WHERE author_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE author_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->bind_result($post_count);
                $stmt->fetch();
                $stmt->close();

                if ($post_count > 0) {
                    $error = "❌ Cannot delete. This user has $post_count posts. Check 'Delete Posts' to remove them.";
                }
            }

            if (!$error) {
                $stmt = $conn->prepare("DELETE FROM authors WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success = "🗑️ User deleted successfully.";
                } else {
                    $error = "❌ Error deleting user.";
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['add_user'])) {
        $name = trim($_POST['new_name']);
        $email = trim($_POST['new_email']);
        $password = $_POST['new_password'];
        $role = $_POST['new_role'];

        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            $error = "❌ All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "❌ Invalid email format.";
        } elseif (strlen($password) < 6) {
            $error = "❌ Password must be at least 6 characters.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM authors WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "❌ Email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO authors (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    $success = "✅ New user added successfully.";
                } else {
                    $error = "❌ Error adding user.";
                }
            }
            $stmt->close();
        }
    }

    if (isset($_POST['export_csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Created']);

        $query = "SELECT id, name, email, role, created_at FROM authors" . $filter_query . " ORDER BY id DESC";
        if (!empty($filter_params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($filter_types, ...$filter_params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Get users with filters
$query = "SELECT * FROM authors" . $filter_query . " ORDER BY id DESC";
if (!empty($filter_params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($filter_types, ...$filter_params);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query($query);
}

// Get statistics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalAdmins = $conn->query("SELECT COUNT(*) as count FROM authors WHERE role = 'admin'")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors WHERE role = 'author'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - My PHP CMS</title>
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

        .wp-button-danger {
            background: var(--wp-error);
        }

        .wp-button-danger:hover {
            background: #b91c1c;
        }

        .wp-button-small {
            padding: 6px 12px;
            font-size: 12px;
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

        .wp-filters-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
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

        /* Users Table */
        .wp-users-container {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .wp-users-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--wp-gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .wp-users-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-table {
            width: 100%;
            border-collapse: collapse;
        }

        .wp-table th,
        .wp-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-table th {
            background: var(--wp-gray-100);
            font-weight: 600;
            color: var(--wp-gray-900);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .wp-table tbody tr:hover {
            background: var(--wp-gray-100);
        }

        .wp-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--wp-blue);
            color: var(--wp-white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-right: 10px;
        }

        .wp-user-info {
            display: flex;
            align-items: center;
        }

        .wp-user-details h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--wp-gray-900);
        }

        .wp-user-details p {
            margin: 2px 0 0 0;
            font-size: 13px;
            color: var(--wp-gray-600);
        }

        .wp-role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .wp-role-badge.admin {
            background: #fef3c7;
            color: #92400e;
        }

        .wp-role-badge.author {
            background: #dbeafe;
            color: #1e40af;
        }

        .wp-user-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Add User Form */
        .wp-add-user {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-top: 30px;
        }

        .wp-add-user-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--wp-gray-200);
        }

        .wp-add-user-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wp-add-user-content {
            padding: 30px;
        }

        .wp-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .wp-form-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        /* Modal */
        .wp-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .wp-modal.active {
            display: flex;
        }

        .wp-modal-content {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .wp-modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--wp-gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .wp-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-900);
            margin: 0;
        }

        .wp-modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--wp-gray-500);
            cursor: pointer;
            padding: 5px;
        }

        .wp-modal-body {
            padding: 30px;
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
            
            .wp-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .wp-table {
                font-size: 14px;
            }
            
            .wp-table th,
            .wp-table td {
                padding: 10px;
            }
            
            .wp-user-actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .wp-filters-grid {
                grid-template-columns: 1fr;
            }
            
            .wp-form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .wp-stats {
                grid-template-columns: 1fr;
            }
            
            .wp-header h1 {
                font-size: 20px;
            }
            
            .wp-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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
                    <a href="add_tag.php" class="wp-menu-link">
                        <i class="fas fa-tags wp-menu-icon"></i>
                        Manage Tags
                    </a>
                </li>
                <li class="wp-menu-item">
                    <a href="manage_users.php" class="wp-menu-link active">
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
                    <h1>Manage Users</h1>
                </div>
                
                <div class="wp-header-actions">
                    <a href="dashboard.php" class="wp-button wp-button-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <!-- Stats Cards -->
                <div class="wp-stats">
                    <div class="wp-stat-card">
                        <div class="wp-stat-number"><?= $totalUsers ?></div>
                        <div class="wp-stat-label">Total Users</div>
                        <i class="fas fa-users wp-stat-icon"></i>
                    </div>
                    <div class="wp-stat-card warning">
                        <div class="wp-stat-number"><?= $totalAdmins ?></div>
                        <div class="wp-stat-label">Administrators</div>
                        <i class="fas fa-user-shield wp-stat-icon"></i>
                    </div>
                    <div class="wp-stat-card success">
                        <div class="wp-stat-number"><?= $totalAuthors ?></div>
                        <div class="wp-stat-label">Authors</div>
                        <i class="fas fa-user-edit wp-stat-icon"></i>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div class="wp-alert success"><?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="wp-alert error"><?= $error ?></div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="wp-filters">
                    <form method="GET" id="filterForm">
                        <div class="wp-filters-grid">
                            <div class="wp-form-group">
                                <label class="wp-form-label">Filter by Role</label>
                                <select name="role" class="wp-form-control" onchange="document.getElementById('filterForm').submit()">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                    <option value="author" <?= $role_filter === 'author' ? 'selected' : '' ?>>Author</option>
                                </select>
                            </div>
                            <div class="wp-form-group">
                                <label class="wp-form-label">Search Users</label>
                                <div class="wp-search">
                                    <i class="fas fa-search wp-search-icon"></i>
                                    <input type="text" name="search" class="wp-form-control" placeholder="Search by name or email..." value="<?= htmlspecialchars($search_filter) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="wp-filters-actions">
                            <button type="submit" class="wp-button wp-button-small">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                            <a href="manage_users.php" class="wp-button wp-button-secondary wp-button-small">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    </form>
                    
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--wp-gray-200);">
                        <form method="POST" style="display: inline-block;">
                            <button type="submit" name="export_csv" class="wp-button wp-button-secondary wp-button-small">
                                <i class="fas fa-download"></i>
                                Export CSV
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="wp-users-container">
                    <div class="wp-users-header">
                        <h2 class="wp-users-title">
                            <i class="fas fa-users"></i>
                            All Users
                        </h2>
                    </div>
                    
                    <table class="wp-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Posts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users->num_rows > 0): ?>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <?php
                                    // Get post count for this user
                                    $post_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM posts WHERE author_id = ?");
                                    $post_count_stmt->bind_param("i", $user['id']);
                                    $post_count_stmt->execute();
                                    $post_count = $post_count_stmt->get_result()->fetch_assoc()['count'];
                                    $post_count_stmt->close();
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="wp-user-info">
                                                <div class="wp-user-avatar">
                                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                </div>
                                                <div class="wp-user-details">
                                                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                                                    <p><?= htmlspecialchars($user['email']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="wp-role-badge <?= $user['role'] ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($post_count > 0): ?>
                                                <a href="posts.php?author=<?= $user['id'] ?>" style="color: var(--wp-blue); text-decoration: none;">
                                                    <?= $post_count ?> posts
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--wp-gray-500);">No posts</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="wp-user-actions">
                                                <button onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= $user['role'] ?>')" class="wp-button wp-button-small">
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <?php if ($user['id'] != $admin_id): ?>
                                                    <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', <?= $post_count ?>)" class="wp-button wp-button-danger wp-button-small">
                                                        <i class="fas fa-trash"></i>
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--wp-gray-600);">
                                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; display: block; color: var(--wp-gray-400);"></i>
                                        No users found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add User Form -->
                <div class="wp-add-user">
                    <div class="wp-add-user-header">
                        <h2 class="wp-add-user-title">
                            <i class="fas fa-user-plus"></i>
                            Add New User
                        </h2>
                    </div>
                    <div class="wp-add-user-content">
                        <form method="POST">
                            <div class="wp-form-grid">
                                <div class="wp-form-group">
                                    <label class="wp-form-label">Full Name</label>
                                    <input type="text" name="new_name" class="wp-form-control" placeholder="Enter full name" required>
                                </div>
                                <div class="wp-form-group">
                                    <label class="wp-form-label">Email Address</label>
                                    <input type="email" name="new_email" class="wp-form-control" placeholder="Enter email address" required>
                                </div>
                                <div class="wp-form-group">
                                    <label class="wp-form-label">Password</label>
                                    <input type="password" name="new_password" class="wp-form-control" placeholder="Enter password (min. 6 characters)" required minlength="6">
                                </div>
                                <div class="wp-form-group">
                                    <label class="wp-form-label">Role</label>
                                    <select name="new_role" class="wp-form-control" required>
                                        <option value="author">Author</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                            </div>
                            <div class="wp-form-actions">
                                <button type="submit" name="add_user" class="wp-button">
                                    <i class="fas fa-user-plus"></i>
                                    Add User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit User Modal -->
    <div class="wp-modal" id="editModal">
        <div class="wp-modal-content">
            <div class="wp-modal-header">
                <h3 class="wp-modal-title">Edit User</h3>
                <button class="wp-modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <div class="wp-modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="wp-form-group" style="margin-bottom: 20px;">
                        <label class="wp-form-label">Full Name</label>
                        <input type="text" name="name" id="editName" class="wp-form-control" placeholder="Leave empty to keep current">
                    </div>
                    <div class="wp-form-group" style="margin-bottom: 20px;">
                        <label class="wp-form-label">Email Address</label>
                        <input type="email" name="email" id="editEmail" class="wp-form-control" placeholder="Leave empty to keep current">
                    </div>
                    <div class="wp-form-group" style="margin-bottom: 20px;">
                        <label class="wp-form-label">New Password</label>
                        <input type="password" name="password" id="editPassword" class="wp-form-control" placeholder="Leave empty to keep current">
                    </div>
                    <div class="wp-form-group" style="margin-bottom: 25px;">
                        <label class="wp-form-label">Role</label>
                        <select name="role" id="editRole" class="wp-form-control">
                            <option value="">Keep current role</option>
                            <option value="author">Author</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="wp-form-actions">
                        <button type="submit" name="update_user" class="wp-button">
                            <i class="fas fa-save"></i>
                            Update User
                        </button>
                        <button type="button" onclick="closeModal('editModal')" class="wp-button wp-button-secondary">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="wp-modal" id="deleteModal">
        <div class="wp-modal-content">
            <div class="wp-modal-header">
                <h3 class="wp-modal-title">Delete User</h3>
                <button class="wp-modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="wp-modal-body">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <p style="margin-bottom: 20px; color: var(--wp-gray-700);">
                        Are you sure you want to delete <strong id="deleteUserName"></strong>?
                    </p>
                    <div id="deletePostsOption" style="margin-bottom: 20px; padding: 15px; background: var(--wp-gray-100); border-radius: var(--border-radius);">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="delete_posts" id="deletePostsCheckbox">
                            <span>Also delete all posts by this user (<span id="postCount"></span> posts)</span>
                        </label>
                        <p style="margin: 10px 0 0 30px; font-size: 13px; color: var(--wp-gray-600);">
                            If unchecked, the user cannot be deleted if they have posts.
                        </p>
                    </div>
                    <div class="wp-form-actions">
                        <button type="submit" name="delete_user" class="wp-button wp-button-danger">
                            <i class="fas fa-trash"></i>
                            Delete User
                        </button>
                        <button type="button" onclick="closeModal('deleteModal')" class="wp-button wp-button-secondary">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('wpSidebar');
            sidebar.classList.toggle('open');
        }

        function editUser(id, name, email, role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editName').value = '';
            document.getElementById('editEmail').value = '';
            document.getElementById('editPassword').value = '';
            document.getElementById('editRole').value = '';
            
            // Set placeholders with current values
            document.getElementById('editName').placeholder = `Current: ${name}`;
            document.getElementById('editEmail').placeholder = `Current: ${email}`;
            
            document.getElementById('editModal').classList.add('active');
        }

        function deleteUser(id, name, postCount) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('postCount').textContent = postCount;
            
            const deletePostsOption = document.getElementById('deletePostsOption');
            if (postCount > 0) {
                deletePostsOption.style.display = 'block';
            } else {
                deletePostsOption.style.display = 'none';
            }
            
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('wp-modal')) {
                e.target.classList.remove('active');
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
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const name = document.getElementById('editName').value.trim();
            const email = document.getElementById('editEmail').value.trim();
            const password = document.getElementById('editPassword').value.trim();
            
            if (!name && !email && !password && !document.getElementById('editRole').value) {
                e.preventDefault();
                alert('Please fill at least one field to update.');
                return false;
            }
            
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            if (password && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    </script>
</body>
</html>
