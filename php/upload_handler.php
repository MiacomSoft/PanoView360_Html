<?php
/**
 * Upload Handler - Mass file upload for panorama editor
 * 
 * GET  - Получить список файлов проекта
 * POST - Загрузить файлы в проект
 * DELETE - Удалить файл
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

// Определяем корневую директорию проекта
$rootDir = dirname(__DIR__);
$baseDir = $rootDir . '/img/';

// Создаем базовую папку если её нет
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
$max_file_size = 50 * 1024 * 1024; // 50MB
$max_files = 50;

/**
 * Санитизация имени проекта - разрешаем точки
 */
function sanitizeProjectName($name) {
    // Разрешаем: буквы (латиница и кириллица), цифры, точки, дефисы, подчеркивания
    return preg_replace('/[^a-zA-Z0-9а-яА-Я._\-]/u', '_', $name);
}

/**
 * Создание директории проекта
 */
function createProjectDirectory($baseDir, $projectName) {
    $projectName = sanitizeProjectName($projectName);
    if (empty($projectName)) {
        return ['error' => 'Invalid project name'];
    }

    $projectPath = $baseDir . $projectName . '/';

    if (!is_dir($projectPath)) {
        if (!mkdir($projectPath, 0755, true)) {
            return ['error' => 'Failed to create project directory: ' . $projectName];
        }
        file_put_contents($projectPath . 'index.html', '<html><body>Directory access forbidden.</body></html>');
    }

    return ['success' => true, 'path' => $projectPath];
}

/**
 * Получение списка файлов проекта
 */
function getProjectFiles($baseDir, $projectName) {
    $projectName = sanitizeProjectName($projectName);
    $projectPath = $baseDir . $projectName . '/';

    $files = [];

    if (!is_dir($projectPath)) {
        return $files;
    }

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

    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    return $files;
}

/**
 * Обработка GET запроса
 */
function handleGet($baseDir) {
    $projectName = isset($_GET['project']) ? $_GET['project'] : '';

    if (empty($projectName)) {
        return ['error' => 'Project name not specified'];
    }

    $projectName = sanitizeProjectName($projectName);
    $files = getProjectFiles($baseDir, $projectName);

    return [
        'success' => true,
        'project' => $projectName,
        'files' => $files,
        'total' => count($files)
    ];
}

/**
 * Обработка POST запроса
 */
function handleUpload($baseDir, $allowed_types, $max_file_size, $max_files) {
    $projectName = isset($_POST['project']) ? $_POST['project'] : '';

    if (empty($projectName)) {
        return ['error' => 'Project name not specified'];
    }

    $projectName = sanitizeProjectName($projectName);

    $result = createProjectDirectory($baseDir, $projectName);
    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }
    $projectPath = $result['path'];

    if (!isset($_FILES['files'])) {
        return ['error' => 'No files uploaded'];
    }

    if (empty($_FILES['files']['name'][0]) || $_FILES['files']['name'][0] === '') {
        return ['error' => 'No files selected'];
    }

    $uploaded = [];
    $errors = [];
    $totalFiles = count($_FILES['files']['name']);

    if ($totalFiles > $max_files) {
        return ['error' => 'Too many files. Maximum: ' . $max_files];
    }

    for ($i = 0; $i < $totalFiles; $i++) {
        if (empty($_FILES['files']['name'][$i])) {
            continue;
        }

        $file = [
            'name' => $_FILES['files']['name'][$i],
            'type' => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error' => $_FILES['files']['error'][$i],
            'size' => $_FILES['files']['size'][$i]
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $file['name'] . ': Upload error code ' . $file['error'];
            continue;
        }

        $fileType = $file['type'];
        if (empty($fileType) || $fileType === 'application/octet-stream') {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png'
            ];
            $fileType = isset($mimeMap[$ext]) ? $mimeMap[$ext] : $fileType;
        }

        if (!in_array($fileType, $allowed_types)) {
            $errors[] = $file['name'] . ': Invalid file type (allowed: JPG, PNG)';
            continue;
        }

        if ($file['size'] > $max_file_size) {
            $errors[] = $file['name'] . ': File too large (max ' . ($max_file_size / 1024 / 1024) . 'MB)';
            continue;
        }

        // Сохраняем оригинальное имя
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));

        // Проверяем, есть ли файл с таким именем
        $destination = $projectPath . $file['name'];
        if (file_exists($destination)) {
            // Если есть - генерируем уникальное имя
            $uniqueName = $baseName . '_' . uniqid() . '.' . $extension;
            $destination = $projectPath . $uniqueName;
        } else {
            $uniqueName = $file['name'];
        }

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $uploaded[] = [
                'original' => $file['name'],
                'saved' => $uniqueName,
                'url' => '/img/' . $projectName . '/' . $uniqueName,
                'size' => $file['size']
            ];
        } else {
            $errors[] = $file['name'] . ': Failed to save file';
        }
    }

    return [
        'success' => true,
        'project' => $projectName,
        'uploaded' => $uploaded,
        'errors' => $errors,
        'total' => count($uploaded)
    ];
}

/**
 * Обработка DELETE запроса
 */
function handleDelete($baseDir) {
    $input = json_decode(file_get_contents('php://input'), true);
    $projectName = isset($input['project']) ? $input['project'] : '';
    $filename = isset($input['filename']) ? $input['filename'] : '';

    if (empty($projectName)) {
        return ['error' => 'Project name not specified'];
    }
    if (empty($filename)) {
        return ['error' => 'Filename not specified'];
    }

    $projectName = sanitizeProjectName($projectName);
    $filename = basename($filename);
    $filePath = $baseDir . $projectName . '/' . $filename;

    if (!file_exists($filePath)) {
        return ['error' => 'File not found'];
    }

    if (unlink($filePath)) {
        return ['success' => true, 'message' => 'File deleted'];
    } else {
        return ['error' => 'Failed to delete file'];
    }
}

// === ОСНОВНАЯ ЛОГИКА ===

$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    switch ($method) {
        case 'GET':
            $response = handleGet($baseDir);
            break;

        case 'POST':
            $response = handleUpload($baseDir, $allowed_types, $max_file_size, $max_files);
            break;

        case 'DELETE':
            $response = handleDelete($baseDir);
            break;

        default:
            http_response_code(405);
            $response = ['error' => 'Method not allowed'];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();