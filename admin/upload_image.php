<?php
// Clean output
ob_clean();
header('Content-Type: application/json');

// Enable error reporting during debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if file is uploaded
if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => ['message' => 'Upload failed or file missing.']]);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$fileType = mime_content_type($_FILES['upload']['tmp_name']);
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['error' => ['message' => 'Only JPG, PNG, WEBP, GIF allowed.']]);
    exit;
}

// Sanitize filename
$originalName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $_FILES['upload']['name']);
$filename = time() . '_' . $originalName;

// Prepare path
$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$targetFile = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($_FILES['upload']['tmp_name'], $targetFile)) {
    echo json_encode(['url' => '/uploads/' . $filename]);
} else {
    echo json_encode(['error' => ['message' => 'Failed to move uploaded file.']]);
}
?>
