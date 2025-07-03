<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$id = $_GET['id'];
$conn->query("DELETE FROM posts WHERE id=$id");
header("Location: dashboard.php");

