<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

require '../config.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid page ID.");
}

$id = intval($_GET['id']);

// Delete the page
$stmt = $conn->prepare("DELETE FROM pages WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: pages.php?deleted=1");
    exit;
} else {
    echo "Error deleting page.";
}
?>

