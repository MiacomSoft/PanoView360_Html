<?php
/**
 * Init Script - Создание всех необходимых директорий
 * Запустите этот скрипт один раз для инициализации
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$rootDir = dirname(__DIR__);
$directories = [
    'uploads/',
    'uploads/images/',
    'uploads/thumbs/',
    'php/',
    'logs/'
];

echo "<h1>Инициализация проекта</h1>";
echo "<p>Корневая директория: " . $rootDir . "</p>";

$allCreated = true;

foreach ($directories as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            echo "<p style='color:green;'>✓ Создана папка: " . $dir . "</p>";
        } else {
            echo "<p style='color:red;'>✗ Ошибка создания папки: " . $dir . "</p>";
            $allCreated = false;
        }
    } else {
        echo "<p style='color:blue;'>• Папка уже существует: " . $dir . "</p>";
    }
}

// Создаем index.html для защиты
$indexFile = $rootDir . '/uploads/index.html';
if (!file_exists($indexFile)) {
    file_put_contents($indexFile, '<html><body>Directory access forbidden.</body></html>');
    echo "<p style='color:green;'>✓ Создан файл защиты: uploads/index.html</p>";
}

// Проверяем права на запись
$testFile = $rootDir . '/uploads/test_write.txt';
if (file_put_contents($testFile, 'test') !== false) {
    unlink($testFile);
    echo "<p style='color:green;'>✓ Права на запись в папку uploads есть</p>";
} else {
    echo "<p style='color:red;'>✗ Нет прав на запись в папку uploads</p>";
}

if ($allCreated) {
    echo "<h2 style='color:green;'>✅ Инициализация завершена успешно!</h2>";
    echo "<p>Теперь вы можете использовать массовую загрузку файлов.</p>";
} else {
    echo "<h2 style='color:red;'>⚠️ Инициализация завершена с ошибками</h2>";
    echo "<p>Проверьте права на запись в папку проекта.</p>";
}

// Ссылка для проверки
echo "<br><a href='file_list.php'>Проверить file_list.php</a> | ";
echo "<a href='../edit.html'>Открыть редактор</a>";