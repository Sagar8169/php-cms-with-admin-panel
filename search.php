<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

// Get search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

$posts_result = null;
$total_posts = 0;

if (!empty($search_query)) {
    // Search in title, content, and tags
    $search_term = '%' . $search_query . '%';
    
    // Count total results
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM posts 
                                 WHERE status = 'published' 
                                 AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)");
    $count_stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_posts = $count_result->fetch_assoc()['total'];
    
    // Get search results
    $stmt = $conn->prepare("SELECT posts.*, authors.name AS author_name 
                           FROM posts 
                           LEFT JOIN authors ON posts.author_id = authors.id 
                           WHERE posts.status = 'published' 
                           AND (posts.title LIKE ? OR posts.content LIKE ? OR posts.tags LIKE ?)
                           ORDER BY posts.created_at DESC 
                           LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $search_term, $search_term, $search_term, $posts_per_page, $offset);
    $stmt->execute();
    $posts_result = $stmt->get_result();
}

// Get popular search terms (recent searches simulation)
$popular_searches = ['Technology', 'Health', 'Business', 'Sports', 'Politics', 'Science', 'Entertainment', 'Travel'];

// Fetch menu pages
$menu = $conn->query("SELECT * FROM pages WHERE status = 'published' AND show_in_menu = 1 ORDER BY page_order ASC, id ASC");

// Calculate pagination
$total_pages = ceil($total_posts / $posts_per_page);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results<?= !empty($search_query) ? ' for "' . htmlspecialchars($search_query) . '"' : '' ?> - My CMS Portal</title>
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
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            line-height: 1.6;
            color: var(--text-primary);
        }

        /* Breaking News Ticker */
        .breaking-news {
            background: linear-gradient(135deg, var(--accent-color), #dc2626);
            color: white;
            padding: 12px 0;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .breaking-content {
            display: flex;
            align-items: center;
            white-space: nowrap;
            animation: scroll 35s linear infinite;
        }

        .breaking-label {
            background: rgba(255,255,255,0.25);
            padding: 8px 18px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 12px;
            margin-right: 30px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        @keyframes scroll {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* Header - SAME AS INDEX.PHP */
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
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .main-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 50px;
        }

        .content-area {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }

        .sidebar {
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            height: fit-content;
            position: relative;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #ff6600, #ff8533);
        }

        .sidebar h3 {
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
            font-family: 'Playfair Display', serif;
        }

        .sidebar h3 i {
            color: #ff6600;
            font-size: 24px;
        }

        /* Search Header */
        .search-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 0;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }

        .search-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #ff6600, #ff8533);
        }

        .search-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
            position: relative;
            display: inline-block;
        }

        .search-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border-radius: 2px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            max-width: 600px;
            margin: 30px auto 0;
            align-items: center;
        }

        .search-box {
            flex: 1;
            padding: 18px 25px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .search-box:focus {
            outline: none;
            border-color: #ff6600;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 102, 0, 0.1);
        }

        .search-btn {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border: none;
            padding: 18px 30px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 102, 0, 0.4);
            background: linear-gradient(135deg, #e55a00, #ff6600);
        }

        /* Search Results Info */
        .search-results-info {
            margin-bottom: 30px;
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            font-size: 16px;
            color: #555;
            font-weight: 500;
        }

        .search-results-info strong {
            color: #ff6600;
            font-weight: 700;
        }

        /* Popular Searches */
        .popular-searches {
            margin-bottom: 25px;
        }

        .search-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-tag {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            color: #555;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .search-tag:hover {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 102, 0, 0.3);
        }

        /* Post Cards with Images */
        .post-card {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
            padding: 30px 0;
            border-bottom: 2px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .post-card:last-child {
            border-bottom: none;
        }

        .post-card:hover {
            transform: translateY(-2px);
        }

        .post-image {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .post-image:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .post-image img {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .post-image:hover img {
            transform: scale(1.05);
        }

        .post-content {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .post-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 700;
            line-height: 1.4;
            font-family: 'Playfair Display', serif;
        }

        .post-title a {
            color: #333;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .post-title a:hover {
            color: #ff6600;
        }

        .post-meta {
            color: #777;
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
        }

        .post-meta i {
            color: #ff6600;
        }

        .post-excerpt {
            color: #555;
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .read-more {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
            font-size: 14px;
            align-self: flex-start;
        }

        .read-more:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 102, 0, 0.4);
            background: linear-gradient(135deg, #e55a00, #ff6600);
        }

        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 60px 40px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            border: 2px dashed #e2e8f0;
        }

        .no-results h2 {
            color: #333;
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
        }

        .no-results p {
            color: #666;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .no-results a {
            color: #ff6600;
            text-decoration: none;
            font-weight: 600;
        }

        .no-results a:hover {
            text-decoration: underline;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 12px 18px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .pagination a:hover {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border-color: #ff6600;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 102, 0, 0.3);
        }

        .pagination .current {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border-color: #ff6600;
            box-shadow: 0 6px 18px rgba(255, 102, 0, 0.4);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            color: #ccc;
            padding: 50px 0 30px;
            margin-top: 60px;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .footer-content h3 {
            color: #ff6600;
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }

        .footer-content p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .footer-bottom {
            border-top: 1px solid #444;
            padding-top: 20px;
            margin-top: 30px;
            font-size: 14px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .main-container {
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .sidebar {
                order: -1;
                padding: 22px;
            }

            .content-area {
                padding: 25px 20px;
            }

            .search-header {
                padding: 30px 20px;
            }

            .search-title {
                font-size: 2rem;
            }

            .search-form {
                flex-direction: column;
                gap: 15px;
            }

            .search-btn {
                width: 100%;
                justify-content: center;
            }

            .post-card {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 25px 0;
            }

            .post-image {
                order: -1;
            }

            .post-title {
                font-size: 1.3rem;
            }

            .search-tags {
                gap: 8px;
            }

            .search-tag {
                padding: 6px 12px;
                font-size: 13px;
            }

            .pagination {
                gap: 8px;
            }

            .pagination a, .pagination span {
                padding: 10px 14px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .post-card {
                padding: 20px 0;
            }

            .content-area {
                padding: 20px 15px;
            }

            .sidebar {
                padding: 20px;
            }

            .search-header {
                padding: 25px 15px;
            }

            .post-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
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

<!-- Header - SAME AS INDEX.PHP -->
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

<div class="container">
    <!-- Search Header -->
    <div class="search-header">
        <h1 class="search-title"><i class="fas fa-search"></i> Search Posts</h1>
        <form method="GET" class="search-form">
            <input type="text" name="q" class="search-box" placeholder="Enter your search terms..." value="<?= htmlspecialchars($search_query) ?>" autofocus>
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>

    <div class="main-container">
        <div class="content-area">
            <?php if (!empty($search_query)): ?>
                <div class="search-results-info">
                    <i class="fas fa-info-circle"></i> <strong><?= $total_posts ?></strong> result<?= $total_posts != 1 ? 's' : '' ?> found for "<strong><?= htmlspecialchars($search_query) ?></strong>"
                </div>

                <?php if ($posts_result && $posts_result->num_rows > 0): ?>
                    <?php while ($post = $posts_result->fetch_assoc()): ?>
                        <article class="post-card">
                            <div class="post-image">
                                <a href="post.php?slug=<?= urlencode($post['slug']) ?>">
                                    <img src="uploads/<?= htmlspecialchars($post['image']) ?>"
                                         alt="<?= htmlspecialchars($post['title']) ?>"
                                         onerror="this.src='/placeholder.svg?height=200&width=300'">
                                </a>
                            </div>
                            
                            <div class="post-content">
                                <h2 class="post-title">
                                    <a href="post.php?slug=<?= urlencode($post['slug']) ?>">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </a>
                                </h2>
                                
                                <div class="post-meta">
                                    <span><i class="fas fa-calendar"></i> <?= date("F d, Y", strtotime($post['created_at'])) ?></span>
                                    <?php if (!empty($post['category'])): ?>
                                        <span><i class="fas fa-folder"></i> <?= htmlspecialchars($post['category']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($post['author_name'])): ?>
                                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($post['author_name']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="post-excerpt">
                                    <?= mb_strimwidth(strip_tags($post['content']), 0, 200, '...') ?>
                                </div>

                                <a href="post.php?slug=<?= urlencode($post['slug']) ?>" class="read-more">
                                    Read Full Article <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    <?php endwhile; ?>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?q=<?= urlencode($search_query) ?>&page=<?= $page - 1 ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?q=<?= urlencode($search_query) ?>&page=<?= $i ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?q=<?= urlencode($search_query) ?>&page=<?= $page + 1 ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-results">
                        <h2><i class="fas fa-search"></i> No Results Found</h2>
                        <p>Sorry, no posts were found matching your search criteria "<strong><?= htmlspecialchars($search_query) ?></strong>".</p>
                        <p>Try different keywords or browse our <a href="index.php"><i class="fas fa-home"></i> latest posts</a>.</p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-results">
                    <h2><i class="fas fa-search"></i> Enter Search Terms</h2>
                    <p>Use the search box above to find posts by title, content, or tags.</p>
                    <p>Start exploring our content by entering keywords related to your interests.</p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="sidebar">
            <h3><i class="fas fa-fire"></i> Popular Searches</h3>
            <div class="popular-searches">
                <div class="search-tags">
                    <?php foreach ($popular_searches as $popular_term): ?>
                        <a href="?q=<?= urlencode($popular_term) ?>" class="search-tag">
                            <?= htmlspecialchars($popular_term) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <h3><i class="fas fa-lightbulb"></i> Search Tips</h3>
            <div style="color: #666; font-size: 14px; line-height: 1.6;">
                <p style="margin-bottom: 15px;"><strong>• Use specific keywords</strong> for better results</p>
                <p style="margin-bottom: 15px;"><strong>• Try different word combinations</strong> if you don't find what you're looking for</p>
                <p style="margin-bottom: 15px;"><strong>• Search includes titles, content, and tags</strong></p>
                <p><strong>• Use quotation marks</strong> for exact phrases</p>
            </div>
        </aside>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="footer-content">
        <h3>PHP CMS News</h3>
        <p>Your trusted source for breaking news, in-depth analysis, and comprehensive coverage of current events from around the world.</p>
        
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> PHP CMS News. All rights reserved. Made with ❤️ in PHP.
        </div>
    </div>
</footer>

<script>
    function toggleMenu() {
        document.querySelector('.menu').classList.toggle('open');
    }

    document.addEventListener('click', function (event) {
        const navMenu = document.querySelector('.menu');
        const mobileToggle = document.querySelector('.hamburger');
        if (!navMenu.contains(event.target) && !mobileToggle.contains(event.target)) {
            navMenu.classList.remove('open');
        }
    });

    // Add smooth scrolling for better UX
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Auto-focus search box on page load
    document.addEventListener('DOMContentLoaded', function() {
        const searchBox = document.querySelector('.search-box');
        if (searchBox && !searchBox.value) {
            searchBox.focus();
        }
    });
</script>

</body>
</html>
