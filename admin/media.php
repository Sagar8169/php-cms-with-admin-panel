<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$mediaDir = "../uploads";
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$maxFileSize = 10 * 1024 * 1024; // 10MB

// Create uploads directory if it doesn't exist
if (!is_dir($mediaDir)) {
    mkdir($mediaDir, 0755, true);
}

// Get statistics
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$totalAuthors = $conn->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
$totalPages = $conn->query("SELECT COUNT(*) as count FROM pages")->fetch_assoc()['count'];

// Get media files with detailed info
$mediaFiles = [];
$files = glob("$mediaDir/*.{jpg,jpeg,png,gif,webp,svg}", GLOB_BRACE);

foreach ($files as $file) {
    $fileInfo = [
        'path' => $file,
        'name' => basename($file),
        'size' => filesize($file),
        'modified' => filemtime($file),
        'type' => mime_content_type($file),
        'dimensions' => null
    ];
    
    // Get image dimensions
    if (in_array($fileInfo['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        $dimensions = getimagesize($file);
        if ($dimensions) {
            $fileInfo['dimensions'] = $dimensions[0] . 'x' . $dimensions[1];
        }
    }
    
    $mediaFiles[] = $fileInfo;
}

// Sort files
$sortBy = $_GET['sort'] ?? 'date_desc';
switch ($sortBy) {
    case 'date_asc':
        usort($mediaFiles, fn($a, $b) => $a['modified'] - $b['modified']);
        break;
    case 'date_desc':
        usort($mediaFiles, fn($a, $b) => $b['modified'] - $a['modified']);
        break;
    case 'name_asc':
        usort($mediaFiles, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        break;
    case 'name_desc':
        usort($mediaFiles, fn($a, $b) => strcasecmp($b['name'], $a['name']));
        break;
    case 'size_asc':
        usort($mediaFiles, fn($a, $b) => $a['size'] - $b['size']);
        break;
    case 'size_desc':
        usort($mediaFiles, fn($a, $b) => $b['size'] - $a['size']);
        break;
}

// Filter by type
$filterType = $_GET['type'] ?? 'all';
if ($filterType !== 'all') {
    $mediaFiles = array_filter($mediaFiles, function($file) use ($filterType) {
        return strpos($file['type'], $filterType) !== false;
    });
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image_upload'])) {
    $uploadResults = [];
    $files = $_FILES['image_upload'];
    
    // Handle multiple files
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === 0) {
                $result = handleFileUpload([
                    'name' => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'type' => $files['type'][$i]
                ], $mediaDir, $allowedTypes, $maxFileSize);
                $uploadResults[] = $result;
            }
        }
    } else {
        $result = handleFileUpload($files, $mediaDir, $allowedTypes, $maxFileSize);
        $uploadResults[] = $result;
    }
    
    header("Location: media.php?uploaded=" . count(array_filter($uploadResults, fn($r) => $r['success'])));
    exit;
}

// Handle single file delete
if (isset($_GET['delete'])) {
    $fileToDelete = realpath($_GET['delete']);
    if (strpos($fileToDelete, realpath($mediaDir)) === 0 && file_exists($fileToDelete)) {
        unlink($fileToDelete);
        header("Location: media.php?deleted=1");
        exit;
    }
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && !empty($_POST['selected_files'])) {
    $deletedCount = 0;
    foreach ($_POST['selected_files'] as $file) {
        $filePath = realpath($file);
        if (strpos($filePath, realpath($mediaDir)) === 0 && file_exists($filePath)) {
            unlink($filePath);
            $deletedCount++;
        }
    }
    header("Location: media.php?deleted=$deletedCount");
    exit;
}

// Handle image optimization
if (isset($_GET['optimize'])) {
    $fileToOptimize = realpath($_GET['optimize']);
    if (strpos($fileToOptimize, realpath($mediaDir)) === 0 && file_exists($fileToOptimize)) {
        optimizeImage($fileToOptimize);
        header("Location: media.php?optimized=1");
        exit;
    }
}

function handleFileUpload($file, $mediaDir, $allowedTypes, $maxFileSize) {
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $mimeType = mime_content_type($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid() . '_' . time() . '.' . $extension;
    $destination = "$mediaDir/$newName";
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Auto-optimize large images
        if (filesize($destination) > 1024 * 1024) { // 1MB
            optimizeImage($destination);
        }
        return ['success' => true, 'filename' => $newName];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}

function optimizeImage($filePath) {
    $imageInfo = getimagesize($filePath);
    if (!$imageInfo) return false;
    
    $mimeType = $imageInfo['mime'];
    $quality = 85;
    
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filePath);
            imagejpeg($image, $filePath, $quality);
            break;
        case 'image/png':
            $image = imagecreatefrompng($filePath);
            imagepng($image, $filePath, 8);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($filePath);
            imagewebp($image, $filePath, $quality);
            break;
    }
    
    if (isset($image)) {
        imagedestroy($image);
    }
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Media Library - My CMS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css" rel="stylesheet">

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
            gap: 10px;
            flex-wrap: wrap;
        }

        .wp-button, .wp-button-secondary, .wp-button-danger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }

        .wp-button {
            background: var(--wp-blue);
            color: var(--wp-white);
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
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .wp-button-danger {
            background: var(--wp-error);
            color: var(--wp-white);
        }

        .wp-button-danger:hover {
            background: #b32d2e;
            transform: translateY(-1px);
        }

        .wp-button-success {
            background: var(--wp-success);
        }

        .wp-button-success:hover {
            background: #007a1f;
        }

        /* Content Area */
        .wp-content {
            padding: 30px;
        }

        /* Upload Zone */
        .upload-zone {
            background: var(--wp-white);
            border: 2px dashed var(--wp-gray-300);
            border-radius: var(--border-radius);
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: var(--transition);
            cursor: pointer;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: var(--wp-blue);
            background: rgba(0, 115, 170, 0.05);
        }

        .upload-zone-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .upload-icon {
            font-size: 48px;
            color: var(--wp-gray-400);
        }

        .upload-text {
            font-size: 18px;
            font-weight: 600;
            color: var(--wp-gray-700);
        }

        .upload-subtext {
            color: var(--wp-gray-500);
            font-size: 14px;
        }

        .file-input {
            display: none;
        }

        /* Media Controls */
        .media-controls {
            background: var(--wp-white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
        }

        .media-stats {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--wp-gray-600);
            font-size: 14px;
        }

        .stat-number {
            font-weight: 600;
            color: var(--wp-blue);
        }

        .media-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: var(--wp-white);
            color: var(--wp-gray-900);
        }

        .view-toggle {
            display: flex;
            border: 1px solid var(--wp-gray-300);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .view-toggle button {
            padding: 8px 12px;
            border: none;
            background: var(--wp-white);
            color: var(--wp-gray-600);
            cursor: pointer;
            transition: var(--transition);
        }

        .view-toggle button.active {
            background: var(--wp-blue);
            color: var(--wp-white);
        }

        /* Media Grid */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .media-grid.list-view {
            grid-template-columns: 1fr;
        }

        .media-item {
            background: var(--wp-white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            position: relative;
            transition: var(--transition);
            cursor: pointer;
        }

        .media-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .media-item.selected {
            border: 2px solid var(--wp-blue);
        }

        .media-image {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            display: block;
        }

        .media-info {
            padding: 15px;
        }

        .media-name {
            font-weight: 600;
            color: var(--wp-gray-900);
            margin-bottom: 5px;
            font-size: 14px;
            word-break: break-all;
        }

        .media-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            color: var(--wp-gray-600);
        }

        .media-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .media-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 6px;
            opacity: 0;
            transition: var(--transition);
        }

        .media-item:hover .media-actions {
            opacity: 1;
        }

        .media-action {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .media-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .media-action.view {
            background: var(--wp-blue);
            color: var(--wp-white);
        }

        .media-action.view:hover {
            background: var(--wp-blue-dark);
        }

        .media-action.copy {
            background: var(--wp-success);
            color: var(--wp-white);
        }

        .media-action.copy:hover {
            background: #007a1f;
        }

        .media-action.optimize {
            background: var(--wp-warning);
            color: var(--wp-white);
        }

        .media-action.optimize:hover {
            background: #b8940f;
        }

        .media-action.delete {
            background: var(--wp-error);
            color: var(--wp-white);
        }

        .media-action.delete:hover {
            background: #b32d2e;
        }

        .media-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* List View */
        .media-grid.list-view .media-item {
            display: flex;
            align-items: center;
            padding: 15px;
        }

        .media-grid.list-view .media-image {
            width: 80px;
            height: 60px;
            aspect-ratio: auto;
            margin-right: 15px;
            border-radius: 4px;
        }

        .media-grid.list-view .media-info {
            flex: 1;
            padding: 0;
        }

        .media-grid.list-view .media-actions {
            position: static;
            opacity: 1;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--wp-white);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 15px;
            border-left: 4px solid var(--wp-blue);
        }

        .bulk-actions.show {
            display: flex;
        }

        .bulk-actions .wp-button-secondary,
        .bulk-actions .wp-button-danger {
            padding: 8px 14px;
            font-size: 13px;
        }

        .selected-count {
            font-weight: 600;
            color: var(--wp-blue);
        }

        /* Progress Bar */
        .upload-progress {
            background: var(--wp-white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--wp-gray-200);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--wp-blue);
            transition: width 0.3s ease;
        }

        /* Responsive */
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
            
            .media-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .media-stats {
                justify-content: center;
            }
            
            .media-filters {
                justify-content: center;
            }

            .wp-header-actions {
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
            
            .wp-button, .wp-button-secondary, .wp-button-danger {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .bulk-actions > * {
                justify-content: center;
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

        /* Animations */
        .wp-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tooltips */
        .tooltip {
            position: relative;
        }

        .tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--wp-gray-900);
            color: var(--wp-white);
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
            margin-top: 5px;
        }

        .tooltip::before {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-bottom-color: var(--wp-gray-900);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            z-index: 1000;
        }

        .tooltip:hover::after,
        .tooltip:hover::before {
            opacity: 1;
            visibility: visible;
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
                    <a href="media.php" class="wp-menu-link active">
                        <i class="fas fa-photo-video wp-menu-icon"></i>
                        Media Library
                        <span class="wp-menu-badge"><?= count($mediaFiles) ?></span>
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
                    <h1>
                        <i class="fas fa-photo-video"></i>
                        Media Library
                    </h1>
                </div>
                
                <div class="wp-header-actions">
                    <button class="wp-button" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-upload"></i>
                        Upload Files
                    </button>
                    <button class="wp-button-secondary" onclick="selectAll()">
                        <i class="fas fa-check-square"></i>
                        Select All
                    </button>
                    <button class="wp-button-danger" onclick="deleteSelected()" id="deleteBtn" style="display: none;">
                        <i class="fas fa-trash"></i>
                        Delete Selected
                    </button>
                </div>
            </header>

            <!-- Content -->
            <div class="wp-content">
                <!-- Upload Zone -->
                <div class="upload-zone" onclick="document.getElementById('fileInput').click()" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                    <div class="upload-zone-content">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="upload-text">Drop files here or click to upload</div>
                        <div class="upload-subtext">
                            Supports: JPG, PNG, GIF, WebP, SVG (Max: 10MB each)
                        </div>
                    </div>
                    <input type="file" id="fileInput" class="file-input" multiple accept="image/*" onchange="handleFileSelect(event)">
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress" id="uploadProgress">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Uploading files...</span>
                        <span id="progressText">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>

                <!-- Media Controls -->
                <div class="media-controls">
                    <div class="media-stats">
                        <div class="stat-item">
                            <i class="fas fa-images"></i>
                            <span class="stat-number"><?= count($mediaFiles) ?></span>
                            <span>Files</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-hdd"></i>
                            <span class="stat-number"><?= formatFileSize(array_sum(array_column($mediaFiles, 'size'))) ?></span>
                            <span>Total Size</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-clock"></i>
                            <span>Last Upload: <?= !empty($mediaFiles) ? date('M j, Y', max(array_column($mediaFiles, 'modified'))) : 'Never' ?></span>
                        </div>
                    </div>

                    <div class="media-filters">
                        <select class="filter-select" onchange="filterByType(this.value)">
                            <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="jpeg" <?= $filterType === 'jpeg' ? 'selected' : '' ?>>JPEG</option>
                            <option value="png" <?= $filterType === 'png' ? 'selected' : '' ?>>PNG</option>
                            <option value="gif" <?= $filterType === 'gif' ? 'selected' : '' ?>>GIF</option>
                            <option value="webp" <?= $filterType === 'webp' ? 'selected' : '' ?>>WebP</option>
                            <option value="svg" <?= $filterType === 'svg' ? 'selected' : '' ?>>SVG</option>
                        </select>

                        <select class="filter-select" onchange="sortFiles(this.value)">
                            <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                            <option value="date_asc" <?= $sortBy === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                            <option value="name_desc" <?= $sortBy === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                            <option value="size_desc" <?= $sortBy === 'size_desc' ? 'selected' : '' ?>>Largest First</option>
                            <option value="size_asc" <?= $sortBy === 'size_asc' ? 'selected' : '' ?>>Smallest First</option>
                        </select>

                        <div class="view-toggle">
                            <button class="active" onclick="toggleView('grid')">
                                <i class="fas fa-th"></i>
                            </button>
                            <button onclick="toggleView('list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <span class="selected-count" id="selectedCount">0 files selected</span>
                    <button class="wp-button-secondary" onclick="downloadSelected()">
                        <i class="fas fa-download"></i>
                        Download
                    </button>
                    <button class="wp-button-secondary" onclick="optimizeSelected()">
                        <i class="fas fa-compress"></i>
                        Optimize
                    </button>
                    <button class="wp-button-danger" onclick="deleteSelected()">
                        <i class="fas fa-trash"></i>
                        Delete
                    </button>
                </div>

                <!-- Media Grid -->
                <div class="media-grid" id="mediaGrid">
                    <?php foreach ($mediaFiles as $index => $file): ?>
                        <div class="media-item wp-fade-in" data-file="<?= htmlspecialchars($file['path']) ?>" onclick="selectItem(this, event)">
                            <input type="checkbox" class="media-checkbox" onchange="updateSelection()">
                            
                            <img src="<?= htmlspecialchars($file['path']) ?>"
                                 alt="<?= htmlspecialchars($file['name']) ?>"
                                 class="media-image"
                                 loading="lazy">
                            
                            <div class="media-actions">
                                <button class="media-action view tooltip" data-tooltip="View Full Size" onclick="viewImage('<?= htmlspecialchars($file['path']) ?>', event)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="media-action copy tooltip" data-tooltip="Copy URL" onclick="copyUrl('<?= htmlspecialchars($file['path']) ?>', event)">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="media-action optimize tooltip" data-tooltip="Optimize Image" onclick="optimizeImage('<?= htmlspecialchars($file['path']) ?>', event)">
                                    <i class="fas fa-compress"></i>
                                </button>
                                <button class="media-action delete tooltip" data-tooltip="Delete Image" onclick="deleteImage('<?= htmlspecialchars($file['path']) ?>', event)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <div class="media-info">
                                <div class="media-name"><?= htmlspecialchars($file['name']) ?></div>
                                <div class="media-meta">
                                    <div class="media-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('M j, Y', $file['modified']) ?>
                                    </div>
                                    <div class="media-meta-item">
                                        <i class="fas fa-weight-hanging"></i>
                                        <?= formatFileSize($file['size']) ?>
                                    </div>
                                    <?php if ($file['dimensions']): ?>
                                        <div class="media-meta-item">
                                            <i class="fas fa-expand-arrows-alt"></i>
                                            <?= $file['dimensions'] ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="media-meta-item">
                                        <i class="fas fa-file-image"></i>
                                        <?= strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($mediaFiles)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--wp-gray-500);">
                        <i class="fas fa-images" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>No media files found</h3>
                        <p>Upload some images to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        let selectedFiles = new Set();
        let currentView = 'grid';

        // Mobile Sidebar Toggle
        function toggleSidebar() {
            document.getElementById('wpSidebar').classList.toggle('open');
        }

        // File Upload Handling
        function handleFileSelect(event) {
            const files = Array.from(event.target.files);
            uploadFiles(files);
        }

        function handleDrop(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const uploadZone = event.currentTarget;
            uploadZone.classList.remove('dragover');
            
            const files = Array.from(event.dataTransfer.files);
            uploadFiles(files);
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.stopPropagation();
            event.currentTarget.classList.add('dragover');
        }

        function handleDragLeave(event) {
            event.preventDefault();
            event.stopPropagation();
            event.currentTarget.classList.remove('dragover');
        }

        function uploadFiles(files) {
            if (files.length === 0) return;

            const formData = new FormData();
            files.forEach(file => {
                formData.append('image_upload[]', file);
            });

            const progressBar = document.getElementById('uploadProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            progressBar.style.display = 'block';

            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                    progressText.textContent = Math.round(percentComplete) + '%';
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    Swal.fire({
                        title: 'Success!',
                        text: `${files.length} file(s) uploaded successfully`,
                        icon: 'success',
                        timer: 2000
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', 'Upload failed', 'error');
                }
                progressBar.style.display = 'none';
            });

            xhr.addEventListener('error', () => {
                Swal.fire('Error', 'Upload failed', 'error');
                progressBar.style.display = 'none';
            });

            xhr.open('POST', 'media.php');
            xhr.send(formData);
        }

        // Selection Management
        function selectItem(item, event) {
            if (event.target.type === 'checkbox' || event.target.closest('.media-action')) {
                return;
            }
            
            const checkbox = item.querySelector('.media-checkbox');
            checkbox.checked = !checkbox.checked;
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('.media-checkbox');
            const checkedBoxes = document.querySelectorAll('.media-checkbox:checked');
            
            selectedFiles.clear();
            checkedBoxes.forEach(cb => {
                const item = cb.closest('.media-item');
                selectedFiles.add(item.dataset.file);
                item.classList.add('selected');
            });

            // Remove selected class from unchecked items
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    cb.closest('.media-item').classList.remove('selected');
                }
            });

            const count = selectedFiles.size;
            document.getElementById('selectedCount').textContent = `${count} file${count !== 1 ? 's' : ''} selected`;
            document.getElementById('bulkActions').classList.toggle('show', count > 0);
            document.getElementById('deleteBtn').style.display = count > 0 ? 'inline-flex' : 'none';
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.media-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            
            updateSelection();
        }

        // Media Actions
        function viewImage(path, event) {
            event.stopPropagation();
            Swal.fire({
                imageUrl: path,
                imageAlt: 'Full Size Image',
                showConfirmButton: false,
                showCloseButton: true,
                width: 'auto',
                customClass: {
                    image: 'swal-image-preview'
                }
            });
        }

        function copyUrl(path, event) {
            event.stopPropagation();
            const fullUrl = window.location.origin + '/' + path;
            navigator.clipboard.writeText(fullUrl).then(() => {
                Swal.fire({
                    title: 'Copied!',
                    text: 'Image URL copied to clipboard',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            });
        }

        function optimizeImage(path, event) {
            event.stopPropagation();
            Swal.fire({
                title: 'Optimize Image?',
                text: 'This will compress the image to reduce file size',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Optimize',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `media.php?optimize=${encodeURIComponent(path)}`;
                }
            });
        }

        function deleteImage(path, event) {
            event.stopPropagation();
            Swal.fire({
                title: 'Delete Image?',
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d63638',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `media.php?delete=${encodeURIComponent(path)}`;
                }
            });
        }

        function deleteSelected() {
            if (selectedFiles.size === 0) return;
            
            Swal.fire({
                title: `Delete ${selectedFiles.size} file(s)?`,
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d63638',
                confirmButtonText: 'Delete All',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const bulkDeleteInput = document.createElement('input');
                    bulkDeleteInput.type = 'hidden';
                    bulkDeleteInput.name = 'bulk_delete';
                    bulkDeleteInput.value = '1';
                    form.appendChild(bulkDeleteInput);
                    
                    selectedFiles.forEach(file => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_files[]';
                        input.value = file;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function optimizeSelected() {
            if (selectedFiles.size === 0) return;
            
            Swal.fire({
                title: `Optimize ${selectedFiles.size} file(s)?`,
                text: 'This will compress the selected images to reduce file size',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Optimize All',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Implementation for bulk optimization
                    Swal.fire('Info', 'Bulk optimization feature coming soon!', 'info');
                }
            });
        }

        function downloadSelected() {
            if (selectedFiles.size === 0) return;
            
            // Implementation for bulk download
            Swal.fire('Info', 'Bulk download feature coming soon!', 'info');
        }

        // View Toggle
        function toggleView(view) {
            currentView = view;
            const mediaGrid = document.getElementById('mediaGrid');
            const buttons = document.querySelectorAll('.view-toggle button');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            if (view === 'list') {
                mediaGrid.classList.add('list-view');
            } else {
                mediaGrid.classList.remove('list-view');
            }
        }

        // Filtering and Sorting
        function filterByType(type) {
            const url = new URL(window.location);
            // Remove success message parameters
            url.searchParams.delete('uploaded');
            url.searchParams.delete('deleted');
            url.searchParams.delete('optimized');
            // Set the filter
            if (type === 'all') {
                url.searchParams.delete('type');
            } else {
                url.searchParams.set('type', type);
            }
            window.location.href = url.toString();
        }

        function sortFiles(sort) {
            const url = new URL(window.location);
            // Remove success message parameters
            url.searchParams.delete('uploaded');
            url.searchParams.delete('deleted');
            url.searchParams.delete('optimized');
            // Set the sort
            url.searchParams.set('sort', sort);
            window.location.href = url.toString();
        }

        // Show success/error messages and clean URL
        <?php if (isset($_GET['uploaded'])): ?>
            Swal.fire({
                title: 'Success!',
                text: '<?= $_GET['uploaded'] ?> file(s) uploaded successfully',
                icon: 'success',
                timer: 3000
            }).then(() => {
                // Clean URL by removing the uploaded parameter
                const url = new URL(window.location);
                url.searchParams.delete('uploaded');
                window.history.replaceState({}, document.title, url.toString());
            });
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            Swal.fire({
                title: 'Deleted!',
                text: '<?= $_GET['deleted'] ?> file(s) deleted successfully',
                icon: 'success',
                timer: 3000
            }).then(() => {
                // Clean URL by removing the deleted parameter
                const url = new URL(window.location);
                url.searchParams.delete('deleted');
                window.history.replaceState({}, document.title, url.toString());
            });
        <?php endif; ?>

        <?php if (isset($_GET['optimized'])): ?>
            Swal.fire({
                title: 'Optimized!',
                text: 'Image has been optimized successfully',
                icon: 'success',
                timer: 3000
            }).then(() => {
                // Clean URL by removing the optimized parameter
                const url = new URL(window.location);
                url.searchParams.delete('optimized');
                window.history.replaceState({}, document.title, url.toString());
            });
        <?php endif; ?>

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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey || event.metaKey) {
                switch (event.key) {
                    case 'a':
                        event.preventDefault();
                        selectAll();
                        break;
                    case 'u':
                        event.preventDefault();
                        document.getElementById('fileInput').click();
                        break;
                    case 'Delete':
                    case 'Backspace':
                        if (selectedFiles.size > 0) {
                            event.preventDefault();
                            deleteSelected();
                        }
                        break;
                }
            }
        });
    </script>
</body>
</html>
