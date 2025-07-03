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
$msg_type = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                
                if (!empty($name)) {
                    // Check if category already exists
                    $check_stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                    $check_stmt->bind_param("s", $name);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $msg = "Category already exists!";
                        $msg_type = "error";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
                        $stmt->bind_param("ss", $name, $description);
                        
                        if ($stmt->execute()) {
                            $msg = "Category added successfully!";
                            $msg_type = "success";
                        } else {
                            $msg = "Error: " . $conn->error;
                            $msg_type = "error";
                        }
                    }
                } else {
                    $msg = "Category name cannot be empty.";
                    $msg_type = "error";
                }
                break;
                
            case 'bulk_delete':
                if (isset($_POST['selected_categories']) && is_array($_POST['selected_categories'])) {
                    $ids = array_map('intval', $_POST['selected_categories']);
                    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                    
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                    
                    if ($stmt->execute()) {
                        $msg = count($ids) . " categories deleted successfully!";
                        $msg_type = "success";
                    } else {
                        $msg = "Error deleting categories: " . $conn->error;
                        $msg_type = "error";
                    }
                }
                break;
        }
    }
}

// Handle individual delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $msg = "Category deleted successfully!";
        $msg_type = "success";
    } else {
        $msg = "Error deleting category: " . $conn->error;
        $msg_type = "error";
    }
}

// Pagination and search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build search query
$search_condition = '';
$search_params = [];
$param_types = '';

if (!empty($search)) {
    $search_condition = "WHERE name LIKE ? OR description LIKE ?";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term];
    $param_types = 'ss';
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM categories $search_condition";
if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$search_params);
    $count_stmt->execute();
    $total_categories = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_categories = $conn->query($count_query)->fetch_assoc()['total'];
}

// Get categories with post counts
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

$allowed_sorts = ['name', 'description', 'post_count', 'created_at'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'name';
}

$query = "SELECT c.*, 
          COUNT(p.id) as post_count,
          DATE_FORMAT(c.created_at, '%M %d, %Y') as formatted_date
          FROM categories c 
          LEFT JOIN posts p ON c.name = p.category 
          $search_condition
          GROUP BY c.id 
          ORDER BY $sort $order 
          LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $search_params[] = $per_page;
    $search_params[] = $offset;
    $param_types .= 'ii';
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$search_params);
    $stmt->execute();
    $categories = $stmt->get_result();
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $per_page, $offset);
    $stmt->execute();
    $categories = $stmt->get_result();
}

$total_pages = ceil($total_categories / $per_page);
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];
// Fetch all authors
$authors = $conn->query("SELECT id, name FROM authors ORDER BY name");

// Fetch all posts with authors
$posts = $conn->query("SELECT posts.*, authors.name AS author_name FROM posts LEFT JOIN authors ON posts.author_id = authors.id ORDER BY posts.created_at DESC");

// Get statistics
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalViews = $conn->query("SELECT SUM(views) as count FROM posts")->fetch_assoc()['count'];
$popularPost = $conn->query("SELECT title, views FROM posts ORDER BY views DESC LIMIT 1")->fetch_assoc();

// Get statistics
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];

$stats = $conn->query("SELECT 
    COUNT(*) as total_categories,
    COUNT(CASE WHEN EXISTS(SELECT 1 FROM posts WHERE posts.category = categories.name) THEN 1 END) as active_categories,
    (SELECT COUNT(*) FROM posts WHERE category IS NOT NULL AND category != '') as total_posts
    FROM categories")->fetch_assoc();

$stats['avg_posts'] = $stats['total_categories'] > 0 ? round($stats['total_posts'] / $stats['total_categories'], 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Categories Management - My CMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f1f1;
            color: #333;
        }
        
        .admin-bar {
            background: #23282d;
            color: white;
            padding: 0 20px;
            height: 32px;
            display: flex;
            align-items: center;
            font-size: 13px;
        }
        
        .admin-bar a {
            color: #b4b9be;
            text-decoration: none;
            margin-right: 20px;
        }
        
        .admin-bar a:hover {
            color: #00b9eb;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #ccd0d4;
            padding: 20px;
        }
        
        .header h1 {
            font-size: 23px;
            font-weight: 400;
            color: #23282d;
            margin: 0;
        }
        
        .main-container {
            display: flex;
            min-height: calc(100vh - 52px);
        }
        
        .sidebar {
            width: 160px;
            background: #23282d;
            padding: 0;
        }
        
        .sidebar ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .sidebar li {
            border-bottom: 1px solid #32373c;
        }
        
        .sidebar a {
            display: block;
            padding: 8px 12px;
            color: #b4b9be;
            text-decoration: none;
            font-size: 13px;
        }
        
        .sidebar a:hover,
        .sidebar a.current {
            background: #0073aa;
            color: white;
        }
        
        .content {
            flex: 1;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #0073aa;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            font-size: 23px;
            font-weight: 400;
            color: #23282d;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #005a87;
        }
        
        .btn-secondary {
            background: #f7f7f7;
            color: #555;
            border: 1px solid #ccc;
        }
        
        .btn-secondary:hover {
            background: #fafafa;
        }
        
        .btn-danger {
            background: #dc3232;
        }
        
        .btn-danger:hover {
            background: #a02622;
        }
        
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #23282d;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .table-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-box input {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .bulk-actions select {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
        }
        
        th a {
            color: #555;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        th a:hover {
            color: #0073aa;
        }
        
        .sort-icon {
            font-size: 12px;
            opacity: 0.5;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .category-name {
            font-weight: 600;
            color: #0073aa;
        }
        
        .post-count {
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .post-count.zero {
            background: #666;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .actions a {
            color: #0073aa;
            text-decoration: none;
            font-size: 13px;
        }
        
        .actions a:hover {
            text-decoration: underline;
        }
        
        .actions .delete {
            color: #dc3232;
        }
        
        .pagination {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #e1e1e1;
        }
        
        .pagination-info {
            color: #666;
            font-size: 13px;
        }
        
        .pagination-links {
            display: flex;
            gap: 5px;
        }
        
        .pagination-links a,
        .pagination-links span {
            padding: 6px 12px;
            text-decoration: none;
            color: #0073aa;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .pagination-links .current {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .pagination-links a:hover {
            background: #f8f9fa;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }

        
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                order: 2;
            }
            
            .sidebar ul {
                display: flex;
                overflow-x: auto;
            }
            
            .sidebar li {
                border-bottom: none;
                border-right: 1px solid #32373c;
                white-space: nowrap;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                justify-content: stretch;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .table-responsive {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            .actions {
                flex-direction: column;
                gap: 5px;
            }
        }

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
            <h1>Manage Categories</h1>
        </div>
        
        <div class="wp-header-actions">
        
            <a href="create.php" class="wp-button">
                <i class="fas fa-plus"></i>
                Add New Post
            </a>
        </div>
    </header>

    

        <main class="content">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $msg_type ?>">
                    <?= $msg_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_categories'] ?></div>
                    <div class="stat-label">Total Categories</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active_categories'] ?></div>
                    <div class="stat-label">Active Categories</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_posts'] ?></div>
                    <div class="stat-label">Total Posts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['avg_posts'] ?></div>
                    <div class="stat-label">Avg Posts/Category</div>
                </div>
            </div>

            <!-- Add New Category Form -->
            <div class="form-section" id="add-form">
                <div class="page-header">
                    <h2 class="page-title">Add New Category</h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Category Name *</label>
                            <input type="text" id="name" name="name" class="form-control"
                                   placeholder="Enter category name" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control"
                                      placeholder="Optional category description"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn">Add Category</button>
                </form>
            </div>

            <!-- Categories Table -->
            <div class="table-section">
                <div class="table-header">
                    <div class="search-box">
                        <form method="GET" style="display: flex; gap: 10px;">
                            <input type="text" name="search" placeholder="Search categories..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-secondary">Search</button>
                            <?php if ($search): ?>
                                <a href="add_category.php" class="btn btn-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <form method="POST" class="bulk-actions" id="bulk-form">
                        <input type="hidden" name="action" value="bulk_delete">
                        <select name="bulk_action" id="bulk-action">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-danger" onclick="return confirmBulkAction()">Apply</button>
                    </form>
                </div>

                <?php if ($categories->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all" onchange="toggleAll(this)">
                                    </th>
                                    <th>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => ($sort === 'name' && $order === 'ASC') ? 'desc' : 'asc'])) ?>">
                                            Name
                                            <?php if ($sort === 'name'): ?>
                                                <span class="sort-icon"><?= $order === 'ASC' ? '↑' : '↓' ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Description</th>
                                    <th>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'post_count', 'order' => ($sort === 'post_count' && $order === 'ASC') ? 'desc' : 'asc'])) ?>">
                                            Posts
                                            <?php if ($sort === 'post_count'): ?>
                                                <span class="sort-icon"><?= $order === 'ASC' ? '↑' : '↓' ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => ($sort === 'created_at' && $order === 'ASC') ? 'desc' : 'asc'])) ?>">
                                            Created
                                            <?php if ($sort === 'created_at'): ?>
                                                <span class="sort-icon"><?= $order === 'ASC' ? '↑' : '↓' ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_categories[]"
                                                   value="<?= $category['id'] ?>" form="bulk-form">
                                        </td>
                                        <td>
                                            <div class="category-name">
                                                <?= htmlspecialchars($category['name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($category['description'] ?: 'No description') ?>
                                        </td>
                                        <td>
                                            <span class="post-count <?= $category['post_count'] == 0 ? 'zero' : '' ?>">
                                                <?= $category['post_count'] ?>
                                            </span>
                                        </td>
                                        <td><?= $category['formatted_date'] ?></td>
                                        <td>
                                            <div class="actions">
                                                <a href="edit_category.php?id=<?= $category['id'] ?>">Edit</a>
                                        
                                                <a href="?delete=<?= $category['id'] ?>" class="delete"
                                                   onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <?= ($page - 1) * $per_page + 1 ?> to <?= min($page * $per_page, $total_categories) ?>
                                of <?= $total_categories ?> categories
                            </div>
                            <div class="pagination-links">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Categories Found</h3>
                        <p>
                            <?php if ($search): ?>
                                No categories match your search criteria.
                                <br><a href="add_category.php">Clear search</a> to see all categories.
                            <?php else: ?>
                                You haven't created any categories yet.
                                <br>Use the form above to add your first category.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_categories[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

        function confirmBulkAction() {
            const action = document.getElementById('bulk-action').value;
            const selected = document.querySelectorAll('input[name="selected_categories[]"]:checked');
            
            if (!action) {
                alert('Please select an action.');
                return false;
            }
            
            if (selected.length === 0) {
                alert('Please select at least one category.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm(`Are you sure you want to delete ${selected.length} selected categories?`);
            }
            
            return true;
        }

        // Auto-submit search form on Enter
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
