<?php
// Desactivar mostrar errores para evitar que interfieran con JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// INCLUIR LÍMITES DE UPLOAD ANTES DE CUALQUIER OPERACIÓN
require_once '../config/upload-limits.php';

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
}

$db = getDatabase();

try {
    // Log información de debug
    error_log("[UPLOAD DEBUG] Iniciando upload - POST size: " . strlen(file_get_contents('php://input')));
    error_log("[UPLOAD DEBUG] POST data: " . print_r($_POST, true));
    error_log("[UPLOAD DEBUG] FILES data: " . print_r($_FILES, true));
    
    $curso_id = isset($_POST['curso_id']) ? (int)$_POST['curso_id'] : 0;
    $seccion_id = isset($_POST['seccion_id']) ? (int)$_POST['seccion_id'] : 0;
    
    // Información de debug adicional
    $debugInfo = [
        'php_limits' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
        ],
        'request_info' => [
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'post_data_received' => !empty($_POST),
            'files_received' => !empty($_FILES),
        ]
    ];
    
    if (!$curso_id || !$seccion_id) {
        jsonResponse([
            'success' => false, 
            'message' => 'curso_id y seccion_id son obligatorios',
            'debug' => $debugInfo,
            'received_data' => [
                'curso_id' => $curso_id,
                'seccion_id' => $seccion_id,
                'post_keys' => array_keys($_POST),
                'files_keys' => array_keys($_FILES)
            ]
        ], 400);
    }
    
    // Verificar que el curso y la sección existen
    $stmt = $db->prepare("SELECT id FROM cursos WHERE id = ?");
    $stmt->execute([$curso_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Curso no encontrado'], 404);
    }
    
    $stmt = $db->prepare("SELECT id FROM secciones WHERE id = ? AND curso_id = ?");
    $stmt->execute([$seccion_id, $curso_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Sección no encontrada'], 404);
    }
    
    // Verificar que se subieron archivos
    if (!isset($_FILES['videos'])) {
        jsonResponse(['success' => false, 'message' => 'No se recibieron archivos en el campo videos'], 400);
    }
    
    if (empty($_FILES['videos']['name']) || empty($_FILES['videos']['name'][0])) {
        jsonResponse(['success' => false, 'message' => 'No se seleccionaron archivos válidos'], 400);
    }
    
    // Debug: verificar estructura de archivos recibidos
    if (!is_array($_FILES['videos']['name'])) {
        jsonResponse(['success' => false, 'message' => 'Formato de archivos incorrecto'], 400);
    }
    
    $uploadedCount = 0;
    $errors = [];
    
    // Crear directorio del curso si no existe
    $videoDir = VIDEOS_DIR . $curso_id . '/';
    if (!is_dir($videoDir)) {
        mkdir($videoDir, 0755, true);
    }
    
    // Obtener el siguiente orden para las clases
    $stmt = $db->prepare("SELECT COALESCE(MAX(orden), 0) as max_orden FROM clases WHERE seccion_id = ?");
    $stmt->execute([$seccion_id]);
    $nextOrder = $stmt->fetch()['max_orden'] + 1;
    
    // Procesar cada archivo
    $files = $_FILES['videos'];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        // Verificar si hay error en la subida
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Error al subir {$files['name'][$i]}: " . getUploadErrorMessage($files['error'][$i]);
            continue;
        }
        
        $fileName = $files['name'][$i];
        $tmpName = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        
        // Validar tipo de archivo
        if (!validateFileType($fileName, ALLOWED_VIDEO_TYPES)) {
            $errors[] = "Archivo {$fileName}: Tipo no permitido (solo MP4)";
            continue;
        }
        
        // Validar tamaño
        if ($fileSize > MAX_VIDEO_SIZE) {
            $errors[] = "Archivo {$fileName}: Tamaño excede el límite de " . formatBytes(MAX_VIDEO_SIZE);
            continue;
        }
        
        // Generar nombre único para el archivo
        $uniqueFileName = generateUniqueFileName($fileName, $videoDir);
        $targetPath = $videoDir . $uniqueFileName;
        
        // Mover archivo
        if (move_uploaded_file($tmpName, $targetPath)) {
            try {
                // Obtener duración del video (ffprobe si está disponible)
                $duracion = getVideoDuration($targetPath);
                
                // Crear título basado en el nombre del archivo (sin extensión)
                $titulo = pathinfo($fileName, PATHINFO_FILENAME);
                $titulo = str_replace(['_', '-'], ' ', $titulo);
                $titulo = ucwords($titulo);
                
                // Insertar en la base de datos
                $stmt = $db->prepare("INSERT INTO clases (seccion_id, titulo, archivo_video, duracion, orden) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$seccion_id, $titulo, $uniqueFileName, $duracion, $nextOrder]);
                
                $uploadedCount++;
                $nextOrder++;
                
            } catch (Exception $e) {
                // Si hay error en la base de datos, eliminar el archivo
                if (file_exists($targetPath)) {
                    unlink($targetPath);
                }
                $errors[] = "Error al procesar {$fileName}: " . $e->getMessage();
            }
        } else {
            $errors[] = "Error al mover archivo {$fileName}";
        }
    }
    
    // Preparar respuesta
    $response = [
        'success' => $uploadedCount > 0,
        'uploaded' => $uploadedCount,
        'total' => $fileCount
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = "Se subieron {$uploadedCount} de {$fileCount} archivos. " . count($errors) . " errores.";
    } else {
        $response['message'] = "Todos los archivos se subieron exitosamente";
    }
    
    jsonResponse($response);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()], 500);
}

function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'El archivo excede el tamaño máximo permitido por el servidor';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo excede el tamaño máximo del formulario';
        case UPLOAD_ERR_PARTIAL:
            return 'El archivo se subió parcialmente';
        case UPLOAD_ERR_NO_FILE:
            return 'No se seleccionó ningún archivo';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Falta directorio temporal';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Error al escribir archivo en disco';
        case UPLOAD_ERR_EXTENSION:
            return 'Subida detenida por extensión';
        default:
            return 'Error desconocido';
    }
}

// Función getVideoDuration() ahora está definida en config/config.php
?>
