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
        // Obtener comentario específico
        $stmt = $db->prepare("SELECT * FROM comentarios_clases WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $comentario = $stmt->fetch();
        
        if ($comentario) {
            jsonResponse(['success' => true, 'data' => $comentario]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Comentario no encontrado'], 404);
        }
    } else if (isset($_GET['clase_id'])) {
        // Obtener comentarios de una clase
        $stmt = $db->prepare("SELECT * FROM comentarios_clases WHERE clase_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_GET['clase_id']]);
        $comentarios = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $comentarios]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Parámetro id o clase_id requerido'], 400);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['clase_id']) || !isset($input['comentario'])) {
        jsonResponse(['success' => false, 'message' => 'clase_id y comentario son obligatorios'], 400);
    }
    
    $clase_id = (int)$input['clase_id'];
    $comentario = cleanInput($input['comentario']);
    
    if (empty(trim($comentario))) {
        jsonResponse(['success' => false, 'message' => 'El comentario no puede estar vacío'], 400);
    }
    
    // Verificar que la clase existe
    $stmt = $db->prepare("SELECT id FROM clases WHERE id = ?");
    $stmt->execute([$clase_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Clase no encontrada'], 404);
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO comentarios_clases (clase_id, comentario) VALUES (?, ?)");
        $stmt->execute([$clase_id, $comentario]);
        
        $comentarioId = $db->lastInsertId();
        // Devolver el comentario completo
        $stmt = $db->prepare("SELECT * FROM comentarios_clases WHERE id = ?");
        $stmt->execute([$comentarioId]);
        $nuevo = $stmt->fetch();
        
        jsonResponse([
            'success' => true, 
            'message' => 'Comentario creado exitosamente',
            'data' => $nuevo
        ]);
    } catch (PDOException $e) {
        throw $e;
    }
}

function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id']) || !isset($input['comentario'])) {
        jsonResponse(['success' => false, 'message' => 'ID y comentario son obligatorios'], 400);
    }
    
    $id = (int)$input['id'];
    $comentario = cleanInput($input['comentario']);
    
    if (empty(trim($comentario))) {
        jsonResponse(['success' => false, 'message' => 'El comentario no puede estar vacío'], 400);
    }
    
    // Verificar si el comentario existe
    $stmt = $db->prepare("SELECT id FROM comentarios_clases WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Comentario no encontrado'], 404);
    }
    
    try {
        $stmt = $db->prepare("UPDATE comentarios_clases SET comentario = ? WHERE id = ?");
        $stmt->execute([$comentario, $id]);
        
        jsonResponse(['success' => true, 'message' => 'Comentario actualizado exitosamente']);
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
    
    // Verificar si el comentario existe
    $stmt = $db->prepare("SELECT id FROM comentarios_clases WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Comentario no encontrado'], 404);
    }
    
    $stmt = $db->prepare("DELETE FROM comentarios_clases WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Comentario eliminado exitosamente']);
}
?>
