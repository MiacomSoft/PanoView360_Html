<?php
/**
 * Test script - проверка работоспособности PHP
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Test</h1>";
echo "<p>PHP version: " . phpversion() . "</p>";
echo "<p>Upload dir: " . __DIR__ . "/../uploads/</p>";

// Проверяем создание папки
$upload_dir = __DIR__ . '/../uploads/';
if (is_dir($upload_dir)) {
    echo "<p style='color:green'>✓ Upload directory exists</p>";
} else {
    echo "<p style='color:orange'>Creating upload directory...</p>";
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p style='color:green'>✓ Upload directory created</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create upload directory</p>";
    }
}

// Проверяем права на запись
if (is_writable($upload_dir)) {
    echo "<p style='color:green'>✓ Upload directory is writable</p>";
} else {
    echo "<p style='color:red'>✗ Upload directory is not writable</p>";
}

// Список файлов
echo "<h2>Files in upload directory:</h2>";
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . htmlspecialchars($file) . " (" . filesize($upload_dir . $file) . " bytes)</li>";
        }
    }
    echo "</ul>";
}