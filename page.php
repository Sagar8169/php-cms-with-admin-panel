<?php
require 'config.php';

// Fetch menu for header with dynamic functionality
$menu = $conn->query("SELECT * FROM pages WHERE status = 'published' AND show_in_menu = 1 ORDER BY page_order ASC, id ASC");

// Get the page by slug
if (!isset($_GET['slug'])) {
    die("Page not found.");
}

$slug = $_GET['slug'];
$stmt = $conn->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$page = $result->fetch_assoc();

if (!$page) {
    die("Page not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title']) ?> - My CMS</title>
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

        .container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .content {
            line-height: 1.7;
            color: #444;
        }

        footer {
            text-align: center;
            padding: 30px 20px;
            background: #222;
            color: #ccc;
            margin-top: 40px;
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

    <div class="container">
        <h2><?= htmlspecialchars($page['title']) ?></h2>
        <div class="content">
<?= $page['content'] ?>

        </div>
        <a class="back" href="index.php">&larr; Back to Home</a>
    </div>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop" onclick="scrollToTop()">
        <i class="fas fa-chevron-up"></i>
    </button>

    <footer>
        &copy; <?= date('Y') ?> My CMS News. Made with ❤️ in PHP.
    </footer>

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
    </script>
</body>
</html>
