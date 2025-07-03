<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

require 'config.php';

// Only get published pages for menu that are set to show in menu, ordered by page_order

$menu = $conn->query("SELECT * FROM pages WHERE status = 'published' AND show_in_menu = 1 ORDER BY page_order ASC, id ASC");

// Only get published posts with authors

$featured = $conn->query("SELECT posts.*, authors.name as author_name FROM posts 

                          LEFT JOIN authors ON posts.author_id = authors.id 

                          WHERE posts.status = 'published'

                          ORDER BY posts.created_at DESC LIMIT 5");

// Get next 4 posts for right featured (don't skip the first one)

$featuredRight = $conn->query("SELECT posts.*, authors.name as author_name FROM posts 

                               LEFT JOIN authors ON posts.author_id = authors.id 

                               WHERE posts.status = 'published'

                               ORDER BY posts.created_at DESC LIMIT 5");

$remaining = $conn->query("SELECT posts.*, authors.name as author_name FROM posts 

                           LEFT JOIN authors ON posts.author_id = authors.id 

                           WHERE posts.status = 'published'

                           ORDER BY posts.created_at DESC");

// Get trending posts for the center section

$trendingPosts = $conn->query("SELECT posts.*, authors.name as author_name FROM posts 

                               LEFT JOIN authors ON posts.author_id = authors.id 

                               WHERE posts.status = 'published'

                               ORDER BY posts.views DESC LIMIT 12");

// Get data for sidebar widgets

$categories = $conn->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL AND status = 'published' LIMIT 8");

$recentPosts = $conn->query("SELECT posts.*, authors.name as author_name FROM posts 

                             LEFT JOIN authors ON posts.author_id = authors.id 

                             WHERE posts.status = 'published'

                             ORDER BY posts.created_at DESC LIMIT 5");

$popularPosts = $conn->query("SELECT posts.*, authors.name as author_name FROM posts 

                              LEFT JOIN authors ON posts.author_id = authors.id 

                              WHERE posts.status = 'published'

                              ORDER BY posts.views DESC LIMIT 5");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>My CMS Portal</title>

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

    header h1 { margin: 0; font-size: 2.2rem; }

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

    /* Container */

    .container { max-width: 1600px; margin: 30px auto; padding: 0 20px; }

    /* Featured Section */

    .featured-section {
        

        display: grid;

        grid-template-columns: 2fr 1fr;

        gap: 20px;

        margin-bottom: 40px;

    }

    .slideshow-container {

        position: relative;

        width: 100%;

        border-radius: 10px;

        overflow: hidden;

        display: block;

    }

    .slide { display: block; transition: 0.5s; }

    .slide img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; }

    .slide-content {

        background: white;

        padding: 20px;

        border: 1px solid #ddd;

        border-top: none;

    }

    .slide-content h2 { margin: 0 0 10px; font-size: 1.6rem; color: #333; }

    .slide-content small { color: #777; }

    .slide-content p { color: #555; margin: 10px 0; }

    .btn {

        display: inline-block;

        padding: 10px 20px;

        background: #ff6600;

        color: white;

        border-radius: 5px;

        text-decoration: none;

        font-weight: bold;

    }

        .featured-right {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .featured-small {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: row;
            height: 120px;
        }
        
        .featured-small img {
            width: 140px;
            height: 120px;
            object-fit: cover;
            flex-shrink: 0;
        }

    .featured-small .info {

        padding: 15px;

        flex-grow: 1;

        display: flex;

        flex-direction: column;

        justify-content: space-between;

    }

    .featured-small h3 { margin: 0 0 8px; font-size: 1.1rem; }

    .featured-small small { color: #777; }

    /* ENHANCED Trending Section with Sidebar - EQUAL HEIGHT */

    .trending-section {

        display: grid;
        
        padding: 20px;

        grid-template-columns: 2fr 1fr;

        gap: 30px;

        margin-bottom: 40px;

        align-items: start;

    }

    .trending-main {

        background: white;

        border-radius: 20px;

        padding: 35px;

        box-shadow: 0 8px 30px rgba(0,0,0,0.12);

        border: 1px solid #e5e7eb;

        height: fit-content;

        min-height: 850px; /* Set minimum height for consistency */

        display: flex;

        flex-direction: column;

    }

    .trending-header {

        display: flex;

        align-items: center;

        justify-content: center;

        margin-bottom: 35px;

        padding-bottom: 25px;

        border-bottom: 4px solid #ff6600;

        position: relative;

    }

    .trending-header::after {

        content: '';

        position: absolute;

        bottom: -4px;

        left: 50%;

        transform: translateX(-50%);

        width: 80px;

        height: 4px;

        background: linear-gradient(135deg, #ff6600, #ff8533);

        border-radius: 2px;

    }

    .trending-title {

        font-size: 32px;

        font-weight: 800;

        color: #333;

        display: flex;

        align-items: center;

        gap: 18px;

        text-transform: uppercase;

        letter-spacing: 1.5px;

    }

    .trending-title i {

        color: #ff6600;

        font-size: 36px;

        animation: pulse 2s infinite;

        filter: drop-shadow(0 0 10px rgba(255, 102, 0, 0.3));

    }

    @keyframes pulse {

        0%, 100% { transform: scale(1); }

        50% { transform: scale(1.15); }

    }

    .trending-grid {

        display: grid;

        grid-template-columns: 1fr;

        gap: 20px;

        flex-grow: 1;

    }

    .trending-item {

        display: flex;

        gap: 20px;

        padding: 25px;

        border-radius: 16px;

        transition: all 0.4s ease;

        border: 2px solid transparent;

        background: linear-gradient(135deg, #fafafa, #f8fafc);

        position: relative;

        overflow: hidden;

    }

    .trending-item::before {

        content: '';

        position: absolute;

        top: 0;

        left: 0;

        width: 5px;

        height: 100%;

        background: linear-gradient(135deg, #ff6600, #ff8533);

        transform: scaleY(0);

        transition: transform 0.4s ease;

        border-radius: 0 3px 3px 0;

    }

    .trending-item::after {

        content: '';

        position: absolute;

        top: 0;

        left: 0;

        right: 0;

        bottom: 0;

        background: linear-gradient(135deg, rgba(255, 102, 0, 0.03), rgba(255, 133, 51, 0.03));

        opacity: 0;

        transition: opacity 0.4s ease;

    }

    .trending-item:hover {

        background: white;

        border-color: #ff6600;

        transform: translateX(10px) translateY(-3px);

        box-shadow: 0 12px 35px rgba(255, 102, 0, 0.2);

    }

    .trending-item:hover::before {

        transform: scaleY(1);

    }

    .trending-item:hover::after {

        opacity: 1;

    }

    .trending-number {

        background: linear-gradient(135deg, #ff6600, #e55a00);

        color: white;

        width: 50px;

        height: 50px;

        border-radius: 50%;

        display: flex;

        align-items: center;

        justify-content: center;

        font-weight: 800;

        font-size: 18px;

        flex-shrink: 0;

        box-shadow: 0 6px 20px rgba(255, 102, 0, 0.4);

        position: relative;

        z-index: 2;

    }

    .trending-number::after {

        content: '';

        position: absolute;

        inset: -4px;

        border-radius: 50%;

        background: linear-gradient(135deg, #ff6600, #e55a00);

        z-index: -1;

        opacity: 0.2;

        animation: ripple 3s infinite;

    }

    @keyframes ripple {

        0%, 100% { transform: scale(1); opacity: 0.2; }

        50% { transform: scale(1.2); opacity: 0; }

    }

    .trending-content {

        flex-grow: 1;

        position: relative;

        z-index: 2;

    }

    .trending-content h4 {

        font-size: 18px;

        font-weight: 700;

        margin-bottom: 10px;

        line-height: 1.4;

        color: #333;

    }

    .trending-content a {

        color: #333;

        text-decoration: none;

        transition: color 0.3s ease;

    }

    .trending-content a:hover {

        color: #ff6600;

    }

    .trending-meta {

        font-size: 14px;

        color: #777;

        display: flex;

        align-items: center;

        gap: 15px;

        margin-top: 8px;

    }

    .trending-views {

        display: flex;

        align-items: center;

        gap: 6px;

        color: #ff6600;

        font-weight: 700;

        background: rgba(255, 102, 0, 0.1);

        padding: 4px 8px;

        border-radius: 12px;

    }

    /* ENHANCED Sidebar Widgets - EQUAL HEIGHT */

    .trending-sidebar {

        display: flex;

        flex-direction: column;

        gap: 25px;

        height: fit-content;

        min-height: 850px; /* Match trending main height */

    }

    .widget {

        background: white;

        border-radius: 18px;

        padding: 28px;

        box-shadow: 0 8px 25px rgba(0,0,0,0.12);

        border: 1px solid #e5e7eb;

        transition: all 0.4s ease;

        flex-grow: 1; /* This makes widgets expand to fill available space */

        position: relative;

        overflow: hidden;

    }

    .widget::before {

        content: '';

        position: absolute;

        top: 0;

        left: 0;

        right: 0;

        height: 4px;

        background: linear-gradient(135deg, #ff6600, #ff8533);

        transform: scaleX(0);

        transition: transform 0.4s ease;

    }

    .widget:hover {

        transform: translateY(-5px);

        box-shadow: 0 15px 40px rgba(0,0,0,0.18);

    }

    .widget:hover::before {

        transform: scaleX(1);

    }

    .widget-title {

        font-size: 22px;

        font-weight: 800;

        margin-bottom: 25px;

        color: #333;

        display: flex;

        align-items: center;

        gap: 15px;

        border-bottom: 3px solid #ff6600;

        padding-bottom: 15px;

        text-transform: uppercase;

        letter-spacing: 1px;

        position: relative;

    }

    .widget-title::after {

        content: '';

        position: absolute;

        bottom: -3px;

        left: 0;

        width: 50px;

        height: 3px;

        background: linear-gradient(135deg, #ff6600, #ff8533);

        border-radius: 2px;

    }

    .widget-title i {

        color: #ff6600;

        font-size: 24px;

        filter: drop-shadow(0 0 8px rgba(255, 102, 0, 0.3));

    }

    /* Enhanced Categories Widget */

    .categories-grid {

        display: grid;

        grid-template-columns: repeat(2, 1fr);

        gap: 12px;

    }

    .category-item {

        background: linear-gradient(135deg, #f8fafc, #f1f5f9);

        padding: 14px 18px;

        border-radius: 12px;

        text-decoration: none;

        color: #555;

        font-weight: 600;

        text-align: center;

        transition: all 0.4s ease;

        font-size: 14px;

        border: 2px solid transparent;

        position: relative;

        overflow: hidden;

    }

    .category-item::before {

        content: '';

        position: absolute;

        top: 0;

        left: -100%;

        width: 100%;

        height: 100%;

        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);

        transition: left 0.5s;

    }

    .category-item:hover::before {

        left: 100%;

    }

    .category-item:hover {

        background: linear-gradient(135deg, #ff6600, #ff8533);

        color: white;

        transform: translateY(-3px);

        box-shadow: 0 8px 20px rgba(255, 102, 0, 0.3);

        border-color: #ff6600;

    }

    /* Enhanced Recent/Popular Posts Widget */

    .widget-list {

        display: flex;

        flex-direction: column;

        gap: 18px;

    }

    .widget-item {

        display: flex;

        gap: 15px;

        padding: 18px;

        border-radius: 12px;

        background: linear-gradient(135deg, #f8fafc, #f1f5f9);

        transition: all 0.4s ease;

        border: 2px solid transparent;

        position: relative;

        overflow: hidden;

    }

    .widget-item::before {

        content: '';

        position: absolute;

        top: 0;

        left: 0;

        width: 3px;

        height: 100%;

        background: linear-gradient(135deg, #ff6600, #ff8533);

        transform: scaleY(0);

        transition: transform 0.4s ease;

    }

    .widget-item:hover {

        background: white;

        border-color: #ff6600;

        transform: translateX(8px);

        box-shadow: 0 8px 20px rgba(255, 102, 0, 0.15);

    }

    .widget-item:hover::before {

        transform: scaleY(1);

    }

    .widget-item img {

        width: 65px;

        height: 65px;

        object-fit: cover;

        border-radius: 12px;

        box-shadow: 0 4px 12px rgba(0,0,0,0.15);

        transition: transform 0.4s ease;

    }

    .widget-item:hover img {

        transform: scale(1.1);

    }

    .widget-content {

        flex-grow: 1;

    }

    .widget-content h5 {

        font-size: 15px;

        font-weight: 700;

        margin-bottom: 8px;

        line-height: 1.4;

        color: #333;

    }

    .widget-content a {

        color: #333;

        text-decoration: none;

        transition: color 0.3s ease;

    }

    .widget-content a:hover {

        color: #ff6600;

    }

    .widget-meta {

        font-size: 13px;

        color: #777;

        display: flex;

        align-items: center;

        gap: 6px;

        margin-top: 5px;

    }

    .widget-meta i {

        color: #ff6600;

    }

    /* Articles Grid - KEPT ORIGINAL */

    .grid {

        display: grid;

        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));

        gap: 25px;

        margin-top: 40px;

    }

    .card {

        background: white;

        border-radius: 8px;

        overflow: hidden;

        box-shadow: 0 2px 8px rgba(0,0,0,0.1);

        transition: transform 0.3s ease;

        display: flex;

        flex-direction: column;

    }

    .card:hover { transform: scale(1.01); }

    .thumb img {

        width: 100%;

        aspect-ratio: 16 / 9;

        object-fit: cover;

        display: block;

    }

    .content {

        padding: 15px 20px;

        flex-grow: 1;

        display: flex;

        flex-direction: column;

        justify-content: space-between;

    }

    .content h2 { font-size: 1.4rem; margin: 0 0 10px; color: #333; }

    .content small { color: #777; }

    .content p { color: #555; line-height: 1.6; }

    .read-more { margin-top: 10px; }

    /* Footer */

    footer {

        text-align: center;

        padding: 30px 20px;

        background: #222;

        color: #ccc;

        margin-top: 40px;

    }

    .clamp-title {

        display: -webkit-box;

        -webkit-line-clamp: 2;

        -webkit-box-orient: vertical;

        overflow: hidden;

        text-overflow: ellipsis;

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

    /* ENHANCED MOBILE RESPONSIVE DESIGN */

    @media (max-width: 768px) {

        .featured-section { grid-template-columns: 1fr; }

        .featured-right { grid-template-columns: repeat(2, 1fr); }

        /* MOBILE TRENDING SECTION - ENHANCED */

        .trending-section {

            grid-template-columns: 1fr;

            gap: 25px;

        }

        .trending-main {

            padding: 25px 20px;

            min-height: auto; /* Remove fixed height on mobile */

            border-radius: 15px;

        }

        .trending-title {

            font-size: 24px;

            gap: 12px;

        }

        .trending-title i {

            font-size: 28px;

        }

        .trending-item {

            padding: 18px;

            gap: 15px;

            border-radius: 12px;

        }

        .trending-number {

            width: 42px;

            height: 42px;

            font-size: 16px;

        }

        .trending-content h4 {

            font-size: 16px;

        }

        .trending-meta {

            font-size: 13px;

            gap: 10px;

        }

        /* MOBILE SIDEBAR - ENHANCED */

        .trending-sidebar {

            gap: 20px;

            min-height: auto; /* Remove fixed height on mobile */

        }

        .widget {

            padding: 22px;

            border-radius: 15px;

        }

        .widget-title {

            font-size: 20px;

            gap: 12px;

        }

        .categories-grid {

            

            gap: 10px;

        }

        .category-item {

            padding: 12px 15px;

            font-size: 14px;

        }

        .widget-item {

            padding: 15px;

            gap: 12px;

        }

        .widget-item img {

            width: 55px;

            height: 55px;

        }

        .widget-content h5 {

            font-size: 14px;

        }

    }

    /* TABLET RESPONSIVE */

    @media (max-width: 1024px) and (min-width: 769px) {

        .trending-section {

            gap: 25px;

        }

        .trending-main {

            padding: 30px;

        }

        .widget {

            padding: 25px;

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

<!-- Main Content -->

<div class="container">

    <!-- Featured Section -->

    <div class="featured-section">

        <div class="slideshow-container">

            <?php while($f = $featured->fetch_assoc()): ?>

                <div class="slide">

                    <a href="post.php?slug=<?= urlencode($f['slug']) ?>">

                        <img src="uploads/<?= htmlspecialchars($f['image']) ?>" alt="<?= htmlspecialchars($f['title']) ?>">

                    </a>

                    <div class="slide-content">

                        <h2 class="clamp-title"><?= htmlspecialchars($f['title']) ?></h2>

                        <small>by <?= htmlspecialchars($f['author_name'] ?? 'Unknown') ?> - <?= date("M d, Y", strtotime($f['created_at'])) ?></small>

                        <p><?= mb_strimwidth(strip_tags($f['content']), 0, 100, '...') ?></p>

                        <a class="btn read-more" href="post.php?slug=<?= urlencode($f['slug']) ?>">Read More →</a>

                    </div>

                </div>

            <?php break; endwhile; ?>

        </div>

        <div class="featured-right">

            <?php while($r = $featuredRight->fetch_assoc()): ?>

                <div class="featured-small">

                    <a href="post.php?slug=<?= urlencode($r['slug']) ?>">

                        <img src="uploads/<?= htmlspecialchars($r['image']) ?>" alt="<?= htmlspecialchars($r['title']) ?>">

                    </a>

                    <div class="info">

                        <h3 class="clamp-title"><?= htmlspecialchars($r['title']) ?></h3>

                        <small>by <?= htmlspecialchars($r['author_name'] ?? 'Unknown') ?> - <?= date("M d, Y", strtotime($r['created_at'])) ?></small>

                        <a class="btn read-more" style="margin-top: 8px; display: inline-block;" href="post.php?slug=<?= urlencode($r['slug']) ?>">Read More →</a>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    </div>
        
        <!-- Latest Articles - KEPT ORIGINAL -->

        <div class="grid">

            <?php while($p = $remaining->fetch_assoc()): ?>

                <div class="card">

                    <div class="thumb">

                        <img src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">

                    </div>

                    <div class="content">

                        <h2 class="clamp-title"><?= htmlspecialchars($p['title']) ?></h2>

                        <small>by <?= htmlspecialchars($p['author_name'] ?? 'Unknown') ?> - <?= date("F d, Y", strtotime($p['created_at'])) ?></small>

                        <p><?= mb_strimwidth(strip_tags($p['content']), 0, 150, '...') ?></p>

                        <a class="btn read-more" href="post.php?slug=<?= urlencode($p['slug']) ?>">Read More →</a>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    </div>

    <!-- ENHANCED Trending Section with Sidebar -->

    <div class="trending-section">

        <!-- Trending Main Content -->

        <div class="trending-main">

            <div class="trending-header">

                <h2 class="trending-title">

                    <i class="fas fa-fire"></i> Trending Now

                </h2>

            </div>

            <div class="trending-grid">

                <?php

                $trendingCount = 1;

                if ($trendingPosts && $trendingPosts->num_rows > 0):

                    while(($trending = $trendingPosts->fetch_assoc()) && $trendingCount <= 12):

                ?>

                <div class="trending-item">

                    <div class="trending-number"><?= $trendingCount ?></div>

                    <div class="trending-content">

                        <h4>

                            <a href="post.php?slug=<?= urlencode($trending['slug']) ?>">

                                <?= htmlspecialchars(mb_strimwidth($trending['title'], 0, 70, '...')) ?>

                            </a>

                        </h4>

                        <div class="trending-meta">

                            <span>by <?= htmlspecialchars($trending['author_name'] ?? 'Unknown') ?></span>

                            <span>•</span>

                            <span><?= isset($trending['created_at']) ? date("M d", strtotime($trending['created_at'])) : 'N/A' ?></span>

                            <span>•</span>

                            <span class="trending-views">

                                <i class="fas fa-eye"></i>

                                <?= number_format($trending['views'] ?? rand(100, 5000)) ?>

                            </span>

                        </div>

                    </div>

                </div>

                <?php

                    $trendingCount++;

                    endwhile;

                else:

                ?>

                    <p>No trending posts found.</p>

                <?php endif; ?>

            </div>

        </div>

        <!-- ENHANCED Trending Sidebar -->

        <div class="trending-sidebar">

            <!-- Categories Widget -->

            <div class="widget">

                <h3 class="widget-title">

                    <i class="fas fa-folder"></i>

                    Categories

                </h3>

                <div class="categories-grid">

                    <?php while($category = $categories->fetch_assoc()): ?>

                        <a href="category.php?cat=<?= urlencode($category['category']) ?>" class="category-item">

                            <?= htmlspecialchars($category['category']) ?>

                        </a>

                    <?php endwhile; ?>

                </div>

            </div>

            <!-- Recent Posts Widget -->

            <div class="widget">

                <h3 class="widget-title">

                    <i class="fas fa-clock"></i>

                    Recent Posts

                </h3>

                <div class="widget-list">

                    <?php while($recent = $recentPosts->fetch_assoc()): ?>

                    <div class="widget-item">

                        <a href="post.php?slug=<?= urlencode($recent['slug']) ?>">

                            <img src="uploads/<?= htmlspecialchars($recent['image']) ?>" alt="<?= htmlspecialchars($recent['title']) ?>">

                        </a>

                        <div class="widget-content">

                            <h5><a href="post.php?slug=<?= urlencode($recent['slug']) ?>"><?= htmlspecialchars(mb_strimwidth($recent['title'], 0, 100, '...')) ?></a></h5>

                            <div class="widget-meta"><i class="fas fa-calendar"></i> <?= date("M d, Y", strtotime($recent['created_at'])) ?></div>

                        </div>

                    </div>

                    <?php endwhile; ?>

                </div>

            </div>

            <!-- Popular Posts Widget -->

            <div class="widget">

                <h3 class="widget-title">

                    <i class="fas fa-star"></i>

                    Popular Posts

                </h3>

                <div class="widget-list">

                    <?php while($popular = $popularPosts->fetch_assoc()): ?>

                    <div class="widget-item">

                        <a href="post.php?slug=<?= urlencode($popular['slug']) ?>">

                            <img src="uploads/<?= htmlspecialchars($popular['image']) ?>" alt="<?= htmlspecialchars($popular['title']) ?>">

                        </a>

                        <div class="widget-content">

                            <h5><a href="post.php?slug=<?= urlencode($popular['slug']) ?>"><?= htmlspecialchars(mb_strimwidth($popular['title'], 0, 60, '...')) ?></a></h5>

                            <div class="widget-meta">

                                <i class="fas fa-eye"></i> <?= number_format($popular['views'] ?? rand(100, 5000)) ?> views

                            </div>

                        </div>

                    </div>

                    <?php endwhile; ?>

                </div>

            </div>

        </div>

    </div>

  

<!-- Footer -->

<footer>

    &copy; <?= date('Y') ?> My CMS News. Made with ❤️ in PHP.

</footer>

<!-- Scroll to Top Button -->

<button class="scroll-top" id="scrollTop" onclick="scrollToTop()">

    <i class="fas fa-chevron-up"></i>

</button>

<script>

    function toggleMenu() {

        document.querySelector('.menu').classList.toggle('open');

    }

    function scrollToTop() {

        window.scrollTo({ top: 0, behavior: 'smooth' });

    }

    window.addEventListener('scroll', function () {

        const scrollTop = document.getElementById('scrollTop');

        scrollTop.classList.toggle('visible', window.pageYOffset > 300);

    });

    document.addEventListener('click', function (event) {

        const navMenu = document.querySelector('.menu');

        const mobileToggle = document.querySelector('.hamburger');

        if (!navMenu.contains(event.target) && !mobileToggle.contains(event.target)) {

            navMenu.classList.remove('open');

        }

    });

</script>

</body>

</html>
