<?php
/**
 * Projects List - Get, create, and manage projects
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$rootDir = dirname(__DIR__);
$baseDir = $rootDir . '/img/';

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

/**
 * Санитизация имени проекта - разрешаем точки
 */
function sanitizeProjectName($name) {
    return preg_replace('/[^a-zA-Z0-9а-яА-Я._\-]/u', '_', $name);
}

/**
 * Получение содержимого файла .project.json
 * Если файла нет - создает его автоматически
 */
function getProjectConfig($baseDir, $projectName) {
    $projectName = sanitizeProjectName($projectName);
    $projectPath = $baseDir . $projectName . '/';
    $configFile = $projectPath . '.project.json';

    // Убеждаемся что директория существует
    if (!is_dir($projectPath)) {
        mkdir($projectPath, 0755, true);
        file_put_contents($projectPath . 'index.html', '<html><body>Directory access forbidden.</body></html>');
    }

    // Получаем список изображений в папке
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

    // Если файл существует - читаем его
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        if ($config && is_array($config)) {
            // Объединяем с дефолтными значениями
            $merged = array_merge($defaultConfig, $config);
            // Проверяем, существует ли main_scene в папке
            if ($merged['main_scene'] && !in_array($merged['main_scene'], $images)) {
                $merged['main_scene'] = !empty($images) ? $images[0] : null;
                $merged['preview'] = $merged['main_scene'];
            }
            // Если main_scene не задан, но есть изображения - устанавливаем первое
            if (!$merged['main_scene'] && !empty($images)) {
                $merged['main_scene'] = $images[0];
                $merged['preview'] = $images[0];
            }
            return $merged;
        }
    }

    // Если файла нет или он поврежден - создаем с дефолтными значениями
    saveProjectConfig($baseDir, $projectName, $defaultConfig);
    return $defaultConfig;
}

/**
 * Сохранение файла .project.json
 */
function saveProjectConfig($baseDir, $projectName, $config) {
    $projectName = sanitizeProjectName($projectName);
    $projectPath = $baseDir . $projectName . '/';
    $configFile = $projectPath . '.project.json';

    // Убеждаемся что директория существует
    if (!is_dir($projectPath)) {
        mkdir($projectPath, 0755, true);
        file_put_contents($projectPath . 'index.html', '<html><body>Directory access forbidden.</body></html>');
    }

    // Обновляем дату
    $config['updated_at'] = date('c');
    $config['name'] = $projectName;

    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($configFile, $json) !== false;
}

/**
 * Получение списка проектов с их конфигурациями
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
            if ($projectName === 'index.html') continue;

            // Получаем конфигурацию проекта (автоматически создается если нет)
            $config = getProjectConfig($baseDir, $projectName);

            // Проверяем, есть ли в папке изображения
            $hasImages = false;
            $images = [];
            $fileIterator = new DirectoryIterator($dir->getPathname());
            foreach ($fileIterator as $file) {
                if ($file->isFile() && !$file->isDot()) {
                    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $hasImages = true;
                        $images[] = $file->getFilename();
                    }
                }
            }

            $projects[] = [
                'name' => $projectName,
                'hasImages' => $hasImages,
                'modified' => $dir->getMTime(),
                'config' => $config,
                'main_scene' => $config['main_scene'] ?? null,
                'images' => $images,
                'imageCount' => count($images)
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

    // Создаем файл конфигурации проекта
    $config = [
        'name' => $projectName,
        'title' => $projectName,
        'description' => '',
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'main_scene' => null,
        'preview' => null,
        'settings' => [
            'yaw' => 0,
            'pitch' => 0,
            'hfov' => 100
        ],
        'author' => ''
    ];
    saveProjectConfig($baseDir, $projectName, $config);

    return ['success' => true, 'project' => $projectName];
}

/**
 * Обновление конфигурации проекта
 */
function updateProjectConfig($baseDir, $projectName, $data) {
    $projectName = sanitizeProjectName($projectName);
    $projectPath = $baseDir . $projectName . '/';

    if (!is_dir($projectPath)) {
        return ['error' => 'Project not found'];
    }

    // Получаем текущую конфигурацию
    $config = getProjectConfig($baseDir, $projectName);

    // Обновляем поля
    if (isset($data['title'])) $config['title'] = $data['title'];
    if (isset($data['description'])) $config['description'] = $data['description'];
    if (isset($data['main_scene'])) $config['main_scene'] = $data['main_scene'];
    if (isset($data['preview'])) $config['preview'] = $data['preview'];
    if (isset($data['author'])) $config['author'] = $data['author'];
    if (isset($data['settings'])) {
        $config['settings'] = array_merge($config['settings'], $data['settings']);
    }

    if (saveProjectConfig($baseDir, $projectName, $config)) {
        return ['success' => true, 'config' => $config];
    } else {
        return ['error' => 'Failed to save configuration'];
    }
}

/**
 * Удаление проекта
 */
function deleteProject($baseDir, $projectName) {
    $projectName = sanitizeProjectName($projectName);
    $projectPath = $baseDir . $projectName . '/';

    if (!is_dir($projectPath)) {
        return ['error' => 'Project not found'];
    }

    // Проверяем, есть ли файлы в папке (кроме .project.json и index.html)
    $files = scandir($projectPath);
    $hasFiles = false;
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && $file !== '.project.json' && $file !== 'index.html') {
            $hasFiles = true;
            break;
        }
    }

    if ($hasFiles) {
        return ['error' => 'Cannot delete project with images. Remove images first.'];
    }

    // Удаляем конфиг и папку
    if (file_exists($projectPath . '.project.json')) {
        unlink($projectPath . '.project.json');
    }
    if (file_exists($projectPath . 'index.html')) {
        unlink($projectPath . 'index.html');
    }
    rmdir($projectPath);
    return ['success' => true, 'message' => 'Project deleted'];
}

// === ОСНОВНАЯ ЛОГИКА ===

$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    if ($method === 'GET') {
        // GET /php/projects_list.php - список всех проектов
        // GET /php/projects_list.php?project=NAME - конфигурация конкретного проекта
        $projectName = isset($_GET['project']) ? $_GET['project'] : '';

        if ($projectName) {
            $projectName = sanitizeProjectName($projectName);
            $config = getProjectConfig($baseDir, $projectName);
            $response = [
                'success' => true,
                'project' => $projectName,
                'config' => $config
            ];
        } else {
            $projects = getProjects($baseDir);
            $response = [
                'success' => true,
                'projects' => $projects,
                'total' => count($projects)
            ];
        }
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
        } elseif ($action === 'update') {
            $data = isset($input['data']) ? $input['data'] : [];
            $result = updateProjectConfig($baseDir, $projectName, $data);
            if (isset($result['error'])) {
                $response = ['success' => false, 'error' => $result['error']];
            } else {
                $response = ['success' => true, 'config' => $result['config']];
            }
        } else {
            $response = ['error' => 'Unknown action'];
        }
    } elseif ($method === 'PUT') {
        // PUT /php/projects_list.php - обновление конфигурации
        $input = json_decode(file_get_contents('php://input'), true);
        $projectName = isset($input['project']) ? $input['project'] : '';
        $data = isset($input['data']) ? $input['data'] : [];

        if (empty($projectName)) {
            $response = ['error' => 'Project name not specified'];
        } else {
            $result = updateProjectConfig($baseDir, $projectName, $data);
            if (isset($result['error'])) {
                $response = ['success' => false, 'error' => $result['error']];
            } else {
                $response = ['success' => true, 'config' => $result['config']];
            }
        }
    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $projectName = isset($input['project']) ? $input['project'] : '';

        if (empty($projectName)) {
            $response = ['error' => 'Project name not specified'];
        } else {
            $result = deleteProject($baseDir, $projectName);
            if (isset($result['error'])) {
                $response = ['success' => false, 'error' => $result['error']];
            } else {
                $response = ['success' => true, 'message' => $result['message']];
            }
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