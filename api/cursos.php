<?php
// Desactivar mostrar errores para evitar que interfieran con JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/logger.php';

header('Content-Type: application/json');

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            // Permitir override para actualizar vía multipart/form-data
            if (isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
                handlePut($db);
            } else {
                handlePost($db);
            }
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()], 500);
}

function handleGet($db) {
    if (isset($_GET['id'])) {
        // Obtener curso específico con información adicional
        $query = "
            SELECT c.*, 
                   i.nombre as instructor_nombre,
                   t.nombre as tematica_nombre
            FROM cursos c
            LEFT JOIN instructores i ON c.instructor_id = i.id
            LEFT JOIN tematicas t ON c.tematica_id = t.id
            WHERE c.id = ?
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$_GET['id']]);
        $curso = $stmt->fetch();
        
        if ($curso) {
            jsonResponse(['success' => true, 'data' => $curso]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Curso no encontrado'], 404);
        }
    } else {
        // Obtener todos los cursos con información adicional
        $query = "
            SELECT c.*, 
                   i.nombre as instructor_nombre,
                   t.nombre as tematica_nombre,
                   (SELECT COUNT(*) FROM secciones s WHERE s.curso_id = c.id) as total_secciones,
                   (SELECT COUNT(*) FROM clases cl 
                    JOIN secciones s ON cl.seccion_id = s.id 
                    WHERE s.curso_id = c.id) as total_clases
            FROM cursos c
            LEFT JOIN instructores i ON c.instructor_id = i.id
            LEFT JOIN tematicas t ON c.tematica_id = t.id
            ORDER BY c.created_at DESC
        ";
        $cursos = $db->query($query)->fetchAll();
        jsonResponse(['success' => true, 'data' => $cursos]);
    }
}

function handlePost($db) {
    $titulo = isset($_POST['titulo']) ? cleanInput($_POST['titulo']) : '';
    $tematica_id = isset($_POST['tematica_id']) && !empty($_POST['tematica_id']) ? (int)$_POST['tematica_id'] : null;
    $instructor_id = isset($_POST['instructor_id']) && !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null;
    $comentarios = isset($_POST['comentarios']) ? cleanInput($_POST['comentarios']) : null;
    
    if (empty($titulo)) {
        jsonResponse(['success' => false, 'message' => 'El título es obligatorio'], 400);
    }
    
    // Manejar subida de imagen
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $imagen = handleImageUpload($_FILES['imagen']);
        if (!$imagen) {
            jsonResponse(['success' => false, 'message' => 'Error al subir la imagen'], 400);
        }
    }
    
    // Validar que temática e instructor existan si se proporcionan
    if ($tematica_id) {
        $stmt = $db->prepare("SELECT id FROM tematicas WHERE id = ?");
        $stmt->execute([$tematica_id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Temática no encontrada'], 400);
        }
    }
    
    if ($instructor_id) {
        $stmt = $db->prepare("SELECT id FROM instructores WHERE id = ?");
        $stmt->execute([$instructor_id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Instructor no encontrado'], 400);
        }
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO cursos (titulo, tematica_id, instructor_id, comentarios, imagen) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $tematica_id, $instructor_id, $comentarios, $imagen]);
        
        $cursoId = $db->lastInsertId();
        
        // Crear directorio para videos del curso (resources se creará solo si se suben recursos)
        $videoDir = VIDEOS_DIR . $cursoId . '/';
        if (!is_dir($videoDir)) {
            mkdir($videoDir, 0755, true);
        }
        
        jsonResponse([
            'success' => true, 
            'message' => 'Curso creado exitosamente',
            'data' => ['id' => $cursoId]
        ]);
    } catch (PDOException $e) {
        // Limpiar imagen si hubo error
        if ($imagen && file_exists(IMAGES_DIR . $imagen)) {
            unlink(IMAGES_DIR . $imagen);
        }
        throw $e;
    }
}

function handlePut($db) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Modo JSON (actual existente)
    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id']) || !isset($input['titulo']) || empty(trim($input['titulo']))) {
            jsonResponse(['success' => false, 'message' => 'ID y título son obligatorios'], 400);
        }
        
        $id = (int)$input['id'];
        $titulo = cleanInput($input['titulo']);
        $tematica_id = isset($input['tematica_id']) && !empty($input['tematica_id']) ? (int)$input['tematica_id'] : null;
        $instructor_id = isset($input['instructor_id']) && !empty($input['instructor_id']) ? (int)$input['instructor_id'] : null;
        $comentarios = isset($input['comentarios']) ? cleanInput($input['comentarios']) : null;
        
        // Verificar si el curso existe
        $stmt = $db->prepare("SELECT id FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Curso no encontrado'], 404);
        }
        
        // Validaciones relacionales
        if ($tematica_id) {
            $stmt = $db->prepare("SELECT id FROM tematicas WHERE id = ?");
            $stmt->execute([$tematica_id]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Temática no encontrada'], 400);
            }
        }
        if ($instructor_id) {
            $stmt = $db->prepare("SELECT id FROM instructores WHERE id = ?");
            $stmt->execute([$instructor_id]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'message' => 'Instructor no encontrado'], 400);
            }
        }
        
        $stmt = $db->prepare("UPDATE cursos SET titulo = ?, tematica_id = ?, instructor_id = ?, comentarios = ? WHERE id = ?");
        $stmt->execute([$titulo, $tematica_id, $instructor_id, $comentarios, $id]);
        
        jsonResponse(['success' => true, 'message' => 'Curso actualizado exitosamente']);
        return;
    }
    
    // Modo multipart/form-data para actualizar título/campos + imagen o eliminar imagen
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $titulo = isset($_POST['titulo']) ? cleanInput($_POST['titulo']) : '';
    if (!$id || !$titulo) {
        jsonResponse(['success' => false, 'message' => 'ID y título son obligatorios'], 400);
    }
    
    $tematica_id = isset($_POST['tematica_id']) && !empty($_POST['tematica_id']) ? (int)$_POST['tematica_id'] : null;
    $instructor_id = isset($_POST['instructor_id']) && !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null;
    $comentarios = isset($_POST['comentarios']) ? cleanInput($_POST['comentarios']) : null;
    $delete_image = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';
    
    // Obtener curso actual
    $stmt = $db->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([$id]);
    $curso = $stmt->fetch();
    if (!$curso) {
        jsonResponse(['success' => false, 'message' => 'Curso no encontrado'], 404);
    }
    
    // Validaciones relacionales
    if ($tematica_id) {
        $stmt = $db->prepare("SELECT id FROM tematicas WHERE id = ?");
        $stmt->execute([$tematica_id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Temática no encontrada'], 400);
        }
    }
    if ($instructor_id) {
        $stmt = $db->prepare("SELECT id FROM instructores WHERE id = ?");
        $stmt->execute([$instructor_id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Instructor no encontrado'], 400);
        }
    }
    
    $newImageName = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $newImageName = handleImageUpload($_FILES['imagen']);
        if (!$newImageName) {
            jsonResponse(['success' => false, 'message' => 'Error al subir la nueva imagen'], 400);
        }
    }
    
    // Construir update
    $stmt = $db->prepare("UPDATE cursos SET titulo = ?, tematica_id = ?, instructor_id = ?, comentarios = ?, imagen = ? WHERE id = ?");
    
    // Determinar valor de imagen a guardar
    $imagenToSave = $curso['imagen'];
    if ($delete_image) {
        $imagenToSave = null;
    }
    if ($newImageName) {
        $imagenToSave = $newImageName;
    }
    
    $stmt->execute([$titulo, $tematica_id, $instructor_id, $comentarios, $imagenToSave, $id]);
    
    // Si se reemplazó o eliminó, borrar archivo antiguo
    if (($delete_image || $newImageName) && $curso['imagen']) {
        $oldPath = IMAGES_DIR . $curso['imagen'];
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }
    
    jsonResponse(['success' => true, 'message' => 'Curso actualizado exitosamente', 'imagen' => $imagenToSave]);
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID es obligatorio'], 400);
    }
    
    $id = (int)$input['id'];
    $deleteVideos = isset($input['deleteVideos']) ? (bool)$input['deleteVideos'] : false;
    $debug = isset($input['debug']) ? (bool)$input['debug'] : false;
    
    // Iniciar logging de eliminación
    $sessionId = uniqid('del_', true);
    Logger::deletion("🔍 INICIO ELIMINACIÓN - Sesión: $sessionId", [
        'curso_id' => $id,
        'delete_videos' => $deleteVideos,
        'debug_enabled' => $debug,
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Verificar si el curso existe y obtener información
    $stmt = $db->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->execute([$id]);
    $curso = $stmt->fetch();
    
    if (!$curso) {
        Logger::deletion("❌ CURSO NO ENCONTRADO - Sesión: $sessionId", ['curso_id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Curso no encontrado'], 404);
    }
    
    Logger::deletion("✅ Curso encontrado - Sesión: $sessionId", [
        'curso_id' => $id,
        'titulo' => $curso['titulo'],
        'imagen' => $curso['imagen']
    ]);
    
    try {
        // Iniciar transacción
        $db->beginTransaction();
        
        Logger::deletion("🔄 Transacción iniciada - Sesión: $sessionId");
        
        // Obtener todos los archivos que deben eliminarse ANTES de borrar los registros
        $videosToDelete = [];
        $recursosToDelete = [];
        
        // Obtener videos si se solicita eliminarlos
        if ($deleteVideos) {
            Logger::deletion("📹 Buscando videos para eliminar - Sesión: $sessionId");
            $query = "
                SELECT cl.archivo_video 
                FROM clases cl
                JOIN secciones s ON cl.seccion_id = s.id
                WHERE s.curso_id = ? AND cl.archivo_video IS NOT NULL
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            $videosToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
            Logger::deletion("📹 Videos encontrados - Sesión: $sessionId", [
                'count' => count($videosToDelete),
                'videos' => $videosToDelete
            ]);
        }
        
        // SIEMPRE obtener recursos para eliminarlos (independiente de deleteVideos)
        Logger::deletion("📎 Buscando recursos para eliminar - Sesión: $sessionId");
        $query = "
            SELECT rc.archivo_path
            FROM recursos_clases rc
            JOIN clases cl ON rc.clase_id = cl.id
            JOIN secciones s ON cl.seccion_id = s.id
            WHERE s.curso_id = ? AND rc.archivo_path IS NOT NULL
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $recursosToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);
        Logger::deletion("📎 Recursos encontrados - Sesión: $sessionId", [
            'count' => count($recursosToDelete),
            'recursos' => $recursosToDelete
        ]);

        // Verificar progreso antes de eliminar (SIEMPRE para debug)
        Logger::deletion("📊 Verificando progreso existente - Sesión: $sessionId");
        $progressQuery = "
            SELECT pc.clase_id, pc.tiempo_visto, pc.ultima_visualizacion, cl.archivo_video, cl.titulo
            FROM progreso_clases pc
            JOIN clases cl ON pc.clase_id = cl.id
            JOIN secciones s ON cl.seccion_id = s.id
            WHERE s.curso_id = ?
            ORDER BY pc.ultima_visualizacion DESC
        ";
        $stmt = $db->prepare($progressQuery);
        $stmt->execute([$id]);
        $progreso = $stmt->fetchAll();
        Logger::deletion("📊 Progreso encontrado - Sesión: $sessionId", [
            'count' => count($progreso),
            'detalles' => $progreso
        ]);
        
        // Eliminar curso (las claves foráneas CASCADE eliminarán automáticamente secciones, clases y recursos)
        Logger::deletion("🗑️ Eliminando curso de la base de datos - Sesión: $sessionId");
        $stmt = $db->prepare("DELETE FROM cursos WHERE id = ?");
        $stmt->execute([$id]);
        Logger::deletion("✅ Curso eliminado de BD (CASCADE activo) - Sesión: $sessionId");
        
        // Eliminar imagen del curso si existe
        if ($curso['imagen'] && file_exists(IMAGES_DIR . $curso['imagen'])) {
            @unlink(IMAGES_DIR . $curso['imagen']);
        }
        
        // Eliminar archivos de recursos (SIEMPRE)
        if (!empty($recursosToDelete)) {
            Logger::deletion("📎 INICIANDO ELIMINACIÓN DE RECURSOS - Sesión: $sessionId", [
                'total_recursos' => count($recursosToDelete)
            ]);
            
            $recursosEliminados = 0;
            foreach ($recursosToDelete as $index => $recursoPath) {
                $fullPath = RESOURCES_DIR . $id . '/' . $recursoPath;
                
                Logger::deletion("📄 Procesando recurso " . ($index + 1) . "/" . count($recursosToDelete) . " - Sesión: $sessionId", [
                    'archivo' => $recursoPath,
                    'ruta_completa' => $fullPath,
                    'existe' => file_exists($fullPath)
                ]);
                
                if (file_exists($fullPath)) {
                    $fileSize = filesize($fullPath);
                    Logger::deletion("📏 Archivo encontrado - Sesión: $sessionId", [
                        'archivo' => $recursoPath,
                        'tamaño_mb' => round($fileSize / (1024 * 1024), 2)
                    ]);
                    
                    // Intentar eliminar con retry logic mejorado
                    $deleted = false;
                    for ($attempt = 1; $attempt <= 5; $attempt++) {
                        Logger::deletion("🔄 Intento $attempt de eliminación - Sesión: $sessionId", [
                            'archivo' => $recursoPath,
                            'intento' => $attempt
                        ]);
                        
                        if (@unlink($fullPath)) {
                            $deleted = true;
                            Logger::deletion("✅ ARCHIVO ELIMINADO EXITOSAMENTE - Sesión: $sessionId", [
                                'archivo' => $recursoPath,
                                'intento_exitoso' => $attempt,
                                'ruta' => $fullPath
                            ]);
                            $recursosEliminados++;
                            break;
                        } else {
                            Logger::deletion("❌ Falló intento $attempt - Sesión: $sessionId", [
                                'archivo' => $recursoPath,
                                'intento' => $attempt,
                                'error' => error_get_last()
                            ]);
                        }
                        
                        if ($attempt < 5) {
                            usleep(1000000); // Esperar 1 segundo completo
                        }
                    }
                    
                    if (!$deleted) {
                        Logger::deletion("⚠️ RECURSO NO ELIMINADO - POSIBLE BLOQUEO - Sesión: $sessionId", [
                            'archivo' => $recursoPath,
                            'ruta_completa' => $fullPath,
                            'intentos_fallidos' => 5,
                            'posible_causa' => 'archivo en uso o permisos'
                        ]);
                        error_log("No se pudo eliminar recurso: $fullPath - puede estar en uso");
                    }
                } else {
                    Logger::deletion("⚠️ Recurso no existe - Sesión: $sessionId", [
                        'archivo' => $recursoPath,
                        'ruta_esperada' => $fullPath
                    ]);
                }
            }
            
            Logger::deletion("🎯 FIN ELIMINACIÓN DE RECURSOS - Sesión: $sessionId", [
                'curso_id' => $id,
                'resultado' => 'exitoso',
                'recursos_eliminados' => $recursosEliminados
            ]);
        } else {
            Logger::deletion("ℹ️ No hay recursos para eliminar - Sesión: $sessionId");
        }
        
        // Eliminar videos solo si se solicita
        if ($deleteVideos) {
            Logger::deletion("🎬 INICIANDO ELIMINACIÓN DE VIDEOS - Sesión: $sessionId", [
                'total_videos' => count($videosToDelete)
            ]);
            
            foreach ($videosToDelete as $index => $videoFile) {
                $fullPath = VIDEOS_DIR . $id . '/' . $videoFile;
                
                Logger::deletion("📹 Procesando video " . ($index + 1) . "/" . count($videosToDelete) . " - Sesión: $sessionId", [
                    'archivo' => $videoFile,
                    'ruta_completa' => $fullPath,
                    'existe' => file_exists($fullPath)
                ]);
                
                if (file_exists($fullPath)) {
                    $fileSize = round(filesize($fullPath) / 1024 / 1024, 2);
                    Logger::deletion("📏 Archivo encontrado - Sesión: $sessionId", [
                        'archivo' => $videoFile,
                        'tamaño_mb' => $fileSize
                    ]);
                    
                    // Intentar eliminar con retry logic (para archivos que puedan estar en uso)
                    $deleted = false;
                    for ($attempt = 1; $attempt <= 3; $attempt++) {
                        Logger::deletion("🔄 Intento $attempt de eliminación - Sesión: $sessionId", [
                            'archivo' => $videoFile,
                            'intento' => $attempt
                        ]);
                        
                        if (@unlink($fullPath)) {
                            $deleted = true;
                            Logger::deletion("✅ ARCHIVO ELIMINADO EXITOSAMENTE - Sesión: $sessionId", [
                                'archivo' => $videoFile,
                                'intento_exitoso' => $attempt,
                                'ruta' => $fullPath
                            ]);
                            break;
                        } else {
                            Logger::deletion("❌ Falló intento $attempt - Sesión: $sessionId", [
                                'archivo' => $videoFile,
                                'intento' => $attempt,
                                'error' => error_get_last()
                            ]);
                        }
                        
                        if ($attempt < 3) {
                            usleep(500000); // Esperar 0.5 segundos antes del siguiente intento
                        }
                    }
                    
                    // Si no se pudo eliminar después de 3 intentos, registrar para limpieza posterior
                    if (!$deleted) {
                        Logger::deletion("⚠️ ARCHIVO NO ELIMINADO - POSIBLE BLOQUEO - Sesión: $sessionId", [
                            'archivo' => $videoFile,
                            'ruta_completa' => $fullPath,
                            'intentos_fallidos' => 3,
                            'posible_causa' => 'archivo en uso o permisos'
                        ]);
                        error_log("No se pudo eliminar archivo: $fullPath - puede estar en uso");
                    }
                } else {
                    Logger::deletion("⚠️ Archivo no existe - Sesión: $sessionId", [
                        'archivo' => $videoFile,
                        'ruta_esperada' => $fullPath
                    ]);
                }
            }
        } else {
            Logger::deletion("⏭️ Saltando eliminación de videos (deleteVideos=false) - Sesión: $sessionId");
        }
        
        // Limpiar directorios
        $videoDir = VIDEOS_DIR . $id . '/';
        $resDir = RESOURCES_DIR . $id . '/';
        
        // Eliminar directorio de recursos (siempre, ya que los recursos se eliminaron)
        if (is_dir($resDir)) {
            $remainingFiles = array_diff(scandir($resDir), ['.', '..']);
            if (empty($remainingFiles)) {
                @rmdir($resDir);
            }
        }
        
        // Eliminar directorio de videos según bandera
        if (is_dir($videoDir)) {
            if ($deleteVideos) {
                $remainingFiles = array_diff(scandir($videoDir), ['.', '..']);
                if (empty($remainingFiles)) {
                    @rmdir($videoDir);
                }
            }
            // Si no se eliminaron videos, solo limpiar si está vacío
            else {
                $remainingFiles = array_diff(scandir($videoDir), ['.', '..']);
                if (empty($remainingFiles)) {
                    @rmdir($videoDir);
                }
            }
        }
        
                $db->commit();
        
        Logger::deletion("✅ TRANSACCIÓN COMPLETADA EXITOSAMENTE - Sesión: $sessionId");
        Logger::deletion("🎯 FIN ELIMINACIÓN - Sesión: $sessionId", [
            'curso_id' => $id,
            'resultado' => 'exitoso',
            'videos_eliminados' => $deleteVideos ? count($videosToDelete) : 0
        ]);

        $response = ['success' => true, 'message' => 'Curso eliminado exitosamente'];
        if ($debug) {
            // En lugar de devolver debug en la respuesta, indicamos dónde revisar los logs
            $response['debug_info'] = "Revisa los logs en logs-viewer.php - Sesión: $sessionId";
        }
        
        jsonResponse($response);
    } catch (Exception $e) {
        $db->rollBack();
        Logger::deletion("❌ ERROR EN ELIMINACIÓN - Sesión: $sessionId", [
            'error' => $e->getMessage(),
            'archivo' => $e->getFile(),
            'linea' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

function handleImageUpload($file) {
    // Validar tipo de archivo
    if (!validateFileType($file['name'], ALLOWED_IMAGE_TYPES)) {
        return false;
    }
    
    // Validar tamaño (máximo 5MB para imágenes)
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    
    // Generar nombre único
    $fileName = generateUniqueFileName($file['name'], IMAGES_DIR);
    $targetPath = IMAGES_DIR . $fileName;
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }
    
    return false;
}
?>
