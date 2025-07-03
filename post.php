<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

// Use slug instead of ID
if (!isset($_GET['slug'])) {
    die("Post not found.");
}

$slug = $_GET['slug'];
$stmt = $conn->prepare("SELECT posts.*, authors.name AS author_name 
                        FROM posts 
                        LEFT JOIN authors ON posts.author_id = authors.id 
                        WHERE posts.slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

if (!$post || $post['status'] === 'draft') {
    die("This post is not available.");
}

// Add views column if it doesn't exist
$conn->query("ALTER TABLE posts ADD COLUMN IF NOT EXISTS views INT DEFAULT 0");

// Increment view count - Real-time tracking
$updateViews = $conn->prepare("UPDATE posts SET views = COALESCE(views, 0) + 1 WHERE id = ?");
$updateViews->bind_param("i", $post['id']);
$updateViews->execute();

// Get updated view count for display
$viewsStmt = $conn->prepare("SELECT views FROM posts WHERE id = ?");
$viewsStmt->bind_param("i", $post['id']);
$viewsStmt->execute();
$viewsResult = $viewsStmt->get_result();
$currentViews = $viewsResult->fetch_assoc()['views'];

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $content = trim($_POST['content']);
    $post_id = $post['id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Basic validation
    if (empty($name) || empty($email) || empty($content)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("INSERT INTO comments (post_id, author_name, author_email, content) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $post_id, $name, $email, $content);
        
        if ($stmt->execute()) {
            $success = "Your comment has been submitted and will be reviewed.";
            // Clear form
            $_POST = array();
        } else {
            $error = "There was an error submitting your comment. Please try again.";
        }
    }
}

// Fetch approved comments for this post
$comments_stmt = $conn->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at DESC");
$comments_stmt->bind_param("i", $post['id']);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

// Fetch menu pages - Updated to match dynamic functionality from index.php
$menu = $conn->query("SELECT * FROM pages WHERE status = 'published' AND show_in_menu = 1 ORDER BY page_order ASC, id ASC");

// Sidebar data queries
// Recent posts (excluding current post)
$recent_posts = $conn->prepare("SELECT title, slug, created_at FROM posts WHERE status = 'published' AND id != ? ORDER BY created_at DESC LIMIT 10");
$recent_posts->bind_param("i", $post['id']);
$recent_posts->execute();
$recent_posts_result = $recent_posts->get_result();

// Categories with post counts
$categories = $conn->query("SELECT category, COUNT(*) as count FROM posts WHERE status = 'published' AND category IS NOT NULL AND category != '' GROUP BY category ORDER BY count DESC LIMIT 10");

// Popular tags
$popular_tags = $conn->query("SELECT tags FROM posts WHERE status = 'published' AND tags IS NOT NULL AND tags != ''");
$tag_counts = array();
while ($tag_row = $popular_tags->fetch_assoc()) {
    $tags = explode(',', $tag_row['tags']);
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
            $tag_counts[$tag] = isset($tag_counts[$tag]) ? $tag_counts[$tag] + 1 : 1;
        }
    }
}
arsort($tag_counts);
$popular_tags_array = array_slice($tag_counts, 0, 15, true);

// Latest comments
$latest_comments = $conn->query("SELECT c.author_name, c.content, c.created_at, p.title, p.slug 
                                FROM comments c 
                                JOIN posts p ON c.post_id = p.id 
                                WHERE c.status = 'approved' 
                                ORDER BY c.created_at DESC 
                                LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?> - My CMS</title>
    <meta name="description" content="<?= substr(strip_tags($post['content']), 0, 150) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #f59e0b;
            --accent-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-dark: #0f172a;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f2f2f2;
        }
        /* Breaking News Ticker */
        .breaking-news {
            background: linear-gradient(135deg, var(--accent-color), #dc2626);
            color: white;
            padding: 10px 0;
            overflow: hidden;
            position: relative;
        }
        .breaking-content {
            display: flex;
            align-items: center;
            white-space: nowrap;
            animation: scroll 30s linear infinite;
        }
        .breaking-label {
            background: rgba(255,255,255,0.2);
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            margin-right: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        @keyframes scroll {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        /* Header */
        header {
            background-color: #1a1a1a;
            color: white;
            padding: 15px 20px;
            border-bottom: 5px solid #ff6600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1000;
        }
        header h1 {
            margin: 0;
            color: white;
            font-size: 2.2rem;
        }
        .hamburger { display: none; flex-direction: column; cursor: pointer; }
        .hamburger div {
            width: 25px; height: 3px;
            background-color: white; margin: 4px 0;
        }
        .menu {
            display: flex; gap: 20px;
            background-color: #1a1a1a;
        }
        .menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 10px;
        }
        .menu a:hover {
            background-color: #333;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .menu {
                display: none;
                flex-direction: column;
                background: #1a1a1a;
                position: absolute;
                right: 0;
                top: 100%;
                width: 200px;
                padding: 15px;
            }
            .menu.open {
                display: flex;
            }
            .hamburger {
                display: flex;
            }
        }
        /* Main layout container */
        .main-container {
            padding-right: 10px;
            padding-left: 10px;
            max-width: auto;
            margin: 40px auto;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }
        /* Content area */
        .content-area {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        /* Enhanced Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .sidebar-widget {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }
        .sidebar-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.12);
        }
        .sidebar-widget h3 {
            margin: 0 0 20px 0;
            color: #1f2937;
            font-size: 1.4rem;
            font-weight: 700;
            border-bottom: 3px solid #ff6600;
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-widget h3 i {
            color: #ff6600;
            font-size: 1.2rem;
        }
        .sidebar-widget ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-widget li {
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }
        .sidebar-widget li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .sidebar-widget li:hover {
            background: #f8fafc;
            padding-left: 10px;
            border-radius: 8px;
        }
        .sidebar-widget a {
            color: #374151;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            line-height: 1.5;
            transition: color 0.3s ease;
        }
        .sidebar-widget a:hover {
            color: #ff6600;
        }
        .post-date {
            font-size: 0.8rem;
            color: #777;
            display: block;
            margin-top: 3px;
        }
        .category-count {
            background: #ff6600;
            color: white;
            font-size: 0.8rem;
            padding: 2px 6px;
            border-radius: 10px;
            float: right;
        }
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tag-cloud a {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            text-decoration: none;
            color: #555;
            transition: all 0.3s ease;
        }
        .tag-cloud a:hover {
            background: #ff6600;
            color: white;
        }
        .comment-excerpt {
            font-size: 13px;
            color: #6b7280;
            margin-top: 8px;
            line-height: 1.5;
            font-style: italic;
        }
        .search-box {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .search-btn {
            width: 100%;
            background: #ff6600;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        .search-btn:hover {
            background: #e65c00;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        /* Meta information with button styling */
        .post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .meta-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid var(--border-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .meta-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        .meta-btn i {
            font-size: 12px;
        }
        .meta-btn.date {
            background: #e0f2fe;
            color: #0277bd;
            border-color: #b3e5fc;
        }
        .meta-btn.date:hover {
            background: #0277bd;
            color: white;
            border-color: #0277bd;
        }
        .meta-btn.author {
            background: #f3e5f5;
            color: #7b1fa2;
            border-color: #e1bee7;
        }
        .meta-btn.author:hover {
            background: #7b1fa2;
            color: white;
            border-color: #7b1fa2;
        }
        .meta-btn.category {
            background: #e8f5e8;
            color: #2e7d32;
            border-color: #c8e6c9;
        }
        .meta-btn.category:hover {
            background: #2e7d32;
            color: white;
            border-color: #2e7d32;
        }
        /* Views counter */
        .meta-btn.views {
            background: #fff3e0;
            color: #f57c00;
            border-color: #ffcc02;
        }
        .meta-btn.views:hover {
            background: #f57c00;
            color: white;
            border-color: #f57c00;
        }
        /* Featured image */
        img.featured {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: var(--radius-md);
            margin: 32px 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .content {
            line-height: 1.7;
            color: #444;
        }
        .tags {
            margin-top: 25px;
            font-size: 14px;
            color: #555;
        }
        .tags span {
            background: #eee;
            padding: 5px 10px;
            border-radius: 20px;
            margin-right: 8px;
            display: inline-block;
        }
        a.back {
            display: inline-block;
            margin-top: 30px;
            color: #007bff;
            text-decoration: none;
        }
        a.back:hover {
            text-decoration: underline;
        }
        footer {
            text-align: center;
            padding: 30px 20px;
            background: #222;
            color: #ccc;
            margin-top: 40px;
        }
        /* Comments Section */
        /* Enhanced Comment Form */
        .comment-form {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 40px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .comment-form h3 {
            margin: 0 0 25px 0;
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .comment-form h3 i {
            color: #ff6600;
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
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6600;
            box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1);
            transform: translateY(-1px);
        }
        .form-group textarea {
            min-height: 140px;
            resize: vertical;
            line-height: 1.6;
        }
        .submit-btn {
            background: linear-gradient(135deg, #ff6600, #e65c00);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(255, 102, 0, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 102, 0, 0.4);
        }
        .submit-btn i {
            font-size: 14px;
        }
        .comments-title {
            font-size: 2rem;
            margin-bottom: 30px;
            color: #1f2937;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .comments-title i {
            color: #ff6600;
            font-size: 1.8rem;
        }
        /* Content styling */
        .post-content {
            font-size: 16px;
            line-height: 1.8;
            color: #444;
        }
        .post-content p {
            margin-bottom: 20px;
        }
        .post-content h2, .post-content h3 {
            margin: 32px 0 16px 0;
            color: var(--text-primary);
        }
        /* Enhanced Comments List */
        .comment-list {
            margin-top: 30px;
        }
        .comment {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .comment:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .comment:last-child {
            margin-bottom: 0;
        }
        .comment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .comment-author {
            font-weight: 700;
            color: #1f2937;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .comment-author::before {
            content: "👤";
            font-size: 14px;
        }
        .comment-date {
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 12px;
        }
        .comment-content {
            color: #4b5563;
            line-height: 1.7;
            font-size: 15px;
        }
        .no-comments {
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            padding: 60px 20px;
            background: #f8fafc;
            border-radius: 15px;
            border: 2px dashed #e2e8f0;
        }
        /* Enhanced Alerts */
        .alert {
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 1px solid #86efac;
        }
        .alert-success::before {
            content: "✅";
            font-size: 16px;
        }
        .alert-error {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .alert-error::before {
            content: "❌";
            font-size: 16px;
        }
        a.back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            color: #ff6600;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 24px;
            border: 2px solid #ff6600;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        a.back:hover {
            background: #ff6600;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 102, 0, 0.3);
        }
        footer {
            text-align: center;
            padding: 30px 20px;
            background: #222;
            color: #ccc;
            margin-top: 40px;
        }
        /* Tags */
        .post-tags {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        .tags-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: block;
        }
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 30px;
        }
        .tag-item {
            background: #eee;
            color: #555;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            margin-right: 8px;
            display: inline-block;
        }
        /* Scroll to Top Button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s;
            z-index: 1000;
        }
        .scroll-top:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }
        .scroll-top.visible {
            display: flex;
        }

        /* Real-time View Counter */
        .view-counter {
            position: fixed;
            bottom: 100px;
            right: 30px;
            background: linear-gradient(135deg, #ff6600, #e65c00);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
            font-weight: 600;
            font-size: 14px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .view-counter i {
            font-size: 16px;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
                margin: 20px auto;
                padding: 0 10px;
            }
            
            /* Remove the order: -1 so content appears first */
            .sidebar {
                margin-top: 20px; /* Add some spacing between content and sidebar */
            }
            
            header h1 {
                font-size: 2rem;
            }
            
            .content-area {
                padding: 20px;
            }
            
            img.featured {
                margin: 15px 0;
            }

            .view-counter {
                bottom: 80px;
                right: 20px;
                padding: 10px 16px;
                font-size: 12px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        @media (max-width: 600px) {
            .sidebar-widget {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<!-- Breaking News Ticker -->
<div class="breaking-news">
    <div class="breaking-content">
        <span class="breaking-label">Breaking News</span>
        <span>Stay updated with the latest news and developments • PHP News brings you real-time updates • Your trusted source for current events</span>
    </div>
</div>

<!-- Header -->
<header>
    <h1>PHP CMS</h1>
    <div class="hamburger" onclick="toggleMenu()">
        <div></div><div></div><div></div>
    </div>
    <nav class="menu">
        <a href="index.php">Home</a>
        <?php while($m = $menu->fetch_assoc()): ?>
            <a href="page.php?slug=<?= urlencode($m['slug']) ?>"><?= htmlspecialchars($m['title']) ?></a>
        <?php endwhile; ?>
    </nav>
</header>

<div class="main-container">
    <!-- Main Content Area -->
    <div class="content-area">
        <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

        <!-- Meta information with button styling -->
        <div class="post-meta">
            <div class="meta-btn date">
                <i class="fas fa-calendar-alt"></i>
                <span><?= date("F d, Y", strtotime($post['created_at'])) ?></span>
            </div>
            <?php if (!empty($post['author_name'])): ?>
                <div class="meta-btn author">
                    <i class="fas fa-user"></i>
                    <span><?= htmlspecialchars($post['author_name']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($post['category'])): ?>
                <div class="meta-btn category">
                    <i class="fas fa-folder"></i>
                    <span><?= htmlspecialchars($post['category']) ?></span>
                </div>
            <?php endif; ?>
            <div class="meta-btn views">
                <i class="fas fa-eye"></i>
                <span id="viewCount"><?= number_format($currentViews) ?> views</span>
            </div>
        </div>

        <?php if (!empty($post['image'])): ?>
            <img class="featured" src="uploads/<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
        <?php endif; ?>

        <div class="post-content">
            <?= $post['content'] ?>
        </div>

        <?php if (!empty($post['tags'])): ?>
            <div class="post-tags">
                <span class="tags-label">🏷️ Tags:</span>
                <div class="tag-list">
                    <?php
                        $tags = explode(",", $post['tags']);
                        foreach ($tags as $tag) {
                            echo '<span class="tag-item">' . htmlspecialchars(trim($tag)) . '</span>';
                        }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Enhanced Comments Section -->
        <div class="comments-section">
            <h2 class="comments-title">
                <i class="fas fa-comments"></i>
                Comments (<?= $comments_result->num_rows ?>)
            </h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <div class="comment-form">
                <h3>
                    <i class="fas fa-edit"></i>
                    Leave a Comment
                </h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="content">Comment *</label>
                        <textarea id="content" name="content" placeholder="Share your thoughts..." required><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?></textarea>
                    </div>
                    <button type="submit" name="submit_comment" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Post Comment
                    </button>
                </form>
            </div>

            <div class="comment-list">
                <?php if ($comments_result->num_rows > 0): ?>
                    <?php while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-author"><?= htmlspecialchars($comment['author_name']) ?></span>
                                <span class="comment-date"><?= date("F j, Y g:i a", strtotime($comment['created_at'])) ?></span>
                            </div>
                            <div class="comment-content">
                                <?= nl2br(htmlspecialchars($comment['content'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-comments">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
            </div>
        </div>

        <a class="back" href="index.php">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar">
        <!-- Search Widget -->
        <div class="sidebar-widget">
            <h3>🔍 Search</h3>
            <form action="search.php" method="GET">
                <input type="text" name="q" class="search-box" placeholder="Search posts..." value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>

        <!-- Recent Posts Widget -->
        <div class="sidebar-widget">
            <h3>
                <i class="fas fa-newspaper"></i>
                Recent Posts
            </h3>
            <?php if ($recent_posts_result->num_rows > 0): ?>
                <ul>
                    <?php while ($recent_post = $recent_posts_result->fetch_assoc()): ?>
                        <li>
                            <a href="post.php?slug=<?= urlencode($recent_post['slug']) ?>">
                                <?= htmlspecialchars($recent_post['title']) ?>
                            </a>
                            <span class="post-date"><?= date("M j, Y", strtotime($recent_post['created_at'])) ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No recent posts found.</p>
            <?php endif; ?>
        </div>

        <!-- Categories Widget -->
        <div class="sidebar-widget">
            <h3>📂 Categories</h3>
            <?php if ($categories->num_rows > 0): ?>
                <ul>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <li>
                            <a href="category.php?cat=<?= urlencode($category['category']) ?>">
                                <?= htmlspecialchars($category['category']) ?>
                            </a>
                            <span class="category-count"><?= $category['count'] ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No categories found.</p>
            <?php endif; ?>
        </div>

        <!-- Popular Tags Widget -->
        <?php if (!empty($popular_tags_array)): ?>
        <div class="sidebar-widget">
            <h3>🏷️ Popular Tags</h3>
            <div class="tag-cloud">
                <?php foreach ($popular_tags_array as $tag => $count): ?>
                    <a href="tag.php?tag=<?= urlencode($tag) ?>" title="<?= $count ?> posts">
                        <?= htmlspecialchars($tag) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Latest Comments Widget -->
        <div class="sidebar-widget">
            <h3>
                <i class="fas fa-comments"></i>
                Latest Comments
            </h3>
            <?php if ($latest_comments->num_rows > 0): ?>
                <ul>
                    <?php while ($comment = $latest_comments->fetch_assoc()): ?>
                        <li>
                            <strong><?= htmlspecialchars($comment['author_name']) ?></strong> on
                            <a href="post.php?slug=<?= urlencode($comment['slug']) ?>">
                                <?= htmlspecialchars($comment['title']) ?>
                            </a>
                            <div class="comment-excerpt">
                                <?= htmlspecialchars(substr($comment['content'], 0, 80)) ?>...
                            </div>
                            <span class="post-date"><?= date("M j, Y", strtotime($comment['created_at'])) ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>No comments yet.</p>
            <?php endif; ?>
        </div>

        <!-- About Widget -->
        <div class="sidebar-widget">
            <h3>
                <i class="fas fa-info-circle"></i>
                About
            </h3>
            <p>Welcome to My CMS! We share the latest news, insights, and stories. Stay connected for regular updates and engaging content.</p>
        </div>
    </aside>
</div>

<!-- Footer -->
<footer>
    &copy; <?= date('Y') ?> My CMS News. Made with ❤️ in PHP.
</footer>

<!-- Real-time View Counter -->

<!-- Scroll to Top Button -->
<button class="scroll-top" id="scrollTop" onclick="scrollToTop()">
    <i class="fas fa-chevron-up"></i>
</button>

<script>
    // Mobile Menu Toggle
    function toggleMenu() {
        document.querySelector('.menu').classList.toggle('open');
    }

    // Scroll to Top
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Show/Hide Scroll to Top Button
    window.addEventListener('scroll', function() {
        const scrollTop = document.getElementById('scrollTop');
        if (window.pageYOffset > 300) {
            scrollTop.classList.add('visible');
        } else {
            scrollTop.classList.remove('visible');
        }
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const navMenu = document.querySelector('.menu');
        const mobileToggle = document.querySelector('.hamburger');
        
        if (!navMenu.contains(event.target) && !mobileToggle.contains(event.target)) {
            navMenu.classList.remove('open');
        }
    });

    // Real-time view tracking
    let currentViews = <?= $currentViews ?>;
    
    // Update view count every 30 seconds to show real-time changes
    setInterval(function() {
        fetch('get_views.php?post_id=<?= $post['id'] ?>')
            .then(response => response.json())
            .then(data => {
                if (data.views && data.views !== currentViews) {
                    currentViews = data.views;
                    document.getElementById('viewCount').innerHTML = new Intl.NumberFormat().format(currentViews) + ' views';
                    document.getElementById('liveViewCount').innerHTML = new Intl.NumberFormat().format(currentViews);
                    
                    // Add a subtle animation when views update
                    const viewCounter = document.getElementById('viewCounter');
                    viewCounter.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        viewCounter.style.transform = 'scale(1)';
                    }, 200);
                }
            })
            .catch(error => console.log('View tracking error:', error));
    }, 30000); // Check every 30 seconds

    // Track time spent on page
    let startTime = Date.now();
    let timeSpent = 0;
    
    setInterval(function() {
        timeSpent = Math.floor((Date.now() - startTime) / 1000);
    }, 1000);

    // Send engagement data when user leaves
    window.addEventListener('beforeunload', function() {
        if (timeSpent > 10) { // Only track if user spent more than 10 seconds
            navigator.sendBeacon('track_engagement.php', JSON.stringify({
                post_id: <?= $post['id'] ?>,
                time_spent: timeSpent,
                scroll_depth: Math.round((window.pageYOffset / (document.body.scrollHeight - window.innerHeight)) * 100)
            }));
        }
    });
</script>
</body>
</html>
