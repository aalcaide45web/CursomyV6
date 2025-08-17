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
        // Obtener temática específica
        $stmt = $db->prepare("SELECT * FROM tematicas WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $tematica = $stmt->fetch();
        
        if ($tematica) {
            jsonResponse(['success' => true, 'data' => $tematica]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Temática no encontrada'], 404);
        }
    } else {
        // Obtener todas las temáticas
        $tematicas = $db->query("SELECT * FROM tematicas ORDER BY nombre")->fetchAll();
        jsonResponse(['success' => true, 'data' => $tematicas]);
    }
}

function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['nombre']) || empty(trim($input['nombre']))) {
        jsonResponse(['success' => false, 'message' => 'El nombre es obligatorio'], 400);
    }
    
    $nombre = cleanInput($input['nombre']);
    $descripcion = isset($input['descripcion']) ? cleanInput($input['descripcion']) : null;
    
    // Verificar si el nombre ya existe
    $stmt = $db->prepare("SELECT id FROM tematicas WHERE nombre = ?");
    $stmt->execute([$nombre]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Ya existe una temática con ese nombre'], 400);
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO tematicas (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([$nombre, $descripcion]);
        
        $tematicaId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true, 
            'message' => 'Temática creada exitosamente',
            'data' => ['id' => $tematicaId]
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Constraint violation
            jsonResponse(['success' => false, 'message' => 'Ya existe una temática con ese nombre'], 400);
        }
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
    $descripcion = isset($input['descripcion']) ? cleanInput($input['descripcion']) : null;
    
    // Verificar si la temática existe
    $stmt = $db->prepare("SELECT id FROM tematicas WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Temática no encontrada'], 404);
    }
    
    // Verificar si el nombre ya existe en otra temática
    $stmt = $db->prepare("SELECT id FROM tematicas WHERE nombre = ? AND id != ?");
    $stmt->execute([$nombre, $id]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Ya existe una temática con ese nombre'], 400);
    }
    
    try {
        $stmt = $db->prepare("UPDATE tematicas SET nombre = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $id]);
        
        jsonResponse(['success' => true, 'message' => 'Temática actualizada exitosamente']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Constraint violation
            jsonResponse(['success' => false, 'message' => 'Ya existe una temática con ese nombre'], 400);
        }
        throw $e;
    }
}

function handleDelete($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID es obligatorio'], 400);
    }
    
    $id = (int)$input['id'];
    
    // Verificar si la temática existe
    $stmt = $db->prepare("SELECT id FROM tematicas WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Temática no encontrada'], 404);
    }
    
    // Verificar si la temática tiene cursos asociados
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cursos WHERE tematica_id = ?");
    $stmt->execute([$id]);
    $cursosCount = $stmt->fetch()['count'];
    
    if ($cursosCount > 0) {
        jsonResponse([
            'success' => false, 
            'message' => "No se puede eliminar la temática porque tiene {$cursosCount} curso(s) asociado(s)"
        ], 400);
    }
    
    $stmt = $db->prepare("DELETE FROM tematicas WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'Temática eliminada exitosamente']);
}
?>
