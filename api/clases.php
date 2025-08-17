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
        // Obtener clase específica con información adicional
        $query = "
            SELECT c.*, s.nombre as seccion_nombre, s.curso_id,
                   cr.titulo as curso_titulo
            FROM clases c
            JOIN secciones s ON c.seccion_id = s.id
            JOIN cursos cr ON s.curso_id = cr.id
            WHERE c.id = ?
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$_GET['id']]);
        $clase = $stmt->fetch();
        
        if ($clase) {
            jsonResponse(['success' => true, 'data' => $clase]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
        }
    } else if (isset($_GET['seccion_id'])) {
        // Obtener clases de una sección
        $stmt = $db->prepare("SELECT * FROM clases WHERE seccion_id = ? ORDER BY orden, id");
        $stmt->execute([$_GET['seccion_id']]);
        $clases = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $clases]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Parámetro id o seccion_id requerido'], 400);
    }
}

function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID es obligatorio'], 400);
    }
    
    $id = (int)$input['id'];
    
    // Verificar si la clase existe
    $stmt = $db->prepare("SELECT * FROM clases WHERE id = ?");
    $stmt->execute([$id]);
    $clase = $stmt->fetch();
    
    if (!$clase) {
        jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
    }
    
    // Preparar campos a actualizar
    $updateFields = [];
    $updateValues = [];
    
    if (isset($input['titulo']) && !empty(trim($input['titulo']))) {
        $updateFields[] = 'titulo = ?';
        $updateValues[] = cleanInput($input['titulo']);
    }
    
    if (isset($input['duracion']) && is_numeric($input['duracion'])) {
        $updateFields[] = 'duracion = ?';
        $updateValues[] = (int)$input['duracion'];
    }
    
    if (isset($input['orden']) && is_numeric($input['orden'])) {
        $updateFields[] = 'orden = ?';
        $updateValues[] = (int)$input['orden'];
    }
    
    if (isset($input['seccion_id']) && is_numeric($input['seccion_id'])) {
        // Verificar que la nueva sección existe
        $stmt = $db->prepare("SELECT id FROM secciones WHERE id = ?");
        $stmt->execute([$input['seccion_id']]);
        if ($stmt->fetch()) {
            $updateFields[] = 'seccion_id = ?';
            $updateValues[] = (int)$input['seccion_id'];
        } else {
            jsonResponse(['success' => false, 'message' => 'Sección no encontrada'], 400);
        }
    }
    
    if (empty($updateFields)) {
        jsonResponse(['success' => false, 'message' => 'No hay campos para actualizar'], 400);
    }
    
    $updateValues[] = $id; // Para el WHERE
    
    try {
        $sql = "UPDATE clases SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($updateValues);
        
        jsonResponse(['success' => true, 'message' => 'Clase actualizada exitosamente']);
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
    $deleteVideo = isset($input['deleteVideo']) ? (bool)$input['deleteVideo'] : false;
    
    // Obtener información de la clase
    $query = "
        SELECT c.*, s.curso_id 
        FROM clases c
        JOIN secciones s ON c.seccion_id = s.id
        WHERE c.id = ?
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $clase = $stmt->fetch();
    
    if (!$clase) {
        jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
    }
    
    try {
        // Iniciar transacción
        $db->beginTransaction();
        
        // Eliminar registros relacionados (progreso, notas, comentarios)
        $stmt = $db->prepare("DELETE FROM progreso_clases WHERE clase_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $db->prepare("DELETE FROM notas_clases WHERE clase_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $db->prepare("DELETE FROM comentarios_clases WHERE clase_id = ?");
        $stmt->execute([$id]);
        
        // Eliminar la clase
        $stmt = $db->prepare("DELETE FROM clases WHERE id = ?");
        $stmt->execute([$id]);
        
        // Eliminar archivo de video si se solicita
        if ($deleteVideo && $clase['archivo_video']) {
            $videoPath = VIDEOS_DIR . $clase['curso_id'] . '/' . $clase['archivo_video'];
            if (file_exists($videoPath)) {
                unlink($videoPath);
            }
        }
        
        $db->commit();
        
        jsonResponse(['success' => true, 'message' => 'Clase eliminada exitosamente']);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>
