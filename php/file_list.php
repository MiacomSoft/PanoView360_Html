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

// === АВТОМАТИЧЕСКОЕ СОЗДАНИЕ .project.json ===
$configFile = $projectPath . '.project.json';
$config = [];

// Если файла конфига нет - создаем его
if (!file_exists($configFile)) {
    // Получаем список изображений
    $images = [];
    $iterator = new DirectoryIterator($projectPath);
    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isDot()) {
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $images[] = $file->getFilename();
            }
        }
    }
    sort($images);

    $defaultConfig = [
        'name' => $projectName,
        'title' => $projectName,
        'description' => '',
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'main_scene' => !empty($images) ? $images[0] : null,
        'preview' => !empty($images) ? $images[0] : null,
        'settings' => [
            'yaw' => 0,
            'pitch' => 0,
            'hfov' => 100
        ],
        'author' => ''
    ];
    file_put_contents($configFile, json_encode($defaultConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $config = $defaultConfig;
} else {
    $config = json_decode(file_get_contents($configFile), true) ?: [];
}

$files = [];
$images = [];
$jsonFiles = [];

if (is_dir($projectPath) && is_readable($projectPath)) {
    $iterator = new DirectoryIterator($projectPath);

    foreach ($iterator as $file) {
        if ($file->isFile() && !$file->isDot()) {
            $filename = $file->getFilename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $images[] = [
                    'name' => $filename,
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'url' => '/img/' . $projectName . '/' . $filename,
                    'is_main' => ($config['main_scene'] ?? null) === $filename,
                    'has_json' => file_exists($projectPath . pathinfo($filename, PATHINFO_FILENAME) . '.json')
                ];
            } elseif ($ext === 'json' && $filename !== '.project.json') {
                $jsonFiles[] = $filename;
            }
        }
    }
}

// Сортируем изображения: главное первым, остальные по дате
usort($images, function($a, $b) {
    if ($a['is_main'] && !$b['is_main']) return -1;
    if (!$a['is_main'] && $b['is_main']) return 1;
    return $b['modified'] - $a['modified'];
});

echo json_encode([
    'success' => true,
    'project' => $projectName,
    'config' => $config,
    'files' => $images,
    'json_files' => $jsonFiles,
    'total' => count($images),
    'main_scene' => $config['main_scene'] ?? null
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();