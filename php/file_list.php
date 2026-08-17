<?php
/**
 * File List - Get list of files for a project
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rootDir = dirname(__DIR__);
$baseDir = $rootDir . '/img/';
$projectName = isset($_GET['project']) ? $_GET['project'] : '';

if (empty($projectName)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Project name not specified'
    ]);
    exit();
}

// НЕ ЗАМЕНЯЕМ ТОЧКИ - используем имя как есть
// Разрешаем точки в именах проектов
$projectName = preg_replace('/[^a-zA-Z0-9а-яА-Я._\-]/u', '_', $projectName);
$projectPath = $baseDir . $projectName . '/';

// Создаем директорию проекта если её нет
if (!is_dir($projectPath)) {
    if (!mkdir($projectPath, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create project directory: ' . $projectName
        ]);
        exit();
    }
    file_put_contents($projectPath . 'index.html', '<html><body>Directory access forbidden.</body></html>');
}

$files = [];

if (is_dir($projectPath) && is_readable($projectPath)) {
    $iterator = new DirectoryIterator($projectPath);

    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isDot()) {
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'url' => '/img/' . $projectName . '/' . $file->getFilename()
                ];
            }
        }
    }
}

usort($files, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

echo json_encode([
    'success' => true,
    'project' => $projectName,
    'files' => $files,
    'total' => count($files)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();