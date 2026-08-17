<?php
/**
 * Projects List - Get and create projects
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rootDir = dirname(__DIR__);
$baseDir = $rootDir . '/img/';

// Создаем базовую папку если её нет
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

/**
 * Санитизация имени проекта - разрешаем точки
 */
function sanitizeProjectName($name) {
    // Разрешаем: буквы (латиница и кириллица), цифры, точки, дефисы, подчеркивания
    // Заменяем все недопустимые символы на подчеркивание
    return preg_replace('/[^a-zA-Z0-9а-яА-Я._\-]/u', '_', $name);
}

/**
 * Получение списка проектов
 */
function getProjects($baseDir) {
    $projects = [];

    if (!is_dir($baseDir)) {
        return $projects;
    }

    $iterator = new DirectoryIterator($baseDir);

    foreach ($iterator as $dir) {
        if ($dir->isDir() && !$dir->isDot()) {
            $projectName = $dir->getFilename();
            // Пропускаем системные папки
            if ($projectName === 'index.html') continue;

            // Проверяем, есть ли в папке изображения
            $hasImages = false;
            $fileIterator = new DirectoryIterator($dir->getPathname());
            foreach ($fileIterator as $file) {
                if ($file->isFile() && !$file->isDot()) {
                    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $hasImages = true;
                        break;
                    }
                }
            }

            $projects[] = [
                'name' => $projectName,
                'hasImages' => $hasImages,
                'modified' => $dir->getMTime()
            ];
        }
    }

    // Сортируем по дате изменения
    usort($projects, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    return $projects;
}

/**
 * Создание нового проекта
 */
function createProject($baseDir, $projectName) {
    // Санитизируем имя проекта
    $projectName = sanitizeProjectName($projectName);
    if (empty($projectName)) {
        return ['error' => 'Invalid project name'];
    }

    $projectPath = $baseDir . $projectName . '/';

    if (is_dir($projectPath)) {
        return ['error' => 'Project already exists'];
    }

    if (!mkdir($projectPath, 0755, true)) {
        return ['error' => 'Failed to create project directory'];
    }

    // Создаем index.html для защиты
    file_put_contents($projectPath . 'index.html', '<html><body>Directory access forbidden.</body></html>');

    return ['success' => true, 'project' => $projectName];
}

// === ОСНОВНАЯ ЛОГИКА ===

$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    if ($method === 'GET') {
        $projects = getProjects($baseDir);
        $response = [
            'success' => true,
            'projects' => array_column($projects, 'name'),
            'total' => count($projects),
            'details' => $projects
        ];
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = isset($input['action']) ? $input['action'] : '';
        $projectName = isset($input['project']) ? $input['project'] : '';

        if ($action === 'create') {
            $result = createProject($baseDir, $projectName);
            if (isset($result['error'])) {
                $response = ['success' => false, 'error' => $result['error']];
            } else {
                $response = ['success' => true, 'project' => $result['project']];
            }
        } else {
            $response = ['error' => 'Unknown action'];
        }
    } else {
        http_response_code(405);
        $response = ['error' => 'Method not allowed'];
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();