<?php
// Desactivar mostrar errores para evitar que interfieran con JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../config/config.php';

header('Content-Type: application/json');

$db = getDatabase();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
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
        // Obtener sección específica
        $stmt = $db->prepare("SELECT * FROM secciones WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $seccion = $stmt->fetch();
        
        if ($seccion) {
            jsonResponse(['success' => true, 'data' => $seccion]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Sección no encontrada'], 404);
        }
    } else if (isset($_GET['curso_id'])) {
        // Obtener secciones de un curso
        $stmt = $db->prepare("SELECT * FROM secciones WHERE curso_id = ? ORDER BY orden, id");
        $stmt->execute([$_GET['curso_id']]);
        $secciones = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $secciones]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Parámetro curso_id requerido'], 400);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['nombre']) || empty(trim($input['nombre'])) || !isset($input['curso_id'])) {
        jsonResponse(['success' => false, 'message' => 'Nombre y curso_id son obligatorios'], 400);
    }
    
    $nombre = cleanInput($input['nombre']);
    $curso_id = (int)$input['curso_id'];
    $orden = isset($input['orden']) ? (int)$input['orden'] : 0;
    
    // Verificar que el curso existe
    $stmt = $db->prepare("SELECT id FROM cursos WHERE id = ?");
    $stmt->execute([$curso_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Curso no encontrado'], 404);
    }
    
    // Si no se especifica orden o viene 0, usar el siguiente disponible
    if ($orden <= 0) {
        $stmt = $db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 as next_orden FROM secciones WHERE curso_id = ?");
        $stmt->execute([$curso_id]);
        $orden = $stmt->fetch()['next_orden'];
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO secciones (nombre, curso_id, orden) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $curso_id, $orden]);
        
        $seccionId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true, 
            'message' => 'Sección creada exitosamente',
            'data' => ['id' => $seccionId]
        ]);
    } catch (PDOException $e) {
        throw $e;
    }
}

function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id']) || !isset($input['nombre']) || empty(trim($input['nombre']))) {
        jsonResponse(['success' => false, 'message' => 'ID y nombre son obligatorios'], 400);
    }
    
    $id = (int)$input['id'];
    $nombre = cleanInput($input['nombre']);
    $orden = isset($input['orden']) ? (int)$input['orden'] : 1;
    
    // Verificar si la sección existe
    $stmt = $db->prepare("SELECT id, curso_id FROM secciones WHERE id = ?");
    $stmt->execute([$id]);
    $seccion = $stmt->fetch();
    
    if (!$seccion) {
        jsonResponse(['success' => false, 'message' => 'Sección no encontrada'], 404);
    }
    
    try {
        $stmt = $db->prepare("UPDATE secciones SET nombre = ?, orden = ? WHERE id = ?");
        $stmt->execute([$nombre, $orden, $id]);
        
        jsonResponse(['success' => true, 'message' => 'Sección actualizada exitosamente']);
    } catch (PDOException $e) {
        throw $e;
    }
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID es obligatorio'], 400);
    }
    
    $id = (int)$input['id'];
    
    // Verificar si la sección existe
    $stmt = $db->prepare("SELECT id, curso_id FROM secciones WHERE id = ?");
    $stmt->execute([$id]);
    $seccion = $stmt->fetch();
    
    if (!$seccion) {
        jsonResponse(['success' => false, 'message' => 'Sección no encontrada'], 404);
    }
    
    // Verificar cuántas clases tiene
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM clases WHERE seccion_id = ?");
    $stmt->execute([$id]);
    $clasesCount = $stmt->fetch()['count'];
    
    try {
        // Iniciar transacción
        $db->beginTransaction();
        
        if ($clasesCount > 0) {
            // Obtener archivos de video para eliminar
            $stmt = $db->prepare("SELECT archivo_video FROM clases WHERE seccion_id = ?");
            $stmt->execute([$id]);
            $videos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Eliminar archivos de video
            $videoDir = VIDEOS_DIR . $seccion['curso_id'] . '/';
            foreach ($videos as $videoFile) {
                $fullPath = $videoDir . $videoFile;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }
        
        // Eliminar sección (las clases se eliminan en cascada)
        $stmt = $db->prepare("DELETE FROM secciones WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        jsonResponse(['success' => true, 'message' => 'Sección eliminada exitosamente']);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>
