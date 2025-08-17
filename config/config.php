<?php
// Configuración general de la aplicación
define('APP_NAME', 'CursosMy');
define('APP_VERSION', '1.0.0');

// Configuración de archivos
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('VIDEOS_DIR', UPLOAD_DIR . 'videos/');
define('IMAGES_DIR', UPLOAD_DIR . 'images/');
define('RESOURCES_DIR', UPLOAD_DIR . 'resources/');

// Configuración de videos y recursos
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024 * 1024); // 500GB en bytes
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'm4v', '3gp', 'ogv']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
// Permitir TODOS los tipos de archivo para recursos
define('ALLOWED_RESOURCE_TYPES', []);

// Crear directorios si no existen
function createUploadDirectories() {
    $directories = [UPLOAD_DIR, VIDEOS_DIR, IMAGES_DIR, RESOURCES_DIR];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Función para formatear tamaño de archivo
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

// Función para formatear duración en segundos
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } else {
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}

// Función para generar nombre único de archivo
function generateUniqueFileName($originalName, $directory) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    
    $counter = 1;
    $fileName = $baseName . '.' . $extension;
    
    while (file_exists($directory . $fileName)) {
        $fileName = $baseName . '_' . $counter . '.' . $extension;
        $counter++;
    }
    
    return $fileName;
}

// Función para validar tipo de archivo
function validateFileType($fileName, $allowedTypes) {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    return in_array($extension, $allowedTypes);
}

// Función para respuesta JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Función para limpiar input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Obtener duración de video usando ffprobe (si está disponible)
function getVideoDuration($filePath) {
    $filePath = realpath($filePath) ?: $filePath;
    $escaped = escapeshellarg($filePath);
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $escaped";
    $output = @shell_exec($cmd);
    if ($output !== null && trim($output) !== '' && is_numeric(trim($output))) {
        return (int)round((float)trim($output));
    }
    return 0;
}

// Inicializar directorios
createUploadDirectories();
?>
