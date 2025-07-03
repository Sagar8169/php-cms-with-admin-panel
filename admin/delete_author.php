<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $authorId = intval($_GET['id']);

    // Check if author has posts
    $check = $conn->query("SELECT COUNT(*) as total FROM posts WHERE author_id = $authorId");
    $data = $check->fetch_assoc();

    if ($data['total'] > 0) {
        // Author has posts, don't delete
        echo "<script>alert('❌ Cannot delete this author because they have published posts.'); window.location.href='authors.php';</script>";
        exit;
    }

    // Delete the author
    $conn->query("DELETE FROM authors WHERE id = $authorId");

    echo "<script>alert('✅ Author deleted successfully.'); window.location.href='authors.php';</script>";
    exit;
} else {
    header("Location: authors.php");
    exit;
}


